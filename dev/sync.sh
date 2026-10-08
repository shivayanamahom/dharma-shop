#!/usr/bin/env bash
# Dev-only: раскладывает исходники в тестовый сайт без пересборки zip (после первой установки пакета).
# Путь к сайту: DHARMA_SITE (по умолчанию /Volumes/Work/Server/www/test).
set -euo pipefail
S="$(cd "$(dirname "$0")/.." && pwd)"
T="${DHARMA_SITE:-/Volumes/Work/Server/www/test}"
R="rsync -a --delete --exclude .DS_Store"
$R "$S/com_dharmashop/admin/" "$T/administrator/components/com_dharmashop/" --exclude language
cp "$S/com_dharmashop/dharmashop.xml" "$T/administrator/components/com_dharmashop/"
for l in ru-RU en-GB; do
  cp "$S/com_dharmashop/admin/language/$l/"*.ini "$T/administrator/language/$l/"
done
[ -d "$S/com_dharmashop/site" ] && $R "$S/com_dharmashop/site/" "$T/components/com_dharmashop/" --exclude /layouts/ --exclude /language/ || true
# Языковые файлы сайта (при установке пакета манифест кладёт их в language/<тег>/).
for l in ru-RU en-GB; do
  [ -f "$S/com_dharmashop/site/language/$l/com_dharmashop.ini" ] && cp "$S/com_dharmashop/site/language/$l/com_dharmashop.ini" "$T/language/$l/com_dharmashop.ini" || true
done
# Общая библиотека: дополнительные пользовательские файлы не удаляем.
if [ -d "$S/files_dharmashop_layouts/layouts" ]; then
  mkdir -p "$T/layouts/components/dharmashop"
  rsync -a --exclude .DS_Store "$S/files_dharmashop_layouts/layouts/" "$T/layouts/components/dharmashop/"
fi
[ -d "$S/com_dharmashop/media" ] && $R "$S/com_dharmashop/media/" "$T/media/com_dharmashop/" || true
# Модуль корзины: код, media и языковые файлы (как при установке пакета).
M="$S/mod_dharmashop_cart"
if [ -d "$M" ]; then
  mkdir -p "$T/modules/mod_dharmashop_cart" "$T/media/mod_dharmashop_cart"
  for d in services src tmpl; do $R "$M/$d/" "$T/modules/mod_dharmashop_cart/$d/"; done
  cp "$M/mod_dharmashop_cart.xml" "$T/modules/mod_dharmashop_cart/"
  [ -d "$M/media" ] && $R "$M/media/" "$T/media/mod_dharmashop_cart/" || true
  for l in ru-RU en-GB; do cp "$M/language/$l/"*.ini "$T/language/$l/"; done
fi
# Модуль поиска: код, media и языковые файлы.
M="$S/mod_dharmashop_search"
if [ -d "$M" ]; then
  mkdir -p "$T/modules/mod_dharmashop_search" "$T/media/mod_dharmashop_search"
  for d in services src tmpl; do $R "$M/$d/" "$T/modules/mod_dharmashop_search/$d/"; done
  cp "$M/mod_dharmashop_search.xml" "$T/modules/mod_dharmashop_search/"
  [ -d "$M/media" ] && $R "$M/media/" "$T/media/mod_dharmashop_search/" || true
  for l in ru-RU en-GB; do cp "$M/language/$l/"*.ini "$T/language/$l/"; done
fi
echo synced
