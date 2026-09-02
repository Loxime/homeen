#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ -f .env ]] && ! git check-ignore -q .env; then
  echo "ERROR: .env is not ignored by Git." >&2
  exit 1
fi

if git ls-files --error-unmatch .env >/dev/null 2>&1; then
  echo "ERROR: .env is tracked by Git." >&2
  exit 1
fi

echo "Security check OK: local .env is not tracked."
