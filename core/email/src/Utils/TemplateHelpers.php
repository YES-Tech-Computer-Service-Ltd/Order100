<?php

namespace Order100\Notification\Engine\Utils;

use Order100\Notification\Engine\Constants\AttributesData;
use Order100\Notification\Engine\Constants\TemplatesData;

defined( 'ABSPATH' ) || exit;

/**
 * TemplateHelpers Classes
 * Define all utility functions to be used inside templates
 */
class TemplateHelpers {

    /**
     * @deprecated
     */
    public static function get_attribute_data( $attribute_data ) {
        $data = [];
        foreach ( $attribute_data as $key => $attribute ) {
            if ( 'image_box' === $key || 'image_list' === $key || 'text_list' === $key ) {
                if ( isset( $attribute['column_1'] ) ) {
                    $data['column_1'] = self::get_attribute_data( $attribute['column_1'] );
                }
                if ( isset( $attribute['column_2'] ) ) {
                    $data['column_2'] = self::get_attribute_data( $attribute['column_2'] );
                }
                if ( isset( $attribute['column_3'] ) ) {
                    $data['column_3'] = self::get_attribute_data( $attribute['column_3'] );
                }
            } elseif ( 'inner_background_color' === $key ) {
                $data[ $key ] = $attribute;
            } else {
                $data[ $key ] = $attribute['default_value'];
            }
        }
        return $data;
    }

    public static function get_spacing_value( $spacing, $unit = 'px' ) {
        $unit = esc_attr( $unit );
        return sprintf(
            '%d%s %d%s %d%s %d%s',
            isset( $spacing['top'] ) ? $spacing['top'] : 0,
            $unit,
            isset( $spacing['right'] ) ? $spacing['right'] : 0,
            $unit,
            isset( $spacing['bottom'] ) ? $spacing['bottom'] : 0,
            $unit,
            isset( $spacing['left'] ) ? $spacing['left'] : 0,
            $unit
        );
    }

    public static function get_border_radius_value( $border_radius, $unit = 'px' ) {
        $unit = esc_attr( $unit );
        return sprintf(
            '%d%s %d%s %d%s %d%s',
            isset( $border_radius['top_left'] ) ? $border_radius['top_left'] : 0,
            $unit,
            isset( $border_radius['top_right'] ) ? $border_radius['top_right'] : 0,
            $unit,
            isset( $border_radius['bottom_right'] ) ? $border_radius['bottom_right'] : 0,
            $unit,
            isset( $border_radius['bottom_left'] ) ? $border_radius['bottom_left'] : 0,
            $unit
        );
    }

    public static function get_dimension_value( $dimension, $unit = 'px' ) {
        $unit      = esc_attr( $unit );
        $dimension = floatval( $dimension );
        return "$dimension$unit";
    }

    public static function get_font_family_value( $font_family ) {
        if ( empty( $font_family ) ) {
            return 'inherit';
        }
        return str_replace( [ '\"','"' ], '', $font_family );
    }

    public static function wp_kses_allowed_html( $cus_attr_tags = [] ) {
        $allowed_html_tags           = wp_kses_allowed_html( 'post' );
        $allowed_html_tags['style']  = true;
        $allowed_html_tags['html']   = [];
        $allowed_html_tags['header'] = [];
        $allowed_html_tags['meta']   = [];
        $allowed_html_attr           = $cus_attr_tags;

        $allowed_html_attr ['data-o100ne-element-type'] = true;
        $allowed_html_attr ['charset']                   = true;
        $allowed_html_attr ['http-equiv']                = true;
        $allowed_html_attr ['content']                   = true;
        $allowed_html_attr ['name']                      = true;
        return array_map(
            function ( $item ) use ( $allowed_html_attr ) {
                return is_array( $item ) ? array_merge( $item, $allowed_html_attr ) : $item;
            },
            $allowed_html_tags
        );
    }

    public static function get_style( $css_properties = [] ) {
        return implode(
            ';',
            array_map(
                function ( $css_value, $css_name ) {
                    return "$css_name:$css_value";
                },
                $css_properties,
                array_keys( $css_properties )
            )
        ) . ';';
    }

    public static function wrap_element_content( $content_html, $element, $wrapper_style = null ) {
        $html = o100ne_get_content(
            'templates/elements/element-wrapper.php',
            [
                'content_html'  => $content_html,
                'element'       => $element,
                'wrapper_style' => $wrapper_style,
            ]
        );

        o100ne_kses_post_e( $html );
    }

    /**
     * The function returns the value based on the provided key, default value, and placeholder
     * flag.
     *
     * @param key Key parameter
     * @param default The default value is the value that will be returned if the key is empty or if the
     * is_placeholder parameter is false.
     * @param is_placeholder A boolean value indicating whether the value should be treated as a
     * placeholder or not.
     *
     * @return either the value of the  variable or the placeholder "[[]]" depending on
     * the values of the  and  variables.
     */
    public static function get_content_as_placeholder( $key, $default, $is_placeholder ) {
        return $is_placeholder && ! empty( $key ) ? "[[{$key}]]" : $default;
    }

    public static function get_booking_from_order( $order ) {
        $booking_ids = [];

        if ( null !== $order ) {
            if ( is_callable( 'WC_Booking_Data_Store::get_booking_ids_from_order_id' ) ) {
                $booking_data = new \WC_Booking_Data_Store();
                $booking_ids  = $booking_data->get_booking_ids_from_order_id( $order->get_id() );
            }

            if ( ! empty( $booking_ids ) ) {
                return new \WC_Booking( $booking_ids[0] );
            }
        }

        return null;
    }

    public static function get_font_size( $size, $is_subtitle = false ) {
        if ( 'default' === $size && $is_subtitle ) {
            return '13px';
        }
        $result  = isset( AttributesData::TITLE_SIZE_OPTIONS[ $size ] ) ? AttributesData::TITLE_SIZE_OPTIONS[ $size ] : 16;
        $result .= 'px';
        return $result;
    }

    /**
     * Remove empty shortcodes from the content
     *
     * @param string $content The content to remove empty shortcodes from
     * @return string The content with empty shortcodes removed
     * @since 4.0.2
     */
    public static function remove_empty_shortcodes( $content ) {
        $content = preg_replace( '/<p\b[^>]*>\[o100_[^\]]*\]<\/p>/i', '', $content );
        $content = preg_replace( '/\[o100_[^\]]*\]/', '', $content );
        return $content;
    }

    public static function convert_rgb_to_hex( $color ) {
        if ( is_string( $color ) && strpos( $color, 'rgb' ) === 0 ) {
            $rgb  = str_replace( 'rgb(', '', $color );
            $rgb  = str_replace( ')', '', $rgb );
            $rgb  = explode( ',', $rgb );
            $hex  = '#';
            $hex .= str_pad( dechex( $rgb[0] ), 2, '0', STR_PAD_LEFT );
            $hex .= str_pad( dechex( $rgb[1] ), 2, '0', STR_PAD_LEFT );
            $hex .= str_pad( dechex( $rgb[2] ), 2, '0', STR_PAD_LEFT );
            return $hex;
        } else {
            return $color;
        }
    }

    /**
     * Find element by id in the list of elements
     *
     * @param string $id The id of the element to find.
     * @param array  $list_elements The list of elements to search in.
     * @return array|null The element if found, null otherwise
     * @since 4.1.0
     */
    public static function find_element_by_id( $id, $list_elements ) {
        foreach ( $list_elements as $element ) {
            if ( $element['id'] === $id ) {
                return $element;
            }
            if ( $element['children'] && count( $element['children'] ) > 0 ) {
                $result = self::find_element_by_id( $id, $element['children'] );
                if ( $result ) {
                    return $result;
                }
            }
        }
        return null;
    }

    public static function find_parent_element( $id, $list_elements ) {

        foreach ( $list_elements as $element ) {
            if ( empty( $element['children'] ) ) {
                continue;
            }
            if ( in_array( $id, array_column( $element['children'], 'id' ) ) ) {
                return $element;
            }
            $sub_query = self::find_parent_element( $id, $element['children'] );
            if ( $sub_query ) {
                return $sub_query;
            }
        }

        return null;
    }

    public static function get_current_column_index( $id, $list_elements ) {
        $element = self::find_element_by_id( $id, $list_elements );
        if ( empty( $element ) ) {
            return 0;
        }
        $parent_element = self::find_parent_element( $element['id'], $list_elements );
        if ( empty( $parent_element ) ) {
            return 0;
        }
        $current_column_index = array_search( $element['id'], array_column( $parent_element['children'], 'id' ) );
        return $current_column_index;
    }

    public static function get_border_css_value( $border ) {
        if ( $border['side'] === 'none' ) {
            return '';
        }
        if ( $border['side'] === 'all' ) {
            return self::get_style(
                [
                    'border' => self::get_border_style( $border ),
                ]
            );
        }
        if ( $border['side'] === 'top' ) {
            return self::get_style(
                [
                    'border-top' => self::get_border_style( $border ),
                ]
            );
        }
        if ( $border['side'] === 'bottom' ) {
            return self::get_style(
                [
                    'border-bottom' => self::get_border_style( $border ),
                ]
            );
        }
        if ( $border['side'] === 'right' ) {
            return self::get_style(
                [
                    'border-right' => self::get_border_style( $border ),
                ]
            );
        }
        if ( $border['side'] === 'left' ) {
            return self::get_style(
                [
                    'border-left' => self::get_border_style( $border ),
                ]
            );
        }
        if ( $border['side'] === 'custom' ) {
            return self::get_style(
                [
                    'border-top'    => self::get_border_style(
                        [
                            'width' => $border['custom']['top'],
                            'style' => $border['style'],
                            'color' => $border['color'],
                        ]
                    ),
                    'border-right'  => self::get_border_style(
                        [
                            'width' => $border['custom']['right'],
                            'style' => $border['style'],
                            'color' => $border['color'],
                        ]
                    ),
                    'border-bottom' => self::get_border_style(
                        [
                            'width' => $border['custom']['bottom'],
                            'style' => $border['style'],
                            'color' => $border['color'],
                        ]
                    ),
                    'border-left'   => self::get_border_style(
                        [
                            'width' => $border['custom']['left'],
                            'style' => $border['style'],
                            'color' => $border['color'],
                        ]
                    ),
                ]
            );
        }//end if
        return '';
    }

    public static function get_border_style( $border, $unit = 'px' ) {
        $unit = esc_attr( $unit );
        return sprintf(
            '%d%s %s %s',
            $border['width'],
            $unit,
            $border['style'],
            $border['color']
        );
    }

    public static function sanitize_elements_recursive( $elements ) {
        if ( ! is_array( $elements ) ) {
            return [];
        }

        foreach ( $elements as &$element ) {
            if ( isset( $element['data']['rich_text'] ) ) {
                $element['data']['rich_text'] = o100ne_kses_post( $element['data']['rich_text'], $allowed );
            }

            if ( isset( $element['data']['title'] ) ) {
                $element['data']['title'] = o100ne_kses_post( $element['data']['title'] );
            }

            // Recursive cho nested elements
            if ( isset( $element['children'] ) ) {
                $element['children'] = self::sanitize_elements_recursive( $element['children'] );
            }
        }

        return $elements;
    }
}



// TS: 20260312214641
