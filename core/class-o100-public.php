<?php
/**
 * Public Class
 */

/**
 * O100 Native Store Data Helpers
 * Direct access to O100 options — no legacy translation layer.
 */
if ( ! class_exists( 'O100_Store_Data' ) ) {
	class O100_Store_Data {
		private static $store_hours_cache = null;

		public static function get_store_hours() {
			if ( self::$store_hours_cache === null ) {
				self::$store_hours_cache = get_option( 'o100_store_hours', array() );
			}
			return self::$store_hours_cache;
		}

		/** Is the Opening Time feature enabled? '' = disabled (always open), 'enable' = follow hours */
		public static function get_open_close_mode() {
			$opts = self::get_store_hours();
			return empty( $opts['o100_op_cl'] ) || $opts['o100_op_cl'] === 'on' ? '' : 'enable';
		}

		public static function get_global_start_time() {
			$opts = self::get_store_hours();
			return isset( $opts['o100_global_start_time'] ) ? $opts['o100_global_start_time'] : '';
		}

		public static function get_global_end_time() {
			$opts = self::get_store_hours();
			return isset( $opts['o100_global_end_time'] ) ? $opts['o100_global_end_time'] : '';
		}

		public static function get_day_hours( $day_abbr ) {
			$opts = self::get_store_hours();
			return isset( $opts['o100_' . $day_abbr . '_opcl_time'] ) ? $opts['o100_' . $day_abbr . '_opcl_time'] : '';
		}

		public static function get_holidays() {
			return class_exists('O100_Settings') ? O100_Settings::get_formatted_holidays() : array();
		}

		public static function get_lead_time( $method = 'delivery' ) {
			$opts = self::get_store_hours();
			$prefix = ( $method === 'takeaway' || $method === 'pickup' ) ? 'o100_pickup_' : 'o100_delivery_';
			return isset( $opts[$prefix . 'lead_time'] ) ? $opts[$prefix . 'lead_time'] : '';
		}

		public static function is_location_enabled() {
			return get_option('o100_locations_status') === 'on';
		}

		/** Build advanced timeslot groups from generated schedule data */
		public static function get_advanced_timeslots() {
			$opts = self::get_store_hours();
			$adv_timesl = array();
			$weekdays = array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' );

			// Branch-level Hours Override
			$loc_id = self::get_selected_location_id();
			if ( $loc_id ) {
				$override_hours = get_post_meta( $loc_id, 'o100_override_hours', true );
				if ( $override_hours === 'yes' ) {
					$generated_slots_json = get_post_meta( $loc_id, 'o100_generated_timeslots', true );
					if ( ! empty( $generated_slots_json ) ) {
						$branch_slots = json_decode( $generated_slots_json, true );
						if ( is_array( $branch_slots ) && ! empty( $branch_slots ) ) {
							$groups = array();
							foreach ( $weekdays as $day ) {
								if ( isset( $branch_slots[$day] ) && is_array( $branch_slots[$day] ) ) {
									$group = array( 'deli_method' => '' );
									foreach ( $weekdays as $d ) {
										$group['repeat_' . $d] = ($d === $day) ? 'on' : 'off';
									}
									$group['o100_deli_time'] = $branch_slots[$day];
									$groups[] = $group;
								}
							}
							return $groups;
						}
					}
					// If override is enabled but no valid slots generated, return empty so it's fully closed
					return array();
				}
			}

			$build_group = function( $json_str, $method ) use ( $weekdays ) {
				if ( empty( $json_str ) ) return array();
				$slots = json_decode( wp_unslash( $json_str ), true );
				if ( ! is_array( $slots ) || empty( $slots ) ) return array();
				$groups = array();
				foreach ( $weekdays as $day ) {
					if ( isset( $slots[$day] ) && ! empty( $slots[$day] ) ) {
						$group = array( 'deli_method' => $method );
						foreach ( $weekdays as $d ) {
							$group['repeat_' . $d] = ($d === $day) ? 'on' : 'off';
						}
						$group['o100_deli_time'] = $slots[$day];
						$groups[] = $group;
					}
				}
				return $groups;
			};

			if ( ! empty( $opts['o100_delivery_override_schedule'] ) && $opts['o100_delivery_override_schedule'] === 'on' && ! empty( $opts['o100_delivery_generated_timeslots'] ) ) {
				$adv_timesl = array_merge( $adv_timesl, $build_group( $opts['o100_delivery_generated_timeslots'], 'delivery' ) );
			}
			if ( ! empty( $opts['o100_pickup_override_schedule'] ) && $opts['o100_pickup_override_schedule'] === 'on' && ! empty( $opts['o100_pickup_generated_timeslots'] ) ) {
				$adv_timesl = array_merge( $adv_timesl, $build_group( $opts['o100_pickup_generated_timeslots'], 'takeaway' ) );
			}
			if ( ! empty( $opts['o100_global_generated_timeslots'] ) ) {
				$adv_timesl = array_merge( $adv_timesl, $build_group( $opts['o100_global_generated_timeslots'], '' ) );
			}
			return $adv_timesl;
		}

		/** Get current time adjusted for site timezone */
		public static function get_current_time() {
			return current_time( 'timestamp' );
		}

		/** Get GMT offset in hours */
		public static function get_gmt_offset() {
			return (float) get_option( 'gmt_offset', 0 );
		}

		/** Get selected location term_id from session */
		public static function get_selected_location_id() {
			if ( ! isset( WC()->session ) ) return '';
			$loc_slug = WC()->session->get( 'ex_userloc' );
			if ( empty( $loc_slug ) ) {
				$loc_slug = WC()->session->get( '_user_deli_log' );
			}
			if ( empty( $loc_slug ) ) return '';
			$locations = get_posts( array(
				'name'           => $loc_slug,
				'post_type'      => 'o100_location',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			) );
			if ( ! empty( $locations ) ) {
				return $locations[0];
			}
			return '';
		}
	}
}

class O100_Public {

	/**
	 * Flag to track if the current order is ASAP
	 *
	 * @var bool
	 */
	private $is_asap_order = false;

	/**
	 * Cached addon options (loaded once per request)
	 *
	 * @var array|null
	 */
	private $options_cache = null;

	/**
	 * Cached store open status (computed once per request)
	 *
	 * @var bool|null
	 */
	private $is_store_open_cache = null;

	/**
	 * Stores the reason message if closed via a Closed Date.
	 *
	 * @var string
	 */
	private $current_close_reason = '';

	/**
	 * Get addon options with per-request caching
	 *
	 * @return array
	 */
	private function get_options() {
		if ( $this->options_cache === null ) {
			$options = array();
			
			// Fetch from all unified setting tabs
			$tabs = array( 'o100_options', 'o100_store_profile', 'o100_store_hours', 'o100_checkout_ext', 'o100_delivery', 'o100_pickup', 'o100_locations', 'o100_advanced' );
			foreach ( $tabs as $tab ) {
				$tab_opts = get_option( $tab, array() );
				if ( is_array( $tab_opts ) ) {
					$options = array_merge( $options, $tab_opts );
				}
			}

			
			$this->options_cache = $options;
		}
		return $this->options_cache;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Warning Hooks
		add_action( 'wp_footer', array( $this, 'inject_popup_warning_script' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_head', array( $this, 'print_dynamic_css' ) );

		// ASAP Hooks
		// add_filter( 'woocommerce_form_field_args', array( $this, 'add_asap_option' ), 10, 3 ); // Disabled as it gets overwritten by JS
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_asap_option' ), 1 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_asap_meta' ), 50, 1 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_native_checkout_fields' ), 20, 1 );

		// Cumulative Quantity Hooks
		add_action( 'cmb2_admin_init', array( $this, 'add_cumulative_qty_field' ), 100 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_cumulative_qty' ), 10, 4 );

		// Shipping Address Restoration Hooks
		add_filter( 'woocommerce_checkout_fields', array( $this, 'modify_shipping_fields' ), 99999 );
		add_filter( 'woocommerce_cart_needs_shipping_address', array( $this, 'force_needs_shipping_address' ), 99999 );
		add_action( 'woocommerce_before_checkout_shipping_form', array( $this, 'add_shipping_form_title' ) );
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'add_shipping_address_checkbox' ), 10 );
		add_action( 'wp_footer', array( $this, 'inject_shipping_address_script' ) );
		
		// Payment Gateway Disabling logic
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'disable_payment_gateways_by_method' ), 10, 1 );

		// Sync session address to SHIPPING defaults (native O100 implementation)
		add_filter( 'default_checkout_shipping_country', array( $this, 'o100_default_shipping_country' ), 20 );
		add_filter( 'default_checkout_shipping_city', array( $this, 'o100_default_shipping_city' ), 20 );
		add_filter( 'default_checkout_shipping_address_1', array( $this, 'o100_default_shipping_address' ), 20 );
		add_filter( 'default_checkout_shipping_postcode', array( $this, 'o100_default_shipping_postcode' ), 20 );

		// Delivery Instruction Hooks
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_delivery_instruction_on_create' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_instruction_on_order_details' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_instruction_on_order_details' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'display_instruction_in_email' ), 10, 4 );

		// REST API Extensions for Location Support
		add_action( 'rest_api_init', array( $this, 'register_location_api_routes' ) );
		add_filter( 'woocommerce_rest_orders_prepare_object_query', array( $this, 'filter_orders_by_location' ), 10, 2 );
		// Support for HPOS (High Performance Order Storage)
		add_filter( 'woocommerce_rest_shop_order_object_query', array( $this, 'filter_orders_by_location' ), 10, 2 );

		// Persist Order Notes Hooks
		add_action( 'wp_ajax_o100_save_order_note', array( $this, 'o100_save_order_note' ) );
		add_action( 'wp_ajax_nopriv_o100_save_order_note', array( $this, 'o100_save_order_note' ) );
		add_action( 'wp_ajax_o100_get_order_note', array( $this, 'o100_get_order_note' ) );
		add_action( 'wp_ajax_nopriv_o100_get_order_note', array( $this, 'o100_get_order_note' ) );

		// Google Maps Distance Updates
		add_action( 'wp_ajax_o100_update_user_distance', array( $this, 'ajax_update_user_distance' ) );
		add_action( 'wp_ajax_nopriv_o100_update_user_distance', array( $this, 'ajax_update_user_distance' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'apply_persisted_order_note' ), 20, 1 );

		// Tip Rendering (dynamic position registration)
		add_action( 'wp', array( $this, 'maybe_filter_tip_form' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'filter_tip_fee_calculation' ), 5 );

		// Timeslot Filtering Hooks
		add_action( 'init', array( $this, 'maybe_override_timeslot_ajax' ), 20 );

		// Guest Auto-Registration & Login
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'guest_auto_register_and_login' ), 10, 3 );

		// Render Checkout Fields Natively
		add_action( 'wp', array( $this, 'disable_legacy_checkout_fields' ) );
		add_action( 'woocommerce_before_order_notes', array( $this, 'render_checkout_delivery_fields' ) );
		
		// Render Order Method Tabs at the Top
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_checkout_method_tabs' ), 5 );
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'update_checkout_fragments' ) );

		// Native AJAX Endpoints for Checkout Updates
		add_action( 'wp_ajax_o100_update_order_method', array( $this, 'ajax_update_order_method' ) );
		add_action( 'wp_ajax_nopriv_o100_update_order_method', array( $this, 'ajax_update_order_method' ) );
		add_action( 'wp_ajax_o100_update_tip', array( $this, 'ajax_update_tip' ) );
		add_action( 'wp_ajax_nopriv_o100_update_tip', array( $this, 'ajax_update_tip' ) );
	}
	
	/**
	 * Disable legacy woo-exfood checkout fields
	 */
	public function disable_legacy_checkout_fields() {
		remove_action( 'woocommerce_before_order_notes', 'exwf_date_deli_field', 10 );
	}

	/**
	 * Native shipping address default: Country
	 */
	public function o100_default_shipping_country() {
		if ( isset( WC()->session ) ) {
			$val = WC()->session->get( '_user_deli_country' );
			if ( ! empty( $val ) ) return $val;
		}
		return get_option( 'woocommerce_default_country', '' );
	}

	/**
	 * Native shipping address default: City
	 */
	public function o100_default_shipping_city() {
		if ( isset( WC()->session ) ) {
			$val = WC()->session->get( '_user_deli_city' );
			if ( ! empty( $val ) ) return $val;
		}
		return '';
	}

	/**
	 * Native shipping address default: Address line 1
	 */
	public function o100_default_shipping_address() {
		if ( isset( WC()->session ) ) {
			$val = WC()->session->get( '_user_deli_address' );
			if ( ! empty( $val ) ) return $val;
		}
		return '';
	}

	/**
	 * Native shipping address default: Postcode
	 */
	public function o100_default_shipping_postcode() {
		if ( isset( WC()->session ) ) {
			$val = WC()->session->get( '_user_deli_postcode' );
			if ( ! empty( $val ) ) return $val;
		}
		return '';
	}

	/**
	 * AJAX handler for updating order method from native tabs
	 */
	public function ajax_update_order_method() {
		if ( isset( $_POST['method'] ) ) {
			$method = sanitize_text_field( wp_unslash( $_POST['method'] ) );
			WC()->session->set( '_o100_order_method', $method );
			wp_send_json_success();
		}
		wp_send_json_error();
	}

	/**
	 * Hook into WooCommerce checkout update fragments to replace delivery fields dynamically
	 */
	public function update_checkout_fragments( $fragments ) {
		ob_start();
		$this->render_checkout_delivery_fields( WC()->checkout() );
		$html = ob_get_clean();

		$fragments['.o100-dynamic-checkout-fields'] = $html;

		return $fragments;
	}

	/**
	 * AJAX handler for updating tipping
	 */
	public function ajax_update_tip() {
		if ( isset( $_POST['tip'] ) ) {
			$tip  = sanitize_text_field( wp_unslash( $_POST['tip'] ) );
			$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
			WC()->session->set( '_user_tip_fee', $tip );
			WC()->session->set( '_user_tip_type', $type );
			wp_send_json_success();
		}
		wp_send_json_error();
	}



	/**
	 * Natively render the Checkout Order Method Tabs at the top of the form.
	 * Native shipping method rendering.
	 */
	public function render_checkout_method_tabs() {
		$options = $this->get_options();
		
		$is_deli = ! empty( $options['o100_enable_delivery'] ) && $options['o100_enable_delivery'] === 'on';
		$is_pick = ! empty( $options['o100_enable_pickup'] ) && $options['o100_enable_pickup'] === 'on';
		$is_dine = ! empty( $options['o100_enable_dinein'] ) && $options['o100_enable_dinein'] === 'on';

		// Branch Level Override
		$loc_id = O100_Store_Data::get_selected_location_id();
		if ( $loc_id ) {
			$loc_deli = get_post_meta( $loc_id, 'o100_enable_delivery', true );
			if ( $loc_deli !== '' ) {
				$is_deli = ( $loc_deli === 'yes' );
			}
			$loc_pick = get_post_meta( $loc_id, 'o100_enable_pickup', true );
			if ( $loc_pick !== '' ) {
				$is_pick = ( $loc_pick === 'yes' );
			}
		}

		if ( ! $is_deli && ! $is_pick && ! $is_dine ) {
			return;
		}

		$current_method = WC()->session->get( '_o100_order_method' );
		if ( empty( $current_method ) ) {
			$current_method = $is_deli ? 'delivery' : ( $is_pick ? 'takeaway' : 'dinein' );
			WC()->session->set( '_o100_order_method', $current_method );
		}

		echo '<div class="exwf-cksp-method exwf-method-ct">';
		echo '<div class="exwf-method-title">';
		
		if ( $is_deli ) {
			$active_class = $current_method === 'delivery' ? 'at-method' : '';
			echo '<div class="exwf-order-deli ' . esc_attr($active_class) . '" data-method="delivery">' . esc_html__('Delivery','order100') . '</div>';
		}
		if ( $is_pick ) {
			$active_class = $current_method === 'takeaway' || $current_method === 'pickup' ? 'at-method' : '';
			echo '<div class="exwf-order-take ' . esc_attr($active_class) . '" data-method="takeaway">' . esc_html__('Pickup','order100') . '</div>';
		}
		if ( $is_dine ) {
			$active_class = $current_method === 'dinein' ? 'at-method' : '';
			echo '<div class="exwf-order-dinein ' . esc_attr($active_class) . '" data-method="dinein">' . esc_html__('Dine-In','order100') . '</div>';
		}
		
		echo '</div></div>';
	}

	/**
	 * Natively render the Checkout Date, Time, Location, and Dine-in fields.
	 * Native checkout field rendering.
	 */
	public function render_checkout_delivery_fields( $checkout ) {
		$options = $this->get_options();
		
		$is_deli = ! empty( $options['o100_enable_delivery'] ) && $options['o100_enable_delivery'] === 'on';
		$is_pick = ! empty( $options['o100_enable_pickup'] ) && $options['o100_enable_pickup'] === 'on';
		$is_dine = ! empty( $options['o100_enable_dinein'] ) && $options['o100_enable_dinein'] === 'on';

		// Branch Level Override
		$loc_id = O100_Store_Data::get_selected_location_id();
		if ( $loc_id ) {
			$loc_deli = get_post_meta( $loc_id, 'o100_enable_delivery', true );
			if ( $loc_deli !== '' ) {
				$is_deli = ( $loc_deli === 'yes' );
			}
			$loc_pick = get_post_meta( $loc_id, 'o100_enable_pickup', true );
			if ( $loc_pick !== '' ) {
				$is_pick = ( $loc_pick === 'yes' );
			}
		}

		if ( ! $is_deli && ! $is_pick && ! $is_dine ) {
			return;
		}

		$current_method = WC()->session->get( '_o100_order_method' );
		if ( empty( $current_method ) ) {
			$current_method = $is_deli ? 'delivery' : ( $is_pick ? 'takeaway' : 'dinein' );
		}

		echo '<div class="o100-dynamic-checkout-fields">';

		// Google Maps Autocomplete limit (if any)
		echo '<input type="hidden" name="exwf_auto_limit" id="exwf_auto_limit" value="">';
		echo '<input type="hidden" name="exwf_dis_auto" id="exwf_dis_auto" value="">';

		// Render Location Select (if multi-location is enabled)
		$is_loc_enabled = get_option('o100_locations_status') === 'on';
		if ( $is_loc_enabled ) {
			$loc_selected = WC()->session->get( 'o100_location_id' );
			if ( empty( $loc_selected ) ) {
				$loc_selected = WC()->session->get( 'ex_userloc' ); // Fallback
			}
			$terms = get_posts( array(
				'post_type'      => 'o100_location',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			) );
			
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				if ( count( $terms ) === 1 ) {
					// Render hidden input for the only location
					echo '<input type="hidden" name="o100_location" value="' . esc_attr($terms[0]->ID) . '">';
				} else {
					echo '<div class="exwf-loc-field">';
					echo '<p class="form-row validate-required">';
					echo '<label for="o100_location">' . esc_html__('Locations', 'order100') . ' <abbr class="required" title="required">*</abbr>';
					echo '<small style="display: block;">' . esc_html__('(Please choose area you want to order)', 'order100') . '</small></label>';
					echo '<span class="woocommerce-input-wrapper">';
					echo '<select class="exck-loc select" name="o100_location">';
					echo '<option value=""></option>';
					foreach ( $terms as $term ) {
						// Match o100_location_id (Post ID) OR ex_userloc (legacy string ID)
						$selected = ( $loc_selected == $term->ID ) ? 'selected' : '';
						echo '<option value="' . esc_attr($term->ID) . '" ' . $selected . '>' . esc_html($term->post_title) . '</option>';
					}
					echo '</select></span></p></div>';
				}
			}
		}

		// Render Dine-in Person Input
		if ( $current_method === 'dinein' ) {
			echo '<div class="exwf-dine-field">';
			echo '<p class="form-row validate-required">';
			$max_person = 10;
			$arr_nb = array( '' => esc_html__('Select number of person', 'order100') );
			for ($i = 1; $i <= $max_person; $i++) {
				$arr_nb[$i] = $i;
			}
			woocommerce_form_field( 
				'o100_person_dinein', array(
					'type'        => 'select',
					'required'    => true,
					'class'       => array('o100-person-dinein form-row-wide'),
					'label'       => esc_html__('Number of person','order100'),
					'options'     => $arr_nb,
				)
			);
			echo '</p></div>';
		}

		// Render Date and Time Selectors
		echo '<div class="exwf-deli-field">';
		
		$prefix = '';
		if ( $current_method === 'delivery' ) $prefix = 'o100_delivery_';
		if ( $current_method === 'takeaway' || $current_method === 'pickup' ) $prefix = 'o100_pickup_';

		$dis_date    = isset($options[$prefix . 'disdate']) ? $options[$prefix . 'disdate'] : array();
		$dis_day     = isset($options[$prefix . 'disday']) ? $options[$prefix . 'disday'] : array();
		$ena_date    = isset($options[$prefix . 'enadate']) ? $options[$prefix . 'enadate'] : array();
		$date_before = isset($options[$prefix . 'lead_time']) ? $options[$prefix . 'lead_time'] : '';

		if ( ! is_array( $dis_date ) ) $dis_date = array();
		if ( ! is_array( $dis_day ) ) $dis_day = array();
		if ( ! is_array( $ena_date ) ) $ena_date = array();

		// Fetch Holidays & Days Off
		$closed_dates = O100_Store_Data::get_holidays();
		if ( ! is_array( $closed_dates ) ) $closed_dates = array();

		// DYNAMICALLY disable days that have NO timeslots generated for the current method
		$adv_timesl = O100_Store_Data::get_advanced_timeslots();
		if ( is_array( $adv_timesl ) ) {
			$active_days_of_week = array();
			$weekdays = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
			foreach ( $adv_timesl as $it_timesl ) {
				$tsl_method = isset( $it_timesl['deli_method'] ) ? $it_timesl['deli_method'] : '';
				if ( $tsl_method == '' || $tsl_method == $current_method ) {
					foreach ($weekdays as $day_index => $day_name) {
						if ( isset($it_timesl['repeat_'.$day_name]) && $it_timesl['repeat_'.$day_name] == 'on' ) {
							if ( !empty($it_timesl['o100_deli_time']) ) {
								$active_days_of_week[] = $day_index;
							}
						}
					}
				}
			}
			
			if ( !empty($active_days_of_week) ) {
				$active_days_of_week = array_unique($active_days_of_week);
				for ($d = 0; $d <= 6; $d++) {
					if ( !in_array($d, $active_days_of_week) && !in_array($d, $dis_day) ) {
						$dis_day[] = (string)$d;
					}
				}
			} else {
				// If NO timeslots exist for this method, disable all days
				$dis_day = array('0','1','2','3','4','5','6');
			}
		}

		date_default_timezone_set('UTC');
		$cure_time = current_time('timestamp');
		
		// Save the real local time before lead time is applied
		$real_local_time = $cure_time;

		if ( $date_before != '' && is_numeric($date_before) ) {
			// In Order100, the new UI defines pure numbers as minutes (e.g. 30 = 30 minutes)
			$cure_time = strtotime("+$date_before minutes", $cure_time);
		} else if ( $date_before != '' && is_numeric(str_replace("m","",$date_before)) ) {
			$cure_time = strtotime("+" . str_replace("m","",$date_before) . " minutes", $cure_time);
		} else if ( $date_before != '' && is_numeric(str_replace("d","",$date_before)) ) {
			// Legacy support if user appended 'd' for days
			$cure_time = strtotime("+" . str_replace("d","",$date_before) . " days", $cure_time);
		}

		$date = strtotime(date('Y-m-d', $cure_time));
		$maxl = 10;
		$deli_date = array();

		// Handle Exclusive Whitelist Dates
		if ( ! empty( $ena_date ) ) {
			foreach ( $ena_date as $ena_str ) {
				$ena_ts = strtotime($ena_str);
				if ( $ena_ts >= $date ) {
					$date_fm = date_i18n(get_option('date_format'), $ena_ts);
					$deli_date[$ena_ts] = $date_fm;
				}
			}
		} else {
			for ($i = 0 ; $i <= $maxl; $i++) {
				$date_un = strtotime("+$i day", $date);
				$day_ofdate = date('w', $date_un); // 0 (Sun) to 6 (Sat)
				$date_ymd   = date('Y-m-d', $date_un);

				if ( ! empty($dis_day) && count($dis_day) >= 7 ) {
					break; // Store completely closed
				}
				
				if ( (!empty($dis_date) && in_array($date_ymd, $dis_date)) || (!empty($dis_day) && in_array($day_ofdate, $dis_day)) ) {
					$maxl = $maxl + 1;
					if ( $maxl > 30 ) break; // infinite loop protection
				} else {
					// Check Holidays & Days off
					$is_holiday = false;
					if ( ! empty( $closed_dates ) ) {
						// The date_un is midnight UTC for the given day
						// A store is closed on this day if any time during this day falls in a holiday range.
						// The simplest check: the start of the day (midnight) up to 23:59:59.
						$day_start = $date_un;
						$day_end   = $date_un + 86399;
						
						foreach ( $closed_dates as $closed_date ) {
							$cls_start = isset( $closed_date['opcl_start'] ) ? $closed_date['opcl_start'] : '';
							$cls_end   = isset( $closed_date['opcl_end'] ) ? $closed_date['opcl_end'] : '';
							
							// If the holiday range overlaps with this day AT ALL, it's considered closed
							// (Overlaps if: day_start <= holiday_end AND day_end >= holiday_start)
							if ( $cls_start != '' && $cls_end != '' && $day_start <= $cls_end && $day_end >= $cls_start ) {
								$is_holiday = true;
								break;
							}
						}
					}
					
					if ( $is_holiday ) {
						$maxl = $maxl + 1;
						if ( $maxl > 30 ) break;
						continue;
					}

					// Check if this date has any valid timeslots left
					$has_valid_slots = true;
					if ( is_array( $adv_timesl ) && ! empty( $adv_timesl ) ) {
						$has_valid_slots = false;
						$day_ofd_tsl = gmdate( 'D', $date_un );
						$loc_id = isset(WC()->session) ? WC()->session->get('_user_deli_log') : '';
						if ( empty($loc_id) && isset(WC()->session) ) $loc_id = WC()->session->get('ex_userloc');
						
						foreach ( $adv_timesl as $it_timesl ) {
							$tsl_method = isset( $it_timesl['deli_method'] ) ? $it_timesl['deli_method'] : '';
							$tsl_log = isset( $it_timesl['times_loc'] ) ? $it_timesl['times_loc'] : '';
							
							if ( isset( $it_timesl['repeat_' . $day_ofd_tsl] ) && $it_timesl['repeat_' . $day_ofd_tsl] == 'on' && 
								( $tsl_method == '' || $tsl_method == $current_method ) && 
								( $tsl_log == '' || ( is_array( $tsl_log ) && ! empty( $tsl_log ) && in_array( $loc_id, $tsl_log ) ) ) ) {
								
								if ( isset( $it_timesl['o100_deli_time'] ) && is_array( $it_timesl['o100_deli_time'] ) ) {
									foreach ( $it_timesl['o100_deli_time'] as $time_option ) {
										if ( isset( $time_option['disable-slot'] ) && $time_option['disable-slot'] == '1' ) continue;
										$_time_base = $time_option['start-time'];
										if ( $_time_base != '' ) {
											$_time_end = ! empty( $time_option['end-time'] ) ? $time_option['end-time'] : $time_option['start-time'];
											$_timeck = explode( ':', $_time_end );
											$_timeck_sec = $_timeck[1] * 60 + $_timeck[0] * 3600;
											if ( ( $date_un + $_timeck_sec ) >= $cure_time ) {
												$has_valid_slots = true;
												break 2;
											}
										}
									}
								}
							}
						}
					}
					
					if ( ! $has_valid_slots ) {
						$maxl = $maxl + 1;
						if ( $maxl > 30 ) break;
						continue;
					}

					$date_fm = date_i18n(get_option('date_format'), $date_un);
					if ( $i === 0 && date('Y-m-d', $real_local_time) === date('Y-m-d', $date_un) ) {
						$date_fm = esc_html__('Today', 'order100') . ' - ' . $date_fm;
					}
					$deli_date[$date_un] = $date_fm;
				}
			}
		}

		if ( empty($deli_date) ) {
			$deli_date[''] = esc_html__('No date available', 'order100');
		}

		$show_exhausted_warning = false;
		if ( ! empty($deli_date) && ! isset( $deli_date[''] ) ) {
			$real_today_ts = strtotime(date('Y-m-d', $real_local_time));
			reset($deli_date);
			$first_available_ts = key($deli_date);
			
			if ( $first_available_ts > $real_today_ts ) {
				$today_day_ofd = date('w', $real_today_ts);
				$today_ymd = date('Y-m-d', $real_today_ts);
				$is_today_closed = false;
				
				if ( (!empty($dis_date) && in_array($today_ymd, $dis_date)) || (!empty($dis_day) && in_array($today_day_ofd, $dis_day)) ) {
					$is_today_closed = true;
				} else if ( ! empty( $closed_dates ) ) {
					foreach ( $closed_dates as $closed_date ) {
						$cls_start = isset( $closed_date['opcl_start'] ) ? $closed_date['opcl_start'] : '';
						$cls_end   = isset( $closed_date['opcl_end'] ) ? $closed_date['opcl_end'] : '';
						if ( $cls_start != '' && $cls_end != '' && $real_today_ts <= $cls_end && ($real_today_ts + 86399) >= $cls_start ) {
							$is_today_closed = true;
							break;
						}
					}
				}
				
				if ( ! $is_today_closed && $this->is_store_open() ) {
					$show_exhausted_warning = true;
				}
			}
		}

		$date_val = WC()->session->get( '_user_deli_date' );
		if ( empty($date_val) && isset($checkout->get_value) ) {
			$date_val = $checkout->get_value('o100_date_deli');
		}

		$text_datedel = $current_method === 'takeaway' ? esc_html__('Pickup Date', 'order100') : esc_html__('Delivery Date', 'order100');
		
		if ( $show_exhausted_warning ) {
			$exhausted_msg = $current_method === 'takeaway' ? 
				esc_html__('Due to required prep time, pickup orders for today have ended. You can pre-order for tomorrow.', 'order100') : 
				esc_html__('Due to required prep time, delivery orders for today have ended. You can pre-order for tomorrow.', 'order100');
			echo '<div class="o100-warning-message o100-today-exhausted" style="background: none !important; border: none !important; box-shadow: none !important; margin-bottom: 20px; color: #ff9800; font-weight: bold; font-size: 15px; padding: 0 10px; line-height: 1.5;">' . $exhausted_msg . '</div>';
		}

		woocommerce_form_field( 
			'o100_date_deli', array(
				'type'        => 'select',
				'required'    => true,
				'class'       => array('o100-date-deli form-row-wide'),
				'label'       => $text_datedel,
				'placeholder' => '',
				'options'     => $deli_date,
				'default'     => '',
			),
			$date_val 
		);

		$time_val = WC()->session->get( '_user_deli_time' );
		$text_timedel = $current_method === 'takeaway' ? esc_html__('Pickup Time', 'order100') : esc_html__('Delivery Time', 'order100');

		woocommerce_form_field( 
			'o100_time_deli', array(
				'type'        => 'select',
				'required'    => true,
				'class'       => array('o100-time-deli form-row-wide'),
				'label'       => $text_timedel,
				'placeholder' => '',
				'options'     => array( '' => esc_html__('Select a time slot', 'order100') ), // Populated by AJAX
				'default'     => '',
			),
			$time_val
		);

		echo '</div>'; // End exwf-deli-field / exwf-take-field
		echo '</div>'; // End o100-dynamic-checkout-fields
	}

	/**
	 * Display warning on checkout
	 */
	public function display_checkout_warning() {
		$options = $this->get_options();
		

		// Check if parent "Opening Time" is disabled (meaning allow orders anytime)
		$store_options = get_option( 'o100_store_hours', array() );
		$parent_open_close_setting = empty( $store_options['o100_op_cl'] ) || $store_options['o100_op_cl'] === 'on' ? '' : 'enable';
		if ( $parent_open_close_setting !== '' ) { // '' means Disable in parent plugin options array
			return;
		}

		if ( ! $this->is_store_open() ) {
			$message = ! empty( $this->current_close_reason ) ? $this->current_close_reason : ( ! empty( $options['o100_warning_message'] ) ? $options['o100_warning_message'] : esc_html__( 'We are currently closed. Please select a future time for your order.', 'order100' ) );
			wc_print_notice( $message, 'notice' );
		}
	}

	/**
	 * Inject JS for popup warning
	 */
	public function inject_popup_warning_script() {
		$options = $this->get_options();
		

		// Check if parent "Opening Time" is disabled
		$parent_open_close_setting = O100_Store_Data::get_open_close_mode();
		if ( $parent_open_close_setting !== '' ) {
			return;
		}

		$is_open = $this->is_store_open();
		$message = ! empty( $this->current_close_reason ) ? $this->current_close_reason : ( ! empty( $options['o100_warning_message'] ) ? $options['o100_warning_message'] : esc_html__( 'We are currently closed. Please select a future time for your order.', 'order100' ) );

		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				var isOpen = <?php echo json_encode( $is_open ); ?>;
				var message = <?php echo json_encode( $message ); ?>;

				if (!isOpen) {
					// Use MutationObserver for better performance than setInterval
					var observer = new MutationObserver(function(mutations, obs) {
						var $popupInfo = $('.ex-popup-location .ex-popup-info');
						if ($popupInfo.length && $('.o100-warning-message').length === 0) {
							$popupInfo.prepend('<p class="o100-warning-message woocommerce-info">' + message + '</p>');
							obs.disconnect(); // Stop observing once injected
						}
					});
					observer.observe(document.body, { childList: true, subtree: true });
				}
			});
		</script>
		<?php
	}

	/**
	 * Add ASAP option to time slot
	 */
	public function add_asap_option( $args, $key, $value ) {
		if ( $key !== 'o100_time_deli' ) {
			return $args;
		}

		$options = $this->get_options();
		if ( empty( $options['o100_enable_asap'] ) ) {
			return $args;
		}

		if ( $this->is_store_open() ) {
			$label = ! empty( $options['o100_asap_label'] ) ? $options['o100_asap_label'] : 'ASAP';
			// Prepend ASAP option
			$new_options = array( 'ASAP' => $label );
			if ( is_array( $args['options'] ) ) {
				$args['options'] = $new_options + $args['options'];
			} else {
				$args['options'] = $new_options;
			}
		}

		return $args;
	}

	/**
	 * Enqueue Scripts
	 */
	public function enqueue_scripts() {
		$options = $this->get_options();
				wp_enqueue_script( 'o100-script', O100_URL . 'assets/js/o100-script.js', array( 'jquery' ), '1.0.0', true );

		// Enqueue Google Maps API if configured
		$api_opts = get_option( 'o100_api_integration', array() );
		$api_key = !empty($api_opts['o100_ggmap_api_js']) ? $api_opts['o100_ggmap_api_js'] : '';
		if ( ! empty( $api_key ) ) {
			wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $api_key ) . '&libraries=places', array(), null, true );
			wp_enqueue_script( 'o100-google-maps', O100_URL . 'assets/js/o100-google-maps.js', array( 'jquery', 'google-maps' ), time(), true );

			// Determine Store Origin Address
			$origin_address = '';
			$loc_id = O100_Store_Data::get_selected_location_id();
			if ( ! $loc_id && isset(WC()->session) ) {
				$loc_slug = WC()->session->get( 'ex_userloc' );
				if ( $loc_slug ) {
					$locations = get_posts( array(
						'name'           => $loc_slug,
						'post_type'      => 'o100_location',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
					) );
					if ( ! empty( $locations ) ) {
						$loc_id = $locations[0];
					}
				}
			}
			
			if ( $loc_id ) {
				$origin_address = get_post_meta( $loc_id, 'o100_address', true );
			}
			
			if ( empty( $origin_address ) ) {
				// Fallback to general settings store address
				$opts = get_option('o100_store_profile', array());
				if (!empty($opts['o100_store_address'])) {
					$origin_address = $opts['o100_store_address'];
				} else {
					$origin_address = get_option('woocommerce_store_address', '') . ' ' . get_option('woocommerce_store_city', '') . ' ' . get_option('woocommerce_store_postcode', '') . ' ' . get_option('woocommerce_store_country', '');
				}
			}

			// Calculate Max Allowed Distance
			$deli_opts = get_option( 'o100_delivery', array() );
			$max_allowed = '';
			if ( $loc_id ) {
				$loc_max = get_post_meta( $loc_id, 'o100_distance_restrict', true );
				if ( $loc_max !== '' ) $max_allowed = floatval( $loc_max );
			}
			if ( $max_allowed === '' && isset( $deli_opts['o100_deli_dis'] ) && $deli_opts['o100_deli_dis'] !== '' ) {
				$max_allowed = floatval( $deli_opts['o100_deli_dis'] );
			}
			if ( $max_allowed === '' && isset( $deli_opts['o100_enable_shp_km'] ) && $deli_opts['o100_enable_shp_km'] === 'on' && isset( $deli_opts['o100_shp_km_loc'] ) && is_array( $deli_opts['o100_shp_km_loc'] ) ) {
				$max_tier = 0;
				foreach ( $deli_opts['o100_shp_km_loc'] as $tier ) {
					if ( isset( $tier['max_distance'] ) && floatval( $tier['max_distance'] ) > $max_tier ) {
						$max_tier = floatval( $tier['max_distance'] );
					}
				}
				if ( $max_tier > 0 ) $max_allowed = $max_tier;
			}

			$ui_prefs = get_option( 'o100_ui_prefs', array() );
			$custom_msg = isset( $ui_prefs['o100_msg_out_of_range'] ) && $ui_prefs['o100_msg_out_of_range'] !== '' 
				? $ui_prefs['o100_msg_out_of_range'] 
				: __( 'We\'re sorry! Your location ({dist} km) is outside our delivery range of {max} km. Please consider switching to Pickup to place your order.', 'order100' );

			wp_localize_script( 'o100-google-maps', 'o100_gmap_vars', array(
				'origin_address' => trim( $origin_address ),
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'o100_gmap_nonce' ),
				'max_allowed'    => $max_allowed !== '' ? $max_allowed : -1,
				'error_msg'      => $custom_msg
			) );
		}

		// Enqueue Category Anchor Navigation script if enabled
		if ( isset( $options['o100_enable_category_anchor'] ) && $options['o100_enable_category_anchor'] === 'on' ) {
			wp_enqueue_script( 'o100-category-anchor', O100_URL . 'assets/js/o100-category-anchor.js', array( 'jquery' ), '1.0.0', true );
			
			$scroll_offset = isset( $options['o100_category_anchor_offset'] ) && $options['o100_category_anchor_offset'] !== '' 
				? intval( $options['o100_category_anchor_offset'] ) 
				: -100;
			
			wp_localize_script( 'o100-category-anchor', 'o100_category_anchor', array(
				'scroll_offset' => $scroll_offset,
			) );
		}

		// Ensure variation scripts are available globally for our product modal
		wp_enqueue_script( 'wc-add-to-cart-variation' );
		wp_enqueue_script( 'wc-single-product' );

		// Always Enqueue Order Notes Persistence script
		wp_enqueue_script( 'o100-order-notes', O100_URL . 'assets/js/o100-order-notes.js', array( 'jquery' ), '1.0.0', true );
		wp_localize_script( 'o100-order-notes', 'o100_order_notes_vars', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'o100_order_notes_nonce' ),
		) );

		// Check if parent "Opening Time" is disabled (meaning allow orders anytime)
		$parent_open_close_setting = O100_Store_Data::get_open_close_mode();
		
		// Use empty() because get_option might return false or null instead of ''
		$is_parent_disabled = empty( $parent_open_close_setting );
		$is_store_open      = $this->is_store_open();
		
		$should_show_warning = ( $is_parent_disabled && ! $is_store_open );

		$label   = ! empty( $options['o100_asap_label'] ) ? $options['o100_asap_label'] : 'ASAP';
		$message = ! empty( $this->current_close_reason ) ? $this->current_close_reason : ( ! empty( $options['o100_outside_hours_msg'] ) ? $options['o100_outside_hours_msg'] : esc_html__( 'We are currently closed. Please select a future time for your order.', 'order100' ) );

		// Calculate today's midnight timestamp in site's timezone
		$current_time = O100_Store_Data::get_current_time();
		$today_midnight = strtotime( 'today', $current_time );

		// Check if there are remaining hours for today
		// Default to is_store_open. If open, we definitely have remaining hours.
		$has_remaining_hours = $is_store_open;
		
		if ( ! $has_remaining_hours ) {
			// Save old timezone to restore later
			$old_tz = date_default_timezone_get();
			date_default_timezone_set( 'UTC' );

			// 1. Check if today is a closed date
			$is_closed_date = false;
			$closed_dates = O100_Settings::get_formatted_holidays();
			if ( is_array( $closed_dates ) && ! empty( $closed_dates ) ) {
				foreach ( $closed_dates as $key => $closed_date ) {
					$cls_start = isset( $closed_date['opcl_start'] ) ? $closed_date['opcl_start'] : '';
					$cls_end   = isset( $closed_date['opcl_end'] ) ? $closed_date['opcl_end'] : '';
					if ( $cls_start != '' && $current_time >= $cls_start && $cls_end != '' && $current_time <= $cls_end ) {
						$is_closed_date = true;
						break;
					}
				}
			}

			// 2. Check location specific closed status
			$log_selected = O100_Store_Data::get_selected_location_id();
			$loc_opcls    = O100_Store_Data::is_location_enabled() ? 'yes' : 'no';
			if ( $log_selected != '' && $loc_opcls == 'yes' ) {
				$closed_loc = get_term_meta( $log_selected, 'exwfood_loc_closed', true );
				if ( $closed_loc == 'yes' ) {
					$is_closed_date = true;
				}
			}

			if ( ! $is_closed_date ) {
				// Get opening hours for today
				$opcl_time = O100_Store_Data::get_day_hours( date( 'D', $current_time ) );
				
				// Override with location specific hours if applicable
				if ( $log_selected != '' && $loc_opcls == 'yes' ) {
					$opcl_time_log = get_term_meta( $log_selected, 'exwfood_' . date( 'D', $current_time ) . '_opcl_time', true );
					$opcl_time     = is_array( $opcl_time_log ) && ! empty( $opcl_time_log ) ? $opcl_time_log : $opcl_time;
				}

				if ( is_array( $opcl_time ) && ! empty( $opcl_time ) ) {
					foreach ( $opcl_time as $it_time ) {
						$close_hours = isset( $it_time['close-time'] ) ? intval( date( 'H', strtotime( $it_time['close-time'] ) ) ) * 3600 + intval( date( 'i', strtotime( $it_time['close-time'] ) ) ) * 60 : 0;
						$open_hours  = isset( $it_time['open-time'] ) ? intval( date( 'H', strtotime( $it_time['open-time'] ) ) ) * 3600 + intval( date( 'i', strtotime( $it_time['open-time'] ) ) ) * 60 : 0;
						
						// Handle overnight hours (e.g. close at 2 AM next day)
						if ( $close_hours < $open_hours ) {
							$close_hours += 86400;
						}
						
						// Calculate seconds from midnight for current time
						$hours_current   = intval( date( 'H', $current_time ) );
						$minutes_current = intval( date( 'i', $current_time ) );
						$seconds_from_midnight = $hours_current * 3600 + $minutes_current * 60;

						if ( $close_hours > $seconds_from_midnight ) {
							$has_remaining_hours = true;
							break; // Found a slot that ends in the future
						}
					}
				} else {
					// Simple hours check
					$open_hours_str  = O100_Store_Data::get_global_start_time();
					$close_hours_str = O100_Store_Data::get_global_end_time();
					
					if ( $open_hours_str != '' && $close_hours_str != '' ) {
						$close_hours = intval( date( 'H', strtotime( $close_hours_str ) ) ) * 3600 + intval( date( 'i', strtotime( $close_hours_str ) ) ) * 60;
						$open_hours  = intval( date( 'H', strtotime( $open_hours_str ) ) ) * 3600 + intval( date( 'i', strtotime( $open_hours_str ) ) ) * 60;
						
						if ( $close_hours < $open_hours ) {
							$close_hours += 86400;
						}

						$hours_current   = intval( date( 'H', $current_time ) );
						$minutes_current = intval( date( 'i', $current_time ) );
						$seconds_from_midnight = $hours_current * 3600 + $minutes_current * 60;

						if ( $close_hours > $seconds_from_midnight ) {
							$has_remaining_hours = true;
						}
					}
				}
			}
			
			// Restore timezone
			date_default_timezone_set( $old_tz );
		}

		
		// Get cumulative qty limits for products on this page
		$cumqty_limits = array();
		if ( function_exists( 'exwoo_get_options' ) ) {
			// Get current product if on single product page
			$current_product_id = get_the_ID();
			$product_ids = array( $current_product_id );
			
			// Also get products from recent posts (for listing pages)
			global $wp_query;
			if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
				foreach ( $wp_query->posts as $post ) {
					if ( $post->post_type === 'product' ) {
						$product_ids[] = $post->ID;
					}
				}
			}
			
			$product_ids = array_unique( $product_ids );
			
			foreach ( $product_ids as $product_id ) {
				$data_options = exwoo_get_options( $product_id );
				
				if ( is_array( $data_options ) && ! empty( $data_options ) ) {
					$product_limits = array();
					$index = 0;
					foreach ( $data_options as $key => $opts ) {
						$type = isset( $opts['_type'] ) ? $opts['_type'] : 'checkbox';
						if ( ( $type == '' || $type == 'checkbox' ) && ! empty( $opts['_max_cumqty'] ) ) {
							$product_limits[ $index ] = $opts['_max_cumqty'];
						}
						$index++;
					}
					if ( ! empty( $product_limits ) ) {
						$cumqty_limits[ $product_id ] = $product_limits;
					}
				}
			}
		}
		
		$addon_enabled = true;

		wp_localize_script( 'o100-script', 'o100_vars', array(
			'ajaxurl'             => admin_url( 'admin-ajax.php' ),
			'addon_enabled'       => $addon_enabled ? 1 : 0,
			'parent_setting'      => $parent_open_close_setting, 
			'is_open'             => $is_store_open ? 1 : 0,
			'has_remaining_hours' => $has_remaining_hours ? 1 : 0,
			'show_warning'        => $should_show_warning ? 1 : 0,
			'warning_message'     => $message,
			'asap_label'          => $label,
			'today'               => $today_midnight,
			'enable_asap'         => ( isset( $options['o100_enable_asap'] ) && $options['o100_enable_asap'] === 'on' ) ? 1 : 0,
			'cumqty_limits'       => $cumqty_limits,
		) );

		// Inject Cumulative Quantity Validation Script
		// This needs to run on pages with the food listing shortcode, not just product pages
		add_action( 'wp_print_footer_scripts', array( $this, 'inject_cumulative_qty_script' ), 99 );
	}

	/**
	 * Check if the store is currently open based on hours (ignoring the "Disable" setting)
	 *
	 * @return boolean
	 */
	public function is_store_open() {
		if ( $this->is_store_open_cache !== null ) {
			return $this->is_store_open_cache;
		}

		$cure_time = O100_Store_Data::get_current_time();

		// Check Emergency Closure first (admin bar toggle)
		if ( class_exists( 'O100_Emergency_Closure' ) ) {
			$closure_data = O100_Emergency_Closure::get_active_closure_data();
			if ( $closure_data !== false ) {
				if ( ! empty( $closure_data['reason'] ) ) {
					$this->current_close_reason = $closure_data['reason'];
				}
				$this->is_store_open_cache = false;
				return false;
			}
		}
		
		// Save old timezone to restore later
		$old_tz = date_default_timezone_get();
		date_default_timezone_set( 'UTC' );

		$hours_current   = intval( date( 'H', $cure_time ) );
		$minutes_current = intval( date( 'i', $cure_time ) );
		$times = $cure_time - $hours_current * 3600 - $minutes_current * 60;

		$is_open = true; // Default to open unless found closed

		// Closed from date to date
		$closed_dates = O100_Store_Data::get_holidays();
		if ( is_array( $closed_dates ) && ! empty( $closed_dates ) ) {
			foreach ( $closed_dates as $key => $closed_date ) {
				$cls_start = isset( $closed_date['opcl_start'] ) ? $closed_date['opcl_start'] : '';
				$cls_end   = isset( $closed_date['opcl_end'] ) ? $closed_date['opcl_end'] : '';
				if ( $cls_start != '' && $cure_time >= $cls_start && $cls_end != '' && $cure_time <= $cls_end ) {
					$is_open = false;
					if ( ! empty( $closed_date['close_reason'] ) ) {
						$this->current_close_reason = $closed_date['close_reason'];
					}
					break;
				}
			}
		}

		if ( $is_open ) {
			// advanced open closing time by day of week
			$opcl_time = O100_Store_Data::get_day_hours( date( 'D', $cure_time ) );
			
			// for each location
			$log_selected = O100_Store_Data::get_selected_location_id();
			$loc_opcls    = O100_Store_Data::is_location_enabled() ? 'yes' : 'no';
			
			if ( $log_selected != '' && $loc_opcls == 'yes' ) {
				$closed_loc = get_post_meta( $log_selected, 'o100_closed', true );
				if ( $closed_loc == 'yes' ) {
					$is_open = false;
				} else {
					$override_hours = get_post_meta( $log_selected, 'o100_override_hours', true );
					if ( $override_hours === 'yes' ) {
						$branch_hours = get_post_meta( $log_selected, 'o100_hours', true );
						$day_abbr = date( 'D', $cure_time );
						if ( is_array( $branch_hours ) && isset( $branch_hours[$day_abbr] ) && is_array( $branch_hours[$day_abbr] ) ) {
							$opcl_time_log = array();
							foreach ( $branch_hours[$day_abbr] as $slot ) {
								if ( !empty($slot['start']) && !empty($slot['end']) ) {
									$opcl_time_log[] = array( 'open-time' => $slot['start'], 'close-time' => $slot['end'] );
								}
							}
							if ( empty( $opcl_time_log ) ) {
								$is_open = false; // Closed today
							} else {
								$opcl_time = $opcl_time_log;
							}
						} else {
							$is_open = false; // Closed today
						}
					}
				}
			}
		}

		if ( $is_open ) {
			if ( is_array( $opcl_time ) && ! empty( $opcl_time ) ) {
				$check = true;
				foreach ( $opcl_time as $it_time ) {
					$open_hours  = isset( $it_time['open-time'] ) ? intval( date( 'H', strtotime( $it_time['open-time'] ) ) ) * 3600 + intval( date( 'i', strtotime( $it_time['open-time'] ) ) ) * 60 : 0;
					$close_hours = isset( $it_time['close-time'] ) ? intval( date( 'H', strtotime( $it_time['close-time'] ) ) ) * 3600 + intval( date( 'i', strtotime( $it_time['close-time'] ) ) ) * 60 : 0;
					
					if ( $close_hours < $open_hours ) {
						$close_hours = $close_hours + 86400;
					}
					
					$open_hours_unix  = $times + $open_hours;
					$close_hours_unix = $times + $close_hours;
					
					if ( $open_hours_unix > $close_hours_unix || $cure_time < $open_hours_unix || $cure_time > $close_hours_unix ) {
						$check = false;
					} else {
						$check = true;
						break;
					}
				}
				$is_open = $check;
			} else {
				$open_hours  = O100_Store_Data::get_global_start_time();
				$close_hours = O100_Store_Data::get_global_end_time();
				
				if ( $open_hours == '' || $close_hours == '' ) {
					$is_open = false; 
				} else {
					$open_hours_unix  = $times + intval( date( 'H', strtotime( $open_hours ) ) ) * 3600 + intval( date( 'i', strtotime( $open_hours ) ) ) * 60;
					$close_hours_unix = $times + intval( date( 'H', strtotime( $close_hours ) ) ) * 3600 + intval( date( 'i', strtotime( $close_hours ) ) ) * 60;
					
					if ( $open_hours_unix > $close_hours_unix || $cure_time < $open_hours_unix || $cure_time > $close_hours_unix ) {
						$is_open = false;
					}
				}
			}
		}

		// Restore timezone
		date_default_timezone_set( $old_tz );

		$this->is_store_open_cache = $is_open;
		return $is_open;
	}

	/**
	 * Validate ASAP option
	 * Bypass the parent plugin's time slot validation if ASAP is selected
	 */
	public function validate_asap_option() {
		if ( isset( $_POST['o100_time_deli'] ) && $_POST['o100_time_deli'] === 'ASAP' ) {
			// Flag this as an ASAP order
			$this->is_asap_order = true;
		}
	}

	/**
	 * Save ASAP meta to the order
	 *
	 * @param int $order_id
	 */
	public function save_asap_meta( $order_id ) {
		if ( $this->is_asap_order ) {
			update_post_meta( $order_id, 'o100_time_deli', 'ASAP' );
			// Also update the underscore version just in case
			update_post_meta( $order_id, '_o100_time_deli', 'ASAP' );
		}
        
        // Save Order Type from Session
        if ( isset( WC()->session ) ) {
            $order_method = WC()->session->get( '_o100_order_method' );
            if ( $order_method ) {
                update_post_meta( $order_id, '_o100_order_type', $order_method );
                // Also save standard key if main plugin doesn't
                update_post_meta( $order_id, '_o100_order_method', $order_method );
            }
        }
	}

	/**
	 * Save Native Checkout Fields
	 */
	public function save_native_checkout_fields( $order_id ) {
		// Save o100_location
		if ( isset( $_POST['o100_location'] ) && ! empty( $_POST['o100_location'] ) ) {
			$loc_id = intval( $_POST['o100_location'] );
			update_post_meta( $order_id, '_o100_location_id', $loc_id );
			
			$loc_post = get_post($loc_id);
			if ($loc_post) {
				update_post_meta( $order_id, 'exwoofood_ck_loca', $loc_post->post_name );
			}
		}

		// Save Date
		if ( isset( $_POST['o100_date_deli'] ) && ! empty( $_POST['o100_date_deli'] ) ) {
			$date_val = sanitize_text_field( $_POST['o100_date_deli'] );
			update_post_meta( $order_id, 'exwfood_date_deli', $date_val );
			update_post_meta( $order_id, '_o100_delivery_date', $date_val );
		}

		// Save Time
		if ( isset( $_POST['o100_time_deli'] ) && ! empty( $_POST['o100_time_deli'] ) ) {
			$time_val = sanitize_text_field( $_POST['o100_time_deli'] );
			update_post_meta( $order_id, 'exwfood_time_deli', $time_val );
			update_post_meta( $order_id, '_o100_delivery_time', $time_val );
		}
	}


	/**
	 * Add cumulative quantity field to product options
	 */
	public function add_cumulative_qty_field() {
		if ( ! function_exists( 'cmb2_get_metabox' ) ) {
			return;
		}

		$cmb = cmb2_get_metabox( 'exwo_addition_options' );
		if ( $cmb ) {
			$cmb->add_group_field( 'exwo_options', array(
				'name' => __( 'Maximum cumulative quantity', 'order100' ),
				'description' => __( 'Enter maximum total quantity for all selected options combined', 'order100' ),
				'id'   => '_max_cumqty',
				'type' => 'text',
				'classes' => 'exwo-stgeneral exhide-radio exhide-select exhide-quantity exhide-textbox exhide-textarea exwo-op-max-cumqty',
			) );
		}
	}

	/**
	 * Inject cumulative quantity validation script
	 */
	public function inject_cumulative_qty_script() {
		// Output the script on all pages - it will only activate on elements with data-maxcumqty attribute
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Get limits data from localized script
			var allLimits = (typeof o100_vars !== 'undefined' && o100_vars.cumqty_limits) ? o100_vars.cumqty_limits : {};
			
			var errorMsg = '<?php echo esc_js( __( 'You can only add max %s total items across all options.', 'order100' ) ); ?>';

			function applyLimitsToProduct($form) {
				// Try to get product ID from form or modal
				var productId = null;
				
				// Method 1: From add-to-cart button value
				var $addToCartBtn = $form.find('button[name="add-to-cart"]');
				if ($addToCartBtn.length && $addToCartBtn.val()) {
					productId = $addToCartBtn.val();
				}
				
				// Method 2: From modal ID
				if (!productId) {
					var $modal = $form.closest('.ex_modal');
					if ($modal.length) {
						var modalId = $modal.attr('id');
						if (modalId) {
							productId = modalId.replace('product-', '');
						}
					}
				}
				
				if (!productId || !allLimits[productId]) {
					return;
				}
				
				var limits = allLimits[productId];
				
				// Find checkbox groups and apply limits
				var $checkboxGroups = $form.find('.ex-checkbox');
				$checkboxGroups.each(function(index) {
					var $container = $(this);
					if (limits[index]) {
						$container.attr('data-maxcumqty', limits[index]);
						$container.addClass('ex-required-max-cumqty');
					}
				});
			}

			function initCumulativeValidation() {
				// Apply limits to all forms on the page
				$('form.cart').each(function() {
					applyLimitsToProduct($(this));
				});
				
				// Find all checkbox option groups that have max cumulative qty set
				var checkboxGroups = $('.ex-checkbox[data-maxcumqty]');
				
				checkboxGroups.each(function() {
					var $container = $(this);
					
					// Skip if already initialized
					if ($container.data('cumqty-initialized')) {
						return;
					}
					
					var maxCumQty = $container.attr('data-maxcumqty');
					
					$container.data('cumqty-initialized', true);

					// Add error message if not present
				if (!$container.find('.ex-required-maxcumqty-message').length) {
					var msg = errorMsg.replace('%s', maxCumQty);
					$container.append('<p class="ex-red-message ex-required-maxcumqty-message" style="display:none; color:#e2401c; font-weight:bold;">' + msg + '</p>');
				}
				});
			}

			// Validation function (needs to be accessible globally for event delegation)
			function validateCumQty($container) {
				var maxCumQty = $container.attr('data-maxcumqty');
				if (!maxCumQty) return true;
				
				var totalQty = 0;
				
				$container.find('.ex-options:checked').each(function() {
					var $checkbox = $(this);
					var checkboxName = $checkbox.attr('name');
					var checkboxVal = $checkbox.val();
					
					// Find corresponding quantity input
					var qtyInputName = checkboxName.replace('[]', '_' + checkboxVal + '_qty');
					var $qtyInput = $('input[name="' + qtyInputName + '"]');
					
					var qty = 1;
					if ($qtyInput.length && $.isNumeric($qtyInput.val())) {
						qty = parseFloat($qtyInput.val());
					}
					
					totalQty += qty;
				});

				if (totalQty > maxCumQty) {
					$container.find('.ex-required-maxcumqty-message').fadeIn();
					return false;
				} else {
					$container.find('.ex-required-maxcumqty-message').fadeOut();
					return true;
				}
			}

			// Event delegation for checkbox changes
			$(document).on('change', '.ex-checkbox.ex-required-max-cumqty .ex-options', function() {
				var $container = $(this).closest('.ex-checkbox');
				if (!validateCumQty($container)) {
					// Uncheck the last checked box
					$(this).prop('checked', false);
				}
			});

			// Event delegation for quantity input changes
			$(document).on('change', '.ex-checkbox.ex-required-max-cumqty .ex-qty-op', function() {
				var $container = $(this).closest('.ex-checkbox');
				if (!validateCumQty($container)) {
					// Revert quantity
					var currentVal = parseInt($(this).val()) || 1;
					if (currentVal > 1) {
						$(this).val(currentVal - 1);
					}
				}
			});

			// Initialize on page load
			initCumulativeValidation();

			// Method 1: Watch for modal opening using MutationObserver
			var observer = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					if (mutation.addedNodes.length) {
						$(mutation.addedNodes).each(function() {
							if ($(this).hasClass('ex_modal') || $(this).find('.ex_modal').length) {
								setTimeout(function() {
									initCumulativeValidation();
								}, 500);
							}
						});
					}
				});
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true
			});

			// Method 2: Listen for modal click events (backup method)
			$(document).on('click', '.exfd_modal_click', function() {
				setTimeout(function() {
					initCumulativeValidation();
				}, 1000);
			});


			// Also fade out error on form submit
			$(document).on('submit', 'form.cart', function() {
				$('.ex-required-maxcumqty-message').fadeOut();
			});
		});
		</script>
		<?php
	}

	/**
	 * Validate cumulative quantity on add to cart
	 */
	public function validate_cumulative_qty( $passed, $product_id, $quantity, $variation_id = false ) {
		if ( ! function_exists( 'exwoo_get_options' ) ) {
			return $passed;
		}

		$data_options = exwoo_get_options( $product_id );
		if ( is_array( $data_options ) && ! empty( $data_options ) ) {
			foreach ( $data_options as $key => $options ) {
				$type = isset( $options['_type'] ) ? $options['_type'] : 'checkbox';
				$max_cumqty = isset( $options['_max_cumqty'] ) && $options['_max_cumqty'] != '' ? $options['_max_cumqty'] : 0;

				if ( $type == 'checkbox' && $max_cumqty > 0 ) {
					$data_exts = isset( $_POST['ex_options_' . $key] ) ? $_POST['ex_options_' . $key] : '';
					if ( is_array( $data_exts ) && ! empty( $data_exts ) ) {
						$cumqty_tt = 0;
						foreach ( $data_exts as $value ) {
							if ( $value != '' ) {
								$qty_key = 'ex_options_' . $key . '_' . $value . '_qty';
								$qty_op = isset( $_POST[ $qty_key ] ) && is_numeric( $_POST[ $qty_key ] ) ? $_POST[ $qty_key ] : 1;
								$cumqty_tt += $qty_op;
							}
						}
						if ( $cumqty_tt > $max_cumqty ) {
							$passed = false;
							wc_add_notice( sprintf( __( 'Error: You can only add max %s total items across all options.', 'order100' ), $max_cumqty ), 'error' );
							break;
						}
					}
				}
			}
		}
		return $passed;
	}

/**
 * Force needs shipping address to true if feature is enabled
 * This ensures the shipping form container is rendered even if "Force shipping to billing" is on
 */
public function force_needs_shipping_address( $needs_shipping ) {
	$options = $this->get_options();
	if ( empty( $options['o100_enable_shipping_address'] ) ) {
		return $needs_shipping;
	}

	// We must ALWAYS return true so that WooCommerce renders the shipping fields in the initial DOM.
	// If the page is loaded as Pickup, the fields will be correctly hidden by our frontend Javascript.
	// If we return false here, WooCommerce completely omits the HTML, breaking the AJAX switch to Delivery.
	return true;
}

/**
 * Modify shipping fields to remove names and set defaults
 * Also rename Billing Labels to Cardholder Name
 */
public function modify_shipping_fields( $fields ) {
	$options = $this->get_options();
	if ( empty( $options['o100_enable_shipping_address'] ) ) {
		return $fields;
	}

	// 1. Rename Billing Labels & Add Instructions (Global)
	$instruction_options = array( '' => __( 'Select an option...', 'order100' ) );
	$raw_instr = isset( $options['o100_delivery_instruction_options'] ) ? $options['o100_delivery_instruction_options'] : "Leave at door\nHand to me\nLeave at reception";
	$lines = explode( "\n", str_replace( "\r", "", $raw_instr ) );
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( ! empty( $line ) ) {
			$instruction_options[$line] = $line;
		}
	}

	$fields['shipping']['o100_delivery_instruction'] = array(
		'type'          => 'select',
		'label'         => __( 'Delivery Instructions', 'order100' ),
		'required'      => false,
		'class'         => array( 'form-row-wide' ),
		'options'       => $instruction_options,
		'priority'      => 999,
	);

	if ( isset( $fields['billing']['billing_first_name'] ) ) {
		$fields['billing']['billing_first_name']['label'] = __( 'Cardholder First Name', 'order100' );
	}
	if ( isset( $fields['billing']['billing_last_name'] ) ) {
		$fields['billing']['billing_last_name']['label'] = __( 'Cardholder Last Name', 'order100' );
	}
	// Make Billing Street Address optional as per user request
	if ( isset( $fields['billing']['billing_address_1'] ) ) {
		$fields['billing']['billing_address_1']['required'] = false;
	}

	// 2. Determine Order Method
	$user_odmethod = 'delivery'; 
	if ( isset( WC()->session ) ) {
		$method = WC()->session->get( '_o100_order_method' );
		if ( $method ) {
			$user_odmethod = $method;
		}
	}
	// POST fallback for when session hasn't updated yet
	if ( $user_odmethod === 'delivery' && ! empty( $_POST['o100_order_type'] ) ) {
		$user_odmethod = sanitize_text_field( $_POST['o100_order_type'] );
	}
	
	// Dine-in and Pickup treated same: Not Delivery
	$is_delivery = ( $user_odmethod !== 'pickup' && $user_odmethod !== 'takeaway' && $user_odmethod !== 'dinein' );

	// 3. Restore shipping fields if empty (For Delivery/Pickup)
	if ( ( ! isset( $fields['shipping'] ) || empty( $fields['shipping'] ) ) && isset( WC()->countries ) ) {
		$fields['shipping'] = WC()->countries->get_address_fields( WC()->customer->get_shipping_country(), 'shipping_' );
	}

	// 4. Configure Fields
	$default_country = ! empty( $options['o100_default_country'] ) ? strtoupper( $options['o100_default_country'] ) : 'CA';
	$default_city = ! empty( $options['o100_default_shipping_city'] ) ? $options['o100_default_shipping_city'] : '';
	$default_state = ! empty( $options['o100_default_shipping_state'] ) ? $options['o100_default_shipping_state'] : '';
	
	// Helper to hide and optionalize a field
	$hide_field = function( &$field_array ) {
		if ( ! isset( $field_array['class'] ) ) $field_array['class'] = array();
		$field_array['class'][] = 'o100-hidden-field';
		$field_array['required'] = false; 
	};

	// 5. Apply Logic
	// RESTORE CRITICAL BILLING FIELDS if they were unset by parent plugin or other filters
	// This is often why Guest users don't see them in Pickup/Dine-in mode
	if ( ( ! isset( $fields['billing'] ) || ! isset( $fields['billing']['billing_address_1'] ) ) && isset( WC()->countries ) ) {
		$default_billing = WC()->countries->get_address_fields( WC()->customer->get_billing_country(), 'billing_' );
		if ( ! isset( $fields['billing'] ) ) $fields['billing'] = array();
		
		if ( ! isset( $fields['billing']['billing_address_1'] ) && isset( $default_billing['billing_address_1'] ) ) {
			$fields['billing']['billing_address_1'] = $default_billing['billing_address_1'];
		}
		if ( ! isset( $fields['billing']['billing_postcode'] ) && isset( $default_billing['billing_postcode'] ) ) {
			$fields['billing']['billing_postcode'] = $default_billing['billing_postcode'];
		}
	}

	// Always hide specific billing fields as per user request
	$billing_to_hide = array( 'billing_country', 'billing_city', 'billing_state', 'billing_address_2' );
	foreach ( $billing_to_hide as $bkey ) {
		if ( isset( $fields['billing'][$bkey] ) ) {
			if ( $bkey === 'billing_country' ) {
				$fields['billing'][$bkey]['default'] = $default_country;
			}
			$hide_field( $fields['billing'][$bkey] );
			if ( $bkey === 'billing_country' ) {
				$fields['billing'][$bkey]['required'] = true;
			}
		}
	}

	if ( isset($fields['shipping']) && is_array($fields['shipping']) ) {
		foreach ( $fields['shipping'] as $key => $field ) {
			// Delivery Address Fields
			if ( ! $is_delivery ) {
				// For Pickup/Takeaway/Dine-in: Check the new UI toggle to see if we should disable addresses
				$pickup_opts = get_option( 'o100_pickup', false );
				$dis_addr = true; // Default to true (legacy behavior)
				if ( $pickup_opts !== false ) {
					$dis_addr = isset($pickup_opts['o100_pickup_dis_addr']) && $pickup_opts['o100_pickup_dis_addr'] === 'on';
				}

				if ( $dis_addr ) {
					// Hide AND Remove Required
					$hide_field( $fields['shipping'][$key] );
				}
			} else {
				// For Delivery: Hide Names (background sync targets or unwanted)
				if ( $key === 'shipping_first_name' || $key === 'shipping_last_name' ) {
					$hide_field( $fields['shipping'][$key] );
				}
				
				// For Delivery: Apply Default Hiding 
				if ( $key === 'shipping_country' ) {
					$fields['shipping'][$key]['default'] = $default_country;
					$hide_field( $fields['shipping'][$key] ); 
					$fields['shipping'][$key]['required'] = true; 
				}
				elseif ( $key === 'shipping_city' ) {
					if ( $default_city ) $fields['shipping'][$key]['default'] = $default_city;
					$hide_field( $fields['shipping'][$key] );
				}
				elseif ( $key === 'shipping_state' ) {
					if ( $default_state ) $fields['shipping'][$key]['default'] = $default_state;
					$hide_field( $fields['shipping'][$key] );
				}
			}
		}
	}

	return $fields;
}

/**
 * Add checkbox to toggle BILLING address form (Logic Inverted)
 */
public function add_shipping_address_checkbox( $checkout ) {
	$options = $this->get_options();
	if ( empty( $options['o100_enable_shipping_address'] ) ) {
		return;
	}

	// Only apply for delivery orders
	if ( ! isset( WC()->session ) ) {
		return;
	}

	$user_odmethod = WC()->session->get( '_o100_order_method' );
	if ( $user_odmethod == 'pickup' || $user_odmethod == 'takeaway' || $user_odmethod == 'dinein' ) {
		return;
	}

	?>
	<div class="o100-billing-address-toggle" style="display:none;"> <!-- Hidden initially, moved by JS -->
		<p class="form-row form-row-wide">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="o100_different_billing" id="o100_different_billing" value="1" />
				<span><?php esc_html_e( 'Billing address is different from delivery address', 'order100' ); ?></span>
			</label>
		</p>
	</div>
	<?php
}

/**
 * Add title to shipping form
 */
/**
	 * Enable/Disable payments by order method natively in Order100
	 */
	public function disable_payment_gateways_by_method( $gateways ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { 
			return $gateways; 
		}
		if ( ! isset( WC()->session ) ) { 
			return $gateways; 
		}

		$options = $this->get_options();
		$method = WC()->session->get( '_o100_order_method' );
		if ( ! $method ) {
			$method = WC()->session->get( '_user_order_method' ); // fallback to legacy session
		}
		if ( ! $method ) {
			$method = 'delivery';
		}

		$disable_mt = array();

		if ( $method === 'takeaway' || $method === 'pickup' || $method === 'dinein' ) {
			// In Order100, Pickup and Dine-in both share the o100_pickup_dis_payment settings currently
			if ( ! empty( $options['o100_pickup_dis_payment'] ) && is_array( $options['o100_pickup_dis_payment'] ) ) {
				$disable_mt = $options['o100_pickup_dis_payment'];
			}
		} else {
			// Delivery
			if ( ! empty( $options['o100_delivery_dis_payment'] ) && is_array( $options['o100_delivery_dis_payment'] ) ) {
				$disable_mt = $options['o100_delivery_dis_payment'];
			}
		}

		if ( ! empty( $disable_mt ) ) {
			foreach ( $disable_mt as $it_mt ) {
				if ( isset( $gateways[ $it_mt ] ) ) {
					unset( $gateways[ $it_mt ] );
				}
			}
		}

		return $gateways;
	}

/**
 * Add title to shipping form
 */
public function add_shipping_form_title() {
	$options = $this->get_options();
	if ( empty( $options['o100_enable_shipping_address'] ) ) {
		return;
	}

	// Only apply for delivery orders
	if ( isset( WC()->session ) ) {
		$user_odmethod = WC()->session->get( '_o100_order_method' );
		if ( $user_odmethod == 'pickup' || $user_odmethod == 'takeaway' || $user_odmethod == 'dinein' ) {
			return;
		}
	}

	echo '<h3>' . esc_html__( 'Delivery Address', 'order100' ) . '</h3>';
}

/**
 * Inject JavaScript for layout restructuring
 */
public function inject_shipping_address_script() {
	$options = $this->get_options();
	if ( empty( $options['o100_enable_shipping_address'] ) ) {
		return;
	}

	// Strict check: Only on the actual checkout page, but NOT the order received/thank you page
	if ( ! is_checkout() || is_order_received_page() ) {
		return;
	}

	$is_delivery = true;
	if ( isset( WC()->session ) ) {
		$user_odmethod = WC()->session->get( '_o100_order_method' );
		if ( $user_odmethod == 'pickup' || $user_odmethod == 'takeaway' || $user_odmethod == 'dinein' ) {
			$is_delivery = false;
		}
	}
	// Pass PHP state to JS
	$is_delivery_js = $is_delivery ? 'true' : 'false';

	// Retrieve session data for initial population
	$session_data = array(
		'address'  => '',
		'postcode' => '',
		'details'  => array(),
	);

	if ( isset( WC()->session ) ) {
		$session_data['address']  = WC()->session->get( '_user_deli_adress' );
		$session_data['postcode'] = WC()->session->get( '_user_postcode' );
		$session_data['details']  = WC()->session->get( '_user_deli_adress_details' );
	}
	
	$session_data_json = json_encode( $session_data );

	?>
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		window.o100_isDelivery = <?php echo $is_delivery_js; ?>;
		var sessionAddr = <?php echo $session_data_json; ?>;
		var isInitialized = false;

		// 1. Core Restructuring Function (ID-based, AJAX Robust)
		function wfaRestructureCheckout() {

			
			// a. Create Structure Wrappers if they don't exist
			if ($('#o100-contact-section').length === 0) {
				var billingDesc = '<?php echo esc_js( __( 'Please ensure your billing information is accurate. The Street Address and Postal Code MUST match the records of the credit card you intend to use to avoid payment failure.', 'order100' ) ); ?>';
				
				var $contactSection = $('<div id="o100-contact-section" class="o100-section"><h3>Contact Info</h3><div class="o100-section-content"></div></div>');
				var $deliverySection = $('<div id="o100-delivery-section" class="o100-section"><h3>Delivery Address</h3><div class="o100-section-content"></div></div>');
				var $billingSection = $('<div id="o100-billing-section" class="o100-section"><h3>Billing Details</h3><p class="o100-billing-desc" style="font-size:0.95em; color:#d63638; margin-bottom:15px; margin-top:5px; font-weight:bold; line-height:1.4; border: 1px solid #d63638; padding: 10px; border-radius: 4px; background: #fff8f8;">' + billingDesc + '</p><div class="o100-section-content"></div></div>');

				// Insert into DOM (Prepend to top of customer details)
				var $customerDetails = $('.col2-set'); 
				if ($customerDetails.length === 0) $customerDetails = $('#customer_details');
				
				if ($customerDetails.length) {
					$customerDetails.before($contactSection);
					$contactSection.after($deliverySection);
					$deliverySection.after($billingSection);
				}
			}

			var $contactContent = $('#o100-contact-section .o100-section-content');
			var $deliveryContent = $('#o100-delivery-section .o100-section-content');
			var $billingContent = $('#o100-billing-section .o100-section-content');

			// b. ROBUST HUNTER: Find fields even if IDs are slightly different
			function moveFieldRobustly(id, target) {
				// Stage 1: Standard ID selector
				var $field = $('#' + id + '_field');
				
				// Stage 2: Fragment-based selector (catch partial matches)
				if (!$field.length) {
					$field = $('[id*="' + id + '_field"]');
				}
				
				// Stage 3: Class-based selector
				if (!$field.length) {
					$field = $('.' + id + '_field');
				}
				
				// Stage 4: Input Name search (find the input and then its row)
				if (!$field.length) {
				    var $input = $('input[name="' + id + '"], select[name="' + id + '"]');
				    if ($input.length) {
				        $field = $input.closest('.form-row');
				    }
				}

				if ($field.length && !$field.parent().is(target)) {
					target.append($field);
					// Force display and visibility to override any random plugin hiding
					$field.addClass('o100-force-visible').attr('style', 'display: block !important; visibility: visible !important;');

				}
			}


			
			// Move Contact Info
			$.each(['billing_first_name', 'billing_last_name', 'billing_phone', 'billing_email'], function(i, id) {
				moveFieldRobustly(id, $contactContent);
			});

			// Move Delivery Fields (Delivery only)
			if (window.o100_isDelivery) {
				var $shipFields = $('.woocommerce-shipping-fields .form-row, #customer_details .woocommerce-shipping-fields .form-row');
				$shipFields.each(function() {
					if (!$(this).hasClass('o100-hidden-field') && $(this).attr('id') && $(this).attr('id').indexOf('shipping_') === 0) {
						$deliveryContent.append($(this));
						$(this).addClass('o100-force-visible').attr('style', 'display: block !important; visibility: visible !important;');
					}
				});
				// Custom delivery instructions
				moveFieldRobustly('o100_delivery_instruction', $deliveryContent);
			}

			// Move Billing Details (Address 1 and Postcode)
			$.each(['billing_address_1', 'billing_postcode'], function(i, id) {
				moveFieldRobustly(id, $billingContent);
			});



			// d. UI Cleanups & Forced Hiding
			$('.woocommerce-shipping-fields, .woocommerce-billing-fields').hide();
			
			// Surgically hide the original address containers ONLY if they are truly empty
			// This prevents hiding the "Additional Information" block or other plugin fields
			$('.col2-set, .col-1, .col-2, #customer_details').each(function() {
				// Don't hide if it's our section
				if ($(this).attr('id') && $(this).attr('id').indexOf('o100-') === 0) return;
				
				// Count visible child elements (excluding wrappers that might be empty)
				var $visibleChildren = $(this).children(':visible').not('.o100-hidden-container');
				if ($visibleChildren.length === 0) {
					$(this).addClass('o100-hidden-container').hide();
				} else {
					// If it has children, just remove the border for cleaner look
					$(this).css({ 'border': 'none', 'padding': '0', 'margin-top': '0' });
				}
			});

			// e. Surgical Account Section Hiding (for logged-in users)
			// Only hide the specific account fields wrapper, not the entire column
			var $accountWrapper = $('.woocommerce-account-fields, .checkout-create-account, .create-account');
			if ($('input[name="createaccount"]').length === 0 || !$('input[name="createaccount"]').is(':visible')) {
				$accountWrapper.hide();
				// Also hide its immediate parent if it was just a wrapper for account fields
				$accountWrapper.parent(':not(.col-1):not(.col-2):not(.col2-set)').hide();
			}

			// Rename Labels
			$contactContent.find('[id*="billing_first_name_field"] label').html('First name <abbr class="required" title="required">*</abbr>');
			$contactContent.find('[id*="billing_last_name_field"] label').html('Last name <abbr class="required" title="required">*</abbr>');
			$billingContent.find('[id*="billing_address_1_field"] label').html('Street address (optional)');

			// e. Visibility Logic
			if (window.o100_isDelivery) {
				$('#o100-contact-section').show();
				$('#o100-delivery-section').show();
				var $toggle = $('.o100-billing-address-toggle');
				if ($toggle.length) {
					$toggle.show(); 
					$('#o100-delivery-section').after($toggle); 
				}
				
				if ($('#o100_different_billing').is(':checked')) {
					$('#o100-billing-section').show();
				} else {
					$('#o100-billing-section').hide();
				}
			} else {
				// Pickup / Dine-in: Show ONLY Contact Info and Billing Details
				$('#o100-contact-section').show();
				$('#o100-delivery-section').hide();
				$('.o100-billing-address-toggle').hide();
				$('#o100-billing-section').show(); 
			}

			// Force WC "Ship to different" checked
			var $shipToDiff = $('#ship-to-different-address-checkbox');
			if ($shipToDiff.length) {
				$shipToDiff.prop('checked', true); 
				$('#ship-to-different-address').hide(); 
			}
		}

		// 2. Sync Logic
		function wfaSyncLogic() {
			// a. Name Sync: Billing -> Shipping (Always)
			$('#shipping_first_name').val($('#billing_first_name').val());
			$('#shipping_last_name').val($('#billing_last_name').val());

			// b. Address Sync: Shipping -> Billing (Delivery mode only, when toggle is off)
			if (window.o100_isDelivery && !$('#o100_different_billing').is(':checked')) {

				$('#billing_address_1').val($('#shipping_address_1').val());
				// Although hidden, sync these for backend consistency
				if ($('#shipping_postcode').val()) $('#billing_postcode').val($('#shipping_postcode').val());
				if ($('#shipping_city').val()) $('#billing_city').val($('#shipping_city').val());
				if ($('#shipping_state').val()) $('#billing_state').val($('#shipping_state').val());
				if ($('#shipping_country').val()) $('#billing_country').val($('#shipping_country').val());
				
				$(document.body).trigger('update_checkout');
			}
		}

		function wfaInitialPopulate() {
			if (!window.o100_isDelivery || isInitialized) return;
			

			var shipAddr1 = $('#shipping_address_1').val();
			var sessionAddr1 = sessionAddr && sessionAddr.address ? sessionAddr.address : '';

			// If we have session data (from popup), populate BOTH shipping and billing first
			if (!shipAddr1 && sessionAddr1) {
				$('#shipping_address_1').val(sessionAddr1);
				$('#billing_address_1').val(sessionAddr1);

				if (sessionAddr.postcode) {
					$('#shipping_postcode').val(sessionAddr.postcode);
					$('#billing_postcode').val(sessionAddr.postcode);
				}
				
				// Optional: City sync
				if (sessionAddr.details && sessionAddr.details.length) {
					sessionAddr.details.forEach(function(comp) {
						if (comp.types.indexOf('locality') !== -1) {
							$('#shipping_city').val(comp.long_name);
							$('#billing_city').val(comp.long_name);
						}
						if (comp.types.indexOf('administrative_area_level_1') !== -1) {
							$('#shipping_state').val(comp.short_name);
							$('#billing_state').val(comp.short_name);
						}
					});
				}
			} 
			
			isInitialized = true;
			wfaSyncLogic();
		}

		// 3. Events
		$(document.body).on('updated_checkout', function() {
			wfaRestructureCheckout();
		});

		$(document).on('change', '#o100_different_billing', function() {
			if ($(this).is(':checked')) {
				$('#o100-billing-section').slideDown();
			} else {
				$('#o100-billing-section').slideUp();
				wfaSyncLogic();
			}
		});

		// Trigger sync on any relevant field change
		var syncSelector = '#billing_first_name, #billing_last_name, #shipping_address_1, #shipping_postcode';
		$(document).on('change blur input', syncSelector, function() {
			wfaSyncLogic();
		});

		// Initial Run
		wfaRestructureCheckout();
		setTimeout(wfaInitialPopulate, 100);
		


	});
	</script>
	<style type="text/css">
		/* Scoped specifically to checkout page sections to avoid global leakage */
		.o100-section {
			margin-bottom: 25px;
			background: #fff;
			padding: 15px;
			border: 1px solid #eee;
			border-radius: 5px;
		}
		.o100-section h3 {
			margin-top: 0;
			padding-bottom: 10px;
			border-bottom: 1px solid #eee;
			margin-bottom: 15px;
		}
		.o100-section-content::after {
			content: "";
			display: table;
			clear: both;
		}
		/* Targeted visibility for specific fields in our custom sections. */
		.woocommerce-checkout .o100-force-visible {
			display: block !important;
			visibility: visible !important;
			opacity: 1 !important;
		}
		
		/* Remove extra padding and borders from section containers if they become empty */
		.o100-section-content:empty, .o100-section:hidden {
			display: none !important;
		}
		
		/* Ensure hidden fields stay hidden even if moved (though we try not to move them) */
		.woocommerce-checkout .o100-hidden-field { 
			display: none !important; 
		}

		/* Global override to kill borders on emptied standard containers */
		.woocommerce-checkout .o100-hidden-container {
			border: none !important;
			padding: 0 !important;
			margin: 0 !important;
			background: none !important;
			min-height: 0 !important;
			height: 0 !important;
			overflow: hidden !important;
		}
	</style>
	<?php
}

	/**
	 * Register custom REST API routes for Locations and Store Status
	 */
	public function register_location_api_routes() {
		$options = $this->get_options();
		
		// Register Location API if enabled
		if ( isset( $options['o100_enable_location_api'] ) && $options['o100_enable_location_api'] === 'on' ) {
			register_rest_route( 'o100-api/v1', '/locations', array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_locations_api' ),
				'permission_callback' => '__return_true', 
			) );
		}

		// Register Store Status API if enabled
		if ( isset( $options['o100_enable_store_status_api'] ) && $options['o100_enable_store_status_api'] === 'on' ) {
			register_rest_route( 'o100-api/v1', '/store-status', array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_store_status_api' ),
					'permission_callback' => '__return_true', // Public
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_store_status_api' ),
					'permission_callback' => array( $this, 'check_store_status_permission' ),
				),
			) );
		}
	}

	/**
	 * Permission callback for updating store status
	 * 
	 * @return boolean|WP_Error
	 */
	public function check_store_status_permission() {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'order100' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Callback for getting locations list
	 * 
	 * @return array
	 */
	public function get_locations_api() {
		// taxonomy 'o100_location'
		$terms = get_terms( array(
			'taxonomy'   => 'o100_location',
			'hide_empty' => false,
		) );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$locations = array();
		foreach ( $terms as $term ) {
			$address     = get_term_meta( $term->term_id, 'exwp_loc_address', true );
			$locations[] = array(
				'id'      => $term->term_id,
				'name'    => $term->name,
				'slug'    => $term->slug,
				'address' => $address,
			);
		}

		return $locations;
	}

	/**
	 * Callback for getting store status
	 * 
	 * @return array
	 */
	public function get_store_status_api() {
		$status = O100_Store_Data::get_open_close_mode();
		
		// Map value to readable status
		// '' => 'disable' (Always Open)
		// 'enable' => 'enable' (Follow Open Hours)
		// 'closed' => 'closed' (Always Closed)
		
		$readable_status = 'disable'; // default
		if ( $status === 'enable' ) {
			$readable_status = 'enable';
		} elseif ( $status === 'closed' ) {
			$readable_status = 'closed';
		}

		return array(
			'status' => $readable_status,
			'raw_value' => $status
		);
	}

	/**
	 * Callback for updating store status
	 * 
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public function update_store_status_api( $request ) {
		$new_status = $request->get_param( 'status' );

		// Validate status
		// Allowed values: 'disable', 'enable', 'closed'
		// Note: The internal value for 'disable' is '' (empty string)
		
		$internal_value = '';
		if ( $new_status === 'enable' ) {
			$internal_value = 'enable';
		} elseif ( $new_status === 'closed' ) {
			$internal_value = 'closed';
		} elseif ( $new_status === 'disable' || $new_status === '' ) {
			$internal_value = '';
		} else {
			return new WP_Error( 'rest_invalid_param', __( 'Invalid status. Allowed values: disable, enable, closed.', 'order100' ), array( 'status' => 400 ) );
		}

		// Update O100 store hours option
		$store_hours = get_option( 'o100_store_hours', array() );
		if ( ! is_array( $store_hours ) ) {
			$store_hours = array();
		}

		// Map: '' = always open (op_cl='on'), 'enable' = follow hours (op_cl='enable'), 'closed' = always closed
		if ( $internal_value === '' ) {
			$store_hours['o100_op_cl'] = 'on';
		} else {
			$store_hours['o100_op_cl'] = $internal_value;
		}
		update_option( 'o100_store_hours', $store_hours );

		return array(
			'success' => true,
			'message' => 'Store status updated successfully.',
			'new_status' => $new_status,
			'internal_value' => $internal_value
		);
	}

	/**
	 * Filter WooCommerce Orders by Location via REST API
	 * 
	 * @param array           $args    WP_Query arguments
	 * @param WP_REST_Request $request Request object
	 * @return array
	 */
	public function filter_orders_by_location( $args, $request ) {
		$options = $this->get_options();
		if ( empty( $options['o100_enable_location_api'] ) ) {
			return $args;
		}

		$location_slug = $request->get_param( 'o100_location' );
		if ( ! empty( $location_slug ) ) {
			$tax_query[] = array(
				'taxonomy' => 'o100_location',
				'field'    => 'slug',
				'terms'    => sanitize_title( $location_slug ),
			);
		}

		if ( ! empty( $location_slug ) ) {
			// Ensure meta_query array exists
			if ( ! isset( $args['meta_query'] ) ) {
				$args['meta_query'] = array();
			}

			$args['meta_query'][] = array(
				'key'     => 'o100_location',
				'value'   => $location_slug,
				'compare' => '=',
			);
		}

		return $args;
	}

	/**
	 * Save delivery instruction to order meta and add note
	 */
	public function save_delivery_instruction_on_create( $order, $data ) {
		if ( ! empty( $_POST['o100_delivery_instruction'] ) ) {
			$val = sanitize_text_field( $_POST['o100_delivery_instruction'] );
			$order->update_meta_data( 'o100_delivery_instruction', $val );
			
			// Also add as a system note
			$note = sprintf( __( 'Delivery Instructions: %s', 'order100' ), $val );
			$order->add_order_note( $note );
		}
	}

	/**
	 * Display instruction on order details (Admin/Thank you page)
	 */
	public function display_instruction_on_order_details( $order ) {
		$instruction = $order->get_meta( 'o100_delivery_instruction' );
		if ( $instruction ) {
			echo '<p><strong>' . __( 'Delivery Instructions', 'order100' ) . ':</strong> ' . esc_html( $instruction ) . '</p>';
		}
	}

	/**
	 * Display instruction in Email
	 */
	public function display_instruction_in_email( $order, $sent_to_admin, $plain_text, $email ) {
		$instruction = $order->get_meta( 'o100_delivery_instruction' );
		if ( $instruction ) {
			if ( $plain_text ) {
				echo "\n" . __( 'Delivery Instructions', 'order100' ) . ": " . $instruction . "\n";
			} else {
				echo '<h2>' . __( 'Delivery Instructions', 'order100' ) . '</h2>';
				echo '<p>' . esc_html( $instruction ) . '</p>';
			}
		}
	}

	/**
	 * Save order note to WooCommerce session via AJAX
	 */
	public function o100_save_order_note() {
		check_ajax_referer( 'o100_order_notes_nonce', 'security' );

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			wp_send_json_error( array( 'reason' => 'no_session' ) );
		}

		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		WC()->session->set( 'o100_persisted_order_note', $note );

		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * Get order note from WooCommerce session via AJAX
	 */
	public function o100_get_order_note() {
		check_ajax_referer( 'o100_order_notes_nonce', 'security' );

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			wp_send_json_success( array( 'note' => '' ) );
		}

		$note = WC()->session->get( 'o100_persisted_order_note' );
		wp_send_json_success( array( 'note' => is_string( $note ) ? $note : '' ) );
	}

	/**
	 * Apply persisted order note to the order on creation
	 *
	 * @param WC_Order $order
	 */
	public function apply_persisted_order_note( $order ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$options = $this->get_options();

		$saved_note = WC()->session->get( 'o100_persisted_order_note' );
		$saved_note = is_string( $saved_note ) ? $saved_note : '';

		// Only set if order has no customer note (avoid overwriting)
		if ( $saved_note !== '' && ! $order->get_customer_note() ) {
			$order->set_customer_note( $saved_note );
		}

		// Clear to prevent leaking into next checkout
		WC()->session->__unset( 'o100_persisted_order_note' );

		// Disable default WooCommerce blocks
		add_filter( 'use_widgets_block_editor', '__return_false' );
	}

	/**
	 * Output dynamic CSS variables for UI preferences
	 */
	public function print_dynamic_css() {
		$opts = get_option( 'o100_ui_prefs', array() );
		
		$warn_bg     = !empty( $opts['o100_color_warn_bg'] ) ? $opts['o100_color_warn_bg'] : '#fff1f2';
		$warn_txt    = !empty( $opts['o100_color_warn_txt'] ) ? $opts['o100_color_warn_txt'] : '#9f1239';
		
		$promo_bg    = !empty( $opts['o100_color_promo_bg'] ) ? $opts['o100_color_promo_bg'] : '#faf5ff';
		$promo_txt   = !empty( $opts['o100_color_promo_txt'] ) ? $opts['o100_color_promo_txt'] : '#9333ea';
		
		$success_bg  = !empty( $opts['o100_color_success_bg'] ) ? $opts['o100_color_success_bg'] : '#f0fdf4';
		$success_txt = !empty( $opts['o100_color_success_txt'] ) ? $opts['o100_color_success_txt'] : '#15803d';
		
		$info_bg     = !empty( $opts['o100_color_info_bg'] ) ? $opts['o100_color_info_bg'] : '#eff6ff';
		$info_txt    = !empty( $opts['o100_color_info_txt'] ) ? $opts['o100_color_info_txt'] : '#1e40af';

		echo '<style>
			:root {
				--o100-notice-warn-bg: ' . esc_attr($warn_bg) . ';
				--o100-notice-warn-txt: ' . esc_attr($warn_txt) . ';
				--o100-notice-promo-bg: ' . esc_attr($promo_bg) . ';
				--o100-notice-promo-txt: ' . esc_attr($promo_txt) . ';
				--o100-notice-success-bg: ' . esc_attr($success_bg) . ';
				--o100-notice-success-txt: ' . esc_attr($success_txt) . ';
				--o100-notice-info-bg: ' . esc_attr($info_bg) . ';
				--o100-notice-info-txt: ' . esc_attr($info_txt) . ';
			}
			
			.o100-notice-warning { background-color: var(--o100-notice-warn-bg) !important; color: var(--o100-notice-warn-txt) !important; border-color: var(--o100-notice-warn-txt) !important; }
			.o100-notice-warning .dashicons, .o100-notice-warning svg { color: var(--o100-notice-warn-txt) !important; fill: var(--o100-notice-warn-txt) !important; }
			
			.o100-notice-promotion { background-color: var(--o100-notice-promo-bg) !important; color: var(--o100-notice-promo-txt) !important; border-color: var(--o100-notice-promo-txt) !important; }
			.o100-notice-promotion .dashicons, .o100-notice-promotion svg { color: var(--o100-notice-promo-txt) !important; fill: var(--o100-notice-promo-txt) !important; }
			
			.o100-notice-success { background-color: var(--o100-notice-success-bg) !important; color: var(--o100-notice-success-txt) !important; border-color: var(--o100-notice-success-txt) !important; }
			.o100-notice-success .dashicons, .o100-notice-success svg { color: var(--o100-notice-success-txt) !important; fill: var(--o100-notice-success-txt) !important; }
			
			.o100-notice-info { background-color: var(--o100-notice-info-bg) !important; color: var(--o100-notice-info-txt) !important; border-color: var(--o100-notice-info-txt) !important; }
			.o100-notice-info .dashicons, .o100-notice-info svg { color: var(--o100-notice-info-txt) !important; fill: var(--o100-notice-info-txt) !important; }
		</style>';
	}

	/**
	 * Hook for the custom entry modal
	 */
	public function maybe_filter_tip_form() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( $this->is_tip_allowed_for_current_method() ) {
			$options = $this->get_options();
			$pos_raw = !empty($options['o100_tip_pos']) ? $options['o100_tip_pos'] : 'before_checkout';
			$pos_map = array(
				'before_checkout' => 'woocommerce_before_checkout_form',
				'after_customer'  => 'woocommerce_checkout_after_customer_details',
				'after_review'    => 'woocommerce_checkout_order_review',
			);
			$pos_of_tip = isset($pos_map[$pos_raw]) ? $pos_map[$pos_raw] : 'woocommerce_before_checkout_form';
			
			// We remove legacy hook if it exists and add our native one

			add_action( $pos_of_tip, array( $this, 'render_tip_form_html' ), 15 );
			return;
		}

		// Tip not allowed — no action needed
		return;
	}

	/**
	 * Natively render the tip form on checkout
	 */
	public function render_tip_form_html() {
		$options = $this->get_options();

		// Check if tip is allowed for current order method
		if ( ! $this->is_tip_allowed_for_current_method() ) {
			return;
		}
		
		// Read tip values from O100 settings
		$title   = !empty($options['o100_tip_title'])  ? $options['o100_tip_title']  : esc_html__('Help keep our team thriving during challenging time', 'order100');
		$addlb   = !empty($options['o100_tip_btn'])    ? $options['o100_tip_btn']    : esc_html__('Show Your Appreciation', 'order100');
		$remlb   = !empty($options['o100_tip_rmbtn'])  ? $options['o100_tip_rmbtn']  : esc_html__('Not Satisfied', 'order100');
		$tvalues = !empty($options['o100_tip_val'])    ? $options['o100_tip_val']    : '10,15,20';
		$tvl_type= !empty($options['o100_tip_type'])   ? $options['o100_tip_type']   : 'percent';


		echo '<div class="exwf-tip-form">';
		if ( $title !== 'off' ) {
			echo '<div class="exwf-tip-title">' . esc_html($title) . '</div>';
		}
		
		$currency_symbol = get_woocommerce_currency_symbol();
		
		if ( $tvalues !== '' ) {
			$tvalues_arr = explode(",", str_replace(' ', '', $tvalues));
			$user_tip = WC()->session->get( '_user_tip_fee' );
			
			foreach ( $tvalues_arr as $tvalue ) {
				if ( is_numeric($tvalue) && $tvalue > 0 ) {
					$tvalue_dspl = $tvl_type === 'percent' ? $tvalue . '%' : strip_tags(wc_price($tvalue));
					$active_class = ($user_tip != '' && $tvalue == $user_tip) ? 'exwf-actip' : '';
					
					echo '<input type="button" class="exwf-tfixed ' . esc_attr($active_class) . '" name="exwf-tip-fixed" value="' . esc_attr($tvalue_dspl) . '" data-value="' . esc_attr($tvalue) . '" data-type="' . esc_attr($tvl_type) . '">';
				}
			}
		}
		
		echo '<input type="number" name="exwf-tip" placeholder="(' . esc_attr($currency_symbol) . ')">';
		echo '<div style="display: flex; gap: 10px; margin-top: 10px;">';
		echo '<input type="button" name="exwf-add-tip" value="' . esc_attr($addlb) . '" style="margin: 0;">';
		echo '<input type="button" name="exwf-remove-tip" value="' . esc_attr($remlb) . '" style="margin: 0;">';
		echo '</div>';
		echo '<div class="exwf-tip-error">' . esc_html__('Please enter a valid number', 'order100') . '</div>';
		echo '</div>';
	}

	/**
	 * Filter tip fee calculation
	 */
	public function filter_tip_fee_calculation() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! $this->is_tip_allowed_for_current_method() ) {
			return;
		}
	}

	/**
	 * Check if tips are allowed for the current order method
	 * 
	 * @return boolean
	 */
	private function is_tip_allowed_for_current_method() {
		$options = $this->get_options();
		

		// If the tip control hasn't been initialized (saved once), default to enabled
		if ( empty( $options['o100_tip_control_initialized'] ) ) {
			return true;
		}

		$user_odmethod = 'delivery'; 
		if ( isset( WC()->session ) ) {
			$method = WC()->session->get( '_o100_order_method' );
			if ( $method ) {
				$user_odmethod = $method;
			}
		}

		// Check settings for each method
		switch ( $user_odmethod ) {
			case 'delivery':
				return ! empty( $options['o100_tip_delivery_enable'] );
			case 'takeaway':
			case 'pickup':
				return ! empty( $options['o100_tip_pickup_enable'] );
			case 'dinein':
			case 'dine_in': // Handle both variations
				return false; // Dine-in tipping is permanently disabled
		}

		return true;
	}

	/**
	 * Maybe override the timeslot AJAX handler
	 */
	public function maybe_override_timeslot_ajax() {
		$options = $this->get_options();
		// If option is not set (new install), we treat it as enabled by default as requested
		$addon_enabled = true;
		$filter_enabled = isset( $options['o100_filter_closed_timeslots'] ) ? $options['o100_filter_closed_timeslots'] === 'on' : true;

		if ( ! $addon_enabled || ! $filter_enabled ) {
			return;
		}

		add_action( 'wp_ajax_exwf_time_delivery_slots', array( $this, 'custom_ajax_exwf_time_delivery_slots' ), 1 );
		add_action( 'wp_ajax_nopriv_exwf_time_delivery_slots', array( $this, 'custom_ajax_exwf_time_delivery_slots' ), 1 );
	}

	/**
	 * Custom AJAX handler for timeslots to filter by closed dates
	 */
	public function custom_ajax_exwf_time_delivery_slots() {
		$log_file = WP_CONTENT_DIR . '/o100-ajax-debug.log';
		$log = "===== NEW REQUEST =====\n";
		$log .= "POST Data: " . print_r($_POST, true) . "\n";
		if ( isset( WC()->session ) ) {
			$log .= "Session Method: " . WC()->session->get( '_o100_order_method' ) . "\n";
		}
		
		$data = array();
		$data['o100_date_deli'] = isset( $_POST['date'] ) && $_POST['date'] != '' && is_numeric( $_POST['date'] ) ? $_POST['date'] : current_time( 'timestamp' );
		$log .= "Determined Date: " . $data['o100_date_deli'] . "\n";
		
		if ( isset( WC()->session ) ) {
			WC()->session->set( '_user_deli_date', $data['o100_date_deli'] );
			if ( is_numeric( $data['o100_date_deli'] ) ) {
					WC()->session->set( '_menudate', date( 'Y-m-d', $data['o100_date_deli'] ) );
				} else {
					WC()->session->set( '_menudate', $data['o100_date_deli'] );
				}
		}

		$adv_timesl = O100_Store_Data::get_advanced_timeslots();
		$html_timesl = '';
		$def_timesl = array();
		$cure_time = O100_Store_Data::get_current_time();
		
		$method_for_lead = isset( WC()->session ) ? WC()->session->get( '_o100_order_method' ) : 'delivery';
		$date_before = O100_Store_Data::get_lead_time( $method_for_lead );
		if ( is_numeric( $date_before ) ) {
			$cure_time = strtotime( "+$date_before minutes", $cure_time );
		} else if ( is_numeric( str_replace( "m", "", $date_before ) ) ) {
			$cure_time = $cure_time + str_replace( "m", "", $date_before ) * 60;
		}

		// $cure_time is aligned to site timezone via current_time('timestamp').
		// Use ' UTC' to force strtotime to treat it as UTC.
		$comparison_midnight = strtotime( gmdate( 'Y-m-d', (int) $data['o100_date_deli'] ) . ' UTC' );

		$disable_sl = ''; // Timeslot disabling no longer used natively
		$user_log = isset( $_POST['loc'] ) ? $_POST['loc'] : '';
		
		$method = 'delivery';
		if ( isset( WC()->session ) ) {
			$method = WC()->session->get( '_o100_order_method' );
			$method = $method != '' ? $method : 'delivery';
		}

		$hide_unavail_slot = 'yes'; // Always hide unavailable slots
		$user_time = isset( WC()->session ) ? WC()->session->get( '_user_deli_time' ) : '';

		// Get closed dates for filtering
		$closed_dates = O100_Store_Data::get_holidays();

		if ( is_array( $def_timesl ) && ! empty( $def_timesl ) ) {
			$html_timesl .= '<select name="o100_time_deli" id="o100_time_deli" class="select " data-time="' . esc_attr( json_encode( $def_timesl ) ) . '" data-crtime="' . esc_attr( $cure_time ) . '" data-userslt="' . esc_attr( $user_time ) . '" data-date="' . esc_attr( strtotime( date( 'Y-m-d', $cure_time ) ) ) . '" data-placeholder="">';
			$html_timesls = '';
			foreach ( $def_timesl as $time_option ) {
				if ( $disable_sl == 'yes' && isset( $time_option['disable-slot'] ) && $time_option['disable-slot'] == '1' ) {
					continue;
				}

				$r_time = '';
				if ( ! empty( $time_option['start-time'] ) && ! empty( $time_option['end-time'] ) ) {
					$r_time = $time_option['start-time'] . ' - ' . $time_option['end-time'];
				} else if ( ! empty( $time_option['start-time'] ) ) {
					$r_time = $time_option['start-time'];
				}
				$name = isset( $time_option['name-ts'] ) && $time_option['name-ts'] != '' ? $time_option['name-ts'] : $r_time;
				$disable = '';

				// Check original disable logic
				$_time_base = $time_option['start-time'];
				if ( $_time_base != '' ) {
					$_timeck = explode( ':', $_time_base );
					$_timeck_sec = $_timeck[1] * 60 + $_timeck[0] * 3600;
					if ( ( $comparison_midnight + $_timeck_sec ) < $cure_time ) {
						$disable = 'disabled="disabled"';
					}
				}

				// ADDED: Check "Closed from date to date"
				if ( $disable == '' && ! empty( $time_option['start-time'] ) && is_array( $closed_dates ) && ! empty( $closed_dates ) ) {
					$_time_pts = explode( ':', $time_option['start-time'] );
					$_time_seconds = (int)$_time_pts[0] * 3600 + (int)$_time_pts[1] * 60;
					$slot_start_timestamp = $comparison_midnight + $_time_seconds;
					foreach ( $closed_dates as $closed_date ) {
						$cls_start = isset( $closed_date['opcl_start'] ) ? $closed_date['opcl_start'] : '';
						$cls_end = isset( $closed_date['opcl_end'] ) ? $closed_date['opcl_end'] : '';
						if ( $cls_start != '' && $slot_start_timestamp >= $cls_start && $cls_end != '' && $slot_start_timestamp <= $cls_end ) {
							$disable = 'disabled="disabled"';
							break;
						}
					}
				}

				$maxsl = isset( $time_option['max-odts'] ) && is_numeric( $time_option['max-odts'] ) ? $time_option['max-odts'] : '';
				if ( $disable != 'disabled="disabled"' && $maxsl != '' && class_exists( 'O100_Order_Manager' ) ) {
					$total_rs = O100_Order_Manager::get_timeslot_order_count( $data['o100_date_deli'], $name, $method );
					if ( $total_rs >= $maxsl ) {
						$disable = 'disabled="disabled"';
					}
				}

				if ( $hide_unavail_slot == 'yes' && $disable == 'disabled="disabled"' && $maxsl != '-1' ) {
					continue;
				}

				$html_timesls .= '<option value="' . esc_attr( $name ) . '" ' . $disable . ' ' . ( $maxsl == '-1' ? 'selected="true"' : '' ) . '>' . esc_html( $name ) . '</option>';
			}

			if ( $html_timesls == '' ) {
				$html_timesl .= '<option value="">' . esc_html__( 'No time slot available for selection', 'woocommerce-food' ) . '</option>';
			} else {
				$html_timesl .= $html_timesls;
			}
			$html_timesl .= '</select>';
		} else {
			$html_timesl = '<input type="text" class="input-text " name="o100_time_deli" id="o100_time_deli" placeholder="" value="">';
		}

		if ( is_array( $adv_timesl ) && ! empty( $adv_timesl ) ) {
			// Use gmdate to extract the day of the week since $data['o100_date_deli'] is intrinsically a UTC midnight timestamp
			$day_ofd = gmdate( 'D', (int) $data['o100_date_deli'] );

			foreach ( $adv_timesl as $it_timesl ) {
				$tsl_log = isset( $it_timesl['times_loc'] ) ? $it_timesl['times_loc'] : '';
				if ( isset( $it_timesl['repeat_' . $day_ofd] ) && $it_timesl['repeat_' . $day_ofd] == 'on' && 
					( ! isset( $it_timesl['deli_method'] ) || $it_timesl['deli_method'] == '' || $it_timesl['deli_method'] == $method ) && 
					( $tsl_log == '' || ( is_array( $tsl_log ) && ! empty( $tsl_log ) && in_array( $user_log, $tsl_log ) ) ) ) {
					
					$tsl_method = isset( $it_timesl['deli_method'] ) ? $it_timesl['deli_method'] : '';
					if ( isset( $it_timesl['o100_deli_time'] ) && is_array( $it_timesl['o100_deli_time'] ) ) {
						$def_timesl = $it_timesl['o100_deli_time'];
					}
					$html_timesl = '';
					$html_timesl .= '<select name="o100_time_deli" id="o100_time_deli" class="select " data-time="' . esc_attr( json_encode( $def_timesl ) ) . '" data-crtime="' . esc_attr( $cure_time ) . '" data-userslt="' . esc_attr( $user_time ) . '" data-date="' . esc_attr( strtotime( gmdate( 'Y-m-d', $cure_time ) . ' UTC' ) ) . '" data-placeholder="">';
					
					if ( isset( $it_timesl['o100_deli_time'] ) && is_array( $it_timesl['o100_deli_time'] ) ) {
						$html_timesls = '';
						foreach ( $it_timesl['o100_deli_time'] as $time_option ) {
							if ( $disable_sl == 'yes' && isset( $time_option['disable-slot'] ) && $time_option['disable-slot'] == '1' ) {
								continue;
							}

							$r_time = '';
							if ( ! empty( $time_option['start-time'] ) && ! empty( $time_option['end-time'] ) ) {
								$r_time = $time_option['start-time'] . ' - ' . $time_option['end-time'];
							} else if ( ! empty( $time_option['start-time'] ) ) {
								$r_time = $time_option['start-time'];
							}
							$name = isset( $time_option['name-ts'] ) && $time_option['name-ts'] != '' ? $time_option['name-ts'] : $r_time;
							$disable = '';

							$_time_base = $time_option['start-time'];
							if ( $_time_base != '' ) {
								// Check against end-time to keep the slot open until the very end of the window
								$_time_end = ! empty( $time_option['end-time'] ) ? $time_option['end-time'] : $time_option['start-time'];
								$_timeck = explode( ':', $_time_end );
								$_timeck_sec = $_timeck[1] * 60 + $_timeck[0] * 3600;
								if ( $time_option['start-time'] != '' && ( $comparison_midnight + $_timeck_sec ) < $cure_time ) {
									$disable = 'disabled="disabled"';
								}
							}

							// ADDED: Check "Closed from date to date"
							if ( $disable == '' && ! empty( $time_option['start-time'] ) && is_array( $closed_dates ) && ! empty( $closed_dates ) ) {
								$_time_pts = explode( ':', $time_option['start-time'] );
								$_time_seconds = (int)$_time_pts[0] * 3600 + (int)$_time_pts[1] * 60;
								$slot_start_timestamp = $comparison_midnight + $_time_seconds;
								foreach ( $closed_dates as $closed_date ) {
									$cls_start = isset( $closed_date['opcl_start'] ) ? $closed_date['opcl_start'] : '';
									$cls_end = isset( $closed_date['opcl_end'] ) ? $closed_date['opcl_end'] : '';
									if ( $cls_start != '' && $slot_start_timestamp >= $cls_start && $cls_end != '' && $slot_start_timestamp <= $cls_end ) {
										$disable = 'disabled="disabled"';
										break;
									}
								}
							}

							$maxsl = isset( $time_option['max-odts'] ) && is_numeric( $time_option['max-odts'] ) ? $time_option['max-odts'] : '';
							if ( $disable != 'disabled="disabled"' && $maxsl != '' && class_exists( 'O100_Order_Manager' ) ) {
								$total_rs = O100_Order_Manager::get_timeslot_order_count( $data['o100_date_deli'], $name, $method );
								if ( $total_rs >= $maxsl ) {
									$disable = 'disabled="disabled"';
								}
							}

							if ( $hide_unavail_slot == 'yes' && $disable == 'disabled="disabled"' && $maxsl != '-1' ) {
								continue;
							}
							$html_timesls .= '<option value="' . esc_attr( $name ) . '" ' . $disable . ' ' . ( $maxsl == '-1' ? 'selected="true"' : '' ) . '>' . esc_html( $name ) . '</option>';
						}
						if ( $html_timesls == '' ) {
							$html_timesl .= '<option value="no_slot" disabled="disabled">' . esc_html__( 'No time slot available for selection', 'order100' ) . '</option>';
						} else {
							$html_timesl .= $html_timesls;
						}
					} else {
						$html_timesl .= '<option value="no_slot" disabled="disabled">' . esc_html__( 'No time slot available for selection', 'order100' ) . '</option>';
					}
					$html_timesl .= '</select>';
					break;
				}
			}
		}

		$output = array( 'html_timesl' => $html_timesl, 'data_time' => json_encode( $def_timesl ) );
		
		$log .= "Output HTML: " . $html_timesl . "\n";
		file_put_contents( $log_file, $log, FILE_APPEND );
		
		if ( ob_get_length() ) {
			ob_clean();
		}
		echo str_replace( '\/', '/', json_encode( $output ) );
		die;
	}

	/**
	 * Guest Auto-Registration & Login
	 * Automatically creates a WooCommerce customer account for guest orders
	 * and logs the user in immediately.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Posted checkout data.
	 * @param WC_Order $order       Order object.
	 */
	public function guest_auto_register_and_login( $order_id, $posted_data, $order ) {
		// Check if feature is enabled
		$options = $this->get_options();
		if ( empty( $options['o100_enable_guest_auto_reg'] ) ) {
			error_log( 'WFA Guest Reg: Feature is disabled in settings, skipping.' );
			return;
		}

		error_log( 'WFA Guest Reg: Hook fired for order #' . $order_id );

		// Basic validation
		if ( ! $order_id || ! $order instanceof WC_Order ) {
			error_log( 'WFA Guest Reg: Invalid order, skipping.' );
			return;
		}

		// If order already has a user, skip (prevent duplicate)
		if ( $order->get_user_id() ) {
			error_log( 'WFA Guest Reg: Order already has user ID ' . $order->get_user_id() . ', skipping.' );
			return;
		}

		// Get billing email
		$email = $order->get_billing_email();
		if ( ! $email || ! is_email( $email ) ) {
			error_log( 'WFA Guest Reg: No valid billing email, skipping.' );
			return;
		}

		error_log( 'WFA Guest Reg: Processing for email: ' . $email );

		// Check if user already exists
		$user = get_user_by( 'email', $email );
		$is_new_user = false;

		// If no user exists, create one
		if ( ! $user ) {
			error_log( 'WFA Guest Reg: No existing user, creating new customer...' );
			$password = wp_generate_password( 12, true );

			$user_id = wc_create_new_customer(
				$email,    // email
				$email,    // username (use email directly)
				$password
			);

			if ( is_wp_error( $user_id ) ) {
				error_log( 'WFA Guest Reg: wc_create_new_customer failed: ' . $user_id->get_error_message() );
				return;
			}

			error_log( 'WFA Guest Reg: Created user ID ' . $user_id );

			// Bind order to new user
			$order->set_customer_id( $user_id );
			$order->save();

			$user = get_user_by( 'id', $user_id );
			$is_new_user = true;
		} else {
			error_log( 'WFA Guest Reg: Existing user found (ID ' . $user->ID . '), linking order only.' );
			// Link existing user to this order but DO NOT log them in automatically for security
			$order->set_customer_id( $user->ID );
			$order->save();
		}

		if ( ! $user || ! $user->ID ) {
			error_log( 'WFA Guest Reg: User object invalid after creation/lookup, aborting.' );
			return;
		}

		/**
		 * Force WordPress Auto Login (ONLY FOR NEWLY CREATED USERS)
		 */
		if ( $is_new_user ) {
			error_log( 'WFA Guest Reg: Forcing auto-login for NEW user ID ' . $user->ID );
			wp_clear_auth_cookie();
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, true );
			do_action( 'wp_login', $user->user_login, $user );

			/**
			 * Force WooCommerce Session Recognition
			 */
			if ( function_exists( 'WC' ) && WC()->session ) {
				WC()->session->set( 'customer_id', $user->ID );
			}
		}

		error_log( 'WFA Guest Reg: Processing complete for order #' . $order_id );
	}

	/**
	 * Render the food labels HTML for a product.
	 *
	 * @param int $product_id The ID of the product.
	 * @return string HTML output for the labels.
	 */
	public static function get_food_labels_html( $product_id ) {
		$product_labels = get_post_meta( $product_id, 'o100_product_labels', true );
		if ( empty( $product_labels ) || ! is_array( $product_labels ) ) {
			return '';
		}

		$settings = get_option( 'o100_misc', array() );
		$global_labels = isset( $settings['o100_global_food_labels'] ) ? $settings['o100_global_food_labels'] : array();

		if ( empty( $global_labels ) ) {
			return '';
		}

		$html = '<div class="o100-food-labels-container">';
		
		foreach ( $product_labels as $label_index ) {
			if ( isset( $global_labels[ $label_index ] ) ) {
				$label_data = $global_labels[ $label_index ];
				$name    = isset( $label_data['name'] ) ? $label_data['name'] : '';
				$bgcolor = isset( $label_data['bgcolor'] ) && $label_data['bgcolor'] !== '' ? $label_data['bgcolor'] : '#ffffff';
				$icon    = isset( $label_data['icon'] ) ? $label_data['icon'] : '';

				if ( empty( $icon ) ) {
					continue; // Icon-only mode: skip labels without an icon
				}

				$html .= '<span class="o100-food-label-item" title="' . esc_attr( $name ) . '" style="background-color: ' . esc_attr( $bgcolor ) . ';">';
				$html .= '<img class="o100-label-icon" src="' . esc_url( $icon ) . '" alt="' . esc_attr( $name ) . '">';
				$html .= '</span>';
			}
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Save frontend-calculated distance to WooCommerce session
	 */
	public function ajax_update_user_distance() {
		check_ajax_referer( 'o100_gmap_nonce', 'nonce' );

		if ( isset( $_POST['distance'] ) && function_exists( 'WC' ) && isset( WC()->session ) ) {
			$distance = floatval( $_POST['distance'] );
			WC()->session->set( '_user_distance', $distance );
			wp_send_json_success( array( 'distance' => $distance ) );
		}

		wp_send_json_error( array( 'message' => 'Failed to update distance' ) );
	}

}


// TS: 20260219215439

// TS: 20260313122550
