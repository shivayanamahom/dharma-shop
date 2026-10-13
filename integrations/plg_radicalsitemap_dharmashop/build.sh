#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
out_dir="${1:-$PWD/dist}"
mkdir -p "$out_dir"
zip_path="$out_dir/plg_radicalsitemap_dharmashop-0.1.0.zip"
rm -f "$zip_path"
python3 build.py "$zip_path"
printf 'Готово: %s\n' "$zip_path"
