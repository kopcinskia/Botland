#!/usr/bin/env bash
set -euo pipefail

CONTAINER="botland_prestashop"
TIMEOUT=300  # max seconds to wait for PS

echo "==> Starting containers..."
docker compose up -d

echo "==> Waiting for PrestaShop to be ready (up to ${TIMEOUT}s)..."
elapsed=0
until docker exec botland_mysql mysql -uprestashop -pprestashop prestashop \
        -e "SELECT COUNT(*) FROM ps_shop" 2>/dev/null | grep -q "^[1-9]"; do
    if [ "$elapsed" -ge "$TIMEOUT" ]; then
        echo "ERROR: PrestaShop did not finish installing within ${TIMEOUT}s."
        echo "Check logs: docker compose logs -f prestashop"
        exit 1
    fi
    sleep 5
    elapsed=$((elapsed + 5))
    echo "    ...still waiting (${elapsed}s)"
done

echo "==> Warming up Symfony cache..."
docker exec "$CONTAINER" chmod -R 777 /var/www/html/var/cache
docker exec "$CONTAINER" su www-data -s /bin/bash -c \
    'php /var/www/html/bin/console cache:warmup --env=prod'

echo "==> Installing currency_rate module..."
docker exec "$CONTAINER" bash -c \
    "php bin/console prestashop:module install currency_rate"

echo ""
echo "Done. Open http://localhost:${PS_PORT:-8080}"
