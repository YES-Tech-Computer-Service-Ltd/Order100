<?php
namespace Order100\Notification\Engine\Elements;

use Order100\Notification\Engine\Abstracts\BaseElement;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Footer Elements
 */
class Footer extends BaseElement {

    use SingletonTrait;

    protected static $type = 'footer';

    public $available_email_ids = [ O100NE_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M17.5,2.5v15H2.5V2.5h15M18,1H2c-.55,0-1,.45-1,1v16c0,.55.45,1,1,1h16c.55,0,1-.45,1-1V2c0-.55-.45-1-1-1h0Z"/>
  <rect x="3.02" y="13.01" width="13.95" height="4" rx=".24" ry=".24"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Footer', 'order100' ),
            'icon'      => self::$icon,
            'group'     => 'basic',
            'available' => true,
            'position'  => 150,
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
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#f9f9f9',
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
                'text_color'                 => [
                    'value_path'    => 'text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Text color', 'order100' ),
                    'default_value' => isset( $attributes['text_color'] ) ? $attributes['text_color'] : '#8a8a8a',
                    'type'          => 'style',
                ],
                'font_family'                => [
                    'value_path'    => 'font_family',
                    'component'     => 'FontFamilySelector',
                    'title'         => __( 'Font family', 'order100' ),
                    'default_value' => isset( $attributes['font_family'] ) ? $attributes['font_family'] : O100NE_DEFAULT_FAMILY,
                    'type'          => 'style',
                ],
                'rich_text'                  => [
                    'value_path'    => 'rich_text',
                    'component'     => 'RichTextEditor',
                    'title'         => __( 'Content', 'order100' ),
                    'default_value' => isset( $attributes['rich_text'] ) ? $attributes['rich_text'] : '<p style="font-size: 14px;margin: 0px 0px 16px; text-align: center;">[o100_site_name]&nbsp;- Built with <a style="color: ' . esc_attr( O100NE_COLOR_WC_DEFAULT ) . '; font-weight: normal; text-decoration: underline;" href="https://woocommerce.com" target="_blank" rel="noopener">WooCommerce</a></p>',
                    'type'          => 'content',
                ],
            ],
        ];
    }
}

