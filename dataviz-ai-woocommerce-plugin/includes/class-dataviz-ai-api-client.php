<?php
/**
 * Handles outbound requests to the AI backend.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API client for Dataviz AI backend.
 */
class Dataviz_AI_API_Client {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	private $option_key = 'dataviz_ai_wc_settings';

	/**
	 * Default OpenAI chat endpoint.
	 *
	 * @var string
	 */
	private $default_openai_url = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Retrieve stored plugin settings.
	 *
	 * @return array
	 */
	protected function get_settings() {
		$defaults = array(
			'api_url' => '',
			'api_key' => '',
		);

		$settings = get_option( $this->option_key, array() );

		if ( ! is_array( $settings ) ) {
			return $defaults;
		}

		return array_merge( $defaults, $settings );
	}

	/**
	 * Return configured API URL.
	 *
	 * @return string
	 */
	public function get_api_url() {
		$settings = $this->get_settings();

		return isset( $settings['api_url'] ) ? esc_url_raw( $settings['api_url'] ) : '';
	}

	/**
	 * Determine if a custom backend URL is configured.
	 *
	 * @return bool
	 */
	public function has_custom_backend() {
		return ! empty( $this->get_api_url() );
	}

	/**
	 * Return configured API key.
	 *
	 * @return string
	 */
	public function get_api_key() {
		$settings = $this->get_settings();

		return isset( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
	}

	/**
	 * Send a POST request to the configured backend.
	 *
	 * @param string $endpoint Endpoint path (e.g., /api/woocommerce/ask).
	 * @param array  $body     Payload to send.
	 *
	 * @return array|WP_Error
	 */
	public function post( $endpoint, array $body = array() ) {
		$base_url = untrailingslashit( $this->get_api_url() );
		$api_key  = $this->get_api_key();

		if ( empty( $base_url ) || empty( $api_key ) ) {
			return new WP_Error(
				'dataviz_ai_missing_config',
				__( 'Please configure the Dataviz AI API URL and key before making requests.', 'dataviz-ai-woocommerce' )
			);
		}

		$url  = $base_url . '/' . ltrim( $endpoint, '/' );
		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$data        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 400 ) {
			return new WP_Error(
				'dataviz_ai_api_error',
				sprintf(
					/* translators: %d status code from API. */
					__( 'Dataviz AI API responded with status %d.', 'dataviz-ai-woocommerce' ),
					(int) $status_code
				),
				$data
			);
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Send a simple chat completion request to OpenAI.
	 *
	 * @param array $messages Chat messages in OpenAI format.
	 * @param array $options  Additional options (model, temperature, etc.).
	 *
	 * @return array|WP_Error
	 */
	public function send_openai_chat( array $messages, array $options = array() ) {
		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'dataviz_ai_missing_api_key',
				__( 'Add an OpenAI-compatible API key to use the chat assistant.', 'dataviz-ai-woocommerce' )
			);
		}

		$request_body = array_merge(
			array(
				'model'       => isset( $options['model'] ) ? sanitize_text_field( $options['model'] ) : 'gpt-4o-mini',
				'temperature' => isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.6,
				'messages'    => $messages,
			),
			array_diff_key( $options, array_flip( array( 'model', 'temperature' ) ) )
		);

		$response = wp_remote_post(
			$this->default_openai_url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $request_body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body        = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 || ! is_array( $body ) ) {
			return new WP_Error(
				'dataviz_ai_openai_error',
				__( 'The OpenAI API returned an error.', 'dataviz-ai-woocommerce' ),
				array(
					'status' => $status_code,
					'body'   => $body,
				)
			);
		}

		return $body;
	}
}

