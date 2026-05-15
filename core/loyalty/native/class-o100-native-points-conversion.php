<?php
/**
 * O100 Native Points Conversion
 *
 * Handles AJAX requests for redeeming points into coupons.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Native_Points_Conversion {

	public static function init() {
		add_action( 'wp_ajax_o100_native_redeem_points', [ __CLASS__, 'handle_redemption_ajax' ] );
		add_action( 'wp_ajax_nopriv_o100_native_redeem_points', [ __CLASS__, 'handle_redemption_ajax' ] );
	}

	public static function handle_redemption_ajax() {
		check_ajax_referer( 'o100_loyalty', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Please log in to redeem points.' );
		}

		$user_id = get_current_user_id();
		$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
		$points_to_redeem = isset( $_POST['points'] ) ? intval( $_POST['points'] ) : 0;

		if ( $points_to_redeem <= 0 ) {
			wp_send_json_error( 'Invalid points amount.' );
		}

		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';
		$camp = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$campaigns_table} WHERE id = %d AND type = 'points_conversion' AND status = 'active'", $campaign_id ) );

		if ( ! $camp ) {
			wp_send_json_error( 'Conversion rule not found.' );
		}

		$ui_json = json_decode( $camp->ui_json, true ) ?: [];
		$require_pt = intval( $ui_json['points'] ?? 100 );
		$reward_type = $ui_json['reward_type'] ?? 'fixed_discount';
		$min_pt = intval( $ui_json['min_points'] ?? 0 );
		$max_pt = intval( $ui_json['max_points'] ?? 0 );
		$conversion_mode = $ui_json['conversion_mode'] ?? 'integer';

		// Validate Minimum
		if ( $min_pt > 0 && $points_to_redeem < $min_pt ) {
			wp_send_json_error( "You must redeem at least {$min_pt} points." );
		}

		// Validate Maximum
		if ( $max_pt > 0 && $points_to_redeem > $max_pt ) {
			wp_send_json_error( "You can only redeem a maximum of {$max_pt} points per coupon." );
		}

		// Validate Integer Mode
		if ( $reward_type === 'free_item' || $conversion_mode === 'integer' ) {
			if ( $points_to_redeem % $require_pt !== 0 ) {
				wp_send_json_error( "Points must be in multiples of {$require_pt}." );
			}
		} else {
			if ( $points_to_redeem < $require_pt ) {
				wp_send_json_error( "You must redeem at least {$require_pt} points." );
			}
		}

		// Check User Balance
		$current_balance = O100_Loyalty_Ledger::get_balance( $user_id );
		if ( $current_balance < $points_to_redeem ) {
			wp_send_json_error( 'You do not have enough points.' );
		}

		// Prepare Coupon Configuration
		$coupon_code = 'REWARD-' . strtoupper( wp_generate_password( 8, false ) );
		$action_config = [];
		$apply_to = 'all';
		$apply_to_items = '';
		$display_name = !empty($ui_json['display_name']) ? sanitize_text_field($ui_json['display_name']) : 'Points Redemption';

		if ( $reward_type === 'free_item' ) {
			$product_id = intval( $ui_json['free_item_id'] ?? 0 );
			if ( $product_id <= 0 ) {
				wp_send_json_error( 'Free item product not configured properly.' );
			}
			$action_config = [
				'discount_type'  => 'percentage',
				'discount_value' => 100
			];
			$apply_to = 'specific_products';
			$apply_to_items = wp_json_encode( [ $product_id ] );
		} else {
			$disc_val = floatval( $ui_json['reward_value'] ?? 1 );
			$ratio = $disc_val / $require_pt;
			$total_discount = round( $points_to_redeem * $ratio, 2 );

			$action_config = [
				'discount_type'  => 'fixed_cart',
				'discount_value' => $total_discount
			];
		}

		// Expiry
		$expire_type = $ui_json['expire_type'] ?? 'unlimited';
		$end_time = null;
		if ( $expire_type === 'limited' ) {
			$expire_after = intval( $ui_json['expire_after'] ?? 30 );
			$expire_period = $ui_json['expire_period'] ?? 'day';
			$period_map = [ 'day' => 'days', 'week' => 'weeks', 'month' => 'months', 'year' => 'years' ];
			$str_period = $period_map[$expire_period] ?? 'days';
			
			$end_time = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expire_after} {$str_period}" ) );
		}

		// User Restriction
		$user_info = get_userdata($user_id);
		$user_email = $user_info ? $user_info->user_email : '';
		$conditions = [];
		if ( !empty($user_email) ) {
			$conditions[] = [
				'type' => 'customer_email',
				'options' => [ 'value' => $user_email ]
			];
		}

		// Insert into Promo Table
		if ( ! class_exists('O100_Promotions_DB') ) {
			wp_send_json_error( 'Promotions Engine not loaded.' );
		}

		$promo_data = [
			'source'         => 'loyalty_conversion',
			'title'          => $display_name,
			'rule_type'      => 'cart_discount',
			'action_config'  => wp_json_encode( $action_config ),
			'apply_to'       => $apply_to,
			'apply_to_items' => $apply_to_items,
			'promo_code'     => $coupon_code,
			'conditions'     => wp_json_encode( $conditions ),
			'usage_limit'    => 1,
			'status'         => 'active',
			'start_time'     => current_time('mysql', true),
			'end_time'       => $end_time
		];

		$promo_table = O100_Promotions_DB::table_name();
		$inserted = $wpdb->insert( $promo_table, $promo_data );

		if ( ! $inserted ) {
			wp_send_json_error( 'Failed to generate coupon.' );
		}

		// Deduct Points
		$deducted = O100_Loyalty_Ledger::deduct_points( $user_id, $points_to_redeem, 'redeem_points', $wpdb->insert_id, 'Redeemed ' . $points_to_redeem . ' points for coupon ' . $coupon_code );

		if ( $deducted ) {
			wp_send_json_success( 'Coupon generated successfully: ' . $coupon_code );
		} else {
			wp_send_json_error( 'Failed to deduct points.' );
		}
	}
}

O100_Native_Points_Conversion::init();

// TS: 20260314134613

// TS: 20260515121830
