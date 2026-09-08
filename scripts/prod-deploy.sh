#!/bin/sh
set -eu

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    echo "Production .env is missing." >&2
    exit 1
fi

compose() {
    docker compose \
        --env-file .env \
        -f compose.prod.yaml \
        "$@"
}

echo "== Validate Compose configuration =="
compose config >/dev/null

echo "== Build immutable production images =="
compose build --pull

echo "== Start infrastructure =="
compose up -d database mercure

echo "== Wait for PostgreSQL =="
until compose exec -T database \
    sh -lc \
    'pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"' \
    >/dev/null 2>&1
do
    sleep 1
done

echo "== Backup database =="
mkdir -p backups

backup_file="backups/homeen-$(date +%Y%m%d-%H%M%S).sql.gz"

compose exec -T database \
    sh -lc \
    'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip > "$backup_file"

test -s "$backup_file"

echo "Backup created: $backup_file"

echo "== Run Doctrine migrations =="
compose run --rm php \
    php bin/console \
    doctrine:migrations:migrate \
    --no-interaction

echo "== Warm Symfony production cache =="
compose run --rm php \
    php bin/console \
    cache:clear \
    --env=prod \
    --no-debug

echo "== Start application =="
compose up -d \
    --remove-orphans

echo "== Production services =="
compose ps

echo "Deployment completed."
