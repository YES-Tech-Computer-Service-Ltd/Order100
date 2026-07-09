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
		require_once $dir . 'class-o100-reservation-notify.php';
		require_once $dir . 'class-o100-reservation-api.php';

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
		if ( file_exists( $dir . 'class-o100-reservation-emails.php' ) ) {
			require_once $dir . 'class-o100-reservation-emails.php';
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



		// Reminder cron
		add_action( 'o100_reservation_send_reminders', array( $this, 'cron_send_reminders' ) );
		if ( ! wp_next_scheduled( 'o100_reservation_send_reminders' ) ) {
			wp_schedule_event( time(), 'hourly', 'o100_reservation_send_reminders' );
		}

		// Post-dining cron
		add_action( 'o100_reservation_send_post_dining', array( $this, 'cron_send_post_dining' ) );
		if ( ! wp_next_scheduled( 'o100_reservation_send_post_dining' ) ) {
			wp_schedule_event( time(), 'daily', 'o100_reservation_send_post_dining' );
		}

		// Handle Email Action Links (Confirm/Cancel)
		add_action( 'template_redirect', array( $this, 'handle_action_links' ) );
	}

	/**
	 * Create the DB table if it doesn't exist.
	 * Uses a version check stored in options to avoid running dbDelta on every load.
	 */
	public function maybe_create_table() {
		$db_version = get_option( 'o100_reservation_db_version', '0' );
		if ( version_compare( $db_version, '1.1', '<' ) ) {
			O100_Reservation_DB::create_table();
			update_option( 'o100_reservation_db_version', '1.1' );
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
		if ( isset( $val['open'] ) || isset( $val['close'] ) || isset( $val['start'] ) || isset( $val['end'] ) || isset( $val['open-time'] ) || isset( $val['close-time'] ) ) {
			$open  = isset( $val['open'] ) ? $val['open'] : ( isset( $val['start'] ) ? $val['start'] : ( isset( $val['open-time'] ) ? $val['open-time'] : '' ) );
			$close = isset( $val['close'] ) ? $val['close'] : ( isset( $val['end'] ) ? $val['end'] : ( isset( $val['close-time'] ) ? $val['close-time'] : '' ) );
			return ! empty( $open ) && ! empty( $close );
		}

		// Format 2: Nested array of slots [{'open':'09:00','close':'12:00'}, {'open':'13:00','close':'21:00'}]
		foreach ( $val as $slot ) {
			if ( ! is_array( $slot ) ) continue;
			$open  = isset( $slot['open'] ) ? $slot['open'] : ( isset( $slot['start'] ) ? $slot['start'] : ( isset( $slot['open-time'] ) ? $slot['open-time'] : '' ) );
			$close = isset( $slot['close'] ) ? $slot['close'] : ( isset( $slot['end'] ) ? $slot['end'] : ( isset( $slot['close-time'] ) ? $slot['close-time'] : '' ) );
			if ( ! empty( $open ) && ! empty( $close ) ) return true;
		}

		// Format 3: Non-empty array without recognizable keys = treat as open
		// (safety fallback for unknown CMB2 formats)
		return count( $val ) > 0 && is_array( reset( $val ) );
	}

	/**
	 * Get available dates for reservation (API data provider)
	 */
	public static function get_available_dates_data( $branch_id = 0 ) {
		$settings   = self::get_settings();
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

		// Emergency Closure Dates
		if ( class_exists( 'O100_Emergency_Closure' ) ) {
			$closure = O100_Emergency_Closure::get_active_closure_data( $branch_id );
			if ( $closure !== false ) {
				if ( $closure['expires_at'] === 0 ) {
					// Indefinite closure. Block a huge range.
					for ( $i = 0; $i < 60; $i++ ) {
						$disabled_dates[] = date( 'Y-m-d', $now + ( $i * 86400 ) );
					}
				} else {
					$st = $closure['starts_at'] > 0 ? $closure['starts_at'] : $now;
					$en = $closure['expires_at'];
					$cur = strtotime( date( 'Y-m-d', $st ) );
					$end_day = strtotime( date( 'Y-m-d', $en ) );
					// Use < (not <=): don't block the expiry date at calendar level.
					// The timeslot-level check handles partial-day closures accurately.
					while ( $cur < $end_day ) {
						$disabled_dates[] = date( 'Y-m-d', $cur );
						$cur += 86400;
					}
				}
			}
		}

		return array(
			'min_date'          => $min_date,
			'max_date'          => $max_date,
			'today'             => date( 'Y-m-d', $now ),
			'lead_cutoff'       => $lead_cutoff,
			'disabled_dates'    => array_values( array_unique( $disabled_dates ) ),
			'disabled_weekdays' => $disabled_weekdays,
		);
	}

	/**
	 * Get available timeslots for a given date (API data provider)
	 */
	public static function get_available_slots_data( $branch_id, $date, $max_per = null ) {
		$settings  = self::get_settings();
		if ( $max_per === null ) {
			$max_per = isset( $settings['o100_resv_max_per_slot'] ) ? intval( $settings['o100_resv_max_per_slot'] ) : 5;
		}

		if ( empty( $date ) ) {
			return new WP_Error( 'missing_date', 'No date provided', array( 'status' => 400 ) );
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
					$open  = isset( $range['start'] ) ? $range['start'] : ( isset( $range['open'] ) ? $range['open'] : ( isset( $range['open-time'] ) ? $range['open-time'] : '' ) );
					$close = isset( $range['end'] ) ? $range['end'] : ( isset( $range['close'] ) ? $range['close'] : ( isset( $range['close-time'] ) ? $range['close-time'] : '' ) );
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

		// Get max limits
		$max_tables = isset( $settings['o100_resv_max_per_slot'] ) && $settings['o100_resv_max_per_slot'] !== '' ? intval( $settings['o100_resv_max_per_slot'] ) : 999;
		$max_pax    = isset( $settings['o100_resv_max_pax_per_slot'] ) && $settings['o100_resv_max_pax_per_slot'] !== '' ? intval( $settings['o100_resv_max_pax_per_slot'] ) : 9999;
		
		// Parse Peak Blocks (New Group Field)
		$peak_blocks_group = isset( $settings['o100_resv_peak_blocks_group'] ) ? $settings['o100_resv_peak_blocks_group'] : array();
		
		// Get Emergency Closure
		$closure = class_exists( 'O100_Emergency_Closure' ) ? O100_Emergency_Closure::get_active_closure_data( $branch_id ) : false;

		$result = array();
		foreach ( $day_slots as $slot ) {
			$time = isset( $slot['start-time'] ) ? $slot['start-time'] : '';
			if ( empty( $time ) ) continue;

			$slot_ts = strtotime( "{$date} {$time}" );
			$is_full = false;

			// 1. Emergency Closure Check
			if ( $closure !== false ) {
				$c_start = $closure['starts_at'] > 0 ? $closure['starts_at'] : time();
				$c_end   = $closure['expires_at'] > 0 ? $closure['expires_at'] : 2147483647;
				if ( $slot_ts >= $c_start && $slot_ts <= $c_end ) {
					$is_full = true;
				}
			}

			// 2. Peak Block Check
			if ( ! $is_full && ! empty( $peak_blocks_group ) && is_array( $peak_blocks_group ) ) {
				$day_short = date('D', $slot_ts); // Mon, Tue...
				foreach ( $peak_blocks_group as $rule ) {
					$r_type  = isset($rule['type']) ? $rule['type'] : '';
					$r_start = isset($rule['start_time']) ? $rule['start_time'] : '';
					$r_end   = isset($rule['end_time']) ? $rule['end_time'] : '';
					
					if ( empty($r_start) || empty($r_end) ) continue;

					$r_start_ts = strtotime("{$date} {$r_start}");
					$r_end_ts   = strtotime("{$date} {$r_end}");

					if ( $r_type === 'date' ) {
						$r_date = isset($rule['date']) ? $rule['date'] : '';
						if ( $date === $r_date && $slot_ts >= $r_start_ts && $slot_ts <= $r_end_ts ) {
							$is_full = true; break;
						}
					} elseif ( $r_type === 'weekday' ) {
						$r_weekdays = isset($rule['weekdays']) ? $rule['weekdays'] : array();
						if ( ! is_array($r_weekdays) ) {
							$r_weekdays = array( $r_weekdays );
						}
						if ( in_array($day_short, $r_weekdays) && $slot_ts >= $r_start_ts && $slot_ts <= $r_end_ts ) {
							$is_full = true; break;
						}
					}
				}
			}

			// 3. Capacity Checks (Tables and Pax)
			$booked_tables = 0;
			$booked_pax = 0;
			if ( ! $is_full && class_exists( 'O100_Reservation_DB' ) ) {
				$booked_tables = O100_Reservation_DB::count_active( $date, $time, 'table', null, 0 );
				$booked_pax    = O100_Reservation_DB::get_active_pax_count( $date, $time, 'table', 0 );
				
				if ( $booked_tables >= $max_tables || $booked_pax >= $max_pax ) {
					$is_full = true;
				}
			}

			$result[] = array(
				'time'      => $time,
				'label'     => date( 'g:i A', strtotime( $time ) ),
				'available' => $is_full ? 0 : 1,
				'max'       => $max_tables,
				'is_full'   => $is_full,
			);
		}

		return $result;
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

		// Get default values for logged-in users
		$default_guest_name = '';
		$default_guest_email = '';
		$default_guest_phone = '';
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$default_guest_email = $current_user->user_email;
			if ( ! empty( $current_user->first_name ) ) {
				$default_guest_name = trim( $current_user->first_name . ' ' . $current_user->last_name );
			} else {
				$default_guest_name = $current_user->display_name;
			}
			$default_guest_phone = get_user_meta( $current_user->ID, 'billing_phone', true );
		}

		ob_start();
		$view_file = O100_PATH . 'core/reservation/views/view-reservation-form.php';
		if ( file_exists( $view_file ) ) {
			include $view_file;
		}
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
	 * Process form submission (API data provider)
	 */
	public static function submit_reservation_data( $post_data ) {
		// Bot token check
		$expected_token = wp_hash( 'o100_resv_' . date( 'Ymd' ) );
		$submitted_token = isset( $post_data['o100_bot_token'] ) ? sanitize_text_field( $post_data['o100_bot_token'] ) : '';
		// Allow yesterday's token too (for submissions near midnight)
		$yesterday_token = wp_hash( 'o100_resv_' . date( 'Ymd', strtotime( '-1 day' ) ) );
		if ( $submitted_token !== $expected_token && $submitted_token !== $yesterday_token ) {
			return new WP_Error( 'session_expired', __( 'Session expired. Please refresh the page and try again.', 'order100' ), array( 'status' => 400 ) );
		}

		// Sanitize inputs
		$guest_name  = sanitize_text_field( $post_data['guest_name'] ?? '' );
		$guest_email = sanitize_email( $post_data['guest_email'] ?? '' );
		$phone_code  = sanitize_text_field( $post_data['guest_phone_code'] ?? '+1' );
		$phone_num   = sanitize_text_field( $post_data['guest_phone'] ?? '' );
		$guest_phone = $phone_code . ' ' . $phone_num;
		$party_size  = intval( $post_data['party_size'] ?? 1 );
		$date        = sanitize_text_field( $post_data['reservation_date'] ?? '' );
		$time        = sanitize_text_field( $post_data['reservation_time'] ?? '' );
		$occasion    = sanitize_text_field( $post_data['occasion'] ?? '' );
		$req_private = ! empty( $post_data['request_private_room'] ) ? true : false;
		$booking_type = $req_private ? 'private_room' : 'table';
		$special_req = sanitize_textarea_field( $post_data['special_requests'] ?? '' );
		$branch_id   = intval( $post_data['branch'] ?? 0 );

		if ( ! empty( $occasion ) ) {
			$special_req = "Occasion: {$occasion}\n\n" . $special_req;
			$special_req = trim( $special_req );
		}

		// Validate required fields
		if ( empty( $guest_name ) ) {
			return new WP_Error( 'invalid_name', __( 'Please enter your name.', 'order100' ), array( 'status' => 400 ) );
		}
		if ( empty( $guest_email ) || ! is_email( $guest_email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'order100' ), array( 'status' => 400 ) );
		}
		if ( empty( $phone_num ) || ! preg_match( '/^\d{6,15}$/', $phone_num ) ) {
			return new WP_Error( 'invalid_phone', __( 'Please enter a valid phone number.', 'order100' ), array( 'status' => 400 ) );
		}
		if ( $party_size < 1 ) {
			return new WP_Error( 'invalid_party', __( 'Party size must be at least 1.', 'order100' ), array( 'status' => 400 ) );
		}
		if ( empty( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'invalid_date', __( 'Please select a valid date.', 'order100' ), array( 'status' => 400 ) );
		}
		if ( empty( $time ) ) {
			return new WP_Error( 'invalid_time', __( 'Please select a time slot.', 'order100' ), array( 'status' => 400 ) );
		}

		// Load settings for all validations
		$settings = self::get_settings();
		$now = current_time( 'timestamp' );
		
		$bypass_limits = false;
		$no_show_prevention = false;
		
		if ( class_exists( 'O100_Privilege_Manager' ) ) {
			// In checkout/submit, we usually have an email. 
			// $guest_email is provided by the form. If logged in, we have user ID too.
			$user_id = get_current_user_id();
			$identifier = $user_id ? $user_id : $guest_email;
			
			$context = [ 
				'branch' => $branch_id, 
				'order_type' => 'reservation',
				'party_size' => $party_size,
				'timestamp' => strtotime( "{$date} {$time}" )
			];
			$bypass_limits = O100_Privilege_Manager::has_privilege( $identifier, 'reservation', 'bypass_limits', $context );
			$no_show_prevention = O100_Privilege_Manager::has_privilege( $identifier, 'reservation', 'no_show_prevention', $context );
		}

		if ( $no_show_prevention ) {
			return new WP_Error( 'no_show', __( 'Your online reservation privileges have been temporarily suspended. Please call the restaurant directly to book a table.', 'order100' ), array( 'status' => 403 ) );
		}

		// Validate date is not in the past
		$today = date( 'Y-m-d', $now );
		if ( $date < $today ) {
			return new WP_Error( 'past_date', __( 'Cannot make reservations for past dates.', 'order100' ), array( 'status' => 400 ) );
		}

		// Validate max advance booking
		$max_advance = isset( $settings['o100_resv_max_advance'] ) ? intval( $settings['o100_resv_max_advance'] ) : 30;
		$max_date = date( 'Y-m-d', $now + ( $max_advance * 86400 ) );
		if ( !$bypass_limits && $date > $max_date ) {
			return new WP_Error( 'max_advance', sprintf( __( 'Reservations can only be made up to %d days in advance.', 'order100' ), $max_advance ), array( 'status' => 400 ) );
		}

		// Validate minimum lead time
		$lead_hours = isset( $settings['o100_resv_lead_time'] ) ? intval( $settings['o100_resv_lead_time'] ) : 2;
		$slot_ts = strtotime( "{$date} {$time}" );
		$min_ts = $now + ( $lead_hours * 3600 );
		if ( !$bypass_limits && $slot_ts < $min_ts ) {
			return new WP_Error( 'lead_time', sprintf( __( 'Reservations must be made at least %d hours in advance.', 'order100' ), $lead_hours ), array( 'status' => 400 ) );
		}

		// Validate max party size
		$max_party = isset( $settings['o100_resv_max_party'] ) ? intval( $settings['o100_resv_max_party'] ) : 0;
		if ( !$bypass_limits && $max_party > 0 && $party_size > $max_party ) {
			return new WP_Error( 'max_party', sprintf( __( 'Maximum party size is %d guests. For larger groups, please call us directly.', 'order100' ), $max_party ), array( 'status' => 400 ) );
		}

		// Validate Emergency Closure
		if ( class_exists( 'O100_Emergency_Closure' ) ) {
			$closure = O100_Emergency_Closure::get_active_closure_data( $branch_id );
			if ( $closure !== false ) {
				$c_start = $closure['starts_at'] > 0 ? $closure['starts_at'] : time();
				$c_end   = $closure['expires_at'] > 0 ? $closure['expires_at'] : 2147483647;
				if ( !$bypass_limits && $slot_ts >= $c_start && $slot_ts <= $c_end ) {
					return new WP_Error( 'closure', __( 'This time slot is unavailable due to a temporary closure.', 'order100' ), array( 'status' => 400 ) );
				}
			}
		}

		// Validate Peak Blockout
		$peak_blocks = isset( $settings['o100_resv_peak_blocks_group'] ) ? $settings['o100_resv_peak_blocks_group'] : array();
		if ( ! empty( $peak_blocks ) && is_array( $peak_blocks ) ) {
			$day_short = date( 'D', $slot_ts );
			foreach ( $peak_blocks as $rule ) {
				$r_type  = isset( $rule['type'] ) ? $rule['type'] : '';
				$r_start = isset( $rule['start_time'] ) ? $rule['start_time'] : '';
				$r_end   = isset( $rule['end_time'] ) ? $rule['end_time'] : '';
				if ( empty( $r_start ) || empty( $r_end ) ) continue;
				$r_start_ts = strtotime( "{$date} {$r_start}" );
				$r_end_ts   = strtotime( "{$date} {$r_end}" );
				$blocked = false;
				if ( $r_type === 'date' ) {
					$r_date = isset( $rule['date'] ) ? $rule['date'] : '';
					if ( $date === $r_date && $slot_ts >= $r_start_ts && $slot_ts <= $r_end_ts ) $blocked = true;
				} elseif ( $r_type === 'weekday' ) {
					$r_weekdays = isset( $rule['weekdays'] ) ? $rule['weekdays'] : array();
					if ( ! is_array( $r_weekdays ) ) $r_weekdays = array( $r_weekdays );
					if ( in_array( $day_short, $r_weekdays ) && $slot_ts >= $r_start_ts && $slot_ts <= $r_end_ts ) $blocked = true;
				}
				if ( !$bypass_limits && $blocked ) {
					return new WP_Error( 'blocked', __( 'This time slot is blocked for reservations.', 'order100' ), array( 'status' => 400 ) );
				}
			}
		}

		// Validate capacity (Max Tables + Max Guests per slot)
		$max_tables = isset( $settings['o100_resv_max_per_slot'] ) && $settings['o100_resv_max_per_slot'] !== '' ? intval( $settings['o100_resv_max_per_slot'] ) : 999;
		$max_pax    = isset( $settings['o100_resv_max_pax_per_slot'] ) && $settings['o100_resv_max_pax_per_slot'] !== '' ? intval( $settings['o100_resv_max_pax_per_slot'] ) : 9999;
		if ( class_exists( 'O100_Reservation_DB' ) ) {
			$booked_tables = O100_Reservation_DB::count_active( $date, $time, 'table', null, 0 );
			$booked_pax    = O100_Reservation_DB::get_active_pax_count( $date, $time, 'table', 0 );
			if ( !$bypass_limits && $booked_tables >= $max_tables ) {
				return new WP_Error( 'full_tables', __( 'Sorry, this time slot is fully booked.', 'order100' ), array( 'status' => 400 ) );
			}
			if ( !$bypass_limits && ( $booked_pax + $party_size ) > $max_pax ) {
				return new WP_Error( 'full_pax', __( 'Sorry, not enough guest capacity for this time slot.', 'order100' ), array( 'status' => 400 ) );
			}
		}

		// Determine initial status based on confirmation mode setting
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
			return new WP_Error( 'insert_failed', __( 'Failed to save reservation. Please try again.', 'order100' ), array( 'status' => 500 ) );
		}

		// Push to CRM Customers
		if ( class_exists( 'O100_Customers_DB' ) ) {
			global $wpdb;
			$tbl_customers = O100_Customers_DB::get_table_customers();
			
			// Extract First and Last Name
			$name_parts = explode( ' ', trim( $guest_name ), 2 );
			$first_name = $name_parts[0];
			$last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';

			$existing_customer = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$tbl_customers} WHERE email = %s", $guest_email ) );
			
			if ( $existing_customer ) {
				$customer_id = $existing_customer->id;
				// Only update name and phone if empty (or just update phone)
				$wpdb->update(
					$tbl_customers,
					[
						'first_name' => $first_name,
						'last_name'  => $last_name,
						'phone'      => $guest_phone,
						'updated_at' => current_time( 'mysql' )
					],
					[ 'id' => $customer_id ]
				);
			} else {
				$wpdb->insert(
					$tbl_customers,
					[
						'email'              => $guest_email,
						'first_name'         => $first_name,
						'last_name'          => $last_name,
						'phone'              => $guest_phone,
						'status'             => 'subscribed',
						'acquisition_source' => 'reservation',
						'created_at'         => current_time( 'mysql' ),
						'updated_at'         => current_time( 'mysql' )
					],
					[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
				);
				$customer_id = $wpdb->insert_id;
			}

			// Add a Reservation Tag
			if ( $customer_id ) {
				// Make sure tag exists
				$tag_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . O100_Customers_DB::get_table_tags() . " WHERE name = %s", 'Reservation' ) );
				if ( ! $tag_id ) {
					$wpdb->insert(
						O100_Customers_DB::get_table_tags(),
						[
							'name'        => 'Reservation',
							'description' => 'Guest who made a table reservation',
							'is_system'   => 1,
							'created_at'  => current_time( 'mysql' )
						]
					);
					$tag_id = $wpdb->insert_id;
				}

				// Assign tag to customer
				if ( $tag_id ) {
					$rel_table = O100_Customers_DB::get_table_relationships();
					$rel_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rel_table} WHERE customer_id = %d AND rel_type = 'tag' AND rel_id = %d", $customer_id, $tag_id ) );
					if ( ! $rel_exists ) {
						$wpdb->insert(
							$rel_table,
							[
								'customer_id' => $customer_id,
								'rel_type'    => 'tag',
								'rel_id'      => $tag_id,
								'created_at'  => current_time( 'mysql' )
							]
						);
					}
				}
			}
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

		return array(
			'message'  => $msg,
			'id'       => $reservation_id,
			'status'   => $initial_status,
			'shop_url' => get_permalink( wc_get_page_id( 'shop' ) ) ?: home_url( '/' ),
		);
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
	 * Send post-dining review requests (runs daily)
	 */
	public function cron_send_post_dining() {
		if ( ! self::is_enabled() ) return;
		if ( ! class_exists( 'O100_Reservation_DB' ) || ! class_exists( 'O100_Reservation_Notify' ) ) return;

		global $wpdb;
		$table = O100_Reservation_DB::table_name();

		// Find confirmed or completed reservations from yesterday where review_sent = 0
		$yesterday = date('Y-m-d', current_time('timestamp') - 86400);
		
		$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE reservation_date = %s AND status IN ('confirmed', 'completed') AND review_sent = 0 LIMIT 100", $yesterday );
		$results = $wpdb->get_results( $sql );

		if ( $results ) {
			foreach ( $results as $resv ) {
				O100_Reservation_Notify::send_guest_review_request( $resv );
				$wpdb->update( $table, array( 'review_sent' => 1 ), array( 'id' => $resv->id ), array( '%d' ), array( '%d' ) );
			}
		}
	}

	/**
	 * Handle email Confirm/Cancel action links
	 */
	public function handle_action_links() {
		if ( ! isset( $_GET['o100_resv_action'] ) || ! isset( $_GET['id'] ) || ! isset( $_GET['token'] ) ) {
			return;
		}

		$action = sanitize_text_field( $_GET['o100_resv_action'] );
		$id     = intval( $_GET['id'] );
		$token  = sanitize_text_field( $_GET['token'] );

		if ( ! class_exists( 'O100_Reservation_DB' ) ) return;

		global $wpdb;
		$table = O100_Reservation_DB::table_name();
		$resv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		if ( ! $resv ) {
			wp_die( esc_html__( 'Reservation not found.', 'order100' ) );
		}

		$expected_token = wp_hash( 'o100_resv_' . $resv->id . '_' . $resv->guest_email );
		if ( ! hash_equals( $expected_token, $token ) ) {
			wp_die( esc_html__( 'Invalid or expired link.', 'order100' ) );
		}

		if ( $action === 'confirm' ) {
			if ( $resv->status === 'pending' ) {
				O100_Reservation_DB::update_status( $resv->id, 'confirmed' );
				// Redirect to front page with success msg
				wp_redirect( add_query_arg( 'resv_msg', 'confirmed', home_url('/') ) );
				exit;
			} else {
				wp_die( esc_html__( 'This reservation has already been processed.', 'order100' ) );
			}
		} elseif ( $action === 'cancel' ) {
			if ( in_array( $resv->status, array('pending', 'confirmed') ) ) {
				O100_Reservation_DB::update_status( $resv->id, 'cancelled' );
				// Send cancellation email
				if ( class_exists( 'O100_Reservation_Notify' ) ) {
					O100_Reservation_Notify::send_status_change( $resv->id, 'cancelled' );
				}
				wp_redirect( add_query_arg( 'resv_msg', 'cancelled', home_url('/') ) );
				exit;
			} else {
				wp_die( esc_html__( 'This reservation cannot be cancelled at this time.', 'order100' ) );
			}
		}
	}

	/**
	 * Cleanup on plugin deactivation — clear cron
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'o100_reservation_send_reminders' );
		wp_clear_scheduled_hook( 'o100_reservation_send_post_dining' );
	}
}
