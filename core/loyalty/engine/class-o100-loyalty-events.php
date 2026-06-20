<?php
/**
 * O100 Loyalty Events Engine
 *
 * Hooks into WooCommerce and other system events to distribute or deduct points.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Loyalty_Events {

	public static function init() {
		// WooCommerce Order Completed Hook
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_order_completed' ), 10, 1 );
		
		// Handle Order Refunds to deduct points
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'handle_order_refunded' ), 10, 1 );
		
		// Handle User Registration
		add_action( 'user_register', array( __CLASS__, 'handle_user_signup' ), 10, 1 );
		
		// Handle Product Reviews
		add_action( 'comment_post', array( __CLASS__, 'handle_product_review' ), 10, 3 );
		
		// Handle Daily Birthday Check
		add_action( 'o100_loyalty_daily_birthday_cron', array( __CLASS__, 'handle_daily_birthday_check' ) );
		if ( ! wp_next_scheduled( 'o100_loyalty_daily_birthday_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'o100_loyalty_daily_birthday_cron' );
		}
	}

	/**
	 * Process points when an order is completed.
	 *
	 * @param int $order_id
	 */
	public static function handle_order_completed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return; // Guest orders don't earn loyalty points natively
		}

		// Prevent duplicate processing
		if ( $order->get_meta( '_o100_loyalty_processed' ) ) {
			return;
		}

		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';

		// Get active base point campaign
		$campaign = $wpdb->get_row( $wpdb->prepare( "
			SELECT * FROM {$campaigns_table} 
			WHERE type = %s AND status = 'active' 
			LIMIT 1
		", 'points_for_purchase' ), ARRAY_A );

		if ( ! $campaign ) {
			return; // No active purchase campaign
		}

		// Determine base conversion (e.g. 1 dollar = 1 point)
		// We'll extract this from conditions_json or reward_value
		$reward_val = (float) $campaign['reward_value']; // e.g. "1" means 1 point per $1 spent.

		if ( $reward_val <= 0 ) {
			$reward_val = 1; // fallback
		}

		$total_spent = (float) $order->get_total() - (float) $order->get_total_tax() - (float) $order->get_shipping_total();
		if ( $total_spent <= 0 ) {
			return;
		}

		$base_points = floor( $total_spent * $reward_val );

		if ( $base_points > 0 ) {
			// Apply Level Multiplier
			$multiplier = O100_Level_Engine::get_user_point_multiplier( $user_id );
			$final_points = ceil( $base_points * $multiplier );

			O100_Loyalty_Ledger::add_points(
				$user_id,
				$final_points,
				'order_earn',
				$order_id,
				sprintf( 'Earned %d points for Order #%d', $final_points, $order_id )
			);
		}

		$order->update_meta_data( '_o100_loyalty_processed', true );
		$order->save();
	}

	/**
	 * Deduct points if an order is refunded.
	 *
	 * @param int $order_id
	 */
	public static function handle_order_refunded( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		// Check if we processed this order previously
		if ( ! $order->get_meta( '_o100_loyalty_processed' ) || $order->get_meta( '_o100_loyalty_refunded' ) ) {
			return;
		}

		global $wpdb;
		$ledger_table = $wpdb->prefix . 'o100_loyalty_ledger';

		// Find exactly how many points were earned for this order
		$earned_points = $wpdb->get_var( $wpdb->prepare( "
			SELECT SUM(points) FROM {$ledger_table} 
			WHERE reference_id = %d AND event_type = 'order_earn' AND user_id = %d
		", $order_id, $user_id ) );

		if ( $earned_points && $earned_points > 0 ) {
			O100_Loyalty_Ledger::deduct_points(
				$user_id,
				$earned_points,
				'order_refund',
				$order_id,
				sprintf( 'Deducted %d points due to Order #%d refund', $earned_points, $order_id )
			);
		}

		$order->update_meta_data( '_o100_loyalty_refunded', true );
		$order->save();
	}
	
	/**
	 * Process points for new user signup
	 *
	 * @param int $user_id
	 */
	public static function handle_user_signup( $user_id ) {
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';

		$campaign = $wpdb->get_row( $wpdb->prepare( "
			SELECT * FROM {$campaigns_table} 
			WHERE type = %s AND status = 'active' 
			LIMIT 1
		", 'signup' ), ARRAY_A );

		if ( ! $campaign ) {
			return;
		}

		$points = (int) $campaign['reward_value'];
		if ( $points > 0 ) {
			O100_Loyalty_Ledger::add_points(
				$user_id,
				$points,
				'signup',
				$user_id,
				'Earned points for Account Sign Up'
			);
		}
	}
	
	/**
	 * Process points for product review
	 *
	 * @param int $comment_id
	 * @param int|string $comment_approved
	 * @param array $commentdata
	 */
	public static function handle_product_review( $comment_id, $comment_approved, $commentdata ) {
		// Only award for approved WooCommerce product reviews
		if ( $comment_approved !== 1 || ! isset($commentdata['comment_type']) || $commentdata['comment_type'] !== 'review' ) {
			return;
		}
		
		$post = get_post( $commentdata['comment_post_ID'] );
		if ( ! $post || $post->post_type !== 'product' ) {
			return;
		}
		
		$user_id = $commentdata['user_id'] ?? 0;
		if ( ! $user_id ) {
			return; // Guest reviews don't earn points
		}
		
		// Check if user already reviewed this product to prevent abuse
		global $wpdb;
		$ledger_table = $wpdb->prefix . 'o100_loyalty_ledger';
		$already_earned = $wpdb->get_var( $wpdb->prepare("
			SELECT id FROM {$ledger_table} 
			WHERE user_id = %d AND event_type = 'product_review' AND reference_id = %d
		", $user_id, $post->ID) );
		
		if ( $already_earned ) {
			return;
		}
		
		$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';
		$campaign = $wpdb->get_row( $wpdb->prepare( "
			SELECT * FROM {$campaigns_table} 
			WHERE type = %s AND status = 'active' 
			LIMIT 1
		", 'product_review' ), ARRAY_A );

		if ( ! $campaign ) {
			return;
		}

		$points = (int) $campaign['reward_value'];
		if ( $points > 0 ) {
			O100_Loyalty_Ledger::add_points(
				$user_id,
				$points,
				'product_review',
				$post->ID,
				sprintf( 'Earned points for reviewing product #%d', $post->ID )
			);
		}
	}
	
	/**
	 * Process daily birthday check
	 */
	public static function handle_daily_birthday_check() {
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';

		$campaign = $wpdb->get_row( $wpdb->prepare( "
			SELECT * FROM {$campaigns_table} 
			WHERE type = %s AND status = 'active' 
			LIMIT 1
		", 'birthday' ), ARRAY_A );

		if ( ! $campaign ) {
			return;
		}
		
		$points = (int) $campaign['reward_value'];
		if ( $points <= 0 ) {
			return;
		}
		
		$users_table = $wpdb->prefix . 'o100_loyalty_accounts';
		$today_md = date('m-d');
		$current_year = date('Y');
		
		// Find users whose birthday is today (MM-DD matches)
		$birthday_users = $wpdb->get_results( $wpdb->prepare("
			SELECT user_id, birthday FROM {$users_table} 
			WHERE DATE_FORMAT(birthday, '%%m-%%d') = %s
		", $today_md) );
		
		foreach ( $birthday_users as $u ) {
			$user_id = $u->user_id;
			$last_reward_year = get_user_meta( $user_id, 'o100_last_birthday_reward_year', true );
			
			// Only reward once per year
			if ( $last_reward_year != $current_year ) {
				O100_Loyalty_Ledger::add_points(
					$user_id,
					$points,
					'birthday',
					0,
					'Happy Birthday! Here are your points.'
				);
				update_user_meta( $user_id, 'o100_last_birthday_reward_year', $current_year );
			}
		}
	}
}
