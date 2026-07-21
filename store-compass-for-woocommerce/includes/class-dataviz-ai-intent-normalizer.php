<?php
/**
 * PHP-side intent normalization and question guard detection.
 *
 * Pure-function class (stateless, static methods) extracted from the AJAX handler
 * to consolidate all PHP-level intent overrides in a single location.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Intent_Normalizer {

	/**
	 * Normalize relative date ranges from the original question (PHP source of truth).
	 * This prevents stale/hallucinated date ranges from the intent parser.
	 *
	 * @param string $question         User question.
	 * @param array  $validated_intent Validated intent.
	 * @return array
	 */
	public static function normalize_relative_date_ranges( $question, array $validated_intent ) {
		if ( preg_match( '/\b(?:in\s+the\s+)?last\s+(\d+)\s+days\b/i', (string) $question, $m ) ) {
			$days = (int) $m[1];
			$days = max( 1, min( 3650, $days ) );

			$current_year = (int) current_time( 'Y' );
			$from_year = null;
			if ( isset( $validated_intent['filters']['date_from'] ) && is_string( $validated_intent['filters']['date_from'] ) ) {
				$from_year = (int) substr( $validated_intent['filters']['date_from'], 0, 4 );
			}

			$needs_override = false;
			if ( empty( $validated_intent['filters']['date_from'] ) || empty( $validated_intent['filters']['date_to'] ) ) {
				$needs_override = true;
			} elseif ( $from_year !== null && $from_year < ( $current_year - 1 ) ) {
				$needs_override = true;
			}

			if ( $needs_override ) {
				$now = current_time( 'timestamp' );
				$validated_intent['filters']['date_from'] = wp_date( 'Y-m-d', $now - ( $days * DAY_IN_SECONDS ) );
				$validated_intent['filters']['date_to']   = wp_date( 'Y-m-d', $now );
			}
		}

		$q_months = (string) $question;
		$q_months = preg_replace_callback(
			'/\blast\s+(one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\s+months?\b/i',
			function ( $wm ) {
				$map = array( 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12 );
				$n = $map[ strtolower( $wm[1] ) ] ?? 0;
				return 'last ' . $n . ' months';
			},
			$q_months
		);
		if ( preg_match( '/\b(?:in\s+the\s+)?last\s+(\d+)\s+months?\b/i', $q_months, $m ) ) {
			$months = (int) $m[1];
			$months = max( 1, min( 120, $months ) );

			$needs_override = empty( $validated_intent['filters']['date_from'] ) || empty( $validated_intent['filters']['date_to'] );
			if ( ! $needs_override ) {
				$current_year = (int) current_time( 'Y' );
				$from_year = (int) substr( (string) ( $validated_intent['filters']['date_from'] ?? '' ), 0, 4 );
				if ( $from_year < ( $current_year - 1 ) ) {
					$needs_override = true;
				}
			}
			if ( $needs_override ) {
				$now    = current_time( 'timestamp' );
				$from_ts = strtotime( sprintf( '-%d months', $months ), $now );
				$validated_intent['filters']['date_from'] = wp_date( 'Y-m-d', $from_ts );
				$validated_intent['filters']['date_to']   = wp_date( 'Y-m-d', $now );
			}
		}

		$q_weeks = (string) $question;
		$q_weeks = preg_replace_callback(
			'/\blast\s+(one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\s+weeks?\b/i',
			function ( $wm ) {
				$map = array( 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12 );
				$n = $map[ strtolower( $wm[1] ) ] ?? 0;
				return 'last ' . $n . ' weeks';
			},
			$q_weeks
		);
		if ( preg_match( '/\b(?:in\s+the\s+)?last\s+(\d+)\s+weeks?\b/i', $q_weeks, $m ) ) {
			$weeks = (int) $m[1];
			$weeks = max( 1, min( 520, $weeks ) );

			$needs_override = empty( $validated_intent['filters']['date_from'] ) || empty( $validated_intent['filters']['date_to'] );
			if ( ! $needs_override ) {
				$current_year = (int) current_time( 'Y' );
				$from_year = (int) substr( (string) ( $validated_intent['filters']['date_from'] ?? '' ), 0, 4 );
				if ( $from_year < ( $current_year - 1 ) ) {
					$needs_override = true;
				}
			}
			if ( $needs_override ) {
				$now = current_time( 'timestamp' );
				$validated_intent['filters']['date_from'] = wp_date( 'Y-m-d', $now - ( $weeks * 7 * DAY_IN_SECONDS ) );
				$validated_intent['filters']['date_to']   = wp_date( 'Y-m-d', $now );
			}
		}

		if ( preg_match( '/\bthis\s+week\b/i', (string) $question ) ) {
			$needs_override = empty( $validated_intent['filters']['date_from'] ) || empty( $validated_intent['filters']['date_to'] );
			if ( $needs_override ) {
				$now = current_time( 'timestamp' );
				$week_start = strtotime( 'monday this week', $now );
				$validated_intent['filters']['date_from'] = wp_date( 'Y-m-d', $week_start );
				$validated_intent['filters']['date_to']   = wp_date( 'Y-m-d', $now );
			}
		}

		if ( preg_match( '/\bthis\s+year\b/i', (string) $question ) ) {
			$needs_override = empty( $validated_intent['filters']['date_from'] ) || empty( $validated_intent['filters']['date_to'] );
			if ( $needs_override ) {
				$now = current_time( 'timestamp' );
				$validated_intent['filters']['date_from'] = wp_date( 'Y-01-01', $now );
				$validated_intent['filters']['date_to']   = wp_date( 'Y-m-d', $now );
			}
		}

		if ( preg_match( '/\bthis\s+month\b/i', (string) $question ) ) {
			$needs_override = empty( $validated_intent['filters']['date_from'] ) || empty( $validated_intent['filters']['date_to'] );
			if ( $needs_override ) {
				$now = current_time( 'timestamp' );
				$validated_intent['filters']['date_from'] = wp_date( 'Y-m-01', $now );
				$validated_intent['filters']['date_to']   = wp_date( 'Y-m-d', $now );
			}
		}

		if ( preg_match( '/\blast\s+month\b/i', (string) $question ) && ! preg_match( '/\blast\s+\d+\s+months?\b/i', (string) $question ) ) {
			$needs_override = empty( $validated_intent['filters']['date_from'] ) || empty( $validated_intent['filters']['date_to'] );
			if ( $needs_override ) {
				$current_year  = (int) current_time( 'Y' );
				$current_month = (int) current_time( 'm' );
				if ( $current_month === 1 ) {
					$lm = 12;
					$ly = $current_year - 1;
				} else {
					$lm = $current_month - 1;
					$ly = $current_year;
				}
				$from = sprintf( '%04d-%02d-01', $ly, $lm );
				$validated_intent['filters']['date_from'] = $from;
				$validated_intent['filters']['date_to']   = wp_date( 'Y-m-d', strtotime( $from . ' +1 month -1 day' ) );
			}
		}

		if ( preg_match( '/\blast\s+quarter\b/i', (string) $question ) ) {
			$has_dates = ! empty( $validated_intent['filters']['date_from'] ) && ! empty( $validated_intent['filters']['date_to'] );
			$current_year = (int) current_time( 'Y' );
			$from_year = null;
			if ( isset( $validated_intent['filters']['date_from'] ) && is_string( $validated_intent['filters']['date_from'] ) ) {
				$from_year = (int) substr( $validated_intent['filters']['date_from'], 0, 4 );
			}

			if ( ! $has_dates || ( $from_year !== null && $from_year < ( $current_year - 1 ) ) ) {
				$range = Dataviz_AI_Intent_Validator::validate(
					array(
						'intent_version' => '1',
						'requires_data'  => true,
						'entity'         => 'orders',
						'operation'      => 'statistics',
						'metrics'        => array(),
						'dimensions'     => array(),
						'filters'        => array(
							'date_range' => array(
								'preset' => 'last_quarter',
								'from'   => null,
								'to'     => null,
							),
						),
						'confidence'     => 'low',
						'draft_answer'   => null,
					)
				);

				if ( is_array( $range ) && ! empty( $range['filters']['date_from'] ) && ! empty( $range['filters']['date_to'] ) ) {
					$validated_intent['filters']['date_from'] = $range['filters']['date_from'];
					$validated_intent['filters']['date_to']   = $range['filters']['date_to'];
				} else {
					$now = current_time( 'timestamp' );
					$current_month = (int) current_time( 'm' );
					$current_quarter = (int) floor( ( $current_month - 1 ) / 3 ) + 1;
					$last_quarter = $current_quarter - 1;
					$year = (int) current_time( 'Y' );
					if ( $last_quarter < 1 ) {
						$last_quarter = 4;
						$year = $year - 1;
					}
					$start_month = ( ( $last_quarter - 1 ) * 3 ) + 1;
					$from = sprintf( '%04d-%02d-01', $year, $start_month );
					$last_day_timestamp = strtotime( $from . ' +3 months -1 day' );
					$validated_intent['filters']['date_from'] = $from;
					$validated_intent['filters']['date_to']   = wp_date( 'Y-m-d', $last_day_timestamp );
				}
			}
		}

		return $validated_intent;
	}

	/**
	 * Normalize high-level intent from question when the intent parser misclassifies.
	 * PHP remains source of truth for critical routing decisions.
	 *
	 * Uses weighted rule scoring: every rule is evaluated and given a confidence
	 * score. The highest-scoring rule wins. This eliminates ordering dependencies
	 * and false-positive short-circuits that plagued the previous if/return chain.
	 *
	 * @param string $question         Question.
	 * @param array  $validated_intent Validated intent.
	 * @return array
	 */
	public static function normalize_intent_from_question( $question, array $validated_intent ) {
		$q = (string) $question;

		$rules = array(
			'try_revenue_to_date',
			'try_inventory_distribution',
			'try_coupon_usage',
			'try_tag_count',
			'try_out_of_stock',
			'try_sales_by_category',
			'try_categories_listing',
			'try_products_under_category',
			'try_orders_status_list',
			'try_order_status_count',
			'try_refunds',
			'try_customer_count',
			'try_order_by_status',
			'try_top_customers',
			'try_top_selling_products',
			'try_monthly_chart',
			'try_daily_chart',
		);

		$best_score  = 0;
		$best_intent = null;

		foreach ( $rules as $method ) {
			$candidate = self::$method( $q, $validated_intent );
			if ( null !== $candidate && $candidate['score'] > $best_score ) {
				$best_score  = $candidate['score'];
				$best_intent = $candidate['intent'];
			}
		}

		return null !== $best_intent ? $best_intent : $validated_intent;
	}

	// ------------------------------------------------------------------
	// Scored normalization rules
	//
	// Each returns [ 'score' => int, 'intent' => array ] or null.
	// Higher score = more specific match. Minimum threshold is enforced
	// inside each rule so only genuine matches produce a candidate.
	// ------------------------------------------------------------------

	private static function try_revenue_to_date( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\b(revenue|sales)\b/i', $q ) )  $s += 30;
		if ( preg_match( '/\bto\s+date\b/i', $q ) )        $s += 40;
		if ( $s < 70 ) return null;

		$intent['entity']    = 'orders';
		$intent['operation'] = 'statistics';
		unset( $intent['filters']['date_from'], $intent['filters']['date_to'] );
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_inventory_distribution( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\binventory\b/i', $q ) )              $s += 40;
		if ( preg_match( '/\b(distribution|across|by)\b/i', $q ) ) $s += 20;
		if ( preg_match( '/\bcategor/i', $q ) )                   $s += 10;
		if ( $s < 60 ) return null;

		$intent['entity']    = 'inventory';
		$intent['operation'] = 'list';
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_coupon_usage( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\bcoupons?\b/i', $q ) )       $s += 40;
		if ( preg_match( '/\bused\b/i', $q ) )           $s += 20;
		if ( preg_match( '/\blast\s+month\b/i', $q ) )   $s += 10;
		if ( $s < 60 ) return null;

		$intent['entity']    = 'coupons';
		$intent['operation'] = 'statistics';
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
		$intent['filters']['date_from'] = $from;
		$intent['filters']['date_to']   = wp_date( 'Y-m-d', $last_day_timestamp );
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_tag_count( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\b(how\s+many|count|number\s+of)\b/i', $q ) ) $s += 20;
		if ( preg_match( '/\bproducts?\b/i', $q ) )                      $s += 10;
		if ( preg_match( '/\btags?\b/i', $q ) )                          $s += 40;
		if ( $s < 60 ) return null;

		$intent['entity']    = 'tags';
		$intent['operation'] = 'list';
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_out_of_stock( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\b(out\s+of\s+stock|outofstock)\b/i', $q ) ) $s += 60;
		if ( preg_match( '/\b(products?|list|provide|show)\b/i', $q ) ) $s += 10;
		if ( $s < 60 ) return null;

		$intent['entity'] = 'stock';
		$intent['filters']['stock_status'] = 'outofstock';
		unset( $intent['filters']['category_name'] );
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_sales_by_category( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\b(pie\s+chart|bar\s+chart|graph)\b/i', $q ) ) $s += 20;
		if ( preg_match( '/\b(sales?|revenue)\b/i', $q ) )                $s += 30;
		if ( preg_match( '/\bcategor(y|ies)\b/i', $q ) )                  $s += 20;
		if ( preg_match( '/\bby\s+(product\s+)?categor/i', $q ) )         $s += 15;
		if ( $s < 50 ) return null;

		$intent['entity']    = 'orders';
		$intent['operation'] = 'statistics';
		$intent['filters']['group_by'] = 'category';
		if ( ! in_array( 'category', $intent['dimensions'] ?? array(), true ) ) {
			$intent['dimensions'][] = 'category';
		}
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_categories_listing( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\bcategor(y|ies)\b/i', $q ) )  $s += 25;
		if ( preg_match( '/\bproducts?\b/i', $q ) )       $s += 10;
		if ( preg_match( '/\b(belong|what|list|show)\b/i', $q ) ) $s += 10;
		if ( preg_match( '/\bunder\b|\bfrom\b|\bin\s+the\b/i', $q ) ) $s -= 30;
		if ( $s < 30 ) return null;

		$intent['entity']    = 'categories';
		$intent['operation'] = 'list';
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_products_under_category( $q, array $intent ) {
		$s = 0;
		if ( ! preg_match( '/\bproducts?\b/i', $q ) ) return null;

		$s += 10;
		if ( preg_match( '/\b(under|in|from)\s+(the\s+)?[\'"]?([A-Za-z][A-Za-z0-9 &-]{1,40})[\'"]?\s*(category|categories)\b/i', $q, $cat_m ) ) {
			$s += 30;
			if ( preg_match( '/\bcategory\b/i', $q ) ) $s += 10;
		} else {
			return null;
		}

		$cat_name = trim( $cat_m[3] );
		if ( $cat_name === '' ) return null;

		$intent['entity']    = 'products';
		$intent['operation'] = 'list';
		$intent['filters']['category_name'] = $cat_name;
		if ( empty( $intent['filters']['limit'] ) ) {
			$intent['filters']['limit'] = -1;
		}
		return array( 'score' => $s, 'intent' => $intent );
	}

	/**
	 * "Show/list … pending orders" must route to a status-filtered list, not generic recent orders.
	 * Skips pure count questions (handled by try_order_status_count).
	 */
	private static function try_orders_status_list( $q, array $intent ) {
		if ( preg_match( '/\b(how\s+many|count|number\s+of)\b/i', $q ) ) {
			return null;
		}
		$s = 0;
		if ( preg_match( '/\b(show|list|lists?|display|see|view|fetch|pull\s+up|print)\b/i', $q ) ) {
			$s += 25;
		}
		if ( preg_match( '/\b(give|send)\s+(me\s+)?/i', $q ) ) {
			$s += 20;
		}
		if ( preg_match( '/\b(orders?)\b/i', $q ) ) {
			$s += 20;
		}
		if ( preg_match( '/\b(pending|processing|completed|on[\s-]?hold|cancelled|canceled|refunded|failed)\b/i', $q, $st_m ) ) {
			$s += 35;
		}
		if ( $s < 65 ) {
			return null;
		}

		$intent['entity']    = 'orders';
		$intent['operation'] = 'list';
		$raw_status = strtolower( $st_m[1] );
		$raw_status          = preg_replace( '/^on\s+hold$/', 'on-hold', $raw_status );
		$raw_status          = str_replace( 'canceled', 'cancelled', $raw_status );
		$intent['filters']['status'] = $raw_status;
		if ( preg_match( '/\b(all|every|full|complete|entire)\b/i', $q ) ) {
			$intent['filters']['limit'] = -1;
		}
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_order_status_count( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\b(how\s+many|count|number\s+of|total)\b/i', $q ) ) $s += 20;
		if ( preg_match( '/\b(orders?)\b/i', $q ) )                            $s += 15;
		if ( preg_match( '/\b(pending|processing|completed|on[\s-]?hold|cancelled|canceled|refunded|failed)\b/i', $q, $st_m ) ) $s += 35;
		if ( $s < 60 ) return null;

		$intent['entity']    = 'orders';
		$intent['operation'] = 'statistics';
		$raw_status = strtolower( $st_m[1] );
		$raw_status = preg_replace( '/^on\s+hold$/', 'on-hold', $raw_status );
		$raw_status = str_replace( 'canceled', 'cancelled', $raw_status );
		$intent['filters']['status'] = $raw_status;
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_refunds( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\b(refund|refunds)\b/i', $q ) )  $s += 50;
		elseif ( preg_match( '/\breturns?\b/i', $q ) )      $s += 40;
		if ( $s < 40 ) return null;

		if ( preg_match( '/\b(how\s+many|count|total|statistics?)\b/i', $q ) ) $s += 10;

		$intent['entity']    = 'refunds';
		$intent['operation'] = 'list';

		if ( preg_match( '/\bthis\s+year\b/i', $q ) ) {
			$now = current_time( 'timestamp' );
			$intent['filters']['date_from'] = wp_date( 'Y-01-01', $now );
			$intent['filters']['date_to']   = wp_date( 'Y-m-d', $now );
		} elseif ( preg_match( '/\blast\s+month\b/i', $q ) ) {
			$current_year  = (int) current_time( 'Y' );
			$current_month = (int) current_time( 'm' );
			if ( $current_month === 1 ) {
				$lm = 12;
				$ly = $current_year - 1;
			} else {
				$lm = $current_month - 1;
				$ly = $current_year;
			}
			$from = sprintf( '%04d-%02d-01', $ly, $lm );
			$intent['filters']['date_from'] = $from;
			$intent['filters']['date_to']   = wp_date( 'Y-m-d', strtotime( $from . ' +1 month -1 day' ) );
		} elseif ( preg_match( '/\bthis\s+month\b/i', $q ) ) {
			$now = current_time( 'timestamp' );
			$intent['filters']['date_from'] = wp_date( 'Y-m-01', $now );
			$intent['filters']['date_to']   = wp_date( 'Y-m-d', $now );
		}
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_customer_count( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\b(how\s+many|count|number\s+of)\b/i', $q ) ) $s += 20;
		if ( preg_match( '/\bcustomers?\b/i', $q ) )                     $s += 25;
		if ( preg_match( '/\b(placed|made|submitted)\b/i', $q ) )        $s += 25;
		if ( $s < 60 ) return null;

		$intent['entity']    = 'customers';
		$intent['operation'] = 'statistics';
		$intent['filters']['sort_by']  = 'total_spent';
		$intent['filters']['group_by'] = 'customer';
		if ( empty( $intent['filters']['limit'] ) ) {
			$intent['filters']['limit'] = -1;
		}
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_order_by_status( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\borders?\b/i', $q ) )                   $s += 20;
		if ( preg_match( '/\b(by\s+status|status(es)?)\b/i', $q ) ) $s += 40;
		if ( $s < 50 ) return null;

		$intent['entity']    = 'orders';
		$intent['operation'] = 'statistics';
		if ( ! in_array( 'status', $intent['dimensions'] ?? array(), true ) ) {
			$intent['dimensions'][] = 'status';
		}
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_top_customers( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\btop\b/i', $q ) )           $s += 30;
		if ( preg_match( '/\bcustomers?\b/i', $q ) )    $s += 30;
		if ( preg_match( '/\b(spend|spent|revenue)\b/i', $q ) ) $s += 10;
		if ( $s < 50 ) return null;

		$intent['entity']    = 'customers';
		$intent['operation'] = 'statistics';
		$intent['filters']['sort_by']  = 'total_spent';
		$intent['filters']['group_by'] = 'customer';
		if ( empty( $intent['filters']['limit'] ) ) {
			$intent['filters']['limit'] = 10;
		}

		if ( preg_match( '/\blast\s+year\b/i', $q ) ) {
			$last_year = (int) current_time( 'Y' ) - 1;
			$intent['filters']['date_from'] = $last_year . '-01-01';
			$intent['filters']['date_to']   = $last_year . '-12-31';
		} elseif ( preg_match( '/\bthis\s+year\b/i', $q ) ) {
			$now = current_time( 'timestamp' );
			$intent['filters']['date_from'] = wp_date( 'Y-01-01', $now );
			$intent['filters']['date_to']   = wp_date( 'Y-m-d', $now );
		}
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_top_selling_products( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\b(top[\s-]?sell|best[\s-]?sell|most[\s-]?sold|most[\s-]?popular)\w*\b/i', $q ) ) $s += 40;
		if ( preg_match( '/\bproducts?\b/i', $q ) ) $s += 15;
		if ( $s < 50 ) return null;

		$intent['entity']    = 'products';
		$intent['operation'] = 'list';
		if ( ! in_array( 'top_products', $intent['metrics'] ?? array(), true ) ) {
			$intent['metrics'][] = 'top_products';
		}
		if ( empty( $intent['filters']['limit'] ) ) {
			$intent['filters']['limit'] = 10;
		}
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_monthly_chart( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\b(chart|graph|visualiz|overview)\b/i', $q ) ) $s += 25;
		if ( preg_match( '/\bmonth(ly|s)?\b/i', $q ) )                   $s += 20;
		if ( preg_match( '/\b(revenue|sales|order)\b/i', $q ) )          $s += 15;
		if ( $s < 50 ) return null;

		$intent['entity']    = 'orders';
		$intent['operation'] = 'by_period';
		if ( ! in_array( 'month', $intent['dimensions'] ?? array(), true ) ) {
			$intent['dimensions'][] = 'month';
		}
		return array( 'score' => $s, 'intent' => $intent );
	}

	private static function try_daily_chart( $q, array $intent ) {
		$s = 0;
		if ( preg_match( '/\b(chart|graph|visualiz)\b/i', $q ) ) $s += 25;
		if ( preg_match( '/\bdail(y|ies)\b/i', $q ) )            $s += 25;
		if ( preg_match( '/\border\b/i', $q ) )                  $s += 15;
		if ( $s < 50 ) return null;

		$intent['entity']    = 'orders';
		$intent['operation'] = 'by_period';
		if ( ! in_array( 'day', $intent['dimensions'] ?? array(), true ) ) {
			$intent['dimensions'][] = 'day';
		}
		return array( 'score' => $s, 'intent' => $intent );
	}

	/**
	 * Detect comparison queries that require multiple date ranges (unsupported today).
	 *
	 * @param string $question Question.
	 * @return bool
	 */
	public static function is_comparison_question( $question ) {
		$q = (string) $question;
		return (bool) preg_match( '/\b(compare|compared\s+to|vs\.?|versus|same\s+month\s+last\s+year|same\s+period\s+last\s+year|year\s+over\s+year|yo\s*y)\b/i', $q );
	}

	/**
	 * Detect conversion-rate questions (unsupported today due to missing traffic/session data).
	 *
	 * @param string $question Question.
	 * @return bool
	 */
	public static function is_conversion_rate_question( $question ) {
		$q = (string) $question;
		return (bool) preg_match( '/\b(conversion\s*rate|conversion|cvr)\b/i', $q );
	}

	/**
	 * Detect complex cross-entity queries that require combining multiple data sources.
	 *
	 * @param string $question Question.
	 * @return bool
	 */
	public static function is_cross_entity_question( $question ) {
		$q = strtolower( (string) $question );
		if ( preg_match( '/\bcombine\b/i', $question ) ) {
			$entities = array( 'sales', 'order', 'customer', 'product', 'category', 'inventory' );
			$found = 0;
			foreach ( $entities as $e ) {
				if ( strpos( $q, $e ) !== false ) {
					$found++;
				}
			}
			return $found >= 2;
		}
		return false;
	}

	/**
	 * Normalize scope semantics from filters and question text.
	 *
	 * @param string $question Question text.
	 * @param array  $intent   Validated + normalized intent.
	 * @return array
	 */
	private static function normalize_scope_from_question( $question, array $intent ) {
		$q = strtolower( (string) $question );
		$scope = isset( $intent['scope'] ) ? (string) $intent['scope'] : '';
		$entity = isset( $intent['entity'] ) ? (string) $intent['entity'] : '';
		$filters = isset( $intent['filters'] ) && is_array( $intent['filters'] ) ? $intent['filters'] : array();
		$metrics = isset( $intent['metrics'] ) && is_array( $intent['metrics'] ) ? $intent['metrics'] : array();
		$wants_all = (bool) preg_match( '/\b(all|every|entire|complete|full)\b/i', $q );

		// "orders by status" should be treated as statistics breakdown, not raw list.
		if (
			$entity === 'orders'
			&& preg_match( '/\borders?\b/i', $q )
			&& preg_match( '/\bby\s+status\b/i', $q )
		) {
			$intent['operation'] = 'statistics';
			if ( ! in_array( 'status', $intent['dimensions'] ?? array(), true ) ) {
				$intent['dimensions'][] = 'status';
			}
		}

		$mentions_low = (bool) preg_match( '/\b(low stock|running low)\b/i', $q );
		$mentions_out = (bool) preg_match( '/\bout of stock\b/i', $q );
		$mentions_inventory_semantics = (bool) preg_match( '/\b(inventory|stock(?:\s+levels?)?|stock\s+quantit(?:y|ies)|quantit(?:y|ies)|qty|on hand)\b/i', $q );
		$mentions_products = (bool) preg_match( '/\bproducts?\b/i', $q );
		$wants_numbers = (bool) preg_match( '/\b(numbers?|quantit(?:y|ies)|qty)\b/i', $q );
		$is_product_ranking = in_array( 'top_products', $metrics, true ) || (bool) preg_match( '/\b(top|best[\s-]?selling|most sold)\b/i', $q );

		// Capability resolver: product + inventory semantics should use inventory capability.
		if ( $entity === 'products' && $mentions_inventory_semantics && ! $is_product_ranking ) {
			$intent['entity']    = 'inventory';
			$intent['operation'] = 'list';
			$entity              = 'inventory';

			if ( $mentions_out ) {
				$intent['scope'] = 'out_of_stock';
				$intent['filters']['stock_status'] = 'outofstock';
				unset( $intent['filters']['stock_threshold'] );
				return $intent;
			}
			if ( $mentions_low ) {
				$intent['scope'] = 'low_stock';
				if ( ! isset( $intent['filters']['stock_threshold'] ) ) {
					$intent['filters']['stock_threshold'] = 10;
				}
				unset( $intent['filters']['stock_status'] );
				return $intent;
			}

			// When user asks products + inventory (or asks for numbers), default to full inventory with quantities.
			if ( $mentions_products || $wants_all || $wants_numbers ) {
				$intent['scope'] = 'all';
				$intent['filters']['limit'] = -1;
				unset( $intent['filters']['stock_status'], $intent['filters']['stock_threshold'] );
				return $intent;
			}
		}

		if ( $entity !== 'stock' && $entity !== 'inventory' ) {
			// Generic scope fallback for non-stock entities.
			if ( $scope === '' && isset( $filters['limit'] ) && (int) $filters['limit'] === -1 ) {
				$intent['scope'] = 'all';
			} elseif ( $scope === '' && isset( $filters['limit'] ) && (int) $filters['limit'] > 0 ) {
				$intent['scope'] = 'top_n';
			}
			return $intent;
		}

		// Stock/inventory scope normalization.
		if ( $mentions_out || ( isset( $filters['stock_status'] ) && $filters['stock_status'] === 'outofstock' ) ) {
			$intent['scope'] = 'out_of_stock';
			$intent['filters']['stock_status'] = 'outofstock';
			unset( $intent['filters']['stock_threshold'] );
			return $intent;
		}

		if ( $mentions_low || isset( $filters['stock_threshold'] ) ) {
			$intent['scope'] = 'low_stock';
			if ( ! isset( $intent['filters']['stock_threshold'] ) ) {
				$intent['filters']['stock_threshold'] = 10;
			}
			unset( $intent['filters']['stock_status'] );
			return $intent;
		}

		// Inventory entity defaults to "all"; explicit "all" on stock should also route to all.
		if ( $entity === 'inventory' || $wants_all || ( isset( $filters['limit'] ) && (int) $filters['limit'] === -1 ) ) {
			$intent['scope'] = 'all';
			$intent['filters']['limit'] = -1;
			unset( $intent['filters']['stock_status'], $intent['filters']['stock_threshold'] );
			return $intent;
		}

		// Backward-compatible default for generic "stock level" wording.
		if ( empty( $intent['scope'] ) ) {
			$intent['scope'] = 'low_stock';
			if ( ! isset( $intent['filters']['stock_threshold'] ) ) {
				$intent['filters']['stock_threshold'] = 10;
			}
		}

		return $intent;
	}

	/**
	 * Capability resolver for orders/revenue questions.
	 * Enforces deterministic operation routing for counts, totals, and status breakdowns.
	 *
	 * @param string $question Question text.
	 * @param array  $intent   Validated + normalized intent.
	 * @return array
	 */
	private static function normalize_orders_revenue_capability( $question, array $intent ) {
		$q = (string) $question;
		$q_l = strtolower( $q );
		$entity = isset( $intent['entity'] ) ? (string) $intent['entity'] : '';
		$operation = isset( $intent['operation'] ) ? (string) $intent['operation'] : 'list';

		$mentions_orders = (bool) preg_match( '/\borders?\b/i', $q );
		$mentions_customers = (bool) preg_match( '/\bcustomers?\b/i', $q );
		$mentions_revenue = (bool) preg_match( '/\b(revenue|sales|income|gmv|gross merchandise value|order value)\b/i', $q );
		$mentions_count = (bool) preg_match( '/\b(how many|count|number of|total number of|total orders?)\b/i', $q_l );
		$mentions_list = (bool) preg_match( '/\b(show|list|display|give|fetch|view)\b/i', $q );
		$mentions_status_breakdown = (bool) preg_match( '/\b(by status|status breakdown|order status|statuses)\b/i', $q );
		$mentions_chart_period = (bool) preg_match( '/\b(chart|graph|trend|timeline|over time)\b/i', $q );
		$status = self::extract_order_status_from_question( $q );
		$wants_all = (bool) preg_match( '/\b(all|every|entire|complete|full)\b/i', $q );

		$is_order_revenue_capability = ( $entity === 'orders' ) || $mentions_orders || $mentions_revenue || $mentions_status_breakdown || $status !== '';
		if ( ! $is_order_revenue_capability ) {
			return $intent;
		}

		// Keep customer-count capability under customers when the question is
		// customer-centric (e.g. "How many customers placed orders this month?").
		if (
			$entity === 'customers'
			&& $mentions_customers
			&& ! $mentions_revenue
			&& ! $mentions_status_breakdown
		) {
			return $intent;
		}

		$intent['entity'] = 'orders';

		// Keep chart/by-period capability when explicitly requested.
		if ( $operation === 'by_period' || $mentions_chart_period ) {
			if ( $status !== '' ) {
				$intent['filters']['status'] = $status;
			}
			return $intent;
		}

		if ( $mentions_status_breakdown ) {
			$intent['operation'] = 'statistics';
			if ( ! isset( $intent['dimensions'] ) || ! is_array( $intent['dimensions'] ) ) {
				$intent['dimensions'] = array();
			}
			if ( ! in_array( 'status', $intent['dimensions'], true ) ) {
				$intent['dimensions'][] = 'status';
			}
		} elseif ( $mentions_revenue || $mentions_count ) {
			$intent['operation'] = 'statistics';
		} elseif ( $mentions_orders && $mentions_list ) {
			$intent['operation'] = 'list';
		}

		if ( $status !== '' ) {
			if ( ! isset( $intent['filters'] ) || ! is_array( $intent['filters'] ) ) {
				$intent['filters'] = array();
			}
			$intent['filters']['status'] = $status;
		}

		if ( isset( $intent['operation'] ) && $intent['operation'] === 'list' ) {
			if ( $wants_all ) {
				$intent['filters']['limit'] = -1;
				$intent['scope'] = 'all';
			}
		}

		if ( isset( $intent['operation'] ) && $intent['operation'] === 'statistics' ) {
			unset( $intent['filters']['limit'] );
		}

		return $intent;
	}

	/**
	 * Extract normalized WooCommerce order status slug from question text.
	 *
	 * @param string $question Question text.
	 * @return string Empty when no explicit status is found.
	 */
	private static function extract_order_status_from_question( $question ) {
		if ( ! preg_match( '/\b(pending|processing|completed|on[\s-]?hold|cancelled|canceled|refunded|failed|trash)\b/i', (string) $question, $m ) ) {
			return '';
		}

		$status = strtolower( $m[1] );
		if ( preg_match( '/^on[\s-]?hold$/', $status ) ) {
			return 'on-hold';
		}
		if ( $status === 'canceled' ) {
			return 'cancelled';
		}
		return $status;
	}

	/**
	 * Detect questions that require external data sources not available in WooCommerce.
	 *
	 * @param string $question Question.
	 * @return string|false The unsupported entity keyword, or false.
	 */
	public static function get_unsupported_data_source( $question ) {
		$q = strtolower( (string) $question );

		$external_sources = array(
			'traffic source'         => 'traffic_source_analytics',
			'traffic sources'        => 'traffic_source_analytics',
			'social media referral'  => 'social_media_referrals',
			'social media'           => 'social_media_analytics',
			'referral'               => 'referral_analytics',
			'affiliate'              => 'affiliate_data',
			'google analytics'       => 'google_analytics',
			'advertising'            => 'advertising_data',
			'ad spend'               => 'advertising_data',
			'email campaign'         => 'email_campaigns',
			'seo'                    => 'seo_analytics',
		);

		foreach ( $external_sources as $keyword => $entity ) {
			if ( strpos( $q, $keyword ) !== false ) {
				return $entity;
			}
		}
		return false;
	}

	/**
	 * Run the full normalization pipeline on a validated intent.
	 *
	 * Convenience method that chains date normalization + intent normalization.
	 *
	 * @param string $question         User question.
	 * @param array  $validated_intent Validated intent.
	 * @return array
	 */
	public static function normalize( $question, array $validated_intent ) {
		$validated_intent = self::normalize_relative_date_ranges( $question, $validated_intent );
		$validated_intent = self::normalize_intent_from_question( $question, $validated_intent );
		$validated_intent = self::normalize_orders_revenue_capability( $question, $validated_intent );
		$validated_intent = self::normalize_scope_from_question( $question, $validated_intent );
		return $validated_intent;
	}
}
