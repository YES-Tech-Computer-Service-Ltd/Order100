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
		require_once $this->base_path . 'engine/class-o100-loyalty-auto-apply.php';
		require_once $this->base_path . 'engine/class-o100-loyalty-emails.php';
		
		O100_Loyalty_DB::maybe_create_tables();
		O100_Loyalty_Hooks::instance()->init();
		O100_Loyalty_Cron::init();
		O100_Loyalty_Auto_Apply::init();
		O100_Loyalty_Emails::init();
		
		// Load frontend widget based on Portal mode setting
		$portal_opts = get_option('o100_portal', array());
		
		// Side Cart mode: native-launcher is NOT loaded (portal JS handles rendering)
		
		// Deprecated: O100_Native_Referral::init();
		// -----------------------------------------

		// Initialize Order100 Restaurant Specific Boosters
		require_once $this->base_path . 'class-o100-loyalty-restaurant-boosters.php';
		new \O100\Loyalty\O100_Loyalty_Restaurant_Boosters();

		// Initialize Checkout Proxy (Points Redemption)
		// Deprecated: O100_Loyalty_Proxy_Checkout::init();

		// Initialize Checkout Rewards (Punch Card Redemption)
		require_once $this->base_path . 'frontend/class-o100-checkout-rewards.php';
		O100_Checkout_Rewards::init();

		// Initialize Frontend Proxy (My Account Dashboard + WPLoyalty suppression)
		require_once $this->base_path . 'frontend/class-o100-loyalty-myaccount.php';
		O100_Loyalty_MyAccount::init();
	}

	/**
	 * Create database tables (called on plugin activation).
	 */
	public static function activate() {
		O100_Loyalty_DB::create_tables();
	}
}

