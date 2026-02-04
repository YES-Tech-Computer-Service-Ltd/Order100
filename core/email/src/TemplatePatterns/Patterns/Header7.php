<?php
namespace Order100\Notification\Engine\TemplatePatterns\Patterns;

use Order100\Notification\Engine\Abstracts\BasePattern;
use Order100\Notification\Engine\Elements\Divider;
use Order100\Notification\Engine\Elements\Image;
use Order100\Notification\Engine\Elements\Text;
use Order100\Notification\Engine\TemplatePatterns\SectionTemplates\Header;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Elements\ColumnLayout;
use Order100\Notification\Engine\Elements\Column;

/**
 * Header6 Elements
 */
class Header7 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'header_7';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Header::TYPE;
        $this->position = 70;
        $this->name     = __( 'Header 7', 'order100' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'background_color' => '#ffffff',
                    'children'         => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'align'   => 'left',
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-footer-img-1.png',
                                            'width'   => 195,
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
                            50,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: right;"><span style="font-size: 16px;">Support     Blog</span><span style="font-size: 16px;">     </span><span style="font-size: 16px;">FAQs</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 10,
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
                1,
                [
                    'background_color'       => '#ffffff',
                    'inner_background_color' => '#ffffff00',
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<h1 style="font-size: 22px; font-weight: 300; line-height: normal; margin: 0px; color: inherit; text-align: left;"><span style="font-size: 24px;"><strong>Order #[o100_order_number]</strong></span></h1>
                                            <h1 style="font-size: 22px; font-weight: 300; line-height: normal; margin: 0px; color: inherit; text-align: left;"><span style="font-size: 24px;"><strong>has been completed</strong></span></h1>',
                                            'text_color' => '#333439',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 40,
                                                'bottom' => 0,
                                                'left'   => 88,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/o100ne-header-img-1-scaled.png',
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

        ];
    }
}



// TS: 20260117222013

// TS: 20260204171600
