<?php
require_once '/Users/kevinqi/Local Sites/order100/app/public/wp-load.php';

$templates = get_option('o100_template_library', []);
foreach ($templates as $tpl) {
    if (strtolower($tpl['name']) === 'email template') {
        file_put_contents('/Users/kevinqi/development/antigravity/order100/core/email/builder/template_mjml.txt', $tpl['mjml']);
        echo "Template saved to template_mjml.txt\n";
        exit;
    }
}
echo "Template not found.\n";
