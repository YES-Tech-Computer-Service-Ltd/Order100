<?php
/**
 * Plugin Name:       Order100
 * Plugin URI:        https://order100.io
 * Description:       All-in-one WooCommerce solution: Discount Rules, Loyalty & Rewards, SEO Engine, Email Builder, Push Notifications, and more.
 * Version:           1.2
 * Author:            YES Tech
 * Author URI:        https://order100.io
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       order100
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'O100_VERSION', '1.2' );
define( 'O100_PATH', plugin_dir_path( __FILE__ ) );
define( 'O100_URL', plugin_dir_url( __FILE__ ) );
define( 'O100_FILE', __FILE__ );

/**
 * Main Plugin Class
 */
class Order100 {

	/**
	 * Loyalty Admin Instance
	 * @var O100_Loyalty_Admin
	 */
	public $loyalty_admin;

	/**
	 * SEO Admin Instance
	 * @var O100_SEO_Admin
	 */
	public $seo_admin;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->includes();
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Load plugin text domain for i18n
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'order100', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Include required files
	 */
	public function includes() {
		require_once O100_PATH . 'core/class-o100-license-manager.php';
		require_once O100_PATH . 'core/class-o100-admin-menu.php';
		require_once O100_PATH . 'core/admin/class-o100-diagnostic.php';
		require_once O100_PATH . 'core/class-o100-settings.php';
		require_once O100_PATH . 'core/class-o100-locations.php';
		require_once O100_PATH . 'core/class-o100-fluent-addons.php';
		require_once O100_PATH . 'core/class-o100-menu-builder-tab.php';
		require_once O100_PATH . 'core/class-o100-menu-rules.php';
		require_once O100_PATH . 'core/class-o100-menu-renderer.php';
		require_once O100_PATH . 'core/class-o100-product-modal.php';
		require_once O100_PATH . 'core/class-o100-entry-modal.php';
		require_once O100_PATH . 'core/class-o100-public.php';
		require_once O100_PATH . 'core/class-o100-shipping.php';
		require_once O100_PATH . 'core/class-o100-product-custom-order.php';
		require_once O100_PATH . 'core/class-o100-order-manager.php';
		require_once O100_PATH . 'core/class-o100-side-cart.php';

		// Auth Modal
		require_once O100_PATH . 'core/auth/class-o100-auth-modal.php';

		// Promotions Module
		require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';

		// Loyalty & Referral System
		require_once O100_PATH . 'core/loyalty/class-o100-loyalty-loader.php';
		require_once O100_PATH . 'core/loyalty/admin/class-o100-loyalty-proxy-admin.php';
		require_once O100_PATH . 'core/loyalty/class-o100-loyalty-proxy-frontend.php';
		require_once O100_PATH . 'core/loyalty/native/class-o100-native-punch-card.php';
		require_once O100_PATH . 'core/loyalty/native/class-o100-native-points-conversion.php';

		// Push Notification System
		require_once O100_PATH . 'core/push/class-o100-push-notification.php';
		require_once O100_PATH . 'core/push/class-o100-inbound-api.php';

		// SEO System
		require_once O100_PATH . 'core/seo/class-o100-seo-admin.php';
		require_once O100_PATH . 'core/seo/class-o100-seo-provider.php';
		require_once O100_PATH . 'core/seo/class-o100-seo-ajax.php';
		require_once O100_PATH . 'core/seo/class-o100-seo-frontend.php';
		require_once O100_PATH . 'core/seo/class-o100-google-reviews.php';

		// Custom CMB2 Field Types
		require_once O100_PATH . 'core/class-o100-cmb2-openclose.php';
		require_once O100_PATH . 'core/class-o100-cmb2-holidays.php';

		// Emergency Closure System
		require_once O100_PATH . 'core/class-o100-emergency-closure.php';

		// Reservation System
		require_once O100_PATH . 'core/reservation/class-o100-reservation.php';

		// CRM Manager
		if ( file_exists( O100_PATH . 'core/class-o100-crm-manager.php' ) ) {
			require_once O100_PATH . 'core/class-o100-crm-manager.php';
		}

		// Notification Engine (Email/SMS)
		require_once O100_PATH . 'core/email/bootstrap.php';

		// Load Extensible Modules
		$this->load_modules();
	}

	/**
	 * Load all active modules from the modules/ directory.
	 */
	private function load_modules() {
		$modules_path = O100_PATH . 'modules/';
		if ( ! is_dir( $modules_path ) ) {
			return;
		}

		// Look for bootstrap.php in each module directory
		$modules = glob( $modules_path . '*', GLOB_ONLYDIR );
		if ( ! empty( $modules ) ) {
			foreach ( $modules as $module_dir ) {
				$bootstrap_file = trailingslashit( $module_dir ) . 'bootstrap.php';
				if ( file_exists( $bootstrap_file ) ) {
					require_once $bootstrap_file;
				}
			}
		}
	}

	/**
	 * Initialize the plugin
	 */
	public function init() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Initialize License Manager early
		O100_License();

		new O100_Admin_Menu();
		new O100_Diagnostic();
		new O100_Settings();
		if ( class_exists( 'O100_Locations' ) ) {
			O100_Locations::init();
		}
		new O100_Public();
		O100_Menu_Rules::instance();
		new O100_Shipping();
		new O100_Side_Cart();
		new O100_Auth_Modal();

		// Emergency Closure System (admin bar toggle + frontend banner)
		new O100_Emergency_Closure();

		// Reservation System
		O100_Reservation::instance();

		// Promotions Module Database Upgrade Check
		$promotions_db_version = get_option( 'o100_promotions_db_version', '0' );
		if ( version_compare( $promotions_db_version, '1.1', '<' ) ) {
			O100_Promotions_DB::create_table();
			update_option( 'o100_promotions_db_version', '1.1' );
		}
		
		if ( is_admin() || wp_doing_ajax() ) {
			if ( ! class_exists( 'O100_Promotions_Admin' ) ) {
				require_once O100_PATH . 'core/promotions/class-o100-promotions-admin.php';
			}
			O100_Promotions_Admin::init();
		}

		if ( ! class_exists( 'O100_Promotions_Engine' ) ) {
			require_once O100_PATH . 'core/promotions/class-o100-promotions-engine.php';
		}
		O100_Promotions_Engine::init();

		if ( ! is_admin() || wp_doing_ajax() ) {
			if ( ! class_exists( 'O100_Promotions_Frontend' ) ) {
				require_once O100_PATH . 'core/promotions/class-o100-promotions-frontend.php';
			}
			O100_Promotions_Frontend::init();
		}

		// Push Notification System
		new O100_Push_Notification();
		new O100_Inbound_API();

		// Loyalty & Referral System
		// Simple check: if key is explicitly set to something other than 'on', it's disabled.
		// For fresh installs (key doesn't exist), default to enabled.
		$options = get_option( 'o100_options', array() );
		$enable_loyalty = ! array_key_exists( 'o100_enable_loyalty', $options ) || $options['o100_enable_loyalty'] === 'on';

		if ( $enable_loyalty ) {
			$loyalty_loader = new O100_Loyalty_Loader();
			$loyalty_loader->init();

			if ( file_exists( O100_PATH . 'core/loyalty/admin/class-o100-loyalty-admin.php' ) ) {
				require_once O100_PATH . 'core/loyalty/admin/class-o100-loyalty-admin.php';
			}
			$loyalty_admin = new O100_Loyalty_Admin();
			$this->loyalty_admin  = $loyalty_admin;
			
			// Initialize Proxy Admin to register AJAX routes
			if ( class_exists( 'O100_Loyalty_Proxy_Admin' ) ) {
				O100_Loyalty_Proxy_Admin::init();
			}

			// Initialize Native Punch Card Engine
			if ( class_exists( 'O100_Native_Punch_Card' ) ) {
				O100_Native_Punch_Card::init();
			}
		}

		// SEO System
		$seo_admin = new O100_SEO_Admin();
		$this->seo_admin = $seo_admin;
		new O100_SEO_Ajax();
		new O100_SEO_Frontend();
		new O100_Google_Reviews();

		// CRM Integration - Delay to 'init' to ensure dependencies (like FluentCRM) are fully loaded
		add_action( 'init', array( $this, 'init_crm' ) );
	}

	/**
	 * Initialize CRM Integrations
	 */
	public function init_crm() {
		$options = get_option( 'o100_options' );
		
		// FluentCRM
		if ( isset( $options['o100_enable_fluentcrm'] ) && $options['o100_enable_fluentcrm'] === 'on' ) {
			if ( file_exists( O100_PATH . 'core/crm/class-o100-fluent-crm-adapter.php' ) ) {
				require_once O100_PATH . 'core/crm/class-o100-fluent-crm-adapter.php';
				
				$crm_adapter = new O100_Fluent_CRM_Adapter();
				if ( $crm_adapter->is_active() ) {
					$crm_adapter->init();
					// Ensure custom fields are present - Disabled as per user request to use manual creation
					// if ( is_admin() ) {
					// 	$crm_adapter->ensure_custom_fields();
					// }
				}
			}
		}
	}
}

/**
 * AJAX handler for toggling the loyalty module on/off.
 * Bypasses CMB2 entirely to avoid the checkbox sanitizer deleting the key.
 */
add_action( 'wp_ajax_o100_toggle_loyalty', function() {
	check_ajax_referer( 'o100_toggle_loyalty_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( 'Insufficient permissions' );
	}

	$enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === '1';
	$options = get_option( 'o100_options', array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}

	// Store 'on' for enabled, 'off' for disabled.
	// We use 'off' (not empty/false) so the key always exists in the array
	// and CMB2 cannot interfere with it during its own save cycle.
	$options['o100_enable_loyalty'] = $enabled ? 'on' : 'off';

	update_option( 'o100_options', $options );
	wp_send_json_success( array( 'enabled' => $enabled ) );
} );

/**
 * Enable WooCommerce Authentication for our custom namespace
 * This must be defined globally and early to ensure WooCommerce picks it up.
 */
if ( ! function_exists( 'o100_enable_woo_auth_early' ) ) {
	function o100_enable_woo_auth_early( $is_request ) {
		if ( $is_request ) {
			return $is_request;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( $_SERVER['REQUEST_URI'], 'o100-api/' ) ) {
			return true;
		}

		return $is_request;
	}
}
add_filter( 'woocommerce_rest_is_request', 'o100_enable_woo_auth_early', 1 );
add_filter( 'woocommerce_is_rest_api_request', 'o100_enable_woo_auth_early', 1 );

/**
 * Manual Authentication Fallback
 * If WooCommerce fails to authenticate our custom namespace automatically, 
 * we manually check the API keys against the database.
 */
add_filter( 'rest_authentication_errors', function( $error ) {
	// 0. Force HTTPS recognition for proxies (LiteSpeed/Hostinger)
	if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
		$_SERVER['HTTPS'] = 'on';
	}

	// If already authenticated or has a hard error, skip
	if ( ! empty( $error ) || get_current_user_id() > 0 ) {
		return $error;
	}

	// Only apply to our namespace
	if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( $_SERVER['REQUEST_URI'], 'o100-api/' ) ) {
		$consumer_key    = '';
		$consumer_secret = '';

		// 1. Try Basic Auth headers
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$consumer_key    = $_SERVER['PHP_AUTH_USER'];
			$consumer_secret = $_SERVER['PHP_AUTH_PW'];
		} 
		// 2. Try Authorization header manually
		elseif ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) && 0 === stripos( $_SERVER['HTTP_AUTHORIZATION'], 'Basic ' ) ) {
			$exploded = explode( ':', base64_decode( substr( $_SERVER['HTTP_AUTHORIZATION'], 6 ) ), 2 );
			if ( 2 === count( $exploded ) ) {
				list( $consumer_key, $consumer_secret ) = $exploded;
			}
		}
		// 3. Try Query Parameters
		elseif ( isset( $_GET['consumer_key'] ) && isset( $_GET['consumer_secret'] ) ) {
			$consumer_key    = $_GET['consumer_key'];
			$consumer_secret = $_GET['consumer_secret'];
		}

		if ( ! empty( $consumer_key ) && ! empty( $consumer_secret ) ) {
			global $wpdb;

			// Fallback 1: Search by Secret (Secret is not hashed in DB)
			$row_by_secret = $wpdb->get_row( $wpdb->prepare( 
				"SELECT user_id FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_secret = %s", 
				$consumer_secret
			) );

			if ( $row_by_secret ) {
				wp_set_current_user( $row_by_secret->user_id );
				return $error;
			}

			// Fallback 2: Search by Hashed Key
			$hashed_key = hash( 'sha256', $consumer_key );
			$table_name = $wpdb->prefix . 'woocommerce_api_keys';
			
			$key_data = $wpdb->get_row( $wpdb->prepare( 
				"SELECT user_id, consumer_secret FROM $table_name WHERE consumer_key = %s", 
				$hashed_key
			) );

			if ( $key_data ) {
				if ( hash_equals( $key_data->consumer_secret, $consumer_secret ) ) {
					wp_set_current_user( $key_data->user_id );
				}
			}
		}
	}

	return $error;
}, 20 );

$o100_instance = new Order100();

	/**
	 * Global WLR Debug Logger
	 */

	if ( ! function_exists( 'wlr_debug_log' ) ) {
		function wlr_debug_log( $message ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				if ( is_array( $message ) || is_object( $message ) ) {
					$message = print_r( $message, true );
				}
				error_log( '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", 3, plugin_dir_path( __FILE__ ) . 'wlr-debug.log' );
			}
		}
	}

// TS: 20260127220416

// TS: 20260222110402
