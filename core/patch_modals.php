<?php
$files = [
	'/Users/kevinqi/development/antigravity/order100/core/class-o100-product-modal.php' => '
	public static function ajax_get_product_info_rest( WP_REST_Request $request ) {
		$_POST[\'product_id\'] = $request->get_param(\'product_id\');
		
		ob_start();
		$instance = new self();
		$instance->ajax_get_product_info();
		$output = ob_get_clean();
	}
',
	'/Users/kevinqi/development/antigravity/order100/core/class-o100-entry-modal.php' => '
	public static function ajax_save_selection_rest( WP_REST_Request $request ) {
		$_POST[\'method\'] = $request->get_param(\'method\');
		$_POST[\'location_id\'] = $request->get_param(\'location_id\');
		
		ob_start();
		$instance = new self();
		$instance->ajax_save_selection();
		$output = ob_get_clean();
	}

	public static function ajax_dismiss_modal_rest( WP_REST_Request $request ) {
		ob_start();
		$instance = new self();
		$instance->ajax_dismiss_modal();
		$output = ob_get_clean();
	}
',
	'/Users/kevinqi/development/antigravity/order100/core/auth/class-o100-auth-modal.php' => '
	public static function handle_login_rest( WP_REST_Request $request ) {
		$_POST[\'log\'] = $request->get_param(\'log\');
		$_POST[\'pwd\'] = $request->get_param(\'pwd\');
		$_POST[\'rememberme\'] = $request->get_param(\'rememberme\');
		
		ob_start();
		$instance = new self();
		$instance->handle_login();
		$output = ob_get_clean();
	}

	public static function handle_register_rest( WP_REST_Request $request ) {
		$_POST[\'email\'] = $request->get_param(\'email\');
		$_POST[\'password\'] = $request->get_param(\'password\');
		
		ob_start();
		$instance = new self();
		$instance->handle_register();
		$output = ob_get_clean();
	}
'
];

foreach ($files as $file => $rest_methods) {
	$content = file_get_contents($file);
	$content = preg_replace(\'/}\s*$/\', "\n\t// REST API Proxies\n" . $rest_methods . "\n}", $content);
	file_put_contents($file, $content);
	echo "Added REST methods to $file\n";
}
