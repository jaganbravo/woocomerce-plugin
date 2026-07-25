<?php
/**
 * Server-Sent Events (SSE) streaming handler.
 *
 * Encapsulates all SSE I/O: headers, chunk sending, error/end markers,
 * and LLM stream-reading with optional greeting filtering.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Stream_Handler {

	/**
	 * API client for LLM streaming.
	 *
	 * @var Dataviz_AI_API_Client
	 */
	protected $api_client;

	/**
	 * Accumulated streaming response content.
	 *
	 * @var string
	 */
	protected $content = '';

	/**
	 * Constructor.
	 *
	 * @param Dataviz_AI_API_Client $api_client API client instance.
	 */
	public function __construct( Dataviz_AI_API_Client $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Get accumulated content from the last stream operation.
	 *
	 * @return string
	 */
	public function get_content() {
		return $this->content;
	}

	/**
	 * Reset accumulated content.
	 *
	 * @return void
	 */
	public function reset_content() {
		$this->content = '';
	}

	/**
	 * Prepare HTTP headers for SSE streaming and flush output buffers.
	 *
	 * @return void
	 */
	public function setup_headers() {
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );
	}

	/**
	 * Send a chunk in the stream.
	 *
	 * @param string $chunk    Text chunk.
	 * @param array  $metadata Optional metadata to include (e.g., tool_data).
	 * @return void
	 */
	public function send_chunk( $chunk, $metadata = array() ) {
		$data = array( 'chunk' => $chunk );
		if ( ! empty( $metadata ) ) {
			$data = array_merge( $data, $metadata );
		}
		echo "data: " . wp_json_encode( $data ) . "\n\n";
		if ( ob_get_level() ) {
			ob_flush();
		}
		flush();
	}

	/**
	 * Send an error in the stream and terminate.
	 *
	 * @param string $error Error message.
	 * @return void
	 */
	public function send_error( $error ) {
		echo "data: " . wp_json_encode( array( 'error' => $error ) ) . "\n\n";
		echo "data: [DONE]\n\n";
		if ( ob_get_level() ) {
			ob_flush();
		}
		flush();
		exit;
	}

	/**
	 * Send stream end marker.
	 *
	 * @param array|null $tool_data Optional tool data to include for frontend chart rendering.
	 * @return void
	 */
	public function send_end( $tool_data = null ) {
		if ( ! empty( $tool_data ) && is_array( $tool_data ) ) {
			echo "data: " . wp_json_encode( array( 'done' => true, 'tool_data' => $tool_data ) ) . "\n\n";
		} else {
			echo "data: [DONE]\n\n";
		}
		if ( ob_get_level() ) {
			ob_flush();
		}
		flush();
		exit;
	}

	/**
	 * Stream text word by word (fallback for non-streaming APIs).
	 *
	 * @param string $text Text to stream.
	 * @return void
	 */
	public function stream_text( $text ) {
		$words = explode( ' ', $text );
		foreach ( $words as $index => $word ) {
			$chunk = $word . ( $index < count( $words ) - 1 ? ' ' : '' );
			$this->send_chunk( $chunk );
			usleep( 30000 );
		}
		$this->send_end();
	}

	/**
	 * Stream the LLM response, optionally filtering out initial greetings for data questions.
	 *
	 * @param array  $messages         OpenAI messages array.
	 * @param bool   $filter_greetings Whether to filter initial greeting content.
	 * @param string $model            Model name.
	 * @return true|WP_Error
	 */
	public function stream_llm_response( array $messages, $filter_greetings = false, $model = 'gpt-4o-mini' ) {
		$this->content = '';
		$greeting_pattern = '/^hello[!.]?\s*(how can i assist you|how may i help)/i';

		$result = $this->api_client->send_openai_chat_stream(
			$messages,
			function( $chunk ) use ( $filter_greetings, $greeting_pattern ) {
				$this->content .= $chunk;

				if ( $filter_greetings ) {
					$trimmed = trim( $this->content );
					if ( preg_match( $greeting_pattern, $trimmed ) && strlen( $trimmed ) < 80 ) {
						return;
					}
				}

				$this->send_chunk( $chunk );
			},
			array( 'model' => $model )
		);

		return $result;
	}
}
