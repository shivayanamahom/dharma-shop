#!/usr/bin/env bash
# Собирает установочный пакет Joomla: dist/pkg_dharmashop-<версия>.zip
# Внутри пакета каждое расширение лежит отдельным zip — так Joomla однозначно определяет тип пакета.
set -euo pipefail
cd "$(dirname "$0")"

version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' pkg_dharmashop.xml | head -n1)
out="$PWD/dist/pkg_dharmashop-${version}.zip"
stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT

mkdir -p dist
rm -f "$out"

# Список расширений пакета (папка = имя zip). Добавляйте сюда новые плагины и модули.
EXTENSIONS=(plg_system_dharmashoprelated plg_dharmashop_imageresize files_dharmashop_layouts com_dharmashop plg_dharmashopfield_basic plg_dharmashopnotify_email plg_dharmashopnotify_telegram plg_dharmashopshipping_standard plg_dharmashopshipping_courier plg_dharmashopshipping_pickup plg_dharmashoppayment_standard plg_dharmashoppayment_sandbox plg_dharmashop_social plg_dharmashop_structureddata plg_system_dharmashopswiper plg_system_dharmashopfancybox plg_system_dharmashopmenu mod_dharmashop_cart mod_dharmashop_search mod_dharmashop_filter mod_dharmashop_categories)

for ext in "${EXTENSIONS[@]}"; do
  (cd "$ext" && zip -r -q "$stage/$ext.zip" . -x '*.DS_Store')
done

cp pkg_dharmashop.xml script.php LICENSE.txt "$stage/"
cp -R language "$stage/language"

(cd "$stage" && zip -r -q "$out" . -x '*.DS_Store')

echo "Готово: $out"
