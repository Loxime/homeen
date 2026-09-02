#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ ! -f .gitignore ]]; then
  echo "ERROR: .gitignore must exist before any local secret file is created." >&2
  exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "ERROR: Docker is not installed on LOCAL." >&2
  exit 1
fi
if ! docker compose version >/dev/null 2>&1; then
  echo "ERROR: Docker Compose v2 is not available on LOCAL." >&2
  exit 1
fi
if ! command -v curl >/dev/null 2>&1; then
  echo "ERROR: curl is required for LOCAL health checks." >&2
  exit 1
fi
if ! command -v openssl >/dev/null 2>&1; then
  echo "ERROR: openssl is required to generate local secrets." >&2
  exit 1
fi

GENERATED_ACCESS_KEY=""
if [[ ! -f .env ]]; then
  APP_SECRET="$(openssl rand -hex 32)"
  POSTGRES_PASSWORD="$(openssl rand -hex 24)"
  GENERATED_ACCESS_KEY="${HOMEEN_ACCESS_KEY:-$(openssl rand -hex 16)}"

  if [[ ! "$GENERATED_ACCESS_KEY" =~ ^[A-Za-z0-9._-]{12,}$ ]]; then
    echo "ERROR: HOMEEN_ACCESS_KEY may contain only letters, numbers, '.', '_' and '-' and must be at least 12 characters." >&2
    exit 1
  fi

  cat > .env <<ENV
COMPOSE_PROJECT_NAME=homeen
LOCAL_UID=$(id -u)
LOCAL_GID=$(id -g)
APP_ENV=dev
APP_SECRET=${APP_SECRET}
APP_TIMEZONE=Europe/Paris
ACCESS_KEY=${GENERATED_ACCESS_KEY}
POSTGRES_DB=homeen
POSTGRES_USER=homeen
POSTGRES_PASSWORD=${POSTGRES_PASSWORD}
ENV
  chmod 600 .env
fi

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  if ! git check-ignore -q .env; then
    echo "ERROR: .env is not ignored by Git. Refusing to continue." >&2
    exit 1
  fi
fi

docker compose config >/dev/null
docker compose up -d --build

healthy=0
for _ in $(seq 1 90); do
  if curl -fsS http://localhost:8080/api/health >/dev/null 2>&1 \
     && curl -fsS http://localhost:8080/ >/dev/null 2>&1; then
    healthy=1
    break
  fi
  sleep 2
done

if [[ "$healthy" -ne 1 ]]; then
  echo "ERROR: Homeen did not become healthy. Recent logs:" >&2
  docker compose logs --tail=120 >&2
  exit 1
fi

make quality

echo
echo "Homeen LOCAL is ready: http://localhost:8080"
if [[ -n "$GENERATED_ACCESS_KEY" ]]; then
  echo "Generated ACCESS_KEY: $GENERATED_ACCESS_KEY"
else
  echo "ACCESS_KEY: existing value kept from .env"
fi

docker compose ps
