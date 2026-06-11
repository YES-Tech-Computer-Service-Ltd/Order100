<?php
/**
 * Order100 SMS Scheduler (Action Scheduler)
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_SMS_Scheduler {

	public static function init() {
		// Hook the scheduled action
		add_action( 'o100_async_send_sms', array( __CLASS__, 'execute_scheduled_sms' ), 10, 2 );
	}

	/**
	 * Schedule an SMS to be sent in the future
	 *
	 * @param int    $timestamp Unix timestamp of when to send
	 * @param string $phone     Target phone number
	 * @param string $message   Message content
	 * @param string $group     Optional group name to allow unscheduling (e.g. "reservation_123")
	 */
	public static function schedule_sms( $timestamp, $phone, $message, $group = '' ) {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			// Fallback if Action Scheduler is not loaded (should not happen with WC active)
			return;
		}

		$args = array(
			'phone'   => $phone,
			'message' => $message,
		);

		as_schedule_single_action( $timestamp, 'o100_async_send_sms', $args, $group );
	}

	/**
	 * Unschedule all pending SMS for a specific group
	 *
	 * @param string $group Group name
	 */
	public static function unschedule_sms( $group ) {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		as_unschedule_all_actions( 'o100_async_send_sms', array(), $group );
	}

	/**
	 * The actual callback executed by Action Scheduler
	 */
	public static function execute_scheduled_sms( $phone, $message ) {
		// Just route it back to the engine's real-time sender
		O100_SMS_Engine::send_custom_sms( $phone, $message );
	}
}
