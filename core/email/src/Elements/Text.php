<?php
namespace Order100\Notification\Engine\Elements;

use Order100\Notification\Engine\Abstracts\BaseElement;
use Order100\Notification\Engine\Constants\AttributesData;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Text Elements
 */
class Text extends BaseElement {

    use SingletonTrait;

    protected static $type = 'text';

    public $available_email_ids = [ O100NE_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        $default_content = '<p><span style="font-size: 18px;"><strong>This is a title</strong></span></p><p>&nbsp;</p><p><span> Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy.</span></p><p>&nbsp;</p><p><span>It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</span></p><p>&nbsp;</p><p><span>Various versions have evolved over the years.</span></p>';
        self::$icon      = '<svg viewBox="64 64 896 896" data-icon="form" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false" class=""><path d="M904 512h-56c-4.4 0-8 3.6-8 8v320H184V184h320c4.4 0 8-3.6 8-8v-56c0-4.4-3.6-8-8-8H144c-17.7 0-32 14.3-32 32v736c0 17.7 14.3 32 32 32h736c17.7 0 32-14.3 32-32V520c0-4.4-3.6-8-8-8z"></path><path d="M355.9 534.9L354 653.8c-.1 8.9 7.1 16.2 16 16.2h.4l118-2.9c2-.1 4-.9 5.4-2.3l415.9-415c3.1-3.1 3.1-8.2 0-11.3L785.4 114.3c-1.6-1.6-3.6-2.3-5.7-2.3s-4.1.8-5.7 2.3l-415.8 415a8.3 8.3 0 0 0-2.3 5.6zm63.5 23.6L779.7 199l45.2 45.1-360.5 359.7-45.7 1.1.7-46.4z"></path></svg>';

        return parent::merge_common_data(
            [
                'id'        => uniqid(),
                'type'      => self::$type,
                'name'      => __( 'Text', 'order100' ),
                'icon'      => self::$icon,
                'group'     => 'basic',
                'available' => true,
                'position'  => 70,
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
                    'border'                     => [
                        'value_path'    => 'border',
                        'component'     => 'Border',
                        'title'         => __( 'Border', 'order100' ),
                        'default_value' => isset( $attributes['border'] ) ? $attributes['border'] : AttributesData::BORDER_DEFAULT,
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
                    'font_family'                => [
                        'value_path'    => 'font_family',
                        'component'     => 'FontFamilySelector',
                        'title'         => __( 'Font family', 'order100' ),
                        'default_value' => isset( $attributes['font_family'] ) ? $attributes['font_family'] : O100NE_DEFAULT_FAMILY,
                        'type'          => 'style',
                    ],
                    'text_color'                 => [
                        'value_path'    => 'text_color',
                        'component'     => 'Color',
                        'title'         => __( 'Text color', 'order100' ),
                        'default_value' => isset( $attributes['text_color'] ) ? $attributes['text_color'] : O100NE_COLOR_TEXT_DEFAULT,
                        'type'          => 'style',
                    ],
                    'rich_text'                  => [
                        'value_path'    => 'rich_text',
                        'component'     => 'RichTextEditor',
                        'title'         => __( 'Content', 'order100' ),
                        'default_value' => isset( $attributes['rich_text'] ) ? $attributes['rich_text'] : $default_content,
                        'type'          => 'content',
                    ],
                ],
            ],
            $attributes
        );
    }
}

