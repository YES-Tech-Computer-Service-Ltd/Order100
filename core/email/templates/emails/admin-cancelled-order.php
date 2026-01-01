<?php

use Order100\Notification\Engine\Emails\CancelledOrder;

defined( 'ABSPATH' ) || exit;

$template = CancelledOrder::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args ); // TODO: process args later.
    o100ne_kses_post_e( $content );
}


