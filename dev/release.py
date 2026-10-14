#!/usr/bin/env python3
"""Выпуск версий и ленты обновлений Joomla для пакета и каждого расширения Dharma Shop.

  python3 dev/release.py status [--since ТЕГ]                        какие расширения изменились с прошлого выпуска
  python3 dev/release.py bump ВЕРСИЯ [--since ТЕГ] [--all]           версии изменившихся расширений и пакета := ВЕРСИЯ; <updateservers> в манифестах
  python3 dev/release.py feed ВЕРСИЯ --out ПАПКА --download-base URL [--channel dev|alpha|beta|rc|stable] [--tag ТЕГ]
                                                                    собирает пакет и zip, пишет ленты ПАПКА/updates/<расширение>.xml и файлы для загрузки ПАПКА/assets/<тег>/

Правило версий: расширение, изменившееся с прошлого выпуска, получает версию выпуска; неизменившиеся остаются прежними
(их ленты не трогаются, ссылки на старые zip остаются рабочими). Пакет получает версию выпуска всегда.
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
    return ext["name"]


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
    if "<updateservers>" in text:
        text = re.sub(r"<updateservers>.*?</updateservers>", block, text, flags=re.S)
    else:
        i = text.rindex("</extension>")
        text = text[:i].rstrip("\n") + "\n\t" + block + "\n" + text[i:]
    open(ext["manifest"], "w", encoding="utf-8").write(text)


def cmd_status(args) -> None:
    exts = extensions()
    ch = changed(exts, args.since)
    for e in exts:
        print(f"{'*' if e['name'] in ch else ' '} {e['name']:42} {e['version']}")
    print(f"изменились: {len(ch)} из {len(exts) - 1}")


def cmd_bump(args) -> None:
    exts = extensions()
    ch = changed(exts, None if args.all else args.since)
    for e in exts:
        if e["type"] == "package" or e["name"] in ch:
            set_manifest(e, args.version)
        else:
            set_manifest(e, None)
    print(f"версия {args.version}: изменившихся расширений {len(ch)}, остальным добавлен <updateservers>, версия не менялась")


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
    """updates/dharma-shop.xml — коллекция Joomla: одна запись на расширение с последней версией и ссылкой на подробную ленту.
    Все манифесты указывают на этот один адрес, поэтому Joomla создаёт один источник обновлений и проверяет его одним запросом."""
    rows = []
    for e in exts:
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
    for e in exts:
        if e["type"] == "package":
            src = os.path.join(ROOT, "dist", f"pkg_dharmashop-{e['version']}.zip")
        else:
            src = os.path.join(zips, e["zip"])
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
    b = sub.add_parser("bump"); b.add_argument("version"); b.add_argument("--since"); b.add_argument("--all", action="store_true")
    f = sub.add_parser("feed"); f.add_argument("version"); f.add_argument("--out", required=True); f.add_argument("--download-base", required=True)
    f.add_argument("--channel", default="dev", choices=["dev", "alpha", "beta", "rc", "stable"]); f.add_argument("--tag")
    args = p.parse_args()
    {"status": cmd_status, "bump": cmd_bump, "feed": cmd_feed}[args.cmd](args)
    return 0


if __name__ == "__main__":
    sys.exit(main())
