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
[ -d "$S/com_dharmashop/site" ] && $R "$S/com_dharmashop/site/" "$T/components/com_dharmashop/" || true
[ -d "$S/com_dharmashop/media" ] && $R "$S/com_dharmashop/media/" "$T/media/com_dharmashop/" || true
echo synced
