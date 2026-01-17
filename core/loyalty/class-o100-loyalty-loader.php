<?php
/**
 * WFA Loyalty Loader
 *
 * Bootstrap file that integrates WPLoyalty core and addons into
 * the Woo ExFood Addon plugin. Replaces the standalone
 * wp-loyalty-rules.php entry point.
 *
 * @package Order100
 * @since   3.2.0
 */

defined( 'ABSPATH' ) or die;

class O100_Loyalty_Loader {

	/** @var string Base path for the loyalty module */
	private $base_path;

	/** @var string Base URL for loyalty assets */
	private $base_url;

	/** @var bool Whether the loader has already run */
	private static $loaded = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->base_path = O100_PATH . 'core/loyalty/';
		$this->base_url  = O100_URL . 'core/loyalty/';
	}

	/**
	 * Initialize the loyalty system.
	 *
	 * Called from Order100::init() after WooCommerce is confirmed active.
	 */
	public function init() {
		if ( self::$loaded ) {
			return;
		}
		self::$loaded = true;

		// --- O100 Native Engine Initialization ---
		require_once $this->base_path . 'engine/class-o100-loyalty-db.php';
		require_once $this->base_path . 'engine/class-o100-loyalty-engine.php';
		require_once $this->base_path . 'engine/class-o100-loyalty-hooks.php';
		require_once $this->base_path . 'engine/class-o100-reward-gateway.php';
		require_once $this->base_path . 'engine/class-o100-loyalty-cron.php';
		
		O100_Loyalty_DB::maybe_create_tables();
		O100_Loyalty_Hooks::instance()->init();
		O100_Loyalty_Cron::init();
		
		// Load frontend widget based on Portal mode setting
		$portal_opts = get_option('o100_portal', array());
		$widget_mode = isset($portal_opts['o100_portal_widget_mode']) ? $portal_opts['o100_portal_widget_mode'] : 'sidecart';
		
		if ( $widget_mode === 'standalone' ) {
			require_once $this->base_path . 'native/class-o100-native-launcher.php';
			O100_Native_Launcher::init();
		}
		// Side Cart mode: native-launcher is NOT loaded (portal JS handles rendering)
		
		require_once $this->base_path . 'native/class-o100-native-referral.php';
		O100_Native_Referral::init();
		// -----------------------------------------

		// Initialize Order100 Restaurant Specific Boosters
		require_once $this->base_path . 'class-o100-loyalty-restaurant-boosters.php';
		new \O100\Loyalty\O100_Loyalty_Restaurant_Boosters();

		// Initialize Checkout Proxy (Points Redemption)
		require_once $this->base_path . 'class-o100-loyalty-proxy-checkout.php';
		O100_Loyalty_Proxy_Checkout::init();

		// Initialize Checkout Rewards (Punch Card Redemption)
		require_once $this->base_path . 'class-o100-checkout-rewards.php';
		O100_Checkout_Rewards::init();

		// Initialize Frontend Proxy (My Account Dashboard + WPLoyalty suppression)
		require_once $this->base_path . 'class-o100-loyalty-proxy-frontend.php';
		O100_Loyalty_Proxy_Frontend::init();
	}

	/**
	 * Create database tables (called on plugin activation).
	 */
	public static function activate() {
		O100_Loyalty_DB::create_tables();
	}
}


// TS: 20260117123449
