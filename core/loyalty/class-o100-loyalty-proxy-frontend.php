<?php
/**
 * O100 Loyalty Proxy Frontend
 * 
 * Handles frontend integrations, text replacements, and UI overrides
 * to map native WPLoyalty structures into the Order100 user experience (e.g., Punch Cards).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Loyalty_Proxy_Frontend {

	public static function init() {
		// Completely disable the WPLoyalty native frontend widget
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'dequeue_launcher_assets' ], 9999 );

		// Restore WooCommerce My Account Endpoints
		add_action( 'init', [ __CLASS__, 'add_endpoints' ] );
		add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'modify_menu_items' ] );
		add_action( 'woocommerce_account_o100_points_endpoint', [ __CLASS__, 'render_points_page' ] );
		add_action( 'woocommerce_account_o100_rewards_endpoint', [ __CLASS__, 'render_rewards_page' ] );
	}
	
	public static function add_endpoints() {
		add_rewrite_endpoint( 'o100_points', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'o100_rewards', EP_ROOT | EP_PAGES );
	}

	public static function modify_menu_items( $items ) {
		// Remove native WPLoyalty item
		if ( isset( $items['loyalty_reward'] ) ) {
			unset( $items['loyalty_reward'] );
		}
		
		// Insert our new items before 'customer-logout'
		$new_items = [];
		foreach ( $items as $key => $value ) {
			if ( $key === 'customer-logout' ) {
				$new_items['o100_points']  = esc_html__( 'Points', 'order100' );
				$new_items['o100_rewards'] = esc_html__( 'Rewards', 'order100' );
			}
			$new_items[ $key ] = $value;
		}
		
		// Fallback if customer-logout doesn't exist
		if ( ! isset( $new_items['o100_points'] ) ) {
			$new_items['o100_points']  = esc_html__( 'Points', 'order100' );
			$new_items['o100_rewards'] = esc_html__( 'Rewards', 'order100' );
		}
		
		return $new_items;
	}

	// (Removed filter_point_label, filter_campaign_selected_data, filter_point_for_purchase_data)

	public static function dequeue_launcher_assets() {
		// Stop the native launcher from loading completely
		wp_dequeue_script('wlr-launcher');
		wp_dequeue_style('wlr-launcher');
		// Order100 checkout styles were moved into launcher CSS by mistake previously. Ensure they still load!
		wp_enqueue_style('o100-frontend-launcher-css', O100_URL . 'assets/css/o100-frontend-launcher.css', [], O100_VERSION);
	}

	public static function print_shared_css( $theme_color ) {
		?>
		<style>
			.o100-loyalty-dashboard-wrapper { font-family: 'Inter', sans-serif; }
			.o100-loyalty-header { background: <?php echo esc_attr($theme_color); ?>; border-radius: 12px; padding: 24px; color: #fff; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
			.o100-loyalty-header h3 { color: #fff; margin: 0 0 8px 0; font-size: 24px; font-weight: 700; }
			.o100-loyalty-header p { margin: 0; opacity: 0.9; font-size: 14px; }
			.o100-loyalty-balance { background: rgba(255,255,255,0.2); padding: 12px 24px; border-radius: 8px; text-align: center; }
			.o100-loyalty-balance .val { font-size: 32px; font-weight: 800; display: block; line-height: 1; margin-bottom: 4px; }
			.o100-loyalty-balance .lbl { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; opacity: 0.9; }
			
			.o100-loyalty-tabs { display: flex; border-bottom: 1px solid #e2e8f0; margin-bottom: 24px; }
			.o100-loyalty-tab { padding: 12px 24px; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.2s; border-bottom: 2px solid transparent; }
			.o100-loyalty-tab:hover { color: <?php echo esc_attr($theme_color); ?>; }
			.o100-loyalty-tab.active { color: <?php echo esc_attr($theme_color); ?>; border-bottom-color: <?php echo esc_attr($theme_color); ?>; }
			
			.o100-loyalty-tab-content { display: none; }
			.o100-loyalty-tab-content.active { display: block; animation: o100FadeIn 0.3s ease; }
			
			.o100-reward-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; }
			.o100-reward-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; text-align: center; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; justify-content: space-between; align-items: center; }
			.o100-reward-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border-color: #cbd5e1; }
			.o100-reward-card h4 { margin: 0 0 8px 0; color: #0f172a; font-size: 16px; font-weight: 700; }
			.o100-reward-card p { color: #64748b; font-size: 13px; margin: 0 0 16px 0; }
			.o100-btn-redeem { display: inline-block; background: <?php echo esc_attr($theme_color); ?>; color: #fff; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; text-decoration: none; transition: all 0.2s; border: none; cursor: pointer; width: 100%; text-align: center; box-sizing: border-box; }
			.o100-btn-redeem:hover:not([disabled]) { opacity: 0.9; color: #fff; }
			.o100-btn-redeem[disabled] { background: #e2e8f0 !important; color: #94a3b8 !important; cursor: not-allowed; }
			
			/* Native WPLoyalty overrides to match our design */
			.o100-loyalty-dashboard-wrapper .wlr-customer-reward { display: grid !important; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)) !important; gap: 20px !important; }
			.o100-loyalty-dashboard-wrapper .wlr-reward-card { background: #fff !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; padding: 20px !important; box-shadow: none !important; margin: 0 !important; width: auto !important; height: auto !important; }
			.o100-loyalty-dashboard-wrapper .wlr-reward-card:hover { transform: translateY(-2px) !important; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05) !important; border-color: #cbd5e1 !important; }
			.o100-loyalty-dashboard-wrapper .wlr-reward-card .wlr-name { margin: 0 0 8px 0 !important; color: #0f172a !important; font-size: 16px !important; font-weight: 700 !important; }
			.o100-loyalty-dashboard-wrapper .wlr-reward-card .wlr-reward-type-name { color: <?php echo esc_attr($theme_color); ?> !important; font-size: 15px !important; font-weight: 600 !important; margin: 0 0 16px 0 !important; border: none !important; padding: 0 !important; text-align: center !important; }
			.o100-loyalty-dashboard-wrapper .wlr-reward-card .wlr-button-action { display: inline-block !important; background: <?php echo esc_attr($theme_color); ?> !important; color: #fff !important; padding: 8px 16px !important; border-radius: 6px !important; font-weight: 600 !important; font-size: 13px !important; text-decoration: none !important; border: none !important; cursor: pointer !important; width: 100% !important; text-align: center !important; margin-top: 10px !important; }
			.o100-loyalty-dashboard-wrapper .wlr-reward-pagination { display: none !important; }
			
			/* Native WPLoyalty table fixes */
			.o100-loyalty-dashboard-wrapper .wlr-transaction-blog { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
			.o100-loyalty-dashboard-wrapper .wlr-transaction-blog .wlr-heading { font-size: 18px; color: #0f172a; margin-bottom: 16px; margin-top: 0; }
			.o100-loyalty-dashboard-wrapper .wlr-table { width: 100% !important; border-collapse: collapse !important; }
			.o100-loyalty-dashboard-wrapper .wlr-table th, .o100-loyalty-dashboard-wrapper .wlr-table td { padding: 12px 15px !important; text-align: left !important; border-bottom: 1px solid #e2e8f0 !important; border-top: none !important; border-left: none !important; border-right: none !important; }
			.o100-loyalty-dashboard-wrapper .wlr-table th { font-weight: 600 !important; color: #475569 !important; background: #f8fafc !important; text-transform: uppercase !important; font-size: 12px !important; }
			.o100-loyalty-dashboard-wrapper .wlr-table td { color: #0f172a !important; font-size: 14px !important; }
			.o100-loyalty-dashboard-wrapper .wlr-table a { color: <?php echo esc_attr($theme_color); ?> !important; text-decoration: none !important; }
			.o100-loyalty-dashboard-wrapper .wlr-point-conversion-box { padding: 8px !important; border: 1px solid #cbd5e1 !important; border-radius: 4px !important; width: 60px !important; text-align: center !important; }
			.o100-loyalty-dashboard-wrapper .wlr-point-conversion-box { padding: 8px !important; border: 1px solid #cbd5e1 !important; border-radius: 4px !important; width: 60px !important; text-align: center !important; }
			
			/* Milestone UI */
			.o100-milestone-wrap { text-align: center; margin: 32px 0; background: #f8fafc; padding: 24px; border-radius: 12px; border: 1px dashed #cbd5e1; }
			.o100-milestone-wrap p { margin: 0; color: #475569; font-size: 16px; font-weight: 500; }
			.o100-milestone-wrap .o100-milestone-hl { color: <?php echo esc_attr($theme_color); ?>; font-weight: 700; font-size: 18px; }
			
			@keyframes o100FadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
		</style>
		<script>
		function o100SwitchTab(e, targetId) {
			document.querySelectorAll('.o100-loyalty-tab').forEach(t => t.classList.remove('active'));
			document.querySelectorAll('.o100-loyalty-tab-content').forEach(c => c.classList.remove('active'));
			e.currentTarget.classList.add('active');
			document.getElementById(targetId).classList.add('active');
		}
		function o100RedeemPunchCard(campaignId, btn) {
			if(confirm('Ready to claim your free reward?')) {
				if(!btn && typeof event !== 'undefined' && event.currentTarget) {
					btn = event.currentTarget;
				}
				let oldText = '';
				if (btn) {
					oldText = btn.innerHTML;
					btn.innerHTML = 'Claiming...';
					btn.disabled = true;
				}
				const data = new FormData();
				data.append('action', 'o100_redeem_punch_card');
				data.append('campaign_id', campaignId);
				fetch( '<?php echo admin_url('admin-ajax.php'); ?>', {
					method: 'POST', body: data
				}).then(res => res.json()).then(res => {
					if(res.success) {
						if (typeof o100ShowToast === 'function') {
							o100ShowToast(res.data.message);
						} else {
							alert(res.data.message);
						}
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						if (typeof o100ShowToast === 'function') {
							o100ShowToast(res.data.message || 'Failed to claim reward.', 'error');
						} else {
							alert(res.data.message || 'Failed to claim reward. Please try again.');
						}
						if(btn) {
							btn.innerHTML = oldText;
							btn.disabled = false;
						}
					}
				}).catch(err => {
					if (typeof o100ShowToast === 'function') {
						o100ShowToast('Network error. Please try again.', 'error');
					} else {
						alert('Network error. Please try again.');
					}
					if(btn) {
						btn.innerHTML = oldText;
						btn.disabled = false;
					}
				});
			}
		}
		

		</script>
		<?php
	}

	public static function render_points_page() {
		if ( ! is_account_page() ) return;
		
		$user_id = get_current_user_id();
		global $wpdb;
		
		// Get Native User State and Level
		$users_table = $wpdb->prefix . 'o100_loyalty_users';
		$levels_table = $wpdb->prefix . 'o100_loyalty_levels';
		
		$user_row = $wpdb->get_row( $wpdb->prepare( "SELECT u.*, l.name as level_name FROM {$users_table} u LEFT JOIN {$levels_table} l ON u.level_id = l.id WHERE u.user_id = %d", $user_id ) );
		
		$points = $user_row ? intval( $user_row->points_balance ) : 0;
		$level_name = ($user_row && $user_row->level_name) ? $user_row->level_name : 'Member';
		$points_label = 'Points';
		$punch_card_exists = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}o100_loyalty_campaigns WHERE type = 'punch_card' AND status = 'active' LIMIT 1" );
		if ( $punch_card_exists ) {
			$points_label = ( $points == 0 || $points > 1 ) ? 'Stamps' : 'Stamp';
		}
		$options = get_option( 'o100_options', array() );
		$theme_color = ! empty( $options['o100_main_color'] ) ? $options['o100_main_color'] : '#e11d48';
		
		echo '<div class="o100-loyalty-dashboard-wrapper">';
		self::print_shared_css( $theme_color );
		?>
		<div class="o100-loyalty-header">
			<div>
				<h3>Your Points Dashboard</h3>
				<p>Level: <?php echo esc_html($level_name); ?></p>
			</div>
			<div class="o100-loyalty-balance">
				<span class="val"><?php echo esc_html($points); ?></span>
				<span class="lbl"><?php echo esc_html($points_label); ?></span>
			</div>
		</div>
		
		<?php
		// Find the Native points_conversion rule
		$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';
		$points_conversion_rule = $wpdb->get_row( "SELECT * FROM {$campaigns_table} WHERE type = 'points_conversion' AND status = 'active' LIMIT 1" );
		?>
		
		<?php if ($points_conversion_rule): 
			$ui_json = json_decode($points_conversion_rule->ui_json, true) ?: [];
			$require_pt = floatval($ui_json['conversion_points'] ?? 100);
			$disc_val = floatval($ui_json['conversion_value'] ?? 1);
			$reward_type = $ui_json['conversion_reward_type'] ?? 'discount';
			$min_pt = intval($ui_json['conv_min_points'] ?? 0);
			$max_pt = intval($ui_json['conv_max_points'] ?? 0);
			
			$currency_sym = get_woocommerce_currency_symbol();
		?>

		<div class="o100-milestone-wrap">
			<?php if ( $reward_type === 'free_item' ) : 
				$product_name = get_the_title($ui_json['freeitem_product'] ?? 0);
			?>
				<p>Redeem <span class="o100-milestone-hl"><?php echo esc_html($require_pt); ?> pts</span> for a free <span class="o100-milestone-hl"><?php echo esc_html($product_name); ?></span></p>
			<?php else : ?>
				<p>Redeem <span class="o100-milestone-hl"><?php echo esc_html($require_pt); ?> pts</span> for every <span class="o100-milestone-hl"><?php echo esc_html($currency_sym . round($disc_val, 2)); ?> off</span></p>
			<?php endif; ?>
			
			<?php if ( $min_pt > 0 ) : ?>
				<p style="font-size: 13px; color: #64748b; margin-top: 4px;">Minimum <?php echo esc_html($min_pt); ?> pts required to redeem.</p>
			<?php endif; ?>
			<p style="font-size: 14px; margin-top: 8px;">Rewards are automatically applied at checkout!</p>
		</div>
		
		<?php else: ?>
			<div style="text-align: center; padding: 30px; background: #f8fafc; border-radius: 12px; margin-bottom: 24px;">
				<p style="color: #64748b; margin: 0;">No active point conversion rules found.</p>
			</div>
		<?php endif; ?>
		
		<div style="margin-bottom: 24px;">
			<div class="wlr-transaction-blog">
				<h3 class="wlr-heading">Points History</h3>
				<?php
				$transactions = O100_Loyalty_DB::get_transactions( $user_row->id, 20, 0 );
				if ( ! empty( $transactions ) ) :
				?>
					<table class="wlr-table">
						<thead>
							<tr>
								<th>Date</th>
								<th>Activity</th>
								<th>Points</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $transactions as $txn ) : ?>
								<tr>
									<td><?php echo date_i18n( get_option( 'date_format' ), strtotime( $txn->created_at ) ); ?></td>
									<td><?php echo esc_html( $txn->note ); ?></td>
									<td style="color: <?php echo $txn->type === 'earn' ? '#10b981' : '#e11d48'; ?>; font-weight: 600;">
										<?php echo $txn->type === 'earn' ? '+' : '-'; ?><?php echo esc_html( $txn->points ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p style="color: #64748b; font-size: 14px; text-align: center; padding: 20px 0;">No history found.</p>
				<?php endif; ?>
			</div>
		</div>
		</div>
		<?php
	}

	public static function render_rewards_page() {
		if ( ! is_account_page() ) return;
		
		$user_id = get_current_user_id();
		global $wpdb;
		
		// Get Native User State and Level
		$users_table = $wpdb->prefix . 'o100_loyalty_users';
		$levels_table = $wpdb->prefix . 'o100_loyalty_levels';
		
		$user_row = $wpdb->get_row( $wpdb->prepare( "SELECT u.*, l.name as level_name FROM {$users_table} u LEFT JOIN {$levels_table} l ON u.level_id = l.id WHERE u.user_id = %d", $user_id ) );
		
		$points = $user_row ? intval( $user_row->points_balance ) : 0;
		$level_name = ($user_row && $user_row->level_name) ? $user_row->level_name : 'Member';
		$points_label = 'Points';
		$punch_card_exists = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}o100_loyalty_campaigns WHERE type = 'punch_card' AND status = 'active' LIMIT 1" );
		if ( $punch_card_exists ) {
			$points_label = ( $points == 0 || $points > 1 ) ? 'Stamps' : 'Stamp';
		}
		
		// Fetch native punch cards
		$native_punch_cards = [];
		if ( class_exists( 'O100_Native_Punch_Card' ) ) {
			$native_campaigns = O100_Native_Punch_Card::get_active_punch_cards();
			foreach ( $native_campaigns as $nc ) {
				$ui = json_decode($nc->ui_json, true) ?: [];
				$req_stamps = isset($ui['punch_count']) ? intval($ui['punch_count']) : 5;
				$bal = O100_Native_Punch_Card::get_stamp_balance( $user_id, $nc->id );
				$native_punch_cards[] = [
					'id'       => $nc->id,
					'name'     => $nc->name,
					'required' => $req_stamps,
					'balance'  => $bal,
					'ui'       => $ui,
					'campaign' => $nc
				];
			}
		}
		
		$options = get_option( 'o100_options', array() );
		$theme_color = ! empty( $options['o100_main_color'] ) ? $options['o100_main_color'] : '#e11d48';
		
		echo '<div class="o100-loyalty-dashboard-wrapper">';
		self::print_shared_css( $theme_color );
		?>
		<style>
			/* Piki-Cards Style Punch Cards */
			.o100-punch-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 20px; transition: box-shadow 0.2s; }
			.o100-punch-card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
			.o100-punch-header { padding: 24px; display: flex; align-items: flex-start; gap: 16px; border-bottom: 1px dashed #e2e8f0; }
			.o100-punch-icon { width: 56px; height: 56px; border-radius: 12px; background: #f8fafc; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; border: 1px solid #f1f5f9; }
			.o100-punch-icon img { max-width: 100%; height: auto; }
			.o100-punch-info { flex: 1; }
			.o100-punch-type { font-size: 13px; color: #64748b; margin: 0 0 4px 0; font-weight: 600; }
			.o100-punch-title { font-size: 18px; color: #0f172a; margin: 0 0 12px 0; font-weight: 700; }
			.o100-punch-meta { display: flex; justify-content: space-between; align-items: center; }
			.o100-punch-status { font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 6px; }
			.o100-punch-status span { color: #10b981; font-weight: 600; }
			.o100-punch-uses { background: #f8fafc; padding: 6px 12px; border-radius: 6px; font-size: 14px; font-weight: 700; color: #0f172a; }
			.o100-punch-uses.full { background: <?php echo esc_attr($theme_color); ?>15; color: <?php echo esc_attr($theme_color); ?>; }
			
			.o100-punch-stamps { padding: 24px; display: flex; flex-wrap: wrap; gap: 12px; }
			.o100-stamp { width: 36px; height: 36px; border-radius: 50%; border: 2px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 12px; font-weight: 700; }
			.o100-stamp.filled { border: 2px solid <?php echo esc_attr($theme_color); ?>; background: <?php echo esc_attr($theme_color); ?>; color: #fff; }
			.o100-stamp.reward { border: 2px solid <?php echo esc_attr($theme_color); ?>; background: <?php echo esc_attr($theme_color); ?>10; color: <?php echo esc_attr($theme_color); ?>; font-size: 11px; flex-direction: column; line-height: 1.2; position: relative; }
			
			.o100-punch-action { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #f1f5f9; text-align: right; }
			
			/* Coupons UI */
			.o100-coupons-heading { font-size: 18px; color: #0f172a; margin: 0 0 16px 0; font-weight: 700; }
			.o100-coupons-heading.used { color: #64748b; font-size: 15px; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px; margin-top: 0; }
			.o100-coupons-divider { display: block !important; width: 100% !important; margin: 64px 0 48px 0 !important; border: 0 !important; border-top: 2px dashed #cbd5e1 !important; }
			.o100-coupons-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
			.o100-coupon-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; }
			.o100-coupon-card.o100-coupon-active { border-color: <?php echo esc_attr($theme_color); ?>50; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
			.o100-coupon-card.o100-coupon-used { background: #f8fafc; opacity: 0.7; }
			.o100-coupon-header { padding: 20px; display: flex; gap: 16px; align-items: flex-start; border-bottom: 1px dashed #e2e8f0; }
			.o100-coupon-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; background: <?php echo esc_attr($theme_color); ?>10; flex-shrink: 0; }
			.o100-coupon-used .o100-coupon-icon { background: #e2e8f0; filter: grayscale(1); }
			.o100-coupon-info h4 { margin: 0 0 4px 0 !important; font-size: 16px !important; color: #0f172a !important; font-weight: 700 !important; }
			.o100-coupon-info p { margin: 0 !important; font-size: 13px !important; color: #64748b !important; line-height: 1.4 !important; }
			.o100-coupon-footer { padding: 16px 20px; background: #f8fafc; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
			.o100-coupon-code { flex: 1; background: #fff; border: 1px dashed #cbd5e1; padding: 8px 12px; border-radius: 6px; font-family: monospace; font-size: 14px; font-weight: 600; color: #0f172a; text-align: center; letter-spacing: 1px; }
			.o100-copy-btn { background: <?php echo esc_attr($theme_color); ?>; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
			.o100-copy-btn:hover { filter: brightness(1.1); }
			.o100-coupon-status-badge { font-size: 12px; font-weight: 700; color: #64748b; background: #e2e8f0; padding: 6px 12px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
		</style>
		<div class="o100-loyalty-tabs">
			<div class="o100-loyalty-tab active" onclick="o100SwitchTab(event, 'o100-tab-coupons')">My Coupons</div>
			<?php if ( ! empty($native_punch_cards) ) : ?>
				<div class="o100-loyalty-tab" onclick="o100SwitchTab(event, 'o100-tab-punch')">Punch Cards</div>
			<?php endif; ?>
			<div class="o100-loyalty-tab" onclick="o100SwitchTab(event, 'o100-tab-earn')">Ways to Earn</div>
		</div>
		
		<div id="o100-tab-coupons" class="o100-loyalty-tab-content active">
			<?php
			$user_email = wp_get_current_user()->user_email;
			$active_coupons = [];
			$used_coupons = [];
			
			if ( class_exists('O100_Promotions_DB') ) {
				global $wpdb;
				$promo_table = O100_Promotions_DB::table_name();
				$native_active = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$promo_table} WHERE source IN ('loyalty_punch', 'loyalty_conversion', 'loyalty_referral', 'loyalty_level') AND status = 'active' AND usage_count < usage_limit AND conditions LIKE %s ORDER BY id DESC LIMIT 50",
					'%' . $wpdb->esc_like( $user_email ) . '%'
				) );
				foreach ( $native_active as $promo ) {
					$obj = new stdClass();
					$obj->description = 'Redeem your reward at checkout.';
					$obj->discount_code = $promo->promo_code;
					$obj->is_native = true;
					
					$items = json_decode( $promo->apply_to_items, true );
					$obj->product_id = !empty($items) ? intval($items[0]) : 0;
					
					if ( $promo->type === 'free_item' && $obj->product_id ) {
						$p = wc_get_product($obj->product_id);
						if ( $p ) {
							$p_name = preg_replace('/^\d+\.\s*/', '', $p->get_name());
							$p_name = preg_replace('/\s*\(\#\d+\)$/', '', $p_name);
							$obj->name = 'Free ' . $p_name;
						} else {
							$obj->name = 'Free Reward';
						}
					} elseif ( $promo->type === 'fixed_cart' ) {
						$obj->name = wc_price($promo->amount) . ' Off';
					} else {
						$obj->name = 'Reward Coupon';
					}
					
					$active_coupons[] = $obj;
				}
				
				$native_used = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$promo_table} WHERE source IN ('loyalty_punch', 'loyalty_conversion', 'loyalty_referral', 'loyalty_level') AND (status != 'active' OR usage_count >= usage_limit) AND conditions LIKE %s ORDER BY id DESC LIMIT 50",
					'%' . $wpdb->esc_like( $user_email ) . '%'
				) );
				foreach ( $native_used as $promo ) {
					$obj = new stdClass();
					$obj->description = 'Redeem your reward at checkout.';
					$obj->discount_code = $promo->promo_code;
					$obj->status = $promo->status === 'active' && $promo->usage_count >= $promo->usage_limit ? 'used' : 'expired';
					
					$items = json_decode( $promo->apply_to_items, true );
					$product_id = !empty($items) ? intval($items[0]) : 0;
					
					if ( $promo->type === 'free_item' && $product_id ) {
						$p = wc_get_product($product_id);
						if ( $p ) {
							$p_name = preg_replace('/^\d+\.\s*/', '', $p->get_name());
							$p_name = preg_replace('/\s*\(\#\d+\)$/', '', $p_name);
							$obj->name = 'Free ' . $p_name;
						} else {
							$obj->name = 'Free Reward';
						}
					} elseif ( $promo->type === 'fixed_cart' ) {
						$obj->name = wc_price($promo->amount) . ' Off';
					} else {
						$obj->name = 'Reward Coupon';
					}
					
					$used_coupons[] = $obj;
				}
			}
			
			?>
			<div class="o100-coupons-container">
					<?php if ( empty($active_coupons) && empty($used_coupons) ) : ?>
						<div class="o100-empty-state">
							<svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 12V7a2 2 0 012-2h5a2 2 0 012 2v5m-8 0a2 2 0 00-2 2v1m10-3a2 2 0 012 2v1m-14 4h18m-18 0v7a2 2 0 002 2h14a2 2 0 002-2v-7m-18 0H4m18 0h2"></path></svg>
							<h3 style="font-size: 16px; margin: 16px 0 8px;">No coupons yet</h3>
							<p style="color: #64748b; margin: 0; font-size: 14px;">Earn points to unlock exclusive rewards.</p>
						</div>
					<?php else : ?>
					
						<?php if ( ! empty($active_coupons) ) : ?>
							<h3 class="o100-coupons-heading">Available Coupons</h3>
							<div class="o100-coupons-grid">
								<?php foreach ( $active_coupons as $coupon ) : ?>
									<div class="o100-coupon-card o100-coupon-active">
										<div class="o100-coupon-header">
											<div class="o100-coupon-icon">🎫</div>
											<div class="o100-coupon-info">
												<h4><?php echo esc_html( $coupon->name ); ?></h4>
												<p><?php echo esc_html( $coupon->description ); ?></p>
											</div>
										</div>
										<div class="o100-coupon-footer">
											<div class="o100-coupon-code">
												<span><?php echo esc_html( $coupon->discount_code ); ?></span>
											</div>
											<?php if ( isset($coupon->is_native) && $coupon->is_native && !empty($coupon->product_id) ) : ?>
												<button class="o100-copy-btn" onclick="o100ApplyReward(this, '<?php echo esc_js( $coupon->discount_code ); ?>', <?php echo intval( $coupon->product_id ); ?>)">Apply Reward</button>
											<?php else : ?>
												<button class="o100-copy-btn" onclick="o100CopyCode(this, '<?php echo esc_js( $coupon->discount_code ); ?>')">Copy Code</button>
											<?php endif; ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						
						<?php if ( ! empty($used_coupons) ) : ?>
							<?php if ( ! empty($active_coupons) ) : ?>
								<div class="o100-coupons-divider"></div>
							<?php endif; ?>
							<h3 class="o100-coupons-heading used">
								<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
								Used / Expired Coupons
							</h3>
							<div class="o100-coupons-grid">
								<?php foreach ( $used_coupons as $coupon ) : ?>
									<div class="o100-coupon-card o100-coupon-used">
										<div class="o100-coupon-header">
											<div class="o100-coupon-icon">🏁</div>
											<div class="o100-coupon-info">
												<h4><?php echo esc_html( $coupon->name ); ?></h4>
												<p>This coupon has been used or expired.</p>
											</div>
										</div>
										<div class="o100-coupon-footer">
											<div class="o100-coupon-code">
												<span><?php echo esc_html( $coupon->discount_code ); ?></span>
											</div>
											<div class="o100-coupon-status-badge">
												<?php echo esc_html( ucfirst( $coupon->status ) ); ?>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					
					<?php endif; ?>
				</div>
				<script>
				function o100ApplyReward(btn, code, productId) {
					var origText = btn.innerText;
					btn.innerText = "Applying...";
					btn.style.opacity = "0.7";
					btn.style.pointerEvents = "none";
					
					jQuery.post('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
						action: 'o100_apply_reward_to_cart',
						coupon_code: code,
						product_id: productId
					}, function(res) {
						if (res.success) {
							btn.innerText = "Applied!";
							btn.style.background = "#10b981";
							btn.style.color = "#fff";
							if (typeof o100ShowToast === "function") o100ShowToast("Reward applied! Redirecting...");
							setTimeout(function() {
								window.location.href = "<?php echo wc_get_checkout_url(); ?>";
							}, 1500);
						} else {
							btn.innerText = "Failed";
							if (typeof o100ShowToast === "function") o100ShowToast(res.data ? res.data.message : "Error", "error");
							setTimeout(function() {
								btn.innerText = origText;
								btn.style.opacity = "1";
								btn.style.pointerEvents = "auto";
							}, 2000);
						}
					});
				}

				function o100CopyCode(btn, code) {
					if(navigator.clipboard && window.isSecureContext) {
						navigator.clipboard.writeText(code).then(function() {
							var oldText = btn.innerText;
							btn.innerText = 'Copied!';
							btn.style.background = '#10b981';
							btn.style.color = '#fff';
							setTimeout(function() {
								btn.innerText = oldText;
								btn.style.background = '';
								btn.style.color = '';
							}, 2000);
						});
					} else {
						// Fallback for non-https local dev
						var textArea = document.createElement("textarea");
						textArea.value = code;
						textArea.style.position = "fixed";
						document.body.appendChild(textArea);
						textArea.focus();
						textArea.select();
						try {
							document.execCommand('copy');
							var oldText = btn.innerText;
							btn.innerText = 'Copied!';
							btn.style.background = '#10b981';
							btn.style.color = '#fff';
							setTimeout(function() {
								btn.innerText = oldText;
								btn.style.background = '';
								btn.style.color = '';
							}, 2000);
						} catch (err) {}
						document.body.removeChild(textArea);
					}
				}
				</script>
				</div>
				<script>
		
		<?php if ( ! empty($native_punch_cards) ) : ?>
		<div id="o100-tab-punch" class="o100-loyalty-tab-content">
			<div class="o100-punch-list">
				<?php foreach ( $native_punch_cards as $pc ) : ?>
					<?php
					$reward_name = 'Free Reward';
					$icon_html = '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 6 6 .5-4.5 4.5 1 6.5-5.5-3-5.5 3 1-6.5L3 8.5l6-.5z"></path></svg>';
					
					// Decode conditions to get participating products text
					$conditions = json_decode($pc['campaign']->conditions_json, true) ?: [];
					$product_names = [];
					foreach ($conditions as $cond) {
						if (isset($cond['type']) && $cond['type'] === 'products') {
							if (isset($cond['options']['value']) && is_array($cond['options']['value'])) {
								foreach ($cond['options']['value'] as $pid) {
									$p = wc_get_product($pid);
									if ($p) {
										$p_name = $p->get_name();
										$p_name = preg_replace('/^\d+\.\s*/', '', $p_name);
										$p_name = preg_replace('/\s*\(\#\d+\)$/', '', $p_name);
										$product_names[] = $p_name;
									}
								}
							}
						}
					}
					
					$ec_earn = json_decode($pc['campaign']->earn_config, true) ?: [];
					$free_products = $ec_earn['earn_reward_config']['free_products'] ?? [];
					if ( ! empty($free_products) ) {
						$rew_p = wc_get_product(intval($free_products[0]));
						if ( $rew_p ) {
							$reward_name = $rew_p->get_name();
							$reward_name = preg_replace('/^\d+\.\s*/', '', $reward_name);
							$reward_name = preg_replace('/\s*\(\#\d+\)$/', '', $reward_name);
						}
					}
					
					$is_full = $pc['balance'] >= $pc['required'];
					$ui_json = isset($pc['ui']) ? $pc['ui'] : [];
					$custom_icon = !empty($ui_json['stamp_icon_url']) ? $ui_json['stamp_icon_url'] : '';
					?>
					<div class="o100-punch-card">
						<div class="o100-punch-header">
							<div class="o100-punch-icon">
								<?php echo wp_kses_post($icon_html); ?>
							</div>
							<div class="o100-punch-info">
								<p class="o100-punch-type">Punch Card Reward</p>
								<h4 class="o100-punch-title"><?php echo esc_html($pc['name']); ?></h4>
								<div class="o100-punch-meta">
									<div class="o100-punch-status">
										<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"></path></svg>
										<?php if ( $is_full ): ?>
											<span>Reward unlocked! Click to claim.</span>
										<?php elseif ( $pc['balance'] == 0 ): ?>
											<span>Buy <?php echo $pc['required']; ?> more to get a free reward!</span>
										<?php else: ?>
											<span>Just <?php echo ($pc['required'] - $pc['balance']); ?> more to get your free reward!</span>
										<?php endif; ?>
									</div>
									<div class="o100-punch-uses <?php echo $is_full ? 'full' : ''; ?>">
										<?php echo esc_html($pc['balance'] . ' / ' . $pc['required']); ?>
									</div>
								</div>
							</div>
						</div>
						
						<div class="o100-punch-stamps">
							<?php for ( $i = 1; $i <= $pc['required']; $i++ ) : ?>
								<?php if ( $i <= $pc['balance'] ) : ?>
									<div class="o100-stamp filled" style="padding: <?php echo $custom_icon ? '0' : 'auto'; ?>">
										<?php if ( $custom_icon ) : ?>
											<img src="<?php echo esc_url($custom_icon); ?>" style="width:100%; height:100%; object-fit:contain; border-radius:50%;" alt="Stamp" />
										<?php else : ?>
											✓
										<?php endif; ?>
									</div>
								<?php else : ?>
									<div class="o100-stamp <?php echo ($i == $pc['required']) ? 'reward' : ''; ?>">
										<?php if ( $i == $pc['required'] ) : ?>
											<span>Free</span>
										<?php else: ?>
											<?php echo $i; ?>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							<?php endfor; ?>
						</div>
						
						<?php if ( $is_full ) : ?>
						<div class="o100-punch-action">
							<button class="o100-btn-redeem" style="width: auto;" onclick="o100RedeemPunchCard(<?php echo esc_attr($pc['id']); ?>, this)">Claim Free <?php echo esc_html($reward_name); ?></button>
						</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		
		<div id="o100-tab-earn" class="o100-loyalty-tab-content">
			<?php 
			$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';
			$earn_campaigns = $wpdb->get_results("SELECT * FROM {$campaigns_table} WHERE type != 'points_conversion' AND type != 'punch_card' AND status = 'active' ORDER BY id ASC");
			
			if ( ! empty($earn_campaigns) ) : ?>
				<div class="o100-reward-grid">
					<?php foreach ( $earn_campaigns as $campaign ) : ?>
						<div class="o100-reward-card" style="align-items: flex-start; text-align: left;">
							<h4 style="margin-bottom: 4px;"><?php echo esc_html($campaign->name); ?></h4>
							<p style="margin-bottom: 8px;"><?php echo esc_html(strip_tags($campaign->description)); ?></p>
							<span style="display:inline-block; padding: 4px 10px; background: <?php echo esc_attr($theme_color); ?>20; color: <?php echo esc_attr($theme_color); ?>; border-radius: 4px; font-size: 12px; font-weight: 600; margin-top: auto;">
								<?php 
								if ( $campaign->type === 'points_for_purchase' ) {
									echo 'Earn ' . esc_html($campaign->reward_value) . ' pts per $1';
								} elseif ( $campaign->type === 'signup' ) {
									echo 'Earn ' . esc_html($campaign->reward_value) . ' pts for joining';
								} elseif ( $campaign->type === 'product_review' ) {
									echo 'Earn ' . esc_html($campaign->reward_value) . ' pts per review';
								} elseif ( $campaign->type === 'birthday' ) {
									echo 'Earn ' . esc_html($campaign->reward_value) . ' pts on birthday';
								} else {
									echo '+' . esc_html($campaign->reward_value) . ' pts';
								}
								?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<p style="color: #64748b; text-align: center; padding: 40px 0; background: #f8fafc; border-radius: 8px;">More ways to earn are coming soon!</p>
			<?php endif; ?>
			
			<?php 
			$user_account = O100_Loyalty_DB::get_account($user_id);
			if ( $user_account && ! empty($user_account->refer_code) ) : 
				$referral_url = add_query_arg('ref', $user_account->refer_code, home_url());
			?>
				<div style="margin-top: 24px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 30px; text-align: center;">
					<h4 style="margin: 0 0 10px 0; font-size: 18px; color: #0f172a;">Share Your Referral Link</h4>
					<p style="color: #64748b; margin: 0 0 20px 0;">Give a discount to a friend, and get a reward when they order!</p>
					<input type="text" readonly value="<?php echo esc_url($referral_url); ?>" style="width: 100%; max-width: 400px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; text-align: center; color: #475569; margin-bottom: 15px; background: #fff;">
					<br>
					<button class="o100-btn-redeem" style="width: auto; padding: 10px 24px;" onclick="navigator.clipboard.writeText('<?php echo esc_js($referral_url); ?>'); alert('Copied to clipboard!');">Copy Link</button>
				</div>
			<?php endif; ?>
		</div>
		</div>
		<?php
	}

}

// Initialized by O100_Loyalty_Loader
