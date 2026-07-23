<?php
/**
 * Reservation Email Notifications
 *
 * Sends emails for reservation lifecycle events:
 * - New submission → Guest confirmation + Admin notification
 * - Status change → Guest notification (confirmed / cancelled)
 * - Reminder → Guest reminder before reservation time
 *
 * @package Order100
 * @since   1.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Reservation_Notify {

	/**
	 * Send notification for a new reservation submission
	 *
	 * @param int $reservation_id
	 */
	public static function send_new_reservation( $reservation_id ) {
		$resv = O100_Reservation_DB::get( $reservation_id );
		if ( ! $resv ) return;

		// 1. Email to guest — confirmation
		self::send_guest_confirmation( $resv );

		// 2. Email to admin — new booking alert
		self::send_admin_notification( $resv );
	}

	/**
	 * Send notification when reservation status changes
	 *
	 * @param int    $reservation_id
	 * @param string $new_status  'confirmed' or 'cancelled'
	 */
	public static function send_status_change( $reservation_id, $new_status ) {
		$resv = O100_Reservation_DB::get( $reservation_id );
		if ( ! $resv ) return;

		if ( $new_status === 'confirmed' ) {
			self::send_guest_confirmed( $resv );
		} elseif ( $new_status === 'cancelled' ) {
			self::send_guest_cancelled( $resv );
		}
	}

	/**
	 * Send reminder emails for upcoming reservations
	 *
	 * @param int $hours_before  Hours before reservation to send reminder
	 */
	public static function send_reminders( $hours_before = 2 ) {
		$pending = O100_Reservation_DB::get_pending_reminders( $hours_before );

		foreach ( $pending as $resv ) {
			self::send_guest_reminder( $resv );
			O100_Reservation_DB::mark_reminder_sent( $resv->id );
		}
	}

	// ─── Individual Email Methods ──────────────────────────────────────

	/**
	 * Guest: Your reservation has been received (pending)
	 */
	private static function send_guest_confirmation( $resv ) {
		$subject = sprintf(
			/* translators: %s: restaurant name */
			__( 'Reservation Received — %s', 'order100' ),
			get_bloginfo( 'name' )
		);

		$vars = self::get_template_vars( $resv );

		$body = self::build_email_body(
			__( 'Thank you for your reservation!', 'order100' ),
			sprintf(
				__( 'Hi %s, we have received your reservation request. Here are the details:', 'order100' ),
				esc_html( $resv->guest_name )
			),
			$vars,
			__( 'Your reservation is currently pending confirmation. We will notify you once it has been confirmed by our team.', 'order100' )
		);

		self::send( $resv->guest_email, $subject, $body );
	}

	/**
	 * Admin: New reservation received
	 */
	private static function send_admin_notification( $resv ) {
		$admin_email = get_option( 'admin_email' );
		$subject = sprintf(
			/* translators: %s: guest name */
			__( '[New Reservation] %s — %s', 'order100' ),
			esc_html( $resv->guest_name ),
			date_i18n( get_option( 'date_format' ), strtotime( $resv->reservation_date ) )
		);

		$vars = self::get_template_vars( $resv );

		$body = self::build_email_body(
			__( 'New Reservation Received', 'order100' ),
			__( 'A new reservation has been submitted on your website:', 'order100' ),
			$vars,
			sprintf(
				'<a href="%s" style="display:inline-block;padding:10px 24px;background:#F59322;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">%s</a>',
				esc_url( admin_url( 'admin.php?page=o100-reservations' ) ),
				__( 'View Reservations', 'order100' )
			)
		);

		self::send( $admin_email, $subject, $body );
	}

	/**
	 * Guest: Your reservation has been confirmed
	 */
	private static function send_guest_confirmed( $resv ) {
		$subject = sprintf(
			__( 'Reservation Confirmed — %s', 'order100' ),
			get_bloginfo( 'name' )
		);

		$vars = self::get_template_vars( $resv );

		$body = self::build_email_body(
			__( 'Your reservation is confirmed! ✅', 'order100' ),
			sprintf(
				__( 'Hi %s, great news! Your reservation has been confirmed. We look forward to seeing you.', 'order100' ),
				esc_html( $resv->guest_name )
			),
			$vars,
			__( 'If you need to make any changes, please contact us directly.', 'order100' )
		);

		self::send( $resv->guest_email, $subject, $body );
	}

	/**
	 * Guest: Your reservation has been cancelled
	 */
	private static function send_guest_cancelled( $resv ) {
		$subject = sprintf(
			__( 'Reservation Cancelled — %s', 'order100' ),
			get_bloginfo( 'name' )
		);

		$vars = self::get_template_vars( $resv );

		$body = self::build_email_body(
			__( 'Reservation Cancelled', 'order100' ),
			sprintf(
				__( 'Hi %s, your reservation has been cancelled. Here were the details:', 'order100' ),
				esc_html( $resv->guest_name )
			),
			$vars,
			__( 'If this was a mistake or you would like to rebook, please visit our website or contact us.', 'order100' )
		);

		self::send( $resv->guest_email, $subject, $body );
	}

	/**
	 * Guest: Reminder before reservation
	 */
	private static function send_guest_reminder( $resv ) {
		$subject = sprintf(
			__( 'Reminder: Your reservation today — %s', 'order100' ),
			get_bloginfo( 'name' )
		);

		$vars = self::get_template_vars( $resv );

		$token = wp_hash( 'o100_resv_' . $resv->id . '_' . $resv->guest_email );
		$confirm_url = add_query_arg( array(
			'o100_resv_action' => 'confirm',
			'id'               => $resv->id,
			'token'            => $token,
		), home_url( '/' ) );
		$cancel_url = add_query_arg( array(
			'o100_resv_action' => 'cancel',
			'id'               => $resv->id,
			'token'            => $token,
		), home_url( '/' ) );

		$buttons_html = '
		<div style="margin:25px 0 10px; text-align:center;">
			<a href="'.esc_url($confirm_url).'" style="display:inline-block; padding:12px 24px; background:#10b981; color:#fff; text-decoration:none; border-radius:6px; font-weight:600; margin-right:12px; font-size:15px;">✓ Confirm Reservation</a>
			<a href="'.esc_url($cancel_url).'" style="display:inline-block; padding:12px 24px; background:#f43f5e; color:#fff; text-decoration:none; border-radius:6px; font-weight:600; font-size:15px;">✗ Cancel Reservation</a>
		</div>';

		$body = self::build_email_body(
			__( 'See you soon! 🍽️', 'order100' ),
			sprintf(
				__( 'Hi %s, this is a friendly reminder about your upcoming reservation. Please confirm or cancel using the buttons below:', 'order100' ),
				esc_html( $resv->guest_name )
			) . $buttons_html,
			$vars,
			__( 'We look forward to welcoming you.', 'order100' )
		);

		self::send( $resv->guest_email, $subject, $body );
	}

	/**
	 * Guest: Post-dining review/marketing request
	 */
	public static function send_guest_review_request( $resv ) {
		$subject = sprintf(
			__( 'How was your experience at %s?', 'order100' ),
			get_bloginfo( 'name' )
		);

		$vars = array(); // Minimal vars

		$body = self::build_email_body(
			__( 'Thank you for dining with us! 🌟', 'order100' ),
			sprintf(
				__( 'Hi %s, we hope you enjoyed your recent visit. We would love to hear your feedback. Please consider leaving us a review to let us know how we did.', 'order100' ),
				esc_html( $resv->guest_name )
			),
			$vars,
			__( 'We look forward to serving you again soon.', 'order100' )
		);

		self::send( $resv->guest_email, $subject, $body );
	}

	// ─── Helpers ────────────────────────────────────────────────────────

	/**
	 * Build template variables from a reservation object
	 */
	private static function get_template_vars( $resv ) {
		$location = '';
		if ( $resv->location_id > 0 ) {
			$location = get_the_title( $resv->location_id );
		}

		$booking_labels = array(
			'table'        => __( 'Regular Table', 'order100' ),
			'private_room' => __( 'Private Room', 'order100' ),
		);

		return array(
			__( 'Name', 'order100' )         => esc_html( $resv->guest_name ),
			__( 'Email', 'order100' )        => esc_html( $resv->guest_email ),
			__( 'Phone', 'order100' )        => esc_html( $resv->guest_phone ),
			__( 'Party Size', 'order100' )   => intval( $resv->party_size ),
			__( 'Date', 'order100' )         => date_i18n( get_option( 'date_format' ), strtotime( $resv->reservation_date ) ),
			__( 'Time', 'order100' )         => date_i18n( 'g:i A', strtotime( $resv->reservation_time ) ),
			__( 'Seating', 'order100' )      => isset( $booking_labels[ $resv->booking_type ] ) ? $booking_labels[ $resv->booking_type ] : ucfirst( $resv->booking_type ),
			__( 'Location', 'order100' )     => $location ?: '—',
			__( 'Special Requests', 'order100' ) => ! empty( $resv->special_requests ) ? esc_html( $resv->special_requests ) : '—',
		);
	}

	/**
	 * Build a styled HTML email body
	 */
	private static function build_email_body( $heading, $intro, $vars, $footer_note = '' ) {
		$site_name = get_bloginfo( 'name' );
		$rows = '';
		foreach ( $vars as $label => $value ) {
			$rows .= sprintf(
				'<tr><td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;color:#64748b;font-weight:600;width:140px;">%s</td><td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;color:#1e293b;">%s</td></tr>',
				esc_html( $label ),
				esc_html( $value )
			);
		}

		return '
		<div style="max-width:600px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
			<div style="background:#1e293b;padding:24px;text-align:center;border-radius:8px 8px 0 0;">
				<h1 style="color:#fff;font-size:20px;margin:0;">' . esc_html( $site_name ) . '</h1>
			</div>
			<div style="background:#fff;padding:32px 24px;border:1px solid #e2e8f0;border-top:none;">
				<h2 style="color:#1e293b;font-size:22px;margin:0 0 12px;">' . $heading . '</h2>
				<p style="color:#475569;font-size:15px;line-height:1.6;margin:0 0 24px;">' . $intro . '</p>
				<table style="width:100%;border-collapse:collapse;background:#f8fafc;border-radius:6px;overflow:hidden;">' . $rows . '</table>
				' . ( $footer_note ? '<p style="color:#64748b;font-size:14px;line-height:1.5;margin:24px 0 0;padding:16px;background:#f8fafc;border-radius:6px;">' . $footer_note . '</p>' : '' ) . '
			</div>
			<div style="text-align:center;padding:16px;color:#94a3b8;font-size:12px;">
				&copy; ' . date('Y') . ' ' . esc_html( $site_name ) . '
			</div>
		</div>';
	}

	/**
	 * Send an HTML email via wp_mail
	 */
	private static function send( $to, $subject, $body ) {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		wp_mail( $to, $subject, $body, $headers );
	}
}

