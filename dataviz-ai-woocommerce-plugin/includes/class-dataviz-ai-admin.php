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
	 * Settings page slug.
	 *
	 * @var string
	 */
	protected $settings_slug = 'dataviz-ai-woocommerce-settings';

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
			'dashicons-admin-comments',
			56
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'API Settings', 'dataviz-ai-woocommerce' ),
			__( 'API Settings', 'dataviz-ai-woocommerce' ),
			'manage_woocommerce',
			$this->settings_slug,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 * 
	 * Note: API settings are now configured via config.php or environment variables.
	 * This registration is kept for backward compatibility only.
	 *
	 * @return void
	 */
	public function register_settings() {
		// Settings registration kept for backward compatibility
		// API configuration is now done via config.php or environment variables
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
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$is_main_page = 'toplevel_page_' . $this->menu_slug === $hook;
		$is_settings_page = 'dataviz-ai-woocommerce_page_' . $this->settings_slug === $hook;

		if ( ! $is_main_page && ! $is_settings_page ) {
			return;
		}

		// Enqueue admin styles for both pages.
		wp_enqueue_style(
			$this->plugin_name . '-admin',
			DATAVIZ_AI_WC_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			$this->version
		);

		// Only enqueue scripts for main page.
		if ( $is_main_page ) {
			// Enqueue Chart.js library
		wp_enqueue_script(
			'dataviz-ai-chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
			array(),
			'4.4.4',
			false
		);

		wp_enqueue_script(
			$this->plugin_name . '-admin',
			DATAVIZ_AI_WC_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery', 'dataviz-ai-chartjs' ),
			$this->version,
			true
		);

		$api_key = $this->api_client->get_api_key();

		// Get chart data for rendering
		$orders    = $this->data_fetcher->get_recent_orders( array( 'limit' => 50 ) );
		$products  = $this->data_fetcher->get_top_products( 10 );
		$order_chart_data = array();
		
		foreach ( $orders as $order ) {
			if ( is_a( $order, 'WC_Order' ) ) {
				$order_chart_data[] = array(
					'id'     => $order->get_id(),
					'total'  => (float) $order->get_total(),
					'status' => $order->get_status(),
					'date'   => $order->get_date_created()->date( 'Y-m-d' ),
				);
			}
		}

		$product_chart_data = array();
		foreach ( $products as $product ) {
			$product_chart_data[] = array(
				'name'        => $product['name'],
				'sales'       => isset( $product['total_sales'] ) ? (int) $product['total_sales'] : 0,
				'price'       => isset( $product['price'] ) ? (float) $product['price'] : 0,
				'product_id'  => isset( $product['id'] ) ? (int) $product['id'] : 0,
			);
		}

			// Get user's session ID from user meta (persists across logins)
			$user_session_id = get_user_meta( get_current_user_id(), 'dataviz_ai_session_id', true );

			wp_localize_script(
				$this->plugin_name . '-admin',
				'DatavizAIAdmin',
				array(
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'dataviz_ai_admin' ),
					'hasApiKey'       => ! empty( $api_key ),
					'orderChartData'  => $order_chart_data,
					'productChartData' => $product_chart_data,
					'userSessionId'   => $user_session_id, // Server-side session ID (persists across logins)
				)
			);
		}
	}

	/**
	 * Render admin page output.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		$api_url   = $this->api_client->get_api_url();
		$api_key   = $this->api_client->get_api_key();
		?>
		<div class="wrap dataviz-ai-admin">
			<h1><?php esc_html_e( 'Dataviz AI for WooCommerce', 'dataviz-ai-woocommerce' ); ?></h1>

			<div class="dataviz-ai-grid">
				<section class="dataviz-ai-card dataviz-ai-card--wide dataviz-ai-chat-container">
					<?php if ( ! $api_key ) : ?>
						<div class="dataviz-ai-chat-warning">
							<p class="notice inline notice-warning">
								<strong><?php esc_html_e( 'API key required.', 'dataviz-ai-woocommerce' ); ?></strong> 
								<?php
								printf(
									/* translators: %s: Link to settings page */
									esc_html__( 'Please %s to enable AI responses. Leave API URL empty to use OpenAI directly.', 'dataviz-ai-woocommerce' ),
									'<a href="' . esc_url( admin_url( 'admin.php?page=' . $this->settings_slug ) ) . '">' . esc_html__( 'configure your API key', 'dataviz-ai-woocommerce' ) . '</a>'
								);
								?>
							</p>
						</div>
					<?php endif; ?>
					
					<div class="dataviz-ai-chat-messages" id="dataviz-ai-chat-messages" role="log" aria-live="polite" aria-atomic="false">
						<div class="dataviz-ai-chat-welcome">
							<h2><?php esc_html_e( 'Chat with me', 'dataviz-ai-woocommerce' ); ?></h2>
							<p><?php esc_html_e( 'Ask questions about your WooCommerce store and get AI-powered insights.', 'dataviz-ai-woocommerce' ); ?></p>
						</div>
					</div>
					
					<form method="post" class="dataviz-ai-chat-form" data-action="analyze">
						<div class="dataviz-ai-chat-input-wrapper">
							<textarea 
								id="dataviz-ai-question" 
								name="question" 
								rows="1" 
								class="dataviz-ai-chat-input" 
								placeholder="<?php esc_attr_e( 'Message AI assistant...', 'dataviz-ai-woocommerce' ); ?>"
								aria-label="<?php esc_attr_e( 'Type your message', 'dataviz-ai-woocommerce' ); ?>"
							></textarea>
							<button 
								type="button" 
								class="dataviz-ai-chat-stop" 
								aria-label="<?php esc_attr_e( 'Stop generating', 'dataviz-ai-woocommerce' ); ?>"
							>
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
									<rect x="2" y="2" width="12" height="12" rx="2" fill="currentColor"/>
								</svg>
							</button>
							<button 
								type="submit" 
								class="dataviz-ai-chat-send" 
								aria-label="<?php esc_attr_e( 'Send message', 'dataviz-ai-woocommerce' ); ?>"
								<?php disabled( ! $api_key ); ?>
							>
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
									<path d="M.5 1.163A1 1 0 0 1 1.97.28l12.868 6.837a1 1 0 0 1 0 1.766L1.969 15.72A1 1 0 0 1 .5 14.836V10.33a1 1 0 0 1 .816-.983L8.5 8 1.316 6.653A1 1 0 0 1 .5 5.67V1.163Z" fill="currentColor"/>
								</svg>
							</button>
						</div>
					</form>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page output.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$api_url = $this->api_client->get_api_url();
		$api_key = $this->api_client->get_api_key();
		$config_file = DATAVIZ_AI_WC_PLUGIN_DIR . 'config.php';
		$config_exists = file_exists( $config_file );
		$env_api_key = getenv( 'OPENAI_API_KEY' ) ?: getenv( 'DATAVIZ_AI_API_KEY' );
		$env_api_url = getenv( 'DATAVIZ_AI_API_BASE_URL' );
		$has_env_config = ( false !== $env_api_key && ! empty( $env_api_key ) ) || ( false !== $env_api_url && ! empty( $env_api_url ) );
		?>
		<div class="wrap dataviz-ai-admin">
			<h1><?php esc_html_e( 'API Configuration', 'dataviz-ai-woocommerce' ); ?></h1>
			
			<div class="dataviz-ai-config-status">
				<?php if ( $api_key ) : ?>
					<div class="notice notice-success inline">
						<p><strong><?php esc_html_e( '✅ API Configuration Active', 'dataviz-ai-woocommerce' ); ?></strong></p>
						<p><?php esc_html_e( 'Your API settings are configured and active.', 'dataviz-ai-woocommerce' ); ?></p>
					</div>
				<?php else : ?>
					<div class="notice notice-warning inline">
						<p><strong><?php esc_html_e( '⚠️ API Configuration Required', 'dataviz-ai-woocommerce' ); ?></strong></p>
						<p><?php esc_html_e( 'Please configure your API key to enable AI responses.', 'dataviz-ai-woocommerce' ); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<div class="dataviz-ai-config-info" style="margin-top: 20px;">
				<h2><?php esc_html_e( 'Configuration Methods', 'dataviz-ai-woocommerce' ); ?></h2>
				
				<div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin: 15px 0;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Method 1: Environment Variables (Recommended)', 'dataviz-ai-woocommerce' ); ?></h3>
					<p><?php esc_html_e( 'Set these environment variables on your server:', 'dataviz-ai-woocommerce' ); ?></p>
					<ul>
						<li><code>OPENAI_API_KEY</code> <?php esc_html_e( 'or', 'dataviz-ai-woocommerce' ); ?> <code>DATAVIZ_AI_API_KEY</code> - <?php esc_html_e( 'Your API key', 'dataviz-ai-woocommerce' ); ?></li>
						<li><code>DATAVIZ_AI_API_BASE_URL</code> - <?php esc_html_e( 'API base URL (optional, leave empty for OpenAI)', 'dataviz-ai-woocommerce' ); ?></li>
					</ul>
					<?php if ( $has_env_config ) : ?>
						<p style="color: #00a32a;"><strong>✅ <?php esc_html_e( 'Environment variables detected', 'dataviz-ai-woocommerce' ); ?></strong></p>
					<?php else : ?>
						<p style="color: #d63638;">⚠️ <?php esc_html_e( 'No environment variables detected', 'dataviz-ai-woocommerce' ); ?></p>
					<?php endif; ?>
				</div>

				<div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin: 15px 0;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Method 2: Configuration File', 'dataviz-ai-woocommerce' ); ?></h3>
					<p><?php esc_html_e( 'Copy', 'dataviz-ai-woocommerce' ); ?> <code>config.php.example</code> <?php esc_html_e( 'to', 'dataviz-ai-woocommerce' ); ?> <code>config.php</code> <?php esc_html_e( 'and update the values:', 'dataviz-ai-woocommerce' ); ?></p>
					<ul>
						<li><code>DATAVIZ_AI_API_KEY</code> - <?php esc_html_e( 'Your API key', 'dataviz-ai-woocommerce' ); ?></li>
						<li><code>DATAVIZ_AI_API_BASE_URL</code> - <?php esc_html_e( 'API base URL (optional, leave empty for OpenAI)', 'dataviz-ai-woocommerce' ); ?></li>
					</ul>
					<p><strong><?php esc_html_e( 'File location:', 'dataviz-ai-woocommerce' ); ?></strong> <code><?php echo esc_html( $config_file ); ?></code></p>
					<?php if ( $config_exists ) : ?>
						<p style="color: #00a32a;"><strong>✅ <?php esc_html_e( 'Configuration file exists', 'dataviz-ai-woocommerce' ); ?></strong></p>
					<?php else : ?>
						<p style="color: #d63638;">⚠️ <?php esc_html_e( 'Configuration file not found. Please create', 'dataviz-ai-woocommerce' ); ?> <code>config.php</code> <?php esc_html_e( 'from', 'dataviz-ai-woocommerce' ); ?> <code>config.php.example</code></p>
					<?php endif; ?>
				</div>

				<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #dba617; margin: 15px 0;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Current Configuration Status', 'dataviz-ai-woocommerce' ); ?></h3>
					<ul>
						<li><strong><?php esc_html_e( 'API Key:', 'dataviz-ai-woocommerce' ); ?></strong> 
							<?php if ( $api_key ) : ?>
								<span style="color: #00a32a;">✅ <?php esc_html_e( 'Configured', 'dataviz-ai-woocommerce' ); ?></span>
								<?php
								// Show source
								if ( $has_env_config ) {
									echo ' <small>(' . esc_html__( 'from environment variable', 'dataviz-ai-woocommerce' ) . ')</small>';
								} elseif ( $config_exists && defined( 'DATAVIZ_AI_API_KEY' ) && ! empty( DATAVIZ_AI_API_KEY ) ) {
									echo ' <small>(' . esc_html__( 'from config.php', 'dataviz-ai-woocommerce' ) . ')</small>';
								} else {
									echo ' <small>(' . esc_html__( 'from WordPress options', 'dataviz-ai-woocommerce' ) . ')</small>';
								}
								?>
							<?php else : ?>
								<span style="color: #d63638;">❌ <?php esc_html_e( 'Not configured', 'dataviz-ai-woocommerce' ); ?></span>
							<?php endif; ?>
						</li>
						<li><strong><?php esc_html_e( 'API Base URL:', 'dataviz-ai-woocommerce' ); ?></strong> 
							<?php if ( ! empty( $api_url ) ) : ?>
								<code><?php echo esc_html( $api_url ); ?></code>
								<?php
								if ( $env_api_url ) {
									echo ' <small>(' . esc_html__( 'from environment variable', 'dataviz-ai-woocommerce' ) . ')</small>';
								} elseif ( $config_exists && defined( 'DATAVIZ_AI_API_BASE_URL' ) ) {
									echo ' <small>(' . esc_html__( 'from config.php', 'dataviz-ai-woocommerce' ) . ')</small>';
								}
								?>
							<?php else : ?>
								<span style="color: #2271b1;"><?php esc_html_e( 'Using OpenAI directly', 'dataviz-ai-woocommerce' ); ?></span>
							<?php endif; ?>
						</li>
					</ul>
				</div>

				<div style="background: #f0f6fc; padding: 15px; border-left: 4px solid #0969da; margin: 15px 0;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Security Note', 'dataviz-ai-woocommerce' ); ?></h3>
					<p><?php esc_html_e( 'API keys are sensitive credentials. For security, we recommend using environment variables or the config.php file (which is excluded from version control).', 'dataviz-ai-woocommerce' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}

