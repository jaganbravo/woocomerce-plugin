<?php
/**
 * Human-readable "interpreted query" line from validated intent (trust / transparency).
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a short sentence for chat UI, e.g. “Interpreting your question as: …”.
 */
class Dataviz_AI_Intent_Query_Summary {

	/**
	 * Non-data chat: clarify no DB query ran.
	 *
	 * @return string
	 */
	public static function conversational_preamble() {
		return __( 'Answering conversationally (no WooCommerce data query was run).', 'dataviz-ai-woocommerce' );
	}

	/**
	 * Custom remote backend answer.
	 *
	 * @return string
	 */
	public static function custom_backend_preamble() {
		return __( 'Answer via your configured Dataviz backend (your question and store context were sent to that service).', 'dataviz-ai-woocommerce' );
	}

	/**
	 * Feature-request confirmation flow.
	 *
	 * @return string
	 */
	public static function feature_request_confirmation_preamble() {
		return __( 'Recording your feature request confirmation.', 'dataviz-ai-woocommerce' );
	}

	/**
	 * One-line summary from validated intent (requires_data true).
	 *
	 * @param array $intent Validated intent array (may include _question — stripped).
	 * @return string Empty if not a data intent.
	 */
	public static function from_intent( array $intent ) {
		$intent = self::strip_internal_keys( $intent );

		if ( empty( $intent['requires_data'] ) ) {
			return '';
		}

		$entity   = isset( $intent['entity'] ) ? self::humanize_token( (string) $intent['entity'] ) : '';
		$operation = isset( $intent['operation'] ) ? sanitize_key( (string) $intent['operation'] ) : '';
		$op_label = self::operation_label( $operation );

		$filters = isset( $intent['filters'] ) && is_array( $intent['filters'] ) ? $intent['filters'] : array();
		$period  = self::describe_date_filters( $filters );

		$status = '';
		if ( ! empty( $filters['status'] ) ) {
			$status = sprintf(
				/* translators: %s: WooCommerce order status slug or label */
				__( 'status “%s”', 'dataviz-ai-woocommerce' ),
				sanitize_text_field( (string) $filters['status'] )
			);
		}

		$metric_phrase = '';
		if ( ! empty( $intent['metrics'] ) && is_array( $intent['metrics'] ) ) {
			$metrics = array();
			foreach ( $intent['metrics'] as $m ) {
				$metrics[] = self::humanize_token( (string) $m );
			}
			$metrics       = array_filter( array_map( 'trim', $metrics ) );
			$metric_phrase = implode( ', ', $metrics );
		}

		$chunks = array_filter( array( $metric_phrase, $entity, $op_label, $period, $status ) );
		if ( empty( $chunks ) ) {
			return __( 'Interpreting your question as: WooCommerce data analysis based on your question.', 'dataviz-ai-woocommerce' );
		}

		/* translators: %s: comma-separated phrases (metrics, entity, operation, dates, status) */
		return sprintf( __( 'Interpreting your question as: %s.', 'dataviz-ai-woocommerce' ), implode( ', ', $chunks ) );
	}

	/**
	 * @param array $intent Intent array.
	 * @return array
	 */
	private static function strip_internal_keys( array $intent ) {
		foreach ( array_keys( $intent ) as $k ) {
			if ( is_string( $k ) && strlen( $k ) > 0 && '_' === $k[0] ) {
				unset( $intent[ $k ] );
			}
		}
		return $intent;
	}

	/**
	 * @param string $token Snake case or single word.
	 * @return string
	 */
	private static function humanize_token( $token ) {
		$token = strtolower( (string) $token );
		$token = str_replace( array( '-', '_' ), ' ', $token );

		return trim( $token );
	}

	/**
	 * @param string $operation Intent operation key.
	 * @return string
	 */
	private static function operation_label( $operation ) {
		$map = array(
			'list'       => __( 'listing', 'dataviz-ai-woocommerce' ),
			'statistics' => __( 'summary statistics', 'dataviz-ai-woocommerce' ),
		);

		if ( isset( $map[ $operation ] ) ) {
			return $map[ $operation ];
		}

		return self::humanize_token( $operation );
	}

	/**
	 * @param array $filters Intent filters array.
	 * @return string
	 */
	private static function describe_date_filters( array $filters ) {
		if ( empty( $filters['date_range'] ) || ! is_array( $filters['date_range'] ) ) {
			return '';
		}

		$dr     = $filters['date_range'];
		$preset = isset( $dr['preset'] ) ? sanitize_key( (string) $dr['preset'] ) : '';

		if ( $preset !== '' ) {
			if ( preg_match( '/^last_(\d+)_days$/', $preset, $m ) ) {
				return sprintf(
					/* translators: %d: number of days */
					__( 'last %d days', 'dataviz-ai-woocommerce' ),
					(int) $m[1]
				);
			}
			if ( preg_match( '/^last_(\d+)_weeks$/', $preset, $m ) ) {
				return sprintf(
					/* translators: %d: number of weeks */
					__( 'last %d weeks', 'dataviz-ai-woocommerce' ),
					(int) $m[1]
				);
			}
			if ( preg_match( '/^last_(\d+)_months$/', $preset, $m ) ) {
				return sprintf(
					/* translators: %d: number of months */
					__( 'last %d months', 'dataviz-ai-woocommerce' ),
					(int) $m[1]
				);
			}

			$labels = array(
				'today'        => __( 'today', 'dataviz-ai-woocommerce' ),
				'yesterday'    => __( 'yesterday', 'dataviz-ai-woocommerce' ),
				'this_week'    => __( 'this week', 'dataviz-ai-woocommerce' ),
				'last_week'    => __( 'last week', 'dataviz-ai-woocommerce' ),
				'this_month'   => __( 'this month', 'dataviz-ai-woocommerce' ),
				'last_month'   => __( 'last month', 'dataviz-ai-woocommerce' ),
				'this_year'    => __( 'this year', 'dataviz-ai-woocommerce' ),
				'last_year'    => __( 'last year', 'dataviz-ai-woocommerce' ),
				'last_quarter' => __( 'last quarter', 'dataviz-ai-woocommerce' ),
				'all_time'     => __( 'all time', 'dataviz-ai-woocommerce' ),
				'all'          => __( 'all time', 'dataviz-ai-woocommerce' ),
				'lifetime'     => __( 'all time', 'dataviz-ai-woocommerce' ),
			);

			if ( isset( $labels[ $preset ] ) ) {
				return $labels[ $preset ];
			}

			/* translators: %s: raw preset key */
			return sprintf( __( 'period %s', 'dataviz-ai-woocommerce' ), self::humanize_token( $preset ) );
		}

		$from = isset( $dr['from'] ) ? (string) $dr['from'] : '';
		$to   = isset( $dr['to'] ) ? (string) $dr['to'] : '';
		if ( $from !== '' && $to !== '' ) {
			return sprintf(
				/* translators: 1: start date, 2: end date */
				__( 'from %1$s to %2$s', 'dataviz-ai-woocommerce' ),
				$from,
				$to
			);
		}

		return '';
	}
}
