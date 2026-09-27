#!/bin/sh
set -eu

mkdir -p \
    /app/var/cache \
    /app/var/log \
    /app/var/sessions

chown -R www-data:www-data \
    /app/var

exec "$@"
