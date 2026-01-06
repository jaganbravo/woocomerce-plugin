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
		
		// Check if user wants "all" orders (for charts or comprehensive views)
		$wants_all = preg_match( '/\b(all|every|entire|complete|full)\b/i', $question );
		
		// Extract limit
		$limit = self::extract_number( $question );
		if ( $limit ) {
			$filters['limit'] = min( 100, max( 1, $limit ) );
		} elseif ( $wants_all ) {
			// If user wants "all" and no specific limit, use -1 to get ALL orders
			// This is especially important for charts to show accurate distribution
			$filters['limit'] = -1; // -1 means unlimited (get all orders)
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
		
		// Extract date ranges (basic - could be enhanced)
		if ( preg_match( '/\b(today|yesterday|this week|this month|last week|last month|this year|last year)\b/i', $lower_question ) ) {
			// Could implement date parsing here
			// For now, we'll let the tool handle default date ranges
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
	 * Classify intent and determine which tools to call based on question.
	 * This replaces LLM-based tool selection with rule-based intent classification.
	 *
	 * @param string $question User's question.
	 * @return array Array of tool call structures with function name and arguments.
	 */
	public static function classify_intent_and_get_tools( $question ) {
		$tool_calls = array();
		
		// Check if question requires data
		if ( ! self::question_requires_data( $question ) ) {
			return array(); // Not a data question, no tools needed
		}
		
		// Extract entity type from question
		$entity_type = self::extract_entity_type( $question );
		$query_type = self::extract_query_type( $question );
		$filters = self::extract_filters( $question, $entity_type );
		
		// Check for statistics/aggregated queries (revenue, total, count, average, etc.)
		$is_statistics_query = preg_match( '/\b(total|revenue|count|average|sum|statistics|stats|how many|revenue by|total sales|avg|mean)\b/i', $question );
		
		// Handle order-related queries
		if ( $entity_type === 'orders' || preg_match( '/\b(order|orders|sale|sales|transaction|purchase|recent order)\b/i', $question ) ) {
			if ( $is_statistics_query || $query_type === 'statistics' ) {
				// Use order statistics for aggregated queries
				$tool_calls[] = array(
					'function' => array(
						'name' => 'get_order_statistics',
						'arguments' => wp_json_encode( $filters ),
					),
					'id' => 'intent-order-stats-' . uniqid(),
				);
			} else {
				// Use flexible query for list/sample queries
				$tool_calls[] = array(
					'function' => array(
						'name' => 'get_woocommerce_data',
						'arguments' => wp_json_encode( array(
							'entity_type' => 'orders',
							'query_type' => $query_type,
							'filters' => $filters,
						) ),
					),
					'id' => 'intent-orders-' . uniqid(),
				);
			}
		}
		// Handle product-related queries
		elseif ( $entity_type === 'products' || preg_match( '/\b(product|products|item|items)\b/i', $question ) ) {
			if ( preg_match( '/\b(top|best|popular|selling|bestselling)\b/i', $question ) ) {
				$limit = self::extract_number( $question, 10 );
				$tool_calls[] = array(
					'function' => array(
						'name' => 'get_top_products',
						'arguments' => wp_json_encode( array( 'limit' => $limit ) ),
					),
					'id' => 'intent-top-products-' . uniqid(),
				);
			} else {
				$tool_calls[] = array(
					'function' => array(
						'name' => 'get_woocommerce_data',
						'arguments' => wp_json_encode( array(
							'entity_type' => 'products',
							'query_type' => $query_type,
							'filters' => $filters,
						) ),
					),
					'id' => 'intent-products-' . uniqid(),
				);
			}
		}
		// Handle customer-related queries
		elseif ( $entity_type === 'customers' || preg_match( '/\b(customer|customers|buyer|buyers|client|clients)\b/i', $question ) ) {
			if ( $is_statistics_query || preg_match( '/\b(summary|overview|total)\b/i', $question ) ) {
				$tool_calls[] = array(
					'function' => array(
						'name' => 'get_customer_summary',
						'arguments' => wp_json_encode( array() ),
					),
					'id' => 'intent-customer-summary-' . uniqid(),
				);
			} else {
				$limit = self::extract_number( $question, 10 );
				$tool_calls[] = array(
					'function' => array(
						'name' => 'get_customers',
						'arguments' => wp_json_encode( array( 'limit' => $limit ) ),
					),
					'id' => 'intent-customers-' . uniqid(),
				);
			}
		}
		// Handle inventory/stock queries
		elseif ( $entity_type === 'stock' || $entity_type === 'inventory' || preg_match( '/\b(inventory|stock|low stock|out of stock)\b/i', $question ) ) {
			$tool_calls[] = array(
				'function' => array(
					'name' => 'get_woocommerce_data',
					'arguments' => wp_json_encode( array(
						'entity_type' => $entity_type,
						'query_type' => $query_type,
						'filters' => $filters,
					) ),
				),
				'id' => 'intent-' . $entity_type . '-' . uniqid(),
			);
		}
		// Handle other entity types (categories, tags, coupons, refunds, etc.)
		elseif ( $entity_type ) {
			$tool_calls[] = array(
				'function' => array(
					'name' => 'get_woocommerce_data',
					'arguments' => wp_json_encode( array(
						'entity_type' => $entity_type,
						'query_type' => $query_type,
						'filters' => $filters,
					) ),
				),
				'id' => 'intent-' . $entity_type . '-' . uniqid(),
			);
		}
		// Default fallback - get recent orders
		elseif ( self::question_requires_data( $question ) ) {
			$tool_calls[] = array(
				'function' => array(
					'name' => 'get_woocommerce_data',
					'arguments' => wp_json_encode( array(
						'entity_type' => 'orders',
						'query_type' => 'list',
						'filters' => array( 'limit' => 20 ),
					) ),
				),
				'id' => 'intent-default-' . uniqid(),
			);
		}
		
		return $tool_calls;
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

