<?php
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		$all_status = self::get_all_closures_status();
		$reasons  = self::get_configured_reasons();
		
		$global_status = $all_status['all'];
		$tz = wp_timezone(); 
		$now_local = new DateTime( 'now', $tz ); 
		$min_dt = $now_local->format('Y-m-d\TH:i');
		$fmt = function($ts) use ($tz) { if(!$ts) return ''; $d=new DateTime('@'.$ts); $d->setTimezone($tz); return $d->format('Y-m-d\TH:i'); };
		$nonce = wp_create_nonce('o100_emergency_nonce');
		
		$multi_branch = get_option('o100_locations_status') === 'on';
		$branches = array();
		if ( $multi_branch ) {
			$b_posts = get_posts( array(
				'post_type'      => 'o100_location',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			) );
			foreach ( $b_posts as $b ) {
				$branches[ $b->ID ] = $b->post_title;
			}
			if ( empty( $branches ) ) {
				$multi_branch = false;
			}
		}
		?>
		<style>#o100-emergency-modal{display:none;position:fixed;z-index:100000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.6);align-items:center;justify-content:center}#o100-emergency-modal.o100-active{display:flex}.o100-em-content{background:#fff;padding:28px;border-radius:10px;width:100%;max-width:480px;box-shadow:0 8px 30px rgba(0,0,0,.25);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}.o100-em-content h2{margin:0 0 20px;font-size:1.4em;border-bottom:1px solid #eee;padding-bottom:14px}.o100-em-modes{display:flex;flex-direction:column;gap:8px;margin-bottom:18px}.o100-em-mode{display:flex;align-items:center;gap:10px;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;cursor:pointer;transition:.2s}.o100-em-mode:hover{border-color:#F59322}.o100-em-mode.active{border-color:#F59322;background:#f0f6fc}.o100-em-mode input[type=radio]{accent-color:#F59322;width:18px;height:18px}.o100-em-mode-info{flex:1}.o100-em-mode-info strong{display:block;font-size:14px}.o100-em-mode-info small{color:#666;font-size:12px}.o100-em-panel{display:none;padding:14px;background:#f9f9f9;border-radius:8px;margin-bottom:14px}.o100-em-panel.visible{display:block}.o100-em-row{margin-bottom:12px}.o100-em-row label{display:block;font-weight:600;margin-bottom:5px;font-size:13px}.o100-em-row select,.o100-em-row input[type=text],.o100-em-row input[type=datetime-local]{width:100%;padding:7px 10px;font-size:13px;border-radius:4px;border:1px solid #c3c4c7;box-sizing:border-box}.o100-em-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}.o100-em-btn{padding:8px 18px;border-radius:5px;border:none;cursor:pointer;font-weight:600;font-size:13px}.o100-em-btn-cancel{background:#f0f0f1;color:#2c3338;border:1px solid #8c8f94}.o100-em-btn-save{background:#F59322;color:#fff}.o100-em-btn-save:hover{background:#135e96}</style>
		<div id="o100-emergency-modal"><div class="o100-em-content">
			<h2><?php esc_html_e('Store Status','order100'); ?></h2>
			
			<?php if ( $multi_branch ): ?>
			<div class="o100-em-row">
				<label><?php esc_html_e('Select Branch to Manage','order100'); ?></label>
				<select id="o100-em-branch-select">
					<option value="all"><?php esc_html_e('All Branches (Global)','order100'); ?></option>
					<?php foreach ( $branches as $bid => $btitle ): ?>
					<option value="<?php echo esc_attr($bid); ?>"><?php echo esc_html($btitle); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php else: ?>
				<input type="hidden" id="o100-em-branch-select" value="all">
			<?php endif; ?>

			<div class="o100-em-modes">
				<label class="o100-em-mode active"><input type="radio" name="o100_em_mode" value="open" checked><div class="o100-em-mode-info"><strong style="color:#00a32a"><?php esc_html_e('Open — Normal Operation','order100'); ?></strong><small><?php esc_html_e('The store is accepting orders.','order100'); ?></small></div></label>
				<label class="o100-em-mode"><input type="radio" name="o100_em_mode" value="close_now"><div class="o100-em-mode-info"><strong style="color:#d63638"><?php esc_html_e('Close Now','order100'); ?></strong><small><?php esc_html_e('Stop accepting orders immediately.','order100'); ?></small></div></label>
				<label class="o100-em-mode"><input type="radio" name="o100_em_mode" value="scheduled"><div class="o100-em-mode-info"><strong style="color:#dba617"><?php esc_html_e('Scheduled Closure','order100'); ?></strong><small><?php esc_html_e('Set a future time window to close.','order100'); ?></small></div></label>
			</div>
			<div class="o100-em-panel" id="o100-panel-close_now">
				<div class="o100-em-row"><label><?php esc_html_e('Auto-Resume At','order100'); ?></label>
					<select id="o100-em-resume-type"><option value="tomorrow"><?php esc_html_e('Tomorrow Morning (4 AM)','order100'); ?></option><option value="30m"><?php esc_html_e('In 30 Minutes','order100'); ?></option><option value="1h"><?php esc_html_e('In 1 Hour','order100'); ?></option><option value="2h"><?php esc_html_e('In 2 Hours','order100'); ?></option><option value="custom"><?php esc_html_e('Custom Time...','order100'); ?></option><option value="manual"><?php esc_html_e('Manual (No Auto-Resume)','order100'); ?></option></select>
				</div>
				<div class="o100-em-row" id="o100-resume-custom-row" style="display:none"><label><?php esc_html_e('Resume At','order100'); ?></label><input type="datetime-local" id="o100-em-resume-date" min="<?php echo esc_attr($min_dt); ?>"></div>
			</div>
			<div class="o100-em-panel" id="o100-panel-scheduled">
				<div class="o100-em-row"><label><?php esc_html_e('Closes At','order100'); ?></label><input type="datetime-local" id="o100-em-start-date" min="<?php echo esc_attr($min_dt); ?>"></div>
				<div class="o100-em-row"><label><?php esc_html_e('Re-opens At','order100'); ?></label><input type="datetime-local" id="o100-em-end-date" min="<?php echo esc_attr($min_dt); ?>"></div>
			</div>
			<div class="o100-em-panel" id="o100-panel-reason">
				<div class="o100-em-row"><label><?php esc_html_e('Reason (shown to customers)','order100'); ?></label>
					<select id="o100-em-reason-select">
						<?php foreach($reasons as $r): $msg=isset($r['message'])?$r['message']:''; $lbl=isset($r['label'])?$r['label']:$msg; ?>
						<option value="<?php echo esc_attr($msg); ?>"><?php echo esc_html($lbl); ?></option>
						<?php endforeach; ?>
						<option value="custom"><?php esc_html_e('Custom...','order100'); ?></option>
					</select>
				</div>
				<div class="o100-em-row" id="o100-custom-reason-row" style="display:none"><input type="text" id="o100-em-custom-reason" placeholder="<?php esc_attr_e('Type your reason...','order100'); ?>"></div>
			</div>
			<div class="o100-em-actions"><button class="o100-em-btn o100-em-btn-cancel" id="o100-em-cancel"><?php esc_html_e('Cancel','order100'); ?></button><button class="o100-em-btn o100-em-btn-save" id="o100-em-save"><?php esc_html_e('Save','order100'); ?></button></div>
		</div></div>
		<script>
		document.addEventListener('DOMContentLoaded',function(){
			var m=document.getElementById('o100-emergency-modal');if(!m)return;
			var rs=document.getElementById('o100-em-resume-type'),rz=document.getElementById('o100-em-reason-select');
			var bs=document.getElementById('o100-em-branch-select');
			var closures = <?php echo json_encode($all_status); ?>;
			var formatDateTime = function(ts) {
				if(!ts)return'';var d=new Date(ts*1000);
				return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')+'T'+String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0');
			};
			function gm(){var c=document.querySelector('input[name=o100_em_mode]:checked');return c?c.value:'open'}
			function sp(){
				var v=gm();
				document.querySelectorAll('.o100-em-mode').forEach(function(e){e.classList.remove('active')});
				var a=document.querySelector('input[name=o100_em_mode]:checked');if(a)a.closest('.o100-em-mode').classList.add('active');
				document.getElementById('o100-panel-close_now').style.display=v==='close_now'?'block':'none';
				document.getElementById('o100-panel-scheduled').style.display=v==='scheduled'?'block':'none';
				document.getElementById('o100-panel-reason').style.display=v!=='open'?'block':'none';
			}
			function loadStatus() {
				var bid=bs.value;
				var st=closures[bid]||{mode:'open',reason:'',starts_at:0,expires_at:0};
				document.querySelector('input[name=o100_em_mode][value="'+st.mode+'"]').checked=true;
				sp();
				if(st.mode!=='open'){
					var hasReason=false;
					Array.from(rz.options).forEach(function(o){if(o.value===st.reason)hasReason=true;});
					if(hasReason){rz.value=st.reason;}else{rz.value='custom';document.getElementById('o100-em-custom-reason').value=st.reason;}
					document.getElementById('o100-custom-reason-row').style.display=rz.value==='custom'?'block':'none';
					
					if(st.mode==='scheduled'){
						document.getElementById('o100-em-start-date').value=formatDateTime(st.starts_at);
						document.getElementById('o100-em-end-date').value=formatDateTime(st.expires_at);
					}else if(st.mode==='close_now'){
						if(st.expires_at>0){
							rs.value='custom';
							document.getElementById('o100-resume-custom-row').style.display='block';
							document.getElementById('o100-em-resume-date').value=formatDateTime(st.expires_at);
						}else{
							rs.value='manual';
							document.getElementById('o100-resume-custom-row').style.display='none';
						}
					}
				}
			}
			document.querySelectorAll('input[name=o100_em_mode]').forEach(function(r){r.addEventListener('change',sp)});
			rs.addEventListener('change',function(){document.getElementById('o100-resume-custom-row').style.display=this.value==='custom'?'block':'none'});
			rz.addEventListener('change',function(){document.getElementById('o100-custom-reason-row').style.display=this.value==='custom'?'block':'none'});
			if(bs)bs.addEventListener('change',loadStatus);
			loadStatus();
			
			document.querySelectorAll('.o100-emergency-trigger').forEach(function(e){e.addEventListener('click',function(ev){ev.preventDefault();m.classList.add('o100-active')})});
			document.getElementById('o100-em-cancel').addEventListener('click',function(){m.classList.remove('o100-active')});
			document.getElementById('o100-em-save').addEventListener('click',function(){
				var b=this,v=gm(),rv=rz.value,fr=rv==='custom'?document.getElementById('o100-em-custom-reason').value:rv;
				b.textContent='<?php esc_html_e("Saving...","order100"); ?>';b.disabled=true;
				var d=new FormData();d.append('action','o100_save_emergency_status');d.append('security','<?php echo esc_js($nonce); ?>');
				d.append('mode',v);d.append('reason',fr);d.append('branch_id',bs.value);
				if(v==='close_now'){d.append('resume_type',rs.value);d.append('resume_date',document.getElementById('o100-em-resume-date').value)}
				else if(v==='scheduled'){d.append('start_date',document.getElementById('o100-em-start-date').value);d.append('end_date',document.getElementById('o100-em-end-date').value)}
				fetch(ajaxurl,{method:'POST',body:d}).then(function(r){return r.json()}).then(function(r){
					if(r.success)window.location.reload();else{alert(r.data||'Error');b.textContent='<?php esc_html_e("Save","order100"); ?>';b.disabled=false}
				});
			});
		});
		</script>
