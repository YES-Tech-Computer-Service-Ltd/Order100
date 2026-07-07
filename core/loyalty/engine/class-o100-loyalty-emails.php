<?php
/**
 * O100 Loyalty Emails Registration
 *
 * Handles registering WC_Email classes for loyalty campaigns.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Loyalty_Emails {

	public static function init() {
		// Hook into WooCommerce to register our custom email classes
		add_filter( 'woocommerce_email_classes', [ __CLASS__, 'register_email_classes' ] );

		// Hook into our internal events to trigger the WC_Email classes
		add_action( 'o100_loyalty_auto_reward_issued', [ __CLASS__, 'trigger_reward_issued' ], 10, 5 );
		add_action( 'o100_loyalty_auto_reward_expiring', [ __CLASS__, 'trigger_reward_expiring' ], 10, 5 );
		add_action( 'o100_trigger_loyalty_points_expiring_email', [ __CLASS__, 'trigger_points_expiring' ], 10, 3 );
		add_action( 'o100_loyalty_daily_birthday_cron', [ __CLASS__, 'trigger_birthday' ], 10, 1 );
		add_action( 'o100_loyalty_points_earned', [ __CLASS__, 'trigger_points_earned' ], 10, 2 );
		add_action( 'o100_loyalty_tier_upgraded', [ __CLASS__, 'trigger_tier_upgrade' ], 10, 2 );
		add_action( 'o100_loyalty_referral_invite', [ __CLASS__, 'trigger_referral_invite' ], 10, 3 );
		add_action( 'o100_loyalty_referral_reward', [ __CLASS__, 'trigger_referral_reward' ], 10, 3 );
		add_action( 'o100_loyalty_punch_card_updated', [ __CLASS__, 'trigger_punch_card_update' ], 10, 4 );

		// Enforce email active status dynamically based on loyalty module campaigns
		add_filter( 'o100_email_template_status', [ __CLASS__, 'enforce_email_status' ], 10, 2 );
	}

	public static function enforce_email_status( $status, $template_name ) {
		if ( strpos( $template_name, 'o100_loyalty_' ) !== 0 ) {
			return $status;
		}

		if ( ! class_exists( 'O100_Loyalty_DB' ) ) {
			return 'inactive';
		}

		$campaigns = O100_Loyalty_DB::get_campaigns();
		$active_types = [];
		if ( ! empty( $campaigns ) ) {
			foreach ( $campaigns as $camp ) {
				if ( $camp->status === 'active' || $camp->status === 1 ) {
					$active_types[] = $camp->type;
				}
			}
		}

		// Birthday Greeting
		if ( $template_name === 'o100_loyalty_birthday' && in_array( 'birthday', $active_types ) ) {
			return 'active';
		}
		// Referral
		if ( in_array( $template_name, [ 'o100_loyalty_referral_invite', 'o100_loyalty_referral_reward' ] ) && in_array( 'referral', $active_types ) ) {
			return 'active';
		}
		// General points
		$points_active = false;
		$punch_card_active = false;
		foreach ( $active_types as $t ) {
			if ( strpos( $t, 'punch_card' ) !== false ) {
				$punch_card_active = true;
			}
			if ( strpos( $t, 'points' ) !== false || strpos( $t, 'punch_card' ) !== false || in_array( $t, ['pickup_bonus', 'signup', 'product_review', 'facebook_share', 'twitter_share', 'whatsapp_share', 'email_share', 'profile_bonus', 'preorder_bonus', 'monthly_reward', 'subtotal', 'automation'] ) ) {
				$points_active = true;
			}
		}

		if ( $punch_card_active && $template_name === 'o100_loyalty_punch_card_update' ) {
			return 'active';
		}

		if ( $points_active && in_array( $template_name, [ 'o100_loyalty_points_earned', 'o100_loyalty_tier_upgrade', 'o100_loyalty_reward_issued', 'o100_loyalty_reward_expiring' ] ) ) {
			return 'active';
		}

		return 'inactive';
	}

	public static function register_email_classes( $emails ) {
		require_once O100_PATH . 'core/loyalty/emails/class-wc-email-o100-loyalty-birthday.php';
		require_once O100_PATH . 'core/loyalty/emails/class-wc-email-o100-loyalty-points-earned.php';
		require_once O100_PATH . 'core/loyalty/emails/class-wc-email-o100-loyalty-tier-upgrade.php';
		require_once O100_PATH . 'core/loyalty/emails/class-wc-email-o100-loyalty-reward-issued.php';
		require_once O100_PATH . 'core/loyalty/emails/class-wc-email-o100-loyalty-reward-expiring.php';
		require_once O100_PATH . 'core/loyalty/emails/class-wc-email-o100-loyalty-points-expiring.php';
		require_once O100_PATH . 'core/loyalty/emails/class-wc-email-o100-loyalty-referral-invite.php';
		require_once O100_PATH . 'core/loyalty/emails/class-wc-email-o100-loyalty-referral-reward.php';
		require_once O100_PATH . 'core/loyalty/emails/class-wc-email-o100-loyalty-punch-card-update.php';

		$emails['WC_Email_O100_Loyalty_Birthday'] = new WC_Email_O100_Loyalty_Birthday();
		$emails['WC_Email_O100_Loyalty_Points_Earned'] = new WC_Email_O100_Loyalty_Points_Earned();
		$emails['WC_Email_O100_Loyalty_Tier_Upgrade'] = new WC_Email_O100_Loyalty_Tier_Upgrade();
		$emails['WC_Email_O100_Loyalty_Reward_Issued'] = new WC_Email_O100_Loyalty_Reward_Issued();
		$emails['WC_Email_O100_Loyalty_Reward_Expiring'] = new WC_Email_O100_Loyalty_Reward_Expiring();
		$emails['WC_Email_O100_Loyalty_Points_Expiring'] = new WC_Email_O100_Loyalty_Points_Expiring();
		$emails['WC_Email_O100_Loyalty_Referral_Invite'] = new WC_Email_O100_Loyalty_Referral_Invite();
		$emails['WC_Email_O100_Loyalty_Referral_Reward'] = new WC_Email_O100_Loyalty_Referral_Reward();
		$emails['WC_Email_O100_Loyalty_Punch_Card_Update'] = new WC_Email_O100_Loyalty_Punch_Card_Update();

		return $emails;
	}

	public static function trigger_reward_issued( $user_id, $campaign_id, $code, $promo_id, $rule ) {
		do_action( 'o100_trigger_loyalty_reward_issued_email', $user_id, $code, $rule );
	}

	public static function trigger_reward_expiring( $user_id, $campaign_id, $code, $rule, $days_left ) {
		do_action( 'o100_trigger_loyalty_reward_expiring_email', $user_id, $code, $days_left );
	}

	public static function trigger_points_expiring( $user_id, $points, $expiry_date ) {
		// Just passing these through to WooCommerce email system.
		// WooCommerce email will catch this via the hook we define inside the email class.
		do_action( 'o100_trigger_wc_loyalty_points_expiring', $user_id, $points, $expiry_date );
	}

	public static function trigger_birthday( $user_id ) {
		do_action( 'o100_trigger_loyalty_birthday_email', $user_id );
	}

	public static function trigger_points_earned( $user_id, $points ) {
		do_action( 'o100_trigger_loyalty_points_earned_email', $user_id, $points );
	}

	public static function trigger_tier_upgrade( $user_id, $new_tier ) {
		do_action( 'o100_trigger_loyalty_tier_upgrade_email', $user_id, $new_tier );
	}

	public static function trigger_referral_invite( $advocate_id, $friend_email, $referral_link ) {
		do_action( 'o100_trigger_loyalty_referral_invite_email', $advocate_id, $friend_email, $referral_link );
	}

	public static function trigger_referral_reward( $advocate_id, $friend_id, $reward_data ) {
		do_action( 'o100_trigger_loyalty_referral_reward_email', $advocate_id, $friend_id, $reward_data );
	}

	public static function trigger_punch_card_update( $user_id, $earned_stamps, $total_stamps, $required_stamps ) {
		do_action( 'o100_trigger_loyalty_punch_card_update_email_notification', $user_id, $earned_stamps, $total_stamps, $required_stamps );
	}
}
