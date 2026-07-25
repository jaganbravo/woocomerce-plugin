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
				"SELECT id, message_type, message_content, metadata, created_at 
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
				"SELECT id, session_id, message_type, message_content, metadata, created_at 
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

