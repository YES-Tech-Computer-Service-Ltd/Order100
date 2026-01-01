<?php
/**
 * O100 Loyalty Hooks
 *
 * Registers all WordPress/WooCommerce hooks for the loyalty system.
 *
 * @package Order100
 * @since   4.0.0
 */

defined( 'ABSPATH' ) or die;

class O100_Loyalty_Hooks {

	private static $instance = null;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register all hooks.
	 */
	public function init() {
		$settings = O100_Loyalty_DB::get_settings();

		// ─── Order-based point earning ─────────────────────────
		$earn_statuses = $settings['earning_statuses'] ?? [ 'processing', 'completed' ];
		foreach ( (array) $earn_statuses as $status ) {
			$status = str_replace( 'wc-', '', $status );
			add_action( 'woocommerce_order_status_' . $status, [ $this, 'on_order_earn_status' ], 20, 1 );
		}

		// ─── Order reversal ────────────────────────────────────
		$remove_statuses = $settings['removing_statuses'] ?? [ 'cancelled', 'refunded', 'failed' ];
		foreach ( (array) $remove_statuses as $status ) {
			$status = str_replace( 'wc-', '', $status );
			add_action( 'woocommerce_order_status_' . $status, [ $this, 'on_order_reverse_status' ], 20, 1 );
		}

		// ─── Signup ────────────────────────────────────────────
		add_action( 'user_register', [ $this, 'on_user_register' ], 20, 1 );

		// ─── Birthday cron ─────────────────────────────────────
		add_action( 'o100_loyalty_daily_cron', [ $this, 'on_daily_cron' ] );
		if ( ! wp_next_scheduled( 'o100_loyalty_daily_cron' ) ) {
			wp_schedule_event( strtotime( 'today 00:05' ), 'daily', 'o100_loyalty_daily_cron' );
		}

		// ─── Birthday AJAX ─────────────────────────────────────
		add_action( 'wp_ajax_o100_save_birthday', [ $this, 'ajax_save_birthday' ] );
		add_action( 'wp_ajax_nopriv_o100_save_birthday', [ $this, 'ajax_save_birthday' ] );

		// ─── Referral tracking ─────────────────────────────────
		add_action( 'init', [ $this, 'capture_referral_code' ] );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'on_checkout_referral' ], 20, 1 );

		// ─── Product Review ─────────────────────────────────────
		add_action( 'comment_post', [ $this, 'on_comment_post' ], 20, 3 );
		add_action( 'comment_unapproved_to_approved', [ $this, 'on_comment_approved' ], 20, 1 );

		// ─── Social Share AJAX ──────────────────────────────────
		add_action( 'wp_ajax_o100_loyalty_social_share', [ $this, 'ajax_social_share' ] );

		// ─── Frontend display messages ─────────────────────────
		add_action( 'woocommerce_before_add_to_cart_form', [ $this, 'display_product_earn_message' ] );
		add_action( 'woocommerce_before_cart', [ $this, 'display_cart_earn_message' ] );
		// Removed: add_action( 'woocommerce_before_checkout_form', [ $this, 'display_checkout_earn_message' ] ); // Now integrated into O100 Promotions Dashboard
		add_action( 'woocommerce_thankyou', [ $this, 'display_thankyou_message' ], 5, 1 );

		// ─── WooCommerce My Account ────────────────────────────
		add_action( 'woocommerce_edit_account_form', [ $this, 'display_birthday_in_my_account' ] );
		add_action( 'woocommerce_save_account_details', [ $this, 'save_birthday_in_my_account' ], 10, 1 );

		// ─── Ensure tables exist ───────────────────────────────
		add_action( 'admin_init', [ 'O100_Loyalty_DB', 'maybe_create_tables' ] );
	}

	// ═══════════════════════════════════════════════════════════
	// HOOK CALLBACKS
	// ═══════════════════════════════════════════════════════════

	public function on_order_earn_status( $order_id ) {
		O100_Loyalty_Engine::instance()->process_order_earn( $order_id );
	}

	public function on_order_reverse_status( $order_id ) {
		O100_Loyalty_Engine::instance()->process_order_reverse( $order_id );
	}

	public function on_user_register( $user_id ) {
		O100_Loyalty_Engine::instance()->process_signup_earn( $user_id );
	}

	public function on_daily_cron() {
		O100_Loyalty_Engine::instance()->process_birthday_earn();
	}

	// ─── Product Review ──────────────────────────────────────────

	public function on_comment_post( $comment_id, $comment_approved, $commentdata ) {
		O100_Loyalty_Engine::instance()->process_review_earn( $comment_id, $comment_approved, $commentdata );
	}

	public function on_comment_approved( $comment ) {
		O100_Loyalty_Engine::instance()->process_review_approved( $comment );
	}

	// ─── Social Share AJAX ───────────────────────────────────────

	public function ajax_social_share() {
		check_ajax_referer( 'o100_loyalty_social_share', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'Not logged in' ] );
		}

		$channel    = sanitize_text_field( $_POST['channel'] ?? '' );
		$shared_url = esc_url_raw( $_POST['shared_url'] ?? '' );

		if ( ! in_array( $channel, [ 'facebook', 'twitter', 'whatsapp', 'email' ], true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid channel' ] );
		}

		$result = O100_Loyalty_Engine::instance()->process_social_share_earn(
			get_current_user_id(), $channel, $shared_url
		);

		if ( $result['status'] === 'success' ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	// ─── Birthday AJAX ─────────────────────────────────────────

	public function ajax_save_birthday() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		$date = isset( $_POST['birthday'] ) ? sanitize_text_field( $_POST['birthday'] ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_send_json_error( 'Invalid date format' );
		}

		$account = O100_Loyalty_DB::get_account_by_user( get_current_user_id() );
		if ( ! $account ) {
			$account = O100_Loyalty_DB::get_or_create_account( get_current_user_id() );
		}
		if ( ! $account ) {
			wp_send_json_error( 'Account error' );
		}

		$force = isset( $_POST['force'] ) && $_POST['force'] === '1';

		// If existing birthday and not forcing, ask confirmation
		if ( $account->birthday && $account->birthday !== $date && ! $force ) {
			wp_send_json_success( [
				'status'  => 'confirm',
				'message' => 'Are you sure you want to change your birthday? Points are awarded once per year.',
			] );
		}

		$result = O100_Loyalty_Engine::instance()->save_birthday( $account->id, $date );
		
		update_user_meta( get_current_user_id(), 'wlr_birthday_date', $date );
		update_user_meta( get_current_user_id(), 'wlr_birth_date', $date );
		update_user_meta( get_current_user_id(), 'o100_birthday', $date );
		
		wp_send_json_success( $result );
	}

	// ─── Referral Tracking ─────────────────────────────────────

	/**
	 * Capture referral code from URL and store in session/cookie.
	 */
	public function capture_referral_code() {
		if ( isset( $_GET['ref'] ) ) {
			$code = sanitize_text_field( $_GET['ref'] );
			if ( $code ) {
				WC()->session->set( 'o100_referral_code', $code );
				setcookie( 'o100_referral_code', $code, time() + ( 30 * DAY_IN_SECONDS ), '/' );
			}
		}
	}

	/**
	 * Process referral on checkout.
	 */
	public function on_checkout_referral( $order_id ) {
		$code = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$code = WC()->session->get( 'o100_referral_code', '' );
		}
		if ( ! $code && isset( $_COOKIE['o100_referral_code'] ) ) {
			$code = sanitize_text_field( $_COOKIE['o100_referral_code'] );
		}
		if ( $code ) {
			O100_Loyalty_Engine::instance()->process_referral( $order_id, $code );
			// Clear
			if ( WC()->session ) WC()->session->set( 'o100_referral_code', '' );
			setcookie( 'o100_referral_code', '', time() - 3600, '/' );
		}
	}

	// ─── WooCommerce My Account ──────────────────────────────────

	public function display_birthday_in_my_account() {
		$user_id = get_current_user_id();
		$birthday = get_user_meta( $user_id, 'wlr_birthday_date', true );
		if ( empty( $birthday ) || $birthday === '0000-00-00' ) {
			$birthday = get_user_meta( $user_id, 'wlr_birth_date', true );
		}
		if ( empty( $birthday ) || $birthday === '0000-00-00' ) {
			$birthday = get_user_meta( $user_id, 'o100_birthday', true );
		}
		
		$settings = O100_Loyalty_DB::get_settings();
		$allow_edit = ( $settings['allow_birthday_edit'] ?? 'yes' ) === 'yes';
		// Check proxy legacy setting too
		$legacy_settings = get_option('wlr_settings', []);
		if ( isset($legacy_settings['is_one_time_birthdate_edit']) && $legacy_settings['is_one_time_birthdate_edit'] === 'yes' ) {
			$allow_edit = false;
		}

		$is_readonly = ( !empty($birthday) && $birthday !== '0000-00-00' && !$allow_edit );
		?>
		<fieldset>
			<legend>Loyalty & Rewards</legend>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="o100_birthday">Birthday</label>
				<input type="date" class="woocommerce-Input woocommerce-Input--text input-text" name="o100_birthday" id="o100_birthday" value="<?php echo esc_attr( $birthday ); ?>" <?php echo $is_readonly ? 'readonly' : ''; ?> />
				<?php if ( $is_readonly ) : ?>
					<span class="description" style="font-size:12px; color:#6b7280;">Your birthday has been set and cannot be changed.</span>
				<?php else: ?>
					<span class="description" style="font-size:12px; color:#6b7280;">Add your birthday to receive a special reward!</span>
				<?php endif; ?>
			</p>
		</fieldset>
		<?php
	}

	public function save_birthday_in_my_account( $user_id ) {
		if ( isset( $_POST['o100_birthday'] ) ) {
			$new_date = sanitize_text_field( $_POST['o100_birthday'] );
			
			$existing_birthday = get_user_meta( $user_id, 'wlr_birthday_date', true );
			if ( empty( $existing_birthday ) || $existing_birthday === '0000-00-00' ) {
				$existing_birthday = get_user_meta( $user_id, 'wlr_birth_date', true );
			}
			if ( empty( $existing_birthday ) || $existing_birthday === '0000-00-00' ) {
				$existing_birthday = get_user_meta( $user_id, 'o100_birthday', true );
			}
			
			$settings = O100_Loyalty_DB::get_settings();
			$allow_edit = ( $settings['allow_birthday_edit'] ?? 'yes' ) === 'yes';
			$legacy_settings = get_option('wlr_settings', []);
			if ( isset($legacy_settings['is_one_time_birthdate_edit']) && $legacy_settings['is_one_time_birthdate_edit'] === 'yes' ) {
				$allow_edit = false;
			}
			
			// If already set and not allowed to edit, ignore
			if ( !empty($existing_birthday) && $existing_birthday !== '0000-00-00' && !$allow_edit && $existing_birthday !== $new_date ) {
				return;
			}
			
			if ( !empty( $new_date ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $new_date ) ) {
				update_user_meta( $user_id, 'wlr_birthday_date', $new_date );
				update_user_meta( $user_id, 'wlr_birth_date', $new_date );
				update_user_meta( $user_id, 'o100_birthday', $new_date );
				
				// Optional: Trigger engine
				if ( $existing_birthday !== $new_date ) {
					$account = O100_Loyalty_DB::get_or_create_account( $user_id );
					if ( $account ) {
						O100_Loyalty_Engine::instance()->save_birthday( $account->id, $new_date );
					}
				}
			}
		}
	}

	// ─── Frontend Display Messages ─────────────────────────────

	public function display_product_earn_message() {
		$settings = O100_Loyalty_DB::get_settings();
		if ( ( $settings['product_message_enable'] ?? 'yes' ) !== 'yes' ) return;

		$campaigns = O100_Loyalty_DB::get_active_campaigns( 'point_for_purchase' );
		if ( empty( $campaigns ) ) {
			$campaigns = O100_Loyalty_DB::get_active_campaigns( 'points_per_dollar' );
		}
		if ( empty( $campaigns ) ) return;

		global $product;
		if ( ! $product ) return;

		$price = (float) $product->get_price();
		if ( $price <= 0 ) return;

		$total_points = 0;
		foreach ( $campaigns as $camp ) {
			$config = json_decode( $camp->earn_config, true );
			$pts = (float) ( $config['earn_point'] ?? 1 );
			$per = (float) ( $config['wlr_point_earn_price'] ?? 1 );
			if ( $per <= 0 ) $per = 1;
			$total_points += ( $price / $per ) * $pts;
		}

		$total_points = (int) round( $total_points );
		if ( $total_points <= 0 ) return;

		$label = $settings['point_label_plural'] ?? 'Points';
		$colors = [
			'border' => $settings['earn_cart_border_color'] ?? '#9CC21D',
			'text'   => $settings['earn_cart_text_color'] ?? '#9CC21D',
			'bg'     => $settings['earn_cart_background_color'] ?? '#ffffff',
		];

		printf(
			'<div class="o100-loyalty-product-msg" style="border:1px solid %s; color:%s; background:%s; padding:8px 12px; border-radius:6px; font-size:13px; margin:10px 0;">🎯 %s</div>',
			esc_attr( $colors['border'] ),
			esc_attr( $colors['text'] ),
			esc_attr( $colors['bg'] ),
			sprintf( esc_html__( 'Purchase this product to earn %d %s!', 'order100' ), $total_points, esc_html( $label ) )
		);
	}

	public function display_cart_earn_message() {
		$settings = O100_Loyalty_DB::get_settings();
		if ( ( $settings['cart_earn_message_enable'] ?? 'yes' ) !== 'yes' ) return;

		$engine = O100_Loyalty_Engine::instance();
		$points = $engine->calculate_cart_points();
		if ( $points <= 0 ) return;

		$msg = $settings['cart_earn_message'] ?? 'Complete your order and earn {o100_cart_points} {o100_points_label}';
		$msg = $engine->replace_placeholders( $msg );

		$colors = [
			'border' => $settings['earn_cart_border_color'] ?? '#9CC21D',
			'text'   => $settings['earn_cart_text_color'] ?? '#9CC21D',
			'bg'     => $settings['earn_cart_background_color'] ?? '#ffffff',
		];

		printf(
			'<div class="o100-loyalty-cart-msg" style="border:1px solid %s; color:%s; background:%s; padding:12px 16px; border-radius:8px; font-size:14px; margin-bottom:16px;">🎯 %s</div>',
			esc_attr( $colors['border'] ),
			esc_attr( $colors['text'] ),
			esc_attr( $colors['bg'] ),
			wp_kses_post( $msg )
		);
	}

	public function display_checkout_earn_message() {
		$settings = O100_Loyalty_DB::get_settings();
		if ( ( $settings['checkout_earn_message_enable'] ?? 'yes' ) !== 'yes' ) return;

		$engine = O100_Loyalty_Engine::instance();
		$points = $engine->calculate_cart_points();
		if ( $points <= 0 ) return;

		$msg = $settings['checkout_earn_message'] ?? 'Complete your order and earn {o100_cart_points} {o100_points_label}';
		$msg = $engine->replace_placeholders( $msg );

		printf(
			'<div class="o100-loyalty-checkout-msg" style="border:1px solid #9CC21D; color:#9CC21D; background:#fff; padding:12px 16px; border-radius:8px; font-size:14px; margin-bottom:16px;">🎯 %s</div>',
			wp_kses_post( $msg )
		);
	}

	public function display_thankyou_message( $order_id ) {
		$settings = O100_Loyalty_DB::get_settings();
		if ( ( $settings['thankyou_message_enable'] ?? 'yes' ) !== 'yes' ) return;

		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		$earned = (int) $order->get_meta( '_o100_loyalty_points_earned' );
		if ( $earned <= 0 ) return;

		$user_id = $order->get_user_id();
		$account = $user_id ? O100_Loyalty_DB::get_account_by_user( $user_id ) : null;
		$total   = $account ? $account->points_balance : $earned;

		$engine = O100_Loyalty_Engine::instance();
		$msg = $settings['thankyou_message'] ?? 'You earned {o100_earned_points} {o100_points_label} for this order!';
		$msg = $engine->replace_placeholders( $msg, [
			'{o100_earned_points}' => $earned,
			'{o100_total_points}'  => $total,
		] );

		printf(
			'<div class="o100-loyalty-thankyou-msg" style="border:2px solid #4F47EB; background:#f8f7ff; padding:16px 20px; border-radius:10px; font-size:15px; margin:20px 0; color:#4F47EB; font-weight:500;">🎉 %s</div>',
			wp_kses_post( $msg )
		);
	}
}
