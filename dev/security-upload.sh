#!/usr/bin/env bash
# Dev-only: проверка защиты загрузчика изображений на тестовом сайте (docs/SECURITY.md).
# Нужны вход: set -a; . dev/.env; set +a; export COOKIES=$TMPDIR/dharma-cookies.txt; dev/web-login.sh
# Ожидание: «OK» у каждой проверки. Загруженные в ходе проверки файлы удаляются в конце.
set -uo pipefail
BASE="${DHARMA_URL:-https://test.test}/administrator/index.php"
UP="$BASE?option=com_dharmashop&task=media.upload&format=json"
J="${COOKIES:?export COOKIES}"
D="$(mktemp -d)"; trap 'rm -rf "$D"' EXIT
curl -sk -b "$J" -L "$BASE?option=com_dharmashop&view=categories&extension=com_dharmashop" -o "$D/p.html"
tok=$(grep -oE 'name="[0-9a-f]{32}" value="1"' "$D/p.html" | head -1 | sed -E 's/name="([0-9a-f]{32})".*/\1/')
[ -n "$tok" ] || { echo "нет токена формы (вход выполнен?)"; exit 1; }
python3 - "$D" <<'PY'
import struct,zlib,sys
def ch(t,d): c=struct.pack('>I',len(d))+t+d; return c+struct.pack('>I',zlib.crc32(t+d)&0xffffffff)
png=b'\x89PNG\r\n\x1a\n'+ch(b'IHDR',struct.pack('>IIBBBBB',1,1,8,2,0,0,0))+ch(b'IDAT',zlib.compress(b'\x00\xff\x00\x00'))+ch(b'IEND',b'')
d=sys.argv[1]
open(d+'/ok.png','wb').write(png)
open(d+'/poly.png','wb').write(png+b'\n<?php system($_GET["c"]); ?>')
open(d+'/shell.png','wb').write(b'<?php echo 1;')
open(d+'/shell.php','wb').write(b'<?php echo 1;')
PY
res() { python3 -c "import sys,json
try: d=json.load(sys.stdin)
except Exception: print('HTTP/не JSON'); raise SystemExit
print('ACCEPT '+d['data']['path'] if d.get('success') else 'REJECT')"; }
check() { local name="$1" want="$2" got="$3"; if [[ "$got" == $want* ]]; then echo "OK    $name ($got)"; else echo "FAIL  $name: ждали $want, получили $got"; fi; }
up() { curl -sk -b "$J" -F "file=@$D/$1;filename=$2" -F "folder=${3:-categories}" -F "$tok=1" "$UP" | res; }
check "без токена"            REJECT "$(curl -sk -b "$J" -F "file=@$D/ok.png" -F folder=categories "$UP" | res)"
check "токен в адресе (GET)"  REJECT "$(curl -sk -b "$J" -F "file=@$D/ok.png" "$UP&$tok=1" | res)"
check "чужой токен"           REJECT "$(curl -sk -b "$J" -F "file=@$D/ok.png" -F "$(printf 'a%.0s' {1..32})=1" "$UP" | res)"
check "без входа"             REJECT "$(curl -sk -F "file=@$D/ok.png" -F "$tok=1" "$UP" | res)"
check "GET-запрос"            REJECT "$(curl -sk -b "$J" "$UP&$tok=1" | res)"
check "текст PHP как .png"    REJECT "$(up shell.png shell.png)"
check "расширение .php"       REJECT "$(up shell.php shell.php)"
check "PNG с хвостом <?php"   REJECT "$(up poly.png poly.png)"
r="$(up ok.png 'мой файл (1).PNG')";  check "кириллица и пробелы в имени" ACCEPT "$r"; paths="${r#ACCEPT }"
r="$(up ok.png '../../evil.png')";    check "обход пути в имени"  ACCEPT "$r"; paths="$paths ${r#ACCEPT }"
r="$(up ok.png ok.png '../../../tmp')"; check "обход пути в папке (→ common)" ACCEPT "$r"; case "$r" in *"/common/"*) echo "OK    папка свелась к common";; *) echo "FAIL  папка: $r";; esac; paths="$paths ${r#ACCEPT }"
ROOT="${DHARMA_SITE:-/Volumes/Work/Server/www/test}"
for p in $paths; do rm -f "$ROOT/${p//\\\//}" "$ROOT/$p"; done
echo "готово (тестовые файлы удалены)"
