#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(
  cd "$(dirname "${BASH_SOURCE[0]}")/.."
  pwd
)"

cd "$ROOT_DIR"

if [[ ! -f .gitignore ]]; then
  echo \
    "ERROR: .gitignore must exist before any local secret file is created." \
    >&2

  exit 1
fi

for command in docker curl openssl; do
  if ! command -v "$command" >/dev/null 2>&1; then
    echo \
      "ERROR: $command is required on LOCAL." \
      >&2

    exit 1
  fi
done

if ! docker compose version >/dev/null 2>&1; then
  echo \
    "ERROR: Docker Compose v2 is not available on LOCAL." \
    >&2

  exit 1
fi

if [[ ! -f .env ]]; then
  APP_SECRET="$(
    openssl rand -hex 32
  )"

  POSTGRES_PASSWORD="$(
    openssl rand -hex 24
  )"

  MERCURE_JWT_SECRET="$(
    openssl rand -hex 32
  )"

  cat > .env <<ENV
COMPOSE_PROJECT_NAME=homeen
LOCAL_UID=$(id -u)
LOCAL_GID=$(id -g)

APP_ENV=dev
APP_SECRET=${APP_SECRET}
APP_TIMEZONE=Europe/Paris
DEFAULT_URI=http://localhost:8080

POSTGRES_DB=homeen
POSTGRES_USER=homeen
POSTGRES_PASSWORD=${POSTGRES_PASSWORD}

HOMEEN_HTTP_PORT=8080

MERCURE_JWT_SECRET=${MERCURE_JWT_SECRET}
MERCURE_PUBLIC_URL=http://localhost:8080/.well-known/mercure
ENV

  chmod 600 .env
fi

if git rev-parse \
  --is-inside-work-tree \
  >/dev/null 2>&1
then
  if ! git check-ignore -q .env; then
    echo \
      "ERROR: .env is not ignored by Git." \
      >&2

    exit 1
  fi
fi

docker compose config >/dev/null

docker compose up \
  -d \
  --build

healthy=0

for _ in $(seq 1 90); do
  if \
    curl -fsS \
      http://localhost:8080/api/health \
      >/dev/null 2>&1 \
    && \
    curl -fsS \
      http://localhost:8080/ \
      >/dev/null 2>&1
  then
    healthy=1
    break
  fi

  sleep 2
done

if [[ "$healthy" -ne 1 ]]; then
  echo \
    "ERROR: Homeen did not become healthy. Recent logs:" \
    >&2

  docker compose logs \
    --tail=120 \
    >&2

  exit 1
fi

make quality

echo
echo \
  "Homeen LOCAL is ready: http://localhost:8080"

docker compose ps
