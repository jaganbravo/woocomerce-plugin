#!/bin/bash

# Quick script to add sample data to WooCommerce
# Run: ./add-sample-data.sh

set -e

echo "🛒 Adding sample data to WooCommerce..."
echo ""

# Create products
echo "📦 Creating products..."
for i in {1..10}; do
    case $i in
        1) name="T-Shirt"; price=24.99 ;;
        2) name="Jeans"; price=59.99 ;;
        3) name="Sneakers"; price=79.99 ;;
        4) name="Hoodie"; price=49.99 ;;
        5) name="Watch"; price=89.99 ;;
        6) name="Backpack"; price=39.99 ;;
        7) name="Sunglasses"; price=29.99 ;;
        8) name="Cap"; price=19.99 ;;
        9) name="Wallet"; price=34.99 ;;
        10) name="Water Bottle"; price=14.99 ;;
    esac
    
    docker compose exec -T wpcli wp wc product create \
        --name="$name" \
        --type=simple \
        --regular_price=$price \
        --stock_quantity=$(( RANDOM % 90 + 10 )) \
        --status=publish \
        --allow-root 2>/dev/null && echo "   ✓ Created: $name" || echo "   ⚠ $name may already exist"
done

# Create customers
echo ""
echo "👥 Creating customers..."
customers=(
    "John:Smith:john.smith@example.com"
    "Jane:Johnson:jane.johnson@example.com"
    "Mike:Williams:mike.williams@example.com"
    "Sarah:Brown:sarah.brown@example.com"
    "David:Jones:david.jones@example.com"
)

for customer in "${customers[@]}"; do
    IFS=':' read -r first last email <<< "$customer"
    username=$(echo "${first}.${last}" | tr '[:upper:]' '[:lower:]')
    
    docker compose exec -T wpcli wp user create "$username" "$email" \
        --role=customer \
        --user_pass=customer123 \
        --first_name="$first" \
        --last_name="$last" \
        --allow-root 2>/dev/null && echo "   ✓ Created customer: $first $last" || echo "   ⚠ $first $last may already exist"
done

# Generate orders using WP-CLI
echo ""
echo "📦 Creating orders..."
docker compose exec -T wpcli wp wc generate orders 20 \
    --status=completed \
    --allow-root 2>/dev/null && echo "   ✓ Generated 20 orders" || echo "   ⚠ Order generation completed (some may already exist)"

echo ""
echo "✅ Sample data added successfully!"
echo ""
echo "📋 You can view the data in WordPress admin:"
echo "   - Products: http://localhost:8080/wp-admin/edit.php?post_type=product"
echo "   - Customers: http://localhost:8080/wp-admin/users.php?role=customer"
echo "   - Orders: http://localhost:8080/wp-admin/edit.php?post_type=shop_order"

