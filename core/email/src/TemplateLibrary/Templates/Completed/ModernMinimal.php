<?php

namespace Order100\Notification\Engine\TemplateLibrary\Templates\Completed;

use Order100\Notification\Engine\Abstracts\BaseTemplate;
use Order100\Notification\Engine\Elements\BillingShippingAddress;
use Order100\Notification\Engine\Elements\Column;
use Order100\Notification\Engine\Elements\ColumnLayout;
use Order100\Notification\Engine\Elements\ElementsHelper;
use Order100\Notification\Engine\Elements\Image;
use Order100\Notification\Engine\Elements\OrderDetails;
use Order100\Notification\Engine\Elements\SocialIcon;
use Order100\Notification\Engine\Elements\Text;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Modern Minimal template for Completed Order email.
 */
class ModernMinimal extends BaseTemplate {
    use SingletonTrait;

    public function __construct() {
        parent::__construct();
        $this->id          = 'completed_order_modern_minimal';
        $this->email_type  = 'customer_completed_order';
        $this->name        = 'Modern Minimal';
        $this->description = 'Clean layout with plenty of whitespace';
        $this->elements    = [
            ColumnLayout::get_object_data(
                2,
                [
                    'background_color' => '#ffffff',
                    'children'         => [
                        Column::get_object_data(
                            30,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'align'   => 'left',
                                            'src'     => O100NE_PLUGIN_URL . 'assets/images/woocommerce-logo.png',
                                            'width'   => 116,
                                            'padding' => [
                                                'top'    => 0,
                                                'right'  => 10,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            70,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: right; font-size: 18px; font-weight: 500;">My Account      Order Tracking      Contact</p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                    'padding'          => [
                        'top'    => 20,
                        'right'  => 40,
                        'bottom' => 20,
                        'left'   => 40,
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'background_color'       => '#EEFFE9',
                    'inner_background_color' => '#ffffff00',
                    'children'               => [
                        Column::get_object_data(
                            70,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<h1 style="font-size: 30px; font-weight: 600;">Order #[o100_order_id is_plain="true"]<br><br>has been completed</h1>',
                                            'text_color' => '#333439',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 10,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 50,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            30,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'align'   => 'left',
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-order-completed-banner-icon.png',
                                            'width'   => 120,
                                            'padding' => [
                                                'top'    => 0,
                                                'right'  => 10,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/o100ne-order-completed-banner-bg.png',
                        'position'   => 'custom',
                        'x_position' => 70,
                        'y_position' => 0,
                        'size'       => 'cover',
                        'repeat'     => 'no-repeat',
                    ],
                    'padding'                => [
                        'top'    => 30,
                        'right'  => 0,
                        'bottom' => 30,
                        'left'   => 0,
                    ],
                ]
            ),
            Image::get_object_data(
                [
                    'src'     => 'https://images.wpbrandy.com/uploads/o100ne-order-completed-processing-bar.png',
                    'width'   => 600,
                    'padding' => [
                        'top'    => 0,
                        'right'  => 10,
                        'bottom' => 0,
                        'left'   => 0,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p>Hi <b>[o100_billing_first_name] [o100_billing_last_name],</b></p><p>Great news! Your order is about to ship. It should be delivered to you within 7 working days.</p>',
                    'text_color' => '#333439',
                    'padding'    => [
                        'top'    => 15,
                        'right'  => 50,
                        'bottom' => 15,
                        'left'   => 50,
                    ],
                ]
            ),
            OrderDetails::get_object_data(
                [
                    'title'       => '<p><span style="font-size: 20px;"><strong>Order Summary</strong></span></p>',
                    'title_color' => '#1A1A1A',
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="font-size: 16px;">[o100_order_link text_link="View Order Detail"]</p>',
                    'text_color' => '#333439',
                    'padding'    => [
                        'top'    => 0,
                        'right'  => 50,
                        'bottom' => 0,
                        'left'   => 50,
                    ],
                ]
            ),
            BillingShippingAddress::get_object_data(
                [
                    'title_color' => '#1A1A1A',
                ]
            ),
            ColumnLayout::get_object_data(
                1,
                [
                    'padding'  => [
                        'top'    => 10,
                        'bottom' => 10,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children' => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-weight: 300;"><span style="font-size: 16px;">For questions, contact <u>hi@o100ne.com</u>, visit our <u>FAQs</u>, or <u>chat</u> with us during operating hours for account support</span></p>',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    SocialIcon::get_object_data(
                                        [
                                            'align'      => 'center',
                                            'spacing'    => 24,
                                            'width_icon' => 24,
                                            'style'      => 'Colorful',
                                            'icon_list'  => [
                                                [
                                                    'icon' => 'facebook',
                                                    'url'  => '#',
                                                ],
                                                [
                                                    'icon' => 'instagram',
                                                    'url'  => '#',
                                                ],
                                                [
                                                    'icon' => 'tiktok',
                                                    'url'  => '#',
                                                ],
                                                [
                                                    'icon' => 'youtube',
                                                    'url'  => '#',
                                                ],

                                            ],
                                            'padding'    => [
                                                'top'    => 20,
                                                'right'  => 0,
                                                'bottom' => 20,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-weight: 300;"><span style="font-size: 12px;">© 2025 O100ne.com</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 20,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#77859B',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
        ];
    }
}


// TS: 20260117162749
