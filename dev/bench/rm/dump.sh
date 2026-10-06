#!/usr/bin/env bash
# Dev-only: дамп/восстановление базы сайта (перед массовыми данными): dump.sh dump <файл.sql.gz> | dump.sh restore <файл.sql.gz>. Нужен DHARMA_SITE.
# Учтите: restore перезаписывает всю базу; для точечного возврата отдельных таблиц загрузите дамп во временную базу и перенесите нужные.
set -euo pipefail
SITE="${DHARMA_SITE:?DHARMA_SITE}"
read -r HOST USER PASS NAME < <(php -r '$c=file_get_contents($argv[1]."/configuration.php");foreach(["host","user","password","db"] as $k){preg_match("/public \\$".$k." = \x27([^\x27]*)\x27/",$c,$m);echo ($m[1]??"")," ";}echo "\n";' "$SITE")
if [ "${1:-dump}" = "dump" ]; then
  mysqldump -h"$HOST" -u"$USER" -p"$PASS" --single-transaction --routines "$NAME" 2>/dev/null | gzip > "$2"
else
  gzip -dc "$2" | mysql -h"$HOST" -u"$USER" -p"$PASS" "$NAME" 2>/dev/null
fi
