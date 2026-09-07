<?php
/**
 * Public-facing shortcode and scripts for Unmai Analytix chat widget.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a chat-style shortcode that communicates with the AI backend.
 */
class Dataviz_AI_Chat_Widget {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected static $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected static $version;

	/**
	 * API client.
	 *
	 * @var Dataviz_AI_API_Client
	 */
	protected $api_client;

	/**
	 * Constructor.
	 *
	 * @param string                  $plugin_name Plugin slug.
	 * @param string                  $version     Plugin version.
	 * @param Dataviz_AI_API_Client   $api_client  API client instance.
	 */
	public function __construct( $plugin_name, $version, Dataviz_AI_API_Client $api_client ) {
		self::$plugin_name = $plugin_name;
		self::$version     = $version;
		$this->api_client  = $api_client;

		add_shortcode( 'unmai_analytix_chat', array( $this, 'render_shortcode' ) );
		add_shortcode( 'dataviz_ai_chat', array( $this, 'render_shortcode' ) ); // Legacy alias.
	}

	/**
	 * Register public assets.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style(
			self::$plugin_name . '-chat',
			DATAVIZ_AI_WC_PLUGIN_URL . 'public/css/chat-widget.css',
			array(),
			self::$version
		);

		wp_register_script(
			self::$plugin_name . '-chat',
			DATAVIZ_AI_WC_PLUGIN_URL . 'public/js/chat-widget.js',
			array( 'jquery' ),
			self::$version,
			true
		);
	}

	/**
	 * Render shortcode output.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_shortcode( $atts = array() ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) {
			return '<p class="unmai-analytix-chat-widget unmai-analytix-chat-widget--restricted dataviz-ai-chat-widget dataviz-ai-chat-widget--restricted">' . esc_html__( 'Store analytics chat is only available to shop managers. Use Unmai Analytix from the WordPress admin.', 'unmai-analytix-for-woocommerce' ) . '</p>';
		}

		wp_enqueue_style( self::$plugin_name . '-chat' );
		wp_enqueue_script( self::$plugin_name . '-chat' );

		wp_localize_script(
			self::$plugin_name . '-chat',
			'DatavizAIChat',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'dataviz_ai_chat' ),
				'connected' => $this->api_client->get_api_url() && $this->api_client->get_api_key(),
				'strings'   => array(
					'send'           => __( 'Send', 'unmai-analytix-for-woocommerce' ),
					'placeholder'    => __( 'Ask about your store performance…', 'unmai-analytix-for-woocommerce' ),
					'disconnected'   => __( 'Configure the Unmai Analytix API credentials in the admin panel to enable chat.', 'unmai-analytix-for-woocommerce' ),
					'error_generic'  => __( 'Something went wrong. Please try again.', 'unmai-analytix-for-woocommerce' ),
				),
			)
		);

		ob_start();
		?>
		<div class="unmai-analytix-chat-widget dataviz-ai-chat-widget" data-connected="<?php echo esc_attr( $this->api_client->get_api_url() ? '1' : '0' ); ?>">
			<div class="unmai-analytix-chat-messages dataviz-ai-chat-messages" role="log" aria-live="polite"></div>
			<form class="unmai-analytix-chat-form dataviz-ai-chat-form">
				<label for="unmai-analytix-chat-input" class="screen-reader-text"><?php esc_html_e( 'Message', 'unmai-analytix-for-woocommerce' ); ?></label>
				<textarea id="unmai-analytix-chat-input" name="message" placeholder="<?php esc_attr_e( 'Ask about your store performance…', 'unmai-analytix-for-woocommerce' ); ?>" required></textarea>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Send', 'unmai-analytix-for-woocommerce' ); ?></button>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

