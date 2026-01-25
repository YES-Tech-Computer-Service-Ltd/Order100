<?php
namespace Order100\Notification\Engine\TemplatePatterns\Patterns;

use Order100\Notification\Engine\Abstracts\BasePattern;
use Order100\Notification\Engine\Elements\Column;
use Order100\Notification\Engine\Elements\ColumnLayout;
use Order100\Notification\Engine\Elements\Image;
use Order100\Notification\Engine\Elements\Text;
use Order100\Notification\Engine\TemplatePatterns\SectionTemplates\Gallery;
use Order100\Notification\Engine\Utils\SingletonTrait;

/** */
class Gallery8 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'gallery_8';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Gallery::TYPE;
        $this->position = 17;
        $this->name     = __( 'Gallery 8', 'order100' );
        $this->elements = [
            ColumnLayout::get_object_data(
                4,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 20,
                        'bottom' => 2,
                        'left'   => 20,
                        'right'  => 20,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            25,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-8-img-1.png',
                                            'width'   => 143,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
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
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-8-img-2.png',
                                            'width'   => 143,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
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
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-8-img-3.png',
                                            'width'   => 143,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
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
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-8-img-4.png',
                                            'width'   => 143,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'background_color' => '#ffffff',
                    'text_color'       => '#242527',
                    'rich_text'        => '<p style="text-align: center; margin: 0;"><span style="font-size: 16px; font-weight: 500;">Explore more at @[o100_site_name]</span></p>',
                    'padding'          => [
                        'top'    => 5,
                        'bottom' => 20,
                        'left'   => 50,
                        'right'  => 50,
                    ],
                ]
            ),
        ];
    }
}


// TS: 20260124122750
