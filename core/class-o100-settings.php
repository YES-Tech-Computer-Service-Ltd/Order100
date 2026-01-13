<?php
/**
 * Settings Class
 */
class O100_Settings {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'cmb2_admin_init', array( $this, 'register_settings' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 99 );

		// AJAX handlers for Locations Manager
		add_action( 'wp_ajax_o100_save_store_profile', array( $this, 'ajax_save_store_profile' ) );
		add_action( 'wp_ajax_o100_save_checkout_ext', array( $this, 'ajax_save_checkout_ext' ) );
		add_action( 'wp_ajax_o100_save_delivery', array( $this, 'ajax_save_delivery' ) );
		add_action( 'wp_ajax_o100_save_pickup', array( $this, 'ajax_save_pickup' ) );
		add_action( 'wp_ajax_o100_toggle_locations', array( $this, 'ajax_toggle_locations' ) );
		add_action( 'wp_ajax_o100_toggle_module', array( $this, 'ajax_toggle_module' ) );
		add_action( 'wp_ajax_o100_save_portal_settings', array( $this, 'ajax_save_portal_settings' ) );

		// Sanitize checkbox values to preserve 'off' state in DB.
		add_filter( 'cmb2_sanitize_checkbox', array( $this, 'sanitize_checkbox_preserve' ), 10, 5 );

		// Auto-disable location feature if no branches are configured
		add_action( 'admin_init', array( $this, 'sanitize_location_state' ) );
		
		// Fallback Override Schedule fields to Global Schedule fields if empty
		add_filter( 'cmb2_override_meta_value', array( $this, 'fallback_override_fields_to_global' ), 10, 4 );
		
		// Global Toast Notifications
		add_action( 'admin_footer', array( $this, 'render_global_toast_and_saving' ) );
		
		// Load Address Autocomplete
		add_action( 'admin_footer', array( $this, 'init_address_autocomplete' ) );
		
		// Generate timeslots on save
		add_action( 'cmb2_save_options-page_fields_o100_store_hours', array( $this, 'generate_timeslots_on_save' ), 10, 2 );
		add_action( 'cmb2_save_options-page_fields_o100_reservation', array( $this, 'save_reservation_rooms' ), 10, 2 );
		add_action( 'cmb2_save_options-page_fields_o100_reservation', array( $this, 'save_reservation_form_fields' ), 11, 2 );
		
		// Force Legacy Option for Location Hours
		
		
		// Register custom CMB2 field types
		add_action( 'cmb2_render_o100_product_search', array( $this, 'render_product_search_field' ), 10, 5 );
		add_action( 'cmb2_render_o100_category_search', array( $this, 'render_category_search_field' ), 10, 5 );
		
		// Add AJAX endpoints for our custom UI
		add_action( 'wp_ajax_o100_mcd_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_o100_mcd_search_categories', array( $this, 'ajax_search_categories' ) );
	}

	

	/**
	 * Sanitize checkbox values to preserve 'off' state in DB.
	 * Without this, CMB2 deletes unchecked checkbox keys entirely.
	 * Scoped to our addon's CMB2 box only.
	 *
	 * @param mixed  $override_value Sanitized value (null = use default).
	 * @param mixed  $value          Raw value from form.
	 * @param int    $object_id      Object ID.
	 * @param array  $args           Field arguments.
	 * @param object $sanitize_obj   CMB2_Sanitize instance.
	 * @return mixed 'on', 'off', or null (pass-through for other boxes).
	 */
	public function sanitize_checkbox_preserve( $override_value, $value, $object_id, $args, $sanitize_obj ) {
		// Only intercept checkboxes belonging to our addon's option box
		if ( isset( $sanitize_obj->field->cmb_id ) && $sanitize_obj->field->cmb_id === 'o100_options' ) {
			return ( $value === 'on' ) ? 'on' : 'off';
		}
		return $override_value; // null = let CMB2 handle normally
	}

	/**
	 * Auto-disable the location feature if no branches exist.
	 * This ensures the configuration page reverts to inactive if the user exits without configuring any locations.
	 */
	public function sanitize_location_state() {
		$opts = get_option('o100_locations', array());
		if ( ! empty($opts['o100_enable_loc']) && $opts['o100_enable_loc'] === 'on' ) {
			$terms = get_terms( array( 'taxonomy' => 'exwoofood_loc', 'hide_empty' => false, 'parent' => 0 ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				$opts['o100_enable_loc'] = '';
				update_option('o100_locations', $opts);
			}
		}
	}

	/**
	 * Fallback Override fields to Global if they are empty
	 * This ensures that when the user turns on the Override toggle, the inputs
	 * are pre-filled with the Global Schedule data automatically.
	 */
	public function fallback_override_fields_to_global( $data, $object_id, $args, $field ) {
		// Only run for o100_store_hours (this tab's option key)
		if ( 'o100_store_hours' !== $object_id ) {
			return $data;
		}

		$id = $field->id();
		
		$fallbacks = array(
			'o100_delivery_interval' => 'o100_global_interval',
			'o100_pickup_interval'   => 'o100_global_interval',
			'o100_delivery_max_order'=> 'o100_global_max_order',
			'o100_pickup_max_order'  => 'o100_global_max_order',
		);
		$days = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');
		foreach ($days as $day) {
			$fallbacks['o100_delivery_' . $day . '_opcl_time'] = 'o100_' . $day . '_opcl_time';
			$fallbacks['o100_pickup_' . $day . '_opcl_time'] = 'o100_' . $day . '_opcl_time';
		}

		if ( array_key_exists( $id, $fallbacks ) ) {
			// Check if the current value is completely empty in the DB.
			$options = get_option( 'o100_store_hours', array() );
			
			// Determine if this is a Delivery or Pickup field to check its respective master switch
			$is_delivery = strpos( $id, 'o100_delivery_' ) === 0;
			$master_switch_key = $is_delivery ? 'o100_delivery_override_schedule' : 'o100_pickup_override_schedule';
			$is_switch_on = isset( $options[ $master_switch_key ] ) && $options[ $master_switch_key ] === 'on';

			$current_val = isset( $options[ $id ] ) ? $options[ $id ] : '';
			
			$is_empty = false;
			
			// If the master switch is OFF in the database, we FORCE the field to be treated as empty.
			// This is critical: it ensures that while the Override section is hidden, its HTML is 
			// secretly pre-populated with a perfect 1:1 clone of the Global Schedule!
			// When the user toggles the switch ON in the browser, they will instantly see the Global data.
			if ( ! $is_switch_on ) {
				$is_empty = true;
			} else {
				// Switch is ON, so we respect user data unless it's genuinely empty
				if ( is_array( $current_val ) ) {
					// The sanitize function strips the 'status' key and saves an indexed array of slots.
					// If the array is completely empty, it means no slots were saved.
					if ( empty( $current_val ) ) {
						$is_empty = true;
					}
				} else {
					// Catch strings like '' or false. 
					// Note: If they explicitly closed it, it saves as the string 'closed', which is NOT empty!
					if ( empty( $current_val ) ) {
						$is_empty = true;
					}
				}
			}

			if ( $is_empty ) {
				$global_key = $fallbacks[ $id ];
				$global_val = isset( $options[ $global_key ] ) ? $options[ $global_key ] : '';
				if ( $global_val !== '' && $global_val !== null ) {
					return $global_val; // Overrides the field output!
				}
			}
		}

		return $data;
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		// Load on all Order100 sub-menu pages (order100_page_o100-*)
		// and on the top-level Order100 dashboard (toplevel_page_order100)
		$is_order100_page = (
			false !== strpos( $hook, 'order100' ) ||
			false !== strpos( $hook, 'o100' )
		);

		// Legacy: also load on old ExFood parent page if our tab is active
		if ( ! $is_order100_page && false !== strpos( $hook, 'exwoofood_options' ) ) {
			$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : '';
			if ( $active_tab === 'o100_options' ) {
				$is_order100_page = true;
			}
		}

		if ( ! $is_order100_page ) {
			return;
		}

		wp_enqueue_style( 'o100-admin-css', O100_URL . 'assets/css/o100-admin.css', array(), time() );
		wp_enqueue_style( 'o100-fluent-admin-css', O100_URL . 'assets/css/o100-fluent-admin.css', array(), time() );
		wp_enqueue_script( 'o100-admin-js', O100_URL . 'assets/js/o100-admin.js', array( 'jquery' ), time(), true );

		wp_enqueue_style( 'flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13' );
		wp_enqueue_script( 'flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'), '4.6.13', true );

		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'select2' );
		} elseif ( wp_script_is( 'select2', 'registered' ) ) {
			wp_enqueue_script( 'select2' );
			wp_enqueue_style( 'select2' );
		}
		
		wp_enqueue_script( 'wc-enhanced-select' ); // Enables native WooCommerce ajax product search
		add_action('admin_footer', function() {
			?>
			<style>
			/* === Store Features: repeat rows === */
			.o100-fluent-content .cmb-repeat-row::before,
			.o100-fluent-content .cmb-repeat-row::after {
				content: none !important;
				display: none !important;
			}
			.o100-fluent-content .cmb-repeat-row:not(.empty-row):not(.hidden) {
				position: relative !important;
				display: block !important;
				margin-bottom: 12px !important;
				padding: 12px 60px 12px 12px !important;
				background: #f8fafc !important;
				border: 1px solid #e2e8f0 !important;
				border-radius: 8px !important;
				float: none !important;
				clear: both !important;
			}
			.o100-fluent-content .cmb-repeat-row:not(.empty-row):not(.hidden):hover {
				border-color: #cbd5e1 !important;
			}
			.o100-fluent-content .cmb-repeat-row:not(.empty-row):not(.hidden) > .cmb-td:first-child {
				display: block !important;
				float: none !important;
				width: 100% !important;
				padding: 0 !important;
			}
			.o100-fluent-content .cmb-repeat-row:not(.empty-row):not(.hidden) > .cmb-td:first-child textarea,
			.o100-fluent-content .cmb-repeat-row:not(.empty-row):not(.hidden) > .cmb-td:first-child input[type="text"] {
				width: 100% !important;
				max-width: none !important;
				min-height: 56px !important;
				resize: vertical !important;
				box-sizing: border-box !important;
			}
			.o100-inline-field-sm {
				flex: 0 0 48% !important;
				max-width: 48% !important;
				min-width: 0 !important;
				border-bottom: none !important;
				padding-bottom: 0 !important;
			}
			.o100-inline-field-sm > .cmb-td {
				display: flex !important;
				flex-wrap: wrap !important;
				align-items: stretch !important;
			}
			.o100-inline-field-sm input.regular-text,
			.o100-inline-field-sm input[type="text"] {
				flex: 1 1 0% !important;
				min-width: 60px !important;
				max-width: 150px !important;
				width: 100% !important;
				border-radius: 6px 0 0 6px !important;
				border-right: none !important;
				margin: 0 !important;
				order: 1 !important;
			}
			.o100-inline-field-sm .o100-input-suffix {
				display: inline-flex !important;
				align-items: center !important;
				justify-content: center !important;
				background: #f8fafc !important;
				border: 1px solid #cbd5e1 !important;
				border-left: none !important;
				padding: 0 15px !important;
				height: 40px !important; /* to match input height */
				color: #64748b !important;
				font-size: 13px !important;
				border-radius: 0 6px 6px 0 !important;
				box-sizing: border-box !important;
				margin: 0 !important;
				order: 2 !important;
			}
			.o100-inline-field-sm p.cmb2-metabox-description {
				order: 3 !important;
				width: 100% !important;
				margin-top: 8px !important;
			}
			/* Remove button: absolute top-right corner
			   CMB2 sets display:none on .cmb-remove-row — override with ID selector for max specificity */
			#o100_store_features_repeat .cmb-repeat-row:not(.empty-row):not(.hidden) > .cmb-td.cmb-remove-row,
			.o100-fluent-content .cmb-repeat-table .cmb-repeat-row:not(.empty-row):not(.hidden) > .cmb-td.cmb-remove-row {
				display: block !important;
				position: absolute !important;
				top: 8px !important;
				right: 8px !important;
				float: none !important;
				width: auto !important;
				padding: 0 !important;
			}
			.o100-fluent-content .cmb-repeat-table .cmb-row {
				border-bottom: none !important;
			}

			/* === Logo file upload === */
			.o100-fluent-content .cmb-type-file .cmb-td {
				display: grid !important;
				grid-template-columns: 1fr auto !important;
				gap: 8px !important;
				align-items: center !important;
				float: none !important;
				width: 100% !important;
			}
			.o100-fluent-content .cmb-type-file .cmb-td input.cmb2-upload-file {
				width: 100% !important;
				min-width: 0 !important;
				float: none !important;
				margin-top: 8px !important;
			}
			.o100-fluent-content .cmb-type-file .cmb-td input.cmb2-upload-button {
				float: none !important;
			}
			.o100-fluent-content .cmb-type-file .cmb-td .cmb2-upload-file-id {
				display: none !important;
			}
			.o100-fluent-content .cmb-type-file .cmb-td .cmb2-media-status:empty {
				display: none !important;
			}
			.o100-fluent-content .cmb-type-file .cmb-td .cmb2-media-status {
				grid-column: 1 / -1 !important;
			}
			</style>
			<?php
		}, 99999);

		// Pass required data to JS
		wp_localize_script( 'o100-admin-js', 'o100Settings', array(
			'tabIds'   => array( 'time', 'checkout', 'api', 'nav', 'discount', 'loyalty', 'seo', 'notification' ),
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'seoNonce' => wp_create_nonce( 'o100_seo_nonce' ),
			'loyaltyToggleNonce' => wp_create_nonce( 'o100_toggle_loyalty_nonce' ),
			'adminNonce' => wp_create_nonce( 'o100_admin_nonce' ),
		) );

		// SEO module JS
		wp_enqueue_script( 'o100-seo-js', O100_URL . 'assets/js/o100-seo.js', array( 'jquery' ), O100_VERSION, true );
		wp_localize_script( 'o100-seo-js', 'o100SeoData', array(
			'nonce'   => wp_create_nonce( 'o100_seo_nonce' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		) );
	}

	public function register_settings() {

		$arr_pm = array();
		if( class_exists('WC_Payment_Gateways')) {
			$wc_gateways = new \WC_Payment_Gateways();
			$payment_gateways = $wc_gateways->get_available_payment_gateways();
			if( is_array($payment_gateways) && !empty($payment_gateways) ) {
				foreach( $payment_gateways as $gateway_id => $gateway ) {
					$arr_pm[$gateway_id] = $gateway->get_title();
				}
			}
		}

		// Default fetching function for Store Profile
		$get_default_val = function($key) {
			$val = '';
			if ($key === 'o100_store_name') $val = get_bloginfo('name');
			if ($key === 'o100_store_email') $val = get_bloginfo('admin_email');
			if ($key === 'o100_store_logo') {
				$custom_logo_id = get_theme_mod( 'custom_logo' );
				if ($custom_logo_id) { $image = wp_get_attachment_image_src( $custom_logo_id , 'full' ); if ($image) $val = $image[0]; }
			}
			if ($key === 'o100_store_address') {
				$addr1 = get_option('woocommerce_store_address', '');
				$addr2 = get_option('woocommerce_store_address_2', '');
				$city = get_option('woocommerce_store_city', '');
				$postcode = get_option('woocommerce_store_postcode', '');
				$val = trim($addr1 . ' ' . $addr2 . ' ' . $city . ' ' . $postcode);
			}
			return $val;
		};


		// TAB: Store Profile
		// Store Profile has been migrated to a custom Fluent CRM style UI in `render_fluent_store_profile()`
		// and is saved via AJAX (`ajax_save_store_profile`).
		
		// Emergency Close is managed via the Admin Bar (O100_Emergency_Closure)
		// Removed redundant fields from here.


		// TAB: Store Hours
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_store_hours',
			'title'        => __( 'Schedule', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_store_hours',
			'display_cb'   => '__return_false',
		) );

		// 1. Multi-location warning moved to the VERY TOP
		$loc_opts = get_option( 'o100_locations', array() );
		$is_loc_enabled = !empty( $loc_opts['o100_enable_loc'] ) && $loc_opts['o100_enable_loc'] === 'on';
		if ( $is_loc_enabled ) {
			$cmb->add_field( array(
				'name' => '',
				'desc' => '<div style="background:#f0f9ff; border-left:4px solid #3b82f6; padding:12px 16px; border-radius:4px; color:#1e40af; margin-bottom:15px; font-size:14px;"><strong style="display:block; margin-bottom:4px;">📍 Multi-Location is Enabled</strong>' . esc_html__( 'You are currently configuring the Global Store Hours. You can override these hours for specific locations by visiting the ', 'order100' ) . '<a href="#" onclick="jQuery(\'.nav-tab[href=\\\'#o100_locations\\\']\').click(); return false;" style="font-weight:600; color:#2563eb; text-decoration:underline;">' . esc_html__( 'Locations Tab', 'order100' ) . '</a>' . esc_html__( ' and editing a location. If a location\'s hours are left blank, it will automatically use these global hours.', 'order100' ) . '</div>',
				'id'   => 'o100_open_close_loc_notice',
				'type' => 'title',
			) );
		}

		$cmb->add_field( array(
			'id'   => 'o100_schedule_block1_start',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Set Your Weekly Global Schedule', 'order100') . '</h3><p>' . esc_html__('Configure your restaurant\'s weekly operating hours, including multiple time slots in each day for flexibility.', 'order100') . '</p></div><div class="o100-settings-group-content">'; }
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_global_config_start',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div style="display:flex; gap:20px; align-items:flex-start; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px;">'; }
		) );

		$cmb->add_field( array(
			'name'       => esc_html__( 'Time Intervals', 'order100' ),
			'id'         => 'o100_global_interval',
			'type'       => 'text',
			'attributes' => array( 'placeholder' => 'e.g. 30' ),
			'classes'    => 'o100-inline-field-sm',
			'after'      => '<span class="o100-input-suffix">minutes</span>',
			'desc'       => esc_html__( 'This affects pickup, delivery, and dine-in slots.', 'order100' ),
		) );

		$cmb->add_field( array(
			'name'       => esc_html__( 'Default Max Order', 'order100' ),
			'id'         => 'o100_global_max_order',
			'type'       => 'text',
			'attributes' => array( 'placeholder' => 'e.g. 5' ),
			'classes'    => 'o100-inline-field-sm',
			'after'      => '<span class="o100-input-suffix">orders</span>',
			'desc'       => esc_html__( 'Maximum orders allowed per timeslot globally.', 'order100' ),
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_global_config_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div><h4 style="margin:0 0 15px 0; font-size:15px; color:#0f172a;">' . esc_html__('Weekly Schedule:', 'order100') . '</h4>'; }
		) );

		$cmb->add_field( array( 'name' => esc_html__( 'Mon', 'order100' ), 'id' => 'o100_Mon_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field', ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Tue', 'order100' ), 'id' => 'o100_Tue_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field', ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Wed', 'order100' ), 'id' => 'o100_Wed_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field', ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Thu', 'order100' ), 'id' => 'o100_Thu_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field', ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Fri', 'order100' ), 'id' => 'o100_Fri_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field', ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Sat', 'order100' ), 'id' => 'o100_Sat_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field', ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Sun', 'order100' ), 'id' => 'o100_Sun_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field', ) );

		// The Generated Timeslots (Folded)
		$cmb->add_field( array(
			'name' => '',
			'id'   => 'o100_global_generated_timeslots',
			'type' => 'o100_generated_timeslots',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_block1_end',
			'type' => 'title',
			'render_row_cb' => function() { 
				echo '</div></div>'; // Close card content + card
			}
		) );

		// Block 2: Holidays & Days Off
		$cmb->add_field( array(
			'id'   => 'o100_schedule_block2_start',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Holidays & Days Off', 'order100') . '</h3><p>' . esc_html__('Set your fixed annual holidays (e.g. Christmas, New Year). During these dates, the restaurant will be closed for online orders.', 'order100') . '</p></div><div class="o100-settings-group-content">'; }
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Holidays & Days Off', 'order100' ),
			'id'   => 'o100_enable_holidays',
			'type' => 'checkbox',
		) );
		
		// DUMMY FIELD: Forces CMB2 to enqueue its native datepicker JS & CSS
		$cmb->add_field( array(
			'id'          => 'o100_dummy_date_for_assets',
			'type'        => 'text_date',
			'attributes'  => array( 'style' => 'display:none;' ),
			'row_classes' => 'o100-hidden-dummy',
			'render_row_cb' => function($field_args, $field) { 
				// Render it completely invisibly to avoid layout issues
				echo '<div style="display:none;">';
				$field->render_column();
				echo '</div>';
			}
		) );

		$cmb->add_field( array(
			'id'          => 'o100_holidays_list',
			'name'        => '',
			'type'        => 'o100_holidays',
			'attributes'  => array(
				// Fix CMB2 conditional logic issue by applying it properly
				'data-conditional-id' => 'o100_enable_holidays',
				'data-conditional-value' => 'on',
			),
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_block2_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div>'; }
		) );

		// Block 3: Override for Delivery
		$cmb->add_field( array(
			'id'   => 'o100_schedule_block3_start',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Override Default Schedule for Delivery', 'order100') . '</h3><p>' . esc_html__('If enabled, Delivery will follow below schedule instead of global.', 'order100') . '</p></div><div class="o100-settings-group-content">'; }
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Override Default Schedule', 'order100' ),
			'id'   => 'o100_delivery_override_schedule',
			'type' => 'checkbox',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_delivery_config_start',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div class="o100-delivery-override-content"><div style="display:flex; gap:20px; align-items:flex-end; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px;">'; }
		) );

		$cmb->add_field( array(
			'name'       => esc_html__( 'Time Intervals', 'order100' ),
			'id'         => 'o100_delivery_interval',
			'type'       => 'text',
			'attributes' => array( 'placeholder' => 'e.g. 30' ),
			'classes'    => 'o100-inline-field-sm',
			'after'      => '<span class="o100-input-suffix">minutes</span>',
		) );

		$cmb->add_field( array(
			'name'       => esc_html__( 'Default Max Order', 'order100' ),
			'id'         => 'o100_delivery_max_order',
			'type'       => 'text',
			'attributes' => array( 'placeholder' => 'e.g. 5' ),
			'classes'    => 'o100-inline-field-sm',
			'after'      => '<span class="o100-input-suffix">orders</span>',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_delivery_config_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div><h4 style="margin:0 0 15px 0; font-size:15px; color:#0f172a;">' . esc_html__('Weekly Schedule (Delivery):', 'order100') . '</h4>'; }
		) );

		$cmb->add_field( array( 'name' => esc_html__( 'Mon', 'order100' ), 'id' => 'o100_delivery_Mon_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Tue', 'order100' ), 'id' => 'o100_delivery_Tue_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Wed', 'order100' ), 'id' => 'o100_delivery_Wed_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Thu', 'order100' ), 'id' => 'o100_delivery_Thu_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Fri', 'order100' ), 'id' => 'o100_delivery_Fri_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Sat', 'order100' ), 'id' => 'o100_delivery_Sat_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Sun', 'order100' ), 'id' => 'o100_delivery_Sun_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );

		$cmb->add_field( array(
			'name' => '',
			'id'   => 'o100_delivery_generated_timeslots',
			'type' => 'o100_generated_timeslots',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_block3_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div></div>'; }
		) );

		// Block 4: Override for Pickup
		$cmb->add_field( array(
			'id'   => 'o100_schedule_block4_start',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Override Default Schedule for Pickup', 'order100') . '</h3><p>' . esc_html__('If enabled, Pickup will follow below schedule instead of global.', 'order100') . '</p></div><div class="o100-settings-group-content">'; }
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Override Default Schedule', 'order100' ),
			'id'   => 'o100_pickup_override_schedule',
			'type' => 'checkbox',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_pickup_config_start',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div class="o100-pickup-override-content"><div style="display:flex; gap:20px; align-items:flex-end; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px;">'; }
		) );

		$cmb->add_field( array(
			'name'       => esc_html__( 'Time Intervals', 'order100' ),
			'id'         => 'o100_pickup_interval',
			'type'       => 'text',
			'attributes' => array( 'placeholder' => 'e.g. 30' ),
			'classes'    => 'o100-inline-field-sm',
			'after'      => '<span class="o100-input-suffix">minutes</span>',
		) );

		$cmb->add_field( array(
			'name'       => esc_html__( 'Default Max Order', 'order100' ),
			'id'         => 'o100_pickup_max_order',
			'type'       => 'text',
			'attributes' => array( 'placeholder' => 'e.g. 5' ),
			'classes'    => 'o100-inline-field-sm',
			'after'      => '<span class="o100-input-suffix">orders</span>',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_pickup_config_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div><h4 style="margin:0 0 15px 0; font-size:15px; color:#0f172a;">' . esc_html__('Weekly Schedule (Pickup):', 'order100') . '</h4>'; }
		) );

		$cmb->add_field( array( 'name' => esc_html__( 'Mon', 'order100' ), 'id' => 'o100_pickup_Mon_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Tue', 'order100' ), 'id' => 'o100_pickup_Tue_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Wed', 'order100' ), 'id' => 'o100_pickup_Wed_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Thu', 'order100' ), 'id' => 'o100_pickup_Thu_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Fri', 'order100' ), 'id' => 'o100_pickup_Fri_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Sat', 'order100' ), 'id' => 'o100_pickup_Sat_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Sun', 'order100' ), 'id' => 'o100_pickup_Sun_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );

		$cmb->add_field( array(
			'name' => '',
			'id'   => 'o100_pickup_generated_timeslots',
			'type' => 'o100_generated_timeslots',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_block4_end',
			'type' => 'title',
			'render_row_cb' => function() { 
				echo '</div></div></div>'; 
				
				// Inject simple UI logic for toggles
				?>
				<script type="text/javascript">
				jQuery(document).ready(function($) {
					// Hard toggle for delivery content
					function toggleDeliveryOverride() {
						if ($('#o100_delivery_override_schedule').is(':checked')) {
							$('.o100-delivery-override-content').show();
						} else {
							$('.o100-delivery-override-content').hide();
						}
					}
					$('#o100_delivery_override_schedule').on('change', toggleDeliveryOverride);
					toggleDeliveryOverride();

					// Hard toggle for pickup content
					function togglePickupOverride() {
						if ($('#o100_pickup_override_schedule').is(':checked')) {
							$('.o100-pickup-override-content').show();
						} else {
							$('.o100-pickup-override-content').hide();
						}
					}
					$('#o100_pickup_override_schedule').on('change', togglePickupOverride);
					togglePickupOverride();

					// Hard toggle for reservation content
					function toggleReservationOverride() {
						if ($('#o100_reservation_override_schedule').is(':checked')) {
							$('.o100-reservation-override-content').show();
						} else {
							$('.o100-reservation-override-content').hide();
						}
					}
					$('#o100_reservation_override_schedule').on('change', toggleReservationOverride);
					toggleReservationOverride();
				});
				</script>
				<?php
			}
		) );

		// Block 5: Override for Reservation
		$cmb->add_field( array(
			'id'   => 'o100_schedule_block5_start',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Override Default Schedule for Reservation', 'order100') . '</h3><p>' . esc_html__('If enabled, Reservations will follow the schedule below instead of global hours.', 'order100') . '</p></div><div class="o100-settings-group-content">'; }
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Override Default Schedule', 'order100' ),
			'id'   => 'o100_reservation_override_schedule',
			'type' => 'checkbox',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_resv_config_start',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div class="o100-reservation-override-content"><div style="display:flex; gap:20px; align-items:flex-end; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px;">'; }
		) );

		$cmb->add_field( array(
			'name'       => esc_html__( 'Time Intervals', 'order100' ),
			'id'         => 'o100_reservation_interval',
			'type'       => 'text',
			'attributes' => array( 'placeholder' => 'e.g. 30' ),
			'classes'    => 'o100-inline-field-sm',
			'after'      => '<span class="o100-input-suffix">minutes</span>',
		) );

		$cmb->add_field( array(
			'name'       => esc_html__( 'Max Tables Per Slot', 'order100' ),
			'id'         => 'o100_resv_max_per_slot',
			'type'       => 'text',
			'attributes' => array( 'placeholder' => 'e.g. 5' ),
			'classes'    => 'o100-inline-field-sm',
			'after'      => '<span class="o100-input-suffix">tables</span>',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_resv_config_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div><h4 style="margin:0 0 15px 0; font-size:15px; color:#0f172a;">' . esc_html__('Weekly Schedule (Reservation):', 'order100') . '</h4>'; }
		) );

		$cmb->add_field( array( 'name' => esc_html__( 'Mon', 'order100' ), 'id' => 'o100_reservation_Mon_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Tue', 'order100' ), 'id' => 'o100_reservation_Tue_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Wed', 'order100' ), 'id' => 'o100_reservation_Wed_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Thu', 'order100' ), 'id' => 'o100_reservation_Thu_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Fri', 'order100' ), 'id' => 'o100_reservation_Fri_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Sat', 'order100' ), 'id' => 'o100_reservation_Sat_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );
		$cmb->add_field( array( 'name' => esc_html__( 'Sun', 'order100' ), 'id' => 'o100_reservation_Sun_opcl_time', 'type' => 'openclose', 'time_format'=> 'H:i', 'classes' => 'o100-weekday-schedule-field' ) );

		$cmb->add_field( array(
			'name' => '',
			'id'   => 'o100_reservation_generated_timeslots',
			'type' => 'o100_generated_timeslots',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_schedule_block5_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div></div>'; }
		) );

		// Block 6: Global Ordering Status (Outside Business Hours)
		$cmb->add_field( array(
			'id'   => 'o100_store_hours_sec_ordering_status_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Ordering Outside Business Hours', 'order100') . '</h3><p>' . esc_html__('If enabled, your store will accept orders 24/7. If disabled, orders are only accepted during your configured business hours.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Allow Orders Outside Business Hours', 'order100' ),
			'id'   => 'o100_op_cl',
			'type' => 'checkbox',
			'default' => function() {
				$opts = get_option('o100_store_hours', false);
				return ( $opts === false ) ? 'on' : '';
			},
		) );

		$cmb->add_field( array(
			'id'   => 'o100_op_cl_inline_js',
			'type' => 'title',
			'render_row_cb' => function() {
				// Inline JS to handle 'show when unchecked' conditional logic
				echo '<script>
				jQuery(document).ready(function($){
					function toggleExcludedFields() {
						var isChecked = $("#o100_op_cl").is(":checked");
						var $fields = $(".cmb2-id-o100-ign-op, .cmb2-id-o100-ign-op-cat");
						var $msgField = $(".cmb2-id-o100-outside-hours-msg");
						if (isChecked) {
							$fields.hide();
							$msgField.show();
						} else {
							$fields.show();
							$msgField.hide();
						}
					}
					$("#o100_op_cl").on("change", toggleExcludedFields);
					setTimeout(toggleExcludedFields, 50);
				});
				</script>';
			}
		) );

		$cmb->add_field( array(
			'name'    => esc_html__( 'Outside Business Hours Notice', 'order100' ),
			'desc'    => esc_html__( 'Display this message at the top of the entry modal when the store is closed but orders are allowed.', 'order100' ),
			'id'      => 'o100_outside_hours_msg',
			'type'    => 'textarea_small',
			'default' => esc_html__( 'We are currently outside business hours. You may still place your order — please select a time slot at checkout.', 'order100' ),
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Allow specific products when closed', 'order100' ),
			'desc' => esc_html__( 'Select products that can still be ordered even outside business hours.', 'order100' ),
			'id'   => 'o100_ign_op',
			'type' => 'o100_product_search',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Allow specific categories when closed', 'order100' ),
			'desc' => esc_html__( 'Select categories that can still be ordered even outside business hours.', 'order100' ),
			'id'   => 'o100_ign_op_cat',
			'type' => 'o100_category_search',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_store_hours_sec_ordering_status_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div>'; }
		) );

		// ── Emergency Closure Configuration ──
		$cmb->add_field( array(
			'id'   => 'o100_ec_section_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Emergency Closure', 'order100') . '</h3><p>' . esc_html__('Configure the admin bar quick-toggle and default closure reasons shown to customers.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Admin Bar Quick Toggle', 'order100' ),
			'desc' => esc_html__( 'Show an "Emergency Closure" button in the WordPress admin bar for one-click store open/close.', 'order100' ),
			'id'   => 'o100_ec_enable_admin_bar',
			'type' => 'checkbox',
			'default' => 'on',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Banner Background Color', 'order100' ),
			'desc' => esc_html__( 'Background color of the closure banner shown to customers.', 'order100' ),
			'id'   => 'o100_ec_banner_bg',
			'type' => 'colorpicker',
			'default' => '#d63638',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Banner Text Color', 'order100' ),
			'id'   => 'o100_ec_banner_text',
			'type' => 'colorpicker',
			'default' => '#ffffff',
		) );

		$ec_reasons_group = $cmb->add_field( array(
			'id'      => 'o100_ec_reasons',
			'type'    => 'group',
			'options' => array(
				'group_title'   => esc_html__( 'Reason {#}', 'order100' ),
				'add_button'    => esc_html__( 'Add Reason', 'order100' ),
				'remove_button' => esc_html__( 'Remove', 'order100' ),
				'sortable'      => true,
			),
		) );

		$cmb->add_group_field( $ec_reasons_group, array(
			'name' => esc_html__( 'Label (Admin)', 'order100' ),
			'desc' => esc_html__( 'Short label shown in the admin bar dropdown.', 'order100' ),
			'id'   => 'label',
			'type' => 'text',
		) );

		$cmb->add_group_field( $ec_reasons_group, array(
			'name' => esc_html__( 'Customer Message', 'order100' ),
			'desc' => esc_html__( 'This message is displayed to customers in the closure banner.', 'order100' ),
			'id'   => 'message',
			'type' => 'textarea_small',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_ec_section_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div>'; }
		) );

		// TAB: Locations
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_locations',
			'title'        => __( 'Branches', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_locations',
			'display_cb'   => '__return_false',
		) );

		$cmb->add_field( array(
			'name' => '',
			'desc' => '',
			'id'   => 'o100_locations_intro',
			'type' => 'title',
			'after_row' => array( $this, 'render_locations_intro' ),
		) );





				$cmb->add_field( array(
			'name' => '',
			'id'   => 'o100_locations_manager_html',
			'type' => 'title',
			'after_row' => array( $this, 'render_fluent_locations_manager' ),
		) );


		// TAB: Tipping has been fully migrated to Fluent UI (see render_fluent_checkout_ext)






		// TAB: Delivery
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_delivery',
			'title'        => __( 'Delivery', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_delivery',
			'display_cb'   => '__return_false',
		) );

		$cmb->add_field( array(
			'name' => '',
			'desc' => '',
			'id'   => 'o100_delivery_intro',
			'type' => 'title',
			'after_row' => array( __CLASS__, 'render_fluent_delivery' ),
		) );

		// TAB: Pickup
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_pickup',
			'title'        => __( 'Pickup', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_pickup',
			'display_cb'   => '__return_false',
		) );

		$cmb->add_field( array(
			'name' => '',
			'desc' => '',
			'id'   => 'o100_pickup_intro',
			'type' => 'title',
			'after_row' => array( __CLASS__, 'render_fluent_pickup' ),
		) );

		// TAB: Reservation
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_reservation',
			'title'        => __( 'Reservation', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_reservation',
			'display_cb'   => '__return_false',
		) );

		// ── Card 1: Basic Settings ──
		$cmb->add_field( array(
			'id'   => 'o100_resv_basic_start',
			'type' => 'title',
			'render_row_cb' => function() {
				// Inject CSS for o100-half suffix support + room repeater compact layout
				echo '<style>
				/* ═══ Global: o100-half with input suffix ═══ */
				.o100-half .cmb-td {
					display: flex !important;
					flex-wrap: wrap !important;
					align-items: stretch !important;
				}
				.o100-half .cmb-td > input[type="text"],
				.o100-half .cmb-td > input[type="number"],
				.o100-half .cmb-td > input[type="email"] {
					order: 1 !important;
				}
				.o100-half .cmb-td > .o100-input-suffix {
					order: 2 !important;
					display: inline-flex !important;
					align-items: center !important;
					justify-content: center !important;
					background: #f8fafc !important;
					border: 1px solid #cbd5e1 !important;
					border-left: none !important;
					padding: 0 15px !important;
					height: 40px !important;
					color: #64748b !important;
					font-size: 13px !important;
					border-radius: 0 6px 6px 0 !important;
					box-sizing: border-box !important;
					margin: 0 !important;
				}
				.o100-half .cmb-td > p.cmb2-metabox-description {
					order: 3 !important;
					width: 100% !important;
					margin-top: 8px !important;
				}
				/* Flatten input right border when suffix exists as sibling */
				.o100-half .cmb-td > input:has(~ .o100-input-suffix) {
					flex: 0 0 auto !important;
					width: 120px !important;
					border-radius: 6px 0 0 6px !important;
					border-right: none !important;
					margin: 0 !important;
				}
				.o100-resv-subtab:focus,
				.o100-resv-subtab:active {
					outline: none !important;
					box-shadow: none !important;
					border-color: transparent !important;
					border-bottom-color: transparent !important;
				}
				.o100-resv-subtab.active:focus,
				.o100-resv-subtab.active:active {
					border-bottom-color: #2563eb !important;
				}
				</style>';
				// ── Sub-tab navigation ──
				echo '<div class="o100-resv-subtabs" style="display:flex; width:100%; flex-shrink:0; border-bottom:2px solid #e2e8f0; margin-bottom:24px;">
					<a href="#" class="o100-resv-subtab active" data-tab="settings" style="padding:10px 20px; font-size:14px; font-weight:600; color:#2563eb; border-bottom:2px solid #2563eb; text-decoration:none; outline:none; margin-bottom:-2px; transition: all 0.15s;">Settings</a>
					<a href="#" class="o100-resv-subtab" data-tab="form" style="padding:10px 20px; font-size:14px; font-weight:500; color:#94a3b8; border-bottom:2px solid transparent; text-decoration:none; outline:none; margin-bottom:-2px; transition: all 0.15s;">Form Builder</a>
				</div>';
				echo '<div class="o100-settings-group-card o100-resv-settings-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Basic Settings', 'order100') . '</h3><p>' . esc_html__('Core reservation configuration for your restaurant.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Reservation', 'order100' ),
			'desc' => esc_html__( 'Allow customers to book tables through your website.', 'order100' ),
			'id'   => 'o100_enable_reservation',
			'type' => 'checkbox',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Confirmation Mode', 'order100' ),
			'desc' => esc_html__( 'Auto: reservation is immediately confirmed. Manual: requires staff approval.', 'order100' ),
			'id'   => 'o100_resv_confirmation',
			'type' => 'select',
			'default' => 'auto',
			'options' => array(
				'auto'   => esc_html__( 'Auto Confirm', 'order100' ),
				'manual' => esc_html__( 'Manual Confirm', 'order100' ),
			),
			'classes' => 'o100-half',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Max Party Size', 'order100' ),
			'id'   => 'o100_resv_max_party',
			'type' => 'text',
			'default' => '10',
			'attributes' => array( 'type' => 'number', 'min' => '1', 'max' => '100' ),
			'classes' => 'o100-half',
			'after' => '<span class="o100-input-suffix">guests</span>',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Minimum Lead Time', 'order100' ),
			'desc' => esc_html__( 'How many hours in advance must a reservation be made.', 'order100' ),
			'id'   => 'o100_resv_lead_time',
			'type' => 'text',
			'default' => '2',
			'attributes' => array( 'type' => 'number', 'min' => '0', 'max' => '72' ),
			'classes' => 'o100-half',
			'after' => '<span class="o100-input-suffix">hours</span>',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Max Advance Booking', 'order100' ),
			'desc' => esc_html__( 'How many days ahead customers can book.', 'order100' ),
			'id'   => 'o100_resv_max_advance',
			'type' => 'text',
			'default' => '30',
			'attributes' => array( 'type' => 'number', 'min' => '1', 'max' => '365' ),
			'classes' => 'o100-half',
			'after' => '<span class="o100-input-suffix">days</span>',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_resv_basic_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div style="clear:both;"></div></div></div>'; }
		) );

		// ── Card 2: Private Rooms & Events ──
		$cmb->add_field( array(
			'id'   => 'o100_resv_rooms_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Private Rooms & Events', 'order100') . '</h3><p>' . esc_html__('Configure private dining rooms and event spaces available for booking.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Private Room Booking', 'order100' ),
			'desc' => esc_html__( 'Allow customers to book private rooms and event spaces.', 'order100' ),
			'id'   => 'o100_resv_enable_rooms',
			'type' => 'checkbox',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Private Room Party Size Prompt', 'order100' ),
			'desc' => esc_html__( 'Automatically prompt customers to book a private room if their party size is greater than or equal to this number.', 'order100' ),
			'id'   => 'o100_resv_private_room_threshold',
			'type' => 'text',
			'default' => '8',
			'attributes' => array(
				'type' => 'number', 'min' => '1', 'max' => '100',
				'data-conditional-id'    => 'o100_resv_enable_rooms',
				'data-conditional-value' => 'on',
			),
			'classes' => 'o100-half',
		) );


		// Custom room list + modal (replaces CMB2 group repeater)
		$cmb->add_field( array(
			'id'   => 'o100_resv_rooms',
			'type' => 'title',
			'render_row_cb' => array( $this, 'render_rooms_table' ),
		) );

		$cmb->add_field( array(
			'id'   => 'o100_resv_rooms_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div style="clear:both;"></div></div></div>'; }
		) );

		// ── Card 3: Notifications ──
		$cmb->add_field( array(
			'id'   => 'o100_resv_notify_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Notifications & Messages', 'order100') . '</h3><p>' . esc_html__('Configure reminder timing and confirmation messages.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Reminder Before Reservation', 'order100' ),
			'desc' => esc_html__( 'Send a reminder email this many hours before the reservation. Set 0 to disable.', 'order100' ),
			'id'   => 'o100_resv_reminder_hours',
			'type' => 'text',
			'default' => '2',
			'attributes' => array( 'type' => 'number', 'min' => '0', 'max' => '48' ),
			'classes' => 'o100-half',
			'after' => '<span class="o100-input-suffix">hours</span>',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Admin Notification Email', 'order100' ),
			'desc' => esc_html__( 'Email address to receive new reservation alerts. Defaults to site admin email.', 'order100' ),
			'id'   => 'o100_resv_admin_email',
			'type' => 'text_email',
			'default' => get_option( 'admin_email' ),
			'classes' => 'o100-half',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Success Message', 'order100' ),
			'desc' => esc_html__( 'Displayed after a customer submits a reservation.', 'order100' ),
			'id'   => 'o100_resv_success_msg',
			'type' => 'textarea_small',
			'default' => esc_html__( 'Thank you! Your reservation has been received. We will contact you shortly to confirm.', 'order100' ),
		) );

		$cmb->add_field( array(
			'id'   => 'o100_resv_notify_end',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div style="clear:both;"></div></div></div>'; // close card 3
			}
		) );

		// ── Form Builder sub-tab ──
		$cmb->add_field( array(
			'id'   => 'o100_resv_form_builder',
			'type' => 'title',
			'render_row_cb' => array( $this, 'render_form_builder' ),
		) );

		// ── Sub-tab toggle JS ──
		$cmb->add_field( array(
			'id'   => 'o100_resv_subtab_js',
			'type' => 'title',
			'render_row_cb' => function() {
				?>
				<script type="text/javascript">
				jQuery(document).ready(function($) {
					// Wrap all settings cards between subtabs and form-builder into a container
					var $subtabs = $('.o100-resv-subtabs');
					var $formTab = $('#o100-resv-tab-form');
					if ($subtabs.length && $formTab.length) {
						// Collect all siblings between subtabs and form tab
						var $settingsEls = $subtabs.nextUntil($formTab);
						$settingsEls.wrapAll('<div id="o100-resv-tab-settings" class="o100-resv-tab-content" style="width:100%;"></div>');
					}

					$('.o100-resv-subtab').on('click', function(e) {
						e.preventDefault();
						var tab = $(this).data('tab');
						$('.o100-resv-subtab').removeClass('active').css({
							'color': '#94a3b8',
							'font-weight': '500',
							'border-bottom-color': 'transparent'
						});
						$(this).addClass('active').css({
							'color': '#2563eb',
							'font-weight': '600',
							'border-bottom-color': '#2563eb'
						});
						$('.o100-resv-tab-content').hide();
						$('#o100-resv-tab-' + tab).show();
					});
				});
				</script>
				<?php
			}
		) );

		// TAB: Product Options
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_product_options',
			'title'        => __( 'Item Modifiers', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_product_options',
			'display_cb'   => '__return_false',
		) );





		// ──────────────────────────────────────────────────────────
		// NEW: Global Option Groups (Fluent Custom UI)
		// ──────────────────────────────────────────────────────────
		if ( class_exists( 'O100_Fluent_Addons' ) ) {
			$cmb->add_field( array(
				'id'   => 'o100_product_options_sec_groups_html',
				'type' => 'title',
				'render_row_cb' => array( 'O100_Fluent_Addons', 'render_manager' ),
			) );
		}

		// TAB: Menu Rules
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_menu_rules',
			'title'        => __( 'Menu Rules', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_menu_rules',
			'display_cb'   => '__return_false',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_sec_disable',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Standard eCommerce Mode', 'order100') . '</h3><p>' . esc_html__('Select products/categories that should behave like normal eCommerce products (no popup, no extra options).', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Standard eCommerce Mode', 'order100' ),
			'desc' => esc_html__( 'Turn this on to disable food ordering fields for specific products or categories.', 'order100' ),
			'id'   => 'o100_enable_standard_ecom',
			'type' => 'checkbox',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Disable Food fields in Products', 'order100' ),
			'desc' => esc_html__( 'Select specific products to exclude from food ordering fields (Extra Options, delivery selection, etc.)', 'order100' ),
			'id'   => 'o100_disable_food_pro',
			'type' => 'o100_product_search',
			'attributes' => array(
				'data-conditional-id' => 'o100_enable_standard_ecom',
				'data-conditional-value' => 'on',
			),
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Disable Food fields in Category', 'order100' ),
			'desc' => esc_html__( 'All products under the selected categories will not show food ordering fields.', 'order100' ),
			'id'   => 'o100_disable_food_cat',
			'type' => 'o100_category_search',
			'attributes' => array(
				'data-conditional-id' => 'o100_enable_standard_ecom',
				'data-conditional-value' => 'on',
			),
		) );

		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_sec_disable_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div>'; }
		) );

			// ── Menu by Order Method section ──
		$_mr_deli_opts = get_option('o100_delivery', array());
		$_mr_pick_opts = get_option('o100_pickup', array());
		$_mr_resv_opts = get_option('o100_reservation', array());
		$_mr_deli_on = !empty($_mr_deli_opts['o100_enable_delivery']) && $_mr_deli_opts['o100_enable_delivery'] === 'on';
		$_mr_pick_on = !empty($_mr_pick_opts['o100_enable_pickup']) && $_mr_pick_opts['o100_enable_pickup'] === 'on';
		$_mr_resv_on = !empty($_mr_resv_opts['o100_enable_reservation']) && $_mr_resv_opts['o100_enable_reservation'] === 'on';
		$_mr_count = (int)$_mr_deli_on + (int)$_mr_pick_on + (int)$_mr_resv_on;

		if ( $_mr_count > 1 ) {

		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_sec_method',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Menu by Order Method', 'order100') . '</h3><p>' . esc_html__('Configure which products are restricted to specific order methods.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			},
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Order Method Filtering', 'order100' ),
			'desc' => esc_html__( 'If enabled, you can restrict specific products to Delivery-Only or Pickup-Only globally here, or individually inside the Product Edit page.', 'order100' ),
			'id'   => 'o100_menu_method',
			'type' => 'checkbox',
		) );

		if ( $_mr_deli_on ) {
		$cmb->add_field( array(
			'name' => esc_html__( 'Delivery Only Products', 'order100' ),
			'desc' => esc_html__( 'Select products that are ONLY available for Delivery.', 'order100' ),
			'id'   => 'o100_delivery_only_pro',
			'type' => 'o100_product_search',
			'attributes' => array( 'data-conditional-id' => 'o100_menu_method', 'data-conditional-value' => 'on' ),
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Delivery Only Categories', 'order100' ),
			'desc' => esc_html__( 'All products under these categories will ONLY be available for Delivery.', 'order100' ),
			'id'   => 'o100_delivery_only_cat',
			'type' => 'o100_category_search',
			'attributes' => array( 'data-conditional-id' => 'o100_menu_method', 'data-conditional-value' => 'on' ),
		) );
		}

		if ( $_mr_pick_on ) {
		$cmb->add_field( array(
			'name' => esc_html__( 'Pickup Only Products', 'order100' ),
			'desc' => esc_html__( 'Select products that are ONLY available for Pickup.', 'order100' ),
			'id'   => 'o100_pickup_only_pro',
			'type' => 'o100_product_search',
			'attributes' => array( 'data-conditional-id' => 'o100_menu_method', 'data-conditional-value' => 'on' ),
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Pickup Only Categories', 'order100' ),
			'desc' => esc_html__( 'All products under these categories will ONLY be available for Pickup.', 'order100' ),
			'id'   => 'o100_pickup_only_cat',
			'type' => 'o100_category_search',
			'attributes' => array( 'data-conditional-id' => 'o100_menu_method', 'data-conditional-value' => 'on' ),
		) );
		}

		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_sec_method_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div>'; },
		) );

		} // end $_mr_count > 1


		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_sec_date',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Menu by Date', 'order100') . '</h3><p>' . esc_html__('Create daily menus — restrict specific products to only be available on certain days.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Daily Menu Filtering', 'order100' ),
			'desc' => esc_html__( 'Enable this to activate date-based filtering globally and on individual products.', 'order100' ),
			'id'   => 'o100_menu_date',
			'type' => 'checkbox',
		) );

		$group_id = $cmb->add_field( array(
			'id'          => 'o100_global_date_rules',
			'type'        => 'group',
			'description' => __( 'Add global date rules. Products mapped here will ONLY be available on the dates you specify.', 'order100' ),
			'options'     => array(
				'group_title'       => __( 'Date Rule {#}', 'order100' ),
				'add_button'        => __( 'Add Another Rule', 'order100' ),
				'remove_button'     => __( 'Remove Rule', 'order100' ),
				'sortable'          => true,
				'closed'            => true,
			),
			'attributes' => array(
				'data-conditional-id' => 'o100_menu_date',
				'data-conditional-value' => 'on',
			),
		) );

		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Allowed Weekdays', 'order100' ),
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

		$cmb->add_group_field( $group_id, array(
			'name' => __( 'Allowed Specific Dates', 'order100' ),
			'desc' => __( 'Click the input box to select one or multiple dates from the calendar.', 'order100' ),
			'id'   => 'o100_rule_dates',
			'type' => 'text',
			'attributes' => array(
				'class' => 'o100-flatpickr-multi',
				'placeholder' => __( 'Select dates...', 'order100' ),
			),
		) );

		$cmb->add_group_field( $group_id, array(
			'name'             => __( 'Assign To', 'order100' ),
			'id'               => 'o100_rule_assign_type',
			'type'             => 'select',
			'show_option_none' => false,
			'default'          => 'products',
			'options'          => array(
				'products'   => __( 'Specific Products', 'order100' ),
				'categories' => __( 'Specific Categories', 'order100' ),
			),
			'before_row'       => '<div class="o100-assign-flex-row">',
		) );

		$cmb->add_group_field( $group_id, array(
			'name' => __( 'Select Products', 'order100' ),
			'id'   => 'o100_rule_products',
			'type' => 'o100_product_search',
		) );

		$cmb->add_group_field( $group_id, array(
			'name' => __( 'Select Categories', 'order100' ),
			'id'   => 'o100_rule_categories',
			'type' => 'o100_category_search',
			'after_row'        => '</div>',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_sec_date_end',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '</div></div>';
			}
		) );

		// TAB: Misc
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_misc',
			'title'        => __( 'Misc', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_misc',
			'display_cb'   => '__return_false',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_misc_sec_whatsapp',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Order on WhatsApp', 'order100') . '</h3><p>' . esc_html__('Let customers place orders directly via WhatsApp instead of the standard checkout flow.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'WhatsApp Number', 'order100' ),
			'desc' => esc_html__( 'International format, e.g. +12124567890', 'order100' ),
			'id'   => 'o100_wa_num',
			'type' => 'text',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Message Title', 'order100' ),
			'desc' => esc_html__( 'Custom greeting at the top of the WhatsApp message. Leave empty for default.', 'order100' ),
			'id'   => 'o100_wa_title',
			'type' => 'text',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'WhatsApp by Location', 'order100' ),
			'desc' => esc_html__( 'Use a different WhatsApp number for each branch location.', 'order100' ),
			'id'   => 'o100_wa_loc',
			'type' => 'select',
			'options' => array(
				''    => esc_html__( 'No', 'order100' ),
				'yes' => esc_html__( 'Yes', 'order100' ),
			),
		) );

		$cmb->add_field( array(
			'id'   => 'o100_misc_sec_whatsapp_end',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '</div></div>';
			}
		) );

		// ═══════════════════════════════════════════════════════════════════
		// Food Labels Library
		// ═══════════════════════════════════════════════════════════════════
		$cmb->add_field( array(
			'id'   => 'o100_misc_sec_labels_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Food Labels Library', 'order100') . '</h3><p>' . esc_html__('Define global food labels (e.g., Spicy, Vegan) to be used across your products.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$group_labels = $cmb->add_field( array(
			'id'          => 'o100_global_food_labels',
			'type'        => 'group',
			'description' => esc_html__( 'Add global food labels.', 'order100' ),
			'options'     => array(
				'group_title'   => esc_html__( 'Label {#}', 'order100' ),
				'add_button'    => esc_html__( 'Add New Label', 'order100' ),
				'remove_button' => esc_html__( 'Remove Label', 'order100' ),
				'sortable'      => false,
			),
		) );

		$cmb->add_group_field( $group_labels, array(
			'name' => esc_html__( 'Label Name', 'order100' ),
			'id'   => 'name',
			'type' => 'text',
			'attributes' => array(
				'placeholder' => esc_attr__( 'Label name...', 'order100' ),
			),
		) );

		$cmb->add_group_field( $group_labels, array(
			'name' => esc_html__( 'Icon', 'order100' ),
			'id'   => 'icon',
			'type' => 'file',
			'options' => array(
				'url' => false, 
			),
			'query_args' => array(
				'type' => array(
					'image/gif',
					'image/jpeg',
					'image/png',
					'image/svg+xml',
				),
			),
		) );

		$cmb->add_group_field( $group_labels, array(
			'name' => esc_html__( 'Background Color', 'order100' ),
			'id'   => 'bgcolor',
			'type' => 'colorpicker',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_misc_sec_labels_end',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '</div></div>';
			}
		) );

		// TAB: UI & Messaging
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_ui_prefs',
			'title'        => __( 'UI & Messaging', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_ui_prefs',
			'display_cb'   => '__return_false',
		) );

		// -- Group 1: Color Configuration (Brand + Notice Colors) --
		$cmb->add_field( array(
			'id'   => 'o100_title_colors_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Color Configuration', 'order100') . '</h3><p>' . esc_html__('Define your brand identity and system notification colors. These colors are applied globally across your storefront.', 'order100') . '</p></div><div class="o100-settings-group-content">';
				echo '<div class="o100-color-section"><div class="o100-color-section-header">' . esc_html__('Brand', 'order100') . '</div>';
				echo '<p class="o100-color-section-desc">' . esc_html__('Your primary brand color — used for buttons, links, active states, and key UI accents.', 'order100') . '</p>';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Primary Color', 'order100' ),
			'id'   => 'o100_main_color',
			'type' => 'colorpicker',
			'default' => '#e60023',
			'after_row' => '</div><hr class="o100-color-divider">',
		) );

		// Sub-section: Warning
		$cmb->add_field( array(
			'id'   => 'o100_color_warn_section',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-color-section"><div class="o100-color-section-header">' . esc_html__('Warning', 'order100') . '</div>';
				echo '<p class="o100-color-section-desc">' . esc_html__('Blocking alerts such as minimum order not met, out of delivery range, or store closed.', 'order100') . '</p>';
			}
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Text', 'order100' ),
			'id'   => 'o100_color_warn_txt',
			'type' => 'colorpicker',
			'default' => '#9f1239',
			'classes' => 'o100-half',
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Background', 'order100' ),
			'id'   => 'o100_color_warn_bg',
			'type' => 'colorpicker',
			'default' => '#fff1f2',
			'classes' => 'o100-half',
			'after_row' => '</div><hr class="o100-color-divider">',
		) );

		// Sub-section: Promotion
		$cmb->add_field( array(
			'id'   => 'o100_color_promo_section',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-color-section"><div class="o100-color-section-header">' . esc_html__('Promotion', 'order100') . '</div>';
				echo '<p class="o100-color-section-desc">' . esc_html__('Free shipping progress bars, discount banners, and upsell messages.', 'order100') . '</p>';
			}
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Text', 'order100' ),
			'id'   => 'o100_color_promo_txt',
			'type' => 'colorpicker',
			'default' => '#9333ea',
			'classes' => 'o100-half',
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Background', 'order100' ),
			'id'   => 'o100_color_promo_bg',
			'type' => 'colorpicker',
			'default' => '#faf5ff',
			'classes' => 'o100-half',
			'after_row' => '</div><hr class="o100-color-divider">',
		) );

		// Sub-section: Success
		$cmb->add_field( array(
			'id'   => 'o100_color_success_section',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-color-section"><div class="o100-color-section-header">' . esc_html__('Success', 'order100') . '</div>';
				echo '<p class="o100-color-section-desc">' . esc_html__('Order confirmations, item added to cart, and positive feedback messages.', 'order100') . '</p>';
			}
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Text', 'order100' ),
			'id'   => 'o100_color_success_txt',
			'type' => 'colorpicker',
			'default' => '#15803d',
			'classes' => 'o100-half',
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Background', 'order100' ),
			'id'   => 'o100_color_success_bg',
			'type' => 'colorpicker',
			'default' => '#f0fdf4',
			'classes' => 'o100-half',
			'after_row' => '</div><hr class="o100-color-divider">',
		) );

		// Sub-section: Info
		$cmb->add_field( array(
			'id'   => 'o100_color_info_section',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-color-section"><div class="o100-color-section-header">' . esc_html__('Info', 'order100') . '</div>';
				echo '<p class="o100-color-section-desc">' . esc_html__('General system information, tips, and neutral status messages.', 'order100') . '</p>';
			}
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Text', 'order100' ),
			'id'   => 'o100_color_info_txt',
			'type' => 'colorpicker',
			'default' => '#1e40af',
			'classes' => 'o100-half',
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Background', 'order100' ),
			'id'   => 'o100_color_info_bg',
			'type' => 'colorpicker',
			'default' => '#eff6ff',
			'classes' => 'o100-half',
			'after_row' => '</div>',
		) );

		// -- Group 2: Storefront Behavior --
		$cmb->add_field( array(
			'id'   => 'o100_title_ui_overrides_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '</div></div><div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Storefront Behavior', 'order100') . '</h3><p>' . esc_html__('Control product popup behavior, cart interactions, and other storefront UI elements.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Product Quick View Popup', 'order100' ),
			'after_row' => '<div style="font-size:12px; color:#64748b; margin-top:-10px; margin-bottom:15px; padding-left:0;">' . esc_html__( 'Enable our optimized AJAX product popup. Uncheck to redirect to native WooCommerce single product pages.', 'order100' ) . '</div>',
			'id'   => 'o100_single_pop',
			'type' => 'checkbox',
			'default' => 'on',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Auto-close Popup', 'order100' ),
			'after_row' => '<div style="font-size:12px; color:#64748b; margin-top:-10px; margin-bottom:15px; padding-left:0;">' . esc_html__( 'Automatically close the product popup after an item is added to the cart.', 'order100' ) . '</div>',
			'id'   => 'o100_close_pop',
			'type' => 'checkbox',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Slide-out Sidecart', 'order100' ),
			'after_row' => '<div style="font-size:12px; color:#64748b; margin-top:-10px; margin-bottom:15px; padding-left:0;">' . esc_html__( 'Enable our custom slide-out sidecart. Uncheck if your theme provides a better native cart.', 'order100' ) . '</div>',
			'id'   => 'o100_tp_sidecart',
			'type' => 'checkbox',
			'default' => 'on',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Floating Minicart Icon', 'order100' ),
			'after_row' => '<div style="font-size:12px; color:#64748b; margin-top:-10px; margin-bottom:15px; padding-left:0;">' . esc_html__( 'Display a floating cart icon on the bottom corner of the screen.', 'order100' ) . '</div>',
			'id'   => 'o100_tp_minicart',
			'type' => 'checkbox',
			'default' => 'on',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Show Popup Close Button', 'order100' ),
			'id'   => 'o100_close_btn',
			'type' => 'checkbox',
			'default' => 'on',
		) );

		// -- Group 1.5: Social Sharing --
		$cmb->add_field( array(
			'id'   => 'o100_title_social_share_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '</div></div><div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Social Sharing', 'order100') . '</h3><p>' . esc_html__('Configure social media sharing options for your products.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Social Share in Popup', 'order100' ),
			'after_row' => '<div style="font-size:12px; color:#64748b; margin-top:-10px; margin-bottom:15px; padding-left:0;">' . esc_html__( 'Display social sharing buttons inside the product popup.', 'order100' ) . '</div>',
			'id'   => 'o100_enable_social',
			'type' => 'checkbox',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Specific Platforms', 'order100' ),
			'id'   => 'o100_enabled_socials',
			'type' => 'multicheck_inline',
			'default' => array('facebook', 'twitter', 'whatsapp', 'email', 'linkedin'),
			'options' => array(
				'facebook' => 'Facebook', 
				'twitter' => 'Twitter (X)',
				'whatsapp' => 'WhatsApp',
				'email' => 'Email',
				'linkedin' => 'LinkedIn'
			),
		) );

		// -- Group 2: Checkout Alerts --
		$cmb->add_field( array(
			'id'   => 'o100_title_checkout_alerts_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '</div></div><div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Checkout Alerts & Messages', 'order100') . '</h3><p>' . esc_html__('Customize the text displayed when users face restrictions during checkout. Use variables like {dist} or {min_amount}.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Out of Delivery Range Warning', 'order100' ),
			'after_row' => '<div style="font-size:12px; color:#64748b; margin-top:-10px; margin-bottom:15px; padding-left:0;">' . esc_html__( 'Available variables: {dist}, {max}.', 'order100' ) . '</div>',
			'id'   => 'o100_msg_out_of_range',
			'type' => 'textarea_small',
			'default' => __( 'We\'re sorry! Your location ({dist} km) is outside our delivery range of {max} km. Please consider switching to Pickup.', 'order100' ),
			'attributes' => array(
				'placeholder' => __( 'We\'re sorry! Your location ({dist} km) is outside our delivery range of {max} km. Please consider switching to Pickup.', 'order100' ),
			),
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Minimum Order Not Met', 'order100' ),
			'after_row' => '<div style="font-size:12px; color:#64748b; margin-top:-10px; margin-bottom:15px; padding-left:0;">' . esc_html__( 'Available variables: {subtotal}, {min_amount}.', 'order100' ) . '</div>',
			'id'   => 'o100_msg_min_order',
			'type' => 'textarea_small',
			'default' => __( 'Your subtotal is {subtotal}. A minimum order of {min_amount} is required for delivery.', 'order100' ),
			'attributes' => array(
				'placeholder' => __( 'Your subtotal is {subtotal}. A minimum order of {min_amount} is required for delivery.', 'order100' ),
			),
		) );

		$cmb->add_field( array(
			'id'   => 'o100_ui_prefs_end',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '</div></div>';
			}
		) );



		// TAB: Integrations & APIs
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_api_integration',
			'title'        => __( 'Integrations', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_api_integration',
			'display_cb'   => '__return_false',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Google Maps API Key', 'order100' ),
			'desc' => esc_html__( 'Used for Address Autocomplete and Delivery Distance Calculation. Restrict this key by HTTP Referrers in Google Cloud Console.', 'order100' ),
			'id'   => 'o100_ggmap_api_js',
			'type' => 'text',
			'classes' => 'o100-half',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'FluentCRM Provider', 'order100' ),
			'id'   => 'o100_crm_provider',
			'type' => 'text',
			'classes' => 'o100-half',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'FluentCRM Lists', 'order100' ),
			'id'   => 'o100_crm_lists',
			'type' => 'text',
			'classes' => 'o100-half',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'FCM Master URL', 'order100' ),
			'id'   => 'o100_push_master_url',
			'type' => 'text',
			'classes' => 'o100-half',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'FCM License Key', 'order100' ),
			'id'   => 'o100_push_license_key',
			'type' => 'text',
			'classes' => 'o100-half',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Status Sync', 'order100' ),
			'id'   => 'o100_enable_status_sync',
			'type' => 'checkbox',
			'classes' => 'o100-half',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable FluentCRM', 'order100' ),
			'id'   => 'o100_enable_fluentcrm',
			'type' => 'checkbox',
			'classes' => 'o100-half',
		) );


		// ═══════════════════════════════════════════════════════════════════
		// STANDALONE PAGE: Discount
		// ═══════════════════════════════════════════════════════════════════
		// STANDALONE PAGE: Store Portal (Custom HTML Renderer - see class-o100-admin-menu.php)
		// ═══════════════════════════════════════════════════════════════════

		// ═══════════════════════════════════════════════════════════════════
		// STANDALONE PAGE: Loyalty
		// ═══════════════════════════════════════════════════════════════════
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_loyalty',
			'title'        => __( 'Loyalty', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_loyalty',
		) );

		$loyalty_admin  = null;
		if ( isset( $o100_instance ) && isset( $o100_instance->loyalty_admin ) ) {
			$loyalty_admin = $o100_instance->loyalty_admin;
		} elseif ( class_exists( 'O100_Loyalty_Admin' ) ) {
			$loyalty_admin = new O100_Loyalty_Admin();
		}

		$cmb->add_field( array(
			'name' => esc_html__( 'Global Loyalty Setting', 'order100' ),
			'desc' => '',
			'type' => 'title',
			'id'   => 'loyalty_global_title',
			'after_row' => array( $this, 'render_loyalty_toggle' ),
		) );

		$cmb->add_field( array(
			'name'      => esc_html__( 'Loyalty & Referral Management', 'order100' ),
			'desc'      => '',
			'type'      => 'title',
			'id'        => 'loyalty_management_title',
			'after_row' => $loyalty_admin ? array( $loyalty_admin, 'render_loyalty_tab' ) : '',
		) );

		// ═══════════════════════════════════════════════════════════════════
		// STANDALONE PAGE: SEO
		// ═══════════════════════════════════════════════════════════════════
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_seo',
			'title'        => __( 'SEO', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_seo',
			'display_cb'   => '__return_false',
		) );

		$seo_admin = null;
		if ( isset( $o100_instance ) && isset( $o100_instance->seo_admin ) ) {
			$seo_admin = $o100_instance->seo_admin;
		} elseif ( class_exists( 'O100_SEO_Admin' ) ) {
			$seo_admin = new O100_SEO_Admin();
		}

		$cmb->add_field( array(
			'name'      => esc_html__( 'Smart SEO Automation', 'order100' ),
			'desc'      => '',
			'type'      => 'title',
			'id'        => 'seo_management_title',
			'after_row' => $seo_admin ? array( $seo_admin, 'render_seo_tab' ) : '',
		) );

		// ═══════════════════════════════════════════════════════════════════
		// STANDALONE PAGE: Notifications
		// ═══════════════════════════════════════════════════════════════════
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_notifications',
			'title'        => __( 'Notifications', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_notifications',
		) );

		$cmb->add_field( array(
			'name'      => esc_html__( 'Email & Notification Settings', 'order100' ),
			'desc'      => '',
			'type'      => 'title',
			'id'        => 'notification_management_title',
			'after_row' => array( $this, 'render_notification_tab' ),
		) );
	}


	/**
	 * Render the loyalty module toggle (outside of CMB2's field system)
	 */
	public function render_loyalty_toggle( $field_args, $field ) {
		$options = get_option( 'o100_options', array() );
		$is_enabled = ! array_key_exists( 'o100_enable_loyalty', $options ) || $options['o100_enable_loyalty'] === 'on';
		?>
		<div class="cmb-row o100-tab-loyalty o100-loyalty-toggle-row" style="display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; margin: 0; border-top: 1px solid #e2e8f0;">
			<div style="flex: 1;">
				<label style="font-weight: 600; font-size: 14px; color: #1e293b; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Enable Loyalty Module', 'order100' ); ?></label>
				<p style="margin: 0; font-size: 12.5px; color: #64748b;"><?php esc_html_e( 'Turn this off to completely disable the loyalty system and hide the frontend launcher.', 'order100' ); ?></p>
			</div>
			<div style="display: flex; align-items: center; gap: 12px; flex-shrink: 0; margin-left: 24px;">
				<div class="o100-toggle-wrap">
					<span class="o100-toggle-status <?php echo $is_enabled ? 'is-enabled' : ''; ?>" id="o100-loyalty-toggle-label"><?php echo $is_enabled ? 'Enable' : 'Disable'; ?></span>
					<input type="checkbox" id="o100-loyalty-global-toggle" <?php checked( $is_enabled ); ?>>
				</div>
				<span id="o100-loyalty-toggle-feedback" style="font-weight: 600; font-size: 13px; white-space: nowrap;"></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render tab navigation bar
	 */
	public function render_tab_navigation( $field_args, $field ) {
		$tabs = array(
			'time'         => '<span class="dashicons dashicons-clock"></span> <span class="o100-tab-text">' . esc_html__( 'Schedule', 'order100' ) . '</span>',
			'checkout'     => '<span class="dashicons dashicons-cart"></span> <span class="o100-tab-text">' . esc_html__( 'Checkout', 'order100' ) . '</span>',
			'api'          => '<span class="dashicons dashicons-networking"></span> <span class="o100-tab-text">' . esc_html__( 'API & Integration', 'order100' ) . '</span>',
			'nav'          => '<span class="dashicons dashicons-category"></span> <span class="o100-tab-text">' . esc_html__( 'Navigation', 'order100' ) . '</span>',
			'discount'     => '<span class="dashicons dashicons-tickets-alt"></span> <span class="o100-tab-text">' . esc_html__( 'Discount', 'order100' ) . '</span>',
			'loyalty'      => '<span class="dashicons dashicons-star-filled"></span> <span class="o100-tab-text">' . esc_html__( 'Loyalty', 'order100' ) . '</span>',
			'portal'       => '<span class="dashicons dashicons-layout"></span> <span class="o100-tab-text">' . esc_html__( 'Store Portal', 'order100' ) . '</span>',
			'seo'          => '<span class="dashicons dashicons-search"></span> <span class="o100-tab-text">' . esc_html__( 'Smart SEO', 'order100' ) . '</span>',
			'notification' => '<span class="dashicons dashicons-email-alt"></span> <span class="o100-tab-text">' . esc_html__( 'Notifications', 'order100' ) . '</span>',
		);
		?>
		<div class="o100-master-header">
			<div class="o100-master-brand">
				<h2>Addon Feature <span class="o100-version">v<?php echo esc_html( O100_VERSION ); ?></span></h2>
			</div>
			<div class="o100-master-links">
				<a href="https://yestech.ca" target="_blank">Tutorials <span class="dashicons dashicons-external"></span></a>
				<a href="https://yestech.ca" target="_blank">Docs <span class="dashicons dashicons-external"></span></a>
			</div>
		</div>
		<?php

		echo '<ul class="o100-tabs-nav">';
		foreach ( $tabs as $id => $label ) {
			echo '<li><a href="#" data-tab="' . esc_attr( $id ) . '">' . $label . '</a></li>';
		}
		echo '</ul>';
	}

	/**
	 * Render the Notification tab content (custom HTML callback)
	 */
	public function render_notification_tab( $field_args, $field ) {
		// Helper to query template status from DB
		$get_tpl_status = function( $template_id ) {
			$data = \Order100\Notification\Engine\Models\TemplateModel::get_short_data_by_name( $template_id );
			if ( ! $data || empty( $data['id'] ) ) {
				return [ 'status' => 'none', 'label' => __( 'Not configured', 'order100' ), 'css' => 'inactive' ];
			}
			if ( isset( $data['status'] ) && $data['status'] === 'active' ) {
				return [ 'status' => 'active', 'label' => __( 'Active', 'order100' ), 'css' => 'active' ];
			}
			return [ 'status' => 'inactive', 'label' => __( 'Inactive', 'order100' ), 'css' => 'inactive' ];
		};

		// Order flow email templates matching the restaurant workflow
		// Triggers: WC hooks + App callbacks via Core Manager → Inbound API
		$email_templates = array(
			array(
				'id'        => 'new_order',
				'label'     => __( 'New Order', 'order100' ),
				'desc'      => __( 'Customer places order → Admin/restaurant notified', 'order100' ),
				'recipient' => __( 'Admin / Restaurant', 'order100' ),
				'trigger'   => 'wc:new_order',
				'icon'      => '🔔',
			),
			array(
				'id'          => 'customer_processing_order',
				'label'       => __( 'Order Confirmed', 'order100' ),
				'desc'        => __( 'Restaurant confirms on App + selects prep time → Customer notified', 'order100' ),
				'recipient'   => __( 'Customer', 'order100' ),
				'trigger'     => 'app:order-confirm',
				'icon'        => '📋',
			),
			array(
				'id'          => 'o100_order_ready',
				'label'       => __( 'Ready for Pickup', 'order100' ),
				'desc'        => __( 'Food ready (Pickup) → Customer notified to pick up', 'order100' ),
				'recipient'   => __( 'Customer', 'order100' ),
				'trigger'     => 'app:order-ready',
				'icon'        => '🛎️',
				'coming_soon' => true,
			),
			array(
				'id'          => 'o100_out_for_delivery',
				'label'       => __( 'Out for Delivery', 'order100' ),
				'desc'        => __( 'Food ready (Delivery) → Customer notified of dispatch', 'order100' ),
				'recipient'   => __( 'Customer', 'order100' ),
				'trigger'     => 'app:order-ready',
				'icon'        => '🚗',
				'coming_soon' => true,
			),
			array(
				'id'        => 'customer_completed_order',
				'label'     => __( 'Order Completed', 'order100' ),
				'desc'      => __( 'Pickup/delivery done → Thank you + review invite + rewards', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:completed',
				'icon'      => '✅',
			),
			array(
				'id'        => 'customer_on_hold_order',
				'label'     => __( 'On-Hold Order', 'order100' ),
				'desc'      => __( 'Awaiting payment confirmation', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:on-hold',
				'icon'      => '⏳',
			),
			array(
				'id'        => 'cancelled_order',
				'label'     => __( 'Cancelled Order', 'order100' ),
				'desc'      => __( 'Order cancelled by customer or restaurant', 'order100' ),
				'recipient' => __( 'Admin', 'order100' ),
				'trigger'   => 'wc:cancelled',
				'icon'      => '⚠️',
			),
			array(
				'id'        => 'customer_refunded_order',
				'label'     => __( 'Refunded Order', 'order100' ),
				'desc'      => __( 'Refund processed → Customer notified', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:refunded',
				'icon'      => '💰',
			),
			array(
				'id'        => 'customer_note',
				'label'     => __( 'Customer Note', 'order100' ),
				'desc'      => __( 'Restaurant sends a note to customer', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:customer_note',
				'icon'      => '💬',
			),
			array(
				'id'        => 'customer_invoice',
				'label'     => __( 'Customer Invoice', 'order100' ),
				'desc'      => __( 'Invoice with payment link', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:customer_invoice',
				'icon'      => '🧾',
			),
			array(
				'id'        => 'failed_order',
				'label'     => __( 'Failed Order', 'order100' ),
				'desc'      => __( 'Payment failed → Admin notified', 'order100' ),
				'recipient' => __( 'Admin', 'order100' ),
				'trigger'   => 'wc:failed',
				'icon'      => '❌',
			),
			array(
				'id'        => 'customer_failed_order',
				'label'     => __( 'Failed Order (Customer)', 'order100' ),
				'desc'      => __( 'Payment failed → Customer notified', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:failed',
				'icon'      => '❌',
			),
			array(
				'id'        => 'customer_cancelled_order',
				'label'     => __( 'Cancelled Order (Customer)', 'order100' ),
				'desc'      => __( 'Order cancelled → Customer notified', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:cancelled',
				'icon'      => '⚠️',
			),
			array(
				'id'        => 'customer_reset_password',
				'label'     => __( 'Reset Password', 'order100' ),
				'desc'      => __( 'Password reset link', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:reset_password',
				'icon'      => '🔑',
			),
			array(
				'id'        => 'customer_new_account',
				'label'     => __( 'New Account', 'order100' ),
				'desc'      => __( 'Welcome email for new registrations', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:new_account',
				'icon'      => '👤',
			),
		);

		// Loyalty email templates
		$loyalty_templates = array(
			array(
				'id'        => 'o100_birthday',
				'label'     => __( 'Birthday Greeting', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'icon'      => '🎂',
			),
			array(
				'id'        => 'o100_points_update',
				'label'     => __( 'Points Update', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'icon'      => '⭐',
			),
			array(
				'id'        => 'o100_tier_upgrade',
				'label'     => __( 'Tier Upgrade', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'icon'      => '🏆',
			),
			array(
				'id'        => 'o100_coupon_issued',
				'label'     => __( 'Coupon Issued', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'icon'      => '🎫',
			),
			array(
				'id'        => 'o100_points_expiring',
				'label'     => __( 'Points Expiring', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'icon'      => '📊',
			),
			array(
				'id'        => 'o100_win_back',
				'label'     => __( 'Welcome Back', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'icon'      => '🔄',
			),
		);

		// ExFood shortcodes available in the email editor
		$shortcodes = array(
			array( 'code' => '[o100_order_type]',           'desc' => __( 'Delivery / Pickup / Dine-in', 'order100' ) ),
			array( 'code' => '[o100_order_timeslot]',       'desc' => __( 'Scheduled timeslot', 'order100' ) ),
			array( 'code' => '[o100_order_date]',           'desc' => __( 'Scheduled date', 'order100' ) ),
			array( 'code' => '[o100_prep_time]',            'desc' => __( 'Prep time (minutes, from App)', 'order100' ) ),
			array( 'code' => '[o100_estimated_ready]',      'desc' => __( 'Estimated ready time', 'order100' ) ),
			array( 'code' => '[o100_order_location]',       'desc' => __( 'Store location name', 'order100' ) ),
			array( 'code' => '[o100_delivery_notes]',       'desc' => __( 'Delivery instructions', 'order100' ) ),
			array( 'code' => '[o100_delivery_address]',     'desc' => __( 'Delivery address', 'order100' ) ),
			array( 'code' => '[o100_order_tips]',           'desc' => __( 'Tip amount (formatted)', 'order100' ) ),
			array( 'code' => '[o100_store_name]',           'desc' => __( 'Restaurant name', 'order100' ) ),
			array( 'code' => '[o100_store_phone]',          'desc' => __( 'Restaurant phone', 'order100' ) ),
			array( 'code' => '[o100_store_address]',        'desc' => __( 'Restaurant address', 'order100' ) ),
			array( 'code' => '[o100_store_hours]',          'desc' => __( 'Business hours', 'order100' ) ),
			array( 'code' => '[o100_loyalty_points]',       'desc' => __( 'Customer points balance', 'order100' ) ),
			array( 'code' => '[o100_loyalty_tier]',         'desc' => __( 'Customer loyalty tier', 'order100' ) ),
			array( 'code' => '[o100_coupon_code]',          'desc' => __( 'Coupon code', 'order100' ) ),
			array( 'code' => '[o100_coupon_expiry]',        'desc' => __( 'Coupon expiry date', 'order100' ) ),
		);
		?>
		<div class="cmb-row o100-tab-notification o100-notification-wrap">
			<div class="o100-notify-wrap">

				<!-- ═══ Sub-tab Navigation ═══ -->
				<ul class="o100-notify-subtabs">
					<li><a href="#" data-subtab="email" class="o100-subtab-active"><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Email', 'order100' ); ?></a></li>
					<li><a href="#" data-subtab="sms"><span class="dashicons dashicons-smartphone"></span> <?php esc_html_e( 'SMS', 'order100' ); ?> <span class="o100-notify-badge o100-notify-badge--coming-soon"><?php esc_html_e( 'Soon', 'order100' ); ?></span></a></li>
				</ul>

				<!-- ═══ EMAIL SUB-TAB ═══ -->
				<div class="o100-notify-subtab-content" data-subtab="email">

					<!-- Status Banner -->
					<div class="o100-notify-status-banner o100-notify-status-banner--active">
						<div class="o100-notify-status-icon">
							<span class="dashicons dashicons-yes-alt"></span>
						</div>
						<div class="o100-notify-status-text">
							<strong><?php esc_html_e( 'Email Template Engine', 'order100' ); ?></strong>
							<span><?php esc_html_e( 'Drag-and-drop email builder is active. Click Edit to customize any template.', 'order100' ); ?></span>
						</div>
						<span class="o100-notify-badge o100-notify-badge--active"><?php esc_html_e( 'Active', 'order100' ); ?></span>
					</div>

					<!-- Order Flow Emails -->
					<div class="o100-notify-section">
						<h3 class="o100-notify-section-title">
							<span class="dashicons dashicons-food"></span>
							<?php esc_html_e( 'Order Flow Emails', 'order100' ); ?>
						</h3>
						<div class="o100-notify-template-list">
							<?php foreach ( $email_templates as $tpl ) : ?>
							<div class="o100-notify-template-row">
								<div class="o100-notify-template-icon"><?php echo $tpl['icon']; ?></div>
								<div class="o100-notify-template-info">
									<span class="o100-notify-template-name"><?php echo esc_html( $tpl['label'] ); ?></span>
									<span class="o100-notify-template-recipient"><?php echo esc_html( $tpl['recipient'] ); ?></span>
									<?php if ( ! empty( $tpl['desc'] ) ) : ?>
										<span class="o100-notify-template-desc"><?php echo esc_html( $tpl['desc'] ); ?></span>
									<?php endif; ?>
								</div>
								<?php $tpl_status = $get_tpl_status( $tpl['id'] ); ?>
								<div class="o100-notify-template-status">
									<span class="o100-notify-dot o100-notify-dot--<?php echo esc_attr( $tpl_status['css'] ); ?>"></span>
									<span class="o100-notify-status-label"><?php echo esc_html( $tpl_status['label'] ); ?></span>
								</div>
								<div class="o100-notify-template-actions">
									<?php if ( ! empty( $tpl['coming_soon'] ) ) : ?>
										<span class="button o100-notify-edit-btn" style="opacity:0.5;cursor:default;pointer-events:none;">
											<span class="dashicons dashicons-clock"></span>
											<?php esc_html_e( 'Coming Soon', 'order100' ); ?>
										</span>
									<?php else : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=o100ne-settings#/editor/' . urlencode( $tpl['id'] ) ) ); ?>" class="button o100-notify-edit-btn" target="_blank">
											<span class="dashicons dashicons-edit"></span>
											<?php esc_html_e( 'Edit', 'order100' ); ?>
										</a>
									<?php endif; ?>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Loyalty & Marketing Emails -->
					<div class="o100-notify-section">
						<h3 class="o100-notify-section-title">
							<span class="dashicons dashicons-star-filled"></span>
							<?php esc_html_e( 'Loyalty & Marketing Emails', 'order100' ); ?>
						</h3>
						<div class="o100-notify-template-list">
							<?php foreach ( $loyalty_templates as $tpl ) : ?>
							<div class="o100-notify-template-row">
								<div class="o100-notify-template-icon"><?php echo $tpl['icon']; ?></div>
								<div class="o100-notify-template-info">
									<span class="o100-notify-template-name"><?php echo esc_html( $tpl['label'] ); ?></span>
									<span class="o100-notify-template-recipient"><?php echo esc_html( $tpl['recipient'] ); ?></span>
								</div>
								<?php $tpl_status = $get_tpl_status( $tpl['id'] ); ?>
								<div class="o100-notify-template-status">
									<span class="o100-notify-dot o100-notify-dot--<?php echo esc_attr( $tpl_status['css'] ); ?>"></span>
									<span class="o100-notify-status-label"><?php echo esc_html( $tpl_status['label'] ); ?></span>
								</div>
								<div class="o100-notify-template-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=o100ne-settings#/editor/' . urlencode( $tpl['id'] ) ) ); ?>" class="button o100-notify-edit-btn" target="_blank">
										<span class="dashicons dashicons-edit"></span>
										<?php esc_html_e( 'Edit', 'order100' ); ?>
									</a>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Available Shortcodes -->
					<div class="o100-notify-section">
						<h3 class="o100-notify-section-title">
							<span class="dashicons dashicons-shortcode"></span>
							<?php esc_html_e( 'ExFood Shortcodes for Email Templates', 'order100' ); ?>
						</h3>
						<p class="o100-notify-section-desc"><?php esc_html_e( 'Use these shortcodes in the drag-and-drop email editor to insert dynamic order and store data.', 'order100' ); ?></p>
						<div class="o100-notify-shortcodes-grid">
							<?php foreach ( $shortcodes as $sc ) : ?>
							<div class="o100-notify-shortcode-item">
								<code class="o100-notify-shortcode-code"><?php echo esc_html( $sc['code'] ); ?></code>
								<span class="o100-notify-shortcode-desc"><?php echo esc_html( $sc['desc'] ); ?></span>
							</div>
							<?php endforeach; ?>
						</div>
					</div>

				</div><!-- /email subtab -->

				<!-- ═══ SMS SUB-TAB ═══ -->
				<div class="o100-notify-subtab-content" data-subtab="sms" style="display:none;">

					<div class="o100-notify-section o100-notify-section--locked">
						<h3 class="o100-notify-section-title">
							<span class="dashicons dashicons-smartphone"></span>
							<?php esc_html_e( 'SMS Notifications', 'order100' ); ?>
							<span class="o100-notify-badge o100-notify-badge--coming-soon"><?php esc_html_e( 'Coming Soon', 'order100' ); ?></span>
						</h3>
						<div class="o100-notify-locked-overlay">
							<span class="dashicons dashicons-lock"></span>
							<p><?php esc_html_e( 'SMS notifications for order updates and marketing campaigns will be available in a future release. Configure SMS gateways, delivery triggers, and message templates here.', 'order100' ); ?></p>
						</div>
					</div>

				</div><!-- /sms subtab -->

			</div><!-- /o100-notify-wrap -->
		</div>
		<?php
	}

	public function render_locations_intro() {
		$is_enabled = get_option('o100_locations_status') === 'on';
		?>
		<!-- Header Toggle Element (Will be teleported by JS) -->
		<div id="o100_locations_header_toggle" style="display: none;">
			<div class="cmb-type-checkbox" style="margin: 0;">
				<label for="o100_enable_loc_dummy" class="o100-toggle-wrap" style="display: flex; align-items: center; justify-content: flex-start; width: auto; background: #f8fafc; padding: 6px 12px; border-radius: 20px; border: 1px solid #e2e8f0; cursor: pointer;">
					<input type="checkbox" id="o100_enable_loc_dummy" value="on" <?php checked( $is_enabled ); ?>>
					<span style="font-size: 14px; font-weight: 600; color: #334155; margin-left: 8px; pointer-events: none;"><?php esc_html_e( 'Locations Enabled', 'order100' ); ?></span>
				</label>
			</div>
		</div>

		<div class="o100-locations-intro-wrap" style="display: <?php echo $is_enabled ? 'none' : 'flex'; ?>; flex-direction: column; align-items: center; justify-content: center; background: #fff; padding: 60px 40px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">
			<h2 style="font-size: 24px; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 16px;"><?php esc_html_e( 'Multi-Locations', 'order100' ); ?></h2>
			<p style="font-size: 15px; color: #475569; margin-bottom: 32px; text-align: center; max-width: 600px; line-height: 1.6;"><?php esc_html_e( 'Manage multiple store locations, define separate delivery radiuses, minimum orders, and unique addresses for each branch directly from this dashboard.', 'order100' ); ?></p>
			<button type="button" class="o100-start-locations-btn" style="background: #22c55e; color: white; border: none; padding: 12px 32px; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);"><?php esc_html_e( 'Start Using Locations', 'order100' ); ?></button>
		</div>
		<style>
			.o100-start-locations-btn:hover { background: #16a34a; transform: translateY(-1px); box-shadow: 0 6px 8px -1px rgba(34, 197, 94, 0.3); }
			.o100-locations-list-container { overflow-x: auto; position: relative; }
			/* Hide the empty title row */
			.cmb2-id-o100-locations-manager-html { display: none !important; }
			.cmb2-id-o100-locations-intro { padding-bottom: 0 !important; border-bottom: none !important; }
			form.cmb-form > .submit, form.cmb-form .cmb2-save-field-row, form.cmb-form .cmb-submit, form.cmb-form > input[type="submit"], form.cmb-form input[name="submit-cmb"] { display: none !important; }
			
			<?php if ( ! $is_enabled ) : ?>
			.cmb2-id-o100-loc-icon, .o100-panel-locations-app { display: none; }
			<?php endif; ?>
			
			/* Make the Actions column sticky on the right */
			.o100-locations-list-container table th:last-child,
			.o100-locations-list-container table td:last-child {
				position: sticky;
				right: 0;
				background-color: inherit;
				z-index: 2;
				box-shadow: -2px 0 5px rgba(0,0,0,0.05);
			}
			.o100-locations-list-container table th:last-child {
				background-color: #f8fafc;
			}
		</style>
		<script>
			jQuery(document).ready(function($){
				var $fields = $('.cmb2-id-o100-loc-icon, .o100-panel-locations-app');
				
				// Portal toggle to header
				var $toggle = $('#o100_locations_header_toggle');
				$('.o100-fluent-header-actions').prepend($toggle);
				if ($('#o100_enable_loc_dummy').is(':checked')) {
					$toggle.show();
				}

				// Sync Dummy to Real
				$('#o100_enable_loc_dummy').on('change', function() {
					var isChecked = $(this).is(':checked');
					
					// Auto save via AJAX instead of submitting the page
					var $btn = $('.o100-fluent-top-save');
					$btn.addClass('o100-saving').text('Saving...').css('opacity', '0.7');
					$.post(ajaxurl, {
						action: 'o100_toggle_locations',
						status: isChecked ? 'on' : ''
					}, function(res) {
						$btn.removeClass('o100-saving').text('Save Settings').css('opacity', '1');
					});
				});
				
				$('.o100-start-locations-btn').on('click', function(e){
					e.preventDefault();
					$('.o100-locations-intro-wrap').slideUp(200);
					$fields.slideDown(200);
					$('#o100_enable_loc_dummy').prop('checked', true);
					$toggle.fadeIn(200);
					
					// Instantly save state to database via auto save
					$('#o100_enable_loc_dummy').trigger('change');
				});
				
				// We removed the listener for the old hidden CMB2 checkbox, since it no longer exists.
			});
		</script>
		<?php
	}

	/**
	 * Render the Fluent-style Locations Manager UI
	 */
	public function render_fluent_locations_manager() {
		// Basic scaffold for the React/Vue/AJAX powered Fluent Modal UI
		if ( class_exists( 'O100_CMB2_Field_OpenClose' ) ) {
			O100_CMB2_Field_OpenClose::force_enqueue_assets();
		}
		?>
		<div class="o100-panel-locations-app" style="margin-top:20px; padding:20px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; width:100%; box-sizing:border-box;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px;">
				<h3 style="margin:0; font-size:16px; color:#0f172a;"><?php esc_html_e( 'Managed Locations', 'order100' ); ?></h3>
				<button type="button" class="button button-primary o100-add-location-btn" style="background:#2563eb; border-color:#2563eb; box-shadow:none;">
					<span class="dashicons dashicons-plus-alt2" style="line-height:inherit; margin-right:4px;"></span>
					<?php esc_html_e( 'Create Location', 'order100' ); ?>
				</button>
			</div>
			
			<!-- Table / List Placeholder -->
			<div class="o100-locations-list-container">
				<p style="color:#64748b; font-size:14px; text-align:center; padding: 40px 0; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1;">
					<?php esc_html_e( 'Loading locations...', 'order100' ); ?>
				</p>
			</div>

			<!-- Fluent Style Modal (Hidden by default) -->
			<div class="o100-location-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; justify-content:center; align-items:center;">
				<div class="o100-location-modal-content" style="background:#fff; width:100%; max-width:850px; border-radius:8px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); display:flex; flex-direction:column; max-height:90vh;">
					<!-- Modal Header -->
					<div style="padding:15px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
						<h3 style="margin:0; font-size:16px; font-weight:600; color:#0f172a;" id="o100-loc-modal-title"><?php esc_html_e( 'Manage Location', 'order100' ); ?></h3>
						<span class="dashicons dashicons-no-alt o100-close-modal" style="cursor:pointer; color:#64748b;"></span>
					</div>
					<!-- Modal Tabs -->
					<div style="display:flex; border-bottom:1px solid #e2e8f0; background:#f8fafc; padding:0 20px;">
						<a href="#" class="o100-loc-tab active" data-tab="general" style="padding:12px 16px; font-size:14px; font-weight:500; color:#2563eb; border-bottom:2px solid #2563eb; text-decoration:none; margin-bottom:-1px;">📍 Profile</a>
						<a href="#" class="o100-loc-tab" data-tab="fulfillment" style="padding:12px 16px; font-size:14px; font-weight:500; color:#64748b; border-bottom:2px solid transparent; text-decoration:none; margin-bottom:-1px;">🛒 Fulfillment</a>
						<a href="#" class="o100-loc-tab" data-tab="visibility" style="padding:12px 16px; font-size:14px; font-weight:500; color:#64748b; border-bottom:2px solid transparent; text-decoration:none; margin-bottom:-1px;">🍔 Menu Visibility</a>
						<a href="#" class="o100-loc-tab" data-tab="hours" style="padding:12px 16px; font-size:14px; font-weight:500; color:#64748b; border-bottom:2px solid transparent; text-decoration:none; margin-bottom:-1px;">🕒 Operating Hours</a>
					</div>
					<!-- Modal Body (Scrollable) -->
					<div style="padding:20px; overflow-y:auto; flex-grow:1; background:#fbfbfb;">
						<div id="o100-loc-tab-general" class="o100-loc-tab-content">
						<style>
							.o100-modal-field { margin-bottom: 20px; }
							.o100-modal-field-header { display: flex; align-items: center; margin-bottom: 8px; }
							.o100-modal-field label { font-weight: 600; color: #1e293b; font-size: 13px; margin: 0; }
							.o100-help-icon { color: #94a3b8; cursor: pointer; margin-left: 8px; font-size: 16px; width: 16px; height: 16px; transition: color 0.2s; }
							.o100-help-icon:hover { color: #3b82f6; }
							.o100-help-text { display: none; margin-top: 6px; margin-bottom: 10px; padding: 10px; background: #eff6ff; color: #1d4ed8; font-size: 12px; border-radius: 4px; border-left: 3px solid #3b82f6; line-height: 1.4; }
							.o100-modal-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 12px; font-size: 13px; color: #334155; transition: border-color 0.2s; }
							.o100-modal-input:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 1px #3b82f6; }
							.o100-modal-row { display: flex; gap: 15px; margin-bottom: 24px; }
							.o100-modal-row:last-child { margin-bottom: 0; }
							.o100-modal-col { flex: 1; }
							.o100-cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; margin-top: 10px; }
							.o100-cat-item { display: flex; align-items: center; font-size: 13px; color: #475569; }
							.o100-cat-item input { margin: 0 8px 0 0; }
							.o100-modal-field .select2-container { padding: 0 !important; background: transparent !important; border: none !important; box-shadow: none !important; }
							.o100-modal-field .select2-container--default .select2-selection--multiple { border: 1px solid #cbd5e1 !important; border-radius: 4px !important; min-height: 36px !important; }
							.o100-modal-field .select2-container--default.select2-container--focus .select2-selection--multiple { border-color: #3b82f6 !important; box-shadow: 0 0 0 1px #3b82f6 !important; }
						</style>

						<?php
						$fields = array(
							'name' => array(
								'label' => __( 'Location Name', 'order100' ),
								'help'  => __( 'Enter a clear name for this location (e.g., Downtown Branch). This will be displayed to customers during checkout.', 'order100' ),
								'type'  => 'text',
								'id'    => 'o100_loc_name',
								'placeholder' => 'e.g. Downtown Branch'
							),
							'address' => array(
								'label' => __( 'Location Address', 'order100' ),
								'help'  => __( 'Enter the complete physical address. This is used to accurately calculate delivery distances and shipping costs.', 'order100' ),
								'type'  => 'text',
								'id'    => 'o100_loc_address',
								'class' => 'o100-modal-address-input',
							),
							'latlng' => array(
								'label' => __( 'Latitude and Longitude', 'order100' ),
								'help'  => __( 'Optional. Enter coordinates (e.g., 51.507,-0.127) to override automatic geocoding for pinpoint delivery accuracy.', 'order100' ),
								'type'  => 'text',
								'id'    => 'o100_loc_latlng',
								'placeholder' => 'e.g. 51.507351,-0.127758'
							)
						);

						foreach ( $fields as $id => $field ) {
							?>
							<div class="o100-modal-field">
								<div class="o100-modal-field-header">
									<label><?php echo esc_html( $field['label'] ); ?></label>
									<span class="dashicons dashicons-editor-help o100-help-icon" title="<?php esc_attr_e('Click for help', 'order100'); ?>"></span>
								</div>
								<div class="o100-help-text"><?php echo esc_html( $field['help'] ); ?></div>
								<input type="<?php echo esc_attr( $field['type'] ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>" class="o100-modal-input <?php echo isset($field['class']) ? esc_attr($field['class']) : ''; ?>" placeholder="<?php echo isset($field['placeholder']) ? esc_attr($field['placeholder']) : ''; ?>">
							</div>
							<?php
						}
						?>

						<div class="o100-modal-row">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Location Phone', 'order100' ); ?></label>
									<span class="dashicons dashicons-editor-help o100-help-icon" title="<?php esc_attr_e('Click for help', 'order100'); ?>"></span>
								</div>
								<div class="o100-help-text"><?php esc_html_e( 'Enter the phone number for this location.', 'order100' ); ?></div>
								<div style="display:flex; gap:10px;">
									<select id="o100_loc_phone_code" class="o100-modal-input" style="width: 100px !important; flex: 0 0 100px !important; padding-right: 8px;">
										<?php
										$country_codes = array(
											'+1' => '🇺🇸/🇨🇦 +1', '+44' => '🇬🇧 +44', '+86' => '🇨🇳 +86', '+61' => '🇦🇺 +61', '+81' => '🇯🇵 +81', '+33' => '🇫🇷 +33', 
											'+49' => '🇩🇪 +49', '+39' => '🇮🇹 +39', '+34' => '🇪🇸 +34', '+7' => '🇷🇺/🇰🇿 +7', '+55' => '🇧🇷 +55', '+91' => '🇮🇳 +91', 
											'+82' => '🇰🇷 +82', '+52' => '🇲🇽 +52', '+62' => '🇮🇩 +62', '+60' => '🇲🇾 +60', '+65' => '🇸🇬 +65', '+63' => '🇵🇭 +63', 
											'+66' => '🇹🇭 +66', '+84' => '🇻🇳 +84', '+971' => '🇦🇪 +971', '+966' => '🇸🇦 +966', '+27' => '🇿🇦 +27', '+20' => '🇪🇬 +20', 
											'+234' => '🇳🇬 +234', '+54' => '🇦🇷 +54', '+56' => '🇨🇱 +56', '+57' => '🇨🇴 +57', '+51' => '🇵🇪 +51', '+31' => '🇳🇱 +31', 
											'+32' => '🇧🇪 +32', '+41' => '🇨🇭 +41', '+43' => '🇦🇹 +43', '+46' => '🇸🇪 +46', '+47' => '🇳🇴 +47', '+45' => '🇩🇰 +45', 
											'+358' => '🇫🇮 +358', '+48' => '🇵🇱 +48', '+420' => '🇨🇿 +420', '+36' => '🇭🇺 +36', '+30' => '🇬🇷 +30', '+351' => '🇵🇹 +351', 
											'+353' => '🇮🇪 +353', '+64' => '🇳🇿 +64', '+852' => '🇭🇰 +852', '+886' => '🇹🇼 +886', '+853' => '🇲🇴 +853', '+90' => '🇹🇷 +90'
										);
										foreach ($country_codes as $code => $label) {
											echo '<option value="'.esc_attr($code).'">'.esc_html($label).'</option>';
										}
										?>
									</select>
									<input type="tel" id="o100_loc_phone" class="o100-modal-input" style="flex:1;" pattern="^\d+$" title="Please enter numbers only">
								</div>
							</div>
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Email Recipients', 'order100' ); ?></label>
									<span class="dashicons dashicons-editor-help o100-help-icon" title="<?php esc_attr_e('Click for help', 'order100'); ?>"></span>
								</div>
								<div class="o100-help-text"><?php esc_html_e( 'Comma-separated list of email addresses that should receive order notifications specifically for this branch.', 'order100' ); ?></div>
								<input type="text" id="o100_loc_emails" class="o100-modal-input" placeholder="manager1@store.com, manager2@store.com">
							</div>
						</div>

						</div> <!-- End General Tab -->

						<!-- FULFILLMENT TAB CONTENT -->
						<div id="o100-loc-tab-fulfillment" class="o100-loc-tab-content" style="display:none;">
							<div class="o100-modal-field">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Minimum Order Amount', 'order100' ); ?></label>
									<span class="dashicons dashicons-editor-help o100-help-icon"></span>
								</div>
								<div class="o100-help-text"><?php esc_html_e( 'The cart subtotal must reach this amount before a customer can place an order at this location.', 'order100' ); ?></div>
								<div class="o100-flex-input-wrap has-prefix">
									<span class="o100-flex-prefix"><?php echo get_woocommerce_currency_symbol(); ?></span>
									<input type="number" step="0.01" id="o100_loc_min_order" class="o100-modal-input" placeholder="0.00">
								</div>
							</div>

							<div class="o100-settings-group" style="border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 15px; overflow: hidden; background: #fff;">
								<!-- Pickup Settings -->
								<div class="o100-settings-row" style="padding: 18px 20px; display: flex; justify-content: space-between; align-items: center;">
									<div class="o100-settings-text" style="padding-right: 20px; flex: 1;">
										<div style="font-weight: 600; color: #1e293b; font-size: 14px; margin-bottom: 6px;"><?php esc_html_e( 'Enable Pickup', 'order100' ); ?></div>
										<div style="font-size: 13px; color: #64748b; line-height: 1.4;"><?php esc_html_e( 'Allow customers to pick up orders directly from this branch.', 'order100' ); ?></div>
									</div>
									<div class="o100-settings-action">
										<label class="o100-switch">
											<input type="checkbox" id="o100_loc_enable_pickup" value="yes" checked>
											<span class="o100-slider"></span>
										</label>
									</div>
								</div>
							</div>

							<div class="o100-settings-group" style="border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px; overflow: hidden; background: #fff;">
								<!-- Delivery Settings -->
								<div class="o100-settings-row" style="padding: 18px 20px; display: flex; justify-content: space-between; align-items: center;">
									<div class="o100-settings-text" style="padding-right: 20px; flex: 1;">
										<div style="font-weight: 600; color: #1e293b; font-size: 14px; margin-bottom: 6px;"><?php esc_html_e( 'Enable Delivery', 'order100' ); ?></div>
										<div style="font-size: 13px; color: #64748b; line-height: 1.4;"><?php esc_html_e( 'Provide delivery services from this branch.', 'order100' ); ?></div>
									</div>
									<div class="o100-settings-action">
										<label class="o100-switch">
											<input type="checkbox" id="o100_loc_enable_delivery" value="yes" checked>
											<span class="o100-slider"></span>
										</label>
									</div>
								</div>

								<div class="o100-loc-delivery-options" style="padding: 20px; border-top: 1px solid #e2e8f0; background: #fbfbfb;">
									<div class="o100-modal-field">
										<div class="o100-modal-field-header">
											<label><?php esc_html_e( 'Distance restrict (km)', 'order100' ); ?></label>
											<span class="dashicons dashicons-editor-help o100-help-icon"></span>
										</div>
										<div class="o100-help-text"><?php esc_html_e( 'Maximum delivery radius from this location in kilometers. Customers outside this range cannot order for delivery from this branch.', 'order100' ); ?></div>
										<div class="o100-flex-input-wrap has-suffix">
											<input type="number" step="0.1" id="o100_loc_distance" class="o100-modal-input" placeholder="e.g. 10">
											<span class="o100-flex-suffix">km</span>
										</div>
									</div>

									<div class="o100-modal-field" style="margin-bottom: 0;">
										<div class="o100-modal-field-header">
											<label><?php esc_html_e( 'Delivery Fee Adjustment', 'order100' ); ?></label>
											<span class="dashicons dashicons-editor-help o100-help-icon"></span>
										</div>
										<div class="o100-help-text"><?php esc_html_e( 'Adjust the calculated global delivery fee specifically for this branch.', 'order100' ); ?></div>
										<div style="display:flex; gap:10px; align-items:center;">
											<select id="o100_loc_fee_action" class="o100-modal-input" style="width: 180px;">
												<option value="none"><?php esc_html_e( 'No Adjustment', 'order100' ); ?></option>
												<option value="add"><?php esc_html_e( 'Add Surcharge', 'order100' ); ?></option>
												<option value="subtract"><?php esc_html_e( 'Give Discount', 'order100' ); ?></option>
											</select>
											<select id="o100_loc_fee_type" class="o100-modal-input o100-loc-fee-fields" style="width: 160px; display:none;">
												<option value="percent"><?php esc_html_e( 'Percentage (%)', 'order100' ); ?></option>
												<option value="fixed"><?php esc_html_e( 'Fixed Amount ($)', 'order100' ); ?></option>
											</select>
											<div class="o100-flex-input-wrap o100-loc-fee-fields has-suffix" style="width: 120px; display:none;" id="o100_loc_fee_val_wrap">
												<span class="o100-flex-prefix" style="display:none;"><?php echo get_woocommerce_currency_symbol(); ?></span>
												<input type="number" step="0.01" id="o100_loc_fee_val" class="o100-modal-input" placeholder="0.00">
												<span class="o100-flex-suffix">%</span>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div> <!-- End Fulfillment Tab -->

						<!-- MENU VISIBILITY TAB CONTENT -->
						<div id="o100-loc-tab-visibility" class="o100-loc-tab-content" style="display:none;">
							<div class="o100-modal-field" style="width: 100%;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Hide Categories', 'order100' ); ?></label>
									<span class="dashicons dashicons-editor-help o100-help-icon"></span>
								</div>
								<div class="o100-help-text"><?php esc_html_e( 'Select entire categories to hide from this location.', 'order100' ); ?></div>
								<select id="o100_loc_hidden_cats" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Select categories...', 'order100' ); ?>">
									<?php
									$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
									if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
										foreach ( $cats as $cat ) {
											echo '<option value="' . esc_attr( $cat->term_id ) . '">' . esc_html( $cat->name ) . '</option>';
										}
									}
									?>
								</select>
							</div>

							<div class="o100-modal-field" style="width: 100%;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Hide Specific Products', 'order100' ); ?></label>
									<span class="dashicons dashicons-editor-help o100-help-icon"></span>
								</div>
								<div class="o100-help-text"><?php esc_html_e( 'Select specific products to hide from this location. They will be removed from the menu when this location is selected.', 'order100' ); ?></div>
								<select id="o100_loc_hidden_products" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for products...', 'order100' ); ?>"></select>
							</div>
						</div> <!-- End Menu Visibility Tab -->

						<!-- HOURS TAB CONTENT -->
						<div id="o100-loc-tab-hours" class="o100-loc-tab-content" style="display:none;">
							<div class="o100-settings-group" style="border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 15px; overflow: hidden; background: #fff;">
								<!-- Row 1: Pause Online Ordering -->
								<div class="o100-settings-row" style="padding: 18px 20px; display: flex; justify-content: space-between; align-items: center;">
									<div class="o100-settings-text" style="padding-right: 20px; flex: 1;">
										<div style="font-weight: 600; color: #1e293b; font-size: 14px; margin-bottom: 6px;"><?php esc_html_e( 'Pause Online Ordering', 'order100' ); ?></div>
										<div style="font-size: 13px; color: #64748b; line-height: 1.4;"><?php esc_html_e( 'Toggle on to forcefully stop accepting orders for this branch, regardless of the time schedule.', 'order100' ); ?></div>
									</div>
									<div class="o100-settings-action">
										<label class="o100-switch">
											<input type="checkbox" id="o100_loc_closed" value="yes">
											<span class="o100-slider"></span>
										</label>
									</div>
								</div>
							</div>

							<div class="o100-settings-group" style="border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px; overflow: hidden; background: #fff;">
								<!-- Row 2: Override Global Hours -->
								<div class="o100-settings-row" style="padding: 18px 20px; display: flex; justify-content: space-between; align-items: center;">
									<div class="o100-settings-text" style="padding-right: 20px; flex: 1;">
										<div style="font-weight: 600; color: #1e293b; font-size: 14px; margin-bottom: 6px;"><?php esc_html_e( 'Override Global Hours', 'order100' ); ?></div>
										<div style="font-size: 13px; color: #64748b; line-height: 1.4;"><?php esc_html_e( 'If enabled, this branch will use its own schedule below instead of the global store hours.', 'order100' ); ?></div>
									</div>
									<div class="o100-settings-action">
										<label class="o100-switch">
											<input type="checkbox" id="o100_loc_override_hours" value="yes">
											<span class="o100-slider"></span>
										</label>
									</div>
								</div>
							</div>

								<div class="o100-loc-hours-wrap" style="display: none; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;">
									<h4 style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600;"><?php esc_html_e( 'Branch Operating Hours', 'order100' ); ?></h4>
									<div class="o100-help-text" style="display:block; margin-top:0; margin-bottom:20px;"><?php esc_html_e( 'Configure the active time slots for each day. Then, generate timeslots to define interval steps and limits.', 'order100' ); ?></div>

									<div style="display: flex; gap: 20px; margin-bottom: 20px;">
										<div style="flex: 1;">
											<label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px;"><?php esc_html_e( 'Time Interval (Minutes)', 'order100' ); ?></label>
											<div class="o100-flex-input-wrap has-suffix">
												<input type="number" id="o100_loc_interval" class="o100-modal-input" value="30" min="5" step="5">
												<span class="o100-flex-suffix"><?php esc_html_e( 'minutes', 'order100' ); ?></span>
											</div>
										</div>
										<div style="flex: 1;">
											<label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px;"><?php esc_html_e( 'Default Max Orders', 'order100' ); ?></label>
											<div class="o100-flex-input-wrap has-suffix">
												<input type="number" id="o100_loc_max_order" class="o100-modal-input" value="4" min="1">
												<span class="o100-flex-suffix"><?php esc_html_e( 'orders', 'order100' ); ?></span>
											</div>
										</div>
									</div>
									
									<div id="o100-loc-hours-grid">
										<!-- Injected via JS -->
									</div>

									<div style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed #cbd5e1;">
										<button type="button" id="o100_loc_generate_slots_btn" class="button button-primary" style="width: 100%; padding: 10px 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px;">
											<span class="dashicons dashicons-clock" style="font-size: 18px;"></span>
											<?php esc_html_e( 'Generate Timeslots', 'order100' ); ?>
										</button>
									</div>

									<div id="o100_loc_generated_slots_display" style="margin-top: 20px;"></div>
									<input type="hidden" id="o100_loc_generated_slots_data" value="{}">
								</div>
						</div> <!-- End Hours Tab -->

						<input type="hidden" id="o100_loc_term_id" value="">
					</div>
					<!-- Modal Footer -->
					<div style="padding:15px 20px; border-top:1px solid #e2e8f0; background:#f8fafc; border-radius:0 0 8px 8px; display:flex; justify-content:flex-end;">
						<button type="button" class="button button-primary o100-save-location-action" style="background:#3b82f6; border-color:#3b82f6; padding:0 20px; height:36px;">
							<?php esc_html_e( 'Save Location', 'order100' ); ?>
						</button>
					</div>
			</div>
		</div>

		<!-- Delete Confirmation Modal -->
		<div id="o100-delete-confirm-modal" class="o100-locations-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:100000; justify-content:center; align-items:center;">
			<div style="background:#fff; width:400px; border-radius:12px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); overflow:hidden;">
				<div style="padding:24px; text-align:center;">
					<div style="width:48px; height:48px; border-radius:50%; background:#fee2e2; color:#ef4444; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
						<span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px;"></span>
					</div>
					<h3 style="margin:0 0 8px; font-size:18px; color:#0f172a; font-weight:600;"><?php esc_html_e( 'Delete Location', 'order100' ); ?></h3>
					<p style="margin:0; font-size:14px; color:#64748b;"><?php esc_html_e( 'Are you sure you want to delete this location? This action cannot be undone.', 'order100' ); ?></p>
				</div>
				<div style="background:#f8fafc; padding:16px 24px; display:flex; justify-content:flex-end; gap:12px; border-top:1px solid #e2e8f0;">
					<button type="button" id="o100-cancel-delete" style="background:#fff; border:1px solid #cbd5e1; padding:8px 16px; border-radius:6px; font-weight:500; color:#475569; cursor:pointer;"><?php esc_html_e( 'Cancel', 'order100' ); ?></button>
					<button type="button" id="o100-confirm-delete" style="background:#ef4444; border:none; padding:8px 16px; border-radius:6px; font-weight:500; color:#fff; cursor:pointer; box-shadow:0 1px 2px rgba(0,0,0,0.05);"><?php esc_html_e( 'Delete', 'order100' ); ?></button>
				</div>
			</div>
		</div>
		<script>
		jQuery(document).ready(function($) {
			function loadLocations() {
				$.post(ajaxurl, { action: 'o100_get_locations' }, function(response) {
					if (response.success) {
						var locs = response.data.locations || response.data;
						window.o100_locations_data = locs;
						window.o100_global_config = response.data.global_config || { interval: 30, max_order: 4, hours: {} };
						
						var html = '';
						if (locs.length === 0) {
							html = '<p style="color:#64748b; font-size:14px; text-align:center; padding: 40px 0; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1;"><?php esc_html_e( 'No locations found. Create one above.', 'order100' ); ?></p>';
						} else {
							html = '<div class="o100-locations-card-list" style="display:flex; flex-direction:column; gap:12px;">';
							$.each(locs, function(i, loc) {
								var locRadius = loc.distance ? loc.distance + ' km' : '-';
								
								// Calculate Fee display
								var locFee = 'Global';
								if (loc.fee_action === 'add') {
									locFee = '+' + (loc.fee_type === 'percent' ? loc.fee_val + '%' : '$' + loc.fee_val);
								} else if (loc.fee_action === 'subtract') {
									locFee = '-' + (loc.fee_type === 'percent' ? loc.fee_val + '%' : '$' + loc.fee_val);
								}

								var statusText = (loc.closed === 'yes') ? 'Closed' : 'Active';
								var statusColor = (loc.closed === 'yes') ? '#ef4444' : '#10b981';
								var statusBg = (loc.closed === 'yes') ? '#fef2f2' : '#ecfdf5';

								var loc_del = loc.enable_delivery || window.o100_global_config.enable_delivery || 'yes';
								var loc_pic = loc.enable_pickup || window.o100_global_config.enable_pickup || 'yes';
								var methodChips = '';
								if (loc_del === 'yes') methodChips += '<span style="background:#e0f2fe; color:#0284c7; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:500; margin-right:4px;">Delivery</span>';
								if (loc_pic === 'yes') methodChips += '<span style="background:#fef3c7; color:#d97706; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:500;">Pickup</span>';
								if (methodChips === '') methodChips = '<span style="color:#9ca3af; font-size:11px;">No Methods</span>';

								html += '<div class="o100-loc-list-row" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 1px 2px rgba(0,0,0,0.02); transition:all 0.2s ease;" onmouseover="this.style.borderColor=\'#cbd5e1\';this.style.boxShadow=\'0 4px 6px -1px rgba(0,0,0,0.05)\'" onmouseout="this.style.borderColor=\'#e5e7eb\';this.style.boxShadow=\'0 1px 2px rgba(0,0,0,0.02)\'">';
								html += '	<div style="flex:1; min-width:0; padding-right:24px;">';
								html += '		<div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">';
								html += '			<h4 style="margin:0; font-size:15px; color:#111827; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + loc.name + '</h4>';
								html += '			<span style="background:' + statusBg + '; color:' + statusColor + '; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600;">' + statusText + '</span>';
								html += '		</div>';
								html += '		<p style="margin:0 0 6px 0; font-size:13px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + (loc.address || '<?php echo esc_js( __( 'No address specified', 'order100' ) ); ?>') + '</p>';
								html += '		<div style="display:flex; align-items:center;">' + methodChips + '</div>';
								html += '	</div>';
								html += '	<div style="flex:0 0 auto; display:flex; align-items:center; gap:32px;">';
								html += '		<div style="text-align:right;">';
								html += '			<div style="font-size:12px; color:#9ca3af; font-weight:500; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;"><?php echo esc_js( __( 'Radius', 'order100' ) ); ?></div>';
								html += '			<div style="font-size:14px; color:#374151; font-weight:600;">' + locRadius + '</div>';
								html += '		</div>';
								html += '		<div style="text-align:right;">';
								html += '			<div style="font-size:12px; color:#9ca3af; font-weight:500; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;"><?php echo esc_js( __( 'Fee', 'order100' ) ); ?></div>';
								html += '			<div style="font-size:14px; color:#374151; font-weight:600;">' + locFee + '</div>';
								html += '		</div>';
								html += '		<div style="display:flex; align-items:center; gap:8px; border-left:1px solid #e5e7eb; padding-left:24px; margin-left:8px;">';
								html += '			<button type="button" class="o100-edit-loc" data-id="' + loc.id + '" style="background:none; border:none; padding:6px; cursor:pointer; color:#6366f1; border-radius:6px; display:flex; align-items:center; justify-content:center; transition:background 0.15s ease;" onmouseover="this.style.background=\'#e0e7ff\'" onmouseout="this.style.background=\'none\'" title="<?php echo esc_js( __( 'Edit Location', 'order100' ) ); ?>"><span class="dashicons dashicons-edit"></span></button>';
								html += '			<button type="button" class="o100-delete-loc" data-id="' + loc.id + '" style="background:none; border:none; padding:6px; cursor:pointer; color:#ef4444; border-radius:6px; display:flex; align-items:center; justify-content:center; transition:background 0.15s ease;" onmouseover="this.style.background=\'#fee2e2\'" onmouseout="this.style.background=\'none\'" title="<?php echo esc_js( __( 'Delete Location', 'order100' ) ); ?>"><span class="dashicons dashicons-trash"></span></button>';
								html += '		</div>';
								html += '	</div>';
								html += '</div>';
							});
							html += '</div>';
						}
						
						$('.o100-locations-list-container').html(html);

						// If no locations, uncheck the master toggle and show the intro
						if (locs.length === 0 && window.o100_just_deleted_last) {
							window.o100_just_deleted_last = false;
							$.post(ajaxurl, { action: 'o100_toggle_locations', status: 'off' });
						}
					}
				});
			}

			loadLocations();

			function o100_init_custom_select2() {
				var selectFn = $.fn.selectWoo ? 'selectWoo' : ($.fn.select2 ? 'select2' : null);
				if (!selectFn) return;

				// Init Category Select2
				$('#o100_loc_hidden_cats')[selectFn]({
					placeholder: $('#o100_loc_hidden_cats').data('placeholder'),
					closeOnSelect: false,
					templateResult: function(result) {
						if (!result.id) return result.text;
						var selectedIds = $('#o100_loc_hidden_cats').val() || [];
						var isSelected = (selectedIds.indexOf(result.id.toString()) !== -1 || selectedIds.indexOf(result.id) !== -1);
						
						if (isSelected) {
							setTimeout(function() {
								$('.o100-select2-item[data-id="'+result.id+'"]').closest('li').addClass('o100-disabled-li');
							}, 0);
						}
						return jQuery('<div class="o100-select2-item" data-id="' + result.id + '" style="display:flex; align-items:center;"><span class="o100-custom-cb"></span><span>' + result.text + '</span></div>');
					},
					templateSelection: function(result) {
						return result.text;
					}
				}).on('select2:select select2:unselect', function(e) {
					var id = e.params.data.id;
					var $li = $('.o100-select2-item[data-id="'+id+'"]').closest('li');
					if (e.type === 'select2:select') {
						$li.addClass('o100-disabled-li');
					} else {
						$li.removeClass('o100-disabled-li');
					}
				});

				// Init Product Select2
				$('#o100_loc_hidden_products')[selectFn]({
					ajax: {
						url: ajaxurl,
						dataType: 'json',
						delay: 250,
						data: function (params) {
							return {
								term: params.term,
								action: 'woocommerce_json_search_products_and_variations',
								security: (typeof wc_enhanced_select_params !== 'undefined') ? wc_enhanced_select_params.search_products_nonce : ''
							};
						},
						processResults: function (data) {
							var terms = [];
							if (data) {
								$.each(data, function(id, text) {
									terms.push({ id: id, text: text });
								});
							}
							return { results: terms };
						},
						cache: true
					},
					minimumInputLength: 3,
					placeholder: $('#o100_loc_hidden_products').data('placeholder'),
					closeOnSelect: false,
					templateResult: function(result) {
						if (result.loading) return result.text;
						var selectedIds = $('#o100_loc_hidden_products').val() || [];
						var isSelected = (selectedIds.indexOf(result.id.toString()) !== -1 || selectedIds.indexOf(result.id) !== -1);
						
						if (isSelected) {
							setTimeout(function() {
								$('.o100-select2-item[data-id="'+result.id+'"]').closest('li').addClass('o100-disabled-li');
							}, 0);
						}
						return jQuery('<div class="o100-select2-item" data-id="' + result.id + '" style="display:flex; align-items:center;"><span class="o100-custom-cb"></span><span>' + result.text + '</span></div>');
					},
					templateSelection: function(result) {
						return result.text;
					}
				}).on('select2:select select2:unselect', function(e) {
					var id = e.params.data.id;
					var $li = $('.o100-select2-item[data-id="'+id+'"]').closest('li');
					if (e.type === 'select2:select') {
						$li.addClass('o100-disabled-li');
					} else {
						$li.removeClass('o100-disabled-li');
					}
				});
			}

			// Fee Adjustment Logic
			$(document).on('change', '#o100_loc_fee_action', function() {
				if ($(this).val() === 'none') {
					$('.o100-loc-fee-fields').hide();
					$('#o100_loc_fee_val').val('');
				} else {
					$('.o100-loc-fee-fields').show();
				}
			});

			$(document).on('change', '#o100_loc_enable_delivery', function() {
				if ($(this).is(':checked')) {
					$('.o100-loc-delivery-options').show();
				} else {
					$('.o100-loc-delivery-options').hide();
					$('#o100_loc_distance').val('');
					$('#o100_loc_fee_action').val('none').trigger('change');
				}
			});

			$(document).on('change', '#o100_loc_fee_type', function() {
				var type = $(this).val();
				var $wrap = $('#o100_loc_fee_val_wrap');
				if (type === 'percent') {
					$wrap.removeClass('has-prefix').addClass('has-suffix');
					$wrap.find('.o100-flex-prefix').hide();
					$wrap.find('.o100-flex-suffix').css('display', 'flex');
				} else {
					$wrap.removeClass('has-suffix').addClass('has-prefix');
					$wrap.find('.o100-flex-suffix').hide();
					$wrap.find('.o100-flex-prefix').css('display', 'flex');
				}
			});

			$(document).on('change', '#o100_loc_override_hours', function() {
				if ($(this).is(':checked')) {
					$('.o100-loc-hours-wrap').slideDown(200);
					// Auto populate with global defaults if completely empty
					var hasAnySlots = false;
					$('#o100-loc-hours-grid .o100-slots-container').each(function() {
						if ($(this).children().length > 0) hasAnySlots = true;
					});
					if (!hasAnySlots) {
						var glConf = window.o100_global_config || {};
						if (glConf.hours && Object.keys(glConf.hours).length > 0) {
							o100_populate_hours_grid(glConf.hours);
						}
					}
				} else {
					$('.o100-loc-hours-wrap').slideUp(200);
					// Uncheck all days when disabled
					$('#o100-loc-hours-grid .o100-day-toggle').prop('checked', false).trigger('change');
				}
			});

			o100_init_custom_select2();

			// Generate Hours Grid UI (Unified with Global Schedule)
			var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
			var dayLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
			var hoursHtml = '';
			$.each(days, function(index, day) {
				hoursHtml += '<div class="o100-custom-schedule-wrapper o100-modal-day-wrapper" data-day="' + day + '" style="margin-bottom: 0; border-bottom: 1px solid #f1f5f9;">';
				
				hoursHtml += '<div class="o100-schedule-left">';
				hoursHtml += '<label class="o100-switch">';
				hoursHtml += '<input type="checkbox" class="o100-day-toggle">';
				hoursHtml += '<span class="o100-slider"></span>';
				hoursHtml += '</label>';
				hoursHtml += '<span class="o100-day-name">' + dayLabels[index] + '</span>';
				hoursHtml += '</div>';

				hoursHtml += '<div class="o100-schedule-right">';
				hoursHtml += '<div class="o100-closed-pill" style="display:inline-flex;">Closed</div>';
				hoursHtml += '<div class="o100-slots-container" style="display:none;"></div>';
				hoursHtml += '<div class="o100-add-slot-wrap" style="display:none;">';
				hoursHtml += '<button type="button" class="o100-add-slot-btn"><span class="dashicons dashicons-plus"></span> Add Slot</button>';
				hoursHtml += '</div>';
				hoursHtml += '</div>';
				
				hoursHtml += '</div>';
			});
			$('#o100-loc-hours-grid').html(hoursHtml);

			// Logic to add a time slot
			$(document).on('click', '#o100-loc-hours-grid .o100-add-slot-btn', function(e) {
				e.preventDefault();
				var $container = $(this).closest('.o100-schedule-right').find('.o100-slots-container');
				
				// Generate hours/mins dropdown options
				var hrsHtml = '<option value="">--</option>';
				for(var i=0; i<24; i++) {
					var v = (i<10?'0':'')+i;
					hrsHtml += '<option value="'+v+'">'+v+'</option>';
				}
				var minsHtml = '<option value="">--</option><option value="00">00</option><option value="15">15</option><option value="30">30</option><option value="45">45</option>';

				var rowHtml = '<div class="o100-time-slot-row o100-modal-time-row">';
				rowHtml += '<div class="o100-time-split">';
				rowHtml += '<select class="o100-time-select op-hr start-hr">' + hrsHtml.replace('value="10"', 'value="10" selected') + '</select>';
				rowHtml += '<span class="o100-split-colon">:</span>';
				rowHtml += '<select class="o100-time-select op-min start-min">' + minsHtml.replace('value="00"', 'value="00" selected') + '</select>';
				rowHtml += '</div>';
				rowHtml += '<span class="o100-time-separator">to</span>';
				rowHtml += '<div class="o100-time-split">';
				rowHtml += '<select class="o100-time-select op-hr end-hr">' + hrsHtml.replace('value="21"', 'value="21" selected') + '</select>';
				rowHtml += '<span class="o100-split-colon">:</span>';
				rowHtml += '<select class="o100-time-select op-min end-min">' + minsHtml.replace('value="00"', 'value="00" selected') + '</select>';
				rowHtml += '</div>';
				rowHtml += '<div class="o100-schedule-actions">';
				rowHtml += '<button type="button" class="o100-remove-slot-btn" title="Remove"><span class="dashicons dashicons-trash"></span></button>';
				rowHtml += '</div>';
				rowHtml += '</div>';

				$container.append(rowHtml);
			});

			$(document).on('change', '#o100-loc-hours-grid .o100-day-toggle', function() {
				var $wrapper = $(this).closest('.o100-custom-schedule-wrapper');
				var isChecked = $(this).is(':checked');
				var $slotsContainer = $wrapper.find('.o100-slots-container');
				var $addWrap = $wrapper.find('.o100-add-slot-wrap');
				var $closedPill = $wrapper.find('.o100-closed-pill');
				
				if (isChecked) {
					$closedPill.hide();
					$slotsContainer.css('display', 'flex');
					$addWrap.css('display', 'flex');
					if ($slotsContainer.children('.o100-time-slot-row').length === 0) {
						$wrapper.find('.o100-add-slot-btn').trigger('click');
					}
				} else {
					$slotsContainer.hide();
					$addWrap.hide();
					$closedPill.css('display', 'inline-flex');
					$slotsContainer.empty(); // Remove all slots when closed
				}
			});

			$(document).on('click', '#o100-loc-hours-grid .o100-remove-slot-btn', function() {
				var $wrapper = $(this).closest('.o100-custom-schedule-wrapper');
				$(this).closest('.o100-time-slot-row').remove();
				if ($wrapper.find('.o100-time-slot-row').length === 0) {
					$wrapper.find('.o100-day-toggle').prop('checked', false).trigger('change');
				}
			});

			function o100_populate_hours_grid(hoursData) {
				try {
					$('.o100-slots-container').empty();
					var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
					
					// Pre-generate options for fast appending
					var hrsHtml = '<option value="">--</option>';
					for(var i=0; i<24; i++) {
						var v = (i<10?'0':'')+i;
						hrsHtml += '<option value="'+v+'">'+v+'</option>';
					}
					var minsHtml = '<option value="">--</option><option value="00">00</option><option value="15">15</option><option value="30">30</option><option value="45">45</option>';

					$.each(days, function(index, day) {
						var $dayWrapper = $('#o100-loc-hours-grid .o100-modal-day-wrapper[data-day="'+day+'"]');
						var $slotsContainer = $dayWrapper.find('.o100-slots-container');

						if (hoursData && hoursData[day] && Array.isArray(hoursData[day]) && hoursData[day].length > 0) {
							// Check the toggle without triggering the change event yet to avoid race conditions
							$dayWrapper.find('.o100-day-toggle').prop('checked', true);
							$dayWrapper.find('.o100-closed-pill').hide();
							$slotsContainer.css('display', 'flex');
							$dayWrapper.find('.o100-add-slot-wrap').css('display', 'flex');
							
							$slotsContainer.empty();

							$.each(hoursData[day], function(idx, timeSlot) {
								if (!timeSlot || typeof timeSlot !== 'object') return;
								
								var startStr = timeSlot.start || timeSlot['open-time'] || timeSlot.open || '';
								var endStr   = timeSlot.end || timeSlot['close-time'] || timeSlot.close || '';

								var startParts = startStr.split(':');
								var endParts = endStr.split(':');
								
								var sh = startParts[0] ? startParts[0] : '';
								var sm = startParts[1] ? startParts[1] : '';
								var eh = endParts[0] ? endParts[0] : '';
								var em = endParts[1] ? endParts[1] : '';

								var rowHtml = '<div class="o100-time-slot-row o100-modal-time-row">';
								rowHtml += '<div class="o100-time-split">';
								rowHtml += '<select class="o100-time-select op-hr start-hr">' + hrsHtml.replace('value="'+sh+'"', 'value="'+sh+'" selected') + '</select>';
								rowHtml += '<span class="o100-split-colon">:</span>';
								rowHtml += '<select class="o100-time-select op-min start-min">' + minsHtml.replace('value="'+sm+'"', 'value="'+sm+'" selected') + '</select>';
								rowHtml += '</div>';
								rowHtml += '<span class="o100-time-separator">to</span>';
								rowHtml += '<div class="o100-time-split">';
								rowHtml += '<select class="o100-time-select op-hr end-hr">' + hrsHtml.replace('value="'+eh+'"', 'value="'+eh+'" selected') + '</select>';
								rowHtml += '<span class="o100-split-colon">:</span>';
								rowHtml += '<select class="o100-time-select op-min end-min">' + minsHtml.replace('value="'+em+'"', 'value="'+em+'" selected') + '</select>';
								rowHtml += '</div>';
								rowHtml += '<div class="o100-schedule-actions">';
								rowHtml += '<button type="button" class="o100-remove-slot-btn" title="Remove"><span class="dashicons dashicons-trash"></span></button>';
								rowHtml += '</div>';
								rowHtml += '</div>';

								$slotsContainer.append(rowHtml);
							});
						} else {
							$dayWrapper.find('.o100-day-toggle').prop('checked', false);
							$slotsContainer.hide();
							$dayWrapper.find('.o100-add-slot-wrap').hide();
							$dayWrapper.find('.o100-closed-pill').css('display', 'inline-flex');
							$slotsContainer.empty();
						}
					});
				} catch (e) {
					console.error("Error populating hours grid:", e);
				}
			}

			// Tabs logic
			$('.o100-loc-tab').on('click', function(e) {
				e.preventDefault();
				$('.o100-loc-tab').removeClass('active').css({'color': '#64748b', 'border-bottom-color': 'transparent'});
				$(this).addClass('active').css({'color': '#2563eb', 'border-bottom-color': '#2563eb'});
				$('.o100-loc-tab-content').hide();
				$('#o100-loc-tab-' + $(this).data('tab')).show();
			});

			var deleteLocId = 0;
			var $deleteTr = null;

			$(document).on('click', '.o100-delete-loc', function(e) {
				e.preventDefault();
				deleteLocId = $(this).data('id');
				$deleteTr = $(this).closest('.o100-loc-list-row');
				$('#o100-delete-confirm-modal').css('display', 'flex');
			});

			$(document).on('click', '#o100-cancel-delete', function() {
				$('#o100-delete-confirm-modal').hide();
			});

			$(document).on('click', '#o100-confirm-delete', function() {
				if (!deleteLocId) return;
				
				var $btn = $(this);
				$btn.text('<?php esc_html_e( 'Deleting...', 'order100' ); ?>').prop('disabled', true);
				if ($deleteTr) $deleteTr.css('opacity', '0.5');
				
				$.post(ajaxurl, { action: 'o100_delete_location', loc_id: deleteLocId }, function(res) {
					$btn.text('<?php esc_html_e( 'Delete', 'order100' ); ?>').prop('disabled', false);
					$('#o100-delete-confirm-modal').hide();
					
					if (res.success) {
						if (typeof window.o100ShowToast === 'function') {
							window.o100ShowToast('<?php esc_html_e( 'Location deleted successfully.', 'order100' ); ?>');
						}
						if ($('.o100-delete-loc').length === 1) {
							window.o100_just_deleted_last = true;
						}
						loadLocations();
					} else {
						if ($deleteTr) $deleteTr.css('opacity', '1');
						if (typeof window.o100ShowToast === 'function') {
							window.o100ShowToast(res.data || '<?php esc_html_e( 'Failed to delete.', 'order100' ); ?>', 'error');
						} else {
							window.o100ShowToast(res.data || 'Failed to delete.', 'error');
						}
					}
				});
			});

			$(document).on('click', '.o100-edit-loc', function(e) {
				e.preventDefault();
				var locId = $(this).data('id');
				var locData = window.o100_locations_data.find(function(l) { return l.id == locId; });
				if (!locData) return;

				$('#o100-loc-modal-title').text('Edit Location: ' + locData.name);
				$('#o100_loc_term_id').val(locData.id);
				$('#o100_loc_name').val(locData.name);
				$('#o100_loc_address').val(locData.address || '');
				$('#o100_loc_latlng').val(locData.latlng || '');
				$('#o100_loc_emails').val(locData.emails || '');
				$('#o100_loc_phone_code').val(locData.phone_code || '+1');
				$('#o100_loc_phone').val(locData.phone || '');
				$('#o100_loc_distance').val(locData.distance || '');
				$('#o100_loc_min_order').val(locData.min_order || '');
				
				var loc_del = locData.enable_delivery || window.o100_global_config.enable_delivery || 'yes';
				var loc_pic = locData.enable_pickup || window.o100_global_config.enable_pickup || 'yes';
				$('#o100_loc_enable_delivery').prop('checked', loc_del === 'yes').trigger('change');
				$('#o100_loc_enable_pickup').prop('checked', loc_pic === 'yes').trigger('change');
				
				$('#o100_loc_fee_action').val(locData.fee_action || 'none').trigger('change');
				$('#o100_loc_fee_type').val(locData.fee_type || 'percent').trigger('change');
				$('#o100_loc_fee_val').val(locData.fee_val || '');

				$('#o100_loc_closed').prop('checked', locData.closed === 'yes');
				
				// Populate hours FIRST, before triggering override change
				if (locData.hours && Object.keys(locData.hours).length > 0) {
					o100_populate_hours_grid(locData.hours);
				} else {
					o100_populate_hours_grid({});
				}

				$('#o100_loc_override_hours').prop('checked', locData.override_hours === 'yes').trigger('change');

				// Populate Select2 for Products
				var $hiddenProducts = $('#o100_loc_hidden_products');
				$hiddenProducts.empty();
				if (locData.hidden_prods && Array.isArray(locData.hidden_prods)) {
					$.each(locData.hidden_prods, function(i, prod) {
						var option = new Option(prod.text, prod.id, true, true);
						$hiddenProducts.append(option);
					});
				}
				$hiddenProducts.trigger('change');

				// Populate Select2 for Categories
				$('#o100_loc_hidden_cats').val(null).trigger('change');
				if (locData.hidden_cats && Array.isArray(locData.hidden_cats)) {
					var catIds = locData.hidden_cats.map(function(c) { return c.id; });
					$('#o100_loc_hidden_cats').val(catIds).trigger('change');
				}

				$('#o100_loc_interval').val(locData.interval || '30');
				$('#o100_loc_max_order').val(locData.max_order || '4');

				// Render previously generated slots manually or trigger generation backend if needed
				if (locData.generated_slots && locData.generated_slots !== '[]' && locData.generated_slots !== '{}') {
					$('#o100_loc_generated_slots_data').val(locData.generated_slots);
					// To correctly render the HTML of saved slots without a backend call, we could trigger a backend re-render.
					// For performance, we can just trigger the generate button silently if there are slots, or just re-render via AJAX.
					$.post(ajaxurl, {
						action: 'o100_loc_preview_timeslots',
						hours: locData.hours,
						interval: locData.interval || 30,
						max_order: locData.max_order || 4
					}, function(res) {
						if (res.success) {
							$('#o100_loc_generated_slots_display').html(res.data.html);
							$('#o100_loc_generated_slots_data').val(locData.generated_slots); // keep the original DB data which might have manual edits
						}
					});
				} else {
					$('#o100_loc_generated_slots_display').empty();
					$('#o100_loc_generated_slots_data').val('{}');
				}

				$('.o100-loc-tab[data-tab="general"]').click();
				$('.o100-location-modal-overlay').css('display', 'flex');
			});

			$(document).on('click', '.o100-add-location-btn', function(e) {
				e.preventDefault();
				$('#o100-loc-modal-title').text('Create a new Location');
				$('#o100_loc_term_id, #o100_loc_name, #o100_loc_address, #o100_loc_latlng, #o100_loc_emails, #o100_loc_phone, #o100_loc_distance, #o100_loc_min_order, #o100_loc_fee_val').val('');
				$('#o100_loc_phone_code').val('+1');
				var glConf = window.o100_global_config || {};
				$('#o100_loc_enable_delivery').prop('checked', glConf.enable_delivery !== 'no').trigger('change');
				$('#o100_loc_enable_pickup').prop('checked', glConf.enable_pickup !== 'no').trigger('change');
				$('#o100_loc_fee_action').val('none').trigger('change');
				$('#o100_loc_fee_type').val('percent').trigger('change');
				$('#o100_loc_closed').prop('checked', false);
				$('#o100_loc_override_hours').prop('checked', false).trigger('change');
				$('#o100_loc_hidden_products').empty().trigger('change');
				$('#o100_loc_hidden_cats').val(null).trigger('change');

				// Prefill global defaults
				var glConf = window.o100_global_config || {};
				$('#o100_loc_interval').val(glConf.interval || '30');
				$('#o100_loc_max_order').val(glConf.max_order || '4');
				o100_populate_hours_grid(glConf.hours || {});

				$('#o100_loc_generated_slots_display').empty();
				$('#o100_loc_generated_slots_data').val('{}');
				$('.o100-loc-tab[data-tab="general"]').click();
				$('.o100-location-modal-overlay').css('display', 'flex');
			});

			$('.o100-close-modal').on('click', function() {
				$('.o100-location-modal-overlay').hide();
			});

			// Generate Timeslots button click
			$('#o100_loc_generate_slots_btn').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				
				var interval = $('#o100_loc_interval').val();
				var max_order = $('#o100_loc_max_order').val();

				var hours_data = {};
				var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
				$.each(days, function(index, day) {
					var $dayWrapper = $('#o100-loc-hours-grid .o100-modal-day-wrapper[data-day="'+day+'"]');
					if ($dayWrapper.find('.o100-day-toggle').is(':checked')) {
						var slots = [];
						$dayWrapper.find('.o100-time-slot-row').each(function() {
							var sh = $(this).find('.start-hr').val() || '';
							var sm = $(this).find('.start-min').val() || '';
							var eh = $(this).find('.end-hr').val() || '';
							var em = $(this).find('.end-min').val() || '';
							var start = (sh && sm) ? sh + ':' + sm : '';
							var end = (eh && em) ? eh + ':' + em : '';
							if (start || end) {
								slots.push({ open: start, close: end });
							}
						});
						if (slots.length > 0) hours_data[day] = slots;
					}
				});

				$btn.text('Generating...').prop('disabled', true);
				$.post(ajaxurl, {
					action: 'o100_loc_preview_timeslots',
					hours: hours_data,
					interval: interval,
					max_order: max_order
				}, function(res) {
					$btn.html('<span class="dashicons dashicons-clock" style="font-size: 18px;"></span> Generate Timeslots').prop('disabled', false);
					if (res.success) {
						$('#o100_loc_generated_slots_display').html(res.data.html);
						$('#o100_loc_generated_slots_data').val(JSON.stringify(res.data.slots));
					}
				});
			});

			// Listen for manual changes to generated max order inputs
			$(document).on('change', '.o100-slot-max-order-input', function() {
				var $input = $(this);
				var day = $input.data('day');
				var idx = $input.data('idx');
				var val = parseInt($input.val());
				var $dataInput = $('#o100_loc_generated_slots_data');
				var slots = JSON.parse($dataInput.val() || '{}');
				if ( slots[day] && slots[day][idx] ) {
					slots[day][idx]['max-odts'] = val;
					$dataInput.val( JSON.stringify(slots) );
				}
			});

			$('.o100-help-icon').on('click', function() {
				$(this).closest('.o100-modal-field-header').next('.o100-help-text').slideToggle(200);
			});

			$('.o100-save-location-action').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var name = $('#o100_loc_name').val().trim();
				var emails = $('#o100_loc_emails').val().trim();
				var phone_code = $('#o100_loc_phone_code').val();
				var phone = $('#o100_loc_phone').val().trim();
				var distance = $('#o100_loc_distance').val().trim();
				var min_order = $('#o100_loc_min_order').val().trim();
				
				var enable_delivery = $('#o100_loc_enable_delivery').is(':checked') ? 'yes' : 'no';
				var enable_pickup = $('#o100_loc_enable_pickup').is(':checked') ? 'yes' : 'no';
				var fee_action = $('#o100_loc_fee_action').val();
				var fee_type = $('#o100_loc_fee_type').val();
				var fee_val = $('#o100_loc_fee_val').val().trim();

				// Validation
				var current_id = $('#o100_loc_term_id').val();
				var address = $('#o100_loc_address').val().trim();

				if (!name) {
					window.o100ShowToast('Location Name is required.', 'error');
					$('#o100_loc_name').focus();
					return;
				}

				// Duplication checks
				if (window.o100_locations_data && window.o100_locations_data.length > 0) {
					var rawName = name.replace(/\s+/g, '').toLowerCase();
					var rawAddress = address.replace(/\s+/g, '').toLowerCase();
					var rawPhone = phone.replace(/[\s\-\(\)\+]/g, '');

					for (var i = 0; i < window.o100_locations_data.length; i++) {
						var loc = window.o100_locations_data[i];
						if (current_id && String(loc.id) === String(current_id)) continue;

						var existingName = (loc.name || '').replace(/\s+/g, '').toLowerCase();
						var existingAddress = (loc.address || '').replace(/\s+/g, '').toLowerCase();
						var existingPhone = (loc.phone || '').replace(/[\s\-\(\)\+]/g, '');

						if (existingName && existingName === rawName) {
							window.o100ShowToast('A location with this Name already exists. Please choose a unique name.', 'error');
							$('#o100_loc_name').focus();
							return;
						}
						if (address && existingAddress && existingAddress === rawAddress) {
							window.o100ShowToast('A location with this exact Address already exists. Please modify the address.', 'error');
							$('#o100_loc_address').focus();
							return;
						}
						if (phone && existingPhone && existingPhone === rawPhone) {
							window.o100ShowToast('A location with this Phone Number already exists. Please check the phone number.', 'error');
							$('#o100_loc_phone').focus();
							return;
						}
					}
				}
				
				if (emails) {
					var emailArr = emails.split(',');
					var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
					for(var i=0; i<emailArr.length; i++) {
						if(!emailRegex.test(emailArr[i].trim())) {
							window.o100ShowToast('Invalid email format: ' + emailArr[i].trim(), 'error');
							$('#o100_loc_emails').focus();
							return;
						}
					}
				}

				if (phone && !/^\d+$/.test(phone)) {
					window.o100ShowToast('Phone number should contain only digits.', 'error');
					$('#o100_loc_phone').focus();
					return;
				}

				if (distance && isNaN(distance)) { window.o100ShowToast('Distance must be a number.', 'error'); return; }
				if (min_order && isNaN(min_order)) { window.o100ShowToast('Minimum Order must be a number.', 'error'); return; }
				if (fee_val && isNaN(fee_val)) { window.o100ShowToast('Fee Adjustment must be a number.', 'error'); return; }

				var hidden_cats = $('#o100_loc_hidden_cats').val() || [];
				var hidden_products = $('#o100_loc_hidden_products').val() || [];

				var closed = $('#o100_loc_closed').is(':checked') ? 'yes' : 'no';
				var override_hours = $('#o100_loc_override_hours').is(':checked') ? 'yes' : 'no';

				var hours_data = {};
				var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
				$.each(days, function(index, day) {
					var $dayWrapper = $('#o100-loc-hours-grid .o100-modal-day-wrapper[data-day="'+day+'"]');
					if ($dayWrapper.find('.o100-day-toggle').is(':checked')) {
						var slots = [];
						$dayWrapper.find('.o100-time-slot-row').each(function() {
							var sh = $(this).find('.start-hr').val() || '';
							var sm = $(this).find('.start-min').val() || '';
							var eh = $(this).find('.end-hr').val() || '';
							var em = $(this).find('.end-min').val() || '';
							var start = (sh && sm) ? sh + ':' + sm : '';
							var end = (eh && em) ? eh + ':' + em : '';
							if (start || end) {
								slots.push({ open: start, close: end });
							}
						});
						if (slots.length > 0) hours_data[day] = slots;
					}
				});

				var interval = $('#o100_loc_interval').val();
				var max_order = $('#o100_loc_max_order').val();
				var generated_slots = $('#o100_loc_generated_slots_data').val();

				var data = {
					action: 'o100_save_location',
					loc_id: $('#o100_loc_term_id').val(),
					name: name,
					address: $('#o100_loc_address').val(),
					latlng: $('#o100_loc_latlng').val(),
					emails: emails,
					phone_code: phone_code,
					phone: phone,
					distance: distance,
					min_order: min_order,
					enable_delivery: enable_delivery,
					enable_pickup: enable_pickup,
					fee_action: fee_action,
					fee_type: fee_type,
					fee_val: fee_val,
					hidden_cats: JSON.stringify(hidden_cats),
					hidden_products: JSON.stringify(hidden_products),
					closed: closed,
					override_hours: override_hours,
					hours: hours_data,
					interval: interval,
					max_order: max_order,
					generated_slots: generated_slots
				};

				$btn.text('Saving...').prop('disabled', true);

				$.post(ajaxurl, data, function(response) {
					$btn.text('<?php esc_html_e( 'Save Location', 'order100' ); ?>').prop('disabled', false);
					if (response.success) {
						$('.o100-location-modal-overlay').hide();
						// Show global toast
						if (window.o100ShowToast) window.o100ShowToast('Location saved successfully!', 'success');
						loadLocations();
					} else {
						window.o100ShowToast(response.data || 'Error saving location', 'error');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX Save Store Profile
	 */
	public function ajax_save_store_profile() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$data = array();
		if ( isset( $_POST['o100_store_profile'] ) ) {
			// Clean the data recursively
			$raw_data = wp_unslash( $_POST['o100_store_profile'] );
			if ( is_array( $raw_data ) ) {
				foreach ( $raw_data as $key => $value ) {
					if ( is_array( $value ) ) {
						$data[ sanitize_text_field( $key ) ] = array_map( 'sanitize_text_field', $value );
					} else {
						$data[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
					}
				}
			}
		}

		update_option( 'o100_store_profile', $data );
		wp_send_json_success( array( 'message' => 'Settings saved successfully' ) );
	}

	/**
	 * AJAX Save Checkout Ext (Tipping)
	 */
	public function ajax_save_checkout_ext() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$data = isset( $_POST['o100_checkout_ext'] ) ? wp_unslash( $_POST['o100_checkout_ext'] ) : array();
		
		$options = get_option( 'o100_options', array() );
		
		// Ensure control initialized flag is saved
		$data['o100_tip_control_initialized'] = '1';
		
		// Map checkboxes (if unchecked they won't be in POST data)
		$checkboxes = array('o100_tip_delivery_enable', 'o100_tip_pickup_enable');
		foreach ($checkboxes as $cb) {
			$data[$cb] = isset($data[$cb]) ? 'on' : '';
		}

		// Sanitize and merge
		foreach ( $data as $key => $value ) {
			$options[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
		}

		update_option( 'o100_options', $options );
		wp_send_json_success();
	}

	/**
	 * AJAX Save Location
	 */
	public function ajax_toggle_locations() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}
		
		$status = isset($_POST['status']) && $_POST['status'] === 'on' ? 'on' : '';
		$opts = get_option('o100_locations', array());
		
		if ( ! is_array($opts) ) {
			$opts = array();
		}
		
		$opts['o100_enable_loc'] = $status;
		update_option('o100_locations', $opts);
		
		// Clear stale session variables if disabled
		if ( $status !== 'on' && function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'ex_userloc', null );
			WC()->session->set( '_user_deli_log', null );
		}
		
		wp_send_json_success();
	}

	/**
	 * AJAX Toggle Generic Module State (Delivery, Pickup, Dine-in)
	 */
	public function ajax_toggle_module() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}
		
		$module = isset($_POST['module']) ? sanitize_text_field($_POST['module']) : '';
		$status = isset($_POST['status']) && $_POST['status'] === 'on' ? 'on' : '';
		
		if ( in_array( $module, array( 'delivery', 'pickup', 'dinein' ) ) ) {
			$opt_key = 'o100_' . $module;
			$field_key = 'o100_enable_' . $module;
			$opts = get_option( $opt_key, array() );
			if ( ! is_array($opts) ) {
				$opts = array();
			}
			$opts[$field_key] = $status;
			update_option( $opt_key, $opts );
			wp_send_json_success();
		} else {
			wp_send_json_error( 'Invalid module' );
		}
	}

	public function init_address_autocomplete() {
		$options = get_option( 'o100_api_integration', array() );
		$api_key = !empty($options['o100_ggmap_api_js']) ? $options['o100_ggmap_api_js'] : '';
		
		// Fallback to legacy Exfood key if Order100 key is missing
		if ( empty( $api_key ) ) {
			$old_options = get_option( 'exwoofood_api_integration', array() );
			$api_key = !empty($old_options['exwoofood_ggmap_api_js']) ? $old_options['exwoofood_ggmap_api_js'] : '';
		}
		
		if ( empty( $api_key ) ) return;
		?>
		<style>
			/* Fix Google Places Autocomplete dropdown z-index to appear above the modal (z-index: 99999) */
			.pac-container {
				z-index: 100005 !important;
			}
		</style>
		<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr( $api_key ); ?>&libraries=places"></script>
		<script>
			jQuery(document).ready(function($){
				function o100InitAutocomplete(input) {
					if (!input || $(input).hasClass('pac-target-input')) return;
					if (typeof google === 'undefined' || !google.maps || !google.maps.places) return;
					
					var autocomplete = new google.maps.places.Autocomplete(input);
					autocomplete.addListener('place_changed', function() {
						var place = autocomplete.getPlace();
						if (place.geometry) {
							var lat = place.geometry.location.lat();
							var lng = place.geometry.location.lng();
							
							if ($(input).attr('id') === 'o100_loc_address') {
								$('#o100_loc_latlng').val(lat + ',' + lng);
								return;
							}
							
							var $row = $(input).closest('.cmb-repeatable-grouping');
							if ($row.length) {
								$row.find('input[id*="coordinates"]').val(lat + ',' + lng);
							}
						}
					});
				}

				// Global expose for manual initialization if needed
				window.o100InitAutocomplete = o100InitAutocomplete;

				// Init dynamically when the user focuses the address field.
				// This ensures the element is strictly visible, preventing Google Maps dropdown positioning bugs.
				$(document).on('focus', '#o100_loc_address, .o100-address-autocomplete input', function() {
					o100InitAutocomplete(this);
				});

				// Init existing CMB2 fields just in case they are visible
				$('.o100-address-autocomplete input').each(function(){
					o100InitAutocomplete(this);
				});
				
				// Optional: pre-init if visible
				if ($('#o100_loc_address').is(':visible')) {
					o100InitAutocomplete($('#o100_loc_address')[0]);
				}
			});
		</script>
		<?php
	}

	/**
	 * Renders the global Toast UI and form interception logic
	 */
	public function render_global_toast_and_saving() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'o100' ) === false && strpos( $screen->id, 'order100' ) === false ) {
			return;
		}
		?>
		<style>
			#o100-global-toast {
				position: fixed;
				bottom: -100px;
				right: 30px;
				background: #22c55e;
				color: white;
				padding: 16px 24px;
				border-radius: 8px;
				box-shadow: 0 10px 15px -3px rgba(34,197,94,0.3);
				display: flex;
				align-items: center;
				gap: 12px;
				font-size: 15px;
				font-weight: 500;
				z-index: 999999;
				transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
				opacity: 0;
			}
			#o100-global-toast.show {
				bottom: 40px;
				opacity: 1;
			}
			.o100-spin {
				animation: o100-spin 1s linear infinite;
			}
			@keyframes o100-spin { 100% { transform: rotate(360deg); } }
			/* Hide default WP save notice to avoid duplication */
			.wrap > .notice.notice-success.is-dismissible { display: none !important; }

			/* === Settings Group Cards === */
			.o100-settings-group-card {
				border: 1px solid #e2e8f0;
				border-radius: 8px;
				margin-bottom: 32px;
				overflow: hidden;
				background: #fff;
			}
			.o100-settings-group-title {
				background: #f8fafc;
				padding: 16px 24px;
				border-bottom: 1px solid #e2e8f0;
			}
			.o100-settings-group-title h3 {
				margin: 0 0 4px 0;
				font-size: 16px;
				font-weight: 600;
				color: #0f172a;
			}
			.o100-settings-group-title p {
				margin: 0;
				font-size: 13px;
				color: #64748b;
			}
			.o100-settings-group-content {
				padding: 24px;
			}

			/* === CMB2 Group Base Styles === */
			.cmb-repeatable-grouping {
				position: relative !important;
				border: 1px solid #e2e8f0 !important;
				border-radius: 8px !important;
				margin-bottom: 24px !important;
				background: #fff !important;
				box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
			}
			.cmb-repeatable-grouping > .cmb-group-title {
				background: #f8fafc !important;
				padding: 14px 20px !important;
				margin: 0 !important;
				border-bottom: 1px solid #e2e8f0 !important;
				border-radius: 8px 8px 0 0 !important;
				font-size: 15px !important;
				font-weight: 600 !important;
				color: #0f172a !important;
			}
			/* General Remove Button (Hidden in favor of custom inline one) */
			.cmb-repeatable-grouping > .cmb-remove-group-row {
				display: none !important;
			}
			.cmb-add-group-row {
				background: #f1f5f9 !important;
				color: #334155 !important;
				border: 1px dashed #cbd5e1 !important;
				padding: 12px 24px !important;
				border-radius: 6px !important;
				font-weight: 600 !important;
				cursor: pointer !important;
				display: inline-block !important;
				margin-top: 10px !important;
				transition: all 0.2s !important;
			}
			.cmb-add-group-row:hover {
				background: #e2e8f0 !important;
				border-color: #94a3b8 !important;
			}
			.cmb-repeatable-grouping .inside {
				padding: 24px !important;
			}

			/* === Settings Group Card Full Width === */
			.o100-settings-group-card {
				width: 100% !important;
				box-sizing: border-box !important;
			}

			/* === Food Labels — Compact Inline Row Layout === */
			/* Based on actual DOM: .cmb-repeatable-grouping > .inside.cmb-td > .cmb-row.cmb-type-* */

			#o100_global_food_labels_repeat .cmb-repeatable-grouping {
				border: 1px solid #e2e8f0 !important;
				border-radius: 6px !important;
				margin-bottom: 8px !important;
				padding: 0 !important;
				background: #fff !important;
				box-shadow: none !important;
			}
			/* Hide: group title bar, collapse handle, top-level X button */
			#o100_global_food_labels_repeat .cmb-repeatable-grouping > .cmb-group-title,
			#o100_global_food_labels_repeat .cmb-repeatable-grouping > .cmbhandle,
			#o100_global_food_labels_repeat .cmb-repeatable-grouping > button.cmb-remove-group-row {
				display: none !important;
			}
			/* .inside is the flex container */
			#o100_global_food_labels_repeat .cmb-repeatable-grouping > .inside {
				display: flex !important;
				align-items: center !important;
				gap: 0 !important;
				padding: 0 !important;
				margin: 0 !important;
				flex-wrap: wrap !important;
			}
			#o100_global_food_labels_repeat .cmb-repeatable-grouping > .inside::before,
			#o100_global_food_labels_repeat .cmb-repeatable-grouping > .inside::after {
				display: none !important;
				content: none !important;
			}
			/* All field cells — dividers + vertical center */
			#o100_global_food_labels_repeat .inside > .cmb-row {
				border: none !important;
				border-right: 1px solid #e2e8f0 !important;
				padding: 5px 10px !important;
				margin: 0 !important;
				float: none !important;
				display: flex !important;
				align-items: center !important;
				align-self: stretch !important;
				box-sizing: border-box !important;
				table-layout: auto !important;
			}
			#o100_global_food_labels_repeat .inside > .cmb-row::before,
			#o100_global_food_labels_repeat .inside > .cmb-row::after {
				display: none !important;
				content: none !important;
			}
			#o100_global_food_labels_repeat .inside > .cmb-row:last-child {
				border-right: none !important;
			}
			/* Hide all field labels by default — force zero size */
			#o100_global_food_labels_repeat .inside > .cmb-row > .cmb-th {
				display: none !important;
				width: 0 !important;
				min-width: 0 !important;
				overflow: hidden !important;
				padding: 0 !important;
				margin: 0 !important;
				flex: 0 0 0px !important;
			}
			#o100_global_food_labels_repeat .inside > .cmb-row > .cmb-td {
				float: none !important;
				padding: 0 !important;
				flex: 1 1 auto !important;
				min-width: 0 !important;
			}

			/* --- NAME (cmb-type-text) — fill remaining space --- */
			#o100_global_food_labels_repeat .inside > .cmb-type-text {
				flex: 1 1 150px !important;
				min-width: 100px !important;
			}
			/* Force cmb-td to fill row (override table-layout 50% width) */
			#o100_global_food_labels_repeat .cmb-type-text > .cmb-td {
				width: 100% !important;
				max-width: 100% !important;
				flex: 1 1 100% !important;
			}
			#o100_global_food_labels_repeat .cmb-type-text input.regular-text,
			#o100_global_food_labels_repeat .cmb-type-text input[type="text"] {
				width: 100% !important;
				max-width: 100% !important;
				padding: 4px 8px !important;
				border: 1px solid #d1d5db !important;
				border-radius: 6px !important;
				font-size: 13px !important;
				box-sizing: border-box !important;
				margin: 0 !important;
				height: 28px !important;
				max-height: 28px !important;
				min-height: 28px !important;
				line-height: 1.2 !important;
			}

			/* --- ICON (cmb-type-file) — button + thumbnail inline --- */
			#o100_global_food_labels_repeat .inside > .cmb-type-file {
				flex: 0 0 auto !important;
			}
			#o100_global_food_labels_repeat .cmb-type-file > .cmb-td {
				display: flex !important;
				align-items: center !important;
				gap: 6px !important;
				flex-wrap: nowrap !important;
				flex: 0 0 auto !important;
			}
			#o100_global_food_labels_repeat .cmb-type-file > .cmb-td > * {
				float: none !important;
				margin: 0 !important;
			}
			#o100_global_food_labels_repeat .cmb-type-file input.cmb2-upload-button {
				padding: 4px 10px !important;
				font-size: 12px !important;
				line-height: 1.4 !important;
				height: 28px !important;
				white-space: nowrap !important;
				box-sizing: border-box !important;
				overflow: visible !important;
			}
			/* Thumbnail container */
			#o100_global_food_labels_repeat .cmb-type-file .cmb2-media-status {
				flex-shrink: 0 !important;
				display: inline-flex !important;
				align-items: center !important;
				padding: 0 !important;
				margin: 0 !important;
			}
			#o100_global_food_labels_repeat .cmb-type-file .cmb2-media-status .img-status {
				display: inline-flex !important;
				align-items: center !important;
				margin: 0 !important;
				padding: 0 !important;
			}
			#o100_global_food_labels_repeat .cmb-type-file .cmb2-media-status img {
				width: 28px !important;
				height: 28px !important;
				object-fit: contain !important;
				border-radius: 4px !important;
				display: block !important;
				margin: 0 !important;
			}
			/* Hide "Remove Image" link */
			#o100_global_food_labels_repeat .cmb-type-file .cmb2-remove-wrapper {
				display: none !important;
			}

			/* --- COLOR (cmb-type-colorpicker) — show label + circle swatch --- */
			#o100_global_food_labels_repeat .inside > .cmb-type-colorpicker {
				flex: 0 0 auto !important;
			}
			#o100_global_food_labels_repeat .cmb-type-colorpicker > .cmb-td {
				flex: 0 0 auto !important;
			}
			/* Show label for colorpicker (override ALL global hide properties) */
			#o100_global_food_labels_repeat .inside > .cmb-row.cmb-type-colorpicker > .cmb-th {
				display: flex !important;
				align-items: center !important;
				width: auto !important;
				min-width: auto !important;
				flex: 0 0 auto !important;
				overflow: visible !important;
				float: none !important;
				padding: 0 6px 0 0 !important;
			}
			#o100_global_food_labels_repeat .cmb-type-colorpicker .cmb-th label {
				font-size: 12px !important;
				font-weight: 600 !important;
				color: #64748b !important;
				white-space: nowrap !important;
			}
			#o100_global_food_labels_repeat .cmb-type-colorpicker .wp-picker-container {
				display: flex !important;
				align-items: center !important;
			}
			#o100_global_food_labels_repeat .cmb-type-colorpicker .wp-color-result {
				width: 28px !important;
				min-width: 28px !important;
				height: 28px !important;
				min-height: 28px !important;
				border-radius: 50% !important;
				padding: 0 !important;
				margin: 0 !important;
				border: 2px solid #d1d5db !important;
				box-sizing: content-box !important;
				flex-shrink: 0 !important;
			}
			#o100_global_food_labels_repeat .cmb-type-colorpicker .wp-color-result-text,
			#o100_global_food_labels_repeat .cmb-type-colorpicker .wp-picker-input-wrap {
				display: none !important;
			}

			/* --- ACTION ROW (cmb-remove-field-row) --- */
			#o100_global_food_labels_repeat .inside > .cmb-remove-field-row {
				flex: 0 0 auto !important;
			}
			#o100_global_food_labels_repeat .cmb-remove-field-row .cmb-remove-row {
				display: flex !important;
				align-items: center !important;
			}
			/* Hide everything in action row except our styled remove button */
			#o100_global_food_labels_repeat .cmb-remove-field-row .cmb-remove-row > *:not(.cmb-remove-group-row-button) {
				display: none !important;
			}
			#o100_global_food_labels_repeat .cmb-remove-field-row .cmb-remove-group-row-button {
				display: inline-flex !important;
				align-items: center !important;
				justify-content: center !important;
				width: 24px !important;
				min-width: 24px !important;
				height: 24px !important;
				min-height: 24px !important;
				border-radius: 50% !important;
				background: #fee2e2 !important;
				color: #dc2626 !important;
				border: none !important;
				cursor: pointer !important;
				padding: 0 !important;
				text-indent: -9999px !important;
				position: relative !important;
				font-size: 0 !important;
				box-sizing: content-box !important;
				flex-shrink: 0 !important;
			}
			#o100_global_food_labels_repeat .cmb-remove-field-row .cmb-remove-group-row-button::after {
				content: '×' !important;
				position: absolute !important;
				text-indent: 0 !important;
				font-size: 16px !important;
				font-weight: 700 !important;
				line-height: 1 !important;
			}
			#o100_global_food_labels_repeat .cmb-remove-field-row .cmb-remove-group-row-button:hover {
				background: #fca5a5 !important;
			}

			/* Hide all description text */
			#o100_global_food_labels_repeat .cmb2-metabox-description {
				display: none !important;
			}

			/* === Flex Layouts for Time Slots Generation === */
			.o100-time-gen-flex {
				display: flex !important;
				flex-wrap: nowrap !important;
				gap: 15px !important;
				align-items: flex-end !important;
				margin-bottom: 25px !important;
				width: 100% !important;
				background: #f8fafc !important;
				padding: 20px !important;
				border-radius: 8px !important;
				border: 1px solid #e2e8f0 !important;
			}
			.o100-time-gen-flex .cmb-row {
				float: none !important;
				width: auto !important;
				margin: 0 !important;
				border-bottom: none !important;
				padding: 0 !important;
			}
			.o100-time-gen-flex .cmb-row.sltime-fr,
			.o100-time-gen-flex .cmb-row.sltime-to {
				flex: 2 !important;
			}
			.o100-time-gen-flex .cmb-row.sltime-maxod,
			.o100-time-gen-flex .cmb-row.sltime-minu {
				flex: 1 !important;
			}
			.o100-time-gen-flex .cmb-row .cmb-th {
				display: block !important;
				width: 100% !important;
				float: none !important;
				padding: 0 0 6px 0 !important;
				text-align: left !important;
			}
			.o100-time-gen-flex .cmb-row .cmb-th label {
				font-size: 13px !important;
				font-weight: 600 !important;
				color: #1e293b !important;
			}
			.o100-time-gen-flex .cmb-row .cmb-td {
				display: block !important;
				width: 100% !important;
				float: none !important;
				padding: 0 !important;
			}
			.o100-time-gen-flex .cmb-row input {
				width: 100% !important;
				height: 40px !important;
				border: 1px solid #cbd5e1 !important;
				border-radius: 6px !important;
			}
			.o100-btn-col {
				flex: 1.5 !important;
				display: flex !important;
				flex-direction: column !important;
				justify-content: flex-end !important;
			}
			.o100-btn-col a.button {
				height: 40px !important;
				line-height: 38px !important;
				padding: 0 15px !important;
				text-align: center !important;
				display: block !important;
				width: 100% !important;
				box-sizing: border-box !important;
				background: #2563eb !important;
				color: #fff !important;
				border: none !important;
				border-radius: 6px !important;
				font-weight: 600 !important;
				font-size: 14px !important;
				box-shadow: 0 2px 4px rgba(37,99,235,0.2) !important;
			}
			.o100-btn-col a.button:hover {
				background: #1d4ed8 !important;
				color: #fff !important;
			}

			/* --- Weekdays Checkboxes Inline Layout --- */
			.o100-weekdays-grid {
				display: flex !important;
				flex-direction: row !important;
				flex-wrap: wrap !important;
				gap: 20px !important;
				padding: 5px 0 20px 0 !important;
			}
			.o100-weekdays-grid .cmb-row {
				flex: 0 0 auto !important;
				width: auto !important;
				margin: 0 !important;
				padding: 0 !important;
				border: none !important;
			}
			.o100-weekdays-grid .cmb-row .cmb-td {
				width: auto !important;
				padding: 0 !important;
			}
			.o100-weekdays-grid .cmb-row label,
			.o100-weekdays-grid .o100-no-switch {
				display: flex !important;
				align-items: center !important;
				gap: 6px !important;
				cursor: pointer !important;
				margin-bottom: 0 !important;
				font-size: 14px !important;
				color: #334155 !important;
				font-weight: 500 !important;
			}
			.o100-weekdays-grid .o100-switch-slider,
			.o100-weekdays-grid .o100-toggle-wrap::after,
			.o100-weekdays-grid .o100-toggle-wrap::before {
				display: none !important;
			}
			.o100-weekdays-grid input[type="checkbox"] {
				margin: 0 !important;
				-webkit-appearance: checkbox !important;
				appearance: checkbox !important;
				width: 18px !important;
				height: 18px !important;
				border: 2px solid #cbd5e1 !important;
				border-radius: 4px !important;
				background: #fff !important;
				position: static !important;
				display: block !important;
				opacity: 1 !important;
			}
			.o100-weekdays-grid input[type="checkbox"]:checked {
				background-color: #2563eb !important;
				border-color: #2563eb !important;
			}

			/* --- Manual Time Slots Repeatable Field --- */
			.cmb-type-timedelivery { width: 100% !important; clear: both !important; margin-top: 10px !important; border-top: 1px solid #f1f5f9 !important; padding-top: 20px !important; }
			.cmb-type-timedelivery > .cmb-td { width: 100% !important; padding: 0 !important; }
			.cmb-type-timedelivery .cmb-tbody { display: block !important; width: 100% !important; }
			.cmb-type-timedelivery .cmb-row { 
				display: flex !important; 
				flex-wrap: nowrap !important; 
				align-items: flex-end !important; 
				gap: 15px !important; 
				background: #fff !important; 
				padding: 15px !important; 
				border: 1px solid #e2e8f0 !important; 
				border-radius: 8px !important; 
				margin-bottom: 12px !important; 
				box-shadow: 0 1px 2px rgba(0,0,0,0.02) !important;
			}
			/* Hide the empty template row (the duplicate) */
			.cmb-type-timedelivery .cmb-row.empty-row {
				display: none !important;
			}
			.cmb-type-timedelivery .cmb-row .exwf-open-time,
			.cmb-type-timedelivery .cmb-row .exwf-close-time {
				flex: 2 !important;
				min-width: 120px !important;
				margin: 0 !important;
				padding: 0 !important;
				border: none !important;
			}
			/* Hide Name of time slot */
			.cmb-type-timedelivery .cmb-row .exwf-name-time {
				display: none !important;
			}
			.cmb-type-timedelivery .cmb-row .exwf-max-order {
				flex: 1.5 !important;
				min-width: 100px !important;
				margin: 0 !important;
				padding: 0 !important;
				border: none !important;
			}
			.cmb-type-timedelivery .cmb-row .cmb-td { padding: 0 !important; }
			.cmb-type-timedelivery .cmb-row .cmb-th { padding: 0 0 6px 0 !important; text-align: left !important; }
			.cmb-type-timedelivery .cmb-row .cmb-th label { font-size: 13px !important; color: #475569 !important; font-weight: 600 !important; }
			.cmb-type-timedelivery .cmb-row input { width: 100% !important; border: 1px solid #cbd5e1 !important; border-radius: 6px !important; padding: 0 12px !important; height: 38px !important; }
			
			.cmb-type-timedelivery .cmb-row .cmb-remove-row { 
				flex: 0 0 auto !important; 
				margin: 0 !important; 
				padding: 0 !important;
				border: none !important;
			}
			.cmb-type-timedelivery .cmb-row .cmb-remove-row button {
				background: #fef2f2 !important;
				color: #ef4444 !important;
				border: 1px solid #fca5a5 !important;
				border-radius: 6px !important;
				width: 38px !important;
				height: 38px !important;
				padding: 0 !important;
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
				cursor: pointer !important;
				box-shadow: none !important;
			}
			.cmb-type-timedelivery .cmb-row .cmb-remove-row button:hover {
				background: #ef4444 !important;
				color: #fff !important;
			}
			.cmb-type-timedelivery .cmb-add-row {
				display: inline-block !important;
				margin-top: 10px !important;
			}
			.cmb-type-timedelivery .cmb-add-row button {
				background: #f1f5f9 !important;
				color: #475569 !important;
				border: 1px dashed #cbd5e1 !important;
				padding: 8px 20px !important;
				border-radius: 6px !important;
				font-size: 14px !important;
				font-weight: 500 !important;
				box-shadow: none !important;
				cursor: pointer !important;
			}
			.cmb-type-timedelivery .cmb-add-row button:hover {
				background: #e2e8f0 !important;
				border-color: #94a3b8 !important;
			}

			/* --- Closed From Date to Date Inline Layout (Grid) --- */
			[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside {
				display: grid !important;
				grid-template-columns: repeat(12, 1fr) !important;
				gap: 20px !important;
				padding: 24px !important;
				align-items: start !important;
			}
			[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside > .cmb-row {
				display: block !important;
				padding: 0 !important;
				margin: 0 !important;
				border: none !important;
				width: 100% !important;
			}
			[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside > .cmb-row.o100-close-name {
				grid-column: span 4 !important;
			}
			[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside > .cmb-row.o100-close-reason {
				grid-column: span 8 !important;
			}
			[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside > .cmb-row.o100-close-reason-custom {
				grid-column: span 12 !important;
				display: none !important;
			}
			[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside > .cmb-row.o100-close-reason-custom.show {
				display: block !important;
			}
			[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside > .cmb-row.o100-close-reason-custom .cmb-th {
				display: none !important;
			}
			[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside > .cmb-row.o100-close-datetime {
				grid-column: span 5 !important;
			}
			/* Remove Button (Bottom) */
			[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside > .cmb-row.cmb-remove-field-row {
				grid-column: span 2 !important;
				display: flex !important;
				justify-content: flex-end !important;
				align-items: flex-end !important;
				height: 67px !important; /* Matches label height (27) + input height (40) */
			}
			[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside > .cmb-row.cmb-remove-field-row button {
				width: 100% !important;
				height: 40px !important;
				background: #fef2f2 !important;
				color: #ef4444 !important;
				border: 1px solid #fca5a5 !important;
				border-radius: 6px !important;
				font-weight: 600 !important;
				cursor: pointer !important;
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
			}
			[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside > .cmb-row.cmb-remove-field-row button:hover {
				background: #ef4444 !important;
				color: white !important;
			}

			@media (max-width: 768px) {
				[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside {
					grid-template-columns: 1fr !important;
				}
				[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside > .cmb-row {
					grid-column: span 1 !important;
				}
				[data-groupid="o100_close_datetodate"] .cmb-repeatable-grouping .inside > .cmb-row.cmb-remove-field-row {
					height: auto !important;
				}
			}
			
			[data-groupid="o100_close_datetodate"] .cmb-row .cmb-th {
				display: block !important;
				width: 100% !important;
				float: none !important;
				padding: 0 0 8px 0 !important;
				text-align: left !important;
			}
			[data-groupid="o100_close_datetodate"] .cmb-row .cmb-th label {
				font-size: 13px !important;
				color: #1e293b !important;
				font-weight: 600 !important;
			}
			[data-groupid="o100_close_datetodate"] .inside > .cmb-row > .cmb-td {
				display: block !important;
				width: 100% !important;
				padding: 0 !important;
			}
			[data-groupid="o100_close_datetodate"] .inside > .cmb-row > .cmb-td input[type="text"],
			[data-groupid="o100_close_datetodate"] .inside > .cmb-row > .cmb-td select {
				width: 100% !important;
				height: 40px !important;
				border: 1px solid #cbd5e1 !important;
				border-radius: 6px !important;
				padding: 0 12px !important;
				background: #fff !important;
				font-size: 14px !important;
				color: #334155 !important;
			}
			[data-groupid="o100_close_datetodate"] .inside > .cmb-row > .cmb-td select {
				padding: 0 30px 0 12px !important;
				appearance: none !important;
				-webkit-appearance: none !important;
				background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m4 6 4 4 4-4'/%3E%3C/svg%3E") !important;
				background-repeat: no-repeat !important;
				background-position: right 12px center !important;
			}
			[data-groupid="o100_close_datetodate"] .inside > .cmb-row > .cmb-td select:focus {
				outline: none !important;
				border-color: #2563eb !important;
				box-shadow: 0 0 0 1px #2563eb !important;
			}
		</style>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
		<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
		<div id="o100-global-toast">
			<span class="dashicons dashicons-yes-alt" style="font-size:24px; width:24px; height:24px;"></span>
			<span class="toast-msg">Settings Saved Successfully!</span>
		</div>
		<script>
			jQuery(document).ready(function($){
				function initO100Flatpickr() {
					if (typeof flatpickr !== 'undefined') {
						$('.o100-close-datetime input[type="text"]').flatpickr({
							enableTime: true,
							dateFormat: "Y-m-d\\TH:i",
							time_24hr: true,
							altInput: true,
							altFormat: "F j, Y h:i K"
						});
					}
				}
				initO100Flatpickr();
				
				if (typeof window.CMB2 !== 'undefined') {
					$('.cmb2-wrap').on('cmb2_add_row', function(e, $row) {
						setTimeout(initO100Flatpickr, 100);
						// Ensure the custom reason field starts hidden correctly on new row
						if ( $row.find('.o100-close-reason select').length ) {
							$row.find('.o100-close-reason select').trigger('change');
						}
					});
				}
				
				// Handle Custom Reason toggle
				$(document).on('change', '.o100-close-reason select', function() {
					var $wrapper = $(this).closest('.inside');
					if ( $(this).val() === 'custom' ) {
						$wrapper.find('.o100-close-reason-custom').addClass('show');
					} else {
						$wrapper.find('.o100-close-reason-custom').removeClass('show');
					}
				});
				// Init reason toggle on load
				$('.o100-close-reason select').trigger('change');

				window.o100ShowToast = function(msg, type) {
					var $toast = $('#o100-global-toast');
					$toast.find('.toast-msg').text(msg || 'Settings Saved Successfully!');
					if(type === 'error') {
						$toast.css('background', '#ef4444').find('.dashicons').attr('class', 'dashicons dashicons-warning');
					} else {
						$toast.css('background', '#22c55e').find('.dashicons').attr('class', 'dashicons dashicons-yes-alt');
					}
					$toast.addClass('show');
					setTimeout(function(){ $toast.removeClass('show'); }, 3500);
				};

				// Check for native CMB2 save success
				if(window.location.search.indexOf('settings-updated=true') > -1) {
					window.o100ShowToast('Settings Saved Successfully!', 'success');
					// Clean URL so refresh doesn't trigger it again
					var cleanUrl = window.location.href.replace('&settings-updated=true', '').replace('?settings-updated=true', '');
					window.history.replaceState({}, document.title, cleanUrl);
				}

				// Intercept form submit to show "Saving..."
				$('form.cmb-form').on('submit', function() {
					var $btn = $(this).find('input[type="submit"], button[type="submit"]');
					
					// Never use prop('disabled', true) here! 
					// 1. It causes Chrome/Safari to abort the submission.
					// 2. It strips the button's name/value from $_POST, which CMB2 requires to save data.
					if ($btn.hasClass('o100-is-saving')) return false; // prevent double submit manually
					
					$btn.addClass('o100-is-saving').data('orig-val', $btn.val() || $btn.text());
					
					if($btn.is('input')) {
						$btn.val('Saving...').css({'opacity': '0.7', 'pointer-events': 'none'});
					} else {
						$btn.html('<span class="dashicons dashicons-update-alt o100-spin" style="margin-right:5px; line-height:inherit;"></span> Saving...').css({'opacity': '0.7', 'pointer-events': 'none'});
					}
				});
			});
		</script>
		<?php
	}

	public function render_delivery_intro() {
		$options = get_option( 'o100_delivery', array() );
		$is_enabled = !empty($options['o100_enable_delivery']) && $options['o100_enable_delivery'] === 'on';
		?>
		<div class="o100-delivery-intro-wrap" style="display: <?php echo $is_enabled ? 'none' : 'flex'; ?>; flex-direction: column; align-items: center; justify-content: center; background: #fff; padding: 60px 40px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">
			<h2 style="font-size: 24px; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 16px;"><?php esc_html_e( 'Delivery Mode', 'order100' ); ?></h2>
			<p style="font-size: 15px; color: #475569; margin-bottom: 32px; text-align: center; max-width: 600px; line-height: 1.6;"><?php esc_html_e( 'Configure delivery zones, shipping fees by radius or zip code, minimum order amounts, and delivery-specific business hours all in one place.', 'order100' ); ?></p>
			<button type="button" class="o100-start-delivery-btn" style="background: #22c55e; color: white; border: none; padding: 12px 32px; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);"><?php esc_html_e( 'Start Using Delivery', 'order100' ); ?></button>
		</div>
		<style>
			.o100-start-delivery-btn:hover { background: #16a34a; transform: translateY(-1px); box-shadow: 0 6px 8px -1px rgba(34, 197, 94, 0.3); }
			/* Permanently hide the raw CMB2 checkbox and its row */
			.cmb2-id-o100-enable-delivery { display: none !important; }
			/* Hide the empty title row */
			.cmb2-id-o100-delivery-intro { display: none !important; }
		</style>
		<?php if ( ! $is_enabled ) : ?>
		<style id="o100-delivery-hide-css">
			.cmb2-id-o100-delivery-intro ~ .cmb-row { display: none !important; }
			.cmb2-id-o100-delivery-intro ~ .o100-settings-group-card { display: none !important; }
		</style>
		<?php endif; ?>
		<script>
			jQuery(document).ready(function($){
				function updateDeliveryVisibility(speed) {
					var isChecked = $('#o100_enable_delivery').is(':checked');
					
					if (isChecked) {
						$('#o100-delivery-hide-css').remove();
						$('.o100-delivery-intro-wrap').slideUp(speed);
						$('.cmb2-id-o100-delivery-intro ~ .o100-settings-group-card').slideDown(speed);
						$('.cmb2-id-o100-delivery-intro ~ .cmb-row:not(.cmb2-id-o100-enable-delivery)').slideDown(speed);
					} else {
						$('.o100-delivery-intro-wrap').slideDown(speed);
						$('.cmb2-id-o100-delivery-intro ~ .o100-settings-group-card').slideUp(speed);
						$('.cmb2-id-o100-delivery-intro ~ .cmb-row:not(.cmb2-id-o100-enable-delivery)').slideUp(speed);
					}
				}

				setTimeout(function() {
					updateDeliveryVisibility(0);
				}, 10);

				$('.o100-start-delivery-btn').on('click', function(e){
					e.preventDefault();
					$('#o100_enable_delivery').prop('checked', true).trigger('change');
					$.post(ajaxurl, { action: 'o100_toggle_module', module: 'delivery', status: 'on' });
				});
				
				$(document).on('change', '#o100_enable_delivery', function(){
					updateDeliveryVisibility(200);
					if (!$(this).is(':checked')) {
						$.post(ajaxurl, { action: 'o100_toggle_module', module: 'delivery', status: '' });
					}
				});
			});
		</script>
		<?php
	}

	public function render_pickup_intro() {
		$options = get_option( 'o100_pickup', array() );
		$is_enabled = !empty($options['o100_enable_pickup']) && $options['o100_enable_pickup'] === 'on';
		?>
		<div class="o100-pickup-intro-wrap" style="display: <?php echo $is_enabled ? 'none' : 'flex'; ?>; flex-direction: column; align-items: center; justify-content: center; background: #fff; padding: 60px 40px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">
			<h2 style="font-size: 24px; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 16px;"><?php esc_html_e( 'Pickup / Pickup Mode', 'order100' ); ?></h2>
			<p style="font-size: 15px; color: #475569; margin-bottom: 32px; text-align: center; max-width: 600px; line-height: 1.6;"><?php esc_html_e( 'Enable customers to order ahead and pick up at your restaurant. Setup pickup-specific discounts, preparation times, and exclusive operating hours.', 'order100' ); ?></p>
			<button type="button" class="o100-start-pickup-btn" style="background: #22c55e; color: white; border: none; padding: 12px 32px; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);"><?php esc_html_e( 'Start Using Pickup', 'order100' ); ?></button>
		</div>
		<style>
			.o100-start-pickup-btn:hover { background: #16a34a; transform: translateY(-1px); box-shadow: 0 6px 8px -1px rgba(34, 197, 94, 0.3); }
			.cmb2-id-o100-enable-pickup { display: none !important; }
			.cmb2-id-o100-pickup-intro { display: none !important; }
		</style>
		<?php if ( ! $is_enabled ) : ?>
		<style id="o100-pickup-hide-css">
			.cmb2-id-o100-pickup-intro ~ .cmb-row { display: none !important; }
			.cmb2-id-o100-pickup-intro ~ .o100-settings-group-card { display: none !important; }
		</style>
		<?php endif; ?>
		<script>
			jQuery(document).ready(function($){
				function updatePickupVisibility(speed) {
					var isChecked = $('#o100_enable_pickup').is(':checked');
					
					if (isChecked) {
						$('#o100-pickup-hide-css').remove();
						$('.o100-pickup-intro-wrap').slideUp(speed);
						$('.cmb2-id-o100-pickup-intro ~ .o100-settings-group-card').slideDown(speed);
						$('.cmb2-id-o100-pickup-intro ~ .cmb-row:not(.cmb2-id-o100-enable-pickup)').slideDown(speed);
					} else {
						$('.o100-pickup-intro-wrap').slideDown(speed);
						$('.cmb2-id-o100-pickup-intro ~ .o100-settings-group-card').slideUp(speed);
						$('.cmb2-id-o100-pickup-intro ~ .cmb-row:not(.cmb2-id-o100-enable-pickup)').slideUp(speed);
					}
				}

				setTimeout(function() {
					updatePickupVisibility(0);
				}, 10);

				$('.o100-start-pickup-btn').on('click', function(e){
					e.preventDefault();
					$('#o100_enable_pickup').prop('checked', true).trigger('change');
					$.post(ajaxurl, { action: 'o100_toggle_module', module: 'pickup', status: 'on' });
				});
				
				$(document).on('change', '#o100_enable_pickup', function(){
					updatePickupVisibility(200);
					if (!$(this).is(':checked')) {
						$.post(ajaxurl, { action: 'o100_toggle_module', module: 'pickup', status: '' });
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Save rooms data from custom hidden JSON field
	 */
	/**
	 * Render the Form Builder tab content
	 */
	public function render_form_builder() {
		wp_enqueue_script( 'jquery-ui-sortable' );
		$opts   = get_option( 'o100_reservation', array() );
		$fields = isset( $opts['o100_resv_form_fields'] ) && is_array( $opts['o100_resv_form_fields'] )
			? $opts['o100_resv_form_fields']
			: $this->get_default_form_fields();

		// Auto-migrate legacy booking_type to occasion
		foreach ( $fields as &$f ) {
			if ( isset( $f['type'] ) && $f['type'] === 'booking_type' ) {
				$f['type'] = 'occasion';
				$f['id']   = 'occasion';
				$f['label'] = __( 'Occasion', 'order100' );
			}
		}
		unset( $f );
		?>
		<div id="o100-resv-tab-form" class="o100-resv-tab-content" style="display:none; width:100%;">

		<div class="o100-settings-group-card">
			<div class="o100-settings-group-title">
				<h3><?php esc_html_e( 'Form Customization', 'order100' ); ?></h3>
				<p><?php esc_html_e( 'Configure which fields appear in the reservation form and their properties.', 'order100' ); ?></p>
				<div style="margin-top:8px; display:flex; align-items:center; gap:8px;">
					<code id="o100-resv-shortcode" style="background:#f1f5f9; padding:6px 12px; border-radius:6px; font-size:13px; color:#334155; border:1px solid #e2e8f0;">[o100_reservation]</code>
					<button type="button" id="o100-resv-copy-sc" class="button" style="padding:4px 10px; font-size:12px;" title="Copy shortcode">
						<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span>
					</button>
					<div style="position:relative; display:inline-block;" id="o100-preview-wrap">
						<button type="button" id="o100-resv-preview-btn" class="button" style="padding:4px 14px; font-size:12px; display:inline-flex; align-items:center; gap:4px;" title="Preview Form">
							<span class="dashicons dashicons-visibility" style="font-size:14px;width:14px;height:14px;"></span>
							<?php esc_html_e( 'Preview', 'order100' ); ?>
						</button>
						<div id="o100-preview-dropdown" style="display:none; position:absolute; top:100%; left:0; margin-top:4px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); min-width:180px; z-index:99; overflow:hidden;">
							<a href="<?php echo esc_url( home_url( '?o100_resv_preview=blank' ) ); ?>" target="_blank" style="display:flex; align-items:center; gap:8px; padding:10px 14px; color:#334155; text-decoration:none; font-size:13px; font-weight:500; transition:background 0.15s;">
								<span class="dashicons dashicons-media-default" style="font-size:16px;width:16px;height:16px;color:#94a3b8;"></span>
								<?php esc_html_e( 'Blank Page', 'order100' ); ?>
							</a>
							<a href="<?php echo esc_url( home_url( '?o100_resv_preview=theme' ) ); ?>" target="_blank" style="display:flex; align-items:center; gap:8px; padding:10px 14px; color:#334155; text-decoration:none; font-size:13px; font-weight:500; border-top:1px solid #f1f5f9; transition:background 0.15s;">
								<span class="dashicons dashicons-admin-appearance" style="font-size:16px;width:16px;height:16px;color:#94a3b8;"></span>
								<?php esc_html_e( 'Theme Page', 'order100' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
			<div class="o100-settings-group-content">

			<input type="hidden" name="o100_resv_form_fields" id="o100_resv_form_fields_data" value="<?php echo esc_attr( wp_json_encode( $fields ) ); ?>">

			<table class="o100-fb-table" id="o100-fb-table">
				<thead>
					<tr>
						<th style="width:5%;"></th>
						<th style="width:35%;"><?php esc_html_e( 'Field Name', 'order100' ); ?></th>
						<th style="width:15%;text-align:center;"><?php esc_html_e( 'Type', 'order100' ); ?></th>
						<th style="width:10%;text-align:center;"><?php esc_html_e( 'Width', 'order100' ); ?></th>
						<th style="width:10%;text-align:center;"><?php esc_html_e( 'Required', 'order100' ); ?></th>
						<th style="width:10%;text-align:center;"><?php esc_html_e( 'Visible', 'order100' ); ?></th>
						<th style="width:15%;text-align:right;"><?php esc_html_e( 'Action', 'order100' ); ?></th>
					</tr>
				</thead>
				<tbody id="o100-fb-tbody">
				</tbody>
			</table>

			<button type="button" id="o100-fb-add-btn" class="button button-primary" style="margin-top:14px; background:#2563eb; border-color:#2563eb; box-shadow:none; display:inline-flex; align-items:center; gap:4px; padding:6px 16px; font-size:13px; font-weight:500; border-radius:6px;">
				<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;"></span>
				<?php esc_html_e( 'Add Custom Field', 'order100' ); ?>
			</button>

			</div>
		</div>

		<!-- Form Footer Settings -->
		<?php
		$default_terms  = __( 'By selecting "Confirm Reservation" you are agreeing to the terms and conditions of our User Agreement and Privacy Policy. The website will automatically use your contact information to register an account and then send promotional information to your email.', 'order100' );
		$default_dining = __( "We have a 15 minute grace period. Please call us if you are running later than 15 minutes after your reservation time.\nWe may contact you about this reservation, so please ensure your email and phone number are up to date.", 'order100' );
		$terms_val   = isset( $opts['o100_resv_terms_text'] ) ? $opts['o100_resv_terms_text'] : $default_terms;
		$dining_val  = isset( $opts['o100_resv_dining_info'] ) ? $opts['o100_resv_dining_info'] : $default_dining;
		$note_val    = isset( $opts['o100_resv_restaurant_note'] ) ? $opts['o100_resv_restaurant_note'] : '';
		?>
		<div class="o100-settings-group-card" style="margin-top:20px;">
			<div class="o100-settings-group-title">
				<h3><?php esc_html_e( 'Form Footer Content', 'order100' ); ?></h3>
				<p><?php esc_html_e( 'Content displayed below the reservation form fields.', 'order100' ); ?></p>
			</div>
			<div class="o100-settings-group-content">
				<div style="display:grid; gap:18px;">
					<div>
						<label style="display:block; font-weight:600; font-size:13px; color:#374151; margin-bottom:6px;">
							<span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; color:#6366f1; vertical-align:text-bottom;"></span>
							<?php esc_html_e( 'Terms & Conditions Checkbox', 'order100' ); ?>
						</label>
						<textarea name="o100_resv_terms_text" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px; font-size:14px; font-family:inherit; resize:vertical;" placeholder="<?php esc_attr_e( 'Leave empty to hide the terms checkbox', 'order100' ); ?>"><?php echo esc_textarea( $terms_val ); ?></textarea>
						<p style="font-size:12px; color:#94a3b8; margin:4px 0 0;"><?php esc_html_e( 'Required checkbox shown before submit. Leave empty to hide.', 'order100' ); ?></p>
					</div>
					<div>
						<label style="display:block; font-weight:600; font-size:13px; color:#374151; margin-bottom:6px;">
							<span class="dashicons dashicons-info-outline" style="font-size:16px; width:16px; height:16px; color:#f59e0b; vertical-align:text-bottom;"></span>
							<?php esc_html_e( 'Important Dining Information', 'order100' ); ?>
						</label>
						<textarea name="o100_resv_dining_info" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px; font-size:14px; font-family:inherit; resize:vertical;" placeholder="<?php esc_attr_e( 'e.g. We have a 15 minute grace period...', 'order100' ); ?>"><?php echo esc_textarea( $dining_val ); ?></textarea>
						<p style="font-size:12px; color:#94a3b8; margin:4px 0 0;"><?php esc_html_e( 'Shown below the form. Use line breaks for multiple lines.', 'order100' ); ?></p>
					</div>
					<div>
						<label style="display:block; font-weight:600; font-size:13px; color:#374151; margin-bottom:6px;">
							<span class="dashicons dashicons-format-quote" style="font-size:16px; width:16px; height:16px; color:#10b981; vertical-align:text-bottom;"></span>
							<?php esc_html_e( 'A Note from the Restaurant', 'order100' ); ?>
						</label>
						<textarea name="o100_resv_restaurant_note" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px; font-size:14px; font-family:inherit; resize:vertical;" placeholder="<?php esc_attr_e( 'e.g. Thank you for choosing our restaurant...', 'order100' ); ?>"><?php echo esc_textarea( $note_val ); ?></textarea>
						<p style="font-size:12px; color:#94a3b8; margin:4px 0 0;"><?php esc_html_e( 'Personal message shown after dining info. Leave empty to hide.', 'order100' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- Edit Field Modal -->
		<div id="o100-fb-modal-overlay" class="o100-room-modal-overlay" style="display:none;">
			<div class="o100-room-modal" style="width:480px;">
				<div class="o100-room-modal-header">
					<h3 id="o100-fb-modal-title"><?php esc_html_e( 'Edit Field', 'order100' ); ?></h3>
					<button type="button" id="o100-fb-modal-close" class="o100-room-modal-close">&times;</button>
				</div>
				<div class="o100-room-modal-body">
					<div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
						<div class="o100-room-modal-field">
							<label><?php esc_html_e( 'Field Type', 'order100' ); ?></label>
							<select id="o100-fb-f-type">
								<option value="text">Text</option>
								<option value="email">Email</option>
								<option value="tel">Phone</option>
								<option value="number">Number</option>
								<option value="textarea">Textarea</option>
								<option value="dropdown">Dropdown</option>
								<option value="select">Select</option>
								<option value="checkbox">Checkbox</option>
								<option value="date">Date</option>
								<option value="time">Time</option>
								<option value="branch">Branch</option>
								<option value="occasion">Occasion Dropdown</option>
							</select>
						</div>
						<div class="o100-room-modal-field">
							<label><?php esc_html_e( 'Width', 'order100' ); ?></label>
							<select id="o100-fb-f-width">
								<option value="half"><?php esc_html_e( 'Half (50%)', 'order100' ); ?></option>
								<option value="third"><?php esc_html_e( 'One-Third (33%)', 'order100' ); ?></option>
								<option value="full"><?php esc_html_e( 'Full (100%)', 'order100' ); ?></option>
							</select>
						</div>
					</div>
					<div class="o100-room-modal-field" style="margin-top:12px;">
						<label style="display:flex; align-items:center; gap:6px; cursor:pointer; text-transform:none; letter-spacing:0; font-size:14px;">
							<input type="checkbox" id="o100-fb-f-required" style="width:auto;margin:0;">
							<?php esc_html_e( 'Required field', 'order100' ); ?>
						</label>
					</div>
					<div class="o100-room-modal-field" style="margin-top:12px;">
						<label><?php esc_html_e( 'Field Label', 'order100' ); ?></label>
						<input type="text" id="o100-fb-f-label" placeholder="<?php esc_attr_e( 'e.g. Your Name', 'order100' ); ?>">
					</div>
					<div class="o100-room-modal-field" style="margin-top:12px;">
						<label><?php esc_html_e( 'Placeholder Text', 'order100' ); ?></label>
						<input type="text" id="o100-fb-f-placeholder" placeholder="<?php esc_attr_e( 'e.g. Enter your name', 'order100' ); ?>">
					</div>
					<div class="o100-room-modal-field" id="o100-fb-options-wrap" style="margin-top:12px; display:none;">
						<label><?php esc_html_e( 'Options (one per line)', 'order100' ); ?></label>
						<textarea id="o100-fb-f-options" rows="4" placeholder="<?php esc_attr_e( "Option 1\nOption 2\nOption 3", 'order100' ); ?>"></textarea>
					</div>
				</div>
				<div class="o100-room-modal-footer">
					<button type="button" id="o100-fb-modal-cancel" class="button"><?php esc_html_e( 'Cancel', 'order100' ); ?></button>
					<button type="button" id="o100-fb-modal-save" class="button button-primary" style="background:#2563eb; border-color:#2563eb;"><?php esc_html_e( 'Save Changes', 'order100' ); ?></button>
				</div>
			</div>
		</div>

		</div><!-- close #o100-resv-tab-form -->

		<style>
		.o100-fb-table {
			width:100%; border-collapse:separate; border-spacing:0;
			border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#fff;
		}
		.o100-fb-table th {
			background:#f8fafc; border-bottom:2px solid #e2e8f0;
			padding:10px 14px; font-size:11px; font-weight:600;
			text-transform:uppercase; letter-spacing:0.05em; color:#94a3b8; text-align:left;
		}
		.o100-fb-table td {
			padding:12px 14px; border-bottom:1px solid #f1f5f9;
			font-size:14px; color:#334155; vertical-align:middle;
		}
		.o100-fb-table tbody tr:last-child td { border-bottom:none; }
		.o100-fb-table tbody tr:hover td { background:#f8fafc; }
		.o100-fb-table td strong { font-weight:600; color:#0f172a; }
		.o100-fb-table .o100-fb-drag { cursor:grab; color:#cbd5e1; font-size:16px; }
		.o100-fb-table .o100-fb-drag:active { cursor:grabbing; }
		.o100-fb-table .o100-fb-type-badge {
			display:inline-block; padding:2px 8px; background:#f1f5f9;
			border-radius:4px; font-size:12px; color:#64748b; font-weight:500;
		}
		.o100-fb-table .o100-fb-width-badge {
			display:inline-block; padding:2px 8px; background:#eff6ff;
			border-radius:4px; font-size:12px; color:#2563eb; font-weight:500;
		}
		.o100-fb-table .o100-fb-actions a {
			font-size:13px; text-decoration:none; margin-left:10px; cursor:pointer; font-weight:500;
		}
		.o100-fb-table .o100-fb-edit { color:#2563eb; }
		.o100-fb-table .o100-fb-edit:hover { color:#1d4ed8; }
		.o100-fb-table .o100-fb-delete { color:#94a3b8; }
		.o100-fb-table .o100-fb-delete:hover { color:#ef4444; }
		/* Toggle switch */
		.o100-fb-toggle { position:relative; display:inline-block; width:36px; height:20px; }
		.o100-fb-toggle input { opacity:0; width:0; height:0; }
		.o100-fb-toggle .o100-fb-slider {
			position:absolute; cursor:pointer; inset:0;
			background:#cbd5e1; border-radius:20px; transition:0.2s;
		}
		.o100-fb-toggle .o100-fb-slider:before {
			content:''; position:absolute; height:16px; width:16px;
			left:2px; bottom:2px; background:#fff; border-radius:50%; transition:0.2s;
		}
		.o100-fb-toggle input:checked + .o100-fb-slider { background:#2563eb; }
		.o100-fb-toggle input:checked + .o100-fb-slider:before { transform:translateX(16px); }
		/* Required dot */
		.o100-fb-req-dot {
			display:inline-block; width:8px; height:8px; border-radius:50%;
			background:#ef4444;
		}
		.o100-fb-req-dot.off { background:#e2e8f0; }
		/* Sortable placeholder */
		.o100-fb-table tbody tr.ui-sortable-placeholder {
			background:#eff6ff !important; border:2px dashed #93c5fd !important;
			visibility:visible !important;
		}
		.o100-fb-table tbody tr.ui-sortable-helper {
			background:#fff; box-shadow:0 4px 12px rgba(0,0,0,0.1);
		}
		/* Modal select */
		.o100-room-modal-field select {
			width:100%; padding:8px 32px 8px 12px; border:1px solid #cbd5e1;
			border-radius:6px; font-size:14px; color:#1e293b;
			box-sizing:border-box; background:#fff;
			-webkit-appearance:none; -moz-appearance:none; appearance:none;
			background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M2 4l4 4 4-4'/%3E%3C/svg%3E") !important;
			background-repeat:no-repeat !important; background-position:right 10px center !important;
			background-size:12px !important;
		}
		.o100-room-modal-field select:disabled {
			background-color:#f1f5f9 !important; color:#94a3b8; cursor:not-allowed; opacity:1;
		}
		</style>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var fields = [];
			try { fields = JSON.parse( $('#o100_resv_form_fields_data').val() ) || []; } catch(e) { fields = []; }
			if (!Array.isArray(fields) || fields.length === 0) fields = <?php echo wp_json_encode( $this->get_default_form_fields() ); ?>;

			var editIdx = -1;

			// Feature flags from PHP settings
			var branchEnabled = <?php echo get_option('o100_locations_status') === 'on' ? 'true' : 'false'; ?>;
			var roomsEnabled = <?php
				$resv_opts = get_option('o100_reservation', array());
				echo (!empty($resv_opts['o100_resv_enable_rooms']) && $resv_opts['o100_resv_enable_rooms'] === 'on') ? 'true' : 'false';
			?>;

			function renderTable() {
				var $tbody = $('#o100-fb-tbody');
				$tbody.empty();

				// Ensure branch is always at index 0
				var branchIdx = -1;
				for (var bi = 0; bi < fields.length; bi++) {
					if (fields[bi].type === 'branch') { branchIdx = bi; break; }
				}
				if (branchIdx > 0) {
					var branchField = fields.splice(branchIdx, 1)[0];
					fields.unshift(branchField);
				}

				fields.forEach(function(f, i) {
					var isBuiltin = f.is_builtin ? true : false;
					var isPinned = (f.type === 'branch'); // Branch is pinned to first row
					var toggleChecked = f.enabled ? 'checked' : '';
					var toggleDisabled = '';

					// Auto-disable logic: Branch if locations off, Seating Preference if rooms off
					if (f.type === 'branch' && !branchEnabled) {
						f.enabled = false;
						toggleChecked = '';
						toggleDisabled = ' disabled';
					}

					var reqClass = f.required ? '' : ' off';
					var deleteBtn = isBuiltin ? '' : '<a class="o100-fb-delete" data-idx="' + i + '">Delete</a>';
					var dragHandle = isPinned
						? '<span class="dashicons dashicons-lock" style="color:#cbd5e1;cursor:default;" title="Pinned to first position"></span>'
						: '<span class="o100-fb-drag dashicons dashicons-menu"></span>';
					var pinnedClass = isPinned ? ' class="o100-fb-pinned"' : '';
					var autoNote = '';
					if (f.type === 'branch' && !branchEnabled) autoNote = ' <span style="font-size:10px;color:#f59e0b;" title="Enable Branches in Settings to activate">(Branches off)</span>';

					$tbody.append(
						'<tr data-idx="' + i + '"' + pinnedClass + '>' +
						'<td>' + dragHandle + '</td>' +
						'<td><strong>' + $('<span>').text(f.label || f.id).html() + '</strong>' +
							(isBuiltin ? ' <span style="font-size:11px;color:#94a3b8;">(built-in)</span>' : '') +
							autoNote +
						'</td>' +
						'<td style="text-align:center;"><span class="o100-fb-type-badge">' + (f.type || 'text') + '</span></td>' +
						'<td style="text-align:center;"><span class="o100-fb-width-badge">' + (f.width || 'half') + '</span></td>' +
						'<td style="text-align:center;"><span class="o100-fb-req-dot' + reqClass + '"></span></td>' +
						'<td style="text-align:center;">' +
							'<label class="o100-fb-toggle"><input type="checkbox" data-idx="' + i + '" ' + toggleChecked + toggleDisabled + '><span class="o100-fb-slider"></span></label>' +
						'</td>' +
						'<td class="o100-fb-actions" style="text-align:right;">' +
							'<a class="o100-fb-edit" data-idx="' + i + '">Edit</a>' +
							deleteBtn +
						'</td>' +
						'</tr>'
					);
				});
				syncHidden();
				initSortable();
			}

			function syncHidden() {
				$('#o100_resv_form_fields_data').val( JSON.stringify(fields) );
			}

			function initSortable() {
				$('#o100-fb-tbody').sortable({
					handle: '.o100-fb-drag',
					axis: 'y',
					placeholder: 'ui-sortable-placeholder',
					items: 'tr:not(.o100-fb-pinned)', // Exclude pinned Branch row
					update: function() {
						var newOrder = [];
						$('#o100-fb-tbody tr').each(function() {
							var idx = parseInt($(this).data('idx'));
							if (!isNaN(idx) && fields[idx]) newOrder.push(fields[idx]);
						});
						fields = newOrder;
						renderTable();
					}
				});
			}

			function openModal(idx) {
				editIdx = idx;
				var f = idx >= 0 ? fields[idx] : null;
				var isBuiltin = f && f.is_builtin;

				$('#o100-fb-modal-title').text(idx >= 0 ? '<?php echo esc_js( __( 'Edit Field', 'order100' ) ); ?>' : '<?php echo esc_js( __( 'Add Custom Field', 'order100' ) ); ?>');

				$('#o100-fb-f-type').val(f ? f.type : 'text').prop('disabled', isBuiltin);
				$('#o100-fb-f-width').val(f ? (f.width || 'half') : 'half');
				$('#o100-fb-f-required').prop('checked', f ? f.required : false);
				$('#o100-fb-f-label').val(f ? f.label : '');
				$('#o100-fb-f-placeholder').val(f ? f.placeholder : '');
				$('#o100-fb-f-options').val(f && f.options ? f.options.replace(/,/g, '\n') : '');

				toggleOptionsField();
				$('#o100-fb-modal-overlay').fadeIn(150);
				setTimeout(function(){ $('#o100-fb-f-label').focus(); }, 200);
			}

			function toggleOptionsField() {
				var t = $('#o100-fb-f-type').val();
				$('#o100-fb-options-wrap').toggle(t === 'select' || t === 'dropdown');
			}

			$('#o100-fb-f-type').on('change', toggleOptionsField);

			function closeModal() { $('#o100-fb-modal-overlay').fadeOut(150); }

			// Toggle enabled
			$(document).on('change', '.o100-fb-toggle input', function() {
				var idx = parseInt($(this).data('idx'));
				fields[idx].enabled = $(this).is(':checked');
				syncHidden();
			});

			// Edit
			$(document).on('click', '.o100-fb-edit', function(e) {
				e.preventDefault();
				openModal(parseInt($(this).data('idx')));
			});

			// Delete
			$(document).on('click', '.o100-fb-delete', function(e) {
				e.preventDefault();
				var idx = parseInt($(this).data('idx'));
				if (confirm('<?php echo esc_js( __( 'Delete this field?', 'order100' ) ); ?>')) {
					fields.splice(idx, 1);
					renderTable();
				}
			});

			// Add
			$('#o100-fb-add-btn').on('click', function() { openModal(-1); });

			// Save
			$('#o100-fb-modal-save').on('click', function() {
				var label = $.trim($('#o100-fb-f-label').val());
				if (!label) { $('#o100-fb-f-label').focus(); return; }

				var type = $('#o100-fb-f-type').val();
				var fieldData = {
					type:        type,
					label:       label,
					placeholder: $.trim($('#o100-fb-f-placeholder').val()),
					width:       $('#o100-fb-f-width').val(),
					required:    $('#o100-fb-f-required').is(':checked'),
					enabled:     true,
				};

				if (type === 'select' || type === 'dropdown') {
					fieldData.options = $.trim($('#o100-fb-f-options').val()).replace(/\n/g, ',');
				}

				if (editIdx >= 0) {
					// Preserve built-in properties
					fieldData.id = fields[editIdx].id;
					fieldData.is_builtin = fields[editIdx].is_builtin || false;
					fieldData.icon = fields[editIdx].icon || '';
					if (fieldData.is_builtin) fieldData.type = fields[editIdx].type; // can't change built-in type
					fieldData.enabled = fields[editIdx].enabled;
					fields[editIdx] = fieldData;
				} else {
					fieldData.id = 'custom_' + Date.now();
					fieldData.is_builtin = false;
					fieldData.icon = '';
					fields.push(fieldData);
				}
				renderTable();
				closeModal();
			});

			// Cancel / close
			$('#o100-fb-modal-cancel, #o100-fb-modal-close').on('click', closeModal);
			$('#o100-fb-modal-overlay').on('click', function(e) {
				if (e.target === this) closeModal();
			});

			// Copy shortcode
			$('#o100-resv-copy-sc').on('click', function() {
				var sc = '[o100_reservation]';
				navigator.clipboard.writeText(sc).then(function() {
					var $btn = $('#o100-resv-copy-sc');
					$btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
					setTimeout(function() {
						$btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
					}, 1500);
				});
			});

			// Preview dropdown toggle
			$('#o100-resv-preview-btn').on('click', function(e) {
				e.stopPropagation();
				$('#o100-preview-dropdown').toggle();
			});
			$(document).on('click', function(e) {
				if (!$(e.target).closest('#o100-preview-wrap').length) {
					$('#o100-preview-dropdown').hide();
				}
			});
			// Hover effect on dropdown items
			$('#o100-preview-dropdown a').on('mouseenter', function() {
				$(this).css('background', '#f8fafc');
			}).on('mouseleave', function() {
				$(this).css('background', '#fff');
			});

			renderTable();
		});
		</script>
		<?php
	}

	/**
	 * Get default form fields for reservation
	 */
	private function get_default_form_fields() {
		return array(
			array(
				'id' => 'guest_name', 'type' => 'text', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Your Name', 'order100' ), 'placeholder' => __( 'Enter your name', 'order100' ),
				'width' => 'half', 'icon' => 'dashicons-admin-users',
			),
			array(
				'id' => 'guest_email', 'type' => 'email', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Email', 'order100' ), 'placeholder' => 'your@email.com',
				'width' => 'half', 'icon' => 'dashicons-email-alt',
			),
			array(
				'id' => 'guest_phone', 'type' => 'tel', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Phone', 'order100' ), 'placeholder' => '(555) 123-4567',
				'width' => 'half', 'icon' => 'dashicons-phone',
			),
			array(
				'id' => 'party_size', 'type' => 'number', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Party Size', 'order100' ), 'placeholder' => '2',
				'width' => 'half', 'icon' => 'dashicons-groups',
			),
			array(
				'id' => 'reservation_date', 'type' => 'date', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Date', 'order100' ), 'placeholder' => __( 'Select date', 'order100' ),
				'width' => 'half', 'icon' => 'dashicons-calendar-alt',
			),
			array(
				'id' => 'reservation_time', 'type' => 'time', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Time', 'order100' ), 'placeholder' => __( 'Select time', 'order100' ),
				'width' => 'half', 'icon' => 'dashicons-clock',
			),
			array(
				'id' => 'branch', 'type' => 'branch', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Branch', 'order100' ), 'placeholder' => __( 'Select a branch', 'order100' ),
				'width' => 'full', 'icon' => 'dashicons-location',
			),
			array(
				'id' => 'occasion', 'type' => 'occasion', 'is_builtin' => true,
				'enabled' => true, 'required' => false,
				'label' => __( 'Occasion', 'order100' ), 'placeholder' => '',
				'width' => 'full', 'icon' => 'dashicons-star-filled',
			),
			array(
				'id' => 'special_requests', 'type' => 'textarea', 'is_builtin' => true,
				'enabled' => true, 'required' => false,
				'label' => __( 'Special Requests', 'order100' ), 'placeholder' => __( 'Any dietary requirements or special needs...', 'order100' ),
				'width' => 'full', 'icon' => 'dashicons-edit',
			),
		);
	}

	/**
	 * Save form fields configuration from custom hidden JSON field
	 */
	public function save_reservation_form_fields( $object_id, $updated ) {
		if ( 'o100_reservation' !== $object_id ) {
			return;
		}
		$raw = isset( $_POST['o100_resv_form_fields'] ) ? wp_unslash( $_POST['o100_resv_form_fields'] ) : '';
		if ( empty( $raw ) ) {
			return;
		}
		$fields = json_decode( $raw, true );
		if ( ! is_array( $fields ) ) {
			return;
		}
		$valid_types = array( 'text', 'email', 'tel', 'number', 'textarea', 'select', 'dropdown', 'checkbox', 'date', 'time', 'branch', 'occasion' );
		$clean = array();
		foreach ( $fields as $f ) {
			if ( empty( $f['label'] ) && empty( $f['id'] ) ) continue;
			$item = array(
				'id'          => sanitize_key( isset( $f['id'] ) ? $f['id'] : 'custom_' . time() ),
				'type'        => in_array( $f['type'], $valid_types, true ) ? $f['type'] : 'text',
				'is_builtin'  => ! empty( $f['is_builtin'] ),
				'enabled'     => ! empty( $f['enabled'] ),
				'required'    => ! empty( $f['required'] ),
				'label'       => sanitize_text_field( isset( $f['label'] ) ? $f['label'] : '' ),
				'placeholder' => sanitize_text_field( isset( $f['placeholder'] ) ? $f['placeholder'] : '' ),
				'width'       => in_array( isset( $f['width'] ) ? $f['width'] : 'half', array( 'half', 'third', 'full' ), true ) ? $f['width'] : 'half',
				'icon'        => sanitize_text_field( isset( $f['icon'] ) ? $f['icon'] : '' ),
			);
			if ( ( $f['type'] === 'select' || $f['type'] === 'dropdown' ) && ! empty( $f['options'] ) ) {
				$item['options'] = sanitize_textarea_field( $f['options'] );
			}
			$clean[] = $item;
		}
		$opts = get_option( 'o100_reservation', array() );
		$opts['o100_resv_form_fields'] = $clean;
		update_option( 'o100_reservation', $opts );
	}

	public function save_reservation_rooms( $object_id, $updated ) {
		if ( 'o100_reservation' !== $object_id ) {
			return;
		}
		$raw = isset( $_POST['o100_resv_rooms'] ) ? wp_unslash( $_POST['o100_resv_rooms'] ) : '[]';
		$rooms = json_decode( $raw, true );
		if ( ! is_array( $rooms ) ) {
			$rooms = array();
		}
		// Sanitize each room
		$clean = array();
		foreach ( $rooms as $r ) {
			if ( empty( $r['name'] ) ) continue;
			$clean[] = array(
				'name'     => sanitize_text_field( $r['name'] ),
				'capacity' => absint( isset( $r['capacity'] ) ? $r['capacity'] : 0 ),
				'quantity' => max( 1, absint( isset( $r['quantity'] ) ? $r['quantity'] : 1 ) ),
				'desc'     => sanitize_textarea_field( isset( $r['desc'] ) ? $r['desc'] : '' ),
			);
		}
		$opts = get_option( 'o100_reservation', array() );
		$opts['o100_resv_rooms'] = $clean;
		update_option( 'o100_reservation', $opts );
	}

	/**
	 * Render the Private Rooms table + modal
	 */
	public function render_rooms_table() {
		$opts  = get_option( 'o100_reservation', array() );
		$rooms = isset( $opts['o100_resv_rooms'] ) && is_array( $opts['o100_resv_rooms'] ) ? $opts['o100_resv_rooms'] : array();
		?>
		<div class="cmb-row" data-conditional-id="o100_resv_enable_rooms" data-conditional-value="on" style="padding:0;border:none;">
		<!-- Hidden field to store rooms JSON — CMB2 saves this -->
		<input type="hidden" name="o100_resv_rooms" id="o100_resv_rooms_data" value="<?php echo esc_attr( wp_json_encode( $rooms ) ); ?>">

		<!-- Table -->
		<table class="o100-rooms-table" id="o100-rooms-table">
			<thead>
				<tr>
					<th style="width:35%;"><?php esc_html_e( 'Room Name', 'order100' ); ?></th>
					<th style="width:15%;text-align:center;"><?php esc_html_e( 'Capacity', 'order100' ); ?></th>
					<th style="width:10%;text-align:center;"><?php esc_html_e( 'Qty', 'order100' ); ?></th>
					<th style="width:30%;"><?php esc_html_e( 'Description', 'order100' ); ?></th>
					<th style="width:10%;text-align:right;"><?php esc_html_e( 'Actions', 'order100' ); ?></th>
				</tr>
			</thead>
			<tbody id="o100-rooms-tbody">
				<!-- JS renders rows -->
			</tbody>
		</table>

		<div id="o100-rooms-empty" style="display:none; text-align:center; padding:32px 20px; color:#94a3b8; font-size:14px;">
			<span class="dashicons dashicons-admin-home" style="font-size:36px;width:36px;height:36px;color:#cbd5e1;display:block;margin:0 auto 10px;"></span>
			<?php esc_html_e( 'No rooms configured yet. Click the button below to add one.', 'order100' ); ?>
		</div>

		<button type="button" id="o100-rooms-add-btn" class="button button-primary" style="margin-top:12px; background:#2563eb; border-color:#2563eb; box-shadow:none; display:inline-flex; align-items:center; gap:4px; padding:6px 16px; font-size:13px; font-weight:500; border-radius:6px;">
			<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;"></span>
			<?php esc_html_e( 'Add Room', 'order100' ); ?>
		</button>

		<!-- Modal Overlay -->
		<div id="o100-room-modal-overlay" class="o100-room-modal-overlay" style="display:none;">
			<div class="o100-room-modal">
				<div class="o100-room-modal-header">
					<h3 id="o100-room-modal-title"><?php esc_html_e( 'Add Room', 'order100' ); ?></h3>
					<button type="button" id="o100-room-modal-close" class="o100-room-modal-close">&times;</button>
				</div>
				<div class="o100-room-modal-body">
					<div class="o100-room-modal-grid">
						<div class="o100-room-modal-field">
							<label><?php esc_html_e( 'Room Name', 'order100' ); ?> <span style="color:#ef4444;">*</span></label>
							<input type="text" id="o100-room-f-name" placeholder="<?php esc_attr_e( 'e.g. VIP Room A', 'order100' ); ?>">
						</div>
						<div class="o100-room-modal-field">
							<label><?php esc_html_e( 'Max Capacity', 'order100' ); ?></label>
							<input type="number" id="o100-room-f-capacity" min="1" placeholder="12">
						</div>
						<div class="o100-room-modal-field">
							<label><?php esc_html_e( 'Quantity', 'order100' ); ?></label>
							<input type="number" id="o100-room-f-quantity" min="1" placeholder="1">
						</div>
					</div>
					<div class="o100-room-modal-field" style="margin-top:12px;">
						<label><?php esc_html_e( 'Description', 'order100' ); ?></label>
						<textarea id="o100-room-f-desc" rows="2" placeholder="<?php esc_attr_e( 'Short description shown to customers...', 'order100' ); ?>"></textarea>
					</div>
				</div>
				<div class="o100-room-modal-footer">
					<button type="button" id="o100-room-modal-cancel" class="button"><?php esc_html_e( 'Cancel', 'order100' ); ?></button>
					<button type="button" id="o100-room-modal-save" class="button button-primary"><?php esc_html_e( 'Save Room', 'order100' ); ?></button>
				</div>
			</div>
		</div>
		</div>

		<style>
		/* ═══ Rooms Table ═══ */
		.o100-rooms-table {
			width: 100%; border-collapse: separate; border-spacing: 0;
			border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;
			background: #fff;
		}
		.o100-rooms-table th {
			background: #f8fafc; border-bottom: 2px solid #e2e8f0;
			padding: 10px 16px; font-size: 11px; font-weight: 600;
			text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;
			text-align: left;
		}
		.o100-rooms-table td {
			padding: 14px 16px; border-bottom: 1px solid #f1f5f9;
			font-size: 14px; color: #334155; vertical-align: middle;
		}
		.o100-rooms-table tbody tr:last-child td { border-bottom: none; }
		.o100-rooms-table tbody tr:hover td { background: #f8fafc; }
		.o100-rooms-table td strong { font-weight: 600; color: #0f172a; }
		.o100-rooms-table .o100-room-desc {
			font-size: 13px; color: #94a3b8; max-width: 220px;
			white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
			display: block;
		}
		.o100-rooms-table .o100-room-actions { white-space: nowrap; }
		.o100-rooms-table .o100-room-actions a {
			font-size: 13px; text-decoration: none; margin-left: 12px; cursor: pointer;
			font-weight: 500;
		}
		.o100-rooms-table .o100-room-actions .o100-room-edit { color: #2563eb; }
		.o100-rooms-table .o100-room-actions .o100-room-edit:hover { color: #1d4ed8; }
		.o100-rooms-table .o100-room-actions .o100-room-delete { color: #94a3b8; }
		.o100-rooms-table .o100-room-actions .o100-room-delete:hover { color: #ef4444; }
		.o100-rooms-table .o100-room-badge {
			display: inline-flex; align-items: center; justify-content: center;
			min-width: 32px; height: 26px; padding: 0 10px;
			background: #f1f5f9; border-radius: 6px;
			font-size: 13px; font-weight: 600; color: #475569;
		}
		/* ═══ Room Modal ═══ */
		.o100-room-modal-overlay {
			position: fixed; inset: 0; background: rgba(15,23,42,0.45);
			z-index: 100000; display: flex; align-items: center; justify-content: center;
		}
		.o100-room-modal {
			background: #fff; border-radius: 12px; width: 520px; max-width: 90vw;
			box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden;
		}
		.o100-room-modal-header {
			display: flex; align-items: center; justify-content: space-between;
			padding: 16px 20px; border-bottom: 1px solid #e5e7eb;
		}
		.o100-room-modal-header h3 { margin: 0; font-size: 16px; font-weight: 600; color: #0f172a; }
		.o100-room-modal-close {
			background: none; border: none; font-size: 22px; color: #94a3b8;
			cursor: pointer; padding: 0; line-height: 1;
		}
		.o100-room-modal-close:hover { color: #475569; }
		.o100-room-modal-body { padding: 20px; }
		.o100-room-modal-grid {
			display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 14px;
		}
		.o100-room-modal-field label {
			display: block; font-size: 12px; font-weight: 600; color: #64748b;
			text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px;
		}
		.o100-room-modal-field input,
		.o100-room-modal-field textarea {
			width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1;
			border-radius: 6px; font-size: 14px; color: #1e293b;
			box-sizing: border-box; transition: border-color 0.15s;
		}
		.o100-room-modal-field input:focus,
		.o100-room-modal-field textarea:focus {
			outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
		}
		.o100-room-modal-footer {
			display: flex; justify-content: flex-end; gap: 10px;
			padding: 14px 20px; border-top: 1px solid #e5e7eb; background: #f8fafc;
		}
		</style>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var rooms = [];
			try { rooms = JSON.parse( $('#o100_resv_rooms_data').val() ) || []; } catch(e) { rooms = []; }
			// Ensure array
			if (!Array.isArray(rooms)) rooms = [];

			var editIdx = -1; // -1 = adding new

			function renderTable() {
				var $tbody = $('#o100-rooms-tbody');
				$tbody.empty();
				if (rooms.length === 0) {
					$('#o100-rooms-table').hide();
					$('#o100-rooms-empty').show();
				} else {
					$('#o100-rooms-table').show();
					$('#o100-rooms-empty').hide();
					rooms.forEach(function(r, i) {
						var desc = r.desc ? '<span class="o100-room-desc">' + $('<span>').text(r.desc).html() + '</span>' : '<span style="color:#cbd5e1;">—</span>';
						$tbody.append(
							'<tr data-idx="' + i + '">' +
							'<td><strong>' + $('<span>').text(r.name || '').html() + '</strong></td>' +
							'<td style="text-align:center;"><span class="o100-room-badge">' + (parseInt(r.capacity) || '—') + '</span></td>' +
							'<td style="text-align:center;"><span class="o100-room-badge">' + (parseInt(r.quantity) || 1) + '</span></td>' +
							'<td>' + desc + '</td>' +
							'<td class="o100-room-actions" style="text-align:right;">' +
								'<a class="o100-room-edit" data-idx="' + i + '">Edit</a>' +
								'<a class="o100-room-delete" data-idx="' + i + '">Delete</a>' +
							'</td>' +
							'</tr>'
						);
					});
				}
				syncHidden();
			}

			function syncHidden() {
				$('#o100_resv_rooms_data').val( JSON.stringify(rooms) );
			}

			function openModal(idx) {
				editIdx = idx;
				if (idx >= 0 && rooms[idx]) {
					$('#o100-room-modal-title').text('<?php echo esc_js( __( 'Edit Room', 'order100' ) ); ?>');
					$('#o100-room-f-name').val(rooms[idx].name || '');
					$('#o100-room-f-capacity').val(rooms[idx].capacity || '');
					$('#o100-room-f-quantity').val(rooms[idx].quantity || '');
					$('#o100-room-f-desc').val(rooms[idx].desc || '');
				} else {
					$('#o100-room-modal-title').text('<?php echo esc_js( __( 'Add Room', 'order100' ) ); ?>');
					$('#o100-room-f-name').val('');
					$('#o100-room-f-capacity').val('');
					$('#o100-room-f-quantity').val('');
					$('#o100-room-f-desc').val('');
				}
				$('#o100-room-modal-overlay').fadeIn(150);
				setTimeout(function(){ $('#o100-room-f-name').focus(); }, 200);
			}

			function closeModal() {
				$('#o100-room-modal-overlay').fadeOut(150);
			}

			// Add button
			$('#o100-rooms-add-btn').on('click', function(){ openModal(-1); });

			// Edit
			$(document).on('click', '.o100-room-edit', function(e){
				e.preventDefault();
				openModal( parseInt($(this).data('idx')) );
			});

			// Delete
			$(document).on('click', '.o100-room-delete', function(e){
				e.preventDefault();
				var idx = parseInt($(this).data('idx'));
				if (confirm('<?php echo esc_js( __( 'Delete this room?', 'order100' ) ); ?>')) {
					rooms.splice(idx, 1);
					renderTable();
				}
			});

			// Save
			$('#o100-room-modal-save').on('click', function(){
				var name = $.trim( $('#o100-room-f-name').val() );
				if (!name) { $('#o100-room-f-name').focus(); return; }
				var room = {
					name:     name,
					capacity: $('#o100-room-f-capacity').val() || '',
					quantity: $('#o100-room-f-quantity').val() || '1',
					desc:     $.trim( $('#o100-room-f-desc').val() )
				};
				if (editIdx >= 0) {
					rooms[editIdx] = room;
				} else {
					rooms.push(room);
				}
				renderTable();
				closeModal();
			});

			// Cancel / close
			$('#o100-room-modal-cancel, #o100-room-modal-close').on('click', closeModal);
			$('#o100-room-modal-overlay').on('click', function(e){
				if (e.target === this) closeModal();
			});

			// Initial render
			renderTable();
		});
		</script>
		<?php
	}

	public function render_dinein_intro() {
		$options = get_option( 'o100_dinein', array() );
		$is_enabled = !empty($options['o100_enable_dinein']) && $options['o100_enable_dinein'] === 'on';
		?>
		<div class="o100-dinein-intro-wrap" style="display: <?php echo $is_enabled ? 'none' : 'flex'; ?>; flex-direction: column; align-items: center; justify-content: center; background: #fff; padding: 60px 40px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">
			<h2 style="font-size: 24px; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 16px;"><?php esc_html_e( 'Dine-in Mode', 'order100' ); ?></h2>
			<p style="font-size: 15px; color: #475569; margin-bottom: 32px; text-align: center; max-width: 600px; line-height: 1.6;"><?php esc_html_e( 'Allow customers to order from their tables using QR codes. Manage tables, service fees, and dine-in specific menus.', 'order100' ); ?></p>
			<button type="button" class="o100-start-dinein-btn" style="background: #22c55e; color: white; border: none; padding: 12px 32px; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);"><?php esc_html_e( 'Start Using Dine-in', 'order100' ); ?></button>
		</div>
		<style>
			.o100-start-dinein-btn:hover { background: #16a34a; transform: translateY(-1px); box-shadow: 0 6px 8px -1px rgba(34, 197, 94, 0.3); }
			.cmb2-id-o100-enable-dinein { display: none !important; }
			.cmb2-id-o100-dinein-intro { display: none !important; }
		</style>
		<?php if ( ! $is_enabled ) : ?>
		<style id="o100-dinein-hide-css">
			.cmb2-id-o100-dinein-intro ~ .cmb-row { display: none !important; }
			.cmb2-id-o100-dinein-intro ~ .o100-settings-group-card { display: none !important; }
		</style>
		<?php endif; ?>
		<script>
			jQuery(document).ready(function($){
				function updateDineinVisibility(speed) {
					var isChecked = $('#o100_enable_dinein').is(':checked');
					
					if (isChecked) {
						$('#o100-dinein-hide-css').remove();
						$('.o100-dinein-intro-wrap').slideUp(speed);
						$('.cmb2-id-o100-dinein-intro ~ .o100-settings-group-card').slideDown(speed);
						$('.cmb2-id-o100-dinein-intro ~ .cmb-row:not(.cmb2-id-o100-enable-dinein)').slideDown(speed);
					} else {
						$('.o100-dinein-intro-wrap').slideDown(speed);
						$('.cmb2-id-o100-dinein-intro ~ .o100-settings-group-card').slideUp(speed);
						$('.cmb2-id-o100-dinein-intro ~ .cmb-row:not(.cmb2-id-o100-enable-dinein)').slideUp(speed);
					}
				}

				setTimeout(function() {
					updateDineinVisibility(0);
				}, 10);

				$('.o100-start-dinein-btn').on('click', function(e){
					e.preventDefault();
					$('#o100_enable_dinein').prop('checked', true).trigger('change');
					$.post(ajaxurl, { action: 'o100_toggle_module', module: 'dinein', status: 'on' });
				});
				
				$(document).on('change', '#o100_enable_dinein', function(){
					updateDineinVisibility(200);
					if (!$(this).is(':checked')) {
						$.post(ajaxurl, { action: 'o100_toggle_module', module: 'dinein', status: '' });
					}
				});
			});
		</script>
		<?php
	}
	public function generate_timeslots_on_save( $object_id = null, $cmb_id = null ) {
		$store_hours = get_option( 'o100_store_hours', array() );
		$delivery    = get_option( 'o100_delivery', array() );
		$pickup      = get_option( 'o100_pickup', array() );
		
		// Merge them so we can read all configurations globally
		$options = array_merge( $store_hours, $delivery, $pickup );
		
		$weekdays = array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' );
		
		$modes = array(
			'global' => array(
				'prefix'   => 'o100_',
				'interval' => isset( $options['o100_global_interval'] ) && is_numeric( $options['o100_global_interval'] ) ? intval( $options['o100_global_interval'] ) : 30,
				'max'      => isset( $options['o100_global_max_order'] ) ? $options['o100_global_max_order'] : '',
				'dest'     => 'o100_global_generated_timeslots'
			),
			'delivery' => array(
				'prefix'   => 'o100_delivery_',
				'interval' => isset( $options['o100_delivery_interval'] ) && is_numeric( $options['o100_delivery_interval'] ) ? intval( $options['o100_delivery_interval'] ) : 30,
				'max'      => isset( $options['o100_delivery_max_order'] ) ? $options['o100_delivery_max_order'] : '',
				'dest'     => 'o100_delivery_generated_timeslots',
				'override' => isset( $options['o100_delivery_override_schedule'] ) ? $options['o100_delivery_override_schedule'] : 'off'
			),
			'pickup' => array(
				'prefix'   => 'o100_pickup_',
				'interval' => isset( $options['o100_pickup_interval'] ) && is_numeric( $options['o100_pickup_interval'] ) ? intval( $options['o100_pickup_interval'] ) : 30,
				'max'      => isset( $options['o100_pickup_max_order'] ) ? $options['o100_pickup_max_order'] : '',
				'dest'     => 'o100_pickup_generated_timeslots',
				'override' => isset( $options['o100_pickup_override_schedule'] ) ? $options['o100_pickup_override_schedule'] : 'off'
			)
		);
		
		foreach ( $modes as $mode => $config ) {
			if ( $mode !== 'global' && $config['override'] !== 'on' ) {
				continue;
			}
			
			$interval = max( 5, $config['interval'] );
			$default_max = $config['max'];
			
			// Load existing generated slots to preserve manual overrides
			$existing_json = isset( $options[$config['dest']] ) ? $options[$config['dest']] : '{}';
			$existing_slots = json_decode( wp_unslash( $existing_json ), true );
			if ( ! is_array( $existing_slots ) ) $existing_slots = array();
			
			$new_slots = array();
			
			foreach ( $weekdays as $day ) {
				$new_slots[$day] = array();
				
				$opcl_key = $config['prefix'] . $day . '_opcl_time';
				$day_hours = isset( $options[$opcl_key] ) ? $options[$opcl_key] : array();
				
				if ( ! is_array( $day_hours ) ) continue;
				
				foreach ( $day_hours as $range ) {
					if ( empty( $range['open-time'] ) || empty( $range['close-time'] ) ) continue;
					
					$start_ts = strtotime( "1970-01-01 " . $range['open-time'] . ":00" );
					$end_ts   = strtotime( "1970-01-01 " . $range['close-time'] . ":00" );
					
					// Handle past-midnight times (e.g. 10:00 to 02:00 next day)
					if ( $end_ts <= $start_ts ) {
						$end_ts += 86400; // add 24 hours
					}
					
					$current_ts = $start_ts;
					while ( $current_ts < $end_ts ) {
						$slot_end_ts = $current_ts + ($interval * 60);
						if ( $slot_end_ts > $end_ts ) {
							$slot_end_ts = $end_ts;
						}
						
						$start_str = gmdate( 'H:i', $current_ts );
						$end_str   = gmdate( 'H:i', $slot_end_ts );
						
						// Check if we have an existing override for this exact slot
						$slot_max = $default_max;
						if ( isset( $existing_slots[$day] ) ) {
							foreach ( $existing_slots[$day] as $existing_slot ) {
								if ( $existing_slot['start-time'] === $start_str && $existing_slot['end-time'] === $end_str ) {
									$slot_max = $existing_slot['max-odts'];
									break;
								}
							}
						}
						
						$new_slots[$day][] = array(
							'start-time' => $start_str,
							'end-time'   => $end_str,
							'max-odts'   => $slot_max,
							'name-ts'    => $start_str . ' - ' . $end_str,
						);
						
						$current_ts = $slot_end_ts;
					}
				}
			}
			
			
			if ( $mode === 'global' ) {
				$store_hours[$config['dest']] = wp_json_encode( $new_slots );
			} elseif ( $mode === 'delivery' ) {
				$delivery[$config['dest']] = wp_json_encode( $new_slots );
			} elseif ( $mode === 'pickup' ) {
				$pickup[$config['dest']] = wp_json_encode( $new_slots );
			}
			$options[$config['dest']] = wp_json_encode( $new_slots );
		}
		
		update_option( 'o100_store_hours', $store_hours );
		update_option( 'o100_delivery', $delivery );
		update_option( 'o100_pickup', $pickup );
		
		// After generating the JSON slots, we need to convert them to the legacy format `exwoofood_adv_timesl_options`
		// Removed legacy DB update
	}
	
	/**
	 * Custom CMB2 field type for WooCommerce Product Search
	 */
	public function render_product_search_field( $field, $escaped_value, $object_id, $object_type, $field_type ) {
		$field_id   = $field_type->_id();
		$field_name = $field_type->_name();
		
		// Ensure it's a comma separated string for the hidden input
		$val_string = '';
		$selected_ids = array();
		if ( ! empty( $escaped_value ) ) {
			if ( is_array( $escaped_value ) ) {
				$selected_ids = $escaped_value;
				$val_string = implode( ',', $escaped_value );
			} elseif ( is_string( $escaped_value ) ) {
				$val_string = $escaped_value;
				$selected_ids = array_map( 'trim', explode( ',', $escaped_value ) );
			}
		}

		$count = count( $selected_ids );
		$header_text = '';
		if ( $count === 0 ) {
			$header_text = '<span class="o100-mcd-placeholder">' . esc_html__( 'Selecting...', 'order100' ) . '</span>';
		} else {
			$max_pills = 3;
			$rendered = 0;
			foreach ( $selected_ids as $pid ) {
				if ( $rendered >= $max_pills ) break;
				$p = wc_get_product( $pid );
				if ( $p ) {
					$header_text .= '<span class="o100-mcd-pill" data-val="' . esc_attr($pid) . '">' . wp_kses_post( $p->get_formatted_name() ) . ' <i class="dashicons dashicons-no-alt"></i></span>';
					$rendered++;
				}
			}
			if ( $count > $max_pills ) {
				$header_text .= '<span class="o100-mcd-more">+' . ($count - $max_pills) . ' more</span>';
			}
		}

		$attrs = '';
		$attributes = $field->args( 'attributes' );
		if ( ! empty( $attributes['data-conditional-id'] ) ) {
			$attrs .= ' data-conditional-id="' . esc_attr( $attributes['data-conditional-id'] ) . '"';
		}
		if ( ! empty( $attributes['data-conditional-value'] ) ) {
			$attrs .= ' data-conditional-value="' . esc_attr( $attributes['data-conditional-value'] ) . '"';
		}

		echo '<div class="o100-mcd-wrapper" data-type="product" data-field-id="' . esc_attr( $field_id ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" value="' . esc_attr( $val_string ) . '" class="o100-mcd-hidden-input"' . $attrs . '>';
		
		echo '<div class="o100-mcd-header">';
		echo '<div class="o100-mcd-header-text">' . $header_text . '</div>';
		echo '<i class="dashicons dashicons-arrow-down-alt2"></i>';
		echo '</div>';

		echo '<div class="o100-mcd-popover">';
		echo '<div class="o100-mcd-search"><input type="text" placeholder="' . esc_attr__( 'Search unselected...', 'order100' ) . '"><span class="dashicons dashicons-search"></span></div>';
		
		echo '<div class="o100-mcd-list">';
		
		// Render already selected items first
		if ( ! empty( $selected_ids ) ) {
			foreach ( $selected_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( is_object( $product ) ) {
					echo '<label class="o100-mcd-item is-selected">';
					echo '<input type="checkbox" value="' . esc_attr( $product_id ) . '" checked="checked">';
					echo '<span>' . wp_kses_post( $product->get_formatted_name() ) . '</span>';
					echo '</label>';
				}
			}
			// Render a divider if there are selected items
			echo '<div class="o100-mcd-divider"></div>';
		}
		
		echo '<div class="o100-mcd-results"></div>'; // AJAX results go here
		
		echo '</div>'; // end list
		echo '</div>'; // end popover
		echo '</div>'; // end wrapper
		
		echo $field_type->_desc( true );
	}

	/**
	 * Custom CMB2 field type for WooCommerce Category Search
	 */
	public function render_category_search_field( $field, $escaped_value, $object_id, $object_type, $field_type ) {
		$field_id   = $field_type->_id();
		$field_name = $field_type->_name();
		
		// Ensure it's a comma separated string for the hidden input
		$val_string = '';
		$selected_ids = array();
		if ( ! empty( $escaped_value ) ) {
			if ( is_array( $escaped_value ) ) {
				$selected_ids = $escaped_value;
				$val_string = implode( ',', $escaped_value );
			} elseif ( is_string( $escaped_value ) ) {
				$val_string = $escaped_value;
				$selected_ids = array_map( 'trim', explode( ',', $escaped_value ) );
			}
		}

		$count = count( $selected_ids );
		$header_text = '';
		if ( $count === 0 ) {
			$header_text = '<span class="o100-mcd-placeholder">' . esc_html__( 'Selecting...', 'order100' ) . '</span>';
		} else {
			$max_pills = 3;
			$rendered = 0;
			foreach ( $selected_ids as $cat_id ) {
				if ( $rendered >= $max_pills ) break;
				$term = get_term_by( 'id', $cat_id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$header_text .= '<span class="o100-mcd-pill" data-val="' . esc_attr($cat_id) . '">' . wp_kses_post( $term->name ) . ' <i class="dashicons dashicons-no-alt"></i></span>';
					$rendered++;
				}
			}
			if ( $count > $max_pills ) {
				$header_text .= '<span class="o100-mcd-more">+' . ($count - $max_pills) . ' more</span>';
			}
		}

		$attrs = '';
		$attributes = $field->args( 'attributes' );
		if ( ! empty( $attributes['data-conditional-id'] ) ) {
			$attrs .= ' data-conditional-id="' . esc_attr( $attributes['data-conditional-id'] ) . '"';
		}
		if ( ! empty( $attributes['data-conditional-value'] ) ) {
			$attrs .= ' data-conditional-value="' . esc_attr( $attributes['data-conditional-value'] ) . '"';
		}

		echo '<div class="o100-mcd-wrapper" data-type="category" data-field-id="' . esc_attr( $field_id ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" value="' . esc_attr( $val_string ) . '" class="o100-mcd-hidden-input"' . $attrs . '>';
		
		echo '<div class="o100-mcd-header">';
		echo '<div class="o100-mcd-header-text">' . $header_text . '</div>';
		echo '<i class="dashicons dashicons-arrow-down-alt2"></i>';
		echo '</div>';

		echo '<div class="o100-mcd-popover">';
		echo '<div class="o100-mcd-search"><input type="text" placeholder="' . esc_attr__( 'Search unselected...', 'order100' ) . '"><span class="dashicons dashicons-search"></span></div>';
		
		echo '<div class="o100-mcd-list">';
		
		// Render already selected items first
		if ( ! empty( $selected_ids ) ) {
			foreach ( $selected_ids as $cat_id ) {
				$term = get_term_by( 'id', $cat_id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					echo '<label class="o100-mcd-item is-selected">';
					echo '<input type="checkbox" value="' . esc_attr( $cat_id ) . '" checked="checked">';
					echo '<span>' . wp_kses_post( $term->name ) . '</span>';
					echo '</label>';
				}
			}
			// Render a divider if there are selected items
			echo '<div class="o100-mcd-divider"></div>';
		}
		
		echo '<div class="o100-mcd-results"></div>'; // AJAX results go here
		
		echo '</div>'; // end list
		echo '</div>'; // end popover
		echo '</div>'; // end wrapper
		
		echo $field_type->_desc( true );
	}

	public function ajax_search_products() {
		check_ajax_referer( 'o100_admin_nonce', 'nonce' );
		
		$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
		$exclude_str = isset( $_POST['exclude'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude'] ) ) : '';
		$exclude = ! empty( $exclude_str ) ? array_map( 'intval', explode( ',', $exclude_str ) ) : array();
		
		$product_ids = array();
		
		if ( empty( $term ) ) {
			// Fetch 30 latest products if term is empty
			$args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 30,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC'
			);
			$query = new WP_Query( $args );
			$product_ids = $query->posts;
		} else {
			// Search products via WooCommerce Data Store
			$data_store = WC_Data_Store::load( 'product' );
			$product_ids = $data_store->search_products( $term, '', true ); // Search by name/sku
			
			// Fallback to standard WP_Query if data store returns nothing
			if ( empty( $product_ids ) ) {
				$args = array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 30,
					's'              => $term,
					'fields'         => 'ids'
				);
				$query = new WP_Query( $args );
				$product_ids = $query->posts;
			}
		}
		
		$results = array();
		
		if ( ! empty( $product_ids ) && is_array( $product_ids ) ) {
			foreach ( $product_ids as $post_id ) {
				if ( ! empty( $exclude ) && in_array( $post_id, $exclude ) ) {
					continue;
				}
				$product = wc_get_product( $post_id );
				if ( $product ) {
					$results[] = array(
						'id'   => $product->get_id(),
						'text' => $product->get_formatted_name()
					);
				}
			}
		}
		
		if ( empty( $results ) ) {
			// DEBUG INFO if empty
			$debug_args = isset($args) ? $args : 'WC Data Store';
			$results[] = array(
				'id'   => 0,
				'text' => 'DEBUG: Found IDs: ' . json_encode($product_ids) . ' | Exclude: ' . json_encode($exclude)
			);
		}
		
		wp_send_json_success( $results );
	}

	public function ajax_search_categories() {
		check_ajax_referer( 'o100_admin_nonce', 'nonce' );
		
		$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
		$exclude_str = isset( $_POST['exclude'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude'] ) ) : '';
		$exclude = ! empty( $exclude_str ) ? array_map( 'intval', explode( ',', $exclude_str ) ) : array();
		
		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => 50,
			'exclude'    => $exclude
		);
		
		if ( ! empty( $term ) ) {
			$args['search'] = $term;
		}
		
		$terms = get_terms( $args );
		$results = array();
		
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $cat ) {
				$results[] = array(
					'id'   => $cat->term_id,
					'text' => $cat->name
				);
			}
		}
		
		if ( empty( $results ) ) {
			// DEBUG INFO
			$err = is_wp_error($terms) ? $terms->get_error_message() : 'Empty array';
			$results[] = array(
				'id'   => 0,
				'text' => 'DEBUG: Cat fetch failed: ' . $err . ' | args: ' . json_encode($args)
			);
		}
		
		wp_send_json_success( $results );
	}


	public static function get_formatted_holidays() {
		$options = get_option( 'o100_store_hours', array() );
		if ( empty( $options['o100_enable_holidays'] ) || $options['o100_enable_holidays'] !== 'on' ) {
			return array();
		}
		
		$holidays = isset( $options['o100_holidays_list'] ) && is_array( $options['o100_holidays_list'] ) ? $options['o100_holidays_list'] : array();
		$formatted = array();
		
		foreach ( $holidays as $idx => $date_group ) {
			$start_dt = isset( $date_group['start'] ) ? trim( $date_group['start'] ) : '';
			$end_dt   = isset( $date_group['end'] ) ? trim( $date_group['end'] ) : '';
			
			$opcl_start = '';
			$opcl_end   = '';

			if ( ! empty( $start_dt ) ) {
				if ( strlen( $start_dt ) <= 10 ) $start_dt .= 'T00:00:00';
				$opcl_start = strtotime( $start_dt );
			}
			
			if ( ! empty( $end_dt ) ) {
				if ( strlen( $end_dt ) <= 10 ) $end_dt .= 'T23:59:59';
				$opcl_end = strtotime( $end_dt );
			}

			if ( empty( $opcl_start ) && empty( $opcl_end ) ) continue;

			$reason = isset( $date_group['reason'] ) ? $date_group['reason'] : '';
			if ( $reason === 'custom' && isset( $date_group['reason_custom'] ) ) {
				$reason = trim( $date_group['reason_custom'] );
			}

			$formatted[] = array(
				'opcl_start'   => $opcl_start,
				'opcl_end'     => $opcl_end,
				'close_reason' => $reason,
			);
		}
		
		return $formatted;
	}

	/**
	 * Render the Store Profile using Fluent CRM style
	 */
	public static function render_fluent_store_profile() {
		$opts = get_option('o100_store_profile', array());
		$get_val = function($key, $default = '') use ($opts) {
			return isset($opts[$key]) ? $opts[$key] : $default;
		};
		
		$store_name = $get_val('o100_store_name');
		$store_logo = $get_val('o100_store_logo');
		$store_phone_code = $get_val('o100_store_phone_code', '+1');
		$store_phone = $get_val('o100_store_phone');
		$store_email = $get_val('o100_store_email');
		$store_address = $get_val('o100_store_address');
		$store_type = $get_val('o100_store_type');
		$store_features = $get_val('o100_store_features', array());
		if (!is_array($store_features)) $store_features = array();

		wp_enqueue_media();
		?>
		<div class="o100-fluent-form-wrapper" id="o100-fluent-store-profile">
			<div class="o100-card-box" style="border:none; box-shadow:none; margin:0;">
				<div class="o100-card-body" style="padding: 10px 0;">
					<form id="o100-store-profile-form">
						<div class="o100-modal-row">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Store Name', 'order100' ); ?></label>
								</div>
								<input type="text" name="o100_store_name" class="o100-modal-input" value="<?php echo esc_attr( $store_name ); ?>">
							</div>
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Store Logo', 'order100' ); ?></label>
								</div>
								<div style="display:flex; gap:10px; align-items:center;">
									<div class="o100-logo-preview" style="width: 40px; height: 40px; border-radius: 6px; border: 1px solid #e2e8f0; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
										<?php if ( $store_logo ) : ?>
											<img src="<?php echo esc_url( $store_logo ); ?>" style="max-width:100%; max-height:100%; object-fit:cover;">
										<?php else : ?>
											<span class="dashicons dashicons-format-image" style="color:#cbd5e1;"></span>
										<?php endif; ?>
									</div>
									<input type="text" name="o100_store_logo" id="o100_store_logo" class="o100-modal-input" value="<?php echo esc_attr( $store_logo ); ?>" readonly placeholder="<?php esc_attr_e( 'No image selected', 'order100' ); ?>">
									<button type="button" class="o100-btn-secondary o100-upload-logo-btn"><?php esc_html_e( 'Upload', 'order100' ); ?></button>
									<button type="button" class="o100-btn-secondary o100-clear-logo-btn" style="<?php echo empty($store_logo) ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Clear', 'order100' ); ?></button>
								</div>
							</div>
						</div>

						<div class="o100-modal-row">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Store Phone', 'order100' ); ?></label>
								</div>
								<div style="display:flex; gap:10px;">
									<select name="o100_store_phone_code" class="o100-modal-input" style="width: 100px !important; flex: 0 0 100px !important; padding-right: 8px;">
										<?php
										$country_codes = array(
											'+1' => '🇺🇸/🇨🇦 +1', '+44' => '🇬🇧 +44', '+86' => '🇨🇳 +86', '+61' => '🇦🇺 +61', '+81' => '🇯🇵 +81', '+33' => '🇫🇷 +33', 
											'+49' => '🇩🇪 +49', '+39' => '🇮🇹 +39', '+34' => '🇪🇸 +34', '+7' => '🇷🇺/🇰🇿 +7', '+55' => '🇧🇷 +55', '+91' => '🇮🇳 +91', 
											'+82' => '🇰🇷 +82', '+52' => '🇲🇽 +52', '+62' => '🇮🇩 +62', '+60' => '🇲🇾 +60', '+65' => '🇸🇬 +65', '+63' => '🇵🇭 +63', 
											'+66' => '🇹🇭 +66', '+84' => '🇻🇳 +84', '+971' => '🇦🇪 +971', '+966' => '🇸🇦 +966', '+27' => '🇿🇦 +27', '+20' => '🇪🇬 +20', 
											'+234' => '🇳🇬 +234', '+54' => '🇦🇷 +54', '+56' => '🇨🇱 +56', '+57' => '🇨🇴 +57', '+51' => '🇵🇪 +51', '+31' => '🇳🇱 +31', 
											'+32' => '🇧🇪 +32', '+41' => '🇨🇭 +41', '+43' => '🇦🇹 +43', '+46' => '🇸🇪 +46', '+47' => '🇳🇴 +47', '+45' => '🇩🇰 +45', 
											'+358' => '🇫🇮 +358', '+48' => '🇵🇱 +48', '+420' => '🇨🇿 +420', '+36' => '🇭🇺 +36', '+30' => '🇬🇷 +30', '+351' => '🇵🇹 +351', 
											'+353' => '🇮🇪 +353', '+64' => '🇳🇿 +64', '+852' => '🇭🇰 +852', '+886' => '🇹🇼 +886', '+853' => '🇲🇴 +853', '+90' => '🇹🇷 +90'
										);
										foreach ($country_codes as $code => $label) {
											echo '<option value="'.esc_attr($code).'" '.selected($store_phone_code, $code, false).'>'.esc_html($label).'</option>';
										}
										?>
									</select>
									<input type="text" name="o100_store_phone" class="o100-modal-input" style="flex:1;" value="<?php echo esc_attr( $store_phone ); ?>" pattern="^\d+$" title="Please enter numbers only">
								</div>
							</div>
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Store Email', 'order100' ); ?></label>
								</div>
								<input type="email" name="o100_store_email" class="o100-modal-input" value="<?php echo esc_attr( $store_email ); ?>">
							</div>
						</div>

						<div class="o100-modal-row">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Store Address', 'order100' ); ?></label>
								</div>
								<input type="text" name="o100_store_address" class="o100-modal-input" value="<?php echo esc_attr( $store_address ); ?>">
							</div>
						</div>

						<div class="o100-modal-row">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Store Type', 'order100' ); ?></label>
								</div>
								<select name="o100_store_type" class="o100-modal-input">
									<?php
									$types = array(
										'restaurant'    => 'Restaurant', 
										'cafe'          => 'Cafe & Coffee Shop', 
										'bakery'        => 'Bakery & Dessert', 
										'bubbletea'     => 'Bubble Tea & Juice Bar',
										'pizza'         => 'Pizzeria', 
										'fastfood'      => 'Fast Food', 
										'foodtruck'     => 'Food Truck',
										'ghostkitchen'  => 'Ghost Kitchen',
										'grocery'       => 'Grocery & Convenience',
										'butcher'       => 'Butcher & Meat Shop',
										'seafood'       => 'Seafood Market',
										'florist'       => 'Florist',
										'other'         => 'Other'
									);
									foreach ( $types as $k => $v ) {
										$sel = selected( $store_type, $k, false );
										echo '<option value="'.esc_attr($k).'" '.$sel.'>'.esc_html($v).'</option>';
									}
									?>
								</select>
							</div>
							<div class="o100-modal-col"></div>
						</div>

						<div class="o100-modal-field" style="margin-top: 24px;">
							<div class="o100-modal-field-header" style="margin-bottom: 8px;">
								<label><?php esc_html_e( 'Store Features', 'order100' ); ?></label>
							</div>
							<div class="o100-help-text" style="display:block; margin-bottom: 16px;">
								<?php esc_html_e( 'List the key highlights of your store. Example: 24-hour temperature-controlled wine cellar with over 200 vintage selections.', 'order100' ); ?>
							</div>
							
							<div class="o100-features-repeater" id="o100-features-list">
								<?php if ( empty( $store_features ) ) : ?>
									<div class="o100-feature-row">
										<input type="text" name="o100_store_features[]" class="o100-modal-input" placeholder="<?php esc_attr_e( 'Enter a feature...', 'order100' ); ?>">
										<button type="button" class="o100-feature-remove"><span class="dashicons dashicons-no"></span></button>
									</div>
								<?php else : ?>
									<?php foreach ( $store_features as $feat ) : ?>
										<div class="o100-feature-row">
											<input type="text" name="o100_store_features[]" class="o100-modal-input" value="<?php echo esc_attr( $feat ); ?>">
											<button type="button" class="o100-feature-remove"><span class="dashicons dashicons-no"></span></button>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
							<button type="button" class="o100-btn-secondary o100-feature-add" style="margin-top: 12px; gap: 4px;">
								<span class="dashicons dashicons-plus-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span> <?php esc_html_e( 'Add New Feature', 'order100' ); ?>
							</button>
						</div>

					</form>
				</div>
			</div>
		</div>


		<script>
		jQuery(document).ready(function($) {
			var $profileForm = $('#o100-store-profile-form');
			
			// WP Media Uploader
			var mediaUploader;
			$('.o100-upload-logo-btn').on('click', function(e) {
				e.preventDefault();
				if (mediaUploader) {
					mediaUploader.open();
					return;
				}
				mediaUploader = wp.media.frames.file_frame = wp.media({
					title: '<?php echo esc_js( __( 'Choose Store Logo', 'order100' ) ); ?>',
					button: { text: '<?php echo esc_js( __( 'Choose Logo', 'order100' ) ); ?>' },
					multiple: false
				});
				mediaUploader.on('select', function() {
					var attachment = mediaUploader.state().get('selection').first().toJSON();
					$('#o100_store_logo').val(attachment.url).trigger('change');
					$('.o100-logo-preview').html('<img src="' + attachment.url + '" style="max-width:100%; max-height:100%; object-fit:cover;">');
					$('.o100-clear-logo-btn').show();
				});
				mediaUploader.open();
			});

			$('.o100-clear-logo-btn').on('click', function() {
				$('#o100_store_logo').val('').trigger('change');
				$('.o100-logo-preview').html('<span class="dashicons dashicons-format-image" style="color:#cbd5e1;"></span>');
				$(this).hide();
			});

			// Repeater Add
			$('.o100-feature-add').on('click', function() {
				var rowHtml = '<div class="o100-feature-row" style="display:none;">' +
								'<input type="text" name="o100_store_features[]" class="o100-modal-input" placeholder="<?php echo esc_js( __( 'Enter a feature...', 'order100' ) ); ?>">' +
								'<button type="button" class="o100-feature-remove"><span class="dashicons dashicons-no"></span></button>' +
							  '</div>';
				var $row = $(rowHtml);
				$('#o100-features-list').append($row);
				$row.slideDown(200);
				$profileForm.trigger('change');
			});

			// Repeater Remove
			$('#o100-features-list').on('click', '.o100-feature-remove', function() {
				$(this).closest('.o100-feature-row').slideUp(200, function() {
					$(this).remove();
					$profileForm.trigger('change');
				});
			});

			// Save Settings for Store Profile
			var $topSaveBtn = $('.o100-fluent-top-save');
			$topSaveBtn.on('click', function(e) {
				// Only hijack the save button if we are on the store profile tab (the form exists)
				if ($profileForm.length === 0) return;
				
				e.preventDefault();
				e.stopImmediatePropagation(); // Prevent the old CMB2 save logic
				
				var $btn = $(this);
				if ($btn.hasClass('o100-saving') || $btn.hasClass('o100-save-disabled')) return;
				
				$btn.addClass('o100-saving').text('<?php echo esc_js( __( 'Saving...', 'order100' ) ); ?>');
				
				var formData = $profileForm.serializeArray();
				var payload = {
					action: 'o100_save_store_profile',
					o100_store_profile: {}
				};
				
				// Group features array
				var features = [];
				$.each(formData, function(i, field) {
					if (field.name === 'o100_store_features[]') {
						if ($.trim(field.value) !== '') {
							features.push(field.value);
						}
					} else {
						payload.o100_store_profile[field.name] = field.value;
					}
				});
				payload.o100_store_profile['o100_store_features'] = features;

				$.post(ajaxurl, payload, function(res) {
					$btn.removeClass('o100-saving').text('<?php echo esc_js( __( 'Save Settings', 'order100' ) ); ?>');
					$btn.addClass('o100-save-disabled'); // Reset to disabled since changes are saved
					
					// Show toast
					var $toast = $('<div class="o100-toast o100-toast--visible">' +
						'<div class="o100-toast-icon">✓</div>' +
						'<div class="o100-toast-body"><h4>Great!</h4><p>Profile Saved Successfully.</p></div>' +
						'<button class="o100-toast-close" type="button">×</button>' +
					'</div>');
					$('body').append($toast);
					
					setTimeout(function() {
						$toast.removeClass('o100-toast--visible');
						setTimeout(function() { $toast.remove(); }, 300);
					}, 3000);
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Fluent UI Render for Checkout Ext (Tipping) Tab
	 */
	public static function render_fluent_checkout_ext() {
		$options = get_option( 'o100_options', array() );

		// Defaults (legacy fallback handled in frontend, but here we just show what's saved)
		$tip_delivery = ! empty( $options['o100_tip_delivery_enable'] );
		$tip_pickup   = ! empty( $options['o100_tip_pickup_enable'] );
		
		// If never initialized, default to enabled
		if ( empty( $options['o100_tip_control_initialized'] ) ) {
			$tip_delivery = true;
			$tip_pickup = true;
		}

		$tip_pos   = ! empty( $options['o100_tip_pos'] ) ? $options['o100_tip_pos'] : 'before_checkout';
		$tip_title = ! empty( $options['o100_tip_title'] ) ? $options['o100_tip_title'] : 'Help keep our team thriving during challenging time';
		$tip_btn   = ! empty( $options['o100_tip_btn'] ) ? $options['o100_tip_btn'] : 'Show Your Appreciation';
		$tip_rmbtn = ! empty( $options['o100_tip_rmbtn'] ) ? $options['o100_tip_rmbtn'] : 'Not Satisfied';
		$tip_val   = ! empty( $options['o100_tip_val'] ) ? $options['o100_tip_val'] : '10,15,20';
		$tip_type  = ! empty( $options['o100_tip_type'] ) ? $options['o100_tip_type'] : 'percent';

		?>
		<div class="o100-fluent-form-wrapper" id="o100-fluent-checkout-ext">
			<form id="o100-checkout-ext-form">
				<div class="o100-card-box" style="border:none; box-shadow:none; margin:0;">
					<div class="o100-card-body" style="padding: 10px 0;">
						
						<!-- Tipping Options -->
						<div class="o100-modal-row" style="margin-bottom: 24px;">
							<div class="o100-modal-field o100-modal-col">
								<style>
									@media (max-width: 768px) {
										.o100-tipping-toggles {
											flex-direction: column !important;
											gap: 16px !important;
										}
									}
								</style>
								<div class="cmb-type-checkbox o100-tipping-toggles" style="display:flex; gap: 24px; margin-bottom: 20px; flex-wrap: wrap; justify-content: flex-start;">
									<div class="o100-toggle-wrap" style="justify-content: flex-start; width: auto; flex: 0 0 auto;">
										<input type="checkbox" name="o100_tip_delivery_enable" value="on" <?php checked( $tip_delivery ); ?>>
										<span style="font-size: 14px; font-weight: 500; color: #1e293b;"><?php esc_html_e( 'Enable for Delivery', 'order100' ); ?></span>
									</div>
									<div class="o100-toggle-wrap" style="justify-content: flex-start; width: auto; flex: 0 0 auto;">
										<input type="checkbox" name="o100_tip_pickup_enable" value="on" <?php checked( $tip_pickup ); ?>>
										<span style="font-size: 14px; font-weight: 500; color: #1e293b;"><?php esc_html_e( 'Enable for Pickup', 'order100' ); ?></span>
									</div>
								</div>
							</div>
						</div>

						<div class="o100-modal-row">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Display Position', 'order100' ); ?></label>
								</div>
								<select name="o100_tip_pos" class="o100-modal-input">
									<option value="before_checkout" <?php selected( $tip_pos, 'before_checkout' ); ?>><?php esc_html_e( 'Before checkout form', 'order100' ); ?></option>
									<option value="after_customer" <?php selected( $tip_pos, 'after_customer' ); ?>><?php esc_html_e( 'After customer details', 'order100' ); ?></option>
									<option value="after_review" <?php selected( $tip_pos, 'after_review' ); ?>><?php esc_html_e( 'After order review', 'order100' ); ?></option>
								</select>
							</div>
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Tipping Section Title', 'order100' ); ?></label>
								</div>
								<input type="text" name="o100_tip_title" class="o100-modal-input" value="<?php echo esc_attr( $tip_title ); ?>" placeholder="<?php esc_attr_e( 'Help keep our team thriving during challenging time', 'order100' ); ?>">
							</div>
						</div>

						<div class="o100-modal-row">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Add Button Label', 'order100' ); ?></label>
								</div>
								<input type="text" name="o100_tip_btn" class="o100-modal-input" value="<?php echo esc_attr( $tip_btn ); ?>" placeholder="<?php esc_attr_e( 'Show Your Appreciation', 'order100' ); ?>">
							</div>
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Remove Button Label', 'order100' ); ?></label>
								</div>
								<input type="text" name="o100_tip_rmbtn" class="o100-modal-input" value="<?php echo esc_attr( $tip_rmbtn ); ?>" placeholder="<?php esc_attr_e( 'Not Satisfied', 'order100' ); ?>">
							</div>
						</div>

						<div class="o100-modal-row">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Preset Values', 'order100' ); ?></label>
									<span style="font-size: 12px; color: #94a3b8; font-weight: normal; margin-left: 8px;">(Comma separated, e.g. 10,15,20)</span>
								</div>
								<input type="text" name="o100_tip_val" class="o100-modal-input" value="<?php echo esc_attr( $tip_val ); ?>" placeholder="10,15,20">
							</div>
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Preset Type', 'order100' ); ?></label>
								</div>
								<select name="o100_tip_type" class="o100-modal-input">
									<option value="percent" <?php selected( $tip_type, 'percent' ); ?>><?php esc_html_e( 'Percentage (%)', 'order100' ); ?></option>
									<option value="fixed" <?php selected( $tip_type, 'fixed' ); ?>><?php esc_html_e( 'Fixed Amount ($)', 'order100' ); ?></option>
								</select>
							</div>
						</div>

					</div>
				</div>
				</div>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var $tippingForm = $('#o100-checkout-ext-form');
			if ($tippingForm.length === 0) return;

			// Save Settings for Tipping
			var $topSaveBtn = $('.o100-fluent-top-save');
			$topSaveBtn.on('click', function(e) {
				if ($tippingForm.length === 0) return;
				
				e.preventDefault();
				e.stopImmediatePropagation();
				
				var $btn = $(this);
				if ($btn.hasClass('o100-saving') || $btn.hasClass('o100-save-disabled')) return;
				
				$btn.addClass('o100-saving').text('<?php echo esc_js( __( 'Saving...', 'order100' ) ); ?>');
				
				var formData = $tippingForm.serializeArray();
				var payload = {
					action: 'o100_save_checkout_ext',
					o100_checkout_ext: {}
				};
				
				$.each(formData, function(i, field) {
					payload.o100_checkout_ext[field.name] = field.value;
				});

				$.post(ajaxurl, payload, function(res) {
					$btn.removeClass('o100-saving').text('<?php echo esc_js( __( 'Save Settings', 'order100' ) ); ?>');
					$btn.addClass('o100-save-disabled');
					
					var $toast = $('<div class="o100-toast o100-toast--visible">' +
						'<div class="o100-toast-icon">✓</div>' +
						'<div class="o100-toast-body"><h4>Great!</h4><p>Tipping Options Saved.</p></div>' +
						'<button class="o100-toast-close" type="button">×</button>' +
					'</div>');
					$('body').append($toast);
					
					setTimeout(function() {
						$toast.removeClass('o100-toast--visible');
						setTimeout(function() { $toast.remove(); }, 300);
					}, 3000);
				});
			});
		});
		</script>
		<?php
	}

	public function ajax_save_delivery() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'order100' ) ) );
		}

		if ( isset( $_POST['o100_delivery'] ) ) {
			$raw_data = wp_unslash( $_POST['o100_delivery'] );
			$data     = array();
			foreach ( $raw_data as $key => $value ) {
				if ( is_array( $value ) ) {
					$data[ sanitize_text_field( $key ) ] = array_map( 'sanitize_text_field', $value );
				} else {
					// Don't sanitize instruction text too aggressively to allow newlines
					if ( $key === 'o100_delivery_instruction_options' || $key === 'o100_shp_postcodes' ) {
						$data[ sanitize_text_field( $key ) ] = sanitize_textarea_field( $value );
					} else {
						$data[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
					}
				}
			}
			
			if ( isset($raw_data['o100_shp_km_loc']) && is_array($raw_data['o100_shp_km_loc']) ) {
				$km_data = array();
				foreach ($raw_data['o100_shp_km_loc'] as $rule) {
					$km_data[] = array(
						'max_distance' => sanitize_text_field( $rule['max_distance'] ?? '' ),
						'fee'          => sanitize_text_field( $rule['fee'] ?? '' ),
						'free'         => sanitize_text_field( $rule['free'] ?? '' ),
						'min_amount'   => sanitize_text_field( $rule['min_amount'] ?? '' )
					);
				}
				// Re-index array nicely
				$data['o100_shp_km_loc'] = array_values($km_data);
			} else {
				$data['o100_shp_km_loc'] = array();
			}

			// For repeater: o100_shp_zip_loc
			if ( isset($raw_data['o100_shp_zip_loc']) && is_array($raw_data['o100_shp_zip_loc']) ) {
				$zip_data = array();
				foreach ($raw_data['o100_shp_zip_loc'] as $rule) {
					$zip_data[] = array(
						'postcode'   => sanitize_text_field( $rule['postcode'] ?? '' ),
						'fee'        => sanitize_text_field( $rule['fee'] ?? '' ),
						'free'       => sanitize_text_field( $rule['free'] ?? '' ),
						'min_amount' => sanitize_text_field( $rule['min_amount'] ?? '' )
					);
				}
				// Re-index array nicely
				$data['o100_shp_zip_loc'] = array_values($zip_data);
			} else {
				$data['o100_shp_zip_loc'] = array();
			}

			update_option( 'o100_delivery', $data );
			wp_send_json_success( array( 'message' => __( 'Delivery options saved successfully.', 'order100' ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'Invalid data.', 'order100' ) ) );
	}

	public function ajax_save_pickup() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'order100' ) ) );
		}

		if ( isset( $_POST['o100_pickup'] ) ) {
			$raw_data = wp_unslash( $_POST['o100_pickup'] );
			$data     = array();
			foreach ( $raw_data as $key => $value ) {
				if ( is_array( $value ) ) {
					$data[ sanitize_text_field( $key ) ] = array_map( 'sanitize_text_field', $value );
				} else {
					$data[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
				}
			}

			update_option( 'o100_pickup', $data );
			wp_send_json_success( array( 'message' => __( 'Pickup options saved successfully.', 'order100' ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'Invalid data.', 'order100' ) ) );
	}

	public static function render_fluent_delivery() {
		$opts = get_option('o100_delivery', array());
		
		$enable_delivery = isset($opts['o100_enable_delivery']) && $opts['o100_enable_delivery'] === 'on';
		$min_amount = isset($opts['o100_delivery_min_amount']) ? $opts['o100_delivery_min_amount'] : '';
		$lead_time = isset($opts['o100_delivery_lead_time']) ? $opts['o100_delivery_lead_time'] : '';
		
		$limit_shp = isset($opts['o100_limit_shp']) ? $opts['o100_limit_shp'] : 'radius';
		$deli_dis = isset($opts['o100_deli_dis']) ? $opts['o100_deli_dis'] : '';
		$deli_mode = isset($opts['o100_deli_mode']) ? $opts['o100_deli_mode'] : 'driving';
		
		$shipping_fee = isset($opts['o100_shipping_fee']) ? $opts['o100_shipping_fee'] : '';
		$shipping_freemax = isset($opts['o100_shipping_freemax']) ? $opts['o100_shipping_freemax'] : '';
		$shp_timeslot = isset($opts['o100_shp_timeslot']) && $opts['o100_shp_timeslot'] === 'on';
		$enable_shp_km = isset($opts['o100_enable_shp_km']) && $opts['o100_enable_shp_km'] === 'on';
		$shp_km_loc = isset($opts['o100_shp_km_loc']) && is_array($opts['o100_shp_km_loc']) ? $opts['o100_shp_km_loc'] : array();
		
		$shp_postcodes = isset($opts['o100_shp_postcodes']) ? $opts['o100_shp_postcodes'] : '';
		$enable_shp_zip = isset($opts['o100_enable_shp_zip']) && $opts['o100_enable_shp_zip'] === 'on';
		$shp_zip_loc = isset($opts['o100_shp_zip_loc']) && is_array($opts['o100_shp_zip_loc']) ? $opts['o100_shp_zip_loc'] : array();
		
		$fee_action = isset($opts['o100_delivery_fee_action']) ? $opts['o100_delivery_fee_action'] : 'none';
		$fee_type = isset($opts['o100_delivery_fee_type']) ? $opts['o100_delivery_fee_type'] : 'percent';
		$fee_val = isset($opts['o100_delivery_fee_val']) ? $opts['o100_delivery_fee_val'] : '';
		$fee_label = isset($opts['o100_delivery_fee_label']) ? $opts['o100_delivery_fee_label'] : '';
		$exclu_sur = isset($opts['o100_delivery_exclu_sur']) && is_array($opts['o100_delivery_exclu_sur']) ? $opts['o100_delivery_exclu_sur'] : array();
		
		// Fetch Product Categories
		$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		// Default to 'on' if new settings have never been saved
		$enable_shipping_address = ! isset($opts['o100_enable_shipping_address']) || $opts['o100_enable_shipping_address'] === 'on';
		
		$instruction_options = isset($opts['o100_delivery_instruction_options']) ? $opts['o100_delivery_instruction_options'] : "Leave at door\nHand to me\nLeave at reception";
		$dis_payment = isset($opts['o100_delivery_dis_payment']) && is_array($opts['o100_delivery_dis_payment']) ? $opts['o100_delivery_dis_payment'] : array();

		// Fetch Payment Gateways
		$gateways = array();
		if ( class_exists( 'WooCommerce' ) ) {
			$available_gateways = WC()->payment_gateways->payment_gateways();
			foreach ( $available_gateways as $id => $gateway ) {
				if ( 'yes' === $gateway->enabled ) {
					$gateways[ $id ] = $gateway->title;
				}
			}
		}

		?>
		<div class="o100-fluent-form-wrapper" id="o100-fluent-delivery">
			<form id="o100-delivery-form">
				<!-- Header Toggle Element (Will be teleported by JS) -->
				<div id="o100_delivery_header_toggle" style="display: none;">
					<div class="cmb-type-checkbox" style="margin: 0;">
						<div class="o100-toggle-wrap" style="justify-content: flex-start; width: auto; background: #f8fafc; padding: 6px 12px; border-radius: 20px; border: 1px solid #e2e8f0;">
							<input type="checkbox" id="o100_enable_delivery" name="o100_enable_delivery" value="on" <?php checked( $enable_delivery ); ?>>
							<span style="font-size: 14px; font-weight: 600; color: #334155; margin-left: 8px;"><?php esc_html_e( 'Delivery Enabled', 'order100' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Welcome Card (Shown when disabled) -->
				<div id="o100-delivery-intro-card" style="display: <?php echo $enable_delivery ? 'none' : 'flex'; ?>; flex-direction: column; align-items: center; justify-content: center; background: #fff; padding: 60px 40px; border-radius: 12px; border: 1px solid #e2e8f0; margin: 40px auto; max-width: 600px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">
					<h2 style="font-size: 24px; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 16px;"><?php esc_html_e( 'Delivery Module', 'order100' ); ?></h2>
					<p style="font-size: 15px; color: #475569; margin-bottom: 32px; text-align: center; line-height: 1.6;"><?php esc_html_e( 'Configure delivery zones, delivery fees by radius or zip code, minimum order amounts, and delivery-specific business hours all in one place.', 'order100' ); ?></p>
					<button type="button" class="o100-start-module-btn" id="o100-start-delivery-btn" style="background: #22c55e; color: white; border: none; padding: 12px 32px; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);"><?php esc_html_e( 'Start Using Delivery', 'order100' ); ?></button>
				</div>

				<div id="o100-delivery-settings-wrapper" style="display: <?php echo $enable_delivery ? 'block' : 'none'; ?>;">
				<style>
				#o100-delivery-settings-wrapper .o100-modal-row { align-items: flex-start; }
				</style>
				<!-- Section: Order Conditions -->
				<div class="o100-settings-group-card">
					<div class="o100-settings-group-title">
						<h3><?php esc_html_e('Order Conditions & Restrictions', 'order100'); ?></h3>
						<p><?php esc_html_e('Set minimum amounts and preparation times required for delivery orders.', 'order100'); ?></p>
					</div>
					<div class="o100-settings-group-content">
						<div class="o100-modal-row">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Delivery Minimum Amount', 'order100' ); ?></label>
								</div>
								<div class="o100-flex-input-wrap has-prefix">
									<span class="o100-flex-prefix"><?php echo get_woocommerce_currency_symbol(); ?></span>
									<input type="number" step="0.01" name="o100_delivery_min_amount" class="o100-modal-input" value="<?php echo esc_attr( $min_amount ); ?>">
								</div>
							</div>
							<div class="o100-modal-field o100-modal-col">
								<style>
								.o100-tooltip-icon { position: relative; cursor: help; display: inline-flex; align-items: center; color: #94a3b8; margin-left: 6px; }
								.o100-tooltip-icon::before { content: attr(data-tooltip); position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 8px; padding: 8px 12px; background-color: #0f172a; color: #fff; font-size: 13px; line-height: 1.4; font-weight: 500; white-space: normal; width: max-content; max-width: 250px; text-align: center; border-radius: 6px; opacity: 0; visibility: hidden; transition: all 0.2s ease; z-index: 10; pointer-events: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
								.o100-tooltip-icon::after { content: ''; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 2px; border-width: 6px; border-style: solid; border-color: #0f172a transparent transparent transparent; opacity: 0; visibility: hidden; transition: all 0.2s ease; z-index: 10; pointer-events: none; }
								.o100-tooltip-icon:hover::before, .o100-tooltip-icon:hover::after { opacity: 1; visibility: visible; }
								</style>
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Minimum Preparation Time', 'order100' ); ?> <span class="o100-tooltip-icon" data-tooltip="<?php esc_attr_e('Specify the estimated minutes needed to prepare an order for delivery.', 'order100'); ?>"><i class="dashicons dashicons-info" style="font-size:16px; width:16px; height:16px;"></i></span></label>
								</div>
								<div class="o100-flex-input-wrap has-suffix">
									<input type="number" name="o100_delivery_lead_time" class="o100-modal-input" value="<?php echo esc_attr( $lead_time ); ?>">
									<span class="o100-flex-suffix"><?php esc_html_e('minutes', 'order100'); ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Section: Delivery Zones & Fees -->
				<div class="o100-settings-group-card">
					<div class="o100-settings-group-title">
						<h3><?php esc_html_e('Delivery Zones & Fees', 'order100'); ?></h3>
						<p><?php esc_html_e('Configure delivery zones, distance limits, and delivery fees.', 'order100'); ?></p>
					</div>
					<div class="o100-settings-group-content">
						<div class="o100-modal-row" style="flex-wrap: wrap; align-items: center;">
							<div class="o100-modal-col" style="flex: 0 1 350px;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Delivery Zone Method', 'order100' ); ?></label>
								</div>
								<select name="o100_limit_shp" class="o100-modal-input" id="o100_limit_shp_select">
									<option value="radius" <?php selected( $limit_shp, 'radius' ); ?>><?php esc_html_e( 'Distance-based (Radius / Driving)', 'order100' ); ?></option>
									<option value="zip" <?php selected( $limit_shp, 'zip' ); ?>><?php esc_html_e( 'Postal / Zip Codes', 'order100' ); ?></option>
								</select>
							</div>
							<div class="o100-modal-col o100-api-notice o100-field-radius-only" style="flex: 1; font-size: 13px; color: #64748b; margin-top: 24px; <?php echo $limit_shp === 'radius' ? '' : 'display: none;'; ?>">
								<i class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px; margin-right: 4px; color: #3b82f6;"></i>
								Requires <a href="<?php echo esc_url( admin_url( 'admin.php?page=o100-integration' ) ); ?>" style="color: #3b82f6; text-decoration: underline;">Google Maps API</a> configuration.
							</div>
						</div>

						<!-- Radius Fields (Visible when Radius is selected) -->
						<div class="o100-modal-row o100-field-radius-only" style="flex-wrap: wrap; <?php echo $limit_shp === 'radius' ? '' : 'display: none;'; ?>">
							<div class="o100-modal-col" style="flex: 1 1 300px;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Distance calculation using', 'order100' ); ?></label>
								</div>
								<select name="o100_deli_mode" class="o100-modal-input">
									<option value="driving" <?php selected( $deli_mode, 'driving' ); ?>><?php esc_html_e( 'Driving (Recommended)', 'order100' ); ?></option>
									<option value="bicycling" <?php selected( $deli_mode, 'bicycling' ); ?>><?php esc_html_e( 'Bicycling', 'order100' ); ?></option>
									<option value="walking" <?php selected( $deli_mode, 'walking' ); ?>><?php esc_html_e( 'Walking', 'order100' ); ?></option>
								</select>
							</div>
							<div class="o100-modal-col" style="flex: 1 1 300px;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Distance Restrict', 'order100' ); ?></label>
								</div>
								<div class="o100-flex-input-wrap has-suffix">
									<input type="number" step="0.1" name="o100_deli_dis" class="o100-modal-input" value="<?php echo esc_attr( $deli_dis ); ?>">
									<span class="o100-flex-suffix">km</span>
								</div>
							</div>
						</div>


						<div class="o100-modal-row o100-field-zip-only" style="margin-top: 12px; display: none;">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Supported Postcodes', 'order100' ); ?></label>
									<span style="font-size: 12px; color: #94a3b8; font-weight: normal; margin-left: 8px;">(Comma separated. Use * for wildcards)</span>
								</div>
								<textarea name="o100_shp_postcodes" class="o100-modal-input" style="height: 60px; padding: 10px;"><?php echo esc_textarea( $shp_postcodes ); ?></textarea>
							</div>
						</div>

						<hr style="margin: 24px 0; border: 0; border-top: 1px solid #e2e8f0;">

						<div class="o100-modal-row" style="flex-wrap: wrap;">
							<div class="o100-modal-field o100-modal-col" style="flex: 1 1 200px;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Global Delivery fee', 'order100' ); ?></label>
								</div>
								<div class="o100-flex-input-wrap has-prefix">
									<span class="o100-flex-prefix"><?php echo get_woocommerce_currency_symbol(); ?></span>
									<input type="number" step="0.01" name="o100_shipping_fee" class="o100-modal-input" value="<?php echo esc_attr( $shipping_fee ); ?>">
								</div>
							</div>
							<div class="o100-modal-field o100-modal-col" style="flex: 1 1 200px;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Free Delivery Threshold (Subtotal)', 'order100' ); ?></label>
								</div>
								<div class="o100-flex-input-wrap has-prefix">
									<span class="o100-flex-prefix"><?php echo get_woocommerce_currency_symbol(); ?></span>
									<input type="number" step="0.01" name="o100_shipping_freemax" class="o100-modal-input" value="<?php echo esc_attr( $shipping_freemax ); ?>">
								</div>
							</div>
						</div>

						<hr style="margin: 24px 0; border: 0; border-top: 1px solid #e2e8f0;">

						<div class="o100-modal-row o100-field-radius-only" style="margin-bottom: 12px;">
							<div class="o100-modal-field o100-modal-col">
								<div class="cmb-type-checkbox">
									<div class="o100-toggle-wrap" style="justify-content: flex-start; width: auto;">
										<input type="checkbox" id="o100_enable_shp_km" name="o100_enable_shp_km" value="on" <?php checked( $enable_shp_km ); ?>>
										<span style="font-size: 14px; font-weight: 500; color: #1e293b;"><?php esc_html_e( 'Enable Tiered Distance Fees', 'order100' ); ?></span>
									</div>
								</div>
							</div>
						</div>

						<div class="o100-modal-row o100-field-radius-only" id="o100-km-repeater-wrap">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Delivery fee by KM settings', 'order100' ); ?></label>
								</div>
								<div id="o100-km-repeater" style="margin-bottom: 12px;">
									<!-- Rules -->
								</div>
								<button type="button" class="o100-btn-secondary" id="o100-add-km-rule"><?php esc_html_e( 'Add Rule', 'order100' ); ?></button>
							</div>
						</div>

						<div class="o100-modal-row o100-field-zip-only" style="margin-bottom: 12px; display: none;">
							<div class="o100-modal-field o100-modal-col">
								<div class="cmb-type-checkbox">
									<div class="o100-toggle-wrap" style="justify-content: flex-start; width: auto;">
										<input type="checkbox" id="o100_enable_shp_zip" name="o100_enable_shp_zip" value="on" <?php checked( $enable_shp_zip ); ?>>
										<span style="font-size: 14px; font-weight: 500; color: #1e293b;"><?php esc_html_e( 'Enable delivery fee by Zip Code rules', 'order100' ); ?></span>
									</div>
								</div>
							</div>
						</div>

						<div class="o100-modal-row o100-field-zip-only" id="o100-zip-repeater-wrap" style="display: none;">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Delivery fee by Zip Code settings', 'order100' ); ?></label>
								</div>
								<div id="o100-zip-repeater" style="margin-bottom: 12px;">
									<!-- Rules -->
								</div>
								<button type="button" class="o100-btn-secondary" id="o100-add-zip-rule"><?php esc_html_e( 'Add Rule', 'order100' ); ?></button>
							</div>
						</div>
								<script>
									jQuery(document).ready(function($) {
										var rules = <?php echo json_encode($shp_km_loc); ?>;
										var zipRules = <?php echo json_encode($shp_zip_loc); ?>;
										var $repeater = $('#o100-km-repeater');
										var $zipRepeater = $('#o100-zip-repeater');
										
										function renderRule(index, maxDist, fee, free, minAmount) {
											var html = '<div class="o100-km-rule" style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:12px; align-items:flex-end; background:#f8fafc; padding:12px; border:1px solid #e2e8f0; border-radius:6px;">' +
												'<div style="flex:1; min-width: 120px;">' +
													'<label style="display:block; font-size:12px; color:#64748b; margin-bottom:4px; font-weight:600;">Max number of km</label>' +
													'<input type="text" class="o100-modal-input" name="o100_shp_km_loc[' + index + '][max_distance]" value="' + maxDist + '">' +
												'</div>' +
												'<div style="flex:1; min-width: 80px;">' +
													'<label style="display:block; font-size:12px; color:#64748b; margin-bottom:4px; font-weight:600;">Fee</label>' +
													'<input type="text" class="o100-modal-input" name="o100_shp_km_loc[' + index + '][fee]" value="' + fee + '">' +
												'</div>' +
												'<div style="flex:1; min-width: 120px;">' +
													'<label style="display:block; font-size:12px; color:#64748b; margin-bottom:4px; font-weight:600;">Free if total amount reach</label>' +
													'<input type="text" class="o100-modal-input" name="o100_shp_km_loc[' + index + '][free]" value="' + free + '">' +
												'</div>' +
												'<div style="flex:1; min-width: 120px;">' +
													'<label style="display:block; font-size:12px; color:#64748b; margin-bottom:4px; font-weight:600;">Minimum amount required</label>' +
													'<input type="text" class="o100-modal-input" name="o100_shp_km_loc[' + index + '][min_amount]" value="' + minAmount + '">' +
												'</div>' +
												'<div style="flex:0 0 auto;">' +
													'<button type="button" class="o100-btn-secondary o100-remove-km-rule" style="color:#ef4444; border-color:#fca5a5;">Remove</button>' +
												'</div>' +
											'</div>';
											$repeater.append(html);
										}

										function renderZipRule(index, postcode, fee, free, minAmount) {
											var html = '<div class="o100-zip-rule" style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:12px; align-items:flex-end; background:#f8fafc; padding:12px; border:1px solid #e2e8f0; border-radius:6px;">' +
												'<div style="flex:1; min-width: 120px;">' +
													'<label style="display:block; font-size:12px; color:#64748b; margin-bottom:4px; font-weight:600;">Postcode</label>' +
													'<input type="text" class="o100-modal-input" name="o100_shp_zip_loc[' + index + '][postcode]" value="' + postcode + '">' +
												'</div>' +
												'<div style="flex:1; min-width: 80px;">' +
													'<label style="display:block; font-size:12px; color:#64748b; margin-bottom:4px; font-weight:600;">Fee</label>' +
													'<input type="text" class="o100-modal-input" name="o100_shp_zip_loc[' + index + '][fee]" value="' + fee + '">' +
												'</div>' +
												'<div style="flex:1; min-width: 120px;">' +
													'<label style="display:block; font-size:12px; color:#64748b; margin-bottom:4px; font-weight:600;">Free if reach</label>' +
													'<input type="text" class="o100-modal-input" name="o100_shp_zip_loc[' + index + '][free]" value="' + free + '">' +
												'</div>' +
												'<div style="flex:1; min-width: 120px;">' +
													'<label style="display:block; font-size:12px; color:#64748b; margin-bottom:4px; font-weight:600;">Min required</label>' +
													'<input type="text" class="o100-modal-input" name="o100_shp_zip_loc[' + index + '][min_amount]" value="' + minAmount + '">' +
												'</div>' +
												'<div style="flex:0 0 auto;">' +
													'<button type="button" class="o100-btn-secondary o100-remove-zip-rule" style="color:#ef4444; border-color:#fca5a5;">Remove</button>' +
												'</div>' +
											'</div>';
											$zipRepeater.append(html);
										}

										if (rules && rules.length > 0) {
											$.each(rules, function(i, rule) {
												renderRule(i, rule.max_distance || '', rule.fee || '', rule.free || '', rule.min_amount || '');
											});
										}

										if (zipRules && zipRules.length > 0) {
											$.each(zipRules, function(i, rule) {
												renderZipRule(i, rule.postcode || '', rule.fee || '', rule.free || '', rule.min_amount || '');
											});
										}

										$('#o100-add-km-rule').on('click', function() {
											var nextIndex = $repeater.children('.o100-km-rule').length;
											renderRule(nextIndex, '', '', '', '');
										});

										$('#o100-add-zip-rule').on('click', function() {
											var nextIndex = $zipRepeater.children('.o100-zip-rule').length;
											renderZipRule(nextIndex, '', '', '', '');
										});

										$repeater.on('click', '.o100-remove-km-rule', function() {
											$(this).closest('.o100-km-rule').remove();
											// Re-index
											$repeater.children('.o100-km-rule').each(function(i) {
												$(this).find('input').eq(0).attr('name', 'o100_shp_km_loc[' + i + '][max_distance]');
												$(this).find('input').eq(1).attr('name', 'o100_shp_km_loc[' + i + '][fee]');
												$(this).find('input').eq(2).attr('name', 'o100_shp_km_loc[' + i + '][free]');
												$(this).find('input').eq(3).attr('name', 'o100_shp_km_loc[' + i + '][min_amount]');
											});
										});

										$zipRepeater.on('click', '.o100-remove-zip-rule', function() {
											$(this).closest('.o100-zip-rule').remove();
											// Re-index
											$zipRepeater.children('.o100-zip-rule').each(function(i) {
												$(this).find('input').eq(0).attr('name', 'o100_shp_zip_loc[' + i + '][postcode]');
												$(this).find('input').eq(1).attr('name', 'o100_shp_zip_loc[' + i + '][fee]');
												$(this).find('input').eq(2).attr('name', 'o100_shp_zip_loc[' + i + '][free]');
												$(this).find('input').eq(3).attr('name', 'o100_shp_zip_loc[' + i + '][min_amount]');
											});
										});

										function o100_toggle_delivery_fields() {
											var limitMode = $('#o100_limit_shp_select').val();
											if ( limitMode === 'radius' ) {
												$('.o100-field-radius-only').show();
												$('.o100-field-zip-only').hide();
											} else if ( limitMode === 'zip' ) {
												$('.o100-field-radius-only').hide();
												$('.o100-field-zip-only').show();
											}

											if ( $('#o100_enable_shp_km').is(':checked') && limitMode === 'radius' ) {
												$('#o100-km-repeater-wrap').show();
											} else {
												$('#o100-km-repeater-wrap').hide();
											}

											if ( $('#o100_enable_shp_zip').is(':checked') && limitMode === 'zip' ) {
												$('#o100-zip-repeater-wrap').show();
											} else {
												$('#o100-zip-repeater-wrap').hide();
											}
										}

										$('#o100_limit_shp_select, #o100_enable_shp_km, #o100_enable_shp_zip').on('change', o100_toggle_delivery_fields);
										o100_toggle_delivery_fields();

										// Portal toggle to header
										var $toggle = $('#o100_delivery_header_toggle');
										$('.o100-fluent-header-actions').prepend($toggle);
										if ($('#o100_enable_delivery').is(':checked')) {
											$toggle.show();
										}

										// Start btn click
										$('#o100-start-delivery-btn').on('click', function() {
											$('#o100_enable_delivery').prop('checked', true).trigger('change');
											$('.o100-fluent-top-save').trigger('click'); // Auto save
										});

										// Global Delivery Toggle — Direct AJAX to o100_toggle_module
										$('#o100_enable_delivery').on('change', function() {
											var $checkbox = $(this);
											
											function saveModuleState() {
												var newStatus = $checkbox.is(':checked') ? 'on' : '';
												if ($checkbox.is(':checked')) {
													$('#o100-delivery-settings-wrapper').fadeIn(200);
													$('#o100-delivery-intro-card').hide();
													$('#o100_delivery_header_toggle').fadeIn(200);
												} else {
													$('#o100-delivery-settings-wrapper').hide();
													$('#o100-delivery-intro-card').fadeIn(200);
													$('#o100_delivery_header_toggle').hide();
												}
												jQuery.post(
													'<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>',
													{ action: 'o100_toggle_module', module: 'delivery', status: newStatus },
													function(res) {
														if (res.success) {
															sessionStorage.setItem('o100_settings_saved', '1');
															location.reload();
														} else {
															window.o100ShowToast('Save failed', 'error');
															$checkbox.prop('checked', !$checkbox.is(':checked'));
														}
													}
												);
											}

											if (!$checkbox.is(':checked')) {
												if (typeof window.o100Confirm === 'function') {
													window.o100Confirm(
														"<?php echo esc_js( __( 'Disable Module', 'order100' ) ); ?>",
														"<?php echo esc_js( __( 'Are you sure you want to disable Delivery? Turning off this switch will completely disable the delivery feature across your entire store.', 'order100' ) ); ?>",
														function(confirmed) {
															if (!confirmed) {
																$checkbox.prop('checked', true);
															} else {
																saveModuleState();
															}
														}
													);
												} else {
													if (!confirm("<?php echo esc_js( __( 'Are you sure you want to disable Delivery?', 'order100' ) ); ?>")) {
														$checkbox.prop('checked', true);
													} else {
														saveModuleState();
													}
												}
											} else {
												saveModuleState();
											}
										});
										// Trigger manually on load isn't strictly needed as PHP sets display, but safe:
										// $('#o100_enable_delivery').trigger('change');

										// Delivery Fees Toggles
										$('#o100_delivery_fee_action').on('change', function() {
											var action = $(this).val();
											if (action === 'none') {
												$('.o100-delivery-fee-fields').hide();
												$('.o100-delivery-exclu-cats').hide();
											} else {
												$('.o100-delivery-fee-fields').css('display', 'block');
												if ($('#o100_delivery_fee_type').val() === 'percent') {
													$('.o100-delivery-exclu-cats').css('display', 'flex');
												} else {
													$('.o100-delivery-exclu-cats').hide();
												}
											}
										});

										$('#o100_delivery_fee_type').on('change', function() {
											var type = $(this).val();
											var action = $('#o100_delivery_fee_action').val();
											var $wrap = $('#o100-delivery-fee-wrap');
											if (type === 'percent') {
												$wrap.removeClass('has-prefix').addClass('has-suffix');
												$('.o100-delivery-fee-prefix').hide();
												$('.o100-delivery-fee-suffix').css('display', 'flex');
												if (action !== 'none') $('.o100-delivery-exclu-cats').css('display', 'flex');
											} else {
												$wrap.removeClass('has-suffix').addClass('has-prefix');
												$('.o100-delivery-fee-suffix').hide();
												$('.o100-delivery-fee-prefix').css('display', 'flex');
												$('.o100-delivery-exclu-cats').hide();
											}
										});

									});
								</script>
					</div>
				</div>

				<!-- Section: Fees & Discounts -->
				<div class="o100-settings-group-card">
					<div class="o100-settings-group-title">
						<h3><?php esc_html_e('Delivery Fees & Discounts', 'order100'); ?></h3>
						<p><?php esc_html_e('Incentivize delivery orders with a discount, or charge an extra processing fee.', 'order100'); ?></p>
					</div>
					<div class="o100-settings-group-content">
						<div class="o100-modal-row" style="margin-bottom: 24px; flex-wrap: wrap;">
							<div class="o100-modal-field o100-modal-col" style="flex: 1 1 200px;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Action', 'order100' ); ?></label>
								</div>
								<select name="o100_delivery_fee_action" id="o100_delivery_fee_action" class="o100-modal-input">
									<option value="none" <?php selected($fee_action, 'none'); ?>><?php esc_html_e( 'No extra fees or discounts', 'order100' ); ?></option>
									<option value="discount" <?php selected($fee_action, 'discount'); ?>><?php esc_html_e( 'Give a Discount (e.g. 10% OFF)', 'order100' ); ?></option>
									<option value="surcharge" <?php selected($fee_action, 'surcharge'); ?>><?php esc_html_e( 'Charge a Fee (e.g. Processing Fee)', 'order100' ); ?></option>
								</select>
							</div>
							
							<div class="o100-modal-field o100-modal-col o100-delivery-fee-fields" style="flex: 1 1 200px; display: <?php echo $fee_action === 'none' ? 'none' : 'block'; ?>;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Amount Type', 'order100' ); ?></label>
								</div>
								<select name="o100_delivery_fee_type" id="o100_delivery_fee_type" class="o100-modal-input">
									<option value="percent" <?php selected($fee_type, 'percent'); ?>><?php esc_html_e( 'Percentage (%)', 'order100' ); ?></option>
									<option value="fixed" <?php selected($fee_type, 'fixed'); ?>><?php esc_html_e( 'Fixed Amount ($)', 'order100' ); ?></option>
								</select>
							</div>
							
							<div class="o100-modal-field o100-modal-col o100-delivery-fee-fields" style="flex: 1 1 200px; display: <?php echo $fee_action === 'none' ? 'none' : 'block'; ?>;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Value', 'order100' ); ?></label>
								</div>
								<div class="o100-flex-input-wrap <?php echo $fee_type === 'percent' ? 'has-suffix' : 'has-prefix'; ?>" id="o100-delivery-fee-wrap">
									<span class="o100-flex-prefix o100-delivery-fee-prefix" style="display: <?php echo $fee_type === 'percent' ? 'none' : 'flex'; ?>;"><?php echo get_woocommerce_currency_symbol(); ?></span>
									<input type="number" step="0.01" min="0" name="o100_delivery_fee_val" class="o100-modal-input" value="<?php echo esc_attr( $fee_val ); ?>" placeholder="e.g. 10">
									<span class="o100-flex-suffix o100-delivery-fee-suffix" style="display: <?php echo $fee_type === 'percent' ? 'flex' : 'none'; ?>;">%</span>
								</div>
							</div>
							
							<div class="o100-modal-field o100-modal-col o100-delivery-fee-fields" style="flex: 1 1 200px; display: <?php echo $fee_action === 'none' ? 'none' : 'block'; ?>;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Custom Name', 'order100' ); ?></label>
									<span style="font-size: 12px; color: #64748b; font-weight: normal; margin-left: 8px;" title="<?php esc_attr_e('Leave blank for default', 'order100'); ?>">(Optional)</span>
								</div>
								<input type="text" name="o100_delivery_fee_label" class="o100-modal-input" value="<?php echo esc_attr( $fee_label ); ?>" placeholder="e.g. Processing Fee">
							</div>
						</div>

						<div class="o100-modal-row o100-delivery-exclu-cats" style="display: <?php echo ($fee_action !== 'none' && $fee_type === 'percent') ? 'flex' : 'none'; ?>;">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Exclude Categories', 'order100' ); ?></label>
									<span style="font-size: 12px; color: #64748b; font-weight: normal; margin-left: 8px;"><?php esc_html_e('(Select categories that should NOT receive this percentage discount/surcharge)', 'order100'); ?></span>
								</div>
								<div style="display:flex; flex-wrap:wrap; gap:16px; background:#f8fafc; padding:12px; border:1px solid #e2e8f0; border-radius:6px; max-height:150px; overflow-y:auto;">
									<?php if ( ! is_wp_error( $categories ) ) : ?>
										<?php foreach ( $categories as $category ) : ?>
											<label style="display:flex; align-items:center; gap:8px; font-size:14px; color:#334155; width:45%;">
												<input type="checkbox" name="o100_delivery_exclu_sur[]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked( in_array($category->term_id, $exclu_sur) ); ?>>
												<?php echo esc_html($category->name); ?>
											</label>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Section: Checkout Interventions -->
				<div class="o100-settings-group-card">
					<div class="o100-settings-group-title">
						<h3><?php esc_html_e('Checkout Interventions', 'order100'); ?></h3>
						<p><?php esc_html_e('Configure checkout flow and payment restrictions for delivery.', 'order100'); ?></p>
					</div>
					<div class="o100-settings-group-content">
						<div class="o100-modal-row" style="margin-bottom: 24px;">
							<div class="o100-modal-field o100-modal-col">
								<div class="cmb-type-checkbox">
									<div class="o100-toggle-wrap" style="justify-content: flex-start; width: auto;">
										<input type="checkbox" name="o100_enable_shipping_address" value="on" <?php checked( $enable_shipping_address ); ?>>
										<span style="font-size: 14px; font-weight: 500; color: #1e293b;"><?php esc_html_e( 'Enable Advanced Shipping Form', 'order100' ); ?></span>
									</div>
								</div>
								<p style="font-size: 13px; color: #64748b; margin: 8px 0 0 0;"><?php esc_html_e( 'Reformat the checkout shipping form for Delivery orders (DoorDash style layout, hide redundant fields).', 'order100' ); ?></p>
							</div>
						</div>

						<div class="o100-modal-row" style="margin-bottom: 24px;">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Delivery Instructions', 'order100' ); ?></label>
									<span style="font-size: 12px; color: #94a3b8; font-weight: normal; margin-left: 8px;">(One per line)</span>
								</div>
								<textarea name="o100_delivery_instruction_options" class="o100-modal-input" style="height: 100px; padding: 10px;"><?php echo esc_textarea( $instruction_options ); ?></textarea>
							</div>
						</div>

						<div class="o100-modal-row">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Disable Payments', 'order100' ); ?></label>
									<span style="font-size: 12px; color: #94a3b8; font-weight: normal; margin-left: 8px;">(Select payment methods to disable when delivery is chosen)</span>
								</div>
								<div style="display:flex; flex-wrap:wrap; gap:16px;">
									<?php foreach ( $gateways as $id => $title ) : ?>
										<label style="display:flex; align-items:center; gap:8px; font-size:14px; color:#334155;">
											<input type="checkbox" name="o100_delivery_dis_payment[]" value="<?php echo esc_attr($id); ?>" <?php checked( in_array($id, $dis_payment) ); ?>>
											<?php echo esc_html($title); ?>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
				</div> <!-- End o100-delivery-settings-wrapper -->
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {

		});
		</script>
		<?php
	}

	public static function render_fluent_pickup() {
		$opts = get_option('o100_pickup', array());
		
		$enable_pickup = isset($opts['o100_enable_pickup']) && $opts['o100_enable_pickup'] === 'on';
		$min_amount = isset($opts['o100_pickup_min_amount']) ? $opts['o100_pickup_min_amount'] : '';
		$lead_time = isset($opts['o100_pickup_lead_time']) ? $opts['o100_pickup_lead_time'] : '';
		$fee_action = isset($opts['o100_pickup_fee_action']) ? $opts['o100_pickup_fee_action'] : 'none';
		$fee_type = isset($opts['o100_pickup_fee_type']) ? $opts['o100_pickup_fee_type'] : 'percent';
		$fee_val = isset($opts['o100_pickup_fee_val']) ? $opts['o100_pickup_fee_val'] : '';
		$fee_label = isset($opts['o100_pickup_fee_label']) ? $opts['o100_pickup_fee_label'] : '';
		$exclu_sur = isset($opts['o100_exclu_sur']) && is_array($opts['o100_exclu_sur']) ? $opts['o100_exclu_sur'] : array();
		
		if ( get_option( 'o100_pickup', false ) !== false ) {
			$dis_addr = isset($opts['o100_pickup_dis_addr']) && $opts['o100_pickup_dis_addr'] === 'on';
		} else {
			$dis_addr = true; // Default to enabled
		}
		
		$dis_payment = isset($opts['o100_pickup_dis_payment']) && is_array($opts['o100_pickup_dis_payment']) ? $opts['o100_pickup_dis_payment'] : array();

		// Fetch Payment Gateways
		$gateways = array();
		if ( class_exists( 'WooCommerce' ) ) {
			$available_gateways = WC()->payment_gateways->payment_gateways();
			foreach ( $available_gateways as $id => $gateway ) {
				if ( 'yes' === $gateway->enabled ) {
					$gateways[ $id ] = $gateway->title;
				}
			}
		}

		// Fetch Product Categories
		$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		
		?>
		<div class="o100-fluent-form-wrapper" id="o100-fluent-pickup">
			<form id="o100-pickup-form">
				<!-- Header Toggle Element (Will be teleported by JS) -->
				<div id="o100_pickup_header_toggle" style="display: none;">
					<div class="cmb-type-checkbox" style="margin: 0;">
						<div class="o100-toggle-wrap" style="justify-content: flex-start; width: auto; background: #f8fafc; padding: 6px 12px; border-radius: 20px; border: 1px solid #e2e8f0;">
							<input type="checkbox" id="o100_enable_pickup" name="o100_enable_pickup" value="on" <?php checked( $enable_pickup ); ?>>
							<span style="font-size: 14px; font-weight: 600; color: #334155; margin-left: 8px;"><?php esc_html_e( 'Pickup Enabled', 'order100' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Welcome Card (Shown when disabled) -->
				<div id="o100-pickup-intro-card" style="display: <?php echo $enable_pickup ? 'none' : 'flex'; ?>; flex-direction: column; align-items: center; justify-content: center; background: #fff; padding: 60px 40px; border-radius: 12px; border: 1px solid #e2e8f0; margin: 40px auto; max-width: 600px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">
					<h2 style="font-size: 24px; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 16px;"><?php esc_html_e( 'Pickup Module', 'order100' ); ?></h2>
					<p style="font-size: 15px; color: #475569; margin-bottom: 32px; text-align: center; line-height: 1.6;"><?php esc_html_e( 'Configure minimum orders, preparation times, and checkout restrictions for pickup orders.', 'order100' ); ?></p>
					<button type="button" class="o100-start-module-btn" id="o100-start-pickup-btn" style="background: #22c55e; color: white; border: none; padding: 12px 32px; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);"><?php esc_html_e( 'Start Using Pickup', 'order100' ); ?></button>
				</div>

				<div id="o100-pickup-settings-wrapper" style="display: <?php echo $enable_pickup ? 'block' : 'none'; ?>;">
				<style>
				#o100-pickup-settings-wrapper .o100-modal-row { align-items: flex-start; }
				</style>

				<!-- Section: Order Conditions -->
				<div class="o100-settings-group-card">
					<div class="o100-settings-group-title">
						<h3><?php esc_html_e('Order Conditions & Restrictions', 'order100'); ?></h3>
						<p><?php esc_html_e('Set minimum amounts and preparation times required for pickup orders.', 'order100'); ?></p>
					</div>
					<div class="o100-settings-group-content">
						<div class="o100-modal-row">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Pickup Minimum Amount', 'order100' ); ?></label>
								</div>
								<div class="o100-flex-input-wrap has-prefix">
									<span class="o100-flex-prefix"><?php echo get_woocommerce_currency_symbol(); ?></span>
									<input type="number" step="0.01" name="o100_pickup_min_amount" class="o100-modal-input" value="<?php echo esc_attr( $min_amount ); ?>">
								</div>
							</div>
							<div class="o100-modal-field o100-modal-col">
								<style>
								.o100-tooltip-icon { position: relative; cursor: help; display: inline-flex; align-items: center; color: #94a3b8; margin-left: 6px; }
								.o100-tooltip-icon::before { content: attr(data-tooltip); position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 8px; padding: 8px 12px; background-color: #0f172a; color: #fff; font-size: 13px; line-height: 1.4; font-weight: 500; white-space: normal; width: max-content; max-width: 250px; text-align: center; border-radius: 6px; opacity: 0; visibility: hidden; transition: all 0.2s ease; z-index: 10; pointer-events: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
								.o100-tooltip-icon::after { content: ''; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 2px; border-width: 6px; border-style: solid; border-color: #0f172a transparent transparent transparent; opacity: 0; visibility: hidden; transition: all 0.2s ease; z-index: 10; pointer-events: none; }
								.o100-tooltip-icon:hover::before, .o100-tooltip-icon:hover::after { opacity: 1; visibility: visible; }
								</style>
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Minimum Preparation Time', 'order100' ); ?> <span class="o100-tooltip-icon" data-tooltip="<?php esc_attr_e('Specify the estimated minutes needed to prepare an order for pickup.', 'order100'); ?>"><i class="dashicons dashicons-info" style="font-size:16px; width:16px; height:16px;"></i></span></label>
								</div>
								<div class="o100-flex-input-wrap has-suffix">
									<input type="number" name="o100_pickup_lead_time" class="o100-modal-input" value="<?php echo esc_attr( $lead_time ); ?>">
									<span class="o100-flex-suffix"><?php esc_html_e('minutes', 'order100'); ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Section: Fees & Discounts -->
				<div class="o100-settings-group-card">
					<div class="o100-settings-group-title">
						<h3><?php esc_html_e('Pickup Fees & Discounts', 'order100'); ?></h3>
						<p><?php esc_html_e('Incentivize pickup orders with a discount, or charge a packaging fee.', 'order100'); ?></p>
					</div>
					<div class="o100-settings-group-content">
						<div class="o100-modal-row" style="margin-bottom: 24px; flex-wrap: wrap;">
							<div class="o100-modal-field o100-modal-col" style="flex: 1 1 200px;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Action', 'order100' ); ?></label>
								</div>
								<select name="o100_pickup_fee_action" id="o100_pickup_fee_action" class="o100-modal-input">
									<option value="none" <?php selected($fee_action, 'none'); ?>><?php esc_html_e( 'No extra fees or discounts', 'order100' ); ?></option>
									<option value="discount" <?php selected($fee_action, 'discount'); ?>><?php esc_html_e( 'Give a Discount (e.g. 10% OFF)', 'order100' ); ?></option>
									<option value="surcharge" <?php selected($fee_action, 'surcharge'); ?>><?php esc_html_e( 'Charge a Fee (e.g. Packaging Fee)', 'order100' ); ?></option>
								</select>
							</div>
							
							<div class="o100-modal-field o100-modal-col o100-pickup-fee-fields" style="flex: 1 1 200px; display: <?php echo $fee_action === 'none' ? 'none' : 'block'; ?>;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Amount Type', 'order100' ); ?></label>
								</div>
								<select name="o100_pickup_fee_type" id="o100_pickup_fee_type" class="o100-modal-input">
									<option value="percent" <?php selected($fee_type, 'percent'); ?>><?php esc_html_e( 'Percentage (%)', 'order100' ); ?></option>
									<option value="fixed" <?php selected($fee_type, 'fixed'); ?>><?php esc_html_e( 'Fixed Amount ($)', 'order100' ); ?></option>
								</select>
							</div>
							
							<div class="o100-modal-field o100-modal-col o100-pickup-fee-fields" style="flex: 1 1 200px; display: <?php echo $fee_action === 'none' ? 'none' : 'block'; ?>;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Value', 'order100' ); ?></label>
								</div>
								<div class="o100-flex-input-wrap <?php echo $fee_type === 'percent' ? 'has-suffix' : 'has-prefix'; ?>" id="o100-pickup-fee-wrap">
									<span class="o100-flex-prefix o100-pickup-fee-prefix" style="display: <?php echo $fee_type === 'percent' ? 'none' : 'flex'; ?>;"><?php echo get_woocommerce_currency_symbol(); ?></span>
									<input type="number" step="0.01" min="0" name="o100_pickup_fee_val" class="o100-modal-input" value="<?php echo esc_attr( $fee_val ); ?>" placeholder="e.g. 10">
									<span class="o100-flex-suffix o100-pickup-fee-suffix" style="display: <?php echo $fee_type === 'percent' ? 'flex' : 'none'; ?>;">%</span>
								</div>
							</div>
							
							<div class="o100-modal-field o100-modal-col o100-pickup-fee-fields" style="flex: 1 1 200px; display: <?php echo $fee_action === 'none' ? 'none' : 'block'; ?>;">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Custom Name', 'order100' ); ?></label>
									<span style="font-size: 12px; color: #64748b; font-weight: normal; margin-left: 8px;" title="<?php esc_attr_e('Leave blank for default', 'order100'); ?>">(Optional)</span>
								</div>
								<input type="text" name="o100_pickup_fee_label" class="o100-modal-input" value="<?php echo esc_attr( $fee_label ); ?>" placeholder="e.g. Packaging Fee">
							</div>
						</div>

						<div class="o100-modal-row o100-pickup-exclu-cats" style="display: <?php echo ($fee_action !== 'none' && $fee_type === 'percent') ? 'flex' : 'none'; ?>;">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Exclude Categories', 'order100' ); ?></label>
									<span style="font-size: 12px; color: #64748b; font-weight: normal; margin-left: 8px;"><?php esc_html_e('(Select categories that should NOT receive this percentage discount/surcharge)', 'order100'); ?></span>
								</div>
								<div style="display:flex; flex-wrap:wrap; gap:16px; background:#f8fafc; padding:12px; border:1px solid #e2e8f0; border-radius:6px; max-height:150px; overflow-y:auto;">
									<?php if ( ! is_wp_error( $categories ) ) : ?>
										<?php foreach ( $categories as $category ) : ?>
											<label style="display:flex; align-items:center; gap:8px; font-size:14px; color:#334155; width:45%;">
												<input type="checkbox" name="o100_exclu_sur[]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked( in_array($category->term_id, $exclu_sur) ); ?>>
												<?php echo esc_html($category->name); ?>
											</label>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Section: Checkout Interventions -->
				<div class="o100-settings-group-card">
					<div class="o100-settings-group-title">
						<h3><?php esc_html_e('Checkout Interventions', 'order100'); ?></h3>
						<p><?php esc_html_e('Customize the checkout experience for pickup orders.', 'order100'); ?></p>
					</div>
					<div class="o100-settings-group-content">
						<div class="o100-modal-row" style="margin-bottom: 24px;">
							<div class="o100-modal-field o100-modal-col">
								<div class="cmb-type-checkbox">
									<div class="o100-toggle-wrap" style="justify-content: flex-start; width: auto;">
										<input type="checkbox" name="o100_pickup_dis_addr" value="on" <?php checked( $dis_addr ); ?>>
										<span style="font-size: 14px; font-weight: 500; color: #1e293b;"><?php esc_html_e( 'Disable address fields for Pickup', 'order100' ); ?></span>
									</div>
								</div>
							</div>
						</div>

						<div class="o100-modal-row">
							<div class="o100-modal-field o100-modal-col">
								<div class="o100-modal-field-header">
									<label><?php esc_html_e( 'Disable Payments for Pickup', 'order100' ); ?></label>
									<span style="font-size: 12px; color: #94a3b8; font-weight: normal; margin-left: 8px;">(Select payment methods to disable)</span>
								</div>
								<div style="display:flex; flex-wrap:wrap; gap:16px;">
									<?php foreach ( $gateways as $id => $title ) : ?>
										<label style="display:flex; align-items:center; gap:8px; font-size:14px; color:#334155;">
											<input type="checkbox" name="o100_pickup_dis_payment[]" value="<?php echo esc_attr($id); ?>" <?php checked( in_array($id, $dis_payment) ); ?>>
											<?php echo esc_html($title); ?>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
				</div> <!-- End o100-pickup-settings-wrapper -->
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Portal toggle to header
			var $toggle = $('#o100_pickup_header_toggle');
			$('.o100-fluent-header-actions').prepend($toggle);
			if ($('#o100_enable_pickup').is(':checked')) {
				$toggle.show();
			}

			// Start btn click
			$('#o100-start-pickup-btn').on('click', function() {
				$('#o100_enable_pickup').prop('checked', true).trigger('change');
				$('.o100-fluent-top-save').trigger('click'); // Auto save
			});

			// Global Pickup Toggle — Direct AJAX to o100_toggle_module
			$('#o100_enable_pickup').on('change', function() {
				var $checkbox = $(this);
				
				function saveModuleState() {
					var newStatus = $checkbox.is(':checked') ? 'on' : '';
					if ($checkbox.is(':checked')) {
						$('#o100-pickup-settings-wrapper').fadeIn(200);
						$('#o100-pickup-intro-card').hide();
						$('#o100_pickup_header_toggle').fadeIn(200);
					} else {
						$('#o100-pickup-settings-wrapper').hide();
						$('#o100-pickup-intro-card').fadeIn(200);
						$('#o100_pickup_header_toggle').hide();
					}
					jQuery.post(
						'<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>',
						{ action: 'o100_toggle_module', module: 'pickup', status: newStatus },
						function(res) {
							if (res.success) {
								sessionStorage.setItem('o100_settings_saved', '1');
								location.reload();
							} else {
								window.o100ShowToast('Save failed', 'error');
								$checkbox.prop('checked', !$checkbox.is(':checked'));
							}
						}
					);
				}

				if (!$checkbox.is(':checked')) {
					if (typeof window.o100Confirm === 'function') {
						window.o100Confirm(
							"<?php echo esc_js( __( 'Disable Module', 'order100' ) ); ?>",
							"<?php echo esc_js( __( 'Are you sure you want to disable Pickup? Turning off this switch will completely disable the pickup feature across your entire store.', 'order100' ) ); ?>",
							function(confirmed) {
								if (!confirmed) {
									$checkbox.prop('checked', true);
								} else {
									saveModuleState();
								}
							}
						);
					} else {
						if (!confirm("<?php echo esc_js( __( 'Are you sure you want to disable Pickup?', 'order100' ) ); ?>")) {
							$checkbox.prop('checked', true);
						} else {
							saveModuleState();
						}
					}
				} else {
					saveModuleState();
				}
			});

			// Pickup Fees Toggles
			$('#o100_pickup_fee_action').on('change', function() {
				var action = $(this).val();
				if (action === 'none') {
					$('.o100-pickup-fee-fields').hide();
					$('.o100-pickup-exclu-cats').hide();
				} else {
					$('.o100-pickup-fee-fields').css('display', 'block');
					if ($('#o100_pickup_fee_type').val() === 'percent') {
						$('.o100-pickup-exclu-cats').css('display', 'flex');
					} else {
						$('.o100-pickup-exclu-cats').hide();
					}
				}
			});

			$('#o100_pickup_fee_type').on('change', function() {
				var type = $(this).val();
				var action = $('#o100_pickup_fee_action').val();
				var $wrap = $('#o100-pickup-fee-wrap');
				if (type === 'percent') {
					$wrap.removeClass('has-prefix').addClass('has-suffix');
					$('.o100-pickup-fee-prefix').hide();
					$('.o100-pickup-fee-suffix').css('display', 'flex');
					if (action !== 'none') $('.o100-pickup-exclu-cats').css('display', 'flex');
				} else {
					$wrap.removeClass('has-suffix').addClass('has-prefix');
					$('.o100-pickup-fee-suffix').hide();
					$('.o100-pickup-fee-prefix').css('display', 'flex');
					$('.o100-pickup-exclu-cats').hide();
				}
			});

		});
		</script>
		<?php
	}

	public function ajax_save_portal_settings() {
		if ( ! isset( $_POST['o100_portal_nonce'] ) || ! wp_verify_nonce( $_POST['o100_portal_nonce'], 'o100_save_portal' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'order100' ) ) );
		}
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'order100' ) ) );
		}
		
		$opts = get_option('o100_portal', array());
		
		// Map simple text/select fields
		$fields = array(
			'o100_portal_widget_mode', 'o100_portal_launcher_style',
			'o100_portal_launcher_text', 'o100_portal_launcher_position', 'o100_portal_launcher_animation',
			'o100_portal_launcher_icon', 'o100_portal_launcher_shape', 'o100_portal_launcher_spacing',
			'o100_portal_theme_color', 'o100_portal_bg_color', 'o100_portal_text_color', 'o100_portal_btn_text_color', 'o100_portal_border_radius',
			'o100_portal_logo',
			'o100_portal_default_tab',
			'o100_portal_tab_label_cart', 'o100_portal_tab_label_rewards', 'o100_portal_tab_label_account',
			'o100_portal_guest_title', 'o100_portal_guest_subtitle', 'o100_portal_guest_btn_text',
			'o100_portal_member_welcome', 'o100_portal_member_points_format', 'o100_portal_member_earn_text', 'o100_portal_member_redeem_text',
			'o100_portal_cart_icon', 'o100_portal_cart_checkout_text'
		);
		foreach($fields as $f) {
			if (isset($_POST[$f])) {
				$opts[$f] = sanitize_text_field( wp_unslash( $_POST[$f] ) );
			}
		}
		
		// Checkboxes & Arrays
		$opts['o100_portal_mobile_integration'] = isset($_POST['o100_portal_mobile_integration']) ? 'on' : 'off';
		$opts['o100_portal_cart_show_upsell'] = isset($_POST['o100_portal_cart_show_upsell']) ? 'yes' : 'no';
		$opts['o100_portal_cart_show_promo'] = isset($_POST['o100_portal_cart_show_promo']) ? 'yes' : 'no';
		$opts['o100_portal_enabled_tabs'] = isset($_POST['o100_portal_enabled_tabs']) && is_array($_POST['o100_portal_enabled_tabs']) ? array_map('sanitize_text_field', $_POST['o100_portal_enabled_tabs']) : array();
		if ( ! in_array('cart', $opts['o100_portal_enabled_tabs']) ) {
			array_unshift($opts['o100_portal_enabled_tabs'], 'cart');
		}
		
		update_option('o100_portal', $opts);
		wp_send_json_success();
	}

	public static function render_fluent_store_portal() {
		$opts = get_option('o100_portal', array());
		
		// Widget Mode
		$widget_mode    = isset($opts['o100_portal_widget_mode']) ? $opts['o100_portal_widget_mode'] : 'sidecart';
		$launcher_style = isset($opts['o100_portal_launcher_style']) ? $opts['o100_portal_launcher_style'] : 'icon_text';
		
		// Launcher
		$launcher_text  = isset($opts['o100_portal_launcher_text']) ? $opts['o100_portal_launcher_text'] : 'Rewards & Cart';
		$launcher_pos   = isset($opts['o100_portal_launcher_position']) ? $opts['o100_portal_launcher_position'] : 'bottom-right';

		if ( $launcher_pos === 'hidden' ) {
			$launcher_style = 'hidden';
		} else if ( strpos($launcher_pos, 'middle') !== false ) {
			$launcher_style = 'icon_only';
		} else {
			$launcher_style = 'icon_text';
		}
		$launcher_pos   = isset($opts['o100_portal_launcher_position']) ? $opts['o100_portal_launcher_position'] : 'bottom-right';
		$launcher_anim  = isset($opts['o100_portal_launcher_animation']) ? $opts['o100_portal_launcher_animation'] : 'pulse';
		$launcher_icon  = isset($opts['o100_portal_launcher_icon']) ? $opts['o100_portal_launcher_icon'] : 'cart';
		$launcher_shape = isset($opts['o100_portal_launcher_shape']) ? $opts['o100_portal_launcher_shape'] : 'pill';
		$launcher_spacing= isset($opts['o100_portal_launcher_spacing']) ? $opts['o100_portal_launcher_spacing'] : '20';
		$mobile_nav     = isset($opts['o100_portal_mobile_integration']) ? $opts['o100_portal_mobile_integration'] : 'on';
		
		// Design
		$theme_color    = isset($opts['o100_portal_theme_color']) ? $opts['o100_portal_theme_color'] : '#e11d48';
		$bg_color       = isset($opts['o100_portal_bg_color']) ? $opts['o100_portal_bg_color'] : '#ffffff';
		$text_color     = isset($opts['o100_portal_text_color']) ? $opts['o100_portal_text_color'] : '#111111';
		$btn_text_color = isset($opts['o100_portal_btn_text_color']) ? $opts['o100_portal_btn_text_color'] : '#ffffff';
		$border_radius  = isset($opts['o100_portal_border_radius']) ? $opts['o100_portal_border_radius'] : '12';
		$logo_url       = isset($opts['o100_portal_logo']) ? $opts['o100_portal_logo'] : '';
		
		// Content - Tabs
		$enabled_tabs   = isset($opts['o100_portal_enabled_tabs']) ? $opts['o100_portal_enabled_tabs'] : array('cart', 'rewards', 'account');
		$default_tab    = isset($opts['o100_portal_default_tab']) ? $opts['o100_portal_default_tab'] : 'rewards';
		$tab_cart       = isset($opts['o100_portal_tab_label_cart']) ? $opts['o100_portal_tab_label_cart'] : 'Your Order';
		$tab_rewards    = isset($opts['o100_portal_tab_label_rewards']) ? $opts['o100_portal_tab_label_rewards'] : 'Rewards';
		$tab_account    = isset($opts['o100_portal_tab_label_account']) ? $opts['o100_portal_tab_label_account'] : 'Account';

		// Content - Text
		$guest_title    = isset($opts['o100_portal_guest_title']) ? $opts['o100_portal_guest_title'] : 'Unlock free food with every order!';
		$guest_sub      = isset($opts['o100_portal_guest_subtitle']) ? $opts['o100_portal_guest_subtitle'] : 'Sign up today and start earning points towards your next meal.';
		$guest_btn      = isset($opts['o100_portal_guest_btn_text']) ? $opts['o100_portal_guest_btn_text'] : 'Join Now';
		
		$member_welcome = isset($opts['o100_portal_member_welcome']) ? $opts['o100_portal_member_welcome'] : 'Welcome back, {name}!';
		$points_format  = isset($opts['o100_portal_member_points_format']) ? $opts['o100_portal_member_points_format'] : 'You have {points} Points';
		$member_earn    = isset($opts['o100_portal_member_earn_text']) ? $opts['o100_portal_member_earn_text'] : 'Ways to earn';
		$member_redeem  = isset($opts['o100_portal_member_redeem_text']) ? $opts['o100_portal_member_redeem_text'] : 'Ways to redeem';
		// Cart Settings
		$cart_icon       = isset($opts['o100_portal_cart_icon']) ? $opts['o100_portal_cart_icon'] : 'cart';
		$cart_show_upsell= isset($opts['o100_portal_cart_show_upsell']) ? $opts['o100_portal_cart_show_upsell'] : 'yes';
		$cart_show_promo = isset($opts['o100_portal_cart_show_promo']) ? $opts['o100_portal_cart_show_promo'] : 'yes';
		$cart_checkout_text = isset($opts['o100_portal_cart_checkout_text']) ? $opts['o100_portal_cart_checkout_text'] : 'Go to checkout';
		
		wp_enqueue_media();
		?>
		<!-- WIDGET MODE SELECTOR (Global) -->
		<div class="o100-pb-mode-selector" style="margin-bottom:24px; padding:20px; background:#fff; border-radius:12px; border:1px solid #e5e7eb; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
			<h3 style="margin:0 0 12px; font-size:16px; font-weight:700;">Widget Display Mode</h3>
			<div class="o100-radio-group">
				<label class="o100-radio-card" style="flex:1;">
					<input type="radio" name="o100_portal_widget_mode" value="standalone" <?php checked($widget_mode, 'standalone'); ?>>
					<span class="card-content" style="flex-direction:column; padding:16px;">
						<strong style="font-size:14px;">Standalone Popup</strong>
						<span style="font-size:12px; color:#6b7280; margin-top:4px;">Floating button + Rewards popup panel</span>
					</span>
				</label>
				<label class="o100-radio-card" style="flex:1;">
					<input type="radio" name="o100_portal_widget_mode" value="sidecart" <?php checked($widget_mode, 'sidecart'); ?>>
					<span class="card-content" style="flex-direction:column; padding:16px;">
						<strong style="font-size:14px;">Side Cart Integrated</strong>
						<span style="font-size:12px; color:#6b7280; margin-top:4px;">Cart + Rewards tabs in side panel</span>
					</span>
				</label>
			</div>
		</div>
		
		<div class="o100-portal-builder">
			<div class="o100-pb-config">
				<!-- PRIMARY CATEGORY TABS -->
				<div class="o100-pb-cat-tabs">
					<button type="button" class="o100-pb-cat active" data-cat="cart">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
						Cart
					</button>
					<button type="button" class="o100-pb-cat" data-cat="loyalty">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
						Loyalty
					</button>
				</div>
				
				<form id="o100-portal-form" method="post">
					<?php wp_nonce_field('o100_save_portal', 'o100_portal_nonce'); ?>
					<input type="hidden" name="o100_portal_widget_mode" id="o100_portal_widget_mode_hidden" value="<?php echo esc_attr($widget_mode); ?>">

					<!-- ====== CART CATEGORY ====== -->
					<div id="pb-cat-cart" class="o100-pb-cat-pane active">
						<h4>Cart Icon</h4>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Floating Button Icon</label>
								<div class="o100-radio-group" style="flex-wrap:wrap; gap:8px;">
									<label class="o100-radio-card" style="flex:0 0 auto;">
										<input type="radio" name="o100_portal_cart_icon" value="cart" <?php checked($cart_icon, 'cart'); ?>>
										<span class="card-content" style="padding:12px 16px; gap:8px;">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
											Cart
										</span>
									</label>
									<label class="o100-radio-card" style="flex:0 0 auto;">
										<input type="radio" name="o100_portal_cart_icon" value="bag" <?php checked($cart_icon, 'bag'); ?>>
										<span class="card-content" style="padding:12px 16px; gap:8px;">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
											Bag
										</span>
									</label>
									<label class="o100-radio-card" style="flex:0 0 auto;">
										<input type="radio" name="o100_portal_cart_icon" value="basket" <?php checked($cart_icon, 'basket'); ?>>
										<span class="card-content" style="padding:12px 16px; gap:8px;">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h2l3.6 7.59L6.25 13A2 2 0 008 16h12v-2H8l1.1-2h7.45a2 2 0 001.76-1.06L22 6H6.21"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
											Basket
										</span>
									</label>
								</div>
							</div>
						</div>
						
						<h4 style="margin-top:30px;">Side Cart Features</h4>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label class="o100-fluent-toggle">
									<input type="checkbox" name="o100_portal_cart_show_upsell" value="yes" <?php checked($cart_show_upsell, 'yes'); ?>>
									<span class="o100-toggle-slider"></span>
									Show Upsell / Cross-sell Recommendations
								</label>
								<p class="description">Display "Offers for you" product carousel at the bottom of cart.</p>
							</div>
						</div>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label class="o100-fluent-toggle">
									<input type="checkbox" name="o100_portal_cart_show_promo" value="yes" <?php checked($cart_show_promo, 'yes'); ?>>
									<span class="o100-toggle-slider"></span>
									Show Promotional Discount Cards
								</label>
								<p class="description">Show active promotions and discount offers inside the cart panel.</p>
							</div>
						</div>
						
						<h4 style="margin-top:30px;">Checkout</h4>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Checkout Button Text</label>
								<input type="text" name="o100_portal_cart_checkout_text" value="<?php echo esc_attr($cart_checkout_text); ?>" class="o100-fluent-input">
							</div>
						</div>
						
						<div style="margin-top:30px; padding:16px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; font-size:13px; color:#166534;">
							<strong>&#x2139;&#xFE0F; Cart Button Position</strong><br>
							<?php if ($widget_mode === 'standalone') : ?>
								In Standalone mode, the Cart button is fixed at <strong>middle-right</strong> of the page as an icon-only button.
							<?php else : ?>
								In Integrated mode, Cart shares the same side panel and launcher button with Loyalty. Configure the shared button in the <strong>Loyalty &rarr; Launcher Button</strong> tab.
							<?php endif; ?>
						</div>
					</div>

					<!-- ====== LOYALTY CATEGORY ====== -->
					<div id="pb-cat-loyalty" class="o100-pb-cat-pane" style="display:none;">
						<?php
						// Smart detection: count active campaigns
						global $wpdb;
						$lc_table = $wpdb->prefix . 'o100_loyalty_campaigns';
						$active_count = 0;
						if ( $wpdb->get_var("SHOW TABLES LIKE '{$lc_table}'") === $lc_table ) {
							$active_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$lc_table} WHERE status = 'active'");
						}
						?>
						<?php if ( $active_count === 0 ) : ?>
						<div style="margin-bottom:20px; padding:16px 20px; background:#fffbeb; border:1px solid #fde68a; border-radius:10px; display:flex; align-items:flex-start; gap:12px;">
							<span style="font-size:22px; flex-shrink:0;">&#x26A0;&#xFE0F;</span>
							<div>
								<strong style="font-size:14px; color:#92400e;">No Active Loyalty Campaigns</strong>
								<p class="description" style="margin:4px 0 0; color:#a16207;">You haven&rsquo;t created any loyalty campaigns yet. The Rewards widget will <strong>not be visible</strong> to customers until at least one active Earn or Redeem campaign exists. You can still configure the widget appearance below.</p>
								<a href="<?php echo admin_url('admin.php?page=order100&tab=loyalty'); ?>" style="display:inline-block; margin-top:8px; font-size:13px; font-weight:600; color:#4f46e5; text-decoration:none;">&rarr; Go to Loyalty Campaigns</a>
							</div>
						</div>
						<?php else : ?>
						<div style="margin-bottom:20px; padding:12px 20px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; display:flex; align-items:center; gap:10px;">
							<span style="font-size:18px;">&#x2705;</span>
							<span style="font-size:13px; color:#166534;"><strong><?php echo $active_count; ?> active campaign<?php echo $active_count > 1 ? 's' : ''; ?></strong> detected &mdash; Loyalty widget is visible to customers.</span>
						</div>
						<?php endif; ?>
						
						<div class="o100-pb-tabs">
							<button type="button" class="o100-pb-tab active" data-target="#pb-design">Design &amp; Panel</button>
							<button type="button" class="o100-pb-tab" data-target="#pb-content">Content &amp; Text</button>
							<button type="button" class="o100-pb-tab" data-target="#pb-launcher">Launcher Button</button>
						</div>
					<!-- DESIGN TAB -->
					<div id="pb-design" class="o100-pb-pane active">
						<h4>Brand Colors</h4>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-6">
								<label>Theme Color (Primary)</label>
								<input type="color" name="o100_portal_theme_color" value="<?php echo esc_attr($theme_color); ?>" class="o100-live-input" data-var="--theme-color">
							</div>
							<div class="o100-fluent-col o100-fluent-col-6">
								<label>Primary Button Text Color</label>
								<input type="color" name="o100_portal_btn_text_color" value="<?php echo esc_attr($btn_text_color); ?>" class="o100-live-input" data-var="--btn-text-color">
							</div>
						</div>
						<div class="o100-fluent-row o100-mode-field" data-mode="sidecart">
							<div class="o100-fluent-col o100-fluent-col-6">
								<label>Header Background Color</label>
								<input type="color" name="o100_portal_bg_color" value="<?php echo esc_attr($bg_color); ?>" class="o100-live-input" data-var="--bg-color">
							</div>
							<div class="o100-fluent-col o100-fluent-col-6">
								<label>Global Text Color</label>
								<input type="color" name="o100_portal_text_color" value="<?php echo esc_attr($text_color); ?>" class="o100-live-input" data-var="--text-color">
							</div>
						</div>
						<div class="o100-fluent-row o100-mode-field" data-mode="sidecart" style="margin-top:20px;">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Panel Border Radius (px)</label>
								<input type="number" name="o100_portal_border_radius" value="<?php echo esc_attr($border_radius); ?>" class="o100-fluent-input o100-live-input" data-var="--border-radius" data-suffix="px" min="0" max="40">
							</div>
						</div>
						
						<div class="o100-mode-field" data-mode="sidecart">
						<h4 style="margin-top:30px;">Custom Branding</h4>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Portal Logo (Optional)</label>
								<div style="display:flex; gap:10px;">
									<input type="text" id="o100_portal_logo" name="o100_portal_logo" value="<?php echo esc_attr($logo_url); ?>" class="o100-fluent-input" placeholder="Image URL...">
									<button type="button" class="button button-secondary" id="o100-upload-logo-btn">Upload</button>
								</div>
								<p class="description">Upload a custom logo to display inside the portal header. Overrides default title.</p>
							</div>
						</div>
						</div>
					</div>
					
					<!-- CONTENT & TABS TAB -->
					<div id="pb-content" class="o100-pb-pane" style="display:none;">
						<div class="o100-mode-field" data-mode="sidecart">
						<h4>Enabled Modules & Tab Labels</h4>
						<div class="o100-fluent-row" style="margin-bottom:20px;">
							<div class="o100-fluent-col o100-fluent-col-12">
								<table style="width:100%; border-collapse:collapse;">
									<tr>
										<td style="width:40px;"><input type="checkbox" name="o100_portal_enabled_tabs[]" value="cart" checked disabled></td>
										<td style="width:150px;"><strong>Shopping Cart (Required)</strong></td>
										<td><input type="text" name="o100_portal_tab_label_cart" value="<?php echo esc_attr($tab_cart); ?>" class="o100-fluent-input o100-live-input" data-target="#live-tab-cart"></td>
									</tr>
									<tr>
										<td><input type="checkbox" name="o100_portal_enabled_tabs[]" value="rewards" <?php checked(in_array('rewards', $enabled_tabs)); ?>></td>
										<td><strong>Rewards & Loyalty</strong></td>
										<td><input type="text" name="o100_portal_tab_label_rewards" value="<?php echo esc_attr($tab_rewards); ?>" class="o100-fluent-input o100-live-input" data-target="#live-tab-rewards"></td>
									</tr>
									<tr>
										<td><input type="checkbox" name="o100_portal_enabled_tabs[]" value="account" <?php checked(in_array('account', $enabled_tabs)); ?>></td>
										<td><strong>My Account</strong></td>
										<td><input type="text" name="o100_portal_tab_label_account" value="<?php echo esc_attr($tab_account); ?>" class="o100-fluent-input o100-live-input" data-target="#live-tab-account"></td>
									</tr>
								</table>
							</div>
						</div>
						
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Default Active Tab</label>
								<select name="o100_portal_default_tab" class="o100-fluent-input">
									<option value="cart" <?php selected($default_tab, 'cart'); ?>>Shopping Cart</option>
									<option value="rewards" <?php selected($default_tab, 'rewards'); ?>>Rewards</option>
								</select>
							</div>
						</div>
						</div>
						
						<h4 style="margin-top:30px;">Guest View Text</h4>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Welcome Title</label>
								<input type="text" name="o100_portal_guest_title" value="<?php echo esc_attr($guest_title); ?>" class="o100-fluent-input o100-live-input" data-target="#live-guest-title">
							</div>
						</div>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Subtitle</label>
								<textarea name="o100_portal_guest_subtitle" class="o100-fluent-input o100-live-input" data-target="#live-guest-sub"><?php echo esc_textarea($guest_sub); ?></textarea>
							</div>
						</div>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Join Button Text</label>
								<input type="text" name="o100_portal_guest_btn_text" value="<?php echo esc_attr($guest_btn); ?>" class="o100-fluent-input o100-live-input" data-target="#live-guest-btn">
							</div>
						</div>
						
						<h4 style="margin-top:30px;">Member View Text</h4>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Welcome Message <em>(Use {name})</em></label>
								<input type="text" name="o100_portal_member_welcome" value="<?php echo esc_attr($member_welcome); ?>" class="o100-fluent-input o100-live-input" data-target="#live-member-welcome">
							</div>
						</div>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Points Format <em>(Use {points})</em></label>
								<input type="text" name="o100_portal_member_points_format" value="<?php echo esc_attr($points_format); ?>" class="o100-fluent-input o100-live-input" data-target="#live-member-points">
							</div>
						</div>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-6">
								<label>Earn Points Label</label>
								<input type="text" name="o100_portal_member_earn_text" value="<?php echo esc_attr($member_earn); ?>" class="o100-fluent-input o100-live-input" data-target="#live-member-earn">
							</div>
							<div class="o100-fluent-col o100-fluent-col-6">
								<label>Redeem Rewards Label</label>
								<input type="text" name="o100_portal_member_redeem_text" value="<?php echo esc_attr($member_redeem); ?>" class="o100-fluent-input o100-live-input" data-target="#live-member-redeem">
							</div>
						</div>
					</div>
					
					<!-- LAUNCHER TAB -->
					<div id="pb-launcher" class="o100-pb-pane" style="display:none;">
						<h4>Desktop Launcher Customization</h4>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Visibility & Position</label>
								<div class="o100-radio-group">
									<label class="o100-radio-card">
										<input type="radio" name="o100_portal_launcher_position" value="bottom-right" class="o100-live-input" data-target-pos="#live-launcher-wrap" <?php checked($launcher_pos, 'bottom-right'); ?>>
										<span class="card-content">Bottom Right</span>
									</label>
									<label class="o100-radio-card">
										<input type="radio" name="o100_portal_launcher_position" value="bottom-left" class="o100-live-input" data-target-pos="#live-launcher-wrap" <?php checked($launcher_pos, 'bottom-left'); ?>>
										<span class="card-content">Bottom Left</span>
									</label>
									<label class="o100-radio-card o100-mode-field" data-mode="sidecart">
										<input type="radio" name="o100_portal_launcher_position" value="middle-right" class="o100-live-input" data-target-pos="#live-launcher-wrap" <?php checked($launcher_pos, 'middle-right'); ?>>
										<span class="card-content">Middle Right</span>
									</label>
									<label class="o100-radio-card o100-mode-field" data-mode="sidecart">
										<input type="radio" name="o100_portal_launcher_position" value="middle-left" class="o100-live-input" data-target-pos="#live-launcher-wrap" <?php checked($launcher_pos, 'middle-left'); ?>>
										<span class="card-content">Middle Left</span>
									</label>
									<label class="o100-radio-card o100-mode-field" data-mode="sidecart">
										<input type="radio" name="o100_portal_launcher_position" value="hidden" class="o100-live-input" data-target-pos="#live-launcher-wrap" <?php checked($launcher_pos, 'hidden'); ?>>
										<span class="card-content">Hidden (Trigger by link)</span>
									</label>
								</div>
							</div>
						</div>
						
						<!-- STANDALONE DISPLAY STYLE -->
						<div class="o100-fluent-row o100-mode-field" data-mode="standalone" style="margin-top:10px;">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Display Style</label>
								<div class="o100-radio-group">
									<label class="o100-radio-card">
										<input type="radio" name="o100_portal_launcher_style" value="icon_only" class="o100-live-input" data-target-style="#live-sa-launcher" <?php checked($launcher_style, 'icon_only'); ?>>
										<span class="card-content">Icon Only</span>
									</label>
									<label class="o100-radio-card">
										<input type="radio" name="o100_portal_launcher_style" value="icon_text" class="o100-live-input" data-target-style="#live-sa-launcher" <?php checked($launcher_style, 'icon_text'); ?>>
										<span class="card-content">Icon + Text</span>
									</label>
									<label class="o100-radio-card">
										<input type="radio" name="o100_portal_launcher_style" value="text_only" class="o100-live-input" data-target-style="#live-sa-launcher" <?php checked($launcher_style, 'text_only'); ?>>
										<span class="card-content">Text Only</span>
									</label>
								</div>
							</div>
						</div>
						
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-6">
								<label>Launcher Icon</label>
								<div class="o100-radio-group o100-icon-grid">
									<label class="o100-radio-card">
										<input type="radio" name="o100_portal_launcher_icon" value="cart" class="o100-live-input" data-target-icon="#live-launcher-icon" <?php checked($launcher_icon, 'cart'); ?>>
										<span class="card-content card-icon">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
										</span>
									</label>
									<label class="o100-radio-card">
										<input type="radio" name="o100_portal_launcher_icon" value="gift" class="o100-live-input" data-target-icon="#live-launcher-icon" <?php checked($launcher_icon, 'gift'); ?>>
										<span class="card-content card-icon">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
										</span>
									</label>
									<label class="o100-radio-card">
										<input type="radio" name="o100_portal_launcher_icon" value="star" class="o100-live-input" data-target-icon="#live-launcher-icon" <?php checked($launcher_icon, 'star'); ?>>
										<span class="card-content card-icon">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
										</span>
									</label>
									<label class="o100-radio-card">
										<input type="radio" name="o100_portal_launcher_icon" value="crown" class="o100-live-input" data-target-icon="#live-launcher-icon" <?php checked($launcher_icon, 'crown'); ?>>
										<span class="card-content card-icon">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>
										</span>
									</label>
								</div>
							</div>
							<div class="o100-fluent-col o100-fluent-col-6">
								<label>Button Shape</label>
								<div class="o100-radio-group">
									<label class="o100-radio-card">
										<input type="radio" name="o100_portal_launcher_shape" value="pill" class="o100-live-input" data-target-shape="#live-launcher-wrap" <?php checked($launcher_shape, 'pill'); ?>>
										<span class="card-content">Pill</span>
									</label>
									<label class="o100-radio-card">
										<input type="radio" name="o100_portal_launcher_shape" value="rounded" class="o100-live-input" data-target-shape="#live-launcher-wrap" <?php checked($launcher_shape, 'rounded'); ?>>
										<span class="card-content">Rounded</span>
									</label>
									<label class="o100-radio-card">
										<input type="radio" name="o100_portal_launcher_shape" value="square" class="o100-live-input" data-target-shape="#live-launcher-wrap" <?php checked($launcher_shape, 'square'); ?>>
										<span class="card-content">Square</span>
									</label>
								</div>
							</div>
						</div>
						
						<div class="o100-fluent-row" style="margin-top: 10px;">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Launcher Text</label>
								<input type="text" name="o100_portal_launcher_text" value="<?php echo esc_attr($launcher_text); ?>" class="o100-fluent-input o100-live-input" data-target="#live-launcher-text">
								<p class="description">Text is only visible when using Bottom Right/Left positions.</p>
							</div>
						</div>

						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-6">
								<label>Edge Spacing (px)</label>
								<input type="number" name="o100_portal_launcher_spacing" value="<?php echo esc_attr($launcher_spacing); ?>" class="o100-fluent-input o100-live-input" data-var="--launcher-spacing" data-suffix="px" min="0" max="100">
							</div>
							<div class="o100-fluent-col o100-fluent-col-6">
								<label>Smart Animations</label>
								<select name="o100_portal_launcher_animation" class="o100-fluent-input">
									<option value="none" <?php selected($launcher_anim, 'none'); ?>>No Animation</option>
									<option value="pulse" <?php selected($launcher_anim, 'pulse'); ?>>Pulse on Notification / Cart Items</option>
									<option value="bounce" <?php selected($launcher_anim, 'bounce'); ?>>Bounce on Load</option>
								</select>
							</div>
						</div>
						
						<div class="o100-mode-field" data-mode="sidecart">
							<h4 style="margin-top:30px;">Mobile Navigation</h4>
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label class="o100-fluent-toggle">
									<input type="checkbox" name="o100_portal_mobile_integration" value="on" <?php checked($mobile_nav, 'on'); ?>>
									<span class="o100-toggle-slider"></span>
									Integrate "Rewards" trigger with Bottom App Menu on Mobile
								</label>
								<p class="description">Replaces traditional My Orders link with unified Portal.</p>
							</div>
						</div>
						</div> <!-- /Mobile Navigation wrapper -->
					</div>
				
				</div><!-- /pb-cat-loyalty -->
			</form>
			</div>
			
			<?php
			// Generate cards for preview
			$earn_cards_html = '';
			$redeem_cards_html = '';

			if ( true ) {
				// Earn Cards
				global $wpdb;
				$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';
				$earn_campaigns = $wpdb->get_results( "SELECT * FROM {$campaigns_table} WHERE type != 'points_conversion' AND type != 'punch_card' AND status = 'active'" );
				if ( !empty($earn_campaigns) && is_array($earn_campaigns) ) {
					foreach ($earn_campaigns as $c) {
						$icon_str = $c->type;
						$icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#f3f4f6; color:#6b7280;">⭐</div>';
						if (strpos($icon_str, 'point') !== false || strpos($icon_str, 'order') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#e0e7ff; color:#4f46e5;">$</div>';
						elseif (strpos($icon_str, 'signup') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fef3c7; color:#d97706;">+</div>';
						elseif (strpos($icon_str, 'birthday') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fce7f3; color:#db2777;">🎁</div>';
						elseif (strpos($icon_str, 'share') !== false || strpos($icon_str, 'social') !== false || strpos($icon_str, 'referral') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#dbeafe; color:#2563eb;">🔗</div>';
						elseif (strpos($icon_str, 'review') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#d1fae5; color:#059669;">📝</div>';
						
						$title = $c->name;
						$desc = wp_strip_all_tags($c->description);
						$c_ui = json_decode($c->ui_json, true) ?: [];
						$point_val = (int)($c_ui['earn_point'] ?? 0);
						
						$earn_cards_html .= '<div class="o100-pbp-card" onclick="o100OpenDetail(\'earn\', \''.rawurlencode(wp_strip_all_tags($title)).'\', \''.rawurlencode(wp_strip_all_tags($desc)).'\', \''.rawurlencode($icon_html).'\', \''.$point_val.'\')" style="background:#fff; border:1px solid #e5e7eb; border-radius:var(--border-radius); padding:16px; margin-bottom:12px; display:flex; align-items:center; gap:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02); cursor:pointer;">'. $icon_html .'<div style="flex:1;"><h4 style="margin:0 0 4px; font-size:14px; color:var(--text-color); font-weight:700;">'.esc_html(wp_strip_all_tags($title)).'</h4><p style="margin:0; font-size:12px; color:#6b7280;">'.esc_html(wp_strip_all_tags($desc)).'</p></div></div>';
					}
				}

				// Redeem Cards
				$redeem_rewards = $wpdb->get_results( "SELECT * FROM {$campaigns_table} WHERE type = 'points_conversion' AND status = 'active'" );
				if ( !empty($redeem_rewards) && is_array($redeem_rewards) ) {
					foreach ( $redeem_rewards as $reward ) {
						$ui_json = json_decode($reward->ui_json, true) ?: [];
						$icon_str = isset($ui_json['reward_type']) ? $ui_json['reward_type'] : 'default';
						$icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#f3e8ff; color:#9333ea;">🔄</div>';
						
						$title = $reward->name;
						$desc = wp_strip_all_tags($reward->description);
						$point_val = isset($ui_json['conversion_points']) ? (int)$ui_json['conversion_points'] : 0;
						
						$redeem_cards_html .= '<div class="o100-pbp-card" onclick="o100OpenDetail(\'redeem\', \''.rawurlencode(wp_strip_all_tags($title)).'\', \''.rawurlencode(wp_strip_all_tags($desc)).'\', \''.rawurlencode($icon_html).'\', \''.$point_val.'\')" style="background:#fff; border:1px solid #e5e7eb; border-radius:var(--border-radius); padding:16px; margin-bottom:12px; display:flex; align-items:center; gap:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02); cursor:pointer;">'. $icon_html .'<div style="flex:1;"><h4 style="margin:0 0 4px; font-size:14px; color:var(--text-color); font-weight:700;">'.esc_html(wp_strip_all_tags($title)).'</h4><p style="margin:0; font-size:12px; color:#6b7280;">'.esc_html(wp_strip_all_tags($desc)).'</p></div></div>';
					}
				}
			}
			
			if ( empty($earn_cards_html) ) {
				$earn_cards_html = '<div style="text-align:center; padding:24px 0; color:#9ca3af; font-size:13px;">No earn campaigns available yet.</div>';
			}
			if ( empty($redeem_cards_html) ) {
				$redeem_cards_html = '<div style="text-align:center; padding:24px 0; color:#9ca3af; font-size:13px;">No rewards available yet.</div>';
			}
			?>
			<div class="o100-pb-preview">
				<!-- PREVIEW CONTROLS -->
				<div class="o100-pbp-controls">
					<div class="o100-pbp-control-group">
						<span style="color:#6b7280; font-weight:normal;">Preview Mode:</span>
						<label><input type="radio" name="pbp-auth" value="guest" checked> Guest</label>
						<label><input type="radio" name="pbp-auth" value="member"> Member</label>
					</div>
				</div>
				
				<!-- SIDECART PREVIEW -->
				<div id="pbp-preview-sidecart" class="o100-mode-preview" data-mode="sidecart">
				<div class="o100-pb-preview-device" style="
					--theme-color: <?php echo esc_attr($theme_color); ?>; 
					--bg-color: <?php echo esc_attr($bg_color); ?>;
					--text-color: <?php echo esc_attr($text_color); ?>;
					--btn-text-color: <?php echo esc_attr($btn_text_color); ?>;
					--border-radius: <?php echo esc_attr($border_radius); ?>px;
					--launcher-spacing: <?php echo esc_attr($launcher_spacing); ?>px;
				">
					<!-- HEADER -->
					<div class="o100-pbp-header">
						<div class="o100-pbp-logo">
							<?php if ($logo_url) : ?>
								<img src="<?php echo esc_url($logo_url); ?>" id="live-logo-img">
							<?php else: ?>
								<span id="live-logo-text">Store Portal</span>
							<?php endif; ?>
							<button class="o100-pbp-close">&times;</button>
						</div>
						<div class="o100-pbp-tabs">
							<div class="o100-pbp-tab o100-pbp-tab-trigger" data-target="cart"><span id="live-tab-cart"><?php echo esc_html($tab_cart); ?></span> <span class="o100-pbp-badge">2</span></div>
							<div class="o100-pbp-tab o100-pbp-tab-trigger active" data-target="rewards"><span id="live-tab-rewards"><?php echo esc_html($tab_rewards); ?></span></div>
						</div>
					</div>
					
					<div class="o100-pbp-body">
						<!-- REWARDS VIEW -->
						<div id="pbp-view-rewards">
							<!-- GUEST -->
							<div class="o100-pbp-guest-view">
								<div class="o100-pbp-banner-box" style="background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; text-align:center; margin-bottom:24px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
									<h3 id="live-guest-title"><?php echo esc_html($guest_title); ?></h3>
									<p id="live-guest-sub"><?php echo esc_html($guest_sub); ?></p>
									<div class="o100-pbp-btn" id="live-guest-btn"><?php echo esc_html($guest_btn); ?></div>
									<div class="o100-pbp-login-text">Already have an account? Sign in</div>
								</div>
								
								<!-- GUEST FOLDER TABS -->
								<div class="o100-sc-inner-tabs">
									<div class="o100-sc-inner-tab active" onclick="document.getElementById('guest-earn-cards').style.display='block'; document.getElementById('guest-redeem-cards').style.display='none'; this.parentElement.children[0].classList.add('active'); this.parentElement.children[1].classList.remove('active');">Earn</div>
									<div class="o100-sc-inner-tab" onclick="document.getElementById('guest-earn-cards').style.display='none'; document.getElementById('guest-redeem-cards').style.display='block'; this.parentElement.children[0].classList.remove('active'); this.parentElement.children[1].classList.add('active');">Redeem</div>
								</div>
								
								<div class="o100-sc-tab-content-wrapper">
									<div id="guest-earn-cards">
										<?php echo $earn_cards_html; ?>
									</div>
									<div id="guest-redeem-cards" style="display:none;">
										<?php echo $redeem_cards_html; ?>
									</div>
								</div>
								
								<div class="o100-pbp-refer-box">
									<h4>Refer and Earn</h4>
									<p>Give your friends 10% off their first order and get $5 off when they buy.</p>
								</div>
							</div>
							
							<!-- MEMBER -->
							<div class="o100-pbp-member-view" style="display:none;">
								<div class="o100-pbp-banner-box" style="background: linear-gradient(135deg, var(--theme-color), #0f172a); border-radius: 20px; padding: 24px; text-align:left; margin-bottom:24px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); position:relative; overflow:hidden;">
									<div style="font-size:14px; font-weight:600; margin-bottom:4px; color:rgba(255,255,255,0.8);" id="live-member-welcome"><?php echo esc_html(str_replace('{name}', 'Kevin', $member_welcome)); ?></div>
									<div style="font-size:32px; font-weight:800; color:#fff;" id="live-member-points"><?php echo esc_html(str_replace('{points}', '120', $points_format)); ?></div>
									<div style="margin-top:16px;">
										<div style="display:flex; justify-content:space-between; font-size:12px; color:rgba(255,255,255,0.9); margin-bottom:6px; font-weight:600;">
											<span>Current: Bronze</span>
											<span>Next: Silver (500 pts)</span>
										</div>
										<div style="background:rgba(255,255,255,0.2); height:6px; border-radius:3px; overflow:hidden;">
											<div style="background:#fff; width:40%; height:100%; border-radius:3px; transition:width 1s ease;"></div>
										</div>
									</div>
								</div>
								
								<!-- MEMBER FOLDER TABS -->
								<div class="o100-sc-inner-tabs">
									<div class="o100-sc-inner-tab active" onclick="document.getElementById('member-earn-cards').style.display='block'; document.getElementById('member-redeem-cards').style.display='none'; this.parentElement.children[0].classList.add('active'); this.parentElement.children[1].classList.remove('active');">
										<span id="live-member-earn"><?php echo esc_html($member_earn); ?></span>
									</div>
									<div class="o100-sc-inner-tab" onclick="document.getElementById('member-earn-cards').style.display='none'; document.getElementById('member-redeem-cards').style.display='block'; this.parentElement.children[0].classList.remove('active'); this.parentElement.children[1].classList.add('active');">
										<span id="live-member-redeem"><?php echo esc_html($member_redeem); ?></span> <span class="o100-sc-inner-badge">1</span>
									</div>
								</div>
								
								<div class="o100-sc-tab-content-wrapper">
									<div id="member-earn-cards">
										<?php echo $earn_cards_html; ?>
									</div>
									<div id="member-redeem-cards" style="display:none;">
										<?php echo $redeem_cards_html; ?>
									</div>
								</div>
								
								<div class="o100-pbp-refer-box">
									<h4>Refer and Earn</h4>
									<p>Give your friends 10% off their first order and get $5 off when they buy.</p>
									<div class="o100-pbp-refer-link">
										<input type="text" value="http://yoursite.com/?wlr=1234" readonly>
										<button>Copy</button>
									</div>
									<div class="o100-pbp-refer-socials">
										<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg></span>
										<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></span>
									</div>
								</div>
							</div>
							
							<!-- DETAIL VIEW PANE -->
							<div id="o100-sc-detail-view" style="display:none; padding:24px; background:#fff; border-radius:20px; box-shadow:0 4px 6px rgba(0,0,0,0.02); margin-bottom:24px; border:1px solid #e5e7eb;">
								<button onclick="o100CloseDetail()" style="background:transparent; border:none; color:#6b7280; font-weight:600; font-size:14px; cursor:pointer; padding:0; display:flex; align-items:center; gap:4px; margin-bottom:24px;">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
									Back
								</button>
								<div style="text-align:center;">
									<div id="o100-detail-icon" style="display:inline-block; margin-bottom:16px; transform:scale(1.5);"></div>
									<h3 id="o100-detail-title" style="font-size:20px; font-weight:800; color:var(--text-color); margin:0 0 8px;"></h3>
									<p id="o100-detail-desc" style="font-size:14px; color:#6b7280; line-height:1.5; margin:0 0 24px;"></p>
									<button id="o100-detail-btn" style="width:100%; background:var(--theme-color); color:#fff; border:none; border-radius:12px; padding:12px; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,0.1);"></button>
								</div>
							</div>
						</div>
						
						<!-- CART VIEW -->
						<div id="pbp-view-cart" style="display:none;">
							<div class="o100-pbp-box" style="display:flex; gap:12px; align-items:center; padding:12px;">
								<div style="width:50px; height:50px; background:#e5e7eb; border-radius:8px;"></div>
								<div style="flex:1;">
									<div style="font-weight:600; font-size:14px; margin-bottom:4px;">Signature Burger</div>
									<div style="font-size:13px; color:var(--theme-color); font-weight:600;">$14.99</div>
								</div>
								<div style="background:#f3f4f6; border-radius:20px; padding:4px 12px; font-size:13px; font-weight:600;">1</div>
							</div>
							<div class="o100-pbp-box" style="display:flex; gap:12px; align-items:center; padding:12px;">
								<div style="width:50px; height:50px; background:#e5e7eb; border-radius:8px;"></div>
								<div style="flex:1;">
									<div style="font-weight:600; font-size:14px; margin-bottom:4px;">French Fries</div>
									<div style="font-size:13px; color:var(--theme-color); font-weight:600;">$4.99</div>
								</div>
								<div style="background:#f3f4f6; border-radius:20px; padding:4px 12px; font-size:13px; font-weight:600;">2</div>
							</div>
							
							<div style="margin-top:auto; padding-top:20px; border-top:1px solid #e5e7eb;">
								<div style="display:flex; justify-content:space-between; font-weight:700; font-size:16px; margin-bottom:16px;">
									<span>Total</span>
									<span>$24.97</span>
								</div>
								<div class="o100-pbp-btn" style="text-align:center;">Checkout Now</div>
							</div>
						</div>
					</div>
					
					<!-- LAUNCHER -->
					<div id="live-launcher-wrap" class="o100-pbp-launcher <?php echo esc_attr($launcher_style . ' shape-' . $launcher_shape . ' pos-' . $launcher_pos); ?>">
						<div id="live-launcher-icon">
							<?php 
							if ($launcher_icon === 'gift') {
								echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>';
							} elseif ($launcher_icon === 'star') {
								echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
							} elseif ($launcher_icon === 'crown') {
								echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>';
							} else {
								// cart
								echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>';
							}
							?>
						</div>
						<span id="live-launcher-text"><?php echo esc_html($launcher_text); ?></span>
					</div>
				</div>
				</div><!-- /sidecart preview -->
			<!-- STANDALONE PREVIEW -->
			<div id="pbp-preview-standalone" class="o100-mode-preview" data-mode="standalone" style="display:none;">
				<div class="o100-pb-preview-device o100-sa-loyalty-preview" style="--theme-color:<?php echo esc_attr($theme_color); ?>;--btn-text-color:<?php echo esc_attr($btn_text_color); ?>;--launcher-spacing:<?php echo esc_attr($launcher_spacing); ?>px; position:relative; min-height:520px; background:#f0f2f5; border-radius:12px; border:1px solid #e5e7eb; overflow:hidden; display:flex; flex-direction:column;">
					<!-- Faux page background -->
					<div style="flex:1; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:13px; letter-spacing:1px; font-weight:500;">STORE PAGE</div>
					
					<!-- Popup Panel (overlays bottom-right) -->
					<div id="sa-popup-panel" class="o100-sa-popup" style="position:absolute; bottom:76px; right:20px; width:300px; background:#fff; border-radius:16px; box-shadow:0 12px 40px rgba(0,0,0,.18); overflow:hidden; display:flex; flex-direction:column; max-height:420px;">
						<!-- Level 1: Home -->
						<div class="o100-sa-level active" data-sa-level="home">
							<div style="background:var(--theme-color); padding:16px 18px; display:flex; justify-content:space-between; align-items:center;">
								<span style="color:#fff; font-size:16px; font-weight:800;">Rewards</span>
								<span style="color:rgba(255,255,255,.7); font-size:18px; cursor:pointer;">&times;</span>
							</div>
							<div style="padding:14px; background:#f8fafc; overflow-y:auto;">
								<!-- Guest card -->
								<div class="o100-pbp-guest-view">
									<div style="background:var(--theme-color); color:#fff; border-radius:12px; padding:20px 16px; text-align:center; margin-bottom:14px;">
										<h4 id="live-sa-guest-title" style="margin:0 0 6px; font-size:15px; font-weight:800; color:#fff;"><?php echo esc_html($guest_title); ?></h4>
										<p id="live-sa-guest-sub" style="margin:0 0 12px; font-size:12px; opacity:.9; line-height:1.4;"><?php echo esc_html($guest_sub); ?></p>
										<div id="live-sa-guest-btn" style="display:inline-block; background:#fff; color:var(--theme-color); padding:8px 24px; border-radius:8px; font-weight:700; font-size:13px;"><?php echo esc_html($guest_btn); ?></div>
									</div>
								</div>
								<!-- Member card -->
								<div class="o100-pbp-member-view" style="display:none;">
									<div style="background:var(--theme-color); color:#fff; border-radius:12px; padding:20px 16px; text-align:center; margin-bottom:14px;">
										<div style="font-size:12px; opacity:.85; margin-bottom:2px;" id="live-sa-member-welcome"><?php echo esc_html(str_replace("{name}", "Kevin", $member_welcome)); ?></div>
										<div style="font-size:28px; font-weight:900; line-height:1.2;" id="live-sa-member-points"><?php echo esc_html(str_replace("{points}", "120", $points_format)); ?></div>
										<div style="font-size:11px; opacity:.7; margin-top:4px; text-transform:uppercase; letter-spacing:.5px;">Gold Member</div>
									</div>
								</div>
								<!-- Earn Nav Card -->
								<div class="o100-sa-nav-card" data-sa-goto="earn" style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:14px; margin-bottom:8px; display:flex; align-items:center; gap:12px; cursor:pointer;">
									<div style="width:40px; height:40px; border-radius:10px; background:#f0fdf4; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--theme-color)" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v12M6 12h12"/></svg>
									</div>
									<div style="flex:1;">
										<strong style="display:block; font-size:14px; color:#0f172a;">Earn</strong>
										<span style="font-size:11px; color:#64748b;">Complete tasks to earn points</span>
									</div>
									<span style="font-size:18px; color:#9ca3af;">›</span>
								</div>
								<!-- Redeem Nav Card -->
								<div class="o100-sa-nav-card" data-sa-goto="redeem" style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:14px; margin-bottom:8px; display:flex; align-items:center; gap:12px; cursor:pointer;">
									<div style="width:40px; height:40px; border-radius:10px; background:#fef2f2; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--theme-color)" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect></svg>
									</div>
									<div style="flex:1;">
										<strong style="display:block; font-size:14px; color:#0f172a;">Redeem</strong>
										<span style="font-size:11px; color:#64748b;">Use points for rewards</span>
									</div>
									<span style="font-size:18px; color:#9ca3af;">›</span>
								</div>
								<!-- Referral card (conditional in frontend) -->
								<div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:14px; margin-bottom:8px;">
									<strong style="display:block; font-size:13px; color:#0f172a; margin-bottom:4px;">Refer and earn</strong>
									<span style="font-size:11px; color:#64748b; line-height:1.5;">Refer your friends and earn rewards. Your friend can get a reward as well!</span>
								</div>
								<div style="text-align:center; margin-top:8px;">
									<a href="#" style="color:var(--theme-color); font-size:12px; font-weight:600; text-decoration:none;">View Full Dashboard &rarr;</a>
								</div>
							</div>
							<!-- Branding -->
							<div style="text-align:center; padding:10px 14px; border-top:1px solid #f1f5f9; font-size:10px; color:#94a3b8;">Powered by <strong style="color:#64748b;">Order100</strong></div>
						</div>
						<!-- Level 2: Earn -->
						<div class="o100-sa-level" data-sa-level="earn" style="display:none;">
							<div style="background:var(--theme-color); padding:14px 18px; display:flex; align-items:center; gap:10px;">
								<span class="o100-sa-back" data-sa-back="home" style="color:rgba(255,255,255,.8); font-size:18px; cursor:pointer;">&larr;</span>
								<span style="color:#fff; font-size:15px; font-weight:700; flex:1;">Earn</span>
								<span style="color:rgba(255,255,255,.7); font-size:18px; cursor:pointer;">&times;</span>
							</div>
							<div style="padding:14px; background:#f8fafc; overflow-y:auto;">
								<?php echo $earn_cards_html; ?>
							</div>
						</div>
						<!-- Level 2: Redeem -->
						<div class="o100-sa-level" data-sa-level="redeem" style="display:none;">
							<div style="background:var(--theme-color); padding:14px 18px; display:flex; align-items:center; gap:10px;">
								<span class="o100-sa-back" data-sa-back="home" style="color:rgba(255,255,255,.8); font-size:18px; cursor:pointer;">&larr;</span>
								<span style="color:#fff; font-size:15px; font-weight:700; flex:1;">Redeem</span>
								<span style="color:rgba(255,255,255,.7); font-size:18px; cursor:pointer;">&times;</span>
							</div>
							<div style="padding:14px; background:#f8fafc; overflow-y:auto;">
								<?php echo $redeem_cards_html; ?>
							</div>
						</div>
					</div>
					
					<!-- Cart FAB (middle-right, icon-only) -->
					<div id="live-sa-cart-btn" class="o100-pbp-launcher shape-pill" style="position:absolute; right:20px; top:50%; margin-top:-24px; left:auto; bottom:auto; padding:0; width:48px; height:48px; justify-content:center; z-index:2;">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
						<span style="position:absolute; top:-6px; right:-6px; background:var(--btn-text-color); color:var(--theme-color); font-size:10px; font-weight:800; min-width:18px; height:18px; border-radius:9px; display:flex; align-items:center; justify-content:center; padding:0 4px;">2</span>
					</div>
					
					<!-- Loyalty Launcher Button -->
					<div id="live-sa-launcher" class="o100-pbp-launcher <?php echo esc_attr("shape-" . $launcher_shape . " pos-" . $launcher_pos); ?>" style="<?php echo $launcher_style === "icon_only" ? "padding:0; width:48px; justify-content:center;" : ""; ?> z-index:2;">
						<div id="live-sa-launcher-icon">
							<?php
							$icon_svgs = array(
								'gift'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>',
								'star'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>',
								'crown' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>',
							);
							echo isset($icon_svgs[$launcher_icon]) ? $icon_svgs[$launcher_icon] : $icon_svgs['gift'];
							?>
						</div>
						<?php if ($launcher_style !== "icon_only") : ?>
						<span id="live-sa-launcher-text"><?php echo esc_html($launcher_text); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- STANDALONE CART PREVIEW -->
			<div id="pbp-preview-sa-cart" class="o100-mode-preview" data-mode="standalone" style="display:none;">
				<div class="o100-pb-preview-device" style="--theme-color:#0f172a;--btn-text-color:#ffffff; display:flex; flex-direction:column; height:600px; background:#fff; overflow:hidden; border-radius:12px; border:1px solid #e5e7eb;">
					<!-- Side Cart Header -->
					<div style="padding:16px 20px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
						<span style="font-size:16px; font-weight:800; color:#111;">Your Order</span>
						<span style="font-size:20px; color:#9ca3af; cursor:pointer;">&times;</span>
					</div>
					
					<!-- Cart Items -->
					<div style="flex:1; overflow-y:auto; padding:0;">
						<?php
						$mock_items = array(
							array('name' => 'Kung Pao Chicken', 'price' => '$18.95', 'qty' => 1),
							array('name' => 'Beef Broccoli', 'price' => '$19.95', 'qty' => 1),
							array('name' => 'Spring Rolls (4pc)', 'price' => '$8.50', 'qty' => 2),
						);
						foreach ($mock_items as $item) : ?>
						<div style="display:flex; align-items:center; padding:14px 20px; border-bottom:1px solid #f5f5f5; gap:12px;">
							<div style="width:48px; height:48px; background:#f3f4f6; border-radius:8px; flex-shrink:0; display:flex; align-items:center; justify-content:center;">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
							</div>
							<div style="flex:1; min-width:0;">
								<div style="font-size:13px; font-weight:600; color:#111; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html($item['name']); ?></div>
								<div style="font-size:13px; color:#111; font-weight:700; margin-top:2px;"><?php echo esc_html($item['price']); ?></div>
							</div>
							<div style="display:flex; align-items:center; gap:0; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
								<span style="padding:4px 8px; font-size:12px; color:#9ca3af; cursor:pointer;">-</span>
								<span style="padding:4px 8px; font-size:13px; font-weight:600; color:#111; min-width:20px; text-align:center;"><?php echo esc_html($item['qty']); ?></span>
								<span style="padding:4px 8px; font-size:12px; color:#9ca3af; cursor:pointer;">+</span>
							</div>
						</div>
						<?php endforeach; ?>
						
						<!-- Upsell Area -->
						<div id="live-cart-upsell" style="padding:16px 20px; border-top:6px solid #f5f5f5; <?php echo $cart_show_upsell !== 'yes' ? 'display:none;' : ''; ?>">
							<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
								<span style="font-size:14px; font-weight:700; color:#111;">Offers for you</span>
								<div style="display:flex; gap:4px;">
									<span style="width:24px; height:24px; border:1px solid #e5e7eb; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; color:#6b7280;">‹</span>
									<span style="width:24px; height:24px; border:1px solid #e5e7eb; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; color:#6b7280;">›</span>
								</div>
							</div>
							<div style="display:flex; gap:10px;">
								<div style="width:80px; text-align:center;">
									<div style="width:70px; height:70px; background:#f9fafb; border-radius:10px; margin:0 auto 6px;"></div>
									<div style="font-size:11px; font-weight:500; color:#111;">Fried Rice</div>
									<div style="font-size:11px; color:#6b7280;">$12.95</div>
								</div>
								<div style="width:80px; text-align:center;">
									<div style="width:70px; height:70px; background:#f9fafb; border-radius:10px; margin:0 auto 6px;"></div>
									<div style="font-size:11px; font-weight:500; color:#111;">Wonton Soup</div>
									<div style="font-size:11px; color:#6b7280;">$9.50</div>
								</div>
							</div>
						</div>
					</div>
					
					<!-- Footer -->
					<div style="padding:16px 20px; border-top:1px solid #e5e7eb; flex-shrink:0;">
						<div style="display:flex; justify-content:space-between; margin-bottom:12px;">
							<span style="font-size:15px; font-weight:600; color:#111;">Subtotal</span>
							<span style="font-size:15px; font-weight:800; color:#111;">$66.85</span>
						</div>
						<div id="live-cart-checkout-btn" style="background:var(--theme-color); color:var(--btn-text-color); padding:14px; border-radius:10px; text-align:center; font-weight:700; font-size:14px; cursor:pointer;"><?php echo esc_html($cart_checkout_text); ?></div>
					</div>
				</div>
				
				<!-- Cart FAB at middle-right -->
				<div class="o100-pbp-launcher shape-pill" style="right:var(--launcher-spacing, 20px); top:50%; margin-top:-26px; left:auto; bottom:auto; padding:0; width:48px; justify-content:center; --theme-color:#0f172a;--btn-text-color:#ffffff;">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
					<span style="position:absolute; top:-6px; right:-6px; background:var(--btn-text-color); color:var(--theme-color); font-size:10px; font-weight:800; min-width:18px; height:18px; border-radius:9px; display:flex; align-items:center; justify-content:center; padding:0 4px;">3</span>
				</div>
			</div>

			</div><!-- /o100-pb-preview -->
		</div><!-- /o100-portal-builder -->

		<style>
		.o100-portal-builder { display:flex; gap:30px; margin-top:20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
		.o100-pb-config { flex:1; min-width:0; background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05); border:1px solid #e5e7eb; padding:24px; }
		.o100-pb-preview { width:380px; flex-shrink:0; position:sticky; top:40px; align-self:start; }
		
		.o100-pb-cat-tabs { display:flex; gap:0; margin-bottom:0; background:#f8fafc; border-radius:10px; padding:4px; border:1px solid #e5e7eb; }
		.o100-pb-cat { flex:1; display:flex; align-items:center; justify-content:center; gap:8px; background:none; border:none; padding:14px 20px; font-size:15px; font-weight:700; color:#6b7280; border-radius:8px; cursor:pointer; transition:all 0.2s ease; }
		.o100-pb-cat:hover { color:#111; background:rgba(255,255,255,0.6); }
		.o100-pb-cat.active { color:#4f46e5; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.08); }
		.o100-pb-cat svg { opacity:0.6; }
		.o100-pb-cat.active svg { opacity:1; stroke:#4f46e5; }
		.o100-pb-cat-pane { }
		.o100-pb-tabs { display:flex; gap:0; border-bottom:1px solid #e5e7eb; margin-bottom:24px; }
		.o100-pb-tab { background:none; border:none; padding:12px 20px; font-size:14px; font-weight:600; color:#6b7280; border-bottom:2px solid transparent; cursor:pointer; margin-bottom:-1px; }
		.o100-pb-tab:hover { color:#111; }
		.o100-pb-tab.active { color:#4f46e5; border-bottom-color:#4f46e5; }
		
		/* Preview Controls */
		.o100-pbp-controls { background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:12px; display:flex; gap:20px; align-items:center; }
		.o100-pbp-control-group { display:flex; gap:10px; font-size:13px; font-weight:600; color:#4b5563; }
		
		/* Preview Device - Right Sidebar Style */
		.o100-pb-preview-device { background:#fff; border:1px solid #e5e7eb; height:680px; overflow:hidden; position:relative; display:flex; flex-direction:column; box-shadow:-8px 0 24px rgba(0,0,0,0.06); color:var(--text-color); border-radius: 8px; border-right: none; border-bottom:none; border-top:none; margin-left: auto; width: 100%; }
		.o100-pbp-header { background:var(--bg-color); border-bottom:1px solid #e5e7eb; padding:16px 20px 0; }
		.o100-pbp-logo { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
		.o100-pbp-logo img { max-height:30px; max-width:150px; }
		.o100-pbp-logo span { font-weight:800; font-size:18px; color:var(--text-color); }
		.o100-pbp-close { background:none; border:none; font-size:24px; line-height:1; color:#9ca3af; padding:0; cursor:pointer;}
		.o100-pbp-tabs { display:flex; gap:16px; }
		.o100-pbp-tab { padding-bottom:12px; font-size:14px; font-weight:600; color:#6b7280; border-bottom:2px solid transparent; display:flex; align-items:center; gap:6px; cursor:pointer; }
		.o100-pbp-tab.active { color:var(--theme-color); border-bottom-color:var(--theme-color); }
		.o100-pbp-badge { background:var(--theme-color); color:var(--btn-text-color); font-size:10px; font-weight:700; padding:2px 6px; border-radius:10px; line-height:1; }
		
		.o100-pbp-body { padding:20px; flex:1; background:#f9fafb; display:flex; flex-direction:column; overflow-y:auto; padding-bottom:100px; }
		
		/* Content Styling Parity */
		.o100-pbp-banner-box { text-align:center; margin-bottom:24px; }
		.o100-pbp-banner-box h3 { margin:0 0 8px; font-size:20px; color:var(--text-color); font-weight:800; line-height:1.2; }
		.o100-pbp-banner-box p { margin:0 0 16px; font-size:13px; color:#6b7280; line-height:1.4; }
		.o100-pbp-btn { background:var(--theme-color); color:var(--btn-text-color); padding:12px; border-radius:var(--border-radius); font-weight:600; font-size:14px; transition:opacity 0.2s; cursor:pointer; text-align:center; }
		.o100-pbp-login-text { font-size:12px; margin-top:12px; color:#6b7280; text-align:center; }
		
		.o100-pbp-section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#6b7280; margin:16px 0 8px; }
		
		.o100-sc-inner-tabs { display:flex; background:#f3f4f6; border-radius:24px 24px 0 0; position:relative; z-index:2; margin-bottom:0; padding-top:6px; }
		.o100-sc-inner-tab { flex:1; text-align:center; padding:12px 12px; font-size:14px; font-weight:700; color:#6b7280; cursor:pointer; background:transparent; border-radius:20px 20px 0 0; position:relative; z-index:1; transition:all 0.2s ease; display:flex; align-items:center; justify-content:center; gap:6px; user-select:none; }
		.o100-sc-inner-tab.active { background:#fff; color:var(--text-color); z-index:3; padding-top:16px; margin-top:-4px; }
		.o100-sc-inner-tab:first-child.active::after { content:''; position:absolute; bottom:0; right:-24px; width:24px; height:24px; background:radial-gradient(circle at top right, transparent 24px, #fff 24.5px); pointer-events:none; }
		.o100-sc-inner-tab:last-child.active::before { content:''; position:absolute; bottom:0; left:-24px; width:24px; height:24px; background:radial-gradient(circle at top left, transparent 24px, #fff 24.5px); pointer-events:none; }
		.o100-sc-tab-content-wrapper { background:#fff; border-radius:0 0 20px 20px; padding:20px; position:relative; z-index:1; margin-bottom:24px; box-shadow:0 4px 6px rgba(0,0,0,0.02); }
		.o100-sc-inner-badge { background:#ef4444; color:#fff; font-size:10px; font-weight:800; padding:2px 6px; border-radius:10px; line-height:1; position:absolute; top:-6px; right:12px; }
		
		.o100-pbp-card { background:#fff; border-radius:var(--border-radius); padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02); margin-bottom:12px; border:1px solid #e5e7eb; display:flex; align-items:center; gap:16px; }
		.o100-pbp-card-icon { width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; }
		.o100-pbp-card-info { flex:1; }
		.o100-pbp-card-info h4 { margin:0 0 4px; font-size:14px; color:var(--text-color); font-weight:700; }
		.o100-pbp-card-info p { margin:0; font-size:12px; color:#6b7280; }
		
		.o100-pbp-refer-box { margin-top:24px; padding:20px; border-radius:var(--border-radius); background:#eef2ff; color:#3730a3; text-align:center; }
		.o100-pbp-refer-box h4 { margin:0 0 8px; font-size:16px; font-weight:800; color:#312e81; }
		.o100-pbp-refer-box p { margin:0 0 16px; font-size:13px; line-height:1.4; color:#4338ca; }
		
		.o100-pbp-refer-link { display:flex; gap:0; border-radius:6px; overflow:hidden; border:1px solid #c7d2fe; margin-bottom:12px; }
		.o100-pbp-refer-link input { flex:1; border:none; padding:8px 12px; font-size:13px; background:#fff; color:#4f46e5; outline:none; }
		.o100-pbp-refer-link button { border:none; background:#4f46e5; color:#fff; font-weight:600; padding:0 12px; cursor:pointer; }
		.o100-pbp-refer-socials { display:flex; gap:12px; justify-content:center; }
		.o100-pbp-refer-socials span { display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:#4f46e5; cursor:pointer; }
		.o100-pbp-refer-socials svg { width:16px; height:16px; }
		
		/* Launcher Styling Parity with Frontend */
		.o100-pbp-launcher { position:absolute; bottom:var(--launcher-spacing); background:var(--theme-color); color:var(--btn-text-color); height:48px; display:flex; align-items:center; padding:0 20px; gap:8px; font-weight:600; font-size:14px; box-shadow:0 4px 12px rgba(0,0,0,0.15); transition:all 0.3s; z-index:99; }
		
		/* Shape Modifiers */
		.o100-pbp-launcher.shape-pill { border-radius:24px; }
		.o100-pbp-launcher.shape-rounded { border-radius:12px; }
		.o100-pbp-launcher.shape-square { border-radius:0; }
		
		/* Style Modifiers */
		.o100-pbp-launcher.icon_only { padding:0; width:48px; justify-content:center; }
		.o100-pbp-launcher.icon_only span { display:none; }
		.o100-pbp-launcher.hidden { display:none; }
		
		/* Position Modifiers */
		.o100-pbp-launcher.pos-bottom-right { right:var(--launcher-spacing); left:auto; bottom:var(--launcher-spacing); top:auto; margin-top:0; }
		.o100-pbp-launcher.pos-bottom-left { left:var(--launcher-spacing); right:auto; bottom:var(--launcher-spacing); top:auto; margin-top:0; }
		.o100-pbp-launcher.pos-middle-right { right:var(--launcher-spacing); left:auto; top:50%; bottom:auto; margin-top:-26px; }
		.o100-pbp-launcher.pos-middle-left { left:var(--launcher-spacing); right:auto; top:50%; bottom:auto; margin-top:-26px; }
		
		/* Radio Cards UI */
		.o100-radio-group { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 4px; }
		.o100-radio-card { flex: 1; min-width: 100px; position: relative; }
		.o100-radio-card input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
		.o100-radio-card .card-content { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px; cursor: pointer; transition: all 0.2s; background: #fff; color: #4b5563; font-weight: 500; font-size: 13px; text-align: center; }
		.o100-radio-card input[type="radio"]:checked + .card-content { border-color: #4f46e5; color: #4f46e5; background: #f5f3ff; box-shadow: 0 0 0 1px #4f46e5 inset; }
		.o100-radio-card input[type="radio"]:focus-visible + .card-content { outline: 2px solid #4f46e5; outline-offset: 2px; }
		.o100-radio-card .card-icon { display: flex; align-items: center; justify-content: center; }
		.o100-radio-card .card-icon svg { width: 20px; height: 20px; }
		
		.o100-icon-grid { gap: 8px; }
		.o100-icon-grid .o100-radio-card { flex: none; min-width: 48px; }
		</style>
		
		<script>
		jQuery(function($){
			// CATEGORY TAB SWITCHING (Cart / Loyalty)
			$('.o100-pb-cat').on('click', function(){
				$('.o100-pb-cat').removeClass('active');
				$(this).addClass('active');
				$('.o100-pb-cat-pane').hide();
				$('#pb-cat-' + $(this).data('cat')).show();
				
				// In Integrated mode: sync preview tab
				var mode = $('input[name="o100_portal_widget_mode"]:checked').val() || $('#o100_portal_widget_mode_hidden').val();
				if (mode === 'sidecart') {
					var cat = $(this).data('cat');
					if (cat === 'cart') {
						$('.o100-sc-header-tab').removeClass('active');
						$('.o100-sc-header-tab[data-tab="cart"]').addClass('active');
						$('.o100-sc-tab-pane').hide();
						$('.o100-sc-tab-pane[data-pane="cart"]').show();
					} else {
						$('.o100-sc-header-tab').removeClass('active');
						$('.o100-sc-header-tab[data-tab="rewards"]').addClass('active');
						$('.o100-sc-tab-pane').hide();
						$('.o100-sc-tab-pane[data-pane="rewards"]').show();
					}
				}
				// In Standalone mode: switch preview panels
				if (mode === 'standalone') {
					var cat = $(this).data('cat');
					$('#pbp-preview-standalone').toggle(cat === 'loyalty');
					$('#pbp-preview-sa-cart').toggle(cat === 'cart');
				}
			});
			
			// REVERSE: clicking preview tabs syncs left category (Integrated mode)
			$(document).on('click', '.o100-sc-header-tab', function(){
				var tab = $(this).data('tab');
				if (tab === 'cart') {
					$('.o100-pb-cat').removeClass('active');
					$('.o100-pb-cat[data-cat="cart"]').addClass('active');
					$('.o100-pb-cat-pane').hide();
					$('#pb-cat-cart').show();
				} else if (tab === 'rewards') {
					$('.o100-pb-cat').removeClass('active');
					$('.o100-pb-cat[data-cat="loyalty"]').addClass('active');
					$('.o100-pb-cat-pane').hide();
					$('#pb-cat-loyalty').show();
				}
			});
			
			// LOYALTY MASTER TOGGLE
			// Sub-Tab Switching (Design / Content / Launcher)
			$('.o100-pb-tab').on('click', function(){
				$('.o100-pb-tab').removeClass('active');
				$(this).addClass('active');
				$('.o100-pb-pane').hide();
				$($(this).data('target')).show();
			});
			
			// Live Preview Updates - Variables
			$('.o100-live-input[data-var]').on('input change', function(){
				var val = $(this).val();
				if($(this).data('suffix')) val += $(this).data('suffix');
				$('.o100-pb-preview-device').css($(this).data('var'), val);
			});
			
			// Live Preview Updates - Text Content
			$('.o100-live-input[data-target]').on('input', function(){
				var val = $(this).val();
				var target = $(this).data('target');
				// Quick format replacements for preview
				val = val.replace('{name}', 'Kevin').replace('{points}', '120');
				$(target).text(val);
				// Also update standalone preview mirror (live-sa-* counterpart)
				var saTarget = target.replace('#live-', '#live-sa-');
				if (saTarget !== target) $(saTarget).text(val);
			});
			
			// Live Preview Updates - Classes
			$('.o100-live-input[data-target-shape]').on('change', function(){
				var shape = 'shape-'+$(this).val();
				$($(this).data('target-shape')).removeClass('shape-pill shape-rounded shape-square').addClass(shape);
				$('#live-sa-launcher').removeClass('shape-pill shape-rounded shape-square').addClass(shape);
			});
			$('.o100-live-input[data-target-pos]').on('change', function(){
				var pos = $(this).val();
				var $target = $($(this).data('target-pos'));
				var $saTarget = $('#live-sa-launcher');
				
				$target.removeClass('pos-bottom-right pos-bottom-left pos-middle-right pos-middle-left hidden').addClass('pos-' + pos);
				$saTarget.removeClass('pos-bottom-right pos-bottom-left pos-middle-right pos-middle-left hidden').addClass('pos-' + pos);
				
				// Handle dynamic style based on position for sidecart
				$target.removeClass('icon_text icon_only');
				if (pos === 'hidden') {
					$target.addClass('hidden');
				} else if (pos.indexOf('middle') !== -1) {
					$target.addClass('icon_only');
				} else {
					$target.addClass('icon_text');
				}
			});

			$('.o100-live-input[data-target-style]').on('change', function(){
				var style = $(this).val();
				var $saTarget = $('#live-sa-launcher');
				$saTarget.removeClass('icon_only icon_text text_only').addClass(style);
				
				if (style === 'icon_only') {
					$saTarget.css({padding: 0, width: '48px', justifyContent: 'center'});
					$('#live-sa-launcher-text').hide();
					$('#live-sa-launcher-icon').show();
				} else if (style === 'text_only') {
					$saTarget.css({padding: '0 20px', width: 'auto', justifyContent: 'flex-start'});
					$('#live-sa-launcher-text').show();
					$('#live-sa-launcher-icon').hide();
				} else {
					$saTarget.css({padding: '0 20px', width: 'auto', justifyContent: 'flex-start'});
					$('#live-sa-launcher-text').show();
					$('#live-sa-launcher-icon').show();
				}
			});
			
			// Live Preview Updates - Icon
			$('.o100-live-input[data-target-icon]').on('change', function(){
				var icon = $(this).val();
				var svg = '';
				if(icon === 'gift') svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>';
				else if(icon === 'star') svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
				else if(icon === 'crown') svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>';
				else svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>';
				// Update both sidecart and standalone previews
				$($(this).data('target-icon')).html(svg);
				$('#live-sa-launcher-icon').html(svg);
			});
			
			// Media Uploader for Logo
			var mediaUploader;
			$('#o100-upload-logo-btn').click(function(e) {
				e.preventDefault();
				if (mediaUploader) { mediaUploader.open(); return; }
				mediaUploader = wp.media.frames.file_frame = wp.media({
					title: 'Choose Portal Logo', button: { text: 'Choose Logo' }, multiple: false
				});
				mediaUploader.on('select', function() {
					var attachment = mediaUploader.state().get('selection').first().toJSON();
					$('#o100_portal_logo').val(attachment.url);
					if($('#live-logo-img').length) {
						$('#live-logo-img').attr('src', attachment.url);
					} else {
						$('#live-logo-text').replaceWith('<img src="'+attachment.url+'" id="live-logo-img">');
					}
				});
				mediaUploader.open();
			});
			
			// Interactivity Preview Controls
			$('input[name="pbp-auth"]').change(function(){
				if($(this).val() === 'guest') {
					$('.o100-pbp-guest-view').show();
					$('.o100-pbp-member-view').hide();
				} else {
					$('.o100-pbp-guest-view').hide();
					$('.o100-pbp-member-view').show();
				}
			});
			
			// Click tabs directly in preview to switch
			$('.o100-pbp-tab-trigger').on('click', function(){
				$('.o100-pbp-tab-trigger').removeClass('active');
				$(this).addClass('active');
				if($(this).data('target') === 'cart') {
					$('#pbp-view-cart').show();
					$('#pbp-view-rewards').hide();
				} else {
					$('#pbp-view-cart').hide();
					$('#pbp-view-rewards').show();
				}
			});
			
			// MODE SWITCHING
			$('input[name="o100_portal_widget_mode"]').on('change', function(){
				var mode = $(this).val();
				// Sync hidden field inside the form
				$('#o100_portal_widget_mode_hidden').val(mode);
				// Show/hide mode-specific config
				$('.o100-mode-field').each(function(){
					var fieldMode = $(this).data('mode');
					if(fieldMode === mode) { $(this).show(); } else { $(this).hide(); }
				});
				// Switch preview
				$('.o100-mode-preview').hide();
				if (mode === 'standalone') {
					// Show only the preview matching the active category tab
					var activeCat = $('.o100-pb-cat.active').data('cat') || 'cart';
					$('#pbp-preview-standalone').toggle(activeCat === 'loyalty');
					$('#pbp-preview-sa-cart').toggle(activeCat === 'cart');
				} else {
					$('#pbp-preview-sidecart').show();
				}
				// If switching to standalone and position is middle/hidden, reset to bottom-right
				if(mode === 'standalone') {
					var pos = $('input[name="o100_portal_launcher_position"]:checked').val();
					if(pos === 'middle-right' || pos === 'middle-left' || pos === 'hidden') {
						$('input[name="o100_portal_launcher_position"][value="bottom-right"]').prop('checked', true).trigger('change');
					}
				}
			}).trigger('change');
			
			// SA Popup 3-level navigation in preview
			$(document).on('click', '.o100-sa-nav-card', function(){
				var goto = $(this).data('sa-goto');
				var panel = $(this).closest('.o100-sa-popup');
				panel.find('.o100-sa-level').hide();
				panel.find('.o100-sa-level[data-sa-level="'+goto+'"]').show();
			});
			$(document).on('click', '.o100-sa-back', function(){
				var back = $(this).data('sa-back');
				var panel = $(this).closest('.o100-sa-popup');
				panel.find('.o100-sa-level').hide();
				panel.find('.o100-sa-level[data-sa-level="'+back+'"]').show();
			});
			
			// Live Icon Switching for launcher
			
			
			// Live Cart checkout text update
			$('input[name="o100_portal_cart_checkout_text"]').on('input', function(){
				$('#live-cart-checkout-btn').text($(this).val());
			});
			
			// Live Cart upsell/promo toggle
			$('input[name="o100_portal_cart_show_upsell"]').on('change', function(){
				$('#live-cart-upsell').toggle($(this).is(':checked'));
			});
			
			// Form Submission Override
			$('.o100-fluent-top-save').off('click').on('click', function(e){
				// If we are on the portal tab
				if ($('#o100-portal-form').length) {
					e.preventDefault();
					var $btn = $(this);
					var ogText = $btn.text();
					$btn.text('Saving...').prop('disabled', true);
					
					var data = $('#o100-portal-form').serialize() + '&action=o100_save_portal_settings';
					$.post(ajaxurl, data, function(res){
						$btn.text('Saved!');
						setTimeout(function(){ $btn.text(ogText).prop('disabled', false); }, 2000);
					});
				}
			});
		});
		</script>
		<?php
	}
}




// TS: 20260113114226
