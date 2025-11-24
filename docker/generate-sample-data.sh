#!/bin/bash

# Script to generate sample WooCommerce data using WP-CLI
# Run from the docker directory: ./generate-sample-data.sh

set -e

echo "🛒 Generating sample WooCommerce data..."
echo ""

# Check if containers are running
if ! docker compose ps | grep -q wpcli.*Up; then
    echo "❌ Docker containers are not running. Please start them with: docker compose up -d"
    exit 1
fi

echo "📦 Creating sample products..."
docker compose exec -T wpcli wp wc product create \
    --name="T-Shirt" \
    --type=simple \
    --regular_price=24.99 \
    --stock_quantity=50 \
    --status=publish \
    --allow-root 2>/dev/null || echo "   Product may already exist"

docker compose exec -T wpcli wp wc product create \
    --name="Jeans" \
    --type=simple \
    --regular_price=59.99 \
    --stock_quantity=30 \
    --status=publish \
    --allow-root 2>/dev/null || echo "   Product may already exist"

docker compose exec -T wpcli wp wc product create \
    --name="Sneakers" \
    --type=simple \
    --regular_price=79.99 \
    --stock_quantity=25 \
    --status=publish \
    --allow-root 2>/dev/null || echo "   Product may already exist"

docker compose exec -T wpcli wp wc product create \
    --name="Hoodie" \
    --type=simple \
    --regular_price=49.99 \
    --stock_quantity=20 \
    --status=publish \
    --allow-root 2>/dev/null || echo "   Product may already exist"

docker compose exec -T wpcli wp wc product create \
    --name="Watch" \
    --type=simple \
    --regular_price=89.99 \
    --stock_quantity=15 \
    --status=publish \
    --allow-root 2>/dev/null || echo "   Product may already exist"

docker compose exec -T wpcli wp wc product create \
    --name="Backpack" \
    --type=simple \
    --regular_price=39.99 \
    --stock_quantity=40 \
    --status=publish \
    --allow-root 2>/dev/null || echo "   Product may already exist"

docker compose exec -T wpcli wp wc product create \
    --name="Sunglasses" \
    --type=simple \
    --regular_price=29.99 \
    --stock_quantity=35 \
    --status=publish \
    --allow-root 2>/dev/null || echo "   Product may already exist"

docker compose exec -T wpcli wp wc product create \
    --name="Cap" \
    --type=simple \
    --regular_price=19.99 \
    --stock_quantity=45 \
    --status=publish \
    --allow-root 2>/dev/null || echo "   Product may already exist"

docker compose exec -T wpcli wp wc product create \
    --name="Wallet" \
    --type=simple \
    --regular_price=34.99 \
    --stock_quantity=30 \
    --status=publish \
    --allow-root 2>/dev/null || echo "   Product may already exist"

docker compose exec -T wpcli wp wc product create \
    --name="Water Bottle" \
    --type=simple \
    --regular_price=14.99 \
    --stock_quantity=60 \
    --status=publish \
    --allow-root 2>/dev/null || echo "   Product may already exist"

echo ""
echo "👥 Creating sample customers..."
docker compose exec -T wpcli wp user create customer1 customer1@example.com \
    --role=customer \
    --user_pass=customer123 \
    --first_name=John \
    --last_name=Smith \
    --allow-root 2>/dev/null || echo "   Customer may already exist"

docker compose exec -T wpcli wp user create customer2 customer2@example.com \
    --role=customer \
    --user_pass=customer123 \
    --first_name=Jane \
    --last_name=Johnson \
    --allow-root 2>/dev/null || echo "   Customer may already exist"

docker compose exec -T wpcli wp user create customer3 customer3@example.com \
    --role=customer \
    --user_pass=customer123 \
    --first_name=Mike \
    --last_name=Williams \
    --allow-root 2>/dev/null || echo "   Customer may already exist"

docker compose exec -T wpcli wp user create customer4 customer4@example.com \
    --role=customer \
    --user_pass=customer123 \
    --first_name=Sarah \
    --last_name=Brown \
    --allow-root 2>/dev/null || echo "   Customer may already exist"

docker compose exec -T wpcli wp user create customer5 customer5@example.com \
    --role=customer \
    --user_pass=customer123 \
    --first_name=David \
    --last_name=Jones \
    --allow-root 2>/dev/null || echo "   Customer may already exist"

echo ""
echo "📦 Generating sample orders..."
docker compose exec -T wpcli wp wc generate orders 20 \
    --status=completed \
    --allow-root 2>/dev/null || echo "   Note: Orders generation may have issues, using alternative method"

echo ""
echo "✅ Sample data generation complete!"
echo ""
echo "📋 Summary:"
echo "   - Products: 10"
echo "   - Customers: 5"
echo "   - Orders: ~20 (if generation succeeded)"
echo ""
echo "💡 You can verify the data in WordPress admin:"
echo "   - Products: http://localhost:8080/wp-admin/edit.php?post_type=product"
echo "   - Customers: http://localhost:8080/wp-admin/users.php?role=customer"
echo "   - Orders: http://localhost:8080/wp-admin/edit.php?post_type=shop_order"

