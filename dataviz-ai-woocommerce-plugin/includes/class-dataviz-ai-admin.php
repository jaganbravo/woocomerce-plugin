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
	 * Checkout page slug.
	 *
	 * @var string
	 */
	protected $checkout_slug = 'dataviz-ai-woocommerce-checkout';

	/**
	 * Payment handler.
	 *
	 * @var Dataviz_AI_Payment_Handler
	 */
	protected $payment_handler;

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
		$this->payment_handler = new Dataviz_AI_Payment_Handler();
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

		// Add checkout page (hidden from menu, accessed via direct link)
		add_submenu_page(
			null, // Hidden from menu
			__( 'Checkout', 'dataviz-ai-woocommerce' ),
			__( 'Checkout', 'dataviz-ai-woocommerce' ),
			'manage_woocommerce',
			$this->checkout_slug,
			array( $this, 'render_checkout_page' )
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
		$is_checkout_page = 'dataviz-ai-woocommerce_page_' . $this->checkout_slug === $hook;

		if ( ! $is_main_page && ! $is_checkout_page ) {
			return;
		}

		// Enqueue admin styles for both pages
		wp_enqueue_style(
			$this->plugin_name . '-admin',
			DATAVIZ_AI_WC_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			$this->version
		);

		if ( $is_checkout_page ) {
			return; // Checkout page handles its own scripts
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

		// Handle payment success redirect
		if ( isset( $_GET['payment'] ) && 'success' === $_GET['payment'] && isset( $_GET['license_key'] ) ) {
			$license_key = sanitize_text_field( $_GET['license_key'] );
			$result = $this->license_manager->activate_license( $license_key );
			if ( $result['success'] ) {
				add_action( 'admin_notices', function() {
					printf(
						'<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s</p></div>',
						esc_html__( 'Payment Successful!', 'dataviz-ai-woocommerce' ),
						esc_html__( 'Your license has been activated automatically.', 'dataviz-ai-woocommerce' )
					);
				} );
			}
		}

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
							<a href="<?php echo esc_url( $this->payment_handler->get_checkout_url( 'pro', 'stripe' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Buy Pro Plan - Secure Checkout', 'dataviz-ai-woocommerce' ); ?>
							</a>
						</p>
						<?php if ( $this->payment_handler->is_paypal_configured() ) : ?>
							<p style="margin-top: 10px;">
								<a href="<?php echo esc_url( $this->payment_handler->get_checkout_url( 'pro', 'paypal' ) ); ?>" class="button">
									<?php esc_html_e( 'Pay with PayPal', 'dataviz-ai-woocommerce' ); ?>
								</a>
							</p>
						<?php endif; ?>
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
							<a href="<?php echo esc_url( $this->payment_handler->get_checkout_url( 'agency', 'stripe' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Buy Agency Plan - Secure Checkout', 'dataviz-ai-woocommerce' ); ?>
							</a>
						</p>
						<?php if ( $this->payment_handler->is_paypal_configured() ) : ?>
							<p style="margin-top: 10px;">
								<a href="<?php echo esc_url( $this->payment_handler->get_checkout_url( 'agency', 'paypal' ) ); ?>" class="button">
									<?php esc_html_e( 'Pay with PayPal', 'dataviz-ai-woocommerce' ); ?>
								</a>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render secure checkout page.
	 *
	 * @return void
	 */
	public function render_checkout_page() {
		// Check user permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dataviz-ai-woocommerce' ) );
		}

		$plan = isset( $_GET['plan'] ) ? sanitize_text_field( $_GET['plan'] ) : 'pro';
		$payment_method = isset( $_GET['payment_method'] ) ? sanitize_text_field( $_GET['payment_method'] ) : 'stripe';
		$plan = in_array( $plan, array( 'pro', 'agency' ), true ) ? $plan : 'pro';
		$payment_method = in_array( $payment_method, array( 'stripe', 'paypal' ), true ) ? $payment_method : 'stripe';

		$pricing = $this->payment_handler->get_plan_pricing( $plan );
		$user = wp_get_current_user();
		$user_email = $user->user_email;

		// Enqueue Stripe.js if using Stripe
		if ( 'stripe' === $payment_method && $this->payment_handler->is_stripe_configured() ) {
			wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), '3.0', true );
		}

		// Enqueue checkout scripts
		wp_enqueue_script(
			$this->plugin_name . '-checkout',
			DATAVIZ_AI_WC_PLUGIN_URL . 'admin/js/checkout.js',
			array( 'jquery' ),
			$this->version,
			true
		);

			wp_localize_script(
				$this->plugin_name . '-checkout',
				'DatavizAICheckout',
				array(
					'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
					'nonce'                => wp_create_nonce( 'dataviz_ai_checkout' ),
					'plan'                 => $plan,
					'paymentMethod'        => $payment_method,
					'amount'               => $pricing['monthly'],
					'currency'             => $pricing['currency'],
					'stripePublishableKey' => $this->payment_handler->get_stripe_publishable_key(),
					'paypalClientId'       => $this->payment_handler->get_paypal_client_id(),
					'isStripeConfigured'   => $this->payment_handler->is_stripe_configured(),
					'isPayPalConfigured'   => $this->payment_handler->is_paypal_configured(),
					'userName'             => $user->display_name,
					'successUrl'           => admin_url( 'admin.php?page=' . $this->license_slug . '&payment=success' ),
					'cancelUrl'            => admin_url( 'admin.php?page=' . $this->license_slug ),
				)
			);

		?>
		<div class="wrap dataviz-ai-admin">
			<h1><?php esc_html_e( 'Secure Checkout', 'dataviz-ai-woocommerce' ); ?></h1>

			<div class="dataviz-ai-checkout-container" style="max-width: 800px; margin: 20px auto;">
				<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
					<!-- Payment Form -->
					<div class="dataviz-ai-checkout-form">
						<h2><?php esc_html_e( 'Payment Details', 'dataviz-ai-woocommerce' ); ?></h2>

						<div class="dataviz-ai-order-summary" style="background: #f9f9f9; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
							<h3 style="margin-top: 0;"><?php esc_html_e( 'Order Summary', 'dataviz-ai-woocommerce' ); ?></h3>
							<p>
								<strong><?php echo esc_html( ucfirst( $plan ) ); ?> <?php esc_html_e( 'Plan', 'dataviz-ai-woocommerce' ); ?></strong><br>
								<span style="font-size: 24px; font-weight: bold; color: #2271b1;">
									$<?php echo esc_html( number_format( $pricing['monthly'], 2 ) ); ?>
									<span style="font-size: 14px;">/month</span>
								</span>
							</p>
							<p style="margin-top: 10px;">
								<small><?php esc_html_e( 'Billed monthly. Cancel anytime.', 'dataviz-ai-woocommerce' ); ?></small>
							</p>
						</div>

						<?php if ( 'stripe' === $payment_method ) : ?>
							<?php if ( ! $this->payment_handler->is_stripe_configured() ) : ?>
								<div class="notice notice-error">
									<p><?php esc_html_e( 'Stripe is not configured. Please contact support.', 'dataviz-ai-woocommerce' ); ?></p>
								</div>
							<?php else : ?>
								<form id="dataviz-ai-stripe-checkout-form">
									<div id="stripe-card-element" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;">
										<!-- Stripe Elements will create form elements here -->
									</div>
									<div id="stripe-card-errors" role="alert" style="color: #d63638; margin-bottom: 15px;"></div>

									<div style="margin-bottom: 15px;">
										<label>
											<input type="checkbox" id="save-payment-method" />
											<?php esc_html_e( 'Save payment method for future use', 'dataviz-ai-woocommerce' ); ?>
										</label>
									</div>

									<button type="submit" id="stripe-submit-button" class="button button-primary button-large" style="width: 100%; padding: 15px;">
										<?php
										printf(
											/* translators: %s: Amount */
											esc_html__( 'Pay $%s/month', 'dataviz-ai-woocommerce' ),
											number_format( $pricing['monthly'], 2 )
										);
										?>
									</button>
								</form>
							<?php endif; ?>
						<?php elseif ( 'paypal' === $payment_method ) : ?>
							<?php if ( ! $this->payment_handler->is_paypal_configured() ) : ?>
								<div class="notice notice-error">
									<p><?php esc_html_e( 'PayPal is not configured. Please contact support.', 'dataviz-ai-woocommerce' ); ?></p>
								</div>
							<?php else : ?>
								<div id="paypal-button-container" style="margin-top: 20px;">
									<!-- PayPal button will be rendered here -->
								</div>
								<script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_js( $this->payment_handler->get_paypal_client_id() ); ?>&currency=<?php echo esc_js( $pricing['currency'] ); ?>"></script>
							<?php endif; ?>
						<?php endif; ?>

						<div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
							<p style="margin: 0; font-size: 12px;">
								<strong>🔒 <?php esc_html_e( 'Secure Payment', 'dataviz-ai-woocommerce' ); ?></strong><br>
								<?php esc_html_e( 'Your payment information is encrypted and secure. We never store your full card details.', 'dataviz-ai-woocommerce' ); ?>
							</p>
						</div>
					</div>

					<!-- Order Details Sidebar -->
					<div class="dataviz-ai-checkout-sidebar">
						<div style="background: #f9f9f9; padding: 20px; border-radius: 4px; position: sticky; top: 20px;">
							<h3 style="margin-top: 0;"><?php esc_html_e( 'What You Get', 'dataviz-ai-woocommerce' ); ?></h3>
							<ul style="list-style: none; padding: 0;">
								<?php if ( 'pro' === $plan ) : ?>
									<li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ <?php esc_html_e( 'Unlimited questions', 'dataviz-ai-woocommerce' ); ?></li>
									<li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ <?php esc_html_e( 'All entity types', 'dataviz-ai-woocommerce' ); ?></li>
									<li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ <?php esc_html_e( 'Chat history (5 days)', 'dataviz-ai-woocommerce' ); ?></li>
									<li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ <?php esc_html_e( 'Priority support', 'dataviz-ai-woocommerce' ); ?></li>
									<li style="padding: 8px 0;">✅ <?php esc_html_e( 'Advanced analytics', 'dataviz-ai-woocommerce' ); ?></li>
								<?php else : ?>
									<li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ <?php esc_html_e( 'Everything in Pro', 'dataviz-ai-woocommerce' ); ?></li>
									<li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ <?php esc_html_e( 'Multiple stores (up to 10)', 'dataviz-ai-woocommerce' ); ?></li>
									<li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ <?php esc_html_e( 'White-label option', 'dataviz-ai-woocommerce' ); ?></li>
									<li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ <?php esc_html_e( 'Priority support', 'dataviz-ai-woocommerce' ); ?></li>
									<li style="padding: 8px 0;">✅ <?php esc_html_e( 'API access (future)', 'dataviz-ai-woocommerce' ); ?></li>
								<?php endif; ?>
							</ul>

							<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
								<p style="font-size: 12px; color: #666;">
									<?php esc_html_e( 'By completing this purchase, you agree to our Terms of Service and Privacy Policy.', 'dataviz-ai-woocommerce' ); ?>
								</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}

