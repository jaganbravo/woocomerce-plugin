<?php
/**
 * WP-Cron handler for scheduled email digests.
 *
 * Hooks into a custom cron event, queries for due digests, generates
 * content via Digest_Generator, renders via Email_Template, and sends
 * with wp_mail().
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Digest_Cron {

	const CRON_HOOK     = 'dataviz_ai_process_email_digests';
	const CRON_INTERVAL = 'dataviz_ai_every_15_min';

	/**
	 * @var Dataviz_AI_Digest_Generator
	 */
	private $generator;

	public function __construct( Dataviz_AI_Digest_Generator $generator ) {
		$this->generator = $generator;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
		add_action( self::CRON_HOOK, array( $this, 'process_due_digests' ) );
	}

	/**
	 * Register a custom 15-minute cron interval.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_interval( $schedules ) {
		if ( ! isset( $schedules[ self::CRON_INTERVAL ] ) ) {
			$schedules[ self::CRON_INTERVAL ] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes', 'dataviz-ai-woocommerce' ),
			);
		}
		return $schedules;
	}

	/**
	 * Schedule the recurring cron event if not already scheduled.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_INTERVAL, self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the cron event (called on deactivation).
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Process all digests that are due.
	 */
	public function process_due_digests() {
		$digests = Dataviz_AI_Email_Digests::get_due_digests();

		if ( empty( $digests ) ) {
			return;
		}

		foreach ( $digests as $digest ) {
			$this->send_digest( $digest );
		}
	}

	/**
	 * Generate and send a single digest email.
	 *
	 * @param object $digest Digest row.
	 */
	private function send_digest( $digest ) {
		$data = $this->generator->build( $digest );
		$html = Dataviz_AI_Digest_Email_Template::render( $data );

		$recipients = $this->resolve_recipients( $digest );
		if ( empty( $recipients ) ) {
			$this->log( sprintf( 'Digest #%d has no valid recipients — skipping.', $digest->id ) );
			Dataviz_AI_Email_Digests::mark_sent( $digest->id );
			return;
		}

		$subject = sprintf(
			/* translators: 1: digest name 2: site name */
			__( '[%2$s] %1$s', 'dataviz-ai-woocommerce' ),
			$digest->digest_name,
			get_bloginfo( 'name' )
		);

		$headers    = Dataviz_AI_Digest_Mailer::default_headers();
		$sent_count = 0;

		foreach ( $recipients as $email ) {
			$result = Dataviz_AI_Digest_Mailer::send_html( $email, $subject, $html, $headers );
			if ( true === $result ) {
				$sent_count++;
			} else {
				$err = is_wp_error( $result ) ? $result->get_error_message() : 'unknown';
				$this->log( sprintf( 'Failed to send digest #%d to %s: %s', $digest->id, $email, $err ) );
			}
		}

		if ( $sent_count > 0 ) {
			Dataviz_AI_Email_Digests::mark_sent( $digest->id );
			$this->log( sprintf( 'Digest #%d: wp_mail accepted for %d/%d recipient(s).', $digest->id, $sent_count, count( $recipients ) ) );
		} else {
			$this->log( sprintf( 'Digest #%d: no emails delivered — next_run not advanced (will retry).', $digest->id ) );
		}
	}

	/**
	 * Resolve recipients for a digest — stored emails + the creating user's email.
	 *
	 * @param object $digest Digest row.
	 * @return array Valid email addresses.
	 */
	private function resolve_recipients( $digest ) {
		$emails = is_array( $digest->recipients ) ? $digest->recipients : array();

		if ( $digest->user_id ) {
			$user = get_user_by( 'id', $digest->user_id );
			if ( $user && ! in_array( $user->user_email, $emails, true ) ) {
				$emails[] = $user->user_email;
			}
		}

		return array_filter( array_unique( $emails ), 'is_email' );
	}

	/**
	 * @param string $message Log message.
	 */
	private function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[Dataviz AI Digest] ' . $message );
		}
	}
}
