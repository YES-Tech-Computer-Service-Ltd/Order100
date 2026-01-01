f = '/Users/kevinqi/development/antigravity/order100/core/loyalty/admin/class-o100-loyalty-proxy-admin.php'
with open(f, 'r') as fh:
    lines = fh.readlines()

# 1. Replace the inline script block (L2211-2219) with enhanced version + modal
old_script_start = None
old_script_end = None
for i, line in enumerate(lines):
    if 'function o100RefToggle(role, val)' in line and '<script>' in lines[i-1]:
        old_script_start = i - 1  # the <script> tag
    if old_script_start and '</script>' in line and i > old_script_start:
        old_script_end = i
        break

if old_script_start is not None and old_script_end is not None:
    new_script = """						<script>
						function o100RefToggle(role, val) {
							document.getElementById('ref-' + role + '-points-panel').style.display = val === 'point' ? '' : 'none';
							document.getElementById('ref-' + role + '-coupon-panel').style.display = val === 'coupon' ? '' : 'none';
						}
						function o100RefCouponToggle(role, val) {
							if (val === '__custom__') {
								// Open the modal instead of inline block
								o100RefCouponModal.open(role);
								return;
							}
							// Hide inline custom fields for non-custom selections
							document.getElementById('ref-' + role + '-custom-coupon').style.display = 'none';
						}

						// Referral Coupon Creation Modal
						var o100RefCouponModal = {
							currentRole: '',
							open: function(role) {
								this.currentRole = role;
								document.getElementById('o100-ref-coupon-modal').style.display = 'flex';
								// Reset form
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
								// Reset the dropdown back to empty if user cancels
								var sel = document.getElementById('wizard_' + this.currentRole + '_coupon');
								if (sel && sel.value === '__custom__') {
									sel.value = '';
								}
							},
							save: function() {
								var btn = document.getElementById('rcm_save_btn');
								btn.disabled = true;
								btn.innerText = 'Creating...';

								var formData = new FormData();
								formData.append('action', 'o100_create_referral_coupon');
								formData.append('nonce', typeof o100_loyalty !== 'undefined' ? o100_loyalty.proxy_nonce : '');
								formData.append('title', document.getElementById('rcm_title').value);
								formData.append('promo_code', document.getElementById('rcm_code').value);
								formData.append('description', document.getElementById('rcm_desc').value);
								formData.append('discount_type', document.getElementById('rcm_discount_type').value);
								formData.append('discount_value', document.getElementById('rcm_discount_value').value);
								formData.append('min_spend', document.getElementById('rcm_min_spend').value);
								formData.append('individual_use', document.getElementById('rcm_individual_use').checked ? '1' : '0');
								formData.append('usage_limit', document.getElementById('rcm_usage_limit').value);
								formData.append('expiry_days', document.getElementById('rcm_expiry_days').value);
								formData.append('priority', document.getElementById('rcm_priority').value);

								fetch(ajaxurl, { method: 'POST', body: formData })
								.then(r => r.json())
								.then(res => {
									btn.disabled = false;
									btn.innerText = 'Create Coupon';
									if (res.success) {
										// Add new option to BOTH advocate and friend dropdowns
										['advocate', 'friend'].forEach(function(r) {
											var sel = document.getElementById('wizard_' + r + '_coupon');
											if (sel) {
												var opt = document.createElement('option');
												opt.value = 'promo_' + res.data.id;
												opt.textContent = '[NEW] ' + res.data.title;
												// Insert before __custom__ option
												var customOpt = sel.querySelector('option[value="__custom__"]');
												sel.insertBefore(opt, customOpt);
											}
										});
										// Select the new option for current role
										var curSel = document.getElementById('wizard_' + o100RefCouponModal.currentRole + '_coupon');
										if (curSel) curSel.value = 'promo_' + res.data.id;
										o100RefCouponModal.close();
									} else {
										alert(res.data ? res.data.message : 'Failed to create coupon');
									}
								})
								.catch(err => {
									btn.disabled = false;
									btn.innerText = 'Create Coupon';
									alert('Error: ' + err.message);
								});
							}
						};
						</script>

						<!-- Referral Coupon Creation Modal -->
						<div id="o100-ref-coupon-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:999999; align-items:center; justify-content:center;">
							<div style="background:#fff; width:520px; max-height:85vh; border-radius:1rem; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); padding:28px;">
								<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
									<h3 style="font-size:1.25rem; font-weight:700; color:#0f172a; margin:0;">Create Referral Coupon</h3>
									<button onclick="o100RefCouponModal.close()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#94a3b8;">&times;</button>
								</div>

								<!-- Basic Info -->
								<div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:16px;">
									<div style="font-weight:700; color:#4F46E5; font-size:0.8rem; text-transform:uppercase; margin-bottom:10px;">Basic Info</div>
									<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Coupon Name *</label>
									<input type="text" id="rcm_title" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:10px; font-size:0.875rem;" placeholder="e.g. Referral $5 Off">
									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
										<div>
											<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Promo Code <span style="color:#94a3b8; font-weight:400;">(optional)</span></label>
											<input type="text" id="rcm_code" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem; font-family:monospace;" placeholder="e.g. REFWELCOME">
										</div>
										<div>
											<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Priority</label>
											<input type="number" id="rcm_priority" value="10" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;">
										</div>
									</div>
									<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px; margin-top:10px;">Description</label>
									<input type="text" id="rcm_desc" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;" placeholder="e.g. Welcome bonus for referred customers">
								</div>

								<!-- Discount Settings -->
								<div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:16px;">
									<div style="font-weight:700; color:#10B981; font-size:0.8rem; text-transform:uppercase; margin-bottom:10px;">Discount Settings</div>
									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
										<div>
											<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Discount Type</label>
											<select id="rcm_discount_type" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;">
												<option value="fixed">Fixed Amount ($)</option>
												<option value="percentage">Percentage (%)</option>
											</select>
										</div>
										<div>
											<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Discount Value *</label>
											<input type="number" id="rcm_discount_value" value="5" step="0.01" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;">
										</div>
									</div>
									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
										<div>
											<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Minimum Spend ($)</label>
											<input type="number" id="rcm_min_spend" value="0" step="0.01" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;">
										</div>
										<div style="display:flex; align-items:flex-end; padding-bottom:4px;">
											<label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.8rem; font-weight:600; color:#475569;">
												<input type="checkbox" id="rcm_individual_use" checked style="width:16px; height:16px;"> Individual Use Only
											</label>
										</div>
									</div>
								</div>

								<!-- Limits & Expiry -->
								<div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:20px;">
									<div style="font-weight:700; color:#F59E0B; font-size:0.8rem; text-transform:uppercase; margin-bottom:10px;">Limits & Expiry</div>
									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
										<div>
											<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Usage Limit (total)</label>
											<input type="number" id="rcm_usage_limit" value="1" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;">
										</div>
										<div>
											<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Expiry (days from now)</label>
											<input type="number" id="rcm_expiry_days" value="30" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;">
										</div>
									</div>
								</div>

								<!-- Buttons -->
								<div style="display:flex; justify-content:flex-end; gap:10px;">
									<button onclick="o100RefCouponModal.close()" style="padding:10px 20px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; color:#64748b; font-weight:600; cursor:pointer; font-size:0.875rem;">Cancel</button>
									<button id="rcm_save_btn" onclick="o100RefCouponModal.save()" style="padding:10px 24px; border:none; border-radius:10px; background:#4F46E5; color:#fff; font-weight:700; cursor:pointer; font-size:0.875rem; box-shadow:0 2px 8px rgba(79,70,229,0.3);">Create Coupon</button>
								</div>
							</div>
						</div>
"""

    new_script_lines = [l + '\n' for l in new_script.split('\n')]
    lines = lines[:old_script_start] + new_script_lines + lines[old_script_end+1:]
    print(f"Replaced script block at lines {old_script_start+1}-{old_script_end+1}")
else:
    print(f"FAILED: script start={old_script_start}, end={old_script_end}")

# 2. Remove the old inline custom-coupon divs (ref-*-custom-coupon)
# These are no longer needed since we use the modal
final_lines = []
skip_custom_div = False
skip_depth = 0
for line in lines:
    if 'ref-advocate-custom-coupon' in line or 'ref-friend-custom-coupon' in line:
        skip_custom_div = True
        skip_depth = 0
        continue
    if skip_custom_div:
        if '<div' in line:
            skip_depth += 1
        if '</div>' in line:
            if skip_depth == 0:
                skip_custom_div = False
                continue
            skip_depth -= 1
            continue
        continue
    final_lines.append(line)

print(f"Lines before cleanup: {len(lines)}, after: {len(final_lines)}")

with open(f, 'w') as fh:
    fh.writelines(final_lines)

print("All done.")
