<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Order_Manager {
	
	/**
	 * Get the total number of active orders for a specific timeslot and date.
	 * Replaces the legacy exwf_get_number_order_timeslot function.
	 *
	 * @param string $date_val The date value (e.g. timestamp or string depending on format).
	 * @param string $time_val The time slot value (e.g. "09:30 - 10:00").
	 * @param string $method   The order method (delivery or takeaway).
	 * @return int Total number of orders.
	 */
	public static function get_timeslot_order_count( $date_val, $time_val, $method = '' ) {
		if ( empty( $date_val ) || empty( $time_val ) ) {
			return 0;
		}

		$args = array(
			'status' => array( 'wc-processing', 'wc-on-hold' ), // Standard active WooCommerce statuses
			'limit'  => -1,
			'return' => 'ids',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => 'o100_date_deli',
					'value'   => $date_val,
					'compare' => '='
				),
				array(
					'key'     => 'o100_time_deli',
					'value'   => $time_val,
					'compare' => '='
				)
			)
		);

		if ( ! empty( $method ) ) {
			// Some orders might not have the method set correctly in legacy systems, but we enforce it for new ones.
			$args['meta_query'][] = array(
				'key'     => 'o100_order_method',
				'value'   => $method,
				'compare' => '='
			);
		}

		// Use wc_get_orders for HPOS compatibility
		$orders = wc_get_orders( $args );
		
		return count( $orders );
	}
}


// TS: 20260515121830
