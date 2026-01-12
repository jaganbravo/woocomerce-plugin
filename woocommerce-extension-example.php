<?php
/**
 * Plugin Name: WooCommerce Extension Example
 * Plugin URI: https://yourwebsite.com
 * Description: An example WooCommerce extension plugin demonstrating best practices
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: woocommerce-extension-example
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.3
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'WCE_PLUGIN_VERSION', '1.0.0' );
define( 'WCE_PLUGIN_FILE', __FILE__ );
define( 'WCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class WC_Extension_Example {
	
	/**
	 * Plugin instance
	 *
	 * @var WC_Extension_Example
	 */
	private static $instance = null;
	
	/**
	 * Get plugin instance
	 *
	 * @return WC_Extension_Example
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		// Check if WooCommerce is active
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		
		// Register activation hook
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		
		// Register deactivation hook
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}
	
	/**
	 * Initialize plugin
	 */
	public function init() {
		// Check if WooCommerce is installed and active
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}
		
		// Load plugin textdomain
		load_plugin_textdomain( 'woocommerce-extension-example', false, dirname( WCE_PLUGIN_BASENAME ) . '/languages' );
		
		// Include required files
		$this->includes();
		
		// Initialize hooks
		$this->init_hooks();
	}
	
	/**
	 * Include required files
	 */
	private function includes() {
		// Include admin class if in admin
		if ( is_admin() ) {
			// require_once WCE_PLUGIN_DIR . 'includes/class-wce-admin.php';
		}
		
		// Include frontend class if not in admin
		if ( ! is_admin() ) {
			// require_once WCE_PLUGIN_DIR . 'includes/class-wce-frontend.php';
		}
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Example: Add custom product field
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_custom_product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_custom_product_field' ) );
		
		// Example: Add custom checkout field
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_custom_checkout_field' ) );
		
		// Example: Add custom cart fee
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_custom_cart_fee' ) );
		
		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}
	
	/**
	 * Display notice if WooCommerce is not installed
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="error">
			<p>
				<strong><?php esc_html_e( 'WooCommerce Extension Example', 'woocommerce-extension-example' ); ?></strong>
				<?php
				printf(
					/* translators: 1: WooCommerce download link */
					esc_html__( ' requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-extension-example' ),
					'<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Add custom product field
	 */
	public function add_custom_product_field() {
		global $woocommerce, $post;
		
		echo '<div class="product_custom_field">';
		
		// Custom Product Text Field
		woocommerce_wp_text_input(
			array(
				'id'          => '_custom_product_text_field',
				'label'       => __( 'Custom Text Field', 'woocommerce-extension-example' ),
				'placeholder' => __( 'Enter custom text', 'woocommerce-extension-example' ),
				'desc_tip'    => 'true',
				'description' => __( 'This is a custom product field example.', 'woocommerce-extension-example' ),
			)
		);
		
		echo '</div>';
	}
	
	/**
	 * Save custom product field
	 *
	 * @param int $post_id Post ID
	 */
	public function save_custom_product_field( $post_id ) {
		// Custom Product Text Field
		$custom_product_text_field = isset( $_POST['_custom_product_text_field'] ) ? sanitize_text_field( $_POST['_custom_product_text_field'] ) : '';
		update_post_meta( $post_id, '_custom_product_text_field', $custom_product_text_field );
	}
	
	/**
	 * Add custom checkout field
	 *
	 * @param array $fields Checkout fields
	 * @return array
	 */
	public function add_custom_checkout_field( $fields ) {
		$fields['billing']['billing_custom_field'] = array(
			'label'       => __( 'Custom Field', 'woocommerce-extension-example' ),
			'placeholder' => _x( 'Enter custom value', 'placeholder', 'woocommerce-extension-example' ),
			'required'    => false,
			'class'       => array( 'form-row-wide' ),
			'clear'       => true,
		);
		
		return $fields;
	}
	
	/**
	 * Add custom cart fee
	 */
	public function add_custom_cart_fee() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		
		// Example: Add a $5 fee if cart total is over $100
		$cart_total = WC()->cart->get_subtotal();
		if ( $cart_total > 100 ) {
			WC()->cart->add_fee( __( 'Custom Fee', 'woocommerce-extension-example' ), 5 );
		}
	}
	
	/**
	 * Enqueue frontend scripts and styles
	 */
	public function enqueue_scripts() {
		if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() ) {
			return;
		}
		
		wp_enqueue_style(
			'wce-frontend-style',
			WCE_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			WCE_PLUGIN_VERSION
		);
		
		wp_enqueue_script(
			'wce-frontend-script',
			WCE_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			WCE_PLUGIN_VERSION,
			true
		);
	}
	
	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 */
	public function admin_enqueue_scripts( $hook ) {
		// Only load on product edit pages
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		
		global $post;
		if ( $post && 'product' !== $post->post_type ) {
			return;
		}
		
		wp_enqueue_style(
			'wce-admin-style',
			WCE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WCE_PLUGIN_VERSION
		);
		
		wp_enqueue_script(
			'wce-admin-script',
			WCE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WCE_PLUGIN_VERSION,
			true
		);
	}
	
	/**
	 * Plugin activation
	 */
	public function activate() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				esc_html__( 'This plugin requires WooCommerce to be installed and active.', 'woocommerce-extension-example' ),
				esc_html__( 'Plugin Activation Error', 'woocommerce-extension-example' ),
				array( 'back_link' => true )
			);
		}
		
		// Create database tables if needed
		// $this->create_tables();
		
		// Set default options
		// add_option( 'wce_version', WCE_PLUGIN_VERSION );
		
		// Flush rewrite rules if needed
		flush_rewrite_rules();
	}
	
	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clean up temporary data
		// Flush rewrite rules if needed
		flush_rewrite_rules();
	}
	
	/**
	 * Plugin uninstall (handled by uninstall.php)
	 */
	public static function uninstall() {
		// Delete options
		// delete_option( 'wce_version' );
		
		// Drop database tables if created
		// Remove any other data
	}
}

/**
 * Initialize the plugin
 */
function wce_init() {
	return WC_Extension_Example::get_instance();
}

// Start the plugin
wce_init();

