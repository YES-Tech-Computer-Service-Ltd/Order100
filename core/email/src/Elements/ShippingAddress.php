<?php
namespace Order100\Notification\Engine\Elements;

use Order100\Notification\Engine\Abstracts\BaseElement;
use Order100\Notification\Engine\Utils\SingletonTrait;
/**
 * Shipping Address Elements
 */
class ShippingAddress extends BaseElement {

    use SingletonTrait;

    protected static $type = 'shipping_address';

    public $available_email_ids = [ O100NE_WITH_ORDER_EMAILS ];

    public static function get_data( $attributes = [] ) {
        $is_email_improvements_enabled = get_option( 'woocommerce_feature_email_improvements_enabled', 'no' ) === 'yes';
        $layout_type                   = $is_email_improvements_enabled ? 'modern' : 'legacy';

        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M7.35,13.86h5.3c.09,0,.16-.07.16-.16v-1.69c0-.09-.07-.16-.16-.16h-.8c-.09,0-.16.07-.16.16v.72h-3.37v-.72c0-.09-.07-.16-.16-.16h-.8c-.09,0-.16.07-.16.16v1.69c0,.09.07.16.16.16ZM14.18,11.39c0,.21.08.42.24.57.15.15.36.24.57.24s.42-.08.57-.24c.15-.15.24-.36.24-.57s-.08-.42-.24-.57c-.15-.15-.36-.24-.57-.24s-.42.08-.57.24c-.15.15-.24.36-.24.57ZM18.98,8.02l-.48-.83s-.06-.06-.1-.07c-.04-.01-.08,0-.12.02l-1.02.59-1.57-4.34c-.08-.26-.25-.49-.47-.65-.22-.16-.49-.25-.76-.25H5.77c-.7,0-1.32.45-1.53,1.11l-1.5,4.12-1.02-.59s-.08-.03-.12-.02c-.04.01-.08.04-.1.07l-.48.83c-.04.08-.02.17.06.22l1.21.71-.29.8c-.02.06-.04.13-.04.2v6.99c0,.32.24.57.53.57h1.36c.25,0,.46-.19.51-.45l.15-.76h10.96l.15.76c.05.26.27.45.51.45h1.36c.29,0,.53-.26.53-.57v-6.99c0-.07-.01-.14-.04-.2l-.29-.8,1.21-.71s.06-.06.07-.1c.01-.04,0-.08-.01-.12ZM16.59,10.1v4.76H3.41v-4.76l.31-.86h12.55l.31.86ZM5.6,4.1v-.03s.02-.03.02-.03c.02-.07.08-.11.15-.11h8.59l1.51,4.18H4.14l1.46-4.02ZM4.22,11.39c0,.21.08.42.24.57.15.15.36.24.57.24s.42-.08.57-.24c.15-.15.24-.36.24-.57s-.08-.42-.24-.57c-.15-.15-.36-.24-.57-.24s-.42.08-.57.24c-.15.15-.24.36-.24.57Z"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Shipping Address', 'woocommerce' ),
            'icon'      => self::$icon,
            'group'     => 'woocommerce',
            'available' => true,
            'position'  => 160,
            'data'      => [
                'container_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Container settings', 'order100' ),
                    'description' => __( 'Handle container layout settings', 'order100' ),
                ],
                'padding'                    => [
                    'value_path'    => 'padding',
                    'component'     => 'Spacing',
                    'title'         => __( 'Padding', 'order100' ),
                    'default_value' => isset( $attributes['padding'] ) ? $attributes['padding'] : [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'type'          => 'style',
                ],
                'background_color'           => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'order100' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'content_breaker'            => [
                    'component' => 'LineBreaker',
                ],
                'content_group_definition'   => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Content settings', 'order100' ),
                    'description' => __( 'Handle content settings', 'order100' ),
                ],
                'layout_type'                => [
                    'value_path'    => 'layout_type',
                    'component'     => 'Selector',
                    'title'         => __( 'Layout type', 'order100' ),
                    'default_value' => isset( $attributes['layout_type'] ) ? $attributes['layout_type'] : $layout_type,
                    'options'       => [
                        [
                            'label' => __( 'Legacy', 'order100' ),
                            'value' => 'legacy',
                        ],
                        [
                            'label' => __( 'Modern', 'order100' ),
                            'value' => 'modern',
                        ],
                    ],
                    'type'          => 'content',
                ],
                'title_color'                => [
                    'value_path'    => 'title_color',
                    'component'     => 'Color',
                    'title'         => __( 'Title color', 'order100' ),
                    'default_value' => isset( $attributes['title_color'] ) ? $attributes['title_color'] : O100NE_COLOR_WC_DEFAULT,
                    'type'          => 'style',
                ],
                'text_color'                 => [
                    'value_path'    => 'text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Text color', 'order100' ),
                    'default_value' => isset( $attributes['text_color'] ) ? $attributes['text_color'] : O100NE_COLOR_TEXT_DEFAULT,
                    'type'          => 'style',
                ],
                'border_color'               => [
                    'value_path'    => 'border_color',
                    'component'     => 'Color',
                    'title'         => __( 'Table border color', 'order100' ),
                    'default_value' => isset( $attributes['border_color'] ) ? $attributes['border_color'] : O100NE_COLOR_BORDER_DEFAULT,
                    'type'          => 'style',
                ],
                'font_family'                => [
                    'value_path'    => 'font_family',
                    'component'     => 'FontFamilySelector',
                    'title'         => __( 'Font family', 'order100' ),
                    'default_value' => isset( $attributes['font_family'] ) ? $attributes['font_family'] : O100NE_DEFAULT_FAMILY,
                    'type'          => 'style',
                ],
                'title'                      => [
                    'value_path'    => 'title',
                    'component'     => 'RichTextEditor',
                    'title'         => __( 'Shipping title', 'order100' ),
                    'default_value' => isset( $attributes['title'] ) ? $attributes['title'] : ( '<span style="font-size: 20px;font-weight:600;">' . __( 'Shipping Address', 'woocommerce' ) . '</span>' ),
                    'type'          => 'content',
                ],
                'rich_text'                  => [
                    'value_path'    => 'rich_text',
                    'component'     => '',
                    'title'         => __( 'Content', 'order100' ),
                    'default_value' => '[o100_shipping_address]',
                    'type'          => 'content',
                ],
            ],
        ];
    }
}

// TS: 20260104170421

// TS: 20260418020931
