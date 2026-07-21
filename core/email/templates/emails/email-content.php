<?php
use Order100\Notification\Engine\Elements\ElementsLoader;
use Order100\Notification\Engine\Models\TemplateModel;
use Order100\Notification\Engine\Utils\TemplateHelpers;
use Order100\Notification\Engine\GlobalHeaderFooter;

defined( 'ABSPATH' ) || exit;

/**
 * $args includes
 * $template
 * $render_data
 * $settings
 */

$template = isset( $args['template'] ) ? $args['template'] : null;
// O100neTemplate instance
$render_data = isset( $args['render_data'] ) ? $args['render_data'] : [];
// Render data
$o100ne_settings    = o100ne_settings();
$container_direction = o100ne_get_email_direction();
$container_width     = isset( $o100ne_settings['container_width'] ) && is_numeric( $o100ne_settings['container_width'] ) ? $o100ne_settings['container_width'] : '605';

$style_container = TemplateHelpers::get_style(
    [
        'background' => $template->get_background_color(),
        'direction'  => $container_direction,
        'margin'     => '0 auto',
    ]
);

$style_container_wrap = TemplateHelpers::get_style(
    [
        'background'     => $template->get_background_color(),
        'direction'      => $container_direction,
        'margin'         => '0 auto',
        'width'          => '100%',
        'border-spacing' => '0',
    ]
);

/**
 * Get global header and footer elements
 *
 * @since 4.1.0
 */
$global_header_footer_elements = GlobalHeaderFooter::get_elements( $template );

if ( ! empty( $template ) ) :
    ?>

    <?php do_action( 'o100_before_email_content', $template, $render_data ); ?>
    <table style="<?php echo esc_attr( $style_container_wrap ); ?>" border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
        <tr>
            <td style="padding: 0;">
                <table class="o100ne-template-content-container" style="width: 100%; max-width: <?php echo esc_attr( $container_width ); ?>px; margin: auto;border-spacing: 0;">
                    <tr>
                        <td style="padding: 0;">
                            <table style="<?php echo esc_attr( $style_container ); ?>" border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" class="o100ne-customizer-email-template-container <?php echo esc_attr( 'o100ne-template-' . $template->get_name() ); ?>">
                                <?php
                                if ( ! empty( $global_header_footer_elements['global_header_elements'] ) ) {
                                    ElementsLoader::render_elements( $global_header_footer_elements['global_header_elements'], $args );
                                }
                                ?>
                                <?php ElementsLoader::render_elements( $template->get_elements(), $args ); ?>
                                <?php
                                if ( ! empty( $global_header_footer_elements['global_footer_elements'] ) ) {
                                    ElementsLoader::render_elements( $global_header_footer_elements['global_footer_elements'], $args );
                                }
                                ?>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <?php do_action( 'o100_after_email_content', $template, $render_data ); ?>
<?php endif; ?>


