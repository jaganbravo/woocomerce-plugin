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
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-intent-classifier.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-intent-validator.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-execution-engine.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-answer-composer.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-prompt-template.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-intent-normalizer.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-stream-handler.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-tool-executor.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-chart-descriptor.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-intent-pipeline.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-query-orchestrator.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-support-requests.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-support-requests-admin.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-email-digests.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-digest-mailer.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-digest-generator.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-digest-email-template.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-digest-cron.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-digest-admin.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-admin.php';
require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-onboarding.php';
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

	/** @var Dataviz_AI_Admin */
	protected $admin;

	/** @var Dataviz_AI_Onboarding */
	protected $onboarding;

	/** @var Dataviz_AI_AJAX_Handler */
	protected $ajax;

	/** @var Dataviz_AI_Digest_Admin */
	protected $digest_admin;

	/** @var Dataviz_AI_Digest_Cron */
	protected $digest_cron;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->version = defined( 'DATAVIZ_AI_WC_VERSION' ) ? DATAVIZ_AI_WC_VERSION : '1.0.0';
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
		$chat_history = new Dataviz_AI_Chat_History();

		$stream_handler  = new Dataviz_AI_Stream_Handler( $api_client );
		$tool_executor   = new Dataviz_AI_Tool_Executor( $data_fetcher );
		$intent_pipeline = new Dataviz_AI_Intent_Pipeline( $api_client );

		$digest_generator = new Dataviz_AI_Digest_Generator( $data_fetcher );
		$this->digest_admin = new Dataviz_AI_Digest_Admin( $digest_generator );
		$this->digest_cron = new Dataviz_AI_Digest_Cron( $digest_generator );

		$orchestrator = new Dataviz_AI_Query_Orchestrator(
			$intent_pipeline,
			$tool_executor,
			$api_client,
			$stream_handler,
			$chat_history
		);
		$orchestrator->set_data_fetcher( $data_fetcher );

		$this->admin = new Dataviz_AI_Admin(
			$this->plugin_name,
			$this->version,
			$data_fetcher,
			$api_client
		);

		$this->onboarding = new Dataviz_AI_Onboarding(
			$this->plugin_name,
			$this->version,
			$api_client
		);

		$this->ajax = new Dataviz_AI_AJAX_Handler(
			$this->plugin_name,
			$data_fetcher,
			$api_client,
			$orchestrator,
			$intent_pipeline
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
		$this->digest_cron->init();
	}

	/**
	 * Register admin-facing hooks.
	 *
	 * @return void
	 */
	protected function define_admin_hooks() {
		add_action( 'admin_menu', array( $this->admin, 'register_menu_page' ) );
		add_action( 'admin_menu', array( 'Dataviz_AI_Support_Requests_Admin', 'register_submenu' ) );
		add_action( 'admin_menu', array( $this->digest_admin, 'register_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this->admin, 'hide_woocommerce_incompatibility_notice' ), 999 );
		add_action( 'admin_enqueue_scripts', array( 'Dataviz_AI_Support_Requests_Admin', 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this->digest_admin, 'enqueue_assets' ) );
		$this->onboarding->init();
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	protected function define_ajax_hooks() {
		add_action( 'wp_ajax_dataviz_ai_analyze', array( $this->ajax, 'handle_analysis_request' ) );

		add_action( 'wp_ajax_dataviz_ai_chat', array( $this->ajax, 'handle_chat_request' ) );

		add_action( 'wp_ajax_dataviz_ai_get_history', array( $this->ajax, 'handle_get_history_request' ) );

		add_action( 'wp_ajax_dataviz_ai_submit_feature_request', array( $this->ajax, 'handle_submit_feature_request' ) );

		add_action( 'wp_ajax_dataviz_ai_get_inventory_chart', array( $this->ajax, 'handle_get_inventory_chart' ) );

		// Debug / test helper: intent parsing output (admin only).
		add_action( 'wp_ajax_dataviz_ai_debug_intent', array( $this->ajax, 'handle_debug_intent_request' ) );

		add_action( 'wp_ajax_dataviz_ai_save_onboarding_step', array( $this->onboarding, 'ajax_save_onboarding_step' ) );
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

