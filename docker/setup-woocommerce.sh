#!/bin/bash

# WooCommerce Local Store Setup Script
# This script helps you set up a WooCommerce store in your Docker environment

set -e

# Use full Docker path for macOS
DOCKER_CMD="/Applications/Docker.app/Contents/Resources/bin/docker"

echo "🚀 Setting up WooCommerce store locally..."
echo ""

# Check if containers are running
echo "📦 Checking Docker containers..."
$DOCKER_CMD compose ps | grep -q wp_app || { echo "❌ WordPress container not running. Start with: docker compose up -d"; exit 1; }

# Check if WordPress is installed
echo "🔍 Checking WordPress installation..."
if $DOCKER_CMD compose exec -T wpcli wp core is-installed --allow-root 2>/dev/null; then
    echo "✅ WordPress is already installed"
else
    echo "⚠️  WordPress not installed yet"
    echo "   Please visit http://localhost:8080 to complete WordPress installation first"
    echo "   Or run: $DOCKER_CMD compose exec wpcli wp core install --url=http://localhost:8080 --title='My Store' --admin_user=admin --admin_password=admin123 --admin_email=admin@example.com --allow-root"
    exit 1
fi

# Install WooCommerce
echo ""
echo "🛒 Installing WooCommerce..."
$DOCKER_CMD compose exec -T wpcli wp plugin install woocommerce --activate --allow-root

# Create WooCommerce pages
echo ""
echo "📄 Creating WooCommerce pages..."
$DOCKER_CMD compose exec -T wpcli wp wc tool run install_pages --allow-root 2>/dev/null || echo "⚠️  Pages may already exist"

# Install default tax rates
echo ""
echo "💰 Installing default tax rates..."
$DOCKER_CMD compose exec -T wpcli wp wc tool run install_default_tax_rates --allow-root 2>/dev/null || echo "⚠️  Tax rates may already exist"

# Create sample products
echo ""
echo "📦 Creating sample products..."

# Product 1
$DOCKER_CMD compose exec -T wpcli wp wc product create \
  --name="Sample T-Shirt" \
  --type=simple \
  --regular_price=19.99 \
  --description="A comfortable cotton t-shirt perfect for everyday wear." \
  --short_description="Comfortable cotton t-shirt" \
  --status=publish \
  --allow-root > /dev/null 2>&1 || echo "⚠️  Product may already exist"

# Product 2
$DOCKER_CMD compose exec -T wpcli wp wc product create \
  --name="Sample Hoodie" \
  --type=simple \
  --regular_price=49.99 \
  --description="A warm and cozy hoodie for cooler days." \
  --short_description="Warm and cozy hoodie" \
  --status=publish \
  --allow-root > /dev/null 2>&1 || echo "⚠️  Product may already exist"

# Product 3
$DOCKER_CMD compose exec -T wpcli wp wc product create \
  --name="Sample Cap" \
  --type=simple \
  --regular_price=14.99 \
  --description="A stylish cap to complete your look." \
  --short_description="Stylish cap" \
  --status=publish \
  --allow-root > /dev/null 2>&1 || echo "⚠️  Product may already exist"

echo ""
echo "✅ WooCommerce setup complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Visit http://localhost:8080/wp-admin"
echo "   2. Complete the WooCommerce setup wizard (if prompted)"
echo "   3. Go to Products → All Products to see your sample products"
echo "   4. Visit http://localhost:8080/shop to see your store"
echo ""
echo "🔌 To activate your Dataviz AI plugin:"
echo "   1. Go to Plugins → Installed Plugins"
echo "   2. Activate 'Dataviz AI for WooCommerce'"
echo "   3. Configure API settings in Dataviz AI menu"
echo ""

