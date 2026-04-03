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
		add_action( 'admin_head', array( $this, 'inject_menu_icon_css' ) );

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
			array( $this, 'render_placeholder' )
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

		// 8. SEO
		add_submenu_page(
			self::MENU_SLUG,
			__( 'SEO', 'order100' ),
			__( 'SEO', 'order100' ),
			self::CAPABILITY,
			'o100-seo',
			array( $this, 'render_seo' )
		);

		// 9. Automation
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Automation', 'order100' ),
			__( 'Automation', 'order100' ) . ' <span class="o100-menu-badge o100-menu-badge--pro">Pro</span>',
			self::CAPABILITY,
			'o100-automation',
			array( $this, 'render_placeholder' )
		);

		// 10. Integration — REMOVED: consolidated into General Settings > Integrations tab

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
				background: linear-gradient(135deg, #1800AD, #6366f1);
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
			body.toplevel_page_order100, body.order100_page_o100-settings, body.order100_page_o100-loyalty, body.order100_page_o100-seo, body.order100_page_o100-notifications {
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
				width: 36px; /* Slightly smaller logo */
				height: 36px;
				border-radius: 8px;
				object-fit: cover;
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
				border-color: #6366f1;
				box-shadow: 0 0 0 1px #6366f1;
			}
			.o100-wrap .cmb2-metabox-description {
				color: #6b7280;
				font-size: 13px;
				margin-top: 6px;
			}
			.o100-wrap .button-primary {
				background: #6366f1 !important;
				border-color: #4f46e5 !important;
				border-radius: 6px !important;
				padding: 0 20px !important;
				height: 38px !important;
				line-height: 36px !important;
				font-weight: 600 !important;
			}
			.o100-wrap .button-primary:hover {
				background: #4f46e5 !important;
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
				background: #6366f1 !important;
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
				background: #4f46e5 !important;
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
				border-bottom: 2px solid transparent;
				transition: all 0.15s ease;
				white-space: nowrap;
				box-sizing: border-box;
			}
			.o100-page-header-nav a:hover {
				color: #1e293b;
			}
			.o100-page-header-nav a.o100-header-nav-active {
				color: #1e293b;
				border-bottom-color: #6366f1;
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
				border: 1px solid #cbd5e1;
				color: #64748b;
				font-size: 14px;
				font-weight: 600;
				text-decoration: none;
				transition: all 0.15s ease;
				margin-left: 12px;
				cursor: pointer;
			}
			.o100-page-header-help:hover {
				border-color: #6366f1;
				color: #6366f1;
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
				bottom: 40px;
				left: 50%;
				transform: translateX(-50%) translateY(20px);
				background: #fff;
				border: 1px solid #e5e7eb;
				border-radius: 10px;
				box-shadow: 0 8px 30px rgba(0,0,0,0.12);
				padding: 14px 24px;
				display: flex;
				align-items: center;
				gap: 12px;
				z-index: 999999;
				opacity: 0;
				transition: opacity 0.3s ease, transform 0.3s ease;
				min-width: 240px;
			}
			.o100-toast.o100-toast--visible {
				opacity: 1;
				transform: translateX(-50%) translateY(0);
			}
			.o100-toast-icon {
				width: 28px;
				height: 28px;
				border-radius: 50%;
				background: #22c55e;
				color: #fff;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 16px;
				flex-shrink: 0;
			}
			.o100-toast-body h4 {
				margin: 0;
				font-size: 14px;
				font-weight: 600;
				color: #111827;
			}
			.o100-toast-body p {
				margin: 2px 0 0;
				font-size: 13px;
				color: #6b7280;
			}
			.o100-toast-close {
				background: none;
				border: none;
				color: #9ca3af;
				cursor: pointer;
				font-size: 18px;
				padding: 0 0 0 8px;
				line-height: 1;
			}
			.o100-toast-close:hover { color: #374151; }
			/* Hide all native bottom-left CMB2 save buttons globally */
			.cmb-form > input[type="submit"],
			.cmb-button-submit,
			.o100-card-body > input[name="submit-cmb"] {
				display: none !important;
			}
		</style>
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

			// Global dirty checking: mark save button disabled initially and re-enable on change
			if ($topSaveBtn.length && $fluentForm.length) {
				$topSaveBtn.addClass('o100-save-disabled');
				
				$fluentForm.on('input change', ':input', function() {
					$topSaveBtn.removeClass('o100-save-disabled');
				});
			}

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

			// Fluent Settings top save button (General Settings page tabs)
			$topSaveBtn.on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				if ($btn.hasClass('o100-save-disabled') || $btn.hasClass('o100-saving')) return;

				var $customForm = $('#o100-delivery-form, #o100-pickup-form');
				
				// 1. Custom Ajax Forms (Delivery / Pickup)
				if ($customForm.length > 0) {
					var formId = $customForm.attr('id');
					var actionName = '';
					var dataKey = '';
					
					if (formId === 'o100-delivery-form') { actionName = 'o100_save_delivery'; dataKey = 'o100_delivery'; }
					else if (formId === 'o100-pickup-form') { actionName = 'o100_save_pickup'; dataKey = 'o100_pickup'; }

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

					$.ajax({
						url: o100Settings.ajaxurl,
						method: 'POST',
						data: postData,
						success: function(response) {
							if(response.success) {
								sessionStorage.setItem('o100_settings_saved', '1');
								location.reload();
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
				
				// 2. Native CMB2 Form (Fallback for other tabs like Appearance, General, etc.)
				var $form = $('.o100-fluent-content form.cmb-form');
				if ($form.length === 0) return;
				
				// Visual feedback
				$btn.addClass('o100-saving').text('Saving...').css('opacity', '0.7');
				
				// Set flag for toast notification after page reload
				sessionStorage.setItem('o100_settings_saved', '1');
				
				// CMB2 requires submit-cmb in POST data to trigger save.
				// The hidden field in form_format already provides this.
				// Use HTMLFormElement.prototype.submit to bypass any naming collisions
				// (if a form element is named 'submit', it shadows the native function).
				HTMLFormElement.prototype.submit.call($form[0]);
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
	public function render_page_header() {
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

		$nav_items = array(
			'order100'           => __( 'Dashboard', 'order100' ),
			'o100-settings'      => __( 'Settings', 'order100' ),
			'o100-loyalty'       => __( 'Loyalty', 'order100' ),
			'o100-notifications' => __( 'Notifications', 'order100' ),
			'o100-seo'           => __( 'SEO', 'order100' ),
			'o100-integration'   => __( 'Integration', 'order100' ),
		);
		?>
		<!-- Hidden H1 to trap WordPress admin notices before our flex layout -->
		<h1 style="display:none; margin:0; padding:0;">Order100</h1>
		<div class="o100-page-header">
			<div class="o100-page-header-left">
				<img src="<?php echo esc_url( O100_URL . 'assets/logo/logo-square.png' ); ?>" alt="Order100" class="o100-page-header-logo">
				<span class="o100-page-header-title">Order100</span>
				<span class="o100-page-header-version">v<?php echo esc_html( O100_VERSION ); ?></span>
			</div>
			<div class="o100-page-header-right">
				<nav class="o100-page-header-nav">
					<?php foreach ( $nav_items as $slug => $label ) :
						$active = ( $current_page === $slug ) ? ' o100-header-nav-active' : '';
					?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="<?php echo esc_attr( $active ); ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
				<div class="o100-page-header-sep"></div>
				<a href="#" id="o100-btn-diagnostic" class="o100-page-header-diag" title="<?php esc_attr_e( 'Diagnostic Center', 'order100' ); ?>" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#f1f5f9; color:#64748b; text-decoration:none; margin-right:8px; transition:all 0.2s;">
					<span class="dashicons dashicons-admin-tools" style="font-size:18px; width:18px; height:18px;"></span>
				</a>
				<a href="#" class="o100-page-header-help" title="<?php esc_attr_e( 'Help & Support', 'order100' ); ?>">?</a>
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
			<div class="o100-dashboard-header">
				<h1><?php esc_html_e( 'Order100', 'order100' ); ?> <span class="o100-version">v<?php echo esc_html( O100_VERSION ); ?></span></h1>
				<p><?php esc_html_e( 'All-in-one WooCommerce solution for Discounts, Loyalty, SEO, Email, and more.', 'order100' ); ?></p>
			</div>

			<div class="o100-dashboard-grid">
				<?php $this->render_dashboard_cards(); ?>
			</div>
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
				'title'  => __( 'SEO Engine', 'order100' ),
				'desc'   => __( 'Auto-generate focus keywords and meta for products', 'order100' ),
				'icon'   => 'dashicons-search',
				'link'   => admin_url( 'admin.php?page=o100-seo' ),
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
			array(
				'title'  => __( 'Integration', 'order100' ),
				'desc'   => __( 'API, push notifications, and third-party connections', 'order100' ),
				'icon'   => 'dashicons-networking',
				'link'   => admin_url( 'admin.php?page=o100-integration' ),
				'status' => 'active',
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
		$tabs = array(
			// ── Step 1: Store Identity ──
			'store_profile'    => array( 'title' => __( 'Profile', 'order100' ), 'icon' => 'dashicons-store' ),
			'store_hours'      => array( 'title' => __( 'Schedule', 'order100' ), 'icon' => 'dashicons-clock' ),
			'locations'        => array( 'title' => __( 'Branches', 'order100' ), 'icon' => 'dashicons-location-alt' ),
			// ── Step 2: Build Your Menu ──
			'menu_builder'     => array( 'title' => __( 'Menu Builder', 'order100' ), 'icon' => 'dashicons-menu-alt' ),
			'product_options'  => array( 'title' => __( 'Item Modifiers', 'order100' ), 'icon' => 'dashicons-plus-alt' ),
			'menu_rules'       => array( 'title' => __( 'Menu Rules', 'order100' ), 'icon' => 'dashicons-filter' ),
			// ── Step 3: Order Methods ──
			'delivery'         => array( 'title' => __( 'Delivery', 'order100' ), 'icon' => 'dashicons-car' ),
			'pickup'           => array( 'title' => __( 'Pickup', 'order100' ), 'icon' => 'dashicons-cart' ),
			'reservation'      => array( 'title' => __( 'Reservation', 'order100' ), 'icon' => 'dashicons-calendar-alt' ),
			// ── Step 4: Checkout & Polish ──
			'checkout_ext'     => array( 'title' => __( 'Tipping', 'order100' ), 'icon' => 'dashicons-money-alt' ),
			'ui_prefs'         => array( 'title' => __( 'Appearance', 'order100' ), 'icon' => 'dashicons-art' ),
			'portal'           => array( 'title' => __( 'Store Portal', 'order100' ), 'icon' => 'dashicons-layout' ),
			'api_integration'  => array( 'title' => __( 'Integrations', 'order100' ), 'icon' => 'dashicons-rest-api' ),
			'misc'             => array( 'title' => __( 'Misc', 'order100' ), 'icon' => 'dashicons-admin-generic' ),
		);

		$current_tab = isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $tabs ) ? sanitize_text_field( $_GET['tab'] ) : 'store_profile';
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			
			<div class="o100-fluent-container">
				<!-- Fluent Sidebar -->
				<div class="o100-fluent-sidebar">
					<ul class="o100-fluent-nav">
						<?php foreach ( $tabs as $tab_id => $tab_data ) : ?>
							<li>
								<a href="?page=o100-settings&tab=<?php echo esc_attr( $tab_id ); ?>" 
								   data-title="<?php echo esc_attr( $tab_data['title'] ); ?>"
								   class="<?php echo $current_tab === $tab_id ? 'active' : ''; ?>">
									<span class="dashicons <?php echo esc_attr( $tab_data['icon'] ); ?>"></span>
									<span class="o100-nav-text"><?php echo esc_html( $tab_data['title'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				
				<!-- Fluent Content -->
				<div class="o100-fluent-content">
					<div class="o100-fluent-header">
						<h2><?php echo esc_html( $tabs[ $current_tab ]['title'] ); ?></h2>
						<div class="o100-fluent-header-actions" style="display:flex; align-items:center; gap:16px;">
							<button type="button" class="o100-fluent-top-save">
								<?php esc_html_e( 'Save Settings', 'order100' ); ?>
							</button>
						</div>
					</div>
					<div class="o100-fluent-form-wrapper">
						<?php 
						if ( $current_tab === 'menu_builder' ) {
							cmb2_metabox_form( "o100_{$current_tab}", "o100_{$current_tab}" ); 
							echo '<style>.o100-fluent-top-save, .cmb-button-submit { display: none !important; }</style>';
						} elseif ( $current_tab === 'store_profile' ) {
							O100_Settings::render_fluent_store_profile();
						} elseif ( $current_tab === 'checkout_ext' ) {
							O100_Settings::render_fluent_checkout_ext();
						} elseif ( $current_tab === 'delivery' ) {
							O100_Settings::render_fluent_delivery();
						} elseif ( $current_tab === 'pickup' ) {
							O100_Settings::render_fluent_pickup();
						} elseif ( $current_tab === 'portal' ) {
							O100_Settings::render_fluent_store_portal();
						} else {
							// Use custom form_format to guarantee submit-cmb is always in POST data.
							// CMB2 requires $_POST['submit-cmb'] to trigger save (helper-functions.php:323).
							// Programmatic submit (top button) doesn't include submit button values,
							// so we bake a hidden field directly into the form template.
							cmb2_metabox_form( "o100_{$current_tab}", "o100_{$current_tab}", array(
								'save_button' => __( 'Save Settings', 'order100' ),
								'form_format' => '<form class="cmb-form" method="post" id="%1$s" enctype="multipart/form-data" encoding="multipart/form-data"><input type="hidden" name="object_id" value="%2$s"><input type="hidden" name="submit-cmb" value="1">%3$s<input type="submit" name="submit-cmb-btn" value="%4$s" class="button-primary"></form>',
							) );
						}
						?>
					</div>
				</div>
			</div>
		</div>
		<script>
		jQuery(document).ready(function($) {

			// ═══ Save Button: Dirty Checking ═══
			var $topSaveBtn = $('.o100-fluent-top-save');
			var $fluentForm = $('.o100-fluent-content form');

			if ($topSaveBtn.length && $fluentForm.length) {
				// Start disabled (grey) until user makes a change
				$topSaveBtn.addClass('o100-save-disabled');

				function markDirty() {
					$topSaveBtn.removeClass('o100-save-disabled');
					$(document).trigger('o100:dirty');
				}

				// Standard form inputs (text, select, checkbox, radio, textarea)
				$fluentForm.on('input change', ':input', markDirty);

				// CMB2 Colorpicker (Iris) fires custom event on the container
				$('.o100-fluent-content').on('irischange', markDirty);

				// CMB2 file/image upload changes
				$('.o100-fluent-content').on('cmb_media_modal_select cmb2_add_row cmb2_remove_row cmb2_shift_rows_complete', markDirty);

				// Fallback: any click on a CMB2 interactive element (color swatch, toggle, etc.)
				$('.o100-fluent-content').on('click', '.wp-color-result, .cmb2-upload-button, .cmb-remove-row-button, .cmb-add-row-button, .cmb2-checkbox label, input[type="checkbox"], input[type="radio"]', markDirty);
			}

			// ═══ Unsaved Changes Warning (Custom UI) ═══
			var o100IsDirty = false;
			var o100PendingUrl = null;

			// Track dirty state in sync with save button
			if ($topSaveBtn.length) {
				// Watch for class changes on the save button
				var origRemoveClass = $.fn.removeClass;
				// Use a simpler approach: override markDirty to also set flag
				$(document).on('o100:dirty', function() { o100IsDirty = true; });
				$(document).on('o100:clean', function() { o100IsDirty = false; });
			}

			// Inject unsaved modal HTML
			$('body').append(
				'<div id="o100-unsaved-overlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:999998; backdrop-filter:blur(4px); opacity:0; transition:opacity 0.2s ease;">' +
				'<div id="o100-unsaved-modal" style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%) scale(0.95); background:#fff; border-radius:16px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); padding:32px; width:420px; max-width:90vw; z-index:999999; opacity:0; transition:all 0.2s ease;">' +
					'<div style="text-align:center; margin-bottom:20px;">' +
						'<div style="width:48px; height:48px; border-radius:50%; background:#fef3c7; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">' +
							'<span class="dashicons dashicons-warning" style="color:#f59e0b; font-size:24px; width:24px; height:24px;"></span>' +
						'</div>' +
						'<h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;">Unsaved Changes</h3>' +
						'<p style="margin:0; font-size:14px; color:#64748b; line-height:1.5;">You have unsaved changes on this page.<br>What would you like to do?</p>' +
					'</div>' +
					'<div style="display:flex; gap:10px; justify-content:center;">' +
						'<button id="o100-unsaved-cancel" style="padding:10px 20px; border:1px solid #e2e8f0; background:#fff; color:#475569; font-size:14px; font-weight:600; border-radius:8px; cursor:pointer; transition:all 0.15s;">Cancel</button>' +
						'<button id="o100-unsaved-discard" style="padding:10px 20px; border:1px solid #fca5a5; background:#fef2f2; color:#dc2626; font-size:14px; font-weight:600; border-radius:8px; cursor:pointer; transition:all 0.15s;">Discard</button>' +
						'<button id="o100-unsaved-save" style="padding:10px 20px; border:none; background:#4f46e5; color:#fff; font-size:14px; font-weight:600; border-radius:8px; cursor:pointer; transition:all 0.15s; box-shadow:0 1px 3px rgba(79,70,229,0.3);">Save & Go</button>' +
					'</div>' +
				'</div>' +
				'</div>'
			);

			function showUnsavedModal(targetUrl) {
				o100PendingUrl = targetUrl;
				var $overlay = $('#o100-unsaved-overlay');
				var $modal = $('#o100-unsaved-modal');
				$overlay.css('display', 'block');
				setTimeout(function() {
					$overlay.css('opacity', '1');
					$modal.css({ opacity: 1, transform: 'translate(-50%,-50%) scale(1)' });
				}, 10);
			}

			function hideUnsavedModal() {
				var $overlay = $('#o100-unsaved-overlay');
				var $modal = $('#o100-unsaved-modal');
				$modal.css({ opacity: 0, transform: 'translate(-50%,-50%) scale(0.95)' });
				$overlay.css('opacity', '0');
				setTimeout(function() { $overlay.css('display', 'none'); }, 200);
				o100PendingUrl = null;
			}

			// Cancel — stay on page
			$('#o100-unsaved-cancel').on('click', hideUnsavedModal);
			$('#o100-unsaved-overlay').on('click', function(e) {
				if (e.target === this) hideUnsavedModal();
			});

			// Discard — leave without saving
			$('#o100-unsaved-discard').on('click', function() {
				o100IsDirty = false;
				if (o100PendingUrl) window.location.href = o100PendingUrl;
			});

			// Save & Go — save first, then navigate to pending URL after reload
			$('#o100-unsaved-save').on('click', function() {
				o100IsDirty = false; // Clear dirty flag so beforeunload doesn't fire
				if (o100PendingUrl) {
					sessionStorage.setItem('o100_redirect_after_save', o100PendingUrl);
				}
				hideUnsavedModal();
				$topSaveBtn.removeClass('o100-save-disabled').trigger('click');
			});

			// Intercept navigation clicks when dirty
			$(document).on('click', '.o100-fluent-nav a, .o100-page-header-nav a, #adminmenu a', function(e) {
				if (o100IsDirty) {
					e.preventDefault();
					showUnsavedModal($(this).attr('href'));
				}
			});

			// Also handle browser back/close with native fallback
			$(window).on('beforeunload', function() {
				if (o100IsDirty) {
					return 'You have unsaved changes.';
				}
			});

			// ═══ Save Button: Click Handler ═══
			$topSaveBtn.on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				if ($btn.hasClass('o100-save-disabled') || $btn.hasClass('o100-saving')) return;

				// Clear dirty flag so beforeunload doesn't fire during save
				o100IsDirty = false;

				var $customForm = $('#o100-delivery-form, #o100-pickup-form');

				// 1. Custom Ajax Forms (Delivery / Pickup)
				if ($customForm.length > 0) {
					var formId = $customForm.attr('id');
					var actionName = '';
					var dataKey = '';

					if (formId === 'o100-delivery-form') { actionName = 'o100_save_delivery'; dataKey = 'o100_delivery'; }
					else if (formId === 'o100-pickup-form') { actionName = 'o100_save_pickup'; dataKey = 'o100_pickup'; }

					if (actionName) {
						$btn.addClass('o100-saving').text('Saving...').css('opacity', '0.7');

						var rawData = $customForm.serializeArray();
						var postData = { action: actionName };
						postData[dataKey] = {};

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

						if (formId === 'o100-delivery-form' && $('#o100_enable_delivery').is(':checked')) {
							postData[dataKey]['o100_enable_delivery'] = 'on';
						}
						if (formId === 'o100-pickup-form' && $('#o100_enable_pickup').is(':checked')) {
							postData[dataKey]['o100_enable_pickup'] = 'on';
						}

						$.ajax({
							url: o100Settings.ajaxurl,
							method: 'POST',
							data: postData,
							success: function(response) {
								if(response.success) {
									sessionStorage.setItem('o100_settings_saved', '1');
									location.reload();
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

						return;
					}
				}

				// 2. Native CMB2 Form (Appearance, General, Locations, etc.)
				var $form = $('.o100-fluent-content form.cmb-form');
				if ($form.length === 0) return;

				$btn.addClass('o100-saving').text('Saving...').css('opacity', '0.7');
				sessionStorage.setItem('o100_settings_saved', '1');

				// Use prototype.submit to bypass any naming collisions on form elements
				HTMLFormElement.prototype.submit.call($form[0]);
			});

			// ═══ Toast: Show success notification after page reload ═══
			if (sessionStorage.getItem('o100_settings_saved')) {
				sessionStorage.removeItem('o100_settings_saved');

				// Check if we need to redirect after save (from "Save & Go")
				var redirectUrl = sessionStorage.getItem('o100_redirect_after_save');
				if (redirectUrl) {
					sessionStorage.removeItem('o100_redirect_after_save');
					window.location.href = redirectUrl;
					return; // Skip toast since we're navigating away
				}

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
			}

			// Custom CMB2 Conditionals fallback for standalone checkboxes
			function handleO100Conditionals() {
				$('[data-conditional-id]').each(function() {
					var $el = $(this);
					var $row = $el.closest('.cmb-row');
					if ($row.length === 0) return;
					
					var targetId = $el.attr('data-conditional-id');
					var targetValue = $el.attr('data-conditional-value');
					var $target = $('#' + targetId);
					
					if ($target.length) {
						var checkVisibility = function() {
							var isVisible = false;
							if ($target.is(':checkbox')) {
								isVisible = $target.is(':checked') && targetValue === 'on';
							} else {
								isVisible = $target.val() === targetValue;
							}
							
							if (isVisible) {
								$row.show();
							} else {
								$row.hide();
							}
						};
						
						$target.on('change', checkVisibility);
						checkVisibility(); // Run on load
					}
				});

				// Explicit handler for the Date Rules group (CMB2 doesn't output custom attrs correctly for groups)
				var $dateSwitch = $('#o100_menu_date');
				var $dateGroup = $('.cmb2-id-o100-global-date-rules');
				if ($dateSwitch.length && $dateGroup.length) {
					var toggleDateGroup = function() {
						if ($dateSwitch.is(':checked')) {
							$dateGroup.show();
						} else {
							$dateGroup.hide();
						}
					};
					$dateSwitch.on('change', toggleDateGroup);
					toggleDateGroup();
				}

				// Handle Assign To in Date Rules Repeater
				function toggleAssignType() {
					var $select = $(this);
					var val = $select.val();
					var $group = $select.closest('.cmb-repeatable-grouping');
					
					var $proRow = $group.find('[id$="_o100_rule_products"]').closest('.cmb-row');
					var $catRow = $group.find('[id$="_o100_rule_categories"]').closest('.cmb-row');
					
					if (val === 'products') {
						$proRow.show();
						$catRow.hide();
					} else {
						$proRow.hide();
						$catRow.show();
					}
				}
				$(document).on('change', 'select[id^="o100_global_date_rules_"][id$="_o100_rule_assign_type"]', toggleAssignType);
				
				// Initialize all existing on load
				$('select[id^="o100_global_date_rules_"][id$="_o100_rule_assign_type"]').each(toggleAssignType);
				
				// Re-initialize when a new row is added by CMB2
				$('.cmb-repeatable-group').on('cmb2_add_row', function(e, newRow) {
					var $newSelect = $(newRow).find('select[id$="_o100_rule_assign_type"]');
					if ($newSelect.length) {
						toggleAssignType.call($newSelect[0]);
					}
					
					// Also initialize flatpickr on the new row
					var $newDateInput = $(newRow).find('.o100-flatpickr-multi');
					if ($newDateInput.length && typeof flatpickr !== 'undefined') {
						flatpickr($newDateInput[0], {
							mode: "multiple",
							dateFormat: "Y-m-d"
						});
					}
				});
				
				// Initialize flatpickr on load
				if (typeof flatpickr !== 'undefined') {
					$('.o100-flatpickr-multi').each(function() {
						flatpickr(this, {
							mode: "multiple",
							dateFormat: "Y-m-d"
						});
					});
				}
			}
			// Run immediately and also after short delay for dynamically initialized fields
			handleO100Conditionals();
			setTimeout(handleO100Conditionals, 500);
		});
		</script>
		<?php
	}


	/**
	/**
	 * Discounts page — shows discount tab from CMB2 form
	 */
	/**
	 * Reservations management page
	 */
	public function render_reservations() {
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<div class="o100-fluent-container" style="display:block;">
				<div class="o100-fluent-content" style="margin-left:0;">
					<div class="o100-fluent-header">
						<h2><?php esc_html_e( 'Reservations', 'order100' ); ?></h2>
					</div>
					<div class="o100-fluent-form-wrapper">
						<?php O100_Reservation_Admin::render_page(); ?>
					</div>
				</div>
			</div>
		</div>
		<style>
		/* ═══ Reservation Admin Styles ═══ */
		.o100-resv-page { padding: 0; }
		.o100-resv-tabs {
			display: flex; gap: 0; margin-bottom: 20px;
			border-bottom: 2px solid #e5e7eb;
		}
		.o100-resv-tab {
			padding: 10px 20px; font-size: 14px; font-weight: 500;
			color: #64748b; text-decoration: none;
			border-bottom: 2px solid transparent;
			margin-bottom: -2px; transition: all 0.15s;
		}
		.o100-resv-tab:hover { color: #1e293b; }
		.o100-resv-tab-active {
			color: #1e293b; font-weight: 600;
			border-bottom-color: #6366f1;
		}
		.o100-resv-status-pills {
			display: flex; gap: 8px; margin-bottom: 16px;
		}
		.o100-resv-pill {
			display: inline-flex; align-items: center; gap: 4px;
			padding: 6px 14px; border-radius: 20px; font-size: 13px;
			text-decoration: none; color: #475569;
			background: #f8fafc; border: 1px solid #e2e8f0;
			transition: all 0.15s;
		}
		.o100-resv-pill:hover { border-color: #cbd5e1; background: #f1f5f9; color: #1e293b; }
		.o100-resv-pill.active { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; font-weight: 600; }
		.o100-resv-pill .count { font-size: 12px; color: #94a3b8; }
		.o100-resv-pill.active .count { color: #6366f1; }

		/* Bulk actions bar */
		.o100-resv-bulk-bar {
			display: flex; gap: 8px; align-items: center; margin-bottom: 12px;
		}
		.o100-resv-bulk-select {
			padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 6px;
			font-size: 13px; background: #fff; color: #374151;
		}
		.o100-resv-bulk-apply {
			padding: 6px 16px; border: 1px solid #e2e8f0; border-radius: 6px;
			font-size: 13px; background: #f8fafc; color: #475569; cursor: pointer;
			transition: all 0.15s;
		}
		.o100-resv-bulk-apply:hover { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }

		/* List Table overrides */
		.o100-resv-page .wp-list-table {
			border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;
			border-spacing: 0;
		}
		.o100-resv-page .wp-list-table thead th {
			background: #f8fafc; border-bottom: 1px solid #e5e7eb;
			font-size: 12px; font-weight: 600; text-transform: uppercase;
			letter-spacing: 0.05em; color: #64748b; padding: 10px 12px;
		}
		.o100-resv-page .wp-list-table td {
			padding: 12px; vertical-align: middle;
			border-bottom: 1px solid #f1f5f9;
		}
		.o100-resv-page .wp-list-table tbody tr:hover td {
			background: #fafbff;
		}

		/* Status badges */
		.o100-resv-badge {
			display: inline-block; padding: 3px 10px; border-radius: 12px;
			font-size: 12px; font-weight: 600; line-height: 1.4;
		}
		.o100-resv-badge--pending { background: #fef3c7; color: #92400e; }
		.o100-resv-badge--confirmed { background: #d1fae5; color: #065f46; }
		.o100-resv-badge--cancelled { background: #fee2e2; color: #991b1b; }

		/* Guest details */
		.o100-resv-guest-detail { font-size: 12px; color: #94a3b8; }

		/* Actions */
		.o100-resv-action {
			font-size: 13px; text-decoration: none; white-space: nowrap;
		}
		.o100-resv-action--confirm { color: #059669; font-weight: 600; }
		.o100-resv-action--confirm:hover { color: #047857; }
		.o100-resv-action--cancel { color: #dc2626; }
		.o100-resv-action--cancel:hover { color: #b91c1c; }
		.o100-resv-action--note { cursor: help; }

		/* Party size */
		.o100-resv-party {
			display: inline-flex; align-items: center; justify-content: center;
			width: 28px; height: 28px; border-radius: 50%;
			background: #f1f5f9; font-size: 13px; font-weight: 600; color: #475569;
		}

		/* Empty state */
		.o100-resv-empty {
			text-align: center; padding: 60px 20px;
		}
		.o100-resv-empty p { color: #94a3b8; font-size: 15px; margin-top: 12px; }

		/* Type icons */
		.o100-resv-type { font-size: 18px; }

		/* N/A dash */
		.o100-resv-na { color: #cbd5e1; }

		/* Time */
		.o100-resv-time { font-size: 13px; color: #6366f1; font-weight: 500; }
		</style>
		<?php
	}

	/**
	 * Promotions page
	 */
	public function render_promotions() {
		// Load the Promotions Admin class
		if ( ! class_exists( 'O100_Promotions_Admin' ) ) {
			require_once O100_PATH . 'core/promotions/class-o100-promotions-admin.php';
		}
		
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<?php O100_Promotions_Admin::render_page(); ?>
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
	 * SEO page — shows seo tab from CMB2 form
	 */
	public function render_seo() {
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<div class="o100-card-box">
				<div class="o100-card-header">
					<h2><?php esc_html_e( 'SEO Automation', 'order100' ); ?></h2>
				</div>
				<div class="o100-card-body">
					<?php cmb2_metabox_form( 'o100_seo', 'o100_seo', array( 'save_button' => __( 'Save Settings', 'order100' ) ) ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Notifications page — shows notification tab from CMB2 form
	 */
	public function render_notifications() {
		?>
		<div class="wrap o100-wrap">
			<?php $this->render_page_header(); ?>
			<div class="o100-card-box">
				<div class="o100-card-header">
					<h2><?php esc_html_e( 'Email & Notifications', 'order100' ); ?></h2>
				</div>
				<div class="o100-card-body">
					<?php cmb2_metabox_form( 'o100_notifications', 'o100_notifications', array( 'save_button' => __( 'Save Settings', 'order100' ) ) ); ?>
				</div>
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

// TS: 20260402232730
