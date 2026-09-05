<?php
/**
 * Admin-facing functionality for Unmai Analytix WooCommerce plugin.
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
	protected $menu_slug = 'unmai-analytix-for-woocommerce';

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
			__( 'Unmai Analytix Insights', 'unmai-analytix-for-woocommerce' ),
			__( 'Unmai Analytix', 'unmai-analytix-for-woocommerce' ),
			'manage_woocommerce',
			$this->menu_slug,
			array( $this, 'render_admin_page' ),
			'dashicons-admin-comments',
			56
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'FAQ', 'unmai-analytix-for-woocommerce' ),
			__( 'FAQ', 'unmai-analytix-for-woocommerce' ),
			'manage_woocommerce',
			'dataviz-ai-faq',
			array( $this, 'render_faq_page' )
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Onboarding', 'unmai-analytix-for-woocommerce' ),
			__( 'Onboarding', 'unmai-analytix-for-woocommerce' ),
			'manage_woocommerce',
			'dataviz-ai-onboarding',
			array( $this, 'render_onboarding_page' )
		);
	}

	/**
	 * Render FAQ page with content from FAQ.md.
	 *
	 * @return void
	 */
	public function render_faq_page() {
		$faq_file = DATAVIZ_AI_WC_PLUGIN_DIR . 'admin/partials/faq-content.html';
		?>
		<div class="wrap dataviz-ai-admin dataviz-ai-faq">
			<h1><?php esc_html_e( 'FAQ', 'unmai-analytix-for-woocommerce' ); ?></h1>
			<div class="dataviz-ai-faq-content">
				<?php
				if ( file_exists( $faq_file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bundled plugin HTML.
					echo wp_kses_post( file_get_contents( $faq_file ) );
				} else {
					echo '<p>' . esc_html__( 'FAQ content not found.', 'unmai-analytix-for-woocommerce' ) . '</p>';
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Onboarding page with content from ONBOARDING_FLOW.md.
	 *
	 * @return void
	 */
	public function render_onboarding_page() {
		$onboarding_file = DATAVIZ_AI_WC_PLUGIN_DIR . 'admin/partials/onboarding-content.html';
		?>
		<div class="wrap dataviz-ai-admin dataviz-ai-onboarding">
			<h1><?php esc_html_e( 'Onboarding', 'unmai-analytix-for-woocommerce' ); ?></h1>
			<div class="dataviz-ai-onboarding-content">
				<?php
				if ( file_exists( $onboarding_file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bundled plugin HTML.
					echo wp_kses_post( file_get_contents( $onboarding_file ) );
				} else {
					echo '<p>' . esc_html__( 'Onboarding content not found.', 'unmai-analytix-for-woocommerce' ) . '</p>';
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$is_main_page       = 'toplevel_page_' . $this->menu_slug === $hook;
		$is_faq_page        = $this->menu_slug . '_page_dataviz-ai-faq' === $hook;
		$is_onboarding_page = $this->menu_slug . '_page_dataviz-ai-onboarding' === $hook;

		if ( ! $is_main_page && ! $is_faq_page && ! $is_onboarding_page ) {
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
			wp_enqueue_script(
				'dataviz-ai-chartjs',
				DATAVIZ_AI_WC_PLUGIN_URL . 'admin/js/vendor/chart.umd.min.js',
				array(),
				'4.5.1',
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
					'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
					'nonce'               => wp_create_nonce( 'dataviz_ai_admin' ),
					'hasApiKey'           => ! empty( $api_key ),
					'orderChartData'      => $order_chart_data,
					'productChartData'    => $product_chart_data,
					'userSessionId'       => $user_session_id, // Server-side session ID (persists across logins)
					'suggestedQuestions'  => $this->get_suggested_chat_prompts(),
					'feedbackI18n'         => $this->get_chat_feedback_i18n(),
				)
			);
		}
	}

	/**
	 * Vetted starter prompts for the admin chat (localized).
	 *
	 * @return string[]
	 */
	protected function get_suggested_chat_prompts() {
		return array(
			__( 'What was my total revenue this month?', 'unmai-analytix-for-woocommerce' ),
			__( 'How many pending orders do I have?', 'unmai-analytix-for-woocommerce' ),
			__( 'Show me my top 10 best-selling products.', 'unmai-analytix-for-woocommerce' ),
			__( 'Which products are low on stock?', 'unmai-analytix-for-woocommerce' ),
			__( 'How many coupons were used last month?', 'unmai-analytix-for-woocommerce' ),
		);
	}

	/**
	 * Strings for per-message feedback UI (admin chat).
	 *
	 * @return array<string, mixed>
	 */
	protected function get_chat_feedback_i18n() {
		return array(
			'helpfulLabel'    => __( 'Helpful', 'unmai-analytix-for-woocommerce' ),
			'notHelpfulLabel' => __( 'Not helpful', 'unmai-analytix-for-woocommerce' ),
			'thanks'          => __( 'Thanks for your feedback.', 'unmai-analytix-for-woocommerce' ),
			'reasonLabel'     => __( 'What went wrong?', 'unmai-analytix-for-woocommerce' ),
			'submit'          => __( 'Submit feedback', 'unmai-analytix-for-woocommerce' ),
			'optionalNote'    => __( 'Optional details', 'unmai-analytix-for-woocommerce' ),
			'saving'          => __( 'Saving…', 'unmai-analytix-for-woocommerce' ),
			'errorGeneric'    => __( 'Could not save feedback. Try again.', 'unmai-analytix-for-woocommerce' ),
			'reasons'         => array(
				array(
					'value' => 'inaccurate',
					'label' => __( 'Inaccurate or wrong numbers', 'unmai-analytix-for-woocommerce' ),
				),
				array(
					'value' => 'not_helpful',
					'label' => __( 'Not helpful', 'unmai-analytix-for-woocommerce' ),
				),
				array(
					'value' => 'other',
					'label' => __( 'Other', 'unmai-analytix-for-woocommerce' ),
				),
			),
		);
	}

	/**
	 * Render admin page output.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		$api_url   = $this->api_client->get_api_url();
		$api_key   = $this->api_client->get_api_key();
		
		// Get onboarding instance
		$onboarding = new Dataviz_AI_Onboarding( $this->plugin_name, $this->version, $this->api_client );
		?>
		<div class="wrap dataviz-ai-admin">
			<h1><?php esc_html_e( 'Unmai Analytix for WooCommerce', 'unmai-analytix-for-woocommerce' ); ?></h1>

			<div class="dataviz-ai-grid">
				<section class="dataviz-ai-card dataviz-ai-card--wide dataviz-ai-chat-container">
					<?php if ( ! $api_key ) : ?>
						<div class="dataviz-ai-chat-warning">
							<p class="notice inline notice-warning">
								<strong><?php esc_html_e( 'API key required.', 'unmai-analytix-for-woocommerce' ); ?></strong> 
								<?php esc_html_e( 'Please configure your API key via environment variables (OPENAI_API_KEY or DATAVIZ_AI_API_KEY) or by editing the config.php file in the plugin directory.', 'unmai-analytix-for-woocommerce' ); ?>
							</p>
						</div>
					<?php endif; ?>
					
					<div class="dataviz-ai-chat-messages" id="dataviz-ai-chat-messages" role="log" aria-live="polite" aria-atomic="false">
						<div class="dataviz-ai-chat-welcome">
							<h2><?php esc_html_e( 'Chat with me', 'unmai-analytix-for-woocommerce' ); ?></h2>
							<p><?php esc_html_e( 'Ask questions about your WooCommerce store and get AI-powered insights.', 'unmai-analytix-for-woocommerce' ); ?></p>
						</div>
					</div>
					
					<form method="post" class="dataviz-ai-chat-form" data-action="analyze">
						<div class="dataviz-ai-chat-input-wrapper">
							<div class="dataviz-ai-chat-input-row">
								<textarea 
									id="dataviz-ai-question" 
									name="question" 
									rows="1" 
									class="dataviz-ai-chat-input" 
									placeholder="<?php esc_attr_e( 'Message AI assistant...', 'unmai-analytix-for-woocommerce' ); ?>"
									aria-label="<?php esc_attr_e( 'Type your message', 'unmai-analytix-for-woocommerce' ); ?>"
								></textarea>
								<button 
									type="button" 
									class="dataviz-ai-chat-stop" 
									aria-label="<?php esc_attr_e( 'Stop generating', 'unmai-analytix-for-woocommerce' ); ?>"
								>
									<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
										<rect x="2" y="2" width="12" height="12" rx="2" fill="currentColor"/>
									</svg>
								</button>
								<button 
									type="submit" 
									class="dataviz-ai-chat-send" 
									aria-label="<?php esc_attr_e( 'Send message', 'unmai-analytix-for-woocommerce' ); ?>"
									<?php disabled( ! $api_key ); ?>
								>
									<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
										<path d="M.5 1.163A1 1 0 0 1 1.97.28l12.868 6.837a1 1 0 0 1 0 1.766L1.969 15.72A1 1 0 0 1 .5 14.836V10.33a1 1 0 0 1 .816-.983L8.5 8 1.316 6.653A1 1 0 0 1 .5 5.67V1.163Z" fill="currentColor"/>
									</svg>
								</button>
							</div>
							<div class="dataviz-ai-suggested-prompts" id="dataviz-ai-suggested-prompts" hidden></div>
						</div>
					</form>
				</section>
			</div>
		</div>
		<?php
		// Render onboarding overlay if not completed
		$onboarding->render_onboarding_overlay();
	}

}

