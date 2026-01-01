f = '/Users/kevinqi/development/antigravity/order100/core/loyalty/admin/class-o100-loyalty-proxy-admin.php'
with open(f, 'r') as fh:
    lines = fh.readlines()

# === PATCH 1: JS POST data (around line 3832) ===
# Find the lines with advocate_type, advocate_amount, friend_type, friend_amount
js_start = None
js_end = None
for i, line in enumerate(lines):
    if "advocate_type: document.getElementById('wizard_advocate_type')" in line:
        js_start = i
    if js_start is not None and "friend_amount: document.getElementById('wizard_friend_amount')" in line:
        js_end = i
        break

if js_start is not None and js_end is not None:
    indent = '\t\t\t\t\t\t\t'
    new_js_lines = [
        indent + "advocate_type: document.getElementById('wizard_advocate_type') ? document.getElementById('wizard_advocate_type').value : 'point',\n",
        indent + "advocate_amount: document.getElementById('wizard_advocate_amount') ? document.getElementById('wizard_advocate_amount').value : 100,\n",
        indent + "advocate_coupon: document.getElementById('wizard_advocate_coupon') ? document.getElementById('wizard_advocate_coupon').value : '',\n",
        indent + "advocate_coupon_type: document.getElementById('wizard_advocate_coupon_type') ? document.getElementById('wizard_advocate_coupon_type').value : 'fixed_cart',\n",
        indent + "advocate_coupon_value: document.getElementById('wizard_advocate_coupon_value') ? document.getElementById('wizard_advocate_coupon_value').value : '5',\n",
        indent + "advocate_coupon_expiry: document.getElementById('wizard_advocate_coupon_expiry') ? document.getElementById('wizard_advocate_coupon_expiry').value : '30',\n",
        indent + "advocate_coupon_limit: document.getElementById('wizard_advocate_coupon_limit') ? document.getElementById('wizard_advocate_coupon_limit').value : '1',\n",
        indent + "friend_type: document.getElementById('wizard_friend_type') ? document.getElementById('wizard_friend_type').value : 'point',\n",
        indent + "friend_amount: document.getElementById('wizard_friend_amount') ? document.getElementById('wizard_friend_amount').value : 50,\n",
        indent + "friend_coupon: document.getElementById('wizard_friend_coupon') ? document.getElementById('wizard_friend_coupon').value : '',\n",
        indent + "friend_coupon_type: document.getElementById('wizard_friend_coupon_type') ? document.getElementById('wizard_friend_coupon_type').value : 'fixed_cart',\n",
        indent + "friend_coupon_value: document.getElementById('wizard_friend_coupon_value') ? document.getElementById('wizard_friend_coupon_value').value : '5',\n",
        indent + "friend_coupon_expiry: document.getElementById('wizard_friend_coupon_expiry') ? document.getElementById('wizard_friend_coupon_expiry').value : '30',\n",
        indent + "friend_coupon_limit: document.getElementById('wizard_friend_coupon_limit') ? document.getElementById('wizard_friend_coupon_limit').value : '1',\n",
    ]
    lines = lines[:js_start] + new_js_lines + lines[js_end+1:]
    print(f"JS PATCH: replaced lines {js_start+1}-{js_end+1} with {len(new_js_lines)} lines")
else:
    print(f"JS PATCH FAILED: start={js_start}, end={js_end}")

# === PATCH 2: PHP save handler (find referral section) ===
php_start = None
php_end = None
for i, line in enumerate(lines):
    if "$adv_type   = isset( $_POST['advocate_type'] )" in line:
        php_start = i
    if php_start is not None and "'limit'  => 1" in line and "'friend'" in ''.join(lines[max(0,i-5):i]):
        # Find the closing bracket and brace
        for j in range(i, min(len(lines), i+5)):
            if '];' in lines[j] or "};" in lines[j] or "}" in lines[j].strip():
                if lines[j].strip().startswith('}'):
                    php_end = j
                    break
        if php_end:
            break

if php_start is not None and php_end is not None:
    t2 = '\t\t'
    t3 = '\t\t\t'
    new_php_lines = [
        t2 + "$adv_type   = isset( $_POST['advocate_type'] ) ? sanitize_text_field( $_POST['advocate_type'] ) : 'point';\n",
        t2 + "$adv_amount = isset( $_POST['advocate_amount'] ) ? intval( $_POST['advocate_amount'] ) : 100;\n",
        t2 + "$fri_type   = isset( $_POST['friend_type'] ) ? sanitize_text_field( $_POST['friend_type'] ) : 'point';\n",
        t2 + "$fri_amount = isset( $_POST['friend_amount'] ) ? intval( $_POST['friend_amount'] ) : 50;\n",
        "\n",
        t2 + "$point_rule_arr = [\n",
        t3 + "'advocate' => [\n",
        t3 + "\t'campaign_type' => $adv_type === 'coupon' ? 'coupon' : 'point',\n",
        t3 + "\t'earn_type'     => 'fixed_point',\n",
        t3 + "\t'earn_point'    => $adv_amount,\n",
        t3 + "\t'earn_reward'   => ''\n",
        t3 + "],\n",
        t3 + "'friend'   => [\n",
        t3 + "\t'campaign_type' => $fri_type === 'coupon' ? 'coupon' : 'point',\n",
        t3 + "\t'earn_type'     => 'fixed_point',\n",
        t3 + "\t'earn_point'    => $fri_amount,\n",
        t3 + "\t'earn_reward'   => ''\n",
        t3 + "]\n",
        t2 + "];\n",
        "\n",
        t2 + "// Process advocate coupon\n",
        t2 + "if ( $adv_type === 'coupon' ) {\n",
        t3 + "$adv_coupon_id = sanitize_text_field( $_POST['advocate_coupon'] ?? '' );\n",
        t3 + "if ( $adv_coupon_id === '__custom__' ) {\n",
        t3 + "\t$c_type   = sanitize_text_field( $_POST['advocate_coupon_type'] ?? 'fixed_cart' );\n",
        t3 + "\t$c_value  = floatval( $_POST['advocate_coupon_value'] ?? 5 );\n",
        t3 + "\t$c_expiry = intval( $_POST['advocate_coupon_expiry'] ?? 30 );\n",
        t3 + "\t$c_limit  = intval( $_POST['advocate_coupon_limit'] ?? 1 );\n",
        t3 + "\t$code = 'O100-REF-ADV-' . wp_generate_password( 6, false, false );\n",
        t3 + "\t$coupon = new WC_Coupon();\n",
        t3 + "\t$coupon->set_code( $code );\n",
        t3 + "\t$coupon->set_discount_type( $c_type );\n",
        t3 + "\t$coupon->set_amount( $c_value );\n",
        t3 + "\t$coupon->set_usage_limit( $c_limit );\n",
        t3 + "\t$coupon->set_individual_use( true );\n",
        t3 + "\tif ( $c_expiry > 0 ) { $exp = new WC_DateTime(); $exp->modify( '+' . $c_expiry . ' days' ); $coupon->set_date_expires( $exp ); }\n",
        t3 + "\t$coupon->save();\n",
        t3 + "\t$adv_coupon_id = $coupon->get_id();\n",
        t3 + "\t$point_rule_arr['advocate']['earn_reward_config'] = [ 'type' => $c_type, 'value' => $c_value, 'expiry' => $c_expiry, 'limit' => $c_limit, 'coupon_code' => $code ];\n",
        t3 + "}\n",
        t3 + "$point_rule_arr['advocate']['earn_reward'] = $adv_coupon_id;\n",
        t2 + "}\n",
        "\n",
        t2 + "// Process friend coupon\n",
        t2 + "if ( $fri_type === 'coupon' ) {\n",
        t3 + "$fri_coupon_id = sanitize_text_field( $_POST['friend_coupon'] ?? '' );\n",
        t3 + "if ( $fri_coupon_id === '__custom__' ) {\n",
        t3 + "\t$c_type   = sanitize_text_field( $_POST['friend_coupon_type'] ?? 'fixed_cart' );\n",
        t3 + "\t$c_value  = floatval( $_POST['friend_coupon_value'] ?? 5 );\n",
        t3 + "\t$c_expiry = intval( $_POST['friend_coupon_expiry'] ?? 30 );\n",
        t3 + "\t$c_limit  = intval( $_POST['friend_coupon_limit'] ?? 1 );\n",
        t3 + "\t$code = 'O100-REF-FRI-' . wp_generate_password( 6, false, false );\n",
        t3 + "\t$coupon = new WC_Coupon();\n",
        t3 + "\t$coupon->set_code( $code );\n",
        t3 + "\t$coupon->set_discount_type( $c_type );\n",
        t3 + "\t$coupon->set_amount( $c_value );\n",
        t3 + "\t$coupon->set_usage_limit( $c_limit );\n",
        t3 + "\t$coupon->set_individual_use( true );\n",
        t3 + "\tif ( $c_expiry > 0 ) { $exp = new WC_DateTime(); $exp->modify( '+' . $c_expiry . ' days' ); $coupon->set_date_expires( $exp ); }\n",
        t3 + "\t$coupon->save();\n",
        t3 + "\t$fri_coupon_id = $coupon->get_id();\n",
        t3 + "\t$point_rule_arr['friend']['earn_reward_config'] = [ 'type' => $c_type, 'value' => $c_value, 'expiry' => $c_expiry, 'limit' => $c_limit, 'coupon_code' => $code ];\n",
        t3 + "}\n",
        t3 + "$point_rule_arr['friend']['earn_reward'] = $fri_coupon_id;\n",
        t2 + "}\n",
    ]
    lines = lines[:php_start] + new_php_lines + lines[php_end+1:]
    print(f"PHP PATCH: replaced lines {php_start+1}-{php_end+1} with {len(new_php_lines)} lines")
else:
    print(f"PHP PATCH FAILED: start={php_start}, end={php_end}")

with open(f, 'w') as fh:
    fh.writelines(lines)
print("All patches applied.")
