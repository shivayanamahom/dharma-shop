#!/usr/bin/env python3
"""Только чтение: проверяет URL категорий и полноту публичных списков seed-100."""
from __future__ import annotations

import html
import json
import os
import re
import subprocess
import sys
import urllib.parse
from collections import Counter
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASE = os.environ.get("DHARMA_URL", "https://test.test").rstrip("/")
MANIFEST = ROOT / "dev/seed/manifest.json"
CATALOG_URL = os.environ.get("DHARMA_CATALOG_URL", "/index.php/tovary-kategorii")
if BASE != "https://test.test":
    raise SystemExit("Отказ: проверка разрешена только на https://test.test")
manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
if manifest.get("seed") != "seed-100":
    raise SystemExit("Нет манифеста seed-100")


def sql(query: str) -> list[list[str]]:
    result = subprocess.run([str(ROOT / "dev/db.sh"), query], cwd=ROOT, text=True, capture_output=True)
    if result.returncode:
        raise RuntimeError(result.stderr.strip() or result.stdout.strip())
    return [line.split("\t") for line in result.stdout.splitlines() if line.strip()]


def fetch(url: str) -> tuple[int, str, str]:
    result = subprocess.run(["curl", "-skL", "--max-time", "30", "-w", "\n__FINAL__%{url_effective}\t%{http_code}", url], capture_output=True, cwd=ROOT, timeout=40)
    output = result.stdout
    marker = output.rfind(b"\n__FINAL__")
    if result.returncode or marker < 0:
        raise RuntimeError(f"curl: {result.stderr.decode('utf-8', 'replace')[:200]}")
    body = output[:marker].decode("utf-8", "replace")
    final, status = output[marker + len(b"\n__FINAL__"):].decode().strip().rsplit("\t", 1)
    return int(status), final, body


class Page(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.title = ""
        self.h1: list[str] = []
        self.cards: list[str] = []
        self.canonical = ""
        self.next = ""
        self._mode = ""
        self._buffer = ""

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "link" and "canonical" in a.get("rel", "").lower():
            self.canonical = a.get("href", "")
        if tag == "a" and "next" in a.get("rel", "").lower():
            self.next = a.get("href", "")
        if tag == "title":
            self._mode, self._buffer = "title", ""
        elif tag == "h1":
            self._mode, self._buffer = "h1", ""
        elif tag == "h3" and "card-title" in a.get("class", "").split():
            self._mode, self._buffer = "card", ""

    def handle_endtag(self, tag):
        if (tag == "title" and self._mode == "title") or (tag == "h1" and self._mode == "h1") or (tag == "h3" and self._mode == "card"):
            value = " ".join(self._buffer.split())
            if self._mode == "title": self.title = value
            elif self._mode == "h1": self.h1.append(value)
            else: self.cards.append(value)
            self._mode, self._buffer = "", ""

    def handle_data(self, data):
        if self._mode:
            self._buffer += data


def expected_titles(category_id: int) -> list[str]:
    return [r[0] for r in sql("select p.title from PFX_dharmashop_products p join PFX_dharmashop_variants v on v.product_id=p.id and v.is_default=1 join PFX_dharmashop_product_categories pc on pc.product_id=p.id and pc.catid=" + str(category_id) + " and pc.state=1 where v.sku like 'DS-%' and p.state=1")]


def check_page(url: str, expected_h1: str | None = None, expected_status: int = 200):
    status, final, body = fetch(url)
    page = Page(); page.feed(body)
    errors = []
    if status != expected_status: errors.append(f"HTTP {status}, ожидался {expected_status}")
    if expected_status == 200:
        if not page.h1: errors.append("нет h1")
        if expected_h1 and expected_h1 not in page.h1: errors.append("h1 не совпал")
        if not page.canonical: errors.append("нет canonical")
        if re.search(r"\{[^{}]+\}", page.title + " " + " ".join(page.h1)): errors.append("плейсхолдер в title/h1")
        if re.search(r"(?i)(fatal error|uncaught exception|stack trace|parse error)", body): errors.append("PHP-ошибка в HTML")
    return page, errors, status, final


def listing(category_id: int, first_url: str, expected: list[str], sort: str = "order") -> tuple[int, list[str], str | None]:
    url = first_url
    if sort != "order": url += ("&" if "?" in url else "?") + "sort=" + urllib.parse.quote(sort)
    got: list[str] = []
    seen_urls = set()
    while url and url not in seen_urls and len(seen_urls) < 200:
        seen_urls.add(url)
        page, errors, status, final = check_page(url)
        if status != 200: return len(got), got, f"HTTP {status} на {final}"
        got.extend(page.cards)
        url = urllib.parse.urljoin(final, page.next) if page.next else ""
    if url:
        return len(got), got, "цикл или более 200 страниц"
    if not got and expected:
        return 0, got, "список карточек не отрисован"
    if Counter(got) != Counter(expected):
        missing = sum((Counter(expected) - Counter(got)).values())
        extra = sum((Counter(got) - Counter(expected)).values())
        return len(got), got, f"расхождение: пропусков {missing}, лишних/дублированных {extra}"
    return len(got), got, None


failed = 0
print("Категория | ожидалось | получено | результат")
category_rows = sql("select id,title,path,published from PFX_categories where note='seed-100' and extension='com_dharmashop' order by lft")
for cid, title, path, published in category_rows:
    url = BASE + "/index.php/" + urllib.parse.quote(path, safe="/-")
    if int(published) != 1:
        _, errors, status, _ = check_page(url, expected_status=404)
        ok = not errors
        print(f"{title} | скрыта | {status} | {'OK' if ok else 'FAIL: ' + '; '.join(errors)}")
        failed += not ok
        continue
    page, errors, status, final = check_page(url, title)
    expected = expected_titles(int(cid))
    if errors:
        print(f"{title} | {len(expected)} | — | FAIL: {'; '.join(errors)}")
        failed += 1
        continue
    if expected:
        count, _, problem = listing(int(cid), final, expected)
        ok = problem is None
        print(f"{title} | {len(expected)} | {count} | {'OK' if ok else 'FAIL: ' + str(problem)}")
        failed += not ok
    else:
        print(f"{title} | 0 | {len(page.cards)} | {'OK' if not page.cards else 'FAIL: карточки неожиданно найдены'}")
        failed += bool(page.cards)

# Root list and all configured sort modes use the store's general catalog route.
root_expected = [r[0] for r in sql("select p.title from PFX_dharmashop_products p join PFX_categories c on c.id=p.catid where p.state=1 and c.extension='com_dharmashop' and c.published=1 and not exists (select 1 from PFX_categories b where b.extension='com_dharmashop' and b.lft<=c.lft and b.rgt>=c.rgt and b.published<>1)")]
root_url = BASE + CATALOG_URL
for mode in ("order", "title", "price_asc", "price_desc", "new"):
    count, _, problem = listing(0, root_url, root_expected, mode)
    ok = problem is None
    print(f"Каталог / {mode} | {len(root_expected)} | {count} | {'OK' if ok else 'FAIL: ' + str(problem)}")
    failed += not ok

print(f"Итог: {'FAIL' if failed else 'OK'}; ошибок {failed}")
sys.exit(1 if failed else 0)
