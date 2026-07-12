<?php
/**
 * Order100 CRM Frontend Integration Engine
 *
 * Handles WooCommerce Checkout Opt-in injection, Order save interception,
 * and Double Opt-in confirmation logic.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Customers_Frontend {

	/**
	 * Initialize frontend hooks.
	 */
	public static function init() {
		// 1. Inject Checkout Checkbox
		if ( get_option( 'o100_crm_enable_optin', 1 ) ) {
			$location = get_option( 'o100_crm_optin_location', 'woocommerce_checkout_terms_and_conditions' );
			add_action( $location, [ __CLASS__, 'render_checkout_optin' ], 10 );
			
			// 2. Save Optin preference to Order Meta
			add_action( 'woocommerce_checkout_update_order_meta', [ __CLASS__, 'save_checkout_optin_meta' ], 10, 2 );
		}

		// 3. Double Opt-in Confirmation Endpoint listener
		add_action( 'init', [ __CLASS__, 'handle_double_optin_confirmation' ] );
	}

	/**
	 * Render the opt-in checkbox on WooCommerce checkout.
	 */
	public static function render_checkout_optin() {
		$label = get_option( 'o100_crm_optin_label', 'Subscribe to our newsletter for exclusive offers!' );
		$is_checked = get_option( 'o100_crm_optin_default', 1 ) ? 'checked' : '';
		
		echo '<p class="form-row o100-crm-optin-row" style="margin:8px 0;">';
		echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
		echo '<input type="checkbox" name="o100_crm_optin" id="o100_crm_optin" value="1" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" ' . $is_checked . ' />';
		echo '<span class="woocommerce-terms-and-conditions-checkbox-text">' . esc_html( $label ) . '</span>';
		echo '</label>';
		echo '</p>';
		
		?>
		<script>
		(function() {
			window.addEventListener('load', function() {
				setTimeout(function() {
					let log = [];
					log.push("CHECKOUT OPT-IN DIAGNOSTIC");
					log.push("URL: " + window.location.href);
					
					let input = document.getElementById('o100_crm_optin');
					log.push("Checkbox input exists: " + (!!input));
					if (input) {
						let parent = input.closest('.o100-crm-optin-row');
						log.push("Parent row exists: " + (!!parent));
						if (parent) {
							log.push("Parent row offsetHeight: " + parent.offsetHeight);
							log.push("Parent row offsetWidth: " + parent.offsetWidth);
							log.push("Parent row display style: " + window.getComputedStyle(parent).display);
							log.push("Parent row visibility style: " + window.getComputedStyle(parent).visibility);
							log.push("Parent row opacity style: " + window.getComputedStyle(parent).opacity);
							log.push("Parent row HTML: " + parent.outerHTML);
							
							// Trace ancestors to see if any parent is hidden
							let ancestor = parent.parentElement;
							while (ancestor && ancestor.tagName !== 'BODY') {
								let style = window.getComputedStyle(ancestor);
								let idAttr = ancestor.id ? ' ID: ' + ancestor.id : '';
								let classAttr = ancestor.className ? ' Class: ' + ancestor.className : '';
								if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0' || ancestor.offsetHeight === 0) {
									log.push(`Ancestor hidden: Tag: ${ancestor.tagName.toLowerCase()}${idAttr}${classAttr} (display: ${style.display}, visibility: ${style.visibility}, opacity: ${style.opacity}, height: ${ancestor.offsetHeight})`);
								}
								ancestor = ancestor.parentElement;
							}
						}
					}
					
					// Send log
					let xhr = new XMLHttpRequest();
					xhr.open('POST', '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>', true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.send('action=o100_crm_dom_diag_log&filename=checkout_diagnostic.txt&log=' + encodeURIComponent(log.join("\n")));
				}, 1000);
			});
		})();
		</script>
		<?php
	}

	/**
	 * Save the opt-in state to the order meta.
	 */
	public static function save_checkout_optin_meta( $order_id, $data ) {
		$optin = isset( $_POST['o100_crm_optin'] ) ? 1 : 0;
		update_post_meta( $order_id, '_o100_crm_optin_status', $optin );
	}

	/**
	 * Trigger Double Opt-in Email Process.
	 * Returns the final CRM status ('subscribed', 'pending', or 'unsubscribed').
	 */
	public static function handle_order_optin_logic( $order_id, $customer_id, $email ) {
		if ( ! get_option( 'o100_crm_enable_optin', 1 ) ) {
			return 'subscribed'; // If optin box is disabled globally, assume they are subscribed (legacy behavior).
		}

		$optin_checked = get_post_meta( $order_id, '_o100_crm_optin_status', true );
		if ( ! $optin_checked ) {
			return 'unsubscribed'; // Customer explicitly declined.
		}

		// Customer agreed. Check if Double Opt-in is required.
		if ( get_option( 'o100_crm_double_optin', 0 ) ) {
			// Generate Token
			$token = wp_generate_password( 32, false );
			update_post_meta( $order_id, '_o100_crm_optin_token', $token );
			
			// Also store it on the customer record for easy lookup
			global $wpdb;
			$tbl_customers = O100_Customers_DB::get_table_customers();
			$wpdb->update( $tbl_customers, [ 'status' => 'pending' ], [ 'id' => $customer_id ] );
			
			// Custom table might not have a token field natively. Let's use WP Options for a quick key-value store, or meta if they existed.
			// Instead of altering DB, we'll store tokens temporarily in wp_options or we lookup by order meta.
			set_transient( 'o100_optin_' . $token, $customer_id, 30 * DAY_IN_SECONDS );

			self::send_double_optin_email( $email, $token );
			return 'pending';
		}

		return 'subscribed';
	}

	/**
	 * Send the Double Opt-in Email.
	 */
	private static function send_double_optin_email( $email, $token ) {
		$subject = get_option( 'o100_crm_double_optin_subject', 'Please confirm your subscription' );
		$body = get_option( 'o100_crm_double_optin_body', '' );

		$confirm_link = add_query_arg( [
			'o100_action' => 'confirm_sub',
			'token'       => $token
		], home_url() );

		// Replace variable
		$body = str_replace( '{{confirm_link}}', esc_url( $confirm_link ), $body );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		wp_mail( $email, $subject, wpautop( $body ), $headers );
	}

	/**
	 * Endpoint listener to handle the URL callback from the email.
	 */
	public static function handle_double_optin_confirmation() {
		if ( isset( $_GET['o100_action'] ) && $_GET['o100_action'] === 'confirm_sub' && isset( $_GET['token'] ) ) {
			$token = sanitize_text_field( $_GET['token'] );
			
			$customer_id = get_transient( 'o100_optin_' . $token );
			
			if ( $customer_id ) {
				global $wpdb;
				$tbl_customers = O100_Customers_DB::get_table_customers();
				$wpdb->update( $tbl_customers, [ 'status' => 'subscribed' ], [ 'id' => $customer_id ] );
				
				delete_transient( 'o100_optin_' . $token );

				// Handle redirect or message
				$action = get_option( 'o100_crm_double_optin_action', 'message' );
				$val = get_option( 'o100_crm_double_optin_val', 'Thank you! Your subscription has been confirmed.' );

				if ( $action === 'redirect' && ! empty( $val ) ) {
					wp_redirect( esc_url_raw( $val ) );
					exit;
				} else {
					// Just output a simple clean message and exit
					wp_die(
						'<h1>' . esc_html__( 'Subscription Confirmed', 'order100' ) . '</h1><p>' . wp_kses_post( $val ) . '</p>',
						esc_html__( 'Success', 'order100' ),
						[ 'response' => 200 ]
					);
				}
			} else {
				wp_die( 'Invalid or expired confirmation link.', 'Error', [ 'response' => 400 ] );
			}
		} elseif ( isset( $_GET['o100_action'] ) && $_GET['o100_action'] === 'unsubscribe' && isset( $_GET['token'] ) ) {
			$token = sanitize_text_field( $_GET['token'] );
			$decoded = base64_decode( $token );
			if ( strpos( $decoded, '|' ) !== false ) {
				list( $email, $hash ) = explode( '|', $decoded );
				if ( md5( $email . NONCE_SALT ) === $hash ) {
					global $wpdb;
					$tbl_customers = O100_Customers_DB::get_table_customers();
					$wpdb->update( $tbl_customers, [ 'status' => 'unsubscribed' ], [ 'email' => $email ] );
					
					wp_die(
						'<h1>' . esc_html__( 'Unsubscribed', 'order100' ) . '</h1><p>' . esc_html__( 'You have been successfully unsubscribed. You will no longer receive marketing emails from us.', 'order100' ) . '</p>',
						esc_html__( 'Unsubscribed', 'order100' ),
						[ 'response' => 200 ]
					);
				}
			}
			wp_die( 'Invalid unsubscribe link.', 'Error', [ 'response' => 400 ] );
		}
	}
}
