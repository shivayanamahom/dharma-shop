#!/usr/bin/env bash
# Dev-only: создаёт товар через форму админки по HTTP (как браузер). Нужен вход: dev/web-login.sh (куки в $COOKIES).
#   dev/save-product.sh "Название" <catid> <sku> <цена> [brand_id] [badge_id]
set -euo pipefail
URL="${DHARMA_URL:-https://test.test}"; J="${COOKIES:-${TMPDIR:-/tmp}/dharma-cookies.txt}"
curl -skL -b "$J" -c "$J" "$URL/administrator/index.php?option=com_dharmashop&task=product.add" -o "$TMPDIR/add.html"
TOKEN=$(grep -oE 'name="[0-9a-f]{32}" value="1"' "$TMPDIR/add.html" | head -1 | sed -E 's/name="([0-9a-f]{32})".*/\1/')
curl -sk -b "$J" -c "$J" -o /dev/null -w "save http=%{http_code} -> %{redirect_url}\n" -X POST "$URL/administrator/index.php?option=com_dharmashop&layout=edit&id=0" \
 --data-urlencode "jform[title]=$1" --data-urlencode "jform[catid]=$2" --data-urlencode "jform[state]=1" \
 --data-urlencode "jform[introtext]=Краткое описание" --data-urlencode "jform[fulltext]=<p>Полное описание</p>" \
 --data-urlencode "jform[sku]=$3" --data-urlencode "jform[price]=$4" --data-urlencode "jform[unit]=шт" \
 --data-urlencode "jform[qty_min]=1" --data-urlencode "jform[qty_step]=1" --data-urlencode "jform[stock_tracked]=1" --data-urlencode "jform[stock_qty]=7" --data-urlencode "jform[backorder]=0" \
 ${5:+--data-urlencode "jform[brand_id]=$5"} ${6:+--data-urlencode "jform[badge_ids][]=$6"} --data-urlencode "jform[extra_catids][]=${EXTRA:-12}" \
 --data-urlencode "jform[id]=0" --data-urlencode "task=product.apply" --data-urlencode "$TOKEN=1"
