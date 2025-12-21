<?php
/**
 * Payment Handler for Dataviz AI WooCommerce plugin.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles payment processing and license key generation.
 */
class Dataviz_AI_Payment_Handler {

	/**
	 * Create Stripe payment intent.
	 *
	 * @param string $plan     Plan name (e.g., 'pro').
	 * @param float  $amount   Amount to charge.
	 * @param string $currency Currency code (e.g., 'USD').
	 * @return array|WP_Error
	 */
	public function create_stripe_payment_intent( $plan, $amount, $currency = 'USD' ) {
		// Stub implementation - return error for now
		return new WP_Error(
			'stripe_not_configured',
			__( 'Stripe is not configured. Please configure payment settings.', 'dataviz-ai-woocommerce' )
		);
	}

	/**
	 * Generate a license key.
	 *
	 * @param string $plan      Plan name.
	 * @param int    $user_id   User ID.
	 * @param string $payment_id Payment ID.
	 * @return string
	 */
	public function generate_license_key( $plan, $user_id, $payment_id ) {
		// Generate a simple license key
		$key_data = array(
			'plan'       => $plan,
			'user_id'    => $user_id,
			'payment_id' => $payment_id,
			'timestamp'  => time(),
		);
		$hash = wp_hash( serialize( $key_data ) );
		return 'DATAVIZ-' . strtoupper( substr( $hash, 0, 16 ) ) . '-' . strtoupper( substr( $hash, 16, 16 ) );
	}

	/**
	 * Send license key via email.
	 *
	 * @param string $license_key License key.
	 * @param string $email        Recipient email.
	 * @param string $plan         Plan name.
	 * @return bool
	 */
	public function send_license_key_email( $license_key, $email, $plan ) {
		$subject = sprintf(
			/* translators: %s: plan name */
			__( 'Your Dataviz AI %s License Key', 'dataviz-ai-woocommerce' ),
			ucfirst( $plan )
		);
		$message = sprintf(
			/* translators: %1$s: license key, %2$s: plan name */
			__( 'Thank you for your purchase! Your license key is: %1$s\n\nPlan: %2$s\n\nYou can activate this license in your WordPress admin.', 'dataviz-ai-woocommerce' ),
			$license_key,
			ucfirst( $plan )
		);
		return wp_mail( $email, $subject, $message );
	}

	/**
	 * Get checkout URL.
	 *
	 * @param string $plan          Plan name.
	 * @param string $payment_method Payment method (e.g., 'stripe', 'paypal').
	 * @return string
	 */
	public function get_checkout_url( $plan, $payment_method = 'stripe' ) {
		return admin_url( 'admin.php?page=dataviz-ai-woocommerce&tab=checkout&plan=' . esc_attr( $plan ) . '&method=' . esc_attr( $payment_method ) );
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
				'name'     => 'Pro',
				'price'    => 15.00,
				'currency' => 'USD',
				'interval' => 'month',
			),
		);
		return isset( $pricing[ $plan ] ) ? $pricing[ $plan ] : $pricing['pro'];
	}

	/**
	 * Check if Stripe is configured.
	 *
	 * @return bool
	 */
	public function is_stripe_configured() {
		$secret_key = get_option( 'dataviz_ai_stripe_secret_key', '' );
		return ! empty( $secret_key );
	}

	/**
	 * Check if PayPal is configured.
	 *
	 * @return bool
	 */
	public function is_paypal_configured() {
		$client_id = get_option( 'dataviz_ai_paypal_client_id', '' );
		return ! empty( $client_id );
	}

	/**
	 * Get Stripe publishable key.
	 *
	 * @return string
	 */
	public function get_stripe_publishable_key() {
		return get_option( 'dataviz_ai_stripe_publishable_key', '' );
	}

	/**
	 * Get PayPal client ID.
	 *
	 * @return string
	 */
	public function get_paypal_client_id() {
		return get_option( 'dataviz_ai_paypal_client_id', '' );
	}
}

