#!/usr/bin/env python3
"""Выпуск версий и ленты обновлений Joomla для пакета и каждого расширения Dharma Shop.

  python3 dev/release.py status [--since ТЕГ]                        какие расширения изменились с прошлого выпуска (справочно)
  python3 dev/release.py bump ВЕРСИЯ [--since ТЕГ]                   версия ВЕРСИЯ у пакета и у всех расширений; <updateservers> только в манифесте пакета
  python3 dev/release.py feed ВЕРСИЯ --out ПАПКА --download-base URL [--channel dev|alpha|beta|rc|stable] [--tag ТЕГ]
                                                                    собирает пакет, пишет ленту ПАПКА/updates/pkg_dharmashop.xml, коллекцию dharma-shop.xml
                                                                    и архив пакета ПАПКА/assets/<тег>/

Правило версий: обновляется только пакет, поэтому все расширения получают версию выпуска (в списке расширений Joomla один номер).
Лента одна — у пакета; коллекция dharma-shop.xml (адрес, зарегистрированный у уже установленных сайтов) содержит одну запись — пакет.
Источник списка расширений — pkg_dharmashop.xml. Вручную XML лент никто не правит.
"""
from __future__ import annotations

import argparse
import glob
import hashlib
import os
import re
import shutil
import subprocess
import sys
import xml.etree.ElementTree as ET
from xml.sax.saxutils import escape

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FEED_BASE = "https://shivayanamahom.github.io/dharma-shop-updates/updates/"
SITE = "https://github.com/shivayanamahom/dharma-shop-updates"
KEEP_VERSIONS = 5


def sh(*args: str) -> str:
    return subprocess.run(args, cwd=ROOT, capture_output=True, text=True, check=True).stdout


def extensions() -> list[dict]:
    """Расширения пакета из pkg_dharmashop.xml (+ сам пакет)."""
    pkg = open(os.path.join(ROOT, "pkg_dharmashop.xml"), encoding="utf-8").read()
    items = []
    for m in re.finditer(r'<file type="(\w+)"((?: \w+="[^"]*")*)>([^<]+)</file>', pkg):
        kind, attrs, zipname = m.group(1), dict(re.findall(r'(\w+)="([^"]*)"', m.group(2))), m.group(3)
        folder = zipname[:-4]
        directory = folder if os.path.isdir(os.path.join(ROOT, folder)) else os.path.join("integrations", folder)
        manifest = None
        for x in sorted(glob.glob(os.path.join(ROOT, directory, "*.xml"))):
            if "<extension" in open(x, encoding="utf-8").read(600):
                manifest = x
                break
        if not manifest:
            raise SystemExit(f"нет манифеста у {folder}")
        text = open(manifest, encoding="utf-8").read()
        element = attrs.get("id", folder)
        items.append({
            "type": kind, "element": element if kind != "file" else folder, "folder": attrs.get("group", ""), "client": "administrator" if kind == "component" else "site",
            "dir": directory, "zip": zipname, "name": folder, "manifest": manifest, "version": re.search(r"<version>([^<]+)</version>", text).group(1),
            "const": re.search(r"<name>([^<]+)</name>", text).group(1),
        })
    pm = os.path.join(ROOT, "pkg_dharmashop.xml")
    items.append({"type": "package", "element": "pkg_dharmashop", "folder": "", "client": "site", "dir": "", "zip": "pkg_dharmashop.zip", "name": "pkg_dharmashop", "manifest": pm,
                  "version": re.search(r"<version>([^<]+)</version>", pkg).group(1), "const": "PKG_DHARMASHOP"})
    return items


def title(ext: dict) -> str:
    """Читаемое имя из языкового файла расширения (русский, затем английский); иначе — имя папки."""
    for lang in ("ru-RU", "en-GB"):
        for f in glob.glob(os.path.join(ROOT, ext["dir"] or "language", "**", lang, "*.ini"), recursive=True) + glob.glob(os.path.join(ROOT, "language", lang, "pkg_dharmashop*.ini")):
            for line in open(f, encoding="utf-8"):
                if line.startswith(ext["const"] + "="):
                    return line.split("=", 1)[1].strip().strip('"')
    # Joomla's action log writes manifest <name> verbatim and does not translate it.
    # Keep human-readable names from module manifests instead of falling back to folders.
    manifest_name = ext["const"]
    return manifest_name if not re.fullmatch(r"[A-Z][A-Z0-9_]*", manifest_name) else ext["name"]


def changed(exts: list[dict], since: str | None) -> set[str]:
    if not since:
        return {e["name"] for e in exts}
    out = set()
    for e in exts:
        if e["type"] == "package":
            continue
        diff = sh("git", "diff", "--name-only", since, "--", e["dir"]).strip()
        untracked = sh("git", "ls-files", "--others", "--exclude-standard", "--", e["dir"]).strip()
        if diff or untracked:
            out.add(e["name"])
    return out


def set_manifest(ext: dict, version: str | None) -> None:
    text = open(ext["manifest"], encoding="utf-8").read()
    if version:
        text = re.sub(r"<version>[^<]*</version>", f"<version>{version}</version>", text, count=1)
    block = f'<updateservers><server type="collection" priority="1" name="Dharma Shop">{FEED_BASE}dharma-shop.xml</server></updateservers>'
    if ext["type"] != "package":
        # Единственный источник обновлений — у пакета; у остальных расширений его нет.
        text = re.sub(r"[ \t]*<updateservers>.*?</updateservers>[ \t]*\n?", "", text, flags=re.S)
    elif "<updateservers>" in text:
        text = re.sub(r"<updateservers>.*?</updateservers>", block, text, flags=re.S)
    else:
        i = text.rindex("</extension>")
        text = text[:i].rstrip("\n") + "\n\t" + block + "\n" + text[i:]
    open(ext["manifest"], "w", encoding="utf-8").write(text)


def migration_problems(exts: list[dict], since: str | None) -> list[str]:
    """Компонент, у которого с прошлого выпуска изменился install-SQL, обязан получить новый файл миграции в sql/updates/mysql
    (иначе сайты, обновлённые поверх прежней версии, получат другую схему БД, чем сайты с чистой установкой)."""
    if not since:
        return []
    problems = []
    for e in exts:
        if e["type"] != "component":
            continue
        status = sh("git", "diff", "--name-status", since, "--", e["dir"]).splitlines()
        install = [x for x in status if re.search(r"/sql/install[^/]*\.sql$", x.split("\t")[-1])]
        added = [x for x in status if x.startswith("A") and "/sql/updates/" in x.split("\t")[-1]]
        untracked = [f for f in sh("git", "ls-files", "--others", "--exclude-standard", "--", e["dir"]).splitlines() if "/sql/updates/" in f]
        if install and not added and not untracked:
            problems.append(f"{e['name']}: изменён install-SQL ({', '.join(x.split(chr(9))[-1] for x in install)}), но нет нового файла в sql/updates/mysql/")
    return problems


def cmd_status(args) -> None:
    exts = extensions()
    ch = changed(exts, args.since)
    for e in exts:
        print(f"{'*' if e['name'] in ch else ' '} {e['name']:42} {e['version']}")
    print(f"изменились: {len(ch)} из {len(exts) - 1}")
    for line in migration_problems(exts, args.since):
        print('ВНИМАНИЕ:', line)


def cmd_bump(args) -> None:
    exts = extensions()
    problems = migration_problems(exts, args.since)
    if problems and not args.skip_migration_check:
        raise SystemExit('Нет миграций БД:\n  ' + '\n  '.join(problems) + '\n(правило: любое изменение схемы = файл sql/updates/mysql/<версия>.sql; для осознанного пропуска — --skip-migration-check)')
    for e in exts:
        set_manifest(e, args.version)
    print(f"версия {args.version}: пакет и {len(exts) - 1} расширений; <updateservers> только у пакета")


def sha256(path: str) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as fh:
        for chunk in iter(lambda: fh.read(1 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def read_entries(path: str) -> list[dict]:
    if not os.path.isfile(path):
        return []
    entries = []
    for u in ET.parse(path).getroot().findall("update"):
        entries.append({"version": u.findtext("version"), "xml": ET.tostring(u, encoding="unicode")})
    return entries


def vkey(v: str):
    return [int(x) if x.isdigit() else x for x in re.split(r"[.\-]", v)]


def entry_xml(e: dict, version: str, url: str, digest: str, channel: str, tag: str) -> str:
    folder = f"\n    <folder>{e['folder']}</folder>" if e["folder"] else ""
    return f"""  <update>
    <name>{escape(title(e))}</name>
    <description>{escape(title(e))}</description>
    <element>{e['element']}</element>
    <type>{e['type']}</type>{folder}
    <client>{e['client']}</client>
    <version>{version}</version>
    <infourl title="Dharma Shop {tag}">{SITE}/releases/tag/{tag}</infourl>
    <downloads><downloadurl type="full" format="zip">{url}</downloadurl></downloads>
    <sha256>{digest}</sha256>
    <tags><tag>{channel}</tag></tags>
    <maintainer>Dharma</maintainer>
    <maintainerurl>https://github.com/shivayanamahom</maintainerurl>
    <targetplatform name="joomla" version="6.*"/>
    <php_minimum>8.3</php_minimum>
  </update>"""


def write_collection(exts: list[dict], out: str) -> None:
    """updates/dharma-shop.xml — коллекция Joomla из одной записи — пакета, с последней версией и ссылкой на его ленту.
    Адрес зарегистрирован у уже установленных сайтов (раньше в коллекции были все расширения), поэтому он сохранён."""
    rows = []
    for e in exts:
        if e["type"] != "package":
            continue
        entries = read_entries(os.path.join(out, "updates", f"{e['name']}.xml"))
        if not entries:
            continue
        best = max(entries, key=lambda x: vkey(x["version"]))
        folder = f' folder="{e["folder"]}"' if e["folder"] else ""
        rows.append(f'  <extension name="{escape(title(e), {chr(34): "&quot;"})}" element="{e["element"]}" type="{e["type"]}"{folder} client="{e["client"]}" version="{best["version"]}" '
                    f'targetplatformversion="6." detailsurl="{FEED_BASE}{e["name"]}.xml"/>')
    with open(os.path.join(out, "updates", "dharma-shop.xml"), "w", encoding="utf-8") as fh:
        fh.write('<?xml version="1.0" encoding="utf-8"?>\n<extensionset name="Dharma Shop" description="Dharma Shop: магазин, плагины и модули">\n' + "\n".join(rows) + "\n</extensionset>\n")


def cmd_feed(args) -> None:
    exts = extensions()
    out = os.path.abspath(args.out)
    tag = args.tag or f"v{args.version}"
    zips = os.path.join(ROOT, "dist", "release", "zips")
    shutil.rmtree(zips, ignore_errors=True)
    env = dict(os.environ, DHARMA_KEEP_ZIPS=zips)
    subprocess.run(["./build.sh"], cwd=ROOT, env=env, check=True, capture_output=True)
    pkg_version = next(e["version"] for e in exts if e["type"] == "package")
    if pkg_version != args.version:
        raise SystemExit(f"версия пакета в манифесте {pkg_version}, а выпуск {args.version}: сначала `bump`")
    assets = os.path.join(out, "assets", tag)
    shutil.rmtree(assets, ignore_errors=True)
    os.makedirs(assets)
    os.makedirs(os.path.join(out, "updates"), exist_ok=True)
    # Лент отдельных расширений больше нет: убираем прежние (остаётся лента пакета, коллекция и служебные файлы).
    for name in os.listdir(os.path.join(out, "updates")):
        if name.endswith(".xml") and name not in ("pkg_dharmashop.xml", "dharma-shop.xml"):
            os.remove(os.path.join(out, "updates", name))
    for e in exts:
        if e["type"] != "package":
            continue
        src = os.path.join(ROOT, "dist", f"pkg_dharmashop-{e['version']}.zip")
        feed = os.path.join(out, "updates", f"{e['name']}.xml")
        entries = read_entries(feed)
        if any(x["version"] == e["version"] for x in entries):
            continue  # эта версия уже в ленте (расширение не менялось)
        asset = f"{e['name']}-{e['version']}.zip"
        shutil.copyfile(src, os.path.join(assets, asset))
        xml = entry_xml(e, e["version"], f"{args.download_base.rstrip('/')}/{tag}/{asset}", sha256(src), args.channel, tag)
        items = [(x["version"], x["xml"]) for x in entries] + [(e["version"], xml)]
        items.sort(key=lambda t: vkey(t[0]), reverse=True)
        body = "\n".join(re.sub(r"^\s*", "  ", x, count=1) if x.lstrip().startswith("<update") else x for _, x in items[:KEEP_VERSIONS])
        open(feed, "w", encoding="utf-8").write(f'<?xml version="1.0" encoding="utf-8"?>\n<updates>\n{body}\n</updates>\n')
    write_collection(exts, out)
    print(f"ленты: {len(os.listdir(os.path.join(out, 'updates')))} файлов; файлов для загрузки: {len(os.listdir(assets))} ({assets}); тег {tag}")


def main() -> int:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = p.add_subparsers(dest="cmd", required=True)
    a = sub.add_parser("status"); a.add_argument("--since")
    b = sub.add_parser("bump"); b.add_argument("version"); b.add_argument("--since"); b.add_argument("--all", action="store_true"); b.add_argument("--skip-migration-check", action="store_true")
    f = sub.add_parser("feed"); f.add_argument("version"); f.add_argument("--out", required=True); f.add_argument("--download-base", required=True)
    f.add_argument("--channel", default="dev", choices=["dev", "alpha", "beta", "rc", "stable"]); f.add_argument("--tag")
    args = p.parse_args()
    {"status": cmd_status, "bump": cmd_bump, "feed": cmd_feed}[args.cmd](args)
    return 0


if __name__ == "__main__":
    sys.exit(main())
