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
}

