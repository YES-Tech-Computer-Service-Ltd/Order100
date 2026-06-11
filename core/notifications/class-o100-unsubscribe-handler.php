<?php
/**
 * Order100 Unsubscribe Handler
 * Handles both one-click POST (RFC 8058) and manual GET landing page.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Unsubscribe_Handler {

	public static function init() {
		add_action( 'template_redirect', [ __CLASS__, 'handle_request' ], 5 );
	}

	public static function handle_request() {
		if ( ! isset( $_GET['o100_crm_unsub'] ) || ! isset( $_GET['token'] ) ) {
			return; // Not an unsubscribe request
		}

		$email = sanitize_email( $_GET['o100_crm_unsub'] );
		$token = sanitize_text_field( $_GET['token'] );

		if ( empty( $email ) ) {
			wp_die( 'Invalid email address.', 'Unsubscribe Error', [ 'response' => 400 ] );
		}

		$expected_token = wp_hash( $email . 'o100_unsub_salt' );
		if ( ! hash_equals( $expected_token, $token ) ) {
			wp_die( 'Invalid or expired unsubscribe link.', 'Unsubscribe Error', [ 'response' => 403 ] );
		}

		// RFC 8058 One-Click POST Request or Form POST
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			self::execute_unsubscribe( $email );
			
			// If it's AJAX or silent request (from Gmail), return 200 OK
			if ( ! isset( $_POST['o100_manual_unsub'] ) ) {
				status_header( 200 );
				exit;
			}
			
			// If it's our manual form submission, render success page
			self::render_success_page();
			exit;
		}

		// GET Request - Render confirmation landing page
		self::render_confirmation_page( $email, $token );
		exit;
	}

	private static function execute_unsubscribe( $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'o100_customers';
		
		// Update CRM database
		$wpdb->update(
			$table,
			[ 'status' => 'unsubscribed', 'updated_at' => current_time( 'mysql' ) ],
			[ 'email' => $email ],
			[ '%s', '%s' ],
			[ '%s' ]
		);
	}

	private static function render_confirmation_page( $email, $token ) {
		$shop_name = get_bloginfo( 'name' );
		$submit_url = esc_url( add_query_arg( [ 'o100_crm_unsub' => urlencode( $email ), 'token' => $token ] ) );
		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>Unsubscribe - <?php echo esc_html( $shop_name ); ?></title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
				.card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); text-align: center; max-width: 400px; width: 90%; }
				.icon { width: 48px; height: 48px; margin: 0 auto 20px; color: #64748b; }
				h1 { font-size: 20px; font-weight: 600; color: #0f172a; margin: 0 0 12px; }
				p { font-size: 15px; color: #475569; margin: 0 0 24px; line-height: 1.5; }
				.btn { display: inline-block; background-color: #ef4444; color: white; padding: 12px 24px; border-radius: 8px; font-size: 15px; font-weight: 500; text-decoration: none; border: none; cursor: pointer; transition: background-color 0.2s; width: 100%; box-sizing: border-box; }
				.btn:hover { background-color: #dc2626; }
				.email-display { font-weight: 600; color: #0f172a; word-break: break-all; }
			</style>
		</head>
		<body>
			<div class="card">
				<svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
				<h1>Unsubscribe Confirmation</h1>
				<p>Are you sure you no longer wish to receive marketing emails from <strong><?php echo esc_html( $shop_name ); ?></strong>?</p>
				<p class="email-display"><?php echo esc_html( $email ); ?></p>
				
				<form method="POST" action="<?php echo $submit_url; ?>">
					<input type="hidden" name="o100_manual_unsub" value="1">
					<button type="submit" class="btn">Confirm Unsubscribe</button>
				</form>
			</div>
		</body>
		</html>
		<?php
	}

	private static function render_success_page() {
		$shop_url = function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ) : home_url();
		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>Unsubscribed Successfully</title>
			<meta http-equiv="refresh" content="3;url=<?php echo esc_url( $shop_url ); ?>">
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
				.card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); text-align: center; max-width: 400px; width: 90%; }
				.icon { width: 56px; height: 56px; margin: 0 auto 20px; color: #10b981; }
				h1 { font-size: 20px; font-weight: 600; color: #0f172a; margin: 0 0 12px; }
				p { font-size: 15px; color: #475569; margin: 0; line-height: 1.5; }
				.redirect { font-size: 13px; color: #64748b; margin-top: 24px; }
				.spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid #cbd5e1; border-top-color: #F59322; border-radius: 50%; animation: spin 1s linear infinite; vertical-align: middle; margin-right: 8px; }
				@keyframes spin { to { transform: rotate(360deg); } }
			</style>
		</head>
		<body>
			<div class="card">
				<svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
				<h1>Unsubscribed Successfully</h1>
				<p>You have been removed from our marketing mailing list. You will still receive essential order notifications.</p>
				<div class="redirect">
					<div class="spinner"></div> Redirecting to store...
				</div>
			</div>
			<script>
				setTimeout(function() {
					window.location.href = "<?php echo esc_js( esc_url_raw( $shop_url ) ); ?>";
				}, 3000);
			</script>
		</body>
		</html>
		<?php
	}
}
