<?php

namespace Order100\Notification\Engine\Shortcodes;

use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * LegacyCustomShortcodes - Handles legacy custom shortcodes
 *
 * @method static LegacyCustomShortcodes get_instance()
 */
class LegacyCustomShortcodes {

    use SingletonTrait;

    private $o100ne_settings;

    private function __construct() {

        $this->o100ne_settings = o100ne_settings();

        add_filter( 'o100_extra_shortcodes', [ $this, 'merge_legacy_custom_shortcodes' ], 10, 2 );
    }

    /**
     * Get legacy custom shortcodes
     *
     * @param array $data The data array.
     * @return array Array of migrated shortcodes.
     */
    public function merge_legacy_custom_shortcodes( $shortcodes, $data ) {
        $args                = $this->get_shortcode_args( $data );
        $o100ne_information = $this->get_o100ne_information( $data );

        $legacy_custom_shortcodes = array_merge(
            /**
             * @deprecated 4.0.0
             */
            apply_filters( 'o100_customs_shortcode', [], $o100ne_information, $args ),
            apply_filters( 'o100_custom_shortcode', [], $o100ne_information, $args )
        );

        $migrated_shortcodes = array_map(
            function( $shortcode_name, $shortcoded_content ) {
                $formatted_name = $this->format_shortcode_name( $shortcode_name );
                return [
                    'name'          => $formatted_name,
                    'description'   => $this->get_shortcode_description( $formatted_name ),
                    'group'         => 'custom_shortcodes',
                    'callback'      => $this->get_mockup_callback( $shortcode_name, $shortcoded_content ),
                    'callback_args' => [
                        'shortcode_name'     => $shortcode_name,
                        'shortcoded_content' => $shortcoded_content,
                    ],
                ];
            },
            array_keys( $legacy_custom_shortcodes ),
            array_values( $legacy_custom_shortcodes )
        );

        return array_merge( $shortcodes, $migrated_shortcodes );
    }

    private function get_shortcode_args( $data ) {
        // Bring the render_data to the top level, as it was in the legacy version of O100ne
        return array_merge( $data, $data['render_data'] ?? [] );
    }

    private function get_mockup_callback( $shortcode_name, $shortcoded_content ) {
        return function( $data, $args ) use ( $shortcode_name, $shortcoded_content ) {
            return $shortcoded_content;
        };
    }

    /**
     * Get shortcode description
     *
     * @param string $shortcode_name The shortcode name.
     * @return string The formatted description.
     */
    private function get_shortcode_description( $shortcode_name ) {
        // Get the part after 'o100_custom_shortcode_'
        $name_part = str_replace( 'o100_custom_shortcode_', '', $shortcode_name );

        // Convert underscores to spaces and capitalize words
        $formatted_name = ucwords( str_replace( '_', ' ', $name_part ) );

        /* translators: %s: The formatted shortcode name */
        return sprintf( __( 'Custom shortcode for %s', 'order100' ), $formatted_name );
    }

    /**
     * Format shortcode name
     *
     * @param string $shortcode_name The raw shortcode name.
     * @return string The formatted shortcode name.
     */
    private function format_shortcode_name( $shortcode_name ) {
        return str_replace( [ '[', ']' ], '', $shortcode_name );
    }

    /**
     * Get O100ne information from data
     *
     * @param array $data The data array.
     * @return array The O100ne information.
     */
    private function get_o100ne_information( $data ) {
        $template = $data['template'] ?? null;

        return [
            'post_id'          => isset( $template ) ? $template->get_id() : '',
            'template'         => $template,
            'order'            => $data['render_data']['order'] ?? null,
            'o100_elements' => $template ? $template->get_data()['elements'] : [],
            'general_settings' => array_merge(
                $this->o100ne_settings,
                [
                    'tableWidth'           => $this->o100ne_settings['container_width'],
                    'emailBackgroundColor' => $template ? $template->get_background_color() : '#ECECEC',
                    'textLinkColor'        => $template ? $template->get_text_link_color() : esc_attr( O100NE_COLOR_WC_DEFAULT ),
                ]
            ),

        ];
    }
}

// TS: 20260113213840

// TS: 20260312114528
