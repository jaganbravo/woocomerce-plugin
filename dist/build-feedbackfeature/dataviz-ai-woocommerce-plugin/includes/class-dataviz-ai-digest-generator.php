<?php
/**
 * Builds email digest content by querying the Data Fetcher.
 *
 * Reuses the existing pipeline (Data_Fetcher → tool calls → aggregation)
 * so the digest numbers are identical to what the chat would answer.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Digest_Generator {

	/**
	 * @var Dataviz_AI_Data_Fetcher
	 */
	private $fetcher;

	public function __construct( Dataviz_AI_Data_Fetcher $fetcher ) {
		$this->fetcher = $fetcher;
	}

	/**
	 * Build the full digest payload for a given digest config.
	 *
	 * @param object $digest Row from the digests table.
	 * @return array Keyed by section slug, each value is an array of data.
	 */
	public function build( $digest ) {
		$range    = $this->date_range_for_frequency( $digest->frequency );
		$sections = is_array( $digest->sections ) ? $digest->sections : array();
		$output   = array(
			'meta' => array(
				'digest_name' => $digest->digest_name,
				'frequency'   => $digest->frequency,
				'date_from'   => $range['from'],
				'date_to'     => $range['to'],
				'generated'   => current_time( 'mysql' ),
			),
		);

		foreach ( $sections as $section ) {
			$method = 'section_' . $section;
			if ( method_exists( $this, $method ) ) {
				$output[ $section ] = $this->$method( $range );
			}
		}

		return $output;
	}

	/**
	 * Compute the look-back date range for the digest frequency.
	 *
	 * @param string $frequency daily|weekly|monthly.
	 * @return array { from: string, to: string } in Y-m-d format.
	 */
	private function date_range_for_frequency( $frequency ) {
		$now = current_time( 'timestamp' );
		$to  = date( 'Y-m-d', $now );

		switch ( $frequency ) {
			case 'daily':
				$from = date( 'Y-m-d', $now - DAY_IN_SECONDS );
				break;
			case 'monthly':
				$from = date( 'Y-m-d', strtotime( '-1 month', $now ) );
				break;
			case 'weekly':
			default:
				$from = date( 'Y-m-d', $now - ( 7 * DAY_IN_SECONDS ) );
				break;
		}

		return array( 'from' => $from, 'to' => $to );
	}

	// ------------------------------------------------------------------
	// Section builders — each returns structured data, NOT HTML.
	// The email template handles presentation.
	// ------------------------------------------------------------------

	private function section_revenue_summary( array $range ) {
		$stats = $this->fetcher->get_order_statistics( array(
			'date_from' => $range['from'],
			'date_to'   => $range['to'],
		) );

		$summary = $stats['summary'] ?? array();

		return array(
			'total_revenue'    => (float) ( $summary['total_revenue'] ?? 0 ),
			'total_orders'     => (int) ( $summary['total_orders'] ?? 0 ),
			'avg_order_value'  => (float) ( $summary['avg_order_value'] ?? 0 ),
			'unique_customers' => (int) ( $summary['unique_customers'] ?? 0 ),
		);
	}

	private function section_order_breakdown( array $range ) {
		$stats = $this->fetcher->get_order_statistics( array(
			'date_from' => $range['from'],
			'date_to'   => $range['to'],
		) );

		return $stats['status_breakdown'] ?? array();
	}

	private function section_top_products( array $range ) {
		$stats = $this->fetcher->get_order_statistics( array(
			'date_from' => $range['from'],
			'date_to'   => $range['to'],
			'group_by'  => 'category',
		) );

		$breakdown = $stats['category_breakdown'] ?? array();
		$rows     = array();
		foreach ( $breakdown as $row ) {
			$rows[] = array(
				'name'          => $row['category_name'] ?? '—',
				'order_count'   => (int) ( $row['order_count'] ?? 0 ),
				'revenue'       => (float) ( $row['revenue'] ?? 0 ),
			);
		}
		return array_slice( $rows, 0, 5 );
	}

	private function section_low_stock( array $range ) {
		return $this->fetcher->get_low_stock_products( 10 );
	}

	private function section_top_customers( array $range ) {
		$result = $this->fetcher->get_customer_statistics( array(
			'date_from' => $range['from'],
			'date_to'   => $range['to'],
			'limit'     => 5,
		) );

		$customers = $result['customers'] ?? array();
		foreach ( $customers as &$c ) {
			$c['name'] = trim( ( $c['first_name'] ?? '' ) . ' ' . ( $c['last_name'] ?? '' ) ) ?: ( $c['email'] ?? '#' . ( $c['id'] ?? '?' ) );
		}
		return array( 'customers' => $customers );
	}

	private function section_refund_summary( array $range ) {
		$refunds = $this->fetcher->get_refunds( array(
			'date_from' => $range['from'],
			'date_to'   => $range['to'],
		) );

		$total_amount = 0;
		$count        = 0;
		if ( is_array( $refunds ) ) {
			foreach ( $refunds as $r ) {
				$count++;
				$total_amount += (float) ( $r['amount'] ?? $r['total'] ?? 0 );
			}
		}

		return array(
			'refund_count'   => $count,
			'total_refunded' => $total_amount,
		);
	}
}
