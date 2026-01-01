import subprocess

f = '/Users/kevinqi/development/antigravity/order100/core/loyalty/admin/class-o100-loyalty-proxy-admin.php'
with open(f, 'r') as fh:
    lines = fh.readlines()

start = None
end = None
for i, line in enumerate(lines):
    if '// Process advocate coupon' in line:
        start = i
    if start and "// Process friend coupon" in line:
        for j in range(i, min(len(lines), i+30)):
            if "$point_rule_arr['friend']['earn_reward']" in lines[j]:
                end = j + 2
                break
        break

if start is not None and end is not None:
    t2 = '\t\t'
    t3 = '\t\t\t'
    new_block = [
        t2 + "// Process advocate coupon — value is 'promo_X', 'wc_X', or empty\n",
        t2 + "if ( $adv_type === 'coupon' ) {\n",
        t3 + "$adv_coupon_ref = sanitize_text_field( $_POST['advocate_coupon'] ?? '' );\n",
        t3 + "$point_rule_arr['advocate']['earn_reward'] = $adv_coupon_ref;\n",
        t2 + "}\n",
        "\n",
        t2 + "// Process friend coupon — value is 'promo_X', 'wc_X', or empty\n",
        t2 + "if ( $fri_type === 'coupon' ) {\n",
        t3 + "$fri_coupon_ref = sanitize_text_field( $_POST['friend_coupon'] ?? '' );\n",
        t3 + "$point_rule_arr['friend']['earn_reward'] = $fri_coupon_ref;\n",
        t2 + "}\n",
    ]
    lines = lines[:start] + new_block + lines[end:]
    print(f"Replaced lines {start+1}-{end}")
    
    content = ''.join(lines)
    # Write using tee
    proc = subprocess.run(['tee', f], input=content.encode(), capture_output=True)
    if proc.returncode == 0:
        print("Written successfully via tee")
    else:
        print(f"tee failed: {proc.stderr.decode()}")
else:
    print(f"FAILED: start={start}, end={end}")
