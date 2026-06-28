<?php
/**
 * Frontend View: Reservation Form Widget (Alpine.js)
 *
 * Included by O100_Reservation::render_form_html()
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
	.o100-resv-widget {
		/* Let theme handle background, borders, and typography */
	}
	.o100-resv-widget * { box-sizing: border-box; }
	.o100-resv-widget h2 { margin: 0 0 1.5em; }
	.o100-resv-grid { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 1.5em; }
	.o100-resv-col-full { width: 100%; }
	.o100-resv-col-half { width: calc(50% - 8px); }
	.o100-resv-col-third { width: calc(33.333% - 10.66px); }
	@media(max-width: 600px) {
		.o100-resv-col-half, .o100-resv-col-third { width: 100%; }
	}
	.o100-resv-field { display: flex; flex-direction: column; margin-bottom: 1em; }
	.o100-resv-label { font-weight: 600; margin-bottom: 6px; }
	.o100-resv-req { color: #ef4444; margin-left: 2px; }
	.o100-resv-input {
		width: 100%; box-sizing: border-box;
	}
	.o100-resv-input.has-error { border-color: #ef4444; }
	.o100-resv-select {
		/* Minimal select structural fix if needed, but let theme handle appearance */
	}
	.o100-resv-error-text { color: #ef4444; font-size: 0.85em; margin-top: 4px; }
	.o100-resv-phone-group { display: flex; gap: 8px; }
	.o100-resv-phone-code { width: 110px; flex-shrink: 0; }
	.o100-resv-private-prompt { background: rgba(253, 230, 138, 0.2); border: 1px dashed #fde68a; border-radius: 4px; padding: 1em; margin: 0.5em 0; }
	.o100-resv-private-prompt p { margin: 0 0 10px; font-weight: 500; }
	.o100-resv-private-prompt label { display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer; }
</style>

<div class="o100-resv-widget" x-data="o100ReservationForm()">
	<h2><?php esc_html_e( 'Make a Reservation', 'order100' ); ?></h2>

	<div x-show="alert.show" :class="{'woocommerce-message': alert.type === 'success', 'woocommerce-error': alert.type === 'error'}" style="display:none; margin-bottom: 20px;" x-html="alert.message"></div>

	<form @submit.prevent="submitForm" x-show="!isSuccess" class="o100-resv-grid">
		<input type="hidden" name="o100_hp_website" value="">
		<input type="hidden" name="o100_bot_token" value="<?php echo esc_attr( $bot_token ); ?>">

		<?php foreach ( $fields as $f ) :
			if ( empty( $f['enabled'] ) ) continue;
			$w = 'o100-resv-col-half';
			if ( isset( $f['width'] ) ) {
				if ( $f['width'] === 'full' ) $w = 'o100-resv-col-full';
				elseif ( $f['width'] === 'third' ) $w = 'o100-resv-col-third';
			}
			$req = ! empty( $f['required'] ) ? ' required' : '';
			$fid = esc_attr( $f['id'] );
			$model = "formData.{$fid}";
		?>
		<div class="o100-resv-field <?php echo $w; ?>">
			<label class="o100-resv-label"><?php echo esc_html( $f['label'] ); ?><?php if( $req ) echo '<span class="o100-resv-req">*</span>'; ?></label>
			
			<?php if ( $f['type'] === 'textarea' ) : ?>
				<textarea class="o100-resv-input" x-model="<?php echo $model; ?>" name="<?php echo $fid; ?>" placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>" rows="3" <?php echo $req; ?>></textarea>
			
			<?php elseif ( $f['type'] === 'email' ) : ?>
				<input type="email" class="o100-resv-input" x-model="<?php echo $model; ?>" name="<?php echo $fid; ?>" placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>" <?php echo $req; ?>>
			
			<?php elseif ( $f['type'] === 'tel' ) : ?>
				<div class="o100-resv-phone-group">
					<select class="o100-resv-input o100-resv-select o100-resv-phone-code" x-model="formData.<?php echo $fid; ?>_code" name="<?php echo $fid; ?>_code">
						<?php foreach ( $country_codes as $code => $lbl ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $default_phone_code ); ?>><?php echo esc_html( $lbl ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="tel" class="o100-resv-input" x-model="<?php echo $model; ?>" name="<?php echo $fid; ?>" placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>" pattern="^\d{6,15}$" <?php echo $req; ?>>
				</div>
			
			<?php elseif ( $f['type'] === 'branch' ) : ?>
				<select class="o100-resv-input o100-resv-select" x-model="<?php echo $model; ?>" @change="onBranchChange" name="<?php echo $fid; ?>" <?php echo $req; ?>>
					<option value=""><?php echo esc_html( $f['placeholder'] ?: __( 'Select a branch', 'order100' ) ); ?></option>
					<?php foreach ( $branches as $b ) : ?>
						<option value="<?php echo esc_attr( $b['id'] ); ?>"><?php echo esc_html( $b['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			
			<?php elseif ( $f['type'] === 'date' ) : ?>
				<input type="text" x-ref="datePicker" class="o100-resv-input" x-model="<?php echo $model; ?>" name="<?php echo $fid; ?>" placeholder="<?php echo esc_attr( $f['placeholder'] ?: __( 'Select date', 'order100' ) ); ?>" readonly <?php echo $req; ?>>
			
			<?php elseif ( $f['type'] === 'time' ) : ?>
				<select class="o100-resv-input o100-resv-select" x-model="<?php echo $model; ?>" name="<?php echo $fid; ?>" <?php echo $req; ?> :disabled="!availableSlots.length && !loadingSlots">
					<option value="" x-text="timePlaceholder"></option>
					<template x-for="slot in availableSlots" :key="slot.time">
						<option :value="slot.time" x-text="slot.label"></option>
					</template>
				</select>
			
			<?php elseif ( $f['type'] === 'occasion' ) : ?>
				<select class="o100-resv-input o100-resv-select" x-model="<?php echo $model; ?>" name="<?php echo $fid; ?>" <?php echo $req; ?>>
					<option value=""><?php esc_html_e( 'Select occasion (Optional)', 'order100' ); ?></option>
					<option value="Birthday"><?php esc_html_e( 'Birthday', 'order100' ); ?></option>
					<option value="Anniversary"><?php esc_html_e( 'Anniversary', 'order100' ); ?></option>
					<option value="Business"><?php esc_html_e( 'Business', 'order100' ); ?></option>
					<option value="Date Night"><?php esc_html_e( 'Date Night', 'order100' ); ?></option>
				</select>
			
			<?php elseif ( $f['type'] === 'number' ) : ?>
				<input type="number" class="o100-resv-input" x-model.number="<?php echo $model; ?>" name="<?php echo $fid; ?>" placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>" min="1" <?php echo $req; ?>>
			
			<?php else : ?>
				<input type="text" class="o100-resv-input" x-model="<?php echo $model; ?>" name="<?php echo $fid; ?>" placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>" <?php echo $req; ?>>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>

		<?php if ( $enable_rooms ) : ?>
		<div class="o100-resv-col-full" x-show="formData.party_size >= <?php echo $room_threshold; ?>" style="display:none;">
			<div class="o100-resv-private-prompt">
				<p>🎉 <?php esc_html_e( 'For large parties, we highly recommend booking a private room.', 'order100' ); ?></p>
				<label>
					<input type="checkbox" x-model="formData.request_private_room" name="request_private_room" value="1">
					<?php esc_html_e( 'Request a Private Dining Room', 'order100' ); ?>
				</label>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $terms_text ) ) : ?>
		<div class="o100-resv-col-full">
			<label style="display:flex; gap:10px; align-items:flex-start; font-size:13px; color:#6b7280;">
				<input type="checkbox" x-model="formData.o100_agree_terms" name="o100_agree_terms" required style="margin-top:2px;">
				<span><?php echo esc_html( $terms_text ); ?></span>
			</label>
		</div>
		<?php endif; ?>

		<div class="o100-resv-col-full">
			<button type="submit" class="button alt" style="width:100%;" :disabled="isSubmitting" x-text="isSubmitting ? '<?php echo esc_js( esc_html__( 'Submitting...', 'order100' ) ); ?>' : '<?php echo esc_js( esc_html__( 'Confirm Reservation', 'order100' ) ); ?>'"></button>
		</div>
	</form>

	<div style="margin-top:24px; padding-top:20px; border-top:1px solid #e5e7eb;">
		<?php if ( ! empty( $dining_info ) ) : ?>
			<h3 style="font-size:17px; font-weight:700; margin:0 0 10px;"><?php esc_html_e( 'Important dining information', 'order100' ); ?></h3>
			<p style="font-size:14px; color:#4b5563; line-height:1.7; margin:0 0 16px;"><?php echo nl2br( esc_html( $dining_info ) ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $restaurant_note ) ) : ?>
			<h3 style="font-size:17px; font-weight:700; margin:0 0 10px;"><?php esc_html_e( 'A note from the restaurant', 'order100' ); ?></h3>
			<p style="font-size:14px; color:#4b5563; line-height:1.7; margin:0 0 16px;"><?php echo nl2br( esc_html( $restaurant_note ) ); ?></p>
		<?php endif; ?>
	</div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
document.addEventListener('alpine:init', () => {
	Alpine.data('o100ReservationForm', () => ({
		formData: {
			branch: '',
			guest_name: '<?php echo esc_js( $default_guest_name ?? '' ); ?>',
			guest_email: '<?php echo esc_js( $default_guest_email ?? '' ); ?>',
			guest_phone_code: '<?php echo esc_js( $default_phone_code ); ?>',
			guest_phone: '<?php echo esc_js( $default_guest_phone ?? '' ); ?>',
			party_size: 2,
			reservation_date: '',
			reservation_time: '',
			occasion: '',
			special_requests: '',
			request_private_room: false,
			o100_agree_terms: false
		},
		alert: { show: false, type: '', message: '' },
		isSubmitting: false,
		isSuccess: false,
		availableSlots: [],
		loadingSlots: false,
		dateConfig: {},
		fpInstance: null,

		get timePlaceholder() {
			if (this.loadingSlots) return '<?php echo esc_js( esc_html__( 'Loading...', 'order100' ) ); ?>';
			if (!this.formData.reservation_date) return '<?php echo esc_js( esc_html__( 'Select a date first', 'order100' ) ); ?>';
			if (this.availableSlots.length === 0) return '<?php echo esc_js( esc_html__( 'No slots available', 'order100' ) ); ?>';
			return '<?php echo esc_js( esc_html__( 'Select time', 'order100' ) ); ?>';
		},

		init() {
			this.$watch('formData.reservation_date', value => {
				if (value) this.loadSlots(value);
			});
			this.$nextTick(() => {
				this.fetchDates();
			});
		},

		onBranchChange() {
			this.formData.reservation_date = '';
			this.formData.reservation_time = '';
			this.availableSlots = [];
			this.fetchDates();
		},

		fetchDates() {
			const bid = this.formData.branch || '';
			fetch(`/wp-json/o100/v1/reservations/available-dates?branch_id=${bid}`)
				.then(r => r.json())
				.then(r => {
					if (r && r.dates) {
						this.dateConfig = r.dates;
						this.initDatePicker();
					}
				})
				.catch(e => {
					console.error('Failed to load dates', e);
					this.dateConfig = { min_date: 'today', disabled_weekdays: [], disabled_dates: [] };
					this.initDatePicker();
				});
		},

		initDatePicker() {
			const el = this.$refs.datePicker;
			if (!el) return;
			const config = this.dateConfig;
			
			if (this.fpInstance) {
				this.fpInstance.destroy();
			}

			this.fpInstance = flatpickr(el, {
				dateFormat: 'Y-m-d',
				minDate: config.min_date || 'today',
				maxDate: config.max_date || null,
				disable: [
					(date) => {
						const wd = date.getDay();
						if ((config.disabled_weekdays || []).includes(wd)) return true;
						const ds = date.getFullYear() + '-' + String(date.getMonth()+1).padStart(2,'0') + '-' + String(date.getDate()).padStart(2,'0');
						return (config.disabled_dates || []).includes(ds);
					}
				],
				onChange: (sel, dateStr) => {
					this.formData.reservation_date = dateStr;
				}
			});
		},

		loadSlots(dateStr) {
			this.loadingSlots = true;
			this.availableSlots = [];
			this.formData.reservation_time = '';
			
			const bid = this.formData.branch || '';
			fetch(`/wp-json/o100/v1/reservations/available-slots?branch_id=${bid}&date=${encodeURIComponent(dateStr)}`)
				.then(r => r.json())
				.then(r => {
					if (r && r.data) {
						const isToday = (this.dateConfig.today && dateStr === this.dateConfig.today);
						const cutoff = this.dateConfig.lead_cutoff || '';
						
						this.availableSlots = r.data.filter(s => {
							if (isToday && cutoff && s.time < cutoff) return false;
							if (s.is_full || s.available === 0) return false;
							return true;
						});
					}
				})
				.finally(() => {
					this.loadingSlots = false;
				});
		},

		submitForm(e) {
			this.isSubmitting = true;
			this.alert.show = false;
			
			const fd = new FormData(e.target);
			
			fetch('/wp-json/o100/v1/reservations/submit', {
				method: 'POST',
				body: fd
			})
			.then(r => r.json())
			.then(r => {
				if (r.code === 'rest_no_route') {
					throw new Error('API Endpoint not found');
				}
				// Handle WP_Error payload
				if (r.code && r.message && r.data && r.data.status >= 400) {
					this.alert = { show: true, type: 'error', message: r.message };
					return;
				}
				// Normal success
				this.isSuccess = true;
				let statusIcon = (r.data && r.data.status === 'confirmed') ? '✅' : '⏳';
				let shopUrl = '/';
				this.alert = {
					show: true,
					type: 'success',
					message: `<div style="font-size:32px;margin-bottom:8px;">${statusIcon}</div>
						<p style="font-size:16px;font-weight:600;margin:0 0 8px;">${r.data?.message || 'Reservation submitted!'}</p>
						<p style="color:#475569;margin:0 0 16px;font-size:14px;"><?php echo esc_js( esc_html__( 'While you wait, why not browse our menu or pre-order online to skip the line?', 'order100' ) ); ?></p>
						<a href="${shopUrl}" style="display:inline-block;padding:12px 28px;background:var(--wp--preset--color--primary,#F59322);color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;"><?php echo esc_js( esc_html__( '🍽️ Browse Our Menu', 'order100' ) ); ?></a>`
				};
				window.scrollTo({top: this.$el.offsetTop - 100, behavior:'smooth'});
			})
			.catch(err => {
				console.error(err);
				this.alert = { show: true, type: 'error', message: '<?php echo esc_js( esc_html__( 'Network error. Please try again.', 'order100' ) ); ?>' };
			})
			.finally(() => {
				this.isSubmitting = false;
			});
		}
	}));
});
</script>
