#!/usr/bin/env bash
# Démarre le proxy avec le venv local (évite ModuleNotFoundError avec le Python système).
set -euo pipefail
cd "$(dirname "$0")"
if [[ ! -d .venv ]]; then
  python3 -m venv .venv
  .venv/bin/pip install -r requirements.txt
fi
exec .venv/bin/uvicorn app:app --host 127.0.0.1 --port 8810 "$@"
