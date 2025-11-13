<?php
/**
 * Public-facing shortcode and scripts for Dataviz AI chat widget.
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

		add_shortcode( 'dataviz_ai_chat', array( $this, 'render_shortcode' ) );
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
					'send'           => __( 'Send', 'dataviz-ai-woocommerce' ),
					'placeholder'    => __( 'Ask about your store performance…', 'dataviz-ai-woocommerce' ),
					'disconnected'   => __( 'Configure the Dataviz AI API credentials in the admin panel to enable chat.', 'dataviz-ai-woocommerce' ),
					'error_generic'  => __( 'Something went wrong. Please try again.', 'dataviz-ai-woocommerce' ),
				),
			)
		);

		ob_start();
		?>
		<div class="dataviz-ai-chat-widget" data-connected="<?php echo esc_attr( $this->api_client->get_api_url() ? '1' : '0' ); ?>">
			<div class="dataviz-ai-chat-messages" role="log" aria-live="polite"></div>
			<form class="dataviz-ai-chat-form">
				<label for="dataviz-ai-chat-input" class="screen-reader-text"><?php esc_html_e( 'Message', 'dataviz-ai-woocommerce' ); ?></label>
				<textarea id="dataviz-ai-chat-input" name="message" placeholder="<?php esc_attr_e( 'Ask about your store performance…', 'dataviz-ai-woocommerce' ); ?>" required></textarea>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Send', 'dataviz-ai-woocommerce' ); ?></button>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

