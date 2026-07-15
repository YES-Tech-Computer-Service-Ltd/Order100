<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class O100_Loyalty_Campaign_Controller {

	public static function handle_save_wizard() {
			 // Ensure clean JSON response
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
			}
	
			if ( ! current_user_can( 'manage_options' ) ) {
				return O100_REST_Controller::error( [ 'message' => 'Unauthorized access.' ] );
			}
	
			$card_type      = isset( $_POST['card_type'] ) ? sanitize_text_field( wp_unslash( $_POST['card_type'] ) ) : '';
			$campaign_name  = isset( $_POST['campaign_name'] ) ? sanitize_text_field( wp_unslash( $_POST['campaign_name'] ) ) : '';
			$campaign_desc  = isset( $_POST['campaign_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['campaign_desc'] ) ) : '';
			$frontend_message  = isset( $_POST['frontend_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['frontend_message'] ) ) : '';
			$conditions_raw = isset( $_POST['conditions'] ) ? json_decode( wp_unslash( $_POST['conditions'] ), true ) : [];
	
			if ( ! in_array( $card_type, ['birthday', 'points', 'punch_card', 'referral', 'spend_save', 'monthly_reward', 'automation'] ) ) {
				return O100_REST_Controller::error( [ 'message' => 'Invalid configuration type.' ] );
			}
	
			if ( ! class_exists( 'O100_Loyalty_DB' ) ) {
				return O100_REST_Controller::error( [ 'message' => 'Loyalty Engine is not loaded.' ] );
			}
	
			// Helper: Create coupon reward via O100 Promotions (replaces WPLoyalty Rewards)
			$create_coupon = function( $name, $value, $type, $expiry, $limit, $apply_to = 'all_products', $apply_to_items = '[]' ) {
				if ( ! class_exists( 'O100_Promotions_DB' ) ) {
					require_once O100_PATH . 'core/promotions/engine/class-o100-promotions-db.php';
				}
				$promo_id = O100_Promotions_DB::insert([
					'source'        => 'loyalty',
					'title'         => $name . ' Coupon',
					'description'   => 'Auto-generated coupon for ' . $name,
					'rule_type'     => 'simple',
					'action_config' => wp_json_encode([ 'discount_type' => $type, 'discount_value' => floatval($value) ]),
					'apply_to'      => $apply_to,
					'apply_to_items'=> $apply_to_items,
					'conditions'    => '[]',
					'usage_limit'   => intval($limit),
					'status'        => 'active',
					'is_exclusive'  => 1,
					'end_date'      => $expiry > 0 ? gmdate('Y-m-d H:i:s', strtotime('+'.$expiry.' days')) : null,
				]);
				return $promo_id ? 'promo_' . $promo_id : false;
			};
	
			// Helper: Save global point conversion rate to o100_loyalty_settings
			// Removed legacy WPLoyalty wlr_rewards table sync to enforce 100% native architecture
			$create_global_conversion = function( $conv_pts, $conv_val ) {
				$settings = O100_Loyalty_DB::get_settings();
				$settings['conversion_points'] = intval($conv_pts);
				$settings['conversion_value']  = floatval($conv_val);
				O100_Loyalty_DB::save_settings( $settings );
			};
	
			// ALWAYS process global conversion if the payload provides it
			if ( isset($_POST['conversion_points']) && !empty($_POST['conversion_points']) ) {
				$create_global_conversion( $_POST['conversion_points'], $_POST['conversion_value'] );
			}
	
			$action_type = '';
			$campaign_type = 'point';
			$point_rule_arr = [];
	
			$reward_type   = isset( $_POST['reward_type'] ) ? sanitize_text_field( wp_unslash( $_POST['reward_type'] ) ) : 'points';
			$reward_points = isset( $_POST['reward_points'] ) ? intval( $_POST['reward_points'] ) : 100;
			$discount_type = isset( $_POST['discount_type'] ) ? sanitize_text_field( wp_unslash( $_POST['discount_type'] ) ) : 'fixed';
			$discount_value= isset( $_POST['discount_value'] ) ? floatval( $_POST['discount_value'] ) : 10;
			$discount_expiry=isset( $_POST['discount_expiry'] ) ? intval( $_POST['discount_expiry'] ) : 30;
			$discount_limit= isset( $_POST['discount_limit'] ) ? intval( $_POST['discount_limit'] ) : 0;
			
			$email_config = [
				'banner_image_url' => isset( $_POST['email_banner'] ) ? sanitize_url( wp_unslash( $_POST['email_banner'] ) ) : '',
				'new_subject'      => isset( $_POST['email_new_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['email_new_subject'] ) ) : '',
				'new_body'         => isset( $_POST['email_new_body'] ) ? wp_kses_post( wp_unslash( $_POST['email_new_body'] ) ) : '',
				'reminder_subject' => isset( $_POST['email_reminder_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['email_reminder_subject'] ) ) : '',
				'reminder_body'    => isset( $_POST['email_reminder_body'] ) ? wp_kses_post( wp_unslash( $_POST['email_reminder_body'] ) ) : '',
				'reminder_days'    => isset( $_POST['email_reminder_days'] ) ? intval( wp_unslash( $_POST['email_reminder_days'] ) ) : 3,
			];
	
			// -------------------------------------------------------------
			// 1. Birthday Campaign
			// -------------------------------------------------------------
			$point_rule_arr['email_config'] = $email_config;
	
			if ( $card_type === 'birthday' ) {
				$action_type = 'birthday';
				$point_rule_arr = [
					'earn_type'          => 'fixed',
					'earn_point'         => $reward_points,
					'birthday_message'   => $frontend_message,
					'birthday_earn_type' => 'on_their_birthday'
				];
	
				if ( $reward_type === 'discount' ) {
					$discount_apply_to   = isset( $_POST['discount_apply_to'] ) ? sanitize_text_field( $_POST['discount_apply_to'] ) : 'cart';
	$discount_products   = isset( $_POST['discount_products'] ) ? sanitize_text_field( $_POST['discount_products'] ) : '';
	$discount_categories = isset( $_POST['discount_categories'] ) ? sanitize_text_field( $_POST['discount_categories'] ) : '';
	
					$reward_id = $create_coupon( $campaign_name, $discount_value, $discount_type, $discount_expiry, $discount_limit, $discount_apply_to, $discount_products, $discount_categories );
					if ( $reward_id ) {
						$campaign_type = 'coupon';
						$point_rule_arr['earn_reward'] = $reward_id;
					}
					// Store discount details inline for edit-mode refill
					$point_rule_arr['discount_config'] = [
						'type'   => $discount_type,
						'value'  => $discount_value,
						'expiry' => $discount_expiry,
						'limit'  => $discount_limit,
					];
				} elseif ( $reward_type === 'free_item' ) {
					$free_item_id = isset( $_POST['punch_reward_product'] ) ? intval( $_POST['punch_reward_product'] ) : 0;
					if ( $free_item_id > 0 ) {
						$reward_id = $create_coupon( $campaign_name, 100, 'percent', $discount_expiry, $discount_limit, 'specific_products', wp_json_encode( [ $free_item_id ] ) );
						if ( $reward_id ) {
							$campaign_type = 'coupon';
							$point_rule_arr['earn_reward'] = $reward_id;
						}
						$point_rule_arr['discount_config'] = [
							'type'       => 'free_item',
							'product_id' => $free_item_id,
							'expiry'     => $discount_expiry,
							'limit'      => $discount_limit,
						];
					}
				}
	
				// Override global Birthday Capture settings
				$settings = O100_Loyalty_DB::get_settings();
				$settings['birthday_display_place']     = 'checkout,registration,account_details';
				$settings['is_one_time_birthdate_edit'] = isset( $_POST['allow_birthday_edit'] ) && $_POST['allow_birthday_edit'] === 'yes' ? 'yes' : 'no';
				O100_Loyalty_DB::save_settings( $settings );
			}
			// -------------------------------------------------------------
			// 1.5. Monthly Reward Campaign
			// -------------------------------------------------------------
			elseif ( $card_type === 'monthly_reward' ) {
				$action_type = 'monthly_reward';
				$monthly_coupon = isset( $_POST['monthly_coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['monthly_coupon'] ) ) : '';
				$point_rule_arr = [
					'earn_type'       => 'fixed',
					'earn_point'      => $reward_points,
					'target_audience' => isset( $_POST['monthly_target_audience'] ) ? sanitize_text_field( wp_unslash( $_POST['monthly_target_audience'] ) ) : 'all',
					'coupon_expiry'   => isset( $_POST['monthly_coupon_expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['monthly_coupon_expiry'] ) ) : 'end_of_month',
				];
	
				if ( ! empty( $monthly_coupon ) ) {
					$campaign_type = 'coupon';
					$point_rule_arr['earn_reward'] = $monthly_coupon;
					$point_rule_arr['discount_config'] = [
						'type' => 'existing'
					];
				} elseif ( $reward_type === 'discount' ) {
					$discount_apply_to   = isset( $_POST['discount_apply_to'] ) ? sanitize_text_field( $_POST['discount_apply_to'] ) : 'cart';
					$discount_products   = isset( $_POST['discount_products'] ) ? sanitize_text_field( $_POST['discount_products'] ) : '';
					$discount_categories = isset( $_POST['discount_categories'] ) ? sanitize_text_field( $_POST['discount_categories'] ) : '';
	
					$final_apply_to = 'all_products';
					$final_apply_to_items = '[]';
					
					if ( $discount_apply_to === 'product' ) {
						if ( ! empty( $discount_products ) ) {
							$final_apply_to = 'specific_products';
							$final_apply_to_items = wp_json_encode( explode( ',', $discount_products ) );
						} elseif ( ! empty( $discount_categories ) ) {
							$final_apply_to = 'specific_categories';
							$final_apply_to_items = wp_json_encode( explode( ',', $discount_categories ) );
						}
					}
	
					$reward_id = $create_coupon( $campaign_name, $discount_value, $discount_type, $discount_expiry, $discount_limit, $final_apply_to, $final_apply_to_items );
					if ( $reward_id ) {
						$campaign_type = 'coupon';
						$point_rule_arr['earn_reward'] = $reward_id;
					}
					$point_rule_arr['discount_config'] = [
						'type'   => $discount_type,
						'value'  => $discount_value,
						'expiry' => $discount_expiry,
						'limit'  => $discount_limit,
					];
				} elseif ( $reward_type === 'free_item' ) {
					$free_item_id = isset( $_POST['punch_reward_product'] ) ? intval( $_POST['punch_reward_product'] ) : 0;
					if ( $free_item_id > 0 ) {
						$reward_id = $create_coupon( $campaign_name, 100, 'percent', $discount_expiry, $discount_limit, 'specific_products', wp_json_encode( [ $free_item_id ] ) );
						if ( $reward_id ) {
							$campaign_type = 'coupon';
							$point_rule_arr['earn_reward'] = $reward_id;
						}
						$point_rule_arr['discount_config'] = [
							'type'       => 'free_item',
							'product_id' => $free_item_id,
							'expiry'     => $discount_expiry,
							'limit'      => $discount_limit,
						];
					}
				}
			}
			// -------------------------------------------------------------
			// 1.5. Automation Campaign
			// -------------------------------------------------------------
			elseif ( $card_type === 'automation' ) {
				$action_type = 'automation';
				$point_rule_arr = [
					'earn_type'       => 'fixed',
					'earn_point'      => $reward_points,
					'target_audience' => isset( $_POST['monthly_target_audience'] ) ? sanitize_text_field( wp_unslash( $_POST['monthly_target_audience'] ) ) : 'all',
				];
				
				$schedule_config = [
					'freq'         => isset( $_POST['auto_freq'] ) ? sanitize_text_field( $_POST['auto_freq'] ) : 'monthly',
					'trigger_rule' => isset( $_POST['auto_trigger'] ) ? sanitize_text_field( $_POST['auto_trigger'] ) : 'specific_day',
					'advance_days' => isset( $_POST['auto_advance'] ) ? intval( $_POST['auto_advance'] ) : 0,
				];
				
				if ($schedule_config['freq'] === 'yearly' && $schedule_config['trigger_rule'] === 'specific_date') {
					$schedule_config['target_date'] = sanitize_text_field($_POST['auto_day'] ?? '');
				} elseif ($schedule_config['freq'] === 'monthly' && $schedule_config['trigger_rule'] === 'specific_day') {
					$val = sanitize_text_field($_POST['auto_day'] ?? '');
					if ($val === 'last') {
						$schedule_config['day_of_month'] = 'last';
					} elseif (strpos($val, '-') !== false) {
						$parts = explode('-', $val);
						$schedule_config['day_of_month'] = intval(end($parts));
					} else {
						$schedule_config['day_of_month'] = intval($val);
					}
				}
				$point_rule_arr['schedule_config'] = $schedule_config;
	
				$existing_coupon = isset( $_POST['reward_existing_coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['reward_existing_coupon'] ) ) : '';
	
				if ( ! empty( $existing_coupon ) ) {
					$campaign_type = 'coupon';
					$point_rule_arr['earn_reward'] = $existing_coupon;
					$point_rule_arr['discount_config'] = [
						'type' => 'existing'
					];
				} elseif ( $reward_type === 'discount' ) {
					$discount_apply_to   = isset( $_POST['discount_apply_to'] ) ? sanitize_text_field( $_POST['discount_apply_to'] ) : 'cart';
					$discount_products   = isset( $_POST['discount_products'] ) ? sanitize_text_field( $_POST['discount_products'] ) : '';
					$discount_categories = isset( $_POST['discount_categories'] ) ? sanitize_text_field( $_POST['discount_categories'] ) : '';
	
					$final_apply_to = 'all_products';
					$final_apply_to_items = '[]';
					
					if ( $discount_apply_to === 'product' ) {
						if ( ! empty( $discount_products ) ) {
							$final_apply_to = 'specific_products';
							$final_apply_to_items = wp_json_encode( explode( ',', $discount_products ) );
						} elseif ( ! empty( $discount_categories ) ) {
							$final_apply_to = 'specific_categories';
							$final_apply_to_items = wp_json_encode( explode( ',', $discount_categories ) );
						}
					}
	
					$reward_id = $create_coupon( $campaign_name, $discount_value, $discount_type, $discount_expiry, $discount_limit, $final_apply_to, $final_apply_to_items );
					if ( $reward_id ) {
						$campaign_type = 'coupon';
						$point_rule_arr['earn_reward'] = $reward_id;
					}
					$point_rule_arr['discount_config'] = [
						'type'   => $discount_type,
						'value'  => $discount_value,
						'expiry' => $discount_expiry,
						'limit'  => $discount_limit,
					];
				} elseif ( $reward_type === 'free_item' ) {
					$free_item_id = isset( $_POST['punch_reward_product'] ) ? intval( $_POST['punch_reward_product'] ) : 0;
					if ( $free_item_id > 0 ) {
						$reward_id = $create_coupon( $campaign_name, 100, 'percent', $discount_expiry, $discount_limit, 'specific_products', wp_json_encode( [ $free_item_id ] ) );
						if ( $reward_id ) {
							$campaign_type = 'coupon';
							$point_rule_arr['earn_reward'] = $reward_id;
						}
						$point_rule_arr['discount_config'] = [
							'type'       => 'free_item',
							'product_id' => $free_item_id,
							'expiry'     => $discount_expiry,
							'limit'      => $discount_limit,
						];
					}
				}
			}
			// -------------------------------------------------------------
			// 2. Points Program
			// -------------------------------------------------------------
			elseif ( $card_type === 'points' ) {
				$action_type = 'point_for_purchase';
				$point_earn_price = isset( $_POST['point_earn_price'] ) ? floatval( $_POST['point_earn_price'] ) : 1;
				
				$point_rule_arr = [
					'wlr_point_earn_price'         => $point_earn_price,
					'earn_point'                   => $reward_points,
					'earn_reward'                  => '',
					'minimum_point'                => 0,
					'maximum_point'                => 0,
					'variable_product_message'     => 'Earn up to {o100_product_points} {o100_points_label}.',
					'single_product_message'       => 'Purchase & earn {o100_product_points} {o100_points_label}!',
					'is_rounded_edge'              => 'yes',
					'display_product_message_page' => 'all'
				];
	
			}
			// -------------------------------------------------------------
			// 3. Visual Punch Card
			// -------------------------------------------------------------
			elseif ( $card_type === 'punch_card' ) {
				$action_type = 'o100_punch_card'; // Using custom action type to detach from WPLoyalty Engine
				
				// Inject participating products into conditions if defined
				$punch_products = isset( $_POST['punch_products'] ) ? sanitize_text_field( wp_unslash( $_POST['punch_products'] ) ) : '';
				if ( empty( $punch_products ) ) {
					return O100_REST_Controller::error( [ 'message' => 'Punch Card requires selecting at least one Participating Product.' ] );
				}
				$product_ids = array_map( 'intval', explode( ',', $punch_products ) );
				$conditions_raw[] = [
					'type' => 'products',
					'options' => [
						'operator' => 'in_list',
						'value'    => $product_ids
					]
				];
	
				// We hardcode earn point to 1 (1 stamp) and earn price to 1.
				$point_rule_arr = [
					'wlr_point_earn_price'         => 1,
					'earn_point'                   => 1,
					'earn_reward'                  => '',
					'minimum_point'                => 0,
					'maximum_point'                => 0,
					'variable_product_message'     => $frontend_message,
					'single_product_message'       => $frontend_message,
					'is_rounded_edge'              => 'yes',
					'display_product_message_page' => 'all',
					'is_punch_card'                => 'yes'
				];
	
				$punch_count = isset( $_POST['punch_count'] ) ? intval( $_POST['punch_count'] ) : 5;
				
				// Build punch card reward config (stored natively in campaign reward_config)
				$punch_reward_option = isset( $_POST['punch_reward_option'] ) ? $_POST['punch_reward_option'] : 'same';
				$punch_reward_products = [];
				if ( $punch_reward_option === 'same' ) {
					$punch_reward_products = $product_ids;
				} else {
					$punch_reward_product = isset( $_POST['punch_reward_product'] ) ? intval( $_POST['punch_reward_product'] ) : 0;
					if ( $punch_reward_product > 0 ) {
						$punch_reward_products[] = $punch_reward_product;
					}
				}
	
				if ( empty( $punch_reward_products ) ) {
					return O100_REST_Controller::error( [ 'message' => 'Punch Card requires selecting a Reward Product.' ] );
				}
	
				// Store reward config directly in campaign (no separate rewards table needed)
				$point_rule_arr['reward_config'] = [
					'type'             => 'free_product',
					'required_stamps'  => $punch_count,
					'reward_option'    => $punch_reward_option,
					'reward_products'  => $punch_reward_products,
				];
			}
			// -------------------------------------------------------------
			// 4. Referral Program
			// -------------------------------------------------------------
			elseif ( $card_type === 'referral' ) {
				$action_type = 'referral';
				
				$adv_type   = isset( $_POST['advocate_type'] ) ? sanitize_text_field( $_POST['advocate_type'] ) : 'point';
				$adv_amount = isset( $_POST['advocate_amount'] ) ? intval( $_POST['advocate_amount'] ) : 100;
				$fri_type   = isset( $_POST['friend_type'] ) ? sanitize_text_field( $_POST['friend_type'] ) : 'point';
				$fri_amount = isset( $_POST['friend_amount'] ) ? intval( $_POST['friend_amount'] ) : 10;
	
				$point_rule_arr = [
					'advocate' => [
						'campaign_type' => $adv_type === 'coupon' ? 'coupon' : 'point',
						'earn_type'     => 'fixed_point', // or 'fixed'
						'earn_point'    => $adv_amount,
						'earn_reward'   => ''
					],
					'friend'   => [
						'campaign_type' => $fri_type === 'coupon' ? 'coupon' : 'point',
						'earn_type'     => 'fixed_point',
						'earn_point'    => $fri_amount,
						'earn_reward'   => ''
					]
				];
	
				// Advocate coupon — value is 'promo_X' or 'wc_X' from dropdown
				if ( $adv_type === 'coupon' ) {
					$point_rule_arr['advocate']['earn_reward'] = sanitize_text_field( $_POST['advocate_coupon'] ?? '' );
				}
	
				// Friend coupon — value is 'promo_X' or 'wc_X' from dropdown
				if ( $fri_type === 'coupon' ) {
					$point_rule_arr['friend']['earn_reward'] = sanitize_text_field( $_POST['friend_coupon'] ?? '' );
				}
			}
			// -------------------------------------------------------------
			// 5. Spend & Save
			// -------------------------------------------------------------
			elseif ( $card_type === 'spend_save' ) {
				$action_type = 'subtotal';
				$min_subtotal = isset( $_POST['spend_min_subtotal'] ) ? floatval( $_POST['spend_min_subtotal'] ) : 100;
				
				$point_rule_arr = [
					'min_subtotal' => $min_subtotal,
					'max_subtotal' => 0,
					'earn_reward'  => '',
					'is_rounded_edge'              => 'yes',
					'display_product_message_page' => 'all'
				];
	
				if ( $reward_type === 'discount' ) {
					$discount_apply_to   = isset( $_POST['discount_apply_to'] ) ? sanitize_text_field( $_POST['discount_apply_to'] ) : 'cart';
	$discount_products   = isset( $_POST['discount_products'] ) ? sanitize_text_field( $_POST['discount_products'] ) : '';
	$discount_categories = isset( $_POST['discount_categories'] ) ? sanitize_text_field( $_POST['discount_categories'] ) : '';
	
					$reward_id = $create_coupon( $campaign_name, $discount_value, $discount_type, $discount_expiry, $discount_limit, $discount_apply_to, $discount_products, $discount_categories );
					if ( $reward_id ) {
						$campaign_type = 'coupon';
						$point_rule_arr['earn_reward'] = $reward_id;
					}
					$point_rule_arr['discount_config'] = [
						'type'   => $discount_type,
						'value'  => $discount_value,
						'expiry' => $discount_expiry,
						'limit'  => $discount_limit,
					];
				} elseif ( $reward_type === 'free_item' ) {
					$free_item_id = isset( $_POST['punch_reward_product'] ) ? intval( $_POST['punch_reward_product'] ) : 0;
					if ( $free_item_id > 0 ) {
						$reward_id = $create_coupon( $campaign_name, 100, 'percent', $discount_expiry, $discount_limit, 'specific_products', wp_json_encode( [ $free_item_id ] ) );
						if ( $reward_id ) {
							$campaign_type = 'coupon';
							$point_rule_arr['earn_reward'] = $reward_id;
						}
						$point_rule_arr['discount_config'] = [
							'type'       => 'free_item',
							'product_id' => $free_item_id,
							'expiry'     => $discount_expiry,
							'limit'      => $discount_limit,
						];
					}
				}
			}
	
			// -------------------------------------------------------------
			// Save Campaign
			// -------------------------------------------------------------
			$req_campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;

			// Premium Limit Check for Automation
			if ( $card_type === 'automation' && $req_campaign_id === 0 ) {
				$active_automations = O100_Loyalty_DB::get_active_campaigns('automation');
				if ( is_array($active_automations) && count($active_automations) >= 1 ) {
					if ( function_exists('O100_License') && ! O100_License()->is_premium() ) {
						return O100_REST_Controller::error( [ 'message' => 'Free version is limited to 1 Scheduled Automation. Please upgrade to Pro.' ] );
					}
				}
			}
			
			$campaign_data = [
				'title'                  => $campaign_name,
				'description'            => $campaign_desc,
				'type'                   => $card_type,
				'earn_config'            => wp_json_encode( $point_rule_arr ),
				'conditions'             => wp_json_encode( is_array($conditions_raw) ? $conditions_raw : [] ),
				'condition_relationship' => 'and',
				'priority'               => 10,
				'status'                 => 'active',
				'is_show_way_to_earn'    => 1,
				'ordering'               => 1,
			];
	
			try {
				if ( $req_campaign_id > 0 ) {
					O100_Loyalty_DB::update_campaign( $req_campaign_id, $campaign_data );
					$campaign_id = $req_campaign_id;
				} else {
					$campaign_id = O100_Loyalty_DB::insert_campaign( $campaign_data );
				}
				if ( ! $campaign_id ) {
					global $wpdb;
					return O100_REST_Controller::error( [ 'message' => 'Save Failed: ' . $wpdb->last_error ] );
				}
			} catch ( Exception $e ) {
				return O100_REST_Controller::error( [ 'message' => $e->getMessage() ] );
			}
	
			return O100_REST_Controller::success( [ 'message' => 'Configuration saved successfully.' ] );
		}

	public static function handle_delete_campaign() {
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return O100_REST_Controller::error( [ 'message' => 'Unauthorized access.' ] );
			}
			
			$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
			if ( $campaign_id > 0 ) {
				O100_Loyalty_DB::delete_campaign( $campaign_id );
				return O100_REST_Controller::success();
			}
			
			return O100_REST_Controller::error( [ 'message' => 'Invalid campaign ID.' ] );
		}

	public static function handle_get_campaign() {
			 // Ensure clean JSON response
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return O100_REST_Controller::error( [ 'message' => 'Unauthorized access.' ] );
			}
			
			$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
			if ( $campaign_id > 0 ) {
				$campaign = O100_Loyalty_DB::get_campaign( $campaign_id );
				if ( $campaign ) {
					// Decode JSON fields
					if ( ! empty( $campaign->earn_config ) ) {
						$campaign->point_rule = json_decode( $campaign->earn_config, true );
					} else {
						$campaign->point_rule = [];
					}
					if ( ! empty( $campaign->conditions ) ) {
						$campaign->conditions = json_decode( $campaign->conditions, true );
					} else {
						$campaign->conditions = [];
					}
	
					// Map O100 field names to what the JS wizard expects
					$campaign->name        = $campaign->title;
					$campaign->action_type = $campaign->type;
					$campaign->active      = $campaign->status === 'active' ? 1 : 0;
	
					// Punch card: extract reward config for UI pre-population
					if ( isset( $campaign->point_rule['reward_config'] ) ) {
						$rc = $campaign->point_rule['reward_config'];
						if ( isset( $rc['required_stamps'] ) ) $campaign->punch_count = $rc['required_stamps'];
						if ( isset( $rc['reward_products'][0] ) ) $campaign->punch_reward_product = $rc['reward_products'][0];
					}
	
					return O100_REST_Controller::success( [ 'campaign' => $campaign ] );
				}
			}
			
			return O100_REST_Controller::error( [ 'message' => 'Campaign not found.' ] );
		}

	public static function handle_toggle_campaign_status() {
			
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return O100_REST_Controller::error( [ 'message' => 'Unauthorized access.' ] );
			}
			
			$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
			$status      = isset( $_POST['status'] ) ? intval( $_POST['status'] ) : 0;
			
			if ( $campaign_id > 0 ) {
				$new_status = $status ? 'active' : 'disabled';
				
				if ( $new_status === 'active' ) {
					$campaign = O100_Loyalty_DB::get_campaign( $campaign_id );
					if ( $campaign && $campaign->type === 'automation' ) {
						$active_automations = O100_Loyalty_DB::get_active_campaigns('automation');
						if ( is_array($active_automations) && count($active_automations) >= 1 ) {
							if ( function_exists('O100_License') && ! O100_License()->is_premium() ) {
								return O100_REST_Controller::error( [ 'message' => 'Free version is limited to 1 Scheduled Automation. Please upgrade to Pro.' ] );
							}
						}
					}
				}

				$result = O100_Loyalty_DB::update_campaign( $campaign_id, [ 'status' => $new_status ] );
				if ( $result !== false ) {
					return O100_REST_Controller::success( [ 'message' => 'Status updated.' ] );
				}
			}
			return O100_REST_Controller::error( [ 'message' => 'Campaign not found.' ] );
		}

}
