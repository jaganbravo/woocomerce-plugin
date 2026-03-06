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
	 * Normalize relative date ranges from the original question (PHP source of truth).
	 * This prevents stale/hallucinated date ranges from the intent parser.
	 *
	 * @param string $question User question.
	 * @param array  $validated_intent Validated intent.
	 * @return array
	 */
	protected function normalize_relative_date_ranges_from_question( $question, array $validated_intent ) {
		// Handle "last N days" / "in the last N days".
		if ( preg_match( '/\b(?:in\s+the\s+)?last\s+(\d+)\s+days\b/i', (string) $question, $m ) ) {
			$days = (int) $m[1];
			$days = max( 1, min( 3650, $days ) );

			$current_year = (int) current_time( 'Y' );
			$from_year = null;
			if ( isset( $validated_intent['filters']['date_from'] ) && is_string( $validated_intent['filters']['date_from'] ) ) {
				$from_year = (int) substr( $validated_intent['filters']['date_from'], 0, 4 );
			}

			// If the intent has no dates, or dates look stale (older than last year), override.
			$needs_override = false;
			if ( empty( $validated_intent['filters']['date_from'] ) || empty( $validated_intent['filters']['date_to'] ) ) {
				$needs_override = true;
			} elseif ( $from_year !== null && $from_year < ( $current_year - 1 ) ) {
				$needs_override = true;
			}

			if ( $needs_override ) {
				$now = current_time( 'timestamp' );
				$validated_intent['filters']['date_from'] = date( 'Y-m-d', $now - ( $days * DAY_IN_SECONDS ) );
				$validated_intent['filters']['date_to']   = date( 'Y-m-d', $now );
			}
		}

		// Handle "last quarter" deterministically.
		if ( preg_match( '/\blast\s+quarter\b/i', (string) $question ) ) {
			$has_dates = ! empty( $validated_intent['filters']['date_from'] ) && ! empty( $validated_intent['filters']['date_to'] );
			$current_year = (int) current_time( 'Y' );
			$from_year = null;
			if ( isset( $validated_intent['filters']['date_from'] ) && is_string( $validated_intent['filters']['date_from'] ) ) {
				$from_year = (int) substr( $validated_intent['filters']['date_from'], 0, 4 );
			}

			// Override if missing or stale.
			if ( ! $has_dates || ( $from_year !== null && $from_year < ( $current_year - 1 ) ) ) {
				$range = Dataviz_AI_Intent_Validator::validate(
					array(
						'intent_version' => '1',
						'requires_data'  => true,
						'entity'         => 'orders',
						'operation'      => 'statistics',
						'metrics'        => array(),
						'dimensions'     => array(),
						'filters'        => array(
							'date_range' => array(
								'preset' => 'last_quarter',
								'from'   => null,
								'to'     => null,
							),
						),
						'confidence'     => 'low',
						'draft_answer'   => null,
					)
				);

				// If for any reason this fails, compute locally.
				if ( is_array( $range ) && ! empty( $range['filters']['date_from'] ) && ! empty( $range['filters']['date_to'] ) ) {
					$validated_intent['filters']['date_from'] = $range['filters']['date_from'];
					$validated_intent['filters']['date_to']   = $range['filters']['date_to'];
				} else {
					$now = current_time( 'timestamp' );
					$current_month = (int) current_time( 'm' );
					$current_quarter = (int) floor( ( $current_month - 1 ) / 3 ) + 1;
					$last_quarter = $current_quarter - 1;
					$year = (int) current_time( 'Y' );
					if ( $last_quarter < 1 ) {
						$last_quarter = 4;
						$year = $year - 1;
					}
					$start_month = ( ( $last_quarter - 1 ) * 3 ) + 1;
					$from = sprintf( '%04d-%02d-01', $year, $start_month );
					$last_day_timestamp = strtotime( $from . ' +3 months -1 day' );
					$validated_intent['filters']['date_from'] = $from;
					$validated_intent['filters']['date_to']   = date( 'Y-m-d', $last_day_timestamp );
				}
			}
		}

		return $validated_intent;
	}

	/**
	 * Detect comparison queries that require multiple date ranges (unsupported today).
	 *
	 * @param string $question Question.
	 * @return bool
	 */
	protected function is_comparison_question( $question ) {
		$q = (string) $question;
		return (bool) preg_match( '/\b(compare|compared\s+to|vs\.?|versus|same\s+month\s+last\s+year|same\s+period\s+last\s+year|year\s+over\s+year|yo\s*y)\b/i', $q );
	}

	/**
	 * Detect conversion-rate questions that require traffic analytics (unsupported today).
	 *
	 * @param string $question Question.
	 * @return bool
	 */
	protected function is_conversion_rate_question( $question ) {
		$q = (string) $question;
		return (bool) preg_match( '/\b(conversion\s*rate|conversion|cvr)\b/i', $q )
			&& (bool) preg_match( '/\b(traffic|visitors?|sessions?|pageviews?)\b/i', $q );
	}

	/**
	 * Normalize high-level intent from question when the intent parser misclassifies.
	 * PHP remains source of truth for critical routing decisions.
	 *
	 * @param string $question Question.
	 * @param array  $validated_intent Validated intent.
	 * @return array
	 */
	protected function normalize_intent_from_question( $question, array $validated_intent ) {
		$q = (string) $question;

		// Coupon usage in a period: route to coupon statistics so PHP can aggregate from orders.
		// Example: "Show me all coupons that have been used in the last month."
		if (
			preg_match( '/\bcoupons?\b/i', $q )
			&& preg_match( '/\bused\b/i', $q )
			&& preg_match( '/\blast\s+month\b/i', $q )
		) {
			$validated_intent['entity']    = 'coupons';
			$validated_intent['operation'] = 'statistics';
			// Resolve "last month" deterministically.
			$current_year  = (int) current_time( 'Y' );
			$current_month = (int) current_time( 'm' );
			if ( $current_month === 1 ) {
				$last_month = 12;
				$last_year  = $current_year - 1;
			} else {
				$last_month = $current_month - 1;
				$last_year  = $current_year;
			}
			$from = sprintf( '%04d-%02d-01', $last_year, $last_month );
			$last_day_timestamp = strtotime( $from . ' +1 month -1 day' );
			$validated_intent['filters']['date_from'] = $from;
			$validated_intent['filters']['date_to']   = date( 'Y-m-d', $last_day_timestamp );
			return $validated_intent;
		}

		// Tag count questions should route to tags list so we can use the tag term count.
		// Example: "How many products have the tag 'New Arrival'?"
		if (
			preg_match( '/\b(how\s+many|count|number\s+of)\b/i', $q )
			&& preg_match( '/\bproducts?\b/i', $q )
			&& preg_match( '/\btags?\b/i', $q )
		) {
			$validated_intent['entity']    = 'tags';
			$validated_intent['operation'] = 'list';
			return $validated_intent;
		}

		// Product categories listing should route to categories list deterministically.
		// Example: "What categories do my products belong to?"
		if ( preg_match( '/\bcategor(y|ies)\b/i', $q ) && preg_match( '/\bproducts?\b/i', $q ) ) {
			$validated_intent['entity']    = 'categories';
			$validated_intent['operation'] = 'list';
			return $validated_intent;
		}

		// Top customers / total spend queries should route to customers statistics.
		if ( preg_match( '/\btop\b/i', $q ) && preg_match( '/\bcustomers?\b/i', $q ) ) {
			$validated_intent['entity']    = 'customers';
			$validated_intent['operation'] = 'statistics';
			$validated_intent['filters']['sort_by']  = 'total_spent';
			$validated_intent['filters']['group_by'] = 'customer';
			if ( empty( $validated_intent['filters']['limit'] ) ) {
				$validated_intent['filters']['limit'] = 10;
			}

			// Handle "last year" deterministically.
			if ( preg_match( '/\blast\s+year\b/i', $q ) ) {
				$now = current_time( 'timestamp' );
				$last_year = (int) current_time( 'Y' ) - 1;
				$validated_intent['filters']['date_from'] = $last_year . '-01-01';
				$validated_intent['filters']['date_to']   = $last_year . '-12-31';
			}
		}

		return $validated_intent;
	}

	/**
	 * Create a tool call that will trigger the existing unsupported-entity feature request flow.
	 *
	 * @param string $requested_entity Requested entity keyword.
	 * @return array
	 */
	protected function build_feature_request_tool_call( $requested_entity ) {
		return array(
			'function' => array(
				'name'      => 'get_woocommerce_data',
				'arguments' => wp_json_encode(
					array(
						'entity_type' => (string) $requested_entity,
						'query_type'  => 'statistics',
						'filters'     => array(),
					)
				),
			),
			'id'       => 'intent-unsupported-' . uniqid(),
		);
	}

	/**
	 * Store pending feature request context for the current session.
	 *
	 * @param string $entity_type Entity type keyword.
	 * @param string $description Optional description/context (e.g., original question).
	 * @return void
	 */
	protected function set_pending_feature_request_context( $entity_type, $description = '' ) {
		$transient_key = 'dataviz_ai_pending_request_' . md5( $this->session_id );
		set_transient( $transient_key, (string) $entity_type, HOUR_IN_SECONDS );

		$desc_key = 'dataviz_ai_pending_request_desc_' . md5( $this->session_id );
		if ( is_string( $description ) && $description !== '' ) {
			set_transient( $desc_key, $description, HOUR_IN_SECONDS );
		} else {
			delete_transient( $desc_key );
		}
	}

	/**
	 * Extract pending feature request description from transient storage.
	 *
	 * @return string
	 */
	protected function extract_feature_request_description_from_history() {
		$desc_key    = 'dataviz_ai_pending_request_desc_' . md5( $this->session_id );
		$description = get_transient( $desc_key );
		if ( is_string( $description ) && $description !== '' ) {
			delete_transient( $desc_key );
			return $description;
		}
		return '';
	}

	/**
	 * Build a deterministic feature-request prompt when intent parsing/validation fails.
	 *
	 * @param string $question Original question.
	 * @param string $reason   Optional failure reason.
	 * @return array Response payload (answer/provider).
	 */
	protected function build_intent_not_found_feature_request_response( $question, $reason = '' ) {
		$entity_type = 'intent_not_found';
		$description = "User question:\n" . (string) $question;
		if ( is_string( $reason ) && $reason !== '' ) {
			$description .= "\n\nReason:\n" . $reason;
		}
		$this->set_pending_feature_request_context( $entity_type, $description );

		$message = __( 'I wasn’t able to understand this request well enough to fetch WooCommerce data for it yet.', 'dataviz-ai-woocommerce' );
		$prompt  = __( 'Would you like to request this feature? Just say "yes" and I’ll submit a feature request to the administrators so we can support questions like this.', 'dataviz-ai-woocommerce' );

		return array(
			'answer'   => trim( $message . "\n\n" . $prompt ),
			'provider' => 'system',
		);
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
				$description = $this->extract_feature_request_description_from_history();
				$args = array( 'entity_type' => $entity_type );
				if ( is_string( $description ) && $description !== '' ) {
					$args['description'] = $description;
				}
				$tool_calls = array(
					array(
						'function' => array(
							'name' => 'submit_feature_request',
							'arguments' => wp_json_encode( $args ),
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

		$validated_intent = null;
		$is_data_question = Dataviz_AI_Intent_Classifier::question_requires_data( $question );

		// Non-data question: stream general chat response.
		if ( ! $is_data_question ) {
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

			if ( ! empty( $this->streaming_content ) ) {
				$this->chat_history->save_message( 'ai', $this->streaming_content, $this->session_id, array( 'provider' => 'openai', 'streaming' => true ) );
			}

			$this->send_stream_end();
			return;
		}

		// Data question: for some unsupported requests, route directly to feature request (no intent parsing).
		if ( $this->is_comparison_question( $question ) ) {
			$validated_intent = array(
				'requires_data' => true,
				'entity'        => 'comparisons',
				'operation'     => 'feature_request',
			);
			$tool_calls = array( $this->build_feature_request_tool_call( 'comparisons' ) );
		} elseif ( $this->is_conversion_rate_question( $question ) ) {
			$validated_intent = array(
				'requires_data' => true,
				'entity'        => 'conversion_rate',
				'operation'     => 'feature_request',
			);
			$tool_calls = array( $this->build_feature_request_tool_call( 'conversion_rate' ) );
		} else {
			// Normal data question: parse intent via LLM, validate, and build tool calls.
			$intent_parse = $this->api_client->parse_intent( $question );
			if ( is_wp_error( $intent_parse ) ) {
				// Only offer a feature request when the intent itself is invalid/unknown (not for temporary infra errors).
				if ( $intent_parse->get_error_code() === 'dataviz_ai_invalid_intent' ) {
					$resp = $this->build_intent_not_found_feature_request_response( $question, $intent_parse->get_error_message() );
					$this->streaming_content = $resp['answer'];
					$this->send_stream_chunk( $resp['answer'] );
					$this->chat_history->save_message( 'ai', $resp['answer'], $this->session_id, array( 'provider' => $resp['provider'], 'streaming' => true, 'direct_response' => true ) );
					$this->send_stream_end();
				} else {
					$this->send_stream_error( __( 'Unable to process data query. Please try rephrasing your question.', 'dataviz-ai-woocommerce' ) );
				}
				return;
			}

			$validated_intent = Dataviz_AI_Intent_Validator::validate( is_array( $intent_parse['intent'] ?? null ) ? $intent_parse['intent'] : array() );
			if ( is_wp_error( $validated_intent ) || empty( $validated_intent['requires_data'] ) ) {
				$reason = is_wp_error( $validated_intent ) ? $validated_intent->get_error_message() : 'requires_data=false for data question';
				$resp = $this->build_intent_not_found_feature_request_response( $question, $reason );
				$this->streaming_content = $resp['answer'];
				$this->send_stream_chunk( $resp['answer'] );
				$this->chat_history->save_message( 'ai', $resp['answer'], $this->session_id, array( 'provider' => $resp['provider'], 'streaming' => true, 'direct_response' => true ) );
				$this->send_stream_end();
				return;
			}

			$validated_intent = $this->normalize_relative_date_ranges_from_question( $question, $validated_intent );
			$validated_intent = $this->normalize_intent_from_question( $question, $validated_intent );

			$tool_calls = Dataviz_AI_Execution_Engine::build_tool_calls( $validated_intent );
		}
		if ( empty( $tool_calls ) ) {
			$resp = $this->build_intent_not_found_feature_request_response( $question, 'Execution engine produced no tool calls.' );
			$this->streaming_content = $resp['answer'];
			$this->send_stream_chunk( $resp['answer'] );
			$this->chat_history->save_message( 'ai', $resp['answer'], $this->session_id, array( 'provider' => $resp['provider'], 'streaming' => true, 'direct_response' => true ) );
			$this->send_stream_end();
			return;
		}

		// If intent classification detected tools (simple case), execute them and then stream the final response.
		if ( ! empty( $tool_calls ) ) {
			// Store tool results for frontend (to avoid redundant AJAX calls).
			$tool_results_for_frontend = array();

			// Build assistant message with tool_calls (required by OpenAI API even when using intent classification)
			$assistant_tool_calls = array();
			$tool_results_messages = array();
			$results_for_prompt = array();

			foreach ( $tool_calls as $tool_call ) {
				// Handle both OpenAI format and auto-detected format.
				if ( isset( $tool_call['function']['name'] ) ) {
					$function_name = $tool_call['function']['name'];
					$arguments_json = isset( $tool_call['function']['arguments'] ) ? $tool_call['function']['arguments'] : '{}';
					$arguments = is_string( $arguments_json ) ? json_decode( $arguments_json, true ) : $arguments_json;
					$tool_call_id = isset( $tool_call['id'] ) ? $tool_call['id'] : 'intent-' . uniqid();
				} else {
					// Skip invalid tool calls.
					continue;
				}

				if ( empty( $function_name ) ) {
					continue;
				}

				// Build tool_call structure for assistant message
				$assistant_tool_calls[] = array(
					'id'       => $tool_call_id,
					'type'     => 'function',
					'function' => array(
						'name'      => $function_name,
						'arguments' => is_string( $arguments_json ) ? $arguments_json : wp_json_encode( $arguments ),
					),
				);

				// Validate arguments before execution (validation layer)
				$arguments = $this->validate_tool_arguments( $function_name, is_array( $arguments ) ? $arguments : array() );
				
				// Check if validation returned an error
				if ( isset( $arguments['error'] ) && $arguments['error'] === true ) {
					$tool_result = array(
						'error'      => true,
						'error_type' => 'validation_error',
						'message'    => $arguments['message'] ?? 'Validation failed',
					);
				} else {
					// Execute tool with try-catch to catch any exceptions
					try {
						$tool_result = $this->execute_tool( $function_name, $arguments );
					} catch ( Exception $e ) {
						// Log the exception for debugging
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
							error_log( sprintf( '[Dataviz AI] Exception in rule-based tool execution %s: %s in %s:%d', $function_name, $e->getMessage(), $e->getFile(), $e->getLine() ) );
						}
						$tool_result = array(
							'error'      => true,
							'error_type' => 'exception',
							'message'    => 'An error occurred while executing the tool: ' . $e->getMessage(),
						);
					} catch ( Error $e ) {
						// Catch PHP 7+ errors (TypeError, etc.)
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
							error_log( sprintf( '[Dataviz AI] Fatal error in rule-based tool execution %s: %s in %s:%d', $function_name, $e->getMessage(), $e->getFile(), $e->getLine() ) );
						}
						$tool_result = array(
							'error'      => true,
							'error_type' => 'fatal_error',
							'message'    => 'A fatal error occurred while executing the tool: ' . $e->getMessage(),
						);
					}
				}
				
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
						} elseif ( $entity_type === 'orders' && isset( $arguments['query_type'] ) && $arguments['query_type'] === 'list' ) {
							// Send order data for chart rendering when query_type is 'list'
							// The tool_result should contain an array of formatted orders
							if ( isset( $tool_result['orders'] ) && is_array( $tool_result['orders'] ) ) {
								$tool_results_for_frontend['orders'] = $tool_result['orders'];
							} elseif ( is_array( $tool_result ) && ! empty( $tool_result ) && isset( $tool_result[0]['id'] ) ) {
								// If tool_result is directly an array of orders
								$tool_results_for_frontend['orders'] = $tool_result;
							}
						} elseif ( $entity_type === 'orders' && isset( $arguments['query_type'] ) && $arguments['query_type'] === 'statistics' ) {
							// Send order statistics (status_breakdown and/or category_breakdown) for chart rendering
							$order_stats = array(
								'summary' => isset( $tool_result['summary'] ) ? $tool_result['summary'] : array(),
							);
							if ( ! empty( $tool_result['status_breakdown'] ) && is_array( $tool_result['status_breakdown'] ) ) {
								$order_stats['status_breakdown'] = $tool_result['status_breakdown'];
							}
							if ( ! empty( $tool_result['category_breakdown'] ) && is_array( $tool_result['category_breakdown'] ) ) {
								$order_stats['category_breakdown'] = $tool_result['category_breakdown'];
							}
							if ( isset( $order_stats['status_breakdown'] ) || isset( $order_stats['category_breakdown'] ) ) {
								$tool_results_for_frontend['order_statistics'] = $order_stats;
								// Backward compatibility: orders array for status_breakdown when present
								if ( isset( $order_stats['status_breakdown'] ) ) {
									$orders_for_chart = array();
									foreach ( $order_stats['status_breakdown'] as $status_data ) {
										for ( $i = 0; $i < ( isset( $status_data['count'] ) ? $status_data['count'] : 0 ); $i++ ) {
											$orders_for_chart[] = array(
												'id'     => 'stat-' . ( isset( $status_data['status'] ) ? $status_data['status'] : 'unknown' ) . '-' . $i,
												'status' => isset( $status_data['status'] ) ? $status_data['status'] : 'unknown',
												'total'  => isset( $status_data['revenue'], $status_data['count'] ) && $status_data['count'] > 0 ? (float) $status_data['revenue'] / $status_data['count'] : 0,
											);
										}
									}
									if ( ! empty( $orders_for_chart ) ) {
										$tool_results_for_frontend['orders'] = $orders_for_chart;
									}
								}
							}
						}
					} elseif ( $function_name === 'get_order_statistics' ) {
						// For statistics queries, send status_breakdown and/or category_breakdown for chart rendering
						$order_stats = array(
							'summary' => isset( $tool_result['summary'] ) ? $tool_result['summary'] : array(),
						);
						if ( ! empty( $tool_result['status_breakdown'] ) && is_array( $tool_result['status_breakdown'] ) ) {
							$order_stats['status_breakdown'] = $tool_result['status_breakdown'];
						}
						if ( ! empty( $tool_result['category_breakdown'] ) && is_array( $tool_result['category_breakdown'] ) ) {
							$order_stats['category_breakdown'] = $tool_result['category_breakdown'];
						}
						if ( isset( $order_stats['status_breakdown'] ) || isset( $order_stats['category_breakdown'] ) ) {
							$tool_results_for_frontend['order_statistics'] = $order_stats;
							// Backward compatibility: orders array for status_breakdown when present
							if ( isset( $order_stats['status_breakdown'] ) ) {
								$orders_for_chart = array();
								foreach ( $order_stats['status_breakdown'] as $status_data ) {
									for ( $i = 0; $i < ( isset( $status_data['count'] ) ? $status_data['count'] : 0 ); $i++ ) {
										$orders_for_chart[] = array(
											'id'     => 'stat-' . ( isset( $status_data['status'] ) ? $status_data['status'] : 'unknown' ) . '-' . $i,
											'status' => isset( $status_data['status'] ) ? $status_data['status'] : 'unknown',
											'total'  => isset( $status_data['revenue'], $status_data['count'] ) && $status_data['count'] > 0 ? (float) $status_data['revenue'] / $status_data['count'] : 0,
										);
									}
								}
								if ( ! empty( $orders_for_chart ) ) {
									$tool_results_for_frontend['orders'] = $orders_for_chart;
								}
							}
						}
					}
				}
				
				$results_for_prompt[] = array(
					'tool'      => $function_name,
					'arguments' => $arguments,
					'result'    => $tool_result,
				);

				// Build tool result message
				$tool_results_messages[] = array(
					'role'         => 'tool',
					'tool_call_id' => $tool_call_id,
					'content'      => wp_json_encode( $tool_result ),
				);
			}

			// Add assistant message with tool_calls (required by OpenAI API)
			$messages[] = array(
				'role'       => 'assistant',
				'content'    => null,
				'tool_calls' => $assistant_tool_calls,
			);

			// Add tool result messages
			$messages = array_merge( $messages, $tool_results_messages );

			// Provide validated intent context for the final LLM response (no tools).
			if ( is_array( $validated_intent ) ) {
				$messages[] = array(
					'role'    => 'assistant',
					'content' => "Validated intent (JSON):\n" . wp_json_encode( $validated_intent ),
				);
				if ( ! empty( $validated_intent['draft_answer'] ) && is_string( $validated_intent['draft_answer'] ) ) {
					$messages[] = array(
						'role'    => 'assistant',
						'content' => "Intent parser draft answer (may be incorrect; use ONLY if consistent with tool data):\n" . $validated_intent['draft_answer'],
					);
				}
			}
			
			// Send tool results to frontend as metadata (before text response).
			if ( ! empty( $tool_results_for_frontend ) ) {
				$this->send_stream_chunk( '', array( 'tool_data' => $tool_results_for_frontend ) );
			}

			// Deterministic answer composition (avoid LLM hallucinations for simple cases).
			$direct_response = Dataviz_AI_Answer_Composer::maybe_compose( $question, is_array( $validated_intent ) ? $validated_intent : array(), $results_for_prompt );
			if ( is_string( $direct_response ) && $direct_response !== '' ) {
				$this->streaming_content = $direct_response;
				$this->send_stream_chunk( $direct_response );
				$this->chat_history->save_message( 'ai', $direct_response, $this->session_id, array( 'provider' => 'openai', 'streaming' => true, 'direct_response' => true ) );
				$this->send_stream_end();
				return;
			}

			// Extract key numbers from tool results
			$extracted_numbers      = array();
			$direct_response_data   = null;
			foreach ( $tool_results_messages as $tool_msg ) {
				$tool_data = json_decode( $tool_msg['content'], true );
				if ( is_array( $tool_data ) && ! isset( $tool_data['error'] ) ) {
					// Store the full data for date range access
					if ( isset( $tool_data['summary'] ) ) {
						$direct_response_data = $tool_data;
					}
					
					// Extract numbers (use array_key_exists to handle 0 values correctly)
					if ( array_key_exists( 'total_orders', $tool_data['summary'] ?? array() ) ) {
						$extracted_numbers['total_orders'] = (int) $tool_data['summary']['total_orders'];
					}
					if ( array_key_exists( 'total_revenue', $tool_data['summary'] ?? array() ) ) {
						$extracted_numbers['total_revenue'] = (float) $tool_data['summary']['total_revenue'];
					}
					if ( array_key_exists( 'unique_customers', $tool_data['summary'] ?? array() ) ) {
						$extracted_numbers['unique_customers'] = (int) $tool_data['summary']['unique_customers'];
					}
				}
			}

			// For simple revenue questions (e.g. "total revenue this month"), format response
			// directly from statistics data to avoid hallucinations and missing numbers.
			$is_revenue_question = preg_match( '/\b(revenue|sales?|turnover)\b/i', $question );
			if ( $is_revenue_question && ! empty( $direct_response_data ) && array_key_exists( 'total_revenue', $extracted_numbers ) ) {
				$total_revenue = (float) $extracted_numbers['total_revenue'];
				$total_orders  = array_key_exists( 'total_orders', $extracted_numbers ) ? (int) $extracted_numbers['total_orders'] : null;

				// Build a human-readable period text from date_range if available.
				$period_text = '';
				if ( isset( $direct_response_data['date_range'] ) && is_array( $direct_response_data['date_range'] ) ) {
					$date_from = $direct_response_data['date_range']['from'] ?? '';
					$date_to   = $direct_response_data['date_range']['to'] ?? '';
					if ( $date_from && $date_to ) {
						$from_timestamp = strtotime( $date_from );
						$to_timestamp   = strtotime( $date_to );
						if ( $from_timestamp && $to_timestamp ) {
							$from_formatted = date_i18n( 'F j', $from_timestamp );
							$to_formatted   = date_i18n( 'F j, Y', $to_timestamp );
							$period_text    = sprintf( ' %s', sprintf( __( 'from %s to %s', 'dataviz-ai-woocommerce' ), $from_formatted, $to_formatted ) );
						}
					}
				}

				// Format revenue amount. Prefer WooCommerce formatting when available.
				if ( function_exists( 'wc_price' ) ) {
					$amount_str = wp_strip_all_tags( html_entity_decode( wc_price( $total_revenue ) ) );
				} else {
					$amount_str = sprintf(
						/* translators: %s: formatted revenue amount */
						__( '$%s', 'dataviz-ai-woocommerce' ),
						number_format_i18n( $total_revenue, 2 )
					);
				}

				if ( $total_revenue === 0.0 ) {
					$direct_response = sprintf(
						__( 'The total revenue generated%s is %s.', 'dataviz-ai-woocommerce' ),
						$period_text ? ' ' . $period_text : '',
						$amount_str
					);
					if ( null !== $total_orders ) {
						if ( $total_orders === 0 ) {
							$direct_response .= ' ' . __( 'There are no orders in this period.', 'dataviz-ai-woocommerce' );
						} elseif ( 1 === $total_orders ) {
							$direct_response .= ' ' . __( 'There is 1 order in this period.', 'dataviz-ai-woocommerce' );
						} else {
							$direct_response .= ' ' . sprintf(
								__( 'There are %d orders in this period.', 'dataviz-ai-woocommerce' ),
								$total_orders
							);
						}
					}
				} else {
					$direct_response = sprintf(
						__( 'The total revenue generated%s is %s.', 'dataviz-ai-woocommerce' ),
						$period_text ? ' ' . $period_text : '',
						$amount_str
					);
				}

				$this->streaming_content = $direct_response;
				$this->send_stream_chunk( $direct_response );
				$this->chat_history->save_message(
					'ai',
					$direct_response,
					$this->session_id,
					array(
						'provider'        => 'openai',
						'streaming'       => true,
						'direct_response' => true,
					)
				);
				$this->send_stream_end();
				return;
			}

			// For simple "how many" questions, format response directly from data (bypass LLM hallucination)
			$is_how_many_question = preg_match( '/\bhow many\b/i', $question );
			
			// Check if question is about customers
			$is_customer_question = preg_match( '/\b(customer|customers)\b/i', $question );
			
			if ( $is_how_many_question && ! empty( $direct_response_data ) ) {
				// Handle customer questions - check if unique_customers exists (even if 0)
				if ( $is_customer_question && array_key_exists( 'unique_customers', $extracted_numbers ) ) {
					$count = (int) $extracted_numbers['unique_customers'];
					
					// Format direct response (bypass LLM to avoid hallucination)
					if ( $count === 0 ) {
						// Get date range for better context
						$date_range = '';
						if ( isset( $direct_response_data['date_range'] ) ) {
							$date_from = $direct_response_data['date_range']['from'] ?? '';
							$date_to = $direct_response_data['date_range']['to'] ?? '';
							if ( $date_from && $date_to ) {
								// Validate dates are reasonable (not from the past too far)
								$current_year = (int) current_time( 'Y' );
								$from_year = (int) substr( $date_from, 0, 4 );
								$to_year = (int) substr( $date_to, 0, 4 );
								
								// Only show dates if they're reasonable (within current year or last year)
								if ( $from_year >= ( $current_year - 1 ) && $to_year >= ( $current_year - 1 ) ) {
									// Format dates nicely using WordPress timezone
									$from_timestamp = strtotime( $date_from );
									$to_timestamp = strtotime( $date_to );
									if ( $from_timestamp && $to_timestamp ) {
										$from_formatted = date_i18n( 'F j', $from_timestamp );
										$to_formatted = date_i18n( 'F j, Y', $to_timestamp );
										$date_range = sprintf( ' (from %s to %s)', $from_formatted, $to_formatted );
									}
								}
							}
						}
						$direct_response = sprintf( __( 'There are currently no customers who have placed orders in the specified period%s.', 'dataviz-ai-woocommerce' ), $date_range );
					} elseif ( $count === 1 ) {
						$direct_response = sprintf( __( 'There is %d customer who placed orders in the specified period.', 'dataviz-ai-woocommerce' ), $count );
					} else {
						$direct_response = sprintf( __( 'There are %d customers who placed orders in the specified period.', 'dataviz-ai-woocommerce' ), $count );
					}
					
					// Stream the direct response
					$this->streaming_content = $direct_response;
					$this->send_stream_chunk( $direct_response );
					
					// Save to chat history
					$this->chat_history->save_message( 'ai', $direct_response, $this->session_id, array( 'provider' => 'openai', 'streaming' => true, 'direct_response' => true ) );
					
					$this->send_stream_end();
					return;
				}
				
				// Handle order questions
				if ( isset( $extracted_numbers['total_orders'] ) ) {
				// Determine the entity type from the question
				$status_text = '';
				if ( preg_match( '/\b(completed|pending|processing|cancelled|refunded|failed|on.?hold)\b/i', $question, $status_matches ) ) {
					$status_text = strtolower( $status_matches[1] );
					if ( $status_text === 'on-hold' || $status_text === 'on hold' ) {
						$status_text = 'on-hold';
					}
				}
				
				$count = $extracted_numbers['total_orders'];
				$entity_name = 'orders';
				if ( ! empty( $status_text ) ) {
					$entity_name = $status_text . ' orders';
				}
				
				// Format direct response (bypass LLM to avoid hallucination)
				if ( $count === 1 ) {
					$direct_response = sprintf( __( 'There is %d %s in the WooCommerce store.', 'dataviz-ai-woocommerce' ), $count, $entity_name );
				} else {
					$direct_response = sprintf( __( 'There are %d %s in the WooCommerce store.', 'dataviz-ai-woocommerce' ), $count, $entity_name );
				}
				
				// Stream the direct response
				$this->streaming_content = $direct_response;
				$this->send_stream_chunk( $direct_response );
				
				// Save to chat history
				$this->chat_history->save_message( 'ai', $direct_response, $this->session_id, array( 'provider' => 'openai', 'streaming' => true, 'direct_response' => true ) );
				
				$this->send_stream_end();
				return;
				}
			}

			// Now get the final streaming response (for non-simple questions).
			// Use LangChain-style prompt templates for structured prompting.
			$data_template = Dataviz_AI_Prompt_Template::data_analysis();
			
			// Build key data section if we have extracted numbers.
			$key_data_section = '';
			if ( ! empty( $extracted_numbers ) ) {
				$key_data_parts = array();
				if ( isset( $extracted_numbers['total_orders'] ) ) {
					$key_data_parts[] = 'Total orders: ' . $extracted_numbers['total_orders'];
				}
				if ( isset( $extracted_numbers['total_revenue'] ) ) {
					$key_data_parts[] = 'Total revenue: ' . $extracted_numbers['total_revenue'];
				}
				if ( ! empty( $key_data_parts ) ) {
					$key_data_section = "\n\nKEY DATA FROM TOOLS: " . implode( '. ', $key_data_parts ) . ". ";
					$key_data_section .= "The full tool response data is also available in the tool messages above.";
				}
			}
			
			// Format the data analysis template with variables.
			$final_prompt = $data_template->format(
				array(
					'question' => $question,
				)
			);
			
			// Add key data section if available.
			if ( ! empty( $key_data_section ) ) {
				$final_prompt .= $key_data_section;
			}
			
			// Combine with other specialized templates.
			$final_prompt = Dataviz_AI_Prompt_Template::combine(
				array(
					$final_prompt,
					Dataviz_AI_Prompt_Template::error_handling()->format(),
					Dataviz_AI_Prompt_Template::chart_request()->format(),
					Dataviz_AI_Prompt_Template::empty_data()->format(),
					"If a feature request was successfully submitted (check for 'success': true in tool responses), confirm this to the user and let them know the administrators have been notified.",
					"Otherwise, provide a comprehensive and helpful answer using the actual data that was retrieved.",
					"\n\nRemember: Answer the question directly. Do not greet the user.",
				)
			);
			
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
		// Use LangChain-style prompt template for system message.
		$system_template = Dataviz_AI_Prompt_Template::system_analyst();
		
		// Combine with error handling template.
		$system_content = Dataviz_AI_Prompt_Template::combine(
			array(
				$system_template->format(),
				Dataviz_AI_Prompt_Template::error_handling()->format(),
			)
		);
		
		return array(
			array(
				'role'    => 'system',
				'content' => $system_content,
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
	 * Handle smart analysis using rule-based tools and LLM summarization.
	 *
	 * Tools are selected and executed entirely in PHP (via the intent classifier
	 * and execute_tool). The LLM never decides which tools to call – it only
	 * receives the question and the already-fetched data and then explains it.
	 *
	 * @param string $question User's question.
	 *
	 * @return array|WP_Error
	 */
	protected function handle_smart_analysis( $question ) {
		// Log user question for diagnostics.
		$this->log_llm_decision( 'User Question', array( 'question' => $question ) );

		// If user is confirming a feature request submission in non-stream mode, submit it deterministically.
		if ( $this->is_feature_request_confirmation( $question ) ) {
			$entity_type = $this->extract_entity_type_from_history();
			if ( $entity_type ) {
				$description = $this->extract_feature_request_description_from_history();
				$args = array( 'entity_type' => $entity_type );
				if ( is_string( $description ) && $description !== '' ) {
					$args['description'] = $description;
				}
				$result = $this->handle_feature_request_submission( $args );
				if ( is_array( $result ) && isset( $result['message'] ) && is_string( $result['message'] ) ) {
					return array(
						'answer'   => $result['message'],
						'provider' => 'system',
					);
				}
				return array(
					'answer'   => __( 'Failed to submit feature request. Please try again later.', 'dataviz-ai-woocommerce' ),
					'provider' => 'system',
				);
			}
		}

		$is_data_question = Dataviz_AI_Intent_Classifier::question_requires_data( $question );

		// Non-data questions: pure chat, no tools.
		if ( ! $is_data_question ) {
			$system_template = Dataviz_AI_Prompt_Template::system_analyst();

			$messages = array(
				$system_template->build_message( 'system' ),
				array(
					'role'    => 'user',
					'content' => $question,
				),
			);

			$response = $this->api_client->send_openai_chat( $messages );

			if ( is_wp_error( $response ) ) {
				$this->log_llm_decision( 'LLM Error (non-data)', array( 'error' => $response->get_error_message() ) );
				return $response;
			}

			$message = $response['choices'][0]['message'] ?? array();
			$answer  = isset( $message['content'] ) ? $message['content'] : '';

			$this->log_llm_decision(
				'Final Answer (Non-data, No Tools)',
				array( 'answer_preview' => wp_trim_words( $answer, 50 ) )
			);

			return array(
				'answer'   => $answer,
				'provider' => 'openai',
			);
		}

		// Data questions: for some unsupported requests, route directly to feature request (no intent parsing).
		if ( $this->is_comparison_question( $question ) ) {
			$validated_intent = array(
				'requires_data' => true,
				'entity'        => 'comparisons',
				'operation'     => 'feature_request',
			);
			$tool_calls = array( $this->build_feature_request_tool_call( 'comparisons' ) );
		} elseif ( $this->is_conversion_rate_question( $question ) ) {
			$validated_intent = array(
				'requires_data' => true,
				'entity'        => 'conversion_rate',
				'operation'     => 'feature_request',
			);
			$tool_calls = array( $this->build_feature_request_tool_call( 'conversion_rate' ) );
		} else {
			// Normal data questions: parse intent via LLM (strict JSON), validate in PHP, execute tools in PHP.
			$intent_parse = $this->api_client->parse_intent( $question );
			if ( is_wp_error( $intent_parse ) ) {
				$this->log_llm_decision( 'Intent Parse Error', array( 'error' => $intent_parse->get_error_message() ) );
				if ( $intent_parse->get_error_code() === 'dataviz_ai_invalid_intent' ) {
					return $this->build_intent_not_found_feature_request_response( $question, $intent_parse->get_error_message() );
				}
				return new WP_Error(
					'dataviz_ai_unable_to_process',
					__( 'Unable to process data query. Please try rephrasing your question.', 'dataviz-ai-woocommerce' )
				);
			}

			$validated_intent = Dataviz_AI_Intent_Validator::validate( is_array( $intent_parse['intent'] ?? null ) ? $intent_parse['intent'] : array() );
			if ( is_wp_error( $validated_intent ) || empty( $validated_intent['requires_data'] ) ) {
				$this->log_llm_decision(
					'Intent Validation Failed',
					array(
						'error' => is_wp_error( $validated_intent ) ? $validated_intent->get_error_message() : 'requires_data=false for data question',
						'raw'   => isset( $intent_parse['raw'] ) ? wp_trim_words( (string) $intent_parse['raw'], 80 ) : '',
					)
				);
				$reason = is_wp_error( $validated_intent ) ? $validated_intent->get_error_message() : 'requires_data=false for data question';
				return $this->build_intent_not_found_feature_request_response( $question, $reason );
			}

			$validated_intent = $this->normalize_relative_date_ranges_from_question( $question, $validated_intent );
			$validated_intent = $this->normalize_intent_from_question( $question, $validated_intent );

			$tool_calls = Dataviz_AI_Execution_Engine::build_tool_calls( $validated_intent );
		}
		if ( empty( $tool_calls ) ) {
			$this->log_llm_decision( 'Execution Engine: No tool calls', array( 'intent' => $validated_intent ) );
			return $this->build_intent_not_found_feature_request_response( $question, 'Execution engine produced no tool calls.' );
		}

		$results_for_prompt = array();
		$operations_used    = array();

		foreach ( $tool_calls as $tool_call ) {
			if ( ! isset( $tool_call['function']['name'] ) ) {
				continue;
			}

			$function_name  = $tool_call['function']['name'];
			$arguments_json = isset( $tool_call['function']['arguments'] ) ? $tool_call['function']['arguments'] : '{}';
			$arguments      = is_string( $arguments_json ) ? json_decode( $arguments_json, true ) : $arguments_json;

			if ( ! is_array( $arguments ) ) {
				$arguments = array();
			}

			// Validate arguments before execution.
			$validated_arguments = $this->validate_tool_arguments(
				$function_name,
				is_array( $arguments ) ? $arguments : array()
			);

			if ( isset( $validated_arguments['error'] ) && true === $validated_arguments['error'] ) {
				$tool_result = array(
					'error'      => true,
					'error_type' => 'validation_error',
					'message'    => $validated_arguments['message'] ?? 'Validation failed',
				);
			} else {
				// Execute tool with try-catch to catch any exceptions.
				try {
					$tool_result = $this->execute_tool( $function_name, $validated_arguments );
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						error_log(
							sprintf(
								'[Dataviz AI] Exception in non-stream tool execution %s: %s in %s:%d',
								$function_name,
								$e->getMessage(),
								$e->getFile(),
								$e->getLine()
							)
						);
					}
					$tool_result = array(
						'error'      => true,
						'error_type' => 'exception',
						'message'    => 'An error occurred while executing the tool: ' . $e->getMessage(),
					);
				} catch ( Error $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						error_log(
							sprintf(
								'[Dataviz AI] Fatal error in non-stream tool execution %s: %s in %s:%d',
								$function_name,
								$e->getMessage(),
								$e->getFile(),
								$e->getLine()
							)
						);
					}
					$tool_result = array(
						'error'      => true,
						'error_type' => 'fatal_error',
						'message'    => 'A fatal error occurred while executing the tool: ' . $e->getMessage(),
					);
				}
			}

			// Normalize WP_Error to array.
			if ( is_wp_error( $tool_result ) ) {
				$tool_result = array(
					'error'              => true,
					'error_type'         => 'execution_error',
					'message'            => $tool_result->get_error_message(),
					'error_code'         => $tool_result->get_error_code(),
				);
			}

			$results_for_prompt[] = array(
				'tool'      => $function_name,
				'arguments' => $validated_arguments,
				'result'    => $tool_result,
			);

			$operations_used[] = $function_name;
		}

		// Deterministic answer composition (avoid LLM hallucinations for simple cases).
		$direct_answer = Dataviz_AI_Answer_Composer::maybe_compose( $question, $validated_intent, $results_for_prompt );
		if ( is_string( $direct_answer ) && $direct_answer !== '' ) {
			$this->log_llm_decision( 'Direct response (composer)', array( 'answer_preview' => $direct_answer ) );
			return array(
				'answer'          => $direct_answer,
				'provider'        => 'openai',
				'operations_used' => $operations_used,
			);
		}

		// Build messages for LLM summarization (no tools exposed).
		$system_template = Dataviz_AI_Prompt_Template::system_analyst();
		$system_message  = $system_template->build_message( 'system' );

		$messages = array(
			$system_message,
			array(
				'role'    => 'user',
				'content' => $question,
			),
		);

		// Provide the validated intent and fetched data as JSON context.
		$messages[] = array(
			'role'    => 'assistant',
			'content' => "Here is the validated intent (JSON):\n" . wp_json_encode( $validated_intent ),
		);

		if ( ! empty( $validated_intent['draft_answer'] ) && is_string( $validated_intent['draft_answer'] ) ) {
			$messages[] = array(
				'role'    => 'assistant',
				'content' => "Intent parser draft answer (may be incorrect; use ONLY if consistent with data):\n" . $validated_intent['draft_answer'],
			);
		}

		// Provide the fetched data as JSON context.
		$messages[] = array(
			'role'    => 'assistant',
			'content' => "Here is the data fetched from the WooCommerce tools (JSON):\n" . wp_json_encode( $results_for_prompt ),
		);

		$final_prompt  = 'Based on the data above, please answer the original question: ' . $question . "\n\n";
		$final_prompt .= "IMPORTANT: If any tool returned an error (check for 'error': true in the data above), politely inform the user that the requested feature is not yet available. Use the error message and suggestions from the tool response to guide your answer. ";
		$final_prompt .= "CRITICAL: If the user asked for a chart, graph, pie chart, bar chart, or visualization, DO NOT say that charts are not supported. Charts are automatically rendered by the frontend - you just need to provide the data. Simply present the data you have in a clear format. ";
		$final_prompt .= "If the data shows empty arrays or no results, and a 'message' or 'date_range' is present in the data, use that to explain what range was searched and why there are no results (for example, suggest trying a different time period).";

		$messages[] = array(
			'role'    => 'user',
			'content' => $final_prompt,
		);

		$final_response = $this->api_client->send_openai_chat( $messages );

		if ( is_wp_error( $final_response ) ) {
			$this->log_llm_decision( 'LLM Error (data summarization)', array( 'error' => $final_response->get_error_message() ) );
			return $final_response;
		}

		$message = $final_response['choices'][0]['message'] ?? array();
		$answer  = isset( $message['content'] ) ? $message['content'] : '';

		$this->log_llm_decision(
			'Final Answer (Data, Classifier Tools)',
			array(
				'operations_used' => $operations_used,
				'answer_preview'  => wp_trim_words( $answer, 50 ),
			)
		);

		if ( $answer === '' ) {
			return new WP_Error(
				'dataviz_ai_invalid_response',
				__( 'Unexpected response format from AI.', 'dataviz-ai-woocommerce' )
			);
		}

		return array(
			'answer'          => $answer,
			'provider'        => 'openai',
			'operations_used' => $operations_used,
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
								'description' => 'Filters to apply. Can include: date_from, date_to, status, stock_threshold, limit, category_id, period, sample_size, group_by. For orders + statistics: group_by can be "status" (default), "customer", or "category" (e.g. sales by product category). CRITICAL: If filters are provided in the hints below, use them EXACTLY as provided - do not recalculate or modify date filters.',
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
									'group_by'       => array(
										'type'        => 'string',
										'description' => 'For orders + statistics: "status" (default), "customer", or "category". Use "category" when user asks for sales/revenue by product category.',
										'enum'        => array( 'status', 'customer', 'category' ),
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
					'description' => 'Get aggregated order statistics (totals, averages, counts, status breakdown, or sales by product category). USE THIS TOOL when user asks for: "total revenue", "how many orders", "average order value", "revenue by status", "order status" breakdown, OR "sales by product category" / "revenue by category" / "pie chart of sales by product category" (in that case pass group_by: "category"). Do NOT use entity_type "categories" for sales by category - use this tool with group_by: "category" instead. CRITICAL: If date filters are provided in the hints below, use them EXACTLY - do not recalculate dates.',
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
							'group_by'  => array(
								'type'        => 'string',
								'description' => 'Optional. Use "category" when user asks for sales or revenue by product category (e.g. pie chart of sales by category). Default is status breakdown.',
								'enum'        => array( 'status', 'category' ),
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
	 * Validate and sanitize tool arguments before execution.
	 * This is the validation layer that prevents bad tool calls.
	 *
	 * @param string $function_name Tool function name.
	 * @param array  $arguments     Tool arguments.
	 * @return array|WP_Error Validated and sanitized arguments, or error array if validation fails.
	 */
	protected function validate_tool_arguments( $function_name, array $arguments ) {
		// Validate tool exists
		$valid_tools = array( 'get_woocommerce_data', 'get_order_statistics', 'get_top_products', 
		                      'get_customers', 'get_customer_summary', 'submit_feature_request',
		                      'get_recent_orders' ); // Legacy tool
		
		if ( ! in_array( $function_name, $valid_tools, true ) ) {
			// Invalid tool - return error structure
			return array( 'error' => true, 'error_type' => 'invalid_tool', 'message' => 'Invalid tool: ' . $function_name );
		}

		// Validate entity_type if present
		if ( isset( $arguments['entity_type'] ) ) {
			$arguments['entity_type'] = $this->validate_and_normalize_entity_type( 
				$arguments['entity_type'], 
				$arguments 
			);
		}

		// Validate query_type enum
		if ( isset( $arguments['query_type'] ) ) {
			$valid_types = array( 'list', 'statistics', 'sample', 'by_period' );
			if ( ! in_array( $arguments['query_type'], $valid_types, true ) ) {
				$arguments['query_type'] = 'list'; // Safe default
			}
		}

		// Sanitize filters
		if ( isset( $arguments['filters'] ) && is_array( $arguments['filters'] ) ) {
			$arguments['filters'] = $this->sanitize_filters( $arguments['filters'] );
		}

		// Validate limit if present
		if ( isset( $arguments['limit'] ) ) {
			$arguments['limit'] = max( 1, min( 1000, (int) $arguments['limit'] ) );
		}

		// Validate filters.limit if present
		if ( isset( $arguments['filters']['limit'] ) ) {
			$arguments['filters']['limit'] = max( -1, min( 1000, (int) $arguments['filters']['limit'] ) );
		}

		return $arguments;
	}

	/**
	 * Validate and normalize entity type.
	 *
	 * @param string $entity_type Entity type to validate.
	 * @param array  $context     Full arguments context for better validation.
	 * @return string Normalized entity type.
	 */
	protected function validate_and_normalize_entity_type( $entity_type, array $context = array() ) {
		$normalized = strtolower( trim( $entity_type ) );

		// Map common mistakes/singular forms to plural
		$normalization_map = array(
			'product'  => 'products',
			'order'    => 'orders',
			'customer' => 'customers',
			'category' => 'categories',
			'tag'      => 'tags',
			'coupon'   => 'coupons',
			'refund'   => 'refunds',
		);

		if ( isset( $normalization_map[ $normalized ] ) ) {
			$normalized = $normalization_map[ $normalized ];
		}

		// Use the classifier's normalization
		$normalized = Dataviz_AI_Intent_Classifier::normalize_entity_type( $normalized );

		// Check if it's a valid entity (let execute_flexible_query handle unsupported types)
		$valid_entities = array( 'orders', 'products', 'customers', 'categories', 
		                         'tags', 'coupons', 'refunds', 'stock', 'inventory' );

		// If not valid, return as-is - execute_flexible_query will handle it gracefully
		return $normalized;
	}

	/**
	 * Sanitize filters array.
	 *
	 * @param array $filters Filters to sanitize.
	 * @return array Sanitized filters.
	 */
	protected function sanitize_filters( array $filters ) {
		$sanitized = array();

		// Sanitize common filter fields
		if ( isset( $filters['status'] ) ) {
			$sanitized['status'] = sanitize_text_field( $filters['status'] );
		}

		if ( isset( $filters['date_from'] ) ) {
			$sanitized['date_from'] = sanitize_text_field( $filters['date_from'] );
		}

		if ( isset( $filters['date_to'] ) ) {
			$sanitized['date_to'] = sanitize_text_field( $filters['date_to'] );
		}

		if ( isset( $filters['limit'] ) ) {
			$sanitized['limit'] = max( -1, min( 1000, (int) $filters['limit'] ) );
		}

		if ( isset( $filters['stock_threshold'] ) ) {
			$sanitized['stock_threshold'] = max( 0, (int) $filters['stock_threshold'] );
		}

		// Stock-specific filter: stock_status (instock/outofstock/onbackorder).
		// Needed for out-of-stock queries in hybrid intent flow.
		if ( isset( $filters['stock_status'] ) ) {
			$stock_status = strtolower( sanitize_text_field( $filters['stock_status'] ) );
			if ( in_array( $stock_status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
				$sanitized['stock_status'] = $stock_status;
			}
		}

		if ( isset( $filters['category_id'] ) ) {
			$sanitized['category_id'] = max( 0, (int) $filters['category_id'] );
		}

		if ( isset( $filters['period'] ) ) {
			$valid_periods = array( 'hour', 'day', 'week', 'month' );
			if ( in_array( $filters['period'], $valid_periods, true ) ) {
				$sanitized['period'] = $filters['period'];
			}
		}

		if ( isset( $filters['sample_size'] ) ) {
			$sanitized['sample_size'] = max( 50, min( 500, (int) $filters['sample_size'] ) );
		}

		// Customer-specific filters for statistics queries.
		if ( isset( $filters['sort_by'] ) ) {
			$allowed_sort = array( 'total_spent', 'order_count' );
			$sort_by      = sanitize_text_field( $filters['sort_by'] );
			if ( in_array( $sort_by, $allowed_sort, true ) ) {
				$sanitized['sort_by'] = $sort_by;
			}
		}

		if ( isset( $filters['group_by'] ) ) {
			$allowed_group_by = array( 'customer', 'category' );
			$group_by         = sanitize_text_field( $filters['group_by'] );
			if ( in_array( $group_by, $allowed_group_by, true ) ) {
				$sanitized['group_by'] = $group_by;
			}
		}

		if ( isset( $filters['min_orders'] ) ) {
			$sanitized['min_orders'] = max( 0, (int) $filters['min_orders'] );
		}

		return $sanitized;
	}

	/**
	 * Handle smart analysis with intent hints for guidance.
	 *
	 * @param string $question User's question.
	 * @param array  $hints    Intent hints from classifier.
	 * @return void
	 */
	protected function handle_smart_analysis_with_hints( $question, array $hints ) {
		// Get available tools
		$tools = $this->get_available_tools();

		// Enhance tool descriptions with hints
		$hints_prompt = '';
		if ( ! empty( $hints['primary_entity'] ) ) {
			$hints_prompt = "\n\nINTENT HINTS (use these to guide your decision):\n";
			$hints_prompt .= "- Primary entity: " . $hints['primary_entity'] . "\n";
			if ( ! empty( $hints['secondary_entities'] ) ) {
				$hints_prompt .= "- Secondary entities: " . implode( ', ', $hints['secondary_entities'] ) . "\n";
			}
			$hints_prompt .= "- Query type: " . $hints['query_type'] . "\n";
			$hints_prompt .= "- Confidence: " . $hints['confidence'] . "\n";
			$hints_prompt .= "- Complexity: " . $hints['complexity'] . "\n";
			
			// Include filters (especially date filters) in hints so LLM uses correct dates
			if ( ! empty( $hints['filters'] ) && is_array( $hints['filters'] ) ) {
				$hints_prompt .= "- Filters: " . wp_json_encode( $hints['filters'] ) . "\n";
				$hints_prompt .= "\nCRITICAL: Use the exact filter values provided above, especially date_from and date_to. Do NOT recalculate dates - use the provided values exactly.\n";
			}
			
			$hints_prompt .= "\nCRITICAL RULES based on hints:\n";
			$hints_prompt .= "- If hints say 'stock' and question mentions 'products', use entity_type: 'stock' (NOT 'products')\n";
			$hints_prompt .= "- If hints say 'inventory' and question mentions 'categories', use entity_type: 'inventory' (NOT 'categories')\n";
			$hints_prompt .= "- Always prioritize the primary_entity from hints over generic entity matching\n";
			$hints_prompt .= "- If filters are provided in hints, use them EXACTLY as provided - do not recalculate or modify them\n";
		}

		// Update tool descriptions to include hints
		foreach ( $tools as &$tool ) {
			if ( isset( $tool['function']['description'] ) ) {
				$tool['function']['description'] .= $hints_prompt;
			}
		}

		// Use the existing handle_smart_analysis but with enhanced tools
		// For now, we'll use the existing method but log that hints were used
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( '[Dataviz AI] Using LLM with hints for complex query. Hints: %s', wp_json_encode( $hints ) ) );
		}

		// Use the existing smart analysis method
		// We'll enhance it to include hints in the system prompt
		$system_template = Dataviz_AI_Prompt_Template::system_analyst();
		$system_message = $system_template->build_message( 'system' );
		
		// Add hints to system message
		if ( ! empty( $hints_prompt ) ) {
			$system_message['content'] .= $hints_prompt;
		}

		$messages = array(
			$system_message,
			array(
				'role'    => 'user',
				'content' => $question,
			),
		);

		// Fix empty properties arrays to be objects for OpenAI API
		$tools = $this->convert_empty_arrays_to_objects( $tools );

		$response = $this->api_client->send_openai_chat(
			$messages,
			array(
				'tools'       => $tools,
				'tool_choice' => 'auto',
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->send_stream_error( $response->get_error_message() );
			return;
		}

		// Validate response structure
		if ( ! isset( $response['choices'] ) || ! is_array( $response['choices'] ) || empty( $response['choices'] ) ) {
			$error_msg = 'Invalid response from OpenAI API: missing or empty choices array';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[Dataviz AI] %s. Response: %s', $error_msg, wp_json_encode( $response ) ) );
			}
			$this->send_stream_error( 'Invalid response from AI service. Please try again.' );
			return;
		}

		if ( ! isset( $response['choices'][0]['message'] ) ) {
			$error_msg = 'Invalid response from OpenAI API: missing message in choices';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[Dataviz AI] %s. Response: %s', $error_msg, wp_json_encode( $response ) ) );
			}
			$this->send_stream_error( 'Invalid response from AI service. Please try again.' );
			return;
		}

		$message = $response['choices'][0]['message'];

		// Execute tool calls with validation and error handling
		$tool_results = array();
		if ( isset( $message['tool_calls'] ) && is_array( $message['tool_calls'] ) ) {
			foreach ( $message['tool_calls'] as $tool_call ) {
				$function_name = $tool_call['function']['name'] ?? '';
				$tool_call_id  = $tool_call['id'] ?? 'unknown-' . uniqid();
				
				// Parse JSON arguments with error handling
				$arguments_json = $tool_call['function']['arguments'] ?? '{}';
				$arguments      = json_decode( $arguments_json, true );
				
				// Validate JSON parsing
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					$error_message = 'Invalid JSON in tool arguments: ' . json_last_error_msg();
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						error_log( sprintf( '[Dataviz AI] JSON parse error for tool %s: %s. Raw JSON: %s', $function_name, $error_message, $arguments_json ) );
					}
					$tool_results[] = array(
						'tool_call_id' => $tool_call_id,
						'role'         => 'tool',
						'name'         => $function_name,
						'content'      => wp_json_encode( array(
							'error'   => true,
							'error_type' => 'json_parse_error',
							'message' => $error_message,
						) ),
					);
					continue;
				}

				if ( ! is_array( $arguments ) ) {
					$arguments = array();
				}

				// Validate arguments before execution
				$arguments = $this->validate_tool_arguments( $function_name, $arguments );

				// Check if validation returned an error
				if ( isset( $arguments['error'] ) && $arguments['error'] === true ) {
					$tool_results[] = array(
						'tool_call_id' => $tool_call_id,
						'role'         => 'tool',
						'name'         => $function_name,
						'content'      => wp_json_encode( array(
							'error'   => true,
							'error_type' => 'validation_error',
							'message' => $arguments['message'] ?? 'Validation failed',
						) ),
					);
					continue;
				}

				// Execute tool with try-catch to catch any exceptions
				try {
					$result = $this->execute_tool( $function_name, $arguments );

					// Convert WP_Error to user-friendly format
					if ( is_wp_error( $result ) ) {
						$result = array(
							'error'      => true,
							'error_type' => 'execution_error',
							'message'    => $result->get_error_message(),
							'error_code' => $result->get_error_code(),
						);
					}
				} catch ( Exception $e ) {
					// Log the exception for debugging
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						error_log( sprintf( '[Dataviz AI] Exception in tool execution %s: %s in %s:%d', $function_name, $e->getMessage(), $e->getFile(), $e->getLine() ) );
					}
					$result = array(
						'error'      => true,
						'error_type' => 'exception',
						'message'    => 'An error occurred while executing the tool: ' . $e->getMessage(),
					);
				} catch ( Error $e ) {
					// Catch PHP 7+ errors (TypeError, etc.)
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						error_log( sprintf( '[Dataviz AI] Fatal error in tool execution %s: %s in %s:%d', $function_name, $e->getMessage(), $e->getFile(), $e->getLine() ) );
					}
					$result = array(
						'error'      => true,
						'error_type' => 'fatal_error',
						'message'    => 'A fatal error occurred while executing the tool: ' . $e->getMessage(),
					);
				}

				$tool_results[] = array(
					'tool_call_id' => $tool_call_id,
					'role'         => 'tool',
					'name'         => $function_name,
					'content'      => wp_json_encode( $result ),
				);
			}
		}

		// Send tool results back to LLM for final answer
		if ( ! empty( $tool_results ) ) {
			// Extract key numbers from tool results for direct response handling
			$extracted_numbers = array();
			$direct_response_data = null;
			foreach ( $tool_results as $tool_result ) {
				$tool_data = json_decode( $tool_result['content'], true );
				if ( is_array( $tool_data ) && ! isset( $tool_data['error'] ) ) {
					// Store the full data for date range access
					if ( isset( $tool_data['summary'] ) ) {
						$direct_response_data = $tool_data;
					}
					
					// Extract numbers (use array_key_exists to handle 0 values correctly)
					if ( array_key_exists( 'total_orders', $tool_data['summary'] ?? array() ) ) {
						$extracted_numbers['total_orders'] = (int) $tool_data['summary']['total_orders'];
					}
					if ( array_key_exists( 'total_revenue', $tool_data['summary'] ?? array() ) ) {
						$extracted_numbers['total_revenue'] = (float) $tool_data['summary']['total_revenue'];
					}
					if ( array_key_exists( 'unique_customers', $tool_data['summary'] ?? array() ) ) {
						$extracted_numbers['unique_customers'] = (int) $tool_data['summary']['unique_customers'];
					}
				}
			}

			// For simple "how many" questions, format response directly from data (bypass LLM hallucination)
			$is_how_many_question = preg_match( '/\bhow many\b/i', $question );
			$is_customer_question = preg_match( '/\b(customer|customers)\b/i', $question );
			
			if ( $is_how_many_question && ! empty( $direct_response_data ) ) {
				// Handle customer questions - check if unique_customers exists (even if 0)
				if ( $is_customer_question && array_key_exists( 'unique_customers', $extracted_numbers ) ) {
					$count = (int) $extracted_numbers['unique_customers'];
					
					// Format direct response (bypass LLM to avoid hallucination)
					if ( $count === 0 ) {
						// Get date range for better context
						$date_range = '';
						if ( isset( $direct_response_data['date_range'] ) ) {
							$date_from = $direct_response_data['date_range']['from'] ?? '';
							$date_to = $direct_response_data['date_range']['to'] ?? '';
							if ( $date_from && $date_to ) {
								// Validate dates are reasonable (not from the past too far)
								$current_year = (int) current_time( 'Y' );
								$from_year = (int) substr( $date_from, 0, 4 );
								$to_year = (int) substr( $date_to, 0, 4 );
								
								// Only show dates if they're reasonable (within current year or last year)
								if ( $from_year >= ( $current_year - 1 ) && $to_year >= ( $current_year - 1 ) ) {
									// Format dates nicely using WordPress timezone
									$from_timestamp = strtotime( $date_from );
									$to_timestamp = strtotime( $date_to );
									if ( $from_timestamp && $to_timestamp ) {
										$from_formatted = date_i18n( 'F j', $from_timestamp );
										$to_formatted = date_i18n( 'F j, Y', $to_timestamp );
										$date_range = sprintf( ' (from %s to %s)', $from_formatted, $to_formatted );
									}
								}
							}
						}
						$direct_response = sprintf( __( 'There are currently no customers who have placed orders in the specified period%s.', 'dataviz-ai-woocommerce' ), $date_range );
					} elseif ( $count === 1 ) {
						$direct_response = sprintf( __( 'There is %d customer who placed orders in the specified period.', 'dataviz-ai-woocommerce' ), $count );
					} else {
						$direct_response = sprintf( __( 'There are %d customers who placed orders in the specified period.', 'dataviz-ai-woocommerce' ), $count );
					}
					
					// Stream the direct response
					$this->streaming_content = $direct_response;
					$this->send_stream_chunk( $direct_response );
					
					// Save to chat history
					$this->chat_history->save_message( 'ai', $direct_response, $this->session_id, array( 'provider' => 'openai', 'streaming' => true, 'direct_response' => true ) );
					
					$this->send_stream_end();
					return;
				}
			}

			$messages[] = $message;
			$messages   = array_merge( $messages, $tool_results );

			$final_prompt = 'Based on the data you just fetched, please answer the original question: ' . $question . "\n\n";
			$final_prompt .= "IMPORTANT: If any tool returned an error (check for 'error': true in the tool responses), politely inform the user that the requested feature is not yet available. Use the error message and suggestions from the tool response to guide your answer. ";
			$final_prompt .= "CRITICAL: If the user asked for a chart, graph, pie chart, bar chart, or visualization, DO NOT say that charts are not supported. Charts are automatically rendered by the frontend - you just need to provide the data. Simply present the data you fetched in a clear format. ";
			$final_prompt .= "If the data shows empty arrays or no results, check if the response includes a 'message' field or 'date_range' field. If a 'message' field exists, use it to inform the user. If a 'date_range' is provided, mention the specific date range that was searched. For example, if searching 'this year' returns no results but the date_range shows 2026, you can suggest trying 'last year' or a different time period.";

			$messages[] = array(
				'role'    => 'user',
				'content' => $final_prompt,
			);

			// Stream the final response with error handling
			$this->streaming_content = '';
			try {
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
					$error_message = $stream_result->get_error_message();
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						error_log( sprintf( '[Dataviz AI] Streaming error: %s. Error data: %s', $error_message, wp_json_encode( $stream_result->get_error_data() ) ) );
					}
					$this->send_stream_error( $error_message );
					return;
				}

				// Save AI response to chat history
				if ( ! empty( $this->streaming_content ) ) {
					$this->chat_history->save_message( 'ai', $this->streaming_content, $this->session_id, array( 'provider' => 'openai', 'streaming' => true ) );
				}

				$this->send_stream_end();
			} catch ( Exception $e ) {
				// Log exception during streaming
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] Exception during streaming: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
				}
				$this->send_stream_error( 'An error occurred while generating the response. Please try again.' );
			} catch ( Error $e ) {
				// Catch PHP 7+ fatal errors
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] Fatal error during streaming: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
				}
				$this->send_stream_error( 'A fatal error occurred. Please try again.' );
			}
		} else {
			// No tools called - stream direct response
			try {
				if ( isset( $message['content'] ) && ! empty( $message['content'] ) ) {
					$this->streaming_content = $message['content'];
					$this->send_stream_chunk( $message['content'] );
					$this->chat_history->save_message( 'ai', $message['content'], $this->session_id, array( 'provider' => 'openai' ) );
					$this->send_stream_end();
				} else {
					// No content and no tools - this shouldn't happen, but handle gracefully
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						error_log( sprintf( '[Dataviz AI] Warning: LLM returned no content and no tool calls for question: %s. Message structure: %s', $question, wp_json_encode( $message ) ) );
					}
					$this->send_stream_error( 'No response generated. Please try rephrasing your question.' );
				}
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] Exception streaming direct response: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
				}
				$this->send_stream_error( 'An error occurred while processing the response.' );
			} catch ( Error $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] Fatal error streaming direct response: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
				}
				$this->send_stream_error( 'A fatal error occurred. Please try again.' );
			}
		}
	}

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
		
		// Sanitize filters before passing to handlers
		$filters = $this->sanitize_filters( $filters );
		
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
				return $this->handle_coupons_query( $query_type, $filters );

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
				// For list queries, handle limit properly
				// -1 means unlimited (get all orders), otherwise use specified limit or default
				if ( isset( $filters['limit'] ) ) {
					$limit = (int) $filters['limit'];
					if ( $limit === -1 ) {
						// Unlimited - get all orders
						$args['limit'] = -1;
					} else {
						// Cap at 1000 to avoid memory issues, but allow higher than default
						$args['limit'] = min( 1000, max( 1, $limit ) );
					}
				} else {
					// Default limit if not specified
					$args['limit'] = 20;
				}

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
		// Handle limit properly - -1 means unlimited (get all products)
		if ( isset( $filters['limit'] ) ) {
			$limit = (int) $filters['limit'];
			if ( $limit === -1 ) {
				// Unlimited - get all products
				$limit = -1;
			} else {
				// Cap at 500 to avoid memory issues, but allow higher than default
				$limit = min( 500, max( 1, $limit ) );
			}
		} else {
			// Default limit if not specified
			$limit = 10;
		}

		switch ( $query_type ) {
			case 'list':
			default:
				if ( isset( $filters['category_id'] ) ) {
					// For category queries, use category-specific method
					$category_limit = $limit === -1 ? 500 : $limit; // Cap at 500 for categories
					return $this->data_fetcher->get_products_by_category( (int) $filters['category_id'], $category_limit );
				} else {
					// Check if user wants "all" products or just top products
					// If limit is -1 or very high, use get_all_products instead of get_top_products
					if ( $limit === -1 || $limit > 50 ) {
						return $this->data_fetcher->get_all_products( $limit );
					} else {
						// For small limits, use top products (default behavior)
					return $this->data_fetcher->get_top_products( $limit );
					}
				}

			case 'statistics':
				// For statistics, use top products (can be enhanced later)
				$stats_limit = $limit === -1 ? 100 : $limit;
				return $this->data_fetcher->get_top_products( $stats_limit );
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
				// If the question is about top customers / spend over a period,
				// use order-based aggregation instead of simple lifetime summary.
				$has_date_filters  = isset( $filters['date_from'] ) || isset( $filters['date_to'] );
				$wants_top_spend   = isset( $filters['sort_by'] ) && $filters['sort_by'] === 'total_spent';
				$group_by_customer = isset( $filters['group_by'] ) && $filters['group_by'] === 'customer';

				// Debug logging
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] handle_customers_query - query_type: %s, filters: %s', $query_type, wp_json_encode( $filters ) ) );
					error_log( sprintf( '[Dataviz AI] has_date_filters: %s, wants_top_spend: %s, group_by_customer: %s', $has_date_filters ? 'yes' : 'no', $wants_top_spend ? 'yes' : 'no', $group_by_customer ? 'yes' : 'no' ) );
				}

				if ( $has_date_filters || $wants_top_spend || $group_by_customer ) {
					$result = $this->data_fetcher->get_customer_statistics( $filters );
					
					// Debug logging
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						error_log( sprintf( '[Dataviz AI] get_customer_statistics returned: %s', wp_json_encode( $result ) ) );
					}
					
					return $result;
				}

				// Fallback: generic lifetime customer summary.
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
	 * @param string $query_type Query type.
	 * @param array $filters Filters.
	 * @return array|WP_Error
	 */
	protected function handle_coupons_query( $query_type, array $filters ) {
		$query_type = (string) $query_type;
		if ( $query_type === 'statistics' && ( isset( $filters['date_from'] ) || isset( $filters['date_to'] ) ) ) {
			return $this->data_fetcher->get_coupon_usage( $filters );
		}
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
		// If user asked for "inventory" or "current inventory", show all inventory.
		// If user asked for "out of stock", show only out-of-stock products.
		// Otherwise, default to low-stock products.

		$entity_lower = strtolower( $entity_type );

		// Explicit out-of-stock filter from intent classifier.
		if ( isset( $filters['stock_status'] ) && $filters['stock_status'] === 'outofstock' ) {
			return $this->data_fetcher->get_out_of_stock_products();
		}

		// Check if user's question suggests "all inventory".
		if ( strpos( $entity_lower, 'inventory' ) !== false ) {
			// Return all products with inventory/stock levels.
			return $this->data_fetcher->get_all_inventory_products( $filters );
		}

		// Default: return only low stock products.
		$threshold = isset( $filters['stock_threshold'] ) ? (int) $filters['stock_threshold'] : 10;
		return $this->data_fetcher->get_low_stock_products( $threshold );
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

	/**
	 * Debug/test helper: parse + validate intent for a question.
	 *
	 * @return void
	 */
	public function handle_debug_intent_request() {
		check_ajax_referer( 'dataviz_ai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized request.', 'dataviz-ai-woocommerce' ) ), 403 );
		}

		$question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : '';
		if ( $question === '' ) {
			wp_send_json_error( array( 'message' => __( 'Question is required.', 'dataviz-ai-woocommerce' ) ), 400 );
		}

		$is_data_question = Dataviz_AI_Intent_Classifier::question_requires_data( $question );
		if ( ! $is_data_question ) {
			wp_send_json_success(
				array(
					'validated_intent' => array(
						'intent_version' => '1',
						'requires_data'  => false,
						'entity'         => 'orders',
						'operation'      => 'list',
						'metrics'        => array(),
						'dimensions'     => array(),
						'filters'        => array(),
						'confidence'     => 'low',
						'draft_answer'   => null,
					),
				)
			);
		}

		$intent_parse = $this->api_client->parse_intent( $question );
		if ( is_wp_error( $intent_parse ) ) {
			wp_send_json_error( array( 'message' => $intent_parse->get_error_message() ), 400 );
		}

		$validated_intent = Dataviz_AI_Intent_Validator::validate( is_array( $intent_parse['intent'] ?? null ) ? $intent_parse['intent'] : array() );
		if ( is_wp_error( $validated_intent ) ) {
			wp_send_json_error(
				array(
					'message' => $validated_intent->get_error_message(),
					'raw'     => $intent_parse['raw'] ?? null,
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'validated_intent' => $validated_intent,
				'raw'              => $intent_parse['raw'] ?? null,
				'model'            => $intent_parse['model'] ?? null,
			)
		);
	}
}

