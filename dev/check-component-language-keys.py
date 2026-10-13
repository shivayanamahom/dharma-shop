#!/usr/bin/env python3
"""Fail the package build when component XML references missing RU/EN admin keys."""
from __future__ import annotations

from pathlib import Path
import re
import sys
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
LANGUAGES = ("ru-RU", "en-GB")
KEY = re.compile(r"COM_DHARMASHOP_[A-Z0-9_]+")
DEFINED = re.compile(r"^\s*(COM_DHARMASHOP_[A-Z0-9_]+)\s*=", re.MULTILINE)


def keys_in(paths: list[Path]) -> set[str]:
    keys: set[str] = set()
    for path in paths:
        if path.is_file():
            keys.update(KEY.findall(path.read_text(encoding="utf-8")))
    return keys


def defined_keys(paths: list[Path]) -> set[str]:
    keys: set[str] = set()
    for path in paths:
        if path.is_file():
            keys.update(DEFINED.findall(path.read_text(encoding="utf-8")))
    return keys


def menu_metadata_keys(paths: list[Path]) -> set[str]:
    keys: set[str] = set()
    for path in paths:
        for element in ET.parse(path).getroot().iter():
            if element.tag == "layout":
                for attribute in ("title", "option"):
                    keys.update(KEY.findall(element.get(attribute, "")))
            elif element.tag == "message":
                keys.update(KEY.findall("".join(element.itertext())))
    return keys


form_xml = [ROOT / "com_dharmashop/admin/config.xml", ROOT / "com_dharmashop/admin/access.xml"]
form_xml += sorted((ROOT / "com_dharmashop/admin/forms").rglob("*.xml"))
manifest = ROOT / "com_dharmashop/dharmashop.xml"
menu_xml = sorted((ROOT / "com_dharmashop/site/tmpl").rglob("*.xml"))
errors: list[str] = []
form_xml += sorted((ROOT / "com_dharmashop_import/admin/forms").rglob("*.xml"))

for language in LANGUAGES:
    ini = ROOT / f"com_dharmashop/admin/language/{language}/com_dharmashop.ini"
    sys_ini = ROOT / f"com_dharmashop/admin/language/{language}/com_dharmashop.sys.ini"
    form_keys = keys_in(form_xml)
    for key in sorted(form_keys - defined_keys([ini, sys_ini, ROOT / f"com_dharmashop_import/admin/language/{language}/com_dharmashop_import.ini"])):
        source = next((path for path in form_xml if key in KEY.findall(path.read_text(encoding="utf-8"))), None)
        errors.append(f"{language}: {key} referenced by {source.relative_to(ROOT) if source else 'administrator forms'} has no admin translation")

    system_keys = keys_in([manifest]) | menu_metadata_keys(menu_xml)
    for key in sorted(system_keys - defined_keys([sys_ini])):
        source = next((path for path in [manifest, *menu_xml] if key in KEY.findall(path.read_text(encoding="utf-8"))), None)
        errors.append(f"{language}: {key} referenced by {source.relative_to(ROOT) if source else 'menu metadata'} has no admin system translation")

if errors:
    print("Missing com_dharmashop language keys:", file=sys.stderr)
    print("\n".join(f"- {error}" for error in errors), file=sys.stderr)
    raise SystemExit(1)

print("Dharma Shop RU/EN administrator language keys: OK")
