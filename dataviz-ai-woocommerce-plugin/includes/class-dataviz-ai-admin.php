<?php
/**
 * Admin-facing functionality for Dataviz AI WooCommerce plugin.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides settings page and dashboard widgets.
 */
class Dataviz_AI_Admin {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Data fetcher dependency.
	 *
	 * @var Dataviz_AI_Data_Fetcher
	 */
	protected $data_fetcher;

	/**
	 * API client dependency.
	 *
	 * @var Dataviz_AI_API_Client
	 */
	protected $api_client;

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	protected $menu_slug = 'dataviz-ai-woocommerce';

	/**
	 * Constructor.
	 *
	 * @param string                  $plugin_name  Plugin slug.
	 * @param string                  $version      Plugin version.
	 * @param Dataviz_AI_Data_Fetcher $data_fetcher Data fetcher instance.
	 * @param Dataviz_AI_API_Client   $api_client   API client instance.
	 */
	public function __construct( $plugin_name, $version, Dataviz_AI_Data_Fetcher $data_fetcher, Dataviz_AI_API_Client $api_client ) {
		$this->plugin_name  = $plugin_name;
		$this->version      = $version;
		$this->data_fetcher = $data_fetcher;
		$this->api_client   = $api_client;
	}

	/**
	 * Register admin menu page.
	 *
	 * @return void
	 */
	public function register_menu_page() {
		add_menu_page(
			__( 'Dataviz AI Insights', 'dataviz-ai-woocommerce' ),
			__( 'Dataviz AI', 'dataviz-ai-woocommerce' ),
			'manage_woocommerce',
			$this->menu_slug,
			array( $this, 'render_admin_page' ),
			'dashicons-chart-line',
			56
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'dataviz_ai_wc',
			'dataviz_ai_wc_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'api_url' => '',
					'api_key' => '',
				),
			)
		);

		add_settings_section(
			'dataviz_ai_wc_api',
			__( 'API Configuration', 'dataviz-ai-woocommerce' ),
			function() {
				echo '<p>' . esc_html__( 'Connect your WordPress store to the Dataviz AI backend.', 'dataviz-ai-woocommerce' ) . '</p>';
			},
			'dataviz_ai_wc'
		);

		add_settings_field(
			'dataviz_ai_wc_api_url',
			__( 'API Base URL', 'dataviz-ai-woocommerce' ),
			array( $this, 'render_api_url_field' ),
			'dataviz_ai_wc',
			'dataviz_ai_wc_api'
		);

		add_settings_field(
			'dataviz_ai_wc_api_key',
			__( 'API Key', 'dataviz-ai-woocommerce' ),
			array( $this, 'render_api_key_field' ),
			'dataviz_ai_wc',
			'dataviz_ai_wc_api'
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw settings from POST.
	 *
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$output = array();

		$output['api_url'] = isset( $input['api_url'] ) ? esc_url_raw( $input['api_url'] ) : '';
		$output['api_key'] = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';

		return $output;
	}

	/**
	 * Render API URL field.
	 *
	 * @return void
	 */
	public function render_api_url_field() {
		$options = get_option( 'dataviz_ai_wc_settings', array() );
		$value   = isset( $options['api_url'] ) ? $options['api_url'] : '';

		printf(
			'<input type="url" name="dataviz_ai_wc_settings[api_url]" value="%1$s" class="regular-text" placeholder="%2$s" />',
			esc_attr( $value ),
			esc_attr__( 'https://app.yourdomain.com', 'dataviz-ai-woocommerce' )
		);
	}

	/**
	 * Render API key field.
	 *
	 * @return void
	 */
	public function render_api_key_field() {
		$options = get_option( 'dataviz_ai_wc_settings', array() );
		$value   = isset( $options['api_key'] ) ? $options['api_key'] : '';

		printf(
			'<input type="password" name="dataviz_ai_wc_settings[api_key]" value="%1$s" class="regular-text" placeholder="%2$s" autocomplete="off" />',
			esc_attr( $value ),
			esc_attr__( 'sk-live-...', 'dataviz-ai-woocommerce' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . $this->menu_slug !== $hook ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name . '-admin',
			DATAVIZ_AI_WC_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			$this->plugin_name . '-admin',
			DATAVIZ_AI_WC_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		$api_key = $this->api_client->get_api_key();

		wp_localize_script(
			$this->plugin_name . '-admin',
			'DatavizAIAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'dataviz_ai_admin' ),
				'hasApiKey' => ! empty( $api_key ),
			)
		);
	}

	/**
	 * Render admin page output.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		$api_url = $this->api_client->get_api_url();
		$api_key = $this->api_client->get_api_key();
		?>
		<div class="wrap dataviz-ai-admin">
			<h1><?php esc_html_e( 'Dataviz AI for WooCommerce', 'dataviz-ai-woocommerce' ); ?></h1>

			<div class="dataviz-ai-grid">
				<section class="dataviz-ai-card dataviz-ai-card--wide">
					<h2><?php esc_html_e( 'AI Chat Assistant', 'dataviz-ai-woocommerce' ); ?></h2>
					<p><?php esc_html_e( 'Ask questions about your WooCommerce store and get AI-powered insights.', 'dataviz-ai-woocommerce' ); ?></p>
					<form method="post" class="dataviz-ai-analysis-form" data-action="analyze">
						<?php if ( ! $api_key ) : ?>
							<p class="notice inline notice-warning"><strong><?php esc_html_e( 'API key required.', 'dataviz-ai-woocommerce' ); ?></strong> <?php esc_html_e( 'Configure your API key below to enable AI responses. Leave API URL empty to use OpenAI directly.', 'dataviz-ai-woocommerce' ); ?></p>
						<?php endif; ?>
						<label for="dataviz-ai-question" class="screen-reader-text"><?php esc_html_e( 'Question', 'dataviz-ai-woocommerce' ); ?></label>
						<textarea id="dataviz-ai-question" name="question" rows="5" class="widefat" placeholder="<?php esc_attr_e( 'What are the key trends from my recent orders?', 'dataviz-ai-woocommerce' ); ?>"></textarea>
						<p>
							<button type="submit" class="button button-primary"<?php disabled( ! $api_key ); ?>><?php esc_html_e( 'Ask AI', 'dataviz-ai-woocommerce' ); ?></button>
						</p>
						<pre class="dataviz-ai-analysis-output" aria-live="polite"></pre>
					</form>
				</section>
			</div>

			<hr />

			<form method="post" action="options.php" class="dataviz-ai-settings">
				<h2><?php esc_html_e( 'API Settings', 'dataviz-ai-woocommerce' ); ?></h2>
				<?php
				settings_fields( 'dataviz_ai_wc' );
				do_settings_sections( 'dataviz_ai_wc' );
				submit_button( __( 'Save Settings', 'dataviz-ai-woocommerce' ) );
				?>
			</form>
		</div>
		<?php
	}
}

