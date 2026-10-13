#!/usr/bin/env bash
# Dev-only: раскладывает компонент импорта и плагин XLSX из рабочего дерева в сайт DHARMA_SITE (по умолчанию test).
set -euo pipefail
S=/Volumes/Work/Joomla/dharma_shop; T="${DHARMA_SITE:-/Volumes/Work/Server/www/test}"; R="rsync -a --delete --exclude .DS_Store"
$R "$S/com_dharmashop_import/admin/" "$T/administrator/components/com_dharmashop_import/" --exclude language
cp "$S/com_dharmashop_import/dharmashop_import.xml" "$T/administrator/components/com_dharmashop_import/" 2>/dev/null || cp "$S"/com_dharmashop_import/*.xml "$T/administrator/components/com_dharmashop_import/"
[ -d "$S/com_dharmashop_import/media" ] && { mkdir -p "$T/media/com_dharmashop_import"; $R "$S/com_dharmashop_import/media/" "$T/media/com_dharmashop_import/"; } || true
for l in ru-RU en-GB; do [ -d "$T/administrator/language/$l" ] && cp "$S/com_dharmashop_import/admin/language/$l/"*.ini "$T/administrator/language/$l/" || true; done
P="$S/plg_dharmashopimport_xlsx"; D="$T/plugins/dharmashopimport/xlsx"; mkdir -p "$D"
for d in src services forms; do [ -d "$P/$d" ] && $R "$P/$d/" "$D/$d/" || true; done; cp "$P/xlsx.xml" "$D/"
for l in ru-RU en-GB; do [ -d "$T/administrator/language/$l" ] && [ -d "$P/language/$l" ] && cp "$P/language/$l/"*.ini "$T/administrator/language/$l/" || true; done
rm -f "$T"/administrator/cache/language/*.php 2>/dev/null || true
echo "import synced"
