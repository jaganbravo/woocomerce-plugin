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
	 * License manager dependency.
	 *
	 * @var Dataviz_AI_License_Manager
	 */
	protected $license_manager;

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	protected $menu_slug = 'dataviz-ai-woocommerce';

	/**
	 * License settings page slug.
	 *
	 * @var string
	 */
	protected $license_slug = 'dataviz-ai-woocommerce-license';

	/**
	 * Constructor.
	 *
	 * @param string                  $plugin_name  Plugin slug.
	 * @param string                  $version      Plugin version.
	 * @param Dataviz_AI_Data_Fetcher $data_fetcher Data fetcher instance.
	 * @param Dataviz_AI_API_Client   $api_client   API client instance.
	 */
	public function __construct( $plugin_name, $version, Dataviz_AI_Data_Fetcher $data_fetcher, Dataviz_AI_API_Client $api_client ) {
		$this->plugin_name     = $plugin_name;
		$this->version         = $version;
		$this->data_fetcher    = $data_fetcher;
		$this->api_client      = $api_client;
		$this->license_manager = new Dataviz_AI_License_Manager();
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
			__( 'License', 'dataviz-ai-woocommerce' ),
			__( 'License', 'dataviz-ai-woocommerce' ),
			'manage_woocommerce',
			$this->license_slug,
			array( $this, 'render_license_page' )
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
		$is_main_page = 'toplevel_page_' . $this->menu_slug === $hook;

		if ( ! $is_main_page ) {
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
		$usage_stats = $this->license_manager->get_usage_stats();

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
					'usageStats'      => $usage_stats,
					'isPremium'       => $this->license_manager->is_premium(),
					'upgradeUrl'      => $this->license_manager->get_purchase_url( 'pro' ),
					'upgradeMessage'  => $this->license_manager->get_upgrade_message(),
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
		$usage_stats = $this->license_manager->get_usage_stats();
		$is_premium = $this->license_manager->is_premium();
		?>
		<div class="wrap dataviz-ai-admin">
			<h1><?php esc_html_e( 'Dataviz AI for WooCommerce', 'dataviz-ai-woocommerce' ); ?></h1>

			<?php if ( ! $is_premium && $usage_stats['questions_limit'] > 0 ) : ?>
				<div class="dataviz-ai-usage-notice" style="margin: 15px 0; padding: 10px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
					<p style="margin: 0;">
						<strong><?php esc_html_e( 'Usage:', 'dataviz-ai-woocommerce' ); ?></strong>
						<?php
						printf(
							/* translators: %1$d: used, %2$d: limit */
							esc_html__( '%1$d of %2$d questions used this month.', 'dataviz-ai-woocommerce' ),
							(int) $usage_stats['questions_used'],
							(int) $usage_stats['questions_limit']
						);
						?>
						<?php if ( $usage_stats['questions_used'] >= $usage_stats['questions_limit'] * 0.8 ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->license_slug ) ); ?>" style="margin-left: 10px;">
								<?php esc_html_e( 'Upgrade to Pro for unlimited questions', 'dataviz-ai-woocommerce' ); ?>
							</a>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="dataviz-ai-grid">
				<section class="dataviz-ai-card dataviz-ai-card--wide dataviz-ai-chat-container">
					<?php if ( ! $api_key ) : ?>
						<div class="dataviz-ai-chat-warning">
							<p class="notice inline notice-warning">
								<strong><?php esc_html_e( 'API key required.', 'dataviz-ai-woocommerce' ); ?></strong> 
								<?php esc_html_e( 'Please configure your API key via environment variables (OPENAI_API_KEY or DATAVIZ_AI_API_KEY) or by editing the config.php file in the plugin directory.', 'dataviz-ai-woocommerce' ); ?>
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
	 * Handle license activation/deactivation.
	 *
	 * @return void
	 */
	public function handle_license_action() {
		if ( ! isset( $_POST['dataviz_ai_license_action'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'dataviz_ai_license_action', 'dataviz_ai_license_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$action = sanitize_text_field( $_POST['dataviz_ai_license_action'] );

		if ( 'activate' === $action && isset( $_POST['license_key'] ) ) {
			$license_key = sanitize_text_field( $_POST['license_key'] );
			$result = $this->license_manager->activate_license( $license_key );

			if ( $result['success'] ) {
				add_action( 'admin_notices', function() use ( $result ) {
					printf(
						'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
						esc_html( $result['message'] )
					);
				} );
			} else {
				add_action( 'admin_notices', function() use ( $result ) {
					printf(
						'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
						esc_html( $result['message'] )
					);
				} );
			}
		} elseif ( 'deactivate' === $action ) {
			$this->license_manager->deactivate_license();
			add_action( 'admin_notices', function() {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html__( 'License deactivated successfully.', 'dataviz-ai-woocommerce' )
				);
			} );
		}
	}

	/**
	 * Render license page output.
	 *
	 * @return void
	 */
	public function render_license_page() {
		$this->handle_license_action();

		$license_status = $this->license_manager->get_license_status();
		$plan = $this->license_manager->get_plan();
		$usage_stats = $this->license_manager->get_usage_stats();
		$is_premium = $this->license_manager->is_premium();
		$license_data = $this->license_manager->get_license_data_public();
		?>
		<div class="wrap dataviz-ai-admin">
			<h1><?php esc_html_e( 'License Settings', 'dataviz-ai-woocommerce' ); ?></h1>

			<div class="dataviz-ai-license-status" style="margin: 20px 0;">
				<?php if ( $is_premium ) : ?>
					<div class="notice notice-success inline">
						<p><strong><?php esc_html_e( '✅ Premium License Active', 'dataviz-ai-woocommerce' ); ?></strong></p>
						<p>
							<?php
							printf(
								/* translators: %s: Plan name */
								esc_html__( 'Your %s plan is active with unlimited questions.', 'dataviz-ai-woocommerce' ),
								esc_html( ucfirst( $plan ) )
							);
							?>
						</p>
					</div>
				<?php else : ?>
					<div class="notice notice-info inline">
						<p><strong><?php esc_html_e( 'Free Plan Active', 'dataviz-ai-woocommerce' ); ?></strong></p>
						<p>
							<?php
							printf(
								/* translators: %1$d: used questions, %2$d: limit */
								esc_html__( 'You have used %1$d of %2$d free questions this month.', 'dataviz-ai-woocommerce' ),
								(int) $usage_stats['questions_used'],
								(int) $usage_stats['questions_limit']
							);
							?>
						</p>
					</div>
				<?php endif; ?>
			</div>

			<div class="dataviz-ai-license-form" style="max-width: 600px;">
				<h2><?php esc_html_e( 'Activate License', 'dataviz-ai-woocommerce' ); ?></h2>
				
				<?php if ( ! $is_premium ) : ?>
					<form method="post" action="">
						<?php wp_nonce_field( 'dataviz_ai_license_action', 'dataviz_ai_license_nonce' ); ?>
						<input type="hidden" name="dataviz_ai_license_action" value="activate" />
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="license_key"><?php esc_html_e( 'License Key', 'dataviz-ai-woocommerce' ); ?></label>
								</th>
								<td>
									<input 
										type="text" 
										id="license_key" 
										name="license_key" 
										class="regular-text" 
										placeholder="<?php esc_attr_e( 'Enter your license key', 'dataviz-ai-woocommerce' ); ?>"
										value="<?php echo esc_attr( $license_data['license_key'] ); ?>"
									/>
									<p class="description">
										<?php esc_html_e( 'Enter your license key to activate premium features.', 'dataviz-ai-woocommerce' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<?php submit_button( __( 'Activate License', 'dataviz-ai-woocommerce' ) ); ?>
					</form>
				<?php else : ?>
					<form method="post" action="">
						<?php wp_nonce_field( 'dataviz_ai_license_action', 'dataviz_ai_license_nonce' ); ?>
						<input type="hidden" name="dataviz_ai_license_action" value="deactivate" />
						
						<p>
							<strong><?php esc_html_e( 'License Key:', 'dataviz-ai-woocommerce' ); ?></strong> 
							<code><?php echo esc_html( substr( $license_data['license_key'], 0, 20 ) . '...' ); ?></code>
						</p>
						
						<?php submit_button( __( 'Deactivate License', 'dataviz-ai-woocommerce' ), 'secondary' ); ?>
					</form>
				<?php endif; ?>
			</div>

			<div class="dataviz-ai-upgrade-info" style="margin-top: 30px;">
				<h2><?php esc_html_e( 'Upgrade to Premium', 'dataviz-ai-woocommerce' ); ?></h2>
				
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
					<div style="border: 1px solid #ddd; padding: 20px; border-radius: 4px;">
						<h3><?php esc_html_e( 'Pro Plan', 'dataviz-ai-woocommerce' ); ?></h3>
						<p style="font-size: 24px; font-weight: bold; color: #2271b1;">$15<span style="font-size: 14px;">/month</span></p>
						<ul style="list-style: disc; margin-left: 20px;">
							<li><?php esc_html_e( 'Unlimited questions', 'dataviz-ai-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'All entity types', 'dataviz-ai-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Chat history (5 days)', 'dataviz-ai-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Priority support', 'dataviz-ai-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Advanced analytics', 'dataviz-ai-woocommerce' ); ?></li>
						</ul>
						<p>
							<a href="<?php echo esc_url( $this->license_manager->get_purchase_url( 'pro' ) ); ?>" class="button button-primary" target="_blank">
								<?php esc_html_e( 'Buy Pro Plan', 'dataviz-ai-woocommerce' ); ?>
							</a>
						</p>
					</div>

					<div style="border: 1px solid #ddd; padding: 20px; border-radius: 4px;">
						<h3><?php esc_html_e( 'Agency Plan', 'dataviz-ai-woocommerce' ); ?></h3>
						<p style="font-size: 24px; font-weight: bold; color: #2271b1;">$99<span style="font-size: 14px;">/month</span></p>
						<ul style="list-style: disc; margin-left: 20px;">
							<li><?php esc_html_e( 'Everything in Pro', 'dataviz-ai-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Multiple stores (up to 10)', 'dataviz-ai-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'White-label option', 'dataviz-ai-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Priority support', 'dataviz-ai-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'API access (future)', 'dataviz-ai-woocommerce' ); ?></li>
						</ul>
						<p>
							<a href="<?php echo esc_url( $this->license_manager->get_purchase_url( 'agency' ) ); ?>" class="button button-primary" target="_blank">
								<?php esc_html_e( 'Buy Agency Plan', 'dataviz-ai-woocommerce' ); ?>
							</a>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

}

