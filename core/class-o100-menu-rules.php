<?php
/**
 * Menu Rules & Filtering Core Logic
 *
 * @package Order100
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class O100_Menu_Rules {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'cmb2_admin_init', array( $this, 'register_metaboxes' ) );
		
		// WooCommerce Query Filters
		add_action( 'woocommerce_product_query', array( $this, 'filter_product_query' ) );
		add_filter( 'woocommerce_shortcode_products_query', array( $this, 'filter_shortcode_query' ), 10, 3 );
	}

	/**
	 * Register CMB2 Metaboxes for Products
	 */
	public function register_metaboxes() {
		$menu_rules = get_option('o100_menu_rules', array());
		$enable_method = !empty($menu_rules['o100_menu_method']) && $menu_rules['o100_menu_method'] === 'on';
		$enable_date = !empty($menu_rules['o100_menu_date']) && $menu_rules['o100_menu_date'] === 'on';

		if ( ! $enable_method && ! $enable_date ) {
			return; // Neither feature is globally enabled, so hide the metabox
		}

		$cmb = new_cmb2_box( array(
			'id'           => 'o100_product_menu_rules',
			'title'        => __( 'Order100 Menu Rules (Availability)', 'order100' ),
			'object_types' => array( 'product' ),
			'context'      => 'normal',
			'priority'     => 'default',
		) );

		if ( $enable_method ) {
			$options = array();
			$deli_opts = get_option('o100_delivery', array());
			$pick_opts = get_option('o100_pickup', array());
			$dine_opts = get_option('o100_dinein', array());

			if ( !empty($deli_opts['o100_enable_delivery']) && $deli_opts['o100_enable_delivery'] === 'on' ) {
				$options['delivery'] = __( 'Delivery', 'order100' );
			}
			if ( !empty($pick_opts['o100_enable_pickup']) && $pick_opts['o100_enable_pickup'] === 'on' ) {
				$options['pickup'] = __( 'Pickup', 'order100' );
			}
			if ( !empty($dine_opts['o100_enable_dinein']) && $dine_opts['o100_enable_dinein'] === 'on' ) {
				$options['dinein'] = __( 'Dine-in', 'order100' );
			}

			$cmb->add_field( array(
				'name'    => __( 'Allowed Order Methods', 'order100' ),
				'desc'    => __( 'Leave empty to allow all active methods. Otherwise, select ONLY the methods allowed for this product.', 'order100' ),
				'id'      => 'o100_rule_methods',
				'type'    => 'multicheck_inline',
				'options' => $options,
			) );
		}

		if ( $enable_date ) {
			$cmb->add_field( array(
				'name'    => __( 'Allowed Weekdays', 'order100' ),
				'desc'    => __( 'Select specific days of the week this product is available. Leave empty for all days.', 'order100' ),
				'id'      => 'o100_rule_days',
				'type'    => 'multicheck_inline',
				'options' => array(
					'Mon' => __( 'Monday', 'order100' ),
					'Tue' => __( 'Tuesday', 'order100' ),
					'Wed' => __( 'Wednesday', 'order100' ),
					'Thu' => __( 'Thursday', 'order100' ),
					'Fri' => __( 'Friday', 'order100' ),
					'Sat' => __( 'Saturday', 'order100' ),
					'Sun' => __( 'Sunday', 'order100' ),
				),
			) );

			$cmb->add_field( array(
				'name' => __( 'Allowed Specific Dates', 'order100' ),
				'desc' => __( 'Comma-separated specific dates (YYYY-MM-DD) when this product is available. E.g., 2026-12-25, 2026-12-26. Leave empty if no specific dates are required.', 'order100' ),
				'id'   => 'o100_rule_dates',
				'type' => 'text',
			) );
		}
	}

	/**
	 * Check if a product is disabled from food fields
	 */
	public static function is_food_disabled( $product_id ) {
		$menu_rules = get_option('o100_menu_rules', array());
		
		if ( empty( $menu_rules['o100_enable_standard_ecom'] ) || $menu_rules['o100_enable_standard_ecom'] !== 'on' ) {
			return false;
		}

		// 1. Check Product ID
		$disable_pro = isset($menu_rules['o100_disable_food_pro']) ? $menu_rules['o100_disable_food_pro'] : '';
		if ( ! empty( $disable_pro ) ) {
			if ( is_array( $disable_pro ) ) {
				$pro_ids = array_map( 'intval', $disable_pro );
			} else {
				$pro_ids = array_map( 'intval', array_filter( explode( ',', $disable_pro ) ) );
			}
			if ( in_array( (int) $product_id, $pro_ids, true ) ) {
				return true;
			}
		}

		// 2. Check Category ID
		$disable_cat = isset($menu_rules['o100_disable_food_cat']) ? $menu_rules['o100_disable_food_cat'] : '';
		if ( ! empty( $disable_cat ) ) {
			if ( is_array( $disable_cat ) ) {
				$cat_ids = array_map( 'intval', $disable_cat );
			} else {
				$cat_ids = array_map( 'intval', array_filter( explode( ',', $disable_cat ) ) );
			}
			$product_cats = wc_get_product_term_ids( $product_id, 'product_cat' );
			if ( ! empty( array_intersect( $product_cats, $cat_ids ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * DEPRECATED: Apply Method & Date filtering to WooCommerce queries
	 * Left empty to prevent fatal errors if third-party plugins call this.
	 * Restrictions are now handled at the frontend render level via check_product_restriction()
	 */
	public function filter_product_query( $q ) {
		return;
	}

	public function filter_shortcode_query( $args, $atts, $type ) {
		return $args;
	}

	/**
	 * Check if a product is restricted by Menu Rules (Order Method or Date)
	 * Returns false if available, or an array with ['type', 'message'] if restricted.
	 */
	public static function check_product_restriction( $product_id ) {
		if ( self::is_food_disabled( $product_id ) ) {
			return array(
				'type' => 'ecom',
				'message' => esc_html__( 'Unavailable', 'order100' )
			);
		}

		$menu_rules = get_option('o100_menu_rules', array());
		$enable_method = !empty($menu_rules['o100_menu_method']) && $menu_rules['o100_menu_method'] === 'on';
		$enable_date = !empty($menu_rules['o100_menu_date']) && $menu_rules['o100_menu_date'] === 'on';

		if ( ! $enable_method && ! $enable_date ) return false;

		$product_cats = wp_get_post_terms( $product_id, 'product_cat', array('fields' => 'ids') );
		if ( is_wp_error( $product_cats ) ) $product_cats = array();

		// 1. Order Method Filter
		if ( $enable_method && function_exists('WC') && isset(WC()->session) ) {
			$current_method = WC()->session->get( '_user_order_method' );
			if ( $current_method === 'takeaway' ) $current_method = 'pickup';

			if ( $current_method ) {
				// 1a. Filter via Product Metabox
				$methods = get_post_meta( $product_id, 'o100_rule_methods', true );
				if ( ! empty( $methods ) && is_array( $methods ) ) {
					if ( ! in_array( $current_method, $methods, true ) ) {
						return array(
							'type' => 'method',
							'message' => $current_method === 'delivery' ? esc_html__('Pickup Only', 'order100') : esc_html__('Delivery Only', 'order100')
						);
					}
				}

				// 1b. Filter via Global Settings
				$is_restricted_by_global_method = false;
				
				$deli_opts = get_option('o100_delivery', array());
				$pick_opts = get_option('o100_pickup', array());
				$deli_on = !empty($deli_opts['o100_enable_delivery']) && $deli_opts['o100_enable_delivery'] === 'on';
				$pick_on = !empty($pick_opts['o100_enable_pickup']) && $pick_opts['o100_enable_pickup'] === 'on';

				if ( $current_method === 'delivery' ) {
					if ( $pick_on ) {
						$pickup_pro = isset($menu_rules['o100_pickup_only_pro']) ? self::parse_ids($menu_rules['o100_pickup_only_pro']) : array();
						$pickup_cat = isset($menu_rules['o100_pickup_only_cat']) ? self::parse_ids($menu_rules['o100_pickup_only_cat']) : array();
						
						if ( in_array( (int) $product_id, $pickup_pro, true ) || ! empty( array_intersect( $product_cats, $pickup_cat ) ) ) {
							return array(
								'type' => 'method',
								'message' => esc_html__('Pickup Only', 'order100')
							);
						}
					}
				} elseif ( $current_method === 'pickup' ) {
					if ( $deli_on ) {
						$deli_pro = isset($menu_rules['o100_delivery_only_pro']) ? self::parse_ids($menu_rules['o100_delivery_only_pro']) : array();
						$deli_cat = isset($menu_rules['o100_delivery_only_cat']) ? self::parse_ids($menu_rules['o100_delivery_only_cat']) : array();
						
						if ( in_array( (int) $product_id, $deli_pro, true ) || ! empty( array_intersect( $product_cats, $deli_cat ) ) ) {
							return array(
								'type' => 'method',
								'message' => esc_html__('Delivery Only', 'order100')
							);
						}
					}
				}
			}
		}

		// 2. Date Filter
		if ( $enable_date && function_exists('WC') && isset(WC()->session) ) {
			$current_date_ts = WC()->session->get( '_user_deli_date' );
			if ( ! $current_date_ts || ! is_numeric( $current_date_ts ) ) {
				$current_date_ts = current_time('timestamp');
			}

			if ( $current_date_ts && is_numeric( $current_date_ts ) ) {
				$day_of_week = gmdate('D', (int)$current_date_ts);
				$full_date   = gmdate('Y-m-d', (int)$current_date_ts);

				// 2a. Filter via Product Metabox
				$p_days = get_post_meta( $product_id, 'o100_rule_days', true );
				if ( ! empty( $p_days ) && is_array( $p_days ) ) {
					if ( ! in_array( $day_of_week, $p_days, true ) ) {
						return array(
							'type' => 'date',
							'message' => esc_html__('Closed', 'order100')
						);
					}
				}

				$p_dates = get_post_meta( $product_id, 'o100_rule_dates', true );
				if ( ! empty( $p_dates ) ) {
					$dates_arr = is_array($p_dates) ? $p_dates : array_map( 'trim', explode( ',', $p_dates ) );
					if ( ! in_array( $full_date, $dates_arr, true ) ) {
						return array(
							'type' => 'date',
							'message' => esc_html__('Closed', 'order100')
						);
					}
				}

				// 2b. Filter via Global Settings (Repeater)
				$global_date_rules = isset( $menu_rules['o100_global_date_rules'] ) ? $menu_rules['o100_global_date_rules'] : array();
				if ( ! empty( $global_date_rules ) && is_array( $global_date_rules ) ) {
					$is_restricted_by_some_rule = false;
					$is_allowed_today = false;

					foreach ( $global_date_rules as $rule ) {
						$r_pro = isset($rule['o100_rule_products']) ? self::parse_ids($rule['o100_rule_products']) : array();
						$r_cat = isset($rule['o100_rule_categories']) ? self::parse_ids($rule['o100_rule_categories']) : array();
						
						$in_rule = in_array( (int) $product_id, $r_pro, true ) || ! empty( array_intersect( $product_cats, $r_cat ) );
						
						if ( $in_rule ) {
							$is_restricted_by_some_rule = true;
							$rule_matches = false;
							$r_days = isset($rule['o100_rule_days']) ? $rule['o100_rule_days'] : array();
							if ( ! empty($r_days) && in_array( $day_of_week, (array) $r_days, true ) ) {
								$rule_matches = true;
							}
							
							$r_dates = isset($rule['o100_rule_dates']) ? $rule['o100_rule_dates'] : '';
							if ( ! empty($r_dates) ) {
								$dates_arr = is_array($r_dates) ? $r_dates : array_map( 'trim', explode( ',', $r_dates ) );
								if ( in_array( $full_date, $dates_arr, true ) ) {
									$rule_matches = true;
								}
							}

							if ( $rule_matches ) {
								$is_allowed_today = true;
							}
						}
					}

					if ( $is_restricted_by_some_rule && ! $is_allowed_today ) {
						return array(
							'type' => 'date',
							'message' => esc_html__('Closed', 'order100')
						);
					}
				}
			}
		}

		return false;
	}



	/**
	 * Helper: parse string or array into flat array of integer IDs
	 */
	private static function parse_ids( $data ) {
		if ( empty( $data ) ) return array();
		if ( is_array( $data ) ) {
			return array_map( 'intval', $data );
		}
		return array_map( 'intval', array_filter( explode( ',', $data ) ) );
	}

}

// TS: 20260105142737

// TS: 20260412123715
