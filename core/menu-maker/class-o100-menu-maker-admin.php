<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Include the API handler
require_once O100_PATH . 'core/menu-maker/class-o100-menu-maker-api.php';
O100_Menu_Maker_API::init();

class O100_Menu_Maker_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'o100_get_product_impacts', array( $this, 'get_promotion_impacts' ), 10, 3 );
	}

	public function get_promotion_impacts( $impacts, $product_id, $category_ids ) {
		if ( ! class_exists( 'O100_Promotions_DB' ) ) return $impacts;

		$active_promos = O100_Promotions_DB::query( array( 'status' => 'active' ) );
		foreach ( $active_promos as $promo ) {
			if ( strpos( $promo['source'], 'loyalty' ) === 0 ) {
				continue;
			}

			$apply_to = $promo['apply_to'];
			$items_raw = json_decode( $promo['apply_to_items'], true );
			if ( ! is_array( $items_raw ) ) $items_raw = array();
			
			$is_eligible = false;
			if ( $apply_to === 'all_products' ) {
				$is_eligible = true;
			} elseif ( $apply_to === 'specific_products' && in_array( $product_id, $items_raw ) ) {
				$is_eligible = true;
			} elseif ( $apply_to === 'specific_categories' ) {
				foreach ( $category_ids as $cid ) {
					if ( in_array( $cid, $items_raw ) ) {
						$is_eligible = true;
						break;
					}
				}
			}
			
			if ( $is_eligible ) {
				$config = json_decode( $promo['action_config'], true ) ?: array();
				$desc = ucfirst( str_replace( '_', ' ', $promo['rule_type'] ) ) . ' promotion.';
				if ( $promo['rule_type'] === 'simple' ) {
					$val = isset( $config['discount_value'] ) ? $config['discount_value'] : '';
					$type = isset( $config['discount_type'] ) ? $config['discount_type'] : '';
					if ( $type === 'percentage' ) $desc = "Get {$val}% off.";
					elseif ( $type === 'fixed_cart' ) $desc = "Get \${$val} off the order.";
					elseif ( $type === 'fixed_product' ) $desc = "Get \${$val} off this product.";
				} elseif ( $promo['rule_type'] === 'bogo' ) {
					$buy = isset( $config['buy_qty'] ) ? $config['buy_qty'] : 1;
					$get = isset( $config['get_qty'] ) ? $config['get_qty'] : 1;
					$type = isset( $config['discount_type'] ) ? $config['discount_type'] : 'free';
					if ( $type === 'free' ) $desc = "Buy {$buy} get {$get} free.";
					elseif ( $type === 'percentage' ) {
						$val = isset( $config['discount_value'] ) ? $config['discount_value'] : '';
						$desc = "Buy {$buy} get {$get} at {$val}% off.";
					}
				}

				$impacts[] = array(
					'module'      => 'Promotions',
					'title'       => html_entity_decode( $promo['title'] ),
					'status'      => 'Active',
					'description' => $desc,
					'action_url'  => admin_url( 'admin.php?page=o100-promotions&edit=' . $promo['id'] ),
					'type'        => 'positive',
				);
			}
		}

		return $impacts;
	}

	public function render_page() {
		$allowed_tabs = array( 'categories', 'items', 'modifiers', 'publish', 'menu_rules' );
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'items';
		if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
			$active_tab = 'items';
		}

		// We need to load standard WP UI styles, plus our own
		wp_enqueue_style( 'o100-admin-css', O100_URL . 'assets/css/o100-admin.css', array(), time() );
		wp_enqueue_style( 'o100-fluent-admin-css', O100_URL . 'assets/css/o100-fluent-admin.css', array(), time() );
		
		wp_enqueue_script( 'alpinejs', 'https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js', [], null, true );
		
		// Configure Tailwind to disable preflight before loading the CDN
		wp_enqueue_script( 'tailwindcss', 'https://cdn.tailwindcss.com', [], null, false );
		wp_add_inline_script( 'tailwindcss', 'window.tailwind = { config: { corePlugins: { preflight: false }, theme: { extend: { colors: { primary: "#F59322", "primary-dark": "#d97b06" } } } } };', 'before' );
		wp_enqueue_style( 'font-awesome-4', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css', array(), '4.7.0' );
		
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		
		wp_enqueue_style( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css' );
		wp_enqueue_script( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array(), null, true );
		
		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'select2' );
		}

		// Inject legacy settings object required by Fluent Addons & Menu Builder
		wp_register_script( 'o100-menu-maker-legacy-env', '', array('jquery'), '', true );
		wp_enqueue_script( 'o100-menu-maker-legacy-env' );
		$opts = get_option('o100_misc', array());
		$labels = isset( $opts['o100_global_food_labels'] ) ? $opts['o100_global_food_labels'] : array();
		if ( is_string( $labels ) ) {
			$labels = json_decode( $labels, true );
		}
		if ( ! is_array( $labels ) ) {
			$labels = array();
		}
		


		wp_localize_script( 'o100-menu-maker-legacy-env', 'o100Settings', array(
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'adminNonce' => wp_create_nonce( 'o100_admin_nonce' ),
			'globalLabels' => $labels,
		) );
		

		
		// Setup the unified page header
		$page_title = __( 'Menu Management', 'order100' );
		$page_subtitle = __( 'Centrally manage your categories, menu items, and options.', 'order100' );

		// Render the header
		if ( class_exists( 'O100_Admin_Menu' ) ) {
			O100_Admin_Menu::render_page_header();
		}

		// Load main view wrapper
		include_once O100_PATH . 'core/menu-maker/views/view-menu-maker-main.php';
	}
}

O100_Menu_Maker_Admin::instance();
