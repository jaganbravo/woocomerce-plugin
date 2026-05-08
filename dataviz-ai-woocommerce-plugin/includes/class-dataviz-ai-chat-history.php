<?php
/**
 * Chat history management for Dataviz AI WooCommerce plugin.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages AI chat history storage and retrieval.
 */
class Dataviz_AI_Chat_History {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Days to keep chat history.
	 *
	 * @var int
	 */
	private $retention_days = 5;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'dataviz_ai_chat_history';
	}

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'dataviz_ai_chat_history';
	}

	/**
	 * Create the chat history table.
	 *
	 * @return void
	 */
	public function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $this->get_table_name();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			session_id varchar(100) NOT NULL DEFAULT '',
			message_type varchar(20) NOT NULL DEFAULT 'user',
			message_content longtext NOT NULL,
			metadata longtext DEFAULT NULL,
			feedback_vote varchar(10) DEFAULT NULL,
			feedback_reason varchar(64) DEFAULT NULL,
			feedback_note text DEFAULT NULL,
			feedback_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY session_id (session_id),
			KEY created_at (created_at),
			KEY message_type (message_type)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Ensure feedback columns exist (dbDelta often omits ALTER on existing tables).
	 *
	 * @return void
	 */
	public function ensure_feedback_schema() {
		global $wpdb;

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$this->create_table();
			return;
		}

		$this->create_table();
		$this->add_feedback_columns_if_missing();
	}

	/**
	 * Add feedback columns with ALTER TABLE when dbDelta did not add them.
	 *
	 * @return void
	 */
	private function add_feedback_columns_if_missing() {
		static $complete = false;
		if ( $complete ) {
			return;
		}

		global $wpdb;
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$fields = $wpdb->get_col( "DESC `{$table}`", 0 );
		if ( ! is_array( $fields ) ) {
			return;
		}

		$have = array_fill_keys( array_map( 'strtolower', $fields ), true );

		if ( ! empty( $have['feedback_vote'] ) && ! empty( $have['feedback_reason'] ) && ! empty( $have['feedback_note'] ) && ! empty( $have['feedback_at'] ) ) {
			$complete = true;
			return;
		}

		if ( empty( $have['feedback_vote'] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `feedback_vote` varchar(10) NULL" );
		}
		if ( empty( $have['feedback_reason'] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `feedback_reason` varchar(64) NULL" );
		}
		if ( empty( $have['feedback_note'] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `feedback_note` text NULL" );
		}
		if ( empty( $have['feedback_at'] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `feedback_at` datetime NULL" );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$fields_after = $wpdb->get_col( "DESC `{$table}`", 0 );
		$have_after   = is_array( $fields_after ) ? array_fill_keys( array_map( 'strtolower', $fields_after ), true ) : array();

		if ( ! empty( $have_after['feedback_vote'] ) && ! empty( $have_after['feedback_reason'] ) && ! empty( $have_after['feedback_note'] ) && ! empty( $have_after['feedback_at'] ) ) {
			$complete = true;
		}

		if ( $wpdb->last_error && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[Dataviz AI] add_feedback_columns_if_missing: ' . $wpdb->last_error );
		}
	}

	/**
	 * Drop the chat history table.
	 *
	 * @return void
	 */
	public function drop_table() {
		global $wpdb;
		$table_name = $this->get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * Save a chat message.
	 *
	 * @param string $message_type   Message type: 'user' or 'ai'.
	 * @param string $message_content Message content.
	 * @param string $session_id      Optional session ID.
	 * @param array  $metadata        Optional metadata (e.g., API response details).
	 * @return int|false Message ID on success, false on failure.
	 */
	public function save_message( $message_type, $message_content, $session_id = '', $metadata = array() ) {
		global $wpdb;

		$user_id = get_current_user_id();
		
		// Debug: Log if user_id is 0 (not logged in)
		if ( $user_id === 0 && defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[Dataviz AI] Warning: Attempting to save message with user_id = 0 (user not logged in)' );
		}

		// Generate session ID if not provided.
		if ( empty( $session_id ) ) {
			$session_id = $this->get_or_create_session_id();
		}

		$data = array(
			'user_id'         => $user_id,
			'session_id'      => sanitize_text_field( $session_id ),
			'message_type'    => in_array( $message_type, array( 'user', 'ai' ), true ) ? $message_type : 'user',
			'message_content' => wp_kses_post( $message_content ),
			'metadata'         => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
			'created_at'      => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$this->get_table_name(),
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			// Log successful save if debug is enabled
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf(
					'[Dataviz AI] Message saved - ID: %d, Type: %s, User: %d, Session: %s',
					$wpdb->insert_id,
					$message_type,
					$user_id,
					substr( $session_id, 0, 20 )
				) );
			}
			return $wpdb->insert_id;
		}

		// Log error if save failed
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf(
				'[Dataviz AI] Failed to save message - Error: %s, User: %d',
				$wpdb->last_error ?: 'Unknown error',
				$user_id
			) );
		}

		return false;
	}

	/**
	 * Allowed feedback reason slugs (optional for down-votes).
	 *
	 * @return string[]
	 */
	public static function get_allowed_feedback_reasons() {
		return array( 'inaccurate', 'not_helpful', 'other' );
	}

	/**
	 * Update thumbs feedback for an AI message (current user must own the row).
	 *
	 * @param int         $message_id Message row ID.
	 * @param string      $vote       'up' or 'down'.
	 * @param string|null $reason     Optional slug from get_allowed_feedback_reasons().
	 * @param string|null $note       Optional short note (max length enforced).
	 * @return true|WP_Error
	 */
	public function update_feedback( $message_id, $vote, $reason = null, $note = null ) {
		global $wpdb;

		$message_id = (int) $message_id;
		if ( $message_id < 1 ) {
			return new WP_Error( 'dataviz_ai_feedback_invalid', __( 'Invalid message.', 'dataviz-ai-woocommerce' ) );
		}

		$vote = strtolower( (string) $vote );
		if ( ! in_array( $vote, array( 'up', 'down' ), true ) ) {
			return new WP_Error( 'dataviz_ai_feedback_invalid', __( 'Invalid feedback vote.', 'dataviz-ai-woocommerce' ) );
		}

		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return new WP_Error( 'dataviz_ai_feedback_auth', __( 'You must be logged in to send feedback.', 'dataviz-ai-woocommerce' ) );
		}

		$reason = is_string( $reason ) ? strtolower( trim( $reason ) ) : '';
		if ( $reason !== '' && ! in_array( $reason, self::get_allowed_feedback_reasons(), true ) ) {
			return new WP_Error( 'dataviz_ai_feedback_invalid', __( 'Invalid feedback reason.', 'dataviz-ai-woocommerce' ) );
		}

		$note_clean = '';
		if ( is_string( $note ) && $note !== '' ) {
			$note_clean = wp_strip_all_tags( $note );
			if ( strlen( $note_clean ) > 500 ) {
				$note_clean = substr( $note_clean, 0, 500 );
			}
		}

		$reason_db = ( $vote === 'down' && $reason !== '' ) ? $reason : null;
		$note_db   = ( $note_clean !== '' ) ? $note_clean : null;

		if ( $vote === 'up' ) {
			$reason_db = null;
			$note_db   = null;
		}

		$this->ensure_feedback_schema();
		$this->add_feedback_columns_if_missing();

		$updated = $wpdb->update(
			$this->get_table_name(),
			array(
				'feedback_vote'   => $vote,
				'feedback_reason' => $reason_db,
				'feedback_note'   => $note_db,
				'feedback_at'     => current_time( 'mysql' ),
			),
			array(
				'id'           => $message_id,
				'user_id'      => $user_id,
				'message_type' => 'ai',
			),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d', '%d', '%s' )
		);

		if ( false === $updated ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && $wpdb->last_error ) {
				error_log( '[Dataviz AI] update_feedback SQL error: ' . $wpdb->last_error );
			}
			return new WP_Error( 'dataviz_ai_feedback_db', __( 'Could not save feedback.', 'dataviz-ai-woocommerce' ) );
		}
		if ( 0 === $updated ) {
			return new WP_Error( 'dataviz_ai_feedback_not_found', __( 'Message not found or not eligible for feedback.', 'dataviz-ai-woocommerce' ) );
		}

		return true;
	}

	/**
	 * Admin: fetch one AI row by ID (any user).
	 *
	 * @param int $id Message row ID.
	 * @return array|null
	 */
	public function get_feedback_entry_by_id( $id ) {
		global $wpdb;
		$table = $this->get_table_name();
		$row   = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM `{$table}` WHERE id = %d AND message_type = %s",
			absint( $id ),
			'ai'
		), ARRAY_A );

		return $row ?: null;
	}

	/**
	 * Admin: paginated AI messages with thumbs feedback (all users).
	 *
	 * @param array $args Keys: limit, offset, search.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_feedback_entries_admin( array $args = array() ) {
		global $wpdb;

		$table  = $this->get_table_name();
		$limit  = max( 1, min( 100, absint( $args['limit'] ?? 20 ) ) );
		$offset = absint( $args['offset'] ?? 0 );
		$search = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';

		$sql  = "SELECT id, user_id, session_id, message_content, feedback_vote, feedback_reason, feedback_note, feedback_at, created_at FROM `{$table}` WHERE message_type = %s AND feedback_vote IN (%s, %s)";
		$prep = array( 'ai', 'up', 'down' );

		if ( $search !== '' ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$sql .= ' AND (message_content LIKE %s OR feedback_note LIKE %s OR CAST(user_id AS CHAR) LIKE %s)';
			$prep[] = $like;
			$prep[] = $like;
			$prep[] = $like;
		}

		$sql .= ' ORDER BY feedback_at DESC LIMIT %d OFFSET %d';
		$prep[] = $limit;
		$prep[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- assembled above with placeholders only.
		return $wpdb->get_results( $wpdb->prepare( $sql, $prep ), ARRAY_A ) ?: array();
	}

	/**
	 * Admin: count rows with feedback (for pagination).
	 *
	 * @param string $search Optional search string.
	 * @return int
	 */
	public function count_feedback_entries_admin( $search = '' ) {
		global $wpdb;

		$table = $this->get_table_name();
		$sql   = "SELECT COUNT(*) FROM `{$table}` WHERE message_type = %s AND feedback_vote IN (%s, %s)";
		$prep  = array( 'ai', 'up', 'down' );

		if ( $search !== '' ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
			$sql .= ' AND (message_content LIKE %s OR feedback_note LIKE %s OR CAST(user_id AS CHAR) LIKE %s)';
			$prep[] = $like;
			$prep[] = $like;
			$prep[] = $like;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $prep ) );
	}

	/**
	 * Email one chat feedback row to the plugin vendor inbox (wp_mail).
	 *
	 * @param int $message_id Chat history row ID (AI message).
	 * @return true|WP_Error
	 */
	public function email_feedback_to_vendor( $message_id ) {
		$to = Dataviz_AI_Support_Requests::get_vendor_support_email();
		if ( ! is_email( $to ) ) {
			return new WP_Error(
				'dataviz_cf_email_no_vendor',
				__( 'Set a vendor support email under Dataviz AI → Support & Requests first.', 'dataviz-ai-woocommerce' )
			);
		}

		$row = $this->get_feedback_entry_by_id( $message_id );
		if ( ! $row ) {
			return new WP_Error( 'dataviz_cf_not_found', __( 'Message not found.', 'dataviz-ai-woocommerce' ) );
		}

		$vote = $row['feedback_vote'] ?? '';
		if ( ! in_array( $vote, array( 'up', 'down' ), true ) ) {
			return new WP_Error( 'dataviz_cf_no_feedback', __( 'This message has no feedback to send.', 'dataviz-ai-woocommerce' ) );
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		/* translators: 1: site name, 2: message row ID */
		$subject = sprintf( __( '[%1$s] Dataviz AI chat feedback (message #%2$d)', 'dataviz-ai-woocommerce' ), $site_name, absint( $message_id ) );

		$body = $this->build_feedback_vendor_email_body( $row );

		$sent = wp_mail(
			$to,
			$subject,
			$body,
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);

		if ( ! $sent ) {
			return new WP_Error(
				'dataviz_cf_email_failed',
				__( 'WordPress could not send email. Check your site mail configuration.', 'dataviz-ai-woocommerce' )
			);
		}

		return true;
	}

	/**
	 * Plain-text body for vendor notification of chat feedback.
	 *
	 * @param array $row Full row from get_feedback_entry_by_id().
	 * @return string
	 */
	private function build_feedback_vendor_email_body( array $row ) {
		$lines   = array();
		$lines[] = __( 'A store administrator forwarded this Dataviz AI admin chat thumbs feedback.', 'dataviz-ai-woocommerce' );
		$lines[] = '';
		$lines[] = __( 'Site', 'dataviz-ai-woocommerce' ) . ': ' . home_url();
		$lines[] = __( 'Message ID', 'dataviz-ai-woocommerce' ) . ': #' . absint( $row['id'] ?? 0 );
		$lines[] = __( 'Session', 'dataviz-ai-woocommerce' ) . ': ' . sanitize_text_field( $row['session_id'] ?? '' );
		$uid     = absint( $row['user_id'] ?? 0 );
		$lines[] = __( 'User ID', 'dataviz-ai-woocommerce' ) . ': ' . $uid;
		$user    = $uid > 0 ? get_userdata( $uid ) : false;
		if ( $user ) {
			$lines[] = __( 'User', 'dataviz-ai-woocommerce' ) . ': ' . sanitize_text_field( $user->display_name );
			$lines[] = __( 'User email', 'dataviz-ai-woocommerce' ) . ': ' . sanitize_email( $user->user_email );
		}
		$lines[] = '';
		$lines[] = __( 'Vote', 'dataviz-ai-woocommerce' ) . ': ' . sanitize_text_field( $row['feedback_vote'] ?? '' );
		if ( ! empty( $row['feedback_reason'] ) ) {
			$lines[] = __( 'Reason', 'dataviz-ai-woocommerce' ) . ': ' . sanitize_text_field( $row['feedback_reason'] ?? '' );
		}
		if ( ! empty( $row['feedback_note'] ) ) {
			$lines[] = __( 'Note', 'dataviz-ai-woocommerce' ) . ': ' . wp_strip_all_tags( (string) $row['feedback_note'] );
		}
		$lines[] = __( 'Feedback time', 'dataviz-ai-woocommerce' ) . ': ' . sanitize_text_field( $row['feedback_at'] ?? '' );
		$lines[] = __( 'Message time', 'dataviz-ai-woocommerce' ) . ': ' . sanitize_text_field( $row['created_at'] ?? '' );
		$lines[] = '';
		$lines[] = __( 'Assistant reply', 'dataviz-ai-woocommerce' ) . ':';
		$content = wp_strip_all_tags( (string) ( $row['message_content'] ?? '' ) );
		if ( strlen( $content ) > 8000 ) {
			$content = substr( $content, 0, 8000 ) . '…';
		}
		$lines[] = $content;

		return implode( "\n", $lines );
	}

	/**
	 * Get or create a session ID for the current user.
	 *
	 * @return string
	 */
	private function get_or_create_session_id() {
		$session_key = 'dataviz_ai_session_id';
		$session_id  = get_user_meta( get_current_user_id(), $session_key, true );

		if ( empty( $session_id ) ) {
			$session_id = wp_generate_uuid4();
			update_user_meta( get_current_user_id(), $session_key, $session_id );
		}

		return $session_id;
	}

	/**
	 * Get chat history for a session.
	 *
	 * @param string $session_id Session ID.
	 * @param int    $limit      Number of messages to retrieve.
	 * @param int    $days       Number of days to look back (default: 5).
	 * @return array
	 */
	public function get_session_history( $session_id, $limit = 50, $days = 5 ) {
		global $wpdb;

		$table_name = $this->get_table_name();
		$user_id    = get_current_user_id();
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, message_type, message_content, metadata, created_at,
				feedback_vote, feedback_reason, feedback_note, feedback_at
				FROM {$table_name} 
				WHERE session_id = %s AND user_id = %d AND created_at >= %s
				ORDER BY created_at ASC 
				LIMIT %d",
				$session_id,
				$user_id,
				$cutoff_date,
				$limit
			),
			ARRAY_A
		);

		// Decode metadata.
		foreach ( $results as &$result ) {
			if ( ! empty( $result['metadata'] ) ) {
				$result['metadata'] = json_decode( $result['metadata'], true );
			}
		}

		return $results;
	}

	/**
	 * Get recent chat history for current user.
	 *
	 * @param int $limit Number of messages to retrieve.
	 * @param int $days  Number of days to look back (default: 5).
	 * @return array
	 */
	public function get_recent_history( $limit = 50, $days = 5 ) {
		global $wpdb;

		$table_name = $this->get_table_name();
		$user_id    = get_current_user_id();
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, session_id, message_type, message_content, metadata, created_at,
				feedback_vote, feedback_reason, feedback_note, feedback_at
				FROM {$table_name} 
				WHERE user_id = %d AND created_at >= %s
				ORDER BY created_at ASC 
				LIMIT %d",
				$user_id,
				$cutoff_date,
				$limit
			),
			ARRAY_A
		);

		// Decode metadata.
		foreach ( $results as &$result ) {
			if ( ! empty( $result['metadata'] ) ) {
				$result['metadata'] = json_decode( $result['metadata'], true );
			}
		}

		return $results;
	}

	/**
	 * Get all unique sessions for current user.
	 *
	 * @param int $limit Number of sessions to retrieve.
	 * @return array
	 */
	public function get_user_sessions( $limit = 20 ) {
		global $wpdb;

		$table_name = $this->get_table_name();
		$user_id    = get_current_user_id();

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT session_id, MAX(created_at) as last_message_at, COUNT(*) as message_count
				FROM {$table_name} 
				WHERE user_id = %d 
				GROUP BY session_id 
				ORDER BY last_message_at DESC 
				LIMIT %d",
				$user_id,
				$limit
			),
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Delete old chat history (older than retention period).
	 *
	 * @return int Number of rows deleted.
	 */
	public function cleanup_old_messages() {
		global $wpdb;

		$table_name = $this->get_table_name();
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$this->retention_days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < %s",
				$cutoff_date
			)
		);

		return $deleted;
	}

	/**
	 * Delete all chat history for a specific user.
	 *
	 * @param int $user_id User ID (defaults to current user).
	 * @return int Number of rows deleted.
	 */
	public function delete_user_history( $user_id = 0 ) {
		global $wpdb;

		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$table_name = $this->get_table_name();

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE user_id = %d",
				$user_id
			)
		);

		return $deleted;
	}

	/**
	 * Get statistics about chat history.
	 *
	 * @return array
	 */
	public function get_statistics() {
		global $wpdb;

		$table_name = $this->get_table_name();
		$user_id    = get_current_user_id();

		$stats = array(
			'total_messages'  => 0,
			'user_messages'   => 0,
			'ai_messages'     => 0,
			'total_sessions'  => 0,
			'oldest_message'  => null,
			'newest_message'  => null,
		);

		// Get total messages.
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
				$user_id
			)
		);
		$stats['total_messages'] = (int) $total;

		// Get message counts by type.
		$user_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND message_type = 'user'",
				$user_id
			)
		);
		$stats['user_messages'] = (int) $user_count;

		$ai_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND message_type = 'ai'",
				$user_id
			)
		);
		$stats['ai_messages'] = (int) $ai_count;

		// Get session count.
		$session_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id) FROM {$table_name} WHERE user_id = %d",
				$user_id
			)
		);
		$stats['total_sessions'] = (int) $session_count;

		// Get oldest and newest messages.
		$oldest = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(created_at) FROM {$table_name} WHERE user_id = %d",
				$user_id
			)
		);
		$stats['oldest_message'] = $oldest;

		$newest = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(created_at) FROM {$table_name} WHERE user_id = %d",
				$user_id
			)
		);
		$stats['newest_message'] = $newest;

		return $stats;
	}

	/**
	 * Set retention days.
	 *
	 * @param int $days Number of days to keep messages.
	 * @return void
	 */
	public function set_retention_days( $days ) {
		$this->retention_days = max( 1, (int) $days );
	}

	/**
	 * Get retention days.
	 *
	 * @return int
	 */
	public function get_retention_days() {
		return $this->retention_days;
	}
}

