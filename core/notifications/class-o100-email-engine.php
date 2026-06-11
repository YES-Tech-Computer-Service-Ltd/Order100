<?php
/**
 * Order100 Email Engine
 * Intercepts wp_mail to inject unsubscribe footers and RFC 8058 headers for marketing emails.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Email_Engine {

	public static function init() {
		// Hook into wp_mail at priority 10 before PHPMailer processes the arguments
		add_filter( 'wp_mail', [ __CLASS__, 'intercept_wp_mail' ], 10, 1 );

		// Log sent/failed emails
		add_action( 'wp_mail_succeeded', [ __CLASS__, 'log_email_success' ] );
		add_action( 'wp_mail_failed', [ __CLASS__, 'log_email_failed' ] );
	}

	public static function log_email_success( $mail_data ) {
		if ( ! class_exists( 'O100_Notification_Log' ) ) {
			return;
		}
		$to = is_array( $mail_data['to'] ) ? implode( ',', $mail_data['to'] ) : $mail_data['to'];
		$subject = isset( $mail_data['subject'] ) ? $mail_data['subject'] : '';
		O100_Notification_Log::log( 'email', $to, $subject, 'sent', 'OK' );
	}

	public static function log_email_failed( $error ) {
		if ( ! class_exists( 'O100_Notification_Log' ) ) {
			return;
		}
		$mail_data = $error->get_error_data( 'wp_mail_failed' );
		$to = '';
		if ( is_array( $mail_data ) && isset( $mail_data['to'] ) ) {
			$to = is_array( $mail_data['to'] ) ? implode( ',', $mail_data['to'] ) : $mail_data['to'];
		}
		$subject = is_array( $mail_data ) && isset( $mail_data['subject'] ) ? $mail_data['subject'] : '';
		O100_Notification_Log::log( 'email', $to, $subject, 'failed', $error->get_error_message() );
	}

	public static function intercept_wp_mail( $args ) {
		$to = isset( $args['to'] ) ? $args['to'] : '';
		if ( empty( $to ) ) {
			return $args;
		}

		// Ensure $to is a single string for simpler processing, or take the first email if it's an array
		$email = is_array( $to ) ? $to[0] : $to;
		// Clean out names (e.g. "John Doe <john@doe.com>")
		if ( preg_match( '/<([^>]+)>/', $email, $matches ) ) {
			$email = $matches[1];
		}
		$email = sanitize_email( $email );

		if ( empty( $email ) ) {
			return $args;
		}

		$headers = isset( $args['headers'] ) ? $args['headers'] : [];
		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		}

		$is_marketing = false;
		$is_transactional = false;
		$new_headers = [];

		// Scan existing headers for our custom flags
		foreach ( $headers as $header ) {
			if ( stripos( $header, 'X-O100-Mail-Type: Marketing' ) !== false ) {
				$is_marketing = true;
			} elseif ( stripos( $header, 'X-O100-Mail-Type: Transactional' ) !== false ) {
				$is_transactional = true;
			} else {
				$new_headers[] = $header; // Keep original headers
			}
		}

		// If it is explicitly transactional, DO NOT inject anything
		if ( $is_transactional ) {
			$args['headers'] = $new_headers;
			return $args;
		}

		// Check global settings
		$enable_optin = get_option( 'o100_crm_enable_optin', 0 );
		$one_click_unsubscribe = get_option( 'o100_crm_one_click_unsubscribe', 0 );

		// If marketing flag is present OR if we want to force unsubscribe links generally 
		// (For safety, we ONLY inject if X-O100-Mail-Type: Marketing is set)
		if ( ! $is_marketing ) {
			$args['headers'] = $new_headers;
			return $args; // Not a marketing email, leave it alone
		}

		$token = wp_hash( $email . 'o100_unsub_salt' );
		$unsub_url = esc_url_raw( add_query_arg( [ 'o100_crm_unsub' => urlencode( $email ), 'token' => $token ], home_url( '/' ) ) );
		
		// 1. Inject RFC 8058 One-Click Headers
		if ( $one_click_unsubscribe ) {
			$new_headers[] = 'List-Unsubscribe: <' . $unsub_url . '>';
			$new_headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}
		
		$args['headers'] = $new_headers;

		// 2. Scan body for {{unsubscribe_link}} or {{unsubscribe_url}}
		$message = isset( $args['message'] ) ? $args['message'] : '';
		
		// Determine content type (HTML or Plain)
		$is_html = false;
		foreach ( $new_headers as $h ) {
			if ( stripos( $h, 'content-type: text/html' ) !== false ) {
				$is_html = true;
				break;
			}
		}
		if ( ! $is_html && isset( $args['message'] ) && ( strpos( $args['message'], '<html' ) !== false || strpos( $args['message'], '<body' ) !== false ) ) {
			$is_html = true;
		}

		$has_placeholder = ( strpos( $message, '{{unsubscribe_link}}' ) !== false || strpos( $message, '{{unsubscribe_url}}' ) !== false );

		if ( $has_placeholder ) {
			// Replace placeholder with actual link
			$message = str_replace( '{{unsubscribe_url}}', $unsub_url, $message );
			if ( $is_html ) {
				$message = str_replace( '{{unsubscribe_link}}', '<a href="' . esc_attr( $unsub_url ) . '">Unsubscribe</a>', $message );
			} else {
				$message = str_replace( '{{unsubscribe_link}}', 'Unsubscribe here: ' . $unsub_url, $message );
			}
		} else {
			// Missing placeholder in Marketing email! Auto-append fallback footer.
			if ( $is_html ) {
				$footer = '
				<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #64748b; font-family: sans-serif;">
					You are receiving this email because you opted in to marketing communications from ' . esc_html( get_bloginfo( 'name' ) ) . '.<br><br>
					<a href="' . esc_attr( $unsub_url ) . '" style="color: #F59322; text-decoration: underline;">Click here to unsubscribe</a>
				</div>';
				
				// Try to inject before </body>
				if ( stripos( $message, '</body>' ) !== false ) {
					$message = str_ireplace( '</body>', $footer . "\n</body>", $message );
				} else {
					$message .= "\n" . $footer;
				}
			} else {
				// Plain text fallback
				$message .= "\n\n---\nTo unsubscribe from our marketing emails, please visit: " . $unsub_url;
			}
		}

		$args['message'] = $message;

		return $args;
	}
}
