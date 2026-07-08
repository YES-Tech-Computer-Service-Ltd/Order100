<?php
/**
 * Handles App Pairing (QR Code generation & FCM Token syncing)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_App_Pairing {

	public function __construct() {
		// AJAX endpoint to generate WC API Keys and return payload
		add_action( 'wp_ajax_o100_generate_app_pairing', array( $this, 'generate_pairing_payload' ) );
		
		// Order100 REST APIs for the App
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}


	public static function render_app_devices_settings_ui() {
		$tokens = get_option( 'o100_fcm_tokens', array() );
		?>
		<div style="margin-bottom: 20px;">
			<button type="button" class="button button-primary o100-btn-primary" id="o100_generate_app_qr">
				<span class="dashicons dashicons-smartphone" style="margin-top:4px;"></span> 
				<?php esc_html_e('Generate Global QR Code', 'order100'); ?>
			</button>
			<p class="description"><?php esc_html_e('Scan this code using the Order100 app to securely connect your device. This QR code grants full access and will automatically expire in 5 minutes.', 'order100'); ?></p>
			
			<div id="o100_qr_modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); z-index:99999; justify-content:center; align-items:center;">
				<div style="background:#fff; padding:30px; border-radius:12px; text-align:center; min-width:300px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
					<h3 style="margin-top:0; color:#1e293b; font-size:18px;"><?php esc_html_e('Scan or Enter Code to Connect', 'order100'); ?></h3>
					<div id="o100_qr_container" style="margin: 20px auto; width: 200px; height: 200px;"></div>
					<div style="font-size: 24px; font-weight: bold; letter-spacing: 4px; margin-bottom: 10px; color: #334155;" id="o100_pairing_code_display"></div>
					<div id="o100_qr_timer" style="color:#ef4444; font-weight:600; margin-bottom:15px;"></div>
					<button type="button" class="button button-secondary" onclick="document.getElementById('o100_qr_modal').style.display='none'; clearInterval(window.o100QrTimer);">
						<?php esc_html_e('Close', 'order100'); ?>
					</button>
				</div>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th><?php esc_html_e('Device Name', 'order100'); ?></th>
					<th><?php esc_html_e('Assigned Branch', 'order100'); ?></th>
					<th><?php esc_html_e('Last Active', 'order100'); ?></th>
					<th><?php esc_html_e('App Version', 'order100'); ?></th>
					<th><?php esc_html_e('Actions', 'order100'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $tokens ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e('No devices connected yet.', 'order100'); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $tokens as $device_id => $data ) : ?>
						<tr>
							<td><strong><?php echo esc_html( isset($data['device_name']) ? $data['device_name'] : 'Unknown Device' ); ?></strong><br><small style="color:#64748b;"><?php echo esc_html( substr($device_id, 0, 12) . '...' ); ?></small></td>
							<td>
								<?php 
									$branch_id = isset($data['branch_id']) ? $data['branch_id'] : '';
									if ( empty($branch_id) ) {
										echo '<span style="color:#64748b; font-style:italic;">' . esc_html__('Global (All Branches)', 'order100') . '</span>';
									} else {
										// If there's a custom post type for branches, we would fetch its title here.
										echo '<strong>' . esc_html( $branch_id ) . '</strong>';
									}
								?>
							</td>
							<td><?php echo esc_html( human_time_diff( $data['last_sync'], time() ) . ' ' . __('ago', 'order100') ); ?></td>
							<td><?php echo esc_html( isset($data['version']) ? $data['version'] : 'N/A' ); ?></td>
							<td>
								<button type="button" class="button button-small o100-remove-device" data-id="<?php echo esc_attr( $device_id ); ?>"><?php esc_html_e('Revoke Access', 'order100'); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<script>
		jQuery(document).ready(function($) {
			$('#o100_generate_app_qr').on('click', function() {
				var $btn = $(this);
				$btn.addClass('updating-message').prop('disabled', true);

				$.post(ajaxurl, {
					action: 'o100_generate_app_pairing',
					nonce: '<?php echo wp_create_nonce("o100_admin_nonce"); ?>'
				}, function(res) {
					$btn.removeClass('updating-message').prop('disabled', false);
					if (res.success && res.data.qr_data) {
						var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(res.data.qr_data);
						$('#o100_qr_container').empty().html('<img src="' + qrUrl + '" width="200" height="200" style="border-radius:8px;" />');
						$('#o100_pairing_code_display').text(res.data.pairing_code);
						
						$('#o100_qr_modal').css('display', 'flex');
						
						// Start 5-min timer
						var seconds = 300;
						if (window.o100QrTimer) clearInterval(window.o100QrTimer);
						window.o100QrTimer = setInterval(function() {
							seconds--;
							var m = Math.floor(seconds / 60);
							var s = seconds % 60;
							$('#o100_qr_timer').text( m + ':' + (s < 10 ? '0'+s : s) + ' remaining');
							if (seconds <= 0) {
								clearInterval(window.o100QrTimer);
								$('#o100_qr_modal').hide();
							}
						}, 1000);
					} else {
						alert('Failed to generate QR code.');
					}
				}).fail(function() {
					$btn.removeClass('updating-message').prop('disabled', false);
					alert('Failed to generate QR code. Server error.');
				});
			});
			
			$('.o100-remove-device').on('click', function() {
				if (!confirm('Are you sure you want to revoke access for this device? It will be disconnected immediately.')) return;
				
				var device_id = $(this).data('id');
				var $row = $(this).closest('tr');
				
				$.ajax({
					url: '/wp-json/order100/v1/sync-token?device_id=' + device_id,
					type: 'DELETE',
					success: function(res) {
						$row.fadeOut(function() { $(this).remove(); });
					}
				});
			});
		});
		</script>
		<?php
	}

	public function generate_pairing_payload() {
		check_ajax_referer( 'o100_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		// Generate 6-digit random code
		$pairing_code = sprintf('%06d', mt_rand(0, 999999));
		
		// Save to transient for 5 minutes (300 seconds)
		set_transient( 'o100_pairing_code_' . $pairing_code, array(
			'user_id' => get_current_user_id(),
			'time'    => time()
		), 300 );

		$payload = array(
			'u'    => site_url(),
			'code' => $pairing_code
		);

		// Return JSON payload encoded in Base64 so JS can pass it to QR generator safely
		wp_send_json_success( array(
			'qr_data'      => base64_encode( json_encode( $payload ) ),
			'pairing_code' => $pairing_code
		) );
	}

	public function register_rest_routes() {
		// POST /wp-json/order100/v1/app/pair
		register_rest_route( 'order100/v1', '/app/pair', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'app_pair' ),
			'permission_callback' => '__return_true'
		) );

		// POST /wp-json/order100/v1/sync-token
		
		// POST /wp-json/order100/v1/device-heartbeat
		register_rest_route( 'order100/v1', '/device-heartbeat', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'device_heartbeat' ),
			'permission_callback' => '__return_true'
		) );
		
		// DELETE /wp-json/order100/v1/sync-token?device_id=...
		register_rest_route( 'order100/v1', '/sync-token', array(
			'methods'             => 'DELETE',
			'callback'            => array( $this, 'delete_fcm_token' ),
			'permission_callback' => '__return_true'
		) );
	}

	public function app_pair( WP_REST_Request $request ) {
		$code = sanitize_text_field( $request->get_param( 'code' ) );
		$device_id = sanitize_text_field( $request->get_param( 'device_id' ) );
		$device_name = sanitize_text_field( $request->get_param( 'device_name' ) );
		
		if ( empty( $code ) || empty( $device_id ) ) {
			return new WP_Error( 'missing_params', 'Missing pairing code or device_id', array( 'status' => 400 ) );
		}

		$transient_key = 'o100_pairing_code_' . $code;
		$pairing_data = get_transient( $transient_key );

		if ( ! $pairing_data ) {
			return new WP_Error( 'invalid_code', 'Invalid or expired pairing code', array( 'status' => 403 ) );
		}

		// Valid code, generate a long-lived device token
		delete_transient( $transient_key );

		$device_token = wp_generate_password( 48, false );
		$hashed_token = wp_hash_password( $device_token );

		// We store devices in an option or custom table. Using o100_devices option for now.
		$devices = get_option( 'o100_devices', array() );
		$devices[ $device_id ] = array(
			'token_hash'  => $hashed_token,
			'device_name' => empty($device_name) ? 'Unknown Device' : $device_name,
			'paired_at'   => time(),
			'last_seen'   => time(),
			'user_id'     => $pairing_data['user_id']
		);
		update_option( 'o100_devices', $devices, false );

		return rest_ensure_response( array(
			'success'      => true,
			'device_token' => $device_token,
			'site_name'    => get_bloginfo( 'name' ),
			'message'      => 'Device paired successfully'
		) );
	}

	public function sync_fcm_token( WP_REST_Request $request ) {
		$fcm_token   = $request->get_param( 'fcm_token' );
		$device_id   = $request->get_param( 'device_id' );
		$app_version = $request->get_param( 'app_version' );
		$device_name = $request->get_param( 'device_name' );
		$branch_id   = $request->get_param( 'branch_id' );

		if ( empty( $fcm_token ) || empty( $device_id ) ) {
			return new WP_Error( 'missing_params', 'Missing required parameters.', array( 'status' => 400 ) );
		}

		$tokens = get_option( 'o100_fcm_tokens', array() );
		
		// Retain existing name/branch if not provided in the update
		if ( isset( $tokens[ $device_id ] ) ) {
			if ( empty( $device_name ) && isset( $tokens[ $device_id ]['device_name'] ) ) {
				$device_name = $tokens[ $device_id ]['device_name'];
			}
			if ( empty( $branch_id ) && isset( $tokens[ $device_id ]['branch_id'] ) ) {
				$branch_id = $tokens[ $device_id ]['branch_id'];
			}
		}

		$tokens[ $device_id ] = array(
			'token'       => $fcm_token,
			'version'     => $app_version,
			'device_name' => empty($device_name) ? 'Unknown Device' : sanitize_text_field( $device_name ),
			'branch_id'   => sanitize_text_field( $branch_id ),
			'last_sync'   => time()
		);

		update_option( 'o100_fcm_tokens', $tokens, false );

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Token synced successfully'
		) );
	}

	public function device_heartbeat( WP_REST_Request $request ) {
		$device_id = $request->get_param( 'device_id' );
		
		if ( empty( $device_id ) ) {
			return new WP_Error( 'missing_params', 'Missing device_id', array( 'status' => 400 ) );
		}

		$devices = get_option( 'o100_devices', array() );
		
		if ( ! isset( $devices[ $device_id ] ) ) {
			$devices[ $device_id ] = array();
		}
		
		$devices[ $device_id ]['last_seen'] = time();
		$devices[ $device_id ]['app_version'] = $request->get_param( 'app_version' );
		
		update_option( 'o100_devices', $devices, false );
		
		return rest_ensure_response( array(
			'success' => true,
			'server_time' => gmdate('Y-m-d\TH:i:s\Z')
		) );
	}
	
	public function delete_fcm_token( WP_REST_Request $request ) {
		$device_id = $request->get_param( 'device_id' );
		
		if ( empty( $device_id ) ) {
			return new WP_Error( 'missing_params', 'Missing device_id', array( 'status' => 400 ) );
		}
		
		$tokens = get_option( 'o100_fcm_tokens', array() );
		if ( isset( $tokens[ $device_id ] ) ) {
			unset( $tokens[ $device_id ] );
			update_option( 'o100_fcm_tokens', $tokens, false );
		}
		
		return rest_ensure_response( array(
			'success' => true,
			'action'  => 'removed'
		) );
	}
}

new O100_App_Pairing();
