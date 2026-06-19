<?php
/**
 * O100 Loyalty Engine
 *
 * Core business logic: earn points, redeem points → Promotions, punch cards, levels.
 *
 * @package Order100
 * @since   4.0.0
 */

defined( 'ABSPATH' ) or die;

class O100_Loyalty_Engine {
	public $debug_info = '';
	public $debug_info_inner = '';


	private static $instance = null;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// ═══════════════════════════════════════════════════════════
	// EARN POINTS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Process points earning for a completed order.
	 *
	 * Evaluates all active "earn" campaigns and awards points accordingly.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function process_order_earn( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		// Prevent double processing
		if ( $order->get_meta( '_o100_loyalty_processed' ) === 'yes' ) return;

		$user_id = $order->get_user_id();
		$email   = $order->get_billing_email();
		if ( ! $email ) return;

		$account = O100_Loyalty_DB::get_or_create_account( $user_id, $email );
		if ( ! $account || $account->status === 'banned' ) return;

		$settings = O100_Loyalty_DB::get_settings();
		$campaigns = O100_Loyalty_DB::get_active_campaigns();
		$total_earned = 0;

		// --- Advanced CRM Rules Engine: Points Multiplier ---
		$points_multiplier = 1;
		if ( class_exists( 'O100_Privilege_Manager' ) ) {
			$loc_id = $order->get_meta( '_o100_branch' );
			$order_type = $order->get_meta( '_o100_order_method' );
			if ( ! $order_type ) $order_type = 'delivery';
			
			$order_time = $order->get_date_created() ? $order->get_date_created()->getOffsetTimestamp() : current_time( 'timestamp' );
			$context = array(
				'branch'     => $loc_id ? intval( $loc_id ) : null,
				'order_type' => $order_type,
				'subtotal'   => $order->get_subtotal(),
				'timestamp'  => $order_time,
			);
			
			// We can use $user_id or $email as identifier
			$identifier = $user_id ? $user_id : $email;
			$multiplier = O100_Privilege_Manager::get_privilege( $identifier, 'loyalty', 'points_multiplier', $context );
			
			if ( is_numeric( $multiplier ) && floatval( $multiplier ) > 0 ) {
				$points_multiplier = floatval( $multiplier );
			}
		}

		foreach ( $campaigns as $campaign ) {
			$points = $this->calculate_campaign_points( $campaign, $order, $settings );
			if ( $points > 0 ) {
				if ( $points_multiplier != 1 ) {
					// Apply multiplier and round up
					$points = (int) ceil( $points * $points_multiplier );
				}

				O100_Loyalty_DB::add_points(
					$account->id,
					$points,
					'order',
					$order_id,
					$campaign->id,
					sprintf( 'Earned %d points from order #%d (%s)', $points, $order_id, $campaign->title )
				);
				$total_earned += $points;

				// Increment campaign usage
				O100_Loyalty_DB::update_campaign( $campaign->id, [
					'usage_count' => $campaign->usage_count + 1,
				] );
			}
		}

		// Process bonus campaigns (pickup, preorder) based on order meta
		$total_earned += $this->process_order_bonuses( $account, $order );

		if ( $total_earned > 0 ) {
			$order->update_meta_data( '_o100_loyalty_processed', 'yes' );
			$order->update_meta_data( '_o100_loyalty_points_earned', $total_earned );
			$order->save();

			// Check level upgrade
			$this->check_level_change( $account->id );

			do_action( 'o100_loyalty_points_earned', $account->id, $total_earned, $order_id );
		}
	}

	/**
	 * Reverse points if order is cancelled/refunded.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function process_order_reverse( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		$earned = (int) $order->get_meta( '_o100_loyalty_points_earned' );
		if ( $earned <= 0 ) return;
		if ( $order->get_meta( '_o100_loyalty_reversed' ) === 'yes' ) return;

		$user_id = $order->get_user_id();
		$email   = $order->get_billing_email();
		$account = $user_id ? O100_Loyalty_DB::get_account_by_user( $user_id ) : O100_Loyalty_DB::get_account_by_email( $email );
		if ( ! $account ) return;

		// Deduct up to what's available
		$to_deduct = min( $earned, $account->points_balance );
		if ( $to_deduct > 0 ) {
			O100_Loyalty_DB::deduct_points(
				$account->id,
				$to_deduct,
				'order_reverse',
				$order_id,
				null,
				sprintf( 'Reversed %d points from cancelled/refunded order #%d', $to_deduct, $order_id )
			);
		}

		$order->update_meta_data( '_o100_loyalty_reversed', 'yes' );
		$order->save();

		$this->check_level_change( $account->id );
	}

	/**
	 * Calculate how many points a campaign awards for this order.
	 */
	public function calculate_campaign_points( $campaign, $order, $settings ) {
		// Handle Native Payload vs Legacy Payload
		$earn_config = isset($campaign->earn_config) ? json_decode( $campaign->earn_config, true ) : [];
		$ui_json = isset($campaign->ui_json) ? json_decode( $campaign->ui_json, true ) : [];

		if ( ! is_array( $earn_config ) ) $earn_config = [];
		if ( ! is_array( $ui_json ) ) $ui_json = [];

		// Check date range
		if ( ! empty($campaign->start_at) && strtotime( $campaign->start_at ) > time() ) return 0;
		if ( ! empty($campaign->end_at) && strtotime( $campaign->end_at ) < time() ) return 0;
		if ( ! empty($campaign->start_date) && strtotime( $campaign->start_date ) > time() ) return 0;
		if ( ! empty($campaign->end_date) && strtotime( $campaign->end_date ) < time() ) return 0;

		// Check conditions
		if ( ! $this->evaluate_conditions( $campaign, $order ) ) return 0;

		$basis = $settings['calculation_basis'] ?? 'subtotal';
		if ( $basis === 'total' ) {
			$subtotal = (float) $order->get_total();
		} else {
			$subtotal = (float) $order->get_subtotal();
			$earn_after = $settings['earn_after_discount'] ?? 'yes';
			if ( $earn_after === 'yes' ) {
				$subtotal -= (float) $order->get_total_discount();
			}
		}

		switch ( $campaign->type ) {
			case 'points':
			case 'points_per_dollar':
			case 'points_for_purchase':
			case 'point_for_purchase':
				$pts = (float) ( $ui_json['earn_point'] ?? ( $earn_config['earn_point'] ?? 1 ) );
				$per = (float) ( $ui_json['point_earn_price'] ?? ( $earn_config['wlr_point_earn_price'] ?? 1 ) );
				if ( $per <= 0 ) $per = 1;
				$raw = ( $subtotal / $per ) * $pts;
				return $this->round_points( $raw, $settings );

			case 'points_per_item':
				$pts = (int) ( $ui_json['earn_point'] ?? ( $earn_config['earn_point'] ?? 1 ) );
				$total_qty = 0;
				foreach ( $order->get_items() as $item ) {
					$total_qty += $item->get_quantity();
				}
				return $total_qty * $pts;

			case 'signup':
			case 'birthday':
			case 'product_review':
			case 'facebook_share':
			case 'twitter_share':
			case 'whatsapp_share':
			case 'email_share':
			case 'referral':
			case 'pickup_bonus':
			case 'preorder_bonus':
			case 'profile_bonus':
			case 'points_conversion':
				// These are handled by separate hooks/methods, not in the order loop
				return 0;

			case 'spend_save':
			case 'subtotal':
				$min_subtotal = (float) ( $earn_config['min_subtotal'] ?? 0 );
				if ( $min_subtotal > 0 && $subtotal < $min_subtotal ) return 0;
				$pts = (int) ( $ui_json['earn_point'] ?? ( $earn_config['earn_point'] ?? 0 ) );
				if ( $pts > 0 ) return $pts;
				// Fallback: legacy reward_type/reward_value pattern
				if ( ($campaign->reward_type ?? '') === 'fixed_points' ) {
					return (int) $campaign->reward_value;
				}
				return 0;

			case 'punch_card':
			case 'o100_punch_card':
				// Punch cards don't give points directly — they track stamps
				$this->process_punch_card_stamps( $campaign, $order );
				return 0;

			default:
				// Ensure unknown or automation campaigns don't accidentally award points per order
				return 0;
		}
	}

	/**
	 * Evaluate campaign conditions against order or cart data.
	 */
	public function evaluate_conditions( $campaign, $order = null, $cart = null ) {
		$conditions = [];
		if ( isset( $campaign->conditions_json ) ) {
			$conditions = json_decode( $campaign->conditions_json, true );
		} elseif ( isset( $campaign->conditions ) ) {
			$conditions = json_decode( $campaign->conditions, true );
		}
		
		if ( empty( $conditions ) || ! is_array( $conditions ) ) return true;

		// Clean up legacy arrays
		$conditions = array_filter( $conditions, function($c) {
			if ( !empty($c['type']) && isset($c['value']) ) return true;
			if ( !empty($c['type']) && isset($c['options']) ) return true;
			return false;
		});

		if ( empty( $conditions ) ) return true;

		$logic = $campaign->condition_relationship ?? 'all';
		if ( empty($logic) ) $logic = 'all';
		if ( $logic === 'and' ) $logic = 'all';
		if ( $logic === 'or' ) $logic = 'any';

		$match_count = 0;

		foreach ( $conditions as $cond ) {
			$matched = $this->evaluate_single_condition( $cond, $order, $cart );
			if ( $matched ) {
				$match_count++;
				if ( $logic === 'any' ) return true;
			} else {
				if ( $logic === 'all' ) return false;
			}
		}

		if ( $logic === 'all' && $match_count === count( $conditions ) ) {
			return true;
		}

		return false;
	}

	private function evaluate_single_condition( $cond, $order, $cart ) {
		$type = $cond['type'];
		$op = $cond['operator'] ?? ( $cond['options']['operator'] ?? '==' );
		$val = $cond['value'] ?? ( $cond['options']['value'][0] ?? '' );

		$is_cart = ( $cart !== null );

		switch ( $type ) {
			// Cart / Order
			case 'cart_subtotal':
				$subtotal = $is_cart ? $cart->get_subtotal() : $order->get_subtotal();
				if ($op === 'greater_than') $op = '>';
				if ($op === 'less_than') $op = '<';
				if ($op === 'equal_to') $op = '==';
				return $this->compare_values( (float)$subtotal, $op, (float)$val );
			case 'cart_items_count':
			case 'line_item_count':
				$count = $is_cart ? count($cart->get_cart()) : count($order->get_items());
				if ($op === 'greater_than') $op = '>';
				if ($op === 'less_than') $op = '<';
				if ($op === 'equal_to') $op = '==';
				return $this->compare_values( $count, $op, (int)$val );
			case 'cart_total_qty':
				$qty = $is_cart ? $cart->get_cart_contents_count() : $order->get_item_count();
				return $this->compare_values( $qty, $op, (int)$val );
			case 'cart_coupon':
				$applied = $is_cart ? array_map('strtolower', $cart->get_applied_coupons()) : array_map('strtolower', $order->get_coupon_codes());
				$target = strtolower(trim($val));
				if ($op === 'is') return in_array($target, $applied);
				if ($op === 'is_not') return !in_array($target, $applied);
				return false;

			// Product
			case 'products':
				$pids = array_map('intval', explode(',', $val));
				$current_pids = [];
				if ($is_cart) {
					foreach ($cart->get_cart() as $item) $current_pids[] = $item['product_id'];
				} else {
					foreach ($order->get_items() as $item) $current_pids[] = $item->get_product_id();
				}
				$intersect = array_intersect($pids, $current_pids);
				if ($op === 'in') return count($intersect) > 0;
				if ($op === 'not_in') return count($intersect) === 0;
				return false;
			case 'product_cat':
				$cids = array_map('intval', explode(',', $val));
				$current_cids = [];
				$items = $is_cart ? $cart->get_cart() : $order->get_items();
				foreach ($items as $item) {
					$pid = $is_cart ? $item['product_id'] : $item->get_product_id();
					$terms = wp_get_post_terms( $pid, 'product_cat', ['fields' => 'ids'] );
					$current_cids = array_merge($current_cids, $terms);
				}
				$intersect = array_intersect($cids, $current_cids);
				if ($op === 'in') return count($intersect) > 0;
				if ($op === 'not_in') return count($intersect) === 0;
				return false;
			case 'product_on_sale':
				$has_sale = false;
				if ($is_cart) {
					foreach ($cart->get_cart() as $item) {
						if ($item['data']->is_on_sale()) { $has_sale = true; break; }
					}
				} else {
					foreach ($order->get_items() as $item) {
						$product = $item->get_product();
						if ($product && $product->is_on_sale()) { $has_sale = true; break; }
					}
				}
				if ($op === 'yes') return $has_sale;
				if ($op === 'no') return !$has_sale;
				return false;

			// Customer
			case 'user_role':
				$user_id = $is_cart ? get_current_user_id() : $order->get_user_id();
				if (!$user_id) return false;
				$user = get_userdata($user_id);
				if (!$user) return false;
				$roles = (array) $user->roles;
				$target_roles = array_map('trim', explode(',', $val));
				
				// Handle legacy options.value logic where operator was in_list
				if ($op === 'in_list') $op = 'in';
				if ($op === 'not_in_list') $op = 'not_in';

				$intersect = array_intersect($target_roles, $roles);
				if ($op === 'in') return count($intersect) > 0;
				if ($op === 'not_in') return count($intersect) === 0;
				return false;
			case 'customer_tag':
				$user_id = $is_cart ? get_current_user_id() : $order->get_user_id();
				if (!$user_id) return false;
				if (!class_exists('O100_Customers_DB')) return false;
				global $wpdb;
				$tbl = O100_Customers_DB::get_table_customers();
				$cid = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl} WHERE wp_user_id = %d", $user_id ) );
				if (!$cid) return false;
				$tags = wp_list_pluck( O100_Customers_DB::get_customer_tags( $cid ), 'id' );
				$targets = array_map('intval', explode(',', $val));
				$intersect = array_intersect($targets, $tags);
				if ($op === 'in') return count($intersect) > 0;
				if ($op === 'not_in') return count($intersect) === 0;
				return false;
			case 'customer_list':
				$user_id = $is_cart ? get_current_user_id() : $order->get_user_id();
				if (!$user_id) return false;
				if (!class_exists('O100_Customers_DB')) return false;
				global $wpdb;
				$tbl = O100_Customers_DB::get_table_customers();
				$cid = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl} WHERE wp_user_id = %d", $user_id ) );
				if (!$cid) return false;
				$lists = wp_list_pluck( O100_Customers_DB::get_customer_lists( $cid ), 'id' );
				$targets = array_map('intval', explode(',', $val));
				$intersect = array_intersect($targets, $lists);
				if ($op === 'in') return count($intersect) > 0;
				if ($op === 'not_in') return count($intersect) === 0;
				return false;
			case 'user_logged_in':
				$logged_in = $is_cart ? is_user_logged_in() : ($order->get_user_id() > 0);
				if ($op === 'yes') return $logged_in;
				if ($op === 'no') return !$logged_in;
				return false;
			case 'first_order':
				$user_id = $is_cart ? get_current_user_id() : $order->get_user_id();
				if (!$user_id) return false;
				$orders = wc_get_orders([ 'customer_id' => $user_id, 'limit' => ($is_cart ? 1 : 2), 'return' => 'ids' ]);
				if ($op === 'yes' || $op === 'equal_to') return $is_cart ? (count($orders) === 0) : (count($orders) <= 1);
				if ($op === 'no') return $is_cart ? (count($orders) > 0) : (count($orders) > 1);
				return false;

			// Purchase History
			case 'purchase_history_order_count':
			case 'prev_orders_count':
				$user_id = $is_cart ? get_current_user_id() : $order->get_user_id();
				if (!$user_id) return false;
				$orders = wc_get_orders([ 'customer_id' => $user_id, 'limit' => -1, 'return' => 'ids' ]);
				$count = count($orders);
				if (!$is_cart && $count > 0) $count--; // Exclude current order
				if ($op === 'greater_than') $op = '>';
				if ($op === 'less_than') $op = '<';
				if ($op === 'equal_to') $op = '==';
				return $this->compare_values( $count, $op, (int)$val );
			case 'total_amount_spent':
			case 'total_spent':
				$user_id = $is_cart ? get_current_user_id() : $order->get_user_id();
				if (!$user_id) return false;
				$spent = wc_get_customer_total_spent( $user_id );
				if (!$is_cart) $spent -= $order->get_total();
				if ($op === 'greater_than') $op = '>';
				if ($op === 'less_than') $op = '<';
				if ($op === 'equal_to') $op = '==';
				return $this->compare_values( $spent, $op, (float)$val );

			// Order
			case 'order_method':
				$method = $is_cart ? WC()->session->get('o100_order_method', 'delivery') : $order->get_meta('_o100_order_method');
				if ($op === 'is') return $method === $val;
				if ($op === 'is_not') return $method !== $val;
				return false;
			case 'payment_method':
				$method = $is_cart ? WC()->session->get('chosen_payment_method') : $order->get_payment_method();
				$targets = array_map('trim', explode(',', $val));
				if ($op === 'in') return in_array($method, $targets);
				if ($op === 'not_in') return !in_array($method, $targets);
				return false;
			case 'location_branch':
				$branch = $is_cart ? WC()->session->get('o100_current_branch', 'all') : $order->get_meta('_o100_branch');
				$targets = array_map('trim', explode(',', $val));
				if ($op === 'in') return in_array($branch, $targets);
				if ($op === 'not_in') return !in_array($branch, $targets);
				return false;

			// Schedule
			case 'time_of_day':
				if ($op === 'between') {
					$parts = explode('-', $val);
					if (count($parts) !== 2) return false;
					$time = $is_cart ? current_time('H:i') : $order->get_date_created()->date_i18n('H:i');
					return ($time >= trim($parts[0]) && $time <= trim($parts[1]));
				}
				return false;
			case 'day_of_week':
				$day = $is_cart ? current_time('w') : $order->get_date_created()->date_i18n('w');
				$targets = array_map('trim', explode(',', $val));
				if ($op === 'in') return in_array($day, $targets);
				if ($op === 'not_in') return !in_array($day, $targets);
				return false;

			// Shipping
			case 'delivery_distance':
				$dist = $is_cart ? WC()->session->get('o100_delivery_distance', 0) : $order->get_meta('_o100_delivery_distance');
				return $this->compare_values( (float)$dist, $op, (float)$val );
			case 'shipping_zip':
				$zip = $is_cart ? (WC()->customer ? WC()->customer->get_shipping_postcode() : '') : $order->get_shipping_postcode();
				$targets = array_map('trim', explode(',', $val));
				if ($op === 'in') return in_array($zip, $targets);
				if ($op === 'not_in') return !in_array($zip, $targets);
				return false;
		}
		return false;
	}

	private function compare_values( $a, $op, $b ) {
		switch ( $op ) {
			case '==': return $a == $b;
			case '>=': return $a >= $b;
			case '<=': return $a <= $b;
			case '>':  return $a > $b;
			case '<':  return $a < $b;
		}
		return false;
	}

	/**
	 * Round points based on settings.
	 */
	private function round_points( $raw, $settings ) {
		$mode = $settings['point_rounding'] ?? 'round';
		switch ( $mode ) {
			case 'floor': return (int) floor( $raw );
			case 'ceil':  return (int) ceil( $raw );
			default:      return (int) round( $raw );
		}
	}

	// ═══════════════════════════════════════════════════════════
	// REDEEM POINTS → PROMOTIONS ENGINE
	// ═══════════════════════════════════════════════════════════

	/**
	 * Redeem points for a discount.
	 * Creates a one-time promo rule in the Promotions engine with source='loyalty'.
	 *
	 * @param int    $account_id  Loyalty account ID.
	 * @param int    $points      Points to spend.
	 * @param string $type        'fixed' or 'percentage'.
	 * @param float  $value       Discount value.
	 * @return int|false  Promotion ID or false on failure.
	 */
	public function redeem_points_for_discount( $account_id, $points, $type = 'fixed', $value = 0 ) {
		$account = O100_Loyalty_DB::get_account( $account_id );
		if ( ! $account || $account->points_balance < $points ) {
			return false;
		}

		if ( ! class_exists( 'O100_Promotions_DB' ) ) {
			return false;
		}

		$settings = O100_Loyalty_DB::get_settings();
		$code = strtoupper( ( $settings['reward_code_prefix'] ?? 'O100-' ) . wp_generate_password( 6, false ) );

		// Create promotion via Promotions engine
		$promo_id = O100_Promotions_DB::insert( [
			'title'         => sprintf( 'Loyalty Redemption (%d pts)', $points ),
			'source'        => 'loyalty',
			'rule_type'     => 'simple',
			'action_config' => wp_json_encode( [
				'discount_type'  => $type,
				'discount_value' => $value,
			] ),
			'promo_code'    => $code,
			'usage_limit'   => 1,
			'status'        => 'active',
			'priority'      => 1,
		] );

		if ( ! $promo_id ) return false;

		// Deduct points
		O100_Loyalty_DB::deduct_points(
			$account_id,
			$points,
			'redeem',
			$promo_id,
			null,
			sprintf( 'Redeemed %d points for %s discount (code: %s)', $points, ( $type === 'percentage' ? $value . '%' : wc_price( $value ) ), $code )
		);

		$this->check_level_change( $account_id );

		do_action( 'o100_loyalty_points_redeemed', $account_id, $points, $promo_id, $code );

		return $promo_id;
	}

	/**
	 * Redeem points for free shipping.
	 */
	public function redeem_points_for_free_shipping( $account_id, $points ) {
		$account = O100_Loyalty_DB::get_account( $account_id );
		if ( ! $account || $account->points_balance < $points ) return false;
		if ( ! class_exists( 'O100_Promotions_DB' ) ) return false;

		$settings = O100_Loyalty_DB::get_settings();
		$code = strtoupper( ( $settings['reward_code_prefix'] ?? 'O100-' ) . wp_generate_password( 6, false ) );

		$promo_id = O100_Promotions_DB::insert( [
			'title'         => sprintf( 'Loyalty Free Shipping (%d pts)', $points ),
			'source'        => 'loyalty',
			'rule_type'     => 'simple',
			'action_config' => wp_json_encode( [ 'discount_type' => 'free_shipping' ] ),
			'promo_code'    => $code,
			'usage_limit'   => 1,
			'status'        => 'active',
			'priority'      => 1,
		] );

		if ( ! $promo_id ) return false;

		O100_Loyalty_DB::deduct_points( $account_id, $points, 'redeem', $promo_id, null,
			sprintf( 'Redeemed %d points for free shipping (code: %s)', $points, $code )
		);

		$this->check_level_change( $account_id );
		return $promo_id;
	}

	// ═══════════════════════════════════════════════════════════
	// PUNCH CARD
	// ═══════════════════════════════════════════════════════════

	/**
	 * Process punch card stamps for an order.
	 */
	public function process_punch_card_stamps( $campaign, $order ) {
		$user_id = $order->get_user_id();
		$email   = $order->get_billing_email();
		$account = O100_Loyalty_DB::get_or_create_account( $user_id, $email );
		if ( ! $account ) return;

		$earn_config = json_decode( $campaign->earn_config, true );
		if ( ! is_array( $earn_config ) ) $earn_config = [];
		$reward_cfg = $earn_config['earn_reward_config'] ?? [];
		$required = (int) ( $reward_cfg['punch_count'] ?? 10 );

		// Count qualifying items — products stored in conditions array
		$qualifying_qty = 0;
		$conditions = json_decode( $campaign->conditions, true );
		$qualifying_products = [];
		if ( is_array( $conditions ) ) {
			foreach ( $conditions as $cond ) {
				if ( isset( $cond['type'] ) && $cond['type'] === 'products' ) {
					$raw = $cond['options']['value'] ?? ( $cond['value'] ?? [] );
					$qualifying_products = array_map( 'intval', (array) $raw );
				}
			}
		}

		foreach ( $order->get_items() as $item ) {
			$pid = $item->get_product_id();
			if ( empty( $qualifying_products ) || in_array( $pid, $qualifying_products ) ) {
				$qualifying_qty += $item->get_quantity();
			}
		}

		if ( $qualifying_qty <= 0 ) return;

		O100_Loyalty_DB::add_stamps( $account->id, $campaign->id, $qualifying_qty );

		// Check if ready for redemption
		$progress = O100_Loyalty_DB::get_punch_progress( $account->id, $campaign->id );
		if ( $progress && $progress->stamps >= $required ) {
			$this->auto_redeem_punch_card( $account->id, $campaign, $progress, $required, $reward_cfg );
		} elseif ( $progress && $account->user_id ) {
			do_action( 'o100_loyalty_punch_card_updated', $account->user_id, $qualifying_qty, $progress->stamps, $required );
		}
	}

	/**
	 * Auto-redeem a full punch card by creating a free item promo.
	 */
	private function auto_redeem_punch_card( $account_id, $campaign, $progress, $required, $reward_config ) {
		if ( ! class_exists( 'O100_Promotions_DB' ) ) return;

		$settings = O100_Loyalty_DB::get_settings();
		$code = strtoupper( 'PUNCH-' . wp_generate_password( 6, false ) );
		$reward_name = $reward_config['reward_name'] ?? $campaign->title;

		$promo_id = O100_Promotions_DB::insert( [
			'title'         => sprintf( 'Punch Card Reward: %s', $reward_name ),
			'source'        => 'loyalty',
			'rule_type'     => 'simple',
			'action_config' => wp_json_encode( [
				'discount_type'    => 'free_item',
				'free_product_ids' => $reward_config['free_products'] ?? [],
				'free_quantity'    => $reward_config['free_quantity'] ?? 1,
			] ),
			'promo_code'    => $code,
			'usage_limit'   => 1,
			'status'        => 'active',
			'priority'      => 1,
		] );

		if ( $promo_id ) {
			O100_Loyalty_DB::reset_punch_progress( $account_id, $campaign->id, $required );

			O100_Loyalty_DB::log_transaction(
				$account_id, 'earn', 0, 0,
				'punch_card', $promo_id, $campaign->id,
				sprintf( 'Punch card completed! Reward: %s (code: %s)', $reward_name, $code )
			);

			do_action( 'o100_loyalty_punch_card_redeemed', $account_id, $campaign->id, $promo_id, $code );

			// Trigger the reward issued email
			$account = O100_Loyalty_DB::get_account( $account_id );
			if ( $account && $account->user_id ) {
				do_action( 'o100_loyalty_auto_reward_issued', $account->user_id, $campaign->id, $code, $promo_id, $campaign );
			}
		}
	}

	// ═══════════════════════════════════════════════════════════
	// SPECIAL EARN EVENTS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Award points for user signup.
	 */
	public function process_signup_earn( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) return;

		$campaigns = O100_Loyalty_DB::get_active_campaigns( 'signup' );
		if ( empty( $campaigns ) ) return;

		$account = O100_Loyalty_DB::get_or_create_account( $user_id, $user->user_email );
		if ( ! $account ) return;

		foreach ( $campaigns as $campaign ) {
			$config = json_decode( $campaign->earn_config, true );
			$points = (int) ( $config['earn_point'] ?? 0 );
			if ( $points > 0 ) {
				O100_Loyalty_DB::add_points( $account->id, $points, 'signup', $user_id, $campaign->id,
					sprintf( 'Welcome bonus: %d points for signing up', $points )
				);
			}
		}

		$this->check_level_change( $account->id );
	}

	/**
	 * Award points for birthday. Called by daily cron.
	 */
	public function process_birthday_earn() {
		$campaigns = O100_Loyalty_DB::get_active_campaigns( 'birthday' );
		if ( empty( $campaigns ) ) return;

		global $wpdb;
		$today = date( 'm-d' );
		$accounts = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE birthday IS NOT NULL AND DATE_FORMAT(birthday, '%%m-%%d') = %s AND status = 'active'",
			O100_Loyalty_DB::table_accounts(), $today
		) );

		foreach ( $accounts as $account ) {
			// Check if already earned today
			$already = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE account_id = %d AND source = 'birthday' AND DATE(created_at) = CURDATE()",
				O100_Loyalty_DB::table_transactions(), $account->id
			) );
			if ( $already > 0 ) continue;

			foreach ( $campaigns as $campaign ) {
				$config = json_decode( $campaign->earn_config, true );
				$points = (int) ( $config['earn_point'] ?? 0 );
				if ( $points > 0 ) {
					O100_Loyalty_DB::add_points( $account->id, $points, 'birthday', 0, $campaign->id,
						sprintf( 'Happy birthday! %d bonus points', $points )
					);
				}
			}
		}
	}

	/**
	 * Save birthday for an account.
	 */
	public function save_birthday( $account_id, $date ) {
		$account = O100_Loyalty_DB::get_account( $account_id );
		if ( ! $account ) return [ 'status' => 'error', 'message' => 'Account not found' ];

		$settings = O100_Loyalty_DB::get_settings();
		$allow_edit = ( $settings['allow_birthday_edit'] ?? 'yes' ) === 'yes';

		if ( $account->birthday ) {
			if ( $account->birthday === $date ) {
				return [ 'status' => 'identical', 'message' => 'You have already set this birthday.' ];
			}
			if ( ! $allow_edit ) {
				return [ 'status' => 'not_allowed', 'message' => 'Sorry, you cannot modify your birthday once set.' ];
			}
		}

		O100_Loyalty_DB::update_account( $account_id, [ 'birthday' => $date ] );

		// Award birthday point for "update" type campaigns
		$campaigns = O100_Loyalty_DB::get_active_campaigns( 'birthday' );
		foreach ( $campaigns as $campaign ) {
			$config = json_decode( $campaign->earn_config, true );
			if ( ( $config['birthday_earn_type'] ?? '' ) === 'update_birth_date' ) {
				$points = (int) ( $config['earn_point'] ?? 0 );
				if ( $points > 0 ) {
					O100_Loyalty_DB::add_points( $account_id, $points, 'birthday', 0, $campaign->id,
						'Birthday set — bonus points awarded'
					);
				}
			}
		}

		return [ 'status' => 'success', 'message' => 'Birthday saved!' ];
	}

	// ═══════════════════════════════════════════════════════════
	// REFERRAL
	// ═══════════════════════════════════════════════════════════

	/**
	 * Process referral when a referred user places their first order.
	 *
	 * @param int    $order_id   The new order.
	 * @param string $refer_code The referral code used.
	 */
	public function process_referral( $order_id, $refer_code ) {
		if ( ! $refer_code ) return;

		global $wpdb;
		// Find advocate
		$advocate = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE refer_code = %s AND status = 'active'",
			O100_Loyalty_DB::table_accounts(), $refer_code
		) );
		if ( ! $advocate ) return;

		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		if ( $order->get_meta( '_o100_referral_processed' ) === 'yes' ) return;

		// Get referral campaigns
		$campaigns = O100_Loyalty_DB::get_active_campaigns( 'referral' );
		if ( empty( $campaigns ) ) return;

		$friend_email = $order->get_billing_email();
		$friend_account = O100_Loyalty_DB::get_or_create_account( $order->get_user_id(), $friend_email );

		foreach ( $campaigns as $campaign ) {
			$config = json_decode( $campaign->earn_config, true );

			// Advocate reward
			$adv_points = (int) ( $config['advocate']['earn_point'] ?? 0 );
			if ( $adv_points > 0 ) {
				O100_Loyalty_DB::add_points( $advocate->id, $adv_points, 'referral', $order_id, $campaign->id,
					sprintf( 'Referral reward: %s placed order #%d', $friend_email, $order_id )
				);
			}

			// Friend reward
			$friend_points = (int) ( $config['friend']['earn_point'] ?? 0 );
			if ( $friend_points > 0 && $friend_account ) {
				O100_Loyalty_DB::add_points( $friend_account->id, $friend_points, 'referral', $order_id, $campaign->id,
					sprintf( 'Welcome bonus from referral by %s', $advocate->email )
				);
			}
		}

		$order->update_meta_data( '_o100_referral_processed', 'yes' );
		$order->update_meta_data( '_o100_referred_by', $advocate->id );
		$order->save();

		$this->check_level_change( $advocate->id );
		if ( $friend_account ) $this->check_level_change( $friend_account->id );
	}

	// ═══════════════════════════════════════════════════════════
	// LEVELS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Check and update level for an account after points change.
	 */
	public function check_level_change( $account_id ) {
		$account = O100_Loyalty_DB::get_account( $account_id );
		if ( ! $account ) return;

		$new_level = O100_Loyalty_DB::determine_level( $account->points_earned );
		$new_level_id = $new_level ? $new_level->id : 0;

		if ( (int) $account->level_id !== (int) $new_level_id ) {
			$old_level_id = $account->level_id;
			O100_Loyalty_DB::update_account( $account_id, [ 'level_id' => $new_level_id ] );

			do_action( 'o100_loyalty_level_changed', $account_id, $old_level_id, $new_level_id );
		}
	}

	// ═══════════════════════════════════════════════════════════
	// FRONTEND HELPERS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Get the current user's loyalty data for frontend display.
	 */
	public function get_current_user_data() {
		if ( ! is_user_logged_in() ) {
			return [ 'is_member' => false ];
		}

		$user_id = get_current_user_id();
		$account = O100_Loyalty_DB::get_account_by_user( $user_id );
		if ( ! $account ) {
			return [ 'is_member' => false ];
		}

		$level = $account->level_id ? O100_Loyalty_DB::get_level( $account->level_id ) : null;

		return [
			'is_member'      => true,
			'account_id'     => $account->id,
			'email'          => $account->email,
			'points_balance' => $account->points_balance,
			'points_earned'  => $account->points_earned,
			'points_spent'   => $account->points_spent,
			'level_name'     => $level ? $level->name : '',
			'level_icon'     => $level ? $level->icon : '',
			'refer_code'     => $account->refer_code,
			'birthday'       => $account->birthday,
		];
	}

	/**
	 * Calculate total prospective points for an order.
	 */
	public function calculate_order_points( $order ) {
		if ( ! $order ) return 0;
		$settings = O100_Loyalty_DB::get_settings();
		$campaigns = O100_Loyalty_DB::get_active_campaigns();
		$total_earned = 0;
		
		$points_multiplier = 1;
		if ( class_exists( 'O100_Privilege_Manager' ) ) {
			$loc_id = $order->get_meta( '_o100_branch' );
			$order_type = $order->get_meta( '_o100_order_method' ) ?: 'delivery';
			$order_time = $order->get_date_created() ? $order->get_date_created()->getOffsetTimestamp() : current_time( 'timestamp' );
			$context = array(
				'branch'     => $loc_id ? intval( $loc_id ) : null,
				'order_type' => $order_type,
				'subtotal'   => $order->get_subtotal(),
				'timestamp'  => $order_time,
			);
			$user_id = $order->get_user_id();
			$email = $order->get_billing_email();
			$identifier = $user_id ? $user_id : $email;
			$multiplier = O100_Privilege_Manager::get_privilege( $identifier, 'loyalty', 'points_multiplier', $context );
			if ( is_numeric( $multiplier ) && floatval( $multiplier ) > 0 ) {
				$points_multiplier = floatval( $multiplier );
			}
		}

		foreach ( $campaigns as $campaign ) {
			$points = $this->calculate_campaign_points( $campaign, $order, $settings );
			if ( $points > 0 ) {
				if ( $points_multiplier != 1 ) {
					$points = (int) ceil( $points * $points_multiplier );
				}
				$total_earned += $points;
			}
		}

		// Calculate order bonuses as well if possible, or skip for simplicity (cart and bonuses are complex)
		// For the thank you page, this gives an accurate projection of the main earn campaigns.
		return $total_earned;
	}

	/**
	 * Calculate points for the current cart.
	 */
	public function calculate_cart_points() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) return 0;

		$campaigns = O100_Loyalty_DB::get_active_campaigns();
		if ( empty( $campaigns ) ) return 0;

		$settings = O100_Loyalty_DB::get_settings();
		$basis = $settings['calculation_basis'] ?? 'subtotal';
		if ( $basis === 'total' ) {
			$subtotal = (float) WC()->cart->get_total('');
		} else {
			$subtotal = (float) WC()->cart->get_subtotal();
			$earn_after = $settings['earn_after_discount'] ?? 'yes';
			if ( $earn_after === 'yes' ) {
				$subtotal -= (float) WC()->cart->get_discount_total();
			}
		}

		$total_points = 0;
		$this->debug_info = 'Subtotal evaluated: ' . $subtotal . ' | Campaigns found: ' . count($campaigns) . '. ';
		foreach ( $campaigns as $campaign ) {
			// Check date range
			if ( ! empty($campaign->start_at) && strtotime( $campaign->start_at ) > time() ) continue;
			if ( ! empty($campaign->end_at) && strtotime( $campaign->end_at ) < time() ) continue;
			if ( ! empty($campaign->start_date) && strtotime( $campaign->start_date ) > time() ) continue;
			if ( ! empty($campaign->end_date) && strtotime( $campaign->end_date ) < time() ) continue;

			// Check conditions
			if ( ! $this->evaluate_conditions( $campaign, null, WC()->cart ) ) {
				$this->debug_info .= '[Camp ' . $campaign->id . ' failed conditions. debug: ' . $this->debug_info_inner . '] ';
				continue;
			}

			// Handle Native Payload vs Legacy Payload
			$earn_config = isset($campaign->earn_config) ? json_decode( $campaign->earn_config, true ) : [];
			$ui_json = isset($campaign->ui_json) ? json_decode( $campaign->ui_json, true ) : [];

			if ( $campaign->type === 'points' || $campaign->type === 'points_per_dollar' || $campaign->type === 'points_for_purchase' || $campaign->type === 'point_for_purchase' ) {
				$pts = (float) ( $ui_json['earn_point'] ?? ( $earn_config['earn_point'] ?? 1 ) );
				$per = (float) ( $ui_json['point_earn_price'] ?? ( $earn_config['wlr_point_earn_price'] ?? 1 ) );
				if ( $per <= 0 ) $per = 1;
				$total_points += ( $subtotal / $per ) * $pts;
				$this->debug_info .= '[Camp ' . $campaign->id . ' awarded ' . (($subtotal / $per) * $pts) . ' pts. per=' . $per . ' pts=' . $pts . '] ';
			} elseif ( $campaign->type === 'points_per_item' ) {
				$pts = (int) ( $ui_json['earn_point'] ?? ( $earn_config['earn_point'] ?? 1 ) );
				$total_points += WC()->cart->get_cart_contents_count() * $pts;
			} elseif ( $campaign->type === 'spend_save' ) {
				// spend_save is fixed reward or points for order
				if ( ($campaign->reward_type ?? '') === 'fixed_points' ) {
					$total_points += (int) $campaign->reward_value;
				}
			}
		}

		// --- Advanced CRM Rules Engine: Points Multiplier ---
		$points_multiplier = 1;
		if ( class_exists( 'O100_Privilege_Manager' ) && function_exists( 'WC' ) && WC()->session ) {
			$loc_id = WC()->session->get( '_o100_branch_id' );
			$order_type = WC()->session->get( '_o100_order_method' );
			if ( ! $order_type ) $order_type = 'delivery';
			
			$context = array(
				'branch'     => $loc_id ? intval( $loc_id ) : null,
				'order_type' => $order_type,
				'subtotal'   => $subtotal,
				'timestamp'  => current_time( 'timestamp' ),
			);
			
			$identifier = get_current_user_id();
			if ( ! $identifier && function_exists('WC') && WC()->checkout() ) {
				$email = WC()->checkout()->get_value('billing_email');
				if ( ! $email && isset($_POST['post_data']) ) {
					parse_str($_POST['post_data'], $post_data);
					$email = $post_data['billing_email'] ?? '';
				}
				if ( $email ) {
					$identifier = $email;
				}
			}

			if ( $identifier ) {
				$multiplier = O100_Privilege_Manager::get_privilege( $identifier, 'loyalty', 'points_multiplier', $context );
				if ( is_numeric( $multiplier ) && floatval( $multiplier ) > 0 ) {
					$points_multiplier = floatval( $multiplier );
				}
			}
		}

		if ( $points_multiplier != 1 && $total_points > 0 ) {
			$total_points = (float) ( $total_points * $points_multiplier );
		}

		return $this->round_points( $total_points, $settings );
	}

	/**
	 * Evaluate if a single product meets a campaign's conditions.
	 */
	public function evaluate_product_conditions( $campaign, $product ) {
		$this->debug_info = '';
		$conditions = [];
		if ( isset( $campaign->conditions_json ) ) {
			$conditions = json_decode( $campaign->conditions_json, true );
		} elseif ( isset( $campaign->conditions ) ) {
			$conditions = json_decode( $campaign->conditions, true );
		}
		
		if ( empty( $conditions ) || ! is_array( $conditions ) ) return true;

		$logic = $campaign->condition_relationship ?? 'all';
		if ( empty($logic) ) $logic = 'all';
		if ( $logic === 'and' ) $logic = 'all';
		if ( $logic === 'or' ) $logic = 'any';

		$product_id = $product->get_id();
		$parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product_id;
		$term_ids = wp_get_post_terms( $parent_id, 'product_cat', ['fields' => 'ids'] );
		if ( is_wp_error($term_ids) ) $term_ids = [];

		$product_cond_count = 0;
		$match_count = 0;

		foreach ( $conditions as $cond ) {
			$type = $cond['type'] ?? '';
			if ( ! in_array( $type, ['products', 'product_cat', 'product_on_sale'] ) ) {
				continue;
			}

			$product_cond_count++;
			
			$op = $cond['operator'] ?? ( $cond['options']['operator'] ?? '==' );
			$val = $cond['value'] ?? ( $cond['options']['value'] ?? '' );
			
			// Normalize array values
			if ( is_array($val) ) {
				// Sometimes legacy WPLoyalty saves options.value as an array
				if ( isset($val[0]) && is_string($val[0]) && strpos($val[0], ',') !== false ) {
					$val = $val[0];
				} else {
					$val = implode(',', $val);
				}
			}

			$matched = false;
			switch ( $type ) {
				case 'products':
					$pids = array_map('intval', explode(',', $val));
					if ($op === 'in' || $op === 'in_list') $matched = in_array($product_id, $pids) || in_array($parent_id, $pids);
					if ($op === 'not_in' || $op === 'not_in_list') $matched = !in_array($product_id, $pids) && !in_array($parent_id, $pids);
					break;
				case 'product_cat':
					$cids = array_map('intval', explode(',', $val));
					$intersect = array_intersect($cids, $term_ids);
					if ($op === 'in' || $op === 'in_list') $matched = count($intersect) > 0;
					if ($op === 'not_in' || $op === 'not_in_list') $matched = count($intersect) === 0;
					break;
				case 'product_on_sale':
					$has_sale = $product->is_on_sale();
					if ($op === 'yes') $matched = $has_sale;
					if ($op === 'no') $matched = !$has_sale;
					break;
			}

			if ( $matched ) {
				$match_count++;
				if ( $logic === 'any' ) return true;
			} else {
				if ( $logic === 'all' ) {
					$this->debug_info = 'FAIL on logic=all. type=' . $type . ' op=' . $op . ' val=' . json_encode($val) . ' cids=' . json_encode($cids ?? []) . ' term_ids=' . json_encode($term_ids) . ' intersect=' . json_encode($intersect ?? []);
					return false;
				}
			}
		}

		if ( $logic === 'all' ) {
			return true;
		} else {
			// For ANY logic: if we had product conditions and NONE matched, it doesn't satisfy the product criteria.
			// However, if there were no product conditions at all, it's globally applicable.
			if ($product_cond_count > 0) {
				$this->debug_info = 'FAIL on logic="' . $logic . '" at end. product_cond_count=' . $product_cond_count;
				return false;
			}
			return true;
		}
	}

	/**
	 * Calculate points to display on the product page.
	 * Returns an array with min and max points for the product, based on active earning campaigns.
	 *
	 * @param WC_Product $product
	 * @return array|false  [ 'min' => int, 'max' => int ] or false if 0 points
	 */
	public function calculate_product_points( $product ) {
		$campaigns = O100_Loyalty_DB::get_active_campaigns();
		if ( empty( $campaigns ) ) return false;

		$settings = O100_Loyalty_DB::get_settings();

		// Calculate min and max price
		$min_price = (float) $product->get_price();
		$max_price = $min_price;

		if ( $product->is_type('variable') ) {
			$min_price = (float) $product->get_variation_price('min');
			$max_price = (float) $product->get_variation_price('max');
		}

		$total_min = 0;
		$total_max = 0;

		foreach ( $campaigns as $campaign ) {
			// Check date range
			if ( ! empty($campaign->start_at) && strtotime( $campaign->start_at ) > time() ) continue;
			if ( ! empty($campaign->end_at) && strtotime( $campaign->end_at ) < time() ) continue;
			if ( ! empty($campaign->start_date) && strtotime( $campaign->start_date ) > time() ) continue;
			if ( ! empty($campaign->end_date) && strtotime( $campaign->end_date ) < time() ) continue;

			// Check product eligibility
			if ( ! $this->evaluate_product_conditions( $campaign, $product ) ) continue;

			// Handle Native Payload vs Legacy Payload
			$earn_config = isset($campaign->earn_config) ? json_decode( $campaign->earn_config, true ) : [];
			$ui_json = isset($campaign->ui_json) ? json_decode( $campaign->ui_json, true ) : [];

			if ( $campaign->type === 'points' || $campaign->type === 'points_per_dollar' || $campaign->type === 'points_for_purchase' || $campaign->type === 'point_for_purchase' ) {
				$pts = (float) ( $ui_json['earn_point'] ?? ( $earn_config['earn_point'] ?? 1 ) );
				$per = (float) ( $ui_json['point_earn_price'] ?? ( $earn_config['wlr_point_earn_price'] ?? 1 ) );
				if ( $per <= 0 ) $per = 1;
				
				$total_min += ( $min_price / $per ) * $pts;
				$total_max += ( $max_price / $per ) * $pts;
			} elseif ( $campaign->type === 'points_per_item' ) {
				$pts = (int) ( $ui_json['earn_point'] ?? ( $earn_config['earn_point'] ?? 1 ) );
				$total_min += $pts;
				$total_max += $pts;
			}
		}

		// Apply VIP multiplier for product display
		$multiplier = 1;
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$tags = get_user_meta( $user_id, 'o100_customer_tags', true );
			$context = [ 'customer_tags' => is_array($tags) ? $tags : [] ];
			$mult = O100_Privilege_Manager::get_privilege( $user_id, 'loyalty', 'points_multiplier', $context );
			if ( is_numeric($mult) && floatval($mult) > 0 ) {
				$multiplier = floatval($mult);
			}
		}

		if ( $multiplier != 1 ) {
			$total_min = $total_min * $multiplier;
			$total_max = $total_max * $multiplier;
		}

		if ( $total_max <= 0 ) return false;

		return [
			'min' => $this->round_points( $total_min, $settings ),
			'max' => $this->round_points( $total_max, $settings ),
		];
	}

	/**
	 * Replace message placeholders.
	 */
	public function replace_placeholders( $message, $extra = [] ) {
		$settings = O100_Loyalty_DB::get_settings();
		$user_data = $this->get_current_user_data();

		$replacements = [
			'{o100_points_label}'    => $settings['point_label_plural'] ?? 'Points',
			'{o100_point_label}'     => $settings['point_label_singular'] ?? 'Point',
			'{o100_cart_points}'     => $this->calculate_cart_points(),
			'{o100_total_points}'    => $user_data['points_balance'] ?? 0,
			'{o100_user_name}'       => is_user_logged_in() ? wp_get_current_user()->display_name : '',
			'{o100_redeem_cart_points}' => $user_data['points_balance'] ?? 0,
		];

		$replacements = array_merge( $replacements, $extra );

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $message );
	}

	// ═══════════════════════════════════════════════════════════
	// PRODUCT REVIEW
	// ═══════════════════════════════════════════════════════════

	/**
	 * Award points when a user submits an approved product review.
	 *
	 * @param int   $comment_id       WP comment ID.
	 * @param int   $comment_approved 1 if approved.
	 * @param array $commentdata      Comment data array.
	 */
	public function process_review_earn( $comment_id, $comment_approved, $commentdata ) {
		if ( $comment_approved !== 1 ) return;
		$comment_type = $commentdata['comment_type'] ?? '';
		if ( $comment_type !== 'review' ) return;

		$post = get_post( $commentdata['comment_post_ID'] ?? 0 );
		if ( ! $post || $post->post_type !== 'product' ) return;

		$user_id = (int) ( $commentdata['user_id'] ?? 0 );
		if ( ! $user_id ) return; // Guest reviews don't earn points

		$campaigns = O100_Loyalty_DB::get_active_campaigns( 'product_review' );
		if ( empty( $campaigns ) ) return;

		$account = O100_Loyalty_DB::get_or_create_account( $user_id );
		if ( ! $account || $account->status === 'banned' ) return;

		// Prevent duplicate: check if this user already earned for this product
		global $wpdb;
		$already = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE account_id = %d AND source = 'product_review' AND source_id = %d",
			O100_Loyalty_DB::table_transactions(), $account->id, $post->ID
		) );
		if ( $already > 0 ) return;

		foreach ( $campaigns as $campaign ) {
			$config = json_decode( $campaign->earn_config, true );
			if ( ! is_array( $config ) ) $config = [];
			$points = (int) ( $config['earn_point'] ?? 0 );
			if ( $points > 0 ) {
				O100_Loyalty_DB::add_points(
					$account->id, $points, 'product_review', $post->ID, $campaign->id,
					sprintf( 'Earned %d points for reviewing product #%d', $points, $post->ID )
				);
				$this->check_level_change( $account->id );
			}
		}
	}

	/**
	 * Award points when a previously pending review is approved.
	 *
	 * @param WP_Comment $comment The comment object.
	 */
	public function process_review_approved( $comment ) {
		if ( $comment->comment_type !== 'review' ) return;
		$this->process_review_earn(
			$comment->comment_ID,
			1,
			[
				'comment_type'    => $comment->comment_type,
				'comment_post_ID' => $comment->comment_post_ID,
				'user_id'         => $comment->user_id,
			]
		);
	}

	// ═══════════════════════════════════════════════════════════
	// SOCIAL SHARE
	// ═══════════════════════════════════════════════════════════

	/**
	 * Award points when a user shares via a social channel.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $channel    Channel key: facebook, twitter, whatsapp, email.
	 * @param string $shared_url The URL that was shared.
	 * @return array [ 'status' => 'success'|'duplicate'|'no_campaign', 'points' => int ]
	 */
	public function process_social_share_earn( $user_id, $channel, $shared_url = '' ) {
		if ( ! $user_id ) return [ 'status' => 'error', 'message' => 'Not logged in' ];

		$type = $channel . '_share'; // e.g. facebook_share
		$campaigns = O100_Loyalty_DB::get_active_campaigns( $type );
		if ( empty( $campaigns ) ) {
			return [ 'status' => 'no_campaign', 'points' => 0 ];
		}

		$account = O100_Loyalty_DB::get_or_create_account( $user_id );
		if ( ! $account || $account->status === 'banned' ) {
			return [ 'status' => 'error', 'message' => 'Account banned' ];
		}

		// Prevent duplicate: same user + same channel + same URL
		$share_key = $type . ':' . md5( $shared_url );
		global $wpdb;
		$already = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE account_id = %d AND source = 'social_share' AND note LIKE %s",
			O100_Loyalty_DB::table_transactions(), $account->id, '%' . $wpdb->esc_like( $share_key ) . '%'
		) );
		if ( $already > 0 ) {
			return [ 'status' => 'duplicate', 'points' => 0 ];
		}

		$total = 0;
		foreach ( $campaigns as $campaign ) {
			$config = json_decode( $campaign->earn_config, true );
			if ( ! is_array( $config ) ) $config = [];
			$points = (int) ( $config['earn_point'] ?? 0 );
			if ( $points > 0 ) {
				O100_Loyalty_DB::add_points(
					$account->id, $points, 'social_share', 0, $campaign->id,
					sprintf( 'Earned %d points for %s share [%s]', $points, $channel, $share_key )
				);
				$total += $points;
			}
		}

		if ( $total > 0 ) {
			$this->check_level_change( $account->id );
		}

		return [ 'status' => 'success', 'points' => $total ];
	}

	// ═══════════════════════════════════════════════════════════
	// ORDER-BASED BONUSES (Pickup, Preorder)
	// ═══════════════════════════════════════════════════════════

	/**
	 * Process bonus campaigns that depend on order meta (pickup, preorder).
	 * Called from process_order_earn() after standard campaigns.
	 *
	 * @param object   $account Loyalty account.
	 * @param WC_Order $order   WooCommerce order.
	 * @return int Total bonus points earned.
	 */
	private function process_order_bonuses( $account, $order ) {
		$bonus_total = 0;

		// --- Pickup Bonus ---
		$order_method = $order->get_meta( '_o100_order_method' );
		if ( $order_method === 'pickup' ) {
			$pickup_campaigns = O100_Loyalty_DB::get_active_campaigns( 'pickup_bonus' );
			foreach ( $pickup_campaigns as $campaign ) {
				$config = json_decode( $campaign->earn_config, true ) ?: [];
				$points = (int) ( $config['earn_point'] ?? 0 );
				if ( $points > 0 ) {
					O100_Loyalty_DB::add_points(
						$account->id, $points, 'pickup_bonus', $order->get_id(), $campaign->id,
						sprintf( 'Pickup bonus: %d points for order #%d', $points, $order->get_id() )
					);
					$bonus_total += $points;
				}
			}
		}

		// --- Preorder Bonus ---
		// Compare order creation time vs scheduled delivery/pickup time.
		// If the gap >= configured threshold (earn_config.preorder_min_minutes), it qualifies.
		$scheduled_time_str = $order->get_meta( '_o100_time_deli' );
		if ( ! $scheduled_time_str ) {
			$scheduled_time_str = $order->get_meta( '_o100_time_pickup' );
		}
		if ( $scheduled_time_str ) {
			$order_created = $order->get_date_created();
			if ( $order_created ) {
				$order_time     = $order_created->getTimestamp();
				$scheduled_time = strtotime( $scheduled_time_str );

				if ( $scheduled_time && $scheduled_time > $order_time ) {
					$gap_minutes = ( $scheduled_time - $order_time ) / 60;

					$preorder_campaigns = O100_Loyalty_DB::get_active_campaigns( 'preorder_bonus' );
					foreach ( $preorder_campaigns as $campaign ) {
						$config = json_decode( $campaign->earn_config, true ) ?: [];
						$threshold_minutes = (int) ( $config['preorder_min_minutes'] ?? 60 );
						$points = (int) ( $config['earn_point'] ?? 0 );

						if ( $points > 0 && $gap_minutes >= $threshold_minutes ) {
							O100_Loyalty_DB::add_points(
								$account->id, $points, 'preorder_bonus', $order->get_id(), $campaign->id,
								sprintf( 'Preorder bonus: %d points for order #%d (%.0f min ahead)', $points, $order->get_id(), $gap_minutes )
							);
							$bonus_total += $points;
						}
					}
				}
			}
		}

		return $bonus_total;
	}
}

