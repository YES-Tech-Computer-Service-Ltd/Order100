<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class O100_Loyalty_Proxy_Admin {

	public static function init() {}

	public static function render_page() {
		include O100_PATH . 'core/loyalty/admin/views/proxy-main.php';
	}
}
