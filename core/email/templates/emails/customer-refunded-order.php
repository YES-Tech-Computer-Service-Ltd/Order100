<?php

use Order100\Notification\Engine\Emails\CustomerRefundedOrder;

defined( 'ABSPATH' ) || exit;

$template = CustomerRefundedOrder::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args ); // TODO: process args later.
    o100ne_kses_post_e( $content );
}


// TS: 20260124225607

// TS: 20260504015117
