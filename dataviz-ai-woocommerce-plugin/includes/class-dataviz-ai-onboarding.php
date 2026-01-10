<?php
/**
 * Onboarding flow for Dataviz AI WooCommerce plugin.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the onboarding experience for new users.
 */
class Dataviz_AI_Onboarding {

	/**
	 * Plugin name.
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
	 * API client instance.
	 *
	 * @var Dataviz_AI_API_Client
	 */
	protected $api_client;

	/**
	 * Constructor.
	 *
	 * @param string                $plugin_name Plugin name.
	 * @param string                $version     Plugin version.
	 * @param Dataviz_AI_API_Client $api_client  API client instance.
	 */
	public function __construct( $plugin_name, $version, Dataviz_AI_API_Client $api_client ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->api_client  = $api_client;
	}

	/**
	 * Initialize onboarding hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_dataviz_ai_complete_onboarding', array( $this, 'ajax_complete_onboarding' ) );
		add_action( 'wp_ajax_dataviz_ai_skip_onboarding', array( $this, 'ajax_skip_onboarding' ) );
		add_action( 'wp_ajax_dataviz_ai_reset_onboarding', array( $this, 'ajax_reset_onboarding' ) );
		add_action( 'wp_ajax_dataviz_ai_get_onboarding_status', array( $this, 'ajax_get_onboarding_status' ) );
	}

	/**
	 * Enqueue onboarding assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// Only load on Dataviz AI admin page
		if ( 'toplevel_page_dataviz-ai-woocommerce' !== $hook ) {
			return;
		}

		// Enqueue onboarding styles
		wp_enqueue_style(
			$this->plugin_name . '-onboarding',
			DATAVIZ_AI_WC_PLUGIN_URL . 'admin/css/onboarding.css',
			array(),
			$this->version
		);

		// Enqueue onboarding scripts
		wp_enqueue_script(
			$this->plugin_name . '-onboarding',
			DATAVIZ_AI_WC_PLUGIN_URL . 'admin/js/onboarding.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Localize script
		wp_localize_script(
			$this->plugin_name . '-onboarding',
			'DatavizAIOnboarding',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'dataviz_ai_onboarding' ),
				'isCompleted'   => $this->is_onboarding_completed(),
				'currentStep'   => $this->get_current_step(),
				'hasApiKey'      => ! empty( $this->api_client->get_api_key() ),
				'apiUrl'         => $this->api_client->get_api_url(),
				'strings'        => $this->get_strings(),
			)
		);
	}

	/**
	 * Check if onboarding is completed for current user.
	 *
	 * @return bool
	 */
	public function is_onboarding_completed() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		return (bool) get_user_meta( $user_id, 'dataviz_ai_onboarding_completed', true );
	}

	/**
	 * Mark onboarding as completed.
	 *
	 * @param int $user_id User ID (optional, defaults to current user).
	 * @return void
	 */
	public function complete_onboarding( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return;
		}

		update_user_meta( $user_id, 'dataviz_ai_onboarding_completed', true );
		update_user_meta( $user_id, 'dataviz_ai_onboarding_completed_at', current_time( 'mysql' ) );
		update_user_meta( $user_id, 'dataviz_ai_onboarding_version', $this->version );
	}

	/**
	 * Skip onboarding.
	 *
	 * @param int $user_id User ID (optional, defaults to current user).
	 * @return void
	 */
	public function skip_onboarding( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return;
		}

		update_user_meta( $user_id, 'dataviz_ai_onboarding_skipped', true );
		update_user_meta( $user_id, 'dataviz_ai_onboarding_skipped_at', current_time( 'mysql' ) );
		$this->complete_onboarding( $user_id ); // Mark as completed so it doesn't show again
	}

	/**
	 * Reset onboarding for a user.
	 *
	 * @param int $user_id User ID (optional, defaults to current user).
	 * @return void
	 */
	public function reset_onboarding( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return;
		}

		// Only allow admins to reset onboarding
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		delete_user_meta( $user_id, 'dataviz_ai_onboarding_completed' );
		delete_user_meta( $user_id, 'dataviz_ai_onboarding_completed_at' );
		delete_user_meta( $user_id, 'dataviz_ai_onboarding_skipped' );
		delete_user_meta( $user_id, 'dataviz_ai_onboarding_skipped_at' );
		delete_user_meta( $user_id, 'dataviz_ai_onboarding_current_step' );
		delete_user_meta( $user_id, 'dataviz_ai_onboarding_version' );
	}

	/**
	 * Get current onboarding step.
	 *
	 * @param int $user_id User ID (optional, defaults to current user).
	 * @return int Step number (1-5).
	 */
	public function get_current_step( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return 1;
		}

		$step = get_user_meta( $user_id, 'dataviz_ai_onboarding_current_step', true );
		return $step ? (int) $step : 1;
	}

	/**
	 * Set current onboarding step.
	 *
	 * @param int $step    Step number (1-5).
	 * @param int $user_id User ID (optional, defaults to current user).
	 * @return void
	 */
	public function set_current_step( $step, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return;
		}

		update_user_meta( $user_id, 'dataviz_ai_onboarding_current_step', (int) $step );
	}

	/**
	 * Get translatable strings.
	 *
	 * @return array
	 */
	protected function get_strings() {
		return array(
			'welcome_title'       => __( 'Welcome to Dataviz AI for WooCommerce!', 'dataviz-ai-woocommerce' ),
			'welcome_message'     => __( 'Transform your store data into actionable insights with AI-powered analytics.', 'dataviz-ai-woocommerce' ),
			'get_started'         => __( 'Get Started', 'dataviz-ai-woocommerce' ),
			'skip'                => __( 'Skip', 'dataviz-ai-woocommerce' ),
			'continue'            => __( 'Continue', 'dataviz-ai-woocommerce' ),
			'next'                => __( 'Next', 'dataviz-ai-woocommerce' ),
			'previous'            => __( 'Previous', 'dataviz-ai-woocommerce' ),
			'complete'            => __( 'Complete Setup', 'dataviz-ai-woocommerce' ),
			'api_key_required'    => __( 'API Key Required', 'dataviz-ai-woocommerce' ),
			'api_key_configured'  => __( 'API Key Configured', 'dataviz-ai-woocommerce' ),
			'step'                => __( 'Step', 'dataviz-ai-woocommerce' ),
			'of'                  => __( 'of', 'dataviz-ai-woocommerce' ),
		);
	}

	/**
	 * Render onboarding overlay.
	 *
	 * @return void
	 */
	public function render_onboarding_overlay() {
		if ( $this->is_onboarding_completed() ) {
			return;
		}

		$current_step = $this->get_current_step();
		$has_api_key  = ! empty( $this->api_client->get_api_key() );
		?>
		<div id="dataviz-ai-onboarding-overlay" class="dataviz-ai-onboarding-overlay" data-step="<?php echo esc_attr( $current_step ); ?>">
			<div class="dataviz-ai-onboarding-container">
				<?php $this->render_step( $current_step, $has_api_key ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render specific onboarding step.
	 *
	 * @param int  $step        Step number (1-5).
	 * @param bool $has_api_key Whether API key is configured.
	 * @return void
	 */
	protected function render_step( $step, $has_api_key ) {
		switch ( $step ) {
			case 1:
				$this->render_welcome_step();
				break;
			case 2:
				$this->render_api_config_step( $has_api_key );
				break;
			case 3:
				$this->render_settings_step();
				break;
			case 4:
				$this->render_tutorial_step();
				break;
			case 5:
				$this->render_features_step();
				break;
			default:
				$this->render_welcome_step();
		}
	}

	/**
	 * Render welcome step (Step 1).
	 *
	 * @return void
	 */
	protected function render_welcome_step() {
		?>
		<div class="dataviz-ai-onboarding-step" data-step="1">
			<div class="dataviz-ai-onboarding-header">
				<h2><?php esc_html_e( 'Welcome to Dataviz AI for WooCommerce!', 'dataviz-ai-woocommerce' ); ?></h2>
			</div>
			<div class="dataviz-ai-onboarding-content">
				<p class="dataviz-ai-onboarding-intro">
					<?php esc_html_e( 'Transform your store data into actionable insights with AI-powered analytics.', 'dataviz-ai-woocommerce' ); ?>
				</p>
				<div class="dataviz-ai-onboarding-features">
					<ul>
						<li><?php esc_html_e( 'Ask questions about your store in natural language', 'dataviz-ai-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Get instant insights on orders, products, customers, and more', 'dataviz-ai-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Visualize data with interactive charts', 'dataviz-ai-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Track trends and patterns automatically', 'dataviz-ai-woocommerce' ); ?></li>
					</ul>
				</div>
			</div>
			<div class="dataviz-ai-onboarding-footer">
				<button type="button" class="button button-secondary dataviz-ai-onboarding-skip">
					<?php esc_html_e( 'Skip', 'dataviz-ai-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-primary dataviz-ai-onboarding-next">
					<?php esc_html_e( 'Get Started', 'dataviz-ai-woocommerce' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render API configuration step (Step 2).
	 *
	 * @param bool $has_api_key Whether API key is configured.
	 * @return void
	 */
	protected function render_api_config_step( $has_api_key ) {
		?>
		<div class="dataviz-ai-onboarding-step" data-step="2">
			<div class="dataviz-ai-onboarding-header">
				<h2><?php esc_html_e( 'API Configuration', 'dataviz-ai-woocommerce' ); ?></h2>
			</div>
			<div class="dataviz-ai-onboarding-content">
				<?php if ( $has_api_key ) : ?>
					<div class="dataviz-ai-onboarding-success">
						<p><?php esc_html_e( 'Great! Your API key is configured and ready to use.', 'dataviz-ai-woocommerce' ); ?></p>
					</div>
				<?php else : ?>
					<div class="dataviz-ai-onboarding-warning">
						<p><strong><?php esc_html_e( 'API Key Required', 'dataviz-ai-woocommerce' ); ?></strong></p>
						<p><?php esc_html_e( 'To use Dataviz AI, you need to configure your API key.', 'dataviz-ai-woocommerce' ); ?></p>
						<div class="dataviz-ai-onboarding-instructions">
							<p><strong><?php esc_html_e( 'Option 1: Environment Variable (Recommended)', 'dataviz-ai-woocommerce' ); ?></strong></p>
							<p><?php esc_html_e( 'Set one of these environment variables:', 'dataviz-ai-woocommerce' ); ?></p>
							<code>OPENAI_API_KEY</code> <?php esc_html_e( 'or', 'dataviz-ai-woocommerce' ); ?> <code>DATAVIZ_AI_API_KEY</code>
							<p><strong><?php esc_html_e( 'Option 2: Config File', 'dataviz-ai-woocommerce' ); ?></strong></p>
							<p><?php esc_html_e( 'Edit config.php in the plugin directory.', 'dataviz-ai-woocommerce' ); ?></p>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<div class="dataviz-ai-onboarding-footer">
				<button type="button" class="button button-secondary dataviz-ai-onboarding-prev">
					<?php esc_html_e( 'Previous', 'dataviz-ai-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-secondary dataviz-ai-onboarding-skip">
					<?php esc_html_e( 'Skip for Now', 'dataviz-ai-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-primary dataviz-ai-onboarding-next">
					<?php esc_html_e( 'Continue', 'dataviz-ai-woocommerce' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings overview step (Step 3).
	 *
	 * @return void
	 */
	protected function render_settings_step() {
		$api_url = $this->api_client->get_api_url();
		?>
		<div class="dataviz-ai-onboarding-step" data-step="3">
			<div class="dataviz-ai-onboarding-header">
				<h2><?php esc_html_e( 'Settings Overview', 'dataviz-ai-woocommerce' ); ?></h2>
			</div>
			<div class="dataviz-ai-onboarding-content">
				<p><?php esc_html_e( 'Your plugin is configured with the following settings:', 'dataviz-ai-woocommerce' ); ?></p>
				<div class="dataviz-ai-onboarding-settings">
					<ul>
						<li><strong><?php esc_html_e( 'API Endpoint:', 'dataviz-ai-woocommerce' ); ?></strong> 
							<?php echo $api_url ? esc_html( $api_url ) : esc_html__( 'Default (OpenAI)', 'dataviz-ai-woocommerce' ); ?>
						</li>
						<li><strong><?php esc_html_e( 'Features Enabled:', 'dataviz-ai-woocommerce' ); ?></strong>
							<?php esc_html_e( 'Chat Interface, Data Analysis, Charts, History', 'dataviz-ai-woocommerce' ); ?>
						</li>
					</ul>
				</div>
			</div>
			<div class="dataviz-ai-onboarding-footer">
				<button type="button" class="button button-secondary dataviz-ai-onboarding-prev">
					<?php esc_html_e( 'Previous', 'dataviz-ai-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-secondary dataviz-ai-onboarding-skip">
					<?php esc_html_e( 'Skip', 'dataviz-ai-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-primary dataviz-ai-onboarding-next">
					<?php esc_html_e( 'Continue', 'dataviz-ai-woocommerce' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render tutorial step (Step 4).
	 *
	 * @return void
	 */
	protected function render_tutorial_step() {
		?>
		<div class="dataviz-ai-onboarding-step" data-step="4">
			<div class="dataviz-ai-onboarding-header">
				<h2><?php esc_html_e( 'Try Your First Question!', 'dataviz-ai-woocommerce' ); ?></h2>
			</div>
			<div class="dataviz-ai-onboarding-content">
				<p><?php esc_html_e( 'The AI assistant is ready to help you understand your store data.', 'dataviz-ai-woocommerce' ); ?></p>
				<p><strong><?php esc_html_e( 'Try asking:', 'dataviz-ai-woocommerce' ); ?></strong></p>
				<div class="dataviz-ai-onboarding-examples">
					<ul>
						<li>"<?php esc_html_e( 'What are my top-selling products?', 'dataviz-ai-woocommerce' ); ?>"</li>
						<li>"<?php esc_html_e( 'Show me orders from last week', 'dataviz-ai-woocommerce' ); ?>"</li>
						<li>"<?php esc_html_e( 'How many customers did I get this month?', 'dataviz-ai-woocommerce' ); ?>"</li>
					</ul>
				</div>
			</div>
			<div class="dataviz-ai-onboarding-footer">
				<button type="button" class="button button-secondary dataviz-ai-onboarding-prev">
					<?php esc_html_e( 'Previous', 'dataviz-ai-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-secondary dataviz-ai-onboarding-skip">
					<?php esc_html_e( 'Skip Tutorial', 'dataviz-ai-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-primary dataviz-ai-onboarding-next">
					<?php esc_html_e( 'Continue', 'dataviz-ai-woocommerce' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render features discovery step (Step 5).
	 *
	 * @return void
	 */
	protected function render_features_step() {
		?>
		<div class="dataviz-ai-onboarding-step" data-step="5">
			<div class="dataviz-ai-onboarding-header">
				<h2><?php esc_html_e( 'Discover Features', 'dataviz-ai-woocommerce' ); ?></h2>
			</div>
			<div class="dataviz-ai-onboarding-content">
				<div class="dataviz-ai-onboarding-features-list">
					<div class="dataviz-ai-onboarding-feature">
						<strong>📊 <?php esc_html_e( 'Charts & Visualizations', 'dataviz-ai-woocommerce' ); ?></strong>
						<p><?php esc_html_e( 'Ask for charts to visualize your data. Example: "Show me a pie chart of order status"', 'dataviz-ai-woocommerce' ); ?></p>
					</div>
					<div class="dataviz-ai-onboarding-feature">
						<strong>📈 <?php esc_html_e( 'Data Analysis', 'dataviz-ai-woocommerce' ); ?></strong>
						<p><?php esc_html_e( 'Get insights on orders, products, customers. Example: "What are my best-selling products?"', 'dataviz-ai-woocommerce' ); ?></p>
					</div>
					<div class="dataviz-ai-onboarding-feature">
						<strong>💬 <?php esc_html_e( 'Chat History', 'dataviz-ai-woocommerce' ); ?></strong>
						<p><?php esc_html_e( 'Your conversations are saved automatically. Access history anytime from the chat interface.', 'dataviz-ai-woocommerce' ); ?></p>
					</div>
					<div class="dataviz-ai-onboarding-feature">
						<strong>📝 <?php esc_html_e( 'Feature Requests', 'dataviz-ai-woocommerce' ); ?></strong>
						<p><?php esc_html_e( 'Request new features directly from the chat. Example: "I\'d like to see product reviews analysis"', 'dataviz-ai-woocommerce' ); ?></p>
					</div>
				</div>
			</div>
			<div class="dataviz-ai-onboarding-footer">
				<button type="button" class="button button-secondary dataviz-ai-onboarding-prev">
					<?php esc_html_e( 'Previous', 'dataviz-ai-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-primary dataviz-ai-onboarding-complete">
					<?php esc_html_e( 'Start Using', 'dataviz-ai-woocommerce' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: Complete onboarding.
	 *
	 * @return void
	 */
	public function ajax_complete_onboarding() {
		check_ajax_referer( 'dataviz_ai_onboarding', 'nonce' );

		$this->complete_onboarding();

		wp_send_json_success( array(
			'message' => __( 'Onboarding completed successfully.', 'dataviz-ai-woocommerce' ),
		) );
	}

	/**
	 * AJAX handler: Skip onboarding.
	 *
	 * @return void
	 */
	public function ajax_skip_onboarding() {
		check_ajax_referer( 'dataviz_ai_onboarding', 'nonce' );

		$this->skip_onboarding();

		wp_send_json_success( array(
			'message' => __( 'Onboarding skipped.', 'dataviz-ai-woocommerce' ),
		) );
	}

	/**
	 * AJAX handler: Reset onboarding.
	 *
	 * @return void
	 */
	public function ajax_reset_onboarding() {
		check_ajax_referer( 'dataviz_ai_onboarding', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Insufficient permissions.', 'dataviz-ai-woocommerce' ),
			) );
		}

		$this->reset_onboarding();

		wp_send_json_success( array(
			'message' => __( 'Onboarding reset successfully.', 'dataviz-ai-woocommerce' ),
		) );
	}

	/**
	 * AJAX handler: Get onboarding status.
	 *
	 * @return void
	 */
	public function ajax_get_onboarding_status() {
		check_ajax_referer( 'dataviz_ai_onboarding', 'nonce' );

		wp_send_json_success( array(
			'completed'   => $this->is_onboarding_completed(),
			'currentStep' => $this->get_current_step(),
			'hasApiKey'   => ! empty( $this->api_client->get_api_key() ),
		) );
	}

	/**
	 * AJAX handler: Save onboarding step.
	 *
	 * @return void
	 */
	public function ajax_save_onboarding_step() {
		check_ajax_referer( 'dataviz_ai_onboarding', 'nonce' );

		$step = isset( $_POST['step'] ) ? (int) $_POST['step'] : 1;

		if ( $step >= 1 && $step <= 5 ) {
			$this->set_current_step( $step );
			wp_send_json_success( array(
				'message' => __( 'Step saved.', 'dataviz-ai-woocommerce' ),
			) );
		}

		wp_send_json_error( array(
			'message' => __( 'Invalid step number.', 'dataviz-ai-woocommerce' ),
		) );
	}
}
