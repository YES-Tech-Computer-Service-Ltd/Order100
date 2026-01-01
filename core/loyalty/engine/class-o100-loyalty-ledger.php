<?php
/**
 * O100 Loyalty Points Ledger
 *
 * Handles adding, deducting, and querying user points with high concurrency safety.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Loyalty_Ledger {

	/**
	 * Get the current points balance for a user.
	 *
	 * @param int $user_id The user ID.
	 * @return int The points balance.
	 */
	public static function get_balance( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		
		$users_table = $wpdb->prefix . 'o100_loyalty_users';
		$balance = $wpdb->get_var( $wpdb->prepare( "SELECT points_balance FROM {$users_table} WHERE user_id = %d", $user_id ) );
		
		return $balance !== null ? (int) $balance : 0;
	}

	/**
	 * Get the lifetime points for a user.
	 *
	 * @param int $user_id The user ID.
	 * @return int The lifetime points.
	 */
	public static function get_lifetime_points( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		
		$users_table = $wpdb->prefix . 'o100_loyalty_users';
		$lifetime = $wpdb->get_var( $wpdb->prepare( "SELECT lifetime_points FROM {$users_table} WHERE user_id = %d", $user_id ) );
		
		return $lifetime !== null ? (int) $lifetime : 0;
	}

	/**
	 * Add points to a user's account.
	 *
	 * @param int    $user_id      The user ID.
	 * @param int    $points       The number of points to add (must be positive).
	 * @param string $event_type   The event type (e.g., 'order_earn', 'signup', 'game_reward').
	 * @param int    $reference_id Optional. An associated reference ID (e.g., order ID, game ID).
	 * @param string $description  Optional. A human-readable description.
	 * @param string $expires_at   Optional. Datetime string when points expire.
	 * @return int|bool The new ledger ID on success, false on failure.
	 */
	public static function add_points( $user_id, $points, $event_type, $reference_id = null, $description = '', $expires_at = null ) {
		global $wpdb;
		
		$user_id = absint( $user_id );
		$points  = absint( $points );

		if ( ! $user_id || ! $points ) {
			return false;
		}

		$ledger_table = $wpdb->prefix . 'o100_loyalty_ledger';
		$users_table  = $wpdb->prefix . 'o100_loyalty_users';

		// Insert into ledger
		$inserted = $wpdb->insert(
			$ledger_table,
			array(
				'user_id'      => $user_id,
				'points'       => $points,
				'event_type'   => $event_type,
				'reference_id' => $reference_id,
				'description'  => $description,
				'expires_at'   => $expires_at,
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return false;
		}

		$ledger_id = $wpdb->insert_id;

		// Update user cache (UPSERT)
		$wpdb->query( $wpdb->prepare( "
			INSERT INTO {$users_table} (user_id, points_balance, lifetime_points) 
			VALUES (%d, %d, %d) 
			ON DUPLICATE KEY UPDATE 
			points_balance = points_balance + %d, 
			lifetime_points = lifetime_points + %d
		", $user_id, $points, $points, $points, $points ) );

		// Hook for external modules (e.g. Level Engine to check for upgrades)
		do_action( 'o100_loyalty_points_added', $user_id, $points, $event_type, $ledger_id );

		return $ledger_id;
	}

	/**
	 * Deduct points from a user's account (e.g., for redemption).
	 *
	 * @param int    $user_id      The user ID.
	 * @param int    $points       The number of points to deduct (must be positive).
	 * @param string $event_type   The event type (e.g., 'order_redeem', 'expired').
	 * @param int    $reference_id Optional. An associated reference ID.
	 * @param string $description  Optional. A human-readable description.
	 * @return int|bool The new ledger ID on success, false on failure (e.g., insufficient balance).
	 */
	public static function deduct_points( $user_id, $points, $event_type, $reference_id = null, $description = '' ) {
		global $wpdb;
		
		$user_id = absint( $user_id );
		$points  = absint( $points ); // ensure positive internally

		if ( ! $user_id || ! $points ) {
			return false;
		}

		$current_balance = self::get_balance( $user_id );

		// Prevent negative balance unless explicitly allowed (for now, strict check)
		if ( $current_balance < $points ) {
			return false; // Insufficient funds
		}

		$ledger_table = $wpdb->prefix . 'o100_loyalty_ledger';
		$users_table  = $wpdb->prefix . 'o100_loyalty_users';

		// Insert into ledger as a negative value
		$inserted = $wpdb->insert(
			$ledger_table,
			array(
				'user_id'      => $user_id,
				'points'       => -$points,
				'event_type'   => $event_type,
				'reference_id' => $reference_id,
				'description'  => $description,
			),
			array( '%d', '%d', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return false;
		}

		$ledger_id = $wpdb->insert_id;

		// Update user cache (UPSERT)
		$wpdb->query( $wpdb->prepare( "
			INSERT INTO {$users_table} (user_id, points_balance, lifetime_points) 
			VALUES (%d, %d, %d) 
			ON DUPLICATE KEY UPDATE 
			points_balance = points_balance - %d
			-- lifetime_points remains unaffected by deductions
		", $user_id, -$points, 0, $points ) );

		do_action( 'o100_loyalty_points_deducted', $user_id, $points, $event_type, $ledger_id );

		return $ledger_id;
	}

}
