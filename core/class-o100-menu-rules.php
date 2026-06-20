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

		// Cart and Checkout Validation
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_items' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_cart_items' ) );
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

		// Check Free Version Limits
		$is_premium = function_exists('O100_License') && O100_License()->is_premium();
		$pro_badge = function_exists('O100_License') ? O100_License()->get_pro_badge('Limit 1 rule in Free version') : '';
		
		$box_title = __( 'Order100 Menu Rules (Availability)', 'order100' );
		if ( ! $is_premium ) {
			$box_title .= ' ' . $pro_badge;
		}

		$cmb = new_cmb2_box( array(
			'id'           => 'o100_product_menu_rules',
			'title'        => $box_title,
			'object_types' => array( 'product' ),
			'context'      => 'normal',
			'priority'     => 'default',
		) );
		
		// If free, inject a check script
		if ( ! $is_premium ) {
			add_action( 'admin_footer', function() {
				global $post;
				if ( ! $post || $post->post_type !== 'product' ) return;
				
				// Count how many products have date rules
				global $wpdb;
				$count = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE (meta_key = 'o100_rule_days' OR meta_key = 'o100_rule_dates') AND meta_value != '' AND meta_value != 'a:0:{}'");
				
				// Is the current post already one of the rule havers?
				$has_rule = get_post_meta( $post->ID, 'o100_rule_days', true ) || get_post_meta( $post->ID, 'o100_rule_dates', true );
				
				if ( $count >= 1 && ! $has_rule ) {
					echo '<script>
					document.addEventListener("DOMContentLoaded", function() {
						var box = document.getElementById("o100_product_menu_rules");
						if (box) {
							var inputs = box.querySelectorAll("input, select, textarea");
							inputs.forEach(function(el) {
								el.disabled = true;
							});
							var title = box.querySelector("h2");
							if(title) {
								var notice = document.createElement("div");
								notice.style.background = "#fff7ed";
								notice.style.border = "1px solid #fed7aa";
								notice.style.padding = "10px 15px";
								notice.style.margin = "10px 0";
								notice.style.borderRadius = "6px";
								notice.style.color = "#c2410c";
								notice.innerHTML = "<strong>Limit Reached:</strong> The Free version allows Menu Rules on only 1 product. <a href=\"#\" onclick=\"event.preventDefault(); if(typeof o100ShowProModal === \'function\') { o100ShowProModal(\'Menu Rules (Schedule)\', \'The Free version is limited to 1 Menu Rule. Upgrade to Order100 Pro to schedule unlimited menus by date/time.\'); }\">Upgrade to PRO</a> to unlock unlimited rules.";
								title.parentNode.insertBefore(notice, title.nextSibling);
							}
							
							// If they try to click anywhere in the box
							box.addEventListener("click", function(e) {
								if(typeof o100ShowProModal === "function") {
									o100ShowProModal("Menu Rules (Schedule)", "The Free version is limited to 1 Menu Rule. Upgrade to Order100 Pro to schedule unlimited menus by date/time.");
								}
							}, true);
						}
					});
					</script>
					<style>
					.o100-half-row { width: 50% !important; float: left; box-sizing: border-box; clear: none !important; }
					.o100-half-row-last { width: 50% !important; float: left; box-sizing: border-box; clear: none !important; }
					</style>';
				}
			});
			
			// <fs_free>
			// Backend Physical Strip: Prevent saving if limit is reached
			add_filter('update_post_metadata', function($check, $object_id, $meta_key, $meta_value) {
				if ( $meta_key === 'o100_rule_days' || $meta_key === 'o100_rule_dates' ) {
					// Only block if they are trying to save actual data (not empty string or empty array)
					if ( ! empty( $meta_value ) && $meta_value !== 'a:0:{}' ) {
						global $wpdb;
						$count = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE (meta_key = 'o100_rule_days' OR meta_key = 'o100_rule_dates') AND meta_value != '' AND meta_value != 'a:0:{}'");
						$has_rule = get_post_meta( $object_id, 'o100_rule_days', true ) || get_post_meta( $object_id, 'o100_rule_dates', true );
						
						if ( $count >= 1 && ! $has_rule ) {
							return false; // Block saving
						}
					}
				}
				return $check;
			}, 10, 4);
			// </fs_free>
		}

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
				'name' => __( 'Step 1: Rule Action (Display Mode)', 'order100' ),
				'desc' => __( 'Choose how this rule affects product visibility and checkout.', 'order100' ),
				'id'   => 'o100_rule_step1_title',
				'type' => 'title',
			) );

			$cmb->add_field( array(
				'name'             => __( 'Rule Action', 'order100' ),
				'desc'             => __( 'Choose how this rule affects the product availability and checkout restrictions.', 'order100' ),
				'id'               => 'o100_rule_action',
				'type'             => 'radio_inline',
				'default'          => 'flexible_show',
				'options'          => array(
					'strict_show'   => __( 'Strict Show (Only visible & orderable during time)', 'order100' ),
					'flexible_show' => __( 'Flexible Show (Always visible, restricts checkout time)', 'order100' ),
					'hide'          => __( 'Hide (Unavailable during time)', 'order100' ),
				),
			) );

			$cmb->add_field( array(
				'name' => __( 'Step 2: Assign Dates', 'order100' ),
				'desc' => __( 'Choose whether this rule applies to recurring weekdays or specific dates.', 'order100' ),
				'id'   => 'o100_rule_step2_title_dates',
				'type' => 'title',
			) );

			$cmb->add_field( array(
				'name'    => __( 'Allowed Weekdays', 'order100' ),
				'desc'    => __( 'Select specific days of the week this product is affected. Leave empty for all days.', 'order100' ),
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
				'desc' => __( 'Comma-separated specific dates (YYYY-MM-DD) when this rule applies. E.g., 2026-12-25. Leave empty if no specific dates are required.', 'order100' ),
				'id'   => 'o100_rule_dates',
				'type' => 'text',
			) );

			$cmb->add_field( array(
				'name' => __( 'Step 3: Assign Time', 'order100' ),
				'desc' => __( 'Configure the time of day this rule is active.', 'order100' ),
				'id'   => 'o100_rule_step3_title_time',
				'type' => 'title',
			) );

			$cmb->add_field( array(
				'name' => __( 'Start Time (Optional)', 'order100' ),
				'desc' => __( 'Time of day this rule starts (e.g. 10:00). Leave blank for all day.', 'order100' ),
				'id'   => 'o100_rule_time_start',
				'type' => 'text_time',
				'attributes' => array(
					'placeholder' => 'HH:MM',
				),
				'row_classes' => 'o100-half-row',
			) );

			$cmb->add_field( array(
				'name' => __( 'End Time (Optional)', 'order100' ),
				'desc' => __( 'Time of day this rule ends (e.g. 14:00). Leave blank for all day.', 'order100' ),
				'id'   => 'o100_rule_time_end',
				'type' => 'text_time',
				'attributes' => array(
					'placeholder' => 'HH:MM',
				),
				'row_classes' => 'o100-half-row-last',
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
	 * Validate product when adding to cart
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity ) {
		$restriction = self::check_product_restriction( $product_id, 'cart' );
		if ( $restriction ) {
			wc_add_notice( sprintf( __( 'Sorry, "%s" cannot be added to your cart: %s.', 'order100' ), get_the_title( $product_id ), $restriction['message'] ), 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Validate items currently in the cart and at checkout
	 */
	public function validate_cart_items() {
		if ( ! function_exists('WC') || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['product_id'];
			$restriction = self::check_product_restriction( $product_id, 'checkout' );
			if ( $restriction ) {
				wc_add_notice( sprintf( __( 'Sorry, "%s" in your cart is currently restricted for your selected time: %s.', 'order100' ), get_the_title( $product_id ), $restriction['message'] ), 'error' );
			}
		}
	}

	/**
	 * Get the time badge string for UI display (e.g., "Available 11:00am - 2:00pm")
	 */
	public static function get_product_time_badge( $product_id ) {
		$p_t_start = get_post_meta( $product_id, 'o100_rule_time_start', true );
		$p_t_end   = get_post_meta( $product_id, 'o100_rule_time_end', true );
		if ( $p_t_start || $p_t_end ) {
			return self::format_time_badge( $p_t_start, $p_t_end );
		}

		$menu_rules = get_option('o100_menu_rules', array());
		$global_date_rules = isset( $menu_rules['o100_global_date_rules'] ) ? $menu_rules['o100_global_date_rules'] : array();
		if ( ! empty( $global_date_rules ) && is_array( $global_date_rules ) ) {
			$product_cats = wc_get_product_term_ids( $product_id, 'product_cat' );
			foreach ( $global_date_rules as $rule ) {
				$r_pro = isset($rule['o100_rule_products']) ? self::parse_ids($rule['o100_rule_products']) : array();
				$r_cat = isset($rule['o100_rule_categories']) ? self::parse_ids($rule['o100_rule_categories']) : array();
				$in_rule = in_array( (int) $product_id, $r_pro, true ) || ! empty( array_intersect( $product_cats, $r_cat ) );
				if ( $in_rule ) {
					$r_t_st = isset($rule['o100_rule_time_start']) ? $rule['o100_rule_time_start'] : '';
					$r_t_en = isset($rule['o100_rule_time_end']) ? $rule['o100_rule_time_end'] : '';
					if ( $r_t_st || $r_t_en ) {
						return self::format_time_badge( $r_t_st, $r_t_en );
					}
				}
			}
		}
		return false;
	}

	private static function format_time_badge( $start, $end ) {
		$fmt = function( $t ) {
			if ( empty($t) ) return '';
			$parts = explode( ':', $t );
			if ( count($parts) < 2 ) return $t;
			$h = (int) $parts[0];
			$m = $parts[1];
			$p = $h >= 12 ? 'pm' : 'am';
			$h12 = $h === 0 ? 12 : ( $h > 12 ? $h - 12 : $h );
			return $m === '00' ? $h12 . $p : $h12 . ':' . $m . $p;
		};
		if ( $start && $end ) {
			return sprintf( 'Available %s - %s', $fmt($start), $fmt($end) );
		} elseif ( $start ) {
			return sprintf( 'Available from %s', $fmt($start) );
		} elseif ( $end ) {
			return sprintf( 'Available until %s', $fmt($end) );
		}
		return false;
	}

	/**
	 * Check if a product is restricted by Menu Rules (Order Method or Date)
	 * Returns false if available, or an array with ['type', 'message'] if restricted.
	 * $context can be 'display', 'cart', or 'checkout'.
	 */
	public static function check_product_restriction( $product_id, $context = 'display' ) {
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
			$current_time_str = WC()->session->get( '_user_deli_time' );
			
			if ( ! $current_date_ts || ! is_numeric( $current_date_ts ) ) {
				$current_date_ts = current_time('timestamp');
			}
			if ( empty($current_time_str) ) {
				$time_check = current_time('H:i');
			} else {
				$time_check = gmdate('H:i', strtotime($current_time_str));
			}

			if ( $current_date_ts && is_numeric( $current_date_ts ) ) {
				$day_of_week = gmdate('D', (int)$current_date_ts);
				$full_date   = gmdate('Y-m-d', (int)$current_date_ts);

				// 2a. Filter via Product Metabox
				$p_days = get_post_meta( $product_id, 'o100_rule_days', true );
				$p_dates = get_post_meta( $product_id, 'o100_rule_dates', true );
				$p_action = get_post_meta( $product_id, 'o100_rule_action', true );
				if ( empty( $p_action ) ) $p_action = 'flexible_show'; // Default for legacy
				
				$p_t_start = get_post_meta( $product_id, 'o100_rule_time_start', true );
				$p_t_end = get_post_meta( $product_id, 'o100_rule_time_end', true );

				$has_p_rule = !empty($p_days) || !empty($p_dates) || !empty($p_t_start) || !empty($p_t_end);
				if ( $has_p_rule ) {
					$hits = true;
					if ( ! empty( $p_days ) && is_array( $p_days ) && ! in_array( $day_of_week, $p_days, true ) ) {
						$hits = false;
					}
					if ( ! empty( $p_dates ) ) {
						$dates_arr = is_array($p_dates) ? $p_dates : array_map( 'trim', explode( ',', $p_dates ) );
						if ( ! in_array( $full_date, $dates_arr, true ) ) {
							$hits = false;
						}
					}
					if ( $p_t_start || $p_t_end ) {
						if ( $p_t_start && $p_t_end ) {
							if ( $p_t_start > $p_t_end ) { // crosses midnight
								if ( $time_check < $p_t_start && $time_check > $p_t_end ) $hits = false;
							} else {
								if ( $time_check < $p_t_start || $time_check > $p_t_end ) $hits = false;
							}
						} elseif ( $p_t_start && $time_check < $p_t_start ) {
							$hits = false;
						} elseif ( $p_t_end && $time_check > $p_t_end ) {
							$hits = false;
						}
					}

					if ( $p_action === 'hide' ) {
						if ( $hits ) return array('type' => 'date', 'message' => esc_html__('Closed', 'order100'));
					} elseif ( $p_action === 'strict_show' ) {
						if ( ! $hits ) return array('type' => 'date', 'message' => esc_html__('Closed', 'order100'));
					} elseif ( $p_action === 'flexible_show' ) {
						// Flexible show ONLY restricts if we are validating a selected fulfillment time (cart/checkout)
						if ( $context !== 'display' ) {
							if ( ! $hits ) return array('type' => 'date', 'message' => esc_html__('Closed', 'order100'));
						}
					}
				}

				// 2b. Filter via Global Settings (Repeater)
				$global_date_rules = isset( $menu_rules['o100_global_date_rules'] ) ? $menu_rules['o100_global_date_rules'] : array();
				if ( ! empty( $global_date_rules ) && is_array( $global_date_rules ) ) {
					$has_strict_show_rule = false;
					$has_flexible_show_rule = false;
					
					$is_strict_allowed = false;
					$is_flexible_allowed = false;
					$is_hidden_today = false;

					foreach ( $global_date_rules as $rule ) {
						$r_pro = isset($rule['o100_rule_products']) ? self::parse_ids($rule['o100_rule_products']) : array();
						$r_cat = isset($rule['o100_rule_categories']) ? self::parse_ids($rule['o100_rule_categories']) : array();
						
						$in_rule = in_array( (int) $product_id, $r_pro, true ) || ! empty( array_intersect( $product_cats, $r_cat ) );
						
						if ( $in_rule ) {
							$r_type   = isset($rule['o100_rule_type']) ? $rule['o100_rule_type'] : 'weekdays';
							$r_days   = isset($rule['o100_rule_days']) ? (array)$rule['o100_rule_days'] : array();
							$r_dates  = isset($rule['o100_rule_date_range']) ? $rule['o100_rule_date_range'] : '';
							$r_action = isset($rule['o100_rule_action']) ? $rule['o100_rule_action'] : 'flexible_show';
							$r_t_st   = isset($rule['o100_rule_time_start']) ? $rule['o100_rule_time_start'] : '';
							$r_t_en   = isset($rule['o100_rule_time_end']) ? $rule['o100_rule_time_end'] : '';

							if ( $r_action === 'strict_show' ) $has_strict_show_rule = true;
							if ( $r_action === 'flexible_show' ) $has_flexible_show_rule = true;

							$day_match = empty($r_days) || in_array( $day_of_week, $r_days, true );
							$date_match = true;
							if ( ! empty($r_dates) ) {
								if ( strpos($r_dates, ' to ') !== false ) {
									$parts = explode(' to ', $r_dates);
									$start = trim($parts[0]);
									$end = trim($parts[1]);
									$date_match = ($full_date >= $start && $full_date <= $end);
								} else {
									$dates_arr = array_map( 'trim', explode( ',', $r_dates ) );
									$date_match = in_array( $full_date, $dates_arr, true );
								}
							}
							
							// Evaluate according to rule_type
							if ( $r_type === 'weekdays' ) $hits = $day_match;
							elseif ( $r_type === 'date_range' ) $hits = $date_match;
							else $hits = $day_match && $date_match;

							// Time logic
							if ( $hits && ( $r_t_st || $r_t_en ) ) {
								if ( $r_t_st && $r_t_en ) {
									if ( $r_t_st > $r_t_en ) {
										if ( $time_check < $r_t_st && $time_check > $r_t_en ) $hits = false;
									} else {
										if ( $time_check < $r_t_st || $time_check > $r_t_en ) $hits = false;
									}
								} elseif ( $r_t_st && $time_check < $r_t_st ) {
									$hits = false;
								} elseif ( $r_t_en && $time_check > $r_t_en ) {
									$hits = false;
								}
							}

							if ( $r_action === 'hide' ) {
								if ( $hits ) $is_hidden_today = true;
							} elseif ( $r_action === 'strict_show' ) {
								if ( $hits ) $is_strict_allowed = true;
							} elseif ( $r_action === 'flexible_show' ) {
								if ( $hits ) $is_flexible_allowed = true;
							}
						}
					}

					if ( $is_hidden_today ) {
						return array('type' => 'date', 'message' => esc_html__('Closed', 'order100'));
					}
					if ( $has_strict_show_rule && ! $is_strict_allowed ) {
						return array('type' => 'date', 'message' => esc_html__('Closed', 'order100'));
					}
					if ( $has_flexible_show_rule && ! $is_flexible_allowed && $context !== 'display' ) {
						return array('type' => 'date', 'message' => esc_html__('Closed', 'order100'));
					}
				}
			}
		}

		// 3. Menu Maker Selling Rules (time-of-day, date range, weekday restrictions)
		$mm_time_start = get_post_meta( $product_id, 'o100_menu_rule_time_start', true );
		$mm_time_end   = get_post_meta( $product_id, 'o100_menu_rule_time_end', true );
		$mm_date_start = get_post_meta( $product_id, 'o100_menu_rule_date_start', true );
		$mm_date_end   = get_post_meta( $product_id, 'o100_menu_rule_date_end', true );
		$mm_days       = get_post_meta( $product_id, 'o100_menu_rule_days', true );

		// 3a. Date range restriction
		if ( $mm_date_start || $mm_date_end ) {
			$today = wp_date( 'Y-m-d' );
			$is_outside = false;
			if ( $mm_date_start && $today < $mm_date_start ) $is_outside = true;
			if ( $mm_date_end && $today > $mm_date_end )     $is_outside = true;
			if ( $is_outside ) {
				$msg_parts = array();
				if ( $mm_date_start ) $msg_parts[] = wp_date( 'M j', strtotime( $mm_date_start ) );
				if ( $mm_date_end )   $msg_parts[] = wp_date( 'M j', strtotime( $mm_date_end ) );
				$msg = count( $msg_parts ) === 2
					? sprintf( 'Available %s – %s', $msg_parts[0], $msg_parts[1] )
					: sprintf( 'Available from %s', $msg_parts[0] );
				return array( 'type' => 'time', 'message' => esc_html( $msg ) );
			}
		}

		// 3b. Weekday restriction
		if ( is_array( $mm_days ) && count( $mm_days ) > 0 && count( $mm_days ) < 7 ) {
			$current_day = strtolower( wp_date( 'D' ) ); // 'mon', 'tue', etc.
			if ( ! in_array( $current_day, $mm_days, true ) ) {
				// Build human-readable day list
				$day_labels = array( 'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun' );
				$readable = array();
				foreach ( $mm_days as $d ) {
					if ( isset( $day_labels[ $d ] ) ) $readable[] = $day_labels[ $d ];
				}
				$msg = 'Available ' . implode( ', ', $readable );
				return array( 'type' => 'time', 'message' => esc_html( $msg ) );
			}
		}

		// 3c. Time-of-day restriction
		if ( $mm_time_start || $mm_time_end ) {
			$now = wp_date( 'H:i' );
			$is_outside = false;
			if ( $mm_time_start && $mm_time_end ) {
				if ( $mm_time_start > $mm_time_end ) {
					// Crosses midnight (e.g., 22:00 - 02:00)
					if ( $now < $mm_time_start && $now > $mm_time_end ) $is_outside = true;
				} else {
					if ( $now < $mm_time_start || $now > $mm_time_end ) $is_outside = true;
				}
			} elseif ( $mm_time_start && $now < $mm_time_start ) {
				$is_outside = true;
			} elseif ( $mm_time_end && $now > $mm_time_end ) {
				$is_outside = true;
			}
			if ( $is_outside ) {
				$fmt = function( $t ) {
					$parts = explode( ':', $t );
					$h = (int) $parts[0];
					$m = $parts[1];
					$p = $h >= 12 ? 'pm' : 'am';
					$h12 = $h === 0 ? 12 : ( $h > 12 ? $h - 12 : $h );
					return $m === '00' ? $h12 . $p : $h12 . ':' . $m . $p;
				};
				$msg_parts = array();
				if ( $mm_time_start ) $msg_parts[] = $fmt( $mm_time_start );
				if ( $mm_time_end )   $msg_parts[] = $fmt( $mm_time_end );
				$msg = count( $msg_parts ) === 2
					? sprintf( 'Available %s – %s', $msg_parts[0], $msg_parts[1] )
					: sprintf( 'Available from %s', $msg_parts[0] );
				return array( 'type' => 'time', 'message' => esc_html( $msg ) );
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
