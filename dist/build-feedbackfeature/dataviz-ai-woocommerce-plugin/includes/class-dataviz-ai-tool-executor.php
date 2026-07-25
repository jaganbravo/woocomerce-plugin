<?php
/**
 * Tool validation, execution, and result packaging.
 *
 * Consolidates the tool execution logic that was duplicated across three
 * code paths in the AJAX handler (streaming, non-streaming, hints).
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Tool_Executor {

	/**
	 * @var Dataviz_AI_Data_Fetcher
	 */
	protected $data_fetcher;

	/**
	 * @param Dataviz_AI_Data_Fetcher $data_fetcher Data fetcher instance.
	 */
	public function __construct( Dataviz_AI_Data_Fetcher $data_fetcher ) {
		$this->data_fetcher = $data_fetcher;
	}

	/**
	 * Execute all tool calls and return structured results.
	 *
	 * @param array $tool_calls       Tool calls from the execution engine.
	 * @param array $validated_intent  Optional. Validated intent for chart descriptor building.
	 * @return array {
	 *     @type array $results_for_prompt  Array of tool/arguments/result entries for LLM context.
	 *     @type array $frontend_data       Chart-relevant data for the frontend (tool_data).
	 *     @type array $operations_used     List of tool function names executed.
	 *     @type array $tool_messages       OpenAI-format tool result messages.
	 *     @type array $assistant_tool_calls OpenAI-format assistant tool_calls array.
	 * }
	 */
	public function execute_all( array $tool_calls, array $validated_intent = array() ) {
		$results_for_prompt    = array();
		$frontend_data         = array();
		$operations_used       = array();
		$tool_messages         = array();
		$assistant_tool_calls  = array();

		foreach ( $tool_calls as $tool_call ) {
			if ( ! isset( $tool_call['function']['name'] ) ) {
				continue;
			}

			$function_name  = $tool_call['function']['name'];
			$arguments_json = isset( $tool_call['function']['arguments'] ) ? $tool_call['function']['arguments'] : '{}';
			$arguments      = is_string( $arguments_json ) ? json_decode( $arguments_json, true ) : $arguments_json;
			$tool_call_id   = isset( $tool_call['id'] ) ? $tool_call['id'] : 'intent-' . uniqid();

			if ( ! is_array( $arguments ) ) {
				$arguments = array();
			}

			$assistant_tool_calls[] = array(
				'id'       => $tool_call_id,
				'type'     => 'function',
				'function' => array(
					'name'      => $function_name,
					'arguments' => is_string( $arguments_json ) ? $arguments_json : wp_json_encode( $arguments ),
				),
			);

			$validated_args = $this->validate_tool_arguments( $function_name, $arguments );

			if ( isset( $validated_args['error'] ) && true === $validated_args['error'] ) {
				$tool_result = array(
					'error'      => true,
					'error_type' => 'validation_error',
					'message'    => $validated_args['message'] ?? 'Validation failed',
				);
			} else {
				$tool_result = $this->safe_execute( $function_name, $validated_args );
			}

			if ( is_wp_error( $tool_result ) ) {
				$tool_result = array(
					'error'      => true,
					'error_type' => 'execution_error',
					'message'    => $tool_result->get_error_message(),
					'error_code' => $tool_result->get_error_code(),
				);
			}

			if ( is_array( $tool_result ) && isset( $tool_result['error'] ) && $tool_result['error'] === true ) {
				if ( isset( $tool_result['can_submit_request'] ) && $tool_result['can_submit_request'] === true && isset( $tool_result['requested_entity'] ) ) {
					$this->store_pending_feature_request( $tool_result['requested_entity'] );
				}
			}

			$this->collect_frontend_data( $frontend_data, $function_name, $validated_args, $tool_result );

			$results_for_prompt[] = array(
				'tool'      => $function_name,
				'arguments' => $validated_args,
				'result'    => $tool_result,
			);

			$tool_messages[] = array(
				'role'         => 'tool',
				'tool_call_id' => $tool_call_id,
				'content'      => wp_json_encode( $tool_result ),
			);

			$operations_used[] = $function_name;
		}

		if ( ! empty( $validated_intent ) && ! empty( $results_for_prompt ) ) {
			$chart_descriptor = Dataviz_AI_Chart_Descriptor::build( $validated_intent, $results_for_prompt );
			if ( $chart_descriptor ) {
				$frontend_data['chart_descriptor'] = $chart_descriptor;
			}
		}

		return array(
			'results_for_prompt'   => $results_for_prompt,
			'frontend_data'        => $frontend_data,
			'operations_used'      => $operations_used,
			'tool_messages'        => $tool_messages,
			'assistant_tool_calls' => $assistant_tool_calls,
		);
	}

	/**
	 * Execute a single tool with exception safety.
	 *
	 * @param string $function_name Tool name.
	 * @param array  $arguments     Validated arguments.
	 * @return array|WP_Error
	 */
	protected function safe_execute( $function_name, array $arguments ) {
		try {
			return $this->execute_tool( $function_name, $arguments );
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[Dataviz AI] Exception in tool %s: %s in %s:%d', $function_name, $e->getMessage(), $e->getFile(), $e->getLine() ) );
			}
			return array(
				'error'      => true,
				'error_type' => 'exception',
				'message'    => 'An error occurred while executing the tool: ' . $e->getMessage(),
			);
		} catch ( \Error $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( '[Dataviz AI] Fatal error in tool %s: %s in %s:%d', $function_name, $e->getMessage(), $e->getFile(), $e->getLine() ) );
			}
			return array(
				'error'      => true,
				'error_type' => 'fatal_error',
				'message'    => 'A fatal error occurred while executing the tool: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Dispatch a tool call to the appropriate data-fetcher method.
	 *
	 * @param string $function_name Tool function name.
	 * @param array  $arguments     Tool arguments.
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

			case 'get_recent_orders':
				$args = array( 'limit' => isset( $arguments['limit'] ) ? (int) $arguments['limit'] : 20 );
				if ( isset( $arguments['status'] ) ) {
					$args['status'] = sanitize_text_field( $arguments['status'] );
				}
				if ( isset( $arguments['date_from'] ) && isset( $arguments['date_to'] ) ) {
					$from_ts = strtotime( $arguments['date_from'] . ' 00:00:00' );
					$to_ts   = strtotime( $arguments['date_to'] . ' 23:59:59' );
					if ( $from_ts && $to_ts ) {
						$args['date_created'] = $from_ts . '...' . $to_ts;
					}
				}
				$orders = $this->data_fetcher->get_recent_orders( $args );
				$formatted = array_map( array( $this, 'format_order' ), $orders );
				if ( empty( $formatted ) ) {
					return array( 'orders' => array(), 'message' => 'No orders found matching the criteria.', 'query_params' => $args );
				}
				return $formatted;

			case 'get_top_products':
				$limit = isset( $arguments['limit'] ) ? min( 50, max( 1, (int) $arguments['limit'] ) ) : 10;
				return $this->data_fetcher->get_top_products( $limit );

			case 'get_customer_summary':
				return $this->data_fetcher->get_customer_summary();

			case 'get_customers':
				$limit = isset( $arguments['limit'] ) ? min( 100, max( 1, (int) $arguments['limit'] ) ) : 10;
				return $this->data_fetcher->get_customers( $limit );

			default:
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] Unknown tool requested: %s', $function_name ) );
				}
				return array(
					'error'           => true,
					'error_type'      => 'unknown_tool',
					'message'         => sprintf( __( 'The tool "%s" is not available.', 'dataviz-ai-woocommerce' ), esc_html( $function_name ) ),
					'requested_tool'  => $function_name,
					'available_tools' => array( 'get_woocommerce_data', 'get_order_statistics' ),
				);
		}
	}

	// ------------------------------------------------------------------
	// Entity query handlers
	// ------------------------------------------------------------------

	/**
	 * Execute flexible query based on entity type and query type.
	 *
	 * @param array $arguments Tool arguments.
	 * @return array|WP_Error
	 */
	protected function execute_flexible_query( array $arguments ) {
		$entity_type = isset( $arguments['entity_type'] ) ? sanitize_text_field( $arguments['entity_type'] ) : 'orders';
		$query_type  = isset( $arguments['query_type'] ) ? sanitize_text_field( $arguments['query_type'] ) : 'list';
		$filters     = isset( $arguments['filters'] ) && is_array( $arguments['filters'] ) ? $arguments['filters'] : array();

		$filters = $this->sanitize_filters( $filters );
		$original_entity_type = $entity_type;

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
				return $this->handle_stock_query( $filters, 'inventory' );
			default:
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( sprintf( '[Dataviz AI] Unsupported entity type requested: %s', $entity_type ) );
				}
				return array(
					'error'              => true,
					'error_type'         => 'unsupported_entity',
					'message'            => sprintf(
						__( 'The "%1$s" data type is not currently supported. Available data types are: %2$s.', 'dataviz-ai-woocommerce' ),
						esc_html( $entity_type ),
						implode( ', ', $supported_entities )
					),
					'requested_entity'   => $entity_type,
					'available_entities' => array_keys( $supported_entities ),
					'suggestion'         => sprintf( __( 'You can ask about: %s', 'dataviz-ai-woocommerce' ), implode( ', ', $supported_entities ) ),
					'can_submit_request' => true,
					'submission_prompt'  => sprintf(
						__( 'Would you like to request this feature? Just say "yes" and I\'ll submit a feature request for "%s" to the administrators.', 'dataviz-ai-woocommerce' ),
						esc_html( $entity_type )
					),
				);
		}
	}

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
				if ( isset( $filters['limit'] ) ) {
					$limit = (int) $filters['limit'];
					$args['limit'] = $limit === -1 ? -1 : min( 1000, max( 1, $limit ) );
				} else {
					$args['limit'] = 20;
				}
				if ( isset( $filters['status'] ) ) {
					$args['status'] = sanitize_text_field( $filters['status'] );
				}
				if ( isset( $filters['date_from'] ) && isset( $filters['date_to'] ) ) {
					$from_ts = strtotime( $filters['date_from'] . ' 00:00:00' );
					$to_ts   = strtotime( $filters['date_to'] . ' 23:59:59' );
					if ( $from_ts && $to_ts ) {
						$args['date_created'] = $from_ts . '...' . $to_ts;
					}
				}
				$orders = $this->data_fetcher->get_recent_orders( $args );
				$formatted = array_map( array( $this, 'format_order' ), $orders );
				if ( empty( $formatted ) ) {
					return array( 'orders' => array(), 'message' => 'No orders found matching the criteria.' );
				}
				$count_args = $args;
				unset( $count_args['offset'] );
				$total_matching = $this->data_fetcher->count_orders( $count_args );
				return array(
					'orders'           => $formatted,
					'total_matching'   => $total_matching,
				);
		}
	}

	protected function handle_products_query( $query_type, array $filters ) {
		if ( isset( $filters['limit'] ) ) {
			$limit = (int) $filters['limit'];
			$limit = $limit === -1 ? -1 : min( 500, max( 1, $limit ) );
		} else {
			$limit = 10;
		}

		if ( ! isset( $filters['category_id'] ) && ! empty( $filters['category_name'] ) ) {
			$cat_name = sanitize_text_field( $filters['category_name'] );
			if ( function_exists( 'get_term_by' ) ) {
				$term = get_term_by( 'name', $cat_name, 'product_cat' );
				if ( ! $term || is_wp_error( $term ) ) {
					$term = get_term_by( 'slug', sanitize_title( $cat_name ), 'product_cat' );
				}
				if ( $term && ! is_wp_error( $term ) ) {
					$filters['category_id'] = (int) $term->term_id;
				}
			}
		}

		switch ( $query_type ) {
			case 'list':
			default:
				if ( isset( $filters['category_id'] ) ) {
					$category_limit = $limit === -1 ? 500 : $limit;
					return $this->data_fetcher->get_products_by_category( (int) $filters['category_id'], $category_limit );
				} else {
					if ( $limit === -1 || $limit > 50 ) {
						return $this->data_fetcher->get_all_products( $limit );
					} else {
						return $this->data_fetcher->get_top_products( $limit );
					}
				}
			case 'statistics':
				$stats_limit = $limit === -1 ? 100 : $limit;
				return $this->data_fetcher->get_top_products( $stats_limit );
		}
	}

	protected function handle_customers_query( $query_type, array $filters ) {
		switch ( $query_type ) {
			case 'statistics':
				$has_date_filters  = isset( $filters['date_from'] ) || isset( $filters['date_to'] );
				$wants_top_spend   = isset( $filters['sort_by'] ) && $filters['sort_by'] === 'total_spent';
				$group_by_customer = isset( $filters['group_by'] ) && $filters['group_by'] === 'customer';

				if ( $has_date_filters || $wants_top_spend || $group_by_customer ) {
					return $this->data_fetcher->get_customer_statistics( $filters );
				}
				return $this->data_fetcher->get_customer_summary();
			case 'list':
			default:
				$limit = isset( $filters['limit'] ) ? min( 100, max( 1, (int) $filters['limit'] ) ) : 10;
				return $this->data_fetcher->get_customers( $limit );
		}
	}

	protected function handle_categories_query( array $filters ) {
		return $this->data_fetcher->get_product_categories();
	}

	protected function handle_tags_query( array $filters ) {
		return $this->data_fetcher->get_product_tags();
	}

	protected function handle_coupons_query( $query_type, array $filters ) {
		$query_type = (string) $query_type;
		if ( $query_type === 'statistics' && ( isset( $filters['date_from'] ) || isset( $filters['date_to'] ) ) ) {
			return $this->data_fetcher->get_coupon_usage( $filters );
		}
		return $this->data_fetcher->get_coupons( $filters );
	}

	protected function handle_refunds_query( array $filters ) {
		return $this->data_fetcher->get_refunds( $filters );
	}

	protected function handle_stock_query( array $filters, $entity_type = 'stock' ) {
		$entity_lower = strtolower( $entity_type );

		if ( isset( $filters['stock_status'] ) && $filters['stock_status'] === 'outofstock' ) {
			return $this->data_fetcher->get_out_of_stock_products();
		}
		if ( strpos( $entity_lower, 'inventory' ) !== false ) {
			return $this->data_fetcher->get_all_inventory_products( $filters );
		}
		$threshold = isset( $filters['stock_threshold'] ) ? (int) $filters['stock_threshold'] : 10;
		return $this->data_fetcher->get_low_stock_products( $threshold );
	}

	// ------------------------------------------------------------------
	// Feature request handling
	// ------------------------------------------------------------------

	protected function handle_feature_request_submission( array $arguments ) {
		require_once DATAVIZ_AI_WC_PLUGIN_DIR . 'includes/class-dataviz-ai-feature-requests.php';

		$entity_type = isset( $arguments['entity_type'] ) ? sanitize_text_field( $arguments['entity_type'] ) : '';
		$description = isset( $arguments['description'] ) ? sanitize_textarea_field( $arguments['description'] ) : '';
		$user_id     = get_current_user_id();

		if ( empty( $entity_type ) ) {
			return array( 'error' => true, 'message' => __( 'Entity type is required to submit a feature request.', 'dataviz-ai-woocommerce' ) );
		}

		$feature_requests = new Dataviz_AI_Feature_Requests();

		global $wpdb;
		$table_name   = $wpdb->prefix . 'dataviz_ai_feature_requests';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
		if ( ! $table_exists ) {
			$feature_requests->create_table();
		}

		$request_id = $feature_requests->submit_request( $entity_type, $user_id, $description );

		if ( $request_id ) {
			$this->send_feature_request_email( $request_id, $entity_type, $user_id, $description );
			return array(
				'success'    => true,
				'message'    => sprintf(
					__( 'Feature request for "%1$s" has been submitted successfully! Request ID: #%2$d. The administrators have been notified.', 'dataviz-ai-woocommerce' ),
					esc_html( $entity_type ),
					$request_id
				),
				'request_id' => $request_id,
			);
		}

		return array( 'error' => true, 'message' => __( 'Failed to submit feature request. Please try again later.', 'dataviz-ai-woocommerce' ) );
	}

	protected function send_feature_request_email( $request_id, $entity_type, $user_id, $description = '' ) {
		$user       = $user_id > 0 ? get_userdata( $user_id ) : null;
		$user_email = $user ? $user->user_email : __( 'Guest', 'dataviz-ai-woocommerce' );
		$user_name  = $user ? $user->display_name : __( 'Guest User', 'dataviz-ai-woocommerce' );
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$subject = sprintf( __( '[%1$s] New Feature Request: %2$s', 'dataviz-ai-woocommerce' ), $site_name, ucfirst( $entity_type ) );

		$message  = sprintf( __( 'A new feature request has been submitted on %1$s:', 'dataviz-ai-woocommerce' ), $site_name ) . "\n\n";
		$message .= sprintf( __( 'Request ID: #%d', 'dataviz-ai-woocommerce' ), $request_id ) . "\n";
		$message .= sprintf( __( 'Feature Requested: %s', 'dataviz-ai-woocommerce' ), ucfirst( $entity_type ) ) . "\n";
		$message .= sprintf( __( 'Requested By: %s (%s)', 'dataviz-ai-woocommerce' ), $user_name, $user_email ) . "\n";
		$message .= sprintf( __( 'Date: %s', 'dataviz-ai-woocommerce' ), current_time( 'mysql' ) ) . "\n";
		if ( ! empty( $description ) ) {
			$message .= "\n" . __( 'Description:', 'dataviz-ai-woocommerce' ) . "\n" . $description . "\n";
		}
		$message .= "\n" . sprintf( __( 'View all feature requests: %s', 'dataviz-ai-woocommerce' ), admin_url( 'admin.php?page=dataviz-ai-feature-requests' ) ) . "\n";

		$admins = get_users( array( 'role' => 'administrator' ) );
		foreach ( $admins as $admin ) {
			wp_mail( $admin->user_email, $subject, $message, array( 'Content-Type: text/plain; charset=UTF-8', 'From: ' . $site_name . ' <' . $admin_email . '>' ) );
		}
	}

	/**
	 * Store pending feature request entity_type in transient (called from session context).
	 *
	 * @param string $entity_type Requested entity keyword.
	 * @return void
	 */
	protected function store_pending_feature_request( $entity_type ) {
		// This requires a session_id. When used from the orchestrator, the orchestrator
		// passes the session_id via set_session_id().
		if ( ! empty( $this->session_id ) ) {
			$transient_key = 'dataviz_ai_pending_request_' . md5( $this->session_id );
			set_transient( $transient_key, (string) $entity_type, HOUR_IN_SECONDS );
		}
	}

	/**
	 * @var string Current session ID (set by orchestrator).
	 */
	protected $session_id = '';

	/**
	 * @param string $session_id Session ID.
	 * @return void
	 */
	public function set_session_id( $session_id ) {
		$this->session_id = (string) $session_id;
	}

	// ------------------------------------------------------------------
	// Validation & sanitization
	// ------------------------------------------------------------------

	protected function validate_tool_arguments( $function_name, array $arguments ) {
		$valid_tools = array( 'get_woocommerce_data', 'get_order_statistics', 'get_top_products', 'get_customers', 'get_customer_summary', 'submit_feature_request', 'get_recent_orders' );
		if ( ! in_array( $function_name, $valid_tools, true ) ) {
			return array( 'error' => true, 'error_type' => 'invalid_tool', 'message' => 'Invalid tool: ' . $function_name );
		}

		if ( isset( $arguments['entity_type'] ) ) {
			$arguments['entity_type'] = $this->validate_and_normalize_entity_type( $arguments['entity_type'] );
		}
		if ( isset( $arguments['query_type'] ) ) {
			$valid_types = array( 'list', 'statistics', 'sample', 'by_period' );
			if ( ! in_array( $arguments['query_type'], $valid_types, true ) ) {
				$arguments['query_type'] = 'list';
			}
		}
		if ( isset( $arguments['filters'] ) && is_array( $arguments['filters'] ) ) {
			$arguments['filters'] = $this->sanitize_filters( $arguments['filters'] );
		}
		if ( isset( $arguments['limit'] ) ) {
			$arguments['limit'] = max( 1, min( 1000, (int) $arguments['limit'] ) );
		}
		if ( isset( $arguments['filters']['limit'] ) ) {
			$arguments['filters']['limit'] = max( -1, min( 1000, (int) $arguments['filters']['limit'] ) );
		}
		return $arguments;
	}

	protected function validate_and_normalize_entity_type( $entity_type ) {
		$normalized = strtolower( trim( $entity_type ) );
		$map = array( 'product' => 'products', 'order' => 'orders', 'customer' => 'customers', 'category' => 'categories', 'tag' => 'tags', 'coupon' => 'coupons', 'refund' => 'refunds' );
		if ( isset( $map[ $normalized ] ) ) {
			$normalized = $map[ $normalized ];
		}
		return Dataviz_AI_Intent_Classifier::normalize_entity_type( $normalized );
	}

	protected function sanitize_filters( array $filters ) {
		$sanitized = array();
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
		if ( isset( $filters['stock_status'] ) ) {
			$stock_status = strtolower( sanitize_text_field( $filters['stock_status'] ) );
			if ( in_array( $stock_status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
				$sanitized['stock_status'] = $stock_status;
			}
		}
		if ( isset( $filters['category_id'] ) ) {
			$sanitized['category_id'] = max( 0, (int) $filters['category_id'] );
		}
		if ( isset( $filters['category_name'] ) ) {
			$sanitized['category_name'] = sanitize_text_field( $filters['category_name'] );
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
		if ( isset( $filters['sort_by'] ) ) {
			$sort_by = sanitize_text_field( $filters['sort_by'] );
			if ( in_array( $sort_by, array( 'total_spent', 'order_count' ), true ) ) {
				$sanitized['sort_by'] = $sort_by;
			}
		}
		if ( isset( $filters['group_by'] ) ) {
			$group_by = sanitize_text_field( $filters['group_by'] );
			if ( in_array( $group_by, array( 'customer', 'category' ), true ) ) {
				$sanitized['group_by'] = $group_by;
			}
		}
		if ( isset( $filters['min_orders'] ) ) {
			$sanitized['min_orders'] = max( 0, (int) $filters['min_orders'] );
		}
		return $sanitized;
	}

	// ------------------------------------------------------------------
	// Frontend data collection (for chart rendering)
	// ------------------------------------------------------------------

	protected function collect_frontend_data( array &$frontend_data, $function_name, $arguments, $tool_result ) {
		if ( ! is_array( $tool_result ) || isset( $tool_result['error'] ) ) {
			return;
		}

		$entity_type = strtolower( $arguments['entity_type'] ?? '' );
		$query_type  = $arguments['query_type'] ?? '';

		if ( $function_name === 'get_woocommerce_data' || $function_name === 'get_order_statistics' ) {
			if ( in_array( $entity_type, array( 'inventory', 'stock' ), true ) ) {
				$frontend_data['inventory'] = $tool_result;
			} elseif ( $entity_type === 'orders' && $query_type === 'by_period' && is_array( $tool_result ) ) {
				$frontend_data['orders_by_period'] = $tool_result;
			} elseif ( $entity_type === 'orders' && $query_type === 'list' ) {
				if ( isset( $tool_result['orders'] ) && is_array( $tool_result['orders'] ) ) {
					$frontend_data['orders'] = $tool_result['orders'];
				} elseif ( is_array( $tool_result ) && ! empty( $tool_result ) && isset( $tool_result[0]['id'] ) ) {
					$frontend_data['orders'] = $tool_result;
				}
			}

			$has_stats = ( $entity_type === 'orders' && $query_type === 'statistics' ) || $function_name === 'get_order_statistics';
			if ( $has_stats ) {
				$order_stats = array( 'summary' => isset( $tool_result['summary'] ) ? $tool_result['summary'] : array() );
				if ( ! empty( $tool_result['status_breakdown'] ) && is_array( $tool_result['status_breakdown'] ) ) {
					$order_stats['status_breakdown'] = $tool_result['status_breakdown'];
				}
				if ( ! empty( $tool_result['category_breakdown'] ) && is_array( $tool_result['category_breakdown'] ) ) {
					$order_stats['category_breakdown'] = $tool_result['category_breakdown'];
				}
				if ( isset( $order_stats['status_breakdown'] ) || isset( $order_stats['category_breakdown'] ) ) {
					$frontend_data['order_statistics'] = $order_stats;
					if ( isset( $order_stats['status_breakdown'] ) ) {
						$orders_for_chart = array();
						foreach ( $order_stats['status_breakdown'] as $sd ) {
							for ( $i = 0; $i < ( $sd['count'] ?? 0 ); $i++ ) {
								$orders_for_chart[] = array(
									'id'     => 'stat-' . ( $sd['status'] ?? 'unknown' ) . '-' . $i,
									'status' => $sd['status'] ?? 'unknown',
									'total'  => isset( $sd['revenue'], $sd['count'] ) && $sd['count'] > 0 ? (float) $sd['revenue'] / $sd['count'] : 0,
								);
							}
						}
						if ( ! empty( $orders_for_chart ) ) {
							$frontend_data['orders'] = $orders_for_chart;
						}
					}
				}
			}
		}
	}

	// ------------------------------------------------------------------
	// Order formatting helper
	// ------------------------------------------------------------------

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
}
