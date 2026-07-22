<?php
/**
 * Settings Main View (Alpine.js SPA Wrapper)
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = array(
	// ── Step 1: Store Identity ──
	'store_profile'    => array( 'title' => __( 'Profile', 'order100' ), 'icon' => 'dashicons-store' ),
	'store_hours'      => array( 'title' => __( 'Schedule', 'order100' ), 'icon' => 'dashicons-clock' ),
	'locations'        => array( 'title' => __( 'Branches', 'order100' ), 'icon' => 'dashicons-location-alt' ),

	// ── Step 3: Order Methods ──
	'delivery'         => array( 'title' => __( 'Delivery', 'order100' ), 'icon' => 'dashicons-car' ),
	'pickup'           => array( 'title' => __( 'Pickup', 'order100' ), 'icon' => 'dashicons-cart' ),

	// ── Step 4: Checkout & Polish ──
	'checkout_ext'     => array( 'title' => __( 'Tipping', 'order100' ), 'icon' => 'dashicons-money-alt' ),
	'ui_prefs'         => array( 'title' => __( 'Appearance', 'order100' ), 'icon' => 'dashicons-art' ),
	'portal'           => array( 'title' => __( 'Store Portal', 'order100' ), 'icon' => 'dashicons-layout' ),
	'api_integration'  => array( 'title' => __( 'Integrations', 'order100' ), 'icon' => 'dashicons-rest-api' ),
	'misc'             => array( 'title' => __( 'Misc', 'order100' ), 'icon' => 'dashicons-admin-generic' ),
);

$current_tab = isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $tabs ) ? sanitize_text_field( $_GET['tab'] ) : 'store_profile';
$nonce = wp_create_nonce( 'wp_rest' );
$rest_url = esc_url_raw( rest_url( 'o100/v1/settings/' ) );

$rest_tabs = array( 'store_profile', 'checkout_ext', 'delivery', 'pickup', 'portal', 'misc' );
$is_rest = in_array( $current_tab, $rest_tabs );

// Determine CMB2 or Custom Render
$is_cmb2 = false;
$custom_render = '';
if ( $current_tab === 'menu_builder' ) {
	$is_cmb2 = true;
} elseif ( $current_tab === 'store_profile' ) {
	$custom_render = 'render_fluent_store_profile';
} elseif ( $current_tab === 'checkout_ext' ) {
	$custom_render = 'render_fluent_checkout_ext';
} elseif ( $current_tab === 'delivery' ) {
	$custom_render = 'render_fluent_delivery';
} elseif ( $current_tab === 'pickup' ) {
	$custom_render = 'render_fluent_pickup';
} elseif ( $current_tab === 'portal' ) {
	$custom_render = 'render_fluent_store_portal';
} elseif ( $current_tab === 'misc' ) {
	$custom_render = 'render_fluent_misc';
} else {
	$is_cmb2 = true;
}
?>

<style>
[x-cloak] { display: none !important; }
</style>
<div class="wrap o100-wrap" x-data="o100SettingsApp()">
	<?php $this->render_page_header(); ?>
	
	<div class="o100-fluent-container">
		<!-- Fluent Sidebar -->
		<div class="o100-fluent-sidebar">
			<ul class="o100-fluent-nav">
				<?php foreach ( $tabs as $tab_id => $tab_data ) : ?>
					<li>
						<a href="?page=o100-settings&tab=<?php echo esc_attr( $tab_id ); ?>" 
						   @click="handleTabClick($event, '<?php echo esc_attr( $tab_id ); ?>')"
						   class="<?php echo $current_tab === $tab_id ? 'active' : ''; ?>">
							<span class="dashicons <?php echo esc_attr( $tab_data['icon'] ); ?>"></span>
							<span class="o100-nav-text"><?php echo esc_html( $tab_data['title'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		
		<!-- Fluent Content -->
		<div class="o100-fluent-content">
			<div class="o100-fluent-header" :class="{ 'is-stuck': isStuck }">
				<h2><?php echo esc_html( $tabs[ $current_tab ]['title'] ); ?></h2>
				<div class="o100-fluent-header-actions" style="display:flex; align-items:center; gap:16px;">
					<?php if ( $current_tab !== 'menu_builder' ) : ?>
					<button type="button" 
							class="o100-fluent-top-save" 
							:class="{ 'o100-save-disabled': !isDirty && !isSaving, 'loading': isSaving }"
							@click="saveSettings()">
						<span x-cloak x-show="!isSaving"><?php esc_html_e( 'Save Settings', 'order100' ); ?></span>
						<span x-cloak x-show="isSaving"><?php esc_html_e( 'Saving...', 'order100' ); ?></span>
					</button>
					<?php endif; ?>
				</div>
			</div>
			<div class="o100-fluent-form-wrapper" @input="markDirty()" @change="markDirty()" @irischange="markDirty()" @click="handleInteraction($event)">
				<?php 
				if ( $custom_render && method_exists( 'O100_Settings', $custom_render ) ) {
					// REST-based Forms
					O100_Settings::$custom_render();
				} elseif ( $is_cmb2 ) {
					// Traditional CMB2 POST Forms
					if ( $current_tab === 'menu_builder' ) {
						cmb2_metabox_form( "o100_{$current_tab}", "o100_{$current_tab}" ); 
					} else {
						cmb2_metabox_form( "o100_{$current_tab}", "o100_{$current_tab}", array(
							'save_button' => __( 'Save Settings', 'order100' ),
							'form_format' => '<form class="cmb-form" method="post" id="%1$s" enctype="multipart/form-data" encoding="multipart/form-data"><input type="hidden" name="object_id" value="%2$s"><input type="hidden" name="submit-cmb" value="1">%3$s<input type="submit" name="submit-cmb-btn" value="%4$s" class="button-primary" style="display:none;"></form>',
						) );
					}
				}
				?>
			</div>
		</div>
	</div>

	<!-- Unsaved Changes Modal -->
	<div id="o100-unsaved-overlay" x-show="showUnsavedModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:999998; backdrop-filter:blur(4px);">
		<div id="o100-unsaved-modal" @click.away="showUnsavedModal = false" style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:16px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); padding:32px; width:420px; max-width:90vw; z-index:999999;">
			<div style="text-align:center; margin-bottom:20px;">
				<div style="width:48px; height:48px; border-radius:50%; background:#fef3c7; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
					<span class="dashicons dashicons-warning" style="color:#f59e0b; font-size:24px; width:24px; height:24px;"></span>
				</div>
				<h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;">Unsaved Changes</h3>
				<p style="margin:0; font-size:14px; color:#64748b; line-height:1.5;">You have unsaved changes on this page.<br>What would you like to do?</p>
			</div>
			<div style="display:flex; gap:10px; justify-content:center;">
				<button @click="showUnsavedModal = false" style="padding:10px 20px; border:1px solid #e2e8f0; background:#fff; color:#475569; font-size:14px; font-weight:600; border-radius:8px; cursor:pointer;">Cancel</button>
				<button @click="discardAndGo()" style="padding:10px 20px; border:1px solid #fca5a5; background:#fef2f2; color:#dc2626; font-size:14px; font-weight:600; border-radius:8px; cursor:pointer;">Discard</button>
				<button @click="saveAndGo()" style="padding:10px 20px; border:none; background:#F59322; color:#fff; font-size:14px; font-weight:600; border-radius:8px; cursor:pointer;">Save & Go</button>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('alpine:init', () => {
	Alpine.data('o100SettingsApp', () => ({
		activeTab: '<?php echo esc_js($current_tab); ?>',
		isRestTab: <?php echo $is_rest ? 'true' : 'false'; ?>,
		isDirty: false,
		isSaving: false,
		isStuck: false,
		showUnsavedModal: false,
		pendingUrl: '',
		
		init() {
			
			// Intercept all link clicks on the page to use custom modal instead of native dialog
			document.addEventListener('click', (e) => {
				const link = e.target.closest('a');
				if (!link) return;
				const href = link.getAttribute('href');
				if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.getAttribute('target') === '_blank') return;
				
				// Don't intercept if it's one of our own side tabs (handled by handleTabClick)
				// Actually handleTabClick uses @click on the a tag, which fires first or later depending on bubbling.
				// We can just let this intercept it if isDirty is true.
				if (this.isDirty && !link.closest('.o100-nav-list')) {
					e.preventDefault();
					this.pendingUrl = href;
					this.showUnsavedModal = true;
				}
			});

			window.addEventListener('scroll', () => {
				const header = document.querySelector('.o100-fluent-header');
				if (header) {
					this.isStuck = header.getBoundingClientRect().top <= 33;
				}
			});

			// Re-initialize CMB2 / other scripts if they exist on standard forms
			if (typeof jQuery !== 'undefined') {
				jQuery('.o100-fluent-content').on('cmb_media_modal_select cmb2_add_row cmb2_remove_row cmb2_shift_rows_complete', () => this.markDirty());
			}
		},
		
		markDirty() {
			this.isDirty = true;
		},

		handleInteraction(e) {
			const target = e.target;
			if (target.closest('.wp-color-result, .cmb2-upload-button, .cmb-remove-row-button, .cmb-add-row-button, .cmb2-checkbox label, input[type="checkbox"], input[type="radio"]')) {
				this.markDirty();
			}
		},

		handleTabClick(e, tabId) {
			if (this.isDirty) {
				e.preventDefault();
				this.pendingUrl = e.currentTarget.href;
				this.showUnsavedModal = true;
			}
		},

		discardAndGo() {
			if (this.pendingUrl) {
				window.onbeforeunload = null; if(typeof jQuery !== 'undefined') jQuery(window).off('beforeunload.edit-post'); window.location.href = this.pendingUrl;
			}
		},

		async saveAndGo() {
			await this.saveSettings();
			if (!this.isDirty && this.pendingUrl) {
				window.onbeforeunload = null; if(typeof jQuery !== 'undefined') jQuery(window).off('beforeunload.edit-post'); window.location.href = this.pendingUrl;
			}
		},
		
		async saveSettings() {
			if (this.isSaving || (!this.isDirty && this.isRestTab)) return;
			this.isSaving = true;
			
			if (this.isRestTab) {
				try {
					let form = document.querySelector('.o100-fluent-form-wrapper form');
					if (!form) {
						this.isSaving = false;
						return;
					}
					let formData = new FormData(form);
					let payload = {};
					
					for (let [key, value] of formData.entries()) {
						if (!key.includes('[')) {
							payload[key] = value;
							continue;
						}
						
						let keys = key.split(/\[([^\]]*)\]/).filter(k => k !== "");
						let current = payload;
						for (let i = 0; i < keys.length; i++) {
							let k = keys[i];
							if (i === keys.length - 1) {
								if (key.endsWith('[]')) {
									if (!Array.isArray(current[k])) current[k] = [];
									current[k].push(value);
								} else {
									current[k] = value;
								}
							} else {
								if (!current[k]) {
									current[k] = !isNaN(keys[i+1]) ? [] : {};
								}
								current = current[k];
							}
						}
					}
					
					let group = this.activeTab;
					if (group === 'store_profile') group = 'profile';

					let response = await fetch('<?php echo $rest_url; ?>' + group, {
						method: 'PATCH',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': '<?php echo $nonce; ?>'
						},
						body: JSON.stringify(payload)
					});
					
					let data = await response.json();
					
					if (response.ok) {
						this.isDirty = false;
						if (window.o100ShowToast) {
							window.o100ShowToast('success', data.message || 'Settings saved successfully');
						} else {
							// Fallback if toast not present
							alert(data.message || 'Settings saved successfully');
						}
					} else {
						alert('Error: ' + (data.message || 'Unknown error'));
					}
				} catch (error) {
					console.error(error);
					alert('Connection error occurred');
				} finally {
					this.isSaving = false;
				}
			} else {
				// CMB2 Form Submission via AJAX to avoid full page refresh
				let cmbForm = document.querySelector('.cmb-form');
				if (cmbForm) {
					try {
						let formData = new FormData(cmbForm);
						// When submitting via fetch, the submit button's value is not included automatically.
						// CMB2 often looks for 'submit-cmb' to process the save.
						if (!formData.has('submit-cmb')) {
							formData.append('submit-cmb', '1');
						}

						let response = await fetch(window.location.href, {
							method: 'POST',
							body: formData
						});
						
						if (response.ok) {
							this.isDirty = false;
							if (window.o100ShowToast) {
								window.o100ShowToast('success', 'Settings saved successfully');
							} else {
								alert('Settings saved successfully');
							}
						} else {
							alert('Error saving settings. Please try again.');
						}
					} catch (error) {
						console.error(error);
						alert('Connection error occurred');
					} finally {
						this.isSaving = false;
					}
				} else {
					this.isSaving = false;
				}
			}
		}
	}));
});
</script>
