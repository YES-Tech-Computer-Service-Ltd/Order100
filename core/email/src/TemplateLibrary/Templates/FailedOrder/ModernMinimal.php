<?php

namespace Order100\Notification\Engine\TemplateLibrary\Templates\FailedOrder;

use Order100\Notification\Engine\Abstracts\BaseTemplate;
use Order100\Notification\Engine\Elements\BillingShippingAddress;
use Order100\Notification\Engine\Elements\Column;
use Order100\Notification\Engine\Elements\ColumnLayout;
use Order100\Notification\Engine\Elements\Divider;
use Order100\Notification\Engine\Elements\Image;
use Order100\Notification\Engine\Elements\OrderDetails;
use Order100\Notification\Engine\Elements\SocialIcon;
use Order100\Notification\Engine\Elements\Text;
use Order100\Notification\Engine\Utils\SingletonTrait;

class ModernMinimal extends BaseTemplate {
    use SingletonTrait;

    public function __construct() {
        parent::__construct();
        $this->id          = 'failed_order_modern_minimal';
        $this->email_type  = 'failed_order';
        $this->name        = 'Modern Minimal';
        $this->description = 'Admin notification for failed order payment';
        $this->elements    = [
            ColumnLayout::get_object_data( 2, [
                'background_color' => '#ffffff',
                'children' => [
                    Column::get_object_data( 30, [ 'children' => [ Image::get_object_data( [ 'align' => 'left', 'src' => O100NE_PLUGIN_URL . 'assets/images/woocommerce-logo.png', 'width' => 116, 'padding' => [ 'top' => 0, 'right' => 10, 'bottom' => 0, 'left' => 0 ] ] ) ] ] ),
                    Column::get_object_data( 70, [ 'children' => [ Text::get_object_data( [ 'rich_text' => '<p style="text-align: right; font-size: 18px; font-weight: 500;">Store Admin &nbsp;&nbsp; Dashboard</p>', 'padding' => [ 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ], 'text_color' => '#333439' ] ) ] ] ),
                ],
                'padding' => [ 'top' => 20, 'right' => 40, 'bottom' => 20, 'left' => 40 ],
            ] ),
            ColumnLayout::get_object_data( 1, [
                'background_color'       => '#FFE8E8',
                'inner_background_color' => '#ffffff00',
                'children' => [
                    Column::get_object_data( 100, [ 'children' => [
                        Text::get_object_data( [
                            'rich_text'        => '<h1 style="font-size: 30px; font-weight: 600; text-align: center;">❌ Payment Failed</h1><p style="text-align: center; font-size: 18px;">Order #[o100_order_id is_plain="true"] requires attention.</p>',
                            'text_color'       => '#333439',
                            'background_color' => '#ffffff00',
                            'padding'          => [ 'top' => 30, 'right' => 50, 'bottom' => 30, 'left' => 50 ],
                        ] ),
                    ] ] ),
                ],
                'padding' => [ 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ],
            ] ),
            Text::get_object_data( [
                'rich_text'  => '<p>Hi Admin,</p><p>Payment for order #[o100_order_id is_plain="true"] from [o100_billing_first_name] [o100_billing_last_name] has failed. The customer may need to retry with a different payment method.</p>',
                'text_color' => '#333439',
                'padding'    => [ 'top' => 15, 'right' => 50, 'bottom' => 15, 'left' => 50 ],
            ] ),
            OrderDetails::get_object_data( [ 'title' => '<p><span style="font-size: 20px;"><strong>Order Summary</strong></span></p>', 'title_color' => '#1A1A1A' ] ),
            BillingShippingAddress::get_object_data( [ 'title_color' => '#1A1A1A' ] ),
            ColumnLayout::get_object_data( 1, [
                'padding'  => [ 'top' => 10, 'bottom' => 10, 'left' => 40, 'right' => 40 ],
                'children' => [ Column::get_object_data( 100, [ 'children' => [
                    Divider::get_object_data( [ 'height' => 2, 'width' => 100, 'divider_color' => '#333439', 'padding' => [ 'top' => 20, 'right' => 0, 'bottom' => 20, 'left' => 0 ] ] ),
                    Text::get_object_data( [ 'rich_text' => '<p style="text-align: center; font-weight: 300;"><span style="font-size: 12px;">© 2025 Your Store</span></p>', 'padding' => [ 'top' => 0, 'right' => 0, 'bottom' => 20, 'left' => 0 ], 'text_color' => '#77859B' ] ),
                ] ] ) ],
            ] ),
        ];
    }
}



// TS: 20260122125442

// TS: 20260527004025
