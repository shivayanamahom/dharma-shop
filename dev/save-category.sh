#!/usr/bin/env bash
# Dev-only: создаёт категорию/бренд/бейдж через форму админки по HTTP (как браузер). Нужен вход: dev/web-login.sh.
#   dev/save-category.sh <extension> "Название" [parent_id] [цвет-бейджа]
set -euo pipefail
URL="${DHARMA_URL:-https://test.test}"; J="${COOKIES:-${TMPDIR:-/tmp}/dharma-cookies.txt}"; EXT="$1"
curl -skL -b "$J" -c "$J" "$URL/administrator/index.php?option=com_dharmashop&task=category.add&extension=$EXT" -o "$TMPDIR/cadd.html"
TOKEN=$(grep -oE 'name="[0-9a-f]{32}" value="1"' "$TMPDIR/cadd.html" | head -1 | sed -E 's/name="([0-9a-f]{32})".*/\1/')
curl -sk -b "$J" -c "$J" -o /dev/null -w "save http=%{http_code} -> %{redirect_url}\n" -X POST "$URL/administrator/index.php?option=com_dharmashop&layout=edit&id=0&extension=$EXT" \
 --data-urlencode "jform[title]=$2" --data-urlencode "jform[extension]=$EXT" --data-urlencode "jform[parent_id]=${3:-1}" --data-urlencode "jform[published]=1" --data-urlencode "jform[access]=1" \
 --data-urlencode "jform[description]=" --data-urlencode "jform[params][show_in_list]=1" --data-urlencode "jform[params][list_limit]=0" \
 ${4:+--data-urlencode "jform[params][badge_color]=$4"} --data-urlencode "jform[id]=0" --data-urlencode "task=category.apply" --data-urlencode "$TOKEN=1"
