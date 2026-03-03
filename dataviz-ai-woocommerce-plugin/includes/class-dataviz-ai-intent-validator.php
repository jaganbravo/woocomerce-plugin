<?php
/**
 * Validates and normalizes LLM-produced intent JSON.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Intent_Validator {
	/**
	 * Validate and normalize an intent array.
	 *
	 * @param array $intent Raw decoded intent JSON.
	 * @return array|WP_Error Normalized intent or WP_Error.
	 */
	public static function validate( array $intent ) {
		$intent_version = isset( $intent['intent_version'] ) ? (string) $intent['intent_version'] : '';
		if ( $intent_version === '' ) {
			$intent_version = '1';
		}

		$requires_data = isset( $intent['requires_data'] ) ? (bool) $intent['requires_data'] : null;
		if ( $requires_data === null ) {
			return new WP_Error( 'dataviz_ai_invalid_intent', 'Missing requires_data' );
		}

		$entity_raw = isset( $intent['entity'] ) ? (string) $intent['entity'] : '';
		$entity = strtolower( trim( $entity_raw ) );
		// Normalize common synonyms / formatting variations from the intent parser.
		$entity = str_replace( array( '-', ' ' ), '_', $entity );
		$entity_map = array(
			'order'             => 'orders',
			'orders'            => 'orders',
			'product'           => 'products',
			'products'          => 'products',
			'customer'          => 'customers',
			'customers'         => 'customers',
			'category'          => 'categories',
			'categories'        => 'categories',
			'product_category'  => 'categories',
			'product_categories'=> 'categories',
			'tag'               => 'tags',
			'tags'              => 'tags',
			'coupon'            => 'coupons',
			'coupons'           => 'coupons',
			'refund'            => 'refunds',
			'refunds'           => 'refunds',
		);
		if ( isset( $entity_map[ $entity ] ) ) {
			$entity = $entity_map[ $entity ];
		}
		$allowed_entities = array( 'orders', 'products', 'customers', 'categories', 'tags', 'coupons', 'refunds', 'stock', 'inventory' );
		// Safety net: if model picked a supported non-orders entity, it is almost certainly a data question.
		// This corrects cases like entity="categories" but requires_data=false.
		if ( ! $requires_data && $entity !== '' && $entity !== 'orders' && in_array( $entity, $allowed_entities, true ) ) {
			$requires_data = true;
		}

		if ( $requires_data && ( $entity === '' || ! in_array( $entity, $allowed_entities, true ) ) ) {
			return new WP_Error( 'dataviz_ai_invalid_intent', 'Invalid entity' );
		}

		$operation = isset( $intent['operation'] ) ? strtolower( trim( (string) $intent['operation'] ) ) : '';
		$operation_map = array(
			'get'    => 'list',
			'fetch'  => 'list',
			'show'   => 'list',
			'count'  => 'statistics',
			'totals' => 'statistics',
		);
		if ( isset( $operation_map[ $operation ] ) ) {
			$operation = $operation_map[ $operation ];
		}
		$allowed_operations = array( 'list', 'statistics', 'by_period', 'sample' );
		if ( $requires_data && ( $operation === '' || ! in_array( $operation, $allowed_operations, true ) ) ) {
			// Be forgiving: default to list instead of rejecting the entire intent.
			// This avoids unnecessary "intent invalid" fallbacks for benign parser mistakes.
			$operation = 'list';
		}

		$confidence = isset( $intent['confidence'] ) ? strtolower( (string) $intent['confidence'] ) : 'low';
		if ( ! in_array( $confidence, array( 'low', 'medium', 'high' ), true ) ) {
			$confidence = 'low';
		}

		$metrics = isset( $intent['metrics'] ) && is_array( $intent['metrics'] ) ? array_values( $intent['metrics'] ) : array();
		$metrics = array_map(
			static function ( $m ) {
				return strtolower( trim( (string) $m ) );
			},
			$metrics
		);
		$metrics = array_values( array_filter( array_unique( $metrics ) ) );

		$dimensions = isset( $intent['dimensions'] ) && is_array( $intent['dimensions'] ) ? array_values( $intent['dimensions'] ) : array();
		$dimensions = array_map(
			static function ( $d ) {
				return strtolower( trim( (string) $d ) );
			},
			$dimensions
		);
		$dimensions = array_values( array_filter( array_unique( $dimensions ) ) );

		$filters_in = isset( $intent['filters'] ) && is_array( $intent['filters'] ) ? $intent['filters'] : array();
		$filters = array();

		// date_range: supports preset or explicit from/to.
		$date_range = isset( $filters_in['date_range'] ) && is_array( $filters_in['date_range'] ) ? $filters_in['date_range'] : array();
		$preset = isset( $date_range['preset'] ) ? strtolower( (string) $date_range['preset'] ) : null;
		$from   = isset( $date_range['from'] ) ? (string) $date_range['from'] : null;
		$to     = isset( $date_range['to'] ) ? (string) $date_range['to'] : null;

		if ( $preset ) {
			$range = self::resolve_date_preset( $preset );
			if ( is_wp_error( $range ) ) {
				return $range;
			}
			$from = $range['from'];
			$to   = $range['to'];
		}

		if ( ( $from && ! self::is_valid_date( $from ) ) || ( $to && ! self::is_valid_date( $to ) ) ) {
			return new WP_Error( 'dataviz_ai_invalid_intent', 'Invalid date_range' );
		}
		if ( ( $from && ! $to ) || ( $to && ! $from ) ) {
			return new WP_Error( 'dataviz_ai_invalid_intent', 'date_range requires both from and to' );
		}
		if ( $from && $to ) {
			$filters['date_from'] = $from;
			$filters['date_to']   = $to;
		}

		// status filter (orders).
		if ( isset( $filters_in['status'] ) && $filters_in['status'] !== null && $filters_in['status'] !== '' ) {
			$filters['status'] = sanitize_text_field( (string) $filters_in['status'] );
		}

		// limit.
		if ( isset( $filters_in['limit'] ) && $filters_in['limit'] !== null && $filters_in['limit'] !== '' ) {
			$filters['limit'] = max( -1, min( 1000, (int) $filters_in['limit'] ) );
		}

		// sort_by (customers statistics).
		if ( isset( $filters_in['sort_by'] ) && $filters_in['sort_by'] !== null && $filters_in['sort_by'] !== '' ) {
			$sort_by = strtolower( sanitize_text_field( (string) $filters_in['sort_by'] ) );
			if ( in_array( $sort_by, array( 'total_spent', 'order_count' ), true ) ) {
				$filters['sort_by'] = $sort_by;
			} else {
				return new WP_Error( 'dataviz_ai_invalid_intent', 'Invalid sort_by' );
			}
		}

		// group_by (customers statistics).
		if ( isset( $filters_in['group_by'] ) && $filters_in['group_by'] !== null && $filters_in['group_by'] !== '' ) {
			$group_by = strtolower( sanitize_text_field( (string) $filters_in['group_by'] ) );
			if ( in_array( $group_by, array( 'customer', 'category' ), true ) ) {
				$filters['group_by'] = $group_by;
			} else {
				return new WP_Error( 'dataviz_ai_invalid_intent', 'Invalid group_by' );
			}
		}

		// min_orders (customers statistics).
		if ( isset( $filters_in['min_orders'] ) && $filters_in['min_orders'] !== null && $filters_in['min_orders'] !== '' ) {
			$filters['min_orders'] = max( 0, (int) $filters_in['min_orders'] );
		}

		// stock_status (stock).
		if ( isset( $filters_in['stock_status'] ) && $filters_in['stock_status'] !== null && $filters_in['stock_status'] !== '' ) {
			$stock_status = strtolower( (string) $filters_in['stock_status'] );
			if ( in_array( $stock_status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
				$filters['stock_status'] = $stock_status;
			} else {
				return new WP_Error( 'dataviz_ai_invalid_intent', 'Invalid stock_status' );
			}
		}

		// stock_threshold (low stock).
		if ( isset( $filters_in['stock_threshold'] ) && $filters_in['stock_threshold'] !== null && $filters_in['stock_threshold'] !== '' ) {
			$filters['stock_threshold'] = max( 0, (int) $filters_in['stock_threshold'] );
		}

		$draft_answer = isset( $intent['draft_answer'] ) && is_string( $intent['draft_answer'] ) ? $intent['draft_answer'] : null;
		if ( is_string( $draft_answer ) ) {
			$draft_answer = trim( $draft_answer );
			if ( $draft_answer === '' ) {
				$draft_answer = null;
			}
		}

		return array(
			'intent_version' => $intent_version,
			'requires_data'  => $requires_data,
			'entity'         => $entity,
			'operation'      => $operation,
			'metrics'        => $metrics,
			'dimensions'     => $dimensions,
			'filters'        => $filters,
			'confidence'     => $confidence,
			'draft_answer'   => $draft_answer,
		);
	}

	private static function is_valid_date( $date ) {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date );
	}

	/**
	 * Resolve common date presets to concrete YYYY-MM-DD ranges in WP timezone.
	 *
	 * @param string $preset Preset string.
	 * @return array|WP_Error
	 */
	private static function resolve_date_preset( $preset ) {
		$now = current_time( 'timestamp' );

		// Support dynamic presets like last_30_days.
		if ( preg_match( '/^last_(\d+)_days$/', $preset, $m ) ) {
			$days = (int) $m[1];
			$days = max( 1, min( 3650, $days ) );
			return array(
				'from' => date( 'Y-m-d', $now - ( $days * DAY_IN_SECONDS ) ),
				'to'   => date( 'Y-m-d', $now ),
			);
		}

		switch ( $preset ) {
			case 'today':
				return array( 'from' => date( 'Y-m-d', $now ), 'to' => date( 'Y-m-d', $now ) );
			case 'yesterday':
				$y = $now - DAY_IN_SECONDS;
				return array( 'from' => date( 'Y-m-d', $y ), 'to' => date( 'Y-m-d', $y ) );
			case 'this_week':
				$week_start = strtotime( 'monday this week', $now );
				return array( 'from' => date( 'Y-m-d', $week_start ), 'to' => date( 'Y-m-d', $now ) );
			case 'last_week':
				$last_week_start = strtotime( 'monday last week', $now );
				$last_week_end   = strtotime( 'sunday last week', $now );
				return array( 'from' => date( 'Y-m-d', $last_week_start ), 'to' => date( 'Y-m-d', $last_week_end ) );
			case 'this_month':
				return array( 'from' => date( 'Y-m-01', $now ), 'to' => date( 'Y-m-d', $now ) );
			case 'last_month':
				$current_year  = (int) current_time( 'Y' );
				$current_month = (int) current_time( 'm' );
				if ( $current_month === 1 ) {
					$last_month = 12;
					$last_year  = $current_year - 1;
				} else {
					$last_month = $current_month - 1;
					$last_year  = $current_year;
				}
				$from = sprintf( '%04d-%02d-01', $last_year, $last_month );
				$last_day_timestamp = strtotime( $from . ' +1 month -1 day' );
				return array( 'from' => $from, 'to' => date( 'Y-m-d', $last_day_timestamp ) );
			case 'this_year':
				return array( 'from' => date( 'Y-01-01', $now ), 'to' => date( 'Y-m-d', $now ) );
			case 'last_year':
				$last_year = (int) current_time( 'Y' ) - 1;
				return array( 'from' => $last_year . '-01-01', 'to' => $last_year . '-12-31' );
			case 'last_quarter':
				$current_year  = (int) current_time( 'Y' );
				$current_month = (int) current_time( 'm' );
				$current_quarter = (int) floor( ( $current_month - 1 ) / 3 ) + 1;
				$last_quarter = $current_quarter - 1;
				$year = $current_year;
				if ( $last_quarter < 1 ) {
					$last_quarter = 4;
					$year = $current_year - 1;
				}
				$start_month = ( ( $last_quarter - 1 ) * 3 ) + 1;
				$from = sprintf( '%04d-%02d-01', $year, $start_month );
				$last_day_timestamp = strtotime( $from . ' +3 months -1 day' );
				return array( 'from' => $from, 'to' => date( 'Y-m-d', $last_day_timestamp ) );
			default:
				return new WP_Error( 'dataviz_ai_invalid_intent', 'Unknown date preset' );
		}
	}
}

