#!/usr/bin/env bash
# Dev-only: довести каталог RadicalMart до N товаров, пересобрать индекс штатными командами и замерить.
#   DHARMA_SITE=/Volumes/Work/Server/www/dharmatest DHARMA_URL=https://dharmatest.test dev/bench/rm/run-scale.sh 50000
set -euo pipefail
cd "$(dirname "$0")/../../.."
N="${1:?число товаров}"
SITE="${DHARMA_SITE:?DHARMA_SITE}"
php dev/bench/rm/clone.php "$N"
s=$(date +%s); (cd "$SITE" && php cli/joomla.php radicalmart:categories:items_resave -n >/dev/null 2>&1); echo "items_resave: $(( $(date +%s) - s )) с"
s=$(date +%s); (cd "$SITE" && php cli/joomla.php radicalmart:categories:totals -n >/dev/null 2>&1); echo "categories:totals: $(( $(date +%s) - s )) с"
php dev/bench/rm/measure.php
