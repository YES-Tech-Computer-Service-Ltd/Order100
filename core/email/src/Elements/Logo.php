<?php
namespace Order100\Notification\Engine\Elements;

use Order100\Notification\Engine\Abstracts\BaseElement;
use Order100\Notification\Engine\Utils\SingletonTrait;
/**
 * Logo Elements
 */
class Logo extends BaseElement {

    use SingletonTrait;

    protected static $type = 'logo';

    public $available_email_ids = [ O100NE_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M6.98,9.32c-1.07,0-1.93-.87-1.93-1.93s.87-1.93,1.93-1.93,1.93.87,1.93,1.93-.87,1.93-1.93,1.93ZM6.98,6.95c-.24,0-.43.19-.43.43s.19.43.43.43.43-.19.43-.43-.19-.43-.43-.43Z"/>
  <path d="M10,2.5c4.14,0,7.5,3.36,7.5,7.5s-3.36,7.5-7.5,7.5-7.5-3.36-7.5-7.5,3.36-7.5,7.5-7.5M10,1C5.03,1,1,5.03,1,10s4.03,9,9,9,9-4.03,9-9S14.97,1,10,1h0Z"/>
  <path d="M4.12,16.28c-.22,0-.43-.09-.58-.27-.26-.32-.22-.79.1-1.06l8.42-6.92c.31-.25.75-.22,1.02.06l4.65,4.93c.29.3.27.78-.03,1.06-.3.28-.78.27-1.06-.03l-4.17-4.42-7.88,6.48c-.14.11-.31.17-.48.17Z"/>
</svg>';

        $profile = get_option('o100_store_profile', []);
        $store_logo = $profile['o100_store_logo'] ?? '';
        if (empty($store_logo)) {
            $custom_logo_id = get_theme_mod( 'custom_logo' );
            if ($custom_logo_id) {
                $image = wp_get_attachment_image_src( $custom_logo_id , 'full' );
                if ($image) $store_logo = $image[0];
            }
        }
        if (empty($store_logo)) {
            $store_logo = esc_url( O100NE_PLUGIN_URL . 'assets/images/woocommerce-logo.png' );
        }

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Logo', 'order100' ),
            'icon'      => self::$icon,
            'group'     => 'basic',
            'available' => true,
            'position'  => 10,
            'data'      => [
                'container_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Container settings', 'order100' ),
                    'description' => __( 'Handle container layout settings', 'order100' ),
                ],
                'padding'                    => ElementsHelper::get_spacing( $attributes ),
                'background_color'           => ElementsHelper::get_color( $attributes ),
                'logo_breaker'               => [
                    'component' => 'LineBreaker',
                ],
                'logo_group_definition'      => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Logo settings', 'order100' ),
                    'description' => __( 'Handle logo settings', 'order100' ),
                ],
                'src'                        => ElementsHelper::get_media(
                    $attributes,
                    [
                        'title' => __( 'Source image', 'order100' ),
                        'default_value' => $store_logo,
                    ]
                ),
                'align'                      => ElementsHelper::get_align(
                    $attributes,
                    [
                        'title' => __( 'Logo position', 'order100' ),
                    ]
                ),
                'width'                      => ElementsHelper::get_dimension( $attributes ),
                'url'                        => ElementsHelper::get_text_input(
                    $attributes,
                    [
                        'title' => __( 'Open link', 'order100' ),
                    ]
                ),
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



