<?php
namespace Order100\Notification\Engine\TemplatePatterns\Patterns;

use Order100\Notification\Engine\Abstracts\BasePattern;
use Order100\Notification\Engine\Elements\Column;
use Order100\Notification\Engine\Elements\ColumnLayout;
use Order100\Notification\Engine\Elements\Image;
use Order100\Notification\Engine\Elements\Text;
use Order100\Notification\Engine\TemplatePatterns\SectionTemplates\Intro;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Intro 4 Pattern
 */
class Intro4 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'intro_4';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Intro::TYPE;
        $this->position = 10;
        $this->name     = __( 'Intro 4', 'order100' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff00',
                    'column_spacing'         => 20,
                    'border_radius'          => [
                        'top_left'     => 15,
                        'top_right'    => 15,
                        'bottom_right' => 15,
                        'bottom_left'  => 15,
                    ],
                    'padding'                => [
                        'top'    => 13,
                        'bottom' => 0,
                        'left'   => 40,
                        'right'  => 42,
                    ],
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/o100ne-intro-3-bg.png',
                        'position'   => 'center_center',
                        'x_position' => 52,
                        'y_position' => 50,
                        'repeat'     => 'no-repeat',
                        'size'       => 'cover',
                    ],
                    'children'               => [
                        Column::get_object_data(
                            65,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 24px; text-align: left; margin: 0px;"><span style="font-size: 24px;font-weight: 700;">Welcome [o100_customer_username] 👋</span></p>',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 20,
                                                'bottom' => 0,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 18px; text-align: left; margin: 0px;"><span style="font-size: 18px;font-weight: 400;">We\'re excited to unveil our latest product, meticulously crafted with you in mind!</span></p>',
                                            'background_color' => '#ffffff00',
                                            'padding'    => [
                                                'top'    => 10,
                                                'bottom' => 25,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            35,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-intro-4-img.png',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                            'align'   => 'right',
                                            'background_color' => '#ffffff00',
                                            'width'   => 242,
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

// TS: 20260113171415

// TS: 20260123164622

// TS: 20260314134613

// TS: 20260406020809
