<?php
/**
 * Order100 CRM Database Operations
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Customers_DB {

	public static function get_table_customers() {
		global $wpdb;
		return $wpdb->prefix . 'o100_customers';
	}

	public static function get_table_lists() {
		global $wpdb;
		return $wpdb->prefix . 'o100_customer_lists';
	}

	public static function get_table_tags() {
		global $wpdb;
		return $wpdb->prefix . 'o100_customer_tags';
	}

	public static function get_table_relationships() {
		global $wpdb;
		return $wpdb->prefix . 'o100_customer_relationships';
	}

	public static function get_table_rules() {
		global $wpdb;
		return $wpdb->prefix . 'o100_customer_rules';
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$tbl_customers = self::get_table_customers();
		$sql_customers = "CREATE TABLE $tbl_customers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) unsigned DEFAULT 0,
			email varchar(100) NOT NULL,
			first_name varchar(50) DEFAULT '',
			last_name varchar(50) DEFAULT '',
			phone varchar(30) DEFAULT '',
			total_spent decimal(10,2) DEFAULT 0.00,
			total_orders int(11) DEFAULT 0,
			last_order_date datetime DEFAULT NULL,
			birthday date DEFAULT NULL,
			status varchar(20) DEFAULT 'subscribed',
			acquisition_source varchar(50) DEFAULT 'woocommerce',
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY wp_user_id (wp_user_id)
		) $charset_collate;";
		dbDelta( $sql_customers );

		$tbl_lists = self::get_table_lists();
		$sql_lists = "CREATE TABLE $tbl_lists (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(100) NOT NULL,
			slug varchar(100) NOT NULL,
			description text,
			is_system tinyint(1) DEFAULT 0,
			is_auto tinyint(1) DEFAULT 0,
			auto_logic varchar(10) DEFAULT 'all',
			auto_conditions longtext,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset_collate;";
		dbDelta( $sql_lists );

		$tbl_tags = self::get_table_tags();
		$sql_tags = "CREATE TABLE $tbl_tags (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(100) NOT NULL,
			slug varchar(100) NOT NULL,
			description text,
			is_system tinyint(1) DEFAULT 0,
			is_auto tinyint(1) DEFAULT 0,
			auto_logic varchar(10) DEFAULT 'all',
			auto_conditions longtext,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset_collate;";
		dbDelta( $sql_tags );

		$tbl_relationships = self::get_table_relationships();
		$sql_relationships = "CREATE TABLE $tbl_relationships (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) unsigned NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			object_type varchar(20) NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY object_id_type (object_id, object_type)
		) $charset_collate;";
		dbDelta( $sql_relationships );

		$tbl_rules = self::get_table_rules();
		$sql_rules = "CREATE TABLE $tbl_rules (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			status varchar(20) DEFAULT 'active',
			target_type varchar(50) DEFAULT 'tags',
			target_ids text,
			privileges longtext,
			restrictions longtext,
			priority int(11) DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql_rules );

		self::upgrade_schema();
	}

	public static function upgrade_schema() {
		global $wpdb;
		$tbl_customers = self::get_table_customers();
		
		// Check if status column exists
		$column_exists = $wpdb->query("SHOW COLUMNS FROM {$tbl_customers} LIKE 'status'");
		if ( ! $column_exists ) {
			$wpdb->query("ALTER TABLE {$tbl_customers} ADD COLUMN status varchar(20) DEFAULT 'subscribed' AFTER last_order_date");
		}

		$tbl_lists = self::get_table_lists();
		$column_exists_auto = $wpdb->query("SHOW COLUMNS FROM {$tbl_lists} LIKE 'is_auto'");
		if ( ! $column_exists_auto ) {
			$wpdb->query("ALTER TABLE {$tbl_lists} ADD COLUMN is_auto tinyint(1) DEFAULT 0 AFTER is_system");
			$wpdb->query("ALTER TABLE {$tbl_lists} ADD COLUMN auto_logic varchar(10) DEFAULT 'all' AFTER is_auto");
			$wpdb->query("ALTER TABLE {$tbl_lists} ADD COLUMN auto_conditions longtext AFTER auto_logic");
		}

		$tbl_tags = self::get_table_tags();
		$column_exists_auto_tags = $wpdb->query("SHOW COLUMNS FROM {$tbl_tags} LIKE 'is_auto'");
		if ( ! $column_exists_auto_tags ) {
			$wpdb->query("ALTER TABLE {$tbl_tags} ADD COLUMN is_auto tinyint(1) DEFAULT 0 AFTER is_system");
			$wpdb->query("ALTER TABLE {$tbl_tags} ADD COLUMN auto_logic varchar(10) DEFAULT 'all' AFTER is_auto");
			$wpdb->query("ALTER TABLE {$tbl_tags} ADD COLUMN auto_conditions longtext AFTER auto_logic");
		}
	}

	// ═══════════════════════════════════════════════════════════
	// TAGS & LISTS MANAGEMENT
	// ═══════════════════════════════════════════════════════════

	public static function get_lists( $include_system = true ) {
		global $wpdb;
		$table = self::get_table_lists();
		$where = $include_system ? '' : 'WHERE is_system = 0';
		return $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY title ASC" );
	}

	public static function add_list( $title, $description = '', $is_system = 0, $is_auto = 0, $auto_logic = 'all', $auto_conditions = '' ) {
		global $wpdb;
		$slug = sanitize_title( $title );
		
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . self::get_table_lists() . " WHERE slug = %s", $slug ) );
		if ( $existing ) return $existing; // Return existing ID

		$wpdb->insert(
			self::get_table_lists(),
			[ 
				'title' => $title, 
				'slug' => $slug, 
				'description' => $description,
				'is_system' => $is_system,
				'is_auto' => $is_auto,
				'auto_logic' => $auto_logic,
				'auto_conditions' => $auto_conditions,
				'created_at' => current_time( 'mysql' ), 
				'updated_at' => current_time( 'mysql' ) 
			],
			[ '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' ]
		);
		return $wpdb->insert_id;
	}

	public static function delete_list( $id ) {
		global $wpdb;
		$is_system = $wpdb->get_var( $wpdb->prepare( "SELECT is_system FROM " . self::get_table_lists() . " WHERE id = %d", $id ) );
		if ( $is_system ) return false;

		$wpdb->delete( self::get_table_lists(), [ 'id' => $id ] );
		$wpdb->delete( self::get_table_relationships(), [ 'object_id' => $id, 'object_type' => 'list' ] );
		return true;
	}

	public static function get_tags( $include_system = true ) {
		global $wpdb;
		$table = self::get_table_tags();
		$where = $include_system ? '' : 'WHERE is_system = 0';
		return $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY title ASC" );
	}

	public static function add_tag( $title, $description = '', $is_system = 0, $is_auto = 0, $auto_logic = 'all', $auto_conditions = '' ) {
		global $wpdb;
		$slug = sanitize_title( $title );

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . self::get_table_tags() . " WHERE slug = %s", $slug ) );
		if ( $existing ) return $existing; // Return existing ID

		$wpdb->insert(
			self::get_table_tags(),
			[ 
				'title' => $title, 
				'slug' => $slug, 
				'description' => $description,
				'is_system' => $is_system,
				'is_auto' => $is_auto,
				'auto_logic' => $auto_logic,
				'auto_conditions' => $auto_conditions,
				'created_at' => current_time( 'mysql' ), 
				'updated_at' => current_time( 'mysql' ) 
			],
			[ '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' ]
		);
		return $wpdb->insert_id;
	}

	public static function delete_tag( $id ) {
		global $wpdb;
		$is_system = $wpdb->get_var( $wpdb->prepare( "SELECT is_system FROM " . self::get_table_tags() . " WHERE id = %d", $id ) );
		if ( $is_system ) return false;

		$wpdb->delete( self::get_table_tags(), [ 'id' => $id ] );
		$wpdb->delete( self::get_table_relationships(), [ 'object_id' => $id, 'object_type' => 'tag' ] );
		return true;
	}

	// ═══════════════════════════════════════════════════════════
	// CUSTOMER RELATIONSHIPS
	// ═══════════════════════════════════════════════════════════

	public static function track_manual_override( $customer_id, $object_id, $object_type, $action_type ) {
		global $wpdb;
		$wp_user_id = $wpdb->get_var( $wpdb->prepare( "SELECT wp_user_id FROM " . self::get_table_customers() . " WHERE id = %d", $customer_id ) );
		
		if ( ! $wp_user_id ) {
			return;
		}

		$overrides = get_user_meta( $wp_user_id, 'o100_crm_manual_overrides', true );
		if ( ! is_array( $overrides ) ) {
			$overrides = [ 'removed_tags' => [], 'added_tags' => [], 'removed_lists' => [], 'added_lists' => [] ];
		}
		
		$k_add = $object_type === 'tag' ? 'added_tags' : 'added_lists';
		$k_rem = $object_type === 'tag' ? 'removed_tags' : 'removed_lists';
		
		if ( $action_type === 'add' ) {
			if ( ! in_array( $object_id, $overrides[ $k_add ] ) ) {
				$overrides[ $k_add ][] = $object_id;
			}
			$overrides[ $k_rem ] = array_diff( $overrides[ $k_rem ], [ $object_id ] );
		} else {
			if ( ! in_array( $object_id, $overrides[ $k_rem ] ) ) {
				$overrides[ $k_rem ][] = $object_id;
			}
			$overrides[ $k_add ] = array_diff( $overrides[ $k_add ], [ $object_id ] );
		}
		
		update_user_meta( $wp_user_id, 'o100_crm_manual_overrides', $overrides );
	}

	public static function assign_tag_to_customer( $customer_id, $tag_id ) {
		global $wpdb;
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . self::get_table_relationships() . " WHERE customer_id = %d AND object_id = %d AND object_type = 'tag'", $customer_id, $tag_id ) );
		if ( ! $existing ) {
			$result = $wpdb->insert( self::get_table_relationships(), [ 'customer_id' => $customer_id, 'object_id' => $tag_id, 'object_type' => 'tag' ], [ '%d', '%d', '%s' ] );
			if ( $result ) {
				do_action( 'o100_crm_tag_added', $customer_id, $tag_id );
			}
		}
	}

	public static function remove_tag_from_customer( $customer_id, $tag_id ) {
		global $wpdb;
		$result = $wpdb->delete( self::get_table_relationships(), [ 'customer_id' => $customer_id, 'object_id' => $tag_id, 'object_type' => 'tag' ], [ '%d', '%d', '%s' ] );
		if ( $result ) {
			do_action( 'o100_crm_tag_removed', $customer_id, $tag_id );
		}
	}

	public static function assign_list_to_customer( $customer_id, $list_id ) {
		global $wpdb;
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . self::get_table_relationships() . " WHERE customer_id = %d AND object_id = %d AND object_type = 'list'", $customer_id, $list_id ) );
		if ( ! $existing ) {
			$result = $wpdb->insert( self::get_table_relationships(), [ 'customer_id' => $customer_id, 'object_id' => $list_id, 'object_type' => 'list' ], [ '%d', '%d', '%s' ] );
			if ( $result ) {
				do_action( 'o100_crm_list_added', $customer_id, $list_id );
			}
		}
	}

	public static function remove_list_from_customer( $customer_id, $list_id ) {
		global $wpdb;
		$result = $wpdb->delete( self::get_table_relationships(), [ 'customer_id' => $customer_id, 'object_id' => $list_id, 'object_type' => 'list' ], [ '%d', '%d', '%s' ] );
		if ( $result ) {
			do_action( 'o100_crm_list_removed', $customer_id, $list_id );
		}
	}

	public static function clear_customer_system_tags( $customer_id ) {
		global $wpdb;
		$rel_table = self::get_table_relationships();
		$tag_table = self::get_table_tags();
		
		$sql = $wpdb->prepare( "
			SELECT r.id 
			FROM {$rel_table} r
			JOIN {$tag_table} t ON r.object_id = t.id
			WHERE r.customer_id = %d AND r.object_type = 'tag' AND t.is_system = 1
		", $customer_id );
		
		$rel_ids = $wpdb->get_col( $sql );
		if ( ! empty( $rel_ids ) ) {
			$id_list = implode( ',', array_map( 'intval', $rel_ids ) );
			$wpdb->query( "DELETE FROM {$rel_table} WHERE id IN ($id_list)" );
		}
	}

	public static function get_customer_tags( $customer_id ) {
		global $wpdb;
		$rel_table = self::get_table_relationships();
		$tag_table = self::get_table_tags();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT t.* FROM {$tag_table} t
			INNER JOIN {$rel_table} r ON t.id = r.object_id
			WHERE r.customer_id = %d AND r.object_type = 'tag'",
			$customer_id
		) );
	}

	public static function get_customer_lists( $customer_id ) {
		global $wpdb;
		$rel_table = self::get_table_relationships();
		$list_table = self::get_table_lists();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT l.* FROM {$list_table} l
			INNER JOIN {$rel_table} r ON l.id = r.object_id
			WHERE r.customer_id = %d AND r.object_type = 'list'",
			$customer_id
		) );
	}

	// ═══════════════════════════════════════════════════════════
	// CUSTOMER RECORD OPERATIONS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Insert a new customer record.
	 *
	 * @param array $data Column => value pairs for wp_o100_customers.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public static function insert_customer( $data ) {
		global $wpdb;
		$defaults = [
			'status'             => 'subscribed',
			'acquisition_source' => 'woocommerce',
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
		];
		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert( self::get_table_customers(), $data );
		if ( $result ) {
			$insert_id = $wpdb->insert_id;
			do_action( 'o100_crm_customer_created', $insert_id, $data );
			return $insert_id;
		}
		return false;
	}

	/**
	 * Update a customer's status with old/new tracking.
	 *
	 * @param int    $customer_id CRM customer ID.
	 * @param string $new_status  New status value (subscribed|unsubscribed|pending|bounced).
	 * @return bool True on success, false on failure or no change.
	 */
	public static function update_customer_status( $customer_id, $new_status ) {
		global $wpdb;
		$tbl = self::get_table_customers();

		$old_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$tbl} WHERE id = %d", $customer_id ) );
		if ( $old_status === null || $old_status === $new_status ) {
			return false;
		}

		$result = $wpdb->update(
			$tbl,
			[ 'status' => $new_status, 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => $customer_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		if ( $result !== false ) {
			do_action( 'o100_crm_status_changed', $customer_id, $new_status, $old_status );
			return true;
		}
		return false;
	}
}

// Temporary hook to update tables during development
add_action( 'admin_init', array( 'O100_Customers_DB', 'create_tables' ) );
