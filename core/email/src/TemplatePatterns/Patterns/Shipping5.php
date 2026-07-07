<?php
namespace Order100\Notification\Engine\TemplatePatterns\Patterns;

use Order100\Notification\Engine\Abstracts\BasePattern;
use Order100\Notification\Engine\Elements\Column;
use Order100\Notification\Engine\Elements\ColumnLayout;
use Order100\Notification\Engine\Elements\Image;
use Order100\Notification\Engine\Elements\Text;
use Order100\Notification\Engine\TemplatePatterns\SectionTemplates\Shipping;
use Order100\Notification\Engine\Utils\SingletonTrait;

/** */
class Shipping5 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'shipping_5';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Shipping::TYPE;
        $this->position = 14;
        $this->name     = __( 'Shipping 5', 'order100' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'background_color'       => '#ffffff00',
                    'padding'                => [
                        'top'    => 0,
                        'left'   => 0,
                        'bottom' => 0,
                        'right'  => 0,
                    ],
                    'inner_background_color' => '#ffffff00',
                    'background_image'       => [
                        'url'      => 'https://images.wpbrandy.com/uploads/o100ne-shipping-5-bg.png',
                        'position' => 'top_center',
                        'size'     => 'contain',
                    ],
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-footer-img-1.png',
                                            'width'   => 162,
                                            'align'   => 'center',
                                            'padding' => [
                                                'top'    => 30,
                                                'right'  => 40,
                                                'bottom' => 5,
                                                'left'   => 40,
                                            ],
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-weight: 700;"><span style="font-size: 24px;">Thank you for shopping with us!</span></p>',
                                            'padding'    => [
                                                'top'    => 5,
                                                'right'  => 40,
                                                'bottom' => 30,
                                                'left'   => 40,
                                            ],
                                            'text_color' => '#ffffff',
                                            'background_color' => '#ffffff00',
                                        ]
                                    ),
                                ],
                            ]
                        ),
                    ],
                ]
            ),
            Image::get_object_data(
                [
                    'src'              => 'https://images.wpbrandy.com/uploads/o100ne-order-status.png',
                    'width'            => 382,
                    'align'            => 'center',
                    'padding'          => [
                        'top'    => 20,
                        'right'  => 40,
                        'bottom' => 20,
                        'left'   => 40,
                    ],
                    'background_color' => '#ffffff',
                ]
            ),
        ];
    }
}


