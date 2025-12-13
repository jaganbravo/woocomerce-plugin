<?php
/**
 * Quick script to check inventory data in WooCommerce
 * Run: docker compose exec wordpress php /var/www/html/wp-content/plugins/../check-inventory.php
 */

// Try different possible paths
$wp_load_paths = array(
    __DIR__ . '/wordpress/wp-load.php',
    __DIR__ . '/wp-load.php',
    '/var/www/html/wp-load.php',
);

$wp_loaded = false;
foreach ( $wp_load_paths as $path ) {
    if ( file_exists( $path ) ) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if ( ! $wp_loaded ) {
    die( "❌ Could not find wp-load.php. Tried: " . implode( ', ', $wp_load_paths ) . "\n" );
}

if ( ! class_exists( 'WooCommerce' ) ) {
    die( "❌ WooCommerce is not active!\n" );
}

echo "📦 Checking inventory data...\n\n";

$products = wc_get_products( array(
    'limit'  => 100,
    'status' => 'publish',
) );

if ( empty( $products ) ) {
    echo "⚠️  No products found in the database.\n";
    echo "💡 Run: docker compose exec wordpress wp eval-file docker/create-sample-data.php --allow-root\n";
    exit;
}

$with_stock_management = 0;
$without_stock_management = 0;
$low_stock = 0;
$out_of_stock = 0;
$in_stock = 0;

echo "Found " . count( $products ) . " products:\n\n";

foreach ( $products as $product ) {
    $manage_stock = $product->get_manage_stock();
    $stock_qty = $product->get_stock_quantity();
    $stock_status = $product->get_stock_status();
    
    if ( $manage_stock ) {
        $with_stock_management++;
        if ( $stock_qty === null || $stock_qty === 0 ) {
            $out_of_stock++;
        } elseif ( $stock_qty < 10 ) {
            $low_stock++;
        } else {
            $in_stock++;
        }
        echo sprintf( "  ✓ %s: Stock=%d, Status=%s\n", 
            $product->get_name(), 
            $stock_qty !== null ? $stock_qty : 0,
            $stock_status 
        );
    } else {
        $without_stock_management++;
        echo sprintf( "  ⊙ %s: No stock management\n", $product->get_name() );
    }
}

echo "\n";
echo "📊 Summary:\n";
echo "  • Products with stock management: $with_stock_management\n";
echo "  • Products without stock management: $without_stock_management\n";
echo "  • In stock (>10): $in_stock\n";
echo "  • Low stock (<10): $low_stock\n";
echo "  • Out of stock (0): $out_of_stock\n";

if ( $with_stock_management === 0 ) {
    echo "\n⚠️  No products have stock management enabled!\n";
    echo "💡 You can enable stock management for products in WooCommerce admin.\n";
}

