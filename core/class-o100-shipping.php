<?php
/**
 * O100 Native Shipping Engine
 *
 * Handles delivery fee calculation, minimum order enforcement,
 * tiered distance/postcode fees, and free shipping progress display.
 *
 * Completely replaces the legacy woo-exfood inc/shipping.php engine.
 * Reads exclusively from `o100_delivery` / `o100_pickup` option tables.
 *
 * @package Order100
 * @since   1.3
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class O100_Shipping {

	/**
	 * Singleton instance
	 * @var O100_Shipping
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 * @return O100_Shipping
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Cached delivery options
	 * @var array|null
	 */
	private $delivery_opts = null;

	/**
	 * Cached pickup options
	 * @var array|null
	 */
	private $pickup_opts = null;

	/**
	 * Stores the original delivery fee when free threshold is met
	 * @var float|null
	 */
	private $waived_original_fee = null;

	/**
	 * Constructor — hook into WooCommerce and neutralize legacy engine
	 */
	public function __construct() {
		// ── Sync our order method AFTER legacy init:10 ──
		// Let exwf_clear_user_address run first (sets defaults for new visitors)
		// then override with our value if user already selected via Entry Modal
		add_action( 'init', array( $this, 'sync_order_method_to_legacy' ), 20 );

		// ── Neutralize legacy shipping fee/minimum hooks ──
		add_action( 'wp_loaded', array( $this, 'disable_legacy_shipping_hooks' ), 0 );

		// ── Native Delivery Fee Calculation ──
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'calculate_delivery_fee' ), 10 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'calculate_dynamic_fees' ), 20 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'calculate_tip_fee' ), 30 );

		// ── Free Delivery Strikethrough Display ──
		add_filter( 'woocommerce_cart_totals_fee_html', array( $this, 'render_free_delivery_strikethrough' ), 10, 2 );

		// ── Minimum Order & Distance Enforcement ──
		add_action( 'woocommerce_checkout_process', array( $this, 'enforce_minimum_order' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'enforce_delivery_distance' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'display_minimum_order_notice' ), 8 );

		// ── Free Shipping Progress ──
		add_action( 'woocommerce_widget_shopping_cart_before_buttons', array( $this, 'display_free_shipping_progress' ), 999 );
		add_action( 'woocommerce_widget_shopping_cart_before_buttons', array( $this, 'display_minimum_order_sidebar_notice' ), 998 );

		// ── Parse User Distance from Checkout ──
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'parse_distance_from_checkout' ) );
	}



	/**
	 * Disable legacy woo-exfood shipping fee hooks (these fire later, so wp_loaded is fine)
	 */
	public function disable_legacy_shipping_hooks() {
		remove_action( 'woocommerce_cart_calculate_fees', 'exwd_add_shipping_fee' );
		remove_action( 'woocommerce_before_checkout_form', 'exwf_minimum_amount_fee_deli' );
		remove_action( 'woocommerce_before_cart', 'exwf_minimum_amount_fee_deli' );
		remove_action( 'woocommerce_widget_shopping_cart_before_buttons', 'exwf_minimum_amount_free_deli_sidecart', 999 );
	}

	/**
	 * Sync _o100_order_method → _user_order_method at init:20
	 * Runs AFTER legacy init:10 as a safety net to ensure our value always wins
	 */
	public function sync_order_method_to_legacy() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) ) {
			return;
		}
		$o100_method = WC()->session->get( '_o100_order_method' );
		if ( ! empty( $o100_method ) ) {
			// Legacy uses 'takeaway' while O100 UI uses 'pickup'
			$legacy_method = ( $o100_method === 'pickup' ) ? 'takeaway' : $o100_method;
			WC()->session->set( '_user_order_method', $legacy_method );
		}
	}

	// ─────────────────────────────────────────────
	//  OPTIONS HELPERS
	// ─────────────────────────────────────────────

	/**
	 * Get delivery options (cached)
	 * @return array
	 */
	private function get_delivery_opts() {
		if ( $this->delivery_opts === null ) {
			$this->delivery_opts = get_option( 'o100_delivery', array() );
		}
		return $this->delivery_opts;
	}

	/**
	 * Get pickup options (cached)
	 * @return array
	 */
	private function get_pickup_opts() {
		if ( $this->pickup_opts === null ) {
			$this->pickup_opts = get_option( 'o100_pickup', array() );
		}
		return $this->pickup_opts;
	}

	/**
	 * Get the current order method from native O100 session
	 * @return string  'delivery' | 'takeaway' | 'pickup' | 'dinein'
	 */
	private function get_order_method() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) ) {
			return 'delivery';
		}
		$method = WC()->session->get( '_o100_order_method' );
		return $method ? $method : 'delivery';
	}

	/**
	 * Check if the current order method is a delivery type
	 * @return bool
	 */
	private function is_delivery() {
		return $this->get_order_method() === 'delivery';
	}

	// ─────────────────────────────────────────────
	//  DELIVERY FEE CALCULATION
	// ─────────────────────────────────────────────

	/**
	 * Calculate and add delivery fee to WooCommerce cart
	 *
	 * Hooked to: woocommerce_cart_calculate_fees
	 */
	public function calculate_delivery_fee() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! $this->is_delivery() ) {
			return;
		}

		$opts = $this->get_delivery_opts();
		if ( empty( $opts['o100_enable_delivery'] ) || $opts['o100_enable_delivery'] !== 'on' ) {
			return;
		}

		// Start with global delivery fee
		$fee = isset( $opts['o100_shipping_fee'] ) ? $opts['o100_shipping_fee'] : '';
		$free_threshold = isset( $opts['o100_shipping_freemax'] ) ? $opts['o100_shipping_freemax'] : '';

		// ── Distance Restriction Enforcement ──
		$user_distance = WC()->session ? WC()->session->get( '_user_distance' ) : null;
		
		if ( $user_distance !== null && is_numeric( $user_distance ) ) {
			$max_allowed = '';
			
			// 1. Check Location-specific override
			$loc_selected = $this->get_selected_location_id();
			if ( $loc_selected ) {
				$loc_max = get_post_meta( $loc_selected, 'o100_distance_restrict', true );
				if ( $loc_max !== '' ) {
					$max_allowed = floatval( $loc_max );
				}
			}
			
			// 2. Check Global Restriction
			if ( $max_allowed === '' && isset( $opts['o100_deli_dis'] ) && $opts['o100_deli_dis'] !== '' ) {
				$max_allowed = floatval( $opts['o100_deli_dis'] );
			}
			
			// 3. Fallback to max tiered distance if tiered rules are enabled
			if ( $max_allowed === '' && isset( $opts['o100_enable_shp_km'] ) && $opts['o100_enable_shp_km'] === 'on' && isset( $opts['o100_shp_km_loc'] ) && is_array( $opts['o100_shp_km_loc'] ) ) {
				$max_tier = 0;
				foreach ( $opts['o100_shp_km_loc'] as $tier ) {
					if ( isset( $tier['max_distance'] ) && floatval( $tier['max_distance'] ) > $max_tier ) {
						$max_tier = floatval( $tier['max_distance'] );
					}
				}
				if ( $max_tier > 0 ) {
					$max_allowed = $max_tier;
				}
			}
			
			// Enforce restriction
			if ( $max_allowed !== '' && $user_distance > $max_allowed ) {
				$err_msg = sprintf( __( 'We\'re sorry! Your location (%.2f km) is outside our delivery range of %.2f km. Please consider switching to Pickup to place your order.', 'order100' ), $user_distance, $max_allowed );
				WC()->session->set( 'o100_distance_error', $err_msg );
				return; // Abort calculation, no delivery fee will be applied
			} else {
				WC()->session->set( 'o100_distance_error', '' );
			}
		}

		$zone_method = isset( $opts['o100_limit_shp'] ) ? $opts['o100_limit_shp'] : 'radius';

		// ── Tiered Distance Fees override global ──
		if ( $zone_method !== 'postcode' ) {
			$tiered = $this->get_tiered_distance_fee( $opts );
			if ( $tiered !== null ) {
				$fee            = $tiered['fee'];
				$free_threshold = isset( $tiered['free'] ) && $tiered['free'] !== '' ? $tiered['free'] : $free_threshold;
			}
		} else {
			// ── Tiered Postcode Fees override global ──
			$tiered = $this->get_tiered_postcode_fee( $opts );
			if ( $tiered !== null ) {
				$fee            = $tiered['fee'];
				$free_threshold = isset( $tiered['free'] ) && $tiered['free'] !== '' ? $tiered['free'] : $free_threshold;
			}
		}

		// ── Location-level override for base delivery fee ──
		$loc_selected = $this->get_selected_location_id();
		if ( $loc_selected && $fee !== '' && is_numeric( $fee ) ) {
			$loc_fee_action = get_post_meta( $loc_selected, 'o100_fee_action', true );
			if ( $loc_fee_action && $loc_fee_action !== 'none' ) {
				$loc_fee_type = get_post_meta( $loc_selected, 'o100_fee_type', true );
				$loc_fee_val = floatval( get_post_meta( $loc_selected, 'o100_fee_val', true ) );
				
				$adjustment = 0;
				if ( $loc_fee_type === 'percent' ) {
					$adjustment = floatval( $fee ) * ( $loc_fee_val / 100 );
				} else {
					$adjustment = $loc_fee_val;
				}
				
				if ( $loc_fee_action === 'subtract' || $loc_fee_action === 'discount' ) {
					$fee = max( 0, floatval( $fee ) - $adjustment );
				} elseif ( $loc_fee_action === 'add' || $loc_fee_action === 'surcharge' ) {
					$fee = floatval( $fee ) + $adjustment;
				}
			}
		}

		// ── Apply extensibility filter (for future time-based surcharge etc.) ──
		$order_method = $this->get_order_method();
		$fee = apply_filters( 'o100_delivery_fee_amount', $fee, $order_method, $opts );
		$free_threshold = apply_filters( 'o100_delivery_free_threshold', $free_threshold, $order_method, $opts );

		// ── Apply to cart ──
		if ( $fee !== '' && is_numeric( $fee ) ) {
			$original_fee = floatval( $fee );
			$subtotal = apply_filters( 'exwf_total_cart_price_fee', WC()->cart->get_subtotal() );

			// Free delivery threshold check
			$is_free = false;
			if ( $free_threshold !== '' && is_numeric( $free_threshold ) && $subtotal >= floatval( $free_threshold ) ) {
				$is_free = true;
				$this->waived_original_fee = $original_fee;
				$fee = 0;
			}

			// CRM VIP Free Delivery Check
			if ( ! $is_free && class_exists( 'O100_Customers_DB' ) && is_user_logged_in() ) {
				$free_tags_str = isset( $opts['o100_delivery_free_tags'] ) ? $opts['o100_delivery_free_tags'] : '';
				if ( ! empty( $free_tags_str ) ) {
					$current_user_tags = O100_Customers_DB::get_customer_tags( get_current_user_id() );
					if ( ! empty( $current_user_tags ) ) {
						$free_tags = array_map('trim', explode(',', $free_tags_str));
						if ( array_intersect( $current_user_tags, $free_tags ) ) {
							$is_free = true;
							$this->waived_original_fee = $original_fee;
							$fee = 0;
						}
					}
				}

				// Advanced CRM Rules Engine Integration (Order100 v2)
				if ( ! $is_free && class_exists( 'O100_Privilege_Manager' ) ) {
					$context = array(
						'branch'     => $this->get_selected_location_id(),
						'order_type' => $order_method,
						'subtotal'   => $subtotal,
						'timestamp'  => current_time( 'timestamp' ),
					);
					if ( O100_Privilege_Manager::has_privilege( get_current_user_id(), 'delivery', 'free_shipping', $context ) ) {
						$is_free = true;
						$this->waived_original_fee = $original_fee;
						$fee = 0;
					}
				}
			}

			$tax_on_fee = apply_filters( 'o100_delivery_fee_taxable', true );
			WC()->cart->add_fee( __( 'Delivery Fee', 'order100' ), floatval( $fee ), $tax_on_fee, '' );
		}
	}

	/**
	 * Calculate dynamic fees (Surcharge/Discount) for Delivery and Pickup
	 * Hooked to: woocommerce_cart_calculate_fees
	 */
	public function calculate_dynamic_fees() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$order_method = $this->get_order_method();
		
		$fee_action = 'none';
		$fee_type = 'percent';
		$fee_val = 0;
		$exclu_sur = array();

		$fee_label = '';

		if ( $order_method === 'delivery' ) {
			$opts = $this->get_delivery_opts();
			if ( empty( $opts['o100_enable_delivery'] ) || $opts['o100_enable_delivery'] !== 'on' ) {
				return;
			}
			$fee_action = isset($opts['o100_delivery_fee_action']) ? $opts['o100_delivery_fee_action'] : 'none';
			$fee_type = isset($opts['o100_delivery_fee_type']) ? $opts['o100_delivery_fee_type'] : 'percent';
			$fee_val = isset($opts['o100_delivery_fee_val']) ? floatval($opts['o100_delivery_fee_val']) : 0;
			$fee_label = isset($opts['o100_delivery_fee_label']) ? $opts['o100_delivery_fee_label'] : '';
			$exclu_sur = isset($opts['o100_delivery_exclu_sur']) && is_array($opts['o100_delivery_exclu_sur']) ? $opts['o100_delivery_exclu_sur'] : array();
		} elseif ( $order_method === 'pickup' || $order_method === 'takeaway' || $order_method === 'dinein' ) {
			$opts = $this->get_pickup_opts();
			// Note: If dine-in, we might want to check o100_enable_dinein, but for now we follow pickup settings
			if ( empty( $opts['o100_enable_pickup'] ) || $opts['o100_enable_pickup'] !== 'on' ) {
				return;
			}
			$fee_action = isset($opts['o100_pickup_fee_action']) ? $opts['o100_pickup_fee_action'] : 'none';
			$fee_type = isset($opts['o100_pickup_fee_type']) ? $opts['o100_pickup_fee_type'] : 'percent';
			$fee_val = isset($opts['o100_pickup_fee_val']) ? floatval($opts['o100_pickup_fee_val']) : 0;
			$fee_label = isset($opts['o100_pickup_fee_label']) ? $opts['o100_pickup_fee_label'] : '';
			$exclu_sur = isset($opts['o100_exclu_sur']) && is_array($opts['o100_exclu_sur']) ? $opts['o100_exclu_sur'] : array();
		}

		if ( $fee_action === 'none' || $fee_val <= 0 ) {
			return;
		}

		// Calculate applicable subtotal (excluding specific categories)
		$applicable_subtotal = 0;
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['product_id'];
			
			$is_excluded = false;
			if ( $fee_type === 'percent' && ! empty( $exclu_sur ) ) {
				$terms = wc_get_product_term_ids( $product_id, 'product_cat' );
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term_id ) {
						// exclu_sur values are strings from $_POST/options
						if ( in_array( strval($term_id), $exclu_sur ) || in_array( intval($term_id), $exclu_sur, true ) ) {
							$is_excluded = true;
							break;
						}
					}
				}
			}
			
			if ( ! $is_excluded ) {
				$applicable_subtotal += $cart_item['line_subtotal']; // line_subtotal does not include tax
			}
		}

		if ( $applicable_subtotal <= 0 && $fee_type === 'percent' ) {
			return;
		}

		$calculated_amount = 0;
		if ( $fee_type === 'percent' ) {
			$calculated_amount = $applicable_subtotal * ( $fee_val / 100 );
		} else {
			$calculated_amount = $fee_val;
		}

		if ( $calculated_amount > 0 ) {
			// Determine name based on method and action
			$fee_name = '';
			if ( $order_method === 'delivery' ) {
				$fee_name = $fee_action === 'discount' ? __( 'Delivery Discount', 'order100' ) : __( 'Processing Fee', 'order100' );
			} else {
				$fee_name = $fee_action === 'discount' ? __( 'Pickup Discount', 'order100' ) : __( 'Packaging Fee', 'order100' );
			}
			
			if ( ! empty( $fee_label ) ) {
				$fee_name = $fee_label;
			}
			
			$final_amount = $fee_action === 'discount' ? -$calculated_amount : $calculated_amount;
			$tax_on_fee = apply_filters( 'o100_dynamic_fee_taxable', false ); // Default to false for these fees to match legacy usually
			
			WC()->cart->add_fee( $fee_name, $final_amount, $tax_on_fee, '' );
		}
	}

	/**
	 * Calculate and apply the Tip Fee
	 * Hooked to: woocommerce_cart_calculate_fees
	 */
	public function calculate_tip_fee() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) ) {
			return;
		}

		// Check if tipping is enabled for the current method
		$opts = get_option( 'o100_options', array() );
		$method = $this->get_order_method();
		
		$is_tip_enabled = false;
		if ( empty( $opts['o100_tip_control_initialized'] ) ) {
			$is_tip_enabled = true; // Default to true if never saved
		} else {
			if ( $method === 'delivery' && ! empty( $opts['o100_tip_delivery_enable'] ) ) {
				$is_tip_enabled = true;
			} elseif ( ( $method === 'takeaway' || $method === 'pickup' ) && ! empty( $opts['o100_tip_pickup_enable'] ) ) {
				$is_tip_enabled = true;
			}
		}

		if ( ! $is_tip_enabled ) {
			return;
		}

		$user_tip = WC()->session->get( '_user_tip_fee' );
		$tip_type = WC()->session->get( '_user_tip_type' );

		if ( $user_tip !== null && $user_tip !== '' && is_numeric( $user_tip ) && floatval( $user_tip ) > 0 ) {
			$tip_amount = floatval( $user_tip );
			
			if ( $tip_type === 'percent' ) {
				$subtotal = apply_filters( 'exwf_total_cart_price_fee', WC()->cart->get_subtotal() );
				$tip_amount = ( $subtotal * $tip_amount ) / 100;
			}

			// Tip is generally non-taxable, but we provide a filter
			$tax_on_tip = apply_filters( 'o100_tip_fee_taxable', false );
			WC()->cart->add_fee( __( 'Tip', 'order100' ), $tip_amount, $tax_on_tip, '' );
		}
	}

	/**
	 * Render strikethrough original price + FREE badge when delivery is waived
	 *
	 * Hooked to: woocommerce_cart_totals_fee_html
	 *
	 * @param string $cart_totals_fee_html  The default HTML for the fee amount
	 * @param object $fee                   The fee object
	 * @return string
	 */
	public function render_free_delivery_strikethrough( $cart_totals_fee_html, $fee ) {
		$fee_name = $fee->name;
		if ( ( $fee_name === __( 'Delivery Fee', 'order100' ) || $fee_name === 'Delivery Fee' ) 
			&& $this->waived_original_fee !== null 
			&& $this->waived_original_fee > 0 
		) {
			$original_html = wc_price( $this->waived_original_fee );
			return '<del style="color:#999;">' . $original_html . '</del> '
				. '<span class="o100-free-badge" style="color:var(--o100-notice-success-txt);font-weight:600;">FREE</span>';
		}
		return $cart_totals_fee_html;
	}

	/**
	 * Get fee from tiered distance rules
	 *
	 * @param array $opts Delivery options
	 * @return array|null  {fee, free, min_amount} or null if no match
	 */
	private function get_tiered_distance_fee( $opts ) {
		if ( empty( $opts['o100_enable_shp_km'] ) || $opts['o100_enable_shp_km'] !== 'on' ) {
			return null;
		}

		$tiers = isset( $opts['o100_shp_km_loc'] ) ? $opts['o100_shp_km_loc'] : array();
		if ( ! is_array( $tiers ) || empty( $tiers ) ) {
			return null;
		}

		$user_distance = WC()->session ? WC()->session->get( '_user_distance' ) : null;
		if ( ! is_numeric( $user_distance ) || $user_distance <= 0 ) {
			return null;
		}

		// Sort by max_distance ascending
		usort( $tiers, function( $a, $b ) {
			return floatval( $a['max_distance'] ) - floatval( $b['max_distance'] );
		});

		foreach ( $tiers as $tier ) {
			if ( $user_distance <= floatval( $tier['max_distance'] ) ) {
				return array(
					'fee'        => isset( $tier['fee'] ) ? $tier['fee'] : '',
					'free'       => isset( $tier['free'] ) ? $tier['free'] : '',
					'min_amount' => isset( $tier['min_amount'] ) ? $tier['min_amount'] : '',
				);
			}
		}

		return null;
	}

	/**
	 * Get fee from tiered postcode rules
	 *
	 * @param array $opts Delivery options
	 * @return array|null  {fee, free, min_amount} or null if no match
	 */
	private function get_tiered_postcode_fee( $opts ) {
		if ( empty( $opts['o100_enable_shp_zip'] ) || $opts['o100_enable_shp_zip'] !== 'on' ) {
			return null;
		}

		$tiers = isset( $opts['o100_shp_zip_loc'] ) ? $opts['o100_shp_zip_loc'] : array();
		if ( ! is_array( $tiers ) || empty( $tiers ) ) {
			return null;
		}

		// Get user postcode from POST or Session
		$user_postcode = '';
		if ( isset( $_POST['s_postcode'] ) && $_POST['s_postcode'] !== '' ) {
			$user_postcode = sanitize_text_field( $_POST['s_postcode'] );
		} elseif ( isset( $_POST['postcode'] ) && $_POST['postcode'] !== '' ) {
			$user_postcode = sanitize_text_field( $_POST['postcode'] );
		} elseif ( WC()->session ) {
			$user_postcode = WC()->session->get( '_user_postcode' );
		}

		if ( empty( $user_postcode ) ) {
			return null;
		}

		foreach ( $tiers as $tier ) {
			$pattern = isset( $tier['postcode'] ) ? $tier['postcode'] : '';
			if ( empty( $pattern ) ) {
				continue;
			}

			// Wildcard match: T2N*
			if ( strpos( $pattern, '*' ) !== false ) {
				$prefix = str_replace( '*', '', $pattern );
				if ( stripos( $user_postcode, $prefix ) === 0 ) {
					return array(
						'fee'        => isset( $tier['fee'] ) ? $tier['fee'] : '',
						'free'       => isset( $tier['free'] ) ? $tier['free'] : '',
						'min_amount' => isset( $tier['min_amount'] ) ? $tier['min_amount'] : '',
					);
				}
				continue;
			}

			// Range match: 10000...20000
			$range = array_map( 'trim', explode( '...', $pattern ) );
			if ( count( $range ) === 2 && is_numeric( $range[0] ) && is_numeric( $range[1] ) ) {
				if ( $user_postcode >= $range[0] && $user_postcode <= $range[1] ) {
					return array(
						'fee'        => isset( $tier['fee'] ) ? $tier['fee'] : '',
						'free'       => isset( $tier['free'] ) ? $tier['free'] : '',
						'min_amount' => isset( $tier['min_amount'] ) ? $tier['min_amount'] : '',
					);
				}
				continue;
			}

			// Exact match
			if ( $user_postcode === $pattern ) {
				return array(
					'fee'        => isset( $tier['fee'] ) ? $tier['fee'] : '',
					'free'       => isset( $tier['free'] ) ? $tier['free'] : '',
					'min_amount' => isset( $tier['min_amount'] ) ? $tier['min_amount'] : '',
				);
			}
		}

		return null;
	}

	// ─────────────────────────────────────────────
	//  MINIMUM ORDER ENFORCEMENT
	// ─────────────────────────────────────────────

	/**
	 * Get the effective minimum order amount for the current method
	 *
	 * @return float|null
	 */
	private function get_effective_minimum() {
		// CRM VIP Bypass Check
		if ( class_exists( 'O100_Customers_DB' ) && is_user_logged_in() ) {
			$opts = $this->get_delivery_opts();
			$bypass_tags_str = isset( $opts['o100_delivery_bypass_min_tags'] ) ? $opts['o100_delivery_bypass_min_tags'] : '';
			if ( ! empty( $bypass_tags_str ) ) {
				$current_user_tags = O100_Customers_DB::get_customer_tags( get_current_user_id() );
				if ( ! empty( $current_user_tags ) ) {
					$bypass_tags = array_map('trim', explode(',', $bypass_tags_str));
					if ( array_intersect( $current_user_tags, $bypass_tags ) ) {
						return null; // VIPs have no minimum order limit
					}
				}
			}

			// Advanced CRM Rules Engine Integration (Order100 v2)
			if ( class_exists( 'O100_Privilege_Manager' ) ) {
				$context = array(
					'branch'     => $this->get_selected_location_id(),
					'order_type' => $this->get_order_method(),
					'subtotal'   => apply_filters( 'exwf_total_cart_price_fee', WC()->cart ? WC()->cart->get_subtotal() : 0 ),
					'timestamp'  => current_time( 'timestamp' ),
				);
				$lower_min = O100_Privilege_Manager::get_privilege( get_current_user_id(), 'delivery', 'lower_min_order', $context );
				if ( $lower_min !== null && $lower_min !== '' ) {
					return floatval( $lower_min );
				}
			}
		}

		$order_method = $this->get_order_method();
		$minimum = null;

		if ( $order_method === 'delivery' ) {
			$opts = $this->get_delivery_opts();
			$minimum = isset( $opts['o100_delivery_min_amount'] ) && $opts['o100_delivery_min_amount'] !== '' 
				? floatval( $opts['o100_delivery_min_amount'] ) 
				: null;

			// Override with tiered min_amount if available
			$zone_method = isset( $opts['o100_limit_shp'] ) ? $opts['o100_limit_shp'] : 'radius';
			if ( $zone_method !== 'postcode' ) {
				$tiered = $this->get_tiered_distance_fee( $opts );
			} else {
				$tiered = $this->get_tiered_postcode_fee( $opts );
			}
			if ( $tiered !== null && isset( $tiered['min_amount'] ) && $tiered['min_amount'] !== '' && is_numeric( $tiered['min_amount'] ) ) {
				$minimum = floatval( $tiered['min_amount'] );
			}
		} elseif ( $order_method === 'takeaway' || $order_method === 'pickup' ) {
			$opts = $this->get_pickup_opts();
			$minimum = isset( $opts['o100_pickup_min_amount'] ) && $opts['o100_pickup_min_amount'] !== ''
				? floatval( $opts['o100_pickup_min_amount'] )
				: null;
		}

		// Override with Location Specific Minimum Order
		$loc_selected = $this->get_selected_location_id();
		if ( $loc_selected ) {
			$loc_min = get_post_meta( $loc_selected, 'o100_min_order', true );
			if ( $loc_min !== '' ) {
				$minimum = floatval( $loc_min );
			}
		}

		return apply_filters( 'o100_delivery_min_amount', $minimum, $order_method );
	}

	/**
	 * Block checkout if minimum order not met
	 *
	 * Hooked to: woocommerce_checkout_process
	 */
	public function enforce_minimum_order() {
		$minimum = $this->get_effective_minimum();
		if ( $minimum === null ) {
			return;
		}

		$subtotal = WC()->cart->get_subtotal();
		if ( $subtotal < $minimum ) {
			$order_method = $this->get_order_method();
			$method_label = $order_method === 'delivery' 
				? __( 'delivery', 'order100' ) 
				: __( 'pickup', 'order100' );

			wc_add_notice(
				sprintf(
					__( 'Minimum order amount for %1$s is %2$s. Your current subtotal is %3$s.', 'order100' ),
					$method_label,
					wc_price( $minimum ),
					wc_price( $subtotal )
				),
				'error'
			);
		}
	}

	/**
	 * Display minimum order notice on checkout page
	 *
	 * Hooked to: woocommerce_before_checkout_form
	 */
	public function display_minimum_order_notice() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || is_order_received_page() ) {
			return;
		}

		$minimum = $this->get_effective_minimum();
		if ( $minimum === null || $minimum <= 0 ) {
			return;
		}

		$subtotal = WC()->cart->get_subtotal();
		if ( $subtotal < $minimum ) {
			$diff = $minimum - $subtotal;
			$order_method = $this->get_order_method();
			$ui_prefs = get_option( 'o100_ui_prefs', array() );
			$custom_msg = isset( $ui_prefs['o100_msg_min_order'] ) && $ui_prefs['o100_msg_min_order'] !== ''
				? $ui_prefs['o100_msg_min_order']
				: __( 'Your subtotal is {subtotal}. A minimum order of {min_amount} is required to checkout.', 'order100' );
			
			$msg = str_replace( 
				array( '{subtotal}', '{min_amount}' ),
				array( '<strong>' . wc_price( $subtotal ) . '</strong>', '<strong>' . wc_price( $minimum ) . '</strong>' ),
				$custom_msg
			);

			echo '<div class="o100-min-order-alert o100-notice-warning" style="border-left: 4px solid var(--o100-notice-warn-txt); padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">';
			echo '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
			echo '<div style="flex-grow:1;"><strong style="display:block; font-size:16px; margin-bottom:4px;">' . esc_html__( 'Minimum Order Required', 'order100' ) . '</strong>';
			echo '<span style="font-size:14px;">' . wp_kses_post( $msg ) . '</span></div>';
			echo '<a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" class="button alt" style="white-space:nowrap; padding: 8px 16px; margin-left: auto; text-decoration: none; border-radius: 8px;">' . esc_html__( 'Add Items', 'order100' ) . '</a>';
			echo '</div>';
		}
	}

	public function display_minimum_order_sidebar_notice() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || is_checkout() ) {
			return;
		}

		$minimum = $this->get_effective_minimum();
		if ( $minimum === null || $minimum <= 0 ) {
			return;
		}

		$subtotal = WC()->cart->get_subtotal();
		if ( $subtotal < $minimum ) {
			$diff = $minimum - $subtotal;
			echo '<div class="o100-notice-warning" style="padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; font-weight: 500; display: flex; align-items: flex-start; gap: 8px; border: 1px solid var(--o100-notice-warn-txt);">';
			echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:1px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
			echo '<span>' . sprintf( __( 'Add %s more to reach the %s minimum order to checkout.', 'order100' ), wc_price( $diff ), wc_price( $minimum ) ) . '</span>';
			echo '</div>';
		}
	}

	/**
	 * Enforce maximum delivery distance on checkout
	 *
	 * Hooked to: woocommerce_checkout_process
	 */
	public function enforce_delivery_distance() {
		if ( ! $this->is_delivery() ) {
			return;
		}

		$opts = $this->get_delivery_opts();
		if ( empty( $opts['o100_enable_delivery'] ) || $opts['o100_enable_delivery'] !== 'on' ) {
			return;
		}

		$user_distance = WC()->session ? WC()->session->get( '_user_distance' ) : null;
		if ( $user_distance !== null && is_numeric( $user_distance ) ) {
			$max_allowed = '';
			
			$loc_selected = $this->get_selected_location_id();
			if ( $loc_selected ) {
				$loc_max = get_post_meta( $loc_selected, 'o100_distance_restrict', true );
				if ( $loc_max !== '' ) $max_allowed = floatval( $loc_max );
			}
			
			if ( $max_allowed === '' && isset( $opts['o100_deli_dis'] ) && $opts['o100_deli_dis'] !== '' ) {
				$max_allowed = floatval( $opts['o100_deli_dis'] );
			}
			
			if ( $max_allowed === '' && isset( $opts['o100_enable_shp_km'] ) && $opts['o100_enable_shp_km'] === 'on' && isset( $opts['o100_shp_km_loc'] ) && is_array( $opts['o100_shp_km_loc'] ) ) {
				$max_tier = 0;
				foreach ( $opts['o100_shp_km_loc'] as $tier ) {
					if ( isset( $tier['max_distance'] ) && floatval( $tier['max_distance'] ) > $max_tier ) {
						$max_tier = floatval( $tier['max_distance'] );
					}
				}
				if ( $max_tier > 0 ) $max_allowed = $max_tier;
			}
			
			if ( $max_allowed !== '' && $user_distance > $max_allowed ) {
				$ui_prefs = get_option( 'o100_ui_prefs', array() );
				$custom_msg = isset( $ui_prefs['o100_msg_out_of_range'] ) && $ui_prefs['o100_msg_out_of_range'] !== ''
					? $ui_prefs['o100_msg_out_of_range']
					: __( 'We\'re sorry! Your location ({dist} km) is outside our delivery range of {max} km. Please consider switching to Pickup to place your order.', 'order100' );
				
				$msg = str_replace(
					array( '{dist}', '{max}' ),
					array( number_format_i18n( $user_distance, 2 ), number_format_i18n( $max_allowed, 2 ) ),
					$custom_msg
				);

				wc_add_notice( wp_kses_post( $msg ), 'error' );
			}
		}
	}

	// ─────────────────────────────────────────────
	//  FREE SHIPPING PROGRESS
	// ─────────────────────────────────────────────

	/**
	 * Display "Order $X more for free delivery" in mini-cart
	 *
	 * Hooked to: woocommerce_widget_shopping_cart_before_buttons
	 */
	public function display_free_shipping_progress( $return_html = false ) {
		if ( ! $this->is_delivery() ) {
			if ( $return_html ) return '';
			return;
		}

		$opts = $this->get_delivery_opts();
		$fee = isset( $opts['o100_shipping_fee'] ) ? $opts['o100_shipping_fee'] : '';
		$free_threshold = isset( $opts['o100_shipping_freemax'] ) ? $opts['o100_shipping_freemax'] : '';

		// Check tiered overrides
		$zone_method = isset( $opts['o100_limit_shp'] ) ? $opts['o100_limit_shp'] : 'radius';
		if ( $zone_method !== 'postcode' ) {
			$tiered = $this->get_tiered_distance_fee( $opts );
		} else {
			$tiered = $this->get_tiered_postcode_fee( $opts );
		}
		if ( $tiered !== null ) {
			$fee = $tiered['fee'];
			if ( isset( $tiered['free'] ) && $tiered['free'] !== '' ) {
				$free_threshold = $tiered['free'];
			}
		}

		if ( $fee === '' || ! is_numeric( $fee ) || $free_threshold === '' || ! is_numeric( $free_threshold ) ) {
			if ( $return_html ) return '';
			return;
		}

		$subtotal = apply_filters( 'exwf_total_cart_price_fee', WC()->cart->get_subtotal() );
		$html = '';
		if ( $subtotal < floatval( $free_threshold ) ) {
			$remaining = floatval( $free_threshold ) - $subtotal;
			$pct = min( 100, round( ( $subtotal / floatval( $free_threshold ) ) * 100 ) );
			$msg = sprintf(
				__( 'Add %s more to get <strong>FREE delivery</strong>!', 'order100' ),
				wc_price( $remaining )
			);
			
			// We use a neutral info style here instead of the aggressive promotion style.
			$style_wrapper = $return_html ? 'padding:8px 0;margin:0 0 12px;text-align:left;' : 'border:1px solid rgba(0,0,0,0.1);border-radius:8px;padding:12px 16px;margin:0 0 16px;text-align:center;';
			
			$html = '<div class="o100-free-ship-banner o100-notice-info" style="' . $style_wrapper . '">'
				. '<div style="font-size:14px;margin-bottom:6px; font-weight: 500; display:flex; align-items:center; gap:6px;">🚚 <span>' . $msg . '</span></div>'
				. '<div style="background:rgba(0,0,0,0.06);border-radius:4px;height:6px;overflow:hidden;width:100%;">'
				// The progress bar fill uses the promo text color as the brand highlight
				. '<div style="background:var(--o100-notice-promo-txt, #e11d48);height:100%;width:' . $pct . '%;border-radius:4px;transition:width 0.4s ease;"></div>'
				. '</div>'
				. '</div>';
		} else {
			$style_wrapper = $return_html ? 'padding:8px 0;margin:0 0 12px;text-align:left;color:#166534;' : 'border:1px solid rgba(34,197,94,0.3);border-radius:8px;padding:12px 16px;margin:0 0 16px;text-align:center;background:rgba(34,197,94,0.05);color:#166534;';
			
			$html = '<div class="o100-free-ship-banner o100-notice-info" style="' . $style_wrapper . '">'
				. '<div style="font-size:14px; font-weight: 600; display:flex; align-items:center; gap:6px;">🎉 <span>You got <strong>FREE delivery</strong>!</span></div>'
				. '</div>';
		}
		
		if ( $return_html ) {
			return $html;
		}
		
		echo $html;
	}

	// ─────────────────────────────────────────────
	//  HELPERS
	// ─────────────────────────────────────────────

	/**
	 * Get the currently selected location term ID
	 *
	 * @return int|null
	 */
	private function get_selected_location_id() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) ) {
			return null;
		}
		$loc_id = WC()->session->get( 'o100_location_id' );
		if ( ! empty( $loc_id ) ) {
			return intval( $loc_id );
		}
		
		return null;
	}

	/**
	 * Parse distance from checkout POST data and save to session
	 * 
	 * @param string $post_data_string Serialized form data
	 */
	public function parse_distance_from_checkout( $post_data_string ) {
		if ( empty( $post_data_string ) ) return;
		
		parse_str( $post_data_string, $post_data );
		
		if ( isset( $post_data['o100_user_distance'] ) && is_numeric( $post_data['o100_user_distance'] ) ) {
			$distance = floatval( $post_data['o100_user_distance'] );
			if ( function_exists( 'WC' ) && isset( WC()->session ) ) {
				WC()->session->set( '_user_distance', $distance );
			}
		}
	}
}

