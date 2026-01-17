<?php
namespace Order100\Notification\Engine\TemplatePatterns\Patterns;

use Order100\Notification\Engine\Abstracts\BasePattern;
use Order100\Notification\Engine\Elements\Column;
use Order100\Notification\Engine\Elements\ColumnLayout;
use Order100\Notification\Engine\Elements\Divider;
use Order100\Notification\Engine\Elements\Image;
use Order100\Notification\Engine\Elements\SocialIcon;
use Order100\Notification\Engine\Elements\Space;
use Order100\Notification\Engine\Elements\Text;
use Order100\Notification\Engine\TemplatePatterns\SectionTemplates\Footer;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Footer3 Elements
 */

class Footer3 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'footer_3';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Footer::TYPE;
        $this->position = 30;
        $this->name     = __( 'Footer 3', 'order100' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'padding'                => [
                        'top'    => 40,
                        'left'   => 20,
                        'right'  => 20,
                        'bottom' => 0,
                    ],
                    'inner_border_radius'    => [
                        'top_left'     => 20,
                        'top_right'    => 20,
                        'bottom_left'  => 0,
                        'bottom_right' => 0,
                    ],
                    'inner_background_color' => '#F8FAFD',
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
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
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 40,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                    Space::get_object_data(
                                        [
                                            'height' => 20,
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                1,
                [
                    'padding'                => [
                        'top'    => 0,
                        'left'   => 20,
                        'right'  => 20,
                        'bottom' => 0,
                    ],
                    'inner_background_color' => '#F8FAFD',
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-weight: 300;"><span style="font-size: 14px; margin: 0;"> If you have any questions, please email us at <u style="font-weight: 400;">hi@o100ne.com</u> </span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-weight: 300;"><span style="font-size: 14px; margin: 0;"> Let\'s Shopping From Anywhere</span></p>',
                                            'padding'    => [
                                                'top'    => 20,
                                                'right'  => 0,
                                                'bottom' => 10,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),

                                ],
                            ]
                        ),
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                2,
                [
                    'padding'                => [
                        'top'    => 0,
                        'left'   => 20,
                        'right'  => 20,
                        'bottom' => 0,
                    ],
                    'inner_background_color' => '#F8FAFD',
                    'children'               => [
                        Column::get_object_data(
                            25,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-footer-2-img-1.png',
                                            'padding' => [
                                                'top'    => 0,
                                                'left'   => 0,
                                                'right'  => 10,
                                                'bottom' => 0,
                                            ],
                                            'align'   => 'right',
                                            'background_color' => '#F8FAFD',
                                            'width'   => 108,
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            25,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-footer-2-img-2.png',
                                            'padding' => [
                                                'top'    => 0,
                                                'left'   => 10,
                                                'right'  => 0,
                                                'bottom' => 0,
                                            ],
                                            'align'   => 'left',
                                            'background_color' => '#F8FAFD',
                                            'width'   => 108,
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                1,
                [
                    'padding'                => [
                        'top'    => 0,
                        'left'   => 20,
                        'right'  => 20,
                        'bottom' => 40,
                    ],
                    'inner_border_radius'    => [
                        'top_left'     => 0,
                        'top_right'    => 0,
                        'bottom_left'  => 20,
                        'bottom_right' => 20,
                    ],
                    'inner_background_color' => '#F8FAFD',
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Divider::get_object_data(
                                        [
                                            'height'  => 1,
                                            'width'   => 100,
                                            'divider_color' => '#E2E6EE',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 20,
                                                'right'  => 20,
                                                'bottom' => 20,
                                                'left'   => 20,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-size: 14px; font-weight: 300;"><span>© 2023 Yaycommerce.com</span>     <span style="color: #e2e6ee;">|</span>     Terms &amp; Condition     <span style="color: #e2e6ee;">|</span>     Return Policy     <span style="color: #e2e6ee;">|</span>     Unsubscribe</p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 40,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#77859B',
                                            'background_color' => '#ffffff00',
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


// TS: 20260117123449
