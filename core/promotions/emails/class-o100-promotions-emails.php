<?php
/**
 * O100 Promotions Emails Registration
 *
 * Handles registering WC_Email classes for promo campaigns.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Promotions_Emails {

	public static function init() {
		// Hook into WooCommerce to register our custom email classes
		add_filter( 'woocommerce_email_classes', [ __CLASS__, 'register_email_classes' ] );

		// Hook into our internal events to trigger the WC_Email classes
		add_action( 'o100_promo_win_back', [ __CLASS__, 'trigger_win_back' ], 10, 2 );
		add_action( 'o100_promo_campaign', [ __CLASS__, 'trigger_campaign' ], 10, 2 );

		// Enforce email active status dynamically based on promo module campaigns
		add_filter( 'o100_email_template_status', [ __CLASS__, 'enforce_email_status' ], 10, 2 );
	}

	public static function enforce_email_status( $status, $template_name ) {
		if ( strpos( $template_name, 'o100_promo_' ) !== 0 ) {
			return $status;
		}

		// Ensure Promotions DB is loaded
		if ( ! class_exists( 'O100_Promotions_DB' ) ) {
			$db_file = O100_PATH . 'core/promotions/engine/class-o100-promotions-db.php';
			if ( file_exists( $db_file ) ) {
				require_once $db_file;
			}
		}

		// If promo module has any active campaigns, consider the promo emails active
		if ( class_exists( 'O100_Promotions_DB' ) ) {
			$active_promos = O100_Promotions_DB::query( [ 'status' => 'active', 'limit' => 1 ] );
			if ( ! empty( $active_promos ) ) {
				return 'active';
			}
		}

		return 'inactive';
	}

	public static function register_email_classes( $emails ) {
		require_once O100_PATH . 'core/promotions/emails/class-wc-email-o100-promo-win-back.php';
		require_once O100_PATH . 'core/promotions/emails/class-wc-email-o100-promo-campaign.php';

		$emails['WC_Email_O100_Promo_Win_Back'] = new WC_Email_O100_Promo_Win_Back();
		$emails['WC_Email_O100_Promo_Campaign'] = new WC_Email_O100_Promo_Campaign();

		return $emails;
	}

	public static function trigger_win_back( $user_id, $promo_data ) {
		do_action( 'o100_trigger_promo_win_back_email_notification', $user_id, $promo_data );
	}

	public static function trigger_campaign( $user_id, $campaign_data ) {
		do_action( 'o100_trigger_promo_campaign_email_notification', $user_id, $campaign_data );
	}
}
