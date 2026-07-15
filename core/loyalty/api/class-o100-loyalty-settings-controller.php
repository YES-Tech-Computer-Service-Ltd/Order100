<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class O100_Loyalty_Settings_Controller {

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
				
				$settings['gs_points_expiry_value'] = $wlr['wlr_point_expiry_duration'] ?? '365';
				$settings['gs_points_expiry_unit'] = $wlr['wlr_point_expiry_duration_type'] ?? 'days';
				$settings['gs_points_expiry_reminder_value'] = $settings['points_expiry_reminder_value'] ?? '30';
				$settings['gs_points_expiry_reminder_unit'] = $settings['points_expiry_reminder_unit'] ?? 'days';
				$settings['gs_grace_period'] = $settings['tier_grace_period_days'] ?? '30';
	
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

	public static function get_proxy_settings() {
			ob_clean();
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Permission denied' );
			}
	
			$settings = self::get_merged_proxy_settings();
			wp_send_json_success( $settings );
		}

	public static function save_proxy_settings() {
			ob_clean();
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				check_ajax_referer( 'o100_loyalty_proxy', 'nonce' );
			}
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
	
			// Map General Settings to Native Engine format
			if ( isset( $updated['gs_points_label_singular'] ) ) {
				$updated['point_label_singular'] = $updated['gs_points_label_singular'];
			}
			if ( isset( $updated['gs_points_label_plural'] ) ) {
				$updated['point_label_plural'] = $updated['gs_points_label_plural'];
			}
			if ( isset( $updated['gs_rounding_type'] ) ) {
				$mode = 'round';
				if ( $updated['gs_rounding_type'] === 'round_up' ) $mode = 'ceil';
				elseif ( $updated['gs_rounding_type'] === 'round_down' ) $mode = 'floor';
				elseif ( $updated['gs_rounding_type'] === 'no_round' ) $mode = 'none';
				$updated['point_rounding'] = $mode;
			}
			if ( isset( $updated['gs_product_earn_msg'] ) ) {
				$updated['product_message_enable'] = $updated['gs_product_earn_msg'] === 'yes' ? 'yes' : 'no';
			}
			if ( isset( $updated['gs_cart_earn_msg'] ) ) {
				$updated['cart_earn_message_enable'] = $updated['gs_cart_earn_msg'] === 'yes' ? 'yes' : 'no';
			}
			if ( isset( $updated['gs_checkout_earn_msg'] ) ) {
				$updated['checkout_earn_message_enable'] = $updated['gs_checkout_earn_msg'] === 'yes' ? 'yes' : 'no';
			}
			if ( isset( $updated['gs_calculation_basis'] ) ) {
				$updated['calculation_basis'] = $updated['gs_calculation_basis'];
			}
			if ( isset( $updated['gs_points_expiry_value'] ) ) {
				$updated['points_expiry_value'] = intval( $updated['gs_points_expiry_value'] );
			}
			if ( isset( $updated['gs_points_expiry_unit'] ) ) {
				$updated['points_expiry_unit'] = sanitize_text_field( $updated['gs_points_expiry_unit'] );
			}
			if ( isset( $updated['gs_points_expiry_reminder_value'] ) ) {
				$updated['points_expiry_reminder_value'] = intval( $updated['gs_points_expiry_reminder_value'] );
			}
			if ( isset( $updated['gs_points_expiry_reminder_unit'] ) ) {
				$updated['points_expiry_reminder_unit'] = sanitize_text_field( $updated['gs_points_expiry_reminder_unit'] );
			}
			if ( isset( $updated['gs_grace_period'] ) ) {
				$updated['tier_grace_period_days'] = intval( $updated['gs_grace_period'] );
			}
	
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
			
			$design_settings['design']['colors']['theme']['primary'] = isset($updated['fw_primary_color']) ? $updated['fw_primary_color'] : '#F59322';
			$design_settings['design']['colors']['theme']['secondary'] = isset($updated['fw_secondary_color']) ? $updated['fw_secondary_color'] : '#FFFFFF';
			$design_settings['design']['colors']['banner']['background'] = isset($updated['fw_banner_bg']) ? $updated['fw_banner_bg'] : '#F5F5F5';
			$design_settings['design']['colors']['banner']['text'] = isset($updated['fw_banner_text']) ? $updated['fw_banner_text'] : '#333333';
			$design_settings['design']['colors']['buttons']['background'] = isset($updated['fw_buttons_bg']) ? $updated['fw_buttons_bg'] : '#F59322';
			$design_settings['design']['colors']['buttons']['text'] = isset($updated['fw_buttons_text']) ? $updated['fw_buttons_text'] : '#FFFFFF';
			$design_settings['design']['colors']['launcher']['background'] = isset($updated['fw_launcher_bg']) ? $updated['fw_launcher_bg'] : '#F59322';
			$design_settings['design']['colors']['launcher']['text'] = isset($updated['fw_launcher_text_color']) ? $updated['fw_launcher_text_color'] : '#FFFFFF';
			$design_settings['design']['colors']['links'] = isset($updated['fw_links_color']) ? $updated['fw_links_color'] : '#F59322';
			$design_settings['design']['colors']['icons'] = isset($updated['fw_icons_color']) ? $updated['fw_icons_color'] : '#F59322';
			
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
			
			if ( isset( $updated['gs_points_expiry_value'] ) ) {
				$wlr['wlr_point_expiry_duration'] = intval( $updated['gs_points_expiry_value'] );
			}
			if ( isset( $updated['gs_points_expiry_unit'] ) ) {
				$wlr['wlr_point_expiry_duration_type'] = sanitize_text_field( $updated['gs_points_expiry_unit'] );
			}
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

}
