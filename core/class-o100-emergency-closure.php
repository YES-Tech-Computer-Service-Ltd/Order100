<?php
/**
 * Emergency Store Closure System
 * Provides Admin Bar toggle, auto-resume functionality, and frontend banners.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Emergency_Closure {

	public function __construct() {
		$this->init();
	}

	public function init() {
		$hours_opts = get_option( 'o100_store_hours', array() );
		$admin_bar_enabled = ! isset( $hours_opts['o100_ec_enable_admin_bar'] ) || $hours_opts['o100_ec_enable_admin_bar'] === 'on';

		if ( $admin_bar_enabled ) {
			add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_node' ), 999 );
			add_action( 'admin_footer', array( $this, 'inject_admin_modal' ) );
			add_action( 'wp_footer', array( $this, 'inject_admin_modal' ) );
		}

		add_action( 'wp_ajax_o100_save_emergency_status', array( $this, 'ajax_save_status' ) );
		add_action( 'wp_footer', array( $this, 'inject_frontend_banner' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'block_checkout_if_closed' ) );
	}

	/**
	 * Check if emergency closure is currently active.
	 * Supports both immediate and scheduled closures.
	 *
	 * @return array|false  False if open; array {reason, expires_at, starts_at} if closed.
	 */
	public static function get_active_closure_data() {
		$options = get_option( 'o100_options', array() );

		$is_set = isset( $options['o100_emergency_closure'] ) && $options['o100_emergency_closure'] === 'on';
		if ( ! $is_set ) {
			return false;
		}

		$now        = time();
		$starts_at  = isset( $options['o100_emergency_closure_starts'] ) ? intval( $options['o100_emergency_closure_starts'] ) : 0;
		$expires_at = isset( $options['o100_emergency_closure_expires'] ) ? intval( $options['o100_emergency_closure_expires'] ) : 0;

		// Scheduled closure: not yet started
		if ( $starts_at > 0 && $now < $starts_at ) {
			return false;
		}

		// Already expired → auto-resume
		if ( $expires_at > 0 && $now > $expires_at ) {
			return false;
		}

		return array(
			'reason'     => isset( $options['o100_emergency_closure_reason'] ) ? $options['o100_emergency_closure_reason'] : '',
			'expires_at' => $expires_at,
			'starts_at'  => $starts_at,
		);
	}

	/**
	 * Get pending scheduled closure data (set but not yet started).
	 */
	public static function get_pending_closure_data() {
		$options = get_option( 'o100_options', array() );
		$is_set = isset( $options['o100_emergency_closure'] ) && $options['o100_emergency_closure'] === 'on';
		if ( ! $is_set ) return false;

		$starts_at = isset( $options['o100_emergency_closure_starts'] ) ? intval( $options['o100_emergency_closure_starts'] ) : 0;
		if ( $starts_at <= 0 || time() >= $starts_at ) return false;

		return array(
			'reason'     => isset( $options['o100_emergency_closure_reason'] ) ? $options['o100_emergency_closure_reason'] : '',
			'starts_at'  => $starts_at,
			'expires_at' => isset( $options['o100_emergency_closure_expires'] ) ? intval( $options['o100_emergency_closure_expires'] ) : 0,
		);
	}

	/**
	 * Read configured closure reasons from Schedule settings.
	 */
	public static function get_configured_reasons() {
		$hours_opts = get_option( 'o100_store_hours', array() );
		$reasons = isset( $hours_opts['o100_ec_reasons'] ) && is_array( $hours_opts['o100_ec_reasons'] ) ? $hours_opts['o100_ec_reasons'] : array();
		if ( empty( $reasons ) ) {
			// Fallback defaults
			$reasons = array(
				array( 'label' => __( 'High order volume', 'order100' ), 'message' => __( 'Experiencing unusually high order volume. Online ordering is paused.', 'order100' ) ),
				array( 'label' => __( 'Equipment maintenance', 'order100' ), 'message' => __( 'Temporarily closed due to equipment maintenance.', 'order100' ) ),
				array( 'label' => __( 'Sold out', 'order100' ), 'message' => __( 'We are sold out for the day. Thank you for your support!', 'order100' ) ),
				array( 'label' => __( 'Private event / Holiday', 'order100' ), 'message' => __( 'Closed for a private event or holiday.', 'order100' ) ),
			);
		}
		return $reasons;
	}

	/**
	 * Block checkout submission when emergency closure is active.
	 */
	public function block_checkout_if_closed() {
		$closure = self::get_active_closure_data();
		if ( $closure !== false ) {
			$msg = ! empty( $closure['reason'] ) ? $closure['reason'] : __( 'We are temporarily closed and not accepting orders.', 'order100' );
			wc_add_notice( $msg, 'error' );
		}
	}

	/**
	 * Add Node to WordPress Admin Bar
	 */
	public function add_admin_bar_node( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$closure_data = self::get_active_closure_data();
		$pending_data = self::get_pending_closure_data();

		if ( $closure_data !== false ) {
			$icon_color = '#d63638';
			$title_text = __( 'Emergency: CLOSED', 'order100' );
		} elseif ( $pending_data !== false ) {
			$icon_color = '#dba617';
			$title_text = __( 'Closure Scheduled', 'order100' );
		} else {
			$icon_color = '#00a32a';
			$title_text = __( 'Store: OPEN', 'order100' );
		}

		$title = '<span style="display:inline-block; width:10px; height:10px; border-radius:50%; background-color:' . esc_attr( $icon_color ) . '; margin-right:5px;"></span>' . esc_html( $title_text );

		$wp_admin_bar->add_node( array(
			'id'    => 'o100-emergency-status',
			'title' => $title,
			'href'  => '#',
			'meta'  => array(
				'class' => 'o100-emergency-trigger',
				'title' => __( 'Manage Store Opening/Closing Status', 'order100' ),
			),
		) );
	}

	/**
	 * Inject HTML Modal for configuring closure
	 */
	public function inject_admin_modal() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		$closure_data = self::get_active_closure_data();
		$pending_data = self::get_pending_closure_data();
		$options  = get_option( 'o100_options', array() );
		$reasons  = self::get_configured_reasons();
		$mode = 'open';
		$reason = '';
		if ( $closure_data !== false ) { $mode = 'close_now'; $reason = $closure_data['reason']; }
		elseif ( $pending_data !== false ) { $mode = 'scheduled'; $reason = $pending_data['reason']; }
		$starts_at  = isset( $options['o100_emergency_closure_starts'] ) ? intval( $options['o100_emergency_closure_starts'] ) : 0;
		$expires_at = isset( $options['o100_emergency_closure_expires'] ) ? intval( $options['o100_emergency_closure_expires'] ) : 0;
		$tz = wp_timezone(); $now_local = new DateTime( 'now', $tz ); $min_dt = $now_local->format('Y-m-d\TH:i');
		$fmt = function($ts) use ($tz) { if(!$ts) return ''; $d=new DateTime('@'.$ts); $d->setTimezone($tz); return $d->format('Y-m-d\TH:i'); };
		$nonce = wp_create_nonce('o100_emergency_nonce');
		?>
		<style>#o100-emergency-modal{display:none;position:fixed;z-index:100000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.6);align-items:center;justify-content:center}#o100-emergency-modal.o100-active{display:flex}.o100-em-content{background:#fff;padding:28px;border-radius:10px;width:100%;max-width:480px;box-shadow:0 8px 30px rgba(0,0,0,.25);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}.o100-em-content h2{margin:0 0 20px;font-size:1.4em;border-bottom:1px solid #eee;padding-bottom:14px}.o100-em-modes{display:flex;flex-direction:column;gap:8px;margin-bottom:18px}.o100-em-mode{display:flex;align-items:center;gap:10px;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;cursor:pointer;transition:.2s}.o100-em-mode:hover{border-color:#2271b1}.o100-em-mode.active{border-color:#2271b1;background:#f0f6fc}.o100-em-mode input[type=radio]{accent-color:#2271b1;width:18px;height:18px}.o100-em-mode-info{flex:1}.o100-em-mode-info strong{display:block;font-size:14px}.o100-em-mode-info small{color:#666;font-size:12px}.o100-em-panel{display:none;padding:14px;background:#f9f9f9;border-radius:8px;margin-bottom:14px}.o100-em-panel.visible{display:block}.o100-em-row{margin-bottom:12px}.o100-em-row label{display:block;font-weight:600;margin-bottom:5px;font-size:13px}.o100-em-row select,.o100-em-row input[type=text],.o100-em-row input[type=datetime-local]{width:100%;padding:7px 10px;font-size:13px;border-radius:4px;border:1px solid #c3c4c7;box-sizing:border-box}.o100-em-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}.o100-em-btn{padding:8px 18px;border-radius:5px;border:none;cursor:pointer;font-weight:600;font-size:13px}.o100-em-btn-cancel{background:#f0f0f1;color:#2c3338;border:1px solid #8c8f94}.o100-em-btn-save{background:#2271b1;color:#fff}.o100-em-btn-save:hover{background:#135e96}</style>
		<div id="o100-emergency-modal"><div class="o100-em-content">
			<h2><?php esc_html_e('Store Status','order100'); ?></h2>
			<div class="o100-em-modes">
				<label class="o100-em-mode <?php echo $mode==='open'?'active':''; ?>"><input type="radio" name="o100_em_mode" value="open" <?php checked($mode,'open'); ?>><div class="o100-em-mode-info"><strong style="color:#00a32a"><?php esc_html_e('Open — Normal Operation','order100'); ?></strong><small><?php esc_html_e('The store is accepting orders.','order100'); ?></small></div></label>
				<label class="o100-em-mode <?php echo $mode==='close_now'?'active':''; ?>"><input type="radio" name="o100_em_mode" value="close_now" <?php checked($mode,'close_now'); ?>><div class="o100-em-mode-info"><strong style="color:#d63638"><?php esc_html_e('Close Now','order100'); ?></strong><small><?php esc_html_e('Stop accepting orders immediately.','order100'); ?></small></div></label>
				<label class="o100-em-mode <?php echo $mode==='scheduled'?'active':''; ?>"><input type="radio" name="o100_em_mode" value="scheduled" <?php checked($mode,'scheduled'); ?>><div class="o100-em-mode-info"><strong style="color:#dba617"><?php esc_html_e('Scheduled Closure','order100'); ?></strong><small><?php esc_html_e('Set a future time window to close.','order100'); ?></small></div></label>
			</div>
			<div class="o100-em-panel" id="o100-panel-close_now" <?php if($mode==='close_now') echo 'style="display:block"'; ?>>
				<div class="o100-em-row"><label><?php esc_html_e('Auto-Resume At','order100'); ?></label>
					<select id="o100-em-resume-type"><option value="tomorrow"><?php esc_html_e('Tomorrow Morning (4 AM)','order100'); ?></option><option value="30m"><?php esc_html_e('In 30 Minutes','order100'); ?></option><option value="1h"><?php esc_html_e('In 1 Hour','order100'); ?></option><option value="2h"><?php esc_html_e('In 2 Hours','order100'); ?></option><option value="custom"><?php esc_html_e('Custom Time...','order100'); ?></option><option value="manual"><?php esc_html_e('Manual (No Auto-Resume)','order100'); ?></option></select>
				</div>
				<div class="o100-em-row" id="o100-resume-custom-row" style="display:none"><label><?php esc_html_e('Resume At','order100'); ?></label><input type="datetime-local" id="o100-em-resume-date" min="<?php echo esc_attr($min_dt); ?>" value="<?php echo esc_attr($fmt($expires_at)); ?>"></div>
			</div>
			<div class="o100-em-panel" id="o100-panel-scheduled" <?php if($mode==='scheduled') echo 'style="display:block"'; ?>>
				<div class="o100-em-row"><label><?php esc_html_e('Closes At','order100'); ?></label><input type="datetime-local" id="o100-em-start-date" min="<?php echo esc_attr($min_dt); ?>" value="<?php echo esc_attr($fmt($starts_at)); ?>"></div>
				<div class="o100-em-row"><label><?php esc_html_e('Re-opens At','order100'); ?></label><input type="datetime-local" id="o100-em-end-date" min="<?php echo esc_attr($min_dt); ?>" value="<?php echo esc_attr($fmt($expires_at)); ?>"></div>
			</div>
			<div class="o100-em-panel" id="o100-panel-reason" <?php if($mode!=='open') echo 'style="display:block"'; ?>>
				<div class="o100-em-row"><label><?php esc_html_e('Reason (shown to customers)','order100'); ?></label>
					<select id="o100-em-reason-select">
						<?php foreach($reasons as $r): $msg=isset($r['message'])?$r['message']:''; $lbl=isset($r['label'])?$r['label']:$msg; ?>
						<option value="<?php echo esc_attr($msg); ?>" <?php selected($msg,$reason); ?>><?php echo esc_html($lbl); ?></option>
						<?php endforeach; ?>
						<option value="custom" <?php if(!empty($reason)&&!in_array($reason,array_column($reasons,'message'))) echo 'selected'; ?>><?php esc_html_e('Custom...','order100'); ?></option>
					</select>
				</div>
				<div class="o100-em-row" id="o100-custom-reason-row" style="display:none"><input type="text" id="o100-em-custom-reason" placeholder="<?php esc_attr_e('Type your reason...','order100'); ?>" value="<?php echo esc_attr($reason); ?>"></div>
			</div>
			<div class="o100-em-actions"><button class="o100-em-btn o100-em-btn-cancel" id="o100-em-cancel"><?php esc_html_e('Cancel','order100'); ?></button><button class="o100-em-btn o100-em-btn-save" id="o100-em-save"><?php esc_html_e('Save','order100'); ?></button></div>
		</div></div>
		<script>document.addEventListener('DOMContentLoaded',function(){var m=document.getElementById('o100-emergency-modal');if(!m)return;var rs=document.getElementById('o100-em-resume-type'),rz=document.getElementById('o100-em-reason-select');function gm(){var c=document.querySelector('input[name=o100_em_mode]:checked');return c?c.value:'open'}function sp(){var v=gm();document.querySelectorAll('.o100-em-mode').forEach(function(e){e.classList.remove('active')});var a=document.querySelector('input[name=o100_em_mode]:checked');if(a)a.closest('.o100-em-mode').classList.add('active');document.getElementById('o100-panel-close_now').style.display=v==='close_now'?'block':'none';document.getElementById('o100-panel-scheduled').style.display=v==='scheduled'?'block':'none';document.getElementById('o100-panel-reason').style.display=v!=='open'?'block':'none'}document.querySelectorAll('input[name=o100_em_mode]').forEach(function(r){r.addEventListener('change',sp)});rs.addEventListener('change',function(){document.getElementById('o100-resume-custom-row').style.display=this.value==='custom'?'block':'none'});rz.addEventListener('change',function(){document.getElementById('o100-custom-reason-row').style.display=this.value==='custom'?'block':'none'});if(rz.value==='custom')document.getElementById('o100-custom-reason-row').style.display='block';document.querySelectorAll('.o100-emergency-trigger').forEach(function(e){e.addEventListener('click',function(ev){ev.preventDefault();m.classList.add('o100-active')})});document.getElementById('o100-em-cancel').addEventListener('click',function(){m.classList.remove('o100-active')});document.getElementById('o100-em-save').addEventListener('click',function(){var b=this,v=gm(),rv=rz.value,fr=rv==='custom'?document.getElementById('o100-em-custom-reason').value:rv;b.textContent='<?php esc_html_e("Saving...","order100"); ?>';b.disabled=true;var d=new FormData();d.append('action','o100_save_emergency_status');d.append('security','<?php echo esc_js($nonce); ?>');d.append('mode',v);d.append('reason',fr);if(v==='close_now'){d.append('resume_type',rs.value);d.append('resume_date',document.getElementById('o100-em-resume-date').value)}else if(v==='scheduled'){d.append('start_date',document.getElementById('o100-em-start-date').value);d.append('end_date',document.getElementById('o100-em-end-date').value)}fetch(ajaxurl,{method:'POST',body:d}).then(function(r){return r.json()}).then(function(r){if(r.success)window.location.reload();else{alert(r.data||'Error');b.textContent='<?php esc_html_e("Save","order100"); ?>';b.disabled=false}})})});</script>
		<?php
	}
	/**
	 * AJAX Handler for saving emergency status
	 */
	public function ajax_save_status() {
		check_ajax_referer( 'o100_emergency_nonce', 'security' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$mode        = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'open';
		$resume_type = isset( $_POST['resume_type'] ) ? sanitize_text_field( wp_unslash( $_POST['resume_type'] ) ) : '';
		$resume_date = isset( $_POST['resume_date'] ) ? sanitize_text_field( wp_unslash( $_POST['resume_date'] ) ) : '';
		$start_date  = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date    = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		$reason      = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

		$options = get_option( 'o100_options', array() );
		$tz = wp_timezone();
		$now = time();

		if ( $mode === 'close_now' ) {
			$options['o100_emergency_closure'] = 'on';
			$options['o100_emergency_closure_reason'] = $reason;
			$options['o100_emergency_closure_starts'] = 0; // immediate

			$expires_at = 0;
			if ( $resume_type === '30m' ) {
				$expires_at = $now + 1800;
			} elseif ( $resume_type === '1h' ) {
				$expires_at = $now + 3600;
			} elseif ( $resume_type === '2h' ) {
				$expires_at = $now + 7200;
			} elseif ( $resume_type === 'tomorrow' ) {
				$tomorrow = new DateTime( 'tomorrow 04:00:00', $tz );
				$expires_at = $tomorrow->getTimestamp();
			} elseif ( $resume_type === 'custom' && ! empty( $resume_date ) ) {
				$dt = new DateTime( $resume_date, $tz );
				$expires_at = $dt->getTimestamp();
			}
			// 'manual' → $expires_at stays 0

			$options['o100_emergency_closure_expires'] = $expires_at;

		} elseif ( $mode === 'scheduled' ) {
			if ( empty( $start_date ) || empty( $end_date ) ) {
				wp_send_json_error( __( 'Start and end times are required for scheduled closure.', 'order100' ) );
			}
			$dt_start = new DateTime( $start_date, $tz );
			$dt_end   = new DateTime( $end_date, $tz );

			$options['o100_emergency_closure'] = 'on';
			$options['o100_emergency_closure_reason']  = $reason;
			$options['o100_emergency_closure_starts']  = $dt_start->getTimestamp();
			$options['o100_emergency_closure_expires'] = $dt_end->getTimestamp();

		} else {
			// mode === 'open'
			$options['o100_emergency_closure'] = '0';
			$options['o100_emergency_closure_reason'] = '';
			$options['o100_emergency_closure_expires'] = 0;
			$options['o100_emergency_closure_starts'] = 0;
		}

		update_option( 'o100_options', $options );
		wp_send_json_success( array( 'mode' => $mode ) );
	}

	/**
	 * Inject frontend global banner if closed
	 */
	public function inject_frontend_banner() {
		if ( is_admin() || wp_is_json_request() ) {
			return;
		}

		$closure_data = self::get_active_closure_data();
		if ( $closure_data === false ) {
			return;
		}

		$reason = ! empty( $closure_data['reason'] ) ? $closure_data['reason'] : __( 'Temporarily paused', 'order100' );
		$resume_text = '';

		if ( $closure_data['expires_at'] > 0 ) {
			$tz = wp_timezone();
			$dt = new DateTime( '@' . $closure_data['expires_at'] );
			$dt->setTimezone( $tz );
			$now_dt = new DateTime( 'now', $tz );
			$time_fmt = get_option( 'time_format', 'g:i A' );

			if ( $dt->format( 'Y-m-d' ) === $now_dt->format( 'Y-m-d' ) ) {
				$resume_text = sprintf( __( 'Resumes today at %s', 'order100' ), $dt->format( $time_fmt ) );
			} else {
				$resume_text = sprintf( __( 'Resumes %s at %s', 'order100' ), $dt->format( 'M j' ), $dt->format( $time_fmt ) );
			}
		}

		// Read banner colors from settings
		$hours_opts = get_option( 'o100_store_hours', array() );
		$bg_color   = ! empty( $hours_opts['o100_ec_banner_bg'] ) ? $hours_opts['o100_ec_banner_bg'] : '#d63638';
		$text_color = ! empty( $hours_opts['o100_ec_banner_text'] ) ? $hours_opts['o100_ec_banner_text'] : '#ffffff';

		?>
		<style>
			.o100-global-closure-banner {
				position: fixed; top: 0; left: 0; width: 100%;
				background-color: <?php echo esc_attr( $bg_color ); ?>;
				color: <?php echo esc_attr( $text_color ); ?>;
				text-align: center; padding: 12px 20px; z-index: 999999;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				box-shadow: 0 2px 10px rgba(0,0,0,0.2); font-size: 15px; font-weight: 500;
			}
			.o100-global-closure-banner strong { font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
			body { padding-top: 45px !important; }
		</style>
		<div class="o100-global-closure-banner">
			<strong><?php esc_html_e( 'Notice:', 'order100' ); ?></strong>
			<?php echo esc_html( $reason ); ?>
			<?php if ( $resume_text ) : ?>
				<span style="opacity: 0.9; margin-left: 10px;">(<?php echo esc_html( $resume_text ); ?>)</span>
			<?php endif; ?>
		</div>
		<?php
	}
}



