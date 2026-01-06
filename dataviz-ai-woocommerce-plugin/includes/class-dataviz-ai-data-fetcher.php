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

		// Merge args with defaults (args take precedence)
		$query_args = wp_parse_args( $args, $defaults );
		
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( '[Dataviz AI] get_recent_orders called with args: %s', wp_json_encode( $args ) ) );
			error_log( sprintf( '[Dataviz AI] get_recent_orders query_args: %s', wp_json_encode( $query_args ) ) );
		}

		$orders = wc_get_orders( $query_args );
		
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( '[Dataviz AI] get_recent_orders returned %d orders', count( $orders ) ) );
		}

		return $orders;
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
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array(
				'summary'         => array(
					'total_orders'      => 0,
					'total_revenue'     => 0,
					'avg_order_value'   => 0,
					'min_order_value'   => 0,
					'max_order_value'   => 0,
					'unique_customers'  => 0,
				),
				'status_breakdown' => array(),
				'daily_trend'      => array(),
				'date_range'       => array(
					'from' => null,
					'to'   => null,
				),
			);
		}

		$date_from = isset( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : null;
		$date_to   = isset( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : null;
		$status    = isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : null;

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( '[Dataviz AI] get_order_statistics called with args: %s', wp_json_encode( $args ) ) );
			error_log( sprintf( '[Dataviz AI] Using wc_get_orders() instead of direct SQL' ) );
		}

		// Build query args for wc_get_orders - WooCommerce handles status automatically
		$query_args = array(
			'limit'  => -1, // Get all matching orders
		);

		// Add status filter - WooCommerce handles the 'wc-' prefix automatically
		if ( $status ) {
			$query_args['status'] = array( $status );
		}

		// Add date filters
		if ( $date_from || $date_to ) {
			$date_query = array();
			if ( $date_from ) {
				$date_query['after'] = $date_from . ' 00:00:00';
			}
		if ( $date_to ) {
				$date_query['before'] = $date_to . ' 23:59:59';
			}
			$query_args['date_created'] = $date_query;
		}

		// Get orders using WooCommerce API - this handles all the complexity automatically
		$orders = wc_get_orders( $query_args );

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( '[Dataviz AI] Found %d orders using wc_get_orders()', count( $orders ) ) );
		}

		// Calculate statistics from order objects
		$total_orders = count( $orders );
		$total_revenue = 0;
		$order_values = array();
		$customer_ids = array();
		$status_counts = array();
		$daily_revenue = array();

		foreach ( $orders as $order ) {
			$order_total = (float) $order->get_total();
			$total_revenue += $order_total;
			$order_values[] = $order_total;

			$customer_id = $order->get_customer_id();
			if ( $customer_id ) {
				$customer_ids[ $customer_id ] = true;
			}

			$order_status = $order->get_status();
			if ( ! isset( $status_counts[ $order_status ] ) ) {
				$status_counts[ $order_status ] = array(
					'count'   => 0,
					'revenue' => 0,
				);
			}
			$status_counts[ $order_status ]['count']++;
			$status_counts[ $order_status ]['revenue'] += $order_total;

			// Daily trend
			$order_date = $order->get_date_created();
			if ( $order_date ) {
				$date_key = $order_date->date( 'Y-m-d' );
				if ( ! isset( $daily_revenue[ $date_key ] ) ) {
					$daily_revenue[ $date_key ] = array(
						'order_count' => 0,
						'revenue'     => 0,
					);
				}
				$daily_revenue[ $date_key ]['order_count']++;
				$daily_revenue[ $date_key ]['revenue'] += $order_total;
			}
		}

		// Calculate averages
		$avg_order_value = $total_orders > 0 ? ( $total_revenue / $total_orders ) : 0;
		$min_order_value = ! empty( $order_values ) ? min( $order_values ) : 0;
		$max_order_value = ! empty( $order_values ) ? max( $order_values ) : 0;
		$unique_customers = count( $customer_ids );

		// Format status breakdown
		$status_breakdown = array();
		foreach ( $status_counts as $status_name => $data ) {
			$status_breakdown[] = array(
				'status'  => $status_name,
				'count'   => $data['count'],
				'revenue' => $data['revenue'],
			);
		}

		// Format daily trend (last 30 days or date range)
		$trend_days = 30;
		if ( $date_from && $date_to ) {
			$days_diff = ( strtotime( $date_to ) - strtotime( $date_from ) ) / DAY_IN_SECONDS;
			$trend_days = min( 90, max( 7, (int) $days_diff ) );
		}

		// Sort daily trend by date and limit
		ksort( $daily_revenue );
		$daily_trend = array_slice( array_map( function( $date, $data ) {
			return array(
				'date'        => $date,
				'order_count' => $data['order_count'],
				'revenue'     => $data['revenue'],
			);
		}, array_keys( $daily_revenue ), $daily_revenue ), -$trend_days );

		return array(
			'summary'         => array(
				'total_orders'      => $total_orders,
				'total_revenue'     => $total_revenue,
				'avg_order_value'   => $avg_order_value,
				'min_order_value'   => $min_order_value,
				'max_order_value'   => $max_order_value,
				'unique_customers'  => $unique_customers,
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
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$sample_size = isset( $args['sample_size'] ) ? (int) $args['sample_size'] : 100;
		$date_from   = isset( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : null;
		$date_to     = isset( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : null;
		$status      = isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : null;

		// Build query args for wc_get_orders
		$wc_args = array(
			'limit'    => -1, // Get all matching orders first
			'orderby'  => 'date',
			'order'    => 'DESC',
			'return'   => 'objects',
		);

		if ( $status ) {
			$wc_args['status'] = $status; // wc_get_orders handles 'wc-' prefix internally
		}

		if ( $date_from && $date_to ) {
			$wc_args['date_created'] = $date_from . '...' . $date_to;
		} elseif ( $date_from ) {
			$wc_args['date_created'] = '>=' . $date_from;
		} elseif ( $date_to ) {
			$wc_args['date_created'] = '<=' . $date_to;
		}

		// Get all matching orders using WooCommerce API
		$all_orders = wc_get_orders( $wc_args );

		if ( empty( $all_orders ) ) {
			return array();
		}

		$total_count = count( $all_orders );

		// If total is less than sample size, just return all.
		if ( $total_count <= $sample_size ) {
			return $all_orders;
		}

		// Use systematic sampling for large datasets.
		// Calculate sampling interval to get representative sample.
		$sampling_interval = floor( $total_count / $sample_size );

		// Sample orders at regular intervals
		$sampled_orders = array();
		for ( $i = 0; $i < $total_count; $i += $sampling_interval ) {
			if ( isset( $all_orders[ $i ] ) ) {
				$sampled_orders[] = $all_orders[ $i ];
			}
			// Stop if we have enough samples
			if ( count( $sampled_orders ) >= $sample_size ) {
				break;
			}
		}

		return $sampled_orders;
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
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$date_from = isset( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : null;
		$date_to   = isset( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : null;
		$status    = isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : null;

		// Validate period
		$valid_periods = array( 'hour', 'day', 'week', 'month' );
		if ( ! in_array( $period, $valid_periods, true ) ) {
			$period = 'day';
		}

		// Build query args for wc_get_orders
		$wc_args = array(
			'limit'    => -1, // Get all matching orders for aggregation
			'orderby'  => 'date',
			'order'    => 'DESC',
			'return'   => 'objects',
		);

		if ( $status ) {
			$wc_args['status'] = $status; // wc_get_orders handles 'wc-' prefix internally
		}

		if ( $date_from && $date_to ) {
			$wc_args['date_created'] = $date_from . '...' . $date_to;
		} elseif ( $date_from ) {
			$wc_args['date_created'] = '>=' . $date_from;
		} elseif ( $date_to ) {
			$wc_args['date_created'] = '<=' . $date_to;
		}

		// Get all matching orders using WooCommerce API
		$orders = wc_get_orders( $wc_args );

		if ( empty( $orders ) ) {
			return array();
		}

		// Aggregate orders by period in PHP
		$aggregated = array();

		foreach ( $orders as $order ) {
			$order_date = $order->get_date_created();
			if ( ! $order_date ) {
				continue;
			}

			// Format period key based on period type
			switch ( $period ) {
				case 'hour':
					$period_key = $order_date->date( 'Y-m-d H:00:00' );
					break;
				case 'day':
					$period_key = $order_date->date( 'Y-m-d' );
					break;
				case 'week':
					// Format: YYYY-WW (week number)
					$week_number = $order_date->format( 'W' );
					$year = $order_date->format( 'Y' );
					$period_key = $year . '-' . $week_number;
					break;
				case 'month':
					$period_key = $order_date->date( 'Y-m' );
					break;
				default:
					$period_key = $order_date->date( 'Y-m-d' );
			}

			// Initialize period if not exists
			if ( ! isset( $aggregated[ $period_key ] ) ) {
				$aggregated[ $period_key ] = array(
					'period'         => $period_key,
					'order_count'    => 0,
					'revenue'        => 0.0,
					'order_values'   => array(), // For calculating average
				);
			}

			// Aggregate data
			$order_total = (float) $order->get_total();
			$aggregated[ $period_key ]['order_count']++;
			$aggregated[ $period_key ]['revenue'] += $order_total;
			$aggregated[ $period_key ]['order_values'][] = $order_total;
		}

		// Calculate average order value and format result
		$result = array();
		foreach ( $aggregated as $period_key => $data ) {
			$avg_order_value = count( $data['order_values'] ) > 0
				? array_sum( $data['order_values'] ) / count( $data['order_values'] )
				: 0.0;

			$result[] = array(
				'period'         => $data['period'],
				'order_count'    => $data['order_count'],
				'revenue'        => $data['revenue'],
				'avg_order_value' => $avg_order_value,
			);
		}

		// Sort by period descending (most recent first)
		usort( $result, function( $a, $b ) {
			return strcmp( $b['period'], $a['period'] );
		} );

		return $result;
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

