import sys

with open('/Users/kevinqi/development/antigravity/Order100/core/class-o100-menu-builder-tab.php', 'r') as f:
    content = f.read()

with open('/Users/kevinqi/development/antigravity/Order100/core/scratch_menu_builder.php', 'r') as f:
    replacement = f.read()

start_marker = '<div style="display:flex; border-bottom:1px solid #e2e8f0; background:#f8fafc; padding:0 20px;">'
end_marker = '</script>'

start_idx = content.find(start_marker)
end_idx = content.find(end_marker, start_idx) + len(end_marker)

if start_idx != -1 and end_idx != -1:
    new_content = content[:start_idx] + replacement + content[end_idx:]
    with open('/Users/kevinqi/development/antigravity/Order100/core/class-o100-menu-builder-tab.php', 'w') as f:
        f.write(new_content)
    print("Success")
else:
    print("Markers not found")

