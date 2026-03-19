<?php
/**
 * Intent processing pipeline.
 *
 * Single entry point that replaces duplicated intent logic across the three
 * handler methods. Chains: guard checks -> LLM parse_intent -> Validator ->
 * Normalizer -> Execution Engine.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Intent_Pipeline {

	/**
	 * @var Dataviz_AI_API_Client
	 */
	protected $api_client;

	/**
	 * @param Dataviz_AI_API_Client $api_client API client.
	 */
	public function __construct( Dataviz_AI_API_Client $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Process a user question into a validated intent and tool calls.
	 *
	 * @param string $question User question.
	 * @return array {
	 *     @type array       $intent          Validated + normalized intent (or minimal array for feature requests).
	 *     @type array       $tool_calls      Tool call structures for the executor.
	 *     @type bool        $feature_request  True when the question routes to an unsupported-entity feature request.
	 *     @type string|null $feature_entity   Entity keyword for feature request context.
	 *     @type WP_Error|null $error          Non-null when the pipeline failed entirely.
	 *     @type string|null $error_reason     Reason string for intent-not-found responses.
	 * }
	 */
	public function process( $question ) {
		$result = array(
			'intent'          => null,
			'raw_intent'      => null,
			'tool_calls'      => array(),
			'feature_request' => false,
			'feature_entity'  => null,
			'error'           => null,
			'error_reason'    => null,
		);

		// Guard: comparison questions (unsupported).
		if ( Dataviz_AI_Intent_Normalizer::is_comparison_question( $question ) ) {
			return $this->feature_request_result( $result, 'comparisons' );
		}

		// Guard: cross-entity questions (unsupported).
		if ( Dataviz_AI_Intent_Normalizer::is_cross_entity_question( $question ) ) {
			return $this->feature_request_result( $result, 'cross_entity_analysis' );
		}

		// Guard: conversion rate with traffic data (unsupported).
		if ( Dataviz_AI_Intent_Normalizer::is_conversion_rate_question( $question ) ) {
			return $this->feature_request_result( $result, 'conversion_rate' );
		}

		// Guard: external data sources (unsupported).
		$unsupported_src = Dataviz_AI_Intent_Normalizer::get_unsupported_data_source( $question );
		if ( false !== $unsupported_src ) {
			return $this->feature_request_result( $result, $unsupported_src );
		}

		// Step 1: LLM intent parsing.
		$intent_parse = $this->api_client->parse_intent( $question );
		if ( is_wp_error( $intent_parse ) ) {
			if ( $intent_parse->get_error_code() === 'dataviz_ai_invalid_intent' ) {
				$result['error_reason'] = $intent_parse->get_error_message();
				return $result;
			}
			$result['error'] = $intent_parse;
			return $result;
		}

		$result['raw_intent'] = $intent_parse['raw'] ?? null;

		// Step 2: Validate via Intent_Validator.
		$validated = Dataviz_AI_Intent_Validator::validate(
			is_array( $intent_parse['intent'] ?? null ) ? $intent_parse['intent'] : array()
		);
		if ( is_wp_error( $validated ) || empty( $validated['requires_data'] ) ) {
			$result['error_reason'] = is_wp_error( $validated )
				? $validated->get_error_message()
				: 'requires_data=false for data question';
			return $result;
		}

		// Step 3: Normalize (dates + intent) via Intent_Normalizer.
		$validated = Dataviz_AI_Intent_Normalizer::normalize( $question, $validated );

		// Step 4: Build tool calls via Execution Engine.
		$tool_calls = Dataviz_AI_Execution_Engine::build_tool_calls( $validated );

		if ( empty( $tool_calls ) ) {
			$result['error_reason'] = 'Execution engine produced no tool calls.';
			return $result;
		}

		$result['intent']     = $validated;
		$result['tool_calls'] = $tool_calls;
		return $result;
	}

	/**
	 * Convenience: validate + normalize only (for the debug endpoint, no tool calls needed).
	 *
	 * @param string $question User question.
	 * @return array|WP_Error Validated + normalized intent, or WP_Error.
	 */
	public function parse_and_validate( $question ) {
		$intent_parse = $this->api_client->parse_intent( $question );
		if ( is_wp_error( $intent_parse ) ) {
			return $intent_parse;
		}

		$validated = Dataviz_AI_Intent_Validator::validate(
			is_array( $intent_parse['intent'] ?? null ) ? $intent_parse['intent'] : array()
		);
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$validated = Dataviz_AI_Intent_Normalizer::normalize( $question, $validated );

		return array(
			'validated_intent' => $validated,
			'raw'              => $intent_parse['raw'] ?? null,
			'model'            => $intent_parse['model'] ?? null,
		);
	}

	/**
	 * Build a feature-request tool call structure.
	 *
	 * @param string $requested_entity Entity keyword.
	 * @return array
	 */
	public static function build_feature_request_tool_call( $requested_entity ) {
		return array(
			'function' => array(
				'name'      => 'get_woocommerce_data',
				'arguments' => wp_json_encode( array(
					'entity_type' => (string) $requested_entity,
					'query_type'  => 'statistics',
					'filters'     => array(),
				) ),
			),
			'id' => 'intent-unsupported-' . uniqid(),
		);
	}

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	protected function feature_request_result( array $result, $entity ) {
		$result['feature_request'] = true;
		$result['feature_entity']  = $entity;
		$result['intent'] = array(
			'requires_data' => true,
			'entity'        => $entity,
			'operation'     => 'feature_request',
		);
		$result['tool_calls'] = array( self::build_feature_request_tool_call( $entity ) );
		return $result;
	}
}
