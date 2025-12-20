<?php
/**
 * Temporary script to read existing API configuration from WordPress
 */

// Load WordPress
require_once __DIR__ . '/wordpress/wp-load.php';

// Get existing settings
$settings = get_option( 'dataviz_ai_wc_settings', array() );

$api_url = isset( $settings['api_url'] ) ? $settings['api_url'] : '';
$api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';

// Output as JSON
header( 'Content-Type: application/json' );
echo json_encode( array(
	'api_url' => $api_url,
	'api_key' => $api_key ? substr( $api_key, 0, 10 ) . '...' : '', // Only show first 10 chars for security
	'has_api_key' => ! empty( $api_key ),
	'has_api_url' => ! empty( $api_url ),
), JSON_PRETTY_PRINT );

