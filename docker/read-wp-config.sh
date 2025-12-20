#!/bin/bash
# Script to read WordPress options from database

cd "$(dirname "$0")"

# Try to read via WP-CLI in Docker
echo "Attempting to read via WP-CLI..."
docker compose exec -T wpcli wp option get dataviz_ai_wc_settings --format=json 2>/dev/null

if [ $? -ne 0 ]; then
    echo "WP-CLI failed. Trying direct database access..."
    # Try MySQL client
    docker compose exec -T db mysql -u wp -pwppass wordpress -e "SELECT option_value FROM wp_options WHERE option_name = 'dataviz_ai_wc_settings';" 2>/dev/null
fi

