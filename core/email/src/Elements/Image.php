<?php
namespace Order100\Notification\Engine\Elements;

use Order100\Notification\Engine\Abstracts\BaseElement;
use Order100\Notification\Engine\Utils\SingletonTrait;
/**
 * Images Elements
 */
class Image extends BaseElement {

    use SingletonTrait;

    protected static $type = 'image';

    public $available_email_ids = [ O100NE_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        $default_src = esc_url( O100NE_PLUGIN_URL . 'assets/images/default-photo.png' );
        self::$icon  = '<svg viewBox="64 64 896 896" data-icon="picture" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false" class=""><path d="M928 160H96c-17.7 0-32 14.3-32 32v640c0 17.7 14.3 32 32 32h832c17.7 0 32-14.3 32-32V192c0-17.7-14.3-32-32-32zm-40 632H136v-39.9l138.5-164.3 150.1 178L658.1 489 888 761.6V792zm0-129.8L664.2 396.8c-3.2-3.8-9-3.8-12.2 0L424.6 666.4l-144-170.7c-3.2-3.8-9-3.8-12.2 0L136 652.7V232h752v430.2zM304 456a88 88 0 1 0 0-176 88 88 0 0 0 0 176zm0-116c15.5 0 28 12.5 28 28s-12.5 28-28 28-28-12.5-28-28 12.5-28 28-28z"></path></svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Image', 'order100' ),
            'icon'      => self::$icon,
            'group'     => 'basic',
            'available' => true,
            'position'  => 30,
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
                'image_breaker'              => [
                    'component' => 'LineBreaker',
                ],
                'image_group_definition'     => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Image settings', 'order100' ),
                    'description' => __( 'Handle image settings', 'order100' ),
                ],
                'src'                        => [
                    'value_path'    => 'src',
                    'component'     => 'Media',
                    'title'         => __( 'Source image', 'order100' ),
                    'default_value' => isset( $attributes['src'] ) ? $attributes['src'] : $default_src,
                    'type'          => 'content',
                ],
                'align'                      => [
                    'value_path'    => 'align',
                    'component'     => 'Align',
                    'title'         => __( 'Image position', 'order100' ),
                    'default_value' => isset( $attributes['align'] ) ? $attributes['align'] : 'center',
                    'type'          => 'style',
                ],
                'width'                      => [
                    'value_path'    => 'width',
                    'component'     => 'Dimension',
                    'title'         => __( 'Width', 'order100' ),
                    'default_value' => isset( $attributes['width'] ) ? $attributes['width'] : '252',
                    'type'          => 'style',
                ],
                'url'                        => [
                    'value_path'    => 'url',
                    'component'     => 'TextInput',
                    'title'         => __( 'Open link', 'order100' ),
                    'default_value' => isset( $attributes['url'] ) ? $attributes['url'] : '#',
                    'type'          => 'content',
                ],
                'alt'                        => ElementsHelper::get_text_input(
                    $attributes,
                    [
                        'value_path'    => 'alt',
                        'title'         => __( 'ALT text', 'order100' ),
                        'default_value' => '',
                    ]
                ),
            ],
        ];
    }
}


// TS: 20260331004200

// TS: 20260412123715
