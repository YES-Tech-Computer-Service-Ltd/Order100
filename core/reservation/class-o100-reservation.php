<?php
/**
 * Reservation Module Controller
 *
 * Central bootstrap for the Reservation feature.
 * Loads sub-modules and registers hooks.
 *
 * @package Order100
 * @since   1.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Reservation {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Get singleton
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — load files and hook into WP
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Load module files
	 */
	private function includes() {
		$dir = O100_PATH . 'core/reservation/';

		require_once $dir . 'class-o100-reservation-db.php';
		require_once $dir . 'class-o100-reservation-admin.php';

		// These will be created in Phase 2 & 3
		if ( file_exists( $dir . 'class-o100-reservation-form.php' ) ) {
			require_once $dir . 'class-o100-reservation-form.php';
		}
		if ( file_exists( $dir . 'class-o100-reservation-notify.php' ) ) {
			require_once $dir . 'class-o100-reservation-notify.php';
		}
		if ( file_exists( $dir . 'class-o100-reservation-api.php' ) ) {
			require_once $dir . 'class-o100-reservation-api.php';
		}
	}

	/**
	 * Register WordPress hooks
	 */
	private function init_hooks() {
		// Ensure table exists on every admin load (lightweight check)
		add_action( 'admin_init', array( $this, 'maybe_create_table' ) );

		// Initialize admin list page
		if ( is_admin() ) {
			O100_Reservation_Admin::instance();
		}

		// Form preview handler
		add_action( 'template_redirect', array( $this, 'handle_preview' ) );

		// Shortcode: [o100_reservation]
		add_shortcode( 'o100_reservation', array( $this, 'shortcode_handler' ) );

		// AJAX: available dates & timeslots
		add_action( 'wp_ajax_o100_resv_get_available_dates', array( $this, 'ajax_available_dates' ) );
		add_action( 'wp_ajax_nopriv_o100_resv_get_available_dates', array( $this, 'ajax_available_dates' ) );
		add_action( 'wp_ajax_o100_resv_get_available_slots', array( $this, 'ajax_available_slots' ) );
		add_action( 'wp_ajax_nopriv_o100_resv_get_available_slots', array( $this, 'ajax_available_slots' ) );

		// AJAX: form submission
		add_action( 'wp_ajax_o100_resv_submit', array( $this, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_o100_resv_submit', array( $this, 'ajax_submit' ) );

		// Reminder cron
		add_action( 'o100_reservation_send_reminders', array( $this, 'cron_send_reminders' ) );
		if ( ! wp_next_scheduled( 'o100_reservation_send_reminders' ) ) {
			wp_schedule_event( time(), 'hourly', 'o100_reservation_send_reminders' );
		}
	}

	/**
	 * Create the DB table if it doesn't exist.
	 * Uses a version check stored in options to avoid running dbDelta on every load.
	 */
	public function maybe_create_table() {
		$db_version = get_option( 'o100_reservation_db_version', '0' );
		if ( version_compare( $db_version, '1.0', '<' ) ) {
			O100_Reservation_DB::create_table();
			update_option( 'o100_reservation_db_version', '1.0' );
		}
	}

	/**
	 * Check if reservation feature is enabled
	 */
	public static function is_enabled() {
		$settings = get_option( 'o100_reservation', array() );
		return ! empty( $settings['o100_enable_reservation'] ) && $settings['o100_enable_reservation'] === 'on';
	}

	/**
	 * Get reservation settings with defaults
	 */
	public static function get_settings() {
		$settings = get_option( 'o100_reservation', array() );

		return wp_parse_args( $settings, array(
			'o100_enable_reservation'    => '',
			'o100_resv_confirmation'     => 'auto',
			'o100_resv_max_party'        => 10,
			'o100_resv_lead_time'        => 2,
			'o100_resv_max_advance'      => 30,
			'o100_resv_max_per_slot'     => 5,
			'o100_resv_reminder_hours'   => 2,
			'o100_resv_success_msg'      => __( 'Thank you! Your reservation has been received. We will contact you shortly to confirm.', 'order100' ),
			'o100_resv_schedule_mode'    => 'global',
			'o100_resv_custom_schedule'  => array(),
			'o100_resv_enable_rooms'     => '',
			'o100_resv_rooms'            => array(),
			'o100_resv_admin_email'      => get_option( 'admin_email' ),
		) );
	}

	/**
	 * Handle reservation form preview
	 */
	public function handle_preview() {
		$mode = isset( $_GET['o100_resv_preview'] ) ? sanitize_text_field( $_GET['o100_resv_preview'] ) : '';
		if ( ! $mode || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( $mode === 'blank' ) {
			$this->render_blank_preview();
		} elseif ( $mode === 'theme' ) {
			$this->render_theme_preview();
		}
		exit;
	}

	/**
	 * Render blank page preview
	 */
	private function render_blank_preview() {
		$form_html = $this->render_form_html();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Reservation Form Preview', 'order100' ); ?></title>
			<style>
				* { margin:0; padding:0; box-sizing:border-box; }
				body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background:#f3f4f6; min-height:100vh; display:flex; flex-direction:column; align-items:center; padding:40px 20px; }
				.o100-preview-banner { background:#1e293b; color:#e2e8f0; padding:10px 20px; font-size:13px; display:flex; align-items:center; gap:8px; position:fixed; top:0; left:0; right:0; z-index:999; justify-content:center; }
				.o100-preview-banner strong { color:#fbbf24; }
				.o100-preview-container { max-width:680px; width:100%; margin-top:40px; }
			</style>
		</head>
		<body>
			<div class="o100-preview-banner">
				<strong>⚡ <?php esc_html_e( 'Preview Mode', 'order100' ); ?></strong>
				— <?php esc_html_e( 'This is how your reservation form will look.', 'order100' ); ?>
			</div>
			<div class="o100-preview-container">
				<?php echo $form_html; ?>
			</div>
		</body>
		</html>
		<?php
	}

	/**
	 * Render theme page preview
	 */
	private function render_theme_preview() {
		// Use the theme's page template
		add_filter( 'the_content', function() {
			return $this->render_form_html();
		});

		// Create a fake WP_Query for a page
		global $wp_query, $post;
		$post = new WP_Post( (object) array(
			'ID'             => 0,
			'post_title'     => __( 'Reservation Form Preview', 'order100' ),
			'post_content'   => '',
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_name'      => 'o100-reservation-preview',
			'filter'         => 'raw',
		) );
		$wp_query->posts       = array( $post );
		$wp_query->post        = $post;
		$wp_query->post_count  = 1;
		$wp_query->found_posts = 1;
		$wp_query->is_page     = true;
		$wp_query->is_singular = true;
		$wp_query->is_404      = false;

		// Load the theme's page template
		$template = get_page_template();
		if ( ! $template ) {
			$template = get_singular_template();
		}
		if ( ! $template ) {
			$template = get_index_template();
		}
		if ( $template ) {
			include $template;
		}
	}

	/**
	 * Helper: Check if a CMB2 openclose schedule value represents an "open" day.
	 * CMB2 stores closed days as string "closed" or as array with empty open/close values.
	 *
	 * @param mixed $val  The schedule value from options/meta.
	 * @return bool True if the day has valid operating hours.
	 */
	private static function is_schedule_day_open( $val ) {
		// String values like "closed" or empty
		if ( ! is_array( $val ) || empty( $val ) ) return false;

		// Format 1: Flat associative {'open':'09:00','close':'21:00'}
		if ( isset( $val['open'] ) || isset( $val['close'] ) || isset( $val['start'] ) || isset( $val['end'] ) ) {
			$open  = isset( $val['open'] ) ? $val['open'] : ( isset( $val['start'] ) ? $val['start'] : '' );
			$close = isset( $val['close'] ) ? $val['close'] : ( isset( $val['end'] ) ? $val['end'] : '' );
			return ! empty( $open ) && ! empty( $close );
		}

		// Format 2: Nested array of slots [{'open':'09:00','close':'12:00'}, {'open':'13:00','close':'21:00'}]
		foreach ( $val as $slot ) {
			if ( ! is_array( $slot ) ) continue;
			$open  = isset( $slot['open'] ) ? $slot['open'] : ( isset( $slot['start'] ) ? $slot['start'] : '' );
			$close = isset( $slot['close'] ) ? $slot['close'] : ( isset( $slot['end'] ) ? $slot['end'] : '' );
			if ( ! empty( $open ) && ! empty( $close ) ) return true;
		}

		// Format 3: Non-empty array without recognizable keys = treat as open
		// (safety fallback for unknown CMB2 formats)
		return count( $val ) > 0 && is_array( reset( $val ) );
	}

	/**
	 * AJAX: Get available dates for reservation
	 */
	public function ajax_available_dates() {
		$settings   = self::get_settings();
		$branch_id  = isset( $_POST['branch_id'] ) ? intval( $_POST['branch_id'] ) : 0;
		$lead_hours = isset( $settings['o100_resv_lead_time'] ) ? intval( $settings['o100_resv_lead_time'] ) : 2;
		$max_days   = isset( $settings['o100_resv_max_advance'] ) ? intval( $settings['o100_resv_max_advance'] ) : 30;

		$now      = current_time( 'timestamp' );
		$min_date = date( 'Y-m-d', $now ); // Today is always selectable
		$max_date = date( 'Y-m-d', $now + ( $max_days * 86400 ) );
		$lead_cutoff = date( 'H:i', $now + ( $lead_hours * 3600 ) ); // For timeslot filtering

		$days_map = array( 'Sun' => 0, 'Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6 );
		$disabled_weekdays = array();
		$store_opts = get_option( 'o100_store_hours', array() );

		// Determine schedule source
		$schedule_source = null; // null = use global

		if ( $branch_id ) {
			$override = get_post_meta( $branch_id, 'o100_override_hours', true );
			if ( $override === 'yes' || $override === 'on' || $override === '1' ) {
				$branch_hours = get_post_meta( $branch_id, 'o100_hours', true );
				if ( is_array( $branch_hours ) ) {
					$schedule_source = 'branch';
				}
			}
		}

		foreach ( $days_map as $day_name => $day_num ) {
			$is_open = false;

			if ( $schedule_source === 'branch' ) {
				// Branch override: check branch hours array
				$is_open = ! empty( $branch_hours[ $day_name ] ) && is_array( $branch_hours[ $day_name ] );
				// Validate that at least one slot has valid times
				if ( $is_open ) {
					$has_valid = false;
					foreach ( $branch_hours[ $day_name ] as $slot ) {
						if ( ! is_array( $slot ) ) continue;
						$o = isset( $slot['start'] ) ? $slot['start'] : ( isset( $slot['open'] ) ? $slot['open'] : '' );
						$c = isset( $slot['end'] ) ? $slot['end'] : ( isset( $slot['close'] ) ? $slot['close'] : '' );
						if ( ! empty( $o ) && ! empty( $c ) ) { $has_valid = true; break; }
					}
					$is_open = $has_valid;
				}
			} else {
				// Global: use global schedule (NOT reservation override — reservation schedule is for timeslot generation only)
				$global_key = "o100_{$day_name}_opcl_time";
				$val = isset( $store_opts[ $global_key ] ) ? $store_opts[ $global_key ] : '';
				$is_open = self::is_schedule_day_open( $val );
			}

			if ( ! $is_open ) {
				$disabled_weekdays[] = $day_num;
			}
		}

		// Get holiday dates
		$disabled_dates = array();
		if ( class_exists( 'O100_Settings' ) ) {
			$holidays = O100_Settings::get_formatted_holidays();
			foreach ( $holidays as $h ) {
				if ( empty( $h['opcl_start'] ) ) continue;
				$end = ! empty( $h['opcl_end'] ) ? $h['opcl_end'] : $h['opcl_start'];
				$cur = strtotime( date( 'Y-m-d', $h['opcl_start'] ) );
				$end_day = strtotime( date( 'Y-m-d', $end ) );
				while ( $cur <= $end_day ) {
					$disabled_dates[] = date( 'Y-m-d', $cur );
					$cur += 86400;
				}
			}
		}

		wp_send_json_success( array(
			'min_date'          => $min_date,
			'max_date'          => $max_date,
			'today'             => date( 'Y-m-d', $now ),
			'lead_cutoff'       => $lead_cutoff,
			'disabled_dates'    => array_unique( $disabled_dates ),
			'disabled_weekdays' => $disabled_weekdays,
		) );
	}

	/**
	 * AJAX: Get available timeslots for a given date
	 */
	public function ajax_available_slots() {
		$settings  = self::get_settings();
		$branch_id = isset( $_POST['branch_id'] ) ? intval( $_POST['branch_id'] ) : 0;
		$date      = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
		$max_per   = isset( $settings['o100_resv_max_per_slot'] ) ? intval( $settings['o100_resv_max_per_slot'] ) : 5;

		if ( empty( $date ) ) {
			wp_send_json_error( 'No date provided' );
		}

		$day_name  = date( 'D', strtotime( $date ) ); // Mon, Tue, Wed, etc.
		$store_opts = get_option( 'o100_store_hours', array() );

		// ── Step 1: Try to find pre-generated timeslots ──
		// Structure: { "Mon": [{"start-time":"09:30","end-time":"10:00","max-odts":4}, ...], ... }
		$slots_data = array();

		// 1a) Branch generated timeslots
		if ( $branch_id ) {
			$raw = get_post_meta( $branch_id, 'o100_generated_timeslots', true );
			$decoded = self::decode_timeslot_json( $raw );
			if ( ! empty( $decoded[ $day_name ] ) ) {
				$slots_data = $decoded;
			}
		}

		// 1b) Global generated timeslots (try global first — it has the full data)
		if ( empty( $slots_data[ $day_name ] ) ) {
			$raw = isset( $store_opts['o100_global_generated_timeslots'] )
				? $store_opts['o100_global_generated_timeslots'] : '';
			$decoded = self::decode_timeslot_json( $raw );
			if ( ! empty( $decoded[ $day_name ] ) ) {
				$slots_data = $decoded;
			}
		}

		// ── Step 2: No generated timeslots — build dynamically from schedule hours ──
		if ( empty( $slots_data[ $day_name ] ) ) {
			$hours    = null;
			$interval = 30;
			$default_max = $max_per;

			// Try branch hours first (if override is on)
			if ( $branch_id ) {
				$override = get_post_meta( $branch_id, 'o100_override_hours', true );
				if ( $override === 'yes' || $override === 'on' || $override === '1' ) {
					$hours = get_post_meta( $branch_id, 'o100_hours', true );
					$b_int = get_post_meta( $branch_id, 'o100_interval', true );
					$b_max = get_post_meta( $branch_id, 'o100_max_order', true );
					if ( $b_int ) $interval = intval( $b_int );
					if ( $b_max ) $default_max = intval( $b_max );
				}
			}

			// Fallback to global schedule
			if ( empty( $hours ) || ! is_array( $hours ) || empty( $hours[ $day_name ] ) ) {
				$global_hours = array();
				foreach ( array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ) as $wd ) {
					$key = "o100_{$wd}_opcl_time";
					if ( isset( $store_opts[ $key ] ) && is_array( $store_opts[ $key ] ) ) {
						$global_hours[ $wd ] = $store_opts[ $key ];
					}
				}
				$hours = $global_hours;
				$g_int = isset( $store_opts['o100_global_interval'] ) ? intval( $store_opts['o100_global_interval'] ) : 0;
				$g_max = isset( $store_opts['o100_global_max_order'] ) ? intval( $store_opts['o100_global_max_order'] ) : 0;
				if ( $g_int > 0 ) $interval = $g_int;
				if ( $g_max > 0 ) $default_max = $g_max;
			}

			// Build timeslots from schedule
			if ( is_array( $hours ) && ! empty( $hours[ $day_name ] ) && is_array( $hours[ $day_name ] ) ) {
				$day_built = array();
				foreach ( $hours[ $day_name ] as $range ) {
					$open  = isset( $range['start'] ) ? $range['start'] : ( isset( $range['open'] ) ? $range['open'] : '' );
					$close = isset( $range['end'] ) ? $range['end'] : ( isset( $range['close'] ) ? $range['close'] : '' );
					if ( empty( $open ) || empty( $close ) ) continue;
					$st = strtotime( $open );
					$et = strtotime( $close );
					if ( ! $st || ! $et || $st >= $et ) continue;
					$cur = $st;
					while ( $cur + ( $interval * 60 ) <= $et ) {
						$day_built[] = array(
							'start-time' => date( 'H:i', $cur ),
							'end-time'   => date( 'H:i', $cur + ( $interval * 60 ) ),
							'max-odts'   => $default_max,
						);
						$cur += ( $interval * 60 );
					}
				}
				if ( ! empty( $day_built ) ) {
					$slots_data[ $day_name ] = $day_built;
				}
			}
		}

		// ── Step 3: Build response ──
		$day_slots = isset( $slots_data[ $day_name ] ) && is_array( $slots_data[ $day_name ] )
			? $slots_data[ $day_name ] : array();

		$result = array();
		foreach ( $day_slots as $slot ) {
			$time = isset( $slot['start-time'] ) ? $slot['start-time'] : '';
			if ( empty( $time ) ) continue;

			$slot_max = isset( $slot['max-odts'] ) ? intval( $slot['max-odts'] ) : $max_per;

			$booked = 0;
			if ( class_exists( 'O100_Reservation_DB' ) ) {
				$booked = O100_Reservation_DB::get_slot_booking_count( $branch_id, $date, $time );
			}

			$available = max( 0, $slot_max - $booked );
			$result[] = array(
				'time'      => $time,
				'label'     => date( 'g:i A', strtotime( $time ) ),
				'available' => $available,
				'max'       => $slot_max,
				'is_full'   => $available <= 0,
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Helper: Decode a timeslot JSON string, returning empty array for invalid/empty data.
	 */
	private static function decode_timeslot_json( $raw ) {
		if ( empty( $raw ) ) return array();
		if ( is_array( $raw ) ) return $raw;
		if ( ! is_string( $raw ) ) return array();
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded ) ) return array();
		return $decoded;
	}

	/**
	 * Render the reservation form HTML from saved configuration
	 */
	public function render_form_html() {
		$settings = self::get_settings();
		$fields   = isset( $settings['o100_resv_form_fields'] ) ? $settings['o100_resv_form_fields'] : array();

		// Migrate legacy 'booking_type' to 'occasion'
		foreach ( $fields as &$f ) {
			if ( isset( $f['type'] ) && $f['type'] === 'booking_type' ) {
				$f['type'] = 'occasion';
				$f['id']   = 'occasion';
				$f['label'] = __( 'Occasion', 'order100' );
			}
		}
		unset( $f );

		// Get configurable texts
		$store_hours_opts = get_option( 'o100_store_hours', array() );
		$terms_text     = isset( $settings['o100_resv_terms_text'] ) ? $settings['o100_resv_terms_text'] : '';
		$dining_info    = isset( $settings['o100_resv_dining_info'] ) ? $settings['o100_resv_dining_info'] : '';
		$restaurant_note = isset( $settings['o100_resv_restaurant_note'] ) ? $settings['o100_resv_restaurant_note'] : '';
		$enable_rooms   = isset( $settings['o100_resv_enable_rooms'] ) && $settings['o100_resv_enable_rooms'] === 'on';
		$room_threshold = isset( $settings['o100_resv_private_room_threshold'] ) ? intval( $settings['o100_resv_private_room_threshold'] ) : 8;
		if ( empty( $terms_text ) ) $terms_text = isset( $store_hours_opts['o100_resv_terms_text'] ) ? $store_hours_opts['o100_resv_terms_text'] : '';
		if ( empty( $dining_info ) ) $dining_info = isset( $store_hours_opts['o100_resv_dining_info'] ) ? $store_hours_opts['o100_resv_dining_info'] : '';
		if ( empty( $restaurant_note ) ) $restaurant_note = isset( $store_hours_opts['o100_resv_restaurant_note'] ) ? $store_hours_opts['o100_resv_restaurant_note'] : '';

		// Anti-bot token
		$bot_token = wp_hash( 'o100_resv_' . date('Ymd') );

		if ( empty( $fields ) ) {
			// Hardcoded defaults matching O100_Settings::get_default_form_fields()
			$fields = array(
				array( 'id' => 'branch', 'type' => 'branch', 'is_builtin' => true, 'enabled' => true, 'required' => true, 'label' => 'Branch', 'placeholder' => 'Select a branch', 'width' => 'full' ),
				array( 'id' => 'guest_name', 'type' => 'text', 'is_builtin' => true, 'enabled' => true, 'required' => true, 'label' => 'Your Name', 'placeholder' => 'Enter your name', 'width' => 'half' ),
				array( 'id' => 'guest_email', 'type' => 'email', 'is_builtin' => true, 'enabled' => true, 'required' => true, 'label' => 'Email', 'placeholder' => 'your@email.com', 'width' => 'half' ),
				array( 'id' => 'guest_phone', 'type' => 'tel', 'is_builtin' => true, 'enabled' => true, 'required' => true, 'label' => 'Phone', 'placeholder' => '(555) 123-4567', 'width' => 'half' ),
				array( 'id' => 'party_size', 'type' => 'number', 'is_builtin' => true, 'enabled' => true, 'required' => true, 'label' => 'Party Size', 'placeholder' => '2', 'width' => 'half' ),
				array( 'id' => 'reservation_date', 'type' => 'date', 'is_builtin' => true, 'enabled' => true, 'required' => true, 'label' => 'Date', 'placeholder' => '', 'width' => 'half' ),
				array( 'id' => 'reservation_time', 'type' => 'time', 'is_builtin' => true, 'enabled' => true, 'required' => true, 'label' => 'Time', 'placeholder' => '', 'width' => 'half' ),
				array( 'id' => 'booking_type', 'type' => 'booking_type', 'is_builtin' => true, 'enabled' => true, 'required' => false, 'label' => 'Seating Preference', 'placeholder' => '', 'width' => 'full' ),
				array( 'id' => 'special_requests', 'type' => 'textarea', 'is_builtin' => true, 'enabled' => true, 'required' => false, 'label' => 'Special Requests', 'placeholder' => 'Any dietary requirements or special needs...', 'width' => 'full' ),
			);
		}

		// Get branches for the branch field
		$branches = array();
		$loc_query = new WP_Query( array(
			'post_type'      => 'o100_location',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		if ( $loc_query->have_posts() ) {
			foreach ( $loc_query->posts as $loc ) {
				$branches[] = array(
					'id'   => $loc->ID,
					'name' => $loc->post_title,
				);
			}
			wp_reset_postdata();
		}

		// Get store phone code default
		$profile = get_option( 'o100_store_profile', array() );
		$default_phone_code = ! empty( $profile['o100_store_phone_code'] ) ? $profile['o100_store_phone_code'] : '+1';
		$country_codes = array(
			'+1'=>'🇺🇸/🇨🇦 +1','+44'=>'🇬🇧 +44','+86'=>'🇨🇳 +86','+61'=>'🇦🇺 +61','+81'=>'🇯🇵 +81','+33'=>'🇫🇷 +33',
			'+49'=>'🇩🇪 +49','+39'=>'🇮🇹 +39','+34'=>'🇪🇸 +34','+7'=>'🇷🇺 +7','+55'=>'🇧🇷 +55','+91'=>'🇮🇳 +91',
			'+82'=>'🇰🇷 +82','+52'=>'🇲🇽 +52','+65'=>'🇸🇬 +65','+66'=>'🇹🇭 +66','+84'=>'🇻🇳 +84','+971'=>'🇦🇪 +971',
			'+64'=>'🇳🇿 +64','+852'=>'🇭🇰 +852','+886'=>'🇹🇼 +886','+90'=>'🇹🇷 +90',
		);
		$ajax_url = admin_url( 'admin-ajax.php' );

		// Ensure branch is first
		$branch_first = array();
		$others = array();
		foreach ( $fields as $f ) {
			if ( isset( $f['type'] ) && $f['type'] === 'branch' ) {
				$branch_first[] = $f;
			} else {
				$others[] = $f;
			}
		}
		$fields = array_merge( $branch_first, $others );

		ob_start();
		?>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
		<style>
			.o100-resv-form { background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.08); padding:32px; border:1px solid #e5e7eb; }
			.o100-resv-form h2 { margin:0 0 24px; font-size:22px; font-weight:700; color:#111827; }
			.o100-resv-form-grid { display:flex; flex-wrap:wrap; gap:16px; }
			.o100-resv-field { display:flex; flex-direction:column; }
			.o100-resv-field.width-third { width:calc(33.333% - 10.66px); }
			.o100-resv-field.width-half { width:calc(50% - 8px); }
			.o100-resv-field.width-full { width:100%; }
			.o100-resv-field label { font-size:14px; font-weight:600; color:#374151; margin-bottom:6px; }
			.o100-resv-field label .o100-req { color:#ef4444; margin-left:2px; }
			.o100-resv-field input, .o100-resv-field select, .o100-resv-field textarea {
				width:100%; padding:10px 14px; border:1px solid #d1d5db; border-radius:8px;
				font-size:15px; color:#111827; background:#fff; transition:border-color 0.2s, box-shadow 0.2s;
				font-family:inherit; box-sizing:border-box;
			}
			.o100-resv-field input:focus, .o100-resv-field select:focus, .o100-resv-field textarea:focus {
				outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,0.1);
			}
			.o100-resv-field textarea { resize:vertical; min-height:80px; }
			.o100-resv-field select {
				-webkit-appearance:none; -moz-appearance:none; appearance:none;
				background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M2 4l4 4 4-4'/%3E%3C/svg%3E");
				background-repeat:no-repeat; background-position:right 12px center; background-size:12px;
				padding-right:32px;
			}
			.o100-resv-field .o100-phone-wrap { display:flex; gap:6px; }
			.o100-resv-field .o100-phone-wrap select { width:110px; flex:0 0 110px; font-size:13px; padding:10px 8px; }
			.o100-resv-field .o100-phone-wrap input { flex:1; }
			.o100-resv-field .o100-field-error { color:#ef4444; font-size:12px; margin-top:4px; display:none; }
			.o100-resv-field input.o100-invalid, .o100-resv-field select.o100-invalid { border-color:#ef4444; }
			.o100-resv-field .o100-check-wrap { display:flex; align-items:center; gap:8px; padding:10px 0; }
			.o100-resv-field .o100-check-wrap input[type="checkbox"] { width:18px; height:18px; accent-color:#6366f1; }
			.o100-resv-field .o100-slot-loading { color:#94a3b8; font-size:13px; padding:10px 14px; }
			.o100-resv-terms { display:flex; align-items:flex-start; gap:10px; padding:16px 0 8px; border-top:1px solid #e5e7eb; margin-top:8px; }
			.o100-resv-terms input[type="checkbox"] { width:18px; height:18px; margin-top:2px; flex-shrink:0; accent-color:#6366f1; }
			.o100-resv-terms span { font-size:13px; color:#6b7280; line-height:1.5; }
			.o100-resv-info-section { margin-top:24px; padding-top:20px; border-top:1px solid #e5e7eb; }
			.o100-resv-info-section h3 { font-size:17px; font-weight:700; color:#111827; margin:0 0 10px; }
			.o100-resv-info-section p { font-size:14px; color:#4b5563; line-height:1.7; margin:0 0 16px; }
			.o100-hp-field { opacity:0; position:absolute; top:0; left:0; height:0; width:0; z-index:-1; }
			.o100-resv-submit {
				margin-top:24px; width:100%; padding:14px; border:none; border-radius:8px;
				font-size:16px; font-weight:600; cursor:pointer; transition:background 0.2s;
				background: var(--wp--preset--color--primary, var(--theme-primary, #6366f1));
				color: var(--wp--preset--color--base, #fff);
			}
			.o100-resv-submit:hover { opacity:0.9; }
			.o100-resv-submit:disabled { opacity:0.5; cursor:not-allowed; }
			
			/* Private Room Prompt */
			.o100-private-room-prompt {
				background: #fffbeb;
				border: 1px solid #fde68a;
				border-radius: 8px;
				padding: 16px;
				margin-top: 8px;
				margin-bottom: 8px;
				width: 100%;
				display: none;
			}
			.o100-private-room-prompt p { margin: 0 0 10px; color: #b45309; font-size: 14px; font-weight: 500; }
			.o100-private-room-prompt label { display: flex; align-items: center; gap: 8px; font-weight: 600; color: #92400e; cursor: pointer; }
			.o100-private-room-prompt input[type="checkbox"] { width: auto; }

			@media (max-width:600px) {
				.o100-resv-field.width-third, .o100-resv-field.width-half { width:100%; }
				.o100-resv-form { padding:20px; }
			}
			.o100-resv-msg { padding:16px 20px; border-radius:8px; margin:12px 0; font-size:14px; line-height:1.5; }
			.o100-resv-msg--success { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
			.o100-resv-msg--error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
			.o100-resv-submit:disabled { opacity:0.5; cursor:not-allowed; }
		</style>
		<div class="o100-resv-form">
			<h2><?php esc_html_e( 'Make a Reservation', 'order100' ); ?></h2>
			<div id="o100-resv-msg" class="o100-resv-msg" style="display:none;"></div>
			<form id="o100-resv-form" class="o100-resv-form-grid">
				<?php wp_nonce_field( 'o100_resv_submit', 'o100_resv_nonce' ); ?>
			<?php foreach ( $fields as $f ) :
				if ( empty( $f['enabled'] ) ) continue;
			$w = 'width-half';
			if ( isset( $f['width'] ) ) {
				if ( $f['width'] === 'full' ) $w = 'width-full';
				elseif ( $f['width'] === 'third' ) $w = 'width-third';
			}
			$req = ! empty( $f['required'] ) ? ' required' : '';
				$req_star = ! empty( $f['required'] ) ? '<span class="o100-req">*</span>' : '';
				$ph = isset( $f['placeholder'] ) ? esc_attr( $f['placeholder'] ) : '';
				$label = esc_html( $f['label'] );
				$type = isset( $f['type'] ) ? $f['type'] : 'text';
				$fid = isset( $f['id'] ) ? esc_attr( $f['id'] ) : '';
			?>
				<div class="o100-resv-field <?php echo esc_attr( $w ); ?>">
					<label><?php echo $label . $req_star; ?></label>
					<?php
					switch ( $type ) {
						case 'textarea':
							echo "<textarea name=\"{$fid}\" placeholder=\"{$ph}\"{$req}></textarea>";
							break;
						case 'email':
							echo "<input type=\"email\" name=\"{$fid}\" id=\"o100-resv-email\" placeholder=\"{$ph}\"{$req}>";
							echo '<div class="o100-field-error" id="o100-email-error">' . esc_html__( 'Please enter a valid email address', 'order100' ) . '</div>';
							break;
						case 'tel':
							echo '<div class="o100-phone-wrap">';
							echo '<select name="' . $fid . '_code" id="o100-resv-phone-code">';
							foreach ( $country_codes as $code => $lbl ) {
								$sel = ( $code === $default_phone_code ) ? ' selected' : '';
								echo '<option value="' . esc_attr( $code ) . '"' . $sel . '>' . esc_html( $lbl ) . '</option>';
							}
							echo '</select>';
							echo "<input type=\"tel\" name=\"{$fid}\" id=\"o100-resv-phone\" placeholder=\"{$ph}\"{$req} pattern=\"^\\d{6,15}$\">";
							echo '</div>';
							echo '<div class="o100-field-error" id="o100-phone-error">' . esc_html__( 'Please enter a valid phone number (6-15 digits)', 'order100' ) . '</div>';
							break;
						case 'select':
						case 'dropdown':
							echo '<select name="' . $fid . '"' . $req . '><option value="">' . esc_html( $ph ?: __( 'Select...', 'order100' ) ) . '</option>';
							if ( ! empty( $f['options'] ) ) {
								foreach ( explode( ',', $f['options'] ) as $opt ) {
									$opt = trim( $opt );
									echo '<option value="' . esc_attr( $opt ) . '">' . esc_html( $opt ) . '</option>';
								}
							}
							echo '</select>';
							break;
						case 'branch':
							echo '<select name="' . $fid . '" id="o100-resv-branch"' . $req . '><option value="">' . esc_html( $ph ?: __( 'Select a branch', 'order100' ) ) . '</option>';
							foreach ( $branches as $b ) {
								echo '<option value="' . esc_attr( $b['id'] ) . '">' . esc_html( $b['name'] ) . '</option>';
							}
							echo '</select>';
							break;
						case 'occasion':
							echo '<select name="' . $fid . '"' . $req . '><option value="">' . esc_html__( 'Select occasion (Optional)', 'order100' ) . '</option>';
							echo '<option value="Birthday">' . esc_html__( 'Birthday', 'order100' ) . '</option>';
							echo '<option value="Anniversary">' . esc_html__( 'Anniversary', 'order100' ) . '</option>';
							echo '<option value="Business">' . esc_html__( 'Business', 'order100' ) . '</option>';
							echo '<option value="Date Night">' . esc_html__( 'Date Night', 'order100' ) . '</option>';
							echo '</select>';
							break;
						case 'checkbox':
							echo '<div class="o100-check-wrap"><input type="checkbox" name="' . $fid . '"' . $req . '><span>' . esc_html( $ph ?: $label ) . '</span></div>';
							break;
						case 'date':
							echo "<input type=\"text\" name=\"{$fid}\" id=\"o100-resv-date\" placeholder=\"" . esc_attr__( 'Select date', 'order100' ) . "\" readonly{$req}>";
							break;
						case 'time':
							echo '<select name="' . $fid . '" id="o100-resv-time"' . $req . '><option value="">' . esc_html__( 'Select a date first', 'order100' ) . '</option></select>';
							break;
						case 'number':
							echo "<input type=\"number\" name=\"{$fid}\" placeholder=\"{$ph}\"{$req} min=\"1\">";
							break;
						default:
							echo "<input type=\"{$type}\" name=\"{$fid}\" placeholder=\"{$ph}\"{$req}>";
							break;
					}
					?>
				</div>
			<?php endforeach; ?>

				<?php if ( $enable_rooms ) : ?>
				<div class="o100-private-room-prompt" id="o100-resv-private-prompt">
					<p>🎉 <?php esc_html_e( 'For large parties, we highly recommend booking a private room.', 'order100' ); ?></p>
					<label>
						<input type="checkbox" name="request_private_room" value="1">
						<?php esc_html_e( 'Request a Private Dining Room', 'order100' ); ?>
					</label>
				</div>
				<?php endif; ?>

				<!-- Honeypot anti-bot -->
				<div class="o100-hp-field" aria-hidden="true">
					<label>Leave this empty</label>
					<input type="text" name="o100_hp_website" value="" tabindex="-1" autocomplete="off">
				</div>
				<input type="hidden" name="o100_bot_token" value="<?php echo esc_attr( $bot_token ); ?>">

				<?php if ( ! empty( $terms_text ) ) : ?>
				<div class="o100-resv-field width-full">
					<div class="o100-resv-terms">
						<input type="checkbox" name="o100_agree_terms" id="o100-resv-terms" required>
						<span><?php echo esc_html( $terms_text ); ?></span>
					</div>
				</div>
				<?php endif; ?>

				<div class="o100-resv-field width-full">
					<button type="submit" class="o100-resv-submit"><?php esc_html_e( 'Confirm Reservation', 'order100' ); ?></button>
				</div>
			</form>

			<?php if ( ! empty( $dining_info ) ) : ?>
			<div class="o100-resv-info-section">
				<h3><?php esc_html_e( 'Important dining information', 'order100' ); ?></h3>
				<p><?php echo nl2br( esc_html( $dining_info ) ); ?></p>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $restaurant_note ) ) : ?>
			<div class="o100-resv-info-section">
				<h3><?php esc_html_e( 'A note from the restaurant', 'order100' ); ?></h3>
				<p><?php echo nl2br( esc_html( $restaurant_note ) ); ?></p>
			</div>
			<?php endif; ?>
		</div>
		<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
		<script>
		(function(){
			var ajaxUrl = '<?php echo esc_url( $ajax_url ); ?>';
			var branchEl = document.getElementById('o100-resv-branch');
			var dateEl   = document.getElementById('o100-resv-date');
			var timeEl   = document.getElementById('o100-resv-time');
			var fp       = null;
			var currentDateConfig = {};

			// ── Private Room Prompt ──
			var enableRooms = <?php echo $enable_rooms ? 'true' : 'false'; ?>;
			var roomThreshold = <?php echo $room_threshold; ?>;
			var partyEl = document.querySelector('input[name="party_size"]');
			var promptEl = document.getElementById('o100-resv-private-prompt');
			if (partyEl && promptEl && enableRooms) {
				partyEl.addEventListener('input', function() {
					var size = parseInt(partyEl.value) || 0;
					promptEl.style.display = (size >= roomThreshold) ? 'block' : 'none';
				});
			}

			// ── Date Picker (flatpickr) ──
			function initDatePicker(config) {
				if (fp) fp.destroy();
				if (!dateEl) return;
				currentDateConfig = config;
				fp = flatpickr(dateEl, {
					dateFormat: 'Y-m-d',
					minDate: config.min_date || 'today',
					maxDate: config.max_date || null,
					disable: [
						function(date) {
							var wd = date.getDay();
							if ((config.disabled_weekdays || []).indexOf(wd) !== -1) return true;
							var ds = date.getFullYear()+'-'+String(date.getMonth()+1).padStart(2,'0')+'-'+String(date.getDate()).padStart(2,'0');
							return (config.disabled_dates || []).indexOf(ds) !== -1;
						}
					],
					onChange: function(sel, dateStr) { loadSlots(dateStr); }
				});
			}

			function loadDates() {
				var bid = branchEl ? branchEl.value : '';
				var fd = new FormData();
				fd.append('action','o100_resv_get_available_dates');
				fd.append('branch_id', bid);
				fetch(ajaxUrl, {method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){
					if (r.success) initDatePicker(r.data);
				});
				// Reset date & time
				if (dateEl) dateEl.value = '';
				if (timeEl) { timeEl.innerHTML = '<option value=""><?php echo esc_js( __( 'Select a date first', 'order100' ) ); ?></option>'; }
			}

			// ── Time Slots ──
			function loadSlots(date) {
				if (!timeEl || !date) return;
				timeEl.innerHTML = '<option value=""><?php echo esc_js( __( 'Loading...', 'order100' ) ); ?></option>';
				var bid = branchEl ? branchEl.value : '';
				var fd = new FormData();
				fd.append('action','o100_resv_get_available_slots');
				fd.append('branch_id', bid);
				fd.append('date', date);
				fetch(ajaxUrl, {method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){
					timeEl.innerHTML = '<option value=""><?php echo esc_js( __( 'Select time', 'order100' ) ); ?></option>';
					if (r.success && r.data.length) {
						var isToday = (currentDateConfig.today && date === currentDateConfig.today);
						var cutoff = currentDateConfig.lead_cutoff || '';
						r.data.forEach(function(s){
							// Skip past timeslots if selecting today
							if (isToday && cutoff && s.time < cutoff) return;
							var opt = document.createElement('option');
							opt.value = s.time;
							opt.textContent = s.label;
							timeEl.appendChild(opt);
						});
						// Check if any options were actually added
						if (timeEl.options.length <= 1) {
							timeEl.innerHTML = '<option value=""><?php echo esc_js( __( 'No slots available', 'order100' ) ); ?></option>';
						}
					} else {
						timeEl.innerHTML = '<option value=""><?php echo esc_js( __( 'No slots available', 'order100' ) ); ?></option>';
					}
				});
			}

			// ── Branch → Date cascade ──
			if (branchEl) branchEl.addEventListener('change', loadDates);

			// ── Email validation ──
			var emailEl = document.getElementById('o100-resv-email');
			if (emailEl) {
				emailEl.addEventListener('blur', function(){
					var errEl = document.getElementById('o100-email-error');
					var valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value);
					emailEl.classList.toggle('o100-invalid', !valid && emailEl.value.length > 0);
					if (errEl) errEl.style.display = (!valid && emailEl.value.length > 0) ? 'block' : 'none';
				});
			}

			// ── Phone validation ──
			var phoneEl = document.getElementById('o100-resv-phone');
			if (phoneEl) {
				phoneEl.addEventListener('blur', function(){
					var errEl = document.getElementById('o100-phone-error');
					var valid = /^\d{6,15}$/.test(phoneEl.value);
					phoneEl.classList.toggle('o100-invalid', !valid && phoneEl.value.length > 0);
					if (errEl) errEl.style.display = (!valid && phoneEl.value.length > 0) ? 'block' : 'none';
				});
			}

			// ── Form Submit ──
			var form = document.getElementById('o100-resv-form');
			var msgEl = document.getElementById('o100-resv-msg');
			if (form) {
				form.addEventListener('submit', function(e) {
					e.preventDefault();
					var submitBtn = form.querySelector('.o100-resv-submit');
					var origText = submitBtn ? submitBtn.textContent : '';
					if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = '<?php echo esc_js( __( 'Submitting...', 'order100' ) ); ?>'; }
					if (msgEl) msgEl.style.display = 'none';

					var fd = new FormData(form);
					fd.append('action', 'o100_resv_submit');

					fetch(ajaxUrl, {method:'POST', body:fd})
						.then(function(r){ return r.json(); })
						.then(function(r){
							if (r.success) {
								if (msgEl) {
									msgEl.className = 'o100-resv-msg o100-resv-msg--success';
									var statusIcon = r.data.status === 'confirmed' ? '✅' : '⏳';
									var shopUrl = r.data.shop_url || '/';
									msgEl.innerHTML = '<div style="text-align:center;">'
										+ '<div style="font-size:32px;margin-bottom:8px;">' + statusIcon + '</div>'
										+ '<p style="font-size:16px;font-weight:600;margin:0 0 8px;">' + (r.data.message || '') + '</p>'
										+ '<p style="color:#475569;margin:0 0 16px;font-size:14px;"><?php echo esc_js( __( 'While you wait, why not browse our menu or pre-order online to skip the line?', 'order100' ) ); ?></p>'
										+ '<a href="' + shopUrl + '" style="display:inline-block;padding:12px 28px;background:var(--wp--preset--color--primary,#6366f1);color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;"><?php echo esc_js( __( '🍽️ Browse Our Menu', 'order100' ) ); ?></a>'
										+ '</div>';
									msgEl.style.display = 'block';
								}
								form.reset();
								form.style.display = 'none';
								window.scrollTo({top: msgEl.offsetTop - 100, behavior:'smooth'});
							} else {
								if (msgEl) {
									msgEl.className = 'o100-resv-msg o100-resv-msg--error';
									msgEl.textContent = r.data || '<?php echo esc_js( __( 'Something went wrong. Please try again.', 'order100' ) ); ?>';
									msgEl.style.display = 'block';
								}
							}
						})
						.catch(function(){
							if (msgEl) {
								msgEl.className = 'o100-resv-msg o100-resv-msg--error';
								msgEl.textContent = '<?php echo esc_js( __( 'Network error. Please try again.', 'order100' ) ); ?>';
								msgEl.style.display = 'block';
							}
						})
						.finally(function(){
							if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = origText; }
						});
				});
			}

			// ── Init ──
			loadDates();
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode handler: [o100_reservation]
	 */
	public function shortcode_handler( $atts ) {
		if ( ! self::is_enabled() ) {
			return '<p>' . esc_html__( 'Online reservations are currently not available.', 'order100' ) . '</p>';
		}
		return $this->render_form_html();
	}

	/**
	 * AJAX: Handle form submission
	 */
	public function ajax_submit() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['o100_resv_nonce'] ?? '', 'o100_resv_submit' ) ) {
			wp_send_json_error( __( 'Security check failed. Please refresh the page and try again.', 'order100' ) );
		}

		// Honeypot check
		if ( ! empty( $_POST['o100_hp_website'] ) ) {
			wp_send_json_error( __( 'Spam detected.', 'order100' ) );
		}

		// Bot token check
		$expected_token = wp_hash( 'o100_resv_' . date( 'Ymd' ) );
		$submitted_token = isset( $_POST['o100_bot_token'] ) ? sanitize_text_field( $_POST['o100_bot_token'] ) : '';
		// Allow yesterday's token too (for submissions near midnight)
		$yesterday_token = wp_hash( 'o100_resv_' . date( 'Ymd', strtotime( '-1 day' ) ) );
		if ( $submitted_token !== $expected_token && $submitted_token !== $yesterday_token ) {
			wp_send_json_error( __( 'Session expired. Please refresh the page and try again.', 'order100' ) );
		}

		// Sanitize inputs
		$guest_name  = sanitize_text_field( $_POST['guest_name'] ?? '' );
		$guest_email = sanitize_email( $_POST['guest_email'] ?? '' );
		$phone_code  = sanitize_text_field( $_POST['guest_phone_code'] ?? '+1' );
		$phone_num   = sanitize_text_field( $_POST['guest_phone'] ?? '' );
		$guest_phone = $phone_code . ' ' . $phone_num;
		$party_size  = intval( $_POST['party_size'] ?? 1 );
		$date        = sanitize_text_field( $_POST['reservation_date'] ?? '' );
		$time        = sanitize_text_field( $_POST['reservation_time'] ?? '' );
		$occasion    = sanitize_text_field( $_POST['occasion'] ?? '' );
		$req_private = ! empty( $_POST['request_private_room'] ) ? true : false;
		$booking_type = $req_private ? 'private_room' : 'table';
		$special_req = sanitize_textarea_field( $_POST['special_requests'] ?? '' );
		$branch_id   = intval( $_POST['branch'] ?? 0 );

		if ( ! empty( $occasion ) ) {
			$special_req = "Occasion: {$occasion}\n\n" . $special_req;
			$special_req = trim( $special_req );
		}

		// Validate required fields
		if ( empty( $guest_name ) ) {
			wp_send_json_error( __( 'Please enter your name.', 'order100' ) );
		}
		if ( empty( $guest_email ) || ! is_email( $guest_email ) ) {
			wp_send_json_error( __( 'Please enter a valid email address.', 'order100' ) );
		}
		if ( empty( $phone_num ) || ! preg_match( '/^\d{6,15}$/', $phone_num ) ) {
			wp_send_json_error( __( 'Please enter a valid phone number.', 'order100' ) );
		}
		if ( $party_size < 1 ) {
			wp_send_json_error( __( 'Party size must be at least 1.', 'order100' ) );
		}
		if ( empty( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_send_json_error( __( 'Please select a valid date.', 'order100' ) );
		}
		if ( empty( $time ) ) {
			wp_send_json_error( __( 'Please select a time slot.', 'order100' ) );
		}

		// Validate date is not in the past
		$today = date( 'Y-m-d', current_time( 'timestamp' ) );
		if ( $date < $today ) {
			wp_send_json_error( __( 'Cannot make reservations for past dates.', 'order100' ) );
		}

		// Determine initial status based on confirmation mode setting
		$settings = self::get_settings();
		$confirm_mode = isset( $settings['o100_resv_confirmation'] ) ? $settings['o100_resv_confirmation'] : 'auto';
		$initial_status = ( $confirm_mode === 'auto' ) ? 'confirmed' : 'pending';

		// Insert into database
		$reservation_id = O100_Reservation_DB::insert( array(
			'status'           => $initial_status,
			'booking_type'     => $booking_type,
			'guest_name'       => $guest_name,
			'guest_email'      => $guest_email,
			'guest_phone'      => $guest_phone,
			'party_size'       => $party_size,
			'reservation_date' => $date,
			'reservation_time' => $time,
			'location_id'      => $branch_id,
			'special_requests' => $special_req,
			'source'           => 'website',
		) );

		if ( ! $reservation_id ) {
			wp_send_json_error( __( 'Failed to save reservation. Please try again.', 'order100' ) );
		}

		// Send email notifications
		if ( class_exists( 'O100_Reservation_Notify' ) ) {
			if ( $initial_status === 'confirmed' ) {
				// Auto-confirm: send confirmed email directly
				O100_Reservation_Notify::send_status_change( $reservation_id, 'confirmed' );
			} else {
				// Manual: send pending notification
				O100_Reservation_Notify::send_new_reservation( $reservation_id );
			}
		}

		// Build response message
		if ( $initial_status === 'confirmed' ) {
			$msg = __( 'Your reservation has been confirmed! A confirmation email has been sent to your inbox.', 'order100' );
		} else {
			$msg = __( 'Your reservation has been submitted successfully! You will receive a confirmation email once approved.', 'order100' );
		}

		wp_send_json_success( array(
			'message'  => $msg,
			'id'       => $reservation_id,
			'status'   => $initial_status,
			'shop_url' => get_permalink( wc_get_page_id( 'shop' ) ) ?: home_url( '/' ),
		) );
	}

	/**
	 * Cron handler: Send reminder emails
	 */
	public function cron_send_reminders() {
		if ( class_exists( 'O100_Reservation_Notify' ) ) {
			$settings = self::get_settings();
			$hours = isset( $settings['o100_resv_reminder_hours'] ) ? intval( $settings['o100_resv_reminder_hours'] ) : 2;
			O100_Reservation_Notify::send_reminders( $hours );
		}
	}

	/**
	 * Cleanup on plugin deactivation — clear cron
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'o100_reservation_send_reminders' );
	}
}

// TS: 20260123020407

// TS: 20260207162611
