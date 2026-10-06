#!/usr/bin/env bash
# Dev-only: SQL к базе тестовой копии. Параметры берутся из configuration.php сайта.
# dev/db.sh "select count(*) from PFX_dharmashop_products"   (PFX_ заменяется префиксом таблиц)
set -euo pipefail
SITE="${DHARMA_SITE:-/Volumes/Work/Server/www/test}"
read -r HOST USER PASS NAME PREFIX < <(php -r '
  $c = file_get_contents($argv[1] . "/configuration.php");
  foreach (["host","user","password","db","dbprefix"] as $k) { preg_match("/public \\$" . $k . " = \x27([^\x27]*)\x27/", $c, $m); echo ($m[1] ?? ""), " "; } echo "\n";
' "$SITE")
mysql --default-character-set=utf8mb4 -h"$HOST" -u"$USER" -p"$PASS" "$NAME" -N -e "${1//PFX_/$PREFIX}" 2>/dev/null
