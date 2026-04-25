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

$element = $args['element'];
$data    = $element['data'];

$wrapper_style = TemplateHelpers::get_style(
    [
        'background-color' => $data['background_color'],
        'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
    ]
);

if ( isset( $data['background_image'], $data['background_image']['url'] ) ) {
    $background_image = $data['background_image'];
    // Calculate background-position
    $background_position = str_replace( '_', ' ', $background_image['position'] );
    if ( ! isset( $background_image['position'] ) || 'default' === $background_image['position'] ) {
        $background_position = 'unset';
    } elseif ( 'custom' === $background_image['position'] ) {
        $background_position = "{$background_image['x_position']}% {$background_image['y_position']}%";
    }

    // Calculate background-repeat
    if ( ! isset( $background_image['repeat'] ) || 'default' === $background_image['repeat'] ) {
        $background_repeat = 'unset';
    } else {
        $background_repeat = $background_image['repeat'];
    }

    // Calculate background-size
    $background_size = $background_image['size'];

    if ( 'default' === $background_size ) {
        $background_size = 'unset';
    } elseif ( 'custom' === $background_size ) {
        $background_size = "{$background_image['custom_size']}%";
    }

    $wrapper_style .= TemplateHelpers::get_style(
        [
            'background-image'    => "url({$background_image['url']})",
            'background-position' => $background_position,
            'background-repeat'   => $background_repeat,
            'background-size'     => $background_size,
        ]
    );
}//end if

if ( isset( $data['border_radius'] ) ) {
    $wrapper_style .= TemplateHelpers::get_style(
        [
            'border-radius' => TemplateHelpers::get_border_radius_value( $data['border_radius'], 'px' ),
            'overflow'      => 'hidden',
        ]
    );
}//end if

if ( ! empty( $element['children'] ) ) {
    $inner_styles = '';
    if ( 'column_layout' === $element['type'] ) {

        $inner_border_radius    = $data['inner_border_radius'];
        $inner_background_color = $data['inner_background_color'];

        $inner_styles = TemplateHelpers::get_style(
            [
                'border-radius'    => TemplateHelpers::get_border_radius_value( $inner_border_radius, 'px' ),
                'overflow'         => 'hidden',
                'background-color' => $inner_background_color,
            ]
        );
    }
}

ob_start();
if ( 'column_layout' === $element['type'] ) : ?>
<div class="o100ne-inner-customizer-element-column" style="<?php echo esc_attr( $inner_styles ); ?>">  
<?php endif; ?> 
   
<table style="width: 100%; background-color: inherit;" cellpadding="0" cellspacing="0">
    <tbody>
        <?php
            ElementsLoader::render_elements(
                $element['children'],
                $args
            );
            ?>
    </tbody>
</table>

<?php if ( 'column_layout' === $element['type'] ) : ?>
</div>  
<?php endif; ?>

<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );

// TS: 20260123020407

// TS: 20260424191056
