<?php
/**
 * Promotions Frontend Prompts
 *
 * Displays badges, banners, and cross-sell notices based on active promotions.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Promotions_Frontend {

	public static function init() {
		// Side Cart / Checkout Cross-sell Prompts
		add_action( 'woocommerce_widget_shopping_cart_before_buttons', [ __CLASS__, 'render_cart_prompts' ] );

		// Checkout UI Interventions for New Engine
		add_filter( 'woocommerce_cart_item_name', [ __CLASS__, 'add_discount_label_to_cart_item' ], 15, 3 );
		add_filter( 'woocommerce_cart_item_class', [ __CLASS__, 'add_free_reward_cart_class' ], 15, 3 );
		add_action( 'woocommerce_before_cart_table', [ __CLASS__, 'render_savings_dashboard' ], 5 );
		add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'render_savings_dashboard' ], 5 );

		// Product Card Badges & Modal Banners (WooFood specific JS injection)
		add_action( 'wp_footer', [ __CLASS__, 'output_promo_scripts' ], 999 );
		add_action( 'wp_footer', [ __CLASS__, 'render_popup' ], 999 );
		
		// URL Coupon logic
		add_action( 'template_redirect', [ __CLASS__, 'intercept_url_coupon' ], 5 );
		add_action( 'wp_footer', [ __CLASS__, 'render_url_coupon_toast' ], 999 );
	}
	
	public static function intercept_url_coupon() {
		if ( isset( $_GET['coupon'] ) && !empty( $_GET['coupon'] ) ) {
			if ( function_exists( 'WC' ) && WC()->cart && WC()->session ) {
				$code = sanitize_text_field( wp_unslash( $_GET['coupon'] ) );
				if ( ! WC()->cart->has_discount( $code ) ) {
					WC()->cart->add_discount( $code );
					setcookie( 'o100_url_coupon_applied', $code, time() + 3600, COOKIEPATH, COOKIE_DOMAIN );
				}
				// Optionally redirect to remove the query param, but user asked to show toast upon entry.
				// By leaving the param, it's fine, the toast will check the cookie.
			} else {
				// If session is not ready, store in cookie to process later?
				// template_redirect is late enough that WC session is initialized.
			}
		}
	}
	
	public static function render_url_coupon_toast() {
		if ( isset( $_COOKIE['o100_url_coupon_applied'] ) ) {
			$code = sanitize_text_field( wp_unslash( $_COOKIE['o100_url_coupon_applied'] ) );
			$color = self::get_primary_color();
			
			// Clear cookie via JS to ensure it only shows once
			?>
			<script>
				document.cookie = "o100_url_coupon_applied=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=<?php echo COOKIEPATH; ?>;";
				document.addEventListener("DOMContentLoaded", function() {
					var toast = document.createElement("div");
					toast.style.position = "fixed";
					toast.style.top = "20px";
					toast.style.right = "20px";
					toast.style.background = "<?php echo esc_js($color); ?>";
					toast.style.color = "#fff";
					toast.style.padding = "16px 24px";
					toast.style.borderRadius = "12px";
					toast.style.boxShadow = "0 10px 25px rgba(0,0,0,0.2)";
					toast.style.zIndex = "999999";
					toast.style.fontFamily = "system-ui, -apple-system, sans-serif";
					toast.style.fontSize = "15px";
					toast.style.fontWeight = "600";
					toast.style.display = "flex";
					toast.style.alignItems = "center";
					toast.style.gap = "12px";
					toast.style.transition = "all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)";
					toast.style.transform = "translateY(-100px)";
					toast.style.opacity = "0";
					
					var icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;"><path d="M20 6L9 17l-5-5"></path></svg>';
					toast.innerHTML = icon + " <span>Coupon <strong>" + "<?php echo esc_html($code); ?>" + "</strong> applied successfully!</span>";
					
					document.body.appendChild(toast);
					
					setTimeout(function() {
						toast.style.transform = "translateY(0)";
						toast.style.opacity = "1";
					}, 100);
					
					setTimeout(function() {
						toast.style.transform = "translateY(-100px)";
						toast.style.opacity = "0";
						setTimeout(function() { toast.remove(); }, 400);
					}, 4000);
				});
			</script>
			<?php
		}
	}

	public static function get_active_deals() {
		require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
		$promos = O100_Promotions_DB::query([ 'status' => 'active', 'orderby' => 'priority', 'order' => 'ASC' ]);
		$active_deals = [];

		if ( empty( $promos ) ) return $active_deals;

		foreach ( $promos as $promo ) {
			// Skip personal coupons generated from Punch Cards
			if ( isset( $promo['source'] ) && $promo['source'] === 'loyalty_punch' ) {
				continue;
			}

			$has_start = ! empty( $promo['start_date'] ) && $promo['start_date'] !== '0000-00-00 00:00:00';
			$has_end = ! empty( $promo['end_date'] ) && $promo['end_date'] !== '0000-00-00 00:00:00';
			if ( $has_start && strtotime( $promo['start_date'] ) > time() ) continue;
			if ( $has_end && ( strtotime( $promo['end_date'] ) + 86399 ) < time() ) continue;
			$active_deals[] = $promo;
		}

		// Inject active Punch Card Campaigns into Deals & Offers
		if ( class_exists('O100_Native_Punch_Card') ) {
			$punch_cards = O100_Native_Punch_Card::get_active_punch_cards();
			foreach ( $punch_cards as $pc ) {
				$ui = json_decode( (string) $pc->ui_json, true );
				$required = isset($ui['punch_count']) ? intval($ui['punch_count']) : 5;
				
				// Reconstruct a mock promo array for the Deals & Offers banner
				$mock_promo = [
					'id' => 'pc_' . $pc->id,
					'title' => $pc->name,
					'description' => 'Buy ' . $required . ' participating items to get a free reward',
					'rule_type' => 'punch_card',
					'apply_to' => 'specific_products',
					'apply_to_items' => json_encode([]), // Default empty, actual items are in conditions_json
					'action_config' => json_encode([
						'display' => [
							'banner_text' => '',
							'badge_text' => isset($ui['campaign_title_discount']) && !empty($ui['campaign_title_discount']) ? wp_strip_all_tags($ui['campaign_title_discount']) : 'Punch Card',
							'popup_enabled' => false,
							'popup_title' => $pc->name,
							'popup_text' => 'Buy ' . $required . ' participating items to earn a free reward.',
						]
					])
				];

				// Extract items from conditions_json for the popup slider
				$conditions = json_decode( (string) $pc->conditions_json, true );
				$items = [];
				if ( !empty($conditions['products']) ) {
					$items = is_array($conditions['products']) ? $conditions['products'] : [$conditions['products']];
				} elseif ( !empty($conditions) && is_array($conditions) ) {
					foreach ($conditions as $cond) {
						if (isset($cond['type']) && $cond['type'] === 'products' && isset($cond['options']['value'])) {
							$val = $cond['options']['value'];
							$items = array_merge($items, is_array($val) ? $val : [$val]);
						}
					}
				}
				if ( !empty($items) ) {
					$mock_promo['apply_to_items'] = json_encode( array_values( array_unique( $items ) ) );
				}

				$active_deals[] = $mock_promo;
			}
		}
		return $active_deals;
	}

	public static function add_discount_label_to_cart_item( $name, $cart_item, $cart_item_key ) {
		if ( is_cart() || is_checkout() || defined('DOING_AJAX') ) {
			if ( isset( $cart_item['o100_reward_item'] ) ) {
				$name = '<strong style="color:#10b981; font-size:13px; margin-right:6px;">🎁 FREE:</strong> ' . $name;
			}
			if ( ! empty( $cart_item['o100_promo_badge'] ) ) {
				$badges = explode(', ', $cart_item['o100_promo_badge']);
				foreach ( $badges as $label ) {
					$name .= '<br/><small class="o100-cart-discount-label" style="color:var(--o100-promo-color, #E53935);font-weight:600;">Promo: <span class="o100-cart-discount-badge" style="background:var(--o100-promo-bg, #FFEEF0);color:var(--o100-promo-color, #E53935);padding:1px 6px;border-radius:3px;font-size:11px;">' . esc_html( $label ) . '</span></small>';
				}
			}
		}
		return $name;
	}

	public static function add_free_reward_cart_class( $class, $cart_item, $cart_item_key ) {
		if ( isset( $cart_item['o100_reward_item'] ) ) {
			$class .= ' o100-free-reward-row';
		}
		return $class;
	}

	public static function render_savings_dashboard() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) return;

		// 1. Collect Promotion Messages
		$promo_messages = [];
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['o100_promo_badge'] ) ) {
				$badges = explode(', ', $cart_item['o100_promo_badge']);
				foreach ( $badges as $badge ) {
					if ( ! in_array( $badge, $promo_messages ) ) {
						$promo_messages[] = $badge;
					}
				}
			}
		}

		// 2. Fetch Free Shipping HTML
		$free_shipping_html = '';
		if ( class_exists( 'O100_Shipping' ) ) {
			$shipping_module = new O100_Shipping();
			$free_shipping_html = $shipping_module->display_free_shipping_progress( true );
		}
		
		// 3. Fetch Earn Points Message (Native)
		$loyalty_html = '';
		if ( class_exists( 'O100_Loyalty_Engine' ) && class_exists( 'O100_Loyalty_DB' ) ) {
			$loyalty_engine = new O100_Loyalty_Engine();
			$earned_points = $loyalty_engine->calculate_cart_points();
			
			// DEBUG START
			$trace = property_exists($loyalty_engine, 'debug_info') ? $loyalty_engine->debug_info : '';
			$loyalty_html .= '<!-- DEBUG PTS: ' . $earned_points . ' | TRACE: ' . esc_html($trace) . ' -->';
			// DEBUG END
			
			if ( $earned_points > 0 ) {
				$settings = O100_Loyalty_DB::get_settings();
				$label = $settings['point_label_plural'] ?? 'Points';
				$loyalty_html .= sprintf( __( 'You will earn <strong>%s %s</strong> for this order.', 'order100' ), number_format_i18n( $earned_points ), $label );
			}
		}

		// 4. Fetch Native Punch Card Coupons
		$native_promo = null;
		if ( is_user_logged_in() && class_exists('O100_Promotions_DB') ) {
			global $wpdb;
			$user_email = wp_get_current_user()->user_email;
			$promo_table = O100_Promotions_DB::table_name();
			
			$native_active = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$promo_table} WHERE source = 'loyalty_punch' AND status = 'active' AND usage_count < usage_limit AND conditions LIKE %s ORDER BY id DESC LIMIT 1",
				'%' . $wpdb->esc_like( $user_email ) . '%'
			) );
			
			if ( !empty($native_active) ) {
				$native_promo = $native_active[0];
			}
		}

		// If no promotions and no free shipping message and no loyalty, we don't need a dashboard
		if ( empty( $promo_messages ) && empty( $free_shipping_html ) && empty( $loyalty_html ) && empty( $native_promo ) ) {
			return;
		}

		?>
		<style>
			/* Hide default Woo messages that we are overriding to prevent duplicates */
			.woocommerce-message.o100-discount-banner { display: none !important; }
			
			/* Hide WPLoyalty default notices if they appear outside our dashboard */
			.wlr-checkout-msg-container, .wlr-cart-msg-container, 
			.woocommerce-info .wlr-points-message, 
			.wlr-points-message-container,
			.woocommerce-message:has(.wlr-cart-notice),
			.woocommerce-info:has([class*="wlr"]),
			.wlr-message-info,
			.wlr_point_redeem_message,
			.wlr_points_rewards_earn_points { 
				display: none !important; 
			}
			
			/* Savings Dashboard Styles */
			.o100-savings-dashboard {
				background: #fff;
				border: 1px solid #e2e8f0;
				border-radius: 12px;
				padding: 16px;
				margin-bottom: 24px;
				box-shadow: 0 2px 4px rgba(0,0,0,0.02);
			}
			.o100-savings-dashboard-title {
				font-size: 15px;
				font-weight: 700;
				color: #0f172a;
				margin-bottom: 12px;
				display: flex;
				align-items: center;
				gap: 8px;
				border-bottom: 1px solid #f1f5f9;
				padding-bottom: 12px;
			}
			.o100-savings-list {
				list-style: none;
				margin: 0;
				padding: 0;
				display: flex;
				flex-direction: column;
			}
			.o100-savings-item {
				display: flex;
				align-items: center;
				font-size: 14px;
				color: #166534;
				padding: 10px 0;
				border-bottom: 1px solid #f1f5f9;
			}
			.o100-savings-item:last-child {
				border-bottom: none;
				padding-bottom: 0;
			}
			.o100-savings-item svg {
				width: 16px;
				height: 16px;
				margin-right: 8px;
				stroke: #166534;
			}
			.o100-savings-shipping-wrap {
				margin-bottom: 0;
				border-bottom: 1px solid #f1f5f9;
			}
			.o100-savings-shipping-wrap .o100-free-ship-banner {
				margin-bottom: 0 !important; /* Reset margin since we wrap it */
			}
			.o100-savings-loyalty {
				display: flex;
				align-items: center;
				font-size: 14px;
				color: #0f172a;
				padding: 10px 0 0 0;
			}
			.o100-savings-loyalty svg {
				width: 16px;
				height: 16px;
				margin-right: 8px;
				stroke: #f59e0b;
				fill: rgba(245, 158, 11, 0.2);
			}
			.o100-savings-loyalty a {
				color: var(--o100-primary-color, #e11d48);
				font-weight: 600;
				text-decoration: none;
				margin-left: 4px;
			}
		</style>
		
		<div class="o100-savings-dashboard">
			<div class="o100-savings-dashboard-title">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #f59e0b;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
				<?php esc_html_e( 'Your Rewards & Savings', 'order100' ); ?>
			</div>
			
			<?php if ( ! empty( $free_shipping_html ) ) : ?>
				<div class="o100-savings-shipping-wrap">
					<?php echo $free_shipping_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $promo_messages ) ) : ?>
				<div class="o100-savings-list">
					<div class="o100-savings-item">
						<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
						<?php 
						$combined_promos = implode( ' <span style="color:#cbd5e1;margin:0 4px;">|</span> ', array_map( 'esc_html', $promo_messages ) );
						echo sprintf( __( 'Applied: <strong>%s</strong>', 'order100' ), $combined_promos ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>
				</div>
			<?php endif; ?>
			
			<?php if ( ! empty( $loyalty_html ) ) : ?>
				<div class="o100-savings-loyalty">
					<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
					<span><?php echo $loyalty_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
			<?php endif; ?>
			
			<?php
			// 4. Check for unused Punch Card Native Coupons
			if ( !empty($native_promo) ) {
				$promo = $native_promo;
				$items = json_decode( $promo->apply_to_items, true );
				$product_id = !empty($items) ? intval($items[0]) : 0;
				
				// Get theme color for styling
				$options = get_option( 'o100_options', array() );
				$primary_color = !empty($options['o100_main_color']) ? $options['o100_main_color'] : '#e11d48';
				
				// Convert hex to rgb for opacity
				$hex = ltrim($primary_color, '#');
				if ( strlen($hex) == 3 ) {
					$r = hexdec(str_repeat(substr($hex,0,1), 2));
					$g = hexdec(str_repeat(substr($hex,1,1), 2));
					$b = hexdec(str_repeat(substr($hex,2,1), 2));
				} else {
					$r = hexdec(substr($hex,0,2));
					$g = hexdec(substr($hex,2,2));
					$b = hexdec(substr($hex,4,2));
				}
				$bg_color = "rgba($r, $g, $b, 0.08)";
				$border_color = "rgba($r, $g, $b, 0.2)";
				$text_color = $primary_color;
				
				// Check if already in cart or applied
				$already_applied = false;
				if ( WC()->cart->has_discount( $promo->promo_code ) ) {
					$already_applied = true;
				}
				
				if ( !$already_applied && $product_id > 0 ) {
					$product_name = get_the_title( $product_id );
					?>
					<div class="o100-savings-loyalty" style="background:<?php echo $bg_color; ?>; padding:12px; border-radius:8px; margin-top:12px; border:1px solid <?php echo $border_color; ?>;">
						<div style="flex:1;">
							<strong style="color:<?php echo $text_color; ?>;display:block;margin-bottom:2px;">🎁 You have a free <?php echo esc_html($product_name); ?> available!</strong>
							<span style="color:<?php echo $text_color; ?>;opacity:0.8;font-size:13px;">Click 'Apply Now' to add it to your order.</span>
						</div>
						<button class="o100-btn-redeem" style="background:<?php echo $text_color; ?>; color:#fff; border:none; padding:6px 12px; font-size:13px; width:auto; border-radius:4px; margin-left:12px; cursor:pointer;" onclick="o100ApplyCheckoutReward(this, '<?php echo esc_js($promo->promo_code); ?>', <?php echo intval($product_id); ?>)">Apply Now</button>
					</div>
					<script>
					function o100ApplyCheckoutReward(btn, code, productId) {
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
								location.reload();
							} else {
								btn.innerText = "Failed";
								if (typeof o100ShowToast === "function") o100ShowToast(res.data ? res.data.message : "Error", "error");
								setTimeout(function(){ btn.innerText = origText; btn.style.opacity = "1"; btn.style.pointerEvents = "auto"; }, 2000);
							}
						});
					}
					</script>
					<?php
				}
			}
			?>
		</div>
		<?php
	}

	public static function render_popup() {
		$deals = self::get_active_deals();
		if ( empty($deals) ) return;

		$popup_content = '';
		$popup_title = '';
		$popup_duration = 5;
		$popup_location = 'all';

		foreach ($deals as $deal) {
			$config = json_decode( $deal['action_config'], true );
			if (isset($config['display']) && !empty($config['display']['popup_enabled'])) {
				$popup_content = $config['display']['popup_text'];
				$popup_title = $config['display']['popup_title'];
				$popup_location = $config['display']['popup_location'];
				$popup_duration = isset($config['display']['popup_duration']) ? intval($config['display']['popup_duration']) : 5;
				break; // Only show the highest priority popup
			}
		}

		if ( empty($popup_content) ) return;

		// Location Check
		$show = false;
		if ( $popup_location === 'all' ) {
			$show = true;
		} elseif ( $popup_location === 'shop' && ( is_shop() || is_product_category() ) ) {
			$show = true;
		} elseif ( $popup_location === 'checkout' && is_checkout() ) {
			$show = true;
		} elseif ( $popup_location === 'home' && ( is_front_page() || is_home() ) ) {
			$show = true;
		}
		
		// If on menu page (which could be a custom page with shortcode)
		global $post;
		if ( $popup_location === 'shop' && is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'order100_food_menu' ) ) {
			$show = true;
		}

		if ( ! $show ) return;

		$color = self::get_primary_color();
		?>
		<style>
			.o100-promo-popup-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
			.o100-promo-popup-overlay.active { opacity: 1; visibility: visible; }
			.o100-promo-popup { background: #fff; width: 90%; max-width: 400px; border-radius: 16px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); transform: translateY(20px) scale(0.95); transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; }
			.o100-promo-popup-overlay.active .o100-promo-popup { transform: translateY(0) scale(1); }
			.o100-promo-popup-close { position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; background: #f1f5f9; border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #475569; z-index: 10; transition: all 0.2s; padding: 0; }
			.o100-promo-popup-close svg { stroke: currentColor; }
			.o100-promo-popup-close:hover { background: #e2e8f0; color: #0f172a; }
			.o100-promo-popup-title { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 12px 0; padding-right: 24px; }
			.o100-promo-popup-body { font-size: 15px; color: #475569; line-height: 1.5; }
			.o100-promo-popup-body p { margin: 0 0 10px 0; }
			.o100-promo-popup-body p:last-child { margin-bottom: 0; }
			.o100-promo-popup-progress { height: 4px; background: <?php echo esc_attr($color); ?>; width: 100%; border-radius: 0 0 16px 16px; position: absolute; bottom: 0; left: 0; transform-origin: left; animation: o100-popup-progress <?php echo esc_attr($popup_duration); ?>s linear forwards; }
			@keyframes o100-popup-progress { from { transform: scaleX(1); } to { transform: scaleX(0); } }
		</style>
		<div class="o100-promo-popup-overlay" id="o100-promo-popup-overlay">
			<div class="o100-promo-popup">
				<button class="o100-promo-popup-close" id="o100-promo-popup-close">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
				</button>
				<?php if ( $popup_title ) : ?>
					<h3 class="o100-promo-popup-title"><?php echo esc_html( $popup_title ); ?></h3>
				<?php endif; ?>
				<div class="o100-promo-popup-body">
					<?php echo wp_kses_post( $popup_content ); ?>
				</div>
				<div class="o100-promo-popup-progress"></div>
			</div>
		</div>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				var overlay = document.getElementById('o100-promo-popup-overlay');
				var closeBtn = document.getElementById('o100-promo-popup-close');
				var duration = <?php echo intval($popup_duration) * 1000; ?>;
				
				// Always bind close events so the popup works if triggered manually by deal cards
				window.closeO100PromoPopup = function() {
					if (overlay) overlay.classList.remove('active');
				};

				if (closeBtn) closeBtn.addEventListener('click', window.closeO100PromoPopup);
				if (overlay) {
					overlay.addEventListener('click', function(e) {
						if (e.target === overlay) window.closeO100PromoPopup();
					});
				}

				// Frequency capping for AUTO-SHOW only
				var lastSeen = localStorage.getItem('o100_promo_popup_seen');
				var now = new Date().getTime();
				if (lastSeen && now - lastSeen < 3600000) { // 1 hour
					return;
				}
				
				setTimeout(function() {
					if (overlay) overlay.classList.add('active');
					localStorage.setItem('o100_promo_popup_seen', now);
				}, 1000); // 1 second delay before showing

				if (duration > 0) {
					setTimeout(window.closeO100PromoPopup, duration + 1000);
				}
			});
		</script>
		<?php
	}



	/**
	 * Get the store's primary color
	 */
	private static function get_primary_color() {
		$options = get_option( 'o100_options', [] );
		return ! empty( $options['o100_main_color'] ) ? $options['o100_main_color'] : '#e11d48';
	}

	/**
	 * Get active promos that target products
	 */
	private static function get_product_promos( $product_id ) {
		require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
		$promos = O100_Promotions_DB::query([ 'status' => 'active', 'orderby' => 'priority', 'order' => 'ASC' ]);
		$matched = [];

		if ( empty( $promos ) ) return $matched;

		foreach ( $promos as $promo ) {
			// Basic filtering
			$has_start = ! empty( $promo['start_date'] ) && $promo['start_date'] !== '0000-00-00 00:00:00';
			$has_end = ! empty( $promo['end_date'] ) && $promo['end_date'] !== '0000-00-00 00:00:00';
			if ( $has_start && strtotime( $promo['start_date'] ) > time() ) continue;
			if ( $has_end && ( strtotime( $promo['end_date'] ) + 86399 ) < time() ) continue;

			$apply_to = $promo['apply_to'];
			if ( $apply_to === 'all_products' ) {
				$matched[] = $promo;
				continue;
			}

			$items = json_decode( $promo['apply_to_items'], true );
			if ( $apply_to === 'specific_products' && in_array( $product_id, $items ) ) {
				$matched[] = $promo;
			} elseif ( $apply_to === 'specific_categories' ) {
				$terms = wp_get_post_terms( $product_id, 'product_cat', ['fields' => 'ids'] );
				if ( count( array_intersect( $terms, $items ) ) > 0 ) {
					$matched[] = $promo;
				}
			}
		}

		return $matched;
	}

	public static function output_promo_scripts() {
		require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
		$promos = O100_Promotions_DB::query([ 'status' => 'active', 'orderby' => 'priority', 'order' => 'ASC' ]);
		
		$color = self::get_primary_color();
		$bg_color = self::hexToRgb($color, 0.1);

		// Build a map of product_id => active promo label
		$product_promos = [];

		if ( ! empty( $promos ) ) {
			foreach ( $promos as $promo ) {
				// Skip personal coupons generated from Punch Cards
				if ( isset( $promo['source'] ) && $promo['source'] === 'loyalty_punch' ) {
					continue;
				}

			$has_start = ! empty( $promo['start_date'] ) && $promo['start_date'] !== '0000-00-00 00:00:00';
			$has_end = ! empty( $promo['end_date'] ) && $promo['end_date'] !== '0000-00-00 00:00:00';
			if ( $has_start && strtotime( $promo['start_date'] ) > time() ) continue;
			if ( $has_end && ( strtotime( $promo['end_date'] ) + 86399 ) < time() ) continue;

			$rule_type = $promo['rule_type'];
			$label = '';
			$msg = '';

			if ( $rule_type === 'bogo' ) {
				$config = json_decode( $promo['action_config'], true );
				$label = 'Buy ' . $config['buy_qty'] . ' Get ' . $config['get_qty'];
				if ( $config['discount_type'] === 'free' ) $label .= ' Free';
				$msg = '🔥 <strong>' . esc_html( $promo['title'] ) . '</strong>: Buy ' . $config['buy_qty'] . ' Get ' . $config['get_qty'] . ( $config['discount_type'] === 'free' ? ' for FREE!' : ' discounted!' );
			} elseif ( $rule_type === 'buy_x_get_y' ) {
				$label = 'Combo Deal';
				$msg = '🎁 <strong>' . esc_html( $promo['title'] ) . '</strong>: Add this and get a special item!';
			} elseif ( $rule_type === 'bulk_tiered' ) {
				$label = 'Bulk Discount';
				$msg = '🏷️ <strong>' . esc_html( $promo['title'] ) . '</strong>: Buy more, save more!';
			} elseif ( $rule_type === 'bundle' ) {
				$label = 'Set Offer';
				$msg = '🏷️ <strong>' . esc_html( $promo['title'] ) . '</strong>: Special set discount!';
			} elseif ( $rule_type === 'simple' ) {
				$config = json_decode( $promo['action_config'], true );
				if ( $config['discount_type'] === 'percentage' ) {
					$label = floatval( $config['discount_value'] ) . '% OFF';
				} elseif ( $config['discount_type'] === 'free_shipping' ) {
					$label = 'Free Shipping';
				} elseif ( $config['discount_type'] === 'free_item' ) {
					$label = 'Free Item';
				} else {
					$label = 'Special Deal';
				}
				$msg = '🏷️ <strong>' . esc_html( $promo['title'] ) . '</strong>: ' . ( $config['discount_type'] === 'percentage' ? floatval($config['discount_value']) . '% OFF!' : 'Special Discount!' );
			}

			// OVERRIDE WITH CUSTOM DISPLAY SETTINGS
			$config = json_decode( $promo['action_config'], true );
			if (isset($config['display'])) {
				if (!empty($config['display']['badge_text'])) {
					$label = $config['display']['badge_text'];
				}
				if (!empty($config['display']['promo_subtitle'])) { // Wait, it's mapped to description
					// Wait, the DB column description was populated from frontend step 1 previously.
					// Let's use the $promo['description']
				}
			}
			
			if ( !empty($promo['description']) ) {
				$msg = '<strong>' . esc_html( $promo['title'] ) . '</strong>: ' . esc_html($promo['description']);
			}

			if ( ! $label ) continue;

			$apply_to = $promo['apply_to'];
			if ( $apply_to === 'all_products' ) {
				continue; // Skip product-level injection for cart-level promos
			}

			$items = json_decode( $promo['apply_to_items'], true );
			if ( ! is_array( $items ) ) $items = [];

			if ( $apply_to === 'specific_products' ) {
				foreach ( $items as $pid ) {
					if ( ! isset( $product_promos[ $pid ] ) ) {
						$product_promos[ $pid ] = [ 'label' => $label, 'msg' => $msg ];
					}
				}
			} elseif ( $apply_to === 'specific_categories' ) {
				// We need to resolve category to products
				foreach ( $items as $cat_id ) {
					$cat_products = get_posts([
						'post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids',
						'tax_query' => [[ 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cat_id ]]
					]);
					foreach ( $cat_products as $pid ) {
						if ( ! isset( $product_promos[ $pid ] ) ) {
							$product_promos[ $pid ] = [ 'label' => $label, 'msg' => $msg ];
						}
					}
				}
			}
		}
		} // End of if (!empty($promos))

		// Inject Punch Cards into Promotions Array
		if ( class_exists('O100_Native_Punch_Card') ) {
			$punch_cards = O100_Native_Punch_Card::get_active_punch_cards();
			foreach ( $punch_cards as $pc ) {
				$label = 'Punch Card';
				$ui = json_decode( (string) $pc->ui_json, true );
				$required = isset($ui['punch_count']) ? intval($ui['punch_count']) : 5;
				$msg = '<strong>' . esc_html( $pc->name ) . '</strong>: Buy ' . $required . ' to get a free reward!';
				
				// Allow UI overrides
				if ( isset($ui['campaign_title_discount']) && !empty($ui['campaign_title_discount']) ) {
					$label = wp_strip_all_tags($ui['campaign_title_discount']);
				}

				$conditions = json_decode( (string) $pc->conditions_json, true );
				$items = [];
				if ( !empty($conditions['products']) ) {
					$items = is_array($conditions['products']) ? $conditions['products'] : [$conditions['products']];
				} elseif ( !empty($conditions) && is_array($conditions) ) {
					// Handle the format where conditions is an array of rules
					foreach ($conditions as $cond) {
						if (isset($cond['type']) && $cond['type'] === 'products' && isset($cond['options']['value'])) {
							$val = $cond['options']['value'];
							$items = array_merge($items, is_array($val) ? $val : [$val]);
						}
					}
				}

				foreach ( $items as $pid ) {
					if ( ! isset( $product_promos[ $pid ] ) ) {
						$product_promos[ $pid ] = [ 'label' => $label, 'msg' => $msg ];
					}
				}
			}
		}

		if ( empty( $product_promos ) ) return;
		?>
		<script>
		(function($) {
			$(document).ready(function() {
				var promoData = <?php echo json_encode( $product_promos ); ?>;
				var color = '<?php echo esc_js($color); ?>';
				var bgColor = '<?php echo esc_js($bg_color); ?>';
				console.log("O100 Promotions Loaded:", promoData);

				function getPromo(pid) {
					if (promoData[pid]) return promoData[pid];
					if (promoData['all']) return promoData['all'];
					return null;
				}

				function getProductId($card) {
					// Method 0: Native Order100 Menu Cards
					var o100Id = $card.attr('data-product-id') || $card.data('product-id');
					if (o100Id) return String(o100Id);

					var idFood = $card.attr('data-id_food') || $card.data('id_food');
					if (idFood) return String(idFood);

					var dataId = $card.attr('data-id') || $card.data('id');
					if (dataId) {
						var parts = String(dataId).split('-');
						if (parts.length >= 3) return parts[parts.length - 1];
					}

					var $figure = $card.find('figure[class*="tppost-"]');
					if ($figure.length) {
						var match = $figure.attr('class').match(/tppost-(\d+)/);
						if (match) return match[1];
					}

					var $btn = $card.find('button[name="add-to-cart"], button.ex-add-cart, button.add_to_cart_button, a.add_to_cart_button');
					if ($btn.length) {
						var val = $btn.val() || $btn.attr('data-product_id');
						if (val) return String(val);
						
						var href = $btn.attr('href');
						if (href) {
							var m = href.match(/add-to-cart=(\d+)/);
							if (m) return String(m[1]);
						}
					}

					// Look deeper for data-id_food in children
					var $inner = $card.find('[data-id_food]').first();
					if ($inner.length) return String($inner.attr('data-id_food'));

					return null;
				}

				function buildPill(label) {
					return '<div class="o100-dd-promo-pill" style="margin-top: 4px; margin-bottom: 4px;"><span style="display:inline-block; background-color:' + bgColor + '; color:' + color + '; padding:2px 8px; font-size:11px; font-weight:600; border-radius:12px;">' + label + '</span></div>';
				}

				function buildModalPill(msg) {
					return '<div class="o100-dd-promo-modal-pill" style="margin-top: 4px; margin-bottom: 8px;"><span style="display:inline-block; background-color:' + bgColor + '; color:' + color + '; padding:4px 10px; font-size:12px; font-weight:600; border-radius:12px;">' + msg + '</span></div>';
				}

				function applyToCards() {
					$('.o100-product-card, .fditem-list, .item-grid').each(function() {
						var $card = $(this);
						if ($card.data('o100-promo-injected') || $card.hasClass('o100-sc-promo-card')) return;
						
						var pid = getProductId($card);
						if (!pid) {
							console.log("Order100: Could not extract PID for card", $card);
							return;
						}

						var promo = getPromo(pid);
						if (!promo) {
							console.log("Order100: PID " + pid + " does not have an active promo.");
							return;
						}

						$card.data('o100-promo-injected', true);
						var pillHtml = buildPill(promo.label);

						// Mimic legacy script DOM insertion precisely
						var injected = false;
						var $figure = $card.find('figure').first();
						if ($figure.length) {
							var $name1 = $figure.find('.fdlist_1_name').first();
							if ($name1.length) { $name1.append(pillHtml); injected = true; }
							
							if (!injected) {
								var $name3 = $figure.find('.fdlist_3_name').first();
								if ($name3.length) { $name3.after(pillHtml); injected = true; }
							}

							if (!injected) {
								var $title = $figure.find('figcaption h3').first();
								if ($title.length) { $title.after(pillHtml); injected = true; }
							}
						}
						
						// General fallback - Highly aggressive
						if (!injected) {
							var $name = $card.find('[class*="name"], h3, h4, .title, .product-title, .woocommerce-loop-product__title').first();
							if ($name.length) {
								$name.append(pillHtml);
								injected = true;
							}
						}

						if (!injected) {
							console.log("Order100: Could not find title element for PID " + pid, $card);
							$card.append(pillHtml); // Absolute last resort
						}
					});
				}

				function applyToModal() {
					var $modal = $('#food_modal, #o100-pm-overlay');
					if (!$modal.length || (!$modal.hasClass('exfd-modal-active') && !$modal.hasClass('active'))) return;

					var pid = null;
					var $inner = $modal.find('.ex-modal-big[id^="product-"]');
					if ($inner.length) pid = $inner.attr('id').replace('product-', '');

					if (!pid) {
						var $btn = $modal.find('button[name="add-to-cart"]');
						if ($btn.length) pid = String($btn.val());
						else {
							// Also check hidden inputs for PID if button lacks value
							var $input = $modal.find('input[name="add-to-cart"]');
							if ($input.length) pid = String($input.val());
						}
					}

					if (!pid) {
						console.log("Order100: Could not extract PID from Modal");
						return;
					}

					var promo = getPromo(pid);
					if (!promo) return;

					if ($modal.find('.o100-dd-promo-modal-pill').length) return; // Prevent duplicates

					var pillHtml = buildModalPill(promo.msg);
					var injected = false;
					
					var $des = $modal.find('.fd_modal_des, .summary, .product-details');
					var $searchContext = $des.length ? $des : $modal;

					var $title = $searchContext.find('h1, h2, h3, h4, .product_title, .modal-title, [class*="title"]').first();
					if ($title.length) {
						$title.after(pillHtml);
						injected = true;
					} else {
						// Extreme fallback
						console.log("Order100: Using extreme fallback for modal injection");
						$searchContext.prepend(pillHtml);
					}
				}

				applyToCards();
				$(document).ajaxComplete(function() {
					setTimeout(applyToCards, 500);
				});

				// Watch for legacy modal open
				var fm = document.getElementById('food_modal');
				if (fm) {
					var obs = new MutationObserver(function(muts) {
						muts.forEach(function(m) {
							if (m.type === 'attributes' && $(m.target).hasClass('exfd-modal-active')) {
								setTimeout(function() {
									$(m.target).find('.o100-dd-promo-modal-pill').remove();
									applyToModal();
								}, 350);
							}
						});
					});
					obs.observe(fm, { attributes: true, attributeFilter: ['class'] });
				} else {
					$(document).on('click', '.exfd_modal_click', function() {
						setTimeout(applyToModal, 600);
					});
				}

				// Watch for NEW Order100 Modal open
				$(document).on('o100_modal_loaded', function(e, $modal) {
					setTimeout(function() {
						$modal.find('.o100-dd-promo-modal-pill').remove();
						applyToModal();
					}, 200);
				});

			});
		})(jQuery);
		</script>
		<?php
	}

	public static function render_deals_carousel() {
		// Deprecated: Carousel is now rendered directly by O100_Menu_Renderer
	}

	public static function render_cart_prompts() {
		if ( ! WC()->cart ) return;
		$cart_items = WC()->cart->get_cart();
		if ( empty( $cart_items ) ) return;

		require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
		$promos = O100_Promotions_DB::query([ 'status' => 'active', 'orderby' => 'priority', 'order' => 'ASC' ]);
		if ( empty( $promos ) ) return;

		$color = self::get_primary_color();
		$bg_color = self::hexToRgb( $color, 0.1 );

		foreach ( $promos as $promo ) {
			if ( $promo['rule_type'] === 'bogo' ) {
				$config = json_decode( $promo['action_config'], true );
				$buy_qty = intval( $config['buy_qty'] );
				$get_qty = intval( $config['get_qty'] );

				// Check if there are applicable items in cart
				$items = json_decode( $promo['apply_to_items'], true );
				$apply_to = $promo['apply_to'];
				$cart_qty_for_promo = 0;
				$sample_product_name = '';

				foreach ( $cart_items as $item ) {
					$pid = $item['product_id'];
					$match = false;
					if ( $apply_to === 'all_products' ) {
						$match = true;
					} elseif ( $apply_to === 'specific_products' && in_array( $pid, $items ) ) {
						$match = true;
					} elseif ( $apply_to === 'specific_categories' ) {
						$terms = wp_get_post_terms( $pid, 'product_cat', ['fields' => 'ids'] );
						if ( count( array_intersect( $terms, $items ) ) > 0 ) $match = true;
					}

					if ( $match ) {
						$cart_qty_for_promo += $item['quantity'];
						if ( ! $sample_product_name ) $sample_product_name = $item['data']->get_name();
					}
				}

				if ( $cart_qty_for_promo > 0 ) {
					$group_size = $buy_qty + $get_qty;
					$remainder = $cart_qty_for_promo % $group_size;
					
					// If they have some items but haven't triggered the BOGO completely
					if ( $remainder > 0 && $remainder < $group_size ) {
						$needed = $group_size - $remainder;
						if ( $needed <= $get_qty ) {
							$msg = '🎉 You are so close! Add <strong>' . $needed . ' more</strong> ' . esc_html( $sample_product_name ) . ' to get it ' . ( $config['discount_type'] === 'free' ? 'FREE' : 'discounted' ) . '!';
							echo '<div style="background-color:' . esc_attr($bg_color) . '; color:' . esc_attr($color) . '; font-size:12px; font-weight:bold; padding:8px 12px; border-radius:6px; margin-bottom:12px; text-align:center;">' . wp_kses_post( $msg ) . '</div>';
							break; // Only show one upsell
						}
					}
				}
			}
		}
	}

	private static function hexToRgb($hex, $alpha = false) {
		$hex = str_replace('#', '', $hex);
		$length = strlen($hex);
		$rgb['r'] = hexdec($length == 6 ? substr($hex, 0, 2) : ($length == 3 ? str_repeat(substr($hex, 0, 1), 2) : 0));
		$rgb['g'] = hexdec($length == 6 ? substr($hex, 2, 2) : ($length == 3 ? str_repeat(substr($hex, 1, 1), 2) : 0));
		$rgb['b'] = hexdec($length == 6 ? substr($hex, 4, 2) : ($length == 3 ? str_repeat(substr($hex, 2, 1), 2) : 0));
		if ($alpha) {
			$rgb['a'] = $alpha;
			return 'rgba(' . implode(',', $rgb) . ')';
		}
		return 'rgb(' . implode(',', $rgb) . ')';
	}
}


