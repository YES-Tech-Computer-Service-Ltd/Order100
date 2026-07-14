<?php
/**
 * Config Check — Pre-launch configuration audit view
 *
 * @package Order100
 */
defined( 'ABSPATH' ) || exit;

// ── Collect all checks ──
$cc_ok       = 0;
$cc_warnings = array();
$cc_errors   = array();

// ── Helper: mask sensitive string ──
if ( ! function_exists( 'o100_cc_mask' ) ) {
function o100_cc_mask( $str ) {
	if ( is_array( $str ) ) $str = implode( '', $str );
	$str = (string) $str;
	if ( strlen( $str ) <= 8 ) return str_repeat( '*', max( 1, strlen( $str ) ) );
	return substr( $str, 0, 4 ) . str_repeat( '*', strlen( $str ) - 8 ) . substr( $str, -4 );
}
}

// ── Helper: resolve product IDs to names ──
if ( ! function_exists( 'o100_cc_product_names' ) ) {
function o100_cc_product_names( $ids ) {
	if ( is_string( $ids ) ) $ids = array_filter( array_map( 'trim', explode( ',', $ids ) ) );
	if ( empty( $ids ) || ! is_array( $ids ) ) return array();
	$names = array();
	foreach ( $ids as $id ) {
		$title = get_the_title( intval( $id ) );
		if ( $title ) $names[] = $title;
	}
	return $names;
}
}

// ── Helper: resolve payment gateway IDs to names ──
if ( ! function_exists( 'o100_cc_payment_names' ) ) {
function o100_cc_payment_names( $ids ) {
	if ( empty( $ids ) || ! is_array( $ids ) ) return 'None';
	$names = array();
	if ( function_exists( 'WC' ) && isset( WC()->payment_gateways ) ) {
		$wc_gateways = WC()->payment_gateways->payment_gateways();
		foreach ( $ids as $id ) {
			if ( isset( $wc_gateways[ $id ] ) ) {
				$names[] = $wc_gateways[ $id ]->title ?: $wc_gateways[ $id ]->method_title;
			} else {
				$names[] = ucfirst( str_replace( '_', ' ', $id ) );
			}
		}
	} else {
		$map = array( 'bacs' => 'Bank Transfer', 'cod' => 'Cash on Delivery', 'cheque' => 'Check Payments' );
		foreach ( $ids as $id ) {
			$names[] = $map[ $id ] ?? ucfirst( str_replace( '_', ' ', $id ) );
		}
	}
	return implode( ', ', $names );
}
}

// ── Helper: resolve category IDs to names ──
if ( ! function_exists( 'o100_cc_category_names' ) ) {
function o100_cc_category_names( $ids ) {
	if ( is_string( $ids ) ) $ids = array_filter( array_map( 'trim', explode( ',', $ids ) ) );
	if ( empty( $ids ) || ! is_array( $ids ) ) return array();
	$names = array();
	foreach ( $ids as $id ) {
		$term = get_term( intval( $id ), 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) $names[] = $term->name;
	}
	return $names;
}
}

// ── Helper: format schedule day ──
if ( ! function_exists( 'o100_cc_format_day_schedule' ) ) {
function o100_cc_format_day_schedule( $day_data ) {
	if ( empty( $day_data ) || ! is_array( $day_data ) ) return 'Closed';
	$ranges = array();
	foreach ( $day_data as $slot ) {
		if ( is_array( $slot ) ) {
			$open = $slot['open-time'] ?? ( $slot['open'] ?? '' );
			$close = $slot['close-time'] ?? ( $slot['close'] ?? '' );
			if ( $open && $close ) {
				$ranges[] = esc_html( $open . '-' . $close );
			}
		}
	}
	return empty( $ranges ) ? 'Closed' : implode( ', ', $ranges );
}
}

// ── Helper: find shortcode usage ──
if ( ! function_exists( 'o100_cc_find_shortcode' ) ) {
function o100_cc_find_shortcode( $shortcodes ) {
	global $wpdb;
	if ( ! is_array( $shortcodes ) ) $shortcodes = array( $shortcodes );
	$likes = array();
	foreach ( $shortcodes as $sc ) {
		$likes[] = $wpdb->prepare( "post_content LIKE %s", '%[' . $wpdb->esc_like( $sc ) . '%' );
	}
	$where = implode( ' OR ', $likes );
	$sql = "SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_status='publish' AND ($where) LIMIT 10";
	return $wpdb->get_results( $sql );
}
}

// ── Helper: badge ──
if ( ! function_exists( 'o100_cc_badge' ) ) {
function o100_cc_badge( $on, $on_text = 'Enabled', $off_text = 'Disabled' ) {
	if ( $on ) {
		return '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#ecfdf5;color:#059669;">✅ ' . esc_html( $on_text ) . '</span>';
	}
	return '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#f1f5f9;color:#64748b;">— ' . esc_html( $off_text ) . '</span>';
}
}

// ══════════════════════════════════════════
// DATA LOADING
// ══════════════════════════════════════════
$opt_delivery     = get_option( 'o100_delivery', array() );
$opt_pickup       = get_option( 'o100_pickup', array() );
$opt_hours        = get_option( 'o100_store_hours', array() );
$opt_locations    = get_option( 'o100_locations', array() );
$opt_menu_rules   = get_option( 'o100_menu_rules', array() );
$opt_notifications = get_option( 'o100_notifications', array() );
$opt_reservation  = get_option( 'o100_reservation', array() );
$opt_options      = get_option( 'o100_options', array() );
$opt_api          = get_option( 'o100_api_integration', array() );
$opt_ui           = get_option( 'o100_ui_prefs', array() );

$deli_on  = ! empty( $opt_delivery['o100_enable_delivery'] ) && $opt_delivery['o100_enable_delivery'] === 'on';
$pick_on  = ! empty( $opt_pickup['o100_enable_pickup'] ) && $opt_pickup['o100_enable_pickup'] === 'on';
$loc_on   = get_option( 'o100_locations_status' ) === 'on';
$resv_on  = ! empty( $opt_reservation['o100_enable_reservation'] ) && $opt_reservation['o100_enable_reservation'] === 'on';
$loyalty_on = ! isset( $opt_options['o100_enable_loyalty'] ) || $opt_options['o100_enable_loyalty'] === 'on';
$sms_on   = ! empty( $opt_notifications['o100_sms_enable'] ) && $opt_notifications['o100_sms_enable'] === 'on';
$voice_on = ! empty( $opt_notifications['o100_voice_enable'] ) && $opt_notifications['o100_voice_enable'] === 'on';
$maps_key = ! empty( $opt_api['o100_ggmap_api_js'] ) ? $opt_api['o100_ggmap_api_js'] : '';

global $wpdb;
$days_of_week = array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' );

// ══════════════════════════════════════════
// CROSS-MODULE CHECKS
// ══════════════════════════════════════════
$deli_mode = ! empty( $opt_delivery['o100_limit_shp'] ) ? $opt_delivery['o100_limit_shp'] : 'radius';
if ( $deli_on && $deli_mode === 'radius' && empty( $maps_key ) ) {
	$cc_errors[] = 'Google Maps API Key is missing — Delivery uses radius-based restriction.';
}
if ( $deli_on && $deli_mode === 'postcode' && empty( $opt_delivery['o100_shp_postcodes'] ) ) {
	$cc_errors[] = 'Delivery uses postcode restriction but no postcodes are configured.';
}
if ( $deli_on && empty( $opt_delivery['o100_deli_dis'] ) && $deli_mode === 'radius' ) {
	$cc_warnings[] = 'Delivery max distance is not set.';
}

$holidays_on   = ! empty( $opt_hours['o100_enable_holidays'] ) && $opt_hours['o100_enable_holidays'] === 'on';
$holidays_list = ! empty( $opt_hours['o100_holidays_list'] ) ? $opt_hours['o100_holidays_list'] : array();
if ( $holidays_on && empty( $holidays_list ) ) {
	$cc_warnings[] = 'Holidays module is enabled but no holidays are configured.';
}

if ( $resv_on ) {
	$resv_rooms_on = ! empty( $opt_reservation['o100_resv_enable_rooms'] ) && $opt_reservation['o100_resv_enable_rooms'] === 'on';
	$resv_rooms    = ! empty( $opt_reservation['o100_resv_rooms'] ) ? $opt_reservation['o100_resv_rooms'] : array();
	if ( $resv_rooms_on && empty( $resv_rooms ) ) {
		$cc_errors[] = 'Private Rooms is enabled but no rooms are configured.';
	}
	if ( empty( $opt_reservation['o100_resv_admin_email'] ) ) {
		$cc_warnings[] = 'Reservation admin notification email is not configured.';
	}
}

if ( $sms_on ) {
	if ( empty( $opt_notifications['o100_sms_api_key'] ) ) $cc_errors[] = 'SMS is enabled but API Key is missing.';
	if ( empty( $opt_notifications['o100_sms_sender_number'] ) ) $cc_errors[] = 'SMS is enabled but Sender Number is missing.';
}

$mr_method_on = ! empty( $opt_menu_rules['o100_menu_method'] ) && $opt_menu_rules['o100_menu_method'] === 'on';
$mr_date_on   = ! empty( $opt_menu_rules['o100_menu_date'] ) && $opt_menu_rules['o100_menu_date'] === 'on';
$mr_date_rules = ! empty( $opt_menu_rules['o100_global_date_rules'] ) ? $opt_menu_rules['o100_global_date_rules'] : array();
if ( $mr_date_on && empty( $mr_date_rules ) ) {
	$cc_warnings[] = 'Daily Menu is enabled but no date rules are configured.';
}

$branch_count = 0;
if ( $loc_on ) {
	$branch_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='o100_location' AND post_status='publish'" );
	if ( $branch_count === 0 ) $cc_errors[] = 'Multi-Branch mode is enabled but no branches exist.';
}

// Promotions
$promo_table = $wpdb->prefix . 'o100_promotions';
$has_promo_table = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $promo_table ) ) === $promo_table;
$promo_active = 0; $promo_inactive = 0; $promo_rows = array();
if ( $has_promo_table ) {
	// Only count configured rules (exclude system-generated per-user coupons from loyalty_auto)
	$promo_active   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$promo_table}` WHERE source != 'loyalty_auto' AND (parent_id IS NULL OR parent_id = 0) AND status='active'" );
	$promo_inactive = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$promo_table}` WHERE source != 'loyalty_auto' AND (parent_id IS NULL OR parent_id = 0) AND status='inactive'" );
	$promo_rows     = $wpdb->get_results( "SELECT id, title, source, rule_type, promo_code, apply_to, apply_to_items, start_date, end_date, status FROM `{$promo_table}` WHERE source != 'loyalty_auto' AND (parent_id IS NULL OR parent_id = 0) AND status='active' ORDER BY priority ASC LIMIT 20" );
}

// Loyalty
$loyalty_camp_table = $wpdb->prefix . 'o100_loyalty_campaigns';
$loyalty_lvl_table  = $wpdb->prefix . 'o100_loyalty_levels';
$has_loyalty_camp = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $loyalty_camp_table ) ) === $loyalty_camp_table;
$has_loyalty_lvl  = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $loyalty_lvl_table ) ) === $loyalty_lvl_table;
$loyalty_campaigns = array(); $loyalty_levels = array();
if ( $loyalty_on && $has_loyalty_camp ) {
	$loyalty_campaigns = $wpdb->get_results( "SELECT id, title, type, status FROM `{$loyalty_camp_table}` ORDER BY ordering ASC, id ASC LIMIT 20" );
	if ( empty( $loyalty_campaigns ) ) $cc_warnings[] = 'Loyalty is enabled but no campaigns are configured.';
}
if ( $loyalty_on && $has_loyalty_lvl ) {
	$loyalty_levels = $wpdb->get_results( "SELECT name, min_points, max_points FROM `{$loyalty_lvl_table}` ORDER BY sort_order ASC" );
	if ( empty( $loyalty_levels ) ) $cc_warnings[] = 'Loyalty is enabled but no membership levels are configured.';
}

// Automation
$auto_table = $wpdb->prefix . 'o100_automations';
$has_auto_table = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $auto_table ) ) === $auto_table;
$auto_active = 0; $auto_inactive = 0; $auto_rows = array();
if ( $has_auto_table ) {
	$auto_active   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$auto_table}` WHERE status='active'" );
	$auto_inactive = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$auto_table}` WHERE status='inactive'" );
	$auto_rows     = $wpdb->get_results( "SELECT id, title, trigger_type, status FROM `{$auto_table}` WHERE status='active' ORDER BY id ASC LIMIT 20" );
}

// SMS template check (collect missing for later)
$sms_templates = array(
	'o100_sms_tpl_order_confirmed' => 'Order Confirmed',
	'o100_sms_tpl_ready_pickup'    => 'Ready for Pickup',
	'o100_sms_tpl_out_delivery'    => 'Out for Delivery',
	'o100_sms_tpl_new_order'       => 'New Order (Admin)',
	'o100_sms_tpl_reservation'     => 'Reservation',
	'o100_sms_tpl_reserve_remind'  => 'Reservation Reminder',
	'o100_sms_tpl_loyalty_update'  => 'Loyalty Update',
	'o100_sms_tpl_birthday'        => 'Birthday',
	'o100_sms_tpl_coupon_issued'   => 'Coupon Issued',
	'o100_sms_tpl_coupon_expire'   => 'Coupon Expiring',
);
$sms_tpl_missing = array();
if ( $sms_on ) {
	foreach ( $sms_templates as $tpl_key => $tpl_label ) {
		if ( empty( $opt_notifications[ $tpl_key ] ) ) $sms_tpl_missing[] = $tpl_label;
	}
	if ( ! empty( $sms_tpl_missing ) ) {
		$cc_warnings[] = 'SMS templates not configured: ' . implode( ', ', $sms_tpl_missing ) . '.';
	}
}

$cc_ok = max( 0, 27 - count( $cc_warnings ) - count( $cc_errors ) );

// ══════════════════════════════════════════
// STYLES
// ══════════════════════════════════════════
?>
<style>
.o100-cc-summary { display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap; }
.o100-cc-summary-box { padding:14px 24px; border-radius:10px; font-size:15px; font-weight:700; display:flex; align-items:center; gap:8px; }
.o100-cc-summary-ok { background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; }
.o100-cc-summary-warn { background:#fffbeb; color:#d97706; border:1px solid #fde68a; }
.o100-cc-summary-err { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
.o100-cc-issues { margin-bottom:24px; padding:16px 20px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; }
.o100-cc-issues p { margin:4px 0; font-size:13px; }
.o100-cc-issues .cc-err { color:#dc2626; }
.o100-cc-issues .cc-warn { color:#d97706; }
.o100-cc-card { margin-bottom:20px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; overflow:hidden; }
.o100-cc-card-title { padding:16px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:10px; }
.o100-cc-card-title h3 { margin:0; font-size:15px; font-weight:700; color:#0f172a; }
.o100-cc-card-body { padding:20px; }
.o100-cc-card-body table { width:100%; border-collapse:collapse; font-size:13px; }
.o100-cc-card-body table th { text-align:left; font-weight:600; color:#475569; padding:8px 12px; background:#f8fafc; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.o100-cc-card-body table td { padding:8px 12px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:top; }
.o100-cc-card-body table tr:last-child td { border-bottom:none; }
.o100-cc-param { display:grid; grid-template-columns:180px 1fr; gap:4px 16px; font-size:13px; }
.o100-cc-param dt { font-weight:600; color:#475569; padding:4px 0; }
.o100-cc-param dd { margin:0; padding:4px 0; color:#0f172a; }
.o100-cc-cols { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.o100-cc-tag { display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; background:#f1f5f9; color:#475569; margin:2px; }
.o100-cc-tag-cat { background:#eff6ff; color:#F59322; }
.o100-cc-tag-prod { background:#fef3c7; color:#92400e; }
.o100-cc-section-label { font-size:13px; font-weight:700; color:#334155; margin:16px 0 8px; padding-bottom:6px; border-bottom:2px solid #e2e8f0; }
.o100-cc-section-label:first-child { margin-top:0; }
@media print {
	.o100-fluent-sidebar, .o100-fluent-header, #adminmenumain, #wpadminbar, #wpfooter, .update-nag, .notice { display:none !important; }
	#wpcontent { margin-left:0 !important; }
	.o100-cc-card { break-inside:avoid; }
}
</style>

<?php // ══ RENDER: Summary Bar ══ ?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
	<div style="font-size:12px; color:#94a3b8;">Generated: <?php echo esc_html( wp_date( 'Y-m-d H:i T' ) ); ?></div>
</div>
<div class="o100-cc-summary">
	<div class="o100-cc-summary-box o100-cc-summary-ok">✅ <?php echo intval( $cc_ok ); ?> OK</div>
	<div class="o100-cc-summary-box o100-cc-summary-warn">⚠️ <?php echo count( $cc_warnings ); ?> Warnings</div>
	<div class="o100-cc-summary-box o100-cc-summary-err">❌ <?php echo count( $cc_errors ); ?> Errors</div>
</div>
<?php if ( ! empty( $cc_errors ) || ! empty( $cc_warnings ) ) : ?>
<div class="o100-cc-issues">
	<?php foreach ( $cc_errors as $e ) : ?><p class="cc-err">❌ <?php echo esc_html( $e ); ?></p><?php endforeach; ?>
	<?php foreach ( $cc_warnings as $w ) : ?><p class="cc-warn">⚠️ <?php echo esc_html( $w ); ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<?php // ══ CARD 2: Schedule ══
$deli_override = ! empty( $opt_hours['o100_delivery_override_schedule'] ) && $opt_hours['o100_delivery_override_schedule'] === 'on';
$pick_override = ! empty( $opt_hours['o100_pickup_override_schedule'] ) && $opt_hours['o100_pickup_override_schedule'] === 'on';
$resv_override = ! empty( $opt_hours['o100_reservation_override_schedule'] ) && $opt_hours['o100_reservation_override_schedule'] === 'on';
?>
<div class="o100-cc-card">
	<div class="o100-cc-card-title"><h3>Schedule</h3></div>
	<div class="o100-cc-card-body">
		<dl class="o100-cc-param" style="margin-bottom:16px;">
			<dt>Global Interval</dt><dd><?php echo esc_html( $opt_hours['o100_global_interval'] ?? '30' ); ?> min</dd>
			<dt>Max Orders/Slot</dt><dd><?php echo esc_html( $opt_hours['o100_global_max_order'] ?? '—' ); ?></dd>
			<dt>Delivery Override</dt><dd><?php echo o100_cc_badge( $deli_override, 'Yes (Interval: ' . ( $opt_hours['o100_delivery_interval'] ?? '—' ) . 'min, Max: ' . ( $opt_hours['o100_delivery_max_order'] ?? '—' ) . ')', 'No — uses Global' ); ?></dd>
			<dt>Pickup Override</dt><dd><?php echo o100_cc_badge( $pick_override, 'Yes (Interval: ' . ( $opt_hours['o100_pickup_interval'] ?? '—' ) . 'min, Max: ' . ( $opt_hours['o100_pickup_max_order'] ?? '—' ) . ')', 'No — uses Global' ); ?></dd>
			<?php if ( $resv_on ) : ?>
			<dt>Reservation Override</dt><dd><?php echo o100_cc_badge( $resv_override, 'Yes (Interval: ' . ( $opt_hours['o100_reservation_interval'] ?? '—' ) . 'min, Max: ' . ( $opt_hours['o100_resv_max_per_slot'] ?? '—' ) . ')', 'No — uses Global' ); ?></dd>
			<?php endif; ?>
		</dl>
		<table>
			<thead><tr><th>Day</th><th>Global</th><?php if ( $deli_override ) : ?><th>Delivery</th><?php endif; ?><?php if ( $pick_override ) : ?><th>Pickup</th><?php endif; ?><?php if ( $resv_on && $resv_override ) : ?><th>Reservation</th><?php endif; ?></tr></thead>
			<tbody>
				<?php foreach ( $days_of_week as $day ) : ?>
				<tr>
					<td style="font-weight:600;"><?php echo esc_html( $day ); ?></td>
					<td><?php echo o100_cc_format_day_schedule( $opt_hours[ 'o100_' . $day . '_opcl_time' ] ?? null ); ?></td>
					<?php if ( $deli_override ) : ?><td><?php echo o100_cc_format_day_schedule( $opt_hours[ 'o100_delivery_' . $day . '_opcl_time' ] ?? null ); ?></td><?php endif; ?>
					<?php if ( $pick_override ) : ?><td><?php echo o100_cc_format_day_schedule( $opt_hours[ 'o100_pickup_' . $day . '_opcl_time' ] ?? null ); ?></td><?php endif; ?>
					<?php if ( $resv_on && $resv_override ) : ?><td><?php echo o100_cc_format_day_schedule( $opt_hours[ 'o100_reservation_' . $day . '_opcl_time' ] ?? null ); ?></td><?php endif; ?>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<div style="margin-top:16px;">
			<dl class="o100-cc-param">
				<dt>Holidays</dt><dd><?php echo o100_cc_badge( $holidays_on ); ?><?php if ( $holidays_on && ! empty( $holidays_list ) ) : foreach ( $holidays_list as $h ) : ?> <span class="o100-cc-tag"><?php echo esc_html( ( $h['start'] ?? '' ) . ( ! empty( $h['end'] ) && $h['end'] !== ( $h['start'] ?? '' ) ? ' → ' . $h['end'] : '' ) . ': ' . ( $h['reason'] ?? '' ) ); ?></span><?php endforeach; endif; ?></dd>
				<dt>Outside Hours Ordering</dt><dd><?php echo o100_cc_badge( ! empty( $opt_hours['o100_op_cl'] ) && $opt_hours['o100_op_cl'] === 'on' ); ?></dd>
				<dt>Emergency Closure Bar</dt><dd><?php echo o100_cc_badge( ! empty( $opt_hours['o100_ec_enable_admin_bar'] ) && $opt_hours['o100_ec_enable_admin_bar'] === 'on' ); ?></dd>
			</dl>
		</div>
	</div>
</div>

<?php // ══ CARD 1: Order Methods ══ ?>
<div class="o100-cc-card">
	<div class="o100-cc-card-title"><h3>Order Methods</h3></div>
	<div class="o100-cc-card-body">
		<div class="o100-cc-cols">
			<div>
				<div class="o100-cc-section-label">Delivery <?php echo o100_cc_badge( $deli_on ); ?></div>
				<?php if ( $deli_on ) : ?>
				<dl class="o100-cc-param">
					<dt>Min Order</dt><dd>$<?php echo esc_html( $opt_delivery['o100_delivery_min_amount'] ?? '—' ); ?></dd>
					<dt>Lead Time</dt><dd><?php echo esc_html( $opt_delivery['o100_delivery_lead_time'] ?? '—' ); ?> min</dd>
					<dt>Restriction</dt><dd><?php echo esc_html( ucfirst( $deli_mode ) ); ?></dd>
					<?php if ( $deli_mode === 'radius' ) : ?>
					<dt>Max Distance</dt><dd><?php echo esc_html( $opt_delivery['o100_deli_dis'] ?? '—' ); ?> km</dd>
					<?php endif; ?>
					<dt>Base Fee</dt><dd>$<?php echo esc_html( $opt_delivery['o100_shipping_fee'] ?? '0' ); ?></dd>
					<dt>Free Over</dt><dd>$<?php echo esc_html( $opt_delivery['o100_shipping_freemax'] ?? '—' ); ?></dd>
					<?php $km_tiers = ! empty( $opt_delivery['o100_shp_km_loc'] ) ? $opt_delivery['o100_shp_km_loc'] : array();
					if ( ! empty( $km_tiers ) ) : ?>
					<dt>Distance Tiers</dt><dd><?php echo count( $km_tiers ); ?> rules</dd>
					<?php endif; ?>
					<?php $dis_pay = ! empty( $opt_delivery['o100_delivery_dis_payment'] ) ? $opt_delivery['o100_delivery_dis_payment'] : array();
					if ( ! empty( $dis_pay ) ) : ?>
					<dt>Disabled Payments</dt><dd><?php echo esc_html( o100_cc_payment_names( $dis_pay ) ); ?></dd>
					<?php endif; ?>
				</dl>
				<?php endif; ?>
			</div>
			<div>
				<div class="o100-cc-section-label">Pickup <?php echo o100_cc_badge( $pick_on ); ?></div>
				<?php if ( $pick_on ) : ?>
				<dl class="o100-cc-param">
					<dt>Min Order</dt><dd>$<?php echo esc_html( $opt_pickup['o100_pickup_min_amount'] ?? '—' ); ?></dd>
					<dt>Lead Time</dt><dd><?php echo esc_html( $opt_pickup['o100_pickup_lead_time'] ?? '—' ); ?> min</dd>
					<?php 
						$p_fee_action = $opt_pickup['o100_pickup_fee_action'] ?? 'none';
						if ( $p_fee_action !== 'none' ) : 
							$p_fee_val = $opt_pickup['o100_pickup_fee_val'] ?? '0';
							$p_fee_type = $opt_pickup['o100_pickup_fee_type'] ?? 'percent';
							$p_fee_symbol = $p_fee_type === 'percent' ? '%' : '$';
							$p_fee_str = $p_fee_type === 'percent' ? $p_fee_val . '%' : '$' . $p_fee_val;
					?>
					<dt><?php echo $p_fee_action === 'discount' ? 'Discount' : 'Fee'; ?></dt><dd><?php echo esc_html( $p_fee_str ); ?></dd>
					<?php endif; ?>
					<?php $p_dis_pay = ! empty( $opt_pickup['o100_pickup_dis_payment'] ) ? $opt_pickup['o100_pickup_dis_payment'] : array();
					if ( ! empty( $p_dis_pay ) ) : ?>
					<dt>Disabled Payments</dt><dd><?php echo esc_html( o100_cc_payment_names( $p_dis_pay ) ); ?></dd>
					<?php endif; ?>
				</dl>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php // ══ CARD 3: Branches ══ ?>
<div class="o100-cc-card">
	<div class="o100-cc-card-title"><h3>Branches</h3> <?php echo o100_cc_badge( $loc_on ); ?></div>
	<div class="o100-cc-card-body">
		<?php if ( $loc_on ) :
			$branches = get_posts( array( 'post_type' => 'o100_location', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
			if ( ! empty( $branches ) ) : ?>
			<table>
				<thead><tr><th>Branch</th><th>Schedule Override</th><th>Delivery</th><th>Pickup</th><th>Status</th><th>Hidden Items</th></tr></thead>
				<tbody>
					<?php foreach ( $branches as $b ) :
						$b_id       = $b->ID;
						$b_override = get_post_meta( $b_id, 'o100_override_hours', true ) === 'on';
						$b_deli     = get_post_meta( $b_id, 'o100_enable_delivery', true ) === 'on';
						$b_pick     = get_post_meta( $b_id, 'o100_enable_pickup', true ) === 'on';
						$b_closed   = get_post_meta( $b_id, 'o100_closed', true ) === 'on';
						$b_hidden_cats = json_decode( get_post_meta( $b_id, 'o100_hidden_cats', true ), true );
						$b_hidden_pros = json_decode( get_post_meta( $b_id, 'o100_hidden_products', true ), true );
						$hidden_parts = array();
						if ( ! empty( $b_hidden_cats ) ) { $cn = o100_cc_category_names( $b_hidden_cats ); if ( ! empty( $cn ) ) $hidden_parts[] = '🏷️ ' . implode( ', ', $cn ); }
						if ( ! empty( $b_hidden_pros ) ) { $pn = o100_cc_product_names( $b_hidden_pros ); if ( ! empty( $pn ) ) $hidden_parts[] = '🍽️ ' . implode( ', ', $pn ); }
					?>
					<tr>
						<td style="font-weight:600;"><?php echo esc_html( $b->post_title ); ?></td>
						<td><?php echo o100_cc_badge( $b_override, 'Yes', 'No' ); ?></td>
						<td><?php echo $b_deli ? '✅' : '—'; ?></td>
						<td><?php echo $b_pick ? '✅' : '—'; ?></td>
						<td><?php echo $b_closed ? '<span style="color:#dc2626;font-weight:600;">🔴 Closed</span>' : '<span style="color:#059669;font-weight:600;">🟢 Open</span>'; ?></td>
						<td><?php echo ! empty( $hidden_parts ) ? wp_kses_post( implode( '<br>', $hidden_parts ) ) : '<span style="color:#94a3b8;">—</span>'; ?></td>
					</tr>
					<?php if ( $b_override ) :
						$b_hours = get_post_meta( $b_id, 'o100_hours', true );
						if ( ! empty( $b_hours ) && is_array( $b_hours ) ) : ?>
					<tr><td colspan="6" style="padding:4px 12px 12px 24px;background:#f8fafc;">
						<table style="width:auto;margin-top:4px;"><thead><tr><th>Day</th><th>Global</th><th>Branch</th></tr></thead><tbody>
						<?php foreach ( $days_of_week as $day ) :
							$g_val = o100_cc_format_day_schedule( $opt_hours[ 'o100_' . $day . '_opcl_time' ] ?? null );
							$b_val = isset( $b_hours[ strtolower( $day ) ] ) ? o100_cc_format_day_schedule( $b_hours[ strtolower( $day ) ] ) : 'Closed';
							$diff = ( $g_val !== $b_val ) ? ' style="background:#fef3c7;"' : '';
						?>
						<tr><td style="font-weight:600;"><?php echo esc_html( $day ); ?></td><td><?php echo $g_val; ?></td><td<?php echo $diff; ?>><?php echo $b_val; ?></td></tr>
						<?php endforeach; ?>
						</tbody></table>
					</td></tr>
					<?php endif; endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?><p style="color:#d97706;font-weight:600;">⚠️ No branches created.</p><?php endif; ?>
		<?php else : ?><p style="color:#64748b;">Multi-Branch mode is disabled.</p><?php endif; ?>
	</div>
</div>

<?php // ══ CARD 4: Menu Rules ══ 
$menu_sc_usage = o100_cc_find_shortcode( array( 'o100_menu', 'order100_food_menu', 'ex_wf_grid', 'ex_wf_list', 'ex_wf_carousel', 'ex_wf_table' ) );
$mr_ecom_on = ! empty( $opt_menu_rules['o100_enable_ecom'] ) && $opt_menu_rules['o100_enable_ecom'] === 'on';
?>
<div class="o100-cc-card" id="cc-menu">
	<div class="o100-cc-card-title"><h3>Menu Rules & Display</h3></div>
	<div class="o100-cc-card-body">
		<dl class="o100-cc-param">
			<dt>Shortcode Usage</dt><dd>
				<?php if ( empty( $menu_sc_usage ) ) : ?>
					<span style="color:#64748b;">Not found in any standard post/page</span>
				<?php else : ?>
					<ul style="margin:0;padding-left:16px;">
						<?php foreach ( $menu_sc_usage as $post ) : ?>
							<li><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" target="_blank"><?php echo esc_html( $post->post_title ); ?></a> (<?php echo esc_html( $post->post_type ); ?>)</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</dd>
			<dt>Menu Method Filter</dt><dd><?php echo $mr_method_on ? o100_cc_badge('Enabled') : 'Disabled'; ?></dd>
		</dl>
		<?php if ( $mr_method_on ) :
			$deli_only_pro = o100_cc_product_names( $opt_menu_rules['o100_delivery_only_pro'] ?? array() );
			$deli_only_cat = o100_cc_category_names( $opt_menu_rules['o100_delivery_only_cat'] ?? array() );
			$pick_only_pro = o100_cc_product_names( $opt_menu_rules['o100_pickup_only_pro'] ?? array() );
			$pick_only_cat = o100_cc_category_names( $opt_menu_rules['o100_pickup_only_cat'] ?? array() );
		?>
		<div class="o100-cc-cols" style="margin-bottom:16px;">
			<div>
				<strong style="font-size:12px;color:#475569;">Delivery Only:</strong><br>
				<?php if ( ! empty( $deli_only_pro ) ) : foreach ( $deli_only_pro as $n ) : ?><span class="o100-cc-tag o100-cc-tag-prod">🍽️ <?php echo esc_html( $n ); ?></span><?php endforeach; endif; ?>
				<?php if ( ! empty( $deli_only_cat ) ) : foreach ( $deli_only_cat as $n ) : ?><span class="o100-cc-tag o100-cc-tag-cat">🏷️ <?php echo esc_html( $n ); ?></span><?php endforeach; endif; ?>
				<?php if ( empty( $deli_only_pro ) && empty( $deli_only_cat ) ) : ?><span style="color:#94a3b8;font-size:13px;">(none)</span><?php endif; ?>
			</div>
			<div>
				<strong style="font-size:12px;color:#475569;">Pickup Only:</strong><br>
				<?php if ( ! empty( $pick_only_pro ) ) : foreach ( $pick_only_pro as $n ) : ?><span class="o100-cc-tag o100-cc-tag-prod">🍽️ <?php echo esc_html( $n ); ?></span><?php endforeach; endif; ?>
				<?php if ( ! empty( $pick_only_cat ) ) : foreach ( $pick_only_cat as $n ) : ?><span class="o100-cc-tag o100-cc-tag-cat">🏷️ <?php echo esc_html( $n ); ?></span><?php endforeach; endif; ?>
				<?php if ( empty( $pick_only_pro ) && empty( $pick_only_cat ) ) : ?><span style="color:#94a3b8;font-size:13px;">(none)</span><?php endif; ?>
			</div>
		</div>
		<?php
			$deli_ids = $opt_menu_rules['o100_delivery_only_pro'] ?? array();
			$pick_ids = $opt_menu_rules['o100_pickup_only_pro'] ?? array();
			if ( is_array( $deli_ids ) && is_array( $pick_ids ) ) {
				$overlap = array_intersect( $deli_ids, $pick_ids );
				if ( ! empty( $overlap ) ) {
					$overlap_names = o100_cc_product_names( $overlap );
					echo '<p style="color:#dc2626;font-size:13px;">❌ Conflict: ' . esc_html( implode( ', ', $overlap_names ) ) . ' appear in both Delivery Only and Pickup Only.</p>';
				}
			}
		?>
		<?php endif; ?>

		<div class="o100-cc-section-label" style="margin-top:20px;">Daily Menu <?php echo o100_cc_badge( $mr_date_on ); ?></div>
		<?php if ( $mr_date_on && ! empty( $mr_date_rules ) ) : ?>
		<table>
			<thead><tr><th>#</th><th>Type</th><th>When</th><th>Affected Items</th></tr></thead>
			<tbody>
				<?php foreach ( $mr_date_rules as $i => $rule ) :
					$r_type = $rule['o100_rule_type'] ?? 'weekdays';
					$r_days = $rule['o100_rule_days'] ?? array();
					$r_range = $rule['o100_rule_date_range'] ?? '';
					$r_assign = $rule['o100_rule_assign_type'] ?? 'products';
					$when = '';
					if ( $r_type === 'weekdays' || $r_type === 'both' ) $when .= is_array( $r_days ) ? implode( ', ', $r_days ) : '';
					if ( $r_type === 'date_range' || $r_type === 'both' ) { if ( $when ) $when .= ' + '; $when .= $r_range; }
					$items_html = '';
					if ( $r_assign === 'categories' ) {
						foreach ( o100_cc_category_names( $rule['o100_rule_categories'] ?? array() ) as $cn ) $items_html .= '<span class="o100-cc-tag o100-cc-tag-cat">🏷️ ' . esc_html( $cn ) . '</span> ';
					} else {
						foreach ( o100_cc_product_names( $rule['o100_rule_products'] ?? array() ) as $pn ) $items_html .= '<span class="o100-cc-tag o100-cc-tag-prod">🍽️ ' . esc_html( $pn ) . '</span> ';
					}
					if ( empty( $items_html ) ) $items_html = '<span style="color:#94a3b8;">—</span>';
				?>
				<tr><td><?php echo intval( $i + 1 ); ?></td><td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $r_type ) ) ); ?></td><td><?php echo esc_html( $when ); ?></td><td><?php echo $items_html; ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php elseif ( $mr_date_on ) : ?><p style="color:#d97706;font-size:13px;">⚠️ Enabled but no rules configured.</p><?php endif; ?>

		<div class="o100-cc-section-label" style="margin-top:20px;">Standard eCommerce Mode <?php echo o100_cc_badge( $mr_ecom_on ); ?></div>
		<?php if ( $mr_ecom_on ) :
			$ecom_pro = o100_cc_product_names( $opt_menu_rules['o100_disable_food_pro'] ?? array() );
			$ecom_cat = o100_cc_category_names( $opt_menu_rules['o100_disable_food_cat'] ?? array() );
		?>
			<?php if ( ! empty( $ecom_pro ) ) : ?><div style="margin-bottom:4px;"><strong style="font-size:12px;color:#475569;">Products:</strong> <?php foreach ( $ecom_pro as $n ) : ?><span class="o100-cc-tag o100-cc-tag-prod">🍽️ <?php echo esc_html( $n ); ?></span><?php endforeach; ?></div><?php endif; ?>
			<?php if ( ! empty( $ecom_cat ) ) : ?><div><strong style="font-size:12px;color:#475569;">Categories:</strong> <?php foreach ( $ecom_cat as $n ) : ?><span class="o100-cc-tag o100-cc-tag-cat">🏷️ <?php echo esc_html( $n ); ?></span><?php endforeach; ?></div><?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<?php // ══ CARD 7: Reservation ══ 
$resv_sc_usage = o100_cc_find_shortcode( 'o100_reservation' );
?>
<div class="o100-cc-card" id="cc-reservation">
	<div class="o100-cc-card-title">
		<h3>Reservation</h3>
		<?php echo $resv_on ? o100_cc_badge('Enabled') : o100_cc_badge('Disabled', 'error'); ?>
	</div>
	<div class="o100-cc-card-body">
		<?php if ( $resv_on ) : ?>
		<dl class="o100-cc-param">
			<dt>Shortcode Usage</dt><dd>
				<?php if ( empty( $resv_sc_usage ) ) : ?>
					<span style="color:#64748b;">Not found in any standard post/page</span>
				<?php else : ?>
					<ul style="margin:0;padding-left:16px;">
						<?php foreach ( $resv_sc_usage as $post ) : ?>
							<li><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" target="_blank"><?php echo esc_html( $post->post_title ); ?></a> (<?php echo esc_html( $post->post_type ); ?>)</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</dd>
			<dt>Auto Confirmation</dt><dd><?php echo esc_html( ucfirst( $opt_reservation['o100_resv_confirmation'] ?? 'Manual' ) ); ?></dd>
			<dt>Max Party</dt><dd><?php echo esc_html( $opt_reservation['o100_resv_max_party'] ?? '10' ); ?></dd>
			<dt>Lead Time</dt><dd><?php echo esc_html( $opt_reservation['o100_resv_lead_time'] ?? '2' ); ?> hours</dd>
			<dt>Max Advance</dt><dd><?php echo esc_html( $opt_reservation['o100_resv_max_advance'] ?? '30' ); ?> days</dd>
			<dt>Private Rooms</dt><dd><?php
				$rooms_on_flag = ! empty( $opt_reservation['o100_resv_enable_rooms'] ) && $opt_reservation['o100_resv_enable_rooms'] === 'on';
				echo o100_cc_badge( $rooms_on_flag );
				if ( $rooms_on_flag && ! empty( $opt_reservation['o100_resv_rooms'] ) ) {
					$rooms_data = is_string( $opt_reservation['o100_resv_rooms'] ) ? json_decode( $opt_reservation['o100_resv_rooms'], true ) : $opt_reservation['o100_resv_rooms'];
					if ( is_array( $rooms_data ) ) {
						$room_names = wp_list_pluck( $rooms_data, 'name' );
						if ( ! empty( $room_names ) ) echo ' — ' . esc_html( implode( ', ', $room_names ) );
					}
				}
			?></dd>
			<dt>Admin Email</dt><dd><?php echo ! empty( $opt_reservation['o100_resv_admin_email'] ) ? esc_html( $opt_reservation['o100_resv_admin_email'] ) : '<span style="color:#d97706;">⚠️ Not set</span>'; ?></dd>
		</dl>
		<?php else : ?><p style="color:#64748b;">Reservation module is disabled.</p><?php endif; ?>
	</div>
</div>

<?php // ══ CARD 8: Notifications ══ ?>
<div class="o100-cc-card">
	<div class="o100-cc-card-title"><h3>Notifications</h3></div>
	<div class="o100-cc-card-body">
		<div class="o100-cc-section-label">SMS <?php echo o100_cc_badge( $sms_on ); ?></div>
		<?php if ( $sms_on ) : ?>
		<dl class="o100-cc-param">
			<dt>Gateway</dt><dd><?php echo esc_html( ucfirst( $opt_notifications['o100_sms_gateway'] ?? '—' ) ); ?></dd>
			<dt>API Key</dt><dd><?php echo ! empty( $opt_notifications['o100_sms_api_key'] ) ? '<span style="color:#059669;">✅ ' . esc_html( o100_cc_mask( $opt_notifications['o100_sms_api_key'] ) ) . '</span>' : '<span style="color:#94a3b8;">—</span>'; ?></dd>
			<dt>Sender</dt><dd><?php echo ! empty( $opt_notifications['o100_sms_sender_number'] ) ? '<span style="color:#059669;">✅ ' . esc_html( o100_cc_mask( $opt_notifications['o100_sms_sender_number'] ) ) . '</span>' : '<span style="color:#94a3b8;">—</span>'; ?></dd>
		</dl>
		<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:4px;">
			<?php foreach ( $sms_templates as $tpl_key => $tpl_label ) :
				$has_tpl = ! empty( $opt_notifications[ $tpl_key ] ); ?>
				<span class="o100-cc-tag" style="background:<?php echo $has_tpl ? '#ecfdf5' : '#fef3c7'; ?>;color:<?php echo $has_tpl ? '#059669' : '#92400e'; ?>;"><?php echo $has_tpl ? '✅' : '⚠️'; ?> <?php echo esc_html( $tpl_label ); ?></span>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
</div>

<?php // ══ CARD 9: Integrations ══
$tip_deli = ! empty( $opt_options['o100_tip_delivery_enable'] ) && $opt_options['o100_tip_delivery_enable'] === 'on';
$tip_pick = ! empty( $opt_options['o100_tip_pickup_enable'] ) && $opt_options['o100_tip_pickup_enable'] === 'on';
?>
<div class="o100-cc-card">
	<div class="o100-cc-card-title"><h3>Integrations</h3></div>
	<div class="o100-cc-card-body">
		<dl class="o100-cc-param">
			<dt>Google Maps Key</dt><dd><?php echo ! empty( $maps_key ) ? '<span style="color:#059669;">✅ ' . esc_html( o100_cc_mask( $maps_key ) ) . '</span>' : '<span style="color:' . ( $deli_on && $deli_mode === 'radius' ? '#dc2626' : '#94a3b8' ) . ';">' . ( $deli_on && $deli_mode === 'radius' ? '❌ Missing (required)' : '—' ) . '</span>'; ?></dd>
			<dt>WhatsApp</dt><dd><?php echo ! empty( $opt_api['o100_wa_num'] ) ? esc_html( o100_cc_mask( $opt_api['o100_wa_num'] ) ) : '<span style="color:#94a3b8;">—</span>'; ?></dd>
		</dl>
		
		<div class="o100-cc-section-label" style="margin-top:16px;">Voice Call API <?php echo o100_cc_badge( $voice_on ); ?></div>
		<?php if ( $voice_on ) : ?>
		<dl class="o100-cc-param">
			<dt>Delay</dt><dd><?php echo esc_html( $opt_notifications['o100_voice_delay_mins'] ?? '3' ); ?> min</dd>
			<dt>Retry</dt><dd><?php echo esc_html( $opt_notifications['o100_voice_retry_count'] ?? '0' ); ?> times</dd>
			<dt>Language</dt><dd><?php echo esc_html( $opt_notifications['o100_voice_language'] ?? 'en-US' ); ?></dd>
		</dl>
		<?php endif; ?>
	</div>
</div>

<?php // ══ CARD 10: Tipping ══ ?>
<div class="o100-cc-card">
	<div class="o100-cc-card-title"><h3>Tipping</h3></div>
	<div class="o100-cc-card-body">
		<dl class="o100-cc-param">
			<dt>Delivery Tip</dt><dd><?php echo o100_cc_badge( $tip_deli ); ?></dd>
			<dt>Pickup Tip</dt><dd><?php echo o100_cc_badge( $tip_pick ); ?></dd>
			<?php if ( $tip_deli || $tip_pick ) : ?>
			<dt>Type</dt><dd><?php echo esc_html( ucfirst( $opt_options['o100_tip_type'] ?? 'percent' ) ); ?></dd>
			<dt>Presets</dt><dd><?php echo esc_html( $opt_options['o100_tip_val'] ?? '—' ); ?></dd>
			<?php endif; ?>
		</dl>
	</div>
</div>
<?php // ══ CARD 5: Promotions ══ ?>
<div class="o100-cc-card">
	<div class="o100-cc-card-title"><h3>Promotions</h3></div>
	<div class="o100-cc-card-body">
		<?php if ( $has_promo_table ) : ?>
		<dl class="o100-cc-param" style="margin-bottom:12px;"><dt>Active Rules</dt><dd><strong><?php echo $promo_active; ?></strong></dd><dt>Inactive Rules</dt><dd><?php echo $promo_inactive; ?></dd></dl>
		<?php if ( ! empty( $promo_rows ) ) : ?>
		<table>
			<thead><tr><th>#</th><th>Title</th><th>Code</th><th>Apply To</th><th>Validity</th></tr></thead>
			<tbody>
				<?php foreach ( $promo_rows as $idx => $pr ) :
					$apply_html = esc_html( $pr->apply_to );
					if ( $pr->apply_to !== 'all_products' && ! empty( $pr->apply_to_items ) ) {
						$items_data = json_decode( $pr->apply_to_items, true );
						if ( is_array( $items_data ) ) {
							$apply_parts = array();
							if ( ! empty( $items_data['products'] ) ) foreach ( o100_cc_product_names( $items_data['products'] ) as $pn ) $apply_parts[] = '<span class="o100-cc-tag o100-cc-tag-prod">🍽️ ' . esc_html( $pn ) . '</span>';
							if ( ! empty( $items_data['categories'] ) ) foreach ( o100_cc_category_names( $items_data['categories'] ) as $cn ) $apply_parts[] = '<span class="o100-cc-tag o100-cc-tag-cat">🏷️ ' . esc_html( $cn ) . '</span>';
							if ( ! empty( $apply_parts ) ) $apply_html = implode( ' ', $apply_parts );
						}
					}
					$validity = $pr->end_date ? '→ ' . wp_date( 'M j, Y', strtotime( $pr->end_date ) ) : 'No expiry';
				?>
				<tr>
					<td><?php echo intval( $idx + 1 ); ?></td>
					<td style="font-weight:600;"><?php echo esc_html( $pr->title ); ?></td>
					<td><?php echo $pr->promo_code ? '<code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px;">' . esc_html( $pr->promo_code ) . '</code>' : '<span style="color:#94a3b8;">—</span>'; ?></td>
					<td><?php echo $apply_html; ?></td>
					<td><?php echo esc_html( $validity ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
		<?php else : ?><p style="color:#94a3b8;">Promotions table not found.</p><?php endif; ?>
	</div>
</div>

<?php // ══ CARD 6: Loyalty ══ ?>
<div class="o100-cc-card">
	<div class="o100-cc-card-title"><h3>Loyalty</h3> <?php echo o100_cc_badge( $loyalty_on ); ?></div>
	<div class="o100-cc-card-body">
		<?php if ( $loyalty_on ) : ?>
			<?php if ( ! empty( $loyalty_campaigns ) ) : ?>
			<div class="o100-cc-section-label">Campaigns</div>
			<table><thead><tr><th>Title</th><th>Type</th><th>Status</th></tr></thead><tbody>
				<?php foreach ( $loyalty_campaigns as $lc ) : ?>
				<tr><td style="font-weight:600;"><?php echo esc_html( $lc->title ); ?></td><td><?php echo esc_html( $lc->type ); ?></td><td><?php echo o100_cc_badge( $lc->status === 'active', 'Active', ucfirst( $lc->status ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<?php else : ?><p style="color:#d97706;">⚠️ No campaigns configured.</p><?php endif; ?>
			<?php if ( ! empty( $loyalty_levels ) ) : ?>
			<div class="o100-cc-section-label" style="margin-top:16px;">Membership Levels</div>
			<div style="display:flex;gap:8px;flex-wrap:wrap;">
				<?php foreach ( $loyalty_levels as $ll ) : ?>
				<span class="o100-cc-tag" style="padding:6px 14px;font-size:13px;"><?php echo esc_html( $ll->name ); ?> <span style="color:#94a3b8;font-size:11px;">(<?php echo intval( $ll->min_points ); ?>-<?php echo $ll->max_points ? intval( $ll->max_points ) : '∞'; ?>)</span></span>
				<?php endforeach; ?>
			</div>
			<?php else : ?><p style="color:#d97706;margin-top:8px;">⚠️ No levels configured.</p><?php endif; ?>
		<?php else : ?><p style="color:#64748b;">Loyalty module is disabled.</p><?php endif; ?>
	</div>
</div>

<?php // ══ CARD 10: Automation ══ ?>
<div class="o100-cc-card" id="cc-automation">
	<div class="o100-cc-card-title"><h3>Automations</h3></div>
	<div class="o100-cc-card-body">
		<?php if ( $has_auto_table ) : ?>
		<dl class="o100-cc-param" style="margin-bottom:12px;"><dt>Active Workflows</dt><dd><strong><?php echo $auto_active; ?></strong></dd><dt>Inactive Workflows</dt><dd><?php echo $auto_inactive; ?></dd></dl>
		<?php if ( ! empty( $auto_rows ) ) : ?>
		<table>
			<thead><tr><th>#</th><th>Title</th><th>Trigger Type</th></tr></thead>
			<tbody>
				<?php foreach ( $auto_rows as $row ) : ?>
				<tr>
					<td><?php echo $row->id; ?></td>
					<td><?php echo esc_html( $row->title ); ?></td>
					<td><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?php echo esc_html( $row->trigger_type ); ?></code></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php else : ?>
		<p style="color:#64748b;font-size:13px;margin:0;">No active automation workflows found.</p>
		<?php endif; ?>
		<?php else : ?>
		<p style="color:#dc2626;font-size:13px;margin:0;">Automation table not found.</p>
		<?php endif; ?>
	</div>
</div>

</div>
