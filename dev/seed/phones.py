#!/usr/bin/env python3
"""Тестовый каталог «Смартфоны и гаджеты»: 5 брендов, 5 категорий (у «Смартфонов» подкатегории), 3 бейджа (из установщика), 50 товаров.

Работает через штатные формы админки (как seed.py). Пометки: заметка `seed-phones`, артикулы `PH-`.
Манифест: dev/seed/phones_manifest.json; очистка: SEED_NOTE=seed-phones SEED_MANIFEST=dev/seed/phones_manifest.json dev/seed/clean.sh
"""
from __future__ import annotations

import hashlib
import re
import sys
import tempfile
import urllib.parse
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import seed  # noqa: E402  (общие функции форм, SQL, загрузки)

from PIL import Image, ImageDraw, ImageFont  # noqa: E402

NOTE = "seed-phones"
seed.SEED_NOTE = NOTE
seed.MANIFEST_PATH = seed.ROOT / "dev/seed/phones_manifest.json"

BRANDS = ["Apple", "Samsung", "Xiaomi", "Google", "OnePlus"]
BADGES = [("Хит", "khit"), ("Новинка", "novinka"), ("Акция", "akciya")]
# (название, родитель)
CATEGORIES = [("Смартфоны", None), ("Флагманы", "Смартфоны"), ("Среднего класса", "Смартфоны"), ("Бюджетные", "Смартфоны"),
              ("Планшеты", None), ("Наушники", None), ("Умные часы", None), ("Аксессуары", None)]

# (бренд, модель, категория, цена; 0 — по запросу)
PRODUCTS = [
    ("Apple", "iPhone 16 Pro 256 ГБ", "Флагманы", 119990), ("Apple", "iPhone 16 Pro Max 512 ГБ", "Флагманы", 149990),
    ("Samsung", "Galaxy S25 Ultra 512 ГБ", "Флагманы", 139990), ("Samsung", "Galaxy S25+ 256 ГБ", "Флагманы", 99990),
    ("Google", "Pixel 10 Pro 256 ГБ", "Флагманы", 109990), ("Xiaomi", "15 Ultra 512 ГБ", "Флагманы", 99990),
    ("OnePlus", "13 512 ГБ", "Флагманы", 79990), ("Samsung", "Galaxy Z Flip6 256 ГБ", "Флагманы", 89990),
    ("Apple", "iPhone 16 128 ГБ", "Флагманы", 79990), ("Google", "Pixel 10 128 ГБ", "Флагманы", 79990),
    ("Apple", "iPhone 15 128 ГБ", "Среднего класса", 59990), ("Samsung", "Galaxy A56 256 ГБ", "Среднего класса", 39990),
    ("Samsung", "Galaxy A36 128 ГБ", "Среднего класса", 29990), ("Xiaomi", "14T 256 ГБ", "Среднего класса", 39990),
    ("Xiaomi", "Redmi Note 14 Pro 256 ГБ", "Среднего класса", 29990), ("Google", "Pixel 9a 128 ГБ", "Среднего класса", 44990),
    ("OnePlus", "Nord 4 256 ГБ", "Среднего класса", 34990), ("OnePlus", "Nord CE4 128 ГБ", "Среднего класса", 24990),
    ("Xiaomi", "Poco X7 Pro 256 ГБ", "Среднего класса", 29990), ("Apple", "iPhone SE 128 ГБ", "Среднего класса", 44990),
    ("Samsung", "Galaxy A16 128 ГБ", "Бюджетные", 14990), ("Samsung", "Galaxy A06 64 ГБ", "Бюджетные", 9990),
    ("Xiaomi", "Redmi 14C 128 ГБ", "Бюджетные", 9990), ("Xiaomi", "Redmi 13 128 ГБ", "Бюджетные", 12990),
    ("Xiaomi", "Poco C75 128 ГБ", "Бюджетные", 9990), ("Google", "Pixel 8a 128 ГБ", "Бюджетные", 34990),
    ("OnePlus", "Nord N30 128 ГБ", "Бюджетные", 14990), ("Samsung", "Galaxy M15 128 ГБ", "Бюджетные", 14990),
    ("Xiaomi", "Redmi A4 64 ГБ", "Бюджетные", 7990), ("OnePlus", "Nord N20 64 ГБ", "Бюджетные", 0),
    ("Apple", "iPad Pro 13 256 ГБ", "Планшеты", 129990), ("Apple", "iPad Air 11 128 ГБ", "Планшеты", 64990),
    ("Samsung", "Galaxy Tab S10 Ultra 256 ГБ", "Планшеты", 109990), ("Xiaomi", "Pad 7 256 ГБ", "Планшеты", 34990),
    ("OnePlus", "Pad 2 128 ГБ", "Планшеты", 44990),
    ("Apple", "AirPods Pro 2", "Наушники", 24990), ("Samsung", "Galaxy Buds3 Pro", "Наушники", 17990),
    ("Xiaomi", "Buds 5 Pro", "Наушники", 9990), ("Google", "Pixel Buds Pro 2", "Наушники", 19990),
    ("OnePlus", "Buds 3", "Наушники", 9990),
    ("Apple", "Watch Series 10 46 мм", "Умные часы", 44990), ("Samsung", "Galaxy Watch7 44 мм", "Умные часы", 24990),
    ("Xiaomi", "Watch S4 Sport", "Умные часы", 12990), ("Google", "Pixel Watch 3 45 мм", "Умные часы", 39990),
    ("OnePlus", "Watch 2R", "Умные часы", 14990),
    ("Apple", "Зарядное устройство MagSafe", "Аксессуары", 4490), ("Samsung", "Адаптер питания 45 Вт", "Аксессуары", 2990),
    ("Xiaomi", "Повербанк 20000 мА·ч", "Аксессуары", 2490), ("Google", "Кабель USB-C 1 м", "Аксессуары", 990),
    ("OnePlus", "Чехол Sandstone для смартфона", "Аксессуары", 1990),
]
INTRO = {
    "Смартфоны": "смартфон", "Флагманы": "флагманский смартфон", "Среднего класса": "смартфон среднего класса", "Бюджетные": "доступный смартфон",
    "Планшеты": "планшет", "Наушники": "беспроводные наушники", "Умные часы": "умные часы", "Аксессуары": "аксессуар",
}
KEEP = {"seed": 1}
TRANSLIT = dict(zip("абвгдежзийклмнопрстуфхцчшщыэюя", "a b v g d e zh z i y k l m n o p r s t u f kh ts ch sh shch y e yu ya".split()))


def slug(text: str) -> str:
    text = "".join(TRANSLIT.get(c, c) for c in text.lower() if c not in "ьъ")
    return re.sub(r"[^a-z0-9]+", "-", text).strip("-")


def category(title: str, extension: str, parent: int, params: dict[str, str] | None = None) -> int:
    found = seed.find_taxonomy(extension, title)
    if found:
        return found
    page = f"/administrator/index.php?option=com_dharmashop&task=category.add&extension={urllib.parse.quote(extension)}"
    pairs = [("jform[title]", title), ("jform[alias]", slug(title)), ("jform[extension]", extension), ("jform[parent_id]", str(parent)),
             ("jform[published]", "1"), ("jform[access]", "1"), ("jform[note]", NOTE), ("jform[description]", ""),
             ("jform[params][show_in_list]", "1"), ("jform[params][list_limit]", "0")]
    pairs += [(f"jform[params][{k}]", v) for k, v in (params or {}).items()]
    pairs += [("jform[id]", "0"), ("task", "category.apply")]
    seed.post_form(page, pairs, f"/administrator/index.php?option=com_dharmashop&layout=edit&id=0&extension={urllib.parse.quote(extension)}")
    found = seed.find_taxonomy(extension, title)
    if not found:
        raise RuntimeError(f"Запись не создана: {extension} {title}")
    return found


def artwork() -> list[tuple[str, Path]]:
    """Пять картинок-заглушек 800×800 (общие цвета и подпись), по одной на группу товаров."""
    work = Path(tempfile.mkdtemp(prefix="dharma-phones-"))
    font_path = next((p for p in (Path("/System/Library/Fonts/Supplemental/Arial.ttf"), Path("/System/Library/Fonts/Helvetica.ttc")) if p.exists()), None)
    groups = [("Смартфон", (34, 51, 89)), ("Планшет", (52, 78, 65)), ("Наушники", (89, 52, 61)), ("Часы", (78, 62, 40)), ("Аксессуар", (60, 60, 72))]
    result = []
    for i, (label, color) in enumerate(groups, 1):
        im = Image.new("RGB", (800, 800))
        d = ImageDraw.Draw(im)
        for y in range(800):
            t = y / 799
            d.line((0, y, 800, y), fill=tuple(int(c * (1 - t * .5) + 200 * t * .5) for c in color))
        d.rounded_rectangle((250, 120, 550, 600), radius=48, outline=(235, 235, 240), width=6)
        font = ImageFont.truetype(str(font_path), 48) if font_path else ImageFont.load_default()
        w = d.textbbox((0, 0), label, font=font)
        d.text(((800 - (w[2] - w[0])) / 2, 660), label, font=font, fill=(245, 245, 248))
        path = work / f"ph-{i}.png"
        im.save(path, format="PNG", optimize=True)
        result.append((f"ph-seed-art-{i}.png", path))
    return result


def upload_artwork(data: dict) -> list[str]:
    known = {f["name"]: f for f in data.get("files", [])}
    paths = []
    for name, file_path in artwork():
        found = known.get(name)
        if found and (seed.SITE_ROOT / found["path"]).is_file():
            paths.append(found["path"])
            continue
        item = {"name": name, "path": seed.upload(file_path, name), "sha256": hashlib.sha256(file_path.read_bytes()).hexdigest()}
        data.setdefault("files", []).append(item)
        seed.save_manifest(data)
        paths.append(item["path"])
    return paths


def make_product(i: int, brand: str, model: str, cat: str, price: int, ids: dict, images: list[str]) -> tuple[int, dict]:
    sku = f"PH-{i + 1:04d}"
    found = seed.sql("select product_id from PFX_dharmashop_variants where sku=" + seed.q(sku) + " limit 1")
    if found:
        return int(found[0][0]), {}
    parent = next(p for t, p in CATEGORIES if t == cat)
    group = {"Планшеты": 1, "Наушники": 2, "Умные часы": 3, "Аксессуары": 4}.get(cat, 0)
    title = f"{brand} {model}"
    discount = ["", "", "", "", "", "10%", "", "", "15%", "", "", "1000"][i % 12] if price and group != 4 else ""
    state = 1
    stock = "0" if i % 11 == 7 else str(3 + i % 17)
    badges = []
    if i % 5 == 0:
        badges.append(ids["badge"]["Хит"])
    if i % 7 == 3:
        badges.append(ids["badge"]["Новинка"])
    extra = [ids["cat"]["Смартфоны"]] if parent == "Смартфоны" and False else []
    gallery = images[group:group + 1] + (images[(group + 1) % 5:(group + 1) % 5 + 1] if i % 3 == 0 else [])
    kind = INTRO[cat]
    pairs = [
        ("jform[id]", "0"), ("jform[title]", title), ("jform[alias]", slug(title)), ("jform[catid]", str(ids["cat"][cat])), ("jform[state]", str(state)),
        ("jform[introtext]", f"<p><strong>{title}</strong> — {kind} {brand}: проверенное решение для повседневных задач.</p>"),
        ("jform[fulltext]", f"<p>{title}. Оригинальная продукция {brand}, гарантия производителя.</p><p>Подходит для работы, учёбы и развлечений. Уточняйте комплектацию и доступные цвета при оформлении заказа.</p>"),
        ("jform[search_text]", f"{title}; {brand}; {kind}; {model.split()[0]}"), ("jform[sku]", sku), ("jform[price]", str(price) if price else ""),
        ("jform[discount]", discount), ("jform[discount_start]", ""), ("jform[discount_end]", ""),
        ("jform[stock_qty]", stock), ("jform[stock_tracked]", "1"), ("jform[backorder]", "0"), ("jform[unit]", "шт"),
        ("jform[qty_min]", "1"), ("jform[qty_step]", "1"), ("jform[qty_max]", "0"),
        ("jform[weight]", "0.2"), ("jform[weight_unit]", "кг"), ("jform[length]", "16"), ("jform[width]", "8"), ("jform[height]", "1"), ("jform[dimension_unit]", "см"),
        ("jform[brand_id]", str(ids["brand"][brand])),
    ]
    pairs += [("jform[badge_ids][]", str(b)) for b in badges]
    pairs += [("jform[extra_catids][]", str(c)) for c in extra]
    for j, path in enumerate(gallery):
        pairs += [(f"jform[gallery][{j}][path]", path), (f"jform[gallery][{j}][alt]", title), (f"jform[gallery][{j}][title]", title)]
    seed.post_form("/administrator/index.php?option=com_dharmashop&task=product.add", pairs + [("task", "product.apply")],
                   "/administrator/index.php?option=com_dharmashop&layout=edit&id=0")
    rows = seed.sql("select product_id from PFX_dharmashop_variants where sku=" + seed.q(sku) + " limit 2")
    if len(rows) != 1:
        raise RuntimeError(f"Товар {sku} не найден после формы")
    return int(rows[0][0]), {"sku": sku, "title": title, "images": gallery}


def main() -> None:
    data = seed.load_manifest()
    ids: dict[str, dict] = {"cat": {}, "brand": {}, "badge": {}}
    for title, parent in CATEGORIES:
        cid = category(title, "com_dharmashop", ids["cat"].get(parent, 1) if parent else 1)
        ids["cat"][title] = cid
        if not any(x["id"] == cid for x in data["categories"]):
            data["categories"].append({"id": cid, "title": title, "extension": "com_dharmashop"})
    for title in BRANDS:
        cid = category(title, "com_dharmashop.brand", 1)
        ids["brand"][title] = cid
        if not any(x["id"] == cid for x in data["brands"]):
            data["brands"].append({"id": cid, "title": title, "extension": "com_dharmashop.brand"})
    # Бейджи «Хит», «Новинка», «Акция» создаёт установщик (dev/default-badges.php на стенде); здесь только берутся.
    for title, alias in BADGES:
        rows = seed.sql("select id from PFX_categories where extension='com_dharmashop.badge' and alias=" + seed.q(alias) + " and published=1 limit 1")
        if not rows:
            raise RuntimeError(f"Нет бейджа «{title}»: установите пакет или выполните php dev/default-badges.php")
        ids["badge"][title] = int(rows[0][0])
    seed.save_manifest(data)
    images = upload_artwork(data)
    for i, (brand, model, cat, price) in enumerate(PRODUCTS):
        pid, info = make_product(i, brand, model, cat, price, ids, images)
        if info and not any(x["id"] == pid for x in data["products"]):
            data["products"].append({"id": pid, "state": 1, **info})
            seed.save_manifest(data)
        if (i + 1) % 10 == 0:
            print(f"Товаров: {i + 1}/{len(PRODUCTS)}", flush=True)
    print("Готово:", len(ids["cat"]), "категорий,", len(ids["brand"]), "брендов,", len(ids["badge"]), "бейджей,", len(PRODUCTS), "товаров")


if __name__ == "__main__":
    main()
