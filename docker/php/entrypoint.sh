#!/bin/sh
set -e

# Make Laravel runtime directories writable regardless of how the bind mount
# was created (Windows/9p mounts report root ownership).
if [ -d /var/www/html/storage ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
    chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
fi

exec "$@"
