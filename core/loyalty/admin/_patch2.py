#!/usr/bin/env python3
"""Patch referral panel: merge WC coupons + O100 promos into dropdown."""

f = '/Users/kevinqi/development/antigravity/order100/core/loyalty/admin/class-o100-loyalty-proxy-admin.php'
with open(f, 'r') as fh:
    lines = fh.readlines()

# Find the old PHP coupon query block
old_query = "$ref_coupons = get_posts([ 'post_type' => 'shop_coupon'"
new_php_block = """<?php
							// Merge WooCommerce coupons + O100 Promotions as reward options
							$ref_reward_options = [];
							// 1. WooCommerce native coupons
							$wc_coupons = get_posts(['post_type'=>'shop_coupon','post_status'=>'publish','posts_per_page'=>50,'orderby'=>'title','order'=>'ASC']);
							foreach ($wc_coupons as $wcc) {
								$c = new WC_Coupon($wcc->ID);
								$ref_reward_options[] = ['value'=>'wc_'.$wcc->ID, 'label'=>$wcc->post_title.' ('.$c->get_discount_type().': '.$c->get_amount().')', 'group'=>'WooCommerce Coupons'];
							}
							// 2. O100 Promotions (simple type with promo_code)
							if (class_exists('O100_Promotions_DB')) {
								$promos = O100_Promotions_DB::query(['status'=>'active']);
								foreach ($promos as $p) {
									$cfg = json_decode($p['action_config']??'{}', true);
									$dt = $cfg['discount_type']??'';
									$dv = $cfg['discount_value']??'';
									$sum = $dt==='percentage' ? $dv.'%' : '$'.$dv;
									$ref_reward_options[] = ['value'=>'promo_'.$p['id'], 'label'=>$p['title'].' ('.$sum.')', 'group'=>'O100 Promotions'];
								}
							}
							?>
"""

# Find and replace the old query lines
patched = 0
i = 0
new_lines = []
while i < len(lines):
    if old_query in lines[i]:
        # Skip old query line and the closing ?>
        new_lines.append(new_php_block)
        i += 1  # skip old query line
        # Skip the closing ?> line
        if i < len(lines) and '?>' in lines[i]:
            i += 1
        patched += 1
        continue
    new_lines.append(lines[i])
    i += 1

print(f"Patched query blocks: {patched}")

# Now replace the foreach loops that use $ref_coupons
# Old pattern: foreach ($ref_coupons as $rc) { $wc = new WC_Coupon(...
old_foreach = "foreach ($ref_coupons as $rc) { $wc = new WC_Coupon($rc->ID);"
new_foreach_line = """<?php
												$cur_group = '';
												foreach ($ref_reward_options as $ropt) {
													if ($ropt['group'] !== $cur_group) {
														if ($cur_group) echo '</optgroup>';
														echo '<optgroup label="'.esc_attr($ropt['group']).'">';
														$cur_group = $ropt['group'];
													}
													echo '<option value="'.esc_attr($ropt['value']).'">'.esc_html($ropt['label']).'</option>';
												}
												if ($cur_group) echo '</optgroup>';
											?>
"""

final_lines = []
i = 0
foreach_patched = 0
while i < len(new_lines):
    line = new_lines[i]
    if old_foreach in line:
        # Replace this line and the next line (which closes the foreach with } ?>)
        final_lines.append(new_foreach_line)
        i += 1
        foreach_patched += 1
        continue
    final_lines.append(line)
    i += 1

print(f"Patched foreach blocks: {foreach_patched}")

with open(f, 'w') as fh:
    fh.writelines(final_lines)

print("Done.")
