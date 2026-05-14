<?php
defined( 'ABSPATH' ) || exit;

use Order100\Notification\Engine\Utils\TemplateHelpers;

if ( empty( $args['element'] ) ) {
    return;
}

$element = $args['element'];
$data    = $element['data'];

$billing_address_html  = wp_kses_post( do_shortcode( '[o100_billing_address]' ) );
$shipping_address_html = wp_kses_post( do_shortcode( '[o100_shipping_address]' ) );
$width                 = ! empty( $billing_address_html ) & ! empty( $shipping_address_html ) ? '50%' : '100%';

if ( empty( $billing_address_html ) && empty( $shipping_address_html ) ) :
    return '';
endif;

$wrapper_style = TemplateHelpers::get_style(
    [
        'word-break'       => 'break-word',
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

$table_style = TemplateHelpers::get_style(
    [
        'width'           => '100%',
        'text-align'      => o100ne_get_text_align(),
        'border-collapse' => 'separate',
        'border-spacing'  => '5px',
    ]
);

$column_style = TemplateHelpers::get_style(
    [
        'color'       => isset( $data['text_color'] ) ? $data['text_color'] : 'inherit',
        'padding'     => '12px',
        'font-size'   => '14px',
        'font-family' => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
        'border'      => 'solid 1px ' . $data['border_color'],
    ]
);

$title_style = TemplateHelpers::get_style(
    [
        'text-align'    => o100ne_get_text_align(),
        'color'         => isset( $data['title_color'] ) ? $data['title_color'] : 'inherit',
        'margin-top'    => '0',
        'font-family'   => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
        'margin-bottom' => '7px',
    ]
);

$is_layout_type_modern = isset( $data['layout_type'] ) && 'modern' === $data['layout_type'];

ob_start();
?>
<style>
    /* Modern layout */
    <?php if ( $is_layout_type_modern ) { ?>
    .o100ne-element-<?php echo esc_attr( $element['id'] ); ?> .o100ne-billing-address-wrap,
    .o100ne-element-<?php echo esc_attr( $element['id'] ); ?> .o100ne-shipping-address-wrap {
        border: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
        <?php
    }//end if
    ?>
</style>
<table class="o100ne-table-billing-shipping-address" cellpadding="0" cellspacing="0" border="0" style="<?php echo esc_attr( $table_style ); ?>">
    <tbody>
        <tr>
            <?php if ( ! empty( $billing_address_html ) ) : ?>
            <td class="o100ne-billing-address-column" style="width: <?php echo esc_attr( $width ); ?>; vertical-align: top;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-spacing: 0;">
                    <tbody>
                    <tr>
                        <td>
                            <div class="o100ne-billing-title" style="<?php echo esc_attr( $title_style ); ?>"><?php echo wp_kses_post( do_shortcode( $data['billing_title'] ) ); ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tbody>
                                    <tr>
                                        <td class="o100ne-billing-address-wrap" style="<?php echo esc_attr( $column_style ); ?>">
                                            <div>
                                                <?php echo wp_kses_post( do_shortcode( '[o100_billing_address]' ) ); ?>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </td>
            <?php endif; ?>
            <?php if ( ! empty( $shipping_address_html ) ) : ?>
            <td class="o100ne-shipping-address-column" style="width: <?php echo esc_attr( $width ); ?>; vertical-align: top;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-spacing: 0;">
                    <tbody>
                    <tr>
                        <td>
                            <div class="o100ne-shipping-title" style="<?php echo esc_attr( $title_style ); ?>"><?php echo wp_kses_post( do_shortcode( $data['shipping_title'] ) ); ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tbody>
                                    <tr>
                                        <td class="o100ne-shipping-address-wrap" style="<?php echo esc_attr( $column_style ); ?>">
                                            <div>
                                                <?php echo wp_kses_post( do_shortcode( '[o100_shipping_address]' ) ); ?>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </td>
            <?php endif; ?>
        </tr>
    </tbody>
</table>
<?php
$element_content = ob_get_clean();
TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );


// TS: 20260109212635

// TS: 20260214215521

// TS: 20260513132740
