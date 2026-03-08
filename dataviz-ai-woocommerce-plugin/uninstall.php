<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all plugin data: custom database tables, options, user meta,
 * and scheduled events.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dataviz_ai_chat_history" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dataviz_ai_feature_requests" );

// Remove plugin options.
delete_option( 'dataviz_ai_wc_settings' );

// Remove user meta for all users.
$wpdb->query(
	"DELETE FROM {$wpdb->usermeta}
	WHERE meta_key IN (
		'dataviz_ai_session_id',
		'dataviz_ai_onboarding_completed',
		'dataviz_ai_onboarding_completed_at',
		'dataviz_ai_onboarding_version',
		'dataviz_ai_onboarding_skipped',
		'dataviz_ai_onboarding_skipped_at',
		'dataviz_ai_onboarding_current_step'
	)"
);

// Clear scheduled events.
wp_clear_scheduled_hook( 'dataviz_ai_cleanup_chat_history' );
