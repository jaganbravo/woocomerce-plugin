<?php
/**
 * Onboarding flow for Unmai Analytix WooCommerce plugin.
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
		// Only load on Unmai Analytix admin page
		if ( 'toplevel_page_' . $this->plugin_name !== $hook ) {
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
			'welcome_title'       => __( 'Welcome to Unmai Analytix for WooCommerce!', 'unmai-analytix-for-woocommerce' ),
			'welcome_message'     => __( 'Transform your store data into actionable insights with AI-powered analytics.', 'unmai-analytix-for-woocommerce' ),
			'get_started'         => __( 'Get Started', 'unmai-analytix-for-woocommerce' ),
			'skip'                => __( 'Skip', 'unmai-analytix-for-woocommerce' ),
			'continue'            => __( 'Continue', 'unmai-analytix-for-woocommerce' ),
			'next'                => __( 'Next', 'unmai-analytix-for-woocommerce' ),
			'previous'            => __( 'Previous', 'unmai-analytix-for-woocommerce' ),
			'complete'            => __( 'Complete Setup', 'unmai-analytix-for-woocommerce' ),
			'api_key_required'    => __( 'API Key Required', 'unmai-analytix-for-woocommerce' ),
			'api_key_configured'  => __( 'API Key Configured', 'unmai-analytix-for-woocommerce' ),
			'step'                => __( 'Step', 'unmai-analytix-for-woocommerce' ),
			'of'                  => __( 'of', 'unmai-analytix-for-woocommerce' ),
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
				<h2><?php esc_html_e( 'Welcome to Unmai Analytix for WooCommerce!', 'unmai-analytix-for-woocommerce' ); ?></h2>
			</div>
			<div class="dataviz-ai-onboarding-content">
				<?php $this->render_onboarding_screenshots(); ?>
				<p class="dataviz-ai-onboarding-intro">
					<?php esc_html_e( 'Transform your store data into actionable insights with AI-powered analytics.', 'unmai-analytix-for-woocommerce' ); ?>
				</p>
				<div class="dataviz-ai-onboarding-features">
					<ul>
						<li><?php esc_html_e( 'Ask questions about your store in natural language', 'unmai-analytix-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Get instant insights on orders, products, customers, and more', 'unmai-analytix-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Visualize data with interactive charts', 'unmai-analytix-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Track trends and patterns automatically', 'unmai-analytix-for-woocommerce' ); ?></li>
					</ul>
				</div>
			</div>
			<div class="dataviz-ai-onboarding-footer">
				<button type="button" class="button button-secondary dataviz-ai-onboarding-skip">
					<?php esc_html_e( 'Skip', 'unmai-analytix-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-primary dataviz-ai-onboarding-next">
					<?php esc_html_e( 'Get Started', 'unmai-analytix-for-woocommerce' ); ?>
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
				<h2><?php esc_html_e( 'API Configuration', 'unmai-analytix-for-woocommerce' ); ?></h2>
			</div>
			<div class="dataviz-ai-onboarding-content">
				<?php $this->render_onboarding_step_screenshot( 2 ); ?>
				<?php if ( $has_api_key ) : ?>
					<div class="dataviz-ai-onboarding-success">
						<p><?php esc_html_e( 'Great! Your API key is configured and ready to use.', 'unmai-analytix-for-woocommerce' ); ?></p>
					</div>
				<?php else : ?>
					<div class="dataviz-ai-onboarding-warning">
						<p><strong><?php esc_html_e( 'API Key Required', 'unmai-analytix-for-woocommerce' ); ?></strong></p>
						<p><?php esc_html_e( 'To use Unmai Analytix, you need to configure your API key.', 'unmai-analytix-for-woocommerce' ); ?></p>
						<div class="dataviz-ai-onboarding-instructions">
							<p><strong><?php esc_html_e( 'Option 1: Environment Variable (Recommended)', 'unmai-analytix-for-woocommerce' ); ?></strong></p>
							<p><?php esc_html_e( 'Set one of these environment variables:', 'unmai-analytix-for-woocommerce' ); ?></p>
							<code>OPENAI_API_KEY</code> <?php esc_html_e( 'or', 'unmai-analytix-for-woocommerce' ); ?> <code>UNMAI_ANALYTIX_API_KEY</code>
							<p><strong><?php esc_html_e( 'Option 2: Config File', 'unmai-analytix-for-woocommerce' ); ?></strong></p>
							<p><?php esc_html_e( 'Edit config.php in the plugin directory.', 'unmai-analytix-for-woocommerce' ); ?></p>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<div class="dataviz-ai-onboarding-footer">
				<button type="button" class="button button-secondary dataviz-ai-onboarding-prev">
					<?php esc_html_e( 'Previous', 'unmai-analytix-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-secondary dataviz-ai-onboarding-skip">
					<?php esc_html_e( 'Skip for Now', 'unmai-analytix-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-primary dataviz-ai-onboarding-next">
					<?php esc_html_e( 'Continue', 'unmai-analytix-for-woocommerce' ); ?>
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
				<h2><?php esc_html_e( 'Settings Overview', 'unmai-analytix-for-woocommerce' ); ?></h2>
			</div>
			<div class="dataviz-ai-onboarding-content">
				<?php $this->render_onboarding_step_screenshot( 3 ); ?>
				<p><?php esc_html_e( 'Your plugin is configured with the following settings:', 'unmai-analytix-for-woocommerce' ); ?></p>
				<div class="dataviz-ai-onboarding-settings">
					<ul>
						<li><strong><?php esc_html_e( 'API Endpoint:', 'unmai-analytix-for-woocommerce' ); ?></strong> 
							<?php echo $api_url ? esc_html( $api_url ) : esc_html__( 'Default (OpenAI)', 'unmai-analytix-for-woocommerce' ); ?>
						</li>
						<li><strong><?php esc_html_e( 'Features Enabled:', 'unmai-analytix-for-woocommerce' ); ?></strong>
							<?php esc_html_e( 'Chat Interface, Data Analysis, Charts, History', 'unmai-analytix-for-woocommerce' ); ?>
						</li>
					</ul>
				</div>
			</div>
			<div class="dataviz-ai-onboarding-footer">
				<button type="button" class="button button-secondary dataviz-ai-onboarding-prev">
					<?php esc_html_e( 'Previous', 'unmai-analytix-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-secondary dataviz-ai-onboarding-skip">
					<?php esc_html_e( 'Skip', 'unmai-analytix-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-primary dataviz-ai-onboarding-next">
					<?php esc_html_e( 'Continue', 'unmai-analytix-for-woocommerce' ); ?>
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
				<h2><?php esc_html_e( 'Try Your First Question!', 'unmai-analytix-for-woocommerce' ); ?></h2>
			</div>
			<div class="dataviz-ai-onboarding-content">
				<?php $this->render_onboarding_step_screenshot( 4 ); ?>
				<p><?php esc_html_e( 'The AI assistant is ready to help you understand your store data.', 'unmai-analytix-for-woocommerce' ); ?></p>
				<p><strong><?php esc_html_e( 'Try asking:', 'unmai-analytix-for-woocommerce' ); ?></strong></p>
				<div class="dataviz-ai-onboarding-examples">
					<ul>
						<li>"<?php esc_html_e( 'What are my top-selling products?', 'unmai-analytix-for-woocommerce' ); ?>"</li>
						<li>"<?php esc_html_e( 'Show me orders from last week', 'unmai-analytix-for-woocommerce' ); ?>"</li>
						<li>"<?php esc_html_e( 'How many customers did I get this month?', 'unmai-analytix-for-woocommerce' ); ?>"</li>
					</ul>
				</div>
			</div>
			<div class="dataviz-ai-onboarding-footer">
				<button type="button" class="button button-secondary dataviz-ai-onboarding-prev">
					<?php esc_html_e( 'Previous', 'unmai-analytix-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-secondary dataviz-ai-onboarding-skip">
					<?php esc_html_e( 'Skip Tutorial', 'unmai-analytix-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-primary dataviz-ai-onboarding-next">
					<?php esc_html_e( 'Continue', 'unmai-analytix-for-woocommerce' ); ?>
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
				<h2><?php esc_html_e( 'Discover Features', 'unmai-analytix-for-woocommerce' ); ?></h2>
			</div>
			<div class="dataviz-ai-onboarding-content">
				<?php $this->render_onboarding_step_screenshot( 5 ); ?>
				<div class="dataviz-ai-onboarding-features-list">
					<div class="dataviz-ai-onboarding-feature">
						<strong>📊 <?php esc_html_e( 'Charts & Visualizations', 'unmai-analytix-for-woocommerce' ); ?></strong>
						<p><?php esc_html_e( 'Ask for charts to visualize your data. Example: "Show me a pie chart of order status"', 'unmai-analytix-for-woocommerce' ); ?></p>
					</div>
					<div class="dataviz-ai-onboarding-feature">
						<strong>📈 <?php esc_html_e( 'Data Analysis', 'unmai-analytix-for-woocommerce' ); ?></strong>
						<p><?php esc_html_e( 'Get insights on orders, products, customers. Example: "What are my best-selling products?"', 'unmai-analytix-for-woocommerce' ); ?></p>
					</div>
					<div class="dataviz-ai-onboarding-feature">
						<strong>💬 <?php esc_html_e( 'Chat History', 'unmai-analytix-for-woocommerce' ); ?></strong>
						<p><?php esc_html_e( 'Your conversations are saved automatically. Access history anytime from the chat interface.', 'unmai-analytix-for-woocommerce' ); ?></p>
					</div>
					<div class="dataviz-ai-onboarding-feature">
						<strong>📝 <?php esc_html_e( 'Feature Requests', 'unmai-analytix-for-woocommerce' ); ?></strong>
						<p><?php esc_html_e( 'Request new features directly from the chat. Example: "I\'d like to see product reviews analysis"', 'unmai-analytix-for-woocommerce' ); ?></p>
					</div>
				</div>
			</div>
			<div class="dataviz-ai-onboarding-footer">
				<button type="button" class="button button-secondary dataviz-ai-onboarding-prev">
					<?php esc_html_e( 'Previous', 'unmai-analytix-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-primary dataviz-ai-onboarding-complete">
					<?php esc_html_e( 'Start Using', 'unmai-analytix-for-woocommerce' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render onboarding screenshots gallery when available.
	 *
	 * @return void
	 */
	protected function render_onboarding_screenshots() {
		$screenshots = $this->get_onboarding_screenshots();

		if ( empty( $screenshots ) ) {
			return;
		}
		?>
		<div class="dataviz-ai-onboarding-screenshots">
			<p class="dataviz-ai-onboarding-screenshots-title">
				<?php esc_html_e( 'Preview the onboarding experience', 'unmai-analytix-for-woocommerce' ); ?>
			</p>
			<div class="dataviz-ai-onboarding-screenshots-grid">
				<?php foreach ( $screenshots as $screenshot ) : ?>
					<figure class="dataviz-ai-onboarding-screenshot-card">
						<img src="<?php echo esc_url( $screenshot['url'] ); ?>" alt="<?php echo esc_attr( $screenshot['alt'] ); ?>" loading="lazy" />
						<figcaption><?php echo esc_html( $screenshot['label'] ); ?></figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Bundled onboarding screenshot files (under admin/img/onboarding/).
	 *
	 * @return array<string, string> Filename => label.
	 */
	protected function get_bundled_onboarding_screenshot_map() {
		return array(
			'step-1-welcome.svg'   => __( 'Welcome', 'unmai-analytix-for-woocommerce' ),
			'step-2-api.svg'       => __( 'API configuration', 'unmai-analytix-for-woocommerce' ),
			'step-3-settings.svg'  => __( 'Settings overview', 'unmai-analytix-for-woocommerce' ),
			'step-4-chat.svg'      => __( 'Chat with your business', 'unmai-analytix-for-woocommerce' ),
			'step-5-features.svg'  => __( 'Feature discovery', 'unmai-analytix-for-woocommerce' ),
		);
	}

	/**
	 * Resolve screenshot URL (uploads override plugin file when same basename exists).
	 *
	 * @param string $file Relative filename (e.g. step-1-welcome.svg).
	 * @return array{0:string,1:string}|null Tuple of absolute file path and public URL, or null if missing.
	 */
	protected function resolve_onboarding_screenshot( $file ) {
		$file = basename( $file );
		if ( '' === $file || 'index.php' === $file ) {
			return null;
		}

		$plugin_path = trailingslashit( DATAVIZ_AI_WC_PLUGIN_DIR ) . 'admin/img/onboarding/' . $file;
		$plugin_url  = trailingslashit( DATAVIZ_AI_WC_PLUGIN_URL ) . 'admin/img/onboarding/' . $file;

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['basedir'] ) && ! empty( $uploads['baseurl'] ) ) {
			$upload_path = trailingslashit( $uploads['basedir'] ) . $file;
			$upload_url  = trailingslashit( $uploads['baseurl'] ) . $file;
			if ( file_exists( $upload_path ) ) {
				return array( $upload_path, $upload_url );
			}
		}

		if ( file_exists( $plugin_path ) ) {
			return array( $plugin_path, $plugin_url );
		}

		return null;
	}

	/**
	 * Collect all onboarding screenshots for the welcome gallery.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function get_onboarding_screenshots() {
		$screenshots = array();
		foreach ( $this->get_bundled_onboarding_screenshot_map() as $file => $label ) {
			$resolved = $this->resolve_onboarding_screenshot( $file );
			if ( null === $resolved ) {
				continue;
			}
			list( , $url ) = $resolved;
			$url           = add_query_arg( 'ver', rawurlencode( (string) $this->version ), $url );
			$screenshots[] = array(
				'url'   => $url,
				'alt'   => sprintf(
					/* translators: %s: screenshot label. */
					__( 'Unmai Analytix onboarding screenshot: %s', 'unmai-analytix-for-woocommerce' ),
					$label
				),
				'label' => $label,
			);
		}

		// Optional extra PNGs in uploads (legacy / marketing captures).
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['basedir'] ) && ! empty( $uploads['baseurl'] ) ) {
			$extras = array(
				'dataviz-ai-screenshot-1.png' => __( 'Store dashboard', 'unmai-analytix-for-woocommerce' ),
				'dataviz-ai-screenshot-2.png' => __( 'Insights', 'unmai-analytix-for-woocommerce' ),
				'dataviz-ai-screenshot-3.png' => __( 'Charts', 'unmai-analytix-for-woocommerce' ),
				'dataviz-ai-screenshot.png'   => __( 'Plugin interface', 'unmai-analytix-for-woocommerce' ),
			);
			foreach ( $extras as $file => $label ) {
				$path = trailingslashit( $uploads['basedir'] ) . $file;
				if ( ! file_exists( $path ) ) {
					continue;
				}
				$mtime         = filemtime( $path );
				$ver           = false !== $mtime ? (string) $mtime : (string) $this->version;
				$url           = add_query_arg( 'ver', rawurlencode( $ver ), trailingslashit( $uploads['baseurl'] ) . $file );
				$screenshots[] = array(
					'url'   => $url,
					'alt'   => sprintf(
						/* translators: %s: screenshot label. */
						__( 'Unmai Analytix onboarding screenshot: %s', 'unmai-analytix-for-woocommerce' ),
						$label
					),
					'label' => $label,
				);
			}
		}

		return $screenshots;
	}

	/**
	 * Render a single contextual screenshot for wizard steps 2–5.
	 *
	 * @param int $step Step index 2–5.
	 * @return void
	 */
	protected function render_onboarding_step_screenshot( $step ) {
		$map = array(
			2 => 'step-2-api.svg',
			3 => 'step-3-settings.svg',
			4 => 'step-4-chat.svg',
			5 => 'step-5-features.svg',
		);
		if ( empty( $map[ $step ] ) ) {
			return;
		}
		$labels = $this->get_bundled_onboarding_screenshot_map();
		$file   = $map[ $step ];
		if ( empty( $labels[ $file ] ) ) {
			return;
		}
		$resolved = $this->resolve_onboarding_screenshot( $file );
		if ( null === $resolved ) {
			return;
		}
		list( , $url ) = $resolved;
		$url = add_query_arg( 'ver', rawurlencode( (string) $this->version ), $url );
		$label = $labels[ $file ];
		$alt   = sprintf(
			/* translators: %s: screenshot label. */
			__( 'Unmai Analytix onboarding screenshot: %s', 'unmai-analytix-for-woocommerce' ),
			$label
		);
		?>
		<div class="dataviz-ai-onboarding-step-screenshot">
			<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy" width="640" height="360" />
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
			'message' => __( 'Onboarding completed successfully.', 'unmai-analytix-for-woocommerce' ),
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
			'message' => __( 'Onboarding skipped.', 'unmai-analytix-for-woocommerce' ),
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
				'message' => __( 'Insufficient permissions.', 'unmai-analytix-for-woocommerce' ),
			) );
		}

		$this->reset_onboarding();

		wp_send_json_success( array(
			'message' => __( 'Onboarding reset successfully.', 'unmai-analytix-for-woocommerce' ),
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
				'message' => __( 'Step saved.', 'unmai-analytix-for-woocommerce' ),
			) );
		}

		wp_send_json_error( array(
			'message' => __( 'Invalid step number.', 'unmai-analytix-for-woocommerce' ),
		) );
	}
}
