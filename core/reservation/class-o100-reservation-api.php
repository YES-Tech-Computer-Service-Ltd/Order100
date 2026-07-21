<?php
/**
 * Reservation REST API
 *
 * Provides endpoints for the Android App and the unified SPA Admin interface.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Reservation_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		// Ensure REST Controller is loaded for permissions
		if ( ! class_exists( 'O100_REST_Controller' ) ) {
			require_once O100_PATH . 'core/class-o100-rest-controller.php';
		}

		// --------------------------------------------------------
		// 1. Admin/SPA Endpoints (/o100/v1/reservations)
		// --------------------------------------------------------

		// GET list
		register_rest_route( 'o100/v1', '/reservations', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_reservations_list' ),
			'permission_callback' => array( 'O100_REST_Controller', 'check_admin_permissions' ),
		) );

		// POST create manually from admin
		register_rest_route( 'o100/v1', '/reservations', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_reservation' ),
			'permission_callback' => array( 'O100_REST_Controller', 'check_admin_permissions' ),
		) );

		// PATCH bulk actions
		register_rest_route( 'o100/v1', '/reservations/bulk', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'bulk_action' ),
			'permission_callback' => array( 'O100_REST_Controller', 'check_admin_permissions' ),
		) );

		// PATCH single status
		register_rest_route( 'o100/v1', '/reservations/(?P<id>[\d]+)/status', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_status' ),
			'permission_callback' => array( 'O100_REST_Controller', 'check_admin_permissions' ),
		) );

		// DELETE single
		register_rest_route( 'o100/v1', '/reservations/(?P<id>[\d]+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_reservation' ),
			'permission_callback' => array( 'O100_REST_Controller', 'check_admin_permissions' ),
		) );

		// --------------------------------------------------------
		// 2. Public Frontend Endpoints
		// --------------------------------------------------------

		// GET available dates
		register_rest_route( 'o100/v1', '/reservations/available-dates', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_available_dates' ),
			'permission_callback' => '__return_true',
		) );

		// GET available slots
		register_rest_route( 'o100/v1', '/reservations/available-slots', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_available_slots' ),
			'permission_callback' => '__return_true',
		) );

		// POST submit form from frontend widget
		register_rest_route( 'o100/v1', '/reservations/submit', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'submit_reservation_frontend' ),
			'permission_callback' => '__return_true',
		) );

		// --------------------------------------------------------
		// 3. Legacy App Compatibility (/order100/v1/reservations)
		// --------------------------------------------------------
		register_rest_route( 'order100/v1', '/reservations', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_reservations_legacy' ),
			'permission_callback' => array( $this, 'check_legacy_app_permission' ),
		) );

		register_rest_route( 'order100/v1', '/reservations/(?P<id>[\d]+)/status', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'update_status' ), // Re-use the same method
			'permission_callback' => array( $this, 'check_legacy_app_permission' ),
		) );
	}

	public function check_legacy_app_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	// =========================================================================
	// ADMIN SPA ENDPOINTS
	// =========================================================================

	public function get_reservations_list( WP_REST_Request $request ) {
		$args = array(
			'status'       => sanitize_text_field( $request->get_param( 'status' ) ),
			'booking_type' => sanitize_text_field( $request->get_param( 'booking_type' ) ),
			'date_from'    => sanitize_text_field( $request->get_param( 'date_from' ) ),
			'date_to'      => sanitize_text_field( $request->get_param( 'date_to' ) ),
			'search'       => sanitize_text_field( $request->get_param( 'search' ) ),
			'per_page'     => intval( $request->get_param( 'per_page' ) ?: 50 ),
			'page'         => intval( $request->get_param( 'page' ) ?: 1 ),
			'orderby'      => sanitize_text_field( $request->get_param( 'orderby' ) ?: 'reservation_date' ),
			'order'        => sanitize_text_field( $request->get_param( 'order' ) ?: 'DESC' ),
		);

		$result = O100_Reservation_DB::get_list( $args );

		// Format output for Vue/Alpine
		foreach ( $result['items'] as &$item ) {
			$item->created_at_ts = strtotime( $item->created_at );
			$item->updated_at_ts = strtotime( $item->updated_at );
			// Stats
			$stats = O100_Reservation_DB::get_guest_stats( $item->guest_email );
			$item->past_visits = $stats['total'];
			$item->past_fulfilled = $stats['fulfilled'];
		}

		return rest_ensure_response( array(
			'items' => $result['items'],
			'total' => $result['total'],
			'page'  => $args['page'],
			'pages' => ceil( $result['total'] / max(1, $args['per_page']) )
		) );
	}

	public function update_status( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$status = sanitize_text_field( $request->get_param( 'status' ) );

		if ( empty( $status ) ) {
			return new WP_Error( 'missing_status', 'Status is required.', array( 'status' => 400 ) );
		}

		$valid_statuses = array( 'pending', 'confirmed', 'rejected', 'completed', 'cancelled', 'no_show' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return new WP_Error( 'invalid_status', 'Invalid status.', array( 'status' => 400 ) );
		}

		$old_row = O100_Reservation_DB::get( $id );
		if ( ! $old_row ) {
			return new WP_Error( 'not_found', 'Reservation not found.', array( 'status' => 404 ) );
		}

		$updated = O100_Reservation_DB::update_status( $id, $status );

		if ( ! $updated && $old_row->status !== $status ) {
			return new WP_Error( 'update_failed', 'Failed to update reservation status.', array( 'status' => 500 ) );
		}

		// Trigger Email notification on status change
		if ( class_exists( 'O100_Reservation_Notify' ) && $updated ) {
			O100_Reservation_Notify::send_status_change( $id, $status );
		}

		$row = O100_Reservation_DB::get( $id );
		
		return rest_ensure_response( array(
			'success' => true,
			'data'    => $row
		) );
	}

	public function bulk_action( WP_REST_Request $request ) {
		$ids = $request->get_param( 'ids' );
		$action = sanitize_text_field( $request->get_param( 'action' ) );

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return new WP_Error( 'missing_ids', 'No items selected.', array( 'status' => 400 ) );
		}

		$count = 0;
		foreach ( $ids as $id ) {
			$id = intval( $id );
			if ( $action === 'delete' ) {
				if ( O100_Reservation_DB::delete( $id ) ) {
					$count++;
				}
			} elseif ( in_array( $action, array( 'confirmed', 'cancelled', 'completed', 'no_show', 'rejected' ), true ) ) {
				if ( O100_Reservation_DB::update_status( $id, $action ) ) {
					if ( class_exists( 'O100_Reservation_Notify' ) ) {
						O100_Reservation_Notify::send_status_change( $id, $action );
					}
					$count++;
				}
			}
		}

		return rest_ensure_response( array(
			'success' => true,
			'count'   => $count
		) );
	}

	public function delete_reservation( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		
		if ( O100_Reservation_DB::delete( $id ) ) {
			return rest_ensure_response( array( 'success' => true ) );
		}
		
		return new WP_Error( 'delete_failed', 'Could not delete.', array( 'status' => 500 ) );
	}

	public function create_reservation( WP_REST_Request $request ) {
		$data = array(
			'status'           => sanitize_text_field( $request->get_param( 'status' ) ?: 'confirmed' ), // Admin created defaults to confirmed
			'booking_type'     => sanitize_text_field( $request->get_param( 'booking_type' ) ?: 'table' ),
			'guest_name'       => sanitize_text_field( $request->get_param( 'guest_name' ) ),
			'guest_email'      => sanitize_email( $request->get_param( 'guest_email' ) ),
			'guest_phone'      => sanitize_text_field( $request->get_param( 'guest_phone' ) ),
			'party_size'       => intval( $request->get_param( 'party_size' ) ?: 2 ),
			'reservation_date' => sanitize_text_field( $request->get_param( 'reservation_date' ) ),
			'reservation_time' => sanitize_text_field( $request->get_param( 'reservation_time' ) ),
			'special_requests' => sanitize_textarea_field( $request->get_param( 'special_requests' ) ),
			'source'           => 'admin',
		);

		if ( empty( $data['guest_name'] ) || empty( $data['reservation_date'] ) || empty( $data['reservation_time'] ) ) {
			return new WP_Error( 'missing_fields', 'Name, date and time are required.', array( 'status' => 400 ) );
		}

		$id = O100_Reservation_DB::insert( $data );
		if ( $id ) {
			return rest_ensure_response( array( 'success' => true, 'id' => $id, 'data' => O100_Reservation_DB::get( $id ) ) );
		}
		
		return new WP_Error( 'create_failed', 'Could not create reservation.', array( 'status' => 500 ) );
	}

	// =========================================================================
	// PUBLIC FRONTEND ENDPOINTS
	// =========================================================================

	public function get_available_dates( WP_REST_Request $request ) {
		if ( ! class_exists( 'O100_Reservation' ) ) {
			return new WP_Error( 'not_loaded', 'Core not loaded', array( 'status' => 500 ) );
		}
		
		// Map the request to a mock $_GET so O100_Reservation can handle it natively
		// (Or directly extract the logic from O100_Reservation)
		$branch_id = intval( $request->get_param( 'branch_id' ) );
		$data      = O100_Reservation::get_available_dates_data( $branch_id );
		
		return rest_ensure_response( array( 'success' => true, 'dates' => $data ) );
	}

	public function get_available_slots( WP_REST_Request $request ) {
		if ( ! class_exists( 'O100_Reservation' ) ) {
			return new WP_Error( 'not_loaded', 'Core not loaded', array( 'status' => 500 ) );
		}

		$date      = sanitize_text_field( $request->get_param( 'date' ) );
		$branch_id = intval( $request->get_param( 'branch_id' ) );
		$max_per   = intval( $request->get_param( 'max_per' ) );

		if ( empty( $date ) ) {
			return new WP_Error( 'missing_date', 'Date required', array( 'status' => 400 ) );
		}

		$data = O100_Reservation::get_available_slots_data( $branch_id, $date, $max_per ?: null );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return rest_ensure_response( array( 'success' => true, 'data' => $data ) );
	}

	public function submit_reservation_frontend( WP_REST_Request $request ) {
		if ( ! class_exists( 'O100_Reservation' ) ) {
			return new WP_Error( 'not_loaded', 'Core not loaded', array( 'status' => 500 ) );
		}
		
		$post_data = $request->get_params();
		$result = O100_Reservation::submit_reservation_data( $post_data );
		
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		
		return rest_ensure_response( array( 'success' => true, 'data' => $result ) );
	}

	// =========================================================================
	// LEGACY APP COMPATIBILITY
	// =========================================================================

	public function get_reservations_legacy( WP_REST_Request $request ) {
		global $wpdb;

		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );
		$status     = $request->get_param( 'status' );
		$id         = $request->get_param( 'reservation_id' );

		$table = O100_Reservation_DB::table_name();
		
		$where = array();
		$args  = array();

		if ( ! empty( $id ) ) {
			$where[] = 'id = %d';
			$args[]  = intval( $id );
		} else {
			if ( ! empty( $start_date ) ) {
				$where[] = 'reservation_date >= %s';
				$args[]  = $start_date;
			}
			if ( ! empty( $end_date ) ) {
				$where[] = 'reservation_date <= %s';
				$args[]  = $end_date;
			}
			if ( ! empty( $status ) ) {
				$where[] = 'status = %s';
				$args[]  = $status;
			}
		}

		$where_sql = '';
		if ( ! empty( $where ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where );
		}

		$query = "SELECT * FROM {$table} {$where_sql} ORDER BY reservation_date ASC, reservation_time ASC LIMIT 500";
		
		if ( ! empty( $args ) ) {
			$query = $wpdb->prepare( $query, $args );
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Format output
		$formatted = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$formatted[] = array(
					'id'               => (int) $row['id'],
					'status'           => $row['status'],
					'guest_name'       => $row['guest_name'],
					'guest_phone'      => $row['guest_phone'],
					'party_size'       => (int) $row['party_size'],
					'reservation_date' => $row['reservation_date'],
					'reservation_time' => $row['reservation_time'],
					'special_requests' => $row['special_requests'],
					'updated_at'       => strtotime( $row['updated_at'] ) * 1000
				);
			}
		}

		return rest_ensure_response( $formatted );
	}
}

new O100_Reservation_API();
