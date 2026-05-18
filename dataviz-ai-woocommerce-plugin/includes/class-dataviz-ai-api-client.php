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
	 * Priority order:
	 * 1. Environment variable: DATAVIZ_AI_API_BASE_URL
	 * 2. Config constant: DATAVIZ_AI_API_BASE_URL
	 * 3. WordPress option (for backward compatibility)
	 *
	 * @return string
	 */
	public function get_api_url() {
		// Check environment variable first
		$env_url = getenv( 'DATAVIZ_AI_API_BASE_URL' );
		if ( false !== $env_url && ! empty( $env_url ) ) {
			return esc_url_raw( $env_url );
		}

		// Check config constant
		if ( defined( 'DATAVIZ_AI_API_BASE_URL' ) && ! empty( DATAVIZ_AI_API_BASE_URL ) ) {
			return esc_url_raw( DATAVIZ_AI_API_BASE_URL );
		}

		// Fall back to WordPress option (backward compatibility)
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
	 * Priority order:
	 * 1. Environment variable: OPENAI_API_KEY or DATAVIZ_AI_API_KEY
	 * 2. Config constant: DATAVIZ_AI_API_KEY
	 * 3. WordPress option (for backward compatibility)
	 *
	 * @return string
	 */
	public function get_api_key() {
		// Check environment variables first (OpenAI standard, then custom)
		$env_key = getenv( 'OPENAI_API_KEY' );
		if ( false === $env_key || empty( $env_key ) ) {
			$env_key = getenv( 'DATAVIZ_AI_API_KEY' );
		}
		if ( false !== $env_key && ! empty( $env_key ) ) {
			return sanitize_text_field( $env_key );
		}

		// Check config constant
		if ( defined( 'DATAVIZ_AI_API_KEY' ) && ! empty( DATAVIZ_AI_API_KEY ) ) {
			return sanitize_text_field( DATAVIZ_AI_API_KEY );
		}

		// Fall back to WordPress option (backward compatibility)
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
				'model'       => isset( $options['model'] ) ? sanitize_text_field( $options['model'] ) : 'gpt-4o',
				'temperature' => isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.6,
				'messages'    => $messages,
			),
			array_diff_key( $options, array_flip( array( 'model', 'temperature' ) ) )
		);

		// Fix empty properties arrays in tools to be objects for OpenAI API.
		$body_json = wp_json_encode( $request_body );
		if ( isset( $request_body['tools'] ) && is_array( $request_body['tools'] ) ) {
			// Replace "properties":[] with "properties":{}
			$body_json = preg_replace( '/"properties"\s*:\s*\[\s*\]/', '"properties":{}', $body_json );
		}

		$response = wp_remote_post(
			$this->default_openai_url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => $body_json,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body        = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		// Check for JSON decode errors
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_msg = 'Failed to parse OpenAI API response: ' . json_last_error_msg();
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[Dataviz AI] %s. Raw body: %s', $error_msg, wp_remote_retrieve_body( $response ) ) );
			}
			return new WP_Error(
				'dataviz_ai_json_error',
				$error_msg,
				array(
					'status' => $status_code,
					'json_error' => json_last_error_msg(),
				)
			);
		}

		if ( $status_code >= 400 || ! is_array( $body ) ) {
			$error_msg = 'The OpenAI API returned an error.';
			if ( is_array( $body ) && isset( $body['error'] ) ) {
				$error_data = $body['error'];
				if ( is_array( $error_data ) && isset( $error_data['message'] ) ) {
					$error_msg .= ' ' . $error_data['message'];
				} elseif ( is_string( $error_data ) ) {
					$error_msg .= ' ' . $error_data;
				}
			}
			return new WP_Error(
				'dataviz_ai_openai_error',
				$error_msg,
				array(
					'status' => $status_code,
					'body'   => $body,
				)
			);
		}

		// Validate response has expected structure
		if ( ! isset( $body['choices'] ) || ! is_array( $body['choices'] ) ) {
			$error_msg = 'Invalid response structure from OpenAI API: missing choices array';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[Dataviz AI] %s. Response: %s', $error_msg, wp_json_encode( $body ) ) );
			}
			return new WP_Error(
				'dataviz_ai_invalid_response',
				$error_msg,
				array( 'response' => $body )
			);
		}

		return $body;
	}

	/**
	 * Parse a user question into a strict intent JSON object.
	 *
	 * @param string $question User question.
	 * @return array|WP_Error Array with keys: intent (array), raw (string), provider, model.
	 */
	public function parse_intent( $question ) {
		$question = (string) $question;
		$template = Dataviz_AI_Prompt_Template::intent_parser();

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You output ONLY JSON.',
			),
			$template->build_message(
				'user',
				array(
					'question' => $question,
				)
			),
		);

		$options = array(
			'model'           => 'gpt-4o-mini',
			'temperature'     => 0,
			'response_format' => array( 'type' => 'json_object' ),
		);

		$response = $this->send_openai_chat( $messages, $options );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$content = $response['choices'][0]['message']['content'] ?? '';
		if ( ! is_string( $content ) || trim( $content ) === '' ) {
			return new WP_Error( 'dataviz_ai_invalid_intent', 'Intent parser returned empty content' );
		}

		$decoded = json_decode( $content, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'dataviz_ai_invalid_intent',
				'Intent parser returned non-JSON content',
				array( 'raw' => $content )
			);
		}

		return array(
			'intent'   => $decoded,
			'raw'      => $content,
			'provider' => 'openai',
			'model'    => $options['model'],
		);
	}

	/**
	 * Send a streaming chat completion request to OpenAI.
	 *
	 * @param array    $messages Chat messages in OpenAI format.
	 * @param callable $callback Callback function to handle each chunk.
	 * @param array    $options  Additional options (model, temperature, etc.).
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function send_openai_chat_stream( array $messages, callable $callback, array $options = array() ) {
		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'dataviz_ai_missing_api_key',
				__( 'Add an OpenAI-compatible API key to use the chat assistant.', 'dataviz-ai-woocommerce' )
			);
		}

		$request_body = array_merge(
			array(
				'model'       => isset( $options['model'] ) ? sanitize_text_field( $options['model'] ) : 'gpt-4o',
				'temperature' => isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.6,
				'messages'    => $messages,
				'stream'      => true, // Enable streaming.
			),
			array_diff_key( $options, array_flip( array( 'model', 'temperature', 'stream' ) ) )
		);

		// Fix empty properties arrays in tools to be objects for OpenAI API.
		$body_json = wp_json_encode( $request_body );
		if ( isset( $request_body['tools'] ) && is_array( $request_body['tools'] ) ) {
			$body_json = preg_replace( '/"properties"\s*:\s*\[\s*\]/', '"properties":{}', $body_json );
		}

		$stream_state = array(
			'buffer'       => '',
			'error_buffer' => '',
			'has_error'    => false,
		);

		$curl_hook = function ( $handle ) use ( &$stream_state, $body_json, $callback ) {
			if ( ! self::is_curl_handle( $handle ) ) {
				return;
			}

			// OpenAI SSE needs incremental reads; WordPress documents http_api_curl for transport tweaks.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Hooked via http_api_curl; not a direct curl_init() call.
			curl_setopt( $handle, CURLOPT_POSTFIELDS, $body_json );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
			curl_setopt(
				$handle,
				CURLOPT_WRITEFUNCTION,
				function ( $ch, $data ) use ( &$stream_state, $callback ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo -- Status checked per chunk during SSE.
					$chunk_status = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );

					if ( $chunk_status >= 400 ) {
						$stream_state['has_error']    = true;
						$stream_state['error_buffer'] .= $data;
						return strlen( $data );
					}

					self::consume_openai_sse_chunk( $stream_state, $data, $callback );
					return strlen( $data );
				}
			);
		};

		add_action( 'http_api_curl', $curl_hook, 10, 1 );

		$response = wp_remote_post(
			$this->default_openai_url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => $body_json,
				'timeout' => 300,
			)
		);

		remove_action( 'http_api_curl', $curl_hook, 10 );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'dataviz_ai_http_error',
				sprintf(
					/* translators: %s: HTTP error message */
					__( 'Request error: %1$s', 'dataviz-ai-woocommerce' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 || $stream_state['has_error'] ) {
			$error_buffer = $stream_state['error_buffer'];
			$has_error    = $stream_state['has_error'];
			// Try to parse error response
			$error_message = __( 'The OpenAI API returned an error.', 'dataviz-ai-woocommerce' );
			$error_details = array( 'status' => $status_code );
			
			if ( ! empty( $error_buffer ) ) {
				$error_data = json_decode( $error_buffer, true );
				if ( is_array( $error_data ) ) {
					if ( isset( $error_data['error'] ) ) {
						$error_obj = $error_data['error'];
						if ( is_array( $error_obj ) && isset( $error_obj['message'] ) ) {
							$error_message .= ' ' . $error_obj['message'];
						} elseif ( is_string( $error_obj ) ) {
							$error_message .= ' ' . $error_obj;
						}
						$error_details['error'] = $error_obj;
					} elseif ( isset( $error_data['message'] ) ) {
						$error_message .= ' ' . $error_data['message'];
						$error_details['error'] = $error_data;
					}
				} else {
					// If not JSON, try to extract message from raw response
					$error_details['raw_response'] = $error_buffer;
				}
			}
			
			// Log detailed error for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[Dataviz AI] OpenAI Streaming API Error (HTTP %d): %s', $status_code, wp_json_encode( $error_details, JSON_PRETTY_PRINT ) ) );
			}
			
			return new WP_Error(
				'dataviz_ai_openai_error',
				$error_message,
				$error_details
			);
		}

		return true;
	}

	/**
	 * Whether a value is a cURL handle (PHP 7 resource or PHP 8 CurlHandle).
	 *
	 * @param mixed $handle Value from http_api_curl.
	 * @return bool
	 */
	private static function is_curl_handle( $handle ) {
		if ( is_resource( $handle ) ) {
			return true;
		}

		return is_object( $handle ) && class_exists( 'CurlHandle', false ) && $handle instanceof CurlHandle;
	}

	/**
	 * Parse one SSE chunk from OpenAI and invoke the stream callback for content deltas.
	 *
	 * @param array    $state    Mutable keys: buffer, error_buffer, has_error.
	 * @param string   $data     Raw bytes from the HTTP stream.
	 * @param callable $callback Consumer for text deltas.
	 * @return void
	 */
	private static function consume_openai_sse_chunk( array &$state, $data, callable $callback ) {
		$state['buffer'] .= $data;
		$lines           = explode( "\n", $state['buffer'] );
		$state['buffer'] = array_pop( $lines );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) || substr( $line, 0, 6 ) !== 'data: ' ) {
				continue;
			}

			$data_str = substr( $line, 6 );

			if ( '[DONE]' === $data_str ) {
				return;
			}

			$chunk = json_decode( $data_str, true );

			if ( is_array( $chunk ) && isset( $chunk['error'] ) ) {
				$state['has_error']    = true;
				$state['error_buffer'] = wp_json_encode( $chunk['error'] );
				continue;
			}

			if ( ! is_array( $chunk ) || ! isset( $chunk['choices'][0]['delta']['content'] ) ) {
				continue;
			}

			$content = $chunk['choices'][0]['delta']['content'];
			if ( ! empty( $content ) ) {
				call_user_func( $callback, $content );
			}
		}
	}
}

