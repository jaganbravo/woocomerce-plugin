<?php
/**
 * Add missing test data for comprehensive testing
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

echo "📦 Adding Missing Test Data\n";
echo str_repeat("=", 60) . "\n\n";

// 1. Create customers
echo "👥 Creating customers...\n";
$customers_created = 0;
for ( $i = 2; $i <= 5; $i++ ) {
    $username = "customer{$i}";
    $email = "customer{$i}@example.com";
    
    // Check if user exists
    if ( ! username_exists( $username ) && ! email_exists( $email ) ) {
        $user_id = wp_create_user( $username, 'customer123', $email );
        if ( ! is_wp_error( $user_id ) ) {
            $user = new WP_User( $user_id );
            $user->set_role( 'customer' );
            wp_update_user( array(
                'ID' => $user_id,
                'first_name' => "Customer{$i}",
                'last_name' => 'Test',
                'display_name' => "Customer{$i} Test"
            ) );
            $customers_created++;
            echo "   ✓ Created {$username}\n";
        } else {
            echo "   ⚠️  Failed to create {$username}: " . $user_id->get_error_message() . "\n";
        }
    } else {
        echo "   ⊙ {$username} already exists\n";
    }
}
echo "   Created {$customers_created} new customers\n\n";

// 2. Create coupons
echo "🎫 Creating coupons...\n";
$coupons_created = 0;

$coupon_data = array(
    array(
        'code' => 'TEST10',
        'discount_type' => 'percent',
        'amount' => 10,
        'description' => '10% off coupon for testing'
    ),
    array(
        'code' => 'FLAT20',
        'discount_type' => 'fixed_cart',
        'amount' => 20,
        'description' => '$20 off coupon for testing'
    ),
    array(
        'code' => 'SUMMER25',
        'discount_type' => 'percent',
        'amount' => 25,
        'description' => '25% off summer sale coupon'
    ),
);

foreach ( $coupon_data as $coupon_info ) {
    // Check if coupon exists
    $coupon_id = wc_get_coupon_id_by_code( $coupon_info['code'] );
    
    if ( ! $coupon_id ) {
        $coupon = new WC_Coupon();
        $coupon->set_code( $coupon_info['code'] );
        $coupon->set_discount_type( $coupon_info['discount_type'] );
        $coupon->set_amount( $coupon_info['amount'] );
        $coupon->set_description( $coupon_info['description'] );
        $coupon->set_status( 'publish' );
        
        $coupon_id = $coupon->save();
        if ( $coupon_id ) {
            $coupons_created++;
            echo "   ✓ Created coupon: {$coupon_info['code']} ({$coupon_info['amount']}" . 
                 ($coupon_info['discount_type'] === 'percent' ? '%' : '$') . " off)\n";
        } else {
            echo "   ⚠️  Failed to create coupon: {$coupon_info['code']}\n";
        }
    } else {
        echo "   ⊙ Coupon {$coupon_info['code']} already exists\n";
    }
}
echo "   Created {$coupons_created} new coupons\n\n";

// 3. Create product categories
echo "📁 Creating product categories...\n";
$categories_created = 0;

$category_names = array( 'Electronics', 'Clothing', 'Accessories', 'Sports' );

foreach ( $category_names as $cat_name ) {
    if ( ! term_exists( $cat_name, 'product_cat' ) ) {
        $term = wp_insert_term( $cat_name, 'product_cat' );
        if ( ! is_wp_error( $term ) ) {
            $categories_created++;
            echo "   ✓ Created category: {$cat_name}\n";
        } else {
            echo "   ⚠️  Failed to create category {$cat_name}: " . $term->get_error_message() . "\n";
        }
    } else {
        echo "   ⊙ Category {$cat_name} already exists\n";
    }
}
echo "   Created {$categories_created} new categories\n\n";

// 4. Create product tags
echo "🏷️  Creating product tags...\n";
$tags_created = 0;

$tag_names = array( 'popular', 'sale', 'new', 'featured' );

foreach ( $tag_names as $tag_name ) {
    if ( ! term_exists( $tag_name, 'product_tag' ) ) {
        $term = wp_insert_term( $tag_name, 'product_tag' );
        if ( ! is_wp_error( $term ) ) {
            $tags_created++;
            echo "   ✓ Created tag: {$tag_name}\n";
        } else {
            echo "   ⚠️  Failed to create tag {$tag_name}: " . $term->get_error_message() . "\n";
        }
    } else {
        echo "   ⊙ Tag {$tag_name} already exists\n";
    }
}
echo "   Created {$tags_created} new tags\n\n";

echo str_repeat("=", 60) . "\n";
echo "✅ Done! Summary:\n";
echo "   • Customers: +{$customers_created}\n";
echo "   • Coupons: +{$coupons_created}\n";
echo "   • Categories: +{$categories_created}\n";
echo "   • Tags: +{$tags_created}\n\n";
echo "💡 Run check-test-data-coverage.php to verify data coverage\n";

