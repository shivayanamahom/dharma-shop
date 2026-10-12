#!/usr/bin/env python3
from __future__ import annotations
"""Rebuild docs/SETTINGS_REFERENCE.md from the Joomla Form XML and RU language files."""
from pathlib import Path
import re
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]


def translations(path: Path) -> dict[str, str]:
    result: dict[str, str] = {}
    # Component entity forms can include field-type labels/descriptions owned
    # by the provider plugin. Load provider strings first, then component RU as
    # a fallback so the same XML resolves in either context.
    component_ini = ROOT / "com_dharmashop/admin/language/ru-RU/com_dharmashop.ini"
    plugin_languages = tuple(sorted(ROOT.glob("plg_*/language/ru-RU/*.ini")))
    files = (path, path.with_name(path.stem + ".sys.ini"), component_ini, *plugin_languages)
    for language_file in files:
        if not language_file.exists():
            continue
        for line in language_file.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if not line or line.startswith((";", "#")) or "=" not in line:
                continue
            key, value = line.split("=", 1)
            value = value.strip()
            if len(value) >= 2 and value[0] == value[-1] and value[0] in "\"'":
                value = value[1:-1]
            result.setdefault(key.strip(), value.replace("\\n", " ").replace("\\\"", '"'))
    result.update({"JYES": "Да", "JNO": "Нет"})
    result.update({
        "JGLOBAL_TITLE": "Заголовок",
        "JENABLED": "Включено",
        "JCATEGORIES": "Категории",
        "COM_MODULES_FIELD_CACHING_LABEL": "Кеширование",
        "COM_MODULES_FIELD_CACHING_DESC": "Использовать кеширование Joomla для модуля.",
        "COM_MODULES_FIELD_MODULECLASS_SFX_LABEL": "Суффикс CSS-класса модуля",
        "COM_MODULES_FIELD_VALUE_NOCACHING": "Без кеширования",
        "JCONFIG_PERMISSIONS_LABEL": "Права доступа Joomla",
        "JGLOBAL_FIELD_ID_LABEL": "ID",
        "JFIELD_ALIAS_LABEL": "Псевдоним",
        "JFIELD_ALIAS_DESC": "Адресная часть URL. Если не заполнить, она будет создана из заголовка.",
        "JSTATUS": "Состояние",
        "JPUBLISHED": "Опубликовано",
        "JUNPUBLISHED": "Не опубликовано",
        "JARCHIVED": "В архиве",
        "JTRASHED": "В корзине",
        "JFIELD_ACCESS_LABEL": "Доступ",
        "JFIELD_ORDERING_LABEL": "Порядок",
        "JFIELD_NOTE_LABEL": "Примечание",
        "JFIELD_RULES_LABEL": "Права доступа",
        "JGLOBAL_EMAIL": "Email",
        "JSEARCH_FILTER": "Поиск",
        "JGLOBAL_SORT_BY": "Сортировка",
        "JGLOBAL_LIST_LIMIT": "Количество строк",
        "JOPTION_SELECT_PUBLISHED": "Выберите состояние",
        "JOPTION_SELECT_MAX_LEVELS": "Выберите уровень",
        "JOPTION_SELECT_CATEGORY": "Выберите категорию",
        "JGLOBAL_ROOT_PARENT": "Корневой уровень",
        "JGLOBAL_FIELD_CREATED_LABEL": "Создано",
        "JGLOBAL_FIELD_CREATED_BY_LABEL": "Автор создания",
        "JGLOBAL_FIELD_MODIFIED_LABEL": "Изменено",
        "JGLOBAL_FIELD_MODIFIED_BY_LABEL": "Автор изменения",
        "JALL": "Все",
        "JDATE_ASC": "Сначала старые",
        "JDATE_DESC": "Сначала новые",
        "JFIELD_ALT_LAYOUT_LABEL": "Макет",
        "JGLOBAL_TITLE_ASC": "Заголовок по возрастанию",
        "JGLOBAL_TITLE_DESC": "Заголовок по убыванию",
        "JGRID_HEADING_ORDERING_ASC": "Порядок по возрастанию",
        "JGRID_HEADING_ORDERING_DESC": "Порядок по убыванию",
        "JGRID_HEADING_ID_ASC": "ID по возрастанию",
        "JGRID_HEADING_ID_DESC": "ID по убыванию",
        "JSTATUS_ASC": "Состояние по возрастанию",
        "JSTATUS_DESC": "Состояние по убыванию",
    })
    return result


def text(key: str | None, dictionary: dict[str, str]) -> str:
    if not key:
        return "—"
    if key in dictionary:
        return dictionary[key]
    return "—" if re.fullmatch(r"(?:COM|PLG|J)[A-Z0-9_]+", key) else key


def md(value: str) -> str:
    return value.replace("|", "\\|").replace("\n", " ").strip()


def options(field: ET.Element, dictionary: dict[str, str]) -> str:
    values = []
    for option in field.findall("option"):
        value = option.get("value", "")
        label = "".join(option.itertext()).strip()
        values.append(f"`{value}` — {text(label, dictionary)}")
    return "; ".join(values) or "—"


def fields_for(xml_path: Path, language_path: Path, title: str) -> list[str]:
    root = ET.parse(xml_path).getroot()
    dictionary = translations(language_path)
    out = [f"## {title}", "", "| Вкладка/раздел | Настройка | ID в форме | По умолчанию / значения | Подсказка формы |", "|---|---|---|---|---|"]

    def visit(parent: ET.Element, groups: list[str]) -> None:
        for child in parent:
            if child.tag == "fieldset":
                label = text(child.get("label"), dictionary)
                visit(child, groups + ([label] if label != "—" else []))
            elif child.tag == "field" and child.get("name"):
                kind = child.get("type", "field")
                if kind in {"note", "spacer", "hidden", "settingssearch"}:
                    continue
                label = text(child.get("label"), dictionary)
                description = text(child.get("description"), dictionary)
                default = child.get("default", "—")
                if kind == "subform":
                    default = "Динамические строки (см. вложенную форму)"
                if child.findall("option"):
                    default = f"{default}; " if default != "—" else ""
                    default += options(child, dictionary)
                if child.get("showon"):
                    description = (description + " " if description != "—" else "") + f"Показывается при `{child.get('showon')}`."
                if kind not in {"list", "radio", "checkboxes"}:
                    default = f"{default}; тип `{kind}`" if default != "—" else f"тип `{kind}`"
                out.append("| " + " / ".join(map(md, groups)) + " | " + md(label) + " | `" + child.get("name", "") + "` | " + md(default) + " | " + md(description) + " |")
            else:
                visit(child, groups)

    visit(root, [])
    return out


component = fields_for(
    ROOT / "com_dharmashop/admin/config.xml",
    ROOT / "com_dharmashop/admin/language/ru-RU/com_dharmashop.ini",
    "Настройки магазина (`com_dharmashop`)",
)
modules = []
for module in ("cart", "categories", "filter", "search"):
    folder = ROOT / f"mod_dharmashop_{module}"
    modules.extend(fields_for(folder / f"mod_dharmashop_{module}.xml", folder / f"language/ru-RU/mod_dharmashop_{module}.ini", f"Модуль `mod_dharmashop_{module}`"))

plugin_forms = []
for folder in sorted(ROOT.glob("plg_*")):
    language_path = folder / "language/ru-RU" / f"{folder.name}.ini"
    for xml_path in sorted((folder / "forms").rglob("*.xml")) if (folder / "forms").exists() else []:
        relative = xml_path.relative_to(folder / "forms").with_suffix("")
        plugin_forms.extend(fields_for(
            xml_path,
            language_path,
            f"Форма `{folder.name}` / `{relative.as_posix()}`",
        ))

component_forms = []
for xml_path in sorted((ROOT / "com_dharmashop/admin/forms").rglob("*.xml")):
    relative = xml_path.relative_to(ROOT / "com_dharmashop/admin/forms").with_suffix("")
    component_forms.extend(fields_for(
        xml_path,
        ROOT / "com_dharmashop/admin/language/ru-RU/com_dharmashop.ini",
        f"Форма компонента / `{relative.as_posix()}`",
    ))

content = [
    "# Полный справочник настроек Dharma Shop", "",
    "Список собран из XML-форм Joomla: ID, заголовки, значения по умолчанию и условия показа сверяются с кодом. Подсказка берётся из RU-языкового файла формы. Это справочник полей интерфейса, не описание внутреннего хранения. Включены общие настройки магазина, все четыре модуля, формы записей компонента и формы типов плагинов (в том числе вложенные настройки доставки, оплаты и связанных товаров).",
    "",
    "Обновить после изменения форм: `python3 dev/generate-help-settings.py`. Результат производный, вручную не редактируйте. Для непереведённых Joomla-клавиш используйте общие подписи в генераторе; смысловые пояснения дополняйте в пользовательской справке или руководстве подсистемы.", "",
    *component, "", *modules, "", "## Формы записей компонента", "",
    "Индекс включает Form XML карточек и фильтров компонента. Для устройства экрана, обязательности полей и правил сохранения используйте предметное руководство.", "",
    *component_forms, "", "## Формы типов и плагинов", "",
    "Эти параметры показываются внутри соответствующих записей магазина или повторяемых блоков. Условия `showon` отражают когда поле формы активно. Смысл, место открытия формы и ограничения см. в [справке пользователя](HELP.md) и профильном руководстве.", "",
    *plugin_forms, "",
    "## Настройки плагинов", "",
    "Общие параметры уведомлений, SEO и размеров изображений находятся в настройках компонента; формы конкретных типов — в настройках записей доставки/оплаты, определений полей или блоков связанных товаров. У самих плагинов могут быть только служебные параметры Joomla. Смотрите каталог расширений в [справке пользователя](HELP.md).", "",
    "## Другие формы компонента", "",
    "Формы товаров, групп, категорий, полей, заказов, складов и способов оплаты/доставки описаны отдельно: [поля и типы](FIELDS.md), [варианты и группы](VARIANTS.md), [заказы](ORDERS.md), [доставка](SHIPPING.md), [оплата](PAYMENTS.md), [склады](WAREHOUSES.md). Динамические поля и повторяемые строки (`subform`) берут состав из отдельной формы; их схема указана в этих руководствах.", "",
]
(ROOT / "docs/SETTINGS_REFERENCE.md").write_text("\n".join(content), encoding="utf-8")
print("Обновлён docs/SETTINGS_REFERENCE.md")
