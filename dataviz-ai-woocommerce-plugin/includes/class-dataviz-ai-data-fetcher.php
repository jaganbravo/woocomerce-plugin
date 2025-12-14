<?php
/**
 * Provides helper methods for retrieving WooCommerce data.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wrapper around WooCommerce data helpers.
 */
class Dataviz_AI_Data_Fetcher {

	/**
	 * Fetch a list of recent orders.
	 *
	 * @param array $args Optional query arguments.
	 *
	 * @return array
	 */
	public function get_recent_orders( array $args = array() ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$defaults = array(
			'limit'   => 5,
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		// Support date_created range format: "timestamp1...timestamp2"
		if ( isset( $args['date_created'] ) && is_string( $args['date_created'] ) && strpos( $args['date_created'], '...' ) !== false ) {
			list( $from, $to ) = explode( '...', $args['date_created'], 2 );
			$args['date_created'] = absint( $from ) . '...' . absint( $to );
		}

		return wc_get_orders( wp_parse_args( $args, $defaults ) );
	}

	/**
	 * Aggregate product sales totals.
	 *
	 * @param int $limit Number of products.
	 *
	 * @return array
	 */
	public function get_top_products( $limit = 5 ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'limit'    => $limit,
				'orderby'  => 'total_sales',
				'order'    => 'DESC',
				'status'   => array( 'publish' ),
				'paginate' => false,
			)
		);

		return array_map(
			static function( $product ) {
				/* @var WC_Product $product */
				return array(
					'id'          => $product->get_id(),
					'name'        => $product->get_name(),
					'total_sales' => (int) $product->get_total_sales(),
					'price'       => $product->get_price(),
				);
			},
			$products
		);
	}

	/**
	 * Basic customer metrics.
	 *
	 * @return array
	 */
	public function get_customer_summary() {
		$summary = array(
			'total_customers'  => 0,
			'avg_lifetime_spent' => 0,
		);

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $summary;
		}

		$query = new WP_User_Query(
			array(
				'role'   => 'customer',
				'fields' => array( 'ID' ),
			)
		);

		$summary['total_customers'] = (int) $query->get_total();

		$total_spent = 0;

		if ( ! empty( $query->results ) ) {
			foreach ( $query->results as $user_id ) {
				$total_spent += (float) wc_get_customer_total_spent( $user_id );
			}

			if ( $summary['total_customers'] > 0 ) {
				$summary['avg_lifetime_spent'] = $total_spent / $summary['total_customers'];
			}
		}

		return $summary;
	}

	/**
	 * Get list of customers with their information.
	 *
	 * @param int $limit Number of customers to return.
	 *
	 * @return array
	 */
	public function get_customers( $limit = 10 ) {
		$query = new WP_User_Query(
			array(
				'role'   => 'customer',
				'number' => $limit,
				'orderby' => 'registered',
				'order'   => 'DESC',
			)
		);

		$customers = array();

		if ( ! empty( $query->results ) ) {
			foreach ( $query->results as $user ) {
				$customer_data = array(
					'id'         => $user->ID,
					'email'      => $user->user_email,
					'username'   => $user->user_login,
					'first_name' => get_user_meta( $user->ID, 'first_name', true ),
					'last_name'  => get_user_meta( $user->ID, 'last_name', true ),
					'city'       => get_user_meta( $user->ID, 'billing_city', true ),
					'state'      => get_user_meta( $user->ID, 'billing_state', true ),
					'country'    => get_user_meta( $user->ID, 'billing_country', true ),
					'phone'      => get_user_meta( $user->ID, 'billing_phone', true ),
					'company'    => get_user_meta( $user->ID, 'billing_company', true ),
					'registered' => $user->user_registered,
				);

				if ( function_exists( 'wc_get_customer_total_spent' ) ) {
					$customer_data['total_spent'] = (float) wc_get_customer_total_spent( $user->ID );
					$customer_data['order_count'] = (int) wc_get_customer_order_count( $user->ID );
				} else {
					$customer_data['total_spent'] = 0;
					$customer_data['order_count'] = 0;
				}

				$customers[] = $customer_data;
			}
		}

		return $customers;
	}

	/**
	 * Get aggregated order statistics without fetching individual orders.
	 * Efficient for large datasets (millions of orders).
	 *
	 * @param array $args Optional query arguments (date_from, date_to, status).
	 * @return array Aggregated statistics.
	 */
	public function get_order_statistics( array $args = array() ) {
		global $wpdb;

		$date_from = isset( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : null;
		$date_to   = isset( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : null;
		$status    = isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : null;

		// Build WHERE clause.
		$where = array( "p.post_type = 'shop_order'" );
		
		if ( $date_from ) {
			$where[] = $wpdb->prepare( "p.post_date >= %s", $date_from . ' 00:00:00' );
		}
		
		if ( $date_to ) {
			$where[] = $wpdb->prepare( "p.post_date <= %s", $date_to . ' 23:59:59' );
		}
		
		if ( $status ) {
			$where[] = $wpdb->prepare( "p.post_status = %s", 'wc-' . $status );
		}

		$where_clause = implode( ' AND ', $where );

		// Single query to get all aggregated stats.
		$query = "
			SELECT 
				COUNT(DISTINCT p.ID) as total_orders,
				SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) as total_revenue,
				AVG(CAST(pm_total.meta_value AS DECIMAL(10,2))) as avg_order_value,
				MIN(CAST(pm_total.meta_value AS DECIMAL(10,2))) as min_order_value,
				MAX(CAST(pm_total.meta_value AS DECIMAL(10,2))) as max_order_value,
				COUNT(DISTINCT pm_customer.meta_value) as unique_customers
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
			LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
			WHERE {$where_clause}
		";

		$stats = $wpdb->get_row( $query, ARRAY_A );

		// Get status breakdown.
		$status_query = "
			SELECT 
				REPLACE(p.post_status, 'wc-', '') as status,
				COUNT(*) as count,
				SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) as revenue
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
			WHERE {$where_clause}
			GROUP BY p.post_status
		";

		$status_breakdown = $wpdb->get_results( $status_query, ARRAY_A );

		// Get daily revenue trend (last 30 days or date range).
		$trend_days = 30;
		if ( $date_from && $date_to ) {
			$days_diff = ( strtotime( $date_to ) - strtotime( $date_from ) ) / DAY_IN_SECONDS;
			$trend_days = min( 90, max( 7, (int) $days_diff ) ); // Between 7-90 days.
		}

		$trend_query = "
			SELECT 
				DATE(p.post_date) as date,
				COUNT(*) as order_count,
				SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) as revenue
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
			WHERE {$where_clause}
			AND p.post_date >= DATE_SUB(NOW(), INTERVAL {$trend_days} DAY)
			GROUP BY DATE(p.post_date)
			ORDER BY date DESC
			LIMIT {$trend_days}
		";

		$daily_trend = $wpdb->get_results( $trend_query, ARRAY_A );

		return array(
			'summary'         => array(
				'total_orders'      => (int) ( $stats['total_orders'] ?? 0 ),
				'total_revenue'     => (float) ( $stats['total_revenue'] ?? 0 ),
				'avg_order_value'   => (float) ( $stats['avg_order_value'] ?? 0 ),
				'min_order_value'    => (float) ( $stats['min_order_value'] ?? 0 ),
				'max_order_value'    => (float) ( $stats['max_order_value'] ?? 0 ),
				'unique_customers'   => (int) ( $stats['unique_customers'] ?? 0 ),
			),
			'status_breakdown' => $status_breakdown,
			'daily_trend'      => $daily_trend,
			'date_range'       => array(
				'from' => $date_from,
				'to'   => $date_to,
			),
		);
	}

	/**
	 * Get sampled orders for analysis (statistical sampling).
	 * Returns a representative sample instead of all orders.
	 *
	 * @param array $args Optional query arguments.
	 * @return array Sampled orders.
	 */
	public function get_sampled_orders( array $args = array() ) {
		global $wpdb;

		$sample_size = isset( $args['sample_size'] ) ? (int) $args['sample_size'] : 100;
		$date_from   = isset( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : null;
		$date_to     = isset( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : null;
		$status      = isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : null;

		// Build WHERE clause.
		$where = array( "p.post_type = 'shop_order'" );
		
		if ( $date_from ) {
			$where[] = $wpdb->prepare( "p.post_date >= %s", $date_from . ' 00:00:00' );
		}
		
		if ( $date_to ) {
			$where[] = $wpdb->prepare( "p.post_date <= %s", $date_to . ' 23:59:59' );
		}
		
		if ( $status ) {
			$where[] = $wpdb->prepare( "p.post_status = %s", 'wc-' . $status );
		}

		$where_clause = implode( ' AND ', $where );

		// Get total count first.
		$total_query = "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$where_clause}";
		$total_count = (int) $wpdb->get_var( $total_query );

		if ( $total_count === 0 ) {
			return array();
		}

		// If total is less than sample size, just get all.
		if ( $total_count <= $sample_size ) {
			return $this->get_recent_orders( $args );
		}

		// Use random sampling for large datasets.
		// For very large datasets, use systematic sampling.
		$sampling_interval = floor( $total_count / $sample_size );

		$query = "
			SELECT p.ID
			FROM {$wpdb->posts} p
			WHERE {$where_clause}
			ORDER BY p.post_date DESC
			LIMIT {$sample_size}
		";

		// For truly random sampling (slower but more accurate):
		// $query = "
		// 	SELECT p.ID
		// 	FROM {$wpdb->posts} p
		// 	WHERE {$where_clause}
		// 	ORDER BY RAND()
		// 	LIMIT {$sample_size}
		// ";

		$order_ids = $wpdb->get_col( $query );
		
		if ( empty( $order_ids ) ) {
			return array();
		}

		// Fetch full order objects.
		$orders = array();
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$orders[] = $order;
			}
		}

		return $orders;
	}

	/**
	 * Get time-based aggregated data (hourly, daily, weekly, monthly).
	 * Perfect for trend analysis without fetching individual records.
	 *
	 * @param string $period 'hour', 'day', 'week', 'month'.
	 * @param array  $args    Optional query arguments.
	 * @return array Aggregated data by time period.
	 */
	public function get_orders_by_period( $period = 'day', array $args = array() ) {
		global $wpdb;

		$date_from = isset( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : null;
		$date_to   = isset( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : null;
		$status    = isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : null;

		// Build WHERE clause.
		$where = array( "p.post_type = 'shop_order'" );
		
		if ( $date_from ) {
			$where[] = $wpdb->prepare( "p.post_date >= %s", $date_from . ' 00:00:00' );
		}
		
		if ( $date_to ) {
			$where[] = $wpdb->prepare( "p.post_date <= %s", $date_to . ' 23:59:59' );
		}
		
		if ( $status ) {
			$where[] = $wpdb->prepare( "p.post_status = %s", 'wc-' . $status );
		}

		$where_clause = implode( ' AND ', $where );

		// Determine date format based on period.
		$date_formats = array(
			'hour'  => '%Y-%m-%d %H:00:00',
			'day'   => '%Y-%m-%d',
			'week'  => '%Y-%u', // Year-week.
			'month' => '%Y-%m',
		);

		$date_format = isset( $date_formats[ $period ] ) ? $date_formats[ $period ] : $date_formats['day'];

		$query = "
			SELECT 
				DATE_FORMAT(p.post_date, %s) as period,
				COUNT(*) as order_count,
				SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) as revenue,
				AVG(CAST(pm_total.meta_value AS DECIMAL(10,2))) as avg_order_value
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
			WHERE {$where_clause}
			GROUP BY period
			ORDER BY period DESC
		";

		$query = $wpdb->prepare( $query, $date_format );

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get product categories.
	 *
	 * @return array
	 */
	public function get_product_categories() {
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $categories ) || empty( $categories ) ) {
			return array();
		}

		return array_map(
			static function( $category ) {
				return array(
					'id'          => $category->term_id,
					'name'        => $category->name,
					'slug'        => $category->slug,
					'description' => $category->description,
					'count'       => $category->count,
					'parent'      => $category->parent,
				);
			},
			$categories
		);
	}

	/**
	 * Get product tags.
	 *
	 * @return array
	 */
	public function get_product_tags() {
		$tags = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $tags ) || empty( $tags ) ) {
			return array();
		}

		return array_map(
			static function( $tag ) {
				return array(
					'id'          => $tag->term_id,
					'name'        => $tag->name,
					'slug'        => $tag->slug,
					'description' => $tag->description,
					'count'       => $tag->count,
				);
			},
			$tags
		);
	}

	/**
	 * Get coupons.
	 *
	 * @param array $args Optional query arguments.
	 * @return array
	 */
	public function get_coupons( array $args = array() ) {
		$defaults = array(
			'posts_per_page' => isset( $args['limit'] ) ? (int) $args['limit'] : 50,
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new WP_Query( wp_parse_args( $args, $defaults ) );

		if ( ! $query->have_posts() ) {
			return array();
		}

		$coupons = array();

		foreach ( $query->posts as $post ) {
			$coupon = new WC_Coupon( $post->ID );
			
			if ( ! $coupon->get_id() ) {
				continue;
			}

			$coupons[] = array(
				'id'              => $coupon->get_id(),
				'code'            => $coupon->get_code(),
				'amount'          => (float) $coupon->get_amount(),
				'discount_type'   => $coupon->get_discount_type(),
				'usage_count'     => (int) $coupon->get_usage_count(),
				'usage_limit'     => $coupon->get_usage_limit() ? (int) $coupon->get_usage_limit() : null,
				'date_expires'    => $coupon->get_date_expires() ? $coupon->get_date_expires()->date_i18n( 'Y-m-d' ) : null,
				'minimum_amount'  => (float) $coupon->get_minimum_amount(),
				'maximum_amount'  => (float) $coupon->get_maximum_amount(),
			);
		}

		return $coupons;
	}

	/**
	 * Get refunds.
	 *
	 * @param array $args Optional query arguments.
	 * @return array
	 */
	public function get_refunds( array $args = array() ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$defaults = array(
			'type'    => 'shop_order_refund',
			'limit'   => isset( $args['limit'] ) ? (int) $args['limit'] : 50,
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		$refunds = wc_get_orders( wp_parse_args( $args, $defaults ) );

		if ( empty( $refunds ) ) {
			return array();
		}

		$formatted_refunds = array();

		foreach ( $refunds as $refund ) {
			if ( ! is_a( $refund, 'WC_Order_Refund' ) ) {
				continue;
			}

			$parent_order = $refund->get_parent_id();
			$formatted_refunds[] = array(
				'id'           => $refund->get_id(),
				'parent_order' => $parent_order,
				'amount'       => (float) $refund->get_amount(),
				'reason'       => $refund->get_reason(),
				'date'         => $refund->get_date_created() ? $refund->get_date_created()->date_i18n( 'Y-m-d H:i:s' ) : null,
			);
		}

		return $formatted_refunds;
	}

	/**
	 * Get products with low stock.
	 *
	 * @param int $threshold Stock threshold.
	 * @return array
	 */
	public function get_low_stock_products( $threshold = 10 ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'limit'        => -1,
				'stock_status' => 'instock',
				'status'       => array( 'publish' ),
			)
		);

		$low_stock = array();

		foreach ( $products as $product ) {
			/* @var WC_Product $product */
			$stock_quantity = $product->get_stock_quantity();
			
			if ( null !== $stock_quantity && $stock_quantity < $threshold ) {
				$low_stock[] = array(
					'id'             => $product->get_id(),
					'name'           => $product->get_name(),
					'sku'            => $product->get_sku(),
					'stock_quantity' => $stock_quantity,
					'price'          => $product->get_price(),
					'stock_status'   => $product->get_stock_status(),
				);
			}
		}

		return $low_stock;
	}

	/**
	 * Get all products with inventory/stock levels.
	 *
	 * @param array $filters Optional filters (limit, etc.).
	 * @return array
	 */
	public function get_all_inventory_products( array $filters = array() ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array(
				'error'   => true,
				'message' => __( 'WooCommerce is not available. Please ensure WooCommerce is installed and activated.', 'dataviz-ai-woocommerce' ),
			);
		}

		$limit = isset( $filters['limit'] ) ? min( 500, max( 1, (int) $filters['limit'] ) ) : 100;

		$products = wc_get_products(
			array(
				'limit'  => $limit,
				'status' => array( 'publish' ),
			)
		);

		$inventory = array();

		foreach ( $products as $product ) {
			/* @var WC_Product $product */
			$stock_quantity = $product->get_stock_quantity();
			$stock_status   = $product->get_stock_status();
			$manage_stock   = $product->get_manage_stock();

			$inventory[] = array(
				'id'             => $product->get_id(),
				'name'           => $product->get_name(),
				'sku'            => $product->get_sku(),
				'stock_quantity' => $manage_stock ? ( $stock_quantity !== null ? $stock_quantity : 0 ) : null,
				'stock_status'   => $stock_status,
				'manage_stock'   => $manage_stock,
				'price'          => $product->get_price(),
				'backorders'     => $product->get_backorders(),
			);
		}

		return array(
			'products' => $inventory,
			'total'    => count( $inventory ),
			'message'  => sprintf(
				/* translators: %d: number of products */
				_n( 'Found %d product with inventory information.', 'Found %d products with inventory information.', count( $inventory ), 'dataviz-ai-woocommerce' ),
				count( $inventory )
			),
		);
	}

	/**
	 * Get products by category.
	 *
	 * @param int $category_id Category ID.
	 * @param int $limit       Number of products.
	 * @return array
	 */
	public function get_products_by_category( $category_id, $limit = 10 ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'limit'      => $limit,
				'category'   => array( $category_id ),
				'status'     => array( 'publish' ),
				'orderby'    => 'date',
				'order'      => 'DESC',
				'paginate'   => false,
			)
		);

		return array_map(
			static function( $product ) {
				/* @var WC_Product $product */
				return array(
					'id'          => $product->get_id(),
					'name'        => $product->get_name(),
					'total_sales' => (int) $product->get_total_sales(),
					'price'       => $product->get_price(),
					'stock'       => $product->get_stock_quantity(),
				);
			},
			$products
		);
	}
}

