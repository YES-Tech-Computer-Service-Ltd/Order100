<?php
/**
 * Promotions Proxy Admin
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Promotions_Proxy_Admin {

	public static function init() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	public static function enqueue_scripts( $hook ) {
		// Only enqueue on our promotions page. The hook name might vary depending on how it's registered.
		// Since order100 pages usually start with 'order100_page_', we can check strpos or just enqueue.
		if ( strpos( $hook, 'order100' ) !== false || strpos( $hook, 'promotions' ) !== false || isset($_GET['page']) && $_GET['page'] === 'o100-promotions' ) {
			wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0', true );
			wp_enqueue_script( 'o100-admin-js', O100_URL . 'assets/js/o100-admin.js', array( 'jquery', 'sweetalert2' ), time(), true );
		}
	}

	public static function render_page() {
		require_once __DIR__ . '/views/proxy-main.php';
	}
}
