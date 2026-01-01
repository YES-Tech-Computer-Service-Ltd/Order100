<?php
namespace Order100\Notification\Engine;

use Order100\Notification\Engine\Elements\ElementsHelper;
use Order100\Notification\Engine\Models\TemplateModel;
use Order100\Notification\Engine\Utils\Helpers;
use Order100\Notification\Engine\Utils\TemplateHelpers;
use Order100\Notification\Engine\Utils\TemplateRenderer;
use Order100\Notification\Engine\Utils\StyleInline;

/**
 * O100ne Template
 */
class O100neTemplate {

    /**
     * TemplateModel
     *
     * @var TemplateModel
     */
    private $model = null;

    /**
     * Contains template id
     *
     * @var number
     */
    private $id = 0;

    public $renderer = null;

    public const META_KEYS = [
        'name'                     => '_o100ne_template',
        'elements'                 => '_o100ne_elements',
        'status'                   => '_o100ne_status',
        'background_color'         => '_o100ne_email_backgroundColor_settings',
        'text_link_color'          => '_o100ne_email_textLinkColor_settings',
        'content_background_color' => '_o100ne_email_content_background_color',
        'content_text_color'       => '_o100ne_email_content_text_color',
        'title_color'              => '_o100ne_email_title_color',
        'language'                 => '_o100ne_template_language',
        'modified_by'              => '_o100ne_modified_by',
        'is_v4_supported'          => '_o100ne_is_v4_supported',
        'global_header_settings'   => '_o100ne_global_header_settings',
        'global_footer_settings'   => '_o100ne_global_footer_settings',
    ];

    public const DEFAULT_DATA = [
        'name'                     => '',
        'elements'                 => [],
        'status'                   => 0,
        'background_color'         => '',
        'text_link_color'          => '',
        'content_background_color' => '',
        'content_text_color'       => '',
        'title_color'              => '',
        'language'                 => '',
        'modified_by'              => '',
        'is_v4_supported'          => false,
        'global_header_settings'   => [
            'content_override' => false,
            'heading_content'  => '<h1 style="font-size: 30px; font-weight: 300; line-height: normal; margin: 0px; color: inherit;">Hello O100ne</h1>',
            'hidden'           => false,
        ],
        'global_footer_settings'   => [
            'content_override' => false,
            'footer_content'   => '<p style="font-size: 14px; margin: 0px 0px 16px; text-align: center;">[o100_site_name] - Built with <a style="color: #873eff; font-weight: normal; text-decoration: underline;" href="https://woocommerce.com" target="_blank" rel="noopener">WooCommerce</a></p>',
            'hidden'           => false,
        ],
    ];

    /**
     * Contains template data
     */
    private $data = [
        'name'                   => self::DEFAULT_DATA['name'],
        'elements'               => self::DEFAULT_DATA['elements'],
        'status'                 => self::DEFAULT_DATA['status'],
        'background_color'       => self::DEFAULT_DATA['background_color'],
        'text_link_color'        => self::DEFAULT_DATA['text_link_color'],
        'language'               => self::DEFAULT_DATA['language'],
        'title_color'            => self::DEFAULT_DATA['title_color'],
        'global_header_settings' => self::DEFAULT_DATA['global_header_settings'],
        'global_footer_settings' => self::DEFAULT_DATA['global_footer_settings'],
    ];

    public function __construct( $template_name = '', $language = '' ) {

        $this->model = TemplateModel::get_instance();

        if ( is_string( $template_name ) && ! empty( $template_name ) && Helpers::is_o100ne_email( $template_name ) ) {
            $template_data = $this->model::find_by_name( $template_name, $language );
            if ( empty( $template_data['id'] ) && SupportedPlugins::get_instance()->get_support_info( $template_name )['status'] === 'already_supported' ) {
                /** Insert new template when not exists */
                $template_data = $this->model::insert(
                    [
                        'name'                     => $template_name,
                        'elements'                 => o100ne_get_default_elements( $template_name ),
                        'language'                 => $language,
                        'background_color'         => self::DEFAULT_DATA['background_color'],
                        'text_link_color'          => self::DEFAULT_DATA['text_link_color'],
                        'content_background_color' => self::DEFAULT_DATA['content_background_color'],
                        'content_text_color'       => self::DEFAULT_DATA['content_text_color'],
                        'title_color'              => self::DEFAULT_DATA['title_color'],
                        'global_header_settings'   => self::DEFAULT_DATA['global_header_settings'],
                        'global_footer_settings'   => self::DEFAULT_DATA['global_footer_settings'],
                    ]
                );
            }
            $this->set_id( $template_data['id'] );
            $this->set_props( $template_data );
            // TODO: Consider filter available elements before pass to props
            $this->renderer = new TemplateRenderer( $this );
        }//end if
    }

    public function is_exists() {
        return is_numeric( $this->id ) && $this->id > 0;
    }

    public function is_enabled() {
        // Check if O100ne core is migrated
        // If not, consider template is not activated
        $old_version = get_option( 'o100_version' );
        if ( $old_version && version_compare( $old_version, '4.0.0', '<' ) ) {
            return false;
        }

        return $this->get_status() === 'active';
    }

    // GETTER METHOD

    private function get_prop( $prop, $context = 'view' ) {
        $value = null;

        if ( array_key_exists( $prop, $this->data ) ) {
            $value = $this->data[ $prop ];

            if ( 'view' === $context ) {
                $value = apply_filters( 'o100_template_get_' . $prop, $value, $this );
            }
        }

        return $value;
    }

    public function get_id() {
        return $this->id;
    }

    public function get_data() {
        return array_merge(
            [
                'id' => $this->get_id(),
            ],
            $this->data
        );
    }

    public function get_name( $context = 'view' ) {
        return $this->get_prop( 'name', $context );
    }

    public function get_elements( $context = 'view' ) {
        $elements = $this->get_prop( 'elements', $context );
        return ElementsHelper::filter_available_elements( $elements, $this->get_name() );
    }

    public function get_status( $context = 'view' ) {
        $value = $this->get_prop( 'status', $context );
        if ( is_numeric( $value ) || is_bool( $value ) ) {
            $value = empty( $value ) ? 'inactive' : 'active';
            // Process old value
        }
        if ( 'inactve' !== $value && 'active' !== $value ) {
            $value = 'inactive';
        }
        return $value;
    }

    public function get_background_color( $context = 'view' ) {
        $color = $this->get_prop( 'background_color', $context );
        return TemplateHelpers::convert_rgb_to_hex( $color );
    }

    public function get_text_link_color( $context = 'view' ) {
        return $this->get_prop( 'text_link_color', $context );
    }

    public function get_language( $context = 'view' ) {
        return $this->get_prop( 'language', $context );
    }

    public function get_title_color( $context = 'view' ) {
        return $this->get_prop( 'title_color', $context );
    }

    /**
     * Get global header
     *
     * @since 4.1.0
     *
     * @param string $context
     * @return array
     */
    public function get_global_header_settings( $context = 'view' ) {
        return $this->get_prop( 'global_header_settings', $context );
    }

    /**
     * Get global header
     *
     * @since 4.1.0
     *
     * @param string $context
     * @return array
     */
    public function get_global_footer_settings( $context = 'view' ) {
        return $this->get_prop( 'global_footer_settings', $context );
    }

    // SETTER METHOD

    public function set_props( $props ) {
        foreach ( $props as $prop_key => $prop_value ) {
            if ( is_null( $prop_value ) ) {
                continue;
            }
            $set_method = "set_$prop_key";
            if ( is_callable( [ $this, $set_method ] ) ) {
                $this->{$set_method}( $prop_value );
            }
        }
    }

    private function set_prop( $prop, $value ) {
        if ( array_key_exists( $prop, $this->data ) ) {
            $this->data[ $prop ] = $value;
        }
    }

    public function set_id( $id ) {
        $this->id = absint( $id );
    }

    public function set_name( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'name', $value );
        }
    }

    public function set_elements( $value ) {
        if ( ! is_null( $value ) && is_array( $value ) ) {
            $this->set_prop( 'elements', $value );
        }
    }

    public function set_status( $value ) {
        if ( ! is_null( $value ) ) {
            if ( is_numeric( $value ) || is_bool( $value ) ) {
                $value = empty( $value ) ? 'inactive' : 'active';
                // Process old value
            }
            if ( 'inactive' === $value || 'active' === $value ) {
                $this->set_prop( 'status', $value );
            }
        }
    }

    public function set_background_color( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'background_color', $value );
        }
    }

    public function set_text_link_color( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'text_link_color', $value );
        }
    }

    public function set_language( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'language', $value );
        }
    }

    public function set_title_color( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'title_color', $value );
        }
    }
    public function set_content_background_color( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'content_background_color', $value );
        }
    }
    public function set_content_text_color( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'content_text_color', $value );
        }
    }


    /**
     * Set global header
     *
     * @since 4.1.0
     *
     * @param array $value
     */
    public function set_global_header_settings( $value ) {
        if ( ! is_null( $value ) && is_array( $value ) ) {
            $this->set_prop( 'global_header_settings', $value );
        }
    }

    /**
     * Set global footer
     *
     * @since 4.1.0
     *
     * @param array $value
     */
    public function set_global_footer_settings( $value ) {
        if ( ! is_null( $value ) && is_array( $value ) ) {
            $this->set_prop( 'global_footer_settings', $value );
        }
    }

    // UPDATE - DELETE METHOD

    public function save() {
        if ( $this->get_id() ) {
            $this->model::update( $this->get_id(), $this->data );
        }
        return $this->get_id();
    }

    public function delete() {
        if ( $this->get_id() ) {
            $this->model::delete( $this->get_id() );
            return true;
        }
        return false;
    }

    public function get_content( $data ) {
        try {
            $type = get_post_meta( $this->id, '_o100ne_template_elements_type', true );
            
            if ( $type === 'mjml' ) {
                $html = get_post_meta( $this->id, '_o100ne_template_html', true );
                if ( ! empty( $html ) ) {
                    // Execute shortcodes inside the string
                    $template_name = $this->get_name();
                    $shortcodes = o100ne_get_email_shortcodes( $template_name );
                    $executor_args = [
                        'template'    => $this,
                        'render_data' => $data,
                        'settings'    => o100ne_settings(),
                    ];
                    // Instantiate executor to register shortcodes context
                    new \Order100\Notification\Engine\Shortcodes\ShortcodesExecutor( $shortcodes, $executor_args );
                    
                    $html = do_shortcode( $html );
                    $html = $this->process_conditional_sections( $html, $data );
                    return $html;
                }
            }

            if ( ! empty( $this->renderer ) ) {
                $html = StyleInline::get_instance()->convert_style_inline( $this->renderer->generate_content( $data ) );
                // Process conditional sections — remove blocks where conditions are not met
                $html = $this->process_conditional_sections( $html, $data );
                return $html;
            }
        } catch ( \Exception $e ) {
            o100ne_get_logger( $e->getMessage() );
        } catch ( \Error $e ) {
            o100ne_get_logger( $e->getMessage() );
        }
        return '';
    }

    /**
     * Process conditional sections in the email HTML.
     * Scans for elements with data-condition-field / data-condition-operator / data-condition-value
     * and removes sections where the condition evaluates to false.
     *
     * @param string $html       The email HTML content.
     * @param array  $render_data Contains 'order' (WC_Order) and other context.
     * @return string Filtered HTML.
     */
    private function process_conditional_sections( $html, $render_data ) {
        if ( strpos( $html, 'cond_o100_' ) === false && strpos( $html, 'data-condition-field' ) === false ) {
            return $html;
        }

        $order = isset( $render_data['order'] ) ? $render_data['order'] : null;
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        // Process legacy elements via regex (if they use data-condition-field)
        if ( strpos( $html, 'data-condition-field' ) !== false ) {
            $pattern = '/<([a-z]+)([^>]*data-condition-field="([^"]*)"[^>]*data-condition-operator="([^"]*)"[^>]*(?:data-condition-value="([^"]*)")?[^>]*)>(.*?)<\/\1>/si';
            $html = preg_replace_callback( $pattern, function ( $matches ) use ( $order ) {
                $field    = $matches[3];
                $operator = $matches[4];
                $value    = isset( $matches[5] ) ? $matches[5] : '';
                $actual = $this->get_condition_value( $order, $field );
                if ( $this->evaluate_condition( $actual, $operator, $value ) ) {
                    return $matches[0];
                }
                return '';
            }, $html );
        }

        // Process MJML/HTML elements with cond_o100_ class
        if ( strpos( $html, 'cond_o100_' ) !== false ) {
            // Strategy 1: If HTML has <body>, use DOMDocument on body content
            // Strategy 2: If MJML source (no <body>), use regex on mj-section tags
            $has_body = preg_match( '/(<body[^>]*>)(.*?)(<\/body>)/si', $html );

            if ( $has_body ) {
                // Compiled HTML path — use DOMDocument
                preg_match( '/(<body[^>]*>)(.*?)(<\/body>)/si', $html, $body_matches );
                $body_tag = $body_matches[1];
                $body_content = $body_matches[2];
                $body_close = $body_matches[3];

                $dom = new \DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadHTML( '<?xml encoding="UTF-8"><div id="o100ne_cond_wrapper">' . $body_content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
                libxml_clear_errors();

                $xpath = new \DOMXPath($dom);
                $nodes = $xpath->query('//*[contains(@class, "cond_o100_")]');

                $nodes_to_remove = [];

                foreach ($nodes as $node) {
                    $class = $node->getAttribute('class');
                    if ( preg_match('/cond_o100_([a-zA-Z0-9\-_]+)/', $class, $matches) ) {
                        $base64 = str_replace( ['-', '_'], ['+', '/'], $matches[1] );
                        $mod = strlen($base64) % 4;
                        if ($mod > 0) {
                            $base64 .= str_repeat('=', 4 - $mod);
                        }
                        $json = base64_decode( $base64 );
                        $cdata = json_decode( $json, true );

                        if ( $cdata && isset($cdata['f']) ) {
                            $actual = $this->get_condition_value( $order, $cdata['f'] );
                            if ( ! $this->evaluate_condition( $actual, $cdata['o'], $cdata['v'] ) ) {
                                $nodes_to_remove[] = $node;
                            } else {
                                $new_class = trim( str_replace( $matches[0], '', $class ) );
                                if ( empty($new_class) ) {
                                    $node->removeAttribute('class');
                                } else {
                                    $node->setAttribute('class', $new_class);
                                }
                            }
                        }
                    }
                }

                foreach ($nodes_to_remove as $node) {
                    if ( $node->parentNode ) {
                        $node->parentNode->removeChild($node);
                    }
                }

                $wrapper = $dom->getElementById('o100ne_cond_wrapper');
                if ( $wrapper ) {
                    $new_body_content = '';
                    foreach ($wrapper->childNodes as $child) {
                        $new_body_content .= $dom->saveHTML($child);
                    }
                    $html = str_replace( $body_matches[0], $body_tag . $new_body_content . $body_close, $html );
                }
            } else {
                // MJML source path — use regex to process <mj-section> blocks with cond_o100_
                // Match <mj-section ... css-class="...cond_o100_XXX..."...>...</mj-section>
                $html = preg_replace_callback(
                    '/<mj-section\b([^>]*cond_o100_[^>]*)>(.*?)<\/mj-section>/si',
                    function ( $matches ) use ( $order ) {
                        $attrs = $matches[1];
                        $inner = $matches[2];

                        // Extract the cond_o100_ token from css-class or class attribute
                        if ( ! preg_match( '/cond_o100_([a-zA-Z0-9\-_]+)/', $attrs, $cond_match ) ) {
                            return $matches[0]; // No condition found, keep as-is
                        }

                        $base64 = str_replace( ['-', '_'], ['+', '/'], $cond_match[1] );
                        $mod = strlen($base64) % 4;
                        if ($mod > 0) {
                            $base64 .= str_repeat('=', 4 - $mod);
                        }
                        $json = base64_decode( $base64 );
                        $cdata = json_decode( $json, true );

                        if ( ! $cdata || ! isset($cdata['f']) ) {
                            return $matches[0]; // Invalid condition data, keep
                        }

                        $actual = $this->get_condition_value( $order, $cdata['f'] );

                        if ( $this->evaluate_condition( $actual, $cdata['o'], $cdata['v'] ) ) {
                            // Condition met — keep the section, clean up the cond class
                            $clean_attrs = preg_replace( '/\s*cond_o100_[a-zA-Z0-9\-_]+/', '', $attrs );
                            $clean_attrs = preg_replace( '/\s*o100ne-conditional/', '', $clean_attrs );
                            return '<mj-section' . $clean_attrs . '>' . $inner . '</mj-section>';
                        }

                        // Condition NOT met — remove entire section
                        return '';
                    },
                    $html
                );
            }
        }

        return $html;
    }

    /**
     * Get the actual value for a condition field from the order or context.
     *
     * @param \WC_Order|null $order
     * @param string         $field
     * @return string
     */
    private function get_condition_value( $order, $field ) {
        if ( ! $order instanceof \WC_Order ) {
            return '';
        }

        switch ( $field ) {
            // Order100 fields — read o100_ first, fallback to exwfood_ for historical orders
            case 'o100_order_method':
            case 'exfood_order_method':
                $val = $order->get_meta( 'o100_order_method' );
                if ( $val === '' ) $val = $order->get_meta( 'exwfood_order_method' );
                return (string) $val;
            case 'o100_prep_time':
            case 'exfood_prep_time':
                $val = $order->get_meta( '_wooauto_confirmed_prep_time' );
                if ( $val === '' ) $val = $order->get_meta( 'o100_prep_time' );
                if ( $val === '' ) $val = $order->get_meta( 'exwfood_prep_time' );
                return (string) $val;
            case 'o100_timeslot':
            case 'exfood_timeslot':
                $val = $order->get_meta( 'o100_timeslot' );
                if ( $val === '' ) $val = $order->get_meta( 'o100_time_deli' );
                if ( $val === '' ) $val = $order->get_meta( 'exwfood_timeslot' );
                if ( $val === '' ) $val = $order->get_meta( 'exwfood_time_deli' );
                return (string) $val;
            case 'o100_tip_amount':
            case 'exfood_tip_amount':
                $tip = $order->get_meta( 'o100_tip_amount' );
                if ( $tip === '' ) $tip = $order->get_meta( '_o100_tip_amount' );
                if ( $tip === '' ) $tip = $order->get_meta( 'exwfood_tip_amount' );
                if ( $tip === '' ) $tip = $order->get_meta( '_exwfood_tip_amount' );
                return $tip !== '' ? (string) floatval( $tip ) : '0';

            // WooCommerce order fields
            case 'order_total':
                return (string) $order->get_total();
            case 'payment_method':
                return (string) $order->get_payment_method();
            case 'order_status':
                return (string) $order->get_status();
            case 'shipping_method':
                $methods = $order->get_shipping_methods();
                if ( ! empty( $methods ) ) {
                    $first = reset( $methods );
                    return $first->get_method_id();
                }
                return '';
            case 'coupon_used':
                return count( $order->get_coupon_codes() ) > 0 ? 'yes' : 'no';

            // Customer fields
            case 'customer_order_count':
                $customer_id = $order->get_customer_id();
                if ( $customer_id > 0 ) {
                    $customer = new \WC_Customer( $customer_id );
                    return (string) $customer->get_order_count();
                }
                return '1'; // Guest = first order
            case 'customer_role':
                $user_id = $order->get_customer_id();
                if ( $user_id > 0 ) {
                    $user = get_userdata( $user_id );
                    return $user ? implode( ',', $user->roles ) : '';
                }
                return 'guest';
            case 'is_guest':
                return $order->get_customer_id() > 0 ? 'no' : 'yes';

            // Order content fields
            case 'item_count':
                return (string) $order->get_item_count();
            case 'has_category':
                $cats = [];
                foreach ( $order->get_items() as $item ) {
                    $product_id = $item->get_product_id();
                    $terms      = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'slugs' ] );
                    if ( is_array( $terms ) ) {
                        $cats = array_merge( $cats, $terms );
                    }
                }
                return implode( ',', array_unique( $cats ) );

            default:
                // Try generic order meta
                $meta_val = $order->get_meta( '_' . $field );
                return $meta_val !== '' ? (string) $meta_val : (string) $order->get_meta( $field );
        }
    }

    /**
     * Evaluate a condition.
     *
     * @param string $actual   The actual value from order.
     * @param string $operator The comparison operator.
     * @param string $expected The expected value set in the editor.
     * @return bool
     */
    private function evaluate_condition( $actual, $operator, $expected ) {
        switch ( $operator ) {
            case 'equals':
                return strtolower( trim( $actual ) ) === strtolower( trim( $expected ) );
            case 'not_equals':
                return strtolower( trim( $actual ) ) !== strtolower( trim( $expected ) );
            case 'contains':
                return stripos( $actual, $expected ) !== false;
            case 'not_contains':
                return stripos( $actual, $expected ) === false;
            case 'greater_than':
                return floatval( $actual ) > floatval( $expected );
            case 'less_than':
                return floatval( $actual ) < floatval( $expected );
            case 'is_empty':
                return empty( trim( $actual ) );
            case 'is_not_empty':
                return ! empty( trim( $actual ) );
            default:
                return true; // Unknown operator — show by default
        }
    }
}

