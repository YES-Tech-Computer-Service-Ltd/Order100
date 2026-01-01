<?php
namespace Order100\Notification\Engine\TemplatePatterns\Patterns;

use Order100\Notification\Engine\Abstracts\BasePattern;
use Order100\Notification\Engine\Elements\Column;
use Order100\Notification\Engine\Elements\ColumnLayout;
use Order100\Notification\Engine\Elements\Image;
use Order100\Notification\Engine\Elements\SocialIcon;
use Order100\Notification\Engine\Elements\Text;
use Order100\Notification\Engine\TemplatePatterns\SectionTemplates\Header;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Header4 Elements
 */
class Header4 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'header_4';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Header::TYPE;
        $this->position = 40;
        $this->name     = __( 'Header 4', 'order100' );
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
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-footer-img-1.png',
                                            'width'   => 195,
                                            'align'   => 'left',
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
                            50,
                            [
                                'children' => [
                                    SocialIcon::get_object_data(
                                        [
                                            'align'      => 'right',
                                            'spacing'    => 24,
                                            'width_icon' => 24,
                                            'style'      => 'SolidDark',
                                            'icon_list'  => [
                                                [
                                                    'icon' => 'twitter',
                                                    'url'  => '#',
                                                ],
                                                [
                                                    'icon' => 'facebook',
                                                    'url'  => '#',
                                                ],
                                                [
                                                    'icon' => 'instagram',
                                                    'url'  => '#',
                                                ],
                                            ],
                                            'padding'    => [
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
                    ],
                    'padding'          => [
                        'top'    => 30,
                        'left'   => 40,
                        'right'  => 40,
                        'bottom' => 30,
                    ],
                ]
            ),

        ];
    }
}

