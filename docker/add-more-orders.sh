#!/bin/bash

# Script to add more sample orders to WooCommerce
# Run: ./add-more-orders.sh [number_of_orders]

set -e

NUM_ORDERS=${1:-50}
echo "🛒 Adding $NUM_ORDERS more sample orders to WooCommerce..."
echo ""

# Check if WooCommerce is active
echo "📦 Checking WooCommerce..."
wc_active=$(docker compose exec -T wpcli wp plugin is-active woocommerce --allow-root 2>/dev/null || echo "no")
if [ "$wc_active" != "active" ]; then
    echo "❌ WooCommerce is not active. Please activate WooCommerce first."
    exit 1
fi

# Get product count
product_count=$(docker compose exec -T wpcli wp wc product list --format=count --user=admin --allow-root 2>/dev/null || echo "0")
customer_count=$(docker compose exec -T wpcli wp user list --role=customer --format=count --allow-root 2>/dev/null || echo "0")

if [ "$product_count" = "0" ]; then
    echo "⚠️  No products found. Please add products first."
    exit 1
fi

if [ "$customer_count" = "0" ]; then
    echo "⚠️  No customers found. Creating a default customer first..."
    docker compose exec -T wpcli wp user create defaultcustomer defaultcustomer@example.com \
        --role=customer \
        --user_pass=customer123 \
        --first_name=Default \
        --last_name=Customer \
        --allow-root > /dev/null 2>&1 || echo "   Customer may already exist"
fi

echo "📊 Current data:"
echo "   - Products: $product_count"
echo "   - Customers: $customer_count"
echo ""

# Generate orders with various statuses
echo "📦 Creating $NUM_ORDERS orders..."
echo "   This may take a moment..."

# Create orders with different statuses
docker compose exec -T wpcli wp wc generate orders $NUM_ORDERS \
    --status=completed,processing,pending,on-hold \
    --user=admin \
    --allow-root 2>&1 | grep -E "(Created|Error|Success)" || echo "   Orders generation in progress..."

# Wait a moment for orders to be created
sleep 2

# Get final count
total_orders=$(docker compose exec -T wpcli wp wc order list --format=count --user=admin --allow-root 2>/dev/null || echo "0")

echo ""
echo "✅ Orders generation complete!"
echo ""
echo "📊 Summary:"
echo "   - Total orders now: $total_orders"
echo ""
echo "💡 View orders in WordPress admin:"
echo "   - Orders: http://localhost:8080/wp-admin/edit.php?post_type=shop_order"

