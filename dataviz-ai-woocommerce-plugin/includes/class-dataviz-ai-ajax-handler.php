<?php
/**
 * Thin AJAX endpoint handler for Dataviz AI WooCommerce plugin.
 *
 * All business logic has been delegated to focused services:
 * - Dataviz_AI_Query_Orchestrator  (streaming + non-streaming analysis)
 * - Dataviz_AI_Intent_Pipeline     (intent parsing / validation / normalization)
 * - Dataviz_AI_Tool_Executor       (tool validation / execution)
 * - Dataviz_AI_Stream_Handler      (SSE streaming I/O)
 * - Dataviz_AI_Intent_Normalizer   (PHP-side intent overrides)
 *
 * This class is responsible only for:
 * - WordPress nonce / capability checks
 * - Session ID management
 * - Chat history persistence
 * - Routing to the orchestrator or lightweight AJAX endpoints
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_AJAX_Handler {

	/** @var string */
	protected $plugin_name;

	/** @var Dataviz_AI_Data_Fetcher */
	protected $data_fetcher;

	/** @var Dataviz_AI_API_Client */
	protected $api_client;

	/** @var Dataviz_AI_Chat_History */
	protected $chat_history;

	/** @var Dataviz_AI_Query_Orchestrator */
	protected $orchestrator;

	/** @var Dataviz_AI_Intent_Pipeline */
	protected $intent_pipeline;

	/** @var string */
	protected $session_id = '';

	/**
	 * @param string                       $plugin_name     Plugin slug.
	 * @param Dataviz_AI_Data_Fetcher      $data_fetcher    Data fetcher.
	 * @param Dataviz_AI_API_Client        $api_client      API client.
	 * @param Dataviz_AI_Query_Orchestrator $orchestrator    Query orchestrator.
	 * @param Dataviz_AI_Intent_Pipeline   $intent_pipeline Intent pipeline.
	 */
	public function __construct(
		$plugin_name,
		Dataviz_AI_Data_Fetcher $data_fetcher,
		Dataviz_AI_API_Client $api_client,
		Dataviz_AI_Query_Orchestrator $orchestrator,
		Dataviz_AI_Intent_Pipeline $intent_pipeline
	) {
		$this->plugin_name     = $plugin_name;
		$this->data_fetcher    = $data_fetcher;
		$this->api_client      = $api_client;
		$this->orchestrator    = $orchestrator;
		$this->intent_pipeline = $intent_pipeline;
		$this->chat_history    = new Dataviz_AI_Chat_History();
	}

	// ------------------------------------------------------------------
	// Main analysis endpoint
	// ------------------------------------------------------------------

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
		$stream   = isset( $_POST['stream'] ) && filter_var( $_POST['stream'], FILTER_VALIDATE_BOOLEAN );

		$this->init_session();

		$this->chat_history->save_message( 'user', $question, $this->session_id );

		$this->orchestrator->set_session_id( $this->session_id );

		if ( $stream ) {
			$this->orchestrator->handle_stream( $question );
			return;
		}

		$response = $this->orchestrator->handle_non_stream( $question );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message(), 'data' => $response->get_error_data() ), 400 );
		}

		$ai_response = isset( $response['answer'] ) ? $response['answer'] : wp_json_encode( $response );
		$this->chat_history->save_message( 'ai', $ai_response, $this->session_id, array( 'provider' => $response['provider'] ?? 'unknown' ) );

		wp_send_json_success( $response );
	}

	// ------------------------------------------------------------------
	// Chat shortcode endpoint (legacy)
	// ------------------------------------------------------------------

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

		if ( $this->api_client->has_custom_backend() ) {
			$orders  = $this->data_fetcher->get_recent_orders( array( 'limit' => 10 ) );
			$payload = array(
				'question' => $question,
				'context'  => array( 'orders' => array_map( array( $this, 'format_order' ), $orders ) ),
			);
			$response = $this->api_client->post( 'api/chat', $payload );
			if ( is_wp_error( $response ) ) {
				wp_send_json_error( array( 'message' => $response->get_error_message(), 'data' => $response->get_error_data() ), 400 );
			}
			wp_send_json_success( $response );
		}

		$orders   = $this->data_fetcher->get_recent_orders( array( 'limit' => 10 ) );
		$messages = $this->build_openai_messages( $question, $orders );
		$result   = $this->api_client->send_openai_chat( $messages, array( 'model' => 'gpt-4o-mini' ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message(), 'data' => $result->get_error_data() ), 400 );
		}

		$content = isset( $result['choices'][0]['message']['content'] ) ? trim( (string) $result['choices'][0]['message']['content'] ) : '';
		if ( empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'The AI response was empty. Try again.', 'dataviz-ai-woocommerce' ), 'data' => $result ), 400 );
		}

		wp_send_json_success( array( 'message' => $content ) );
	}

	// ------------------------------------------------------------------
	// History endpoint
	// ------------------------------------------------------------------

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

		$session_id   = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
		$limit        = isset( $_GET['limit'] ) ? min( 200, max( 1, (int) $_GET['limit'] ) ) : 100;
		$days         = isset( $_GET['days'] ) ? min( 30, max( 1, (int) $_GET['days'] ) ) : 5;
		$all_sessions = isset( $_GET['all_sessions'] ) && filter_var( $_GET['all_sessions'], FILTER_VALIDATE_BOOLEAN );

		if ( $all_sessions || empty( $session_id ) ) {
			$history = $this->chat_history->get_recent_history( $limit, $days );
		} else {
			$history = $this->chat_history->get_session_history( $session_id, $limit, $days );
		}

		wp_send_json_success( array( 'history' => $history ) );
	}

	// ------------------------------------------------------------------
	// Feature request AJAX endpoint
	// ------------------------------------------------------------------

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

		$feature_requests = new Dataviz_AI_Feature_Requests();
		$request_id       = $feature_requests->submit_request( $entity_type, $user_id, $description );

		Dataviz_AI_Support_Requests::store_feature_request( $entity_type, $user_id, $description );

		if ( $request_id ) {
			wp_send_json_success( array(
				'message'    => sprintf(
					__( 'Feature request for "%1$s" has been submitted successfully! Request ID: #%2$d.', 'dataviz-ai-woocommerce' ),
					esc_html( $entity_type ),
					$request_id
				),
				'request_id' => $request_id,
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Failed to submit feature request. Please try again later.', 'dataviz-ai-woocommerce' ) ), 500 );
	}

	// ------------------------------------------------------------------
	// Inventory chart endpoint
	// ------------------------------------------------------------------

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

		$inventory_data = $this->data_fetcher->get_all_inventory_products( array( 'limit' => 100 ) );
		if ( isset( $inventory_data['error'] ) && $inventory_data['error'] ) {
			wp_send_json_error( array( 'message' => $inventory_data['message'] ), 400 );
		}

		wp_send_json_success( array( 'products' => $inventory_data['products'] ?? array() ) );
	}

	// ------------------------------------------------------------------
	// Debug intent endpoint
	// ------------------------------------------------------------------

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
			wp_send_json_success( array(
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
			) );
		}

		$parsed = $this->intent_pipeline->parse_and_validate( $question );
		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( array( 'message' => $parsed->get_error_message() ), 400 );
		}

		wp_send_json_success( $parsed );
	}

	// ------------------------------------------------------------------
	// Private helpers
	// ------------------------------------------------------------------

	protected function init_session() {
		$this->session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		if ( empty( $this->session_id ) ) {
			$session_key      = 'dataviz_ai_session_id';
			$this->session_id = get_user_meta( get_current_user_id(), $session_key, true );
			if ( empty( $this->session_id ) ) {
				$this->session_id = wp_generate_uuid4();
				update_user_meta( get_current_user_id(), $session_key, $this->session_id );
			}
		}
	}

	protected function format_order( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return array();
		}
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => (float) $item->get_total(),
				'product'  => $product ? array( 'id' => $product->get_id(), 'sku' => $product->get_sku(), 'price' => (float) $product->get_price() ) : null,
			);
		}
		return array(
			'id'          => $order->get_id(),
			'total'       => (float) $order->get_total(),
			'currency'    => $order->get_currency(),
			'status'      => $order->get_status(),
			'date'        => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'c' ) : null,
			'items'       => $items,
			'customer_id' => $order->get_customer_id(),
		);
	}

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
			array( 'role' => 'system', 'content' => __( 'You are a helpful WooCommerce analytics assistant. Answer clearly and concisely based on the provided store data.', 'dataviz-ai-woocommerce' ) ),
			array( 'role' => 'user', 'content' => sprintf( "%s\n\nRecent orders snapshot:\n%s", $question, $context_block ) ),
		);
	}
}
