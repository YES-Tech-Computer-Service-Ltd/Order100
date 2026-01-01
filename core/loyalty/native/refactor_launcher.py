import re

file_path = "/Users/kevinqi/development/antigravity/order100/core/loyalty/native/class-o100-native-launcher.php"
with open(file_path, "r") as f:
    data = f.read()

# Replace '?wlr_ref=' with '?o100_ref='
data = data.replace("?wlr_ref=", "?o100_ref=")

# Replace footer branding
branding_old = '<div class="o100-lp-branding">Powered by <strong>Order100</strong></div>'
branding_new = '<div class="o100-lp-branding" style="font-size:12px;"><a href="https://order100.ca" target="_blank" style="text-decoration:none; color:inherit;">Powered by <strong style="font-size:13px; font-weight:800; color:var(--o100-tc);">Order100</strong></a></div>'
data = data.replace(branding_old, branding_new)

# Replace referral section with more icons
referral_pattern = re.compile(r'<!-- Referral Section -->.*?</div>\s*<\?php endif; \?>', re.DOTALL)
referral_new = """<!-- Referral Section -->
						<div class="o100-lp-referral-card">
							<strong>Refer and earn</strong>
							<p><?php echo esc_html($ref_desc); ?></p>
							<?php if ($is_logged_in): ?>
							<div style="display:flex; gap:0; border-radius:6px; overflow:hidden; border:1px solid #e2e8f0; margin-top:12px; margin-bottom:12px;">
								<input type="text" value="<?php echo esc_attr($referral_url); ?>" readonly style="flex:1; border:none; padding:8px 10px; font-size:12px; background:#f8fafc; color:#334155; outline:none; margin:0; width:100%;">
								<button style="border:none; background:var(--o100-tc); color:#fff; font-weight:600; font-size:12px; padding:0 12px; cursor:pointer;" onclick="navigator.clipboard.writeText('<?php echo esc_js($referral_url); ?>');">Copy</button>
							</div>
							<div style="display:flex; gap:12px; justify-content:center;">
								<a href="https://api.whatsapp.com/send?text=<?php echo urlencode($referral_url); ?>" target="_blank" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:var(--o100-tc); text-decoration:none; border:1px solid #e2e8f0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg></a>
								<a href="mailto:?body=<?php echo urlencode($referral_url); ?>" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:var(--o100-tc); text-decoration:none; border:1px solid #e2e8f0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></a>
								<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_url); ?>" target="_blank" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:var(--o100-tc); text-decoration:none; border:1px solid #e2e8f0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></a>
								<a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($referral_url); ?>" target="_blank" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:var(--o100-tc); text-decoration:none; border:1px solid #e2e8f0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path></svg></a>
								<a href="https://reddit.com/submit?url=<?php echo urlencode($referral_url); ?>" target="_blank" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#fff; color:var(--o100-tc); text-decoration:none; border:1px solid #e2e8f0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px; height:16px;"><path d="M22.54 11.12a2.82 2.82 0 0 0-4.66-1.57c-1.84-1.28-4.32-2.1-7.07-2.19l1.49-4.8 4.21 1.25a2.53 2.53 0 0 0 4.8-1.16 2.54 2.54 0 0 0-5-1l-4.52-1.34a1 1 0 0 0-1.25.68l-1.68 5.4c-2.82.1-5.38.93-7.25 2.23a2.82 2.82 0 0 0-4.66 1.58c0 1.07.6 2 1.5 2.5a5.55 5.55 0 0 0-.15 1.25c0 3.73 4.26 6.75 9.5 6.75s9.5-3.02 9.5-6.75a5.13 5.13 0 0 0-.14-1.25c.9-.5 1.49-1.42 1.49-2.5zM7.5 13.88a1.62 1.62 0 1 1 1.62-1.62A1.62 1.62 0 0 1 7.5 13.88zm4.5 5.25c-2.34 0-4.22-.84-4.22-1.88 0-.25.26-.47.66-.62a3.83 3.83 0 0 0 3.56.88 3.83 3.83 0 0 0 3.56-.88c.4.15.66.37.66.62 0 1.04-1.88 1.88-4.22 1.88zm4.5-5.25a1.62 1.62 0 1 1 1.62-1.62A1.62 1.62 0 0 1 16.5 13.88z"></path></svg></a>
							</div>
							<?php else: ?>
							<div style="margin-top:12px; font-size:12px;"><a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" style="color:var(--o100-tc); font-weight:600;">Sign in</a> to get your link</div>
							<?php endif; ?>
						</div>
						<?php endif; ?>"""
data = referral_pattern.sub(referral_new, data)

# Fix Earn List missing title and icons
earn_loop_pattern = re.compile(r'<\?php if \(\$earn_campaigns\) : foreach \(\$earn_campaigns as \$c\) :.*?<span class="o100-lp-nav-arrow">›</span>\s*</div>\s*<\?php endforeach; else : \?>', re.DOTALL)
earn_loop_replacement = """						<?php if ($earn_campaigns) : foreach ($earn_campaigns as $c) :
							$ui = json_decode($c->ui_json, true) ?: [];
							$is_pc = isset($c->type) && $c->type === 'o100_punch_card';
							$earn_pt = isset($ui['earn_point']) ? $ui['earn_point'] : '';
							$desc = strip_tags($c->description);
							$points_text = $is_pc ? 'Free Reward' : '+' . $earn_pt . ' Points';
							
							// Fix missing titles
							$title = $c->name;
							if ( empty($title) ) {
								$title = !empty($ui['campaign_title_discount']) ? $ui['campaign_title_discount'] : 
										 (!empty($ui['campaign_title']) ? $ui['campaign_title'] : 'Campaign');
							}

							// Assign Icon
							$icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--o100-tc)" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v12M6 12h12"/></svg>';
							if ($is_pc) {
								$icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--o100-tc)" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>'; // stamp/calendar
							} else if ($c->type === 'birthday') {
								$icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--o100-tc)" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>'; // user/bday
							} else if ($c->type === 'signup') {
								$icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--o100-tc)" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>'; // signup
							}
						?>
						<div class="o100-lp-activity-card" data-goto="detail" data-target="detail-<?php echo esc_attr($c->id); ?>" data-back="earn">
							<div class="o100-lp-act-icon" style="background:#f0fdf4;">
								<?php echo $icon_svg; ?>
							</div>
							<div class="o100-lp-act-info">
								<strong><?php echo esc_html($title); ?></strong>
								<span><?php echo esc_html($points_text); ?><?php echo $desc ? ' · ' . esc_html(wp_trim_words($desc, 6)) : ''; ?></span>
							</div>
							<span class="o100-lp-nav-arrow">›</span>
						</div>
						<?php endforeach; else : ?>"""
data = earn_loop_pattern.sub(earn_loop_replacement, data)

# Fix Redeem List missing title and update data-target
redeem_loop_pattern = re.compile(r'<\?php if \(\$redeem_campaigns\) : foreach \(\$redeem_campaigns as \$r\) :.*?<span class="o100-lp-nav-arrow">›</span>\s*</div>\s*<\?php endforeach; else : \?>', re.DOTALL)
redeem_loop_replacement = """						<?php if ($redeem_campaigns) : foreach ($redeem_campaigns as $r) :
							$ui = json_decode($r->ui_json, true) ?: [];
							$req_pt = floatval($ui['conversion_points'] ?? 100);
							$reward_type = $ui['conversion_reward_type'] ?? 'discount';
							$disc_val = floatval($ui['conversion_value'] ?? 1);
							$r_name = ($reward_type === 'free_item') ? 'Free ' . get_the_title($ui['freeitem_product'] ?? 0) : wc_price($disc_val) . ' Off Discount';
							if (!empty($r->name)) $r_name = $r->name;
							$r_desc = strip_tags($r->description);
						?>
						<div class="o100-lp-activity-card" data-goto="detail" data-target="detail-<?php echo esc_attr($r->id); ?>" data-back="redeem">
							<div class="o100-lp-act-icon" style="background:#fef2f2;">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--o100-tc)" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect></svg>
							</div>
							<div class="o100-lp-act-info">
								<strong><?php echo wp_kses_post($r_name); ?></strong>
								<span><?php echo esc_html($req_pt); ?> Points required</span>
							</div>
							<span class="o100-lp-nav-arrow">›</span>
						</div>
						<?php endforeach; else : ?>"""
data = redeem_loop_pattern.sub(redeem_loop_replacement, data)

# Rewrite Level 3: Details 
# We replace the single level 3 block with a PHP loop generating one block per campaign!
level3_pattern = re.compile(r'<!-- Level 3: Detail -->.*?</div>\s*</div>\s*</div>\s*</div>', re.DOTALL)

level3_replacement = """				<!-- Level 3: Dynamic Details -->
				<?php 
				$all_campaigns = array_merge( is_array($earn_campaigns) ? $earn_campaigns : [], is_array($redeem_campaigns) ? $redeem_campaigns : [] );
				if ( !empty($all_campaigns) ) : foreach ( $all_campaigns as $c ) : 
					$is_pc = isset($c->type) && $c->type === 'o100_punch_card';
					$ui = json_decode($c->ui_json, true) ?: [];
					$title = $c->name ?: ($ui['campaign_title_discount'] ?? ($ui['campaign_title'] ?? 'Campaign'));
					$desc = $c->description;
					$is_redeem = ($c->type === 'points_conversion');
				?>
				<div class="o100-lp-level" data-level="detail-<?php echo esc_attr($c->id); ?>" style="display:none;">
					<div class="o100-lp-header">
						<button class="o100-lp-back" data-back="<?php echo $is_redeem ? 'redeem' : 'earn'; ?>">&larr;</button>
						<span class="o100-lp-title">Details</span>
						<button class="o100-lp-close">&times;</button>
					</div>
					<div class="o100-lp-body">
						<div class="o100-lp-detail-content">
							<h3 class="o100-lp-detail-name" style="margin-top:10px;"><?php echo esc_html($title); ?></h3>
							<p class="o100-lp-detail-desc"><?php echo wp_kses_post($desc); ?></p>
							
							<!-- Interactive Blocks -->
							<div style="margin-top:20px; text-align:left;">
							<?php if ( !$is_logged_in ): ?>
								<div style="text-align:center; padding:20px 0;">
									<p style="margin-bottom:12px; font-size:14px;">Sign in to participate.</p>
									<a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="o100-lp-btn o100-lp-shape-pill" style="display:inline-block; text-decoration:none;">Sign In</a>
								</div>
							<?php else: ?>
								<?php if ( $c->type === 'birthday' ) : 
									$birthday_val = get_user_meta( get_current_user_id(), 'o100_birthday', true );
								?>
									<?php if ( $birthday_val ) : ?>
										<div style="background:#f0fdf4; border:1px solid #10b981; color:#10b981; padding:12px; border-radius:8px; text-align:center; font-weight:600;">Your Birthday: <?php echo esc_html($birthday_val); ?></div>
									<?php else : ?>
										<label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Enter your birthday:</label>
										<input type="date" id="o100-bday-input-<?php echo esc_attr($c->id); ?>" style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:12px;">
										<button class="o100-lp-btn o100-lp-shape-pill" style="width:100%; justify-content:center;" onclick="o100SaveBirthday(this, 'o100-bday-input-<?php echo esc_attr($c->id); ?>')">Save Birthday</button>
									<?php endif; ?>
								
								<?php elseif ( $is_pc ) : 
									$req_stamps = isset($ui['punch_count']) ? intval($ui['punch_count']) : 5;
									$stamp_icon = isset($ui['stamp_icon_url']) && !empty($ui['stamp_icon_url']) ? $ui['stamp_icon_url'] : '';
									if ( empty($stamp_icon) && defined('O100_PLUGIN_URL') ) $stamp_icon = O100_PLUGIN_URL . 'assets/images/stamp.svg';
									$stamps_data = class_exists('O100_Native_Punch_Card') ? O100_Native_Punch_Card::get_stamp_balance( get_current_user_id(), $c->id ) : null;
									$current_stamps = $stamps_data ? intval($stamps_data->stamps) : 0;
								?>
									<div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:12px;">
										<div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:8px; margin-bottom:16px;">
											<?php for($i=1; $i<=$req_stamps; $i++): $active = $i <= $current_stamps; ?>
												<div style="aspect-ratio:1; border-radius:50%; background:<?php echo $active ? 'rgba(225,29,72,0.1)' : '#e2e8f0'; ?>; display:flex; align-items:center; justify-content:center; border:2px solid <?php echo $active ? 'var(--o100-tc)' : 'transparent'; ?>;">
													<?php if ($active): ?>
														<?php if ($stamp_icon): ?><img src="<?php echo esc_url($stamp_icon); ?>" style="width:60%;height:60%;object-fit:contain;"><?php else: ?><span style="color:var(--o100-tc); font-weight:800; font-size:16px;">✓</span><?php endif; ?>
													<?php else: ?>
														<span style="color:#94a3b8; font-weight:600; font-size:12px;"><?php echo $i; ?></span>
													<?php endif; ?>
												</div>
											<?php endfor; ?>
										</div>
										<div style="text-align:center; font-size:13px; font-weight:600; color:#334155; margin-bottom:12px;">
											<?php echo esc_html($current_stamps); ?> of <?php echo esc_html($req_stamps); ?> collected
										</div>
										<button class="o100-lp-btn o100-lp-shape-pill" style="width:100%; justify-content:center; <?php echo ($current_stamps >= $req_stamps) ? '' : 'opacity:0.5; cursor:not-allowed;'; ?>" <?php echo ($current_stamps >= $req_stamps) ? 'onclick="o100RedeemPunchCard('.$c->id.', this)"' : ''; ?>>Claim Reward</button>
									</div>

								<?php elseif ( $is_redeem ) : 
									$req_pt = floatval($ui['conversion_points'] ?? 100);
								?>
									<div style="text-align:center;">
										<div style="font-size:14px; font-weight:600; margin-bottom:12px;">Requires <?php echo esc_html($req_pt); ?> Points</div>
										<button class="o100-lp-btn o100-lp-shape-pill" style="width:100%; justify-content:center; <?php echo ($points >= $req_pt) ? '' : 'opacity:0.5; cursor:not-allowed;'; ?>" <?php echo ($points >= $req_pt) ? 'onclick="o100RedeemReward('.$c->id.', '.$req_pt.', this)"' : ''; ?>>Redeem Now</button>
									</div>
								<?php else : ?>
									<div style="text-align:center; padding:16px; background:#f8fafc; border-radius:12px; font-size:13px; color:#475569;">
										Automatically applied when condition is met.
									</div>
								<?php endif; ?>
							<?php endif; ?>
							</div>

						</div>
					</div>
					<!-- Branding -->
					<div class="o100-lp-branding" style="font-size:12px;"><a href="https://order100.ca" target="_blank" style="text-decoration:none; color:inherit;">Powered by <strong style="font-size:13px; font-weight:800; color:var(--o100-tc);">Order100</strong></a></div>
				</div>
				<?php endforeach; endif; ?>
			</div>
		</div>"""
data = level3_pattern.sub(level3_replacement, data)

# Rewrite JS Logic inside the script tag
js_pattern = re.compile(r'// Activity cards in level 2 → level 3 detail.*?// Back buttons', re.DOTALL)
js_replacement = """// Activity cards in level 2 → level 3 detail (Dynamic logic)
			panel.querySelectorAll(".o100-lp-activity-card[data-goto='detail']").forEach(function(card){
				card.addEventListener("click",function(){
					var target=this.getAttribute("data-target");
					panel.querySelectorAll(".o100-lp-level").forEach(function(l){l.style.display="none";});
					var detailPane = panel.querySelector('[data-level="'+target+'"]');
					if(detailPane) detailPane.style.display="flex";
				});
			});

			// Back buttons"""
data = js_pattern.sub(js_replacement, data)

# Inject AJAX functions into the script tag
ajax_funcs = """
		window.o100ShowToast = function(message, type) {
			type = type || "success";
			var toast = document.createElement("div");
			toast.style.position = "fixed";
			toast.style.bottom = "20px";
			toast.style.right = "20px";
			toast.style.background = type === "success" ? "#10b981" : "#ef4444";
			toast.style.color = "#fff";
			toast.style.padding = "12px 24px";
			toast.style.borderRadius = "8px";
			toast.style.boxShadow = "0 4px 12px rgba(0,0,0,0.15)";
			toast.style.zIndex = "999999";
			toast.style.fontFamily = "system-ui, -apple-system, sans-serif";
			toast.style.fontSize = "14px";
			toast.style.fontWeight = "600";
			toast.style.transition = "all 0.3s ease";
			toast.style.transform = "translateY(50px)";
			toast.style.opacity = "0";
			toast.innerText = message;
			document.body.appendChild(toast);
			setTimeout(function() { toast.style.transform = "translateY(0)"; toast.style.opacity = "1"; }, 10);
			setTimeout(function() { toast.style.transform = "translateY(50px)"; toast.style.opacity = "0"; }, 3000);
			setTimeout(function() { toast.remove(); }, 3300);
		};

		window.o100SaveBirthday = function(btn, inputId) {
			var dateVal = document.getElementById(inputId).value;
			if (!dateVal) return;
			var origText = btn.innerText;
			btn.innerText = "Saving...";
			btn.style.opacity = "0.7";
			btn.style.pointerEvents = "none";
			jQuery.post("<?php echo admin_url('admin-ajax.php'); ?>", {
				action: "o100_save_birthday",
				birth_date: dateVal
			}, function(res) {
				if (res.success) {
					o100ShowToast("Birthday saved successfully!");
					setTimeout(function() { location.reload(); }, 1500);
				} else {
					o100ShowToast(res.data || "Failed to save.", "error");
					btn.innerText = origText;
					btn.style.opacity = "1";
					btn.style.pointerEvents = "auto";
				}
			}).fail(function() {
				o100ShowToast("Network error.", "error");
				btn.innerText = origText;
				btn.style.opacity = "1";
				btn.style.pointerEvents = "auto";
			});
		};

		window.o100RedeemPunchCard = function(campaignId, btn) {
			if (!campaignId) return;
			var origText = btn.innerText;
			btn.innerText = "Claiming...";
			btn.style.opacity = "0.7";
			btn.style.pointerEvents = "none";
			jQuery.post("<?php echo admin_url('admin-ajax.php'); ?>", {
				action: "o100_native_redeem_punch_card",
				campaign_id: campaignId,
				nonce: "<?php echo wp_create_nonce('o100_loyalty'); ?>"
			}, function(res) {
				if (res.success) {
					o100ShowToast("Reward claimed! Added to your account.");
					setTimeout(function() { location.reload(); }, 2000);
				} else {
					o100ShowToast(res.data || "Error claiming reward.", "error");
					btn.innerText = origText;
					btn.style.opacity = "1";
					btn.style.pointerEvents = "auto";
				}
			}).fail(function() {
				o100ShowToast("Network error.", "error");
				btn.innerText = origText;
				btn.style.opacity = "1";
				btn.style.pointerEvents = "auto";
			});
		};

		window.o100RedeemReward = function(rewardId, points, btn) {
			var origText = btn.innerText;
			btn.innerText = "Redeeming...";
			btn.style.opacity = "0.7";
			btn.style.pointerEvents = "none";
			jQuery.post("<?php echo admin_url('admin-ajax.php'); ?>", {
				action: "o100_native_redeem_points",
				campaign_id: rewardId,
				points: points,
				nonce: "<?php echo wp_create_nonce('o100_loyalty'); ?>"
			}, function(res) {
				if (res.success) {
					o100ShowToast("Reward redeemed successfully!");
					setTimeout(function() { location.reload(); }, 2000);
				} else {
					o100ShowToast(res.data || "Error redeeming reward.", "error");
					btn.innerText = origText;
					btn.style.opacity = "1";
					btn.style.pointerEvents = "auto";
				}
			}).fail(function() {
				o100ShowToast("Network error.", "error");
				btn.innerText = origText;
				btn.style.opacity = "1";
				btn.style.pointerEvents = "auto";
			});
		};
"""

data = data.replace('document.addEventListener("DOMContentLoaded",function(){', ajax_funcs + '\n\t\tdocument.addEventListener("DOMContentLoaded",function(){')

with open(file_path, "w") as f:
    f.write(data)
