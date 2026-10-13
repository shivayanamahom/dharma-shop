#!/usr/bin/env bash
# Dev-only: раскладывает компонент экспорта, плагины форматов, задачу планировщика и плагин меню из рабочего дерева в тестовый сайт.
# Путь к сайту: DHARMA_SITE (по умолчанию /Volumes/Work/Server/www/test). Языковые файлы копируются только в уже существующие папки языков.
set -euo pipefail
S="$(cd "$(dirname "$0")/.." && pwd)"
T="${DHARMA_SITE:-/Volumes/Work/Server/www/test}"
R="rsync -a --delete --exclude .DS_Store"
copy_language() { # <каталог с языками источника> <имя файла-маски>
  for l in ru-RU en-GB; do
    if [ -d "$1/$l" ] && [ -d "$T/administrator/language/$l" ]; then cp "$1/$l/"*.ini "$T/administrator/language/$l/"; fi
  done
}
E="$S/com_dharmashop_export"
if [ -d "$E" ]; then
  mkdir -p "$T/administrator/components/com_dharmashop_export" "$T/media/com_dharmashop_export"
  $R "$E/admin/" "$T/administrator/components/com_dharmashop_export/" --exclude language
  cp "$E/dharmashop_export.xml" "$T/administrator/components/com_dharmashop_export/"
  [ -d "$E/media" ] && $R "$E/media/" "$T/media/com_dharmashop_export/" || true
  copy_language "$E/admin/language"
fi
# Пары «папка в репозитории : путь плагина : имя файла манифеста».
for pair in "plg_dharmashopexport_xlsx:dharmashopexport/xlsx:xlsx" "plg_dharmashopexport_yml:dharmashopexport/yml:yml" "plg_task_dharmashopexport:task/dharmashopexport:dharmashopexport" "plg_system_dharmashopmenu:system/dharmashopmenu:dharmashopmenu"; do
  src="$S/${pair%%:*}"; rest="${pair#*:}"; dest="$T/plugins/${rest%%:*}"; name="${rest##*:}"
  if [ -d "$src" ]; then
    mkdir -p "$dest"
    for d in src services forms; do if [ -d "$src/$d" ]; then $R "$src/$d/" "$dest/$d/"; fi; done
    cp "$src/$name.xml" "$dest/"
    copy_language "$src/language"
  fi
done
# Общее окно прогресса магазина (скрипт и версия ассета) нужно списку профилей экспорта.
[ -d "$S/com_dharmashop/media" ] && $R "$S/com_dharmashop/media/" "$T/media/com_dharmashop/" || true
rm -rf "$T/administrator/cache/language"/*.php 2>/dev/null || true
echo "export synced"
