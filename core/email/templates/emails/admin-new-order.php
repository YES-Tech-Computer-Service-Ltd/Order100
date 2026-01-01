<?php

use Order100\Notification\Engine\Emails\NewOrder;

defined( 'ABSPATH' ) || exit;

$template = NewOrder::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( array( 'order' => isset($order) ? $order : null, 'email' => isset($email) ? $email : null, 'email_heading' => isset($email_heading) ? $email_heading : '', 'additional_content' => isset($additional_content) ? $additional_content : '' ) );
    echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
