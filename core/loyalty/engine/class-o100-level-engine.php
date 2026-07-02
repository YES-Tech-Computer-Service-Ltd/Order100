<?php
/**
 * O100 Loyalty Level Engine
 *
 * Handles VIP tier calculations, automatic upgrades, and benefit retrieval.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Level_Engine {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Listen for points added to recalculate levels if necessary
		add_action( 'o100_loyalty_points_added', array( __CLASS__, 'check_for_upgrade' ), 10, 4 );
	}

	/**
	 * Check if a user qualifies for a level upgrade based on lifetime points.
	 *
	 * @param int $user_id    The user ID.
	 * @param int $points     The points added.
	 * @param string $event   The event type.
	 * @param int $ledger_id  The ledger transaction ID.
	 */
	public static function check_for_upgrade( $user_id, $points, $event, $ledger_id ) {
		$lifetime_points = O100_Loyalty_Ledger::get_lifetime_points( $user_id );
		
		$current_level_id = self::get_user_level_id( $user_id );
		$new_level = self::determine_level( $lifetime_points );

		if ( $new_level && $new_level['id'] != $current_level_id ) {
			// Ensure it's actually an upgrade or a change
			self::set_user_level( $user_id, $new_level['id'] );
			do_action( 'o100_loyalty_level_changed', $user_id, $new_level['id'], $current_level_id );
		}
	}

	/**
	 * Determine the appropriate level for a given lifetime points value.
	 *
	 * @param int $lifetime_points
	 * @return array|null The level data, or null if no level matches.
	 */
	public static function determine_level( $lifetime_points ) {
		global $wpdb;
		$levels_table = $wpdb->prefix . 'o100_loyalty_levels';

		// Get the highest level where min_lifetime_points <= the user's lifetime points
		$level = $wpdb->get_row( $wpdb->prepare( "
			SELECT * FROM {$levels_table} 
			WHERE min_lifetime_points <= %d 
			ORDER BY min_lifetime_points DESC 
			LIMIT 1
		", $lifetime_points ), ARRAY_A );

		return $level ? $level : null;
	}

	/**
	 * Get a user's current level ID.
	 *
	 * @param int $user_id
	 * @return int|null
	 */
	public static function get_user_level_id( $user_id ) {
		global $wpdb;
		$users_table = $wpdb->prefix . 'o100_loyalty_users';
		
		$level_id = $wpdb->get_var( $wpdb->prepare( "SELECT level_id FROM {$users_table} WHERE user_id = %d", $user_id ) );
		return $level_id ? (int) $level_id : null;
	}

	/**
	 * Set a user's current level ID.
	 *
	 * @param int $user_id
	 * @param int $level_id
	 */
	public static function set_user_level( $user_id, $level_id ) {
		global $wpdb;
		$users_table = $wpdb->prefix . 'o100_loyalty_users';

		$wpdb->update(
			$users_table,
			array( 'level_id' => $level_id ),
			array( 'user_id' => $user_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Get full details of a user's current level.
	 *
	 * @param int $user_id
	 * @return array|null
	 */
	public static function get_user_level_details( $user_id ) {
		$level_id = self::get_user_level_id( $user_id );
		if ( ! $level_id ) {
			return null;
		}

		global $wpdb;
		$levels_table = $wpdb->prefix . 'o100_loyalty_levels';
		$level = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$levels_table} WHERE id = %d", $level_id ), ARRAY_A );

		return $level ? $level : null;
	}

	/**
	 * Get the point multiplier for a specific user based on their VIP tier.
	 *
	 * @param int $user_id
	 * @return float
	 */
	public static function get_user_point_multiplier( $user_id ) {
		$level = self::get_user_level_details( $user_id );
		if ( $level && isset( $level['point_multiplier'] ) ) {
			return (float) $level['point_multiplier'];
		}
		return 1.0;
	}
}
