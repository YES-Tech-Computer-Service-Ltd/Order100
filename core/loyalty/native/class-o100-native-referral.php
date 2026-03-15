<?php
/**
 * O100 Native Referral Engine
 *
 * Handles referral links tracking, attribution, and rewards distribution.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Native_Referral {

	public static function init() {
		// Track referral code in URL
		add_action( 'template_redirect', array( __CLASS__, 'track_referral_click' ) );
		
		// Attribute referral code on user registration
		add_action( 'user_register', array( __CLASS__, 'attribute_referral_on_signup' ), 20, 1 );
		
		// Award referral points on first order completion
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'process_referral_rewards' ), 20, 1 );
	}

	/**
	 * Save the referral code from the URL into a 30-day cookie
	 */
	public static function track_referral_click() {
		if ( isset( $_GET['ref'] ) && ! empty( $_GET['ref'] ) ) {
			$refer_code = sanitize_text_field( $_GET['ref'] );
			
			// Don't track if the user is already logged in (they can't be referred)
			if ( is_user_logged_in() ) {
				return;
			}
			
			// Set cookie for 30 days
			setcookie( 'o100_referral_code', $refer_code, time() + ( 30 * 24 * 60 * 60 ), COOKIEPATH, COOKIE_DOMAIN );
			
			// Immediately apply the friend's welcome coupon if configured
			self::generate_friend_reward( $refer_code );
		}
	}

	/**
	 * Generate a welcome coupon for the referred friend
	 */
	public static function generate_friend_reward( $advocate_refer_code ) {
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';
		
		// Look for a referral campaign that gives a reward to the friend
		$campaign = $wpdb->get_row( "SELECT * FROM {$campaigns_table} WHERE type = 'referral_friend' AND status = 'active' LIMIT 1" );
		if ( ! $campaign ) {
			return;
		}

		$advocate = O100_Loyalty_DB::get_account_by_refer_code( $advocate_refer_code );
		if ( ! $advocate ) {
			return;
		}

		// Create a coupon for the guest using O100 Promotions
		if ( class_exists( 'O100_Promotions_DB' ) ) {
			// We can generate a promo code for them or just show a message.
			// Since we don't know their email yet, we can attach the coupon to the session.
			$coupon_code = 'REF-' . strtoupper( wp_generate_password( 6, false ) );
			
			$promo_data = array(
				'name'          => 'Friend Referral Welcome Discount',
				'description'   => 'Referred by ' . $advocate->email,
				'type'          => 'fixed_cart',
				'amount'        => floatval( $campaign->reward_value ),
				'promo_code'    => $coupon_code,
				'usage_limit'   => 1,
				'status'        => 'active',
				'source'        => 'loyalty_referral',
				'conditions'    => json_encode(array(
					array('type' => 'min_subtotal', 'options' => array('value' => 0))
				))
			);
			
			$promo_id = O100_Promotions_DB::insert_promotion( $promo_data );
			if ( $promo_id ) {
				// Store the coupon in session so it auto-applies
				if ( ! WC()->session->has_session() ) {
					WC()->session->set_customer_session_cookie( true );
				}
				WC()->session->set( 'o100_friend_referral_coupon', $coupon_code );
				WC()->cart->add_discount( $coupon_code );
			}
		}
	}

	/**
	 * Link the new user to their advocate based on the cookie
	 */
	public static function attribute_referral_on_signup( $user_id ) {
		if ( isset( $_COOKIE['o100_referral_code'] ) ) {
			$refer_code = sanitize_text_field( $_COOKIE['o100_referral_code'] );
			$advocate = O100_Loyalty_DB::get_account_by_refer_code( $refer_code );
			
			if ( $advocate ) {
				// Mark the new user's advocate ID in meta
				update_user_meta( $user_id, '_o100_referred_by_id', $advocate->user_id );
				
				// Optional: Reward advocate just for signup? Usually we wait for first purchase.
			}
			
			// Clear cookie
			setcookie( 'o100_referral_code', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
		}
	}

	/**
	 * Process advocate reward when the friend completes their first order
	 */
	public static function process_referral_rewards( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$friend_id = $order->get_user_id();
		if ( ! $friend_id ) {
			return;
		}

		// Check if this user was referred
		$advocate_id = get_user_meta( $friend_id, '_o100_referred_by_id', true );
		if ( ! $advocate_id ) {
			return;
		}

		// Check if advocate already got rewarded for this friend
		$already_rewarded = get_user_meta( $friend_id, '_o100_referral_reward_granted', true );
		if ( $already_rewarded ) {
			return;
		}

		// Check if there is an active Advocate campaign
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';
		$campaign = $wpdb->get_row( "SELECT * FROM {$campaigns_table} WHERE type = 'referral_advocate' AND status = 'active' LIMIT 1" );
		
		if ( ! $campaign ) {
			return;
		}

		$points_to_award = (int) $campaign->reward_value;
		if ( $points_to_award > 0 ) {
			$friend_user = get_userdata( $friend_id );
			$friend_email = $friend_user ? $friend_user->user_email : 'a friend';
			
			O100_Loyalty_Ledger::add_points(
				$advocate_id,
				$points_to_award,
				'referral_advocate',
				$friend_id,
				sprintf( 'Earned points for successfully referring %s', $friend_email )
			);

			// Mark as rewarded so we don't reward advocate again for this friend's future purchases
			update_user_meta( $friend_id, '_o100_referral_reward_granted', true );
		}
	}
}

// TS: 20260120224658

// TS: 20260314174143
