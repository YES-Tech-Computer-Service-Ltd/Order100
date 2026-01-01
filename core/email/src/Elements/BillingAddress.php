<?php
namespace Order100\Notification\Engine\Elements;

use Order100\Notification\Engine\Abstracts\BaseElement;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Billing Address Elements
 */
class BillingAddress extends BaseElement {

    use SingletonTrait;

    protected static $type = 'billing_address';

    public $available_email_ids = [ O100NE_WITH_ORDER_EMAILS ];

    public static function get_data( $attributes = [] ) {
        $is_email_improvements_enabled = get_option( 'woocommerce_feature_email_improvements_enabled', 'no' ) === 'yes';
        $layout_type                   = $is_email_improvements_enabled ? 'modern' : 'legacy';

        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M17.5,2.5v15H2.5V2.5h15M18.5,1H1.5c-.28,0-.5.22-.5.5v17c0,.28.22.5.5.5h17c.28,0,.5-.22.5-.5V1.5c0-.28-.22-.5-.5-.5h0Z"/>
  <path d="M6.79,7.33c.57,0,1.04.47,1.04,1.04s-.47,1.04-1.04,1.04-1.04-.47-1.04-1.04.47-1.04,1.04-1.04M6.79,5.83c-1.4,0-2.54,1.14-2.54,2.54s1.14,2.54,2.54,2.54,2.54-1.14,2.54-2.54-1.14-2.54-2.54-2.54h0Z"/>
  <path d="M10.75,13.46h-1.5c0-1.25-1.01-2.26-2.26-2.26s-2.26,1.01-2.26,2.26h-1.5c0-2.07,1.69-3.76,3.76-3.76s3.76,1.69,3.76,3.76Z"/>
  <rect x="12.24" y="7.5" width="2.94" height="1.5"/>
  <rect x="12.24" y="10.87" width="4.62" height="1.5"/>
</svg>';

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Billing Address', 'woocommerce' ),
            'icon'      => self::$icon,
            'group'     => 'woocommerce',
            'available' => true,
            'position'  => 170,
            'data'      => [
                'container_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Container settings', 'order100' ),
                    'description' => __( 'Handle container layout settings', 'order100' ),
                ],
                'padding'                    => ElementsHelper::get_spacing( $attributes ),
                'background_color'           => ElementsHelper::get_color( $attributes, [ 'default_value' => '#fff' ] ),
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
                'title_color'                => ElementsHelper::get_color(
                    $attributes,
                    [
                        'value_path'    => 'title_color',
                        'title'         => __( 'Title color', 'order100' ),
                        'default_value' => O100NE_COLOR_WC_DEFAULT,
                    ]
                ),

                'text_color'                 => ElementsHelper::get_color(
                    $attributes,
                    [
                        'value_path'    => 'text_color',
                        'title'         => __( 'Text color', 'order100' ),
                        'default_value' => O100NE_COLOR_TEXT_DEFAULT,
                    ]
                ),

                'border_color'               => ElementsHelper::get_color(
                    $attributes,
                    [
                        'value_path'    => 'border_color',
                        'title'         => __( 'Table border color', 'order100' ),
                        'default_value' => O100NE_COLOR_BORDER_DEFAULT,
                    ]
                ),
                'font_family'                => ElementsHelper::get_font_family_selector( $attributes ),
                'title'                      => ElementsHelper::get_rich_text(
                    $attributes,
                    [
                        'value_path'    => 'title',
                        'title'         => __( 'Billing title', 'order100' ),
                        'default_value' => '<span style="font-size: 20px;font-weight:600;">' . __( 'Billing Address', 'woocommerce' ) . '</span>',
                    ]
                ),
                'rich_text'                  => [
                    'value_path'    => 'rich_text',
                    'component'     => '',
                    'title'         => __( 'Content', 'order100' ),
                    'default_value' => '[o100_billing_address]',
                    'type'          => 'content',
                ],
            ],
        ];
    }
}


