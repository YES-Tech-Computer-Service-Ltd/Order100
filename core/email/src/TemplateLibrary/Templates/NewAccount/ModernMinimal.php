<?php

namespace Order100\Notification\Engine\TemplateLibrary\Templates\NewAccount;

use Order100\Notification\Engine\Abstracts\BaseTemplate;
use Order100\Notification\Engine\Elements\Column;
use Order100\Notification\Engine\Elements\ColumnLayout;
use Order100\Notification\Engine\Elements\Divider;
use Order100\Notification\Engine\Elements\Image;
use Order100\Notification\Engine\Elements\SocialIcon;
use Order100\Notification\Engine\Elements\Text;
use Order100\Notification\Engine\Utils\SingletonTrait;

class ModernMinimal extends BaseTemplate {
    use SingletonTrait;

    public function __construct() {
        parent::__construct();
        $this->id          = 'new_account_modern_minimal';
        $this->email_type  = 'customer_new_account';
        $this->name        = 'Modern Minimal';
        $this->description = 'Welcome email for new accounts';
        $this->elements    = [
            ColumnLayout::get_object_data( 2, [
                'background_color' => '#ffffff',
                'children' => [
                    Column::get_object_data( 30, [ 'children' => [ Image::get_object_data( [ 'align' => 'left', 'src' => O100NE_PLUGIN_URL . 'assets/images/woocommerce-logo.png', 'width' => 116, 'padding' => [ 'top' => 0, 'right' => 10, 'bottom' => 0, 'left' => 0 ] ] ) ] ] ),
                    Column::get_object_data( 70, [ 'children' => [ Text::get_object_data( [ 'rich_text' => '<p style="text-align: right; font-size: 18px; font-weight: 500;">My Account &nbsp;&nbsp; Shop Now</p>', 'padding' => [ 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ], 'text_color' => '#333439' ] ) ] ] ),
                ],
                'padding' => [ 'top' => 20, 'right' => 40, 'bottom' => 20, 'left' => 40 ],
            ] ),
            ColumnLayout::get_object_data( 1, [
                'background_color'       => '#E8FFE8',
                'inner_background_color' => '#ffffff00',
                'children' => [
                    Column::get_object_data( 100, [ 'children' => [
                        Text::get_object_data( [
                            'rich_text'        => '<h1 style="font-size: 30px; font-weight: 600; text-align: center;">🎉 Welcome!</h1><p style="text-align: center; font-size: 18px;">Your account has been created on [o100_site_title]</p>',
                            'text_color'       => '#333439',
                            'background_color' => '#ffffff00',
                            'padding'          => [ 'top' => 30, 'right' => 50, 'bottom' => 30, 'left' => 50 ],
                        ] ),
                    ] ] ),
                ],
                'padding' => [ 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ],
            ] ),
            Text::get_object_data( [
                'rich_text'  => '<p>Hi <b>[o100_user_login],</b></p><p>Thank you for creating an account on [o100_site_title]. Your account is now ready to use.</p><p>Your username is: <b>[o100_user_login]</b></p><p>You can access your account area to view orders, change your password, and more at the link below:</p>',
                'text_color' => '#333439',
                'padding'    => [ 'top' => 15, 'right' => 50, 'bottom' => 15, 'left' => 50 ],
            ] ),
            Text::get_object_data( [
                'rich_text'  => '<p style="font-size: 16px;"><b>[o100_my_account_link text_link="Go to My Account →"]</b></p>',
                'text_color' => '#333439',
                'padding'    => [ 'top' => 0, 'right' => 50, 'bottom' => 20, 'left' => 50 ],
            ] ),
            ColumnLayout::get_object_data( 1, [
                'padding'  => [ 'top' => 10, 'bottom' => 10, 'left' => 40, 'right' => 40 ],
                'children' => [ Column::get_object_data( 100, [ 'children' => [
                    Divider::get_object_data( [ 'height' => 2, 'width' => 100, 'divider_color' => '#333439', 'padding' => [ 'top' => 20, 'right' => 0, 'bottom' => 20, 'left' => 0 ] ] ),
                    Text::get_object_data( [ 'rich_text' => '<p style="text-align: center; font-weight: 300;"><span style="font-size: 16px;">For questions, contact <u>support@yourstore.com</u></span></p>', 'background_color' => '#ffffff00', 'padding' => [ 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ], 'text_color' => '#333439' ] ),
                    SocialIcon::get_object_data( [ 'align' => 'center', 'spacing' => 24, 'width_icon' => 24, 'style' => 'Colorful', 'icon_list' => [ [ 'icon' => 'facebook', 'url' => '#' ], [ 'icon' => 'instagram', 'url' => '#' ], [ 'icon' => 'tiktok', 'url' => '#' ], [ 'icon' => 'youtube', 'url' => '#' ] ], 'padding' => [ 'top' => 20, 'right' => 0, 'bottom' => 20, 'left' => 0 ] ] ),
                    Text::get_object_data( [ 'rich_text' => '<p style="text-align: center; font-weight: 300;"><span style="font-size: 12px;">© 2025 Your Store</span></p>', 'padding' => [ 'top' => 0, 'right' => 0, 'bottom' => 20, 'left' => 0 ], 'text_color' => '#77859B' ] ),
                ] ] ) ],
            ] ),
        ];
    }
}

// TS: 20260127154407
