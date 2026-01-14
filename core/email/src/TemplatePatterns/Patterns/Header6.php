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
class Header6 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'header_6';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Header::TYPE;
        $this->position = 60;
        $this->name     = __( 'Header 6', 'order100' );
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
                                            'rich_text' => '<p style="text-align: right; margin: 0;"><span style="font-size: 16px; color: #333439;">Contact customer service</span></p>
                                            <p style="text-align: right; margin: 0;"><span style="font-size: 16px;"><span style="color: #ffc900; font-weight: 500;">1-987-654-1234</span></span></p>',
                                            'padding'   => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 10,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                    'padding'          => [
                        'top'    => 30,
                        'right'  => 40,
                        'bottom' => 30,
                        'left'   => 40,
                    ],
                ]
            ),

            Divider::get_object_data(
                [
                    'height'        => 1,
                    'width'         => 100,
                    'divider_color' => '#f1f2f5',
                    'padding'       => [
                        'top'    => 0,
                        'right'  => 0,
                        'bottom' => 0,
                        'left'   => 0,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="margin: 0px; text-align: center;"><span style="font-size: 16px;"><strong>New Arrivals         New Arrivals</strong></span><strong style="font-size: 16px;"><strong>          </strong>Outlet</strong><strong style="font-size: 16px;"><strong>          </strong>Contact</strong></p>',
                    'padding'    => [
                        'top'    => 20,
                        'right'  => 40,
                        'bottom' => 20,
                        'left'   => 40,
                    ],
                    'text_color' => '#333439',
                ]
            ),
        ];
    }
}


// TS: 20260114122431
