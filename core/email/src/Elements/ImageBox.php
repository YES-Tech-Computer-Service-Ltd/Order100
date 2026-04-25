<?php
namespace Order100\Notification\Engine\Elements;

use Order100\Notification\Engine\Abstracts\BaseElement;
use Order100\Notification\Engine\Utils\SingletonTrait;
/**
 * ImageBox Elements
 */
class ImageBox extends BaseElement {

    use SingletonTrait;

    protected static $type = 'image_box';

    public $available_email_ids = [ O100NE_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        $src        = esc_url( O100NE_PLUGIN_URL . 'assets/images/default-photo.png' );
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M18.31,2.5H1.69c-.38,0-.69-.34-.69-.75s.31-.75.69-.75h16.62c.38,0,.69.34.69.75s-.31.75-.69.75Z"/>
  <path d="M18.31,7.75h-6.9c-.38,0-.69-.34-.69-.75s.31-.75.69-.75h6.9c.38,0,.69.34.69.75s-.31.75-.69.75Z"/>
  <path d="M18.31,13.75h-6.9c-.38,0-.69-.34-.69-.75s.31-.75.69-.75h6.9c.38,0,.69.34.69.75s-.31.75-.69.75Z"/>
  <path d="M18.31,19H1.69c-.38,0-.69-.34-.69-.75s.31-.75.69-.75h16.62c.38,0,.69.34.69.75s-.31.75-.69.75Z"/>
  <path d="M8.43,13.66H1l-.04-.79v-6.63l.79-.04h7.44l.04.79v6.63l-.79.04ZM2.46,12.16h5.27v-4.46H2.46v4.46Z"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Image Box', 'order100' ),
            'icon'      => self::$icon,
            'group'     => 'basic',
            'available' => true,
            'position'  => 120,
            'data'      => [
                'container_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Container settings', 'order100' ),
                    'description' => __( 'Handle container layout settings', 'order100' ),
                ],
                'background_color'           => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'order100' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'text_color'                 => [
                    'value_path'    => 'text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Text color', 'order100' ),
                    'default_value' => isset( $attributes['text_color'] ) ? $attributes['text_color'] : O100NE_COLOR_TEXT_DEFAULT,
                    'type'          => 'style',
                ],
                'column_breaker'             => [
                    'component' => 'LineBreaker',
                ],
                'column_settings'            => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Column settings', 'order100' ),
                    'description' => __( 'Handle column settings', 'order100' ),
                ],
                'image_box'                  => [
                    'component'  => 'ImageBox',
                    'value_path' => 'image_box',
                    'column_1'   => [
                        'padding' => [
                            'value' => $attributes['column_1']['padding'] ?? [
                                'top'    => '10',
                                'right'  => '10',
                                'bottom' => '10',
                                'left'   => '50',
                            ],
                            'type'  => 'style',
                        ],
                        'align'   => [
                            'value' => $attributes['column_1']['align'] ?? 'center',
                            'type'  => 'style',
                        ],
                        'image'   => [
                            'value' => $attributes['column_1']['image'] ?? $src,
                            'type'  => 'content',
                        ],
                        'width'   => [
                            'value' => $attributes['column_1']['width'] ?? '242',
                            'type'  => 'style',
                        ],
                        'url'     => [
                            'value' => $attributes['column_1']['url'] ?? '#',
                            'type'  => 'content',
                        ],
                        'alt'     => [
                            'value' => $attributes['column_1']['alt'] ?? '',
                            'type'  => 'content',
                        ],
                    ],
                    'column_2'   => [
                        'padding'     => [
                            'value' => $attributes['column_2']['padding'] ?? [
                                'top'    => '10',
                                'right'  => '50',
                                'bottom' => '10',
                                'left'   => '10',
                            ],
                            'type'  => 'style',
                        ],
                        'font_family' => [
                            'value' => $attributes['column_2']['font_family'] ?? O100NE_DEFAULT_FAMILY,
                            'type'  => 'style',
                        ],
                        'rich_text'   => [
                            'value' => $attributes['column_2']['rich_text'] ?? '<p><span style="font-size: 18px;"><strong>This is a title</strong></span></p><p><span> Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy.</span></p><p><span>It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</span></p>',
                            'type'  => 'content',
                        ],
                    ],
                ],
            ],
        ];
    }
}


// TS: 20260424191056
