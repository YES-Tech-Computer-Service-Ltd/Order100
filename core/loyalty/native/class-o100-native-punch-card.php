<?php
/**
 * O100 Native Punch Card Engine
 * 
 * Handles Punch Card calculation, stamp awarding, and redemption entirely independently of WPLoyalty's global ledger.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Native_Punch_Card {

	public static function init() {
		// Hook into order completion to calculate and award stamps
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'process_order_stamps' ], 10, 1 );
		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'process_order_stamps' ], 10, 1 );
		
		// Handle AJAX redemption
		add_action( 'wp_ajax_o100_redeem_punch_card', [ __CLASS__, 'handle_redemption_ajax' ] );
		add_action( 'wp_ajax_nopriv_o100_redeem_punch_card', [ __CLASS__, 'handle_redemption_ajax' ] );

		add_action( 'wp_ajax_o100_apply_reward_to_cart', [ __CLASS__, 'handle_apply_reward_to_cart' ] );
		add_action( 'wp_ajax_nopriv_o100_apply_reward_to_cart', [ __CLASS__, 'handle_apply_reward_to_cart' ] );
		
		// Thank you page notice
		add_action( 'woocommerce_thankyou', [ __CLASS__, 'thank_you_page_notice' ], 10, 1 );
	}

	/**
	 * Process completed/processing orders and award stamps natively.
	 */
	public static function process_order_stamps( $order_id ) {
		// Prevent double processing
		$processed = get_post_meta( $order_id, '_o100_punch_card_processed', true );
		if ( $processed === 'yes' ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		
		$user_id = $order->get_customer_id();
		if ( ! $user_id ) return; // Guest orders don't get stamps unless tracked by email (to be implemented if needed)

		// Get all active native punch card campaigns
		$campaigns = self::get_active_punch_cards();
		if ( empty( $campaigns ) ) {
			return;
		}

		$stamps_awarded = false;

		foreach ( $campaigns as $camp ) {
			$conditions = json_decode($camp->conditions_json, true) ?: [];
			
			// Extract participating product IDs
			$eligible_product_ids = [];
			foreach ( $conditions as $cond ) {
				if ( isset($cond['type']) && $cond['type'] === 'products' ) {
					if ( isset($cond['options']['value']) && is_array($cond['options']['value']) ) {
						$eligible_product_ids = array_merge($eligible_product_ids, $cond['options']['value']);
					}
				}
			}

			if ( empty( $eligible_product_ids ) ) continue;

			// Calculate eligible quantity
			$earned_qty = 0;
			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				$variation_id = $item->get_variation_id();
				
				if ( in_array( $product_id, $eligible_product_ids ) || in_array( $variation_id, $eligible_product_ids ) ) {
					// Check if this item is a free reward item itself (we don't award stamps on free items)
					$is_free = $item->get_meta('loyalty_free_product') || $item->get_total() == 0;
					if ( ! $is_free ) {
						$earned_qty += $item->get_quantity();
					}
				}
			}

			if ( $earned_qty > 0 ) {
				self::add_stamps( $user_id, $camp->id, $earned_qty );
				// Log to order
				$order->add_order_note( sprintf( 'Order100 Native Loyalty: Awarded %d stamps for Punch Card "%s".', $earned_qty, $camp->name ) );
				$stamps_awarded = true;
			}
		}

		if ( $stamps_awarded ) {
			update_post_meta( $order_id, '_o100_punch_card_processed', 'yes' );
		}
	}

	/**
	 * Display Thank You Page Notice
	 */
	public static function thank_you_page_notice( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		$campaigns = self::get_active_punch_cards();
		if ( empty( $campaigns ) ) return;

		$total_earned_now = 0;
		$campaign_name = 'Reward';

		foreach ( $campaigns as $camp ) {
			$conditions = json_decode($camp->conditions_json, true) ?: [];
			
			$eligible_product_ids = [];
			foreach ( $conditions as $cond ) {
				if ( isset($cond['type']) && $cond['type'] === 'products' ) {
					if ( isset($cond['options']['value']) && is_array($cond['options']['value']) ) {
						$eligible_product_ids = array_merge($eligible_product_ids, $cond['options']['value']);
					}
				}
			}

			if ( empty( $eligible_product_ids ) ) continue;

			$earned_qty = 0;
			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				$variation_id = $item->get_variation_id();
				
				if ( in_array( $product_id, $eligible_product_ids ) || in_array( $variation_id, $eligible_product_ids ) ) {
					$is_free = $item->get_meta('loyalty_free_product') || $item->get_total() == 0;
					if ( ! $is_free ) {
						$earned_qty += $item->get_quantity();
					}
				}
			}

			if ( $earned_qty > 0 ) {
				$total_earned_now += $earned_qty;
				$campaign_name = $camp->name;
			}
		}

		if ( $total_earned_now > 0 ) {
			if ( is_user_logged_in() || $order->get_customer_id() ) {
				$user_id = $order->get_customer_id() ? $order->get_customer_id() : get_current_user_id();
				
				$total = 0;
				foreach ($campaigns as $c) {
					$total += self::get_stamp_balance( $user_id, $c->id );
				}
				
				$msg = sprintf(
					'You have earned %d stamp(s) for this order! You now have a total of %d stamps. <a href="%s" class="button" style="margin-left: 10px;">View your rewards in My Account</a>',
					$total_earned_now,
					$total,
					esc_url( wc_get_page_permalink( 'myaccount' ) )
				);
				wc_print_notice( wp_kses_post( $msg ), 'success' );
			} else {
				$msg = sprintf(
					'You\'ve earned %d stamp(s) on this order! <a href="%s" style="font-weight:bold;">Sign up</a> or <a href="%s" style="font-weight:bold;">Log in</a> now to save your stamps and get closer to a free %s.',
					$total_earned_now,
					esc_url( wc_get_page_permalink( 'myaccount' ) ),
					esc_url( wc_get_page_permalink( 'myaccount' ) ),
					esc_html( $campaign_name )
				);
				wc_print_notice( wp_kses_post( $msg ), 'success' );
			}
		}
	}

	/**
	 * Retrieve active Punch Card campaigns
	 */
	public static function get_active_punch_cards() {
		global $wpdb;
		$table = $wpdb->prefix . 'o100_loyalty_campaigns';
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE type = 'punch_card' AND status = 'active'" );
	}

	/**
	 * Get Stamp Balance for a specific user and campaign
	 */
	public static function get_stamp_balance( $user_id, $campaign_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'o100_loyalty_punch_cards';
		$balance = $wpdb->get_var( $wpdb->prepare( "SELECT current_stamps FROM {$table} WHERE user_id = %d AND campaign_id = %d", $user_id, $campaign_id ) );
		return (int) $balance;
	}

	/**
	 * Add Stamps
	 */
	public static function add_stamps( $user_id, $campaign_id, $qty ) {
		global $wpdb;
		$table = $wpdb->prefix . 'o100_loyalty_punch_cards';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND campaign_id = %d", $user_id, $campaign_id ) );

		if ( $row ) {
			$history = $row->history_json ? json_decode($row->history_json, true) : [];
			$history[] = [ 'date' => current_time('mysql'), 'qty' => $qty, 'action' => 'earned' ];

			$wpdb->update(
				$table,
				[
					'current_stamps' => $row->current_stamps + $qty,
					'history_json'   => wp_json_encode( $history )
				],
				[ 'id' => $row->id ],
				[ '%d', '%s' ],
				[ '%d' ]
			);
		} else {
			$history = [ [ 'date' => current_time('mysql'), 'qty' => $qty, 'action' => 'earned' ] ];
			$wpdb->insert(
				$table,
				[
					'user_id'        => $user_id,
					'campaign_id'    => $campaign_id,
					'current_stamps' => $qty,
					'total_redeemed' => 0,
					'history_json'   => wp_json_encode( $history )
				],
				[ '%d', '%d', '%d', '%d', '%s' ]
			);
		}
	}

	/**
	 * Handle AJAX request to redeem stamps for a free item
	 */
	public static function handle_redemption_ajax() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( ['message' => 'Please login to redeem rewards.'] );
		}

		$user_id = get_current_user_id();
		$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
		
		if ( $campaign_id <= 0 ) {
			wp_send_json_error( ['message' => 'Invalid campaign.'] );
		}

		global $wpdb;
		$camp_table = $wpdb->prefix . 'o100_loyalty_campaigns';
		$camp = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$camp_table} WHERE id = %d AND type = 'punch_card' AND status = 'active'", $campaign_id ) );
		
		if ( ! $camp ) {
			wp_send_json_error( ['message' => 'Campaign not found or inactive.'] );
		}

		$ui_json = json_decode( $camp->ui_json, true );
		$required_stamps = isset($ui_json['punch_count']) ? intval($ui_json['punch_count']) : 5;
		
		if ( empty($camp->reward_value) ) {
			wp_send_json_error( ['message' => 'Reward product missing.'] );
		}
		
		$product_ids_to_discount = array_map('intval', explode(',', $camp->reward_value));

		// Check Balance
		$current_balance = self::get_stamp_balance( $user_id, $campaign_id );
		if ( $current_balance < $required_stamps ) {
			wp_send_json_error( ['message' => 'Not enough stamps.'] );
		}

		// Create the Promo Coupon dynamically via Native Promotions module
		if ( ! class_exists('O100_Promotions_DB') ) {
			wp_send_json_error( ['message' => 'Promotions Engine not loaded.'] );
		}

		$coupon_code = 'PUNCH-FREE-' . strtoupper( wp_generate_password( 6, false ) );
		$action_config = [
			'discount_type'  => 'percentage',
			'discount_value' => 100
		];
		
		$user_info = get_userdata($user_id);
		$user_email = $user_info ? $user_info->user_email : '';
		
		$conditions = [];
		if ( !empty($user_email) ) {
			$conditions[] = [
				'type' => 'customer_email',
				'options' => [ 'value' => $user_email ]
			];
		}

		$promo_data = [
			'source'         => 'loyalty_punch',
			'title'          => 'Redeemed Punch Card: ' . $camp->name,
			'rule_type'      => 'cart_discount',
			'action_config'  => wp_json_encode( $action_config ),
			'apply_to'       => 'specific_products',
			'apply_to_items' => wp_json_encode( $product_ids_to_discount ),
			'promo_code'     => $coupon_code,
			'conditions'     => wp_json_encode( $conditions ),
			'usage_limit'    => 1,
			'status'         => 'active'
		];
		
		O100_Promotions_DB::insert( $promo_data );

		// Deduct Stamps
		$new_balance = $current_balance - $required_stamps;
		$pc_table = $wpdb->prefix . 'o100_loyalty_punch_cards';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pc_table} WHERE user_id = %d AND campaign_id = %d", $user_id, $campaign_id ) );
		
		if ( $row ) {
			$history = $row->history_json ? json_decode($row->history_json, true) : [];
			$history[] = [ 'date' => current_time('mysql'), 'qty' => -$required_stamps, 'action' => 'redeemed' ];
			
			$wpdb->update(
				$pc_table,
				[ 
					'current_stamps' => $new_balance,
					'total_redeemed' => $row->total_redeemed + 1,
					'history_json'   => wp_json_encode( $history )
				],
				[ 'id' => $row->id ],
				[ '%d', '%d', '%s' ],
				[ '%d' ]
			);
		}

		wp_send_json_success([
			'message' => '成功获得 Coupon！请在 My Coupons 页面查看并使用。',
			'coupon' => $coupon_code,
			'new_balance' => $new_balance
		]);
	}
	public static function handle_apply_reward_to_cart() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( ['message' => 'Please login to use coupons.'] );
		}

		$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( $_POST['coupon_code'] ) : '';
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

		if ( empty( $coupon_code ) || empty( $product_id ) ) {
			wp_send_json_error( ['message' => 'Invalid request.'] );
		}

		if ( ! function_exists('WC') || ! WC()->cart ) {
			wp_send_json_error( ['message' => 'Cart not found.'] );
		}

		// Ensure product is in cart specifically as a reward item
		$found = false;
		foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
			if ( isset( $values['o100_reward_item'] ) && $values['o100_reward_item'] === $coupon_code ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			WC()->cart->add_to_cart( $product_id, 1, 0, array(), [ 'o100_reward_item' => $coupon_code ] );
		}

		// Apply Coupon
		WC()->cart->apply_coupon( $coupon_code );

		wp_send_json_success( ['message' => 'Applied successfully'] );
	}
}



// TS: 20260126145855

// TS: 20260417174438
