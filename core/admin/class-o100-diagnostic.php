<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

class O100_Diagnostic {

	public function __construct() {
		// Only load in admin
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_footer', array( $this, 'render_diagnostic_panel' ) );
		
		// AJAX Handlers
		add_action( 'wp_ajax_o100_run_health_check', array( $this, 'ajax_health_check' ) );
		add_action( 'wp_ajax_o100_run_shipping_sim', array( $this, 'ajax_shipping_simulator' ) );
	}

	/**
	 * AJAX: Run Health Check
	 */
	public function ajax_health_check() {
		check_ajax_referer( 'o100_diag_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$results = array();

		// 1. WooCommerce
		$wc_active = class_exists( 'WooCommerce' );
		$results[] = array(
			'label'  => __( 'WooCommerce Active', 'order100' ),
			'status' => $wc_active ? 'ok' : 'error',
			'msg'    => $wc_active ? 'v' . WC_VERSION : __( 'Not found', 'order100' ),
			'action' => $wc_active ? '' : admin_url('plugins.php')
		);

		if ( $wc_active ) {
			// Tax Check
			$calc_taxes = get_option( 'woocommerce_calc_taxes' );
			$results[] = array(
				'label'  => __( 'WooCommerce Taxes', 'order100' ),
				'status' => $calc_taxes === 'yes' ? 'ok' : 'warning',
				'msg'    => $calc_taxes === 'yes' ? __( 'Enabled', 'order100' ) : __( 'Disabled (Taxes won\'t calculate)', 'order100' ),
				'action' => $calc_taxes === 'yes' ? '' : admin_url('admin.php?page=wc-settings&tab=general')
			);

			// Payments Check
			$gateways = WC()->payment_gateways->get_available_payment_gateways();
			$gateways_active = ! empty( $gateways );
			$results[] = array(
				'label'  => __( 'Payment Gateways', 'order100' ),
				'status' => $gateways_active ? 'ok' : 'error',
				'msg'    => $gateways_active ? sprintf( __( '%d Active Gateways', 'order100' ), count($gateways) ) : __( 'No Gateways Active', 'order100' ),
				'action' => $gateways_active ? '' : admin_url('admin.php?page=wc-settings&tab=checkout')
			);
		}

		// 2. Google Maps API
		$api_opts = get_option( 'o100_api_integration', array() );
		$api_key = isset( $api_opts['o100_ggmap_api_js'] ) ? trim( $api_opts['o100_ggmap_api_js'] ) : '';
		$api_ok = ! empty( $api_key );
		$results[] = array(
			'label'  => __( 'Google Maps API', 'order100' ),
			'status' => $api_ok ? 'ok' : 'error',
			'msg'    => $api_ok ? __( 'Configured', 'order100' ) : __( 'Missing API Key', 'order100' ),
			'action' => $api_ok ? '' : admin_url('admin.php?page=o100-settings&tab=api_integration')
		);

		// 3. Timezone
		$tz = wp_timezone_string();
		$tz_ok = ! in_array( $tz, array( 'UTC', 'UTC+0', 'Etc/UTC' ) ); // We usually want a real timezone
		$results[] = array(
			'label'  => __( 'WordPress Timezone', 'order100' ),
			'status' => $tz_ok ? 'ok' : 'warning',
			'msg'    => $tz,
			'action' => $tz_ok ? '' : admin_url('options-general.php')
		);

		// 4. Permalinks
		$permalink = get_option( 'permalink_structure' );
		$pl_ok = ! empty( $permalink );
		$results[] = array(
			'label'  => __( 'Permalinks', 'order100' ),
			'status' => $pl_ok ? 'ok' : 'error',
			'msg'    => $pl_ok ? $permalink : __( 'Plain (API may fail)', 'order100' ),
			'action' => $pl_ok ? '' : admin_url('options-permalink.php')
		);

		// 5. Database Custom Tables
		$results[] = array(
			'label'  => __( 'Branches Data Structure', 'order100' ),
			'status' => post_type_exists( 'o100_location' ) ? 'ok' : 'error',
			'msg'    => post_type_exists( 'o100_location' ) ? __( 'System Ready', 'order100' ) : __( 'Missing Branch Structure', 'order100' )
		);

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: Run Shipping Simulator
	 */
	public function ajax_shipping_simulator() {
		check_ajax_referer( 'o100_diag_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$method   = isset( $_POST['method'] ) ? sanitize_text_field( $_POST['method'] ) : 'delivery';
		$distance = isset( $_POST['distance'] ) ? floatval( $_POST['distance'] ) : 0;
		$subtotal = isset( $_POST['subtotal'] ) ? floatval( $_POST['subtotal'] ) : 0;
		$loc_id   = isset( $_POST['location'] ) ? intval( $_POST['location'] ) : 0;

		// Mock the session explicitly for the duration of this request
		if ( ! WC()->session ) {
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
		}
		
		WC()->session->set( '_o100_order_method', $method );
		WC()->session->set( '_user_order_method', $method === 'pickup' ? 'takeaway' : $method );
		WC()->session->set( 'o100_location_id', $loc_id );
		WC()->session->set( '_user_distance', $distance );

		// We need to mock the cart subtotal. Since we can't easily mock WC()->cart items without adding real items,
		// we will instantiate O100_Shipping and use reflection or override methods for simulation,
		// OR we can just read the raw options and calculate them ourselves here to show the breakdown.
		
		$shipping = new O100_Shipping();
		
		// 1. Get Effective Minimum
		$reflection = new ReflectionClass( $shipping );
		$get_min = $reflection->getMethod( 'get_effective_minimum' );
		$get_min->setAccessible( true );
		$min_order = $get_min->invoke( $shipping );
		
		// 2. Base Delivery Fee
		$get_opts = $reflection->getMethod( 'get_delivery_opts' );
		$get_opts->setAccessible( true );
		$opts = $get_opts->invoke( $shipping );

		$base_fee = 0;
		if ( $method === 'delivery' ) {
			$base_fee = isset( $opts['o100_deli_fee'] ) ? floatval( $opts['o100_deli_fee'] ) : 0;
			
			// Tiered Fee override
			$zone_method = isset( $opts['o100_limit_shp'] ) ? $opts['o100_limit_shp'] : 'radius';
			if ( $zone_method !== 'postcode' ) {
				$get_tiered = $reflection->getMethod( 'get_tiered_distance_fee' );
				$get_tiered->setAccessible( true );
				$tiered = $get_tiered->invoke( $shipping, $opts );
				if ( $tiered !== null && isset( $tiered['fee'] ) ) {
					$base_fee = floatval( $tiered['fee'] );
				}
			}
		}

		// 3. Distance Check
		$is_blocked = false;
		$block_reason = '';
		if ( $method === 'delivery' ) {
			$max_allowed = '';
			if ( $loc_id ) {
				$loc_max = get_post_meta( $loc_id, 'o100_distance_restrict', true );
				if ( $loc_max !== '' ) $max_allowed = floatval( $loc_max );
			}
			if ( $max_allowed === '' && isset( $opts['o100_deli_dis'] ) && $opts['o100_deli_dis'] !== '' ) {
				$max_allowed = floatval( $opts['o100_deli_dis'] );
			}
			
			if ( $max_allowed !== '' && $distance > $max_allowed ) {
				$is_blocked = true;
				$block_reason = sprintf( __( 'Distance (%.2f km) exceeds maximum limit of %.2f km.', 'order100' ), $distance, $max_allowed );
			}
		}

		// Minimum Check
		if ( ! $is_blocked && $min_order > 0 && $subtotal < $min_order ) {
			$is_blocked = true;
			$block_reason = sprintf( __( 'Subtotal ($%.2f) does not meet minimum order requirement ($%.2f).', 'order100' ), $subtotal, $min_order );
		}

		// 4. Surcharge / Discount
		$surcharge_amount = 0;
		$surcharge_label = '';
		if ( $loc_id ) {
			$fee_action = get_post_meta( $loc_id, 'o100_fee_action', true );
			$fee_val = floatval( get_post_meta( $loc_id, 'o100_fee_val', true ) );
			$fee_type = get_post_meta( $loc_id, 'o100_fee_type', true );
			if ( ! $fee_type ) $fee_type = 'percent';

			if ( $fee_action !== '' && $fee_val > 0 ) {
				if ( $fee_type === 'fixed' ) {
					$surcharge_amount = $fee_val;
				} else {
					$surcharge_amount = ( $fee_val / 100 ) * $subtotal;
				}
				if ( $fee_action === 'discount' ) {
					$surcharge_amount = -$surcharge_amount;
					$surcharge_label = 'Branch Discount';
				} else {
					$surcharge_label = 'Processing Fee (Branch)';
				}
			}
		}

		$report = array();
		if ( $is_blocked ) {
			$report[] = array( 'label' => 'Status', 'val' => '<span style="color:#e11d48;font-weight:bold;">Blocked</span>' );
			$report[] = array( 'label' => 'Reason', 'val' => '<span style="color:#e11d48;">' . esc_html( $block_reason ) . '</span>' );
		} else {
			$report[] = array( 'label' => 'Status', 'val' => '<span style="color:#10b981;font-weight:bold;">Allowed</span>' );
			$report[] = array( 'label' => 'Subtotal', 'val' => wc_price( $subtotal ) );
			
			if ( $method === 'delivery' ) {
				$report[] = array( 'label' => 'Base Delivery Fee', 'val' => wc_price( $base_fee ) );
			}
			
			if ( $surcharge_amount !== 0 ) {
				$color = $surcharge_amount < 0 ? '#10b981' : '#f59e0b';
				$report[] = array( 'label' => $surcharge_label, 'val' => '<span style="color:'.$color.'">' . wc_price( $surcharge_amount ) . '</span>' );
			}
			
			$final = $subtotal + $base_fee + $surcharge_amount;
			$report[] = array( 'label' => '<strong>Simulated Total</strong>', 'val' => '<strong>' . wc_price( $final ) . '</strong>' );
		}

		// Also return raw data for dev inspection
		$debug = array(
			'method' => $method,
			'distance' => $distance,
			'subtotal' => $subtotal,
			'loc_id' => $loc_id,
			'min_order' => $min_order
		);

		wp_send_json_success( array(
			'html' => $this->build_report_html( $report ),
			'debug' => $debug
		) );
	}

	private function build_report_html( $report ) {
		$html = '<table style="width:100%; border-collapse:collapse; margin-top:10px;">';
		foreach ( $report as $r ) {
			$html .= '<tr>';
			$html .= '<td style="padding:8px 0; border-bottom:1px solid #f1f5f9; color:#64748b;">' . $r['label'] . '</td>';
			$html .= '<td style="padding:8px 0; border-bottom:1px solid #f1f5f9; text-align:right; color:#0f172a;">' . $r['val'] . '</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';
		return $html;
	}

	/**
	 * Render the panel HTML
	 */
	public function render_diagnostic_panel() {
		// Only render if we are on order100 settings pages
		if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'o100' ) === false ) {
			return;
		}

		$branches = array();
		$args = array(
			'post_type' => 'o100_location',
			'posts_per_page' => -1,
			'post_status' => 'publish'
		);
		$q = new WP_Query($args);
		if ( $q->have_posts() ) {
			while( $q->have_posts() ) {
				$q->the_post();
				$branches[] = array(
					'id' => get_the_ID(),
					'title' => get_the_title(),
					'latlng' => get_post_meta( get_the_ID(), 'o100_latlng', true )
				);
			}
			wp_reset_postdata();
		}
		?>
		<script>
			window.o100_diag_branches = <?php echo wp_json_encode( $branches ); ?>;
		</script>
		<style>
			/* Diagnostic Panel Styles */
			#o100-diag-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.4); backdrop-filter: blur(2px); z-index: 999998; opacity: 0; visibility: hidden; transition: all 0.3s; }
			#o100-diag-overlay.active { opacity: 1; visibility: visible; }
			
			#o100-diag-panel { position: fixed; top: 0; right: -420px; width: 400px; height: 100%; background: #fff; z-index: 999999; box-shadow: -4px 0 24px rgba(0,0,0,0.1); transition: right 0.3s cubic-bezier(0.16,1,0.3,1); display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
			#o100-diag-panel.open { right: 0; }
			
			.o100-diag-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
			.o100-diag-header h2 { margin: 0; font-size: 18px; color: #0f172a; display: flex; align-items: center; gap: 8px; }
			.o100-diag-close { background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer; line-height: 1; padding: 0; }
			.o100-diag-close:hover { color: #0f172a; }
			
			.o100-diag-tabs { display: flex; padding: 0 24px; border-bottom: 1px solid #e2e8f0; background: #fff; }
			.o100-diag-tab-btn { flex: 1; padding: 14px 0; background: none; border: none; font-size: 14px; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; }
			.o100-diag-tab-btn.active { color: #e11d48; border-bottom-color: #e11d48; }
			
			.o100-diag-body { flex: 1; overflow-y: auto; background: #fff; padding: 24px; }
			.o100-diag-tab-content { display: none; }
			.o100-diag-tab-content.active { display: block; }
			
			/* Form Controls */
			.o100-diag-form-group { margin-bottom: 16px; }
			.o100-diag-form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
			.o100-diag-form-control { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; color: #0f172a; box-sizing: border-box; }
			.o100-diag-form-control:focus { border-color: #e11d48; outline: none; box-shadow: 0 0 0 3px rgba(225,29,72,0.1); }
			.o100-diag-btn { display: block; width: 100%; padding: 12px; background: #0f172a; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
			.o100-diag-btn:hover { background: #1e293b; }
			
			/* Health Check List */
			.o100-diag-list { list-style: none; padding: 0; margin: 0; }
			.o100-diag-list li { padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; }
			.o100-diag-list li.ok { border-left: 4px solid #10b981; }
			.o100-diag-list li.error { border-left: 4px solid #e11d48; background: #fff1f2; }
			.o100-diag-list li.warning { border-left: 4px solid #f59e0b; }
			.o100-diag-item-label { font-size: 14px; font-weight: 600; color: #0f172a; }
			.o100-diag-item-msg { font-size: 13px; color: #64748b; }
			
			.o100-diag-loader { border: 2px solid #f3f3f3; border-top: 2px solid #e11d48; border-radius: 50%; width: 20px; height: 20px; animation: spin 1s linear infinite; margin: 0 auto; }
			@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
		</style>
		
		<div id="o100-diag-overlay"></div>
		<div id="o100-diag-panel">
			<div class="o100-diag-header">
				<h2><span class="dashicons dashicons-admin-tools"></span> Diagnostic Center</h2>
				<button type="button" class="o100-diag-close">&times;</button>
			</div>
			
			<div class="o100-diag-tabs">
				<button type="button" class="o100-diag-tab-btn active" data-target="tab-health">Health Check</button>
				<button type="button" class="o100-diag-tab-btn" data-target="tab-sim">Delivery Sim</button>
			</div>
			
			<div class="o100-diag-body">
				<!-- Health Check Tab -->
				<div id="tab-health" class="o100-diag-tab-content active">
					<p style="font-size:13px; color:#64748b; margin-top:0;">Run a system health check to ensure all dependencies and environment variables are configured correctly.</p>
					<button type="button" id="o100-btn-run-health" class="o100-diag-btn" style="margin-bottom: 20px;">Run Diagnosis</button>
					<div id="o100-health-results"></div>
				</div>
				
				<!-- Shipping Sim Tab -->
				<div id="tab-sim" class="o100-diag-tab-content">
					<p style="font-size:13px; color:#64748b; margin-top:0;">Simulate checkout conditions to verify shipping fees and restrictions without using the frontend.</p>
					
					<div class="o100-diag-form-group">
						<label>Order Method</label>
						<select id="o100-sim-method" class="o100-diag-form-control">
							<option value="delivery">Delivery</option>
							<option value="pickup">Pickup</option>
						</select>
					</div>
					
					<div class="o100-diag-form-group">
						<label>Selected Branch</label>
						<select id="o100-sim-location" class="o100-diag-form-control">
							<option value="0">Global (No specific branch)</option>
							<?php foreach($branches as $b): ?>
								<option value="<?php echo esc_attr($b['id']); ?>"><?php echo esc_html($b['title']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					
					<div class="o100-diag-form-group" style="position:relative; margin-bottom: 8px;">
						<label>Real Address</label>
						<input type="text" id="o100-sim-address" class="o100-diag-form-control" placeholder="Start typing address...">
						<span class="dashicons dashicons-location-alt" style="position:absolute; right:10px; top:32px; color:#9ca3af;"></span>
					</div>
					
					<div style="text-align:center; font-size:12px; font-weight:bold; color:#cbd5e1; margin:8px 0;">— OR —</div>
					
					<div class="o100-diag-form-group">
						<label>Distance (km)</label>
						<input type="number" id="o100-sim-distance" class="o100-diag-form-control" step="0.1" value="5.5">
					</div>
					
					<div class="o100-diag-form-group">
						<label>Cart Subtotal ($)</label>
						<input type="number" id="o100-sim-subtotal" class="o100-diag-form-control" step="0.01" value="45.00">
					</div>
					
					<button type="button" id="o100-btn-run-sim" class="o100-diag-btn" style="margin-top: 24px; background:#e11d48;">Simulate Checkout</button>
					
					<div id="o100-sim-results" style="margin-top: 20px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; display:none;">
						<!-- Results injected here -->
					</div>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var $panel = $('#o100-diag-panel');
			var $overlay = $('#o100-diag-overlay');
			
			// Open / Close
			$('#o100-btn-diagnostic').on('click', function(e) {
				e.preventDefault();
				$panel.addClass('open');
				$overlay.addClass('active');
			});
			
			$('.o100-diag-close, #o100-diag-overlay').on('click', function() {
				$panel.removeClass('open');
				$overlay.removeClass('active');
			});
			
			// Tabs
			$('.o100-diag-tab-btn').on('click', function() {
				$('.o100-diag-tab-btn').removeClass('active');
				$(this).addClass('active');
				$('.o100-diag-tab-content').removeClass('active');
				$('#' + $(this).data('target')).addClass('active');
			});
			
			// Run Health Check
			$('#o100-btn-run-health').on('click', function() {
				var $btn = $(this);
				var $res = $('#o100-health-results');
				
				$btn.text('Running...').prop('disabled', true);
				$res.html('<div class="o100-diag-loader"></div>');
				
				$.post(ajaxurl, {
					action: 'o100_run_health_check',
					nonce: '<?php echo wp_create_nonce("o100_diag_nonce"); ?>'
				}, function(res) {
					$btn.text('Run Diagnosis').prop('disabled', false);
					if (res.success) {
						var html = '<ul class="o100-diag-list">';
						$.each(res.data, function(i, item) {
							var icon = item.status === 'ok' ? 'dashicons-yes-alt' : (item.status === 'warning' ? 'dashicons-warning' : 'dashicons-dismiss');
							var color = item.status === 'ok' ? '#10b981' : (item.status === 'warning' ? '#f59e0b' : '#e11d48');
							var actionHtml = (item.action && item.status !== 'ok') ? '<a href="'+item.action+'" style="font-size:12px; color:#3b82f6; text-decoration:none; display:block; margin-top:4px;">Fix Issue &rarr;</a>' : '';
							html += '<li class="'+item.status+'">';
							html += '<div><div class="o100-diag-item-label">'+item.label+'</div><div class="o100-diag-item-msg">'+item.msg+'</div>'+actionHtml+'</div>';
							html += '<span class="dashicons '+icon+'" style="color:'+color+';"></span>';
							html += '</li>';
						});
						html += '</ul>';
						$res.html(html);
					} else {
						$res.html('<p style="color:red;">Error running check.</p>');
					}
				});
			});
			
			// Run Simulator
			$('#o100-btn-run-sim').on('click', function() {
				var $btn = $(this);
				var $res = $('#o100-sim-results');
				
				$btn.text('Simulating...').prop('disabled', true);
				$res.hide().html('<div class="o100-diag-loader"></div>').fadeIn(200);
				
				$.post(ajaxurl, {
					action: 'o100_run_shipping_sim',
					method: $('#o100-sim-method').val(),
					location: $('#o100-sim-location').val(),
					distance: $('#o100-sim-distance').val(),
					subtotal: $('#o100-sim-subtotal').val(),
					nonce: '<?php echo wp_create_nonce("o100_diag_nonce"); ?>'
				}, function(res) {
					$btn.text('Simulate Checkout').prop('disabled', false);
					if (res.success) {
						$res.html(res.data.html);
						console.log("Sim Debug:", res.data.debug);
					} else {
						$res.html('<p style="color:red;">Simulation failed.</p>');
					}
				});
			});

			// Google Maps Logic
			var distanceCalc = function(lat1, lon1, lat2, lon2) {
				var p = 0.017453292519943295;
				var c = Math.cos;
				var a = 0.5 - c((lat2 - lat1) * p)/2 + c(lat1 * p) * c(lat2 * p) * (1 - c((lon2 - lon1) * p))/2;
				return 12742 * Math.asin(Math.sqrt(a));
			};

			var updateDistance = function() {
				if (!window.o100DiagUserLatLng) return;
				
				var locId = $('#o100-sim-location').val();
				if (!locId || locId == '0') return;

				var branch = window.o100_diag_branches.find(function(b) { return b.id == locId; });
				if (branch && branch.latlng) {
					var coords = branch.latlng.split(',');
					if (coords.length === 2) {
						var dist = distanceCalc(
							window.o100DiagUserLatLng.lat, 
							window.o100DiagUserLatLng.lng, 
							parseFloat(coords[0]), 
							parseFloat(coords[1])
						);
						$('#o100-sim-distance').val(dist.toFixed(2));
						$('#o100-sim-distance').css({backgroundColor: '#ecfdf5', transition: 'background 0.5s'});
						setTimeout(function(){ $('#o100-sim-distance').css({backgroundColor: '#fff'}); }, 1000);
					}
				}
			};

			$('#o100-sim-location').on('change', updateDistance);

			// Init Autocomplete
			if (typeof google !== 'undefined' && google.maps && google.maps.places) {
				var input = document.getElementById('o100-sim-address');
				if (input) {
					var autocomplete = new google.maps.places.Autocomplete(input, { types: ['geocode'] });
					autocomplete.addListener('place_changed', function() {
						var place = autocomplete.getPlace();
						if (!place.geometry) {
							return;
						}
						window.o100DiagUserLatLng = {
							lat: place.geometry.location.lat(),
							lng: place.geometry.location.lng()
						};
						updateDistance();
					});
				}
			}
		});
		</script>
		<?php
	}
}

