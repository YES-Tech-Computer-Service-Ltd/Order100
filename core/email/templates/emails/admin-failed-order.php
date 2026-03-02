<?php

use Order100\Notification\Engine\Emails\FailedOrder;

defined( 'ABSPATH' ) || exit;

$template = FailedOrder::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args ); // TODO: process args later.
    o100ne_kses_post_e( $content );
}


// TS: 20260109122458

// TS: 20260302144411
