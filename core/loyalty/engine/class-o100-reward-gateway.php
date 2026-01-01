<?php
/**
 * O100 Reward Gateway
 *
 * Unified interface for all reward-related operations across the system.
 * This is the single entry point for any module (Loyalty, Promotions, 
 * future Game Center) to award or deduct points, coupons, and free items.
 *
 * @package Order100
 * @since   4.1.0
 */

defined( 'ABSPATH' ) or die;

class O100_Reward_Gateway {

	/**
	 * Award points to a user.
	 *
	 * @param array $args {
	 *     @type int    $user_id     Required. WordPress user ID.
	 *     @type int    $points      Required. Number of points to award.
	 *     @type string $source      Required. Source identifier (e.g. 'spin_wheel', 'blind_box', 'manual').
	 *     @type int    $source_id   Optional. Associated reference ID (e.g. game session ID).
	 *     @type int    $campaign_id Optional. Linked campaign ID.
	 *     @type string $note        Optional. Human-readable description.
	 * }
	 * @return array [ 'success' => bool, 'points_awarded' => int, 'new_balance' => int ]
	 */
	public static function award_points( $args ) {
		$defaults = [
			'user_id'     => 0,
			'points'      => 0,
			'source'      => 'gateway',
			'source_id'   => 0,
			'campaign_id' => 0,
			'note'        => '',
		];
		$args = wp_parse_args( $args, $defaults );

		if ( ! $args['user_id'] || $args['points'] <= 0 ) {
			return [ 'success' => false, 'message' => 'Invalid user_id or points' ];
		}

		if ( ! class_exists( 'O100_Loyalty_DB' ) ) {
			return [ 'success' => false, 'message' => 'Loyalty DB not loaded' ];
		}

		$account = O100_Loyalty_DB::get_or_create_account( $args['user_id'] );
		if ( ! $account ) {
			return [ 'success' => false, 'message' => 'Could not create account' ];
		}

		if ( $account->status === 'banned' ) {
			return [ 'success' => false, 'message' => 'Account is banned' ];
		}

		$result = O100_Loyalty_DB::add_points(
			$account->id,
			$args['points'],
			$args['source'],
			$args['source_id'],
			$args['campaign_id'],
			$args['note']
		);

		if ( $result === false ) {
			return [ 'success' => false, 'message' => 'DB write failed' ];
		}

		// Refresh balance
		$updated_account = O100_Loyalty_DB::get_account( $account->id );
		$new_balance = $updated_account ? (int) $updated_account->points_balance : 0;

		// Trigger level check
		if ( class_exists( 'O100_Loyalty_Engine' ) ) {
			O100_Loyalty_Engine::instance()->check_level_change( $account->id );
		}

		do_action( 'o100_reward_points_awarded', $args['user_id'], $args['points'], $args['source'] );

		return [
			'success'        => true,
			'points_awarded' => $args['points'],
			'new_balance'    => $new_balance,
		];
	}

	/**
	 * Deduct points from a user (e.g. game entry cost, manual adjustment).
	 *
	 * @param array $args {
	 *     @type int    $user_id   Required. WordPress user ID.
	 *     @type int    $points    Required. Points to deduct.
	 *     @type string $source    Required. Source identifier.
	 *     @type int    $source_id Optional.
	 *     @type string $note      Optional.
	 * }
	 * @return array [ 'success' => bool, 'points_deducted' => int, 'new_balance' => int ]
	 */
	public static function deduct_points( $args ) {
		$defaults = [
			'user_id'   => 0,
			'points'    => 0,
			'source'    => 'gateway',
			'source_id' => 0,
			'note'      => '',
		];
		$args = wp_parse_args( $args, $defaults );

		if ( ! $args['user_id'] || $args['points'] <= 0 ) {
			return [ 'success' => false, 'message' => 'Invalid user_id or points' ];
		}

		if ( ! class_exists( 'O100_Loyalty_DB' ) ) {
			return [ 'success' => false, 'message' => 'Loyalty DB not loaded' ];
		}

		$account = O100_Loyalty_DB::get_account_by_user( $args['user_id'] );
		if ( ! $account ) {
			return [ 'success' => false, 'message' => 'Account not found' ];
		}

		if ( (int) $account->points_balance < $args['points'] ) {
			return [ 'success' => false, 'message' => 'Insufficient balance', 'balance' => (int) $account->points_balance ];
		}

		$result = O100_Loyalty_DB::deduct_points(
			$account->id,
			$args['points'],
			$args['source'],
			$args['source_id'],
			0,
			$args['note']
		);

		if ( $result === false ) {
			return [ 'success' => false, 'message' => 'DB write failed' ];
		}

		$updated_account = O100_Loyalty_DB::get_account( $account->id );
		$new_balance = $updated_account ? (int) $updated_account->points_balance : 0;

		do_action( 'o100_reward_points_deducted', $args['user_id'], $args['points'], $args['source'] );

		return [
			'success'         => true,
			'points_deducted' => $args['points'],
			'new_balance'     => $new_balance,
		];
	}

	/**
	 * Award a WooCommerce coupon to a user.
	 *
	 * @param array $args {
	 *     @type int    $user_id        Required. WordPress user ID.
	 *     @type string $source         Required. Source (e.g. 'spin_wheel', 'loyalty_redeem').
	 *     @type int    $source_id      Optional.
	 *     @type string $discount_type  Required. 'fixed_cart' or 'percent'.
	 *     @type float  $discount_value Required. Discount amount.
	 *     @type int    $expiry_days    Optional. Days until expiry. Default 30.
	 *     @type int    $usage_limit    Optional. Max uses. Default 1.
	 *     @type string $note           Optional.
	 * }
	 * @return array [ 'success' => bool, 'coupon_code' => string, 'coupon_id' => int ]
	 */
	public static function award_coupon( $args ) {
		$defaults = [
			'user_id'        => 0,
			'source'         => 'gateway',
			'source_id'      => 0,
			'discount_type'  => 'fixed_cart',
			'discount_value' => 0,
			'expiry_days'    => 30,
			'usage_limit'    => 1,
			'note'           => '',
		];
		$args = wp_parse_args( $args, $defaults );

		if ( ! $args['user_id'] || $args['discount_value'] <= 0 ) {
			return [ 'success' => false, 'message' => 'Invalid parameters' ];
		}

		// Generate unique coupon code
		$code = 'O100-' . strtoupper( $args['source'] ) . '-' . wp_generate_password( 6, false, false );

		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( $args['discount_type'] );
		$coupon->set_amount( $args['discount_value'] );
		$coupon->set_usage_limit( $args['usage_limit'] );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_individual_use( true );
		$coupon->set_email_restrictions( [ get_userdata( $args['user_id'] )->user_email ] );

		if ( $args['expiry_days'] > 0 ) {
			$expiry = new WC_DateTime();
			$expiry->modify( '+' . $args['expiry_days'] . ' days' );
			$coupon->set_date_expires( $expiry );
		}

		$coupon->save();

		if ( ! $coupon->get_id() ) {
			return [ 'success' => false, 'message' => 'Coupon creation failed' ];
		}

		// Tag the coupon with metadata for tracking
		update_post_meta( $coupon->get_id(), '_o100_reward_source', $args['source'] );
		update_post_meta( $coupon->get_id(), '_o100_reward_source_id', $args['source_id'] );
		update_post_meta( $coupon->get_id(), '_o100_reward_user_id', $args['user_id'] );

		do_action( 'o100_reward_coupon_awarded', $args['user_id'], $code, $args['source'] );

		return [
			'success'     => true,
			'coupon_code' => $code,
			'coupon_id'   => $coupon->get_id(),
		];
	}

	/**
	 * Award a free item via a 100% discount coupon restricted to specific products.
	 *
	 * @param array $args {
	 *     @type int    $user_id     Required. WordPress user ID.
	 *     @type string $source      Required.
	 *     @type int    $source_id   Optional.
	 *     @type array  $product_ids Required. Array of WooCommerce product IDs.
	 *     @type int    $quantity    Optional. Default 1.
	 *     @type int    $expiry_days Optional. Default 30.
	 *     @type string $note        Optional.
	 * }
	 * @return array [ 'success' => bool, 'coupon_code' => string, 'coupon_id' => int ]
	 */
	public static function award_free_item( $args ) {
		$defaults = [
			'user_id'     => 0,
			'source'      => 'gateway',
			'source_id'   => 0,
			'product_ids' => [],
			'quantity'    => 1,
			'expiry_days' => 30,
			'note'        => '',
		];
		$args = wp_parse_args( $args, $defaults );

		if ( ! $args['user_id'] || empty( $args['product_ids'] ) ) {
			return [ 'success' => false, 'message' => 'Invalid parameters' ];
		}

		// Create a 100% coupon restricted to these products
		return self::award_coupon( [
			'user_id'        => $args['user_id'],
			'source'         => $args['source'],
			'source_id'      => $args['source_id'],
			'discount_type'  => 'percent',
			'discount_value' => 100,
			'expiry_days'    => $args['expiry_days'],
			'usage_limit'    => $args['quantity'],
			'note'           => $args['note'] ?: 'Free item reward',
		] );
	}

	/**
	 * Check a user's current points balance and level.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array [ 'balance' => int, 'earned' => int, 'spent' => int, 'level_name' => string ]
	 */
	public static function check_balance( $user_id ) {
		if ( ! class_exists( 'O100_Loyalty_DB' ) ) {
			return [ 'balance' => 0, 'earned' => 0, 'spent' => 0, 'level_name' => '' ];
		}

		$account = O100_Loyalty_DB::get_account_by_user( $user_id );
		if ( ! $account ) {
			return [ 'balance' => 0, 'earned' => 0, 'spent' => 0, 'level_name' => '' ];
		}

		$level_name = '';
		if ( $account->level_id ) {
			$level = O100_Loyalty_DB::get_level( $account->level_id );
			$level_name = $level ? $level->name : '';
		}

		return [
			'balance'    => (int) $account->points_balance,
			'earned'     => (int) $account->points_earned,
			'spent'      => (int) $account->points_spent,
			'level_name' => $level_name,
		];
	}
}
