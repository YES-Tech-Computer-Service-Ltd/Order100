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
 * Intro 1 Pattern
 */
class Intro1 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'intro_1';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Intro::TYPE;
        $this->position = 10;
        $this->name     = __( 'Intro 1', 'order100' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'inner_background_color' => '#F1F6FF',
                    'border_radius'          => [
                        'top_left'     => 20,
                        'top_right'    => 20,
                        'bottom_right' => 20,
                        'bottom_left'  => 20,
                    ],
                    'padding'                => [
                        'top'    => 40,
                        'bottom' => 40,
                        'right'  => 40,
                        'left'   => 40,
                    ],
                    'background_color'       => '#F1F6FF',
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-intro-1-img.png',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 10,
                                                'right'  => 50,
                                                'left'   => 50,
                                            ],
                                            'align'   => 'center',
                                            'width'   => 160,
                                            'background_color' => '#F1F6FF',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#F1F6FF',
                                            'text_color' => '#333439',
                                            'rich_text'  => '<p style="text-align: center; margin: 0;"><span style="font-size: 24px; font-weight: 700;"><b>Welcome [o100_customer_name] 👋</b></span></p>',
                                            'padding'    => [
                                                'top'    => 15,
                                                'bottom' => 5,
                                                'right'  => 50,
                                                'left'   => 50,
                                            ],
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#F1F6FF',
                                            'text_color' => '#77859B',
                                            'rich_text'  => '<p style="text-align: center; margin: 0;"><span style="font-size: 18px; font-weight: 400;">We\'re excited to unveil our latest product, meticulously crafted with you in mind!</span></p>',
                                            'padding'    => [
                                                'top'    => 5,
                                                'bottom' => 10,
                                                'right'  => 50,
                                                'left'   => 50,
                                            ],
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



// TS: 20260104123439
