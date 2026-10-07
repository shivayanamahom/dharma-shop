#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
if [[ -f dev/.env ]]; then
  set -a
  . dev/.env
  set +a
fi
export DHARMA_URL="${DHARMA_URL:-https://test.test}"
python3 dev/smoke/site.py
