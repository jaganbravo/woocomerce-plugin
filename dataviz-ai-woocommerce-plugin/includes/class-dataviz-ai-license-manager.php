<?php
/**
 * License Manager for Dataviz AI WooCommerce plugin.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages license validation and usage limits.
 */
class Dataviz_AI_License_Manager {

	/**
	 * Check if user can ask a question (license limits).
	 *
	 * @return bool
	 */
	public function can_ask_question() {
		// For now, always allow questions (free tier)
		return true;
	}

	/**
	 * Get usage statistics.
	 *
	 * @return array
	 */
	public function get_usage_stats() {
		return array(
			'questions_asked'  => 0,
			'questions_limit'  => 100, // Free tier limit
			'is_premium'       => false,
		);
	}

	/**
	 * Get purchase URL for upgrade.
	 *
	 * @param string $plan Plan name (e.g., 'pro').
	 * @return string
	 */
	public function get_purchase_url( $plan = 'pro' ) {
		return admin_url( 'admin.php?page=dataviz-ai-woocommerce&tab=checkout&plan=' . esc_attr( $plan ) );
	}

	/**
	 * Increment usage counter.
	 *
	 * @return void
	 */
	public function increment_usage() {
		// Track usage if needed
		$current = get_option( 'dataviz_ai_questions_asked', 0 );
		update_option( 'dataviz_ai_questions_asked', $current + 1 );
	}

	/**
	 * Check if premium license is active.
	 *
	 * @return bool
	 */
	public function is_premium() {
		return false; // Default to free tier
	}

	/**
	 * Get upgrade message.
	 *
	 * @return string
	 */
	public function get_upgrade_message() {
		return __( 'Upgrade to Pro for unlimited questions and advanced features.', 'dataviz-ai-woocommerce' );
	}

	/**
	 * Activate a license key.
	 *
	 * @param string $license_key License key to activate.
	 * @return array
	 */
	public function activate_license( $license_key ) {
		// Stub implementation - always return success for now
		return array(
			'success' => true,
			'message' => __( 'License activated successfully.', 'dataviz-ai-woocommerce' ),
		);
	}
}

