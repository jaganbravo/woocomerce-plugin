#!/bin/bash
# Script to add missing test data for comprehensive testing

cd "$(dirname "$0")"

echo "📦 Adding missing test data..."
echo ""

# Add more customers
echo "👥 Creating customers..."
for i in {2..5}; do
    docker-compose exec -T wordpress wp user create "customer${i}" "customer${i}@example.com" \
        --role=customer \
        --user_pass=customer123 \
        --first_name="Customer${i}" \
        --last_name="Test" \
        --allow-root 2>/dev/null && echo "   ✓ Created customer${i}" || echo "   ⊙ customer${i} may already exist"
done

echo ""

# Create coupons
echo "🎫 Creating coupons..."
docker-compose exec -T wordpress wp wc coupon create \
    --code=TEST10 \
    --discount_type=percent \
    --amount=10 \
    --status=publish \
    --allow-root 2>/dev/null && echo "   ✓ Created coupon TEST10 (10% off)" || echo "   ⊙ TEST10 may already exist"

docker-compose exec -T wordpress wp wc coupon create \
    --code=FLAT20 \
    --discount_type=fixed_cart \
    --amount=20 \
    --status=publish \
    --allow-root 2>/dev/null && echo "   ✓ Created coupon FLAT20 ($20 off)" || echo "   ⊙ FLAT20 may already exist"

docker-compose exec -T wordpress wp wc coupon create \
    --code=SUMMER25 \
    --discount_type=percent \
    --amount=25 \
    --status=publish \
    --allow-root 2>/dev/null && echo "   ✓ Created coupon SUMMER25 (25% off)" || echo "   ⊙ SUMMER25 may already exist"

echo ""

# Create product categories
echo "📁 Creating product categories..."
docker-compose exec -T wordpress wp term create product_cat "Electronics" --allow-root 2>/dev/null && echo "   ✓ Created category: Electronics" || echo "   ⊙ Electronics may already exist"

docker-compose exec -T wordpress wp term create product_cat "Clothing" --allow-root 2>/dev/null && echo "   ✓ Created category: Clothing" || echo "   ⊙ Clothing may already exist"

docker-compose exec -T wordpress wp term create product_cat "Accessories" --allow-root 2>/dev/null && echo "   ✓ Created category: Accessories" || echo "   ⊙ Accessories may already exist"

docker-compose exec -T wordpress wp term create product_cat "Sports" --allow-root 2>/dev/null && echo "   ✓ Created category: Sports" || echo "   ⊙ Sports may already exist"

echo ""

# Create product tags
echo "🏷️  Creating product tags..."
docker-compose exec -T wordpress wp term create product_tag "popular" --allow-root 2>/dev/null && echo "   ✓ Created tag: popular" || echo "   ⊙ popular may already exist"

docker-compose exec -T wordpress wp term create product_tag "sale" --allow-root 2>/dev/null && echo "   ✓ Created tag: sale" || echo "   ⊙ sale may already exist"

docker-compose exec -T wordpress wp term create product_tag "new" --allow-root 2>/dev/null && echo "   ✓ Created tag: new" || echo "   ⊙ new may already exist"

echo ""
echo "✅ Done! Check data coverage:"
echo "   docker-compose exec wordpress php /var/www/html/check-test-data-coverage.php"

