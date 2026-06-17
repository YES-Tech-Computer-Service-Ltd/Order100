<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class O100_Menu_Rules_Enforcer {

	public static function init() {
		// Enforce purchasability
		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'check_purchasable' ), 99, 2 );
		
		// Enforce add to cart validation as a fallback
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 99, 3 );
	}

	/**
	 * Checks if a product is purchasable based on Menu Maker Selling Rules.
	 */
	public static function check_purchasable( $is_purchasable, $product ) {
		// If it's already unpurchasable, don't override to true
		if ( ! $is_purchasable ) return false;
		
		if ( ! $product ) return $is_purchasable;
		
		$product_id = $product->get_id();
		if ( $product->is_type( 'variation' ) ) {
			$product_id = $product->get_parent_id();
		}

		// 1. Check Date Restrictions
		$rule_start = get_post_meta( $product_id, 'o100_menu_rule_date_start', true );
		$rule_end   = get_post_meta( $product_id, 'o100_menu_rule_date_end', true );
		if ( $rule_start || $rule_end ) {
			$current_date = wp_date( 'Y-m-d' );
			if ( $rule_start && $current_date < $rule_start ) return false;
			if ( $rule_end && $current_date > $rule_end ) return false;
		}

		// 2. Check Weekday Restrictions
		$rule_days = get_post_meta( $product_id, 'o100_menu_rule_days', true );
		if ( is_array( $rule_days ) && count( $rule_days ) > 0 && count( $rule_days ) < 7 ) {
			// wp_date('D') returns Mon, Tue, Wed...
			$current_day = strtolower( wp_date( 'D' ) ); 
			if ( ! in_array( $current_day, $rule_days ) ) return false;
		}

		// 3. Check Time Restrictions
		$rule_time_start = get_post_meta( $product_id, 'o100_menu_rule_time_start', true );
		$rule_time_end   = get_post_meta( $product_id, 'o100_menu_rule_time_end', true );
		if ( $rule_time_start || $rule_time_end ) {
			$current_time = wp_date( 'H:i' );
			if ( $rule_time_start && $rule_time_end ) {
				// If start is greater than end (e.g., 22:00 to 02:00), it crosses midnight
				if ( $rule_time_start > $rule_time_end ) {
					if ( $current_time < $rule_time_start && $current_time > $rule_time_end ) {
						return false;
					}
				} else {
					if ( $current_time < $rule_time_start || $current_time > $rule_time_end ) {
						return false;
					}
				}
			} elseif ( $rule_time_start && $current_time < $rule_time_start ) {
				return false;
			} elseif ( $rule_time_end && $current_time > $rule_time_end ) {
				return false;
			}
		}

		// 4. Check Order Method Restrictions
		$rule_methods = get_post_meta( $product_id, 'o100_menu_rule_method', true );
		if ( is_array( $rule_methods ) ) {
			if ( count( $rule_methods ) === 0 ) {
				// No methods allowed at all
				return false;
			} elseif ( count( $rule_methods ) === 1 ) {
				$current_method = self::get_current_order_method();
				if ( $current_method && ! in_array( $current_method, $rule_methods ) ) {
					return false;
				}
			}
		}

		// 5. Check Branch Restrictions
		$rule_branches = get_post_meta( $product_id, 'o100_menu_rule_branches', true );
		if ( is_array( $rule_branches ) && ! in_array( 'all', $rule_branches ) ) {
			if ( count( $rule_branches ) === 0 ) {
				// Explicitly excluded from all branches
				return false;
			}
			$current_branch = self::get_current_branch();
			if ( $current_branch && ! in_array( (string)$current_branch, $rule_branches ) ) {
				return false;
			}
		}

		return $is_purchasable;
	}

	/**
	 * Validate Add to Cart as a fallback.
	 */
	public static function validate_add_to_cart( $passed, $product_id, $quantity ) {
		if ( ! $passed ) return $passed;

		$product = wc_get_product( $product_id );
		if ( ! self::check_purchasable( true, $product ) ) {
			wc_add_notice( __( 'This product is currently not available for order based on your selected branch, order method, or time constraints.', 'order100' ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Get the current order method context.
	 * Tries to fetch from WooCommerce Session or generic global state.
	 */
	private static function get_current_order_method() {
		if ( isset( WC()->session ) ) {
			$method = WC()->session->get( 'o100_order_method' );
			if ( ! $method ) $method = WC()->session->get( 'order_type' ); // fallback
			return $method;
		}
		return '';
	}

	/**
	 * Get the current branch context.
	 * Tries to fetch from WooCommerce Session or generic global state.
	 */
	private static function get_current_branch() {
		if ( isset( WC()->session ) ) {
			$branch = WC()->session->get( 'o100_branch_id' );
			if ( ! $branch ) $branch = WC()->session->get( 'branch_id' ); // fallback
			return $branch;
		}
		return '';
	}

}

O100_Menu_Rules_Enforcer::init();
