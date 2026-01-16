<?php

use Order100\Notification\Engine\Emails\CustomerInvoice;

defined( 'ABSPATH' ) || exit;

$template = CustomerInvoice::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args ); // TODO: process args later.
    o100ne_kses_post_e( $content );
}


// TS: 20260114222648
