<?php
/**
 * Promotions Module Loader
 *
 * Boots up the Promotions module and requires files based on context.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Promotions_Loader {

	private static $loaded = false;
	private static $base_path;

	public static function init() {
		if ( self::$loaded ) {
			return;
		}
		self::$loaded = true;
		self::$base_path = O100_PATH . 'core/promotions/';

		// Always load engine and DB for points logic / calculation
		require_once self::$base_path . 'engine/class-o100-promotions-db.php';
		require_once self::$base_path . 'engine/class-o100-promotions-engine.php';
		
		O100_Promotions_Engine::init();

		$is_rest = defined( 'REST_REQUEST' ) || ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/wp-json/' ) !== false );
		
		// Load Admin & API Controllers
		if ( is_admin() || wp_doing_ajax() || $is_rest ) {
			require_once self::$base_path . 'admin/class-o100-promotions-proxy-admin.php';
			require_once self::$base_path . 'api/class-o100-promotions-campaign-controller.php';
			
			O100_Promotions_Proxy_Admin::init();
			O100_Promotions_Campaign_Controller::init();
		}

		// Load Frontend for checking out, cart, etc
		if ( ! is_admin() || wp_doing_ajax() ) {
			require_once self::$base_path . 'frontend/class-o100-promotions-frontend.php';
			O100_Promotions_Frontend::init();
		}
	}
}
