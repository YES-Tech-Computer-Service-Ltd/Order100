<?php
/**
 * Global Interceptor for Customer Privileges
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Privilege_Manager {

	/**
	 * Check if a customer has a specific privilege based on Advanced Rules.
	 * 
	 * @param int|string $customer_identifier WP User ID or Email.
	 * @param string $module The module key (e.g., 'delivery', 'reservation', 'loyalty').
	 * @param string $privilege_key The specific privilege key (e.g., 'free_shipping', 'bypass_limits').
	 * @param array $context_args Array of context info, e.g., ['branch' => 'hq', 'order_type' => 'delivery'].
	 * @return mixed Privilege value (boolean, number, array) or null if no rule applies.
	 */
	public static function get_privilege( $customer_identifier, $module, $privilege_key, $context_args = [] ) {
		global $wpdb;

		$crm_customer_id = self::get_crm_customer_id( $customer_identifier );

		// If no customer record exists, rules that require tags/lists cannot apply.
		// However, 'all_customers' rules might apply.
		
		$customer_tags = [];
		$customer_lists = [];
		if ( $crm_customer_id ) {
			$customer_tags = wp_list_pluck( O100_Customers_DB::get_customer_tags( $crm_customer_id ), 'id' );
			$customer_lists = wp_list_pluck( O100_Customers_DB::get_customer_lists( $crm_customer_id ), 'id' );
		}

		$rules = O100_Customer_Rules_DB::get_rules( [ 'status' => 'active' ] ); // Ordered by priority DESC

		foreach ( $rules as $rule ) {
			// 1. Check Target Audience
			if ( ! self::matches_target( $rule, $customer_tags, $customer_lists ) ) {
				continue;
			}

			// 2. Check Restrictions (Branch, Order Type, Schedule)
			if ( ! self::passes_restrictions( $rule, $context_args ) ) {
				continue;
			}

			// 3. Check if Privilege exists
			$privileges = json_decode( $rule->privileges, true );
			if ( isset( $privileges[ $module ][ $privilege_key ] ) ) {
				$val = $privileges[ $module ][ $privilege_key ];
				// For toggles, false means disabled, but if a rule explicitly defines it, we return it.
				// However, if the rule has it as empty/false, should we keep checking lower priority rules?
				// Yes, if it's a boolean false (meaning no privilege granted), we might want to check other rules that might grant it.
				// Wait, if a high priority rule explicitly denies it? UI doesn't have "Deny", just "Enable".
				// So if $val is empty/false, it means this rule doesn't grant it.
				if ( is_bool( $val ) ) {
					if ( $val === true ) {
						return true;
					}
					// If false, continue checking other rules
				} else {
					if ( ! empty( $val ) ) {
						return $val;
					}
				}
			}
		}

		return null; // No rule grants this privilege
	}

	/**
	 * Helper for true/false boolean privileges.
	 */
	public static function has_privilege( $customer_identifier, $module, $privilege_key, $context_args = [] ) {
		$result = self::get_privilege( $customer_identifier, $module, $privilege_key, $context_args );
		return $result === true;
	}

	private static function get_crm_customer_id( $identifier ) {
		global $wpdb;
		$tbl = O100_Customers_DB::get_table_customers();
		
		if ( is_numeric( $identifier ) && $identifier > 0 ) {
			// Try by WP User ID
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl} WHERE wp_user_id = %d", $identifier ) );
			if ( $id ) return $id;
		}

		if ( is_string( $identifier ) && is_email( $identifier ) ) {
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl} WHERE email = %s", $identifier ) );
			if ( $id ) return $id;
		}

		// Try by WP User ID if they passed an ID string
		$user = get_userdata( intval( $identifier ) );
		if ( $user ) {
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl} WHERE email = %s", $user->user_email ) );
			if ( $id ) return $id;
		}

		return 0;
	}

	private static function matches_target( $rule, $customer_tags, $customer_lists ) {
		if ( $rule->target_type === 'all_customers' ) {
			return true;
		}

		$target_ids = json_decode( $rule->target_ids, true );
		if ( empty( $target_ids ) ) {
			return false; // No targets selected
		}

		if ( $rule->target_type === 'tags' ) {
			return count( array_intersect( $target_ids, $customer_tags ) ) > 0;
		}

		if ( $rule->target_type === 'lists' ) {
			return count( array_intersect( $target_ids, $customer_lists ) ) > 0;
		}

		return false;
	}

	private static function passes_restrictions( $rule, $context ) {
		$restrictions = json_decode( $rule->restrictions, true );
		if ( ! $restrictions ) {
			return true;
		}

		// Branch check
		if ( ! empty( $restrictions['branches'] ) ) {
			if ( ! isset( $context['branch'] ) || ! in_array( (string)$context['branch'], array_map('strval', $restrictions['branches']) ) ) {
				return false;
			}
		}

		// Order Type check
		if ( ! empty( $restrictions['order_types'] ) ) {
			if ( ! isset( $context['order_type'] ) || ! in_array( $context['order_type'], $restrictions['order_types'] ) ) {
				return false;
			}
		}

		// Minimum Spend check (for Loyalty & Checkout)
		if ( ! empty( $restrictions['min_spend'] ) ) {
			$min_spend = floatval( $restrictions['min_spend'] );
			$subtotal = isset( $context['subtotal'] ) ? floatval( $context['subtotal'] ) : 0;
			if ( $subtotal < $min_spend ) {
				return false;
			}
		}

		// Max Party Size (for Reservation)
		if ( ! empty( $restrictions['max_party'] ) ) {
			$max_party = intval( $restrictions['max_party'] );
			$party_size = isset( $context['party_size'] ) ? intval( $context['party_size'] ) : 0;
			if ( $party_size > 0 && $party_size > $max_party ) {
				return false;
			}
		}

		// Schedule check
		$check_time = isset( $context['timestamp'] ) ? $context['timestamp'] : current_time( 'timestamp' );

		if ( ! empty( $restrictions['days'] ) ) {
			$current_day = strtolower( date( 'D', $check_time ) ); // 'mon', 'tue', etc.
			if ( ! in_array( $current_day, $restrictions['days'] ) ) {
				return false;
			}
		}

		if ( ! empty( $restrictions['time_start'] ) && ! empty( $restrictions['time_end'] ) ) {
			$current_hm = date( 'H:i', $check_time );
			$start = $restrictions['time_start'];
			$end = $restrictions['time_end'];

			if ( $start < $end ) {
				if ( $current_hm < $start || $current_hm > $end ) {
					return false;
				}
			} else {
				// Overnight (e.g. 22:00 to 02:00)
				if ( $current_hm < $start && $current_hm > $end ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Retrieve all secret category IDs defined across all active rules.
	 * 
	 * @return array Array of category term IDs
	 */
	public static function get_all_secret_categories() {
		$secret_cats = array();
		if ( ! class_exists( 'O100_Customer_Rules_DB' ) ) {
			return $secret_cats;
		}

		$rules = O100_Customer_Rules_DB::get_rules( array( 'status' => 'active' ) );
		foreach ( $rules as $rule ) {
			$privileges = json_decode( $rule->privileges, true );
			if ( ! empty( $privileges['menu']['secret_menu'] ) && is_array( $privileges['menu']['secret_menu'] ) ) {
				$secret_cats = array_merge( $secret_cats, $privileges['menu']['secret_menu'] );
			}
		}

		// Ensure uniqueness and that they are integers
		return array_unique( array_map( 'intval', $secret_cats ) );
	}

	/**
	 * Retrieve all secret reward/campaign IDs defined across all active rules.
	 * 
	 * @return array Array of campaign IDs
	 */
	public static function get_all_secret_rewards() {
		$secret_rewards = array();
		if ( ! class_exists( 'O100_Customer_Rules_DB' ) ) {
			return $secret_rewards;
		}

		$rules = O100_Customer_Rules_DB::get_rules( array( 'status' => 'active' ) );
		foreach ( $rules as $rule ) {
			$privileges = json_decode( $rule->privileges, true );
			if ( ! empty( $privileges['loyalty']['secret_rewards'] ) && is_array( $privileges['loyalty']['secret_rewards'] ) ) {
				$secret_rewards = array_merge( $secret_rewards, $privileges['loyalty']['secret_rewards'] );
			}
		}

		// Ensure uniqueness and that they are integers
		return array_unique( array_map( 'intval', $secret_rewards ) );
	}

}
