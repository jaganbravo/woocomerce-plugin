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

		// Order status queries (high priority)
		if ( preg_match( '/\b(order )?status|status(es)?\b/i', $question ) && preg_match( '/\b(order|orders)\b/i', $question ) ) {
			$hints['primary_entity'] = 'orders';
			$hints['query_type']      = 'statistics';
			$hints['confidence']      = 'high';
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
		if ( ! empty( $multiple_entities ) ) {
			$hints['complexity']          = 'complex';
			$hints['secondary_entities']  = array_merge( $hints['secondary_entities'], $multiple_entities );
		}

		return $hints;
	}

	/**
	 * Build tool calls from hints (for simple, high-confidence cases).
	 *
	 * @param array $hints Intent hints.
	 * @return array Array of tool call structures.
	 */
	private static function build_tool_calls_from_hints( array $hints ) {
		$tool_calls = array();

		if ( ! $hints['primary_entity'] ) {
			return $tool_calls;
		}

		$entity_type = $hints['primary_entity'];
		$query_type  = $hints['query_type'];
		$filters      = $hints['filters'];

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

		return $tool_calls;
	}

	/**
	 * Classify intent and determine which tools to call based on question.
	 * Uses hints to decide: simple cases use rules, complex cases use LLM.
	 *
	 * @param string $question User's question.
	 * @return array Array of tool call structures with function name and arguments.
	 */
	public static function classify_intent_and_get_tools( $question ) {
		// Check if question requires data
		if ( ! self::question_requires_data( $question ) ) {
			return array(); // Not a data question, no tools needed
		}

		// Step 1: Extract hints (lightweight pattern matching)
		$hints = self::extract_intent_hints( $question );

		// Step 2: Decision logic - use rules for simple, high-confidence cases
		// Use LLM for complex cases or when confidence is low
		if ( $hints['confidence'] === 'high' && $hints['complexity'] === 'simple' && $hints['primary_entity'] ) {
			// Simple case: use rule-based tool calls
			return self::build_tool_calls_from_hints( $hints );
		}

		// Complex case or low confidence: return hints for LLM to use
		// The AJAX handler will use these hints to guide LLM tool selection
		// For now, return empty array to signal "use LLM"
		// The hints will be passed to LLM via tool descriptions
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

