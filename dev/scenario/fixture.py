#!/usr/bin/env python3
"""Наполнение чистой установки Dharma Shop через формы админки: мини-магазин (категории, бренды, поля, группы, товары, склады,
доставка, оплата, акции, посадочные страницы). Повторный запуск пропускает уже созданное (по названию).

  SCENARIO_URL=https://dharmashop.test SCENARIO_USER=… SCENARIO_PASS=… SCENARIO_SITE_PATH=/путь/к/сайту python3 dev/scenario/fixture.py [фаза …]
Фазы: base (склады, поля), catalog (категории, бренды), groups, products, shop (доставка, оплата, акции), landing, all.
"""
from __future__ import annotations

import sys
import os

sys.path.insert(0, os.path.dirname(__file__))
from lib import Site, db, make_image, upload_image, enable_plugin  # noqa: E402
import tempfile  # noqa: E402
import urllib.parse  # noqa: E402
import datetime as dt  # noqa: E402

A = "/administrator/index.php?option=com_dharmashop"


def one(sql: str) -> str | None:
    rows = db(sql)
    return rows[0][0] if rows and rows[0] else None


class Fixture:
    def __init__(self) -> None:
        self.s = Site()
        self.s.login()
        self.ids: dict[str, int] = {}

    def check(self, r, what: str) -> bool:
        msgs = self.s.messages(r.text)
        ok = any("saved" in m.lower() or "сохранен" in m.lower() for m in msgs)
        print(("  ok   " if ok else "  FAIL ") + what + ("" if ok else f" :: {msgs[:2]} {r.url[-80:]}"))
        return ok

    def exists(self, table: str, title: str, extra: str = "") -> int | None:
        v = one(f"SELECT id FROM PFX_{table} WHERE title = '{title.replace(chr(39), chr(92) + chr(39))}' {extra} LIMIT 1")
        return int(v) if v else None

    # --- склады
    def warehouse(self, title: str, address: str, phone: str, hours: str, ready: str) -> None:
        if self.exists("dharmashop_warehouses", title):
            print(f"  есть склад {title}")
            return
        r = self.s.submit_form(A + "&task=warehouse.add", {"jform[title]": title, "jform[address]": address, "jform[phone]": phone, "jform[hours]": hours, "jform[ready_term]": ready}, task="warehouse.save")
        self.check(r, f"склад {title}")

    # --- группы полей и поля
    def fieldgroup(self, title: str, target: str = "product") -> int | None:
        if (i := self.exists("dharmashop_fieldgroups", title)):
            return i
        r = self.s.submit_form(A + "&task=fieldgroup.add", {"jform[title]": title, "jform[target]": target}, task="fieldgroup.save")
        self.check(r, f"группа полей {title}")
        return self.exists("dharmashop_fieldgroups", title)

    def field(self, title: str, ftype: str, group_id: int | None, options: list[tuple] | None = None, unit: str = "", filterable: bool = True,
              in_card: bool = False, variation: bool = False, extra: dict | None = None) -> int | None:
        if (i := self.exists("dharmashop_fields", title)):
            self.ids[title] = i
            return i
        data = {"jform[title]": title, "jform[type]": ftype, "jform[target]": "product", "jform[unit]": unit, "jform[in_page]": "1", "jform[in_card]": "1" if in_card else "0",
                "jform[filterable]": "1" if filterable else "0", "jform[group_id]": str(group_id or 0), "jform[all_categories]": "1"}
        if variation:
            data["jform[params][variation]"] = "1"
        if ftype == "color":
            for n, (t, a, color) in enumerate(options or []):
                data[f"jform[color_options][color_options{n}][title]"] = t
                data[f"jform[color_options][color_options{n}][alias]"] = a
                data[f"jform[color_options][color_options{n}][color]"] = color
                data[f"jform[color_options][color_options{n}][swatch]"] = "color"
        elif ftype in {"list", "checkbox"}:
            for n, (t, a) in enumerate(options or []):
                data[f"jform[options][options{n}][title]"] = t
                data[f"jform[options][options{n}][alias]"] = a
        data.update(extra or {})
        r = self.s.submit_form(A + "&task=field.add", data, task="field.save")
        self.check(r, f"поле {title}")
        i = self.exists("dharmashop_fields", title)
        if i:
            self.ids[title] = i
        return i

    def base(self) -> None:
        print("== склады")
        self.warehouse("Основной склад", "г. Пермь, ул. Складская, 1", "+7 (342) 000-00-01", "Пн–Пт 9:00–18:00", "в день заказа")
        self.warehouse("Пункт выдачи «Центр»", "г. Пермь, ул. Ленина, 10", "+7 (342) 000-00-02", "Ежедневно 10:00–20:00", "на следующий день")
        print("== группы полей и поля")
        main = self.fieldgroup("Основные")
        tech = self.fieldgroup("Технические")
        self.field("Цвет", "color", main, [("Чёрный", "chernyj", "#111111"), ("Белый", "belyj", "#f5f5f5"), ("Синий", "sinij", "#2255aa"), ("Красный", "krasnyj", "#cc2222")],
                   in_card=True, variation=True)
        self.field("Размер", "list", main, [("S", "s"), ("M", "m"), ("L", "l"), ("XL", "xl")], variation=True)
        self.field("Материал", "list", main, [("Хлопок", "hlopok"), ("Полиэстер", "poliester"), ("Керамика", "keramika"), ("Пластик", "plastik")], in_card=True)
        self.field("Память", "list", tech, [("64 ГБ", "64-gb"), ("128 ГБ", "128-gb"), ("256 ГБ", "256-gb")], variation=True)
        self.field("Вес", "number", tech, unit="г", in_card=False)
        self.field("Диагональ", "number", tech, unit="дюйм")
        self.field("Особенности", "checkbox", tech, [("Влагозащита", "vlagozashita"), ("NFC", "nfc"), ("Bluetooth", "bluetooth")], extra={"jform[params][checkbox_mode]": "options"})

    # --- категории, бренды
    def category(self, title: str, parent: int = 1, extension: str = "com_dharmashop", intro: str = "") -> int | None:
        if (i := self.exists("categories", title, f"AND extension = '{extension}'")):
            self.ids[title] = i
            return i
        r = self.s.submit_form(A + f"&task=category.add&extension={extension}", {"jform[title]": title, "jform[parent_id]": str(parent), "jform[params][introtext]": intro,
                                                                           "jform[description]": ""}, task="category.save")
        self.check(r, f"{extension.split('.')[-1] if '.' in extension else 'категория'} {title}")
        i = self.exists("categories", title, f"AND extension = '{extension}'")
        if i:
            self.ids[title] = i
        return i

    def catalog(self) -> None:
        print("== категории")
        el = self.category("Электроника", intro="<p>Смартфоны, наушники и аксессуары.</p>")
        self.category("Смартфоны", el)
        self.category("Наушники", el)
        od = self.category("Одежда", intro="<p>Базовая одежда на каждый день.</p>")
        self.category("Футболки", od)
        self.category("Худи", od)
        dm = self.category("Дом")
        self.category("Кружки", dm)
        print("== бренды")
        for b in ("Aurora", "Nordic", "Pixel"):
            self.category(b, extension="com_dharmashop.brand")

    # --- товары
    def option_id(self, field: str, option: str) -> str:
        v = one(f"SELECT o.id FROM PFX_dharmashop_field_options o JOIN PFX_dharmashop_fields f ON f.id = o.field_id WHERE f.title = '{field}' AND o.title = '{option}' LIMIT 1")
        if not v:
            raise RuntimeError(f"нет значения {field}: {option}")
        return v

    def field_id(self, field: str) -> int:
        return int(one(f"SELECT id FROM PFX_dharmashop_fields WHERE title = '{field}' LIMIT 1") or 0)

    def taxonomy(self, title: str, extension: str) -> str:
        v = one(f"SELECT id FROM PFX_categories WHERE title = '{title}' AND extension = '{extension}' LIMIT 1")
        if not v:
            raise RuntimeError(f"нет {extension}: {title}")
        return v

    def image_paths(self, title: str, count: int, color: tuple[int, int, int]) -> list[str]:
        tmp = tempfile.mkdtemp(prefix="dharma-scn-")
        paths = []
        for n in range(count):
            f = os.path.join(tmp, f"img-{abs(hash((title, n))) % 10**8}.png")
            make_image(f, title if n == 0 else f"{title} · вид {n + 1}", tuple(max(0, min(255, c + 30 * n)) for c in color))
            paths.append(upload_image(self.s, f))
        return paths

    def product(self, title: str, category: str, brand: str | None, price: str, sku: str, stock: int | None, intro: str, fields: dict | None = None, badges: list[str] | None = None,
                discount: str = "", discount_days: int = 0, images: int = 2, color=(60, 90, 140), backorder: bool = False, state: int = 1, unit: str = "шт", extra: dict | None = None) -> int | None:
        if (i := self.exists("dharmashop_products", title)):
            print(f"  есть товар {title}")
            return i
        data = {"jform[title]": title, "jform[catid]": self.taxonomy(category, "com_dharmashop"), "jform[state]": str(state), "jform[introtext]": f"<p>{intro}</p>",
                "jform[fulltext]": f"<p>{intro} Подробное описание товара «{title}» для проверки страницы товара, вкладок и разметки.</p>", "jform[sku]": sku, "jform[price]": price,
                "jform[stock_tracked]": "1" if stock is not None else "0", "jform[stock_qty]": str(stock or 0), "jform[backorder]": "1" if backorder else "0", "jform[unit]": unit,
                "jform[discount]": discount}
        if discount and discount_days:
            data["jform[discount_start]"] = (dt.date.today() - dt.timedelta(days=1)).isoformat() + " 00:00:00"
            data["jform[discount_end]"] = (dt.date.today() + dt.timedelta(days=discount_days)).isoformat() + " 23:59:00"
        if brand:
            data["jform[brand_id]"] = self.taxonomy(brand, "com_dharmashop.brand")
        if badges:
            data["jform[badge_ids][]"] = [self.taxonomy(b, "com_dharmashop.badge") for b in badges]
        for fname, value in (fields or {}).items():
            fid = self.field_id(fname)
            ftype = one(f"SELECT type FROM PFX_dharmashop_fields WHERE id = {fid}")
            key = f"jform[custom_fields][f_{fid}]"
            if ftype == "number":
                data[key] = str(value)
            elif ftype == "checkbox":
                data[key + "[]"] = [self.option_id(fname, v) for v in value]
            else:
                data[key] = self.option_id(fname, value)
        for n, path in enumerate(self.image_paths(title, images, color) if images else []):
            data[f"jform[gallery][{n}][path]"] = path
            data[f"jform[gallery][{n}][alt]"] = title
            data[f"jform[gallery][{n}][title]"] = title
        data.update(extra or {})
        r = self.s.submit_form(A + "&task=product.add", data, task="product.save")
        self.check(r, f"товар {title}")
        return self.exists("dharmashop_products", title)

    def products(self) -> None:
        print("== товары (одиночные)")
        self.product("Наушники Aurora Sound", "Наушники", "Aurora", "4990", "AUR-SND-1", 25, "Беспроводные наушники с шумоподавлением.", {"Цвет": "Чёрный", "Материал": "Пластик", "Вес": 250, "Особенности": ["Bluetooth", "Влагозащита"]}, ["Хит"], color=(40, 60, 90))
        self.product("Наушники Nordic Air", "Наушники", "Nordic", "7990", "NRD-AIR-1", 8, "Лёгкие наушники для спорта.", {"Цвет": "Белый", "Материал": "Пластик", "Вес": 180, "Особенности": ["Bluetooth", "NFC"]}, ["Акция"], discount="15%", discount_days=14, color=(90, 110, 130))
        self.product("Наушники Pixel Buds", "Наушники", "Pixel", "3990", "PXL-BUD-1", 0, "Компактные наушники-вкладыши (нет в наличии).", {"Цвет": "Синий", "Вес": 60, "Особенности": ["Bluetooth"]}, color=(30, 60, 120))
        self.product("Кружка «Утро»", "Кружки", "Aurora", "590", "MUG-MORN", 120, "Керамическая кружка 350 мл.", {"Цвет": "Белый", "Материал": "Керамика", "Вес": 350}, ["Новинка"], color=(200, 160, 100))
        self.product("Кружка «Тёмная»", "Кружки", "Aurora", "650", "MUG-DARK", 0, "Чёрная матовая кружка; можно заказать под заказ.", {"Цвет": "Чёрный", "Материал": "Керамика", "Вес": 360}, backorder=True, color=(40, 40, 50))
        self.product("Худи Nordic Warm", "Худи", "Nordic", "3990", "HOOD-NW-L", 14, "Тёплое худи из плотного хлопка.", {"Цвет": "Синий", "Размер": "L", "Материал": "Хлопок", "Вес": 700}, ["Новинка", "Акция"], discount="25%", discount_days=7, color=(40, 70, 130))
        self.product("Чехол для смартфона", "Смартфоны", "Pixel", "790", "CASE-1", 40, "Прозрачный силиконовый чехол.", {"Цвет": "Белый", "Материал": "Пластик", "Вес": 40}, images=1, color=(150, 150, 160))
        self.product("Подарочный сертификат", "Дом", None, "", "GIFT-1", None, "Сертификат на любую сумму: цена по запросу.", images=1, color=(180, 120, 60))

    # --- группы товаров (варианты)
    def group(self, title: str, axes: list[str], values: dict[str, list[str]], category: str, brand: str | None, intro: str, price: str, stock: int,
              prices: dict | None = None, common: dict | None = None, badges: list[str] | None = None, images: int = 2, color=(70, 90, 120)) -> None:
        if self.exists("dharmashop_product_groups", title):
            print(f"  есть группа {title}")
            return
        axis_ids = [str(self.field_id(a)) for a in axes]
        cat = self.taxonomy(category, "com_dharmashop")
        data = {"jform[title]": title, "jform[axes][]": axis_ids, "jform[catid]": cat, "jform[introtext]": f"<p>{intro}</p>", "jform[generation_price]": price,
                "jform[generation_stock]": str(stock), "jform[generation_catid]": cat, "jform[copy_fields]": "1"}
        if brand:
            data["jform[brand_id]"] = self.taxonomy(brand, "com_dharmashop.brand")
        if badges:
            data["jform[badge_ids][]"] = [self.taxonomy(b, "com_dharmashop.badge") for b in badges]
        for fname, value in (common or {}).items():
            fid = self.field_id(fname)
            ftype = one(f"SELECT type FROM PFX_dharmashop_fields WHERE id = {fid}")
            key = f"jform[custom_fields][f_{fid}]"
            if ftype == "number":
                data[key] = str(value)
            elif ftype == "checkbox":
                data[key + "[]"] = [self.option_id(fname, v) for v in value]
            else:
                data[key] = self.option_id(fname, value)
        for n, path in enumerate(self.image_paths(title, images, color) if images else []):
            data[f"jform[gallery][{n}][path]"] = path
            data[f"jform[gallery][{n}][alt]"] = title
            data[f"jform[gallery][{n}][title]"] = title
        r = self.s.submit_form(A + "&task=productgroup.add", data, task="productgroup.apply")
        if not self.check(r, f"группа {title}"):
            return
        gid = self.exists("dharmashop_product_groups", title)
        edit = A + f"&task=productgroup.edit&id={gid}"
        gen = {"jform[axes][]": axis_ids, "jform[generation_catid]": cat, "jform[generation_price]": price, "jform[generation_stock]": str(stock), "jform[generation_state]": "1"}
        for a, opts in values.items():
            gen[f"jform[generation_values][f_{self.field_id(a)}][]"] = [self.option_id(a, o) for o in opts]
        r = self.s.submit_form(edit, gen, task="productgroup.prepare")
        action, fields = self.s.parse_form(r.text)
        rows = [(k, v) for k, v in fields if k != "task"]
        if prices:
            # цена по значению оси: {"Память": {"128 ГБ": "29990"}}
            axis_by_id = {str(self.field_id(a)): a for a in axes}
            idx: dict[str, dict[str, str]] = {}
            for k, v in rows:
                if k.startswith("jform[executions][executions") and "][axis_" in k:
                    n = k.split("[")[2].rstrip("]")
                    idx.setdefault(n, {})[axis_by_id.get(k.split("axis_")[1].rstrip("]"), "?")] = v
            for n, got in idx.items():
                for axis, mapping in prices.items():
                    for opt, value in mapping.items():
                        if got.get(axis) == self.option_id(axis, opt):
                            rows = [(k, value if k == f"jform[executions][{n}][price]" else vv) for k, vv in rows]
        rows.append(("task", "productgroup.generate"))
        r2 = self.s.post(urllib.parse.urljoin(r.url, action), rows)
        msgs = self.s.messages(r2.text)
        print(("  ok   " if any("created" in m.lower() for m in msgs) else "  FAIL ") + f"варианты группы {title}: {msgs[:1]}")

    def groups(self) -> None:
        print("== группы товаров")
        self.group("Футболка Basic", ["Цвет", "Размер"], {"Цвет": ["Чёрный", "Белый"], "Размер": ["S", "M", "L"]}, "Футболки", "Nordic", "Базовая футболка из плотного хлопка.", "1490", 20,
                   common={"Материал": "Хлопок"}, badges=["Хит"], color=(90, 90, 100))
        self.group("Смартфон Pixel P", ["Память", "Цвет"], {"Память": ["128 ГБ", "256 ГБ"], "Цвет": ["Чёрный", "Синий"]}, "Смартфоны", "Pixel", "Смартфон с ярким экраном и хорошей камерой.", "29990", 10,
                   prices={"Память": {"256 ГБ": "34990"}}, common={"Материал": "Пластик", "Вес": 190, "Диагональ": 6.1, "Особенности": ["NFC", "Bluetooth", "Влагозащита"]}, badges=["Новинка"], color=(30, 50, 110))

    # --- доставка, оплата, акции
    def shipping(self, title: str, plugin: str, params: dict, warehouses: list[str] | None = None, description: str = "", default: bool = False) -> None:
        if self.exists("dharmashop_shipping_methods", title):
            print(f"  есть доставка {title}")
            return
        data = {"jform[title]": title, "jform[plugin]": plugin, "jform[description]": description, "jform[state]": "1", "jform[is_default]": "1" if default else "0"}
        for k, v in params.items():
            data[f"jform[params][{plugin}][{k}]"] = v
        if warehouses:
            data[f"jform[params][{plugin}][warehouses][]"] = [one(f"SELECT id FROM PFX_dharmashop_warehouses WHERE title = '{w}' LIMIT 1") or "" for w in warehouses]
        r = self.s.submit_form(A + "&task=shipping.add", data, task="shipping.save")
        self.check(r, f"доставка {title}")

    def payment(self, title: str, plugin: str, params: dict | None = None, description: str = "", default: bool = False, paid_status: str | None = None) -> None:
        if self.exists("dharmashop_payment_methods", title):
            print(f"  есть оплата {title}")
            return
        data = {"jform[title]": title, "jform[plugin]": plugin, "jform[description]": description, "jform[state]": "1", "jform[is_default]": "1" if default else "0"}
        for k, v in (params or {}).items():
            data[f"jform[params][{plugin}][{k}]"] = v
        if paid_status:
            data["jform[paid_status_id]"] = one(f"SELECT id FROM PFX_dharmashop_statuses WHERE title = '{paid_status}' LIMIT 1") or ""
        r = self.s.submit_form(A + "&task=payment.add", data, task="payment.save")
        self.check(r, f"оплата {title}")

    def shop(self) -> None:
        print("== доставка")
        self.shipping("Самовывоз", "pickup", {"price": "0", "term": "в день заказа", "map": "none"}, ["Основной склад", "Пункт выдачи «Центр»"], "Заберите заказ сами.", default=True)
        self.shipping("Курьер по Перми", "courier", {"price_mode": "fixed", "price": "350", "free_from": "5000", "term": "1–2 дня", "map": "none", "suggest": "0", "verify_address": "0"}, ["Основной склад"], "Курьером до двери.")
        self.shipping("Почта России", "standard", {"price": "450", "term": "5–10 дней"}, None, "Доставка по России.")
        print("== оплата")
        self.payment("Оплата при получении", "standard", {"instruction": "Оплатите заказ наличными или картой при получении."}, "Платите, когда получите заказ.", default=True)
        enable_plugin(self.s, "dharmashoppayment", "sandbox")
        self.payment("Онлайн-оплата (тест)", "sandbox", None, "Тестовая оплата без списания денег.", paid_status="Paid")
        post_id = one("SELECT id FROM PFX_dharmashop_shipping_methods WHERE title = 'Почта России'")
        cash_id = one("SELECT id FROM PFX_dharmashop_payment_methods WHERE title = 'Оплата при получении'")
        if post_id and cash_id and not one(f"SELECT payment_ids FROM PFX_dharmashop_shipping_methods WHERE id = {post_id} AND payment_ids <> ''"):
            r = self.s.submit_form(A + f"&task=shipping.edit&id={post_id}", {"jform[payment_ids][]": [cash_id]}, task="shipping.save")
            self.check(r, "правило: Почта России → только оплата при получении")
        print("== акции")
        enable_plugin(self.s, "dharmashoppromotion", "threshold")
        if not self.exists("dharmashop_promotions", "Скидка от суммы"):
            data = {"jform[title]": "Скидка от суммы", "jform[plugin]": "threshold", "jform[state]": "1",
                    "jform[params][tiers][tiers0][minimum_subtotal]": "5000", "jform[params][tiers][tiers0][discount_type]": "percent", "jform[params][tiers][tiers0][discount_value]": "5",
                    "jform[params][tiers][tiers1][minimum_subtotal]": "10000", "jform[params][tiers][tiers1][discount_type]": "percent", "jform[params][tiers][tiers1][discount_value]": "10"}
            r = self.s.submit_form(A + "&task=promotion.add", data, task="promotion.save")
            self.check(r, "акция «Скидка от суммы»")
        if not self.exists("dharmashop_promotions", "Купоны"):
            data = {"jform[title]": "Купоны", "jform[plugin]": "coupon", "jform[state]": "1",
                    "jform[params][coupons][coupons0][code]": "WELCOME10", "jform[params][coupons][coupons0][description]": "Скидка 10% новым покупателям", "jform[params][coupons][coupons0][discount_type]": "percent",
                    "jform[params][coupons][coupons0][discount_value]": "10", "jform[params][coupons][coupons0][minimum_subtotal]": "1000",
                    "jform[params][coupons][coupons1][code]": "MINUS500", "jform[params][coupons][coupons1][description]": "500 ₽ от 3000 ₽", "jform[params][coupons][coupons1][discount_type]": "amount",
                    "jform[params][coupons][coupons1][discount_value]": "500", "jform[params][coupons][coupons1][minimum_subtotal]": "3000"}
            r = self.s.submit_form(A + "&task=promotion.add", data, task="promotion.save")
            self.check(r, "акция «Купоны»")

    # --- настройки: контакты и согласие на оформлении (как это сделал бы администратор в «Настройках»)
    def checkout(self) -> None:
        print("== настройки оформления")
        url = "/administrator/index.php?option=com_config&view=component&component=com_dharmashop"
        data = {}
        for n, (typ, name, label, req) in enumerate([("text", "name", "Имя", 1), ("tel", "phone", "Телефон", 1), ("email", "email", "E-mail", 1), ("textarea", "comment", "Комментарий к заказу", 0)]):
            pre = f"jform[checkout_fields][checkout_fields{n}]"
            data.update({f"{pre}[type]": typ, f"{pre}[name]": name, f"{pre}[label]": label, f"{pre}[required]": str(req), f"{pre}[enabled]": "1", f"{pre}[show_label]": "1"})
        data.update({"jform[checkout_consents][checkout_consents0][name]": "personal_data", "jform[checkout_consents][checkout_consents0][text]": "Я согласен на обработку персональных данных",
                     "jform[checkout_consents][checkout_consents0][required]": "1", "jform[checkout_consents][checkout_consents0][enabled]": "1"})
        data.update({"jform[price_zero_replace]": "1", "jform[price_zero_text]": "Цена по запросу"})
        r = self.s.submit_form(url, data, task="component.apply")
        msgs = self.s.messages(r.text)
        print("  " + ("ok   " if any("saved" in m.lower() for m in msgs) else "FAIL ") + f"контакты и согласие {msgs[:2]}")

    # --- посадочные страницы и модули
    def landing_page(self, title: str, data: dict) -> None:
        if self.exists("dharmashop_landing_pages", title):
            print(f"  есть страница {title}")
            return
        base = {"jform[title]": title, "jform[state]": "1", "jform[access]": "1"}
        base.update(data)
        r = self.s.submit_form("/administrator/index.php?option=com_dharmashop_landing&task=page.add", base, task="page.save")
        self.check(r, f"посадочная страница {title}")

    def landing(self) -> None:
        print("== посадочные страницы")
        self.landing_page("Смартфоны Pixel", {"jform[catid]": self.taxonomy("Смартфоны", "com_dharmashop"), "jform[source]": "filter", "jform[cond_brands][]": [self.taxonomy("Pixel", "com_dharmashop.brand")],
                                               "jform[introtext]": "<p>Все смартфоны Pixel в одной подборке.</p>", "jform[fulltext]": "<p>Подборка собирается сама из каталога по бренду.</p>", "jform[meta_title]": "Смартфоны Pixel — купить"})
        cvet = one("SELECT alias FROM PFX_dharmashop_fields WHERE title = 'Цвет'")
        black = one("SELECT o.alias FROM PFX_dharmashop_field_options o JOIN PFX_dharmashop_fields f ON f.id = o.field_id WHERE f.title = 'Цвет' AND o.title = 'Чёрный'")
        self.landing_page("Чёрные товары", {"jform[catid]": "0", "jform[source]": "filter", "jform[cond_values][]": [f"{cvet}:{black}"], "jform[introtext]": "<p>Всё чёрное в нашем магазине.</p>"})
        ids = [one(f"SELECT id FROM PFX_dharmashop_products WHERE title = '{t}'") for t in ("Кружка «Утро»", "Подарочный сертификат", "Худи Nordic Warm")]
        manual = {"jform[catid]": "0", "jform[source]": "manual", "jform[introtext]": "<p>Идеи подарков.</p>"}
        for n, i in enumerate(ids):
            manual[f"jform[products][products{n}][product_id]"] = i or ""
        self.landing_page("Подарки", manual)

    def module(self, module_id: int, position: str, assign_all: bool = True, title: str | None = None, extra: dict | None = None) -> None:
        data = {"jform[position]": position, "jform[published]": "1", "jform[assignment]": "0"}
        if title:
            data["jform[title]"] = title
        data.update(extra or {})
        r = self.s.submit_form(f"/administrator/index.php?option=com_modules&task=module.edit&id={module_id}", data, task="module.save", remove_prefixes=())
        msgs = self.s.messages(r.text)
        print(("  ok   " if any("saved" in m.lower() for m in msgs) else "  FAIL ") + f"модуль {module_id} → {position} {msgs[:1] if not any('saved' in m.lower() for m in msgs) else ''}")

    def modules(self) -> None:
        print("== модули")
        rows = {r[1]: int(r[0]) for r in db("SELECT id, module FROM PFX_modules WHERE module LIKE 'mod_dharmashop%' AND client_id = 0")}
        self.module(rows["mod_dharmashop_cart"], "topbar")
        self.module(rows["mod_dharmashop_favorites"], "topbar")
        self.module(rows["mod_dharmashop_search"], "search")
        self.module(rows["mod_dharmashop_filter"], "dharmashop-products-left")
        self.module(rows["mod_dharmashop_categories"], "sidebar-left")
        self.module(rows["mod_dharmashop_products"], "main-bottom")


def main() -> None:
    phases = sys.argv[1:] or ["all"]
    f = Fixture()
    for phase in (["base", "catalog", "products", "groups", "shop", "checkout", "landing", "modules"] if "all" in phases else phases):
        getattr(f, phase)()
    if f.s.problems:
        print("ПРОБЛЕМЫ ОТВЕТОВ:", *f.s.problems[:20], sep="\n  ")


if __name__ == "__main__":
    main()
