<?php
/**
 * Script to create sample WooCommerce data
 * Run: docker compose exec wpcli wp eval-file create-sample-data.php --allow-root
 */

// Load WordPress
require_once __DIR__ . '/wordpress/wp-load.php';

if ( ! class_exists( 'WooCommerce' ) ) {
    die( "❌ WooCommerce is not active!\n" );
}

echo "🛒 Generating sample WooCommerce data...\n\n";

// Get all products
$products_query = new WP_Query( array(
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
) );

$product_ids = array();
if ( $products_query->have_posts() ) {
    while ( $products_query->have_posts() ) {
        $products_query->the_post();
        $product_ids[] = get_the_ID();
    }
    wp_reset_postdata();
}

// If no products exist, create some
if ( empty( $product_ids ) ) {
    echo "📦 Creating products...\n";
    $product_names = array( 'T-Shirt', 'Jeans', 'Sneakers', 'Hoodie', 'Watch', 'Backpack', 'Sunglasses', 'Cap', 'Wallet', 'Water Bottle' );
    $prices = array( 24.99, 59.99, 79.99, 49.99, 89.99, 39.99, 29.99, 19.99, 34.99, 14.99 );
    
    foreach ( $product_names as $index => $name ) {
        $product = new WC_Product_Simple();
        $product->set_name( $name );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'visible' );
        $product->set_regular_price( (string) $prices[ $index ] );
        $product->set_stock_status( 'instock' );
        $product->set_manage_stock( true );
        $product->set_stock_quantity( rand( 10, 100 ) );
        $product_id = $product->save();
        
        if ( $product_id ) {
            $product_ids[] = $product_id;
            echo "   ✓ Created product: {$name} (ID: {$product_id})\n";
        }
    }
} else {
    echo "📦 Found " . count( $product_ids ) . " existing products\n";
}

// Get all customers
$customers = get_users( array(
    'role'    => 'customer',
    'number'  => -1,
) );

$customer_ids = array();
foreach ( $customers as $customer ) {
    $customer_ids[] = $customer->ID;
}

// If no customers exist, create some
if ( empty( $customer_ids ) ) {
    echo "\n👥 Creating customers...\n";
    $customer_data = array(
        array( 'first' => 'John', 'last' => 'Smith', 'email' => 'john.smith@example.com' ),
        array( 'first' => 'Jane', 'last' => 'Johnson', 'email' => 'jane.johnson@example.com' ),
        array( 'first' => 'Mike', 'last' => 'Williams', 'email' => 'mike.williams@example.com' ),
        array( 'first' => 'Sarah', 'last' => 'Brown', 'email' => 'sarah.brown@example.com' ),
        array( 'first' => 'David', 'last' => 'Jones', 'email' => 'david.jones@example.com' ),
        array( 'first' => 'Emily', 'last' => 'Garcia', 'email' => 'emily.garcia@example.com' ),
        array( 'first' => 'Chris', 'last' => 'Miller', 'email' => 'chris.miller@example.com' ),
        array( 'first' => 'Jessica', 'last' => 'Davis', 'email' => 'jessica.davis@example.com' ),
    );
    
    foreach ( $customer_data as $data ) {
        $username = strtolower( $data['first'] . '.' . $data['last'] );
        $customer_id = wp_create_user( $username, 'customer123', $data['email'] );
        
        if ( ! is_wp_error( $customer_id ) ) {
            $user = new WP_User( $customer_id );
            $user->set_role( 'customer' );
            update_user_meta( $customer_id, 'first_name', $data['first'] );
            update_user_meta( $customer_id, 'last_name', $data['last'] );
            update_user_meta( $customer_id, 'billing_first_name', $data['first'] );
            update_user_meta( $customer_id, 'billing_last_name', $data['last'] );
            update_user_meta( $customer_id, 'billing_email', $data['email'] );
            update_user_meta( $customer_id, 'billing_city', 'New York' );
            update_user_meta( $customer_id, 'billing_state', 'NY' );
            update_user_meta( $customer_id, 'billing_country', 'US' );
            update_user_meta( $customer_id, 'billing_phone', sprintf( '(%d) %d-%d', rand( 200, 999 ), rand( 200, 999 ), rand( 1000, 9999 ) ) );
            
            $customer_ids[] = $customer_id;
            echo "   ✓ Created customer: {$data['first']} {$data['last']} (ID: {$customer_id})\n";
        }
    }
} else {
    echo "\n👥 Found " . count( $customer_ids ) . " existing customers\n";
}

// Create orders
echo "\n📦 Creating orders...\n";
$order_count = 0;
$statuses = array( 'completed', 'processing', 'pending', 'on-hold' );
$status_weights = array( 'completed' => 60, 'processing' => 20, 'pending' => 10, 'on-hold' => 10 );

// Get weighted random status
$get_status = function() use ( $statuses, $status_weights ) {
    $total = array_sum( $status_weights );
    $rand = rand( 1, $total );
    $current = 0;
    foreach ( $status_weights as $status => $weight ) {
        $current += $weight;
        if ( $rand <= $current ) {
            return $status;
        }
    }
    return 'completed';
};

// Create orders over the last 90 days
$end_date = current_time( 'timestamp' );
$start_date = $end_date - ( 90 * DAY_IN_SECONDS );

for ( $i = 0; $i < 20; $i++ ) {
    if ( empty( $product_ids ) || empty( $customer_ids ) ) {
        break;
    }
    
    $order = wc_create_order();
    
    if ( is_wp_error( $order ) ) {
        continue;
    }
    
    // Random date within last 90 days
    $random_timestamp = rand( $start_date, $end_date );
    $order->set_date_created( $random_timestamp );
    
    // Set customer
    $customer_id = $customer_ids[ array_rand( $customer_ids ) ];
    $order->set_customer_id( $customer_id );
    $customer = new WP_User( $customer_id );
    if ( $customer ) {
        $order->set_billing_email( $customer->user_email );
        $order->set_billing_first_name( get_user_meta( $customer_id, 'first_name', true ) );
        $order->set_billing_last_name( get_user_meta( $customer_id, 'last_name', true ) );
        $order->set_billing_city( get_user_meta( $customer_id, 'billing_city', true ) );
        $order->set_billing_state( get_user_meta( $customer_id, 'billing_state', true ) );
        $order->set_billing_country( get_user_meta( $customer_id, 'billing_country', true ) );
        $order->set_billing_phone( get_user_meta( $customer_id, 'billing_phone', true ) );
    }
    
    // Add 1-3 random products
    $num_items = rand( 1, min( 3, count( $product_ids ) ) );
    $selected_products = array_rand( $product_ids, $num_items );
    
    if ( ! is_array( $selected_products ) ) {
        $selected_products = array( $selected_products );
    }
    
    foreach ( $selected_products as $product_index ) {
        $product_id = $product_ids[ $product_index ];
        $product = wc_get_product( $product_id );
        
        if ( $product ) {
            $quantity = rand( 1, 3 );
            $order->add_product( $product, $quantity );
        }
    }
    
    // Set status
    $status = $get_status();
    $order->set_status( $status );
    
    // Set payment method
    $order->set_payment_method( 'bacs' );
    $order->set_payment_method_title( 'Direct Bank Transfer' );
    
    $order->calculate_totals();
    $order->save();
    
    // Update product sales for completed orders
    if ( 'completed' === $status ) {
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product ) {
                $current_sales = $product->get_total_sales();
                $product->set_total_sales( $current_sales + $item->get_quantity() );
                $product->save();
            }
        }
    }
    
    $order_count++;
    echo "   ✓ Created order #{$order->get_id()} - Status: {$status} - Total: $" . number_format( $order->get_total(), 2 ) . "\n";
}

echo "\n✅ Sample data generation complete!\n";
echo "\n📋 Summary:\n";
echo "   - Products: " . count( $product_ids ) . "\n";
echo "   - Customers: " . count( $customer_ids ) . "\n";
echo "   - Orders created: {$order_count}\n";
echo "\n💡 You can view the data in WordPress admin:\n";
echo "   - Products: http://localhost:8080/wp-admin/edit.php?post_type=product\n";
echo "   - Customers: http://localhost:8080/wp-admin/users.php?role=customer\n";
echo "   - Orders: http://localhost:8080/wp-admin/edit.php?post_type=shop_order\n";

