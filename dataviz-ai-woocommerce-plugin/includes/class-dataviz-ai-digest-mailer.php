<?php
/**
 * Wraps wp_mail() for digest emails with error capture for admin feedback.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Digest_Mailer {

	/**
	 * Send HTML email; returns true or WP_Error with a useful message.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject line.
	 * @param string $html    HTML body.
	 * @param array  $headers Headers (optional).
	 * @return true|WP_Error
	 */
	public static function send_html( $to, $subject, $html, array $headers = array() ) {
		$captured = null;

		$on_failed = static function ( $wp_error ) use ( &$captured ) {
			if ( $wp_error instanceof WP_Error ) {
				$captured = $wp_error->get_error_message();
			}
		};

		add_action( 'wp_mail_failed', $on_failed, 10, 1 );

		$ok = wp_mail( $to, $subject, $html, $headers );

		remove_action( 'wp_mail_failed', $on_failed, 10 );

		if ( $ok ) {
			return true;
		}

		$msg = $captured
			? $captured
			: __( 'The server could not send email. On local or Docker installs, PHP mail() is usually disabled — install an SMTP plugin (e.g. WP Mail SMTP) or configure your host’s mail.', 'dataviz-ai-woocommerce' );

		// PHPMailer when PHP mail() is missing/broken (typical on Docker/local).
		if ( is_string( $msg ) && stripos( $msg, 'instantiate mail' ) !== false ) {
			$msg .= "\n\n" . __( 'What this means: WordPress tried to use PHP’s mail() function, but this server cannot run it.', 'dataviz-ai-woocommerce' )
				. ' ' . __( 'Fix: Install “WP Mail SMTP” (free), choose “Other SMTP”, and enter Gmail (app password), Mailtrap, SendGrid, or your host’s SMTP. Then digests will send like any other WordPress email.', 'dataviz-ai-woocommerce' );
		}

		return new WP_Error( 'dataviz_digest_mail_failed', $msg );
	}

	/**
	 * Default headers for digest HTML emails.
	 *
	 * @return array
	 */
	public static function default_headers() {
		return array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' <' . sanitize_email( get_option( 'admin_email' ) ) . '>',
		);
	}
}
