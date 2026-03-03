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
}

