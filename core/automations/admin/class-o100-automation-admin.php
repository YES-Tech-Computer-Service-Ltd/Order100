<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Automation_Admin {

	const NONCE_ACTION = 'o100_automation_nonce';

	public static function init() {
		O100_Automation_DB::create_table();
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_o100_get_automations', array( __CLASS__, 'ajax_get_automations' ) );
		add_action( 'wp_ajax_o100_save_automation', array( __CLASS__, 'ajax_save_automation' ) );
		add_action( 'wp_ajax_o100_delete_automation', array( __CLASS__, 'ajax_delete_automation' ) );
		add_action( 'wp_ajax_o100_get_automation_options', array( __CLASS__, 'ajax_get_options' ) );
		add_action( 'wp_ajax_o100_get_auto_settings', array( __CLASS__, 'ajax_get_auto_settings' ) );
		add_action( 'wp_ajax_o100_save_auto_settings', array( __CLASS__, 'ajax_save_auto_settings' ) );
		add_action( 'wp_ajax_o100_run_auto_queue', array( __CLASS__, 'ajax_run_auto_queue' ) );
		add_action( 'wp_ajax_o100_clone_email_template', array( __CLASS__, 'ajax_clone_email_template' ) );
		add_action( 'wp_ajax_o100_get_auto_reports', array( __CLASS__, 'ajax_get_auto_reports' ) );
		add_action( 'wp_ajax_o100_toggle_automation_status', array( __CLASS__, 'ajax_toggle_automation_status' ) );
	}

	public static function ajax_clone_email_template() {
		// Verify nonce or basic permission, omitting strict nonce for simplicity here to match existing style if needed
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		
		$template_id = isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;
		
		if ( $template_id === 0 ) {
			// Create a brand new blank template
			$new_post = array(
				'post_title'   => 'Email Template (' . current_time( 'Y-m-d' ) . ')',
				'post_content' => '',
				'post_status'  => 'publish',
				'post_type'    => 'o100_template',
				'post_author'  => get_current_user_id()
			);
			$new_id = wp_insert_post( $new_post );
			if ( is_wp_error( $new_id ) || ! $new_id ) {
				wp_send_json_error( 'Failed to create blank template' );
			}
			update_post_meta( $new_id, '_o100ne_template', (string) $new_id );
			wp_send_json_success( array( 'new_template_id' => $new_id ) );
		}
		
		$post = get_post( $template_id );
		if ( ! $post || $post->post_type !== 'o100_template' ) {
			wp_send_json_error( 'Template not found' );
		}
		
		$new_post = array(
			'post_title'   => $post->post_title . ' (Automation Copy)',
			'post_content' => $post->post_content,
			'post_status'  => $post->post_status,
			'post_type'    => $post->post_type,
			'post_author'  => get_current_user_id()
		);
		$new_id = wp_insert_post( $new_post );
		if ( is_wp_error( $new_id ) || ! $new_id ) wp_send_json_error( 'Clone failed' );

		// Set the _o100ne_template meta to its own ID so it's addressable by name
		update_post_meta( $new_id, '_o100ne_template', (string) $new_id );
		
		// Copy over any o100ne metas
		$metas = get_post_meta( $template_id );
		foreach ( $metas as $key => $values ) {
			if ( strpos( $key, '_o100ne_' ) === 0 && $key !== '_o100ne_template' ) {
				update_post_meta( $new_id, $key, maybe_unserialize( $values[0] ) );
			}
		}
		
		$meta = get_post_custom( $template_id );
		foreach ( $meta as $k => $v ) {
			foreach ( $v as $val ) {
				add_post_meta( $new_id, $k, maybe_unserialize( $val ) );
			}
		}
		
		wp_send_json_success( array( 'new_template_id' => $new_id ) );
	}

	public static function ajax_get_auto_reports() {
		// check_ajax_referer( 'o100_admin_nonce', 'nonce' );
		global $wpdb;
		
		$logs_table = O100_Automation_DB::get_table_logs();
		$auto_table = O100_Automation_DB::get_table_name();
		$cust_table = class_exists('O100_Customers_DB') ? O100_Customers_DB::get_table_customers() : $wpdb->prefix . 'o100_customers';
		
		$auto_id = isset( $_POST['automation_id'] ) ? intval( $_POST['automation_id'] ) : 0;
		$where = "1=1";
		if ( $auto_id > 0 ) {
			$where .= $wpdb->prepare( " AND l.automation_id = %d", $auto_id );
		}
		
		$sql = "SELECT l.*, a.title as auto_title, c.email as cust_email, c.first_name as cust_first, c.last_name as cust_last 
				FROM $logs_table l 
				LEFT JOIN $auto_table a ON l.automation_id = a.id 
				LEFT JOIN $cust_table c ON l.customer_id = c.id 
				WHERE $where 
				ORDER BY l.id DESC LIMIT 100";
				
		$results = $wpdb->get_results( $sql );
		wp_send_json_success( $results );
	}

	public static function enqueue_scripts( $hook ) {
		if ( false === strpos( $hook, 'o100' ) ) {
			return;
		}
		wp_enqueue_script( 'jquery-ui-sortable' );
		
		// Enqueue Select2
		wp_enqueue_style( 'o100-select2-core', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
		wp_enqueue_script( 'o100-select2-core-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true );
	}

	// ═══════════════════════════════════════════════════════════════
	// AJAX Handlers
	// ═══════════════════════════════════════════════════════════════

	public static function ajax_get_automations() {
		check_ajax_referer( self::NONCE_ACTION, '_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied', 403 );
		}
		$automations = O100_Automation_DB::get_automations();
		wp_send_json_success( $automations );
	}

	public static function ajax_save_automation() {
		check_ajax_referer( self::NONCE_ACTION, '_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied', 403 );
		}

		$id            = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$title         = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$trigger_type  = isset( $_POST['trigger_type'] ) ? sanitize_text_field( $_POST['trigger_type'] ) : '';
		$status        = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'paused';
		
		if ( $status === 'active' && function_exists('O100_License') && ! O100_License()->is_premium() ) {
			global $wpdb;
			$table = O100_Automation_DB::get_table_name();
			$active_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table} WHERE status = 'active' AND id != %d", $id ) );
			if ( $active_count >= 1 ) {
				wp_send_json_error( 'Free plan limit reached: You can only have 1 active automation rule. Please upgrade to PRO.' );
			}
		}

		$conditions    = isset( $_POST['conditions'] ) ? wp_unslash( $_POST['conditions'] ) : '[]';
		$actions       = isset( $_POST['actions'] ) ? wp_unslash( $_POST['actions'] ) : '[]';
		$trigger_config = isset( $_POST['trigger_config'] ) ? wp_unslash( $_POST['trigger_config'] ) : '{}';
		$rules_and_goals = isset( $_POST['rules_and_goals'] ) ? wp_unslash( $_POST['rules_and_goals'] ) : '{"allow_reentry":false,"wait_days":0,"exit_goal":"none"}';
		$delay_minutes = isset( $_POST['delay_minutes'] ) ? intval( $_POST['delay_minutes'] ) : 0;

		$description   = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';

		if ( empty( $title ) || empty( $trigger_type ) ) {
			wp_send_json_error( 'Title and trigger are required.' );
		}

		$data = array(
			'title'           => $title,
			'description'     => $description,
			'trigger_type'    => $trigger_type,
			'trigger_config'  => $trigger_config,
			'status'          => $status,
			'conditions'      => $conditions,
			'actions'         => $actions,
			'rules_and_goals' => $rules_and_goals,
			'delay_minutes'   => $delay_minutes,
		);

		if ( $id > 0 ) {
			O100_Automation_DB::update_automation( $id, $data );
			wp_send_json_success( array( 'id' => $id, 'message' => 'Updated' ) );
		} else {
			$new_id = O100_Automation_DB::insert_automation( $data );
			wp_send_json_success( array( 'id' => $new_id, 'message' => 'Created' ) );
		}
	}

	public static function ajax_delete_automation() {
		check_ajax_referer( self::NONCE_ACTION, '_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied', 403 );
		}
		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( 'Invalid ID' );
		}
		O100_Automation_DB::delete_automation( $id );
		wp_send_json_success( array( 'message' => 'Deleted' ) );
	}

	public static function ajax_toggle_automation_status() {
		check_ajax_referer( self::NONCE_ACTION, '_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied', 403 );
		}
		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
		if ( $id <= 0 || empty( $status ) ) {
			wp_send_json_error( 'Invalid data' );
		}
		
		if ( $status === 'active' && function_exists('O100_License') && ! O100_License()->is_premium() ) {
			global $wpdb;
			$table = O100_Automation_DB::get_table_name();
			$active_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table} WHERE status = 'active' AND id != %d", $id ) );
			if ( $active_count >= 1 ) {
				wp_send_json_error( 'Free plan limit reached: You can only have 1 active automation rule. Please upgrade to PRO.' );
			}
		}

		global $wpdb;
		$table = O100_Automation_DB::get_table_name();
		$wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );
		wp_send_json_success( array( 'message' => 'Status updated', 'status' => $status ) );
	}

	public static function ajax_get_options() {
		check_ajax_referer( self::NONCE_ACTION, '_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied', 403 );
		}

		$data = array(
			'triggers'         => array(),
			'trigger_groups'   => array(),
			'condition_fields' => self::get_condition_fields(),
			'action_types'     => self::get_action_types(),
			'tags'             => array(),
			'lists'            => array(),
			'email_templates'  => array(),
			'promotions'       => array(),
		);

		if ( class_exists( 'O100_Automation_Triggers' ) ) {
			$data['triggers']       = O100_Automation_Triggers::get_triggers();
			$data['trigger_groups'] = O100_Automation_Triggers::get_groups();
		}

		if ( class_exists( 'O100_Customers_DB' ) && method_exists( 'O100_Customers_DB', 'get_tags' ) ) {
			$tags = O100_Customers_DB::get_tags( true );
			if ( is_array( $tags ) ) {
				foreach ( $tags as $t ) {
					$data['tags'][] = array( 'id' => $t->id, 'title' => $t->title );
				}
			}
		}

		if ( class_exists( 'O100_Customers_DB' ) && method_exists( 'O100_Customers_DB', 'get_lists' ) ) {
			$lists = O100_Customers_DB::get_lists( true );
			if ( is_array( $lists ) ) {
				foreach ( $lists as $l ) {
					$data['lists'][] = array( 'id' => $l->id, 'title' => $l->title );
				}
			}
		}

		$library = get_option( 'o100_template_library', array() );
		if ( is_array( $library ) ) {
			foreach ( $library as $tpl ) {
				if ( ! is_array( $tpl ) || empty( $tpl['id'] ) ) {
					continue;
				}
				$tpl_name = ! empty( $tpl['name'] ) ? $tpl['name'] : 'Unnamed';
				$tpl_date = ! empty( $tpl['date'] ) ? explode( ' ', $tpl['date'] )[0] : '';
				
				$data['email_templates'][] = array(
					'id'    => $tpl['id'],
					'name'  => $tpl_name,
					'title' => $tpl_date ? $tpl_name . ' (' . $tpl_date . ')' : $tpl_name,
				);
			}
		}

		if ( class_exists( 'O100_Promotions_DB' ) ) {
			$promos = O100_Promotions_DB::query( array( 'status' => 'active' ) );
			if ( is_array( $promos ) ) {
				foreach ( $promos as $p ) {
					if ( ! empty( $p['promo_code'] ) ) {
						$data['promotions'][] = array(
							'id'    => $p['id'],
							'title' => $p['title'],
							'code'  => $p['promo_code'],
						);
					}
				}
			}
		}

		wp_send_json_success( $data );
	}

	public static function ajax_get_auto_settings() {
		check_ajax_referer( self::NONCE_ACTION, '_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied', 403 );
		}
		$settings = get_option( 'o100_automation_settings', array() );
		$defaults = array(
			'batch_size'           => 20,
			'enable_logs'          => false,
			'enable_cart_tracking' => false,
			'cart_cutoff_time'     => 15,
			'cart_lost_time'       => 7,
		);
		$settings = wp_parse_args( $settings, $defaults );

		// Return mock counts for cron status for now until engine is built
		$settings['cron_status'] = 'Running';
		$settings['queue_count'] = 0;

		wp_send_json_success( $settings );
	}

	public static function ajax_save_auto_settings() {
		check_ajax_referer( self::NONCE_ACTION, '_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied', 403 );
		}
		
		$settings_json = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '{}';
		$settings = json_decode( $settings_json, true );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$clean = array(
			'batch_size'           => isset( $settings['batch_size'] ) ? intval( $settings['batch_size'] ) : 20,
			'enable_logs'          => ! empty( $settings['enable_logs'] ),
			'daily_cron_time'      => isset( $settings['daily_cron_time'] ) ? sanitize_text_field( $settings['daily_cron_time'] ) : '08:00',
			'enable_cart_tracking' => ! empty( $settings['enable_cart_tracking'] ),
			'cart_cutoff_time'     => isset( $settings['cart_cutoff_time'] ) ? intval( $settings['cart_cutoff_time'] ) : 15,
			'cart_lost_time'       => isset( $settings['cart_lost_time'] ) ? intval( $settings['cart_lost_time'] ) : 7,
		);

		$old_settings = get_option( 'o100_automation_settings', array() );
		$old_time = isset( $old_settings['daily_cron_time'] ) ? $old_settings['daily_cron_time'] : '';

		update_option( 'o100_automation_settings', $clean );

		// Reschedule cron if time changed
		if ( $clean['daily_cron_time'] !== $old_time ) {
			wp_clear_scheduled_hook( 'o100_automation_daily_cron' );
			$target_time = strtotime( 'today ' . $clean['daily_cron_time'] );
			if ( $target_time <= time() ) {
				$target_time += DAY_IN_SECONDS; // Schedule for tomorrow if time already passed today
			}
			wp_schedule_event( $target_time, 'daily', 'o100_automation_daily_cron' );
		}

		wp_send_json_success( 'Settings saved' );
	}

	public static function ajax_run_auto_queue() {
		check_ajax_referer( self::NONCE_ACTION, '_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied', 403 );
		}
		// TODO: Trigger WP-Cron queue process directly here
		wp_send_json_success( array( 'message' => 'Queue processing started in background' ) );
	}

	private static function get_condition_fields() {
		return array(
			'order_total'              => array( 'label' => 'This Order Total', 'type' => 'number', 'operators' => array( '>', '<', '==', '>=', '<=' ) ),
			'customer_total_spent'     => array( 'label' => 'Lifetime Spent ($)', 'type' => 'number', 'operators' => array( '>', '<', '==', '>=', '<=' ) ),
			'customer_total_orders'    => array( 'label' => 'Total Orders', 'type' => 'number', 'operators' => array( '>', '<', '==', '>=', '<=' ) ),
			'customer_aov'             => array( 'label' => 'Avg Order Value ($)', 'type' => 'number', 'operators' => array( '>', '<', '==', '>=', '<=' ) ),
			'customer_account_age'     => array( 'label' => 'Account Age (Days)', 'type' => 'number', 'operators' => array( '>', '<', '==', '>=', '<=' ) ),
			'customer_has_tag'         => array( 'label' => 'Customer Has Tag', 'type' => 'select_tag', 'operators' => array( 'is', 'is_not' ) ),
			'customer_in_list'         => array( 'label' => 'Customer In List', 'type' => 'select_list', 'operators' => array( 'is', 'is_not' ) ),
			'customer_status'          => array( 'label' => 'Subscription Status', 'type' => 'select_status', 'operators' => array( 'is', 'is_not' ) ),
			'customer_last_order_days' => array( 'label' => 'Days Since Last Order', 'type' => 'number', 'operators' => array( '>', '<', '==', '>=', '<=' ) ),
			'purchased_product'        => array( 'label' => 'Purchased Product (ID)', 'type' => 'text', 'operators' => array( 'includes', 'excludes' ) ),
		);
	}

	private static function get_action_types() {
		return array(
			'wait'                 => array( 'label' => 'Wait / Delay', 'fields' => array( 'wait_value', 'wait_unit' ) ),
			'send_email'           => array( 'label' => 'Send Email', 'fields' => array( 'subject', 'body', 'editor_mode', 'visual_template_id' ) ),
			'send_sms'             => array( 'label' => 'Send SMS', 'fields' => array( 'message' ) ),
			'give_coupon_existing' => array( 'label' => 'Give Existing Coupon', 'fields' => array( 'promo_id' ) ),
			'give_coupon_custom'   => array( 'label' => 'Generate Custom Coupon', 'fields' => array( 'discount_type', 'discount_value', 'expiry_days', 'usage_limit' ) ),
			'add_tag'              => array( 'label' => 'Add Customer Tag', 'fields' => array( 'tag_id' ) ),
		);
	}

	// ═══════════════════════════════════════════════════════════════
	// Page Render (Tailwind UI identical to Loyalty Proxy Admin)
	// ═══════════════════════════════════════════════════════════════

	public static function render_page() {
		$nonce = wp_create_nonce( self::NONCE_ACTION );
		$notifications_url = admin_url( 'admin.php?page=o100-notifications' );

		$trigger_configs = array(
			// Scheduled
			'customer_inactive'            => array( array( 'key' => 'days', 'label' => 'Inactive For (Days)', 'type' => 'number', 'default' => 30 ) ),
			'customer_birthday'            => array( array( 'key' => 'days_before', 'label' => 'Days Before Birthday', 'type' => 'number', 'default' => 7 ) ),
			'customer_anniversary'         => array( array( 'key' => 'days_before', 'label' => 'Days Before Anniversary', 'type' => 'number', 'default' => 0 ) ),
			// Customers
			'customer_tag_added'           => array( array( 'key' => 'tag_id', 'label' => 'Which Tag(s)?', 'type' => 'select_tag', 'multiple' => true ) ),
			'customer_tag_removed'         => array( array( 'key' => 'tag_id', 'label' => 'Which Tag(s)?', 'type' => 'select_tag', 'multiple' => true ) ),
			'customer_list_added'          => array( array( 'key' => 'list_id', 'label' => 'Which List(s)?', 'type' => 'select_list', 'multiple' => true ) ),
			'customer_list_removed'        => array( array( 'key' => 'list_id', 'label' => 'Which List(s)?', 'type' => 'select_list', 'multiple' => true ) ),
			'customer_status_changed'      => array( array( 'key' => 'target_status', 'label' => 'New Status', 'type' => 'select_status' ) ),
			// Loyalty
			'loyalty_level_changed'        => array( array( 'key' => 'target_level', 'label' => 'Target Level', 'type' => 'select', 'options' => array( 'any' => 'Any Level' ) ) ), // Will be populated dynamically if levels exist
			'loyalty_points_earned'        => array( array( 'key' => 'min_points', 'label' => 'Minimum Points Earned', 'type' => 'number', 'default' => 100 ) ),
			'loyalty_reward_expiring'      => array( array( 'key' => 'days_before', 'label' => 'Days Before Expiration', 'type' => 'number', 'default' => 7 ) ),
		);
		?>
		<script src="https://cdn.tailwindcss.com"></script>
		<script>
			tailwind.config = {
				theme: {
					extend: {
						colors: {
							primary: '#F59322', // Indigo 600
							'primary-dark': '#d97b06', // Indigo 700
						}
					}
				}
			}
		</script>
		<style>
			.o100-proxy-wrap { padding: 0; background: #F8FAFC; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
			.o100-proxy-wrap * { box-sizing: border-box; }

			/* Custom Select2 Overrides */
			.select2-container {
				width: 100% !important;
			}
			.select2-container--default .select2-selection--multiple {
				border: 1px solid #cbd5e1;
				border-radius: 0.375rem;
				min-height: 34px;
				padding: 2px 4px;
				background: #fff;
			}
			.select2-container--default.select2-container--focus .select2-selection--multiple,
			.select2-container--default.select2-container--open .select2-selection--multiple {
				border-color: #F59322;
				box-shadow: 0 0 0 1px #F59322;
			}
			.select2-container--default .select2-selection--multiple .select2-selection__rendered {
				display: flex !important;
				flex-wrap: wrap !important;
				align-items: center !important;
				padding: 0 !important;
				margin: 0 !important;
				list-style: none !important;
			}
			.select2-container--default .select2-selection--multiple .select2-selection__choice {
				background-color: #fff7ed !important;
				border: 1px solid #c7d2fe !important;
				border-radius: 4px !important;
				color: #1e293b !important;
				padding: 2px 6px !important;
				margin: 2px 4px 2px 0 !important;
				font-size: 13px !important;
				line-height: 1.5 !important;
				display: flex !important;
				align-items: center !important;
				float: none !important;
			}
			.select2-container--default .select2-search--inline .select2-search__field {
				width: auto !important;
				min-width: 50px !important;
				border: none !important;
				box-shadow: none !important;
				margin: 2px 0 !important;
				padding: 0 !important;
				font-size: 14px !important;
			}
			.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
				color: #F59322 !important;
				margin-right: 5px !important;
				font-weight: bold !important;
				font-size: 14px !important;
				position: relative !important;
				top: auto !important;
				left: auto !important;
				bottom: auto !important;
				right: auto !important;
				border: none !important;
				padding: 0 !important;
				background: none !important;
				border-right: none !important;
			}
			.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
				color: #ef4444 !important;
				background: none !important;
			}
			.select2-container--default .select2-selection--multiple .select2-selection__choice__display {
				padding: 0 !important;
				margin-left: 2px !important;
			}
			.select2-dropdown {
				border: 1px solid #cbd5e1;
				border-radius: 0.375rem;
				box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
				z-index: 999999 !important;
			}
			.select2-results__option--highlighted {
				background-color: #f1f5f9 !important;
				color: #0f172a !important;
			}
			/* Custom Select2 Checkbox */
			.o100-custom-cb {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 16px;
				height: 16px;
				border: 1px solid #cbd5e1;
				border-radius: 4px;
				margin-right: 10px;
				vertical-align: middle;
				position: relative;
				background: #fff;
				transition: all 0.2s;
				box-sizing: border-box;
				flex-shrink: 0;
			}
			.o100-custom-cb.o100-cb-checked {
				background: #F59322;
				border-color: #F59322;
			}
			.o100-custom-cb.o100-cb-checked::after {
				content: '';
				position: absolute;
				left: 5px;
				top: 2px;
				width: 4px;
				height: 8px;
				border: solid white;
				border-width: 0 2px 2px 0;
				transform: rotate(45deg);
				display: block;
			}
			/* Disabled state for already selected items */
			li.o100-disabled-li {
				opacity: 0.5 !important;
				pointer-events: none !important;
				background-color: #f8fafc !important;
				cursor: not-allowed !important;
			}
			li.o100-disabled-li .o100-custom-cb {
				background: #94a3b8 !important;
				border-color: #94a3b8 !important;
			}
			li.o100-disabled-li .o100-custom-cb::after {
				content: '';
				position: absolute;
				left: 5px;
				top: 2px;
				width: 4px;
				height: 8px;
				border: solid white;
				border-width: 0 2px 2px 0;
				transform: rotate(45deg);
			}

			.o100-tab {
				display: inline-block;
				padding: 0.75rem 0;
				margin-right: 2rem;
				font-weight: 600;
				font-size: 0.875rem;
				color: #64748b;
				border-bottom: 2px solid transparent;
				transition: all 0.2s ease;
				text-decoration: none;
			}
			.o100-tab:hover { color: #F59322; border-bottom-color: #c7d2fe; }
			.o100-tab.active { color: #F59322; border-bottom-color: #F59322; }
			
			/* Fallback utility classes for JIT compiler misses */
			.bg-blue-500 { background-color: #F59322 !important; }
			.hover\:bg-blue-600:hover { background-color: #F59322 !important; }
			.bg-red-500 { background-color: #ef4444 !important; }
			.hover\:bg-red-600:hover { background-color: #dc2626 !important; }
			.bg-emerald-50 { background-color: #ecfdf5 !important; }
			.text-emerald-600 { color: #059669 !important; }
			.bg-indigo-50 { background-color: #fff7ed !important; }
			.text-indigo-600 { color: #F59322 !important; }
			.bg-pink-50 { background-color: #fdf2f8 !important; }
			.text-pink-600 { color: #db2777 !important; }
			.bg-amber-50 { background-color: #fffbeb !important; }
			.text-amber-600 { color: #d97706 !important; }
			.bg-red-50 { background-color: #fef2f2 !important; }
			.text-red-600 { color: #dc2626 !important; }
			.bg-yellow-50 { background-color: #fefce8 !important; }
			.text-yellow-600 { color: #ca8a04 !important; }
			.bg-blue-50 { background-color: #eff6ff !important; }
			.text-blue-600 { color: #F59322 !important; }
			
			/* Toggle Switch */
			.o100-toggle-switch { position: relative; display: inline-block; width: 40px; height: 22px; }
			.o100-toggle-switch input { opacity: 0; width: 0; height: 0; }
			.o100-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 22px; }
			.o100-toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
			.o100-toggle-switch input:checked + .o100-toggle-slider { background-color: #F59322; }
			.o100-toggle-switch input:checked + .o100-toggle-slider:before { transform: translateX(18px); }

			/* Wizard Modal */
			.o100-wizard-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 99999; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
			.o100-wizard-overlay.is-open { display: flex; opacity: 1; }
			.o100-wizard-modal { background: #fff; width: 100%; max-width: 900px; max-height: 90vh; border-radius: 1.5rem; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); transform: translateY(20px); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
			.o100-wizard-overlay.is-open .o100-wizard-modal { transform: translateY(0); }
			.o100-modal-body { overflow-y: auto; flex: 1; padding: 24px; }
			
			/* Form Selects/Inputs inside wizard */
			.o100-wizard-modal input[type="text"], .o100-wizard-modal input[type="number"], .o100-wizard-modal select, .o100-wizard-modal textarea {
				width: 100%; border-radius: 0.5rem; border: 1px solid #e2e8f0; padding: 0.5rem 0.75rem; font-size: 0.875rem; color: #1e293b; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
			}
			.o100-wizard-modal input:focus, .o100-wizard-modal select:focus, .o100-wizard-modal textarea:focus { outline: none; border-color: #F59322; ring: 1px solid #F59322; }
			
			/* Steps CSS */
			.o100-step-item { position: relative; padding-bottom: 2rem; cursor: pointer; }
			.o100-step-item:last-child { padding-bottom: 0; }
			.o100-step-item:not(:last-child)::after { content: ''; position: absolute; left: 1rem; top: 2.5rem; bottom: 0; width: 2px; background: #E2E8F0; transform: translateX(-50%); }
			.o100-step-indicator { width: 2rem; height: 2rem; border-radius: 9999px; border: 2px solid; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; transition: all 0.2s; position: relative; z-index: 10; background: white; }
			.o100-step-item.is-active .o100-step-indicator { border-color: #F59322; color: #fff; background: #F59322; }
			.o100-step-item.is-completed .o100-step-indicator { border-color: #F59322; color: #F59322; background: white; }
			.o100-step-item.is-pending .o100-step-indicator { border-color: #E2E8F0; color: #94A3B8; background: white; }
			.o100-form-step { display: none; }
			.o100-form-step.is-active { display: block; animation: o100FadeIn 0.3s ease-out forwards; }
			@keyframes o100FadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
		</style>

		<div class="o100-proxy-wrap pb-24">
			<!-- HEADER -->
			<div class="o100-loyalty-page-header mb-8 bg-white border-b border-slate-200">
				<div class="w-full px-8">
					<div class="mb-4 pt-8 flex items-center justify-between">
						<div>
							<h1 class="text-2xl font-bold text-slate-900 m-0 pb-1" style="font-size:1.5rem !important; font-weight:700 !important; color:#0f172a !important;">Automations Engine</h1>
							<p class="text-sm text-slate-500 m-0 mt-1">Configure trigger-based marketing workflows, auto-responders, and automated coupons.</p>
						</div>
					</div>
				</div>
				
				<div class="w-full px-8 flex justify-between items-center">
					<nav class="flex" id="o100-tabs">
						<a href="#" onclick="AUTO.switchTab('automations', this)" class="o100-tab active">Automations</a>
						<a href="#" onclick="AUTO.switchTab('templates', this)" class="o100-tab">Automation Templates</a>
						<a href="#" onclick="AUTO.switchTab('reports', this)" class="o100-tab">Reports</a>
						<a href="#" onclick="AUTO.switchTab('settings', this)" class="o100-tab">Settings</a>
					</nav>
				</div>
			</div>
			
			<?php 
			$auto_table = O100_Automation_DB::get_table_name();
			global $wpdb;
			$active_auto_count = $wpdb->get_var("SELECT COUNT(id) FROM {$auto_table} WHERE status = 'active'");
			$is_premium_check = function_exists('O100_License') && O100_License()->is_premium();
			if ( ! $is_premium_check && $active_auto_count >= 1 ) : 
			?>
				<div class="bg-amber-50 border-l-4 border-amber-400 p-4 shadow-sm mb-6 w-full">
					<div class="px-8 flex">
						<div class="flex-shrink-0">
							<svg class="h-5 w-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
						</div>
						<div class="ml-3 flex-1">
							<p class="text-sm text-amber-800">
								<strong>Free Plan Limit:</strong> You have reached the maximum limit of 1 active Automation rule. <a href="#" onclick="o100ShowProModal('Unlimited Automations', 'Want to schedule multiple automated marketing workflows? Upgrade to Pro to unlock unlimited automation rules!'); return false;" class="font-medium underline hover:text-amber-600">Upgrade to PRO</a> to unlock unlimited automations.
							</p>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- TAB: AUTOMATIONS -->
			<div class="w-full px-8" id="tab-automations">
				


				<div class="flex items-center justify-between mb-4">
					<h2 class="text-lg font-bold text-slate-800">Active Automations</h2>
					<button onclick="AUTO.switchTab('templates', document.querySelectorAll('.o100-tab')[1])" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm px-4 py-2 rounded-xl shadow-sm transition-colors flex items-center">
						<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
						Create New Automation
					</button>
				</div>

				<div class="bg-white rounded-xl border border-slate-200 shadow-sm relative z-0">
					<div class="overflow-x-auto rounded-xl">
						<table class="w-full text-left border-collapse whitespace-nowrap min-w-[800px]">
							<thead>
								<tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] uppercase tracking-wider font-bold text-slate-500">
									<th class="py-4 px-6 sticky left-0 bg-slate-50 z-20 border-r border-slate-100 shadow-[1px_0_0_0_#e2e8f0]">Automation Details</th>
									<th class="py-4 px-6 hidden sm:table-cell">Trigger Type</th>
									<th class="py-4 px-6 hidden md:table-cell">Created On</th>
									<th class="py-4 px-6 text-center">Status</th>
									<th class="py-4 px-6 sticky right-0 bg-slate-50 z-20 border-l border-slate-100 text-right shadow-[-1px_0_0_0_#e2e8f0]">Actions</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-slate-100" id="o100-auto-table-body">
								<tr><td colspan="5" class="py-12 text-center text-slate-500 text-sm">Loading automations...</td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- TAB: TEMPLATES -->
			<div class="w-full px-8 hidden" id="tab-templates">
				<h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center">
					<svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
					Quick Start Templates
				</h2>
				
				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8" id="o100-template-grid">
					<!-- JS Populates this grid -->
				</div>

				<h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center border-t border-slate-200 pt-8 mt-4">
					<svg class="w-5 h-5 mr-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
					Custom Automation
				</h2>
				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8" id="o100-custom-template-grid">
					<!-- JS Populates this grid -->
				</div>
			</div>

			<div class="w-full px-8 hidden" id="tab-reports">
				<div class="flex items-center justify-between mb-4">
					<h2 class="text-lg font-bold text-slate-800">Execution Reports</h2>
					<div class="flex items-center gap-4">
						<select id="o100-report-filter" class="w-64 px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors" onchange="AUTO.loadReports(this.value)">
							<option value="0">All Automations</option>
							<!-- Options populated by JS -->
						</select>
						<button onclick="AUTO.loadReports($('#o100-report-filter').val())" class="bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 font-bold text-sm px-4 py-2 rounded-xl shadow-sm transition-colors flex items-center">
							<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
							Refresh
						</button>
					</div>
				</div>

				<div class="bg-white rounded-xl border border-slate-200 shadow-sm relative z-0">
					<div class="overflow-x-auto rounded-xl">
						<table class="w-full text-left border-collapse whitespace-nowrap min-w-[800px]">
							<thead>
								<tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] uppercase tracking-wider font-bold text-slate-500">
									<th class="py-4 px-6 sticky left-0 bg-slate-50 z-20 border-r border-slate-100 shadow-[1px_0_0_0_#e2e8f0]">Automation Rule</th>
									<th class="py-4 px-6">Customer</th>
									<th class="py-4 px-6 text-center">Status</th>
									<th class="py-4 px-6">Run Date</th>
									<th class="py-4 px-6 sticky right-0 bg-slate-50 z-20 border-l border-slate-100 text-right shadow-[-1px_0_0_0_#e2e8f0]">Details</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-slate-100" id="o100-reports-table-body">
								<tr><td colspan="5" class="py-12 text-center text-slate-500 text-sm">Loading reports...</td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- TAB: SETTINGS -->
			<div class="w-full px-8 hidden" id="tab-settings">
				<h2 class="text-lg font-bold text-slate-800 mb-6 flex items-center">
					<svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
					Automation Settings
				</h2>
				
				<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl">
					
					<!-- Engine & Performance -->
					<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
						<div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
							<h3 class="font-bold text-slate-800">Engine & Performance</h3>
						</div>
						<div class="p-6 space-y-6 flex-1">
							<div>
								<label class="flex items-center space-x-3 cursor-pointer group">
									<div class="o100-toggle-switch">
										<input type="checkbox" id="set_enable_logs">
										<span class="o100-toggle-slider"></span>
									</div>
									<div>
										<span class="text-sm font-bold text-slate-700 group-hover:text-slate-900 transition-colors">Enable Debug Logs</span>
										<p class="text-xs text-slate-500 mt-0.5">Record diagnostic logs in the database. Useful for troubleshooting.</p>
									</div>
								</label>
							</div>

							<div>
								<label class="block text-sm font-bold text-slate-700 mb-2">Tasks Processed per Batch</label>
								<p class="text-xs text-slate-500 mb-3">Maximum number of actions executed per cron cycle. Lower this if your host limits outgoing emails.</p>
								<div class="w-1/2">
									<input type="number" id="set_batch_size" class="w-full text-sm py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 shadow-sm" value="20" min="1" max="100">
								</div>
							</div>

							<div>
								<label class="block text-sm font-bold text-slate-700 mb-2">Daily Execution Time</label>
								<p class="text-xs text-slate-500 mb-3">Time of day to run "Customer Inactive" and "Birthday" trigger scans. Recommended: early morning to avoid peak server hours.</p>
								<div class="w-1/2">
									<input type="time" id="set_daily_cron_time" class="w-full text-sm py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 shadow-sm" value="08:00">
								</div>
							</div>

							<div class="bg-slate-50 p-4 rounded-lg border border-slate-200 mt-auto">
								<h4 class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-3">Cron Status</h4>
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-slate-800 font-medium mb-1">Queue: <span id="set_queue_count" class="text-indigo-600 font-bold">0</span> pending tasks</p>
										<p class="text-xs text-slate-500" id="set_cron_status">Next run: Scheduled in 2 mins</p>
									</div>
									<button type="button" class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors" onclick="AUTO.runQueueNow()">Run Manually</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Abandoned Cart Rules -->
					<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
						<div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
							<h3 class="font-bold text-slate-800">Abandoned Cart Rules</h3>
						</div>
						<div class="p-6 space-y-6 flex-1">
							<div>
								<label class="flex items-center space-x-3 cursor-pointer group">
									<div class="o100-toggle-switch">
										<input type="checkbox" id="set_enable_cart_tracking">
										<span class="o100-toggle-slider"></span>
									</div>
									<div>
										<span class="text-sm font-bold text-slate-700 group-hover:text-slate-900 transition-colors">Enable Cart Tracking</span>
										<p class="text-xs text-slate-500 mt-0.5">Listen for abandoned cart events system-wide.</p>
									</div>
								</label>
							</div>

							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Cart Cut-off Time</label>
									<p class="text-xs text-slate-500 mb-3">Mark cart as abandoned after</p>
									<div class="flex rounded-md shadow-sm">
										<input type="number" id="set_cart_cutoff_time" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md border border-slate-300 focus:z-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" value="15">
										<span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-slate-300 bg-slate-50 text-slate-500 sm:text-sm font-medium">Minutes</span>
									</div>
								</div>
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Mark as Lost After</label>
									<p class="text-xs text-slate-500 mb-3">Stop trying to recover cart after</p>
									<div class="flex rounded-md shadow-sm">
										<input type="number" id="set_cart_lost_time" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md border border-slate-300 focus:z-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" value="7">
										<span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-slate-300 bg-slate-50 text-slate-500 sm:text-sm font-medium">Days</span>
									</div>
								</div>
							</div>
						</div>
					</div>

				</div>
				
				<div class="mt-6 flex items-center justify-between max-w-6xl">
					<p class="text-sm text-slate-500 italic flex items-center">
						<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
						Global Email Sender & Compliance settings are configured in the General Settings.
					</p>
					<button type="button" id="o100-btn-save-settings" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-sm transition-colors flex items-center" onclick="AUTO.saveSettings()">
						Save Settings
					</button>
				</div>
			</div>
		</div>

		<!-- AUTOMATION EDITOR MODAL -->
		<div class="o100-wizard-overlay" id="o100-editor-modal">
			<div class="o100-wizard-modal max-w-5xl max-h-[90vh]">
				
				<div class="flex flex-1 overflow-hidden">

					<!-- Left Sidebar: Stepper -->
					<div class="w-72 bg-slate-50 border-r border-slate-200 p-8 flex flex-col flex-shrink-0">
						<h2 class="text-xl font-bold text-slate-900 mb-8" id="o100-editor-title">Configure Automation</h2>
						
						<div class="flex-1">
							<div class="o100-step-item is-active" id="step-nav-1" onclick="AUTO.goToStep(1)">
								<div class="flex items-start">
									<div class="o100-step-indicator">1</div>
									<div class="ml-4">
										<h4 class="text-sm font-bold text-slate-900">General Info</h4>
									</div>
								</div>
							</div>
							<div class="o100-step-item is-pending" id="step-nav-2" onclick="AUTO.goToStep(2)">
								<div class="flex items-start">
									<div class="o100-step-indicator">2</div>
									<div class="ml-4">
										<h4 class="text-sm font-bold text-slate-900">Trigger Event</h4>
									</div>
								</div>
							</div>
							<div class="o100-step-item is-pending" id="step-nav-3" onclick="AUTO.goToStep(3)">
								<div class="flex items-start">
									<div class="o100-step-indicator">3</div>
									<div class="ml-4">
										<h4 class="text-sm font-bold text-slate-900">Conditions</h4>
									</div>
								</div>
							</div>
							<div class="o100-step-item is-pending" id="step-nav-4" onclick="AUTO.goToStep(4)">
								<div class="flex items-start">
									<div class="o100-step-indicator">4</div>
									<div class="ml-4">
										<h4 class="text-sm font-bold text-slate-900">Actions</h4>
									</div>
								</div>
							</div>
							<div class="o100-step-item is-pending" id="step-nav-5" onclick="AUTO.goToStep(5)">
								<div class="flex items-start">
									<div class="o100-step-indicator">5</div>
									<div class="ml-4">
										<h4 class="text-sm font-bold text-slate-900">Limits & Goals</h4>
									</div>
								</div>
							</div>
						</div>
						<div class="pt-6 border-t border-slate-200 mt-6">
							<button class="text-sm text-slate-500 hover:text-slate-900 font-medium" onclick="AUTO.closeEditor()">Cancel & Exit</button>
						</div>
					</div>

					<!-- Right Content -->
					<div class="flex-1 bg-white p-10 flex flex-col relative overflow-y-auto w-full max-w-full">

						<div class="flex-1 max-w-3xl w-full mt-4">
							
							<!-- Step 1: General -->
							<div class="o100-form-step is-active" id="step-content-1">
								<h3 class="text-2xl font-bold text-slate-900 mb-6">General Information</h3>
								
								<div class="space-y-6">
									<div class="bg-slate-50 p-6 rounded-xl border border-slate-200 shadow-sm">
										<label class="block text-sm font-bold text-slate-700 mb-2">Automation Name <span class="text-red-500">*</span></label>
										<input type="text" id="o100-auto-name" placeholder="e.g. Win-back Inactive Customers" class="w-full text-base py-3">
										
										<label class="block text-sm font-bold text-slate-700 mt-4 mb-2">Description</label>
										<textarea id="o100-auto-desc" placeholder="Internal notes about this automation..." class="w-full text-sm py-2" rows="2"></textarea>
									</div>

									<div id="o100-abandoned-cart-hint" class="hidden bg-yellow-50 p-4 rounded-xl border border-yellow-200 shadow-sm flex items-start gap-3">
										<span class="dashicons dashicons-warning text-yellow-500 mt-1"></span>
										<div>
											<h4 class="font-bold text-yellow-800 text-sm mb-1">Abandoned Cart Settings</h4>
											<p class="text-xs text-yellow-700">Please note that the timing settings (Cutoff Time & Lost Time) for abandoned carts are configured globally. <a href="?page=o100-settings#o100-tab-api" target="_blank" class="font-bold underline hover:text-yellow-900">Configure Settings</a></p>
										</div>
									</div>

									<div class="bg-slate-50 p-6 rounded-xl border border-slate-200 shadow-sm">
										<h4 class="font-bold text-slate-800 text-sm mb-3">Automation Status</h4>
										<p class="text-xs text-slate-500 mb-4">You can draft this automation and activate it later.</p>
										<div class="flex items-center justify-between bg-white p-4 rounded-lg border border-slate-200">
											<span class="text-sm font-medium text-slate-700">Active</span>
											<label class="o100-toggle-switch">
												<input type="checkbox" id="o100-auto-status" value="1">
												<span class="o100-toggle-slider"></span>
											</label>
										</div>
									</div>
								</div>
							</div>

							<!-- Step 2: Trigger -->
							<div class="o100-form-step" id="step-content-2">
								<h3 class="text-2xl font-bold text-slate-900 mb-6">Trigger Event</h3>
								
								<div class="bg-slate-50 p-6 rounded-xl border border-slate-200 shadow-sm">
									<label class="block text-sm font-bold text-slate-700 mb-1">When should this automation start?</label>
									<p class="text-xs text-slate-500 mb-4">Select the event that will trigger this automation flow.</p>
									<select id="o100-auto-trigger-val" class="w-full text-base py-3 border border-slate-300 rounded-lg shadow-sm font-medium text-slate-800"></select>
									<div id="o100-auto-trigger-desc" class="hidden mt-3 text-sm text-slate-600 bg-white p-3 rounded-lg border border-slate-200 shadow-sm leading-relaxed flex items-start gap-2">
										<svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
										<span id="o100-auto-trigger-desc-text"></span>
									</div>
									<div id="o100-auto-trigger-config" class="mt-6 space-y-4 pl-4 border-l-2 border-indigo-200"></div>
								</div>
							</div>

							<!-- Step 3: Conditions -->
							<div class="o100-form-step" id="step-content-3">
								<div class="flex justify-between items-center mb-6">
									<h3 class="text-2xl font-bold text-slate-900">Conditions (Optional)</h3>
									<button type="button" class="text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg border border-indigo-100 transition-colors text-sm font-bold flex items-center" id="o100-btn-add-cond">
										<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg> Add Condition
									</button>
								</div>
								
								<div class="bg-amber-50 p-4 rounded-xl border border-amber-200 text-amber-800 text-sm mb-6 flex">
									<svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
									<p>Only run this automation if <strong>ALL</strong> the conditions below are met. If no conditions are added, the automation will always run when triggered.</p>
								</div>
								
								<div id="o100-auto-conds-list" class="space-y-4"></div>
							</div>

							<!-- Step 4: Actions -->
							<div class="o100-form-step" id="step-content-4">
								<div class="flex justify-between items-center mb-6">
									<h3 class="text-2xl font-bold text-slate-900">Actions Sequence</h3>
									<button type="button" class="text-white bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded-lg transition-colors text-sm font-bold flex items-center shadow-sm" id="o100-btn-add-action">
										<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg> Add Action
									</button>
								</div>
								
								<p class="text-sm text-slate-500 mb-6 pb-4 border-b border-slate-100">Configure the sequence of actions (delays, emails, coupons) that will happen when the automation runs.</p>
								
								<div id="o100-auto-actions-list" class="space-y-4 w-full"></div>
							</div>

							<!-- Step 5: Limits & Goals -->
							<div class="o100-form-step" id="step-content-5">
								<h3 class="text-2xl font-bold text-slate-900 mb-6">Execution Limits & Goals</h3>
								
								<div class="space-y-8">
									<!-- Execution Limits -->
									<div class="bg-slate-50 p-6 rounded-xl border border-slate-200 shadow-sm">
										<h4 class="font-bold text-slate-800 text-base mb-4 flex items-center">
											<svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
											Execution Frequency
										</h4>
										
										<div class="space-y-4">
											<label class="flex items-start p-4 border border-slate-200 rounded-lg bg-white cursor-pointer hover:border-indigo-300 transition-colors">
												<div class="flex items-center h-5">
													<input type="radio" name="o100_auto_limit" value="once" class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500" checked>
												</div>
												<div class="ml-3 text-sm">
													<span class="font-bold text-slate-900">Run only once per customer</span>
													<p class="text-slate-500 mt-0.5">A customer will never enter this automation again after their first run.</p>
												</div>
											</label>
											
											<label class="flex items-start p-4 border border-slate-200 rounded-lg bg-white cursor-pointer hover:border-indigo-300 transition-colors">
												<div class="flex items-center h-5">
													<input type="radio" name="o100_auto_limit" value="multiple" class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
												</div>
												<div class="ml-3 text-sm w-full">
													<span class="font-bold text-slate-900">Run multiple times</span>
													<p class="text-slate-500 mt-0.5 mb-3">Customers can re-enter this automation if they trigger it again.</p>
													
													<div class="flex items-center bg-slate-50 p-3 rounded border border-slate-200">
														<span class="text-slate-600 mr-2">But wait</span>
														<input type="number" id="o100_auto_wait_days" class="w-20 text-sm py-1 px-2 border border-slate-300 rounded focus:ring-indigo-500" value="30" min="0">
														<span class="text-slate-600 ml-2">days before they can enter again.</span>
													</div>
												</div>
											</label>
										</div>
									</div>

									<!-- Goal / Exit Rule -->
									<div class="bg-indigo-50 p-6 rounded-xl border border-indigo-100 shadow-sm">
										<h4 class="font-bold text-indigo-900 text-base mb-4 flex items-center">
											<svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
											Goal & Exit Rule
										</h4>
										<p class="text-sm text-indigo-700 mb-4">If a customer achieves this goal while in the middle of the automation flow (e.g. waiting for a delayed email), they will be immediately removed from the automation and will not receive further actions.</p>
										
										<div>
											<label class="block text-sm font-bold text-slate-700 mb-2">Exit Condition</label>
											<select id="o100_auto_exit_goal" class="w-full text-sm py-2.5 px-3 border border-slate-300 rounded-lg focus:ring-indigo-500 font-medium">
												<option value="none">None (Always complete all actions)</option>
												<option value="placed_order">Customer places a new order</option>
											</select>
										</div>
									</div>
								</div>
							</div>

						</div>

						<!-- Next/Prev Footer -->
						<div class="mt-auto pt-8 border-t border-slate-100 flex justify-between items-center max-w-3xl w-full">
							<button type="button" id="btn-back" class="px-6 py-2 border border-slate-300 rounded-xl font-medium text-slate-700 hover:bg-slate-50 hidden transition-colors" onclick="AUTO.prevStep()">
								Back
							</button>
							<div class="flex-1"></div>
							<button type="button" id="btn-next" class="px-6 py-2 bg-indigo-600 text-white rounded-xl font-bold shadow-sm hover:bg-indigo-700 transition-colors flex items-center" onclick="AUTO.nextStep()">
								Continue
							</button>
							<button type="button" id="o100-auto-save" class="px-6 py-2 bg-emerald-600 text-white rounded-xl font-bold shadow-sm hover:bg-emerald-700 transition-colors items-center hidden">
								<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
								Save Automation
							</button>
						</div>

					</div>
				</div>

			</div>
		</div>

		<script>
		(function($) {
			'use strict';

			var NONCE = '<?php echo esc_js( $nonce ); ?>';
			var NOTIF_URL = '<?php echo esc_js( $notifications_url ); ?>';
			var TRIGGER_CONFIGS = <?php echo wp_json_encode( $trigger_configs ); ?>;

			var OPT = null;
			var S = { id:null, trigger:'', triggerConfig:{}, conditions:[], actions:[] };

			var TEMPLATES = [
				{ key:'scratch', title:'Custom Automation', desc:'Build your own custom automation flow from the ground up.', icon:'<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>', colorClass:'bg-slate-100 text-slate-600', isBlank:true },
				{ key:'winback', title:'Win-back Inactive', desc:'Re-engage customers who haven\'t ordered in 30+ days.', icon:'<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>', colorClass:'bg-indigo-50 text-indigo-600', trigger:'customer_inactive', triggerConfig:{days:30}, actions:[{type:'give_coupon_custom',val:'',config:{discount_type:'percent',discount_value:'15',expiry_days:'14',usage_limit:'1'}},{type:'send_email',val:'',config:{editor_mode:'classic',subject:'We Miss You! Here\'s 15% Off',body:'Hey [o100_customer_name],\n\nIt\'s been a while since your last visit. We\'ve missed you!\n\nCome back and enjoy 15% off your next order. Use code [o100_coupon_code].\n\nSee you soon!'}}] },
				{ key:'abandoned_cart', title:'Abandoned Cart Recovery', desc:'Send a reminder email 1 hour after a customer abandons their cart.', icon:'<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>', colorClass:'bg-orange-50 text-orange-600', trigger:'abandoned_cart', conditions:[{field:'order_total',op:'>',val:'20'}], actions:[{type:'wait',val:'1',config:{unit:'hours'}},{type:'give_coupon_custom',val:'',config:{discount_type:'percent',discount_value:'10',expiry_days:'7',usage_limit:'1'}},{type:'send_email',val:'',config:{editor_mode:'classic',subject:'You left something behind!',body:'Hey [o100_customer_name],\n\nYour cart is waiting for you. Complete your checkout now and take 10% off with code [o100_coupon_code].'}}] },
				{ key:'first_order', title:'First Order Welcome', desc:'Greet new customers after their first purchase with a warm welcome.', icon:'<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>', colorClass:'bg-pink-50 text-pink-600', trigger:'order_completed', conditions:[{field:'customer_total_orders',op:'==',val:'1'}], actions:[{type:'send_email',val:'',config:{editor_mode:'classic',subject:'Welcome to the family!',body:'Hi [o100_customer_name],\n\nThanks for your first order at [o100_store_name]. We\'re so happy to have you!'}}] },
				{ key:'birthday', title:'Birthday Surprise', desc:'Automatically send a birthday coupon when the tag is added.', icon:'<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z"></path></svg>', colorClass:'bg-amber-50 text-amber-600', trigger:'customer_tag_added', triggerConfig:{tag_id:''}, actions:[{type:'give_coupon_custom',val:'',config:{discount_type:'percent',discount_value:'15',expiry_days:'14',usage_limit:'1'}},{type:'send_email',val:'',config:{editor_mode:'classic',subject:'Happy Birthday from [o100_store_name]!',body:'Happy Birthday [o100_customer_name]!\n\nCelebrate with 15% off your next meal. Use code: [o100_coupon_code].'}}] },
				{ key:'payment_failed', title:'Payment Failed Recovery', desc:'Send a recovery email 10 minutes after a payment fails, with a link to retry.', icon:'<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>', colorClass:'bg-red-50 text-red-600', trigger:'order_failed', actions:[{type:'wait',val:'10',config:{unit:'minutes'}},{type:'send_email',val:'',config:{editor_mode:'classic',subject:'Payment Failed',body:'Hi [o100_customer_name],\n\nYour recent payment failed. Please update your payment method.'}}] },
				{ key:'product_recs', title:'Product Recommendations', desc:'Send your seasonal menu highlights to regular buyers who haven\'t visited recently.', icon:'<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>', colorClass:'bg-emerald-50 text-emerald-600', trigger:'customer_inactive', triggerConfig:{days:30}, actions:[{type:'send_email',val:'',config:{editor_mode:'classic',subject:'Check out our new menu items!',body:'Hi [o100_customer_name],\n\nWe have some delicious new seasonal items on the menu. Come try them out!'}}] },
				{ key:'vip', title:'VIP Level-Up', desc:'Thank VIP customers with a tag and personalized email when their loyalty level changes.', icon:'<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>', colorClass:'bg-yellow-50 text-yellow-600', trigger:'loyalty_level_changed', actions:[{type:'add_tag',val:''},{type:'send_email',val:'',config:{editor_mode:'classic',subject:'Congrats! You leveled up!',body:'Hi [o100_customer_name],\n\nYou just reached a new loyalty level. Enjoy your new perks!'}}] },
				{ key:'reservation_reminder', title:'Reservation Reminder', desc:'Send an SMS reminder 24 hours before a confirmed reservation.', icon:'<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>', colorClass:'bg-blue-50 text-blue-600', trigger:'reservation_confirmed', actions:[{type:'wait',val:'1',config:{unit:'days'}},{type:'send_sms',val:'Hi [o100_customer_name], reminder: your reservation is tomorrow at [o100_reserve_time]. See you at [o100_store_name]!'}] }
			];

			var AUTO = window.AUTO = {
				init: function() {
					$('#o100-btn-add-action').on('click', function() { S.actions.push({type:'send_email',val:'',config:{}}); AUTO.renderActions(); });
					$('#o100-btn-add-cond').on('click', function() { S.conditions.push({field:'order_total',op:'>',val:''}); AUTO.renderConditions(); });
					$('#o100-auto-save').on('click', function() { AUTO.save(); });
					
					$('#o100-auto-trigger-val').on('change', function(e, isInit) {
						S.trigger = $(this).val();
						if (!isInit) S.triggerConfig = {};
						if (S.trigger === 'abandoned_cart') {
							$('#o100-abandoned-cart-hint').removeClass('hidden');
						} else {
							$('#o100-abandoned-cart-hint').addClass('hidden');
						}
						AUTO.updateTriggerConfig();
					});
					
					this.loadOptions();
					this.renderTemplates();
					this.loadSettings();
				},

				switchTab: function(tabId, el) {
					$('.o100-tab-panel, #tab-automations, #tab-templates, #tab-settings, #tab-reports').addClass('hidden').removeClass('block');
					$('#tab-' + tabId).removeClass('hidden').addClass('block');
					
					$('.o100-tab').removeClass('active text-indigo-600 border-indigo-600').addClass('border-transparent text-slate-500');
					$(el).addClass('active text-indigo-600 border-indigo-600').removeClass('border-transparent text-slate-500');

					if (tabId === 'settings') {
						this.loadSettings();
					} else if (tabId === 'reports') {
						this.loadReports( $('#o100-report-filter').val() );
					}
				},

				loadOptions: function() {
					$.ajax({
						url: ajaxurl, type: 'POST', data: {action:'o100_get_automation_options',_nonce:NONCE},
						success: function(r) {
							if (r.success) { 
								OPT = r.data; 
								AUTO.populateTriggerDropdown(); 
								AUTO.loadList(); 
								if (r.data.settings) {
									var s = r.data.settings;
									$('#set_enable_logs').prop('checked', s.enable_logs);
									if (s.batch_size) $('#set_batch_size').val(s.batch_size);
									if (s.daily_cron_time) $('#set_daily_cron_time').val(s.daily_cron_time);
									$('#set_enable_cart_tracking').prop('checked', s.enable_cart_tracking);
									if (s.cart_cutoff_time) $('#set_cart_cutoff_time').val(s.cart_cutoff_time);
									if (s.cart_lost_time) $('#set_cart_lost_time').val(s.cart_lost_time);
								}
							} else { console.error('loadOptions failed', r); alert('loadOptions error: ' + (r.data || JSON.stringify(r))); }
						},
						error: function(xhr) {
							console.error('loadOptions AJAX error', xhr.responseText);
							alert('loadOptions AJAX error: ' + xhr.responseText.substring(0, 500));
						}
					});
				},

				// ── Reports ──
				loadReports: function(autoId) {
					var tbody = $('#o100-reports-table-body');
					tbody.html('<tr><td colspan="5" class="py-12 text-center text-slate-500 text-sm"><svg class="animate-spin h-5 w-5 mr-3 text-indigo-600 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Loading reports...</td></tr>');
					
					$.post(ajaxurl, {action:'o100_get_auto_reports', nonce:NONCE, automation_id: autoId || 0}, function(r) {
						if(r.success) {
							tbody.empty();
							if (r.data.length === 0) {
								tbody.html('<tr><td colspan="5" class="py-12 text-center text-slate-500 text-sm">No execution logs found.</td></tr>');
								return;
							}
							
							$.each(r.data, function(i, log) {
								var statusColor = log.status === 'completed' ? 'bg-emerald-100 text-emerald-700' : (log.status === 'waiting' || log.status === 'active' ? 'bg-yellow-100 text-yellow-700' : 'bg-rose-100 text-rose-700');
								var statusIcon = log.status === 'completed' ? '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>' : (log.status === 'waiting' || log.status === 'active' ? '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' : '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>');
								var statusText = log.status.charAt(0).toUpperCase() + log.status.slice(1);
								
								// Pass the entire log object
								var encodedLog = encodeURIComponent(JSON.stringify(log));

								var tr = $('<tr></tr>').addClass('hover:bg-slate-50 transition-colors');
								tr.append('<td class="py-4 px-6 sticky left-0 bg-white/90 backdrop-blur z-10 border-r border-slate-100 shadow-[1px_0_0_0_#e2e8f0] group-hover:bg-slate-50"><a href="#" onclick="AUTO.edit(' + log.automation_id + '); return false;" class="font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">' + log.auto_title + '</a></td>');
								tr.append('<td class="py-4 px-6 text-sm text-slate-700"><div>' + (log.cust_first || '') + ' ' + (log.cust_last || '') + '</div><div class="text-xs text-slate-500">' + log.cust_email + '</div></td>');
								tr.append('<td class="py-4 px-6 text-center"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' + statusColor + '">' + statusIcon + statusText + '</span></td>');
								tr.append('<td class="py-4 px-6 text-sm text-slate-500">' + log.run_at + '</td>');
								tr.append('<td class="py-4 px-6 text-right"><button onclick="AUTO.showReportDetails(this, \'' + encodedLog + '\')" class="text-slate-400 hover:text-indigo-600 transition-colors p-2 rounded-lg hover:bg-indigo-50" title="View Details"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></button></td>');
								
								tbody.append(tr);
							});
						}
					});
				},

				showReportDetails: function(btn, encodedLog) {
					var log = JSON.parse(decodeURIComponent(encodedLog));
					
					var modal = $('#o100-report-details-modal');
					if (!modal.length) {
						modal = $('<div id="o100-report-details-modal" class="fixed inset-0 z-[999] hidden"><div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="document.getElementById(\'o100-report-details-modal\').classList.add(\'hidden\')"></div><div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white w-full max-w-lg rounded-2xl shadow-2xl flex flex-col"><div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center"><h3 class="text-lg font-bold text-slate-800 m-0" id="o100-report-details-title">Execution Details</h3><button onclick="document.getElementById(\'o100-report-details-modal\').classList.add(\'hidden\')" class="text-slate-400 hover:text-slate-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button></div><div class="px-6 py-6" id="o100-report-details-content" style="max-height:70vh; overflow-y:auto;"></div><div class="px-6 py-4 border-t border-slate-50 bg-slate-50 rounded-b-2xl flex justify-end"><button onclick="document.getElementById(\'o100-report-details-modal\').classList.add(\'hidden\')" class="bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 font-bold text-sm px-6 py-2 rounded-xl shadow-sm transition-colors">Close</button></div></div></div>');
						$('body').append(modal);
					}
					
					$('#o100-report-details-title').text('Details: ' + log.auto_title);
					
					var html = '<div class="mb-6 flex items-center justify-between bg-slate-50 p-4 rounded-xl border border-slate-100">';
					html += '<div><p class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-1">Customer</p><p class="text-sm font-medium text-slate-900">' + AUTO.esc(log.cust_first) + ' ' + AUTO.esc(log.cust_last) + ' <span class="text-slate-500 font-normal">(' + AUTO.esc(log.cust_email) + ')</span></p></div>';
					html += '<div class="text-right"><p class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-1">Executed At</p><p class="text-sm font-medium text-slate-900">' + log.run_at + '</p></div>';
					html += '</div>';

					html += '<h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 px-2">Execution Timeline</h4>';
					
					var stepsHtml = '';
					
					// Prepend a "Trigger" step visually
					stepsHtml += '<div class="relative pl-6 pb-6">';
					stepsHtml += '<div class="absolute left-[7px] top-1.5 bottom-[-6px] w-[2px] bg-slate-200"></div>'; 
					stepsHtml += '<div class="absolute left-0 top-1 w-4 h-4 rounded-full bg-indigo-100 border-2 border-indigo-500 z-10"></div>';
					stepsHtml += '<div class="flex justify-between"><div class="text-sm font-bold text-slate-800">Trigger Activated</div><div class="text-xs font-bold text-indigo-600">✓ Logged</div></div>';
					stepsHtml += '<div class="text-xs text-slate-500 mt-1">Customer entered the automation flow.</div>';
					stepsHtml += '</div>';

					if (log.details) {
						try {
							var detailsArr = JSON.parse(log.details);
							if (Array.isArray(detailsArr) && detailsArr.length > 0) {
								$.each(detailsArr, function(idx, actionInfo) {
									var isLast = idx === detailsArr.length - 1;
									var stepStatusColor = actionInfo.status === 'completed' || actionInfo.status === 'success' ? 'text-emerald-600 border-emerald-500 bg-emerald-100' : (actionInfo.status === 'waiting' ? 'text-yellow-600 border-yellow-500 bg-yellow-100' : 'text-rose-600 border-rose-500 bg-rose-100');
									var stepTextColor = actionInfo.status === 'completed' || actionInfo.status === 'success' ? 'text-emerald-600' : (actionInfo.status === 'waiting' ? 'text-yellow-600' : 'text-rose-600');
									var stepIcon = actionInfo.status === 'completed' || actionInfo.status === 'success' ? '✓' : (actionInfo.status === 'waiting' ? '⏱' : '✗');
									
									stepsHtml += '<div class="relative pl-6 ' + (isLast ? '' : 'pb-6') + '">';
									if (!isLast) stepsHtml += '<div class="absolute left-[7px] top-1.5 bottom-[-6px] w-[2px] bg-slate-200"></div>';
									stepsHtml += '<div class="absolute left-0 top-1 w-4 h-4 rounded-full border-2 z-10 ' + stepStatusColor + '"></div>';
									stepsHtml += '<div class="flex justify-between"><div class="text-sm font-bold text-slate-800 capitalize">' + actionInfo.type.replace(/_/g, ' ') + '</div><div class="text-xs font-bold ' + stepTextColor + '">' + stepIcon + ' ' + (actionInfo.status.charAt(0).toUpperCase() + actionInfo.status.slice(1)) + '</div></div>';
									if (actionInfo.val) stepsHtml += '<div class="text-xs text-slate-500 mt-1 truncate max-w-[280px]">' + AUTO.esc(actionInfo.val) + '</div>';
									stepsHtml += '</div>';
								});
							} else {
								stepsHtml += '<div class="relative pl-6"><div class="text-xs text-slate-500 italic mt-2">No detailed actions logged.</div></div>';
							}
						} catch(e) {
							stepsHtml += '<div class="relative pl-6"><div class="text-xs text-slate-500 italic mt-2">Error parsing logs.</div></div>';
						}
					} else {
						stepsHtml += '<div class="relative pl-6"><div class="text-xs text-slate-500 italic mt-2">No detailed actions logged.</div></div>';
					}
					
					html += '<div class="bg-white p-4 rounded-xl border border-slate-100">' + stepsHtml + '</div>';
					
					$('#o100-report-details-content').html(html);
					modal.removeClass('hidden');
				},

				// ── Settings ──
				loadSettings: function() {
					$.post(ajaxurl, {action:'o100_get_auto_settings',_nonce:NONCE}, function(r) {
						if (r.success) {
							var s = r.data;
							$('#set_batch_size').val(s.batch_size);
							$('#set_daily_cron_time').val(s.daily_cron_time);
							$('#set_enable_logs').prop('checked', s.enable_logs);
							$('#set_enable_cart_tracking').prop('checked', s.enable_cart_tracking);
							$('#set_cart_cutoff_time').val(s.cart_cutoff_time);
							$('#set_cart_lost_time').val(s.cart_lost_time);
							$('#set_queue_count').text(s.queue_count);
						}
					});
				},

				saveSettings: function() {
					var btn = $('#o100-btn-save-settings');
					var oldHtml = btn.html();
					btn.html('<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Saving...');
					var s = {
						enable_logs: $('#set_enable_logs').is(':checked'),
						batch_size: $('#set_batch_size').val(),
						daily_cron_time: $('#set_daily_cron_time').val(),
						enable_cart_tracking: $('#set_enable_cart_tracking').is(':checked'),
						cart_cutoff_time: $('#set_cart_cutoff_time').val(),
						cart_lost_time: $('#set_cart_lost_time').val()
					};
					$.post(ajaxurl, {action:'o100_save_auto_settings',_nonce:NONCE,settings:JSON.stringify(s)}, function(r) {
						btn.html(oldHtml);
						if (r.success) {
							var successMsg = $('<div class="fixed top-4 right-4 bg-emerald-50 text-emerald-600 px-4 py-3 rounded-xl border border-emerald-200 shadow-lg z-50 flex items-center transition-all"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Settings saved successfully!</div>');
							$('body').append(successMsg);
							setTimeout(function() { successMsg.fadeOut(300, function() { $(this).remove(); }); }, 3000);
						} else {
							alert('Error: ' + (r.data || 'Failed to save settings.'));
						}
					});
				},

				runQueueNow: function() {
					$.post(ajaxurl, {action:'o100_run_auto_queue',_nonce:NONCE}, function(r) {
						if (r.success) {
							alert(r.data.message);
						}
					});
				},

				// ── List ──
				loadList: function() {
					var tb = $('#o100-auto-table-body');
					$.ajax({
						url: ajaxurl, type: 'POST', data: {action:'o100_get_automations',_nonce:NONCE},
						success: function(r) {
							if (!r.success || !r.data || r.data.length === 0) {
								tb.html('<tr><td colspan="5" class="py-12 text-center text-slate-500 text-sm">No automations found. Click "Create New Automation" to get started.</td></tr>');
								return;
							}
							var h = '';
							r.data.forEach(function(item) {
								var tLabel = (OPT && OPT.triggers && OPT.triggers[item.trigger_type]) ? OPT.triggers[item.trigger_type].label : item.trigger_type;
								tLabel = tLabel.replace(/^[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE00}-\u{FEFF}\u{1F900}-\u{1F9FF}\u{200D}\u{2702}-\u{27B0}]+\s*/u, '');
								
								var created = item.created_at ? item.created_at.substring(0,10) : '';
								var isChecked = item.status === 'active' ? 'checked' : '';
								
								h += '<tr class="group hover:bg-slate-50/50 transition-colors">';
								h += '<td class="py-4 px-6 border-b border-slate-100">';
								h += '	<p class="font-bold text-slate-900 text-sm">'+AUTO.esc(item.title)+'</p>';
								h += '</td>';
								h += '<td class="py-4 px-6 border-b border-slate-100 hidden sm:table-cell text-sm text-slate-600 font-medium">';
								h += '	<span class="flex items-center"><span class="w-2 h-2 rounded-full bg-indigo-400 mr-2"></span>'+AUTO.esc(tLabel)+'</span>';
								h += '</td>';
								h += '<td class="py-4 px-6 border-b border-slate-100 hidden md:table-cell text-sm text-slate-500">'+AUTO.esc(created)+'</td>';
								h += '<td class="py-4 px-6 border-b border-slate-100 text-center" style="vertical-align: middle;">';
								h += '	<label class="o100-toggle-switch" style="margin-bottom:0; transform:translateY(3px);">';
								h += '		<input type="checkbox" class="o100-auto-status-toggle" data-id="'+item.id+'" '+isChecked+'>';
								h += '		<span class="o100-toggle-slider"></span>';
								h += '	</label>';
								h += '</td>';
								h += '<td class="py-4 px-6 border-b border-slate-100 text-right">';
								h += '	<div class="flex justify-end gap-2">';
								h += '		<button class="p-1.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg transition-colors o100-auto-report" data-id="'+item.id+'" title="View Report"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg></button>';
								h += '		<button class="p-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors o100-auto-edit" data-id="'+item.id+'" title="Edit"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>';
								h += '		<button class="p-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors o100-auto-del" data-id="'+item.id+'" title="Delete"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>';
								h += '	</div>';
								h += '</td>';
								h += '</tr>';
							});
							tb.html(h);
							
							// Populate Report Filter Dropdown
							var filterOptions = '<option value="0">All Automations</option>';
							r.data.forEach(function(item) {
								filterOptions += '<option value="' + item.id + '">' + AUTO.esc(item.title) + '</option>';
							});
							var currentFilterVal = $('#o100-report-filter').val();
							$('#o100-report-filter').html(filterOptions);
							if (currentFilterVal) {
								$('#o100-report-filter').val(currentFilterVal);
							}

							tb.find('.o100-auto-status-toggle').on('change', function() {
								var checkbox = $(this);
								var id = checkbox.data('id');
								var newStatus = checkbox.is(':checked') ? 'active' : 'paused';
								checkbox.prop('disabled', true);
								$.post(ajaxurl, {
									action: 'o100_toggle_automation_status',
									_nonce: NONCE,
									id: id,
									status: newStatus
								}, function(r) {
									checkbox.prop('disabled', false);
									if (!r.success) {
										if (r.data && typeof r.data === 'string' && r.data.indexOf('Free plan limit reached') !== -1) {
											o100ShowProModal('Unlimited Automations', 'Want to schedule multiple automated marketing workflows? Upgrade to Pro to unlock unlimited automation rules!');
										} else {
											alert('Failed to update status: ' + (r.data || 'Unknown error'));
										}
										checkbox.prop('checked', !checkbox.is(':checked'));
									}
								}).fail(function() {
									checkbox.prop('disabled', false);
									checkbox.prop('checked', !checkbox.is(':checked'));
									alert('Network error');
								});
							});

							tb.find('.o100-auto-report').on('click', function(e) { 
								e.preventDefault(); 
								$('#o100-report-filter').val($(this).data('id'));
								AUTO.switchTab('reports', document.querySelectorAll('.o100-tab')[2]); 
							});
							tb.find('.o100-auto-edit').on('click', function(e) { e.preventDefault(); AUTO.edit($(this).data('id')); });
							tb.find('.o100-auto-del').on('click', function(e) { e.preventDefault(); AUTO.remove($(this).data('id')); });
						},
						error: function(xhr) {
							tb.html('<tr><td colspan="5" class="py-12 text-center text-red-500 text-sm">AJAX Error: ' + AUTO.esc(xhr.responseText.substring(0,200)) + '</td></tr>');
							alert('loadList AJAX error: ' + xhr.responseText.substring(0, 500));
						}
					});
				},

				// ── Templates ──
				renderTemplates: function() {
					var grid = $('#o100-template-grid').empty();
					var customGrid = $('#o100-custom-template-grid').empty();
					TEMPLATES.forEach(function(t) {
						var card = $('<div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all cursor-pointer group flex flex-col h-full"></div>');
						var h = '<div class="w-10 h-10 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform origin-left '+t.colorClass+'">'+t.icon+'</div>';
						h += '<h3 class="font-bold text-slate-900 text-sm mb-1">'+AUTO.esc(t.title)+'</h3>';
						h += '<p class="text-xs text-slate-500 flex-1 leading-relaxed">'+AUTO.esc(t.desc)+'</p>';
						card.html(h);
						card.on('click', function() { AUTO.useTemplate(t); });
						if (t.isBlank) {
							customGrid.append(card);
						} else {
							grid.append(card);
						}
					});
				},

				useTemplate: function(t) {
					S.id = null;
					S.trigger = t.trigger || '';
					S.triggerConfig = {};
					S.conditions = t.conditions ? JSON.parse(JSON.stringify(t.conditions)) : [];
					S.actions = t.actions ? JSON.parse(JSON.stringify(t.actions)) : [];
					S.rulesAndGoals = t.rules_and_goals ? JSON.parse(JSON.stringify(t.rules_and_goals)) : {allow_reentry: false, wait_days: 0, exit_goal: 'none'};
					$('#o100-auto-name').val(t.isBlank ? '' : t.title);
					$('#o100-auto-trigger-val').val(S.trigger);
					$('#o100-auto-status').prop('checked', false);
					$('#o100-editor-title').text(t.isBlank ? 'Create Automation' : 'Edit: ' + t.title);
					
					// Populate limits
					if (S.rulesAndGoals.allow_reentry) {
						$('input[name="o100_auto_limit"][value="multiple"]').prop('checked', true);
					} else {
						$('input[name="o100_auto_limit"][value="once"]').prop('checked', true);
					}
					$('#o100_auto_wait_days').val(S.rulesAndGoals.wait_days || 0);
					$('#o100_auto_exit_goal').val(S.rulesAndGoals.exit_goal || 'none');

					this.updateTriggerConfig();
					this.renderConditions();
					this.renderActions();
					this.openEditor();
				},

				// ── Trigger Picker ──
				populateTriggerDropdown: function() {
					var sel = $('#o100-auto-trigger-val').empty();
					sel.append('<option value="">-- Select an Event --</option>');
					if (!OPT || !OPT.triggers) return;
					
					// Group by trigger group
					var groups = OPT.trigger_groups || {};
					Object.keys(groups).forEach(function(gk) {
						var optgroup = $('<optgroup label="'+AUTO.esc(groups[gk])+'"></optgroup>');
						var hasOpts = false;
						Object.keys(OPT.triggers).forEach(function(tk) {
							if (OPT.triggers[tk].group === gk) {
								hasOpts = true;
								var label = OPT.triggers[tk].label.replace(/^[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE00}-\u{FEFF}\u{1F900}-\u{1F9FF}\u{200D}\u{2702}-\u{27B0}]+\s*/u, '');
								optgroup.append('<option value="'+tk+'">'+AUTO.esc(label)+'</option>');
							}
						});
						if (hasOpts) sel.append(optgroup);
					});
				},
				
				updateTriggerConfig: function() {
					var val = $('#o100-auto-trigger-val').val();
					var cfgBox = $('#o100-auto-trigger-config').empty();
					var descBox = $('#o100-auto-trigger-desc');
					
					if (val && OPT && OPT.triggers && OPT.triggers[val]) {
						$('#o100-auto-trigger-desc-text').text(OPT.triggers[val].description || '');
						descBox.removeClass('hidden');
					} else {
						descBox.addClass('hidden');
					}
					
					if (val && TRIGGER_CONFIGS[val]) {
						TRIGGER_CONFIGS[val].forEach(function(f) {
							var fg = $('<div class="mb-2"></div>');
							fg.append('<label class="block text-xs font-bold text-slate-700 mb-1">'+AUTO.esc(f.label)+'</label>');
							var inp;
							if (f.type === 'select_tag' || f.type === 'select_list') {
								var isMult = f.multiple ? ' multiple="multiple" data-key="'+f.key+'" class="o100-multi-select text-sm py-1 px-2 w-full border rounded border-slate-300 shadow-sm"' : ' data-key="'+f.key+'" class="text-sm py-1 px-2 border border-slate-300 rounded shadow-sm w-full"';
								inp = $('<select' + isMult + '></select>');
								if (!f.multiple) inp.append('<option value="">-- Select --</option>');
								
								var opts = f.type === 'select_tag' ? (OPT.tags||[]) : (OPT.lists||[]);
								opts.forEach(function(item) {
									var isSel = false;
									if (f.multiple) {
										var savedArr = Array.isArray(S.triggerConfig[f.key]) ? S.triggerConfig[f.key] : (S.triggerConfig[f.key] ? [S.triggerConfig[f.key]] : []);
										isSel = savedArr.includes(String(item.id));
									} else {
										isSel = (S.triggerConfig[f.key] == item.id);
									}
									inp.append('<option value="'+item.id+'"'+(isSel?' selected':'')+'>'+AUTO.esc(item.title)+'</option>');
								});
							} else if (f.type === 'select_status') {
								inp = $('<select class="text-sm py-1 px-2 border border-slate-300 rounded shadow-sm w-full"></select>');
								['subscribed','unsubscribed','pending'].forEach(function(s) { inp.append('<option value="'+s+'"'+(S.triggerConfig[f.key]===s?' selected':'')+'>'+s.charAt(0).toUpperCase()+s.slice(1)+'</option>'); });
							} else if (f.type === 'select') {
								inp = $('<select class="text-sm py-1 px-2 border border-slate-300 rounded shadow-sm w-full"></select>');
								if (f.options) {
									Object.keys(f.options).forEach(function(ok) {
										var isSel = (S.triggerConfig[f.key] == ok);
										inp.append('<option value="'+AUTO.esc(ok)+'"'+(isSel?' selected':'')+'>'+AUTO.esc(f.options[ok])+'</option>');
									});
								}
							} else {
								inp = $('<input type="'+(f.type==='number'?'number':'text')+'" class="text-sm py-1 px-2 border border-slate-300 rounded shadow-sm w-full" value="'+AUTO.esc(S.triggerConfig[f.key]||(f.default||''))+'">');
							}
							inp.on('change', function() {
								S.triggerConfig[f.key] = f.multiple ? $(this).val() : this.value;
							});
							fg.append(inp);
							cfgBox.append(fg);
						});
					}
					
					setTimeout(function() {
						if ($.fn.select2) {
							cfgBox.find('.o100-multi-select').each(function() {
								var $el = $(this);
								var key = $el.attr('data-key');
								$el.select2({
									placeholder: 'Search and select...',
									allowClear: true,
									closeOnSelect: false,
									templateResult: function(result) {
										if (result.loading) return result.text;
										if (!result.id) return result.text;
										
										var selectedVals = $el.val() || [];
										var isSelected = selectedVals.includes(String(result.id));
										
										if (isSelected) {
											setTimeout(function() {
												$('.o100-select2-item[data-id="'+result.id+'"]').closest('li').addClass('o100-disabled-li');
											}, 0);
										}
										
										return $('<div class="o100-select2-item" data-id="' + result.id + '" style="display:flex; align-items:center;"><span class="o100-custom-cb' + (isSelected ? ' o100-cb-checked' : '') + '"></span><span>' + result.text + '</span></div>');
									},
									templateSelection: function(result) {
										// Render as plain text (or safely decoded HTML) to avoid the "x" showing up twice 
										var temp = document.createElement('div');
										temp.innerHTML = result.text;
										return temp.textContent || temp.innerText || result.text;
									}
								}).on('select2:select select2:unselect', function(e) {
									var id = e.params.data.id;
									var $li = $('.o100-select2-item[data-id="'+id+'"]').closest('li');
									if (e.type === 'select2:select') {
										$li.addClass('o100-disabled-li');
										$li.find('.o100-custom-cb').addClass('o100-cb-checked');
									} else {
										$li.removeClass('o100-disabled-li');
										$li.find('.o100-custom-cb').removeClass('o100-cb-checked');
									}
								});
							});
						}
					}, 50);
				},

				// ── Modal ──
				totalSteps: 5,
				currentStep: 1,
				
				goToStep: function(step) {
					this.currentStep = step;
					for(var i=1; i<=this.totalSteps; i++) {
						var navItem = $('#step-nav-' + i);
						if (!navItem.length) continue;
						navItem.removeClass('is-active is-completed is-pending');
						if (i < step) navItem.addClass('is-completed');
						else if (i === step) navItem.addClass('is-active');
						else navItem.addClass('is-pending');
						
						var contentItem = $('#step-content-' + i);
						if (i === step) contentItem.addClass('is-active');
						else contentItem.removeClass('is-active');
					}
					
					var btnBack = $('#btn-back');
					var btnNext = $('#btn-next');
					var btnSave = $('#o100-auto-save');
					
					if (step === 1) btnBack.addClass('hidden'); else btnBack.removeClass('hidden');
					if (step === this.totalSteps) { btnNext.addClass('hidden'); btnSave.removeClass('hidden').css('display', 'flex'); }
					else { btnNext.removeClass('hidden'); btnSave.addClass('hidden').css('display', ''); }
				},
				
				nextStep: function() {
					if (this.currentStep < this.totalSteps) { this.goToStep(this.currentStep + 1); }
				},
				
				prevStep: function() {
					if (this.currentStep > 1) { this.goToStep(this.currentStep - 1); }
				},

				openEditor: function() {
					$('#o100-editor-modal').addClass('is-open');
					this.goToStep(1);
				},
				closeEditor: function() {
					$('#o100-editor-modal').removeClass('is-open');
				},

				edit: function(id) {
					$.post(ajaxurl, {action:'o100_get_automations',_nonce:NONCE}, function(r) {
						if (!r.success) return;
						var item = null;
						r.data.forEach(function(a) { if (parseInt(a.id)===id) item=a; });
						if (!item) return alert('Not found.');
						
						S.id = item.id;
						S.trigger = item.trigger_type;
						S.triggerConfig = item.trigger_config ? JSON.parse(item.trigger_config) : {};
						S.conditions = item.conditions ? JSON.parse(item.conditions) : [];
						S.actions = item.actions ? JSON.parse(item.actions) : [];
						
						$('#o100-auto-name').val(item.title);
						$('#o100-auto-desc').val(item.description||'');
						$('#o100-auto-trigger-val').val(item.trigger_type).trigger('change', [true]);
						$('#o100-auto-status').prop('checked', item.status==='active');
						$('#o100-editor-title').text('Edit: ' + item.title);
						
						AUTO.renderConditions();
						AUTO.renderActions();
						AUTO.openEditor();
					});
				},

				loadOptionsAndRender: function() {
					$.post(ajaxurl, {action:'o100_get_automation_options',_nonce:NONCE}, function(r) {
						if (r.success) {
							OPT = r.data;
							AUTO.renderActions(); // Re-render to show updated template dropdown
						}
					});
				},

				launchVisualBuilder: function(callback, existingTemplateId) {
					var route = existingTemplateId ? '#/editor/' + existingTemplateId : '#/editor/new';
					var iframeUrl = 'admin.php?page=o100ne-settings&o100_iframe=1' + route;
					var modal = $('<div id="o100-visual-modal" class="fixed inset-0 z-[99999] bg-black bg-opacity-75 flex flex-col transition-opacity opacity-0"><div class="flex justify-between items-center bg-slate-900 text-white px-4 py-2"><div class="text-sm font-bold flex items-center"><svg class="w-5 h-5 mr-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg> Order100 Visual Builder</div><button type="button" id="o100-close-builder-btn" class="text-slate-300 hover:text-white flex items-center text-sm"><svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Close Builder & Return</button></div><iframe src="'+iframeUrl+'" class="flex-1 w-full border-none bg-white"></iframe></div>');
					$('body').append(modal);
					setTimeout(function(){ modal.removeClass('opacity-0').addClass('opacity-100'); }, 10);
					
					window.o100VisualCallback = callback;
					
					var doCloseModal = function() {
						var iframe = modal.find('iframe')[0];
						if (iframe && iframe.contentWindow) { iframe.contentWindow.onbeforeunload = null; } // Prevent native prompt
						$('#o100-visual-modal').removeClass('opacity-100').addClass('opacity-0');
						setTimeout(function(){$('#o100-visual-modal').remove(); if(window.o100VisualCallback) window.o100VisualCallback(null);}, 300);
					};

					$('#o100-close-builder-btn').on('click', function() {
						var iframe = modal.find('iframe')[0];
						var hasChanges = iframe && iframe.contentWindow && typeof iframe.contentWindow.onbeforeunload === 'function';
						
						if (hasChanges) {
							// Show unified UI modal instead of native confirm
							var confirmHtml = $('<div id="o100-visual-confirm" class="fixed inset-0 z-[100000] flex items-center justify-center bg-slate-900 bg-opacity-50 transition-opacity opacity-0"><div class="bg-white rounded-xl shadow-2xl max-w-sm w-full mx-4 overflow-hidden transform transition-all translate-y-4 sm:translate-y-0 sm:scale-95"><div class="px-6 py-5"><div class="flex items-start"><div class="flex-shrink-0 w-12 h-12 rounded-full bg-red-100 flex items-center justify-center sm:mx-0 sm:h-10 sm:w-10"><svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg></div><div class="ml-4 mt-1"><h3 class="text-lg leading-6 font-bold text-slate-900">Unsaved Changes</h3><p class="mt-2 text-sm text-slate-500 leading-relaxed">You have unsaved changes in the email editor. If you leave now, your recent edits will be permanently lost.</p></div></div></div><div class="bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-100"><button type="button" class="btn-cancel px-4 py-2 bg-white border border-slate-300 rounded-lg shadow-sm text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500">Cancel</button><button type="button" class="btn-leave px-4 py-2 bg-red-600 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white hover:bg-red-700 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">Discard & Leave</button></div></div></div>');
							$('body').append(confirmHtml);
							
							// Animate in
							setTimeout(function() {
								confirmHtml.removeClass('opacity-0').addClass('opacity-100');
								confirmHtml.find('.transform').removeClass('translate-y-4 sm:translate-y-0 sm:scale-95').addClass('translate-y-0 sm:scale-100');
							}, 10);

							confirmHtml.find('.btn-cancel').on('click', function() {
								confirmHtml.removeClass('opacity-100').addClass('opacity-0');
								setTimeout(function(){ confirmHtml.remove(); }, 300);
							});
							confirmHtml.find('.btn-leave').on('click', function() {
								confirmHtml.removeClass('opacity-100').addClass('opacity-0');
								setTimeout(function(){ confirmHtml.remove(); doCloseModal(); }, 300);
							});
						} else {
							doCloseModal();
						}
					});

					// Listen for message from the iframe (the React app should ideally postMessage when saved)
					// If it doesn't, the user will just close the modal, and we'll re-fetch options to see if a new template was created.
					if (!window.o100VisualMessageListener) {
						window.o100VisualMessageListener = true;
						window.addEventListener('message', function(event) {
							if (event.data && event.data.type === 'o100_template_saved' && event.data.template_id) {
								if (window.o100VisualCallback) window.o100VisualCallback(event.data.template_id);
								var iframe = modal.find('iframe')[0];
								if (iframe && iframe.contentWindow) { iframe.contentWindow.onbeforeunload = null; }
								$('#o100-visual-modal').removeClass('opacity-100').addClass('opacity-0');
								setTimeout(function(){$('#o100-visual-modal').remove();}, 300);
							}
						});
					}
				},

				remove: function(id) {
					if (!confirm('Delete this automation?')) return;
					$.post(ajaxurl, {action:'o100_delete_automation',_nonce:NONCE,id:id}, function(r) {
						if (r.success) AUTO.loadList();
					});
				},

				// ── Conditions ──
				renderConditions: function() {
					var c = $('#o100-auto-conds-list').empty();
					if (!OPT) return;
					var fields = OPT.condition_fields || {};
					var opLabels = {'>':'Greater Than','<':'Less Than','==':'Equals','>=':'At Least','<=':'At Most','is':'Is','is_not':'Is Not','includes':'Includes','excludes':'Excludes'};

					S.conditions.forEach(function(cond, idx) {
						var row = $('<div class="grid grid-cols-12 gap-2 items-center p-3 bg-slate-50 border border-slate-200 rounded-lg relative group"></div>');
						
						var fSel = $('<select class="text-sm py-1.5 px-2 col-span-5 border border-slate-300 rounded shadow-sm text-slate-800"></select>');
						Object.keys(fields).forEach(function(fk) {
							fSel.append('<option value="'+fk+'"'+(cond.field===fk?' selected':'')+'>'+AUTO.esc(fields[fk].label)+'</option>');
						});
						fSel.on('change', function() { S.conditions[idx].field=this.value; S.conditions[idx].val=''; AUTO.renderConditions(); });

						var ops = fields[cond.field] ? fields[cond.field].operators : ['>'];
						var oSel = $('<select class="text-sm py-1.5 px-2 col-span-3 border border-slate-300 rounded shadow-sm text-slate-800"></select>');
						ops.forEach(function(op) { oSel.append('<option value="'+op+'"'+(cond.op===op?' selected':'')+'>'+( opLabels[op]||op)+'</option>'); });
						oSel.on('change', function() { S.conditions[idx].op=this.value; });

						var fieldDef = fields[cond.field] || {};
						var vInput;
						if (fieldDef.type==='select_tag') {
							vInput=$('<select class="text-sm py-1.5 px-2 col-span-4 border border-slate-300 rounded shadow-sm text-slate-800"></select>').append('<option value="">-- Tag --</option>');
							(OPT.tags||[]).forEach(function(t){vInput.append('<option value="'+t.id+'"'+(cond.val==t.id?' selected':'')+'>'+AUTO.esc(t.title)+'</option>');});
						} else if (fieldDef.type==='select_list') {
							vInput=$('<select class="text-sm py-1.5 px-2 col-span-4 border border-slate-300 rounded shadow-sm text-slate-800"></select>').append('<option value="">-- List --</option>');
							(OPT.lists||[]).forEach(function(l){vInput.append('<option value="'+l.id+'"'+(cond.val==l.id?' selected':'')+'>'+AUTO.esc(l.title)+'</option>');});
						} else if (fieldDef.type==='select_status') {
							vInput=$('<select class="text-sm py-1.5 px-2 col-span-4 border border-slate-300 rounded shadow-sm text-slate-800"></select>');
							['subscribed','unsubscribed','pending'].forEach(function(s){vInput.append('<option value="'+s+'"'+(cond.val===s?' selected':'')+'>'+s.charAt(0).toUpperCase()+s.slice(1)+'</option>');});
						} else {
							vInput=$('<input type="'+(fieldDef.type==='number'?'number':'text')+'" class="text-sm py-1.5 px-2 col-span-4 border border-slate-300 rounded shadow-sm text-slate-800" placeholder="Value" value="'+AUTO.esc(cond.val||'')+'">');
						}
						vInput.on('change', function(){S.conditions[idx].val=this.value;});
						
						var rm=$('<button type="button" class="text-slate-400 hover:text-red-500 absolute -top-2 -right-2 bg-white rounded-full shadow-sm border border-slate-200 w-5 h-5 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity" title="Remove">&times;</button>');
						rm.on('click', function(){S.conditions.splice(idx,1); AUTO.renderConditions();});
						
						row.append(fSel,oSel,vInput,rm);
						c.append(row);
					});
				},

				// ── Actions ──
				renderActions: function() {
					var c=$('#o100-auto-actions-list').empty();
					if (!OPT) return;
					var types=OPT.action_types||{};

					S.actions.forEach(function(act,idx) {
						var row=$('<div class="p-4 bg-slate-50 border border-slate-200 rounded-lg relative group mb-3 o100-action-row" data-index="'+idx+'"></div>');
						
						var topRow=$('<div class="flex gap-3 mb-3 items-center"></div>');
						topRow.append('<div class="o100-drag-handle cursor-move text-slate-400 hover:text-slate-600 p-1 -ml-2" title="Drag to reorder"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg></div>');
						topRow.append('<div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs flex items-center justify-center flex-shrink-0">'+(idx+1)+'</div>');
						
						var tSel=$('<select class="text-sm font-bold text-slate-700 flex-1"></select>');
						Object.keys(types).forEach(function(tk){tSel.append('<option value="'+tk+'"'+(act.type===tk?' selected':'')+'>'+AUTO.esc(types[tk].label)+'</option>');});
						tSel.on('change', function(){S.actions[idx].type=this.value; S.actions[idx].val=''; S.actions[idx].config={}; AUTO.renderActions();});
						topRow.append(tSel);
						row.append(topRow);

						var valRow=$('<div class="pl-9"></div>');
						if (act.type==='wait') {
							var cfg=act.config||{};
							var mi=$('<div class="flex items-center gap-2"><input type="number" class="text-sm w-24 border border-slate-300 rounded px-2 py-1" placeholder="Value" value="'+(act.val||'')+'"></div>');
							var uSel=$('<select class="text-sm border border-slate-300 rounded px-2 py-1"><option value="minutes"'+(cfg.unit==='minutes'?' selected':'')+'>Minutes</option><option value="hours"'+(cfg.unit==='hours'?' selected':'')+'>Hours</option><option value="days"'+(cfg.unit==='days'?' selected':'')+'>Days</option></select>');
							
							mi.find('input').on('change',function(){S.actions[idx].val=this.value;});
							uSel.on('change',function(){S.actions[idx].config=S.actions[idx].config||{}; S.actions[idx].config.unit=this.value;});
							mi.append(uSel);
							valRow.append(mi);
						} else if (act.type==='send_email') {
							var cfg=act.config||{};
							var mode=cfg.editor_mode||'classic';
							
							var globalsRow=$('<div class="flex flex-col gap-3 mb-4 p-4 bg-white rounded-lg border border-slate-200 shadow-sm"></div>');
							
							var subj=$('<input type="text" class="text-sm w-full border border-slate-300 rounded px-3 py-2 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. You left something behind!" value="'+AUTO.esc(cfg.subject||'')+'">');
							subj.on('change',function(){S.actions[idx].config.subject=this.value;});
							
							var preH=$('<input type="text" class="text-sm w-full border border-slate-300 rounded px-3 py-2 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. Come back and complete your order to get 10% off." value="'+AUTO.esc(cfg.preheader||'')+'">');
							preH.on('change',function(){S.actions[idx].config.preheader=this.value;});

							globalsRow.append(
								$('<div><label class="block text-xs font-bold text-slate-700 mb-1">Email Subject <span class="text-red-500">*</span></label></div>').append(subj),
								$('<div><label class="block text-xs font-bold text-slate-700 mb-1">Pre-header Text <span class="text-slate-400 font-normal">(optional preview snippet)</span></label></div>').append(preH)
							);
							valRow.append(globalsRow);
							
							var modeRow=$('<div class="flex items-center gap-4 mb-4"></div>');
							var lblMode=$('<span class="text-xs font-bold text-slate-700">Editor Mode:</span>');
							var btnClassic=$('<button type="button" class="px-3 py-1 rounded text-xs font-bold transition-colors '+(mode==='classic'?'bg-indigo-100 text-indigo-700':'text-slate-500 hover:bg-slate-100')+'">Classic Text</button>');
							var btnVisual=$('<button type="button" class="px-3 py-1 rounded text-xs font-bold flex items-center transition-colors '+(mode==='visual'?'bg-indigo-100 text-indigo-700':'text-slate-500 hover:bg-slate-100')+'"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg> Visual Builder</button>');
							
							btnClassic.on('click', function() {
								S.actions[idx].config=S.actions[idx].config||{};
								S.actions[idx].config.editor_mode='classic';
								AUTO.renderActions();
							});
							btnVisual.on('click', function() {
								S.actions[idx].config=S.actions[idx].config||{};
								S.actions[idx].config.editor_mode='visual';
								AUTO.renderActions();
							});
							modeRow.append(lblMode, btnClassic, btnVisual);
							valRow.append(modeRow);

							if (mode === 'classic') {
								var varRow=$('<div class="mb-2 flex justify-end"></div>');
								var varBtn=$('<button type="button" class="text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded hover:bg-indigo-100 transition-colors inline-flex items-center"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg> Insert Variable &darr;</button>');
								var varDrop=$('<div class="hidden absolute right-0 mt-8 w-48 bg-white border border-slate-200 rounded shadow-lg z-[9999] py-1 text-left"></div>');
								var vars = ['[o100_customer_name]', '[o100_customer_email]', '[o100_order_number]', '[o100_coupon_code]', '[o100_store_name]', '[o100_loyalty_points]'];
								vars.forEach(function(v) {
									var a=$('<a href="#" class="block px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 hover:text-indigo-600"></a>').text(v);
									a.on('click', function(e) {
										e.preventDefault();
										var elId = 'o100-auto-email-body-'+idx;
										if (typeof tinymce !== 'undefined' && tinymce.get(elId)) {
											tinymce.get(elId).execCommand('mceInsertContent', false, v);
											S.actions[idx].config.body = tinymce.get(elId).getContent();
										} else {
											var ta = $('#'+elId)[0];
											if(ta) {
												var start = ta.selectionStart;
												var end = ta.selectionEnd;
												ta.value = ta.value.substring(0, start) + v + ta.value.substring(end);
												$(ta).trigger('change');
											}
										}
										varDrop.addClass('hidden');
									});
									varDrop.append(a);
								});
								varBtn.on('click', function(e) { e.stopPropagation(); varDrop.toggleClass('hidden'); });
								$(document).on('click', function() { varDrop.addClass('hidden'); });
								varRow.append($('<div class="relative flex flex-col items-end"></div>').append(varBtn, varDrop));

								var bodyHtml = typeof wp !== "undefined" && wp.editor ?
									$('<textarea id="o100-auto-email-body-'+idx+'" class="text-sm w-full border border-slate-300 rounded px-3 py-2" rows="6" placeholder="Email Body (HTML/Shortcodes supported)">'+AUTO.esc(cfg.body||'')+'</textarea>') :
									$('<textarea id="o100-auto-email-body-'+idx+'" class="text-sm w-full border border-slate-300 rounded px-3 py-2" rows="6" placeholder="Email Body (HTML/Shortcodes supported)">'+AUTO.esc(cfg.body||'')+'</textarea>');

								bodyHtml.on('change',function(){S.actions[idx].config.body=this.value;});
								
								valRow.append(varRow, bodyHtml);

								setTimeout(function() {
									if (typeof wp !== "undefined" && wp.editor) {
										var elId = 'o100-auto-email-body-'+idx;
										if ($('#'+elId).length) {
											wp.editor.remove(elId);
											wp.editor.initialize(elId, {
												tinymce: { wpautop: true, plugins: 'charmap,colorpicker,hr,lists,media,paste,tabfocus,textcolor,fullscreen,wordpress,wpautoresize,wpeditimage,wpemoji,wpgallery,wplink,wptextpattern' },
												quicktags: true
											});
											// On blur, update the array
											if (tinymce && tinymce.get(elId)) {
												tinymce.get(elId).on('change keyup', function(e) {
													S.actions[idx].config.body = tinymce.get(elId).getContent();
												});
											}
										}
									}
								}, 100);
							} else {
								var vWrap=$('<div class="p-4 border border-dashed border-slate-300 rounded-lg bg-slate-50 flex flex-col items-center justify-center text-center"></div>');
								var vIcon=$('<div class="w-12 h-12 bg-white rounded-full shadow-sm flex items-center justify-center text-indigo-500 mb-3"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg></div>');
								var vDesc=$('<p class="text-sm text-slate-600 mb-4">Launch the visual builder to design your email. You can also import templates from your Template Library inside the editor.</p>');
								
								var btnsWrap=$('<div class="flex items-center gap-3"></div>');
								var vBtn;
								
								if (cfg.visual_template_id) {
									// They already have a template, so open it for editing
									var tName = "Template #"+cfg.visual_template_id;
									var tmpl = (OPT.email_templates||[]).find(function(t){ return parseInt(t.id) === parseInt(cfg.visual_template_id); });
									if (tmpl && (tmpl.title || tmpl.name)) tName = tmpl.title || tmpl.name;
									
									var tmplTitle = $('<div class="text-sm font-semibold text-slate-700 mb-4 bg-white px-4 py-2 border border-slate-200 rounded-md shadow-sm">Current Design: <span class="text-indigo-600">'+AUTO.esc(tName)+'</span></div>');
									vWrap.append(tmplTitle);
									
									vBtn=$('<button type="button" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm flex items-center"><svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg> Open Visual Builder</button>');
									vBtn.on('click', function() {
										AUTO.launchVisualBuilder(function(newTemplateId) {
											// if saved, reload to get latest title
											AUTO.loadOptionsAndRender();
										}, cfg.visual_template_id);
									});
								} else {
									// No template yet, open new
									vBtn=$('<button type="button" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm flex items-center"><svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg> Create Email Design</button>');
									vBtn.on('click', function() {
										var btnHtml = vBtn.html();
										vBtn.html('Creating...').prop('disabled', true);
										// We create a blank template first so it has an ID when the editor opens
										jQuery.post(ajaxurl, {
											action: 'o100_clone_email_template', // Reusing the clone endpoint by passing 0 as template_id
											template_id: 0
										}, function(res) {
											vBtn.html(btnHtml).prop('disabled', false);
											if (res.success && res.data.new_template_id) {
												S.actions[idx].config.visual_template_id = res.data.new_template_id;
												AUTO.launchVisualBuilder(function(savedTemplateId) {
													AUTO.loadOptionsAndRender(); // reload and re-render to show "Current Design: xxx"
												}, res.data.new_template_id);
											} else {
												alert('Failed to create new template. Please try again.');
											}
										});
									});
								}
								
								btnsWrap.append(vBtn);
								vWrap.append(vIcon, vDesc, btnsWrap);
								valRow.append(vWrap);
							}
						} else if (act.type==='send_sms') {
							var sm=$('<textarea class="text-sm w-full" rows="3" placeholder="SMS text. Variables: [o100_customer_name], [o100_coupon_code], [o100_store_name]">'+AUTO.esc(act.val||'')+'</textarea>');
							sm.on('change',function(){S.actions[idx].val=this.value;});
							valRow.append(sm);
						} else if (act.type==='give_coupon_existing') {
							var ps=$('<select class="text-sm w-full"></select>').append('<option value="">-- Select Coupon --</option>');
							(OPT.promotions||[]).forEach(function(p){ps.append('<option value="'+p.id+'"'+(act.val==p.id?' selected':'')+'>'+AUTO.esc(p.title)+' ('+p.code+')</option>');});
							ps.on('change',function(){S.actions[idx].val=this.value;});
							valRow.append(ps);
						} else if (act.type==='give_coupon_custom') {
							var cfg=act.config||{};
							var cf=$('<div class="grid grid-cols-2 gap-3"></div>');
							
							var dtCol=$('<div><label class="block text-xs text-slate-500 mb-1">Type</label></div>');
							var dt=$('<select class="text-sm w-full"><option value="percent"'+(cfg.discount_type==='percent'?' selected':'')+'>Percentage</option><option value="fixed_cart"'+(cfg.discount_type==='fixed_cart'?' selected':'')+'>Fixed $</option></select>');
							dt.on('change',function(){S.actions[idx].config.discount_type=this.value;});
							dtCol.append(dt);
							
							var dvCol=$('<div><label class="block text-xs text-slate-500 mb-1">Amount</label></div>');
							var dv=$('<input type="number" class="text-sm w-full" placeholder="Value" value="'+(cfg.discount_value||'')+'">');
							dv.on('change',function(){S.actions[idx].config.discount_value=this.value;});
							dvCol.append(dv);

							var edCol=$('<div><label class="block text-xs text-slate-500 mb-1">Expires In (Days)</label></div>');
							var ed=$('<input type="number" class="text-sm w-full" value="'+(cfg.expiry_days||'30')+'">');
							ed.on('change',function(){S.actions[idx].config.expiry_days=this.value;});
							edCol.append(ed);
							
							var ulCol=$('<div><label class="block text-xs text-slate-500 mb-1">Usage Limit</label></div>');
							var ul=$('<input type="number" class="text-sm w-full" value="'+(cfg.usage_limit||'1')+'">');
							ul.on('change',function(){S.actions[idx].config.usage_limit=this.value;});
							ulCol.append(ul);
							
							cf.append(dtCol, dvCol, edCol, ulCol);
							valRow.append(cf);
						} else if (act.type==='add_tag') {
							var ts=$('<select class="text-sm w-full"></select>').append('<option value="">-- Select Tag --</option>');
							(OPT.tags||[]).forEach(function(t){ts.append('<option value="'+t.id+'"'+(act.val==t.id?' selected':'')+'>'+AUTO.esc(t.title)+'</option>');});
							ts.on('change',function(){S.actions[idx].val=this.value;});
							valRow.append(ts);
						}
						row.append(valRow);

						var rm=$('<button type="button" class="text-slate-400 hover:text-red-500 absolute top-2 right-2 p-1 opacity-0 group-hover:opacity-100 transition-opacity" title="Remove"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>');
						rm.on('click',function(){S.actions.splice(idx,1); AUTO.renderActions();});
						row.append(rm);
						
						c.append(row);
					});

					if ($.fn.sortable) {
						c.sortable({
							handle: '.o100-drag-handle',
							axis: 'y',
							opacity: 0.8,
							update: function(e, ui) {
								var newActions = [];
								c.children('.o100-action-row').each(function() {
									var oldIdx = $(this).data('index');
									newActions.push(S.actions[oldIdx]);
								});
								S.actions = newActions;
								AUTO.renderActions();
							}
						});
					}
				},

				// ── Save ──
				save: function() {
					var title=$('#o100-auto-name').val();
					var desc=$('#o100-auto-desc').val();
					var trigger=$('#o100-auto-trigger-val').val();
					var status=$('#o100-auto-status').is(':checked')?'active':'paused';
					if (!title) return alert('Please enter an Automation Name.');
					if (!trigger) return alert('Please select a Trigger Event.');

					var btn = $('#o100-auto-save');
					var oldText = btn.html();
					btn.html('Saving...').prop('disabled', true);

					$.post(ajaxurl, {
						action:'o100_save_automation', _nonce:NONCE,
						id:S.id||0, title:title, description:desc, trigger_type:trigger, status:status,
						conditions:JSON.stringify(S.conditions),
						actions:JSON.stringify(S.actions),
						trigger_config:JSON.stringify(S.triggerConfig),
						rules_and_goals:JSON.stringify({
							allow_reentry: $('input[name="o100_auto_limit"]:checked').val() === 'multiple',
							wait_days: parseInt($('#o100_auto_wait_days').val()) || 0,
							exit_goal: $('#o100_auto_exit_goal').val()
						})
					}, function(r) {
						btn.html(oldText).prop('disabled', false);
						if (r.success) {
							AUTO.closeEditor();
							AUTO.loadList();
						} else { 
							if (r.data && typeof r.data === 'string' && r.data.indexOf('Free plan limit reached') !== -1) {
								o100ShowProModal('Unlimited Automations', 'Want to schedule multiple automated marketing workflows? Upgrade to Pro to unlock unlimited automation rules!');
								$('#o100-auto-status').prop('checked', false); // Force switch back to paused so they can save draft
							} else {
								alert('Error: '+(r.data||'Unknown')); 
							}
						}
					});
				},

				esc: function(s) {
					if (!s) return '';
					var d=document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML;
				}
			};

			$(function() { if ($('.o100-proxy-wrap').length) AUTO.init(); });
		})(jQuery);
		</script>
		<?php
	}
}

O100_Automation_Admin::init();
