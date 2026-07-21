<?php
defined( 'ABSPATH' ) || exit;
use Order100\Notification\Engine\Utils\TemplateHelpers;
use Order100\Notification\Engine\Models\TemplateModel;

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

ob_start();
?>
<div class="o100ne-skeleton-divider"><div class="o100ne-skeleton o100ne-skeleton-round css-dev-only-do-not-override-scpxro" style="margin-bottom: 10px;"><div class="o100ne-skeleton-content"><ul class="o100ne-skeleton-paragraph"><li style="width: 30%;"></li></ul></div></div><div class="o100ne-skeleton o100ne-skeleton-round o100ne-skeleton-divider__image css-dev-only-do-not-override-scpxro" style="margin-bottom: 10px;"><div class="o100ne-skeleton-content"><ul class="o100ne-skeleton-paragraph"><li style="width: 100%;"></li></ul></div></div><div class="o100ne-skeleton o100ne-skeleton-round css-dev-only-do-not-override-scpxro"><div class="o100ne-skeleton-content"><ul class="o100ne-skeleton-paragraph"><li style="width: 70%;"></li></ul></div></div><div class="o100ne-skeleton o100ne-skeleton-round css-dev-only-do-not-override-scpxro"><div class="o100ne-skeleton-content"><ul class="o100ne-skeleton-paragraph"><li style="width: 100%;"></li></ul></div></div><div class="o100ne-skeleton o100ne-skeleton-round css-dev-only-do-not-override-scpxro"><div class="o100ne-skeleton-content"><ul class="o100ne-skeleton-paragraph"><li style="width: 100%;"></li></ul></div></div></div>
<?php
$element_content = ob_get_clean();

TemplateHelpers::wrap_element_content( $element_content, $element );

