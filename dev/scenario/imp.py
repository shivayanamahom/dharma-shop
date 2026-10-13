"""Помощники сценариев импорта: профиль через форму админки, загрузка файла, запуск, подтверждение и итоги запуска.

Работает как человек в админке (формы Joomla, общий JSON-шаг окна прогресса); данные читаются из базы только для проверки.
"""
from __future__ import annotations

import os
import re
import time

from lib import Site, db

IMPORT = "/administrator/index.php?option=com_dharmashop_import"


class Importer:
    def __init__(self, admin: Site) -> None:
        self.a = admin

    # --- файл
    def upload(self, path: str) -> str:
        page = self.a.get(IMPORT + "&view=profiles")
        token = self.a.token(page.text)
        with open(path, "rb") as fh:
            r = self.a.post(IMPORT + "&task=job.upload&format=json", {token: "1"}, files={"file": (os.path.basename(path), fh, "text/csv")})
        data = r.json()
        if not data.get("success", True) or "path" not in (data.get("data") or data):
            raise RuntimeError(f"загрузка отклонена: {data}")
        return (data.get("data") or data)["path"]

    # --- профиль
    def save_profile(self, title: str, entity: str, params: dict, csv_path: str | None = None, mapping: dict[int, str] | None = None,
                     profile_id: int | None = None, fmt: str = "csv") -> int:
        """Создаёт или правит профиль. mapping: номер колонки → назначение (остальное остаётся как подобрало автосопоставление)."""
        url = IMPORT + (f"&task=profile.edit&id={profile_id}" if profile_id else "&task=profile.add")
        fields = {"jform[title]": title, "jform[entity]": entity, "jform[format]": fmt}
        fields.update({f"jform[params][{k}]": v for k, v in params.items()})
        if csv_path:
            fields["jform[params][source_type]"] = "upload"
            fields["jform[params][source_file][0][path]"] = self.upload(csv_path)
        r = self.a.submit_form(url, fields, task="profile.apply")
        msgs = self.a.messages(r.text)
        if not any("saved" in m.lower() or "сохранен" in m.lower() for m in msgs):
            raise RuntimeError(f"профиль не сохранён: {msgs[:2]}")
        pid = profile_id or int(re.search(r"[?&]id=(\d+)", r.url).group(1))
        if mapping is not None:
            page = self.a.get(IMPORT + f"&view=profile&layout=edit&id={pid}")
            _, current = Site.parse_form(page.text)
            names = {}
            for k, v in current:
                m = re.fullmatch(r"jform\[params\]\[mapping\]\[(mapping\d+)\]\[column\]", k)
                if m:
                    names[int(v)] = m.group(1)
            over = {}
            for column, group in names.items():
                over[f"jform[params][mapping][{group}][target]"] = mapping.get(column, "")
            r = self.a.submit_form(IMPORT + f"&view=profile&layout=edit&id={pid}", over, task="profile.apply")
            if not any("saved" in m.lower() or "сохранен" in m.lower() for m in self.a.messages(r.text)):
                raise RuntimeError(f"сопоставление не сохранено: {self.a.messages(r.text)[:2]}")
        return pid

    # --- запуск
    def _tok(self) -> str:
        return self.a.token(self.a.get(IMPORT + "&view=profiles").text)

    def start(self, pid: int) -> int:
        r = self.a.post(IMPORT, {"id": pid, "task": "job.start", self._tok(): "1"})
        m = re.search(r"[?&]id=(\d+)", r.url)
        if "view=job" not in r.url or not m:
            raise RuntimeError(f"запуск не создан: {self.a.messages(r.text)[:2]}")
        return int(m.group(1))

    def state(self, job: int) -> str:
        return db(f"SELECT state FROM PFX_dharmashop_import_jobs WHERE id = {job}")[0][0]

    def drive(self, job: int, until: tuple[str, ...], limit_s: float = 900) -> str:
        tok, t0 = self._tok(), time.time()
        while self.state(job) not in until and time.time() - t0 < limit_s:
            r = self.a.post(IMPORT + "&task=job.step&format=json", {"id": job, tok: "1"})
            try:
                data = r.json()
            except ValueError:
                raise RuntimeError(f"шаг вернул не JSON: {r.status_code} {r.text[:200]}")
            if not data.get("success"):
                raise RuntimeError(f"шаг отклонён: {data}")
            if data["state"] in ("failed", "cancelled"):
                break
        return self.state(job)

    def run(self, pid: int, approve: bool = True, limit_s: float = 900) -> dict:
        job = self.start(pid)
        st = self.drive(job, ("checked", "done", "failed", "cancelled"), limit_s)
        report = {"job": job, "after_check": st, "check": self.summary(job)}
        if st == "checked" and approve:
            self.a.post(IMPORT, {"id": job, "task": "job.approve", self._tok(): "1"})
            if self.state(job) == "checked":  # подтверждение отклонено (например, есть ошибки без разрешения их пропускать)
                st = "checked"
            else:
                st = self.drive(job, ("done", "failed", "cancelled"), limit_s)
        report.update({"job": job, "state": st, "summary": self.summary(job)})
        return report

    def summary(self, job: int) -> dict:
        j = db(f"SELECT state, total, processed, created_count, updated_count, unchanged_count, errors, COALESCE(error,'') FROM PFX_dharmashop_import_jobs WHERE id = {job}")[0]
        rows = dict(db(f"SELECT state, COUNT(*) FROM PFX_dharmashop_import_rows WHERE job_id = {job} GROUP BY state"))
        actions = dict(db(f"SELECT COALESCE(action,'-'), COUNT(*) FROM PFX_dharmashop_import_rows WHERE job_id = {job} GROUP BY action"))
        return {"state": j[0], "total": int(j[1]), "created": int(j[3]), "updated": int(j[4]), "unchanged": int(j[5]), "errors": int(j[6]), "error": j[7], "rows": rows, "actions": actions}

    def errors(self, job: int, limit: int = 20) -> list[tuple[str, str, str]]:
        return [(r[0], r[1], r[2]) for r in db(f"SELECT line, COALESCE(source_key,''), COALESCE(error,'') FROM PFX_dharmashop_import_rows WHERE job_id = {job} AND state = 'error' ORDER BY id LIMIT {limit}")]
