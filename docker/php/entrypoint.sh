#!/bin/sh
set -eu

cd /app

if [ ! -f composer.json ]; then
  echo "backend/composer.json is missing" >&2
  exit 1
fi

composer install --no-interaction --prefer-dist

for generated_file in composer.lock symfony.lock; do
  if [ -f "$generated_file" ]; then
    chown "${LOCAL_UID:-1000}:${LOCAL_GID:-1000}" "$generated_file" 2>/dev/null || true
  fi
done

if [ -f bin/console ]; then
  php bin/console doctrine:migrations:migrate --no-interaction
fi

exec "$@"
