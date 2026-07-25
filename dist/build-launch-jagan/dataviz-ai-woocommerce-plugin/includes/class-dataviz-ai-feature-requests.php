<?php
/**
 * Feature request management for Dataviz AI WooCommerce plugin.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages feature request storage and retrieval.
 */
class Dataviz_AI_Feature_Requests {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'dataviz_ai_feature_requests';
	}

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'dataviz_ai_feature_requests';
	}

	/**
	 * Create the feature requests table.
	 *
	 * @return void
	 */
	public function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $this->get_table_name();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_type varchar(50) NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			user_email varchar(255) DEFAULT NULL,
			user_name varchar(255) DEFAULT NULL,
			description text DEFAULT NULL,
			status varchar(20) DEFAULT 'pending',
			vote_count int(11) DEFAULT 1,
			voters longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY entity_type (entity_type),
			KEY status (status),
			KEY user_id (user_id),
			KEY created_at (created_at),
			KEY vote_count (vote_count)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop the feature requests table.
	 *
	 * @return void
	 */
	public function drop_table() {
		global $wpdb;
		$table_name = $this->get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * Submit a feature request.
	 *
	 * @param string $entity_type Entity type requested (e.g., 'reviews').
	 * @param int    $user_id     User ID.
	 * @param string $description Optional description.
	 * @return int|false Request ID on success, false on failure.
	 */
	public function submit_request( $entity_type, $user_id = 0, $description = '' ) {
		global $wpdb;

		// Check if request already exists for this entity type from this user.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->get_table_name()} WHERE entity_type = %s AND user_id = %d AND status != 'rejected' LIMIT 1",
				$entity_type,
				$user_id
			)
		);

		if ( $existing ) {
			// User already requested this, just increment vote count.
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$this->get_table_name()} SET vote_count = vote_count + 1, updated_at = %s WHERE id = %d",
					current_time( 'mysql' ),
					$existing
				)
			);
			return $existing;
		}

		// Get user info.
		$user = $user_id > 0 ? get_userdata( $user_id ) : null;
		$user_email = $user ? $user->user_email : '';
		$user_name = $user ? $user->display_name : __( 'Guest', 'dataviz-ai-woocommerce' );

		$data = array(
			'entity_type' => sanitize_text_field( $entity_type ),
			'user_id'     => $user_id,
			'user_email'  => sanitize_email( $user_email ),
			'user_name'   => sanitize_text_field( $user_name ),
			'description' => sanitize_textarea_field( $description ),
			'status'      => 'pending',
			'vote_count'  => 1,
			'voters'      => wp_json_encode( array( $user_id ) ),
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$this->get_table_name(),
			$data,
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( $result ) {
			return $wpdb->insert_id;
		}

		// Log the database error if available.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf(
				'[Dataviz AI] Failed to insert feature request - DB Error: %s, Entity: %s, User: %d',
				$wpdb->last_error ?: 'Unknown error',
				$entity_type,
				$user_id
			) );
		}

		return false;
	}

	/**
	 * Get feature requests.
	 *
	 * @param string $status Status filter ('all', 'pending', 'in_progress', 'completed', 'rejected').
	 * @param int    $limit  Limit results.
	 * @return array
	 */
	public function get_requests( $status = 'all', $limit = 50 ) {
		global $wpdb;

		$where = '';
		if ( 'all' !== $status ) {
			$where = $wpdb->prepare( "WHERE status = %s", $status );
		}

		$limit = absint( $limit );
		$results = $wpdb->get_results(
			"SELECT * FROM {$this->get_table_name()} {$where} ORDER BY vote_count DESC, created_at DESC LIMIT {$limit}",
			ARRAY_A
		);

		// Decode voters JSON.
		foreach ( $results as &$result ) {
			if ( ! empty( $result['voters'] ) ) {
				$result['voters'] = json_decode( $result['voters'], true ) ?: array();
			} else {
				$result['voters'] = array();
			}
		}

		return $results;
	}

	/**
	 * Get request count for an entity type.
	 *
	 * @param string $entity_type Entity type.
	 * @return int
	 */
	public function get_request_count( $entity_type ) {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(vote_count) FROM {$this->get_table_name()} WHERE entity_type = %s AND status != 'rejected'",
				$entity_type
			)
		);

		return (int) $count;
	}

	/**
	 * Update request status.
	 *
	 * @param int    $request_id Request ID.
	 * @param string $status     New status.
	 * @return bool
	 */
	public function update_status( $request_id, $status ) {
		global $wpdb;

		$allowed_statuses = array( 'pending', 'in_progress', 'completed', 'rejected' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return false;
		}

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $request_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get request by ID.
	 *
	 * @param int $request_id Request ID.
	 * @return array|null
	 */
	public function get_request( $request_id ) {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} WHERE id = %d",
				$request_id
			),
			ARRAY_A
		);

		if ( $result && ! empty( $result['voters'] ) ) {
			$result['voters'] = json_decode( $result['voters'], true ) ?: array();
		} elseif ( $result ) {
			$result['voters'] = array();
		}

		return $result;
	}
}

