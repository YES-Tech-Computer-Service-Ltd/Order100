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
class Gallery2 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'gallery_2';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Gallery::TYPE;
        $this->position = 11;
        $this->name     = __( 'Gallery 2', 'order100' );
        $this->elements = [
            ColumnLayout::get_object_data(
                2,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 10,
                        'bottom' => 0,
                        'left'   => 0,
                        'right'  => 0,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            27,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-instagram-icon-2.png',
                                            'width'   => 22,
                                            'align'   => 'right',
                                            'padding' => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'right'  => 5,
                                                'left'   => 10,
                                            ],
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            73,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'background_color' => '#ffffff00',
                                            'text_color' => '#242527',
                                            'rich_text'  => '<p style="text-align: left; margin: 0;"><span style="font-size: 18px; font-weight: 600;">Follow us at @[o100_site_name]</span></p>',
                                            'padding'    => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'right'  => 10,
                                                'left'   => 5,
                                            ],
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
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 5,
                        'bottom' => 0,
                        'left'   => 10,
                        'right'  => 10,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-2-image-1.png',
                                            'width'   => 290,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 10,
                                                'bottom' => 5,
                                                'right'  => 5,
                                                'left'   => 10,
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
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-2-image-2.png',
                                            'width'   => 290,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 10,
                                                'bottom' => 5,
                                                'right'  => 10,
                                                'left'   => 5,
                                            ],
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
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 0,
                        'bottom' => 10,
                        'left'   => 10,
                        'right'  => 10,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            50,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-2-image-3.png',
                                            'width'   => 290,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 10,
                                                'right'  => 5,
                                                'left'   => 10,
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
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-2-image-4.png',
                                            'width'   => 290,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 10,
                                                'right'  => 10,
                                                'left'   => 5,
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



// TS: 20260104170421

// TS: 20260121235409
