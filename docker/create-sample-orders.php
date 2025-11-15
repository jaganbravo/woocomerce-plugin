<?php
/**
 * Script to create sample WooCommerce orders with Dataviz products
 * Run: wp eval-file create-sample-orders.php
 */

// Load WordPress
require_once __DIR__ . '/wordpress/wp-load.php';

if (!function_exists('wc_create_order')) {
    die("WooCommerce is not active!\n");
}

// Product IDs for Dataviz products
$products = [
    12 => ['name' => 'Dataviz Analytics Dashboard', 'price' => 99.99],
    13 => ['name' => 'Dataviz AI Insights Pro', 'price' => 149.99],
    14 => ['name' => 'Dataviz Reporting Suite', 'price' => 79.99],
    15 => ['name' => 'Dataviz Customer Analytics', 'price' => 129.99],
    16 => ['name' => 'Dataviz Data Visualization Tools', 'price' => 89.99],
    17 => ['name' => 'Dataviz Performance Monitor', 'price' => 119.99],
    18 => ['name' => 'Dataviz Sales Forecasting', 'price' => 199.99],
    19 => ['name' => 'Dataviz Inventory Manager', 'price' => 159.99],
    20 => ['name' => 'Dataviz Marketing Analytics', 'price' => 139.99],
    21 => ['name' => 'Dataviz E-commerce Intelligence', 'price' => 249.99],
];

// Sample orders to create
$orders_to_create = [
    [
        'products' => [12],
        'status' => 'completed',
        'total' => 99.99,
    ],
    [
        'products' => [13, 14],
        'status' => 'completed',
        'total' => 229.98,
    ],
    [
        'products' => [15 => 2], // quantity 2
        'status' => 'processing',
        'total' => 259.98,
    ],
    [
        'products' => [16, 17, 18],
        'status' => 'completed',
        'total' => 409.97,
    ],
    [
        'products' => [19],
        'status' => 'completed',
        'total' => 159.99,
    ],
    [
        'products' => [20, 21],
        'status' => 'completed',
        'total' => 389.98,
    ],
    [
        'products' => [12, 16],
        'status' => 'pending',
        'total' => 189.98,
    ],
    [
        'products' => [18],
        'status' => 'completed',
        'total' => 199.99,
    ],
    [
        'products' => [13],
        'status' => 'completed',
        'total' => 149.99,
    ],
    [
        'products' => [14, 15, 16],
        'status' => 'completed',
        'total' => 299.97,
    ],
];

$created = 0;
$errors = 0;

foreach ($orders_to_create as $order_data) {
    try {
        // Create order
        $order = wc_create_order();
        
        if (is_wp_error($order)) {
            echo "Error creating order: " . $order->get_error_message() . "\n";
            $errors++;
            continue;
        }
        
        // Add products
        foreach ($order_data['products'] as $product_id => $quantity) {
            if (is_numeric($product_id)) {
                // If quantity is not specified, default to 1
                $qty = is_numeric($quantity) ? $quantity : 1;
                $prod_id = $product_id;
            } else {
                $qty = 1;
                $prod_id = $quantity;
            }
            
            $product = wc_get_product($prod_id);
            if ($product) {
                $order->add_product($product, $qty);
            }
        }
        
        // Set order status
        $order->set_status($order_data['status']);
        
        // Set customer
        $order->set_customer_id(1);
        
        // Set payment method
        $order->set_payment_method('bacs');
        $order->set_payment_method_title('Direct Bank Transfer');
        
        // Calculate totals
        $order->calculate_totals();
        
        // Save order
        $order->save();
        
        $created++;
        echo "Created order #{$order->get_id()} - Status: {$order_data['status']} - Total: $" . number_format($order_data['total'], 2) . "\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n";
echo "Summary:\n";
echo "Created: $created orders\n";
echo "Errors: $errors\n";

