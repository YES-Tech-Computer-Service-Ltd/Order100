<?php
/**
 * Order100 Loyalty My Account Integration
 * 
 * Adds "My Points" and "My Rewards" tabs to the WooCommerce My Account page,
 * restoring the rich UI and AJAX redeem capabilities natively.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Loyalty_MyAccount {

	public static function init() {
		// Add endpoints
		add_action( 'init', [ __CLASS__, 'add_endpoints' ] );
		
		// Add menu items
		add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'add_menu_items' ], 10, 1 );
		
		// Render endpoint contents
		add_action( 'woocommerce_account_o100-points_endpoint', [ __CLASS__, 'render_points_page' ] );
		add_action( 'woocommerce_account_o100-rewards_endpoint', [ __CLASS__, 'render_rewards_page' ] );
	}

	public static function add_endpoints() {
		add_rewrite_endpoint( 'o100-points', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'o100-rewards', EP_ROOT | EP_PAGES );
	}

	public static function add_menu_items( $items ) {
		$new_items = array();
		foreach ( $items as $key => $value ) {
			$new_items[$key] = $value;
			if ( 'orders' === $key ) {
				$new_items['o100-points'] = esc_html__( 'My Points', 'order100' );
				$new_items['o100-rewards'] = esc_html__( 'My Rewards', 'order100' );
			}
		}
		return $new_items;
	}

	public static function get_theme_color() {
		$options = get_option( 'o100_options', array() );
		return ! empty( $options['o100_main_color'] ) ? $options['o100_main_color'] : '#e11d48';
	}

	public static function print_shared_css() {
		$theme_color = self::get_theme_color();
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
			
			/* Native Table */
			.o100-transaction-block { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
			.o100-transaction-block .wlr-heading { font-size: 18px; color: #0f172a; margin-bottom: 16px; margin-top: 0; }
			.o100-table { width: 100%; border-collapse: collapse; }
			.o100-table th, .o100-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
			.o100-table th { font-weight: 600; color: #475569; background: #f8fafc; text-transform: uppercase; font-size: 12px; }
			.o100-table td { color: #0f172a; font-size: 14px; }
			
			/* Milestone UI */
			.o100-milestone-wrap { text-align: center; margin: 32px 0; background: #f8fafc; padding: 24px; border-radius: 12px; border: 1px dashed #cbd5e1; }
			.o100-milestone-wrap p { margin: 0; color: #475569; font-size: 16px; font-weight: 500; }
			.o100-milestone-wrap .o100-milestone-hl { color: <?php echo esc_attr($theme_color); ?>; font-weight: 700; font-size: 18px; }
			
			/* Punch Cards */
			.o100-punch-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 20px; transition: box-shadow 0.2s; }
			.o100-punch-card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
			.o100-punch-header { padding: 24px; display: flex; align-items: flex-start; gap: 16px; border-bottom: 1px dashed #e2e8f0; }
			.o100-punch-icon { width: 56px; height: 56px; border-radius: 12px; background: #f8fafc; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; border: 1px solid #f1f5f9; }
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
			.o100-stamp.reward { border: 2px solid <?php echo esc_attr($theme_color); ?>; background: <?php echo esc_attr($theme_color); ?>10; color: <?php echo esc_attr($theme_color); ?>; font-size: 11px; flex-direction: column; line-height: 1.2; }
			.o100-punch-action { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #f1f5f9; text-align: right; }
			
			/* Coupons */
			.o100-coupons-heading { font-size: 18px; color: #0f172a; margin: 0 0 16px 0; font-weight: 700; }
			.o100-coupons-heading.used { color: #64748b; font-size: 15px; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px; margin-top: 0; }
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
			.o100-coupon-code { flex: 1; background: #fff; border: 1px dashed #cbd5e1; padding: 8px 12px; border-radius: 6px; font-family: monospace; font-size: 14px; font-weight: 600; color: #0f172a; text-align: center; }
			.o100-copy-btn { background: <?php echo esc_attr($theme_color); ?>; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
			.o100-coupon-status-badge { font-size: 12px; font-weight: 700; color: #64748b; background: #e2e8f0; padding: 6px 12px; border-radius: 20px; text-transform: uppercase; }
			
			@keyframes o100FadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
		</style>
		<script>
		function o100SwitchTab(e, targetId) {
			document.querySelectorAll('.o100-loyalty-tab').forEach(t => t.classList.remove('active'));
			document.querySelectorAll('.o100-loyalty-tab-content').forEach(c => c.classList.remove('active'));
			e.currentTarget.classList.add('active');
			document.getElementById(targetId).classList.add('active');
		}
		
		function o100ApplyReward(btn, code) {
			var origText = btn.innerText;
			btn.innerText = "Applying...";
			btn.style.opacity = "0.7";
			btn.style.pointerEvents = "none";
			
			jQuery.ajax({
				url: '/wp-json/o100/v1/loyalty/frontend/apply_reward',
				method: 'POST',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce("wp_rest"); ?>');
				},
				data: { coupon_code: code },
				success: function(res) {
					btn.innerText = "Applied!";
					btn.style.background = "#10b981";
					setTimeout(function() {
						window.location.href = "<?php echo wc_get_checkout_url(); ?>";
					}, 1500);
				},
				error: function(err) {
					btn.innerText = "Failed";
					var msg = err.responseJSON && err.responseJSON.message ? err.responseJSON.message : "Error applying reward";
					alert(msg);
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
					setTimeout(function() {
						btn.innerText = oldText;
						btn.style.background = '';
					}, 2000);
				});
			} else {
				var textArea = document.createElement("textarea");
				textArea.value = code;
				textArea.style.position = "fixed";
				document.body.appendChild(textArea);
				textArea.focus();
				textArea.select();
				try { document.execCommand('copy'); btn.innerText = 'Copied!'; btn.style.background = '#10b981'; } catch (err) {}
				document.body.removeChild(textArea);
				setTimeout(function() { btn.innerText = 'Copy Code'; btn.style.background = ''; }, 2000);
			}
		}

		function o100RedeemPunchCard(campaignId, btn) {
			if(confirm('Ready to claim your free reward?')) {
				var oldText = btn.innerHTML;
				btn.innerHTML = 'Claiming...';
				btn.disabled = true;
				
				jQuery.ajax({
					url: '/wp-json/o100/v1/loyalty/frontend/redeem_punch_card',
					method: 'POST',
					beforeSend: function (xhr) {
						xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce("wp_rest"); ?>');
					},
					data: { campaign_id: campaignId },
					success: function(res) {
						alert('Reward claimed successfully! You can find your coupon in the My Coupons tab.');
						location.reload();
					},
					error: function(err) {
						var msg = err.responseJSON && err.responseJSON.message ? err.responseJSON.message : "Failed to claim reward.";
						alert(msg);
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
		if ( ! is_account_page() || ! class_exists('O100_Loyalty_Engine') ) return;
		
		$user_data = O100_Loyalty_Engine::instance()->get_current_user_data();
		if ( ! $user_data['is_member'] ) {
			echo '<p>' . esc_html__( 'You are not enrolled in the loyalty program.', 'order100' ) . '</p>';
			return;
		}

		echo '<div class="o100-loyalty-dashboard-wrapper">';
		self::print_shared_css();
		?>
		<div class="o100-loyalty-header">
			<div>
				<h3><?php esc_html_e('Your Points Dashboard', 'order100'); ?></h3>
				<p><?php esc_html_e('Level:', 'order100'); ?> <?php echo esc_html($user_data['level_name'] ?: 'Member'); ?></p>
			</div>
			<div class="o100-loyalty-balance">
				<span class="val"><?php echo esc_html($user_data['points_balance']); ?></span>
				<span class="lbl"><?php esc_html_e('Points', 'order100'); ?></span>
			</div>
		</div>

		<?php
		global $wpdb;
		$user_id = get_current_user_id();
		$account = O100_Loyalty_DB::get_account_by_user( $user_id );
		if ( $account ) {
			$settings = O100_Loyalty_DB::get_settings();
			$reminder_val = isset( $settings['points_expiry_reminder_value'] ) ? intval( $settings['points_expiry_reminder_value'] ) : 30;
			$reminder_unit = isset( $settings['points_expiry_reminder_unit'] ) ? $settings['points_expiry_reminder_unit'] : 'days';
			$threshold_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$reminder_val} {$reminder_unit}" ) );
			
			$expiring_soon = $wpdb->get_results( $wpdb->prepare(
				"SELECT SUM(points_remaining) as total_expiring, DATE(expires_at) as expiry_date
				FROM %i 
				WHERE account_id = %d AND type IN ('earn','adjust') 
				  AND points_remaining > 0 
				  AND expires_at IS NOT NULL 
				  AND expires_at > %s 
				  AND expires_at <= %s
				GROUP BY expiry_date 
				ORDER BY expiry_date ASC 
				LIMIT 3",
				O100_Loyalty_DB::table_transactions(), $account->id, current_time('mysql', 1), $threshold_date
			) );

			if ( ! empty( $expiring_soon ) ) {
				echo '<div style="background: #fef3c7; color: #92400e; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; border-left: 4px solid #f59e0b; font-size: 14px;">';
				echo '<strong>' . esc_html__('⚠️ Points Expiring Soon:', 'order100') . '</strong><ul style="margin: 8px 0 0 20px; padding: 0;">';
				foreach ( $expiring_soon as $exp ) {
					echo '<li>' . sprintf( esc_html__('%d points on %s', 'order100'), $exp->total_expiring, $exp->expiry_date ) . '</li>';
				}
				echo '</ul></div>';
			}
		}
		
		$campaigns_table = O100_Loyalty_DB::table_campaigns();
		$points_conversion_rule = $wpdb->get_row( "SELECT * FROM {$campaigns_table} WHERE type = 'points_conversion' AND status = 'active' LIMIT 1" );
		
		if ( $points_conversion_rule ) {
			$ui_json = json_decode($points_conversion_rule->ui_json, true) ?: [];
			$require_pt = floatval($ui_json['conversion_points'] ?? 100);
			$disc_val = floatval($ui_json['conversion_value'] ?? 1);
			$reward_type = $ui_json['conversion_reward_type'] ?? 'discount';
			$min_pt = intval($ui_json['conv_min_points'] ?? 0);
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
			<?php
		}

		// Redeem Rewards Grid
		$redeem_campaigns = $wpdb->get_results( "SELECT * FROM {$campaigns_table} WHERE type = 'redeem' AND status = 'active' ORDER BY ordering ASC, id DESC" );
		
		if ( ! empty($redeem_campaigns) ) {
			echo '<div style="margin-bottom: 32px;">';
			echo '<h3 class="wlr-heading" style="font-size: 18px; color: #0f172a; margin-bottom: 16px;">' . esc_html__('Redeem Your Points', 'order100') . '</h3>';
			echo '<div class="o100-reward-grid">';
			foreach ( $redeem_campaigns as $camp ) {
				$rules = json_decode($camp->ui_json, true) ?: [];
				$cost = intval($rules['cost_points'] ?? 0);
				
				$can_redeem = $user_data['points_balance'] >= $cost;
				
				echo '<div class="o100-reward-card">';
				echo '<div>';
				echo '<div style="font-size:32px; margin-bottom:12px;">🎁</div>';
				echo '<h4>' . esc_html($camp->name) . '</h4>';
				echo '<p>' . esc_html(strip_tags($camp->description)) . '</p>';
				echo '</div>';
				
				echo '<div style="width:100%; margin-top:16px;">';
				echo '<div style="font-weight:700; color:' . esc_attr(self::get_theme_color()) . '; margin-bottom:12px;">' . esc_html($cost) . ' Points</div>';
				if ( $can_redeem ) {
					echo '<button class="o100-btn-redeem" onclick="o100RedeemPoints(' . intval($camp->id) . ', ' . $cost . ', this)">' . esc_html__('Redeem', 'order100') . '</button>';
				} else {
					echo '<button class="o100-btn-redeem" disabled>' . sprintf( esc_html__('Need %d more', 'order100'), $cost - $user_data['points_balance'] ) . '</button>';
				}
				echo '</div>';
				echo '</div>';
			}
			echo '</div></div>';
			?>
			<script>
			function o100RedeemPoints(campaignId, cost, btn) {
				if(confirm('Are you sure you want to spend ' + cost + ' points to redeem this reward?')) {
					var oldText = btn.innerHTML;
					btn.innerHTML = 'Redeeming...';
					btn.disabled = true;
					
					jQuery.ajax({
						url: '/wp-json/o100/v1/loyalty/frontend/redeem_points',
						method: 'POST',
						beforeSend: function (xhr) {
							xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce("wp_rest"); ?>');
						},
						data: { campaign_id: campaignId },
						success: function(res) {
							alert('Success! Your reward coupon has been generated. You can find it in the "My Rewards" -> "My Coupons" tab.');
							location.reload();
						},
						error: function(err) {
							var msg = err.responseJSON && err.responseJSON.message ? err.responseJSON.message : "Failed to redeem points.";
							alert(msg);
							btn.innerHTML = oldText;
							btn.disabled = false;
						}
					});
				}
			}
			</script>
			<?php
		}

		// Points History
		echo '<div class="o100-transaction-block">';
		echo '<h3 class="wlr-heading">' . esc_html__('Points History', 'order100') . '</h3>';
		
		$tbl_ledger = O100_Loyalty_DB::table_transactions();
		$history = $wpdb->get_results( $wpdb->prepare( "
			SELECT * FROM {$tbl_ledger} 
			WHERE account_id = %d 
			ORDER BY created_at DESC 
			LIMIT 50
		", $user_data['account_id'] ) );

		if ( empty( $history ) ) {
			echo '<p style="color: #64748b; font-size: 14px; text-align: center; padding: 20px 0;">No history found.</p>';
		} else {
			echo '<table class="o100-table">';
			echo '<thead><tr>';
			echo '<th>Date</th>';
			echo '<th>Activity</th>';
			echo '<th>Points</th>';
			echo '</tr></thead><tbody>';
			foreach ( $history as $row ) {
				$is_earn = ($row->type === 'earn');
				$color = $is_earn ? '#10b981' : '#e11d48';
				$sign  = $is_earn ? '+' : '-';
				echo '<tr>';
				echo '<td>' . esc_html( date_i18n( get_option('date_format'), strtotime($row->created_at) ) ) . '</td>';
				echo '<td>' . esc_html( $row->note ?: ucfirst(str_replace('_', ' ', $row->type)) ) . '</td>';
				echo '<td style="color:' . $color . '; font-weight:600;">' . $sign . (int)$row->points . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div></div>';
	}

	public static function render_rewards_page() {
		if ( ! is_account_page() || ! class_exists('O100_Loyalty_Engine') ) return;
		
		$user_id = get_current_user_id();
		$user_data = O100_Loyalty_Engine::instance()->get_current_user_data();
		if ( ! $user_data['is_member'] ) return;

		global $wpdb;
		$campaigns_table = O100_Loyalty_DB::table_campaigns();

		// Fetch Punch Cards
		$punch_cards = $wpdb->get_results("SELECT * FROM {$campaigns_table} WHERE type = 'punch_card' AND status = 'active'");
		$formatted_punch = [];
		foreach ( $punch_cards as $pc ) {
			$rules = json_decode($pc->ui_json ?? '', true) ?: [];
			$req_stamps = isset($rules['punch_count']) ? intval($rules['punch_count']) : 5;
			$prog = O100_Loyalty_DB::get_punch_progress( $user_data['account_id'], $pc->id );
			$bal = $prog ? intval($prog->stamps) : 0;
			$formatted_punch[] = [
				'id' => $pc->id,
				'name' => $pc->name,
				'required' => $req_stamps,
				'balance' => $bal,
				'campaign' => $pc
			];
		}

		echo '<div class="o100-loyalty-dashboard-wrapper">';
		self::print_shared_css();
		?>
		<div class="o100-loyalty-tabs">
			<div class="o100-loyalty-tab active" onclick="o100SwitchTab(event, 'o100-tab-coupons')">My Coupons</div>
			<?php if ( ! empty($formatted_punch) ) : ?>
				<div class="o100-loyalty-tab" onclick="o100SwitchTab(event, 'o100-tab-punch')">Punch Cards</div>
			<?php endif; ?>
			<div class="o100-loyalty-tab" onclick="o100SwitchTab(event, 'o100-tab-earn')">Ways to Earn</div>
		</div>

		<!-- COUPONS TAB -->
		<div id="o100-tab-coupons" class="o100-loyalty-tab-content active">
			<?php
			// Query WC native coupons associated with this user
			$args = [
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_query'     => [
					[
						'key'   => '_o100_reward_user_id',
						'value' => $user_id,
					]
				]
			];
			$query = new WP_Query($args);
			
			$active_coupons = [];
			$used_coupons = [];

			if ( $query->have_posts() ) {
				foreach ( $query->posts as $post ) {
					$coupon = new WC_Coupon($post->ID);
					$usage_count = $coupon->get_usage_count();
					$usage_limit = $coupon->get_usage_limit();
					$is_expired = false;
					if ( $coupon->get_date_expires() && time() > $coupon->get_date_expires()->getTimestamp() ) {
						$is_expired = true;
					}

					$obj = new stdClass();
					$obj->code = $coupon->get_code();
					$obj->desc = 'Redeem your reward at checkout.';
					
					$amt = $coupon->get_amount();
					$type = $coupon->get_discount_type();
					if ( $type === 'fixed_cart' ) {
						$obj->name = wc_price($amt) . ' Off';
					} elseif ( $type === 'percent' ) {
						$obj->name = floatval($amt) . '% Off';
					} else {
						$obj->name = 'Free Reward';
					}

					if ( ($usage_limit > 0 && $usage_count >= $usage_limit) || $is_expired ) {
						$obj->status = $is_expired ? 'expired' : 'used';
						$used_coupons[] = $obj;
					} else {
						$active_coupons[] = $obj;
					}
				}
			}
			wp_reset_postdata();
			?>
			
			<?php if ( empty($active_coupons) && empty($used_coupons) ) : ?>
				<div style="text-align:center; padding: 40px; background: #f8fafc; border-radius: 12px; color: #64748b;">
					<div style="font-size:32px; margin-bottom:10px;">🎫</div>
					<h3 style="margin:0 0 8px 0; color:#0f172a;">No coupons yet</h3>
					<p style="margin:0;">Earn points to unlock exclusive rewards.</p>
				</div>
			<?php else : ?>
				
				<?php if ( ! empty($active_coupons) ) : ?>
					<h3 class="o100-coupons-heading">Available Coupons</h3>
					<div class="o100-coupons-grid">
						<?php foreach ( $active_coupons as $c ) : ?>
							<div class="o100-coupon-card o100-coupon-active">
								<div class="o100-coupon-header">
									<div class="o100-coupon-icon">🎫</div>
									<div class="o100-coupon-info">
										<h4><?php echo esc_html($c->name); ?></h4>
										<p><?php echo esc_html($c->desc); ?></p>
									</div>
								</div>
								<div class="o100-coupon-footer">
									<div class="o100-coupon-code"><?php echo esc_html($c->code); ?></div>
									<button class="o100-copy-btn" onclick="o100ApplyReward(this, '<?php echo esc_js($c->code); ?>')">Apply Reward</button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty($used_coupons) ) : ?>
					<h3 class="o100-coupons-heading used" style="margin-top:40px;">Used / Expired Coupons</h3>
					<div class="o100-coupons-grid">
						<?php foreach ( $used_coupons as $c ) : ?>
							<div class="o100-coupon-card o100-coupon-used">
								<div class="o100-coupon-header">
									<div class="o100-coupon-icon">🏁</div>
									<div class="o100-coupon-info">
										<h4><?php echo esc_html($c->name); ?></h4>
										<p>This coupon has been used or expired.</p>
									</div>
								</div>
								<div class="o100-coupon-footer">
									<div class="o100-coupon-code"><?php echo esc_html($c->code); ?></div>
									<div class="o100-coupon-status-badge"><?php echo esc_html(ucfirst($c->status)); ?></div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

			<?php endif; ?>
		</div>

		<!-- PUNCH CARDS TAB -->
		<?php if ( ! empty($formatted_punch) ) : ?>
		<div id="o100-tab-punch" class="o100-loyalty-tab-content">
			<div class="o100-punch-list">
				<?php foreach ( $formatted_punch as $pc ) : 
					$is_full = $pc['balance'] >= $pc['required'];
				?>
					<div class="o100-punch-card">
						<div class="o100-punch-header">
							<div class="o100-punch-icon">
								<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 6 6 .5-4.5 4.5 1 6.5-5.5-3-5.5 3 1-6.5L3 8.5l6-.5z"></path></svg>
							</div>
							<div class="o100-punch-info">
								<p class="o100-punch-type">Punch Card Reward</p>
								<h4 class="o100-punch-title"><?php echo esc_html($pc['name']); ?></h4>
								<div class="o100-punch-meta">
									<div class="o100-punch-status">
										<?php if ( $is_full ): ?>
											<span style="color:#10b981;">Reward unlocked! Click to claim.</span>
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
									<div class="o100-stamp filled">✓</div>
								<?php else : ?>
									<div class="o100-stamp <?php echo ($i == $pc['required']) ? 'reward' : ''; ?>">
										<?php echo ($i == $pc['required']) ? '<span>Free</span>' : $i; ?>
									</div>
								<?php endif; ?>
							<?php endfor; ?>
						</div>
						
						<?php if ( $is_full ) : ?>
						<div class="o100-punch-action">
							<button class="o100-btn-redeem" style="width: auto;" onclick="o100RedeemPunchCard(<?php echo esc_attr($pc['id']); ?>, this)">Claim Reward</button>
						</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- WAYS TO EARN TAB -->
		<div id="o100-tab-earn" class="o100-loyalty-tab-content">
			<?php 
			$earn_campaigns = $wpdb->get_results("SELECT * FROM {$campaigns_table} WHERE type NOT IN ('points_conversion', 'punch_card', 'redeem') AND status = 'active' ORDER BY id ASC");
			
			if ( ! empty($earn_campaigns) ) : ?>
				<div class="o100-reward-grid">
					<?php foreach ( $earn_campaigns as $camp ) : 
						$rules = json_decode($camp->reward_config ?? '', true) ?: [];
						$reward_pts = isset($rules['reward_points']) ? $rules['reward_points'] : 0;
					?>
						<div class="o100-reward-card" style="align-items: flex-start; text-align: left;">
							<h4 style="margin-bottom: 4px;"><?php echo esc_html($camp->name); ?></h4>
							<p style="margin-bottom: 8px;"><?php echo esc_html(strip_tags($camp->description)); ?></p>
							<span style="display:inline-block; padding: 4px 10px; background: <?php echo esc_attr(self::get_theme_color()); ?>20; color: <?php echo esc_attr(self::get_theme_color()); ?>; border-radius: 4px; font-size: 12px; font-weight: 600; margin-top: auto;">
								Earn <?php echo esc_html($reward_pts); ?> pts
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<p style="color: #64748b; text-align: center; padding: 40px 0; background: #f8fafc; border-radius: 8px;">More ways to earn are coming soon!</p>
			<?php endif; ?>
			
			<?php if ( ! empty($user_data['refer_code']) ) : 
				$referral_url = add_query_arg('ref', $user_data['refer_code'], home_url());
			?>
				<div style="margin-top: 24px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 30px; text-align: center;">
					<h4 style="margin: 0 0 10px 0; font-size: 18px; color: #0f172a;">Share Your Referral Link</h4>
					<p style="color: #64748b; margin: 0 0 20px 0;">Give a discount to a friend, and get a reward when they order!</p>
					<input type="text" readonly value="<?php echo esc_url($referral_url); ?>" style="width: 100%; max-width: 400px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; text-align: center; color: #475569; margin-bottom: 15px; background: #fff;">
					<br>
					<button class="o100-btn-redeem" style="width: auto; padding: 10px 24px;" onclick="o100CopyCode(this, '<?php echo esc_js($referral_url); ?>')">Copy Link</button>
				</div>
			<?php endif; ?>
		</div>

		</div>
		<?php
	}
}
