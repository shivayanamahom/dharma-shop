#!/usr/bin/env python3
"""Импорт реального XLSX поставщика (705-Benarmo.xlsx: ~1440 товаров, 118 колонок, фото ссылками) на тестовом сайте.

  BENARMO_XLSX=/путь/705-Benarmo.xlsx SCENARIO_URL=… SCENARIO_USER=… SCENARIO_PASS=… SCENARIO_SITE_PATH=… python3 dev/scenario/benarmo_xlsx.py [--keep]

Шаги: профиль с сопоставлением → импорт (товары, категории, фото скачиваются с сайта поставщика) → повторный импорт → удаление созданного.
С --keep созданные товары остаются (для ручного просмотра), удаляется только отчёт профиля не трогается.
"""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(__file__))
from imp import Importer  # noqa: E402
from lib import Site, db, ok  # noqa: E402

R: list[tuple[bool, str]] = []
SHOP = "/administrator/index.php?option=com_dharmashop"
MAPPING = {0: "sku", 2: "title", 3: "category_path", 5: "weight", 8: "introtext", 10: "gallery", 12: "price", 14: "unit"}


def params(root: str) -> dict:
    return {"key": "sku", "source_code": "benarmo-xlsx", "create_new": "1", "update_existing": "1", "skip_errors": "1", "new_state": "1", "default_category": root,
            "create_missing_categories": "1", "category_separator": "///", "list_separator": ",", "download_images": "1", "skip_media_errors": "1", "media_hosts": "www.santech.ru", "media_max_files": "3000"}


def remove_created(a: Site, products_before: set[str], categories_before: set[str]) -> tuple[int, int]:
    ids = [r[0] for r in db("SELECT id FROM PFX_dharmashop_products") if r[0] not in products_before]
    lst = SHOP + "&view=products"
    if ids:
        tok = a.token(a.get(lst).text)
        for task in ("products.trash", "products.delete"):
            for i in range(0, len(ids), 200):
                chunk = ids[i:i + 200]
                a.post(lst, {"cid[]": chunk, "task": task, tok: "1", "boxchecked": str(len(chunk))})
    new = [r[0] for r in db("SELECT id FROM PFX_categories WHERE extension = 'com_dharmashop' ORDER BY lft DESC") if r[0] not in categories_before]
    if new:
        clst = SHOP + "&view=categories"
        tok = a.token(a.get(clst).text)
        for task in ("categories.trash", "categories.delete"):
            a.post(clst, {"cid[]": new, "task": task, tok: "1", "boxchecked": str(len(new))})
    return len(ids), len(new)


def main() -> int:
    path = os.environ.get("BENARMO_XLSX", "")
    if not os.path.isfile(path):
        print("Укажите BENARMO_XLSX=/путь/к/705-Benarmo.xlsx")
        return 2
    keep = "--keep" in sys.argv
    a = Site()
    a.login()
    im = Importer(a)
    products_before = {r[0] for r in db("SELECT id FROM PFX_dharmashop_products")}
    categories_before = {r[0] for r in db("SELECT id FROM PFX_categories WHERE extension = 'com_dharmashop'")}
    root = db("SELECT id FROM PFX_categories WHERE extension = 'com_dharmashop' AND published = 1 ORDER BY id LIMIT 1")[0][0]
    pid = im.save_profile("QA Benarmo XLSX", "products", params(root), csv_path=path, mapping=MAPPING, fmt="xlsx")
    try:
        first = im.run(pid, limit_s=1500)
        s = first["summary"]
        print(f"  первый запуск: {s}")
        failed = db("SELECT COUNT(*) FROM PFX_dharmashop_import_files WHERE job_id = %s AND state = 'failed'" % first["job"])[0][0]
        print(f"  файлов не скачано (мёртвые ссылки поставщика, пропущены): {failed}")
        ok(first["state"] == "done" and s["errors"] == 0, "первый импорт завершён без ошибок", R)
        ok(s["created"] == 1437, f"создано товаров: {s['created']} из 1437", R)
        new = [r[0] for r in db("SELECT id FROM PFX_dharmashop_products") if r[0] not in products_before]
        with_images = int(db(f"SELECT COUNT(DISTINCT product_id) FROM PFX_dharmashop_field_media WHERE field_id = 0 AND product_id IN ({','.join(new)})")[0][0]) if new else 0
        ok(with_images >= 1350, f"фото прикреплены у {with_images} товаров из {len(new)}", R)
        cats = int(db("SELECT COUNT(*) FROM PFX_categories WHERE extension = 'com_dharmashop'")[0][0]) - len(categories_before)
        ok(cats == 25, f"создано категорий: {cats} из 25", R)
        row = db(f"SELECT p.title, vp.price, COALESCE(p.unit,'') FROM PFX_dharmashop_products p JOIN PFX_dharmashop_variants v ON v.product_id = p.id LEFT JOIN PFX_dharmashop_variant_prices vp ON vp.variant_id = v.id WHERE v.sku = '117-5248' LIMIT 1")
        ok(bool(row) and abs(float(row[0][1] or 0) - 1935.3) < 0.01, f"карточка 117-5248: {row[0][:2] if row else None}", R)
        second = im.run(pid, limit_s=1500)
        s2 = second["summary"]
        print(f"  повторный запуск: {s2}")
        ok(s2["created"] == 0 and s2["updated"] == 0 and s2["errors"] == 0, f"повторный импорт без изменений и без повторного скачивания фото (обновлено {s2['updated']}, без изменений {s2['unchanged']})", R)
    finally:
        if keep:
            print("  --keep: товары оставлены")
        else:
            n, c = remove_created(a, products_before, categories_before)
            print(f"  удалено товаров: {n}, категорий: {c}")
            jobs = [r[0] for r in db(f"SELECT id FROM PFX_dharmashop_import_jobs WHERE profile_id = {pid}")]
            if jobs:
                url = "/administrator/index.php?option=com_dharmashop_import&view=jobs"
                tok = a.token(a.get(url).text)
                a.post(url, {"cid[]": jobs, "task": "jobs.delete", tok: "1", "boxchecked": str(len(jobs))})
            db(f"DELETE FROM PFX_dharmashop_import_profiles WHERE id = {pid}")
    bad = [name for passed, name in R if not passed]
    print(f"Итог: {len(R) - len(bad)} из {len(R)}")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main())
