<?php
require_once('/Users/kevinqi/Local Sites/order100/app/public/wp-load.php');
require_once('/Users/kevinqi/development/antigravity/order100/core/customers/class-o100-customers-db.php');
O100_Customers_DB::create_tables();
echo "Tables updated!";
