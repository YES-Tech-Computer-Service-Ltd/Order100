<?php
/**
 * REST API Controller for Settings (Tools) Module
 * Replaces old wp_ajax_ calls with standard REST endpoints.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class O100_Settings_API {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$namespace = 'o100/v1';

		register_rest_route( $namespace, '/settings/(?P<group>[a-zA-Z0-9_-]+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE, // POST, PUT, PATCH
				'callback'            => array( __CLASS__, 'update_settings_group' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			),
		) );
	}

	public static function check_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Unified endpoint to save settings based on the group (tab) passed
	 */
	public static function update_settings_group( WP_REST_Request $request ) {
		$group = $request->get_param( 'group' );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			// Fallback for form data if JSON wasn't parsed properly
			$params = $request->get_body_params();
		}

		switch ( $group ) {
			case 'profile':
				return self::save_store_profile( $params );
			case 'portal':
				return self::save_portal_settings( $params );
			case 'checkout_ext':
				return self::save_checkout_ext( $params );
			case 'delivery':
				return self::save_delivery( $params );
			case 'pickup':
				return self::save_pickup( $params );
			case 'misc':
				return self::save_misc( $params );
			case 'toggle_module':
				return self::toggle_module( $params );
			case 'toggle_locations':
				return self::toggle_locations( $params );
			default:
				return new WP_Error( 'invalid_group', 'Invalid settings group.', array( 'status' => 400 ) );
		}
	}

	private static function save_store_profile( $params ) {
		$data = array();
		if ( isset( $params['o100_store_profile'] ) && is_array( $params['o100_store_profile'] ) ) {
			foreach ( $params['o100_store_profile'] as $key => $value ) {
				if ( is_array( $value ) ) {
					$data[ sanitize_text_field( $key ) ] = array_map( 'sanitize_text_field', $value );
				} else {
					$data[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
				}
			}
		} else {
			// If flattened
			foreach ( $params as $key => $value ) {
				if ( is_array( $value ) ) {
					$data[ sanitize_text_field( $key ) ] = array_map( 'sanitize_text_field', $value );
				} else {
					$data[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
				}
			}
		}
		
		update_option( 'o100_store_profile', $data );
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Store profile saved successfully.', 'order100' ) ) );
	}

	private static function save_portal_settings( $params ) {
		$opts = get_option('o100_portal', array());
		
		// The fields from original ajax_save_portal_settings
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
			'o100_portal_custom_css'
		);
		
		foreach ( $fields as $f ) {
			if ( isset( $params[$f] ) ) {
				$opts[$f] = $f === 'o100_portal_custom_css' ? wp_strip_all_tags( wp_unslash( $params[$f] ) ) : sanitize_text_field( wp_unslash( $params[$f] ) );
			} else {
				$opts[$f] = '';
			}
		}

		$checkboxes = array(
			'o100_portal_enable', 'o100_portal_force_desktop', 'o100_portal_launcher_hide_mobile',
			'o100_portal_enable_rewards', 'o100_portal_enable_cart', 'o100_portal_enable_account'
		);
		foreach ( $checkboxes as $cb ) {
			$opts[$cb] = ! empty( $params[$cb] ) ? '1' : '';
		}
		
		update_option('o100_portal', $opts);
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Portal settings saved successfully.', 'order100' ) ) );
	}

	private static function save_checkout_ext( $params ) {
		$data = isset( $params['o100_checkout_ext'] ) ? $params['o100_checkout_ext'] : $params;
		$options = get_option( 'o100_options', array() );
		
		$data['o100_tip_control_initialized'] = '1';
		
		$checkboxes = array('o100_tip_delivery_enable', 'o100_tip_pickup_enable');
		foreach ($checkboxes as $cb) {
			$data[$cb] = !empty($data[$cb]) ? 'on' : '';
		}

		foreach ( $data as $key => $value ) {
			$options[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
		}

		update_option( 'o100_options', $options );
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Checkout settings saved successfully.', 'order100' ) ) );
	}

	private static function save_delivery( $params ) {
		// Because the frontend uses FormData and sends stringified keys if not mapped, 
		// we check if the nested o100_delivery is present or if we need to map from root params.
		$raw_data = isset( $params['o100_delivery'] ) ? $params['o100_delivery'] : $params;
		
		$data = array();
		foreach ( $raw_data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ sanitize_text_field( $key ) ] = array_map( 'sanitize_text_field', $value );
			} else {
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
			$data['o100_shp_km_loc'] = array_values($km_data);
		} else {
			$data['o100_shp_km_loc'] = array();
		}

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
			$data['o100_shp_zip_loc'] = array_values($zip_data);
		} else {
			$data['o100_shp_zip_loc'] = array();
		}

		update_option( 'o100_delivery', $data );
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Delivery settings saved successfully.', 'order100' ) ) );
	}

	private static function save_pickup( $params ) {
		$raw_data = isset( $params['o100_pickup'] ) ? $params['o100_pickup'] : $params;
		$data = array();
		foreach ( $raw_data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ sanitize_text_field( $key ) ] = array_map( 'sanitize_text_field', $value );
			} else {
				$data[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
			}
		}

		update_option( 'o100_pickup', $data );
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Pickup options saved successfully.', 'order100' ) ) );
	}

	private static function save_misc( $params ) {
		$raw_data = isset( $params['o100_misc'] ) ? $params['o100_misc'] : $params;
		$data = array();
		foreach ( $raw_data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ sanitize_text_field( $key ) ] = array_map( 'sanitize_text_field', $value );
			} else {
				$data[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
			}
		}

		update_option( 'o100_misc', $data );
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Misc settings saved successfully.', 'order100' ) ) );
	}

	private static function toggle_module( $params ) {
		if ( empty( $params['module'] ) || ! isset( $params['status'] ) ) {
			return new WP_Error( 'missing_params', 'Module and status are required.', array( 'status' => 400 ) );
		}
		
		$module = sanitize_text_field( $params['module'] );
		$status = rest_sanitize_boolean( $params['status'] );
		
		$modules = get_option( 'o100_active_modules', array() );
		if ( ! is_array( $modules ) ) {
			$modules = array();
		}
		
		$modules[ $module ] = $status;
		update_option( 'o100_active_modules', $modules );
		
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Module status updated.', 'order100' ) ) );
	}

	private static function toggle_locations( $params ) {
		if ( ! isset( $params['enabled'] ) ) {
			return new WP_Error( 'missing_params', 'Status is required.', array( 'status' => 400 ) );
		}
		
		$status = rest_sanitize_boolean( $params['enabled'] ) ? 'yes' : 'no';
		update_option( 'o100_multi_location_enabled', $status );
		
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Locations status updated.', 'order100' ) ) );
	}
}
