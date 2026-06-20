<?php
/**
 * Reservation Push Notification Hooks
 *
 * Listens for reservation creation and status changes to trigger
 * FCM push notifications via the Order100 Cloud API.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Reservation_Push {

	public function __construct() {
		add_action( 'o100_new_reservation', array( $this, 'push_new_reservation' ), 10, 2 );
		add_action( 'o100_reservation_status_changed', array( $this, 'push_status_change' ), 10, 3 );
	}

	private function get_fcm_tokens( $branch_id = null ) {
		$devices = get_option( 'o100_fcm_tokens', array() );
		if ( empty( $devices ) || ! is_array( $devices ) ) {
			return array();
		}

		$target_tokens = array();
		foreach ( $devices as $device ) {
			if ( ! empty( $device['token'] ) ) {
				// If a branch_id is required and the device is bound to a different branch, skip it.
				// For now, if branch_id is empty on the device, we treat it as Global.
				if ( ! empty( $branch_id ) && ! empty( $device['branch_id'] ) ) {
					if ( (string) $device['branch_id'] !== (string) $branch_id ) {
						continue;
					}
				}
				$target_tokens[] = $device['token'];
			}
		}
		return $target_tokens;
	}

	public function push_new_reservation( $insert_id, $data ) {
		// Use location_id as branch_id if available
		$branch_id = isset( $data['location_id'] ) && $data['location_id'] > 0 ? $data['location_id'] : null;
		
		$tokens = $this->get_fcm_tokens( $branch_id );
		if ( empty( $tokens ) ) {
			return;
		}

		$payload = array(
			'action'         => 'new_reservation',
			'reservation_id' => $insert_id,
			'guest_name'     => isset( $data['guest_name'] ) ? $data['guest_name'] : '',
			'party_size'     => isset( $data['party_size'] ) ? $data['party_size'] : 1,
			'date'           => isset( $data['reservation_date'] ) ? $data['reservation_date'] : '',
			'time'           => isset( $data['reservation_time'] ) ? $data['reservation_time'] : '',
			'domain'         => wp_parse_url( home_url(), PHP_URL_HOST ),
			'event_id'       => 'resv:' . wp_parse_url( home_url(), PHP_URL_HOST ) . ':' . $insert_id,
		);

		O100_Cloud_API::send_fcm_push( $tokens, $payload );
	}

	public function push_status_change( $id, $new_status, $old_status ) {
		$row = O100_Reservation_DB::get( $id );
		if ( ! $row ) {
			return;
		}

		$branch_id = isset( $row->location_id ) && $row->location_id > 0 ? $row->location_id : null;
		
		$tokens = $this->get_fcm_tokens( $branch_id );
		if ( empty( $tokens ) ) {
			return;
		}

		$payload = array(
			'action'             => 'reservation_status_changed',
			'reservation_id'     => $id,
			'reservation_status' => $new_status,
			'domain'             => wp_parse_url( home_url(), PHP_URL_HOST ),
			'event_id'           => 'resv_status:' . wp_parse_url( home_url(), PHP_URL_HOST ) . ':' . $id . ':' . time(),
		);

		O100_Cloud_API::send_fcm_push( $tokens, $payload );
	}
}

new O100_Reservation_Push();
