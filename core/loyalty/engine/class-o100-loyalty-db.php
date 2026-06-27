<?php
/**
 * O100 Loyalty Database Layer
 *
 * Native database operations for the Loyalty module.
 * Replaces all \Wlr\App\Models\* classes with direct $wpdb calls.
 *
 * @package Order100
 * @since   4.0.0
 */

defined( 'ABSPATH' ) or die;

class O100_Loyalty_DB {

	/** @var string DB version for schema migrations */
	const DB_VERSION = '1.2.0';

	/** @var string Option key for tracking DB version */
	const DB_VERSION_KEY = 'o100_loyalty_db_version';

	// ─── Table Names ───────────────────────────────────────────

	public static function table_accounts()      { global $wpdb; return $wpdb->prefix . 'o100_loyalty_accounts'; }
	public static function table_transactions()   { global $wpdb; return $wpdb->prefix . 'o100_loyalty_transactions'; }
	public static function table_campaigns()      { global $wpdb; return $wpdb->prefix . 'o100_loyalty_campaigns'; }
	public static function table_levels()         { global $wpdb; return $wpdb->prefix . 'o100_loyalty_levels'; }
	public static function table_punch_progress() { global $wpdb; return $wpdb->prefix . 'o100_loyalty_punch_progress'; }

	// ─── Schema Creation ───────────────────────────────────────

	/**
	 * Create all loyalty tables. Called on plugin activation.
	 */
	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. Accounts
		$t = self::table_accounts();
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED DEFAULT 0,
			email VARCHAR(200) NOT NULL DEFAULT '',
			points_balance INT NOT NULL DEFAULT 0,
			points_earned INT NOT NULL DEFAULT 0,
			points_spent INT NOT NULL DEFAULT 0,
			level_id INT UNSIGNED DEFAULT 0,
			refer_code VARCHAR(50) DEFAULT '',
			status ENUM('active','banned') NOT NULL DEFAULT 'active',
			birthday DATE DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_user_id (user_id),
			UNIQUE KEY idx_email (email),
			KEY idx_level (level_id),
			KEY idx_status (status)
		) {$charset};" );

		// 2. Transactions
		$t = self::table_transactions();
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			account_id BIGINT UNSIGNED NOT NULL,
			type ENUM('earn','spend','adjust','expire') NOT NULL DEFAULT 'earn',
			points INT NOT NULL DEFAULT 0,
			balance_after INT NOT NULL DEFAULT 0,
			source VARCHAR(50) NOT NULL DEFAULT 'order',
			source_id BIGINT UNSIGNED DEFAULT 0,
			campaign_id BIGINT UNSIGNED DEFAULT NULL,
			note TEXT,
			points_remaining INT NOT NULL DEFAULT 0,
			expires_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_account_date (account_id, created_at),
			KEY idx_source (source, source_id)
		) {$charset};" );

		// 3. Campaigns (earn rules)
		$t = self::table_campaigns();
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(200) NOT NULL DEFAULT '',
			description TEXT,
			icon VARCHAR(500) DEFAULT '',
			type VARCHAR(50) NOT NULL DEFAULT 'points_per_dollar',
			earn_config TEXT,
			conditions TEXT,
			reward_config TEXT,
			condition_relationship ENUM('and','or') NOT NULL DEFAULT 'and',
			priority INT NOT NULL DEFAULT 10,
			status ENUM('active','disabled') NOT NULL DEFAULT 'active',
			usage_count INT UNSIGNED NOT NULL DEFAULT 0,
			start_at DATETIME DEFAULT NULL,
			end_at DATETIME DEFAULT NULL,
			is_show_way_to_earn TINYINT(1) NOT NULL DEFAULT 1,
			ordering INT NOT NULL DEFAULT 0,
			ui_json TEXT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_type_status (type, status),
			KEY idx_status (status)
		) {$charset};" );

		// 4. Levels
		$t = self::table_levels();
		dbDelta( "CREATE TABLE {$t} (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL DEFAULT '',
			min_points INT NOT NULL DEFAULT 0,
			max_points INT NOT NULL DEFAULT 0,
			icon VARCHAR(500) DEFAULT '',
			badge_css TEXT,
			perks TEXT,
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_points (min_points, max_points)
		) {$charset};" );

		// 5. Punch Card Progress
		$t = self::table_punch_progress();
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			account_id BIGINT UNSIGNED NOT NULL,
			campaign_id BIGINT UNSIGNED NOT NULL,
			stamps INT NOT NULL DEFAULT 0,
			redeemed_count INT NOT NULL DEFAULT 0,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_account_campaign (account_id, campaign_id)
		) {$charset};" );

		update_option( self::DB_VERSION_KEY, self::DB_VERSION );
	}

	/**
	 * Check if tables need creating/updating.
	 */
	public static function maybe_create_tables() {
		if ( get_option( self::DB_VERSION_KEY ) !== self::DB_VERSION ) {
			self::create_tables();
		}
	}

	// ═══════════════════════════════════════════════════════════
	// ACCOUNTS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Get account by user ID.
	 */
	public static function get_account_by_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE user_id = %d", self::table_accounts(), $user_id
		) );
	}

	/**
	 * Get account by email.
	 */
	public static function get_account_by_email( $email ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE email = %s", self::table_accounts(), $email
		) );
	}

	/**
	 * Get account by ID.
	 */
	public static function get_account( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d", self::table_accounts(), $id
		) );
	}

	/**
	 * Get or create account for a user/email.
	 */
	public static function get_or_create_account( $user_id = 0, $email = '' ) {
		if ( $user_id ) {
			$acct = self::get_account_by_user( $user_id );
			if ( $acct ) return $acct;
		}
		if ( $email ) {
			$acct = self::get_account_by_email( $email );
			if ( $acct ) return $acct;
		}
		if ( ! $email && $user_id ) {
			$user = get_userdata( $user_id );
			$email = $user ? $user->user_email : '';
		}
		if ( ! $email ) return null;

		global $wpdb;
		$refer_code = strtoupper( 'REF-' . wp_generate_password( 6, false ) );
		$wpdb->insert( self::table_accounts(), [
			'user_id'    => $user_id,
			'email'      => $email,
			'refer_code' => $refer_code,
		] );
		return self::get_account( $wpdb->insert_id );
	}

	/**
	 * Update account fields.
	 */
	public static function update_account( $id, $data ) {
		global $wpdb;
		return $wpdb->update( self::table_accounts(), $data, [ 'id' => $id ] );
	}

	/**
	 * List accounts with pagination, search, sorting.
	 */
	public static function get_accounts( $args = [] ) {
		global $wpdb;
		$defaults = [
			'page'     => 1,
			'per_page' => 20,
			'search'   => '',
			'orderby'  => 'id',
			'order'    => 'DESC',
			'status'   => '',
		];
		$args = wp_parse_args( $args, $defaults );
		$t = self::table_accounts();

		$where = '1=1';
		$params = [];
		if ( $args['search'] ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= ' AND email LIKE %s';
			$params[] = $like;
		}
		if ( $args['status'] ) {
			$where .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$allowed_order = [ 'id', 'email', 'points_balance', 'created_at' ];
		$orderby = in_array( $args['orderby'], $allowed_order ) ? $args['orderby'] : 'id';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$offset  = ( max( 1, $args['page'] ) - 1 ) * $args['per_page'];

		$count_sql = "SELECT COUNT(*) FROM {$t} WHERE {$where}";
		$data_sql  = "SELECT * FROM {$t} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params_data = array_merge( $params, [ $args['per_page'], $offset ] );

		$total = $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql );
		$items = $params_data ? $wpdb->get_results( $wpdb->prepare( $data_sql, $params_data ) ) : $wpdb->get_results( $data_sql );

		return [
			'items'       => $items ?: [],
			'total_count' => (int) $total,
			'page'        => $args['page'],
			'per_page'    => $args['per_page'],
		];
	}

	/**
	 * Get total account count.
	 */
	public static function get_account_count( $where = '' ) {
		global $wpdb;
		$sql = "SELECT COUNT(*) FROM " . self::table_accounts();
		if ( $where ) $sql .= " WHERE {$where}";
		return (int) $wpdb->get_var( $sql );
	}

	// ═══════════════════════════════════════════════════════════
	// POINTS OPERATIONS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Add points to an account and log the transaction.
	 */
	public static function add_points( $account_id, $points, $source = 'order', $source_id = 0, $campaign_id = null, $note = '' ) {
		global $wpdb;
		$acct = self::get_account( $account_id );
		if ( ! $acct || $points <= 0 ) return false;

		$settings = self::get_settings();
		$expiry_value = isset( $settings['points_expiry_value'] ) ? intval( $settings['points_expiry_value'] ) : 0;
		$expiry_unit = isset( $settings['points_expiry_unit'] ) ? $settings['points_expiry_unit'] : 'days';
		
		// Fallback for older configurations
		if ( !isset($settings['points_expiry_value']) && isset( $settings['points_expiry_days'] ) ) {
			$expiry_value = intval( $settings['points_expiry_days'] );
			$expiry_unit = 'days';
		}

		$expires_at = null;
		if ( $expiry_value > 0 ) {
			$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_value} {$expiry_unit}" ) );
		}

		$new_balance = $acct->points_balance + $points;
		$wpdb->update( self::table_accounts(), [
			'points_balance' => $new_balance,
			'points_earned'  => $acct->points_earned + $points,
		], [ 'id' => $account_id ] );

		return self::log_transaction( $account_id, 'earn', $points, $new_balance, $source, $source_id, $campaign_id, $note, $points, $expires_at );
	}

	/**
	 * Deduct points from an account.
	 */
	public static function deduct_points( $account_id, $points, $source = 'redeem', $source_id = 0, $campaign_id = null, $note = '' ) {
		global $wpdb;
		$acct = self::get_account( $account_id );
		if ( ! $acct || $points <= 0 ) return false;
		if ( $acct->points_balance < $points ) return false;

		$new_balance = $acct->points_balance - $points;
		$wpdb->update( self::table_accounts(), [
			'points_balance' => $new_balance,
			'points_spent'   => $acct->points_spent + $points,
		], [ 'id' => $account_id ] );
		
		// FIFO Logic: deduct from oldest unexpired points buckets
		$active_buckets = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, points_remaining FROM %i WHERE account_id = %d AND type = 'earn' AND points_remaining > 0 AND (expires_at IS NULL OR expires_at > %s) ORDER BY (expires_at IS NULL) ASC, expires_at ASC, id ASC",
			self::table_transactions(), $account_id, current_time('mysql', 1)
		) );
		
		$remaining_to_deduct = $points;
		foreach ( $active_buckets as $bucket ) {
			if ( $remaining_to_deduct <= 0 ) break;
			$deduct = min( $bucket->points_remaining, $remaining_to_deduct );
			$wpdb->query( $wpdb->prepare(
				"UPDATE %i SET points_remaining = points_remaining - %d WHERE id = %d",
				self::table_transactions(), $deduct, $bucket->id
			) );
			$remaining_to_deduct -= $deduct;
		}

		return self::log_transaction( $account_id, 'spend', $points, $new_balance, $source, $source_id, $campaign_id, $note );
	}

	/**
	 * Adjust points (admin override). Can set absolute value.
	 */
	public static function adjust_points( $account_id, $new_balance, $note = 'Admin adjustment' ) {
		global $wpdb;
		$acct = self::get_account( $account_id );
		if ( ! $acct ) return false;

		$diff = max(0, $new_balance) - $acct->points_balance;
		if ( $diff == 0 ) return true;

		$update = [ 'points_balance' => max( 0, $new_balance ) ];
		if ( $diff > 0 ) {
			$update['points_earned'] = $acct->points_earned + $diff;
			$wpdb->update( self::table_accounts(), $update, [ 'id' => $account_id ] );
			
			$settings = self::get_settings();
			$expiry_days = isset( $settings['points_expiry_days'] ) ? intval( $settings['points_expiry_days'] ) : 0;
			$expires_at = null;
			if ( $expiry_days > 0 ) {
				$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_days} days" ) );
			}
			return self::log_transaction( $account_id, 'adjust', abs( $diff ), max( 0, $new_balance ), 'admin', get_current_user_id(), null, $note, abs( $diff ), $expires_at );
		} else {
			$update['points_spent'] = $acct->points_spent + abs( $diff );
			$wpdb->update( self::table_accounts(), $update, [ 'id' => $account_id ] );
			
			// FIFO Logic
			$active_buckets = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, points_remaining FROM %i WHERE account_id = %d AND type IN ('earn','adjust') AND points_remaining > 0 AND (expires_at IS NULL OR expires_at > %s) ORDER BY (expires_at IS NULL) ASC, expires_at ASC, id ASC",
				self::table_transactions(), $account_id, current_time('mysql', 1)
			) );
			
			$remaining_to_deduct = abs($diff);
			foreach ( $active_buckets as $bucket ) {
				if ( $remaining_to_deduct <= 0 ) break;
				$deduct = min( $bucket->points_remaining, $remaining_to_deduct );
				$wpdb->query( $wpdb->prepare(
					"UPDATE %i SET points_remaining = points_remaining - %d WHERE id = %d",
					self::table_transactions(), $deduct, $bucket->id
				) );
				$remaining_to_deduct -= $deduct;
			}
			return self::log_transaction( $account_id, 'adjust', abs( $diff ), max( 0, $new_balance ), 'admin', get_current_user_id(), null, $note );
		}
	}

	// ═══════════════════════════════════════════════════════════
	// TRANSACTIONS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Log a transaction.
	 */
	public static function log_transaction( $account_id, $type, $points, $balance_after, $source = '', $source_id = 0, $campaign_id = null, $note = '', $points_remaining = 0, $expires_at = null ) {
		global $wpdb;
		return $wpdb->insert( self::table_transactions(), [
			'account_id'       => $account_id,
			'type'             => $type,
			'points'           => $points,
			'balance_after'    => $balance_after,
			'source'           => $source,
			'source_id'        => $source_id,
			'campaign_id'      => $campaign_id,
			'note'             => $note,
			'points_remaining' => $points_remaining,
			'expires_at'       => $expires_at
		] );
	}

	/**
	 * Get transactions for an account.
	 */
	public static function get_transactions( $account_id, $limit = 50, $offset = 0 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE account_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
			self::table_transactions(), $account_id, $limit, $offset
		) );
	}

	/**
	 * Get transaction count for an account.
	 */
	public static function get_transaction_count( $account_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE account_id = %d",
			self::table_transactions(), $account_id
		) );
	}

	/**
	 * Get recent transactions (all accounts, for dashboard).
	 */
	public static function get_recent_transactions( $limit = 10 ) {
		global $wpdb;
		$t_trans = self::table_transactions();
		$t_acct  = self::table_accounts();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT t.*, a.email FROM {$t_trans} t LEFT JOIN {$t_acct} a ON t.account_id = a.id ORDER BY t.created_at DESC LIMIT %d",
			$limit
		) );
	}

	// ═══════════════════════════════════════════════════════════
	// CAMPAIGNS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Get a single campaign.
	 */
	public static function get_campaign( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d", self::table_campaigns(), $id
		) );
	}

	/**
	 * Get all campaigns, optionally filtered.
	 */
	public static function get_campaigns( $args = [] ) {
		global $wpdb;
		$t = self::table_campaigns();
		$where = '1=1';
		$params = [];

		if ( ! empty( $args['status'] ) ) {
			$where .= ' AND status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['type'] ) ) {
			$where .= ' AND type = %s';
			$params[] = $args['type'];
		}

		$sql = "SELECT * FROM {$t} WHERE {$where} ORDER BY priority ASC, id DESC";
		$campaigns = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

		// --- Advanced CRM Rules Engine: Secret Rewards Filtering ---
		$is_frontend = ( ! is_admin() || wp_doing_ajax() ) && ! ( is_user_logged_in() && current_user_can( 'manage_woocommerce' ) );
		if ( $is_frontend && class_exists( 'O100_Privilege_Manager' ) ) {
			$all_secret_rewards = O100_Privilege_Manager::get_all_secret_rewards();
			if ( ! empty( $all_secret_rewards ) ) {
				$allowed_secrets = array();
				if ( is_user_logged_in() ) {
					$loc_id = isset( WC()->session ) ? WC()->session->get( 'o100_location_id' ) : 0;
					$method = isset( WC()->session ) ? WC()->session->get( '_o100_order_method' ) : 'delivery';
					$context = array(
						'branch'     => $loc_id ? intval( $loc_id ) : null,
						'order_type' => $method,
						'timestamp'  => current_time( 'timestamp' ),
					);
					$user_secrets = O100_Privilege_Manager::get_privilege( get_current_user_id(), 'loyalty', 'secret_rewards', $context );
					if ( is_array( $user_secrets ) ) {
						$allowed_secrets = array_map( 'intval', $user_secrets );
					}
				}

				$campaigns = array_filter( $campaigns, function( $camp ) use ( $all_secret_rewards, $allowed_secrets ) {
					$camp_id = intval( $camp->id );
					if ( in_array( $camp_id, $all_secret_rewards ) && ! in_array( $camp_id, $allowed_secrets ) ) {
						return false; // Hide secret campaign
					}
					return true;
				});
				$campaigns = array_values( $campaigns );
			}
		}

		return $campaigns;
	}

	/**
	 * Get active campaigns, optionally by type.
	 */
	public static function get_active_campaigns( $type = '' ) {
		$args = [ 'status' => 'active' ];
		if ( $type ) $args['type'] = $type;
		return self::get_campaigns( $args );
	}

	/**
	 * Insert a campaign.
	 */
	public static function insert_campaign( $data ) {
		global $wpdb;
		$wpdb->insert( self::table_campaigns(), $data );
		return $wpdb->insert_id;
	}

	/**
	 * Update a campaign.
	 */
	public static function update_campaign( $id, $data ) {
		global $wpdb;
		return $wpdb->update( self::table_campaigns(), $data, [ 'id' => $id ] );
	}

	/**
	 * Delete a campaign.
	 */
	public static function delete_campaign( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table_campaigns(), [ 'id' => $id ] );
	}

	/**
	 * Toggle campaign status.
	 */
	public static function toggle_campaign_status( $id ) {
		$camp = self::get_campaign( $id );
		if ( ! $camp ) return false;
		$new_status = $camp->status === 'active' ? 'disabled' : 'active';
		return self::update_campaign( $id, [ 'status' => $new_status ] );
	}

	/**
	 * Get active campaign count.
	 */
	public static function get_campaign_count( $status = '' ) {
		global $wpdb;
		$table = self::table_campaigns();
		if ( $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status != 'trash'" );
	}

	// ═══════════════════════════════════════════════════════════
	// REWARDS (REDEEM RULES)
	// ═══════════════════════════════════════════════════════════

	/**
	 * Get active rewards (Redeem rules).
	 */
	public static function get_active_rewards() {
		return self::get_active_campaigns( 'points_conversion' );
	}

	// ═══════════════════════════════════════════════════════════
	// LEVELS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Get all levels.
	 */
	public static function get_levels() {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM " . self::table_levels() . " ORDER BY sort_order ASC, min_points ASC" );
	}

	/**
	 * Get a single level.
	 */
	public static function get_level( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d", self::table_levels(), $id
		) );
	}

	/**
	 * Insert a level.
	 */
	public static function insert_level( $data ) {
		global $wpdb;
		$wpdb->insert( self::table_levels(), $data );
		return $wpdb->insert_id;
	}

	/**
	 * Update a level.
	 */
	public static function update_level( $id, $data ) {
		global $wpdb;
		return $wpdb->update( self::table_levels(), $data, [ 'id' => $id ] );
	}

	/**
	 * Delete a level.
	 */
	public static function delete_level( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table_levels(), [ 'id' => $id ] );
	}

	/**
	 * Determine the correct level for a given point total.
	 */
	public static function determine_level( $total_points ) {
		global $wpdb;
		$t = self::table_levels();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$t} WHERE min_points <= %d AND (max_points >= %d OR max_points = 0) ORDER BY min_points DESC LIMIT 1",
			$total_points, $total_points
		) );
	}

	// ═══════════════════════════════════════════════════════════
	// PUNCH CARD PROGRESS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Get punch card progress.
	 */
	public static function get_punch_progress( $account_id, $campaign_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE account_id = %d AND campaign_id = %d",
			self::table_punch_progress(), $account_id, $campaign_id
		) );
	}

	/**
	 * Add stamps to punch card.
	 */
	public static function add_stamps( $account_id, $campaign_id, $qty ) {
		global $wpdb;
		$t = self::table_punch_progress();
		$existing = self::get_punch_progress( $account_id, $campaign_id );

		if ( $existing ) {
			return $wpdb->update( $t, [
				'stamps' => $existing->stamps + $qty,
			], [ 'id' => $existing->id ] );
		}

		return $wpdb->insert( $t, [
			'account_id'  => $account_id,
			'campaign_id' => $campaign_id,
			'stamps'      => $qty,
		] );
	}

	/**
	 * Reset punch card after redemption.
	 */
	public static function reset_punch_progress( $account_id, $campaign_id, $required_stamps ) {
		global $wpdb;
		$existing = self::get_punch_progress( $account_id, $campaign_id );
		if ( ! $existing ) return false;

		$remaining = max( 0, $existing->stamps - $required_stamps );
		return $wpdb->update( self::table_punch_progress(), [
			'stamps'         => $remaining,
			'redeemed_count' => $existing->redeemed_count + 1,
		], [ 'id' => $existing->id ] );
	}

	/**
	 * Get all punch progress for a user (across all campaigns).
	 */
	public static function get_all_punch_progress( $account_id ) {
		global $wpdb;
		$t_prog = self::table_punch_progress();
		$t_camp = self::table_campaigns();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT p.*, c.title as campaign_name, c.reward_config
			 FROM {$t_prog} p
			 LEFT JOIN {$t_camp} c ON p.campaign_id = c.id
			 WHERE p.account_id = %d AND c.status = 'active'",
			$account_id
		) );
	}

	// ═══════════════════════════════════════════════════════════
	// SETTINGS (o100_loyalty_settings)
	// ═══════════════════════════════════════════════════════════

	/**
	 * Get all loyalty settings.
	 */
	public static function get_settings() {
		$defaults = [
			'point_rounding'         => 'round',
			'earning_statuses'       => [ 'processing', 'completed' ],
			'removing_statuses'      => [],
			'point_label_plural'     => 'Points',
			'point_label_singular'   => 'Point',
			'reward_label_plural'    => 'Rewards',
			'reward_label_singular'  => 'Reward',
			'reward_code_prefix'     => 'O100-',
			'referral_code_prefix'   => 'REF-',
			'earn_after_discount'    => 'yes',
			'allow_earn_with_coupon' => 'yes',
			'product_message_enable' => 'yes',
			'cart_earn_message'      => 'Complete your order and earn {o100_cart_points} {o100_points_label}',
			'checkout_earn_message'  => 'Complete your order and earn {o100_cart_points} {o100_points_label}',
			'thankyou_message'       => 'You earned {o100_earned_points} {o100_points_label} for this order!',
		];
		$saved = get_option( 'o100_loyalty_settings', [] );
		$settings = wp_parse_args( $saved, $defaults );
		
		// Fallback for global conversion if uninitialized (fixes blank "Ways to redeem" after migration)
		if ( empty($settings['conversion_points']) || $settings['conversion_points'] <= 0 ) {
			$settings['conversion_points'] = 100;
			$settings['conversion_value'] = 1;
		}
		
		return $settings;
	}

	/**
	 * Save loyalty settings.
	 */
	public static function save_settings( $data ) {
		return update_option( 'o100_loyalty_settings', $data );
	}

	// ═══════════════════════════════════════════════════════════
	// DASHBOARD STATS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Get dashboard statistics.
	 */
	public static function get_dashboard_stats() {
		global $wpdb;
		$t_acct = self::table_accounts();
		$t_camp = self::table_campaigns();
		$t_trans = self::table_transactions();

		return [
			'total_customers'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_acct}" ),
			'total_points'     => (int) $wpdb->get_var( "SELECT COALESCE(SUM(points_balance),0) FROM {$t_acct}" ),
			'active_campaigns' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_camp} WHERE status = 'active'" ),
			'total_earned'     => (int) $wpdb->get_var( "SELECT COALESCE(SUM(points),0) FROM {$t_trans} WHERE type = 'earn'" ),
			'total_spent'      => (int) $wpdb->get_var( "SELECT COALESCE(SUM(points),0) FROM {$t_trans} WHERE type = 'spend'" ),
		];
	}

	// ═══════════════════════════════════════════════════════════
	// UTILITIES
	// ═══════════════════════════════════════════════════════════

	/**
	 * Drop all loyalty tables (used in uninstall).
	 */
	public static function drop_tables() {
		global $wpdb;
		$tables = [
			self::table_accounts(),
			self::table_transactions(),
			self::table_campaigns(),
			self::table_levels(),
			self::table_punch_progress(),
		];
		foreach ( $tables as $t ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$t}" );
		}
		delete_option( self::DB_VERSION_KEY );
	}
}

