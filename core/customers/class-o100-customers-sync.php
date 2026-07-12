<?php
/**
 * Order100 Native Customer CRM Synchronization Engine
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Customers_Sync {

	/**
	 * Initialize the synchronization engine.
	 */
	public static function init() {
		// Hook into WooCommerce order processing
		add_action( 'woocommerce_checkout_order_processed', [ __CLASS__, 'sync_order_customer' ], 10, 3 );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'sync_order_customer_by_id' ], 10, 1 );

		// Hook into WP user registration
		add_action( 'user_register', [ __CLASS__, 'sync_new_user' ], 10, 1 );
		
		// Compliance: Delete user hook
		add_action( 'delete_user', [ __CLASS__, 'sync_delete_user' ], 10, 1 );
	}

	/**
	 * Compliance: Cascading delete of CRM profile when WP User is deleted.
	 */
	public static function sync_delete_user( $user_id ) {
		if ( ! class_exists( 'O100_Customers_DB' ) ) return;
		
		// Check compliance setting
		if ( get_option( 'o100_crm_data_deletion', 0 ) == 1 ) {
			global $wpdb;
			$tbl_customers = O100_Customers_DB::get_table_customers();
			
			$customer = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$tbl_customers} WHERE wp_user_id = %d", $user_id ) );
			if ( $customer ) {
				$wpdb->delete( $tbl_customers, [ 'id' => $customer->id ] );
				$wpdb->delete( O100_Customers_DB::get_table_relationships(), [ 'customer_id' => $customer->id ] );
			}
		}
	}

	/**
	 * Sync customer data when a WooCommerce order is processed.
	 */
	public static function sync_order_customer( $order_id, $posted_data, $order ) {
		self::process_order( $order );
	}

	public static function sync_order_customer_by_id( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::process_order( $order );
		}
	}

	/**
	 * Process order to update/create customer record and apply smart profiling.
	 */
	public static function process_order( $order ) {
		if ( ! class_exists( 'O100_Customers_DB' ) ) return;

		$email = $order->get_billing_email();
		if ( empty( $email ) ) return;

		global $wpdb;
		$tbl_customers = O100_Customers_DB::get_table_customers();
		$customer = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_customers} WHERE email = %s", $email ) );
		
		$customer_id = $customer ? $customer->id : 0;
		$user_id = $order->get_customer_id() > 0 ? $order->get_customer_id() : ($customer ? $customer->wp_user_id : null);
		$first_name = $order->get_billing_first_name();
		$last_name = $order->get_billing_last_name();
		$phone = $order->get_billing_phone();
		
		// Determine true creation date
		$created_at = current_time( 'mysql' );
		if ( $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$created_at = $user->user_registered;
			}
		}
		// If still empty or no user, use order date as fallback
		if ( empty( $created_at ) || ! $user_id ) {
			$order_date = $order->get_date_created();
			if ( $order_date ) {
				$created_at = $order_date->date('Y-m-d H:i:s');
			}
		}

		// 1. Create or Update Customer Base Record
		$is_new_customer = false;
		if ( ! $customer_id ) {
			$wpdb->insert(
				$tbl_customers,
				[
					'email'        => $email,
					'wp_user_id'   => $user_id,
					'first_name'   => $first_name,
					'last_name'          => $last_name,
					'phone'              => $phone,
					'status'             => 'unsubscribed', // Temporary default
					'acquisition_source' => 'woocommerce',
					'created_at'         => $created_at,
					'updated_at'         => current_time( 'mysql' )
				],
				[ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
			);
			$customer_id = $wpdb->insert_id;
			$is_new_customer = true;
		} else {
			$wpdb->update(
				$tbl_customers,
				[
					'wp_user_id'      => $user_id,
					'first_name'      => $first_name ?: $customer->first_name,
					'last_name'       => $last_name ?: $customer->last_name,
					'phone'           => $phone ?: $customer->phone,
					'updated_at'      => current_time( 'mysql' )
				],
				[ 'id' => $customer_id ],
				[ '%d', '%s', '%s', '%s', '%s' ],
				[ '%d' ]
			);
		}

		// Handle Opt-in Logic (Only apply if they are new, or currently unsubscribed and they opted in)
		if ( class_exists('O100_Customers_Frontend') ) {
			$current_status = $is_new_customer ? 'unsubscribed' : $customer->status;
			// If they aren't already actively subscribed, check their opt-in preference on this checkout
			if ( $current_status !== 'subscribed' ) {
				$final_status = O100_Customers_Frontend::handle_order_optin_logic( $order->get_id(), $customer_id, $email );
				if ( $final_status !== $current_status && $final_status !== 'pending' ) {
					// handle_order_optin_logic already sets to 'pending' inside the DB if Double Opt-in is true.
					// So we only update here if it resolves to 'subscribed' or 'unsubscribed'.
					$wpdb->update( $tbl_customers, [ 'status' => $final_status ], [ 'id' => $customer_id ] );
				}
			}
		}

		// 2. Default List and Tag Assignment
		$default_list = get_option( 'o100_crm_default_list', '' );
		if ( ! empty( $default_list ) ) {
			O100_Customers_DB::assign_list_to_customer( $customer_id, intval( $default_list ) );
		}
		
		$default_tag = get_option( 'o100_crm_default_tag', '' );
		if ( ! empty( $default_tag ) ) {
			O100_Customers_DB::assign_tag_to_customer( $customer_id, intval( $default_tag ) );
		}

		// 3. Clear System Tags (Smart Profiling recalculation starts clean)
		O100_Customers_DB::clear_customer_system_tags( $customer_id );

		// 4. Perform Full Aggregation over Historical Orders
		self::recalculate_smart_profiling( $customer_id, $email );
	}

	public static function get_smart_tag_rules() {
		$defaults = [
			'vip' => [ 'enabled' => true, 'threshold' => 10, 'title' => 'VIP Customer', 'desc' => 'Total completed orders greater than or equal to threshold.' ],
			'regular' => [ 'enabled' => true, 'threshold' => 3, 'title' => 'Regular Buyer', 'desc' => 'Total completed orders greater than or equal to threshold.' ],
			'high_roller' => [ 'enabled' => true, 'threshold' => 80, 'title' => 'High-Roller', 'desc' => 'Average Order Value (AOV) is greater than or equal to threshold.' ],
			'budget' => [ 'enabled' => true, 'threshold' => 25, 'title' => 'Budget Buyer', 'desc' => 'Average Order Value (AOV) is less than or equal to threshold.' ],
			'family' => [ 'enabled' => true, 'threshold' => 4, 'title' => 'Family/Group', 'desc' => 'Average items per order greater than or equal to threshold.' ],
			'solo' => [ 'enabled' => true, 'threshold' => 2, 'title' => 'Solo Eater', 'desc' => 'Average items per order less than or equal to threshold.' ],
			'promo' => [ 'enabled' => true, 'threshold' => 50, 'title' => 'Promo Seeker', 'desc' => 'Percentage of orders using a discount is greater than threshold %.' ],
			'category' => [ 'enabled' => true, 'threshold' => 3, 'title' => 'Category Affinity', 'desc' => 'Purchased items from a specific category more than threshold times (Generates dynamic tags like "Breakfast Lover").' ],
			'time_night' => [ 'enabled' => true, 'threshold' => 40, 'start' => '22:00', 'end' => '04:00', 'title' => 'Night Owl', 'desc' => 'Percentage of orders placed between start and end time > threshold %.' ],
			'time_morning' => [ 'enabled' => true, 'threshold' => 40, 'start' => '06:00', 'end' => '10:00', 'title' => 'Early Bird', 'desc' => 'Percentage of orders placed between start and end time > threshold %.' ],
			'hesitant' => [ 'enabled' => true, 'threshold' => 3, 'title' => 'Hesitant Buyer', 'desc' => 'Total abandoned, cancelled, or failed orders greater than or equal to threshold.' ],
			'inactive' => [ 'enabled' => true, 'threshold' => 30, 'title' => 'Inactive Buyer', 'desc' => 'Customer has not placed any completed order in the last threshold days.' ],
			'dietary' => [ 'enabled' => true, 'title' => 'Dietary NLP', 'desc' => 'Automatically scans order notes for keywords to generate tags (Vegan, Gluten-Free, Non-Spicy).' ]
		];
		$saved = get_option( 'o100_crm_smart_tag_rules', [] );
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Recalculate RFM and apply NLP Smart Tags based on historical orders.
	 */
	public static function recalculate_smart_profiling( $customer_id, $email ) {
		global $wpdb;

		$rules = self::get_smart_tag_rules();

		$orders = wc_get_orders([
			'billing_email' => $email,
			'limit'         => -1,
		]);

		$total_spent = 0;
		$total_orders = 0;
		$total_items = 0;
		$discount_orders_count = 0;
		$delivery_count = 0;
		$takeaway_count = 0;
		$category_tally = [];
		$last_order_date = null;
		
		$dietary_tags = [];

		$failed_count = 0;
		$time_tally = ['night' => 0, 'morning' => 0];
		
		$valid_statuses = ['completed', 'processing', 'on-hold'];
		$hesitant_statuses = ['cancelled', 'failed', 'pending'];

		foreach ( $orders as $ord ) {
			$status = $ord->get_status();
			if ( in_array( $status, $hesitant_statuses ) ) {
				$failed_count++;
				continue;
			}
			if ( ! in_array( $status, $valid_statuses ) ) continue;

			$total_orders++;
			$total_spent += $ord->get_total();
			$total_items += $ord->get_item_count();

			if ( $ord->get_discount_total() > 0 ) {
				$discount_orders_count++;
			}

			// Delivery vs Takeaway
			$methods = $ord->get_shipping_methods();
			if ( ! empty( $methods ) ) {
				$method_id = strtolower( reset( $methods )->get_method_id() );
				if ( strpos( $method_id, 'pickup' ) !== false ) $takeaway_count++;
				else $delivery_count++;
			}

			// Time Habit Logic
			$time_str = $ord->get_date_created()->date('H:i');
			
			// Night Owl check (crosses midnight)
			$n_start = $rules['time_night']['start'];
			$n_end = $rules['time_night']['end'];
			if ( $n_start > $n_end ) {
				if ( $time_str >= $n_start || $time_str <= $n_end ) $time_tally['night']++;
			} else {
				if ( $time_str >= $n_start && $time_str <= $n_end ) $time_tally['night']++;
			}

			// Early Bird check
			$m_start = $rules['time_morning']['start'];
			$m_end = $rules['time_morning']['end'];
			if ( $time_str >= $m_start && $time_str <= $m_end ) $time_tally['morning']++;

			// NLP on Order Note
			if ( isset($rules['dietary']['enabled']) && $rules['dietary']['enabled'] ) {
				$note = strtolower( $ord->get_customer_note() );
				if ( ! empty( $note ) ) {
					if ( preg_match( '/no\s+spicy|not\s+spicy|mild/i', $note ) ) $dietary_tags['Non-Spicy'] = true;
					if ( preg_match( '/vegan/i', $note ) ) $dietary_tags['Vegan'] = true;
					if ( preg_match( '/gluten\s*free|gf/i', $note ) ) $dietary_tags['Gluten-Free'] = true;
					if ( preg_match( '/no\s+cilantro|without\s+cilantro/i', $note ) ) $dietary_tags['No Cilantro'] = true;
				}
			}

			// Category Affinity
			if ( isset($rules['category']['enabled']) && $rules['category']['enabled'] ) {
				foreach ( $ord->get_items() as $item ) {
					$product_id = $item->get_product_id();
					$terms = get_the_terms( $product_id, 'product_cat' );
					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						foreach ( $terms as $term ) {
							if ( ! isset( $category_tally[$term->name] ) ) $category_tally[$term->name] = 0;
							$category_tally[$term->name] += $item->get_quantity();
						}
					}
				}
			}

			// Date
			if ( $status === 'completed' ) {
				if ( is_null( $last_order_date ) || $ord->get_date_created()->getTimestamp() > strtotime( $last_order_date ) ) {
					$last_order_date = gmdate( 'Y-m-d H:i:s', $ord->get_date_created()->getTimestamp() );
				}
			}
		}

		// Update base metrics
		$tbl_customers = O100_Customers_DB::get_table_customers();
		$wpdb->update(
			$tbl_customers,
			[
				'total_orders' => $total_orders,
				'total_spent' => $total_spent,
				'delivery_count' => $delivery_count,
				'takeaway_count' => $takeaway_count,
				'last_order_date' => $last_order_date
			],
			[ 'id' => $customer_id ]
		);

		// SMART PROFILING TAG ASSIGNMENTS
		$smart_tags_to_apply = array_keys( $dietary_tags );

		// Hesitant Buyer
		if ( isset($rules['hesitant']['enabled']) && $rules['hesitant']['enabled'] && $failed_count >= floatval($rules['hesitant']['threshold']) ) {
			$smart_tags_to_apply[] = $rules['hesitant']['title'];
		}

		if ( $total_orders > 0 ) {
			// Frequency
			if ( isset($rules['vip']['enabled']) && $rules['vip']['enabled'] && $total_orders >= floatval($rules['vip']['threshold']) ) {
				$smart_tags_to_apply[] = $rules['vip']['title'];
			} elseif ( isset($rules['regular']['enabled']) && $rules['regular']['enabled'] && $total_orders >= floatval($rules['regular']['threshold']) ) {
				$smart_tags_to_apply[] = $rules['regular']['title'];
			}

			// Monetary (AOV)
			$aov = $total_spent / $total_orders;
			if ( isset($rules['high_roller']['enabled']) && $rules['high_roller']['enabled'] && $aov >= floatval($rules['high_roller']['threshold']) ) {
				$smart_tags_to_apply[] = $rules['high_roller']['title'];
			}
			if ( isset($rules['budget']['enabled']) && $rules['budget']['enabled'] && $aov <= floatval($rules['budget']['threshold']) ) {
				$smart_tags_to_apply[] = $rules['budget']['title'];
			}

			// Discount Sensitivity
			$discount_ratio = ($discount_orders_count / $total_orders) * 100;
			if ( isset($rules['promo']['enabled']) && $rules['promo']['enabled'] && $discount_ratio >= floatval($rules['promo']['threshold']) ) {
				$smart_tags_to_apply[] = $rules['promo']['title'];
			}

			// Household Size
			$avg_items = $total_items / $total_orders;
			if ( isset($rules['family']['enabled']) && $rules['family']['enabled'] && $avg_items >= floatval($rules['family']['threshold']) ) {
				$smart_tags_to_apply[] = $rules['family']['title'];
			}
			if ( isset($rules['solo']['enabled']) && $rules['solo']['enabled'] && $avg_items <= floatval($rules['solo']['threshold']) ) {
				$smart_tags_to_apply[] = $rules['solo']['title'];
			}

			// Time Habit
			$night_ratio = ($time_tally['night'] / $total_orders) * 100;
			if ( isset($rules['time_night']['enabled']) && $rules['time_night']['enabled'] && $night_ratio >= floatval($rules['time_night']['threshold']) ) {
				$smart_tags_to_apply[] = $rules['time_night']['title'];
			}
			$morning_ratio = ($time_tally['morning'] / $total_orders) * 100;
			if ( isset($rules['time_morning']['enabled']) && $rules['time_morning']['enabled'] && $morning_ratio >= floatval($rules['time_morning']['threshold']) ) {
				$smart_tags_to_apply[] = $rules['time_morning']['title'];
			}

			// Inactive Buyer Check
			if ( isset($rules['inactive']['enabled']) && $rules['inactive']['enabled'] && ! is_null($last_order_date) ) {
				$days_since_last = (time() - strtotime($last_order_date)) / (60 * 60 * 24);
				if ( $days_since_last >= floatval($rules['inactive']['threshold']) ) {
					$smart_tags_to_apply[] = $rules['inactive']['title'];
				}
			}

			// Category Affinity (Find top category)
			if ( ! empty( $category_tally ) ) {
				arsort( $category_tally );
				$top_category = key( $category_tally );
				if ( $category_tally[$top_category] >= floatval($rules['category']['threshold']) ) {
					$smart_tags_to_apply[] = $top_category . ' Lover';
				}
			}
		}

		// Apply all smart tags
		foreach ( $smart_tags_to_apply as $tag_name ) {
			$tag_id = O100_Customers_DB::add_tag( $tag_name, 'Smart Profiling Generated', 1 );
			if ( $tag_id ) {
				O100_Customers_DB::assign_tag_to_customer( $customer_id, $tag_id );
			}
		}
	}

	/**
	 * Sync new WP user registration to CRM.
	 */
	public static function sync_new_user( $user_id ) {
		if ( ! class_exists( 'O100_Customers_DB' ) ) return;

		$user = get_userdata( $user_id );
		if ( ! $user ) return;

		global $wpdb;
		$tbl_customers = O100_Customers_DB::get_table_customers();
		
		$customer = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$tbl_customers} WHERE email = %s", $user->user_email ) );

		if ( $customer ) {
			$wpdb->update( $tbl_customers, [ 'wp_user_id' => $user_id ], [ 'id' => $customer->id ] );
			$customer_id = $customer->id;
		} else {
			$current_time = current_time( 'mysql' );
			$wpdb->insert(
				$tbl_customers,
				[
					'wp_user_id'         => $user_id,
					'email'              => $user->user_email,
					'first_name'         => $user->first_name,
					'last_name'          => $user->last_name,
					'status'             => 'subscribed',
					'acquisition_source' => 'woocommerce',
					'created_at'         => $current_time,
					'updated_at'         => $current_time,
				],
				[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
			);
			$customer_id = $wpdb->insert_id;
		}

		// Ensure they get the default list/tag if set during sync process
		$default_list = get_option( 'o100_crm_default_list', '' );
		if ( ! empty( $default_list ) ) O100_Customers_DB::assign_list_to_customer( $customer_id, intval( $default_list ) );
		
		$default_tag = get_option( 'o100_crm_default_tag', '' );
		if ( ! empty( $default_tag ) ) O100_Customers_DB::assign_tag_to_customer( $customer_id, intval( $default_tag ) );
	}
}
