<?php

use Order100\Notification\Engine\Emails\CustomerProcessingOrder;

defined( 'ABSPATH' ) || exit;

$template = CustomerProcessingOrder::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( array( 'order' => isset($order) ? $order : null, 'email' => isset($email) ? $email : null, 'email_heading' => isset($email_heading) ? $email_heading : '', 'additional_content' => isset($additional_content) ? $additional_content : '' ) );
    o100ne_kses_post_e( $content );
}



// TS: 20260106125033
