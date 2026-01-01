<?php

namespace Order100\Notification\Engine\Utils;

use Order100\Notification\Engine\Models\TemplateModel;
use Order100\Notification\Engine\Shortcodes\ShortcodesExecutor;
use Order100\Notification\Engine\Models\SettingModel;
use Order100\Notification\Engine\O100neTemplate;


defined( 'ABSPATH' ) || exit;

/**
 * TemplateRenderer Classes
 * Define all utility functions to be used for rendering templates
 */
class TemplateRenderer {

    public $template = null;

    public function __construct( $template ) {
        if ( $template instanceof O100neTemplate ) {
            $this->template = $template;
        }
    }

    public function generate_content( $render_data ) {
        if ( empty( $this->template ) ) {
            return '';
        }

        // Handle the cases when order is numeric (order_id)
        if ( isset( $render_data['order'] ) && is_numeric( $render_data['order'] ) ) {
            $order = wc_get_order( $render_data['order'] );
            if ( $order ) {
                $render_data['order'] = $order;
            }
        }

        // TODO: Need to generate render_data based on email type
        $args = [
            'template'    => $this->template,
            'render_data' => $render_data,
            'settings'    => o100ne_settings(),
        ];

        return o100ne_get_content( 'templates/emails/email-content.php', $args );
    }
}


