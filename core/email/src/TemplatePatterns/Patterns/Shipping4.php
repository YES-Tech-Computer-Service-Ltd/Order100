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
class Shipping4 extends BasePattern {
    use SingletonTrait;

    public const TYPE = 'shipping_4';

    public function __construct() {
        $this->id       = uniqid();
        $this->section  = Shipping::TYPE;
        $this->position = 13;
        $this->name     = __( 'Shipping 4', 'order100' );
        $this->elements = [
            ColumnLayout::get_object_data(
                1,
                [
                    'background_color'       => '#F1F2F5',
                    'padding'                => [
                        'top'    => 20,
                        'left'   => 20,
                        'bottom' => 20,
                        'right'  => 20,
                    ],
                    'inner_border_radius'    => [
                        'top_left'     => 10,
                        'top_right'    => 10,
                        'bottom_left'  => 10,
                        'bottom_right' => 10,
                    ],
                    'inner_background_color' => '#FFFFFF',
                    'children'               => [
                        Column::get_object_data(
                            100,
                            [
                                'children' => [
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-weight: 700;"><span style="font-size: 24px;">Yay!! Order Has Shipped!</span></p>',
                                            'padding'    => [
                                                'top'    => 15,
                                                'right'  => 40,
                                                'bottom' => 0,
                                                'left'   => 40,
                                            ],
                                            'text_color' => '#333439',
                                            'background_color' => '#F8FAFD',
                                        ]
                                    ),
                                    Text::get_object_data(
                                        [
                                            'rich_text'  => '<p style="text-align: center; margin: 0; font-weight: 400;"><span style="font-size: 16px; font-weight: 300;">Great news! Your order is on its way. You can find the shipping details below.</span></p>',
                                            'padding'    => [
                                                'top'    => 0,
                                                'right'  => 40,
                                                'bottom' => 15,
                                                'left'   => 40,
                                            ],
                                            'text_color' => '#333439',
                                            'background_color' => '#F8FAFD',
                                        ]
                                    ),
                                    Image::get_object_data(
                                        [
                                            'src'     => 'https://images.wpbrandy.com/uploads/o100ne-order-status.png',
                                            'width'   => 382,
                                            'align'   => 'center',
                                            'padding' => [
                                                'top'    => 20,
                                                'right'  => 40,
                                                'bottom' => 20,
                                                'left'   => 40,
                                            ],
                                            'background_color' => '#ffffff00',
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

