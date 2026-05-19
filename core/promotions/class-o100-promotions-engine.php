<?php
/**
 * Promotions Core Engine
 *
 * Evaluates active promotions and calculates discounts during checkout.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Promotions_Engine {

	private static $applied_coupons = [];

	public static function init() {
		add_action( 'woocommerce_cart_calculate_fees', [ __CLASS__, 'calculate_fees' ], 20, 1 );
		add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'set_free_reward_prices' ], 10, 1 );
		add_filter( 'woocommerce_get_shop_coupon_data', [ __CLASS__, 'virtual_coupon_data' ], 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid', [ __CLASS__, 'validate_virtual_coupon' ], 10, 3 );
		add_action( 'template_redirect', [ __CLASS__, 'process_pending_promo_cookie' ] );
		add_action( 'woocommerce_increase_coupon_usage_count', [ __CLASS__, 'increase_virtual_coupon_usage' ], 10, 3 );
		add_action( 'woocommerce_decrease_coupon_usage_count', [ __CLASS__, 'decrease_virtual_coupon_usage' ], 10, 3 );

	}

	public static function increase_virtual_coupon_usage( $coupon, $new_count, $used_by = '' ) {
		$code = $coupon->get_code();
		require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
		global $wpdb;
		$table = O100_Promotions_DB::table_name();
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET usage_count = usage_count + 1 WHERE promo_code = %s", $code ) );
	}

	public static function decrease_virtual_coupon_usage( $coupon, $new_count, $used_by = '' ) {
		$code = $coupon->get_code();
		require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
		global $wpdb;
		$table = O100_Promotions_DB::table_name();
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET usage_count = GREATEST(0, usage_count - 1) WHERE promo_code = %s", $code ) );
	}

	public static function set_free_reward_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
		
		// Find applied loyalty punch coupons
		$applied_coupons = $cart->get_applied_coupons();
		if ( empty( $applied_coupons ) ) return;
		
		require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
		$promos = O100_Promotions_DB::query([ 'status' => 'active' ]);
		$reward_codes = [];
		foreach ( $promos as $promo ) {
			if ( $promo['source'] === 'loyalty_punch' && in_array( strtolower($promo['promo_code']), array_map('strtolower', $applied_coupons) ) ) {
				$reward_codes[] = strtolower($promo['promo_code']);
			}
		}
		
		if ( empty( $reward_codes ) ) return;
		
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['o100_reward_item'] ) && in_array( strtolower($cart_item['o100_reward_item']), $reward_codes ) ) {
				$cart_item['data']->set_price( 0 );
			}
		}
	}


	public static function process_pending_promo_cookie() {
		if ( ! is_user_logged_in() || empty( $_COOKIE['o100_pending_promo'] ) ) {
			return;
		}

		$promo_id = absint( $_COOKIE['o100_pending_promo'] );
		
		// Clear cookie immediately
		setcookie( 'o100_pending_promo', '', time() - 3600, '/' );

		if ( $promo_id ) {
			try {
				self::apply_promotion_by_id( $promo_id );
				wc_add_notice( __( 'Promotion applied successfully.', 'order100' ), 'success' );
			} catch ( Exception $e ) {
				wc_add_notice( $e->getMessage(), 'error' );
			}
		}
	}

	public static function virtual_coupon_data( $data, $code ) {
		if ( $data !== false ) {
			return $data; // Already resolved
		}

		require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
		$promos = O100_Promotions_DB::query([ 'status' => 'active' ]);
		
		foreach ( $promos as $promo ) {
			if ( ! empty( $promo['promo_code'] ) && strcasecmp( $promo['promo_code'], $code ) === 0 ) {
				// Found a matching active promotion code
				return array(
					'id'                         => $promo['id'], // Use promotion ID as dummy coupon ID
					'code'                       => $code,
					'amount'                     => '0',
					'discount_type'              => 'fixed_cart',
					'description'                => $promo['title'],
					'date_expires'               => ! empty( $promo['end_date'] ) && $promo['end_date'] !== '0000-00-00 00:00:00' ? strtotime( $promo['end_date'] ) : null,
					'usage_limit'                => $promo['usage_limit'] > 0 ? $promo['usage_limit'] : null,
					'usage_count'                => $promo['usage_count'],
					'individual_use'             => intval( $promo['is_exclusive'] ) === 1 ? 'yes' : 'no',
					'free_shipping'              => 'no', // Handle internally
				);
			}
		}

		return false;
	}

	public static function validate_virtual_coupon( $valid, $coupon, $discount ) {
		// Only intercept if it's our virtual coupon (amount is '0' and we find it in our DB)
		if ( $coupon->get_amount() !== '0' ) {
			return $valid;
		}

		$code = $coupon->get_code();
		require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
		$promos = O100_Promotions_DB::query([ 'status' => 'active' ]);
		
		foreach ( $promos as $promo ) {
			if ( ! empty( $promo['promo_code'] ) && strcasecmp( $promo['promo_code'], $code ) === 0 ) {
				
				// Validate common rules (dates, limits, min order, method, branch)
				$error_msg = self::get_common_rules_error( $promo, WC()->cart );
				if ( $error_msg ) {
					throw new Exception( $error_msg );
				}

				// Validate advanced conditions (first order, time, etc.)
				$error_msg = self::get_advanced_conditions_error( $promo, WC()->cart );
				if ( $error_msg ) {
					throw new Exception( $error_msg );
				}

				return true;
			}
		}

		return $valid;
	}

	public static function calculate_fees( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Reset injected metadata for this calculation pass to prevent accumulation
		foreach ( $cart->cart_contents as $key => $item ) {
			$cart->cart_contents[$key]['o100_promo_discount'] = 0;
			$cart->cart_contents[$key]['o100_promo_badge'] = '';
		}

		// Prevent infinite loops if multiple rules trigger recalculation
		if ( did_action( 'woocommerce_cart_calculate_fees' ) >= 2 ) {
			// In some setups, woo calculates fees multiple times. We usually let it run.
		}

		require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
		
		// Fetch all active promotions, ordered by priority ASC
		$active_promos = O100_Promotions_DB::query([
			'status' => 'active',
			'orderby' => 'priority',
			'order' => 'ASC',
			'limit' => 200
		]);

		if ( empty( $active_promos ) ) {
			return;
		}

		$exclusive_applied = false;
		self::$applied_coupons = $cart->get_applied_coupons();

		foreach ( $active_promos as $promo ) {
			if ( $exclusive_applied ) {
				break; // Skip remaining if an exclusive rule already applied
			}

			// 1. Validate promo code requirement
			if ( ! empty( $promo['promo_code'] ) ) {
				if ( ! in_array( strtolower( $promo['promo_code'] ), array_map( 'strtolower', self::$applied_coupons ) ) ) {
					if ( isset($_GET['debug_bogo']) ) echo "Skipping {$promo['title']}: missing code\n";
					continue; // Requires a code that is not applied
				}
			}

			// 2. Validate common rules (dates, limits, min order)
			if ( ! self::validate_common_rules( $promo, $cart ) ) {
				if ( isset($_GET['debug_bogo']) ) echo "Skipping {$promo['title']}: common rules failed\n";
				continue;
			}

			// 3. Validate advanced conditions
			if ( ! self::validate_advanced_conditions( $promo, $cart ) ) {
				if ( isset($_GET['debug_bogo']) ) echo "Skipping {$promo['title']}: advanced conditions failed\n";
				continue;
			}

			// 4. Get eligible items (returns array of cart item keys or 'all' for cart-level)
			$eligible_items = self::get_eligible_cart_items( $promo, $cart );
			if ( empty( $eligible_items ) ) {
				if ( isset($_GET['debug_bogo']) ) echo "Skipping {$promo['title']}: no eligible items\n";
				continue;
			}

			// 5. Calculate discount
			$calc_result = self::calculate_discount( $promo, $eligible_items, $cart );
			$discount = is_array( $calc_result ) ? $calc_result['total'] : $calc_result;
			$item_discounts = is_array( $calc_result ) && isset( $calc_result['items'] ) ? $calc_result['items'] : [];

			if ( isset($_GET['debug_bogo']) ) echo "Discount for {$promo['title']}: $discount\n";

			if ( $discount > 0 ) {
				if ( isset($_GET['debug_bogo']) ) echo "Adding fee: -$discount\n";
				$cart->add_fee( $promo['title'], -$discount, true );
				
				foreach ( $item_discounts as $key => $amt ) {
					if ( $amt > 0 ) {
						// Set item metadata for the side cart rendering
						$cart->cart_contents[$key]['o100_promo_discount'] = ( $cart->cart_contents[$key]['o100_promo_discount'] ?? 0 ) + $amt;
						
						// Generate badge text
						$config = json_decode( $promo['action_config'], true );
						$badge = '';
						if ( $promo['rule_type'] === 'bogo' ) {
							$badge = 'Buy ' . $config['buy_qty'] . ' Get ' . $config['get_qty'];
							if ( $config['discount_type'] === 'free' ) $badge .= ' Free';
						} elseif ( $promo['rule_type'] === 'simple' && $config['discount_type'] === 'percentage' ) {
							$badge = $config['discount_value'] . '% OFF';
						} else {
							$badge = $promo['title'];
						}
						
						// Append badge if multiple
						$existing_badge_str = isset($cart->cart_contents[$key]['o100_promo_badge']) ? $cart->cart_contents[$key]['o100_promo_badge'] : '';
						$existing_badges = !empty($existing_badge_str) ? explode(', ', $existing_badge_str) : [];
						if ( ! in_array( $badge, $existing_badges ) ) {
							$existing_badges[] = $badge;
						}
						$cart->cart_contents[$key]['o100_promo_badge'] = implode(', ', $existing_badges);
					}
				}
				
				if ( intval( $promo['is_exclusive'] ) === 1 ) {
					$exclusive_applied = true;
				}
			}
		}

		// Process free shipping
		if ( isset( $cart->o100_free_shipping_promo ) && $cart->o100_free_shipping_promo ) {
			foreach ( $cart->get_fees() as $fee_key => $fee ) {
				// Only target delivery or shipping related fees
				if ( stripos( $fee->name, 'Delivery' ) !== false || stripos( $fee->name, 'Shipping' ) !== false ) {
					$shipping_cost = $fee->amount;
					if ( $shipping_cost > 0 ) {
						// Add a negative fee to zero it out
						$cart->add_fee( __( 'Free Shipping Promo', 'order100' ), -$shipping_cost, false );
					}
				}
			}
		}
	}

	/**
	 * Validate Common Rules (Date, Limits, Method, Branch, Min Order)
	 */
	public static function validate_common_rules( $promo, $cart ) {
		return self::get_common_rules_error( $promo, $cart ) === false;
	}

	/**
	 * Returns an error string if common rules fail, otherwise false.
	 */
	public static function get_common_rules_error( $promo, $cart ) {
		$now = current_time( 'timestamp' );

		$has_start = ! empty( $promo['start_date'] ) && $promo['start_date'] !== '0000-00-00 00:00:00';
		$has_end = ! empty( $promo['end_date'] ) && $promo['end_date'] !== '0000-00-00 00:00:00';

		if ( $has_start && strtotime( $promo['start_date'] ) > $now ) {
			return __( 'This promotion has not started yet.', 'order100' );
		}
		if ( $has_end && ( strtotime( $promo['end_date'] ) + 86399 ) < $now ) {
			return __( 'This promotion has expired.', 'order100' );
		}

		if ( $promo['usage_limit'] > 0 && $promo['usage_count'] >= $promo['usage_limit'] ) {
			return __( 'This promotion has reached its usage limit.', 'order100' );
		}

		$config = json_decode( $promo['action_config'], true );
		if ( ! $config ) return __( 'Invalid promotion configuration.', 'order100' );

		// Min Order
		$min_order = isset( $config['min_order'] ) ? floatval( $config['min_order'] ) : 0;
		if ( $min_order > 0 && $cart->get_subtotal() < $min_order ) {
			return sprintf( __( 'This promotion requires a minimum order of %s.', 'order100' ), wc_price( $min_order ) );
		}

		// Order Method
		$method = isset( $config['order_method'] ) ? $config['order_method'] : 'both';
		if ( $method !== 'both' ) {
			$current_method = WC()->session->get( '_o100_order_method', 'delivery' ); 
			if ( $method !== $current_method ) {
				return sprintf( __( 'This promotion is only valid for %s orders.', 'order100' ), $method );
			}
		}

		// Branch
		$branch = isset( $config['branch'] ) ? $config['branch'] : 'all';
		if ( $branch !== 'all' ) {
			$current_branch = WC()->session->get( 'o100_current_branch', 'all' );
			if ( $current_branch !== 'all' && $current_branch != $branch ) {
				return __( 'This promotion is not valid for the selected location.', 'order100' );
			}
		}

		return false;
	}

	/**
	 * Validate Advanced Conditions (Logic Tree)
	 */
	private static function validate_advanced_conditions( $promo, $cart ) {
		return self::get_advanced_conditions_error( $promo, $cart ) === false;
	}

	/**
	 * Returns an error string if advanced conditions fail, otherwise false.
	 */
	private static function get_advanced_conditions_error( $promo, $cart ) {
		$conditions = json_decode( $promo['conditions'], true );
		
		if ( is_array( $conditions ) ) {
			$conditions = array_filter( $conditions, function($c) {
				return !empty($c['type']) && isset($c['value']) && $c['value'] !== '';
			});
		}

		if ( empty( $conditions ) ) {
			return false;
		}

		$logic = $promo['conditions_logic']; // 'all' or 'any'
		if ( empty($logic) ) $logic = 'all';

		$match_count = 0;
		$last_error = '';

		foreach ( $conditions as $cond ) {
			$matched = self::evaluate_single_condition( $cond, $cart );
			if ( $matched ) {
				$match_count++;
				if ( $logic === 'any' ) return false;
			} else {
				$last_error = self::get_condition_error_message( $cond, isset( $promo['id'] ) ? $promo['id'] : 0 );
				if ( $logic === 'all' ) return $last_error;
			}
		}

		if ( $logic === 'all' && $match_count === count( $conditions ) ) {
			return false;
		}

		return $last_error ?: __( 'Promotion conditions are not met.', 'order100' );
	}

	private static function get_condition_error_message( $cond, $promo_id = 0 ) {
		$type = $cond['type'];
		$login_btn = ' <a href="javascript:void(0);" class="o100-login-trigger" data-pending-promo="' . esc_attr( $promo_id ) . '" style="padding:6px 12px; margin-left:10px; font-size:13px; border-radius:6px; display:inline-block; vertical-align:middle; text-decoration:none; background:#e11d48; color:#fff;">' . esc_html__( 'Login / Register', 'order100' ) . '</a>';

		switch ( $type ) {
			case 'first_order':
				if (!is_user_logged_in()) return __( 'You must be logged in to use this promotion.', 'order100' ) . $login_btn;
				return __( 'This promotion is only available for first-time orders.', 'order100' );
			case 'user_logged_in':
				return __( 'You must be logged in to use this promotion.', 'order100' ) . $login_btn;
			case 'user_role':
				return __( 'Your user role is not eligible for this promotion.', 'order100' );
			case 'cart_subtotal':
				return __( 'Cart subtotal does not meet the promotion requirements.', 'order100' );
			case 'time_of_day':
				return __( 'This promotion is not available at this time.', 'order100' );
			case 'day_of_week':
				return __( 'This promotion is not available today.', 'order100' );
			default:
				return __( 'Your order does not meet the requirements for this promotion.', 'order100' );
		}
	}

	private static function evaluate_single_condition( $cond, $cart ) {
		$type = $cond['type'];
		$op = $cond['operator'];
		$val = $cond['value'];

		switch ( $type ) {
			// Cart
			case 'cart_subtotal':
				return self::compare_values( $cart->get_subtotal(), $op, floatval( $val ) );
			case 'cart_items_count': // unique items
				return self::compare_values( count($cart->get_cart()), $op, intval( $val ) );
			case 'cart_total_qty': // total quantity
				return self::compare_values( $cart->get_cart_contents_count(), $op, intval( $val ) );
			case 'cart_coupon':
				$applied = array_map('strtolower', $cart->get_applied_coupons());
				$target = strtolower(trim($val));
				if ($op === 'is') return in_array($target, $applied);
				if ($op === 'is_not') return !in_array($target, $applied);
				return false;

			// Product
			case 'products':
				$pids = array_map('intval', explode(',', $val));
				$cart_pids = [];
				foreach ($cart->get_cart() as $item) $cart_pids[] = $item['product_id'];
				$intersect = array_intersect($pids, $cart_pids);
				if ($op === 'in') return count($intersect) > 0;
				if ($op === 'not_in') return count($intersect) === 0;
				return false;
			case 'product_cat':
				$cids = array_map('intval', explode(',', $val));
				$cart_cids = [];
				foreach ($cart->get_cart() as $item) {
					$terms = wp_get_post_terms( $item['product_id'], 'product_cat', ['fields' => 'ids'] );
					$cart_cids = array_merge($cart_cids, $terms);
				}
				$intersect = array_intersect($cids, $cart_cids);
				if ($op === 'in') return count($intersect) > 0;
				if ($op === 'not_in') return count($intersect) === 0;
				return false;
			case 'product_on_sale':
				$has_sale = false;
				foreach ($cart->get_cart() as $item) {
					if ($item['data']->is_on_sale()) { $has_sale = true; break; }
				}
				if ($op === 'yes') return $has_sale;
				if ($op === 'no') return !$has_sale;
				return false;

			// Customer
			case 'user_role':
				if (!is_user_logged_in()) return false;
				$user = wp_get_current_user();
				$roles = (array) $user->roles;
				$target_roles = array_map('trim', explode(',', $val));
				$intersect = array_intersect($target_roles, $roles);
				if ($op === 'in') return count($intersect) > 0;
				if ($op === 'not_in') return count($intersect) === 0;
				return false;
			case 'user_logged_in':
				if ($op === 'yes') return is_user_logged_in();
				if ($op === 'no') return !is_user_logged_in();
				return false;
			case 'first_order':
				if (!is_user_logged_in()) return false;
				$orders = wc_get_orders([ 'customer_id' => get_current_user_id(), 'limit' => 1, 'return' => 'ids' ]);
				if ($op === 'yes') return count($orders) === 0;
				if ($op === 'no') return count($orders) > 0;
				return false;

			// Purchase History
			case 'prev_orders_count':
				if (!is_user_logged_in()) return false;
				$orders = wc_get_orders([ 'customer_id' => get_current_user_id(), 'limit' => -1, 'return' => 'ids' ]);
				return self::compare_values( count( $orders ), $op, intval( $val ) );
			case 'total_spent':
				if (!is_user_logged_in()) return false;
				$spent = wc_get_customer_total_spent( get_current_user_id() );
				return self::compare_values( $spent, $op, floatval( $val ) );

			// Order
			case 'order_method':
				$current_method = WC()->session->get( 'o100_order_method', 'delivery' );
				if ($op === 'is') return $current_method === $val;
				if ($op === 'is_not') return $current_method !== $val;
				return false;
			case 'payment_method':
				$chosen = WC()->session->get( 'chosen_payment_method' );
				$targets = array_map('trim', explode(',', $val));
				if ($op === 'in') return in_array($chosen, $targets);
				if ($op === 'not_in') return !in_array($chosen, $targets);
				return false;
			case 'location_branch':
				$current_branch = WC()->session->get( 'o100_current_branch', 'all' );
				$targets = array_map('trim', explode(',', $val));
				if ($op === 'in') return in_array($current_branch, $targets);
				if ($op === 'not_in') return !in_array($current_branch, $targets);
				return false;

			// Schedule
			case 'time_of_day':
				if ($op === 'between') {
					$parts = explode('-', $val);
					if (count($parts) !== 2) return false;
					$current_time = current_time( 'H:i' );
					return ($current_time >= trim($parts[0]) && $current_time <= trim($parts[1]));
				}
				return false;
			case 'day_of_week':
				$current_day = current_time( 'w' ); // 0-6
				$targets = array_map('trim', explode(',', $val));
				if ($op === 'in') return in_array($current_day, $targets);
				if ($op === 'not_in') return !in_array($current_day, $targets);
				return false;

			// Shipping / Delivery
			case 'delivery_distance':
				$dist = WC()->session->get( 'o100_delivery_distance', 0 );
				return self::compare_values( floatval($dist), $op, floatval( $val ) );
			case 'shipping_zip':
				$zip = WC()->customer ? WC()->customer->get_shipping_postcode() : '';
				$targets = array_map('trim', explode(',', $val));
				if ($op === 'in') return in_array($zip, $targets);
				if ($op === 'not_in') return !in_array($zip, $targets);
				return false;
		}
		return false;
	}

	private static function compare_values( $a, $op, $b ) {
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
	 * Get cart items eligible for this promotion
	 * Returns array of cart_item_keys, or ['all'] if cart-level.
	 */
	public static function get_eligible_cart_items( $promo, $cart ) {
		$config = json_decode( $promo['action_config'], true );
		if ( isset( $config['level'] ) && $config['level'] === 'cart' ) {
			return ['all']; // Applies to entire cart total
		}

		$apply_to = $promo['apply_to'];
		if ( $apply_to === 'all_products' ) {
			return array_keys( $cart->get_cart() );
		}

		$items_raw = json_decode( $promo['apply_to_items'], true );
		if ( ! is_array( $items_raw ) || empty( $items_raw ) ) {
			return [];
		}

		$eligible_keys = [];
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['product_id'];
			
			if ( $apply_to === 'specific_products' ) {
				if ( in_array( $product_id, $items_raw ) ) {
					$eligible_keys[] = $cart_item_key;
				}
			} elseif ( $apply_to === 'specific_categories' ) {
				$terms = wp_get_post_terms( $product_id, 'product_cat', ['fields' => 'ids'] );
				if ( count( array_intersect( $terms, $items_raw ) ) > 0 ) {
					$eligible_keys[] = $cart_item_key;
				}
			}
		}

		return $eligible_keys;
	}

	/**
	 * Calculate total discount value
	 */
	public static function calculate_discount( $promo, $eligible_items, $cart ) {
		$type = $promo['rule_type'];
		$config = json_decode( $promo['action_config'], true );
		
		$result = 0;
		switch ( $type ) {
			case 'simple':
				$result = self::calc_simple( $config, $eligible_items, $cart );
				break;
			case 'bogo':
				$result = self::calc_bogo( $config, $eligible_items, $cart );
				break;
			case 'buy_x_get_y':
				$result = self::calc_buy_x_get_y( $config, $eligible_items, $cart );
				break;
			case 'bulk_tiered':
				$result = self::calc_bulk_tiered( $config, $eligible_items, $cart );
				break;
			case 'bundle':
				$result = self::calc_bundle( $config, $eligible_items, $cart );
				break;
		}
		
		if ( ! is_array( $result ) ) {
			return [ 'total' => $result, 'items' => [] ];
		}
		return $result;
	}

	/**
	 * Handlers for each promotion type
	 */
	private static function calc_simple( $config, $eligible_items, $cart ) {
		$type = $config['discount_type'];
		$val = floatval( $config['discount_value'] );
		$discount = 0;

		$base_total = 0;
		if ( $eligible_items === ['all'] ) {
			$base_total = $cart->get_subtotal();
		} else {
			foreach ( $eligible_items as $key ) {
				$item = $cart->get_cart()[ $key ];
				$base_total += $item['line_total'];
			}
		}

		if ( $type === 'free_item' ) {
			// Find cheapest 1 unit among eligible items to make free
			$min_price = PHP_INT_MAX;
			$target_key = null;
			if ( $eligible_items !== ['all'] ) {
				foreach ( $eligible_items as $key ) {
					$item = $cart->get_cart()[ $key ];
					$price = $item['data']->get_price();
					if ($price < $min_price) {
						$min_price = $price;
						$target_key = $key;
					}
				}
			}
			if ( $target_key !== null ) {
				$discount = min( $min_price, $base_total );
				$item_discounts[$target_key] = $discount;
			}
			return [ 'total' => $discount, 'items' => $item_discounts ];
		}

		if ( $type === 'percentage' ) {
			$discount = $base_total * ( $val / 100 );
		} elseif ( $type === 'fixed' ) {
			$discount = min( $val, $base_total );
		} elseif ( $type === 'free_shipping' ) {
			$cart->o100_free_shipping_promo = true;
		}

		$item_discounts = [];
		if ( $discount > 0 && $base_total > 0 ) {
			if ( $eligible_items === ['all'] ) {
				// If cart-wide, distribute across all items
				foreach ( $cart->get_cart() as $key => $item ) {
					$ratio = $item['line_total'] / $base_total;
					$item_discounts[$key] = $discount * $ratio;
				}
			} else {
				foreach ( $eligible_items as $key ) {
					$item = $cart->get_cart()[ $key ];
					$ratio = $item['line_total'] / $base_total;
					$item_discounts[$key] = $discount * $ratio;
				}
			}
		}

		return [ 'total' => $discount, 'items' => $item_discounts ];
	}

	private static function calc_bogo( $config, $eligible_items, $cart ) {
		// BOGO logic requires flattening cart items into individual units to sort by price
		if ( $eligible_items === ['all'] ) return 0; // BOGO doesn't apply cart-wide usually

		$buy_qty = intval( $config['buy_qty'] );
		$get_qty = intval( $config['get_qty'] );
		$disc_type = $config['discount_type']; // free, percentage, fixed
		$disc_val = floatval( $config['discount_value'] );

		if ( $buy_qty <= 0 || $get_qty <= 0 ) return [ 'total' => 0, 'items' => [] ];

		$flattened_items = [];
		foreach ( $eligible_items as $key ) {
			$item = $cart->get_cart()[ $key ];
			$qty = $item['quantity'];
			$price = $item['data']->get_price();
			for ( $i = 0; $i < $qty; $i++ ) {
				$flattened_items[] = [ 'price' => $price, 'key' => $key ];
			}
		}

		// Sort items by price ascending (cheapest are discounted first)
		usort( $flattened_items, function($a, $b) {
			return $a['price'] <=> $b['price'];
		});
		
		$total_items = count( $flattened_items );
		$group_size = $buy_qty + $get_qty;

		$discount = 0;
		$item_discounts = [];
		$num_groups = floor( $total_items / $group_size );

		for ( $g = 0; $g < $num_groups; $g++ ) {
			// For each group, discount the first `get_qty` items (which are the cheapest due to sorting)
			for ( $i = 0; $i < $get_qty; $i++ ) {
				$idx = ( $g * $group_size ) + $i;
				$price = $flattened_items[ $idx ]['price'];
				$key   = $flattened_items[ $idx ]['key'];
				
				$item_discount = 0;
				if ( $disc_type === 'free' ) {
					$item_discount = $price;
				} elseif ( $disc_type === 'percentage' ) {
					$item_discount = $price * ( $disc_val / 100 );
				} elseif ( $disc_type === 'fixed' ) {
					$item_discount = min( $price, $disc_val );
				}
				
				$discount += $item_discount;
				if ( ! isset( $item_discounts[$key] ) ) $item_discounts[$key] = 0;
				$item_discounts[$key] += $item_discount;
			}
		}

		return [ 'total' => $discount, 'items' => $item_discounts ];
	}

	private static function calc_buy_x_get_y( $config, $eligible_items, $cart ) {
		// Buy X of eligible_items, Get Y of product_y
		if ( $eligible_items === ['all'] ) return 0;

		$buy_qty = intval( $config['buy_qty'] );
		$get_qty = intval( $config['get_qty'] );
		$disc_type = $config['discount_type'];
		$disc_val = floatval( $config['discount_value'] );
		$prod_y_id = $config['product_y'];

		if ( $buy_qty <= 0 || $get_qty <= 0 || empty( $prod_y_id ) ) return 0;

		$total_x_qty = 0;
		foreach ( $eligible_items as $key ) {
			$item = $cart->get_cart()[ $key ];
			$total_x_qty += $item['quantity'];
		}

		// Find Y items in cart
		$y_items = [];
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( $item['product_id'] == $prod_y_id ) {
				for ( $i = 0; $i < $item['quantity']; $i++ ) {
					$y_items[] = [ 'price' => $item['data']->get_price(), 'key' => $key ];
				}
			}
		}

		if ( empty( $y_items ) ) return [ 'total' => 0, 'items' => [] ]; // Customer hasn't added Y to cart

		// Sort by price ascending
		usort( $y_items, function($a, $b) {
			return $a['price'] <=> $b['price'];
		});
		$eligible_y_qty_for_discount = floor( $total_x_qty / $buy_qty ) * $get_qty;
		
		$discount = 0;
		$item_discounts = [];
		$items_to_discount = min( $eligible_y_qty_for_discount, count( $y_items ) );

		for ( $i = 0; $i < $items_to_discount; $i++ ) {
			$price = $y_items[$i]['price'];
			$key = $y_items[$i]['key'];
			
			$item_discount = 0;
			if ( $disc_type === 'free' ) {
				$item_discount = $price;
			} elseif ( $disc_type === 'percentage' ) {
				$item_discount = $price * ( $disc_val / 100 );
			} elseif ( $disc_type === 'fixed' ) {
				$item_discount = min( $price, $disc_val );
			}
			
			$discount += $item_discount;
			if ( ! isset( $item_discounts[$key] ) ) $item_discounts[$key] = 0;
			$item_discounts[$key] += $item_discount;
		}

		return [ 'total' => $discount, 'items' => $item_discounts ];
	}

	private static function calc_bulk_tiered( $config, $eligible_items, $cart ) {
		if ( empty( $config['tiers'] ) || $eligible_items === ['all'] ) return 0;

		$total_qty = 0;
		$base_total = 0;
		foreach ( $eligible_items as $key ) {
			$item = $cart->get_cart()[ $key ];
			$total_qty += $item['quantity'];
			$base_total += $item['line_total'];
		}

		$discount = 0;
		$item_discounts = [];
		// Find matching tier
		foreach ( $config['tiers'] as $tier ) {
			$min = intval( $tier['min'] );
			$max = empty( $tier['max'] ) ? 999999 : intval( $tier['max'] );

			if ( $total_qty >= $min && $total_qty <= $max ) {
				$type = $tier['discount_type'];
				$val = floatval( $tier['discount_value'] );

				if ( $type === 'percentage' ) {
					$discount = $base_total * ( $val / 100 );
				} elseif ( $type === 'fixed' ) {
					$discount = min( $val, $base_total );
				}
				
				// Distribute discount proportionally across eligible items
				if ( $discount > 0 && $base_total > 0 ) {
					foreach ( $eligible_items as $key ) {
						$item = $cart->get_cart()[ $key ];
						$ratio = $item['line_total'] / $base_total;
						$item_discounts[$key] = $discount * $ratio;
					}
				}
				break; // Stop at first matching tier (assume sorted or exclusive tiers)
			}
		}

		return [ 'total' => $discount, 'items' => $item_discounts ];
	}

	private static function calc_bundle( $config, $eligible_items, $cart ) {
		if ( $eligible_items === ['all'] ) return 0;

		$set_qty = intval( $config['set_qty'] );
		if ( $set_qty <= 0 ) return 0;

		$total_qty = 0;
		$base_total = 0;
		foreach ( $eligible_items as $key ) {
			$item = $cart->get_cart()[ $key ];
			$total_qty += $item['quantity'];
			$base_total += $item['line_total'];
		}

		if ( $total_qty === 0 ) return 0;

		$num_bundles = floor( $total_qty / $set_qty );
		if ( $num_bundles <= 0 ) return 0;

		// Calculate ratio of bundled items to total eligible items to determine eligible base total
		$eligible_bundle_total = ( $base_total / $total_qty ) * ( $num_bundles * $set_qty );

		$type = $config['discount_type'];
		$val = floatval( $config['discount_value'] );
		$discount = 0;
		$item_discounts = [];

		if ( $type === 'percentage' ) {
			$discount = $eligible_bundle_total * ( $val / 100 );
		} elseif ( $type === 'fixed' ) {
			$discount = min( $val * $num_bundles, $eligible_bundle_total ); // Apply fixed val per bundle
		}
		
		// Distribute discount proportionally across eligible items
		if ( $discount > 0 && $base_total > 0 ) {
			foreach ( $eligible_items as $key ) {
				$item = $cart->get_cart()[ $key ];
				$ratio = $item['line_total'] / $base_total;
				$item_discounts[$key] = $discount * $ratio;
			}
		}

		return [ 'total' => $discount, 'items' => $item_discounts ];
	}
}


// TS: 20260312114528

// TS: 20260519165012
