<?php
/**
 * Dashboard view for Loyalty & Referral
 *
 * @package Order100
 * @since   3.2.0
 */
defined( 'ABSPATH' ) || exit;

// We will fetch stats and charts via AJAX to match the original React SPA and avoid blocking the PHP render.
?>
<div class="o100-loyalty-dashboard o100-loyalty-dashboard-full">

	<!-- Filters -->
	<div class="o100-loyalty-dashboard-filters" style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 20px; gap: 10px;">
		<span style="font-weight:600; text-transform:uppercase; margin-right: auto;"><?php esc_html_e( 'Dashboard', 'order100' ); ?></span>
		
		<select id="o100-loyalty-date-filter" class="o100-dr-sort-select">
			<option value="90_days"><?php esc_html_e( 'Last 90 Days', 'order100' ); ?></option>
			<option value="this_month" selected><?php esc_html_e( 'This Month', 'order100' ); ?></option>
			<option value="last_month"><?php esc_html_e( 'Last Month', 'order100' ); ?></option>
			<option value="last_year"><?php esc_html_e( 'Last Year', 'order100' ); ?></option>
			<option value="custom"><?php esc_html_e( 'Custom', 'order100' ); ?></option>
		</select>

		<!-- Custom Date Popup -->
		<div id="o100-loyalty-custom-date-popup" style="display:none; position:absolute; top:45px; right:100px; background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); z-index:50;">
			<div style="display:flex; gap:15px; margin-bottom:15px;">
				<div>
					<label style="display:block; font-size:13px; color:#374151; margin-bottom:5px; font-weight:500;"><?php esc_html_e( 'From Date', 'order100' ); ?></label>
					<input type="date" id="o100-loyalty-date-from" class="o100-dr-input" style="width:140px;">
				</div>
				<div>
					<label style="display:block; font-size:13px; color:#374151; margin-bottom:5px; font-weight:500;"><?php esc_html_e( 'To Date', 'order100' ); ?></label>
					<input type="date" id="o100-loyalty-date-to" class="o100-dr-input" style="width:140px;">
				</div>
			</div>
			<div style="text-align:right;">
				<button type="button" id="o100-loyalty-custom-date-btn" class="button button-primary"><?php esc_html_e( 'Get Result', 'order100' ); ?></button>
			</div>
		</div>

		<select id="o100-loyalty-currency-filter" class="o100-dr-sort-select">
			<option value="<?php echo esc_attr( get_woocommerce_currency() ); ?>" selected>
				<?php echo esc_html( get_woocommerce_currency() ); ?>
			</option>
		</select>
	</div>

	<!-- Stats Grid -->
	<div class="o100-loyalty-stats-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 24px;">
		
		<div class="o100-loyalty-stat-card" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px; text-align:left;">
			<h4 style="font-size:14px; color:#111827; margin:0 0 10px 0; font-weight:600;" id="stat-points-val">0</h4>
			<span style="font-size:12px; color:#6b7280;"><?php esc_html_e('Total points', 'order100'); ?></span>
		</div>
		
		<div class="o100-loyalty-stat-card" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px; text-align:left;">
			<h4 style="font-size:14px; color:#111827; margin:0 0 10px 0; font-weight:600;" id="stat-coupons-val">0</h4>
			<span style="font-size:12px; color:#6b7280;"><?php esc_html_e('Total Coupons', 'order100'); ?></span>
		</div>
		
		<div class="o100-loyalty-stat-card" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px; text-align:left;">
			<h4 style="font-size:14px; color:#111827; margin:0 0 10px 0; font-weight:600;" id="stat-redeemed-val">$0.00</h4>
			<span style="font-size:12px; color:#6b7280;"><?php esc_html_e('Total value Redeemed', 'order100'); ?></span>
		</div>
		
		<div class="o100-loyalty-stat-card" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px; text-align:left;">
			<h4 style="font-size:14px; color:#111827; margin:0 0 10px 0; font-weight:600;" id="stat-orders-val">0</h4>
			<span style="font-size:12px; color:#6b7280;"><?php esc_html_e('Number Of Orders', 'order100'); ?></span>
		</div>
		
		<div class="o100-loyalty-stat-card" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px; text-align:left;">
			<h4 style="font-size:14px; color:#111827; margin:0 0 10px 0; font-weight:600;" id="stat-orders-value-val">$0.00</h4>
			<span style="font-size:12px; color:#6b7280;"><?php esc_html_e('Total value of Orders', 'order100'); ?></span>
		</div>

	</div>

	<!-- Charts Area -->
	<div class="o100-loyalty-charts-area" style="margin-bottom: 24px;">
		<!-- Revenue Chart (Full Width) -->
		<div class="o100-loyalty-chart-box" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px; margin-bottom:20px;">
			<h3 style="margin: 0 0 20px 0; font-size: 16px; color: #111827; font-weight: 600;"><?php esc_html_e( 'Revenue', 'order100' ); ?></h3>
			<div style="position: relative; height: 300px; width: 100%;">
				<canvas id="o100-loyalty-revenue-chart"></canvas>
			</div>
		</div>

		<!-- Points & Rewards Charts (Half Width) -->
		<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
			<div class="o100-loyalty-chart-box" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px;">
				<h3 style="margin: 0 0 20px 0; font-size: 16px; color: #111827; font-weight: 600;"><?php esc_html_e( 'Points', 'order100' ); ?></h3>
				<div style="position: relative; height: 250px; width: 100%;">
					<canvas id="o100-loyalty-points-chart"></canvas>
				</div>
			</div>
			
			<div class="o100-loyalty-chart-box" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px;">
				<h3 style="margin: 0 0 20px 0; font-size: 16px; color: #111827; font-weight: 600;"><?php esc_html_e( 'Coupons', 'order100' ); ?></h3>
				<div style="position: relative; height: 250px; width: 100%;">
					<canvas id="o100-loyalty-coupons-chart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- Recent Activities -->
	<div class="o100-loyalty-recent-activity" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px;">
		<h3 style="margin: 0 0 20px 0; font-size: 16px; color: #111827; font-weight: 600;"><?php esc_html_e( 'Recent Activities', 'order100' ); ?></h3>
		
		<div id="o100-loyalty-activities-timeline" style="position:relative; padding-left: 30px;">
			<!-- Timeline items injected via JS -->
			<div class="o100-loading-state"><?php esc_html_e( 'Loading activities...', 'order100' ); ?></div>
		</div>
		
	</div>

</div>


// TS: 20260127175320

// TS: 20260413163718

// TS: 20260424233105
