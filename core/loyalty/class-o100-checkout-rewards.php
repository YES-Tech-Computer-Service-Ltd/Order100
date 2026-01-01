<?php
/**
 * O100 Checkout Rewards Integration
 * 
 * Injects custom Points and Punch Card redemption UI into the WooCommerce Checkout page,
 * replacing the default WPLoyalty implementation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Checkout_Rewards {

	public static function init() {
		// Hook into checkout page
		add_action( 'woocommerce_review_order_before_payment', [ __CLASS__, 'render_checkout_rewards' ], 10 );
		
		// Handle AJAX redemption from checkout
		add_action( 'wp_ajax_o100_checkout_redeem_points', [ __CLASS__, 'ajax_redeem_points' ] );
		add_action( 'wp_ajax_nopriv_o100_checkout_redeem_points', [ __CLASS__, 'ajax_redeem_points' ] );
		
		// Handle remove WPLoyalty native checkout hooks
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'remove_wployalty_checkout_ui' ], 99 );
	}

	public static function remove_wployalty_checkout_ui() {
		if ( ! is_checkout() ) return;
		
		// Add CSS to hide WPLoyalty native checkout blocks in case hooks fail
		$css = "
			.wlr-checkout-redemption-container,
			#wlr-checkout-redemption,
			.wlr-checkout-reward-list-container {
				display: none !important;
			}
		";
		wp_add_inline_style( 'woocommerce-inline', $css );
	}

	public static function render_checkout_rewards() {
		if ( ! is_user_logged_in() ) return;
		$user_email = wp_get_current_user()->user_email;
		$user_id = get_current_user_id();
		
		// Removed WPLoyalty CustomerPage check
		// Fetch Full Punch Cards
		$full_punch_cards = [];
		if ( class_exists( 'O100_Native_Punch_Card' ) ) {
			$native_campaigns = O100_Native_Punch_Card::get_active_punch_cards();
			foreach ( $native_campaigns as $nc ) {
				$ui = is_string($nc->ui_json) ? json_decode($nc->ui_json, true) : (array)$nc->ui_json;
				$req_stamps = isset($ui['punch_count']) ? intval($ui['punch_count']) : 5;
				$bal = O100_Native_Punch_Card::get_stamp_balance( $user_id, $nc->id );
				
				if ( $bal >= $req_stamps ) {
					$reward_name = 'Free Reward';
					$icon_html = '';
					
					global $wpdb;
					$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';
					$ec = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$campaigns_table} WHERE id = %d", $nc->id ) );
					if ( $ec ) {
						// Extract icon from ui_json if needed, or fallback
						$icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#f3e8ff; color:#9333ea;">🎫</div>';
						$ec_earn = json_decode( $ec->earn_config, true ) ?: [];
						$free_products = $ec_earn['earn_reward_config']['free_products'] ?? [];
						if ( !empty($free_products) ) {
							$r_prod = wc_get_product( intval($free_products[0]) );
							if ( $r_prod ) {
								$reward_name = $r_prod->get_name();
							}
						}
					}
					
					$full_punch_cards[] = [
						'id' => $nc->id,
						'name' => $nc->name,
						'reward_name' => $reward_name,
						'icon' => $icon_html
					];
				}
			}
		}

		// Check if we have anything to show
		if ( empty($full_punch_cards) ) return;
		$options = get_option( 'o100_options', array() );
		$theme_color = ! empty( $options['o100_main_color'] ) ? $options['o100_main_color'] : '#e11d48';
		$currency_sym = get_woocommerce_currency_symbol();
		
		?>
		<style>
			.o100-checkout-rewards { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 24px; overflow: hidden; font-family: 'Inter', sans-serif; }
			.o100-checkout-rewards-header { background: #f8fafc; padding: 16px 20px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; font-size: 16px; }
			.o100-checkout-rewards-body { padding: 20px; }
			
			.o100-checkout-point-box { background: #f1f5f9; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
			.o100-checkout-point-box h4 { margin: 0 0 8px 0; font-size: 15px; color: #0f172a; }
			.o100-checkout-point-box p { margin: 0 0 12px 0; font-size: 13px; color: #64748b; }
			.o100-checkout-point-input-row { display: flex; gap: 12px; align-items: center; }
			.o100-checkout-point-input-row input { flex: 1; border: 1px solid #cbd5e1; padding: 10px 12px; border-radius: 6px; font-size: 14px; }
			.o100-checkout-btn { background: <?php echo esc_attr($theme_color); ?>; color: #fff; border: none; padding: 10px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: opacity 0.2s; white-space: nowrap; }
			.o100-checkout-btn:hover { opacity: 0.9; }
			
			.o100-checkout-punch-card { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; margin-bottom: 12px; }
			.o100-checkout-punch-icon { width: 40px; height: 40px; border-radius: 8px; background: #f8fafc; display: flex; align-items: center; justify-content: center; }
			.o100-checkout-punch-info { flex: 1; }
			.o100-checkout-punch-info h5 { margin: 0 0 2px 0; font-size: 14px; color: #0f172a; }
			.o100-checkout-punch-info span { font-size: 12px; color: #10b981; font-weight: 600; }
		</style>
		
		<div class="o100-checkout-rewards">
			<div class="o100-checkout-rewards-header">
				<span style="color: <?php echo esc_attr($theme_color); ?>;">✦</span> Available Rewards
			</div>
			<div class="o100-checkout-rewards-body">

				<?php if ( ! empty($full_punch_cards) ) : ?>
					<div style="margin-top: 16px;">
						<h4 style="margin: 0 0 12px 0; font-size: 15px; color: #0f172a;">Full Punch Cards</h4>
						<?php foreach ( $full_punch_cards as $pc ) : ?>
							<div class="o100-checkout-punch-card">
								<div class="o100-checkout-punch-icon">
									<?php echo !empty($pc['icon']) ? wp_kses_post($pc['icon']) : '🎁'; ?>
								</div>
								<div class="o100-checkout-punch-info">
									<h5><?php echo esc_html($pc['name']); ?></h5>
									<span>Free <?php echo esc_html($pc['reward_name']); ?> Available!</span>
								</div>
								<button type="button" class="o100-checkout-btn" style="background: #10b981;" onclick="o100CheckoutRedeemPunch(<?php echo esc_attr($pc['id']); ?>)">Use Now</button>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				
		</div>
		</div>
		
		<script type="text/javascript">
		function o100CheckoutRedeemPunch(campaignId) {
			if(confirm('Use your punch card for a free item on this order?')) {
				const data = new FormData();
				data.append('action', 'o100_redeem_punch_card');
				data.append('campaign_id', campaignId);
				fetch( '<?php echo admin_url('admin-ajax.php'); ?>', {
					method: 'POST', body: data
				}).then(res => res.json()).then(res => {
					if(res.success) {
						jQuery('body').trigger('update_checkout');
						alert(res.data.message);
					} else {
						alert('Error: ' + res.data.message);
					}
				}).catch(err => {
					alert('A network error occurred.');
				});
			}
		}
		</script>
		<?php
	}

}
// Initialized by O100_Loyalty_Loader
