<?php
namespace Order100\Notification\Engine\TemplatePatterns\Patterns;

use Order100\Notification\Engine\Abstracts\BasePattern;
use Order100\Notification\Engine\Elements\Column;
use Order100\Notification\Engine\Elements\ColumnLayout;
use Order100\Notification\Engine\Elements\Image;
use Order100\Notification\Engine\Elements\Text;
use Order100\Notification\Engine\TemplatePatterns\SectionTemplates\Header;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Header2 Elements
 */
class Header2 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'header_2';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Header::TYPE;
        $this->position = 20;
        $this->name     = __( 'Header 2', 'order100' );
        $this->elements = [
            ColumnLayout::get_object_data(
                3,
                [
                    'background_color' => '#ffffff',
                    'children'         => [
                        Column::get_object_data(
                            32,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-footer-img-1.png',
                                            'width'   => 195,
                                            'padding' => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            5,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0;"><span style="font-size: 18px; font-weight: 100;">|</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 0,
                                                'bottom' => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#C4C6CC',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            68,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: left; margin: 0;"><span style="font-size: 18px; margin: 0px;">A store where you can shop everything</span></p>',
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
                        'left'   => 30,
                        'bottom' => 10,
                        'right'  => 30,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'rich_text'  => '<p style="text-align: left; margin: 0;"><span style="font-size: 16px;"><span style="margin-right: 25px;">Support</span> <span style="margin-right: 25px;">Blog</span> <span style="margin-right: 25px;">FAQs</span></span></p>',
                    'padding'    => [
                        'top'    => 0,
                        'right'  => 30,
                        'bottom' => 20,
                        'left'   => 30,
                    ],
                    'text_color' => '#333439',
                ]
            ),
        ];
    }
}
