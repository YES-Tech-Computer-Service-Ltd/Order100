<?php
/**
 * O100 B2B Pricing Manager
 * Handles the display and application of B2B/Catering prices for privileged users.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_B2B_Manager {

	public static function init() {
		// Admin hooks for saving B2B price
		add_action( 'woocommerce_product_options_pricing', array( __CLASS__, 'add_b2b_price_field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_b2b_price_field' ) );
		
		// Frontend hooks for applying B2B price
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'apply_b2b_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( __CLASS__, 'apply_b2b_price' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'apply_b2b_price' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price', array( __CLASS__, 'apply_b2b_price' ), 99, 2 );
	}

	public static function add_b2b_price_field() {
		echo '<div class="options_group">';
		woocommerce_wp_text_input( array(
			'id'          => '_o100_b2b_price',
			'class'       => 'wc_input_price short',
			'label'       => __( 'B2B / Catering Price', 'order100' ) . ' (' . get_woocommerce_currency_symbol() . ')',
			'description' => __( 'This price will override the regular price for customers who match a CRM rule with B2B Pricing enabled.', 'order100' ),
			'desc_tip'    => true,
			'data_type'   => 'price'
		) );
		echo '</div>';
	}

	public static function save_b2b_price_field( $post_id ) {
		$b2b_price = isset( $_POST['_o100_b2b_price'] ) ? wc_format_decimal( $_POST['_o100_b2b_price'] ) : '';
		update_post_meta( $post_id, '_o100_b2b_price', $b2b_price );
	}

	public static function apply_b2b_price( $price, $product ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return $price;
		}

		if ( ! class_exists( 'O100_Privilege_Manager' ) || ! is_user_logged_in() ) {
			return $price;
		}

		static $user_has_b2b = null;
		if ( $user_has_b2b === null ) {
			$loc_id = isset( WC()->session ) ? WC()->session->get( 'o100_location_id' ) : 0;
			$method = isset( WC()->session ) ? WC()->session->get( '_o100_order_method' ) : 'delivery';
			$context = array(
				'branch'     => $loc_id ? intval( $loc_id ) : null,
				'order_type' => $method,
				'timestamp'  => current_time( 'timestamp' ),
			);
			$user_has_b2b = O100_Privilege_Manager::has_privilege( get_current_user_id(), 'menu', 'b2b_pricing', $context );
		}

		if ( $user_has_b2b ) {
			$b2b_price = $product->get_meta( '_o100_b2b_price' );
			if ( $b2b_price !== '' ) {
				return $b2b_price;
			}
		}

		return $price;
	}

}

O100_B2B_Manager::init();
