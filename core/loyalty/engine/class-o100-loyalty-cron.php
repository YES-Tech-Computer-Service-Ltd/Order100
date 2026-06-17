<?php
/**
 * O100 Loyalty Cron
 *
 * Handles scheduled tasks such as monthly recurring rewards.
 */

defined( 'ABSPATH' ) or die;

class O100_Loyalty_Cron {

	public static function init() {
		add_action( 'o100_daily_loyalty_cron', [ __CLASS__, 'process_automation_rewards' ] );
		add_action( 'o100_daily_loyalty_cron', [ __CLASS__, 'process_expiring_reminders' ] );
		add_action( 'o100_daily_loyalty_cron', [ __CLASS__, 'handle_points_expiry' ] );
		add_action( 'o100_daily_loyalty_cron', [ __CLASS__, 'handle_points_expiry_reminders' ] );

		if ( ! wp_next_scheduled( 'o100_daily_loyalty_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'o100_daily_loyalty_cron' );
		}
	}

	public static function process_automation_rewards() {
		if ( ! class_exists( 'O100_Loyalty_DB' ) ) {
			return;
		}

		// Fetch active automation campaigns
		$campaigns = O100_Loyalty_DB::get_active_campaigns( 'automation' );
		if ( empty( $campaigns ) ) {
			return;
		}

		foreach ( $campaigns as $camp ) {
			if ( $camp->status !== 'active' ) {
				continue;
			}

			// Parse config
			$rule = is_string( $camp->earn_config ) ? json_decode( $camp->earn_config, true ) : (array) $camp->earn_config;
			if ( empty( $rule ) || empty( $rule['schedule_config'] ) ) {
				continue;
			}

			$schedule = $rule['schedule_config'];
			$freq = isset( $schedule['freq'] ) ? $schedule['freq'] : (isset( $schedule['frequency'] ) ? $schedule['frequency'] : 'monthly');
			$trigger = isset( $schedule['trigger_rule'] ) ? $schedule['trigger_rule'] : 'specific_day';
			$advance_days = isset( $schedule['advance_days'] ) ? intval( $schedule['advance_days'] ) : 0;
			$target_audience = isset( $schedule['target_audience'] ) ? $schedule['target_audience'] : 'all';

			// Calculate the future target date we are evaluating today
			$target_time = strtotime( "+{$advance_days} days" );
			$target_y = gmdate( 'Y', $target_time );
			$target_m = gmdate( 'm', $target_time );
			$target_d = gmdate( 'd', $target_time );

			$reward_type = isset( $rule['earn_reward'] ) && ! empty( $rule['earn_reward'] ) ? 'coupon' : 'points';
			
			// Find eligible users
			$users = self::get_eligible_users( $target_audience );
			
			foreach ( $users as $user_id ) {
				// Check trigger match
				$matched = false;

				if ( $trigger === 'specific_day' ) {
					$req_day = isset( $schedule['day_of_month'] ) ? $schedule['day_of_month'] : 1;
					if ( $req_day === 'last' ) {
						$last_day_of_target_month = gmdate('t', $target_time);
						if ( intval( $target_d ) === intval( $last_day_of_target_month ) ) {
							$matched = true;
						}
					} else {
						if ( intval( $target_d ) === intval( $req_day ) ) {
							$matched = true;
						}
					}
				} elseif ( $trigger === 'specific_date' ) {
					$req_date = isset( $schedule['target_date'] ) ? $schedule['target_date'] : '';
					if ( ! empty( $req_date ) ) {
						$parsed_time = strtotime( $req_date );
						if ( $parsed_time ) {
							$req_m = gmdate( 'm', $parsed_time );
							$req_d = gmdate( 'd', $parsed_time );
							if ( $req_m === $target_m && $req_d === $target_d ) {
								$matched = true;
							}
						}
					}
				} elseif ( $trigger === 'birthday' ) {
					$birthday = get_user_meta( $user_id, 'o100_user_birthday', true );
					if ( ! empty( $birthday ) ) {
						// Format is usually YYYY-MM-DD
						$parts = explode( '-', $birthday );
						if ( count( $parts ) === 3 && $parts[1] === $target_m && $parts[2] === $target_d ) {
							$matched = true;
						}
					}
				} elseif ( $trigger === 'registration_anniversary' ) {
					$user_data = get_userdata( $user_id );
					if ( $user_data ) {
						$reg_time = strtotime( $user_data->user_registered );
						$reg_m = gmdate( 'm', $reg_time );
						$reg_d = gmdate( 'd', $reg_time );
						
						if ( $freq === 'yearly' ) {
							if ( $reg_m === $target_m && $reg_d === $target_d ) {
								$matched = true;
							}
						} else { // monthly
							if ( $reg_d === $target_d ) {
								$matched = true;
							}
						}
					}
				}

				if ( ! $matched ) {
					continue;
				}

				// Check if already sent
				if ( $freq === 'yearly' ) {
					$meta_key = "o100_auto_reward_{$camp->id}_{$target_y}";
				} else {
					$meta_key = "o100_auto_reward_{$camp->id}_{$target_y}_{$target_m}";
				}

				if ( get_user_meta( $user_id, $meta_key, true ) ) {
					continue; // Already processed for this cycle
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

	public static function process_expiring_reminders() {
		$campaigns = O100_Loyalty_DB::get_active_campaigns( 'automation' );
		if ( empty( $campaigns ) ) {
			return;
		}

		if ( ! class_exists( 'O100_Promotions_DB' ) ) {
			require_once O100_PATH . 'core/promotions/engine/class-o100-promotions-db.php';
		}
		
		global $wpdb;
		$table = O100_Promotions_DB::table_name();

		foreach ( $campaigns as $camp ) {
			$rule = json_decode( $camp->earn_config, true );
			if ( ! is_array( $rule ) ) {
				continue;
			}
			
			$email_config = isset( $rule['email_config'] ) ? $rule['email_config'] : [];
			$reminder_days = isset( $email_config['reminder_days'] ) ? intval( $email_config['reminder_days'] ) : 3;
			
			if ( $reminder_days <= 0 || empty( $email_config['reminder_subject'] ) ) {
				continue; // Reminder disabled or no subject
			}
			
			// Find active unused coupons generated by this campaign that expire exactly $reminder_days from now.
			// The parent_id of the generated promo is $camp->id (wait, no, parent_id is the template promo ID, but we also save _o100_monthly_reward_source for WC coupons. For promos, we have parent_id).
			// Actually, the promo description contains "Automated Reward for user_email", but the most reliable is that it's loyalty_auto.
			// Let's just find ALL loyalty_auto promos that expire in X days and haven't been reminded.
			
			$target_date = gmdate( 'Y-m-d', strtotime( "+{$reminder_days} days" ) );
			
			// Find promos expiring on this exact date
			$query = $wpdb->prepare( "
				SELECT * 
				FROM {$table} 
				WHERE source = 'loyalty_auto' 
				  AND status = 'active' 
				  AND usage_count < usage_limit 
				  AND DATE(end_date) = %s
			", $target_date );
			
			$expiring_promos = $wpdb->get_results( $query );
			
			foreach ( $expiring_promos as $promo ) {
				// Check if reminder was already sent for this promo
				$meta_key = '_o100_reminder_sent_' . $promo->id;
				if ( get_option( $meta_key ) ) {
					continue;
				}
				
				// Extract email from conditions
				$conditions = json_decode( $promo->conditions, true );
				$user_email = '';
				if ( is_array( $conditions ) ) {
					foreach ( $conditions as $cond ) {
						if ( isset( $cond['type'] ) && $cond['type'] === 'customer_email' ) {
							$user_email = $cond['options']['value'][0] ?? '';
							break;
						}
					}
				}
				
				if ( ! empty( $user_email ) ) {
					$user = get_user_by( 'email', $user_email );
					if ( $user ) {
						// Assuming parent campaign is the automation reward one (we can use $rule from $camp, but wait... 
						// What if there are multiple automation campaigns? We need the exact campaign this promo belongs to.
						// Promo description is "Automated Reward for user_email" - wait, we should just fire the hook with the promo data.
						do_action( 'o100_loyalty_auto_reward_expiring', $user->ID, $camp->id, $promo->promo_code, $rule, $reminder_days );
						update_option( $meta_key, 'yes', 'no' );
					}
				}
			}
		}
	}

	private static function get_eligible_users( $target_audience ) {
		$args = [
			'fields' => 'ID',
		];

		if ( $target_audience === 'levels' ) {
			// Query users who have an active VIP level
			$args['meta_query'] = [
				[
					'key'     => '_o100_user_level',
					'compare' => 'EXISTS',
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
				$expiry_type = isset( $rule['discount_config'] ) && isset( $rule['discount_config']['expiry_type'] ) ? $rule['discount_config']['expiry_type'] : 'end_of_month';
				$expiry_days = isset( $rule['discount_config'] ) && isset( $rule['discount_config']['expiry_days'] ) ? intval( $rule['discount_config']['expiry_days'] ) : 0;
				$expiry_date = null;
				
				if ( $expiry_type === 'end_of_month' ) {
					$expiry_date = gmdate( 'Y-m-t 23:59:59' );
				} elseif ( $expiry_type === 'days' && $expiry_days > 0 ) {
					$expiry_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_days} days" ) );
				}
				
				// Generate a unique coupon for this user
				$code = 'M' . gmdate( 'y' ) . $user_id . wp_generate_password( 4, false );
				
				// Using the O100_Reward_Gateway to generate native Woo coupon or Promo based on the source
				if ( strpos( $source_coupon, 'promo_' ) === 0 ) {
					$promo_id = intval( str_replace( 'promo_', '', $source_coupon ) );
					if ( class_exists('O100_Promotions_DB') ) {
						$promo = O100_Promotions_DB::get( $promo_id );
						if ( $promo ) {
							$new_promo_id = O100_Promotions_DB::insert([
								'source'        => 'loyalty_auto',
								'parent_id'     => $promo_id,
								'title'         => $campaign->title . ' - ' . $user->user_email,
								'description'   => 'Automated Reward for ' . $user->user_email,
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
								'usage_limit'   => $promo['usage_limit'] > 0 ? $promo['usage_limit'] : 1,
								'status'        => 'active',
								'is_exclusive'  => $promo['is_exclusive'],
								'start_date'    => gmdate( 'Y-m-d H:i:s' ),
								'end_date'      => $expiry_date,
								'promo_code'    => $code,
								'created_at'    => current_time( 'mysql' ),
							]);
							
							do_action( 'o100_loyalty_auto_reward_issued', $user_id, $campaign->id, $code, $new_promo_id, $rule );
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
						$new_coupon->set_usage_limit( $original_coupon->get_usage_limit() > 0 ? $original_coupon->get_usage_limit() : 1 );
						$new_coupon->set_usage_limit_per_user( $original_coupon->get_usage_limit_per_user() > 0 ? $original_coupon->get_usage_limit_per_user() : 1 );
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
						
						update_post_meta( $new_coupon->get_id(), '_o100_auto_reward_source', $campaign->id );
						
						do_action( 'o100_loyalty_auto_reward_issued', $user_id, $campaign->id, $code, $new_coupon->get_id() );
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
	
	public static function handle_points_expiry() {
		if ( ! class_exists( 'O100_Loyalty_DB' ) ) return;
		global $wpdb;

		// Find expired buckets
		$expired = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE type IN ('earn','adjust') AND points_remaining > 0 AND expires_at < %s",
			O100_Loyalty_DB::table_transactions(), current_time('mysql', 1)
		) );

		foreach ( $expired as $bucket ) {
			$points_to_expire = intval($bucket->points_remaining);
			if ( $points_to_expire <= 0 ) continue;
			
			// Zero out the remaining points in the bucket
			$wpdb->query( $wpdb->prepare(
				"UPDATE %i SET points_remaining = 0 WHERE id = %d",
				O100_Loyalty_DB::table_transactions(), $bucket->id
			) );
			
			// Deduct from account balance
			$acct = O100_Loyalty_DB::get_account( $bucket->account_id );
			if ( $acct ) {
				$new_balance = max(0, $acct->points_balance - $points_to_expire);
				$wpdb->update( O100_Loyalty_DB::table_accounts(), [
					'points_balance' => $new_balance
				], [ 'id' => $acct->id ] );
				
				// Log expiration
				O100_Loyalty_DB::log_transaction( $acct->id, 'expire', $points_to_expire, $new_balance, 'cron', $bucket->id, null, 'Points expired' );
			}
		}
	}
	
	public static function handle_points_expiry_reminders() {
		if ( ! class_exists( 'O100_Loyalty_DB' ) ) return;
		global $wpdb;
		
		$settings = O100_Loyalty_DB::get_settings();
		$reminder_val = isset( $settings['points_expiry_reminder_value'] ) ? intval( $settings['points_expiry_reminder_value'] ) : 30;
		$reminder_unit = isset( $settings['points_expiry_reminder_unit'] ) ? $settings['points_expiry_reminder_unit'] : 'days';
		
		// Remind users N days/weeks/months before expiry
		$target_date = gmdate( 'Y-m-d', strtotime( "+{$reminder_val} {$reminder_unit}" ) );
		
		$expiring_soon = $wpdb->get_results( $wpdb->prepare(
			"SELECT account_id, SUM(points_remaining) as total_expiring, MAX(expires_at) as expiry_date
			FROM %i 
			WHERE type IN ('earn','adjust') 
			  AND points_remaining > 0 
			  AND DATE(expires_at) = %s 
			GROUP BY account_id",
			O100_Loyalty_DB::table_transactions(), $target_date
		) );
		
		foreach ( $expiring_soon as $row ) {
			$acct = O100_Loyalty_DB::get_account( $row->account_id );
			if ( $acct && $acct->user_id > 0 ) {
				// Trigger Emails/SMS
				do_action( 'o100_trigger_loyalty_points_expiring_email', $acct->user_id, intval($row->total_expiring), $row->expiry_date );
				do_action( 'o100_loyalty_points_expiring_sms', $acct->user_id, intval($row->total_expiring), $row->expiry_date );
			}
		}
	}
}
