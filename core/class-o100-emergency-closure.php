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
	 * @return array|false  False if open; array {reason, expires_at, starts_at, scope} if closed.
	 */
	public static function get_active_closure_data( $branch_id = 0 ) {
		$now = time();

		// Check branch level first
		if ( $branch_id > 0 ) {
			$b_is_set = get_post_meta( $branch_id, 'o100_emergency_closure', true ) === 'on';
			if ( $b_is_set ) {
				$b_starts  = intval( get_post_meta( $branch_id, 'o100_emergency_closure_starts', true ) );
				$b_expires = intval( get_post_meta( $branch_id, 'o100_emergency_closure_expires', true ) );
				$b_reason  = get_post_meta( $branch_id, 'o100_emergency_closure_reason', true );

				$active = true;
				if ( $b_starts > 0 && $now < $b_starts ) {
					$active = false;
				}
				if ( $b_expires > 0 && $now > $b_expires ) {
					$active = false; // already expired
				}

				if ( $active ) {
					return array(
						'reason'     => $b_reason,
						'expires_at' => $b_expires,
						'starts_at'  => $b_starts,
						'scope'      => 'branch',
					);
				}
			}
		}

		// Fallback to Global
		$options = get_option( 'o100_options', array() );
		$is_set = isset( $options['o100_emergency_closure'] ) && $options['o100_emergency_closure'] === 'on';
		if ( ! $is_set ) {
			return false;
		}

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
			'scope'      => 'global',
		);
	}

	/**
	 * Get pending scheduled closure data (set but not yet started).
	 */
	public static function get_pending_closure_data( $branch_id = 0 ) {
		$now = time();

		// Check branch level first
		if ( $branch_id > 0 ) {
			$b_is_set = get_post_meta( $branch_id, 'o100_emergency_closure', true ) === 'on';
			if ( $b_is_set ) {
				$b_starts = intval( get_post_meta( $branch_id, 'o100_emergency_closure_starts', true ) );
				if ( $b_starts > 0 && $now < $b_starts ) {
					return array(
						'reason'     => get_post_meta( $branch_id, 'o100_emergency_closure_reason', true ),
						'starts_at'  => $b_starts,
						'expires_at' => intval( get_post_meta( $branch_id, 'o100_emergency_closure_expires', true ) ),
						'scope'      => 'branch',
					);
				}
			}
		}

		// Fallback to Global
		$options = get_option( 'o100_options', array() );
		$is_set = isset( $options['o100_emergency_closure'] ) && $options['o100_emergency_closure'] === 'on';
		if ( ! $is_set ) return false;

		$starts_at = isset( $options['o100_emergency_closure_starts'] ) ? intval( $options['o100_emergency_closure_starts'] ) : 0;
		if ( $starts_at <= 0 || $now >= $starts_at ) return false;

		return array(
			'reason'     => isset( $options['o100_emergency_closure_reason'] ) ? $options['o100_emergency_closure_reason'] : '',
			'starts_at'  => $starts_at,
			'expires_at' => isset( $options['o100_emergency_closure_expires'] ) ? intval( $options['o100_emergency_closure_expires'] ) : 0,
			'scope'      => 'global',
		);
	}

	/**
	 * Get the full dictionary of all closures (global + all branches)
	 * For UI initialization
	 */
	public static function get_all_closures_status() {
		$statuses = array();

		// Get Global
		$options = get_option( 'o100_options', array() );
		$is_set = isset( $options['o100_emergency_closure'] ) && $options['o100_emergency_closure'] === 'on';
		$now = time();

		$starts_at  = isset( $options['o100_emergency_closure_starts'] ) ? intval( $options['o100_emergency_closure_starts'] ) : 0;
		$expires_at = isset( $options['o100_emergency_closure_expires'] ) ? intval( $options['o100_emergency_closure_expires'] ) : 0;
		$reason     = isset( $options['o100_emergency_closure_reason'] ) ? $options['o100_emergency_closure_reason'] : '';

		$mode = 'open';
		if ( $is_set ) {
			if ( $starts_at > 0 && $now < $starts_at ) {
				$mode = 'scheduled';
			} elseif ( $expires_at === 0 || $now <= $expires_at ) {
				$mode = 'close_now';
			}
		}
		
		$statuses['all'] = array(
			'mode'       => $mode,
			'starts_at'  => $starts_at,
			'expires_at' => $expires_at,
			'reason'     => $reason,
		);

		// Get Branches
		if ( get_option('o100_locations_status') === 'on' ) {
			$branches = get_posts( array(
				'post_type'      => 'o100_location',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			) );

			foreach ( $branches as $b ) {
				$bid = $b->ID;
				$b_is_set = get_post_meta( $bid, 'o100_emergency_closure', true ) === 'on';
				
				$b_mode = 'open';
				$b_starts = 0;
				$b_expires = 0;
				$b_reason = '';

				if ( $b_is_set ) {
					$b_starts  = intval( get_post_meta( $bid, 'o100_emergency_closure_starts', true ) );
					$b_expires = intval( get_post_meta( $bid, 'o100_emergency_closure_expires', true ) );
					$b_reason  = get_post_meta( $bid, 'o100_emergency_closure_reason', true );

					if ( $b_starts > 0 && $now < $b_starts ) {
						$b_mode = 'scheduled';
					} elseif ( $b_expires === 0 || $now <= $b_expires ) {
						$b_mode = 'close_now';
					}
				}

				$statuses[ $bid ] = array(
					'mode'       => $b_mode,
					'starts_at'  => $b_starts,
					'expires_at' => $b_expires,
					'reason'     => $b_reason,
				);
			}
		}

		return $statuses;
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
		$branch_id = 0;
		if ( function_exists( 'WC' ) && WC()->session ) {
			$branch_id = intval( WC()->session->get( 'o100_location_id' ) );
		}

		$closure = self::get_active_closure_data( $branch_id );
		if ( $closure !== false ) {
			$msg = ! empty( $closure['reason'] ) ? $closure['reason'] : __( 'We are temporarily closed and not accepting orders.', 'order100' );
			if ( $closure['scope'] === 'branch' ) {
				$branch_title = get_the_title( $branch_id );
				$msg = sprintf( __( '%s is temporarily closed: %s', 'order100' ), $branch_title, $msg );
			}
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

		$all_status = self::get_all_closures_status();
		$global = $all_status['all'];

		$icon_color = '#00a32a';
		$title_text = __( 'Store: OPEN', 'order100' );

		if ( $global['mode'] === 'close_now' ) {
			$icon_color = '#d63638';
			$title_text = __( 'Emergency: CLOSED', 'order100' );
		} elseif ( $global['mode'] === 'scheduled' ) {
			$icon_color = '#dba617';
			$title_text = __( 'Closure Scheduled', 'order100' );
		} else {
			// Check branches
			$closed_branches = 0;
			$scheduled_branches = 0;
			$total_branches = 0;

			foreach ( $all_status as $k => $v ) {
				if ( $k === 'all' ) continue;
				$total_branches++;
				if ( $v['mode'] === 'close_now' ) $closed_branches++;
				if ( $v['mode'] === 'scheduled' ) $scheduled_branches++;
			}

			if ( $closed_branches > 0 ) {
				if ( $closed_branches === $total_branches ) {
					$icon_color = '#d63638';
					$title_text = __( 'Emergency: CLOSED', 'order100' );
				} else {
					$icon_color = '#dba617';
					$title_text = __( 'Partial Closure', 'order100' );
				}
			} elseif ( $scheduled_branches > 0 ) {
				$icon_color = '#dba617';
				$title_text = __( 'Closure Scheduled', 'order100' );
			}
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
		include dirname( __FILE__ ) . '/class-o100-emergency-closure-modal.php';
	}
	/**
	 * AJAX Handler for saving emergency status
	 */
	public function ajax_save_status() {
		check_ajax_referer( 'o100_emergency_nonce', 'security' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$branch_id   = isset( $_POST['branch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['branch_id'] ) ) : 'all';
		$mode        = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'open';
		$resume_type = isset( $_POST['resume_type'] ) ? sanitize_text_field( wp_unslash( $_POST['resume_type'] ) ) : '';
		$resume_date = isset( $_POST['resume_date'] ) ? sanitize_text_field( wp_unslash( $_POST['resume_date'] ) ) : '';
		$start_date  = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date    = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		$reason      = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

		$tz = wp_timezone();
		$now = time();

		$data = array(
			'is_set'  => '0',
			'reason'  => '',
			'starts'  => 0,
			'expires' => 0,
		);

		if ( $mode === 'close_now' ) {
			$data['is_set'] = 'on';
			$data['reason'] = $reason;

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

			$data['expires'] = $expires_at;

		} elseif ( $mode === 'scheduled' ) {
			if ( empty( $start_date ) || empty( $end_date ) ) {
				wp_send_json_error( __( 'Start and end times are required for scheduled closure.', 'order100' ) );
			}
			$dt_start = new DateTime( $start_date, $tz );
			$dt_end   = new DateTime( $end_date, $tz );

			$data['is_set']  = 'on';
			$data['reason']  = $reason;
			$data['starts']  = $dt_start->getTimestamp();
			$data['expires'] = $dt_end->getTimestamp();
		}

		if ( $branch_id === 'all' ) {
			$options = get_option( 'o100_options', array() );
			$options['o100_emergency_closure']         = $data['is_set'];
			$options['o100_emergency_closure_reason']  = $data['reason'];
			$options['o100_emergency_closure_starts']  = $data['starts'];
			$options['o100_emergency_closure_expires'] = $data['expires'];
			update_option( 'o100_options', $options );
		} else {
			$bid = intval( $branch_id );
			update_post_meta( $bid, 'o100_emergency_closure', $data['is_set'] );
			update_post_meta( $bid, 'o100_emergency_closure_reason', $data['reason'] );
			update_post_meta( $bid, 'o100_emergency_closure_starts', $data['starts'] );
			update_post_meta( $bid, 'o100_emergency_closure_expires', $data['expires'] );
		}

		wp_send_json_success( array( 'mode' => $mode, 'branch' => $branch_id ) );
	}

	/**
	 * Inject frontend global banner if closed
	 */
	public function inject_frontend_banner() {
		if ( is_admin() || wp_is_json_request() ) {
			return;
		}

		$branch_id = 0;
		if ( function_exists( 'WC' ) && WC()->session ) {
			$branch_id = intval( WC()->session->get( 'o100_location_id' ) );
		}

		$closure_data = self::get_active_closure_data( $branch_id );
		if ( $closure_data === false ) {
			return;
		}

		$reason = ! empty( $closure_data['reason'] ) ? $closure_data['reason'] : __( 'Temporarily paused', 'order100' );
		if ( $closure_data['scope'] === 'branch' ) {
			$branch_title = get_the_title( $branch_id );
			$reason = $branch_title . ' — ' . $reason;
		}
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



