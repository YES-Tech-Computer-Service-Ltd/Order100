<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_App_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		
		// Initialize the new Order100 App APIs
		if ( class_exists( 'O100_App_API_Orders' ) ) {
			new O100_App_API_Orders();
		}
		if ( class_exists( 'O100_App_API_Catalog' ) ) {
			new O100_App_API_Catalog();
		}
		
		// Authenticate WooCommerce REST API requests (wc/v3) using Order100 App Bearer token
		add_filter( 'determine_current_user', array( $this, 'authenticate_via_bearer' ), 14 );
	}

	public function authenticate_via_bearer( $user_id ) {
		if ( ! empty( $user_id ) ) {
			return $user_id;
		}

		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$auth_header = $_SERVER['HTTP_AUTHORIZATION'];
		} elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
		} else {
			return $user_id;
		}

		if ( ! preg_match('/Bearer\s(\S+)/', $auth_header, $matches) ) {
			return $user_id;
		}

		$token = $matches[1];
		$devices = get_option( 'o100_devices', array() );
		
		foreach ( $devices as $id => $data ) {
			if ( isset( $data['token_hash'] ) && wp_check_password( $token, $data['token_hash'] ) ) {
				if ( ! empty( $data['user_id'] ) ) {
					return intval( $data['user_id'] );
				}
				break;
			}
		}

		return $user_id;
	}

	public function register_rest_routes() {
		// GET store status (emergency off)
		register_rest_route( 'order100/v1', '/store-status', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_store_status' ),
			'permission_callback' => '__return_true', // Rely on Basic Auth for WooCommerce API context if needed, or open if device is paired. For now open as pairing API.
		) );

		// GET store profile
		register_rest_route( 'order100/v1', '/app/store-profile', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_store_profile' ),
			'permission_callback' => '__return_true',
		) );

		// POST update store status
		register_rest_route( 'order100/v1', '/store-status', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'update_store_status' ),
			'permission_callback' => '__return_true', // Add auth logic if required by App
		) );

		// POST update stock
		register_rest_route( 'order100/v1', '/update-stock', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'update_stock' ),
			'permission_callback' => '__return_true', 
		) );
		
		// POST print receipt (Future feature: currently enforced limits)
		register_rest_route( 'order100/v1', '/print-receipt', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'print_receipt' ),
			'permission_callback' => '__return_true', 
		) );

		// Free version restrictions: block PRO-only App features
		if ( ! function_exists('O100_License') || ! O100_License()->is_premium() ) {
			add_filter( 'woocommerce_rest_pre_insert_shop_order_object', array( $this, 'intercept_app_order_updates' ), 10, 3 );
			add_filter( 'woocommerce_rest_orders_prepare_object_query', array( $this, 'limit_app_order_history' ), 10, 2 );
			add_filter( 'woocommerce_rest_pre_insert_product_object', array( $this, 'block_app_product_updates' ), 10, 3 );
			add_filter( 'woocommerce_rest_pre_insert_customer_object', array( $this, 'block_app_customer_updates' ), 10, 3 );
		}
	}
	
	// Free version restriction methods (only registered when not premium)
	public function block_app_product_updates( $product, $request, $creating ) {
		return new WP_Error( 'pro_required', 'Order100 Pro is required to manage products from the App.', array( 'status' => 403 ) );
	}
	
	public function block_app_customer_updates( $customer, $request, $creating ) {
		return new WP_Error( 'pro_required', 'Order100 Pro is required to manage customers from the App.', array( 'status' => 403 ) );
	}
	public function limit_app_order_history( $args, $request ) {
		$three_days_ago = date( 'Y-m-d\TH:i:s', strtotime( '-3 days' ) );
		
		if ( empty( $args['date_query'] ) ) {
			$args['date_query'] = array();
		}
		
		$args['date_query'][] = array(
			'column' => 'post_date',
			'after'  => $three_days_ago,
			'inclusive' => true,
		);
		
		return $args;
	}
	
	public function intercept_app_order_updates( $order, $request, $creating ) {
		if ( $creating ) return $order;

		$meta_data = $request->get_param( 'meta_data' );
		if ( is_array( $meta_data ) ) {
			foreach ( $meta_data as $meta ) {
				if ( isset( $meta['key'] ) && $meta['key'] === 'o100_prep_time' ) {
					return new WP_Error( 'pro_required', 'Order100 Pro is required to dynamically adjust preparation times from the App.', array( 'status' => 403 ) );
				}
			}
		}

		return $order;
	}

	public function get_store_profile( WP_REST_Request $request ) {
		$branch_id = $request->get_param( 'branch_id' );
		
		$profile = array(
			'name'    => get_bloginfo( 'name' ),
			'address' => '',
			'phone'   => '',
			'currency'=> get_woocommerce_currency(),
			'timezone'=> wp_timezone_string()
		);
		
		if ( ! empty( $branch_id ) && $branch_id !== 'all' ) {
			$loc_id = intval( $branch_id );
			$post = get_post( $loc_id );
			if ( $post && $post->post_type === 'o100_location' ) {
				$profile['name']    = $post->post_title;
				$profile['address'] = get_post_meta( $loc_id, 'o100_branch_address', true );
				$profile['phone']   = get_post_meta( $loc_id, 'o100_branch_phone', true );
			}
		} else {
			$opts = get_option( 'o100_locations', array() );
			$store_name = get_option( 'o100_store_name' );
			if ( ! empty( $store_name ) ) {
				$profile['name'] = $store_name;
			}
			$profile['address'] = isset( $opts['o100_store_address'] ) ? $opts['o100_store_address'] : '';
			$profile['phone']   = isset( $opts['o100_store_phone'] ) ? $opts['o100_store_phone'] : '';
		}
		
		$is_pro = false;
		if ( function_exists('O100_License') && O100_License()->is_premium() ) {
			$is_pro = true;
		}
		$profile['is_pro'] = $is_pro;
		
		// 获取所有可用的备餐台 (Prep Stations)
		global $wpdb;
		$stations = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'o100_prep_station' AND meta_value != ''" );
		$profile['prep_stations'] = $stations ? array_values( $stations ) : array();
		
		return rest_ensure_response( $profile );
	}

	public function print_receipt( WP_REST_Request $request ) {
		$order_id = intval( $request->get_param( 'order_id' ) );
		$printer_id = sanitize_text_field( $request->get_param( 'printer_id' ) );
		
		if ( ! $order_id ) {
			return new WP_Error( 'missing_order', 'Missing order ID', array( 'status' => 400 ) );
		}
		
		// Free version limit: Only 1 printer allowed
		if ( ! function_exists('O100_License') || ! O100_License()->is_premium() ) {
			if ( $printer_id && $printer_id !== 'default_printer_1' ) {
				return new WP_Error( 'pro_required', 'Order100 Pro is required to connect multiple printers. Free version is limited to 1 printer.', array( 'status' => 403 ) );
			}
			
			// Free version limit: Only print 1 copy per order
			$print_count = (int) get_post_meta( $order_id, '_o100_print_count', true );
			if ( $print_count >= 1 ) {
				return new WP_Error( 'pro_required', 'Order100 Pro is required to print multiple copies. Free version allows 1 print per order.', array( 'status' => 403 ) );
			}
		}
		
		// Mark as printed
		$current_count = (int) get_post_meta( $order_id, '_o100_print_count', true );
		update_post_meta( $order_id, '_o100_print_count', $current_count + 1 );
		
		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Receipt sent to printer successfully'
		) );
	}

	public function get_store_status( WP_REST_Request $request ) {
		$branch_id = $request->get_param( 'branch_id' ) ? intval( $request->get_param( 'branch_id' ) ) : 0;
		$status = class_exists( 'O100_Emergency_Closure' ) ? O100_Emergency_Closure::get_active_closure_data( $branch_id ) : false;
		
		return rest_ensure_response( array(
			'success' => true,
			'is_closed' => $status !== false,
			'closure_data' => $status
		) );
	}

	public function update_store_status( WP_REST_Request $request ) {
		if ( ! function_exists('O100_License') || ! O100_License()->is_premium() ) {
			return new WP_Error( 'pro_required', 'Order100 Pro is required to use Remote Emergency Closure.', array( 'status' => 403 ) );
		}

		$branch_id = $request->get_param( 'branch_id' ) ? intval( $request->get_param( 'branch_id' ) ) : 0;
		$mode = $request->get_param( 'mode' ); // 'open' or 'close_now' or 'scheduled'
		
		// Map simple bool emergency_off to mode
		$emergency_off = $request->get_param( 'emergency_off' );
		if ( $emergency_off !== null ) {
			$mode = filter_var( $emergency_off, FILTER_VALIDATE_BOOLEAN ) ? 'close_now' : 'open';
		}

		if ( empty( $mode ) ) {
			return new WP_Error( 'missing_params', 'Missing mode or emergency_off', array( 'status' => 400 ) );
		}

		$starts_at = $request->get_param( 'starts_at' ) ? intval( $request->get_param( 'starts_at' ) ) : 0;
		$expires_at = $request->get_param( 'expires_at' ) ? intval( $request->get_param( 'expires_at' ) ) : 0;
		$reason = $request->get_param( 'reason' ) ? sanitize_text_field( $request->get_param( 'reason' ) ) : '';

		if ( $branch_id === 0 || $branch_id === 'all' ) {
			// Global
			$options = get_option( 'o100_options', array() );
			
			if ( $mode === 'open' ) {
				$options['o100_emergency_closure'] = 'off';
			} else {
				$options['o100_emergency_closure'] = 'on';
				$options['o100_emergency_closure_starts'] = $starts_at;
				$options['o100_emergency_closure_expires'] = $expires_at;
				$options['o100_emergency_closure_reason'] = $reason;
			}
			update_option( 'o100_options', $options );
		} else {
			// Branch
			if ( $mode === 'open' ) {
				update_post_meta( $branch_id, 'o100_emergency_closure', 'off' );
			} else {
				update_post_meta( $branch_id, 'o100_emergency_closure', 'on' );
				update_post_meta( $branch_id, 'o100_emergency_closure_starts', $starts_at );
				update_post_meta( $branch_id, 'o100_emergency_closure_expires', $expires_at );
				update_post_meta( $branch_id, 'o100_emergency_closure_reason', $reason );
			}
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Store status updated successfully'
		) );
	}

	public function update_stock( WP_REST_Request $request ) {
		$product_id = intval( $request->get_param( 'product_id' ) );
		$in_stock = filter_var( $request->get_param( 'in_stock' ), FILTER_VALIDATE_BOOLEAN );

		if ( ! $product_id ) {
			return new WP_Error( 'missing_product', 'Missing product ID', array( 'status' => 400 ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'invalid_product', 'Invalid product ID', array( 'status' => 404 ) );
		}

		// Simple instock / outofstock
		$stock_status = $in_stock ? 'instock' : 'outofstock';
		$product->set_stock_status( $stock_status );
		$product->save();

		return rest_ensure_response( array(
			'success' => true,
			'stock_status' => $stock_status,
			'message' => 'Stock updated successfully'
		) );
	}
}

new O100_App_API();
