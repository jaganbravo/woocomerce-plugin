<?php
/**
 * Composes deterministic answers from tool results when possible.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Answer_Composer {
	private static function extract_tag_from_question( $question ) {
		$q = (string) $question;
		// Prefer quoted tag name.
		if ( preg_match( "/\\btag\\b\\s*['\\\"]([^'\\\"]+)['\\\"]/i", $q, $m ) ) {
			return trim( (string) $m[1] );
		}
		// Fallback: tag New Arrival (until punctuation/end).
		if ( preg_match( "/\\btag\\b\\s+([^\\?\\.!\\n\\r]+)/i", $q, $m ) ) {
			return trim( (string) $m[1] );
		}
		return '';
	}

	private static function find_tag_in_list( array $tags, $needle ) {
		$needle = strtolower( trim( (string) $needle ) );
		if ( $needle === '' ) {
			return null;
		}
		foreach ( $tags as $t ) {
			if ( ! is_array( $t ) ) {
				continue;
			}
			$name = isset( $t['name'] ) ? strtolower( trim( (string) $t['name'] ) ) : '';
			$slug = isset( $t['slug'] ) ? strtolower( trim( (string) $t['slug'] ) ) : '';
			if ( $needle === $name || $needle === $slug ) {
				return $t;
			}
		}
		// Second pass: substring match for convenience.
		foreach ( $tags as $t ) {
			if ( ! is_array( $t ) ) {
				continue;
			}
			$name = isset( $t['name'] ) ? strtolower( (string) $t['name'] ) : '';
			$slug = isset( $t['slug'] ) ? strtolower( (string) $t['slug'] ) : '';
			if ( $name !== '' && strpos( $name, $needle ) !== false ) {
				return $t;
			}
			if ( $slug !== '' && strpos( $slug, $needle ) !== false ) {
				return $t;
			}
		}
		return null;
	}

	/**
	 * Try to build a deterministic answer; return null if not applicable.
	 *
	 * @param string $question         Original question.
	 * @param array  $validated_intent Validated intent.
	 * @param array  $results_for_prompt Tool results array (tool/arguments/result entries).
	 * @return string|null
	 */
	public static function maybe_compose( $question, array $validated_intent, array $results_for_prompt ) {
		// Empty orders list short-circuit.
		if ( count( $results_for_prompt ) === 1 ) {
			$single = $results_for_prompt[0];
			$res    = $single['result'] ?? null;
			$args   = $single['arguments'] ?? array();

			// Unsupported entity short-circuit (feature request).
			if ( is_array( $res ) && isset( $res['error'] ) && $res['error'] === true && ( $res['can_submit_request'] ?? false ) === true ) {
				$message = isset( $res['message'] ) ? (string) $res['message'] : __( 'This request is not currently supported.', 'dataviz-ai-woocommerce' );
				$prompt  = isset( $res['submission_prompt'] ) ? (string) $res['submission_prompt'] : '';
				return trim( $message . "\n\n" . $prompt );
			}

			// Out-of-stock products: compose deterministic list to avoid blank stock quantities.
			$wants_out_of_stock = ( isset( $validated_intent['entity'] ) && $validated_intent['entity'] === 'stock' )
				&& ( isset( $validated_intent['filters']['stock_status'] ) && $validated_intent['filters']['stock_status'] === 'outofstock' );

			if ( $wants_out_of_stock && is_array( $res ) && ( empty( $res ) || isset( $res[0]['id'] ) ) ) {
				if ( empty( $res ) ) {
					return __( 'There are no out-of-stock products.', 'dataviz-ai-woocommerce' );
				}

				$lines = array();
				foreach ( $res as $idx => $p ) {
					if ( ! is_array( $p ) ) {
						continue;
					}
					$name = isset( $p['name'] ) ? (string) $p['name'] : __( '(Unnamed product)', 'dataviz-ai-woocommerce' );
					$sku  = isset( $p['sku'] ) ? (string) $p['sku'] : '';
					$manage_stock = isset( $p['manage_stock'] ) ? (bool) $p['manage_stock'] : false;
					$qty = array_key_exists( 'stock_quantity', $p ) ? $p['stock_quantity'] : null;

					$qty_text = $manage_stock ? ( $qty === null ? '0' : (string) $qty ) : __( 'N/A', 'dataviz-ai-woocommerce' );
					$sku_text = $sku !== '' ? ' (SKU: ' . $sku . ')' : '';

					$lines[] = sprintf( '%d. %s%s — Qty: %s', (int) ( $idx + 1 ), $name, $sku_text, $qty_text );
				}

				return __( 'Out-of-stock products:', 'dataviz-ai-woocommerce' ) . "\n" . implode( "\n", $lines );
			}

			if ( is_array( $res ) && ! isset( $res['error'] ) && isset( $res['orders'] ) && is_array( $res['orders'] ) && empty( $res['orders'] ) ) {
				$period_text = '';
				if ( isset( $args['filters']['date_from'], $args['filters']['date_to'] ) ) {
					$period_text = ' ' . sprintf(
						__( 'in the period from %s to %s', 'dataviz-ai-woocommerce' ),
						$args['filters']['date_from'],
						$args['filters']['date_to']
					);
				} elseif ( isset( $validated_intent['filters']['date_from'], $validated_intent['filters']['date_to'] ) ) {
					$period_text = ' ' . sprintf(
						__( 'in the period from %s to %s', 'dataviz-ai-woocommerce' ),
						$validated_intent['filters']['date_from'],
						$validated_intent['filters']['date_to']
					);
				} else {
					$period_text = ' ' . __( 'for the requested period', 'dataviz-ai-woocommerce' );
				}
				return sprintf( __( 'There are no orders%s.', 'dataviz-ai-woocommerce' ), $period_text );
			}

			// Top customers (customer statistics) short-circuit.
			$is_customer_stats = ( isset( $validated_intent['entity'], $validated_intent['operation'] ) && $validated_intent['entity'] === 'customers' && $validated_intent['operation'] === 'statistics' );
			if ( $is_customer_stats && is_array( $res ) && ! isset( $res['error'] ) && isset( $res['customers'] ) && is_array( $res['customers'] ) ) {
				if ( empty( $res['customers'] ) ) {
					// Prefer tool message if present.
					if ( isset( $res['message'] ) && is_string( $res['message'] ) && $res['message'] !== '' ) {
						return $res['message'];
					}
					return __( 'No customers found matching the criteria.', 'dataviz-ai-woocommerce' );
				}

				$lines = array();
				foreach ( $res['customers'] as $idx => $c ) {
					if ( ! is_array( $c ) ) {
						continue;
					}
					$email = isset( $c['email'] ) ? (string) $c['email'] : '';
					$first = isset( $c['first_name'] ) ? trim( (string) $c['first_name'] ) : '';
					$last  = isset( $c['last_name'] ) ? trim( (string) $c['last_name'] ) : '';
					$name  = trim( $first . ' ' . $last );
					if ( $name === '' ) {
						$name = isset( $c['username'] ) ? (string) $c['username'] : __( '(Unknown customer)', 'dataviz-ai-woocommerce' );
					}

					$total_spent = isset( $c['total_spent'] ) ? (float) $c['total_spent'] : 0.0;
					$order_count = isset( $c['order_count'] ) ? (int) $c['order_count'] : 0;
					$spent_str   = self::format_currency( $total_spent );

					$who = $name;
					if ( $email !== '' ) {
						$who .= ' <' . $email . '>';
					}
					$lines[] = sprintf( '%d. %s — %s (%d orders)', (int) ( $idx + 1 ), $who, $spent_str, $order_count );
				}

				$header = __( 'Top customers by total spend:', 'dataviz-ai-woocommerce' );
				return $header . "\n" . implode( "\n", $lines );
			}

			// Tag count questions: "How many products have the tag 'X'?"
			$is_tag_list = ( isset( $validated_intent['entity'], $validated_intent['operation'] ) && $validated_intent['entity'] === 'tags' && $validated_intent['operation'] === 'list' );
			$is_how_many = (bool) preg_match( '/\b(how\s+many|count|number\s+of)\b/i', (string) $question );
			if ( $is_tag_list && $is_how_many && is_array( $res ) && ( empty( $res ) || isset( $res[0]['id'] ) ) ) {
				$tag_query = self::extract_tag_from_question( $question );
				if ( $tag_query !== '' ) {
					$tag = self::find_tag_in_list( $res, $tag_query );
					if ( is_array( $tag ) ) {
						$count = isset( $tag['count'] ) ? (int) $tag['count'] : 0;
						return sprintf(
							/* translators: %1$d: number of products, %2$s: tag name */
							__( 'There are %1$d products with the tag "%2$s".', 'dataviz-ai-woocommerce' ),
							$count,
							$tag['name'] ?? $tag_query
						);
					}
					return sprintf(
						/* translators: %s: tag name */
						__( 'I couldn’t find a product tag named "%s".', 'dataviz-ai-woocommerce' ),
						$tag_query
					);
				}
			}

			// Order statistics revenue/total short-circuit (orders only).
			$is_order_stats = ( isset( $validated_intent['entity'] ) && $validated_intent['entity'] === 'orders' );
			if ( $is_order_stats && is_array( $res ) && ! isset( $res['error'] ) && isset( $res['summary'] ) && is_array( $res['summary'] ) && array_key_exists( 'total_revenue', $res['summary'] ) ) {
				$total_revenue = (float) $res['summary']['total_revenue'];
				$total_orders  = array_key_exists( 'total_orders', $res['summary'] ) ? (int) $res['summary']['total_orders'] : null;

				$period_text = '';
				if ( isset( $res['date_range'] ) && is_array( $res['date_range'] ) ) {
					$date_from = $res['date_range']['from'] ?? '';
					$date_to   = $res['date_range']['to'] ?? '';
					if ( $date_from && $date_to ) {
						$from_timestamp = strtotime( $date_from );
						$to_timestamp   = strtotime( $date_to );
						if ( $from_timestamp && $to_timestamp ) {
							$from_formatted = date_i18n( 'F j', $from_timestamp );
							$to_formatted   = date_i18n( 'F j, Y', $to_timestamp );
							$period_text    = sprintf( ' %s', sprintf( __( 'from %s to %s', 'dataviz-ai-woocommerce' ), $from_formatted, $to_formatted ) );
						}
					}
				}

				$amount_str = self::format_currency( $total_revenue );
				$answer = sprintf(
					__( 'The total revenue generated%s is %s.', 'dataviz-ai-woocommerce' ),
					$period_text ? ' ' . $period_text : '',
					$amount_str
				);

				if ( null !== $total_orders ) {
					if ( $total_orders === 0 ) {
						$answer .= ' ' . __( 'There are no orders in this period.', 'dataviz-ai-woocommerce' );
					} elseif ( 1 === $total_orders ) {
						$answer .= ' ' . __( 'There is 1 order in this period.', 'dataviz-ai-woocommerce' );
					} else {
						$answer .= ' ' . sprintf( __( 'There are %d orders in this period.', 'dataviz-ai-woocommerce' ), $total_orders );
					}
				}

				return $answer;
			}
		}

		return null;
	}

	private static function format_currency( $amount ) {
		$amount = (float) $amount;
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( html_entity_decode( wc_price( $amount ) ) );
		}
		return '$' . number_format_i18n( $amount, 2 );
	}
}

