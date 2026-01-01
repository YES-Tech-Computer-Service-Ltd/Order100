<?php
/**
 * Reservation Database Operations
 *
 * Handles table creation, CRUD operations, and availability queries
 * for the o100_reservations table.
 *
 * @package Order100
 * @since   1.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Reservation_DB {

	/**
	 * Get the full table name
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'o100_reservations';
	}

	/**
	 * Create the reservations table on plugin activation.
	 * Safe to call multiple times — dbDelta handles schema diffing.
	 */
	public static function create_table() {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			booking_type VARCHAR(20) NOT NULL DEFAULT 'table',
			room_type_id INT UNSIGNED DEFAULT NULL,
			guest_name VARCHAR(100) NOT NULL DEFAULT '',
			guest_email VARCHAR(100) NOT NULL DEFAULT '',
			guest_phone VARCHAR(30) NOT NULL DEFAULT '',
			party_size INT UNSIGNED NOT NULL DEFAULT 1,
			reservation_date DATE NOT NULL,
			reservation_time VARCHAR(20) NOT NULL DEFAULT '',
			location_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			special_requests TEXT,
			reminder_sent TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			source VARCHAR(20) NOT NULL DEFAULT 'website',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_date_status (reservation_date, status),
			KEY idx_room_avail (reservation_date, reservation_time, room_type_id, status),
			KEY idx_reminder (status, reminder_sent, reservation_date)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Check if the table exists
	 */
	public static function table_exists() {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
	}

	// ─── CRUD ──────────────────────────────────────────────────────────

	/**
	 * Insert a new reservation
	 *
	 * @param array $data Associative array of column => value.
	 * @return int|false  Inserted ID on success, false on failure.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$defaults = array(
			'status'           => 'pending',
			'booking_type'     => 'table',
			'room_type_id'     => null,
			'guest_name'       => '',
			'guest_email'      => '',
			'guest_phone'      => '',
			'party_size'       => 1,
			'reservation_date' => '',
			'reservation_time' => '',
			'location_id'      => 0,
			'special_requests' => '',
			'reminder_sent'    => 0,
			'source'           => 'website',
		);

		$data = wp_parse_args( $data, $defaults );

		$formats = array(
			'%s', // status
			'%s', // booking_type
			'%d', // room_type_id
			'%s', // guest_name
			'%s', // guest_email
			'%s', // guest_phone
			'%d', // party_size
			'%s', // reservation_date
			'%s', // reservation_time
			'%d', // location_id
			'%s', // special_requests
			'%d', // reminder_sent
			'%s', // source
		);

		$result = $wpdb->insert( self::table_name(), $data, $formats );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a single reservation by ID
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d", $id
		) );
	}

	/**
	 * Update a reservation
	 *
	 * @param int   $id
	 * @param array $data Column => value pairs to update.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		return false !== $wpdb->update(
			self::table_name(),
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Update reservation status with timestamp
	 *
	 * @param int    $id
	 * @param string $status  pending|confirmed|cancelled
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		$allowed = array( 'pending', 'confirmed', 'cancelled' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		return self::update( $id, array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		) );
	}

	/**
	 * Delete a reservation permanently
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete(
			self::table_name(),
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	// ─── Queries ───────────────────────────────────────────────────────

	/**
	 * Get reservations list with filters, pagination, and sorting.
	 *
	 * @param array $args {
	 *   @type string $status       Filter by status.
	 *   @type string $booking_type Filter by booking_type.
	 *   @type string $date_from    Filter date >= YYYY-MM-DD.
	 *   @type string $date_to      Filter date <= YYYY-MM-DD.
	 *   @type int    $location_id  Filter by location.
	 *   @type int    $per_page     Items per page (default 20).
	 *   @type int    $page         Current page (default 1).
	 *   @type string $orderby      Column to sort (default reservation_date).
	 *   @type string $order        ASC or DESC (default DESC).
	 * }
	 * @return array { 'items' => array, 'total' => int }
	 */
	public static function get_list( $args = array() ) {
		global $wpdb;

		$table = self::table_name();

		$defaults = array(
			'status'       => '',
			'booking_type' => '',
			'date_from'    => '',
			'date_to'      => '',
			'location_id'  => '',
			'search'       => '',
			'per_page'     => 20,
			'page'         => 1,
			'orderby'      => 'reservation_date',
			'order'        => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where   = array( '1=1' );
		$values  = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( $args['booking_type'] ) {
			$where[]  = 'booking_type = %s';
			$values[] = $args['booking_type'];
		}

		if ( $args['date_from'] ) {
			$where[]  = 'reservation_date >= %s';
			$values[] = $args['date_from'];
		}

		if ( $args['date_to'] ) {
			$where[]  = 'reservation_date <= %s';
			$values[] = $args['date_to'];
		}

		if ( $args['location_id'] !== '' && $args['location_id'] !== null ) {
			$where[]  = 'location_id = %d';
			$values[] = intval( $args['location_id'] );
		}

		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(guest_name LIKE %s OR guest_email LIKE %s OR guest_phone LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		// Whitelist orderby
		$allowed_orderby = array( 'id', 'reservation_date', 'reservation_time', 'status', 'party_size', 'created_at', 'guest_name' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'reservation_date';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Count total
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$values );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Get items
		$offset  = max( 0, ( intval( $args['page'] ) - 1 ) * intval( $args['per_page'] ) );
		$limit   = intval( $args['per_page'] );

		$query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order}, reservation_time ASC LIMIT %d OFFSET %d";
		$values[] = $limit;
		$values[] = $offset;

		$items = $wpdb->get_results( $wpdb->prepare( $query, ...$values ) );

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	// ─── Availability ──────────────────────────────────────────────────

	/**
	 * Count active (pending+confirmed) reservations for a given date + time slot.
	 *
	 * @param string $date         YYYY-MM-DD
	 * @param string $time         e.g. "18:00"
	 * @param string $booking_type "table" or "private_room"
	 * @param int    $room_type_id Only used when booking_type = private_room
	 * @param int    $location_id  Location filter (0 = all)
	 * @return int
	 */
	public static function count_active( $date, $time, $booking_type = 'table', $room_type_id = null, $location_id = 0 ) {
		global $wpdb;
		$table = self::table_name();

		$sql = "SELECT COUNT(*) FROM {$table}
			WHERE reservation_date = %s
			AND reservation_time = %s
			AND booking_type = %s
			AND status IN ('pending', 'confirmed')";
		$values = array( $date, $time, $booking_type );

		if ( $booking_type === 'private_room' && $room_type_id !== null ) {
			$sql     .= ' AND room_type_id = %d';
			$values[] = $room_type_id;
		}

		if ( $location_id > 0 ) {
			$sql     .= ' AND location_id = %d';
			$values[] = $location_id;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$values ) );
	}

	/**
	 * Check if a table slot is available.
	 *
	 * @param string $date
	 * @param string $time
	 * @param int    $location_id
	 * @return bool
	 */
	public static function is_table_available( $date, $time, $location_id = 0 ) {
		$schedule = get_option( 'o100_store_hours', array() );
		$max      = isset( $schedule['o100_resv_max_per_slot'] ) ? intval( $schedule['o100_resv_max_per_slot'] ) : 5;
		$booked   = self::count_active( $date, $time, 'table', null, $location_id );
		return $booked < $max;
	}

	/**
	 * Check if a private room is available.
	 *
	 * @param string $date
	 * @param string $time
	 * @param int    $room_type_id  Index in the rooms config array
	 * @param int    $location_id
	 * @return bool
	 */
	public static function is_room_available( $date, $time, $room_type_id, $location_id = 0 ) {
		$settings = get_option( 'o100_reservation', array() );
		$rooms    = isset( $settings['o100_resv_rooms'] ) ? $settings['o100_resv_rooms'] : array();

		if ( ! isset( $rooms[ $room_type_id ] ) ) {
			return false;
		}

		$max_qty = isset( $rooms[ $room_type_id ]['quantity'] ) ? intval( $rooms[ $room_type_id ]['quantity'] ) : 1;
		$booked  = self::count_active( $date, $time, 'private_room', $room_type_id, $location_id );

		return $booked < $max_qty;
	}

	/**
	 * Get full availability info for a date (all time slots).
	 * Returns each slot with remaining tables and room availability.
	 *
	 * @param string $date        YYYY-MM-DD
	 * @param array  $time_slots  Array of time strings, e.g. ['17:00', '17:30', '18:00']
	 * @param int    $location_id
	 * @return array
	 */
	public static function get_availability( $date, $time_slots, $location_id = 0 ) {
		$schedule  = get_option( 'o100_store_hours', array() );
		$max_tables = isset( $schedule['o100_resv_max_per_slot'] ) ? intval( $schedule['o100_resv_max_per_slot'] ) : 5;
		$resv_settings = get_option( 'o100_reservation', array() );
		$rooms     = isset( $resv_settings['o100_resv_rooms'] ) ? $resv_settings['o100_resv_rooms'] : array();

		$availability = array();

		foreach ( $time_slots as $time ) {
			$table_booked = self::count_active( $date, $time, 'table', null, $location_id );

			$slot = array(
				'time'             => $time,
				'tables_remaining' => max( 0, $max_tables - $table_booked ),
				'tables_available' => $table_booked < $max_tables,
				'rooms'            => array(),
			);

			foreach ( $rooms as $idx => $room ) {
				$room_booked = self::count_active( $date, $time, 'private_room', $idx, $location_id );
				$room_qty    = isset( $room['quantity'] ) ? intval( $room['quantity'] ) : 1;

				$slot['rooms'][] = array(
					'id'        => $idx,
					'name'      => isset( $room['name'] ) ? $room['name'] : 'Room ' . ( $idx + 1 ),
					'capacity'  => isset( $room['capacity'] ) ? intval( $room['capacity'] ) : 0,
					'remaining' => max( 0, $room_qty - $room_booked ),
					'available' => $room_booked < $room_qty,
				);
			}

			$availability[] = $slot;
		}

		return $availability;
	}

	// ─── Slot Booking Count ───────────────────────────────────────────

	/**
	 * Count active reservations for a specific slot (used by AJAX timeslot availability)
	 *
	 * @param int    $branch_id  Location/branch ID (0 = all)
	 * @param string $date       YYYY-MM-DD
	 * @param string $time       e.g. "18:00"
	 * @return int
	 */
	public static function get_slot_booking_count( $branch_id, $date, $time ) {
		global $wpdb;
		$table = self::table_name();

		if ( ! self::table_exists() ) return 0;

		$sql    = "SELECT COUNT(*) FROM {$table} WHERE reservation_date = %s AND reservation_time = %s AND status IN ('pending','confirmed')";
		$values = array( $date, $time );

		if ( $branch_id > 0 ) {
			$sql     .= ' AND location_id = %d';
			$values[] = $branch_id;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$values ) );
	}

	// ─── Reminder Query ────────────────────────────────────────────────

	/**
	 * Get confirmed reservations that need reminder and haven't been sent yet.
	 *
	 * @param int $hours_before  Send reminder N hours before reservation time.
	 * @return array
	 */
	public static function get_pending_reminders( $hours_before = 2 ) {
		global $wpdb;
		$table = self::table_name();

		$now = current_time( 'mysql' );

		// Calculate the cutoff: reservations happening within the next N hours
		$cutoff = date( 'Y-m-d H:i:s', strtotime( "+{$hours_before} hours", strtotime( $now ) ) );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE status = 'confirmed'
			AND reminder_sent = 0
			AND CONCAT(reservation_date, ' ', reservation_time, ':00') <= %s
			AND CONCAT(reservation_date, ' ', reservation_time, ':00') > %s
			ORDER BY reservation_date ASC, reservation_time ASC",
			$cutoff,
			$now
		) );
	}

	/**
	 * Mark a reservation's reminder as sent.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function mark_reminder_sent( $id ) {
		return self::update( $id, array( 'reminder_sent' => 1 ) );
	}
}
