#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
if [[ -f dev/.env ]]; then
  set -a
  . dev/.env
  set +a
fi
export COOKIES="${COOKIES:-${TMPDIR:-/tmp}/dharma-seed-100.cookies}"
dev/web-login.sh
python3 dev/seed/seed.py seed
