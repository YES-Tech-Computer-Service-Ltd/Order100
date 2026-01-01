<?php
defined( 'ABSPATH' ) || exit;

use Order100\Notification\Engine\Elements\ElementsLoader;
use Order100\Notification\Engine\Utils\TemplateHelpers;

/**
 * $args includes
 * $element
 * $render_data
 * $is_nested
 */
if ( empty( $args['element'] ) ) {
    return;
}

$element   = $args['element'];
$data      = $element['data'];
$direction = isset( $data['direction'] ) ? $data['direction'] : 'vertical';

if ( 'horizontal' === $direction ) {
    $args['is_horizontal'] = true;
}

$wrapper_style = TemplateHelpers::get_style(
    [
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
        'overflow'         => 'hidden',
    ]
);

$inner_style = TemplateHelpers::get_style(
    [
        'width'    => '100%',
        'overflow' => 'hidden',
    ]
);


ob_start();
?>

<div class="o100ne-inner-customizer-element-container" style="<?php echo esc_attr( $inner_style ); ?>">  
    <table style="width: 100%; background-color: inherit;" cellpadding="0" cellspacing="0" width="100%">
        <tbody>
            <?php
                ElementsLoader::render_elements(
                    $element['children'],
                    $args
                );
                ?>
        </tbody>
    </table>
</div>  


<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );

