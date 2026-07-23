<?php
/**
 * Reservation Admin — SPA Controller
 *
 * Renders the Alpine.js + Tailwind single page application.
 *
 * @package Order100
 * @since   1.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
		// Enqueue scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts( $hook ) {
		if ( $hook !== 'order100_page_o100-reservations' ) {
			return;
		}

		// FullCalendar for Calendar view
		wp_enqueue_script( 'fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', array(), '6.1.10', true );

		// Localized API info
		wp_localize_script( 'o100-admin-app', 'o100_resv_api', array(
			'root'  => esc_url_raw( rest_url( 'o100/v1/reservations' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		) );
	}

	/**
	 * Render the admin reservations SPA page
	 */
	public static function render_page() {
		// Ensure the view directory exists
		$view_file = O100_PATH . 'core/reservation/views/view-reservation-main.php';

		if ( file_exists( $view_file ) ) {
			include $view_file;
		} else {
			echo '<div class="wrap"><h2>' . esc_html__( 'Reservations', 'order100' ) . '</h2><p>' . esc_html__( 'View file not found.', 'order100' ) . '</p></div>';
		}
	}
}
