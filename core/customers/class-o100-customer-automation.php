<?php
/**
 * Customer Tag Automation Engine
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Customer_Automation {

	public static function init() {
		// Hook into order status changes
		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'process_order_trigger' ], 20, 2 );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'process_order_trigger' ], 20, 2 );

		// Abandoned Cart Cron
		add_action( 'o100_crm_cart_abandoned_cron', [ __CLASS__, 'process_abandoned_carts' ] );
		if ( ! wp_next_scheduled( 'o100_crm_cart_abandoned_cron' ) ) {
			wp_schedule_event( time(), 'hourly', 'o100_crm_cart_abandoned_cron' );
		}
	}

	/**
	 * Find pending orders and mark them as abandoned if past the cut-off.
	 */
	public static function process_abandoned_carts() {
		$abandoned_minutes = (int) get_option( 'o100_crm_cart_abandoned_time', 60 );
		$lost_hours = (int) get_option( 'o100_crm_cart_lost_time', 24 );

		$cutoff_abandoned = strtotime( "-{$abandoned_minutes} minutes" );
		$cutoff_lost = strtotime( "-{$lost_hours} hours" );

		global $wpdb;
		// We query 'wc-pending' orders
		$orders = wc_get_orders([
			'status' => 'pending',
			'limit' => 100,
			'date_created' => '<' . $cutoff_abandoned,
		]);

		foreach ( $orders as $order ) {
			$order_date = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
			
			if ( $order_date < $cutoff_lost ) {
				// It's a lost cart. We can mark it cancelled or just update meta
				if ( ! $order->get_meta( '_o100_cart_status' ) || $order->get_meta( '_o100_cart_status' ) === 'abandoned' ) {
					$order->update_meta_data( '_o100_cart_status', 'lost' );
					$order->save_meta_data();
					// Trigger action for lost cart
					do_action( 'o100_crm_cart_lost', $order->get_id() );
				}
			} elseif ( $order_date < $cutoff_abandoned ) {
				// It's an abandoned cart
				if ( ! $order->get_meta( '_o100_cart_status' ) ) {
					$order->update_meta_data( '_o100_cart_status', 'abandoned' );
					$order->save_meta_data();
					// Trigger action for abandoned cart email etc.
					do_action( 'o100_crm_cart_abandoned', $order->get_id() );
				}
			}
		}
	}

	public static function process_order_trigger( $order_id, $order = null ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		$email   = $order->get_billing_email();

		// If no email, we can't identify the CRM customer
		if ( empty( $email ) ) {
			return;
		}

		self::evaluate_customer( $email, $user_id );
	}

	/**
	 * Evaluate a customer against all automation rules.
	 */
	public static function evaluate_customer( $email, $wp_user_id = 0 ) {
		global $wpdb;

		// 1. Get or Sync CRM Customer
		$tbl_customers = class_exists('O100_Customers_DB') ? O100_Customers_DB::get_table_customers() : $wpdb->prefix . 'o100_crm_customers';
		if ( class_exists( 'O100_Customers_Sync' ) && $wp_user_id ) {
			// Ensure sync is up to date
			O100_Customers_Sync::sync_new_user( $wp_user_id );
			$crm_customer_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl_customers} WHERE wp_user_id = %d", $wp_user_id ) );
		} else {
			$crm_customer_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl_customers} WHERE email = %s", $email ) );
		}

		if ( ! $crm_customer_id ) {
			return; // Cannot process without a CRM record
		}

		// 2. Fetch Customer Orders and Base Metrics
		$orders = wc_get_orders([
			'customer' => $wp_user_id ? $wp_user_id : $email,
			'status'   => [ 'completed', 'processing' ],
			'limit'    => -1,
			'return'   => 'objects'
		]);
		$metrics = self::get_customer_metrics( $orders, $wp_user_id );

		// 3. Fetch Automated Tags and Lists
		$auto_tags = $wpdb->get_results( "SELECT id, auto_logic, auto_conditions FROM " . O100_Customers_DB::get_table_tags() . " WHERE is_auto = 1" );
		$auto_lists = $wpdb->get_results( "SELECT id, auto_logic, auto_conditions FROM " . O100_Customers_DB::get_table_lists() . " WHERE is_auto = 1" );

		// 4. Fetch Manual Overrides
		$overrides = [ 'removed_tags' => [], 'added_tags' => [], 'removed_lists' => [], 'added_lists' => [] ];
		if ( $wp_user_id ) {
			$meta = get_user_meta( $wp_user_id, 'o100_crm_manual_overrides', true );
			if ( is_array( $meta ) ) {
				$overrides = wp_parse_args( $meta, $overrides );
			}
		}

		// 5. Evaluate and Assign Tags
		self::process_items( $crm_customer_id, $auto_tags, 'tag', $metrics, $orders, $overrides['removed_tags'], $overrides['added_tags'] );

		// 6. Evaluate and Assign Lists
		self::process_items( $crm_customer_id, $auto_lists, 'list', $metrics, $orders, $overrides['removed_lists'], $overrides['added_lists'] );
	}

	private static function process_items( $crm_customer_id, $items, $type, $metrics, $orders, $removed_overrides, $added_overrides ) {
		foreach ( $items as $item ) {
			$conditions = json_decode( $item->auto_conditions, true );
			if ( ! is_array( $conditions ) || empty( $conditions ) ) {
				continue; // No conditions, skip
			}

			$passed = self::evaluate_conditions( $conditions, $item->auto_logic, $metrics, $orders );

			if ( $passed ) {
				// Condition met. Assign if NOT manually removed.
				if ( ! in_array( $item->id, $removed_overrides ) ) {
					if ( $type === 'tag' ) {
						O100_Customers_DB::assign_tag_to_customer( $crm_customer_id, $item->id );
					} else {
						O100_Customers_DB::assign_list_to_customer( $crm_customer_id, $item->id );
					}
				}
			} else {
				// Condition NOT met. Remove if NOT manually added.
				if ( ! in_array( $item->id, $added_overrides ) ) {
					if ( $type === 'tag' ) {
						O100_Customers_DB::remove_tag_from_customer( $crm_customer_id, $item->id );
					} else {
						O100_Customers_DB::remove_list_from_customer( $crm_customer_id, $item->id );
					}
				}
			}
		}
	}

	private static function evaluate_conditions( $conditions, $logic, $metrics, $orders ) {
		$all_passed = true;
		$any_passed = false;

		foreach ( $conditions as $cond ) {
			$field    = isset( $cond['field'] ) ? $cond['field'] : '';
			$operator = isset( $cond['operator'] ) ? $cond['operator'] : '';
			$value    = isset( $cond['value'] ) ? $cond['value'] : '';

			$result = false;

			if ( $field === 'recent_orders' ) {
				$days = isset( $cond['timeframe_days'] ) ? intval( $cond['timeframe_days'] ) : 30;
				$cutoff = time() - ( $days * DAY_IN_SECONDS );
				$count = 0;
				foreach ( $orders as $o ) {
					if ( $o->get_date_created() && $o->get_date_created()->getTimestamp() >= $cutoff ) {
						$count++;
					}
				}
				$metric_val = $count;
				$cond_val = floatval( $value );
				$result = self::compare_values( $metric_val, $cond_val, $operator );
			} elseif ( $field === 'purchased_product' ) {
				$target = trim( strtolower( $value ) );
				$found = false;
				foreach ( $orders as $o ) {
					foreach ( $o->get_items() as $item ) {
						$prod_id = $item->get_product_id();
						$prod_name = strtolower( $item->get_name() );
						if ( $prod_id == $target || strpos( $prod_name, $target ) !== false ) {
							$found = true;
							break 2;
						}
					}
				}
				if ( $operator === 'includes' ) {
					$result = $found;
				} else {
					$result = ! $found;
				}
			} else {
				if ( isset( $metrics[ $field ] ) ) {
					$metric_val = floatval( $metrics[ $field ] );
					$cond_val   = floatval( $value );
					$result = self::compare_values( $metric_val, $cond_val, $operator );
				}
			}

			if ( $result ) {
				$any_passed = true;
			} else {
				$all_passed = false;
			}
		}

		return ( $logic === 'any' ) ? $any_passed : $all_passed;
	}

	private static function compare_values( $metric_val, $cond_val, $operator ) {
		switch ( $operator ) {
			case '>':  return $metric_val > $cond_val;
			case '>=': return $metric_val >= $cond_val;
			case '<':  return $metric_val < $cond_val;
			case '<=': return $metric_val <= $cond_val;
			case '==':
			case '=':  return $metric_val == $cond_val;
			default:   return false;
		}
	}

	/**
	 * Recalculate metrics in real-time, including 'processing' status.
	 */
	public static function get_customer_metrics( $orders, $wp_user_id ) {
		global $wpdb;
		$metrics = [
			'total_spent'  => 0,
			'total_orders' => 0,
			'aov'          => 0,
			'account_age'  => 0,
		];

		foreach ( $orders as $o ) {
			$metrics['total_spent'] += $o->get_total();
			$metrics['total_orders']++;
		}

		if ( $metrics['total_orders'] > 0 ) {
			$metrics['aov'] = $metrics['total_spent'] / $metrics['total_orders'];
		}

		// Account age
		if ( $wp_user_id ) {
			$user = get_userdata( $wp_user_id );
			if ( $user ) {
				$registered = strtotime( $user->user_registered );
				$now = time();
				$metrics['account_age'] = floor( ( $now - $registered ) / DAY_IN_SECONDS );
			}
		}

		return $metrics;
	}

}

O100_Customer_Automation::init();
