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
class Gallery7 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'gallery_7';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Gallery::TYPE;
        $this->position = 16;
        $this->name     = __( 'Gallery 7', 'order100' );
        $this->elements = [
            Image::get_object_data(
                [
                    'src'     => 'https://images.wpbrandy.com/uploads/o100ne-instagram-icon-2.png',
                    'width'   => 30,
                    'align'   => 'center',
                    'alt'     => 'Icon',
                    'padding' => [
                        'top'    => 15,
                        'bottom' => 10,
                        'left'   => 30,
                        'right'  => 30,
                    ],
                ]
            ),
            Text::get_object_data(
                [
                    'background_color' => '#ffffff',
                    'text_color'       => '#242527',
                    'rich_text'        => '<p style="text-align: center; margin: 0;"><span style="font-size: 18px; font-weight: 600;">@by shop [o100_site_name]</span></p>',
                    'padding'          => [
                        'top'    => 0,
                        'bottom' => 0,
                        'left'   => 30,
                        'right'  => 30,
                    ],
                ]
            ),
            ColumnLayout::get_object_data(
                3,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 20,
                        'bottom' => 20,
                        'left'   => 20,
                        'right'  => 20,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            33,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-3-image-3.png',
                                            'width'   => 178,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
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
                            33,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-3-image-4.png',
                                            'width'   => 173,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
                                                'bottom' => 5,
                                                'right'  => 5,
                                                'left'   => 5,
                                            ],
                                        ]
                                    ),
                                ],
                            ]
                        ),
                        Column::get_object_data(
                            33,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-3-image-5.png',
                                            'width'   => 178,
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'padding' => [
                                                'top'    => 5,
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
        ];
    }
}


