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
	 * Internal trace lines should be hidden in normal UX.
	 * Set DATAVIZ_AI_SHOW_INTENT_TRACE=true (or filter) to expose them.
	 *
	 * @return bool
	 */
	private static function should_expose_internal_trace() {
		$enabled = defined( 'DATAVIZ_AI_SHOW_INTENT_TRACE' ) && DATAVIZ_AI_SHOW_INTENT_TRACE;
		return (bool) apply_filters( 'dataviz_ai_show_intent_trace', $enabled );
	}

	/**
	 * Non-data chat: clarify no DB query ran.
	 *
	 * @return string
	 */
	public static function conversational_preamble() {
		if ( ! self::should_expose_internal_trace() ) {
			return '';
		}
		return __( 'Answering conversationally (no WooCommerce data query was run).', 'unmai-analytix-for-woocommerce' );
	}

	/**
	 * Custom remote backend answer.
	 *
	 * @return string
	 */
	public static function custom_backend_preamble() {
		if ( ! self::should_expose_internal_trace() ) {
			return '';
		}
		return __( 'Answer via your configured Dataviz backend (your question and store context were sent to that service).', 'unmai-analytix-for-woocommerce' );
	}

	/**
	 * Feature-request confirmation flow.
	 *
	 * @return string
	 */
	public static function feature_request_confirmation_preamble() {
		if ( ! self::should_expose_internal_trace() ) {
			return '';
		}
		return __( 'Recording your feature request confirmation.', 'unmai-analytix-for-woocommerce' );
	}

	/**
	 * One-line summary from validated intent (requires_data true).
	 *
	 * @param array $intent Validated intent array (may include _question — stripped).
	 * @return string Empty if not a data intent.
	 */
	public static function from_intent( array $intent ) {
		if ( ! self::should_expose_internal_trace() ) {
			return '';
		}

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
				__( 'status “%s”', 'unmai-analytix-for-woocommerce' ),
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
			return __( 'Interpreting your question as: WooCommerce data analysis based on your question.', 'unmai-analytix-for-woocommerce' );
		}

		/* translators: %s: comma-separated phrases (metrics, entity, operation, dates, status) */
		return sprintf( __( 'Interpreting your question as: %s.', 'unmai-analytix-for-woocommerce' ), implode( ', ', $chunks ) );
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
			'list'       => __( 'listing', 'unmai-analytix-for-woocommerce' ),
			'statistics' => __( 'summary statistics', 'unmai-analytix-for-woocommerce' ),
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
					__( 'last %d days', 'unmai-analytix-for-woocommerce' ),
					(int) $m[1]
				);
			}
			if ( preg_match( '/^last_(\d+)_weeks$/', $preset, $m ) ) {
				return sprintf(
					/* translators: %d: number of weeks */
					__( 'last %d weeks', 'unmai-analytix-for-woocommerce' ),
					(int) $m[1]
				);
			}
			if ( preg_match( '/^last_(\d+)_months$/', $preset, $m ) ) {
				return sprintf(
					/* translators: %d: number of months */
					__( 'last %d months', 'unmai-analytix-for-woocommerce' ),
					(int) $m[1]
				);
			}

			$labels = array(
				'today'        => __( 'today', 'unmai-analytix-for-woocommerce' ),
				'yesterday'    => __( 'yesterday', 'unmai-analytix-for-woocommerce' ),
				'this_week'    => __( 'this week', 'unmai-analytix-for-woocommerce' ),
				'last_week'    => __( 'last week', 'unmai-analytix-for-woocommerce' ),
				'this_month'   => __( 'this month', 'unmai-analytix-for-woocommerce' ),
				'last_month'   => __( 'last month', 'unmai-analytix-for-woocommerce' ),
				'this_year'    => __( 'this year', 'unmai-analytix-for-woocommerce' ),
				'last_year'    => __( 'last year', 'unmai-analytix-for-woocommerce' ),
				'last_quarter' => __( 'last quarter', 'unmai-analytix-for-woocommerce' ),
				'all_time'     => __( 'all time', 'unmai-analytix-for-woocommerce' ),
				'all'          => __( 'all time', 'unmai-analytix-for-woocommerce' ),
				'lifetime'     => __( 'all time', 'unmai-analytix-for-woocommerce' ),
			);

			if ( isset( $labels[ $preset ] ) ) {
				return $labels[ $preset ];
			}

			/* translators: %s: raw preset key */
			return sprintf( __( 'period %s', 'unmai-analytix-for-woocommerce' ), self::humanize_token( $preset ) );
		}

		$from = isset( $dr['from'] ) ? (string) $dr['from'] : '';
		$to   = isset( $dr['to'] ) ? (string) $dr['to'] : '';
		if ( $from !== '' && $to !== '' ) {
			return sprintf(
				/* translators: 1: start date, 2: end date */
				__( 'from %1$s to %2$s', 'unmai-analytix-for-woocommerce' ),
				$from,
				$to
			);
		}

		return '';
	}
}
