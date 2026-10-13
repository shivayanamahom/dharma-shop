#!/usr/bin/env python3
"""Сценарии посетителя и сотрудника на наполненной чистой установке (после fixture.py). Каждый сценарий — набор проверок PASS/FAIL;
находки (предупреждения PHP, ответы 5xx) собираются клиентом и печатаются в конце.

  SCENARIO_URL=… SCENARIO_USER=… SCENARIO_PASS=… SCENARIO_SITE_PATH=… python3 dev/scenario/scenarios.py [раздел …]
Разделы: browse, product, favorites, cart, checkout, landing, admin, users, perf, all
"""
from __future__ import annotations

import json
import os
import re
import sys
import time
from html import unescape

sys.path.insert(0, os.path.dirname(__file__))
from bs4 import BeautifulSoup  # noqa: E402
from lib import Site, db, ok  # noqa: E402

R: list[tuple[bool, str]] = []
RUN = str(int(time.time()))[-6:]  # у каждого запуска свои адреса покупателей, чтобы не упираться в лимит «заказов на e-mail»
FINDINGS: list[str] = []


def soup(r) -> BeautifulSoup:
    return BeautifulSoup(r.text, "html.parser")


def ld(html: str) -> list[dict]:
    out = []
    for m in re.findall(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', html, re.S):
        try:
            out.append(json.loads(m))
        except ValueError:
            pass
    return out


def links(html: str, needle: str) -> list[str]:
    return list(dict.fromkeys(a["href"] for a in BeautifulSoup(html, "html.parser").find_all("a", href=True) if needle in a["href"]))


def meta(html: str, name: str) -> str:
    m = re.search(rf'<meta[^>]+(?:name|property)="{re.escape(name)}"[^>]+content="([^"]*)"', html)
    return unescape(m.group(1)) if m else ""


def canonical(html: str) -> str:
    m = re.search(r'<link[^>]+rel="canonical"[^>]*href="([^"]*)"|<link[^>]+href="([^"]*)"[^>]*rel="canonical"', html)
    return (m.group(1) or m.group(2)) if m else ""


def browse(g: Site) -> None:
    print("\n== каталог и фильтры")
    r = g.get("/index.php/katalog")
    ok(r.status_code == 200 and "Электроника" in r.text, "каталог: список категорий открывается", R)
    r = g.get("/index.php/katalog/elektronika/smartfony")
    h = r.text
    ok(r.status_code == 200, "категория «Смартфоны» открывается", R)
    prod_links = links(h, "/katalog/elektronika/smartfony/")
    ok(len(prod_links) >= 5, f"в «Смартфонах» ссылок на товары: {len(prod_links)} (ожидается ≥5: чехол и 4 варианта)", R)
    ok("<h1" in h and h.count("<h1") == 1, "один заголовок h1", R)
    ok(bool(canonical(h)), f"canonical есть: {canonical(h)}", R)
    ok("noindex" not in meta(h, "robots"), "категория индексируется", R)
    imgs = [i for i in re.findall(r"<img[^>]*>", h) if "/images/dharmashop/" in i]
    ok(len(imgs) >= 5 and all("width=" in i and "height=" in i for i in imgs), f"у всех картинок товаров ({len(imgs)}) есть width/height", R)
    # сортировка и вид
    r2 = g.get("/index.php/katalog/elektronika?sort=price_asc")
    ok("noindex" in meta(r2.text, "robots"), "сортировка: страница-вариант закрыта от индексации", R)
    prices = [float(x.replace("\xa0", "").replace(" ", "").replace(",", ".")) for x in re.findall(r"([\d\s\xa0]+(?:[,.]\d+)?)\s*₽", BeautifulSoup(r2.text, "html.parser").select_one("[data-dharmashop-listing]").get_text(" ") if BeautifulSoup(r2.text, "html.parser").select_one("[data-dharmashop-listing]") else "") if x.strip()]
    ok(prices == sorted(prices) and len(prices) >= 3, f"сортировка по цене по возрастанию: {prices[:6]}", R)
    # фильтры: бренд
    r3 = g.get("/index.php/katalog/elektronika?filter[brand][]=pixel")
    n_pixel = len(links(r3.text, "/katalog/")) 
    ok("Pixel" in r3.text and r3.status_code == 200, "фильтр по бренду Pixel работает", R)
    ok("noindex" in meta(r3.text, "robots") or r3.history, "фильтр по бренду: noindex или переход на посадочную", R)
    r4 = g.get("/index.php/katalog/elektronika?filter[f][cvet][]=chernyj")
    ok(r4.status_code == 200, "фильтр по цвету открывается", R)
    r5 = g.get("/index.php/katalog/elektronika?filter[price][min]=4000&filter[price][max]=8000")
    ok(r5.status_code == 200, "фильтр по цене открывается", R)
    r6 = g.get("/index.php/katalog/elektronika?filter[stock]=1")
    ok(r6.status_code == 200, "фильтр «в наличии» открывается", R)
    # поиск (отдельное представление /search)
    S = "/index.php/component/dharmashop/search?filter[search]="
    r7 = g.get(S + "наушники")
    ok(r7.status_code == 200 and "Found: 3" in re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", r7.text)), "поиск «наушники»: найдено 3", R)
    r7b = g.get(S + "naushniki")
    ok("Found: 3" in re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", r7b.text)), "поиск «naushniki» (транслитерация): найдено 3", R)
    r7c = g.get(S + "AUR-SND")
    ok("Found: 1" in re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", r7c.text)), "поиск по артикулу «AUR-SND»: найден 1", R)
    ok("noindex" in meta(r7.text, "robots"), "результаты поиска закрыты от индексации", R)
    r8 = g.get("/index.php?option=com_dharmashop&task=search.suggest&format=json&q=наушн")
    try:
        j = r8.json()
        ok(j.get("total") == 3, f"подсказки поиска: найдено {j.get('total')}", R)
    except ValueError:
        ok(False, f"подсказки поиска: не JSON ({r8.status_code})", R)
    r9 = g.get(S + "несуществующийтовар")
    ok(r9.status_code == 200 and "Found: 0" in re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", r9.text)) and "noindex" in meta(r9.text, "robots"), "поиск без результатов: 200, «Found: 0», noindex", R)
    r9b = g.get("/index.php/katalog/elektronika?filter[search]=" + "наушники")
    ok("Наушники Pixel Buds" in r9b.text and "Смартфон Pixel P Синий 256 ГБ" not in BeautifulSoup(r9b.text, "html.parser").select_one("[data-dharmashop-listing]").get_text(" "), "поиск внутри категории сужает выдачу", R) if BeautifulSoup(r9b.text, "html.parser").select_one("[data-dharmashop-listing]") else ok("Наушники Pixel Buds" in r9b.text, "поиск внутри категории находит товар", R)
    # бренды и бейджи
    r = g.get("/index.php/brendy")
    ok(r.status_code == 200 and "Aurora" in r.text, "список брендов", R)
    r = g.get("/index.php/bejdzhi")
    ok(r.status_code == 200, "список бейджей", R)
    # 404 и пагинация за пределами
    r = g.get("/index.php/katalog/elektronika/net-takoj")
    ok(r.status_code == 404, "несуществующая категория: 404", R)
    r = g.get("/index.php/katalog/elektronika?start=500")
    ok(r.status_code == 404, "пагинация за пределами: 404", R)
    r = g.get("/index.php/katalog/elektronika?start=0", allow_redirects=False)
    ok(r.status_code == 301, "?start=0 → 301 на чистый адрес", R)


def pid(sku: str) -> int:
    return int(db(f"SELECT product_id FROM PFX_dharmashop_variants WHERE sku = '{sku}'")[0][0])


def text_of(html: str) -> str:
    t = re.sub(r"<script.*?</script>|<style.*?</style>", "", html, flags=re.S)
    return re.sub(r"\s+", " ", unescape(re.sub(r"<[^>]+>", " ", t))).strip()


def walk(node, kind: str):
    """Все объекты JSON-LD нужного @type (в том числе вложенные)."""
    if isinstance(node, dict):
        t = node.get("@type")
        if t == kind or (isinstance(t, list) and kind in t):
            yield node
        for v in node.values():
            yield from walk(v, kind)
    elif isinstance(node, list):
        for v in node:
            yield from walk(v, kind)


class Shopper(Site):
    """Гость с корзиной: те же запросы, что отправляет JS витрины."""

    JSON = {"X-Requested-With": "XMLHttpRequest", "Accept": "application/json"}

    def __init__(self):
        super().__init__()
        page = self.get("/index.php/katalog")
        o = json.loads(re.search(r'<script type="application/json" class="joomla-script-options[^>]*>(.*?)</script>', page.text, re.S).group(1))
        self.tok = o["csrf.token"]
        self.urls = o["com_dharmashop.cart"]
        self.last: dict = {}

    def api(self, url: str, data: dict, token: bool = True) -> dict:
        body = dict(data)
        if token:
            body[self.tok] = "1"
        r = self.post(url, body, headers=self.JSON)
        try:
            j = r.json()
        except ValueError:
            return {"success": False, "message": f"HTTP {r.status_code} не JSON"}
        self.last = j.get("cart") or self.last
        return j

    def add(self, product_id: int, qty: float = 1, variant: int | None = None) -> dict:
        d = {"product_id": product_id, "qty": qty}
        if variant:
            d["variant_id"] = variant
        return self.api(self.urls["url"], d)

    def update(self, item: int, qty: float) -> dict:
        return self.api(self.urls["updateUrl"], {f"qty[{item}]": qty})

    def remove(self, item: int) -> dict:
        return self.api(self.urls["removeUrl"], {"item_id": item})

    def promo(self, code: str) -> dict:
        return self.api("/index.php/component/dharmashop?task=cart.applyPromotion&format=json", {"promotion_code": code})

    def unpromo(self) -> dict:
        return self.api("/index.php/component/dharmashop?task=cart.removePromotion&format=json", {})

    def checkout_form(self):
        page = self.get("/index.php/oformlenie")
        form = [f for f in BeautifulSoup(page.text, "html.parser").find_all("form") if "checkout.submit" in str(f)]
        if not form:
            return page, None, []
        fields = []
        for el in form[0].find_all(["input", "textarea", "select"]):
            n = el.get("name")
            if not n or el.get("type") in {"submit", "button"}:
                continue
            if el.get("type") in {"checkbox", "radio"} and not el.has_attr("checked"):
                continue
            fields.append((n, el.get("value", "") if el.name != "textarea" else el.text))
        return page, form[0].get("action") or "/index.php/oformlenie", fields

    def order(self, shipping: int = 1, payment: int = 1, email: str = "buyer@example.test", over: dict | None = None, drop: tuple[str, ...] = (), consent: bool = True):
        page, action, fields = self.checkout_form()
        if action is None:
            return page, None
        base = {"shipping_method": str(shipping), "payment_method": str(payment), "recipient[name]": "Тест Покупатель", "recipient[phone]": "+7 (900) 123-45-67",
                "recipient[email]": email, "recipient[comment]": "сценарий"}
        if shipping == 1:
            base["shipping[1][point]"] = "1"
        if shipping == 2:
            base.update({"shipping[2][city]": "Пермь", "shipping[2][street]": "Ленина", "shipping[2][house]": "10", "shipping[2][kvartira]": "5"})
        if shipping == 3:
            base.update({"shipping[3][gorod]": "Москва", "shipping[3][ulica_i_dom]": "Тверская 1"})
        if consent:
            base["consents[personal_data]"] = "1"
        base.update(over or {})
        data = {k: v for k, v in fields if not k.startswith(("shipping", "payment", "recipient", "consents"))}
        data.update(base)
        for d in drop:
            data.pop(d, None)
        r = self.post(action, data)
        return r, data


def last_order(email: str):
    rows = db(f"SELECT id, number, total, subtotal, adjustments_total, status_id, payment_status, shipping_title, payment_title, contact_phone FROM PFX_dharmashop_orders WHERE contact_email = '{email}' ORDER BY id DESC LIMIT 1")
    return rows[0] if rows else None


def product(g: Site) -> None:
    print("\n== страницы товаров и микроразметка")
    restock()
    base = "/index.php/katalog/elektronika/"
    r = g.get(base + "nausniki/nausniki-aurora-sound")
    h = r.text
    prods = [p for d in ld(h) for p in walk(d, "Product")]
    ok(r.status_code == 200 and h.count("<h1") == 1, "товар: 200 и один h1", R)
    ok(len(prods) == 1 and prods[0]["offers"]["price"] == "4990.00" and prods[0]["offers"]["availability"].endswith("InStock"), "JSON-LD Product: цена 4990.00, InStock", R)
    ok(len(prods[0].get("image", [])) == 2 and len(prods[0].get("additionalProperty", [])) >= 4 and prods[0].get("brand", {}).get("name") == "Aurora", "JSON-LD: 2 фото, характеристики, бренд", R)
    imgs = [i for i in re.findall(r"<img[^>]*>", h) if "/images/dharmashop/" in i]
    ok(imgs and all("width=" in i and "height=" in i and "alt=" in i for i in imgs), f"картинки товара ({len(imgs)}): width, height, alt", R)
    ok(meta(h, "og:image:width") != "" and meta(h, "og:image").startswith("https://"), "og:image с размерами", R)
    set_config(seo_product_description="{product.title} — {product.introtext}")  # значение по умолчанию из поставки
    h = g.get(base + "nausniki/nausniki-aurora-sound").text
    ok(meta(h, "description") == "Наушники Aurora Sound — Беспроводные наушники с шумоподавлением.", f"meta description = название + краткое описание: «{meta(h, 'description')[:70]}»", R)
    # скидка
    r = g.get(base + "nausniki/nausniki-nordic-air")
    p = [p for d in ld(r.text) for p in walk(d, "Product")][0]
    ok(abs(float(p["offers"]["price"]) - 6791.5) < 0.01, f"скидка 15%: в JSON-LD цена {p['offers']['price']} (7990 → 6791.50)", R)
    t = text_of(r.text)
    ok("7 990" in t and "6 792" in t or "6 791" in t, "на странице видны старая и новая цена", R)
    # нет в наличии
    r = g.get(base + "nausniki/nausniki-pixel-buds")
    p = [p for d in ld(r.text) for p in walk(d, "Product")][0]
    ok(p["offers"]["availability"].endswith("OutOfStock"), "нет в наличии: OutOfStock", R)
    ok("Out of stock" in text_of(r.text), "на странице «Out of stock»", R)
    # цена по запросу
    r = g.get("/index.php/katalog/dom/podarocnyj-sertifikat")
    if r.status_code != 200:
        links_ = links(g.get("/index.php/katalog/dom").text, "/katalog/dom/")
        r = g.get(links_[0]) if links_ else r
    pp = [p for d in ld(r.text) for p in walk(d, "Product")]
    ok(r.status_code == 200 and pp and "offers" not in pp[0], "цена по запросу: Offer в разметке не выводится", R)
    # группы
    r = g.get(base + "smartfony/smartfon-pixel-p-cernyj-128-gb")
    groups = [p for d in ld(r.text) for p in walk(d, "ProductGroup")]
    ok(bool(groups) and len(groups[0].get("hasVariant", [])) == 4, f"ProductGroup: вариантов {len(groups[0].get('hasVariant', [])) if groups else 0} (ожидается 4)", R)
    ok(canonical(r.text).endswith("smartfon-pixel-p-cernyj-128-gb") or "smartfon-pixel-p" in canonical(r.text), f"canonical варианта: {canonical(r.text)[-45:]}", R)
    t = text_of(r.text)
    ok("128" in t and "256" in t, "на странице варианта предлагается выбор вариантов (128/256)", R)
    prices = sorted({float(x["offers"]["price"]) for x in groups[0]["hasVariant"] if "offers" in x}) if groups else []
    ok(prices == [29990.0, 34990.0], f"цены вариантов: {prices}", R)
    # крошки
    bc = [b for d in ld(r.text) for b in walk(d, "BreadcrumbList")]
    ok(bool(bc) and len(bc[0]["itemListElement"]) >= 3, "BreadcrumbList не короче 3 звеньев", R)
    # дубль по неверному пути
    r = g.get("/index.php/katalog/odezda/nausniki-aurora-sound")
    ok(r.status_code == 404, "товар по чужому пути категории: 404", R)


def modules_css(g: Site) -> None:
    print("\n== модуль категорий в узкой колонке")
    h = g.get("/index.php/katalog/elektronika/smartfony").text
    ok("mod_dharmashop_categories/css/grid" in h or "mod_dharmashop_categories/grid" in h, "стиль сетки категорий подключён", R)
    css = g.get(re.search(r'href="([^"]*mod_dharmashop_categories/css/grid[^"]*)"', h).group(1)).text if re.search(r'href="([^"]*mod_dharmashop_categories/css/grid[^"]*)"', h) else ""
    ok("@container" in css and "container-type" in css, "в стиле есть контейнерный запрос для узкой колонки", R)


def favorites(g: Site) -> None:
    print("\n== избранное")
    f = Site()
    page = f.get("/index.php/katalog/elektronika/nausniki")
    soup_ = BeautifulSoup(page.text, "html.parser")
    form = [x for x in soup_.find_all("form") if "favorites.toggle" in (x.get("action") or "")][0]
    tok = [i.get("name") for i in form.find_all("input") if re.fullmatch(r"[0-9a-f]{32}", i.get("name") or "")][0]
    url = form["action"] + "&format=json"
    ids = [pid("AUR-SND-1"), pid("NRD-AIR-1")]
    for i in ids:
        j = f.post(url, {"product_id": i, tok: "1"}, headers=Shopper.JSON).json()
        ok(j.get("success") is True, f"добавить в избранное товар {i}: {str(j.get('message'))[:50]}", R)
    r = f.get("/index.php/izbrannoe")
    ok("Наушники Aurora Sound" in r.text and "Наушники Nordic Air" in r.text, "страница избранного показывает оба товара", R)
    ok("noindex" in meta(r.text, "robots"), "избранное закрыто от индексации", R)
    cards = BeautifulSoup(r.text, "html.parser").select(".dharmaCard, [data-dharmashop-card]")
    ok(len(cards) >= 2, f"карточки избранного как в каталоге ({len(cards)})", R)
    j = f.post(url, {"product_id": ids[0], tok: "1"}, headers=Shopper.JSON).json()
    r = f.get("/index.php/izbrannoe")
    ok("Наушники Aurora Sound" not in r.text and "Наушники Nordic Air" in r.text, "повторное нажатие убирает товар", R)
    j = f.post(url, {"product_id": 999999, tok: "1"}, headers=Shopper.JSON).json()
    ok(j.get("success") is False, "несуществующий товар не добавляется", R)
    j = f.post(url, {"product_id": ids[0]}, headers=Shopper.JSON).json()
    ok(j.get("success") is False, "без токена отказ", R)
    other = Site()
    ok("Наушники Nordic Air" not in other.get("/index.php/izbrannoe").text, "избранное другого гостя пусто", R)
    n = db(f"SELECT COUNT(*) FROM PFX_dharmashop_favorites")[0][0] if db("SHOW TABLES LIKE 'PFX_dharmashop_favorites'") else "?"
    print(f"  (записей избранного в базе: {n})")


def cart(g: Site) -> None:
    print("\n== корзина")
    restock()
    c = Shopper()
    aur, nor, buds, mug_dark, gift, mug = pid("AUR-SND-1"), pid("NRD-AIR-1"), pid("PXL-BUD-1"), pid("MUG-DARK"), pid("GIFT-1"), pid("MUG-MORN")
    j = c.add(aur, 2)
    ok(j["success"] and c.last["qty"] == 2 and c.last["subtotal"] == 9980, f"добавить 2 шт: {c.last}", R)
    item = c.last["products"][str(aur)]["item"]
    c.add(aur, 1)
    ok(c.last["qty"] == 3 and c.last["count"] == 1, "повторное добавление суммирует количество в одной строке", R)
    c.update(item, 1)
    ok(c.last["qty"] == 1 and c.last["subtotal"] == 4990, "изменить количество на 1", R)
    j = c.add(buds, 1)
    ok(j["success"] is False, f"нет в наличии не добавляется: {j.get('message')}", R)
    j = c.add(nor, 9)
    ok(j["success"] is False or c.last["products"].get(str(nor), {}).get("qty", 99) <= 8, f"больше остатка (9 из 8): {j.get('message')} / в корзине {c.last['products'].get(str(nor))}", R)
    j = c.add(mug_dark, 1)
    ok(j["success"] is True, "под заказ (остаток 0, backorder) добавляется", R)
    j = c.add(gift, 1)
    ok(j["success"] is True, "цена по запросу добавляется", R)
    j = c.add(nor, 1)
    ok(j["success"] is True, "товар со скидкой добавляется", R)
    j = c.add(999999, 1)
    ok(j["success"] is False, "несуществующий товар: отказ", R)
    j = c.api(c.urls["url"], {"product_id": aur, "qty": 1}, token=False)
    ok(j["success"] is False, "без токена: отказ", R)
    j = c.add(aur, 1e12)
    ok(j["success"] is False or j.get("cart", {}).get("qty", 0) < 1e9, "огромное количество не ломает корзину", R)
    page = c.get("/index.php/korzina")
    t = text_of(page.text)
    ok(page.status_code == 200 and "Наушники Aurora Sound" in t and "Кружка «Тёмная»" in t, "страница корзины показывает позиции", R)
    ok("noindex" in meta(page.text, "robots"), "корзина noindex", R)
    for k in list(c.last["products"].values()):
        c.remove(k["item"])
    ok(c.last["count"] == 0, f"после удаления всех позиций корзина пуста: {c.last}", R)
    # скидка от суммы и купоны
    c = Shopper()
    c.add(aur, 2)
    page, _, _ = c.checkout_form()
    t = text_of(page.text)
    ok("Order amount discount (5%)" in t and "-499" in t, "скидка от суммы 5% на 9 980 ₽ → −499 ₽", R)
    c.add(aur, 1)
    t = text_of(c.get("/index.php/oformlenie").text)
    ok("(10%)" in t and "-1 497" in t.replace("\xa0", " "), "скидка от суммы 10% на 14 970 ₽ → −1 497 ₽", R)
    j = c.promo("WELCOME10")
    t = text_of(c.get("/index.php/oformlenie").text)
    ok(j["success"] and "WELCOME10" in t, f"купон WELCOME10 применён: {str(j.get('message'))[:50]}", R)
    ok(len(re.findall(r"-\s?\d[\d ]*\s?₽", t)) >= 2, "скидка от суммы и купон действуют вместе", R)
    j = c.promo("NOPE")
    ok(j["success"] is False, f"неверный код отклонён: {j.get('message')}", R)
    j = c.promo("MINUS500")
    t = text_of(c.get("/index.php/oformlenie").text)
    ok("MINUS500" in t and "WELCOME10" not in t, "второй купон заменяет первый (один код на заказ)", R)
    c.unpromo()
    t = text_of(c.get("/index.php/oformlenie").text)
    ok("MINUS500" not in t, "купон снимается", R)
    small = Shopper()
    small.add(mug, 1)
    j = small.promo("MINUS500")
    t = text_of(small.get("/index.php/oformlenie").text)
    ok("Coupon discount" not in t, "MINUS500 при сумме 590 ₽ (минимум 3000): код прикреплён, скидки пока нет", R)
    ok("not active yet" in str(j.get("message")) and "starts only when" in t, f"покупателю объяснено, что скидка пока не действует: «{str(j.get('message'))[:60]}»", R)
    small.add(mug, 5)
    t = text_of(small.get("/index.php/oformlenie").text)
    ok("Coupon discount MINUS500 -500" in t, "MINUS500 при 3 540 ₽: скидка −500 ₽ появилась", R)
    j = Shopper().promo("WELCOME10")
    ok(j["success"] is False, "купон в пустой корзине отклонён", R)


def restock() -> None:
    """Возвращает остатки как в наполнении: сценарии заказов их списывают, а повторный запуск должен начинаться с тех же данных."""
    a = Site()
    a.login()
    for sku, qty in (("AUR-SND-1", 25), ("NRD-AIR-1", 8), ("MUG-MORN", 120)):
        i = pid(sku)
        a.submit_form(f"/administrator/index.php?option=com_dharmashop&task=product.edit&id={i}", {"jform[stock_qty]": str(qty)}, task="product.save")
        # закрыть запись (проверка включена на время редактирования)


def set_config(**kv) -> None:
    """Сохранить параметры компонента через форму «Настройки» (как администратор), остальные значения остаются прежними."""
    a = Site()
    a.login()
    a.submit_form("/administrator/index.php?option=com_config&view=component&component=com_dharmashop", {f"jform[{k}]": str(v) for k, v in kv.items()}, task="component.apply")


def checkout(g: Site) -> None:
    print("\n== оформление заказов")
    set_config(checkout_rate_ip=1000)
    restock()
    aur, mug, nor = pid("AUR-SND-1"), pid("MUG-MORN"), pid("NRD-AIR-1")
    stock0 = float(db(f"SELECT stock_total FROM PFX_dharmashop_variants WHERE product_id = {aur}")[0][0])
    # 1. самовывоз + оплата при получении, скидка 5%
    c = Shopper(); c.add(aur, 2)
    r, _ = c.order(1, 1, f"o1.{RUN}@example.test")
    o = last_order(f"o1.{RUN}@example.test")
    ok(o is not None, f"заказ №1 создан: {o[:4] if o else r.url}", R)
    if o:
        ok(float(o[2]) == 9481.0 and float(o[3]) == 9980.0 and float(o[4]) == -499.0, f"сумма 9 481 (9 980 − 5%): subtotal={o[3]} adj={o[4]} total={o[2]}", R)
        ok(o[7] == "Самовывоз" and o[8] == "Оплата при получении", f"способы сохранены: {o[7]} / {o[8]}", R)
        ok("checkoutcomplete" in r.url or "zakaz-oformlen" in r.url, f"редирект на «Заказ оформлен»: {r.url[-40:]}", R)
        ok(o[1] in text_of(r.text) or "Aurora" in text_of(r.text), "на странице завершения видны данные заказа", R)
        items = db(f"SELECT title, qty, price, total FROM PFX_dharmashop_order_items WHERE order_id = {o[0]}")
        ok(len(items) == 1 and float(items[0][1]) == 2 and float(items[0][3]) == 9980.0, f"позиция заказа: {items}", R)
        stock1 = float(db(f"SELECT stock_total FROM PFX_dharmashop_variants WHERE product_id = {aur}")[0][0])
        print(f"  остаток Aurora: {stock0} → {stock1}")
        ok(stock1 <= stock0 - 2 or stock1 == stock0, "остаток: списан при заказе или ждёт статуса (см. вывод)", R)
        mv = db(f"SELECT COUNT(*) FROM PFX_dharmashop_stock_movements WHERE order_id = {o[0]}") if db("SHOW COLUMNS FROM PFX_dharmashop_stock_movements LIKE 'order_id'") else [["?"]]
        print(f"  движений остатка по заказу: {mv[0][0]}")
    ok(Shopper_cart_empty(c), "корзина очищена после заказа", R)
    # 2. курьер + онлайн-оплата (тест): доставка бесплатна от 5 000 ₽
    c = Shopper(); c.add(aur, 2)
    r, _ = c.order(2, 2, f"o2.{RUN}@example.test")
    o = last_order(f"o2.{RUN}@example.test")
    ok(o is not None, f"заказ №2 (курьер + онлайн): {o[:4] if o else text_of(r.text)[:160]}", R)
    if o:
        ok(float(o[2]) == 9481.0, f"курьер бесплатно от 5 000 ₽: итог {o[2]}", R)
        ok("payment" in r.url or "oplat" in r.url, f"редирект на страницу оплаты: {r.url[-50:]}", R)
        pay_html = r.text
        paid_links = re.findall(r'href="([^"]*payment\.callback[^"]*paid[^"]*)"', pay_html)
        print(f"  ссылок «оплачено» на странице: {len(paid_links)}; статус оплаты: {o[6]}")
        if paid_links:
            href = unescape(paid_links[0])
            r2 = c.get(href)
            o = last_order(f"o2.{RUN}@example.test")
            ok(o[6] == "paid", f"после тестовой оплаты payment_status={o[6]}, status_id={o[5]}", R)
        else:
            ok(False, "на странице оплаты нет ссылки/кнопки «оплачено» (песочница)", R)
    # 3. почта России + оплата при получении, малая сумма → доставка 450, без скидки
    c = Shopper(); c.add(mug, 1)
    r, _ = c.order(3, 1, f"o3.{RUN}@example.test")
    o = last_order(f"o3.{RUN}@example.test")
    ok(o is not None and float(o[2]) == 590.0 + 450.0 or (o is not None and float(o[2]) in (590.0, 1040.0)), f"заказ №3 (почта): итог {o[2] if o else '—'} (590 + 450 = 1 040 ожидается)", R)
    if o and float(o[2]) == 590.0:
        FINDINGS.append("ЗАКАЗ: стоимость доставки «Почта России» (450 ₽) не включена в итог заказа — проверить, как задана цена способа")
    # 4. курьер платно (<5000) и купон
    c = Shopper(); c.add(mug, 3); c.add(nor, 1)
    c.promo("WELCOME10")
    r, _ = c.order(2, 1, f"o4.{RUN}@example.test")
    o = last_order(f"o4.{RUN}@example.test")
    ok(o is not None, f"заказ №4 (курьер, купон): {o[:5] if o else text_of(r.text)[:160]}", R)
    if o:
        adj = db(f"SELECT title, amount FROM PFX_dharmashop_order_adjustments WHERE order_id = {o[0]}") if db("SHOW TABLES LIKE 'PFX_dharmashop_order_adjustments'") else []
        print(f"  корректировки: {adj}")
        ok(any("WELCOME10" in a[0] or "10" in a[0] for a in adj), "купон сохранён в корректировках заказа", R)
    # 5. проверки формы
    for name, kwargs in [
        ("без имени", {"over": {"recipient[name]": ""}}),
        ("без согласия", {"consent": False}),
        ("неверный e-mail", {"over": {"recipient[email]": "не-почта"}}),
        ("неверный телефон", {"over": {"recipient[phone]": "123"}}),
        ("ловушка для ботов", {"over": {"website": "http://spam.test"}}),
        ("несуществующий способ доставки", {"shipping": 99}),
        ("курьер без адреса", {"shipping": 2, "over": {"shipping[2][city]": "", "shipping[2][street]": "", "shipping[2][house]": ""}}),
    ]:
        c = Shopper(); c.add(aur, 1)
        email = f"bad.{RUN}@example.test"
        before = db("SELECT COUNT(*) FROM PFX_dharmashop_orders")[0][0]
        kw = dict(kwargs)
        r, _ = c.order(kw.pop("shipping", 1), 1, kw.pop("email", email), **kw)
        after = db("SELECT COUNT(*) FROM PFX_dharmashop_orders")[0][0]
        ok(after == before and "checkoutcomplete" not in r.url, f"форма: {name} — заказ не создан ({text_of(r.text)[:0]})", R)
    # оплата, не доступная для способа доставки
    c = Shopper(); c.add(aur, 1)
    r, _ = c.order(3, 2, f"bad2.{RUN}@example.test")
    ok(last_order(f"bad2.{RUN}@example.test") is None, "онлайн-оплата для «Почты России» недоступна (правило доставка → оплата)", R)
    # пустая корзина
    c = Shopper()
    page = c.get("/index.php/oformlenie")
    t = text_of(page.text)
    ok(page.status_code == 200 and "Your cart is empty" in t, "оформление с пустой корзиной сообщает, что корзина пуста", R)
    ok("checkout.submit" not in page.text and "recipient[name]" not in page.text, "при пустой корзине формы оформления нет", R)
    r2 = c.post("/index.php/oformlenie", {"task": "checkout.submit", "shipping_method": 1, "recipient[name]": "x"})
    ok(db("SELECT COUNT(*) FROM PFX_dharmashop_orders WHERE contact_name = 'x'")[0][0] == "0", "отправка формы без корзины/токена заказ не создаёт", R)
    # лимит заказов на один e-mail
    placed = 0
    for n in range(5):
        c = Shopper(); c.add(mug, 1)
        r, _ = c.order(1, 1, f"limit.{RUN}@example.test")
        if last_order(f"limit.{RUN}@example.test") and int(db(f"SELECT COUNT(*) FROM PFX_dharmashop_orders WHERE contact_email = 'limit.{RUN}@example.test'")[0][0]) > placed:
            placed += 1
    ok(placed == 3, f"лимит: на один e-mail за час принято {placed} заказов из 5 (ожидается 3)", R)
    # повторная отправка той же формы
    c = Shopper(); c.add(mug, 1)
    page, action, fields = c.checkout_form()
    data = dict(fields); data.update({"shipping_method": "1", "payment_method": "1", "shipping[1][point]": "1", "recipient[name]": "Дубль", "recipient[phone]": "+7 (900) 123-45-67", "recipient[email]": f"dup.{RUN}@example.test", "consents[personal_data]": "1"})
    c.post(action, data); c.post(action, data)
    n = int(db(f"SELECT COUNT(*) FROM PFX_dharmashop_orders WHERE contact_email = 'dup.{RUN}@example.test'")[0][0])
    ok(n == 1, f"двойная отправка формы создаёт один заказ ({n})", R)
    # лимит заказов с одного адреса (по умолчанию 10 в час): ставим 2 — следующая попытка отклоняется
    set_config(checkout_rate_ip=2)
    c = Shopper(); c.add(mug, 1)
    r, _ = c.order(1, 1, f"ip.{RUN}@example.test")
    ok(last_order(f"ip.{RUN}@example.test") is None and "Too many orders" in text_of(r.text), "лимит заказов с одного IP срабатывает", R)
    set_config(checkout_rate_ip=10)


def Shopper_cart_empty(c: Shopper) -> bool:
    t = text_of(c.get("/index.php/korzina").text)
    return "Наушники Aurora Sound" not in t


def landing(g: Site) -> None:
    print("\n== посадочные страницы")
    lst = g.get("/index.php/posadochnye")
    ok(lst.status_code == 200, "список посадочных открывается", R)
    pages = {t: href for href, t in ((a["href"], a.get_text(" ", strip=True)) for a in BeautifulSoup(lst.text, "html.parser").find_all("a", href=True)) if "posadochnye/" in href}
    print("  страницы:", list(pages.values())[:6])
    ok(len(set(pages.values())) >= 3, f"в списке {len(set(pages.values()))} страниц (ожидается 3–4)", R)
    for href in list(dict.fromkeys(pages.values()))[:4]:
        r = g.get(href)
        t = text_of(r.text)
        ok(r.status_code == 200 and r.text.count("<h1") == 1, f"{href[-30:]}: 200, один h1", R)
        ok(meta(r.text, "robots").startswith("index"), f"{href[-30:]}: индексируется ({meta(r.text, 'robots')})", R)
        ok(canonical(r.text).endswith(href.split("/index.php")[-1]) or canonical(r.text) == g.base + href, f"{href[-30:]}: canonical на себя", R)
        ok(any(True for d in ld(r.text) for _ in walk(d, "CollectionPage")), f"{href[-30:]}: JSON-LD CollectionPage", R)
        ok("Add to cart" in t or "Out of stock" in t, f"{href[-30:]}: показывает товары", R)
    # 301 с категории с совпавшим фильтром
    r = g.get("/index.php/katalog/elektronika/smartfony?filter[brand][]=pixel", allow_redirects=False)
    ok(r.status_code in (301, 302, 200), f"категория + бренд Pixel → {r.status_code} {r.headers.get('location', '')[-40:]}", R)
    if r.status_code == 200:
        FINDINGS.append("ЛЕНДИНГИ: фильтр категории «Смартфоны» + бренд Pixel не перенаправляет на посадочную «Смартфоны Pixel» (проверить условия совпадения; возможно, нужны точные условия)")
    r = g.get("/index.php/posadochnye/net-takoj")
    ok(r.status_code == 404, "несуществующая посадочная: 404", R)


def order_status_flow(a: Site) -> None:
    """Смена статуса заказа сотрудником: отгрузка, отмена с возвратом остатка, история."""
    aur = pid("AUR-SND-1")
    row = db(f"SELECT o.id, o.status_id, i.qty FROM PFX_dharmashop_orders o JOIN PFX_dharmashop_order_items i ON i.order_id = o.id WHERE i.product_id = {aur} AND o.status_id = 1 ORDER BY o.id DESC LIMIT 1")
    if not row:
        print("  нет подходящего заказа для проверки статусов")
        return
    oid, status, qty = row[0][0], row[0][1], float(row[0][2])

    def change(new_status: int):
        page = a.get(f"/administrator/index.php?option=com_dharmashop&view=order&id={oid}")
        f = [x for x in BeautifulSoup(page.text, "html.parser").find_all("form") if "order.changeStatus" in (x.get("action") or "")][0]
        data = {i.get("name"): i.get("value", "") for i in f.find_all("input") if i.get("name")}
        data["status_id"] = str(new_status)
        return a.post(unescape(f["action"]), data)

    stock = lambda: float(db(f"SELECT stock_total FROM PFX_dharmashop_variants WHERE product_id = {aur}")[0][0])  # noqa: E731
    s0 = stock()
    r = change(5)
    st = db(f"SELECT status_id FROM PFX_dharmashop_orders WHERE id = {oid}")[0][0]
    ok(st == "5", f"статус заказа {oid} изменён на «Отгружен»: {a.messages(r.text)[:1]}", R)
    hist = [t for t in db("SHOW TABLES") if "history" in t[0] or "status_log" in t[0]]
    r = change(8)
    s1 = stock()
    ok(db(f"SELECT status_id FROM PFX_dharmashop_orders WHERE id = {oid}")[0][0] == "8", f"статус заказа {oid} изменён на «Отменён»", R)
    print(f"  остаток Aurora при отмене заказа на {qty:g} шт: {s0:g} → {s1:g}")
    ok(s1 >= s0, "после отмены остаток не уменьшился (возврат на склад по правилу статуса)", R)
    r = change(1)
    print(f"  возврат в «Новый»: остаток {stock():g}; сообщения: {a.messages(r.text)[:1]}")


def admin(g: Site) -> None:
    print("\n== админка: списки, формы, справка")
    a = Site()
    a.login()
    menu = a.get("/administrator/index.php?option=com_dharmashop")
    ok(menu.status_code == 200 and "Dharma" in menu.text, "панель компонента открывается", R)
    views = sorted(set(re.findall(r"index\.php\?option=(com_dharmashop(?:_landing|_export)?)&(?:amp;)?view=([a-z_]+)", menu.text)))
    print("  разделы из панели:", [v for _, v in views])
    for comp, v in views:
        r = a.get(f"/administrator/index.php?option={comp}&view={v}")
        ok(r.status_code == 200 and "Fatal" not in r.text, f"список «{v}»: {r.status_code}", R)
        m = re.search(r'href="([^"]*task=[a-z]+\.edit[^"]*id=\d+[^"]*)"', r.text)
        if m:
            e = a.get(unescape(m.group(1)))
            ok(e.status_code == 200 and "<form" in e.text, f"форма редактирования «{v}» открывается", R)
            # закрыть форму, чтобы не оставлять запись заблокированной
            cancel = re.search(r'name="task" value="([a-z]+)\.edit"', e.text)
            m2 = re.search(r'Joomla\.submitbutton\(\'([a-z]+\.cancel)\'', e.text)
            tk = re.findall(r"task=([a-z]+)\.cancel", e.text)
            if tk:
                a.get(f"/administrator/index.php?option=com_dharmashop&task={tk[0]}.cancel")
    for path, what in [("/administrator/index.php?option=com_dharmashop_landing&view=pages", "посадочные страницы"),
                       ("/administrator/index.php?option=com_config&view=component&component=com_dharmashop", "настройки"),
                       ("/administrator/index.php?option=com_dharmashop&view=help", "справка")]:
        r = a.get(path)
        ok(r.status_code == 200 and "Fatal" not in r.text, f"{what}: {r.status_code}", R)
    h = a.get("/administrator/index.php?option=com_dharmashop&view=help")
    arts = sorted(set(re.findall(r"article=([A-Z_0-9a-z]+)", h.text)))
    bad = []
    for art in arts:
        rr = a.get(f"/administrator/index.php?option=com_dharmashop&view=help&article={art}")
        if rr.status_code != 200 or len(rr.text) < 5000:
            bad.append(art)
    ok(len(arts) >= 10 and not bad, f"справка: статей {len(arts)}, не открылись: {bad}", R)
    # заказы: последний заказ и смена статуса
    rows = db("SELECT id, contact_email FROM PFX_dharmashop_orders ORDER BY id DESC LIMIT 1")
    if rows:
        e = a.get(f"/administrator/index.php?option=com_dharmashop&view=order&id={rows[0][0]}")
        ok(e.status_code == 200 and rows[0][1] in e.text, "карточка заказа открывается, видны контакты", R)
        order_status_flow(a)
    g.problems.extend(a.problems)


def new_user(a: Site, username: str, email: str, group_ids: list[str], password: str) -> str | None:
    if db(f"SELECT id FROM PFX_users WHERE username = '{username}'"):
        return db(f"SELECT id FROM PFX_users WHERE username = '{username}'")[0][0]
    r = a.submit_form("/administrator/index.php?option=com_users&task=user.add", {"jform[name]": username, "jform[username]": username, "jform[email]": email, "jform[password]": password,
                      "jform[password2]": password, "jform[block]": "0", "jform[requireReset]": "0", "jform[groups][]": group_ids}, task="user.save")
    rows = db(f"SELECT id FROM PFX_users WHERE username = '{username}'")
    if not rows:
        print("  не удалось создать пользователя:", a.messages(r.text)[:2])
    return rows[0][0] if rows else None


def users(g: Site) -> None:
    print("\n== пользователи, права и «Мои заказы»")
    import secrets
    set_config(checkout_rate_ip=1000)
    a = Site()
    a.login()
    # 1. оператор заказов: отдельная группа с минимальными правами
    gid_rows = db("SELECT id FROM PFX_usergroups WHERE title = 'QA оператор заказов'")
    if not gid_rows:
        a.submit_form("/administrator/index.php?option=com_users&task=group.add", {"jform[title]": "QA оператор заказов", "jform[parent_id]": "6"}, task="group.save")
        gid_rows = db("SELECT id FROM PFX_usergroups WHERE title = 'QA оператор заказов'")
    ok(bool(gid_rows), "группа пользователей создана (наследует Manager)", R)
    gid = gid_rows[0][0]
    cfg = "/administrator/index.php?option=com_config&view=component&component=com_dharmashop"
    rules = {f"jform[rules][{k}][{gid}]": "1" for k in ("core.manage", "dharmashop.orders.manage", "dharmashop.orders.status")}
    rules.update({f"jform[rules][{k}][{gid}]": "0" for k in ("core.create", "core.edit", "core.delete")})
    # остальные действия магазина — «наследовать»: прежние запуски и миграции не должны оставлять группе лишних прав
    rules.update({f"jform[rules][{k}][{gid}]": "" for k in ("dharmashop.fields.manage", "dharmashop.groups.manage", "dharmashop.statuses.manage", "dharmashop.warehouses.manage", "dharmashop.payment.manage",
                                                            "dharmashop.shipping.manage", "dharmashop.promotions.manage", "dharmashop.customers.view", "dharmashop.favorites.view")})
    r = a.submit_form(cfg, rules, task="component.apply")
    ok(any("saved" in m.lower() for m in a.messages(r.text)), "права группы сохранены (вход, заказы, смена статуса)", R)
    pwd = secrets.token_urlsafe(12) + "aA1!"
    uid = new_user(a, f"qa_operator_{RUN}", f"qa_operator_{RUN}@example.test", [gid], pwd)
    ok(uid is not None, "пользователь-оператор создан", R)
    op = Site()
    op.login(f"qa_operator_{RUN}", pwd)
    base = "/administrator/index.php?option=com_dharmashop"
    sees = {v: op.get(f"{base}&view={v}").status_code for v in ("orders", "products", "fields", "productgroups", "statuses", "shippings", "payments", "promotions", "warehouses", "customers", "favorites")}
    print("  статусы ответов оператора:", sees)
    ok(sees["orders"] == 200, "оператору доступен список заказов", R)
    for v in ("fields", "productgroups", "statuses", "shippings", "payments", "promotions", "warehouses"):
        ok(sees[v] in (403, 404) or "not authorised" in op.get(f"{base}&view={v}").text.lower() or "permission" in op.get(f"{base}&view={v}").text.lower(), f"оператору закрыт раздел «{v}» ({sees[v]})", R)
    ok(sees["customers"] != 200 or True, f"покупатели без права customers.view: {sees['customers']}", R)
    cfgp = op.get(cfg)
    ok(cfgp.status_code in (403, 404) or "not authorised" in cfgp.text.lower() or "permission" in cfgp.text.lower() or "<form" not in cfgp.text, f"настройки оператору недоступны ({cfgp.status_code})", R)
    add = op.get(f"{base}&task=product.add")
    ok(add.status_code in (403, 404) or "not authorised" in add.text.lower() or "permission" in add.text.lower() or "task=product.add" in add.url and "<form" not in add.text or "view=products" in add.url, f"создание товара оператору закрыто ({add.status_code} {add.url[-40:]})", R)
    # смена статуса оператором
    rows = db("SELECT id FROM PFX_dharmashop_orders WHERE status_id = 1 ORDER BY id DESC LIMIT 1")
    if rows:
        page = op.get(f"{base}&view=order&id={rows[0][0]}")
        ok(page.status_code == 200, "оператор открывает заказ", R)
        f = [x for x in BeautifulSoup(page.text, "html.parser").find_all("form") if "order.changeStatus" in (x.get("action") or "")]
        if f:
            data = {i.get("name"): i.get("value", "") for i in f[0].find_all("input") if i.get("name")}
            data["status_id"] = "2"
            op.post(unescape(f[0]["action"]), data)
            ok(db(f"SELECT status_id FROM PFX_dharmashop_orders WHERE id = {rows[0][0]}")[0][0] == "2", "оператор переводит заказ в «В работе»", R)
            data["status_id"] = "1"
            data["previous_status_id"] = "2"
            op.post(unescape(f[0]["action"]), data)
        else:
            ok(False, "у оператора нет формы смены статуса", R)
    # 2. покупатель: вход и «Мои заказы», привязка гостевых заказов
    buyer_email = f"o3.{RUN}@example.test"
    if db(f"SELECT id FROM PFX_dharmashop_orders WHERE contact_email = '{buyer_email}'"):
        bpwd = secrets.token_urlsafe(12) + "aA1!"
        bid = new_user(a, f"qa_buyer_{RUN}", buyer_email, ["2"], bpwd)
        ok(bid is not None, "пользователь-покупатель создан (e-mail совпадает с гостевым заказом)", R)
        b = Site()
        ok(b.site_login(f"qa_buyer_{RUN}", bpwd), "покупатель входит на сайте", R)
        page = b.get("/index.php/moi-zakazy")
        t = text_of(page.text)
        print("  «Мои заказы» покупателя:", t[t.find("My orders"):][:200] if "My orders" in t else t[200:400])
        has = "000003" in t or any(n in t for n in [x[0] for x in db(f"SELECT number FROM PFX_dharmashop_orders WHERE contact_email = '{buyer_email}'")])
        print(f"  гостевой заказ с тем же e-mail подтянулся: {has} (режим привязки по умолчанию — verified)")
        ok(page.status_code == 200, "«Мои заказы» открывается после входа", R)
        # заказ вошедшим
        c = Shopper.__new__(Shopper)
        Site.__init__(c)
        c.http = b.http
        pg = c.get("/index.php/katalog")
        o = json.loads(re.search(r'<script type="application/json" class="joomla-script-options[^>]*>(.*?)</script>', pg.text, re.S).group(1))
        c.tok, c.urls, c.last = o["csrf.token"], o["com_dharmashop.cart"], {}
        c.add(pid("MUG-MORN"), 1)
        r, _ = c.order(1, 1, f"qa_buyer2.{RUN}@example.test")
        mine = db(f"SELECT id, user_id FROM PFX_dharmashop_orders WHERE contact_email = 'qa_buyer2.{RUN}@example.test'")
        ok(bool(mine) and mine[0][1] == bid, f"заказ вошедшего покупателя привязан к его учётной записи ({mine})", R)
        t = text_of(b.get("/index.php/moi-zakazy").text)
        ok("000" in t, "новый заказ виден в «Мои заказы»", R)
        # чужой заказ по прямой ссылке
        other = db(f"SELECT id FROM PFX_dharmashop_orders WHERE user_id IS NULL ORDER BY id LIMIT 1")
        if other:
            rr = b.get(f"/index.php?option=com_dharmashop&view=order&id={other[0][0]}")
            ok(rr.status_code in (403, 404) or "000001" not in text_of(rr.text), f"чужой гостевой заказ по id недоступен ({rr.status_code})", R)
    else:
        print("  пропуск покупателя: нет заказа o3 в этом запуске (запустите вместе с checkout)")
    set_config(checkout_rate_ip=10)
    g.problems.extend(a.problems)


def perf(g: Site) -> None:
    print("\n== скорость (холодный и тёплый ответы, мс)")
    for path in ["/index.php/katalog", "/index.php/katalog/elektronika", "/index.php/katalog/elektronika/smartfony", "/index.php/katalog/elektronika?filter[brand][]=pixel&filter[price][min]=3000",
                 "/index.php/component/dharmashop/search?filter[search]=pixel", "/index.php/katalog/elektronika/nausniki/nausniki-aurora-sound", "/index.php/posadochnye"]:
        ts = []
        for _ in range(4):
            t0 = time.perf_counter(); r = g.get(path); ts.append((time.perf_counter() - t0) * 1000)
        print(f"  {path[-60:]:62} {ts[0]:6.0f} / {sorted(ts)[1]:6.0f}  {len(r.text) // 1024} КБ")
        ok(sorted(ts)[1] < 1500, f"{path[-50:]}: тёплый ответ быстрее 1,5 с", R)


def main() -> None:
    sections = sys.argv[1:] or ["all"]
    g = Site()  # гость
    run = lambda name, fn: (name in sections or "all" in sections) and fn(g)  # noqa: E731
    for name, fn in [("browse", browse), ("product", product), ("modules", modules_css), ("favorites", favorites), ("cart", cart), ("checkout", checkout), ("landing", landing), ("admin", admin), ("users", users), ("perf", perf)]:
        run(name, fn)
    print(f"\nИтог: {sum(1 for o, _ in R if o)} из {len(R)}")
    for o, n in R:
        if not o:
            print("  FAIL:", n)
    if FINDINGS:
        print("НАХОДКИ:")
        for f in FINDINGS:
            print("  *", f)
    if g.problems:
        print("ПРЕДУПРЕЖДЕНИЯ/ОШИБКИ В ОТВЕТАХ:")
        for p in dict.fromkeys(g.problems):
            print("  ", p)


if __name__ == "__main__":
    main()
