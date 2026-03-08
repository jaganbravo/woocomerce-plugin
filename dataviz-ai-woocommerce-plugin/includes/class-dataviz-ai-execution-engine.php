<?php
/**
 * Converts validated intent into internal tool calls.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Execution_Engine {
	/**
	 * Build tool calls in the same structure used by the existing handler.
	 *
	 * @param array $validated_intent Validated intent from Dataviz_AI_Intent_Validator.
	 * @return array Tool calls.
	 */
	public static function build_tool_calls( array $validated_intent ) {
		if ( empty( $validated_intent['requires_data'] ) ) {
			return array();
		}

		$entity     = $validated_intent['entity'] ?? '';
		$operation  = $validated_intent['operation'] ?? 'list';
		$metrics    = $validated_intent['metrics'] ?? array();
		$dimensions = $validated_intent['dimensions'] ?? array();
		$filters    = $validated_intent['filters'] ?? array();

		$tool_calls = array();

		// Special: top products metric.
		if ( $entity === 'products' && in_array( 'top_products', $metrics, true ) ) {
			$limit = isset( $filters['limit'] ) ? (int) $filters['limit'] : 10;
			$tool_calls[] = array(
				'function' => array(
					'name'      => 'get_top_products',
					'arguments' => wp_json_encode( array( 'limit' => min( 100, max( 1, $limit ) ) ) ),
				),
				'id'       => 'intent-top-products-' . uniqid(),
			);
			return $tool_calls;
		}

		// Orders statistics should use the dedicated tool for consistent aggregates.
		if ( $entity === 'orders' && $operation === 'statistics' ) {
			$args = array();
			if ( isset( $filters['date_from'], $filters['date_to'] ) ) {
				$args['date_from'] = $filters['date_from'];
				$args['date_to']   = $filters['date_to'];
			}

			if ( ! empty( $filters['status'] ) ) {
				$args['status'] = $filters['status'];
			}

			// group_by mapping.
			$group_by = null;
			if ( in_array( 'category', $dimensions, true ) ) {
				$group_by = 'category';
			} elseif ( in_array( 'customer', $dimensions, true ) ) {
				$group_by = 'customer';
			} elseif ( in_array( 'status', $dimensions, true ) ) {
				$group_by = 'status';
			}
			if ( $group_by ) {
				$args['group_by'] = $group_by;
			}

			$tool_calls[] = array(
				'function' => array(
					'name'      => 'get_order_statistics',
					'arguments' => wp_json_encode( $args ),
				),
				'id'       => 'intent-order-stats-' . uniqid(),
			);
			return $tool_calls;
		}

		// by_period: use flexible tool, but map time dimension to filters.period.
		if ( $entity === 'orders' && $operation === 'by_period' ) {
			$period = 'day';
			if ( in_array( 'hour', $dimensions, true ) ) {
				$period = 'hour';
			} elseif ( in_array( 'week', $dimensions, true ) ) {
				$period = 'week';
			} elseif ( in_array( 'month', $dimensions, true ) ) {
				$period = 'month';
			}
			$filters['period'] = $period;
		}

		// Resolve category_name → category_id for product queries.
		if ( $entity === 'products' && ! empty( $filters['category_name'] ) && empty( $filters['category_id'] ) ) {
			$cat_id = self::resolve_category_id( $filters['category_name'] );
			if ( $cat_id ) {
				$filters['category_id'] = $cat_id;
			}
			unset( $filters['category_name'] );
		}

		// Default: use the flexible tool.
		$tool_calls[] = array(
			'function' => array(
				'name'      => 'get_woocommerce_data',
				'arguments' => wp_json_encode(
					array(
						'entity_type' => $entity,
						'query_type'  => $operation,
						'filters'     => $filters,
					)
				),
			),
			'id'       => 'intent-' . $entity . '-' . uniqid(),
		);

		return $tool_calls;
	}

	/**
	 * Resolve a product category name/slug to its term ID.
	 *
	 * @param string $name Category name or slug.
	 * @return int|null Term ID or null if not found.
	 */
	private static function resolve_category_id( $name ) {
		if ( ! function_exists( 'get_term_by' ) ) {
			return null;
		}
		$name = trim( (string) $name );
		if ( $name === '' ) {
			return null;
		}
		// Try by name first, then slug.
		$term = get_term_by( 'name', $name, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			$term = get_term_by( 'slug', sanitize_title( $name ), 'product_cat' );
		}
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		return null;
	}
}

