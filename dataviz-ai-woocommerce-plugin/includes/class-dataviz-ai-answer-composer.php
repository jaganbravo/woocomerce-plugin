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
		if ( empty( $results_for_prompt ) ) {
			return null;
		}
		foreach ( $results_for_prompt as $single ) {
			if ( ! is_array( $single ) ) {
				continue;
			}
			$composed = self::compose_from_single_tool_result( $question, $validated_intent, $single );
			if ( is_string( $composed ) && $composed !== '' ) {
				return $composed;
			}
		}
		return null;
	}

	/**
	 * @param array $single One entry from results_for_prompt (tool, arguments, result).
	 * @return string|null
	 */
	private static function compose_from_single_tool_result( $question, array $validated_intent, array $single ) {
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

			// Orders list: deterministic count + rows (matches DB; avoids LLM inventing totals).
			$is_orders_list = isset( $validated_intent['entity'], $validated_intent['operation'] )
				&& $validated_intent['entity'] === 'orders'
				&& $validated_intent['operation'] === 'list';
			if ( $is_orders_list && is_array( $res ) && isset( $res['orders'] ) && is_array( $res['orders'] ) && ! empty( $res['orders'] ) && ! isset( $res['error'] ) ) {
				$shown = count( $res['orders'] );
				$total = isset( $res['total_matching'] ) ? max( $shown, (int) $res['total_matching'] ) : $shown;
				return self::compose_orders_list_answer( $res['orders'], $shown, $total );
			}

			// Top customers (customer statistics) short-circuit.
			$is_customer_stats = ( isset( $validated_intent['entity'], $validated_intent['operation'] ) && $validated_intent['entity'] === 'customers' && $validated_intent['operation'] === 'statistics' );
			if ( $is_customer_stats && is_array( $res ) && ! isset( $res['error'] ) && isset( $res['customers'] ) && is_array( $res['customers'] ) ) {
				if ( empty( $res['customers'] ) ) {
					$period_text = '';
					if ( isset( $res['date_range']['from'], $res['date_range']['to'] ) && $res['date_range']['from'] && $res['date_range']['to'] ) {
						$period_text = sprintf(
							' %s',
							sprintf( __( 'from %s to %s', 'dataviz-ai-woocommerce' ), $res['date_range']['from'], $res['date_range']['to'] )
						);
					}
					$is_how_many = (bool) preg_match( '/\b(how\s+many|count|number\s+of)\b/i', (string) $question );
					if ( $is_how_many ) {
						return sprintf( __( '0 customers have placed orders%s.', 'dataviz-ai-woocommerce' ), $period_text );
					}
					return sprintf( __( 'No customers found with orders%s.', 'dataviz-ai-woocommerce' ), $period_text );
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

			// Coupon usage in a period (coupons statistics).
			$is_coupon_usage = ( isset( $validated_intent['entity'], $validated_intent['operation'] ) && $validated_intent['entity'] === 'coupons' && $validated_intent['operation'] === 'statistics' );
			if ( $is_coupon_usage && is_array( $res ) && ! isset( $res['error'] ) && isset( $res['coupons'] ) && is_array( $res['coupons'] ) ) {
				$date_from = '';
				$date_to   = '';
				if ( isset( $res['date_range']['from'], $res['date_range']['to'] ) ) {
					$date_from = (string) $res['date_range']['from'];
					$date_to   = (string) $res['date_range']['to'];
				}
				$period = '';
				if ( $date_from && $date_to ) {
					$period = sprintf( __( 'from %1$s to %2$s', 'dataviz-ai-woocommerce' ), $date_from, $date_to );
				}

				if ( empty( $res['coupons'] ) ) {
					return $period
						? sprintf( __( 'No coupons were used %s.', 'dataviz-ai-woocommerce' ), $period )
						: __( 'No coupons were used in the requested period.', 'dataviz-ai-woocommerce' );
				}

				$lines = array();
				foreach ( $res['coupons'] as $idx => $c ) {
					if ( ! is_array( $c ) ) {
						continue;
					}
					$code = isset( $c['code'] ) ? (string) $c['code'] : '';
					$uses = isset( $c['uses'] ) ? (int) $c['uses'] : 0;
					if ( $code === '' ) {
						continue;
					}
					$lines[] = sprintf( '%d. %s — %d uses', (int) ( $idx + 1 ), $code, $uses );
				}

				$header = $period
					? sprintf( __( 'Coupons used %s:', 'dataviz-ai-woocommerce' ), $period )
					: __( 'Coupons used in the requested period:', 'dataviz-ai-woocommerce' );
				return $header . "\n" . implode( "\n", $lines );
			}

			// Refunds short-circuit.
			$is_refunds = ( isset( $validated_intent['entity'] ) && $validated_intent['entity'] === 'refunds' );
			if ( $is_refunds && is_array( $res ) ) {
				$is_how_many_refunds = (bool) preg_match( '/\b(how\s+many|count|total|number\s+of)\b/i', (string) $question );

				if ( empty( $res ) ) {
					$period_text = '';
					if ( isset( $validated_intent['filters']['date_from'], $validated_intent['filters']['date_to'] ) ) {
						$period_text = sprintf(
							' %s',
							sprintf( __( 'from %s to %s', 'dataviz-ai-woocommerce' ), $validated_intent['filters']['date_from'], $validated_intent['filters']['date_to'] )
						);
					}
					if ( $is_how_many_refunds ) {
						return sprintf( __( 'There are 0 refunds%s.', 'dataviz-ai-woocommerce' ), $period_text );
					}
					return sprintf( __( 'No refunds found%s.', 'dataviz-ai-woocommerce' ), $period_text );
				}

				$count = count( $res );
				$total_amount = 0.0;
				foreach ( $res as $r ) {
					if ( is_array( $r ) && isset( $r['amount'] ) ) {
						$total_amount += (float) $r['amount'];
					}
				}

				if ( $is_how_many_refunds ) {
					$amount_str = self::format_currency( $total_amount );
					$period_text = '';
					if ( isset( $validated_intent['filters']['date_from'], $validated_intent['filters']['date_to'] ) ) {
						$period_text = sprintf(
							' %s',
							sprintf( __( 'from %s to %s', 'dataviz-ai-woocommerce' ), $validated_intent['filters']['date_from'], $validated_intent['filters']['date_to'] )
						);
					}
					return sprintf(
						__( 'There are %1$d refunds%2$s, totaling %3$s.', 'dataviz-ai-woocommerce' ),
						$count,
						$period_text,
						$amount_str
					);
				}

				$lines = array();
				foreach ( $res as $idx => $r ) {
					if ( ! is_array( $r ) ) {
						continue;
					}
					$id     = isset( $r['id'] ) ? (int) $r['id'] : 0;
					$amount = isset( $r['amount'] ) ? self::format_currency( (float) $r['amount'] ) : self::format_currency( 0 );
					$reason = isset( $r['reason'] ) && $r['reason'] !== '' ? (string) $r['reason'] : __( 'No reason given', 'dataviz-ai-woocommerce' );
					$date   = isset( $r['date'] ) ? (string) $r['date'] : '';
					$parent = isset( $r['parent_order'] ) ? (int) $r['parent_order'] : 0;

					$line = sprintf( '%d. Refund #%d — %s', (int) ( $idx + 1 ), $id, $amount );
					if ( $parent ) {
						$line .= sprintf( ' (Order #%d)', $parent );
					}
					if ( $reason !== __( 'No reason given', 'dataviz-ai-woocommerce' ) ) {
						$line .= ' — ' . $reason;
					}
					if ( $date !== '' ) {
						$line .= ' — ' . $date;
					}
					$lines[] = $line;
				}

				$header = sprintf( __( '%d refunds found:', 'dataviz-ai-woocommerce' ), $count );
				return $header . "\n" . implode( "\n", $lines );
			}

			// Order statistics revenue/total short-circuit (orders only).
			$is_order_stats = ( isset( $validated_intent['entity'] ) && $validated_intent['entity'] === 'orders' );
			if ( $is_order_stats && is_array( $res ) && ! isset( $res['error'] ) && isset( $res['summary'] ) && is_array( $res['summary'] ) && array_key_exists( 'total_revenue', $res['summary'] ) ) {
				$total_revenue = (float) $res['summary']['total_revenue'];
				$total_orders  = array_key_exists( 'total_orders', $res['summary'] ) ? (int) $res['summary']['total_orders'] : null;

				// Detect status-specific count questions (e.g. "how many pending orders").
				$status_filter = $validated_intent['filters']['status'] ?? null;
				$asks_count    = (bool) preg_match( '/\b(how many|count|number of|total)\b/i', (string) $question );
				$asks_specific_status = (bool) preg_match(
					'/\b(pending|processing|on[\s-]?hold|completed|cancelled|canceled|refunded|failed|trash)\b/i',
					(string) $question,
					$status_match
				);

				if ( $asks_count && ( $status_filter || $asks_specific_status ) ) {
					$status_label = $status_filter ? ucfirst( $status_filter ) : ucfirst( $status_match[1] );
					$status_label = str_ireplace( array( 'on-hold', 'onhold', 'on hold' ), 'On-hold', $status_label );

					$count = $total_orders ?? 0;
					if ( empty( $status_filter ) && $asks_specific_status && ! empty( $res['status_breakdown'] ) && is_array( $res['status_breakdown'] ) && isset( $status_match[1] ) ) {
						$from_breakdown = self::count_for_status_in_breakdown( $res['status_breakdown'], $status_match[1] );
						if ( null !== $from_breakdown ) {
							$count = $from_breakdown;
						}
					}
					if ( 0 === $count ) {
						return sprintf(
							__( 'There are currently no %s orders.', 'dataviz-ai-woocommerce' ),
							strtolower( $status_label )
						);
					}
					$answer = sprintf(
						_n(
							'There is currently %d %s order.',
							'There are currently %d %s orders.',
							$count,
							'dataviz-ai-woocommerce'
						),
						$count,
						strtolower( $status_label )
					);
					$rev_for_status = null;
					if ( empty( $status_filter ) && $asks_specific_status && ! empty( $res['status_breakdown'] ) && isset( $status_match[1] ) ) {
						$rev_for_status = self::revenue_for_status_in_breakdown( $res['status_breakdown'], $status_match[1] );
					}
					if ( null !== $rev_for_status && $rev_for_status > 0 ) {
						$answer .= ' ' . sprintf(
							__( 'Their total value is %s.', 'dataviz-ai-woocommerce' ),
							self::format_currency( $rev_for_status )
						);
					} elseif ( $total_revenue > 0 && ! empty( $status_filter ) ) {
						$answer .= ' ' . sprintf(
							__( 'Their total value is %s.', 'dataviz-ai-woocommerce' ),
							self::format_currency( $total_revenue )
						);
					}
					return $answer;
				}

				// Sales by product category: empty category_breakdown → clear deterministic message (avoid mixing with product counts).
				$wants_sales_by_category = in_array( 'category', $validated_intent['dimensions'] ?? array(), true )
					|| ( isset( $validated_intent['filters']['group_by'] ) && $validated_intent['filters']['group_by'] === 'category' );
				$category_breakdown = $res['category_breakdown'] ?? array();
				if ( $wants_sales_by_category ) {
					if ( empty( $category_breakdown ) || ! is_array( $category_breakdown ) ) {
						$period_text = '';
						if ( isset( $res['date_range'] ) && is_array( $res['date_range'] ) ) {
							$df = $res['date_range']['from'] ?? '';
							$dt = $res['date_range']['to'] ?? '';
							if ( $df && $dt ) {
								$period_text = ' ' . sprintf(
									__( 'in the selected period (%s to %s)', 'dataviz-ai-woocommerce' ),
									$df,
									$dt
								);
							}
						}
						return sprintf(
							__( 'There are no sales records by product category%s. This can happen when there are no completed orders, or when ordered products are not assigned to categories.', 'dataviz-ai-woocommerce' ),
							$period_text
						);
					}
					// Non-empty category_breakdown: let LLM summarize; frontend will render pie chart from tool data.
					return null;
				}

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
					$used_status_line = false;
					if ( empty( $status_filter ) && $asks_specific_status && ! empty( $res['status_breakdown'] ) && isset( $status_match[1] ) ) {
						$bd_c = self::count_for_status_in_breakdown( $res['status_breakdown'], $status_match[1] );
						if ( null !== $bd_c ) {
							$used_status_line = true;
							$lbl              = strtolower( ucfirst( $status_match[1] ) );
							$lbl              = str_ireplace( array( 'on-hold', 'onhold' ), 'on-hold', $lbl );
							if ( 0 === $bd_c ) {
								$answer .= ' ' . sprintf( __( 'There are no %s orders in this period.', 'dataviz-ai-woocommerce' ), $lbl );
							} elseif ( 1 === $bd_c ) {
								$answer .= ' ' . sprintf( __( 'There is 1 %s order in this period.', 'dataviz-ai-woocommerce' ), $lbl );
							} else {
								$answer .= ' ' . sprintf( __( 'There are %1$d %2$s orders in this period.', 'dataviz-ai-woocommerce' ), $bd_c, $lbl );
							}
						}
					}
					if ( ! $used_status_line ) {
						if ( $total_orders === 0 ) {
							$answer .= ' ' . __( 'There are no orders in this period.', 'dataviz-ai-woocommerce' );
						} elseif ( 1 === $total_orders ) {
							$answer .= ' ' . __( 'There is 1 order in this period.', 'dataviz-ai-woocommerce' );
						} else {
							$answer .= ' ' . sprintf( __( 'There are %d orders in this period.', 'dataviz-ai-woocommerce' ), $total_orders );
						}
					}
				}

				// Append status breakdown when available and the question asks for it.
				$wants_status = (bool) preg_match( '/\b(status|statuses|by\s+status)\b/i', (string) $question );
				if ( $wants_status && ! empty( $res['status_breakdown'] ) && is_array( $res['status_breakdown'] ) ) {
					$status_lines = array();
					foreach ( $res['status_breakdown'] as $sb ) {
						if ( ! is_array( $sb ) ) {
							continue;
						}
						$s_name  = isset( $sb['status'] ) ? ucfirst( (string) $sb['status'] ) : __( 'Unknown', 'dataviz-ai-woocommerce' );
						$s_count = isset( $sb['count'] ) ? (int) $sb['count'] : 0;
						$s_rev   = isset( $sb['revenue'] ) ? self::format_currency( (float) $sb['revenue'] ) : self::format_currency( 0 );
						$status_lines[] = sprintf( '- %s: %d orders (%s)', $s_name, $s_count, $s_rev );
					}
					if ( ! empty( $status_lines ) ) {
						$answer .= "\n\n" . __( 'Breakdown by status:', 'dataviz-ai-woocommerce' ) . "\n" . implode( "\n", $status_lines );
					}
				}

				return $answer;
			}

		return null;
	}

	private static function normalize_order_status_slug( $raw ) {
		$raw = strtolower( trim( preg_replace( '/^wc[-]/', '', (string) $raw ) ) );
		$raw = str_replace( array( ' ', '_' ), '-', $raw );
		$map = array(
			'on-hold' => 'on-hold',
			'onhold'  => 'on-hold',
			'canceled' => 'cancelled',
		);
		return isset( $map[ $raw ] ) ? $map[ $raw ] : $raw;
	}

	/**
	 * @param array  $breakdown Rows with keys status, count.
	 * @param string $status_word From question regex, e.g. "pending".
	 * @return int|null
	 */
	private static function count_for_status_in_breakdown( array $breakdown, $status_word ) {
		$want = self::normalize_order_status_slug( $status_word );
		foreach ( $breakdown as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['status'], $row['count'] ) ) {
				continue;
			}
			if ( self::normalize_order_status_slug( $row['status'] ) === $want ) {
				return (int) $row['count'];
			}
		}
		return null;
	}

	/**
	 * @param array  $breakdown Rows with keys status, revenue.
	 * @param string $status_word From question.
	 * @return float|null
	 */
	private static function revenue_for_status_in_breakdown( array $breakdown, $status_word ) {
		$want = self::normalize_order_status_slug( $status_word );
		foreach ( $breakdown as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['status'] ) ) {
				continue;
			}
			if ( self::normalize_order_status_slug( $row['status'] ) === $want ) {
				return isset( $row['revenue'] ) ? (float) $row['revenue'] : 0.0;
			}
		}
		return null;
	}

	/**
	 * @param array $orders Formatted order rows from tool.
	 * @param int   $shown  Count returned (page size).
	 * @param int   $total  Total matching query.
	 * @return string
	 */
	private static function compose_orders_list_answer( array $orders, $shown, $total ) {
		$lines = array();
		foreach ( $orders as $idx => $o ) {
			if ( ! is_array( $o ) ) {
				continue;
			}
			$id     = isset( $o['id'] ) ? (int) $o['id'] : 0;
			$total_amt = isset( $o['total'] ) ? (float) $o['total'] : 0.0;
			$st = isset( $o['status'] ) ? (string) $o['status'] : '';
			$date   = isset( $o['date'] ) ? (string) $o['date'] : '';
			$date_short = $date !== '' ? preg_replace( '/T.*/', '', $date ) : '';
			$lines[]    = sprintf(
				'%d. %s — %s — %s — %s',
				(int) ( $idx + 1 ),
				sprintf( /* translators: %d order ID */ __( 'Order #%d', 'dataviz-ai-woocommerce' ), $id ),
				self::format_currency( $total_amt ),
				$st !== '' ? $st : '—',
				$date_short !== '' ? $date_short : '—'
			);
		}
		$header = '';
		if ( $total > $shown ) {
			$header = sprintf(
				/* translators: 1: number shown, 2: total matching */
				__( 'Showing %1$d of %2$d orders (newest first):', 'dataviz-ai-woocommerce' ),
				$shown,
				$total
			);
		} else {
			$header = sprintf(
				/* translators: %d order count */
				_n( '%d order:', '%d orders:', $total, 'dataviz-ai-woocommerce' ),
				$total
			);
		}
		return $header . "\n" . implode( "\n", $lines );
	}

	private static function format_currency( $amount ) {
		$amount = (float) $amount;
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( html_entity_decode( wc_price( $amount ) ) );
		}
		return '$' . number_format_i18n( $amount, 2 );
	}
}

