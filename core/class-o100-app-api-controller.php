<?php
/**
 * Order100 App API Controller
 * 
 * Provides centralized routing, token-based permission checking, and response formatting for the App API.
 * Namespace: order100/v1/app
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_App_API_Controller {

	const NAMESPACE = 'order100/v1/app';
	protected $namespace = self::NAMESPACE;

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Verify the Bearer token in the request header.
	 */
	public static function check_app_permissions( $request ) {
		$auth_header = $request->get_header( 'authorization' );
		
		if ( empty( $auth_header ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Missing Authorization header.', 'order100' ), [ 'status' => 401 ] );
		}

		if ( ! preg_match('/Bearer\s(\S+)/', $auth_header, $matches) ) {
			return new WP_Error( 'rest_forbidden', __( 'Invalid Authorization format.', 'order100' ), [ 'status' => 401 ] );
		}

		$token = $matches[1];
		
		// Look up the token in o100_devices
		$devices = get_option( 'o100_devices', array() );
		$device_id = null;
		
		foreach ( $devices as $id => $data ) {
			if ( isset( $data['token_hash'] ) && wp_check_password( $token, $data['token_hash'] ) ) {
				$device_id = $id;
				break;
			}
		}

		if ( ! $device_id ) {
			return new WP_Error( 'rest_forbidden', __( 'Invalid or revoked token.', 'order100' ), [ 'status' => 401 ] );
		}

		// Update last seen
		$devices[ $device_id ]['last_seen'] = time();
		update_option( 'o100_devices', $devices, false );

		// Attach the device_id to the request for controllers to use
		$request->set_param( 'o100_device_id', $device_id );
		
		// If multi-branch is active, the app should pass branch_id in the query params.
		// The controller can read $request->get_param('branch_id').

		return true;
	}

	/**
	 * Format success response
	 */
	public static function success( $data = [] ) {
		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data
		], 200 );
	}

	/**
	 * Format error response
	 */
	public static function error( $message, $status = 400, $code = 'o100_error' ) {
		return new WP_Error( $code, $message, [ 'status' => $status ] );
	}

}
