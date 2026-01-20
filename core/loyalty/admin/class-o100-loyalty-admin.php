<?php
/**
 * Loyalty Admin UI
 *
 * Handles the admin interface for the Loyalty module,
 * rendered inside the CMB2 "Loyalty" tab with nested sub-tabs.
 *
 * @package Order100
 * @since   4.0.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class O100_Loyalty_Admin {

	/** @var string Path to view templates */
	private $views_path;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->views_path = O100_PATH . 'core/loyalty/admin/views/';
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Levels AJAX Actions
		add_action( 'wp_ajax_o100_loyalty_get_levels', array( $this, 'ajax_get_levels' ) );
		add_action( 'wp_ajax_o100_loyalty_save_level', array( $this, 'ajax_save_level' ) );
		add_action( 'wp_ajax_o100_loyalty_delete_level', array( $this, 'ajax_delete_level' ) );
		add_action( 'wp_ajax_o100_loyalty_save_level_settings', array( $this, 'ajax_save_level_settings' ) );
		add_action( 'wp_ajax_o100_loyalty_get_level_settings', array( $this, 'ajax_get_level_settings' ) );

		// Customer AJAX Actions
		add_action( 'wp_ajax_o100_loyalty_get_customers', array( $this, 'ajax_get_customers' ) );
		add_action( 'wp_ajax_o100_loyalty_get_customer_detail', array( $this, 'ajax_get_customer_detail' ) );
		add_action( 'wp_ajax_o100_loyalty_get_customer_full_data', array( $this, 'ajax_get_customer_full_data' ) );
		add_action( 'wp_ajax_o100_loyalty_get_transactions', array( $this, 'ajax_get_transactions' ) );
		add_action( 'wp_ajax_o100_loyalty_get_customer_rewards', array( $this, 'ajax_get_customer_rewards' ) );
		add_action( 'wp_ajax_o100_loyalty_adjust_points', array( $this, 'ajax_adjust_points' ) );
		add_action( 'wp_ajax_o100_loyalty_toggle_customer_status', array( $this, 'ajax_toggle_customer_status' ) );
		add_action( 'wp_ajax_o100_loyalty_save_customer', array( $this, 'ajax_save_customer' ) );
		add_action( 'wp_ajax_o100_loyalty_export_customers', array( $this, 'ajax_export_customers' ) );

		// Settings / Launcher Actions
		add_action( 'wp_ajax_o100_loyalty_get_launcher_settings', array( $this, 'ajax_get_launcher_settings' ) );
		add_action( 'wp_ajax_o100_loyalty_save_launcher_settings', array( $this, 'ajax_save_launcher_settings' ) );
		add_action( 'wp_ajax_o100_save_loyalty_settings_all', array( $this, 'ajax_save_loyalty_settings_all' ) );

		// Dashboard Data
		add_action( 'wp_ajax_o100_loyalty_dashboard_analytic_data', array( $this, 'ajax_dashboard_analytic_data' ) );
		add_action( 'wp_ajax_o100_loyalty_all_customer_activities', array( $this, 'ajax_all_customer_activities' ) );
		add_action( 'wp_ajax_o100_loyalty_chart_data', array( $this, 'ajax_chart_data' ) );

		// Campaigns & Rewards AJAX Actions
		add_action( 'wp_ajax_o100_save_campaign', array( $this, 'ajax_save_campaign' ) );
		add_action( 'wp_ajax_o100_campaign_status', array( $this, 'ajax_campaign_status' ) );
		add_action( 'wp_ajax_o100_campaign_delete', array( $this, 'ajax_campaign_delete' ) );
		add_action( 'wp_ajax_o100_save_reward', array( $this, 'ajax_save_reward' ) );
		add_action( 'wp_ajax_o100_reward_status', array( $this, 'ajax_reward_status' ) );
		add_action( 'wp_ajax_o100_reward_delete', array( $this, 'ajax_reward_delete' ) );

		// Data Providers for Conditions
		add_action( 'wp_ajax_o100_condition_data', array( $this, 'ajax_condition_data' ) );
		add_action( 'wp_ajax_o100_get_customer_list', array( $this, 'ajax_get_customer_list' ) );
		add_action( 'wp_ajax_o100_free_product_options', array( $this, 'ajax_free_product_options' ) );
	}

	/**
	 * Enqueue admin assets on our settings page only
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'o100' ) === false ) {
			return;
		}

		$css_file = O100_PATH . 'assets/css/o100-loyalty-admin.css';
		$js_file  = O100_PATH . 'assets/js/o100-loyalty-admin.js';

		$css_ver = file_exists( $css_file ) ? O100_VERSION . '.' . filemtime( $css_file ) : O100_VERSION;
		$js_ver  = file_exists( $js_file ) ? O100_VERSION . '.' . filemtime( $js_file ) : O100_VERSION;

		wp_enqueue_style( 'o100-loyalty-admin', O100_URL . 'assets/css/o100-loyalty-admin.css', array(), $css_ver );
		wp_enqueue_script( 'o100-loyalty-chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true );
		
		wp_enqueue_media();
		
		wp_enqueue_style( 'o100-select2-core', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
		wp_enqueue_script( 'o100-select2-core-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true );

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_script( 'o100-loyalty-admin', O100_URL . 'assets/js/o100-loyalty-admin.js', array( 'jquery', 'o100-loyalty-chartjs', 'o100-select2-core-js', 'wp-color-picker' ), $js_ver, true );

		$roles = array();
		if ( function_exists( 'get_editable_roles' ) ) {
			foreach ( get_editable_roles() as $role_id => $role_info ) {
				$roles[] = array( 'id' => $role_id, 'text' => $role_info['name'] );
			}
		}

		$currencies = array();
		if ( function_exists( 'get_woocommerce_currencies' ) ) {
			foreach ( get_woocommerce_currencies() as $code => $name ) {
				$currencies[] = array( 'id' => $code, 'text' => $code . ' - ' . $name );
			}
		}

		wp_localize_script( 'o100-loyalty-admin', 'o100_loyalty', array(
			'ajax_url'        => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'o100_loyalty_nonce' ),
			'dashboard_nonce' => wp_create_nonce( 'o100_dashboard_nonce' ),
			'campaign_nonce'  => wp_create_nonce( 'o100-earn-campaign-nonce' ),
			'reward_nonce'    => wp_create_nonce( 'o100-reward-nonce' ),
			'customer_nonce'  => wp_create_nonce( 'o100-customers-nonce' ),
			'roles'           => $roles,
			'currencies'      => $currencies,
			'i18n'            => array(
				'loading'        => __( 'Loading...', 'order100' ),
				'saving'         => __( 'Saving...', 'order100' ),
				'saved'          => __( 'Settings saved!', 'order100' ),
				'error'          => __( 'An error occurred.', 'order100' ),
				'confirm_delete' => __( 'Are you sure?', 'order100' ),
			),
		) );
	}

	/**
	 * Main entry point for rendering the page from Order100 Admin Menu
	 */
	public static function render_page() {
		$instance = new self();
		$instance->render_loyalty_tab( null, null );
	}

	/**
	 * Render the Loyalty & Referral tab content (CMB2 after_row callback)
	 */
	public function render_loyalty_tab( $field_args = null, $field = null ) {
		$sub_tabs = array(
			'dashboard' => __( 'Dashboard', 'order100' ),
			'customers' => __( 'Customers', 'order100' ),
			'campaigns' => __( 'Campaigns', 'order100' ),
			'coupons'   => __( 'Coupons', 'order100' ),
			'levels'    => __( 'Levels', 'order100' ),
			'settings'  => __( 'Settings', 'order100' ),
		);

		$icons = array(
			'dashboard' => 'dashicons-dashboard',
			'customers' => 'dashicons-groups',
			'campaigns' => 'dashicons-megaphone',
			'coupons'   => 'dashicons-awards',
			'levels'    => 'dashicons-chart-bar',
			'settings'  => 'dashicons-admin-generic',
		);
		?>
		<div class="cmb-row o100-tab-loyalty" style="border-bottom:none; padding:10px 0;">
			<div class="o100-loyalty-wrap">
				<ul class="o100-loyalty-subtabs">
					<?php foreach ( $sub_tabs as $id => $label ) : ?>
						<li>
							<a href="#" data-subtab="<?php echo esc_attr( $id ); ?>">
								<span class="dashicons <?php echo esc_attr( $icons[ $id ] ); ?>"></span>
								<?php echo esc_html( $label ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php foreach ( $sub_tabs as $id => $label ) : ?>
					<div class="o100-loyalty-subtab-content" data-subtab="<?php echo esc_attr( $id ); ?>"
						 style="<?php echo $id !== 'dashboard' ? 'display:none;' : ''; ?>">
						<?php $this->render_subtab( $id ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private function render_subtab( $tab_id ) {
		$file = $this->views_path . $tab_id . '.php';
		if ( file_exists( $file ) ) {
			include $file;
		}
	}

	public static function get_dashboard_stats() {
		return O100_Loyalty_DB::get_dashboard_stats();
	}

	public static function get_recent_activities( $limit = 10 ) {
		return O100_Loyalty_DB::get_recent_transactions( $limit );
	}

	public static function get_loyalty_settings() {
		return O100_Loyalty_DB::get_settings();
	}

	// ═══════════════════════════════════════════════════════════
	// LEVELS AJAX
	// ═══════════════════════════════════════════════════════════

	public function ajax_get_levels() {
		$levels = O100_Loyalty_DB::get_levels();
		wp_send_json_success( $levels );
	}

	public function ajax_save_level() {
		$data = array(
			'name'       => sanitize_text_field( $_POST['name'] ?? '' ),
			'min_points' => intval( $_POST['min_points'] ?? 0 ),
			'max_points' => intval( $_POST['max_points'] ?? 0 ),
			'icon'       => sanitize_url( $_POST['icon'] ?? '' ),
			'perks'      => wp_kses_post( wp_unslash( $_POST['perks'] ?? '' ) ),
			'sort_order' => intval( $_POST['sort_order'] ?? 0 )
		);

		if ( isset( $_POST['id'] ) && intval( $_POST['id'] ) > 0 ) {
			O100_Loyalty_DB::update_level( intval( $_POST['id'] ), $data );
			$id = intval( $_POST['id'] );
		} else {
			$id = O100_Loyalty_DB::insert_level( $data );
		}

		wp_send_json_success( array( 'id' => $id, 'message' => __( 'Level saved successfully!', 'order100' ) ) );
	}

	public function ajax_delete_level() {
		$id = intval( $_POST['id'] ?? 0 );
		if ( $id ) {
			O100_Loyalty_DB::delete_level( $id );
			wp_send_json_success( array( 'message' => __( 'Level deleted', 'order100' ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'Invalid ID', 'order100' ) ) );
	}

	public function ajax_save_level_settings() {
		$settings = get_option( 'o100_loyalty_settings', array() );
		$settings['levels_from_which_point_based'] = sanitize_text_field( $_POST['levels_from_which_point_based'] ?? 'from_current_balance' );
		update_option( 'o100_loyalty_settings', $settings );
		wp_send_json_success( array( 'message' => __( 'Global settings saved successfully!', 'order100' ) ) );
	}

	public function ajax_get_level_settings() {
		wp_send_json_success( get_option( 'o100_loyalty_settings', array() ) );
	}

	// ═══════════════════════════════════════════════════════════
	// CUSTOMERS AJAX
	// ═══════════════════════════════════════════════════════════

	public function ajax_get_customers() {
		$page   = isset( $_POST['paged'] ) ? max( 1, intval( $_POST['paged'] ) ) : 1;
		$limit  = 20;
		$search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';

		$args = array(
			'page'     => $page,
			'per_page' => $limit,
			'search'   => $search,
			'orderby'  => sanitize_text_field( $_POST['filter_order'] ?? 'id' ),
			'order'    => sanitize_text_field( $_POST['filter_order_dir'] ?? 'DESC' ),
		);

		$res = O100_Loyalty_DB::get_accounts( $args );
		
		// Map for JS compatibility
		$items = array();
		foreach ( $res['items'] as $item ) {
			$lvl = $item->level_id ? O100_Loyalty_DB::get_level( $item->level_id ) : null;
			$items[] = array(
				'id'             => $item->id,
				'user_email'     => $item->email,
				'points'         => $item->points_balance,
				'refer_code'     => $item->refer_code,
				'level_name'     => $lvl ? $lvl->name : '-',
				'is_banned'      => $item->status === 'banned' ? 1 : 0,
				'created_at'     => strtotime( $item->created_at )
			);
		}

		wp_send_json_success( array(
			'items'       => $items,
			'total_count' => $res['total_count'],
			'page'        => $page,
			'limit'       => $limit,
		) );
	}

	public function ajax_get_customer_detail() {
		$id = intval( $_POST['id'] ?? 0 );
		$account = O100_Loyalty_DB::get_account( $id );
		if ( ! $account ) wp_send_json_error( array( 'message' => 'Customer not found' ) );

		$lvl = $account->level_id ? O100_Loyalty_DB::get_level( $account->level_id ) : null;
		
		$data = array(
			'id'            => $account->id,
			'user_email'    => $account->email,
			'points'        => $account->points_balance,
			'refer_code'    => $account->refer_code,
			'level_name'    => $lvl ? $lvl->name : '-',
			'birthday_date' => $account->birthday,
		);
		wp_send_json_success( (object) $data );
	}

	public function ajax_get_transactions() {
		$email = sanitize_email( $_POST['email'] ?? '' );
		$account = O100_Loyalty_DB::get_account_by_email( $email );
		if ( ! $account ) wp_send_json_error( array( 'message' => 'Account not found' ) );

		$transactions = O100_Loyalty_DB::get_transactions( $account->id, 100, 0 );
		$items = array();

		foreach ( $transactions as $t ) {
			$items[] = array(
				'type'         => $t->type === 'earn' ? 'credit' : 'debit',
				'points'       => $t->points,
				'activity'     => $t->note,
				'date_display' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $t->created_at ) )
			);
		}

		wp_send_json_success( $items );
	}

	public function ajax_adjust_points() {
		$id     = intval( $_POST['id'] ?? 0 );
		$points = intval( $_POST['points'] ?? 0 );
		$type   = sanitize_text_field( $_POST['action_type'] ?? 'add' );
		$note   = sanitize_text_field( $_POST['comments'] ?? '' );

		$account = O100_Loyalty_DB::get_account( $id );
		if ( ! $account ) wp_send_json_error( array( 'message' => 'Customer not found' ) );

		$new_balance = $account->points_balance;
		if ( $type === 'add' ) {
			$new_balance += $points;
		} elseif ( $type === 'reduce' ) {
			$new_balance = max( 0, $new_balance - $points );
		} elseif ( $type === 'overwrite' ) {
			$new_balance = $points;
		}

		O100_Loyalty_DB::adjust_points( $id, $new_balance, $note );
		wp_send_json_success( array( 'message' => 'Points adjusted successfully!', 'new_points' => $new_balance ) );
	}

	public function ajax_toggle_customer_status() {
		$id = intval( $_POST['id'] ?? 0 );
		$status = intval( $_POST['is_banned'] ?? 0 ) ? 'banned' : 'active';
		O100_Loyalty_DB::update_account( $id, array( 'status' => $status ) );
		wp_send_json_success( array( 'message' => 'Status updated' ) );
	}

	public function ajax_save_customer() {
		$id = intval( $_POST['id'] ?? 0 );
		$birthday = sanitize_text_field( $_POST['birthday_date'] ?? '' );
		O100_Loyalty_DB::update_account( $id, array( 'birthday' => $birthday ? $birthday : null ) );
		wp_send_json_success( array( 'message' => 'Customer updated' ) );
	}

	public function ajax_export_customers() {
		// Basic export shim
		wp_send_json_error( array( 'message' => 'Export mapped to native DB coming soon.' ) );
	}

	// ═══════════════════════════════════════════════════════════
	// SETTINGS / LAUNCHER AJAX
	// ═══════════════════════════════════════════════════════════

	public function ajax_get_launcher_settings() {
		$settings = get_option( 'o100_launcher_settings', array() );
		wp_send_json_success( $settings );
	}

	public function ajax_save_launcher_settings() {
		$settings = get_option( 'o100_launcher_settings', array() );
		$settings['theme_color'] = sanitize_hex_color( $_POST['theme_color'] ?? '#4F47EB' );
		$settings['launcher_text'] = sanitize_text_field( $_POST['launcher_text'] ?? 'Rewards' );
		$settings['position'] = sanitize_text_field( $_POST['position'] ?? 'right' );
		update_option( 'o100_launcher_settings', $settings );
		wp_send_json_success( array( 'message' => 'Launcher saved' ) );
	}

	public function ajax_save_loyalty_settings_all() {
		$settings = O100_Loyalty_DB::get_settings();
		foreach ( $_POST as $k => $v ) {
			if ( strpos( $k, 'o100_' ) !== false || strpos( $k, 'wlr_' ) !== false ) {
				$key = str_replace( array( 'wlr_', 'o100_' ), '', $k );
				$settings[ $key ] = is_array( $v ) ? array_map('sanitize_text_field', $v) : sanitize_text_field( wp_unslash( $v ) );
			}
		}
		O100_Loyalty_DB::save_settings( $settings );
		wp_send_json_success( array( 'message' => 'Settings saved' ) );
	}

	// ═══════════════════════════════════════════════════════════
	// DASHBOARD CHARTS
	// ═══════════════════════════════════════════════════════════

	public function ajax_dashboard_analytic_data() {
		$stats = O100_Loyalty_DB::get_dashboard_stats();
		wp_send_json_success( array(
			'points_earned' => $stats['total_earned'] ?? 0,
			'points_spent'  => $stats['total_spent'] ?? 0,
			'total_users'   => $stats['total_customers'] ?? 0,
			'coupons_used'  => 0
		) );
	}

	public function ajax_chart_data() {
		wp_send_json_success( array(
			'labels' => [date('M d')],
			'earned' => [O100_Loyalty_DB::get_dashboard_stats()['total_earned'] ?? 0],
			'spent'  => [O100_Loyalty_DB::get_dashboard_stats()['total_spent'] ?? 0]
		) );
	}

	public function ajax_all_customer_activities() {
		$limit = intval( $_POST['limit'] ?? 10 );
		$transactions = O100_Loyalty_DB::get_recent_transactions( $limit );
		
		$items = array();
		foreach ( $transactions as $t ) {
			$items[] = array(
				'user_email' => $t->email,
				'activity'   => $t->note,
				'points'     => $t->points,
				'date'       => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $t->created_at ) )
			);
		}
		
		wp_send_json_success( array( 'activities' => $items ) );
	}

	public function ajax_get_customer_full_data() {
		$this->ajax_get_customer_detail();
	}
	
	public function ajax_get_customer_rewards() {
		wp_send_json_success( array() );
	}

	private function sanitize_array_recursive( $array ) {
		if ( ! is_array( $array ) ) {
			return sanitize_text_field( $array );
		}
		$sanitized = array();
		foreach ( $array as $key => $value ) {
			$sanitized[ sanitize_text_field( $key ) ] = is_array( $value ) ? $this->sanitize_array_recursive( $value ) : sanitize_text_field( $value );
		}
		return $sanitized;
	}

	public function ajax_save_campaign() {
		check_ajax_referer( 'o100-campaign-nonce', 'o100_nonce' );

		$id          = intval( $_POST['id'] ?? 0 );
		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$action_type = sanitize_text_field( wp_unslash( $_POST['action_type'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$active      = isset( $_POST['active'] ) ? intval( $_POST['active'] ) : 0;
		$status      = $active ? 'active' : 'inactive';
		
		$ui_data = array(
			'action_type' => $action_type,
			'point_rule'  => isset( $_POST['point_rule'] ) ? $this->sanitize_array_recursive( wp_unslash( $_POST['point_rule'] ) ) : array(),
			'start_at'    => sanitize_text_field( wp_unslash( $_POST['start_at'] ?? '' ) ),
			'end_at'      => sanitize_text_field( wp_unslash( $_POST['end_at'] ?? '' ) ),
		);
		
		$conditions = isset( $_POST['conditions'] ) ? $this->sanitize_array_recursive( wp_unslash( $_POST['conditions'] ) ) : array();
		
		// Build earn_config from point_rule for Engine compatibility
		$point_rule = $ui_data['point_rule'] ?? [];

		// Auto-create WooCommerce coupons for referral campaigns when "__custom__" is selected
		if ( $action_type === 'referral' ) {
			foreach ( [ 'advocate', 'friend' ] as $role ) {
				if ( isset( $point_rule[ $role ]['earn_reward'] ) && $point_rule[ $role ]['earn_reward'] === '__custom__' ) {
					$c_type   = sanitize_text_field( $point_rule[ $role ]['custom_coupon_type'] ?? 'fixed_cart' );
					$c_value  = floatval( $point_rule[ $role ]['custom_coupon_value'] ?? 5 );
					$c_expiry = intval( $point_rule[ $role ]['custom_coupon_expiry'] ?? 30 );
					$c_limit  = intval( $point_rule[ $role ]['custom_coupon_limit'] ?? 1 );

					$code = 'O100-REF-' . strtoupper( $role ) . '-' . wp_generate_password( 6, false, false );
					$coupon = new WC_Coupon();
					$coupon->set_code( $code );
					$coupon->set_discount_type( $c_type );
					$coupon->set_amount( $c_value );
					$coupon->set_usage_limit( $c_limit );
					$coupon->set_individual_use( true );
					if ( $c_expiry > 0 ) {
						$expiry_date = new WC_DateTime();
						$expiry_date->modify( '+' . $c_expiry . ' days' );
						$coupon->set_date_expires( $expiry_date );
					}
					$coupon->save();

					// Replace __custom__ with the real coupon ID
					$point_rule[ $role ]['earn_reward'] = $coupon->get_id();
					$point_rule[ $role ]['earn_reward_config'] = [
						'type'   => $c_type,
						'value'  => $c_value,
						'expiry' => $c_expiry,
						'limit'  => $c_limit,
						'coupon_code' => $code,
					];

					// Clean up temp fields
					unset( $point_rule[ $role ]['custom_coupon_type'] );
					unset( $point_rule[ $role ]['custom_coupon_value'] );
					unset( $point_rule[ $role ]['custom_coupon_expiry'] );
					unset( $point_rule[ $role ]['custom_coupon_limit'] );
				}
			}
			// Update ui_data with processed point_rule
			$ui_data['point_rule'] = $point_rule;
		}
		
		$data = array(
			'title'                  => $name,
			'type'                   => $action_type ?: 'point_for_purchase',
			'description'            => $description,
			'status'                 => $status,
			'earn_config'            => wp_json_encode( $point_rule ),
			'ui_json'                => wp_json_encode( $ui_data ),
			'conditions'             => wp_json_encode( array_values( $conditions ) ),
			'condition_relationship' => sanitize_text_field( wp_unslash( $_POST['condition_relationship'] ?? 'and' ) ),
			'is_show_way_to_earn'    => 1,
			'start_at'               => $ui_data['start_at'] ?: null,
			'end_at'                 => $ui_data['end_at'] ?: null,
		);

		global $wpdb;
		$table = O100_Loyalty_DB::get_campaigns_table();
		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$id = $wpdb->insert_id;
		}

		wp_send_json_success( array( 'message' => __( 'Campaign saved successfully!', 'order100' ), 'id' => $id ) );
	}

	public function ajax_campaign_status() {
		$id     = intval( $_POST['id'] ?? 0 );
		$status = intval( $_POST['is_active'] ?? 0 ) ? 'active' : 'inactive';
		
		if ( $id > 0 ) {
			global $wpdb;
			$wpdb->update( O100_Loyalty_DB::get_campaigns_table(), array( 'status' => $status ), array( 'id' => $id ) );
			wp_send_json_success( array( 'message' => __( 'Status updated!', 'order100' ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'Invalid campaign', 'order100' ) ) );
	}

	public function ajax_campaign_delete() {
		$id = intval( $_POST['id'] ?? 0 );
		if ( $id > 0 ) {
			global $wpdb;
			$wpdb->delete( O100_Loyalty_DB::get_campaigns_table(), array( 'id' => $id ) );
			wp_send_json_success( array( 'message' => __( 'Campaign deleted!', 'order100' ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'Invalid campaign', 'order100' ) ) );
	}

	public function ajax_save_reward() {
		// Native rewards are managed via the Promotions module now, but if there's a specific loyalty conversion reward, handle it.
		wp_send_json_success( array( 'message' => __( 'Rewards are now managed via Promotions Engine', 'order100' ) ) );
	}

	public function ajax_reward_status() { wp_send_json_success(); }
	public function ajax_reward_delete() { wp_send_json_success(); }

	public function ajax_condition_data() {
		$method = sanitize_text_field( $_GET['method'] ?? '' );
		$search = sanitize_text_field( $_GET['search'] ?? '' );
		$results = array();

		if ( $method === 'productCategory' ) {
			$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'name__like' => $search ) );
			foreach ( $terms as $term ) {
				$results[] = array( 'id' => $term->term_id, 'text' => $term->name );
			}
		} elseif ( $method === 'productTags' ) {
			$terms = get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false, 'name__like' => $search ) );
			foreach ( $terms as $term ) {
				$results[] = array( 'id' => $term->term_id, 'text' => $term->name );
			}
		} elseif ( $method === 'userLevel' ) {
			$levels = O100_Loyalty_DB::get_levels();
			foreach ( $levels as $lvl ) {
				$results[] = array( 'id' => $lvl->id, 'text' => $lvl->name );
			}
		}

		wp_send_json_success( array( 'items' => $results ) );
	}

	public function ajax_get_customer_list() {
		$search = sanitize_text_field( $_GET['search'] ?? '' );
		$users = get_users( array( 'search' => "*{$search}*", 'search_columns' => array( 'user_login', 'user_email', 'display_name' ), 'number' => 20 ) );
		$results = array();
		foreach ( $users as $user ) {
			$results[] = array( 'id' => $user->ID, 'text' => $user->user_email );
		}
		wp_send_json_success( array( 'items' => $results ) );
	}

	public function ajax_free_product_options() {
		$search = sanitize_text_field( $_GET['search'] ?? '' );
		$args = array( 'post_type' => 'product', 'post_status' => 'publish', 's' => $search, 'posts_per_page' => 20 );
		$query = new WP_Query( $args );
		$results = array();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$results[] = array( 'id' => get_the_ID(), 'text' => get_the_title() );
			}
			wp_reset_postdata();
		}
		wp_send_json_success( array( 'items' => $results ) );
	}
}

// TS: 20260117173732

// TS: 20260119173147
