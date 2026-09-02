#!/bin/sh
set -eu

cd /app

while [ ! -f /app/vendor/autoload.php ]; do
    sleep 2
done

while true; do
    php /app/bin/console app:trash:purge --no-interaction || true
    sleep 86400
done
