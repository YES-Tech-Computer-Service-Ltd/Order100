f = '/Users/kevinqi/development/antigravity/order100/core/loyalty/admin/class-o100-loyalty-proxy-admin.php'
with open(f, 'r') as fh:
    lines = fh.readlines()

# Remove the duplicate <?php at line 2101 (0-indexed 2100)
# Line 2101 contains just "<?php" which was the old opening tag before the old query
# Line 2102 contains "<?php" from our new injection
# We need to remove line 2101

removed = 0
new_lines = []
for i, line in enumerate(lines):
    lineno = i + 1
    # Remove line 2101 which is the old orphaned <?php
    if lineno == 2101 and '<?php' in line.strip() and len(line.strip()) < 10:
        print(f"Removing duplicate <?php at line {lineno}: {repr(line.rstrip())}")
        removed += 1
        continue
    new_lines.append(line)

# Also check for duplicate <?php in the foreach blocks
# The new_foreach_line starts with <?php but it's inside HTML context, which is correct
# Let's verify the foreach blocks don't have similar issues
print(f"Removed {removed} duplicate lines")

with open(f, 'w') as fh:
    fh.writelines(new_lines)

print("Fixed.")
