<?php
/**
 * Query orchestrator — single code path for both streaming and non-streaming analysis.
 *
 * Replaces handle_streaming_analysis(), handle_smart_analysis(), and
 * handle_smart_analysis_with_hints() from the original AJAX handler.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Query_Orchestrator {

	/** @var Dataviz_AI_Intent_Pipeline */
	protected $pipeline;

	/** @var Dataviz_AI_Tool_Executor */
	protected $tool_executor;

	/** @var Dataviz_AI_API_Client */
	protected $api_client;

	/** @var Dataviz_AI_Stream_Handler */
	protected $stream_handler;

	/** @var Dataviz_AI_Chat_History */
	protected $chat_history;

	/** @var string */
	protected $session_id = '';

	/**
	 * @param Dataviz_AI_Intent_Pipeline $pipeline       Intent pipeline.
	 * @param Dataviz_AI_Tool_Executor   $tool_executor  Tool executor.
	 * @param Dataviz_AI_API_Client      $api_client     API client.
	 * @param Dataviz_AI_Stream_Handler  $stream_handler Stream handler.
	 * @param Dataviz_AI_Chat_History    $chat_history   Chat history.
	 */
	public function __construct(
		Dataviz_AI_Intent_Pipeline $pipeline,
		Dataviz_AI_Tool_Executor $tool_executor,
		Dataviz_AI_API_Client $api_client,
		Dataviz_AI_Stream_Handler $stream_handler,
		Dataviz_AI_Chat_History $chat_history
	) {
		$this->pipeline       = $pipeline;
		$this->tool_executor  = $tool_executor;
		$this->api_client     = $api_client;
		$this->stream_handler = $stream_handler;
		$this->chat_history   = $chat_history;
	}

	/**
	 * @param string $session_id Session ID.
	 * @return void
	 */
	public function set_session_id( $session_id ) {
		$this->session_id = (string) $session_id;
		$this->tool_executor->set_session_id( $this->session_id );
	}

	/**
	 * Handle a question — streaming variant.
	 *
	 * Sets up SSE headers, orchestrates the pipeline, and exits via stream.
	 *
	 * @param string $question User question.
	 * @return void
	 */
	public function handle_stream( $question ) {
		$this->stream_handler->setup_headers();

		// Custom backend fallback (non-streaming under the hood).
		if ( $this->api_client->has_custom_backend() ) {
			$this->handle_custom_backend_stream( $question );
			return;
		}

		// Feature-request confirmation shortcut.
		if ( $this->is_feature_request_confirmation( $question ) ) {
			$handled = $this->handle_feature_request_confirmation_stream( $question );
			if ( $handled ) {
				return;
			}
		}

		// Non-data question: pure LLM chat.
		if ( ! Dataviz_AI_Intent_Classifier::question_requires_data( $question ) ) {
			$this->stream_chat_response( $question );
			return;
		}

		// Data question: intent pipeline -> execute -> compose/stream.
		$pipeline_result = $this->pipeline->process( $question );

		// Pipeline hard error.
		if ( $pipeline_result['error'] instanceof \WP_Error ) {
			Dataviz_AI_Support_Requests::store_failed_question(
				$question,
				$pipeline_result['error']->get_error_message(),
				$pipeline_result['raw_intent'] ?? null
			);
			$this->stream_handler->send_error(
				__( 'Unable to process data query. Please try rephrasing your question.', 'dataviz-ai-woocommerce' )
			);
			return;
		}

		// Intent not found / feature request prompt.
		if ( ! empty( $pipeline_result['error_reason'] ) && empty( $pipeline_result['tool_calls'] ) ) {
			Dataviz_AI_Support_Requests::store_failed_question(
				$question,
				$pipeline_result['error_reason'],
				isset( $pipeline_result['intent'] ) ? wp_json_encode( $pipeline_result['intent'] ) : null
			);
			$resp = $this->build_intent_not_found_response( $question, $pipeline_result['error_reason'] );
			$this->stream_handler->send_chunk( $resp['answer'] );
			$this->chat_history->save_message( 'ai', $resp['answer'], $this->session_id, array( 'provider' => 'system', 'streaming' => true, 'direct_response' => true ) );
			$this->stream_handler->send_end();
			return;
		}

		// No tool calls produced.
		if ( empty( $pipeline_result['tool_calls'] ) ) {
			Dataviz_AI_Support_Requests::store_failed_question(
				$question,
				'Execution engine produced no tool calls.',
				isset( $pipeline_result['intent'] ) ? wp_json_encode( $pipeline_result['intent'] ) : null
			);
			$resp = $this->build_intent_not_found_response( $question, 'Execution engine produced no tool calls.' );
			$this->stream_handler->send_chunk( $resp['answer'] );
			$this->chat_history->save_message( 'ai', $resp['answer'], $this->session_id, array( 'provider' => 'system', 'streaming' => true, 'direct_response' => true ) );
			$this->stream_handler->send_end();
			return;
		}

		// Execute tools.
		$exec = $this->tool_executor->execute_all( $pipeline_result['tool_calls'] );

		// Send chart data to frontend early.
		if ( ! empty( $exec['frontend_data'] ) ) {
			$this->stream_handler->send_chunk( '', array( 'tool_data' => $exec['frontend_data'] ) );
		}

		$validated_intent = is_array( $pipeline_result['intent'] ) ? $pipeline_result['intent'] : array();

		// Try deterministic answer first.
		$direct = Dataviz_AI_Answer_Composer::maybe_compose( $question, $validated_intent, $exec['results_for_prompt'] );
		if ( is_string( $direct ) && $direct !== '' ) {
			$this->stream_handler->send_chunk( $direct );
			$this->chat_history->save_message( 'ai', $direct, $this->session_id, array( 'provider' => 'openai', 'streaming' => true, 'direct_response' => true ) );
			$this->stream_handler->send_end();
			return;
		}

		// LLM summarization with tool data.
		$messages = $this->build_summarization_messages( $question, $validated_intent, $exec );
		$this->stream_handler->reset_content();
		$stream_result = $this->stream_handler->stream_llm_response( $messages, true );

		if ( is_wp_error( $stream_result ) ) {
			$this->stream_handler->send_error( $stream_result->get_error_message() );
			return;
		}

		$content = $this->stream_handler->get_content();
		if ( ! empty( $content ) ) {
			$this->chat_history->save_message( 'ai', $content, $this->session_id, array( 'provider' => 'openai', 'streaming' => true ) );
		}

		$tool_for_done = ! empty( $exec['frontend_data'] ) ? $exec['frontend_data'] : null;
		$this->stream_handler->send_end( $tool_for_done );
	}

	/**
	 * Handle a question — non-streaming variant.
	 *
	 * @param string $question User question.
	 * @return array|WP_Error Response array with 'answer' and 'provider' keys.
	 */
	public function handle_non_stream( $question ) {
		// Custom backend.
		if ( $this->api_client->has_custom_backend() ) {
			return $this->handle_custom_backend( $question );
		}

		// Feature-request confirmation.
		if ( $this->is_feature_request_confirmation( $question ) ) {
			$entity_type = $this->extract_entity_type_from_history();
			if ( $entity_type ) {
				$desc = $this->extract_feature_request_description();
				$result = $this->tool_executor->execute_all( array(
					array(
						'function' => array(
							'name'      => 'submit_feature_request',
							'arguments' => wp_json_encode( array_filter( array( 'entity_type' => $entity_type, 'description' => $desc ) ) ),
						),
						'id' => 'auto-submit-' . uniqid(),
					),
				) );
				$first = $result['results_for_prompt'][0]['result'] ?? array();
				if ( isset( $first['message'] ) && is_string( $first['message'] ) ) {
					return array( 'answer' => $first['message'], 'provider' => 'system' );
				}
				return array( 'answer' => __( 'Failed to submit feature request. Please try again later.', 'dataviz-ai-woocommerce' ), 'provider' => 'system' );
			}
		}

		// Non-data question.
		if ( ! Dataviz_AI_Intent_Classifier::question_requires_data( $question ) ) {
			return $this->chat_response( $question );
		}

		// Data question.
		$pipeline_result = $this->pipeline->process( $question );

		if ( $pipeline_result['error'] instanceof \WP_Error ) {
			Dataviz_AI_Support_Requests::store_failed_question(
				$question,
				$pipeline_result['error']->get_error_message(),
				$pipeline_result['raw_intent'] ?? null
			);
			return $pipeline_result['error'];
		}

		if ( ! empty( $pipeline_result['error_reason'] ) && empty( $pipeline_result['tool_calls'] ) ) {
			Dataviz_AI_Support_Requests::store_failed_question(
				$question,
				$pipeline_result['error_reason'],
				isset( $pipeline_result['intent'] ) ? wp_json_encode( $pipeline_result['intent'] ) : null
			);
			return $this->build_intent_not_found_response( $question, $pipeline_result['error_reason'] );
		}

		if ( empty( $pipeline_result['tool_calls'] ) ) {
			Dataviz_AI_Support_Requests::store_failed_question(
				$question,
				'Execution engine produced no tool calls.',
				isset( $pipeline_result['intent'] ) ? wp_json_encode( $pipeline_result['intent'] ) : null
			);
			return $this->build_intent_not_found_response( $question, 'Execution engine produced no tool calls.' );
		}

		$exec = $this->tool_executor->execute_all( $pipeline_result['tool_calls'] );
		$validated_intent = is_array( $pipeline_result['intent'] ) ? $pipeline_result['intent'] : array();

		// Deterministic answer.
		$direct = Dataviz_AI_Answer_Composer::maybe_compose( $question, $validated_intent, $exec['results_for_prompt'] );
		if ( is_string( $direct ) && $direct !== '' ) {
			return array( 'answer' => $direct, 'provider' => 'openai', 'operations_used' => $exec['operations_used'] );
		}

		// LLM summarization.
		$messages = $this->build_summarization_messages( $question, $validated_intent, $exec );
		$response = $this->api_client->send_openai_chat( $messages );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$answer = $response['choices'][0]['message']['content'] ?? '';
		if ( $answer === '' ) {
			return new \WP_Error( 'dataviz_ai_invalid_response', __( 'Unexpected response format from AI.', 'dataviz-ai-woocommerce' ) );
		}

		return array( 'answer' => $answer, 'provider' => 'openai', 'operations_used' => $exec['operations_used'] );
	}

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	protected function build_summarization_messages( $question, array $validated_intent, array $exec ) {
		$system_template = Dataviz_AI_Prompt_Template::system_analyst();
		$system_content  = Dataviz_AI_Prompt_Template::combine( array(
			$system_template->format(),
			Dataviz_AI_Prompt_Template::error_handling()->format(),
		) );

		$messages = array(
			array( 'role' => 'system', 'content' => $system_content ),
			array( 'role' => 'user', 'content' => $question ),
		);

		// Attach tool call context for OpenAI API format.
		if ( ! empty( $exec['assistant_tool_calls'] ) ) {
			$messages[] = array(
				'role'       => 'assistant',
				'content'    => null,
				'tool_calls' => $exec['assistant_tool_calls'],
			);
			$messages = array_merge( $messages, $exec['tool_messages'] );
		}

		if ( ! empty( $validated_intent ) ) {
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

		$data_template = Dataviz_AI_Prompt_Template::data_analysis();
		$final_prompt  = $data_template->format( array( 'question' => $question ) );
		$final_prompt  = Dataviz_AI_Prompt_Template::combine( array(
			$final_prompt,
			Dataviz_AI_Prompt_Template::error_handling()->format(),
			Dataviz_AI_Prompt_Template::chart_request()->format(),
			Dataviz_AI_Prompt_Template::empty_data()->format(),
			"If a feature request was successfully submitted (check for 'success': true in tool responses), confirm this to the user and let them know the administrators have been notified.",
			"Otherwise, provide a comprehensive and helpful answer using the actual data that was retrieved.",
			"\n\nRemember: Answer the question directly. Do not greet the user.",
		) );

		$messages[] = array( 'role' => 'user', 'content' => $final_prompt );
		return $messages;
	}

	protected function build_chat_messages( $question ) {
		$system_template = Dataviz_AI_Prompt_Template::system_analyst();
		$system_content  = Dataviz_AI_Prompt_Template::combine( array(
			$system_template->format(),
			Dataviz_AI_Prompt_Template::error_handling()->format(),
		) );
		return array(
			array( 'role' => 'system', 'content' => $system_content ),
			array( 'role' => 'user', 'content' => $question ),
		);
	}

	protected function stream_chat_response( $question ) {
		$messages = $this->build_chat_messages( $question );
		$this->stream_handler->reset_content();
		$result = $this->stream_handler->stream_llm_response( $messages );

		if ( is_wp_error( $result ) ) {
			$this->stream_handler->send_error( $result->get_error_message() );
			return;
		}

		$content = $this->stream_handler->get_content();
		if ( ! empty( $content ) ) {
			$this->chat_history->save_message( 'ai', $content, $this->session_id, array( 'provider' => 'openai', 'streaming' => true ) );
		}
		$this->stream_handler->send_end();
	}

	protected function chat_response( $question ) {
		$messages = $this->build_chat_messages( $question );
		$response = $this->api_client->send_openai_chat( $messages );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$answer = $response['choices'][0]['message']['content'] ?? '';
		return array( 'answer' => $answer, 'provider' => 'openai' );
	}

	protected function handle_custom_backend_stream( $question ) {
		$data_fetcher = $this->get_data_fetcher();
		$orders    = $data_fetcher->get_recent_orders( array( 'limit' => 20 ) );
		$products  = $data_fetcher->get_top_products( 10 );
		$customers = $data_fetcher->get_customer_summary();

		$payload = array(
			'question'  => $question,
			'orders'    => $orders,
			'products'  => $products,
			'customers' => $customers,
		);

		$response = $this->api_client->post( 'api/woocommerce/ask', $payload );
		if ( is_wp_error( $response ) ) {
			$this->stream_handler->send_error( $response->get_error_message() );
			return;
		}

		$answer = isset( $response['answer'] ) ? $response['answer'] : __( 'Response received.', 'dataviz-ai-woocommerce' );
		$this->stream_handler->stream_text( $answer );
	}

	protected function handle_custom_backend( $question ) {
		$data_fetcher = $this->get_data_fetcher();
		$orders    = $data_fetcher->get_recent_orders( array( 'limit' => 20 ) );
		$products  = $data_fetcher->get_top_products( 10 );
		$customers = $data_fetcher->get_customer_summary();

		$payload = array(
			'question'  => $question,
			'orders'    => $orders,
			'products'  => $products,
			'customers' => $customers,
		);

		return $this->api_client->post( 'api/woocommerce/ask', $payload );
	}

	/**
	 * Get data fetcher from the tool executor (via reflection-free accessor).
	 *
	 * @return Dataviz_AI_Data_Fetcher
	 */
	protected function get_data_fetcher() {
		// The tool executor holds the data_fetcher; expose a getter for custom backend.
		return $this->data_fetcher;
	}

	/** @var Dataviz_AI_Data_Fetcher */
	protected $data_fetcher;

	/**
	 * @param Dataviz_AI_Data_Fetcher $data_fetcher Data fetcher.
	 * @return void
	 */
	public function set_data_fetcher( Dataviz_AI_Data_Fetcher $data_fetcher ) {
		$this->data_fetcher = $data_fetcher;
	}

	// ------------------------------------------------------------------
	// Feature request helpers
	// ------------------------------------------------------------------

	protected function handle_feature_request_confirmation_stream( $question ) {
		$entity_type = $this->extract_entity_type_from_history();
		if ( ! $entity_type ) {
			return false;
		}

		$desc = $this->extract_feature_request_description();
		$tool_calls = array(
			array(
				'function' => array(
					'name'      => 'submit_feature_request',
					'arguments' => wp_json_encode( array_filter( array( 'entity_type' => $entity_type, 'description' => $desc ) ) ),
				),
				'id' => 'auto-submit-' . uniqid(),
			),
		);

		$exec = $this->tool_executor->execute_all( $tool_calls );

		$messages = $this->build_chat_messages( $question );
		foreach ( $exec['results_for_prompt'] as $r ) {
			$messages[] = array(
				'role'       => 'assistant',
				'content'    => null,
				'tool_calls' => array( array(
					'id'       => 'auto-submit',
					'type'     => 'function',
					'function' => array( 'name' => $r['tool'], 'arguments' => wp_json_encode( $r['arguments'] ) ),
				) ),
			);
			$messages[] = array(
				'role'         => 'tool',
				'tool_call_id' => 'auto-submit',
				'content'      => wp_json_encode( $r['result'] ),
			);
		}
		$messages[] = array(
			'role'    => 'user',
			'content' => 'The user confirmed they want to submit a feature request. A feature request has been submitted. Please confirm this to the user using the tool response message.',
		);

		$this->stream_handler->reset_content();
		$result = $this->stream_handler->stream_llm_response( $messages );
		if ( is_wp_error( $result ) ) {
			$this->stream_handler->send_error( $result->get_error_message() );
			return true;
		}

		$content = $this->stream_handler->get_content();
		if ( ! empty( $content ) ) {
			$this->chat_history->save_message( 'ai', $content, $this->session_id, array( 'provider' => 'openai', 'streaming' => true ) );
		}
		$this->stream_handler->send_end();
		return true;
	}

	protected function is_feature_request_confirmation( $question ) {
		$lower = strtolower( trim( $question ) );
		$patterns = array( 'yes', 'yes please', 'yes, please', 'yep', 'yup', 'sure', 'ok', 'okay', 'request this feature', 'submit request', 'submit the request', 'please submit', 'go ahead', 'do it', 'that would be great' );
		foreach ( $patterns as $p ) {
			if ( $lower === $p || strpos( $lower, $p ) !== false ) {
				return true;
			}
		}
		return false;
	}

	protected function extract_entity_type_from_history() {
		$transient_key = 'dataviz_ai_pending_request_' . md5( $this->session_id );
		$entity_type   = get_transient( $transient_key );
		if ( $entity_type ) {
			delete_transient( $transient_key );
			return $entity_type;
		}

		$history = $this->chat_history->get_session_history( $this->session_id, 20, 1 );
		$history = array_reverse( $history );
		foreach ( $history as $msg ) {
			if ( $msg['message_type'] === 'ai' ) {
				$content = $msg['message_content'];
				if ( preg_match( '/"requested_entity"\s*:\s*"([^"]+)"/', $content, $m ) ) {
					if ( preg_match( '/"can_submit_request"\s*:\s*true/', $content ) ) {
						return $m[1];
					}
				}
			}
		}
		return false;
	}

	protected function extract_feature_request_description() {
		$desc_key = 'dataviz_ai_pending_request_desc_' . md5( $this->session_id );
		$desc     = get_transient( $desc_key );
		if ( is_string( $desc ) && $desc !== '' ) {
			delete_transient( $desc_key );
			return $desc;
		}
		return '';
	}

	protected function build_intent_not_found_response( $question, $reason = '' ) {
		$entity_type = 'intent_not_found';
		$description = "User question:\n" . (string) $question;
		if ( is_string( $reason ) && $reason !== '' ) {
			$description .= "\n\nReason:\n" . $reason;
		}

		$transient_key = 'dataviz_ai_pending_request_' . md5( $this->session_id );
		set_transient( $transient_key, $entity_type, HOUR_IN_SECONDS );
		$desc_key = 'dataviz_ai_pending_request_desc_' . md5( $this->session_id );
		set_transient( $desc_key, $description, HOUR_IN_SECONDS );

		$message = __( 'I was not able to understand this request well enough to fetch WooCommerce data for it yet.', 'dataviz-ai-woocommerce' );
		$prompt  = __( 'Would you like to request this feature? Just say "yes" and I will submit a feature request to the administrators so we can support questions like this.', 'dataviz-ai-woocommerce' );

		return array( 'answer' => trim( $message . "\n\n" . $prompt ), 'provider' => 'system' );
	}
}
