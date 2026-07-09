<?php
/**
 * Order100 Base REST API Controller
 * 
 * Provides centralized routing, permission checking, and response formatting for the modern O100 API.
 * Namespace: o100/v1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_REST_Controller {

	const NAMESPACE = 'o100/v1';

	public static function init() {
		// Modules will call register_rest_route directly during rest_api_init.
	}

	/**
	 * Standard permissions check for admin-level operations.
	 */
	public static function check_admin_permissions( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Unauthorized access.', 'order100' ), [ 'status' => 403 ] );
		}
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
	public static function error( $message, $status = 400 ) {
		return new WP_Error( 'o100_error', $message, [ 'status' => $status ] );
	}

}
