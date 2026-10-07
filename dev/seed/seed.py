#!/usr/bin/env python3
"""Создание тестового мебельного каталога через штатные формы Dharma Shop."""
from __future__ import annotations

import datetime as dt
import json
import os
import re
import subprocess
import sys
import tempfile
import urllib.parse
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[2]
SITE_ROOT = Path(os.environ.get("DHARMA_SITE", "/Volumes/Work/Server/www/test")).resolve()
BASE = os.environ.get("DHARMA_URL", "https://test.test").rstrip("/")
COOKIE_PATH = Path(os.environ.get("COOKIES", "/tmp/dharma-seed-100.cookies"))
MANIFEST_PATH = ROOT / "dev/seed/manifest.json"
SEED_NOTE = "seed-100"
SKU_PREFIX = "DS-"
TODAY = dt.date(2026, 10, 7)

if not BASE.startswith("https://test.test"):
    raise SystemExit("Отказ: наполнение разрешено только на https://test.test")

LAST_RESPONSE_TEXT = ""


def curl_request(url: str, pairs: list[tuple[str, str]] | None = None, files: list[tuple[str, str, str]] | None = None):
    args = ["curl", "-skL", "-b", str(COOKIE_PATH), "-c", str(COOKIE_PATH), "--max-time", "60", "-w", "\n__FINAL__%{url_effective}\t%{http_code}"]
    if files is not None:
        for field, file_path, filename in files:
            args.extend(["-F", f"{field}=@{file_path};filename={filename};type=image/png"])
        for key, value in pairs or []:
            args.extend(["-F", f"{key}={value}"])
    elif pairs is not None:
        for key, value in pairs:
            args.extend(["--data-urlencode", f"{key}={value}"])
    args.append(url)
    result = subprocess.run(args, cwd=ROOT, capture_output=True, timeout=70)
    output = result.stdout
    marker = output.rfind(b"\n__FINAL__")
    if result.returncode or marker < 0:
        raise RuntimeError(f"curl не выполнил запрос к {url}: {result.stderr.decode('utf-8', 'replace')[:300]}")
    body, trailer = output[:marker], output[marker + len(b"\n__FINAL__"):].decode().strip()
    final_url, status = trailer.rsplit("\t", 1)
    if int(status) >= 400:
        Path("/tmp/dharma-seed-100-last-error.html").write_bytes(body)
        raise RuntimeError(f"HTTP {status} для {url}: {body[:300].decode('utf-8', 'replace')}")
    return final_url, body


def request(url: str, data: bytes | None = None, headers: dict[str, str] | None = None):
    del headers
    pairs = urllib.parse.parse_qsl(data.decode(), keep_blank_values=True) if data is not None else None
    return curl_request(url, pairs)


def form_token(url: str) -> str:
    _, html = request(BASE + url)
    match = re.search(rb'name="([0-9a-f]{32})" value="1"', html)
    if not match:
        raise RuntimeError(f"Не найден токен Joomla: {url}")
    return match.group(1).decode()


def fields_body(pairs: list[tuple[str, str]]) -> bytes:
    return b""


def post_form(url: str, pairs: list[tuple[str, str]], post_url: str | None = None) -> str:
    global LAST_RESPONSE_TEXT
    token = form_token(url)
    pairs = pairs + [(token, "1")]
    target = post_url or url
    final_url, body = curl_request(BASE + target, pairs)
    text = body.decode("utf-8", "replace")
    LAST_RESPONSE_TEXT = text
    alerts = re.findall(r'<div[^>]*class="[^"]*alert[^\"]*"[^>]*>(.*?)</div>', text, flags=re.S)
    errors = [re.sub(r"<[^>]+>", " ", item).strip() for item in alerts if any(word in item.lower() for word in ("error", "ошиб", "invalid", "не удалось"))]
    if errors:
        raise RuntimeError(f"Ошибка формы ({final_url}): {' '.join(errors)[:700]}")
    return final_url


def sql(query: str) -> list[list[str]]:
    result = subprocess.run([str(ROOT / "dev/db.sh"), query], cwd=ROOT, text=True, capture_output=True)
    if result.returncode:
        raise RuntimeError(f"SQL-проверка завершилась ошибкой: {result.stderr.strip() or result.stdout.strip()}")
    return [line.split("\t") for line in result.stdout.splitlines() if line.strip()]


def q(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def save_manifest(data: dict) -> None:
    MANIFEST_PATH.parent.mkdir(parents=True, exist_ok=True)
    tmp = MANIFEST_PATH.with_suffix(".json.tmp")
    tmp.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    tmp.replace(MANIFEST_PATH)


def load_manifest() -> dict:
    if MANIFEST_PATH.exists():
        data = json.loads(MANIFEST_PATH.read_text(encoding="utf-8"))
        if data.get("seed") != SEED_NOTE or data.get("base_url") != BASE:
            raise RuntimeError("Манифест относится к другому набору или сайту")
        return data
    return {"version": 1, "seed": SEED_NOTE, "base_url": BASE, "created_at": dt.datetime.now().isoformat(timespec="seconds"), "categories": [], "brands": [], "badges": [], "products": [], "files": []}


def find_taxonomy(extension: str, title: str) -> int | None:
    rows = sql("select id from PFX_categories where extension=" + q(extension) + " and note=" + q(SEED_NOTE) + " and title=" + q(title) + " limit 2")
    if len(rows) > 1:
        raise RuntimeError(f"Несколько записей {extension} с заметкой {SEED_NOTE}: {title}")
    return int(rows[0][0]) if rows else None


def create_category(title: str, extension: str, parent: int, published: int = 1, params: dict[str, str] | None = None) -> int:
    existing = find_taxonomy(extension, title)
    if existing:
        return existing
    page = f"/administrator/index.php?option=com_dharmashop&task=category.add&extension={urllib.parse.quote(extension)}"
    pairs = [
        ("jform[title]", title), ("jform[extension]", extension), ("jform[parent_id]", str(parent)),
        ("jform[published]", str(published)), ("jform[access]", "1"), ("jform[note]", SEED_NOTE),
        ("jform[description]", f"<p>{title}: тестовая категория мебели и интерьера для проверки каталога.</p>"),
        ("jform[params][show_in_list]", "1"), ("jform[params][list_limit]", "0"),
    ]
    for key, value in (params or {}).items():
        pairs.append((f"jform[params][{key}]", value))
    pairs.extend([("jform[id]", "0"), ("task", "category.apply")])
    target = f"/administrator/index.php?option=com_dharmashop&layout=edit&id=0&extension={urllib.parse.quote(extension)}"
    final = post_form(page, pairs, target)
    found = find_taxonomy(extension, title)
    if not found:
        clean = re.sub(r"<[^>]+>", " ", LAST_RESPONSE_TEXT)
        clean = re.sub(r"\s+", " ", clean)
        contexts = []
        for word in ("alert", "error", "ошиб", "сохран", "token", "csrf", "denied", "Запрещ", "Гостиная", "invalid-feedback", "form-control-danger", "is-invalid", "JLIB_FORM_CONTAINS_INVALID_FIELDS"):
            pos = LAST_RESPONSE_TEXT.lower().find(word.lower())
            if pos >= 0:
                contexts.append(re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", LAST_RESPONSE_TEXT[max(0, pos - 120):pos + 350])))
        bad_tags = re.findall(r"<[^>]*(?:is-invalid|form-control-danger|invalid-feedback|aria-invalid)[^>]*>", LAST_RESPONSE_TEXT, re.I)
        title_match = re.search(r"<title[^>]*>(.*?)</title>", LAST_RESPONSE_TEXT, re.S | re.I)
        raise RuntimeError(f"Категория не появилась: {title}; ответ {final}; title={title_match.group(1).strip() if title_match else '?'}; invalid={bad_tags[:12]}; фрагменты={contexts[:10]}; текст={clean[-500:]}")
    return found


def upload(file_path: Path, filename: str) -> str:
    token = form_token("/administrator/index.php?option=com_dharmashop&view=products")
    _, body = curl_request(BASE + "/administrator/index.php?option=com_dharmashop&task=media.upload&format=json", [("folder", "products"), (token, "1")], [("file", str(file_path), filename)])
    payload = json.loads(body)
    if not payload.get("success") or not payload.get("data", {}).get("path"):
        raise RuntimeError(f"Загрузчик отклонил {filename}: {payload}")
    return str(payload["data"]["path"])


def make_artwork() -> list[Path]:
    work = Path(tempfile.mkdtemp(prefix="dharma-seed-100-"))
    specs = [(800, 800), (1200, 800), (600, 900), (1200, 800), (800, 800)]
    palettes = [(22, 45, 55), (72, 64, 52), (37, 67, 62), (91, 71, 52), (46, 56, 76)]
    fonts = [Path("/System/Library/Fonts/Supplemental/Arial.ttf"), Path("/System/Library/Fonts/Supplemental/Helvetica.ttf")]
    font_path = next((p for p in fonts if p.exists()), None)
    result = []
    for i, ((w, h), color) in enumerate(zip(specs, palettes), 1):
        im = Image.new("RGB", (w, h))
        draw = ImageDraw.Draw(im)
        for y in range(h):
            t = y / max(1, h - 1)
            c = tuple(int(channel * (1 - t * .52) + 210 * t * .52) for channel in color)
            draw.line((0, y, w, y), fill=c)
        margin = int(min(w, h) * .1)
        draw.rounded_rectangle((margin, margin, w - margin, h - margin), radius=max(12, margin // 3), outline=(230, 222, 199), width=max(2, min(w, h) // 240))
        font_size = max(22, min(w, h) // 15)
        font = ImageFont.truetype(str(font_path), font_size) if font_path else ImageFont.load_default()
        subfont = ImageFont.truetype(str(font_path), max(16, font_size // 2)) if font_path else ImageFont.load_default()
        title = "DHARMA SHOP"
        sub = ["Мебель для гостиной", "Тепло натурального дерева", "Простые формы для дома", "Функциональный интерьер", "Коллекция мебели и декора"][i - 1]
        box = draw.textbbox((0, 0), title, font=font)
        draw.text(((w - (box[2] - box[0])) / 2, h * .43), title, font=font, fill=(250, 247, 237))
        box = draw.textbbox((0, 0), sub, font=subfont)
        draw.text(((w - (box[2] - box[0])) / 2, h * .43 + font_size * 1.7), sub, font=subfont, fill=(237, 224, 195))
        path = work / f"art-{i}.png"
        im.save(path, format="PNG", optimize=True)
        result.append(path)
    return result


def make_titles() -> list[str]:
    series = ["Верона", "Сканди", "Лофт", "Рига", "Милан", "Норд", "Луна", "Терра", "Фьорд", "Орех", "Лайн", "Модена", "Рондо", "Эстель", "Рондо", "Соло", "Марсель", "Аврора", "Берг", "Альба"]
    kinds = [
        ("Диван", ["трёхместный", "модульный", "раскладной", "компактный", "угловой"]),
        ("Кресло", ["мягкое", "лаунж", "с поворотным основанием", "для чтения", "компактное"]),
        ("Стол обеденный", ["из массива", "раздвижной", "круглый", "прямоугольный", "на металлических ножках"]),
        ("Стул", ["с мягким сиденьем", "из бука", "обеденный", "барный", "скандинавский"]),
        ("Кровать", ["двуспальная", "с подъёмным основанием", "из массива", "с мягким изголовьем", "полуторная"]),
        ("Комод", ["с четырьмя ящиками", "узкий", "из массива", "с металлическими ручками", "для спальни"]),
        ("Шкаф", ["двухдверный", "для одежды", "с зеркалом", "модульный", "с антресолью"]),
        ("Светильник", ["подвесной", "настольный", "напольный", "бра", "для чтения"]),
        ("Стеллаж", ["открытый", "модульный", "для книг", "из дуба", "компактный"]),
        ("Текстиль", ["декоративный", "льняной", "для гостиной", "натуральный", "интерьерный"]),
    ]
    titles = []
    for k, (kind, attrs) in enumerate(kinds):
        for j in range(10):
            name = f"{kind} «{series[(k * 2 + j) % len(series)]}» {attrs[j % len(attrs)]}"
            if k == 0 and j == 0:
                name = "Диван «Верона» модульный трёхместный с вместительным коробом для белья, съёмными чехлами и независимым пружинным блоком — идеальное решение для просторной гостиной с продуманной эргономикой и качественными материалами"
            if k == 0 and j == 1:
                name = "Диван Urban «Луна» — комфортная модель с лаконичным силуэтом и прочной конструкцией"
            titles.append(name)
    for i in range(5):
        titles[30 + i] = f"Стул «{series[i]}» & «Forma» <b>новинка</b> — обеденная модель с мягким сиденьем {i + 1}"
    titles[40:45] = [f"{kinds[2][0]} Urban «Сканди» {['Light','Nord','Oak','Soft','Line'][i]} — модель для современного дома" for i in range(5)]
    return titles


def product_spec(i: int, category_ids: list[int], brand_ids: list[int], badge_ids: list[int], image_paths: list[str]) -> dict:
    category = category_ids[-1] if i >= 90 else (category_ids[9] if i < 3 else category_ids[i // 10])
    price = "5000" if 20 <= i < 40 else str([490, 1290, 3490, 7990, 14990, 28900, 54900, 89900, 129900, 189900][i % 10])
    if i in (95, 96, 97, 98, 99):
        price = ""
    discount = ""
    start = end = ""
    if i < 12:
        discount = "500"
        if float(price) <= 500:
            price = "1290"
    elif i < 24:
        discount = ["10%", "15%", "25%"][i % 3]
    if i < 3:
        end = "30.09.2026, 23:59:59"
    elif i in (3, 4):
        start = "12.10.2026, 00:00:00"
    state = 1 if i < 90 or i >= 98 else (0 if i < 95 else 2)
    units = ["шт", "компл.", "набор", "пар", "упак.", "пог. м", "м²", "рул.", "лист", "ед."]
    unit = units[i % len(units)]
    stock_tracked = 1 if i < 85 else 0
    stock = str(2 + i % 27) if i < 70 else ("0" if i < 85 else "0")
    brand = brand_ids[(i * 3) % len(brand_ids)] if i % 10 < 7 else 0
    badges = []
    if i % 4 == 0:
        badges = [badge_ids[i % len(badge_ids)]]
        if i % 8 == 0:
            badges.append(badge_ids[(i + 1) % len(badge_ids)])
    extra = []
    if i % 10 < 3:
        primary_index = i // 10
        extra = [category_ids[(primary_index + 2) % 9]]
        if i % 9 == 0:
            extra.append(category_ids[(primary_index + 4) % 9])
    imgs = []
    if i < 60:
        count = 1 + i % 5
        imgs = [image_paths[(i + j) % len(image_paths)] for j in range(count)]
    return {"i": i, "catid": category, "price": price, "discount": discount, "discount_start": start, "discount_end": end, "state": state, "unit": unit, "stock_tracked": stock_tracked, "stock_qty": stock, "brand": brand, "badges": badges, "extra": extra, "images": imgs}


def create_product(i: int, title: str, spec: dict, data: dict) -> int:
    sku = f"DS-{i + 1:04d}"
    found = sql("select v.product_id from PFX_dharmashop_variants v where v.sku=" + q(sku) + " limit 2")
    if found:
        pid = int(found[0][0])
        return pid
    slug = re.sub(r"[^a-z0-9]+", "-", sku.lower()).strip("-")
    pairs = [
        ("jform[id]", "0"), ("jform[title]", title), ("jform[alias]", slug), ("jform[catid]", str(spec["catid"])),
        ("jform[state]", str(spec["state"])), ("jform[introtext]", f"<p><strong>{title}</strong> — продуманная модель для дома с надёжной конструкцией и удобной повседневной эксплуатацией.</p>"),
        ("jform[fulltext]", f"<p>{title} сочетает практичность и спокойный современный дизайн.</p><p>Модель подходит для обустройства жилого пространства и легко сочетается с предметами из коллекции Dharma Shop.</p><p>Материалы и отделка подобраны с расчётом на ежедневное использование. Уточняйте доступные варианты при оформлении заказа.</p>"),
        ("jform[search_text]", f"{title}; мебель для дома; интерьер; коллекция {sku}"), ("jform[sku]", sku), ("jform[price]", spec["price"]),
        ("jform[discount]", spec["discount"]), ("jform[discount_start]", spec["discount_start"]), ("jform[discount_end]", spec["discount_end"]),
        ("jform[stock_qty]", spec["stock_qty"]), ("jform[stock_tracked]", str(spec["stock_tracked"])), ("jform[backorder]", "0"),
        ("jform[unit]", spec["unit"]), ("jform[qty_min]", "0.5" if spec["unit"] in ("пог. м", "м²") else "1"),
        ("jform[qty_step]", "0.5" if spec["unit"] in ("пог. м", "м²") else "1"), ("jform[qty_max]", "0"),
        ("jform[weight]", str(2 + (i % 45))), ("jform[weight_unit]", "кг"), ("jform[length]", str(35 + i % 230)),
        ("jform[width]", str(25 + i % 120)), ("jform[height]", str(20 + i % 170)), ("jform[dimension_unit]", "см"),
    ]
    if spec["brand"]:
        pairs.append(("jform[brand_id]", str(spec["brand"])))
    for cat in spec["extra"]:
        pairs.append(("jform[extra_catids][]", str(cat)))
    for badge in spec["badges"]:
        pairs.append(("jform[badge_ids][]", str(badge)))
    for j, path in enumerate(spec["images"]):
        pairs.extend([(f"jform[gallery][{j}][path]", path), (f"jform[gallery][{j}][alt]", title), (f"jform[gallery][{j}][title]", title)])
    if i < 10:
        pairs.extend([
            ("jform[params][meta_title]", title + " — купить в Dharma Shop"),
            ("jform[metadesc]", f"{title}. Мебель и интерьер для дома в каталоге Dharma Shop."),
            ("jform[params][breadcrumb_title]", title[:60]), ("jform[params][h1]", title),
        ])
    page = "/administrator/index.php?option=com_dharmashop&task=product.add"
    final = post_form(page, pairs + [("task", "product.apply")], "/administrator/index.php?option=com_dharmashop&layout=edit&id=0")
    rows = sql("select product_id from PFX_dharmashop_variants where sku=" + q(sku) + " limit 2")
    if len(rows) != 1:
        raise RuntimeError(f"Товар {sku} не найден после формы; ответ {final}")
    return int(rows[0][0])


def create_artwork_paths(data: dict) -> list[str]:
    known = {f.get("name"): f for f in data.get("files", [])}
    names = [f"ds-seed-100-art-{i}.png" for i in range(1, 6)]
    if all(name in known and (SITE_ROOT / known[name]["path"]).is_file() for name in names):
        return [known[name]["path"] for name in names]
    artwork = make_artwork()
    paths = []
    for i, file_path in enumerate(artwork, 1):
        filename = f"ds-seed-100-art-{i}.png"
        found = known.get(filename)
        if found and (SITE_ROOT / found["path"]).is_file():
            paths.append(found["path"])
            continue
        path = upload(file_path, filename)
        item = {"name": filename, "path": path, "sha256": __import__("hashlib").sha256(file_path.read_bytes()).hexdigest()}
        if found:
            data["files"][data["files"].index(found)] = item
        else:
            data.setdefault("files", []).append(item)
        known[filename] = item
        save_manifest(data)
        paths.append(path)
    return paths


def seed() -> None:
    data = load_manifest()
    categories = [
        ("Гостиная", None, 1), ("Диваны", "Гостиная", 1), ("Кресла", "Гостиная", 1),
        ("Кухня и столовая", None, 1), ("Обеденные столы", "Кухня и столовая", 1),
        ("Стулья", "Кухня и столовая", 1), ("Коллекция — архив", None, 0),
        ("Спальня", None, 1), ("Кровати", "Спальня", 1), ("Комоды", "Спальня", 1),
        ("Угловые диваны", "Диваны", 1),
        ("Освещение", None, 1), ("Текстиль и декор", None, 1),
    ]
    cat_ids: dict[str, int] = {}
    for title, parent_title, published in categories:
        parent = cat_ids.get(parent_title, 1) if parent_title else 1
        cid = create_category(title, "com_dharmashop", parent, published)
        cat_ids[title] = cid
        if not any(x.get("id") == cid for x in data["categories"]):
            data["categories"].append({"id": cid, "title": title, "extension": "com_dharmashop", "parent_id": parent, "published": published, "empty": title == "Текстиль и декор"})
            save_manifest(data)

    brand_defs = [("Nordroom", None), ("Forma Casa", None), ("Loftika", None), ("Timber Works", None), ("Maison", None), ("Maison Atelier", "Maison")]
    brand_ids = []
    for title, parent_title in brand_defs:
        parent = next((x["id"] for x in data["brands"] if x["title"] == parent_title), 1) if parent_title else 1
        cid = create_category(title, "com_dharmashop.brand", parent)
        brand_ids.append(cid)
        if not any(x.get("id") == cid for x in data["brands"]):
            data["brands"].append({"id": cid, "title": title, "extension": "com_dharmashop.brand", "parent_id": parent})
            save_manifest(data)

    # Avoid colliding with the user's existing badge «Хит» (id 15).
    badge_defs = [("Хит продаж", "danger"), ("Новинка", "success"), ("Распродажа", "warning"), ("Эко", "info")]
    badge_ids = []
    for title, color in badge_defs:
        cid = create_category(title, "com_dharmashop.badge", 1, params={"badge_color": color, "badge_show_list": "1"})
        badge_ids.append(cid)
        if not any(x.get("id") == cid for x in data["badges"]):
            data["badges"].append({"id": cid, "title": title, "extension": "com_dharmashop.badge", "color": color})
            save_manifest(data)

    # Product families align to populated categories; first three sofas use the third-level category.
    product_cats = [cat_ids[name] for name in ("Диваны", "Кресла", "Обеденные столы", "Стулья", "Кровати", "Комоды", "Спальня", "Освещение", "Гостиная", "Угловые диваны", "Коллекция — архив")]
    image_paths = create_artwork_paths(data)
    titles = make_titles()
    for i, title in enumerate(titles):
        sku = f"DS-{i + 1:04d}"
        spec = product_spec(i, product_cats, brand_ids, badge_ids, image_paths)
        pid = create_product(i, title, spec, data)
        if not any(x.get("sku") == sku for x in data["products"]):
            data["products"].append({"id": pid, "sku": sku, "title": title, "state": spec["state"], "trash_by_action": i >= 98, "images": spec["images"], "catid": spec["catid"]})
            save_manifest(data)
        if (i + 1) % 10 == 0:
            print(f"Создано/сверено товаров: {i + 1}/100", flush=True)

    # Два товара отправляются в корзину стандартным действием списка.
    trash_ids = [int(x["id"]) for x in data["products"] if x.get("trash_by_action")]
    if trash_ids:
        page = "/administrator/index.php?option=com_dharmashop&view=products"
        pairs = [("task", "products.trash")] + [("cid[]", str(pid)) for pid in trash_ids]
        post_form(page, pairs, page)
    print("Наполнение завершено; манифест сохранён.")


def main() -> None:
    if len(sys.argv) != 2 or sys.argv[1] != "seed":
        raise SystemExit("Использование: dev/seed/seed.sh")
    seed()


if __name__ == "__main__":
    main()
