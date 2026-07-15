<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/class-o100-loyalty-campaign-controller.php';
require_once __DIR__ . '/class-o100-loyalty-settings-controller.php';
require_once __DIR__ . '/class-o100-loyalty-birthday-controller.php';
require_once __DIR__ . '/class-o100-loyalty-misc-controller.php';
require_once __DIR__ . '/class-o100-loyalty-frontend-api.php';

class O100_Loyalty_API {

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );

		add_action( 'user_register', [ 'O100_Loyalty_Birthday_Controller', 'process_guest_birthday_cookie' ] );
		add_action( 'wp_login', [ 'O100_Loyalty_Birthday_Controller', 'process_guest_birthday_cookie' ], 10, 2 );
	}

	public static function register_rest_routes() {
			register_rest_route( 'o100/v1', '/loyalty/campaign(?:/(?P<id>\d+))?', [
				[
					'methods'             => 'GET',
					'callback'            => [ __CLASS__, 'rest_get_campaign' ],
					'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ __CLASS__, 'rest_save_campaign' ],
					'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ __CLASS__, 'rest_delete_campaign' ],
					'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
				]
			] );
			register_rest_route( 'o100/v1', '/loyalty/campaign/(?P<id>\d+)/toggle', [
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'rest_toggle_campaign_status' ],
				'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
			] );
	
			register_rest_route( 'o100/v1', '/loyalty/settings', [
				[
					'methods'             => 'GET',
					'callback'            => [ __CLASS__, 'rest_get_proxy_settings' ],
					'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ __CLASS__, 'rest_save_proxy_settings' ],
					'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
				]
			] );
			register_rest_route( 'o100/v1', '/loyalty/settings/conversion', [
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'rest_check_conversion_rate' ],
				'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
			] );
			register_rest_route( 'o100/v1', '/loyalty/booster', [
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'rest_save_booster' ],
				'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
			] );
			register_rest_route( 'o100/v1', '/loyalty/referral-coupon', [
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'rest_create_referral_coupon' ],
				'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
			] );
			register_rest_route( 'o100/v1', '/loyalty/products/categories', [
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'rest_search_categories' ],
				'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
			] );
			register_rest_route( 'o100/v1', '/loyalty/settings/birthday', [
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'rest_check_birthday_settings' ],
				'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
			] );
	
			register_rest_route( 'o100/v1', '/loyalty/birthday', 			[
				'methods'             => 'POST',
				'callback'            => [ 'O100_Loyalty_Birthday_Controller', 'handle_save_birthday' ],
				'permission_callback' => '__return_true', // Birthday logic has its own validation
			] );

		// Frontend API routes
		register_rest_route( 'o100/v1', '/loyalty/frontend/apply_reward', [
			'methods'             => 'POST',
			'callback'            => [ 'O100_Loyalty_Frontend_API', 'rest_apply_reward_to_cart' ],
			'permission_callback' => [ 'O100_Loyalty_Frontend_API', 'check_user_permissions' ],
		] );

		register_rest_route( 'o100/v1', '/loyalty/frontend/redeem_punch_card', [
			'methods'             => 'POST',
			'callback'            => [ 'O100_Loyalty_Frontend_API', 'rest_redeem_punch_card' ],
			'permission_callback' => [ 'O100_Loyalty_Frontend_API', 'check_user_permissions' ],
		] );

		register_rest_route( 'o100/v1', '/loyalty/frontend/redeem_points', [
			'methods'             => 'POST',
			'callback'            => [ 'O100_Loyalty_Frontend_API', 'rest_redeem_points' ],
			'permission_callback' => [ 'O100_Loyalty_Frontend_API', 'check_user_permissions' ],
		] );

		register_rest_route( 'o100/v1', '/loyalty/frontend/social_share', [
			'methods'             => 'POST',
			'callback'            => [ 'O100_Loyalty_Frontend_API', 'rest_social_share' ],
			'permission_callback' => [ 'O100_Loyalty_Frontend_API', 'check_user_permissions' ],
		] );
	}

	public static function rest_get_campaign( WP_REST_Request $request ) {
			$campaign_id = (int) $request->get_param( 'id' );
			if ( $campaign_id > 0 ) {
				$campaign = O100_Loyalty_DB::get_campaign( $campaign_id );
				if ( $campaign ) {
					// Decode JSON fields
					$campaign->point_rule = !empty( $campaign->earn_config ) ? json_decode( $campaign->earn_config, true ) : [];
					$campaign->conditions = !empty( $campaign->conditions ) ? json_decode( $campaign->conditions, true ) : [];
					// Map fields
					$campaign->name        = $campaign->title;
					$campaign->action_type = $campaign->type;
					$campaign->active      = $campaign->status === 'active' ? 1 : 0;
					// Extract UI config
					if ( isset( $campaign->point_rule['reward_config'] ) ) {
						$rc = $campaign->point_rule['reward_config'];
						if ( isset( $rc['required_stamps'] ) ) $campaign->punch_count = $rc['required_stamps'];
						if ( isset( $rc['reward_products'][0] ) ) $campaign->punch_reward_product = $rc['reward_products'][0];
					}
					return O100_REST_Controller::success( [ 'campaign' => $campaign ] );
				}
			}
			return O100_REST_Controller::error( 'Campaign not found.', 404 );
		}

	public static function rest_delete_campaign( WP_REST_Request $request ) {
			$campaign_id = (int) $request->get_param( 'id' );
			if ( $campaign_id > 0 ) {
				O100_Loyalty_DB::delete_campaign( $campaign_id );
				return O100_REST_Controller::success();
			}
			return O100_REST_Controller::error( 'Invalid campaign ID.' );
		}

	public static function rest_toggle_campaign_status( WP_REST_Request $request ) {
			$campaign_id = (int) $request->get_param( 'id' );
			$new_status = $request->get_param( 'status' );
			if ( $campaign_id > 0 ) {
				$status_val = $new_status === 'active' ? 'active' : 'disabled';
				O100_Loyalty_DB::update_campaign( $campaign_id, [ 'status' => $status_val ] );
				return O100_REST_Controller::success();
			}
			return O100_REST_Controller::error( 'Invalid campaign ID.' );
		}

	public static function rest_save_campaign( WP_REST_Request $request ) {
			$_POST = $request->get_params(); // map all rest params to POST for the legacy handler
			ob_start();
			try {
				O100_Loyalty_Campaign_Controller::handle_save_wizard();
			} catch (Exception $e) {
				ob_end_clean();
				return O100_REST_Controller::error( $e->getMessage() );
			}
			$output = ob_get_clean();
			$decoded = json_decode($output, true);
			if ($decoded) {
				if ($decoded['success']) {
					return O100_REST_Controller::success( $decoded['data'] );
				} else {
					return O100_REST_Controller::error( isset($decoded['data']['message']) ? $decoded['data']['message'] : 'Save failed' );
				}
			}
			return O100_REST_Controller::error( 'Invalid output from legacy save handler.' );
		}

	public static function rest_get_proxy_settings( WP_REST_Request $request ) {
			return O100_Loyalty_Settings_Controller::get_proxy_settings();
		}

	public static function rest_save_proxy_settings( WP_REST_Request $request ) {
			$params = $request->get_params();
			foreach($params as $k => $v) { $_POST[$k] = $v; }
			return O100_Loyalty_Settings_Controller::save_proxy_settings();
		}

	public static function rest_check_conversion_rate( WP_REST_Request $request ) {
			return O100_Loyalty_Misc_Controller::check_conversion_rate();
		}

	public static function rest_save_booster( WP_REST_Request $request ) {
			$params = $request->get_params();
			foreach($params as $k => $v) { $_POST[$k] = $v; }
			return O100_Loyalty_Misc_Controller::handle_save_booster();
		}

	public static function rest_create_referral_coupon( WP_REST_Request $request ) {
			$params = $request->get_params();
			foreach($params as $k => $v) { $_POST[$k] = $v; }
			return O100_Loyalty_Misc_Controller::handle_create_referral_coupon();
		}

	public static function rest_search_categories( WP_REST_Request $request ) {
			$params = $request->get_params();
			foreach($params as $k => $v) { $_POST[$k] = $v; $_GET[$k] = $v; }
			return O100_Loyalty_Misc_Controller::handle_search_categories();
		}

	public static function rest_save_birthday( WP_REST_Request $request ) {
			$params = $request->get_params();
			foreach($params as $k => $v) { $_POST[$k] = $v; }
			return O100_Loyalty_Birthday_Controller::handle_save_birthday();
		}

	public static function rest_check_birthday_settings( WP_REST_Request $request ) {
			return O100_Loyalty_Birthday_Controller::check_birthday_settings();
		}

}
