<?php
/**
 * License Manager for Dataviz AI WooCommerce Plugin
 *
 * Handles license key validation, activation, and premium feature gating.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin licenses and premium features.
 */
class Dataviz_AI_License_Manager {

	/**
	 * Option key for license data.
	 *
	 * @var string
	 */
	private $option_key = 'dataviz_ai_wc_license';

	/**
	 * License server URL (for remote validation).
	 *
	 * @var string
	 */
	private $license_server_url = 'https://your-license-server.com/api/validate'; // Update with your server

	/**
	 * Get license data.
	 *
	 * @return array
	 */
	private function get_license_data() {
		$defaults = array(
			'license_key'     => '',
			'status'          => 'inactive', // inactive, active, expired, invalid
			'plan'            => 'free', // free, pro, agency
			'activated_at'    => '',
			'expires_at'      => '',
			'questions_used'  => 0,
			'questions_limit' => 50, // Free tier limit
			'reset_date'      => date( 'Y-m-d' ), // Monthly reset
		);

		$license = get_option( $this->option_key, array() );

		if ( ! is_array( $license ) ) {
			return $defaults;
		}

		return array_merge( $defaults, $license );
	}

	/**
	 * Save license data.
	 *
	 * @param array $data License data.
	 * @return bool
	 */
	private function save_license_data( $data ) {
		return update_option( $this->option_key, $data );
	}

	/**
	 * Get current license status.
	 *
	 * @return string
	 */
	public function get_license_status() {
		$license = $this->get_license_data();
		return $license['status'];
	}

	/**
	 * Get current plan.
	 *
	 * @return string
	 */
	public function get_plan() {
		$license = $this->get_license_data();
		return $license['plan'];
	}

	/**
	 * Check if license is active.
	 *
	 * @return bool
	 */
	public function is_license_active() {
		$status = $this->get_license_status();
		return 'active' === $status;
	}

	/**
	 * Check if user has premium plan.
	 *
	 * @return bool
	 */
	public function is_premium() {
		$plan = $this->get_plan();
		return in_array( $plan, array( 'pro', 'agency' ), true );
	}

	/**
	 * Activate license key.
	 *
	 * @param string $license_key License key to activate.
	 * @return array Result with status and message.
	 */
	public function activate_license( $license_key ) {
		$license_key = sanitize_text_field( $license_key );

		if ( empty( $license_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'License key is required.', 'dataviz-ai-woocommerce' ),
			);
		}

		// Validate license key format (basic check)
		if ( ! $this->validate_license_format( $license_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid license key format.', 'dataviz-ai-woocommerce' ),
			);
		}

		// For MVP: Simple validation (can be enhanced with remote server)
		$validation_result = $this->validate_license_remote( $license_key );

		if ( ! $validation_result['success'] ) {
			return $validation_result;
		}

		// Save license data
		$license_data = array(
			'license_key'     => $license_key,
			'status'          => 'active',
			'plan'            => $validation_result['plan'],
			'activated_at'    => current_time( 'mysql' ),
			'expires_at'      => $validation_result['expires_at'],
			'questions_used'  => 0,
			'questions_limit' => $this->get_plan_limit( $validation_result['plan'] ),
			'reset_date'      => date( 'Y-m-d' ),
		);

		$this->save_license_data( $license_data );

		return array(
			'success' => true,
			'message' => __( 'License activated successfully!', 'dataviz-ai-woocommerce' ),
			'plan'    => $validation_result['plan'],
		);
	}

	/**
	 * Deactivate license.
	 *
	 * @return bool
	 */
	public function deactivate_license() {
		$license = $this->get_license_data();

		// Reset to free tier
		$license_data = array(
			'license_key'     => '',
			'status'          => 'inactive',
			'plan'            => 'free',
			'activated_at'    => '',
			'expires_at'      => '',
			'questions_used'  => 0,
			'questions_limit' => 50,
			'reset_date'      => date( 'Y-m-d' ),
		);

		return $this->save_license_data( $license_data );
	}

	/**
	 * Validate license key format.
	 *
	 * @param string $license_key License key.
	 * @return bool
	 */
	private function validate_license_format( $license_key ) {
		// Basic format validation: alphanumeric with dashes, 20-40 chars
		return preg_match( '/^[a-zA-Z0-9\-]{20,40}$/', $license_key ) === 1;
	}

	/**
	 * Validate license with remote server (or local for MVP).
	 *
	 * @param string $license_key License key.
	 * @return array
	 */
	private function validate_license_remote( $license_key ) {
		// MVP: Simple local validation
		// In production, this should call your license server

		// Check if it's a test/demo key
		if ( 'TEST-PRO-LICENSE-KEY-12345' === $license_key ) {
			return array(
				'success'   => true,
				'plan'      => 'pro',
				'expires_at' => date( 'Y-m-d', strtotime( '+1 year' ) ),
			);
		}

		if ( 'TEST-AGENCY-LICENSE-KEY-12345' === $license_key ) {
			return array(
				'success'   => true,
				'plan'      => 'agency',
				'expires_at' => date( 'Y-m-d', strtotime( '+1 year' ) ),
			);
		}

		// For MVP: Accept any valid format as "pro" (remove in production)
		// TODO: Replace with actual remote validation
		/*
		$response = wp_remote_post( $this->license_server_url, array(
			'body' => array(
				'license_key' => $license_key,
				'site_url'    => home_url(),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not connect to license server.', 'dataviz-ai-woocommerce' ),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		*/

		// For now, return invalid for unknown keys
		return array(
			'success' => false,
			'message' => __( 'Invalid license key. Please check your key and try again.', 'dataviz-ai-woocommerce' ),
		);
	}

	/**
	 * Get question limit for plan.
	 *
	 * @param string $plan Plan name.
	 * @return int
	 */
	private function get_plan_limit( $plan ) {
		$limits = array(
			'free'    => 50,
			'pro'     => -1, // Unlimited
			'agency'  => -1, // Unlimited
		);

		return isset( $limits[ $plan ] ) ? $limits[ $plan ] : 50;
	}

	/**
	 * Check if user can ask questions (within limit).
	 *
	 * @return bool
	 */
	public function can_ask_question() {
		$license = $this->get_license_data();

		// Premium plans have unlimited questions
		if ( $this->is_premium() ) {
			return true;
		}

		// Check monthly reset
		$reset_date = strtotime( $license['reset_date'] );
		$current_date = strtotime( date( 'Y-m-d' ) );
		$days_since_reset = floor( ( $current_date - $reset_date ) / DAY_IN_SECONDS );

		if ( $days_since_reset >= 30 ) {
			// Reset monthly limit
			$license['questions_used'] = 0;
			$license['reset_date'] = date( 'Y-m-d' );
			$this->save_license_data( $license );
		}

		// Check if within limit
		return $license['questions_used'] < $license['questions_limit'];
	}

	/**
	 * Increment question usage.
	 *
	 * @return void
	 */
	public function increment_usage() {
		if ( $this->is_premium() ) {
			return; // No limit for premium
		}

		$license = $this->get_license_data();
		$license['questions_used']++;
		$this->save_license_data( $license );
	}

	/**
	 * Get usage statistics.
	 *
	 * @return array
	 */
	public function get_usage_stats() {
		$license = $this->get_license_data();

		return array(
			'plan'            => $license['plan'],
			'questions_used'  => $license['questions_used'],
			'questions_limit' => $license['questions_limit'],
			'reset_date'      => $license['reset_date'],
			'is_unlimited'    => $this->is_premium(),
		);
	}

	/**
	 * Get purchase URL for plan.
	 *
	 * @param string $plan Plan name.
	 * @return string
	 */
	public function get_purchase_url( $plan = 'pro' ) {
		$urls = array(
			'pro'    => 'https://woocommerce.com/products/dataviz-ai-woocommerce', // Update with actual URL
			'agency' => 'https://woocommerce.com/products/dataviz-ai-woocommerce-agency', // Update with actual URL
		);

		return isset( $urls[ $plan ] ) ? $urls[ $plan ] : $urls['pro'];
	}

	/**
	 * Get upgrade message.
	 *
	 * @return string
	 */
	public function get_upgrade_message() {
		$license = $this->get_usage_stats();

		if ( $license['is_unlimited'] ) {
			return '';
		}

		$remaining = $license['questions_limit'] - $license['questions_used'];
		$message = sprintf(
			/* translators: %1$d: remaining questions, %2$d: total limit */
			__( 'You have %1$d of %2$d free questions remaining this month.', 'dataviz-ai-woocommerce' ),
			$remaining,
			$license['questions_limit']
		);

		return $message;
	}

	/**
	 * Get license data (public accessor).
	 *
	 * @return array
	 */
	public function get_license_data_public() {
		return $this->get_license_data();
	}
}

