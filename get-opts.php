<?php
define('WP_USE_THEMES', false);
require_once '/Users/kevinqi/Local Sites/order100/app/public/wp-load.php';

$options = get_option('o100_settings', array());
echo "o100_filter_closed_timeslots: " . (isset($options['o100_filter_closed_timeslots']) ? $options['o100_filter_closed_timeslots'] : 'NOT SET') . "\n";
echo "o100_delivery_override_schedule: " . (isset($options['o100_delivery_override_schedule']) ? $options['o100_delivery_override_schedule'] : 'NOT SET') . "\n";



