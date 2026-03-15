<?php
/**
 * Unified support request storage — combines feature requests and failed questions.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Support_Requests {

	const TYPE_FEATURE  = 'feature_request';
	const TYPE_FAILED   = 'failed_question';

	const STATUS_PENDING  = 'pending';
	const STATUS_RESOLVED = 'resolved';
	const STATUS_WONT_FIX = 'wont_fix';

	/**
	 * @return string Full table name with prefix.
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'dataviz_ai_support_requests';
	}

	/**
	 * Create the table (safe to call multiple times via dbDelta).
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			type varchar(30) NOT NULL DEFAULT 'feature_request',
			question text DEFAULT NULL,
			entity_type varchar(100) NOT NULL DEFAULT '',
			error_reason text DEFAULT NULL,
			raw_intent longtext DEFAULT NULL,
			description text DEFAULT NULL,
			user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			user_email varchar(255) DEFAULT NULL,
			user_name varchar(255) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			admin_notes text DEFAULT NULL,
			vote_count int(11) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			resolved_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY type (type),
			KEY entity_type (entity_type),
			KEY status (status),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Ensure the table exists (lazy creation for existing installs).
	 *
	 * @return void
	 */
	protected static function maybe_create_table() {
		global $wpdb;
		$table = self::table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			self::create_table();
		}
	}

	/**
	 * Insert a support request.
	 *
	 * @param array $args {
	 *     @type string $type         'feature_request' or 'failed_question'.
	 *     @type string $question     Original user question.
	 *     @type string $entity_type  Entity keyword / category.
	 *     @type string $error_reason Pipeline error reason (for failed questions).
	 *     @type string $raw_intent   JSON string of raw LLM intent.
	 *     @type string $description  Human-readable description.
	 *     @type int    $user_id      WordPress user ID.
	 * }
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public static function insert( array $args ) {
		global $wpdb;

		self::maybe_create_table();

		$type        = sanitize_text_field( $args['type'] ?? self::TYPE_FAILED );
		$question    = sanitize_textarea_field( $args['question'] ?? '' );
		$entity_type = sanitize_text_field( $args['entity_type'] ?? '' );
		$user_id     = absint( $args['user_id'] ?? get_current_user_id() );

		// De-duplicate: if same user asked same question (exact match), increment vote.
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM %i WHERE question = %s AND user_id = %d AND status = %s LIMIT 1",
			self::table_name(),
			$question,
			$user_id,
			self::STATUS_PENDING
		) );

		if ( $existing ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE %i SET vote_count = vote_count + 1 WHERE id = %d",
				self::table_name(),
				$existing
			) );
			return (int) $existing;
		}

		$user       = $user_id > 0 ? get_userdata( $user_id ) : null;
		$user_email = $user ? $user->user_email : '';
		$user_name  = $user ? $user->display_name : __( 'Guest', 'dataviz-ai-woocommerce' );

		$result = $wpdb->insert(
			self::table_name(),
			array(
				'type'         => $type,
				'question'     => $question,
				'entity_type'  => $entity_type,
				'error_reason' => sanitize_textarea_field( $args['error_reason'] ?? '' ),
				'raw_intent'   => $args['raw_intent'] ?? null,
				'description'  => sanitize_textarea_field( $args['description'] ?? '' ),
				'user_id'      => $user_id,
				'user_email'   => sanitize_email( $user_email ),
				'user_name'    => sanitize_text_field( $user_name ),
				'status'       => self::STATUS_PENDING,
				'vote_count'   => 1,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Convenience: store a failed question from the orchestrator.
	 *
	 * @param string      $question     User question.
	 * @param string      $error_reason Why it failed.
	 * @param string|null $raw_intent   Raw LLM intent JSON.
	 * @param int         $user_id      User ID.
	 * @return int|false
	 */
	public static function store_failed_question( $question, $error_reason = '', $raw_intent = null, $user_id = 0 ) {
		return self::insert( array(
			'type'         => self::TYPE_FAILED,
			'question'     => $question,
			'entity_type'  => 'failed_question',
			'error_reason' => $error_reason,
			'raw_intent'   => $raw_intent,
			'user_id'      => $user_id > 0 ? $user_id : get_current_user_id(),
		) );
	}

	/**
	 * Convenience: store a feature request.
	 *
	 * @param string $entity_type Requested entity.
	 * @param int    $user_id     User ID.
	 * @param string $description Description.
	 * @param string $question    Original question.
	 * @return int|false
	 */
	public static function store_feature_request( $entity_type, $user_id = 0, $description = '', $question = '' ) {
		return self::insert( array(
			'type'        => self::TYPE_FEATURE,
			'question'    => $question,
			'entity_type' => $entity_type,
			'description' => $description,
			'user_id'     => $user_id > 0 ? $user_id : get_current_user_id(),
		) );
	}

	/**
	 * Query requests with filters.
	 *
	 * @param array $args {
	 *     @type string $type   Filter by type ('all', 'feature_request', 'failed_question').
	 *     @type string $status Filter by status ('all', 'pending', 'resolved', 'wont_fix').
	 *     @type string $search Search in question/entity_type/description.
	 *     @type string $orderby Column to sort by.
	 *     @type string $order   ASC or DESC.
	 *     @type int    $limit   Max rows.
	 *     @type int    $offset  Offset for pagination.
	 * }
	 * @return array
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$type    = $args['type'] ?? 'all';
		$status  = $args['status'] ?? 'all';
		$search  = $args['search'] ?? '';
		$orderby = $args['orderby'] ?? 'created_at';
		$order   = strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
		$limit   = absint( $args['limit'] ?? 50 );
		$offset  = absint( $args['offset'] ?? 0 );

		$allowed_orderby = array( 'id', 'type', 'entity_type', 'status', 'vote_count', 'created_at', 'resolved_at' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'created_at';
		}

		$where = array( '1=1' );
		$values = array();

		if ( 'all' !== $type ) {
			$where[]  = 'type = %s';
			$values[] = $type;
		}
		if ( 'all' !== $status ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}
		if ( $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(question LIKE %s OR entity_type LIKE %s OR description LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$table     = self::table_name();

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$values[] = $limit;
		$values[] = $offset;

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	/**
	 * Count requests matching filters (for pagination).
	 *
	 * @param string $type   Type filter.
	 * @param string $status Status filter.
	 * @return int
	 */
	public static function count( $type = 'all', $status = 'all' ) {
		global $wpdb;

		$where  = array( '1=1' );
		$values = array();

		if ( 'all' !== $type ) {
			$where[]  = 'type = %s';
			$values[] = $type;
		}
		if ( 'all' !== $status ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}

		$where_sql = implode( ' AND ', $where );
		$table     = self::table_name();
		$sql       = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get a single request by ID.
	 *
	 * @param int $id Request ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d",
			self::table_name(),
			absint( $id )
		), ARRAY_A );
	}

	/**
	 * Update request status.
	 *
	 * @param int    $id          Request ID.
	 * @param string $status      New status.
	 * @param string $admin_notes Optional admin notes.
	 * @return bool
	 */
	public static function update_status( $id, $status, $admin_notes = null ) {
		global $wpdb;

		$allowed = array( self::STATUS_PENDING, self::STATUS_RESOLVED, self::STATUS_WONT_FIX );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$data   = array( 'status' => $status );
		$format = array( '%s' );

		if ( $status !== self::STATUS_PENDING ) {
			$data['resolved_at'] = current_time( 'mysql' );
			$format[]            = '%s';
		}
		if ( null !== $admin_notes ) {
			$data['admin_notes'] = sanitize_textarea_field( $admin_notes );
			$format[]            = '%s';
		}

		return false !== $wpdb->update( self::table_name(), $data, array( 'id' => absint( $id ) ), $format, array( '%d' ) );
	}

	/**
	 * Bulk update status.
	 *
	 * @param array  $ids    Array of request IDs.
	 * @param string $status New status.
	 * @return int Number of rows updated.
	 */
	public static function bulk_update_status( array $ids, $status ) {
		$updated = 0;
		foreach ( $ids as $id ) {
			if ( self::update_status( (int) $id, $status ) ) {
				$updated++;
			}
		}
		return $updated;
	}
}
