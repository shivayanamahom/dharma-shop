#!/usr/bin/env python3
"""Текст релиза GitHub для Dharma Shop: блоки «Новая установка» и «Обновление», список изменившихся расширений и раздел из CHANGELOG.md.

  python3 dev/release-notes.py ВЕРСИЯ --repo ВЛАДЕЛЕЦ/РЕПОЗИТОРИЙ [--since ТЕГ]

Без --since предыдущий выпуск берётся как ближайший более ранний тег vX.Y.Z; если его нет, список изменений расширений пропускается.
Печатает Markdown в stdout (его использует .github/workflows/release.yml).
"""
from __future__ import annotations

import argparse
import importlib.util
import os
import re
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def sh(*args: str) -> str:
    return subprocess.run(args, cwd=ROOT, capture_output=True, text=True, check=True).stdout


def vkey(v: str):
    return [int(x) if x.isdigit() else x for x in re.split(r"[.\-]", v)]


def previous_tag(version: str) -> str | None:
    tags = [t.strip() for t in sh("git", "tag", "--list", "v*").splitlines()]
    older = [t for t in tags if re.fullmatch(r"v\d+\.\d+\.\d+.*", t) and vkey(t[1:]) < vkey(version)]
    return max(older, key=lambda t: vkey(t[1:])) if older else None


def changelog_section(version: str) -> str:
    text = open(os.path.join(ROOT, "CHANGELOG.md"), encoding="utf-8").read()
    m = re.search(rf"^## {re.escape(version)}\b[^\n]*\n(.*?)(?=^## |\Z)", text, flags=re.S | re.M)
    return m.group(1).strip() if m else ""


def load_release():
    spec = importlib.util.spec_from_file_location("dharma_release", os.path.join(ROOT, "dev", "release.py"))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def changed_extensions(since: str) -> tuple[list[str], int]:
    """Расширения, у которых с `since` изменилось что-то кроме номера версии и даты в манифесте
    (повышение версии трогает манифест каждого расширения, поэтому `release.py status` показывает «изменились все»)."""
    exts = [e for e in load_release().extensions() if e["type"] != "package"]
    names = []
    for e in exts:
        diff = sh("git", "diff", "-U0", "--no-color", since, "--", e["dir"])
        lines = [l for l in diff.splitlines() if l[:1] in "+-" and not l.startswith(("+++", "---"))]
        if any(not re.search(r"<(version|creationDate)>", l) for l in lines):
            names.append(e["name"])
        elif sh("git", "ls-files", "--others", "--exclude-standard", "--", e["dir"]).strip():
            names.append(e["name"])
    return names, len(exts)


def main() -> int:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("version")
    p.add_argument("--repo", required=True)
    p.add_argument("--since")
    args = p.parse_args()

    v, repo = args.version, args.repo
    url = f"https://github.com/{repo}/releases/download/v{v}/pkg_dharmashop-{v}.zip"
    logo = f"https://raw.githubusercontent.com/{repo}/main/docs/release-logo.png"
    since = args.since or previous_tag(v)

    lines = [
        f"![Dharma Shop]({logo})",
        "",
        f"# Dharma Shop {v}",
        "",
        "Пакет Dharma Shop для Joomla 6 (PHP 8.3+).",
        "",
        "## 🆕 Новая установка",
        "",
        "Скопируйте ссылку и вставьте её в Joomla: **Система → Установка → Расширения → Установить по URL-адресу**, затем нажмите «Проверить и установить».",
        "",
        "```text",
        url,
        "```",
        "",
        f"Или [скачайте zip]({url}) и загрузите его на вкладке «Загрузить файл пакета». [Контрольная сумма SHA-256]({url}.sha256).",
        "",
        "## 🔄 Обновление",
        "",
        "Для уже установленного магазина: **Система → Обновления → Расширения → «Найти обновления»**, затем «Обновить» в строке «Dharma Shop (пакет)». "
        "Данные магазина сохраняются, изменения схемы базы Joomla применит сама. Перед обновлением сделайте резервную копию базы и файлов.",
        "",
        "Если обновление через Joomla недоступно, установите тот же пакет вручную поверх (ссылка выше): Joomla обновит все расширения.",
    ]

    if since:
        names, total = changed_extensions(since)
        if names:
            lines += ["", f"**Изменились расширения ({len(names)} из {total}, с {since}):** " + ", ".join(f"`{n}`" for n in names)]

    section = changelog_section(v)
    if section:
        lines += ["", f"## Изменения в версии {v}", "", section]

    print("\n".join(lines))
    return 0


if __name__ == "__main__":
    sys.exit(main())
