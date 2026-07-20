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

		// Customer AJAX Actions

		// Settings / Launcher Actions

		// Dashboard Data
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
		wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0', true );
		
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
			'rest_url'        => esc_url_raw( rest_url() ),
			'rest_nonce'      => wp_create_nonce( 'wp_rest' ),
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
	 * Render the Loyalty & Referral tab content (CMB2 after_row callback)
	 */
	public function render_loyalty_tab( $field_args, $field ) {
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

	}
