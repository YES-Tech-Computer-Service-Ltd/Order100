<?php
defined( 'ABSPATH' ) || exit;

use Order100\Notification\Engine\Utils\TemplateHelpers;

if ( empty( $args['element'] ) ) {
    return;
}

$element = $args['element'];
$data    = $element['data'];

$billing_address_html = wp_kses_post( do_shortcode( isset( $data['rich_text'] ) ? $data['rich_text'] : '[o100_billing_address]' ) );

if ( empty( $billing_address_html ) ) :
    return '';
endif;

$wrapper_style = TemplateHelpers::get_style(
    [
        'word-break'       => 'break-word',
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

$billing_border_style = TemplateHelpers::get_style(
    [
        'border' => 'solid 1px ' . $data['border_color'],
    ]
);

$billing_wrapper_style = TemplateHelpers::get_style(
    [
        'color'       => isset( $data['text_color'] ) ? $data['text_color'] : 'inherit',
        'padding'     => '12px',
        'text-align'  => o100ne_get_text_align(),
        'font-size'   => '14px',
        'font-family' => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
        'border'      => 'solid 1px ' . $data['border_color'],
    ]
);

$title_style = TemplateHelpers::get_style(
    [
        'text-align'  => o100ne_get_text_align(),
        'color'       => isset( $data['title_color'] ) ? $data['title_color'] : 'inherit',
        'margin'      => '0 0 7px 0',
        'font-size'   => '20px',
        'font-weight' => '600',
        'font-family' => TemplateHelpers::get_font_family_value( isset( $data['font_family'] ) ? $data['font_family'] : 'inherit' ),
    ]
);

$is_layout_type_modern = isset( $data['layout_type'] ) && 'modern' === $data['layout_type'];

ob_start();
?>
<style>
    /* Modern layout */
    <?php if ( $is_layout_type_modern ) { ?>
    .o100ne-element-<?php echo esc_attr( $element['id'] ); ?> .o100ne-billing-address-wrap {
        border: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
        <?php
    }//end if
    ?>
</style>
<div class="o100ne-billing-title" style="<?php echo esc_attr( $title_style ); ?>" > <?php echo wp_kses_post( do_shortcode( $data['title'] ) ); ?> </div>
<div class="o100ne-billing-address-wrap" style="<?php echo esc_attr( $billing_wrapper_style ); ?>">
    <?php echo wp_kses_post( do_shortcode( isset( $data['rich_text'] ) ? $data['rich_text'] : '[o100_billing_address]' ) ); ?>
</div>
           
<?php
$element_content = ob_get_clean();
TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );



