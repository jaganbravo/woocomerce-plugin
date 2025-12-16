<?php
/**
 * Loader for Dataviz AI WooCommerce plugin.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-api-client.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-data-fetcher.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-chat-history.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-feature-requests.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-admin.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-chat-widget.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-ajax-handler.php';

/**
 * Coordinates plugin components.
 */
class Dataviz_AI_Loader {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $plugin_name = 'dataviz-ai-woocommerce';

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Admin component.
	 *
	 * @var Dataviz_AI_Admin
	 */
	protected $admin;

	/**
	 * AJAX handler.
	 *
	 * @var Dataviz_AI_AJAX_Handler
	 */
	protected $ajax;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->version = defined( 'DATAVIZ_AI_WC_VERSION' ) ? DATAVIZ_AI_WC_VERSION : '0.1.0';
		$this->init_components();
	}

	/**
	 * Instantiate plugin components.
	 *
	 * @return void
	 */
	protected function init_components() {
		$api_client   = new Dataviz_AI_API_Client();
		$data_fetcher = new Dataviz_AI_Data_Fetcher();

		$this->admin = new Dataviz_AI_Admin(
			$this->plugin_name,
			$this->version,
			$data_fetcher,
			$api_client
		);

		$this->ajax = new Dataviz_AI_AJAX_Handler(
			$this->plugin_name,
			$data_fetcher,
			$api_client
		);

		new Dataviz_AI_Chat_Widget( $this->plugin_name, $this->version, $api_client );
	}

	/**
	 * Register WordPress hooks for the plugin.
	 *
	 * @return void
	 */
	public function run() {
		$this->define_admin_hooks();
		$this->define_ajax_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Register admin-facing hooks.
	 *
	 * @return void
	 */
	protected function define_admin_hooks() {
		add_action( 'admin_menu', array( $this->admin, 'register_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	protected function define_ajax_hooks() {
		add_action( 'wp_ajax_dataviz_ai_analyze', array( $this->ajax, 'handle_analysis_request' ) );
		add_action( 'wp_ajax_nopriv_dataviz_ai_analyze', array( $this->ajax, 'handle_analysis_request' ) );

		add_action( 'wp_ajax_dataviz_ai_chat', array( $this->ajax, 'handle_chat_request' ) );
		add_action( 'wp_ajax_nopriv_dataviz_ai_chat', array( $this->ajax, 'handle_chat_request' ) );

		add_action( 'wp_ajax_dataviz_ai_get_history', array( $this->ajax, 'handle_get_history_request' ) );

		add_action( 'wp_ajax_dataviz_ai_submit_feature_request', array( $this->ajax, 'handle_submit_feature_request' ) );

		add_action( 'wp_ajax_dataviz_ai_get_inventory_chart', array( $this->ajax, 'handle_get_inventory_chart' ) );
	}

	/**
	 * Register public hooks.
	 *
	 * @return void
	 */
	protected function define_public_hooks() {
		add_action( 'wp_enqueue_scripts', array( 'Dataviz_AI_Chat_Widget', 'register_assets' ) );
	}
}

