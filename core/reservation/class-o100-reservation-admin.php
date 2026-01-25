<?php
/**
 * Reservation Admin — List Table & Management
 *
 * Renders the WP_List_Table for reservations in the admin panel.
 * Handles status transitions, bulk actions, and inline operations.
 *
 * @package Order100
 * @since   1.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not loaded yet
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class O100_Reservation_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Handle single-row and bulk actions (confirm, cancel, delete)
	 */
	public function handle_actions() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		if ( $page !== 'o100-reservations' ) {
			return;
		}

		// Single row action: confirm / cancel
		if ( isset( $_GET['o100_resv_action'] ) && isset( $_GET['o100_resv_id'] ) ) {
			$action = sanitize_text_field( $_GET['o100_resv_action'] );
			$id     = intval( $_GET['o100_resv_id'] );

			if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'o100_resv_action_' . $id ) ) {
				wp_die( __( 'Security check failed.', 'order100' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( __( 'Insufficient permissions.', 'order100' ) );
			}

			if ( in_array( $action, array( 'confirm', 'cancel' ), true ) ) {
				$new_status = $action === 'confirm' ? 'confirmed' : 'cancelled';
				O100_Reservation_DB::update_status( $id, $new_status );

				// Send email notification to guest
				if ( class_exists( 'O100_Reservation_Notify' ) ) {
					O100_Reservation_Notify::send_status_change( $id, $new_status );
				}
			}

			if ( $action === 'delete' ) {
				O100_Reservation_DB::delete( $id );
			}

			wp_redirect( remove_query_arg( array( 'o100_resv_action', 'o100_resv_id', '_wpnonce' ) ) );
			exit;
		}

		// Bulk actions
		if ( isset( $_POST['o100_resv_bulk_action'] ) && isset( $_POST['o100_resv_ids'] ) ) {
			if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'o100_resv_bulk' ) ) {
				wp_die( __( 'Security check failed.', 'order100' ) );
			}

			$action = sanitize_text_field( $_POST['o100_resv_bulk_action'] );
			$ids    = array_map( 'intval', (array) $_POST['o100_resv_ids'] );

			foreach ( $ids as $id ) {
				if ( $action === 'confirm' ) {
					O100_Reservation_DB::update_status( $id, 'confirmed' );
				} elseif ( $action === 'cancel' ) {
					O100_Reservation_DB::update_status( $id, 'cancelled' );
				} elseif ( $action === 'delete' ) {
					O100_Reservation_DB::delete( $id );
				}
			}

			wp_redirect( remove_query_arg( array( 'o100_resv_bulk_action', 'o100_resv_ids' ) ) );
			exit;
		}
	}

	/**
	 * Render the admin reservations page
	 */
	public static function render_page() {
		$list_table = new O100_Reservation_List_Table();
		$list_table->prepare_items();

		// Get stats for quick tabs
		$stats = self::get_stats();
		$current_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$current_period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : '';

		?>
		<div class="o100-resv-page">
			<!-- Quick period tabs -->
			<div class="o100-resv-tabs">
				<?php
				$periods = array(
					''          => __( 'All', 'order100' ),
					'today'     => __( 'Today', 'order100' ),
					'this_week' => __( 'This Week', 'order100' ),
					'upcoming'  => __( 'Upcoming', 'order100' ),
				);
				foreach ( $periods as $key => $label ) {
					$active = $current_period === $key ? ' o100-resv-tab-active' : '';
					$url = add_query_arg( array( 'page' => 'o100-reservations', 'period' => $key ), admin_url( 'admin.php' ) );
					echo '<a href="' . esc_url( $url ) . '" class="o100-resv-tab' . $active . '">' . esc_html( $label ) . '</a>';
				}
				?>
			</div>

			<!-- Status filter pills -->
			<div class="o100-resv-status-pills">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'o100-reservations', 'period' => $current_period ), admin_url( 'admin.php' ) ) ); ?>" 
				   class="o100-resv-pill<?php echo $current_filter === '' ? ' active' : ''; ?>">
					<?php esc_html_e( 'All', 'order100' ); ?> <span class="count">(<?php echo intval( $stats['total'] ); ?>)</span>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'o100-reservations', 'status' => 'pending', 'period' => $current_period ), admin_url( 'admin.php' ) ) ); ?>"
				   class="o100-resv-pill o100-resv-pill--pending<?php echo $current_filter === 'pending' ? ' active' : ''; ?>">
					<?php esc_html_e( 'Pending', 'order100' ); ?> <span class="count">(<?php echo intval( $stats['pending'] ); ?>)</span>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'o100-reservations', 'status' => 'confirmed', 'period' => $current_period ), admin_url( 'admin.php' ) ) ); ?>"
				   class="o100-resv-pill o100-resv-pill--confirmed<?php echo $current_filter === 'confirmed' ? ' active' : ''; ?>">
					<?php esc_html_e( 'Confirmed', 'order100' ); ?> <span class="count">(<?php echo intval( $stats['confirmed'] ); ?>)</span>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'o100-reservations', 'status' => 'cancelled', 'period' => $current_period ), admin_url( 'admin.php' ) ) ); ?>"
				   class="o100-resv-pill o100-resv-pill--cancelled<?php echo $current_filter === 'cancelled' ? ' active' : ''; ?>">
					<?php esc_html_e( 'Cancelled', 'order100' ); ?> <span class="count">(<?php echo intval( $stats['cancelled'] ); ?>)</span>
				</a>
			</div>

			<!-- Bulk actions form -->
			<form method="post" id="o100-resv-list-form">
				<?php wp_nonce_field( 'o100_resv_bulk' ); ?>

				<div class="o100-resv-bulk-bar">
					<select name="o100_resv_bulk_action" class="o100-resv-bulk-select">
						<option value=""><?php esc_html_e( 'Bulk Actions', 'order100' ); ?></option>
						<option value="confirm"><?php esc_html_e( 'Confirm', 'order100' ); ?></option>
						<option value="cancel"><?php esc_html_e( 'Cancel', 'order100' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete', 'order100' ); ?></option>
					</select>
					<button type="submit" class="o100-resv-bulk-apply"><?php esc_html_e( 'Apply', 'order100' ); ?></button>
				</div>

				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get quick stats (total, pending, confirmed, cancelled)
	 */
	private static function get_stats() {
		global $wpdb;
		$table = O100_Reservation_DB::table_name();

		if ( ! O100_Reservation_DB::table_exists() ) {
			return array( 'total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0 );
		}

		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as cnt FROM {$table} GROUP BY status"
		);

		$stats = array( 'total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0 );
		foreach ( $results as $row ) {
			$stats[ $row->status ] = intval( $row->cnt );
			$stats['total']       += intval( $row->cnt );
		}

		return $stats;
	}
}


/**
 * WP_List_Table implementation for Reservations
 */
class O100_Reservation_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'reservation',
			'plural'   => 'reservations',
			'ajax'     => false,
		) );
	}

	/**
	 * Table columns
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'id'           => '#',
			'booking_type' => __( 'Type', 'order100' ),
			'status'       => __( 'Status', 'order100' ),
			'guest_name'   => __( 'Guest', 'order100' ),
			'party_size'   => __( 'Party', 'order100' ),
			'date_time'    => __( 'Date & Time', 'order100' ),
			'room'         => __( 'Room', 'order100' ),
			'source'       => __( 'Source', 'order100' ),
			'actions'      => __( 'Actions', 'order100' ),
		);
	}

	/**
	 * Sortable columns
	 */
	public function get_sortable_columns() {
		return array(
			'id'           => array( 'id', true ),
			'guest_name'   => array( 'guest_name', false ),
			'party_size'   => array( 'party_size', false ),
			'date_time'    => array( 'reservation_date', true ),
			'status'       => array( 'status', false ),
		);
	}

	/**
	 * Prepare table items
	 */
	public function prepare_items() {
		$per_page = 20;
		$page     = $this->get_pagenum();

		// Required: set column headers for WP_List_Table
		$this->_column_headers = array(
			$this->get_columns(),
			array(), // hidden columns
			$this->get_sortable_columns(),
		);

		$args = array(
			'per_page' => $per_page,
			'page'     => $page,
			'orderby'  => isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'reservation_date',
			'order'    => isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC',
		);

		// Status filter
		if ( ! empty( $_GET['status'] ) ) {
			$args['status'] = sanitize_text_field( $_GET['status'] );
		}

		// Booking type filter
		if ( ! empty( $_GET['booking_type'] ) ) {
			$args['booking_type'] = sanitize_text_field( $_GET['booking_type'] );
		}

		// Search
		if ( ! empty( $_GET['s'] ) ) {
			$args['search'] = sanitize_text_field( $_GET['s'] );
		}

		// Period filter
		if ( ! empty( $_GET['period'] ) ) {
			$today = current_time( 'Y-m-d' );
			switch ( $_GET['period'] ) {
				case 'today':
					$args['date_from'] = $today;
					$args['date_to']   = $today;
					break;
				case 'this_week':
					$args['date_from'] = date( 'Y-m-d', strtotime( 'monday this week', strtotime( $today ) ) );
					$args['date_to']   = date( 'Y-m-d', strtotime( 'sunday this week', strtotime( $today ) ) );
					break;
				case 'upcoming':
					$args['date_from'] = $today;
					$args['order']     = 'ASC';
					break;
			}
		}

		$result = O100_Reservation_DB::get_list( $args );

		$this->items = $result['items'];

		$this->set_pagination_args( array(
			'total_items' => $result['total'],
			'per_page'    => $per_page,
			'total_pages' => ceil( $result['total'] / $per_page ),
		) );
	}

	/**
	 * Checkbox column
	 */
	public function column_cb( $item ) {
		return '<input type="checkbox" name="o100_resv_ids[]" value="' . intval( $item->id ) . '" />';
	}

	/**
	 * ID column
	 */
	public function column_id( $item ) {
		return '<strong>' . intval( $item->id ) . '</strong>';
	}

	/**
	 * Booking type column
	 */
	public function column_booking_type( $item ) {
		$types = array(
			'table'        => '<span class="o100-resv-type o100-resv-type--table" title="Table">🍽️</span>',
			'private_room' => '<span class="o100-resv-type o100-resv-type--room" title="Private Room">🚪</span>',
		);
		return isset( $types[ $item->booking_type ] ) ? $types[ $item->booking_type ] : esc_html( $item->booking_type );
	}

	/**
	 * Status column with colored badges
	 */
	public function column_status( $item ) {
		$badges = array(
			'pending'   => '<span class="o100-resv-badge o100-resv-badge--pending">' . __( 'Pending', 'order100' ) . '</span>',
			'confirmed' => '<span class="o100-resv-badge o100-resv-badge--confirmed">' . __( 'Confirmed', 'order100' ) . '</span>',
			'cancelled' => '<span class="o100-resv-badge o100-resv-badge--cancelled">' . __( 'Cancelled', 'order100' ) . '</span>',
		);
		return isset( $badges[ $item->status ] ) ? $badges[ $item->status ] : esc_html( $item->status );
	}

	/**
	 * Guest column — name, email, phone
	 */
	public function column_guest_name( $item ) {
		$name  = esc_html( $item->guest_name );
		$email = esc_html( $item->guest_email );
		$phone = esc_html( $item->guest_phone );

		$output = '<div class="o100-resv-guest">';
		$output .= '<strong>' . $name . '</strong>';
		if ( $email ) {
			$output .= '<br><span class="o100-resv-guest-detail">' . $email . '</span>';
		}
		if ( $phone ) {
			$output .= '<br><span class="o100-resv-guest-detail">' . $phone . '</span>';
		}
		$output .= '</div>';

		return $output;
	}

	/**
	 * Party size column
	 */
	public function column_party_size( $item ) {
		return '<span class="o100-resv-party">' . intval( $item->party_size ) . '</span>';
	}

	/**
	 * Date & Time column
	 */
	public function column_date_time( $item ) {
		$date = date_i18n( get_option( 'date_format' ), strtotime( $item->reservation_date ) );
		$time = esc_html( $item->reservation_time );

		return '<div class="o100-resv-datetime">'
			. '<strong>' . $date . '</strong>'
			. '<br><span class="o100-resv-time">' . $time . '</span>'
			. '</div>';
	}

	/**
	 * Room column
	 */
	public function column_room( $item ) {
		if ( $item->booking_type !== 'private_room' || $item->room_type_id === null ) {
			return '<span class="o100-resv-na">—</span>';
		}

		$settings = get_option( 'o100_reservation', array() );
		$rooms    = isset( $settings['o100_resv_rooms'] ) ? $settings['o100_resv_rooms'] : array();

		if ( isset( $rooms[ $item->room_type_id ]['name'] ) ) {
			return esc_html( $rooms[ $item->room_type_id ]['name'] );
		}

		return __( 'Room', 'order100' ) . ' #' . intval( $item->room_type_id );
	}

	/**
	 * Source column
	 */
	public function column_source( $item ) {
		$icons = array(
			'website' => '🌐',
			'app'     => '📱',
			'phone'   => '📞',
		);
		$icon = isset( $icons[ $item->source ] ) ? $icons[ $item->source ] : '🌐';
		return $icon . ' ' . ucfirst( esc_html( $item->source ) );
	}

	/**
	 * Actions column
	 */
	public function column_actions( $item ) {
		$actions = array();

		if ( $item->status === 'pending' ) {
			$confirm_url = wp_nonce_url(
				add_query_arg( array(
					'page'             => 'o100-reservations',
					'o100_resv_action' => 'confirm',
					'o100_resv_id'     => $item->id,
				), admin_url( 'admin.php' ) ),
				'o100_resv_action_' . $item->id
			);
			$actions[] = '<a href="' . esc_url( $confirm_url ) . '" class="o100-resv-action o100-resv-action--confirm" title="' . esc_attr__( 'Confirm', 'order100' ) . '">✓ ' . __( 'Confirm', 'order100' ) . '</a>';
		}

		if ( $item->status !== 'cancelled' ) {
			$cancel_url = wp_nonce_url(
				add_query_arg( array(
					'page'             => 'o100-reservations',
					'o100_resv_action' => 'cancel',
					'o100_resv_id'     => $item->id,
				), admin_url( 'admin.php' ) ),
				'o100_resv_action_' . $item->id
			);
			$actions[] = '<a href="' . esc_url( $cancel_url ) . '" class="o100-resv-action o100-resv-action--cancel" title="' . esc_attr__( 'Cancel', 'order100' ) . '">✗ ' . __( 'Cancel', 'order100' ) . '</a>';
		}

		// Special requests tooltip
		if ( ! empty( $item->special_requests ) ) {
			$actions[] = '<span class="o100-resv-action o100-resv-action--note" title="' . esc_attr( $item->special_requests ) . '">💬</span>';
		}

		return implode( ' · ', $actions );
	}

	/**
	 * No items found message
	 */
	public function no_items() {
		echo '<div class="o100-resv-empty">';
		echo '<span class="dashicons dashicons-calendar-alt" style="font-size:48px;width:48px;height:48px;color:#cbd5e1;"></span>';
		echo '<p>' . esc_html__( 'No reservations found.', 'order100' ) . '</p>';
		echo '</div>';
	}
}


// TS: 20260104123439

// TS: 20260123164622
