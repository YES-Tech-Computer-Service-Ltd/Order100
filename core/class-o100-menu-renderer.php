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
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'order100_food_menu' ) ) {
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

		ob_start();

		echo '<div class="o100-menu-wrapper ' . esc_attr( isset( $settings['class'] ) ? $settings['class'] : '' ) . '">';

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
	private static function render_shared_styles($cols, $cols_tablet = 2, $cols_mobile = 1) {
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

		/* Category Nav */
		.o100-menu-container .o100-menu-header { position: sticky !important; top: 0 !important; z-index: 99 !important; background: #fff !important; border-bottom: 1px solid #e8e8e8 !important; margin-bottom: 24px !important; padding: 10px 0 !important; }
		.o100-menu-container .o100-nav-inner { display: flex !important; align-items: center !important; width: 100% !important; position: relative !important; }
		.o100-menu-container .o100-list-icon { padding-right: 16px !important; color: #000 !important; flex-shrink: 0 !important; display: flex !important; }
		.o100-menu-container .o100-category-nav-wrapper { display: flex !important; overflow-x: auto !important; flex-grow: 1 !important; scrollbar-width: none !important; align-items: center !important; scroll-behavior: smooth !important; }
		.o100-menu-container a.o100-cat-link { text-decoration: none !important; color: #767676 !important; font-weight: 500 !important; white-space: nowrap !important; padding: 10px 0 !important; margin-right: 24px !important; font-size: 15px !important; border-bottom: 3px solid transparent !important; transition: color 0.2s, border-color 0.2s !important; }
		.o100-menu-container a.o100-cat-link:hover { color: #191919 !important; border-bottom-color: #191919 !important; }
		.o100-menu-container a.o100-cat-link.active { color: #000 !important; font-weight: 700 !important; border-bottom: 3px solid #000 !important; }
		
		@media (max-width: 768px) {
			.o100-menu-container a.o100-cat-link { font-size: 13px !important; padding: 8px 12px !important; margin-right: 12px !important; }
		}
		
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
		.o100-menu-section h2 { margin: 0 0 20px 0; font-size: 24px; font-weight: 700; color: #000; letter-spacing: -0.5px; }
		
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
		.o100-product-restricted { opacity: 0.7; pointer-events: none; }
		.o100-product-restricted .o100-product-title, .o100-product-restricted .o100-product-desc, .o100-product-restricted .o100-product-price { color: #888; }
		.o100-restriction-badge { display: block; font-size: 12px; font-weight: 700; color: #c48b85; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

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
		
		$product_link = $product->get_permalink();
		
		$card_classes = 'o100-product-card ' . ($img_url ? '' : 'o100-no-image');
		if ( $restriction ) {
			$card_classes .= ' o100-product-restricted';
		}
		
		echo '<div class="' . esc_attr( $card_classes ) . '" data-product-id="' . esc_attr( $product->get_id() ) . '" data-enable-modal="' . esc_attr( $enable_modal ) . '" data-product-link="' . esc_url( $product_link ) . '">';
		
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
		}
		echo '</div>';
		
		if ( ! $restriction ) {
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
			'posts_per_page' => isset($settings['posts_per_page']) && $settings['posts_per_page'] !== '' ? intval($settings['posts_per_page']) : -1,
			'order'          => isset($settings['order']) ? $settings['order'] : 'DESC',
			'orderby'        => $orderby,
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

		// Handle Category Filter
		$cat_input = isset($settings['categories']) ? $settings['categories'] : (isset($settings['cat']) ? $settings['cat'] : '');
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

		echo '<div class="o100-menu-container o100-standalone">';
		self::render_shared_styles($cols, $cols_tablet, $cols_mobile);

		$products = self::build_product_query( $settings );

		if ( ! $products->have_posts() ) {
			echo '<p>No products found for this query.</p></div>';
			return;
		}

		// Photos First sorting
		if ( ! empty( $settings['photos_first'] ) && $settings['photos_first'] === 'yes' ) {
			self::sort_photos_first( $products );
		}

		echo '<div class="o100-menu-sections ' . esc_attr($layout_class) . '">';
		echo '<div class="o100-menu-section">'; // Use the section wrapper
		echo '<div class="o100-product-grid">';
		
		while ( $products->have_posts() ) {
			$products->the_post();
			global $product;
			self::render_product_card( $product, $layout_class, $settings );
		}
		
		echo '</div></div></div>'; // End grid, section, sections
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

		echo '<div class="o100-menu-container">';
		self::render_shared_styles($cols, $cols_tablet, $cols_mobile);

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
		echo '<div class="o100-search-container" style="width: 100%; max-width: 500px; margin-bottom: 0 !important;">';
		echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; color: #111;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
		echo '<input type="text" class="o100-search-input" placeholder="Search store menu" style="width: 100%; padding: 12px 16px 12px 48px; border-radius: 50px; border: none; background: #f2f2f2; font-size: 16px; outline: none; color: #111; font-weight: 500;">';
		echo '</div>';

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
			echo '<a href="#o100-cat-' . esc_attr( $cat->term_id ) . '" class="o100-cat-link' . $active_class . '" data-target="o100-cat-' . esc_attr( $cat->term_id ) . '">' . esc_html( $cat->name ) . '</a>';
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
			$hex = str_replace('#', '', $color);
			$r = hexdec(strlen($hex) == 3 ? str_repeat(substr($hex, 0, 1), 2) : substr($hex, 0, 2));
			$g = hexdec(strlen($hex) == 3 ? str_repeat(substr($hex, 1, 1), 2) : substr($hex, 2, 2));
			$b = hexdec(strlen($hex) == 3 ? str_repeat(substr($hex, 2, 1), 2) : substr($hex, 4, 2));
			$bg_color = "rgba($r,$g,$b,0.06)";

			echo '<div class="o100-menu-section o100-deals-section" style="margin-bottom: 40px;">';
			echo '<h2 style="font-size: 24px; font-weight: 700; color: #000; margin: 0 0 20px 0; letter-spacing: -0.5px;">Deals & offers</h2>';
			echo '<div class="o100-deals-carousel" style="display: flex; gap: 16px; overflow-x: auto; padding: 4px 4px 12px 4px; margin: -4px -4px 0 -4px; scrollbar-width: none; -ms-overflow-style: none;">';
			
			foreach ($active_deals as $deal) {
				$config = json_decode( $deal['action_config'], true );
				$label = $deal['title'];
				$sub = '';
				if (isset($config['display']) && !empty($config['display']['badge_text'])) {
					$label = $config['display']['badge_text'];
				}
				if (!empty($deal['description'])) {
					$sub = $deal['description'];
				} else {
					$type = $deal['rule_type'];
					if ($type === 'bogo') $sub = 'Add eligible items';
					elseif ($type === 'simple') $sub = 'Save on your order';
					else $sub = 'Special Offer';
				}

				// Content for popup
				$popup_id = 'o100-deal-popup-' . intval($deal['id']);
				$popup_title = isset($config['display']) && !empty($config['display']['popup_title']) ? $config['display']['popup_title'] : $deal['title'];
				$popup_desc = isset($config['display']) && !empty($config['display']['popup_text']) ? wp_strip_all_tags($config['display']['popup_text']) : $sub;
				
				echo '<div class="o100-deal-card" data-popup-id="' . esc_attr($popup_id) . '" style="flex: 0 0 auto; width: 280px; background-color: ' . esc_attr($bg_color) . '; border-radius: 12px; padding: 16px 20px; cursor: pointer; border: 1px solid rgba(' . $r . ',' . $g . ',' . $b . ', 0.15); transition: all 0.2s ease; display: flex; flex-direction: column; justify-content: center;">';
				echo '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">';
				echo '<div style="color: ' . esc_attr($color) . '; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
						' . esc_html($label) . '
					  </div>';
				echo '<div style="color: ' . esc_attr($color) . ';">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5l7 7-7 7"></path></svg>
					  </div>';
				echo '</div>';
				echo '<div style="color: #475569; font-size: 13px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-weight: 500;">' . esc_html($sub) . '</div>';
				echo '</div>';
			}
			echo '</div>';
			echo '<style>
				.o100-deals-carousel::-webkit-scrollbar { display: none; }
				.o100-deals-carousel .o100-deal-card:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.06); }
			</style>';
			echo '</div>';

			// Render Popups for deals
			foreach ($active_deals as $deal) {
				self::render_deal_popup($deal, $settings, $color);
			}

			// Inject JS to handle click
			echo '<script>
			document.addEventListener("DOMContentLoaded", function() {
				var cards = document.querySelectorAll(".o100-deal-card");
				cards.forEach(function(card) {
					card.addEventListener("click", function() {
						var popupId = this.getAttribute("data-popup-id");
						var popup = document.getElementById(popupId);
						if (popup) {
							popup.classList.add("active");
							document.body.style.overflow = "hidden"; // prevent background scroll
						}
					});
				});
			});
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

	private static function render_deal_popup($deal, $settings, $color) {
		$config = json_decode( $deal['action_config'], true );
		$popup_id = 'o100-deal-popup-' . intval($deal['id']);
		
		$title = isset($config['display']) && !empty($config['display']['popup_title']) ? $config['display']['popup_title'] : $deal['title'];
		$desc = isset($config['display']) && !empty($config['display']['popup_text']) ? $config['display']['popup_text'] : $deal['description'];
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
							window.scrollTo({ top: target.offsetTop - 70, behavior: "smooth" });
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
					
					const originalContent = addBtn.innerHTML;
					const successIcon = "<svg viewBox=\"0 0 24 24\" width=\"16\" height=\"16\" fill=\"none\" stroke=\"#10b981\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"20 6 9 17 4 12\"></polyline></svg>";
					
					// Optimistic UI: Immediately show success
					addBtn.classList.add("loading");
					addBtn.innerHTML = successIcon;
					
					// Reset button visually after 1.5s so user can click again
					setTimeout(() => {
						if (!addBtn.classList.contains("o100-qty-active")) {
							addBtn.innerHTML = originalContent;
						}
						addBtn.classList.remove("loading");
					}, 1500);

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

			return array_unique( $categories, SORT_REGULAR );
		}

		// If no specific categories selected, return all
		return get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		) );
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

