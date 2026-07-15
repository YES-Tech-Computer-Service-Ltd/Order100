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

		// AJAX handlers for Store Portal
		add_action( 'wp_ajax_o100_save_portal_settings', array( $this, 'ajax_save_portal_settings' ) );

		// AJAX handlers for Locations Manager
		add_action( 'wp_ajax_o100_save_store_profile', array( $this, 'ajax_save_store_profile' ) );
		add_action( 'wp_ajax_o100_save_checkout_ext', array( $this, 'ajax_save_checkout_ext' ) );
		add_action( 'wp_ajax_o100_save_delivery', array( $this, 'ajax_save_delivery' ) );
		add_action( 'wp_ajax_o100_save_pickup', array( $this, 'ajax_save_pickup' ) );
		add_action( 'wp_ajax_o100_save_misc', array( $this, 'ajax_save_misc' ) );
								add_action( 'wp_ajax_o100_toggle_locations', array( $this, 'ajax_toggle_locations' ) );
		add_action( 'wp_ajax_o100_toggle_module', array( $this, 'ajax_toggle_module' ) );

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

		
		// Force Legacy Option for Location Hours
		
		
		// Register custom CMB2 field types
		add_action( 'cmb2_render_o100_product_search', array( $this, 'render_product_search_field' ), 10, 5 );
		add_action( 'cmb2_render_o100_category_search', array( $this, 'render_category_search_field' ), 10, 5 );
		add_action( 'cmb2_render_o100_phone_intl', array( $this, 'render_phone_intl_field' ), 10, 5 );
		add_filter( 'cmb2_sanitize_o100_phone_intl', array( $this, 'sanitize_phone_intl_field' ), 10, 5 );
		
		// Add AJAX endpoints for our custom UI
		add_action( 'wp_ajax_o100_mcd_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_o100_mcd_search_categories', array( $this, 'ajax_search_categories' ) );
		add_action( 'wp_ajax_o100_mcd_search_crm_tags', array( $this, 'ajax_search_crm_tags' ) );
		add_action( 'wp_ajax_o100_mcd_search_crm_lists', array( $this, 'ajax_search_crm_lists' ) );
		add_action( 'wp_ajax_o100_upload_label_icon', array( $this, 'ajax_upload_label_icon' ) );
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

		// Exclude Freemius auto-generated pages so our CSS doesn't break their native UI
		if ( false !== strpos( $hook, '-pricing' ) || false !== strpos( $hook, '-account' ) || false !== strpos( $hook, '-contact' ) ) {
			return;
		}

			// Only load the heavy media library on tabs that actually use media uploads
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : '';
		$media_tabs = array( 'seo', 'portal', 'appearance', 'notification', '' ); // '' = default/profile tab
		if ( in_array( $active_tab, $media_tabs, true ) ) {
			wp_enqueue_media();
		}

		wp_enqueue_style( 'o100-admin-css', O100_URL . 'assets/css/o100-admin.css', array(), time() );
		wp_enqueue_style( 'o100-fluent-admin-css', O100_URL . 'assets/css/o100-fluent-admin.css', array(), time() );
		wp_enqueue_script( 'o100-admin-js', O100_URL . 'assets/js/o100-admin.js', array( 'jquery' ), time(), true );
		
		// Alpine JS for SPA views
		add_filter( 'script_loader_tag', function( $tag, $handle, $src ) {
			if ( 'alpinejs' === $handle || 'alpine-js' === $handle ) {
				$tag = str_replace( '<script ', '<script crossorigin="anonymous" ', $tag );
			}
			return $tag;
		}, 10, 3 );
		wp_enqueue_script( 'alpinejs', 'https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js', array(), '3.13.3', true );

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
			'tabIds'   => array( 'time', 'checkout', 'api', 'nav', 'discount', 'loyalty', 'seo', 'notification', 'portal' ),
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

		$loc_opts = get_option( 'o100_locations', array() );
		$is_loc_enabled = !empty( $loc_opts['o100_enable_loc'] ) && $loc_opts['o100_enable_loc'] === 'on';
		if ( $is_loc_enabled ) {
			$cmb->add_field( array(
				'name' => '',
				'desc' => '<div style="background:#f0f9ff; border-left:4px solid #F59322; padding:12px 16px; border-radius:4px; color:#b06d04; margin-bottom:15px; font-size:14px;"><strong style="display:block; margin-bottom:4px;">📍 Multi-Location is Enabled</strong>' . esc_html__( 'You are currently configuring the Global Store Hours. You can override these hours for specific locations by visiting the ', 'order100' ) . '<a href="#" onclick="jQuery(\'.nav-tab[href=\\\'#o100_locations\\\']\').click(); return false;" style="font-weight:600; color:#F59322; text-decoration:underline;">' . esc_html__( 'Locations Tab', 'order100' ) . '</a>' . esc_html__( ' and editing a location. If a location\'s hours are left blank, it will automatically use these global hours.', 'order100' ) . '</div>',
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
		$branches_title = __( 'Branches', 'order100' );
		if ( function_exists('O100_License') && ! O100_License()->is_premium() ) {
			$branches_title .= ' ' . O100_License()->get_pro_badge('Limit 1 branch in Free version');
		}
		
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_locations',
			'title'        => $branches_title,
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



		// TAB: Misc
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_misc',
			'title'        => __( 'Misc', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_misc',
			'display_cb'   => '__return_false',
		) );

		
		// ═══════════════════════════════════════════════════════════════════
		// Food Labels Library
		// ═══════════════════════════════════════════════════════════════════
		$cmb->add_field( array(
			'id'   => 'o100_global_food_labels',
			'type' => 'text',
			'attributes' => array(
				'type' => 'hidden',
			),
			'sanitization_cb' => function( $value, $field_args, $field ) {
				if ( is_string( $value ) ) {
					$decoded = json_decode( wp_unslash( $value ), true );
					if ( is_array( $decoded ) ) {
						return $decoded;
					}
				}
				return $value;
			},
			'render_row_cb' => array( $this, 'render_food_labels_table' )
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
			'default' => '#b06d04',
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
				'linkedin' => 'LinkedIn',
				'instagram' => 'Instagram',
				'tiktok' => 'TikTok',
				'pinterest' => 'Pinterest',
				'telegram' => 'Telegram',
				'line' => 'LINE',
				'viber' => 'Viber',
			),
			'attributes' => array(
				'data-conditional-id' => 'o100_enable_social',
				'data-conditional-value' => 'on',
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

		// -- Block: Google Maps --
		$cmb->add_field( array(
			'id'   => 'o100_integ_gmap_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Google Maps API', 'order100') . '</h3><p>' . esc_html__('Required for Address Autocomplete and Delivery Distance Calculation. Restrict this key by HTTP Referrers in Google Cloud Console.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Google Maps API Key', 'order100' ),
			'id'   => 'o100_ggmap_api_js',
			'type' => 'text',
			'classes' => 'o100-half',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_integ_gmap_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div>'; }
		) );

		// -- Block: WhatsApp --
		$cmb->add_field( array(
			'id'   => 'o100_integ_wa_start',
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
			'id'   => 'o100_integ_wa_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div>'; }
		) );

		// -- Block: App Connect --
		$cmb->add_field( array(
			'id'   => 'o100_integ_app_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('App Connect', 'order100') . '</h3><p>' . esc_html__('Connect and manage dedicated Order100 tablet devices for your kitchen or front desk.', 'order100') . '</p></div><div class="o100-settings-group-content">';
				// Global QR Code Generation UI and Device List will be rendered here via a specific field or JS injection
			}
		) );

		// We use a custom field to render the complex device list and generate QR button
		$cmb->add_field( array(
			'name' => '',
			'id'   => 'o100_app_devices_html',
			'type' => 'title',
			'render_row_cb' => array( 'O100_App_Pairing', 'render_app_devices_settings_ui' )
		) );

		$cmb->add_field( array(
			'id'   => 'o100_integ_app_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div>'; }
		) );

		// -- Block: SMS & Voice Testing --
		$cmb->add_field( array(
			'id'   => 'o100_integ_testing_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Mobile Connection Testing', 'order100') . '</h3><p>' . esc_html__('Verify your SMS API connection and Voice call routing directly by sending test notifications.', 'order100') . '</p></div><div class="o100-settings-group-content">';
				echo '<div style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 8px;">';
				
				// Left half: SMS Test Form
				echo '  <div style="flex: 1; min-width: 300px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; box-sizing: border-box;">';
				echo '    <h4 style="margin: 0 0 12px 0; font-size: 14.5px; font-weight: 600; color: #374151;">💬 Send Test SMS</h4>';
				echo '    <div style="margin-bottom: 12px;">';
				echo '      <label style="display: block; font-size: 12.5px; color: #4b5563; margin-bottom: 6px;">Test Phone Number:</label>';
				echo '      <input type="text" id="o100_api_test_sms_phone" placeholder="e.g. +1234567890" class="regular-text" style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 12px; font-size: 13.5px; box-sizing: border-box;">';
				echo '    </div>';
				echo '    <div style="margin-bottom: 16px;">';
				echo '      <label style="display: block; font-size: 12.5px; color: #4b5563; margin-bottom: 6px;">Message Content:</label>';
				echo '      <textarea id="o100_api_test_sms_message" placeholder="Hello from Order100 API test!" style="width: 100%; height: 75px; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 12px; font-size: 13px; box-sizing: border-box; resize: vertical;"></textarea>';
				echo '    </div>';
				echo '    <button type="button" class="button button-secondary" onclick="o100ApiTestSMS()" style="display: inline-flex; align-items: center; gap: 6px;"><span class="dashicons dashicons-email-alt" style="line-height: inherit; font-size: 16px; width:16px; height:16px;"></span> Send SMS</button>';
				echo '  </div>';
				
				// Right half: Voice Test Form
				echo '  <div style="flex: 1; min-width: 300px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; box-sizing: border-box;">';
				echo '    <h4 style="margin: 0 0 12px 0; font-size: 14.5px; font-weight: 600; color: #374151;">📞 Send Test Voice Call</h4>';
				echo '    <div style="margin-bottom: 12px;">';
				echo '      <label style="display: block; font-size: 12.5px; color: #4b5563; margin-bottom: 6px;">Test Phone Number:</label>';
				echo '      <input type="text" id="o100_api_test_voice_phone" placeholder="e.g. +1234567890" class="regular-text" style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 12px; font-size: 13.5px; box-sizing: border-box;">';
				echo '    </div>';
				echo '    <div style="margin-bottom: 16px;">';
				echo '      <label style="display: block; font-size: 12.5px; color: #4b5563; margin-bottom: 6px;">Spoken Message Script:</label>';
				echo '      <textarea id="o100_api_test_voice_message" placeholder="This is a test voice call from Order100 connection manager!" style="width: 100%; height: 75px; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 12px; font-size: 13px; box-sizing: border-box; resize: vertical;"></textarea>';
				echo '    </div>';
				echo '    <button type="button" class="button button-secondary" onclick="o100ApiTestVoice()" style="display: inline-flex; align-items: center; gap: 6px;"><span class="dashicons dashicons-phone" style="line-height: inherit; font-size: 16px; width:16px; height:16px;"></span> Initiate Call</button>';
				echo '  </div>';
				
				echo '</div>';
				
				// Inline testing JavaScript
				echo '
				<script>
				function o100ApiTestSMS() {
					var phone = jQuery("#o100_api_test_sms_phone").val();
					var message = jQuery("#o100_api_test_sms_message").val();
					if (!phone) { alert("Please enter a phone number."); return; }
					
					jQuery.post(ajaxurl, {
						action: "o100_test_sms",
						phone: phone,
						message: message,
						nonce: "' . wp_create_nonce('o100_sms_test') . '"
					}, function(res) {
						if (res.success) {
							alert("Success: " + (res.data || "Test SMS sent successfully!"));
						} else {
							alert("Failed: " + (res.data || "Unknown error"));
						}
					});
				}
				function o100ApiTestVoice() {
					var phone = jQuery("#o100_api_test_voice_phone").val();
					var message = jQuery("#o100_api_test_voice_message").val();
					if (!phone) { alert("Please enter a phone number."); return; }
					
					jQuery.post(ajaxurl, {
						action: "o100_test_voice_call",
						phone: phone,
						message: message,
						nonce: "' . wp_create_nonce('o100_voice_test') . '"
					}, function(res) {
						if (res.success) {
							alert("Success: " + (res.data || "Test call initiated successfully!"));
						} else {
							alert("Failed: " + (res.data || "Unknown error"));
						}
					});
				}
				</script>
				';
			}
		) );

		$cmb->add_field( array(
			'id'   => 'o100_integ_testing_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div>'; }
		) );


		// ═══════════════════════════════════════════════════════════════════
		// STANDALONE PAGE: Discount
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
			'render_row_cb' => array( $this, 'render_notification_tab_start' ),
		) );




		// --- TABS UI INJECTION ---
		$cmb->add_field( array(
			'name' => '',
			'id'   => 'o100_sms_tabs_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<style>
					/* --- Service Provider grid layout --- */
					#sms-config .cmb2-metabox { display: grid; grid-template-columns: 1fr 1fr; gap: 0 32px; }
					#sms-config .cmb-row { grid-column: span 1; }
					#sms-config .cmb-row:first-child { grid-column: 1 / -1; }
					#sms-config .cmb2-id-o100-sms-gateway { order: 1; }
					#sms-config .cmb2-id-o100-sms-sender-number { order: 2; }
					#sms-config .cmb2-id-o100-sms-api-key { order: 3; grid-column: 1 / -1; }
					#sms-config .cmb2-id-o100-sms-api-secret { order: 4; grid-column: 1 / -1; }
					#sms-config .cmb-row .cmb-td select,
					#sms-config .cmb-row .cmb-td input[type="text"],
					#sms-config .cmb-row .cmb-td input[type="password"] { width: 100% !important; max-width: 100% !important; box-sizing: border-box; }
					#sms-templates .cmb-type-title, #voice-config .cmb-type-title { border: none !important; padding: 0 !important; background: transparent !important; margin: 0 !important; }
					#sms-templates .cmb-type-title .cmb2-metabox-title, #voice-config .cmb-type-title .cmb2-metabox-title { padding: 0 !important; margin: 0 !important; border: none !important; }
					.o100-hide-conditionally { display: none !important; }
					.o100-toggle-block { border-left: none; padding-left: 0; margin-left: 0; margin-top: 10px; margin-bottom: 20px; transition: opacity 0.2s ease; }
					@media (max-width: 768px) { .o100-toggle-status-text { display: none !important; } }

					/* Card Radio Selection Styling (Fluent Card Style) */
					.o100-radio-cards-row ul.cmb2-radio-list {
						display: flex !important;
						flex-direction: row !important;
						gap: 16px !important;
						margin: 0 !important;
						padding: 0 !important;
						list-style: none !important;
						width: 100% !important;
					}
					.o100-radio-cards-row ul.cmb2-radio-list li {
						flex: 1 !important;
						margin: 0 !important;
						padding: 0 !important;
						display: block !important;
					}
					.o100-radio-cards-row ul.cmb2-radio-list li label {
						display: flex !important;
						align-items: center !important;
						padding: 12px 18px !important;
						border: 1px solid #cbd5e1 !important;
						border-radius: 8px !important;
						background: #ffffff !important;
						cursor: pointer !important;
						transition: all 0.2s ease !important;
						font-size: 14px !important;
						font-weight: 500 !important;
						color: #334155 !important;
						position: relative !important;
						user-select: none !important;
						box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
					}
					.o100-radio-cards-row ul.cmb2-radio-list li input[type="radio"] {
						position: absolute !important;
						opacity: 0 !important;
						width: 0 !important;
						height: 0 !important;
						margin: 0 !important;
						padding: 0 !important;
					}
					.o100-radio-cards-row ul.cmb2-radio-list li label::before {
						content: "" !important;
						display: inline-block !important;
						width: 18px !important;
						height: 18px !important;
						border: 2px solid #cbd5e1 !important;
						border-radius: 50% !important;
						margin-right: 12px !important;
						box-sizing: border-box !important;
						transition: all 0.2s ease !important;
						background: #fff !important;
						flex-shrink: 0 !important;
					}
					.o100-radio-cards-row ul.cmb2-radio-list li:hover label {
						border-color: #94a3b8 !important;
						background: #f8fafc !important;
					}
					.o100-radio-cards-row ul.cmb2-radio-list li input[type="radio"]:checked + label {
						border-color: #F59322 !important;
						background: #eff6ff !important;
						color: #1e3a8a !important;
					}
					.o100-radio-cards-row ul.cmb2-radio-list li input[type="radio"]:checked + label::before {
						border-color: #F59322 !important;
						background: #F59322 !important;
						box-shadow: inset 0 0 0 4px #fff !important;
					}
					@media (max-width: 640px) {
						.o100-radio-cards-row ul.cmb2-radio-list {
							flex-direction: column !important;
						}
					}

					/* Inner phone custom numbers block - grid of branch cards */
					.o100-voice-custom-phones-wrap {
						display: flex; /* Removed !important so jQuery slideDown/slideUp can work */
						flex-wrap: wrap !important;
						gap: 16px !important;
						background: #f8fafc !important;
						border-left: 3px solid #F59322 !important;
						padding: 1.5rem !important;
						margin: 1rem 0 !important;
						border-radius: 0 8px 8px 0 !important;
						box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.02) !important;
						transition: opacity 0.2s ease;
					}
					#voice-config .o100-voice-custom-phones-wrap .cmb-row {
						flex: 1 1 calc(50% - 8px) !important;
						max-width: calc(50% - 8px) !important;
						min-width: 280px !important;
						background: #ffffff !important;
						border: 1px solid #e2e8f0 !important;
						border-radius: 8px !important;
						padding: 16px !important;
						box-sizing: border-box !important;
						box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.02) !important;
						display: flex !important;
						flex-direction: column !important;
						align-items: stretch !important;
						margin: 0 !important;
					}
					.o100-voice-custom-phones-wrap .cmb-row .cmb-th {
						width: 100% !important;
						padding: 0 0 8px 0 !important;
						margin: 0 !important;
						border-bottom: 1px solid #f1f5f9 !important;
						margin-bottom: 12px !important;
					}
					.o100-voice-custom-phones-wrap .cmb-row .cmb-th label {
						font-weight: 600 !important;
						color: #334155 !important;
						font-size: 13.5px !important;
						margin: 0 !important;
					}
					.o100-voice-custom-phones-wrap .cmb-row .cmb-td {
						width: 100% !important;
						padding: 0 !important;
						margin: 0 !important;
					}
					@media (max-width: 640px) {
						.o100-voice-custom-phones-wrap .cmb-row {
							flex: 1 1 100% !important;
							max-width: 100% !important;
						}
					}
					
					/* Premium Custom Styles to avoid WP native settings styling */
					.o100-sms-fields-container input[type="text"],
					.o100-sms-fields-container input[type="password"],
					.o100-sms-fields-container input[type="number"],
					.o100-sms-fields-container input[type="tel"],
					.o100-sms-fields-container select,
					.o100-sms-fields-container textarea {
						border: 1px solid #d1d5db !important;
						border-radius: 6px !important;
						padding: 8px 12px !important;
						font-size: 13.5px !important;
						color: #1f2937 !important;
						background-color: #fff !important;
						box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
						transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
						width: 100% !important;
						max-width: 100% !important;
						height: auto !important;
						line-height: 1.4 !important;
					}
					.o100-sms-fields-container input:focus,
					.o100-sms-fields-container select:focus,
					.o100-sms-fields-container textarea:focus {
						border-color: #F59322 !important;
						box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
						outline: none !important;
					}
					.o100-sms-fields-container textarea { min-height: 80px !important; }
					.o100-sms-fields-container .cmb2-metabox .cmb-row .cmb-th {
						width: 25% !important;
						padding: 16px 20px 16px 0 !important;
						font-weight: 500 !important;
						color: #374151 !important;
						font-size: 14px !important;
					}
					.o100-sms-fields-container .cmb2-metabox .cmb-row .cmb-td {
						padding: 16px 0 !important;
					}
					.o100-sms-fields-container .cmb-row {
						border-bottom: 1px solid #f3f4f6 !important;
						padding: 0 !important;
					}
					.o100-sms-fields-container .cmb-row:last-child {
						border-bottom: none !important;
					}
					.o100-sms-fields-container .cmb2-metabox {
						width: 100% !important;
					}
					.o100-sms-fields-container p.description {
						font-size: 12.5px !important;
						color: #6b7280 !important;
						margin-top: 6px !important;
						font-style: normal !important;
					}
				</style>
				<script>
					jQuery(document).ready(function($){
						// Collapsible blocks toggle
						$(".o100-block-header").click(function(){
							var $header = $(this);
							var $content = $header.parent().find(".o100-collapsible-content");
							var $arrow = $header.find(".dashicons-arrow-up-alt2, .dashicons-arrow-down-alt2");
							
							$content.slideToggle(200, function(){
								if ($content.is(":visible")) {
									$arrow.removeClass("dashicons-arrow-down-alt2").addClass("dashicons-arrow-up-alt2").css("transform", "rotate(0deg)");
								} else {
									$arrow.removeClass("dashicons-arrow-up-alt2").addClass("dashicons-arrow-down-alt2").css("transform", "rotate(-180deg)");
								}
							});
						});
						function initToggleBlocks() {
							$(".o100-toggle-block").each(function() {
								var $block = $(this);
								var toggleId = $block.attr("data-toggle-id");
								var toggleVal = $block.attr("data-toggle-val");
								var $toggle = $("#" + toggleId);
								
								if ($toggle.length === 0) {
									var $radios = $("input[name=\"" + toggleId + "\"]:radio");
									if ($radios.length > 0) $toggle = $radios;
								}
								
								if ($toggle.length) {
									var updateBlock = function() {
										var isVisible = false;
										if ($toggle.is(":radio")) {
											isVisible = $toggle.filter(":checked").val() === toggleVal;
										} else if ($toggle.is(":checkbox")) {
											isVisible = $toggle.is(":checked");
										} else {
											var currentVal = $toggle.val();
											if (currentVal !== undefined && currentVal !== null) {
												var valArray = toggleVal.toString().split(",");
												isVisible = $.inArray(currentVal.toString(), valArray) > -1;
											}
										}
										
										if (isVisible) {
											$block.slideDown(200);
										} else {
											$block.slideUp(200);
										}
									};
									$toggle.on("change", updateBlock);
									updateBlock(); // Run on load
								}
							});
						}
						setTimeout(initToggleBlocks, 10);
						
						// Sync toggle label status
						$(document).on("change", ".o100-toggle-wrap input[type=\"checkbox\"]", function() {
							var $status = $(this).siblings(".o100-toggle-status");
							if ($status.length) {
								var enabledText = $status.attr("data-enabled-text") || "Enabled";
								var disabledText = $status.attr("data-disabled-text") || "Disabled";
								if ($(this).is(":checked")) {
									$status.addClass("is-enabled").text(enabledText);
								} else {
									$status.removeClass("is-enabled").text(disabledText);
								}
							}
						});

						// Module start button
						$(document).on("click", ".o100-start-module-btn", function(e) {
							e.preventDefault();
							var targetId = $(this).data("target");
							var $checkbox = $("#" + targetId);
							$checkbox.prop("checked", true).trigger("change");
							
							$("#" + targetId + "_intro_wrap").slideUp(200);
							$("#" + targetId + "_header_wrap").slideDown(200);
							$("#" + targetId + "_content_wrap").slideDown(200);
						});
						
						// Intercept disable click
						$(document).on("click", ".o100-module-toggle", function(e) {
							var $checkbox = $(this);
							if (!$checkbox.is(":checked")) { // Attempting to uncheck
								e.preventDefault(); // Stop it from unchecking
								var moduleName = $checkbox.data("module-name");
								var targetId = $checkbox.attr("id");
								
								if (typeof window.o100Confirm === "function") {
									window.o100Confirm(
										"Disable Module",
										"Are you sure you want to disable " + moduleName + "? Turning off this switch will completely disable the " + moduleName + " feature.",
										function(confirmed) {
											if (confirmed) {
												$checkbox.prop("checked", false).trigger("change");
												$("#" + targetId + "_header_wrap").slideUp(200);
												$("#" + targetId + "_content_wrap").slideUp(200);
												$("#" + targetId + "_intro_wrap").slideDown(200);
											}
										}
									);
								} else {
									if (confirm("Are you sure you want to disable " + moduleName + "?")) {
										$checkbox.prop("checked", false).trigger("change");
										$("#" + targetId + "_header_wrap").slideUp(200);
										$("#" + targetId + "_content_wrap").slideUp(200);
										$("#" + targetId + "_intro_wrap").slideDown(200);
									}
								}
							}
						});
					});
				</script>
				<div class="o100-flat-section" style="background: #ffffff; border-bottom: 1px solid #e2e8f0; margin-bottom: 1.5rem; padding: 1.25rem 1.5rem;">
					<div style="display: flex; align-items: flex-start; gap: 16px;">
						
						<div>
							<h3 style="margin: 0 0 6px 0; font-size: 17px; font-weight: 600; color: #111827;">' . esc_html__( 'API Connection', 'order100' ) . '</h3>
							<p style="margin: 0; font-size: 13.5px; line-height: 1.6; color: #6b7280;">' . esc_html__( 'Connect your Twilio or messaging account here. This allows the system to automatically send text messages and make phone calls on your behalf.', 'order100' ) . '</p>
						</div>
					</div>
					<div style="padding: 24px 0 0 0;">';
			}
		) );

		// --- GATEWAY CONFIG FIELDS ---
		$cmb->add_field( array(
			'name'    => __( 'Service Provider', 'order100' ),
			'id'      => 'o100_sms_gateway',
			'type'    => 'select',
			'default' => 'twilio',
			'options' => array(
				'twilio'     => 'Twilio',
				'plivo'      => 'Plivo',
				'vonage'     => 'Vonage (Nexmo)',
			),
		) );

		$cmb->add_field( array(
			'name' => __( 'API Key / Account SID', 'order100' ),
			'id'   => 'o100_sms_api_key',
			'type' => 'text',
			'attributes' => array( 'type' => 'password' )
		) );

		$cmb->add_field( array(
			'name' => __( 'Auth Token / Secret', 'order100' ),
			'id'   => 'o100_sms_api_secret',
			'type' => 'text',
			'attributes' => array( 'type' => 'password' )
		) );

		$cmb->add_field( array(
			'name' => __( 'Sender Phone Number', 'order100' ),
			'id'   => 'o100_sms_sender_number',
			'type' => 'o100_phone_intl',
			'after_row' => function() { echo '</div></div></div><!-- /settings subtab -->'; }
		) );

		// --- SMS ENABLE TOGGLE & HEADER ---
		$cmb->add_field( array(
			'name' => __( 'Enable SMS Notifications', 'order100' ),
			'id'   => 'o100_sms_enable',
			'type' => 'checkbox',
			'render_row_cb' => function( $field_args, $field ) {
				$val = $field->escaped_value();
				$checked = $val === 'on' ? 'checked="checked"' : '';
				$field_name = $field->args('_name');
				if ( empty( $field_name ) ) $field_name = 'o100_notifications[o100_sms_enable]';
				
				echo '<div id="sms-templates" class="o100-notify-subtab-content o100-sms-fields-container" data-subtab="sms" style="display:none; width: 100%;">';
				
				$opts = get_option( 'o100_notifications', array() );
				$sms_on = ! empty( $opts['o100_sms_enable'] ) && $opts['o100_sms_enable'] === 'on';

				// Intro Card
				echo '<div class="o100-intro-wrap" id="o100_sms_enable_intro_wrap" style="display: '.($sms_on ? 'none' : 'flex').'; flex-direction: column; align-items: center; justify-content: center; background: #fff; padding: 60px 40px; border-radius: 12px; border: 1px solid #e2e8f0; margin: 40px auto; max-width: 600px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">';
				echo '<h2 style="font-size: 24px; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 16px;">' . esc_html__( 'SMS Module', 'order100' ) . '</h2>';
				echo '<p style="font-size: 15px; color: #475569; margin-bottom: 8px; text-align: center; line-height: 1.6;">' . esc_html__( 'Configure the text messages sent to your customers and staff during the order lifecycle.', 'order100' ) . '</p>';
				echo '<p style="font-size: 13px; color: #94a3b8; margin-bottom: 32px; text-align: center;">' . esc_html__( 'Note: You need to configure the API in the Settings tab first.', 'order100' ) . ' <a href="#" onclick="jQuery(\'.o100-notify-subtabs a[data-subtab=\\\'settings\\\']\').click(); return false;" style="color: #F59322; text-decoration: underline;">' . esc_html__( 'Go to Settings', 'order100' ) . '</a></p>';
				echo '<button type="button" class="o100-start-module-btn" data-target="o100_sms_enable" style="background: #22c55e; color: white; border: none; padding: 12px 32px; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);">' . esc_html__( 'Start Using SMS', 'order100' ) . '</button>';
				echo '</div>';

				echo '<div class="o100-flat-section" id="o100_sms_enable_header_wrap" style="display: '.($sms_on ? 'block' : 'none').'; background: #ffffff; border-bottom: 1px solid #e2e8f0; margin-bottom: 1.5rem; padding: 1.25rem 1.5rem;">';
				echo '  <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: nowrap;">';
				echo '    <div style="display: flex; align-items: flex-start; gap: 16px; flex: 1 1 0%; min-width: 0;">';
				echo '      <div style="flex: 1 1 0%; min-width: 0;">';
				echo '        <h3 style="margin: 0 0 6px 0; font-size: 17px; font-weight: 600; color: #111827;">' . esc_html__( 'Enable SMS Notifications', 'order100' ) . '</h3>';
				echo '        <p style="margin: 0; font-size: 13.5px; line-height: 1.6; color: #6b7280; white-space: normal;">' . esc_html__( 'Turn on automated text messages to keep your customers and staff updated on order progress.', 'order100' ) . '</p>';
				echo '      </div>';
				echo '    </div>';
				echo '    <div style="flex-shrink: 0;">';
				echo '      <div class="o100-toggle-wrap cmb-type-checkbox" style="display: flex; align-items: center; justify-content: flex-start; background: #f8fafc; padding: 6px 12px; border-radius: 20px; border: 1px solid #e2e8f0; white-space: nowrap; flex-shrink: 0;">';
				echo '        <input type="checkbox" name="' . esc_attr($field_name) . '" id="o100_sms_enable" value="on" class="cmb2-option o100-module-toggle" data-module-name="SMS" ' . $checked . '>';
				echo '        <span class="o100-toggle-status o100-toggle-status-text ' . ($sms_on ? 'is-enabled' : '') . '" data-enabled-text="' . esc_attr__( 'SMS Enabled', 'order100' ) . '" data-disabled-text="' . esc_attr__( 'SMS Disabled', 'order100' ) . '" style="font-size: 14px; font-weight: 600; color: #334155; margin-left: 8px;">' . ($sms_on ? esc_html__( 'SMS Enabled', 'order100' ) : esc_html__( 'SMS Disabled', 'order100' )) . '</span>';
				echo '      </div>';
				echo '    </div>';
				echo '  </div>';
				echo '</div>'; // Close header div
				
				echo '<div class="o100-toggle-block" id="o100_sms_enable_content_wrap" data-toggle-id="o100_sms_enable"' . ( $sms_on ? '' : ' style="display:none"' ) . '>';
			}
		) );

		// ═══ CATEGORY 1: Customer Order Notifications ═══
		$cmb->add_field( array(
			'id'   => 'o100_sms_title_customer_orders',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-collapsible-section" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); margin: 0 1.5rem 1.5rem 1.5rem; overflow: hidden;">';
				echo '  <div class="o100-block-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">';
				echo '    <div>';
				echo '      <div style="margin-top: 0; font-size: 15.5px; font-weight: 600; color: #111827;">' . esc_html__( 'Messages to Customers', 'order100' ) . '</div>';
				echo '      <p style="margin-top: 4px; margin-bottom: 0; color: #6b7280; font-weight: normal; font-size: 13px; line-height: 1.4;">' . esc_html__( 'Setup text messages sent to customers when their order is confirmed, ready, or out for delivery.', 'order100' ) . '</p>';
				echo '    </div>';
				echo '    <span class="dashicons dashicons-arrow-up-alt2" style="font-size: 20px; width: 20px; height: 20px; color: #9ca3af; transition: transform 0.2s; transform: rotate(180deg);"></span>';
				echo '  </div>';
				echo '  <div class="o100-collapsible-content" style="padding: 1.5rem; background: #ffffff; display: none;">';
			}
		) );

		$cmb->add_field( array(
			'name'    => __( 'Order Confirmed (Customer)', 'order100' ),
			'desc'    => __( 'Sent when the restaurant confirms the order.', 'order100' ) . '<br><code style="font-size:11px;color:#9ca3af;">{customer_name} {order_id} {order_total} {store_name} {estimated_ready}</code>',
			'id'      => 'o100_sms_tpl_order_confirmed',
			'type'    => 'textarea_small',
			'default' => 'Hi {customer_name}, your order #{order_id} has been confirmed! Estimated ready time: {estimated_ready}. Thank you for ordering from {store_name}!',
		) );
		$cmb->add_field( array(
			'name'    => __( 'Ready for Pickup (Customer)', 'order100' ),
			'desc'    => __( 'Sent when food is ready for customer pickup.', 'order100' ) . '<br><code style="font-size:11px;color:#9ca3af;">{customer_name} {order_id} {store_name} {store_address}</code>',
			'id'      => 'o100_sms_tpl_ready_pickup',
			'type'    => 'textarea_small',
			'default' => 'Hi {customer_name}, your order #{order_id} is ready for pickup at {store_name} ({store_address}). See you soon!',
		) );
		$cmb->add_field( array(
			'name'    => __( 'Out for Delivery (Customer)', 'order100' ),
			'desc'    => __( 'Sent when delivery driver leaves the restaurant.', 'order100' ) . '<br><code style="font-size:11px;color:#9ca3af;">{customer_name} {order_id} {delivery_address}</code>',
			'id'      => 'o100_sms_tpl_out_delivery',
			'type'    => 'textarea_small',
			'default' => 'Hi {customer_name}, your order #{order_id} is on its way! Our driver is heading to {delivery_address}. Track your delivery in real time.',
			'after_row' => function() { echo '</div></div>'; }
		) );

		// ═══ CATEGORY 2: Staff & Driver Notifications ═══
		$cmb->add_field( array(
			'id'   => 'o100_sms_title_staff',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-collapsible-section" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); margin: 0 1.5rem 1.5rem 1.5rem; overflow: hidden;">';
				echo '  <div class="o100-block-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">';
				echo '    <div>';
				echo '      <div style="margin-top: 0; font-size: 15.5px; font-weight: 600; color: #111827;">' . esc_html__( 'Messages to Staff & Drivers', 'order100' ) . '</div>';
				echo '      <p style="margin-top: 4px; margin-bottom: 0; color: #6b7280; font-weight: normal; font-size: 13px; line-height: 1.4;">' . esc_html__( 'Setup alerts for new orders and dispatch messages for delivery drivers.', 'order100' ) . '</p>';
				echo '    </div>';
				echo '    <span class="dashicons dashicons-arrow-up-alt2" style="font-size: 20px; width: 20px; height: 20px; color: #9ca3af; transition: transform 0.2s; transform: rotate(180deg);"></span>';
				echo '  </div>';
				echo '  <div class="o100-collapsible-content" style="padding: 1.5rem; background: #ffffff; display: none;">';
			}
		) );

		$cmb->add_field( array(
			'name'    => __( 'New Order (Admin)', 'order100' ),
			'desc'    => __( 'Sent to admin/restaurant when a new order is placed.', 'order100' ) . '<br><code style="font-size:11px;color:#9ca3af;">{order_type} {order_id} {customer_name} {order_total}</code>',
			'id'      => 'o100_sms_tpl_new_order',
			'type'    => 'textarea_small',
			'default' => 'New {order_type} order #{order_id} received! Customer: {customer_name}. Total: {order_total}. Please confirm ASAP.',
		) );
		$cmb->add_field( array(
			'name'    => __( 'Global Delivery Driver Number', 'order100' ),
			'desc'    => __( 'Default phone number for delivery driver notifications. Can be overridden per branch.', 'order100' ),
			'id'      => 'o100_sms_driver_number',
			'type' => 'o100_phone_intl',
		) );
		$cmb->add_field( array(
			'name'    => __( 'Driver Dispatch (Driver)', 'order100' ),
			'desc'    => __( 'Sent to delivery driver when assigned to an order.', 'order100' ) . '<br><code style="font-size:11px;color:#9ca3af;">{order_id} {store_name} {store_address} {delivery_address}</code>',
			'id'      => 'o100_sms_tpl_driver_dispatch',
			'type'    => 'textarea_small',
			'default' => 'New delivery assignment! Order #{order_id} from {store_name}. Pickup at: {store_address}. Deliver to: {delivery_address}.',
			'after_row' => function() { echo '</div></div>'; }
		) );

		// ═══ CATEGORY 3: Reservation Notifications ═══
		$cmb->add_field( array(
			'id'   => 'o100_sms_title_reservations',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-collapsible-section" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); margin: 0 1.5rem 1.5rem 1.5rem; overflow: hidden;">';
				echo '  <div class="o100-block-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">';
				echo '    <div>';
				echo '      <div style="margin-top: 0; font-size: 15.5px; font-weight: 600; color: #111827;">' . esc_html__( 'Table Reservation Messages', 'order100' ) . '</div>';
				echo '      <p style="margin-top: 4px; margin-bottom: 0; color: #6b7280; font-weight: normal; font-size: 13px; line-height: 1.4;">' . esc_html__( 'Setup text confirmations and reminders for customers who booked a table.', 'order100' ) . '</p>';
				echo '    </div>';
				echo '    <span class="dashicons dashicons-arrow-up-alt2" style="font-size: 20px; width: 20px; height: 20px; color: #9ca3af; transition: transform 0.2s; transform: rotate(180deg);"></span>';
				echo '  </div>';
				echo '  <div class="o100-collapsible-content" style="padding: 1.5rem; background: #ffffff; display: none;">';
			}
		) );

		$cmb->add_field( array(
			'name'    => __( 'Reservation Confirmed (Customer)', 'order100' ),
			'desc'    => __( 'Sent when reservation is approved by admin.', 'order100' ) . '<br><code style="font-size:11px;color:#9ca3af;">{customer_name} {store_name} {reservation_date} {reservation_time} {party_size}</code>',
			'id'      => 'o100_sms_tpl_reservation',
			'type'    => 'textarea_small',
			'default' => 'Hi {customer_name}, your reservation at {store_name} is confirmed! Date: {reservation_date}, Time: {reservation_time}, Party size: {party_size}. See you there!',
		) );
		$cmb->add_field( array(
			'name'       => __( 'Advance Reminder Time (Minutes)', 'order100' ),
			'desc'       => __( 'e.g. 120 for 2 hours before.', 'order100' ),
			'id'         => 'o100_sms_reserve_remind_time',
			'type'       => 'text_small',
			'attributes' => array( 'type' => 'number', 'min' => 0 ),
			'default'    => '120',
		) );
		$cmb->add_field( array(
			'name'    => __( 'Reservation Reminder (Customer)', 'order100' ),
			'desc'    => __( 'Sent X minutes before the reservation time.', 'order100' ) . '<br><code style="font-size:11px;color:#9ca3af;">{customer_name} {store_name} {reservation_time} {party_size}</code>',
			'id'      => 'o100_sms_tpl_reserve_remind',
			'type'    => 'textarea_small',
			'default' => 'Reminder: Your reservation at {store_name} is coming up today at {reservation_time}. Party of {party_size}. We look forward to seeing you!',
			'after_row' => function() { echo '</div></div>'; }
		) );

		// ═══ CATEGORY 4: Loyalty & Marketing ═══
		$cmb->add_field( array(
			'id'   => 'o100_sms_title_loyalty',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-collapsible-section" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); margin: 0 1.5rem 1.5rem 1.5rem; overflow: hidden;">';
				echo '  <div class="o100-block-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">';
				echo '    <div>';
				echo '      <div style="margin-top: 0; font-size: 15.5px; font-weight: 600; color: #111827;">' . esc_html__( 'Marketing & Loyalty Messages', 'order100' ) . '</div>';
				echo '      <p style="margin-top: 4px; margin-bottom: 0; color: #6b7280; font-weight: normal; font-size: 13px; line-height: 1.4;">' . esc_html__( 'Setup automated texts to reward loyal customers and send birthday coupons.', 'order100' ) . '</p>';
				echo '    </div>';
				echo '    <span class="dashicons dashicons-arrow-up-alt2" style="font-size: 20px; width: 20px; height: 20px; color: #9ca3af; transition: transform 0.2s; transform: rotate(180deg);"></span>';
				echo '  </div>';
				echo '  <div class="o100-collapsible-content" style="padding: 1.5rem; background: #ffffff; display: none;">';
			}
		) );

		$cmb->add_field( array(
			'name'    => __( 'Loyalty Points Updated (Customer)', 'order100' ),
			'desc'    => __( 'Sent when points are earned.', 'order100' ) . '<br><code style="font-size:11px;color:#9ca3af;">{customer_name} {points_earned} {points_balance} {store_name}</code>',
			'id'      => 'o100_sms_tpl_loyalty_update',
			'type'    => 'textarea_small',
			'default' => 'Hi {customer_name}! You earned {points_earned} points on your last order. Your balance is now {points_balance} points. Keep earning at {store_name}!',
		) );
		$cmb->add_field( array(
			'name'    => __( 'Birthday Wishes (Customer)', 'order100' ),
			'desc'    => __( 'Sent on customer birthday.', 'order100' ) . '<br><code style="font-size:11px;color:#9ca3af;">{customer_name} {store_name} {coupon_code} {coupon_expiry}</code>',
			'id'      => 'o100_sms_tpl_birthday',
			'type'    => 'textarea_small',
			'default' => 'Happy Birthday, {customer_name}! 🎂 {store_name} has a special gift for you. Use code {coupon_code} for a treat on us. Valid until {coupon_expiry}.',
		) );
		$cmb->add_field( array(
			'name'    => __( 'Coupon Received (Customer)', 'order100' ),
			'desc'    => __( 'Sent when a coupon is issued.', 'order100' ) . '<br><code style="font-size:11px;color:#9ca3af;">{customer_name} {store_name} {coupon_code} {coupon_expiry}</code>',
			'id'      => 'o100_sms_tpl_coupon_issued',
			'type'    => 'textarea_small',
			'default' => 'Hi {customer_name}! You have a new coupon from {store_name}: {coupon_code}. Use it before {coupon_expiry} to save on your next order!',
		) );
		$cmb->add_field( array(
			'name'    => __( 'Coupon Expiry Warning (Customer)', 'order100' ),
			'desc'    => __( 'Sent before a coupon expires.', 'order100' ) . '<br><code style="font-size:11px;color:#9ca3af;">{customer_name} {store_name} {coupon_code} {coupon_expiry}</code>',
			'id'      => 'o100_sms_tpl_coupon_expire',
			'type'    => 'textarea_small',
			'default' => 'Hi {customer_name}, your coupon {coupon_code} at {store_name} expires on {coupon_expiry}. Use it now before it is gone!',
		) );
		$cmb->add_field( array(
			'name'    => __( 'Points Expiry Warning (Customer)', 'order100' ),
			'desc'    => __( 'Sent before points expire.', 'order100' ) . '<br><code style="font-size:11px;color:#9ca3af;">{customer_name} {store_name} {points_amount} {points_expiry}</code>',
			'id'      => 'o100_sms_tpl_points_expire',
			'type'    => 'textarea_small',
			'default' => 'Hi {customer_name}, your {points_amount} points at {store_name} will expire on {points_expiry}. Use them now before they are gone!',
			'after_row' => function() {
				echo '</div></div></div></div>'; // Close Category 4 content + section, close o100-toggle-block, close sms-templates pane
			},
		) );

		// ═══════════════════════════════════════════════════════════════
		// VOICE CALLS TAB
		// ═══════════════════════════════════════════════════════════════
		// --- VOICE ENABLE TOGGLE & HEADER ---
		$cmb->add_field( array(
			'name' => __( 'Enable Voice Alerts', 'order100' ),
			'id'   => 'o100_voice_enable',
			'type' => 'checkbox',
			'render_row_cb' => function( $field_args, $field ) {
				$val = $field->escaped_value();
				$checked = $val === 'on' ? 'checked="checked"' : '';
				$field_name = $field->args('_name');
				if ( empty( $field_name ) ) $field_name = 'o100_notifications[o100_voice_enable]';
				
				echo '<div id="voice-config" class="o100-notify-subtab-content o100-sms-fields-container" data-subtab="voice" style="display:none; width: 100%;">';
				
				$opts = get_option( 'o100_notifications', array() );
				$voice_on = ! empty( $opts['o100_voice_enable'] ) && $opts['o100_voice_enable'] === 'on';

				// Intro Card
				echo '<div class="o100-intro-wrap" id="o100_voice_enable_intro_wrap" style="display: '.($voice_on ? 'none' : 'flex').'; flex-direction: column; align-items: center; justify-content: center; background: #fff; padding: 60px 40px; border-radius: 12px; border: 1px solid #e2e8f0; margin: 40px auto; max-width: 600px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">';
				echo '<h2 style="font-size: 24px; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 16px;">' . esc_html__( 'Voice Call Module', 'order100' ) . '</h2>';
				echo '<p style="font-size: 15px; color: #475569; margin-bottom: 8px; text-align: center; line-height: 1.6;">' . esc_html__( 'Configure voice call alerts to be placed when incoming orders are not confirmed by staff within the specified time limit.', 'order100' ) . '</p>';
				echo '<p style="font-size: 13px; color: #94a3b8; margin-bottom: 32px; text-align: center;">' . esc_html__( 'Note: You need to configure the API in the Settings tab first.', 'order100' ) . ' <a href="#" onclick="jQuery(\'.o100-notify-subtabs a[data-subtab=\\\'settings\\\']\').click(); return false;" style="color: #F59322; text-decoration: underline;">' . esc_html__( 'Go to Settings', 'order100' ) . '</a></p>';
				echo '<button type="button" class="o100-start-module-btn" data-target="o100_voice_enable" style="background: #22c55e; color: white; border: none; padding: 12px 32px; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);">' . esc_html__( 'Start Using Voice Call', 'order100' ) . '</button>';
				echo '</div>';

				echo '<div class="o100-flat-section" id="o100_voice_enable_header_wrap" style="display: '.($voice_on ? 'block' : 'none').'; background: #ffffff; border-bottom: 1px solid #e2e8f0; margin-bottom: 1.5rem; padding: 1.25rem 1.5rem;">';
				echo '  <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: nowrap;">';
				echo '    <div style="display: flex; align-items: flex-start; gap: 16px; flex: 1 1 0%; min-width: 0;">';
				echo '      <div style="flex: 1 1 0%; min-width: 0;">';
				echo '        <h3 style="margin: 0 0 6px 0; font-size: 17px; font-weight: 600; color: #111827;">' . esc_html__( 'Enable Voice Call Alerts', 'order100' ) . '</h3>';
				echo '        <p style="margin: 0; font-size: 13.5px; line-height: 1.6; color: #6b7280; white-space: normal;">' . esc_html__( 'Turn on automated phone calls to notify your staff when they forget to accept a new order in time.', 'order100' ) . '</p>';
				echo '      </div>';
				echo '    </div>';
				echo '    <div style="flex-shrink: 0;">';
				echo '      <div class="o100-toggle-wrap cmb-type-checkbox" style="display: flex; align-items: center; justify-content: flex-start; background: #f8fafc; padding: 6px 12px; border-radius: 20px; border: 1px solid #e2e8f0; white-space: nowrap; flex-shrink: 0;">';
				echo '        <input type="checkbox" name="' . esc_attr($field_name) . '" id="o100_voice_enable" value="on" class="cmb2-option o100-module-toggle" data-module-name="Voice Call" ' . $checked . '>';
				echo '        <span class="o100-toggle-status o100-toggle-status-text ' . ($voice_on ? 'is-enabled' : '') . '" data-enabled-text="' . esc_attr__( 'Call Enabled', 'order100' ) . '" data-disabled-text="' . esc_attr__( 'Call Disabled', 'order100' ) . '" style="font-size: 14px; font-weight: 600; color: #334155; margin-left: 8px;">' . ($voice_on ? esc_html__( 'Call Enabled', 'order100' ) : esc_html__( 'Call Disabled', 'order100' )) . '</span>';
				echo '      </div>';
				echo '    </div>';
				echo '  </div>';
				echo '</div>'; // Close module header card
				
				echo '<div class="o100-toggle-block" id="o100_voice_enable_content_wrap" data-toggle-id="o100_voice_enable"' . ( $voice_on ? '' : ' style="display:none"' ) . '>';
			}
		) );

		$is_multibranch = get_option('o100_locations_status') === 'on';
		$locations = get_posts(array( 'post_type' => 'o100_location', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ));
		$has_branches = $is_multibranch && count($locations) > 0;

		// ═══ VOICE CATEGORY 1: Receiver Setup ═══
		$cmb->add_field( array(
			'id'   => 'o100_voice_title_receivers',
			'type' => 'title',
			'render_row_cb' => function() {
				$is_multibranch = get_option('o100_locations_status') === 'on';
				$locations = get_posts(array( 'post_type' => 'o100_location', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ));
				$has_branches = $is_multibranch && count($locations) > 0;
				
				echo '<div class="o100-collapsible-section" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); margin: 0 1.5rem 1.5rem 1.5rem; overflow: hidden;">';
				echo '  <div class="o100-block-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">';
				echo '    <div>';
				echo '      <div style="margin-top: 0; font-size: 15.5px; font-weight: 600; color: #111827;">' . esc_html__( 'Who Receives The Call?', 'order100' ) . '</div>';
				echo '      <p style="margin-top: 4px; margin-bottom: 0; color: #6b7280; font-weight: normal; font-size: 13px; line-height: 1.4;">' . esc_html__( 'Select which phone numbers will be called when an order is missed.', 'order100' ) . '</p>';
				echo '    </div>';
				echo '    <span class="dashicons dashicons-arrow-up-alt2" style="font-size: 20px; width: 20px; height: 20px; color: #9ca3af; transition: transform 0.2s; transform: rotate(180deg);"></span>';
				echo '  </div>';
				echo '  <div class="o100-collapsible-content" style="padding: 1.5rem; background: #ffffff; display: none;">';
			}
		) );

		$cmb->add_field( array(
			'name'    => __( 'Which Number Should We Call?', 'order100' ),
			'id'   => 'o100_voice_phone_source',
			'type' => 'radio',
			'default' => 'default',
			'options' => array(
				'default' => $has_branches ? __( 'Call the branch\'s main phone number', 'order100' ) : __( 'Call the restaurant\'s main phone number', 'order100' ),
				'custom'  => $has_branches ? __( 'Specify custom phone numbers for each branch', 'order100' ) : __( 'Specify a different phone number', 'order100' ),
			),
			'row_classes' => 'o100-radio-cards-row',
			'after_row' => function() { echo '<div class="o100-toggle-block o100-voice-custom-phones-wrap" data-toggle-id="o100_voice_phone_source" data-toggle-val="custom">'; }
		) );

		if ( $has_branches ) {
			// Dynamically render custom phone inputs for each location
			foreach( $locations as $loc ) {
				$cmb->add_field( array(
					'name'       => sprintf( __( '%s Phone', 'order100' ), $loc->post_title ),
					'desc'       => __( 'Enter phone number including country code (e.g. +1234567890)', 'order100' ),
					'id'         => 'o100_voice_phone_loc_' . $loc->ID,
					'type'       => 'o100_phone_intl',
				) );
			}
		} else {
			// Single custom phone input
			$cmb->add_field( array(
				'name'       => __( 'Custom Phone Number', 'order100' ),
				'desc'       => __( 'Enter phone number including country code (e.g. +1234567890)', 'order100' ),
				'id'         => 'o100_voice_phone_custom_single',
				'type'       => 'o100_phone_intl',
			) );
		}

		$cmb->add_field( array(
			'id'   => 'o100_voice_phone_source_custom_close',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div></div>'; } // Close custom toggle + collapsible content + section
		) );

		// ═══ VOICE CATEGORY 2: Trigger Rules & Retry ═══
		$cmb->add_field( array(
			'id'   => 'o100_voice_title_trigger',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-collapsible-section" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); margin: 0 1.5rem 1.5rem 1.5rem; overflow: hidden;">';
				echo '  <div class="o100-block-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">';
				echo '    <div>';
				echo '      <div style="margin-top: 0; font-size: 15.5px; font-weight: 600; color: #111827;">' . esc_html__( 'When to Call & Retries', 'order100' ) . '</div>';
				echo '      <p style="margin-top: 4px; margin-bottom: 0; color: #6b7280; font-weight: normal; font-size: 13px; line-height: 1.4;">' . esc_html__( 'Set how long the system waits before making a call, and how many times it should try.', 'order100' ) . '</p>';
				echo '    </div>';
				echo '    <span class="dashicons dashicons-arrow-up-alt2" style="font-size: 20px; width: 20px; height: 20px; color: #9ca3af; transition: transform 0.2s; transform: rotate(180deg);"></span>';
				echo '  </div>';
				echo '  <div class="o100-collapsible-content" style="padding: 1.5rem; background: #ffffff; display: none;">';
			}
		) );

		$cmb->add_field( array(
			'name'       => __( 'Wait Time Before Calling', 'order100' ) . '<span class="cmb2-metabox-description" style="display:block; margin-top:6px; font-weight:normal;">' . __( 'The call will be made if the order isn\'t accepted within this many minutes.', 'order100' ) . '</span>',
			'id'   => 'o100_voice_delay_mins',
			'type' => 'text_small',
			'default'    => '3',
			'after'      => '<span class="o100-input-suffix">minutes</span>',
			'attributes' => array( 'type' => 'number', 'min' => 1 ),
			'row_classes' => 'o100-horizontal-card-row',
		) );

		$cmb->add_field( array(
			'name'    => __( 'If Nobody Answers', 'order100' ) . '<span class="cmb2-metabox-description" style="display:block; margin-top:6px; font-weight:normal;">' . __( 'Choose how many times we should try calling again if the first call is missed.', 'order100' ) . '</span>',
			'id'   => 'o100_voice_retry_count',
			'type' => 'select',
			'default' => '0',
			'options' => array(
				'0' => 'Do not retry',
				'1' => 'Retry 1 time',
				'2' => 'Retry 2 times',
				'3' => 'Retry 3 times',
			),
			'before_row' => function() { echo '<div class="o100-horizontal-card-group">'; },
			'row_classes' => 'o100-horizontal-card-row',
		) );

		$cmb->add_field( array(
			'name'       => __( 'Retry Interval', 'order100' ) . '<span class="cmb2-metabox-description" style="display:block; margin-top:6px; font-weight:normal;">' . __( 'Wait this many minutes between each retry call.', 'order100' ) . '</span>',
			'id'         => 'o100_voice_retry_delay',
			'type'       => 'text_small',
			'default'    => '2',
			'after'      => '<span class="o100-input-suffix">minutes</span>',
			'attributes' => array( 
				'type' => 'number', 
				'min'  => 1
			),
			'row_classes' => 'o100-horizontal-card-row o100-toggle-block',
			'before_row' => function() {
				echo '<div class="o100-toggle-block" data-toggle-id="o100_voice_retry_count" data-toggle-val="1,2,3">';
			},
			'after_row' => function() { 
				echo '</div></div></div></div>'; // closes toggle block, card group, collapsible content, collapsible section
			}
		) );

		// ═══ VOICE CATEGORY 3: Message Content ═══
		$cmb->add_field( array(
			'id'   => 'o100_voice_title_content',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-collapsible-section" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); margin: 0 1.5rem 1.5rem 1.5rem; overflow: hidden;">';
				echo '  <div class="o100-block-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">';
				echo '    <div>';
				echo '      <div style="margin-top: 0; font-size: 15.5px; font-weight: 600; color: #111827;">' . esc_html__( 'What the Call Says', 'order100' ) . '</div>';
				echo '      <p style="margin-top: 4px; margin-bottom: 0; color: #6b7280; font-weight: normal; font-size: 13px; line-height: 1.4;">' . esc_html__( 'Configure the automated voice message spoken during the call and select the voice language.', 'order100' ) . '</p>';
				echo '    </div>';
				echo '    <span class="dashicons dashicons-arrow-up-alt2" style="font-size: 20px; width: 20px; height: 20px; color: #9ca3af; transition: transform 0.2s; transform: rotate(180deg);"></span>';
				echo '  </div>';
				echo '  <div class="o100-collapsible-content" style="padding: 1.5rem; background: #ffffff; display: none;">';
			}
		) );

		$cmb->add_field( array(
			'name'    => __( 'Voice Language', 'order100' ),
			'id'   => 'o100_voice_language',
			'type' => 'select',
			'default' => 'en-US',
			'options' => array(
				'en-US' => 'English (US)',
				'en-GB' => 'English (UK)',
				'zh-CN' => 'Chinese (Mandarin)',
				'fr-FR' => 'French (France)',
				'es-ES' => 'Spanish (Spain)',
			),
		) );

		$cmb->add_field( array(
			'name'    => __( 'Message Content', 'order100' ),
			'desc'    => __( 'Variables allowed: {order_id}, {order_type}, {store_name}. Example: You have a new {order_type} order. Order ID is {order_id}. Please accept it immediately.', 'order100' ),
			'id'   => 'o100_voice_tpl_content',
			'type' => 'textarea',
			'default' => 'You have a new {order_type} order. Order ID is {order_id}. Please accept it immediately.',
			'after_row' => function() { echo '</div></div>'; }
		) );



		$cmb->add_field( array(
			'id'   => 'o100_voice_enable_close',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div>'; } // Close Voice toggle block
		) );

		$cmb->add_field( array(
			'id'   => 'o100_voice_pane_close',
			'type' => 'title',
			'render_row_cb' => function( $field_args, $field ) {
				echo '</div>';
				$this->render_notification_tab_end( $field_args, $field );
			}
		) );


	}

	public function render_sms_card_start() {
		echo '<div class="o100-notify-section" style="padding: 24px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 24px;">';
		echo '<h3 class="o100-notify-section-title" style="margin-top: 0;"><span class="dashicons dashicons-admin-generic"></span> ' . esc_html__( 'Gateway Settings', 'order100' ) . '</h3>';
	}

	public function render_sms_card_end() {
		echo '</div>';
	}

	public function render_sms_templates_start() {
		echo '<div class="o100-notify-section" style="padding: 24px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 24px;">';
		echo '<h3 class="o100-notify-section-title" style="margin-top: 0;"><span class="dashicons dashicons-testimonial"></span> ' . esc_html__( 'SMS Templates', 'order100' ) . '</h3>';
		echo '<p class="o100-notify-section-desc" style="margin-bottom: 16px;">' . esc_html__( 'Configure the message content for each stage. Leave a template empty to disable SMS for that stage.', 'order100' ) . '</p>';
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
			'loyalty'      => '<span class="dashicons dashicons-star-filled"></span> <span class="o100-tab-text">' . esc_html__( 'Loyalty & Referral', 'order100' ) . '</span>',
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
	public function render_notification_tab_start( $field_args, $field ) {
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
				'icon'      => '<span class="dashicons dashicons-bell"></span>',
			),
			array(
				'id'          => 'customer_processing_order',
				'label'       => __( 'Order Confirmed', 'order100' ),
				'desc'        => __( 'Restaurant confirms on App + selects prep time → Customer notified', 'order100' ),
				'recipient'   => __( 'Customer', 'order100' ),
				'trigger'     => 'app:order-confirm',
				'icon'        => '<span class="dashicons dashicons-clipboard"></span>',
			),
			array(
				'id'          => 'o100_order_ready',
				'label'       => __( 'Ready for Pickup', 'order100' ),
				'desc'        => __( 'Food ready (Pickup) → Customer notified to pick up', 'order100' ),
				'recipient'   => __( 'Customer', 'order100' ),
				'trigger'     => 'app:order-ready',
				'icon'        => '<span class="dashicons dashicons-store"></span>',
			),
			array(
				'id'          => 'o100_out_for_delivery',
				'label'       => __( 'Out for Delivery', 'order100' ),
				'desc'        => __( 'Food ready (Delivery) → Customer notified of dispatch', 'order100' ),
				'recipient'   => __( 'Customer', 'order100' ),
				'trigger'     => 'app:out-for-delivery',
				'icon'        => '<span class="dashicons dashicons-car"></span>',
			),
			array(
				'id'          => 'o100_driver_dispatch',
				'label'       => __( 'Driver Dispatch', 'order100' ),
				'desc'        => __( 'Order ready → Driver notified to pick up delivery', 'order100' ),
				'recipient'   => __( 'Driver', 'order100' ),
				'trigger'     => 'app:driver-dispatch',
				'icon'        => '<span class="dashicons dashicons-location"></span>',
			),
			array(
				'id'        => 'customer_completed_order',
				'label'     => __( 'Order Completed', 'order100' ),
				'desc'      => __( 'Pickup/delivery done → Thank you + review invite + rewards', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:completed',
				'icon'      => '<span class="dashicons dashicons-yes-alt"></span>',
			),
			array(
				'id'        => 'customer_on_hold_order',
				'label'     => __( 'On-Hold Order', 'order100' ),
				'desc'      => __( 'Awaiting payment confirmation', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:on-hold',
				'icon'      => '<span class="dashicons dashicons-clock"></span>',
			),
			array(
				'id'        => 'cancelled_order',
				'label'     => __( 'Cancelled Order', 'order100' ),
				'desc'      => __( 'Order cancelled by customer or restaurant', 'order100' ),
				'recipient' => __( 'Admin', 'order100' ),
				'trigger'   => 'wc:cancelled',
				'icon'      => '<span class="dashicons dashicons-warning"></span>',
			),
			array(
				'id'        => 'customer_refunded_order',
				'label'     => __( 'Refunded Order', 'order100' ),
				'desc'      => __( 'Refund processed → Customer notified', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:refunded',
				'icon'      => '<span class="dashicons dashicons-money-alt"></span>',
			),
			array(
				'id'        => 'customer_note',
				'label'     => __( 'Customer Note', 'order100' ),
				'desc'      => __( 'Restaurant sends a note to customer', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:customer_note',
				'icon'      => '<span class="dashicons dashicons-testimonial"></span>',
			),
			array(
				'id'        => 'customer_invoice',
				'label'     => __( 'Customer Invoice', 'order100' ),
				'desc'      => __( 'Invoice with payment link', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:customer_invoice',
				'icon'      => '<span class="dashicons dashicons-media-document"></span>',
			),
			array(
				'id'        => 'failed_order',
				'label'     => __( 'Failed Order', 'order100' ),
				'desc'      => __( 'Payment failed → Admin notified', 'order100' ),
				'recipient' => __( 'Admin', 'order100' ),
				'trigger'   => 'wc:failed',
				'icon'      => '<span class="dashicons dashicons-dismiss"></span>',
			),
			array(
				'id'        => 'customer_failed_order',
				'label'     => __( 'Failed Order (Customer)', 'order100' ),
				'desc'      => __( 'Payment failed → Customer notified', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:failed',
				'icon'      => '<span class="dashicons dashicons-dismiss"></span>',
			),
			array(
				'id'        => 'customer_cancelled_order',
				'label'     => __( 'Cancelled Order (Customer)', 'order100' ),
				'desc'      => __( 'Order cancelled → Customer notified', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:cancelled',
				'icon'      => '<span class="dashicons dashicons-warning"></span>',
			),
			array(
				'id'        => 'customer_reset_password',
				'label'     => __( 'Reset Password', 'order100' ),
				'desc'      => __( 'Password reset link', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:reset_password',
				'icon'      => '<span class="dashicons dashicons-lock"></span>',
			),
			array(
				'id'        => 'customer_new_account',
				'label'     => __( 'New Account', 'order100' ),
				'desc'      => __( 'Welcome email for new registrations', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'wc:new_account',
				'icon'      => '<span class="dashicons dashicons-admin-users"></span>',
			),
		);

		// Reservation email templates
		$reservation_templates = array(
			array(
				'id'        => 'o100_reservation_new',
				'label'     => __( 'New Reservation', 'order100' ),
				'desc'      => __( 'Customer submits reservation → Admin notified', 'order100' ),
				'recipient' => __( 'Admin', 'order100' ),
				'trigger'   => 'o100:reservation_new',
				'icon'      => '<span class="dashicons dashicons-calendar-alt"></span>',
			),
			array(
				'id'        => 'o100_reservation_confirmed',
				'label'     => __( 'Reservation Confirmed', 'order100' ),
				'desc'      => __( 'Admin approves reservation → Customer notified', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'o100:reservation_confirmed',
				'icon'      => '<span class="dashicons dashicons-yes-alt"></span>',
			),
			array(
				'id'        => 'o100_reservation_rejected',
				'label'     => __( 'Reservation Rejected', 'order100' ),
				'desc'      => __( 'Admin rejects reservation → Customer notified', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'o100:reservation_rejected',
				'icon'      => '<span class="dashicons dashicons-dismiss"></span>',
			),
			array(
				'id'        => 'o100_reservation_reminder',
				'label'     => __( 'Reservation Reminder', 'order100' ),
				'desc'      => __( 'Reminder sent X hours before dining time', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'trigger'   => 'o100:reservation_reminder',
				'icon'      => '<span class="dashicons dashicons-clock"></span>',
			),
		);

		// Loyalty email templates
		$loyalty_templates = array(
			array(
				'id'        => 'o100_birthday',
				'label'     => __( 'Birthday Greeting', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'icon'      => '<span class="dashicons dashicons-awards"></span>',
			),
			array(
				'id'        => 'o100_points_update',
				'label'     => __( 'Points Update', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'icon'      => '<span class="dashicons dashicons-star-filled"></span>',
			),
			array(
				'id'        => 'o100_tier_upgrade',
				'label'     => __( 'Tier Upgrade', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'icon'      => '<span class="dashicons dashicons-insert-after"></span>',
			),
			array(
				'id'        => 'o100_coupon_issued',
				'label'     => __( 'Coupon Issued', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'icon'      => '<span class="dashicons dashicons-tickets-alt"></span>',
			),
			array(
				'id'        => 'o100_points_expiring',
				'label'     => __( 'Points Expiring', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'icon'      => '<span class="dashicons dashicons-chart-bar"></span>',
			),
			array(
				'id'        => 'o100_win_back',
				'label'     => __( 'Welcome Back', 'order100' ),
				'recipient' => __( 'Customer', 'order100' ),
				'icon'      => '<span class="dashicons dashicons-update"></span>',
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
		<div class="cmb-row o100-tab-notification o100-notification-wrap" style="border:none; margin:0;">
			
			<style>
				/* Force the wrapper to take 2rem padding exactly like Customer module */
				.o100-notification-wrap { padding: 0 2rem 2rem 2rem !important; display: block !important; box-sizing: border-box !important; }

				.o100-notifications-page-header a { text-decoration: none; box-shadow: none !important; outline: none !important; }
				.o100-notifications-page-header a:focus { box-shadow: none !important; outline: none !important; }
				
				/* Override o100-admin.css pill design */
				.o100-notify-subtabs { background: transparent !important; border-bottom: none !important; }
				.o100-notify-subtabs a { background: transparent !important; border-bottom: 2px solid transparent !important; border-radius: 0 !important; margin-bottom: -1px !important; }
				
				.o100-notify-subtabs a.o100-subtab-active { border-color: #F59322 !important; color: #F59322 !important; font-weight: 500 !important; background: transparent !important; box-shadow: none !important; }
				.o100-notify-subtabs a:not(.o100-subtab-active) { border-color: transparent !important; color: #64748b !important; font-weight: 500 !important; background: transparent !important; box-shadow: none !important; }
				.o100-notify-subtabs a:not(.o100-subtab-active):hover { color: #0f172a !important; border-color: #cbd5e1 !important; }
				.o100-notify-subtab-content { padding: 0 !important; }
				.o100-notify-subtab-content[data-subtab="email"] { padding: 0 !important; margin: 0 !important; background: transparent !important; }
				
				#o100_notifications > p.submit, .cmb2-options-page form.cmb-form > p.submit,
				input[name="submit-cmb"]:not(#o100-inner-save-btn) {
					position: absolute !important;
					width: 1px !important;
					height: 1px !important;
					padding: 0 !important;
					margin: -1px !important;
					overflow: hidden !important;
					clip: rect(0, 0, 0, 0) !important;
					border: 0 !important;
					opacity: 0 !important;
					pointer-events: none !important;
				}
				
								/* Global subtab content wrapper styling to match Customer module */
				.o100-notify-subtab-content { 
					background: #f8fafc !important; 
					border: 1px solid #e2e8f0 !important; 
					border-radius: 0.75rem !important; 
					box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05) !important; 
					overflow: hidden !important; 
					padding-bottom: 1.5rem !important; 
				}
				.o100-notify-subtab-content[data-subtab="email"] { 
					background: transparent !important; 
					border: none !important; 
					box-shadow: none !important; 
					padding-bottom: 0 !important; 
				}
				.o100-notify-subtab-content[data-subtab="reports"] { 
					background: transparent !important; 
					border: none !important; 
					box-shadow: none !important; 
					padding: 0 !important; 
					overflow: visible !important;
					margin-top: 0 !important;
				}

				/* Fix huge gap above header */
				.cmb2-id-notification-management-title { display: none !important; }

				/* Custom top-save button styling that respects o100-save-disabled */
				#o100-inner-save-btn {
					padding: 0 16px !important;
					border-radius: 6px !important;
					font-size: 14px !important;
					font-weight: 500 !important;
					transition: background-color 0.15s ease, border-color 0.15s ease !important;
					display: inline-flex !important;
					align-items: center !important;
					justify-content: center !important;
					height: 36px !important;
					box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
				}
				#o100-inner-save-btn:not(.o100-save-disabled) {
					background: #F59322 !important;
					color: #fff !important;
					border: 1px solid transparent !important;
					cursor: pointer !important;
				}
				#o100-inner-save-btn:not(.o100-save-disabled):hover {
					background: #F59322 !important;
				}
			</style>

			<!-- UNIFIED HEADER -->
			<div class="o100-notifications-page-header" style="margin-bottom: 2rem;">
				<div>

					<script>
					// ═══ Hash Guard: protect React HashRouter from foreign hash values ═══
					// React email app uses HashRouter: valid hashes are "#/", "#/editor/xxx", etc.
					// Any hash not starting with "#/" will cause "No routes matched" → blank page.
					// This guard runs synchronously BEFORE React mounts to sanitize the hash.
					(function() {
						var h = location.hash;
						if (h && h.indexOf('#/') !== 0) {
							history.replaceState(null, '', location.pathname + location.search);
						}
					})();
					</script>
					<div style="margin-bottom: 1.5rem; padding-top: 2rem;">
						<div>
							<h1 style="font-size: 1.5rem !important; font-weight: 700 !important; color: #0f172a !important; margin: 0; padding-bottom: 0.25rem;"><?php esc_html_e( 'Notifications', 'order100' ); ?></h1>
							<p style="font-size: 0.875rem; color: #64748b; margin: 0; margin-top: 0.25rem;"><?php esc_html_e( 'Manage automated emails and SMS messaging.', 'order100' ); ?></p>
						</div>
					</div>
				</div>
				
				<div style="border-bottom: 1px solid #d1d5db;">
					<div style="display: flex; justify-content: space-between; align-items: flex-end;">
						<nav class="o100-notify-subtabs" style="display: flex; gap: 2rem;">
							<a href="#" data-subtab="email" class="o100-subtab-active" style="padding: 1rem 0.25rem; font-size: 0.875rem; display: flex; align-items: center; transition: color 0.15s ease, border-color 0.15s ease;">
								<span class="dashicons dashicons-email-alt" style="margin-right: 0.5rem;"></span> <?php esc_html_e( 'Email', 'order100' ); ?>
							</a>
							<a href="#" data-subtab="sms" style="padding: 1rem 0.25rem; font-size: 0.875rem; display: flex; align-items: center; transition: color 0.15s ease, border-color 0.15s ease;">
								<span class="dashicons dashicons-smartphone" style="margin-right: 0.5rem;"></span> <?php esc_html_e( 'SMS', 'order100' ); ?>
							</a>
							<a href="#" data-subtab="voice" style="padding: 1rem 0.25rem; font-size: 0.875rem; display: flex; align-items: center; transition: color 0.15s ease, border-color 0.15s ease;">
								<span class="dashicons dashicons-phone" style="margin-right: 0.5rem;"></span> <?php esc_html_e( 'Call Reminder', 'order100' ); ?>
							</a>
							<a href="#" data-subtab="settings" style="padding: 1rem 0.25rem; font-size: 0.875rem; display: flex; align-items: center; transition: color 0.15s ease, border-color 0.15s ease;">
								<span class="dashicons dashicons-admin-network" style="margin-right: 0.5rem;"></span> <?php esc_html_e( 'Settings', 'order100' ); ?>
							</a>
							<a href="#" data-subtab="reports" style="padding: 1rem 0.25rem; font-size: 0.875rem; display: flex; align-items: center; transition: color 0.15s ease, border-color 0.15s ease;">
								<span class="dashicons dashicons-chart-bar" style="margin-right: 0.5rem;"></span> <?php esc_html_e( 'Reports', 'order100' ); ?>
							</a>
							<?php if ( function_exists('O100_License') && ! O100_License()->is_premium() ) : ?>
							<a href="#" data-subtab="email-designer" class="o100-pro-fake-tab" style="padding: 1rem 0.25rem; font-size: 0.875rem; display: flex; align-items: center; transition: color 0.15s ease, border-color 0.15s ease;">
								<span class="dashicons dashicons-admin-customizer" style="margin-right: 0.5rem;"></span> <?php esc_html_e( 'Email Designer', 'order100' ); ?> <?php echo O100_License()->get_pro_badge('Visual Email Editor'); ?>
							</a>
							<a href="#" data-subtab="printer-layouts" class="o100-pro-fake-tab" style="padding: 1rem 0.25rem; font-size: 0.875rem; display: flex; align-items: center; transition: color 0.15s ease, border-color 0.15s ease;">
								<span class="dashicons dashicons-media-text" style="margin-right: 0.5rem;"></span> <?php esc_html_e( 'Printer Layouts', 'order100' ); ?> <?php echo O100_License()->get_pro_badge('Custom Receipt Layouts'); ?>
							</a>
							<script>
							document.addEventListener("DOMContentLoaded", function() {
								var tabs = document.querySelectorAll('.o100-pro-fake-tab');
								tabs.forEach(function(tab) {
									tab.addEventListener('click', function(e) {
										e.preventDefault();
										var type = this.getAttribute('data-subtab');
										if (type === 'email-designer') {
											if(typeof o100ShowProModal === 'function') {
												o100ShowProModal('Drag & Drop Email Customizer', 'Design beautiful, on-brand emails with our visual drag and drop builder. Upgrade to Order100 Pro to access this feature.');
											}
										} else if (type === 'printer-layouts') {
											if(typeof o100ShowProModal === 'function') {
												o100ShowProModal('Advanced Printer Layouts', 'Customize thermal receipt layouts, specify which categories print to which prep stations (e.g. drinks to bar, food to kitchen). Upgrade to Order100 Pro to access this feature.');
											}
										}
									});
								});
							});
							</script>
							<?php endif; ?>
						</nav>
						<div class="o100-header-save-container" style="margin-bottom: 0.5rem;">
							<button type="button" name="submit-cmb" value="Save Settings" id="o100-inner-save-btn" class="o100-fluent-top-save" style="margin: 0 !important; border: none !important;">
								<?php esc_html_e( 'Save Settings', 'order100' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<div class="o100-notify-wrap" style="padding: 2rem 0; padding-bottom: 3rem;">
				<script>
				// ═══ Instant subtab restore (runs before content divs render) ═══
				(function(){
					var s = '';
					try { s = localStorage.getItem('o100_notify_subtab'); } catch(e) {}
					if (s && s !== 'email') {
						// Pre-set CSS so browser renders the active subtab visible from the start
						document.write('<style id="o100-subtab-preload">.o100-notify-subtab-content{display:none !important}.o100-notify-subtab-content[data-subtab="' + s + '"]{display:block !important}</style>');
						// Also fix nav active state
						var navLinks = document.querySelectorAll('.o100-notify-subtabs a');
						if (navLinks.length) {
							navLinks.forEach(function(a){ a.classList.remove('o100-subtab-active'); });
							var sLink = document.querySelector('.o100-notify-subtabs a[data-subtab="' + s + '"]');
							if (sLink) sLink.classList.add('o100-subtab-active');
						}
						// Hide save button for email/reports
						if (s === 'email' || s === 'reports') {
							document.write('<style id="o100-save-preload">.o100-header-save-container { display:none !important; }</style>');
						}
						if (s === 'reports') {
							document.write('<style id="o100-form-preload">.o100-notify-wrap { display:none !important; }</style>');
						}
					} else {
						// Default to email, hide submit
						document.write('<style id="o100-save-preload">.o100-header-save-container { display:none !important; }</style>');
					}
				})();
				</script>

				<!-- ═══ EMAIL SUB-TAB ═══ -->
				<div class="o100-notify-subtab-content" data-subtab="email" style="padding: 0;">
					<div id="o100ne-main-pages" style="min-height: 500px; position: relative;">
						<div class="o100ne-pre-loading" style="width:20px;height:20px;background:url(<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>);background-size:contain;background-repeat:no-repeat;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);"></div>
					</div>
					<script>
						window.addEventListener('error', function(e) {
							console.error("DIAGNOSTIC CAUGHT ERROR:", e.message, e.filename, e.lineno);
						});
					</script>
					<div style="display: none;">
						<?php
						wp_editor( '', 'o100ne-wp-editor-placeholder', [
							'quicktags' => false, 'media_buttons' => true, 'tinymce' => true,
						]);
						?>
					</div>
				</div><!-- /email subtab -->


				<!-- ═══ SETTINGS SUB-TAB (API) ═══ -->
				<div id="sms-config" class="o100-notify-subtab-content o100-sms-fields-container" data-subtab="settings" style="display:none; width: 100%;">
					<!-- CMB2 API Fields will render here natively -->
					<!-- CMB2 SMS Fields will render here natively -->
		<?php
	}

	public function render_notification_tab_end( $field_args, $field ) {
		?>

	


				<script>
				// Condition Logic for Retry Interval
				(function($) {
					function toggleRetryInterval() {
						if ($('#o100_voice_retry_count').val() === '0') {
							$('.cmb2-id-o100-voice-retry-delay').hide();
						} else {
							$('.cmb2-id-o100-voice-retry-delay').show();
						}
					}
					$('#o100_voice_retry_count').on('change', toggleRetryInterval);
					// Run once on load
					setTimeout(toggleRetryInterval, 100);
				})(jQuery);

				// Unsaved warning and dirty state logic for Mobile Notifications
				(function($) {
					// Toggle Submit button visibility based on tab
					$('.o100-notify-subtabs a').on('click', function(e) {
						$('#o100-save-preload').remove();
						$('#o100-form-preload').remove();
						var tab = $(this).data('subtab');
						if (tab === 'email' || tab === 'reports') {
							$('.o100-header-save-container').hide();
						} else {
							$('.o100-header-save-container').show();
						}
						// Hide/show content wrap when switching to/from Reports
						if (tab === 'reports') {
							$('.o100-notify-wrap').hide();
						} else {
							$('.o100-notify-wrap').show();
						}
					});
				})(jQuery);
				</script>
			</div><!-- /o100-notify-wrap -->
		<?php
	}

	public function render_locations_intro() {
		$is_enabled = get_option('o100_locations_status') === 'on';
		$is_premium = function_exists('O100_License') && O100_License()->is_premium();
		if ( ! $is_premium ) $is_enabled = false;
		?>
		<!-- Header Toggle Element (Will be teleported by JS) -->
		<div id="o100_locations_header_toggle" style="display: none;">
			<div class="cmb-type-checkbox" style="margin: 0;">
				<label for="o100_enable_loc_dummy" class="o100-toggle-wrap" style="display: flex; align-items: center; justify-content: flex-start; width: auto; background: #f8fafc; padding: 6px 12px; border-radius: 20px; border: 1px solid #e2e8f0; <?php echo ! $is_premium ? 'opacity:0.6; cursor:not-allowed;' : 'cursor: pointer;'; ?>">
					<input type="checkbox" id="o100_enable_loc_dummy" value="on" <?php checked( $is_enabled ); ?> <?php echo ! $is_premium ? 'disabled' : ''; ?>>
					<span style="font-size: 14px; font-weight: 600; color: #334155; margin-left: 8px; pointer-events: none;"><?php esc_html_e( 'Locations Enabled', 'order100' ); ?></span>
				</label>
			</div>
		</div>

		<div class="o100-locations-intro-wrap" style="display: <?php echo $is_enabled ? 'none' : 'flex'; ?>; flex-direction: column; align-items: center; justify-content: center; background: #fff; padding: 60px 40px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">
			<h2 style="font-size: 24px; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 16px;"><?php esc_html_e( 'Multi-Locations', 'order100' ); ?></h2>
			<p style="font-size: 15px; color: #475569; margin-bottom: 32px; text-align: center; max-width: 600px; line-height: 1.6;"><?php esc_html_e( 'Manage multiple store locations, define separate delivery radiuses, minimum orders, and unique addresses for each branch directly from this dashboard.', 'order100' ); ?></p>
			<?php if ( ! $is_premium ) : 
				O100_License()->render_upgrade_notice( 'Multi-Branch', 'Run a franchise or multiple branches? Unlock the ability to manage unlimited locations from a single dashboard.' );
			else : ?>
				<button type="button" class="o100-start-locations-btn" style="background: #22c55e; color: white; border: none; padding: 12px 32px; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);"><?php esc_html_e( 'Start Using Locations', 'order100' ); ?></button>
			<?php endif; ?>
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
				<button type="button" class="button button-primary o100-add-location-btn" style="background:#F59322; border-color:#F59322; box-shadow:none;">
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
						<a href="#" class="o100-loc-tab active" data-tab="general" style="padding:12px 16px; font-size:14px; font-weight:500; color:#F59322; border-bottom:2px solid #F59322; text-decoration:none; margin-bottom:-1px;">📍 Profile</a>
						<a href="#" class="o100-loc-tab" data-tab="fulfillment" style="padding:12px 16px; font-size:14px; font-weight:500; color:#64748b; border-bottom:2px solid transparent; text-decoration:none; margin-bottom:-1px;">Fulfillment</a>
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
							.o100-help-icon:hover { color: #F59322; }
							.o100-help-text { display: none; margin-top: 6px; margin-bottom: 10px; padding: 10px; background: #eff6ff; color: #d97b06; font-size: 12px; border-radius: 4px; border-left: 3px solid #F59322; line-height: 1.4; }
							.o100-modal-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 12px; font-size: 13px; color: #334155; transition: border-color 0.2s; }
							.o100-modal-input:focus { border-color: #F59322; outline: none; box-shadow: 0 0 0 1px #F59322; }
							.o100-modal-row { display: flex; gap: 15px; margin-bottom: 24px; }
							.o100-modal-row:last-child { margin-bottom: 0; }
							.o100-modal-col { flex: 1; }
							.o100-cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; margin-top: 10px; }
							.o100-cat-item { display: flex; align-items: center; font-size: 13px; color: #475569; }
							.o100-cat-item input { margin: 0 8px 0 0; }
							.o100-modal-field .select2-container { padding: 0 !important; background: transparent !important; border: none !important; box-shadow: none !important; }
							.o100-modal-field .select2-container--default .select2-selection--multiple { border: 1px solid #cbd5e1 !important; border-radius: 4px !important; min-height: 36px !important; }
							.o100-modal-field .select2-container--default.select2-container--focus .select2-selection--multiple { border-color: #F59322 !important; box-shadow: 0 0 0 1px #F59322 !important; }
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
						<button type="button" class="button button-primary o100-save-location-action" style="background:#F59322; border-color:#F59322; padding:0 20px; height:36px;">
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
								html += '			<button type="button" class="o100-edit-loc" data-id="' + loc.id + '" style="background:none; border:none; padding:6px; cursor:pointer; color:#F59322; border-radius:6px; display:flex; align-items:center; justify-content:center; transition:background 0.15s ease;" onmouseover="this.style.background=\'#fff7ed\'" onmouseout="this.style.background=\'none\'" title="<?php echo esc_js( __( 'Edit Location', 'order100' ) ); ?>"><span class="dashicons dashicons-edit"></span></button>';
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
				$(this).addClass('active').css({'color': '#F59322', 'border-bottom-color': '#F59322'});
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
						var msg = response.data || 'Error saving location';
						if ( typeof msg === 'string' && msg.indexOf('Free version is limited to 1 branch') !== -1 && typeof window.o100ShowProModal === 'function' ) {
							$('.o100-location-modal-overlay').hide();
							window.o100ShowProModal('Multi-Branch System', 'The Free version is limited to 1 branch. Upgrade to Order100 Pro to manage unlimited locations and centralize your operations.');
						} else {
							if(window.o100ShowToast) window.o100ShowToast(msg, 'error');
						}
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
		
		if ( $status === 'on' && function_exists('O100_License') && ! O100_License()->is_premium() ) {
			wp_send_json_error( 'Premium required to enable Multi-Branch locations.' );
		}
		
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
			/* Remove overlapping border from description if it exists */
			.o100-fluent-content p.cmb2-metabox-description {
				border: none !important;
				box-shadow: none !important;
				margin-bottom: 16px !important; /* Provide some breathing room */
			}
			/* Remove any outer row border that CMB2 might apply */
			.o100-fluent-content div.cmb-row.cmb-type-group {
				border-top: none !important;
				border-bottom: none !important;
				box-shadow: none !important;
			}
			/* Kill any CMB2 native border on the TD */
			.o100-fluent-content div.cmb-row.cmb-type-group > .cmb-td {
				border: none !important;
			}
			/* Reset native CMB2 outer group wrapper padding/border */
			.o100-fluent-content div.cmb-repeatable-group,
			.o100-fluent-content div.cmb-td > div.cmb-repeatable-group {
				padding: 0 !important;
				margin: 0 !important;
				border: none !important;
				background: transparent !important;
				box-shadow: none !important;
				outline: none !important;
			}
			/* Kill the native CMB2 border-top on repeatable group rows */
			.o100-fluent-content div.cmb-repeatable-group > .cmb-row,
			.o100-fluent-content div.cmb-repeatable-group > .cmb-repeatable-grouping {
				border-top: none !important;
			}
			/* Super high specificity (0,0,5,1) to guarantee no padding */
			.o100-fluent-content div.cmb-row.cmb-repeatable-grouping:not(.empty-row):not(.hidden) {
				position: relative !important;
				border: 1px solid #e2e8f0 !important;
				border-radius: 8px !important;
				margin-bottom: 24px !important;
				background: #fff !important;
				box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
				padding: 0 !important; /* Force reset native padding */
				overflow: hidden !important; /* contain child radius */
			}
			/* Prevent pseudo elements from creating 1px gaps */
			.o100-fluent-content div.cmb-row.cmb-repeatable-grouping::before,
			.o100-fluent-content div.cmb-row.cmb-repeatable-grouping::after {
				display: none !important;
			}
			/* Closed State Group */
			.o100-fluent-content div.cmb-row.cmb-repeatable-grouping.closed:not(.empty-row):not(.hidden) {
				border-bottom: 1px solid #e2e8f0 !important;
			}
			/* Custom Radio Button Styling */
			.o100-fluent-content input[type="radio"] {
				-webkit-appearance: none;
				appearance: none;
				width: 18px;
				height: 18px;
				border: 2px solid #cbd5e1;
				border-radius: 50%;
				outline: none;
				cursor: pointer;
				position: relative;
				vertical-align: middle;
				margin-top: -2px;
				margin-right: 6px;
				background-color: #fff;
				transition: border-color 0.2s, background-color 0.2s;
			}
			/* Kill the native WP blue dot */
			.o100-fluent-content input[type="radio"]::before {
				display: none !important;
			}
			.o100-fluent-content input[type="radio"]:checked {
				border-color: #F59322;
				border-width: 5px;
				background-color: #fff;
			}
			.o100-fluent-content input[type="radio"]:hover:not(:checked) {
				border-color: #94a3b8;
			}
			.o100-fluent-content ul.cmb2-radio-list li {
				margin-bottom: 8px;
			}
			.o100-fluent-content ul.cmb2-radio-list li:last-child {
				margin-bottom: 0;
			}
			/* Reduce excessive whitespace below Title rows in repeaters */
			.o100-fluent-content div.cmb-row.cmb-type-title {
				padding-bottom: 8px !important;
				border-bottom: none !important;
			}
			.o100-fluent-content div.cmb-row.cmb-type-title .cmb2-metabox-description {
				margin-bottom: 0 !important;
			}
			.o100-fluent-content div.cmb-row.cmb-repeatable-grouping.closed:not(.empty-row):not(.hidden) > h3.cmb-group-title {
				border-bottom: none !important;
			}
			/* Group Title Bar */
			.o100-fluent-content div.cmb-row.cmb-repeatable-grouping:not(.empty-row):not(.hidden) > h3.cmb-group-title {
				background: #f1f5f9 !important; /* Make header distinctly grey */
				padding: 12px 45px 12px 20px !important;
				margin: 0 !important;
				border: none !important;
				border-bottom: 1px solid #e2e8f0 !important;
				border-radius: 0 !important;
				font-size: 15px !important;
				font-weight: 600 !important;
				color: #0f172a !important;
				outline: none !important;
				box-shadow: none !important; /* Remove any inset shadows */
				width: 100% !important;
				max-width: 100% !important;
				box-sizing: border-box !important;
				display: block !important;
				position: relative !important;
				cursor: pointer !important; /* Make it look clickable */
			}
			/* Accordion Arrow Icon */
			.o100-fluent-content div.cmb-row.cmb-repeatable-grouping:not(.empty-row):not(.hidden) > h3.cmb-group-title::after {
				content: "\f142"; /* dashicons-arrow-up-alt2 for open state */
				font-family: dashicons;
				position: absolute;
				right: 20px;
				top: 50%;
				transform: translateY(-50%);
				color: #64748b;
				font-size: 20px;
				pointer-events: none;
			}
			/* Accordion Arrow Icon (Closed State) */
			.o100-fluent-content div.cmb-row.cmb-repeatable-grouping.closed:not(.empty-row):not(.hidden) > h3.cmb-group-title::after {
				content: "\f140"; /* dashicons-arrow-down-alt2 for closed state */
			}
			/* Explicitly hide the inner remove button and ensure it takes no space */
			.o100-fluent-content div.cmb-row.cmb-repeatable-grouping > button.cmb-remove-group-row {
				display: none !important;
				height: 0 !important;
				margin: 0 !important;
				padding: 0 !important;
			}
			.o100-fluent-content div.cmb-row.cmb-repeatable-grouping .cmbhandle {

				position: absolute !important;
				top: 5px !important;
				right: 12px !important;
				width: 32px !important;
				height: 32px !important;
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
				cursor: pointer !important;
				color: #64748b !important;
			}
			.cmb-repeatable-grouping .cmbhandle:hover {
				color: #0f172a !important;
			}

			/* General Remove Button (Hidden in favor of custom inline one) */
			.cmb-repeatable-grouping > button.cmb-remove-group-row {
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
				box-shadow: none !important;
				text-shadow: none !important;
			}
			.cmb-add-group-row:hover {
				background: #e2e8f0 !important;
				border-color: #94a3b8 !important;
			}
			/* Apply padding to the fields inside the group */
			.cmb-repeatable-grouping .cmb-row:not(.cmb-remove-field-row) {
				padding: 16px 20px !important;
				border-bottom: 1px solid #e2e8f0 !important;
				background: #fff !important;
				margin: 0 !important;
			}
			.cmb-repeatable-grouping .cmb-row:not(.cmb-remove-field-row):last-of-type {
				border-bottom: none !important;
			}
			/* The native remove row button container at the bottom */
			.cmb-repeatable-grouping .cmb-remove-field-row {
				padding: 16px 20px !important;
				background: #f8fafc !important;
				border-top: 1px solid #e2e8f0 !important;
				margin: 0 !important;
			}
			.cmb-repeatable-grouping .cmb-remove-field-row .cmb-remove-row {
				display: flex !important;
				align-items: center !important;
				width: 100% !important;
			}
			/* Remove Rule Button */
			button.cmb-remove-group-row-button {
				color: #ef4444 !important;
				border: 1px solid #ef4444 !important;
				background: #fff !important;
				border-radius: 6px !important;
				font-weight: 500 !important;
				padding: 4px 12px !important;
				transition: all 0.2s !important;
				box-shadow: none !important;
				text-shadow: none !important;
				margin-left: auto !important; /* Pushes button to the right */
			}
			button.cmb-remove-group-row-button:hover {
				background: #fef2f2 !important;
				color: #dc2626 !important;
				border-color: #dc2626 !important;
			}
			/* Shift Rows (Up/Down arrows) */
			a.cmb-shift-rows {
				display: inline-flex !important;
				align-items: center !important;
				justify-content: center !important;
				width: 32px !important;
				height: 32px !important;
				padding: 0 !important;
				border-radius: 6px !important;
				border: 1px solid #cbd5e1 !important;
				background: #fff !important;
				color: #475569 !important;
				box-shadow: none !important;
				text-decoration: none !important;
				margin-right: 8px !important;
			}
			a.cmb-shift-rows:hover {
				background: #f1f5f9 !important;
				border-color: #94a3b8 !important;
			}
			a.cmb-shift-rows .dashicons {
				font-size: 18px !important;
				width: 18px !important;
				height: 18px !important;
			}
			/* Select / Deselect All Toggle */
			.cmb-multicheck-toggle {
				display: inline-block !important;
				margin-bottom: 12px !important;
				color: #F59322 !important;
				background: #eff6ff !important;
				border: 1px solid #bfdbfe !important;
				padding: 4px 12px !important;
				border-radius: 6px !important;
				font-size: 12px !important;
				font-weight: 500 !important;
				text-decoration: none !important;
				transition: all 0.2s !important;
			}
			.cmb-multicheck-toggle:hover {
				background: #fff7ed !important;
				color: #F59322 !important;
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
				background: #F59322 !important;
				color: #fff !important;
				border: none !important;
				border-radius: 6px !important;
				font-weight: 600 !important;
				font-size: 14px !important;
				box-shadow: 0 2px 4px rgba(37,99,235,0.2) !important;
			}
			.o100-btn-col a.button:hover {
				background: #d97b06 !important;
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
				background-color: #F59322 !important;
				border-color: #F59322 !important;
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
				border-color: #F59322 !important;
				box-shadow: 0 0 0 1px #F59322 !important;
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
						$('.o100-flatpickr-range').flatpickr({
							mode: "range",
							dateFormat: "Y-m-d",
							altInput: true,
							altFormat: "M j, Y",
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

	public function ajax_save_misc() {
		check_ajax_referer( 'o100_admin_nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Permission denied.' ) );

		$misc_data = isset( $_POST['o100_misc'] ) ? wp_unslash( $_POST['o100_misc'] ) : array();
		update_option( 'o100_misc', $misc_data );
		
		wp_send_json_success();
	}

	/**
	 * Renders a standardized Input Group component with a prefix or suffix.
	 *
	 * @param array $args {
	 *     @type string $name        Field name (required)
	 *     @type string $value       Current value
	 *     @type string $type        Input type (default: 'text')
	 *     @type string $placeholder Placeholder text
	 *     @type string $prefix      Prefix text (e.g., '$')
	 *     @type string $suffix      Suffix text (e.g., 'px')
	 *     @type string $class       Extra classes for the input
	 *     @type array  $attrs       Key-value pairs for additional attributes
	 * }
	 */
	public static function render_input_group( $args = array() ) {
		$name        = isset( $args['name'] ) ? $args['name'] : '';
		$value       = isset( $args['value'] ) ? $args['value'] : '';
		$type        = isset( $args['type'] ) ? $args['type'] : 'text';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		$prefix      = isset( $args['prefix'] ) ? $args['prefix'] : '';
		$suffix      = isset( $args['suffix'] ) ? $args['suffix'] : '';
		$class       = isset( $args['class'] ) ? $args['class'] : '';
		$attrs       = isset( $args['attrs'] ) && is_array( $args['attrs'] ) ? $args['attrs'] : array();

		$attr_string = '';
		foreach ( $attrs as $k => $v ) {
			$attr_string .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}

		$wrapper_class = 'o100-flex-input-wrap w-full';
		if ( ! empty( $prefix ) ) $wrapper_class .= ' has-prefix';
		if ( ! empty( $suffix ) ) $wrapper_class .= ' has-suffix';
		
		echo '<div class="' . esc_attr( $wrapper_class ) . '">';
		
		if ( ! empty( $prefix ) ) {
			echo '<span class="o100-flex-prefix">' . esc_html( $prefix ) . '</span>';
		}

		echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" class="o100-modal-input ' . esc_attr( $class ) . '"' . $attr_string . '>';

		if ( ! empty( $suffix ) ) {
			echo '<span class="o100-flex-suffix">' . esc_html( $suffix ) . '</span>';
		}

		echo '</div>';
	}

	/**
	 * Render the Misc Tab (AJAX Form)
	 */
	public static function render_fluent_misc() {
		echo '<form id="o100-misc-form">';
		$instance = new self();
		$instance->render_food_labels_table(null, null);
		echo '<input type="hidden" name="security" value="' . esc_attr( wp_create_nonce( 'o100_admin_nonce' ) ) . '">';
		echo '</form>';
	}

	/**
	 * Render the Private Rooms table + modal
	 */
	public function render_food_labels_table( $field_args, $field ) {
		$opts = get_option( 'o100_misc', array() );
		$labels = isset( $opts['o100_global_food_labels'] ) ? $opts['o100_global_food_labels'] : array();
		if ( is_string( $labels ) ) {
			$decoded = json_decode( $labels, true );
			$labels = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $labels ) ) {
			$labels = array();
		}

		$nonce = wp_create_nonce( 'o100_upload_label_icon' );
		?>
		<div class="o100-settings-group-card" style="margin-top:20px;">
			<div class="o100-settings-group-title">
				<h3><?php esc_html_e( 'Food Labels Library', 'order100' ); ?></h3>
				<p><?php esc_html_e( 'Define global food labels (e.g., Spicy, Vegan) to be used across your products.', 'order100' ); ?></p>
			</div>
			<div class="o100-settings-group-content">
				<input type="hidden" name="o100_global_food_labels" id="o100_global_food_labels_data" value="<?php echo esc_attr( wp_json_encode( $labels ) ); ?>">

				<style>
					#o100-fl-table { border-collapse: collapse; width: 100%; }
					#o100-fl-table th { padding: 12px 16px; border-bottom: 1px solid #cbd5e1; color: #475569; font-weight: 600; text-align: left; }
					#o100-fl-table td { padding: 18px 16px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
					#o100-fl-table tbody tr:last-child td { border-bottom: none; }
					#o100-fl-table tbody tr:hover td { background: #f8fafc; }
				</style>
				<table class="o100-fb-table" id="o100-fl-table" style="width:100%;">
					<thead>
						<tr>
							<th style="width:20%;"><?php esc_html_e( 'Name', 'order100' ); ?></th>
							<th style="width:30%;"><?php esc_html_e( 'Icon', 'order100' ); ?></th>
							<th style="width:20%;"><?php esc_html_e( 'Background Color', 'order100' ); ?></th>
							<th style="width:30%; text-align:right;"><?php esc_html_e( 'Action', 'order100' ); ?></th>
						</tr>
					</thead>
					<tbody id="o100-fl-tbody">
					</tbody>
				</table>

				<button type="button" id="o100-fl-add-btn" style="margin-top:14px; background:#F59322; border:none; color:#fff; box-shadow:0 1px 2px rgba(0,0,0,0.1); display:inline-flex; align-items:center; gap:6px; padding:8px 18px; font-size:13px; font-weight:500; border-radius:6px; cursor:pointer; transition:background 0.2s;" onmouseover="this.style.background='#d97b06'" onmouseout="this.style.background='#F59322'">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
					<?php esc_html_e( 'Add New Label', 'order100' ); ?>
				</button>
			</div>
		</div>

		<!-- Modal -->
		<div id="o100-fl-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
			<div style="background:#fff; width:100%; max-width:500px; border-radius:12px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); display:flex; flex-direction:column; max-height:90vh;">
				<div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between;">
					<h3 style="margin:0; font-size:16px; font-weight:600; color:#0f172a;" id="o100-fl-modal-title"><?php esc_html_e( 'Add Label', 'order100' ); ?></h3>
					<button type="button" class="o100-fl-modal-close" style="background:transparent; border:none; cursor:pointer; color:#64748b; padding:4px;">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
					</button>
				</div>
				<div style="padding:20px; overflow-y:auto;">
					<div style="margin-bottom:16px;">
						<label style="display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;"><?php esc_html_e( 'Label Name', 'order100' ); ?> <span style="color:#ef4444;">*</span></label>
						<input type="text" id="o100-fl-name" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; outline:none; transition:border-color 0.2s;" placeholder="e.g. Spicy" onfocus="this.style.borderColor='#F59322'" onblur="this.style.borderColor='#cbd5e1'">
					</div>
					<div style="margin-bottom:16px;">
						<label style="display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;"><?php esc_html_e( 'Icon', 'order100' ); ?></label>
						<div id="o100-fl-icon-area" style="border:2px dashed #cbd5e1; border-radius:8px; padding:16px; text-align:center; cursor:pointer; transition:all 0.2s; background:#f8fafc;" onmouseover="this.style.borderColor='#F59322';this.style.background='#eff6ff'" onmouseout="this.style.borderColor='#cbd5e1';this.style.background='#f8fafc'">
							<div id="o100-fl-icon-preview-wrap" style="display:none; margin-bottom:8px;">
								<img id="o100-fl-icon-preview-img" src="" style="max-width:48px; max-height:48px; object-fit:contain; border-radius:4px; margin:0 auto; display:block;">
							</div>
							<div id="o100-fl-icon-placeholder">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 6px;display:block;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
								<p style="margin:0; font-size:13px; color:#64748b;"><?php esc_html_e( 'Click to upload or drag & drop', 'order100' ); ?></p>
								<p style="margin:4px 0 0; font-size:11px; color:#94a3b8;"><?php esc_html_e( 'SVG, PNG recommended', 'order100' ); ?></p>
							</div>
							<input type="file" id="o100-fl-file-input" accept="image/*,.svg" style="display:none;">
							<input type="hidden" id="o100-fl-icon-url" value="">
						</div>
						<div id="o100-fl-icon-actions" style="display:none; margin-top:8px; text-align:right;">
							<button type="button" id="o100-fl-icon-change" style="background:#fff; border:1px solid #cbd5e1; border-radius:6px; padding:5px 12px; font-size:12px; font-weight:500; color:#334155; cursor:pointer; margin-right:6px;">
								<?php esc_html_e( 'Change', 'order100' ); ?>
							</button>
							<button type="button" id="o100-fl-icon-remove" style="background:#fff; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; font-size:12px; font-weight:500; color:#ef4444; cursor:pointer;">
								<?php esc_html_e( 'Remove', 'order100' ); ?>
							</button>
						</div>
					</div>
					<div style="margin-bottom:16px;">
						<label style="display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;"><?php esc_html_e( 'Background Color', 'order100' ); ?></label>
						<input type="color" id="o100-fl-bgcolor" value="#ffffff" style="width:60px; height:40px; border:1px solid #cbd5e1; border-radius:6px; padding:2px; cursor:pointer;">
					</div>
				</div>
				<div style="padding:16px 20px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px; background:#f8fafc; border-radius:0 0 12px 12px;">
					<button type="button" class="o100-fl-modal-close" style="background:#fff; border:1px solid #cbd5e1; border-radius:6px; padding:8px 18px; font-size:13px; font-weight:500; color:#334155; cursor:pointer;"><?php esc_html_e( 'Cancel', 'order100' ); ?></button>
					<button type="button" id="o100-fl-modal-save" style="background:#F59322; border:none; border-radius:6px; padding:8px 18px; font-size:13px; font-weight:500; color:#fff; cursor:pointer; transition:background 0.2s;" onmouseover="this.style.background='#d97b06'" onmouseout="this.style.background='#F59322'"><?php esc_html_e( 'Save Label', 'order100' ); ?></button>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			let labels = [];
			try {
				labels = JSON.parse($("#o100_global_food_labels_data").val()) || [];
			} catch(e) { labels = []; }

			let editIndex = -1;

			function renderTable() {
				const tbody = $("#o100-fl-tbody");
				tbody.empty();
				if (labels.length === 0) {
					tbody.append(`<tr><td colspan="4" style="text-align:center; padding:20px; color:#64748b;">No labels found.</td></tr>`);
					return;
				}
				labels.forEach((lbl, index) => {
					let iconHtml = lbl.icon ? `<img src="${lbl.icon}" style="max-width:30px; max-height:30px; object-fit:contain; border-radius:4px;">` : `<span style="color:#94a3b8; font-size:12px;">No icon</span>`;
					let colorHtml = lbl.bgcolor ? `<div style="display:flex; align-items:center; gap:8px;"><div style="width:20px; height:20px; border-radius:4px; border:1px solid #e2e8f0; background:${lbl.bgcolor};"></div><code style="font-size:12px; color:#64748b;">${lbl.bgcolor}</code></div>` : `-`;

					tbody.append(`
						<tr>
							<td style="font-weight:500; color:#334155;">${lbl.name}</td>
							<td>${iconHtml}</td>
							<td>${colorHtml}</td>
							<td style="text-align:right;">
								<div style="display:flex; gap:14px; align-items:center; justify-content:flex-end;">
									<button type="button" class="o100-fl-edit" data-index="${index}" style="background:none; border:none; padding:0; cursor:pointer; color:#F59322; display:flex;" title="Edit">
										<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
									</button>
									<button type="button" class="o100-fl-delete" data-index="${index}" style="background:none; border:none; padding:0; cursor:pointer; color:#ef4444; display:flex;" title="Delete">
										<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
									</button>
								</div>
							</td>
						</tr>
					`);
				});
				$("#o100_global_food_labels_data").val(JSON.stringify(labels)).trigger('change');
				// Enable top save button
				$('.o100-fluent-top-save').removeClass('o100-save-disabled');
			}

			renderTable();

			const modal = $("#o100-fl-modal");
			const placeholderOriginal = $("#o100-fl-icon-placeholder").html();

			function updateIconUI(url) {
				if (url) {
					$("#o100-fl-icon-preview-img").attr("src", url);
					$("#o100-fl-icon-preview-wrap").show();
					$("#o100-fl-icon-placeholder").hide();
					$("#o100-fl-icon-actions").show();
				} else {
					$("#o100-fl-icon-preview-wrap").hide();
					$("#o100-fl-icon-placeholder").html(placeholderOriginal).show();
					$("#o100-fl-icon-actions").hide();
				}
				$("#o100-fl-icon-url").val(url || "");
			}

			function openMediaPicker() {
				if (typeof wp === 'undefined' || typeof wp.media !== 'function') {
					alert('Media library is not available. Please reload the page.');
					return;
				}
				const frame = wp.media({
					title: 'Choose Icon',
					button: { text: 'Use this icon' },
					multiple: false
				});
				frame.on('select', function() {
					const attachment = frame.state().get('selection').first().toJSON();
					updateIconUI(attachment.url);
				});
				frame.open();
			}

			function openModal(index) {
				editIndex = index;
				if (index > -1) {
					$("#o100-fl-modal-title").text("Edit Label");
					$("#o100-fl-name").val(labels[index].name || "");
					$("#o100-fl-bgcolor").val(labels[index].bgcolor || "#ffffff");
					updateIconUI(labels[index].icon || "");
				} else {
					$("#o100-fl-modal-title").text("Add Label");
					$("#o100-fl-name").val("");
					$("#o100-fl-bgcolor").val("#ffffff");
					updateIconUI("");
				}
				modal.css("display", "flex");
			}

			function closeModal() { modal.hide(); }

			// Click upload area or Change button => open WP media library
			$("#o100-fl-icon-area").on("click", function(e) {
				if ($(e.target).closest("#o100-fl-icon-actions").length) return;
				openMediaPicker();
			});
			$("#o100-fl-icon-change").on("click", function(e) {
				e.stopPropagation();
				openMediaPicker();
			});

			// Remove icon
			$("#o100-fl-icon-remove").on("click", function(e) {
				e.stopPropagation();
				updateIconUI("");
			});

			// Events
			$(".o100-fl-modal-close").on("click", closeModal);
			$("#o100-fl-add-btn").on("click", function() { openModal(-1); });
			$(document).on("click", ".o100-fl-edit", function() { openModal($(this).data("index")); });
			$(document).on("click", ".o100-fl-delete", function() {
				if (confirm("Are you sure you want to delete this label?")) {
					labels.splice($(this).data("index"), 1);
					renderTable();
				}
			});

			$("#o100-fl-modal-save").on("click", function() {
				const name = $("#o100-fl-name").val().trim();
				if (!name) { alert("Please enter a label name."); return; }
				const lbl = {
					name: name,
					icon: $("#o100-fl-icon-url").val(),
					bgcolor: $("#o100-fl-bgcolor").val()
				};
				if (editIndex > -1) {
					labels[editIndex] = lbl;
				} else {
					labels.push(lbl);
				}
				renderTable();
				closeModal();
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler: Upload label icon via native file input.
	 * Uses wp_handle_upload to properly store in WP media library.
	 */
	public function ajax_upload_label_icon() {
		check_ajax_referer( 'o100_upload_label_icon', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( 'No file uploaded.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( $attachment_id->get_error_message() );
		}

		$url = wp_get_attachment_url( $attachment_id );
		wp_send_json_success( array( 'url' => $url, 'id' => $attachment_id ) );
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
		if ( ! empty( $attributes['data-assign-field'] ) ) {
			$attrs .= ' data-assign-field="' . esc_attr( $attributes['data-assign-field'] ) . '"';
		}
		if ( ! empty( $attributes['data-rule-field'] ) ) {
			$attrs .= ' data-rule-field="' . esc_attr( $attributes['data-rule-field'] ) . '"';
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
	public function render_phone_intl_field( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
		$value = wp_parse_args( $escaped_value, array( 'code' => '+1', 'number' => '' ) );
		$country_codes = array(
			'+1'   => '🇺🇸/🇨🇦 US/CA (+1)',
			'+44'  => '🇬🇧 UK (+44)',
			'+61'  => '🇦🇺 AU (+61)',
			'+64'  => '🇳🇿 NZ (+64)',
			'+86'  => '🇨🇳 CN (+86)',
			'+852' => '🇭🇰 HK (+852)',
			'+886' => '🇹🇼 TW (+886)',
			'+65'  => '🇸🇬 SG (+65)',
			'+60'  => '🇲🇾 MY (+60)',
			'+81'  => '🇯🇵 JP (+81)',
			'+82'  => '🇰🇷 KR (+82)',
			'+66'  => '🇹🇭 TH (+66)',
			'+84'  => '🇻🇳 VN (+84)',
			'+62'  => '🇮🇩 ID (+62)',
			'+63'  => '🇵🇭 PH (+63)',
			'+91'  => '🇮🇳 IN (+91)',
			'+33'  => '🇫🇷 FR (+33)',
			'+49'  => '🇩🇪 DE (+49)',
			'+39'  => '🇮🇹 IT (+39)',
			'+34'  => '🇪🇸 ES (+34)',
			'+31'  => '🇳🇱 NL (+31)',
			'+32'  => '🇧🇪 BE (+32)',
			'+41'  => '🇨🇭 CH (+41)',
			'+43'  => '🇦🇹 AT (+43)',
			'+46'  => '🇸🇪 SE (+46)',
			'+47'  => '🇳🇴 NO (+47)',
			'+45'  => '🇩🇰 DK (+45)',
			'+358' => '🇫🇮 FI (+358)',
			'+353' => '🇮🇪 IE (+353)',
			'+52'  => '🇲🇽 MX (+52)',
			'+55'  => '🇧🇷 BR (+55)',
			'+54'  => '🇦🇷 AR (+54)',
			'+56'  => '🇨🇱 CL (+56)',
			'+57'  => '🇨🇴 CO (+57)',
			'+51'  => '🇵🇪 PE (+51)',
			'+27'  => '🇿🇦 ZA (+27)',
			'+971' => '🇦🇪 AE (+971)',
			'+966' => '🇸🇦 SA (+966)',
			'+972' => '🇮🇱 IL (+972)',
			'+90'  => '🇹🇷 TR (+90)',
			'custom' => '🌐 Custom',
		);
		$attrs = '';
		$attributes = $field->args( 'attributes' );
		if ( ! empty( $attributes['data-o100-conditional-id'] ) ) {
			$attrs .= '';
		}
		if ( ! empty( $attributes['data-conditional-id'] ) ) {
			$attrs .= ' data-conditional-id="' . esc_attr( $attributes['data-conditional-id'] ) . '"';
		}
		
		$current_code = $value['code'];
		$is_custom = ! array_key_exists( $current_code, $country_codes ) && ! empty( $current_code );
		$select_val = $is_custom ? 'custom' : $current_code;
		$custom_val = $is_custom ? $current_code : '';

		echo '<div class="o100-phone-intl-wrap" style="display: flex; gap: 8px; max-width: 400px;"' . $attrs . '>';
		echo '<select class="o100-phone-code-select" name="' . esc_attr( $field->args( '_name' ) ) . '[code]" style="width: 120px !important; flex: 0 0 120px !important; max-width: 120px !important;">';
		foreach ($country_codes as $code => $label) {
			echo '<option value="'.esc_attr($code).'" '.selected($select_val, $code, false).'>'.esc_html($label).'</option>';
		}
		echo '</select>';
		
		echo '<input type="text" class="o100-phone-code-custom" name="' . esc_attr( $field->args( '_name' ) ) . '[custom_code]" value="' . esc_attr( $custom_val ) . '" placeholder="+XX" style="width: 65px !important; flex: 0 0 65px !important; display: ' . ($is_custom ? 'block' : 'none') . ';">';
		
		echo '<input type="tel" name="' . esc_attr( $field->args( '_name' ) ) . '[number]" value="' . esc_attr( $value['number'] ) . '" pattern="^\d+$" title="Please enter numbers only" style="flex: 1 1 auto !important; width: auto !important; min-width: 100px !important;">';
		echo '</div>';
		
		static $o100_phone_intl_js_added = false;
		if ( ! $o100_phone_intl_js_added ) {
			$o100_phone_intl_js_added = true;
			echo '<script>
				jQuery(document).on("change", ".o100-phone-code-select", function() {
					var $wrap = jQuery(this).closest(".o100-phone-intl-wrap");
					var $custom = $wrap.find(".o100-phone-code-custom");
					if(jQuery(this).val() === "custom") {
						$custom.show();
						jQuery(this).attr("style", "width: 95px !important; flex: 0 0 95px !important; max-width: 95px !important;");
					} else {
						$custom.hide();
						jQuery(this).attr("style", "width: 120px !important; flex: 0 0 120px !important; max-width: 120px !important;");
					}
				});
				jQuery(document).ready(function($) {
					$(".o100-phone-code-select").each(function() {
						if($(this).val() === "custom") {
							$(this).attr("style", "width: 95px !important; flex: 0 0 95px !important; max-width: 95px !important;");
						}
					});
				});
			</script>';
		}
	}

	public function sanitize_phone_intl_field( $override_value, $value, $object_id, $field_args, $sanitize_object ) {
		if ( ! is_array( $value ) ) {
			return '';
		}
		
		$code = isset( $value['code'] ) ? sanitize_text_field( $value['code'] ) : '';
		$custom_code = isset( $value['custom_code'] ) ? sanitize_text_field( $value['custom_code'] ) : '';
		
		if ( $code === 'custom' && ! empty( $custom_code ) ) {
			$code = $custom_code;
		}
		
		$number = isset( $value['number'] ) ? preg_replace( '/[^0-9]/', '', $value['number'] ) : '';
		
		if ( empty( $number ) ) {
			return '';
		}
		
		return array(
			'code'   => $code,
			'number' => $number
		);
	}


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
		if ( ! empty( $attributes['data-assign-field'] ) ) {
			$attrs .= ' data-assign-field="' . esc_attr( $attributes['data-assign-field'] ) . '"';
		}
		if ( ! empty( $attributes['data-rule-field'] ) ) {
			$attrs .= ' data-rule-field="' . esc_attr( $attributes['data-rule-field'] ) . '"';
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
									<option value="polygon" disabled>Polygon Geo-fencing (PRO)</option>
								</select>
								
								<script>
								document.addEventListener("DOMContentLoaded", function() {
									var select = document.getElementById('o100_limit_shp_select');
									if (select) {
										// Enable the disabled option just so it can be clicked/selected
										var polyOpt = select.querySelector('option[value="polygon"]');
										if (polyOpt) {
											polyOpt.removeAttribute('disabled');
										}
										
										select.addEventListener('change', function(e) {
											if (e.target.value === 'polygon') {
												// Revert to previous value
												e.target.value = '<?php echo esc_js($limit_shp === "polygon" ? "radius" : $limit_shp); ?>';
												if(typeof o100ShowProModal === 'function') {
													o100ShowProModal('Polygon Geo-fencing', 'Draw custom delivery zones on a map and set unique fees and minimum orders per zone. Upgrade to Order100 Pro to unlock this feature.');
												}
											}
										});
									}
								});
								</script>
							</div>
							<div class="o100-modal-col o100-api-notice o100-field-radius-only" style="flex: 1; font-size: 13px; color: #64748b; margin-top: 24px; <?php echo $limit_shp === 'radius' ? '' : 'display: none;'; ?>">
								<i class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px; margin-right: 4px; color: #F59322;"></i>
								Requires <a href="<?php echo esc_url( admin_url( 'admin.php?page=o100-integration' ) ); ?>" style="color: #F59322; text-decoration: underline;">Google Maps API</a> configuration.
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
	public function ajax_search_crm_tags() {
		check_ajax_referer( 'o100_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$term = isset($_POST['term']) ? trim(sanitize_text_field($_POST['term'])) : '';
		if(!class_exists('O100_Customers_DB')) wp_send_json_error();

		$tags = O100_Customers_DB::get_tags();
		$system_tags = [];
		$manual_tags = [];
		foreach($tags as $t) {
			if ( empty($term) || stripos($t->title, $term) !== false || stripos($t->slug, $term) !== false ) {
				if ( $t->is_system == '1' ) {
					$system_tags[] = [ 'id' => $t->slug, 'text' => $t->title, 'is_system' => 1 ];
				} else {
					$manual_tags[] = [ 'id' => $t->slug, 'text' => $t->title, 'is_system' => 0 ];
				}
			}
		}
		
		$results = [];
		if ( ! empty($system_tags) ) {
			$results[] = [ 'is_header' => true, 'text' => 'Smart Tags (System)' ];
			$results = array_merge($results, $system_tags);
		}
		if ( ! empty($manual_tags) ) {
			$results[] = [ 'is_header' => true, 'text' => 'Custom Tags (Manual)' ];
			$results = array_merge($results, $manual_tags);
		}
		
		wp_send_json_success($results);
	}

	public function ajax_search_crm_lists() {
		check_ajax_referer( 'o100_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$term = isset($_POST['term']) ? trim(sanitize_text_field($_POST['term'])) : '';
		if(!class_exists('O100_Customers_DB')) wp_send_json_error();

		$lists = O100_Customers_DB::get_lists();
		$results = [];
		foreach($lists as $l) {
			if ( empty($term) || stripos($l->title, $term) !== false || stripos($l->slug, $term) !== false ) {
				$results[] = [ 'id' => $l->slug, 'text' => $l->title ];
			}
		}
		wp_send_json_success($results);
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
			'o100_portal_launcher_style',
			'o100_portal_launcher_text', 'o100_portal_launcher_position', 'o100_portal_launcher_animation',
			'o100_portal_launcher_icon', 'o100_portal_launcher_shape', 'o100_portal_launcher_spacing', 'o100_portal_launcher_edge_mobile',
			'o100_portal_launcher_custom_image',
			'o100_portal_theme_color', 'o100_portal_bg_color', 'o100_portal_text_color', 'o100_portal_btn_text_color', 'o100_portal_border_radius',
			'o100_portal_theme_mode', 'o100_portal_panel_width', 'o100_portal_drawer_side', 'o100_portal_backdrop_overlay', 'o100_portal_close_btn_style',
			'o100_portal_z_index',
			'o100_portal_logo',
			'o100_portal_tab_label_cart', 'o100_portal_tab_label_rewards', 'o100_portal_tab_label_account',
			'o100_portal_guest_title', 'o100_portal_guest_subtitle', 'o100_portal_guest_btn_text',
			'o100_portal_guest_earn_text', 'o100_portal_guest_redeem_text',
			'o100_portal_guest_referral_title', 'o100_portal_guest_referral_desc',
			'o100_portal_member_welcome', 'o100_portal_member_points_format', 'o100_portal_member_earn_text', 'o100_portal_member_redeem_text',
			'o100_portal_member_referral_title', 'o100_portal_member_referral_desc',
			'o100_portal_cart_checkout_text', 'o100_portal_cart_empty_text', 'o100_portal_cart_continue_btn_text'
		);
		foreach($fields as $f) {
			if (isset($_POST[$f])) {
				$opts[$f] = sanitize_text_field( wp_unslash( $_POST[$f] ) );
			}
		}
		
		// Allow custom CSS to have newlines and basic css chars (don't strip all tags)
		if (isset($_POST['o100_portal_custom_css'])) {
			$opts['o100_portal_custom_css'] = wp_strip_all_tags( wp_unslash( $_POST['o100_portal_custom_css'] ) );
		}

		// Checkboxes & Arrays
		$opts['o100_portal_mobile_integration'] = isset($_POST['o100_portal_mobile_integration']) ? 'on' : 'off';
		$opts['o100_portal_cart_show_upsell'] = isset($_POST['o100_portal_cart_show_upsell']) ? 'yes' : 'no';
		$opts['o100_portal_cart_show_promo'] = isset($_POST['o100_portal_cart_show_promo']) ? 'yes' : 'no';
		$opts['o100_portal_guest_referral_enabled'] = isset($_POST['o100_portal_guest_referral_enabled']) ? 'yes' : 'no';
		$opts['o100_portal_member_referral_enabled'] = isset($_POST['o100_portal_member_referral_enabled']) ? 'yes' : 'no';
		$opts['o100_portal_enabled_tabs'] = isset($_POST['o100_portal_enabled_tabs']) && is_array($_POST['o100_portal_enabled_tabs']) ? array_map('sanitize_text_field', $_POST['o100_portal_enabled_tabs']) : array();
		if ( ! in_array('cart', $opts['o100_portal_enabled_tabs']) ) {
			array_unshift($opts['o100_portal_enabled_tabs'], 'cart');
		}
		
		update_option('o100_portal', $opts);
		wp_send_json_success();
	}

	public static function render_fluent_store_portal() {
		$opts = get_option('o100_portal', array());
		
		// Widget Mode is always sidecart in Order100
		$widget_mode    = 'sidecart';
		$launcher_style = isset($opts['o100_portal_launcher_style']) ? $opts['o100_portal_launcher_style'] : 'icon_text';
		
		// Launcher
		$launcher_text  = isset($opts['o100_portal_launcher_text']) ? $opts['o100_portal_launcher_text'] : 'Rewards & Cart';
		$launcher_pos   = isset($opts['o100_portal_launcher_position']) ? $opts['o100_portal_launcher_position'] : 'bottom-right';

		if ( $launcher_pos === 'hidden' ) {
			$launcher_style = 'hidden';
		}

		$launcher_pos   = isset($opts['o100_portal_launcher_position']) ? $opts['o100_portal_launcher_position'] : 'bottom-right';
		$launcher_anim  = isset($opts['o100_portal_launcher_animation']) ? $opts['o100_portal_launcher_animation'] : 'pulse';
		$launcher_icon  = isset($opts['o100_portal_launcher_icon']) ? $opts['o100_portal_launcher_icon'] : 'cart';
		$launcher_shape = isset($opts['o100_portal_launcher_shape']) ? $opts['o100_portal_launcher_shape'] : 'pill';
		$launcher_spacing = isset($opts['o100_portal_launcher_spacing']) ? $opts['o100_portal_launcher_spacing'] : '20';
		$launcher_mobile  = isset($opts['o100_portal_launcher_edge_mobile']) ? $opts['o100_portal_launcher_edge_mobile'] : '16';
		$mobile_nav       = isset($opts['o100_portal_mobile_integration']) ? $opts['o100_portal_mobile_integration'] : 'on';
		
		// Design
		$theme_color    = isset($opts['o100_portal_theme_color']) ? $opts['o100_portal_theme_color'] : '#e11d48';
		$bg_color       = isset($opts['o100_portal_bg_color']) ? $opts['o100_portal_bg_color'] : '#ffffff';
		$text_color     = isset($opts['o100_portal_text_color']) ? $opts['o100_portal_text_color'] : '#111111';
		$btn_text_color = isset($opts['o100_portal_btn_text_color']) ? $opts['o100_portal_btn_text_color'] : '#ffffff';
		$border_radius  = isset($opts['o100_portal_border_radius']) ? $opts['o100_portal_border_radius'] : '12';
		$theme_mode     = isset($opts['o100_portal_theme_mode']) ? $opts['o100_portal_theme_mode'] : 'light';
		$panel_width    = isset($opts['o100_portal_panel_width']) ? $opts['o100_portal_panel_width'] : '400';
		$drawer_side    = isset($opts['o100_portal_drawer_side']) ? $opts['o100_portal_drawer_side'] : 'right';
		$backdrop       = isset($opts['o100_portal_backdrop_overlay']) ? $opts['o100_portal_backdrop_overlay'] : 'dark';
		$close_btn      = isset($opts['o100_portal_close_btn_style']) ? $opts['o100_portal_close_btn_style'] : 'inside';
		$z_index        = isset($opts['o100_portal_z_index']) ? $opts['o100_portal_z_index'] : '999999';
		$custom_css     = isset($opts['o100_portal_custom_css']) ? $opts['o100_portal_custom_css'] : '';
		$logo_url       = isset($opts['o100_portal_logo']) ? $opts['o100_portal_logo'] : '';
		
		// Content - Tabs
		$enabled_tabs   = isset($opts['o100_portal_enabled_tabs']) ? $opts['o100_portal_enabled_tabs'] : array('cart', 'rewards', 'account');
		$tab_cart       = isset($opts['o100_portal_tab_label_cart']) ? $opts['o100_portal_tab_label_cart'] : 'Your Order';
		$tab_rewards    = isset($opts['o100_portal_tab_label_rewards']) ? $opts['o100_portal_tab_label_rewards'] : 'Rewards';
		$tab_account    = isset($opts['o100_portal_tab_label_account']) ? $opts['o100_portal_tab_label_account'] : 'Account';

		// Content - Text
		$guest_title    = isset($opts['o100_portal_guest_title']) ? $opts['o100_portal_guest_title'] : 'Unlock free food with every order!';
		$guest_sub      = isset($opts['o100_portal_guest_subtitle']) ? $opts['o100_portal_guest_subtitle'] : 'Sign up today and start earning points towards your next meal.';
		$guest_btn      = isset($opts['o100_portal_guest_btn_text']) ? $opts['o100_portal_guest_btn_text'] : 'Join Now';
		$guest_earn     = isset($opts['o100_portal_guest_earn_text']) ? $opts['o100_portal_guest_earn_text'] : 'Ways to earn';
		$guest_redeem   = isset($opts['o100_portal_guest_redeem_text']) ? $opts['o100_portal_guest_redeem_text'] : 'Ways to redeem';
		
		$member_welcome = isset($opts['o100_portal_member_welcome']) ? $opts['o100_portal_member_welcome'] : 'Welcome back, {name}!';
		$points_format  = isset($opts['o100_portal_member_points_format']) ? $opts['o100_portal_member_points_format'] : 'You have {points} Points';
		$member_earn    = isset($opts['o100_portal_member_earn_text']) ? $opts['o100_portal_member_earn_text'] : 'Ways to earn';
		$member_redeem  = isset($opts['o100_portal_member_redeem_text']) ? $opts['o100_portal_member_redeem_text'] : 'Ways to redeem';
		
		$guest_referral_enabled = isset($opts['o100_portal_guest_referral_enabled']) ? $opts['o100_portal_guest_referral_enabled'] : 'yes';
		$guest_referral_title   = isset($opts['o100_portal_guest_referral_title']) ? $opts['o100_portal_guest_referral_title'] : 'Refer and Earn';
		$guest_referral_desc    = isset($opts['o100_portal_guest_referral_desc']) ? $opts['o100_portal_guest_referral_desc'] : 'Share the love of our restaurant! Your friend gets $5 off their first order of $50+, and you get rewarded when they finish their meal!';
		
		$member_referral_enabled = isset($opts['o100_portal_member_referral_enabled']) ? $opts['o100_portal_member_referral_enabled'] : 'yes';
		$member_referral_title   = isset($opts['o100_portal_member_referral_title']) ? $opts['o100_portal_member_referral_title'] : 'Refer and Earn';
		$member_referral_desc    = isset($opts['o100_portal_member_referral_desc']) ? $opts['o100_portal_member_referral_desc'] : 'Share the love of our restaurant! Your friend gets $5 off their first order of $50+, and you get rewarded when they finish their meal!';
		
		// Cart Settings
		$cart_show_upsell= isset($opts['o100_portal_cart_show_upsell']) ? $opts['o100_portal_cart_show_upsell'] : 'yes';
		$cart_show_promo = isset($opts['o100_portal_cart_show_promo']) ? $opts['o100_portal_cart_show_promo'] : 'yes';
		$cart_checkout_text = isset($opts['o100_portal_cart_checkout_text']) ? $opts['o100_portal_cart_checkout_text'] : 'Go to checkout';
		$cart_empty_text    = isset($opts['o100_portal_cart_empty_text']) ? $opts['o100_portal_cart_empty_text'] : 'Your cart is currently empty.';
		$cart_continue_btn  = isset($opts['o100_portal_cart_continue_btn_text']) ? $opts['o100_portal_cart_continue_btn_text'] : 'Browse Menu';
		
		wp_enqueue_media();
		?>

		
		<div class="o100-portal-builder">
			<div class="o100-pb-config">
				<div class="o100-pb-tabs" style="margin-bottom:20px; background:#f8fafc; border:1px solid #e5e7eb; padding:4px; border-radius:10px; display:flex;">
					<button type="button" class="o100-pb-tab active" data-target="#pb-portal" style="flex:1;">Portal &amp; Launcher</button>
					<button type="button" class="o100-pb-tab" data-target="#pb-cart" style="flex:1;">Shopping Cart</button>
					<button type="button" class="o100-pb-tab" data-target="#pb-loyalty" style="flex:1;">Loyalty &amp; Rewards</button>
				</div>
				
				<form id="o100-portal-form" method="post">
					<?php wp_nonce_field('o100_save_portal', 'o100_portal_nonce'); ?>

					<!-- TAB 1: PORTAL & LAUNCHER -->
					<div id="pb-portal" class="o100-pb-pane active">
					
						<details class="o100-pb-accordion">
							<summary class="o100-pb-accordion-header">
								<h4>Launcher Customization</h4>
								<p style="margin:0;font-size:13px;color:#6b7280;font-weight:normal;">Configure the visibility, style, and icon of the launcher button.</p>
							</summary>
							<div class="o100-pb-accordion-body" style="padding-top:15px;">
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Visibility & Position</label>
								<div class="o100-radio-group" style="flex-wrap:wrap;">
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_position" value="bottom-right" class="o100-live-input" data-target-pos="#live-launcher-wrap" <?php checked($launcher_pos, 'bottom-right'); ?>><span class="card-content">Bottom Right</span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_position" value="bottom-left" class="o100-live-input" data-target-pos="#live-launcher-wrap" <?php checked($launcher_pos, 'bottom-left'); ?>><span class="card-content">Bottom Left</span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_position" value="middle-right" class="o100-live-input" data-target-pos="#live-launcher-wrap" <?php checked($launcher_pos, 'middle-right'); ?>><span class="card-content">Middle Right</span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_position" value="middle-left" class="o100-live-input" data-target-pos="#live-launcher-wrap" <?php checked($launcher_pos, 'middle-left'); ?>><span class="card-content">Middle Left</span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_position" value="hidden" class="o100-live-input" data-target-pos="#live-launcher-wrap" <?php checked($launcher_pos, 'hidden'); ?>><span class="card-content">Hidden (Trigger by link)</span></label>
								</div>
							</div>
						</div>
						
						<div class="o100-fluent-row" style="margin-top:10px;">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Display Style</label>
								<div class="o100-radio-group">
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_style" value="icon_only" class="o100-live-input" data-target-style="#live-sa-launcher, #live-launcher-wrap" <?php checked($launcher_style, 'icon_only'); ?>><span class="card-content">Icon Only</span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_style" value="icon_text" class="o100-live-input" data-target-style="#live-sa-launcher, #live-launcher-wrap" <?php checked($launcher_style, 'icon_text'); ?>><span class="card-content">Icon + Text</span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_style" value="text_only" class="o100-live-input" data-target-style="#live-sa-launcher, #live-launcher-wrap" <?php checked($launcher_style, 'text_only'); ?>><span class="card-content">Text Only</span></label>
								</div>
							</div>
						</div>
						
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-6">
								<label>Launcher Icon</label>
								<div class="o100-radio-group o100-icon-grid" style="flex-wrap:wrap; margin-bottom: 10px;">
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_icon" value="cart" class="o100-live-input" data-target-icon="#live-launcher-icon" <?php checked($launcher_icon, 'cart'); ?>><span class="card-content card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg></span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_icon" value="bag" class="o100-live-input" data-target-icon="#live-launcher-icon" <?php checked($launcher_icon, 'bag'); ?>><span class="card-content card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_icon" value="basket" class="o100-live-input" data-target-icon="#live-launcher-icon" <?php checked($launcher_icon, 'basket'); ?>><span class="card-content card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h2l3.6 7.59L6.25 13A2 2 0 008 16h12v-2H8l1.1-2h7.45a2 2 0 001.76-1.06L22 6H6.21"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_icon" value="gift" class="o100-live-input" data-target-icon="#live-launcher-icon" <?php checked($launcher_icon, 'gift'); ?>><span class="card-content card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg></span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_icon" value="star" class="o100-live-input" data-target-icon="#live-launcher-icon" <?php checked($launcher_icon, 'star'); ?>><span class="card-content card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_icon" value="crown" class="o100-live-input" data-target-icon="#live-launcher-icon" <?php checked($launcher_icon, 'crown'); ?>><span class="card-content card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg></span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_icon" value="custom_image" class="o100-live-input" data-target-icon="#live-launcher-icon" <?php checked($launcher_icon, 'custom_image'); ?>><span class="card-content card-icon" style="font-size:12px;font-weight:600;">Custom Icon</span></label>
								</div>
								
								<div id="o100-launcher-custom-img-wrap" style="display: <?php echo ($launcher_icon === 'custom_image') ? 'block' : 'none'; ?>; padding: 10px; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;">
									<input type="hidden" id="o100_portal_launcher_custom_image" name="o100_portal_launcher_custom_image" value="<?php echo esc_attr(isset($opts['o100_portal_launcher_custom_image']) ? $opts['o100_portal_launcher_custom_image'] : ''); ?>">
									<?php $custom_img_url = isset($opts['o100_portal_launcher_custom_image']) ? $opts['o100_portal_launcher_custom_image'] : ''; ?>
									<div id="o100-launcher-img-preview" style="display:<?php echo !empty($custom_img_url) ? 'flex' : 'none'; ?>; align-items:center; gap:10px; margin-bottom:8px; padding:6px; background:#fff; border:1px solid #e5e7eb; border-radius:6px;">
										<img id="o100-launcher-img-thumb" src="<?php echo esc_url($custom_img_url); ?>" style="width:36px; height:36px; border-radius:6px; object-fit:cover; flex-shrink:0;">
										<span style="flex:1; font-size:12px; color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" id="o100-launcher-img-name"><?php echo !empty($custom_img_url) ? basename($custom_img_url) : ''; ?></span>
										<button type="button" id="o100-remove-launcher-img-btn" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:16px; padding:2px 4px; line-height:1;" title="Remove">&times;</button>
									</div>
									<button type="button" id="o100-upload-launcher-img-btn" class="o100-btn-secondary" style="font-size:12px; padding:4px 10px; width:100%;"><?php echo !empty($custom_img_url) ? 'Change Image' : 'Select Image from Media Library'; ?></button>
								</div>
							</div>
							<div class="o100-fluent-col o100-fluent-col-6">
								<label>Button Shape</label>
								<div class="o100-radio-group">
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_shape" value="pill" class="o100-live-input" data-target-shape="#live-launcher-wrap" <?php checked($launcher_shape, 'pill'); ?>><span class="card-content">Circle / Pill</span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_shape" value="rounded" class="o100-live-input" data-target-shape="#live-launcher-wrap" <?php checked($launcher_shape, 'rounded'); ?>><span class="card-content">Rounded</span></label>
									<label class="o100-radio-card"><input type="radio" name="o100_portal_launcher_shape" value="square" class="o100-live-input" data-target-shape="#live-launcher-wrap" <?php checked($launcher_shape, 'square'); ?>><span class="card-content">Square</span></label>
								</div>
							</div>
						</div>
						
						<div class="o100-fluent-row" style="margin-top: 10px;">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Launcher Text</label>
								<input type="text" name="o100_portal_launcher_text" value="<?php echo esc_attr($launcher_text); ?>" class="o100-fluent-input o100-live-input" data-target="#live-launcher-text">
							</div>
						</div>

						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-4">
								<label>Edge Spacing Desktop</label>
								<?php
								self::render_input_group(array(
									'name'   => 'o100_portal_launcher_spacing',
									'value'  => $launcher_spacing,
									'type'   => 'number',
									'suffix' => 'px',
									'class'  => 'o100-live-input',
									'attrs'  => array(
										'data-var'    => '--launcher-spacing',
										'data-suffix' => 'px',
										'min'         => '0',
										'max'         => '100'
									)
								));
								?>
							</div>
							<div class="o100-fluent-col o100-fluent-col-4">
								<label>Edge Spacing Mobile</label>
								<?php
								self::render_input_group(array(
									'name'   => 'o100_portal_launcher_edge_mobile',
									'value'  => $launcher_mobile,
									'type'   => 'number',
									'suffix' => 'px',
									'attrs'  => array(
										'min' => '0',
										'max' => '100'
									)
								));
								?>
							</div>
							<div class="o100-fluent-col o100-fluent-col-4">
								<label>Smart Animations</label>
								<select name="o100_portal_launcher_animation" class="o100-fluent-input">
									<option value="none" <?php selected($launcher_anim, 'none'); ?>>No Animation</option>
									<option value="pulse" <?php selected($launcher_anim, 'pulse'); ?>>Pulse on Notification / Cart Items</option>
									<option value="bounce" <?php selected($launcher_anim, 'bounce'); ?>>Bounce on Load</option>
								</select>
							</div>
						</div>
						
						<div class="o100-fluent-row" style="margin-top:10px;">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label class="o100-settings-card-toggle">
									<div class="o100-sct-content">
										<div class="o100-sct-title">Integrate with Bottom App Menu on Mobile</div>
										<div class="o100-sct-desc">This will seamlessly integrate the "Rewards" trigger into the native mobile app menu bar on mobile devices.</div>
									</div>
									<div class="o100-sct-switch">
										<div class="o100-fluent-toggle">
											<input type="checkbox" name="o100_portal_mobile_integration" value="on" <?php checked($mobile_nav, 'on'); ?>>
											<span class="o100-toggle-slider" style="margin:0;"></span>
										</div>
									</div>
								</label>
							</div>
						</div>

							</div> <!-- End Accordion Body -->
						</details>

						<details class="o100-pb-accordion" style="margin-top:20px;">
							<summary class="o100-pb-accordion-header">
								<h4>Portal Design & Layout</h4>
								<p style="margin:0;font-size:13px;color:#6b7280;font-weight:normal;">Customize colors, dark mode, drawer styles, and layout dimensions.</p>
							</summary>
							<div class="o100-pb-accordion-body" style="padding-top:15px;">
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-6">
										<label>Theme Mode</label>
										<div style="display:flex; gap:10px;">
											<label class="o100-radio-card" style="flex:1;"><input type="radio" name="o100_portal_theme_mode" value="light" class="o100-live-input" data-target-class=".o100-pb-preview-device" data-prefix="mode-" <?php checked($theme_mode, 'light'); ?>><span class="card-content" style="padding:8px 0;font-size:13px;">Light</span></label>
											<label class="o100-radio-card" style="flex:1;"><input type="radio" name="o100_portal_theme_mode" value="dark" class="o100-live-input" data-target-class=".o100-pb-preview-device" data-prefix="mode-" <?php checked($theme_mode, 'dark'); ?>><span class="card-content" style="padding:8px 0;font-size:13px;">Dark</span></label>
										</div>
									</div>
									<div class="o100-fluent-col o100-fluent-col-6">
										<label>Panel Drawer Side</label>
										<div style="display:flex; gap:10px;">
											<label class="o100-radio-card" style="flex:1;"><input type="radio" name="o100_portal_drawer_side" value="right" <?php checked($drawer_side, 'right'); ?>><span class="card-content" style="padding:8px 0;font-size:13px;">Right Side</span></label>
											<label class="o100-radio-card" style="flex:1;"><input type="radio" name="o100_portal_drawer_side" value="left" <?php checked($drawer_side, 'left'); ?>><span class="card-content" style="padding:8px 0;font-size:13px;">Left Side</span></label>
										</div>
									</div>
								</div>

								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-6">
										<label>Close Button Style</label>
										<div style="display:flex; gap:10px;">
											<label class="o100-radio-card" style="flex:1;"><input type="radio" name="o100_portal_close_btn_style" value="inside" <?php checked($close_btn, 'inside'); ?>><span class="card-content" style="padding:8px 0;font-size:13px;">Inside Header</span></label>
											<label class="o100-radio-card" style="flex:1;"><input type="radio" name="o100_portal_close_btn_style" value="outside" <?php checked($close_btn, 'outside'); ?>><span class="card-content" style="padding:8px 0;font-size:13px;">Floating Outside</span></label>
										</div>
									</div>
									<div class="o100-fluent-col o100-fluent-col-6">
										<label>Backdrop Overlay</label>
										<div style="display:flex; gap:10px;">
											<label class="o100-radio-card" style="flex:1;"><input type="radio" name="o100_portal_backdrop_overlay" value="none" class="o100-live-input" data-target-class=".o100-pb-preview-device" data-prefix="backdrop-" <?php checked($backdrop, 'none'); ?>><span class="card-content" style="padding:8px 0;font-size:13px;">None</span></label>
											<label class="o100-radio-card" style="flex:1;"><input type="radio" name="o100_portal_backdrop_overlay" value="dark" class="o100-live-input" data-target-class=".o100-pb-preview-device" data-prefix="backdrop-" <?php checked($backdrop, 'dark'); ?>><span class="card-content" style="padding:8px 0;font-size:13px;">Dark</span></label>
											<label class="o100-radio-card" style="flex:1;"><input type="radio" name="o100_portal_backdrop_overlay" value="glass" class="o100-live-input" data-target-class=".o100-pb-preview-device" data-prefix="backdrop-" <?php checked($backdrop, 'glass'); ?>><span class="card-content" style="padding:8px 0;font-size:13px;">Glass</span></label>
										</div>
									</div>
								</div>

								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-6">
										<label>Panel Width Desktop</label>
										<?php
										self::render_input_group(array(
											'name'   => 'o100_portal_panel_width',
											'value'  => $panel_width,
											'type'   => 'number',
											'suffix' => 'px',
											'class'  => 'o100-live-input',
											'attrs'  => array('data-var' => '--panel-width', 'data-suffix' => 'px', 'min' => '300', 'max' => '800')
										));
										?>
									</div>
									<div class="o100-fluent-col o100-fluent-col-6">
										<label>Panel Border Radius</label>
										<?php
										self::render_input_group(array(
											'name'   => 'o100_portal_border_radius',
											'value'  => $border_radius,
											'type'   => 'number',
											'suffix' => 'px',
											'class'  => 'o100-live-input',
											'attrs'  => array('data-var' => '--border-radius', 'data-suffix' => 'px', 'min' => '0', 'max' => '40')
										));
										?>
									</div>
								</div>
								
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-12">
										<label>Portal Logo (Optional)</label>
										<div class="o100-flex-input-wrap has-suffix w-full">
											<input type="text" id="o100_portal_logo" name="o100_portal_logo" value="<?php echo esc_attr($logo_url); ?>" class="o100-modal-input" placeholder="Image URL...">
											<button type="button" class="o100-flex-suffix" id="o100-upload-logo-btn" style="cursor:pointer; background:#f8fafc; color:#0f172a; border-left:1px solid #cbd5e1 !important; transition:0.2s;">Upload</button>
										</div>
									</div>
								</div>
								
								<div class="o100-fluent-row" style="margin-top:10px;">
									<div class="o100-fluent-col" style="flex: 1 1 100px;">
										<label>Brand Accent Color</label>
										<input type="color" name="o100_portal_theme_color" value="<?php echo esc_attr($theme_color); ?>" class="o100-live-input o100-fluent-input" data-var="--theme-color" style="padding:2px; height:38px;">
									</div>
									<div class="o100-fluent-col" style="flex: 1 1 100px;">
										<label>Button Text</label>
										<input type="color" name="o100_portal_btn_text_color" value="<?php echo esc_attr($btn_text_color); ?>" class="o100-live-input o100-fluent-input" data-var="--btn-text-color" style="padding:2px; height:38px;">
									</div>
									<div class="o100-fluent-col" style="flex: 1 1 100px;">
										<label>Header BG</label>
										<input type="color" name="o100_portal_bg_color" value="<?php echo esc_attr($bg_color); ?>" class="o100-live-input o100-fluent-input" data-var="--bg-color" style="padding:2px; height:38px;">
									</div>
									<div class="o100-fluent-col" style="flex: 1 1 100px;">
										<label>Global Text</label>
										<input type="color" name="o100_portal_text_color" value="<?php echo esc_attr($text_color); ?>" class="o100-live-input o100-fluent-input" data-var="--text-color" style="padding:2px; height:38px;">
									</div>
								</div>
							</div>
						</details>
						
						<details class="o100-pb-accordion">
							<summary class="o100-pb-accordion-header">
								<h4>Advanced (Developer)</h4>
								<p style="margin:0;font-size:13px;color:#6b7280;font-weight:normal;">Advanced technical settings for custom development.</p>
							</summary>
							<div class="o100-pb-accordion-body" style="padding-top:15px;">
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-4">
										<label>Z-Index</label>
										<input type="number" name="o100_portal_z_index" value="<?php echo esc_attr($z_index); ?>" class="o100-fluent-input">
									</div>
									<div class="o100-fluent-col o100-fluent-col-4">
										<label>Enable "My Account" Tab</label>
										<div class="o100-fluent-toggle" style="margin-top: 8px;">
											<input type="checkbox" name="o100_portal_enabled_tabs[]" value="account" <?php checked(in_array('account', $enabled_tabs)); ?>>
											<span class="o100-toggle-slider" style="margin:0;"></span>
										</div>
									</div>
									<div class="o100-fluent-col o100-fluent-col-4">
										<label>"My Account" Tab Label</label>
										<input type="text" name="o100_portal_tab_label_account" value="<?php echo esc_attr($tab_account); ?>" class="o100-fluent-input o100-live-input" data-target="#live-tab-account" placeholder="Account">
									</div>
								</div>
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-12">
										<label>Custom CSS</label>
										<textarea name="o100_portal_custom_css" class="o100-fluent-input" rows="4" placeholder="/* Enter custom CSS here. Example: .o100-portal { margin-top: 20px; } */"><?php echo esc_textarea($custom_css); ?></textarea>
									</div>
								</div>
							</div>
						</details>

					</div> <!-- /pb-portal -->
					
					<!-- TAB 2: SHOPPING CART -->
					<div id="pb-cart" class="o100-pb-pane" style="display:none;">
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Tab Label</label>
								<input type="text" name="o100_portal_tab_label_cart" value="<?php echo esc_attr($tab_cart); ?>" class="o100-fluent-input o100-live-input" data-target="#live-tab-cart">
							</div>
						</div>
						
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Empty Cart Message</label>
								<input type="text" name="o100_portal_cart_empty_text" value="<?php echo esc_attr($cart_empty_text); ?>" class="o100-fluent-input">
							</div>
						</div>
						
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Continue Shopping Button Text</label>
								<input type="text" name="o100_portal_cart_continue_btn_text" value="<?php echo esc_attr($cart_continue_btn); ?>" class="o100-fluent-input">
							</div>
						</div>
						
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label>Checkout Button Text</label>
								<input type="text" name="o100_portal_cart_checkout_text" value="<?php echo esc_attr($cart_checkout_text); ?>" class="o100-fluent-input">
							</div>
						</div>
						
						<div class="o100-fluent-row" style="margin-top:10px;">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label class="o100-settings-card-toggle">
									<div class="o100-sct-content">
										<div class="o100-sct-title">Show Upsell / Cross-sell Recommendations</div>
										<div class="o100-sct-desc">Display recommended items to customers before they checkout.</div>
									</div>
									<div class="o100-sct-switch">
										<div class="o100-fluent-toggle">
											<input type="checkbox" name="o100_portal_cart_show_upsell" value="yes" <?php checked($cart_show_upsell, 'yes'); ?>>
											<span class="o100-toggle-slider" style="margin:0;"></span>
										</div>
									</div>
								</label>
							</div>
						</div>
						
						<div class="o100-fluent-row">
							<div class="o100-fluent-col o100-fluent-col-12">
								<label class="o100-settings-card-toggle">
									<div class="o100-sct-content">
										<div class="o100-sct-title">Show Promotional Discount Cards</div>
										<div class="o100-sct-desc">Highlight available promotions or discounts in the cart.</div>
									</div>
									<div class="o100-sct-switch">
										<div class="o100-fluent-toggle">
											<input type="checkbox" name="o100_portal_cart_show_promo" value="yes" <?php checked($cart_show_promo, 'yes'); ?>>
											<span class="o100-toggle-slider" style="margin:0;"></span>
										</div>
									</div>
								</label>
							</div>
						</div>
					</div> <!-- /pb-cart -->
					
					<!-- TAB 3: LOYALTY & REWARDS -->
					<div id="pb-loyalty" class="o100-pb-pane" style="display:none;">
						<?php
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
								<p class="description" style="margin:4px 0 0; color:#a16207;">You haven&rsquo;t created any loyalty campaigns yet. The Rewards widget will <strong>not be visible</strong> to customers until at least one active Earn or Redeem campaign exists.</p>
								<a href="<?php echo admin_url('admin.php?page=order100&tab=loyalty'); ?>" style="display:inline-block; margin-top:8px; font-size:13px; font-weight:600; color:#F59322; text-decoration:none;">&rarr; Go to Loyalty Campaigns</a>
							</div>
						</div>
						<?php else : ?>
						<div style="margin-bottom:20px; padding:12px 20px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; display:flex; align-items:center; gap:10px;">
							<span style="font-size:18px;">&#x2705;</span>
							<span style="font-size:13px; color:#166534;"><strong><?php echo $active_count; ?> active campaign<?php echo $active_count > 1 ? 's' : ''; ?></strong> detected &mdash; Loyalty widget is visible to customers.</span>
						</div>
						<?php endif; ?>

						<!-- GENERAL SETTINGS -->
						<details class="o100-pb-accordion" open>
							<summary class="o100-pb-accordion-header">
								<h4>General Settings</h4>
								<p style="margin:0;font-size:13px;color:#6b7280;font-weight:normal;">Configure the loyalty tab visibility and label.</p>
							</summary>
							<div class="o100-pb-accordion-body" style="padding-top:15px;">
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-12">
								<label style="display:flex; justify-content:space-between; align-items:center; cursor:pointer; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
									<div>
										<strong style="display:block; font-size:14px; color:#0f172a; margin-bottom:4px;">Enable Rewards & Loyalty Module</strong>
										<span style="font-size:13px; color:#64748b;">Show the Rewards tab in the portal.</span>
									</div>
									<div style="margin-left: 16px;">
										<div class="o100-fluent-toggle">
											<input type="checkbox" name="o100_portal_enabled_tabs[]" value="rewards" <?php checked(in_array('rewards', $enabled_tabs)); ?>>
											<span class="o100-toggle-slider" style="margin:0;"></span>
										</div>
									</div>
								</label>
									</div>
								</div>
								<div class="o100-fluent-row" style="margin-top:10px;">
									<div class="o100-fluent-col o100-fluent-col-12">
										<label>Tab Label</label>
										<input type="text" name="o100_portal_tab_label_rewards" value="<?php echo esc_attr($tab_rewards); ?>" class="o100-fluent-input o100-live-input" data-target="#live-tab-rewards">
									</div>
								</div>
							</div>
						</details>

						<!-- GUEST VIEW -->
						<details class="o100-pb-accordion">
							<summary class="o100-pb-accordion-header">
								<h4>Guest View Text</h4>
								<p style="margin:0;font-size:13px;color:#6b7280;font-weight:normal;">What non-logged in users will see.</p>
							</summary>
							<div class="o100-pb-accordion-body" style="padding-top:15px;">
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-12">
										<label>Welcome Title</label>
										<input type="text" name="o100_portal_guest_title" value="<?php echo esc_attr($guest_title); ?>" class="o100-fluent-input o100-live-input" data-target="#live-guest-title">
									</div>
								</div>
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-12">
										<label>Subtitle</label>
										<textarea name="o100_portal_guest_subtitle" class="o100-fluent-input o100-live-input" data-target="#live-guest-sub" rows="3"><?php echo esc_textarea($guest_sub); ?></textarea>
									</div>
								</div>
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-12">
										<label>Join Button Text</label>
										<input type="text" name="o100_portal_guest_btn_text" value="<?php echo esc_attr($guest_btn); ?>" class="o100-fluent-input o100-live-input" data-target="#live-guest-btn">
									</div>
								</div>
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-6">
										<label>Earn Points Label</label>
										<input type="text" name="o100_portal_guest_earn_text" value="<?php echo esc_attr($guest_earn); ?>" class="o100-fluent-input o100-live-input" data-target="#live-guest-earn">
									</div>
									<div class="o100-fluent-col o100-fluent-col-6">
										<label>Redeem Rewards Label</label>
										<input type="text" name="o100_portal_guest_redeem_text" value="<?php echo esc_attr($guest_redeem); ?>" class="o100-fluent-input o100-live-input" data-target="#live-guest-redeem">
									</div>
								</div>
								
								<h5 style="margin:24px 0 16px; padding-bottom:8px; border-bottom:1px solid #e2e8f0; font-size:14px; font-weight:600; color:#334155;">Referral Block</h5>
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-12">
								<label style="display:flex; justify-content:space-between; align-items:center; cursor:pointer; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
									<div>
										<strong style="display:block; font-size:14px; color:#0f172a; margin-bottom:4px;">Enable Referral Block (Guest)</strong>
										<span style="font-size:13px; color:#64748b;">Show the referral block to non-logged in users.</span>
									</div>
									<div style="margin-left: 16px;">
										<div class="o100-fluent-toggle">
											<input type="checkbox" name="o100_portal_guest_referral_enabled" value="yes" class="o100-live-input-toggle-guest" <?php checked($guest_referral_enabled, 'yes'); ?>>
											<span class="o100-toggle-slider" style="margin:0;"></span>
										</div>
									</div>
								</label>
									</div>
								</div>
								<div class="o100-fluent-row" style="margin-top:10px;">
									<div class="o100-fluent-col o100-fluent-col-12">
										<label>Referral Title</label>
										<input type="text" name="o100_portal_guest_referral_title" value="<?php echo esc_attr($guest_referral_title); ?>" class="o100-fluent-input o100-live-input" data-target="#live-referral-title-guest">
									</div>
								</div>
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-12">
										<label>Referral Description</label>
										<textarea name="o100_portal_guest_referral_desc" class="o100-fluent-input o100-live-input" data-target="#live-referral-desc-guest" rows="3"><?php echo esc_textarea($guest_referral_desc); ?></textarea>
									</div>
								</div>
							</div>
						</details>
						
						<!-- MEMBER VIEW -->
						<details class="o100-pb-accordion">
							<summary class="o100-pb-accordion-header">
								<h4>Member View Text</h4>
								<p style="margin:0;font-size:13px;color:#6b7280;font-weight:normal;">What logged-in members will see.</p>
							</summary>
							<div class="o100-pb-accordion-body" style="padding-top:15px;">
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
								
								<h5 style="margin:24px 0 16px; padding-bottom:8px; border-bottom:1px solid #e2e8f0; font-size:14px; font-weight:600; color:#334155;">Referral Block</h5>
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-12">
								<label style="display:flex; justify-content:space-between; align-items:center; cursor:pointer; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
									<div>
										<strong style="display:block; font-size:14px; color:#0f172a; margin-bottom:4px;">Enable Referral Block (Member)</strong>
										<span style="font-size:13px; color:#64748b;">Show the referral block to logged in members.</span>
									</div>
									<div style="margin-left: 16px;">
										<div class="o100-fluent-toggle">
											<input type="checkbox" name="o100_portal_member_referral_enabled" value="yes" class="o100-live-input-toggle-member" <?php checked($member_referral_enabled, 'yes'); ?>>
											<span class="o100-toggle-slider" style="margin:0;"></span>
										</div>
									</div>
								</label>
									</div>
								</div>
								<div class="o100-fluent-row" style="margin-top:10px;">
									<div class="o100-fluent-col o100-fluent-col-12">
										<label>Referral Title</label>
										<input type="text" name="o100_portal_member_referral_title" value="<?php echo esc_attr($member_referral_title); ?>" class="o100-fluent-input o100-live-input" data-target="#live-referral-title-member">
									</div>
								</div>
								<div class="o100-fluent-row">
									<div class="o100-fluent-col o100-fluent-col-12">
										<label>Referral Description</label>
										<textarea name="o100_portal_member_referral_desc" class="o100-fluent-input o100-live-input" data-target="#live-referral-desc-member" rows="3"><?php echo esc_textarea($member_referral_desc); ?></textarea>
									</div>
								</div>
							</div>
						</details>
					</div> <!-- /pb-loyalty -->
				</form>
			</div>

			
			<?php
			// Generate cards for preview
			$earn_cards_html = '';
			$redeem_cards_html = '';
			$referral_campaign = null;

			if ( true ) {
				// Earn Cards
				global $wpdb;
				$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';
				
				// Fetch Referral Campaign
				$referral_campaign = $wpdb->get_row( "SELECT * FROM {$campaigns_table} WHERE type = 'referral' AND status = 'active' LIMIT 1" );
				
				$earn_campaigns = $wpdb->get_results( "SELECT * FROM {$campaigns_table} WHERE type != 'points_conversion' AND type != 'punch_card' AND status = 'active' ORDER BY ordering ASC, id DESC" );
				if ( !empty($earn_campaigns) && is_array($earn_campaigns) ) {
					foreach ($earn_campaigns as $c) {
						$action_type = $c->type;
						$ui = isset($c->earn_config) ? (is_string($c->earn_config) ? json_decode($c->earn_config, true) : (array)$c->earn_config) : [];
						
						$icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#f3f4f6; color:#6b7280;">⭐</div>';
						if (strpos($action_type, 'point') !== false || strpos($action_type, 'order') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fff7ed; color:#F59322;">$</div>';
						elseif (strpos($action_type, 'signup') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fef3c7; color:#d97706;">+</div>';
						elseif (strpos($action_type, 'birthday') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fce7f3; color:#db2777;">🎁</div>';
						elseif (strpos($action_type, 'share') !== false || strpos($action_type, 'social') !== false || strpos($action_type, 'referral') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fff7ed; color:#F59322;">🔗</div>';
						elseif (strpos($action_type, 'review') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#d1fae5; color:#059669;">📝</div>';
						
						$title = $c->title;
						if (empty($title)) $title = $c->name;
						$point_val = (int)($ui['earn_point'] ?? 0);
						$desc = '';
						$short_desc = '';
						
						if ($action_type === 'birthday') {
							$point_rule = isset($c->point_rule) ? (is_string($c->point_rule) ? json_decode($c->point_rule, true) : (array)$c->point_rule) : [];
							if (!empty($point_rule['birthday_message'])) $desc = $point_rule['birthday_message'];
						}
						
						if (empty($desc)) {
							if ($action_type === 'signup') $desc = "Earn " . $point_val . " points by creating an account.";
							elseif ($action_type === 'birthday') $desc = "Celebrate your special day with a reward.";
							elseif ($action_type === 'product_review' || $action_type === 'review') $desc = sprintf("Leave a review for purchased items to get %d points.", $point_val);
							elseif (in_array($action_type, ['facebook_share', 'twitter_share', 'whatsapp_share', 'email_share'])) $desc = "Share our store on social media to get rewarded.";
							elseif ($action_type === 'followup_share') $desc = "Follow our social pages to get rewarded.";
							elseif ($point_val > 0) $desc = "Earn " . $point_val . " points for this activity.";
							else $desc = !empty($ui['campaign_message']) ? wp_strip_all_tags($ui['campaign_message']) : "Unlock special rewards and offers by completing this activity.";
						}
						
						if (empty($short_desc)) {
							if ($action_type === 'signup') $short_desc = sprintf("Earn %d points.", $point_val);
							elseif ($action_type === 'birthday') $short_desc = "Get a special reward.";
							elseif ($action_type === 'product_review' || $action_type === 'review') $short_desc = sprintf("Get %d points.", $point_val);
							elseif (in_array($action_type, ['facebook_share', 'twitter_share', 'whatsapp_share', 'email_share'])) $short_desc = "Share and get rewarded.";
							elseif ($action_type === 'followup_share') $short_desc = "Follow us and get rewarded.";
							elseif ($point_val > 0) $short_desc = "Earn " . $point_val . " points.";
							else $short_desc = "Special reward inside.";
						}
						
						$right_arrow = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M9 18l6-6-6-6"/></svg>';
						$earn_cards_html .= '<div class="o100-pbp-card" onclick="o100OpenDetail(\'earn\', \''.rawurlencode(wp_strip_all_tags($title)).'\', \''.rawurlencode(wp_strip_all_tags($desc)).'\', \''.rawurlencode($icon_html).'\', \''.$point_val.'\')" style="background:#fff; border:1px solid #e5e7eb; border-radius:var(--border-radius); padding:16px; margin-bottom:12px; display:flex; align-items:center; gap:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02); cursor:pointer;">'. $icon_html .'<div style="flex:1;"><h4 style="margin:0 0 4px; font-size:14px; color:var(--text-color); font-weight:700;">'.esc_html(wp_strip_all_tags($title)).'</h4><p style="margin:0; font-size:12px; color:#6b7280;">'.esc_html(wp_strip_all_tags($short_desc)).'</p></div>' . $right_arrow . '</div>';
					}
				}

				// Redeem Cards
				$o100_settings = class_exists('O100_Loyalty_DB') ? O100_Loyalty_DB::get_settings() : [];
				$conv_pts = isset($o100_settings['conversion_points']) ? intval($o100_settings['conversion_points']) : 0;
				$conv_val = isset($o100_settings['conversion_value']) ? floatval($o100_settings['conversion_value']) : 0;
				
				if ( $conv_pts > 0 && $conv_val > 0 ) {
					$icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#f3e8ff; color:#9333ea;">🔄</div>';
					$clean_price = html_entity_decode(wp_strip_all_tags(wc_price($conv_val)), ENT_QUOTES, 'UTF-8');
					$title = $clean_price . ' Off Discount';
					$desc = 'Use ' . $conv_pts . ' points to get a ' . wc_price($conv_val) . ' discount on your order.';
					$point_val = $conv_pts;
					
					$redeem_cards_html .= '<div class="o100-pbp-card" onclick="o100OpenDetail(\'redeem\', \''.rawurlencode(wp_strip_all_tags($title)).'\', \''.rawurlencode(wp_strip_all_tags($desc)).'\', \''.rawurlencode($icon_html).'\', \''.$point_val.'\')" style="background:#fff; border:1px solid #e5e7eb; border-radius:var(--border-radius); padding:16px; margin-bottom:12px; display:flex; align-items:center; gap:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02); cursor:pointer;">'. $icon_html .'<div style="flex:1;"><h4 style="margin:0 0 4px; font-size:14px; color:var(--text-color); font-weight:700;">'.esc_html(wp_strip_all_tags($title)).'</h4><p style="margin:0; font-size:12px; color:#6b7280;">'.esc_html($point_val.' points').'</p></div></div>';
				}
			}
			
			if ( empty($earn_cards_html) ) {
				$dummy_icon_1 = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fff7ed; color:#F59322;">$</div>';
				$dummy_icon_2 = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fef3c7; color:#d97706;">+</div>';
				$right_arrow = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M9 18l6-6-6-6"/></svg>';
				
				$earn_cards_html .= '<div class="o100-pbp-card" onclick="o100OpenDetail(\'earn\', \'Earn Points on Purchases\', \'Earn 5 points for every dollar spent.\', \''.rawurlencode($dummy_icon_1).'\', \'5\')" style="background:#fff; border:1px solid #e5e7eb; border-radius:var(--border-radius); padding:16px; margin-bottom:12px; display:flex; align-items:center; gap:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02); cursor:pointer;">'. $dummy_icon_1 .'<div style="flex:1;"><h4 style="margin:0 0 4px; font-size:14px; color:var(--text-color); font-weight:700;">Earn Points on Purchases</h4><p style="margin:0; font-size:12px; color:#6b7280;">Earn 5 points for every dollar spent. <span style="background:#fef3c7; color:#d97706; padding:2px 6px; border-radius:4px; font-size:10px; margin-left:4px;">Example</span></p></div>' . $right_arrow . '</div>';
				
				$earn_cards_html .= '<div class="o100-pbp-card" onclick="o100OpenDetail(\'earn\', \'Welcome Bonus\', \'Earn 500 points by creating an account.\', \''.rawurlencode($dummy_icon_2).'\', \'500\')" style="background:#fff; border:1px solid #e5e7eb; border-radius:var(--border-radius); padding:16px; margin-bottom:12px; display:flex; align-items:center; gap:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02); cursor:pointer;">'. $dummy_icon_2 .'<div style="flex:1;"><h4 style="margin:0 0 4px; font-size:14px; color:var(--text-color); font-weight:700;">Welcome Bonus</h4><p style="margin:0; font-size:12px; color:#6b7280;">Earn 500 points. <span style="background:#fef3c7; color:#d97706; padding:2px 6px; border-radius:4px; font-size:10px; margin-left:4px;">Example</span></p></div>' . $right_arrow . '</div>';
			}
			if ( empty($redeem_cards_html) ) {
				$dummy_icon_r = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#f3e8ff; color:#9333ea;">🔄</div>';
				$redeem_cards_html .= '<div class="o100-pbp-card" onclick="o100OpenDetail(\'redeem\', \'$5.00 Off Discount\', \'Use 1000 points to get a $5.00 discount on your order.\', \''.rawurlencode($dummy_icon_r).'\', \'1000\')" style="background:#fff; border:1px solid #e5e7eb; border-radius:var(--border-radius); padding:16px; margin-bottom:12px; display:flex; align-items:center; gap:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02); cursor:pointer;">'. $dummy_icon_r .'<div style="flex:1;"><h4 style="margin:0 0 4px; font-size:14px; color:var(--text-color); font-weight:700;">$5.00 Off Discount</h4><p style="margin:0; font-size:12px; color:#6b7280;">1000 points <span style="background:#fef3c7; color:#d97706; padding:2px 6px; border-radius:4px; font-size:10px; margin-left:4px;">Example</span></p></div></div>';
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
				<div class="o100-pb-preview-device mode-<?php echo esc_attr($theme_mode); ?> backdrop-<?php echo esc_attr($backdrop); ?>" style="
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
							<div class="o100-pbp-tab o100-pbp-tab-trigger <?php echo in_array('rewards', $enabled_tabs) ? '' : 'active'; ?>" data-target="cart"><span id="live-tab-cart"><?php echo esc_html($tab_cart); ?></span> <span class="o100-pbp-badge">2</span></div>
							<div class="o100-pbp-tab o100-pbp-tab-trigger <?php echo in_array('rewards', $enabled_tabs) ? 'active' : ''; ?>" data-target="rewards" <?php if(!in_array('rewards', $enabled_tabs)) echo 'style="display:none;"'; ?>><span id="live-tab-rewards"><?php echo esc_html($tab_rewards); ?></span></div>
						</div>
					</div>
					
					<div class="o100-pbp-body">
						<!-- REWARDS VIEW -->
						<div id="pbp-view-rewards" <?php if(!in_array('rewards', $enabled_tabs)) echo 'style="display:none;"'; ?>>
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
									<div class="o100-sc-inner-tab active" onclick="document.getElementById('guest-earn-cards').style.display='block'; document.getElementById('guest-redeem-cards').style.display='none'; this.parentElement.children[0].classList.add('active'); this.parentElement.children[1].classList.remove('active');"><span id="live-guest-earn"><?php echo esc_html($guest_earn); ?></span></div>
									<div class="o100-sc-inner-tab" onclick="document.getElementById('guest-earn-cards').style.display='none'; document.getElementById('guest-redeem-cards').style.display='block'; this.parentElement.children[0].classList.remove('active'); this.parentElement.children[1].classList.add('active');"><span id="live-guest-redeem"><?php echo esc_html($guest_redeem); ?></span></div>
								</div>
								
								<div class="o100-sc-tab-content-wrapper">
									<div id="guest-earn-cards">
										<?php echo $earn_cards_html; ?>
									</div>
									<div id="guest-redeem-cards" style="display:none;">
										<?php echo $redeem_cards_html; ?>
									</div>
								</div>
								
								<?php if ( !empty($referral_campaign) ) :
									if (!function_exists('o100_parse_referral_desc')) {
										function o100_parse_referral_desc($desc, $campaign) {
											if (empty($campaign)) return $desc;
											$old_default = 'Share the love of our restaurant! Your friend gets $5 off their first order of $50+, and you get rewarded when they finish their meal!';
											$point_rule = [];
											if (!empty($campaign->earn_config)) $point_rule = is_string($campaign->earn_config) ? json_decode($campaign->earn_config, true) : (array)$campaign->earn_config;
											elseif (!empty($campaign->point_rule)) $point_rule = is_string($campaign->point_rule) ? json_decode($campaign->point_rule, true) : (array)$campaign->point_rule;
											
											$get_txt = function($type, $amt, $cpn) {
												if ($type === 'point') return $amt . ' Points';
												if ($type === 'coupon' && !empty($cpn)) {
													if (strpos($cpn, 'promo_') === 0 && class_exists('O100_Promotions_DB')) {
														$promo = O100_Promotions_DB::get((int)str_replace('promo_', '', $cpn));
														if ($promo) {
															$cfg = json_decode($promo['action_config']??'{}', true);
															return ($cfg['discount_type']??'') === 'percentage' ? ($cfg['discount_value']??'').'% off' : '$'.($cfg['discount_value']??'').' off';
														}
													}
													if (strpos($cpn, 'wc_') === 0) { $wc = get_post((int)str_replace('wc_', '', $cpn)); if ($wc) return $wc->post_title; }
													if (strpos($cpn, 'REFERRAL_') === 0) {
														global $wpdb;
														$coupon = $wpdb->get_row($wpdb->prepare("SELECT post_title FROM {$wpdb->posts} WHERE post_type='shop_coupon' AND post_name=%s LIMIT 1", strtolower($cpn)));
														if ($coupon) return 'the ' . $coupon->post_title . ' coupon';
														return $cpn;
													}
													return 'a special discount';
												}
												return 'a reward';
											};
											
											$fri_type = $point_rule['friend']['campaign_type'] ?? ($point_rule['friend_type'] ?? 'point');
											$fri_amt  = $point_rule['friend']['earn_point'] ?? ($point_rule['friend_amount'] ?? 0);
											$fri_cpn  = $point_rule['friend']['earn_reward'] ?? ($point_rule['friend_coupon'] ?? '');
											
											$adv_type = $point_rule['advocate']['campaign_type'] ?? ($point_rule['advocate_type'] ?? 'point');
											$adv_amt  = $point_rule['advocate']['earn_point'] ?? ($point_rule['advocate_amount'] ?? 0);
											$adv_cpn  = $point_rule['advocate']['earn_reward'] ?? ($point_rule['advocate_coupon'] ?? '');

											$fri_txt = $get_txt($fri_type, $fri_amt, $fri_cpn);
											$adv_txt = $get_txt($adv_type, $adv_amt, $adv_cpn);
											if (trim($desc) === $old_default) {
												return "Share the love of our restaurant! Your friend gets {$fri_txt} on their first order, and you get {$adv_txt} when they finish their meal!";
											}
											return str_replace(['{friend_reward}', '{advocate_reward}'], [$fri_txt, $adv_txt], $desc);
										}
									}
									$preview_guest_ref = o100_parse_referral_desc($guest_referral_desc, $referral_campaign);
								?>
								<div class="o100-pbp-refer-box live-referral-block" style="margin-top:24px; padding:20px; border-radius:var(--border-radius); background:#fff7ed; color:#b06d04; text-align:center; <?php if($guest_referral_enabled!=='yes') echo 'display:none;'; ?>">
									<h4 id="live-referral-title-guest" style="margin:0 0 8px; font-size:16px; font-weight:800; color:#9a5c06;"><?php echo esc_html($guest_referral_title); ?></h4>
									<p id="live-referral-desc-guest" style="margin:0; font-size:13px; line-height:1.4; color:#d97b06;"><?php echo esc_html($preview_guest_ref); ?></p>
									<div style="margin-top:12px; font-size:12px;"><span style="color:#F59322; font-weight:600; cursor:pointer;">Sign in</span> to get your link</div>
								</div>
								<?php endif; ?>
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
								
								<?php if ( !empty($referral_campaign) ) :
									$preview_member_ref = o100_parse_referral_desc($member_referral_desc, $referral_campaign);
									$dummy_referral_url = site_url('/?o100_ref=123');
									$wll_content = get_option('wll_launcher_content_settings', []);
									$enabled_socials = isset($wll_content['content']['member']['referrals']['channels']) && is_array($wll_content['content']['member']['referrals']['channels']) ? $wll_content['content']['member']['referrals']['channels'] : ['whatsapp', 'email', 'facebook', 'twitter'];
								?>
								<div class="o100-pbp-refer-box live-referral-block" style="margin-top:24px; padding:20px; border-radius:var(--border-radius); background:#fff7ed; color:#b06d04; text-align:center; <?php if($member_referral_enabled!=='yes') echo 'display:none;'; ?>">
									<h4 id="live-referral-title-member" style="margin:0 0 8px; font-size:16px; font-weight:800; color:#9a5c06;"><?php echo esc_html($member_referral_title); ?></h4>
									<p id="live-referral-desc-member" style="margin:0 0 16px; font-size:13px; line-height:1.4; color:#d97b06;"><?php echo esc_html($preview_member_ref); ?></p>
									<div style="display:flex; gap:0; border-radius:6px; overflow:hidden; border:1px solid #c7d2fe; margin-bottom:12px;">
										<input type="text" value="<?php echo esc_attr($dummy_referral_url); ?>" readonly style="flex:1; border:none; padding:8px 12px; font-size:13px; background:#fff; color:#F59322; outline:none; margin:0; width:100%;">
										<button style="border:none; background:#F59322; color:#fff; font-weight:600; padding:0 12px; cursor:pointer;" onclick="event.preventDefault();">Copy</button>
									</div>
									<div style="display:flex; gap:12px; justify-content:center;">
										<?php if (in_array('whatsapp', $enabled_socials)) : ?>
										<span style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:#F59322;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg></span>
										<?php endif; ?>
										<?php if (in_array('email', $enabled_socials)) : ?>
										<span style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:#F59322;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></span>
										<?php endif; ?>
										<?php if (in_array('facebook', $enabled_socials)) : ?>
										<span style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:#F59322;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></span>
										<?php endif; ?>
										<?php if (in_array('twitter', $enabled_socials) || in_array('x', $enabled_socials)) : ?>
										<span style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:#F59322;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path></svg></span>
										<?php endif; ?>
										<?php if (in_array('linkedin', $enabled_socials)) : ?>
										<span style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:#F59322;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg></span>
										<?php endif; ?>
									</div>
								</div>
								<?php endif; ?>
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
						<div id="pbp-view-cart" <?php if(in_array('rewards', $enabled_tabs)) echo 'style="display:none;"'; ?>>
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
								<!-- Upsell Area (Visible Preview) -->
								<div class="live-cart-upsell" style="margin:0 -12px; padding:12px; border-top:6px solid #f5f5f5; border-bottom:6px solid #f5f5f5; <?php echo $cart_show_upsell !== 'yes' ? 'display:none;' : ''; ?>">
									<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
										<span style="font-size:14px; font-weight:700; color:#111;">Offers for you</span>
										<div style="display:flex; gap:4px;">
											<span style="width:24px; height:24px; min-width:24px; min-height:24px; padding:0; box-sizing:border-box; border:1px solid #e5e7eb; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; line-height:1; color:#6b7280; flex-shrink:0;">‹</span>
											<span style="width:24px; height:24px; min-width:24px; min-height:24px; padding:0; box-sizing:border-box; border:1px solid #e5e7eb; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; line-height:1; color:#6b7280; flex-shrink:0;">›</span>
										</div>
									</div>
									<div style="display:flex; gap:10px;">
										<div class="o100-sc-upsell-card" style="width:220px; display:flex; justify-content:space-between; align-items:center; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; text-align:left; flex-shrink:0;">
											<div style="flex:1; padding-right:12px;">
												<div style="font-size:13px; font-weight:600; color:#111; line-height:1.3; margin-bottom:4px;">Shanghai Noodles</div>
												<div style="font-size:12px; color:#6b7280; font-weight:500;">$18.34</div>
											</div>
											<div style="width:64px; height:64px; border-radius:8px; background:#f9fafb; position:relative; flex-shrink:0;">
												<div style="position:absolute; right:-6px; bottom:-6px; width:24px; height:24px; border-radius:50%; background:#fff; border:1px solid #e5e7eb; color:var(--theme-color); font-size:18px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,.08);">+</div>
											</div>
										</div>
									</div>
								</div>
								
								<!-- Promo Area (Visible Preview) -->
								<div class="live-cart-promo" style="margin:12px 0; <?php echo $cart_show_promo !== 'yes' ? 'display:none;' : ''; ?>">
									<div style="background:#fff0f2; border:1px solid #ffe4e6; border-radius:8px; padding:12px; display:flex; align-items:center; gap:10px;">
										<div style="width:12px; height:12px; border-radius:50%; background:#e11d48; flex-shrink:0;"></div>
										<span style="color:#e11d48; font-size:13px; font-weight:500;">Add $14.01 more to reach the $50.00 minimum order to checkout.</span>
									</div>
								</div>

								<div style="display:flex; justify-content:space-between; font-weight:700; font-size:16px; margin-bottom:16px;">
									<span>Total</span>
									<span>$24.97</span>
								</div>
								<div class="live-cart-checkout-btn" style="background:var(--theme-color); color:var(--btn-text-color); padding:14px; border-radius:10px; text-align:center; font-weight:700; font-size:14px; cursor:pointer;"><?php echo esc_html($cart_checkout_text); ?></div>
							</div>
						</div>
					</div>
					
					<!-- LAUNCHER -->
					<div id="live-launcher-wrap" class="o100-pbp-launcher <?php echo esc_attr($launcher_style . ' shape-' . $launcher_shape . ' pos-' . $launcher_pos); ?>">
						<div id="live-launcher-icon">
							<?php 
							if ($launcher_icon === 'custom_image' && !empty($opts['o100_portal_launcher_custom_image'])) {
							echo '<img src="' . esc_url($opts['o100_portal_launcher_custom_image']) . '" style="width:20px;height:20px;border-radius:50%;object-fit:cover;">';
						} elseif ($launcher_icon === 'bag') {
							echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>';
						} elseif ($launcher_icon === 'basket') {
							echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h2l3.6 7.59L6.25 13A2 2 0 008 16h12v-2H8l1.1-2h7.45a2 2 0 001.76-1.06L22 6H6.21"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>';
						} elseif ($launcher_icon === 'gift') {
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
							$sa_icon_svgs = array(
								'cart'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>',
								'bag'    => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>',
								'basket' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h2l3.6 7.59L6.25 13A2 2 0 008 16h12v-2H8l1.1-2h7.45a2 2 0 001.76-1.06L22 6H6.21"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>',
								'gift'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>',
								'star'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>',
								'crown'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>',
							);
							if ($launcher_icon === 'custom_image' && !empty($opts['o100_portal_launcher_custom_image'])) {
								echo '<img src="' . esc_url($opts['o100_portal_launcher_custom_image']) . '" style="width:20px;height:20px;border-radius:50%;object-fit:cover;">';
							} else {
								echo isset($sa_icon_svgs[$launcher_icon]) ? $sa_icon_svgs[$launcher_icon] : $sa_icon_svgs['gift'];
							}
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
						<div class="live-cart-upsell" style="padding:16px 20px; border-top:6px solid #f5f5f5; <?php echo $cart_show_upsell !== 'yes' ? 'display:none;' : ''; ?>">
							<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
								<span style="font-size:14px; font-weight:700; color:#111;">Offers for you</span>
								<div style="display:flex; gap:4px;">
									<span style="width:24px; height:24px; min-width:24px; min-height:24px; padding:0; box-sizing:border-box; border:1px solid #e5e7eb; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; line-height:1; color:#6b7280; flex-shrink:0;">‹</span>
									<span style="width:24px; height:24px; min-width:24px; min-height:24px; padding:0; box-sizing:border-box; border:1px solid #e5e7eb; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; line-height:1; color:#6b7280; flex-shrink:0;">›</span>
								</div>
							</div>
									<div style="display:flex; gap:10px;">
										<div class="o100-sc-upsell-card" style="width:220px; display:flex; justify-content:space-between; align-items:center; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; text-align:left; flex-shrink:0;">
											<div style="flex:1; padding-right:12px;">
												<div style="font-size:13px; font-weight:600; color:#111; line-height:1.3; margin-bottom:4px;">Shanghai Noodles</div>
												<div style="font-size:12px; color:#6b7280; font-weight:500;">$18.34</div>
											</div>
											<div style="width:64px; height:64px; border-radius:8px; background:#f9fafb; position:relative; flex-shrink:0;">
												<div style="position:absolute; right:-6px; bottom:-6px; width:24px; height:24px; border-radius:50%; background:#fff; border:1px solid #e5e7eb; color:var(--theme-color); font-size:18px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,.08);">+</div>
											</div>
										</div>
									</div>
						</div>
						
						<!-- Promo Area -->
						<div class="live-cart-promo" style="padding:16px 20px; border-top:6px solid #f5f5f5; <?php echo $cart_show_promo !== 'yes' ? 'display:none;' : ''; ?>">
							<div style="background:#fff0f2; border:1px solid #ffe4e6; border-radius:8px; padding:12px; display:flex; align-items:center; gap:10px;">
								<div style="width:12px; height:12px; border-radius:50%; background:#e11d48; flex-shrink:0;"></div>
								<span style="color:#e11d48; font-size:13px; font-weight:500;">Add $14.01 more to reach the $50.00 minimum order to checkout.</span>
							</div>
						</div>
					</div>
					
					<!-- Footer -->
					<div style="padding:16px 20px; border-top:1px solid #e5e7eb; flex-shrink:0;">
						<div style="display:flex; justify-content:space-between; margin-bottom:12px;">
							<span style="font-size:15px; font-weight:600; color:#111;">Subtotal</span>
							<span style="font-size:15px; font-weight:800; color:#111;">$66.85</span>
						</div>
						<div class="live-cart-checkout-btn" style="background:var(--theme-color); color:var(--btn-text-color); padding:14px; border-radius:10px; text-align:center; font-weight:700; font-size:14px; cursor:pointer;"><?php echo esc_html($cart_checkout_text); ?></div>
					</div>
				</div>
				
				<!-- Cart FAB at middle-right -->
				<div id="live-launcher-wrap" class="o100-pbp-launcher shape-<?php echo esc_attr($launcher_shape); ?> pos-<?php echo esc_attr($launcher_pos); ?> <?php echo esc_attr($launcher_style); ?>" style="--theme-color:var(--theme-color);--btn-text-color:var(--btn-text-color);">
					<span id="live-launcher-icon" style="display:<?php echo $launcher_style === 'text_only' ? 'none' : 'flex'; ?>; align-items:center; justify-content:center;">
						<?php
						$icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>';
						if ($launcher_icon === 'bag') $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>';
						if ($launcher_icon === 'basket') $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h2l3.6 7.59L6.25 13A2 2 0 008 16h12v-2H8l1.1-2h7.45a2 2 0 001.76-1.06L22 6H6.21"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>';
						if ($launcher_icon === 'gift') $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>';
						if ($launcher_icon === 'star') $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
						if ($launcher_icon === 'crown') $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>';
						if ($launcher_icon === 'custom_image' && !empty($opts['o100_portal_launcher_custom_image'])) {
							$icon_svg = '<img src="' . esc_url($opts['o100_portal_launcher_custom_image']) . '" style="width:20px;height:20px;border-radius:50%;object-fit:cover;">';
						}
						echo $icon_svg;
						?>
					</span>
					<span id="live-launcher-text" style="display:<?php echo $launcher_style === 'icon_only' ? 'none' : 'block'; ?>; margin-left:8px; font-weight:600; font-size:14px; white-space:nowrap;"><?php echo esc_html($launcher_text); ?></span>
					<span class="o100-pbp-badge" style="position:absolute; top:-6px; right:-6px;">3</span>
				</div>
			</div>

			</div><!-- /o100-pb-preview -->
			<div class="o100-pb-preview-backdrop"></div>
		</div><!-- /o100-portal-builder -->

		<style>
		.o100-portal-builder { display:flex; gap:30px; margin-top:20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
		.o100-pb-config { flex:1; min-width:0; background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05); border:1px solid #e5e7eb; padding:24px; }
		.o100-pb-preview { width:380px; flex-shrink:0; position:sticky; top:40px; align-self:start; }
		
		.o100-pb-cat-tabs { display:flex; gap:0; margin-bottom:0; background:#f8fafc; border-radius:10px; padding:4px; border:1px solid #e5e7eb; }
		.o100-pb-cat { flex:1; display:flex; align-items:center; justify-content:center; gap:8px; background:none; border:none; padding:14px 20px; font-size:15px; font-weight:700; color:#6b7280; border-radius:8px; cursor:pointer; transition:all 0.2s ease; }
		.o100-pb-cat:hover { color:#111; background:rgba(255,255,255,0.6); }
		.o100-pb-cat.active { color:#F59322; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.08); }
		.o100-pb-cat svg { opacity:0.6; }
		.o100-pb-cat.active svg { opacity:1; stroke:#F59322; }
		.o100-pb-cat-pane { }
		.o100-pb-tabs { display:flex; gap:0; border-bottom:1px solid #e5e7eb; margin-bottom:24px; }
		.o100-pb-tab { background:none; border:none; padding:12px 20px; font-size:14px; font-weight:600; color:#6b7280; border-bottom:2px solid transparent; cursor:pointer; margin-bottom:-1px; }
		.o100-pb-tab:hover { color:#111; }
		.o100-pb-tab.active { color:#F59322; border-bottom-color:#F59322; }
		
		/* Fluent Grid & Form Layout */
		.o100-fluent-row { display:flex; flex-wrap:wrap; margin-left:-10px; margin-right:-10px; margin-bottom:16px; gap: 16px 0; }
		.o100-fluent-col { padding-left:10px; padding-right:10px; box-sizing:border-box; }
		.o100-fluent-col-12 { flex: 0 0 100%; max-width: 100%; }
		.o100-fluent-col-6 { flex: 1 1 260px; max-width: 100%; }
		.o100-fluent-col-4 { flex: 1 1 200px; max-width: 100%; }
		.o100-fluent-col-3 { flex: 1 1 150px; max-width: 100%; }
		.o100-pb-pane label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
		.o100-fluent-input { width:100%; box-sizing:border-box; height:38px; line-height:36px; padding:0 12px; font-size:14px; border:1px solid #d1d5db; border-radius:6px; background:#fff; color:#111; box-shadow:0 1px 2px rgba(0,0,0,0.05); }
		select.o100-fluent-input { padding:0 30px 0 12px; }
		.o100-fluent-input:focus { outline:none; border-color:#F59322; box-shadow:0 0 0 3px rgba(79,70,229,0.1); }
		input[type="color"].o100-live-input { width: 100%; height: 38px; padding: 2px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer; box-sizing: border-box; }
		
		
		/* Settings Card Toggle */
		.o100-settings-card-toggle { display: flex !important; justify-content: space-between !important; align-items: center !important; padding: 16px 20px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; cursor: pointer; transition: border-color 0.2s, box-shadow 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.02); margin: 0; }
		.o100-settings-card-toggle:hover { border-color: #d1d5db; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
		.o100-settings-card-toggle .o100-sct-content { flex: 1; padding-right: 20px; }
		.o100-settings-card-toggle .o100-sct-title { font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 4px; }
		.o100-settings-card-toggle .o100-sct-desc { font-size: 13px; color: #6b7280; font-weight: 400; line-height: 1.4; display: block; margin: 0; }
		.o100-settings-card-toggle .o100-sct-switch { flex-shrink: 0; position: relative; display: flex; align-items: center; }
		.o100-fluent-toggle { position: relative; display: inline-flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px; color: #4b5563; font-weight: 500; }
		.o100-fluent-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
		.o100-toggle-slider { position: relative; width: 44px; height: 24px; background-color: #d1d5db; border-radius: 24px; transition: .3s; flex-shrink: 0; }
		.o100-toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; border-radius: 50%; transition: .3s; }
		.o100-fluent-toggle input:checked + .o100-toggle-slider { background-color: #F59322; }
		.o100-fluent-toggle input:checked + .o100-toggle-slider:before { transform: translateX(20px); }
		
		/* Preview Controls */
		.o100-pbp-controls { background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:12px; display:flex; gap:20px; align-items:center; }
		.o100-pbp-control-group { display:flex; gap:10px; font-size:13px; font-weight:600; color:#4b5563; }
		
		/* Preview Device - Right Sidebar Style */
		.o100-pb-preview-device { background:#fff; border:1px solid #e5e7eb; height:680px; overflow:hidden; position:relative; display:flex; flex-direction:column; box-shadow:-8px 0 24px rgba(0,0,0,0.06); color:var(--text-color); border-radius: 8px; border-right: none; border-bottom:none; border-top:none; margin-left: auto; width: 100%; transition: background 0.3s, color 0.3s; }
		
		/* Dark Mode Preview Overrides */
		.o100-pb-preview-device.mode-dark { --bg-color: #1e293b !important; --text-color: #f8fafc !important; }
		.o100-pb-preview-device.mode-dark .o100-pbp-body { background: #0f172a !important; }
		.o100-pb-preview-device.mode-dark .o100-pbp-card { background: #1e293b !important; border-color: #334155 !important; }
		.o100-pb-preview-device.mode-dark .o100-pbp-card-info h4 { color: #f8fafc !important; }
		.o100-pb-preview-device.mode-dark .o100-pbp-card-info p { color: #94a3b8 !important; }
		
		/* Backdrop Overlay Preview Overrides */
		.o100-pb-preview-backdrop { position:absolute; top:0; left:0; right:0; bottom:0; z-index:90; display:none; pointer-events:none; }
		.o100-pb-preview-device.backdrop-dark .o100-pb-preview-backdrop { display:block; background:rgba(0,0,0,0.6); }
		.o100-pb-preview-device.backdrop-glass .o100-pb-preview-backdrop { display:block; background:rgba(255,255,255,0.2); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); }
		.o100-pb-preview-device.mode-dark.backdrop-glass .o100-pb-preview-backdrop { display:block; background:rgba(0,0,0,0.4); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); }

		.o100-pbp-header { background:var(--bg-color); border-bottom:1px solid #e5e7eb; padding:16px 20px 0; z-index:95; position:relative; }
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
		
		.o100-pbp-refer-box { margin-top:24px; padding:20px; border-radius:var(--border-radius); background:#fff7ed; color:#b06d04; text-align:center; }
		.o100-pbp-refer-box h4 { margin:0 0 8px; font-size:16px; font-weight:800; color:#9a5c06; }
		.o100-pbp-refer-box p { margin:0 0 16px; font-size:13px; line-height:1.4; color:#d97b06; }
		
		.o100-pbp-refer-link { display:flex; gap:0; border-radius:6px; overflow:hidden; border:1px solid #c7d2fe; margin-bottom:12px; }
		.o100-pbp-refer-link input { flex:1; border:none; padding:8px 12px; font-size:13px; background:#fff; color:#F59322; outline:none; }
		.o100-pbp-refer-link button { border:none; background:#F59322; color:#fff; font-weight:600; padding:0 12px; cursor:pointer; }
		.o100-pbp-refer-socials { display:flex; gap:12px; justify-content:center; }
		.o100-pbp-refer-socials span { display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:#F59322; cursor:pointer; }
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
		.o100-radio-card input[type="radio"]:checked + .card-content { border-color: #F59322; color: #F59322; background: #f5f3ff; box-shadow: 0 0 0 1px #F59322 inset; }
		.o100-radio-card input[type="radio"]:focus-visible + .card-content { outline: 2px solid #F59322; outline-offset: 2px; }
		.o100-radio-card .card-icon { display: flex; align-items: center; justify-content: center; }
		.o100-radio-card .card-icon svg { width: 20px; height: 20px; }
		
		.o100-icon-grid { gap: 8px; }
		.o100-icon-grid .o100-radio-card { flex: none; min-width: 48px; }
		
		/* Accordion UI */
		.o100-pb-accordion { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 15px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
		.o100-pb-accordion-header { padding: 15px 20px; background: #f9fafb; cursor: pointer; list-style: none; display: block; border-bottom: 1px solid transparent; transition: background 0.2s; }
		.o100-pb-accordion-header::-webkit-details-marker { display: none; }
		.o100-pb-accordion-header:hover { background: #f3f4f6; }
		.o100-pb-accordion[open] .o100-pb-accordion-header { border-bottom-color: #e5e7eb; background: #fff; }
		.o100-pb-accordion-header h4 { margin: 0 0 4px; font-size: 15px; font-weight: 700; color: #111; display: flex; align-items: center; justify-content: space-between; }
		.o100-pb-accordion-header h4::after { content: '\25BC'; font-size: 10px; color: #9ca3af; transition: transform 0.2s; }
		.o100-pb-accordion[open] .o100-pb-accordion-header h4::after { transform: rotate(180deg); }
		.o100-pb-accordion-body { padding: 0 20px 20px; }
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
				var mode = 'sidecart';
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
			
			// Live Preview Updates - Classes with prefix
			$('.o100-live-input[data-target-class]').on('change', function(){
				var prefix = $(this).data('prefix');
				var val = $(this).val();
				var $target = $($(this).data('target-class'));
				
				// remove existing prefixed classes
				var classes = ($target.attr('class') || '').split(' ');
				for(var i=0; i<classes.length; i++){
					if(classes[i].indexOf(prefix) === 0) {
						$target.removeClass(classes[i]);
					}
				}
				if (val !== 'none') {
					$target.addClass(prefix + val);
				}
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
				
				if (pos === 'hidden') {
					$target.addClass('hidden');
					$saTarget.addClass('hidden');
				} else {
					$target.removeClass('hidden');
					$saTarget.removeClass('hidden');
				}
			});

			$('.o100-live-input[data-target-style]').on('change', function(){
				var style = $(this).val();
				var $targets = $($(this).data('target-style'));
				$targets.removeClass('icon_only icon_text text_only').addClass(style);
				
				$targets.each(function(){
					var $saTarget = $(this);
					if (style === 'icon_only') {
						$saTarget.css({padding: 0, width: '48px', justifyContent: 'center'});
						$saTarget.find('#live-sa-launcher-text, #live-launcher-text').hide();
						$saTarget.find('#live-sa-launcher-icon, #live-launcher-icon').css('display', 'flex');
					} else if (style === 'text_only') {
						$saTarget.css({padding: '0 20px', width: 'auto', justifyContent: 'flex-start'});
						$saTarget.find('#live-sa-launcher-text, #live-launcher-text').show();
						$saTarget.find('#live-sa-launcher-icon, #live-launcher-icon').hide();
					} else {
						$saTarget.css({padding: '0 20px', width: 'auto', justifyContent: 'flex-start'});
						$saTarget.find('#live-sa-launcher-text, #live-launcher-text').show();
						$saTarget.find('#live-sa-launcher-icon, #live-launcher-icon').css('display', 'flex');
					}
				});
			});
			
			// Live Preview Updates - Icon
			$('.o100-live-input[data-target-icon]').on('change', function(){
				var icon = $(this).val();
				if (icon === 'custom_image') {
					$('#o100-launcher-custom-img-wrap').show();
					var imgUrl = $('#o100_portal_launcher_custom_image').val();
					if (imgUrl) {
						var svg = '<img src="' + imgUrl + '" style="width:20px;height:20px;border-radius:50%;object-fit:cover;">';
						$($(this).data('target-icon')).html(svg);
						$('#live-sa-launcher-icon').html(svg);
					}
					return;
				}
				
				$('#o100-launcher-custom-img-wrap').hide();
				var svg = '';
				if(icon === 'bag') svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>';
				else if(icon === 'basket') svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h2l3.6 7.59L6.25 13A2 2 0 008 16h12v-2H8l1.1-2h7.45a2 2 0 001.76-1.06L22 6H6.21"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>';
				else if(icon === 'gift') svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>';
				else if(icon === 'star') svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
				else if(icon === 'crown') svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>';
				else svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>';
				
				// Update both sidecart and standalone previews
				$($(this).data('target-icon')).html(svg);
				$('#live-sa-launcher-icon').html(svg);
			});
			
			// Custom Image Icon Upload
			var mediaIconUploader;
			$('#o100-upload-launcher-img-btn').click(function(e) {
				e.preventDefault();
				if (mediaIconUploader) { mediaIconUploader.open(); return; }
				mediaIconUploader = wp.media.frames.file_frame = wp.media({
					title: 'Choose Launcher Icon', button: { text: 'Choose Icon' }, multiple: false
				});
				mediaIconUploader.on('select', function() {
					var attachment = mediaIconUploader.state().get('selection').first().toJSON();
					$('#o100_portal_launcher_custom_image').val(attachment.url).trigger('change');
					
					// Update live preview icons
					var imgHtml = '<img src="' + attachment.url + '" style="width:20px;height:20px;border-radius:50%;object-fit:cover;">';
					$('#live-launcher-icon').html(imgHtml);
					$('#live-sa-launcher-icon').html(imgHtml);
					
					// Update thumbnail preview
					$('#o100-launcher-img-thumb').attr('src', attachment.url);
					$('#o100-launcher-img-name').text(attachment.filename || attachment.url.split('/').pop());
					$('#o100-launcher-img-preview').show();
					$('#o100-upload-launcher-img-btn').text('Change Image');
				});
				mediaIconUploader.open();
			});
			
			// Remove custom launcher image
			$('#o100-remove-launcher-img-btn').click(function(e) {
				e.preventDefault();
				$('#o100_portal_launcher_custom_image').val('').trigger('change');
				$('#o100-launcher-img-preview').hide();
				$('#o100-upload-launcher-img-btn').text('Select Image from Media Library');
				// Reset preview to default cart icon
				var defaultSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>';
				$('#live-launcher-icon').html(defaultSvg);
				$('#live-sa-launcher-icon').html(defaultSvg);
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
					$('#o100_portal_logo').val(attachment.url).trigger('change');
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
				$('.live-cart-checkout-btn').text($(this).val());
			});
			
			// Live Cart empty/continue texts update (dummy UI doesn't show empty cart, but we keep them for consistency)
			$('input[name="o100_portal_cart_empty_text"]').on('input', function(){
				// no live target for empty cart yet, but enable save button
				$('.o100-fluent-top-save').removeClass('o100-save-disabled');
			});
			
			$('input[name="o100_portal_cart_continue_btn_text"]').on('input', function(){
				// no live target for continue btn yet, but enable save button
				$('.o100-fluent-top-save').removeClass('o100-save-disabled');
			});
			
			// Live Cart upsell/promo toggle
			$('input[name="o100_portal_cart_show_upsell"]').on('change', function(){
				$('.live-cart-upsell').toggle($(this).is(':checked'));
				$('.o100-fluent-top-save').removeClass('o100-save-disabled');
			});
			$('input[name="o100_portal_cart_show_promo"]').on('change', function(){
				$('.live-cart-promo').toggle($(this).is(':checked'));
				$('.o100-fluent-top-save').removeClass('o100-save-disabled');
			});
			
			// Live Referral Toggle
			$('input[name="o100_portal_guest_referral_enabled"]').on('change', function(){
				$('#live-referral-title-guest').parent().toggle($(this).is(':checked'));
				$('.o100-fluent-top-save').removeClass('o100-save-disabled');
			});
			$('input[name="o100_portal_member_referral_enabled"]').on('change', function(){
				$('#live-referral-title-member').parent().toggle($(this).is(':checked'));
				$('.o100-fluent-top-save').removeClass('o100-save-disabled');
			});
			
			// Module Toggle
			$('input[name="o100_portal_enabled_tabs[]"]').on('change', function(){
				var isRewards = $(this).val() === 'rewards';
				if (isRewards) {
					var show = $(this).is(':checked');
					var $tabRewards = $('.o100-pbp-tab-trigger[data-target="rewards"]');
					if (show) {
						$tabRewards.show();
					} else {
						$tabRewards.hide();
						$('.o100-pbp-tab-trigger[data-target="cart"]').trigger('click');
					}
				}
				$('.o100-fluent-top-save').removeClass('o100-save-disabled');
			});
			
			// Form Submission Override
			$('.o100-fluent-top-save').off('click').on('click', function(e){
				// If we are on the portal tab
				if ($('#o100-portal-form').length) {
					e.preventDefault();
					var $btn = $(this);
					$btn.text('Saving...').prop('disabled', true).addClass('o100-saving');
					
					var data = $('#o100-portal-form').serialize() + '&action=o100_save_portal_settings';
					$.post(ajaxurl, data, function(res){
						$btn.text('Saved!');
						// Reset dirty snapshot
						if (typeof window.o100SerializePage === 'function') {
							window.o100FormSnapshot = window.o100SerializePage();
						}
						setTimeout(function(){
							$btn.text('Save Settings').prop('disabled', false).removeClass('o100-saving').addClass('o100-save-disabled');
						}, 2000);
					});
				}
			});
			
			// Detail View Navigation
			window.o100OpenDetail = function(type, title, desc, icon_html, points) {
				title = decodeURIComponent(title);
				desc = decodeURIComponent(desc);
				icon_html = decodeURIComponent(icon_html);
				
				$('#o100-detail-title').html(title);
				$('#o100-detail-desc').html(desc);
				$('#o100-detail-icon').html(icon_html);
				
				var btnText = type === 'redeem' ? 'Redeem for ' + points + ' Points' : 'Complete Activity';
				$('#o100-detail-btn').text(btnText);
				
				$('.o100-pbp-guest-view').hide();
				$('.o100-pbp-member-view').hide();
				$('#o100-sc-detail-view').fadeIn(200);
			};
			
			window.o100CloseDetail = function() {
				$('#o100-sc-detail-view').hide();
				if ($('input[name="pbp-auth"]:checked').val() === 'guest') {
					$('.o100-pbp-guest-view').fadeIn(200);
				} else {
					$('.o100-pbp-member-view').fadeIn(200);
				}
			};
		});
		</script>
		<?php
	}




}
