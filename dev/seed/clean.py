#!/usr/bin/env python3
"""Удаление только записей и файлов из dev/seed/manifest.json штатными действиями."""
from __future__ import annotations

import json
import os
import re
import subprocess
import urllib.parse
import hashlib
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SITE = Path(os.environ.get("DHARMA_SITE", "/Volumes/Work/Server/www/test")).resolve()
BASE = os.environ.get("DHARMA_URL", "https://test.test").rstrip("/")
COOKIE_PATH = Path(os.environ.get("COOKIES", "/tmp/dharma-seed-100.cookies"))
NOTE = os.environ.get("SEED_NOTE", "seed-100")
MANIFEST = ROOT / os.environ.get("SEED_MANIFEST", "dev/seed/manifest.json")
if BASE != "https://test.test":
    raise SystemExit("Отказ: очистка разрешена только на https://test.test")

data = json.loads(MANIFEST.read_text(encoding="utf-8"))
if data.get("seed") != NOTE or data.get("base_url") != BASE:
    raise SystemExit("Манифест не подтверждает набор на test.test")
def req(url: str, pairs: list[tuple[str, str]] | None = None):
    args = ["curl", "-skL", "-b", str(COOKIE_PATH), "-c", str(COOKIE_PATH), "--max-time", "60", "-w", "\n__FINAL__%{url_effective}\t%{http_code}"]
    for key, value in pairs or []:
        args.extend(["--data-urlencode", f"{key}={value}"])
    args.append(BASE + url)
    result = subprocess.run(args, cwd=ROOT, capture_output=True, timeout=70)
    output = result.stdout
    marker = output.rfind(b"\n__FINAL__")
    if result.returncode or marker < 0:
        raise RuntimeError(f"curl не выполнил запрос {url}: {result.stderr.decode('utf-8', 'replace')[:300]}")
    body, trailer = output[:marker], output[marker + len(b"\n__FINAL__"):].decode().strip()
    final_url, status = trailer.rsplit("\t", 1)
    if int(status) >= 400:
        raise RuntimeError(f"HTTP {status} для {url}: {body[:300].decode('utf-8', 'replace')}")
    return final_url, body.decode("utf-8", "replace")


def token(url: str) -> str:
    _, body = req(url)
    match = re.search(r'name="([0-9a-f]{32})" value="1"', body)
    if not match:
        raise RuntimeError(f"Не найден токен Joomla на {url}")
    return match.group(1)


def action(url: str, task: str, ids: list[int], extension: str | None = None):
    pairs = [("task", task)] + [("cid[]", str(i)) for i in ids] + [(token(url), "1")]
    req(url, pairs)


def sql(query: str) -> list[list[str]]:
    result = subprocess.run([str(ROOT / "dev/db.sh"), query], cwd=ROOT, text=True, capture_output=True)
    if result.returncode:
        raise RuntimeError(result.stderr.strip() or result.stdout.strip())
    return [line.split("\t") for line in result.stdout.splitlines() if line.strip()]


products = sorted({int(p["id"]) for p in data.get("products", [])})
if products:
    present = [int(row[0]) for row in sql("select id from PFX_dharmashop_products where id in (" + ",".join(map(str, products)) + ")")]
    if present:
        states = {int(row[0]): int(row[1]) for row in sql("select id,state from PFX_dharmashop_products where id in (" + ",".join(map(str, present)) + ")")}
        to_trash = [pid for pid in present if states[pid] != -2]
        if to_trash:
            page = "/administrator/index.php?option=com_dharmashop&view=products"
            action(page, "products.trash", to_trash)
        trashed = [int(row[0]) for row in sql("select id from PFX_dharmashop_products where id in (" + ",".join(map(str, present)) + ") and state=-2")]
        if trashed:
            page = "/administrator/index.php?option=com_dharmashop&view=products&filter_state=-2"
            action(page, "products.delete", trashed)
        remaining = sql("select id from PFX_dharmashop_products where id in (" + ",".join(map(str, products)) + ")")
        if remaining:
            raise RuntimeError(f"Товары не удалились штатным действием: {remaining}")
        print(f"Удалено товаров: {len(present)}")

# Remove only exact image paths from the manifest, after the product galleries were removed.
for item in data.get("files", []):
    path = str(item.get("path", ""))
    if not re.fullmatch(r"images/dharmashop/products/\d{4}/[a-f0-9]{10}\.png", path):
        print(f"Пропуск файла вне допустимого шаблона: {path}")
        continue
    refs = sql("select count(*) from PFX_dharmashop_field_media where path='" + path.replace("'", "''") + "'")
    if int(refs[0][0]):
        print(f"Файл сохранён: на него ещё ссылаются записи ({path})")
        continue
    target = (SITE / path).resolve()
    if SITE not in target.parents:
        raise RuntimeError(f"Путь файла выходит за пределы сайта: {path}")
    if target.is_file():
        expected = item.get("sha256")
        if expected and hashlib.sha256(target.read_bytes()).hexdigest() != expected:
            print(f"Файл изменён после загрузки, оставлен: {path}")
            continue
        target.unlink()
        print(f"Удалён файл: {path}")

# Child-first deletion; validate ownership by both manifest ID and note before each standard action.
taxonomy = []
for group in ("categories", "badges", "brands"):
    taxonomy.extend(data.get(group, []))
if taxonomy:
    ids = ",".join(str(int(row["id"])) for row in taxonomy)
    levels = {int(row[0]): int(row[1]) for row in sql("select id,level from PFX_categories where id in (" + ids + ")")}
    taxonomy.sort(key=lambda row: levels.get(int(row["id"]), 0), reverse=True)
for row in taxonomy:
    cid = int(row["id"])
    extension = str(row["extension"])
    found = sql("select id from PFX_categories where id=" + str(cid) + " and note='" + NOTE + "' and extension='" + extension.replace("'", "''") + "'")
    if not found:
        continue
    url = "/administrator/index.php?option=com_dharmashop&view=categories&extension=" + urllib.parse.quote(extension)
    action(url, "categories.delete", [cid], extension)
    if sql("select id from PFX_categories where id=" + str(cid)):
        print(f"Не удалена штатным действием (проверьте права или пустоту): {cid} {row['title']}")
print(f"Обработано таксономий: {len(taxonomy)} (только ID из манифеста с заметкой набора)")

for group in ("categories", "brands", "badges"):
    data[group] = [row for row in data.get(group, []) if sql("select id from PFX_categories where id=" + str(int(row["id"])) + " and note='" + NOTE + "'")]
data["products"] = []
data["files"] = [item for item in data.get("files", []) if (SITE / str(item.get("path", ""))).is_file()]
data["cleaned_at"] = __import__("datetime").datetime.now().isoformat(timespec="seconds")
MANIFEST.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
