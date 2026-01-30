<?php
/**
 * O100 Loyalty Cron
 *
 * Handles scheduled tasks such as monthly recurring rewards.
 */

defined( 'ABSPATH' ) or die;

class O100_Loyalty_Cron {

	public static function init() {
		add_action( 'o100_daily_loyalty_cron', [ __CLASS__, 'process_monthly_rewards' ] );

		if ( ! wp_next_scheduled( 'o100_daily_loyalty_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'o100_daily_loyalty_cron' );
		}
	}

	public static function process_monthly_rewards() {
		if ( ! class_exists( 'O100_Loyalty_DB' ) ) {
			return;
		}

		// Fetch active monthly reward campaigns
		$campaigns = O100_Loyalty_DB::get_active_campaigns( 'monthly_reward' );
		if ( empty( $campaigns ) ) {
			return;
		}

		$current_ym = gmdate( 'Y_m' );

		foreach ( $campaigns as $camp ) {
			if ( $camp->status !== 'active' ) {
				continue;
			}

			// Parse config
			$rule = is_string( $camp->earn_config ) ? json_decode( $camp->earn_config, true ) : (array) $camp->earn_config;
			if ( empty( $rule ) ) {
				continue;
			}

			$target_audience = isset( $rule['target_audience'] ) ? $rule['target_audience'] : 'all';
			$reward_type = isset( $rule['earn_reward'] ) && ! empty( $rule['earn_reward'] ) ? 'coupon' : 'points';
			
			// Find eligible users
			$users = self::get_eligible_users( $target_audience );
			
			foreach ( $users as $user_id ) {
				// Check if already sent this month
				$meta_key = "o100_monthly_reward_{$camp->id}_{$current_ym}";
				if ( get_user_meta( $user_id, $meta_key, true ) ) {
					continue;
				}

				if ( $reward_type === 'coupon' ) {
					self::dispatch_coupon_reward( $user_id, $camp, $rule );
				} else {
					self::dispatch_points_reward( $user_id, $camp, $rule );
				}

				// Mark as sent
				update_user_meta( $user_id, $meta_key, 'sent' );
			}
		}
	}

	private static function get_eligible_users( $target_audience ) {
		$args = [
			'fields' => 'ID',
		];

		if ( $target_audience === 'active_30' ) {
			// Need a way to check last active/order date. 
			// Let's use last order date or last login if tracked.
			// Simplified: just get all users who placed an order in last 30 days.
			$args['meta_query'] = [
				[
					'key'     => '_o100_last_order_date',
					'value'   => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
					'compare' => '>=',
					'type'    => 'DATE'
				]
			];
		} elseif ( $target_audience === 'active_90' ) {
			$args['meta_query'] = [
				[
					'key'     => '_o100_last_order_date',
					'value'   => gmdate( 'Y-m-d', strtotime( '-90 days' ) ),
					'compare' => '>=',
					'type'    => 'DATE'
				]
			];
		}

		$user_query = new WP_User_Query( $args );
		return $user_query->get_results();
	}

	private static function dispatch_coupon_reward( $user_id, $campaign, $rule ) {
		$source_coupon = $rule['earn_reward']; // could be 'wc_123' or 'promo_123'
		
		// If it's a wc coupon, just grant access or duplicate it for the user if it's unique
		// But usually Monthly rewards are dynamic unique coupons OR they just get notified.
		// We use O100_Reward_Gateway to assign the reward.
		if ( class_exists( 'O100_Reward_Gateway' ) ) {
			// Get user email
			$user = get_userdata( $user_id );
			if ( $user ) {
				$discount_val = 0;
				if ( isset( $rule['discount_config'] ) && isset( $rule['discount_config']['value'] ) ) {
					$discount_val = $rule['discount_config']['value'];
				}
				
				// Calculate expiry based on config
				$expiry_config = isset( $rule['coupon_expiry'] ) ? $rule['coupon_expiry'] : 'end_of_month';
				$expiry_date = null;
				
				if ( $expiry_config === 'end_of_month' ) {
					$expiry_date = gmdate( 'Y-m-t 23:59:59' );
				} elseif ( $expiry_config === '7_days' ) {
					$expiry_date = gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) );
				} elseif ( $expiry_config === '30_days' ) {
					$expiry_date = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
				}
				
				// Generate a unique coupon for this user
				$code = 'M' . gmdate( 'y' ) . $user_id . wp_generate_password( 4, false );
				
				// Using the O100_Reward_Gateway to generate native Woo coupon or Promo based on the source
				if ( strpos( $source_coupon, 'promo_' ) === 0 ) {
					$promo_id = intval( str_replace( 'promo_', '', $source_coupon ) );
					if ( class_exists('O100_Promotions_DB') ) {
						$promo = O100_Promotions_DB::get_promotion( $promo_id );
						if ( $promo ) {
							$new_promo_id = O100_Promotions_DB::insert([
								'source'        => 'loyalty_monthly',
								'title'         => $campaign->title . ' - ' . $user->user_email,
								'description'   => 'Monthly Reward for ' . $user->user_email,
								'rule_type'     => $promo['rule_type'],
								'action_config' => $promo['action_config'],
								'apply_to'      => $promo['apply_to'],
								'apply_to_items'=> $promo['apply_to_items'],
								'conditions'    => wp_json_encode([
									[
										'type' => 'customer_email',
										'options' => [
											'operator' => 'in_list',
											'value' => [ $user->user_email ]
										]
									]
								]),
								'usage_limit'   => 1,
								'status'        => 'active',
								'is_exclusive'  => $promo['is_exclusive'],
								'start_date'    => gmdate( 'Y-m-d H:i:s' ),
								'end_date'      => $expiry_date ?: $promo['end_date'],
								'promo_code'    => $code,
								'created_at'    => current_time( 'mysql' ),
							]);
							
							do_action( 'o100_loyalty_monthly_reward_issued', $user_id, $campaign->id, $code, $new_promo_id );
						}
					}
				} elseif ( strpos( $source_coupon, 'wc_' ) === 0 ) {
					$wc_id = intval( str_replace( 'wc_', '', $source_coupon ) );
					$original_coupon = new WC_Coupon( $wc_id );
					if ( $original_coupon && $original_coupon->get_id() ) {
						$new_coupon = new WC_Coupon();
						$new_coupon->set_code( $code );
						$new_coupon->set_discount_type( $original_coupon->get_discount_type() );
						$new_coupon->set_amount( $original_coupon->get_amount() );
						$new_coupon->set_individual_use( $original_coupon->get_individual_use() );
						$new_coupon->set_product_ids( $original_coupon->get_product_ids() );
						$new_coupon->set_excluded_product_ids( $original_coupon->get_excluded_product_ids() );
						$new_coupon->set_usage_limit( 1 );
						$new_coupon->set_usage_limit_per_user( 1 );
						$new_coupon->set_limit_usage_to_x_items( $original_coupon->get_limit_usage_to_x_items() );
						$new_coupon->set_free_shipping( $original_coupon->get_free_shipping() );
						$new_coupon->set_product_categories( $original_coupon->get_product_categories() );
						$new_coupon->set_excluded_product_categories( $original_coupon->get_excluded_product_categories() );
						$new_coupon->set_exclude_sale_items( $original_coupon->get_exclude_sale_items() );
						$new_coupon->set_minimum_amount( $original_coupon->get_minimum_amount() );
						$new_coupon->set_maximum_amount( $original_coupon->get_maximum_amount() );
						$new_coupon->set_email_restrictions( [ $user->user_email ] );
						
						if ( $expiry_date ) {
							$new_coupon->set_date_expires( strtotime( $expiry_date ) );
						} else {
							$new_coupon->set_date_expires( $original_coupon->get_date_expires() );
						}
						
						$new_coupon->save();
						
						update_post_meta( $new_coupon->get_id(), '_o100_monthly_reward_source', $campaign->id );
						
						do_action( 'o100_loyalty_monthly_reward_issued', $user_id, $campaign->id, $code, $new_coupon->get_id() );
					}
				}
			}
		}
	}

	private static function dispatch_points_reward( $user_id, $campaign, $rule ) {
		$points = isset( $rule['earn_point'] ) ? intval( $rule['earn_point'] ) : 0;
		if ( $points > 0 ) {
			if ( class_exists( 'O100_Loyalty_Engine' ) ) {
				O100_Loyalty_Engine::add_points( $user_id, $points, 'Monthly Reward: ' . $campaign->title, 0 );
			}
		}
	}
}

// TS: 20260129211513
