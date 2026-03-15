<?php
/**
 * Intent classification and query parsing utilities for Dataviz AI WooCommerce plugin.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles intent classification and query parsing for user questions.
 */
class Dataviz_AI_Intent_Classifier {

	/**
	 * Check if question requires data from WooCommerce.
	 *
	 * @param string $question User's question.
	 * @return bool
	 */
	public static function question_requires_data( $question ) {
		$data_keywords = array(
			'order', 'product', 'customer', 'sale', 'revenue', 'transaction',
			'purchase', 'buy', 'item', 'inventory', 'stock', 'buyer',
			'client', 'purchased', 'sold', 'total', 'recent', 'list',
			'show me', 'display', 'what are', 'how many', 'tell me about',
			// Spending / financial.
			'spent', 'spend', 'spending', 'earning', 'income', 'profit',
			// Funnel / conversion questions (often require external analytics).
			'conversion', 'conversion rate', 'cvr', 'traffic', 'visitors', 'sessions', 'pageviews',
			// Discounts / coupons.
			'discount', 'discounts', 'coupon', 'coupons', 'promo', 'promotion', 'promotions', 'promo code', 'promo codes',
			// Refunds / returns.
			'refund', 'refunds', 'return', 'returns', 'returned',
			// Categories / tags.
			'category', 'categories', 'tag', 'tags',
			// Charts / visualizations.
			'chart', 'graph', 'pie chart', 'bar chart', 'line chart', 'visualize', 'visualization',
			// General analytics.
			'metric', 'metrics', 'statistic', 'statistics', 'overview', 'report',
			// Restocking / availability.
			'restock', 'restocking', 'available', 'availability', 'out of stock',
		);
		
		$lower_question = strtolower( $question );
		foreach ( $data_keywords as $keyword ) {
			if ( strpos( $lower_question, $keyword ) !== false ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Detect if user is asking for multiple entity types.
	 *
	 * @param string $question User's question.
	 * @return array Array of detected entity types (empty if none or single entity).
	 */
	public static function detect_multiple_entities( $question ) {
		$lower_question = strtolower( $question );
		$detected_entities = array();
		
		// Entity type mappings with synonyms
		$entity_patterns = self::get_entity_patterns();
		
		// Check for conjunctions that indicate multiple entities
		$conjunction_patterns = array( '/\band\b/i', '/\bor\b/i', '/\bplus\b/i', '/\bwith\b/i', '/\b,\s*/' );
		$has_conjunction = false;
		foreach ( $conjunction_patterns as $pattern ) {
			if ( preg_match( $pattern, $question ) ) {
				$has_conjunction = true;
				break;
			}
		}
		
		// If no conjunction, likely single entity
		if ( ! $has_conjunction ) {
			return array();
		}
		
		// Detect all entities mentioned
		foreach ( $entity_patterns as $entity_type => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( preg_match( '/\b' . preg_quote( $pattern, '/' ) . '\b/i', $lower_question ) ) {
					if ( ! in_array( $entity_type, $detected_entities, true ) ) {
						$detected_entities[] = $entity_type;
					}
					break; // Found this entity type, move to next
				}
			}
		}
		
		// Return only if multiple entities detected
		return count( $detected_entities ) > 1 ? $detected_entities : array();
	}

	/**
	 * Extract entity type from question.
	 *
	 * @param string $question User's question.
	 * @return string|null Entity type or null.
	 */
	public static function extract_entity_type( $question ) {
		$lower_question = strtolower( $question );
		$entity_patterns = self::get_entity_patterns();
		
		foreach ( $entity_patterns as $entity_type => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( preg_match( '/\b' . preg_quote( $pattern, '/' ) . '\b/i', $lower_question ) ) {
					return $entity_type;
				}
			}
		}
		
		return null;
	}
	
	/**
	 * Extract query type from question (list, statistics, sample, by_period).
	 *
	 * @param string $question User's question.
	 * @return string Query type (default: 'list').
	 */
	public static function extract_query_type( $question ) {
		// Check for statistics queries
		if ( preg_match( '/\b(total|revenue|count|average|sum|statistics|stats|how many|revenue by|total sales|avg|mean)\b/i', $question ) ) {
			return 'statistics';
		}
		
		// Check for status breakdown queries (should use statistics to get all statuses)
		if ( preg_match( '/\b(order )?status|status(es)?\b/i', $question ) && preg_match( '/\b(order|orders)\b/i', $question ) ) {
			return 'statistics';
		}
		
		// Check for time-series queries
		if ( preg_match( '/\b(by (day|week|month|year|hour)|over time|trend|daily|weekly|monthly|hourly)\b/i', $question ) ) {
			return 'by_period';
		}
		
		// Check for sample queries
		if ( preg_match( '/\b(sample|example|few|some)\b/i', $question ) ) {
			return 'sample';
		}
		
		// Default to list
		return 'list';
	}
	
	/**
	 * Extract filters from question (status, date ranges, limits, etc.).
	 *
	 * @param string $question User's question.
	 * @param string|null $entity_type Entity type.
	 * @return array Filters array.
	 */
	public static function extract_filters( $question, $entity_type = null ) {
		$filters = array();
		$lower_question = strtolower( $question );
		
		// Check if user wants "all" items (for charts or comprehensive views)
		$wants_all = preg_match( '/\b(all|every|entire|complete|full)\b/i', $question );
		
		// Extract limit
		$limit = self::extract_number( $question );
		if ( $limit ) {
			$filters['limit'] = min( 100, max( 1, $limit ) );
		} elseif ( $wants_all ) {
			// If user wants "all" and no specific limit, use -1 to get ALL items
			// This is especially important for charts to show accurate distribution
			// Works for orders, products, customers, etc.
			$filters['limit'] = -1; // -1 means unlimited (get all items)
		}
		
		// Extract order status
		if ( $entity_type === 'orders' || preg_match( '/\b(order|orders)\b/i', $question ) ) {
			$status_patterns = array(
				'completed' => array( 'completed', 'finished', 'done', 'processed' ),
				'pending'   => array( 'pending', 'waiting', 'unpaid' ),
				'processing' => array( 'processing', 'in progress', 'being processed' ),
				'on-hold'   => array( 'on hold', 'on-hold', 'held' ),
				'cancelled' => array( 'cancelled', 'canceled', 'cancelled' ),
				'refunded'  => array( 'refunded', 'refund' ),
				'failed'    => array( 'failed', 'failure' ),
			);
			
			foreach ( $status_patterns as $status => $patterns ) {
				foreach ( $patterns as $pattern ) {
					if ( preg_match( '/\b' . preg_quote( $pattern, '/' ) . '\b/i', $lower_question ) ) {
						$filters['status'] = $status;
						break 2;
					}
				}
			}
		}
		
		// Extract date ranges and parse relative dates
		// Check for calendar month patterns first (they take precedence)
		$has_calendar_month = false;
		if ( preg_match( '/\b(today|yesterday|this week|this month|last week|last month|this year|last year)\b/i', $lower_question, $date_matches ) ) {
			$date_phrase = strtolower( $date_matches[1] );
			$now = current_time( 'timestamp' );
			
			switch ( $date_phrase ) {
				case 'today':
					$filters['date_from'] = date( 'Y-m-d', $now );
					$filters['date_to']   = date( 'Y-m-d', $now );
					$has_calendar_month = true;
					break;
					
				case 'yesterday':
					$yesterday = $now - DAY_IN_SECONDS;
					$filters['date_from'] = date( 'Y-m-d', $yesterday );
					$filters['date_to']   = date( 'Y-m-d', $yesterday );
					$has_calendar_month = true;
					break;
					
				case 'this week':
					$week_start = strtotime( 'monday this week', $now );
					$filters['date_from'] = date( 'Y-m-d', $week_start );
					$filters['date_to']   = date( 'Y-m-d', $now );
					$has_calendar_month = true;
					break;
					
				case 'last week':
					$last_week_start = strtotime( 'monday last week', $now );
					$last_week_end   = strtotime( 'sunday last week', $now );
					$filters['date_from'] = date( 'Y-m-d', $last_week_start );
					$filters['date_to']   = date( 'Y-m-d', $last_week_end );
					$has_calendar_month = true;
					break;
					
				case 'this month':
					$filters['date_from'] = date( 'Y-m-01', $now );
					$filters['date_to']   = date( 'Y-m-d', $now );
					$has_calendar_month = true;
					break;
					
				case 'last month':
					// Calculate last month using WordPress timezone-aware functions
					// Get current month and year in WordPress timezone
					$current_year = (int) current_time( 'Y' );
					$current_month = (int) current_time( 'm' );
					
					// Calculate last month
					if ( $current_month === 1 ) {
						$last_month = 12;
						$last_year = $current_year - 1;
					} else {
						$last_month = $current_month - 1;
						$last_year = $current_year;
					}
					
					// Get first and last day of last month using WordPress timezone
					$filters['date_from'] = sprintf( '%04d-%02d-01', $last_year, $last_month );
					// Use WordPress timezone for last day calculation
					$last_day_timestamp = strtotime( $filters['date_from'] . ' +1 month -1 day' );
					$filters['date_to'] = date( 'Y-m-d', $last_day_timestamp );
					$has_calendar_month = true;
					break;
					
				case 'this year':
					$filters['date_from'] = date( 'Y-01-01', $now );
					$filters['date_to']   = date( 'Y-m-d', $now );
					$has_calendar_month = true;
					break;
					
				case 'last year':
					$last_year = date( 'Y', $now ) - 1;
					$filters['date_from'] = $last_year . '-01-01';
					$filters['date_to']   = $last_year . '-12-31';
					$has_calendar_month = true;
					break;
			}
		}
		
		// Explicit "Month Year" expressions, e.g. "December 2023", "the month of December 1987".
		if ( ! $has_calendar_month && preg_match( '/\b(?:(?:in|during|for)\s+|(?:for\s+the\s+month\s+of\s+)|(?:the\s+month\s+of\s+))?(january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{4})\b/i', $question, $explicit_matches ) ) {
			$month_name = strtolower( $explicit_matches[1] );
			$year       = (int) $explicit_matches[2];

			$month_map = array(
				'january'   => 1,
				'february'  => 2,
				'march'     => 3,
				'april'     => 4,
				'may'       => 5,
				'june'      => 6,
				'july'      => 7,
				'august'    => 8,
				'september' => 9,
				'october'   => 10,
				'november'  => 11,
				'december'  => 12,
			);

			if ( isset( $month_map[ $month_name ] ) ) {
				$month                    = $month_map[ $month_name ];
				$filters['date_from']     = sprintf( '%04d-%02d-01', $year, $month );
				$last_day_timestamp       = strtotime( $filters['date_from'] . ' +1 month -1 day' );
				$filters['date_to']       = date( 'Y-m-d', $last_day_timestamp );
				$has_calendar_month       = true;
			}
		}

		// Stock-specific filters (low stock vs out of stock).
		if ( $entity_type === 'stock' || preg_match( '/\b(stock|inventory)\b/i', $lower_question ) ) {
			// Explicit out-of-stock queries.
			if ( preg_match( '/\bout of stock\b/i', $lower_question ) ) {
				$filters['stock_status'] = 'outofstock';
			}
		}
		
		// Also check for "in the last X days/weeks/months" patterns
		// Only if no calendar month pattern was matched (to avoid overriding)
		if ( ! $has_calendar_month && preg_match( '/\bin the last (\d+)\s*(day|days|week|weeks|month|months)\b/i', $lower_question, $period_matches ) ) {
			$amount = (int) $period_matches[1];
			$unit   = strtolower( $period_matches[2] );
			$now    = current_time( 'timestamp' );
			
			$filters['date_to'] = date( 'Y-m-d', $now );
			
			switch ( $unit ) {
				case 'day':
				case 'days':
					$from_timestamp = $now - ( $amount * DAY_IN_SECONDS );
					$filters['date_from'] = date( 'Y-m-d', $from_timestamp );
					break;
					
				case 'week':
				case 'weeks':
					$from_timestamp = $now - ( $amount * WEEK_IN_SECONDS );
					$filters['date_from'] = date( 'Y-m-d', $from_timestamp );
					break;
					
				case 'month':
				case 'months':
					$from_timestamp = strtotime( "-{$amount} months", $now );
					$filters['date_from'] = date( 'Y-m-d', $from_timestamp );
					break;
			}
		}
		
		// Customer-specific filters for "top customers" style queries.
		if ( $entity_type === 'customers' || preg_match( '/\b(customer|customers|buyer|buyers|client|clients)\b/i', $lower_question ) ) {
			// Detect "top" or "best" semantics.
			if ( preg_match( '/\b(top|best)\b/i', $lower_question ) ) {
				$filters['sort_by']  = 'total_spent';
				$filters['group_by'] = 'customer';

				// If no explicit number was found earlier, default to top 10.
				if ( ! isset( $filters['limit'] ) ) {
					$filters['limit'] = 10;
				}
			}
		}

		// Sales/revenue by product category: use orders + statistics + group_by category.
		if ( preg_match( '/\b(sales?|revenue)\s+by\s+(product\s+)?categor(y|ies)\b/i', $lower_question ) || preg_match( '/\b(by\s+)?(product\s+)?categor(y|ies).*\b(sales?|revenue)\b/i', $lower_question ) ) {
			$filters['group_by'] = 'category';
		}

		return $filters;
	}
	
	/**
	 * Extract number from question (for limits, thresholds, etc.).
	 *
	 * @param string $question User's question.
	 * @param int $default Default value if no number found.
	 * @return int|null Number or default.
	 */
	public static function extract_number( $question, $default = null ) {
		if ( preg_match( '/\b(\d+)\b/', $question, $matches ) ) {
			return (int) $matches[1];
		}
		return $default;
	}

	/**
	 * Normalize entity type (handle synonyms).
	 *
	 * @param string $entity_type Entity type from user.
	 * @return string Normalized entity type.
	 */
	public static function normalize_entity_type( $entity_type ) {
		$normalized = strtolower( trim( $entity_type ) );
		
		// Handle synonyms - map to supported types
		// Note: "inventory" is NOT mapped - it's handled separately to show all inventory
		$synonyms = array(
			'stock levels' => 'stock',
			'stock level' => 'stock',
		);
		
		if ( isset( $synonyms[ $normalized ] ) ) {
			return $synonyms[ $normalized ];
		}
		
		// Keep "inventory" as-is (not normalized to "stock")
		return $normalized;
	}

	/**
	 * Extract intent hints from question (lightweight pattern matching for guidance).
	 * This provides hints to guide LLM decisions, not final decisions.
	 *
	 * @param string $question User's question.
	 * @return array Hints array with primary_entity, secondary_entities, query_type, confidence, complexity.
	 */
	public static function extract_intent_hints( $question ) {
		$hints = array(
			'primary_entity'     => null,
			'secondary_entities' => array(),
			'query_type'         => 'list',
			'confidence'         => 'low',
			'complexity'          => 'simple',
			'filters'            => array(),
		);

		$lower_question = strtolower( $question );

		// Priority 1: Check for specific high-confidence patterns FIRST (before generic patterns)
		// These are unambiguous and should override generic entity matching

		// Stock/inventory queries (high priority - overrides "products")
		if ( preg_match( '/\b(low stock|running low|out of stock|stock level|stock levels)\b/i', $question ) ) {
			$hints['primary_entity'] = 'stock';
			$hints['confidence']      = 'high';
			// If question mentions "products" but also "low stock", it's a stock query
			if ( preg_match( '/\b(product|products)\b/i', $question ) ) {
				$hints['secondary_entities'][] = 'products';
			}
		} elseif ( preg_match( '/\b(inventory.*category|category.*inventory|inventory distribution)\b/i', $question ) ) {
			$hints['primary_entity']     = 'inventory';
			$hints['secondary_entities'] = array( 'categories' );
			$hints['confidence']          = 'high';
			$hints['complexity']          = 'complex'; // Needs grouping by category
		} elseif ( preg_match( '/\b(inventory|inventories)\b/i', $question ) ) {
			$hints['primary_entity'] = 'inventory';
			$hints['confidence']     = 'high';
		}

		// Sales/revenue by product category (high priority: orders + statistics; extract_filters sets group_by=category)
		if ( preg_match( '/\b(sales?|revenue)\s+by\s+(product\s+)?categor(y|ies)\b/i', $question ) || preg_match( '/\b(by\s+)?(product\s+)?categor(y|ies).*\b(sales?|revenue)\b/i', $question ) ) {
			$hints['primary_entity'] = 'orders';
			$hints['query_type']     = 'statistics';
			$hints['confidence']     = 'high';
		}
		// Order status queries (high priority)
		elseif ( preg_match( '/\b(order )?status|status(es)?\b/i', $question ) && preg_match( '/\b(order|orders)\b/i', $question ) ) {
			$hints['primary_entity'] = 'orders';
			$hints['query_type']      = 'statistics';
			$hints['confidence']      = 'high';
		}
		// Generic revenue/sales totals (e.g. "total revenue this month") default to orders statistics.
		elseif ( $hints['primary_entity'] === null && preg_match( '/\b(revenue|sales?|turnover)\b/i', $question ) ) {
			$hints['primary_entity'] = 'orders';
			$hints['query_type']     = 'statistics';
			$hints['confidence']     = 'medium';
		}

		// If no high-confidence match yet, check generic patterns
		if ( $hints['primary_entity'] === null ) {
			$entity_type = self::extract_entity_type( $question );
			if ( $entity_type ) {
				$hints['primary_entity'] = $entity_type;
				$hints['confidence']      = 'medium';
			}
		}

		// Extract query type
		$hints['query_type'] = self::extract_query_type( $question );

		// Extract filters
		$hints['filters'] = self::extract_filters( $question, $hints['primary_entity'] );

		// Detect complexity
		if ( count( $hints['secondary_entities'] ) > 0 || 
			 preg_match( '/\b(distribution|grouped by|by category|by status|across)\b/i', $question ) ) {
			$hints['complexity'] = 'complex';
		}

		// Detect multiple entities (true multi-entity queries)
		$multiple_entities = self::detect_multiple_entities( $question );
		if ( ! empty( $multiple_entities ) && count( $multiple_entities ) > 1 ) {
			$hints['complexity'] = 'complex';
			
			// If primary_entity is already set and is in the multiple entities list, keep it as primary
			// Otherwise, use the first detected entity as primary
			if ( empty( $hints['primary_entity'] ) || ! in_array( $hints['primary_entity'], $multiple_entities, true ) ) {
				$hints['primary_entity'] = $multiple_entities[0];
			}
			
			// Add all other entities (excluding primary) to secondary_entities
			$remaining_entities = array_diff( $multiple_entities, array( $hints['primary_entity'] ) );
			$hints['secondary_entities'] = array_merge( $hints['secondary_entities'], array_values( $remaining_entities ) );
			// Remove duplicates
			$hints['secondary_entities'] = array_unique( $hints['secondary_entities'] );
		}

		return $hints;
	}

	/**
	 * Build tool calls from hints (supports single and multiple entities).
	 *
	 * @param array $hints Intent hints.
	 * @return array Array of tool call structures.
	 */
	private static function build_tool_calls_from_hints( array $hints ) {
		$tool_calls = array();
		
		if ( ! $hints['primary_entity'] ) {
			return $tool_calls;
		}

		$query_type  = $hints['query_type'];
		$filters      = $hints['filters'];
		
		// Get all entities to process (primary + secondary if multiple entities detected)
		$entities_to_process = array( $hints['primary_entity'] );
		
		// If multiple entities detected, add them to the list
		if ( ! empty( $hints['secondary_entities'] ) ) {
			$entities_to_process = array_merge( $entities_to_process, $hints['secondary_entities'] );
			// Remove duplicates
			$entities_to_process = array_unique( $entities_to_process );
		}
		
		// Build tool calls for each entity
		foreach ( $entities_to_process as $entity_type ) {

			// Handle orders
			if ( $entity_type === 'orders' ) {
				if ( $query_type === 'statistics' ) {
				$tool_calls[] = array(
					'function' => array(
							'name'      => 'get_order_statistics',
						'arguments' => wp_json_encode( $filters ),
					),
						'id'        => 'intent-order-stats-' . uniqid(),
				);
			} else {
				$tool_calls[] = array(
					'function' => array(
							'name'      => 'get_woocommerce_data',
							'arguments' => wp_json_encode(
								array(
							'entity_type' => 'orders',
									'query_type'  => $query_type,
									'filters'     => $filters,
								)
							),
						),
						'id'        => 'intent-orders-' . uniqid(),
					);
				}
			}
			// Handle products
			elseif ( $entity_type === 'products' ) {
				$tool_calls[] = array(
					'function' => array(
						'name'      => 'get_woocommerce_data',
						'arguments' => wp_json_encode(
							array(
								'entity_type' => 'products',
								'query_type'  => $query_type,
								'filters'     => $filters,
							)
						),
					),
					'id'        => 'intent-products-' . uniqid(),
				);
			}
			// Handle stock/inventory
			elseif ( $entity_type === 'stock' || $entity_type === 'inventory' ) {
				$tool_calls[] = array(
					'function' => array(
						'name'      => 'get_woocommerce_data',
						'arguments' => wp_json_encode(
							array(
								'entity_type' => $entity_type,
								'query_type'  => $query_type,
								'filters'     => $filters,
							)
						),
					),
					'id'        => 'intent-' . $entity_type . '-' . uniqid(),
				);
			}
			// Handle other entities
			else {
				$tool_calls[] = array(
					'function' => array(
						'name'      => 'get_woocommerce_data',
						'arguments' => wp_json_encode(
							array(
								'entity_type' => $entity_type,
								'query_type'  => $query_type,
								'filters'     => $filters,
							)
						),
					),
					'id'        => 'intent-' . $entity_type . '-' . uniqid(),
				);
			}
		}
		
		return $tool_calls;
	}

	/**
	 * Classify intent and determine which tools to call based on question.
	 * Always uses rule-based tool selection (no LLM fallback).
	 * Supports both single and multiple entities.
	 *
	 * @param string $question User's question.
	 * @return array Array of tool call structures with function name and arguments.
	 */
	public static function classify_intent_and_get_tools( $question ) {
		// Check if question requires data
		if ( ! self::question_requires_data( $question ) ) {
			return array(); // Not a data question, no tools needed
		}

		// Extract hints (lightweight pattern matching)
		$hints = self::extract_intent_hints( $question );

		// Always use rule-based tool calls if we have a primary entity
		// This now supports multiple entities (primary + secondary)
		if ( ! empty( $hints['primary_entity'] ) ) {
			return self::build_tool_calls_from_hints( $hints );
		}

		// If no entity detected, return empty (will be handled as non-data question)
		return array();
	}

	/**
	 * Get entity type patterns with synonyms.
	 *
	 * @return array Entity patterns array.
	 */
	private static function get_entity_patterns() {
		return array(
			'orders'     => array( 'order', 'orders', 'sale', 'sales', 'transaction', 'transactions', 'purchase', 'purchases' ),
			'products'   => array( 'product', 'products', 'item', 'items', 'goods' ),
			'customers'  => array( 'customer', 'customers', 'buyer', 'buyers', 'client', 'clients' ),
			'categories' => array( 'category', 'categories', 'product category', 'product categories' ),
			'tags'       => array( 'tag', 'tags', 'product tag', 'product tags' ),
			'coupons'    => array( 'coupon', 'coupons', 'discount', 'discounts', 'promo code', 'promo codes' ),
			'refunds'    => array( 'refund', 'refunds' ),
			'stock'      => array( 'stock', 'stock level', 'stock levels', 'low stock' ),
			'inventory'  => array( 'inventory', 'inventories' ),
		);
	}
}

