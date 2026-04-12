<?php

use Order100\Notification\Engine\Utils\TemplateHelpers;

extract( isset( $args ) ? $args : [] );

if ( empty( $element ) ) {
    return;
}

if ( ! isset( $content_html ) ) {
    $content_html = '';
}

if ( empty( $wrapper_style ) ) {
    $wrapper_style = '';
}

$o100ne_settings    = o100ne_settings();
$container_direction = o100ne_get_email_direction();


$wrapper_style .= TemplateHelpers::get_style(
    [
        'border-spacing'  => '0',
        'width'           => '100%',
        'direction'       => $container_direction,
        'min-width'       => '100%',
        'border-collapse' => 'separate',
    ]
);

$border_style = isset( $element['data']['border'] ) ? TemplateHelpers::get_border_css_value( $element['data']['border'] ) : '';

if ( ! empty( $border_style ) && 'button' !== $element['type'] ) {
    $wrapper_style .= $border_style;
}

$user_custom_classes = isset( $element['data']['custom_css_classes'] ) ? $element['data']['custom_css_classes'] : '';
$settings            = o100ne_settings();

if ( ! empty( trim( wp_kses_post( $content_html ) ) ) ) {
    ?>
    <div class="o100ne-element o100ne-element-<?php echo esc_attr( $element['id'] ); ?> <?php echo esc_attr( $user_custom_classes ); ?>" data-o100ne-element-type="<?php echo esc_attr( $element['type'] ); ?>" style="width: 100%; margin: 0 auto;" data-o100ne-element-id="<?php echo esc_attr( $element['id'] ); ?>">
        <table cellpadding="0" cellspacing="0"  class="o100ne-element__content" style="<?php echo esc_attr( $wrapper_style ); ?>">
            <tbody>
                <tr>
                    <td>
                        <style>
                            .o100ne-element__content p {
                                font-size: 14px;
                                margin: 0px;
                            }
                        </style>
                        <?php
                        o100ne_kses_post_e( $content_html );
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}//end if
?>




// TS: 20260319172012

// TS: 20260408124242

// TS: 20260412165631
