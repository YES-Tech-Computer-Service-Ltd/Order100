<?php

/**
 * Template for POS completed order email. WC 9.9.3
 * @since 4.0.6
 */

use Order100\Notification\Engine\Emails\CustomerPOSCompletedOrder;

defined( 'ABSPATH' ) || exit;

$template = CustomerPOSCompletedOrder::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args ); // TODO: process args later.
    o100ne_kses_post_e( $content );
}


// TS: 20260205165317

// TS: 20260224115534
