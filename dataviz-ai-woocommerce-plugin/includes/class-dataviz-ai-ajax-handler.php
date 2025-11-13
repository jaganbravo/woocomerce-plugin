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

		$orders    = $this->data_fetcher->get_recent_orders(
			array(
				'limit' => 20,
			)
		);
		$products  = $this->data_fetcher->get_top_products( 10 );
		$customers = $this->data_fetcher->get_customer_summary();

		$payload = array(
			'question'  => isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : __( 'Provide a quick performance summary.', 'dataviz-ai-woocommerce' ),
			'orders'    => array_map( array( $this, 'format_order' ), $orders ),
			'products'  => $products,
			'customers' => $customers,
		);

		$response = $this->api_client->post( 'api/woocommerce/ask', $payload );

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
}

