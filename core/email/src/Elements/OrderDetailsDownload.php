<?php
namespace Order100\Notification\Engine\Elements;

use Order100\Notification\Engine\Abstracts\BaseElement;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Emails\CustomerCompletedOrder;
use Order100\Notification\Engine\Emails\CustomerProcessingOrder;
use Order100\Notification\Engine\Constants\AttributesData;

/**
 * OrderDetailsDownload Elements
 */
class OrderDetailsDownload extends BaseElement {

    use SingletonTrait;

    protected static $type = 'order_details_download';

    protected function __construct() {
        $this->available_email_ids = [
            CustomerCompletedOrder::get_instance()->get_id(),
            CustomerProcessingOrder::get_instance()->get_id(),
        ];
    }

    public static function get_data( $attributes = [] ) {
        $is_email_improvements_enabled = get_option( 'woocommerce_feature_email_improvements_enabled', 'no' ) === 'yes';
        $layout_type                   = $is_email_improvements_enabled ? 'modern' : 'legacy';

        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M17.5,2.5v15H2.5V2.5h15M18,1H2c-.55,0-1,.45-1,1v16c0,.55.45,1,1,1h16c.55,0,1-.45,1-1V2c0-.55-.45-1-1-1h0Z"/>
  <path d="M18.05,8.1H1.82c-.41,0-.75-.34-.75-.75s.34-.75.75-.75h16.23c.41,0,.75.34.75.75s-.34.75-.75.75Z"/>
  <path d="M18.05,13.55H1.82c-.41,0-.75-.34-.75-.75s.34-.75.75-.75h16.23c.41,0,.75.34.75.75s-.34.75-.75.75Z"/>
  <path d="M18.05,18.99H1.82c-.41,0-.75-.34-.75-.75s.34-.75.75-.75h16.23c.41,0,.75.34.75.75s-.34.75-.75.75Z"/>
  <path d="M12.75,18.8c-.41,0-.75-.34-.75-.75V1.82c0-.41.34-.75.75-.75s.75.34.75.75v16.23c0,.41-.34.75-.75.75Z"/>
  <path d="M7.27,18.8c-.41,0-.75-.34-.75-.75V1.82c0-.41.34-.75.75-.75s.75.34.75.75v16.23c0,.41-.34.75-.75.75Z"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Order Details Download', 'order100' ),
            'icon'      => self::$icon,
            'group'     => 'woocommerce',
            'available' => true,
            'position'  => 200,
            'data'      => [
                'rich_text'                      => [
                    'value_path'    => 'rich_text',
                    'component'     => '',
                    'title'         => __( 'Content', 'order100' ),
                    'default_value' => isset( $attributes['rich_text'] ) ? $attributes['rich_text'] : '[o100_order_details_download_product]',
                    'type'          => 'content',
                ],
                'container_group_definition'     => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Container settings', 'order100' ),
                    'description' => __( 'Handle container layout settings', 'order100' ),
                ],
                'padding'                        => [
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
                'background_color'               => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'order100' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'border'                         => [
                    'value_path'    => 'border',
                    'component'     => 'Border',
                    'title'         => __( 'Border', 'order100' ),
                    'default_value' => isset( $attributes['border'] ) ? $attributes['border'] : AttributesData::BORDER_DEFAULT,
                    'type'          => 'style',
                ],
                'table_setting_breaker'          => [
                    'component' => 'LineBreaker',
                ],
                'table_group_definition'         => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Table settings', 'order100' ),
                    'description' => __( 'Handle table settings', 'order100' ),
                ],
                'layout_type'                    => [
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
                'title'                          => [
                    'value_path'    => 'title',
                    'component'     => 'RichTextEditor',
                    'title'         => __( 'Title', 'order100' ),
                    'default_value' => isset( $attributes['title'] ) ? $attributes['title'] : '<span style="font-size: 20px;font-weight:600;">Downloads</span>',
                    'type'          => 'content',
                ],
                'title_color'                    => [
                    'value_path'    => 'title_color',
                    'component'     => 'Color',
                    'title'         => __( 'Title color', 'order100' ),
                    'default_value' => isset( $attributes['title_color'] ) ? $attributes['title_color'] : O100NE_COLOR_WC_DEFAULT,
                    'type'          => 'style',
                ],
                'text_color'                     => [
                    'value_path'    => 'text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Text color', 'order100' ),
                    'default_value' => isset( $attributes['text_color'] ) ? $attributes['text_color'] : O100NE_COLOR_TEXT_DEFAULT,
                    'type'          => 'style',
                ],
                'border_color'                   => [
                    'value_path'    => 'border_color',
                    'component'     => 'Color',
                    'title'         => __( 'Border color', 'order100' ),
                    'default_value' => isset( $attributes['border_color'] ) ? $attributes['border_color'] : O100NE_COLOR_BORDER_DEFAULT,
                    'type'          => 'style',
                ],
                'font_family'                    => [
                    'value_path'    => 'font_family',
                    'component'     => 'FontFamilySelector',
                    'title'         => __( 'Font family', 'order100' ),
                    'default_value' => isset( $attributes['font_family'] ) ? $attributes['font_family'] : O100NE_DEFAULT_FAMILY,
                    'type'          => 'style',
                ],
                'table_content_font_size'        => [
                    'value_path'    => 'table_content_font_size',
                    'component'     => 'Dimension',
                    'title'         => __( 'Table content font size', 'order100' ),
                    'default_value' => isset( $attributes['table_content_font_size'] ) ? $attributes['table_content_font_size'] : '14',
                    'type'          => 'style',
                    'min'           => 8,
                    'max'           => 25,
                    'unit'          => 'px',
                ],
                'table_heading_line_breaker'     => [
                    'component' => 'LineBreaker',
                ],
                'table_heading_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Table heading settings', 'order100' ),
                    'description' => __( 'Handle table heading settings', 'order100' ),
                ],
                'table_heading_font_size'        => [
                    'value_path'    => 'table_heading_font_size',
                    'component'     => 'Dimension',
                    'title'         => __( 'Table heading font size', 'order100' ),
                    'default_value' => isset( $attributes['table_heading_font_size'] ) ? $attributes['table_heading_font_size'] : '14',
                    'type'          => 'style',
                    'min'           => 8,
                    'max'           => 25,
                    'unit'          => 'px',
                ],
                'product_title'                  => [
                    'value_path'    => 'product_title',
                    'component'     => 'TextInput',
                    'title'         => __( 'Product', 'order100' ),
                    'default_value' => esc_html__( 'Product', 'woocommerce' ),
                    'type'          => 'content',
                ],
                'expires_title'                  => [
                    'value_path'    => 'expires_title',
                    'component'     => 'TextInput',
                    'title'         => __( 'Expires', 'order100' ),
                    'default_value' => esc_html__( 'Expires', 'woocommerce' ),
                    'type'          => 'content',
                ],
                'download_title'                 => [
                    'value_path'    => 'download_title',
                    'component'     => 'TextInput',
                    'title'         => __( 'Download', 'order100' ),
                    'default_value' => esc_html__( 'Download', 'woocommerce' ),
                    'type'          => 'content',
                ],
            ],
        ];
    }
}
