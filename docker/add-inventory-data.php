<?php
/**
 * Script to add inventory/stock data to existing products
 * Run: docker compose exec wordpress php /var/www/html/add-inventory-data.php
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
    die( "❌ Could not find wp-load.php\n" );
}

if ( ! class_exists( 'WooCommerce' ) ) {
    die( "❌ WooCommerce is not active!\n" );
}

echo "📦 Adding inventory data to products...\n\n";

$products = wc_get_products( array(
    'limit'  => 100,
    'status' => 'publish',
) );

if ( empty( $products ) ) {
    echo "⚠️  No products found. Create products first.\n";
    exit;
}

$updated = 0;
$stock_levels = array(
    array( 'min' => 0, 'max' => 0, 'label' => 'Out of Stock' ),
    array( 'min' => 1, 'max' => 9, 'label' => 'Low Stock' ),
    array( 'min' => 10, 'max' => 49, 'label' => 'Medium Stock' ),
    array( 'min' => 50, 'max' => 150, 'label' => 'High Stock' ),
);

foreach ( $products as $index => $product ) {
    // Enable stock management
    $product->set_manage_stock( true );
    
    // Assign stock quantities to create a good distribution for charts
    // Mix of out of stock, low stock, medium stock, and high stock
    $level_index = $index % count( $stock_levels );
    $level = $stock_levels[ $level_index ];
    $stock_qty = rand( $level['min'], $level['max'] );
    
    $product->set_stock_quantity( $stock_qty );
    
    // Set stock status based on quantity
    if ( $stock_qty === 0 ) {
        $product->set_stock_status( 'outofstock' );
    } else {
        $product->set_stock_status( 'instock' );
    }
    
    $product_id = $product->save();
    
    if ( $product_id ) {
        $updated++;
        echo sprintf( "  ✓ %s: Stock=%d (%s)\n", 
            $product->get_name(), 
            $stock_qty,
            $level['label']
        );
    }
}

echo "\n✅ Updated $updated products with inventory data!\n";
echo "📊 Distribution:\n";
foreach ( $stock_levels as $level ) {
    $count = 0;
    foreach ( $products as $index => $product ) {
        $level_index = $index % count( $stock_levels );
        if ( $level_index === array_search( $level, $stock_levels ) ) {
            $count++;
        }
    }
    echo sprintf( "  • %s: ~%d products\n", $level['label'], $count );
}

echo "\n💡 Now you can test the inventory pie chart feature!\n";

