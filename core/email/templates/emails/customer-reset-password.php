<?php

use Order100\Notification\Engine\Emails\CustomerResetPassword;

defined( 'ABSPATH' ) || exit;

$template = CustomerResetPassword::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args ); // TODO: process args later.
    o100ne_kses_post_e( $content );
}





// TS: 20260213185426

// TS: 20260223122044
