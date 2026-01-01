<?php
/**
 * O100 Loyalty Checkout Proxy
 * Handles DoorDash style point redemption directly on the WooCommerce checkout page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Loyalty_Proxy_Checkout {

	public static function init() {
		// Development point restore logic removed.

		add_action( 'woocommerce_review_order_before_payment', [ __CLASS__, 'render_checkout_loyalty_ui' ], 10 );
		add_action( 'wp_footer', [ __CLASS__, 'inject_checkout_scripts' ] );

		// Disable WPLoyalty's native Redeem UI at checkout and cart to prevent duplicate/empty boxes
		add_filter( 'wlr_is_checkout_redeem_message_enabled', '__return_false', 99 );
		add_filter( 'wlr_is_cart_redeem_message_enabled', '__return_false', 99 );

		// Suppress WPLoyalty's native success notices on the checkout page
		add_action( 'woocommerce_before_checkout_process', [ __CLASS__, 'suppress_wlr_native_notice' ], 1 );
		add_action( 'woocommerce_checkout_update_order_review', [ __CLASS__, 'suppress_wlr_native_notice' ], 1 );
	}

	// (Legacy WPLoyalty coupon validation override removed)

	public static function suppress_wlr_native_notice() {
		if ( function_exists( 'WC' ) && isset( WC()->session ) ) {
			$notices = WC()->session->get( 'wc_notices', array() );
			$updated = false;
			if ( isset( $notices['success'] ) && is_array( $notices['success'] ) ) {
				foreach ( $notices['success'] as $key => $notice ) {
					if ( isset( $notice['notice'] ) && strpos( $notice['notice'], 'Coupon applied successfully' ) !== false ) {
						unset( $notices['success'][$key] );
						$updated = true;
					}
				}
			}
			if ( $updated ) {
				WC()->session->set( 'wc_notices', $notices );
			}
		}
	}

	public static function add_loyalty_checkout_fragment( $fragments ) {
		ob_start();
		self::render_checkout_loyalty_ui();
		$fragments['.o100-checkout-loyalty-wrapper'] = ob_get_clean();
		return $fragments;
	}

	public static function inject_checkout_scripts() {
		if ( ! is_checkout() || ! is_user_logged_in() ) {
			return;
		}
		
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($){
			// When the apply button is clicked
			$(document).on('click', '.o100-apply-checkout-pts-btn', function(e) {
				e.preventDefault();
				var btn = $(this);
				var pts = btn.data('points');
				var reward_id = btn.data('reward-id');
				
				btn.text('Applying...');
				btn.css('opacity', '0.5');
				btn.css('pointer-events', 'none');
				
				var data = {
					action: 'o100_apply_reward_points',
					reward_id: reward_id,
					points: pts,
					security: '<?php echo wp_create_nonce("o100-loyalty-nonce"); ?>'
				};
				
				$.ajax({
					type: 'POST',
					url: '<?php echo admin_url("admin-ajax.php"); ?>',
					data: data,
					dataType: 'json',
					success: function(res) {
						if(res.success) {
							// Trigger woo checkout update
							$('body').trigger('update_checkout', { update_shipping_method: true });
						} else {
							alert('Error: ' + (res.data && res.data.message ? res.data.message : 'Could not apply reward.'));
							btn.text('Apply Reward');
							btn.css('opacity', '1');
							btn.css('pointer-events', 'auto');
						}
					},
					error: function() {
						alert('Network error.');
						btn.text('Apply Reward');
						btn.css('opacity', '1');
						btn.css('pointer-events', 'auto');
					}
				});
			});
		});
		</script>
		<style>
			/* Applied State */
			.o100-checkout-reward-box { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
			.o100-checkout-reward-info h4 { margin: 0 0 4px 0 !important; color: #0f172a !important; font-size: 16px !important; font-weight: 700 !important; display: flex !important; align-items: center !important; gap: 8px !important; border:none !important; padding:0 !important; }
			.o100-checkout-reward-info p { margin: 0 !important; color: #64748b !important; font-size: 14px !important; }
			.o100-checkout-reward-applied { background: #f0fdf4 !important; border-color: #bbf7d0 !important; }
			.o100-checkout-reward-applied h4 { color: #166534 !important; }
			
			/* Accordion State */
			.o100-checkout-rewards-accordion { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); overflow: hidden; }
			.o100-cra-header { padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: #f8fafc; transition: background 0.2s; }
			.o100-cra-header:hover { background: #f1f5f9; }
			.o100-cra-title { display: flex; align-items: center; gap: 8px; font-size: 15px; color: #0f172a; }
			.o100-cra-toggle { display: flex; align-items: center; }
			.o100-cra-icon { transition: transform 0.3s; stroke: #64748b; }
			.o100-cra-icon.open { transform: rotate(180deg); }
			
			.o100-cra-body { border-top: 1px solid #e2e8f0; padding: 12px 20px; }
			.o100-cra-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed #e2e8f0; gap: 16px; flex-wrap: wrap; }
			.o100-cra-item:last-child { border-bottom: none; }
			.o100-cra-item-info strong { display: block; font-size: 15px; color: #0f172a; margin-bottom: 2px; }
			.o100-cra-item-info p { margin: 0; font-size: 13px; color: #64748b; }
			
			/* Shared Button */
			.o100-apply-checkout-pts-btn { background: var(--wlr-theme-color, #ea580c) !important; color: #fff !important; padding: 8px 16px !important; border-radius: 20px !important; font-weight: 600 !important; font-size: 13px !important; cursor: pointer !important; border: none !important; white-space: nowrap !important; transition: opacity 0.2s !important; display: inline-block !important; text-decoration: none !important; }
			.o100-apply-checkout-pts-btn:hover { opacity: 0.9 !important; color: #fff !important; }
			
			@media (max-width: 480px) {
				.o100-cra-item { flex-direction: column; align-items: flex-start; }
				.o100-apply-checkout-pts-btn { width: 100%; text-align: center; }
			}
		</style>
		<?php
	}

	public static function render_checkout_loyalty_ui() {
		echo '<div class="o100-checkout-loyalty-wrapper">';
		self::render_checkout_loyalty_ui_inner();
		echo '</div>';
	}

	public static function render_checkout_loyalty_ui_inner() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Check if point discount is already applied
		$cart = WC()->cart;
		$is_applied = false;
		if ( $cart ) {
			$applied_coupons = $cart->get_applied_coupons();
			foreach ( $applied_coupons as $coupon_code ) {
				$coupon_id = wc_get_coupon_id_by_code( $coupon_code );
				if ( $coupon_id && ( get_post_meta( $coupon_id, 'is_o100_loyalty_coupon', true ) === 'yes' || get_post_meta( $coupon_id, 'is_wployalty_coupon', true ) === 'yes' ) ) {
					$is_applied = true;
					break;
				}
			}
		}

		$user_id = get_current_user_id();
		$current_user = wp_get_current_user();
		$account = \O100_Loyalty_DB::get_account_by_user( $user_id );
		$user_points = $account ? (int) $account->points_balance : 0;
		
		// Fallback to legacy WPLoyalty points if O100 points is 0 (Before DB migration is fully executed)
		if ( $user_points === 0 && ! empty( $current_user->user_email ) ) {
			global $wpdb;
			$legacy_points = $wpdb->get_var( $wpdb->prepare( "SELECT points FROM {$wpdb->prefix}wlr_users WHERE user_email = %s", $current_user->user_email ) );
			if ( $legacy_points ) {
				$user_points = (int) $legacy_points;
			}
		}
		
		// 100% NATIVE ENGINE REWRITE: Fetch points_conversion rules directly from o100_loyalty_campaigns
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';
		$available_rewards = $wpdb->get_results( "SELECT * FROM {$campaigns_table} WHERE type = 'points_conversion' AND status = 'active' ORDER BY id ASC" );

		if ( empty( $available_rewards ) ) {
			return;
		}

		$currency_sym = get_woocommerce_currency_symbol();
		
		$options = get_option( 'o100_options', array() );
		$theme_color = ! empty( $options['o100_main_color'] ) ? $options['o100_main_color'] : '#e11d48';

		$eligible_rewards = [];
		foreach ( $available_rewards as $camp ) {
			$ui_json = json_decode( $camp->ui_json, true ) ?: [];
			$reward_type = $ui_json['conversion_reward_type'] ?? 'discount';
			$require_pt = intval( $ui_json['conversion_points'] ?? 100 );
			
			// Invalid rule config
			if ( $require_pt <= 0 ) continue;
			
			// Handle limits & balance
			$avail_pt = $user_points;
			$max_allowed = intval( $ui_json['conv_max_points'] ?? 0 );
			if ( $max_allowed > 0 && $max_allowed < $avail_pt ) {
				$avail_pt = $max_allowed;
			}
			
			// Not enough points
			if ( $avail_pt < $require_pt ) continue;
			
			$camp->require_point = $require_pt;
			$camp->discount_type = $reward_type;
			
			if ( $reward_type === 'discount' ) {
				$disc_val = floatval( $ui_json['conversion_value'] ?? 1 );
				if ( $disc_val <= 0 ) continue;
				
				$multiplier = floor( $avail_pt / $require_pt );
				if ( $multiplier >= 1 ) {
					$camp->points_to_use = $multiplier * $require_pt;
					$camp->calculated_discount = $multiplier * $disc_val;
					$eligible_rewards[] = $camp;
				}
			} elseif ( $reward_type === 'free_item' ) {
				$product_id = intval( $ui_json['freeitem_product'] ?? 0 );
				$require_pt = intval( $ui_json['freeitem_points'] ?? $require_pt );
				$camp->require_point = $require_pt;
				if ( $product_id > 0 && $avail_pt >= $require_pt ) {
					$camp->free_product_id = $product_id;
					$eligible_rewards[] = $camp;
				}
			}
		}

		if ( empty( $eligible_rewards ) && ! $is_applied ) {
			return;
		}

		if ( $is_applied ) {
			?>
			<div class="o100-checkout-reward-box o100-checkout-reward-applied">
				<div class="o100-checkout-reward-info">
					<h4><span style="color:<?php echo esc_attr($theme_color); ?>;">⭐</span> Reward Applied!</h4>
					<p>Your point reward has been successfully applied to this order.</p>
				</div>
			</div>
			<?php
		} else {
			?>
			<div class="o100-checkout-rewards-accordion" style="--wlr-theme-color: <?php echo esc_attr($theme_color); ?>;">
				<div class="o100-cra-header" onclick="jQuery(this).next('.o100-cra-body').slideToggle(); jQuery(this).find('.o100-cra-icon').toggleClass('open');">
					<div class="o100-cra-title">
						<span style="color:var(--wlr-theme-color);">⭐</span> 
						<strong>You have <?php echo esc_html( number_format($user_points) ); ?> Points!</strong>
					</div>
					<div class="o100-cra-toggle">
						<span style="font-size:13px; color:#64748b; margin-right:8px;">View <?php echo count($eligible_rewards); ?> Rewards</span>
						<svg class="o100-cra-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
					</div>
				</div>
				<div class="o100-cra-body" style="display:none;">
					<?php foreach ( $eligible_rewards as $camp ) : ?>
						<?php if ( $camp->discount_type === 'discount' ) : ?>
							<div class="o100-cra-item">
								<div class="o100-cra-item-info">
									<strong><?php echo esc_html( $currency_sym . number_format( $camp->calculated_discount, 2 ) ); ?> Discount</strong>
									<p>Use <?php echo esc_html( $camp->points_to_use ); ?> points towards this order.</p>
								</div>
								<button type="button" class="o100-apply-checkout-pts-btn" data-points="<?php echo esc_attr( $camp->points_to_use ); ?>" data-reward-id="<?php echo esc_attr( $camp->id ); ?>" data-reward-type="points_conversion">Apply</button>
							</div>
						<?php elseif ( $camp->discount_type === 'free_item' ) : 
							$pid = $camp->free_product_id;
							$pname = $pid ? get_the_title( $pid ) : 'Free Reward';
							$pname = preg_replace('/^\d+\.\s*/', '', $pname);
							$pname = preg_replace('/\s*\(\#\d+\)$/', '', $pname);
						?>
							<div class="o100-cra-item">
								<div class="o100-cra-item-info">
									<strong>Free <?php echo esc_html($pname); ?></strong>
									<p>Use <?php echo esc_html( $camp->require_point ); ?> points to redeem this item.</p>
								</div>
								<button type="button" class="o100-apply-checkout-pts-btn" data-points="<?php echo esc_attr( $camp->require_point ); ?>" data-reward-id="<?php echo esc_attr( $camp->id ); ?>" data-reward-type="free_product">Apply</button>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
		}
	}
}
