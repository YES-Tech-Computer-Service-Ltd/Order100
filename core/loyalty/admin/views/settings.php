<?php
/**
 * DEBUG_O100_LOYALTY_SETTINGS_FILE: V2
 * @author      Wployalty (Alagesan)
 *
 * @package Order100
 * @since   3.2.0
 */
defined( 'ABSPATH' ) || exit;

$settings = O100_Loyalty_Admin::get_loyalty_settings();
$all_statuses = wc_get_order_statuses();

// Load Launcher Data
$launcher_design = get_option('wll_launcher_design_settings', []);
$launcher_icon = get_option('wll_launcher_icon_settings', []);
$launcher_content = get_option('wll_launcher_content_settings', []);
if (is_array($launcher_content)) {
    array_walk_recursive($launcher_content, function(&$value) {
        if (is_string($value)) {
            $value = str_replace(array('{wfa_', '{o100_'), '{o100_', $value);
        }
    });
}

$settings['launcher_design_logo_is_show'] = $launcher_design['design']['logo']['is_show'] ?? 'show';
$settings['launcher_design_logo_image'] = $launcher_design['design']['logo']['image'] ?? '';
$settings['launcher_design_theme_primary'] = $launcher_design['design']['colors']['theme']['primary'] ?? '#4F47EB';
$settings['launcher_design_theme_secondary'] = $launcher_design['design']['colors']['theme']['secondary'] ?? '#FFFFFF';
$settings['launcher_design_banner_background'] = $launcher_design['design']['colors']['banner']['background'] ?? '#F5F5F5';
$settings['launcher_design_banner_text'] = $launcher_design['design']['colors']['banner']['text'] ?? '#333333';
$settings['launcher_design_buttons_background'] = $launcher_design['design']['colors']['buttons']['background'] ?? '#4F47EB';
$settings['launcher_design_buttons_text'] = $launcher_design['design']['colors']['buttons']['text'] ?? '#FFFFFF';
$settings['launcher_design_links'] = $launcher_design['design']['colors']['links'] ?? '#4F47EB';
$settings['launcher_design_icons'] = $launcher_design['design']['colors']['icons'] ?? '#4F47EB';
$settings['launcher_design_launcher_background'] = $launcher_design['design']['colors']['launcher']['background'] ?? '#4F47EB';
$settings['launcher_design_launcher_text'] = $launcher_design['design']['colors']['launcher']['text'] ?? '#FFFFFF';
$settings['launcher_design_branding_is_show'] = $launcher_design['design']['branding']['is_show'] ?? 'show';

$settings['launcher_content_guest_welcome_title'] = $launcher_content['content']['guest']['welcome']['texts']['title'] ?? 'Welcome';
$settings['launcher_content_guest_welcome_desc'] = $launcher_content['content']['guest']['welcome']['texts']['description'] ?? 'Sign up to earn points';
$settings['launcher_content_guest_have_account'] = $launcher_content['content']['guest']['welcome']['texts']['have_account'] ?? 'Already have an account?';
$settings['launcher_content_guest_signin_text'] = $launcher_content['content']['guest']['welcome']['texts']['sign_in'] ?? 'Sign in';
$settings['launcher_content_guest_signin_url'] = $launcher_content['content']['guest']['welcome']['texts']['sign_in_url'] ?? '';
$settings['launcher_content_guest_button_text'] = $launcher_content['content']['guest']['welcome']['button']['text'] ?? 'Join Now';
$settings['launcher_content_guest_button_url'] = $launcher_content['content']['guest']['welcome']['button']['url'] ?? '';
$settings['launcher_content_guest_earn_title'] = $launcher_content['content']['guest']['points']['earn']['title'] ?? 'Earn Points';
$settings['launcher_content_guest_redeem_title'] = $launcher_content['content']['guest']['points']['redeem']['title'] ?? 'Redeem Points';
$settings['launcher_content_guest_referrals_title'] = $launcher_content['content']['guest']['referrals']['title'] ?? 'Referrals';
$settings['launcher_content_guest_referrals_desc'] = $launcher_content['content']['guest']['referrals']['description'] ?? 'Refer your friends and earn rewards.';

$settings['launcher_content_member_welcome'] = $launcher_content['content']['member']['banner']['texts']['welcome'] ?? 'Welcome back, {o100_user_name}';
$settings['launcher_content_member_points_label'] = $launcher_content['content']['member']['banner']['texts']['points_label'] ?? 'Your Points';
$settings['launcher_content_member_earn_title'] = $launcher_content['content']['member']['points']['earn']['title'] ?? 'Earn Points';
$settings['launcher_content_member_redeem_title'] = $launcher_content['content']['member']['points']['redeem']['title'] ?? 'Redeem Points';
$settings['launcher_content_member_referrals_title'] = $launcher_content['content']['member']['referrals']['title'] ?? 'Referrals';
$settings['launcher_content_member_referrals_desc'] = $launcher_content['content']['member']['referrals']['description'] ?? 'Refer your friends and earn rewards.';

$settings['launcher_widget_appearance'] = $launcher_icon['launcher']['appearance']['selected'] ?? 'icon_with_text';
$settings['launcher_widget_text'] = $launcher_icon['launcher']['appearance']['text'] ?? 'Rewards';
$settings['launcher_widget_icon_type'] = $launcher_icon['launcher']['appearance']['icon']['icon'] ?? 'gift';
$settings['launcher_widget_custom_icon'] = $launcher_icon['launcher']['appearance']['icon']['image'] ?? '';
$settings['launcher_widget_view_option'] = $launcher_icon['launcher']['view_option'] ?? 'mobile_and_desktop';
$settings['launcher_widget_position'] = $launcher_icon['launcher']['placement']['position'] ?? 'right';
$settings['launcher_widget_side_spacing'] = $launcher_icon['launcher']['placement']['side_spacing'] ?? 20;
$settings['launcher_widget_bottom_spacing'] = $launcher_icon['launcher']['placement']['bottom_spacing'] ?? 20;

// Load Guest Referral Data
$wlcr_settings = get_option('wlcr_settings', []);
$wlcr_popup = get_option('wlcr_popup_settings', []);
$wlcr_coupon = get_option('wlcr_coupon_settings', []);

$wlcr_popup_defaults = [
    'background_color' => '#FFFFFF',
    'enable_image_content' => 'yes',
    'popup_image' => WLCR_PLUGIN_URL . "/Assets/svg/claim_reward.svg",
    'title_content' => 'Claim your reward!',
    'title_text_color' => '#333333',
    'enable_sub_title_content' => 'yes',
    'sub_title_text_color' => '#6d6d6d',
    'sub_title_content' => 'Your friend has gifted you a reward',
    'enable_prompt_content' => 'yes',
    'prompt_message_content' => 'Enter your email address to receive the reward.',
    'place_holder_content' => 'Enter your email',
    'claim_button_text' => 'Claim reward',
    'claim_button_background_color' => '#4f47eb',
    'claim_button_text_color' => '#ffffff',
    'no_thanks_text' => 'No, thanks'
];

$wlcr_coupon_defaults = [
    'background_color' => '#FFFFFF',
    'enable_image_content' => 'yes',
    'popup_image' => WLCR_PLUGIN_URL . "/Assets/svg/your_reward.svg",
    'title_content' => 'Your Reward As Promised',
    'title_text_color' => '#333333',
    'enable_sub_title_content' => 'yes',
    'sub_title_text_color' => '#6d6d6d',
    'sub_title_content' => 'Enter this coupon code at checkout to receive a discount on your 1st purchase.',
    'coupon_color' => '#FF8E3D',
    'background_coupon_color' => '#FFF8F3',
    'shop_button_text' => 'Shop Now',
    'shop_button_background_color' => '#4f47eb',
    'shop_button_text_color' => '#ffffff',
];

$wlcr_popup = wp_parse_args($wlcr_popup, $wlcr_popup_defaults);
$wlcr_coupon = wp_parse_args($wlcr_coupon, $wlcr_coupon_defaults);

// Prefix referral keys to avoid conflict
foreach ($wlcr_settings as $k => $v) { $settings['referral_'.$k] = $v; }
foreach ($wlcr_popup as $k => $v) { $settings['referral_popup_'.$k] = $v; }
foreach ($wlcr_coupon as $k => $v) { $settings['referral_coupon_'.$k] = $v; }

$referral_main = class_exists('\Wlcr\App\Controllers\Admin\Main') ? new \Wlcr\App\Controllers\Admin\Main() : null;
$referral_campaigns = $referral_main ? $referral_main->getReferralCampaignList() : [];
if (empty($referral_campaigns)) {
    $referral_campaigns = ['' => 'No eligible referral campaigns found'];
}


// Define Settings Schema
$settings_schema = array(
	'earn_point' => array(
		'title'       => __( 'General Settings', 'order100' ),
		'description' => __( 'General settings related to points and rewards', 'order100' ),
		'fields'      => array(
			array(
				'id'      => 'o100_point_rounding_type',
				'type'    => 'select',
				'title   '=> __( 'Rounding Mode for points earned', 'order100' ),
				'desc'    => __( 'Ex: If a user has spent 5.50 on a product, round up or to the nearest integer would make 6 points whereas round down will earn him 5 points.', 'order100' ),
				'options' => array(
					'round' => __( 'Round (Nearest)', 'order100' ),
					'floor' => __( 'Round Down (Floor)', 'order100' ),
					'ceil'  => __( 'Round Up (Ceil)', 'order100' )
				),
				'default' => 'round'
			),
			array(
				'id'      => 'o100_earning_status',
				'type'    => 'multiselect',
				'title'   => __( 'Success order status', 'order100' ),
				'desc'    => __( 'Points are awarded when order reaches these statuses.', 'order100' ),
				'options' => $all_statuses,
				'default' => array( 'processing', 'completed' )
			),
			array(
				'id'      => 'o100_removing_status',
				'type'    => 'multiselect',
				'title'   => __( 'Unsuccessful order status', 'order100' ),
				'desc'    => __( 'Points and rewards are removed if the order turns to these statuses.', 'order100' ),
				'options' => $all_statuses,
				'default' => array()
			),
			array(
				'id'      => 'o100_point_label',
				'type'    => 'text',
				'title'   => __( 'Label for the "points" - plural', 'order100' ),
				'desc'    => __( 'Enter the plural form of label for points.', 'order100' ),
				'default' => 'Points'
			),
			array(
				'id'      => 'o100_point_singular_label',
				'type'    => 'text',
				'title'   => __( 'Label for the "point" - singular', 'order100' ),
				'desc'    => __( 'Enter the singular form of label for point.', 'order100' ),
				'default' => 'Point'
			),
			array(
				'id'      => 'o100_my_account_label_icon_position',
				'type'    => 'select',
				'title'   => __( 'My Account Label Icon Display Position', 'order100' ),
				'desc'    => __( 'In My Account Page, Point label icon display position.', 'order100' ),
				'options' => array( 'menu' => 'Menu', 'dashboard' => 'Dashboard', 'none' => 'None' ),
				'default' => 'dashboard'
			),
			array(
				'id'      => 'reward_plural_label',
				'type'    => 'text',
				'title'   => __( 'Label for the "rewards" - plural', 'order100' ),
				'desc'    => __( 'Enter the plural form of label for rewards.', 'order100' ),
				'default' => 'Rewards'
			),
			array(
				'id'      => 'reward_singular_label',
				'type'    => 'text',
				'title'   => __( 'Label for the "reward" - singular', 'order100' ),
				'desc'    => __( 'Enter the singular form of label for reward.', 'order100' ),
				'default' => 'Reward'
			),
			array(
				'id'      => 'reward_code_prefix',
				'type'    => 'text',
				'title'   => __( 'Prefix to use for reward coupons', 'order100' ),
				'default' => 'WLR-'
			),
			array(
				'id'      => 'o100_referral_prefix',
				'type'    => 'text',
				'title'   => __( 'Prefix for referral code', 'order100' ),
				'default' => 'REF-'
			),
			array(
				'id'      => 'automatic_create_coupon',
				'type'    => 'select',
				'title'   => __( 'Create a coupon instantly when customers get an instant reward ?', 'order100' ),
				'options' => array( 'yes' => 'Yes', 'no' => 'No' ),
				'default' => 'yes'
			),
			array(
				'id'      => 'individual_use_coupon',
				'type'    => 'select',
				'title'   => __( 'Force "individual use only" for coupons', 'order100' ),
				'options' => array( 'yes' => 'Yes', 'no' => 'No' ),
				'default' => 'no'
			),
			array(
				'id'      => 'is_revert_enabled',
				'type'    => 'select',
				'title'   => __( 'Show an option to return points after converting to a coupon ?', 'order100' ),
				'options' => array( 'yes' => 'Yes', 'no' => 'No' ),
				'default' => 'no'
			),
			array(
				'id'      => 'add_customer_wpl_customer',
				'type'    => 'multiselect',
				'title'   => __( 'Create a customer record in Loyalty automatically for following actions', 'order100' ),
				'desc'    => __( 'Useful to automatically add the customer to Loyalty. Example: When Signin', 'order100' ),
				'options' => array( 'signin' => 'When Signin', 'checkout' => 'When checkout' ),
				'default' => array('signin')
			),
			array(
				'id'      => 'debug_mode',
				'type'    => 'select',
				'title'   => __( 'Debug mode', 'order100' ),
				'options' => array( 'no' => 'No', 'yes' => 'Yes' ),
				'default' => 'no'
			),
			array(
				'id'      => 'pagination_limit',
				'type'    => 'select',
				'title'   => __( 'Pagination Limit (default limit)', 'order100' ),
				'options' => array( '10' => '10', '20' => '20', '30' => '30', '50' => '50' ),
				'default' => '10'
			),
			array(
				'id'      => 'is_earn_point_after_discount',
				'type'    => 'select',
				'title'   => __( 'Calculate earn points', 'order100' ),
				'options' => array( 'after_discount' => 'After discount', 'before_discount' => 'Before discount' ),
				'default' => 'after_discount'
			),
			array(
				'id'      => 'birthday_display_place',
				'type'    => 'multiselect',
				'title'   => __( 'Capture customer\'s birthday', 'order100' ),
				'options' => array( 'checkout' => 'Checkout', 'register' => 'Registered on (WooCommerce)', 'account' => 'Account details' ),
				'default' => array( 'checkout', 'register', 'account' )
			),
			array(
				'id'      => 'is_one_time_birthdate_edit',
				'type'    => 'select',
				'title'   => __( 'Allow customers to edit their birthday after entering it ?', 'order100' ),
				'options' => array( 'yes' => 'Yes', 'no' => 'No' ),
				'default' => 'yes'
			),
			array(
				'id'      => 'tax_calculation_type',
				'type'    => 'select',
				'title'   => __( 'Tax calculation should be based on', 'order100' ),
				'options' => array( 'inherit' => 'Inherit from WooCommerce', 'including' => 'Including Tax', 'excluding' => 'Excluding Tax' ),
				'default' => 'inherit'
			),
			array(
				'id'      => 'o100_new_rewards_section_enabled',
				'type'    => 'toggle',
				'title'   => __( 'Enable new rewards section ?', 'order100' ),
				'default' => 'no'
			)
		)
	),
	'display_messages' => array(
		'title'       => __( 'Display Messages', 'order100' ),
		'description' => __( 'Includes product / cart and checkout pages', 'order100' ),
		'fields'      => array(
			array(
				'id'   => 'dm_products_heading',
				'type' => 'heading',
				'title'=> __( 'Products', 'order100' )
			),
			array(
				'id'      => 'product_message_display_position',
				'type'    => 'select',
				'title'   => __( 'Display position of earn points product page message', 'order100' ),
				'options' => array( 
					'before_add_to_cart' => 'Before Add to Cart', 
					'after_add_to_cart' => 'After Add to Cart',
					'before_price' => 'Before Price',
					'after_price' => 'After Price',
					'before_title' => 'Before Title',
					'after_title' => 'After Title',
					'before_excerpt' => 'Before Excerpt',
					'after_excerpt' => 'After Excerpt'
				),
				'default' => 'before_add_to_cart'
			),
			array(
				'id'      => 'o100_is_product_earn_message_enable',
				'type'    => 'toggle',
				'title'   => __( 'Display on Product Page ?', 'order100' ),
				'default' => 'yes'
			),

			array(
				'id'   => 'dm_cart_heading',
				'type' => 'heading',
				'title'=> __( 'Cart', 'order100' )
			),
			array(
				'id'      => 'o100_is_cart_earn_message_enable',
				'type'    => 'toggle',
				'title'   => __( 'Enable cart page earn message ?', 'order100' ),
				'default' => 'yes'
			),
			array(
				'id'      => 'o100_cart_earn_points_message',
				'type'    => 'textarea',
				'title'   => __( 'Cart page message for earn points', 'order100' ),
				'default' => 'Complete your order and earn {o100_cart_points} {o100_points_label} &amp; rewards for a discount on a future purchase'
			),
			array(
				'id'      => 'o100_cart_earn_point_display',
				'type'    => 'select',
				'title'   => __( 'Display position of earn points cart page message', 'order100' ),
				'options' => array( 'before' => 'Before Cart items [Normal Cart]', 'after' => 'After Cart items' ),
				'default' => 'before'
			),
			array(
				'id'      => 'o100_is_cart_redeem_message_enable',
				'type'    => 'toggle',
				'title'   => __( 'Enable cart page redeem message ?', 'order100' ),
				'default' => 'yes'
			),
			array(
				'id'      => 'o100_cart_redeem_points_message',
				'type'    => 'textarea',
				'title'   => __( 'Cart page message for Redeem points', 'order100' ),
				'default' => 'You have {o100_redeem_cart_points} {o100_points_label} earned choose your rewards {o100_reward_link}'
			),
			array(
				'id'      => 'o100_cart_redeem_point_display',
				'type'    => 'select',
				'title'   => __( 'Display Position Of Redeem Points Cart Page Message', 'order100' ),
				'options' => array( 'before' => 'Before Cart items [Normal Cart]', 'after' => 'After Cart items' ),
				'default' => 'before'
			),
			array(
				'id'   => 'dm_checkout_heading',
				'type' => 'heading',
				'title'=> __( 'Checkout', 'order100' )
			),
			array(
				'id'      => 'o100_is_checkout_earn_message_enable',
				'type'    => 'toggle',
				'title'   => __( 'Enable checkout page earn message ?', 'order100' ),
				'default' => 'yes'
			),
			array(
				'id'      => 'o100_checkout_earn_points_message',
				'type'    => 'textarea',
				'title'   => __( 'Checkout page message for earn points', 'order100' ),
				'default' => 'Complete your order and earn {o100_cart_points} {o100_points_label} &amp; rewards for a discount on a future purchase'
			),
			array(
				'id'      => 'o100_is_checkout_redeem_message_enable',
				'type'    => 'toggle',
				'title'   => __( 'Enable checkout page redeem message ?', 'order100' ),
				'default' => 'yes'
			),
			array(
				'id'      => 'o100_checkout_redeem_points_message',
				'type'    => 'textarea',
				'title'   => __( 'Checkout page message for Redeem points', 'order100' ),
				'default' => 'You have {o100_redeem_cart_points} {o100_points_label} earned choose your rewards {o100_reward_link}'
			),
			array(
				'id'   => 'dm_thankyou_heading',
				'type' => 'heading',
				'title'=> __( 'Thank You Page', 'order100' )
			),
			array(
				'id'      => 'o100_is_thank_you_message_enable',
				'type'    => 'toggle',
				'title'   => __( 'Enable Thank you page message ?', 'order100' ),
				'default' => 'yes'
			),
			array(
				'id'      => 'o100_thank_you_message',
				'type'    => 'textarea',
				'title'   => __( 'Message on Thank you page', 'order100' ),
				'default' => 'You have earned {o100_earned_points} {o100_points_label} for this order. You have a total of {o100_total_points}.'
			),
			array(
				'id'      => 'o100_thank_you_position',
				'type'    => 'select',
				'title'   => __( 'Thank you message display position', 'order100' ),
				'options' => array( 'top' => 'Top of the thank you message', 'bottom' => 'Bottom of the thank you message' ),
				'default' => 'top'
			),
			array(
				'id'      => 'o100_earn_point_order_summary_text',
				'type'    => 'textarea',
				'title'   => __( 'Order Review Earn Point Text', 'order100' ),
				'default' => 'Earn {o100_points} {o100_points_label} for this order.'
			),
			array(
				'id'   => 'dm_branding_heading',
				'type' => 'heading',
				'title'=> __( 'Branding', 'order100' )
			),
			array(
				'id'      => 'earn_cart_border_color',
				'type'    => 'color',
				'title'   => __( 'Earn Message - Border color', 'order100' ),
				'default' => '#9CC21D'
			),
			array(
				'id'      => 'earn_cart_text_color',
				'type'    => 'color',
				'title'   => __( 'Earn Message - Text color', 'order100' ),
				'default' => '#9CC21D'
			),
			array(
				'id'      => 'earn_cart_background_color',
				'type'    => 'color',
				'title'   => __( 'Earn Message - Background color', 'order100' ),
				'default' => '#ffffff'
			),
			/* Media uploads skipped until we confirm asset storage behavior
			array(
				'id'      => 'earn_message_icon',
				'type'    => 'media',
				'title'   => __( 'Icon / image for the Earn Point message', 'order100' ),
			), */
			array(
				'id'      => 'redeem_cart_border_color',
				'type'    => 'color',
				'title'   => __( 'Redeem Message - Border color', 'order100' ),
				'default' => '#9CC21D'
			),
			array(
				'id'      => 'redeem_cart_text_color',
				'type'    => 'color',
				'title'   => __( 'Redeem Message - Text color', 'order100' ),
				'default' => '#9CC21D'
			),
			array(
				'id'      => 'redeem_cart_background_color',
				'type'    => 'color',
				'title'   => __( 'Redeem Message - Background color', 'order100' ),
				'default' => '#ffffff'
			),
		)
	),
	'customer_reward_page' => array(
		'title'       => __( 'Customer Reward Page', 'order100' ),
		'description' => __( 'Configure the customer facing rewards dashboard', 'order100' ),
		'fields'      => array(
			array(
				'id'      => 'is_campaign_display',
				'type'    => 'toggle',
				'title'   => __( 'Show Ways to Earn section', 'order100' ),
				'default' => 'yes'
			),
			array(
				'id'      => 'user_display_conditions',
				'type'    => 'multiselect',
				'title'   => __( 'Show the earning opportunities based on conditions', 'order100' ),
				'desc'    => __( 'Filter the earning opportunities based on the selected conditions.', 'order100' ),
				'options' => array( 'user_role' => 'Customer Role', 'user_level' => 'Current Level', 'user_level_with_next_level' => 'Current and next user level' ),
				'default' => array()
			),
			array(
				'id'      => 'is_campaign_level_batch_display',
				'type'    => 'select',
				'title'   => __( 'Display the "Level" icon for rewards assigned to selected levels', 'order100' ),
				'options' => array( 'no' => 'No', 'yes' => 'Yes' ),
				'default' => 'no'
			),
			array(
				'id'      => 'is_campaign_point_display',
				'type'    => 'toggle',
				'title'   => __( 'Display potential points to be earned', 'order100' ),
				'default' => 'yes'
			),
			array(
				'id'      => 'is_reward_display',
				'type'    => 'toggle',
				'title'   => __( 'Show Reward Opportunities section', 'order100' ),
				'default' => 'yes'
			),
			array(
				'id'      => 'is_sent_email_display',
				'type'    => 'toggle',
				'title'   => __( 'Show email opt-in section', 'order100' ),
				'default' => 'yes'
			),
			array(
				'id'      => 'is_transaction_display',
				'type'    => 'toggle',
				'title'   => __( 'Show recent activities section', 'order100' ),
				'default' => 'yes'
			),
			array(
				'id'   => 'crp_branding_heading',
				'type' => 'heading',
				'title'=> __( 'Branding', 'order100' )
			),
			array(
				'id'      => 'redeem_point_icon',
				'type'    => 'image',
				'title   '=> __( 'Icon for Redeemed Points', 'order100' ),
				'desc'    => __( 'Choose an icon / image for the redeemed points section', 'order100' ),
				'default' => O100_URL . 'assets/images/redeem_point_icon.png'
			),
			array(
				'id'      => 'available_point_icon',
				'type'    => 'image',
				'title   '=> __( 'Icon for Available Points', 'order100' ),
				'desc'    => __( 'Choose an icon / image for the available points section', 'order100' ),
				'default' => O100_URL . 'assets/images/available_point_icon.png'
			),
			array(
				'id'      => 'used_reward_icon',
				'type'    => 'image',
				'title   '=> __( 'Icon for Used rewards', 'order100' ),
				'desc'    => __( 'Choose an icon / image for the used rewards section', 'order100' ),
				'default' => O100_URL . 'assets/images/used_reward_icon.png'
			),
			array(
				'id'      => 'theme_color',
				'type'    => 'color',
				'title'   => __( 'Base theme color for customer reward page', 'order100' ),
				'default' => '#4F47EB'
			),
			array(
				'id'      => 'heading_color',
				'type'    => 'color',
				'title'   => __( 'Text color', 'order100' ),
				'default' => '#1D2327'
			),
			array(
				'id'      => 'border_color',
				'type'    => 'color',
				'title'   => __( 'Border color', 'order100' ),
				'default' => '#CFCFCF'
			),
			array(
				'id'      => 'background_color',
				'type'    => 'color',
				'title'   => __( 'Background color', 'order100' ),
				'default' => '#ffffff'
			),
			array(
				'id'      => 'button_text_color',
				'type'    => 'color',
				'title'   => __( 'Button text color', 'order100' ),
				'default' => '#ffffff'
			),
			array(
				'id'      => 'redeem_button_text',
				'type'    => 'text',
				'title'   => __( 'Redeem button text', 'order100' ),
				'default' => 'Redeem Now'
			),
			array(
				'id'      => 'apply_coupon_button_text',
				'type'    => 'text',
				'title'   => __( 'Apply coupon button text', 'order100' ),
				'default' => 'Apply Coupon'
			),
			array(
				'id'      => 'apply_coupon_button_text_color',
				'type'    => 'color',
				'title'   => __( 'Apply coupon button text color', 'order100' ),
				'default' => '#ffffff'
			),
			array(
				'id'      => 'apply_coupon_button_color',
				'type'    => 'color',
				'title'   => __( 'Apply coupon button color', 'order100' ),
				'default' => '#4F47EB'
			),
			array(
				'id'      => 'apply_coupon_border_color',
				'type'    => 'color',
				'title'   => __( 'Coupon code/border color', 'order100' ),
				'default' => '#FF8E3D'
			),
			array(
				'id'      => 'apply_coupon_background',
				'type'    => 'color',
				'title'   => __( 'Coupon code background color', 'order100' ),
				'default' => '#FFF8F3'
			),
			array(
				'id'      => 'redeem_button_text_color',
				'type'    => 'color',
				'title'   => __( 'Redeem button text color', 'order100' ),
				'default' => '#ffffff'
			),
			array(
				'id'      => 'redeem_button_color',
				'type'    => 'color',
				'title'   => __( 'Redeem button color', 'order100' ),
				'default' => '#4F47EB'
			),
		)
	),

	
	'launcher_design' => array(
		'title'       => __( 'Panel Design', 'order100' ),
		'description' => __( 'Configure the colors and logo for the launcher panel.', 'order100' ),
		'fields'      => array(
			array( 'id' => 'ld_logo_heading', 'type' => 'heading', 'title' => 'Logo Settings' ),
			array( 'id' => 'launcher_design_logo_is_show', 'type' => 'toggle', 'title' => 'Show Logo', 'default' => 'yes' ),
			array( 'id' => 'launcher_design_logo_image', 'type' => 'image', 'title' => 'Logo Image' ),
			
			array( 'id' => 'ld_colors_heading', 'type' => 'heading', 'title' => 'Color Settings' ),
			array( 'id' => 'launcher_design_theme_primary', 'type' => 'color', 'title' => 'Primary Theme Color' ),
			array( 'id' => 'launcher_design_theme_secondary', 'type' => 'color', 'title' => 'Secondary Theme Color' ),
			array( 'id' => 'launcher_design_banner_background', 'type' => 'color', 'title' => 'Banner Background' ),
			array( 'id' => 'launcher_design_banner_text', 'type' => 'color', 'title' => 'Banner Text Color' ),
			array( 'id' => 'launcher_design_buttons_text', 'type' => 'color', 'title' => 'Buttons Text Color' ),
			
			array( 'id' => 'la_style_heading', 'type' => 'heading', 'title' => 'Trigger Button Settings' ),
			array( 'id' => 'launcher_widget_appearance', 'type' => 'select', 'title' => 'Button Style', 'options' => ['icon_with_text' => 'Icon with Text', 'text_only' => 'Text Only', 'icon_only' => 'Icon Only'] ),
			array( 'id' => 'launcher_widget_text', 'type' => 'text', 'title' => 'Launcher Text' ),
			array( 'id' => 'launcher_widget_icon_type', 'type' => 'select', 'title' => 'Icon Type', 'options' => ['gift' => 'Gift', 'star' => 'Star', 'trophy' => 'Trophy', 'medal' => 'Medal', 'crown' => 'Crown', 'heart' => 'Heart', 'custom' => 'Upload Icon'] ),
			array( 'id' => 'launcher_widget_custom_icon', 'type' => 'image', 'title' => 'Custom Icon' ),
			array( 'id' => 'launcher_design_launcher_background', 'type' => 'color', 'title' => 'Button Background' ),
			array( 'id' => 'launcher_design_launcher_text', 'type' => 'color', 'title' => 'Button Text Color' ),
			array( 'id' => 'launcher_widget_view_option', 'type' => 'select', 'title' => 'Visibility', 'options' => ['mobile_and_desktop' => 'Mobile & Desktop', 'desktop_only' => 'Desktop Only', 'mobile_only' => 'Mobile Only', 'do_not_show' => 'Do not show'] ),
			array( 'id' => 'launcher_widget_position', 'type' => 'select', 'title' => 'Position', 'options' => ['right' => 'Bottom Right', 'left' => 'Bottom Left'] ),
			array( 'id' => 'launcher_widget_side_spacing', 'type' => 'text', 'title' => 'Side Spacing (px)' ),
			array( 'id' => 'launcher_widget_bottom_spacing', 'type' => 'text', 'title' => 'Bottom Spacing (px)' ),
		)
	),
	'launcher_content' => array(
		'title'       => __( 'Launcher Content', 'order100' ),
		'description' => __( 'Configure the text displayed inside the launcher panel.', 'order100' ),
		'fields'      => array(
			array( 'id' => 'gr_guest_heading', 'type' => 'heading', 'title' => 'Guest View (Not Logged In)' ),
			array( 'id' => 'launcher_content_guest_welcome_title', 'type' => 'text', 'title' => 'Welcome Title' ),
			array( 'id' => 'launcher_content_guest_welcome_desc', 'type' => 'textarea', 'title' => 'Welcome Description' ),
			array( 'id' => 'launcher_content_guest_have_account', 'type' => 'text', 'title' => 'Have Account Text' ),
			array( 'id' => 'launcher_content_guest_signin_text', 'type' => 'text', 'title' => 'Sign-in Link Text' ),
			array( 'id' => 'launcher_content_guest_signin_url', 'type' => 'text', 'title' => 'Sign-in URL' ),
			array( 'id' => 'launcher_content_guest_button_text', 'type' => 'text', 'title' => 'Join Button Text' ),
			array( 'id' => 'launcher_content_guest_button_url', 'type' => 'text', 'title' => 'Join Button URL' ),
			array( 'id' => 'launcher_content_guest_earn_title', 'type' => 'text', 'title' => 'Earn Points Title' ),
			array( 'id' => 'launcher_content_guest_redeem_title', 'type' => 'text', 'title' => 'Redeem Points Title' ),
			array( 'id' => 'launcher_content_guest_referrals_title', 'type' => 'text', 'title' => 'Referrals Title' ),
			array( 'id' => 'launcher_content_guest_referrals_desc', 'type' => 'textarea', 'title' => 'Referrals Description' ),
			array( 'id' => 'guest_shortcodes_info', 'type' => 'html', 'title' => 'Available Shortcodes', 'html' => '<details style="background:#f8fafc; padding:10px 15px; border-radius:8px; border:1px solid #e2e8f0; font-size:13px; color:#475569; cursor:pointer;">
				<summary style="font-weight:600; outline:none;">View Available Shortcodes</summary>
				<div style="margin-top:10px; padding-top:10px; border-top:1px solid #e2e8f0; cursor:text;">
					<p style="margin-top:0;"><strong>{o100_signin_url}</strong> : Automatically outputs the WooCommerce login URL.</p>
					<p style="margin-bottom:0;"><strong>{o100_signup_url}</strong> : Automatically outputs the WooCommerce registration URL.</p>
				</div>
			</details>' ),

			array( 'id' => 'gr_member_heading', 'type' => 'heading', 'title' => 'Member View (Logged In)' ),
			array( 'id' => 'launcher_content_member_welcome', 'type' => 'text', 'title' => 'Welcome Message' ),
			array( 'id' => 'launcher_content_member_points_label', 'type' => 'text', 'title' => 'Points Label' ),
			array( 'id' => 'launcher_content_member_earn_title', 'type' => 'text', 'title' => 'Earn Points Title' ),
			array( 'id' => 'launcher_content_member_redeem_title', 'type' => 'text', 'title' => 'Redeem Points Title' ),
			array( 'id' => 'launcher_content_member_referrals_title', 'type' => 'text', 'title' => 'Referrals Title' ),
			array( 'id' => 'launcher_content_member_referrals_desc', 'type' => 'textarea', 'title' => 'Referrals Description' ),
			array( 'id' => 'launcher_content_member_referrals_channels', 'type' => 'multiselect_checkbox', 'title' => 'Social Share Channels', 'options' => ['facebook' => 'Facebook', 'twitter' => 'Twitter/X', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'linkedin' => 'LinkedIn', 'telegram' => 'Telegram'], 'default' => ['facebook', 'twitter', 'whatsapp', 'email'] ),
			array( 'id' => 'member_shortcodes_info', 'type' => 'html', 'title' => 'Available Shortcodes', 'html' => '<details style="background:#f8fafc; padding:10px 15px; border-radius:8px; border:1px solid #e2e8f0; font-size:13px; color:#475569; cursor:pointer;">
				<summary style="font-weight:600; outline:none;">View Available Shortcodes</summary>
				<div style="margin-top:10px; padding-top:10px; border-top:1px solid #e2e8f0; cursor:text;">
					<p style="margin-top:0;"><strong>{o100_user_name}</strong> : Displays the logged-in user\'s display name.</p>
					<p><strong>{o100_point_label}</strong> : Outputs the configured points label (e.g., Points).</p>
					<p><strong>{o100_referral_friend_point_percentage}</strong> : Displays points percentage for friends configured in referral campaign.</p>
					<p><strong>{o100_referral_friend_reward}</strong> : Displays any direct coupon reward for friends.</p>
					<p><strong>{o100_referral_advocate_point_percentage}</strong> : Displays points percentage for advocate.</p>
					<p style="margin-bottom:0;"><strong>{o100_referral_advocate_reward}</strong> : Displays direct coupon for advocate.</p>
				</div>
			</details>' ),
		)

	),
	'referral_settings' => array(
		'title'       => __( 'Referral Settings', 'order100' ),
		'description' => __( 'Select the referral campaign to use for Guest Referrals.', 'order100' ),
		'fields'      => array(
			array( 'id' => 'referral_campaign_id', 'type' => 'select', 'title' => 'Select Referral Campaign', 'options' => $referral_campaigns ),
		)
	),
	'referral_popup' => array(
		'title'       => __( 'Referral Popup Template', 'order100' ),
		'description' => __( 'Configure the email collection popup.', 'order100' ),
		'fields'      => array(
			array( 'id' => 'referral_popup_background_color', 'type' => 'color', 'title' => 'Popup Background Color', 'default' => '#FFFFFF' ),
			array( 'id' => 'referral_popup_enable_image_content', 'type' => 'toggle', 'title' => 'Enable Image Content', 'default' => 'yes' ),
			array( 'id' => 'referral_popup_popup_image', 'type' => 'image', 'title' => 'Popup Image URL' ),
			array( 'id' => 'referral_popup_title_content', 'type' => 'text', 'title' => 'Title Text' ),
			array( 'id' => 'referral_popup_title_text_color', 'type' => 'color', 'title' => 'Title Color' ),
			array( 'id' => 'referral_popup_enable_sub_title_content', 'type' => 'toggle', 'title' => 'Enable Subtitle', 'default' => 'yes' ),
			array( 'id' => 'referral_popup_sub_title_content', 'type' => 'text', 'title' => 'Subtitle Text' ),
			array( 'id' => 'referral_popup_sub_title_text_color', 'type' => 'color', 'title' => 'Subtitle Color' ),
			array( 'id' => 'referral_popup_enable_prompt_content', 'type' => 'toggle', 'title' => 'Enable Prompt Message', 'default' => 'yes' ),
			array( 'id' => 'referral_popup_prompt_message_content', 'type' => 'textarea', 'title' => 'Prompt Message' ),
			array( 'id' => 'referral_popup_place_holder_content', 'type' => 'text', 'title' => 'Email Field Placeholder' ),
			array( 'id' => 'referral_popup_claim_button_text', 'type' => 'text', 'title' => 'Claim Button Text' ),
			array( 'id' => 'referral_popup_claim_button_background_color', 'type' => 'color', 'title' => 'Claim Button Color' ),
			array( 'id' => 'referral_popup_claim_button_text_color', 'type' => 'color', 'title' => 'Claim Button Text Color' ),
			array( 'id' => 'referral_popup_no_thanks_text', 'type' => 'text', 'title' => 'No Thanks Text' ),
		)
	),
	'referral_coupon' => array(
		'title'       => __( 'Referral Coupon Template', 'order100' ),
		'description' => __( 'Configure the coupon code display popup.', 'order100' ),
		'fields'      => array(
			array( 'id' => 'referral_coupon_background_color', 'type' => 'color', 'title' => 'Popup Background Color', 'default' => '#FFFFFF' ),
			array( 'id' => 'referral_coupon_enable_image_content', 'type' => 'toggle', 'title' => 'Enable Image Content', 'default' => 'yes' ),
			array( 'id' => 'referral_coupon_popup_image', 'type' => 'image', 'title' => 'Popup Image URL' ),
			array( 'id' => 'referral_coupon_title_content', 'type' => 'text', 'title' => 'Title Text' ),
			array( 'id' => 'referral_coupon_title_text_color', 'type' => 'color', 'title' => 'Title Color' ),
			array( 'id' => 'referral_coupon_enable_sub_title_content', 'type' => 'toggle', 'title' => 'Enable Subtitle', 'default' => 'yes' ),
			array( 'id' => 'referral_coupon_sub_title_content', 'type' => 'text', 'title' => 'Subtitle Text' ),
			array( 'id' => 'referral_coupon_sub_title_text_color', 'type' => 'color', 'title' => 'Subtitle Color' ),
			array( 'id' => 'referral_coupon_coupon_color', 'type' => 'color', 'title' => 'Coupon Text Color' ),
			array( 'id' => 'referral_coupon_background_coupon_color', 'type' => 'color', 'title' => 'Coupon Background Color' ),
			array( 'id' => 'referral_coupon_shop_button_text', 'type' => 'text', 'title' => 'Shop Button Text' ),
			array( 'id' => 'referral_coupon_shop_button_background_color', 'type' => 'color', 'title' => 'Shop Button Color' ),
			array( 'id' => 'referral_coupon_shop_button_text_color', 'type' => 'color', 'title' => 'Shop Button Text Color' ),
		)
	)

);


// Function to render a single field
if ( ! function_exists( 'o100_loyalty_render_setting_field' ) ) {
	function o100_loyalty_render_setting_field( $field, $settings ) {
		static $current_heading_id = '';
		
		$id      = esc_attr( $field['id'] ?? '' );
		$title   = isset( $field['title'] ) ? esc_html( $field['title'] ) : esc_html( $field['title   '] ?? '' );
		$desc    = isset( $field['desc'] ) ? $field['desc'] : '';
		$default = isset( $field['default'] ) ? $field['default'] : '';
		
		// Get current value. DB keys do not have the o100_ prefix
		$db_key = str_replace( 'o100_', '', $id );
		$value = isset( $settings[ $db_key ] ) ? $settings[ $db_key ] : $default;

		// Handle headings natively bypassing standard th/td layout
		if ( $field['type'] === 'heading' ) {
			$current_heading_id = 'group-' . $id;
			echo '<tr><td colspan="2" style="height:20px; border:none; padding:0;"></td></tr>';
			echo '<tr class="o100-heading-row" data-group-target="' . esc_attr($current_heading_id) . '" style="cursor:pointer;"><td colspan="2" style="padding: 16px 20px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 10px;">';
			echo '<h3 style="margin: 0; font-size: 16px; color: #1e293b; font-weight:600; display:flex; align-items:center; justify-content:space-between;">';
			echo '<div style="display:flex; align-items:center;"><span class="dashicons dashicons-admin-generic" style="margin-right:8px; color:#64748b;"></span>' . $title . '</div>';
			echo '<span class="dashicons dashicons-arrow-up-alt2 o100-heading-toggle-icon" style="color:#64748b;"></span>';
			echo '</h3>';
			if ( $desc ) {
				echo '<p class="description" style="margin-top: 6px; margin-bottom: 0; font-size:13px; color:#64748b;">' . wp_kses_post( $desc ) . '</p>';
			}
			echo '</td></tr>';
			return;
		}

		$group_class = $current_heading_id ? ' class="' . esc_attr($current_heading_id) . '"' : '';
		echo '<tr' . $group_class . '>';
		echo '<th scope="row"><label for="' . $id . '">' . $title . '</label></th>';
		echo '<td>';
		
		switch ( $field['type'] ) {
			case 'text':
				echo '<input type="text" id="' . $id . '" name="' . $id . '" value="' . esc_attr( $value ) . '" class="regular-text">';
				break;
			case 'select':
				echo '<select id="' . $id . '" name="' . $id . '" class="regular-text" style="padding:4px; max-width:25em;">';
				foreach ( $field['options'] as $opt_val => $opt_label ) {
					echo '<option value="' . esc_attr( $opt_val ) . '" ' . selected( $value, $opt_val, false ) . '>' . esc_html( $opt_label ) . '</option>';
				}
				echo '</select>';
				break;
			case 'multiselect':
				$value_arr = (array) $value;
				echo '<select id="' . $id . '" name="' . $id . '[]" class="regular-text" multiple="multiple" style="padding:4px; max-width:25em; height:auto; min-height:80px;">';
				foreach ( $field['options'] as $opt_val => $opt_label ) {
					$opt_val_clean = str_replace( 'wc-', '', $opt_val );
					echo '<option value="' . esc_attr( $opt_val_clean ) . '" ' . selected( in_array( $opt_val_clean, $value_arr ), true, false ) . '>' . esc_html( $opt_label ) . '</option>';
				}
				echo '</select>';
				break;
			case 'multiselect_checkbox':
				$value_arr = (array) $value;
				echo '<div class="o100-multiselect-checkbox-dropdown" style="position:relative; max-width:25em;">';
				
				// Calculate how many selected for the header text
				$selected_count = count($value_arr);
				$header_text = $selected_count > 0 ? $selected_count . ' channel(s) selected' : 'Select channels...';
				
				echo '<div class="o100-multiselect-header" style="border:2px solid #e2e8f0; border-radius:8px; padding:8px 12px; background:#fff; cursor:pointer; display:flex; justify-content:space-between; align-items:center;" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === \'none\' ? \'block\' : \'none\';">';
				echo '<span class="o100-multiselect-header-text" style="font-size:13px; color:#475569; font-weight:600;">' . esc_html($header_text) . '</span>';
				echo '<span class="dashicons dashicons-arrow-down-alt2" style="font-size:16px; color:#94a3b8; width:16px; height:16px;"></span>';
				echo '</div>';
				echo '<div class="o100-multiselect-options" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:100; margin-top:4px; max-height:200px; overflow-y:auto;">';
				foreach ( $field['options'] as $opt_val => $opt_label ) {
					$opt_val_clean = str_replace( 'wc-', '', $opt_val );
					$is_checked = in_array( $opt_val_clean, $value_arr ) ? 'checked' : '';
					echo '<label style="display:flex; align-items:center; margin-bottom:8px; cursor:pointer; font-size:13px; color:#1e293b;">';
					echo '<input type="checkbox" id="' . $id . '_' . esc_attr($opt_val_clean) . '" name="' . $id . '[]" value="' . esc_attr( $opt_val_clean ) . '" ' . $is_checked . ' style="margin-right:8px; margin-top:0;" onchange="
						var container = this.closest(\'.o100-multiselect-checkbox-dropdown\');
						var checkedCount = container.querySelectorAll(\'input[type=checkbox]:checked\').length;
						container.querySelector(\'.o100-multiselect-header-text\').innerText = checkedCount > 0 ? checkedCount + \' channel(s) selected\' : \'Select channels...\';
					">';
					echo esc_html( $opt_label );
					echo '</label>';
				}
				echo '</div>';
				echo '</div>';
				// Make clicking outside close it
				echo '<script>
					document.addEventListener("click", function(e) {
						var dropdowns = document.querySelectorAll(".o100-multiselect-checkbox-dropdown");
						dropdowns.forEach(function(d) {
							if (!d.contains(e.target)) {
								d.querySelector(".o100-multiselect-options").style.display = "none";
							}
						});
					});
				</script>';
				break;
			case 'textarea':
				echo '<textarea id="' . $id . '" name="' . $id . '" rows="4" class="large-text" style="width:100%">' . esc_textarea( $value ) . '</textarea>';
				break;
			case 'image':
				echo '<div class="o100-image-upload-wrapper" style="display:flex; flex-direction:column; align-items:flex-start;">';
				echo '<input type="hidden" id="' . $id . '" name="' . $id . '" value="' . esc_attr( $value ) . '" class="o100-image-url-input">';
				echo '<div class="o100-image-preview" style="width:64px; height:64px; border:2px dashed #cbd5e1; border-radius:8px; margin-bottom:10px; display:flex; align-items:center; justify-content:center; overflow:hidden; background:#f8fafc;">';
				if ( $value ) {
					echo '<img src="' . esc_url( $value ) . '" style="max-width:100%; max-height:100%;">';
				} else {
					echo '<span style="color:#94a3b8; font-size:24px;">🖼️</span>';
				}
				echo '</div>';
				echo '<button type="button" class="button o100-upload-image-btn">Choose an image</button>';
				echo '</div>';
				break;
			case 'toggle':
				$is_checked = ( $value === 'yes' || $value === true );
				$checked_attr = $is_checked ? 'checked="checked"' : '';
				$label_text = $is_checked ? 'Visible' : 'Hidden';
				$bg_color = $is_checked ? '#2271b1' : '#cbd5e1';
				$handle_pos = $is_checked ? '23px' : '3px';
				
				echo '<div class="o100-toggle-wrapper" style="display:flex; align-items:center;">';
				echo '<span class="o100-toggle-label" style="margin-right:12px; font-size:14px; color:#475569; min-width:55px; text-align:right;">' . esc_html($label_text) . '</span>';
				echo '<label class="o100-toggle-switch" style="position:relative; display:inline-block; width:44px; height:24px; margin:0; cursor:pointer;">';
				echo '<input type="checkbox" id="' . $id . '" name="' . $id . '" value="yes" ' . $checked_attr . ' style="opacity:0; width:0; height:0; position:absolute;">';
				echo '<span class="o100-slider round" style="position:absolute; top:0; left:0; right:0; bottom:0; background-color:' . $bg_color . '; transition:.3s; border-radius:34px;">';
				echo '<span class="o100-slider-handle" style="position:absolute; content:\'\'; height:18px; width:18px; left:' . $handle_pos . '; bottom:3px; background-color:white; transition:.3s; border-radius:50%; box-shadow:0 1px 2px rgba(0,0,0,0.2);"></span>';
				echo '</span>';
				echo '<script>
					document.getElementById("' . $id . '").addEventListener("change", function(){ 
						var slider = this.nextElementSibling;
						var handle = slider.querySelector(".o100-slider-handle");
						var label = this.closest(".o100-toggle-wrapper").querySelector(".o100-toggle-label");
						if(this.checked) {
							slider.style.backgroundColor = "#2271b1";
							handle.style.left = "23px";
							label.textContent = "Visible";
						} else {
							slider.style.backgroundColor = "#cbd5e1";
							handle.style.left = "3px";
							label.textContent = "Hidden";
						}
					});
				</script>';
				echo '</label>';
				echo '</div>';
				break;
			case 'color':
				echo '<input type="color" id="' . $id . '" name="' . $id . '" value="' . esc_attr( $value ) . '">';
				break;
			case 'html':
				echo isset($field['html']) ? $field['html'] : '';
				break;
		}

		if ( $desc ) {
			echo '<p class="description">' . wp_kses_post( $desc ) . '</p>';
		}
		
		echo '</td>';
		echo '</tr>';
	}
}
?>
<div class="o100-loyalty-settings">
	<div id="o100-loyalty-settings-form" class="o100-loyalty-form">
		<?php wp_nonce_field( 'o100_apps_nonce', 'o100_nonce' ); ?>
		<input type="hidden" name="option_key" value="o100_settings">

		<div class="o100-loyalty-settings-tabs-wrapper" style="border-bottom: 1px solid #e2e8f0; margin-bottom: 24px;">
			<ul class="o100-loyalty-settings-tabs" style="display: flex; margin: 0; padding: 0; list-style: none;">
				<li class="o100-settings-tab active" data-tab="tab-earn_point" style="margin-right: 20px; padding: 12px 0; cursor: pointer; color: #2271b1; border-bottom: 2px solid #2271b1; font-weight: 500;">General Settings</li>
				<li class="o100-settings-tab" data-tab="tab-display_messages" style="margin-right: 20px; padding: 12px 0; cursor: pointer; color: #64748b; font-weight: 500;">Display Messages</li>
				<li class="o100-settings-tab" data-tab="tab-customer_reward_page" style="margin-right: 20px; padding: 12px 0; cursor: pointer; color: #64748b; font-weight: 500;">Customer Reward Page</li>
				<li class="o100-settings-tab" data-tab="tab-launcher" style="margin-right: 20px; padding: 12px 0; cursor: pointer; color: #64748b; font-weight: 500;">Rewards Widget</li>
				<li class="o100-settings-tab" data-tab="tab-guest_referral" style="margin-right: 20px; padding: 12px 0; cursor: pointer; color: #64748b; font-weight: 500;">Guest Referral</li>

			</ul>
		</div>

		
		<div class="o100-loyalty-sub-tabs-wrapper-launcher" style="display:none; align-items:center; justify-content:space-between; margin-bottom:20px;">
			<div style="display:flex; gap:10px;">
				<div class="o100-sub-tab o100-sub-tab-launcher active" data-sub-tab="launcher_design" style="padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:500; background:#e0e7ff; color:#4338ca;">Panel Design</div>
				<div class="o100-sub-tab o100-sub-tab-launcher" data-sub-tab="launcher_content" style="padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:500; background:#f1f5f9; color:#64748b;">Panel Content</div>
			</div>
			<div style="display:flex; gap:10px;">
				<button type="button" class="button o100-launcher-reset-btn" style="border-radius:6px; padding:4px 16px;">Reset Defaults</button>
				<button type="button" class="button button-primary o100-launcher-save-btn" style="border-radius:6px; padding:4px 16px; background:#4338ca; border-color:#4338ca;">Save Widget Settings</button>
			</div>
		</div>

		<div class="o100-loyalty-sub-tabs-wrapper-referral" style="display:none; gap:10px; margin-bottom:20px;">
			<div class="o100-sub-tab o100-sub-tab-referral active" data-sub-tab="referral_settings" style="padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:500; background:#e0e7ff; color:#4338ca;">Settings</div>
			<div class="o100-sub-tab o100-sub-tab-referral" data-sub-tab="referral_popup" style="padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:500; background:#f1f5f9; color:#64748b;">Popup Template</div>
			<div class="o100-sub-tab o100-sub-tab-referral" data-sub-tab="referral_coupon" style="padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:500; background:#f1f5f9; color:#64748b;">Coupon Template</div>
		</div>
		<div class="o100-loyalty-tab-contents">
			<div class="o100-launcher-flex-container" style="display:none; gap:30px; align-items:flex-start;">
				<div class="o100-launcher-form-column" style="flex:1; max-height:75vh; overflow-y:auto; padding-right:15px;"></div>
				<div class="o100-launcher-preview-column" style="width:380px; position:sticky; top:40px;">
					<div id="o100-widget-preview" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; height:550px; display:flex; flex-direction:column; justify-content:flex-end; position:relative; overflow:hidden;">
                        <div style="position:absolute; top:10px; right:10px; display:flex; align-items:center; gap:10px;">
							<div class="preview-mode-toggle" style="background:#e2e8f0; border-radius:20px; display:flex; overflow:hidden; font-size:12px; font-weight:600; cursor:pointer;">
								<div class="preview-toggle-btn active" data-mode="guest" style="padding:4px 12px; background:#4F47EB; color:#FFF;">Guest</div>
								<div class="preview-toggle-btn" data-mode="member" style="padding:4px 12px; color:#475569;">Member</div>
							</div>
							<span style="color:#94a3b8; font-size:12px; font-weight:600;">LIVE PREVIEW</span>
						</div>
                        <div class="preview-panel preview-main-panel" style="background:#fff; border-radius:16px; box-shadow:0 10px 25px rgba(0,0,0,0.1); overflow:hidden; margin-bottom:20px; transform-origin:bottom right;">
                            <div class="preview-header" style="background:#4F47EB; color:#FFF; padding:25px 20px; text-align:center; position:relative;">
                                <div class="preview-close" style="position:absolute; right:15px; top:15px; width:24px; height:24px; border-radius:50%; background:rgba(0,0,0,0.2); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:12px;">✕</div>
                                <div class="preview-logo-wrapper" style="width:40px; height:40px; background:#fff; border-radius:8px; margin:0 auto 10px auto; display:none; align-items:center; justify-content:center; overflow:hidden;"><img src="" class="preview-logo-img" style="max-width:100%; max-height:100%;"></div>
                                <h3 class="preview-welcome-title" style="margin:0 0 8px 0; color:inherit; font-size:20px; font-weight:700;">Welcome</h3>
                                <p class="preview-welcome-desc" style="margin:0; opacity:0.9; font-size:14px;">Sign up to earn points</p>
                            </div>
                            <div class="preview-body" style="padding:20px; background:#F5F5F5;">
                                <div class="preview-earn-card" style="background:#fff; border-radius:8px; padding:15px; display:flex; align-items:center; gap:15px; margin-bottom:15px; box-shadow:0 2px 5px rgba(0,0,0,0.02); position:relative; cursor:pointer;">
                                    <div class="preview-icons" style="color:#4F47EB; background:#e0e7ff; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center;"><span class="dashicons dashicons-money-alt"></span></div>
                                    <div style="flex:1;">
                                        <div class="preview-earn-title" style="font-weight:600; font-size:15px; margin-bottom:2px;">Earn Points</div>
                                        <div class="preview-earn-desc" style="color:#64748b; font-size:12px;">Complete tasks to earn points</div>
                                    </div>
                                    <div style="color:#94a3b8; font-size:18px; font-weight:bold;">›</div>
                                </div>
                                <div class="preview-redeem-card" style="background:#fff; border-radius:8px; padding:15px; display:flex; align-items:center; gap:15px; box-shadow:0 2px 5px rgba(0,0,0,0.02); position:relative; cursor:pointer;">
                                    <div class="preview-icons" style="color:#4F47EB; background:#e0e7ff; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center;"><span class="dashicons dashicons-tickets-alt"></span></div>
                                    <div style="flex:1;">
                                        <div class="preview-redeem-title" style="font-weight:600; font-size:15px; margin-bottom:2px;">Redeem Points</div>
                                        <div class="preview-redeem-desc" style="color:#64748b; font-size:12px;">Use points for discounts</div>
                                    </div>
                                    <div style="color:#94a3b8; font-size:18px; font-weight:bold;">›</div>
                                </div>
								<div class="preview-referral-card" style="display:none; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:15px; margin-top:15px;">
									<div class="preview-referral-title" style="font-weight:600; font-size:14px; margin-bottom:5px;">Refer and earn</div>
									<div class="preview-referral-desc" style="color:#64748b; font-size:12px; margin-bottom:12px;">Refer your friends and earn rewards. Your friend can get a reward as well!</div>
									<div style="display:flex; border:1px solid #cbd5e1; border-radius:6px; overflow:hidden; margin-bottom:12px; background:#fff;">
										<input type="text" value="http://localhost:10004?o100_ref=dummy" readonly style="flex:1; border:none; padding:8px 10px; font-size:12px; color:#475569; outline:none; background:transparent;">
										<div class="preview-referral-copy" style="background:#f59e0b; color:#fff; width:36px; display:flex; align-items:center; justify-content:center; cursor:pointer;">
											<span class="dashicons dashicons-admin-page" style="font-size:16px; width:16px; height:16px;"></span>
										</div>
									</div>
									<div class="preview-referral-socials" style="display:flex; justify-content:center; gap:12px; color:#f59e0b;">
										<span class="dashicons dashicons-facebook-alt" style="cursor:pointer;"></span>
										<span class="dashicons dashicons-twitter" style="cursor:pointer;"></span>
										<span class="dashicons dashicons-whatsapp" style="cursor:pointer;"></span>
										<span class="dashicons dashicons-email-alt" style="cursor:pointer;"></span>
									</div>
								</div>
                            </div>
                            <div class="preview-footer" style="padding:20px; text-align:center; border-top:1px solid #eee;">
                                <button class="preview-button" style="background:#4F47EB; color:#FFF; border:none; padding:12px 20px; border-radius:6px; font-weight:600; width:100%; cursor:pointer; font-size:14px;">Join Now</button>
                                <div class="preview-signin-wrap" style="margin-top:15px; font-size:13px; color:#64748b;">
                                    <span class="preview-have-account">Already have an account?</span> <a href="#" class="preview-link" style="color:#4F47EB; text-decoration:none; font-weight:600;">Sign in</a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Secondary Panel -->
						<div class="preview-panel preview-secondary-panel" style="display:none; background:#fff; border-radius:16px; box-shadow:0 10px 25px rgba(0,0,0,0.1); overflow:hidden; margin-bottom:20px; flex-direction:column; min-height:400px;">
                            <div class="preview-header" style="background:#4F47EB; color:#FFF; padding:20px; display:flex; align-items:center; justify-content:space-between; position:relative;">
                                <div class="preview-back-btn" style="cursor:pointer; font-size:18px; line-height:1; display:flex; align-items:center; padding-right:10px;">←</div>
                                <div class="preview-secondary-title" style="font-weight:700; font-size:16px; flex:1; text-align:center;">Earn</div>
                                <div class="preview-close" style="width:24px; height:24px; border-radius:50%; background:rgba(0,0,0,0.2); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:12px;">✕</div>
                            </div>
                            <div class="preview-secondary-body" style="flex:1; padding:20px; background:#F5F5F5; overflow-y:auto;">
                                <!-- Dynamically populated by JS -->
                            </div>
						</div>

                        <div class="preview-launcher" style="background:#4F47EB; color:#FFF; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; align-self:flex-end; box-shadow:0 4px 12px rgba(0,0,0,0.15); cursor:pointer;">
                            <span class="dashicons dashicons-star-filled" style="font-size:24px; width:24px; height:24px;"></span>
                        </div>
                    </div>
				</div>
			</div>

						<?php foreach ( $settings_schema as $section_id => $section ) : 
				$display = ( $section_id === 'earn_point' ) ? 'block' : 'none';
				
				// Map sub-tabs to main tabs
				$tab_class = 'tab-' . $section_id;
				if ( $section_id === 'branding' ) $tab_class = 'tab-display_messages';
				if ( strpos($section_id, 'launcher_') === 0 ) $tab_class = 'tab-launcher';
				if ( strpos($section_id, 'referral_') === 0 ) $tab_class = 'tab-guest_referral';

				// Determine sub-tab active state
				$sub_tab_display = 'none';
				if ( $section_id === 'launcher_design' || $section_id === 'referral_settings' || !in_array($tab_class, ['tab-launcher', 'tab-guest_referral']) ) {
					$sub_tab_display = 'block';
				}
			?>
				<div class="o100-loyalty-settings-section <?php echo esc_attr($tab_class); ?> sub-tab-<?php echo esc_attr($section_id); ?>" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:20px; margin-bottom:20px; display: <?php echo $section_id==='earn_point'?'block':'none'; ?>;">
					<h3 style="margin:0 0 10px 0; font-size:16px; border-bottom:1px solid #eee; padding-bottom:10px;"><?php echo esc_html( $section['title'] ); ?></h3>
					<?php if ( isset( $section['description'] ) ) : ?>
						<p class="section-desc" style="color:#6b7280; font-size:13px; margin-bottom:20px;"><?php echo esc_html( $section['description'] ); ?></p>
					<?php endif; ?>

					<table class="form-table o100-loyalty-styled-table">
						<tbody>
							<?php foreach ( $section['fields'] as $field ) : ?>
								<?php o100_loyalty_render_setting_field( $field, $settings ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
		</div>

		
		<script>
		// Vanilla JS for simple tab switching
		document.addEventListener('DOMContentLoaded', function() {
			var tabs = document.querySelectorAll('.o100-settings-tab');
			tabs.forEach(function(tab) {
				tab.addEventListener('click', function() {
					tabs.forEach(function(t) { 
						t.classList.remove('active'); 
						t.style.color = '#64748b'; 
						t.style.borderBottom = 'none'; 
					});
					this.classList.add('active');
					this.style.color = '#2271b1';
					this.style.borderBottom = '2px solid #2271b1';

					var targetTab = this.getAttribute('data-tab');
					var allSections = document.querySelectorAll('.o100-loyalty-settings-section');
					allSections.forEach(function(sec) {
						sec.style.display = 'none';
					});

					// Show the target main tab sections
					var targetSections = document.querySelectorAll('.' + targetTab);
					var mainSaveBtn = document.querySelector('.o100-loyalty-form-actions');
					
					try {
						var flexContainer = document.querySelector('.o100-launcher-flex-container');
						// If it has sub-tabs (launcher or referral), show only the active sub-tab
						if (targetTab === 'tab-launcher') {
							if (mainSaveBtn) mainSaveBtn.style.display = 'none';
							document.querySelector('.o100-loyalty-sub-tabs-wrapper-launcher').style.display = 'flex';
							document.querySelector('.o100-loyalty-sub-tabs-wrapper-referral').style.display = 'none';
							
							if (flexContainer) {
								flexContainer.style.display = 'flex';
								var formCol = document.querySelector('.o100-launcher-form-column');
								document.querySelectorAll('.tab-launcher').forEach(function(sec) {
									formCol.appendChild(sec);
								});
							}
							
							// Find active sub-tab
							var activeSub = document.querySelector('.o100-sub-tab-launcher.active').getAttribute('data-sub-tab');
							var targetSec = document.querySelector('.sub-tab-' + activeSub);
							if (targetSec) {
								targetSec.style.display = 'block';
								targetSec.style.opacity = '1';
								targetSec.style.visibility = 'visible';
							} else {
								console.error("Could not find section: .sub-tab-" + activeSub);
							}
						} else if (targetTab === 'tab-guest_referral') {
							if (mainSaveBtn) mainSaveBtn.style.display = 'flex';
							if (flexContainer) flexContainer.style.display = 'none';
							document.querySelector('.o100-loyalty-sub-tabs-wrapper-launcher').style.display = 'none';
							document.querySelector('.o100-loyalty-sub-tabs-wrapper-referral').style.display = 'flex';
							
							var activeSub = document.querySelector('.o100-sub-tab-referral.active').getAttribute('data-sub-tab');
							var targetSec = document.querySelector('.sub-tab-' + activeSub);
							if (targetSec) {
								targetSec.style.display = 'block';
								targetSec.style.opacity = '1';
								targetSec.style.visibility = 'visible';
							}
							var previewLauncher = document.querySelector('.preview-launcher');
							if (previewLauncher) previewLauncher.style.display = 'none';
						} else {
							if (mainSaveBtn) mainSaveBtn.style.display = 'flex';
							if (flexContainer) flexContainer.style.display = 'none';
							document.querySelector('.o100-loyalty-sub-tabs-wrapper-launcher').style.display = 'none';
							document.querySelector('.o100-loyalty-sub-tabs-wrapper-referral').style.display = 'none';
							targetSections.forEach(function(sec) {
								sec.style.display = 'block';
							});
						}
					} catch (e) {
						console.error("Tab switching error:", e);
					}
				});
			});

			// Sub-tab switching
			var subTabs = document.querySelectorAll('.o100-sub-tab');
			subTabs.forEach(function(tab) {
				tab.addEventListener('click', function() {
					var group = this.classList.contains('o100-sub-tab-launcher') ? 'launcher' : 'referral';
					
					document.querySelectorAll('.o100-sub-tab-' + group).forEach(function(t) {
						t.classList.remove('active');
						t.style.background = '#f1f5f9';
						t.style.color = '#64748b';
					});
					this.classList.add('active');
					this.style.background = '#e0e7ff';
					this.style.color = '#4338ca';

					var targetSubTab = this.getAttribute('data-sub-tab');
					var parentClass = 'tab-' + (group === 'launcher' ? 'launcher' : 'guest_referral');
					
					document.querySelectorAll('.' + parentClass).forEach(function(sec) {
						sec.style.display = 'none';
					});
					
					var targetSec = document.querySelector('.sub-tab-' + targetSubTab);
					if (targetSec) {
						targetSec.style.display = 'block';
						targetSec.style.opacity = '1';
						targetSec.style.visibility = 'visible';
					}
				});
			});

			// Collapsible Headings Logic
			document.querySelectorAll('.o100-heading-row').forEach(function(row) {
				row.addEventListener('click', function() {
					var groupClass = this.getAttribute('data-group-target');
					var icon = this.querySelector('.o100-heading-toggle-icon');
					if (!groupClass || !icon) return;
					
					var rows = document.querySelectorAll('tr.' + groupClass);
					var isHidden = false;
					if (rows.length > 0) {
						isHidden = (rows[0].style.display === 'none');
					}
					
					rows.forEach(function(r) {
						r.style.display = isHidden ? 'table-row' : 'none';
					});
					
					if (isHidden) {
						icon.classList.remove('dashicons-arrow-down-alt2');
						icon.classList.add('dashicons-arrow-up-alt2');
					} else {
						icon.classList.remove('dashicons-arrow-up-alt2');
						icon.classList.add('dashicons-arrow-down-alt2');
					}
				});
			});

			// Secondary Panel Logic
			var mainPanel = document.querySelector('.preview-main-panel');
			var secPanel = document.querySelector('.preview-secondary-panel');
			var secTitle = document.querySelector('.preview-secondary-title');
			var secBody = document.querySelector('.preview-secondary-body');
			var secBackBtn = document.querySelector('.preview-back-btn');
			
			var earnCard = document.querySelector('.preview-earn-card');
			var redeemCard = document.querySelector('.preview-redeem-card');
			
			if (earnCard && redeemCard && mainPanel && secPanel && secBackBtn) {
				var dummyEarnHTML = `
					<div style="background:#fff; border-radius:8px; padding:15px; margin-bottom:10px; display:flex; align-items:center; gap:15px; box-shadow:0 2px 5px rgba(0,0,0,0.02);">
						<div style="color:#f59e0b; font-size:24px;"><span class="dashicons dashicons-cart"></span></div>
						<div style="flex:1;">
							<div style="font-weight:600; font-size:14px; margin-bottom:2px;">Point for purchase</div>
							<div style="color:#64748b; font-size:12px;">+10 Points for every $1 spent</div>
						</div>
						<div style="color:#f59e0b; font-size:18px;">›</div>
					</div>
					<div style="background:#fff; border-radius:8px; padding:15px; margin-bottom:10px; display:flex; align-items:center; gap:15px; box-shadow:0 2px 5px rgba(0,0,0,0.02);">
						<div style="color:#f59e0b; font-size:24px;"><span class="dashicons dashicons-calendar-alt"></span></div>
						<div style="flex:1;">
							<div style="font-weight:600; font-size:14px; margin-bottom:2px;">Celebrate a birthday</div>
							<div style="color:#64748b; font-size:12px;">+30 points</div>
						</div>
						<div style="color:#f59e0b; font-size:18px;">›</div>
					</div>
					<div style="background:#fff; border-radius:8px; padding:15px; margin-bottom:10px; display:flex; align-items:center; gap:15px; box-shadow:0 2px 5px rgba(0,0,0,0.02);">
						<div style="color:#f59e0b; font-size:24px;"><span class="dashicons dashicons-twitter"></span></div>
						<div style="flex:1;">
							<div style="font-weight:600; font-size:14px; margin-bottom:2px;">Twitter Share</div>
							<div style="color:#64748b; font-size:12px;">+70 points</div>
						</div>
						<div style="color:#f59e0b; font-size:18px;">›</div>
					</div>
				`;
				var dummyRedeemHTML = `
					<div style="background:#fff; border-radius:8px; padding:15px; margin-bottom:10px; display:flex; align-items:center; gap:15px; box-shadow:0 2px 5px rgba(0,0,0,0.02);">
						<div style="color:#f59e0b; font-size:24px;"><span class="dashicons dashicons-tickets-alt"></span></div>
						<div style="flex:1;">
							<div style="font-weight:600; font-size:14px; margin-bottom:2px;">Fixed coupon discount</div>
							<div style="color:#64748b; font-size:12px;">Coupon reward</div>
						</div>
					</div>
					<div style="background:#fff; border-radius:8px; padding:15px; margin-bottom:10px; display:flex; align-items:center; gap:15px; box-shadow:0 2px 5px rgba(0,0,0,0.02);">
						<div style="color:#f59e0b; font-size:24px;"><span class="dashicons dashicons-tag"></span></div>
						<div style="flex:1;">
							<div style="font-weight:600; font-size:14px; margin-bottom:2px;">Percentage discount</div>
							<div style="color:#64748b; font-size:12px;">Percentage discount</div>
						</div>
					</div>
				`;

				earnCard.addEventListener('click', function() {
					mainPanel.style.display = 'none';
					secPanel.style.display = 'flex';
					secTitle.innerText = 'Earn';
					secBody.innerHTML = dummyEarnHTML;
				});

				redeemCard.addEventListener('click', function() {
					mainPanel.style.display = 'none';
					secPanel.style.display = 'flex';
					secTitle.innerText = 'Redeem';
					secBody.innerHTML = dummyRedeemHTML;
				});

				secBackBtn.addEventListener('click', function() {
					secPanel.style.display = 'none';
					mainPanel.style.display = 'block';
				});
			}
		});
		</script>

		<script>
		// Live Preview Data Binding Logic
		document.addEventListener('DOMContentLoaded', function() {
			var updatePreview = function() {
				var primaryColor = document.getElementById('launcher_design_theme_primary')?.value || '#4F47EB';
				var launcherBtnBg = document.getElementById('launcher_design_launcher_background')?.value || '#4F47EB';
				var launcherBtnText = document.getElementById('launcher_design_launcher_text')?.value || '#FFFFFF';
				
				var guestWelcomeTitle = document.getElementById('launcher_content_guest_welcome_title')?.value || 'Welcome';
				var guestWelcomeDesc = document.getElementById('launcher_content_guest_welcome_desc')?.value || 'Sign up to earn points';
				var guestEarnTitle = document.getElementById('launcher_content_guest_earn_title')?.value || 'Earn Points';
				var guestRedeemTitle = document.getElementById('launcher_content_guest_redeem_title')?.value || 'Redeem Points';
				var joinBtnText = document.getElementById('launcher_content_guest_button_text')?.value || 'Join Now';
				var signInText = document.getElementById('launcher_content_guest_signin_text')?.value || 'Sign in';
				var haveAccountText = document.getElementById('launcher_content_guest_have_account')?.value || 'Already have an account?';
				
				var memberWelcome = document.getElementById('launcher_content_member_welcome')?.value || 'Welcome back, User';
				var memberPointsLabel = document.getElementById('launcher_content_member_points_label')?.value || 'Your Points';
				var memberEarnTitle = document.getElementById('launcher_content_member_earn_title')?.value || 'Earn Points';
				var memberRedeemTitle = document.getElementById('launcher_content_member_redeem_title')?.value || 'Redeem Points';
				var memberReferralTitle = document.getElementById('launcher_content_member_referrals_title')?.value || 'Refer and earn';
				var memberReferralDesc = document.getElementById('launcher_content_member_referrals_desc')?.value || 'Refer your friends and earn rewards. Your friend can get a reward as well!';
				
				var channelCheckboxes = document.querySelectorAll('input[name="launcher_content_member_referrals_channels[]"]:checked');
				var memberReferralChannels = Array.from(channelCheckboxes).map(function(cb) { return cb.value; });
				if (memberReferralChannels.length === 0) {
					memberReferralChannels = ['facebook', 'twitter', 'whatsapp', 'email'];
				}
				
				var currentMode = document.querySelector('.preview-toggle-btn.active')?.getAttribute('data-mode') || 'guest';
				
				var previewHeader = document.querySelector('.preview-main-panel .preview-header');
				if (previewHeader) previewHeader.style.backgroundColor = primaryColor;
				
				var secPreviewHeader = document.querySelector('.preview-secondary-panel .preview-header');
				if (secPreviewHeader) secPreviewHeader.style.backgroundColor = primaryColor;
				
				var previewLauncher = document.querySelector('.preview-launcher');
				if (previewLauncher) {
					previewLauncher.style.backgroundColor = launcherBtnBg;
					previewLauncher.style.color = launcherBtnText;
					
					var btnStyle = document.getElementById('launcher_widget_appearance')?.value || 'icon_with_text';
					var btnText = document.getElementById('launcher_widget_text')?.value || 'Rewards';
					var iconType = document.getElementById('launcher_widget_icon_type')?.value || 'star';

					var iconClass = 'dashicons-star-filled';
					if (iconType === 'gift') iconClass = 'dashicons-pressthis';
					else if (iconType === 'trophy') iconClass = 'dashicons-awards';
					else if (iconType === 'medal') iconClass = 'dashicons-saved';
					else if (iconType === 'crown') iconClass = 'dashicons-superhero';
					else if (iconType === 'heart') iconClass = 'dashicons-heart';
					else if (iconType === 'custom') iconClass = 'dashicons-format-image';
					
					var innerHTML = '';
					if (btnStyle === 'icon_only') {
						previewLauncher.style.width = '60px';
						previewLauncher.style.borderRadius = '50%';
						previewLauncher.style.padding = '0';
						innerHTML = `<span class="dashicons ${iconClass}" style="font-size:24px; width:24px; height:24px; display:flex; align-items:center; justify-content:center;"></span>`;
					} else if (btnStyle === 'text_only') {
						previewLauncher.style.width = 'auto';
						previewLauncher.style.borderRadius = '30px';
						previewLauncher.style.padding = '0 20px';
						innerHTML = `<span style="font-weight:600; font-size:16px;">${btnText}</span>`;
					} else { // icon_with_text
						previewLauncher.style.width = 'auto';
						previewLauncher.style.borderRadius = '30px';
						previewLauncher.style.padding = '0 20px 0 15px';
						innerHTML = `<span class="dashicons ${iconClass}" style="font-size:20px; width:20px; height:20px; margin-right:8px; display:flex; align-items:center; justify-content:center;"></span><span style="font-weight:600; font-size:16px;">${btnText}</span>`;
					}
					previewLauncher.innerHTML = innerHTML;
				}

				document.querySelectorAll('.preview-icons').forEach(function(icon) {
					icon.style.color = primaryColor;
				});

				var previewBtn = document.querySelector('.preview-button');
				if (previewBtn) previewBtn.style.backgroundColor = primaryColor;

				var previewLink = document.querySelector('.preview-link');
				if (previewLink) previewLink.style.color = primaryColor;

				var titleEl = document.querySelector('.preview-welcome-title');
				var descEl = document.querySelector('.preview-welcome-desc');
				var earnEl = document.querySelector('.preview-earn-title');
				var redeemEl = document.querySelector('.preview-redeem-title');
				var previewBtn = document.querySelector('.preview-button');
				var previewLink = document.querySelector('.preview-link');
				var haveAccountEl = document.querySelector('.preview-have-account');
				var footerWrap = document.querySelector('.preview-footer');
				var refCard = document.querySelector('.preview-referral-card');
				var refTitleEl = document.querySelector('.preview-referral-title');
				var refDescEl = document.querySelector('.preview-referral-desc');
				
				if (currentMode === 'guest') {
					if (titleEl) titleEl.innerText = guestWelcomeTitle;
					if (descEl) descEl.innerText = guestWelcomeDesc;
					if (earnEl) earnEl.innerText = guestEarnTitle;
					if (redeemEl) redeemEl.innerText = guestRedeemTitle;
					if (footerWrap) footerWrap.style.display = 'block';
					if (haveAccountEl) haveAccountEl.innerText = haveAccountText;
					if (previewBtn) previewBtn.innerText = joinBtnText;
					if (previewLink) previewLink.innerText = signInText;
					if (refCard) refCard.style.display = 'none';
				} else {
					if (titleEl) titleEl.innerText = memberWelcome.replace('{o100_user_name}', 'John Doe');
					if (descEl) descEl.innerHTML = '<span style="font-size:24px; font-weight:bold;">4000</span> ' + memberPointsLabel.replace('{o100_point_label}', 'Points');
					if (earnEl) earnEl.innerText = memberEarnTitle;
					if (redeemEl) redeemEl.innerText = memberRedeemTitle;
					if (footerWrap) footerWrap.style.display = 'none'; // Members don't see Join/Signin
					if (refCard) {
						refCard.style.display = 'block';
						
						var dashiconMap = {
							'facebook': 'dashicons-facebook-alt',
							'whatsapp': 'dashicons-whatsapp',
							'email': 'dashicons-email-alt',
							'linkedin': 'dashicons-linkedin',
							'telegram': 'dashicons-testimonial'
						};
						
						var socialsHtml = memberReferralChannels.map(function(ch) {
							if (ch === 'twitter') {
								return '<span style="display:inline-flex; align-items:center; cursor:pointer;"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></span>';
							} else {
								var iconClass = dashiconMap[ch] || 'dashicons-share';
								return '<span class="dashicons ' + iconClass + '" style="cursor:pointer;"></span>';
							}
						}).join('');
						
						var socialsContainer = document.querySelector('.preview-referral-socials');
						if (socialsContainer) socialsContainer.innerHTML = socialsHtml;
					}
					if (refTitleEl) refTitleEl.innerText = memberReferralTitle;
					if (refDescEl) refDescEl.innerText = memberReferralDesc;
				}

				// Logo Logic
				var logoIsShow = document.getElementById('launcher_design_logo_is_show')?.checked;
				var logoImage = document.getElementById('launcher_design_logo_image')?.value;
				var logoWrapper = document.querySelector('.preview-logo-wrapper');
				var logoImg = document.querySelector('.preview-logo-img');
				if (logoWrapper && logoImg) {
					if (logoIsShow && logoImage) {
						logoWrapper.style.display = 'flex';
						logoImg.src = logoImage;
					} else {
						logoWrapper.style.display = 'none';
					}
				}
			};

			// Bind events to inputs
			var inputsToBind = [
				'launcher_design_theme_primary',
				'launcher_design_launcher_background',
				'launcher_design_launcher_text',
				'launcher_content_guest_welcome_title',
				'launcher_content_guest_welcome_desc',
				'launcher_content_guest_earn_title',
				'launcher_content_guest_redeem_title',
				'launcher_content_guest_button_text',
				'launcher_content_guest_signin_text',
				'launcher_content_guest_have_account',
				'launcher_content_member_welcome',
				'launcher_content_member_points_label',
				'launcher_content_member_earn_title',
				'launcher_content_member_redeem_title',
				'launcher_content_member_referrals_title',
				'launcher_content_member_referrals_desc',
				'launcher_design_logo_is_show',
				'launcher_design_logo_image',
				'launcher_widget_appearance',
				'launcher_widget_text',
				'launcher_widget_icon_type'
			];

			inputsToBind.forEach(function(id) {
				var el = document.getElementById(id);
				if (el) {
					el.addEventListener('input', updatePreview);
					el.addEventListener('change', updatePreview);
				}
			});

			// For multiselect checkbox preview trigger
			document.querySelectorAll('input[name="launcher_content_member_referrals_channels[]"]').forEach(function(cb) {
				cb.addEventListener('change', updatePreview);
			});

			// Toggle Logic
			document.querySelectorAll('.preview-toggle-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					document.querySelectorAll('.preview-toggle-btn').forEach(function(b) {
						b.classList.remove('active');
						b.style.background = 'transparent';
						b.style.color = '#475569';
					});
					this.classList.add('active');
					var primaryColor = document.getElementById('launcher_design_theme_primary')?.value || '#4F47EB';
					this.style.background = primaryColor;
					this.style.color = '#FFF';
					updatePreview();
				});
			});

			// Initial update
			setTimeout(updatePreview, 500);

			// Handle Save Widget Settings Button
			var topSaveBtn = document.querySelector('.o100-launcher-save-btn');
			if (topSaveBtn) {
				topSaveBtn.addEventListener('click', function(e) {
					e.preventDefault();
					var bottomSaveBtn = document.getElementById('o100-loyalty-save-settings');
					if (bottomSaveBtn) {
						var originalText = this.innerText;
						this.innerText = 'Saving...';
						this.style.opacity = '0.7';
						bottomSaveBtn.click();
						
						var that = this;
						setTimeout(function() {
							that.innerText = 'Saved!';
							setTimeout(function() {
								that.innerText = originalText;
								that.style.opacity = '1';
							}, 2000);
						}, 1000);
					}
				});
			}

			// Handle Reset Defaults
			var resetBtn = document.querySelector('.o100-launcher-reset-btn');
			if (resetBtn) {
				resetBtn.addEventListener('click', function(e) {
					e.preventDefault();
					if (confirm('Are you sure you want to reset all Launcher settings to default?')) {
						// Reset basic fields
						var defaults = {
							'launcher_design_theme_primary': '#4F47EB',
							'launcher_design_theme_secondary': '#FFFFFF',
							'launcher_design_banner_background': '#F5F5F5',
							'launcher_design_banner_text': '#333333',
							'launcher_design_buttons_background': '#4F47EB',
							'launcher_design_buttons_text': '#FFFFFF',
							'launcher_design_launcher_background': '#4F47EB',
							'launcher_design_launcher_text': '#FFFFFF',
							'launcher_content_guest_welcome_title': 'Welcome',
							'launcher_content_guest_welcome_desc': 'Sign up to earn points',
							'launcher_content_guest_earn_title': 'Earn Points',
							'launcher_content_guest_redeem_title': 'Redeem Points',
							'launcher_content_guest_button_text': 'Join Now',
							'launcher_content_guest_signin_text': 'Sign in'
						};
						for (var id in defaults) {
							var el = document.getElementById(id);
							if (el) {
								el.value = defaults[id];
								if (el.tagName === 'INPUT' && el.type === 'color') {
									// Trigger change for color picker
									var event = new Event('input', { bubbles: true });
									el.dispatchEvent(event);
								}
							}
						}
						// Trigger preview update
						updatePreview();
					}
				});
			}
		});
		</script>

		<script>
		// Initialize Select2 on our custom class after DOM is ready
		jQuery(document).ready(function($){
			$('.o100-select2').select2({
				width: '100%',
				allowClear: true
			});

			// Initialize WP Media Uploader for image fields
			$('.o100-upload-image-btn').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var $wrapper = $btn.closest('.o100-image-upload-wrapper');
				var $input = $wrapper.find('.o100-image-url-input');
				var $preview = $wrapper.find('.o100-image-preview');

				// Create the media frame
				var customUploader = wp.media({
					title: 'Choose an image',
					button: { text: 'Use this image' },
					multiple: false
				});

				// When an image is selected, run a callback
				customUploader.on('select', function() {
					var attachment = customUploader.state().get('selection').first().toJSON();
					$input.val(attachment.url).trigger('change');
					$preview.html('<img src="' + attachment.url + '" style="max-width:100%; max-height:100%;">');
				});

				// Open the uploader dialog
				customUploader.open();
			});
		});
		</script>

		<style>
		/* Core fallback to hide native select when Select2 is active but hasn't fully loaded its CSS */
		.select2-hidden-accessible {
			border: 0 !important;
			clip: rect(0 0 0 0) !important;
			-webkit-clip-path: inset(50%) !important;
			clip-path: inset(50%) !important;
			height: 1px !important;
			overflow: hidden !important;
			padding: 0 !important;
			position: absolute !important;
			width: 1px !important;
			white-space: nowrap !important;
		}

		/* Responsive fixes for settings form */
		.o100-loyalty-styled-table {
			table-layout: fixed;
			width: 100%;
		}
		.o100-loyalty-styled-table th {
			width: 30%;
			min-width: 120px;
			word-wrap: break-word;
		}
		.o100-loyalty-styled-table td {
			width: 70%;
		}
		.o100-loyalty-styled-table input[type="text"],
		.o100-loyalty-styled-table input[type="number"],
		.o100-loyalty-styled-table textarea,
		.o100-loyalty-styled-table select {
			width: 100%;
			max-width: 100%;
			box-sizing: border-box;
		}

		/* Custom Select2 Overrides to match React-Select screenshot */
		.o100-loyalty-settings .select2-container {
			width: 100% !important;
			max-width: 600px;
		}
		.o100-loyalty-settings .select2-container--default .select2-selection--multiple {
			border: 2px solid #e2e8f0;
			border-radius: 8px;
			min-height: 44px;
			padding: 4px 8px;
			background: #fff;
		}
		.o100-loyalty-settings .select2-container--default.select2-container--focus .select2-selection--multiple,
		.o100-loyalty-settings .select2-container--default.select2-container--open .select2-selection--multiple {
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
		}
		.o100-loyalty-settings .select2-container--default .select2-selection--multiple .select2-selection__rendered {
			display: flex !important;
			flex-wrap: wrap !important;
			align-items: center !important;
			padding: 4px 8px !important;
			margin: 0 !important;
			list-style: none !important;
		}
		.o100-loyalty-settings .select2-container--default .select2-selection--multiple .select2-selection__choice {
			background-color: #eef2ff !important;
			border: 1px solid #c7d2fe !important;
			border-radius: 4px !important;
			color: #1e293b !important;
			padding: 3px 8px !important;
			margin: 3px 4px 3px 0 !important;
			font-size: 13px !important;
			line-height: 1.8 !important;
			display: flex !important;
			align-items: center !important;
			float: none !important;
		}
		.o100-loyalty-settings .select2-container--default .select2-search--inline .select2-search__field {
			width: auto !important;
			min-width: 50px !important;
			border: none !important;
			box-shadow: none !important;
			margin: 3px 0 !important;
			padding: 0 !important;
		}
		.o100-loyalty-settings .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
			color: #6366f1;
			margin-right: 5px;
			font-weight: bold;
			font-size: 14px;
		}
		.o100-loyalty-settings .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
			color: #ef4444;
		}
		.o100-loyalty-settings .select2-container--default .select2-selection--single {
			height: 42px;
			border: 2px solid #e2e8f0;
			border-radius: 8px;
		}
		.o100-loyalty-settings .select2-container--default.select2-container--focus .select2-selection--single,
		.o100-loyalty-settings .select2-container--default.select2-container--open .select2-selection--single {
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
		}
		.o100-loyalty-settings .select2-container--default .select2-selection--single .select2-selection__rendered {
			line-height: 38px;
			padding-left: 12px;
		}
		.o100-loyalty-settings .select2-container--default .select2-selection--single .select2-selection__arrow {
			height: 38px;
		}
		.o100-loyalty-settings .select2-dropdown {
			border: 2px solid #e2e8f0;
			border-radius: 8px;
			box-shadow: 0 4px 12px rgba(0,0,0,0.08);
		}
		.o100-loyalty-settings .select2-results__option--highlighted {
			background-color: #eef2ff !important;
			color: #1e293b !important;
		}
		.o100-loyalty-styled-table input[type="text"], 
		.o100-loyalty-styled-table textarea {
			border: 2px solid #e2e8f0;
			border-radius: 8px;
			padding: 8px 12px;
		}
		.o100-loyalty-styled-table input[type="text"]:focus, 
		.o100-loyalty-styled-table textarea:focus {
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
		}
		</style>

		<div class="o100-loyalty-form-actions" style="margin-top:20px;">
			<button type="button" class="button button-primary" id="o100-loyalty-save-settings">
				<?php esc_html_e( 'Save Settings', 'order100' ); ?>
			</button>
			<span class="o100-loyalty-save-status" style="margin-left:15px; font-weight:600;"></span>
		</div>
	</div>
</div>


// TS: 20260204110224

// TS: 20260311173430
