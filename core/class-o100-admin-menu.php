<?php
/**
 * Admin Menu Registration
 *
 * Registers Order100 as a top-level admin menu with sub-menu items
 * for each module. Replaces the old tab-based navigation.
 *
 * Menu structure (ordered by typical user setup flow):
 *  1. Dashboard
 *  2. General Settings  (time schedule, checkout, navigation → from old tabs)
 *  3. Food Ordering     (ExFood module, shown only when module active)
 *  4. Customers          (CRM)
 *  5. Discounts
 *  6. Loyalty
 *  7. Notifications     (email + SMS)
 *  8. SEO
 *  9. Automation
 * 10. Integration       (API, push, FluentCRM)
 * 11. Documentation
 *
 * @package Order100
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Admin_Menu {

	/**
	 * Top-level menu slug
	 */
	const MENU_SLUG = 'order100';

	/**
	 * Required capability
	 */
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Menu position in WP sidebar
	 */
	const POSITION = 56; // Below WooCommerce (55)

	/**
	 * Boot
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ), 9 );
		add_action( 'admin_menu', array( $this, 'hide_native_woo_menus' ), 999 );
		add_action( 'admin_head', array( $this, 'inject_menu_icon_css' ) );
		add_action( 'wp_ajax_o100_run_health_check', array( $this, 'ajax_run_health_check' ) );

		add_action( 'admin_init', function() {
			if ( isset($_GET['o100_iframe']) && $_GET['o100_iframe'] == '1' ) {
				if ( ! defined('IFRAME_REQUEST') ) {
					define('IFRAME_REQUEST', true);
				}
			}
		});

		// Hide WP Admin UI if loaded via seamless iframe (Use admin_footer because WLL aggressively clears admin_head)
		add_action( 'admin_footer', function() {
			if ( isset($_GET['o100_iframe']) && $_GET['o100_iframe'] == '1' ) {
				echo '<style>
					#adminmenumain, #wpadminbar, #wpfooter, .update-nag, .notice { display: none !important; }
					#wpcontent, #wpfooter { margin-left: 0 !important; }
					html.wp-toolbar { padding-top: 0 !important; }
					body { background: #fff !important; }
					/* Hide all native bottom-left CMB2 save buttons globally */
			.cmb-form > input[type="submit"],
			.cmb-button-submit,
			.o100-card-body > input[name="submit-cmb"] {
				display: none !important;
			}
		</style>';
			}
		}, 9999);
	}

	/**
	 * Hide native WooCommerce Products menu if configured
	 */
	public function hide_native_woo_menus() {
		$misc = get_option( 'o100_misc', array() );
		if ( isset( $misc['o100_hide_woo_products'] ) && $misc['o100_hide_woo_products'] === 'on' ) {
			remove_menu_page( 'edit.php?post_type=product' );
		}
	}

	/**
	 * Register top-level menu + all sub-menus
	 */
	public function register_menus() {

		// Use the actual logo file as menu icon (grey version for WP sidebar)
		$icon_url = O100_URL . 'assets/logo/icon-20x20.png';

		add_menu_page(
			__( 'Order100', 'order100' ),
			__( 'Order100', 'order100' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			$icon_url,
			self::POSITION
		);

		// (Menu Maker moved to after General Settings)

		// ── Sub-menus (in display order) ────────────────────────────────

		// 1. Dashboard (duplicate of parent to rename "Order100" → "Dashboard")
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'order100' ),
			__( 'Dashboard', 'order100' ),
			self::CAPABILITY,
			self::MENU_SLUG, // Same slug as parent = replaces parent entry
			array( $this, 'render_dashboard' )
		);

		// 2. General Settings
		add_submenu_page(
			self::MENU_SLUG,
			__( 'General Settings', 'order100' ),
			__( 'General Settings', 'order100' ),
			self::CAPABILITY,
			'o100-settings',
			array( $this, 'render_general_settings' )
		);

		// 2.5 Menu Maker
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Menu Management', 'order100' ),
			__( 'Menu Management', 'order100' ),
			self::CAPABILITY,
			'o100-menu-maker',
			array( O100_Menu_Maker_Admin::instance(), 'render_page' )
		);

		// 3. Food Ordering (conditionally shown)
		if ( apply_filters( 'o100_module_food_ordering_active', false ) ) {
			add_submenu_page(
				self::MENU_SLUG,
				__( 'Food Ordering', 'order100' ),
				__( 'Food Ordering', 'order100' ),
				self::CAPABILITY,
				'o100-food-ordering',
				array( $this, 'render_placeholder' )
			);
		}

		// 4. Customers
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Customers', 'order100' ),
			__( 'Customers', 'order100' ) . ' <span class="o100-menu-badge o100-menu-badge--pro">Pro</span>',
			self::CAPABILITY,
			'o100-customers',
			array( $this, 'render_customers' )
		);

		// 5. Product Add-ons — removed from menu; now managed as "Item Modifiers" in General Settings > Misc tab.
		// add_submenu_page(
		// 	self::MENU_SLUG,
		// 	__( 'Product Add-ons', 'order100' ),
		// 	__( 'Product Add-ons', 'order100' ),
		// 	self::CAPABILITY,
		// 	'edit.php?post_type=exwo_glboptions'
		// );

		// 5. Reservations
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Reservations', 'order100' ),
			__( 'Reservations', 'order100' ),
			self::CAPABILITY,
			'o100-reservations',
			array( $this, 'render_reservations' )
		);

		// 6. Promotions
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Promotions', 'order100' ),
			__( 'Promotions', 'order100' ),
			self::CAPABILITY,
			'o100-promotions',
			array( $this, 'render_promotions' )
		);

		// 7. Loyalty
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Loyalty', 'order100' ),
			__( 'Loyalty', 'order100' ),
			self::CAPABILITY,
			'o100-loyalty',
			array( $this, 'render_loyalty' )
		);

		// 7. Notifications (Email + SMS)
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Notifications', 'order100' ),
			__( 'Notifications', 'order100' ),
			self::CAPABILITY,
			'o100-notifications',
			array( $this, 'render_notifications' )
		);

		// 8. Tools (SEO + Health Check + Delivery Sim)
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Tools', 'order100' ),
			__( 'Tools', 'order100' ),
			self::CAPABILITY,
			'o100-tools',
			array( $this, 'render_tools' )
		);

		// 9. Automation
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Automations', 'order100' ),
			__( 'Automations', 'order100' ),
			self::CAPABILITY,
			'o100-automation',
			array( $this, 'render_automation' )
		);

		// 10. Integration — REMOVED: consolidated into General Settings > Integrations tab

		// 10. App Devices — REMOVED: consolidated into General Settings > Integrations tab

		// 11. Documentation
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Documentation', 'order100' ),
			__( 'Documentation', 'order100' ) . ' <span class="dashicons dashicons-external" style="font-size:12px;line-height:20px;width:12px;height:12px;"></span>',
			self::CAPABILITY,
			'o100-docs',
			array( $this, 'render_docs' )
		);
	}

	/**
	 * Inject the logo icon as base64 SVG via CSS
	 * This ensures the icon shows in the WP admin sidebar
	 */
	public function inject_menu_icon_css() {
		?>
		<style>
			/* Order100 menu icon — max specificity to prevent overrides */
			body.wp-admin #adminmenu li.toplevel_page_order100 div.wp-menu-image img,
			body.wp-admin #adminmenu li.toplevel_page_order100.wp-menu-open div.wp-menu-image img,
			body.wp-admin #adminmenu li.toplevel_page_order100.wp-has-current-submenu div.wp-menu-image img,
			body.wp-admin #adminmenu li.toplevel_page_order100.current div.wp-menu-image img,
			body.wp-admin #adminmenu li.toplevel_page_order100:hover div.wp-menu-image img {
				width: 20px !important;
				height: 20px !important;
				max-width: 20px !important;
				max-height: 20px !important;
				min-width: 20px !important;
				min-height: 20px !important;
				display: block !important;
				object-fit: contain !important;
				padding: 0 !important;
				margin: 6px auto !important;
				box-sizing: content-box !important;
			}
			/* Default state: grey */
			body.wp-admin #adminmenu li.toplevel_page_order100 div.wp-menu-image img {
				opacity: 0.7;
				filter: brightness(0) invert(0.7);
			}
			/* Active/hover state: white */
			body.wp-admin #adminmenu li.toplevel_page_order100:hover div.wp-menu-image img,
			body.wp-admin #adminmenu li.toplevel_page_order100.wp-has-current-submenu div.wp-menu-image img,
			body.wp-admin #adminmenu li.toplevel_page_order100.wp-menu-open div.wp-menu-image img,
			body.wp-admin #adminmenu li.toplevel_page_order100.current div.wp-menu-image img {
				opacity: 1;
				filter: brightness(0) invert(1);
			}
			/* Pro / New badges in submenu */
			.o100-menu-badge {
				display: inline-block;
				padding: 0 6px;
				border-radius: 3px;
				font-size: 9px;
				font-weight: 600;
				line-height: 17px;
				vertical-align: middle;
				text-transform: uppercase;
				margin-left: 4px;
			}
			.o100-menu-badge--pro {
				background: linear-gradient(135deg, #1800AD, #F59322);
				color: #fff;
			}
			.o100-menu-badge--new {
				background: #06B6D4;
				color: #fff;
			}
			.o100-menu-badge--soon {
				background: #64748b;
				color: #fff;
			}

			/* ═══ Global Page Background (FluentCRM Style) ═══ */
			body.toplevel_page_order100, body.order100_page_o100-settings, body.order100_page_o100-loyalty, body.order100_page_o100-seo, body.order100_page_o100-notifications, body.order100_page_o100-reservations {
				background: #f3f4f6 !important;
			}

			/* ═══ Order100 Page Header Bar (Light) ═══ */
			.o100-page-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				background: #fff;
				padding: 0 28px;
				height: 72px;
				margin: -1px -1px 24px -20px; /* Add bottom margin to separate from content */
				border-bottom: 1px solid #e5e7eb;
				position: relative;
				z-index: 100;
			}
			.o100-page-header-left {
				display: flex;
				align-items: center;
				gap: 16px;
			}
			.o100-page-header-logo {
				object-fit: contain;
			}
			.o100-page-header-logo.o100-desktop-logo {
				width: auto;
				height: 32px;
				border-radius: 0;
			}
			.o100-page-header-logo.o100-mobile-logo {
				width: 36px;
				height: 36px;
				border-radius: 0;
				display: none;
			}
			@media (max-width: 960px) {
				.o100-page-header-logo.o100-desktop-logo { display: none !important; }
				.o100-page-header-logo.o100-mobile-logo { display: block !important; }
			}

			/* ═══ CMB2 Form Styling (FluentCRM White Card Style) ═══ */
			.o100-card-box {
				background: #fff;
				border: 1px solid #e5e7eb;
				border-radius: 8px;
				box-shadow: 0 1px 2px rgba(0,0,0,0.05);
				margin: 0 24px 40px;
				position: relative;
			}
			.o100-card-header {
				padding: 16px 32px;
				border-bottom: 1px solid #e5e7eb;
				background: #fdfdfd;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}
			.o100-card-header h2 {
				margin: 0;
				font-size: 16px;
				font-weight: 600;
				color: #111827;
			}
			.o100-wrap .cmb2-wrap {
				padding: 24px 32px;
			}
			.o100-wrap .cmb2-wrap > .form-table {
				width: 100%;
			}
			.o100-wrap .cmb-row {
				padding: 20px 0;
				border-bottom: 1px solid #f3f4f6;
			}
			.o100-wrap .cmb-row:last-child {
				border-bottom: none;
			}
			.o100-wrap .cmb-th {
				color: #374151;
				font-weight: 600;
				font-size: 14px;
			}
			.o100-wrap input[type="text"], .o100-wrap input[type="number"], .o100-wrap select, .o100-wrap textarea {
				border: 1px solid #d1d5db;
				border-radius: 6px;
				padding: 6px 12px;
				box-shadow: none;
			}
			.o100-wrap input[type="text"]:focus, .o100-wrap textarea:focus {
				border-color: #F59322;
				box-shadow: 0 0 0 1px #F59322;
			}
			.o100-wrap .cmb2-metabox-description {
				color: #6b7280;
				font-size: 13px;
				margin-top: 6px;
			}
			.o100-wrap .button-primary {
				background: #F59322 !important;
				border-color: #F59322 !important;
				border-radius: 6px !important;
				padding: 0 20px !important;
				height: 38px !important;
				line-height: 36px !important;
				font-weight: 600 !important;
			}
			.o100-wrap .button-primary:hover {
				background: #F59322 !important;
			}
			/* Reposition CMB2 native submit button to card header area.
			   Diagnostic confirmed: CMB2 outputs submit OUTSIDE the form tag,
			   as a direct child of .o100-card-body. */
			.o100-card-body > input[type="submit"],
			.o100-card-body > input[name="submit-cmb"] {
				position: absolute !important;
				top: 9px !important;
				right: 32px !important;
				z-index: 10;
				margin: 0 !important;
				background: #F59322 !important;
				color: #fff !important;
				border: none !important;
				border-radius: 6px !important;
				padding: 8px 24px !important;
				font-size: 14px !important;
				font-weight: 600 !important;
				cursor: pointer !important;
				height: 38px !important;
				line-height: 22px !important;
				box-shadow: none !important;
			}
			.o100-card-body > input[type="submit"]:hover {
				background: #F59322 !important;
			}
			/* ═══ FOUC Prevention: hide all CMB2 tab fields by default ═══ */
			.o100-wrap .o100-tab-field { display: none !important; }
			.o100-wrap .o100-tabs-nav { display: none !important; }
			.o100-wrap .o100-master-header { display: none !important; }
			.o100-page-header-title {
				font-size: 18px;
				font-weight: 700;
				color: #111827;
				letter-spacing: -0.2px;
				margin-left: 4px;
			}

			/* ═══ Global Tailwind Blue/Indigo → Brand Orange Override ═══ */
			/* Background colors */
			.o100-wrap .bg-blue-600,
			.o100-wrap .bg-indigo-600 { background-color: #F59322 !important; }
			.o100-wrap .bg-blue-700,
			.o100-wrap .bg-indigo-700 { background-color: #d97b06 !important; }
			.o100-wrap .bg-blue-500,
			.o100-wrap .bg-indigo-500 { background-color: #F59322 !important; }
			.o100-wrap .bg-blue-100,
			.o100-wrap .bg-indigo-100 { background-color: #fff7ed !important; }
			.o100-wrap .bg-blue-50,
			.o100-wrap .bg-indigo-50 { background-color: #fffaf5 !important; }
			/* Text colors */
			.o100-wrap .text-blue-600,
			.o100-wrap .text-indigo-600 { color: #F59322 !important; }
			.o100-wrap .text-blue-700,
			.o100-wrap .text-indigo-700 { color: #d97b06 !important; }
			.o100-wrap .text-blue-500,
			.o100-wrap .text-indigo-500 { color: #F59322 !important; }
			.o100-wrap .text-blue-800,
			.o100-wrap .text-indigo-800 { color: #9a5c06 !important; }
			/* Border colors */
			.o100-wrap .border-blue-600,
			.o100-wrap .border-indigo-600 { border-color: #F59322 !important; }
			.o100-wrap .border-blue-500,
			.o100-wrap .border-indigo-500 { border-color: #F59322 !important; }
			.o100-wrap .border-blue-300,
			.o100-wrap .border-indigo-300 { border-color: #fbb75c !important; }
			/* Hover states */
			.o100-wrap .hover\:bg-blue-700:hover,
			.o100-wrap .hover\:bg-indigo-700:hover { background-color: #d97b06 !important; }
			.o100-wrap .hover\:bg-blue-600:hover,
			.o100-wrap .hover\:bg-indigo-600:hover { background-color: #F59322 !important; }
			.o100-wrap .hover\:bg-blue-50:hover,
			.o100-wrap .hover\:bg-indigo-50:hover { background-color: #fff7ed !important; }
			.o100-wrap .hover\:text-blue-600:hover,
			.o100-wrap .hover\:text-indigo-600:hover { color: #F59322 !important; }
			.o100-wrap .hover\:text-blue-700:hover,
			.o100-wrap .hover\:text-indigo-700:hover { color: #d97b06 !important; }
			.o100-wrap .hover\:text-blue-800:hover,
			.o100-wrap .hover\:text-indigo-800:hover { color: #9a5c06 !important; }
			.o100-wrap .hover\:border-blue-500:hover,
			.o100-wrap .hover\:border-indigo-500:hover { border-color: #F59322 !important; }
			/* Focus states */
			.o100-wrap .focus\:ring-blue-500:focus,
			.o100-wrap .focus\:ring-indigo-500:focus { box-shadow: 0 0 0 2px rgba(245,147,34,0.4) !important; }
			.o100-wrap .focus\:border-blue-500:focus,
			.o100-wrap .focus\:border-indigo-500:focus { border-color: #F59322 !important; }
			.o100-wrap .ring-blue-500,
			.o100-wrap .ring-indigo-500 { --tw-ring-color: #F59322 !important; }
			/* ═══ Extreme Minimalist Input Groups (Matching Schedule Style) ═══ */
			.o100-flex-input-wrap {
				display: flex !important;
				align-items: stretch !important;
				width: 100%;
			}
			.o100-flex-input-wrap input.o100-modal-input {
				flex: 1 1 0% !important;
				min-width: 60px !important;
				margin: 0 !important;
			}
			.o100-flex-input-wrap .o100-flex-prefix,
			.o100-flex-input-wrap .o100-flex-suffix {
				align-items: center !important;
				justify-content: center !important;
				background: #f8fafc !important;
				border: 1px solid #cbd5e1 !important;
				padding: 0 15px !important;
				color: #64748b !important;
				font-size: 13px !important;
				font-weight: 500 !important;
				box-sizing: border-box !important;
			}
			/* With Prefix */
			.o100-flex-input-wrap.has-prefix input.o100-modal-input { border-radius: 0 6px 6px 0 !important; border-left: none !important; }
			.o100-flex-input-wrap.has-prefix .o100-flex-prefix { border-right: none !important; border-radius: 6px 0 0 6px !important; display: inline-flex !important; }
			.o100-flex-input-wrap.has-prefix .o100-flex-suffix { display: none !important; }
			/* With Suffix */
			.o100-flex-input-wrap.has-suffix input.o100-modal-input { border-radius: 6px 0 0 6px !important; border-right: none !important; }
			.o100-flex-input-wrap.has-suffix .o100-flex-suffix { border-left: none !important; border-radius: 0 6px 6px 0 !important; display: inline-flex !important; }
			.o100-flex-input-wrap.has-suffix .o100-flex-prefix { display: none !important; }
			/* Focus States */
			.o100-flex-input-wrap:focus-within input.o100-modal-input { box-shadow: none !important; border-color: #F59322 !important; }
			.o100-flex-input-wrap:focus-within .o100-flex-prefix, .o100-flex-input-wrap:focus-within .o100-flex-suffix { border-color: #F59322 !important; }
			.o100-flex-input-wrap:focus-within { box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15) !important; border-radius: 6px !important; }
			.o100-page-header-version {
				display: inline-block;
				background: #f3f4f6;
				color: #4b5563;
				font-size: 10px;
				font-weight: 600;
				padding: 3px 8px;
				border-radius: 4px;
				margin-left: 8px;
				vertical-align: middle;
				border: 1px solid #e5e7eb;
			}
			.o100-page-header-right {
				display: flex;
				align-items: center;
				gap: 0;
			}
			.o100-page-header-nav {
				display: flex;
				align-items: center;
				gap: 0;
				list-style: none;
				margin: 0;
				padding: 0;
			}
			.o100-page-header-nav a {
				display: block;
				height: 72px;
				line-height: 72px;
				padding: 0 18px;
				color: #4b5563;
				font-size: 14px;
				font-weight: 500;
				text-decoration: none;
				border: none !important; border-bottom: 2px solid transparent !important;
				transition: all 0.15s ease;
				white-space: nowrap;
				box-sizing: border-box;
			}
			.o100-page-header-nav a:hover {
				color: #1e293b;
			}
			.o100-page-header-nav a.o100-header-nav-active {
				color: #1e293b;
				border-bottom-color: #F59322;
				font-weight: 600;
			}
			.o100-page-header-sep {
				display: none; /* Removed separator to match FluentCRM cleanliness */
			}
			.o100-page-header-help {
				display: flex;
				align-items: center;
				justify-content: center;
				width: 32px;
				height: 32px;
				border-radius: 50%;
				border: 1px solid #cbd5e1 !important;
				color: #64748b;
				font-size: 14px;
				font-weight: 600;
				text-decoration: none;
				transition: all 0.15s ease;
				margin-left: 12px;
				cursor: pointer;
			}
			.o100-page-header-help:hover {
				border-color: #F59322;
				color: #F59322;
			}
			/* Responsive */
			@media (max-width: 1200px) {
				.o100-page-header-nav a {
					padding: 0 10px;
					font-size: 13px;
				}
			}
			@media (max-width: 960px) {
				.o100-page-header-nav {
					display: none;
				}
			}

			/* ═══ Save Button Spinner ═══ */
			@keyframes o100-spin {
				0% { transform: rotate(0deg); }
				100% { transform: rotate(360deg); }
			}
			.o100-saving::before {
				content: '';
				display: inline-block;
				width: 14px;
				height: 14px;
				border: 2px solid rgba(255,255,255,0.3);
				border-top-color: #fff;
				border-radius: 50%;
				animation: o100-spin 0.6s linear infinite;
				margin-right: 8px;
				vertical-align: middle;
			}

			/* ═══ Toast Notification ═══ */
			.o100-toast {
				position: fixed;
				top: 2rem;
				left: 50%;
				transform: translateX(-50%) translateY(-20px);
				background: rgba(15, 23, 42, 0.95) !important;
				backdrop-filter: blur(8px) !important;
				-webkit-backdrop-filter: blur(8px) !important;
				border: 1px solid rgba(255, 255, 255, 0.1) !important;
				color: white !important;
				padding: 12px 18px !important;
				border-radius: 10px !important;
				box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
				display: flex;
				align-items: center;
				gap: 12px;
				z-index: 999999;
				opacity: 0;
				pointer-events: none;
				transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
				min-width: 280px;
			}
			.o100-toast.o100-toast--visible {
				opacity: 1;
				transform: translateX(-50%) translateY(0);
				pointer-events: auto;
			}
			.o100-toast-icon {
				width: 24px;
				height: 24px;
				border-radius: 50%;
				background: rgba(16, 185, 129, 0.15) !important;
				color: #10b981 !important;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 14px;
				flex-shrink: 0;
				font-weight: bold;
			}
			.o100-toast-body {
				flex: 1;
			}
			.o100-toast-body h4 {
				margin: 0 !important;
				font-size: 14px !important;
				font-weight: 600 !important;
				color: #fff !important;
			}
			.o100-toast-body p {
				margin: 2px 0 0 !important;
				font-size: 13px !important;
				color: #94a3b8 !important;
			}
			.o100-toast-close {
				background: none;
				border: none;
				color: #94a3b8 !important;
				cursor: pointer;
				font-size: 18px;
				padding: 0 0 0 8px;
				line-height: 1;
				transition: color 0.15s ease;
			}
			.o100-toast-close:hover { color: #fff !important; }
			/* Hide all native bottom-left CMB2 save buttons globally */
			.cmb-form > input[type="submit"],
			.cmb-button-submit,
			.o100-card-body > input[name="submit-cmb"] {
				display: none !important;
			}

			/* Hide border when conditional children are collapsed */
			.cmb-row.o100-no-border { border-bottom: none !important; }

			/* ═══ FOUC Preventer ═══ */
			.o100-fluent-content, .o100-proxy-wrap, .o100-resv-page, .o100-customers-page-header, .o100-customers-content { 
				opacity: 0; 
				visibility: hidden; 
			}
			.o100-fouc-show, .o100-fluent-content.o100-fouc-show, .o100-proxy-wrap.o100-fouc-show, .o100-resv-page.o100-fouc-show, .o100-customers-page-header.o100-fouc-show, .o100-customers-content.o100-fouc-show { 
				opacity: 1 !important; 
				visibility: visible !important; 
				transition: opacity 0.3s ease !important; 
			}
			/* Protect WP Admin Menu from Tailwind Base CSS */
			#adminmenuback, #adminmenuwrap, #adminmenu, #adminmenu li, #adminmenu a, #adminmenu div {
				border: none !important;
				outline: none !important;
				box-shadow: none !important;
			}
			
			/* Kill WP Native focus outline on our SaaS UI */
			body.toplevel_page_order100 a:focus, body[class*="order100_page"] a:focus,
			body.toplevel_page_order100 button:focus, body[class*="order100_page"] button:focus,
			.o100-subtabs a:focus {
				box-shadow: none !important;
				outline: none !important;
			}
		</style>
		<script>
		// FOUC Preventer — show content immediately once DOM is ready
		document.addEventListener("DOMContentLoaded", function() {
			var targetSelectors = ['.o100-fluent-content', '.o100-proxy-wrap', '.o100-resv-page', '.o100-customers-page-header', '.o100-customers-content'];
			var els = document.querySelectorAll(targetSelectors.join(','));
			els.forEach(function(el) { el.classList.add('o100-fouc-show'); });
		});
		</script>

		<script>
		jQuery(function($) {
			// Handle save button interaction directly on click (handles buttons detached from form by malformed DOM)
			$('.o100-card-body > input[name="submit-cmb"], form#o100_options > input[type="submit"]').on('click', function(e) {
				e.preventDefault();

				var $btn = $(this);
				if ($btn.hasClass('o100-is-saving')) return false;

				var btnName = $btn.attr('name') || 'submit-cmb';
				// Capture original value before changing it
				var btnVal = $btn.data('orig-val') || $btn.attr('value') || 'Save';
				
				// Change text to indicate saving
				$btn.data('orig-val', btnVal).val('Saving...').css({'opacity': '0.7', 'pointer-events': 'none'}).addClass('o100-is-saving');
				
				// Find the actual form
				var $form = $('form#o100_options');
				if ($form.length === 0) $form = $('.cmb-form');

				if ($form.length > 0) {
					// Inject a hidden field so CMB2 knows it was a valid save action
					$('<input>').attr({
						type: 'hidden',
						name: btnName,
						value: btnVal
					}).appendTo($form);
					
					// Programmatically submit the form
					$form.submit();
				}
			});

			var $topSaveBtn = $('.o100-fluent-top-save');
			var $fluentForm = $('.o100-fluent-content form');

			// ═══ Snapshot-based dirty checking ═══
			// Serialize ALL named inputs on the settings page, not just specific containers.
			// Compare at both event-time (for save button) and beforeunload-time (for leave warning).
			// Exposed on window so other scripts (Portal Builder, etc.) can reset snapshot after saving.
			window.o100FormSnapshot = '';
			window.o100SnapshotReady = false;

			window.o100SerializePage = function() {
				var parts = [];
				$('#wpbody-content').find('input[name], select[name], textarea[name]').not('[type="button"], [type="submit"], [type="reset"], [name="security"], [name="_wp_http_referer"], [name="_wpnonce"], [name="submit-cmb"], [name="action"], [name="option_page"], [name^="o100_portal_nonce"]')
				.filter(function() {
					// Exclude fields inside Email and Reports subtabs to prevent React / dynamic mounts from breaking dirty checking
					var $parentTab = $(this).closest('.o100-notify-subtab-content');
					if ($parentTab.length) {
						var tab = $parentTab.data('subtab');
						if (tab === 'email' || tab === 'reports') {
							return false;
						}
					}
					return true;
				}).each(function() {
					var $el = $(this);
					var name = $el.attr('name');
					if (!name) return;
					if ($el.is(':radio')) {
						// Only serialize the CHECKED radio — use its value attribute
						if ($el.is(':checked')) {
							parts.push(name + '=' + ($el.val() || ''));
						}
					} else if ($el.is(':checkbox')) {
						parts.push(name + '=' + ($el.is(':checked') ? '1' : '0'));
					} else {
						parts.push(name + '=' + ($el.val() || ''));
					}
				});
				return parts.sort().join('&');
			};

			function o100IsDirtyNow() {
				if (!window.o100SnapshotReady) return false;
				return window.o100SerializePage() !== window.o100FormSnapshot;
			}

			function o100UpdateSaveBtn() {
				if (!window.o100SnapshotReady) return;
				if ($topSaveBtn.length) {
					$topSaveBtn.toggleClass('o100-save-disabled', !o100IsDirtyNow());
				}
			}

			// Stabilization: poll every 500ms until two consecutive reads match
			var o100StabAttempt = 0;
			var o100StabLast = '';
			var o100StabTimer = setInterval(function() {
				var current = window.o100SerializePage();
				o100StabAttempt++;
				if (current === o100StabLast && current !== '') {
					clearInterval(o100StabTimer);
					window.o100FormSnapshot = current;
					window.o100SnapshotReady = true;
					// Listen for user changes to update Save button state
					$('#wpbody-content').on('input change', 'input, select, textarea', function() {
						o100UpdateSaveBtn();
					});
				} else {
					o100StabLast = current;
				}
				if (o100StabAttempt >= 20 && !window.o100SnapshotReady) {
					clearInterval(o100StabTimer);
					window.o100FormSnapshot = current;
					window.o100SnapshotReady = true;
					$('#wpbody-content').on('input change', 'input, select, textarea', function() {
						o100UpdateSaveBtn();
					});
				}
			}, 500);

			// Disable save button initially
			if ($topSaveBtn.length && $fluentForm.length) {
				$topSaveBtn.addClass('o100-save-disabled');
			}

			// ═══ Suppress CMB2 / WP native beforeunload ═══
			var o100BeforeUnloadHandler = function() {
				if (o100IsDirtyNow()) {
					return 'You have unsaved changes. Are you sure you want to leave?';
				}
			};
			var o100CleanupCount = 0;
			var o100CleanupTimer = setInterval(function() {
				if (window.onbeforeunload && window.onbeforeunload !== o100BeforeUnloadHandler) {
					window.onbeforeunload = null;
				}
				$(window).off('beforeunload');
				window.onbeforeunload = o100BeforeUnloadHandler;
				o100CleanupCount++;
				if (o100CleanupCount >= 10) {
					clearInterval(o100CleanupTimer);
				}
			}, 500);

			// Sticky header shadow
			var $header = $('.o100-fluent-header');
			if ($header.length) {
				$(window).on('scroll', function() {
					if ($header[0].getBoundingClientRect().top <= 33) {
						$header.addClass('is-stuck');
					} else {
						$header.removeClass('is-stuck');
					}
				});
				$(window).trigger('scroll');
			}

			// ═══ CMB2 Conditional Field Visibility ═══
			function o100ApplyConditionals() {
				$('[data-conditional-id]').each(function() {
					var $el = $(this);
					var condId = $el.data('conditional-id');
					var condVal = String($el.data('conditional-value') || 'on');
					
					var $group = $el.closest('.cmb-repeatable-grouping');
					var $control;
					if ($group.length && !$el.hasClass('cmb-repeatable-group')) {
						// Inside a group, match array-based name suffix: something[0][condId]
						$control = $group.find('[name="' + condId + '"], [name$="[' + condId + ']"]');
					} else {
						$control = $('[name="' + condId + '"]');
					}
					
					if (!$control.length) return;
					
					var isVisible = false;
					if ($control.is(':checkbox')) {
						isVisible = $control.is(':checked');
					} else if ($control.is(':radio')) {
						isVisible = $control.filter(':checked').val() === condVal;
					} else {
						isVisible = String($control.val()) === condVal;
					}
					
					var $target;
					if ($el.hasClass('cmb-repeatable-group')) {
						$target = $el;
					} else {
						$target = $el.closest('.cmb-row');
					}
					if ($target.length) {
						$target.toggle(isVisible);
					}
				});
				
				// Hide divider on toggle rows when children below are hidden
				$('.o100-settings-group-content').each(function() {
					$(this).find('.cmb-type-checkbox').each(function() {
						var $checkRow = $(this);
						var hasVisibleAfter = $checkRow.nextAll('.cmb-row:visible, .cmb-repeatable-group:visible').length > 0;
						$checkRow.toggleClass('o100-no-border', !hasVisibleAfter);
					});
				});
			}
			
			// CMB2 doesn't apply attributes to group wrappers — inject manually
			$('.cmb2-id-o100-global-date-rules, #o100_global_date_rules_repeat').attr({'data-conditional-id': 'o100_menu_date', 'data-conditional-value': 'on'});
			
			o100ApplyConditionals();
			
			// Initialize Flatpickr for date rules
			function o100InitFlatpickr() {
				if (typeof flatpickr !== 'undefined') {
					$('.o100-flatpickr-multi').each(function() {
						// Prevent double initialization
						if (!this._flatpickr) {
							flatpickr(this, {
								mode: 'multiple',
								dateFormat: 'Y-m-d',
								placeholder: 'Select dates...'
							});
						}
					});
				}
			}
			o100InitFlatpickr();
			
			// Event listeners
			$('#wpbody-content').on('change', 'input[type="checkbox"], input[type="radio"], select', function() {
				o100ApplyConditionals();
			});
			
			// Handle new CMB2 rows (repeatable groups)
			$('#wpbody-content').on('cmb2_add_row', function(e, newRow) {
				setTimeout(function() {
					o100InitFlatpickr();
					o100ApplyConditionals();
				}, 100);
			});

			// Fluent Settings top save button (General Settings page tabs)
			$topSaveBtn.on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				if ($btn.hasClass('o100-save-disabled') || $btn.hasClass('o100-saving')) return;

				var $customForm = $('#o100-delivery-form, #o100-pickup-form, #o100-misc-form');

				// 0. Portal Builder has its own save handler — skip
				if ($('#o100-portal-form').length) return;
				
				// 1. Custom Ajax Forms (Delivery / Pickup / Misc)
				if ($customForm.length > 0) {
					var formId = $customForm.attr('id');
					var actionName = '';
					var dataKey = '';
					
					if (formId === 'o100-delivery-form') { actionName = 'o100_save_delivery'; dataKey = 'o100_delivery'; }
					else if (formId === 'o100-pickup-form') { actionName = 'o100_save_pickup'; dataKey = 'o100_pickup'; }
					else if (formId === 'o100-misc-form') { actionName = 'o100_save_misc'; dataKey = 'o100_misc'; }

					if (actionName) {
						// Visual feedback
						$btn.addClass('o100-saving').text('Saving...').css('opacity', '0.7');

						var rawData = $customForm.serializeArray();
						var postData = { action: actionName };
						postData[dataKey] = {};
						
						// Re-map flat names to nested array format expected by PHP
						$.each(rawData, function(i, field) {
						var arrayMatch = field.name.match(/^([^\[]+)\[([^\]]+)\]\[([^\]]+)\]$/);
						if (arrayMatch) {
							var parent = arrayMatch[1];
							var index = arrayMatch[2];
							var child = arrayMatch[3];
							if (!postData[dataKey][parent]) postData[dataKey][parent] = {};
							if (!postData[dataKey][parent][index]) postData[dataKey][parent][index] = {};
							postData[dataKey][parent][index][child] = field.value;
						} else {
							var multiMatch = field.name.match(/^([^\[]+)\[\]$/);
							if (multiMatch) {
								var keyName = multiMatch[1];
								if (!postData[dataKey][keyName]) postData[dataKey][keyName] = [];
								postData[dataKey][keyName].push(field.value);
							} else {
								postData[dataKey][field.name] = field.value;
							}
						}
					});

					// Manually inject values for inputs that were teleported outside the form
					if (formId === 'o100-delivery-form' && $('#o100_enable_delivery').is(':checked')) {
						postData[dataKey]['o100_enable_delivery'] = 'on';
					}
					if (formId === 'o100-pickup-form' && $('#o100_enable_pickup').is(':checked')) {
						postData[dataKey]['o100_enable_pickup'] = 'on';
					}
					if (formId === 'o100-misc-form') {
						postData['security'] = $('#o100-misc-form input[name="security"]').val();
					}

					$.ajax({
						url: o100Settings.ajaxurl,
						method: 'POST',
						data: postData,
						success: function(response) {
							if(response.success) {
								// Reset snapshot to current state (no longer dirty)
								window.o100FormSnapshot = window.o100SerializePage();
								$btn.removeClass('o100-saving').addClass('o100-save-disabled').text('Save Settings').css('opacity', '1');

								// Show toast inline instead of reloading
								var $toast = $('<div class="o100-toast">' +
									'<div class="o100-toast-icon">✓</div>' +
									'<div class="o100-toast-body"><h4>Great!</h4><p>Settings Updated.</p></div>' +
									'<button class="o100-toast-close" type="button">×</button>' +
								'</div>');
								$('body').append($toast);
								setTimeout(function() { $toast.addClass('o100-toast--visible'); }, 50);
								setTimeout(function() {
									$toast.removeClass('o100-toast--visible');
									setTimeout(function() { $toast.remove(); }, 300);
								}, 4000);
								$toast.find('.o100-toast-close').on('click', function() {
									$toast.removeClass('o100-toast--visible');
									setTimeout(function() { $toast.remove(); }, 300);
								});
							} else {
								alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown'));
								$btn.removeClass('o100-saving').text('Save Settings').css('opacity', '1');
							}
						},
						error: function() {
							alert('Server Error.');
							$btn.removeClass('o100-saving').text('Save Settings').css('opacity', '1');
						}
					});
					
					return; // Stop here, do not run native CMB2 submit
					}
				}
				
				// 2. Native CMB2 Form (Fallback for other tabs like Appearance, General, etc.)
				var $form = $('.o100-fluent-content form.cmb-form');
				if ($form.length === 0) return;
				
				// Visual feedback
				$btn.addClass('o100-saving').text('Saving...').css('opacity', '0.7');
				
				// AJAX Save for Notifications page to prevent page reload
				if ($form.attr('id') === 'o100_notifications') {
					var formData = $form.serialize();
					if (formData.indexOf('submit-cmb=') === -1) {
						formData += '&submit-cmb=1';
					}
					$.ajax({
						url: $form.attr('action') || window.location.href,
						method: 'POST',
						data: formData,
						success: function() {
							// Reset dirty snapshot to current state
							window.o100FormSnapshot = window.o100SerializePage();
							$btn.removeClass('o100-saving').addClass('o100-save-disabled').text('Save Settings').css('opacity', '1');
							
							// Trigger success toast dynamically
							var $toast = $('<div class="o100-toast">' +
								'<div class="o100-toast-icon">✓</div>' +
								'<div class="o100-toast-body"><h4>Great!</h4><p>Settings Updated.</p></div>' +
								'<button class="o100-toast-close" type="button">×</button>' +
							'</div>');
							$('body').append($toast);
							setTimeout(function() { $toast.addClass('o100-toast--visible'); }, 50);
							setTimeout(function() {
								$toast.removeClass('o100-toast--visible');
								setTimeout(function() { $toast.remove(); }, 300);
							}, 4000);
							$toast.find('.o100-toast-close').on('click', function() {
								$toast.removeClass('o100-toast--visible');
								setTimeout(function() { $toast.remove(); }, 300);
							});
						},
						error: function() {
							alert('Error saving settings.');
							$btn.removeClass('o100-saving').text('Save Settings').css('opacity', '1');
						}
					});
					return;
				}
				
				// Set flag for toast notification after page reload for other tabs
				sessionStorage.setItem('o100_settings_saved', '1');
				
				// Clear dirty state so the "Leave page?" dialog doesn't fire during submit
				window.onbeforeunload = null;
				
				// CMB2 requires submit-cmb in POST data to trigger save.
				// The hidden field in form_format already provides this.
				// Use HTMLFormElement.prototype.submit to bypass any naming collisions
				// (if a form element is named 'submit', it shadows the native function).
				HTMLFormElement.prototype.submit.call($form[0]);
				
				// Safety net: if submit somehow fails, restore button after 5s
				setTimeout(function() {
					if ($btn.hasClass('o100-saving')) {
						$btn.removeClass('o100-saving').text('Save Settings').css('opacity', '1');
					}
				}, 5000);
			});

			// Check for save flag on page load
			if (sessionStorage.getItem('o100_settings_saved')) {
				sessionStorage.removeItem('o100_settings_saved');

				var $toast = $('<div class="o100-toast">' +
					'<div class="o100-toast-icon">✓</div>' +
					'<div class="o100-toast-body"><h4>Great!</h4><p>Settings Updated.</p></div>' +
					'<button class="o100-toast-close" type="button">×</button>' +
				'</div>');
				$('body').append($toast);

				// Trigger animation
				setTimeout(function() { $toast.addClass('o100-toast--visible'); }, 50);
				
				// Auto hide
				setTimeout(function() {
					$toast.removeClass('o100-toast--visible');
					setTimeout(function() { $toast.remove(); }, 300);
				}, 4000);

				// Manual close
				$toast.find('.o100-toast-close').on('click', function() {
					$toast.removeClass('o100-toast--visible');
					setTimeout(function() { $toast.remove(); }, 300);
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Render the persistent page header bar (logo + name + version + nav + help)
	 */
	public static function render_page_header() {
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

		$nav_items = array(
			'order100'           => __( 'Dashboard', 'order100' ),
			'o100-settings'      => __( 'Settings', 'order100' ),
			'o100-menu-maker'    => __( 'Menu Management', 'order100' ),
			'o100-customers'     => __( 'Customers', 'order100' ),
			'o100-promotions'    => __( 'Promotions', 'order100' ),
			'o100-loyalty'       => __( 'Loyalty', 'order100' ),
			'o100-notifications' => __( 'Notifications', 'order100' ),
			'o100-automation'    => __( 'Automations', 'order100' ),
		);
		?>
		<!-- Hidden H1 to trap WordPress admin notices before our flex layout -->
		<h1 style="display:none; margin:0; padding:0;">Order100</h1>
		<div class="o100-page-header">
			<div class="o100-page-header-left">
				<img src="<?php echo esc_url( O100_URL . 'assets/logo/order100-logo-full.png' ); ?>" alt="Order100" class="o100-page-header-logo o100-desktop-logo">
				<img src="<?php echo esc_url( O100_URL . 'assets/logo/order100-logo-icon.png' ); ?>" alt="Order100" class="o100-page-header-logo o100-mobile-logo">
				<span class="o100-page-header-version">v<?php echo esc_html( O100_VERSION ); ?></span>
			</div>
			<div class="o100-page-header-right">
				<nav class="o100-page-header-nav">
					<?php foreach ( $nav_items as $slug => $label ) :
						$active = ( $current_page === $slug ) ? ' o100-header-nav-active' : '';
						// Use inline style to absolutely suppress any FOUC border artifacts before CSS loads
						$inline_style = 'border-top: none !important; border-left: none !important; border-right: none !important; outline: none !important; box-shadow: none !important;';
					?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="<?php echo esc_attr( $active ); ?>" style="<?php echo esc_attr( $inline_style ); ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
				<div class="o100-page-header-sep"></div>
				<?php if ( ! function_exists('O100_License') || ! O100_License()->is_premium() ) : ?>
			<a href="#" onclick="if(typeof o100ShowProModal !== 'undefined'){o100ShowProModal('Order100 Pro', 'Unlock limitless marketing possibilities. Upgrade now to exceed your limits and access valuable tools that fuel your business.');}else{alert('Pro features are locked.');} return false;" class="o100-page-header-upgrade" style="display:flex; align-items:center; justify-content:center; padding:0 16px; height:32px; border-radius:16px; background:#0f172a; color:#F59322; text-decoration:none; margin-right:16px; font-weight:700; font-size:13px; transition:all 0.2s; border:1px solid #0f172a; box-shadow:0 2px 4px rgba(15,23,42,0.15);" onmouseover="this.style.background='#F59322'; this.style.color='#0f172a'; this.style.borderColor='#F59322';" onmouseout="this.style.background='#0f172a'; this.style.color='#F59322'; this.style.borderColor='#0f172a';">
					<span style="margin-right:6px; font-size:14px;">✨</span> Upgrade
				</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=o100-tools' ) ); ?>" class="o100-page-header-diag" title="<?php esc_attr_e( 'Tools', 'order100' ); ?>" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#f1f5f9; color:#64748b; text-decoration:none; margin-right:8px; transition:all 0.2s; border:none !important; outline:none !important; box-shadow:none !important;">
					<span class="dashicons dashicons-admin-tools" style="font-size:18px; width:18px; height:18px;"></span>
				</a>
				<a href="#" class="o100-page-header-help" title="<?php esc_attr_e( 'Help & Support', 'order100' ); ?>" style="border:none !important; outline:none !important; box-shadow:none !important;">?</a>
			</div>
		</div>
		<?php
	}

	// ═══════════════════════════════════════════════════════════════════
	// Page Render Callbacks
	// ═══════════════════════════════════════════════════════════════════

	/**
	 * Dashboard page
	 */
	public function render_dashboard() {
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<?php 
				if ( class_exists( 'O100_Dashboard_Admin' ) ) {
					O100_Dashboard_Admin::instance()->render();
				} else {
					echo '<div class="o100-dashboard-header">';
					echo '<h1>' . esc_html__( 'Order100', 'order100' ) . ' <span class="o100-version">v' . esc_html( O100_VERSION ) . '</span></h1>';
					echo '<p>' . esc_html__( 'All-in-one WooCommerce solution for Discounts, Loyalty, SEO, Email, and more.', 'order100' ) . '</p>';
					echo '</div>';
					echo '<div class="o100-dashboard-grid">';
					$this->render_dashboard_cards();
					echo '</div>';
				}
			?>
		</div>
		<?php
	}

	/**
	 * Dashboard feature cards
	 */
	private function render_dashboard_cards() {
		$cards = array(
			array(
				'title'  => __( 'General Settings', 'order100' ),
				'desc'   => __( 'Time schedule, checkout, navigation settings', 'order100' ),
				'icon'   => 'dashicons-admin-generic',
				'link'   => admin_url( 'admin.php?page=o100-settings' ),
				'status' => 'active',
			),
			array(
				'title'  => __( 'Loyalty', 'order100' ),
				'desc'   => __( 'Points, tiers, punch cards, and customer rewards', 'order100' ),
				'icon'   => 'dashicons-star-filled',
				'link'   => admin_url( 'admin.php?page=o100-loyalty' ),
				'status' => 'active',
			),
			array(
				'title'  => __( 'Notifications', 'order100' ),
				'desc'   => __( 'Email templates and SMS notification settings', 'order100' ),
				'icon'   => 'dashicons-email-alt',
				'link'   => admin_url( 'admin.php?page=o100-notifications' ),
				'status' => 'active',
			),
			array(
				'title'  => __( 'Tools', 'order100' ),
				'desc'   => __( 'SEO engine, health check, and delivery simulator', 'order100' ),
				'icon'   => 'dashicons-admin-tools',
				'link'   => admin_url( 'admin.php?page=o100-tools' ),
				'status' => 'active',
			),
			array(
				'title'  => __( 'Customers (CRM)', 'order100' ),
				'desc'   => __( 'Customer management, segments, and analytics', 'order100' ),
				'icon'   => 'dashicons-groups',
				'link'   => admin_url( 'admin.php?page=o100-customers' ),
				'status' => 'pro',
			),
			array(
				'title'  => __( 'Automation', 'order100' ),
				'desc'   => __( 'Automated workflows and triggers', 'order100' ),
				'icon'   => 'dashicons-controls-repeat',
				'link'   => admin_url( 'admin.php?page=o100-automation' ),
				'status' => 'pro',
			),
		);

		foreach ( $cards as $card ) {
			$badge = '';
			if ( $card['status'] === 'pro' ) {
				$badge = '<span class="o100-card-badge o100-card-badge--pro">Pro</span>';
			}
			printf(
				'<a href="%s" class="o100-dashboard-card">
					<div class="o100-card-icon"><span class="dashicons %s"></span></div>
					<div class="o100-card-content">
						<h3>%s %s</h3>
						<p>%s</p>
					</div>
				</a>',
				esc_url( $card['link'] ),
				esc_attr( $card['icon'] ),
				esc_html( $card['title'] ),
				$badge,
				esc_html( $card['desc'] )
			);
		}
	}

	/**
	 * General Settings page — renders old CMB2 form with time/checkout/nav tabs visible
	 */
	public function render_general_settings() {
		include O100_PATH . 'core/views/view-settings-main.php';
	}
	public function render_customers() {
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<?php O100_Customers_Admin::render_page(); ?>
		</div>
		<?php
	}

	/**
	 * Reservations page
	 */
	public function render_reservations() {
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<?php 
			if ( class_exists( 'O100_Reservation_Admin' ) ) {
				O100_Reservation_Admin::render_page();
			} else {
				echo '<p>' . esc_html__( 'Reservation module not loaded.', 'order100' ) . '</p>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Promotions page
	 */
	public function render_promotions() {
		// Load the Promotions Admin class
		if ( ! class_exists( 'O100_Promotions_Proxy_Admin' ) ) {
			// Actually the loader should have loaded it. Just in case:
			require_once O100_PATH . 'core/promotions/class-o100-promotions-loader.php';
			O100_Promotions_Loader::init();
		}
		
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<?php O100_Promotions_Proxy_Admin::render_page(); ?>
		</div>
		<?php
	}

	/**
	 * Loyalty page — shows loyalty tab from CMB2 form
	 */
	public function render_loyalty() {
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<?php O100_Loyalty_Proxy_Admin::render_page(); ?>
		</div>
		<?php
	}

	/**
	 * Automation page
	 */
	public function render_automation() {
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<?php 
			if ( class_exists( 'O100_Automation_Admin' ) ) {
				O100_Automation_Admin::render_page(); 
			}
			?>
		</div>
		<?php
	}

	/**
	 * Launcher page — directly renders the launcher addon's React config UI.
	 * Based on the old v3.5.6 advanced.php approach: PHP-rendered, no iframe.
	 */
	public function render_launcher() {
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<div class="o100-launcher-container" style="margin-top:20px;">
				<?php
				if ( class_exists( '\WLL\App\Controller\Common' ) ) {
					// Launcher scripts auto-enqueued via admin_enqueue_scripts hook (Router.php)
					\WLL\App\Controller\Common::displayMenuContent();
				} else {
					echo '<p>' . esc_html__( 'Launcher module is not available.', 'order100' ) . '</p>';
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * SEO page — kept for backward compat redirect
	 */
	public function render_seo() {
		wp_safe_redirect( admin_url( 'admin.php?page=o100-tools&tab=seo' ) );
		exit;
	}

	/**
	 * Tools page — SEO, Health Check, Delivery Simulator
	 */
	public function render_tools() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'seo';
		$tabs = array(
			'seo'              => array( 'label' => __( 'SEO Engine', 'order100' ), 'icon' => 'dashicons-search' ),
			'health_check'     => array( 'label' => __( 'Health Check', 'order100' ), 'icon' => 'dashicons-heart' ),
			'config_check'     => array( 'label' => __( 'Config Check', 'order100' ), 'icon' => 'dashicons-clipboard' ),
			'delivery_sim'     => array( 'label' => __( 'Delivery Simulator', 'order100' ), 'icon' => 'dashicons-car' ),
			'backup_migration' => array( 'label' => __( 'Backup & Migration', 'order100' ), 'icon' => 'dashicons-database' ),
		);

		// Fallback if tab doesn't exist
		if ( ! array_key_exists( $active_tab, $tabs ) ) {
			$active_tab = 'seo';
		}
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			
			<div class="o100-fluent-container">
				<!-- Fluent Sidebar -->
				<div class="o100-fluent-sidebar">
					<ul class="o100-fluent-nav">
						<?php foreach ( $tabs as $slug => $tab ) : ?>
							<li>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=o100-tools&tab=' . $slug ) ); ?>"
								   class="<?php echo $active_tab === $slug ? 'active' : ''; ?>">
									<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
									<span class="o100-nav-text"><?php echo esc_html( $tab['label'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				
				<!-- Fluent Content -->
				<div class="o100-fluent-content">
					<div class="o100-fluent-header">
						<h2><?php echo esc_html( $tabs[ $active_tab ]['label'] ); ?></h2>
						<?php if ( $active_tab === 'seo' ) : ?>
							<div class="o100-fluent-header-actions" style="display:flex; align-items:center; gap:16px;">
								<button type="button" class="o100-fluent-top-save">
									<?php esc_html_e( 'Save Settings', 'order100' ); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
					<div class="o100-fluent-form-wrapper">
						<?php
						if ( $active_tab === 'seo' ) {
							// Use custom form_format to guarantee submit-cmb is always in POST data.
							$form_format = '<form class="cmb-form" method="post" id="%1$s" enctype="multipart/form-data" encoding="multipart/form-data"><input type="hidden" name="object_id" value="%2$s"><input type="hidden" name="submit-cmb" value="1">%3$s<div class="cmb-submit-wrap" style="display:none;">%4$s</div></form>';
							cmb2_metabox_form( 'o100_seo', 'o100_seo', array( 
								'save_button' => __( 'Save Settings', 'order100' ),
								'form_format' => $form_format
							) );
						} elseif ( $active_tab === 'health_check' ) {
							$this->render_tools_health_check();
						} elseif ( $active_tab === 'delivery_sim' ) {
							$this->render_tools_delivery_sim();
						} elseif ( $active_tab === 'config_check' ) {
							$view_file = O100_PATH . 'core/tools/views/view-config-check.php';
							if ( file_exists( $view_file ) ) {
								include $view_file;
							} else {
								echo '<p>' . esc_html__( 'Config Check view not found.', 'order100' ) . '</p>';
							}
						} elseif ( $active_tab === 'backup_migration' ) {
							$view_file = O100_PATH . 'core/tools/backup-migration/views/view-backup-migration.php';
							if ( file_exists( $view_file ) ) {
								include $view_file;
							} else {
								echo '<p>' . esc_html__( 'Backup & Migration module not found.', 'order100' ) . '</p>';
							}
						}
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Tools > Health Check tab content
	 */
	private function render_tools_health_check() {
		?>
		<div class="o100-settings-group-card">
			<div class="o100-settings-group-title">
				<h3><?php esc_html_e( 'System Health', 'order100' ); ?></h3>
				<p><?php esc_html_e( 'Run a system health check to ensure all dependencies and environment variables are configured correctly.', 'order100' ); ?></p>
			</div>
			<div class="o100-settings-group-content">
				<button type="button" id="o100-btn-run-health-inline" class="button button-primary button-large" style="margin-bottom:20px;"><?php esc_html_e( 'Run Diagnosis', 'order100' ); ?></button>
				<div id="o100-health-results-inline"></div>
			</div>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('#o100-btn-run-health-inline').on('click', function() {
				var $btn = $(this), $res = $('#o100-health-results-inline');
				$btn.text('Running...').prop('disabled', true);
				$res.html('<div style="text-align:center; padding:20px;"><span class="spinner is-active" style="float:none;"></span></div>');
				$.post(ajaxurl, {
					action: 'o100_run_health_check',
					nonce: '<?php echo wp_create_nonce("o100_diag_nonce"); ?>'
				}, function(res) {
					$btn.text('<?php echo esc_js( __( 'Run Diagnosis', 'order100' ) ); ?>').prop('disabled', false);
					if (res.success) {
						var html = '<div style="display:grid; gap:10px;">';
						$.each(res.data, function(i, item) {
							var borderColor = item.status === 'ok' ? '#10b981' : (item.status === 'warning' ? '#f59e0b' : '#e11d48');
							var icon = item.status === 'ok' ? '✓' : (item.status === 'warning' ? '⚠' : '✗');
							var actionHtml = (item.action && item.status !== 'ok') ? '<a href="'+item.action+'" style="font-size:12px; color:#F59322; margin-top:4px; display:inline-block;">Fix Issue →</a>' : '';
							html += '<div style="padding:14px 18px; border:1px solid #e2e8f0; border-left:4px solid '+borderColor+'; border-radius:8px; display:flex; justify-content:space-between; align-items:center;'+(item.status==='error'?' background:#fff1f2;':'')+'">';
							html += '<div><div style="font-weight:600; color:#0f172a; font-size:14px;">'+item.label+'</div><div style="color:#64748b; font-size:13px; margin-top:2px;">'+item.msg+'</div>'+actionHtml+'</div>';
							html += '<span style="font-size:18px; color:'+borderColor+';">'+icon+'</span>';
							html += '</div>';
						});
						html += '</div>';
						$res.html(html);
					} else {
						$res.html('<p style="color:red;">Error running check.</p>');
					}
				}).fail(function() {
					$btn.text('<?php echo esc_js( __( 'Run Diagnosis', 'order100' ) ); ?>').prop('disabled', false);
					$res.html('<p style="color:red;">Request failed. Please try again.</p>');
				});
			});
		});
		</script>
		<?php
	}
	/**
	 * AJAX handler: Run system health check
	 */
	public function ajax_run_health_check() {
		check_ajax_referer( 'o100_diag_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$checks = array();

		// 1. PHP Version
		$php_ver = phpversion();
		$checks[] = array(
			'label'  => 'PHP Version',
			'msg'    => $php_ver,
			'status' => version_compare( $php_ver, '7.4', '>=' ) ? 'ok' : 'error',
		);

		// 2. WordPress Version
		global $wp_version;
		$checks[] = array(
			'label'  => 'WordPress Version',
			'msg'    => $wp_version,
			'status' => version_compare( $wp_version, '5.8', '>=' ) ? 'ok' : 'warning',
		);

		// 3. WooCommerce
		if ( class_exists( 'WooCommerce' ) ) {
			$checks[] = array(
				'label'  => 'WooCommerce',
				'msg'    => 'Active (v' . WC()->version . ')',
				'status' => 'ok',
			);
		} else {
			$checks[] = array(
				'label'  => 'WooCommerce',
				'msg'    => 'Not active — Order100 requires WooCommerce.',
				'status' => 'error',
				'action' => admin_url( 'plugins.php' ),
			);
		}

		// 4. Memory Limit
		$mem = ini_get( 'memory_limit' );
		$mem_bytes = wp_convert_hr_to_bytes( $mem );
		$checks[] = array(
			'label'  => 'PHP Memory Limit',
			'msg'    => $mem,
			'status' => $mem_bytes >= 128 * 1024 * 1024 ? 'ok' : ( $mem_bytes >= 64 * 1024 * 1024 ? 'warning' : 'error' ),
		);

		// 5. cURL
		$checks[] = array(
			'label'  => 'cURL Extension',
			'msg'    => function_exists( 'curl_version' ) ? 'Installed (v' . curl_version()['version'] . ')' : 'Missing',
			'status' => function_exists( 'curl_version' ) ? 'ok' : 'error',
		);

		// 6. Timezone
		$tz = wp_timezone_string();
		$checks[] = array(
			'label'  => 'Timezone',
			'msg'    => $tz ?: 'Not set',
			'status' => ! empty( $tz ) && $tz !== 'UTC+0' ? 'ok' : 'warning',
		);

		// 7. WP-Cron
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$checks[] = array(
			'label'  => 'WP-Cron',
			'msg'    => $cron_disabled ? 'Disabled (DISABLE_WP_CRON is true)' : 'Enabled',
			'status' => $cron_disabled ? 'warning' : 'ok',
		);

		// 8. Uploads Directory
		$upload_dir = wp_upload_dir();
		$writable = ! empty( $upload_dir['basedir'] ) && wp_is_writable( $upload_dir['basedir'] );
		$checks[] = array(
			'label'  => 'Uploads Directory',
			'msg'    => $writable ? 'Writable' : 'Not writable — file uploads will fail.',
			'status' => $writable ? 'ok' : 'error',
		);

		// 9. Order100 Database Tables (module-aware)
		global $wpdb;
		$table_checks = array(
			array( 'table' => $wpdb->prefix . 'o100_promotions',           'module' => 'Promotions',    'class' => 'O100_Promotions_DB' ),
			array( 'table' => $wpdb->prefix . 'o100_customers',            'module' => 'CRM',           'class' => 'O100_Customers_DB' ),
			array( 'table' => $wpdb->prefix . 'o100_loyalty_points',       'module' => 'Loyalty',       'class' => 'O100_Loyalty_DB' ),
			array( 'table' => $wpdb->prefix . 'o100_loyalty_transactions', 'module' => 'Loyalty',       'class' => 'O100_Loyalty_DB' ),
			array( 'table' => $wpdb->prefix . 'o100_automations',          'module' => 'Automations',   'class' => 'O100_Automation_DB' ),
			array( 'table' => $wpdb->prefix . 'o100_notification_log',     'module' => 'Notifications', 'class' => 'O100_Notification_Log' ),
		);
		$missing  = array();
		$skipped  = array();
		$checked  = 0;
		foreach ( $table_checks as $tc ) {
			if ( ! class_exists( $tc['class'] ) ) {
				$skipped[] = $tc['module'];
				continue;
			}
			$checked++;
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tc['table'] ) ) !== $tc['table'] ) {
				$missing[] = str_replace( $wpdb->prefix, '', $tc['table'] );
			}
		}
		$skipped = array_unique( $skipped );
		$msg = '';
		if ( empty( $missing ) ) {
			$msg = $checked . ' active module tables OK.';
		} else {
			$msg = 'Missing: ' . implode( ', ', $missing ) . '.';
		}
		if ( ! empty( $skipped ) ) {
			$msg .= ' Skipped (module inactive): ' . implode( ', ', $skipped ) . '.';
		}
		$checks[] = array(
			'label'  => 'Order100 DB Tables',
			'msg'    => $msg,
			'status' => empty( $missing ) ? 'ok' : 'error',
		);

		// 10. Key Options
		$key_options = array( 'o100_misc', 'o100_portal' );
		$opt_missing = array();
		foreach ( $key_options as $opt ) {
			if ( get_option( $opt, '__MISSING__' ) === '__MISSING__' ) {
				$opt_missing[] = $opt;
			}
		}
		$checks[] = array(
			'label'  => 'Core Settings',
			'msg'    => empty( $opt_missing ) ? 'All core option keys present.' : 'Missing: ' . implode( ', ', $opt_missing ) . '. Save Settings to initialize.',
			'status' => empty( $opt_missing ) ? 'ok' : 'warning',
			'action' => empty( $opt_missing ) ? '' : admin_url( 'admin.php?page=o100-settings' ),
		);

		wp_send_json_success( $checks );
	}

	/**
	 * Tools > Delivery Simulator tab content
	 */
	private function render_tools_delivery_sim() {
		$branches = array();
		$q = new WP_Query( array( 'post_type' => 'o100_location', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$branches[] = array( 'id' => get_the_ID(), 'title' => get_the_title(), 'latlng' => get_post_meta( get_the_ID(), 'o100_latlng', true ) );
			}
			wp_reset_postdata();
		}
		?>
		<script>window.o100_diag_branches = <?php echo wp_json_encode( $branches ); ?>;</script>
		<div class="o100-settings-group-card">
			<div class="o100-settings-group-title">
				<h3><?php esc_html_e( 'Delivery Simulator', 'order100' ); ?></h3>
				<p><?php esc_html_e( 'Simulate checkout conditions to verify shipping fees and restrictions without using the frontend.', 'order100' ); ?></p>
			</div>
			<div class="o100-settings-group-content">
				<div class="cmb-row">
					<div class="cmb-th"><label><?php esc_html_e( 'Order Method', 'order100' ); ?></label></div>
					<div class="cmb-td">
						<select id="o100-sim-method" class="regular-text">
							<option value="delivery"><?php esc_html_e( 'Delivery', 'order100' ); ?></option>
							<option value="pickup"><?php esc_html_e( 'Pickup', 'order100' ); ?></option>
						</select>
					</div>
				</div>
				<div class="cmb-row">
					<div class="cmb-th"><label><?php esc_html_e( 'Selected Branch', 'order100' ); ?></label></div>
					<div class="cmb-td">
						<select id="o100-sim-location" class="regular-text">
							<option value="0"><?php esc_html_e( 'Global (No specific branch)', 'order100' ); ?></option>
							<?php foreach ( $branches as $b ) : ?>
								<option value="<?php echo esc_attr( $b['id'] ); ?>"><?php echo esc_html( $b['title'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="cmb-row">
					<div class="cmb-th"><label><?php esc_html_e( 'Real Address', 'order100' ); ?></label></div>
					<div class="cmb-td">
						<input type="text" id="o100-sim-address" class="regular-text" placeholder="<?php esc_attr_e( 'Start typing address...', 'order100' ); ?>">
					</div>
				</div>
				<div class="cmb-row" style="text-align:center; position:relative;">
					<hr style="border:0; border-top:1px dashed #cbd5e1; position:absolute; top:50%; left:0; width:100%; z-index:1;">
					<span style="background:#fff; padding:0 12px; font-size:12px; font-weight:bold; color:#94a3b8; position:relative; z-index:2;">— OR —</span>
				</div>
				<div class="cmb-row">
					<div class="cmb-th"><label><?php esc_html_e( 'Distance (km)', 'order100' ); ?></label></div>
					<div class="cmb-td">
						<input type="number" id="o100-sim-distance" class="regular-text" step="0.1" value="5.5">
					</div>
				</div>
				<div class="cmb-row">
					<div class="cmb-th"><label><?php esc_html_e( 'Cart Subtotal ($)', 'order100' ); ?></label></div>
					<div class="cmb-td">
						<input type="number" id="o100-sim-subtotal" class="regular-text" step="0.01" value="45.00">
					</div>
				</div>
				<div class="cmb-row">
					<div class="cmb-th"></div>
					<div class="cmb-td">
						<button type="button" id="o100-btn-run-sim-inline" class="button button-primary button-large" style="background:#F59322; border-color:#F59322;"><?php esc_html_e( 'Simulate Checkout', 'order100' ); ?></button>
						<div id="o100-sim-results-inline" style="margin-top:20px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; display:none; max-width: 480px;"></div>
					</div>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Distance calculator
			var distanceCalc = function(lat1, lon1, lat2, lon2) {
				var p = 0.017453292519943295, c = Math.cos;
				var a = 0.5 - c((lat2-lat1)*p)/2 + c(lat1*p)*c(lat2*p)*(1-c((lon2-lon1)*p))/2;
				return 12742 * Math.asin(Math.sqrt(a));
			};
			var updateDistance = function() {
				if (!window.o100DiagUserLatLng) return;
				var locId = $('#o100-sim-location').val();
				if (!locId || locId == '0') return;
				var branch = window.o100_diag_branches.find(function(b) { return b.id == locId; });
				if (branch && branch.latlng) {
					var coords = branch.latlng.split(',');
					if (coords.length === 2) {
						var dist = distanceCalc(window.o100DiagUserLatLng.lat, window.o100DiagUserLatLng.lng, parseFloat(coords[0]), parseFloat(coords[1]));
						$('#o100-sim-distance').val(dist.toFixed(2)).css({backgroundColor:'#ecfdf5',transition:'background 0.5s'});
						setTimeout(function(){ $('#o100-sim-distance').css({backgroundColor:'#fff'}); }, 1000);
					}
				}
			};
			$('#o100-sim-location').on('change', updateDistance);

			// Google Autocomplete
			if (typeof google !== 'undefined' && google.maps && google.maps.places) {
				var input = document.getElementById('o100-sim-address');
				if (input) {
					var autocomplete = new google.maps.places.Autocomplete(input, { types: ['geocode'] });
					autocomplete.addListener('place_changed', function() {
						var place = autocomplete.getPlace();
						if (!place.geometry) return;
						window.o100DiagUserLatLng = { lat: place.geometry.location.lat(), lng: place.geometry.location.lng() };
						updateDistance();
					});
				}
			}

			// Simulate
			$('#o100-btn-run-sim-inline').on('click', function() {
				var $btn = $(this), $res = $('#o100-sim-results-inline');
				$btn.text('Simulating...').prop('disabled', true);
				$res.hide().html('<div style="text-align:center; padding:20px;"><span class="spinner is-active" style="float:none;"></span></div>').fadeIn(200);
				$.post(ajaxurl, {
					action: 'o100_run_shipping_sim',
					method: $('#o100-sim-method').val(),
					location: $('#o100-sim-location').val(),
					distance: $('#o100-sim-distance').val(),
					subtotal: $('#o100-sim-subtotal').val(),
					nonce: '<?php echo wp_create_nonce("o100_diag_nonce"); ?>'
				}, function(res) {
					$btn.text('<?php echo esc_js( __( 'Simulate Checkout', 'order100' ) ); ?>').prop('disabled', false);
					if (res.success) { $res.html(res.data.html); console.log('Sim Debug:', res.data.debug); }
					else { $res.html('<p style="color:red;">Simulation failed.</p>'); }
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Notifications page — shows notification tab from CMB2 form
	 */
	public function render_notifications() {
		?>
		<style>
			/* Hide the native old wrapper styles */
			#wpfooter { display: none !important; }
			.o100-wrap .o100-card-box { background: transparent; border: none; box-shadow: none; padding: 0; }
			.o100-wrap .o100-card-header { display: none; }
			.o100-wrap .o100-card-body { padding: 0; }
			/* Hide duplicate CMB2 title */
			.o100-notifications-container .cmb2-metabox-title { display: none !important; }
			/* Make the container full width */
			.o100-notifications-container { margin-top: 0; background: transparent !important; border: none !important; box-shadow: none !important; padding: 0 32px 48px 32px !important; }
			/* CMB2 wrappers — transparent, full width */
			.o100-notifications-container .cmb2-wrap,
			.o100-notifications-container .cmb2-wrap > .postbox,
			.o100-notifications-container .cmb2-wrap .cmb2-metabox { background: transparent !important; border: none !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; }
			/* Custom callback rows — full width, no padding */
			.o100-notifications-container .cmb-row.o100-tab-notification { display: block !important; width: 100% !important; max-width: 100% !important; padding: 0 !important; border: none !important; box-sizing: border-box !important; }
			/* Subtab content full width */
			.o100-notifications-container .o100-notify-subtab-content { width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; }
			/* Hide default save button at bottom, we don't need it if iframe handles save, or we put it inside SMS tab */
			.o100-notifications-container > form > input[type="submit"],
			.o100-notifications-container > form > .cmb-submit { display: none !important; }
			.o100-notifications-container form.cmb-form { margin: 0 !important; padding: 0 !important; width: 100% !important; }
			.o100-notifications-container .cmb2-id-o100-notification-dummy { padding: 0 !important; border: none !important; background: transparent !important; }
		</style>
		<style>
		/* ═══ Hand-written Tailwind utility subset for Email React app ═══ */
		/* Layout */
		.o100-notifications-container .w-full { width: 100% !important; }
		.o100-notifications-container .relative { position: relative !important; }
		.o100-notifications-container .absolute { position: absolute !important; }
		.o100-notifications-container .flex { display: flex !important; }
		.o100-notifications-container .flex-1 { flex: 1 1 0% !important; }
		.o100-notifications-container .flex-wrap { flex-wrap: wrap !important; }
		.o100-notifications-container .items-center { align-items: center !important; }
		.o100-notifications-container .justify-center { justify-content: center !important; }
		.o100-notifications-container .gap-3 { gap: 0.75rem !important; }
		.o100-notifications-container .space-x-8 > * + * { margin-left: 2rem !important; }
		.o100-notifications-container .overflow-hidden { overflow: hidden !important; }
		/* Spacing */
		.o100-notifications-container .mb-4 { margin-bottom: 1rem !important; }
		.o100-notifications-container .mb-6 { margin-bottom: 1.5rem !important; }
		.o100-notifications-container .ml-2 { margin-left: 0.5rem !important; }
		.o100-notifications-container .-mb-px { margin-bottom: -1px !important; }
		.o100-notifications-container .py-0\.5 { padding-top: 0.125rem !important; padding-bottom: 0.125rem !important; }
		.o100-notifications-container .py-2 { padding-top: 0.5rem !important; padding-bottom: 0.5rem !important; }
		.o100-notifications-container .py-3 { padding-top: 0.75rem !important; padding-bottom: 0.75rem !important; }
		.o100-notifications-container .py-4 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
		.o100-notifications-container .py-8 { padding-top: 2rem !important; padding-bottom: 2rem !important; }
		.o100-notifications-container .px-1 { padding-left: 0.25rem !important; padding-right: 0.25rem !important; }
		.o100-notifications-container .px-2 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
		.o100-notifications-container .px-3 { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
		.o100-notifications-container .px-4 { padding-left: 1rem !important; padding-right: 1rem !important; }
		.o100-notifications-container .px-6 { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
		/* Sizing */
		.o100-notifications-container .w-4 { width: 1rem !important; }
		.o100-notifications-container .h-4 { height: 1rem !important; }
		.o100-notifications-container .min-w-\[200px\] { min-width: 200px !important; }
		.o100-notifications-container .max-w-xs { max-width: 20rem !important; }
		/* Typography */
		.o100-notifications-container .text-xs { font-size: 0.75rem !important; line-height: 1rem !important; }
		.o100-notifications-container .text-sm { font-size: 0.875rem !important; line-height: 1.25rem !important; }
		.o100-notifications-container .text-center { text-align: center !important; }
		.o100-notifications-container .text-left { text-align: left !important; }
		.o100-notifications-container .font-medium { font-weight: 500 !important; }
		.o100-notifications-container .font-semibold { font-weight: 600 !important; }
		.o100-notifications-container .uppercase { text-transform: uppercase !important; }
		.o100-notifications-container .tracking-wider { letter-spacing: 0.05em !important; }
		.o100-notifications-container .whitespace-nowrap { white-space: nowrap !important; }
		/* Colors — unified to blue-500 (#F59322) instead of indigo */
		.o100-notifications-container .text-slate-400 { color: #94a3b8 !important; }
		.o100-notifications-container .text-slate-500 { color: #64748b !important; }
		.o100-notifications-container .text-slate-700 { color: #334155 !important; }
		.o100-notifications-container .text-indigo-600, .o100-notifications-container .text-blue-600 { color: #F59322 !important; }
		.o100-notifications-container .bg-white { background-color: #fff !important; }
		.o100-notifications-container .bg-slate-50 { background-color: #f8fafc !important; }
		.o100-notifications-container .bg-slate-100 { background-color: #f1f5f9 !important; }
		.o100-notifications-container .bg-indigo-100, .o100-notifications-container .bg-blue-100 { background-color: #fff7ed !important; }
		.o100-notifications-container .bg-green-100 { background-color: #dcfce7 !important; }
		.o100-notifications-container .bg-red-100 { background-color: #fee2e2 !important; }
		.o100-notifications-container .text-green-700 { color: #15803d !important; }
		.o100-notifications-container .text-red-700 { color: #b91c1c !important; }
		.o100-notifications-container .bg-transparent { background-color: transparent !important; }
		/* Borders */
		.o100-notifications-container .border { border-width: 1px !important; }
		.o100-notifications-container .border-b { border-bottom-width: 1px !important; }
		.o100-notifications-container .border-b-2 { border-bottom-width: 2px !important; }
		.o100-notifications-container .border-slate-200 { border-color: #e2e8f0 !important; }
		.o100-notifications-container .border-slate-300 { border-color: #cbd5e1 !important; }
		.o100-notifications-container .border-indigo-500, .o100-notifications-container .border-blue-500 { border-color: #F59322 !important; }
		.o100-notifications-container .border-transparent { border-color: transparent !important; }
		/* Border radius */
		.o100-notifications-container .rounded-full { border-radius: 9999px !important; }
		.o100-notifications-container .rounded-lg { border-radius: 0.5rem !important; }
		.o100-notifications-container .rounded-xl { border-radius: 0.75rem !important; }
		/* Shadows */
		.o100-notifications-container .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05) !important; }
		/* Transitions */
		.o100-notifications-container .transition-colors { transition: color 0.15s ease, background-color 0.15s ease, border-color 0.15s ease !important; }
		.o100-notifications-container .cursor-pointer { cursor: pointer !important; }
		/* Focus */
		.o100-notifications-container .focus\:outline-none:focus { outline: none !important; }
		.o100-notifications-container .focus\:ring-2:focus { box-shadow: 0 0 0 2px #F59322 !important; }
		.o100-notifications-container .focus\:ring-indigo-500:focus, .o100-notifications-container .focus\:ring-blue-500:focus { box-shadow: 0 0 0 2px #F59322 !important; }
		.o100-notifications-container .focus\:border-indigo-500:focus, .o100-notifications-container .focus\:border-blue-500:focus { border-color: #F59322 !important; }
		/* Hover */
		.o100-notifications-container .hover\:text-slate-700:hover { color: #334155 !important; }
		.o100-notifications-container .hover\:border-slate-300:hover { border-color: #cbd5e1 !important; }
		.o100-notifications-container .hover\:bg-slate-50:hover { background-color: #f8fafc !important; }
		/* Table */
		.o100-notifications-container .border-collapse { border-collapse: collapse !important; }
		/* Fix search icon overlap — WP admin padding override */
		.o100-notifications-container .relative > input[type="text"] { padding-left: 36px !important; }
		</style>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<div class="o100-fluent-content o100-notifications-container">
				<?php cmb2_metabox_form( 'o100_notifications', 'o100_notifications', array( 
					'save_button' => __( 'Save Settings', 'order100' ),
					'form_format' => '<form class="cmb-form" method="post" id="%1$s" enctype="multipart/form-data" encoding="multipart/form-data"><input type="hidden" name="object_id" value="%2$s"><input type="hidden" name="submit-cmb" value="1">%3$s<input type="submit" name="submit-cmb-btn" value="%4$s" class="button-primary" style="display:none;"></form>'
				) ); ?>

				<!-- ═══ REPORTS SUB-TAB (outside CMB2 form) ═══ -->
				<div class="o100-notify-subtab-content" data-subtab="reports" style="display:none; width: 100%;">
					<?php require_once O100_PATH . 'core/notifications/views/view-notification-reports.php'; ?>
				</div><!-- /reports subtab -->
			</div>
		</div>
		<?php
	}

	/**
	 * Integration page (API, Push, FluentCRM) — shows api tab from old CMB2 form
	 */
	public function render_integration() {
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<style>
				.o100-wrap .o100-tab-api { display: block !important; }
				/* Hide all native bottom-left CMB2 save buttons globally */
			.cmb-form > input[type="submit"],
			.cmb-button-submit,
			.o100-card-body > input[name="submit-cmb"] {
				display: none !important;
			}
		</style>
			<div class="o100-card-box">
				<div class="o100-card-header">
					<h2><?php esc_html_e( 'Integration', 'order100' ); ?></h2>
				</div>
				<div class="o100-card-body">
					<?php cmb2_metabox_form( 'o100_options', 'o100_options', array( 'save_button' => __( 'Save Settings', 'order100' ) ) ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Documentation page
	 */
	public function render_docs() {
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<h1><?php esc_html_e( 'Documentation', 'order100' ); ?></h1>
			<p><?php esc_html_e( 'Documentation will be available at order100.io', 'order100' ); ?></p>
			<a href="https://order100.io/docs" target="_blank" class="button button-primary">
				<?php esc_html_e( 'Visit Documentation →', 'order100' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * App Devices (QR Code Pairing)
	 */
	public function render_app_devices() {
		wp_enqueue_script( 'qrcode-js', 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js', array(), '1.4.4', true );
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<h1><?php esc_html_e( 'App Devices', 'order100' ); ?></h1>
			
			<div class="o100-fluent-content" style="max-width: 800px; margin-top: 20px;">
				<div class="o100-card" style="background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 30px; display: flex; flex-direction: column; align-items: center; text-align: center;">
					<h2 style="margin-top: 0; font-size: 24px; color: #0f172a;"><?php esc_html_e( 'Connect Order100 App', 'order100' ); ?></h2>
					<p style="color: #64748b; font-size: 16px; margin-bottom: 30px; max-width: 500px;">
						<?php esc_html_e( 'Click the button below to generate a secure pairing code. Then open the Order100 App on your tablet and scan the QR code to instantly connect your device.', 'order100' ); ?>
					</p>
					
					<button id="o100-generate-qr-btn" class="button button-primary button-large" style="font-size: 16px; padding: 0 30px; height: 48px; line-height: 46px; border-radius: 6px;">
						<?php esc_html_e( 'Generate Pairing QR Code', 'order100' ); ?>
					</button>
					
					<div id="o100-qr-code-container" style="display: none; margin-top: 40px; padding: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
						<div id="o100-qr-code"></div>
						<p style="margin-top: 16px; color: #0f172a; font-weight: 500;"><?php esc_html_e( 'Scan this code with the Order100 App', 'order100' ); ?></p>
						<p style="margin: 0; color: #ef4444; font-size: 13px;"><?php esc_html_e( 'For your security, this code will only be shown once.', 'order100' ); ?></p>
					</div>
					<div id="o100-qr-loading" style="display: none; margin-top: 40px; color: #64748b;">
						<span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span> <?php esc_html_e( 'Generating secure keys...', 'order100' ); ?>
					</div>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#o100-generate-qr-btn').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $loading = $('#o100-qr-loading');
				var $container = $('#o100-qr-code-container');
				var $qrDiv = $('#o100-qr-code');
				
				$btn.prop('disabled', true);
				$loading.show();
				$container.hide();
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'o100_generate_app_pairing',
						nonce: '<?php echo wp_create_nonce( 'o100_admin_nonce' ); ?>'
					},
					success: function(response) {
						$loading.hide();
						if ( response.success && response.data && response.data.qr_data ) {
							$btn.hide();
							var payload = atob(response.data.qr_data);
							var typeNumber = 0; // Auto-detect
							var errorCorrectionLevel = 'M';
							var qr = qrcode(typeNumber, errorCorrectionLevel);
							qr.addData(payload);
							qr.make();
							$qrDiv.html(qr.createImgTag(5, 10)); 
							$container.fadeIn();
						} else {
							$btn.prop('disabled', false);
							alert('Failed to generate QR code.');
						}
					},
					error: function() {
						$loading.hide();
						$btn.prop('disabled', false);
						alert('Server error.');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Placeholder for upcoming features
	 */
	public function render_placeholder() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

		$titles = array(
			'o100-food-ordering' => __( 'Food Ordering', 'order100' ),
			'o100-customers'     => __( 'Customers (CRM)', 'order100' ),
			'o100-automation'    => __( 'Automation', 'order100' ),
		);
		$title = isset( $titles[ $page ] ) ? $titles[ $page ] : __( 'Coming Soon', 'order100' );
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<h1><?php echo esc_html( $title ); ?></h1>
			<div class="o100-coming-soon-box">
				<span class="dashicons dashicons-lock" style="font-size: 48px; color: #94a3b8; margin-bottom: 16px;"></span>
				<h2><?php esc_html_e( 'Coming Soon', 'order100' ); ?></h2>
				<p><?php esc_html_e( 'This feature is currently in development and will be available in a future release.', 'order100' ); ?></p>
			</div>
		</div>
		<?php
	}
}


