<?php
/**
 * Loyalty Auto Apply
 *
 * Automatically applies active monthly reward coupons for the logged-in user.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Loyalty_Auto_Apply {

	public static function init() {
		add_action( 'template_redirect', [ __CLASS__, 'auto_apply_monthly_coupons' ], 20 );
		// Also hook into WC ajax requests (like updating checkout)
		add_action( 'woocommerce_checkout_update_order_review', [ __CLASS__, 'auto_apply_on_checkout_ajax' ] );
	}

	public static function auto_apply_monthly_coupons() {
		if ( ! is_user_logged_in() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		// Only auto-apply on cart, checkout or shop pages
		if ( ! is_cart() && ! is_checkout() && ! is_shop() && ! is_product_category() && ! is_product() ) {
			return;
		}

		self::apply_eligible_coupons( WC()->cart );
	}

	public static function auto_apply_on_checkout_ajax() {
		if ( ! is_user_logged_in() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		self::apply_eligible_coupons( WC()->cart );
	}

	private static function apply_eligible_coupons( $cart ) {
		if ( $cart->is_empty() ) {
			return;
		}

		$user = wp_get_current_user();
		
		if ( ! class_exists( 'O100_Promotions_DB' ) ) {
			require_once O100_PATH . 'core/promotions/engine/class-o100-promotions-db.php';
		}
		
		global $wpdb;
		$table = O100_Promotions_DB::table_name();
		
		// Find unexpired, unused monthly rewards for this user
		$email_like = '%' . $wpdb->esc_like( '"' . $user->user_email . '"' ) . '%';
		
		$query = $wpdb->prepare( "
			SELECT promo_code 
			FROM {$table} 
			WHERE source = 'loyalty_monthly' 
			  AND status = 'active' 
			  AND usage_count < usage_limit 
			  AND (end_date IS NULL OR end_date >= NOW())
			  AND conditions LIKE %s
		", $email_like );
		
		$codes = $wpdb->get_col( $query );
		
		if ( ! empty( $codes ) ) {
			$applied_coupons = $cart->get_applied_coupons();
			$changed = false;
			
			foreach ( $codes as $code ) {
				if ( ! in_array( strtolower( $code ), array_map( 'strtolower', $applied_coupons ) ) ) {
					$cart->add_discount( $code );
					$changed = true;
				}
			}
			
			if ( $changed && ! defined( 'DOING_AJAX' ) ) {
				wc_add_notice( __( 'Your monthly reward has been automatically applied!', 'order100' ), 'success' );
			}
		}
	}
}
