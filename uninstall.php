<?php
/**
 * Uninstall script for WooCommerce Extension Example
 *
 * This file is executed when the plugin is uninstalled.
 *
 * @package WooCommerce_Extension_Example
 */

// Exit if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options
delete_option( 'wce_version' );
delete_option( 'wce_settings' );

// Delete plugin transients
delete_transient( 'wce_transient' );

// Clear any cached data
// wp_cache_flush();

// Drop custom database tables if created
// global $wpdb;
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wce_table_name" );

// Remove any scheduled cron jobs
// wp_clear_scheduled_hook( 'wce_cron_hook' );

