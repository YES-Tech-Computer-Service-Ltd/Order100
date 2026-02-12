							<div id="reward-panel-referral" class="hidden bg-white border border-slate-200 rounded-xl p-6 shadow-sm mb-6">
							<h4 class="font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Referral Rewards</h4>
							<?php
							$ref_coupons = get_posts([ 'post_type' => 'shop_coupon', 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => 'title', 'order' => 'ASC' ]);
							?>
							<div class="grid grid-cols-2 gap-6 mb-4">
								<!-- ADVOCATE -->
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
											<?php foreach ($ref_coupons as $rc) { $wc = new WC_Coupon($rc->ID); echo '<option value="' . esc_attr($rc->ID) . '">' . esc_html($rc->post_title) . ' (' . $wc->get_discount_type() . ': ' . $wc->get_amount() . ')</option>'; } ?>
											<option value="__custom__">-- Create Custom Coupon --</option>
										</select>
										<div id="ref-advocate-custom-coupon" style="display:none; background:#f1f0ff; padding:12px; border-radius:8px; border:1px dashed #c7d2fe; margin-top:6px;">
											<div class="text-xs font-bold text-indigo-600 mb-2">New Coupon Settings</div>
											<label class="block text-xs text-slate-600 mb-1">Discount Type</label>
											<select id="wizard_advocate_coupon_type" class="w-full mb-2 text-sm"><option value="fixed_cart">Fixed Cart ($)</option><option value="percent">Percentage (%)</option></select>
											<label class="block text-xs text-slate-600 mb-1">Amount</label>
											<input type="number" id="wizard_advocate_coupon_value" class="w-full mb-2 text-sm" value="5" step="0.01">
											<div class="grid grid-cols-2 gap-2">
												<div><label class="block text-xs text-slate-600 mb-1">Expiry (days)</label><input type="number" id="wizard_advocate_coupon_expiry" class="w-full text-sm" value="30"></div>
												<div><label class="block text-xs text-slate-600 mb-1">Usage Limit</label><input type="number" id="wizard_advocate_coupon_limit" class="w-full text-sm" value="1"></div>
											</div>
										</div>
									</div>
								</div>
								<!-- FRIEND -->
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
											<?php foreach ($ref_coupons as $rc) { $wc = new WC_Coupon($rc->ID); echo '<option value="' . esc_attr($rc->ID) . '">' . esc_html($rc->post_title) . ' (' . $wc->get_discount_type() . ': ' . $wc->get_amount() . ')</option>'; } ?>
											<option value="__custom__">-- Create Custom Coupon --</option>
										</select>
										<div id="ref-friend-custom-coupon" style="display:none; background:#ecfdf5; padding:12px; border-radius:8px; border:1px dashed #86efac; margin-top:6px;">
											<div class="text-xs font-bold text-emerald-600 mb-2">New Coupon Settings</div>
											<label class="block text-xs text-slate-600 mb-1">Discount Type</label>
											<select id="wizard_friend_coupon_type" class="w-full mb-2 text-sm"><option value="fixed_cart">Fixed Cart ($)</option><option value="percent">Percentage (%)</option></select>
											<label class="block text-xs text-slate-600 mb-1">Amount</label>
											<input type="number" id="wizard_friend_coupon_value" class="w-full mb-2 text-sm" value="5" step="0.01">
											<div class="grid grid-cols-2 gap-2">
												<div><label class="block text-xs text-slate-600 mb-1">Expiry (days)</label><input type="number" id="wizard_friend_coupon_expiry" class="w-full text-sm" value="30"></div>
												<div><label class="block text-xs text-slate-600 mb-1">Usage Limit</label><input type="number" id="wizard_friend_coupon_limit" class="w-full text-sm" value="1"></div>
											</div>
										</div>
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
							document.getElementById('ref-' + role + '-custom-coupon').style.display = val === '__custom__' ? '' : 'none';
						}
						</script>

// TS: 20260211205228
