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
class Gallery1 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'gallery_1';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Gallery::TYPE;
        $this->position = 10;
        $this->name     = __( 'Gallery 1', 'order100' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'inner_background_color' => '#ffffff00',
                    'padding'                => [
                        'top'    => 10,
                        'bottom' => 10,
                        'left'   => 40,
                        'right'  => 40,
                    ],
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-instagram-icon-1.png',
                                            'padding' => [
                                                'top'    => 10,
                                                'bottom' => 10,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'width'   => 50,
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0;"><span style="font-size: 18px; font-weight: 600;">EXPLORE OUR LATEST SPRING 2024 COLLECTION!</span></p>',
                                            'padding'    => [
                                                'top'    => 10,
                                                'right'  => 0,
                                                'bottom' => 20,
                                                'left'   => 0,
                                            ],
                                            'text_color' => '#242527',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-1-image.png',
                                            'padding' => [
                                                'top'    => 10,
                                                'bottom' => 15,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'width'   => 540,
                                        ]
                                    ),
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-1-image-2.png',
                                            'padding' => [
                                                'top'    => 0,
                                                'bottom' => 0,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'width'   => 540,
                                        ]
                                    ),
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-gallery-1-image-3.png',
                                            'padding' => [
                                                'top'    => 15,
                                                'bottom' => 20,
                                                'right'  => 0,
                                                'left'   => 0,
                                            ],
                                            'align'   => 'center',
                                            'background_color' => '#ffffff00',
                                            'width'   => 540,
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
