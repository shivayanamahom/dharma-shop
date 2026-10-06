#!/usr/bin/env bash
# Dev-only: логин в админку тестовой копии, куки в $COOKIES. Логин и пароль — из окружения:
#   DHARMA_USER=... DHARMA_PASS=... dev/web-login.sh
set -euo pipefail
URL="${DHARMA_URL:-https://test.test}"
J="${COOKIES:-${TMPDIR:-/tmp}/dharma-cookies.txt}"
rm -f "$J"
html=$(curl -sk -c "$J" "$URL/administrator/index.php")
token=$(echo "$html" | grep -oE 'name="[0-9a-f]{32}" value="1"' | head -1 | sed -E 's/name="([0-9a-f]{32})".*/\1/')
curl -sk -b "$J" -c "$J" -o /dev/null -w "login http=%{http_code}\n" -X POST "$URL/administrator/index.php" \
  --data-urlencode "username=${DHARMA_USER:?set DHARMA_USER}" --data-urlencode "passwd=${DHARMA_PASS:?set DHARMA_PASS}" \
  --data-urlencode "option=com_login" --data-urlencode "task=login" --data-urlencode "return=" --data-urlencode "$token=1"
echo "cookies: $J"
