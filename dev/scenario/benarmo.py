#!/usr/bin/env python3
"""Импорт реального прайса стороннего магазина (CSV Benarmo: ~970 строк, товары в 4 уровнях категорий) на тестовом сайте.

  BENARMO_CSV=/путь/tovar_benarmo.csv SCENARIO_URL=… SCENARIO_USER=… SCENARIO_PASS=… SCENARIO_SITE_PATH=… python3 dev/scenario/benarmo.py

Шаги: импорт файла (товары и цепочки категорий создаются) → повторный импорт без изменений (идемпотентность) → правка цен в файле → импорт → сверка цен →
удаление созданных товаров и категорий. Прочие данные сайта не трогаются: товары и внешние ID выбираются по коду источника qabenarmo.
"""
from __future__ import annotations

import csv
import os
import sys
import tempfile

sys.path.insert(0, os.path.dirname(__file__))
from imp import Importer  # noqa: E402
from lib import Site, db, ok  # noqa: E402

R: list[tuple[bool, str]] = []
SOURCE = "qabenarmo"
TITLE = "QA Benarmo"
SHOP = "/administrator/index.php?option=com_dharmashop"


def created_products() -> list[str]:
    return [r[0] for r in db(f"SELECT DISTINCT id FROM PFX_dharmashop_external_ids WHERE source = '{SOURCE}' AND entity = 'products'")]


def cleanup(a: Site, categories_before: set[str] | None) -> tuple[int, int]:
    ids = created_products()
    lst = SHOP + "&view=products"
    if ids:
        tok = a.token(a.get(lst).text)
        for task in ("products.trash", "products.delete"):
            for i in range(0, len(ids), 200):
                chunk = ids[i:i + 200]
                a.post(lst, {"cid[]": chunk, "task": task, tok: "1", "boxchecked": str(len(chunk))})
    db(f"DELETE FROM PFX_dharmashop_external_ids WHERE source = '{SOURCE}'")
    removed = 0
    if categories_before is not None:
        now = [r[0] for r in db("SELECT id FROM PFX_categories WHERE extension = 'com_dharmashop' ORDER BY lft DESC")]
        new = [c for c in now if c not in categories_before]
        if new:
            clst = SHOP + "&view=categories"
            tok = a.token(a.get(clst).text)
            for task in ("categories.trash", "categories.delete"):
                a.post(clst, {"cid[]": new, "task": task, tok: "1", "boxchecked": str(len(new))})
            removed = len(new) - len([r for r in db("SELECT id FROM PFX_categories WHERE extension = 'com_dharmashop'") if r[0] in new])
    return len(ids), removed


def forget_jobs(a: Site, pid: int) -> None:
    """Запуски профиля удаляются через историю импорта (вместе с подготовительными файлами), затем сам профиль."""
    jobs = [r[0] for r in db(f"SELECT id FROM PFX_dharmashop_import_jobs WHERE profile_id = {pid}")]
    if jobs:
        url = "/administrator/index.php?option=com_dharmashop_import&view=jobs"
        tok = a.token(a.get(url).text)
        a.post(url, {"cid[]": jobs, "task": "jobs.delete", tok: "1", "boxchecked": str(len(jobs))})
    db(f"DELETE FROM PFX_dharmashop_import_profiles WHERE id = {pid}")


def main() -> int:
    path = os.environ.get("BENARMO_CSV", "")
    if not os.path.isfile(path):
        print("Укажите BENARMO_CSV=/путь/к/tovar_benarmo.csv")
        return 2
    a = Site()
    a.login()
    im = Importer(a)
    leftover, _ = cleanup(a, None)
    if leftover:
        print(f"  удалены товары прошлого запуска: {leftover}")
    existing = int(db("SELECT COUNT(*) FROM PFX_dharmashop_variants WHERE sku IN ('1466','1467','1468')")[0][0])
    if existing:
        print("На сайте уже есть товары с артикулами из файла (импорт Benarmo уже выполнен): сценарий пропущен, чтобы не создавать дубли артикулов.")
        cleanup(a, None)
        return 3
    categories_before = {r[0] for r in db("SELECT id FROM PFX_categories WHERE extension = 'com_dharmashop'")}
    root = db("SELECT id FROM PFX_categories WHERE extension = 'com_dharmashop' AND published = 1 ORDER BY id LIMIT 1")[0][0]
    with open(path, newline="", encoding="utf-8-sig") as fh:
        rows = list(csv.reader(fh, delimiter=";"))
    header, data = rows[0], rows[1:]
    data = [r for r in data if any(c.strip() for c in r)]
    groups: dict[str, list[list[str]]] = {}
    for r in data:
        groups.setdefault(r[0], []).append(r)
    # Одинаковые строки с одним ключом сливаются, расходящиеся отклоняются обе: так в файле Benarmo — товары 1639 и 1973.
    bad = {k for k, g in groups.items() if any(x != g[0] for x in g)}
    keys = set(groups) - bad
    expected_errors = sum(len(groups[k]) for k in bad)
    mapping = {0: "external_id", 1: "sku", 2: "title", 3: "alias", 6: "category_path", 11: "price"}
    params = {"key": "external_id", "source_code": SOURCE, "create_new": "1", "update_existing": "1", "skip_errors": "1", "new_state": "1", "default_category": root,
              "create_missing_categories": "1", "category_separator": "///", "separator": ";"}
    pid = im.save_profile(TITLE, "products", params, csv_path=path, mapping=mapping)
    try:
        first = im.run(pid)
        s = first["summary"]
        print(f"  первый запуск: {s}")
        if s["errors"] != expected_errors:
            print("  первые ошибки:", im.errors(first["job"], 5))
        ok(first["state"] == "done" and s["errors"] == expected_errors, f"первый импорт завершён; ошибки только у строк с противоречивыми дублями ключа: {s['errors']} из {expected_errors} ожидаемых", R)
        ok(s["created"] == len(keys), f"создано товаров: {s['created']} (ключей без конфликта в файле {len(keys)})", R)
        ids = created_products()
        ok(len(ids) == len(keys), f"внешние ID записаны: {len(ids)}", R)
        paths = db(f"SELECT COUNT(DISTINCT pc.catid) FROM PFX_dharmashop_product_categories pc WHERE pc.product_id IN ({','.join(ids[:500])})")
        ok(int(paths[0][0]) > 50, f"категории созданы по цепочкам: {paths[0][0]} используемых", R)
        sample = data[0]
        row = db(f"SELECT p.title, vp.price FROM PFX_dharmashop_external_ids e JOIN PFX_dharmashop_products p ON p.id = e.id JOIN PFX_dharmashop_variants v ON v.product_id = p.id LEFT JOIN PFX_dharmashop_variant_prices vp ON vp.variant_id = v.id WHERE e.source = '{SOURCE}' AND e.external_id = '{sample[0]}' LIMIT 1")[0]
        ok(row[0] == sample[2] and float(row[1] or 0) == float(sample[11].replace(",", ".") or 0), f"карточка «{row[0][:40]}»: название и цена {row[1]} совпадают с файлом", R)

        second = im.run(pid)
        s2 = second["summary"]
        ok(s2["created"] == 0 and s2["updated"] == 0 and s2["unchanged"] == len(data) - expected_errors and s2["errors"] == expected_errors, f"повторный импорт ничего не меняет (создано {s2['created']}, обновлено {s2['updated']}, без изменений {s2['unchanged']})", R)

        changed = []
        for r in data[:3] + data[600:602]:
            if r and r[11] and r[0] not in bad:
                r[11] = str(int(float(r[11].replace(",", ".")) + 100))
                changed.append(r[0])
        edited = os.path.join(tempfile.mkdtemp(), "benarmo-edit.csv")
        with open(edited, "w", encoding="utf-8-sig", newline="") as fh:
            w = csv.writer(fh, delimiter=";")
            w.writerow(header)
            w.writerows(data)
        im.save_profile(TITLE, "products", params, csv_path=edited, mapping=mapping, profile_id=pid)
        third = im.run(pid)
        s3 = third["summary"]
        ok(s3["updated"] == len(changed) and s3["created"] == 0 and s3["errors"] == expected_errors, f"правка цен: обновлено {s3['updated']} из {len(changed)}", R)
        by = {r[0]: r for r in data}
        good = 0
        for k in changed:
            price = db(f"SELECT vp.price FROM PFX_dharmashop_external_ids e JOIN PFX_dharmashop_variants v ON v.product_id = e.id LEFT JOIN PFX_dharmashop_variant_prices vp ON vp.variant_id = v.id WHERE e.source = '{SOURCE}' AND e.external_id = '{k}' LIMIT 1")[0][0]
            good += float(price or 0) == float(by[k][11])
        ok(good == len(changed), f"новые цены записаны в карточки: {good} из {len(changed)}", R)
    finally:
        n, c = cleanup(a, categories_before)
        print(f"  удалено товаров: {n}, категорий: {c}")
        forget_jobs(a, pid)
    bad = [name for passed, name in R if not passed]
    print(f"Итог: {len(R) - len(bad)} из {len(R)}")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main())
