<?php
/**
 * Dashboard Admin Class
 * Handles the rendering of the modern Dashboard UI.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Dashboard_Admin {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts( $hook ) {
		// Only enqueue on our dashboard page
		// The dashboard is hooked into 'toplevel_page_order100' or similar in class-o100-admin-menu.php
		if ( strpos( $hook, 'order100' ) !== false && ! isset( $_GET['page'] ) || (isset($_GET['page']) && $_GET['page'] === 'order100') ) {
			// Enqueue Chart.js from CDN for now
			wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true );
			
			// Dashboard specific CSS/JS (inline for now, can be extracted later)
		}
	}

	/**
	 * Main render function for the Dashboard
	 */
	public function render() {
		?>
		<style>
			.o100-dash-wrapper {
				padding: 24px;
				max-width: 1400px;
				margin: 0 auto;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}
			.o100-dash-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 24px;
			}
			.o100-dash-title {
				font-size: 24px;
				font-weight: 600;
				color: #111827;
				margin: 0;
			}
			.o100-dash-subtitle {
				font-size: 14px;
				color: #6b7280;
				margin-top: 4px;
			}
			.o100-date-picker-wrap select {
				padding: 8px 32px 8px 12px;
				border: 1px solid #d1d5db;
				border-radius: 6px;
				background-color: #fff;
				font-size: 14px;
				color: #374151;
				box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
			}
			
			/* Tabs */
			.o100-dash-tabs {
				display: flex;
				border-bottom: 1px solid #e5e7eb;
				margin-bottom: 24px;
				gap: 32px;
			}
			.o100-dash-tab {
				padding: 12px 4px;
				font-size: 14px;
				font-weight: 500;
				color: #6b7280;
				cursor: pointer;
				border-bottom: 2px solid transparent;
				transition: all 0.2s;
			}
			.o100-dash-tab:hover {
				color: #374151;
				border-bottom-color: #d1d5db;
			}
			.o100-dash-tab.active {
				color: #F59322;
				border-bottom-color: #F59322;
			}
			
			/* Tab Panes */
			.o100-dash-pane { display: none; }
			.o100-dash-pane.active { display: block; }

			/* Grid System */
			.o100-grid {
				display: grid;
				gap: 24px;
			}
			.o100-grid-4 { grid-template-columns: repeat(4, 1fr); }
			.o100-grid-3 { grid-template-columns: repeat(3, 1fr); }
			.o100-grid-2 { grid-template-columns: repeat(2, 1fr); }
			.o100-grid-2-1 { grid-template-columns: 2fr 1fr; }

			/* Cards */
			.o100-dash-card {
				background: #fff;
				border: 1px solid #e5e7eb;
				border-radius: 12px;
				padding: 20px;
				box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
			}
			.o100-kpi-title {
				font-size: 13px;
				font-weight: 500;
				color: #6b7280;
				margin-bottom: 8px;
			}
			.o100-kpi-value {
				font-size: 28px;
				font-weight: 700;
				color: #111827;
				display: flex;
				align-items: baseline;
				gap: 8px;
			}
			.o100-kpi-trend {
				font-size: 13px;
				font-weight: 500;
				padding: 2px 8px;
				border-radius: 12px;
			}
			.o100-trend-up { background: #dcfce7; color: #166534; }
			.o100-trend-down { background: #fee2e2; color: #991b1b; }
			.o100-trend-neutral { background: #f3f4f6; color: #374151; }

			.o100-card-header {
				font-size: 16px;
				font-weight: 600;
				color: #111827;
				margin-bottom: 16px;
				display: flex;
				align-items: center;
				justify-content: space-between;
			}
			
			.o100-loading-overlay {
				position: absolute;
				inset: 0;
				background: rgba(255,255,255,0.7);
				display: flex;
				align-items: center;
				justify-content: center;
				border-radius: 12px;
				z-index: 10;
			}
			.o100-spinner {
				width: 24px;
				height: 24px;
				border: 3px solid #e5e7eb;
				border-top-color: #F59322;
				border-radius: 50%;
				animation: o100-spin 1s linear infinite;
			}
			@keyframes o100-spin {
				to { transform: rotate(360deg); }
			}
			.o100-relative { position: relative; }
		</style>

		<div class="o100-dash-wrapper">
			<div class="o100-dash-header">
				<div>
					<h1 class="o100-dash-title"><?php esc_html_e( 'Restaurant Analytics', 'order100' ); ?></h1>
					<p class="o100-dash-subtitle"><?php esc_html_e( 'Track your sales, marketing, and customer insights in real-time.', 'order100' ); ?></p>
				</div>
				<div class="o100-date-picker-wrap">
					<select id="o100-dash-date-range">
						<option value="today"><?php esc_html_e( 'Today', 'order100' ); ?></option>
						<option value="yesterday"><?php esc_html_e( 'Yesterday', 'order100' ); ?></option>
						<option value="7days"><?php esc_html_e( 'Last 7 Days', 'order100' ); ?></option>
						<option value="30days" selected><?php esc_html_e( 'Last 30 Days', 'order100' ); ?></option>
						<option value="this_month"><?php esc_html_e( 'This Month', 'order100' ); ?></option>
						<option value="last_month"><?php esc_html_e( 'Last Month', 'order100' ); ?></option>
						<option value="this_year"><?php esc_html_e( 'This Year', 'order100' ); ?></option>
					</select>
				</div>
			</div>

			<div class="o100-dash-tabs">
				<div class="o100-dash-tab active" data-target="#dash-overview"><?php esc_html_e( 'Overview', 'order100' ); ?></div>
				<div class="o100-dash-tab" data-target="#dash-sales"><?php esc_html_e( 'Sales & Orders', 'order100' ); ?></div>
				<div class="o100-dash-tab" data-target="#dash-marketing"><?php esc_html_e( 'Marketing & Loyalty', 'order100' ); ?></div>
				<div class="o100-dash-tab" data-target="#dash-customers"><?php esc_html_e( 'Customers', 'order100' ); ?></div>
			</div>

			<!-- OVERVIEW PANE -->
			<div id="dash-overview" class="o100-dash-pane active">
				<div class="o100-grid o100-grid-4" style="margin-bottom: 24px;">
					<!-- KPI: Total Sales -->
					<div class="o100-dash-card o100-relative" id="kpi-sales">
						<div class="o100-kpi-title"><?php esc_html_e( 'Total Sales', 'order100' ); ?></div>
						<div class="o100-kpi-value">
							<span class="value">...</span>
							<span class="o100-kpi-trend trend-badge">...</span>
						</div>
					</div>
					<!-- KPI: Orders -->
					<div class="o100-dash-card o100-relative" id="kpi-orders">
						<div class="o100-kpi-title"><?php esc_html_e( 'Total Orders', 'order100' ); ?></div>
						<div class="o100-kpi-value">
							<span class="value">...</span>
							<span class="o100-kpi-trend trend-badge">...</span>
						</div>
					</div>
					<!-- KPI: AOV -->
					<div class="o100-dash-card o100-relative" id="kpi-aov">
						<div class="o100-kpi-title"><?php esc_html_e( 'Avg. Order Value', 'order100' ); ?></div>
						<div class="o100-kpi-value">
							<span class="value">...</span>
							<span class="o100-kpi-trend trend-badge">...</span>
						</div>
					</div>
					<!-- KPI: New Customers -->
					<div class="o100-dash-card o100-relative" id="kpi-new-customers">
						<div class="o100-kpi-title"><?php esc_html_e( 'New Customers', 'order100' ); ?></div>
						<div class="o100-kpi-value">
							<span class="value">...</span>
							<span class="o100-kpi-trend trend-badge">...</span>
						</div>
					</div>
				</div>

				<div class="o100-grid o100-grid-2-1">
					<div class="o100-dash-card o100-relative" id="chart-sales-overview">
						<div class="o100-card-header"><?php esc_html_e( 'Sales & Orders Trend', 'order100' ); ?></div>
						<div style="position: relative; height: 300px; width: 100%;">
							<canvas id="overviewSalesChart"></canvas>
						</div>
					</div>
					<div class="o100-dash-card o100-relative" id="feed-recent-activity">
						<div class="o100-card-header"><?php esc_html_e( 'Live Activity', 'order100' ); ?></div>
						<div class="o100-activity-list" style="max-height: 300px; overflow-y: auto;">
							<!-- Placeholder for live feed -->
							<div style="text-align:center; color:#9ca3af; padding: 20px; font-size:13px;">Coming soon...</div>
						</div>
					</div>
				</div>
			</div>

			<!-- SALES PANE -->
			<div id="dash-sales" class="o100-dash-pane">
				<div class="o100-grid o100-grid-2" style="margin-bottom: 24px;">
					<div class="o100-dash-card o100-relative" id="chart-peak-hours">
						<div class="o100-card-header"><?php esc_html_e( 'Peak Hours Heatmap', 'order100' ); ?></div>
						<div id="heatmap-container" style="position: relative; height: 300px; width: 100%; color:#9ca3af; font-size:13px; overflow: hidden;">
							Heatmap rendering coming soon...
						</div>
					</div>
					<div class="o100-dash-card o100-relative" id="chart-order-types">
						<div class="o100-card-header" style="display:flex; justify-content:space-between; align-items:center;">
							<span><?php esc_html_e( 'Order Types', 'order100' ); ?></span>
							<select id="o100-ordertype-toggle" class="o100-select" style="font-size:13px; padding:4px 28px 4px 12px; min-height:30px; width:120px;">
								<option value="count"><?php esc_html_e( 'By Orders', 'order100' ); ?></option>
								<option value="revenue"><?php esc_html_e( 'By Revenue', 'order100' ); ?></option>
							</select>
						</div>
						<div style="position: relative; height: 300px; width: 100%;">
							<canvas id="salesOrderTypesChart"></canvas>
						</div>
					</div>
				</div>
				<div class="o100-dash-card o100-relative" id="table-top-items">
					<div class="o100-card-header" style="display:flex; justify-content:space-between; align-items:center;">
						<span id="o100-topitems-title"><?php esc_html_e( 'Top Selling Items', 'order100' ); ?></span>
						<select id="o100-topitems-toggle" class="o100-select" style="font-size:13px; padding:4px 28px 4px 12px; min-height:30px; width:120px;">
							<option value="item"><?php esc_html_e( 'By Product', 'order100' ); ?></option>
							<option value="category"><?php esc_html_e( 'By Category', 'order100' ); ?></option>
						</select>
					</div>
					<table style="width: 100%; border-collapse: collapse; text-align: left;">
						<thead>
							<tr style="border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 13px;">
								<th id="o100-topitems-col" style="padding: 12px 0;"><?php esc_html_e( 'Item', 'order100' ); ?></th>
								<th style="padding: 12px 0;"><?php esc_html_e( 'Quantity Sold', 'order100' ); ?></th>
								<th style="padding: 12px 0; text-align: right;"><?php esc_html_e( 'Revenue', 'order100' ); ?></th>
							</tr>
						</thead>
						<tbody id="top-items-tbody">
							<tr><td colspan="3" style="text-align:center; padding: 20px; color:#9ca3af; font-size:13px;">Loading...</td></tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- MARKETING PANE -->
			<div id="dash-marketing" class="o100-dash-pane">
				<div class="o100-grid o100-grid-3" style="margin-bottom: 24px;">
					<div class="o100-dash-card o100-relative" id="kpi-points-issued">
						<div class="o100-kpi-title"><?php esc_html_e( 'Points Issued', 'order100' ); ?></div>
						<div class="o100-kpi-value"><span class="value">...</span></div>
					</div>
					<div class="o100-dash-card o100-relative" id="kpi-points-redeemed">
						<div class="o100-kpi-title"><?php esc_html_e( 'Points Redeemed', 'order100' ); ?></div>
						<div class="o100-kpi-value"><span class="value">...</span></div>
					</div>
					<div class="o100-dash-card o100-relative" id="kpi-promo-usage">
						<div class="o100-kpi-title"><?php esc_html_e( 'Promo Discount Total', 'order100' ); ?></div>
						<div class="o100-kpi-value"><span class="value">...</span></div>
					</div>
				</div>
				<div class="o100-grid o100-grid-2">
					<div class="o100-dash-card o100-relative" id="chart-tier-dist">
						<div class="o100-card-header"><?php esc_html_e( 'Customer Tiers', 'order100' ); ?></div>
						<div style="position: relative; height: 300px; width: 100%;">
							<canvas id="marketingTierChart"></canvas>
						</div>
					</div>
					<div class="o100-dash-card o100-relative" id="chart-automations">
						<div class="o100-card-header"><?php esc_html_e( 'Automation Funnel', 'order100' ); ?></div>
						<div style="position: relative; height: 300px; width: 100%;">
							<canvas id="marketingFunnelChart"></canvas>
						</div>
					</div>
				</div>
			</div>

			<!-- CUSTOMERS PANE -->
			<div id="dash-customers" class="o100-dash-pane">
				<div class="o100-grid o100-grid-2" style="margin-bottom: 24px;">
					<div class="o100-dash-card o100-relative" id="chart-new-returning">
						<div class="o100-card-header"><?php esc_html_e( 'New vs Returning', 'order100' ); ?></div>
						<div style="position: relative; height: 300px; width: 100%;">
							<canvas id="customersNewRetChart"></canvas>
						</div>
					</div>
					<div class="o100-dash-card o100-relative" id="table-churn-risk">
						<div class="o100-card-header">
							<?php esc_html_e( 'Churn Risk', 'order100' ); ?>
							<span style="font-size:12px; font-weight:normal; color:#9ca3af;">(>60 days inactive)</span>
						</div>
						<table style="width: 100%; border-collapse: collapse; text-align: left;">
							<thead>
								<tr style="border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 13px;">
									<th style="padding: 12px 0;"><?php esc_html_e( 'Customer', 'order100' ); ?></th>
									<th style="padding: 12px 0;"><?php esc_html_e( 'Last Order', 'order100' ); ?></th>
									<th style="padding: 12px 0; text-align: right;"><?php esc_html_e( 'LTV', 'order100' ); ?></th>
								</tr>
							</thead>
							<tbody id="churn-risk-tbody">
								<tr><td colspan="3" style="text-align:center; padding: 20px; color:#9ca3af; font-size:13px;">Loading...</td></tr>
							</tbody>
						</table>
					</div>
				</div>
				<div class="o100-dash-card o100-relative" id="table-top-spenders">
					<div class="o100-card-header"><?php esc_html_e( 'Top Spenders', 'order100' ); ?></div>
					<table style="width: 100%; border-collapse: collapse; text-align: left;">
						<thead>
							<tr style="border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 13px;">
								<th style="padding: 12px 0;"><?php esc_html_e( 'Customer', 'order100' ); ?></th>
								<th style="padding: 12px 0;"><?php esc_html_e( 'Total Orders', 'order100' ); ?></th>
								<th style="padding: 12px 0; text-align: right;"><?php esc_html_e( 'LTV (Lifetime Value)', 'order100' ); ?></th>
							</tr>
						</thead>
						<tbody id="top-spenders-tbody">
							<tr><td colspan="3" style="text-align:center; padding: 20px; color:#9ca3af; font-size:13px;">Loading...</td></tr>
						</tbody>
					</table>
				</div>
			</div>

		</div>

		<script>
			var o100_currency_symbol = '<?php echo esc_js( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ); ?>';
			jQuery(document).ready(function($) {
			// Tab Switching
			$('.o100-dash-tab').click(function() {
				$('.o100-dash-tab').removeClass('active');
				$(this).addClass('active');
				
				$('.o100-dash-pane').removeClass('active');
				$($(this).data('target')).addClass('active');
				
				// Re-trigger resize on charts so they fit correctly if they were hidden
				if (typeof Chart !== 'undefined') {
					for (var id in Chart.instances) {
						Chart.instances[id].resize();
					}
				}
			});

			var overviewChart = null;

			function loadOverviewData(dateRange) {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'o100_dash_get_overview',
						nonce: '<?php echo wp_create_nonce("o100_dashboard_nonce"); ?>',
						date_range: dateRange
					},
					success: function(res) {
						if(res.success) {
							var d = res.data;
							// Update KPIs
							$('#kpi-sales .value').html(d.sales_total);
							$('#kpi-sales .trend-badge').text(d.trend_sales.text).removeClass('o100-trend-up o100-trend-down o100-trend-neutral').addClass(d.trend_sales.class);
							
							$('#kpi-orders .value').text(d.orders_count);
							$('#kpi-orders .trend-badge').text(d.trend_orders.text).removeClass('o100-trend-up o100-trend-down o100-trend-neutral').addClass(d.trend_orders.class);
							
							$('#kpi-aov .value').html(d.aov);
							$('#kpi-aov .trend-badge').text(d.trend_aov.text).removeClass('o100-trend-up o100-trend-down o100-trend-neutral').addClass(d.trend_aov.class);
							
							$('#kpi-new-customers .value').text(d.new_customers);
							$('#kpi-new-customers .trend-badge').text(d.trend_new.text).removeClass('o100-trend-up o100-trend-down o100-trend-neutral').addClass(d.trend_new.class);

							// Update Chart
							if (overviewChart) {
								overviewChart.destroy();
							}
							
							var ctx = document.getElementById('overviewSalesChart');
							if (ctx) {
								var gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
								gradient.addColorStop(0, 'rgba(79, 70, 229, 0.2)');
								gradient.addColorStop(1, 'rgba(79, 70, 229, 0.0)');

								overviewChart = new Chart(ctx, {
									type: 'line',
									data: {
										labels: d.chart.labels,
										datasets: [
											{
												label: 'Sales',
												data: d.chart.sales,
												borderColor: '#F59322',
												backgroundColor: gradient,
												borderWidth: 2,
												yAxisID: 'y',
												fill: true,
												tension: 0.4,
												pointRadius: 0,
												pointHoverRadius: 6,
												pointHitRadius: 10
											},
											{
												label: 'Orders',
												data: d.chart.orders,
												borderColor: '#10b981',
												backgroundColor: 'transparent',
												borderWidth: 2,
												borderDash: [5, 5],
												yAxisID: 'y1',
												tension: 0.4,
												pointRadius: 0,
												pointHoverRadius: 6,
												pointHitRadius: 10
											}
										]
									},
									options: {
										responsive: true,
										maintainAspectRatio: false,
										interaction: {
											mode: 'index',
											intersect: false,
										},
										plugins: {
											tooltip: {
												backgroundColor: 'rgba(255, 255, 255, 0.9)',
												titleColor: '#111827',
												bodyColor: '#374151',
												borderColor: '#e5e7eb',
												borderWidth: 1,
												padding: 10,
												displayColors: true,
												boxPadding: 4,
												usePointStyle: true,
												callbacks: {
													label: function(context) {
														let label = context.dataset.label || '';
														if (label) { label += ': '; }
														if (context.datasetIndex === 0) {
															label += o100_currency_symbol + parseFloat(context.raw).toFixed(2);
														} else {
															label += context.raw;
														}
														return label;
													}
												}
											},
											legend: {
												position: 'top',
												align: 'end',
												labels: { usePointStyle: true, boxWidth: 8 }
											}
										},
										scales: {
											x: {
												grid: { display: false, drawBorder: false },
												ticks: {
													maxRotation: 0,
													autoSkip: true,
													maxTicksLimit: 10,
													color: '#6b7280',
													font: { size: 11 }
												}
											},
											y: {
												type: 'linear',
												display: true,
												position: 'left',
												grid: {
													color: '#f3f4f6',
													borderDash: [4, 4],
													drawBorder: false
												},
												ticks: {
													color: '#6b7280',
													font: { size: 11 },
													callback: function(value) {
														return value >= 1000 ? (value / 1000) + 'k' : value;
													}
												}
											},
											y1: {
												type: 'linear',
												display: true,
												position: 'right',
												grid: { drawOnChartArea: false },
												ticks: {
													color: '#10b981',
													font: { size: 11 }
												}
											}
										}
									}
								});
							}
							// Update Live Activity
							var liveContainer = $('#feed-recent-activity .o100-activity-list');
							liveContainer.empty();
							if (d.live_activity && d.live_activity.length > 0) {
								d.live_activity.forEach(function(activity) {
									liveContainer.append('<div style="display:flex; justify-content:space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6;">' + 
										'<div><span style="font-weight:500;">Order #' + activity.id + '</span></div>' +
										'<div style="text-align:right;"><span style="color:#10b981; font-weight:500;">' + activity.total + '</span><br><span style="font-size:12px; color:#9ca3af;">' + activity.time + '</span></div>' +
									'</div>');
								});
							} else {
								liveContainer.append('<div style="text-align:center; padding: 20px; color:#9ca3af; font-size:13px;">No recent orders.</div>');
							}
						}
					},
					complete: function() {
						$('#dash-overview .o100-loading-overlay').remove();
					}
				});
			}

			function loadSalesData(dateRange) {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'o100_dash_get_sales',
						nonce: '<?php echo wp_create_nonce("o100_dashboard_nonce"); ?>',
						date_range: dateRange
					},
					success: function(res) {
						if(res.success) {
							var d = res.data;
							
							// Top Items / Categories
							var tbody = $('#top-items-tbody');
							window.o100TopItemsData = {
								item: d.top_items || [],
								category: d.top_cats || []
							};
							
							function renderTopItemsTable() {
								var mode = $('#o100-topitems-toggle').val() || 'item';
								var sourceData = window.o100TopItemsData[mode];
								tbody.empty();
								
								// Update card title and column header dynamically
								if (mode === 'category') {
									$('#o100-topitems-title').text('Top Selling Categories');
									$('#o100-topitems-col').text('Category');
								} else {
									$('#o100-topitems-title').text('Top Selling Items');
									$('#o100-topitems-col').text('Item');
								}
								
								if (sourceData && sourceData.length > 0) {
									sourceData.forEach(function(item) {
										tbody.append('<tr><td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">' + item.name + '</td><td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">' + item.qty + '</td><td style="padding: 12px 0; text-align: right; border-bottom: 1px solid #f3f4f6; font-weight: 500;">' + item.revenue + '</td></tr>');
									});
								} else {
									tbody.append('<tr><td colspan="3" style="text-align:center; padding: 20px; color:#9ca3af; font-size:13px;">No sales data found for this period.</td></tr>');
								}
							}
							
							renderTopItemsTable();
							$('#o100-topitems-toggle').off('change').on('change', renderTopItemsTable);
							
							// Order Types Chart
							var ctx = document.getElementById('salesOrderTypesChart');
							if (ctx) {
								window.o100OrderTypesData = {
									count: d.chart_types,
									revenue: d.chart_types_rev
								};
								
								function renderOrderTypesChart() {
									var metric = $('#o100-ordertype-toggle').val() || 'count';
									var sourceData = window.o100OrderTypesData[metric];
									
									var totalTypes = Object.values(sourceData).reduce(function(a, b) { return a + b; }, 0);
									if (totalTypes === 0) {
										$(ctx).parent().html('<div style="text-align:center; padding: 40px 0; color:#9ca3af; font-size:13px;">No order type data available yet.</div>');
									} else {
										if (window.orderTypesChart) window.orderTypesChart.destroy();
										
										var labelsWithValues = Object.keys(sourceData).map(function(key) {
											var val = sourceData[key];
											var formattedVal = metric === 'revenue' ? o100_currency_symbol + parseFloat(val).toFixed(2) : val;
											return key + ' (' + formattedVal + ')';
										});
										
										window.orderTypesChart = new Chart(ctx, {
											type: 'doughnut',
											data: {
												labels: labelsWithValues,
												datasets: [{
													data: Object.values(sourceData),
													backgroundColor: ['#F59322', '#10b981']
												}]
											},
											options: {
												responsive: true,
												maintainAspectRatio: false,
												cutout: '70%',
												plugins: { 
													legend: { position: 'right' },
													tooltip: {
														callbacks: {
															label: function(context) {
																var val = context.parsed;
																return metric === 'revenue' ? o100_currency_symbol + val.toFixed(2) : val;
															}
														}
													}
												}
											}
										});
									}
								}
								
								renderOrderTypesChart();
								$('#o100-ordertype-toggle').off('change').on('change', renderOrderTypesChart);
							}

							// Heatmap Grid Update
							var heatmapContainer = $('#heatmap-container');
							heatmapContainer.empty();
							if (d.heatmap) {
								var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
								var html = '<div style="display:flex; flex-direction:column; gap:4px; margin-top:20px;">';
								// Find max for scaling colors
								var maxCount = 0;
								for (var r=0; r<7; r++) {
									for (var c=0; c<24; c++) {
										if (d.heatmap[r][c] > maxCount) maxCount = d.heatmap[r][c];
									}
								}
								
								for (var row=0; row<7; row++) {
									html += '<div style="display:flex; align-items:center; gap:4px;">';
									html += '<div style="width:30px; font-size:11px; color:#6b7280; text-align:right; padding-right:8px;">' + days[row] + '</div>';
									html += '<div style="display:flex; gap:4px; flex:1;">';
									for (var col=0; col<24; col++) {
										var count = d.heatmap[row][col];
										var color = '#ebedf0'; // default empty
										if (count > 0) {
											var intensity = count / maxCount;
											if (intensity > 0.75) color = '#216e39';
											else if (intensity > 0.5) color = '#30a14e';
											else if (intensity > 0.25) color = '#40c463';
											else color = '#9be9a8';
										}
										html += '<div title="' + count + ' orders at ' + col + ':00 ' + days[row] + '" style="flex:1; height:12px; border-radius:2px; background-color:' + color + ';"></div>';
									}
									html += '</div></div>';
								}
								
								// Hours X-axis
								html += '<div style="display:flex; align-items:center; gap:4px; margin-top:4px;">';
								html += '<div style="width:30px;"></div>';
								html += '<div style="display:flex; gap:4px; flex:1;">';
								for (var col=0; col<24; col++) {
									if (col % 4 === 0) {
										html += '<div style="flex:4; font-size:10px; color:#9ca3af; text-align:left;">' + col + '</div>';
									}
								}
								html += '</div></div>';
								
								html += '</div>';
								heatmapContainer.html(html);
							} else {
								heatmapContainer.html('<div style="text-align:center; color:#9ca3af; padding: 40px 0;">No heatmap data available.</div>');
							}
						}
					},
					complete: function() {
						$('#dash-sales .o100-loading-overlay').remove();
					}
				});
			}

			function loadMarketingData(dateRange) {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'o100_dash_get_marketing',
						nonce: '<?php echo wp_create_nonce("o100_dashboard_nonce"); ?>',
						date_range: dateRange
					},
					success: function(res) {
						if(res.success) {
							var d = res.data;
							
							$('#kpi-points-issued .value').text(d.points_issued);
							$('#kpi-points-redeemed .value').text(d.points_redeemed);
							$('#kpi-promo-usage .value').html(d.promo_discount);
							
							var ctx = document.getElementById('marketingTierChart');
							if (d.tiers_enabled) {
								if (ctx) {
									if (window.tierChart) window.tierChart.destroy();
									window.tierChart = new Chart(ctx, {
										type: 'pie',
										data: {
											labels: d.tier_chart.labels,
											datasets: [{
												data: d.tier_chart.data,
												backgroundColor: ['#9ca3af', '#60a5fa', '#fbbf24', '#f87171', '#c084fc']
											}]
										},
										options: {
											responsive: true,
											maintainAspectRatio: false,
											plugins: { legend: { position: 'right' } }
										}
									});
								}
							} else {
								$('#chart-customer-tiers').parent().html('<div style="text-align:center; padding: 40px 0; color:#9ca3af;">Tiers feature is currently disabled.</div>');
							}
							
							var funnelCtx = document.getElementById('marketingFunnelChart');
							if (funnelCtx) {
								if (window.funnelChart) window.funnelChart.destroy();
								window.funnelChart = new Chart(funnelCtx, {
									type: 'bar',
									data: {
										labels: d.funnel.labels,
										datasets: [{
											label: 'Users',
											data: d.funnel.data,
											backgroundColor: ['#F59322', '#818cf8', '#c7d2fe', '#fff7ed'],
											borderRadius: 4
										}]
									},
									options: {
										indexAxis: 'y',
										responsive: true,
										maintainAspectRatio: false,
										plugins: { legend: { display: false } },
										scales: { x: { display: false } }
									}
								});
							}
						}
					},
					complete: function() {
						$('#dash-marketing .o100-loading-overlay').remove();
					}
				});
			}

			function loadCustomersData(dateRange) {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'o100_dash_get_customers',
						nonce: '<?php echo wp_create_nonce("o100_dashboard_nonce"); ?>',
						date_range: dateRange
					},
					success: function(res) {
						if(res.success) {
							var d = res.data;
							
							var ctx = document.getElementById('customersNewRetChart');
							if (ctx) {
								if (window.newRetChart) window.newRetChart.destroy();
								window.newRetChart = new Chart(ctx, {
									type: 'doughnut',
									data: {
										labels: d.new_returning.labels,
										datasets: [{
											data: d.new_returning.data,
											backgroundColor: ['#10b981', '#F59322']
										}]
									},
									options: {
										responsive: true,
										maintainAspectRatio: false,
										cutout: '60%',
										plugins: { legend: { position: 'bottom' } }
									}
								});
							}
							
							var tbodyTS = $('#top-spenders-tbody');
							tbodyTS.empty();
							if (d.top_spenders && d.top_spenders.length > 0) {
								d.top_spenders.forEach(function(item) {
									tbodyTS.append('<tr><td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">' + item.name + '</td><td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">' + item.orders + '</td><td style="padding: 12px 0; text-align: right; border-bottom: 1px solid #f3f4f6; font-weight: 500;">' + item.ltv + '</td></tr>');
								});
							} else {
								tbodyTS.append('<tr><td colspan="3" style="text-align:center; padding: 20px; color:#9ca3af; font-size:13px;">No data found.</td></tr>');
							}
							
							var tbodyCR = $('#churn-risk-tbody');
							tbodyCR.empty();
							if (d.churn_risk && d.churn_risk.length > 0) {
								d.churn_risk.forEach(function(item) {
									tbodyCR.append('<tr><td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">' + item.name + '</td><td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">' + item.last + '</td><td style="padding: 12px 0; text-align: right; border-bottom: 1px solid #f3f4f6; font-weight: 500; color: #ef4444;">' + item.ltv + '</td></tr>');
								});
							} else {
								tbodyCR.append('<tr><td colspan="3" style="text-align:center; padding: 20px; color:#9ca3af; font-size:13px;">No customers at risk currently.</td></tr>');
							}
						}
					},
					complete: function() {
						$('#dash-customers .o100-loading-overlay').remove();
					}
				});
			}

			// Data loading logic
			function loadDashboardData() {
				var dateRange = $('#o100-dash-date-range').val();
				
				// Active Tab
				var activeTab = $('.o100-dash-pane.active').attr('id');

				if (activeTab === 'dash-overview') {
					$('#dash-overview').append('<div class="o100-loading-overlay"><div class="o100-spinner"></div></div>');
					loadOverviewData(dateRange);
				} else if (activeTab === 'dash-sales') {
					$('#dash-sales').append('<div class="o100-loading-overlay"><div class="o100-spinner"></div></div>');
					loadSalesData(dateRange);
				} else if (activeTab === 'dash-marketing') {
					$('#dash-marketing').append('<div class="o100-loading-overlay"><div class="o100-spinner"></div></div>');
					loadMarketingData(dateRange);
				} else if (activeTab === 'dash-customers') {
					$('#dash-customers').append('<div class="o100-loading-overlay"><div class="o100-spinner"></div></div>');
					loadCustomersData(dateRange);
				}
			}

			// On Tab Switch, load data if needed
			$('.o100-dash-tab').click(function() {
				setTimeout(loadDashboardData, 50); // slight delay to allow class switch
			});

			$('#o100-dash-date-range').change(function() {
				loadDashboardData();
			});
			
			// Initial load
			loadDashboardData();
		});
		</script>
		<?php
	}
}
