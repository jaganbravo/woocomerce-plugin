<?php
/**
 * Script to add MORE sample WooCommerce data
 * Run: docker compose exec wpcli wp eval-file add-more-sample-data.php --allow-root
 */

require_once __DIR__ . '/wordpress/wp-load.php';

if ( ! class_exists( 'WooCommerce' ) ) {
    die( "❌ WooCommerce is not active!\n" );
}

echo "🛒 Adding MORE sample data to WooCommerce...\n\n";

// Additional products
$additional_products = array(
    array( 'name' => 'Running Shoes', 'price' => 99.99 ),
    array( 'name' => 'Leather Jacket', 'price' => 149.99 ),
    array( 'name' => 'Denim Shorts', 'price' => 34.99 ),
    array( 'name' => 'Baseball Cap', 'price' => 22.99 ),
    array( 'name' => 'Sneakers', 'price' => 79.99 ),
    array( 'name' => 'Polo Shirt', 'price' => 39.99 ),
    array( 'name' => 'Cargo Pants', 'price' => 54.99 ),
    array( 'name' => 'Winter Coat', 'price' => 129.99 ),
    array( 'name' => 'Summer Dress', 'price' => 49.99 ),
    array( 'name' => 'Hiking Boots', 'price' => 119.99 ),
    array( 'name' => 'Bluetooth Headphones', 'price' => 89.99 ),
    array( 'name' => 'Smart Watch', 'price' => 199.99 ),
    array( 'name' => 'Gym Bag', 'price' => 44.99 ),
    array( 'name' => 'Yoga Mat', 'price' => 29.99 ),
    array( 'name' => 'Dumbbells Set', 'price' => 79.99 ),
    array( 'name' => 'Bicycle Helmet', 'price' => 59.99 ),
    array( 'name' => 'Tennis Racket', 'price' => 89.99 ),
    array( 'name' => 'Basketball', 'price' => 24.99 ),
    array( 'name' => 'Fitness Tracker', 'price' => 69.99 ),
    array( 'name' => 'Sports Bra', 'price' => 34.99 ),
);

echo "📦 Adding more products...\n";
$product_ids = array();

// Get existing products
$existing_products = wc_get_products( array(
    'limit'  => -1,
    'status' => 'publish',
    'return' => 'ids',
) );

$product_ids = array_merge( $product_ids, $existing_products );

$created_count = 0;
foreach ( $additional_products as $product_data ) {
    // Check if product already exists
    $existing = wc_get_products( array(
        'name'   => $product_data['name'],
        'limit'  => 1,
        'return' => 'ids',
    ) );
    
    if ( ! empty( $existing ) ) {
        $product_ids[] = $existing[0];
        continue;
    }
    
    $product = new WC_Product_Simple();
    $product->set_name( $product_data['name'] );
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'visible' );
    $product->set_regular_price( (string) $product_data['price'] );
    $product->set_stock_status( 'instock' );
    $product->set_manage_stock( true );
    $product->set_stock_quantity( rand( 15, 150 ) );
    $product->set_total_sales( rand( 0, 100 ) );
    
    $product_id = $product->save();
    
    if ( $product_id ) {
        $product_ids[] = $product_id;
        $created_count++;
        echo "   ✓ Created: {$product_data['name']} (ID: {$product_id})\n";
    }
}

echo "   Total products: " . count( $product_ids ) . " (" . $created_count . " new)\n\n";

// Additional customers
$additional_customers = array(
    array( 'first' => 'Robert', 'last' => 'Taylor', 'email' => 'robert.taylor@example.com' ),
    array( 'first' => 'Lisa', 'last' => 'Anderson', 'email' => 'lisa.anderson@example.com' ),
    array( 'first' => 'James', 'last' => 'Martinez', 'email' => 'james.martinez@example.com' ),
    array( 'first' => 'Patricia', 'last' => 'Wilson', 'email' => 'patricia.wilson@example.com' ),
    array( 'first' => 'Michael', 'last' => 'Moore', 'email' => 'michael.moore@example.com' ),
    array( 'first' => 'Jennifer', 'last' => 'Thomas', 'email' => 'jennifer.thomas@example.com' ),
    array( 'first' => 'William', 'last' => 'Jackson', 'email' => 'william.jackson@example.com' ),
    array( 'first' => 'Linda', 'last' => 'White', 'email' => 'linda.white@example.com' ),
    array( 'first' => 'Richard', 'last' => 'Harris', 'email' => 'richard.harris@example.com' ),
    array( 'first' => 'Susan', 'last' => 'Martin', 'email' => 'susan.martin@example.com' ),
    array( 'first' => 'Joseph', 'last' => 'Thompson', 'email' => 'joseph.thompson@example.com' ),
    array( 'first' => 'Jessica', 'last' => 'Garcia', 'email' => 'jessica.garcia@example.com' ),
    array( 'first' => 'Thomas', 'last' => 'Martinez', 'email' => 'thomas.martinez@example.com' ),
    array( 'first' => 'Sarah', 'last' => 'Robinson', 'email' => 'sarah.robinson@example.com' ),
    array( 'first' => 'Charles', 'last' => 'Clark', 'email' => 'charles.clark@example.com' ),
);

echo "👥 Adding more customers...\n";
$customer_ids = array();

// Get existing customers
$existing_customers = get_users( array(
    'role'   => 'customer',
    'number' => -1,
) );

foreach ( $existing_customers as $customer ) {
    $customer_ids[] = $customer->ID;
}

$cities = array( 'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose' );
$states = array( 'NY', 'CA', 'IL', 'TX', 'AZ', 'PA', 'FL', 'OH', 'GA', 'NC' );

$created_count = 0;
foreach ( $additional_customers as $data ) {
    // Check if customer already exists
    $existing = get_user_by( 'email', $data['email'] );
    if ( $existing ) {
        $customer_ids[] = $existing->ID;
        continue;
    }
    
    $username = strtolower( $data['first'] . '.' . $data['last'] . '.' . rand( 100, 999 ) );
    $customer_id = wp_create_user( $username, 'customer123', $data['email'] );
    
    if ( ! is_wp_error( $customer_id ) ) {
        $user = new WP_User( $customer_id );
        $user->set_role( 'customer' );
        
        $city_index = array_rand( $cities );
        $state_index = array_rand( $states );
        
        update_user_meta( $customer_id, 'first_name', $data['first'] );
        update_user_meta( $customer_id, 'last_name', $data['last'] );
        update_user_meta( $customer_id, 'billing_first_name', $data['first'] );
        update_user_meta( $customer_id, 'billing_last_name', $data['last'] );
        update_user_meta( $customer_id, 'billing_email', $data['email'] );
        update_user_meta( $customer_id, 'billing_city', $cities[ $city_index ] );
        update_user_meta( $customer_id, 'billing_state', $states[ $state_index ] );
        update_user_meta( $customer_id, 'billing_country', 'US' );
        update_user_meta( $customer_id, 'billing_phone', sprintf( '(%d) %d-%d', rand( 200, 999 ), rand( 200, 999 ), rand( 1000, 9999 ) ) );
        
        $customer_ids[] = $customer_id;
        $created_count++;
        echo "   ✓ Created: {$data['first']} {$data['last']} (ID: {$customer_id})\n";
    }
}

echo "   Total customers: " . count( $customer_ids ) . " (" . $created_count . " new)\n\n";

// Generate more orders
echo "📦 Creating more orders...\n";

$order_statuses = array(
    'completed'  => 50,
    'processing' => 20,
    'pending'    => 15,
    'on-hold'    => 10,
    'cancelled'  => 5,
);

$end_date = current_time( 'timestamp' );
$start_date = $end_date - ( 180 * DAY_IN_SECONDS ); // Last 6 months

$orders_to_create = 50;
$created_orders = 0;

// Weighted random status
$get_status = function() use ( $order_statuses ) {
    $total = array_sum( $order_statuses );
    $rand = rand( 1, $total );
    $current = 0;
    foreach ( $order_statuses as $status => $weight ) {
        $current += $weight;
        if ( $rand <= $current ) {
            return $status;
        }
    }
    return 'completed';
};

for ( $i = 0; $i < $orders_to_create; $i++ ) {
    if ( empty( $product_ids ) || empty( $customer_ids ) ) {
        break;
    }
    
    $order = wc_create_order();
    
    if ( is_wp_error( $order ) ) {
        continue;
    }
    
    // Random date within last 6 months
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
    
    // Add 1-4 random products
    $num_items = rand( 1, min( 4, count( $product_ids ) ) );
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
    
    $created_orders++;
    if ( $created_orders % 10 === 0 ) {
        echo "   ✓ Created {$created_orders} orders...\n";
    }
}

echo "   ✓ Created {$created_orders} new orders\n\n";

echo "✅ Sample data generation complete!\n\n";
echo "📋 Summary:\n";
echo "   - Products: " . count( $product_ids ) . " total\n";
echo "   - Customers: " . count( $customer_ids ) . " total\n";
echo "   - Orders: {$created_orders} new orders created\n";
echo "\n💡 You can view the data in WordPress admin:\n";
echo "   - Products: http://localhost:8080/wp-admin/edit.php?post_type=product\n";
echo "   - Customers: http://localhost:8080/wp-admin/users.php?role=customer\n";
echo "   - Orders: http://localhost:8080/wp-admin/edit.php?post_type=shop_order\n";

