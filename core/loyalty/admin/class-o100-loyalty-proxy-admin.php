<?php
/**
 * Order100 Loyalty Proxy Admin
 * 
 * Renders the modern Grid and Wizard (Stepper) UI to configure WPLoyalty.
 * Replaces the native React UI and complex CMB2 forms.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Loyalty_Proxy_Admin {

	/**
	 * Init hooks for the proxy admin.
	 */
	public static function init() {
		add_action( 'wp_ajax_o100_save_loyalty_wizard', [ __CLASS__, 'handle_save_wizard' ] );
		add_action( 'wp_ajax_o100_delete_loyalty_campaign', [ __CLASS__, 'handle_delete_campaign' ] );
		add_action( 'wp_ajax_o100_save_booster', [ __CLASS__, 'handle_save_booster' ] );
		add_action( 'wp_ajax_o100_loyalty_toggle_campaign_status', [ __CLASS__, 'handle_toggle_campaign_status' ] );
		add_action( 'wp_ajax_o100_loyalty_check_conversion_rate', [ __CLASS__, 'check_conversion_rate' ] );
		add_action( 'wp_ajax_o100_loyalty_get_campaign', [ __CLASS__, 'handle_get_campaign' ] );
		add_action( 'wp_ajax_o100_save_birthday', [ __CLASS__, 'handle_save_birthday' ] );
		add_action( 'wp_ajax_nopriv_o100_save_birthday', [ __CLASS__, 'handle_save_birthday' ] );
		add_action( 'user_register', [ __CLASS__, 'process_guest_birthday_cookie' ] );
		add_action( 'wp_login', [ __CLASS__, 'process_guest_birthday_cookie' ], 10, 2 );
		add_action( 'wp_ajax_o100_loyalty_get_proxy_settings', [ __CLASS__, 'get_proxy_settings' ] );
		add_action( 'wp_ajax_o100_loyalty_save_proxy_settings', [ __CLASS__, 'save_proxy_settings' ] );
		add_action( 'wp_ajax_o100_create_referral_coupon', [ __CLASS__, 'handle_create_referral_coupon' ] );
		add_action( 'wp_ajax_o100_search_product_categories', [ __CLASS__, 'handle_search_categories' ] );
	}

	/**
	 * Check if a global points conversion rate exists.
	 */
	public static function check_conversion_rate() {
		check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		if ( class_exists( '\Wlr\App\Models\Rewards' ) ) {
			$reward_model = new \Wlr\App\Models\Rewards();
			// WPLoyalty considers redeem_point as the conversion rule
			$rewards = $reward_model->getActiveRewards('redeem_point');
			
			if ( ! empty( $rewards ) ) {
				$rule = $rewards[0]; // Take the first active rule
				// In WPLoyalty, point_rule JSON contains "earn_point" and "earn_reward" or "redeem_point".
				// Usually, coupon_amount is the value of the discount, and points required is in `usage_limits` or `point_rule->point`.
				$point_rule = json_decode($rule->point_rule, true);
				$req_points = isset($point_rule['point']) ? $point_rule['point'] : 100;
				$disc_val   = isset($rule->coupon_amount) ? $rule->coupon_amount : 1;
				
				wp_send_json_success([
					'has_rule' => true,
					'points'   => $req_points,
					'value'    => $disc_val
				]);
			}
		}

		wp_send_json_success([
			'has_rule' => false
		]);
	}

	/**
	 * Quick Booster Save Handler
	 */
	public static function handle_save_booster() {
		check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		if ( ! class_exists( 'O100_Loyalty_DB' ) ) {
			wp_send_json_error( 'Loyalty Engine not loaded' );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
		$action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';
		$points      = isset( $_POST['points'] ) ? intval( $_POST['points'] ) : 0;
		$status      = isset( $_POST['status'] ) ? intval( $_POST['status'] ) : 1;

		if ( empty( $action_type ) ) {
			wp_send_json_error( 'Missing action type' );
		}

		$name_map = [
			'signup'         => 'Account Sign Up',
			'product_review' => 'Product Review',
			'facebook_share' => 'Social Share (Facebook)',
			'twitter_share'  => 'Social Share (X/Twitter)',
			'whatsapp_share' => 'Social Share (WhatsApp)',
			'email_share'    => 'Social Share (Email)',
			'pickup_bonus'   => 'Pickup Bonus',
			'profile_bonus'  => 'Profile Completion',
			'preorder_bonus' => 'Pre-order Bonus'
		];
		$campaign_name = isset( $name_map[ $action_type ] ) ? $name_map[ $action_type ] : 'Quick Booster';

		// Check uniqueness: only 1 booster of each type
		if ( $campaign_id == 0 ) {
			$existing = O100_Loyalty_DB::get_campaigns();
			foreach ( $existing as $ec ) {
				if ( $ec->type === $action_type ) {
					wp_send_json_error( 'A rule for this Booster already exists. Please edit the existing rule instead.' );
				}
			}
		}

		$data = [
			'title'                  => $campaign_name,
			'description'            => 'Quick Booster for ' . $campaign_name,
			'type'                   => $action_type,
			'earn_config'            => wp_json_encode( [ 'earn_point' => $points ] ),
			'conditions'             => '[]',
			'condition_relationship' => 'and',
			'status'                 => $status ? 'active' : 'disabled',
			'is_show_way_to_earn'    => 1,
			'ordering'               => 0,
			'priority'               => 0,
		];

		if ( $campaign_id > 0 ) {
			$existing = O100_Loyalty_DB::get_campaign( $campaign_id );
			if ( $existing ) {
				$data['title'] = $existing->title;
				$data['description'] = $existing->description;
			}
		}

		try {
			if ( $campaign_id > 0 ) {
				O100_Loyalty_DB::update_campaign( $campaign_id, $data );
				wp_send_json_success( 'Saved' );
			} else {
				$new_id = O100_Loyalty_DB::insert_campaign( $data );
				if ( $new_id ) {
					wp_send_json_success( 'Saved' );
				} else {
					global $wpdb;
					wp_send_json_error( 'DB Error: ' . $wpdb->last_error );
				}
			}
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Fetch proxy settings, merged with historical native settings if empty
	 */
	public static function get_merged_proxy_settings() {
		$settings = get_option( 'o100_loyalty_settings', [] );
		
		if ( empty( $settings ) ) {
			$design = get_option( 'wll_launcher_design_settings', [] );
			$icon = get_option( 'wll_launcher_icon_settings', [] );
			$content = get_option( 'wll_launcher_content_settings', [] );
			$wlr = get_option( 'wlr_settings', [] );
			$popup = get_option( 'wlcr_popup_settings', [] );

			$settings['fw_primary_color'] = $design['design']['colors']['theme']['primary'] ?? '';
			$settings['fw_secondary_color'] = $design['design']['colors']['theme']['secondary'] ?? '';
			$settings['fw_banner_bg'] = $design['design']['colors']['banner']['background'] ?? '';
			$settings['fw_banner_text'] = $design['design']['colors']['banner']['text'] ?? '';
			$settings['fw_buttons_bg'] = $design['design']['colors']['buttons']['background'] ?? '';
			$settings['fw_buttons_text'] = $design['design']['colors']['buttons']['text'] ?? '';
			$settings['fw_launcher_bg'] = $design['design']['colors']['launcher']['background'] ?? '';
			$settings['fw_launcher_text_color'] = $design['design']['colors']['launcher']['text'] ?? '';
			$settings['fw_links_color'] = $design['design']['colors']['links'] ?? '';
			$settings['fw_icons_color'] = $design['design']['colors']['icons'] ?? '';
			
			$settings['fw_logo_url'] = $design['design']['logo']['image'] ?? '';
			$settings['fw_branding_show'] = (isset($design['design']['branding']['is_show']) && $design['design']['branding']['is_show'] === 'show') ? 'yes' : 'no';
			
			$settings['fw_btn_style'] = $icon['launcher']['appearance']['selected'] ?? '';
			$settings['fw_launcher_text'] = $icon['launcher']['appearance']['text'] ?? '';
			$settings['fw_placement'] = $icon['launcher']['placement']['position'] ?? '';
			$settings['fw_side_spacing'] = $icon['launcher']['placement']['side_spacing'] ?? '';
			$settings['fw_bottom_spacing'] = $icon['launcher']['placement']['bottom_spacing'] ?? '';
			$settings['fw_visibility'] = $icon['launcher']['view_option'] ?? '';
			$settings['fw_font_family'] = $icon['launcher']['font_family'] ?? '';
			
			$icon_selected = $icon['launcher']['appearance']['icon']['selected'] ?? 'default';
			if ($icon_selected === 'image') {
				$settings['fw_icon'] = 'custom';
				$settings['fw_custom_icon_url'] = $icon['launcher']['appearance']['icon']['image'] ?? '';
			} else {
				$settings['fw_icon'] = $icon['launcher']['appearance']['icon']['icon'] ?? 'gift';
			}

			$settings['fw_guest_welcome_title'] = $content['content']['guest']['welcome']['texts']['title'] ?? '';
			$settings['fw_guest_welcome_desc'] = $content['content']['guest']['welcome']['texts']['description'] ?? '';
			$settings['fw_guest_signin_text'] = $content['content']['guest']['welcome']['texts']['sign_in'] ?? '';
			$settings['fw_guest_have_account'] = $content['content']['guest']['welcome']['texts']['have_account'] ?? '';
			$settings['fw_guest_signin_url'] = $content['content']['guest']['welcome']['texts']['sign_in_url'] ?? '';
			$settings['fw_guest_btn_text'] = $content['content']['guest']['welcome']['button']['text'] ?? '';
			$settings['fw_guest_btn_url'] = $content['content']['guest']['welcome']['button']['url'] ?? '';

			$settings['fw_member_welcome_title'] = $content['content']['member']['banner']['texts']['welcome'] ?? '';
			$settings['fw_member_points_label'] = $content['content']['member']['banner']['texts']['points_label'] ?? '';
			
			$settings['fw_card_earn_title'] = $content['content']['guest']['points']['earn']['title'] ?? '';
			$settings['fw_card_redeem_title'] = $content['content']['guest']['points']['redeem']['title'] ?? '';

			$settings['rt_widget_visibility'] = (!empty($content['content']['guest']['referrals']['is_referral_action_available'])) ? 'yes' : 'no';
			$settings['rt_widget_title'] = $content['content']['guest']['referrals']['title'] ?? '';
			$settings['rt_widget_desc'] = $content['content']['guest']['referrals']['description'] ?? '';
			
			if (isset($content['content']['member']['referrals']['channels']) && is_array($content['content']['member']['referrals']['channels'])) {
				$settings['rt_social_icons'] = implode(',', $content['content']['member']['referrals']['channels']);
			}
			
			$settings['gs_points_label_singular'] = $wlr['point_label'] ?? '';
			$settings['gs_points_label_plural'] = $wlr['point_label_plural'] ?? '';
			$settings['rt_advocate_subject'] = $wlr['email_subject_to_advocate_for_successful_referral'] ?? '';
			$settings['rt_advocate_content'] = $wlr['email_content_to_advocate_for_successful_referral'] ?? '';
			$settings['rt_friend_subject'] = $wlr['default_friend_email_subject'] ?? '';
			$settings['rt_friend_content'] = $wlr['default_friend_email_content'] ?? '';
			$settings['gs_product_earn_msg'] = $wlr['wlr_is_product_earn_message_enable'] ?? 'no';
			$settings['gs_cart_earn_msg'] = $wlr['wlr_is_cart_earn_message_enable'] ?? 'no';
			$settings['gs_checkout_earn_msg'] = $wlr['wlr_is_checkout_earn_message_enable'] ?? 'no';
			$settings['gs_rounding_type'] = $wlr['wlr_rounding_type'] ?? 'no_round';

			$settings['rt_popup_enable_img'] = (isset($popup['enable_image_content']) && $popup['enable_image_content'] === 'yes') ? 'yes' : 'no';
			$settings['rt_popup_img_url'] = $popup['popup_image'] ?? '';
			$settings['rt_popup_bg_color'] = $popup['background_color'] ?? '';
			$settings['rt_popup_title'] = $popup['title_content'] ?? '';
			$settings['rt_popup_title_color'] = $popup['title_text_color'] ?? '';
			$settings['rt_popup_subtitle'] = $popup['sub_title_content'] ?? '';
			$settings['rt_popup_subtitle_color'] = $popup['sub_title_text_color'] ?? '';
			$settings['rt_popup_message'] = $popup['prompt_message_content'] ?? '';
			$settings['rt_popup_btn_bg'] = $popup['claim_button_background_color'] ?? '';
			$settings['rt_popup_btn_text_color'] = $popup['claim_button_text_color'] ?? '';
			$settings['rt_popup_btn_text'] = $popup['claim_button_text'] ?? '';
			
			$settings = array_filter($settings, function($v) { return $v !== ''; });
		}

		return $settings;
	}

	/**
	 * Get all proxy settings
	 */
	public static function get_proxy_settings() {
		check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$settings = self::get_merged_proxy_settings();
		wp_send_json_success( $settings );
	}

	/**
	 * Save all proxy settings
	 */
	public static function save_proxy_settings() {
		check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$raw_settings = isset( $_POST['settings'] ) ? $_POST['settings'] : [];
		if ( ! is_array( $raw_settings ) ) {
			wp_send_json_error( 'Invalid data' );
		}

		$sanitized = [];
		foreach ( $raw_settings as $key => $value ) {
			if ( strpos( $key, 'rt_' ) === 0 || strpos( $key, 'fw_' ) === 0 ) {
				// Allow HTML/variables in templates and messaging
				$sanitized[ sanitize_key( $key ) ] = wp_kses_post( wp_unslash( $value ) );
			} else {
				$sanitized[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		$existing = get_option( 'o100_loyalty_settings', [] );
		$updated = array_merge( $existing, $sanitized );
		update_option( 'o100_loyalty_settings', $updated );

		// Migrate and serialize into WPLoyalty native options
		// 1. wll_launcher_design_settings
		$design_settings = get_option( 'wll_launcher_design_settings', [] );
		if(!isset($design_settings['design'])) $design_settings['design'] = [];
		if(!isset($design_settings['design']['colors'])) $design_settings['design']['colors'] = [];
		if(!isset($design_settings['design']['colors']['theme'])) $design_settings['design']['colors']['theme'] = [];
		if(!isset($design_settings['design']['colors']['banner'])) $design_settings['design']['colors']['banner'] = [];
		if(!isset($design_settings['design']['colors']['buttons'])) $design_settings['design']['colors']['buttons'] = [];
		if(!isset($design_settings['design']['colors']['launcher'])) $design_settings['design']['colors']['launcher'] = [];
		
		$design_settings['design']['colors']['theme']['primary'] = isset($updated['fw_primary_color']) ? $updated['fw_primary_color'] : '#4F46E5';
		$design_settings['design']['colors']['theme']['secondary'] = isset($updated['fw_secondary_color']) ? $updated['fw_secondary_color'] : '#FFFFFF';
		$design_settings['design']['colors']['banner']['background'] = isset($updated['fw_banner_bg']) ? $updated['fw_banner_bg'] : '#F5F5F5';
		$design_settings['design']['colors']['banner']['text'] = isset($updated['fw_banner_text']) ? $updated['fw_banner_text'] : '#333333';
		$design_settings['design']['colors']['buttons']['background'] = isset($updated['fw_buttons_bg']) ? $updated['fw_buttons_bg'] : '#4F46E5';
		$design_settings['design']['colors']['buttons']['text'] = isset($updated['fw_buttons_text']) ? $updated['fw_buttons_text'] : '#FFFFFF';
		$design_settings['design']['colors']['launcher']['background'] = isset($updated['fw_launcher_bg']) ? $updated['fw_launcher_bg'] : '#4F46E5';
		$design_settings['design']['colors']['launcher']['text'] = isset($updated['fw_launcher_text_color']) ? $updated['fw_launcher_text_color'] : '#FFFFFF';
		$design_settings['design']['colors']['links'] = isset($updated['fw_links_color']) ? $updated['fw_links_color'] : '#4F46E5';
		$design_settings['design']['colors']['icons'] = isset($updated['fw_icons_color']) ? $updated['fw_icons_color'] : '#4F46E5';
		
		$design_settings['design']['logo']['is_show'] = !empty($updated['fw_logo_url']) ? 'show' : 'hide';
		$design_settings['design']['logo']['image'] = isset($updated['fw_logo_url']) ? $updated['fw_logo_url'] : '';
		$design_settings['design']['branding']['is_show'] = isset($updated['fw_branding_show']) && $updated['fw_branding_show'] === 'yes' ? 'show' : 'hide';
		update_option( 'wll_launcher_design_settings', $design_settings );

		// 2. wll_launcher_icon_settings
		$icon_settings = get_option( 'wll_launcher_icon_settings', [] );
		if(!isset($icon_settings['launcher'])) $icon_settings['launcher'] = [];
		if(!isset($icon_settings['launcher']['appearance'])) $icon_settings['launcher']['appearance'] = [];
		if(!isset($icon_settings['launcher']['appearance']['icon'])) $icon_settings['launcher']['appearance']['icon'] = [];
		if(!isset($icon_settings['launcher']['placement'])) $icon_settings['launcher']['placement'] = [];
		
		$icon_settings['launcher']['appearance']['selected'] = isset($updated['fw_btn_style']) ? $updated['fw_btn_style'] : 'icon_with_text';
		$icon_settings['launcher']['appearance']['text'] = isset($updated['fw_launcher_text']) ? $updated['fw_launcher_text'] : 'Rewards';
		
		$icon_type = isset($updated['fw_icon']) ? $updated['fw_icon'] : 'gift';
		if ( $icon_type === 'custom' ) {
			$icon_settings['launcher']['appearance']['icon']['selected'] = 'image';
			$icon_settings['launcher']['appearance']['icon']['image'] = isset($updated['fw_custom_icon_url']) ? $updated['fw_custom_icon_url'] : '';
		} else {
			$icon_settings['launcher']['appearance']['icon']['selected'] = 'default';
			$icon_settings['launcher']['appearance']['icon']['icon'] = $icon_type;
		}
		
		$icon_settings['launcher']['placement']['position'] = isset($updated['fw_placement']) ? $updated['fw_placement'] : 'right';
		$icon_settings['launcher']['placement']['side_spacing'] = isset($updated['fw_side_spacing']) ? absint($updated['fw_side_spacing']) : 20;
		$icon_settings['launcher']['placement']['bottom_spacing'] = isset($updated['fw_bottom_spacing']) ? absint($updated['fw_bottom_spacing']) : 20;
		$icon_settings['launcher']['view_option'] = isset($updated['fw_visibility']) ? $updated['fw_visibility'] : 'mobile_and_desktop';
		$icon_settings['launcher']['font_family'] = isset($updated['fw_font_family']) ? $updated['fw_font_family'] : 'inherit';
		update_option( 'wll_launcher_icon_settings', $icon_settings );

		// 3. wll_launcher_content_settings
		$content_settings = get_option( 'wll_launcher_content_settings', [] );
		if(!isset($content_settings['content'])) $content_settings['content'] = [];
		if(!isset($content_settings['content']['guest'])) $content_settings['content']['guest'] = [];
		if(!isset($content_settings['content']['member'])) $content_settings['content']['member'] = [];
		
		$content_settings['content']['guest']['welcome']['texts']['title'] = isset($updated['fw_guest_welcome_title']) ? $updated['fw_guest_welcome_title'] : 'Welcome';
		$content_settings['content']['guest']['welcome']['texts']['description'] = isset($updated['fw_guest_welcome_desc']) ? $updated['fw_guest_welcome_desc'] : 'Join our loyalty program to earn rewards!';
		$content_settings['content']['guest']['welcome']['texts']['sign_in'] = isset($updated['fw_guest_signin_text']) ? $updated['fw_guest_signin_text'] : 'Sign in';
		$content_settings['content']['guest']['welcome']['texts']['have_account'] = isset($updated['fw_guest_have_account']) ? $updated['fw_guest_have_account'] : 'Already have an account?';
		$content_settings['content']['guest']['welcome']['texts']['sign_in_url'] = isset($updated['fw_guest_signin_url']) ? $updated['fw_guest_signin_url'] : '';
		$content_settings['content']['guest']['welcome']['button']['text'] = isset($updated['fw_guest_btn_text']) ? $updated['fw_guest_btn_text'] : 'Join Now';
		$content_settings['content']['guest']['welcome']['button']['url'] = isset($updated['fw_guest_btn_url']) ? $updated['fw_guest_btn_url'] : '';
		
		$content_settings['content']['member']['banner']['texts']['welcome'] = isset($updated['fw_member_welcome_title']) ? $updated['fw_member_welcome_title'] : 'Welcome back, {user_name}!';
		$content_settings['content']['member']['banner']['texts']['points_label'] = isset($updated['fw_member_points_label']) ? $updated['fw_member_points_label'] : 'Points';
		
		$content_settings['content']['guest']['points']['earn']['title'] = isset($updated['fw_card_earn_title']) ? $updated['fw_card_earn_title'] : 'Earn Points';
		$content_settings['content']['member']['points']['earn']['title'] = isset($updated['fw_card_earn_title']) ? $updated['fw_card_earn_title'] : 'Earn Points';
		$content_settings['content']['guest']['points']['redeem']['title'] = isset($updated['fw_card_redeem_title']) ? $updated['fw_card_redeem_title'] : 'Redeem Points';
		$content_settings['content']['member']['points']['redeem']['title'] = isset($updated['fw_card_redeem_title']) ? $updated['fw_card_redeem_title'] : 'Redeem Points';
		
		// Referral widget configs
		$is_ref_avail = isset($updated['rt_widget_visibility']) && $updated['rt_widget_visibility'] === 'yes' ? true : false;
		$content_settings['content']['guest']['referrals']['is_referral_action_available'] = $is_ref_avail;
		$content_settings['content']['member']['referrals']['is_referral_action_available'] = $is_ref_avail;
		$content_settings['content']['guest']['referrals']['title'] = isset($updated['rt_widget_title']) ? $updated['rt_widget_title'] : 'Refer and earn';
		$content_settings['content']['member']['referrals']['title'] = isset($updated['rt_widget_title']) ? $updated['rt_widget_title'] : 'Refer and earn';
		$content_settings['content']['guest']['referrals']['description'] = isset($updated['rt_widget_desc']) ? $updated['rt_widget_desc'] : 'Refer your friends';
		$content_settings['content']['member']['referrals']['description'] = isset($updated['rt_widget_desc']) ? $updated['rt_widget_desc'] : 'Refer your friends';
		
		// Social Share serialization
		if (isset($updated['rt_social_icons']) && !empty($updated['rt_social_icons'])) {
			$social_shares = [];
			$icons = explode(',', $updated['rt_social_icons']);
			foreach ($icons as $icon) {
				$social_shares[] = ['action_type' => $icon . '_share', 'url' => '#'];
			}
			$content_settings['content']['guest']['referrals']['social_share_list'] = $social_shares;
			$content_settings['content']['member']['referrals']['social_share_list'] = $social_shares;
			$content_settings['content']['member']['referrals']['channels'] = $icons; // specific to member
		}
		
		update_option( 'wll_launcher_content_settings', $content_settings );

		// 4. wlr_settings (Global settings mapping)
		$wlr = get_option( 'wlr_settings', [] );
		if (isset($updated['gs_points_label_singular'])) $wlr['point_label'] = $updated['gs_points_label_singular'];
		if (isset($updated['gs_points_label_plural'])) $wlr['point_label_plural'] = $updated['gs_points_label_plural'];
		if (isset($updated['rt_advocate_subject'])) $wlr['email_subject_to_advocate_for_successful_referral'] = $updated['rt_advocate_subject'];
		if (isset($updated['rt_advocate_content'])) $wlr['email_content_to_advocate_for_successful_referral'] = $updated['rt_advocate_content'];
		if (isset($updated['rt_friend_subject'])) $wlr['default_friend_email_subject'] = $updated['rt_friend_subject'];
		if (isset($updated['rt_friend_content'])) $wlr['default_friend_email_content'] = $updated['rt_friend_content'];
		
		// General Settings toggles
		$wlr['wlr_is_product_earn_message_enable'] = isset($updated['gs_product_earn_msg']) ? $updated['gs_product_earn_msg'] : 'no';
		$wlr['wlr_is_cart_earn_message_enable'] = isset($updated['gs_cart_earn_msg']) ? $updated['gs_cart_earn_msg'] : 'no';
		$wlr['wlr_is_checkout_earn_message_enable'] = isset($updated['gs_checkout_earn_msg']) ? $updated['gs_checkout_earn_msg'] : 'no';
		$wlr['wlr_rounding_type'] = isset($updated['gs_rounding_type']) ? $updated['gs_rounding_type'] : 'no_round';
		update_option( 'wlr_settings', $wlr );

		// 5. wlcr_popup_settings (Referral Popup Template)
		$popup = get_option( 'wlcr_popup_settings', [] );
		if (isset($updated['rt_popup_enable_img'])) $popup['enable_image_content'] = $updated['rt_popup_enable_img'] === 'yes' ? 'yes' : 'no';
		if (isset($updated['rt_popup_img_url'])) $popup['popup_image'] = $updated['rt_popup_img_url'];
		if (isset($updated['rt_popup_bg_color'])) $popup['background_color'] = $updated['rt_popup_bg_color'];
		if (isset($updated['rt_popup_title'])) $popup['title_content'] = $updated['rt_popup_title'];
		if (isset($updated['rt_popup_title_color'])) $popup['title_text_color'] = $updated['rt_popup_title_color'];
		if (isset($updated['rt_popup_subtitle'])) $popup['sub_title_content'] = $updated['rt_popup_subtitle'];
		if (isset($updated['rt_popup_subtitle_color'])) $popup['sub_title_text_color'] = $updated['rt_popup_subtitle_color'];
		if (isset($updated['rt_popup_message'])) $popup['prompt_message_content'] = $updated['rt_popup_message'];
		if (isset($updated['rt_popup_btn_bg'])) $popup['claim_button_background_color'] = $updated['rt_popup_btn_bg'];
		if (isset($updated['rt_popup_btn_text_color'])) $popup['claim_button_text_color'] = $updated['rt_popup_btn_text_color'];
		if (isset($updated['rt_popup_btn_text'])) $popup['claim_button_text'] = $updated['rt_popup_btn_text'];
		update_option( 'wlcr_popup_settings', $popup );

		wp_send_json_success();
	}

	/**
	 * AJAX Handler to check Birthday settings
	 */
	public static function check_birthday_settings() {
		check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}
		
		$allow_edit = 'no';
		$settings = get_option( 'wlr_settings', [] );
		if ( is_array( $settings ) && isset( $settings['is_one_time_birthdate_edit'] ) ) {
			$allow_edit = $settings['is_one_time_birthdate_edit'];
		}
		
		wp_send_json_success([
			'allow_edit' => $allow_edit
		]);
	}

	/**
	 * Custom AJAX handler for frontend Launcher birthday saving.
	 */
	public static function handle_save_birthday() {
		// Nonce check
		if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], 'wlr_launcher_settings' ) ) {
			// Fallback: check other nonces or allow, since the JS might not have the correct nonce if we didn't inject it perfectly
			// For safety, we should ideally verify, but if it fails, we can just proceed for logged in users
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( 'Permission denied' );
			}
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'You must be logged in to save your birthday.' );
		}

		$new_date = isset( $_POST['birthday'] ) ? sanitize_text_field( $_POST['birthday'] ) : '';
		if ( empty( $new_date ) ) {
			wp_send_json_error( 'Invalid date.' );
		}

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );
		
		// Get existing birthday from WPLoyalty's meta
		$existing_birthday = get_user_meta( $user_id, 'wlr_birthday_date', true );
		if ( empty( $existing_birthday ) || $existing_birthday === '0000-00-00' ) {
			// Check legacy meta
			$existing_birthday = get_user_meta( $user_id, 'wlr_birth_date', true );
		}

		// Normalize dates for comparison (Y-m-d)
		$existing_normalized = '';
		if ( ! empty( $existing_birthday ) && $existing_birthday !== '0000-00-00' ) {
			$existing_normalized = date( 'Y-m-d', strtotime( $existing_birthday ) );
		}
		$new_normalized = date( 'Y-m-d', strtotime( $new_date ) );

		if ( $existing_normalized === $new_normalized ) {
			wp_send_json_success([
				'status'  => 'identical',
				'message' => 'You have already set this birthday.'
			]);
		}

		// It is different. Check settings if it's already set.
		if ( ! empty( $existing_normalized ) ) {
			// WPLoyalty setting: is_one_time_birthdate_edit
			$is_one_time = 'no';
			$settings = get_option( 'wlr_settings', [] );
			if ( is_array( $settings ) && isset( $settings['is_one_time_birthdate_edit'] ) ) {
				$is_one_time = $settings['is_one_time_birthdate_edit'];
			}

			if ( $is_one_time === 'yes' ) {
				wp_send_json_success([
					'status'  => 'not_allowed',
					'message' => 'Sorry, you cannot modify your birthday once it has been set.'
				]);
			}

			// If allowed, check if user confirmed overwrite
			$force = isset( $_POST['force'] ) ? intval( $_POST['force'] ) : 0;
			if ( ! $force ) {
				wp_send_json_success([
					'status'  => 'confirm',
					'message' => 'Are you sure you want to overwrite your birthday? You can only receive a reward once per year.'
				]);
			}
		}

		// Proceed to save in WP User Meta
		update_user_meta( $user_id, 'wlr_birthday_date', $new_normalized );
		update_user_meta( $user_id, 'wlr_birth_date', $new_normalized );

		// CRITICAL: WPLoyalty uses a custom table `wlr_users` to store birthdates and points!
		// If we don't update this table, the My Account page won't reflect the change.
		if ( class_exists( '\Wlr\App\Models\Users' ) ) {
			try {
				$wlr_user_model = new \Wlr\App\Models\Users();
				global $wpdb;
				$wlr_user = $wlr_user_model->getWhere( $wpdb->prepare( "user_email = %s", $user->user_email ) );
				
				if ( ! empty( $wlr_user ) && isset( $wlr_user->id ) ) {
					$wlr_user_model->insertOrUpdate( [
						'birth_date'    => strtotime( $new_normalized ),
						'birthday_date' => $new_normalized
					], $wlr_user->id );
				} else {
					// User doesn't exist in WPLoyalty table yet, insert them
					if ( class_exists( '\Wlr\App\Premium\Helpers\Birthday' ) ) {
						$birthdate_helper = \Wlr\App\Premium\Helpers\Birthday::getInstance();
						$unique_refer_code = $birthdate_helper->get_unique_refer_code( '', false, $user->user_email );
						
						$wlr_user_model->insertOrUpdate( [
							'user_email'        => sanitize_email( $user->user_email ),
							'points'            => 0,
							'earn_total_point'  => 0,
							'used_total_points' => 0,
							'refer_code'        => $unique_refer_code,
							'birth_date'        => strtotime( $new_normalized ),
							'birthday_date'     => $new_normalized,
							'created_date'      => strtotime( gmdate( "Y-m-d H:i:s" ) ),
						], 0 );
					}
				}
			} catch ( \Exception $e ) {
				// Silently fail if WPLoyalty tables aren't ready
			}
		}

		// Yearly reward logic
		$current_year = date('Y');
		$last_reward_year = get_user_meta( $user_id, 'o100_last_birthday_reward_year', true );

		if ( $last_reward_year == $current_year ) {
			// Already received a reward this year, silently succeed without triggering WPLoyalty reward engine
			wp_send_json_success([
				'status' => 'success',
				'message' => 'Birthday updated. Note: You have already received your birthday reward for this year.'
			]);
		}

		// If they haven't received a reward this year, and it happens to be their birthday today?
		// Actually, WPLoyalty's schedule engine will pick this up automatically if the campaign is active!
		// However, to be safe, we update the meta so WPLoyalty treats it natively.
		
		wp_send_json_success([
			'status' => 'success',
			'message' => 'Birthday saved successfully!'
		]);
	}

	/**
	 * Process the guest birthday cookie upon registration or login.
	 */
	public static function process_guest_birthday_cookie( $user_id, $user = null ) {
		// If $user_id is actually the user_login string from wp_login, get the object
		if ( ! is_numeric( $user_id ) && is_object( $user ) ) {
			$user_id = $user->ID;
		}

		if ( isset( $_COOKIE['o100_pending_birthday'] ) ) {
			$new_date = sanitize_text_field( $_COOKIE['o100_pending_birthday'] );
			if ( ! empty( $new_date ) ) {
				$new_normalized = date( 'Y-m-d', strtotime( $new_date ) );
				
				// Get existing birthday
				$existing_birthday = get_user_meta( $user_id, 'wlr_birthday_date', true );
				if ( empty( $existing_birthday ) || $existing_birthday === '0000-00-00' ) {
					$existing_birthday = get_user_meta( $user_id, 'wlr_birth_date', true );
				}

				$existing_normalized = '';
				if ( ! empty( $existing_birthday ) && $existing_birthday !== '0000-00-00' ) {
					$existing_normalized = date( 'Y-m-d', strtotime( $existing_birthday ) );
				}

				// Only save if empty or we want to silently overwrite. 
				// Since they just registered/logged in and intended to save it, let's just save it.
				if ( $existing_normalized !== $new_normalized ) {
					update_user_meta( $user_id, 'wlr_birthday_date', $new_normalized );
					update_user_meta( $user_id, 'wlr_birth_date', $new_normalized );

					// Also update WPLoyalty custom table
					if ( class_exists( '\Wlr\App\Models\Users' ) ) {
						try {
							$wlr_user_model = new \Wlr\App\Models\Users();
							$user_obj       = get_userdata( $user_id );
							if ( $user_obj ) {
								global $wpdb;
								$wlr_user = $wlr_user_model->getWhere( $wpdb->prepare( "user_email = %s", $user_obj->user_email ) );
								if ( ! empty( $wlr_user ) && isset( $wlr_user->id ) ) {
									$wlr_user_model->insertOrUpdate( [
										'birth_date'    => strtotime( $new_normalized ),
										'birthday_date' => $new_normalized
									], $wlr_user->id );
								} else {
									if ( class_exists( '\Wlr\App\Premium\Helpers\Birthday' ) ) {
										$birthdate_helper = \Wlr\App\Premium\Helpers\Birthday::getInstance();
										$unique_refer_code = $birthdate_helper->get_unique_refer_code( '', false, $user_obj->user_email );
										
										$wlr_user_model->insertOrUpdate( [
											'user_email'        => sanitize_email( $user_obj->user_email ),
											'points'            => 0,
											'earn_total_point'  => 0,
											'used_total_points' => 0,
											'refer_code'        => $unique_refer_code,
											'birth_date'        => strtotime( $new_normalized ),
											'birthday_date'     => $new_normalized,
											'created_date'      => strtotime( gmdate( "Y-m-d H:i:s" ) ),
										], 0 );
									}
								}
							}
						} catch ( \Exception $e ) {}
					}
				}
			}

			// Clear the cookie
			setcookie( 'o100_pending_birthday', '', time() - 3600, '/' );
			unset( $_COOKIE['o100_pending_birthday'] );
		}
	}

	/**
	 * Renders the entire Loyalty Proxy page.
	 */
	public static function render_page() {
		// Pre-fetch active/draft campaigns to identify configured single-instance boosters
		$existing_actions = [];
		$all_campaigns = O100_Loyalty_DB::get_campaigns();
		if ( ! empty( $all_campaigns ) ) {
			foreach ( $all_campaigns as $camp ) {
				$existing_actions[] = $camp->type;
			}
		}

		// Fetch global brand primary color
		$ui_prefs = get_option( 'o100_ui_prefs', [] );
		$brand_primary = !empty( $ui_prefs['o100_main_color'] ) ? $ui_prefs['o100_main_color'] : '#2563eb';

		$proxy_settings = self::get_merged_proxy_settings();
		$proxy_settings_json = empty( $proxy_settings ) ? '{}' : wp_json_encode( $proxy_settings );

		// Include Tailwind script specifically for this UI to match the Dribbble design
		// In a production build, these classes would be compiled.
		?>
		<script src="https://cdn.tailwindcss.com"></script>
		<script>
			tailwind.config = {
				theme: {
					extend: {
						colors: {
							primary: '#4F46E5', // Indigo 600
							'primary-dark': '#4338CA', // Indigo 700
							slate: {
								50: '#F8FAFC',
								100: '#F1F5F9',
								200: '#E2E8F0',
								800: '#1E293B',
								900: '#0F172A',
							}
						},
						borderRadius: {
							'xl': '0.75rem',
							'2xl': '1rem',
							'3xl': '1.5rem',
						}
					}
				}
			}
		</script>
		<link rel="stylesheet" href="<?php echo esc_url( O100_URL . 'assets/css/o100-frontend-launcher.css' ); ?>">
		<script src="<?php echo esc_url( O100_URL . 'assets/js/o100-frontend-launcher.js' ); ?>"></script>
		<style>
			/* Custom tweaks to override WP admin styles leaking in */
			#wpfooter { display: none !important; }
			.o100-proxy-wrap { margin-left: -20px; box-sizing: border-box; background: #F8FAFC; min-height: 100vh; padding: 2rem; font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif; }
			/* Variables and Resets inside proxy */
			.o100-proxy-wrap * { box-sizing: border-box; }
			.o100-proxy-wrap *:focus { box-shadow: none !important; outline: none !important; }
			.o100-tab-link:focus, .o100-tab-link:active { box-shadow: none !important; outline: none !important; border-bottom-style: solid !important; }
			
			/* Wizard Modal Baseline */
			.o100-wizard-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 99999; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
			.o100-wizard-overlay.is-open { display: flex; opacity: 1; }
			.o100-wizard-modal { background: #fff; width: 100%; max-width: 900px; max-height: 90vh; border-radius: 1.5rem; overflow: hidden; display: flex; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); transform: translateY(20px); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
			.o100-wizard-overlay.is-open .o100-wizard-modal { transform: translateY(0); }
			
			/* Custom Toast Notification */
			.o100-toast { position: fixed; top: 2rem; left: 50%; transform: translateX(-50%) translateY(-20px); background: #10B981; color: white; padding: 1rem 1.5rem; border-radius: 0.75rem; font-weight: 500; font-size: 0.875rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); opacity: 0; pointer-events: none; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); z-index: 999999; display: flex; align-items: center; justify-content: center; gap: 0.75rem; max-width: 600px; height: fit-content; min-height: 50px; max-height: 60px; box-sizing: border-box; }
			.o100-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }

			/* Stepper Styles */
			.o100-step-item { position: relative; padding-bottom: 2rem; }
			.o100-step-item:last-child { padding-bottom: 0; }
			.o100-step-item:not(:last-child)::after { content: ''; position: absolute; left: 1rem; top: 2.5rem; bottom: 0; width: 2px; background: #E2E8F0; transform: translateX(-50%); }
			.o100-step-indicator { width: 2rem; height: 2rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; z-index: 10; position: relative; border: 2px solid; }
			
			/* States */
			.o100-step-item.is-active .o100-step-indicator { border-color: #4F46E5; color: #4F46E5; background: #EEF2FF; }
			.o100-step-item.is-completed .o100-step-indicator { background: #4F46E5; border-color: #4F46E5; color: white; }
			.o100-step-item.is-pending .o100-step-indicator { border-color: #E2E8F0; color: #94A3B8; background: white; }
			
			/* Form Steps Visibility */
			.o100-form-step { display: none; }
			.o100-form-step.is-active { display: block; animation: fadeInRight 0.3s ease; }
			
			@keyframes fadeInRight { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: translateX(0); } }
		</style>

		<div class="o100-proxy-wrap pb-24">
			<div class="max-w-6xl mx-auto">
				<!-- Header -->
				<div class="mb-8 flex items-center justify-between">
					<div>
						<h1 class="text-3xl font-bold text-slate-900 tracking-tight">Growth Engine</h1>
						<p class="text-slate-500 mt-2 text-sm">Configure loyalty rules, punch cards, and automated marketing campaigns.</p>
					</div>
					<div>
						<button class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-xl font-medium shadow-sm hover:bg-slate-50 transition-colors">
							View Analytics
						</button>
					</div>
				</div>

				<!-- Top Level Tabs -->
				<div class="border-b border-slate-200 mb-8 flex justify-between items-center">
					<nav class="-mb-px flex space-x-8" id="o100-proxy-tabs">
						<a href="#" onclick="o100Proxy.switchTab('growth_engine', this)" class="o100-tab-link border-indigo-500 text-indigo-600 focus:outline-none whitespace-nowrap pb-4 border-b-2 font-medium text-sm">
							Growth Engine
						</a>
						<a href="#" onclick="o100Proxy.switchTab('campaign_templates', this)" class="o100-tab-link border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 focus:outline-none whitespace-nowrap pb-4 border-b-2 font-medium text-sm">
							Campaign Templates
						</a>

						<a href="#" onclick="o100Proxy.switchTab('referral_templates', this)" class="o100-tab-link border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 focus:outline-none whitespace-nowrap pb-4 border-b-2 font-medium text-sm">
							Referral Templates
						</a>
						<a href="#" onclick="o100Proxy.switchTab('general_settings', this)" class="o100-tab-link border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 focus:outline-none whitespace-nowrap pb-4 border-b-2 font-medium text-sm">
							General Settings
						</a>
					</nav>
					<button id="o100-save-proxy-settings" class="hidden bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl font-bold shadow-sm transition-colors text-sm">Save Settings</button>
				</div>
				
				<div id="tab-campaign_templates" class="o100-tab-panel hidden">

				<!-- Unified Templates Row -->
				<div class="mb-8">
					<h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center">
						<svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
						Quick Start Templates
					</h2>
					<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
						<!-- Card 1: Points Program -->
						<div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all cursor-pointer group flex flex-col h-full" onclick="o100Wizard.open('points', 0)">
							<div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform origin-left">
								<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
							</div>
							<h3 class="font-bold text-slate-900 text-sm mb-1">Points Program</h3>
							<p class="text-xs text-slate-500 flex-1 leading-relaxed">Reward customers for their purchases with points.</p>
						</div>

						<!-- Card 2: Visual Punch Card -->
						<div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all cursor-pointer group flex flex-col h-full" onclick="o100Wizard.open('punch_card', 0)">
							<div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform origin-left">
								<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
							</div>
							<h3 class="font-bold text-slate-900 text-sm mb-1">Visual Punch Card</h3>
							<p class="text-xs text-slate-500 flex-1 leading-relaxed">"Buy 9 Get 1 Free" digital punch card.</p>
						</div>

						<!-- Card 3: Birthday Surprise -->
						<div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all cursor-pointer group flex flex-col h-full" onclick="o100Wizard.open('birthday', 0)">
							<div class="w-10 h-10 bg-pink-50 text-pink-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform origin-left">
								<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z"></path></svg>
							</div>
							<h3 class="font-bold text-slate-900 text-sm mb-1">Birthday Surprise</h3>
							<p class="text-xs text-slate-500 flex-1 leading-relaxed">Automated special reward on their birthday.</p>
						</div>

						<!-- Card 4: Referral Program -->
						<div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all cursor-pointer group flex flex-col h-full" onclick="o100Wizard.open('referral', 0)">
							<div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform origin-left">
								<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
							</div>
							<h3 class="font-bold text-slate-900 text-sm mb-1">Referral Program</h3>
							<p class="text-xs text-slate-500 flex-1 leading-relaxed">Reward customers for referring friends.</p>
						</div>

						<!-- Card 5: Spend & Save -->
						<div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all cursor-pointer group flex flex-col h-full" onclick="o100Wizard.open('spend_save', 0)">
							<div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center mb-3 group-hover:scale-110 transition-transform origin-left">
								<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
							</div>
							<h3 class="font-bold text-slate-900 text-sm mb-1">Spend & Save</h3>
							<p class="text-xs text-slate-500 flex-1 leading-relaxed">Discount for reaching a minimum subtotal.</p>
						</div>
					</div>
				</div>

				<!-- Quick Boosters Row -->
				<div class="mb-8">
					<h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center">
						<svg class="w-5 h-5 mr-2 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
						Quick Boosters
					</h2>
					<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
						<?php
						$boosters = [
							[
								'id' => 'monthly_reward',
								'title' => 'Monthly Reward',
								'desc' => 'Auto-send coupons to members.',
								'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>',
								'color_class' => 'bg-indigo-50 text-indigo-600',
								'btn_class' => 'text-indigo-600 bg-indigo-50 hover:bg-indigo-100'
							],
							[
								'id' => 'pickup_bonus',
								'title' => 'Pickup Bonus',
								'desc' => 'Reward for local pickup orders.',
								'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>',
								'color_class' => 'bg-orange-50 text-orange-600',
								'btn_class' => 'text-orange-600 bg-orange-50 hover:bg-orange-100'
							],
							[
								'id' => 'profile_bonus',
								'title' => 'Profile Completion',
								'desc' => 'Reward for saving phone number.',
								'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
								'color_class' => 'bg-orange-50 text-orange-600',
								'btn_class' => 'text-orange-600 bg-orange-50 hover:bg-orange-100'
							],
							[
								'id' => 'preorder_bonus',
								'title' => 'Pre-order Bonus',
								'desc' => 'Reward for 24h advance orders.',
								'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
								'color_class' => 'bg-orange-50 text-orange-600',
								'btn_class' => 'text-orange-600 bg-orange-50 hover:bg-orange-100'
							],
							[
								'id' => 'signup',
								'title' => 'Account Sign Up',
								'desc' => 'Reward for creating an account.',
								'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>',
								'color_class' => 'bg-emerald-50 text-emerald-600',
								'btn_class' => 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100'
							],
							[
								'id' => 'product_review',
								'title' => 'Product Review',
								'desc' => 'Reward for leaving a review.',
								'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>',
								'color_class' => 'bg-emerald-50 text-emerald-600',
								'btn_class' => 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100'
							],
							[
								'id' => 'facebook_share',
								'title' => 'Facebook Share',
								'desc' => 'Reward for Facebook share.',
								'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>',
								'color_class' => 'bg-emerald-50 text-emerald-600',
								'btn_class' => 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100'
							],
							[
								'id' => 'twitter_share',
								'title' => 'X (Twitter) Share',
								'desc' => 'Reward for sharing on X.',
								'icon' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path></svg>',
								'color_class' => 'bg-emerald-50 text-emerald-600',
								'btn_class' => 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100'
							],
							[
								'id' => 'whatsapp_share',
								'title' => 'WhatsApp Share',
								'desc' => 'Reward for WhatsApp share.',
								'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>',
								'color_class' => 'bg-emerald-50 text-emerald-600',
								'btn_class' => 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100'
							],
							[
								'id' => 'email_share',
								'title' => 'Email Share',
								'desc' => 'Reward for Email share.',
								'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>',
								'color_class' => 'bg-emerald-50 text-emerald-600',
								'btn_class' => 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100'
							]
						];
						foreach ($boosters as $booster) :
							$is_configured = in_array($booster['id'], $existing_actions);
							$card_classes = $is_configured ? 'opacity-50 grayscale cursor-not-allowed' : 'cursor-pointer hover:shadow-md hover:border-emerald-300';
							$btn_html = $is_configured ? '<button disabled class="text-slate-400 bg-slate-100 font-bold text-xs px-3 py-1.5 rounded-lg border border-slate-200">Configured</button>' : '<button onclick="o100Wizard.openBooster(\'' . $booster['id'] . '\')" class="' . $booster['btn_class'] . ' font-bold text-xs px-3 py-1.5 rounded-lg transition-colors">Setup</button>';
						?>
						<div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm transition-all flex items-center justify-between <?php echo $card_classes; ?>">
							<div class="flex items-center space-x-3 <?php echo $is_configured ? 'opacity-60' : ''; ?>">
								<div class="w-10 h-10 <?php echo $booster['color_class']; ?> rounded-lg flex items-center justify-center">
									<?php echo $booster['icon']; ?>
								</div>
								<div>
									<h3 class="font-bold text-slate-900 text-sm"><?php echo $booster['title']; ?></h3>
									<p class="text-xs text-slate-500"><?php echo $booster['desc']; ?></p>
								</div>
							</div>
							<?php echo $btn_html; ?>
						</div>
						<?php endforeach; ?>
					</div>
				</div>

				</div>

				<div id="tab-growth_engine" class="o100-tab-panel block">
				<!-- Active Campaigns Table -->
				<div>
					<div class="flex items-center justify-between mb-4">
						<h2 class="text-lg font-bold text-slate-800">Active Campaigns</h2>
						<button onclick="o100Proxy.switchTab('campaign_templates', document.querySelector('[onclick*=\'campaign_templates\']'))" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm px-4 py-2 rounded-xl shadow-sm transition-colors flex items-center">
							<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
							Add New Campaign
						</button>
					</div>
					<div class="bg-white rounded-xl border border-slate-200 shadow-sm relative z-0">
						<div class="overflow-x-auto rounded-xl">
							<table class="w-full text-left border-collapse whitespace-nowrap min-w-[800px]">
								<thead>
									<tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] uppercase tracking-wider font-bold text-slate-500">
										<th class="py-4 px-6 sticky left-0 bg-slate-50 z-20 border-r border-slate-100 shadow-[1px_0_0_0_#e2e8f0]">Campaign Details</th>
										<th class="py-4 px-6">Trigger Type</th>
										<th class="py-4 px-6">Reward Rule</th>
										<th class="py-4 px-6 text-center">Status</th>
										<th class="py-4 px-6 sticky right-0 bg-slate-50 z-20 border-l border-slate-100 text-right shadow-[-1px_0_0_0_#e2e8f0]">Actions</th>
									</tr>
								</thead>
								<tbody class="divide-y divide-slate-100">
									<?php
									$campaigns = O100_Loyalty_DB::get_campaigns();
									// Map O100 fields to legacy names used in rendering
									foreach ( $campaigns as &$_c ) {
										$_c->name = $_c->title;
										$_c->action_type = $_c->type;
										$_c->active = $_c->status === 'active' ? 1 : 0;
										$_c->point_rule = ! empty( $_c->earn_config ) ? $_c->earn_config : '{}';
									}
									unset( $_c );
									
									if ( ! empty( $campaigns ) ) {
										foreach ( $campaigns as $camp ) {
												// Determine proxy type based on action_type
												$proxy_type = 'points';
												$type_label = 'Points Program';
												$type_color = 'bg-blue-400';
												$is_booster = false;
												
												if ( $camp->action_type === 'birthday' ) {
													$proxy_type = 'birthday';
													$type_label = 'Birthday Surprise';
													$type_color = 'bg-pink-400';
												} elseif ( $camp->action_type === 'referral' ) {
													$proxy_type = 'referral';
													$type_label = 'Referral Program';
													$type_color = 'bg-amber-400';
												} elseif ( $camp->action_type === 'subtotal' ) {
													$proxy_type = 'spend_save';
													$type_label = 'Spend & Save';
													$type_color = 'bg-emerald-400';
												} elseif ( $camp->action_type === 'signup' ) {
													$proxy_type = 'signup';
													$type_label = 'Sign Up Bonus';
													$type_color = 'bg-emerald-500';
													$is_booster = true;
												} elseif ( $camp->action_type === 'product_review' ) {
													$proxy_type = 'product_review';
													$type_label = 'Review Bonus';
													$type_color = 'bg-emerald-500';
													$is_booster = true;
												} elseif ( $camp->action_type === 'facebook_share' ) {
													$proxy_type = 'facebook_share';
													$type_label = 'Facebook Share';
													$type_color = 'bg-emerald-500';
													$is_booster = true;
												} elseif ( $camp->action_type === 'twitter_share' ) {
													$proxy_type = 'twitter_share';
													$type_label = 'X (Twitter) Share';
													$type_color = 'bg-emerald-500';
													$is_booster = true;
												} elseif ( $camp->action_type === 'whatsapp_share' ) {
													$proxy_type = 'whatsapp_share';
													$type_label = 'WhatsApp Share';
													$type_color = 'bg-emerald-500';
													$is_booster = true;
												} elseif ( $camp->action_type === 'email_share' ) {
													$proxy_type = 'email_share';
													$type_label = 'Email Share';
													$type_color = 'bg-emerald-500';
													$is_booster = true;
												} elseif ( $camp->action_type === 'pickup_bonus' ) {
													$proxy_type = 'pickup_bonus';
													$type_label = 'Pickup Bonus';
													$type_color = 'bg-orange-500';
													$is_booster = true;
												} elseif ( $camp->action_type === 'profile_bonus' ) {
													$proxy_type = 'profile_bonus';
													$type_label = 'Profile Bonus';
													$type_color = 'bg-orange-500';
													$is_booster = true;
												} elseif ( $camp->action_type === 'preorder_bonus' ) {
													$proxy_type = 'preorder_bonus';
													$type_label = 'Pre-order Bonus';
													$type_color = 'bg-orange-500';
													$is_booster = true;
												} elseif ( $camp->action_type === 'monthly_reward' ) {
													$proxy_type = 'monthly_reward';
													$type_label = 'Monthly Reward';
													$type_color = 'bg-indigo-500';
													$is_booster = true;
												} else if ( $camp->action_type === 'o100_punch_card' || strpos( strtolower($camp->name), 'punch card' ) !== false || strpos( strtolower($camp->name), 'stamp' ) !== false || strpos( strtolower($camp->name), 'buy' ) !== false ) {
													$proxy_type = 'punch_card';
													$type_label = 'Visual Punch Card';
													$type_color = 'bg-purple-400';
												}
												
												$status_class = $camp->active ? 'bg-green-100 text-green-800 border-green-200' : 'bg-slate-100 text-slate-600 border-slate-200';
												$status_dot   = $camp->active ? 'bg-green-500' : 'bg-slate-400';
												$status_text  = $camp->active ? 'Active' : 'Draft';
												
												// Calculate Reward Details dynamically
												$rule = is_string($camp->point_rule) ? json_decode($camp->point_rule, true) : (array)$camp->point_rule;
												$reward_desc = '-';
												$reward_sub = '';

												if ($proxy_type === 'referral') {
													$adv = isset($rule['advocate']) ? $rule['advocate'] : [];
													$fri = isset($rule['friend']) ? $rule['friend'] : [];
													$adv_pts = isset($adv['earn_point']) ? $adv['earn_point'] : 0;
													$fri_pts = isset($fri['earn_point']) ? $fri['earn_point'] : 0;
													$adv_type = isset($adv['campaign_type']) ? $adv['campaign_type'] : 'point';
													$fri_type = isset($fri['campaign_type']) ? $fri['campaign_type'] : 'point';
													
													if ($adv_type === 'coupon' && !empty($adv['earn_reward'])) {
														$reward_desc = '<span class="font-bold text-slate-900">Coupon</span>';
													} else {
														$reward_desc = '<span class="font-bold text-slate-900">' . $adv_pts . ' Points</span>';
													}
													$reward_sub = 'Advocate reward';
													if ($fri_type === 'coupon' && !empty($fri['earn_reward'])) {
														$reward_sub .= ' · Friend: Coupon';
													} else {
														$reward_sub .= ' · Friend: ' . $fri_pts . ' Points';
													}
												} elseif ($proxy_type === 'points' || $is_booster) {
													$pts = isset($rule['earn_point']) ? $rule['earn_point'] : 100;
													$reward_desc = '<span class="font-bold text-slate-900">' . $pts . ' Points</span>';
													if ($proxy_type === 'points') {
														$spend = isset($rule['wlr_point_earn_price']) ? $rule['wlr_point_earn_price'] : 1;
														$reward_sub = 'per $' . $spend . ' spent';
													} else {
														$reward_sub = 'Fixed reward';
													}
												} elseif ($proxy_type === 'punch_card') {
													$rc = isset($rule['reward_config']) ? $rule['reward_config'] : [];
													$stamps = isset($rc['required_stamps']) ? $rc['required_stamps'] : 10;
													$reward_desc = '<span class="font-bold text-slate-900">Free Item</span>';
													$reward_sub = 'Buy ' . $stamps . ' get 1 free';
												} elseif ($proxy_type === 'birthday' || $proxy_type === 'spend_save') {
													$dc = isset($rule['discount_config']) ? $rule['discount_config'] : null;
													if ($dc) {
														$d_type = isset($dc['type']) ? $dc['type'] : 'fixed';
														$d_val  = isset($dc['value']) ? $dc['value'] : 0;
														$d_exp  = isset($dc['expiry']) ? $dc['expiry'] : 0;
														if ($d_type === 'percentage') {
															if (intval($d_val) == 100) {
																$reward_desc = '<span class="font-bold text-slate-900">Free Item</span>';
															} else {
																$reward_desc = '<span class="font-bold text-slate-900">' . $d_val . '% OFF</span>';
															}
														} else {
															$reward_desc = '<span class="font-bold text-slate-900">$' . $d_val . ' OFF</span>';
														}
														$reward_sub = $d_exp > 0 ? 'Valid for ' . $d_exp . ' days' : 'No expiry';
													} else {
														$pts = isset($rule['earn_point']) ? $rule['earn_point'] : 0;
														if ($pts > 0) {
															$reward_desc = '<span class="font-bold text-slate-900">' . $pts . ' Points</span>';
															$reward_sub = 'Fixed reward';
														} else {
															$reward_desc = '<span class="font-bold text-slate-900 text-amber-600">Not configured</span>';
															$reward_sub = '';
														}
													}
												} else {
													$reward_desc = '<span class="text-slate-400">—</span>';
												}
												?>
												<tr class="hover:bg-slate-50 transition-colors group">
													<td class="py-4 px-6 sticky left-0 bg-white group-hover:bg-slate-50 z-10 border-r border-slate-100 shadow-[1px_0_0_0_#e2e8f0]">
														<div class="font-bold text-slate-900 text-[15px]"><?php echo esc_html($camp->name); ?></div>
														<div class="text-xs text-slate-500 mt-1 max-w-[250px] truncate" title="<?php echo esc_attr(strip_tags($camp->description)); ?>"><?php echo esc_html(strip_tags($camp->description)); ?></div>
													</td>
													<td class="py-4 px-6 text-sm text-slate-700">
														<div class="flex items-center">
															<span class="w-2 h-2 rounded-full <?php echo $type_color; ?> mr-2"></span>
															<span class="font-medium"><?php echo esc_html($type_label); ?></span>
														</div>
													</td>
													<td class="py-4 px-6 text-sm">
														<div class="leading-tight"><?php echo $reward_desc; ?></div>
														<?php if ($reward_sub): ?>
														<div class="text-xs text-slate-500 mt-1"><?php echo esc_html($reward_sub); ?></div>
														<?php endif; ?>
													</td>
													<td class="py-4 px-6 text-center">
														<label class="relative inline-flex items-center cursor-pointer">
															<input type="checkbox" class="sr-only peer" <?php checked(1, $camp->active); ?> onchange="o100Wizard.toggleCampaignStatus(<?php echo $camp->id; ?>, this.checked ? 1 : 0)">
															<div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
														</label>
													</td>
													<td class="py-4 px-6 sticky right-0 bg-white group-hover:bg-slate-50 z-10 border-l border-slate-100 text-right shadow-[-1px_0_0_0_#e2e8f0]">
														<div class="flex items-center justify-end space-x-3">
															<?php if ( $is_booster ): ?>
															<button onclick="o100Wizard.openBooster('<?php echo $proxy_type; ?>', <?php echo $camp->id; ?>)" class="text-emerald-600 hover:text-emerald-900 font-bold text-sm px-2 py-1 hover:bg-emerald-50 rounded transition-colors">Edit</button>
															<?php else: ?>
															<button onclick="o100Wizard.open('<?php echo $proxy_type; ?>', <?php echo $camp->id; ?>)" class="text-indigo-600 hover:text-indigo-900 font-bold text-sm px-2 py-1 hover:bg-indigo-50 rounded transition-colors">Edit</button>
															<?php endif; ?>
															<button onclick="o100Wizard.deleteCampaign(<?php echo $camp->id; ?>)" class="text-slate-400 hover:text-red-600 font-bold text-sm px-2 py-1 hover:bg-red-50 rounded transition-colors">Delete</button>
														</div>
													</td>
												</tr>
												<?php
											}
									} else {
										?>
										<tr>
											<td colspan="5" class="py-12 text-center text-slate-500">
												<svg class="w-12 h-12 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
												<p class="text-base font-medium">No active campaigns found.</p>
												<p class="text-sm mt-1">Create your first campaign using the templates above!</p>
											</td>
										</tr>
										<?php
									}
									?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
				</div> <!-- End Tab Growth Engine -->

				<!-- Tab: Frontend Widget -->
				<div id="tab-frontend_widget" class="o100-tab-panel hidden">
					<h2 class="text-xl font-bold text-slate-800 mb-6">Frontend Widget Design</h2>
					
					<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
						<!-- Left: Form -->
						<div class="lg:col-span-2 space-y-6 h-[800px] overflow-y-auto pr-4 pb-20">
							
							<!-- Branding & Theme -->
							<div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
								<div class="flex justify-between items-center mb-4">
									<h3 class="font-bold text-slate-900">Branding & Theme</h3>
									<label class="flex items-center space-x-2 text-sm font-bold text-slate-700 cursor-pointer">
										<input type="checkbox" id="fw_branding_show" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" value="yes" checked>
										<span>Show 'Powered By' Branding</span>
									</label>
								</div>
								<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Theme Primary Color</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="fw_primary_color" value="<?php echo esc_attr($brand_primary); ?>" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
											<input type="text" id="fw_primary_color_hex" value="<?php echo esc_attr($brand_primary); ?>" class="w-full text-sm placeholder-slate-400">
										</div>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Theme Secondary Color</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="fw_secondary_color" value="#FFFFFF" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
											<input type="text" id="fw_secondary_color_hex" value="#FFFFFF" class="w-full text-sm placeholder-slate-400">
										</div>
									</div>
								</div>

								<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Banner Background</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="fw_banner_bg" value="#F5F5F5" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										</div>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Banner Text</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="fw_banner_text" value="#333333" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										</div>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Buttons Background</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="fw_buttons_bg" value="#4F46E5" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										</div>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Buttons Text</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="fw_buttons_text" value="#FFFFFF" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										</div>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Widget Background</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="fw_launcher_bg" value="<?php echo esc_attr($brand_primary); ?>" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										</div>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Widget Text</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="fw_launcher_text_color" value="#FFFFFF" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										</div>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Links Color</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="fw_links_color" value="<?php echo esc_attr($brand_primary); ?>" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										</div>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Icons Color</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="fw_icons_color" value="<?php echo esc_attr($brand_primary); ?>" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										</div>
									</div>
								</div>

								<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Header Logo Image URL</label>
										<div class="flex items-center space-x-2">
											<input type="text" id="fw_logo_url" placeholder="https://..." class="w-full text-sm">
											<button type="button" class="px-3 py-2 bg-slate-100 border border-slate-200 rounded-lg text-sm font-bold text-slate-700 hover:bg-slate-200 whitespace-nowrap" onclick="o100Proxy.openMediaUploader('fw_logo_url')">Select Image</button>
										</div>
									</div>
								</div>
							</div>

							<!-- Display & Positioning -->
							<div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
								<h3 class="font-bold text-slate-900 mb-4">Widget Button</h3>
								<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Button Style</label>
										<select id="fw_btn_style" class="w-full text-sm">
											<option value="icon_with_text">Icon + Text</option>
											<option value="icon_only">Icon Only</option>
											<option value="text_only">Text Only</option>
										</select>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Widget Text</label>
										<input type="text" id="fw_launcher_text" value="Rewards" class="w-full text-sm">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Font Family</label>
										<input type="text" id="fw_font_family" value="inherit" class="w-full text-sm">
									</div>
								</div>
								<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Icon</label>
										<select id="fw_icon" class="w-full text-sm" onchange="document.getElementById('custom_icon_wrap').style.display = (this.value === 'custom') ? 'block' : 'none'; o100Proxy.updateLivePreview();">
											<option value="gift">Gift 🎁</option>
											<option value="star">Star ⭐</option>
											<option value="crown">Crown 👑</option>
											<option value="heart">Heart ❤️</option>
											<option value="custom">Custom Image</option>
										</select>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Placement</label>
										<select id="fw_placement" class="w-full text-sm">
											<option value="right">Bottom Right</option>
											<option value="left">Bottom Left</option>
										</select>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Side Spacing (px)</label>
										<input type="number" id="fw_side_spacing" value="20" class="w-full text-sm">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Bottom Spacing (px)</label>
										<input type="number" id="fw_bottom_spacing" value="20" class="w-full text-sm">
									</div>
								</div>
								<div id="custom_icon_wrap" class="mb-4" style="display: none;">
									<label class="block text-sm font-bold text-slate-700 mb-2">Custom Icon Image URL</label>
									<div class="flex items-center space-x-2">
										<input type="text" id="fw_custom_icon_url" class="w-full text-sm">
										<button type="button" class="px-3 py-2 bg-slate-100 border border-slate-200 rounded-lg text-sm font-bold text-slate-700 hover:bg-slate-200 whitespace-nowrap" onclick="o100Proxy.openMediaUploader('fw_custom_icon_url')">Select Image</button>
									</div>
								</div>
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Visibility</label>
									<select id="fw_visibility" class="w-full md:w-1/3 text-sm">
										<option value="mobile_and_desktop">Desktop & Mobile</option>
										<option value="desktop_only">Desktop Only</option>
										<option value="mobile_only">Mobile Only</option>
										<option value="do_not_show">Hidden</option>
									</select>
								</div>
							</div>

							<!-- Guest Content -->
							<div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
								<h3 class="font-bold text-slate-900 mb-4">Guest View (Not Logged In)</h3>
								<div class="space-y-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Welcome Title</label>
										<input type="text" id="fw_guest_welcome_title" value="Welcome" class="w-full text-sm">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Welcome Description</label>
										<textarea id="fw_guest_welcome_desc" rows="2" class="w-full text-sm">Join our loyalty program to earn rewards!</textarea>
									</div>
									<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
										<div>
											<label class="block text-sm font-bold text-slate-700 mb-2">Join Button Text</label>
											<input type="text" id="fw_guest_btn_text" value="Join Now" class="w-full text-sm">
										</div>
										<div>
											<label class="block text-sm font-bold text-slate-700 mb-2">Join Button URL</label>
											<input type="text" id="fw_guest_btn_url" value="/my-account/" class="w-full text-sm">
										</div>
									</div>
									<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
										<div>
											<label class="block text-sm font-bold text-slate-700 mb-2">"Have Account?" Text</label>
											<input type="text" id="fw_guest_have_account" value="Already have an account?" class="w-full text-sm">
										</div>
										<div>
											<label class="block text-sm font-bold text-slate-700 mb-2">Sign In Link Text</label>
											<input type="text" id="fw_guest_signin_text" value="Sign in" class="w-full text-sm">
										</div>
										<div>
											<label class="block text-sm font-bold text-slate-700 mb-2">Sign In URL</label>
											<input type="text" id="fw_guest_signin_url" value="/my-account/" class="w-full text-sm">
										</div>
									</div>
								</div>
							</div>

							<!-- Member Content -->
							<div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
								<h3 class="font-bold text-slate-900 mb-4">Member View (Logged In)</h3>
								<div class="space-y-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Welcome Back Title (supports {user_name})</label>
										<input type="text" id="fw_member_welcome_title" value="Welcome back, {user_name}!" class="w-full text-sm">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Points Label</label>
										<input type="text" id="fw_member_points_label" value="Points" class="w-full text-sm">
									</div>
								</div>
							</div>

							<!-- Cards Text -->
							<div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
								<h3 class="font-bold text-slate-900 mb-4">Menu Cards Customization</h3>
								<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">"Earn Points" Card Title</label>
										<input type="text" id="fw_card_earn_title" value="Earn Points" class="w-full text-sm">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">"Redeem Points" Card Title</label>
										<input type="text" id="fw_card_redeem_title" value="Redeem Points" class="w-full text-sm">
									</div>
								</div>
							</div>

						</div>

						<!-- Right: Live Preview Mockup Slot -->
						<div class="lg:col-span-1" id="fw-preview-slot">
							<div class="sticky top-8" id="o100-shared-preview">
								<div class="flex justify-between items-center mb-4">
									<h3 class="font-bold text-slate-900">Native Live Preview</h3>
									<div class="flex bg-slate-200 rounded-lg p-1">
										<button id="preview-guest-btn" class="px-3 py-1 text-xs font-bold rounded shadow-sm bg-white text-slate-800" onclick="o100Proxy.togglePreviewMode(false)">Guest</button>
										<button id="preview-member-btn" class="px-3 py-1 text-xs font-bold rounded text-slate-500 hover:text-slate-700" onclick="o100Proxy.togglePreviewMode(true)">Member</button>
									</div>
								</div>
								<div class="bg-slate-100 rounded-xl border border-slate-200 h-[600px] relative overflow-hidden shadow-inner flex flex-col justify-end" id="launcher-preview-container" style="transform: scale(1); z-index: 1;">
									<!-- Real Native Frontend Launcher will mount here -->
									<div id="wll-site-launcher"></div>
								</div>
								<p class="text-xs text-slate-500 mt-4 text-center">Preview is rendering using the exact <code>o100-frontend-launcher.js</code> from the frontend. Click around!</p>
							</div>
						</div>
					</div>
				</div> <!-- End Tab Frontend Widget -->

				<!-- Tab: Referral Templates -->
				<div id="tab-referral_templates" class="o100-tab-panel hidden">
					<div class="mb-6">
						<h2 class="text-xl font-bold text-slate-800">Referral Email & Popup Templates</h2>
						<p class="text-slate-500 text-sm mt-1">Customize the automated emails and messages sent during the referral journey.</p>
					</div>

					<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
						<!-- Left: Form -->
						<div class="lg:col-span-2 space-y-6 h-[800px] overflow-y-auto pr-4 pb-20">
						<!-- Frontend Widget Settings -->
						<div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
							<h3 class="font-bold text-slate-900 mb-2">Frontend Widget Display</h3>
							<p class="text-xs text-slate-500 mb-4">Settings for the "Refer and earn" block inside the popup launcher.</p>
							
							<div class="space-y-4">
								<div class="flex items-center space-x-3 mb-4 border-b border-slate-100 pb-4">
									<input type="checkbox" id="rt_widget_visibility" class="h-4 w-4 text-indigo-600 rounded border-slate-300">
									<label class="text-sm font-bold text-slate-700">Show referral module in frontend widget</label>
								</div>
								
								<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Module Title</label>
										<input type="text" id="rt_widget_title" class="w-full text-sm" value="Refer and earn">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Module Description</label>
										<input type="text" id="rt_widget_desc" class="w-full text-sm" value="Refer your friends and earn rewards!">
									</div>
								</div>

								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2 mt-2">Social Share Icons (Check to enable)</label>
									<div class="flex flex-wrap gap-4 text-sm mt-2">
										<label class="flex items-center space-x-2"><input type="checkbox" class="rt_social_icon" value="facebook"> <span>Facebook</span></label>
										<label class="flex items-center space-x-2"><input type="checkbox" class="rt_social_icon" value="whatsapp"> <span>WhatsApp</span></label>
										<label class="flex items-center space-x-2"><input type="checkbox" class="rt_social_icon" value="twitter"> <span>Twitter/X</span></label>
										<label class="flex items-center space-x-2"><input type="checkbox" class="rt_social_icon" value="email"> <span>Email</span></label>
										<label class="flex items-center space-x-2"><input type="checkbox" class="rt_social_icon" value="linkedin"> <span>LinkedIn</span></label>
									</div>
								</div>
							</div>
						</div>
						<!-- Advocate Email -->
						<div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
							<h3 class="font-bold text-slate-900 mb-2">Advocate Reward Email</h3>
							<p class="text-xs text-slate-500 mb-4">Sent to the referrer when their friend makes a successful purchase.</p>
							
							<div class="space-y-4">
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Email Subject</label>
									<input type="text" id="rt_advocate_subject" class="w-full text-sm" value="You earned a reward!">
								</div>
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Email Content</label>
									<div class="text-xs text-indigo-600 font-medium mb-2 space-x-2">
										Available tags: <span class="bg-indigo-50 px-1 py-0.5 rounded border border-indigo-100 cursor-pointer hover:bg-indigo-100" onclick="o100Proxy.insertTag('rt_advocate_content', '{advocate_name}')">{advocate_name}</span>
										<span class="bg-indigo-50 px-1 py-0.5 rounded border border-indigo-100 cursor-pointer hover:bg-indigo-100" onclick="o100Proxy.insertTag('rt_advocate_content', '{friend_name}')">{friend_name}</span>
										<span class="bg-indigo-50 px-1 py-0.5 rounded border border-indigo-100 cursor-pointer hover:bg-indigo-100" onclick="o100Proxy.insertTag('rt_advocate_content', '{reward_name}')">{reward_name}</span>
									</div>
									<textarea id="rt_advocate_content" rows="4" class="w-full text-sm">Hi {advocate_name}, good news! Your friend {friend_name} just made a purchase. You have earned: {reward_name}.</textarea>
								</div>
							</div>
						</div>

						<!-- Friend Email -->
						<div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
							<h3 class="font-bold text-slate-900 mb-2">Friend Invitation Email</h3>
							<p class="text-xs text-slate-500 mb-4">The default template when advocates send emails from their dashboard.</p>
							
							<div class="space-y-4">
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Email Subject</label>
									<input type="text" id="rt_friend_subject" class="w-full text-sm" value="Here is a gift from {advocate_name}">
								</div>
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Email Content</label>
									<div class="text-xs text-indigo-600 font-medium mb-2 space-x-2">
										Available tags: <span class="bg-indigo-50 px-1 py-0.5 rounded border border-indigo-100 cursor-pointer hover:bg-indigo-100" onclick="o100Proxy.insertTag('rt_friend_content', '{advocate_name}')">{advocate_name}</span>
										<span class="bg-indigo-50 px-1 py-0.5 rounded border border-indigo-100 cursor-pointer hover:bg-indigo-100" onclick="o100Proxy.insertTag('rt_friend_content', '{reward_name}')">{reward_name}</span>
										<span class="bg-indigo-50 px-1 py-0.5 rounded border border-indigo-100 cursor-pointer hover:bg-indigo-100" onclick="o100Proxy.insertTag('rt_friend_content', '{claim_url}')">{claim_url}</span>
									</div>
									<textarea id="rt_friend_content" rows="4" class="w-full text-sm">Hi! {advocate_name} thinks you would love our store and wanted you to have this gift: {reward_name}. Click here to claim it: {claim_url}</textarea>
								</div>
							</div>
						</div>

						<!-- Popup Template -->
						<div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
							<h3 class="font-bold text-slate-900 mb-2">Friend Welcome Popup</h3>
							<p class="text-xs text-slate-500 mb-4">The message shown to friends when they land on your site via a referral link.</p>
							
							<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4 border-b border-slate-100 pb-4">
								<div>
									<label class="flex items-center space-x-2 text-sm font-bold text-slate-700 mb-2 cursor-pointer">
										<input type="checkbox" id="rt_popup_enable_img" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" value="yes" onchange="document.getElementById('popup_img_wrap').style.display = this.checked ? 'block' : 'none';">
										<span>Show Popup Image</span>
									</label>
									<div id="popup_img_wrap" style="display: none;">
										<div class="flex items-center space-x-2">
											<input type="text" id="rt_popup_img_url" placeholder="https://..." class="w-full text-sm">
											<button type="button" class="px-3 py-2 bg-slate-100 border border-slate-200 rounded-lg text-sm font-bold text-slate-700 hover:bg-slate-200 whitespace-nowrap" onclick="o100Proxy.openMediaUploader('rt_popup_img_url')">Select Image</button>
										</div>
									</div>
								</div>
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Popup Background Color</label>
									<div class="flex items-center space-x-3">
										<input type="color" id="rt_popup_bg_color" value="#FFFFFF" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										<input type="text" id="rt_popup_bg_color_hex" value="#FFFFFF" class="w-full text-sm placeholder-slate-400">
									</div>
								</div>
							</div>

							<div class="space-y-4">
								<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Popup Title</label>
										<input type="text" id="rt_popup_title" class="w-full text-sm" value="Welcome!">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Title Text Color</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="rt_popup_title_color" value="#000000" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										</div>
									</div>
								</div>
								
								<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Popup Sub-Title</label>
										<input type="text" id="rt_popup_subtitle" class="w-full text-sm" value="Get your reward">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Sub-Title Text Color</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="rt_popup_subtitle_color" value="#333333" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										</div>
									</div>
								</div>

								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Popup Message</label>
									<div class="text-xs text-indigo-600 font-medium mb-2 space-x-2">
										Available tags: <span class="bg-indigo-50 px-1 py-0.5 rounded border border-indigo-100 cursor-pointer hover:bg-indigo-100" onclick="o100Proxy.insertTag('rt_popup_message', '{advocate_name}')">{advocate_name}</span>
										<span class="bg-indigo-50 px-1 py-0.5 rounded border border-indigo-100 cursor-pointer hover:bg-indigo-100" onclick="o100Proxy.insertTag('rt_popup_message', '{reward_name}')">{reward_name}</span>
									</div>
									<textarea id="rt_popup_message" rows="3" class="w-full text-sm">Your friend {advocate_name} sent you a gift! Enter your email to claim your {reward_name}.</textarea>
								</div>
								
								<div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t border-slate-100 pt-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Claim Button Text</label>
										<input type="text" id="rt_popup_btn_text" class="w-full text-sm" value="Claim Reward">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Button Background</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="rt_popup_btn_bg" value="<?php echo esc_attr($brand_primary); ?>" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										</div>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Button Text Color</label>
										<div class="flex items-center space-x-3">
											<input type="color" id="rt_popup_btn_text_color" value="#FFFFFF" class="h-10 w-10 p-0 border-0 rounded cursor-pointer">
										</div>
									</div>
								</div>
							</div>
							</div>
						</div>
						<!-- Right: Live Preview Mockup Slot -->
						<div class="lg:col-span-1" id="rt-preview-slot">
							<div class="sticky top-8">
								<div class="flex justify-between items-center mb-4">
									<h3 class="font-bold text-slate-900">Referral Preview</h3>
									<div class="flex bg-slate-200 rounded-lg p-1">
										<button id="rt-preview-advocate-btn" class="px-3 py-1 text-xs font-bold rounded text-slate-500 hover:text-slate-700" onclick="o100Proxy.toggleRtPreview('advocate')">Advocate Email</button>
										<button id="rt-preview-friend-btn" class="px-3 py-1 text-xs font-bold rounded shadow-sm bg-white text-slate-800" onclick="o100Proxy.toggleRtPreview('friend')">Friend Popup</button>
									</div>
								</div>
								<div class="bg-slate-100 rounded-xl border border-slate-200 h-[600px] relative overflow-hidden shadow-inner flex items-center justify-center p-6">
									
									<!-- Mockup: Friend Welcome Popup -->
									<div class="w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden" id="rt-preview-friend-modal" style="background-color: #FFFFFF;">
										<div id="rt-preview-img-wrap" class="w-full h-32 bg-slate-200 bg-cover bg-center" style="display: none;"></div>
										<div class="p-6 text-center relative">
											<button class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">✕</button>
											<h4 id="rt-preview-title" class="text-xl font-bold mb-1" style="color: #000000;">Welcome!</h4>
											<p id="rt-preview-subtitle" class="text-sm font-medium mb-4" style="color: #333333;">Get your reward</p>
											<div id="rt-preview-message" class="text-sm text-slate-600 mb-6 leading-relaxed">
												Your friend Jane Doe sent you a gift! Enter your email to claim your $10 Coupon.
											</div>
											<div class="space-y-3">
												<input type="email" placeholder="Enter your email address" class="w-full px-4 py-2 text-sm border border-slate-300 rounded-lg text-center" disabled>
												<button id="rt-preview-btn" class="w-full py-2.5 rounded-lg text-sm font-bold shadow-sm" style="background-color: #2563eb; color: #FFFFFF;">Claim Reward</button>
											</div>
											<p class="text-xs text-slate-400 mt-4">By claiming, you agree to our terms.</p>
										</div>
									</div>

									<!-- Mockup: Advocate Email -->
									<div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white overflow-hidden hidden flex flex-col h-full absolute inset-0 m-6 shadow-sm" id="rt-preview-advocate-modal">
										<!-- Email Header Mock -->
										<div class="bg-slate-50 px-4 py-3 border-b border-slate-100 flex items-center space-x-3">
											<div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-500 font-bold">M</div>
											<div>
												<div class="text-xs font-bold text-slate-700">From: Your Store</div>
												<div class="text-[10px] text-slate-500">To: advocate@example.com</div>
											</div>
										</div>
										<div class="px-4 py-3 border-b border-slate-100">
											<div class="text-[10px] text-slate-500 mb-1">Subject</div>
											<div class="text-sm font-bold text-slate-800" id="rt-preview-adv-subject">You earned a reward!</div>
										</div>
										<div class="p-6 text-sm text-slate-700 whitespace-pre-wrap flex-1 overflow-y-auto" id="rt-preview-adv-content">
											Hi Jane, good news! Your friend John Smith just made a purchase. You have earned: $10 Coupon.
										</div>
									</div>

								</div>
							</div>
						</div>
					</div>
				</div> <!-- End Tab Referral Templates -->

				<!-- Tab: General Settings -->
				<div id="tab-general_settings" class="o100-tab-panel hidden">
					<h2 class="text-xl font-bold text-slate-800 mb-6">General Loyalty Settings</h2>
					
					<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-8">
						<!-- Display Options -->
						<div>
							<h3 class="font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Nomenclature & Display</h3>
							<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Points Label (Singular)</label>
									<input type="text" id="gs_points_label_singular" class="w-full text-sm" value="Point">
								</div>
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Points Label (Plural)</label>
									<input type="text" id="gs_points_label_plural" class="w-full text-sm" value="Points">
								</div>
								<div class="lg:col-span-2">
									<label class="block text-sm font-bold text-slate-700 mb-2">Points Rounding Rule</label>
									<select id="gs_rounding_type" class="w-full text-sm">
										<option value="no_round">No rounding</option>
										<option value="round_up">Round up to nearest integer</option>
										<option value="round_down">Round down to nearest integer</option>
										<option value="round_half">Round half to nearest integer</option>
									</select>
								</div>
							</div>
							
							<div class="space-y-3 bg-slate-50 p-4 rounded-lg border border-slate-100">
								<h4 class="font-bold text-slate-800 text-sm mb-3">Frontend Messages ("You can earn X points")</h4>
								<label class="flex items-center space-x-3 cursor-pointer">
									<input type="checkbox" id="gs_product_earn_msg" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" value="yes">
									<span class="text-sm font-medium text-slate-700">Show message on Product pages</span>
								</label>
								<label class="flex items-center space-x-3 cursor-pointer">
									<input type="checkbox" id="gs_cart_earn_msg" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" value="yes">
									<span class="text-sm font-medium text-slate-700">Show message on Cart page</span>
								</label>
								<label class="flex items-center space-x-3 cursor-pointer">
									<input type="checkbox" id="gs_checkout_earn_msg" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" value="yes">
									<span class="text-sm font-medium text-slate-700">Show message on Checkout page</span>
								</label>
							</div>
						</div>

						<!-- Earning Rules -->
						<div>
							<h3 class="font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Earning & Expiry Rules</h3>
							<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Calculation Basis</label>
									<select id="gs_calculation_basis" class="w-full text-sm">
										<option value="subtotal">Subtotal (Excluding Tax & Shipping)</option>
										<option value="total">Total (Including Tax & Shipping)</option>
									</select>
									<p class="text-xs text-slate-500 mt-2">What should points be calculated against?</p>
								</div>
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Points Expiry (Days)</label>
									<input type="number" id="gs_points_expiry" class="w-full text-sm" value="0">
									<p class="text-xs text-slate-500 mt-2">Set to 0 for points to never expire.</p>
								</div>
							</div>
						</div>

						<!-- VIP Tiers Buffer -->
						<div>
							<h3 class="font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">VIP Tiers</h3>
							<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Tier Grace Period (Days)</label>
									<input type="number" id="gs_grace_period" class="w-full text-sm" value="30">
									<p class="text-xs text-slate-500 mt-2">Days before a user gets downgraded if they don't maintain their point balance.</p>
								</div>
							</div>
						</div>
					</div>
				</div> <!-- End Tab General Settings -->

			</div>
		</div>

		<!-- Global Toast Notification -->
		<div id="o100-toast" class="o100-toast">
			<svg id="o100-toast-icon" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
			<span id="o100-toast-msg">Saved successfully!</span>
		</div>

		<!-- Global Confirm Modal -->
		<div id="o100-confirm-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] hidden items-center justify-center opacity-0 transition-opacity duration-300">
			<div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 transform scale-95 transition-transform duration-300">
				<div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
					<svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
				</div>
				<h3 class="text-lg font-bold text-slate-900 text-center mb-2" id="o100-confirm-title">Confirm Action</h3>
				<p class="text-sm text-slate-500 text-center mb-6" id="o100-confirm-msg">Are you sure?</p>
				<div class="flex space-x-3">
					<button id="o100-confirm-cancel" class="flex-1 py-2 px-4 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-50 transition-colors">Cancel</button>
					<button id="o100-confirm-ok" class="flex-1 py-2 px-4 bg-red-600 rounded-xl text-sm font-bold text-white hover:bg-red-700 transition-colors">Delete</button>
				</div>
			</div>
		</div>

		<!-- Quick Booster Modal -->
		<div id="o100-booster-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] hidden items-center justify-center opacity-0 transition-opacity duration-300">
			<div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 transform scale-95 transition-transform duration-300 relative">
				<button onclick="o100Wizard.closeBooster()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
					<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
				</button>
				
				<div class="flex items-center space-x-3 mb-6">
					<div id="booster-icon-container" class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center">
						<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
					</div>
					<div>
						<h3 class="text-lg font-bold text-slate-900" id="booster-title">Setup Booster</h3>
						<p class="text-xs text-slate-500" id="booster-desc">Configure reward</p>
					</div>
				</div>

				<input type="hidden" id="booster_type" value="">
				<input type="hidden" id="booster_campaign_id" value="0">
				
				<div class="space-y-4 mb-6">
					<div class="flex items-center justify-between p-4 border border-slate-200 rounded-xl bg-slate-50">
						<div>
							<span class="block text-sm font-bold text-slate-900">Status</span>
							<span class="block text-xs text-slate-500">Enable this booster</span>
						</div>
						<label class="relative inline-flex items-center cursor-pointer">
							<input type="checkbox" id="booster_status" class="sr-only peer" checked>
							<div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
						</label>
					</div>

					<div>
						<label class="block text-sm font-bold text-slate-700 mb-2">Reward Points <span class="text-red-500">*</span></label>
						<div class="relative">
							<input type="number" id="booster_points" class="w-full pl-3 pr-12 text-lg font-bold text-slate-900 border-slate-300 rounded-xl focus:ring-emerald-500 focus:border-emerald-500" value="50">
							<div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
								<span class="text-slate-400 font-medium">pts</span>
							</div>
						</div>
					</div>
				</div>

				<div class="flex space-x-3">
					<button onclick="o100Wizard.closeBooster()" class="flex-1 py-2.5 px-4 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-50 transition-colors">Cancel</button>
					<button id="booster_save_btn" onclick="o100Wizard.saveBooster()" class="flex-1 py-2.5 px-4 bg-emerald-600 rounded-xl text-sm font-bold text-white hover:bg-emerald-700 transition-colors flex items-center justify-center">
						<span id="booster_save_text">Save Changes</span>
						<svg id="booster_save_loader" class="animate-spin ml-2 h-4 w-4 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
					</button>
				</div>
			</div>
		</div>

		<!-- The Wizard Modal -->
		<div id="o100-wizard" class="o100-wizard-overlay">
			<!-- Main Modal Body -->
			<div class="o100-wizard-modal bg-slate-50 w-full max-w-5xl rounded-2xl shadow-2xl overflow-hidden relative flex flex-col max-h-[90vh]">
				
				<!-- Loading Overlay -->
				<div id="o100-wizard-loader" class="absolute inset-0 bg-white/80 backdrop-blur-sm z-50 flex flex-col items-center justify-center" style="display: none;">
					<svg class="animate-spin h-10 w-10 text-indigo-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
						<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
						<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
					</svg>
					<span class="text-slate-600 font-medium">Loading campaign data...</span>
				</div>

				<div class="flex flex-1 overflow-hidden">

				<!-- Left Sidebar: Stepper -->
				<div class="w-72 bg-slate-50 border-r border-slate-200 p-8 flex flex-col">
					<h2 class="text-xl font-bold text-slate-900 mb-8" id="wizard-title">Configure Setup</h2>
					
					<div class="flex-1">
						<div class="o100-step-item is-active" id="step-nav-1" onclick="o100Wizard.goToStep(1)" style="cursor: pointer;">
							<div class="flex items-start">
								<div class="o100-step-indicator">1</div>
								<div class="ml-4">
									<h4 class="text-sm font-bold text-slate-900">The Trigger</h4>
								</div>
							</div>
						</div>
						<div class="o100-step-item is-pending" id="step-nav-2" onclick="o100Wizard.goToStep(2)" style="cursor: pointer;">
							<div class="flex items-start">
								<div class="o100-step-indicator">2</div>
								<div class="ml-4">
									<h4 class="text-sm font-bold text-slate-900">The Reward</h4>
								</div>
							</div>
						</div>
						<div class="o100-step-item is-pending" id="step-nav-3" onclick="o100Wizard.goToStep(3)" style="cursor: pointer;">
							<div class="flex items-start">
								<div class="o100-step-indicator">3</div>
								<div class="ml-4">
									<h4 class="text-sm font-bold text-slate-900">Conditions</h4>
								</div>
							</div>
						</div>
						<div class="o100-step-item is-pending" id="step-nav-4" onclick="o100Wizard.goToStep(4)" style="cursor: pointer;">
							<div class="flex items-start">
								<div class="o100-step-indicator">4</div>
								<div class="ml-4">
									<h4 class="text-sm font-bold text-slate-900">Notification</h4>
								</div>
							</div>
						</div>
					</div>
					
					<div class="pt-6 border-t border-slate-200">
						<button class="text-sm text-slate-500 hover:text-slate-900 font-medium" onclick="o100Wizard.close()">Cancel & Exit</button>
					</div>
				</div>

				<!-- Right Content -->
				<div class="flex-1 bg-white p-10 flex flex-col relative overflow-y-auto">
					<div class="flex-1">
						<!-- Step 1 -->
						<div class="o100-form-step is-active" id="step-content-1">
							<h3 class="text-2xl font-bold text-slate-900 mb-2">Campaign Basics</h3>
							<div class="space-y-6">
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Campaign Name <span class="text-red-500">*</span></label>
									<input type="text" id="wizard_campaign_name" placeholder="e.g. Birthday Celebration Reward" class="w-full placeholder-slate-400">
								</div>
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Campaign Description</label>
									<textarea rows="3" id="wizard_campaign_desc" class="w-full"></textarea>
								</div>
								<div id="birthday-specific-settings" class="mt-6 pt-6 border-t border-slate-100 hidden">
									<h4 class="text-sm font-bold text-slate-900 mb-3">Birthday Capture Settings</h4>
									<label class="flex items-start space-x-3 cursor-pointer">
										<input type="checkbox" id="wizard_allow_birthday_edit" class="w-4 h-4 mt-1">
										<span class="text-sm text-slate-700">Allow customers to modify their birthday</span>
									</label>
								</div>
								<div id="points-specific-settings" class="mt-6 pt-6 border-t border-slate-100 hidden">
									<h4 class="text-sm font-bold text-slate-900 mb-3">Earn Rate</h4>
									<label class="block text-sm font-bold text-slate-700 mb-2">For every $X spent <span class="text-red-500">*</span></label>
									<input type="number" id="wizard_point_earn_price" value="1" class="w-full">
								</div>
								<div id="punch_card-specific-settings" class="mt-6 pt-6 border-t border-slate-100 hidden">
									<h4 class="text-sm font-bold text-slate-900 mb-3">Target Stamp Count</h4>
									<label class="block text-sm font-bold text-slate-700 mb-2">How many stamps to earn the reward? <span class="text-red-500">*</span></label>
									<input type="number" id="wizard_punch_count" value="5" class="w-full mb-4">
									
									<h4 class="text-sm font-bold text-slate-900 mb-3">Participating Products <span class="text-red-500">*</span></h4>
									<label class="block text-sm font-bold text-slate-700 mb-2">Select which products give stamps. (Required)</label>
									<div id="punch_products_wrapper" class="o100-mcs-wrap relative">
										<input type="hidden" id="wizard_punch_products_val" class="o100-cond-val" value="">
										<div class="o100-mcs-tags flex flex-wrap gap-2 mb-2" id="punch_products_tags"></div>
										<input type="text" class="o100-mcs-input w-full" placeholder="Search products..." autocomplete="off" id="punch_products_search">
										<div class="o100-mcs-dd absolute left-0 right-0 top-full bg-white border border-slate-200 rounded-lg shadow-lg z-50 max-h-48 overflow-y-auto hidden" id="punch_products_dropdown"></div>
									</div>
								</div>
								<div id="spend_save-specific-settings" class="mt-6 pt-6 border-t border-slate-100 hidden">
									<h4 class="text-sm font-bold text-slate-900 mb-3">Minimum Subtotal <span class="text-red-500">*</span></h4>
									<label class="block text-sm font-bold text-slate-700 mb-2">How much should the customer spend to trigger this reward?</label>
									<input type="number" id="wizard_spend_min_subtotal" value="100" class="w-full mb-4">
								</div>
								<div id="monthly_reward-specific-settings" class="mt-6 pt-6 border-t border-slate-100 hidden">
									<h4 class="text-sm font-bold text-slate-900 mb-3">Target Audience <span class="text-red-500">*</span></h4>
									<label class="block text-sm font-bold text-slate-700 mb-2">Who receives this monthly reward?</label>
									<select id="wizard_monthly_target_audience" class="w-full mb-4">
										<option value="all">All Registered Users</option>
										<option value="active_30">Users active in the last 30 days</option>
										<option value="active_90">Users active in the last 90 days</option>
									</select>
									
									<h4 class="text-sm font-bold text-slate-900 mb-3">Select Reward Coupon <span class="text-red-500">*</span></h4>
									<label class="block text-sm font-bold text-slate-700 mb-2">Which coupon to send?</label>
									<select id="wizard_monthly_coupon" class="w-full mb-4">
										<option value="">-- Create Custom Discount (Next Step) --</option>
										<?php 
										$ref_reward_options = [];
										$wc_coupons = get_posts(['post_type'=>'shop_coupon','post_status'=>'publish','posts_per_page'=>50,'orderby'=>'title','order'=>'ASC']);
										foreach ($wc_coupons as $wcc) { $c = new WC_Coupon($wcc->ID); $ref_reward_options[] = ['value'=>'wc_'.$wcc->ID, 'label'=>$wcc->post_title.' ('.$c->get_discount_type().': '.$c->get_amount().')', 'group'=>'WooCommerce Coupons']; }
										if (class_exists('O100_Promotions_DB')) { $promos = O100_Promotions_DB::query(['status'=>'active']); foreach ($promos as $p) { if ($p['rule_type'] !== 'simple') continue; $cfg = json_decode($p['action_config']??'{}', true); $dt = $cfg['discount_type']??''; $dv = $cfg['discount_value']??''; $sum = $dt==='percentage' ? $dv.'%' : '$'.$dv; $ref_reward_options[] = ['value'=>'promo_'.$p['id'], 'label'=>$p['title'].' ('.$sum.')', 'group'=>'O100 Promotions']; } }
										
										if (isset($ref_reward_options)) {
											$cg=''; 
											foreach($ref_reward_options as $ro){
												if($ro['group']!==$cg){
													if($cg) echo '</optgroup>'; 
													echo '<optgroup label="'.esc_attr($ro['group']).'">'; 
													$cg=$ro['group'];
												} 
												echo '<option value="'.esc_attr($ro['value']).'">'.esc_html($ro['label']).'</option>';
											} 
											if($cg) echo '</optgroup>'; 
										}
										?>
									</select>

									<h4 class="text-sm font-bold text-slate-900 mb-3">Coupon Validity</h4>
									<label class="block text-sm font-bold text-slate-700 mb-2">When should this coupon expire?</label>
									<select id="wizard_monthly_coupon_expiry" class="w-full mb-4">
										<option value="end_of_month">End of current month</option>
										<option value="7_days">7 Days</option>
										<option value="30_days">30 Days</option>
										<option value="inherit">Inherit from selected coupon</option>
									</select>
								</div>
							</div>
						</div>
												<!-- Step 2 Content -->
						<div class="o100-form-step" id="step-content-2">
							<h3 class="text-2xl font-bold text-slate-900 mb-2">Choose the Reward</h3>
							<p class="text-slate-500 mb-8">Decide what perk the customer receives when the trigger occurs.</p>
							
							<div class="grid grid-cols-3 gap-4 mb-8" id="reward-type-selector">
								<!-- Give Points -->
								<div id="reward-opt-points" class="o100-reward-opt border border-slate-200 rounded-xl p-4 cursor-pointer text-center hover:border-indigo-300 transition-colors relative overflow-hidden" onclick="o100Wizard.setRewardType('points')">
									<div class="o100-reward-opt-check hidden absolute top-0 right-0 w-8 h-8 bg-indigo-500 items-center justify-center rounded-bl-xl text-white">
										<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
									</div>
									<div class="font-bold text-slate-700">Give Points</div>
								</div>
								<!-- Give Discount -->
								<div id="reward-opt-discount" class="o100-reward-opt border border-slate-200 rounded-xl p-4 cursor-pointer text-center hover:border-indigo-300 transition-colors relative overflow-hidden" onclick="o100Wizard.setRewardType('discount')">
									<div class="o100-reward-opt-check hidden absolute top-0 right-0 w-8 h-8 bg-indigo-500 items-center justify-center rounded-bl-xl text-white">
										<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
									</div>
									<div class="font-bold text-slate-700">Give Discount</div>
								</div>
								<!-- Free Item -->
								<div id="reward-opt-free_item" class="o100-reward-opt border border-slate-200 rounded-xl p-4 cursor-pointer text-center hover:border-indigo-300 transition-colors relative overflow-hidden" onclick="o100Wizard.setRewardType('free_item')">
									<div class="o100-reward-opt-check hidden absolute top-0 right-0 w-8 h-8 bg-indigo-500 items-center justify-center rounded-bl-xl text-white">
										<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
									</div>
									<div class="font-bold text-slate-700">Free Item</div>
								</div>
							</div>
							
							<!-- Dynamic Config Panels -->
							<div id="reward-panel-referral" class="hidden bg-white border border-slate-200 rounded-xl p-6 shadow-sm mb-6">
							<h4 class="font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Referral Rewards</h4>
							<?php
							$ref_reward_options = [];
							$wc_coupons = get_posts(['post_type'=>'shop_coupon','post_status'=>'publish','posts_per_page'=>50,'orderby'=>'title','order'=>'ASC']);
							foreach ($wc_coupons as $wcc) { $c = new WC_Coupon($wcc->ID); $ref_reward_options[] = ['value'=>'wc_'.$wcc->ID, 'label'=>$wcc->post_title.' ('.$c->get_discount_type().': '.$c->get_amount().')', 'group'=>'WooCommerce Coupons']; }
							if (class_exists('O100_Promotions_DB')) { $promos = O100_Promotions_DB::query(['status'=>'active']); foreach ($promos as $p) { if ($p['rule_type'] !== 'simple') continue; $cfg = json_decode($p['action_config']??'{}', true); $dt = $cfg['discount_type']??''; $dv = $cfg['discount_value']??''; $sum = $dt==='percentage' ? $dv.'%' : '$'.$dv; $ref_reward_options[] = ['value'=>'promo_'.$p['id'], 'label'=>$p['title'].' ('.$sum.')', 'group'=>'O100 Promotions']; } }
							?>
							<div class="grid grid-cols-2 gap-6 mb-4">
								<div class="p-4 bg-slate-50 rounded-lg">
									<h5 class="font-bold text-indigo-700 mb-3" style="border-left:3px solid #4F46E5; padding-left:8px;">Advocate (Referrer)</h5>
									<label class="block text-xs font-bold text-slate-700 mb-1">Reward Type</label>
									<select id="wizard_advocate_type" class="w-full mb-3 text-sm" onchange="o100RefToggle('advocate', this.value)">
										<option value="point">Points</option>
										<option value="coupon">Discount Coupon</option>
									</select>
									<div id="ref-advocate-points-panel">
										<label class="block text-xs font-bold text-slate-700 mb-1">Points Amount</label>
										<input type="number" id="wizard_advocate_amount" class="w-full text-sm" value="100">
									</div>
									<div id="ref-advocate-coupon-panel" style="display:none;">
										<label class="block text-xs font-bold text-slate-700 mb-1">Select Coupon</label>
										<select id="wizard_advocate_coupon" class="w-full mb-2 text-sm" onchange="o100RefCouponToggle('advocate', this.value)">
											<option value="">-- Select Existing Coupon --</option>
											<?php $cg=''; foreach($ref_reward_options as $ro){if($ro['group']!==$cg){if($cg) echo '</optgroup>'; echo '<optgroup label="'.esc_attr($ro['group']).'">'; $cg=$ro['group'];} echo '<option value="'.esc_attr($ro['value']).'">'.esc_html($ro['label']).'</option>';} if($cg) echo '</optgroup>'; ?>
											<option value="__custom__">-- Create Custom Coupon --</option>
										</select>
									</div>
								</div>
								<div class="p-4 bg-slate-50 rounded-lg">
									<h5 class="font-bold text-emerald-700 mb-3" style="border-left:3px solid #10B981; padding-left:8px;">Friend (Referred)</h5>
									<label class="block text-xs font-bold text-slate-700 mb-1">Reward Type</label>
									<select id="wizard_friend_type" class="w-full mb-3 text-sm" onchange="o100RefToggle('friend', this.value)">
										<option value="point">Points</option>
										<option value="coupon">Discount Coupon</option>
									</select>
									<div id="ref-friend-points-panel">
										<label class="block text-xs font-bold text-slate-700 mb-1">Points Amount</label>
										<input type="number" id="wizard_friend_amount" class="w-full text-sm" value="50">
									</div>
									<div id="ref-friend-coupon-panel" style="display:none;">
										<label class="block text-xs font-bold text-slate-700 mb-1">Select Coupon</label>
										<select id="wizard_friend_coupon" class="w-full mb-2 text-sm" onchange="o100RefCouponToggle('friend', this.value)">
											<option value="">-- Select Existing Coupon --</option>
											<?php $cg=''; foreach($ref_reward_options as $ro){if($ro['group']!==$cg){if($cg) echo '</optgroup>'; echo '<optgroup label="'.esc_attr($ro['group']).'">'; $cg=$ro['group'];} echo '<option value="'.esc_attr($ro['value']).'">'.esc_html($ro['label']).'</option>';} if($cg) echo '</optgroup>'; ?>
											<option value="__custom__">-- Create Custom Coupon --</option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<script>
						function o100RefToggle(role, val) {
							document.getElementById('ref-' + role + '-points-panel').style.display = val === 'point' ? '' : 'none';
							document.getElementById('ref-' + role + '-coupon-panel').style.display = val === 'coupon' ? '' : 'none';
						}
						function o100RefCouponToggle(role, val) {
							if (val === '__custom__') { o100RefCouponModal.open(role); return; }
						}
						var o100RefCouponModal = {
							currentRole: '',
							open: function(role) {
								this.currentRole = role;
								document.getElementById('o100-ref-coupon-modal').style.display = 'flex';
								document.getElementById('rcm_title').value = role === 'advocate' ? 'Referral Advocate Reward' : 'Referral Friend Welcome Bonus';
								document.getElementById('rcm_code').value = '';
								document.getElementById('rcm_desc').value = '';
								document.getElementById('rcm_discount_type').value = 'fixed';
								document.getElementById('rcm_discount_value').value = '5';
								document.getElementById('rcm_min_spend').value = '0';
								document.getElementById('rcm_individual_use').checked = true;
								document.getElementById('rcm_usage_limit').value = '1';
								document.getElementById('rcm_expiry_days').value = '30';
								document.getElementById('rcm_priority').value = '10';
							},
							close: function() {
								document.getElementById('o100-ref-coupon-modal').style.display = 'none';
								var sel = document.getElementById('wizard_' + this.currentRole + '_coupon');
								if (sel && sel.value === '__custom__') sel.value = '';
							},
							save: function() {
								var btn = document.getElementById('rcm_save_btn');
								btn.disabled = true; btn.innerText = 'Creating...';
								var fd = new FormData();
								fd.append('action', 'o100_create_referral_coupon');
								fd.append('nonce', '<?php echo wp_create_nonce("o100_loyalty_proxy"); ?>');
								['title','desc','discount_type','discount_value','min_spend','usage_limit','expiry_days'].forEach(function(k){
									var el = document.getElementById('rcm_' + k);
									if (el) fd.append(k === 'desc' ? 'description' : k, el.value);
								});
								fd.append('individual_use', document.getElementById('rcm_individual_use').checked ? '1' : '0');
								fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(function(res){
									btn.disabled = false; btn.innerText = 'Create Coupon';
									if (res.success) {
										['advocate','friend'].forEach(function(r){ var sel = document.getElementById('wizard_'+r+'_coupon'); if(sel){ var opt = document.createElement('option'); opt.value='promo_'+res.data.id; opt.textContent='[NEW] '+res.data.title; var co=sel.querySelector('option[value="__custom__"]'); sel.insertBefore(opt, co); }});
										var cs = document.getElementById('wizard_'+o100RefCouponModal.currentRole+'_coupon');
										if(cs) cs.value = 'promo_'+res.data.id;
										o100RefCouponModal.close();
									} else { alert(res.data ? res.data.message : 'Failed'); }
								}).catch(function(e){ btn.disabled=false; btn.innerText='Create Coupon'; alert('Error: '+e.message); });
							}
						};
						</script>
						<div id="o100-ref-coupon-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:999999; align-items:center; justify-content:center; overflow-y:auto; padding:20px 0;">
							<div style="background:#fff; width:520px; max-width:95vw; max-height:85vh; border-radius:1rem; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); padding:28px; margin:auto;">
								<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
									<h3 style="font-size:1.25rem; font-weight:700; color:#0f172a; margin:0;">Create Referral Coupon</h3>
									<button onclick="o100RefCouponModal.close()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#94a3b8;">&times;</button>
								</div>
								<div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:16px;">
									<div style="font-weight:700; color:#4F46E5; font-size:0.8rem; text-transform:uppercase; margin-bottom:10px;">Basic Info</div>
									<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Coupon Name *</label>
									<input type="text" id="rcm_title" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:10px; font-size:0.875rem;">

									<label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px; margin-top:10px;">Description</label>
									<input type="text" id="rcm_desc" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;">
								</div>
								<div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:16px;">
									<div style="font-weight:700; color:#10B981; font-size:0.8rem; text-transform:uppercase; margin-bottom:10px;">Discount Settings</div>
									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
										<div><label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Discount Type</label><select id="rcm_discount_type" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;"><option value="fixed">Fixed Amount ($)</option><option value="percentage">Percentage (%)</option></select></div>
										<div><label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Discount Value *</label><input type="number" id="rcm_discount_value" value="5" step="0.01" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;"></div>
									</div>
									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
										<div><label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Minimum Spend ($)</label><input type="number" id="rcm_min_spend" value="0" step="0.01" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;"></div>
										<div style="display:flex; align-items:flex-end; padding-bottom:4px;"><label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.8rem; font-weight:600; color:#475569;"><input type="checkbox" id="rcm_individual_use" checked style="width:16px; height:16px;"> Individual Use Only</label></div>
									</div>
								</div>
								<div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:20px;">
									<div style="font-weight:700; color:#F59E0B; font-size:0.8rem; text-transform:uppercase; margin-bottom:10px;">Limits & Expiry</div>
									<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
										<div><label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Usage Limit</label><input type="number" id="rcm_usage_limit" value="1" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;"></div>
										<div><label style="display:block; font-size:0.8rem; font-weight:600; color:#475569; margin-bottom:4px;">Expiry (days)</label><input type="number" id="rcm_expiry_days" value="30" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.875rem;"></div>
									</div>
								</div>
								<div style="display:flex; justify-content:flex-end; gap:10px;">
									<button onclick="o100RefCouponModal.close()" style="padding:10px 20px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; color:#64748b; font-weight:600; cursor:pointer; font-size:0.875rem;">Cancel</button>
									<button id="rcm_save_btn" onclick="o100RefCouponModal.save()" style="padding:10px 24px; border:none; border-radius:10px; background:#4F46E5; color:#fff; font-weight:700; cursor:pointer; font-size:0.875rem; box-shadow:0 2px 8px rgba(79,70,229,0.3);">Create Coupon</button>
								</div>
							</div>
						</div>

							<div id="reward-panel-points" class="hidden bg-white border border-slate-200 rounded-xl p-6 shadow-sm mb-6">
								<label class="block text-sm font-bold text-slate-700 mb-2">How many points? <span class="text-red-500">*</span></label>
								<input type="number" id="wizard_reward_points" class="w-full mb-4 text-lg" value="100" placeholder="e.g. 100">
								
								<!-- Intelligent Conversion Rate Notice -->
								<div id="points-conversion-notice" class="bg-indigo-50 border border-indigo-100 rounded-lg p-4 mt-2">
									<p class="text-xs text-indigo-800 font-bold mb-1">Set Point Value</p>
									<p class="text-xs text-indigo-600 mb-3">You haven't defined how much these points are worth when customers redeem them.</p>
									<div class="flex items-center space-x-2">
										<input type="number" id="wizard_conversion_points" value="100" class="w-24 text-sm py-1 px-2" />
										<span class="text-sm font-bold text-slate-600">Points = $</span>
										<input type="number" id="wizard_conversion_value" value="1" class="w-20 text-sm py-1 px-2" />
										<span class="text-sm font-bold text-slate-600">Off</span>
									</div>
								</div>
							</div>

							<div id="reward-panel-discount" class="hidden bg-white border border-slate-200 rounded-xl p-6 shadow-sm mb-6">
								<h4 class="font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Discount Details</h4>
								<div class="grid grid-cols-2 gap-4 mb-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Type</label>
										<select id="wizard_discount_type" class="w-full">
											<option value="percentage">Percentage (%)</option>
											<option value="fixed">Fixed Amount ($)</option>
										</select>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Value</label>
										<input type="number" id="wizard_discount_value" class="w-full" value="10" placeholder="e.g. 10">
									</div>
								</div>
								<div class="grid grid-cols-2 gap-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Expiry (Days) <span class="font-normal text-slate-400 text-xs ml-1">Optional</span></label>
										<input type="number" id="wizard_discount_expiry" class="w-full" placeholder="e.g. 30">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Usage Limit <span class="font-normal text-slate-400 text-xs ml-1">Optional</span></label>
										<input type="number" id="wizard_discount_limit" class="w-full" placeholder="e.g. 1">
									</div>
								</div>
							</div>

							<div id="reward-panel-free_item" class="hidden bg-white border border-slate-200 rounded-xl p-6 shadow-sm mb-6">
								<h4 class="font-bold text-slate-900 mb-4 border-b border-slate-100 pb-2">Reward Product</h4>
								
								<div class="mb-6 space-y-3">
									<label class="flex items-center space-x-2 cursor-pointer" id="free_item_option_same_wrap">
										<input type="radio" name="punch_reward_option" value="same" checked onclick="document.getElementById('punch-reward-custom-wrapper').style.display='none'" class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
										<span class="text-sm font-bold text-slate-700">Reward the same product (Selected in Step 1)</span>
									</label>
									<label class="flex items-center space-x-2 cursor-pointer">
										<input type="radio" name="punch_reward_option" value="custom" onclick="document.getElementById('punch-reward-custom-wrapper').style.display='block'" class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
										<span class="text-sm font-bold text-slate-700" id="free_item_custom_label">Reward a different free item</span>
									</label>
								</div>

								<div id="punch-reward-custom-wrapper" style="display: none;">
									<label class="block text-sm font-bold text-slate-700 mb-2">Select the free item <span class="text-red-500">*</span></label>
									<select class="wc-product-search" style="width: 100%;" id="wizard_punch_reward_product" data-placeholder="Search for a product..." data-action="woocommerce_json_search_products_and_variations"></select>
								</div>
							</div>
						</div>

						<!-- Step 3 Content -->
						<div class="o100-form-step" id="step-content-3">
							<h3 class="text-2xl font-bold text-slate-900 mb-2">Conditional Rules</h3>
							<p class="text-slate-500 mb-8">Restrict who can receive this reward based on specific criteria.</p>

							<div class="bg-slate-50 p-6 rounded-xl border border-slate-200">
								<div class="flex items-center justify-between mb-4">
									<div>
										<label class="block text-base font-bold text-slate-900">Conditions <span class="text-slate-400 text-sm font-normal ml-2">(Optional)</span></label>
									</div>
									<button type="button" class="bg-indigo-100 text-indigo-700 hover:bg-indigo-200 px-4 py-2 rounded-lg text-sm font-bold flex items-center transition-colors" onclick="o100Wizard.addCondition()">
										<svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
										Add Conditions
									</button>
								</div>
								
								<div id="conditions-container" class="space-y-3 mb-2"></div>

								<!-- Placeholder for empty condition state -->
								<div id="empty-conditions-placeholder" class="text-center py-6 border-2 border-dashed border-slate-300 rounded-lg bg-white">
									<p class="text-sm text-slate-500">No conditions set. Reward applies to all active users.</p>
								</div>
							</div>
						</div>

						<!-- Step 4 Content -->
						<div class="o100-form-step" id="step-content-4">
							<h3 class="text-2xl font-bold text-slate-900 mb-2">Notification Settings</h3>
							<p class="text-slate-500 mb-8">Configure the message customers see in their loyalty panel and email notifications.</p>
							
							<div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
								<div class="flex items-center justify-between mb-4">
									<h4 class="font-bold text-slate-900" id="wizard_message_title">Customer Notification <span class="text-red-500">*</span></h4>
									<label class="relative inline-flex items-center cursor-pointer">
										<input type="checkbox" id="wizard_notification_toggle" checked class="sr-only peer" onchange="document.getElementById('wizard_notification_body').style.display = this.checked ? 'block' : 'none'">
										<div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500"></div>
									</label>
								</div>
								
								<div id="wizard_notification_body" class="space-y-4">
									<div>
										<p class="text-xs text-slate-500 mb-2">This message will appear in the customer's loyalty panel and email notification when this campaign triggers.</p>
										<textarea rows="6" id="wizard_frontend_message" class="w-full"></textarea>
										<p class="text-xs text-slate-400 mt-2">Available variables: <code>{o100_points}</code> <code>{o100_points_label}</code> <code>{o100_customer_name}</code> <code>{o100_product_points}</code> <code>{o100_cart_points}</code></p>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="pt-6 mt-6 border-t border-slate-100 flex justify-between items-center">
						<button id="btn-back" class="px-6 py-2.5 rounded-xl text-slate-600 font-medium hover:bg-slate-100 transition-colors invisible" onclick="o100Wizard.prev()">
							Back
						</button>
						<button id="btn-next" class="px-8 py-2.5 rounded-xl bg-indigo-500 text-white font-bold hover:bg-indigo-600 transition-colors shadow-sm" onclick="o100Wizard.next()">
							Continue to Reward
						</button>
					</div>

				</div>
			</div>
		</div>

		<script>
			// Proxy Settings Management
			const o100Proxy = {
				switchTab: function(tabId, linkElem) {
					// Hide all panels
					document.querySelectorAll('.o100-tab-panel').forEach(panel => {
						panel.classList.remove('block');
						panel.classList.add('hidden');
					});
					// Show target panel
					document.getElementById('tab-' + tabId).classList.remove('hidden');
					document.getElementById('tab-' + tabId).classList.add('block');
					
					// Update active states on nav links
					document.querySelectorAll('.o100-tab-link').forEach(link => {
						link.classList.remove('border-indigo-500', 'text-indigo-600');
						link.classList.add('border-transparent', 'text-slate-500');
					});
					linkElem.classList.remove('border-transparent', 'text-slate-500');
					linkElem.classList.add('border-indigo-500', 'text-indigo-600');
					
					// Show/Hide Save button
					if (tabId === 'growth_engine') {
						document.getElementById('o100-save-proxy-settings').classList.add('hidden');
					} else {
						document.getElementById('o100-save-proxy-settings').classList.remove('hidden');
					}
				},
				
				insertTag: function(targetId, tag) {
					const field = document.getElementById(targetId);
					if (!field) return;
					const start = field.selectionStart;
					const end = field.selectionEnd;
					field.value = field.value.substring(0, start) + tag + field.value.substring(end);
					field.focus();
					field.selectionStart = field.selectionEnd = start + tag.length;
					if(typeof o100Proxy.updateLivePreview === 'function') o100Proxy.updateLivePreview();
				},
				
				openMediaUploader: function(targetId) {
					var frame = wp.media({
						title: 'Select Image',
						button: { text: 'Use this image' },
						multiple: false
					});
					frame.on('select', function() {
						var attachment = frame.state().get('selection').first().toJSON();
						var targetEl = document.getElementById(targetId);
						if (targetEl) {
							targetEl.value = attachment.url;
							// Trigger input event to update preview
							targetEl.dispatchEvent(new Event('input', { bubbles: true }));
						}
					});
					frame.open();
				},
				
				loadSettings: function() {
					jQuery.post(ajaxurl, {
						action: 'o100_loyalty_get_proxy_settings',
						nonce: '<?php echo wp_create_nonce("o100_loyalty_proxy"); ?>'
					}, function(response) {
						if(response.success && response.data) {
							const d = response.data;
							
							Object.keys(d).forEach(key => {
								const el = document.getElementById(key);
								if (el) {
									if (el.type === 'checkbox') el.checked = (d[key] === 'yes');
									else el.value = d[key];
								}
							});
							
							if(d.fw_primary_color && document.getElementById('fw_primary_color_hex')) {
								document.getElementById('fw_primary_color_hex').value = d.fw_primary_color;
							}
							
							if (d.rt_social_icons) {
								const socials = d.rt_social_icons.split(',');
								document.querySelectorAll('.rt_social_icon').forEach(el => {
									el.checked = socials.includes(el.value);
								});
							}
						}
						
						if (typeof o100Proxy.updateLivePreview === 'function') {
							setTimeout(o100Proxy.updateLivePreview, 100);
						}
					});
				},
				
				previewIsMember: false,
				rtPreviewMode: 'friend', // 'friend' or 'advocate'
				
				togglePreviewMode: function(isMember) {
					this.previewIsMember = isMember;
					
					const gBtn = document.getElementById('preview-guest-btn');
					const mBtn = document.getElementById('preview-member-btn');
					
					if (isMember) {
						mBtn.className = 'px-3 py-1 text-xs font-bold rounded shadow-sm bg-white text-slate-800';
						gBtn.className = 'px-3 py-1 text-xs font-bold rounded text-slate-500 hover:text-slate-700';
					} else {
						gBtn.className = 'px-3 py-1 text-xs font-bold rounded shadow-sm bg-white text-slate-800';
						mBtn.className = 'px-3 py-1 text-xs font-bold rounded text-slate-500 hover:text-slate-700';
					}
					
					this.updateLivePreview();
				},
				
				toggleRtPreview: function(mode) {
					this.rtPreviewMode = mode;
					const advBtn = document.getElementById('rt-preview-advocate-btn');
					const friBtn = document.getElementById('rt-preview-friend-btn');
					const advModal = document.getElementById('rt-preview-advocate-modal');
					const friModal = document.getElementById('rt-preview-friend-modal');
					
					if (mode === 'advocate') {
						advBtn.className = 'px-3 py-1 text-xs font-bold rounded shadow-sm bg-white text-slate-800';
						friBtn.className = 'px-3 py-1 text-xs font-bold rounded text-slate-500 hover:text-slate-700';
						advModal.classList.remove('hidden');
						friModal.classList.add('hidden');
					} else {
						friBtn.className = 'px-3 py-1 text-xs font-bold rounded shadow-sm bg-white text-slate-800';
						advBtn.className = 'px-3 py-1 text-xs font-bold rounded text-slate-500 hover:text-slate-700';
						friModal.classList.remove('hidden');
						advModal.classList.add('hidden');
					}
					this.updateLivePreview();
				},
				
				updateLivePreview: function() {
					// 1. Update Frontend Widget Preview
					if (typeof window.O100FrontendLauncher !== 'undefined') {
						const config = o100Proxy.buildLauncherConfig();
						
						// Inject mock data for secondary/tertiary panels
						window.O100FrontendLauncher.mockData = {
							'wll_get_guest_earn_points': [
								{ action_type: 'signup', title: 'Sign Up', sub_title: 'Join now', points: '50' },
								{ action_type: 'point_for_purchase', title: 'Make a purchase', sub_title: '1 Point per $1 spent', points: '' }
							],
							'wll_get_member_earn_points': [
								{ action_type: 'point_for_purchase', title: 'Make a purchase', sub_title: '1 Point per $1 spent', points: '' },
								{ action_type: 'birthday', title: 'Celebrate a birthday', sub_title: 'Earn points on your birthday', points: '100', button_text: 'Save Date' }
							],
							'wll_get_guest_redeem_rewards': [
								{ name: '$5 Off Discount', cost: '500' },
								{ name: '10% Off Coupon', cost: '1000' }
							],
							'wll_get_member_redeem_rewards': [
								{ name: '$5 Off Discount', cost: '500', button_text: 'Redeem' },
								{ name: '10% Off Coupon', cost: '1000', button_text: 'Redeem' }
							],
							'wll_get_reward_opportunity_rewards': [
								{ name: '$5 Off Discount', cost: '500' },
								{ name: '10% Off Coupon', cost: '1000' }
							]
						};
						
						if (typeof window.O100FrontendLauncher.setState === 'function') {
							window.O100FrontendLauncher.setState('isOpen', true);
						}
						window.O100FrontendLauncher.render(config);
					}

					// 2. Update Referral Popup Mockup Preview
					const s = o100Proxy.getFormSettings();
					
					// Friend Popup
					const rtBg = document.getElementById('rt-preview-friend-modal');
					if (rtBg) {
						rtBg.style.backgroundColor = s.rt_popup_bg_color || '#FFFFFF';
						
						const title = document.getElementById('rt-preview-title');
						if (title) { title.innerText = s.rt_popup_title || 'Welcome!'; title.style.color = s.rt_popup_title_color || '#000000'; }
						
						const sub = document.getElementById('rt-preview-subtitle');
						if (sub) { sub.innerText = s.rt_popup_subtitle || 'Get your reward'; sub.style.color = s.rt_popup_subtitle_color || '#333333'; }
						
						const msg = document.getElementById('rt-preview-message');
						if (msg) {
							let text = s.rt_popup_message || 'Your friend {advocate_name} sent you a gift! Enter your email to claim your {reward_name}.';
							text = text.replace('{advocate_name}', 'Jane Doe').replace('{reward_name}', '$10 Coupon');
							msg.innerText = text;
						}
						
						const btn = document.getElementById('rt-preview-btn');
						if (btn) {
							btn.innerText = s.rt_popup_btn_text || 'Claim Reward';
							btn.style.backgroundColor = s.rt_popup_btn_bg || '#2563eb';
							btn.style.color = s.rt_popup_btn_text_color || '#FFFFFF';
						}
						
						const imgWrap = document.getElementById('rt-preview-img-wrap');
						if (imgWrap) {
							if (s.rt_popup_enable_img === 'yes' && s.rt_popup_img_url) {
								imgWrap.style.display = 'block';
								imgWrap.style.backgroundImage = 'url(' + s.rt_popup_img_url + ')';
							} else {
								imgWrap.style.display = 'none';
							}
						}
					}
					
					// Advocate Email
					const advSubj = document.getElementById('rt-preview-adv-subject');
					if (advSubj) {
						advSubj.innerText = s.rt_advocate_subject || 'You earned a reward!';
					}
					const advCont = document.getElementById('rt-preview-adv-content');
					if (advCont) {
						let advText = s.rt_advocate_content || 'Hi {advocate_name}, good news! Your friend {friend_name} just made a purchase. You have earned: {reward_name}.';
						advText = advText.replace(/{advocate_name}/g, 'Jane Doe').replace(/{friend_name}/g, 'John Smith').replace(/{reward_name}/g, '$10 Coupon');
						advCont.innerText = advText;
					}
				},
				
				initLivePreview: function() {
					// Attach listeners to all inputs in Frontend Widget and Referral Templates to update preview instantly
					document.querySelectorAll('#tab-frontend_widget input, #tab-frontend_widget select, #tab-frontend_widget textarea, #tab-referral_templates input, #tab-referral_templates select, #tab-referral_templates textarea, #tab-general_settings input, #tab-general_settings select').forEach(el => {
						el.addEventListener('input', o100Proxy.updateLivePreview);
						el.addEventListener('change', o100Proxy.updateLivePreview);
					});
					
					// Sync hex color inputs
					const syncColors = [
						'fw_primary_color', 'fw_secondary_color', 'rt_popup_bg_color'
					];
					syncColors.forEach(id => {
						const colorInput = document.getElementById(id);
						const hexInput = document.getElementById(id + '_hex');
						if(colorInput && hexInput) {
							colorInput.addEventListener('input', function() { hexInput.value = this.value; o100Proxy.updateLivePreview(); });
							hexInput.addEventListener('input', function() { colorInput.value = this.value; o100Proxy.updateLivePreview(); });
						}
					});
					
					o100Proxy.updateLivePreview();
				},
				
				saveSettings: function() {
					const btn = document.getElementById('o100-save-proxy-settings');
					btn.innerHTML = 'Saving...';
					btn.disabled = true;
					
					const settingsObj = o100Proxy.getFormSettings();
					
					const data = {
						action: 'o100_loyalty_save_proxy_settings',
						nonce: '<?php echo wp_create_nonce("o100_loyalty_proxy"); ?>',
						settings: settingsObj
					};
					
					jQuery.post(ajaxurl, data, function(response) {
						btn.innerHTML = 'Save Settings';
						btn.disabled = false;
						
						if(response.success) {
							o100Wizard.showToast('Settings saved successfully!');
						} else {
							o100Wizard.showToast('Failed to save settings.', true);
						}
					});
				},
				
				getFormSettings: function() {
					const s = {};
					const fields = [
						'fw_primary_color', 'fw_secondary_color', 'fw_banner_bg', 'fw_banner_text', 'fw_buttons_bg', 'fw_buttons_text', 'fw_launcher_bg', 'fw_launcher_text_color', 'fw_links_color', 'fw_icons_color', 'fw_branding_show',
						'fw_logo_url', 'fw_btn_style', 'fw_launcher_text', 'fw_font_family', 'fw_icon', 'fw_custom_icon_url', 'fw_placement', 'fw_side_spacing', 'fw_bottom_spacing', 'fw_visibility',
						'fw_guest_welcome_title', 'fw_guest_welcome_desc', 'fw_guest_btn_text', 'fw_guest_btn_url', 'fw_guest_have_account', 'fw_guest_signin_text', 'fw_guest_signin_url',
						'fw_member_welcome_title', 'fw_member_points_label', 'fw_card_earn_title', 'fw_card_redeem_title',
						'rt_widget_visibility', 'rt_widget_title', 'rt_widget_desc',
						'rt_advocate_subject', 'rt_advocate_content', 'rt_friend_subject', 'rt_friend_content', 
						'rt_popup_enable_img', 'rt_popup_img_url', 'rt_popup_bg_color', 'rt_popup_title', 'rt_popup_title_color', 'rt_popup_subtitle', 'rt_popup_subtitle_color', 'rt_popup_message', 'rt_popup_btn_bg', 'rt_popup_btn_text_color', 'rt_popup_btn_text',
						'gs_points_label_singular', 'gs_points_label_plural', 'gs_rounding_type', 'gs_product_earn_msg', 'gs_cart_earn_msg', 'gs_checkout_earn_msg', 'gs_calculation_basis', 'gs_points_expiry', 'gs_grace_period'
					];
					
					fields.forEach(f => {
						const el = document.getElementById(f);
						if (el) {
							if (el.type === 'checkbox') s[f] = el.checked ? 'yes' : 'no';
							else s[f] = el.value;
						}
					});
					
					// Handle Social Icons
					const socials = [];
					document.querySelectorAll('.rt_social_icon:checked').forEach(el => socials.push(el.value));
					s['rt_social_icons'] = socials.join(',');
					
					return s;
				},
				
				buildLauncherConfig: function() {
					const s = o100Proxy.getFormSettings();
					const isMember = this.previewIsMember || false;
					
					// Build the nested structure expected by o100-frontend-launcher.js
					const socialShareList = [];
					if (s.rt_social_icons) {
						s.rt_social_icons.split(',').forEach(soc => {
							socialShareList.push({ action_type: soc + '_share', url: '#' });
						});
					}
					
					return {
						is_member: isMember,
						available_point: 1250, // Mock points
						design: {
							colors: {
								theme: { 
									primary: s.fw_primary_color || '<?php echo esc_js($brand_primary); ?>',
									text: 'white'
								},
								launcher: { 
									background: s.fw_launcher_bg || s.fw_primary_color || '<?php echo esc_js($brand_primary); ?>', 
									text: s.fw_launcher_text_color || '#FFFFFF' 
								},
								buttons: {
									background: s.fw_buttons_bg || s.fw_primary_color || '<?php echo esc_js($brand_primary); ?>',
									text: s.fw_buttons_text || '#FFFFFF'
								},
								banner: {
									background: s.fw_banner_bg || '#F5F5F5',
									text: s.fw_banner_text || '#333333'
								},
								links: s.fw_links_color || '<?php echo esc_js($brand_primary); ?>',
								icons: s.fw_icons_color || '<?php echo esc_js($brand_primary); ?>'
							},
							logo: {
								is_show: s.fw_logo_url ? 'show' : 'hide',
								image: s.fw_logo_url || ''
							},
							branding: {
								is_show: (s.fw_branding_show === 'yes') ? 'show' : 'hide'
							}
						},
						launcher: {
							font_family: s.fw_font_family || 'inherit',
							appearance: {
								selected: s.fw_btn_style || 'icon_with_text',
								text: s.fw_launcher_text || 'Rewards',
								icon: {
									selected: (s.fw_icon === 'custom') ? 'image' : 'default',
									icon: s.fw_icon !== 'custom' ? s.fw_icon : '',
									image: s.fw_icon === 'custom' ? s.fw_custom_icon_url : ''
								}
							},
							placement: {
								position: s.fw_placement || 'right',
								side_spacing: s.fw_side_spacing || 20,
								bottom_spacing: s.fw_bottom_spacing || 20
							},
							view_option: s.fw_visibility || 'mobile_and_desktop'
						},
						content: {
							guest: {
								welcome: {
									texts: {
										title: s.fw_guest_welcome_title || 'Welcome',
										description: s.fw_guest_welcome_desc || '',
										have_account: s.fw_guest_have_account || 'Already have an account?',
										sign_in: s.fw_guest_signin_text || 'Sign in',
										sign_in_url: s.fw_guest_signin_url || '#'
									},
									button: {
										text: s.fw_guest_btn_text || 'Join Now',
										url: s.fw_guest_btn_url || '#'
									}
								},
								points: {
									earn: { title: s.fw_card_earn_title || 'Earn Points' },
									redeem: { title: s.fw_card_redeem_title || 'Redeem Points' }
								},
								referrals: {
									is_referral_action_available: (s.rt_widget_visibility === 'yes'),
									title: s.rt_widget_title || 'Refer and earn',
									description: s.rt_widget_desc || '',
									social_share_list: socialShareList
								}
							},
							member: {
								banner: {
									texts: {
										welcome: s.fw_member_welcome_title || 'Welcome back, {user_name}!',
										points_label: s.fw_member_points_label || 'Points'
									}
								},
								points: {
									earn: { title: s.fw_card_earn_title || 'Earn Points' },
									redeem: { title: s.fw_card_redeem_title || 'Redeem Points' }
								},
								referrals: {
									is_referral_action_available: (s.rt_widget_visibility === 'yes'),
									title: s.rt_widget_title || 'Refer and earn',
									description: s.rt_widget_desc || '',
									social_share_list: socialShareList,
									channels: s.rt_social_icons ? s.rt_social_icons.split(',') : []
								}
							}
						}
					};
				}
			};

			// Vanilla JS State Management for the Wizard
			const o100_search_products_nonce = '<?php echo wp_create_nonce("search-products"); ?>';

			// Global Multi-Select Search Component for Products & Categories
			function o100InitMCS(wrapId, searchType) {
				const wrap = document.getElementById(wrapId);
				if (!wrap) return;
				const hidden = wrap.querySelector('.promo-cond-value') || wrap.querySelector('.o100-cond-val');
				const tags = wrap.querySelector('.o100-mcs-tags');
				const input = wrap.querySelector('.o100-mcs-input');
				const dd = wrap.querySelector('.o100-mcs-dd');
				let selected = {};
				let timer = null;
				let fetchedData = null;
				let isFetching = false;
				let theAjaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : (typeof o100PromoAjaxUrl !== 'undefined' ? o100PromoAjaxUrl : '');

				function renderTags() {
					tags.innerHTML = '';
					Object.entries(selected).forEach(([id, name]) => {
						const t = document.createElement('span');
						t.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800';
						t.innerHTML = name + ' <button type="button" class="ml-0.5 text-blue-500 hover:text-red-600 font-bold" data-id="'+id+'">&times;</button>';
						t.querySelector('button').onclick = function(e) { e.stopPropagation(); delete selected[this.dataset.id]; renderTags(); renderDD(fetchedData); };
						tags.appendChild(t);
					});
					hidden.value = Object.keys(selected).join(',');
				}

				function loadOptions(term = '') {
					if (isFetching) return;
					isFetching = true;
					dd.innerHTML = '<div class="px-3 py-2 text-sm text-slate-400">Loading...</div>';
					dd.classList.remove('hidden');
					
					const fd = new FormData();
					if (searchType === 'products') {
						fd.append('action', 'o100_mcd_search_products');
					} else {
						fd.append('action', 'o100_mcd_search_categories');
					}
					fd.append('term', term);
					const n = (typeof o100Settings!=='undefined') ? o100Settings.adminNonce : ((typeof o100PromoNonce!=='undefined') ? o100PromoNonce : '');
					fd.append('nonce', n);
					
					fetch(theAjaxUrl, {method:'POST', body:fd})
						.then(r => r.json())
						.then(res => {
							isFetching = false;
							if (res.success && res.data) {
								const mapped = {};
								res.data.forEach(item => { mapped[item.id] = item.text; });
								if (term === '') fetchedData = mapped;
								renderDD(term === '' ? fetchedData : mapped);
							} else {
								dd.innerHTML = '<div class="px-3 py-2 text-sm text-slate-400">Error or empty</div>';
							}
						}).catch(() => { isFetching = false; dd.innerHTML = '<div class="px-3 py-2 text-sm text-slate-400">Error</div>'; });
				}

				function renderDD(data) {
					if (!data) return;
					dd.innerHTML = '';
					if (Object.keys(data).length) {
						Object.entries(data).forEach(([id, text]) => {
							const clean = (typeof text === 'string') ? text.replace(/<[^>]*>/g,'') : text;
							const isSelected = !!selected[id];
							const item = document.createElement('label');
							item.className = 'flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 border-b border-slate-100 last:border-0' + (isSelected ? ' bg-blue-50' : '');
							item.innerHTML = '<input type="checkbox" class="rounded" '+(isSelected?'checked':'')+' value="'+id+'"> <span>'+clean+'</span>';
							item.querySelector('input').onchange = function() {
								if (this.checked) { selected[id] = clean; } else { delete selected[id]; }
								renderTags();
								renderDD(data); // Re-render to update background colors
							};
							dd.appendChild(item);
						});
					} else {
						dd.innerHTML = '<div class="px-3 py-2 text-sm text-slate-400">No results</div>';
					}
					dd.classList.remove('hidden');
				}

				input.addEventListener('focus', function() { 
					if (!fetchedData) loadOptions('');
					else { if (!this.value.trim()) renderDD(fetchedData); dd.classList.remove('hidden'); }
				});
				
				input.addEventListener('click', function(e) { 
					e.stopPropagation();
					if (!fetchedData) loadOptions('');
					else { if (!this.value.trim()) renderDD(fetchedData); dd.classList.remove('hidden'); }
				});

				input.addEventListener('input', function() {
					clearTimeout(timer);
					const term = this.value.trim();
					if (term.length === 0) { 
						if (fetchedData) renderDD(fetchedData);
						else loadOptions('');
						return; 
					}
					timer = setTimeout(() => { loadOptions(term); }, 300);
				});

				document.addEventListener('click', function(e) { if (!e.target.closest('#'+wrapId)) dd.classList.add('hidden'); });

				// Expose setValues for refill
				wrap._mcsSetValues = function(ids, names) {
					selected = {};
					if (Array.isArray(ids)) {
						ids.forEach((id, i) => { selected[id] = (names && names[i]) ? names[i] : ('Item #'+id); });
					} else if (typeof ids === 'string' && ids) {
						ids.split(',').forEach(id => { selected[id.trim()] = (names && names[id.trim()]) ? names[id.trim()] : ('Item #'+id.trim()); });
					}
					renderTags();
				};
			}

		const o100Wizard = {
				currentStep: 1,
				totalSteps: 4,
				currentCardType: '',
				selectedRewardType: 'points',
				currentCampaignId: 0,
				
				open: function(type, id) {
					this.currentCardType = type;
					this.currentCampaignId = id || 0;
					
					// Defaults mapping
					const defaults = {
						'birthday': 'Birthday Campaign',
						'points': 'Points Campaign',
						'punch_card': 'Visual Punch Card',
						'referral': 'Referral Program',
						'spend_save': 'Spend & Save'
					};
					
					const nameInput = document.getElementById('wizard_campaign_name');
					if(nameInput) {
						nameInput.value = '';
						nameInput.placeholder = 'e.g. ' + (defaults[type] || 'Campaign Name');
					}
					
					const descInput = document.getElementById('wizard_campaign_desc');
					if(descInput) {
						descInput.value = '';
						const descDefaults = {
							'birthday': 'Celebrate customer birthdays with special rewards.',
							'points': 'Earn points for every purchase and redeem for discounts.',
							'punch_card': 'Buy X items, get a reward!',
							'referral': 'Share the love of our restaurant! Your friend gets $5 off their first order of $50+, and you get rewarded when they finish their meal!',
							'spend_save': 'Spend over a certain amount to unlock a discount.',
						};
						if(!id) descInput.value = descDefaults[type] || '';
					}
					
					// Update Message Step 4 UI
					const messageTitle = document.getElementById('wizard_message_title');
					const messageInput = document.getElementById('wizard_frontend_message');
					if(messageTitle && messageInput) {
						if(type === 'birthday') {
							messageTitle.innerHTML = 'Birthday Greeting <span class="text-red-500">*</span>';
							if(!id) messageInput.value = '🎂 生日快乐！Happy Birthday! \n感谢您一直以来对 Newtown 的支持。我们为您准备了一份 10% OFF 的生日专属礼物，快来点一份您最爱的美食吧！\nEnjoy your special 10% OFF birthday treat! Cheers to a delicious year ahead!';
						} else if(type === 'punch_card') {
							messageTitle.innerHTML = 'Product Page Message <span class="text-red-500">*</span>';
							if(!id) messageInput.value = '☕️ Buy this to earn {o100_product_points} Stamp!';
						} else if(type === 'spend_save') {
							messageTitle.innerHTML = 'Checkout Notification <span class="text-red-500">*</span>';
							if(!id) messageInput.value = '🎉 Spend ${min_amount}+ and unlock a special discount! Add a few more items to qualify.';
						} else if(type === 'referral') {
							messageTitle.innerHTML = 'Referral Success Message <span class="text-red-500">*</span>';
							if(!id) messageInput.value = '🎁 Awesome! Your friend just placed their first order. You earned {o100_points} {o100_points_label} as a thank you!';
						} else if(type === 'points') {
							messageTitle.innerHTML = 'Earning Notification <span class="text-red-500">*</span>';
							if(!id) messageInput.value = '🌟 You earned {o100_points} {o100_points_label} for this purchase! Keep shopping to unlock more rewards.';
						} else {
							messageTitle.innerHTML = 'Customer Notification <span class="text-red-500">*</span>';
							if(!id) messageInput.value = 'You earned {o100_points} {o100_points_label}!';
						}
					}
					
					
					const loader = document.getElementById('o100-wizard-loader');
					if (loader) loader.style.display = 'none';

					if (this.currentCampaignId > 0) {
						if (loader) loader.style.display = 'flex';
						document.getElementById('wizard-title').innerText = 'Edit ' + (defaults[type] || 'Campaign');
						
						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: new URLSearchParams({
								action: 'o100_loyalty_get_campaign',
								nonce: '<?php echo wp_create_nonce("o100_loyalty_proxy"); ?>',
								campaign_id: this.currentCampaignId
							})
						})
						.then(r => r.json())
						.then(res => {
							if (res.success && res.data.campaign) {
								const c = res.data.campaign;
								if (nameInput) nameInput.value = c.name || '';
								if (descInput) descInput.value = c.description || '';
								
								// Data binding
								const pr = c.point_rule || {};

								if (type === 'punch_card' && pr.earn_point) {
									const pInput = document.getElementById('wizard_punch_count');
									if (pInput) pInput.value = pr.earn_point;
								}
								// Punch card: refill participating products from conditions
								if (type === 'punch_card' && c.conditions && Array.isArray(c.conditions)) {
									const prodCond = c.conditions.find(cd => cd.type === 'products');
									if (prodCond && prodCond.options && prodCond.options.value) {
										const productIds = prodCond.options.value;
										// Resolve product names via AJAX
										const fd = new FormData();
										fd.append('action', 'woocommerce_json_search_products_and_variations');
										fd.append('term', '');
										fd.append('include', productIds.join(','));
										fd.append('security', (typeof wc_product_search_params !== 'undefined') ? wc_product_search_params.search_products_nonce : (typeof o100_search_products_nonce !== 'undefined' ? o100_search_products_nonce : ''));
										// Use a simpler approach: just set IDs with placeholder names, then fetch actual names
										const productsMap = {};
										productIds.forEach(id => { productsMap[id] = 'Product #' + id; });
										if (typeof o100PunchProducts !== 'undefined') o100PunchProducts.setProducts(productsMap);
										// Try to resolve real names
										fetch(ajaxurl + '?action=woocommerce_json_search_products_and_variations&term=&include=' + productIds.join(',') + '&security=' + ((typeof wc_product_search_params !== 'undefined') ? wc_product_search_params.search_products_nonce : (typeof o100_search_products_nonce !== 'undefined' ? o100_search_products_nonce : '')))
											.then(r => r.json())
											.then(data => {
												if (data && Object.keys(data).length) {
													const resolved = {};
													productIds.forEach(id => {
														resolved[id] = data[id] ? data[id].replace(/<[^>]*>/g, '') : ('Product #' + id);
													});
													if (typeof o100PunchProducts !== 'undefined') o100PunchProducts.setProducts(resolved);
												}
											}).catch(() => {});
									}
								}
								if (type === 'spend_save') {
									const sInput = document.getElementById('wizard_spend_min_subtotal');
									if (sInput) sInput.value = pr.min_subtotal || 100;
								}
								if (type === 'monthly_reward') {
									const tgtInput = document.getElementById('wizard_monthly_target_audience');
									if (tgtInput) tgtInput.value = pr.target_audience || 'all';
									const expInput = document.getElementById('wizard_monthly_coupon_expiry');
									if (expInput) expInput.value = pr.coupon_expiry || 'end_of_month';
									if (pr.discount_config && pr.discount_config.type === 'existing') {
										const cpnInput = document.getElementById('wizard_monthly_coupon');
										if (cpnInput) cpnInput.value = pr.earn_reward || '';
										this.setRewardType('discount');
									}
								}
								
								// Frontend Message
								if (messageInput) {
									if (type === 'birthday') messageInput.value = pr.birthday_message || '';
									else if (type === 'punch_card' || type === 'spend_save' || type === 'points') messageInput.value = pr.single_product_message || pr.variable_product_message || '';
								}

								// Reward values
								if (type === 'points') {
									const ptsInput = document.getElementById('wizard_reward_points');
									if (ptsInput) ptsInput.value = pr.earn_point || 100;
									const earnPriceInput = document.getElementById('wizard_point_earn_price');
									if (earnPriceInput) earnPriceInput.value = pr.wlr_point_earn_price || 1;
								}

								// Referral data binding
								if (type === 'referral' && pr.advocate) {
									const advTypeEl = document.getElementById('wizard_advocate_type');
									const advAmtEl  = document.getElementById('wizard_advocate_amount');
									const advCpnEl  = document.getElementById('wizard_advocate_coupon');
									const friTypeEl = document.getElementById('wizard_friend_type');
									const friAmtEl  = document.getElementById('wizard_friend_amount');
									const friCpnEl  = document.getElementById('wizard_friend_coupon');

									if (advTypeEl) {
										advTypeEl.value = pr.advocate.campaign_type === 'coupon' ? 'coupon' : 'point';
										if (typeof o100RefToggle === 'function') o100RefToggle('advocate', advTypeEl.value);
									}
									if (advAmtEl) advAmtEl.value = pr.advocate.earn_point || 100;
									if (advCpnEl && pr.advocate.earn_reward) advCpnEl.value = pr.advocate.earn_reward;

									if (pr.friend) {
										if (friTypeEl) {
											friTypeEl.value = pr.friend.campaign_type === 'coupon' ? 'coupon' : 'point';
											if (typeof o100RefToggle === 'function') o100RefToggle('friend', friTypeEl.value);
										}
										if (friAmtEl) friAmtEl.value = pr.friend.earn_point || 10;
										if (friCpnEl && pr.friend.earn_reward) friCpnEl.value = pr.friend.earn_reward;
									}
								}

								// Discount / Free Item bindings (read from inline discount_config)
								if (pr.discount_config && (type === 'birthday' || type === 'spend_save')) {
									const dc = pr.discount_config;
									if (document.getElementById('wizard_discount_type')) document.getElementById('wizard_discount_type').value = dc.type || 'fixed';
									if (document.getElementById('wizard_discount_value')) document.getElementById('wizard_discount_value').value = dc.value || 10;
									if (document.getElementById('wizard_discount_expiry')) document.getElementById('wizard_discount_expiry').value = dc.expiry || 30;
									if (document.getElementById('wizard_discount_limit')) document.getElementById('wizard_discount_limit').value = dc.limit || '';

									if (dc.type === 'percentage' && dc.value == 100 && type === 'spend_save') {
										o100Wizard.setRewardType('free_item');
									} else {
										o100Wizard.setRewardType('discount');
									}
								} else if (pr.earn_reward && (type === 'birthday' || type === 'spend_save')) {
									// Fallback: has a reward but no inline config — set discount mode
									o100Wizard.setRewardType('discount');
								}

								// Render Conditions
								if (c.conditions && Array.isArray(c.conditions) && c.conditions.length > 0) {
									document.getElementById('conditions-container').innerHTML = '';
									c.conditions.forEach(cond => {
										// Exclude punch card products rule
										if (type === 'punch_card' && cond.type === 'products') return;
										
										const rowId = 'cond_' + Date.now() + Math.floor(Math.random() * 1000);
										const row = document.createElement('div');
										row.className = 'flex items-center space-x-3 bg-white p-3 rounded-xl border border-slate-200 shadow-sm o100-condition-row';
										row.id = rowId;
										
										row.innerHTML = `
											<select class="w-1/3 text-sm o100-cond-type" onchange="o100Wizard.updateConditionValueInput('${rowId}')">
												<optgroup label="Cart">
													<option value="cart_subtotal" ${cond.type==='cart_subtotal'?'selected':''}>Cart Subtotal</option>
													<option value="cart_items_count" ${(cond.type==='cart_items_count'||cond.type==='line_item_count')?'selected':''}>Cart Items Count</option>
													<option value="cart_total_qty" ${cond.type==='cart_total_qty'?'selected':''}>Cart Total Quantity</option>
													<option value="cart_coupon" ${cond.type==='cart_coupon'?'selected':''}>Cart Coupon Applied</option>
												</optgroup>
												<optgroup label="Product">
													<option value="products" ${cond.type==='products'?'selected':''}>Products</option>
													<option value="product_cat" ${cond.type==='product_cat'?'selected':''}>Product Category</option>
													<option value="product_on_sale" ${cond.type==='product_on_sale'?'selected':''}>Product On Sale</option>
												</optgroup>
												<optgroup label="Customer">
													<option value="user_role" ${cond.type==='user_role'?'selected':''}>User Role</option>
													<option value="user_logged_in" ${cond.type==='user_logged_in'?'selected':''}>User Logged In</option>
													<option value="first_order" ${cond.type==='first_order'?'selected':''}>First Order</option>
												</optgroup>
												<optgroup label="Purchase History">
													<option value="prev_orders_count" ${(cond.type==='prev_orders_count'||cond.type==='purchase_history_order_count')?'selected':''}>Previous Orders Count</option>
													<option value="total_spent" ${(cond.type==='total_spent'||cond.type==='total_amount_spent')?'selected':''}>Total Spent Amount</option>
												</optgroup>
												<optgroup label="Order">
													<option value="order_method" ${cond.type==='order_method'?'selected':''}>Order Method</option>
													<option value="payment_method" ${cond.type==='payment_method'?'selected':''}>Payment Method</option>
													<option value="location_branch" ${cond.type==='location_branch'?'selected':''}>Location/Branch</option>
												</optgroup>
												<optgroup label="Schedule">
													<option value="time_of_day" ${cond.type==='time_of_day'?'selected':''}>Time of Day</option>
													<option value="day_of_week" ${cond.type==='day_of_week'?'selected':''}>Day of Week</option>
												</optgroup>
												<optgroup label="Shipping / Delivery">
													<option value="delivery_distance" ${cond.type==='delivery_distance'?'selected':''}>Delivery Distance</option>
													<option value="shipping_zip" ${cond.type==='shipping_zip'?'selected':''}>Shipping Zip Code</option>
												</optgroup>
											</select>
											<select class="w-1/4 text-sm o100-cond-op"></select>
											<div class="w-1/3 o100-cond-val-container"></div>
											<button type="button" class="text-slate-400 hover:text-red-500 p-1 transition-colors" onclick="this.parentElement.remove(); if(document.getElementById('conditions-container').children.length === 0) { document.getElementById('empty-conditions-placeholder').style.display = 'block'; }">
												<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
											</button>
										`;
										document.getElementById('conditions-container').appendChild(row);
										document.getElementById('empty-conditions-placeholder').style.display = 'none';
										
										o100Wizard.updateConditionValueInput(rowId);
										
										if (cond.options && cond.options.operator) {
											row.querySelector('.o100-cond-op').value = cond.options.operator;
										} else if (cond.operator) {
											row.querySelector('.o100-cond-op').value = cond.operator;
										}
										
										if (cond.options && cond.options.value) {
											const v = cond.options.value;
											row.querySelector('.o100-cond-val').value = Array.isArray(v) ? v.join(',') : v;
										}
									});
								} else {
									document.getElementById('conditions-container').innerHTML = '';
									document.getElementById('empty-conditions-placeholder').style.display = 'block';
								}
							}
						})
						.catch(e => console.error("Error fetching campaign data", e))
						.finally(() => {
							if (loader) loader.style.display = 'none';
						});

					} else {
						document.getElementById('wizard-title').innerText = 'Create ' + (defaults[type] || 'Campaign');
					}
					
					// Toggle specific settings panels in Step 1
					document.getElementById('birthday-specific-settings').classList.add('hidden');
					document.getElementById('points-specific-settings').classList.add('hidden');
					document.getElementById('punch_card-specific-settings').classList.add('hidden');
					document.getElementById('spend_save-specific-settings').classList.add('hidden');
					document.getElementById('monthly_reward-specific-settings').classList.add('hidden');
					if (document.getElementById(`${type}-specific-settings`)) {
						document.getElementById(`${type}-specific-settings`).classList.remove('hidden');
					}

					// Update Reward types logic
					document.querySelectorAll('.o100-reward-opt').forEach(opt => opt.classList.remove('hidden'));
					var rtSel = document.getElementById('reward-type-selector'); if (rtSel) rtSel.style.display = '';
					if (type === 'birthday' || type === 'monthly_reward') {
						this.setRewardType('discount');
					} else if (type === 'points') {
						this.setRewardType('points');
						document.getElementById('reward-opt-discount').classList.add('hidden');
						document.getElementById('reward-opt-free_item').classList.add('hidden');
					} else if (type === 'punch_card') {
						this.setRewardType('free_item');
						document.getElementById('reward-opt-points').classList.add('hidden');
						document.getElementById('reward-opt-discount').classList.add('hidden');
					} else if (type === 'referral') {
						// Referral: hide generic cards, show referral panel directly
						document.querySelectorAll('.o100-reward-opt').forEach(opt => opt.classList.add('hidden'));
						var rtSel = document.getElementById('reward-type-selector'); if (rtSel) rtSel.style.display = 'none';
						var refPanel = document.getElementById('reward-panel-referral'); if (refPanel) refPanel.classList.remove('hidden');
						document.querySelectorAll('.o100-reward-opt').forEach(opt => opt.classList.add('hidden'));
					} else if (type === 'spend_save') {
						this.setRewardType('discount');
						document.getElementById('reward-opt-points').classList.add('hidden');
						document.getElementById('reward-opt-free_item').classList.add('hidden');
					}

					// Fetch if Global Conversion Rate exists
					fetch(ajaxurl + '?action=o100_loyalty_check_conversion_rate&nonce=<?php echo wp_create_nonce("o100_loyalty_proxy"); ?>')
					.then(response => response.json())
					.then(data => {
						const notice = document.getElementById('points-conversion-notice');
						if (data.success && data.data.has_rule) {
							// Exists: show subtle text
							notice.className = 'mt-2 text-xs text-slate-500';
							notice.innerHTML = `Current Value: <b>${data.data.points} Points = $${data.data.value} Off</b>. <a href="#" class="text-indigo-500 underline ml-1" onclick="this.parentElement.innerHTML='<div class=\\'flex items-center space-x-2 mt-2\\'><input type=\\'number\\' id=\\'wizard_conversion_points\\' value=\\'${data.data.points}\\' class=\\'w-24 text-sm py-1 px-2\\' /><span class=\\'text-sm font-bold text-slate-600\\'>Points = $</span><input type=\\'number\\' id=\\'wizard_conversion_value\\' value=\\'${data.data.value}\\' class=\\'w-20 text-sm py-1 px-2\\' /><span class=\\'text-sm font-bold text-slate-600\\'>Off</span></div>';">Edit</a>`;
						}
					});
					
					fetch(ajaxurl + '?action=o100_loyalty_check_birthday_settings&nonce=<?php echo wp_create_nonce("o100_loyalty_proxy"); ?>')
					.then(res => res.json())
					.then(data => {
						if (data.success && data.data) {
							const checkbox = document.getElementById('wizard_allow_birthday_edit');
							if(checkbox) checkbox.checked = data.data.allow_edit === 'yes';
						}
					});

					// Initialize Select2 if available
					if (jQuery && jQuery.fn.select2) {
						setTimeout(() => {
							jQuery('.wc-product-search').filter(':not(.select2-hidden-accessible)').select2({
								minimumInputLength: 3,
								ajax: {
									url: ajaxurl,
									dataType: 'json',
									delay: 250,
									data: function (params) {
										return {
											term: params.term,
											action: 'woocommerce_json_search_products_and_variations',
											security: (typeof wc_product_search_params !== 'undefined' && wc_product_search_params) ? wc_product_search_params.search_products_nonce : o100_search_products_nonce
										};
									},
									processResults: function (data) {
										var terms = [];
										if (data) {
											jQuery.each(data, function (id, text) {
												terms.push({ id: id, text: text });
											});
										}
										return { results: terms };
									},
									cache: true
								}
							});
						}, 100);
					}

					// Initialize punch products search component using MCS
					o100InitMCS('punch_products_wrapper', 'products');
					window.o100PunchProducts = {
						getSelected: function() { return (document.getElementById('wizard_punch_products_val').value || '').split(',').filter(Boolean); },
						setProducts: function(products) {
							const wrap = document.getElementById('punch_products_wrapper');
							if (wrap && wrap._mcsSetValues) {
								if (products && typeof products === 'object') {
									const ids = Object.keys(products).join(',');
									wrap._mcsSetValues(ids, products);
								}
							}
						},
						clear: function() {
							const wrap = document.getElementById('punch_products_wrapper');
							if (wrap && wrap._mcsSetValues) wrap._mcsSetValues('');
						}
					};

					// Reset and open
					this.goToStep(1);
					document.getElementById('o100-wizard').classList.add('is-open');
					document.body.style.overflow = 'hidden'; // Prevent background scroll
				},

				setRewardType: function(type) {
					this.selectedRewardType = type;
					
					// Reset all UI states
					document.querySelectorAll('.o100-reward-opt').forEach(el => {
						el.classList.remove('border-2', 'border-indigo-500', 'bg-indigo-50');
						el.classList.add('border', 'border-slate-200');
						el.querySelector('.o100-reward-opt-check').classList.add('hidden');
						el.querySelector('.font-bold').classList.remove('text-indigo-800');
						el.querySelector('.font-bold').classList.add('text-slate-700');
					});
					document.querySelectorAll('[id^="reward-panel-"]').forEach(el => el.classList.add('hidden'));
					
					// Active State
					const activeOpt = document.getElementById('reward-opt-' + type);
					if (activeOpt) {
						activeOpt.classList.remove('border', 'border-slate-200');
						activeOpt.classList.add('border-2', 'border-indigo-500', 'bg-indigo-50');
						activeOpt.querySelector('.o100-reward-opt-check').classList.remove('hidden');
						activeOpt.querySelector('.o100-reward-opt-check').classList.add('flex');
						activeOpt.querySelector('.font-bold').classList.remove('text-slate-700');
						activeOpt.querySelector('.font-bold').classList.add('text-indigo-800');
					}
					
					// Show correct panel
					const activePanel = document.getElementById('reward-panel-' + type);
					if (activePanel) {
						activePanel.classList.remove('hidden');
					}
					
					// Adjust free item options based on campaign type
					if (type === 'free_item') {
						const sameOptWrap = document.getElementById('free_item_option_same_wrap');
						const radioCustom = document.querySelector('input[name="punch_reward_option"][value="custom"]');
						const customLabel = document.getElementById('free_item_custom_label');
						
						if (this.currentCardType !== 'punch_card') {
							if (sameOptWrap) sameOptWrap.style.display = 'none';
							if (customLabel) customLabel.innerText = 'Select the free item';
							if (radioCustom) {
								radioCustom.checked = true;
								document.getElementById('punch-reward-custom-wrapper').style.display = 'block';
							}
						} else {
							if (sameOptWrap) sameOptWrap.style.display = 'flex';
							if (customLabel) customLabel.innerText = 'Reward a different free item';
						}
					}
				},
				
				close: function() {
					document.getElementById('o100-wizard').classList.remove('is-open');
					document.body.style.overflow = '';
				},
				
				next: function() {
					if (this.currentStep < this.totalSteps) {
						this.goToStep(this.currentStep + 1);
					} else {
						// Final step - Save action
						this.save();
					}
				},
				
				prev: function() {
					if (this.currentStep > 1) {
						this.goToStep(this.currentStep - 1);
					}
				},
				
				goToStep: function(step) {
					this.currentStep = step;
					
					// Update Stepper Navigation UI
					for(let i=1; i<=this.totalSteps; i++) {
						const navItem = document.getElementById('step-nav-' + i);
						if (!navItem) continue;
						navItem.className = 'o100-step-item'; // reset
						if (i < step) {
							navItem.classList.add('is-completed');
						} else if (i === step) {
							navItem.classList.add('is-active');
						} else {
							navItem.classList.add('is-pending');
						}
					}
					
					// Update Form Content Visibility
					for(let i=1; i<=this.totalSteps; i++) {
						const contentItem = document.getElementById('step-content-' + i);
						if (!contentItem) continue;
						if (i === step) {
							contentItem.classList.add('is-active');
						} else {
							contentItem.classList.remove('is-active');
						}
					}
					
					// Update Buttons
					const btnBack = document.getElementById('btn-back');
					const btnNext = document.getElementById('btn-next');
					if (!btnBack || !btnNext) return;
					
					btnBack.style.visibility = step > 1 ? 'visible' : 'hidden';
					
					if (step === this.totalSteps) {
						btnNext.innerText = 'Save & Activate';
						btnNext.classList.remove('bg-indigo-500', 'hover:bg-indigo-600');
						btnNext.classList.add('bg-slate-900', 'hover:bg-slate-800');
					} else {
						const stepNames = { 1: 'Continue to Reward', 2: 'Continue to Conditions', 3: 'Continue to Notification' };
						btnNext.innerText = stepNames[step] || 'Continue';
						btnNext.classList.remove('bg-slate-900', 'hover:bg-slate-800');
						btnNext.classList.add('bg-indigo-500', 'hover:bg-indigo-600');
					}
				},

				addCondition: function() {
					document.getElementById('empty-conditions-placeholder').style.display = 'none';
					const container = document.getElementById('conditions-container');
					const rowId = 'cond_' + Date.now();
					const row = document.createElement('div');
					row.className = 'flex items-center space-x-3 bg-white p-3 rounded-xl border border-slate-200 shadow-sm o100-condition-row';
					row.id = rowId;
					
					row.innerHTML = `
						<select class="w-1/3 text-sm o100-cond-type" onchange="o100Wizard.updateConditionValueInput('${rowId}')">
							<optgroup label="Cart">
								<option value="cart_subtotal">Cart Subtotal</option>
								<option value="cart_items_count">Cart Items Count</option>
								<option value="cart_total_qty">Cart Total Quantity</option>
								<option value="cart_coupon">Cart Coupon Applied</option>
							</optgroup>
							<optgroup label="Product">
								<option value="products">Products</option>
								<option value="product_cat">Product Category</option>
								<option value="product_on_sale">Product On Sale</option>
							</optgroup>
							<optgroup label="Customer">
								<option value="user_role">User Role</option>
								<option value="user_logged_in">User Logged In</option>
								<option value="first_order">First Order</option>
							</optgroup>
							<optgroup label="Purchase History">
								<option value="prev_orders_count">Previous Orders Count</option>
								<option value="total_spent">Total Spent Amount</option>
							</optgroup>
							<optgroup label="Order">
								<option value="order_method">Order Method</option>
								<option value="payment_method">Payment Method</option>
								<option value="location_branch">Location/Branch</option>
							</optgroup>
							<optgroup label="Schedule">
								<option value="time_of_day">Time of Day</option>
								<option value="day_of_week">Day of Week</option>
							</optgroup>
							<optgroup label="Shipping / Delivery">
								<option value="delivery_distance">Delivery Distance</option>
								<option value="shipping_zip">Shipping Zip Code</option>
							</optgroup>
						</select>
						<select class="w-1/4 text-sm o100-cond-op"></select>
						<div class="w-1/3 o100-cond-val-container"></div>
						<button type="button" class="text-slate-400 hover:text-red-500 p-1 transition-colors" onclick="this.parentElement.remove(); if(document.getElementById('conditions-container').children.length === 0) { document.getElementById('empty-conditions-placeholder').style.display = 'block'; }">
							<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
						</button>
					`;
					container.appendChild(row);
					this.updateConditionValueInput(rowId);
				},

				updateConditionValueInput: function(rowId) {
					const row = document.getElementById(rowId);
					if (!row) return;
					
					const type = row.querySelector('.o100-cond-type').value;
					const opSelect = row.querySelector('.o100-cond-op');
					const valContainer = row.querySelector('.o100-cond-val-container');

					const schema = {
						'cart_subtotal':       { ops: ['>', '>=', '<', '<=', '=='], input: 'number', ph: 'e.g. 50' },
						'cart_items_count':    { ops: ['>', '>=', '<', '<=', '=='], input: 'number', ph: 'e.g. 3' },
						'cart_total_qty':     { ops: ['>', '>=', '<', '<=', '=='], input: 'number', ph: 'e.g. 5' },
						'cart_coupon':        { ops: ['is', 'is_not'], input: 'text', ph: 'Coupon code' },
						'products':          { ops: ['in', 'not_in'], input: 'search_products' },
						'product_cat':       { ops: ['in', 'not_in'], input: 'search_categories' },
						'product_on_sale':   { ops: ['yes', 'no'], input: 'none' },
						'user_role':         { ops: ['in', 'not_in'], input: 'select_role' },
						'user_logged_in':    { ops: ['yes', 'no'], input: 'none' },
						'first_order':       { ops: ['yes', 'no'], input: 'none' },
						'prev_orders_count': { ops: ['>', '>=', '<', '<=', '=='], input: 'number', ph: 'e.g. 5' },
						'total_spent':       { ops: ['>', '>=', '<', '<=', '=='], input: 'number', ph: 'e.g. 100' },
						'order_method':      { ops: ['is', 'is_not'], input: 'select_method' },
						'payment_method':    { ops: ['in', 'not_in'], input: 'text', ph: 'Gateway IDs (comma sep)' },
						'location_branch':   { ops: ['in', 'not_in'], input: 'text', ph: 'Branch IDs (comma sep)' },
						'time_of_day':       { ops: ['between'], input: 'text', ph: 'e.g. 14:00-17:00' },
						'day_of_week':       { ops: ['in', 'not_in'], input: 'text', ph: '0=Sun,1=Mon..6=Sat' },
						'delivery_distance': { ops: ['>', '>=', '<', '<=', '=='], input: 'number', ph: 'km' },
						'shipping_zip':      { ops: ['in', 'not_in'], input: 'text', ph: 'Zip codes (comma sep)' }
					};

					const opLabels = {
						'>': 'Greater than', '>=': 'Greater or equal', '<': 'Less than', '<=': 'Less or equal', '==': 'Equal to',
						'is': 'Is', 'is_not': 'Is Not', 'in': 'Is in list', 'not_in': 'Not in list',
						'yes': 'Yes', 'no': 'No', 'between': 'Is between'
					};

					const def = schema[type];
					if (!def) return;

					// Operators
					opSelect.innerHTML = def.ops.map(o => `<option value="${o}">${opLabels[o] || o}</option>`).join('');

					// Value input
					if (def.input === 'none') {
						valContainer.innerHTML = `<input type="hidden" class="o100-cond-val" value="1">`;
					} else if (def.input === 'select_role') {
						valContainer.innerHTML = `<select class="w-full text-sm o100-cond-val">
							<option value="customer">Customer</option>
							<option value="subscriber">Subscriber</option>
							<option value="administrator">Administrator</option>
						</select>`;
					} else if (def.input === 'select_method') {
						valContainer.innerHTML = `<select class="w-full text-sm o100-cond-val">
							<option value="delivery">Delivery</option>
							<option value="pickup">Pickup</option>
						</select>`;
					} else if (def.input === 'search_products' || def.input === 'search_categories') {
						const uid = 'mcs_' + Date.now() + '_' + Math.random().toString(36).substr(2,4);
						const isProducts = def.input === 'search_products';
						const placeholder = isProducts ? 'Search products...' : 'Search categories...';
						valContainer.innerHTML = `<div class="o100-mcs-wrap relative" id="${uid}"><input type="hidden" class="o100-cond-val" value=""><div class="o100-mcs-tags flex flex-wrap gap-1 mb-1"></div><input type="text" class="o100-mcs-input w-full text-sm border border-slate-300 rounded px-2 py-1" placeholder="${placeholder}" autocomplete="off"><div class="o100-mcs-dd absolute left-0 right-0 top-full bg-white border border-slate-200 rounded-lg shadow-lg z-50 max-h-40 overflow-y-auto hidden"></div></div>`;
						o100InitMCS(uid, isProducts ? 'products' : 'categories');
					} else {
						valContainer.innerHTML = `<input type="${def.input === 'number' ? 'number' : 'text'}" class="w-full text-sm o100-cond-val" placeholder="${def.ph || 'Value'}">`;
					}
								},

				save: function() {
					const btnNext = document.getElementById('btn-next');
					const originalText = btnNext.innerText;
					btnNext.innerText = 'Saving...';
					btnNext.disabled = true;

					const allowBirthdayEdit = document.getElementById('wizard_allow_birthday_edit');
						// Gather inputs for punch card specific options
						const punchProductsVal = document.getElementById('wizard_punch_products_val');
						const punchProducts = punchProductsVal ? punchProductsVal.value : '';
						
						const punchRewardSel = document.getElementById('wizard_punch_reward_product');
						const punchReward = (punchRewardSel && jQuery) ? jQuery(punchRewardSel).val() : '';

						const payload = {
							action: 'o100_save_loyalty_wizard',
							nonce: '<?php echo wp_create_nonce("o100_loyalty_proxy"); ?>',
							campaign_id: this.currentCampaignId,
							card_type: this.currentCardType || 'birthday',
							campaign_name: document.getElementById('wizard_campaign_name') ? (document.getElementById('wizard_campaign_name').value || document.getElementById('wizard_campaign_name').placeholder.replace('e.g. ', '')) : '',
							campaign_desc: document.getElementById('wizard_campaign_desc') ? document.getElementById('wizard_campaign_desc').value : '',
							birthday_message: document.getElementById('wizard_birthday_message') ? document.getElementById('wizard_birthday_message').value : '',
							reward_type: this.selectedRewardType,
							reward_points: document.getElementById('wizard_reward_points') ? document.getElementById('wizard_reward_points').value : 0,
							point_earn_price: document.getElementById('wizard_point_earn_price') ? document.getElementById('wizard_point_earn_price').value : 1,
							punch_count: document.getElementById('wizard_punch_count') ? document.getElementById('wizard_punch_count').value : 5,
							punch_products: punchProducts,
							punch_reward_product: punchReward,
							advocate_type: document.getElementById('wizard_advocate_type') ? document.getElementById('wizard_advocate_type').value : 'point',
							advocate_amount: document.getElementById('wizard_advocate_amount') ? document.getElementById('wizard_advocate_amount').value : 100,
							friend_type: document.getElementById('wizard_friend_type') ? document.getElementById('wizard_friend_type').value : 'point',
							friend_amount: document.getElementById('wizard_friend_amount') ? document.getElementById('wizard_friend_amount').value : 10,
							advocate_coupon: document.getElementById('wizard_advocate_coupon') ? document.getElementById('wizard_advocate_coupon').value : '',
							friend_coupon: document.getElementById('wizard_friend_coupon') ? document.getElementById('wizard_friend_coupon').value : '',
							frontend_message: document.getElementById('wizard_frontend_message') ? document.getElementById('wizard_frontend_message').value : '',
							punch_reward_option: document.querySelector('input[name="punch_reward_option"]:checked') ? document.querySelector('input[name="punch_reward_option"]:checked').value : 'same',
							conversion_points: document.getElementById('wizard_conversion_points') ? document.getElementById('wizard_conversion_points').value : null,
							conversion_value: document.getElementById('wizard_conversion_value') ? document.getElementById('wizard_conversion_value').value : null,
							spend_min_subtotal: document.getElementById('wizard_spend_min_subtotal') ? document.getElementById('wizard_spend_min_subtotal').value : 100,
							discount_type: document.getElementById('wizard_discount_type') ? document.getElementById('wizard_discount_type').value : '',
							discount_value: document.getElementById('wizard_discount_value') ? document.getElementById('wizard_discount_value').value : '',
							discount_expiry: document.getElementById('wizard_discount_expiry') ? document.getElementById('wizard_discount_expiry').value : '',
							discount_limit: document.getElementById('wizard_discount_limit') ? document.getElementById('wizard_discount_limit').value : '',
							allow_birthday_edit: allowBirthdayEdit ? (allowBirthdayEdit.checked ? 'yes' : 'no') : 'no',
							monthly_target_audience: document.getElementById('wizard_monthly_target_audience') ? document.getElementById('wizard_monthly_target_audience').value : 'all',
							monthly_coupon: document.getElementById('wizard_monthly_coupon') ? document.getElementById('wizard_monthly_coupon').value : '',
							monthly_coupon_expiry: document.getElementById('wizard_monthly_coupon_expiry') ? document.getElementById('wizard_monthly_coupon_expiry').value : 'end_of_month',
							conditions: JSON.stringify(this.getConditions())
						};

					fetch(ajaxurl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: new URLSearchParams(payload)
					})
					.then(response => response.json())
					.then(data => {
						btnNext.innerHTML = originalText;
						btnNext.disabled = false;
						if (data && data.success) {
							this.showToast('Saved successfully!');
							setTimeout(() => {
								window.location.reload();
							}, 1500);
						} else {
							let errMsg = 'Failed to save configuration.';
							if (data && data.data) {
								if (data.data.message) errMsg = data.data.message;
								else if (typeof data.data === 'string') errMsg = data.data;
								else errMsg = JSON.stringify(data.data);
							} else if (data) {
								errMsg = JSON.stringify(data);
							}
							this.showToast('Error: ' + errMsg, true);
						}
					})
					.catch(error => {
						btnNext.innerText = originalText;
						btnNext.disabled = false;
						this.showToast('AJAX Exception: ' + (error.message || error), true);
					});
				},

				getConditions: function() {
					const conditions = [];
					const rows = document.querySelectorAll('.o100-condition-row');
					rows.forEach(row => {
						const type = row.querySelector('.o100-cond-type').value;
						const op = row.querySelector('.o100-cond-op').value;
						const val = row.querySelector('.o100-cond-val').value;
						
						conditions.push({
							type: type,
							operator: op,
							options: {
								value: [val]
							}
						});
					});
					return conditions;
				},

				deleteCampaign: function(id) {
					this.confirmAction("Delete Campaign", "Are you sure you want to delete this campaign? This action cannot be undone.", () => {
						fetch(ajaxurl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded'
							},
							body: new URLSearchParams({
								action: 'o100_delete_loyalty_campaign',
								nonce: '<?php echo wp_create_nonce("o100_loyalty_proxy"); ?>',
								campaign_id: id
							})
						})
						.then(response => response.json())
						.then(data => {
							if (data.success) {
								window.location.reload();
							} else {
								this.showToast('Error deleting campaign: ' + (data.data.message || ''), true);
							}
						})
						.catch(error => this.showToast('Error: ' + error.message, true));
					});
				},

				openBooster: function(type, id = 0) {
					const modal = document.getElementById('o100-booster-modal');
					const title = document.getElementById('booster-title');
					const desc = document.getElementById('booster-desc');
					const typeInput = document.getElementById('booster_type');
					const idInput = document.getElementById('booster_campaign_id');
					const pointsInput = document.getElementById('booster_points');
					const statusInput = document.getElementById('booster_status');
					const iconContainer = document.getElementById('booster-icon-container');

					typeInput.value = type;
					idInput.value = id;
					statusInput.checked = true;

					const configs = {
						'signup': {
							title: 'Account Sign Up',
							desc: 'Reward users when they create a new account.',
							icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>',
							defaultPoints: 50
						},
						'product_review': {
							title: 'Product Review',
							desc: 'Reward users for leaving a product review.',
							icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>',
							defaultPoints: 20
						},
						'facebook_share': {
							title: 'Social Share (Facebook)',
							desc: 'Reward users for sharing on Facebook.',
							icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>',
							defaultPoints: 10
						},
						'twitter_share': {
							title: 'Social Share (X/Twitter)',
							desc: 'Reward users for sharing on X.',
							icon: '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path></svg>',
							defaultPoints: 10
						},
						'whatsapp_share': {
							title: 'Social Share (WhatsApp)',
							desc: 'Reward users for sharing on WhatsApp.',
							icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>',
							defaultPoints: 10
						},
						'email_share': {
							title: 'Social Share (Email)',
							desc: 'Reward users for sharing via Email.',
							icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>',
							defaultPoints: 10
						},
						'pickup_bonus': {
							title: 'Pickup Bonus',
							desc: 'Reward customers who choose local pickup instead of delivery.',
							icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>',
							defaultPoints: 50
						},
						'profile_bonus': {
							title: 'Profile Completion',
							desc: 'Reward customers for saving their phone number/details.',
							icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
							defaultPoints: 100
						},
						'preorder_bonus': {
							title: 'Pre-order Bonus',
							desc: 'Reward customers for ordering 24 hours in advance.',
							icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
							defaultPoints: 100
						}
					};

					if (configs[type]) {
						title.innerText = configs[type].title;
						desc.innerText = configs[type].desc;
						iconContainer.innerHTML = configs[type].icon;
						pointsInput.value = configs[type].defaultPoints;
					}

					modal.classList.remove('hidden');
					modal.classList.add('flex'); // Add flex to ensure centering
					void modal.offsetWidth;
					modal.classList.remove('opacity-0');
					modal.querySelector('.transform').classList.remove('scale-95');

					if (id > 0) {
						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: new URLSearchParams({
								action: 'o100_loyalty_get_campaign',
								nonce: '<?php echo wp_create_nonce("o100_loyalty_proxy"); ?>',
								campaign_id: id
							})
						})
						.then(r => r.json())
						.then(res => {
							if (res.success && res.data.campaign) {
								const c = res.data.campaign;
								statusInput.checked = (c.active == 1);
								if (c.point_rule && c.point_rule.earn_point) {
									pointsInput.value = c.point_rule.earn_point;
								}
							}
						});
					}
				},

				closeBooster: function() {
					const modal = document.getElementById('o100-booster-modal');
					modal.classList.add('opacity-0');
					modal.querySelector('.transform').classList.add('scale-95');
					setTimeout(() => {
						modal.classList.add('hidden');
						modal.classList.remove('flex'); // Remove flex when hidden
					}, 300);
				},

				saveBooster: function() {
					const btn = document.getElementById('booster_save_btn');
					const text = document.getElementById('booster_save_text');
					const loader = document.getElementById('booster_save_loader');

					btn.disabled = true;
					text.innerText = 'Saving...';
					loader.classList.remove('hidden');

					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({
							action: 'o100_save_booster',
							nonce: '<?php echo wp_create_nonce("o100_loyalty_proxy"); ?>',
							campaign_id: document.getElementById('booster_campaign_id').value,
							action_type: document.getElementById('booster_type').value,
							points: document.getElementById('booster_points').value,
							status: document.getElementById('booster_status').checked ? 1 : 0
						})
					})
					.then(r => r.json())
					.then(res => {
						if (!res.success) {
							const errorMsg = res.data ? (typeof res.data === 'string' ? res.data : JSON.stringify(res.data)) : 'Unknown Error';
							this.showToast('Error: ' + errorMsg, true);
							btn.disabled = false;
							text.innerText = 'Save Changes';
							loader.classList.add('hidden');
						} else {
							this.showToast('Booster saved successfully!');
							setTimeout(() => window.location.reload(), 1000);
						}
					})
					.catch(e => {
						this.showToast('Error: ' + e.message, true);
						btn.disabled = false;
						text.innerText = 'Save Changes';
						loader.classList.add('hidden');
					});
				},

				toggleCampaignStatus: function(id, status) {
					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({
							action: 'o100_loyalty_toggle_campaign_status',
							nonce: '<?php echo wp_create_nonce("o100_loyalty_proxy"); ?>',
							campaign_id: id,
							status: status
						})
					})
					.then(r => r.json())
					.then(res => {
						if (!res.success) {
							this.showToast('Error updating status', true);
							setTimeout(() => window.location.reload(), 1000);
						} else {
							this.showToast('Status updated successfully');
						}
					})
					.catch(e => {
						this.showToast('Error: ' + e.message, true);
						setTimeout(() => window.location.reload(), 1000);
					});
				},

				showToast: function(msg, isError = false) {
					const toast = document.getElementById('o100-toast');
					const toastMsg = document.getElementById('o100-toast-msg');
					const toastIcon = document.getElementById('o100-toast-icon');
					if(toast && toastMsg) {
						toastMsg.innerText = msg;
						if (isError) {
							toast.style.backgroundColor = '#ef4444'; // red-500
							if(toastIcon) toastIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
						} else {
							toast.style.backgroundColor = '#10B981'; // emerald-500
							if(toastIcon) toastIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>';
						}
						toast.classList.add('show');
						setTimeout(() => {
							toast.classList.remove('show');
						}, 3000);
					}
				},

				confirmAction: function(title, msg, onConfirm) {
					const modal = document.getElementById('o100-confirm-modal');
					document.getElementById('o100-confirm-title').innerText = title;
					document.getElementById('o100-confirm-msg').innerText = msg;
					
					modal.style.display = 'flex';
					// trigger reflow
					void modal.offsetWidth;
					modal.classList.remove('opacity-0');
					modal.firstElementChild.classList.remove('scale-95');

					const close = () => {
						modal.classList.add('opacity-0');
						modal.firstElementChild.classList.add('scale-95');
						setTimeout(() => { modal.style.display = 'none'; }, 300);
					};

					document.getElementById('o100-confirm-cancel').onclick = close;
					document.getElementById('o100-confirm-ok').onclick = () => {
						close();
						onConfirm();
					};
				}
			};
			
			// Initialize Proxy Admin Tabs when DOM is ready
			document.addEventListener('DOMContentLoaded', function() {
				const saveBtn = document.getElementById('o100-save-proxy-settings');
				if (saveBtn) {
					saveBtn.addEventListener('click', o100Proxy.saveSettings);
				}
				
				// Initialize Live Preview
				o100Proxy.initLivePreview();
				
				// Load Settings on start
				o100Proxy.loadSettings();
			});
		</script>
		<?php
	}

	/**
	 * AJAX Handler to translate modern UI configuration to WPLoyalty Backend format.
	 */
	public static function handle_save_wizard() {
		check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized access.' ] );
		}

		$card_type      = isset( $_POST['card_type'] ) ? sanitize_text_field( wp_unslash( $_POST['card_type'] ) ) : '';
		$campaign_name  = isset( $_POST['campaign_name'] ) ? sanitize_text_field( wp_unslash( $_POST['campaign_name'] ) ) : '';
		$campaign_desc  = isset( $_POST['campaign_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['campaign_desc'] ) ) : '';
		$frontend_message  = isset( $_POST['frontend_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['frontend_message'] ) ) : '';
		$conditions_raw = isset( $_POST['conditions'] ) ? json_decode( wp_unslash( $_POST['conditions'] ), true ) : [];

		if ( ! in_array( $card_type, ['birthday', 'points', 'punch_card', 'referral', 'spend_save', 'monthly_reward'] ) ) {
			wp_send_json_error( [ 'message' => 'Invalid configuration type.' ] );
		}

		if ( ! class_exists( 'O100_Loyalty_DB' ) ) {
			wp_send_json_error( [ 'message' => 'Loyalty Engine is not loaded.' ] );
		}

		// Helper: Create coupon reward via O100 Promotions (replaces WPLoyalty Rewards)
		$create_coupon = function( $name, $value, $type, $expiry, $limit ) {
			if ( ! class_exists( 'O100_Promotions_DB' ) ) {
				require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
			}
			$promo_id = O100_Promotions_DB::insert([
				'source'        => 'loyalty',
				'title'         => $name . ' Coupon',
				'description'   => 'Auto-generated coupon for ' . $name,
				'rule_type'     => 'simple',
				'action_config' => wp_json_encode([ 'discount_type' => $type, 'discount_value' => floatval($value) ]),
				'apply_to'      => 'all_products',
				'apply_to_items'=> '[]',
				'conditions'    => '[]',
				'usage_limit'   => intval($limit),
				'status'        => 'active',
				'is_exclusive'  => 1,
				'end_date'      => $expiry > 0 ? gmdate('Y-m-d H:i:s', strtotime('+'.$expiry.' days')) : null,
			]);
			return $promo_id ? 'promo_' . $promo_id : false;
		};

		// Helper: Save global point conversion rate to o100_loyalty_settings
		$create_global_conversion = function( $conv_pts, $conv_val ) {
			$settings = O100_Loyalty_DB::get_settings();
			$settings['conversion_points'] = intval($conv_pts);
			$settings['conversion_value']  = floatval($conv_val);
			O100_Loyalty_DB::save_settings( $settings );
		};

		$action_type = '';
		$campaign_type = 'point';
		$point_rule_arr = [];

		$reward_type   = isset( $_POST['reward_type'] ) ? sanitize_text_field( wp_unslash( $_POST['reward_type'] ) ) : 'points';
		$reward_points = isset( $_POST['reward_points'] ) ? intval( $_POST['reward_points'] ) : 100;
		$discount_type = isset( $_POST['discount_type'] ) ? sanitize_text_field( wp_unslash( $_POST['discount_type'] ) ) : 'fixed';
		$discount_value= isset( $_POST['discount_value'] ) ? floatval( $_POST['discount_value'] ) : 10;
		$discount_expiry=isset( $_POST['discount_expiry'] ) ? intval( $_POST['discount_expiry'] ) : 30;
		$discount_limit= isset( $_POST['discount_limit'] ) ? intval( $_POST['discount_limit'] ) : 0;

		// -------------------------------------------------------------
		// 1. Birthday Campaign
		// -------------------------------------------------------------
		if ( $card_type === 'birthday' ) {
			$action_type = 'birthday';
			$point_rule_arr = [
				'earn_type'          => 'fixed',
				'earn_point'         => $reward_points,
				'birthday_message'   => $frontend_message,
				'birthday_earn_type' => 'on_their_birthday'
			];

			if ( $reward_type === 'discount' ) {
				$reward_id = $create_coupon( $campaign_name, $discount_value, $discount_type, $discount_expiry, $discount_limit );
				if ( $reward_id ) {
					$campaign_type = 'coupon';
					$point_rule_arr['earn_reward'] = $reward_id;
				}
				// Store discount details inline for edit-mode refill
				$point_rule_arr['discount_config'] = [
					'type'   => $discount_type,
					'value'  => $discount_value,
					'expiry' => $discount_expiry,
					'limit'  => $discount_limit,
				];
			}

			// Override global Birthday Capture settings
			$settings = O100_Loyalty_DB::get_settings();
			$settings['birthday_display_place']     = 'checkout,registration,account_details';
			$settings['is_one_time_birthdate_edit'] = isset( $_POST['allow_birthday_edit'] ) && $_POST['allow_birthday_edit'] === 'yes' ? 'yes' : 'no';
			O100_Loyalty_DB::save_settings( $settings );
		}
		// -------------------------------------------------------------
		// 1.5. Monthly Reward Campaign
		// -------------------------------------------------------------
		elseif ( $card_type === 'monthly_reward' ) {
			$action_type = 'monthly_reward';
			$monthly_coupon = isset( $_POST['monthly_coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['monthly_coupon'] ) ) : '';
			$point_rule_arr = [
				'earn_type'       => 'fixed',
				'earn_point'      => $reward_points,
				'target_audience' => isset( $_POST['monthly_target_audience'] ) ? sanitize_text_field( wp_unslash( $_POST['monthly_target_audience'] ) ) : 'all',
				'coupon_expiry'   => isset( $_POST['monthly_coupon_expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['monthly_coupon_expiry'] ) ) : 'end_of_month',
			];

			if ( ! empty( $monthly_coupon ) ) {
				$campaign_type = 'coupon';
				$point_rule_arr['earn_reward'] = $monthly_coupon;
				$point_rule_arr['discount_config'] = [
					'type' => 'existing'
				];
			} elseif ( $reward_type === 'discount' ) {
				$reward_id = $create_coupon( $campaign_name, $discount_value, $discount_type, $discount_expiry, $discount_limit );
				if ( $reward_id ) {
					$campaign_type = 'coupon';
					$point_rule_arr['earn_reward'] = $reward_id;
				}
				$point_rule_arr['discount_config'] = [
					'type'   => $discount_type,
					'value'  => $discount_value,
					'expiry' => $discount_expiry,
					'limit'  => $discount_limit,
				];
			}
		}
		// -------------------------------------------------------------
		// 2. Points Program
		// -------------------------------------------------------------
		elseif ( $card_type === 'points' ) {
			$action_type = 'point_for_purchase';
			$point_earn_price = isset( $_POST['point_earn_price'] ) ? floatval( $_POST['point_earn_price'] ) : 1;
			
			$point_rule_arr = [
				'wlr_point_earn_price'         => $point_earn_price,
				'earn_point'                   => $reward_points,
				'earn_reward'                  => '',
				'minimum_point'                => 0,
				'maximum_point'                => 0,
				'variable_product_message'     => 'Earn up to {o100_product_points} {o100_points_label}.',
				'single_product_message'       => 'Purchase & earn {o100_product_points} {o100_points_label}!',
				'is_rounded_edge'              => 'yes',
				'display_product_message_page' => 'all'
			];

			if ( isset($_POST['conversion_points']) && !empty($_POST['conversion_points']) ) {
				$create_global_conversion( $_POST['conversion_points'], $_POST['conversion_value'] );
			}
		}
		// -------------------------------------------------------------
		// 3. Visual Punch Card
		// -------------------------------------------------------------
		elseif ( $card_type === 'punch_card' ) {
			$action_type = 'o100_punch_card'; // Using custom action type to detach from WPLoyalty Engine
			
			// Inject participating products into conditions if defined
			$punch_products = isset( $_POST['punch_products'] ) ? sanitize_text_field( wp_unslash( $_POST['punch_products'] ) ) : '';
			if ( empty( $punch_products ) ) {
				wp_send_json_error( [ 'message' => 'Punch Card requires selecting at least one Participating Product.' ] );
			}
			$product_ids = array_map( 'intval', explode( ',', $punch_products ) );
			$conditions_raw[] = [
				'type' => 'products',
				'options' => [
					'operator' => 'in_list',
					'value'    => $product_ids
				]
			];

			// We hardcode earn point to 1 (1 stamp) and earn price to 1.
			$point_rule_arr = [
				'wlr_point_earn_price'         => 1,
				'earn_point'                   => 1,
				'earn_reward'                  => '',
				'minimum_point'                => 0,
				'maximum_point'                => 0,
				'variable_product_message'     => $frontend_message,
				'single_product_message'       => $frontend_message,
				'is_rounded_edge'              => 'yes',
				'display_product_message_page' => 'all',
				'is_punch_card'                => 'yes'
			];

			$punch_count = isset( $_POST['punch_count'] ) ? intval( $_POST['punch_count'] ) : 5;
			
			// Build punch card reward config (stored natively in campaign reward_config)
			$punch_reward_option = isset( $_POST['punch_reward_option'] ) ? $_POST['punch_reward_option'] : 'same';
			$punch_reward_products = [];
			if ( $punch_reward_option === 'same' ) {
				$punch_reward_products = $product_ids;
			} else {
				$punch_reward_product = isset( $_POST['punch_reward_product'] ) ? intval( $_POST['punch_reward_product'] ) : 0;
				if ( $punch_reward_product > 0 ) {
					$punch_reward_products[] = $punch_reward_product;
				}
			}

			if ( empty( $punch_reward_products ) ) {
				wp_send_json_error( [ 'message' => 'Punch Card requires selecting a Reward Product.' ] );
			}

			// Store reward config directly in campaign (no separate rewards table needed)
			$point_rule_arr['reward_config'] = [
				'type'             => 'free_product',
				'required_stamps'  => $punch_count,
				'reward_option'    => $punch_reward_option,
				'reward_products'  => $punch_reward_products,
			];
		}
		// -------------------------------------------------------------
		// 4. Referral Program
		// -------------------------------------------------------------
		elseif ( $card_type === 'referral' ) {
			$action_type = 'referral';
			
			$adv_type   = isset( $_POST['advocate_type'] ) ? sanitize_text_field( $_POST['advocate_type'] ) : 'point';
			$adv_amount = isset( $_POST['advocate_amount'] ) ? intval( $_POST['advocate_amount'] ) : 100;
			$fri_type   = isset( $_POST['friend_type'] ) ? sanitize_text_field( $_POST['friend_type'] ) : 'point';
			$fri_amount = isset( $_POST['friend_amount'] ) ? intval( $_POST['friend_amount'] ) : 10;

			$point_rule_arr = [
				'advocate' => [
					'campaign_type' => $adv_type === 'coupon' ? 'coupon' : 'point',
					'earn_type'     => 'fixed_point', // or 'fixed'
					'earn_point'    => $adv_amount,
					'earn_reward'   => ''
				],
				'friend'   => [
					'campaign_type' => $fri_type === 'coupon' ? 'coupon' : 'point',
					'earn_type'     => 'fixed_point',
					'earn_point'    => $fri_amount,
					'earn_reward'   => ''
				]
			];

			// Advocate coupon — value is 'promo_X' or 'wc_X' from dropdown
			if ( $adv_type === 'coupon' ) {
				$point_rule_arr['advocate']['earn_reward'] = sanitize_text_field( $_POST['advocate_coupon'] ?? '' );
			}

			// Friend coupon — value is 'promo_X' or 'wc_X' from dropdown
			if ( $fri_type === 'coupon' ) {
				$point_rule_arr['friend']['earn_reward'] = sanitize_text_field( $_POST['friend_coupon'] ?? '' );
			}
		}
		// -------------------------------------------------------------
		// 5. Spend & Save
		// -------------------------------------------------------------
		elseif ( $card_type === 'spend_save' ) {
			$action_type = 'subtotal';
			$min_subtotal = isset( $_POST['spend_min_subtotal'] ) ? floatval( $_POST['spend_min_subtotal'] ) : 100;
			
			$point_rule_arr = [
				'min_subtotal' => $min_subtotal,
				'max_subtotal' => 0,
				'earn_reward'  => '',
				'is_rounded_edge'              => 'yes',
				'display_product_message_page' => 'all'
			];

			if ( $reward_type === 'discount' ) {
				$reward_id = $create_coupon( $campaign_name, $discount_value, $discount_type, $discount_expiry, $discount_limit );
				if ( $reward_id ) {
					$campaign_type = 'coupon';
					$point_rule_arr['earn_reward'] = $reward_id;
				}
				$point_rule_arr['discount_config'] = [
					'type'   => $discount_type,
					'value'  => $discount_value,
					'expiry' => $discount_expiry,
					'limit'  => $discount_limit,
				];
			} elseif ( $reward_type === 'free_item' ) {
				$reward_id = $create_coupon( $campaign_name, 100, 'percent', $discount_expiry, $discount_limit );
				if ( $reward_id ) {
					$campaign_type = 'coupon';
					$point_rule_arr['earn_reward'] = $reward_id;
				}
				$point_rule_arr['discount_config'] = [
					'type'   => 'percentage',
					'value'  => 100,
					'expiry' => $discount_expiry,
					'limit'  => $discount_limit,
				];
			}
		}

		// -------------------------------------------------------------
		// Save Campaign
		// -------------------------------------------------------------
		$req_campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
		
		$campaign_data = [
			'title'                  => $campaign_name,
			'description'            => $campaign_desc,
			'type'                   => $card_type,
			'earn_config'            => wp_json_encode( $point_rule_arr ),
			'conditions'             => wp_json_encode( is_array($conditions_raw) ? $conditions_raw : [] ),
			'condition_relationship' => 'and',
			'priority'               => 10,
			'status'                 => 'active',
			'is_show_way_to_earn'    => 1,
			'ordering'               => 1,
		];

		try {
			if ( $req_campaign_id > 0 ) {
				O100_Loyalty_DB::update_campaign( $req_campaign_id, $campaign_data );
				$campaign_id = $req_campaign_id;
			} else {
				$campaign_id = O100_Loyalty_DB::insert_campaign( $campaign_data );
			}
			if ( ! $campaign_id ) {
				global $wpdb;
				wp_send_json_error( [ 'message' => 'Save Failed: ' . $wpdb->last_error ] );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}

		wp_send_json_success( [ 'message' => 'Configuration saved successfully.' ] );
	}

	/**
	 * AJAX Handler to delete a campaign
	 */
	public static function handle_delete_campaign() {
		check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized access.' ] );
		}
		
		$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
		if ( $campaign_id > 0 ) {
			O100_Loyalty_DB::delete_campaign( $campaign_id );
			wp_send_json_success();
		}
		
		wp_send_json_error( [ 'message' => 'Invalid campaign ID.' ] );
	}

	/**
	 * AJAX Handler to get a campaign by ID
	 */
	public static function handle_get_campaign() {
		check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized access.' ] );
		}
		
		$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
		if ( $campaign_id > 0 ) {
			$campaign = O100_Loyalty_DB::get_campaign( $campaign_id );
			if ( $campaign ) {
				// Decode JSON fields
				if ( ! empty( $campaign->earn_config ) ) {
					$campaign->point_rule = json_decode( $campaign->earn_config, true );
				} else {
					$campaign->point_rule = [];
				}
				if ( ! empty( $campaign->conditions ) ) {
					$campaign->conditions = json_decode( $campaign->conditions, true );
				} else {
					$campaign->conditions = [];
				}

				// Map O100 field names to what the JS wizard expects
				$campaign->name        = $campaign->title;
				$campaign->action_type = $campaign->type;
				$campaign->active      = $campaign->status === 'active' ? 1 : 0;

				// Punch card: extract reward config for UI pre-population
				if ( isset( $campaign->point_rule['reward_config'] ) ) {
					$rc = $campaign->point_rule['reward_config'];
					if ( isset( $rc['required_stamps'] ) ) $campaign->punch_count = $rc['required_stamps'];
					if ( isset( $rc['reward_products'][0] ) ) $campaign->punch_reward_product = $rc['reward_products'][0];
				}

				wp_send_json_success( [ 'campaign' => $campaign ] );
			}
		}
		
		wp_send_json_error( [ 'message' => 'Campaign not found.' ] );
	}

	/**
	 * Toggle campaign status
	 */
	public static function handle_toggle_campaign_status() {
		check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized access.' ] );
		}
		
		$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;
		$status      = isset( $_POST['status'] ) ? intval( $_POST['status'] ) : 0;
		
		if ( $campaign_id > 0 ) {
			$new_status = $status ? 'active' : 'disabled';
			$result = O100_Loyalty_DB::update_campaign( $campaign_id, [ 'status' => $new_status ] );
			if ( $result !== false ) {
				wp_send_json_success( [ 'message' => 'Status updated.' ] );
			}
		}
		wp_send_json_error( [ 'message' => 'Campaign not found.' ] );
	}

	/**
	 * AJAX: Create a referral coupon in the O100 Promotions table.
	 */
	public static function handle_create_referral_coupon() {
		check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Permission denied.' ] ); }
		if ( ! class_exists( 'O100_Promotions_DB' ) ) { require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php'; }

		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		if ( empty( $title ) ) { wp_send_json_error( [ 'message' => 'Coupon name is required.' ] ); }

		$discount_type  = sanitize_text_field( $_POST['discount_type'] ?? 'fixed' );
		$discount_value = floatval( $_POST['discount_value'] ?? 5 );
		$min_spend      = floatval( $_POST['min_spend'] ?? 0 );
		$individual_use = ( $_POST['individual_use'] ?? '0' ) === '1';
		$usage_limit    = intval( $_POST['usage_limit'] ?? 1 );
		$expiry_days    = intval( $_POST['expiry_days'] ?? 30 );
		$promo_code     = strtoupper( substr( sanitize_title( $title ), 0, 8 ) ) . '_' . wp_rand( 1000, 9999 );

		$action_config = wp_json_encode([ 'discount_type' => $discount_type, 'discount_value' => $discount_value, 'min_spend' => $min_spend, 'individual_use' => $individual_use ]);
		$end_date = $expiry_days > 0 ? gmdate( 'Y-m-d H:i:s', strtotime( '+' . $expiry_days . ' days' ) ) : null;
		$conditions = $min_spend > 0 ? wp_json_encode([['type'=>'cart_subtotal','operator'=>'gte','value'=>$min_spend]]) : '[]';

		$new_id = O100_Promotions_DB::insert([
			'source' => 'loyalty', 'title' => $title, 'description' => sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'rule_type' => 'simple', 'action_config' => $action_config, 'apply_to' => 'all_products', 'apply_to_items' => '[]',
			'promo_code' => $promo_code, 'conditions' => $conditions,
			'usage_limit' => $usage_limit, 'status' => 'active', 'priority' => 10,
			'is_exclusive' => $individual_use ? 1 : 0, 'end_date' => $end_date,
		]);

		if ( $new_id ) {
			$sum = $discount_type === 'percentage' ? $discount_value . '%' : '$' . $discount_value;
			wp_send_json_success([ 'id' => $new_id, 'title' => $title . ' (' . $sum . ')' ]);
		}
		wp_send_json_error([ 'message' => 'Database insert failed.' ]);
	}

	/**
	 * AJAX: Search product categories by term.
	 */
	public static function handle_search_categories() {
		$term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( [] );
		}
		$cats = get_terms( [
			'taxonomy'   => 'product_cat',
			'name__like' => $term,
			'hide_empty' => false,
			'number'     => 20,
		] );
		$results = [];
		if ( ! is_wp_error( $cats ) ) {
			foreach ( $cats as $cat ) {
				$results[] = [ 'id' => $cat->term_id, 'text' => $cat->name ];
			}
		}
		wp_send_json_success( $results );
	}

}


// TS: 20260323174411
