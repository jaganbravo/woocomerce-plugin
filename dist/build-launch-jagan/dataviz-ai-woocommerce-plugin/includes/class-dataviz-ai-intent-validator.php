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
				// Silently ignore unknown presets instead of rejecting the entire intent.
				// The PHP-side normalize_relative_date_ranges_from_question() will
				// deterministically resolve dates from the original question text.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] Ignoring unknown date preset "%s" — PHP normalization will handle dates.', $preset ) );
				}
			} else {
				$from = $range['from'];
				$to   = $range['to'];
			}
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

		// sort_by (customers statistics). Unsupported values are silently dropped instead of rejecting the entire intent.
		if ( isset( $filters_in['sort_by'] ) && $filters_in['sort_by'] !== null && $filters_in['sort_by'] !== '' ) {
			$sort_by = strtolower( sanitize_text_field( (string) $filters_in['sort_by'] ) );
			if ( in_array( $sort_by, array( 'total_spent', 'order_count' ), true ) ) {
				$filters['sort_by'] = $sort_by;
			}
			// Unsupported sort values (e.g. "location") are ignored rather than failing validation.
		}

		// group_by (customers statistics). Unsupported values are silently dropped.
		if ( isset( $filters_in['group_by'] ) && $filters_in['group_by'] !== null && $filters_in['group_by'] !== '' ) {
			$group_by = strtolower( sanitize_text_field( (string) $filters_in['group_by'] ) );
			if ( in_array( $group_by, array( 'customer', 'category' ), true ) ) {
				$filters['group_by'] = $group_by;
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

		// category_name (product by category).
		if ( isset( $filters_in['category_name'] ) && $filters_in['category_name'] !== null && $filters_in['category_name'] !== '' ) {
			$filters['category_name'] = sanitize_text_field( (string) $filters_in['category_name'] );
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

		// Normalize separators: spaces and hyphens to underscores.
		$preset = str_replace( array( ' ', '-' ), '_', trim( $preset ) );
		// Collapse multiple underscores.
		$preset = preg_replace( '/_+/', '_', $preset );

		// Normalize singular → plural for time units (last_6_month → last_6_months).
		$preset = preg_replace( '/_(day|week|month)$/', '_${1}s', $preset );

		// Handle "all_time" / "to_date" / "all" presets (no date filter).
		if ( in_array( $preset, array( 'all_time', 'to_date', 'all', 'lifetime', 'ever', 'total' ), true ) ) {
			return array( 'from' => '2000-01-01', 'to' => date( 'Y-m-d', $now ) );
		}

		// Normalize English word numbers in dynamic presets (e.g. "last_six_months" → "last_6_months").
		$word_map = array( 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14, 'fifteen' => 15, 'twenty' => 20, 'thirty' => 30, 'sixty' => 60, 'ninety' => 90 );
		$preset = preg_replace_callback(
			'/^(last|past)_([a-z]+)_(days|weeks|months)$/',
			function ( $wm ) use ( $word_map ) {
				$n = $word_map[ strtolower( $wm[2] ) ] ?? null;
				return $n !== null ? ( 'last_' . $n . '_' . $wm[3] ) : $wm[0];
			},
			$preset
		);
		// Also normalize "past_" prefix to "last_".
		$preset = preg_replace( '/^past_/', 'last_', $preset );

		// Support dynamic presets like last_30_days.
		if ( preg_match( '/^last_(\d+)_days$/', $preset, $m ) ) {
			$days = (int) $m[1];
			$days = max( 1, min( 3650, $days ) );
			return array(
				'from' => date( 'Y-m-d', $now - ( $days * DAY_IN_SECONDS ) ),
				'to'   => date( 'Y-m-d', $now ),
			);
		}

		// Support dynamic presets like last_6_months.
		if ( preg_match( '/^last_(\d+)_months$/', $preset, $m ) ) {
			$months = (int) $m[1];
			$months = max( 1, min( 120, $months ) );
			$from_ts = strtotime( sprintf( '-%d months', $months ), $now );
			return array(
				'from' => date( 'Y-m-d', $from_ts ),
				'to'   => date( 'Y-m-d', $now ),
			);
		}

		// Support dynamic presets like last_2_weeks.
		if ( preg_match( '/^last_(\d+)_weeks$/', $preset, $m ) ) {
			$weeks = (int) $m[1];
			$weeks = max( 1, min( 520, $weeks ) );
			return array(
				'from' => date( 'Y-m-d', $now - ( $weeks * 7 * DAY_IN_SECONDS ) ),
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

