"""Сценарная проверка Dharma Shop на чистой установке: клиент админки и сайта (формы Joomla как у человека).

Работает только с локальными адресами разработки (`*.test`, `localhost`, `127.0.0.1`). Логин и пароль — переменные окружения
SCENARIO_USER и SCENARIO_PASS (создайте тестового суперпользователя; в репозиторий они не попадают).
"""
from __future__ import annotations

import os
import re
import sys
import urllib.parse
import warnings

warnings.filterwarnings("ignore")
import requests  # noqa: E402
from bs4 import BeautifulSoup  # noqa: E402

requests.packages.urllib3.disable_warnings()  # type: ignore[attr-defined]


def allowed(base: str) -> bool:
    host = urllib.parse.urlparse(base).hostname or ""
    return host in {"localhost", "127.0.0.1", "::1"} or host.endswith(".test") or host.endswith(".localhost")


class Site:
    def __init__(self, base: str | None = None):
        self.base = (base or os.environ.get("SCENARIO_URL", "")).rstrip("/")
        if not self.base or not allowed(self.base):
            raise SystemExit("Отказ: SCENARIO_URL должен быть локальным адресом разработки (*.test, localhost, 127.0.0.1)")
        self.http = requests.Session()
        self.http.verify = False
        self.http.headers["User-Agent"] = "dharma-scenario/1"
        self.admin = False
        self.problems: list[str] = []

    # --- базовые запросы
    def url(self, path: str) -> str:
        return path if path.startswith("http") else self.base + path

    def get(self, path: str, **kw) -> requests.Response:
        r = self.http.get(self.url(path), timeout=90, allow_redirects=kw.pop("allow_redirects", True), **kw)
        self._scan(r, path)
        return r

    def post(self, path: str, data=None, **kw) -> requests.Response:
        r = self.http.post(self.url(path), data=data, timeout=120, allow_redirects=kw.pop("allow_redirects", True), **kw)
        self._scan(r, path)
        return r

    def _scan(self, r: requests.Response, path: str) -> None:
        """Любой ответ с предупреждением PHP или фатальной ошибкой записывается в проблемы сценария."""
        text = r.text if "html" in r.headers.get("content-type", "html") or "json" in r.headers.get("content-type", "") else ""
        if re.search(r"(Warning|Deprecated|Notice|Fatal error): ", text) or r.status_code >= 500:
            m = re.search(r"((Warning|Deprecated|Notice|Fatal error): [^\n<]{0,200})", text)
            self.problems.append(f"{r.status_code} {path} :: {m.group(1) if m else 'HTTP ' + str(r.status_code)}")

    # --- вход
    def login(self, user: str | None = None, password: str | None = None) -> None:
        user = user or os.environ["SCENARIO_USER"]
        password = password or os.environ["SCENARIO_PASS"]
        page = self.get("/administrator/index.php")
        token = self.token(page.text)
        r = self.post("/administrator/index.php", {"username": user, "passwd": password, "option": "com_login", "task": "login", "return": "", token: "1"})
        if "mod-login" in r.text and "logout" not in r.text.lower():
            raise SystemExit("Вход в админку не удался")
        self.admin = True

    def site_login(self, user: str, password: str) -> bool:
        """Вход посетителя на сайте через форму входа (с токеном), как это делает человек."""
        page = self.get("/index.php/component/users/login")
        try:
            token = self.token(page.text)
        except RuntimeError:
            return False
        r = self.post("/index.php?option=com_users&task=user.login", {"username": user, "password": password, "return": "", token: "1"})
        return "task=user.logout" in r.text or "user.logout" in r.text or "Log out" in r.text

    @staticmethod
    def token(html: str) -> str:
        m = re.search(r'name="([0-9a-f]{32})" value="1"', html)
        if not m:
            raise RuntimeError("токен формы не найден")
        return m.group(1)

    # --- формы
    @staticmethod
    def parse_form(html: str, form_id: str | None = None):
        soup = BeautifulSoup(html, "html.parser")
        form = soup.find("form", id=form_id) if form_id else None
        if form is None:
            form = soup.find("form", attrs={"name": "adminForm"}) or soup.find("form", id=re.compile("form|adminForm"))
        if form is None:
            raise RuntimeError("форма не найдена")
        fields: list[tuple[str, str]] = []
        for el in form.find_all(["input", "select", "textarea"]):
            name = el.get("name")
            if not name or el.has_attr("disabled") or el.find_parent("template") is not None or name.startswith("jform[gallery][_]"):
                continue
            if el.name == "textarea":
                fields.append((name, el.text.lstrip("\n")))
            elif el.name == "select":
                opts = el.find_all("option")
                chosen = [o for o in opts if o.has_attr("selected")]
                if el.has_attr("multiple"):
                    for o in chosen:
                        fields.append((name, o.get("value", o.text)))
                else:
                    pick = chosen[0] if chosen else (opts[0] if opts else None)
                    if pick is not None:
                        fields.append((name, pick.get("value", pick.text)))
            else:
                t = (el.get("type") or "text").lower()
                if t in {"submit", "button", "image", "file", "reset"}:
                    continue
                if t in {"checkbox", "radio"}:
                    if el.has_attr("checked"):
                        fields.append((name, el.get("value", "1")))
                else:
                    fields.append((name, el.get("value", "")))
        return form.get("action") or "", fields

    def submit_form(self, edit_url: str, overrides: dict | None = None, task: str = "apply", remove_prefixes: tuple[str, ...] = ()):
        """Открыть форму (GET), подставить значения, отправить с задачей `task`. Возвращает ответ после перенаправления.

        overrides: имя поля → строка или список строк (несколько значений); None удаляет поле."""
        page = self.get(edit_url)
        action, fields = self.parse_form(page.text)
        data: list[tuple[str, str]] = [(k, v) for k, v in fields if k != "task"]
        for name, value in (overrides or {}).items():
            data = [(k, v) for k, v in data if k != name]
            if value is None:
                continue
            for item in (value if isinstance(value, list) else [value]):
                data.append((name, str(item)))
        for prefix in remove_prefixes:
            data = [(k, v) for k, v in data if not k.startswith(prefix)]
        data.append(("task", task))
        target = urllib.parse.urljoin(page.url, action) if action else page.url
        r = self.post(target, data)
        return r

    def messages(self, html: str) -> list[str]:
        soup = BeautifulSoup(html, "html.parser")
        out = []
        for el in soup.select("joomla-alert, .alert-message, .alert .alert-message, #system-message-container .alert"):
            t = " ".join(el.get_text(" ", strip=True).split())
            if t:
                out.append(t)
        return out


def ok(cond: bool, name: str, results: list) -> bool:
    results.append((bool(cond), name))
    print(("PASS " if cond else "FAIL ") + name)
    return bool(cond)


def db(sql: str, site_path: str | None = None) -> list[list[str]]:
    """SELECT к базе проверяемого сайта (читает configuration.php; запись не нужна). PFX_ заменяется префиксом."""
    import subprocess
    env = dict(os.environ, DHARMA_SITE=site_path or os.environ.get("SCENARIO_SITE_PATH", ""))
    if not env["DHARMA_SITE"]:
        raise SystemExit("SCENARIO_SITE_PATH не задан")
    out = subprocess.run(["bash", os.path.join(os.path.dirname(__file__), "..", "db.sh"), sql], capture_output=True, text=True, env=env).stdout
    return [line.split("\t") for line in out.splitlines() if line.strip()]


def make_image(path: str, title: str, color: tuple[int, int, int], size: tuple[int, int] = (800, 800)) -> None:
    """Картинка-заглушка с названием (для загрузки в галерею товара)."""
    from PIL import Image, ImageDraw, ImageFont
    img = Image.new("RGB", size, color)
    draw = ImageDraw.Draw(img)
    for i in range(0, size[1], 40):
        draw.line([(0, i), (size[0], size[1] - i)], fill=tuple(min(255, c + 25) for c in color), width=2)
    try:
        font = ImageFont.truetype("/System/Library/Fonts/Supplemental/Arial.ttf", 44)
    except OSError:
        font = ImageFont.load_default()
    words = title.split()
    lines, cur = [], ""
    for w in words:
        if len(cur + " " + w) > 18:
            lines.append(cur.strip())
            cur = w
        else:
            cur += " " + w
    lines.append(cur.strip())
    y = size[1] // 2 - 30 * len(lines)
    for line in lines:
        w = draw.textlength(line, font=font)
        draw.text(((size[0] - w) / 2, y), line, fill=(255, 255, 255), font=font)
        y += 60
    img.save(path, "PNG")


def upload_image(site: "Site", path: str, folder: str = "products") -> str:
    page = site.get("/administrator/index.php?option=com_dharmashop&view=products")
    token = site.token(page.text)
    with open(path, "rb") as fh:
        r = site.post("/administrator/index.php?option=com_dharmashop&task=media.upload&format=json", {"folder": folder, token: "1"}, files={"file": (os.path.basename(path), fh, "image/png")})
    data = r.json()
    if not data.get("success"):
        raise RuntimeError(f"Загрузчик отклонил файл: {data}")
    return str(data["data"]["path"])


def enable_plugin(site: "Site", folder: str, element: str) -> bool:
    """Включить плагин из списка плагинов Joomla (как кнопкой «Включить»)."""
    import subprocess  # noqa: F401
    rows = db(f"SELECT extension_id, enabled FROM PFX_extensions WHERE type='plugin' AND folder='{folder}' AND element='{element}' LIMIT 1")
    if not rows:
        raise RuntimeError(f"плагин {folder}/{element} не установлен")
    ext_id, enabled = rows[0]
    if enabled == "1":
        return True
    page = site.get("/administrator/index.php?option=com_plugins&view=plugins")
    token = site.token(page.text)
    r = site.post("/administrator/index.php?option=com_plugins&view=plugins", {"cid[]": ext_id, "task": "plugins.publish", token: "1", "boxchecked": "1"})
    again = db(f"SELECT enabled FROM PFX_extensions WHERE extension_id={ext_id}")
    return bool(again and again[0][0] == "1")
