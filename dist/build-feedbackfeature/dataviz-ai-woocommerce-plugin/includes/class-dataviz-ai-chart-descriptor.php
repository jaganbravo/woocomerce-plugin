<?php
/**
 * Builds a structured chart descriptor from validated intent and tool results.
 *
 * The frontend renders this descriptor directly — no question parsing needed.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Chart_Descriptor {

	/**
	 * Build a chart descriptor from intent + tool execution results.
	 *
	 * @param array $intent       Validated intent array.
	 * @param array $tool_results Array of { tool, arguments, result } entries from execute_all.
	 * @return array|null Chart descriptor or null when no chart is appropriate.
	 */
	public static function build( array $intent, array $tool_results ) {
		$question = $intent['_question'] ?? '';
		if ( ! self::question_wants_chart( $question ) ) {
			return null;
		}

		$entity    = $intent['entity'] ?? '';
		$operation = $intent['operation'] ?? '';
		$dims      = $intent['dimensions'] ?? array();
		$metrics   = $intent['metrics'] ?? array();

		$result = self::first_successful_result( $tool_results );
		if ( null === $result ) {
			return null;
		}

		if ( $entity === 'orders' && $operation === 'by_period' ) {
			return self::build_period_chart( $result, $dims, $intent );
		}

		if ( $entity === 'orders' && $operation === 'statistics' ) {
			if ( in_array( 'category', $dims, true ) || ( ( $intent['filters']['group_by'] ?? '' ) === 'category' ) ) {
				return self::build_category_pie( $result );
			}
			if ( in_array( 'status', $dims, true ) ) {
				return self::build_status_pie( $result );
			}
			if ( ! empty( $result['status_breakdown'] ) ) {
				return self::build_status_pie( $result );
			}
		}

		if ( in_array( $entity, array( 'inventory', 'stock' ), true ) ) {
			return self::build_inventory_chart( $result );
		}

		if ( $entity === 'products' && in_array( 'top_products', $metrics, true ) ) {
			return self::build_top_products_chart( $result );
		}

		if ( $entity === 'customers' && $operation === 'statistics' ) {
			return self::build_top_customers_chart( $result );
		}

		return null;
	}

	// ------------------------------------------------------------------
	// Period charts (bar for month, line for day/week/hour)
	// ------------------------------------------------------------------

	private static function build_period_chart( $result, array $dims, array $intent ) {
		if ( ! is_array( $result ) || empty( $result ) ) {
			return null;
		}

		$period = 'day';
		foreach ( array( 'month', 'week', 'hour', 'day' ) as $p ) {
			if ( in_array( $p, $dims, true ) ) {
				$period = $p;
				break;
			}
		}

		$chart_type = in_array( $period, array( 'month' ), true ) ? 'bar' : 'line';

		$labels = array();
		$values = array();
		foreach ( $result as $row ) {
			$label = $row['period'] ?? $row['date'] ?? $row['label'] ?? '';
			$labels[] = self::format_period_label( $label, $period );
			$values[] = (float) ( $row['revenue'] ?? $row['total'] ?? $row['order_count'] ?? 0 );
		}

		if ( empty( $labels ) ) {
			return null;
		}

		$has_revenue = false;
		foreach ( $result as $row ) {
			if ( isset( $row['revenue'] ) || isset( $row['total'] ) ) {
				$has_revenue = true;
				break;
			}
		}

		$y_label  = $has_revenue ? 'Revenue ($)' : 'Orders';
		$format   = $has_revenue ? 'currency' : 'number';
		$ds_label = $has_revenue ? 'Revenue' : 'Orders';

		$title_period = ucfirst( $period ) . 'ly';
		$title        = $title_period . ' ' . ( $has_revenue ? 'Revenue' : 'Orders' );

		return array(
			'chart_type'   => $chart_type,
			'title'        => $title,
			'labels'       => $labels,
			'datasets'     => array(
				array(
					'label' => $ds_label,
					'data'  => $values,
				),
			),
			'x_axis_label' => ucfirst( $period ),
			'y_axis_label' => $y_label,
			'format'       => $format,
		);
	}

	private static function format_period_label( $raw, $period ) {
		if ( empty( $raw ) ) {
			return '—';
		}
		switch ( $period ) {
			case 'month':
				$ts = strtotime( $raw . '-01' );
				return $ts ? date( 'M Y', $ts ) : $raw;
			case 'week':
				return 'Wk ' . $raw;
			case 'hour':
				return $raw . ':00';
			case 'day':
			default:
				$ts = strtotime( $raw );
				return $ts ? date( 'M j', $ts ) : $raw;
		}
	}

	// ------------------------------------------------------------------
	// Category pie
	// ------------------------------------------------------------------

	private static function build_category_pie( $result ) {
		$breakdown = $result['category_breakdown'] ?? array();
		if ( empty( $breakdown ) ) {
			return null;
		}

		$labels = array();
		$values = array();
		foreach ( $breakdown as $row ) {
			$labels[] = $row['category_name'] ?? 'Uncategorized';
			$values[] = (float) ( $row['revenue'] ?? $row['order_count'] ?? 0 );
		}

		$has_nonzero = false;
		foreach ( $values as $v ) {
			if ( $v > 0 ) {
				$has_nonzero = true;
				break;
			}
		}
		if ( ! $has_nonzero ) {
			return null;
		}

		return array(
			'chart_type'   => 'pie',
			'title'        => 'Sales by Product Category',
			'labels'       => $labels,
			'datasets'     => array(
				array(
					'label' => 'Revenue',
					'data'  => $values,
				),
			),
			'x_axis_label' => null,
			'y_axis_label' => null,
			'format'       => 'currency',
		);
	}

	// ------------------------------------------------------------------
	// Status pie
	// ------------------------------------------------------------------

	private static function build_status_pie( $result ) {
		$breakdown = $result['status_breakdown'] ?? array();
		if ( empty( $breakdown ) ) {
			return null;
		}

		$labels = array();
		$values = array();
		foreach ( $breakdown as $row ) {
			$status   = $row['status'] ?? 'unknown';
			$labels[] = ucfirst( str_replace( array( 'wc-', '-' ), array( '', ' ' ), $status ) );
			$values[] = (int) ( $row['count'] ?? 0 );
		}

		return array(
			'chart_type'   => 'pie',
			'title'        => 'Order Status Distribution',
			'labels'       => $labels,
			'datasets'     => array(
				array(
					'label' => 'Orders',
					'data'  => $values,
				),
			),
			'x_axis_label' => null,
			'y_axis_label' => null,
			'format'       => 'number',
		);
	}

	// ------------------------------------------------------------------
	// Inventory pie (stock-level groups)
	// ------------------------------------------------------------------

	private static function build_inventory_chart( $result ) {
		$products = array();
		if ( isset( $result['products'] ) && is_array( $result['products'] ) ) {
			$products = $result['products'];
		} elseif ( is_array( $result ) && ! empty( $result ) && isset( $result[0]['name'] ) ) {
			$products = $result;
		}
		if ( empty( $products ) ) {
			return null;
		}

		$groups = array();
		foreach ( $products as $p ) {
			$qty = $p['stock_quantity'] ?? null;
			if ( null === $qty ) {
				$group = 'No Stock Mgmt';
			} elseif ( (int) $qty === 0 ) {
				$group = 'Out of Stock';
			} elseif ( (int) $qty < 10 ) {
				$group = 'Low (1-9)';
			} elseif ( (int) $qty < 50 ) {
				$group = 'Medium (10-49)';
			} else {
				$group = 'High (50+)';
			}
			$groups[ $group ] = ( $groups[ $group ] ?? 0 ) + 1;
		}

		return array(
			'chart_type'   => 'pie',
			'title'        => 'Inventory Distribution',
			'labels'       => array_keys( $groups ),
			'datasets'     => array(
				array(
					'label' => 'Products',
					'data'  => array_values( $groups ),
				),
			),
			'x_axis_label' => null,
			'y_axis_label' => null,
			'format'       => 'number',
		);
	}

	// ------------------------------------------------------------------
	// Top products (horizontal bar)
	// ------------------------------------------------------------------

	private static function build_top_products_chart( $result ) {
		$products = is_array( $result ) ? $result : array();
		if ( empty( $products ) ) {
			return null;
		}

		$products = array_filter( $products, function ( $p ) {
			return ( $p['total_sales'] ?? 0 ) > 0;
		} );
		usort( $products, function ( $a, $b ) {
			return ( $b['total_sales'] ?? 0 ) - ( $a['total_sales'] ?? 0 );
		} );
		$products = array_slice( $products, 0, 10 );

		if ( empty( $products ) ) {
			return null;
		}

		$labels = array();
		$values = array();
		foreach ( $products as $p ) {
			$name     = $p['name'] ?? '—';
			$labels[] = mb_strlen( $name ) > 25 ? mb_substr( $name, 0, 22 ) . '...' : $name;
			$values[] = (int) ( $p['total_sales'] ?? 0 );
		}

		return array(
			'chart_type'   => 'horizontalBar',
			'title'        => 'Top Products by Sales',
			'labels'       => $labels,
			'datasets'     => array(
				array(
					'label' => 'Units Sold',
					'data'  => $values,
				),
			),
			'x_axis_label' => 'Units Sold',
			'y_axis_label' => 'Product',
			'format'       => 'number',
		);
	}

	// ------------------------------------------------------------------
	// Top customers (bar)
	// ------------------------------------------------------------------

	private static function build_top_customers_chart( $result ) {
		$customers = $result['customers'] ?? array();
		if ( empty( $customers ) ) {
			return null;
		}

		$customers = array_slice( $customers, 0, 10 );
		$labels    = array();
		$values    = array();

		foreach ( $customers as $c ) {
			$name     = trim( ( $c['first_name'] ?? '' ) . ' ' . ( $c['last_name'] ?? '' ) );
			if ( empty( $name ) ) {
				$name = $c['email'] ?? ( '#' . ( $c['id'] ?? '?' ) );
			}
			$labels[] = mb_strlen( $name ) > 20 ? mb_substr( $name, 0, 17 ) . '...' : $name;
			$values[] = (float) ( $c['total_spent'] ?? 0 );
		}

		if ( empty( array_filter( $values ) ) ) {
			return null;
		}

		return array(
			'chart_type'   => 'bar',
			'title'        => 'Top Customers by Spend',
			'labels'       => $labels,
			'datasets'     => array(
				array(
					'label' => 'Total Spent',
					'data'  => $values,
				),
			),
			'x_axis_label' => 'Customer',
			'y_axis_label' => 'Spent ($)',
			'format'       => 'currency',
		);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private static function first_successful_result( array $tool_results ) {
		foreach ( $tool_results as $entry ) {
			$r = $entry['result'] ?? null;
			if ( is_array( $r ) && ! isset( $r['error'] ) ) {
				return $r;
			}
		}
		return null;
	}

	/**
	 * Return true only when the user explicitly asks for a chart / graph / visualization.
	 */
	private static function question_wants_chart( $question ) {
		if ( empty( $question ) ) {
			return false;
		}
		$lower    = strtolower( $question );
		$keywords = array( 'chart', 'graph', 'pie', 'bar', 'line chart', 'visualize', 'visualization', 'plot', 'diagram' );
		foreach ( $keywords as $kw ) {
			if ( strpos( $lower, $kw ) !== false ) {
				return true;
			}
		}
		return false;
	}
}
