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

		$question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : __( 'Provide a quick performance summary.', 'dataviz-ai-woocommerce' );

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

		wp_send_json_success( $response );
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
				'content' => 'You are a helpful WooCommerce data analyst. Analyze the user\'s question and decide which data operations to fetch. Use the available tools to request the data you need.',
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
			array(
				'type' => 'function',
				'function' => array(
					'name'        => 'get_recent_orders',
					'description' => 'Fetch recent orders from the WooCommerce store. Use this when the user asks about orders, sales, revenue, or recent transactions.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'limit'     => array(
								'type'        => 'integer',
								'description' => 'Number of orders to retrieve (1-100). Default is 20.',
								'minimum'     => 1,
								'maximum'     => 100,
							),
							'status'    => array(
								'type'        => 'string',
								'description' => 'Filter by order status (e.g., completed, processing, pending, cancelled). Optional.',
								'enum'        => array( 'completed', 'processing', 'pending', 'cancelled', 'refunded', 'failed', 'on-hold' ),
							),
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
						),
					),
				),
			),
			array(
				'type' => 'function',
				'function' => array(
					'name'        => 'get_top_products',
					'description' => 'Get top selling products. Use this when the user asks about best sellers, popular products, or product performance.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'limit' => array(
								'type'        => 'integer',
								'description' => 'Number of top products to retrieve (1-50). Default is 10.',
								'minimum'     => 1,
								'maximum'     => 50,
							),
						),
					),
				),
			),
			array(
				'type' => 'function',
				'function' => array(
					'name'        => 'get_customer_summary',
					'description' => 'Get overall customer metrics including total customer count and average lifetime value. Use this for general customer insights.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(),
					),
				),
			),
			array(
				'type' => 'function',
				'function' => array(
					'name'        => 'get_customers',
					'description' => 'Get list of customers with their details. Use this when the user asks about specific customers or customer lists.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'limit' => array(
								'type'        => 'integer',
								'description' => 'Number of customers to retrieve (1-100). Default is 10.',
								'minimum'     => 1,
								'maximum'     => 100,
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
			case 'get_recent_orders':
				$args = array(
					'limit' => isset( $arguments['limit'] ) ? (int) $arguments['limit'] : 20,
				);

				if ( isset( $arguments['status'] ) ) {
					$args['status'] = sanitize_text_field( $arguments['status'] );
				}

				if ( isset( $arguments['date_from'] ) && isset( $arguments['date_to'] ) ) {
					// Convert dates to timestamps (start of day for from, end of day for to).
					$from_timestamp = strtotime( $arguments['date_from'] . ' 00:00:00' );
					$to_timestamp   = strtotime( $arguments['date_to'] . ' 23:59:59' );
					
					if ( $from_timestamp && $to_timestamp ) {
						$args['date_created'] = $from_timestamp . '...' . $to_timestamp;
					}
				}

				$orders = $this->data_fetcher->get_recent_orders( $args );
				$formatted_orders = array_map( array( $this, 'format_order' ), $orders );
				
				// Add metadata if no orders found.
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
}

