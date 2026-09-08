<?php
/**
 * Plugin Name: Unmai Analytix for WooCommerce
 * Plugin URI: https://profiles.wordpress.org/aprinston/
 * Description: Conversational AI analytics for WooCommerce — ask questions, get answers, charts, and email digests.
 * Version: 1.0.0
 * Author: Aprinston and JaganBravo
 * Author URI: https://profiles.wordpress.org/aprinston/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: unmai-analytix-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * WC requires at least: 6.0
 * WC tested up to: 10.7
 *
 * Internal identifiers (PHP classes Dataviz_AI_*, AJAX actions/nonces dataviz_ai_*,
 * constants DATAVIZ_AI_WC_*, option/table prefixes dataviz_ai_*) are frozen so
 * existing installs, stored data, and client scripts keep working.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DATAVIZ_AI_WC_VERSION', '1.0.0' );
define( 'DATAVIZ_AI_WC_PLUGIN_FILE', __FILE__ );
define( 'DATAVIZ_AI_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DATAVIZ_AI_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DATAVIZ_AI_WC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce features (HPOS, etc.) to prevent incompatibility notices.
 */
add_action(
	'before_woocommerce_init',
	static function() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Load configuration file if it exists.
 */
$dataviz_ai_wc_config_file = DATAVIZ_AI_WC_PLUGIN_DIR . 'config.php';
if ( file_exists( $dataviz_ai_wc_config_file ) ) {
	require_once $dataviz_ai_wc_config_file;
}

/**
 * Log debug messages when WP_DEBUG and WP_DEBUG_LOG are enabled.
 *
 * @param string $message Message to log.
 * @return void
 */
function dataviz_ai_wc_debug_log( $message ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG_LOG is on.
		error_log( $message );
	}
}

/**
 * Autoload dependencies.
 */
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-loader.php';

/**
 * Fired during plugin activation.
 */
function dataviz_ai_wc_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'Unmai Analytix for WooCommerce requires WooCommerce to be installed and active.', 'unmai-analytix-for-woocommerce' ),
			esc_html__( 'Plugin activation error', 'unmai-analytix-for-woocommerce' ),
			array( 'back_link' => true )
		);
	}

	// Create chat history table.
	require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-chat-history.php';
	$chat_history = new Dataviz_AI_Chat_History();
	$chat_history->create_table();

	// Create feature requests table.
	require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-feature-requests.php';
	$feature_requests = new Dataviz_AI_Feature_Requests();
	$feature_requests->create_table();

	// Create unified support requests table.
	require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-support-requests.php';
	Dataviz_AI_Support_Requests::create_table();

	// Create email digests table.
	require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-email-digests.php';
	Dataviz_AI_Email_Digests::create_table();

	// Schedule daily cleanup of old messages.
	if ( ! wp_next_scheduled( 'dataviz_ai_cleanup_chat_history' ) ) {
		wp_schedule_event( time(), 'daily', 'dataviz_ai_cleanup_chat_history' );
	}

	// Schedule digest cron.
	require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-digest-cron.php';
	Dataviz_AI_Digest_Cron::schedule();

	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'dataviz_ai_wc_activate' );

/**
 * Fired during plugin deactivation.
 */
function dataviz_ai_wc_deactivate() {
	// Clear scheduled cleanup task.
	wp_clear_scheduled_hook( 'dataviz_ai_cleanup_chat_history' );

	// Clear digest cron.
	require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-digest-cron.php';
	Dataviz_AI_Digest_Cron::unschedule();

	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'dataviz_ai_wc_deactivate' );

/**
 * Initialize the plugin.
 */
function dataviz_ai_wc_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			static function() {
				printf(
					'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
					esc_html__( 'Unmai Analytix for WooCommerce', 'unmai-analytix-for-woocommerce' ),
					wp_kses_post(
						sprintf(
							/* translators: %s link to WooCommerce plugin. */
							__( 'requires WooCommerce to be installed and active. <a href="%s" target="_blank" rel="noopener noreferrer">Install WooCommerce</a>.', 'unmai-analytix-for-woocommerce' ),
							esc_url( 'https://woocommerce.com/' )
						)
					)
				);
			}
		);

		return;
	}

	// Ensure chat history feedback columns exist (runs dbDelta when columns are missing).
	if ( is_admin() ) {
		require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-chat-history.php';
		( new Dataviz_AI_Chat_History() )->ensure_feedback_schema();
	}

	// Register cleanup hook.
	add_action( 'dataviz_ai_cleanup_chat_history', 'dataviz_ai_wc_cleanup_chat_history' );

	$loader = new Dataviz_AI_Loader();
	$loader->run();
}

/**
 * Cleanup old chat history (called by scheduled event).
 */
function dataviz_ai_wc_cleanup_chat_history() {
	require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-chat-history.php';
	$chat_history = new Dataviz_AI_Chat_History();
	$deleted = $chat_history->cleanup_old_messages();
	
	dataviz_ai_wc_debug_log( sprintf( '[Unmai Analytix] Cleaned up %d old chat history messages', $deleted ) );
}

add_action( 'plugins_loaded', 'dataviz_ai_wc_init', 20 );
add_action( 'admin_init', 'dataviz_ai_wc_redirect_legacy_admin_pages' );

/**
 * Redirect old dataviz-ai-* admin page slugs to unmai-analytix-* equivalents.
 *
 * @return void
 */
function dataviz_ai_wc_redirect_legacy_admin_pages() {
	if ( ! is_admin() || ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$map  = array(
		'dataviz-ai-faq'              => 'unmai-analytix-faq',
		'dataviz-ai-onboarding'       => 'unmai-analytix-onboarding',
		'dataviz-ai-digests'          => 'unmai-analytix-digests',
		'dataviz-ai-support-requests' => 'unmai-analytix-support-requests',
		'dataviz-ai-chat-feedback'    => 'unmai-analytix-chat-feedback',
		'dataviz-ai-feature-requests' => 'unmai-analytix-support-requests',
	);

	if ( ! isset( $map[ $page ] ) ) {
		return;
	}

	$query         = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$query['page'] = $map[ $page ];
	wp_safe_redirect( admin_url( 'admin.php?' . http_build_query( $query ) ) );
	exit;
}

/**
 * Register personal data exporters.
 *
 * @param array $exporters Existing exporters.
 * @return array
 */
function dataviz_ai_wc_register_personal_data_exporters( $exporters ) {
	$exporters['dataviz-ai-chat-history'] = array(
		'exporter_friendly_name' => __( 'Unmai Analytix Chat History', 'unmai-analytix-for-woocommerce' ),
		'callback'               => 'dataviz_ai_wc_chat_history_exporter',
	);
	$exporters['dataviz-ai-support-requests'] = array(
		'exporter_friendly_name' => __( 'Unmai Analytix Support Requests', 'unmai-analytix-for-woocommerce' ),
		'callback'               => 'dataviz_ai_wc_support_requests_exporter',
	);
	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'dataviz_ai_wc_register_personal_data_exporters' );

/**
 * Register personal data erasers.
 *
 * @param array $erasers Existing erasers.
 * @return array
 */
function dataviz_ai_wc_register_personal_data_erasers( $erasers ) {
	$erasers['dataviz-ai-chat-history'] = array(
		'eraser_friendly_name' => __( 'Unmai Analytix Chat History', 'unmai-analytix-for-woocommerce' ),
		'callback'             => 'dataviz_ai_wc_chat_history_eraser',
	);
	$erasers['dataviz-ai-support-requests'] = array(
		'eraser_friendly_name' => __( 'Unmai Analytix Support Requests', 'unmai-analytix-for-woocommerce' ),
		'callback'             => 'dataviz_ai_wc_support_requests_eraser',
	);
	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'dataviz_ai_wc_register_personal_data_erasers' );

/**
 * Export chat history for a user email.
 *
 * @param string $email_address Email address.
 * @param int    $page          Pagination page.
 * @return array
 */
function dataviz_ai_wc_chat_history_exporter( $email_address, $page = 1 ) {
	global $wpdb;
	$user = get_user_by( 'email', $email_address );
	if ( ! $user ) {
		return array( 'data' => array(), 'done' => true );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT id, session_id, message_type, message_content, created_at FROM ' . $wpdb->prefix . 'dataviz_ai_chat_history WHERE user_id = %d ORDER BY created_at ASC',
			$user->ID
		),
		ARRAY_A
	);

	$data = array();
	foreach ( $rows as $row ) {
		$data[] = array(
			'group_id'    => 'dataviz_ai_chat_history',
			'group_label' => __( 'Unmai Analytix Chat History', 'unmai-analytix-for-woocommerce' ),
			'item_id'     => 'dataviz-ai-chat-history-' . (int) $row['id'],
			'data'        => array(
				array( 'name' => __( 'Session ID', 'unmai-analytix-for-woocommerce' ), 'value' => (string) $row['session_id'] ),
				array( 'name' => __( 'Message Type', 'unmai-analytix-for-woocommerce' ), 'value' => (string) $row['message_type'] ),
				array( 'name' => __( 'Message', 'unmai-analytix-for-woocommerce' ), 'value' => (string) $row['message_content'] ),
				array( 'name' => __( 'Created At', 'unmai-analytix-for-woocommerce' ), 'value' => (string) $row['created_at'] ),
			),
		);
	}

	return array( 'data' => $data, 'done' => true );
}

/**
 * Export support requests for a user email.
 *
 * @param string $email_address Email address.
 * @param int    $page          Pagination page.
 * @return array
 */
function dataviz_ai_wc_support_requests_exporter( $email_address, $page = 1 ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT id, type, question, entity_type, description, status, created_at FROM ' . $wpdb->prefix . 'dataviz_ai_support_requests WHERE user_email = %s ORDER BY created_at ASC',
			$email_address
		),
		ARRAY_A
	);

	$data = array();
	foreach ( $rows as $row ) {
		$data[] = array(
			'group_id'    => 'dataviz_ai_support_requests',
			'group_label' => __( 'Unmai Analytix Support Requests', 'unmai-analytix-for-woocommerce' ),
			'item_id'     => 'dataviz-ai-support-request-' . (int) $row['id'],
			'data'        => array(
				array( 'name' => __( 'Type', 'unmai-analytix-for-woocommerce' ), 'value' => (string) $row['type'] ),
				array( 'name' => __( 'Question', 'unmai-analytix-for-woocommerce' ), 'value' => (string) $row['question'] ),
				array( 'name' => __( 'Entity Type', 'unmai-analytix-for-woocommerce' ), 'value' => (string) $row['entity_type'] ),
				array( 'name' => __( 'Description', 'unmai-analytix-for-woocommerce' ), 'value' => (string) $row['description'] ),
				array( 'name' => __( 'Status', 'unmai-analytix-for-woocommerce' ), 'value' => (string) $row['status'] ),
				array( 'name' => __( 'Created At', 'unmai-analytix-for-woocommerce' ), 'value' => (string) $row['created_at'] ),
			),
		);
	}

	return array( 'data' => $data, 'done' => true );
}

/**
 * Erase chat history for a user email.
 *
 * @param string $email_address Email address.
 * @param int    $page          Pagination page.
 * @return array
 */
function dataviz_ai_wc_chat_history_eraser( $email_address, $page = 1 ) {
	global $wpdb;
	$user = get_user_by( 'email', $email_address );
	if ( ! $user ) {
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$deleted = $wpdb->query(
		$wpdb->prepare(
			'DELETE FROM ' . $wpdb->prefix . 'dataviz_ai_chat_history WHERE user_id = %d',
			$user->ID
		)
	);

	return array(
		'items_removed'  => $deleted > 0,
		'items_retained' => false,
		'messages'       => array(),
		'done'           => true,
	);
}

/**
 * Erase support requests for a user email.
 *
 * @param string $email_address Email address.
 * @param int    $page          Pagination page.
 * @return array
 */
function dataviz_ai_wc_support_requests_eraser( $email_address, $page = 1 ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$deleted = $wpdb->query(
		$wpdb->prepare(
			'DELETE FROM ' . $wpdb->prefix . 'dataviz_ai_support_requests WHERE user_email = %s',
			$email_address
		)
	);

	return array(
		'items_removed'  => $deleted > 0,
		'items_retained' => false,
		'messages'       => array(),
		'done'           => true,
	);
}

/**
 * Add privacy policy helper text.
 *
 * @return void
 */
function dataviz_ai_wc_add_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content  = '<p>' . esc_html__( 'Unmai Analytix for WooCommerce stores chat history and support request data so administrators can review AI interactions and improve analytics workflows.', 'unmai-analytix-for-woocommerce' ) . '</p>';
	$content .= '<p>' . esc_html__( 'When generating answers, prompts and selected store aggregates may be sent to your configured AI provider (for example, OpenAI). Review provider terms and privacy policies before enabling production use.', 'unmai-analytix-for-woocommerce' ) . '</p>';
	$content .= '<p>' . esc_html__( 'The plugin integrates with WordPress personal data export and erasure tools for plugin-owned chat and support request records.', 'unmai-analytix-for-woocommerce' ) . '</p>';

	wp_add_privacy_policy_content(
		__( 'Unmai Analytix for WooCommerce', 'unmai-analytix-for-woocommerce' ),
		wp_kses_post( wpautop( $content, false ) )
	);
}
add_action( 'admin_init', 'dataviz_ai_wc_add_privacy_policy_content' );

