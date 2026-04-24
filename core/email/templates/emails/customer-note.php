<?php

use Order100\Notification\Engine\Emails\CustomerNote;

defined( 'ABSPATH' ) || exit;

$template = CustomerNote::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args ); // TODO: process args later.
    o100ne_kses_post_e( $content );
}



// TS: 20260420204938
