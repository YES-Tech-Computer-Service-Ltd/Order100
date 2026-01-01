#!/usr/bin/env python3
"""
Apply ALL referral panel patches to class-o100-loyalty-proxy-admin.php in one shot.
Uses tempfile + os.rename for atomic write.
"""
import tempfile, os, shutil

SRC = '/Users/kevinqi/development/antigravity/order100/core/loyalty/admin/class-o100-loyalty-proxy-admin.php'

with open(SRC, 'r') as f:
    lines = f.readlines()

print(f"Original: {len(lines)} lines")

# ============================================================
# PATCH 1: Register AJAX action in init() — after handle_mcd_search
# ============================================================
for i, line in enumerate(lines):
    if "wp_ajax_o100_mcd_search" in line and "handle_mcd_search" in line:
        lines.insert(i+1, "\t\tadd_action( 'wp_ajax_o100_create_referral_coupon', [ __CLASS__, 'handle_create_referral_coupon' ] );\n")
        print(f"PATCH1: Added AJAX registration after line {i+1}")
        break

# ============================================================
# PATCH 2: Replace referral panel HTML (reward-panel-referral)
# Find lines with id="reward-panel-referral" to its closing </div>
# ============================================================
ref_start = ref_end = None
for i, line in enumerate(lines):
    if 'id="reward-panel-referral"' in line:
        ref_start = i
    if ref_start and i > ref_start:
        if 'id="reward-panel-points"' in line or 'id="reward-panel-discount"' in line:
            ref_end = i
            break

if ref_start is not None and ref_end is not None:
    new_panel = '''							<div id="reward-panel-referral" class="hidden bg-white border border-slate-200 rounded-xl p-6 shadow-sm mb-6">
							<h4 class="font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Referral Rewards</h4>
							<?php
							$ref_reward_options = [];
							$wc_coupons = get_posts(['post_type'=>'shop_coupon','post_status'=>'publish','posts_per_page'=>50,'orderby'=>'title','order'=>'ASC']);
							foreach ($wc_coupons as $wcc) { $c = new WC_Coupon($wcc->ID); $ref_reward_options[] = ['value'=>'wc_'.$wcc->ID, 'label'=>$wcc->post_title.' ('.$c->get_discount_type().': '.$c->get_amount().')', 'group'=>'WooCommerce Coupons']; }
							if (class_exists('O100_Promotions_DB')) { $promos = O100_Promotions_DB::query(['status'=>'active']); foreach ($promos as $p) { $cfg = json_decode($p['action_config']??'{}', true); $dt = $cfg['discount_type']??''; $dv = $cfg['discount_value']??''; $sum = $dt==='percentage' ? $dv.'%' : '$'.$dv; $ref_reward_options[] = ['value'=>'promo_'.$p['id'], 'label'=>$p['title'].' ('.$sum.')', 'group'=>'O100 Promotions']; } }
							?>
							<div class="grid grid-cols-2 gap-6 mb-4">
								<div class="p-4 bg-slate-50 rounded-lg">
									<h5 class="font-bold text-indigo-700 mb-3" style="border-left:3px solid #4F46E5; padding-left:8px;">Advocate (Referrer)</h5>
									<label class="block text-xs font-bold text-slate-700 mb-1">Reward Type</label>
									<select id="wizard_advocate_type" class="w-full mb-3 text-sm" onchange="o100RefToggle('advocate', this.value)">
										<option value="point">Points</option>
										<option value="coupon">Discount Coupon</option>
									</select>
									<div id="ref-advocate-points-panel">
										<label class="block text-xs font-bold text-slate-700 mb-1">Points Amount</label>
										<input type="number" id="wizard_advocate_amount" class="w-full text-sm" value="100">
									</div>
									<div id="ref-advocate-coupon-panel" style="display:none;">
										<label class="block text-xs font-bold text-slate-700 mb-1">Select Coupon</label>
										<select id="wizard_advocate_coupon" class="w-full mb-2 text-sm" onchange="o100RefCouponToggle('advocate', this.value)">
											<option value="">-- Select Existing Coupon --</option>
											<?php $cg=''; foreach($ref_reward_options as $ro){if($ro['group']!==$cg){if($cg) echo '</optgroup>'; echo '<optgroup label="'.esc_attr($ro['group']).'">'; $cg=$ro['group'];} echo '<option value="'.esc_attr($ro['value']).'">'.esc_html($ro['label']).'</option>';} if($cg) echo '</optgroup>'; ?>
											<option value="__custom__">-- Create Custom Coupon --</option>
										</select>
									</div>
								</div>
								<div class="p-4 bg-slate-50 rounded-lg">
									<h5 class="font-bold text-emerald-700 mb-3" style="border-left:3px solid #10B981; padding-left:8px;">Friend (Referred)</h5>
									<label class="block text-xs font-bold text-slate-700 mb-1">Reward Type</label>
									<select id="wizard_friend_type" class="w-full mb-3 text-sm" onchange="o100RefToggle('friend', this.value)">
										<option value="point">Points</option>
										<option value="coupon">Discount Coupon</option>
									</select>
									<div id="ref-friend-points-panel">
										<label class="block text-xs font-bold text-slate-700 mb-1">Points Amount</label>
										<input type="number" id="wizard_friend_amount" class="w-full text-sm" value="50">
									</div>
									<div id="ref-friend-coupon-panel" style="display:none;">
										<label class="block text-xs font-bold text-slate-700 mb-1">Select Coupon</label>
										<select id="wizard_friend_coupon" class="w-full mb-2 text-sm" onchange="o100RefCouponToggle('friend', this.value)">
											<option value="">-- Select Existing Coupon --</option>
											<?php $cg=''; foreach($ref_reward_options as $ro){if($ro['group']!==$cg){if($cg) echo '</optgroup>'; echo '<optgroup label="'.esc_attr($ro['group']).'">'; $cg=$ro['group'];} echo '<option value="'.esc_attr($ro['value']).'">'.esc_html($ro['label']).'</option>';} if($cg) echo '</optgroup>'; ?>
											<option value="__custom__">-- Create Custom Coupon --</option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<script>
						function o100RefToggle(role, val) {
							document.getElementById('ref-' + role + '-points-panel').style.display = val === 'point' ? '' : 'none';
							document.getElementById('ref-' + role + '-coupon-panel').style.display = val === 'coupon' ? '' : 'none';
						}
						function o100RefCouponToggle(role, val) {
							if (val === '__custom__') { o100RefCouponModal.open(role); return; }
						}
						var o100RefCouponModal = {
							currentRole: '',
							open: function(role) {
								this.currentRole = role;
								document.getElementById('o100-ref-coupon-modal').style.display = 'flex';
								document.getElementById('rcm_title').value = role === 'advocate' ? 'Referral Advocate Reward' : 'Referral Friend Welcome Bonus';
								document.getElementById('rcm_code').value = '';
								document.getElementById('rcm_desc').value = '';
								document.getElementById('rcm_discount_type').value = 'fixed';
								document.getElementById('rcm_discount_value').value = '5';
								document.getElementById('rcm_min_spend').value = '0';
								document.getElementById('rcm_individual_use').checked = true;
								document.getElementById('rcm_usage_limit').value = '1';
								document.getElementById('rcm_expiry_days').value = '30';
								document.getElementById('rcm_priority').value = '10';
							},
							close: function() {
								document.getElementById('o100-ref-coupon-modal').style.display = 'none';
								var sel = document.getElementById('wizard_' + this.currentRole + '_coupon');
								if (sel && sel.value === '__custom__') sel.value = '';
							},
							save: function() {
								var btn = document.getElementById('rcm_save_btn');
								btn.disabled = true; btn.innerText = 'Creating...';
								var fd = new FormData();
								fd.append('action', 'o100_create_referral_coupon');
								fd.append('nonce', '<?php echo wp_create_nonce("o100_loyalty_proxy"); ?>');
								['title','code','desc','discount_type','discount_value','min_spend','usage_limit','expiry_days','priority'].forEach(function(k){
									var el = document.getElementById('rcm_' + k);
									if (el) fd.append(k === 'code' ? 'promo_code' : (k === 'desc' ? 'description' : k), el.value);
								});
								fd.append('individual_use', document.getElementById('rcm_individual_use').checked ? '1' : '0');
								fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(function(res){
									btn.disabled = false; btn.innerText = 'Create Coupon';
									if (res.success) {
										['advocate','friend'].forEach(function(r){ var sel = document.getElementById('wizard_'+r+'_coupon'); if(sel){ var opt = document.createElement('option'); opt.value='promo_'+res.data.id; opt.textContent='[NEW] '+res.data.title; var co=sel.querySelector('option[value="__custom__"]'); sel.insertBefore(opt, co); }});
										var cs = document.getElementById('wizard_'+o100RefCouponModal.currentRole+'_coupon');
										if(cs) cs.value = 'promo_'+res.data.id;
										o100RefCouponModal.close();
									} else { alert(res.data ? res.data.message : 'Failed'); }
								}).catch(function(e){ btn.disabled=false; btn.innerText='Create Coupon'; alert('Error: '+e.message); });
							}
						};
						</script>
						<div id="o100-ref-coupon-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:999999; align-items:center; justify-content:center;">
							<div style="background:#fff; width:520px; max-height:85vh; border-radius:1rem; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); padding:28px;">
								<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
									<h3 style="font-size:1.25rem; font-weight:700; color:#0f172a; margin:0;">Create Referral Coupon</h3>
									<button onclick="o100RefCouponModal.close()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#94a3b8;">&times;</button>
								</div>
								<div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:16px;">
									<div style="font-weight:700; color:#4F46E5; font-size:0.8rem; text-transform:uppercase; margin-bottom:10px;">Basic Info</div>
									<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Coupon Name *</label>
									<input type="text" id="rcm_title" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:10px; font-size:0.875rem;">
									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
										<div><label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Promo Code</label><input type="text" id="rcm_code" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem; font-family:monospace;" placeholder="e.g. REFWELCOME"></div>
										<div><label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Priority</label><input type="number" id="rcm_priority" value="10" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;"></div>
									</div>
									<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px; margin-top:10px;">Description</label>
									<input type="text" id="rcm_desc" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;">
								</div>
								<div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:16px;">
									<div style="font-weight:700; color:#10B981; font-size:0.8rem; text-transform:uppercase; margin-bottom:10px;">Discount Settings</div>
									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
										<div><label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Discount Type</label><select id="rcm_discount_type" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;"><option value="fixed">Fixed Amount ($)</option><option value="percentage">Percentage (%)</option></select></div>
										<div><label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Discount Value *</label><input type="number" id="rcm_discount_value" value="5" step="0.01" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;"></div>
									</div>
									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
										<div><label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Minimum Spend ($)</label><input type="number" id="rcm_min_spend" value="0" step="0.01" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;"></div>
										<div style="display:flex; align-items:flex-end; padding-bottom:4px;"><label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.8rem; font-weight:600; color:#475569;"><input type="checkbox" id="rcm_individual_use" checked style="width:16px; height:16px;"> Individual Use Only</label></div>
									</div>
								</div>
								<div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:20px;">
									<div style="font-weight:700; color:#F59E0B; font-size:0.8rem; text-transform:uppercase; margin-bottom:10px;">Limits & Expiry</div>
									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
										<div><label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Usage Limit</label><input type="number" id="rcm_usage_limit" value="1" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;"></div>
										<div><label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Expiry (days)</label><input type="number" id="rcm_expiry_days" value="30" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;"></div>
									</div>
								</div>
								<div style="display:flex; justify-content:flex-end; gap:10px;">
									<button onclick="o100RefCouponModal.close()" style="padding:10px 20px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; color:#64748b; font-weight:600; cursor:pointer; font-size:0.875rem;">Cancel</button>
									<button id="rcm_save_btn" onclick="o100RefCouponModal.save()" style="padding:10px 24px; border:none; border-radius:10px; background:#4F46E5; color:#fff; font-weight:700; cursor:pointer; font-size:0.875rem; box-shadow:0 2px 8px rgba(79,70,229,0.3);">Create Coupon</button>
								</div>
							</div>
						</div>
'''
    panel_lines = [l + '\n' for l in new_panel.split('\n')]
    lines = lines[:ref_start] + panel_lines + lines[ref_end:]
    print(f"PATCH2: Replaced referral panel lines {ref_start+1}-{ref_end}")
else:
    print(f"PATCH2 FAILED: start={ref_start}, end={ref_end}")

# ============================================================
# PATCH 3: Fix wizard startWizard — referral type should show referral panel, not call setRewardType
# Find the referral handling in startWizard
# ============================================================
for i, line in enumerate(lines):
    if "} else if (type === 'referral')" in line or "else if (type === 'referral')" in line:
        # Check if next line calls setRewardType
        if i+1 < len(lines) and 'setRewardType' in lines[i+1]:
            old_line = lines[i+1]
            lines[i+1] = "\t\t\t\t\t\t// Referral: hide generic cards, show referral panel directly\n"
            lines.insert(i+2, "\t\t\t\t\t\tdocument.querySelectorAll('.o100-reward-opt').forEach(opt => opt.classList.add('hidden'));\n")
            lines.insert(i+3, "\t\t\t\t\t\tvar rtSel = document.getElementById('reward-type-selector'); if (rtSel) rtSel.style.display = 'none';\n")
            lines.insert(i+4, "\t\t\t\t\t\tvar refPanel = document.getElementById('reward-panel-referral'); if (refPanel) refPanel.classList.remove('hidden');\n")
            print(f"PATCH3: Fixed referral wizard routing at line {i+1}")
            break

# Also restore reward-type-selector for non-referral types
for i, line in enumerate(lines):
    if "// Update Reward types logic" in line:
        if i+1 < len(lines) and "querySelectorAll('.o100-reward-opt')" in lines[i+1]:
            lines.insert(i+2, "\t\t\t\t\tvar rtSel = document.getElementById('reward-type-selector'); if (rtSel) rtSel.style.display = '';\n")
            print(f"PATCH3b: Added rtSel restore at line {i+3}")
            break

# ============================================================
# PATCH 4: Update JS POST data — add coupon fields for advocate/friend
# ============================================================
for i, line in enumerate(lines):
    if "friend_amount: document.getElementById('wizard_friend_amount')" in line:
        indent = '\t\t\t\t\t\t\t'
        extra = [
            indent + "advocate_coupon: document.getElementById('wizard_advocate_coupon') ? document.getElementById('wizard_advocate_coupon').value : '',\n",
            indent + "friend_coupon: document.getElementById('wizard_friend_coupon') ? document.getElementById('wizard_friend_coupon').value : '',\n",
        ]
        for j, el in enumerate(extra):
            lines.insert(i+1+j, el)
        print(f"PATCH4: Added coupon POST fields after line {i+1}")
        break

# ============================================================
# PATCH 5: Simplify PHP save handler for referral coupons
# ============================================================
for i, line in enumerate(lines):
    if "$adv_type === 'coupon'" in line and 'earn_reward_config' in lines[min(i+3, len(lines)-1)]:
        # Find end of this if block
        end_i = i
        for j in range(i, min(len(lines), i+12)):
            if "earn_reward_config" in lines[j] and "adv" in lines[j].lower():
                for k in range(j, min(len(lines), j+5)):
                    if ']' in lines[k] and ';' in lines[k]:
                        end_i = k + 1
                        # Find closing brace
                        if end_i < len(lines) and '}' in lines[end_i].strip():
                            end_i += 1
                        break
                break
        
        if end_i > i:
            t2, t3 = '\t\t', '\t\t\t'
            new_block = [
                t2 + "if ( $adv_type === 'coupon' ) {\n",
                t3 + "$point_rule_arr['advocate']['earn_reward'] = sanitize_text_field( $_POST['advocate_coupon'] ?? '' );\n",
                t2 + "}\n",
            ]
            lines = lines[:i] + new_block + lines[end_i:]
            print(f"PATCH5a: Simplified advocate coupon handler at line {i+1}")
        break

for i, line in enumerate(lines):
    if "$fri_type === 'coupon'" in line and i > 100:
        end_i = i
        for j in range(i, min(len(lines), i+12)):
            if "earn_reward_config" in lines[j] and "fri" in lines[j].lower():
                for k in range(j, min(len(lines), j+5)):
                    if ']' in lines[k] and ';' in lines[k]:
                        end_i = k + 1
                        if end_i < len(lines) and '}' in lines[end_i].strip():
                            end_i += 1
                        break
                break
        if end_i > i:
            t2, t3 = '\t\t', '\t\t\t'
            new_block = [
                t2 + "if ( $fri_type === 'coupon' ) {\n",
                t3 + "$point_rule_arr['friend']['earn_reward'] = sanitize_text_field( $_POST['friend_coupon'] ?? '' );\n",
                t2 + "}\n",
            ]
            lines = lines[:i] + new_block + lines[end_i:]
            print(f"PATCH5b: Simplified friend coupon handler at line {i+1}")
        break

# ============================================================
# PATCH 6: Add handle_create_referral_coupon method before closing }
# ============================================================
# Find last closing brace of the class
last_brace = None
for i in range(len(lines)-1, -1, -1):
    if lines[i].strip() == '}':
        last_brace = i
        break

if last_brace:
    method = '''
\t/**
\t * AJAX: Create a referral coupon in the O100 Promotions table.
\t */
\tpublic static function handle_create_referral_coupon() {
\t\tcheck_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
\t\tif ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Permission denied.' ] ); }
\t\tif ( ! class_exists( 'O100_Promotions_DB' ) ) { require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php'; }

\t\t$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
\t\tif ( empty( $title ) ) { wp_send_json_error( [ 'message' => 'Coupon name is required.' ] ); }

\t\t$discount_type  = sanitize_text_field( $_POST['discount_type'] ?? 'fixed' );
\t\t$discount_value = floatval( $_POST['discount_value'] ?? 5 );
\t\t$min_spend      = floatval( $_POST['min_spend'] ?? 0 );
\t\t$individual_use = ( $_POST['individual_use'] ?? '0' ) === '1';
\t\t$usage_limit    = intval( $_POST['usage_limit'] ?? 1 );
\t\t$expiry_days    = intval( $_POST['expiry_days'] ?? 30 );
\t\t$promo_code     = sanitize_text_field( wp_unslash( $_POST['promo_code'] ?? '' ) );

\t\t$action_config = wp_json_encode([ 'discount_type' => $discount_type, 'discount_value' => $discount_value, 'min_spend' => $min_spend, 'individual_use' => $individual_use ]);
\t\t$end_date = $expiry_days > 0 ? gmdate( 'Y-m-d H:i:s', strtotime( '+' . $expiry_days . ' days' ) ) : null;
\t\t$conditions = $min_spend > 0 ? wp_json_encode([['type'=>'cart_subtotal','operator'=>'gte','value'=>$min_spend]]) : '[]';

\t\t$new_id = O100_Promotions_DB::insert([
\t\t\t'source' => 'loyalty', 'title' => $title, 'description' => sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) ),
\t\t\t'rule_type' => 'simple', 'action_config' => $action_config, 'apply_to' => 'all_products', 'apply_to_items' => '[]',
\t\t\t'promo_code' => ! empty( $promo_code ) ? $promo_code : null, 'conditions' => $conditions,
\t\t\t'usage_limit' => $usage_limit, 'status' => 'active', 'priority' => intval( $_POST['priority'] ?? 10 ),
\t\t\t'is_exclusive' => $individual_use ? 1 : 0, 'end_date' => $end_date,
\t\t]);

\t\tif ( $new_id ) {
\t\t\t$sum = $discount_type === 'percentage' ? $discount_value . '%' : '$' . $discount_value;
\t\t\twp_send_json_success([ 'id' => $new_id, 'title' => $title . ' (' . $sum . ')' ]);
\t\t}
\t\twp_send_json_error([ 'message' => 'Database insert failed.' ]);
\t}
'''
    method_lines = [l + '\n' for l in method.split('\n')]
    lines = lines[:last_brace] + method_lines + lines[last_brace:]
    print(f"PATCH6: Added handle_create_referral_coupon before line {last_brace+1}")

# ============================================================
# WRITE using tempfile + shutil.move
# ============================================================
dir_name = os.path.dirname(SRC)
fd, tmp_path = tempfile.mkstemp(dir=dir_name, suffix='.php')
with os.fdopen(fd, 'w') as tmp:
    tmp.writelines(lines)

shutil.move(tmp_path, SRC)
print(f"Final: {len(lines)} lines written to {SRC}")
