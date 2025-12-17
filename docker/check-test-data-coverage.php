<?php
/**
 * Check if we have sufficient data for all test scenarios
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

if (!class_exists('WooCommerce')) {
    die("❌ WooCommerce is not active!\n");
}

echo "📊 Checking Data Coverage for Test Cases\n";
echo str_repeat("=", 60) . "\n\n";

$results = [];

// 1. Products with Inventory
$products = wc_get_products(array('limit' => 100, 'status' => 'publish'));
$with_inventory = 0;
$low_stock = 0;
$out_of_stock = 0;
foreach ($products as $p) {
    if ($p->get_manage_stock()) {
        $with_inventory++;
        $qty = $p->get_stock_quantity();
        if ($qty === 0) $out_of_stock++;
        elseif ($qty < 10) $low_stock++;
    }
}

$results['Products'] = [
    'total' => count($products),
    'with_inventory' => $with_inventory,
    'low_stock' => $low_stock,
    'out_of_stock' => $out_of_stock,
    'status' => count($products) > 0 ? '✅' : '❌',
    'needed' => count($products) > 0 ? '' : 'Need at least 10 products with inventory data'
];

// 2. Orders
$orders = wc_get_orders(array('limit' => 100));
$completed_orders = 0;
$pending_orders = 0;
$total_revenue = 0;
foreach ($orders as $o) {
    $status = $o->get_status();
    if ($status === 'completed') $completed_orders++;
    if ($status === 'pending') $pending_orders++;
    $total_revenue += $o->get_total();
}

$results['Orders'] = [
    'total' => count($orders),
    'completed' => $completed_orders,
    'pending' => $pending_orders,
    'revenue' => $total_revenue,
    'status' => count($orders) > 0 ? '✅' : '❌',
    'needed' => count($orders) > 0 ? '' : 'Need at least 10-20 orders for testing'
];

// 3. Customers
$customers = get_users(array('role' => 'customer'));
$results['Customers'] = [
    'total' => count($customers),
    'status' => count($customers) > 0 ? '✅' : '❌',
    'needed' => count($customers) > 0 ? '' : 'Need at least 5 customers'
];

// 4. Categories
$categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
$results['Categories'] = [
    'total' => is_array($categories) ? count($categories) : 0,
    'status' => (is_array($categories) && count($categories) > 0) ? '✅' : '❌',
    'needed' => (is_array($categories) && count($categories) > 0) ? '' : 'Need at least 3 product categories'
];

// 5. Tags
$tags = get_terms(array('taxonomy' => 'product_tag', 'hide_empty' => false));
$results['Tags'] = [
    'total' => is_array($tags) ? count($tags) : 0,
    'status' => (is_array($tags) && count($tags) > 0) ? '✅' : '❌',
    'needed' => (is_array($tags) && count($tags) > 0) ? '' : 'Optional: Product tags'
];

// 6. Coupons
$coupon_query = new WP_Query(array(
    'post_type' => 'shop_coupon',
    'posts_per_page' => 100,
    'post_status' => 'publish'
));
$results['Coupons'] = [
    'total' => $coupon_query->found_posts,
    'status' => $coupon_query->found_posts > 0 ? '✅' : '⚠️',
    'needed' => $coupon_query->found_posts > 0 ? '' : 'Optional: Coupons for testing'
];

// 7. Refunds
$refunds = wc_get_orders(array('type' => 'shop_order_refund', 'limit' => 100));
$results['Refunds'] = [
    'total' => count($refunds),
    'status' => count($refunds) > 0 ? '✅' : '⚠️',
    'needed' => count($refunds) > 0 ? '' : 'Optional: Refunds for testing'
];

// Print results
foreach ($results as $type => $data) {
    echo "📦 {$type}:\n";
    foreach ($data as $key => $value) {
        if ($key === 'status') continue;
        if ($key === 'needed' && empty($value)) continue;
        if (is_numeric($value)) {
            echo "   • {$key}: {$value}\n";
        }
    }
    echo "   Status: {$data['status']}\n";
    if (!empty($data['needed'])) {
        echo "   ⚠️  {$data['needed']}\n";
    }
    echo "\n";
}

// Summary
echo str_repeat("=", 60) . "\n";
echo "📋 Test Coverage Summary:\n\n";

$test_scenarios = [
    'Inventory/Pie Chart' => $results['Products']['with_inventory'] > 0,
    'Product Listings' => $results['Products']['total'] > 0,
    'Order Statistics' => $results['Orders']['total'] > 0,
    'Order Charts' => $results['Orders']['total'] > 5,
    'Customer Data' => $results['Customers']['total'] > 0,
    'Categories' => $results['Categories']['total'] > 0,
    'Coupons' => $results['Coupons']['total'] > 0,
    'Refunds' => $results['Refunds']['total'] > 0,
];

foreach ($test_scenarios as $scenario => $has_data) {
    echo ($has_data ? '✅' : '❌') . " {$scenario}\n";
}

echo "\n💡 Recommendations:\n";
if ($results['Orders']['total'] < 10) {
    echo "   • Generate more orders: wp wc generate orders 20\n";
}
if ($results['Customers']['total'] < 5) {
    echo "   • Create more customers\n";
}
if ($results['Coupons']['total'] === 0) {
    echo "   • Create some coupons for testing\n";
}

