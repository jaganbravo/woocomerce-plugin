<?php
/**
 * Email Digests model – stores and manages scheduled digest configurations.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Email_Digests {

	const FREQ_DAILY   = 'daily';
	const FREQ_WEEKLY  = 'weekly';
	const FREQ_MONTHLY = 'monthly';

	const STATUS_ACTIVE   = 'active';
	const STATUS_PAUSED   = 'paused';

	/**
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'dataviz_ai_email_digests';
	}

	/**
	 * Create the digests table.
	 */
	public static function create_table() {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
			digest_name   VARCHAR(120)    NOT NULL DEFAULT '',
			frequency     VARCHAR(20)     NOT NULL DEFAULT 'weekly',
			day_of_week   TINYINT UNSIGNED NOT NULL DEFAULT 1,
			day_of_month  TINYINT UNSIGNED NOT NULL DEFAULT 1,
			send_hour     TINYINT UNSIGNED NOT NULL DEFAULT 9,
			recipients    TEXT            NOT NULL,
			sections      TEXT            NOT NULL,
			status        VARCHAR(20)     NOT NULL DEFAULT 'active',
			last_sent_at  DATETIME        DEFAULT NULL,
			next_run_at   DATETIME        DEFAULT NULL,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_status_next (status, next_run_at),
			KEY idx_user (user_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Ensure table exists (lazy creation).
	 */
	private static function maybe_create_table() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			self::create_table();
		}
	}

	/**
	 * Get valid frequency values.
	 *
	 * @return array
	 */
	public static function frequencies() {
		return array(
			self::FREQ_DAILY   => __( 'Daily', 'dataviz-ai-woocommerce' ),
			self::FREQ_WEEKLY  => __( 'Weekly', 'dataviz-ai-woocommerce' ),
			self::FREQ_MONTHLY => __( 'Monthly', 'dataviz-ai-woocommerce' ),
		);
	}

	/**
	 * Available digest sections the user can toggle.
	 *
	 * @return array Keyed by section slug, value is label.
	 */
	public static function available_sections() {
		return array(
			'revenue_summary'  => __( 'Revenue Summary', 'dataviz-ai-woocommerce' ),
			'order_breakdown'  => __( 'Order Status Breakdown', 'dataviz-ai-woocommerce' ),
			'top_products'     => __( 'Top-Selling Products', 'dataviz-ai-woocommerce' ),
			'low_stock'        => __( 'Low-Stock Alerts', 'dataviz-ai-woocommerce' ),
			'top_customers'    => __( 'Top Customers', 'dataviz-ai-woocommerce' ),
			'refund_summary'   => __( 'Refund Summary', 'dataviz-ai-woocommerce' ),
		);
	}

	/**
	 * Insert a new digest configuration.
	 *
	 * @param array $data Digest fields.
	 * @return int|false Inserted ID or false.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		self::maybe_create_table();

		$defaults = array(
			'user_id'      => get_current_user_id(),
			'digest_name'  => __( 'Weekly Sales Digest', 'dataviz-ai-woocommerce' ),
			'frequency'    => self::FREQ_WEEKLY,
			'day_of_week'  => 1,
			'day_of_month' => 1,
			'send_hour'    => 9,
			'recipients'   => wp_json_encode( array() ),
			'sections'     => wp_json_encode( array_keys( self::available_sections() ) ),
			'status'       => self::STATUS_ACTIVE,
			'next_run_at'  => null,
		);

		$row = wp_parse_args( $data, $defaults );

		if ( is_array( $row['recipients'] ) ) {
			$row['recipients'] = wp_json_encode( $row['recipients'] );
		}
		if ( is_array( $row['sections'] ) ) {
			$row['sections'] = wp_json_encode( $row['sections'] );
		}

		if ( empty( $row['next_run_at'] ) ) {
			$row['next_run_at'] = self::compute_next_run( $row['frequency'], (int) $row['day_of_week'], (int) $row['day_of_month'], (int) $row['send_hour'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert( self::table_name(), $row );
		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Get a single digest by ID.
	 *
	 * @param int $id Digest ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		if ( $row ) {
			$row->recipients = json_decode( $row->recipients, true ) ?: array();
			$row->sections   = json_decode( $row->sections, true ) ?: array();
		}
		return $row;
	}

	/**
	 * Update a digest.
	 *
	 * @param int   $id   Digest ID.
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		if ( isset( $data['recipients'] ) && is_array( $data['recipients'] ) ) {
			$data['recipients'] = wp_json_encode( $data['recipients'] );
		}
		if ( isset( $data['sections'] ) && is_array( $data['sections'] ) ) {
			$data['sections'] = wp_json_encode( $data['sections'] );
		}

		// Recompute next_run_at if schedule fields changed.
		if ( isset( $data['frequency'] ) || isset( $data['day_of_week'] ) || isset( $data['day_of_month'] ) || isset( $data['send_hour'] ) ) {
			$current = self::get( $id );
			if ( $current ) {
				$freq  = $data['frequency']    ?? $current->frequency;
				$dow   = (int) ( $data['day_of_week']  ?? $current->day_of_week );
				$dom   = (int) ( $data['day_of_month'] ?? $current->day_of_month );
				$hour  = (int) ( $data['send_hour']    ?? $current->send_hour );
				$data['next_run_at'] = self::compute_next_run( $freq, $dow, $dom, $hour );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->update( self::table_name(), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * Delete a digest.
	 *
	 * @param int $id Digest ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete( self::table_name(), array( 'id' => (int) $id ) );
	}

	/**
	 * Get all digests for the current user.
	 *
	 * @param int|null $user_id User ID or null for all.
	 * @return array
	 */
	public static function get_all( $user_id = null ) {
		global $wpdb;
		self::maybe_create_table();
		$table = self::table_name();

		if ( $user_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", $user_id ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
		}

		foreach ( $rows as &$row ) {
			$row->recipients = json_decode( $row->recipients, true ) ?: array();
			$row->sections   = json_decode( $row->sections, true ) ?: array();
		}
		return $rows;
	}

	/**
	 * Get all digests that are due to be sent.
	 *
	 * @return array
	 */
	public static function get_due_digests() {
		global $wpdb;
		self::maybe_create_table();
		$table = self::table_name();
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s AND next_run_at <= %s ORDER BY next_run_at ASC",
				self::STATUS_ACTIVE,
				$now
			)
		);

		foreach ( $rows as &$row ) {
			$row->recipients = json_decode( $row->recipients, true ) ?: array();
			$row->sections   = json_decode( $row->sections, true ) ?: array();
		}
		return $rows;
	}

	/**
	 * After sending a digest, advance next_run_at and update last_sent_at.
	 *
	 * @param int $id Digest ID.
	 */
	public static function mark_sent( $id ) {
		$digest = self::get( $id );
		if ( ! $digest ) {
			return;
		}

		$next = self::compute_next_run(
			$digest->frequency,
			(int) $digest->day_of_week,
			(int) $digest->day_of_month,
			(int) $digest->send_hour
		);

		self::update( $id, array(
			'last_sent_at' => current_time( 'mysql' ),
			'next_run_at'  => $next,
		) );
	}

	/**
	 * Compute the next run datetime based on frequency settings.
	 *
	 * @param string $frequency   daily|weekly|monthly.
	 * @param int    $day_of_week 0=Sun … 6=Sat (used for weekly).
	 * @param int    $day_of_month 1–28 (used for monthly).
	 * @param int    $send_hour   0–23.
	 * @return string MySQL datetime.
	 */
	public static function compute_next_run( $frequency, $day_of_week, $day_of_month, $send_hour ) {
		$now = current_time( 'timestamp' );

		switch ( $frequency ) {
			case self::FREQ_DAILY:
				$candidate = strtotime( sprintf( 'today %02d:00:00', $send_hour ), $now );
				if ( $candidate <= $now ) {
					$candidate = strtotime( '+1 day', $candidate );
				}
				break;

			case self::FREQ_WEEKLY:
				$days_map = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
				$day_name = $days_map[ $day_of_week % 7 ];
				$candidate = strtotime( "next {$day_name} " . sprintf( '%02d:00:00', $send_hour ), $now );
				$today_is = (int) date( 'w', $now );
				if ( $today_is === ( $day_of_week % 7 ) ) {
					$today_candidate = strtotime( sprintf( 'today %02d:00:00', $send_hour ), $now );
					if ( $today_candidate > $now ) {
						$candidate = $today_candidate;
					}
				}
				break;

			case self::FREQ_MONTHLY:
			default:
				$dom  = max( 1, min( 28, $day_of_month ) );
				$year  = (int) date( 'Y', $now );
				$month = (int) date( 'n', $now );
				$candidate = mktime( $send_hour, 0, 0, $month, $dom, $year );
				if ( $candidate <= $now ) {
					$candidate = mktime( $send_hour, 0, 0, $month + 1, $dom, $year );
				}
				break;
		}

		return date( 'Y-m-d H:i:s', $candidate );
	}
}
