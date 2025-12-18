<?php
/**
 * Payment Handler for Dataviz AI WooCommerce Plugin
 *
 * Handles secure online payments via Stripe and PayPal.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles secure payment processing.
 */
class Dataviz_AI_Payment_Handler {

	/**
	 * Stripe API key (from config or environment).
	 *
	 * @var string
	 */
	private $stripe_secret_key;

	/**
	 * Stripe publishable key.
	 *
	 * @var string
	 */
	private $stripe_publishable_key;

	/**
	 * PayPal client ID.
	 *
	 * @var string
	 */
	private $paypal_client_id;

	/**
	 * PayPal client secret.
	 *
	 * @var string
	 */
	private $paypal_client_secret;

	/**
	 * Payment mode (test or live).
	 *
	 * @var string
	 */
	private $payment_mode = 'test';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Get Stripe keys from environment or config
		$this->stripe_secret_key = getenv( 'STRIPE_SECRET_KEY' ) ?: ( defined( 'DATAVIZ_AI_STRIPE_SECRET_KEY' ) ? DATAVIZ_AI_STRIPE_SECRET_KEY : '' );
		$this->stripe_publishable_key = getenv( 'STRIPE_PUBLISHABLE_KEY' ) ?: ( defined( 'DATAVIZ_AI_STRIPE_PUBLISHABLE_KEY' ) ? DATAVIZ_AI_STRIPE_PUBLISHABLE_KEY : '' );
		
		// Get PayPal credentials
		$this->paypal_client_id = getenv( 'PAYPAL_CLIENT_ID' ) ?: ( defined( 'DATAVIZ_AI_PAYPAL_CLIENT_ID' ) ? DATAVIZ_AI_PAYPAL_CLIENT_ID : '' );
		$this->paypal_client_secret = getenv( 'PAYPAL_CLIENT_SECRET' ) ?: ( defined( 'DATAVIZ_AI_PAYPAL_CLIENT_SECRET' ) ? DATAVIZ_AI_PAYPAL_CLIENT_SECRET : '' );

		// Determine payment mode
		$this->payment_mode = getenv( 'PAYMENT_MODE' ) ?: ( defined( 'DATAVIZ_AI_PAYMENT_MODE' ) ? DATAVIZ_AI_PAYMENT_MODE : 'test' );
	}

	/**
	 * Get Stripe publishable key for frontend.
	 *
	 * @return string
	 */
	public function get_stripe_publishable_key() {
		return $this->stripe_publishable_key;
	}

	/**
	 * Get PayPal client ID for frontend.
	 *
	 * @return string
	 */
	public function get_paypal_client_id() {
		return $this->paypal_client_id;
	}

	/**
	 * Check if Stripe is configured.
	 *
	 * @return bool
	 */
	public function is_stripe_configured() {
		return ! empty( $this->stripe_secret_key ) && ! empty( $this->stripe_publishable_key );
	}

	/**
	 * Check if PayPal is configured.
	 *
	 * @return bool
	 */
	public function is_paypal_configured() {
		return ! empty( $this->paypal_client_id ) && ! empty( $this->paypal_client_secret );
	}

	/**
	 * Get checkout URL for a plan.
	 *
	 * @param string $plan Plan name (pro or agency).
	 * @param string $payment_method Payment method (stripe or paypal).
	 * @return string
	 */
	public function get_checkout_url( $plan = 'pro', $payment_method = 'stripe' ) {
		$base_url = admin_url( 'admin.php?page=dataviz-ai-woocommerce-checkout' );
		return add_query_arg(
			array(
				'plan'           => sanitize_text_field( $plan ),
				'payment_method' => sanitize_text_field( $payment_method ),
			),
			$base_url
		);
	}

	/**
	 * Create Stripe payment intent.
	 *
	 * @param string $plan Plan name.
	 * @param float  $amount Amount in dollars.
	 * @param string $currency Currency code.
	 * @return array|WP_Error
	 */
	public function create_stripe_payment_intent( $plan, $amount, $currency = 'USD' ) {
		if ( ! $this->is_stripe_configured() ) {
			return new WP_Error( 'stripe_not_configured', __( 'Stripe is not configured.', 'dataviz-ai-woocommerce' ) );
		}

		// Convert to cents
		$amount_cents = (int) ( $amount * 100 );

		$response = wp_remote_post(
			'https://api.stripe.com/v1/payment_intents',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->stripe_secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'amount'               => $amount_cents,
					'currency'             => strtolower( $currency ),
					'payment_method_types' => array( 'card' ),
					'metadata'             => array(
						'plan'      => $plan,
						'site_url'  => home_url(),
						'user_id'   => get_current_user_id(),
					),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			return new WP_Error(
				'stripe_error',
				isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Stripe API error.', 'dataviz-ai-woocommerce' ),
				$body
			);
		}

		return $body;
	}

	/**
	 * Confirm Stripe payment.
	 *
	 * @param string $payment_intent_id Payment intent ID.
	 * @return array|WP_Error
	 */
	public function confirm_stripe_payment( $payment_intent_id ) {
		if ( ! $this->is_stripe_configured() ) {
			return new WP_Error( 'stripe_not_configured', __( 'Stripe is not configured.', 'dataviz-ai-woocommerce' ) );
		}

		$response = wp_remote_post(
			'https://api.stripe.com/v1/payment_intents/' . $payment_intent_id . '/confirm',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->stripe_secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			return new WP_Error(
				'stripe_error',
				isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Payment confirmation failed.', 'dataviz-ai-woocommerce' ),
				$body
			);
		}

		return $body;
	}

	/**
	 * Create PayPal order.
	 *
	 * @param string $plan Plan name.
	 * @param float  $amount Amount in dollars.
	 * @param string $currency Currency code.
	 * @return array|WP_Error
	 */
	public function create_paypal_order( $plan, $amount, $currency = 'USD' ) {
		if ( ! $this->is_paypal_configured() ) {
			return new WP_Error( 'paypal_not_configured', __( 'PayPal is not configured.', 'dataviz-ai-woocommerce' ) );
		}

		// Get PayPal access token first
		$access_token = $this->get_paypal_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$paypal_url = 'test' === $this->payment_mode
			? 'https://api.sandbox.paypal.com/v2/checkout/orders'
			: 'https://api.paypal.com/v2/checkout/orders';

		$response = wp_remote_post(
			$paypal_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'intent' => 'CAPTURE',
					'purchase_units' => array(
						array(
							'amount' => array(
								'currency_code' => $currency,
								'value'         => number_format( $amount, 2, '.', '' ),
							),
							'description' => sprintf(
								/* translators: %s: Plan name */
								__( 'Dataviz AI %s Plan', 'dataviz-ai-woocommerce' ),
								ucfirst( $plan )
							),
						),
					),
					'application_context' => array(
						'brand_name'          => 'Dataviz AI',
						'landing_page'       => 'BILLING',
						'user_action'        => 'PAY_NOW',
						'return_url'         => admin_url( 'admin.php?page=dataviz-ai-woocommerce-checkout&paypal_return=1' ),
						'cancel_url'         => admin_url( 'admin.php?page=dataviz-ai-woocommerce-license' ),
					),
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			return new WP_Error(
				'paypal_error',
				isset( $body['message'] ) ? $body['message'] : __( 'PayPal API error.', 'dataviz-ai-woocommerce' ),
				$body
			);
		}

		return $body;
	}

	/**
	 * Get PayPal access token.
	 *
	 * @return string|WP_Error
	 */
	private function get_paypal_access_token() {
		$paypal_url = 'test' === $this->payment_mode
			? 'https://api.sandbox.paypal.com/v1/oauth2/token'
			: 'https://api.paypal.com/v1/oauth2/token';

		$response = wp_remote_post(
			$paypal_url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->paypal_client_id . ':' . $this->paypal_client_secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type' => 'client_credentials',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			return new WP_Error(
				'paypal_auth_error',
				__( 'PayPal authentication failed.', 'dataviz-ai-woocommerce' ),
				$body
			);
		}

		return isset( $body['access_token'] ) ? $body['access_token'] : new WP_Error( 'paypal_no_token', __( 'No access token received.', 'dataviz-ai-woocommerce' ) );
	}

	/**
	 * Get plan pricing.
	 *
	 * @param string $plan Plan name.
	 * @return array
	 */
	public function get_plan_pricing( $plan ) {
		$pricing = array(
			'pro' => array(
				'monthly' => 15.00,
				'yearly'  => 150.00,
				'currency' => 'USD',
			),
			'agency' => array(
				'monthly' => 99.00,
				'yearly'  => 990.00,
				'currency' => 'USD',
			),
		);

		return isset( $pricing[ $plan ] ) ? $pricing[ $plan ] : $pricing['pro'];
	}

	/**
	 * Generate license key after successful payment.
	 *
	 * @param string $plan Plan name.
	 * @param int    $user_id User ID.
	 * @param string $payment_id Payment transaction ID.
	 * @return string License key.
	 */
	public function generate_license_key( $plan, $user_id, $payment_id ) {
		// Generate unique license key
		$prefix = 'PRO' === strtoupper( $plan ) ? 'DVZ-PRO' : 'DVZ-AGY';
		$key = $prefix . '-' . strtoupper( wp_generate_password( 12, false ) ) . '-' . substr( md5( $user_id . $payment_id . time() ), 0, 8 );
		
		// Store license key in database (you may want to create a separate table)
		update_option( 'dataviz_ai_license_' . md5( $key ), array(
			'license_key' => $key,
			'plan'        => $plan,
			'user_id'     => $user_id,
			'payment_id'  => $payment_id,
			'created_at'  => current_time( 'mysql' ),
			'expires_at'  => date( 'Y-m-d', strtotime( '+1 year' ) ),
		) );

		return $key;
	}

	/**
	 * Send license key via email.
	 *
	 * @param string $license_key License key.
	 * @param string $email Email address.
	 * @param string $plan Plan name.
	 * @return bool
	 */
	public function send_license_key_email( $license_key, $email, $plan ) {
		$subject = sprintf(
			/* translators: %s: Plan name */
			__( 'Your Dataviz AI %s License Key', 'dataviz-ai-woocommerce' ),
			ucfirst( $plan )
		);

		$message = sprintf(
			/* translators: %1$s: Plan name, %2$s: License key, %3$s: Site URL */
			__(
				'Thank you for purchasing the Dataviz AI %1$s plan!

Your license key is: %2$s

To activate your license:
1. Go to your WordPress admin dashboard
2. Navigate to Dataviz AI → License
3. Enter your license key and click "Activate License"

If you have any questions, please contact support.

Thank you!
Dataviz AI Team

%3$s',
				'dataviz-ai-woocommerce'
			),
			ucfirst( $plan ),
			$license_key,
			home_url()
		);

		return wp_mail( $email, $subject, $message );
	}
}

