<?php
/**
 * AJAX endpoints for Dataviz AI WooCommerce plugin.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests for analysis and chat.
 */
class Dataviz_AI_AJAX_Handler {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Data fetcher.
	 *
	 * @var Dataviz_AI_Data_Fetcher
	 */
	protected $data_fetcher;

	/**
	 * API client.
	 *
	 * @var Dataviz_AI_API_Client
	 */
	protected $api_client;

	/**
	 * Chat history manager.
	 *
	 * @var Dataviz_AI_Chat_History
	 */
	protected $chat_history;

	/**
	 * Current session ID for this request.
	 *
	 * @var string
	 */
	protected $session_id = '';

	/**
	 * Accumulated streaming response content.
	 *
	 * @var string
	 */
	protected $streaming_content = '';

	/**
	 * Constructor.
	 *
	 * @param string                  $plugin_name  Plugin slug.
	 * @param Dataviz_AI_Data_Fetcher $data_fetcher Data fetcher instance.
	 * @param Dataviz_AI_API_Client   $api_client   API client instance.
	 */
	public function __construct( $plugin_name, Dataviz_AI_Data_Fetcher $data_fetcher, Dataviz_AI_API_Client $api_client ) {
		$this->plugin_name  = $plugin_name;
		$this->data_fetcher = $data_fetcher;
		$this->api_client   = $api_client;
		$this->chat_history = new Dataviz_AI_Chat_History();
	}

	/**
	 * Handle analysis request triggered from admin dashboard.
	 *
	 * @return void
	 */
	public function handle_analysis_request() {
		check_ajax_referer( 'dataviz_ai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized request.', 'dataviz-ai-woocommerce' ) ), 403 );
		}
		
		// Debug: Log user info
		$current_user_id = get_current_user_id();
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( '[Dataviz AI] handle_analysis_request - User ID: %d', $current_user_id ) );
		}

		$question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : __( 'Provide a quick performance summary.', 'dataviz-ai-woocommerce' );
		$stream   = isset( $_POST['stream'] ) && filter_var( $_POST['stream'], FILTER_VALIDATE_BOOLEAN );
		$this->session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

		// Debug log
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf(
				'[Dataviz AI] handle_analysis_request - User: %d, Session from POST: %s, Question length: %d',
				get_current_user_id(),
				$this->session_id ?: 'empty',
				strlen( $question )
			) );
		}

		// If no session ID provided, get or create one from user meta (persists across logins)
		if ( empty( $this->session_id ) ) {
			$session_key = 'dataviz_ai_session_id';
			$this->session_id = get_user_meta( get_current_user_id(), $session_key, true );
			if ( empty( $this->session_id ) ) {
				$this->session_id = wp_generate_uuid4();
				update_user_meta( get_current_user_id(), $session_key, $this->session_id );
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[Dataviz AI] Generated/retrieved session ID: %s', $this->session_id ) );
			}
		}

		// Save user message to chat history.
		$saved_id = $this->chat_history->save_message( 'user', $question, $this->session_id );
		if ( $saved_id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[Dataviz AI] User message saved successfully - ID: %d', $saved_id ) );
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				global $wpdb;
				error_log( sprintf(
					'[Dataviz AI] Failed to save user message - Error: %s, User: %d, Session: %s',
					$wpdb->last_error ?: 'Unknown',
					get_current_user_id(),
					$this->session_id
				) );
			}
		}

		// If streaming is requested, use streaming handler.
		if ( $stream ) {
			$this->handle_streaming_analysis( $question );
			return;
		}

		// If custom backend is configured, use it; otherwise use OpenAI with function calling.
		if ( $this->api_client->has_custom_backend() ) {
			$orders    = $this->data_fetcher->get_recent_orders( array( 'limit' => 20 ) );
			$products  = $this->data_fetcher->get_top_products( 10 );
			$customers = $this->data_fetcher->get_customer_summary();

			$payload = array(
				'question'  => $question,
				'orders'    => array_map( array( $this, 'format_order' ), $orders ),
				'products'  => $products,
				'customers' => $customers,
			);

			$response = $this->api_client->post( 'api/woocommerce/ask', $payload );
		} else {
			// Use OpenAI with function calling to let LLM decide what data to fetch.
			$response = $this->handle_smart_analysis( $question );
		}

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => $response->get_error_message(),
					'data'    => $response->get_error_data(),
				),
				400
			);
		}

		// Save AI response to chat history.
		$ai_response = isset( $response['answer'] ) ? $response['answer'] : wp_json_encode( $response );
		$this->chat_history->save_message( 'ai', $ai_response, $this->session_id, array( 'provider' => $response['provider'] ?? 'unknown' ) );

		wp_send_json_success( $response );
	}

	/**
	 * Handle streaming analysis request.
	 *
	 * @param string $question User's question.
	 * @return void
	 */
	protected function handle_streaming_analysis( $question ) {
		// Disable output buffering for streaming.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Set headers for streaming.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' ); // Disable nginx buffering.

		// For custom backend, fall back to non-streaming.
		if ( $this->api_client->has_custom_backend() ) {
			$orders    = $this->data_fetcher->get_recent_orders( array( 'limit' => 20 ) );
			$products  = $this->data_fetcher->get_top_products( 10 );
			$customers = $this->data_fetcher->get_customer_summary();

			$payload = array(
				'question'  => $question,
				'orders'    => array_map( array( $this, 'format_order' ), $orders ),
				'products'  => $products,
				'customers' => $customers,
			);

			$response = $this->api_client->post( 'api/woocommerce/ask', $payload );
			if ( is_wp_error( $response ) ) {
				$this->send_stream_error( $response->get_error_message() );
				return;
			}

			$answer = isset( $response['answer'] ) ? $response['answer'] : __( 'Response received.', 'dataviz-ai-woocommerce' );
			// Simulate streaming for custom backend.
			$this->stream_text( $answer );
			// Save complete AI response to chat history.
			$saved_custom_id = $this->chat_history->save_message( 'ai', $answer, $this->session_id, array( 'provider' => 'custom_backend', 'streaming' => true ) );
			if ( ! $saved_custom_id && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Dataviz AI] Failed to save custom backend AI response to chat history' );
			}
			return;
		}

		// Use smart analysis with streaming.
		$messages = $this->build_smart_analysis_messages( $question );
		$tools    = $this->get_available_tools();

		// First, check if LLM wants to call tools.
		// If question clearly requires data, suggest tools more strongly.
		$options = array(
			'model' => 'gpt-4o-mini',
			'tools' => $tools,
		);
		
		// For data-related questions, prefer tool usage.
		if ( $this->question_requires_data( $question ) ) {
			$options['tool_choice'] = 'auto'; // Let LLM decide, but strongly hint
		}
		
		$first_response = $this->api_client->send_openai_chat(
			$messages,
			$options
		);

		if ( is_wp_error( $first_response ) ) {
			$this->send_stream_error( $first_response->get_error_message() );
			return;
		}

		$assistant_message = $first_response['choices'][0]['message'] ?? array();
		$tool_calls        = $assistant_message['tool_calls'] ?? array();

		// If LLM doesn't call tools but question is clearly about data, proactively fetch data.
		if ( empty( $tool_calls ) && $this->question_requires_data( $question ) ) {
			// Automatically fetch relevant data based on question keywords.
			$tool_calls = $this->auto_detect_tool_calls( $question );
		}

		// If LLM wants to call tools (or we auto-detected), execute them and then stream the final response.
		if ( ! empty( $tool_calls ) ) {
			// If we have LLM tool calls, add the assistant message. Otherwise, we're using auto-detected calls.
			if ( isset( $assistant_message['tool_calls'] ) ) {
				$messages[] = $assistant_message;
			}

			foreach ( $tool_calls as $tool_call ) {
				// Handle both OpenAI format and auto-detected format.
				if ( isset( $tool_call['function']['name'] ) ) {
					$function_name = $tool_call['function']['name'];
					$arguments_json = isset( $tool_call['function']['arguments'] ) ? $tool_call['function']['arguments'] : '{}';
					$arguments = is_string( $arguments_json ) ? json_decode( $arguments_json, true ) : $arguments_json;
					$tool_call_id = isset( $tool_call['id'] ) ? $tool_call['id'] : 'auto-' . uniqid();
				} else {
					// Skip invalid tool calls.
					continue;
				}

				if ( empty( $function_name ) ) {
					continue;
				}

				$tool_result = $this->execute_tool( $function_name, is_array( $arguments ) ? $arguments : array() );
				$messages[]  = array(
					'role'         => 'tool',
					'tool_call_id' => $tool_call_id,
					'content'      => wp_json_encode( $tool_result ),
				);
			}

			// Now get the final streaming response.
			$messages[] = array(
				'role'    => 'user',
				'content' => 'Based on the WooCommerce store data that was just fetched using the tools, please answer the user\'s question: ' . $question . "\n\nProvide a comprehensive and helpful answer using the actual data that was retrieved. If the data shows empty arrays or no results, inform the user that there are currently no records matching their query in the WooCommerce store database.",
			);
		} else {
			// No tools needed, add the assistant message and ask for answer.
			if ( isset( $assistant_message['content'] ) ) {
				$messages[] = $assistant_message;
			}
		}

		// Reset streaming content accumulator.
		$this->streaming_content = '';

		// Stream the final response.
		$stream_result = $this->api_client->send_openai_chat_stream(
			$messages,
			function( $chunk ) {
				$this->streaming_content .= $chunk;
				$this->send_stream_chunk( $chunk );
			},
			array(
				'model' => 'gpt-4o-mini',
			)
		);

		if ( is_wp_error( $stream_result ) ) {
			$this->send_stream_error( $stream_result->get_error_message() );
			return;
		}

		// Save complete AI response to chat history.
		if ( ! empty( $this->streaming_content ) ) {
			$saved_ai_id = $this->chat_history->save_message( 'ai', $this->streaming_content, $this->session_id, array( 'provider' => 'openai', 'streaming' => true ) );
			if ( ! $saved_ai_id && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Dataviz AI] Failed to save AI streaming response to chat history' );
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Dataviz AI] Warning: streaming_content is empty, cannot save AI response' );
			}
		}

		$this->send_stream_end();
	}

	/**
	 * Send a chunk in the stream.
	 *
	 * @param string $chunk Text chunk.
	 * @return void
	 */
	protected function send_stream_chunk( $chunk ) {
		echo "data: " . wp_json_encode( array( 'chunk' => $chunk ) ) . "\n\n";
		if ( ob_get_level() ) {
			ob_flush();
		}
		flush();
	}

	/**
	 * Send an error in the stream.
	 *
	 * @param string $error Error message.
	 * @return void
	 */
	protected function send_stream_error( $error ) {
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
	 * @return void
	 */
	protected function send_stream_end() {
		echo "data: [DONE]\n\n";
		if ( ob_get_level() ) {
			ob_flush();
		}
		flush();
		exit;
	}

	/**
	 * Stream text character by character (fallback for non-streaming APIs).
	 *
	 * @param string $text Text to stream.
	 * @return void
	 */
	protected function stream_text( $text ) {
		$words = explode( ' ', $text );
		foreach ( $words as $index => $word ) {
			$chunk = $word . ( $index < count( $words ) - 1 ? ' ' : '' );
			$this->send_stream_chunk( $chunk );
			usleep( 30000 ); // Small delay to simulate streaming (30ms per word).
		}
		$this->send_stream_end();
	}

	/**
	 * Check if question requires data from WooCommerce.
	 *
	 * @param string $question User's question.
	 * @return bool
	 */
	protected function question_requires_data( $question ) {
		$data_keywords = array(
			'order', 'product', 'customer', 'sale', 'revenue', 'transaction',
			'purchase', 'buy', 'item', 'inventory', 'stock', 'buyer',
			'client', 'purchased', 'sold', 'total', 'recent', 'list',
			'show me', 'display', 'what are', 'how many', 'tell me about',
		);
		
		$lower_question = strtolower( $question );
		foreach ( $data_keywords as $keyword ) {
			if ( strpos( $lower_question, $keyword ) !== false ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Auto-detect which tools to call based on question.
	 *
	 * @param string $question User's question.
	 * @return array Array of tool call structures.
	 */
	protected function auto_detect_tool_calls( $question ) {
		$tool_calls = array();
		$lower_question = strtolower( $question );
		
		// Check for orders/sales/revenue keywords.
		if ( preg_match( '/\b(order|orders|sale|sales|revenue|transaction|purchase|recent)\b/i', $question ) ) {
			$tool_calls[] = array(
				'function' => array(
					'name' => 'get_recent_orders',
					'arguments' => wp_json_encode( array( 'limit' => 20 ) ),
				),
				'id' => 'auto-orders-' . uniqid(),
			);
		}
		
		// Check for product keywords.
		if ( preg_match( '/\b(product|products|item|items|inventory|stock|top|best|popular)\b/i', $question ) ) {
			$tool_calls[] = array(
				'function' => array(
					'name' => 'get_top_products',
					'arguments' => wp_json_encode( array( 'limit' => 10 ) ),
				),
				'id' => 'auto-products-' . uniqid(),
			);
		}
		
		// Check for customer keywords.
		if ( preg_match( '/\b(customer|customers|buyer|buyers|client|clients)\b/i', $question ) ) {
			if ( preg_match( '/\b(list|all|show)\b/i', $question ) ) {
				$tool_calls[] = array(
					'function' => array(
						'name' => 'get_customers',
						'arguments' => wp_json_encode( array( 'limit' => 10 ) ),
					),
					'id' => 'auto-customers-' . uniqid(),
				);
			} else {
				$tool_calls[] = array(
					'function' => array(
						'name' => 'get_customer_summary',
						'arguments' => wp_json_encode( array() ),
					),
					'id' => 'auto-customer-summary-' . uniqid(),
				);
			}
		}
		
		// If no specific match but question is about data, default to getting orders.
		if ( empty( $tool_calls ) && $this->question_requires_data( $question ) ) {
			$tool_calls[] = array(
				'function' => array(
					'name' => 'get_recent_orders',
					'arguments' => wp_json_encode( array( 'limit' => 20 ) ),
				),
				'id' => 'auto-default-' . uniqid(),
			);
		}
		
		return $tool_calls;
	}

	/**
	 * Build messages for smart analysis.
	 *
	 * @param string $question User's question.
	 * @return array
	 */
	protected function build_smart_analysis_messages( $question ) {
		return array(
			array(
				'role'    => 'system',
				'content' => __( 'You are an AI assistant helping analyze WooCommerce store data. You have access to tools that can fetch real data from the WooCommerce store including orders, products, and customer information. IMPORTANT: When the user asks about store data (orders, products, customers, sales, revenue, etc.), you MUST use the available tools to fetch that data. Do not say you don\'t have access - use the tools provided to get the actual data from the store. Only provide answers after you have retrieved the relevant data using the tools.', 'dataviz-ai-woocommerce' ),
			),
			array(
				'role'    => 'user',
				'content' => $question,
			),
		);
	}

	/**
	 * Handle chat request from shortcode.
	 *
	 * @return void
	 */
	public function handle_chat_request() {
		check_ajax_referer( 'dataviz_ai_chat', 'nonce' );

		$question = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( empty( $question ) ) {
			wp_send_json_error( array( 'message' => __( 'Message cannot be empty.', 'dataviz-ai-woocommerce' ) ), 400 );
		}

		$orders = $this->data_fetcher->get_recent_orders(
			array(
				'limit' => 10,
			)
		);

		if ( $this->api_client->has_custom_backend() ) {
			$payload = array(
				'question' => $question,
				'context'  => array(
					'orders' => array_map( array( $this, 'format_order' ), $orders ),
				),
			);

			$response = $this->api_client->post( 'api/chat', $payload );

			if ( is_wp_error( $response ) ) {
				wp_send_json_error(
					array(
						'message' => $response->get_error_message(),
						'data'    => $response->get_error_data(),
					),
					400
				);
			}

			wp_send_json_success( $response );
		}

		$messages = $this->build_openai_messages( $question, $orders );
		$result   = $this->api_client->send_openai_chat(
			$messages,
			array(
				'model' => 'gpt-4o-mini',
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'data'    => $result->get_error_data(),
				),
				400
			);
		}

		$content = '';

		if ( isset( $result['choices'][0]['message']['content'] ) ) {
			$content = trim( (string) $result['choices'][0]['message']['content'] );
		}

		if ( empty( $content ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'The AI response was empty. Try again.', 'dataviz-ai-woocommerce' ),
					'data'    => $result,
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'message' => $content,
			)
		);
	}

	/**
	 * Helper to normalize order data for API calls.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 *
	 * @return array
	 */
	protected function format_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return array();
		}

		return array(
			'id'          => $order->get_id(),
			'total'       => (float) $order->get_total(),
			'currency'    => $order->get_currency(),
			'status'      => $order->get_status(),
			'date'        => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'c' ) : null,
			'items'       => $this->format_order_items( $order ),
			'customer_id' => $order->get_customer_id(),
		);
	}

	/**
	 * Normalize order line items.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 *
	 * @return array
	 */
	protected function format_order_items( $order ) {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			/* @var WC_Order_Item_Product $item */
			$product = $item->get_product();

			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => (float) $item->get_total(),
				'product'  => $product ? array(
					'id'    => $product->get_id(),
					'sku'   => $product->get_sku(),
					'price' => (float) $product->get_price(),
				) : null,
			);
		}

		return $items;
	}

	/**
	 * Handle smart analysis using OpenAI function calling to decide which operations to run.
	 *
	 * @param string $question User's question.
	 *
	 * @return array|WP_Error
	 */
	protected function handle_smart_analysis( $question ) {
		// Log user question.
		$this->log_llm_decision( 'User Question', array( 'question' => $question ) );

		// Step 1: Ask LLM what operations it needs.
		$tools = $this->get_available_tools();
		
		// Log available tools.
		$tool_names = array_map(
			function( $tool ) {
				return $tool['function']['name'] ?? 'unknown';
			},
			$tools
		);
		$this->log_llm_decision( 'Available Tools', array( 'tools' => $tool_names ) );
		
		// Fix empty properties arrays to be objects for OpenAI API.
		// Convert empty arrays to empty objects before encoding.
		$tools = $this->convert_empty_arrays_to_objects( $tools );
		
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are a helpful WooCommerce data analyst. You have direct access to the WooCommerce store database through tools. IMPORTANT: When the user asks about store data (orders, products, customers, sales, revenue, etc.), you MUST use the available tools to fetch that data. Never say you don\'t have access - use the tools provided to get real data from the store. Analyze the user\'s question and use the appropriate tools to fetch the required data.',
			),
			array(
				'role'    => 'user',
				'content' => $question,
			),
		);

		$response = $this->api_client->send_openai_chat(
			$messages,
			array(
				'tools'     => $tools,
				'tool_choice' => 'auto',
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_llm_decision( 'LLM Error', array( 'error' => $response->get_error_message() ) );
			return $response;
		}

		$message = $response['choices'][0]['message'];

		// Log LLM's initial response.
		if ( isset( $message['tool_calls'] ) && is_array( $message['tool_calls'] ) ) {
			$llm_decision = array();
			foreach ( $message['tool_calls'] as $tool_call ) {
				$llm_decision[] = array(
					'tool'      => $tool_call['function']['name'] ?? 'unknown',
					'arguments' => json_decode( $tool_call['function']['arguments'] ?? '{}', true ),
					'reasoning' => 'LLM analyzed the question and selected this tool to fetch relevant data',
				);
			}
			$this->log_llm_decision( 'LLM Tool Selection', $llm_decision );
		} elseif ( isset( $message['content'] ) ) {
			$this->log_llm_decision( 'LLM Direct Answer', array( 'no_tools_used' => true, 'reasoning' => 'LLM determined no tool calls were needed' ) );
		}

		// Step 2: Execute any requested tool calls.
		$tool_results = array();
		if ( isset( $message['tool_calls'] ) && is_array( $message['tool_calls'] ) ) {
			foreach ( $message['tool_calls'] as $tool_call ) {
				$function_name = $tool_call['function']['name'] ?? '';
				$arguments     = json_decode( $tool_call['function']['arguments'] ?? '{}', true );

				if ( ! is_array( $arguments ) ) {
					$arguments = array();
				}

				$this->log_llm_decision( 'Executing Tool', array( 'tool' => $function_name, 'arguments' => $arguments ) );

				$result = $this->execute_tool( $function_name, $arguments );

				// Log tool execution result summary.
				$result_summary = array(
					'tool'            => $function_name,
					'result_type'     => is_wp_error( $result ) ? 'error' : 'success',
					'result_count'    => is_array( $result ) ? count( $result ) : 0,
				);
				
				if ( is_wp_error( $result ) ) {
					$result_summary['error_message'] = $result->get_error_message();
				} elseif ( is_array( $result ) ) {
					$result_summary['result_keys'] = array_keys( $result );
					// If result has orders/products/customers, log count.
					if ( isset( $result['orders'] ) || ( is_array( $result ) && isset( $result[0] ) && isset( $result[0]['id'] ) ) ) {
						$result_summary['items_returned'] = is_array( $result ) ? count( $result ) : 0;
					}
				}
				
				$this->log_llm_decision( 'Tool Execution Result', $result_summary );

				$tool_results[] = array(
					'tool_call_id' => $tool_call['id'] ?? '',
					'role'         => 'tool',
					'name'         => $function_name,
					'content'      => wp_json_encode( $result ),
				);
			}
		}

		// Step 3: If tools were called, send results back to LLM for final answer.
		if ( ! empty( $tool_results ) ) {
			$messages[] = $message;
			$messages   = array_merge( $messages, $tool_results );

			$messages[] = array(
				'role'    => 'user',
				'content' => 'Based on the data you just fetched, please answer the original question: ' . $question . "\n\nIf the data shows empty arrays or no results, inform the user that there are currently no records matching their query in the WooCommerce store database.",
			);

			$final_response = $this->api_client->send_openai_chat( $messages );

			if ( is_wp_error( $final_response ) ) {
				return $final_response;
			}

			if ( isset( $final_response['choices'][0]['message']['content'] ) ) {
				$operations_used = array_map(
					function( $result ) {
						return $result['name'];
					},
					$tool_results
				);
				
				$this->log_llm_decision( 'Final Answer', array(
					'operations_used' => $operations_used,
					'answer_preview'  => wp_trim_words( $final_response['choices'][0]['message']['content'], 50 ),
				) );
				
				return array(
					'answer'   => $final_response['choices'][0]['message']['content'],
					'provider' => 'openai',
					'operations_used' => $operations_used,
				);
			}
		} elseif ( isset( $message['content'] ) ) {
			// No tools called, return direct answer.
			$this->log_llm_decision( 'Final Answer (No Tools)', array(
				'answer_preview' => wp_trim_words( $message['content'], 50 ),
			) );
			
			return array(
				'answer'   => $message['content'],
				'provider' => 'openai',
			);
		}

		return new WP_Error(
			'dataviz_ai_invalid_response',
			__( 'Unexpected response format from AI.', 'dataviz-ai-woocommerce' )
		);
	}

	/**
	 * Recursively convert empty arrays in 'properties' keys to empty objects for JSON encoding.
	 * This fixes OpenAI API requirement that properties must be an object, not an array.
	 *
	 * @param mixed $data Data to process.
	 *
	 * @return mixed
	 */
	protected function convert_empty_arrays_to_objects( $data ) {
		if ( is_array( $data ) ) {
			$result = array();
			foreach ( $data as $key => $value ) {
				// If key is 'properties' and value is an empty array, convert to empty object.
				if ( 'properties' === $key && is_array( $value ) && empty( $value ) ) {
					$result[ $key ] = new stdClass();
				} else {
					// Recursively process other values.
					$result[ $key ] = $this->convert_empty_arrays_to_objects( $value );
				}
			}
			return $result;
		}
		
		return $data;
	}

	/**
	 * Get available tools/functions for OpenAI function calling.
	 *
	 * @return array
	 */
	protected function get_available_tools() {
		return array(
			// Flexible tool that can handle many entity types
			array(
				'type' => 'function',
				'function' => array(
					'name'        => 'get_woocommerce_data',
					'description' => 'Get any WooCommerce data dynamically. Can fetch orders, products, customers, categories, tags, coupons, refunds, stock levels, etc. Specify what you need in the parameters. USE THIS TOOL when user asks about: product categories, product tags, coupons, low stock products, refunds, or any data type not covered by specialized tools below.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'entity_type' => array(
								'type'        => 'string',
								'description' => 'What type of data to fetch: orders, products, customers, categories, tags, coupons, refunds, stock',
								'enum'        => array( 'orders', 'products', 'customers', 'categories', 'tags', 'coupons', 'refunds', 'stock' ),
							),
							'query_type'  => array(
								'type'        => 'string',
								'description' => 'How to fetch data: list (individual items), statistics (aggregated totals/averages), sample (representative sample), by_period (time-series grouped by hour/day/week/month)',
								'enum'        => array( 'list', 'statistics', 'sample', 'by_period' ),
							),
							'filters'     => array(
								'type'        => 'object',
								'description' => 'Filters to apply. Can include: date_from, date_to, status, stock_threshold, limit, category_id, etc.',
								'properties'  => array(
									'date_from'      => array(
										'type'   => 'string',
										'format' => 'date',
									),
									'date_to'        => array(
										'type'   => 'string',
										'format' => 'date',
									),
									'status'         => array(
										'type' => 'string',
									),
									'stock_threshold' => array(
										'type' => 'integer',
									),
									'limit'          => array(
										'type' => 'integer',
									),
									'category_id'    => array(
										'type' => 'integer',
									),
									'period'         => array(
										'type' => 'string',
										'enum' => array( 'hour', 'day', 'week', 'month' ),
									),
									'sample_size'    => array(
										'type' => 'integer',
									),
								),
							),
						),
						'required'   => array( 'entity_type', 'query_type' ),
					),
				),
			),
			// Specialized tool for order statistics (optimized for large datasets)
			array(
				'type' => 'function',
				'function' => array(
					'name'        => 'get_order_statistics',
					'description' => 'Get aggregated order statistics (totals, averages, counts, status breakdown). USE THIS TOOL when user asks for totals like "total revenue", "how many orders", "average order value", "revenue by status". This is optimized for large datasets (millions of orders).',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'date_from' => array(
								'type'        => 'string',
								'description' => 'Start date in YYYY-MM-DD format. Optional.',
								'format'      => 'date',
							),
							'date_to'   => array(
								'type'        => 'string',
								'description' => 'End date in YYYY-MM-DD format. Optional.',
								'format'      => 'date',
							),
							'status'    => array(
								'type'        => 'string',
								'description' => 'Filter by order status. Optional.',
								'enum'        => array( 'completed', 'processing', 'pending', 'cancelled', 'refunded', 'failed', 'on-hold' ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Execute a tool/function call requested by the LLM.
	 *
	 * @param string $function_name Function name to execute.
	 * @param array  $arguments     Function arguments.
	 *
	 * @return array|WP_Error
	 */
	protected function execute_tool( $function_name, array $arguments ) {
		switch ( $function_name ) {
			case 'get_woocommerce_data':
				return $this->execute_flexible_query( $arguments );

			case 'get_order_statistics':
				return $this->data_fetcher->get_order_statistics( $arguments );

			// Legacy tools for backward compatibility
			case 'get_recent_orders':
				$args = array(
					'limit' => isset( $arguments['limit'] ) ? (int) $arguments['limit'] : 20,
				);

				if ( isset( $arguments['status'] ) ) {
					$args['status'] = sanitize_text_field( $arguments['status'] );
				}

				if ( isset( $arguments['date_from'] ) && isset( $arguments['date_to'] ) ) {
					$from_timestamp = strtotime( $arguments['date_from'] . ' 00:00:00' );
					$to_timestamp   = strtotime( $arguments['date_to'] . ' 23:59:59' );
					
					if ( $from_timestamp && $to_timestamp ) {
						$args['date_created'] = $from_timestamp . '...' . $to_timestamp;
					}
				}

				$orders = $this->data_fetcher->get_recent_orders( $args );
				$formatted_orders = array_map( array( $this, 'format_order' ), $orders );
				
				if ( empty( $formatted_orders ) ) {
					return array(
						'orders' => array(),
						'message' => 'No orders found matching the criteria.',
						'query_params' => $args,
					);
				}
				
				return $formatted_orders;

			case 'get_top_products':
				$limit = isset( $arguments['limit'] ) ? min( 50, max( 1, (int) $arguments['limit'] ) ) : 10;
				return $this->data_fetcher->get_top_products( $limit );

			case 'get_customer_summary':
				return $this->data_fetcher->get_customer_summary();

			case 'get_customers':
				$limit = isset( $arguments['limit'] ) ? min( 100, max( 1, (int) $arguments['limit'] ) ) : 10;
				return $this->data_fetcher->get_customers( $limit );

			default:
				return new WP_Error(
					'dataviz_ai_unknown_tool',
					sprintf( __( 'Unknown tool: %s', 'dataviz-ai-woocommerce' ), $function_name )
				);
		}
	}

	/**
	 * Execute flexible query based on entity type and query type.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array|WP_Error
	 */
	protected function execute_flexible_query( array $arguments ) {
		$entity_type = isset( $arguments['entity_type'] ) ? sanitize_text_field( $arguments['entity_type'] ) : 'orders';
		$query_type  = isset( $arguments['query_type'] ) ? sanitize_text_field( $arguments['query_type'] ) : 'list';
		$filters      = isset( $arguments['filters'] ) && is_array( $arguments['filters'] ) ? $arguments['filters'] : array();

		switch ( $entity_type ) {
			case 'orders':
				return $this->handle_orders_query( $query_type, $filters );

			case 'products':
				return $this->handle_products_query( $query_type, $filters );

			case 'customers':
				return $this->handle_customers_query( $query_type, $filters );

			case 'categories':
				return $this->handle_categories_query( $filters );

			case 'tags':
				return $this->handle_tags_query( $filters );

			case 'coupons':
				return $this->handle_coupons_query( $filters );

			case 'refunds':
				return $this->handle_refunds_query( $filters );

			case 'stock':
				return $this->handle_stock_query( $filters );

			default:
				return new WP_Error(
					'dataviz_ai_unknown_entity',
					sprintf( __( 'Unknown entity type: %s', 'dataviz-ai-woocommerce' ), $entity_type )
				);
		}
	}

	/**
	 * Handle orders queries.
	 *
	 * @param string $query_type Query type.
	 * @param array  $filters    Filters.
	 * @return array|WP_Error
	 */
	protected function handle_orders_query( $query_type, array $filters ) {
		switch ( $query_type ) {
			case 'statistics':
				return $this->data_fetcher->get_order_statistics( $filters );

			case 'by_period':
				$period = isset( $filters['period'] ) ? sanitize_text_field( $filters['period'] ) : 'day';
				return $this->data_fetcher->get_orders_by_period( $period, $filters );

			case 'sample':
				$sample_size = isset( $filters['sample_size'] ) ? (int) $filters['sample_size'] : 100;
				$filters['sample_size'] = min( 500, max( 50, $sample_size ) );
				$orders = $this->data_fetcher->get_sampled_orders( $filters );
				return array_map( array( $this, 'format_order' ), $orders );

			case 'list':
			default:
				$args = array(
					'limit' => isset( $filters['limit'] ) ? min( 100, max( 1, (int) $filters['limit'] ) ) : 20,
				);

				if ( isset( $filters['status'] ) ) {
					$args['status'] = sanitize_text_field( $filters['status'] );
				}

				if ( isset( $filters['date_from'] ) && isset( $filters['date_to'] ) ) {
					$from_timestamp = strtotime( $filters['date_from'] . ' 00:00:00' );
					$to_timestamp   = strtotime( $filters['date_to'] . ' 23:59:59' );
					
					if ( $from_timestamp && $to_timestamp ) {
						$args['date_created'] = $from_timestamp . '...' . $to_timestamp;
					}
				}

				$orders = $this->data_fetcher->get_recent_orders( $args );
				$formatted_orders = array_map( array( $this, 'format_order' ), $orders );
				
				if ( empty( $formatted_orders ) ) {
					return array(
						'orders' => array(),
						'message' => 'No orders found matching the criteria.',
					);
				}
				
				return $formatted_orders;
		}
	}

	/**
	 * Handle products queries.
	 *
	 * @param string $query_type Query type.
	 * @param array  $filters    Filters.
	 * @return array|WP_Error
	 */
	protected function handle_products_query( $query_type, array $filters ) {
		$limit = isset( $filters['limit'] ) ? min( 50, max( 1, (int) $filters['limit'] ) ) : 10;

		switch ( $query_type ) {
			case 'list':
			default:
				if ( isset( $filters['category_id'] ) ) {
					return $this->data_fetcher->get_products_by_category( (int) $filters['category_id'], $limit );
				} else {
					return $this->data_fetcher->get_top_products( $limit );
				}

			case 'statistics':
				// For now, return top products as statistics
				// Can be enhanced later with actual product statistics
				return $this->data_fetcher->get_top_products( $limit );
		}
	}

	/**
	 * Handle customers queries.
	 *
	 * @param string $query_type Query type.
	 * @param array  $filters    Filters.
	 * @return array|WP_Error
	 */
	protected function handle_customers_query( $query_type, array $filters ) {
		switch ( $query_type ) {
			case 'statistics':
				return $this->data_fetcher->get_customer_summary();

			case 'list':
			default:
				$limit = isset( $filters['limit'] ) ? min( 100, max( 1, (int) $filters['limit'] ) ) : 10;
				return $this->data_fetcher->get_customers( $limit );
		}
	}

	/**
	 * Handle categories queries.
	 *
	 * @param array $filters Filters.
	 * @return array|WP_Error
	 */
	protected function handle_categories_query( array $filters ) {
		return $this->data_fetcher->get_product_categories();
	}

	/**
	 * Handle tags queries.
	 *
	 * @param array $filters Filters.
	 * @return array|WP_Error
	 */
	protected function handle_tags_query( array $filters ) {
		return $this->data_fetcher->get_product_tags();
	}

	/**
	 * Handle coupons queries.
	 *
	 * @param array $filters Filters.
	 * @return array|WP_Error
	 */
	protected function handle_coupons_query( array $filters ) {
		return $this->data_fetcher->get_coupons( $filters );
	}

	/**
	 * Handle refunds queries.
	 *
	 * @param array $filters Filters.
	 * @return array|WP_Error
	 */
	protected function handle_refunds_query( array $filters ) {
		return $this->data_fetcher->get_refunds( $filters );
	}

	/**
	 * Handle stock queries.
	 *
	 * @param array $filters Filters.
	 * @return array|WP_Error
	 */
	protected function handle_stock_query( array $filters ) {
		$threshold = isset( $filters['stock_threshold'] ) ? (int) $filters['stock_threshold'] : 10;
		return $this->data_fetcher->get_low_stock_products( $threshold );
	}

	/**
	 * Prepare chat messages for OpenAI.
	 *
	 * @param string   $question User query.
	 * @param WC_Order $orders   Orders array.
	 *
	 * @return array
	 */
	protected function build_openai_messages( $question, $orders ) {
		$orders_summary = array();

		foreach ( $orders as $order ) {
			if ( ! is_a( $order, 'WC_Order' ) ) {
				continue;
			}

			$orders_summary[] = sprintf(
				'#%1$d — %2$s — %3$s — %4$s items',
				$order->get_id(),
				wc_price( $order->get_total() ),
				$order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d' ) : __( 'N/A', 'dataviz-ai-woocommerce' ),
				count( $order->get_items() )
			);
		}

		$context_block = $orders_summary ? implode( "\n", $orders_summary ) : __( 'No recent orders available.', 'dataviz-ai-woocommerce' );

		return array(
			array(
				'role'    => 'system',
				'content' => __( 'You are a helpful WooCommerce analytics assistant. Answer clearly and concisely based on the provided store data.', 'dataviz-ai-woocommerce' ),
			),
			array(
				'role'    => 'user',
				'content' => sprintf(
					"%s\n\nRecent orders snapshot:\n%s",
					$question,
					$context_block
				),
			),
		);
	}

	/**
	 * Log LLM decision-making process for debugging.
	 *
	 * @param string $step     Step name (e.g., 'User Question', 'LLM Tool Selection').
	 * @param array  $data     Data to log.
	 *
	 * @return void
	 */
	protected function log_llm_decision( $step, $data ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		$log_entry = sprintf(
			'[Dataviz AI LLM Decision] %s: %s',
			$step,
			wp_json_encode( $data, JSON_PRETTY_PRINT )
		);

		error_log( $log_entry );
	}

	/**
	 * Handle request to get chat history.
	 *
	 * @return void
	 */
	public function handle_get_history_request() {
		check_ajax_referer( 'dataviz_ai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized request.', 'dataviz-ai-woocommerce' ) ), 403 );
		}

		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
		$limit      = isset( $_GET['limit'] ) ? min( 200, max( 1, (int) $_GET['limit'] ) ) : 100;
		$days       = isset( $_GET['days'] ) ? min( 30, max( 1, (int) $_GET['days'] ) ) : 5;
		$all_sessions = isset( $_GET['all_sessions'] ) && filter_var( $_GET['all_sessions'], FILTER_VALIDATE_BOOLEAN );

		// If all_sessions is true or session_id is empty, get all user history
		if ( $all_sessions || empty( $session_id ) ) {
			$history = $this->chat_history->get_recent_history( $limit, $days );
		} else {
			// Get history for specific session
			$history = $this->chat_history->get_session_history( $session_id, $limit, $days );
		}

		// Debug logging (only if WP_DEBUG is enabled)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[Dataviz AI] History request - User: %d, Sessions: %s, Limit: %d, Days: %d, Found: %d messages',
				get_current_user_id(),
				$all_sessions ? 'all' : $session_id,
				$limit,
				$days,
				count( $history )
			) );
		}

		wp_send_json_success( array( 'history' => $history ) );
	}
}

