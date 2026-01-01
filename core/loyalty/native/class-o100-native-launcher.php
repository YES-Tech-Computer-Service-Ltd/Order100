<?php
/**
 * O100 Native Loyalty Launcher
 * 3-level navigation popup: Home → Earn/Redeem list → Detail
 * @package Order100
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class O100_Native_Launcher {

	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'render_launcher' ) );
	}

	public static function get_icon_svg( $icon ) {
		switch ( $icon ) {
			case 'cart':
				return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>';
			case 'star':
				return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
			case 'crown':
				return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path></svg>';
			case 'gift':
			default:
				return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>';
		}
	}

	public static function render_launcher() {
		if ( is_admin() ) return;

		$opts = get_option( 'o100_portal', array() );
		// Smart detection: skip if no active loyalty campaigns
		global $wpdb;
		$lc_table = $wpdb->prefix . 'o100_loyalty_campaigns';
		$has_campaigns = false;
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$lc_table}'") === $lc_table ) {
			$has_campaigns = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$lc_table} WHERE status = 'active'") > 0;
		}
		if ( ! $has_campaigns ) return;

		$theme_color   = !empty($opts['o100_portal_theme_color']) ? $opts['o100_portal_theme_color'] : '#e11d48';
		$btn_text_color= !empty($opts['o100_portal_btn_text_color']) ? $opts['o100_portal_btn_text_color'] : '#ffffff';
		$btn_text      = !empty($opts['o100_portal_launcher_text']) ? $opts['o100_portal_launcher_text'] : 'Rewards';
		$position      = !empty($opts['o100_portal_launcher_position']) ? $opts['o100_portal_launcher_position'] : 'bottom-right';
		$display_style = !empty($opts['o100_portal_launcher_style']) ? $opts['o100_portal_launcher_style'] : 'icon_text';
		$icon          = !empty($opts['o100_portal_launcher_icon']) ? $opts['o100_portal_launcher_icon'] : 'gift';
		$shape         = !empty($opts['o100_portal_launcher_shape']) ? $opts['o100_portal_launcher_shape'] : 'pill';
		$spacing       = !empty($opts['o100_portal_launcher_spacing']) ? $opts['o100_portal_launcher_spacing'] : '20';
		$guest_title   = !empty($opts['o100_portal_guest_title']) ? $opts['o100_portal_guest_title'] : 'Join and Earn Rewards';
		$guest_sub     = !empty($opts['o100_portal_guest_subtitle']) ? $opts['o100_portal_guest_subtitle'] : 'Get exclusive perks by becoming a member of our rewards program.';
		$guest_btn     = !empty($opts['o100_portal_guest_btn_text']) ? $opts['o100_portal_guest_btn_text'] : 'Join Now!';

		if ( strpos($position, 'middle') !== false || $position === 'hidden' ) {
			$position = 'bottom-right';
		}

		$is_logged_in = is_user_logged_in();
		$points = 0; $level_name = 'Member'; $display_name = '';
		if ( $is_logged_in ) {
			$user = wp_get_current_user();
			$display_name = $user->display_name;
			$account = O100_Loyalty_DB::get_account( get_current_user_id() );
			if ( $account ) {
				$points = $account->points_balance;
				if ( $account->level_id ) {
					$level = O100_Loyalty_DB::get_level( $account->level_id );
					if ( $level ) $level_name = $level->name;
				}
			}
		}

		// Fetch campaigns by type
		$table = $wpdb->prefix . 'o100_loyalty_campaigns';
				$earn_campaigns   = $wpdb->get_results("SELECT * FROM {$table} WHERE type NOT IN ('points_conversion','punch_card','referral') AND status = 'active' ORDER BY id ASC");
		if ( !is_array($earn_campaigns) ) $earn_campaigns = [];
		if ( class_exists('O100_Native_Punch_Card') ) {
			$ncs = O100_Native_Punch_Card::get_active_punch_cards();
			foreach ($ncs as $nc) {
				$ui = json_decode($nc->ui_json ?: '', true) ?: [];
				$req = isset($ui['punch_count']) ? intval($ui['punch_count']) : 5;
				$mock = new stdClass();
				$mock->type = 'o100_punch_card';
				$mock->id = $nc->id;
				$mock->title = !empty($nc->title) ? $nc->title : (!empty($nc->name) ? $nc->name : '');
				$mock->name = $mock->title; // For backwards compatibility
				$mock->ui_json = $nc->ui_json;
				$mock->conditions_json = $nc->conditions_json;
				$mock->description = "Buy $req participating items to get a free reward.";
				array_unshift($earn_campaigns, $mock);
			}
		}
		$redeem_campaigns = $wpdb->get_results("SELECT * FROM {$table} WHERE type = 'points_conversion' AND status = 'active' ORDER BY id ASC");
		$referral_campaign = $wpdb->get_row("SELECT * FROM {$table} WHERE type = 'referral' AND status = 'active' LIMIT 1");

		$pos_class = (strpos($position, 'left') !== false) ? 'o100-lp-left' : 'o100-lp-right';
		$btn_classes = 'o100-lp-btn o100-lp-shape-' . $shape;
		if ($display_style === 'icon_only') $btn_classes .= ' o100-lp-icon-only';
		?>
		<div id="o100-loyalty-launcher" class="<?php echo esc_attr($pos_class); ?>" style="--o100-tc:<?php echo esc_attr($theme_color); ?>;--o100-btc:<?php echo esc_attr($btn_text_color); ?>;--o100-sp:<?php echo esc_attr($spacing); ?>px;">
			<button id="o100-lp-fab" class="<?php echo esc_attr($btn_classes); ?>">
				<?php if ($display_style !== 'text_only') : ?>
				<span class="o100-lp-icon"><?php echo self::get_icon_svg($icon); ?></span>
				<?php endif; ?>
				<?php if ($display_style !== 'icon_only') : ?>
				<span class="o100-lp-text"><?php echo esc_html($btn_text); ?></span>
				<?php endif; ?>
			</button>

			<div id="o100-lp-panel" class="o100-lp-panel">
				<!-- Level 1: Home -->
				<div class="o100-lp-level" data-level="home">
					<div class="o100-lp-header">
						<span class="o100-lp-title">Rewards</span>
						<button class="o100-lp-close">&times;</button>
					</div>
					<div class="o100-lp-body">
						<?php if ( $is_logged_in ) : ?>
						<div class="o100-lp-welcome-card">
							<div class="o100-lp-welcome-name">Welcome, <?php echo esc_html($display_name); ?>!</div>
							<div class="o100-lp-welcome-pts"><?php echo esc_html($points); ?> <small>Points</small></div>
							<div class="o100-lp-welcome-level"><?php echo esc_html($level_name); ?></div>
						</div>
						<?php else : ?>
						<div class="o100-lp-guest-card">
							<h4><?php echo esc_html($guest_title); ?></h4>
							<p><?php echo esc_html($guest_sub); ?></p>
							<a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="o100-lp-join-btn"><?php echo esc_html($guest_btn); ?></a>
							<div class="o100-lp-signin">Already have an account? <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"><strong>Sign in</strong></a></div>
						</div>
						<?php endif; ?>

						<!-- Earn / Redeem Nav Cards -->
						<div class="o100-lp-nav-card" data-goto="earn">
							<div class="o100-lp-nav-icon" style="background:#f0fdf4;">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--o100-tc)" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v12M6 12h12"/></svg>
							</div>
							<div class="o100-lp-nav-info">
								<strong>Earn</strong>
								<span>Complete tasks to earn points</span>
							</div>
							<span class="o100-lp-nav-arrow">›</span>
						</div>
						<div class="o100-lp-nav-card" data-goto="redeem">
							<div class="o100-lp-nav-icon" style="background:#fef2f2;">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--o100-tc)" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
							</div>
							<div class="o100-lp-nav-info">
								<strong>Redeem</strong>
								<span>Use points for rewards</span>
							</div>
							<span class="o100-lp-nav-arrow">›</span>
						</div>

												<?php if ( $referral_campaign ) :
							$ref_desc = strip_tags($referral_campaign->description) ?: 'Refer your friends and earn rewards. Your friend can get a reward as well!';
							$referral_url = $is_logged_in ? site_url('/?o100_ref=' . get_current_user_id()) : '';
						?>
						<!-- Referral Section -->
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
						<?php endif; ?>
					</div>
					<!-- Branding -->
					<div class="o100-lp-branding" style="font-size:12px;"><a href="https://order100.ca" target="_blank" style="text-decoration:none; color:inherit;">Powered by <strong style="font-size:13px; font-weight:800; color:var(--o100-tc);">Order100</strong></a></div>
				</div>

				<!-- Level 2: Earn List -->
				<div class="o100-lp-level" data-level="earn" style="display:none;">
					<div class="o100-lp-header">
						<button class="o100-lp-back" data-back="home">&larr;</button>
						<span class="o100-lp-title">Earn</span>
						<button class="o100-lp-close">&times;</button>
					</div>
					<div class="o100-lp-body">
						<?php if ($earn_campaigns) : foreach ($earn_campaigns as $c) :
							$ui = json_decode($c->ui_json ?: '', true) ?: [];
							$is_pc = isset($c->type) && $c->type === 'o100_punch_card';
							$earn_pt = isset($ui['earn_point']) ? $ui['earn_point'] : '';
							$desc = strip_tags($c->description);
							$points_text = $is_pc ? 'Free Reward' : '+' . $earn_pt . ' Points';
							
							// Fix missing titles (DB column is `title`, not `name`)
							$title = !empty($c->title) ? $c->title : (!empty($c->name) ? $c->name : '');
							if ( empty($title) ) {
								$title = !empty($ui['campaign_title_discount']) ? $ui['campaign_title_discount'] : 
										 (!empty($ui['campaign_title']) ? $ui['campaign_title'] : 'Campaign');
							}

							// Assign Icon and Color
							$icon_bg = '#f3f4f6'; $icon_color = '#6b7280';
							$icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v8"></path><path d="M10.5 9.5l3-1.5"></path><path d="M10.5 14.5l3 1.5"></path></svg>';
							
							if ($is_pc) {
								$icon_bg = '#f3e8ff'; $icon_color = '#9333ea';
								$icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2H6c-1.1 0-2 .9-2 2z"></path><line x1="8" y1="5" x2="8" y2="19"></line><line x1="16" y1="5" x2="16" y2="19"></line></svg>'; // ticket
							} else if ($c->type === 'birthday') {
								$icon_bg = '#fce7f3'; $icon_color = '#db2777';
								$icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>'; // gift
							} else if ($c->type === 'signup') {
								$icon_bg = '#fef3c7'; $icon_color = '#d97706';
								$icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>'; // user-plus
							} else if ($c->type === 'point_for_purchase' || strpos($c->type, 'point') !== false) {
								$icon_bg = '#e0e7ff'; $icon_color = '#4f46e5';
								$icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>'; // star
							}
						?>
						<div class="o100-lp-activity-card" data-goto="detail" data-target="detail-<?php echo esc_attr($c->id); ?>" data-back="earn">
							<div class="o100-lp-act-icon" style="background:<?php echo esc_attr($icon_bg); ?>; color:<?php echo esc_attr($icon_color); ?>;">
								<?php echo $icon_svg; ?>
							</div>
							<div class="o100-lp-act-info">
								<strong><?php echo esc_html($title); ?></strong>
								<span><?php echo esc_html($points_text); ?><?php echo $desc ? ' · ' . esc_html(wp_trim_words($desc, 6)) : ''; ?></span>
							</div>
							<span class="o100-lp-nav-arrow">›</span>
						</div>
						<?php endforeach; else : ?>
						<div class="o100-lp-empty">No earn campaigns available yet.</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Level 2: Redeem List -->
				<div class="o100-lp-level" data-level="redeem" style="display:none;">
					<div class="o100-lp-header">
						<button class="o100-lp-back" data-back="home">&larr;</button>
						<span class="o100-lp-title">Redeem</span>
						<button class="o100-lp-close">&times;</button>
					</div>
					<div class="o100-lp-body">
						<?php if ($redeem_campaigns) : foreach ($redeem_campaigns as $r) :
							$ui = json_decode($r->ui_json, true) ?: [];
							$req_pt = floatval($ui['conversion_points'] ?? 100);
							$reward_type = $ui['conversion_reward_type'] ?? 'discount';
							$disc_val = floatval($ui['conversion_value'] ?? 1);
							$r_name = ($reward_type === 'free_item') ? 'Free ' . get_the_title($ui['freeitem_product'] ?? 0) : wc_price($disc_val) . ' Off Discount';
							if (!empty($r->title)) $r_name = $r->title;
							else if (!empty($r->name)) $r_name = $r->name;
							
							$r_desc = strip_tags($r->description);
						?>
						<div class="o100-lp-activity-card" data-goto="detail" data-target="detail-<?php echo esc_attr($r->id); ?>" data-back="redeem">
							<div class="o100-lp-act-icon" style="background:#fce7f3; color:#db2777;">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
							</div>
							<div class="o100-lp-act-info">
								<strong><?php echo wp_kses_post($r_name); ?></strong>
								<span><?php echo esc_html($req_pt); ?> Points required</span>
							</div>
							<span class="o100-lp-nav-arrow">›</span>
						</div>
						<?php endforeach; else : ?>
						<div class="o100-lp-empty">No rewards available yet.</div>
						<?php endif; ?>
					</div>
				</div>

								<!-- Level 3: Dynamic Details -->
				<?php 
				$all_campaigns = array_merge( is_array($earn_campaigns) ? $earn_campaigns : [], is_array($redeem_campaigns) ? $redeem_campaigns : [] );
				if ( !empty($all_campaigns) ) : foreach ( $all_campaigns as $c ) : 
					$is_pc = isset($c->type) && $c->type === 'o100_punch_card';
					$ui = json_decode($c->ui_json ?: '', true) ?: [];
					
					$title = !empty($c->title) ? $c->title : (!empty($c->name) ? $c->name : '');
					if ( empty($title) ) {
						$title = !empty($ui['campaign_title_discount']) ? $ui['campaign_title_discount'] : 
								 (!empty($ui['campaign_title']) ? $ui['campaign_title'] : 'Campaign');
					}
					if ( $c->type === 'points_conversion' ) {
						$reward_type = $ui['conversion_reward_type'] ?? 'discount';
						$disc_val = floatval($ui['conversion_value'] ?? 1);
						$title = ($reward_type === 'free_item') ? 'Free ' . get_the_title($ui['freeitem_product'] ?? 0) : wc_price($disc_val) . ' Off Discount';
						if (!empty($c->title)) $title = $c->title;
						else if (!empty($c->name)) $title = $c->name;
					}

					$desc = $c->description;
					$is_redeem = ($c->type === 'points_conversion');

					$icon_bg = '#f3f4f6'; $icon_color = '#6b7280';
					$icon_svg = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v8"></path><path d="M10.5 9.5l3-1.5"></path><path d="M10.5 14.5l3 1.5"></path></svg>';
					
					if ($is_redeem) {
						$icon_bg = '#fce7f3'; $icon_color = '#db2777';
						$icon_svg = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>';
					} else if ($is_pc) {
						$icon_bg = '#f3e8ff'; $icon_color = '#9333ea';
						$icon_svg = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2H6c-1.1 0-2 .9-2 2z"></path><line x1="8" y1="5" x2="8" y2="19"></line><line x1="16" y1="5" x2="16" y2="19"></line></svg>'; // ticket
					} else if ($c->type === 'birthday') {
						$icon_bg = '#fce7f3'; $icon_color = '#db2777';
						$icon_svg = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>'; // gift
					} else if ($c->type === 'signup') {
						$icon_bg = '#fef3c7'; $icon_color = '#d97706';
						$icon_svg = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>'; // user-plus
					} else if ($c->type === 'point_for_purchase' || strpos($c->type, 'point') !== false) {
						$icon_bg = '#e0e7ff'; $icon_color = '#4f46e5';
						$icon_svg = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>'; // star
					}
				?>
				<div class="o100-lp-level" data-level="detail-<?php echo esc_attr($c->id); ?>" style="display:none;">
					<div class="o100-lp-header">
						<button class="o100-lp-back" data-back="<?php echo $is_redeem ? 'redeem' : 'earn'; ?>">&larr;</button>
						<span class="o100-lp-title">Details</span>
						<button class="o100-lp-close">&times;</button>
					</div>
					<div class="o100-lp-body">
						<div class="o100-lp-detail-content">
							<div class="o100-lp-detail-icon" style="background:<?php echo esc_attr($icon_bg); ?>; color:<?php echo esc_attr($icon_color); ?>;">
								<?php echo $icon_svg; ?>
							</div>
							<h3 class="o100-lp-detail-name"><?php echo esc_html($title); ?></h3>
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
									$user_id = get_current_user_id();
									$birthday_val = get_user_meta( $user_id, 'wlr_birthday_date', true );
									if ( empty($birthday_val) || $birthday_val === '0000-00-00' ) {
										$birthday_val = get_user_meta( $user_id, 'wlr_birth_date', true );
									}
									if ( empty($birthday_val) || $birthday_val === '0000-00-00' ) {
										$birthday_val = get_user_meta( $user_id, 'o100_birthday', true );
									}
								?>
									<?php if ( !empty($birthday_val) && $birthday_val !== '0000-00-00' ) : 
										$bday_time = strtotime($birthday_val);
										$current_year = date('Y');
										$bday_this_year = strtotime($current_year . '-' . date('m-d', $bday_time));
										
										$next_bday = $bday_this_year;
										if ( $bday_this_year < strtotime('today') ) {
											$next_bday = strtotime(($current_year + 1) . '-' . date('m-d', $bday_time));
										}
										
										$days_left = ceil(($next_bday - strtotime('today')) / 86400);
										$bday_msg = "Your Birthday: <strong>" . date('F j, Y', $bday_time) . "</strong><br><br>";
										
										if ($days_left == 0) {
											$bday_msg .= "🎉 <strong>Happy Birthday!</strong> Enjoy your special day! 🎂";
										} elseif ($days_left <= 3) {
											$bday_msg .= "🎉 <strong>Happy early Birthday!</strong> Only {$days_left} day(s) left! 🎁";
										} else {
											$bday_msg .= "Only <strong>{$days_left}</strong> day(s) until your birthday! 🎈";
										}
									?>
										<div style="background:#f0fdf4; border:1px solid #10b981; color:#065f46; padding:16px; border-radius:8px; text-align:center; font-size:14px; line-height:1.5;">
											<?php echo $bday_msg; ?>
										</div>
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
		</div>
		</div>
		<style>
		#o100-loyalty-launcher{position:fixed;z-index:999999;font-family:'Inter',-apple-system,sans-serif}
		.o100-lp-right{right:var(--o100-sp);bottom:var(--o100-sp)}
		.o100-lp-left{left:var(--o100-sp);bottom:var(--o100-sp)}
		.o100-lp-btn{background:var(--o100-tc);color:var(--o100-btc);border:none;border-radius:30px;padding:12px 24px;font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.18);display:flex;align-items:center;gap:8px;transition:transform .2s,box-shadow .2s}
		.o100-lp-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.25)}
		.o100-lp-icon-only{padding:0;width:56px;height:56px;justify-content:center;}
		.o100-lp-shape-pill{border-radius:30px}
		.o100-lp-shape-rounded{border-radius:12px}
		.o100-lp-shape-square{border-radius:0}
		.o100-lp-panel{position:absolute;bottom:65px;width:360px;max-height:80vh;background:#fff;border-radius:16px;box-shadow:0 12px 48px rgba(0,0,0,.18);overflow:hidden;display:none;flex-direction:column;opacity:0;transform:translateY(16px);transition:opacity .25s,transform .25s}
		.o100-lp-right .o100-lp-panel{right:0}
		.o100-lp-left .o100-lp-panel{left:0}
		.o100-lp-panel.active{display:flex;opacity:1;transform:translateY(0)}
		.o100-lp-level{width:100%;height:100%;flex-direction:column;display:none;}
		.o100-lp-header{background:var(--o100-tc);color:#fff;padding:18px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0}
		.o100-lp-title{font-size:17px;font-weight:800;flex:1}
		.o100-lp-close,.o100-lp-back{background:none;border:none;color:rgba(255,255,255,.8);font-size:22px;cursor:pointer;padding:0;line-height:1}
		.o100-lp-close:hover,.o100-lp-back:hover{color:#fff}
		.o100-lp-body{padding:16px;overflow-y:auto;flex:1;background:#f8fafc}
		.o100-lp-guest-card{background:var(--o100-tc);color:#fff;border-radius:12px;padding:24px 20px;text-align:center;margin-bottom:16px}
		.o100-lp-guest-card h4{margin:0 0 8px;font-size:17px;font-weight:800;color:#fff}
		.o100-lp-guest-card p{margin:0 0 16px;font-size:13px;opacity:.9;line-height:1.5}
		.o100-lp-join-btn{display:inline-block;background:#fff;color:var(--o100-tc);padding:10px 28px;border-radius:8px;font-weight:700;text-decoration:none;font-size:14px}
		.o100-lp-signin{margin-top:12px;font-size:12px;opacity:.85}
		.o100-lp-signin a{color:#fff;text-decoration:none}
		.o100-lp-welcome-card{background:var(--o100-tc);color:#fff;border-radius:12px;padding:24px 20px;text-align:center;margin-bottom:16px}
		.o100-lp-welcome-name{font-size:14px;opacity:.9;margin-bottom:4px}
		.o100-lp-welcome-pts{font-size:32px;font-weight:900;line-height:1.2}
		.o100-lp-welcome-pts small{font-size:14px;font-weight:600;opacity:.8}
		.o100-lp-welcome-level{font-size:12px;opacity:.75;margin-top:6px;text-transform:uppercase;letter-spacing:.5px}
		.o100-lp-nav-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:10px;display:flex;align-items:center;gap:14px;cursor:pointer;transition:box-shadow .15s,border-color .15s}
		.o100-lp-nav-card:hover{border-color:var(--o100-tc);box-shadow:0 2px 8px rgba(0,0,0,.06)}
		.o100-lp-nav-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
		.o100-lp-nav-info{flex:1;min-width:0}
		.o100-lp-nav-info strong{display:block;font-size:15px;color:#0f172a;margin-bottom:2px}
		.o100-lp-nav-info span{font-size:12px;color:#64748b}
		.o100-lp-nav-arrow{font-size:20px;color:#9ca3af;font-weight:300}
		.o100-lp-activity-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px;margin-bottom:8px;display:flex;align-items:center;gap:12px;cursor:pointer;transition:border-color .15s}
		.o100-lp-activity-card:hover{border-color:var(--o100-tc)}
		.o100-lp-act-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
		.o100-lp-act-info{flex:1;min-width:0}
		.o100-lp-act-info strong{display:block;font-size:14px;color:#0f172a;margin-bottom:2px}
		.o100-lp-act-info span{font-size:12px;color:#64748b}
		.o100-lp-referral-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:10px}
		.o100-lp-referral-card strong{display:block;font-size:14px;color:#0f172a;margin-bottom:4px}
		.o100-lp-referral-card p{margin:0;font-size:12px;color:#64748b;line-height:1.5}
		.o100-lp-detail-content{text-align:center;padding:30px 16px}
		.o100-lp-detail-icon{width:64px;height:64px;border-radius:16px;background:#fef2f2;margin:0 auto 16px;display:flex;align-items:center;justify-content:center}
		.o100-lp-detail-name{font-size:18px;font-weight:800;color:#0f172a;margin:0 0 8px}
		.o100-lp-detail-points{font-size:15px;font-weight:700;color:var(--o100-tc);margin-bottom:16px}
		.o100-lp-detail-desc{font-size:13px;color:#64748b;line-height:1.6;margin:0}
		.o100-lp-empty{text-align:center;padding:32px 16px;color:#9ca3af;font-size:13px}
		.o100-lp-dashboard-link{text-align:center;margin-top:12px}
		.o100-lp-dashboard-link a{color:var(--o100-tc);font-size:13px;font-weight:600;text-decoration:none}
		.o100-lp-branding{text-align:center;padding:12px 16px;border-top:1px solid #f1f5f9;font-size:11px;color:#94a3b8;background:#fff}
		.o100-lp-branding strong{color:#64748b;font-weight:700}
		</style>
		<script>
		
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
				birthday: dateVal,
				force: "1"
			}, function(res) {
				if (res.success && res.data && (res.data.status === 'success' || res.data.status === 'identical' || !res.data.status)) {
					o100ShowToast("Birthday saved successfully!");
					setTimeout(function() { location.reload(); }, 1500);
				} else {
					var errMsg = "Failed to save.";
					if (res.data && res.data.message) errMsg = res.data.message;
					else if (res.data && typeof res.data === 'string') errMsg = res.data;
					o100ShowToast(errMsg, "error");
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

		document.addEventListener("DOMContentLoaded",function(){
			var fab=document.getElementById("o100-lp-fab"),
				panel=document.getElementById("o100-lp-panel");
			if(!fab||!panel)return;

			fab.addEventListener("click",function(){
				if(panel.classList.contains("active")){
					panel.classList.remove("active");
				}else{
					panel.querySelectorAll(".o100-lp-level").forEach(function(l){l.style.display="none";});
					panel.querySelector('[data-level="home"]').style.display="flex";
					panel.classList.add("active");
				}
			});

			panel.querySelectorAll(".o100-lp-close").forEach(function(btn){
				btn.addEventListener("click",function(){panel.classList.remove("active");});
			});

			// Home nav cards → level 2 (earn/redeem list)
			panel.querySelectorAll(".o100-lp-nav-card[data-goto]").forEach(function(card){
				card.addEventListener("click",function(){
					var target=this.getAttribute("data-goto");
					panel.querySelectorAll(".o100-lp-level").forEach(function(l){l.style.display="none";});
					panel.querySelector('[data-level="'+target+'"]').style.display="flex";
				});
			});

			// Activity cards in level 2 → level 3 detail (Dynamic logic)
			panel.querySelectorAll(".o100-lp-activity-card[data-goto='detail']").forEach(function(card){
				card.addEventListener("click",function(){
					var target=this.getAttribute("data-target");
					panel.querySelectorAll(".o100-lp-level").forEach(function(l){l.style.display="none";});
					var detailPane = panel.querySelector('[data-level="'+target+'"]');
					if(detailPane) detailPane.style.display="flex";
				});
			});

			// Back buttons
			panel.querySelectorAll(".o100-lp-back").forEach(function(btn){
				btn.addEventListener("click",function(){
					var target=this.getAttribute("data-back");
					panel.querySelectorAll(".o100-lp-level").forEach(function(l){l.style.display="none";});
					panel.querySelector('[data-level="'+target+'"]').style.display="flex";
				});
			});
		});
		</script>
		<?php
	}
}
