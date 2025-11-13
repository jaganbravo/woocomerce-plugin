<?php
/**
 * Plugin Name: Dataviz AI for WooCommerce
 * Plugin URI: https://example.com
 * Description: Sample AI-assisted analytics plugin scaffold for WooCommerce stores.
 * Version: 0.1.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: dataviz-ai-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DATAVIZ_AI_WC_VERSION', '0.1.0' );
define( 'DATAVIZ_AI_WC_PLUGIN_FILE', __FILE__ );
define( 'DATAVIZ_AI_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DATAVIZ_AI_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DATAVIZ_AI_WC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

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
			esc_html__( 'Dataviz AI for WooCommerce requires WooCommerce to be installed and active.', 'dataviz-ai-woocommerce' ),
			esc_html__( 'Plugin activation error', 'dataviz-ai-woocommerce' ),
			array( 'back_link' => true )
		);
	}

	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'dataviz_ai_wc_activate' );

/**
 * Fired during plugin deactivation.
 */
function dataviz_ai_wc_deactivate() {
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
					esc_html__( 'Dataviz AI for WooCommerce', 'dataviz-ai-woocommerce' ),
					wp_kses_post(
						sprintf(
							/* translators: %s link to WooCommerce plugin. */
							__( 'requires WooCommerce to be installed and active. <a href="%s" target="_blank" rel="noopener noreferrer">Install WooCommerce</a>.', 'dataviz-ai-woocommerce' ),
							esc_url( 'https://woocommerce.com/' )
						)
					)
				);
			}
		);

		return;
	}

	load_plugin_textdomain( 'dataviz-ai-woocommerce', false, dirname( DATAVIZ_AI_WC_PLUGIN_BASENAME ) . '/languages' );

	$loader = new Dataviz_AI_Loader();
	$loader->run();
}

add_action( 'plugins_loaded', 'dataviz_ai_wc_init', 20 );

