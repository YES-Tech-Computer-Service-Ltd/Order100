<?php
/**
 * Dashboard API Class
 * Handles AJAX requests for dashboard metrics and charts.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Dashboard_API {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		// AJAX endpoints
		add_action( 'wp_ajax_o100_dash_get_overview', array( $this, 'ajax_get_overview' ) );
		add_action( 'wp_ajax_o100_dash_get_sales', array( $this, 'ajax_get_sales' ) );
		add_action( 'wp_ajax_o100_dash_get_marketing', array( $this, 'ajax_get_marketing' ) );
		add_action( 'wp_ajax_o100_dash_get_customers', array( $this, 'ajax_get_customers' ) );
	}

	/**
	 * Helper to parse date range from request
	 */
	private function get_date_range() {
		$range_type = isset( $_POST['date_range'] ) ? sanitize_text_field( $_POST['date_range'] ) : '30days';
		
		$end_date = current_time( 'Y-m-d 23:59:59' );
		$start_date = current_time( 'Y-m-d 00:00:00' );
		$current_time = current_time( 'timestamp' );
		
		$prev_end_date = '';
		$prev_start_date = '';

		switch ( $range_type ) {
			case 'today':
				$prev_start_date = date( 'Y-m-d 00:00:00', strtotime( '-1 day', $current_time ) );
				$prev_end_date   = date( 'Y-m-d 23:59:59', strtotime( '-1 day', $current_time ) );
				break;
			case 'yesterday':
				$start_date = date( 'Y-m-d 00:00:00', strtotime( '-1 day', $current_time ) );
				$end_date   = date( 'Y-m-d 23:59:59', strtotime( '-1 day', $current_time ) );
				$prev_start_date = date( 'Y-m-d 00:00:00', strtotime( '-2 days', $current_time ) );
				$prev_end_date   = date( 'Y-m-d 23:59:59', strtotime( '-2 days', $current_time ) );
				break;
			case '7days':
				$start_date = date( 'Y-m-d 00:00:00', strtotime( '-6 days', $current_time ) );
				$prev_start_date = date( 'Y-m-d 00:00:00', strtotime( '-13 days', $current_time ) );
				$prev_end_date   = date( 'Y-m-d 23:59:59', strtotime( '-7 days', $current_time ) );
				break;
			case '30days':
				$start_date = date( 'Y-m-d 00:00:00', strtotime( '-29 days', $current_time ) );
				$prev_start_date = date( 'Y-m-d 00:00:00', strtotime( '-59 days', $current_time ) );
				$prev_end_date   = date( 'Y-m-d 23:59:59', strtotime( '-30 days', $current_time ) );
				break;
			case 'this_month':
				$start_date = date( 'Y-m-01 00:00:00', $current_time );
				$prev_start_date = date( 'Y-m-01 00:00:00', strtotime( 'first day of last month', $current_time ) );
				$prev_end_date   = date( 'Y-m-t 23:59:59', strtotime( 'last day of last month', $current_time ) );
				break;
			case 'last_month':
				$start_date = date( 'Y-m-01 00:00:00', strtotime( 'first day of last month', $current_time ) );
				$end_date   = date( 'Y-m-t 23:59:59', strtotime( 'last day of last month', $current_time ) );
				$prev_start_date = date( 'Y-m-01 00:00:00', strtotime( 'first day of -2 months', $current_time ) );
				$prev_end_date   = date( 'Y-m-t 23:59:59', strtotime( 'last day of -2 months', $current_time ) );
				break;
			case 'this_year':
				$start_date = date( 'Y-01-01 00:00:00', $current_time );
				$prev_start_date = date( 'Y-01-01 00:00:00', strtotime( 'first day of january last year', $current_time ) );
				$prev_end_date   = date( 'Y-12-31 23:59:59', strtotime( 'last day of december last year', $current_time ) );
				break;
		}

		return array(
			'start' => $start_date,
			'end'   => $end_date,
			'prev_start' => $prev_start_date,
			'prev_end'   => $prev_end_date,
		);
	}

	private function get_order_table_info() {
		global $wpdb;
		$use_hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		
		return array(
			'use_hpos' => $use_hpos,
			'orders'   => $use_hpos ? "{$wpdb->prefix}wc_orders" : "{$wpdb->posts}",
			'meta'     => $use_hpos ? "{$wpdb->prefix}wc_orders_meta" : "{$wpdb->postmeta}",
			'col_id'   => $use_hpos ? "id" : "ID",
			'col_stat' => $use_hpos ? "status" : "post_status",
			'col_date' => $use_hpos ? "date_created_gmt" : "post_date",
			'col_auth' => $use_hpos ? "customer_id" : "post_author",
			'col_mid'  => $use_hpos ? "order_id" : "post_id",
			'status_in'=> $use_hpos ? "('wc-completed', 'wc-processing')" : "('wc-completed', 'wc-processing')"
		);
	}

	public function ajax_get_overview() {
		check_ajax_referer( 'o100_dashboard_nonce', 'nonce' );
		$dates = $this->get_date_range();
		$range_type = isset( $_POST['date_range'] ) ? sanitize_text_field( $_POST['date_range'] ) : '30days';
		$cache_key = 'o100_dash_overview_' . $range_type;
		
		// Bypass cache for 'today' or if explicitly requested
		$cache_time = ( $range_type === 'today' || $range_type === 'yesterday' ) ? 300 : 3600; // 5 mins vs 1 hour
		
		$cached = get_transient( $cache_key );
		if ( false !== $cached && ! (defined('WP_DEBUG') && WP_DEBUG) ) {
			// wp_send_json_success( $cached ); // Temporarily bypass cache
		}

		global $wpdb;

		$t = (object) $this->get_order_table_info();

		// 1. Current Period Data
		if ( $t->use_hpos ) {
			$sql_current = $wpdb->prepare( "
				SELECT 
					COUNT(DISTINCT id) as order_count,
					SUM(total_amount) as total_sales,
					COUNT(DISTINCT customer_id) as unique_customers
				FROM {$t->orders}
				WHERE status IN {$t->status_in}
				AND date_created_gmt >= %s
				AND date_created_gmt <= %s
			", $dates['start'], $dates['end'] );
		} else {
			$sql_current = $wpdb->prepare( "
				SELECT 
					COUNT(DISTINCT p.ID) as order_count,
					SUM(pm.meta_value) as total_sales,
					COUNT(DISTINCT p.post_author) as unique_customers
				FROM {$t->orders} p
				LEFT JOIN {$t->meta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
				WHERE p.post_status IN {$t->status_in}
				AND p.post_date >= %s
				AND p.post_date <= %s
			", $dates['start'], $dates['end'] );
		}

		$current_data = $wpdb->get_row( $sql_current );

		// 2. Previous Period Data for Trends
		if ( $t->use_hpos ) {
			$sql_prev = $wpdb->prepare( "
				SELECT 
					COUNT(DISTINCT id) as order_count,
					SUM(total_amount) as total_sales,
					COUNT(DISTINCT customer_id) as unique_customers
				FROM {$t->orders}
				WHERE status IN {$t->status_in}
				AND date_created_gmt >= %s
				AND date_created_gmt <= %s
			", $dates['prev_start'], $dates['prev_end'] );
		} else {
			$sql_prev = $wpdb->prepare( "
				SELECT 
					COUNT(DISTINCT p.ID) as order_count,
					SUM(pm.meta_value) as total_sales,
					COUNT(DISTINCT p.post_author) as unique_customers
				FROM {$t->orders} p
				LEFT JOIN {$t->meta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
				WHERE p.post_status IN {$t->status_in}
				AND p.post_date >= %s
				AND p.post_date <= %s
			", $dates['prev_start'], $dates['prev_end'] );
		}

		$prev_data = $wpdb->get_row( $sql_prev );

		// Calculations
		$curr_sales = (float) $current_data->total_sales;
		$prev_sales = (float) $prev_data->total_sales;
		$curr_orders = (int) $current_data->order_count;
		$prev_orders = (int) $prev_data->order_count;
		
		$curr_aov = $curr_orders > 0 ? $curr_sales / $curr_orders : 0;
		$prev_aov = $prev_orders > 0 ? $prev_sales / $prev_orders : 0;
		
		// 3. Live Activity (Recent 5 Orders)
		if ( $t->use_hpos ) {
			$sql_live = "SELECT id, total_amount, date_created_gmt as date FROM {$t->orders} ORDER BY date_created_gmt DESC LIMIT 5";
		} else {
			$sql_live = "
				SELECT p.ID as id, pm.meta_value as total_amount, p.post_date as date 
				FROM {$t->orders} p 
				LEFT JOIN {$t->meta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total' 
				WHERE p.post_type = 'shop_order' 
				ORDER BY p.post_date DESC LIMIT 5";
		}
		$live_raw = $wpdb->get_results( $sql_live );
		$live_activity = array();
		foreach ( $live_raw as $row ) {
			$live_activity[] = array(
				'id'     => $row->id,
				'total'  => wc_price( (float) $row->total_amount ),
				'time'   => human_time_diff( strtotime($row->date), current_time('timestamp') ) . ' ago'
			);
		}

		// New Customers estimation
		$curr_new_customers = (int) $current_data->unique_customers; 
		$prev_new_customers = (int) $prev_data->unique_customers;

		if ( class_exists('O100_Customers_DB') ) {
			$curr_new_customers = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$wpdb->prefix}o100_customers WHERE created_at >= %s AND created_at <= %s", $dates['start'], $dates['end'] ) );
			$prev_new_customers = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$wpdb->prefix}o100_customers WHERE created_at >= %s AND created_at <= %s", $dates['prev_start'], $dates['prev_end'] ) );
		}

		// Trend Calculation Helper
		$calc_trend = function($curr, $prev) {
			if ( $prev == 0 ) return $curr > 0 ? '+100%' : '0%';
			$diff = (($curr - $prev) / $prev) * 100;
			return ($diff > 0 ? '+' : '') . round($diff, 1) . '%';
		};
		
		$trend_class = function($curr, $prev) {
			if ( $curr > $prev ) return 'o100-trend-up';
			if ( $curr < $prev ) return 'o100-trend-down';
			return 'o100-trend-neutral';
		};

		// 3. Chart Data (Daily breakdown)
		if ( $t->use_hpos ) {
			$sql_chart = $wpdb->prepare( "
				SELECT 
					DATE(date_created_gmt) as order_date,
					COUNT(DISTINCT id) as order_count,
					SUM(total_amount) as total_sales
				FROM {$t->orders}
				WHERE status IN {$t->status_in}
				AND date_created_gmt >= %s
				AND date_created_gmt <= %s
				GROUP BY DATE(date_created_gmt)
				ORDER BY DATE(date_created_gmt) ASC
			", $dates['start'], $dates['end'] );
		} else {
			$sql_chart = $wpdb->prepare( "
				SELECT 
					DATE(p.post_date) as order_date,
					COUNT(DISTINCT p.ID) as order_count,
					SUM(pm.meta_value) as total_sales
				FROM {$t->orders} p
				LEFT JOIN {$t->meta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
				WHERE p.post_status IN {$t->status_in}
				AND p.post_date >= %s
				AND p.post_date <= %s
				GROUP BY DATE(p.post_date)
				ORDER BY DATE(p.post_date) ASC
			", $dates['start'], $dates['end'] );
		}

		$chart_results = $wpdb->get_results( $sql_chart );
		
		$chart_labels = array();
		$chart_sales = array();
		$chart_orders = array();
		
		$period = new DatePeriod(
			new DateTime( $dates['start'] ),
			new DateInterval('P1D'),
			(new DateTime( $dates['end'] ))->modify('+1 day')
		);
		
		$result_map = array();
		foreach ( $chart_results as $row ) {
			$result_map[$row->order_date] = $row;
		}

		foreach ( $period as $dt ) {
			$date_str = $dt->format('Y-m-d');
			$chart_labels[] = $dt->format('M j');
			if ( isset( $result_map[$date_str] ) ) {
				$chart_sales[] = (float) $result_map[$date_str]->total_sales;
				$chart_orders[] = (int) $result_map[$date_str]->order_count;
			} else {
				$chart_sales[] = 0;
				$chart_orders[] = 0;
			}
		}

		$response_data = array(
			'sales_total'  => wc_price( $curr_sales ),
			'orders_count' => $curr_orders,
			'aov'          => wc_price( $curr_aov ),
			'new_customers'=> $curr_new_customers,
			'trend_sales'  => array( 'text' => $calc_trend($curr_sales, $prev_sales), 'class' => $trend_class($curr_sales, $prev_sales) ),
			'trend_orders' => array( 'text' => $calc_trend($curr_orders, $prev_orders), 'class' => $trend_class($curr_orders, $prev_orders) ),
			'trend_aov'    => array( 'text' => $calc_trend($curr_aov, $prev_aov), 'class' => $trend_class($curr_aov, $prev_aov) ),
			'trend_new'    => array( 'text' => $calc_trend($curr_new_customers, $prev_new_customers), 'class' => $trend_class($curr_new_customers, $prev_new_customers) ),
			'live_activity'=> $live_activity,
			'chart' => array(
				'labels' => $chart_labels,
				'sales'  => $chart_sales,
				'orders' => $chart_orders
			)
		);
		
		set_transient( $cache_key, $response_data, $cache_time );
		wp_send_json_success( $response_data );
	}
	public function ajax_get_sales() {
		check_ajax_referer( 'o100_dashboard_nonce', 'nonce' );
		
		$dates = $this->get_date_range();
		global $wpdb;

		$t = (object) $this->get_order_table_info();

		// 1. Order Types (Delivery vs Pickup vs Dine-in)
		if ( $t->use_hpos ) {
			$sql_types = $wpdb->prepare( "
				SELECT 
					pm.meta_value as order_type,
					COUNT(p.id) as count,
					SUM(p.total_amount) as revenue
				FROM {$t->orders} p
				LEFT JOIN {$t->meta} pm ON p.id = pm.order_id AND pm.meta_key = 'o100_order_type'
				WHERE p.status IN {$t->status_in}
				AND p.date_created_gmt >= %s
				AND p.date_created_gmt <= %s
				GROUP BY pm.meta_value
			", $dates['start'], $dates['end'] );
		} else {
			$sql_types = $wpdb->prepare( "
				SELECT 
					pm.meta_value as order_type,
					COUNT(p.ID) as count,
					SUM(pm2.meta_value) as revenue
				FROM {$t->orders} p
				LEFT JOIN {$t->meta} pm ON p.ID = pm.post_id AND pm.meta_key = 'o100_order_type'
				LEFT JOIN {$t->meta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_order_total'
				WHERE p.post_status IN {$t->status_in}
				AND p.post_date >= %s
				AND p.post_date <= %s
				GROUP BY pm.meta_value
			", $dates['start'], $dates['end'] );
		}
		
		$type_results = $wpdb->get_results( $sql_types );
		$order_types_count = array( 'Delivery' => 0, 'Pickup' => 0 );
		$order_types_rev   = array( 'Delivery' => 0.0, 'Pickup' => 0.0 );
		foreach ( $type_results as $row ) {
			$type = strtolower((string)$row->order_type);
			if ( strpos($type, 'delivery') !== false ) {
				$order_types_count['Delivery'] += (int)$row->count;
				$order_types_rev['Delivery']   += (float)$row->revenue;
			} else {
				$order_types_count['Pickup'] += (int)$row->count;
				$order_types_rev['Pickup']   += (float)$row->revenue;
			}
		}

		// 2. Top Items (Top 10 sold products)
		if ( $t->use_hpos ) {
			$sql_top = $wpdb->prepare( "
				SELECT 
					order_item_name as name,
					SUM(im_qty.meta_value) as qty,
					SUM(im_total.meta_value) as revenue
				FROM {$wpdb->prefix}woocommerce_order_items oi
				JOIN {$t->orders} p ON oi.order_id = p.id
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im_qty ON oi.order_item_id = im_qty.order_item_id AND im_qty.meta_key = '_qty'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im_total ON oi.order_item_id = im_total.order_item_id AND im_total.meta_key = '_line_total'
				WHERE p.status IN {$t->status_in}
				AND oi.order_item_type = 'line_item'
				AND p.date_created_gmt >= %s
				AND p.date_created_gmt <= %s
				GROUP BY order_item_name
				ORDER BY qty DESC
				LIMIT 10
			", $dates['start'], $dates['end'] );
		} else {
			$sql_top = $wpdb->prepare( "
				SELECT 
					order_item_name as name,
					SUM(im_qty.meta_value) as qty,
					SUM(im_total.meta_value) as revenue
				FROM {$wpdb->prefix}woocommerce_order_items oi
				JOIN {$t->orders} p ON oi.order_id = p.ID
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im_qty ON oi.order_item_id = im_qty.order_item_id AND im_qty.meta_key = '_qty'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im_total ON oi.order_item_id = im_total.order_item_id AND im_total.meta_key = '_line_total'
				WHERE p.post_status IN {$t->status_in}
				AND oi.order_item_type = 'line_item'
				AND p.post_date >= %s
				AND p.post_date <= %s
				GROUP BY order_item_name
				ORDER BY qty DESC
				LIMIT 10
			", $dates['start'], $dates['end'] );
		}

		$top_items_raw = $wpdb->get_results( $sql_top );
		$top_items = array();
		foreach ( $top_items_raw as $item ) {
			$top_items[] = array(
				'name'    => $item->name,
				'qty'     => (int) $item->qty,
				'revenue' => wc_price( (float) $item->revenue )
			);
		}

		// 2.5 Top Categories
		if ( $t->use_hpos ) {
			$sql_cat = $wpdb->prepare( "
				SELECT 
					t.name as name,
					SUM(im_qty.meta_value) as qty,
					SUM(im_total.meta_value) as revenue
				FROM {$wpdb->prefix}woocommerce_order_items oi
				JOIN {$t->orders} p ON oi.order_id = p.id
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im_qty ON oi.order_item_id = im_qty.order_item_id AND im_qty.meta_key = '_qty'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im_total ON oi.order_item_id = im_total.order_item_id AND im_total.meta_key = '_line_total'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im_pid ON oi.order_item_id = im_pid.order_item_id AND im_pid.meta_key = '_product_id'
				JOIN {$wpdb->prefix}term_relationships tr ON tr.object_id = im_pid.meta_value
				JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
				JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
				WHERE p.status IN {$t->status_in}
				AND oi.order_item_type = 'line_item'
				AND p.date_created_gmt >= %s
				AND p.date_created_gmt <= %s
				GROUP BY t.term_id
				ORDER BY qty DESC
				LIMIT 10
			", $dates['start'], $dates['end'] );
		} else {
			$sql_cat = $wpdb->prepare( "
				SELECT 
					t.name as name,
					SUM(im_qty.meta_value) as qty,
					SUM(im_total.meta_value) as revenue
				FROM {$wpdb->prefix}woocommerce_order_items oi
				JOIN {$t->orders} p ON oi.order_id = p.ID
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im_qty ON oi.order_item_id = im_qty.order_item_id AND im_qty.meta_key = '_qty'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im_total ON oi.order_item_id = im_total.order_item_id AND im_total.meta_key = '_line_total'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im_pid ON oi.order_item_id = im_pid.order_item_id AND im_pid.meta_key = '_product_id'
				JOIN {$wpdb->prefix}term_relationships tr ON tr.object_id = im_pid.meta_value
				JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
				JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
				WHERE p.post_status IN {$t->status_in}
				AND oi.order_item_type = 'line_item'
				AND p.post_date >= %s
				AND p.post_date <= %s
				GROUP BY t.term_id
				ORDER BY qty DESC
				LIMIT 10
			", $dates['start'], $dates['end'] );
		}

		$top_cats_raw = $wpdb->get_results( $sql_cat );
		$top_cats = array();
		foreach ( $top_cats_raw as $cat ) {
			$top_cats[] = array(
				'name'    => html_entity_decode( $cat->name, ENT_QUOTES, 'UTF-8' ),
				'qty'     => (int) $cat->qty,
				'revenue' => wc_price( (float) $cat->revenue )
			);
		}

		// 3. Peak Hours Heatmap (Day of week x Hour of day)
		if ( $t->use_hpos ) {
			$sql_heatmap = $wpdb->prepare( "
				SELECT 
					DAYOFWEEK(date_created_gmt) as day_num,
					HOUR(date_created_gmt) as hour_num,
					COUNT(id) as count
				FROM {$t->orders}
				WHERE status IN {$t->status_in}
				AND date_created_gmt >= %s
				AND date_created_gmt <= %s
				GROUP BY day_num, hour_num
			", $dates['start'], $dates['end'] );
		} else {
			$sql_heatmap = $wpdb->prepare( "
				SELECT 
					DAYOFWEEK(post_date) as day_num,
					HOUR(post_date) as hour_num,
					COUNT(ID) as count
				FROM {$t->orders}
				WHERE post_status IN {$t->status_in}
				AND post_date >= %s
				AND post_date <= %s
				GROUP BY day_num, hour_num
			", $dates['start'], $dates['end'] );
		}

		$heatmap_raw = $wpdb->get_results( $sql_heatmap );
		
		// MySQL DAYOFWEEK: 1=Sunday, 2=Monday, ... 7=Saturday
		// We'll format to 0=Mon, 6=Sun
		$heatmap = array();
		// Initialize empty heatmap (7 days x 24 hours)
		for ( $d = 0; $d < 7; $d++ ) {
			for ( $h = 0; $h < 24; $h++ ) {
				$heatmap[$d][$h] = 0;
			}
		}
		
		foreach ( $heatmap_raw as $row ) {
			$mysql_day = (int) $row->day_num;
			// Convert to 0-indexed Monday-start
			$d = $mysql_day - 2; 
			if ( $d < 0 ) $d = 6; // Sunday
			$h = (int) $row->hour_num;
			$heatmap[$d][$h] = (int) $row->count;
		}

		wp_send_json_success( array(
			'chart_types'     => $order_types_count,
			'chart_types_rev' => $order_types_rev,
			'top_items'       => $top_items,
			'top_cats'        => $top_cats,
			'heatmap'         => $heatmap
		) );
	}

	public function ajax_get_marketing() {
		check_ajax_referer( 'o100_dashboard_nonce', 'nonce' );
		$dates = $this->get_date_range();
		global $wpdb;

		$start = $dates['start'];
		$end = $dates['end'];
		$t = (object) $this->get_order_table_info();

		// 1. Points Flow
		$points_issued = 0;
		$points_redeemed = 0;
		if ( class_exists('O100_Loyalty_DB') ) {
			$t_trans = O100_Loyalty_DB::table_transactions();
			
			$issued = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(points) FROM {$t_trans} WHERE type IN ('earn','adjust') AND points > 0 AND created_at >= %s AND created_at <= %s",
				$start, $end
			) );
			
			$redeemed = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(points) FROM {$t_trans} WHERE type = 'spend' AND created_at >= %s AND created_at <= %s",
				$start, $end
			) );
			
			$points_issued = abs($issued);
			$points_redeemed = abs($redeemed);
		}

		// 2. Promo Discount Total
		if ( $t->use_hpos ) {
			$promo_discount = (float) $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(pm.meta_value) FROM {$t->orders} p
				JOIN {$t->meta} pm ON p.id = pm.order_id AND pm.meta_key = '_cart_discount'
				WHERE p.status IN {$t->status_in}
				AND p.date_created_gmt >= %s AND p.date_created_gmt <= %s",
				$start, $end
			) );
		} else {
			$promo_discount = (float) $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(pm.meta_value) FROM {$t->orders} p
				JOIN {$t->meta} pm ON p.ID = pm.post_id AND pm.meta_key = '_cart_discount'
				WHERE p.post_status IN {$t->status_in}
				AND p.post_date >= %s AND p.post_date <= %s",
				$start, $end
			) );
		}

		// 3. Customer Tiers
		$tier_labels = array('None');
		$tier_counts = array(0);
		if ( class_exists('O100_Loyalty_DB') ) {
			$t_acc = O100_Loyalty_DB::table_accounts();
			$t_lvl = O100_Loyalty_DB::table_levels();
			
			$tier_data = $wpdb->get_results( "
				SELECT l.name, COUNT(a.id) as count 
				FROM {$t_acc} a 
				LEFT JOIN {$t_lvl} l ON a.level_id = l.id 
				GROUP BY a.level_id
			" );
			
			$tier_labels = array();
			$tier_counts = array();
			foreach ( $tier_data as $t ) {
				$tier_labels[] = $t->name ? $t->name : 'Basic';
				$tier_counts[] = (int) $t->count;
			}
		}

		$funnel_data = [0, 0, 0, 0];
		if ( class_exists('O100_Automation_DB') ) {
			$t_logs = $wpdb->prefix . 'o100_automation_logs';
			// Check if table exists (silently)
			$logs_exist = $wpdb->get_var("SHOW TABLES LIKE '{$t_logs}'") === $t_logs;
			if ( $logs_exist ) {
				$funnel_data[0] = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$t_logs} WHERE created_at >= '{$start}' AND created_at <= '{$end}'");
				$funnel_data[1] = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$t_logs} WHERE status IN ('sent', 'opened', 'clicked') AND created_at >= '{$start}' AND created_at <= '{$end}'");
				$funnel_data[2] = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$t_logs} WHERE status IN ('opened', 'clicked') AND created_at >= '{$start}' AND created_at <= '{$end}'");
				$funnel_data[3] = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$t_logs} WHERE status = 'clicked' AND created_at >= '{$start}' AND created_at <= '{$end}'");
			}
		}

		wp_send_json_success( array(
			'points_issued'  => number_format_i18n($points_issued),
			'points_redeemed'=> number_format_i18n($points_redeemed),
			'promo_discount' => wc_price($promo_discount),
			'tiers_enabled'  => get_option('o100_loyalty_enable_tiers', 'yes') === 'yes',
			'tier_chart' => array(
				'labels' => $tier_labels,
				'data'   => $tier_counts
			),
			'funnel' => array(
				'labels' => ['Triggered', 'Sent', 'Opened', 'Clicked'],
				'data'   => $funnel_data
			)
		) );
	}

	public function ajax_get_customers() {
		check_ajax_referer( 'o100_dashboard_nonce', 'nonce' );
		$dates = $this->get_date_range();
		global $wpdb;

		$start = $dates['start'];
		$end = $dates['end'];
		$t = (object) $this->get_order_table_info();

		$new_customers = 0;
		$returning_customers = 0;
		$top_spenders = array();
		$churn_risk = array();

		if ( class_exists('O100_Customers_DB') ) {
			$t_cust = $wpdb->prefix . 'o100_customers';
			
			// 1. New vs Returning (based on orders placed within the period)
			// Total unique customers who placed an order in period
			if ( $t->use_hpos ) {
				$total_unique_buyers = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(DISTINCT customer_id) FROM {$t->orders} WHERE status IN {$t->status_in} AND date_created_gmt >= %s AND date_created_gmt <= %s AND customer_id > 0",
					$start, $end
				) );
			} else {
				$total_unique_buyers = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(DISTINCT post_author) FROM {$t->orders} WHERE post_status IN {$t->status_in} AND post_date >= %s AND post_date <= %s AND post_author > 0",
					$start, $end
				) );
			}

			$new_customers = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(id) FROM {$t_cust} WHERE created_at >= %s AND created_at <= %s",
				$start, $end
			) );
			
			$returning_customers = max(0, $total_unique_buyers - $new_customers);

			// 2. Top Spenders (Lifetime)
			$top_spenders_raw = $wpdb->get_results( "
				SELECT CONCAT(first_name, ' ', last_name) as display_name, total_orders, total_spent 
				FROM {$t_cust} 
				ORDER BY total_spent DESC 
				LIMIT 10
			" );
			foreach ( $top_spenders_raw as $s ) {
				$top_spenders[] = array(
					'name'   => $s->display_name,
					'orders' => $s->total_orders,
					'ltv'    => wc_price($s->total_spent)
				);
			}

			// 3. Churn Risk
			$churn_days = (int) apply_filters( 'o100_churn_risk_days', get_option( 'o100_churn_risk_days', 60 ) );
			$churn_date = date( 'Y-m-d H:i:s', strtotime( "-{$churn_days} days" ) );

			$churn_raw = $wpdb->get_results( $wpdb->prepare( "
				SELECT CONCAT(first_name, ' ', last_name) as display_name, last_order_date, total_spent 
				FROM {$t_cust} 
				WHERE last_order_date < %s AND total_orders > 1
				ORDER BY total_spent DESC 
				LIMIT 10
			", $churn_date ) );
			foreach ( $churn_raw as $c ) {
				$churn_risk[] = array(
					'name' => $c->display_name,
					'last' => date_i18n( get_option('date_format'), strtotime($c->last_order_date) ),
					'ltv'  => wc_price($c->total_spent)
				);
			}
		}

		wp_send_json_success( array(
			'new_returning' => array(
				'labels' => array( 'New', 'Returning' ),
				'data'   => array( $new_customers, $returning_customers )
			),
			'top_spenders' => $top_spenders,
			'churn_risk'   => $churn_risk
		) );
	}

}
