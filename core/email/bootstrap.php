<?php
/**
 * Order100 Notification Engine Bootstrap
 *
 * Initializes the re-namespaced O100ne engine as a module
 * within the order100 plugin. This file replaces the
 * original o100ne.php plugin entry point.
 *
 * @package Order100\Notification\Engine
 * @since   3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Engine Constants ────────────────────────────────────────────────────────

$engine_dir = plugin_dir_path( __FILE__ );
$engine_url = O100_URL . 'core/email/';

if ( ! defined( 'O100NE_PREFIX' ) ) {
	define( 'O100NE_PREFIX', 'o100ne' );
}

if ( ! defined( 'O100NE_DEBUG' ) ) {
	define( 'O100NE_DEBUG', false );
}

if ( ! defined( 'O100NE_VERSION' ) ) {
	define( 'O100NE_VERSION', '4.0.0' );
}

if ( ! defined( 'O100NE_PLUGIN_URL' ) ) {
	define( 'O100NE_PLUGIN_URL', $engine_url );
}

if ( ! defined( 'O100NE_PLUGIN_PATH' ) ) {
	define( 'O100NE_PLUGIN_PATH', $engine_dir );
}

if ( ! defined( 'O100NE_PLUGIN_BASENAME' ) ) {
	define( 'O100NE_PLUGIN_BASENAME', plugin_basename( O100_FILE ) );
}

if ( ! defined( 'O100NE_IS_DEVELOPMENT' ) ) {
	define( 'O100NE_IS_DEVELOPMENT', false );
}

if ( ! defined( 'O100NE_REST_NAMESPACE' ) ) {
	define( 'O100NE_REST_NAMESPACE', 'o100ne/v1' );
}

// ─── Autoloader ──────────────────────────────────────────────────────────────

require_once $engine_dir . 'vendor/autoload.php';

// ─── Initialize ──────────────────────────────────────────────────────────────

/**
 * Initialize the notification engine constants handler
 */
\Order100\Notification\Engine\Constants\ConstantsHandler::get_instance();

/**
 * Boot the engine on plugins_loaded (after WooCommerce is available)
 */
function o100ne_init() {
	if ( ! function_exists( 'WC' ) ) {
		return; // WooCommerce not active, skip
	}

	add_action( 'before_woocommerce_init', 'o100ne_enable_compatible_hpos' );
	do_action( 'o100ne_before_init' );

	\Order100\Notification\Engine\Initialize::get_instance();
}

/**
 * HPOS compatibility declaration
 */
function o100ne_enable_compatible_hpos() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			O100_FILE,
			true
		);
	}
}

// Hook into plugins_loaded with priority 20 (after WC loads at 10)
add_action( 'plugins_loaded', 'o100ne_init', 20 );



