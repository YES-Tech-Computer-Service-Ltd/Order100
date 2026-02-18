<?php
/**
 * Restaurant-Specific Loyalty Boosters
 *
 * Implements logic for:
 * 1. Pickup Bonus
 * 2. Pre-order Bonus
 * 3. Profile Completion Bonus
 *
 * @package Order100\Loyalty
 */

namespace O100\Loyalty;

defined( 'ABSPATH' ) || exit;

class O100_Loyalty_Restaurant_Boosters {

	public function __construct() {
		// Register our custom action types in native O100 system if needed
		add_filter( 'o100_loyalty_action_types', [ $this, 'register_custom_actions' ] );

		// Hook into order status changes
		add_action( 'woocommerce_order_status_processing', [ $this, 'process_order_boosters' ], 99, 1 );
		add_action( 'woocommerce_order_status_completed', [ $this, 'process_order_boosters' ], 99, 1 );
		
		// Hook into profile save
		add_action( 'woocommerce_customer_save_address', [ $this, 'process_profile_bonus' ], 10, 2 );
		add_action( 'personal_options_update', [ $this, 'process_profile_bonus_wp' ], 10, 1 );
	}

	/**
	 * Register custom action types in Native system.
	 */
	public function register_custom_actions( $actions ) {
		$actions['pickup_bonus']   = 'Pickup Bonus';
		$actions['profile_bonus']  = 'Profile Completion';
		$actions['preorder_bonus'] = 'Pre-order Bonus';
		return $actions;
	}

	/**
	 * Process Pickup and Pre-order Boosters when order is paid/processing.
	 */
	public function process_order_boosters( $order_id ) {
		if ( ! class_exists( '\O100_Loyalty_DB' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_customer_id();
		$user_email = $order->get_billing_email();
		if ( empty( $user_email ) ) {
			return;
		}

		// 1. Pickup Bonus Check
		$order_method = get_post_meta( $order_id, '_o100_order_method', true );
		if ( empty( $order_method ) ) {
			$order_method = get_post_meta( $order_id, 'o100_order_method', true );
		}
		if ( empty( $order_method ) ) {
			$order_method = get_post_meta( $order_id, '_o100_order_type', true );
		}
		$is_pickup = ( strpos( strtolower( $order_method ), 'pickup' ) !== false );

		global $wpdb;
		$campaign_table = $wpdb->prefix . 'o100_loyalty_campaigns';
		$account = \O100_Loyalty_DB::get_or_create_account( $user_id, $user_email );

		if ( $is_pickup && $account ) {
			$pickup_campaigns = $wpdb->get_results("SELECT * FROM {$campaign_table} WHERE type = 'pickup_bonus' AND status = 'active'");
			foreach ( $pickup_campaigns as $campaign ) {
				$points = intval( $campaign->reward_value );
				if ( $points > 0 ) {
					// Check if already rewarded for this order and campaign
					$transactions_table = $wpdb->prefix . 'o100_loyalty_transactions';
					$has_earned = $wpdb->get_var( $wpdb->prepare(
						"SELECT id FROM {$transactions_table} WHERE account_id = %d AND campaign_id = %d AND source_id = %d LIMIT 1",
						$account->id, $campaign->id, $order_id
					) );
					
					if ( ! $has_earned ) {
						\O100_Loyalty_DB::add_points( $account->id, $points, 'order_pickup_bonus', $order_id, $campaign->id, "Earned points for pickup order #" . $order_id );
					}
				}
			}
		}

		// 2. Pre-order Bonus Check
		$time_deli = get_post_meta( $order_id, '_o100_time_deli', true );
		if ( empty( $time_deli ) ) {
			$time_deli = get_post_meta( $order_id, 'o100_time_deli', true );
		}
		if ( ! empty( $time_deli ) && $account ) {
			// e.g., '2026-05-27 18:30:00' or similar string. Assuming it can be parsed by strtotime
			$target_time  = strtotime( $time_deli );
			$current_time = current_time( 'timestamp' );
			
			if ( $target_time && ( $target_time - $current_time >= 24 * HOUR_IN_SECONDS ) ) {
				$preorder_campaigns = $wpdb->get_results("SELECT * FROM {$campaign_table} WHERE type = 'preorder_bonus' AND status = 'active'");
				foreach ( $preorder_campaigns as $campaign ) {
					$points = intval( $campaign->reward_value );
					if ( $points > 0 ) {
						// Check if already rewarded for this order and campaign
						$transactions_table = $wpdb->prefix . 'o100_loyalty_transactions';
						$has_earned = $wpdb->get_var( $wpdb->prepare(
							"SELECT id FROM {$transactions_table} WHERE account_id = %d AND campaign_id = %d AND source_id = %d LIMIT 1",
							$account->id, $campaign->id, $order_id
						) );
						
						if ( ! $has_earned ) {
							\O100_Loyalty_DB::add_points( $account->id, $points, 'order_preorder_bonus', $order_id, $campaign->id, "Earned points for pre-order #" . $order_id );
						}
					}
				}
			}
		}
	}

	/**
	 * Wrapper for standard WP profile update.
	 */
	public function process_profile_bonus_wp( $user_id ) {
		$this->process_profile_bonus( $user_id, 'billing' );
	}

	/**
	 * Process Profile Completion Bonus when saving address.
	 */
	public function process_profile_bonus( $user_id, $load_address ) {
		if ( ! class_exists( '\O100_Loyalty_DB' ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$phone = get_user_meta( $user_id, 'billing_phone', true );
		if ( empty( $phone ) ) {
			return;
		}

		global $wpdb;
		$campaign_table = $wpdb->prefix . 'o100_loyalty_campaigns';
		$profile_campaigns = $wpdb->get_results("SELECT * FROM {$campaign_table} WHERE type = 'profile_bonus' AND status = 'active'");
		
		if ( ! empty( $profile_campaigns ) ) {
			$account = \O100_Loyalty_DB::get_or_create_account( $user_id, $user->user_email );
			if ( $account ) {
				foreach ( $profile_campaigns as $campaign ) {
					$points = intval( $campaign->reward_value );
					if ( $points > 0 ) {
						// Check if already earned
						$transactions_table = $wpdb->prefix . 'o100_loyalty_transactions';
						$has_earned = $wpdb->get_var( $wpdb->prepare(
							"SELECT id FROM {$transactions_table} WHERE account_id = %d AND campaign_id = %d LIMIT 1",
							$account->id, $campaign->id
						) );

						if ( ! $has_earned ) {
							\O100_Loyalty_DB::add_points( $account->id, $points, 'profile_bonus', $user_id, $campaign->id, "Earned points for completing profile." );
						}
					}
				}
			}
		}
	}
}






// TS: 20260106222640

// TS: 20260123124308

// TS: 20260201111517

// TS: 20260215005541
