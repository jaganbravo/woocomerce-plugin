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

		// Check if user is confirming a feature request submission.
		$is_feature_request_confirmation = $this->is_feature_request_confirmation( $question );
		if ( $is_feature_request_confirmation ) {
			// Get recent chat history to find the entity_type from previous error.
			$entity_type = $this->extract_entity_type_from_history();
			if ( $entity_type ) {
				// Automatically call submit_feature_request tool.
				$tool_calls = array(
					array(
						'function' => array(
							'name' => 'submit_feature_request',
							'arguments' => wp_json_encode( array( 'entity_type' => $entity_type ) ),
						),
						'id' => 'auto-submit-' . uniqid(),
					),
				);
				
				// Execute the tool immediately.
				foreach ( $tool_calls as $tool_call ) {
					$function_name = $tool_call['function']['name'];
					$arguments = json_decode( $tool_call['function']['arguments'], true );
					$tool_result = $this->execute_tool( $function_name, is_array( $arguments ) ? $arguments : array() );
					
					$messages[] = array(
						'role' => 'assistant',
						'content' => null,
						'tool_calls' => array(
							array(
								'id' => $tool_call['id'],
								'type' => 'function',
								'function' => array(
									'name' => $function_name,
									'arguments' => $tool_call['function']['arguments'],
								),
							),
						),
					);
					
					$messages[] = array(
						'role' => 'tool',
						'tool_call_id' => $tool_call['id'],
						'content' => wp_json_encode( $tool_result ),
					);
				}
				
				// Now get the final response.
				$final_prompt = 'The user confirmed they want to submit a feature request. A feature request has been submitted. Please confirm this to the user using the tool response message.';
				$messages[] = array(
					'role' => 'user',
					'content' => $final_prompt,
				);
				
				// Stream the final response.
				$this->streaming_content = '';
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
				
				// Save AI response to chat history.
				if ( ! empty( $this->streaming_content ) ) {
					$this->chat_history->save_message( 'ai', $this->streaming_content, $this->session_id, array( 'provider' => 'openai', 'streaming' => true ) );
				}
				
				$this->send_stream_done();
				return;
			} else {
				// Entity type not found - let LLM know to ask user.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( '[Dataviz AI] Feature request confirmation detected but entity_type could not be extracted from history. This may happen if the previous error response was not saved properly.' );
				}
				// Continue with normal flow - LLM will handle it.
			}
		}

		// Check if user is asking for multiple entities (not supported yet)
		$multiple_entities = Dataviz_AI_Intent_Classifier::detect_multiple_entities( $question );
		if ( ! empty( $multiple_entities ) && count( $multiple_entities ) > 1 ) {
			// User asked for multiple entities - suggest feature request
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[Dataviz AI] Multiple entities detected: %s for question: %s', implode( ', ', $multiple_entities ), $question ) );
			}
			
			// Create a fake tool result with error to trigger feature request flow
			$entity_list = implode( ', ', $multiple_entities );
			$tool_call_id = 'multi-entity-detected-' . uniqid();
			$fake_tool_result = array(
				'error'              => true,
				'error_type'         => 'unsupported_feature',
				'message'            => sprintf(
					/* translators: %s: list of entity types */
					__( 'Currently, I can only fetch one type of data at a time. You asked about: %s. Please ask about one entity type at a time, or submit a feature request for multi-entity queries.', 'dataviz-ai-woocommerce' ),
					$entity_list
				),
				'requested_entity'   => 'multi-entity-queries',
				'can_submit_request' => true,
				'submission_prompt'  => sprintf(
					/* translators: %s: list of entity types */
					__( 'Would you like to request support for queries about multiple entity types (like "%s") at once? Just say "yes" or "request this feature" and I\'ll submit a feature request to the administrators.', 'dataviz-ai-woocommerce' ),
					$entity_list
				),
				'suggestion'         => __( 'You can ask about one entity type at a time, such as "show me orders" or "show me products".', 'dataviz-ai-woocommerce' ),
			);
			
			// Build messages array with the error response
			$messages = $this->build_smart_analysis_messages( $question );
			
			// Add the error as a fake tool result and let LLM handle it
			$messages[] = array(
				'role'         => 'assistant',
				'content'      => null,
				'tool_calls'   => array(
					array(
						'id'       => $tool_call_id,
						'type'     => 'function',
						'function' => array(
							'name'      => 'get_woocommerce_data',
							'arguments' => wp_json_encode( array( 'entity_type' => 'multi-entity' ) ),
						),
					),
				),
			);
			
			$messages[] = array(
				'role'         => 'tool',
				'tool_call_id' => $tool_call_id,
				'content'      => wp_json_encode( $fake_tool_result ),
			);
			
			// Store entity type in transient for feature request
			$transient_key = 'dataviz_ai_pending_request_' . md5( $this->session_id );
			set_transient( $transient_key, 'multi-entity-queries', HOUR_IN_SECONDS );
			
			// Add final prompt and let LLM handle the error response
			$final_prompt = 'You are a WooCommerce data analyst. The user asked: "' . $question . '". ';
			$final_prompt .= 'I attempted to fetch the data but encountered an error. ';
			$final_prompt .= "Please inform the user about the limitation and ask if they'd like to submit a feature request using the submission_prompt from the tool error response.";
			$final_prompt .= "\n\nCRITICAL: Do NOT greet the user. Answer directly and professionally.";
			
			$messages[] = array(
				'role'    => 'user',
				'content' => $final_prompt,
			);
			
			// Stream the response
			$this->streaming_content = '';
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
			
			// Save AI response to chat history.
			if ( ! empty( $this->streaming_content ) ) {
				$this->chat_history->save_message( 'ai', $this->streaming_content, $this->session_id, array( 'provider' => 'openai', 'streaming' => true ) );
			}
			
			$this->send_stream_end();
			return;
		}

		// Use intent classification to determine which tools to call (no LLM needed for tool selection)
		$tool_calls = Dataviz_AI_Intent_Classifier::classify_intent_and_get_tools( $question );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			if ( ! empty( $tool_calls ) ) {
				$tool_names = array_map( function( $tc ) {
					return $tc['function']['name'] ?? 'unknown';
				}, $tool_calls );
				error_log( sprintf( '[Dataviz AI] Intent classification detected tools: %s for question: %s', implode( ', ', $tool_names ), $question ) );
			} else {
				error_log( sprintf( '[Dataviz AI] Intent classification: No tools detected for question: %s', $question ) );
			}
		}

		// If intent classification detected tools, execute them and then stream the final response.
		if ( ! empty( $tool_calls ) ) {
			// Don't add assistant message - we're using intent classification, not LLM tool selection

			// Store tool results for frontend (to avoid redundant AJAX calls).
			$tool_results_for_frontend = array();

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
				
				// Convert WP_Error to user-friendly format for LLM.
				if ( is_wp_error( $tool_result ) ) {
					$tool_result = array(
						'error'              => true,
						'error_type'         => 'execution_error',
						'message'            => $tool_result->get_error_message(),
						'error_code'         => $tool_result->get_error_code(),
					);
				}
				
				// If tool result is an error with can_submit_request, store entity_type in transient.
				if ( is_array( $tool_result ) && isset( $tool_result['error'] ) && $tool_result['error'] === true ) {
					if ( isset( $tool_result['can_submit_request'] ) && $tool_result['can_submit_request'] === true ) {
						if ( isset( $tool_result['requested_entity'] ) ) {
							// Store pending entity_type for this session (expires in 1 hour).
							$transient_key = 'dataviz_ai_pending_request_' . md5( $this->session_id );
							set_transient( $transient_key, $tool_result['requested_entity'], HOUR_IN_SECONDS );
						}
					}
				}
				
				// Store tool results for frontend if they contain chart-relevant data.
				if ( is_array( $tool_result ) && ! isset( $tool_result['error'] ) ) {
					// Check if this is inventory data.
					if ( $function_name === 'get_woocommerce_data' && isset( $arguments['entity_type'] ) ) {
						$entity_type = strtolower( $arguments['entity_type'] );
						if ( in_array( $entity_type, array( 'inventory', 'stock' ), true ) ) {
							$tool_results_for_frontend['inventory'] = $tool_result;
						}
					}
				}
				
				$messages[]  = array(
					'role'         => 'tool',
					'tool_call_id' => $tool_call_id,
					'content'      => wp_json_encode( $tool_result ),
				);
			}
			
			// Send tool results to frontend as metadata (before text response).
			if ( ! empty( $tool_results_for_frontend ) ) {
				$this->send_stream_chunk( '', array( 'tool_data' => $tool_results_for_frontend ) );
			}

			// Now get the final streaming response.
			$final_prompt = 'You are a WooCommerce data analyst. The user asked: "' . $question . '". ';
			$final_prompt .= 'I have just fetched the relevant data from the WooCommerce store using tools. ';
			$final_prompt .= 'Your task is to analyze this data and provide a clear, helpful answer to the user\'s question. ';
			$final_prompt .= "\n\nCRITICAL: Do NOT greet the user or say 'Hello' or 'How can I assist you'. ";
			$final_prompt .= "The user has already asked a specific question - answer it directly with the data provided. ";
			$final_prompt .= "Start your response by directly addressing their question. ";
			$final_prompt .= "\n\nIMPORTANT: If any tool returned an error (check for 'error': true in the tool responses), politely inform the user that the requested feature is not yet available. Use the error message and suggestions from the tool response to guide your answer. ";
			$final_prompt .= "CRITICAL: If the user asked for a chart, graph, pie chart, bar chart, or visualization, DO NOT say that charts are not supported or not yet available. Charts are automatically rendered by the frontend - you just need to provide the data. Simply present the data you fetched in a clear format without mentioning chart limitations. ";
			$final_prompt .= "If the error response includes 'can_submit_request': true and 'submission_prompt', ask the user if they would like to submit a feature request using the exact prompt provided. ";
			$final_prompt .= "CRITICAL: If in a follow-up message the user says 'yes', 'request this feature', 'submit request', or any affirmative response, you MUST IMMEDIATELY call submit_feature_request tool. Extract the entity_type from the 'requested_entity' field in the most recent tool error response. Do NOT ask for clarification - just call the tool with the entity_type from the error response. ";
			$final_prompt .= "If the data shows empty arrays or no results, inform the user that there are currently no records matching their query in the WooCommerce store database. ";
			$final_prompt .= "If a feature request was successfully submitted (check for 'success': true in tool responses), confirm this to the user and let them know the administrators have been notified. ";
			$final_prompt .= "Otherwise, provide a comprehensive and helpful answer using the actual data that was retrieved. ";
			$final_prompt .= "\n\nRemember: Answer the question directly. Do not greet the user.";
			
			$messages[] = array(
				'role'    => 'user',
				'content' => $final_prompt,
			);
		} else {
			// No tools detected - handle non-data questions or fallback cases
			if ( Dataviz_AI_Intent_Classifier::question_requires_data( $question ) ) {
				// This shouldn't happen because classify_intent_and_get_tools has a fallback for data questions
				// But if it does, log it and return an error
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] ERROR: No tool calls detected for data question: %s', $question ) );
				}
				$this->send_stream_error( __( 'Unable to process data query. Please try rephrasing your question.', 'dataviz-ai-woocommerce' ) );
				return;
			} else {
				// Non-data question (e.g., greeting, general chat) - use LLM to respond directly
				$messages = $this->build_smart_analysis_messages( $question );
				$this->streaming_content = '';
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
				
				// Save AI response to chat history.
				if ( ! empty( $this->streaming_content ) ) {
					$this->chat_history->save_message( 'ai', $this->streaming_content, $this->session_id, array( 'provider' => 'openai', 'streaming' => true ) );
				}
				
				$this->send_stream_end();
				return;
			}
		}

		// Reset streaming content accumulator.
		$this->streaming_content = '';
		
		// For data questions, filter out greetings from the stream
		$is_data_question = Dataviz_AI_Intent_Classifier::question_requires_data( $question );
		$greeting_pattern = '/^hello[!.]?\s*(how can i assist you|how may i help)/i';

		// Stream the final response.
		$stream_result = $this->api_client->send_openai_chat_stream(
			$messages,
			function( $chunk ) use ( $is_data_question, $greeting_pattern ) {
				$this->streaming_content .= $chunk;
				
				// For data questions, check if accumulated content is just a greeting
				if ( $is_data_question ) {
					$trimmed_content = trim( $this->streaming_content );
					// If content matches greeting pattern and is short, don't send yet
					if ( preg_match( $greeting_pattern, $trimmed_content ) && strlen( $trimmed_content ) < 80 ) {
						// Don't send this chunk - it's just a greeting, wait for actual content
						return;
					}
				}
				
				// Send the chunk normally
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
	 * @param array  $metadata Optional metadata to include (e.g., tool_data).
	 * @return void
	 */
	protected function send_stream_chunk( $chunk, $metadata = array() ) {
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
	 * Auto-detect which tools to call based on question.
	 * DEPRECATED: Use classify_intent_and_get_tools() instead.
	 *
	 * @param string $question User's question.
	 * @return array Array of tool call structures.
	 */
	protected function auto_detect_tool_calls( $question ) {
		$tool_calls = array();
		$lower_question = strtolower( $question );
		
		// Check for statistics/aggregated queries (revenue, total, count, average, etc.)
		if ( preg_match( '/\b(total|revenue|count|average|sum|statistics|stats|how many)\b/i', $question ) && 
		     preg_match( '/\b(order|orders|sale|sales)\b/i', $question ) ) {
			// Use order statistics for aggregated queries
			$tool_calls[] = array(
				'function' => array(
					'name' => 'get_order_statistics',
					'arguments' => wp_json_encode( array() ),
				),
				'id' => 'auto-order-stats-' . uniqid(),
			);
		}
		// Check for orders/sales/revenue keywords (list queries)
		elseif ( preg_match( '/\b(order|orders|sale|sales|revenue|transaction|purchase|recent)\b/i', $question ) ) {
			// For questions about orders, use flexible tool with appropriate query type
			if ( preg_match( '/\b(list|show|display|all)\b/i', $question ) ) {
				$tool_calls[] = array(
					'function' => array(
						'name' => 'get_woocommerce_data',
						'arguments' => wp_json_encode( array(
							'entity_type' => 'orders',
							'query_type' => 'list',
							'filters' => array( 'limit' => 20 ),
						) ),
					),
					'id' => 'auto-orders-list-' . uniqid(),
				);
			} else {
				// Default to order statistics for general order queries
				$tool_calls[] = array(
					'function' => array(
						'name' => 'get_order_statistics',
						'arguments' => wp_json_encode( array() ),
					),
					'id' => 'auto-order-stats-' . uniqid(),
				);
			}
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
		if ( empty( $tool_calls ) && Dataviz_AI_Intent_Classifier::question_requires_data( $question ) ) {
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
				'content' => __( 'You are a WooCommerce data analyst AI assistant. Your role is to analyze store data and answer user questions directly and concisely. CRITICAL: When a user asks a question, do NOT greet them or say "Hello" or "How can I assist you". The user has already asked a specific question - answer it directly. Start your response by addressing their question immediately. You have access to tools that can fetch real data from the WooCommerce store including orders, products, and customer information. CRITICAL: When the user asks about ANY store data (orders, products, customers, sales, revenue, commission, sales commission, reviews, shipping, taxes, inventory, stock, or ANY other data type), you MUST use the available tools to fetch that data. Even if you think the data type might not be supported, still call the get_woocommerce_data tool with the exact entity_type the user mentioned (e.g., if they say "commission" or "sales commission", use entity_type: "commission"). Do not say you don\'t have access - use the tools provided to get the actual data from the store. IMPORTANT: If the user asks for a chart, graph, pie chart, bar chart, or visualization, you should still fetch the data using the tools. The frontend will automatically render charts based on the data and question - you do NOT need to generate charts yourself. Just provide the data in a clear format. Do NOT say "I cannot generate charts" - charts are handled automatically by the system. Only provide answers after you have retrieved the relevant data using the tools. If a tool returns an error (check for "error": true in the response), politely inform the user that the requested feature is not yet available and suggest available alternatives based on the error message provided. If the error response includes "can_submit_request": true and "submission_prompt", ask the user if they would like to submit a feature request using the exact prompt provided. CRITICAL: If the user says "yes", "request this feature", "submit request", "yes please", "sure", "ok", or ANY affirmative response, you MUST IMMEDIATELY call the submit_feature_request tool. Extract the entity_type from the "requested_entity" field in the most recent error response that had "can_submit_request": true. Do NOT ask the user what feature they want - use the entity_type from the error response. Do NOT ask for clarification - just call the tool immediately.', 'dataviz-ai-woocommerce' ),
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
				'content' => 'You are a helpful WooCommerce data analyst. You have direct access to the WooCommerce store database through tools. IMPORTANT: When the user asks about store data (orders, products, customers, sales, revenue, inventory, stock, etc.), you MUST use the available tools to fetch that data. Never say you don\'t have access - use the tools provided to get real data from the store. CRITICAL: If the user asks for a chart, graph, pie chart, bar chart, or visualization, you should still fetch the data using the tools. The frontend will automatically render charts based on the data and question - you do NOT need to generate charts yourself. Just provide the data in a clear format. Do NOT say "I cannot generate charts" or "charts are not yet supported" - charts are handled automatically by the system. Analyze the user\'s question and use the appropriate tools to fetch the required data.',
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

				// Convert WP_Error to user-friendly format for LLM.
				if ( is_wp_error( $result ) ) {
					$result = array(
						'error'              => true,
						'error_type'         => 'execution_error',
						'message'            => $result->get_error_message(),
						'error_code'         => $result->get_error_code(),
					);
				}

				// Log tool execution result summary.
				$result_summary = array(
					'tool'            => $function_name,
					'result_type'     => ( is_array( $result ) && isset( $result['error'] ) && $result['error'] ) ? 'error' : ( is_wp_error( $result ) ? 'error' : 'success' ),
					'result_count'    => is_array( $result ) ? count( $result ) : 0,
				);
				
				if ( is_array( $result ) && isset( $result['error'] ) && $result['error'] ) {
					$result_summary['error_message'] = $result['message'] ?? 'Unknown error';
					$result_summary['error_type'] = $result['error_type'] ?? 'unknown';
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

			$final_prompt = 'Based on the data you just fetched, please answer the original question: ' . $question . "\n\n";
			$final_prompt .= "IMPORTANT: If any tool returned an error (check for 'error': true in the tool responses), politely inform the user that the requested feature is not yet available. Use the error message and suggestions from the tool response to guide your answer. ";
			$final_prompt .= "CRITICAL: If the user asked for a chart, graph, pie chart, bar chart, or visualization, DO NOT say that charts are not supported. Charts are automatically rendered by the frontend - you just need to provide the data. Simply present the data you fetched in a clear format. ";
			$final_prompt .= "If the data shows empty arrays or no results, inform the user that there are currently no records matching their query in the WooCommerce store database.";

			$messages[] = array(
				'role'    => 'user',
				'content' => $final_prompt,
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
					'description' => 'Get any WooCommerce data dynamically. Can fetch orders, products, customers, categories, tags, coupons, refunds, stock levels, inventory, etc. CRITICAL: If the user asks about ANY WooCommerce data (including "commission", "sales commission", "reviews", "shipping", "taxes", "inventory", "stock", or any other data type), you MUST call this tool with the exact entity_type the user mentioned. Even if you think it might not be supported, still call the tool - the system will detect unsupported types and offer to submit a feature request. IMPORTANT: If the user asks for a chart or visualization, still call this tool to fetch the data - charts are automatically rendered by the frontend. USE THIS TOOL when user asks about: product categories, product tags, coupons, inventory, stock levels, low stock products, refunds, commission, or any data type not covered by specialized tools below.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'entity_type' => array(
								'type'        => 'string',
								'description' => 'What type of data to fetch. Supported types: orders, products, customers, categories, tags, coupons, refunds, stock, inventory. IMPORTANT: Use the EXACT term the user mentioned. If user says "inventory", use entity_type: "inventory" (NOT "stock"). If user says "stock", use entity_type: "stock". If the user asks about an unsupported type (e.g., "commission", "reviews", "shipping"), use the exact term the user mentioned - the system will detect it as unsupported and offer to submit a feature request.',
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
			// Feature request submission tool
			array(
				'type' => 'function',
				'function' => array(
					'name'        => 'submit_feature_request',
					'description' => 'Submit a feature request when user confirms they want to request an unsupported feature. USE THIS TOOL IMMEDIATELY when user says "yes", "request this feature", "submit request", "yes please", "sure", "ok", or any affirmative response after being asked if they want to submit a feature request. IMPORTANT: Extract the entity_type from the most recent tool error response that had "can_submit_request": true. Look for "requested_entity" field in the error response. Do NOT ask the user for clarification - use the entity_type from the error response.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'entity_type' => array(
								'type'        => 'string',
								'description' => 'The entity type that was requested. Extract this from the most recent tool error response that had "can_submit_request": true. Look for the "requested_entity" field in that error response. Examples: "reviews", "shipping", "taxes", etc.',
							),
							'description' => array(
								'type'        => 'string',
								'description' => 'Optional description or context about why this feature is needed.',
							),
						),
						'required'   => array( 'entity_type' ),
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

			case 'submit_feature_request':
				return $this->handle_feature_request_submission( $arguments );

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
				// Log the unknown tool request for debugging.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] Unknown tool requested: %s', $function_name ) );
				}

				// Return user-friendly error message.
				return array(
					'error'              => true,
					'error_type'         => 'unknown_tool',
					'message'            => sprintf(
						/* translators: %s: tool name */
						__( 'The tool "%s" is not available. Please use one of the available tools: get_woocommerce_data or get_order_statistics.', 'dataviz-ai-woocommerce' ),
						esc_html( $function_name )
					),
					'requested_tool'     => $function_name,
					'available_tools'    => array( 'get_woocommerce_data', 'get_order_statistics' ),
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
		
		// Store original entity_type for later use
		$original_entity_type = $entity_type;

		// List of supported entity types for error messages.
		$supported_entities = array(
			'orders'     => __( 'orders', 'dataviz-ai-woocommerce' ),
			'products'   => __( 'products', 'dataviz-ai-woocommerce' ),
			'customers'  => __( 'customers', 'dataviz-ai-woocommerce' ),
			'categories' => __( 'categories', 'dataviz-ai-woocommerce' ),
			'tags'       => __( 'tags', 'dataviz-ai-woocommerce' ),
			'coupons'    => __( 'coupons', 'dataviz-ai-woocommerce' ),
			'refunds'    => __( 'refunds', 'dataviz-ai-woocommerce' ),
			'stock'      => __( 'stock levels / inventory', 'dataviz-ai-woocommerce' ),
		);

		// Normalize entity_type (handle synonyms) but keep original for context
		// Note: "inventory" is NOT normalized to "stock" - they are different
		$entity_type_normalized = Dataviz_AI_Intent_Classifier::normalize_entity_type( $entity_type );

		switch ( $entity_type_normalized ) {
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
				return $this->handle_stock_query( $filters, $original_entity_type );
			
			case 'inventory':
				// "inventory" is treated as "stock" but shows all inventory
				return $this->handle_stock_query( $filters, 'inventory' );

			default:
				// Log the unsupported request for future feature development.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] Unsupported entity type requested: %s', $entity_type ) );
				}

				// Return user-friendly error message that LLM can understand and relay.
				return array(
					'error'              => true,
					'error_type'         => 'unsupported_entity',
					'message'            => sprintf(
						/* translators: %1$s: requested entity type, %2$s: list of supported types */
						__( 'The "%1$s" data type is not currently supported. Available data types are: %2$s. Please try asking about one of these supported types instead.', 'dataviz-ai-woocommerce' ),
						esc_html( $entity_type ),
						implode( ', ', $supported_entities )
					),
					'requested_entity'   => $entity_type,
					'available_entities' => array_keys( $supported_entities ),
					'suggestion'         => sprintf(
						/* translators: %s: list of supported entity types */
						__( 'You can ask about: %s', 'dataviz-ai-woocommerce' ),
						implode( ', ', $supported_entities )
					),
					'can_submit_request' => true,
					'submission_prompt'  => sprintf(
						/* translators: %s: requested entity type */
						__( 'Would you like to request this feature? Just say "yes" or "request this feature" and I\'ll submit a feature request for "%s" to the administrators.', 'dataviz-ai-woocommerce' ),
						esc_html( $entity_type )
					),
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
	 * @param array  $filters     Filters.
	 * @param string $entity_type Original entity type (to detect if user asked for "inventory" vs "stock").
	 * @return array|WP_Error
	 */
	protected function handle_stock_query( array $filters, $entity_type = 'stock' ) {
		// If user asked for "inventory" or "current inventory", show all inventory
		// If user asked for "stock" or "low stock", show only low stock
		$show_all = false;
		
		$entity_lower = strtolower( $entity_type );
		
		// Check if user's question suggests "all inventory" vs "low stock"
		if ( strpos( $entity_lower, 'inventory' ) !== false ) {
			// User asked for inventory - show all products with stock levels
			$show_all = true;
		} elseif ( strpos( $entity_lower, 'low' ) !== false || strpos( $entity_lower, 'stock' ) !== false ) {
			// User asked for "low stock" - show only low stock
			$show_all = false;
		} else {
			// Default: if query_type is "list", show all; otherwise show low stock
			$query_type = isset( $filters['query_type'] ) ? $filters['query_type'] : 'list';
			$show_all = ( $query_type === 'list' && strpos( $entity_lower, 'inventory' ) !== false );
		}
		
		if ( $show_all ) {
			// Return all products with inventory/stock levels
			return $this->data_fetcher->get_all_inventory_products( $filters );
		} else {
			// Return only low stock products (default behavior)
			$threshold = isset( $filters['stock_threshold'] ) ? (int) $filters['stock_threshold'] : 10;
			return $this->data_fetcher->get_low_stock_products( $threshold );
		}
	}

	/**
	 * Handle feature request submission.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	protected function handle_feature_request_submission( array $arguments ) {
		require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-feature-requests.php';
		
		$entity_type = isset( $arguments['entity_type'] ) ? sanitize_text_field( $arguments['entity_type'] ) : '';
		$description = isset( $arguments['description'] ) ? sanitize_textarea_field( $arguments['description'] ) : '';
		$user_id     = get_current_user_id();

		// Log the submission attempt.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf(
				'[Dataviz AI] Feature request submission attempt - Entity: %s, User: %d, Description: %s',
				$entity_type ?: 'empty',
				$user_id,
				! empty( $description ) ? 'provided' : 'none'
			) );
		}

		if ( empty( $entity_type ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( '[Dataviz AI] Feature request submission failed: entity_type is empty' );
			}
			return array(
				'error'   => true,
				'message' => __( 'Entity type is required to submit a feature request.', 'dataviz-ai-woocommerce' ),
			);
		}

		$feature_requests = new Dataviz_AI_Feature_Requests();
		
		// Ensure table exists (in case plugin wasn't reactivated).
		global $wpdb;
		$table_name = $wpdb->prefix . 'dataviz_ai_feature_requests';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
		
		if ( ! $table_exists ) {
			// Try to create the table.
			$feature_requests->create_table();
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( '[Dataviz AI] Feature requests table did not exist, attempting to create it.' );
			}
		}
		
		$request_id = $feature_requests->submit_request( $entity_type, $user_id, $description );

		if ( $request_id ) {
			// Send email to admins.
			$email_sent = $this->send_feature_request_email( $request_id, $entity_type, $user_id, $description );
			
			// Log if email failed (but don't fail the request submission).
			if ( ! $email_sent && defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[Dataviz AI] Feature request #%d submitted but email notification failed.', $request_id ) );
			}

			return array(
				'success'  => true,
				'message'  => sprintf(
					/* translators: %1$s: entity type, %2$d: request ID */
					__( 'Feature request for "%1$s" has been submitted successfully! Request ID: #%2$d. The administrators have been notified.', 'dataviz-ai-woocommerce' ),
					esc_html( $entity_type ),
					$request_id
				),
				'request_id' => $request_id,
			);
		}

		// Log the failure with more details.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			global $wpdb;
			error_log( sprintf(
				'[Dataviz AI] Feature request submission failed - Entity: %s, User: %d, DB Error: %s',
				$entity_type,
				$user_id,
				$wpdb->last_error ?: 'Unknown error'
			) );
		}

		return array(
			'error'   => true,
			'message' => __( 'Failed to submit feature request. Please try again later.', 'dataviz-ai-woocommerce' ),
		);
	}

	/**
	 * Send email notification to admins about new feature request.
	 *
	 * @param int    $request_id  Request ID.
	 * @param string $entity_type Entity type requested.
	 * @param int    $user_id     User ID.
	 * @param string $description Optional description.
	 * @return bool
	 */
	protected function send_feature_request_email( $request_id, $entity_type, $user_id, $description = '' ) {
		$user = $user_id > 0 ? get_userdata( $user_id ) : null;
		$user_email = $user ? $user->user_email : __( 'Guest', 'dataviz-ai-woocommerce' );
		$user_name = $user ? $user->display_name : __( 'Guest User', 'dataviz-ai-woocommerce' );

		// Get admin email.
		$admin_email = get_option( 'admin_email' );
		$site_name = get_bloginfo( 'name' );

		// Build email subject.
		$subject = sprintf(
			/* translators: %1$s: site name, %2$s: entity type */
			__( '[%1$s] New Feature Request: %2$s', 'dataviz-ai-woocommerce' ),
			$site_name,
			ucfirst( $entity_type )
		);

		// Build email message.
		$message = sprintf(
			/* translators: %1$s: site name */
			__( 'A new feature request has been submitted on %1$s:', 'dataviz-ai-woocommerce' ),
			$site_name
		) . "\n\n";

		$message .= sprintf( __( 'Request ID: #%d', 'dataviz-ai-woocommerce' ), $request_id ) . "\n";
		$message .= sprintf( __( 'Feature Requested: %s', 'dataviz-ai-woocommerce' ), ucfirst( $entity_type ) ) . "\n";
		$message .= sprintf( __( 'Requested By: %s (%s)', 'dataviz-ai-woocommerce' ), $user_name, $user_email ) . "\n";
		$message .= sprintf( __( 'Date: %s', 'dataviz-ai-woocommerce' ), current_time( 'mysql' ) ) . "\n";

		if ( ! empty( $description ) ) {
			$message .= "\n" . __( 'Description:', 'dataviz-ai-woocommerce' ) . "\n";
			$message .= $description . "\n";
		}

		$message .= "\n" . sprintf(
			/* translators: %s: admin URL */
			__( 'View all feature requests: %s', 'dataviz-ai-woocommerce' ),
			admin_url( 'admin.php?page=dataviz-ai-feature-requests' )
		) . "\n";

		// Send email to all admins.
		$admins = get_users( array( 'role' => 'administrator' ) );
		$sent = false;
		$email_errors = array();

		if ( empty( $admins ) ) {
			// Log error if no admins found.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( '[Dataviz AI] No administrators found to send feature request email to.' );
			}
			return false;
		}

		foreach ( $admins as $admin ) {
			$email_result = wp_mail(
				$admin->user_email,
				$subject,
				$message,
				array(
					'Content-Type: text/plain; charset=UTF-8',
					'From: ' . $site_name . ' <' . $admin_email . '>',
				)
			);

			if ( $email_result ) {
				$sent = true;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] Feature request email sent successfully to: %s', $admin->user_email ) );
				}
			} else {
				$email_errors[] = $admin->user_email;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] Failed to send feature request email to: %s', $admin->user_email ) );
				}
			}
		}

		// Log summary.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf(
				'[Dataviz AI] Feature request email summary - Total admins: %d, Sent: %s, Failed: %d',
				count( $admins ),
				$sent ? 'Yes' : 'No',
				count( $email_errors )
			) );
			if ( ! empty( $email_errors ) ) {
				error_log( '[Dataviz AI] Failed email addresses: ' . implode( ', ', $email_errors ) );
			}
		}

		return $sent;
	}

	/**
	 * Check if user's question is a feature request confirmation.
	 *
	 * @param string $question User's question.
	 * @return bool
	 */
	protected function is_feature_request_confirmation( $question ) {
		$lower_question = strtolower( trim( $question ) );
		$affirmative_patterns = array(
			'yes',
			'yes please',
			'yes, please',
			'yep',
			'yup',
			'sure',
			'ok',
			'okay',
			'request this feature',
			'submit request',
			'submit the request',
			'please submit',
			'go ahead',
			'do it',
			'that would be great',
		);
		
		foreach ( $affirmative_patterns as $pattern ) {
			if ( $lower_question === $pattern || strpos( $lower_question, $pattern ) !== false ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Extract entity_type from transient storage.
	 *
	 * @return string|false Entity type or false if not found.
	 */
	protected function extract_entity_type_from_history() {
		// Get pending entity_type from transient (stored when error occurred).
		$transient_key = 'dataviz_ai_pending_request_' . md5( $this->session_id );
		$entity_type = get_transient( $transient_key );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf(
				'[Dataviz AI] Checking transient for entity_type - Key: %s, Found: %s',
				$transient_key,
				$entity_type ?: 'NOT FOUND'
			) );
		}
		
		if ( $entity_type ) {
			// Clear the transient after use.
			delete_transient( $transient_key );
			return $entity_type;
		}
		
		// Fallback: Try to extract from recent chat history.
		$history = $this->chat_history->get_session_history( $this->session_id, 20, 1 );
		$history = array_reverse( $history );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf(
				'[Dataviz AI] Checking chat history for entity_type - History messages: %d',
				count( $history )
			) );
		}
		
		foreach ( $history as $message ) {
			if ( $message['message_type'] === 'ai' ) {
				$content = $message['message_content'];
				// Check if content contains JSON with the error.
				if ( preg_match( '/"requested_entity"\s*:\s*"([^"]+)"/', $content, $matches ) ) {
					if ( preg_match( '/"can_submit_request"\s*:\s*true/', $content ) ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
							error_log( sprintf( '[Dataviz AI] Found entity_type in chat history: %s', $matches[1] ) );
						}
						return $matches[1];
					}
				}
			}
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[Dataviz AI] Could not extract entity_type from history' );
		}
		
		return false;
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

	/**
	 * Handle feature request submission via AJAX.
	 *
	 * @return void
	 */
	public function handle_submit_feature_request() {
		check_ajax_referer( 'dataviz_ai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized request.', 'dataviz-ai-woocommerce' ) ), 403 );
		}

		$entity_type = isset( $_POST['entity_type'] ) ? sanitize_text_field( wp_unslash( $_POST['entity_type'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$user_id     = get_current_user_id();

		if ( empty( $entity_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Entity type is required.', 'dataviz-ai-woocommerce' ) ), 400 );
		}

		require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-feature-requests.php';
		$feature_requests = new Dataviz_AI_Feature_Requests();
		$request_id = $feature_requests->submit_request( $entity_type, $user_id, $description );

		if ( $request_id ) {
			// Send email to admins.
			$email_sent = $this->send_feature_request_email( $request_id, $entity_type, $user_id, $description );
			
			// Log if email failed (but don't fail the request submission).
			if ( ! $email_sent && defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[Dataviz AI] Feature request #%d submitted but email notification failed.', $request_id ) );
			}

			wp_send_json_success(
				array(
					'message'    => sprintf(
						/* translators: %1$s: entity type, %2$d: request ID */
						__( 'Feature request for "%1$s" has been submitted successfully! Request ID: #%2$d.', 'dataviz-ai-woocommerce' ),
						esc_html( $entity_type ),
						$request_id
					),
					'request_id' => $request_id,
				)
			);
		}

		wp_send_json_error( array( 'message' => __( 'Failed to submit feature request. Please try again later.', 'dataviz-ai-woocommerce' ) ), 500 );
	}

	/**
	 * Handle inventory chart data request via AJAX.
	 *
	 * @return void
	 */
	public function handle_get_inventory_chart() {
		check_ajax_referer( 'dataviz_ai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized request.', 'dataviz-ai-woocommerce' ) ), 403 );
		}

		// Get all inventory products
		$inventory_data = $this->data_fetcher->get_all_inventory_products( array( 'limit' => 100 ) );

		if ( isset( $inventory_data['error'] ) && $inventory_data['error'] ) {
			wp_send_json_error( array( 'message' => $inventory_data['message'] ), 400 );
		}

		wp_send_json_success( array( 'products' => $inventory_data['products'] ?? array() ) );
	}
}

