#!/usr/bin/env bash
# Собирает установочный пакет Joomla: dist/pkg_dharmashop-<версия>.zip
# Внутри пакета каждое расширение лежит отдельным zip — так Joomla однозначно определяет тип пакета.
set -euo pipefail
cd "$(dirname "$0")"

python3 dev/check-component-language-keys.py

version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' pkg_dharmashop.xml | head -n1)
out="$PWD/dist/pkg_dharmashop-${version}.zip"
stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT

mkdir -p dist
rm -f "$out"

# Список расширений пакета (папка = имя zip). Добавляйте сюда новые плагины и модули.
EXTENSIONS=(plg_system_dharmashoppositions plg_system_dharmashoprelated plg_dharmashop_imageresize files_dharmashop_layouts com_dharmashop com_dharmashop_landing com_dharmashop_export com_dharmashop_import plg_dharmashopimport_xlsx plg_dharmashopexport_xlsx plg_dharmashopexport_yml plg_console_dharmashopexport plg_task_dharmashopexport plg_console_dharmashopimport plg_task_dharmashopimport plg_dharmashopfield_basic plg_dharmashopnotify_email plg_dharmashopnotify_telegram plg_dharmashopshipping_standard plg_dharmashopshipping_courier plg_dharmashopshipping_pickup plg_dharmashoppayment_standard plg_dharmashoppayment_sandbox plg_dharmashoppromotion_coupon plg_dharmashoppromotion_threshold plg_dharmashop_social plg_dharmashop_structureddata plg_radicalsitemap_dharmashop plg_system_dharmashopswiper plg_system_dharmashopfancybox plg_system_dharmashopmenu plg_task_dharmashop mod_dharmashop_cart mod_dharmashop_favorites mod_dharmashop_search mod_dharmashop_filter mod_dharmashop_categories mod_dharmashop_products)

for ext in "${EXTENSIONS[@]}"; do
  if [[ "$ext" == "plg_radicalsitemap_dharmashop" ]]; then
    (cd "integrations/$ext" && zip -r -q "$stage/$ext.zip" dharmashop.xml services src language -x '*.DS_Store')
  elif [[ "$ext" == "com_dharmashop" ]]; then
    cp -R "$ext" "$stage/$ext"
    python3 dev/generate-help-site.py "$stage/$ext/admin/help"
    (cd "$stage/$ext" && zip -r -q "$stage/$ext.zip" . -x '*.DS_Store')
  else
    (cd "$ext" && zip -r -q "$stage/$ext.zip" . -x '*.DS_Store')
  fi
done

# DHARMA_KEEP_ZIPS=<папка>: отдельные zip расширений сохраняются (для лент обновлений, dev/release.py).
if [ -n "${DHARMA_KEEP_ZIPS:-}" ]; then
  mkdir -p "$DHARMA_KEEP_ZIPS"
  for ext in "${EXTENSIONS[@]}"; do cp "$stage/$ext.zip" "$DHARMA_KEEP_ZIPS/$ext.zip"; done
fi

cp pkg_dharmashop.xml script.php LICENSE.txt "$stage/"
cp -R language "$stage/language"

(cd "$stage" && zip -r -q "$out" . -x '*.DS_Store')

echo "Готово: $out"
