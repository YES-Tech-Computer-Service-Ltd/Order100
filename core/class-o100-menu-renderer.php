<?php
/**
 * Order100 Menu Renderer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Menu_Renderer {

	/**
	 * Initialize the shortcode
	 */
	public static function init() {
		add_shortcode( 'o100_menu', array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( 'order100_food_menu', array( __CLASS__, 'render_shortcode' ) );
		// Ensure compatibility with old exfood shortcodes for users migrating
		add_shortcode( 'ex_wf_grid', array( __CLASS__, 'render_legacy_shortcode_bridge' ) );
		add_shortcode( 'ex_wf_list', array( __CLASS__, 'render_legacy_shortcode_bridge' ) );
		add_shortcode( 'ex_wf_carousel', array( __CLASS__, 'render_legacy_shortcode_bridge' ) );
		add_shortcode( 'ex_wf_table', array( __CLASS__, 'render_legacy_shortcode_bridge' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue necessary assets for the menu UI
	 */
	public static function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'o100_menu' ) || has_shortcode( $post->post_content, 'order100_food_menu' ) ) ) {
			wp_enqueue_style( 'dashicons' );
		}
	}

	/**
	 * Bridge for legacy exfood shortcodes (e.g. [ex_wf_grid cat="pizza" posts_per_page="4"])
	 */
	public static function render_legacy_shortcode_bridge( $atts, $content = null, $tag = '' ) {
		$type_map = array(
			'ex_wf_grid'     => 'grid',
			'ex_wf_list'     => 'list',
			'ex_wf_carousel' => 'carousel',
			'ex_wf_table'    => 'table',
		);
		$type = isset( $type_map[ $tag ] ) ? $type_map[ $tag ] : 'grid';
		
		// Map shortcode atts to our settings array format
		$settings = array(
			'categories'     => isset($atts['cat']) ? $atts['cat'] : '',
			'posts_per_page' => isset($atts['posts_per_page']) ? $atts['posts_per_page'] : '',
			'columns'        => isset($atts['column']) ? $atts['column'] : 3,
			'order'          => isset($atts['order']) ? $atts['order'] : 'DESC',
			'orderby'        => isset($atts['orderby']) ? $atts['orderby'] : 'date',
			'ids'            => isset($atts['ids']) ? $atts['ids'] : '',
			'class'          => isset($atts['class']) ? $atts['class'] : '',
		);

		ob_start();
		echo '<div class="o100-menu-wrapper ' . esc_attr( $settings['class'] ) . '">';
		self::render_standalone_layout( $settings, $type );
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Render the shortcode
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'id' => '',
		), $atts, 'order100_food_menu' );

		if ( empty( $atts['id'] ) ) {
			return '<p style="color:red;">Order100 Menu: Missing Shortcode ID.</p>';
		}

		$configs = get_option( 'o100_menu_shortcodes' );
		if ( ! $configs || ! isset( $configs[ $atts['id'] ] ) ) {
			$configs = get_option( 'o100_menu_configs' );
		}
		if ( ! $configs || ! isset( $configs[ $atts['id'] ] ) ) {
			$configs = get_option( 'exwoofood_menu_configs' );
		}
		
		if ( ! $configs || ! isset( $configs[ $atts['id'] ] ) ) {
			return '<p style="color:red;">Order100 Menu: Configuration not found for ID ' . esc_html( $atts['id'] ) . '.</p>';
		}

		$settings = $configs[ $atts['id'] ];
		$type     = isset( $settings['sc_type'] ) ? $settings['sc_type'] : (isset($settings['type']) ? $settings['type'] : 'mn_group');

		// Handle missing Menu Builder configurations
		if ( isset( $settings['enable_modal'] ) && $settings['enable_modal'] === 'no' ) {
			global $o100_disable_entry_modal;
			$o100_disable_entry_modal = true;
		} elseif ( isset( $settings['enable_mtod'] ) && $settings['enable_mtod'] === 'no' ) {
			global $o100_disable_entry_modal;
			$o100_disable_entry_modal = true;
		} else {
			global $o100_enable_entry_modal;
			$o100_enable_entry_modal = true;
		}
		
		if ( isset( $settings['cart_enable'] ) && $settings['cart_enable'] === 'no' ) {
			global $o100_disable_side_cart;
			$o100_disable_side_cart = true;
		}

		ob_start();

		// Check menu_pos for Sidebar Layout
		$wrapper_class = isset( $settings['class'] ) ? $settings['class'] : '';
		if ( isset( $settings['menu_pos'] ) && $settings['menu_pos'] === 'left' ) {
			$wrapper_class .= ' o100-layout-sidebar';
		}

		echo '<div class="o100-menu-wrapper ' . esc_attr( trim($wrapper_class) ) . '">';

		if ( $type === 'mn_group' || $type === 'exwoofood_menu' ) {
			self::render_menu_group( $settings );
		} else {
			// Handle grid, list, carousel, table
			self::render_standalone_layout( $settings, $type );
		}

		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Render the CSS styles needed for products cards
	 */
	private static function render_shared_styles($cols, $cols_tablet = 2, $cols_mobile = 1, $style_id = '1') {
        $carousel_tablet_flex = ($cols_tablet == 1) ? '85%' : 'calc( (100% - (' . esc_html($cols_tablet - 1) . ' * 16px)) / ' . esc_html($cols_tablet) . ' )';
        $carousel_mobile_flex = ($cols_mobile == 1) ? '85%' : 'calc( (100% - (' . esc_html($cols_mobile - 1) . ' * 12px)) / ' . esc_html($cols_mobile) . ' )';
        
		echo '<style>
		.o100-menu-container { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; max-width: 1200px; margin: 0 auto; }
		.o100-category-nav-wrapper::-webkit-scrollbar { display: none; }
		
		/* Search Bar - DoorDash Pill Style */
		.o100-menu-container .o100-search-container { position: relative !important; width: 100% !important; max-width: 500px !important; margin-bottom: 8px !important; }
		.o100-menu-container .o100-search-container svg { position: absolute !important; left: 18px !important; top: 50% !important; transform: translateY(-50%) !important; width: 20px !important; height: 20px !important; color: #000 !important; fill: none !important; stroke: currentColor !important; }
		.o100-menu-container input.o100-search-input { width: 100% !important; padding: 10px 16px 10px 50px !important; border-radius: 50px !important; border: none !important; background: #f2f2f2 !important; font-size: 16px !important; outline: none !important; color: #111 !important; font-weight: 500 !important; }
		.o100-menu-container input.o100-search-input:focus { background: #e8e8e8 !important; }
		.o100-menu-container input.o100-search-input::placeholder { color: #767676 !important; }

		/* Category Nav Shared */
		.o100-menu-container .o100-menu-header { position: sticky !important; top: 0 !important; z-index: 99 !important; background: #fff !important; border-bottom: 1px solid #e8e8e8 !important; margin-bottom: 24px !important; padding: 10px 0 !important; }
		.o100-menu-container .o100-nav-inner { display: flex !important; align-items: center !important; width: 100% !important; position: relative !important; }
		.o100-menu-container .o100-list-icon { padding-right: 16px !important; color: #000 !important; flex-shrink: 0 !important; display: flex !important; }
		.o100-menu-container .o100-category-nav-wrapper { display: flex !important; overflow-x: auto !important; flex-grow: 1 !important; scrollbar-width: none !important; align-items: center !important; scroll-behavior: smooth !important; }
		.o100-menu-container a.o100-cat-link { text-decoration: none !important; color: #767676 !important; font-weight: 500 !important; white-space: nowrap !important; transition: all 0.2s !important; }
		@media (max-width: 768px) {
			.o100-menu-container a.o100-cat-link { font-size: 13px !important; }
		}

		/* Nav Style 1: DoorDash Underline */
		.o100-menu-container.o100-style-1 a.o100-cat-link { padding: 10px 0 !important; margin-right: 24px !important; font-size: 15px !important; border-bottom: 3px solid transparent !important; }
		.o100-menu-container.o100-style-1 a.o100-cat-link:hover { color: #191919 !important; border-bottom-color: #191919 !important; }
		.o100-menu-container.o100-style-1 a.o100-cat-link.active { color: #000 !important; font-weight: 700 !important; border-bottom: 3px solid #000 !important; }
		@media (max-width: 768px) { .o100-menu-container.o100-style-1 a.o100-cat-link { padding: 8px 12px !important; margin-right: 12px !important; } }

		/* Nav Style 2: Solid Pills */
		.o100-menu-container.o100-style-2 a.o100-cat-link { border-radius: 50px !important; padding: 8px 16px !important; background: #f2f2f2 !important; color: #111 !important; margin-right: 12px !important; }
		.o100-menu-container.o100-style-2 a.o100-cat-link:hover { background: #e8e8e8 !important; }
		.o100-menu-container.o100-style-2 a.o100-cat-link.active { background: #111 !important; color: #fff !important; font-weight: 600 !important; }

		/* Nav Style 3: Outlined Pills */
		.o100-menu-container.o100-style-3 a.o100-cat-link { border-radius: 50px !important; padding: 8px 16px !important; background: transparent !important; border: 1px solid #e8e8e8 !important; color: #111 !important; margin-right: 12px !important; }
		.o100-menu-container.o100-style-3 a.o100-cat-link:hover { border-color: #111 !important; }
		.o100-menu-container.o100-style-3 a.o100-cat-link.active { border-color: #111 !important; background: #111 !important; color: #fff !important; font-weight: 600 !important; }

		/* Nav Style 4: Minimal Text */
		.o100-menu-container.o100-style-4 a.o100-cat-link { padding: 10px 0 !important; margin-right: 24px !important; font-size: 15px !important; }
		.o100-menu-container.o100-style-4 a.o100-cat-link:hover { color: #111 !important; }
		.o100-menu-container.o100-style-4 a.o100-cat-link.active { color: #111 !important; font-weight: 700 !important; }

		/* Nav Style 5: Classic Woomenu */
		.o100-menu-container.o100-style-5 .o100-menu-header { background: #f8f9fa !important; border: 1px solid #e8e8e8 !important; border-radius: 8px; padding: 0 16px !important; margin-bottom: 24px !important; }
		.o100-menu-container.o100-style-5 a.o100-cat-link { padding: 16px 0 !important; margin-right: 32px !important; color: #555 !important; border-bottom: 3px solid transparent !important; }
		.o100-menu-container.o100-style-5 a.o100-cat-link:hover { color: #000 !important; }
		.o100-menu-container.o100-style-5 a.o100-cat-link.active { color: #d62828 !important; border-bottom: 3px solid #d62828 !important; font-weight: 600 !important; }
		
		/* Nav Arrows */
		.o100-nav-arrow { position: absolute; top: 50%; transform: translateY(-50%); width: 32px; height: 32px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15); cursor: pointer; z-index: 10; color: #000; }
		.o100-nav-arrow-left { left: 36px; }
		.o100-nav-arrow-right { right: 0; }
		
		/* Sidebar Drawer */
		.o100-drawer-overlay { position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; background: rgba(0,0,0,0.5) !important; z-index: 9998 !important; opacity: 0; visibility: hidden; transition: opacity 0.3s; }
		.o100-drawer-overlay.active { opacity: 1 !important; visibility: visible !important; }
		.o100-drawer { position: fixed !important; top: 0 !important; left: 0 !important; width: 320px !important; max-width: 85vw !important; height: 100% !important; background: #fff !important; z-index: 9999 !important; transform: translateX(-100%); transition: transform 0.3s; overflow-y: auto !important; }
		.o100-drawer.active { transform: translateX(0) !important; }
		.o100-drawer-header { display: flex !important; align-items: center !important; justify-content: space-between !important; padding: 20px 20px 12px !important; border-bottom: 1px solid #eee !important; }
		.o100-drawer-header h3 { margin: 0 !important; font-size: 18px !important; font-weight: 700 !important; color: #000 !important; }
		.o100-drawer-close { background: none !important; border: none !important; color: #000 !important; cursor: pointer; }
		.o100-drawer-list { list-style: none !important; padding: 8px 0 !important; margin: 0 !important; }
		.o100-drawer-list li a { display: block !important; padding: 14px 24px !important; font-size: 16px !important; color: #191919 !important; text-decoration: none !important; }
		.o100-drawer-list li a.active { font-weight: 700 !important; background: #f2f2f2 !important; }
		
		/* Section Title */
		.o100-menu-container .o100-menu-section h2 { margin: 0 0 20px 0; font-size: 24px; font-weight: 700; color: #000; letter-spacing: -0.5px; }
		.o100-menu-container.o100-heading-align-center .o100-menu-section h2 { text-align: center; justify-content: center; }
		.o100-menu-container.o100-heading-align-right .o100-menu-section h2 { text-align: right; justify-content: flex-end; }
		.o100-menu-container.o100-heading-align-left .o100-menu-section h2 { text-align: left; justify-content: flex-start; }
		
		/* Heading Style 1: Underlined */
		.o100-menu-container.o100-heading-style-1 .o100-menu-section h2 { border-bottom: 2px solid #e8e8e8; padding-bottom: 8px; }
		
		/* Heading Style 2: Elegant Divider */
		.o100-menu-container.o100-heading-style-2 .o100-menu-section h2 { display: flex; align-items: center; color: #111; border-bottom: none; }
		.o100-menu-container.o100-heading-style-2.o100-heading-align-center .o100-menu-section h2::before,
		.o100-menu-container.o100-heading-style-2.o100-heading-align-center .o100-menu-section h2::after { content: ""; flex: 1; border-bottom: 1px solid #e8e8e8; margin: 0 16px; }
		.o100-menu-container.o100-heading-style-2.o100-heading-align-left .o100-menu-section h2::after { content: ""; flex: 1; border-bottom: 1px solid #e8e8e8; margin-left: 16px; }
		.o100-menu-container.o100-heading-style-2.o100-heading-align-right .o100-menu-section h2::before { content: ""; flex: 1; border-bottom: 1px solid #e8e8e8; margin-right: 16px; }
		
		/* Heading Style 3: Modern Boxed */
		.o100-menu-container.o100-heading-style-3 .o100-menu-section h2 { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 20px; color: #1e293b; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
		
		/* Heading Style 4: Ribbon Accent */
		.o100-menu-container.o100-heading-style-4 .o100-menu-section h2 { border-left: 4px solid #e11d48; padding-left: 12px; border-radius: 2px; }
		
		/* Heading Style 5: Classic Woomenu */
		.o100-menu-container.o100-heading-style-5 .o100-menu-section h2 { font-size: 20px; text-transform: uppercase; letter-spacing: 2px; color: #555; border-bottom: 1px dashed #ccc; padding-bottom: 8px; }
		
		/* Grid Layout */
		.o100-product-grid { display: grid; gap: 16px; }
		
		/* Carousel Layout */
		.o100-layout-carousel .o100-product-grid { 
			display: flex !important; 
			flex-wrap: nowrap !important; 
			overflow-x: auto !important; 
			scroll-snap-type: x mandatory; 
			padding-bottom: 16px; 
			-webkit-overflow-scrolling: touch; 
			gap: 16px; 
		}
		.o100-layout-carousel .o100-product-grid::-webkit-scrollbar { height: 6px; }
		.o100-layout-carousel .o100-product-grid::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
		.o100-layout-carousel .o100-product-grid::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
		.o100-layout-carousel .o100-product-card { 
			scroll-snap-align: start; 
		}

		/* Product Card Base */
		.o100-product-card { position: relative; display: flex; cursor: pointer; background: #fff; transition: box-shadow 0.2s; overflow: hidden; box-sizing: border-box; }
		
		/* --- LIST LAYOUT (Image on Left) --- */
		.o100-layout-list .o100-product-card { flex-direction: row; justify-content: space-between; padding: 0; border: 1px solid #e8e8e8; border-radius: 12px; }
		.o100-layout-list .o100-product-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
		.o100-layout-list .o100-product-info { flex-grow: 1; padding: 16px 16px 16px 20px; display: flex; flex-direction: column; justify-content: space-between; position: relative; }
		.o100-layout-list .o100-product-image-wrap { width: 150px; flex-shrink: 0; position: relative; border-radius: 12px 0 0 12px; overflow: hidden; background: #f5f5f5; }
		.o100-layout-list .o100-product-image { width: 100%; height: 100%; min-height: 140px; background-size: cover; background-position: center; transition: transform 0.3s ease; }
		.o100-layout-list .o100-product-card:hover .o100-product-image { transform: scale(1.05); }
		.o100-layout-list .o100-no-image .o100-product-info { padding-left: 16px; }
		.o100-layout-list .o100-no-image .o100-add-btn-wrapper { position: absolute; bottom: 0; right: 0; }
		
		/* --- GRID & CAROUSEL LAYOUT (Image on Top) --- */
		.o100-layout-grid .o100-product-card, .o100-layout-carousel .o100-product-card { flex-direction: column; border: 1px solid #e8e8e8; border-radius: 12px; }
		/* Card Animations & Quantity Styling */
		.o100-product-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
		.o100-product-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
		
		a.o100-add-btn.o100-qty-active {
			background: #111 !important;
			color: #fff !important;
			padding: 0 10px !important;
			border-radius: 14px !important;
			width: auto !important;
			min-width: 44px !important;
			height: 28px !important;
			line-height: 28px !important;
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			font-size: 12px !important;
			font-weight: 700 !important;
			border: 1px solid #111 !important;
			letter-spacing: 0.5px !important;
			text-decoration: none !important;
		}
		a.o100-add-btn.o100-qty-active svg { display: none !important; }
		
		.o100-layout-grid .o100-product-image-wrap, .o100-layout-carousel .o100-product-image-wrap { width: 100%; height: 200px; position: relative; overflow: hidden; background: #f5f5f5; }
		.o100-layout-grid .o100-product-image, .o100-layout-carousel .o100-product-image { width: 100%; height: 100%; background-size: cover; background-position: center; transition: transform 0.3s ease; }
		.o100-layout-grid .o100-product-card:hover .o100-product-image, .o100-layout-carousel .o100-product-card:hover .o100-product-image { transform: scale(1.05); }
		.o100-layout-grid .o100-product-info, .o100-layout-carousel .o100-product-info { padding: 16px; display: flex; flex-direction: column; flex-grow: 1; }
		
		/* Add Button */
		.o100-add-btn { position: absolute; bottom: 12px; right: 12px; width: 36px; height: 36px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(0,0,0,0.15); border: 1px solid #f0f0f0; z-index: 2; color: #111 !important; transition: all 0.2s; text-decoration: none; }
		.o100-add-btn:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.2); transform: translateY(-1px); }
		.o100-add-btn:active { transform: scale(0.9); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
		.o100-add-btn svg { stroke-width: 3; }
		.o100-btn-spinner { width: 16px; height: 16px; border: 2px solid #e2e8f0; border-top-color: #111; border-radius: 50%; animation: o100-spin 0.8s linear infinite; }
		@keyframes o100-spin { to { transform: rotate(360deg); } }
		
		/* Typography */
		.o100-product-title { margin: 0 0 6px 0; font-size: 16px; font-weight: 700; color: #000; line-height: 1.3; }
		.o100-product-desc { color: #555; font-size: 14px; margin-bottom: 12px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
		.o100-product-price { font-weight: 600; font-size: 15px; color: #000; margin-top: auto; }
		
		/* Food Labels — Circular Icon Badges */
		.o100-food-labels-container { position: absolute; top: 8px; right: 8px; display: flex; flex-direction: column; gap: 5px; z-index: 5; }
		.o100-food-label-item { position: relative; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 4px rgba(0,0,0,0.15); border: 1.5px solid var(--o100-theme-color, #ff5722); cursor: pointer; transition: transform 0.2s ease; }
		.o100-food-label-item:hover { transform: scale(1.15); }
		.o100-label-icon { width: 14px; height: 14px; object-fit: contain; display: block; pointer-events: none; }
		/* Tooltip */
		.o100-food-label-item::after { content: attr(title); position: absolute; right: calc(100% + 8px); top: 50%; transform: translateY(-50%); background: #1a1a1a; color: #fff; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 6px; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
		.o100-food-label-item:hover::after { opacity: 1; }
		

		/* Restricted Products */
		.o100-product-restricted { opacity: 0.85; pointer-events: none; position: relative; }
		.o100-product-restricted .o100-product-img { position: relative; }
		.o100-product-restricted .o100-product-img::after { content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.45); z-index: 1; }
		.o100-product-restricted .o100-product-img img { filter: grayscale(40%); }
		.o100-product-restricted .o100-product-title, .o100-product-restricted .o100-product-desc, .o100-product-restricted .o100-product-price { color: #94a3b8; }
		.o100-restriction-badge { display: block; font-size: 11px; font-weight: 700; color: #e87561; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

		@media (min-width: 1025px) {
			.o100-product-grid { grid-template-columns: repeat(' . esc_html($cols) . ', 1fr); }
            .o100-layout-carousel .o100-product-card { flex: 0 0 calc( (100% - (' . esc_html($cols - 1) . ' * 16px)) / ' . esc_html($cols) . ' ); }
		}
        @media (min-width: 768px) and (max-width: 1024px) {
			.o100-product-grid { grid-template-columns: repeat(' . esc_html($cols_tablet) . ', 1fr); }
            .o100-layout-carousel .o100-product-card { flex: 0 0 ' . $carousel_tablet_flex . '; }
		}
		@media (max-width: 767px) {
			.o100-product-grid { grid-template-columns: repeat(' . esc_html($cols_mobile) . ', 1fr); gap: 12px; }
			.o100-layout-carousel .o100-product-card { flex: 0 0 ' . $carousel_mobile_flex . '; }
			/* On Mobile, List Layout turns into divider lines without border */
			.o100-layout-list .o100-product-card { border: none; border-bottom: 1px solid #eee; border-radius: 0; padding: 16px 0; flex-direction: row; }
			.o100-layout-list .o100-product-card:last-child { border-bottom: none; }
			.o100-layout-list .o100-product-image-wrap { width: 110px; border-radius: 8px; }
            .o100-layout-list .o100-product-image { min-height: 110px; }
            .o100-layout-list .o100-product-info { padding: 0 0 0 16px; }
		}

		/* --- LEFT SIDEBAR LAYOUT --- */
		@media (min-width: 1025px) {
			.o100-layout-sidebar .o100-menu-container { display: grid; grid-template-columns: 240px 1fr; gap: 40px; align-items: start; }
			.o100-layout-sidebar .o100-menu-top-row { grid-column: 1 / -1; }
			.o100-layout-sidebar .o100-menu-header { position: sticky !important; top: 20px !important; grid-column: 1; border-bottom: none !important; margin-bottom: 0 !important; padding: 0 !important; }
			.o100-layout-sidebar .o100-nav-inner { flex-direction: column; align-items: flex-start !important; }
			.o100-layout-sidebar .o100-category-nav-wrapper { flex-direction: column; align-items: flex-start !important; width: 100%; white-space: normal; }
			.o100-layout-sidebar a.o100-cat-link { width: 100%; padding: 12px 16px !important; margin-right: 0 !important; border-bottom: none !important; border-left: 3px solid transparent !important; white-space: normal !important; text-align: left; }
			.o100-layout-sidebar a.o100-cat-link.active { border-bottom: none !important; border-left-color: #000 !important; background: #f8fafc; border-radius: 0 6px 6px 0; }
			.o100-layout-sidebar .o100-menu-sections, .o100-layout-sidebar .o100-deals-section { grid-column: 2; }
			.o100-layout-sidebar .o100-list-icon, .o100-layout-sidebar .o100-nav-arrow { display: none !important; }
		}
		</style>';
	}

	/**
	 * Render a single product card
	 */
	private static function render_product_card( $product, $layout_class, $settings = array() ) {
		$img_url = get_the_post_thumbnail_url( $product->get_id(), 'medium_large' );
		$enable_modal = (isset($settings['enable_modal']) && $settings['enable_modal'] !== '') ? $settings['enable_modal'] : 'yes';
		
		$restriction = false;
		if ( class_exists( 'O100_Menu_Rules' ) ) {
			$restriction = O100_Menu_Rules::check_product_restriction( $product->get_id() );
			if ( $restriction ) {
				if ( $restriction['type'] === 'ecom' ) {
					return; // Completely hide if eCommerce mode is disabled
				}
				$enable_modal = 'no'; // Disable modal if restricted by date/method
			}
		}

		if ( ! $restriction && ! $product->is_in_stock() ) {
			$restriction = array(
				'type'    => 'stock',
				'message' => __( 'Sold Out', 'order100' )
			);
			$enable_modal = 'no';
		}
		
		$product_link = $product->get_permalink();
		
		$card_classes = 'o100-product-card ' . ($img_url ? '' : 'o100-no-image');
		if ( $restriction ) {
			$card_classes .= ' o100-product-restricted';
		}

		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		$cat_ids_str = '';
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$term_ids = array();
			foreach ( $terms as $term ) {
				$term_ids[] = $term->term_id;
			}
			$cat_ids_str = implode(',', $term_ids);
		}
		
		echo '<div class="' . esc_attr( $card_classes ) . '" data-product-id="' . esc_attr( $product->get_id() ) . '" data-cats="' . esc_attr( $cat_ids_str ) . '" data-enable-modal="' . esc_attr( $enable_modal ) . '" data-product-link="' . esc_url( $product_link ) . '">';
		
		if ( $img_url ) {
			echo '<div class="o100-product-image-wrap">';
			echo '<div class="o100-product-image" style="background-image: url(\'' . esc_url( $img_url ) . '\');">';
			echo O100_Public::get_food_labels_html( $product->get_id() );
			echo '</div>';
			echo '</div>';
		}
		
		echo '<div class="o100-product-info">';
		echo '<div class="o100-product-details">';
		echo '<h3 class="o100-product-title">' . esc_html( $product->get_name() ) . '</h3>';
		
		$desc = wp_trim_words( $product->get_short_description(), 12, '...' );
		if ( $desc ) {
			echo '<p class="o100-product-desc">' . esc_html( $desc ) . '</p>';
		}
		
		if ( $restriction ) {
			echo '<p class="o100-product-price"><span style="font-weight:400;">' . wp_kses_post( $product->get_price_html() ) . '</span> <span class="o100-restriction-badge">' . esc_html( $restriction['message'] ) . '</span></p>';
		} else {
			echo '<p class="o100-product-price">' . wp_kses_post( $product->get_price_html() ) . '</p>';
			
			if ( class_exists('O100_Menu_Rules') ) {
				$time_badge = O100_Menu_Rules::get_product_time_badge( $product->get_id() );
				if ( $time_badge ) {
					echo '<p class="o100-flexible-badge" style="font-size:12px; color:#c2410c; margin-top:2px; display:flex; align-items:center;"><i class="dashicons dashicons-clock" style="font-size:14px; line-height:1.2; width:14px; height:14px; margin-right:3px;"></i>' . esc_html( $time_badge ) . '</p>';
				}
			}
		}
		echo '</div>';
		
		if ( ! $restriction && ( ! isset( $settings['hide_atc'] ) || $settings['hide_atc'] !== 'yes' ) ) {
			echo '<div class="o100-add-btn-wrapper">';
			$has_legacy_addons = get_post_meta( $product->get_id(), '_product_addons', true ) || get_post_meta( $product->get_id(), 'tm_meta', true );
			$has_required_o100_addons = false;
			if ( class_exists( 'O100_Product_Addons_Frontend' ) ) {
				$options = O100_Product_Addons_Frontend::instance()->get_product_options( $product->get_id() );
				if ( ! empty( $options ) ) {
					foreach ( $options as $group ) {
						if ( isset( $group['_required'] ) && $group['_required'] === 'yes' ) {
							$has_required_o100_addons = true;
							break;
						}
					}
				}
			}
			$has_addons = $has_legacy_addons || $has_required_o100_addons;

			if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() && empty( $has_addons ) ) {
				echo '<a href="?add-to-cart=' . esc_attr( $product->get_id() ) . '" data-quantity="1" class="o100-add-btn o100-native-add-to-cart" data-product_id="' . esc_attr( $product->get_id() ) . '" data-product_sku="' . esc_attr( $product->get_sku() ) . '" aria-label="Add &ldquo;' . esc_attr( $product->get_name() ) . '&rdquo; to your cart" rel="nofollow"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></a>';
			} else {
				echo '<a href="' . esc_url( $product->get_permalink() ) . '" class="o100-add-btn o100-view-options-btn" aria-label="Select options for &ldquo;' . esc_attr( $product->get_name() ) . '&rdquo;" rel="nofollow"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></a>';
			}
			echo '</div>';
		}
		
		echo '</div>'; // End info
		echo '</div>'; // End card
	}

	/**
	 * Build query arguments from shortcode settings
	 */
	private static function build_product_query( $settings ) {
		$orderby = isset($settings['orderby']) ? $settings['orderby'] : 'date';
		$meta_key = '';
		
		if ( $orderby === 'order_field' ) {
			$orderby = 'meta_value_num';
			$meta_key = 'exwoofood_order';
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'order'          => isset($settings['order']) ? $settings['order'] : 'DESC',
			'orderby'        => $orderby,
		);

		if ( isset( $settings['posts_per_page'] ) && $settings['posts_per_page'] !== '' ) {
			$args['posts_per_page'] = intval( $settings['posts_per_page'] );
			$args['paged'] = max( 1, get_query_var('paged'), get_query_var('page') );
		} elseif ( isset( $settings['count'] ) && $settings['count'] !== '' && $settings['count'] !== '-1' ) {
			$args['posts_per_page'] = intval( $settings['count'] );
		} else {
			$args['posts_per_page'] = -1;
		}

		if ( $meta_key ) {
			$args['meta_key'] = $meta_key;
		}

		// Handle Specific IDs
		$ids = isset($settings['ids']) ? trim($settings['ids']) : '';
		if ( ! empty($ids) ) {
			$args['post__in'] = array_map('intval', explode(',', $ids));
		}

		// Handle Featured Only
		if ( isset($settings['featured']) && ($settings['featured'] === '1' || $settings['featured'] === 'yes') ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => 'featured',
				'operator' => 'IN',
			);
		}

		// Handle Category Filter
		$cat_input = isset($settings['categories']) ? $settings['categories'] : (isset($settings['cat']) ? $settings['cat'] : '');
		// If $cat_input is an array (stored via our new UI), convert to string
		if ( is_array( $cat_input ) ) {
			$cat_input = implode( ',', $cat_input );
		}
		if ( ! empty($cat_input) ) {
			$cat_raw = array_map( 'trim', explode( ',', $cat_input ) );
			$cat_ids = array();
			$cat_slugs = array();
			foreach ( $cat_raw as $c ) {
				if ( is_numeric( $c ) ) {
					$cat_ids[] = intval( $c );
				} else {
					$cat_slugs[] = $c;
				}
			}

			if ( ! empty($cat_ids) || ! empty($cat_slugs) ) {
				$tax_query = array( 'relation' => 'OR' );
				if ( ! empty($cat_ids) ) {
					$tax_query[] = array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cat_ids );
				}
				if ( ! empty($cat_slugs) ) {
					$tax_query[] = array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cat_slugs );
				}
				$args['tax_query'][] = $tax_query;
			}
		}

		// Handle Tag Filter
		$tag_input = isset($settings['tags']) ? $settings['tags'] : '';
		if ( is_array( $tag_input ) ) {
			$tag_input = implode( ',', $tag_input );
		}
		if ( ! empty( $tag_input ) ) {
			$tag_raw = array_map( 'trim', explode( ',', $tag_input ) );
			$tag_ids = array();
			$tag_slugs = array();
			foreach ( $tag_raw as $t ) {
				if ( is_numeric( $t ) ) {
					$tag_ids[] = intval( $t );
				} else {
					$tag_slugs[] = $t;
				}
			}

			if ( ! empty($tag_ids) || ! empty($tag_slugs) ) {
				$tax_query = array( 'relation' => 'OR' );
				if ( ! empty($tag_ids) ) {
					$tax_query[] = array( 'taxonomy' => 'product_tag', 'field' => 'term_id', 'terms' => $tag_ids );
				}
				if ( ! empty($tag_slugs) ) {
					$tax_query[] = array( 'taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => $tag_slugs );
				}
				$args['tax_query'][] = $tax_query;
			}
		}

		// Ensure tax_query has relation AND if there are multiple elements (e.g. Featured AND Cat AND Tag)
		if ( isset( $args['tax_query'] ) && count( $args['tax_query'] ) > 1 ) {
			$args['tax_query']['relation'] = 'AND';
		}

		return new WP_Query( $args );
	}

	/**
	 * Render standalone layouts: grid, list, table, carousel
	 */
	private static function render_standalone_layout( $settings, $type ) {
		$cols = isset($settings['columns']) ? intval($settings['columns']) : (isset($settings['column']) ? intval($settings['column']) : 3);
        $cols_tablet = isset($settings['column_tablet']) ? intval($settings['column_tablet']) : 2;
        $cols_mobile = isset($settings['column_mobile']) ? intval($settings['column_mobile']) : 1;
		if ($cols < 1) $cols = 3;
        if ($cols_tablet < 1) $cols_tablet = 2;
        if ($cols_mobile < 1) $cols_mobile = 1;

		// Map type to layout class
		$layout_class = 'o100-layout-grid'; // Default
		if ( $type === 'list' || $type === 'table' ) {
			$layout_class = 'o100-layout-list';
		} elseif ( $type === 'carousel' ) {
			$layout_class = 'o100-layout-carousel';
		}
		
		$header_style = isset($settings['style']) ? $settings['style'] : '1';

		echo '<div class="o100-menu-container o100-standalone o100-style-' . esc_attr($header_style) . '">';
		self::render_shared_styles($cols, $cols_tablet, $cols_mobile, $header_style);

		$products = self::build_product_query( $settings );

		if ( ! $products->have_posts() ) {
			echo '<p>No products found for this query.</p></div>';
			return;
		}

		// Photos First sorting
		if ( ! empty( $settings['photos_first'] ) && $settings['photos_first'] === 'yes' ) {
			self::sort_photos_first( $products );
		}

		// Top Row: Search Bar
		echo '<div class="o100-menu-top-row" style="margin-bottom: 16px;">';
		if ( isset($settings['enable_search']) && $settings['enable_search'] === 'yes' ) {
			echo '<div class="o100-search-container">';
			echo '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
			echo '<input type="text" class="o100-search-input" placeholder="' . esc_attr__( 'Search for a product...', 'order100' ) . '">';
			echo '</div>';
		}
		echo '</div>'; // End Top Row

		echo '<div class="o100-menu-sections ' . esc_attr($layout_class) . '">';
		
		// Render Standalone Filter
		if ( isset( $settings['menu_filter'] ) && $settings['menu_filter'] === 'show' ) {
			$raw_categories = self::get_filtered_categories( $settings );
			
			// Only show categories that are actually present in the current product query
			$active_cat_ids = array();
			foreach ( $products->posts as $p ) {
				$terms = get_the_terms( $p->ID, 'product_cat' );
				if ( ! empty($terms) && ! is_wp_error($terms) ) {
					foreach( $terms as $t ) {
						$active_cat_ids[ $t->term_id ] = true;
					}
				}
			}
			$categories = array();
			if ( ! empty( $raw_categories ) && ! is_wp_error( $raw_categories ) ) {
				foreach ( $raw_categories as $cat ) {
					if ( isset( $active_cat_ids[ $cat->term_id ] ) ) {
						// Recalculate count specifically for this queried view
						$count = 0;
						foreach ( $products->posts as $p ) {
							$p_terms = get_the_terms( $p->ID, 'product_cat' );
							if ( ! empty($p_terms) && ! is_wp_error($p_terms) ) {
								foreach( $p_terms as $pt ) {
									if ( $pt->term_id === $cat->term_id ) {
										$count++;
										break;
									}
								}
							}
						}
						$cat->dynamic_count = $count;
						$categories[] = $cat;
					}
				}
			}

			if ( ! empty( $categories ) ) {
				echo '<div class="o100-menu-header" style="margin-bottom: 20px;">';
				echo '<div class="o100-nav-inner">';
				echo '<div class="o100-category-nav-wrapper o100-standalone-filter" style="width: 100%;">';
				echo '<a href="#" class="o100-cat-link active" data-filter="all">' . esc_html__( 'All', 'order100' ) . '</a>';
				foreach ( $categories as $cat ) {
					$display_count = isset($cat->dynamic_count) ? $cat->dynamic_count : $cat->count;
					$count_text = ( isset( $settings['show_count'] ) && $settings['show_count'] === 'yes' ) ? ' <span class="o100-cat-count" style="opacity:0.6; font-size:0.9em; margin-left:4px;">(' . esc_html( $display_count ) . ')</span>' : '';
					echo '<a href="#" class="o100-cat-link" data-filter="' . esc_attr( $cat->term_id ) . '">' . esc_html( $cat->name ) . wp_kses_post( $count_text ) . '</a>';
				}
				echo '</div>';
				echo '</div>';
				echo '</div>';
				
				echo '<script>
				document.addEventListener("DOMContentLoaded", function() {
					var wrappers = document.querySelectorAll(".o100-standalone-filter");
					wrappers.forEach(function(wrapper) {
						var links = wrapper.querySelectorAll(".o100-cat-link");
						var container = wrapper.closest(".o100-standalone");
						var products = container.querySelectorAll(".o100-product-card");
						
						links.forEach(function(link) {
							link.addEventListener("click", function(e) {
								e.preventDefault();
								links.forEach(function(l) { l.classList.remove("active"); });
								this.classList.add("active");
								
								var filter = this.getAttribute("data-filter");
								products.forEach(function(p) {
									if (filter === "all") {
										p.style.display = "";
									} else {
										var cats = p.getAttribute("data-cats");
										if (cats && cats.split(",").indexOf(filter) !== -1) {
											p.style.display = "";
										} else {
											p.style.display = "none";
										}
									}
								});
							});
						});
					});
				});
				</script>';
			}
		}

		echo '<div class="o100-menu-section">'; // Use the section wrapper
		echo '<div class="o100-product-grid">';
		
		while ( $products->have_posts() ) {
			$products->the_post();
			global $product;
			self::render_product_card( $product, $layout_class, $settings );
		}
		
		echo '</div></div></div>'; // End grid, section, sections
		
		// Render Pagination
		if ( isset( $settings['page_navi'] ) && $settings['page_navi'] !== '' ) {
			$total_pages = $products->max_num_pages;
			if ( $total_pages > 1 ) {
				$current_page = max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) );
				echo '<div class="o100-pagination" style="margin-top: 30px; text-align: center;">';
				echo paginate_links( array(
					'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
					'format'    => '?paged=%#%',
					'current'   => $current_page,
					'total'     => $total_pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'type'      => 'list',
				) );
				echo '</div>';
				
				// Standard Pagination Styling
				echo '<style>
				.o100-pagination ul { display: inline-flex; list-style: none; padding: 0; margin: 0; gap: 8px; }
				.o100-pagination li a, .o100-pagination li span { display: flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 8px; font-size: 14px; font-weight: 600; color: #111; background: #f2f2f2; text-decoration: none; transition: all 0.2s; }
				.o100-pagination li a:hover { background: #e8e8e8; }
				.o100-pagination li span.current { background: #111; color: #fff; }
				</style>';
			}
		}

		self::render_menu_scripts();
		echo '</div>'; // End container
		wp_reset_postdata();
	}

	/**
	 * Render the modern DoorDash-style Menu Group
	 */
	private static function render_menu_group( $settings ) {
		$raw_categories = self::get_filtered_categories( $settings );

		// Pre-fetch products to filter out completely empty categories efficiently
		$valid_categories = array();
		$cat_products = array();
		
		if ( ! empty( $raw_categories ) && ! is_wp_error( $raw_categories ) ) {
			foreach ( $raw_categories as $cat ) {
				$products = self::get_products_for_category( $cat->term_id, $settings );
				if ( $products->have_posts() ) {
					$valid_categories[] = $cat;
					$cat_products[ $cat->term_id ] = $products;
				}
			}
		}

		$categories = $valid_categories;

		if ( empty( $categories ) ) {
			echo '<p>No categories or products found for this menu.</p>';
			return;
		}

		$cols = isset($settings['columns']) ? intval($settings['columns']) : (isset($settings['column']) ? intval($settings['column']) : 2);
        $cols_tablet = isset($settings['column_tablet']) ? intval($settings['column_tablet']) : 2;
        $cols_mobile = isset($settings['column_mobile']) ? intval($settings['column_mobile']) : 1;
		if ($cols < 1) $cols = 2;
        if ($cols_tablet < 1) $cols_tablet = 2;
        if ($cols_mobile < 1) $cols_mobile = 1;
		
		$inner_layout = isset($settings['sc_layout']) ? strtolower($settings['sc_layout']) : (isset($settings['inner_layout']) ? strtolower($settings['inner_layout']) : 'list');
		$is_list = ($inner_layout === 'list');
		$layout_class = $is_list ? 'o100-layout-list' : 'o100-layout-grid';
		
		$header_style = isset($settings['style']) ? $settings['style'] : '1';
		$heading_style = isset($settings['sc_heading']) && $settings['sc_heading'] !== '' ? $settings['sc_heading'] : '';
		$heading_align = isset($settings['heading_align']) && $settings['heading_align'] !== '' ? $settings['heading_align'] : 'left';
		
		$container_classes = 'o100-menu-container o100-style-' . esc_attr($header_style);
		$container_classes .= ' o100-heading-align-' . esc_attr($heading_align);
		if ( $heading_style ) {
			$container_classes .= ' o100-heading-style-' . esc_attr($heading_style);
		}

		echo '<div class="' . esc_attr($container_classes) . '">';
		self::render_shared_styles($cols, $cols_tablet, $cols_mobile, $header_style);

		// Top Row: Banner & Search
		echo '<div class="o100-menu-top-row" style="margin-bottom: 16px;">';
		
		$active_deals = [];
		$banner_text = '';
		if (class_exists('O100_Promotions_Frontend')) {
			$active_deals = O100_Promotions_Frontend::get_active_deals();
			if (!empty($active_deals)) {
				foreach ($active_deals as $deal) {
					$config = json_decode( $deal['action_config'], true );
					if (isset($config['display']) && !empty($config['display']['banner_text'])) {
						$banner_text = $config['display']['banner_text'];
						break;
					}
				}
			}
		}

		if ($banner_text) {
			echo '<div style="background: #111; color: #fff; text-align: center; padding: 10px 16px; font-size: 14px; font-weight: 600; border-radius: 8px; margin-bottom: 16px;">' . esc_html($banner_text) . '</div>';
		}

		// Search Box
		if ( ! isset( $settings['enable_search'] ) || $settings['enable_search'] !== 'no' ) {
			echo '<div class="o100-search-container" style="width: 100%; max-width: 500px; margin-bottom: 0 !important;">';
			echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; color: #111;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
			echo '<input type="text" class="o100-search-input" placeholder="Search store menu" style="width: 100%; padding: 12px 16px 12px 48px; border-radius: 50px; border: none; background: #f2f2f2; font-size: 16px; outline: none; color: #111; font-weight: 500;">';
			echo '</div>';
		}

		echo '</div>'; // End Top Row

		// Sticky Category Header
		echo '<div class="o100-menu-header">';
		echo '<div class="o100-nav-inner">';
		// List Icon (Menu)
		echo '<div class="o100-list-icon" id="o100-drawer-toggle" style="cursor:pointer;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg></div>';

		// Sidebar Drawer
		echo '<div class="o100-drawer-overlay" id="o100-drawer-overlay"></div>';
		echo '<div class="o100-drawer" id="o100-drawer">';
		echo '<div class="o100-drawer-header"><h3>Menu</h3><button class="o100-drawer-close" id="o100-drawer-close"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button></div>';
		echo '<ul class="o100-drawer-list">';
		foreach ( $categories as $cat ) {
			echo '<li><a href="#o100-cat-' . esc_attr( $cat->term_id ) . '" class="o100-drawer-link" data-target="o100-cat-' . esc_attr( $cat->term_id ) . '">' . esc_html( $cat->name ) . '</a></li>';
		}
		echo '</ul>';
		echo '</div>';
		
		// Nav Arrows for Desktop
		echo '<div class="o100-nav-arrow o100-nav-arrow-left" style="display:none;"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/></svg></div>';
		
		// Category Nav Wrapper
		echo '<div class="o100-category-nav-wrapper">';
		$is_first = true;
		foreach ( $categories as $cat ) {
			$active_class = $is_first ? ' active' : '';
			$count_text   = ( isset( $settings['show_count'] ) && $settings['show_count'] === 'yes' ) ? ' <span class="o100-cat-count" style="opacity:0.6; font-size:0.9em; margin-left:4px;">(' . esc_html( $cat->count ) . ')</span>' : '';
			echo '<a href="#o100-cat-' . esc_attr( $cat->term_id ) . '" class="o100-cat-link' . $active_class . '" data-target="o100-cat-' . esc_attr( $cat->term_id ) . '">' . esc_html( $cat->name ) . wp_kses_post( $count_text ) . '</a>';
			$is_first = false;
		}
		echo '</div>'; // End Nav Wrapper
		
		echo '<div class="o100-nav-arrow o100-nav-arrow-right" style="display:none;"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg></div>';
		
		echo '</div>'; // End Nav Inner
		echo '</div>'; // End Header

		// Deals Section
		if (!empty($active_deals)) {
			$options = get_option( 'o100_options', [] );
			$color = ! empty( $options['o100_main_color'] ) ? $options['o100_main_color'] : '#e11d48';

			echo '<div class="o100-menu-section o100-deals-section" style="margin-bottom: 40px;">';
			echo '<h2 style="font-size: 24px; font-weight: 700; color: #000; margin: 0 0 20px 0; letter-spacing: -0.5px;">Deals & offers</h2>';
			
			$is_slider = count($active_deals) > 1;
			
			echo '<div class="o100-deals-slider-container" id="o100DealsSliderContainer">';
			
			if ($is_slider) {
				echo '<button class="o100-deals-nav prev" id="o100DealsPrev">&#10094;</button>';
			}
			
			echo '<div class="o100-deals-carousel-wrapper" id="o100DealsCarouselWrapper">';
			echo '<div class="o100-deals-carousel">';
			
			foreach ($active_deals as $deal) {
				$config = json_decode( $deal['action_config'], true );
				
				// Dynamically compute the main label based on discount configuration
				$label = $deal['title'];
				if ($deal['rule_type'] === 'simple') {
					if (isset($config['discount_type']) && $config['discount_type'] === 'percentage') {
						$label = floatval($config['discount_value']) . '% OFF';
					} elseif (isset($config['discount_type']) && in_array($config['discount_type'], ['fixed_cart', 'fixed_product'])) {
						$label = strip_tags(wc_price(floatval($config['discount_value']))) . ' OFF';
					}
				} elseif ($deal['rule_type'] === 'bogo') {
					$label = 'Buy 1 Get 1'; 
				}

				// Subtitle is the promotion name
				$sub = $deal['title'];

				// Default icon based on rule type
				$icon_svg = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>';
				if ($deal['rule_type'] === 'bogo') {
					$icon_svg = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>';
				}

				$image_url = isset($config['display']) && !empty($config['display']['image']) ? $config['display']['image'] : '';

				$popup_id = 'o100-deal-popup-' . intval($deal['id']);
				
				echo '<div class="o100-deal-card" data-popup-id="' . esc_attr($popup_id) . '">';
				echo '<div class="o100-deal-card-left">';
				echo '<div class="o100-deal-card-title" style="color: ' . esc_attr($color) . ';">' . esc_html($label) . '</div>';
				echo '<div class="o100-deal-card-sub">' . esc_html($sub) . '</div>';
				echo '</div>'; // end left
				
				echo '<div class="o100-deal-card-right">';
				if ($image_url) {
					echo '<img src="' . esc_url($image_url) . '" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">';
				} else {
					echo $icon_svg;
				}
				echo '</div>'; // end right
				echo '</div>'; // end card
			}
			echo '</div>'; // end carousel
			echo '</div>'; // end wrapper
			
			if ($is_slider) {
				echo '<button class="o100-deals-nav next" id="o100DealsNext">&#10095;</button>';
			}
			echo '</div>'; // end slider container
			
			echo '<style>
				.o100-deals-slider-container {
					position: relative;
					width: 100%;
				}
				.o100-deals-carousel-wrapper {
					width: 100%;
					overflow-x: auto;
					padding: 16px 12px 24px 12px;
					margin: -16px -12px -24px -12px;
					scrollbar-width: none; /* Firefox */
					scroll-behavior: smooth;
				}
				.o100-deals-carousel-wrapper::-webkit-scrollbar { display: none; }
				
				.o100-deals-carousel {
					display: flex;
					gap: 16px;
					width: max-content;
				}
				
				.o100-deals-nav {
					position: absolute;
					top: calc(50% - 4px);
					transform: translateY(-50%);
					width: 36px;
					height: 36px;
					background: #fff;
					border-radius: 50%;
					box-shadow: 0 4px 12px rgba(0,0,0,0.15);
					z-index: 10;
					cursor: pointer;
					border: 1px solid rgba(0,0,0,0.05);
					display: none; /* hidden on PC by default if it fits */
					align-items: center;
					justify-content: center;
					color: #475569;
					font-size: 16px;
					font-weight: bold;
					transition: all 0.2s;
					user-select: none;
				}
				.o100-deals-nav:hover {
					background: #f8fafc;
					color: #0f172a;
					transform: translateY(-50%) scale(1.05);
				}
				.o100-deals-nav.prev { left: -10px; }
				.o100-deals-nav.next { right: -10px; }
				
				.o100-deal-card {
					position: relative;
					width: 260px;
					min-height: 90px;
					height: auto;
					background: radial-gradient(circle at 100% 50%, transparent 12px, #fff 12.5px);
					filter: drop-shadow(0 0 2px rgba(0,0,0,0.08)) drop-shadow(0 4px 12px rgba(0,0,0,0.08));
					border-radius: 12px;
					display: flex;
					cursor: pointer;
					transition: transform 0.2s ease, filter 0.2s ease;
					flex: 0 0 auto;
					scroll-snap-align: center;
				}
				.o100-deal-card:hover {
					transform: translateY(-2px);
					filter: drop-shadow(0 0 2px rgba(0,0,0,0.08)) drop-shadow(0 8px 16px rgba(0,0,0,0.12));
				}
				
				.o100-deal-card-left {
					flex: 1;
					padding: 12px 16px; /* slightly smaller top/bottom padding to fit more */
					border-right: 2px dashed #f1f5f9;
					display: flex;
					flex-direction: column;
					justify-content: center;
					overflow: hidden;
				}
				.o100-deal-card-title {
					font-size: 18px;
					font-weight: 800;
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
					line-height: 1.2;
				}
				.o100-deal-card-sub {
					font-size: 13px;
					color: #64748b;
					margin-top: 4px;
					font-weight: 500;
					display: -webkit-box;
					-webkit-line-clamp: 2;
					-webkit-box-orient: vertical;
					overflow: hidden;
					line-height: 1.3;
				}
				.o100-deal-card-right {
					width: 70px;
					display: flex;
					align-items: center;
					justify-content: center;
					padding-right: 8px; /* account for notch */
				}
				
				@media (max-width: 640px) {
					.o100-deals-nav { display: flex; }
					.o100-deals-nav.prev { left: 0px; }
					.o100-deals-nav.next { right: 0px; }
					.o100-deals-carousel {
						padding: 0 15vw !important; /* Center the first card with 15vw margin */
						gap: 16px; /* Restore standard gap for a modern peeking look */
					}
					.o100-deal-card { 
						width: 70vw !important; /* Restrict width so it does not look stretched */
						max-width: none !important;
						scroll-snap-align: center;
					}
					.o100-deals-carousel-wrapper {
						scroll-snap-type: x mandatory;
					}
				}
			</style>';
			echo '</div>'; // End Deals section

			// Render Popups for unique deals only
			foreach ($active_deals as $deal) {
				self::render_deal_popup($deal, $settings, $color);
			}

			// Inject JS to handle click and Share
			echo '<script>
			(function() {
				var initDeals = function() {
					var cards = document.querySelectorAll(".o100-deal-card");
					cards.forEach(function(card) {
						// Only attach once
						if (card.dataset.dealInit) return;
						card.dataset.dealInit = "true";
						card.addEventListener("click", function() {
							var popupId = this.getAttribute("data-popup-id");
							var popup = document.getElementById(popupId);
							if (popup) {
								popup.classList.add("active");
								document.body.style.overflow = "hidden"; // prevent background scroll
							}
						});
					});
					
					window.o100ShareDeal = function(btn) {
						var url = btn.getAttribute("data-url");
						var title = btn.getAttribute("data-title");
						if (navigator.share) {
							navigator.share({
								title: title,
								url: url
							}).catch(console.error);
						} else {
							navigator.clipboard.writeText(url).then(function() {
								var oldText = btn.innerHTML;
								btn.innerHTML = "Copied!";
								setTimeout(function(){ btn.innerHTML = oldText; }, 2000);
							});
						}
					};

					// Carousel Logic
					var wrappers = document.querySelectorAll(".o100-deals-carousel-wrapper");
					wrappers.forEach(function(wrapper) {
						if (wrapper.dataset.carouselInit) return;
						wrapper.dataset.carouselInit = "true";
						
						var container = wrapper.closest(".o100-deals-slider-container");
						if (!container) return;
						
						var btnPrev = container.querySelector(".o100-deals-nav.prev");
						var btnNext = container.querySelector(".o100-deals-nav.next");
						var autoPlayInterval;
						var isAnimating = false;
						
						function getCardWidth() {
							var card = wrapper.querySelector(".o100-deal-card");
							return card ? (card.offsetWidth + 16) : 276; // width + gap
						}
						
						function scrollCarousel(dir) {
							if (isAnimating) return;
							var w = getCardWidth();
							// Only loop if there are enough cards
							if (wrapper.children[0].children.length <= 1) return;
							
							var carouselInner = wrapper.querySelector(".o100-deals-carousel");
							isAnimating = true;
							
							if (dir === 1) {
								wrapper.style.scrollBehavior = "smooth";
								wrapper.style.scrollSnapType = "none";
								wrapper.scrollBy({ left: w });
								
								setTimeout(function() {
									wrapper.style.scrollBehavior = "auto";
									var first = carouselInner.firstElementChild;
									carouselInner.appendChild(first);
									wrapper.scrollLeft -= w;
									wrapper.style.scrollSnapType = "x mandatory";
									isAnimating = false;
								}, 400);
							} else {
								wrapper.style.scrollBehavior = "auto";
								wrapper.style.scrollSnapType = "none";
								var last = carouselInner.lastElementChild;
								carouselInner.prepend(last);
								wrapper.scrollLeft += w;
								
								void wrapper.offsetWidth; // force reflow
								
								wrapper.style.scrollBehavior = "smooth";
								wrapper.scrollBy({ left: -w });
								
								setTimeout(function() {
									wrapper.style.scrollSnapType = "x mandatory";
									isAnimating = false;
								}, 400);
							}
							resetAutoPlay();
						}
						
						if (btnPrev) btnPrev.addEventListener("click", function() { scrollCarousel(-1); });
						if (btnNext) btnNext.addEventListener("click", function() { scrollCarousel(1); });
						
						function startAutoPlay() {
							if (window.innerWidth <= 640 && wrapper.querySelectorAll(".o100-deal-card").length > 1) {
								autoPlayInterval = setInterval(function() {
									scrollCarousel(1);
								}, 3000);
							}
						}
						
						function resetAutoPlay() {
							clearInterval(autoPlayInterval);
							startAutoPlay();
						}
						
						// Pause on hover/touch
						wrapper.addEventListener("mouseenter", function() { clearInterval(autoPlayInterval); });
						wrapper.addEventListener("mouseleave", startAutoPlay);
						wrapper.addEventListener("touchstart", function() { clearInterval(autoPlayInterval); }, {passive: true});
						wrapper.addEventListener("touchend", startAutoPlay, {passive: true});
						
						startAutoPlay();
						window.addEventListener("resize", function() {
							clearInterval(autoPlayInterval);
							startAutoPlay();
						});
					});
				};

				if (document.readyState === "loading") {
					document.addEventListener("DOMContentLoaded", initDeals);
				} else {
					initDeals();
				}
			})();
			</script>';
		}

		// Menu Sections
		echo '<div class="o100-menu-sections ' . esc_attr( $layout_class ) . '">';
		foreach ( $categories as $cat ) {
			$products = $cat_products[ $cat->term_id ];

			// Photos First sorting per category
			if ( ! empty( $settings['photos_first'] ) && $settings['photos_first'] === 'yes' ) {
				self::sort_photos_first( $products );
			}

			echo '<div id="o100-cat-' . esc_attr( $cat->term_id ) . '" class="o100-menu-section" style="margin-bottom: 40px; scroll-margin-top: 80px;">';
			echo '<h2>' . esc_html( $cat->name ) . '</h2>';
			
			echo '<div class="o100-product-grid">';
			while ( $products->have_posts() ) {
				$products->the_post();
				global $product;
				self::render_product_card( $product, $layout_class, $settings );
			}
			echo '</div>'; // End product grid
			echo '</div>'; // End section
			wp_reset_postdata();
		}
		echo '</div>'; // End sections wrapper

		self::render_menu_scripts();
		echo '</div>'; // End Main Container
	}

	private static function get_deal_requirements($deal, $config) {
		$reqs = [];
		if (!empty($deal['promo_code'])) {
			$reqs[] = 'Code: ' . strtoupper($deal['promo_code']);
		}
		if (isset($config['min_order']) && floatval($config['min_order']) > 0) {
			$reqs[] = 'Min. order: ' . strip_tags(wc_price(floatval($config['min_order'])));
		}
		if (!empty($deal['conditions'])) {
			$conditions = json_decode($deal['conditions'], true);
			if (is_array($conditions)) {
				foreach ($conditions as $cond) {
					if (!empty($cond['type']) && $cond['type'] === 'first_order') {
						if (isset($cond['operator']) && $cond['operator'] === 'yes') {
							$reqs[] = 'First order only';
						}
					}
				}
			}
		}
		return $reqs;
	}

	private static function render_deal_popup($deal, $settings, $color) {
		$config = json_decode( $deal['action_config'], true );
		$popup_id = 'o100-deal-popup-' . intval($deal['id']);
		
		$title = $deal['title'];
		if ($deal['rule_type'] === 'simple') {
			if (isset($config['discount_type']) && in_array($config['discount_type'], ['percentage', 'percent'])) {
				$title = floatval($config['discount_value']) . '% OFF';
			} elseif (isset($config['discount_type']) && in_array($config['discount_type'], ['fixed', 'fixed_cart', 'fixed_product'])) {
				$title = strip_tags(wc_price(floatval($config['discount_value']))) . ' OFF';
			}
		} elseif ($deal['rule_type'] === 'bogo') {
			$title = 'Buy 1 Get 1'; 
		}
		
		$desc = $deal['title']; // Show promo name as description
		$tc = isset($config['display']) && !empty($config['display']['terms_conditions']) ? $config['display']['terms_conditions'] : '';

		// Get eligible products
		$products = [];
		if ($deal['apply_to'] === 'specific_products') {
			$pids = json_decode($deal['apply_to_items'], true);
			if (!empty($pids) && is_array($pids)) {
				$query_args = array(
					'post_type' => 'product',
					'post_status' => 'publish',
					'post__in' => $pids,
					'posts_per_page' => 15
				);
				$products = new WP_Query($query_args);
			}
		} elseif ($deal['apply_to'] === 'specific_categories') {
			$cat_ids = json_decode($deal['apply_to_items'], true);
			if (!empty($cat_ids) && is_array($cat_ids)) {
				$query_args = array(
					'post_type' => 'product',
					'post_status' => 'publish',
					'tax_query' => array(
						array(
							'taxonomy' => 'product_cat',
							'field' => 'term_id',
							'terms' => $cat_ids
						)
					),
					'posts_per_page' => 15
				);
				$products = new WP_Query($query_args);
			}
		}

		?>
		<style>
			.o100-dp-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 999999; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.3s ease; backdrop-filter: blur(2px); }
			.o100-dp-overlay.active { opacity: 1; visibility: visible; }
			.o100-dp-modal { background: #fff; width: 95%; max-width: 600px; max-height: 90vh; border-radius: 16px; display: flex; flex-direction: column; box-shadow: 0 20px 40px rgba(0,0,0,0.2); transform: translateY(20px) scale(0.98); transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; overflow: hidden; }
			.o100-dp-overlay.active .o100-dp-modal { transform: translateY(0) scale(1); }
			.o100-dp-close-btn { position: absolute; top: 16px; right: 16px; width: 36px; height: 36px; background: #f1f5f9; border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #475569; z-index: 10; transition: all 0.2s; padding: 0; }
			.o100-dp-close-btn svg { stroke: currentColor; }
			.o100-dp-close-btn:hover { background: #e2e8f0; color: #0f172a; }
			.o100-dp-header { padding: 32px 32px 16px 32px; border-bottom: 1px solid #f1f5f9; }
			.o100-dp-title { font-size: 28px; font-weight: 800; color: #0f172a; margin: 0 0 12px 0; padding-right: 32px; line-height: 1.2; letter-spacing: -0.5px; }
			.o100-dp-desc { font-size: 16px; color: #475569; line-height: 1.5; margin: 0; font-weight: 500; }
			.o100-dp-tc-toggle { color: #475569; font-size: 13px; font-weight: 600; text-decoration: underline; cursor: pointer; margin-top: 12px; display: inline-block; transition: color 0.2s; }
			.o100-dp-tc-toggle:hover { color: <?php echo esc_attr($color); ?>; }
			.o100-dp-tc-content { display: none; padding: 12px; background: #f8fafc; border-radius: 8px; font-size: 13px; color: #64748b; margin-top: 12px; border: 1px solid #e2e8f0; }
			.o100-dp-body { padding: 24px 48px; flex: 1; overflow: hidden; display: flex; flex-direction: column; position: relative; }
			.o100-dp-slider-wrap { position: relative; width: 100%; overflow: hidden; border-radius: 12px; }
			.o100-dp-products-carousel { display: flex; flex-direction: row; width: 100%; transition: transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); }
			.o100-dp-products-carousel .o100-product-card { flex: 0 0 100%; width: 100%; margin: 0 !important; border: 1px solid #f1f5f9; border-radius: 12px; }
			.o100-dp-slider-nav { position: absolute; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; background: #fff; border: 1px solid #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #475569; z-index: 5; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); transition: all 0.2s; padding: 0; }
			.o100-dp-slider-nav:hover { background: #f8fafc; color: #0f172a; border-color: #cbd5e1; }
			.o100-dp-slider-nav svg { width: 20px !important; height: 20px !important; fill: none !important; stroke: currentColor !important; stroke-width: 2 !important; stroke-linecap: round; stroke-linejoin: round; display: block; flex-shrink: 0; }
			.o100-dp-slider-prev { left: 8px; }
			.o100-dp-slider-next { right: 8px; }
			.o100-dp-footer { padding: 16px 32px 24px 32px; border-top: 1px solid #f1f5f9; display: flex; justify-content: center; }
			.o100-dp-btn-close-big { background: <?php echo esc_attr($color); ?>; color: #fff; border: none; border-radius: 50px; padding: 12px 40px; font-size: 16px; font-weight: 700; cursor: pointer; transition: opacity 0.2s; width: 100%; max-width: 200px; }
			.o100-dp-btn-close-big:hover { opacity: 0.9; }
			@media (max-width: 640px) {
				.o100-dp-header, .o100-dp-body, .o100-dp-footer { padding-left: 20px; padding-right: 20px; }
				.o100-dp-title { font-size: 24px; }
			}
		</style>
		<div class="o100-dp-overlay" id="<?php echo esc_attr($popup_id); ?>">
			<div class="o100-dp-modal">
				<button class="o100-dp-close-btn o100-dp-close-trigger">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
				</button>
				
				<div class="o100-dp-header">
					<h2 class="o100-dp-title"><?php echo esc_html($title); ?></h2>
					<?php if ($desc) : ?>
						<p class="o100-dp-desc"><?php echo wp_kses_post($desc); ?></p>
					<?php endif; ?>
					
					<?php 
					$req_tags = self::get_deal_requirements($deal, $config);
					if (!empty($req_tags)) : 
					?>
						<div style="margin-top: 16px; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px dashed #cbd5e1;">
							<div style="font-weight: 700; color: #0f172a; margin-bottom: 6px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Requirements</div>
							<ul style="margin: 0; padding-left: 20px; color: #475569; font-size: 14px; font-weight: 500;">
								<?php foreach ($req_tags as $tag) : ?>
									<li style="margin-bottom: 4px;"><?php echo esc_html($tag); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
					
					<?php if ($tc) : ?>
						<div class="o100-dp-tc-toggle">Terms and Conditions</div>
						<div class="o100-dp-tc-content"><?php echo nl2br(esc_html($tc)); ?></div>
					<?php endif; ?>
				</div>
				
				<?php if (!empty($products) && $products->have_posts()) : ?>
					<div class="o100-dp-body">
						<div class="o100-dp-slider-wrap">
							<div class="o100-dp-products-carousel o100-layout-list">
								<?php 
								// Render as list layout (100% width)
								while ($products->have_posts()) : $products->the_post();
									global $product;
									self::render_product_card($product, 'o100-layout-list', $settings);
								endwhile; 
								wp_reset_postdata();
								?>
							</div>
						</div>
						<?php if ($products->found_posts > 1) : ?>
							<button class="o100-dp-slider-nav o100-dp-slider-prev">
								<svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
							</button>
							<button class="o100-dp-slider-nav o100-dp-slider-next">
								<svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg>
							</button>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="o100-dp-footer">
					<button class="o100-dp-btn-close-big o100-dp-close-trigger">Close</button>
				</div>
			</div>
		</div>
		<script>
			document.addEventListener("DOMContentLoaded", function() {
				var modal = document.getElementById("<?php echo esc_js($popup_id); ?>");
				if (!modal) return;

				var closeTriggers = modal.querySelectorAll(".o100-dp-close-trigger");
				var tcToggle = modal.querySelector(".o100-dp-tc-toggle");
				var tcContent = modal.querySelector(".o100-dp-tc-content");

				var closeModal = function() {
					modal.classList.remove("active");
					document.body.style.overflow = ""; // restore scroll
				};

				closeTriggers.forEach(function(btn) {
					btn.addEventListener("click", closeModal);
				});

				modal.addEventListener("click", function(e) {
					if (e.target === modal) closeModal();
				});

				if (tcToggle && tcContent) {
					tcToggle.addEventListener("click", function() {
						tcContent.style.display = tcContent.style.display === "block" ? "none" : "block";
					});
				}

				// Carousel Logic
				var carousel = modal.querySelector(".o100-dp-products-carousel");
				if (carousel) {
					var cards = carousel.querySelectorAll(".o100-product-card");
					if (cards.length > 1) {
						var currentIndex = 0;
						var total = cards.length;
						var prevBtn = modal.querySelector(".o100-dp-slider-prev");
						var nextBtn = modal.querySelector(".o100-dp-slider-next");
						var autoPlayInterval;

						function updateSlider() {
							carousel.style.transform = "translateX(-" + (currentIndex * 100) + "%)";
						}

						function nextSlide() {
							currentIndex = (currentIndex + 1) % total;
							updateSlider();
						}

						function prevSlide() {
							currentIndex = (currentIndex - 1 + total) % total;
							updateSlider();
						}

						function startAutoPlay() {
							stopAutoPlay();
							autoPlayInterval = setInterval(nextSlide, 2000);
						}

						function stopAutoPlay() {
							if (autoPlayInterval) clearInterval(autoPlayInterval);
						}

						if (nextBtn) {
							nextBtn.addEventListener("click", function() {
								nextSlide();
								startAutoPlay(); // reset timer on manual click
							});
						}
						
						if (prevBtn) {
							prevBtn.addEventListener("click", function() {
								prevSlide();
								startAutoPlay(); // reset timer on manual click
							});
						}

						// Pause on hover
						var sliderWrap = modal.querySelector(".o100-dp-slider-wrap");
						sliderWrap.addEventListener("mouseenter", stopAutoPlay);
						sliderWrap.addEventListener("mouseleave", startAutoPlay);

						// Start autoplay only when modal is active
						var observer = new MutationObserver(function(mutations) {
							mutations.forEach(function(mutation) {
								if (mutation.attributeName === "class") {
									if (modal.classList.contains("active")) {
										startAutoPlay();
									} else {
										stopAutoPlay();
									}
								}
							});
						});
						observer.observe(modal, { attributes: true });
					}
				}
			});
		</script>
		<?php
	}

	private static function render_menu_scripts() {
		// Output script only once per page request
		static $script_rendered = false;
		if ( $script_rendered ) return;
		$script_rendered = true;

		echo '<script>
		(function() {
			function initOrder100Menu() {
				try {
				// Category Smooth Scroll
				document.addEventListener("click", function(e) {
					const catLink = e.target.closest(".o100-cat-link, .o100-drawer-link");
					if (catLink) {
						e.preventDefault();
						const targetId = catLink.getAttribute("data-target") || catLink.getAttribute("href").replace("#", "");
						const target = document.getElementById(targetId);
						if (target) {
							const yOffset = -150; // Offset to keep it in the upper-middle of the screen
							const y = target.getBoundingClientRect().top + window.pageYOffset + yOffset;
							window.scrollTo({ top: Math.max(0, y), behavior: "smooth" });
						}
					}
				});

				// Initialize Cache for instant modal opening
				window.o100_modal_cache = window.o100_modal_cache || {};

				function prefetchProductModal(productId) {
					if (window.o100_modal_cache[productId]) return window.o100_modal_cache[productId];

					const ajaxUrl = typeof o100_ajax_object !== "undefined" ? o100_ajax_object.ajax_url : "/wp-admin/admin-ajax.php";
					const ajaxNonce = typeof o100_ajax_object !== "undefined" ? o100_ajax_object.nonce : "";

					const formData = new FormData();
					formData.append("action", "o100_product_modal_info");
					formData.append("product_id", productId);
					formData.append("nonce", ajaxNonce);

					const fetchPromise = fetch(ajaxUrl, {
						method: "POST",
						body: formData
					}).then(res => res.json());

					window.o100_modal_cache[productId] = fetchPromise;
					return fetchPromise;
				}

				// Prefetch on Hover or Touch to eliminate perceived latency
				document.addEventListener("mouseover", function(e) {
					const card = e.target.closest(".o100-product-card");
					if (!card) return;
					if (card.getAttribute("data-enable-modal") === "yes") {
						const productId = card.getAttribute("data-product-id");
						if (productId) prefetchProductModal(productId);
					}
				}, { passive: true });

				document.addEventListener("touchstart", function(e) {
					const card = e.target.closest(".o100-product-card");
					if (!card) return;
					if (card.getAttribute("data-enable-modal") === "yes") {
						const productId = card.getAttribute("data-product-id");
						if (productId) prefetchProductModal(productId);
					}
				}, { passive: true });

				// Product Card Click Handling
				document.addEventListener("click", function(e) {
					const card = e.target.closest(".o100-product-card");
					if (!card) return;

					// If user clicked the fast native add-to-cart, do NOT open modal
					if (e.target.closest(".o100-native-add-to-cart")) return;
					
					// Prevent navigation if they clicked the view-options button or the card
					if (e.target.closest(".o100-view-options-btn") || !e.target.closest("a")) {
						e.preventDefault();
					} else if (e.target.closest("a") && !e.target.closest(".o100-view-options-btn")) {
						return; // Some other link inside the card (rare)
					}
					const productId = card.getAttribute("data-product-id");
					const enableModal = card.getAttribute("data-enable-modal");
					const productLink = card.getAttribute("data-product-link");

					if (enableModal !== "yes") {
						window.location.href = productLink;
						return;
					}

					// Extract product info for Skeleton
					const imgEl = card.querySelector(".o100-product-image img") || card.querySelector("img");
					const imgSrc = imgEl ? imgEl.src : "";
					const titleEl = card.querySelector(".o100-product-title");
					const title = titleEl ? titleEl.innerText : "";
					const priceEl = card.querySelector(".o100-price");
					const price = priceEl ? priceEl.innerText : "";
					const descEl = card.querySelector(".o100-product-desc");
					const desc = descEl ? descEl.innerText : "";

					const skeletonStyle = `
						<style>
							@keyframes o100-pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
							.o100-skeleton-box { background: #e2e8f0; border-radius: 8px; margin-bottom: 12px; animation: o100-pulse 1.5s infinite ease-in-out; }
							.o100-pm-skeleton-wrap { max-height: 90vh; overflow-y: auto; background: #fff; width: 100%; max-width: 600px; margin: auto; border-radius: 12px; position: relative; display: flex; flex-direction: column; }
							.o100-pm-close-sk { position: absolute; top: 15px; right: 15px; background: #fff; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
						</style>
					`;

					let imgHtml = "";
					if (imgSrc) {
						imgHtml = "<div style=\"width:100%; background:#f8f9fa;\"><img src=\"" + imgSrc + "\" style=\"width:100%; max-height:250px; object-fit:cover; border-radius:12px 12px 0 0; display:block;\"></div>";
					}
					
					let descHtml = "";
					if (desc) {
						descHtml = "<div style=\"color:#666; font-size:14px; margin-bottom:16px; line-height:1.5;\">" + desc + "</div>";
					}

					let modalOverlay = document.getElementById("o100-pm-overlay");
					if (!modalOverlay) {
						modalOverlay = document.createElement("div");
						modalOverlay.id = "o100-pm-overlay";
						modalOverlay.className = "o100-pm-overlay";
						document.body.appendChild(modalOverlay);
					}

					modalOverlay.innerHTML = skeletonStyle + `
						<div class="o100-pm-skeleton-wrap">
							<button type="button" class="o100-pm-close-sk" onclick="document.getElementById(\'o100-pm-overlay\').classList.remove(\'active\'); document.body.style.overflow=\'\';">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
							</button>
							${imgHtml}
							<div style="padding: 24px; flex-grow: 1;">
								<h2 style="margin:0 0 8px 0; font-size:22px; font-weight:700; color:#111;">${title}</h2>
								${descHtml}
								<div style="margin-bottom:24px;"><span style="font-weight:600; font-size:18px; color:#111;">${price}</span></div>
								<div style="margin-top: 24px;">
									<div class="o100-skeleton-box" style="height: 24px; width: 40%; margin-bottom: 16px;"></div>
									<div class="o100-skeleton-box" style="height: 48px; width: 100%;"></div>
									<div class="o100-skeleton-box" style="height: 48px; width: 100%;"></div>
								</div>
							</div>
							<div style="padding: 16px 24px; border-top: 1px solid #eee; background: #fff; position: sticky; bottom: 0; border-radius: 0 0 12px 12px;">
								<div class="o100-skeleton-box" style="height: 48px; width: 100%; border-radius: 8px; margin: 0;"></div>
							</div>
						</div>
					`;

					modalOverlay.classList.add("active");
					document.body.style.overflow = "hidden";

					let fetchPromise = window.o100_modal_cache[productId];
					if (!fetchPromise) {
						fetchPromise = prefetchProductModal(productId);
					}

					fetchPromise.then(response => {
						if (response.success && response.data && response.data.html) {
							modalOverlay.innerHTML = response.data.html;
							
							// Close button handler
							const closeBtn = modalOverlay.querySelector(".o100-pm-close");
							if (closeBtn) {
								closeBtn.addEventListener("click", () => {
									modalOverlay.classList.remove("active");
									document.body.style.overflow = "";
								});
							}
							
							// Click outside handler
							modalOverlay.addEventListener("click", (e) => {
								if (e.target === modalOverlay) {
									modalOverlay.classList.remove("active");
									document.body.style.overflow = "";
								}
							});

							// Initialize Variations if present
							if (typeof jQuery !== "undefined") {
								jQuery(document).trigger("o100_modal_loaded", [jQuery(modalOverlay).find(".o100-product-modal-inner").last()]);

								if (jQuery().wc_variation_form) {
									const form = jQuery(modalOverlay).find(".variations_form");
									if (form.length) {
										form.each(function(){ jQuery(this).wc_variation_form() });
										form.trigger("check_variations");
										form.trigger("reset_image");
									}
								}
								// Initialize WooCommerce Product Addons if present
								if (typeof jQuery.fn.init_addon_totals === "function") {
									jQuery(modalOverlay).find(".cart:not(.cart_group)").each(function() {
										jQuery(this).init_addon_totals();
									});
								}
							}
						} else {
							modalOverlay.innerHTML = "<div class=\"o100-pm-error\">Error loading product.</div>";
							setTimeout(() => { modalOverlay.classList.remove("active"); document.body.style.overflow = ""; }, 2000);
						}
					})
					.catch(err => {
						modalOverlay.innerHTML = "<div class=\"o100-pm-error\">Error loading product.</div>";
						setTimeout(() => { modalOverlay.classList.remove("active"); document.body.style.overflow = ""; }, 2000);
					});
				});

				// Native Add to Cart AJAX Handler (Optimistic UI)
				document.addEventListener("click", function(e) {
					const addBtn = e.target.closest(".o100-native-add-to-cart");
					if (!addBtn) return;
					
					e.preventDefault();
					e.stopPropagation();

					if (addBtn.classList.contains("loading")) return;

					const productId = addBtn.getAttribute("data-product_id");
					const qty = addBtn.getAttribute("data-quantity") || 1;
					
					// Optimistic UI: Immediately show updated quantity
					addBtn.classList.add("loading");
					
					if (addBtn.classList.contains("o100-qty-active")) {
						// Already in cart — increment displayed qty instantly
						const $qtySpan = addBtn.querySelector(".o100-qty-text");
						if ($qtySpan) {
							const curQty = parseInt($qtySpan.textContent) || 1;
							$qtySpan.textContent = (curQty + parseInt(qty)) + " ×";
						}
					} else {
						// First add — instantly flip to qty badge style
						addBtn.classList.add("o100-qty-active");
						addBtn.innerHTML = "<span class=\"o100-qty-text\" style=\"color: #fff !important; font-size: 13px !important; font-weight: 800; display: inline-block; white-space: nowrap; line-height: 1;\">1 \u00d7</span>";
					}
					
					// Remove loading flag after brief delay
					setTimeout(() => {
						addBtn.classList.remove("loading");
					}, 800);

					const ajaxUrl = (typeof wc_add_to_cart_params !== "undefined") ? wc_add_to_cart_params.wc_ajax_url.toString().replace("%%endpoint%%", "add_to_cart") : "/?wc-ajax=add_to_cart";

					if (typeof jQuery !== "undefined") {
						jQuery.ajax({
							url: ajaxUrl,
							type: "POST",
							data: {
								product_id: productId,
								quantity: qty
							},
							success: function(data) {
								if (data.error && data.product_url) {
									window.location = data.product_url;
									return;
								}
								// Silently update fragments in background
								jQuery(document.body).trigger("added_to_cart", [data.fragments, data.cart_hash, null]);
							}
						});
					} else {
						// Fallback if jQuery is not available
						const formData = new FormData();
						formData.append("product_id", productId);
						formData.append("quantity", qty);

						fetch(ajaxUrl, {
							method: "POST",
							headers: {
								"X-Requested-With": "XMLHttpRequest"
							},
							body: formData
						})
						.then(res => res.json())
						.then(data => {
							if (data.error && data.product_url) {
								window.location = data.product_url;
								return;
							}
						});
					}
				});

				// Active Section Scroll Spy
				window.addEventListener("scroll", function() {
					const sections = document.querySelectorAll(".o100-menu-section");
					const links = document.querySelectorAll(".o100-cat-link");
					let current = "";
					sections.forEach(section => {
						const sectionTop = section.offsetTop;
						if (pageYOffset >= sectionTop - 100) {
							current = section.getAttribute("id");
						}
					});
					links.forEach(link => {
						link.classList.remove("active");
						if (link.getAttribute("data-target") === current) {
							link.classList.add("active");
							
							const navWrapper = document.querySelector(".o100-category-nav-wrapper");
							if (navWrapper) {
								const linkLeft = link.offsetLeft;
								const linkWidth = link.offsetWidth;
								const navWidth = navWrapper.offsetWidth;
								const scrollLeft = navWrapper.scrollLeft;
								if (linkLeft < scrollLeft || (linkLeft + linkWidth) > (scrollLeft + navWidth)) {
									navWrapper.scrollTo({ left: linkLeft - 20, behavior: "smooth" });
								}
							}
						}
					});
				});

				// Drawer Logic
				document.addEventListener("click", function(e) {
					const drawerToggle = e.target.closest("#o100-drawer-toggle");
					const drawerClose = e.target.closest("#o100-drawer-close");
					const drawerOverlay = document.getElementById("o100-drawer-overlay");
					const drawer = document.getElementById("o100-drawer");

					if (drawerToggle) {
						if (drawer) drawer.classList.add("active");
						if (drawerOverlay) drawerOverlay.classList.add("active");
						document.body.style.overflow = "hidden";
					}

					if (drawerClose || e.target === drawerOverlay || e.target.closest(".o100-drawer-link")) {
						if (drawer) drawer.classList.remove("active");
						if (drawerOverlay) drawerOverlay.classList.remove("active");
						document.body.style.overflow = "";
					}
				});

				// Search Logic
				document.addEventListener("input", function(e) {
					if (e.target.classList.contains("o100-search-input")) {
						const val = e.target.value.toLowerCase();
						const cards = document.querySelectorAll(".o100-product-card");
						const sections = document.querySelectorAll(".o100-menu-section");
						
						cards.forEach(card => {
							const title = card.querySelector(".o100-product-title");
							const descEl = card.querySelector(".o100-product-desc");
							const desc = descEl ? descEl.innerText.toLowerCase() : "";
							if (title && (title.innerText.toLowerCase().includes(val) || desc.includes(val))) {
								card.style.display = "";
							} else {
								card.style.display = "none";
							}
						});

						sections.forEach(section => {
							const visibleCards = section.querySelectorAll(".o100-product-card[style=\"\"]");
							if (visibleCards.length === 0) {
								section.style.display = "none";
							} else {
								section.style.display = "";
							}
						});
					}
				});
				
				// Scroll Arrows Logic
				const navWrapper = document.querySelector(".o100-category-nav-wrapper");
				const leftArrow = document.querySelector(".o100-nav-arrow-left");
				const rightArrow = document.querySelector(".o100-nav-arrow-right");

				if (navWrapper && leftArrow && rightArrow) {
					const checkArrows = () => {
						if (navWrapper.scrollLeft > 0) leftArrow.style.display = "flex";
						else leftArrow.style.display = "none";
						
						if (Math.ceil(navWrapper.scrollLeft + navWrapper.clientWidth) < navWrapper.scrollWidth) rightArrow.style.display = "flex";
						else rightArrow.style.display = "none";
					};

					navWrapper.addEventListener("scroll", checkArrows);
					window.addEventListener("resize", checkArrows);
					setTimeout(checkArrows, 100);

					leftArrow.addEventListener("click", () => { navWrapper.scrollBy({ left: -200, behavior: "smooth" }); });
					rightArrow.addEventListener("click", () => { navWrapper.scrollBy({ left: 200, behavior: "smooth" }); });
				}
				} catch (e) {
					console.error("Order100 Menu Init Error: ", e);
				}
			}

			if (document.readyState === "loading") {
				document.addEventListener("DOMContentLoaded", initOrder100Menu);
			} else {
				initOrder100Menu();
			}
		})();
		</script>';
	}

	/**
	 * Get categories based on settings filter
	 */
	private static function get_filtered_categories( $settings ) {
		$cat_input = isset( $settings['categories'] ) ? $settings['categories'] : (isset($settings['cat']) ? $settings['cat'] : '');
		
		if ( ! empty( $cat_input ) ) {
			$cat_raw = array_map( 'trim', explode( ',', $cat_input ) );
			$cat_ids = array();
			$cat_slugs = array();
			
			foreach ( $cat_raw as $c ) {
				if ( is_numeric( $c ) ) {
					$cat_ids[] = intval( $c );
				} else {
					$cat_slugs[] = $c;
				}
			}

			$categories = array();
			
			if ( ! empty( $cat_slugs ) ) {
				$slug_cats = get_terms( array(
					'taxonomy'   => 'product_cat',
					'slug'       => $cat_slugs,
					'hide_empty' => false,
				) );
				if ( ! is_wp_error( $slug_cats ) ) {
					$categories = array_merge( $categories, $slug_cats );
				}
			}

			if ( ! empty( $cat_ids ) ) {
				$id_cats = get_terms( array(
					'taxonomy'   => 'product_cat',
					'include'    => $cat_ids,
					'hide_empty' => false,
				) );
				if ( ! is_wp_error( $id_cats ) ) {
					$categories = array_merge( $categories, $id_cats );
				}
			}

			$categories = array_unique( $categories, SORT_REGULAR );
			
			if ( isset( $settings['order_cat'] ) && $settings['order_cat'] === 'yes' ) {
				usort( $categories, function( $a, $b ) {
					$order_a = (int) get_term_meta( $a->term_id, 'order', true );
					$order_b = (int) get_term_meta( $b->term_id, 'order', true );
					return $order_a - $order_b;
				} );
			}
		} else {
			$term_args = array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			);
			
			if ( isset( $settings['order_cat'] ) && $settings['order_cat'] === 'yes' ) {
				$term_args['orderby']  = 'meta_value_num';
				$term_args['meta_key'] = 'order';
				$term_args['order']    = 'ASC';
			}

			// If no specific categories selected, return all
			$categories = get_terms( $term_args );
			if ( is_wp_error( $categories ) ) {
				$categories = array();
			}
		}

		// --- Advanced CRM Rules Engine: Secret Menu Filtering ---
		if ( class_exists( 'O100_Privilege_Manager' ) ) {
			$all_secret_cats = O100_Privilege_Manager::get_all_secret_categories();
			if ( ! empty( $all_secret_cats ) ) {
				$allowed_secrets = array();
				if ( is_user_logged_in() ) {
					$loc_id = isset( WC()->session ) ? WC()->session->get( 'o100_location_id' ) : 0;
					$method = isset( WC()->session ) ? WC()->session->get( '_o100_order_method' ) : 'delivery';
					$context = array(
						'branch'     => $loc_id ? intval( $loc_id ) : null,
						'order_type' => $method,
						'timestamp'  => current_time( 'timestamp' ),
					);
					$user_secrets = O100_Privilege_Manager::get_privilege( get_current_user_id(), 'menu', 'secret_menu', $context );
					if ( is_array( $user_secrets ) ) {
						$allowed_secrets = array_map( 'intval', $user_secrets );
					}
				}

				// Filter out categories that are secret, unless the user is explicitly allowed to see them
				$categories = array_filter( $categories, function( $cat ) use ( $all_secret_cats, $allowed_secrets ) {
					$cat_id = intval( $cat->term_id );
					if ( in_array( $cat_id, $all_secret_cats ) && ! in_array( $cat_id, $allowed_secrets ) ) {
						return false; // Hide this secret category
					}
					return true; // Show normal category or allowed secret category
				} );
			}
		}

		return $categories;
	}

	/**
	 * Get products for a specific category
	 */
	private static function get_products_for_category( $term_id, $settings = array() ) {
		$orderby = isset($settings['orderby']) ? $settings['orderby'] : 'date';
		$order   = isset($settings['order']) ? $settings['order'] : 'DESC';
		$meta_key = '';
		
		if ( $orderby === 'order_field' ) {
			$orderby = 'meta_value_num';
			$meta_key = 'exwoofood_order';
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'order'          => $order,
			'orderby'        => $orderby,
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		);

		if ( $meta_key ) {
			$args['meta_key'] = $meta_key;
		}

		// Handle Specific IDs
		$ids = isset($settings['ids']) ? trim($settings['ids']) : '';
		if ( ! empty($ids) ) {
			$args['post__in'] = array_map('intval', explode(',', $ids));
		}

		// Handle Featured Only
		if ( isset($settings['featured']) && ($settings['featured'] === '1' || $settings['featured'] === 'yes') ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => 'featured',
				'operator' => 'IN',
			);
		}
		
		// Handle Standard eCommerce Exclusion (Completely hide from Food Menu SPA)
		$menu_rules = get_option('o100_menu_rules', array());
		if ( ! empty( $menu_rules['o100_enable_standard_ecom'] ) && $menu_rules['o100_enable_standard_ecom'] === 'on' ) {
			// Exclude Categories
			$disable_cat = isset($menu_rules['o100_disable_food_cat']) ? $menu_rules['o100_disable_food_cat'] : '';
			if ( ! empty( $disable_cat ) ) {
				$cat_ids = is_array( $disable_cat ) ? array_map( 'intval', $disable_cat ) : array_map( 'intval', array_filter( explode( ',', $disable_cat ) ) );
				if ( ! empty( $cat_ids ) ) {
					$args['tax_query'][] = array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $cat_ids,
						'operator' => 'NOT IN',
					);
				}
			}
			// Exclude Products
			$disable_pro = isset($menu_rules['o100_disable_food_pro']) ? $menu_rules['o100_disable_food_pro'] : '';
			if ( ! empty( $disable_pro ) ) {
				$pro_ids = is_array( $disable_pro ) ? array_map( 'intval', $disable_pro ) : array_map( 'intval', array_filter( explode( ',', $disable_pro ) ) );
				if ( ! empty( $pro_ids ) ) {
					$args['post__not_in'] = $pro_ids;
				}
			}
		}


		return new WP_Query( $args );
	}

	/**
	 * Sort a WP_Query result so products with thumbnails appear first,
	 * preserving the original sort order within each group.
	 */
	private static function sort_photos_first( $query ) {
		$with_image    = array();
		$without_image = array();

		foreach ( $query->posts as $post ) {
			if ( has_post_thumbnail( $post->ID ) ) {
				$with_image[] = $post;
			} else {
				$without_image[] = $post;
			}
		}

		$query->posts = array_merge( $with_image, $without_image );
		$query->rewind_posts();
	}
}

O100_Menu_Renderer::init();
add_action( 'init', array( 'O100_Menu_Renderer', 'init' ), 999 );

