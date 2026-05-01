<?php

/**
 * Template for POS refunded order email. WC 9.9.3
 * @since 4.0.6
 */
use Order100\Notification\Engine\Emails\CustomerPOSRefundedOrder;

defined( 'ABSPATH' ) || exit;

$template = CustomerPOSRefundedOrder::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args ); // TODO: process args later.
    o100ne_kses_post_e( $content );
}

// TS: 20260427025939

// TS: 20260430164932
