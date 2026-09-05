<?php
/**
 * Example questions when intent is unclear, low confidence, or execution cannot proceed.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds short, copy-pastable example prompts for the chat UI.
 */
class Dataviz_AI_Question_Suggestions {

	/**
	 * Collect up to N unique example questions, preferring entity-specific lines.
	 *
	 * @param array $intent_snapshot Validated intent (may be empty), e.g. entity/operation.
	 * @param int   $limit           Max suggestions.
	 * @return array List of translated strings.
	 */
	public static function get_lines( array $intent_snapshot, $limit = 5 ) {
		$limit = (int) $limit;
		if ( $limit < 1 ) {
			return array();
		}

		$entity = isset( $intent_snapshot['entity'] ) ? sanitize_key( (string) $intent_snapshot['entity'] ) : '';

		$specific = self::lines_for_entity( $entity );
		$generic  = self::generic_lines();

		$merged = array();
		foreach ( array_merge( $specific, $generic ) as $line ) {
			$line = is_string( $line ) ? trim( $line ) : '';
			if ( $line !== '' && ! in_array( $line, $merged, true ) ) {
				$merged[] = $line;
			}
			if ( count( $merged ) >= $limit ) {
				break;
			}
		}

		return $merged;
	}

	/**
	 * Format suggestion lines for plain chat (newlines preserved; admin UI uses <br>).
	 *
	 * @param array $lines Translated suggestion strings.
	 * @return string
	 */
	public static function format_for_chat( array $lines ) {
		$out = '';
		foreach ( $lines as $line ) {
			if ( ! is_string( $line ) || trim( $line ) === '' ) {
				continue;
			}
			$out .= '• ' . trim( $line ) . "\n";
		}

		return rtrim( $out );
	}

	/**
	 * @param string $entity Sanitized entity key.
	 * @return array
	 */
	protected static function lines_for_entity( $entity ) {
		switch ( $entity ) {
			case 'orders':
				return array(
					__( 'What was my total revenue this month?', 'unmai-analytix-for-woocommerce' ),
					__( 'How many pending orders do I have?', 'unmai-analytix-for-woocommerce' ),
					__( 'Show order totals for last week.', 'unmai-analytix-for-woocommerce' ),
				);
			case 'products':
				return array(
					__( 'What are my best-selling products?', 'unmai-analytix-for-woocommerce' ),
					__( 'List products in a specific category.', 'unmai-analytix-for-woocommerce' ),
				);
			case 'customers':
				return array(
					__( 'Who are my top customers by total spend?', 'unmai-analytix-for-woocommerce' ),
					__( 'How many customers placed an order this month?', 'unmai-analytix-for-woocommerce' ),
				);
			case 'categories':
				return array(
					__( 'List all product categories.', 'unmai-analytix-for-woocommerce' ),
					__( 'Show sales by product category.', 'unmai-analytix-for-woocommerce' ),
				);
			case 'stock':
			case 'inventory':
				return array(
					__( 'Which products are out of stock?', 'unmai-analytix-for-woocommerce' ),
					__( 'Show low-stock products.', 'unmai-analytix-for-woocommerce' ),
				);
			case 'coupons':
				return array(
					__( 'List active coupons.', 'unmai-analytix-for-woocommerce' ),
					__( 'How many times was a coupon used last month?', 'unmai-analytix-for-woocommerce' ),
				);
			case 'refunds':
				return array(
					__( 'How many refunds were issued this year?', 'unmai-analytix-for-woocommerce' ),
				);
			default:
				return array();
		}
	}

	/**
	 * @return array
	 */
	protected static function generic_lines() {
		return array(
			__( 'What was my total revenue this month?', 'unmai-analytix-for-woocommerce' ),
			__( 'How many pending orders do I have?', 'unmai-analytix-for-woocommerce' ),
			__( 'What are my best-selling products?', 'unmai-analytix-for-woocommerce' ),
			__( 'List my product categories.', 'unmai-analytix-for-woocommerce' ),
			__( 'Which products are low on stock?', 'unmai-analytix-for-woocommerce' ),
		);
	}
}
