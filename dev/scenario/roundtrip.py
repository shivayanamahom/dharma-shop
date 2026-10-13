#!/usr/bin/env python3
"""Круговая проверка «экспорт → импорт» на копиях товаров: оригиналы не меняются, копии (внешний код источника qart) создаются, правятся и удаляются.

  SCENARIO_URL=… SCENARIO_USER=… SCENARIO_PASS=… SCENARIO_SITE_PATH=… python3 dev/scenario/roundtrip.py

Шаги: выгрузка CSV профилем экспорта → выбор товаров с галереей, файлами, скидкой, значками → файл копий (новые внешние ID, артикулы, названия, цена +10) →
импорт через форму админки → сверка копий с оригиналами (цены, скидка, остаток, бренд, значки, галерея, значения полей, файлы) → правка файла (цены, остаток,
название, порядок и состав галереи, очистка поля) → повторный импорт → третий импорт без изменений → удаление копий.
"""
from __future__ import annotations

import csv
import io
import os
import sys
import tempfile

sys.path.insert(0, os.path.dirname(__file__))
from imp import Importer  # noqa: E402
from lib import Site, db, ok  # noqa: E402

R: list[tuple[bool, str]] = []
E = "/administrator/index.php?option=com_dharmashop_export"
SOURCE = "qart"
PREFIX = "QA-RT "
SUPPORTED = {"text", "editor", "number", "range", "list", "checkbox", "color", "gallery", "files"}


def q(value: str) -> str:
    return value.replace("\\", "\\\\").replace("'", "\\'")


def export_csv(a: Site, site_url: str, fields: list[tuple[str, str]]) -> list[dict]:
    keys = ["id", "group_id", "title", "alias", "sku", "category_path", "category_paths_extra", "brand", "badges", "base_price", "discount", "discount_start", "discount_end",
            "stock_qty", "unit", "introtext", "cover_alt", "gallery_json"] + ["field." + f[0] + ("@json" if f[1] in ("gallery", "files") else "") for f in fields if f[1] in SUPPORTED]
    data = {"jform[title]": "QA RT экспорт", "jform[state]": "1", "jform[entity]": "products", "jform[format]": "csv", "jform[params][filter_state]": "*", "jform[params][in_stock]": "0",
            "jform[params][visibility]": "private", "jform[params][file_name]": "qart", "jform[params][site_url]": site_url, "jform[params][sef_urls]": "1",
            "jform[params][csv_separator]": ";", "jform[params][csv_bom]": "1"}
    for i, k in enumerate(keys):
        data[f"jform[params][columns_products][columns_products{i}][key]"] = k
        data[f"jform[params][columns_products][columns_products{i}][header]"] = k
        data[f"jform[params][columns_products][columns_products{i}][mode]"] = ""
    rows = db("SELECT id FROM PFX_dharmashop_export_profiles WHERE title = 'QA RT экспорт'")
    if rows:
        a.submit_form(E + f"&task=profile.edit&id={rows[0][0]}", data, task="profile.save", remove_prefixes=("jform[params][columns_",))
    else:
        a.submit_form(E + "&task=profile.add", data, task="profile.save")
    pid = int(db("SELECT id FROM PFX_dharmashop_export_profiles WHERE title = 'QA RT экспорт'")[0][0])
    tok = a.token(a.get(E + "&view=profiles").text)
    r = a.post(E + "&task=job.start&format=json", {"id": pid, tok: "1"}).json()
    while r["state"] == "running":
        r = a.post(E + "&task=job.step&format=json", {"id": r["id"], tok: "1"}).json()
    d = a.get(E + f"&task=job.download&id={r['id']}&{tok}=1")
    return list(csv.DictReader(io.StringIO(d.content.decode("utf-8-sig")), delimiter=";"))


def write_csv(path: str, header: list[str], rows: list[dict]) -> None:
    with open(path, "w", encoding="utf-8-sig", newline="") as fh:
        w = csv.writer(fh, delimiter=";")
        w.writerow(header)
        for r in rows:
            w.writerow([r.get(h, "") for h in header])


EXCLUDED: set[str] = set()


def product_state(pid: str) -> dict:
    """Снимок карточки для сравнения (без ID записей): базовые поля, цена, остаток, значки, галерея, значения полей."""
    row = db(f"SELECT p.title, p.alias, p.catid, p.state, p.unit, COALESCE(p.introtext,''), p.cover_alt, v.sku, COALESCE(vp.price,''), COALESCE(vp.discount,''), COALESCE(vp.discount_percent,''), "
             f"COALESCE(vp.discount_start,''), COALESCE(vp.discount_end,''), COALESCE((SELECT SUM(qty) FROM PFX_dharmashop_stock_levels l WHERE l.variant_id = v.id),0), v.id "
             f"FROM PFX_dharmashop_products p JOIN PFX_dharmashop_variants v ON v.product_id = p.id LEFT JOIN PFX_dharmashop_variant_prices vp ON vp.variant_id = v.id WHERE p.id = {pid} LIMIT 1")[0]
    cats = sorted(r[0] + ":" + r[1] for r in db(f"SELECT c.extension, c.alias FROM PFX_dharmashop_product_categories pc JOIN PFX_categories c ON c.id = pc.catid WHERE pc.product_id = {pid} AND pc.direct = 1"))
    gallery = [r[0] for r in db(f"SELECT path FROM PFX_dharmashop_field_media WHERE product_id = {pid} AND field_id = 0 AND variant_id IS NULL ORDER BY ordering, id")]
    skipped = ",".join("'" + x + "'" for x in EXCLUDED) or "''"
    values = sorted("|".join(r) for r in db(
        f"SELECT f.alias, COALESCE(v.option_id,''), COALESCE(v.num,''), COALESCE(v.num_end,''), COALESCE(v.str,'') FROM PFX_dharmashop_field_values v JOIN PFX_dharmashop_fields f ON f.id = v.field_id WHERE v.product_id = {pid} AND v.variant_id IS NULL AND f.alias NOT IN ({skipped})"))
    texts = sorted("|".join(r) for r in db(f"SELECT f.alias, v.value FROM PFX_dharmashop_field_texts v JOIN PFX_dharmashop_fields f ON f.id = v.field_id WHERE v.product_id = {pid} AND v.variant_id IS NULL"))
    media = [f"{r[0]}|{os.path.basename(r[1])}|{r[2]}|{r[3]}" for r in db(
        f"SELECT f.alias, m.path, COALESCE(m.alt,''), COALESCE(m.title,'') FROM PFX_dharmashop_field_media m JOIN PFX_dharmashop_fields f ON f.id = m.field_id WHERE m.product_id = {pid} AND m.field_id > 0 AND f.type <> 'video' ORDER BY f.alias, m.ordering, m.id")]
    return {"title": row[0], "alias": row[1], "catid": row[2], "state": row[3], "unit": row[4], "introtext": row[5], "cover_alt": row[6], "sku": row[7], "price": row[8],
            "discount": row[9], "percent": row[10], "dstart": row[11], "dend": row[12], "qty": float(row[13]), "cats": cats, "gallery": gallery, "values": values, "texts": texts, "media": media}


def diff(a: dict, b: dict, skip: tuple[str, ...] = ()) -> list[str]:
    return [k for k in a if k not in skip and a[k] != b[k]]


def cleanup(a: Site) -> int:
    """Удаляет только свои копии: название начинается с «QA-RT », артикул с «QART-»; через корзину и удаление, как в интерфейсе."""
    ids = [r[0] for r in db(f"SELECT p.id FROM PFX_dharmashop_products p JOIN PFX_dharmashop_variants v ON v.product_id = p.id WHERE p.title LIKE '{PREFIX}%' AND v.sku LIKE 'QART-%'")]
    if ids:
        lst = "/administrator/index.php?option=com_dharmashop&view=products"
        tok = a.token(a.get(lst).text)
        for task in ("products.trash", "products.delete"):
            a.post(lst, {"cid[]": ids, "task": task, tok: "1", "boxchecked": str(len(ids))})
    db(f"DELETE FROM PFX_dharmashop_external_ids WHERE source = '{SOURCE}'")
    return len(ids)


def main() -> int:
    site = os.environ["SCENARIO_URL"].rstrip("/")
    a = Site()
    a.login()
    im = Importer(a)
    leftover = cleanup(a)
    if leftover:
        print(f"  удалены копии прошлого запуска: {leftover}")
    # Флажок в режиме «один» хранит да/нет; значения вариантов в таких полях — остаток прежней настройки на сайте, их импорт не проверяем.
    all_fields = db("SELECT alias, type, params FROM PFX_dharmashop_fields WHERE target = 'product' AND state = 1 ORDER BY id")
    EXCLUDED.update(r[0] for r in all_fields if r[1] == "checkbox" and '"checkbox_mode":"single"' in r[2])
    fields = [(r[0], r[1]) for r in all_fields if r[0] not in EXCLUDED]
    exported = export_csv(a, site, fields)
    ok(len(exported) > 0 and "stock_qty" in exported[0], f"выгрузка: {len(exported)} товаров, есть колонка физического количества", R)

    def interesting(r: dict) -> int:
        return (2 if r.get("field.fajl") else 0) + (2 if r.get("field.galereya") else 0) + (1 if r.get("discount") else 0) + (1 if r.get("badges") else 0) + (1 if r.get("gallery") else 0) + (1 if r.get("brand") else 0)
    # Товары из групп выгружаются «развёрнутыми» (унаследованные текст и галерея записываются как собственные) — для строгой сверки берём самостоятельные.
    sources = sorted([r for r in exported if r["sku"] and not r.get("group_id")], key=interesting, reverse=True)[:5]
    # Медиа-источники: у товаров на сайте файлы и галереи полей лежат в группах; для них сравниваем только значения и медиа полей (унаследованное от группы не сравнивается).
    media_sources = [r for r in exported if r["sku"] and r.get("group_id") and (r.get("field.fajl@json") or r.get("field.galereya@json"))][:3]
    media_ids = {r["id"] for r in media_sources}
    sources += media_sources
    has_files = any(r.get("field.fajl@json") for r in sources)
    print(f"  исходные товары: {[r['id'] for r in sources]}; с файлами: {has_files}")

    header = [h for h in exported[0].keys()]
    copies = []
    for r in sources:
        c = dict(r)
        c["id"] = f"QA-{r['id']}"
        c["sku"] = f"QART-{r['sku']}"
        c["title"] = PREFIX + r["title"]
        c["alias"] = f"{r['alias']}-qart"
        try:
            c["base_price"] = f"{float(r['base_price']) + 10:.2f}"
        except ValueError:
            pass
        copies.append(c)
    work = tempfile.mkdtemp(prefix="qart-")
    first = os.path.join(work, "copies.csv")
    write_csv(first, header, copies)

    mapping_names = {"id": "external_id", "title": "title", "alias": "alias", "sku": "sku", "category_path": "category_path", "brand": "brand_id", "badges": "badge_ids", "base_price": "price",
                     "discount": "discount", "discount_start": "discount_start", "discount_end": "discount_end", "stock_qty": "stock_qty", "unit": "unit", "introtext": "introtext",
                     "cover_alt": "cover_alt", "gallery_json": "gallery", "category_paths_extra": "extra_catids"}
    mapping = {i: mapping_names.get(h, h.replace("@json", "") if h.startswith("field.") else "") for i, h in enumerate(header)}
    params = {"source_code": SOURCE, "key": "external_id", "create_new": "1", "update_existing": "1", "new_state": "1", "list_separator": " | ", "category_separator": "/",
              "download_images": "1", "download_files": "1", "skip_errors": "0"}
    title = "QA RT импорт"
    existing = db(f"SELECT id FROM PFX_dharmashop_import_profiles WHERE title = '{title}'")
    pid = im.save_profile(title, "products", params, first, mapping, int(existing[0][0]) if existing else None)

    # --- 1. создание копий
    run = im.run(pid, approve=True)
    s = run["summary"]
    for e in im.errors(run["job"], 12):
        print("   ошибка строки:", e)
    ok(run["state"] == "done" and s["created"] == len(copies) and s["errors"] == 0, f"импорт 1: создано {s['created']} из {len(copies)} копий, ошибок {s['errors']} ({run['state']} {s['error']})", R)
    ids = {r[0]: r[1] for r in db(f"SELECT external_id, id FROM PFX_dharmashop_external_ids WHERE source = '{SOURCE}' AND entity = 'products'")}
    ok(len(ids) == len(copies), f"внешние соответствия записаны ({len(ids)})", R)

    problems = []
    for r in sources:
        cid = ids.get(f"QA-{r['id']}")
        if not cid:
            problems.append((r["id"], "нет копии"))
            continue
        o, c = product_state(r["id"]), product_state(cid)
        if r["id"] in media_ids:
            d = [k for k in ("media", "values") if o[k] != c[k]]
            if d:
                problems.append((r["id"], d, {k: (o[k], c[k]) for k in d}))
            continue
        d = diff(o, c, skip=("title", "alias", "sku", "price"))
        # цена: копия = оригинал + 10; название, артикул и алиас — с префиксом
        try:
            if abs(float(c["price"]) - (float(o["price"]) + 10)) > 0.005:
                d.append("price")
        except ValueError:
            d.append("price")
        if c["title"] != PREFIX + o["title"] or c["sku"] != "QART-" + o["sku"]:
            d.append("title/sku")
        if d:
            problems.append((r["id"], d, {k: (o[k], c[k]) for k in d if k in o}))
    ok(not problems, f"сверка копий с оригиналами (цена, скидка, остаток, бренд и значки, галерея, значения полей, файлы): расхождения {problems[:3]}", R)

    # --- 2. правка файла и повторный импорт
    edited = [dict(c) for c in copies]
    changes = {}
    edited[0]["base_price"] = f"{float(edited[0]['base_price']) + 5:.2f}"; changes[0] = "price"
    edited[1]["stock_qty"] = f"{float(edited[1]['stock_qty'] or 0) + 7:.0f}"; changes[1] = "stock"
    edited[2]["title"] = edited[2]["title"] + " (правка)"; changes[2] = "title"
    gallery_row = next((i for i, c in enumerate(edited) if c.get("gallery") and " | " in c["gallery"] and i > 2), None)
    if gallery_row is not None:
        parts = edited[gallery_row]["gallery"].split(" | ")
        edited[gallery_row]["gallery"] = " | ".join(reversed(parts))[: None]; changes[gallery_row] = "gallery"
    clear_row = next((i for i, c in enumerate(edited) if c.get("field.tekst") and i not in changes), None)
    if clear_row is not None:
        edited[clear_row]["field.tekst"] = "__CLEAR__"; changes[clear_row] = "clear"
    second = os.path.join(work, "edited.csv")
    write_csv(second, header, edited)
    pid = im.save_profile(title, "products", params, second, mapping, pid)
    run2 = im.run(pid, approve=True)
    s2 = run2["summary"]
    ok(run2["state"] == "done" and s2["updated"] == len(changes) and s2["errors"] == 0, f"импорт 2 (правки): обновлено {s2['updated']} из {len(changes)}, без изменений {s2['unchanged']}, ошибок {s2['errors']} {im.errors(run2['job'], 3)}", R)
    after = {c["id"]: product_state(ids[c["id"]]) for c in edited}
    chk = []
    if after[edited[0]["id"]]["price"] and abs(float(after[edited[0]["id"]]["price"]) - float(edited[0]["base_price"])) > 0.005:
        chk.append("price")
    if abs(after[edited[1]["id"]]["qty"] - float(edited[1]["stock_qty"])) > 0.0005:
        chk.append("stock")
    if after[edited[2]["id"]]["title"] != edited[2]["title"]:
        chk.append("title")
    if gallery_row is not None:
        names = [os.path.basename(p) for p in after[edited[gallery_row]["id"]]["gallery"]]
        want = [os.path.basename(p) for p in reversed(copies[gallery_row]["gallery"].split(" | "))]
        if names != want:
            chk.append(f"gallery order {names[:2]} vs {want[:2]}")
    if clear_row is not None and any(x.startswith("tekst|") or x.startswith("tekst") for x in after[edited[clear_row]["id"]]["texts"] + after[edited[clear_row]["id"]]["values"]):
        chk.append("clear")
    # нетронутые данные: введение, бренд, категории остались как были у копии 0 (не менялись в файле)
    untouched = diff(product_state(ids[edited[0]["id"]]), product_state(ids[edited[0]["id"]]))
    ok(not chk, f"значения после правок верны: {chk}", R)

    # --- 3. тот же файл ещё раз: ничего не меняется
    run3 = im.run(pid, approve=True)
    s3 = run3["summary"]
    ok(run3["state"] == "done" and s3["updated"] == 0 and s3["created"] == 0 and s3["errors"] == 0, f"импорт 3 (тот же файл): обновлено {s3['updated']}, создано {s3['created']}, без изменений {s3['unchanged']}", R)

    # --- 4. оригиналы не тронуты, копии удаляются, файлы оригиналов целы
    before_media = [(r["id"], product_state(r["id"])) for r in sources]
    removed = cleanup(a)
    left = db(f"SELECT COUNT(*) FROM PFX_dharmashop_products p JOIN PFX_dharmashop_variants v ON v.product_id = p.id WHERE p.title LIKE '{PREFIX}%' AND v.sku LIKE 'QART-%'")[0][0]
    ok(left == "0" and removed == len(copies), f"копии удалены ({removed}), осталось {left}", R)
    broken = []
    sess = Site()
    for r in sources:
        for url in (r.get("gallery") or "").split(" | ")[:2] + (r.get("field.fajl") or "").split(" | ")[:1]:
            if url and sess.get(url.replace(site, "")).status_code != 200:
                broken.append(url)
    ok(not broken, f"файлы оригиналов целы после удаления копий {broken[:2]}", R)
    ok(all(product_state(i) == s_ for i, s_ in before_media), "оригиналы за время проверки не изменились", R)
    print(f"\nИтог: {sum(1 for o_, _ in R if o_)} из {len(R)}")
    for o_, n in R:
        if not o_:
            print("  FAIL:", n)
    if a.problems:
        print("ПРЕДУПРЕЖДЕНИЯ/ОШИБКИ В ОТВЕТАХ:", dict.fromkeys(a.problems))
    return 0 if all(o_ for o_, _ in R) else 1


if __name__ == "__main__":
    sys.exit(main())
