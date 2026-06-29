<?php
/**
 * Entry Modal / Order Method Selection
 *
 * @package Order100
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class O100_Entry_Modal {

	public function __construct() {
		add_action( 'wp_footer', array( $this, 'render_entry_modal' ) );
		add_action( 'wp_ajax_o100_save_entry_selection', array( $this, 'ajax_save_selection' ) );
		add_action( 'wp_ajax_nopriv_o100_save_entry_selection', array( $this, 'ajax_save_selection' ) );
		add_action( 'wp_ajax_o100_dismiss_entry_modal', array( $this, 'ajax_dismiss_modal' ) );
		add_action( 'wp_ajax_nopriv_o100_dismiss_entry_modal', array( $this, 'ajax_dismiss_modal' ) );
	}

	public function render_entry_modal() {
		global $o100_disable_entry_modal, $o100_enable_entry_modal;
		if ( ! empty( $o100_disable_entry_modal ) ) {
			return;
		}

		// Skip on admin or if WooCommerce session is not loaded
		if ( is_admin() ) return;

		// Only show on Menu pages (Shop, Category, or if explicitly enabled by shortcode)
		$is_menu_page = ( function_exists('is_shop') && ( is_shop() || is_product_category() ) );
		if ( ! $is_menu_page && empty( $o100_enable_entry_modal ) ) {
			return;
		}
		
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		
		$method = WC()->session->get( '_o100_order_method' );
		$dismissed = WC()->session->get( 'o100_entry_dismissed' );
		
		// If already selected or dismissed, do not show popup.
		if ( ! empty( $method ) || $dismissed ) {
			return;
		}
		
		// Fetch options
		$opts_delivery = get_option( 'o100_delivery', array() );
		$opts_pickup   = get_option( 'o100_pickup', array() );
		$opts_locations = get_option( 'o100_locations', array() );
		$opts_ui       = get_option( 'o100_ui_prefs', array() );
		
		$enable_delivery = ! empty( $opts_delivery['o100_enable_delivery'] ) && $opts_delivery['o100_enable_delivery'] === 'on';
		$enable_pickup   = ! empty( $opts_pickup['o100_enable_pickup'] ) && $opts_pickup['o100_enable_pickup'] === 'on';
		$enable_location = get_option('o100_locations_status') === 'on';
		$show_close_btn  = ! empty( $opts_ui['o100_close_btn'] ) && $opts_ui['o100_close_btn'] === 'on';

		// Fetch Locations
		$locations = array();
		if ( $enable_location ) {
			$loc_query = new WP_Query( array(
				'post_type'      => 'o100_location',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC'
			) );

			if ( $loc_query->have_posts() ) {
				$locations = $loc_query->posts;
				if ( count( $locations ) === 1 ) {
					// Only 1 location exists, auto-select it and skip location selection logic
					if ( WC()->session && ! WC()->session->get( 'o100_location_id' ) ) {
						WC()->session->set( 'o100_location_id', $locations[0]->ID );
					}
					$enable_location = false;
				}
			} else {
				$enable_location = false;
			}
			wp_reset_postdata();
		}

		// Check if we already have session data
		$has_method   = WC()->session ? WC()->session->get( '_o100_order_method' ) : false;
		$has_location = WC()->session ? WC()->session->get( 'ex_userloc' ) : false;
		if ( ! $has_location && WC()->session && WC()->session->get( 'o100_location_id' ) ) {
			$has_location = WC()->session->get( 'o100_location_id' );
		}

		$selected_is_closed = false;
		// Verify if the selected location is currently closed (Emergency Closure)
		if ( $has_location && class_exists( 'O100_Emergency_Closure' ) ) {
			$closure_check = O100_Emergency_Closure::get_active_closure_data( intval( $has_location ) );
			if ( $closure_check !== false ) {
				// The branch the user selected is currently closed! We should force the modal so they can pick another.
				$selected_is_closed = true;
			}
		}

		// We need the modal if the enabled features are missing from the session
		$need_method   = ( $enable_delivery || $enable_pickup ) && ! $has_method;
		$need_location = $enable_location && ! $has_location;

		// Force modal if features are enabled but session is empty, OR if selected branch is closed.
		$force_modal = $need_method || $need_location || ( $enable_location && $selected_is_closed );

		// The modal can be closed if it's just triggered manually (not forced)
		// User request: always show the close button so users can browse menu first.
		$show_close_btn = true;

		// If we don't need to force it, and it's not manually triggered (we don't have a manual trigger yet), hide it.
		// For now, only render if forced.
		if ( ! $force_modal ) {
			// If not forced, we might want to still output the HTML but hidden so JS can trigger it.
			// But for now, returning early means no modal. Let's keep returning early if not needed.
			return;
		}

		// If nothing enabled, abort
		if ( ! $enable_delivery && ! $enable_pickup ) {
			return;
		}
		
		// Fetch Store Open Status
		$is_store_open = true;
		if ( class_exists( 'O100_Public' ) ) {
			$public = new O100_Public();
			$is_store_open = $public->is_store_open();
		}

		$store_hours_opts = get_option('o100_store_hours', array());
		$allow_outside = !empty($store_hours_opts['o100_op_cl']) && $store_hours_opts['o100_op_cl'] === 'on';
		$outside_msg = !empty($store_hours_opts['o100_outside_hours_msg']) ? $store_hours_opts['o100_outside_hours_msg'] : esc_html__( 'We are currently outside business hours. You may still place your order — please select a time slot at checkout.', 'order100' );
		
		$global_store_address = '';
		if ( function_exists('cmb2_get_option') ) {
			$global_store_address = cmb2_get_option('o100_store_profile', 'o100_store_address');
		}
		if ( empty( $global_store_address ) ) {
			$store_profile_opts = get_option('o100_store_profile', array());
			$global_store_address = is_array($store_profile_opts) && !empty($store_profile_opts['o100_store_address']) ? $store_profile_opts['o100_store_address'] : '';
		}
		if ( empty( $global_store_address ) ) {
			$global_store_address = get_option('o100_store_address', '');
		}
		if ( empty( $global_store_address ) ) {
			$store_hours_opts = get_option('o100_store_hours', array());
			$global_store_address = is_array($store_hours_opts) && !empty($store_hours_opts['o100_store_address']) ? $store_hours_opts['o100_store_address'] : '';
		}
		if ( empty( $global_store_address ) ) {
			$addr1 = get_option('woocommerce_store_address', '');
			$addr2 = get_option('woocommerce_store_address_2', '');
			$city = get_option('woocommerce_store_city', '');
			$postcode = get_option('woocommerce_store_postcode', '');
			$global_store_address = trim($addr1 . ' ' . $addr2 . ' ' . $city . ' ' . $postcode);
		}

		// Output the HTML
		?>
		<div id="o100-entry-modal" class="o100-modal-overlay">
			<div class="o100-modal-container">
				<?php if ( !$is_store_open && $allow_outside ) : ?>
					<div class="o100-alert-banner">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
						<span><?php echo esc_html( $outside_msg ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( $show_close_btn ) : ?>
					<button type="button" class="o100-modal-close" aria-label="<?php esc_attr_e( 'Close', 'order100' ); ?>">&times;</button>
				<?php endif; ?>
				
				<div class="o100-modal-body">

					<form id="o100-entry-form">
						<!-- Location JSON Data for JS -->
						<?php if ( $enable_location && count($locations) > 0 ) : ?>
							<script>
								window.o100_branches = <?php 
									$branch_data = array();
									foreach($locations as $loc) {
										$is_closed = false;
										$closure_reason = '';
										if ( class_exists( 'O100_Emergency_Closure' ) ) {
											$c = O100_Emergency_Closure::get_active_closure_data( $loc->ID );
											if ( $c !== false ) {
												$is_closed = true;
												$closure_reason = ! empty( $c['reason'] ) ? $c['reason'] : __( 'Closed', 'order100' );
											}
										}
										$branch_data[] = array(
											'id' => $loc->ID,
											'name' => $loc->post_title,
											'address' => get_post_meta($loc->ID, 'o100_address', true),
											'latlng' => get_post_meta($loc->ID, 'o100_latlng', true),
											'distance_limit' => get_post_meta($loc->ID, 'o100_distance_restrict', true),
											'enable_delivery' => get_post_meta($loc->ID, 'o100_enable_delivery', true) !== 'no',
											'enable_pickup' => get_post_meta($loc->ID, 'o100_enable_pickup', true) !== 'no',
											'is_closed' => $is_closed,
											'closure_reason' => $closure_reason,
										);
									}
									echo json_encode($branch_data);
								?>;
							</script>
							<input type="hidden" name="o100_location" id="o100_location_input" value="">
						<?php endif; ?>

						<!-- TAB HEADER -->
						<div class="o100-tabs-header">
							<?php if ( $enable_delivery ) : ?>
								<div class="o100-tab-btn active" data-tab="delivery"><?php esc_html_e( 'Delivery', 'order100' ); ?></div>
							<?php endif; ?>
							<?php if ( $enable_pickup ) : ?>
								<div class="o100-tab-btn <?php echo !$enable_delivery ? 'active' : ''; ?>" data-tab="pickup"><?php esc_html_e( 'Pickup', 'order100' ); ?></div>
							<?php endif; ?>
						</div>
						
						<input type="hidden" name="o100_method" id="o100_method_input" value="<?php echo $enable_delivery ? 'delivery' : 'pickup'; ?>">

						<!-- DELIVERY TAB CONTENT -->
						<?php if ( $enable_delivery ) : ?>
						<div class="o100-tab-content active" id="tab-delivery">
							<!-- Step 1: Address Input -->
							<div class="o100-delivery-step-1">
								<div class="o100-form-group">
									<label><?php esc_html_e( 'Where are we delivering?', 'order100' ); ?></label>
									<div class="o100-input-icon-wrap">
										<input type="text" name="ex_useraddre" id="exwf-user-address" placeholder="<?php esc_attr_e('Enter your delivery address', 'order100'); ?>">
										<button type="button" id="o100-geolocate-btn" class="o100-geo-btn" title="<?php esc_attr_e('Current Location', 'order100'); ?>">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
												<path d="M12 21c-2.5-3.5-7-8-7-12A7 7 0 0 1 19 9c0 1.4-.4 2.8-1 4"></path>
												<circle cx="12" cy="9" r="2.5"></circle>
												<path d="M14 15l4-3 4 3v6h-8v-6z"></path>
											</svg>
										</button>
									</div>
								</div>
								<?php if ( $enable_location ) : ?>
									<button type="button" class="o100-btn-primary o100-btn-block o100-find-delivery-branches-btn" style="margin-bottom:20px;"><?php esc_html_e('Find Branches', 'order100'); ?></button>
								<?php endif; ?>
							</div>

							<!-- Step 2: Branch Selection (Hidden initially) -->
							<?php if ( $enable_location ) : ?>
							<div class="o100-delivery-step-2" style="display:none;">
								<div class="o100-address-summary" style="background:#f9fafb; border-radius:8px; padding:12px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M12 21c-2.5-3.5-7-8-7-12A7 7 0 0 1 19 9c0 1.4-.4 2.8-1 4"></path><circle cx="12" cy="9" r="2.5"></circle></svg>
									<span id="o100-user-address-display" style="flex:1; font-size:14px; color:#334155; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></span>
									<a href="#" id="o100-change-address" style="color:#F59322; font-size:13px; font-weight:600; text-decoration:none;"><?php esc_html_e('Change', 'order100'); ?></a>
								</div>
								<label class="o100-branches-label" style="display:block; font-size:15px; font-weight:600; color:#111827; margin-bottom:12px;"><?php esc_html_e( 'Select a branch', 'order100' ); ?></label>
								<div class="o100-branches-list" id="o100-delivery-branches" style="max-height:240px; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
									<!-- Populated via JS -->
								</div>
							</div>
							<?php endif; ?>
						</div>
						<?php endif; ?>

						<!-- PICKUP TAB CONTENT -->
						<?php if ( $enable_pickup ) : ?>
						<div class="o100-tab-content <?php echo !$enable_delivery ? 'active' : ''; ?>" id="tab-pickup">
							<?php if ( $enable_location && count($locations) > 0 ) : ?>
								<label class="o100-branches-label" style="display:block; font-size:15px; font-weight:600; color:#111827; margin-bottom:12px;"><?php esc_html_e( 'Select a branch for pickup', 'order100' ); ?></label>
								<button type="button" id="o100-sort-distance-btn" style="background:#f3f4f6; color:#4b5563; border:1px solid #d1d5db; width:100%; padding:10px; border-radius:8px; cursor:pointer; font-weight:600; font-size:14px; margin-bottom:16px; display:flex; align-items:center; justify-content:center; gap:6px;">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path><path d="M2 12h20"></path></svg>
									<?php esc_html_e('Sort by nearest', 'order100'); ?>
								</button>
								<div class="o100-branches-list" id="o100-pickup-branches" style="max-height:300px; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
									<?php foreach ($locations as $loc): ?>
										<?php 
											if ( get_post_meta($loc->ID, 'o100_enable_pickup', true) === 'no' ) continue; 
											$is_closed = false;
											$closure_reason = '';
											if ( class_exists( 'O100_Emergency_Closure' ) ) {
												$c = O100_Emergency_Closure::get_active_closure_data( $loc->ID );
												if ( $c !== false ) {
													$is_closed = true;
													$closure_reason = ! empty( $c['reason'] ) ? $c['reason'] : __( 'Closed', 'order100' );
												}
											}
											$closed_style = $is_closed ? 'opacity:0.5; cursor:not-allowed;' : 'cursor:pointer;';
											$sel_class = $is_closed ? '' : 'o100-selectable-branch';
										?>
										<div class="o100-branch-card <?php echo esc_attr($sel_class); ?>" data-id="<?php echo esc_attr($loc->ID); ?>" style="border:1px solid #e2e8f0; border-radius:12px; padding:14px; transition:all 0.2s; <?php echo esc_attr($closed_style); ?>">
											<div class="o100-branch-name" style="font-weight:600; color:#0f172a; font-size:15px; margin-bottom:4px;">
												<?php echo esc_html($loc->post_title); ?>
												<?php if ( $is_closed ): ?>
													<span style="background:#ef4444; color:#fff; font-size:11px; padding:2px 6px; border-radius:4px; margin-left:6px;"><?php echo esc_html($closure_reason); ?></span>
												<?php endif; ?>
											</div>
											<div class="o100-branch-address" style="color:#64748b; font-size:13px;"><?php echo esc_html(get_post_meta($loc->ID, 'o100_address', true)); ?></div>
											<div class="o100-branch-distance" style="display:none; color:#F59322; font-weight:600; font-size:13px; margin-top:6px;"></div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php elseif ( !empty($global_store_address) ) : ?>
							<div class="o100-store-address-display">
								<div class="o100-store-icon">
									<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
										<path d="M12 21c-2.5-3.5-7-8-7-12A7 7 0 0 1 19 9c0 1.4-.4 2.8-1 4"></path>
										<circle cx="12" cy="9" r="2.5"></circle>
										<path d="M14 15l4-3 4 3v6h-8v-6z"></path>
									</svg>
								</div>
								<div class="o100-store-text">
									<strong><?php esc_html_e('Store Address', 'order100'); ?></strong><br>
									<span><?php echo esc_html( $global_store_address ); ?></span>
								</div>
							</div>
							<?php endif; ?>
						</div>
						<?php endif; ?>

						<div class="o100-modal-footer">
							<button type="submit" class="o100-btn-primary o100-btn-block"><?php esc_html_e( 'START MY ORDER', 'order100' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<style>
			.o100-modal-overlay {
				position: fixed;
				top: 0; left: 0; right: 0; bottom: 0;
				background: rgba(0,0,0,0.65);
				backdrop-filter: blur(4px);
				z-index: 999999;
				display: flex;
				align-items: center;
				justify-content: center;
			}
			body.o100-modal-open { overflow: hidden; }
			.o100-modal-container {
				background: #fff;
				border-radius: 16px;
				width: 100%;
				max-width: 440px;
				padding: 0;
				box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
				transform: translateY(0);
				animation: o100ModalPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
				overflow: hidden;
				position: relative;
			}
			@keyframes o100ModalPop {
				from { opacity: 0; transform: translateY(30px) scale(0.95); }
				to { opacity: 1; transform: translateY(0) scale(1); }
			}
			.o100-modal-close {
				position: absolute;
				top: 12px;
				right: 12px;
				background: #fff;
				border: 1px solid #e5e7eb;
				border-radius: 50%;
				width: 32px; height: 32px;
				font-size: 20px;
				line-height: 1;
				color: #4b5563;
				cursor: pointer;
				transition: all 0.2s;
				display: flex;
				align-items: center;
				justify-content: center;
				z-index: 10;
				box-shadow: 0 2px 4px rgba(0,0,0,0.05);
			}
			.o100-modal-close:hover {
				background: #f9fafb;
				color: #111827;
			}
			
			/* Alert Banner */
			.o100-alert-banner {
				background: #fff1f2;
				color: #e11d48;
				padding: 16px 48px 16px 24px;
				font-size: 14px;
				font-weight: 500;
				line-height: 1.5;
				display: flex;
				align-items: flex-start;
				gap: 12px;
				border-bottom: 1px solid #fecdd3;
			}
			.o100-alert-banner svg {
				flex-shrink: 0;
				margin-top: 2px;
			}

			.o100-modal-body {
				padding: 24px;
			}

			/* Tabs */
			.o100-tabs-header {
				display: flex;
				background: #f3f4f6;
				border-radius: 999px;
				padding: 4px;
				margin-bottom: 24px;
				position: relative;
			}
			.o100-tab-btn {
				flex: 1;
				text-align: center;
				padding: 10px 16px;
				font-size: 15px;
				font-weight: 600;
				color: #4b5563;
				cursor: pointer;
				border-radius: 999px;
				transition: all 0.2s ease;
				z-index: 2;
			}
			.o100-tab-btn.active {
				background: #fff;
				color: #111827;
				box-shadow: 0 1px 3px rgba(0,0,0,0.1);
			}
			.o100-tab-content {
				display: none;
			}
			.o100-tab-content.active {
				display: block;
				animation: fadeIn 0.3s ease;
			}
			@keyframes fadeIn {
				from { opacity: 0; }
				to { opacity: 1; }
			}

			.o100-form-group {
				margin-bottom: 24px;
			}
			.o100-form-group label {
				display: block;
				font-size: 15px;
				font-weight: 600;
				color: #111827;
				margin-bottom: 8px;
			}
			.o100-form-group select,
			.o100-form-group input[type="text"] {
				width: 100%;
				padding: 14px 16px;
				border: 1px solid #d1d5db;
				border-radius: 12px;
				font-size: 16px;
				color: #111827;
				background: #fff;
				transition: border-color 0.2s, box-shadow 0.2s;
				outline: none;
				box-sizing: border-box;
			}
			.o100-form-group select:focus,
			.o100-form-group input[type="text"]:focus {
				border-color: #e11d48;
				box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.1);
			}

			/* Input with Icon */
			.o100-input-icon-wrap {
				position: relative;
				display: flex;
				align-items: center;
			}
			.o100-input-icon-wrap input {
				padding-right: 56px;
			}
			.o100-geo-btn {
				position: absolute;
				right: 8px;
				background: #e11d48;
				border: none;
				border-radius: 8px;
				color: #fff;
				cursor: pointer;
				padding: 6px;
				display: flex;
				align-items: center;
				justify-content: center;
				transition: background 0.2s;
				width: 36px;
				height: 36px;
			}
			.o100-geo-btn:hover {
				background: #be123c;
			}

			/* Pickup Store Address Display */
			.o100-store-address-display {
				display: flex;
				align-items: center;
				gap: 16px;
				padding: 16px;
				background: #f9fafb;
				border: 1px solid #e5e7eb;
				border-radius: 12px;
				margin-bottom: 24px;
			}
			.o100-store-icon {
				background: #fff;
				border: 1px solid #e5e7eb;
				border-radius: 8px;
				padding: 8px;
				display: flex;
				color: #4b5563;
			}
			.o100-store-text {
				font-size: 14px;
				color: #4b5563;
				line-height: 1.4;
			}
			.o100-store-text strong {
				color: #111827;
				font-size: 15px;
			}

			/* Button */
			.o100-modal-footer {
				margin-top: 16px;
			}
			.o100-btn-primary {
				width: 100%;
				background: #e11d48;
				color: #fff;
				font-weight: 700;
				font-size: 16px;
				padding: 16px;
				border: none;
				border-radius: 999px;
				cursor: pointer;
				transition: background 0.2s, transform 0.1s;
				box-sizing: border-box;
				letter-spacing: 0.5px;
			}
			.o100-btn-primary:hover:not(:disabled) {
				background: #be123c;
			}
			.o100-btn-primary:active:not(:disabled) {
				transform: scale(0.98);
			}
			.o100-btn-primary:disabled {
				background: #f3f4f6;
				color: #9ca3af;
				cursor: not-allowed;
			}
			
			/* Hide pac container by default inside modal if needed, Google injects to body */
			body .pac-container {
				z-index: 9999999 !important;
				border-radius: 8px;
				border: 1px solid #e5e7eb;
				box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
				font-family: inherit;
			}
		</style>
		<script>
			jQuery(document).ready(function($) {
				var $modal = $('#o100-entry-modal');
				var $form = $('#o100-entry-form');
				var $btn = $form.find('button[type="submit"]');
				var hasLocation = $form.find('.o100_location_select').length > 0;
				var addressInput = document.getElementById('exwf-user-address');
				
				if ($modal.length) {
					$('body').addClass('o100-modal-open');
				}

				// Tab Switching Logic
				$('.o100-tab-btn').on('click', function() {
					var targetTab = $(this).data('tab');
					
					// Update Buttons
					$('.o100-tab-btn').removeClass('active');
					$(this).addClass('active');
					
					// Update Hidden Input
					$('#o100_method_input').val(targetTab);
					
					// Update Content
					$('.o100-tab-content').removeClass('active');
					$('#tab-' + targetTab).addClass('active');
					
					checkValidity();
				});

				function checkValidity() {
					var currentMethod = $('#o100_method_input').val();
					var isValid = true;
					var hasLocationEnabled = window.o100_branches && window.o100_branches.length > 0;

					if (hasLocationEnabled) {
						if (!$('#o100_location_input').val()) {
							isValid = false;
						}
					}

					if (currentMethod === 'delivery' && addressInput) {
						if (!$(addressInput).val().trim()) isValid = false;
					}
					
					$btn.prop('disabled', !isValid);
				}

				// Haversine Distance Calculator (km)
				function getDistance(lat1, lon1, lat2, lon2) {
					var R = 6371; // km
					var dLat = (lat2 - lat1) * Math.PI / 180;
					var dLon = (lon2 - lon1) * Math.PI / 180;
					var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
						Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
						Math.sin(dLon / 2) * Math.sin(dLon / 2);
					var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
					return R * c;
				}

				var userLatLng = null;

				// Google Maps Autocomplete Initialization
				var initRetries = 0;
				function initEntryAutocomplete() {
					if (addressInput && typeof google !== 'undefined' && google.maps && google.maps.places) {
						var autocomplete = new google.maps.places.Autocomplete(addressInput, { types: ['geocode'] });
						
						$(addressInput).on('keydown', function(e) {
							if (e.key === 'Enter' || e.keyCode === 13) {
								e.preventDefault();
							}
						});

						autocomplete.addListener('place_changed', function() {
							var place = autocomplete.getPlace();
							if (place && place.geometry) {
								userLatLng = { lat: place.geometry.location.lat(), lng: place.geometry.location.lng() };
								$(addressInput).val(place.formatted_address);
							} else {
								userLatLng = null;
							}
							checkValidity();
						});
					} else if (initRetries < 10) {
						initRetries++;
						setTimeout(initEntryAutocomplete, 500);
					}
				}
				initEntryAutocomplete();

				// HTML5 Geolocation
				$('#o100-geolocate-btn').on('click', function(e) {
					e.preventDefault();
					if (navigator.geolocation) {
						var $btnIcon = $(this).find('svg');
						$btnIcon.css({opacity: 0.5});
						navigator.geolocation.getCurrentPosition(function(position) {
							if (typeof google !== 'undefined' && google.maps) {
								var geocoder = new google.maps.Geocoder();
								userLatLng = {lat: position.coords.latitude, lng: position.coords.longitude};
								geocoder.geocode({'location': userLatLng}, function(results, status) {
									$btnIcon.css({opacity: 1});
									if (status === 'OK' && results[0]) {
										$(addressInput).val(results[0].formatted_address).trigger('change');
									}
								});
							}
						}, function() {
							$btnIcon.css({opacity: 1});
							alert("<?php esc_html_e( 'Geolocation failed.', 'order100' ); ?>");
						});
					}
				});

				// Delivery: Find Branches Button
				$('.o100-find-delivery-branches-btn').on('click', function(e) {
					e.preventDefault();
					var addr = $(addressInput).val().trim();
					if (!addr) {
						alert("<?php esc_html_e( 'Please enter your address first.', 'order100' ); ?>");
						return;
					}

					var processBranches = function() {
						$('#o100-user-address-display').text(addr);
						var $list = $('#o100-delivery-branches');
						$list.empty();
						
						var validBranches = [];
						$.each(window.o100_branches, function(i, b) {
							if (b.enable_delivery === false) return; // Skip branches with delivery disabled
							if (b.latlng) {
								var coords = b.latlng.split(',');
								if (coords.length === 2) {
									var bLat = parseFloat(coords[0]);
									var bLng = parseFloat(coords[1]);
									var dist = getDistance(userLatLng.lat, userLatLng.lng, bLat, bLng);
									var limit = parseFloat(b.distance_limit) || 0;
									
									if (limit === 0 || dist <= limit) {
										b.calc_dist = dist;
										validBranches.push(b);
									}
								}
							}
						});

						if (validBranches.length === 0) {
							$list.html('<div style="padding:15px; text-align:center; color:#e11d48; font-weight:600;"><?php esc_html_e("Sorry, no branches deliver to this area.", "order100"); ?></div>');
						} else {
							validBranches.sort(function(a,b){ return a.calc_dist - b.calc_dist; });
							$.each(validBranches, function(i, b) {
								var closedStyle = b.is_closed ? 'opacity:0.5; cursor:not-allowed;' : 'cursor:pointer;';
								var selClass = b.is_closed ? '' : 'o100-selectable-branch';
								var html = '<div class="o100-branch-card '+selClass+'" data-id="'+b.id+'" style="border:1px solid #e2e8f0; border-radius:12px; padding:14px; transition:all 0.2s; '+closedStyle+'">';
								html += '<div style="font-weight:600; color:#0f172a; font-size:15px; margin-bottom:4px;">'+b.name;
								if ( b.is_closed ) {
									html += ' <span style="background:#ef4444; color:#fff; font-size:11px; padding:2px 6px; border-radius:4px; margin-left:6px;">'+b.closure_reason+'</span>';
								}
								html += '</div>';
								html += '<div style="color:#64748b; font-size:13px;">'+b.address+'</div>';
								html += '<div style="color:#F59322; font-weight:600; font-size:13px; margin-top:6px;">'+b.calc_dist.toFixed(1)+' km</div>';
								html += '</div>';
								$list.append(html);
							});

							if (validBranches.length === 1) {
								$list.find('.o100-branch-card').click();
							}
						}

						$('.o100-delivery-step-1').slideUp(200);
						$('.o100-delivery-step-2').slideDown(200);
					};

					if (!userLatLng && typeof google !== 'undefined' && google.maps) {
						var geocoder = new google.maps.Geocoder();
						geocoder.geocode({ 'address': addr }, function(results, status) {
							if (status === 'OK') {
								userLatLng = { lat: results[0].geometry.location.lat(), lng: results[0].geometry.location.lng() };
								processBranches();
							} else {
								alert("<?php esc_html_e( 'Could not locate this address.', 'order100' ); ?>");
							}
						});
					} else {
						processBranches();
					}
				});

				$('#o100-change-address').on('click', function(e) {
					e.preventDefault();
					$('.o100-delivery-step-2').slideUp(200);
					$('.o100-delivery-step-1').slideDown(200);
					$('#o100_location_input').val('');
					$('.o100-branch-card').css({'border-color': '#e2e8f0', 'background': '#fff'});
					checkValidity();
				});

				// Pickup: Sort Nearest
				$('#o100-sort-distance-btn').on('click', function(e) {
					e.preventDefault();
					if (navigator.geolocation) {
						var $btn = $(this);
						$btn.css({opacity: 0.5}).text("<?php esc_html_e('Locating...', 'order100'); ?>");
						navigator.geolocation.getCurrentPosition(function(position) {
							$btn.hide();
							var uLat = position.coords.latitude;
							var uLng = position.coords.longitude;
							
							var $cards = $('#o100-pickup-branches .o100-selectable-branch').get();
							$cards.sort(function(a, b) {
								var idA = $(a).data('id');
								var idB = $(b).data('id');
								var branchA = window.o100_branches.find(function(x) { return x.id == idA; });
								var branchB = window.o100_branches.find(function(x) { return x.id == idB; });
								var distA = 99999, distB = 99999;
								
								if (branchA && branchA.latlng) {
									var coords = branchA.latlng.split(',');
									distA = getDistance(uLat, uLng, parseFloat(coords[0]), parseFloat(coords[1]));
									$(a).find('.o100-branch-distance').text(distA.toFixed(1) + ' km').show();
								}
								if (branchB && branchB.latlng) {
									var coords = branchB.latlng.split(',');
									distB = getDistance(uLat, uLng, parseFloat(coords[0]), parseFloat(coords[1]));
									$(b).find('.o100-branch-distance').text(distB.toFixed(1) + ' km').show();
								}
								return distA - distB;
							});
							$.each($cards, function(idx, itm) { $('#o100-pickup-branches').append(itm); });
						}, function() {
							$btn.css({opacity: 1}).text("<?php esc_html_e('Locating failed', 'order100'); ?>");
						});
					}
				});

				// Branch Selection
				$(document).on('click', '.o100-selectable-branch', function() {
					$(this).closest('.o100-branches-list').find('.o100-selectable-branch').css({'border-color': '#e2e8f0', 'background': '#fff'});
					$(this).css({'border-color': '#e11d48', 'background': '#fff1f2'});
					$('#o100_location_input').val($(this).data('id'));
					checkValidity();
				});

				// Bind checkValidity to inputs
				$form.find('input, select').on('input change keyup', checkValidity);
				
				// Initial check
				checkValidity();

				$form.on('submit', function(e) {
					e.preventDefault();
					$btn.prop('disabled', true).text('<?php esc_html_e( "Saving...", "order100" ); ?>');
					
					var currentMethod = $('#o100_method_input').val();
					var selectedLoc = $('#o100_location_input').val();
					var address = (currentMethod === 'delivery' && addressInput) ? $(addressInput).val() : '';

					var selectedDist = '';
					if (selectedLoc && window.o100_branches && window.o100_branches.length > 0) {
						var selectedBranch = window.o100_branches.find(function(b) { return String(b.id) === String(selectedLoc); });
						if (selectedBranch && selectedBranch.calc_dist) {
							selectedDist = selectedBranch.calc_dist;
						}
					}

					var data = {
						action: 'o100_save_entry_selection',
						method: currentMethod,
						location: selectedLoc,
						address: address,
						distance: selectedDist,
						nonce: '<?php echo wp_create_nonce("o100_entry_nonce"); ?>'
					};

					$.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(response) {
						if (response.success) {
							$('body').removeClass('o100-modal-open');
							$modal.fadeOut(200);
						} else {
							$btn.prop('disabled', false).text('<?php esc_html_e( "START MY ORDER", "order100" ); ?>');
							alert('<?php esc_html_e( "Error saving preferences. Please try again.", "order100" ); ?>');
						}
					});
				});

				// Close modal
				$('.o100-modal-close').on('click', function() {
					$('body').removeClass('o100-modal-open');
					$modal.fadeOut(200);
					$.post('<?php echo admin_url("admin-ajax.php"); ?>', {
						action: 'o100_dismiss_entry_modal',
						nonce: '<?php echo wp_create_nonce("o100_entry_nonce"); ?>'
					});
				});
			});
		</script>
		<?php
	}

	public function ajax_dismiss_modal() {
		check_ajax_referer( 'o100_entry_nonce', 'nonce' );
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'o100_entry_dismissed', true );
		}
		wp_send_json_success();
	}

	public function ajax_save_selection() {
		check_ajax_referer( 'o100_entry_nonce', 'nonce' );

		$method = isset( $_POST['method'] ) ? sanitize_text_field( wp_unslash( $_POST['method'] ) ) : '';
		$location = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
		$address = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
		$distance = isset( $_POST['distance'] ) && is_numeric( $_POST['distance'] ) ? floatval( wp_unslash( $_POST['distance'] ) ) : '';

		// Force-create WC session cookie for guests so data persists across page loads
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		if ( ! empty( $method ) ) {
			// Map 'pickup' to 'takeaway' for legacy compatibility
			$legacy_method = ( $method === 'pickup' ) ? 'takeaway' : $method;
			WC()->session->set( '_o100_order_method', $method );
			WC()->session->set( '_user_order_method', $legacy_method );
		}

		if ( ! empty( $location ) ) {
			WC()->session->set( 'o100_location_id', intval( $location ) );
			// Keep legacy session vars for backward compat during transition
			WC()->session->set( 'ex_userloc', $location );
			WC()->session->set( '_user_deli_log', $location );
		}

		if ( ! empty( $address ) ) {
			WC()->session->set( '_user_deli_adress', $address );
			WC()->session->set( 'ex_useraddre', $address );
		}

		if ( $distance !== '' ) {
			WC()->session->set( '_user_distance', $distance );
		}

		// Mark modal as dismissed to prevent re-showing on page reload
		WC()->session->set( 'o100_entry_dismissed', true );

		wp_send_json_success();
	}
}

new O100_Entry_Modal();

