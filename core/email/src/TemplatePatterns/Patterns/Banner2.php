<?php
namespace Order100\Notification\Engine\TemplatePatterns\Patterns;

use Order100\Notification\Engine\Abstracts\BasePattern;
use Order100\Notification\Engine\Elements\ColumnLayout;
use Order100\Notification\Engine\Elements\Column;
use Order100\Notification\Engine\Elements\Text;
use Order100\Notification\Engine\TemplatePatterns\SectionTemplates\Banner;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Banner2 Elements
 */
class Banner2 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'banner_2';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Banner::TYPE;
        $this->position = 20;
        $this->name     = __( 'Banner 2', 'order100' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 30,
                        'bottom' => 30,
                        'left'   => 0,
                        'right'  => 0,
                    ],
                    'background_image'       => [
                        'url'        => 'https://images.wpbrandy.com/uploads/o100ne-banner-2-img-1.jpg',
                        'position'   => 'custom',
                        'x_position' => 100,
                        'y_position' => 0,
                        'size'       => 'cover',
                        'repeat'     => 'no-repeat',
                    ],
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 18px; margin: 0px; text-align: center;"><span style="font-size: 30px;"><strong>New products for November Check \'em out!</strong></span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'left'   => 100,
                                                'right'  => 100,
                                            ],
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#333439',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="font-size: 16px; text-align: center; margin: 0;"><u>Explore Now</u> <img src="https://images.wpbrandy.com/uploads/o100ne-banner-img-arrow-1.png" alt="" width="12" height="12" style="width:20px !important; height:20px !important;"/></p>',
                                            'padding'    => [
                                                'top'    => 10,
                                                'bottom' => 0,
                                                'left'   => 0,
                                                'right'  => 0,
                                            ],
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#333439',
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


// TS: 20260124122750

// TS: 20260326174957
