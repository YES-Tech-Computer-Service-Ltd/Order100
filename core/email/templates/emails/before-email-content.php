<?php
$dir                    = is_rtl() ? 'rtl' : 'ltr';
$template_exclude_style = apply_filters( 'o100_template_exclude_style', [] );
$o100ne_settings       = o100ne_settings();
$container_width        = isset( $o100ne_settings['container_width'] ) && is_numeric( $o100ne_settings['container_width'] ) ? $o100ne_settings['container_width'] : '605';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="<?php echo esc_attr( $dir ); ?>">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <meta name="x-apple-disable-message-reformatting" />
        <?php if ( ! in_array( $template->get_name(), $template_exclude_style ) ) : ?>
            <style>
                h1{ font-family:inherit;text-shadow:unset;text-align:inherit;}
                h2,h3{ font-family:inherit;color:inherit;text-align:inherit;}
                .o100ne-inline-block {display: inline-block;}
                .o100ne-customizer-email-template-container a {color: <?php echo esc_attr( $template->get_text_link_color() ); ?>}
                .o100ne-order-details-table .wc-item-meta {list-style-type: none;}
                /* Ensure container width remains constant on mobile */
                @media screen and (max-width: 600px) {
                    .o100ne-template-content-container {
                        width: <?php echo esc_attr( $container_width ); ?>px !important;
                    }
                }
            /**
            * Media queries are not supported by all email clients, however they do work on modern mobile
            * Gmail clients and can help us achieve better consistency there.
            */
            /* @media screen and (max-width: 600px) {
                .o100ne-template-content-container {
                    width: 100% !important;
                }
                .o100ne-template-content-container .o100ne-element__content {
                    padding: 8px 15px !important;
                }
                .o100ne-template-content-container .o100ne-billing-address-column,
                .o100ne-template-content-container .o100ne-shipping-address-column {
                    display: block !important;
                    width: 100% !important;
                }
                .o100ne-template-content-container .o100ne-shipping-address-column {
                    margin-top: 15px;
                }
                .o100ne-template-content-container .o100ne_item_product_title {
                    min-width: 120px;
                    width: 100%;
                }
            } */
            </style>
        <?php endif; ?>
    </head>
    <body style="background: <?php echo esc_attr( $template->get_background_color() ); ?>" <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">


