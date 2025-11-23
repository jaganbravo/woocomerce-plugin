#!/bin/bash

# Script to add MORE sample data to WooCommerce
# Run: ./add-more-data.sh

set -e

# Use WordPress container for WP-CLI since it has DB access
WP_CONTAINER="wordpress"

echo "🛒 Adding MORE sample data to WooCommerce..."
echo ""

# Additional products
echo "📦 Adding more products..."
additional_products=(
    "Running Shoes:99.99"
    "Leather Jacket:149.99"
    "Denim Shorts:34.99"
    "Baseball Cap:22.99"
    "Polo Shirt:39.99"
    "Cargo Pants:54.99"
    "Winter Coat:129.99"
    "Summer Dress:49.99"
    "Hiking Boots:119.99"
    "Bluetooth Headphones:89.99"
    "Smart Watch:199.99"
    "Gym Bag:44.99"
    "Yoga Mat:29.99"
    "Dumbbells Set:79.99"
    "Bicycle Helmet:59.99"
    "Tennis Racket:89.99"
    "Basketball:24.99"
    "Fitness Tracker:69.99"
    "Sports Bra:34.99"
    "Running Shorts:29.99"
)

created_products=0
for product in "${additional_products[@]}"; do
    IFS=':' read -r name price <<< "$product"
    
    # Check if product exists
    existing=$(docker compose exec -T wpcli wp wc product list --name="$name" --format=count --user=admin --allow-root 2>/dev/null || echo "0")
    
    if [ "$existing" = "0" ]; then
        docker compose exec -T wpcli wp wc product create \
            --name="$name" \
            --type=simple \
            --regular_price=$price \
            --stock_quantity=$(( RANDOM % 135 + 15 )) \
            --status=publish \
            --user=admin \
            --allow-root > /dev/null 2>&1 && echo "   ✓ Created: $name" && ((created_products++)) || echo "   ⚠ Failed: $name"
    else
        echo "   ⊙ Already exists: $name"
    fi
done

echo "   Created $created_products new products"
echo ""

# Additional customers
echo "👥 Adding more customers..."
additional_customers=(
    "Robert:Taylor:robert.taylor@example.com"
    "Lisa:Anderson:lisa.anderson@example.com"
    "James:Martinez:james.martinez@example.com"
    "Patricia:Wilson:patricia.wilson@example.com"
    "Michael:Moore:michael.moore@example.com"
    "Jennifer:Thomas:jennifer.thomas@example.com"
    "William:Jackson:william.jackson@example.com"
    "Linda:White:linda.white@example.com"
    "Richard:Harris:richard.harris@example.com"
    "Susan:Martin:susan.martin@example.com"
    "Joseph:Thompson:joseph.thompson@example.com"
    "Thomas:Martinez:thomas.martinez@example.com"
    "Sarah:Robinson:sarah.robinson@example.com"
    "Charles:Clark:charles.clark@example.com"
    "Daniel:Lewis:daniel.lewis@example.com"
)

created_customers=0
for customer in "${additional_customers[@]}"; do
    IFS=':' read -r first last email <<< "$customer"
    username=$(echo "${first}.${last}" | tr '[:upper:]' '[:lower:]')
    
    # Try to create customer
    result=$(docker compose exec -T wpcli wp user create "$username" "$email" --role=customer --user_pass=customer123 --first_name="$first" --last_name="$last" --allow-root --skip-email 2>&1)
    
    if echo "$result" | grep -q "Success:"; then
        echo "   ✓ Created: $first $last"
        ((created_customers++))
    elif echo "$result" | grep -q "already exists"; then
        echo "   ⊙ Already exists: $first $last"
    else
        echo "   ⚠ Failed: $first $last"
        # Show error for debugging (comment out in production)
        # echo "     Error: $result"
    fi
done

echo "   Created $created_customers new customers"
echo ""

# Generate more orders
echo "📦 Creating more orders..."
echo "   This may take a moment..."

# Try to create orders using WP-CLI
docker compose exec -T wpcli wp wc generate orders 50 \
    --status=completed,processing,pending \
    --user=admin \
    --allow-root 2>/dev/null && echo "   ✓ Generated 50 orders" || echo "   ⚠ Some orders may already exist"

echo ""
echo "✅ Additional sample data added!"
echo ""
echo "📊 Current totals:"
total_products=$(docker compose exec -T wpcli wp wc product list --format=count --user=admin --allow-root 2>/dev/null || echo "0")
total_customers=$(docker compose exec -T wpcli wp user list --role=customer --format=count --allow-root 2>/dev/null || echo "0")
total_orders=$(docker compose exec -T wpcli wp wc order list --format=count --user=admin --allow-root 2>/dev/null || echo "0")

echo "   - Products: $total_products"
echo "   - Customers: $total_customers"
echo "   - Orders: $total_orders"
echo ""
echo "💡 View in WordPress admin:"
echo "   - Products: http://localhost:8080/wp-admin/edit.php?post_type=product"
echo "   - Customers: http://localhost:8080/wp-admin/users.php?role=customer"
echo "   - Orders: http://localhost:8080/wp-admin/edit.php?post_type=shop_order"

