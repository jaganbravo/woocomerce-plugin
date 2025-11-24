<?php
/**
 * Script to generate more WooCommerce orders
 * Run: docker compose exec wpcli wp eval-file generate-orders.php --allow-root
 */

require_once __DIR__ . '/wp-load.php';

if ( ! class_exists( 'WooCommerce' ) ) {
    die( "❌ WooCommerce is not active!\n" );
}

// Get number of orders to create from command line or default to 100
$num_orders = isset( $argv[1] ) ? (int) $argv[1] : 100;

echo "🛒 Generating $num_orders sample orders...\n\n";

// Get all products
$product_ids = wc_get_products( array(
    'limit'  => -1,
    'status' => 'publish',
    'return' => 'ids',
) );

if ( empty( $product_ids ) ) {
    die( "❌ No products found. Please add products first.\n" );
}

// Get all customers
$customers = get_users( array(
    'role'   => 'customer',
    'number' => -1,
) );

$customer_ids = array();
foreach ( $customers as $customer ) {
    $customer_ids[] = $customer->ID;
}

if ( empty( $customer_ids ) ) {
    echo "⚠️  No customers found. Creating a default customer...\n";
    $customer_id = wp_create_user( 'defaultcustomer', 'customer123', 'defaultcustomer@example.com' );
    if ( ! is_wp_error( $customer_id ) ) {
        $user = new WP_User( $customer_id );
        $user->set_role( 'customer' );
        $customer_ids[] = $customer_id;
    } else {
        // Try to get existing default customer
        $default_user = get_user_by( 'login', 'defaultcustomer' );
        if ( $default_user ) {
            $customer_ids[] = $default_user->ID;
        }
    }
}

echo "📊 Using:\n";
echo "   - Products: " . count( $product_ids ) . "\n";
echo "   - Customers: " . count( $customer_ids ) . "\n\n";

// Order statuses with weights
$statuses = array(
    'completed'  => 50,
    'processing' => 20,
    'pending'    => 15,
    'on-hold'    => 10,
    'cancelled'  => 5,
);

$get_status = function() use ( $statuses ) {
    $total = array_sum( $statuses );
    $rand = rand( 1, $total );
    $current = 0;
    foreach ( $statuses as $status => $weight ) {
        $current += $weight;
        if ( $rand <= $current ) {
            return $status;
        }
    }
    return 'completed';
};

// Create orders over the last 180 days
$end_date = current_time( 'timestamp' );
$start_date = $end_date - ( 180 * DAY_IN_SECONDS );

$created = 0;
$errors = 0;

echo "📦 Creating orders...\n";

for ( $i = 0; $i < $num_orders; $i++ ) {
    $order = wc_create_order();
    
    if ( is_wp_error( $order ) ) {
        $errors++;
        continue;
    }
    
    // Random date within last 180 days
    $random_timestamp = rand( $start_date, $end_date );
    $order->set_date_created( $random_timestamp );
    
    // Set customer
    $customer_id = $customer_ids[ array_rand( $customer_ids ) ];
    $order->set_customer_id( $customer_id );
    
    $customer = new WP_User( $customer_id );
    if ( $customer ) {
        $order->set_billing_email( $customer->user_email );
        $first_name = get_user_meta( $customer_id, 'first_name', true ) ?: get_user_meta( $customer_id, 'billing_first_name', true ) ?: 'Customer';
        $last_name = get_user_meta( $customer_id, 'last_name', true ) ?: get_user_meta( $customer_id, 'billing_last_name', true ) ?: '';
        $order->set_billing_first_name( $first_name );
        $order->set_billing_last_name( $last_name );
        $order->set_billing_city( get_user_meta( $customer_id, 'billing_city', true ) ?: 'New York' );
        $order->set_billing_state( get_user_meta( $customer_id, 'billing_state', true ) ?: 'NY' );
        $order->set_billing_country( get_user_meta( $customer_id, 'billing_country', true ) ?: 'US' );
        $order->set_billing_phone( get_user_meta( $customer_id, 'billing_phone', true ) ?: sprintf( '(%d) %d-%d', rand( 200, 999 ), rand( 200, 999 ), rand( 1000, 9999 ) ) );
    }
    
    // Add 1-4 random products
    $num_items = rand( 1, min( 4, count( $product_ids ) ) );
    $selected_indices = array_rand( $product_ids, $num_items );
    
    if ( ! is_array( $selected_indices ) ) {
        $selected_indices = array( $selected_indices );
    }
    
    foreach ( $selected_indices as $index ) {
        $product_id = $product_ids[ $index ];
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
    
    $created++;
    
    if ( $created % 10 === 0 ) {
        echo "   ✓ Created $created orders...\n";
    }
}

echo "\n✅ Order generation complete!\n\n";
echo "📊 Summary:\n";
echo "   - Orders created: $created\n";
echo "   - Errors: $errors\n";

// Get final counts
$total_products = count( $product_ids );
$total_customers = count( $customer_ids );
$total_orders = wc_get_orders( array(
    'limit'  => -1,
    'return' => 'ids',
    'status' => 'any',
) );

echo "   - Total products: $total_products\n";
echo "   - Total customers: $total_customers\n";
echo "   - Total orders: " . count( $total_orders ) . "\n";
echo "\n💡 View orders in WordPress admin:\n";
echo "   - Orders: http://localhost:8080/wp-admin/edit.php?post_type=shop_order\n";

