#!/bin/bash
# Update WordPress using WP-CLI

echo "Updating WordPress core..."

# Check if wp_cli container is running
if docker ps | grep -q wp_cli; then
    echo "Using wp_cli container to update WordPress..."
    docker exec wp_cli wp core update --allow-root
    docker exec wp_cli wp core update-db --allow-root
    echo "WordPress update complete!"
else
    echo "wp_cli container not running. Starting it..."
    docker-compose up -d wpcli
    sleep 3
    docker exec wp_cli wp core update --allow-root
    docker exec wp_cli wp core update-db --allow-root
    echo "WordPress update complete!"
fi
