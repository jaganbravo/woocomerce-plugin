#!/bin/bash

# Script to update Docker containers to PHP 8.3

echo "🔄 Updating Docker containers to PHP 8.3..."

cd "$(dirname "$0")"

# Pull the latest PHP 8.3 images
echo "📥 Pulling PHP 8.3 images..."
docker-compose pull wordpress wpcli

# Stop existing containers
echo "🛑 Stopping containers..."
docker-compose down

# Start containers with new PHP 8.3 images
echo "🚀 Starting containers with PHP 8.3..."
docker-compose up -d

# Wait a moment for containers to start
sleep 3

# Check PHP version
echo ""
echo "✅ Checking PHP version..."
docker exec wp_app php -v

echo ""
echo "🎉 Done! Your WordPress is now running PHP 8.3"
echo "Access your site at: http://localhost:8080"

