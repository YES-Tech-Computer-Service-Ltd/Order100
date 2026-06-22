<?php
if ( ! defined( 'WPINC' ) ) die;

class O100_Side_Cart {

	public function __construct() {
		add_action( 'wp_footer', array( $this, 'render_side_cart' ) );
		add_action( 'wp_footer', array( $this, 'render_mobile_nav_bar' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_fragments' ) );

		// AJAX qty update
		add_action( 'wp_ajax_o100_update_cart_qty', array( $this, 'ajax_update_qty' ) );
		add_action( 'wp_ajax_nopriv_o100_update_cart_qty', array( $this, 'ajax_update_qty' ) );

		// Quickview Modal
		add_action( 'wp_ajax_o100_get_product_quickview', array( $this, 'ajax_quickview' ) );
		add_action( 'wp_ajax_nopriv_o100_get_product_quickview', array( $this, 'ajax_quickview' ) );

		// Inject Loyalty Content into Rewards Tab
		add_action( 'o100_store_portal_rewards_tab_content', array( $this, 'inject_loyalty_rewards_content' ) );
	}

	public function ajax_quickview() {
		$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
		if ( ! $product_id ) wp_send_json_error('No product id');

		$product = wc_get_product($product_id);
		if ( ! $product ) wp_send_json_error('Invalid product');

		ob_start();
		$img = wp_get_attachment_image_url( $product->get_image_id(), 'large' ) ?: wc_placeholder_img_src('large');
		?>
		<div style="display:flex; flex-direction:column; background:#fff; border-radius:16px; overflow:hidden;">
			<div style="height:220px; background:url('<?php echo esc_url($img); ?>') center/cover;"></div>
			<div style="padding:20px;">
				<h2 style="margin:0 0 8px; font-size:20px; font-weight:800; color:#111;"><?php echo esc_html($product->get_name()); ?></h2>
				<div style="font-size:18px; font-weight:700; color:var(--theme-color, #e11d48); margin-bottom:12px;"><?php echo $product->get_price_html(); ?></div>
				<div style="font-size:14px; color:#6b7280; margin-bottom:20px; line-height:1.5;">
					<?php echo wp_kses_post( $product->get_short_description() ?: $product->get_description() ); ?>
				</div>
				<?php 
				// Render Add to Cart Form
				if ( $product->is_type('simple') ) {
					echo '<div class="o100-quickview-atc-wrap">';
					woocommerce_simple_add_to_cart(); 
					echo '</div>';
				} else if ( $product->is_type('variable') ) {
					echo '<div class="o100-quickview-atc-wrap o100-quickview-variable">';
					woocommerce_variable_add_to_cart();
					echo '</div>';
				} else {
					echo '<a href="'.esc_url($product->get_permalink()).'" class="button alt" style="display:block; text-align:center; padding:12px; background:var(--theme-color,#e11d48); color:#fff; border-radius:8px; font-weight:700;">'.esc_html__('View Product', 'order100').'</a>';
				}
				?>
			</div>
		</div>
		<?php
		$html = ob_get_clean();
		wp_send_json_success(array('html' => $html));
	}

	public function inject_loyalty_rewards_content() {
		$opts = get_option('o100_portal', array());
		$theme_color    = !empty($opts['o100_portal_theme_color']) ? $opts['o100_portal_theme_color'] : '#e11d48';
		$text_color     = !empty($opts['o100_portal_text_color']) ? $opts['o100_portal_text_color'] : '#111111';
		$btn_text_color = !empty($opts['o100_portal_btn_text_color']) ? $opts['o100_portal_btn_text_color'] : '#ffffff';
		$border_radius  = !empty($opts['o100_portal_border_radius']) ? $opts['o100_portal_border_radius'] : '12';
		
		$guest_title    = isset($opts['o100_portal_guest_title']) ? $opts['o100_portal_guest_title'] : 'Unlock free food with every order!';
		$guest_sub      = isset($opts['o100_portal_guest_subtitle']) ? $opts['o100_portal_guest_subtitle'] : 'Sign up today and start earning points towards your next meal.';
		$guest_btn      = isset($opts['o100_portal_guest_btn_text']) ? $opts['o100_portal_guest_btn_text'] : 'Join Now';
		$guest_earn     = isset($opts['o100_portal_guest_earn_text']) ? $opts['o100_portal_guest_earn_text'] : 'Ways to earn';
		$guest_redeem   = isset($opts['o100_portal_guest_redeem_text']) ? $opts['o100_portal_guest_redeem_text'] : 'Ways to redeem';
		
		$member_welcome = isset($opts['o100_portal_member_welcome']) ? $opts['o100_portal_member_welcome'] : 'Welcome back, {name}!';
		$points_format  = isset($opts['o100_portal_member_points_format']) ? $opts['o100_portal_member_points_format'] : 'You have {points} Points';
		$member_earn    = isset($opts['o100_portal_member_earn_text']) ? $opts['o100_portal_member_earn_text'] : 'Ways to earn';
		$member_redeem  = isset($opts['o100_portal_member_redeem_text']) ? $opts['o100_portal_member_redeem_text'] : 'Ways to redeem';
		$guest_referral_enabled = isset($opts['o100_portal_guest_referral_enabled']) ? $opts['o100_portal_guest_referral_enabled'] : 'yes';
		$guest_referral_title   = isset($opts['o100_portal_guest_referral_title']) ? $opts['o100_portal_guest_referral_title'] : 'Refer and Earn';
		$guest_referral_desc    = isset($opts['o100_portal_guest_referral_desc']) ? $opts['o100_portal_guest_referral_desc'] : '';
		
		$member_referral_enabled = isset($opts['o100_portal_member_referral_enabled']) ? $opts['o100_portal_member_referral_enabled'] : 'yes';
		$member_referral_title   = isset($opts['o100_portal_member_referral_title']) ? $opts['o100_portal_member_referral_title'] : 'Refer and Earn';
		$member_referral_desc    = isset($opts['o100_portal_member_referral_desc']) ? $opts['o100_portal_member_referral_desc'] : '';

		// Determine user context
		$is_member = is_user_logged_in();
		
		echo '<div class="o100-portal-rewards-ui" style="
			--theme-color: ' . esc_attr($theme_color) . ';
			--text-color: ' . esc_attr($text_color) . ';
			--btn-text-color: ' . esc_attr($btn_text_color) . ';
			--border-radius: ' . esc_attr($border_radius) . 'px;
			padding: 20px;
		">';

		// Fetch Real Loyalty Data for Cards
		$earn_cards_html = '';
		$redeem_cards_html = '';

		if ( true ) {
			// Earn Cards
			global $wpdb;
			$campaigns_table = $wpdb->prefix . 'o100_loyalty_campaigns';
			$earn_campaigns = $wpdb->get_results( "SELECT * FROM {$campaigns_table} WHERE type NOT IN ('points_conversion','punch_card','referral') AND status = 'active'" );
			$referral_campaign = $wpdb->get_row( "SELECT * FROM {$campaigns_table} WHERE type = 'referral' AND status = 'active' LIMIT 1" );
			
			// NATIVE PUNCH CARDS INJECTION
			$native_campaigns_to_inject = [];
			if ( class_exists('O100_Native_Punch_Card') ) {
				$ncs = O100_Native_Punch_Card::get_active_punch_cards();
				foreach ($ncs as $nc) {
					$mock = new stdClass();
					$mock->action_type = 'o100_punch_card';
					$mock->id = $nc->id;
					$mock->name = !empty($nc->title) ? $nc->title : (!empty($nc->name) ? $nc->name : '');
					$mock->ui_json = $nc->ui_json;
					$mock->conditions_json = $nc->conditions_json;
					$mock->reward_value = $nc->reward_value;
					$native_campaigns_to_inject[] = $mock;
				}
			}
			
			$all_earn_campaigns = !empty($earn_campaigns) && is_array($earn_campaigns) ? $earn_campaigns : [];
			// Prepend our native punch cards to the top
			$all_earn_campaigns = array_merge($native_campaigns_to_inject, $all_earn_campaigns);

			if ( !empty($all_earn_campaigns) ) {
				foreach ($all_earn_campaigns as $c) {
					$action_type = !empty($c->type) ? $c->type : (!empty($c->action_type) ? $c->action_type : '');
					if ( $action_type === 'signup' && is_user_logged_in() ) {
						continue;
					}
					
					// Skip legacy WPLoyalty punch cards to avoid duplicates if any still exist
					if ( $action_type === 'o100_punch_card' && !isset($c->ui_json) ) {
						continue; 
					}

					$icon_str = $action_type ? $action_type : 'default';
					$icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#f3f4f6; color:#6b7280;">⭐</div>';
					if ($icon_str === 'o100_punch_card') $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#f3e8ff; color:#9333ea;">🎫</div>';
					elseif (strpos($icon_str, 'point') !== false || strpos($icon_str, 'order') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fff7ed; color:#F59322;">$</div>';
					elseif (strpos($icon_str, 'signup') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fef3c7; color:#d97706;">+</div>';
					elseif (strpos($icon_str, 'birthday') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fce7f3; color:#db2777;">🎁</div>';
					elseif (strpos($icon_str, 'share') !== false || strpos($icon_str, 'social') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fff7ed; color:#F59322;">🔗</div>';
					elseif (strpos($icon_str, 'review') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#d1fae5; color:#059669;">📝</div>';
					
					$ui = json_decode($c->ui_json ?: '', true) ?: [];
					$title = !empty($c->title) ? $c->title : (!empty($c->name) ? $c->name : '');
					if ( empty($title) ) {
						$title = !empty($ui['campaign_title_discount']) ? $ui['campaign_title_discount'] : 
								 (!empty($ui['campaign_title']) ? $ui['campaign_title'] : 'Campaign');
					}
					$desc = !empty($c->description) ? wp_strip_all_tags($c->description) : '';
					$short_desc = $desc;
					$point_val = isset($ui['earn_point']) ? $ui['earn_point'] : (isset($c->point) ? $c->point : 0);
					$campaign_id = isset($c->id) ? $c->id : 0;
					
					$action_url = !empty($c->action_url) ? $c->action_url : '';
					$button_text = !empty($c->button_text) ? $c->button_text : '';
					$is_achieved = !empty($c->is_achieved) ? '1' : '0';
					$achieved_text = !empty($c->achieved_text) ? $c->achieved_text : '';
					$referral_url = !empty($c->referral_url) ? $c->referral_url : '';
					
					$current_stamps = 0;
					$max_stamps = 0;
					$stamp_icon_url = '';

					if ($action_type === 'o100_punch_card') {
						$ui = json_decode($c->ui_json, true) ?: [];
						$max_stamps = isset($ui['punch_count']) ? intval($ui['punch_count']) : 5;
						$stamp_icon_url = !empty($ui['stamp_icon_url']) ? $ui['stamp_icon_url'] : '';
						$reward_name = 'Free Reward';
						
						if (!empty($c->reward_value)) {
							$product_ids = explode(',', $c->reward_value);
							if (!empty($product_ids[0])) {
								$r_prod = wc_get_product($product_ids[0]);
								if ($r_prod) {
									$r_name = $r_prod->get_name();
									$r_name = preg_replace('/^\d+\.\s*/', '', $r_name);
									$r_name = preg_replace('/\s*\(\#\d+\)$/', '', $r_name);
									$reward_name = $r_name;
								}
							}
						}

						if (is_user_logged_in() && class_exists('O100_Native_Punch_Card')) {
							$current_stamps = O100_Native_Punch_Card::get_stamp_balance(get_current_user_id(), $campaign_id);
						}
						
						if (empty($desc) || strpos(strtolower(trim($desc)), 'punch card test') !== false || strpos(strtolower(trim($desc)), 'buy ') !== false) {
							$product_names = [];
							$conditions = json_decode($c->conditions_json, true) ?: [];
							foreach ($conditions as $cond) {
								if (isset($cond['type']) && $cond['type'] === 'products') {
									if (isset($cond['options']['value']) && is_array($cond['options']['value'])) {
										foreach ($cond['options']['value'] as $pid) {
											$p = wc_get_product($pid);
											if ($p) {
												$p_name = $p->get_name();
												// Strip out leading numbers like "107. "
												$p_name = preg_replace('/^\d+\.\s*/', '', $p_name);
												// Strip out ID hashtag if present, e.g. "Shanghai Noodles / 上海炒面 (#6668)" -> "Shanghai Noodles / 上海炒面"
												$p_name = preg_replace('/\s*\(\#\d+\)$/', '', $p_name);
												$product_names[] = '<strong>' . esc_html($p_name) . '</strong>';
											}
										}
									}
								}
							}
							$products_text = !empty($product_names) ? implode(', ', $product_names) : 'participating items';
							
							// If reward_name is still the fallback campaign name, use the participating products text
							$final_reward_name = $reward_name;
							if (strtolower(trim($reward_name)) === strtolower(trim(str_replace(' - Free Reward', '', $c->name)))) {
								$final_reward_name = $products_text;
							} else {
								$final_reward_name = '<strong>' . esc_html($reward_name) . '</strong>';
							}
							
							$desc = sprintf("Buy %d %s to get a free %s.", $max_stamps, $products_text, $final_reward_name);
						}
						
						if (empty($short_desc) || strpos(strtolower(trim($short_desc)), 'punch card test') !== false || strpos(strtolower(trim($short_desc)), 'buy ') !== false) {
							$short_desc = "1 Stamp for each participating item purchased";
						}
					}
					
					if ($action_type === 'birthday') {
						$point_rule = isset($c->point_rule) ? (is_string($c->point_rule) ? json_decode($c->point_rule, true) : (array)$c->point_rule) : [];
						if (!empty($point_rule['birthday_message'])) {
							$desc = $point_rule['birthday_message'];
						}
					}
					
					if (empty($desc)) {
						if ($action_type === 'signup') {
							$desc = "Earn " . $point_val . " points by creating an account.";
						} elseif ($action_type === 'birthday') {
							$desc = "Celebrate your special day with a reward.";
						} elseif ($action_type === 'product_review' || $action_type === 'review') {
							$desc = sprintf("Leave a review for purchased items to get %d points.", $point_val);
						} elseif (in_array($action_type, ['facebook_share', 'twitter_share', 'whatsapp_share', 'email_share'])) {
							$desc = "Share our store on social media to get rewarded.";
						} elseif ($action_type === 'followup_share') {
							$desc = "Follow our social pages to get rewarded.";
						} elseif ($point_val > 0) {
							$desc = "Earn " . $point_val . " points for this activity.";
						} else {
							$desc = !empty($ui['campaign_message']) ? wp_strip_all_tags($ui['campaign_message']) : "Unlock special rewards and offers by completing this activity.";
						}
					}
					
					if (empty($short_desc)) {
						if ($action_type === 'signup') {
							$short_desc = sprintf("Earn %d points.", $point_val);
						} elseif ($action_type === 'birthday') {
							$short_desc = "Get a special reward.";
						} elseif ($action_type === 'product_review' || $action_type === 'review') {
							$short_desc = sprintf("Get %d points.", $point_val);
						} elseif (in_array($action_type, ['facebook_share', 'twitter_share', 'whatsapp_share', 'email_share'])) {
							$short_desc = "Share and get rewarded.";
						} elseif ($action_type === 'followup_share') {
							$short_desc = "Follow us and get rewarded.";
						} elseif ($point_val > 0) {
							$short_desc = "Earn " . $point_val . " points.";
						} else {
							$short_desc = "Special reward inside.";
						}
					}
					
					$payload = htmlspecialchars(wp_json_encode(compact('title', 'short_desc', 'desc', 'point_val', 'action_url', 'button_text', 'is_achieved', 'achieved_text', 'referral_url', 'action_type', 'icon_html', 'current_stamps', 'max_stamps', 'campaign_id', 'stamp_icon_url')), ENT_QUOTES, 'UTF-8');
					
					$right_arrow = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M9 18l6-6-6-6"/></svg>';
					$earn_cards_html .= '<div class="o100-pbp-card o100-campaign-card" data-type="earn" data-payload="'.$payload.'" style="background:#fff; border:1px solid #e5e7eb; border-radius:var(--border-radius); padding:16px; margin-bottom:12px; display:flex; align-items:center; gap:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02); cursor:pointer;">'. $icon_html .'<div style="flex:1;"><h4 style="margin:0 0 4px; font-size:14px; color:var(--text-color); font-weight:700;">'.esc_html(wp_strip_all_tags($title)).'</h4><p style="margin:0; font-size:12px; color:#6b7280;">'.esc_html(wp_strip_all_tags($short_desc)).'</p></div>' . $right_arrow . '</div>';
				}
			}

			// Redeem Cards
			$redeem_count = 0;
			
			// 1. Fetch Global Conversion from Settings (New Architecture)
			$o100_settings = class_exists('O100_Loyalty_DB') ? O100_Loyalty_DB::get_settings() : [];
			$conv_pts = isset($o100_settings['conversion_points']) ? intval($o100_settings['conversion_points']) : 0;
			$conv_val = isset($o100_settings['conversion_value']) ? floatval($o100_settings['conversion_value']) : 0;
			
			if ( $conv_pts > 0 && $conv_val > 0 ) {
				$icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#f3e8ff; color:#9333ea;">🔄</div>';
				$clean_price = html_entity_decode(wp_strip_all_tags(wc_price($conv_val)), ENT_QUOTES, 'UTF-8');
				$title = $clean_price . ' Off Discount';
				$short_desc = $conv_pts . ' points';
				$desc = 'Use ' . $conv_pts . ' points to get a ' . wc_price($conv_val) . ' discount on your order.';
				$point_val = $conv_pts;
				$payload = htmlspecialchars(wp_json_encode([
					'title' => $title,
					'short_desc' => $short_desc,
					'desc' => $desc,
					'point_val' => $point_val,
					'action_type' => 'redeem',
					'icon_html' => $icon_html,
					'reward_id' => 'global_conversion'
				]), ENT_QUOTES, 'UTF-8');
				
				$right_arrow = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M9 18l6-6-6-6"/></svg>';
				$redeem_cards_html .= '<div class="o100-pbp-card o100-campaign-card" data-type="redeem" data-payload="'.$payload.'" style="background:#fff; border:1px solid #e5e7eb; border-radius:var(--border-radius); padding:16px; margin-bottom:12px; display:flex; align-items:center; gap:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02); cursor:pointer;">'. $icon_html .'<div style="flex:1;"><h4 style="margin:0 0 4px; font-size:14px; color:var(--text-color); font-weight:700;">'.esc_html(wp_strip_all_tags($title)).'</h4><p style="margin:0; font-size:12px; color:#6b7280;">'.esc_html(wp_strip_all_tags($short_desc)).'</p></div>' . $right_arrow . '</div>';
				$redeem_count++;
			}

			// 2. Fetch standard points_conversion campaigns (Legacy/Fallback)
			$redeem_rewards = $wpdb->get_results( "SELECT * FROM {$campaigns_table} WHERE type = 'points_conversion' AND status = 'active'" );
			if ( !empty($redeem_rewards) && is_array($redeem_rewards) ) {
				foreach ( $redeem_rewards as $reward ) {
					$ui_json = json_decode($reward->ui_json, true) ?: [];
					// Filter out internal campaign rewards (e.g. punch cards, birthday auto-rewards) that don't cost points
					if ( empty($ui_json['points']) || $ui_json['points'] <= 0 ) {
						continue;
					}
					$icon_str = isset($ui_json['reward_type']) ? $ui_json['reward_type'] : 'default';
					$icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#f3f4f6; color:#6b7280;">⭐</div>';
					if (strpos($icon_str, 'percent') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#dcfce7; color:#16a34a;">%</div>';
					elseif (strpos($icon_str, 'fixed') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fef08a; color:#ca8a04;">$</div>';
					elseif (strpos($icon_str, 'shipping') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#e0f2fe; color:#0284c7;">🚚</div>';
					elseif (strpos($icon_str, 'product') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#fee2e2; color:#b91c1c;">🎁</div>';
					elseif (strpos($icon_str, 'points_conversion') !== false) $icon_html = '<div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; background:#f3e8ff; color:#9333ea;">🔄</div>';
					
					$reward_type = $ui_json['conversion_reward_type'] ?? 'discount';
					$disc_val = floatval($ui_json['conversion_value'] ?? 1);
					$title = ($reward_type === 'free_item') ? 'Free ' . get_the_title($ui_json['freeitem_product'] ?? 0) : wc_price($disc_val) . ' Off Discount';
					if (!empty($reward->title)) $title = $reward->title;
					else if (!empty($reward->name)) $title = $reward->name;
					
					$short_desc = isset($ui_json['points']) ? $ui_json['points'] . ' points' : '';
					$desc = wp_strip_all_tags($reward->description);
					$point_val = isset($ui_json['points']) ? $ui_json['points'] : 0;
					$action_url = '';
					$button_text = '';
					$is_achieved = '0';
					$achieved_text = '';
					$referral_url = '';
					$action_type = 'redeem';
					$reward_id = isset($reward->id) ? $reward->id : 0;
					
					$payload = htmlspecialchars(wp_json_encode(compact('title', 'short_desc', 'desc', 'point_val', 'action_url', 'button_text', 'is_achieved', 'achieved_text', 'referral_url', 'action_type', 'icon_html', 'reward_id')), ENT_QUOTES, 'UTF-8');
					
					$right_arrow = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M9 18l6-6-6-6"/></svg>';
					$redeem_cards_html .= '<div class="o100-pbp-card o100-campaign-card" data-type="redeem" data-payload="'.$payload.'" style="background:#fff; border:1px solid #e5e7eb; border-radius:var(--border-radius); padding:16px; margin-bottom:12px; display:flex; align-items:center; gap:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02); cursor:pointer;">'. $icon_html .'<div style="flex:1;"><h4 style="margin:0 0 4px; font-size:14px; color:var(--text-color); font-weight:700;">'.esc_html(wp_strip_all_tags($title)).'</h4><p style="margin:0; font-size:12px; color:#6b7280;">'.esc_html(wp_strip_all_tags($short_desc)).'</p></div>' . $right_arrow . '</div>';
					$redeem_count++;
				}
			}
		}

		if ( empty($earn_cards_html) ) {
			$earn_cards_html = '<div style="text-align:center; padding:24px 0; color:#9ca3af; font-size:13px;">No earn campaigns available yet.</div>';
		}
		if ( empty($redeem_cards_html) ) {
			$redeem_cards_html = '<div style="text-align:center; padding:24px 0; color:#9ca3af; font-size:13px;">No rewards available yet.</div>';
		}

		echo '<style>
			.o100-sc-inner-tabs { display:flex; background:#f3f4f6; border-radius:24px 24px 0 0; position:relative; z-index:2; margin-bottom:0; padding-top:6px; }
			.o100-sc-inner-tab { flex:1; text-align:center; padding:12px 12px; font-size:14px; font-weight:700; color:#6b7280; cursor:pointer; background:transparent; border-radius:20px 20px 0 0; position:relative; z-index:1; transition:all 0.2s ease; display:flex; align-items:center; justify-content:center; gap:6px; user-select:none; }
			.o100-sc-inner-tab.active { background:#fff; color:var(--text-color); z-index:3; padding-top: 16px; margin-top: -4px; }
			.o100-sc-inner-tab:first-child.active::after { content:""; position:absolute; bottom:0; right:-24px; width:24px; height:24px; background:radial-gradient(circle at top right, transparent 24px, #fff 24.5px); pointer-events:none; }
			.o100-sc-inner-tab:last-child.active::before { content:""; position:absolute; bottom:0; left:-24px; width:24px; height:24px; background:radial-gradient(circle at top left, transparent 24px, #fff 24.5px); pointer-events:none; }
			.o100-sc-tab-content-wrapper { background:#fff; border-radius:0 0 20px 20px; padding:20px; position:relative; z-index:1; margin-bottom:24px; box-shadow:0 4px 6px rgba(0,0,0,0.02); }
		</style>
		<script>
		window.o100ActivePbpView = "none";
		
		jQuery(document).on("click", ".o100-campaign-card", function() {
			var type = jQuery(this).attr("data-type");
			var p = JSON.parse(jQuery(this).attr("data-payload"));
			
			var wrapper = jQuery(this).closest(".o100-sc-tab-content-wrapper");
			var tabs = wrapper.prev(".o100-sc-inner-tabs");
			
			wrapper.hide();
			if(tabs.length) tabs.hide();
			
			var detailView = document.getElementById("o100-sc-detail-view");
			wrapper.after(detailView);
			
			window.o100ActiveWrapper = wrapper;
			window.o100ActiveTabs = tabs;
			
			if (jQuery(this).closest(".o100-pbp-guest-view").length > 0) {
				window.o100ActivePbpView = "guest";
			} else {
				window.o100ActivePbpView = "member";
			}
			
			document.getElementById("o100-detail-header-title").innerText = (type === "earn" ? "Earn" : "Redeem");
			document.getElementById("o100-sc-detail-view").style.display = "flex";
			document.getElementById("o100-detail-icon").innerHTML = p.icon_html;
			document.getElementById("o100-detail-title").innerText = p.title;
			document.getElementById("o100-detail-short-desc").innerHTML = p.short_desc || "";
			
			if (p.desc && p.desc.trim() !== "" && p.desc.trim() !== (p.short_desc || "").trim()) {
				document.getElementById("o100-detail-desc").innerHTML = p.desc;
				document.getElementById("o100-detail-desc").style.display = "block";
			} else {
				document.getElementById("o100-detail-desc").style.display = "none";
			}
			
			var btnContainer = document.getElementById("o100-detail-action-container");
			var actionHtml = "";
			
			if (type === "earn") {
				if (p.is_achieved === "1") {
					actionHtml = "<button disabled style=\"width:100%; background:#d1d5db; color:#4b5563; border:none; border-radius:var(--border-radius); padding:12px; font-size:14px; font-weight:700; cursor:not-allowed;\">" + (p.achieved_text || "Earned") + "</button>";
				} else if (p.action_type === "o100_punch_card") {
					var max = parseInt(p.max_stamps) || 5;
					var cur = parseInt(p.current_stamps) || 0;
					var cUrl = p.stamp_icon_url || "";
					var stampsHtml = "<div style=\"display:flex; gap:8px; justify-content:center; margin: 12px 0 20px 0; flex-wrap:wrap;\">";
					for (var i = 0; i < max; i++) {
						var iconTag = cUrl ? "<img src=\""+cUrl+"\" style=\"width:100%; height:100%; object-fit:contain; border-radius:50%;\" alt=\"Stamp\" />" : "★";
						if (i < cur) {
							stampsHtml += "<div style=\"width:36px;height:36px;border-radius:50%;background:"+ (cUrl ? "transparent" : "#10b981") + ";display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;box-shadow:0 2px 4px rgba(16,185,129,0.3);\">" + iconTag + "</div>";
						} else {
							stampsHtml += "<div style=\"width:36px;height:36px;border-radius:50%;background:#f1f5f9;border:1px dashed #cbd5e1;display:flex;align-items:center;justify-content:center;color:#cbd5e1;font-size:18px;\">★</div>";
						}
					}
					stampsHtml += "</div>";
					
					if (cur >= max) {
						if (window.o100ActivePbpView === "guest") {
							actionHtml = stampsHtml + "<button onclick=\"o100TriggerGlobalLogin(event)\" style=\"display:block; width:100%; background:var(--theme-color); color:var(--btn-text-color); border:none; border-radius:var(--border-radius); padding:12px; font-size:14px; font-weight:700; cursor:pointer; text-decoration:none; box-shadow:0 4px 6px rgba(0,0,0,0.1);\">Login to Claim Reward</button>";
						} else {
							actionHtml = stampsHtml + "<button onclick=\"o100RedeemPunchCard("+p.campaign_id+", this)\" style=\"display:block; width:100%; background:#10b981; color:#fff; border:none; border-radius:var(--border-radius); padding:12px; font-size:14px; font-weight:700; cursor:pointer; text-decoration:none; box-shadow:0 4px 6px rgba(0,0,0,0.1);\">Claim Free Reward</button>";
						}
					} else {
						actionHtml = stampsHtml;
					}
				} else if (p.action_type === "referral" && p.referral_url) {
					actionHtml = "<div style=\"margin-top:12px;\"><div style=\"font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text-color);\">Share your referral link:</div><div style=\"display:flex; gap:0; border-radius:6px; overflow:hidden; border:1px solid #c7d2fe; margin-bottom:12px;\"><input type=\"text\" value=\""+p.referral_url+"\" readonly style=\"flex:1; border:none; padding:8px 12px; font-size:13px; background:#fff; color:#F59322; outline:none; margin:0; width:100%;\"><button style=\"border:none; background:#F59322; color:#fff; font-weight:600; padding:0 12px; cursor:pointer;\" onclick=\"navigator.clipboard.writeText(\'"+p.referral_url+"\');\">Copy</button></div></div>";
				} else if (p.action_type === "signup") {
					if (window.o100ActivePbpView === "guest") {
						actionHtml = "<button onclick=\"o100TriggerGlobalLogin(event)\" style=\"width:100%; background:var(--theme-color); color:var(--btn-text-color); border:none; border-radius:var(--border-radius); padding:12px; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,0.1);\">Register Now</button>";
					}
				} else if (p.action_type === "birthday") {
					if (window.o100ActivePbpView === "guest") {
						actionHtml = "<button onclick=\"o100TriggerGlobalLogin(event)\" style=\"width:100%; background:var(--theme-color); color:var(--btn-text-color); border:none; border-radius:var(--border-radius); padding:12px; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,0.1);\">Set Birthday in Account</button>";
					} else {
						actionHtml = "<div style=\"display:flex;gap:8px;margin-top:12px;\"><input type=\"date\" id=\"o100_birthday_input\" style=\"flex:1; border:1px solid #cbd5e1; border-radius:6px; padding:8px 12px; font-size:14px; outline:none; color:var(--text-color);\"><button onclick=\"o100SaveBirthday(this)\" style=\"background:var(--theme-color); color:var(--btn-text-color); border:none; border-radius:6px; padding:8px 16px; font-weight:700; cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,0.1); transition:background 0.2s;\">Save</button></div>";
					}
				} else if (p.action_type === "product_review" || p.action_type === "review") {
					if (window.o100ActivePbpView === "guest") {
						actionHtml = "<button onclick=\"o100TriggerGlobalLogin(event)\" style=\"width:100%; background:var(--theme-color); color:var(--btn-text-color); border:none; border-radius:var(--border-radius); padding:12px; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,0.1);\">Login to Write a Review</button>";
					} else {
						actionHtml = "<a href=\"'. esc_url(wc_get_endpoint_url('orders', '', wc_get_page_permalink('myaccount'))) .'\" style=\"display:block; text-align:center; width:100%; background:var(--theme-color); color:var(--btn-text-color); border:none; border-radius:var(--border-radius); padding:12px; font-size:14px; font-weight:700; cursor:pointer; text-decoration:none; box-shadow:0 4px 6px rgba(0,0,0,0.1);\">Write a Review</a>";
					}
				} else if (p.action_url) {
					actionHtml = "<a href=\""+p.action_url+"\" target=\"_blank\" style=\"display:block; text-align:center; width:100%; background:var(--theme-color); color:var(--btn-text-color); border:none; border-radius:var(--border-radius); padding:12px; font-size:14px; font-weight:700; cursor:pointer; text-decoration:none; box-shadow:0 4px 6px rgba(0,0,0,0.1);\">" + (p.button_text || "Complete Activity") + "</a>";
				}
			} else {
				if (window.o100ActivePbpView === "guest") {
					actionHtml = "<button onclick=\"o100TriggerGlobalLogin(event)\" style=\"width:100%; background:var(--theme-color); color:var(--btn-text-color); border:none; border-radius:var(--border-radius); padding:12px; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,0.1);\">Sign in to Redeem</button>";
				} else {
					if (p.reward_id === \'global_conversion\') {
						actionHtml = "<a href=\"/checkout/\" style=\"display:block; text-align:center; width:100%; background:var(--theme-color); color:var(--btn-text-color); border:none; border-radius:var(--border-radius); padding:12px; font-size:14px; font-weight:700; cursor:pointer; text-decoration:none; box-shadow:0 4px 6px rgba(0,0,0,0.1);\">Redeem at Checkout</a>";
					} else {
						actionHtml = "<button onclick=\"o100RedeemReward(\'"+p.reward_id+"\', "+p.point_val+", this)\" style=\"width:100%; background:var(--theme-color); color:var(--btn-text-color); border:none; border-radius:var(--border-radius); padding:12px; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,0.1);\">Redeem for " + p.point_val + " Points</button>";
					}
				}
			}
			
			btnContainer.innerHTML = actionHtml;
		});
		
		function o100CloseDetail() {
			document.getElementById("o100-sc-detail-view").style.display = "none";
			if (window.o100ActiveWrapper) {
				window.o100ActiveWrapper.show();
				if (window.o100ActiveTabs) window.o100ActiveTabs.show();
			} else {
				// Fallback
				if (window.o100ActivePbpView === "guest") {
					var gv = document.querySelector(".o100-pbp-guest-view");
					if(gv) gv.style.display = "block";
				} else if (window.o100ActivePbpView === "member") {
					var mv = document.querySelector(".o100-pbp-member-view");
					if(mv) mv.style.display = "block";
				}
			}
			window.o100ActivePbpView = "none";
		}
		
		function o100TriggerGlobalLogin(e) {
			if (e) e.preventDefault();
			o100CloseDetail();
			o100CloseSideCart();
			if (jQuery("#wfa-login-modal").length > 0) {
				jQuery("#wfa-login-modal").fadeIn();
			} else {
				window.location.href = "'. esc_url(wc_get_page_permalink('myaccount')) .'";
			}
		}

		function o100SaveBirthday(btn) {
			var dateVal = document.getElementById("o100_birthday_input").value;
			if (!dateVal) return;
			var origText = btn.innerText;
			btn.innerText = "Saving...";
			jQuery.post("'. admin_url('admin-ajax.php') .'", {
				action: "o100_save_birthday",
				birth_date: dateVal
			}, function(res) {
				if (res.success || (res.data && res.data.message)) {
					btn.innerText = "Saved!";
					btn.style.background = "#10b981";
				} else {
					btn.innerText = "Error";
				}
				setTimeout(function() { 
					btn.innerText = origText; 
					btn.style.background = "var(--theme-color)"; 
				}, 2000);
			}).fail(function() {
				btn.innerText = "Error";
				setTimeout(function() { 
					btn.innerText = origText; 
					btn.style.background = "var(--theme-color)"; 
				}, 2000);
			});
		}

		window.o100ShowToast = function(message, type = "success") {
			var toast = document.createElement("div");
			toast.style.position = "fixed";
			toast.style.bottom = "20px";
			toast.style.right = "20px";
			toast.style.background = type === "success" ? "#10b981" : "#ef4444";
			toast.style.color = "#fff";
			toast.style.padding = "12px 24px";
			toast.style.borderRadius = "8px";
			toast.style.boxShadow = "0 4px 6px rgba(0,0,0,0.1)";
			toast.style.zIndex = "999999";
			toast.style.fontFamily = "system-ui, -apple-system, sans-serif";
			toast.style.fontSize = "14px";
			toast.style.fontWeight = "500";
			toast.style.transition = "all 0.3s ease-in-out";
			toast.style.transform = "translateY(100px)";
			toast.style.opacity = "0";
			toast.innerText = message;
			document.body.appendChild(toast);
			
			setTimeout(function() {
				toast.style.transform = "translateY(0)";
				toast.style.opacity = "1";
			}, 10);
			
			setTimeout(function() {
				toast.style.transform = "translateY(100px)";
				toast.style.opacity = "0";
				setTimeout(function() { toast.remove(); }, 300);
			}, 3000);
		};

		function o100RedeemPunchCard(campaignId, btn) {
			if (!campaignId) return;
			if(!btn && typeof event !== "undefined" && event.currentTarget) {
				btn = event.currentTarget;
			}
			var origText = "";
			if (btn) {
				origText = btn.innerText;
				btn.innerText = "Processing...";
				btn.style.opacity = "0.7";
				btn.style.pointerEvents = "none";
			}
			jQuery.post("'. admin_url('admin-ajax.php') .'", {
				action: "o100_redeem_punch_card",
				campaign_id: campaignId
			}, function(res) {
				if (res.success) {
					btn.innerText = "Claimed!";
					btn.style.background = "#10b981";
					if (typeof o100ShowToast === "function") {
						o100ShowToast(res.data.message);
					} else {
						alert(res.data.message);
					}
					setTimeout(function() { location.reload(); }, 2000);
				} else {
					btn.innerText = "Failed";
					if (typeof o100ShowToast === "function") {
						o100ShowToast(res.data ? res.data.message : "Error claiming reward.", "error");
					} else {
						alert(res.data ? res.data.message : "Error claiming reward.");
					}
					setTimeout(function() {
						btn.innerText = origText;
						btn.style.opacity = "1";
						btn.style.pointerEvents = "auto";
					}, 2000);
				}
			}).fail(function() {
				btn.innerText = "Error";
				setTimeout(function() {
					btn.innerText = origText;
					btn.style.opacity = "1";
					btn.style.pointerEvents = "auto";
				}, 2000);
			});
		}
		
		function o100RedeemReward(rewardId, points, btn) {
			var origText = btn.innerText;
			btn.innerText = "Redeeming...";
			btn.style.opacity = "0.7";
			btn.style.pointerEvents = "none";
			jQuery.post("'. admin_url('admin-ajax.php') .'", {
				action: "o100_native_redeem_points",
				campaign_id: rewardId,
				points: points,
				nonce: "'. wp_create_nonce('o100_loyalty') .'"
			}, function(res) {
				if(res.success) {
					btn.innerText = "Reward Applied!";
					btn.style.background = "#10b981";
					setTimeout(function(){
						jQuery(document.body).trigger("wc_fragment_refresh");
					}, 500);
				} else {
					if (typeof o100ShowToast === "function") {
						o100ShowToast(res.data && res.data.message ? res.data.message : "Failed to redeem.", "error");
					} else {
						alert(res.data && res.data.message ? res.data.message : "Failed to redeem.");
					}
					btn.innerText = origText;
					btn.style.opacity = "1";
					btn.style.pointerEvents = "auto";
				}
			});
		}
		</script>';

		if (!function_exists('o100_parse_referral_desc')) {
			function o100_parse_referral_desc($desc, $campaign) {
				if (empty($campaign)) return $desc;
				$old_default = 'Share the love of our restaurant! Your friend gets $5 off their first order of $50+, and you get rewarded when they finish their meal!';
				$point_rule = [];
				if (!empty($campaign->earn_config)) $point_rule = is_string($campaign->earn_config) ? json_decode($campaign->earn_config, true) : (array)$campaign->earn_config;
				elseif (!empty($campaign->point_rule)) $point_rule = is_string($campaign->point_rule) ? json_decode($campaign->point_rule, true) : (array)$campaign->point_rule;
				
				$get_txt = function($type, $amt, $cpn) {
					if ($type === 'point') return $amt . ' Points';
					if ($type === 'coupon' && !empty($cpn)) {
						if (strpos($cpn, 'promo_') === 0 && class_exists('O100_Promotions_DB')) {
							$promo = O100_Promotions_DB::get((int)str_replace('promo_', '', $cpn));
							if ($promo) {
								$cfg = json_decode($promo['action_config']??'{}', true);
								return ($cfg['discount_type']??'') === 'percentage' ? ($cfg['discount_value']??'').'% off' : '$'.($cfg['discount_value']??'').' off';
							}
						}
						if (strpos($cpn, 'wc_') === 0) { $wc = get_post((int)str_replace('wc_', '', $cpn)); if ($wc) return $wc->post_title; }
						if (strpos($cpn, 'REFERRAL_') === 0) {
							global $wpdb;
							$coupon = $wpdb->get_row($wpdb->prepare("SELECT post_title FROM {$wpdb->posts} WHERE post_type='shop_coupon' AND post_name=%s LIMIT 1", strtolower($cpn)));
							if ($coupon) return 'the ' . $coupon->post_title . ' coupon';
							return $cpn;
						}
						return 'a special discount';
					}
					return 'a reward';
				};
				
				$fri_type = $point_rule['friend']['campaign_type'] ?? ($point_rule['friend_type'] ?? 'point');
				$fri_amt  = $point_rule['friend']['earn_point'] ?? ($point_rule['friend_amount'] ?? 0);
				$fri_cpn  = $point_rule['friend']['earn_reward'] ?? ($point_rule['friend_coupon'] ?? '');
				
				$adv_type = $point_rule['advocate']['campaign_type'] ?? ($point_rule['advocate_type'] ?? 'point');
				$adv_amt  = $point_rule['advocate']['earn_point'] ?? ($point_rule['advocate_amount'] ?? 0);
				$adv_cpn  = $point_rule['advocate']['earn_reward'] ?? ($point_rule['advocate_coupon'] ?? '');

				$fri_txt = $get_txt($fri_type, $fri_amt, $fri_cpn);
				$adv_txt = $get_txt($adv_type, $adv_amt, $adv_cpn);
				if (trim($desc) === $old_default) {
					return "Share the love of our restaurant! Your friend gets {$fri_txt} on their first order, and you get {$adv_txt} when they finish their meal!";
				}
				return str_replace(['{friend_reward}', '{advocate_reward}'], [$fri_txt, $adv_txt], $desc);
			}
		}

		if ( ! $is_member ) {
			// GUEST VIEW
			?>
			<div class="o100-pbp-guest-view">
				<div class="o100-pbp-banner-box" style="background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; text-align:center; margin-bottom:24px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
					<h3 style="margin:0 0 8px; font-size:20px; color:var(--text-color); font-weight:800; line-height:1.2;"><?php echo esc_html($guest_title); ?></h3>
					<p style="margin:0 0 16px; font-size:13px; color:#6b7280; line-height:1.4;"><?php echo esc_html($guest_sub); ?></p>
					<a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="o100-pbp-btn" style="display:block; text-decoration:none; background:var(--theme-color); color:var(--btn-text-color); padding:12px; border-radius:var(--border-radius); font-weight:600; font-size:14px; text-align:center;"><?php echo esc_html($guest_btn); ?></a>
					<div style="font-size:12px; margin-top:12px; color:#6b7280; text-align:center;">Already have an account? <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" style="color:var(--theme-color); font-weight:600;">Sign in</a></div>
				</div>
				
				<!-- GUEST FOLDER TABS -->
				<div class="o100-sc-inner-tabs">
					<div class="o100-sc-inner-tab active" onclick="document.getElementById('sc-guest-earn-cards').style.display='block'; document.getElementById('sc-guest-redeem-cards').style.display='none'; this.parentElement.children[0].classList.add('active'); this.parentElement.children[1].classList.remove('active');"><?php echo esc_html($guest_earn); ?></div>
					<div class="o100-sc-inner-tab" onclick="document.getElementById('sc-guest-earn-cards').style.display='none'; document.getElementById('sc-guest-redeem-cards').style.display='block'; this.parentElement.children[0].classList.remove('active'); this.parentElement.children[1].classList.add('active');"><?php echo esc_html($guest_redeem); ?></div>
				</div>
				
				<div class="o100-sc-tab-content-wrapper">
					<div id="sc-guest-earn-cards">
						<?php echo $earn_cards_html; ?>
					</div>
					
					<div id="sc-guest-redeem-cards" style="display:none;">
						<?php echo $redeem_cards_html; ?>
					</div>
				</div>
				
				<?php if ( !empty($referral_campaign) && $guest_referral_enabled === 'yes' ) :
					$ref_desc = !empty($guest_referral_desc) ? $guest_referral_desc : (strip_tags($referral_campaign->description) ?: 'Refer your friends and earn rewards. Your friend can get a reward as well!');
					if (function_exists('o100_parse_referral_desc')) $ref_desc = o100_parse_referral_desc($ref_desc, $referral_campaign);
				?>
				<div class="o100-sc-referral-block" style="margin-top:24px; padding:20px; border-radius:var(--border-radius); background:#fff7ed; color:#3730a3; text-align:center;">
					<h4 style="margin:0 0 8px; font-size:16px; font-weight:800; color:#9a5c06;"><?php echo esc_html($guest_referral_title); ?></h4>
					<p style="margin:0; font-size:13px; line-height:1.4; color:#d97b06;"><?php echo esc_html($ref_desc); ?></p>
					<div style="margin-top:12px; font-size:12px;"><a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" style="color:#F59322; font-weight:600;">Sign in</a> to get your link</div>
				</div>
				<?php endif; ?>
			</div>
			<?php
		} else {
			// MEMBER VIEW
			$user = wp_get_current_user();
			$display_name = $user->display_name;
			$points = 0; // Temp placeholder until real integration
			$level_html = '';
			if ( class_exists('O100_Loyalty_DB') ) {
				$account = \O100_Loyalty_DB::get_account_by_user( $user->ID );
				$points = $account ? (int) $account->points_balance : 0;
			}

			$welcome_msg = str_replace('{name}', $display_name, $member_welcome);
			$points_msg  = str_replace('{points}', $points, $points_format);
			$referral_url = site_url('/?wlr=' . $user->ID); // Temp placeholder pattern
			
			?>
			<div class="o100-pbp-member-view">
				<div class="o100-pbp-banner-box" style="background: linear-gradient(135deg, var(--theme-color), #0f172a); border-radius: 20px; padding: 24px; text-align:left; margin-bottom:24px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); position:relative; overflow:hidden;">
					<div style="font-size:14px; font-weight:600; margin-bottom:4px; color:rgba(255,255,255,0.8);"><?php echo esc_html($welcome_msg); ?></div>
					<div style="font-size:32px; font-weight:800; color:#fff;" id="live-member-points"><?php echo esc_html($points_msg); ?></div>
					<?php echo $level_html; ?>
				</div>
				
				<!-- MEMBER FOLDER TABS -->
				<div class="o100-sc-inner-tabs">
					<div class="o100-sc-inner-tab active" onclick="document.getElementById('sc-member-earn-cards').style.display='block'; document.getElementById('sc-member-redeem-cards').style.display='none'; this.parentElement.children[0].classList.add('active'); this.parentElement.children[1].classList.remove('active');">
						<?php echo esc_html($member_earn); ?>
					</div>
					<div class="o100-sc-inner-tab" onclick="document.getElementById('sc-member-earn-cards').style.display='none'; document.getElementById('sc-member-redeem-cards').style.display='block'; this.parentElement.children[0].classList.remove('active'); this.parentElement.children[1].classList.add('active');">
						<?php echo esc_html($member_redeem); ?> <?php if ($redeem_count > 0) { ?><span style="background:#ef4444; color:#fff; font-size:10px; font-weight:800; padding:2px 6px; border-radius:10px; line-height:1; position:absolute; top:-6px; right:12px;"><?php echo $redeem_count; ?></span><?php } ?>
					</div>
				</div>
				
				<div class="o100-sc-tab-content-wrapper">
					<div id="sc-member-earn-cards">
						<?php echo $earn_cards_html; ?>
					</div>
					
					<div id="sc-member-redeem-cards" style="display:none;">
						<?php echo $redeem_cards_html; ?>
					</div>
				</div>
				
				<?php if ( !empty($referral_campaign) && $member_referral_enabled === 'yes' ) :
					$ref_desc = !empty($member_referral_desc) ? $member_referral_desc : (strip_tags($referral_campaign->description) ?: 'Refer your friends and earn rewards. Your friend can get a reward as well!');
					if (function_exists('o100_parse_referral_desc')) $ref_desc = o100_parse_referral_desc($ref_desc, $referral_campaign);
					$referral_url = site_url('/?o100_ref=' . $user->ID);
				?>
				<div class="o100-sc-referral-block" style="background:#fff7ed; border-radius:12px; padding:16px; margin-top:20px; text-align:center;">
					<div style="font-size:16px; font-weight:800; color:#9a5c06; margin-bottom:4px;"><?php echo esc_html($member_referral_title); ?></div>
					<div style="font-size:13px; color:#d97b06; line-height:1.4; margin-bottom:12px;"><?php echo esc_html($ref_desc); ?></div>
					<div style="display:flex; gap:0; border-radius:6px; overflow:hidden; border:1px solid #c7d2fe; margin-bottom:12px;">
						<input type="text" value="<?php echo esc_attr($referral_url); ?>" readonly style="flex:1; border:none; padding:8px 12px; font-size:13px; background:#fff; color:#F59322; outline:none; margin:0; width:100%;">
						<button style="border:none; background:#F59322; color:#fff; font-weight:600; padding:0 12px; cursor:pointer;" onclick="navigator.clipboard.writeText('<?php echo esc_js($referral_url); ?>');">Copy</button>
					</div>
					<div style="display:flex; gap:12px; justify-content:center;">
						<?php
						$wll_content = get_option('wll_launcher_content_settings', []);
						$enabled_socials = isset($wll_content['content']['member']['referrals']['channels']) && is_array($wll_content['content']['member']['referrals']['channels']) ? $wll_content['content']['member']['referrals']['channels'] : ['whatsapp', 'email', 'facebook', 'twitter'];
						?>
						<?php if (in_array('whatsapp', $enabled_socials)) : ?>
						<a href="https://api.whatsapp.com/send?text=<?php echo urlencode($referral_url); ?>" target="_blank" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:#F59322; text-decoration:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg></a>
						<?php endif; ?>
						<?php if (in_array('email', $enabled_socials)) : ?>
						<a href="mailto:?body=<?php echo urlencode($referral_url); ?>" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:#F59322; text-decoration:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></a>
						<?php endif; ?>
						<?php if (in_array('facebook', $enabled_socials)) : ?>
						<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_url); ?>" target="_blank" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:#F59322; text-decoration:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></a>
						<?php endif; ?>
						<?php if (in_array('twitter', $enabled_socials) || in_array('x', $enabled_socials)) : ?>
						<a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($referral_url); ?>" target="_blank" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:#F59322; text-decoration:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path></svg></a>
						<?php endif; ?>
						<?php if (in_array('linkedin', $enabled_socials)) : ?>
						<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($referral_url); ?>" target="_blank" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:#F59322; text-decoration:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg></a>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>
			</div>
			<?php
		}
		
		?>
		<!-- DETAIL VIEW PANE -->
		<div id="o100-sc-detail-view" style="display:none; flex-direction:column; background:#f9fafb; border-radius:20px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.05); margin-bottom:24px; border:1px solid #e5e7eb;">
			<div style="padding:16px 20px; background:#d92728; color:#fff; display:flex; align-items:center; justify-content:space-between;">
				<button onclick="o100CloseDetail()" style="background:transparent; border:none; color:#fff; font-weight:600; font-size:16px; cursor:pointer; padding:0; display:flex; align-items:center; gap:8px;">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
					<span id="o100-detail-header-title">Earn</span>
				</button>
				<button onclick="o100CloseDetail()" style="background:transparent; border:none; color:#fff; cursor:pointer; padding:0; display:flex; align-items:center;">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
				</button>
			</div>
			<div style="padding:24px 20px; background:#fff;">
				<div style="display:flex; align-items:flex-start; gap:16px; margin-bottom:24px;">
					<div id="o100-detail-icon" style="flex-shrink:0;"></div>
					<div>
						<h3 id="o100-detail-title" style="font-size:16px; font-weight:800; color:var(--text-color); margin:0 0 4px;"></h3>
						<p id="o100-detail-short-desc" style="font-size:14px; color:#6b7280; margin:0;"></p>
					</div>
				</div>
				<div id="o100-detail-action-container" style="text-align:center; margin-bottom:24px;"></div>
				<p id="o100-detail-desc" style="font-size:14px; color:#6b7280; line-height:1.6; margin:0; text-align:center;"></p>
			</div>
		</div>
		<?php
		
		echo '</div>';
	}

	public function ajax_update_qty() {
		$key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
		$qty = isset($_POST['qty']) ? absint($_POST['qty']) : 1;
		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if ( WC()->cart ) {
			if ( $key ) {
				WC()->cart->set_quantity( $key, $qty );
			} elseif ( $product_id ) {
				WC()->cart->add_to_cart( $product_id, $qty );
			}
			WC()->cart->calculate_totals();
		}
		
		$res = array(
			'subtotal'       => WC()->cart->get_cart_subtotal(),
			'cart_count'     => WC()->cart->get_cart_contents_count(),
			'cart_html'      => $this->build_cart_body(),
			'total'          => WC()->cart->get_total(),
			'discount_total' => WC()->cart->get_cart_discount_total(),
			'shipping_total' => WC()->cart->get_shipping_total()
		);
		
		
		
		wp_send_json_success( $res );
	}

	private function render_cart_item( $cart_item, $cart_item_key ) {
		$product   = $cart_item['data'];
		$qty       = $cart_item['quantity'];
		$thumb     = $product->get_image( array(64, 64), array('class' => 'o100-sc-thumb') );
		$title     = $product->get_name();
		$remove_url = wc_get_cart_remove_url( $cart_item_key );

		// Calculate base line total according to tax settings
		$line_total_amount = 0;
		if ( isset( $cart_item['line_subtotal'] ) ) {
			if ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_cart' ) ) {
				$line_total_amount = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'];
			} else {
				$line_total_amount = $cart_item['line_subtotal'];
			}
		} else {
			$line_total_amount = $product->get_price() * $qty;
		}

		// Apply visual discount if the engine injected it
		$item_discount = isset( $cart_item['o100_promo_discount'] ) ? floatval( $cart_item['o100_promo_discount'] ) : 0;
		$badge_text = isset( $cart_item['o100_promo_badge'] ) ? $cart_item['o100_promo_badge'] : '';

		if ( $item_discount > 0 ) {
			$discounted_amount = max( 0, $line_total_amount - $item_discount );
			$price_html = '<span style="color:#e11d48; font-weight:700;">' . wc_price( $discounted_amount ) . '</span> <del style="color:#9ca3af; font-size:12px; margin-left:4px;">' . wc_price( $line_total_amount ) . '</del>';
		} else {
			$price_html = wc_price( $line_total_amount );
		}

		// Collect addon/option labels from cart item meta
		$options = array();
		if ( ! empty( $cart_item['o100_addons'] ) && is_array( $cart_item['o100_addons'] ) ) {
			foreach ( $cart_item['o100_addons'] as $addon ) {
				$label = isset($addon['name']) ? $addon['name'] : '';
				$val   = isset($addon['value']) ? $addon['value'] : '';
				if ( $val ) $options[] = $val;
			}
		}
		// Fallback: WC product addons format
		if ( empty($options) && ! empty( $cart_item['addons'] ) && is_array( $cart_item['addons'] ) ) {
			foreach ( $cart_item['addons'] as $addon ) {
				if ( ! empty($addon['value']) ) $options[] = $addon['value'];
			}
		}
		// Variation attributes
		if ( ! empty( $cart_item['variation'] ) ) {
			foreach ( $cart_item['variation'] as $attr => $val ) {
				if ( $val ) $options[] = ucfirst( str_replace( '-', ' ', $val ) );
			}
		}
		$options_html = ! empty($options) ? '<div class="o100-sc-options">' . esc_html( implode(', ', array_slice($options, 0, 4)) ) . '</div>' : '';

		ob_start(); ?>
		<div class="o100-sc-item" data-key="<?php echo esc_attr($cart_item_key); ?>" data-remove-url="<?php echo esc_url($remove_url); ?>">
			<div class="o100-sc-item-thumb"><?php echo $thumb; ?></div>
			<div class="o100-sc-item-info">
				<div class="o100-sc-item-title"><?php echo esc_html($title); ?></div>
				<?php echo $options_html; ?>
				<div class="o100-sc-item-price"><?php echo $price_html; ?></div>
				<?php if ( $item_discount > 0 && ! empty( $badge_text ) ) : ?>
					<div class="o100-sc-item-badge" style="margin-top: 4px;">
						<span style="display:inline-block; background-color:#fee2e2; color:#e11d48; padding:2px 8px; font-size:11px; font-weight:600; border-radius:12px;">
							<?php echo esc_html( $badge_text ); ?>
						</span>
					</div>
				<?php endif; ?>
			</div>
			<div class="o100-sc-qty">
				<button class="o100-sc-qty-btn o100-sc-minus <?php echo $qty <= 1 ? 'is-trash' : ''; ?>" data-key="<?php echo esc_attr($cart_item_key); ?>">
					<span class="qty-minus-text">-</span>
					<span class="qty-trash-icon"></span>
				</button>
				<span class="o100-sc-qty-val"><?php echo $qty; ?></span>
				<button class="o100-sc-qty-btn o100-sc-plus" data-key="<?php echo esc_attr($cart_item_key); ?>">+</button>
			</div>
		</div>
		<?php return ob_get_clean();
	}

	/**
	 * Build upsell carousel HTML
	 */
	private function render_upsells() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) return '';

		$cart_ids = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$cart_ids[] = $item['product_id'];
		}

		$upsell_ids = array();
		foreach ( $cart_ids as $pid ) {
			$p = wc_get_product($pid);
			if ( $p ) {
				$upsell_ids = array_merge( $upsell_ids, $p->get_cross_sell_ids(), $p->get_upsell_ids() );
			}
		}
		$upsell_ids = array_unique( array_diff( $upsell_ids, $cart_ids ) );

		// Fallback 1: Featured Products
		if ( count($upsell_ids) < 8 ) {
			$featured = wc_get_products( array(
				'featured' => true,
				'limit'    => 8,
				'exclude'  => array_merge( $cart_ids, $upsell_ids ),
				'return'   => 'ids',
				'status'   => 'publish'
			) );
			$upsell_ids = array_merge( $upsell_ids, $featured );
			$upsell_ids = array_unique( $upsell_ids );
		}

		// Fallback 2: Best Sellers
		if ( count($upsell_ids) < 8 ) {
			$best_sellers = wc_get_products( array(
				'meta_key' => 'total_sales',
				'orderby'  => 'meta_value_num',
				'order'    => 'DESC',
				'limit'    => 8,
				'exclude'  => array_merge( $cart_ids, $upsell_ids ),
				'return'   => 'ids',
				'status'   => 'publish'
			) );
			$upsell_ids = array_merge( $upsell_ids, $best_sellers );
			$upsell_ids = array_unique( $upsell_ids );
		}

		if ( empty($upsell_ids) ) return '';

		$upsell_ids = array_slice( $upsell_ids, 0, 8 );
		ob_start(); ?>
		<div class="o100-sc-upsells">
			<div class="o100-sc-upsells-header">
				<span><?php esc_html_e('Complement your cart', 'order100'); ?></span>
				<div class="o100-sc-upsells-nav">
					<button class="o100-sc-upsell-prev">‹</button>
					<button class="o100-sc-upsell-next">›</button>
				</div>
			</div>
			<div class="o100-sc-upsells-track">
				<?php foreach ( $upsell_ids as $uid ) :
					$up = wc_get_product($uid);
					if ( ! $up || ! $up->is_visible() ) continue;
					$img = wp_get_attachment_image_url( $up->get_image_id(), 'thumbnail' ) ?: wc_placeholder_img_src('thumbnail');
				?>
				<div class="o100-sc-upsell-card" data-id="<?php echo esc_attr($uid); ?>">
					<div class="o100-sc-upsell-info">
						<div class="o100-sc-upsell-name"><?php echo esc_html( wp_trim_words($up->get_name(), 8) ); ?></div>
						<div class="o100-sc-upsell-price"><?php echo $up->get_price_html(); ?></div>
					</div>
					<div class="o100-sc-upsell-img-wrap">
						<img src="<?php echo esc_url($img); ?>" alt="">
						<button class="o100-sc-upsell-add add_to_cart_button ajax_add_to_cart" data-product_id="<?php echo esc_attr($uid); ?>">+</button>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php return ob_get_clean();
	}

	/**
	 * Build active promotions HTML (Uber-style "Offers for you")
	 */
	private function render_promotions() {
		if ( ! class_exists('O100_Promotions_DB') ) return '';
		$rules = O100_Promotions_DB::query([ 'status' => 'active', 'orderby' => 'priority', 'order' => 'ASC' ]);
		if ( empty($rules) ) return '';

		// Collect promo products from rules
		$promo_items = array();
		$cart_ids = array();
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $ci ) {
				$cart_ids[] = $ci['product_id'];
				if ( ! empty($ci['variation_id']) ) {
					$cart_ids[] = $ci['variation_id'];
				}
			}
		}

		foreach ( $rules as $rule ) {
			// Skip if cart scope
			$config = json_decode($rule['action_config'], true);
			if (isset($config['level']) && $config['level'] === 'cart') continue;

			// Extract targeted products
			$pids = [];
			if ($rule['apply_to'] === 'specific_products') {
				$pids = json_decode($rule['apply_to_items'], true);
			}

			if (empty($pids) || !is_array($pids)) continue;

			$rtype = $rule['rule_type'];
			$badge = '';

			if (isset($config['display']) && !empty($config['display']['badge_text'])) {
				$badge = $config['display']['badge_text'];
			} else {
				if ( $rtype === 'bogo' ) {
					$badge = 'Buy ' . $config['buy_qty'] . ' Get ' . $config['get_qty'];
					if ( $config['discount_type'] === 'free' ) $badge .= ' Free';
				} elseif ( $rtype === 'buy_x_get_y' ) {
					$badge = 'Combo Deal';
				} elseif ( $rtype === 'simple' && $config['discount_type'] === 'percentage' ) {
					$badge = floatval($config['discount_value']) . '% OFF';
				} elseif ( $rtype === 'simple' && $config['discount_type'] === 'fixed' ) {
					$badge = '$' . $config['discount_value'] . ' OFF';
				}
			}

			if ( ! empty($pids) ) {
				foreach ( array_slice($pids, 0, 3) as $pid ) {
					if ( in_array($pid, $cart_ids) ) continue;
					$promo_items[] = array( 'product_id' => intval($pid), 'badge' => $badge );
				}
			}
		}
		if ( empty($promo_items) ) return '';
		$promo_items = array_slice($promo_items, 0, 4);

		ob_start(); ?>
		<div class="o100-sc-promos">
			<div class="o100-sc-promos-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
				<div class="o100-sc-promos-title" style="margin-bottom:0; font-size:16px; font-weight:700; color:#111;"><?php esc_html_e('Offers for you', 'order100'); ?></div>
				<div class="o100-sc-promos-nav" style="display:flex; gap:4px;">
					<button class="o100-sc-promo-prev" type="button" style="width:28px; height:28px; min-width:28px; min-height:28px; padding:0; box-sizing:border-box; border:1px solid #e5e7eb; border-radius:50%; background:#fff; cursor:pointer; font-size:16px; line-height:1; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#111; transition:background .15s;">&#8249;</button>
					<button class="o100-sc-promo-next" type="button" style="width:28px; height:28px; min-width:28px; min-height:28px; padding:0; box-sizing:border-box; border:1px solid #e5e7eb; border-radius:50%; background:#fff; cursor:pointer; font-size:16px; line-height:1; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#111; transition:background .15s;">&#8250;</button>
				</div>
			</div>
			<div class="o100-sc-promos-track" style="display:flex; gap:12px; overflow-x:auto; -ms-overflow-style:none; scrollbar-width:none; padding-bottom:8px;">
			<?php foreach ( $promo_items as $pi ) :
				$p = wc_get_product($pi['product_id']);
				if ( ! $p ) continue;
				$img = wp_get_attachment_image_url($p->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail');
				
				$has_legacy_addons = get_post_meta( $p->get_id(), '_product_addons', true ) || get_post_meta( $p->get_id(), 'tm_meta', true );
				$has_required_o100_addons = false;
				if ( class_exists( 'O100_Product_Addons_Frontend' ) ) {
					$options = O100_Product_Addons_Frontend::instance()->get_product_options( $p->get_id() );
					if ( ! empty( $options ) ) {
						foreach ( $options as $group ) {
							if ( isset( $group['_required'] ) && $group['_required'] === 'yes' ) {
								$has_required_o100_addons = true;
								break;
							}
						}
					}
				}
				$has_addons = $has_legacy_addons || $has_required_o100_addons;

				if ( $p->is_type( 'simple' ) && $p->is_purchasable() && $p->is_in_stock() && empty( $has_addons ) ) {
					$btn_class = 'o100-native-add-to-cart';
					$btn_href  = '?add-to-cart=' . esc_attr($pi['product_id']);
				} else {
					$btn_class = 'o100-view-options-btn';
					$btn_href  = esc_url( $p->get_permalink() );
				}
			?>
			<div class="o100-sc-promo-card o100-product-card" data-product-id="<?php echo esc_attr($pi['product_id']); ?>" data-enable-modal="yes" data-product-link="<?php echo esc_url( $p->get_permalink() ); ?>">
				<div class="o100-sc-promo-info">
					<div class="o100-sc-promo-name"><?php echo esc_html($p->get_name()); ?></div>
					<div class="o100-sc-promo-price"><?php echo $p->get_price_html(); ?></div>
					<?php if ($pi['badge']) : ?><span class="o100-sc-promo-badge"><?php echo esc_html($pi['badge']); ?></span><?php endif; ?>
				</div>
				<div class="o100-sc-promo-img-wrap">
					<img src="<?php echo esc_url($img); ?>" alt="">
					<a href="<?php echo $btn_href; ?>" data-quantity="1" class="o100-sc-promo-add-btn <?php echo esc_attr($btn_class); ?>" data-product_id="<?php echo esc_attr($pi['product_id']); ?>">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
					</a>
				</div>
			</div>
			<?php endforeach; ?>
			</div>
		</div>
		<style>.o100-sc-promos-track::-webkit-scrollbar{display:none;}</style>
		<?php return ob_get_clean();
	}

	/**
	 * Build the full cart body HTML (used for initial render + fragments)
	 */
	private function build_cart_body() {
		$portal_opts = get_option('o100_portal', array());
		$empty_text = isset($portal_opts['o100_portal_cart_empty_text']) ? $portal_opts['o100_portal_cart_empty_text'] : 'Your cart is currently empty.';
		$continue_btn = isset($portal_opts['o100_portal_cart_continue_btn_text']) ? $portal_opts['o100_portal_cart_continue_btn_text'] : 'Browse Menu';
		$checkout_btn = isset($portal_opts['o100_portal_cart_checkout_text']) ? $portal_opts['o100_portal_cart_checkout_text'] : 'Go to checkout';
		
		ob_start();
		echo '<div class="o100-sc-body">';
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			echo '<div class="o100-sc-empty"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>';
			echo '<p>' . esc_html( $empty_text ) . '</p>';
			echo '<button type="button" class="o100-sc-continue-btn o100-sc-close-btn" style="margin-top:16px; background:var(--theme-color); color:var(--btn-text-color); padding:10px 20px; border-radius:8px; border:none; font-weight:600; cursor:pointer; width:auto; height:auto; display:inline-block; transition:opacity 0.2s;">' . esc_html( $continue_btn ) . '</button></div>';
		} else {
			// Items
			echo '<div class="o100-sc-items">';
			foreach ( WC()->cart->get_cart() as $key => $item ) {
				echo $this->render_cart_item( $item, $key );
			}
			echo '</div>';

			// Upsells (conditional)
			// Upsells (conditional)
			$show_upsell = isset($portal_opts['o100_portal_cart_show_upsell']) ? $portal_opts['o100_portal_cart_show_upsell'] : 'yes';
			$show_promo  = isset($portal_opts['o100_portal_cart_show_promo']) ? $portal_opts['o100_portal_cart_show_promo'] : 'yes';
			if ( $show_upsell === 'yes' ) {
				echo $this->render_upsells();
			}

			// Promotions (conditional)
			if ( $show_promo === 'yes' ) {
				echo $this->render_promotions();
			}

			// Notices (Minimum Order, Free Shipping Progress, etc.)
			ob_start();
			do_action( 'woocommerce_widget_shopping_cart_before_buttons' );
			$notices = ob_get_clean();
			if ( $notices ) {
				echo '<div class="o100-sc-notices" style="padding: 0 20px;">' . $notices . '</div>';
			}

			// Subtotal + Checkout
			echo '<div class="o100-sc-footer">';
			echo '<div class="o100-sc-subtotal"><span>' . esc_html__('Subtotal', 'order100') . '</span><span class="o100-sc-subtotal-val">' . WC()->cart->get_cart_subtotal() . '</span></div>';
			
			if ( WC()->cart->get_fees() ) {
				foreach ( WC()->cart->get_fees() as $fee ) {
					echo '<div class="o100-sc-fee" style="display:flex; justify-content:space-between; margin-top:8px; font-size:14px; color:#e11d48; font-weight:600;">';
					echo '<span>' . esc_html( $fee->name ) . '</span>';
					echo '<span>' . wc_price( $fee->total ) . '</span>';
					echo '</div>';
				}
				
				$total = WC()->cart->get_cart_contents_total() + WC()->cart->get_fee_total();
				echo '<div class="o100-sc-total" style="display:flex; justify-content:space-between; margin-top:12px; padding-top:12px; border-top:1px solid #eee; font-size:16px; font-weight:700;">';
				echo '<span>' . esc_html__('Total', 'order100') . '</span>';
				echo '<span class="o100-sc-total-val">' . wc_price( $total ) . '</span>';
				echo '</div>';
			}
			
			echo '<a href="' . esc_url(wc_get_checkout_url()) . '" class="o100-sc-checkout-btn">' . esc_html( $checkout_btn ) . '</a>';
			echo '</div>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Render full side cart to footer
	 */
	public function render_side_cart() {
		global $o100_disable_side_cart;
		if ( ! empty( $o100_disable_side_cart ) ) {
			return;
		}

		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}
		if ( is_checkout() || is_cart() ) return;
		
		if ( WC()->cart && ! WC()->cart->is_empty() ) {
			WC()->cart->calculate_totals();
		}
		
		$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		
		// Load Store Portal Settings
		$opts = get_option('o100_portal', array());
		$widget_mode    = isset($opts['o100_portal_widget_mode']) ? $opts['o100_portal_widget_mode'] : 'sidecart';
		$launcher_text  = isset($opts['o100_portal_launcher_text']) ? $opts['o100_portal_launcher_text'] : 'Rewards & Cart';
		$launcher_pos   = isset($opts['o100_portal_launcher_position']) ? $opts['o100_portal_launcher_position'] : 'bottom-right';
		$launcher_anim  = isset($opts['o100_portal_launcher_animation']) ? $opts['o100_portal_launcher_animation'] : 'pulse';
		$launcher_icon  = isset($opts['o100_portal_launcher_icon']) ? $opts['o100_portal_launcher_icon'] : 'cart';
		$launcher_shape = isset($opts['o100_portal_launcher_shape']) ? $opts['o100_portal_launcher_shape'] : 'pill';
		$launcher_spacing= isset($opts['o100_portal_launcher_spacing']) ? $opts['o100_portal_launcher_spacing'] : '20';
		
		$theme_color    = !empty($opts['o100_portal_theme_color']) ? $opts['o100_portal_theme_color'] : '#e11d48';
		$bg_color       = !empty($opts['o100_portal_bg_color']) ? $opts['o100_portal_bg_color'] : '#ffffff';
		$btn_text_color = !empty($opts['o100_portal_btn_text_color']) ? $opts['o100_portal_btn_text_color'] : '#ffffff';
		
		$theme_mode     = !empty($opts['o100_portal_theme_mode']) ? $opts['o100_portal_theme_mode'] : 'light';
		$panel_width    = !empty($opts['o100_portal_panel_width']) ? $opts['o100_portal_panel_width'] : '400';
		$drawer_side    = !empty($opts['o100_portal_drawer_side']) ? $opts['o100_portal_drawer_side'] : 'right';
		$backdrop       = !empty($opts['o100_portal_backdrop_overlay']) ? $opts['o100_portal_backdrop_overlay'] : 'dark';
		$close_btn      = !empty($opts['o100_portal_close_btn_style']) ? $opts['o100_portal_close_btn_style'] : 'inside';
		$z_index        = !empty($opts['o100_portal_z_index']) ? $opts['o100_portal_z_index'] : '999999';
		$custom_css     = !empty($opts['o100_portal_custom_css']) ? $opts['o100_portal_custom_css'] : '';
		
		$launcher_style = isset($opts['o100_portal_launcher_style']) ? $opts['o100_portal_launcher_style'] : 'icon_text';
		if ( $launcher_pos === 'hidden' ) {
			$launcher_style = 'hidden';
		}
			$enabled_tabs   = isset($opts['o100_portal_enabled_tabs']) && is_array($opts['o100_portal_enabled_tabs']) ? $opts['o100_portal_enabled_tabs'] : array('cart', 'rewards', 'account');
			if ( ! in_array('cart', $enabled_tabs) ) array_unshift($enabled_tabs, 'cart');
			// Smart detection: remove rewards tab if no active loyalty campaigns
			global $wpdb;
			$lc_table = $wpdb->prefix . 'o100_loyalty_campaigns';
			$has_loy_campaigns = false;
			if ( $wpdb->get_var("SHOW TABLES LIKE '{$lc_table}'") === $lc_table ) {
				$has_loy_campaigns = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$lc_table} WHERE status = 'active'") > 0;
			}
			if ( ! $has_loy_campaigns ) {
				$enabled_tabs = array_diff($enabled_tabs, array('rewards'));
			}
		$default_tab    = isset($opts['o100_portal_default_tab']) ? $opts['o100_portal_default_tab'] : 'rewards';
		if ( ! $has_loy_campaigns && $default_tab === 'rewards' ) $default_tab = 'cart';

		// If hidden, render nothing for desktop
		if ( $launcher_style !== 'hidden' ) {
			$anim_class = '';
			if ($launcher_anim === 'bounce') $anim_class = 'o100-anim-bounce';
			if ($launcher_anim === 'pulse' && $count > 0) $anim_class = 'o100-anim-pulse';

			$pos_style = (strpos($launcher_pos, 'left') !== false) ? "left: {$launcher_spacing}px; right: auto;" : "right: {$launcher_spacing}px; left: auto;";
			if ( strpos($launcher_pos, 'middle') !== false ) {
				$pos_style .= " top: 50%; margin-top: -26px;";
			} else {
				$pos_style .= " bottom: {$launcher_spacing}px;";
			}
			
			$shape_style = 'border-radius: 50px;';
			if ($launcher_shape === 'rounded') $shape_style = 'border-radius: 12px;';
			if ($launcher_shape === 'square') $shape_style = 'border-radius: 0;';
			
			$icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>';
			if ($launcher_icon === 'bag') $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>';
			if ($launcher_icon === 'basket') $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h2l3.6 7.59L6.25 13A2 2 0 008 16h12v-2H8l1.1-2h7.45a2 2 0 001.76-1.06L22 6H6.21"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>';
			if ($launcher_icon === 'gift') $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>';
			if ($launcher_icon === 'star') $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
			if ($launcher_icon === 'crown') $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>';
			if ($launcher_icon === 'custom_image') {
				$custom_img = isset($opts['o100_portal_launcher_custom_image']) ? $opts['o100_portal_launcher_custom_image'] : '';
				if (!empty($custom_img)) {
					$icon_svg = '<img src="' . esc_url($custom_img) . '" style="width:20px;height:20px;border-radius:50%;object-fit:cover;">';
				}
			}
			
			?>
			<div id="o100-cart-toggle" class="o100-cart-toggle <?php echo esc_attr($launcher_style . ' ' . $anim_class); ?>" style="<?php echo esc_attr($pos_style); ?> <?php echo esc_attr($shape_style); ?> background: <?php echo esc_attr($theme_color); ?>; color: <?php echo esc_attr($btn_text_color); ?>; z-index: <?php echo esc_attr($z_index); ?>; --badge-bg: <?php echo esc_attr($btn_text_color); ?>; --badge-color: <?php echo esc_attr($theme_color); ?>;">
				<?php if ( $launcher_style !== 'text_only' ) echo $icon_svg; ?>
				<?php if ( $launcher_style !== 'icon_only' ) : ?>
					<span class="o100-cart-text" style="margin-left: <?php echo $launcher_style === 'text_only' ? '0' : '8px'; ?>; font-weight: 600; font-size: 14px; white-space:nowrap;"><?php echo esc_html($launcher_text); ?></span>
				<?php endif; ?>
				<span class="o100-cart-count"><?php echo esc_html($count); ?></span>
			</div>
			<?php
		}
		?>
		<?php if ( !empty($custom_css) ) : ?>
			<style id="o100-portal-custom-css"><?php echo wp_strip_all_tags($custom_css); ?></style>
		<?php endif; ?>
		<div id="o100-cart-overlay" class="o100-cart-overlay backdrop-<?php echo esc_attr($backdrop); ?>" style="z-index: <?php echo esc_attr(intval($z_index) - 1); ?>;"></div>
		<div id="o100-side-cart" class="o100-side-cart drawer-<?php echo esc_attr($drawer_side); ?> mode-<?php echo esc_attr($theme_mode); ?>" style="width: <?php echo esc_attr($panel_width); ?>px; max-width: 100%; z-index: <?php echo esc_attr($z_index); ?>;">
			
			<?php if ( $close_btn === 'outside' ) : ?>
				<button type="button" id="o100-cart-close" class="o100-sc-close-btn o100-sc-close-outside" style="position:absolute; top:15px; left:-50px; width:40px; height:40px; background:#fff; border-radius:50%; border:none; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 12px rgba(0,0,0,0.15); cursor:pointer; color:#111; z-index:<?php echo esc_attr($z_index); ?>;">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
				</button>
			<?php endif; ?>

			<div class="o100-sc-header" style="background: <?php echo esc_attr($bg_color); ?>;">
				<div class="o100-sc-header-logo-row">
					<div class="o100-sc-header-logo" style="display:flex; align-items:center;">
						<?php 
						$logo_url = isset($opts['o100_portal_logo']) ? $opts['o100_portal_logo'] : '';
						if ( $logo_url ) {
							echo '<img src="' . esc_url($logo_url) . '" alt="Logo" style="max-height:30px; max-width:120px; object-fit:contain;">';
						} else {
							echo '<span class="o100-sc-header-label" style="color:var(--text-color);">' . esc_html__('Store Portal', 'order100') . '</span>';
						}
						?>
					</div>
					<?php if ( $close_btn === 'inside' ) : ?>
						<button type="button" id="o100-cart-close" class="o100-sc-close-btn" style="color:var(--text-color);">&times;</button>
					<?php endif; ?>
				</div>
				<div class="o100-sc-header-tabs">
					<?php 
					$tab_cart = isset($portal_opts['o100_portal_tab_label_cart']) ? $portal_opts['o100_portal_tab_label_cart'] : 'Your Order';
					$tab_rewards = isset($portal_opts['o100_portal_tab_label_rewards']) ? $portal_opts['o100_portal_tab_label_rewards'] : 'Rewards';
					$tab_account = isset($portal_opts['o100_portal_tab_label_account']) ? $portal_opts['o100_portal_tab_label_account'] : 'Account';
					?>
					<?php if ( in_array('cart', $enabled_tabs) ) : ?>
						<button class="o100-sc-tab-btn <?php echo ($default_tab === 'cart') ? 'active' : ''; ?>" data-target="o100-sc-tab-cart"><?php echo esc_html($tab_cart); ?></button>
					<?php endif; ?>
					<?php if ( in_array('rewards', $enabled_tabs) ) : ?>
						<button class="o100-sc-tab-btn <?php echo ($default_tab === 'rewards') ? 'active' : ''; ?>" data-target="o100-sc-tab-rewards"><?php echo esc_html($tab_rewards); ?></button>
					<?php endif; ?>
					<?php if ( in_array('account', $enabled_tabs) ) : ?>
						<button class="o100-sc-tab-btn" data-target="o100-sc-tab-account"><?php echo esc_html($tab_account); ?></button>
					<?php endif; ?>
				</div>
			</div>
			
			<div class="o100-sc-content-wrapper">
				<?php if ( in_array('cart', $enabled_tabs) ) : ?>
					<!-- Cart Tab -->
					<div id="o100-sc-tab-cart" class="o100-sc-tab-pane <?php echo ($default_tab === 'cart') ? 'active' : ''; ?>">
						<?php echo $this->build_cart_body(); ?>
					</div>
				<?php endif; ?>
				
				<?php if ( in_array('rewards', $enabled_tabs) ) : ?>
					<!-- Rewards Tab -->
					<div id="o100-sc-tab-rewards" class="o100-sc-tab-pane <?php echo ($default_tab === 'rewards') ? 'active' : ''; ?>">
						<div class="o100-portal-rewards-container">
							<?php do_action('o100_store_portal_rewards_tab_content'); ?>
						</div>
					</div>
				<?php endif; ?>
				
				<?php if ( in_array('account', $enabled_tabs) ) : ?>
				<!-- Account Tab -->
				<div id="o100-sc-tab-account" class="o100-sc-tab-pane">
					<div class="o100-portal-account-container" style="padding:20px;">
						<ul class="o100-portal-account-menu" style="list-style:none;padding:0;margin:0;">
							<?php if ( is_user_logged_in() ) : ?>
								<li style="margin-bottom:15px;"><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" style="display:flex;align-items:center;gap:10px;color:#111;text-decoration:none;font-weight:600;"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('My Orders', 'order100'); ?></a></li>
								<li style="margin-bottom:15px;"><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" style="display:flex;align-items:center;gap:10px;color:#111;text-decoration:none;font-weight:600;"><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('Account Details', 'order100'); ?></a></li>
								<li style="margin-bottom:15px;"><a href="<?php echo esc_url( wp_logout_url( wc_get_page_permalink( 'myaccount' ) ) ); ?>" style="display:flex;align-items:center;gap:10px;color:#e11d48;text-decoration:none;font-weight:600;"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e('Logout', 'order100'); ?></a></li>
							<?php else : ?>
								<li style="margin-bottom:15px;"><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" style="display:flex;align-items:center;gap:10px;color:#111;text-decoration:none;font-weight:600;"><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('Login / Register', 'order100'); ?></a></li>
							<?php endif; ?>
						</ul>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>
		
		<!-- Product Quickview Modal -->
		<div id="o100-product-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center; backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px);">
			<div class="o100-modal-content" style="background:#fff; border-radius:16px; width:90%; max-width:400px; position:relative; box-shadow:0 10px 40px rgba(0,0,0,0.2);">
				<button class="o100-modal-close" style="position:absolute; top:12px; right:12px; width:30px; height:30px; border-radius:50%; background:rgba(0,0,0,0.4); color:#fff; border:none; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:20px; z-index:10; padding:0;">&times;</button>
				<div class="o100-modal-body"></div>
			</div>
		</div>
		
		<?php
		$qtys = array();
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$pid = $cart_item['product_id'];
				if ( ! isset( $qtys[$pid] ) ) $qtys[$pid] = 0;
				$qtys[$pid] += $cart_item['quantity'];
			}
		}
		echo '<div id="o100-cart-qtys" style="display:none;" data-qtys="' . esc_attr(wp_json_encode($qtys)) . '"></div>';
		?>
		<?php $this->render_styles(); $this->render_scripts();
	}

	/**
	 * Retrieve order method info HTML for the mobile bar
	 */
	private function get_user_order_method_info_html() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) return '';

		$method = WC()->session->get('_o100_order_method');
		$address_html = '';

		if ( $method === 'takeaway' || $method === 'dinein' ) {
			$loc_slug = WC()->session->get('ex_userloc');
			if ( ! $loc_slug ) {
				$loc_slug = WC()->session->get('_user_deli_log');
			}

			$address = '';
			if ( $loc_slug ) {
				$term = get_term_by('slug', $loc_slug, 'exwoofood_loc');
				if ( isset($term->term_id) ) {
					$address = get_term_meta($term->term_id, 'exwp_loc_address', true);
					if ( empty($address) ) {
						$address = $term->name;
					}
				}
			} else {
				$address = get_option('woocommerce_store_address', '');
				$address = apply_filters('exwf_default_store_address', $address);
			}

			$title = ($method === 'dinein') ? __('Dine-in at:', 'order100') : __('Pickup at:', 'order100');
			if ( !empty($address) ) {
				$address_html = '<span class="o100-mbnav-title">' . esc_html($title) . '</span><span class="o100-mbnav-address">' . esc_html($address) . '</span>';
			}
		} else if ( $method === 'delivery' ) {
			$address = WC()->session->get('_user_deli_adress');
			if ( $address ) {
				$address_html = '<span class="o100-mbnav-title">' . esc_html__('Delivery to:', 'order100') . '</span><span class="o100-mbnav-address">' . esc_html($address) . '</span>';
			}
		}

		return $address_html;
	}

	/**
	 * Render fixed mobile navigation bar at the bottom
	 */
	public function render_mobile_nav_bar() {
		if ( is_admin() || ! function_exists('WC') ) return;
		if ( is_checkout() || is_cart() ) return;
		
		$opts = get_option('o100_portal', array());
		$widget_mode = isset($opts['o100_portal_widget_mode']) ? $opts['o100_portal_widget_mode'] : 'sidecart';
		$mobile_nav = isset($opts['o100_portal_mobile_integration']) ? $opts['o100_portal_mobile_integration'] : 'on';
		$show_rewards_icon = ( $widget_mode === 'sidecart' && $mobile_nav === 'on' );

		$dl_info = $this->get_user_order_method_info_html();
		$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		?>
		<div class="o100-mbnav">
			<div class="o100-mbnav-content">
				<?php if ( ! empty($dl_info) ) : ?>
					<div class="o100-mbnav-item o100-mbnav-dl-info">
						<?php echo $dl_info; ?>
					</div>
				<?php endif; ?>
				<div class="o100-mbnav-item o100-mbnav-cart-icon">
					<a href="#" class="o100-mbnav-cart-trigger">
						<span class="o100-mbnav-icon-wrap">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
							<span class="o100-mbnav-cart-count"><?php echo esc_html($count); ?></span>
						</span>
						<span class="o100-mbnav-text"><?php esc_html_e('Cart', 'order100'); ?></span>
					</a>
				</div>
				<div class="o100-mbnav-item o100-mbnav-ck-icon">
					<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>">
						<span class="o100-mbnav-icon-wrap">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
						</span>
						<span class="o100-mbnav-text"><?php esc_html_e('Checkout', 'order100'); ?></span>
					</a>
				</div>
				<?php if ( $show_rewards_icon ) : ?>
				<div class="o100-mbnav-item o100-mbnav-rewards-icon">
					<a href="#" class="o100-mbnav-rewards-trigger">
						<span class="o100-mbnav-icon-wrap" style="position:relative;">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
							<span class="o100-mbnav-rewards-badge" style="display:none;position:absolute;top:-2px;right:-4px;width:10px;height:10px;background:#e11d48;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.1);"></span>
						</span>
						<span class="o100-mbnav-text"><?php esc_html_e('Rewards', 'order100'); ?></span>
					</a>
				</div>
				<?php else : ?>
				<div class="o100-mbnav-item o100-mbnav-orders-icon">
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
						<span class="o100-mbnav-icon-wrap">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
						</span>
						<span class="o100-mbnav-text"><?php esc_html_e('My Orders', 'order100'); ?></span>
					</a>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * WC fragments for AJAX updates
	 */
	public function cart_fragments( $fragments ) {
		if ( WC()->cart ) {
			WC()->cart->calculate_totals();
		}
		$count = WC()->cart->get_cart_contents_count();
		$fragments['span.o100-cart-count'] = '<span class="o100-cart-count">' . esc_html($count) . '</span>';
		$fragments['span.o100-mbnav-cart-count'] = '<span class="o100-mbnav-cart-count">' . esc_html($count) . '</span>';
		$fragments['div.o100-sc-body'] = $this->build_cart_body();
		
		$qtys = array();
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$pid = $cart_item['product_id'];
				if ( ! isset( $qtys[$pid] ) ) $qtys[$pid] = 0;
				$qtys[$pid] += $cart_item['quantity'];
			}
		}
		$fragments['div#o100-cart-qtys'] = '<div id="o100-cart-qtys" style="display:none;" data-qtys="' . esc_attr(wp_json_encode($qtys)) . '"></div>';
		return $fragments;
	}

	private function render_styles() { 
		$options = get_option( 'o100_options', array() );
		$primary_color = !empty($options['o100_main_color']) ? $options['o100_main_color'] : '#e11d48';
		?>
<style>
/* ── Toggle Button ── */
.o100-cart-toggle{position:fixed;z-index:99990;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 12px rgba(0,0,0,.25);transition:transform .2s,box-shadow .2s; height: 52px; padding: 0 20px; box-sizing: border-box;}
.o100-cart-toggle.icon_only{padding: 0; width: 52px; justify-content: center; aspect-ratio: 1; flex-shrink:0;}
.o100-cart-toggle:hover{transform:translateY(-2px);box-shadow:0 4px 18px rgba(0,0,0,.35)}
.o100-cart-count{position:absolute;top:-4px;right:-4px;font-size:11px;font-weight:700;min-width:20px;height:20px;line-height:20px;text-align:center;border-radius:10px;padding:0 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.2); background: var(--badge-bg, #fff); color: var(--badge-color, #e11d48);}

/* ── Overlay ── */
.o100-cart-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99991;opacity:0;visibility:hidden;transition:opacity .3s,visibility .3s}
.o100-cart-overlay.active{opacity:1;visibility:visible}
.o100-cart-overlay.backdrop-glass{background:rgba(255,255,255,0.1);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);}
.o100-cart-overlay.backdrop-none{background:transparent; pointer-events:none;}
.o100-cart-overlay.backdrop-none.active{pointer-events:auto;}

/* ── Panel ── */
.o100-side-cart{position:fixed;top:0;right:-440px;width:420px;max-width:92vw;height:100%;background:#fff;z-index:99992;box-shadow:-2px 0 20px rgba(0,0,0,.12);transition:right .35s cubic-bezier(.16,1,.3,1),left .35s cubic-bezier(.16,1,.3,1);display:flex;flex-direction:column;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
.o100-side-cart.open{right:0}

/* Left Drawer Variant */
.o100-side-cart.drawer-left{right:auto;left:-440px;box-shadow:2px 0 20px rgba(0,0,0,.12);}
.o100-side-cart.drawer-left.open{left:0;right:auto;}

/* Dark Mode Variant */
.o100-side-cart.mode-dark{background:#111827;color:#f9fafb;}
.o100-side-cart.mode-dark .o100-sc-header{border-bottom:1px solid #374151;}
.o100-side-cart.mode-dark .o100-sc-close-btn{color:#f9fafb;}
.o100-side-cart.mode-dark .o100-sc-header-label{color:#f9fafb;}
.o100-side-cart.mode-dark .o100-sc-header-tabs{background:#1f2937;}
.o100-side-cart.mode-dark .o100-sc-tab-btn{color:#9ca3af !important;}
.o100-side-cart.mode-dark .o100-sc-tab-btn:hover{color:#f9fafb !important;background:rgba(255,255,255,.05) !important;}
.o100-side-cart.mode-dark .o100-sc-tab-btn.active{color:#111827 !important;background:#f9fafb !important;}
.o100-side-cart.mode-dark .o100-sc-item{border-bottom-color:#374151;}
.o100-side-cart.mode-dark .o100-sc-item:hover{background:#1f2937;}
.o100-side-cart.mode-dark .o100-sc-item-title, .o100-side-cart.mode-dark .o100-sc-item-price{color:#f9fafb;}
.o100-side-cart.mode-dark .o100-sc-qty{border-color:#4b5563;}
.o100-side-cart.mode-dark .o100-sc-qty-btn{background:#1f2937;color:#f9fafb;}
.o100-side-cart.mode-dark .o100-sc-qty-btn:hover{background:#374151 !important;}
.o100-side-cart.mode-dark .o100-sc-qty-val{color:#f9fafb;}
.o100-side-cart.mode-dark .o100-sc-upsells, .o100-side-cart.mode-dark .o100-sc-promos{border-top-color:#1f2937;}
.o100-side-cart.mode-dark .o100-sc-upsells-header span, .o100-side-cart.mode-dark .o100-sc-promos-title{color:#f9fafb;}
.o100-side-cart.mode-dark .o100-sc-upsell-name{color:#f9fafb;}
.o100-side-cart.mode-dark .o100-sc-upsells-nav button{background:#1f2937;border-color:#374151;color:#f9fafb;}
.o100-side-cart.mode-dark .o100-sc-upsells-nav button:hover{background:#374151;}
.o100-side-cart.mode-dark .o100-sc-footer{background:#111827;border-top-color:#374151;}
.o100-side-cart.mode-dark .o100-sc-subtotal span{color:#f9fafb;}
.o100-side-cart.mode-dark .o100-sc-promo-card{background:#1f2937;border-color:#374151;}
.o100-side-cart.mode-dark .o100-sc-promo-name, .o100-side-cart.mode-dark .o100-sc-promo-price{color:#f9fafb;}

/* Header */
.o100-sc-header{display:block;padding:15px 20px 12px;border-bottom:1px solid #f0f0f0;flex-shrink:0}
.o100-sc-header-logo-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
.o100-sc-header-logo-row .o100-sc-close-btn{position:relative;left:0 !important;top:0 !important;right:0 !important;}
.o100-sc-close-btn{background:none;border:none;font-size:28px;color:#111;cursor:pointer;padding:0;line-height:1;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:background .15s}
.o100-sc-close-btn:hover{background:#f3f4f6}
.o100-side-cart.drawer-left .o100-sc-close-outside{left:auto !important;right:-50px !important;}
@media(max-width: 768px) { .o100-sc-close-outside{display:none !important;} }
.o100-sc-header-tabs{display:flex;gap:4px;width:100%;background:#f3f4f6;padding:4px;border-radius:24px;}
.o100-sc-tab-btn{background:transparent !important;border:none !important;font-size:14px !important;font-weight:600 !important;color:#6b7280 !important;cursor:pointer;padding:6px 12px !important;border-radius:20px !important;transition:all .2s ease !important;white-space:nowrap;flex:1;text-align:center;box-shadow:none !important;outline:none !important;line-height:1.4 !important;margin:0 !important;}
.o100-sc-tab-btn:hover{color:#111827 !important;background:rgba(0,0,0,.05) !important;}
.o100-sc-tab-btn.active{color:#111827 !important;background:#ffffff !important;box-shadow:0 1px 3px rgba(0,0,0,.1) !important;}

/* Tabs Content */
.o100-sc-content-wrapper{flex:1;overflow:hidden;position:relative}
.o100-sc-tab-pane{display:none;width:100%;height:100%;flex-direction:column;overflow-y:auto}
.o100-sc-tab-pane.active{display:flex}
.o100-sc-header-label{font-size:18px;font-weight:700;color:#111}

/* Body */
.o100-sc-body{flex:1;overflow-y:auto;display:flex;flex-direction:column}
.o100-sc-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;gap:12px;color:#9ca3af;padding:40px 20px}
.o100-sc-empty p{margin:0;font-size:15px}

/* Items */
.o100-sc-items{padding:4px 0}
.o100-sc-item{display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid #f5f5f5;transition:background .15s}
.o100-sc-item:hover{background:#fafafa}
.o100-sc-item-thumb{flex-shrink:0;width:56px;height:56px;border-radius:8px;overflow:hidden;background:#f9fafb}
.o100-sc-item-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.o100-sc-item-info{flex:1;min-width:0}
.o100-sc-item-title{font-size:14px;font-weight:600;color:#111;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.o100-sc-options{font-size:12px;color:#6b7280;margin-top:2px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.o100-sc-item-price{font-size:13px;font-weight:500;color:#111;margin-top:4px}
.o100-sc-qty{display:flex;align-items:center;gap:0;border:1px solid #e5e7eb;border-radius:20px;overflow:hidden;flex-shrink:0}
.o100-sc-qty-btn{width:32px;height:32px;border:none;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s ease;outline:none !important;box-shadow:none !important;font-size:18px;color:#111;line-height:1}
.o100-sc-qty-btn:hover{background:#f3f4f6 !important;color:#111 !important;}
.o100-sc-qty-btn:focus, .o100-sc-qty-btn:active{background:#fff !important;color:#111 !important;box-shadow:none !important;}
.o100-sc-qty-btn .qty-trash-icon{display:none;align-items:center;justify-content:center;line-height:0}
.o100-sc-qty-btn.is-trash .qty-minus-text{display:none;}
.o100-sc-qty-btn.is-trash .qty-trash-icon{display:flex;}
.o100-sc-qty-val{width:28px;text-align:center;font-size:13px;font-weight:600;color:#111;line-height:32px;transition:opacity .2s ease;}
.o100-sc-qty.is-loading .o100-sc-qty-val{opacity:.3;}
@keyframes o100-spin{100%{transform:rotate(360deg)}}
.o100-sc-spinner{animation:o100-spin 1s linear infinite}

/* Quickview Add to Cart */
.o100-quickview-atc-wrap form.cart { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:0; }
.o100-quickview-atc-wrap .quantity { display:flex; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; }
.o100-quickview-atc-wrap .quantity input { width:40px; height:44px; text-align:center; border:none; background:transparent; font-size:14px; font-weight:600; padding:0; box-shadow:none; }
.o100-quickview-atc-wrap .quantity input[type=number]::-webkit-inner-spin-button, .o100-quickview-atc-wrap .quantity input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.o100-quickview-atc-wrap button.single_add_to_cart_button { flex:1; height:44px; background:var(--theme-color, #e11d48); color:#fff; border:none; border-radius:8px; font-weight:700; font-size:14px; cursor:pointer; transition:opacity 0.2s; white-space:nowrap; padding:0 20px; }
.o100-quickview-atc-wrap button.single_add_to_cart_button:hover { opacity:0.9; }
.o100-quickview-variable form.cart { display:block; }
.o100-quickview-variable .variations { margin-bottom:15px; width:100%; border-collapse:collapse; }
.o100-quickview-variable .variations td { padding:5px 0; }
.o100-quickview-variable .variations .label { font-weight:600; font-size:13px; color:#374151; vertical-align:middle; width:30%; }
.o100-quickview-variable .variations select { width:100%; height:36px; border:1px solid #d1d5db; border-radius:6px; padding:0 10px; font-size:13px; }
.o100-quickview-variable .single_variation_wrap { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
.o100-quickview-variable .woocommerce-variation-price { width:100%; font-size:16px; font-weight:700; color:var(--theme-color, #e11d48); margin-bottom:10px; }
.o100-quickview-variable .woocommerce-variation-add-to-cart { display:flex; width:100%; gap:10px; }

@media (max-width: 480px) { .o100-modal-content { width:95%; } }

/* Upsells */
.o100-sc-upsells{padding:16px 20px;border-top:6px solid #f5f5f5}
.o100-sc-upsells-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.o100-sc-upsells-header span{font-size:16px;font-weight:700;color:#111}
.o100-sc-upsells-nav{display:flex;gap:4px}
.o100-sc-upsells-nav button{width:28px;height:28px;min-width:28px;min-height:28px;padding:0;box-sizing:border-box;border:1px solid #e5e7eb;border-radius:50%;background:#fff;cursor:pointer;font-size:16px;line-height:1;display:flex;align-items:center;justify-content:center;color:#111;transition:background .15s;flex-shrink:0}
.o100-sc-upsells-nav button:hover{background:#f3f4f6}
.o100-sc-upsells-track{display:flex;gap:10px;overflow-x:auto;-ms-overflow-style:none;scrollbar-width:none;padding-bottom:12px;padding-top:4px}
.o100-sc-upsells-track::-webkit-scrollbar{display:none}
.o100-sc-upsell-card{flex-shrink:0;width:220px;display:flex;justify-content:space-between;align-items:center;padding:12px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;cursor:pointer;text-align:left;transition:border-color .15s}
.o100-sc-upsell-card:hover{border-color:#d1d5db}
.o100-sc-upsell-info{flex:1;padding-right:12px;min-width:0}
.o100-sc-upsell-name{font-size:13px;font-weight:600;color:#111;line-height:1.3;margin-bottom:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;white-space:normal}
.o100-sc-upsell-price{font-size:12px;color:#6b7280;font-weight:500}
.o100-sc-upsell-img-wrap{width:64px;height:64px;border-radius:8px;overflow:visible;background:#f9fafb;position:relative;flex-shrink:0}
.o100-sc-upsell-img-wrap img{width:100%;height:100%;object-fit:cover;border-radius:8px}
.o100-sc-upsell-add{position:absolute;right:-6px;bottom:-6px;width:24px;height:24px;min-width:24px;min-height:24px;padding:0;box-sizing:border-box;border-radius:50%;background:#fff;border:1px solid #e5e7eb;color:var(--theme-color,#d97706);font-size:18px;line-height:1;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 6px rgba(0,0,0,.08);transition:all .2s;z-index:2}
.o100-sc-upsell-add:hover{transform:scale(1.1)}

/* Promotions */
.o100-sc-promos{padding:16px 20px;border-top:6px solid #f5f5f5}
.o100-sc-promos-title{font-size:16px;font-weight:700;color:#111;margin-bottom:12px}
.o100-sc-promo-card{flex-shrink:0;width:85%;max-width:280px;display:flex;justify-content:space-between;align-items:center;padding:12px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;cursor:pointer;transition:border-color .15s ease;position:relative}
.o100-sc-promo-card:hover{border-color:#d1d5db}
.o100-sc-promo-info{flex:1;min-width:0;padding-right:12px}
.o100-sc-promo-name{font-size:14px;font-weight:600;color:#111;margin-bottom:4px;line-height:1.3}
.o100-sc-promo-price{font-size:13px;color:#111;margin-bottom:6px}
.o100-sc-promo-badge{display:inline-block;font-size:11px;font-weight:700;color:var(--o100-notice-promo-txt);background:var(--o100-notice-promo-bg);border:1px solid var(--o100-notice-promo-txt);padding:2px 5px;border-radius:4px;line-height:1}
.o100-sc-promo-img-wrap{flex-shrink:0;width:64px;height:64px;border-radius:8px;position:relative;background:#f9fafb}
.o100-sc-promo-img-wrap img{width:100%;height:100%;object-fit:cover;border-radius:8px;display:block}
.o100-sc-promo-add-btn{position:absolute;bottom:-6px;right:-6px;width:28px;height:28px;background:#fff;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.15);display:flex;align-items:center;justify-content:center;color:#111;text-decoration:none;transition:transform .1s ease;z-index:2}
.o100-sc-promo-add-btn:hover{transform:scale(1.05)}

/* Footer */
.o100-sc-footer{padding:16px 20px;border-top:1px solid #e5e7eb;flex-shrink:0;background:#fff}
.o100-sc-subtotal{display:flex;justify-content:space-between;margin-bottom:14px;font-size:15px}
.o100-sc-subtotal span:first-child{font-weight:500;color:#111}
.o100-sc-subtotal span:last-child{font-weight:700;color:#111}
.o100-sc-checkout-btn{display:block;width:100%;padding:14px;background:<?php echo esc_attr($primary_color); ?>;color:#ffffff !important;text-align:center;border-radius:12px;font-size:15px;font-weight:700;text-decoration:none;transition:filter .2s;border:none;cursor:pointer}
.o100-sc-checkout-btn:hover{filter:brightness(0.9);color:#ffffff !important}
/* Mobile Bottom Nav */
.o100-mbnav{display:none;position:fixed;bottom:0;left:0;width:100%;background:#fff;box-shadow:0 -2px 10px rgba(0,0,0,.08);z-index:99989;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
.o100-mbnav-content{display:flex;align-items:center;justify-content:space-between;padding:10px 15px;max-width:100%;overflow-x:auto}
.o100-mbnav-item{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1}
.o100-mbnav-item a{display:flex;flex-direction:column;align-items:center;justify-content:center;color:#6b7280;text-decoration:none;transition:color .2s}
.o100-mbnav-item a:hover{color:#111}
.o100-mbnav-icon-wrap{position:relative;margin-bottom:4px;display:flex;align-items:center;justify-content:center}
.o100-mbnav-cart-count{position:absolute;top:-6px;right:-8px;background:<?php echo esc_attr($primary_color); ?>;color:#fff;font-size:10px;font-weight:700;min-width:18px;height:18px;line-height:18px;text-align:center;border-radius:9px;padding:0 4px;box-shadow:0 1px 3px rgba(0,0,0,.1)}
.o100-mbnav-text{font-size:11px;font-weight:500}
.o100-mbnav-dl-info{flex:1;align-items:flex-start;padding-right:10px;border-right:1px solid #f0f0f0;margin-right:5px;min-width:100px;max-width:35%}
.o100-mbnav-title{font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px}
.o100-mbnav-address{font-size:11px;font-weight:600;color:#111;line-height:1.2;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}

@media (max-width:768px){
	.o100-mbnav{display:block}
	body{padding-bottom:70px !important}
	.o100-cart-toggle{display:none !important}
}
</style>
	<?php }

	private function render_scripts() { ?>
<script>
jQuery(function($){
	var $t=$('#o100-cart-toggle'),$o=$('#o100-cart-overlay'),$p=$('#o100-side-cart');
	var $cartTriggers=$('.o100-mbnav-cart-trigger');
	var $rewardsTriggers=$('.o100-mbnav-rewards-trigger');
	
	function openPortal(e, targetTab){
		if(e)e.preventDefault();
		$p.addClass('open');
		$o.addClass('active');
		$('body').css('overflow','hidden');
		
		if(targetTab) {
			$('.o100-sc-tab-btn').removeClass('active');
			$('.o100-sc-tab-pane').removeClass('active');
			$('.o100-sc-tab-btn[data-target="' + targetTab + '"]').addClass('active');
			$('#' + targetTab).addClass('active');
		}
	}
	
	function closePortal(){$p.removeClass('open');$o.removeClass('active');$('body').css('overflow','')}
	
	$t.on('click',function(e){ openPortal(e, 'o100-sc-tab-cart'); });
	$cartTriggers.on('click',function(e){ openPortal(e, 'o100-sc-tab-cart'); });
	$rewardsTriggers.on('click',function(e){ openPortal(e, 'o100-sc-tab-rewards'); });
	$o.on('click',closePortal);
	$('#o100-cart-close').on('click',closePortal);
	
	// Tab switching inside the portal
	$('.o100-sc-tab-btn').on('click', function() {
		var target = $(this).data('target');
		$('.o100-sc-tab-btn').removeClass('active');
		$(this).addClass('active');
		$('.o100-sc-tab-pane').removeClass('active');
		$('#' + target).addClass('active');
	});

	// Force WC fragment refresh to clear stale sessionStorage cache
	try{Object.keys(sessionStorage).forEach(function(k){if(k.indexOf('wc_fragments')===0||k.indexOf('wc_cart_hash')===0)sessionStorage.removeItem(k)})}catch(e){}
	$(document.body).trigger('wc_fragment_refresh');

	// Dynamic Cart Quantities & Empty Category Hider
	function o100_frontend_optimizations() {
		// Categories are now natively hidden via PHP pre-filtering in Order100 Menu Renderer.
		// No JS needed to hide empty categories anymore.

		// Update card quantities
		var qtys = {};
		var $qtyData = $('#o100-cart-qtys');

		if ($qtyData.length > 0) {
			try { 
				qtys = JSON.parse($qtyData.attr('data-qtys'));
			} catch(e){
				console.error('Error parsing qtys:', e);
			}
		}

		// Carousel Autoplay
		if (!window.o100_promo_carousel_interval) {
			window.o100_promo_carousel_interval = setInterval(function() {
				var $track = $('.o100-sc-promos-track');
				if ($track.length) {
					var el = $track[0];
					if (el.scrollLeft + $track.innerWidth() >= el.scrollWidth - 10) {
						el.scrollLeft = 0;
					} else {
						el.scrollLeft += 260;
					}
				}
			}, 3500);
		}

		$('.o100-product-card').each(function() {
			var pid = $(this).attr('data-product-id');
			if (!pid) return; // skip if undefined
			
			var qty = qtys[pid] ? parseInt(qtys[pid], 10) : 0;
			var $btn = $(this).find('.o100-add-btn');
			if ($btn.length === 0) return;
			
			if (qty > 0) {
				if (!$btn.hasClass('o100-qty-active')) {
					$btn.addClass('o100-qty-active');
				}
				$btn.html('<span class="o100-qty-text" style="color: #fff !important; font-size: 13px !important; font-weight: 800; display: inline-block; white-space: nowrap; line-height: 1;">' + qty + ' &times;</span>');
			} else {
				$btn.removeClass('o100-qty-active').html('<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>');
			}
		});
	}
	
	$(document.body).on('wc_fragments_refreshed wc_fragments_loaded', o100_frontend_optimizations);
	$(document).ready(function(){
		setTimeout(o100_frontend_optimizations, 300);
	});

	// Qty +/- via AJAX (Promise Queue to prevent server race conditions)
	var qtyTimers={}, wcRefreshTimer=null;
	var ajaxSeq = 0; 
	var cartQueue = jQuery.Deferred().resolve(); // Global queue
	
	// Inject SVGs dynamically to bypass wp_kses_post stripping!
	var trashSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"></path></svg>';
	function injectSvgs(){ $('.qty-trash-icon').each(function(){ if($(this).is(':empty')) $(this).html(trashSvg); }); }
	injectSvgs();

	// Restore sidecart class after WC finishes its background update
	$(document.body).on('wc_fragments_refreshed wc_fragments_loaded', function() {
		// No longer renaming class to allow full refresh
		injectSvgs();
	});

	// Promo Carousel Manual Navigation


	$(document).on('click','.o100-sc-plus,.o100-sc-minus',function(e){
		e.preventDefault();
		var $b=$(this),key=$b.data('key'),isPlus=$b.hasClass('o100-sc-plus');
		var $qty=$b.closest('.o100-sc-qty');
		var $val=$qty.find('.o100-sc-qty-val');
		var $minus=$qty.find('.o100-sc-minus');
		var cur=parseInt($val.text())||1;
		var nw=isPlus?cur+1:cur-1;
		
		if(nw<1){
			var $item=$b.closest('.o100-sc-item');
			$item.css({opacity:.4,pointerEvents:'none'});
			
			cartQueue = cartQueue.then(function() {
				return $.post('<?php echo admin_url("admin-ajax.php"); ?>',{action:'o100_update_cart_qty',cart_item_key:key,qty:0}).then(function(res){
					if(res.success && res.data) {
						if(res.data.cart_count === 0) {
							$('.o100-sc-body').replaceWith(res.data.cart_html); 
						} else {
							$item.slideUp(200, function(){ $(this).remove(); });
							$('.o100-sc-subtotal-val').html(res.data.subtotal); 
						}
						$('.o100-cart-count').text(res.data.cart_count);
						if (res.data.cart_html) {
							var $newBody = $(res.data.cart_html);
							$('.o100-sc-body').replaceWith($newBody);
							injectSvgs();
						}
						
						if (typeof o100_frontend_optimizations === 'function') {
							o100_frontend_optimizations();
						}
					}
					$(document.body).trigger('wc_fragment_refresh');
				}).catch(function(){ return $.Deferred().resolve(); });
			});
			return;
		}
		
		// Instant UI Update
		$val.text(nw);
		if(nw<=1){
			$minus.addClass('is-trash');
			injectSvgs(); // Make sure SVG is present
		}else{
			$minus.removeClass('is-trash');
		}
		$qty.addClass('is-loading');
		
		// Show loading spinner at subtotal
		if ($('.o100-sc-subtotal-val .o100-sc-spinner').length === 0) {
			$('.o100-sc-subtotal-val').append(' <svg class="o100-sc-spinner" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="4.93" x2="19.07" y2="7.76"></line></svg>');
		}
		
		if(qtyTimers[key])clearTimeout(qtyTimers[key]);
		// Wait 400ms after the user STOPS clicking before sending the AJAX
		qtyTimers[key]=setTimeout(function(){
			ajaxSeq++;
			var currentSeq = ajaxSeq;
			
			// Append AJAX to the promise queue to run sequentially
			cartQueue = cartQueue.then(function() {
				return $.post('<?php echo admin_url("admin-ajax.php"); ?>',{action:'o100_update_cart_qty',cart_item_key:key,qty:nw}).then(function(res){
					if(currentSeq !== ajaxSeq) return; // Superceded by newer click
					
					$qty.removeClass('is-loading');
					if(res.success && res.data){
						$('.o100-sc-subtotal-val').html(res.data.subtotal);
						$('.o100-cart-count').text(res.data.cart_count);
						if (res.data.cart_html) {
							var $newBody = $(res.data.cart_html);
							$('.o100-sc-body').replaceWith($newBody);
							injectSvgs();
						}
					}
					
					// Re-bind optimizations immediately
					if (typeof o100_frontend_optimizations === 'function') {
						o100_frontend_optimizations();
					}
					
					if(wcRefreshTimer) clearTimeout(wcRefreshTimer);
					wcRefreshTimer=setTimeout(function(){ 
						$(document.body).trigger('wc_fragment_refresh'); 
					}, 800);
					
				}).fail(function(){
					$qty.removeClass('is-loading');
				});
			});
		}, 300);
	});

	// Upsell & Promo carousel nav (Infinite Loop)
	$(document).on('click', '.o100-sc-upsell-prev, .o100-sc-promo-prev', function(e) {
		e.preventDefault();
		var track = $(this).closest('.o100-sc-upsells, .o100-sc-promos').find('.o100-sc-upsells-track, .o100-sc-promos-track');
		if(track.children().length <= 1) return;
		var last = track.children().last();
		var w = last.outerWidth(true);
		track.prepend(last);
		track.scrollLeft(w);
		track.animate({scrollLeft: 0}, 250);
	});
	$(document).on('click', '.o100-sc-upsell-next, .o100-sc-promo-next', function(e) {
		e.preventDefault();
		var track = $(this).closest('.o100-sc-upsells, .o100-sc-promos').find('.o100-sc-upsells-track, .o100-sc-promos-track');
		if(track.children().length <= 1) return;
		var first = track.children().first();
		var w = first.outerWidth(true);
		track.animate({scrollLeft: w}, 250, function() {
			track.append(first);
			track.scrollLeft(0);
		});
	});

	// Upsell card click -> open product modal
	$(document).on('click','.o100-sc-upsell-card',function(e){
		if ($(e.target).closest('.o100-sc-upsell-add').length) return; // ignore clicks on the Add button
		
		var id=$(this).data('id');
		var $modal = $('#o100-product-modal');
		$modal.find('.o100-modal-body').html('<div style="padding:40px; text-align:center;"><svg class="o100-sc-spinner" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="4.93" x2="19.07" y2="7.76"></line></svg></div>');
		$modal.css('display', 'flex');
		
		$.post('<?php echo admin_url("admin-ajax.php"); ?>', {
			action: 'o100_get_product_quickview',
			product_id: id
		}, function(res) {
			if (res.success) {
				$modal.find('.o100-modal-body').html(res.data.html);
				// Re-init WC variation scripts if needed
				if (typeof $.fn.wc_variation_form !== 'undefined') {
					$modal.find('.variations_form').wc_variation_form();
				}
			}
		});
	});

	$(document).on('click', '.o100-modal-close, #o100-product-modal', function(e) {
		if (e.target === this) {
			$('#o100-product-modal').hide();
		}
	});

	// Quick Add to Cart Button on Upsell Card
	$(document).on('click', '.o100-sc-upsell-add', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var pid = $btn.data('product_id');
		$btn.html('<svg class="o100-sc-spinner" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="4.93" x2="19.07" y2="7.76"></line></svg>');
		
		$.post('<?php echo admin_url("admin-ajax.php"); ?>', {
			action: 'o100_update_cart_qty',
			cart_item_key: '', 
			product_id: pid,
			qty: 1
		}, function(res) {
			if(res.success) {
				$btn.html('✓');
				setTimeout(function(){ $btn.html('+'); }, 2000);
				$(document.body).trigger('wc_fragment_refresh');
			} else {
				$btn.html('+');
			}
		});
	});

	// Auto-refresh fragments after add/remove
	$(document.body).on('added_to_cart', function(){
		setTimeout(function(){$(document.body).trigger('wc_fragment_refresh')},100);
		// No longer auto-opening side cart when item is added per user request
	});
	$(document.body).on('removed_from_cart', function(){
		setTimeout(function(){$(document.body).trigger('wc_fragment_refresh')},100);
	});
});
</script>
	<?php }

	// ========================================================================

}