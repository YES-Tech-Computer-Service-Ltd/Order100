<?php
/**
 * Frontend Loyalty API Controller
 *
 * Handles REST endpoints for frontend customer interactions.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Loyalty_Frontend_API {

	public static function check_user_permissions( $request ) {
		return is_user_logged_in();
	}

	public static function rest_apply_reward_to_cart( $request ) {
		$coupon_code = sanitize_text_field( $request->get_param( 'coupon_code' ) );
		if ( empty( $coupon_code ) ) {
			return new WP_Error( 'invalid_coupon', 'Invalid coupon code', [ 'status' => 400 ] );
		}

		if ( ! WC()->cart ) {
			WC()->frontend_includes();
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
			WC()->customer = new WC_Customer( get_current_user_id(), true );
			WC()->cart = new WC_Cart();
		}

		// Auto-add product if specified (used by promotions UI)
		$product_id = intval( $request->get_param( 'product_id' ) );
		if ( $product_id > 0 ) {
			WC()->cart->add_to_cart( $product_id );
		}

		$applied = WC()->cart->add_discount( $coupon_code );
		
		if ( $applied ) {
			return rest_ensure_response( [ 'success' => true ] );
		} else {
			return new WP_Error( 'coupon_failed', 'Coupon could not be applied. It may be expired or invalid for current cart.', [ 'status' => 400 ] );
		}
	}

	public static function rest_redeem_punch_card( $request ) {
		if ( ! class_exists('O100_Loyalty_DB') || ! class_exists('O100_Reward_Gateway') ) {
			return new WP_Error( 'system_error', 'System unavailable', [ 'status' => 500 ] );
		}

		$campaign_id = intval( $request->get_param( 'campaign_id' ) );
		if ( ! $campaign_id ) return new WP_Error( 'invalid_campaign', 'Invalid campaign', [ 'status' => 400 ] );

		$user_id = get_current_user_id();
		$account = O100_Loyalty_DB::get_account_by_user( $user_id );
		if ( ! $account ) return new WP_Error( 'no_account', 'No loyalty account found', [ 'status' => 404 ] );

		global $wpdb;
		$campaign = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . O100_Loyalty_DB::table_campaigns() . " WHERE id = %d AND type = 'punch_card' AND status = 'active'", $campaign_id ) );
		
		if ( ! $campaign ) return new WP_Error( 'inactive_campaign', 'Punch card is not active', [ 'status' => 400 ] );

		$rules = json_decode($campaign->rules_json, true) ?: [];
		$req_stamps = isset($rules['punch_count']) ? intval($rules['punch_count']) : 5;
		
		$prog = O100_Loyalty_DB::get_punch_progress( $account->id, $campaign->id );
		if ( ! $prog || $prog->stamps < $req_stamps ) {
			return new WP_Error( 'not_enough_stamps', 'Not enough stamps to redeem', [ 'status' => 400 ] );
		}

		$reward_val = isset($rules['reward_value']) ? floatval($rules['reward_value']) : 5.0;

		$reset = O100_Loyalty_DB::reset_punch_progress( $account->id, $campaign->id, $req_stamps );
		if ( ! $reset ) return new WP_Error( 'deduct_failed', 'Failed to deduct stamps', [ 'status' => 500 ] );

		$result = O100_Reward_Gateway::award_coupon([
			'user_id'        => $user_id,
			'source'         => 'punch_card',
			'source_id'      => $campaign->id,
			'discount_type'  => 'fixed_cart',
			'discount_value' => $reward_val,
			'note'           => 'Punch Card Reward: ' . $campaign->name,
		]);

		if ( $result['success'] ) {
			return rest_ensure_response( [ 'success' => true, 'message' => 'Reward claimed! Code: ' . $result['coupon_code'] ] );
		} else {
			O100_Loyalty_DB::add_stamps( $account->id, $campaign->id, $req_stamps );
			return new WP_Error( 'reward_failed', 'Failed to generate reward coupon', [ 'status' => 500 ] );
		}
	}

	public static function rest_redeem_points( $request ) {
		if ( ! class_exists('O100_Loyalty_DB') || ! class_exists('O100_Reward_Gateway') ) {
			return new WP_Error( 'system_error', 'System unavailable', [ 'status' => 500 ] );
		}

		$campaign_id = intval( $request->get_param( 'campaign_id' ) );
		if ( ! $campaign_id ) return new WP_Error( 'invalid_campaign', 'Invalid campaign', [ 'status' => 400 ] );

		$user_id = get_current_user_id();
		$account = O100_Loyalty_DB::get_account_by_user( $user_id );
		if ( ! $account ) return new WP_Error( 'no_account', 'No loyalty account found', [ 'status' => 404 ] );

		global $wpdb;
		$campaign = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . O100_Loyalty_DB::table_campaigns() . " WHERE id = %d AND status = 'active'", $campaign_id ) );
		
		if ( ! $campaign ) return new WP_Error( 'inactive_reward', 'Reward is not active', [ 'status' => 400 ] );

		$rules = json_decode($campaign->ui_json ?? '', true) ?: [];
		$cost_points = isset($rules['cost_points']) ? intval($rules['cost_points']) : 0;
		$reward_val = isset($rules['reward_value']) ? floatval($rules['reward_value']) : 0;
		$discount_type = isset($rules['discount_type']) ? $rules['discount_type'] : 'fixed_cart';

		if ( $cost_points <= 0 || $reward_val <= 0 ) return new WP_Error( 'invalid_config', 'Reward configuration is invalid', [ 'status' => 400 ] );

		if ( $account->points_balance < $cost_points ) return new WP_Error( 'insufficient_points', 'Not enough points to redeem this reward', [ 'status' => 400 ] );

		$deducted = O100_Loyalty_DB::add_points( $account->id, -$cost_points, 'redeem_reward', $campaign->id, 'Redeemed: ' . $campaign->name );
		if ( ! $deducted ) return new WP_Error( 'deduct_failed', 'Failed to deduct points', [ 'status' => 500 ] );

		$result = O100_Reward_Gateway::award_coupon([
			'user_id'        => $user_id,
			'source'         => 'redeem',
			'source_id'      => $campaign->id,
			'discount_type'  => $discount_type,
			'discount_value' => $reward_val,
			'note'           => 'Reward: ' . $campaign->name,
		]);

		if ( $result['success'] ) {
			return rest_ensure_response( [ 'success' => true, 'message' => 'Reward claimed! Code: ' . $result['coupon_code'], 'coupon_code' => $result['coupon_code'] ] );
		} else {
			O100_Loyalty_DB::add_points( $account->id, $cost_points, 'refund', $campaign->id, 'Refund: Coupon generation failed' );
			return new WP_Error( 'reward_failed', 'Failed to generate reward coupon', [ 'status' => 500 ] );
		}
	}
	
	public static function rest_social_share( $request ) {
		if ( ! class_exists('O100_Loyalty_Engine') ) return new WP_Error( 'system_error', 'System unavailable', [ 'status' => 500 ] );

		$channel    = sanitize_text_field( $request->get_param( 'network' ) );
		$shared_url = esc_url_raw( $request->get_param( 'shared_url' ) );
		
		if ( empty( $channel ) ) return new WP_Error( 'invalid_params', 'Invalid channel', [ 'status' => 400 ] );

		if ( ! in_array( $channel, [ 'facebook', 'twitter', 'whatsapp', 'email' ], true ) ) {
			return new WP_Error( 'invalid_channel', 'Invalid channel', [ 'status' => 400 ] );
		}

		$result = O100_Loyalty_Engine::instance()->process_social_share_earn(
			get_current_user_id(), $channel, $shared_url
		);

		if ( $result['status'] === 'success' ) {
			return rest_ensure_response( [ 'success' => true, 'message' => $result['message'] ?? 'Thanks for sharing!' ] );
		} else {
			return new WP_Error( 'award_failed', $result['message'] ?? 'Failed to award points', [ 'status' => 400 ] );
		}
	}
}
