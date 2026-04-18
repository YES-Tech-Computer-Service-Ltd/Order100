<?php
require_once('../../../wp-load.php');
if (class_exists('O100_Promotions_DB')) {
	O100_Promotions_DB::create_table();
	echo "Table created.\n";
} else {
	echo "Class not found.\n";
}

// TS: 20260418020931
