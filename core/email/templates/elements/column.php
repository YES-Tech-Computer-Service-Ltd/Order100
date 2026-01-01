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

$element              = $args['element'];
$data                 = $element['data'];
$list_elements        = $args['template']->get_elements();
$parent_element       = TemplateHelpers::find_parent_element( $element['id'], $list_elements );
$row_avg_padding      = $parent_element['data']['column_spacing'] ?? 0;
$total_columns        = $parent_element['data']['amount_of_columns'] ?? 1;
$current_column_index = array_search( $element['id'], array_column( $parent_element['children'] ?? [], 'id' ) );

$wrapper_style = TemplateHelpers::get_style(
    array_merge(
        [
            'width'          => "{$data['width']}%",
            'max-width'      => "{$data['width']}%",
            'vertical-align' => 'top',
        ],
        $current_column_index === 0 ? [
            'padding-left'  => '0',
            'padding-right' => TemplateHelpers::get_dimension_value( $row_avg_padding / 2 ),
        ] : [
            'padding-right' => TemplateHelpers::get_dimension_value( $row_avg_padding / 4 ),
            'padding-left'  => TemplateHelpers::get_dimension_value( $row_avg_padding / 4 ),
        ],
        $total_columns - 1 === $current_column_index ? [
            'padding-right' => '0',
            'padding-left'  => TemplateHelpers::get_dimension_value( $row_avg_padding / 2 ),
        ] : [],
    )
);

$content_style = TemplateHelpers::get_style(
    [
        'min-height' => '50px',
    ]
);

?>

<td class="o100ne-customizer-element-column" style="<?php echo esc_attr( $wrapper_style ); ?>">
    <div class="o100ne-customizer-element-nested-column-content" style="<?php echo esc_attr( $content_style ); ?>">
        <?php
        if ( ! empty( $element['children'] ) ) {
            $args['is_nested'] = true;
            ElementsLoader::render_elements(
                $element['children'],
                $args
            );
        }
        ?>
    </div>
</td>




