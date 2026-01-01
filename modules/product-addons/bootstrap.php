<?php
/**
 * Product Add-ons Module Bootstrap
 *
 * @package Order100
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'O100_ADDONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'O100_ADDONS_URL', plugin_dir_url( __FILE__ ) );

require_once O100_ADDONS_PATH . 'class-o100-product-addons.php';
require_once O100_ADDONS_PATH . 'class-o100-product-addons-frontend.php';

add_action( 'plugins_loaded', function() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	O100_Product_Addons::instance();
	O100_Product_Addons_Frontend::instance();
}, 20 );

