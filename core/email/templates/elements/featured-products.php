<?php
defined( 'ABSPATH' ) || exit;

use Order100\Notification\Engine\Utils\TemplateHelpers;
use Order100\Notification\Engine\Models\ProductModel;

/**
 * Featured Products Email Element Template
 *
 * Queries real WooCommerce products based on element settings and renders
 * a grid with product image, name, price, and "Add to Cart" button.
 *
 * $args includes:
 *   $element     – element data from the template builder
 *   $render_data – order/customer context
 *   $is_nested   – whether inside a column
 */
if ( empty( $args['element'] ) ) {
	return;
}

$element = $args['element'];
$data    = $element['data'];

// ── Style settings ──────────────────────────────────────────────────
$showing_items       = isset( $data['showing_items'] ) ? (array) $data['showing_items'] : [];
$products_per_row    = max( 1, min( 3, intval( $data['products_per_row'] ?? 3 ) ) );
$font_family         = TemplateHelpers::get_font_family_value( $data['font_family'] ?? '' );
$text_color          = $data['text_color'] ?? '#333333';
$sale_price_color    = $data['sale_price_color'] ?? '#ec4770';
$regular_price_color = $data['regular_price_color'] ?? '#808080';
$btn_bg              = $data['buy_button_background_color'] ?? '#ec4770';
$btn_text            = $data['buy_button_text_color'] ?? '#ffffff';
$btn_label           = $data['buy_button_label'] ?? __( 'Add to Cart', 'order100' );

// ── Build query params for ProductModel ─────────────────────────────
$product_type = $data['product_type'] ?? 'newest';

$params = [
	'product_type'       => $product_type,
	'number_of_products' => intval( $data['number_of_products'] ?? 5 ),
	'sorted_by'          => $data['sorted_by'] ?? 'none',
];

if ( 'category_selections' === $product_type && ! empty( $data['categories'] ) ) {
	$params['category_ids'] = array_map( 'intval', wp_list_pluck( (array) $data['categories'], 'id' ) );
}
if ( 'tag_selections' === $product_type && ! empty( $data['tags'] ) ) {
	$params['tag_ids'] = array_map( 'intval', wp_list_pluck( (array) $data['tags'], 'id' ) );
}
if ( 'product_selections' === $product_type && ! empty( $data['products'] ) ) {
	$params['product_ids'] = array_map( 'intval', wp_list_pluck( (array) $data['products'], 'id' ) );
}

// ── Fetch products ──────────────────────────────────────────────────
$product_model = ProductModel::get_instance();
$products      = $product_model->get_featured_products( $params );

if ( empty( $products ) ) {
	return; // Nothing to render
}

// ── Wrapper style ───────────────────────────────────────────────────
$wrapper_style = TemplateHelpers::get_style(
	[
		'width'            => '100%',
		'background-color' => $data['background_color'] ?? '#ffffff',
		'padding'          => TemplateHelpers::get_spacing_value( isset( $data['padding'] ) ? $data['padding'] : [] ),
		'font-family'      => $font_family,
		'color'            => $text_color,
	]
);

// ── Calculate column width ──────────────────────────────────────────
$col_width = intval( 100 / $products_per_row );

ob_start();
?>

<?php // ── Top content ──────────────────────────────────────────────── ?>
<?php if ( in_array( 'top_content', $showing_items, true ) && ! empty( $data['top_content'] ) ) : ?>
	<div style="width:100%;margin-bottom:20px;">
		<?php o100ne_kses_post_e( $data['top_content'] ); ?>
	</div>
<?php endif; ?>

<?php // ── Products grid (email-safe table layout) ──────────────────── ?>
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
<?php
$chunks = array_chunk( $products, $products_per_row );
foreach ( $chunks as $row ) :
?>
	<tr>
	<?php foreach ( $row as $product ) : ?>
		<td width="<?php echo esc_attr( $col_width ); ?>%" valign="top" style="padding:10px;text-align:center;">
			<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
				<?php // Product image ?>
				<?php if ( in_array( 'product_image', $showing_items, true ) && ! empty( $product['thumbnail_src'] ) ) : ?>
				<tr>
					<td style="text-align:center;padding-bottom:10px;">
						<a href="<?php echo esc_url( $product['add_to_cart_url'] ); ?>" target="_blank" rel="noreferrer" style="text-decoration:none;">
							<img src="<?php echo esc_url( $product['thumbnail_src'] ); ?>"
								 alt="<?php echo esc_attr( $product['name'] ); ?>"
								 width="150"
								 style="max-width:100%;height:auto;border-radius:6px;display:block;margin:0 auto;" />
						</a>
					</td>
				</tr>
				<?php endif; ?>

				<?php // Product name ?>
				<?php if ( in_array( 'product_name', $showing_items, true ) ) : ?>
				<tr>
					<td style="text-align:center;padding-bottom:5px;font-size:15px;color:<?php echo esc_attr( $text_color ); ?>;font-family:<?php echo esc_attr( $font_family ); ?>;">
						<a href="<?php echo esc_url( $product['add_to_cart_url'] ); ?>" target="_blank" rel="noreferrer" style="text-decoration:none;color:<?php echo esc_attr( $text_color ); ?>;">
							<?php echo esc_html( $product['name'] ); ?>
						</a>
					</td>
				</tr>
				<?php endif; ?>

				<?php // Product price ?>
				<?php if ( in_array( 'product_price', $showing_items, true ) ) : ?>
				<tr>
					<td style="text-align:center;padding-bottom:5px;font-size:16px;font-weight:bold;color:<?php echo esc_attr( $sale_price_color ); ?>;font-family:<?php echo esc_attr( $font_family ); ?>;">
						<?php o100ne_kses_post_e( $product['sale_price_html'] ); ?>
					</td>
				</tr>
				<?php endif; ?>

				<?php // Original price (strikethrough) ?>
				<?php if ( in_array( 'product_original_price', $showing_items, true ) && ! empty( $product['regular_price_html'] ) && $product['sale_price_html'] !== $product['regular_price_html'] ) : ?>
				<tr>
					<td style="text-align:center;padding-bottom:8px;font-size:13px;color:<?php echo esc_attr( $regular_price_color ); ?>;text-decoration:line-through;font-family:<?php echo esc_attr( $font_family ); ?>;">
						<?php o100ne_kses_post_e( $product['regular_price_html'] ); ?>
					</td>
				</tr>
				<?php endif; ?>

				<?php // Buy / Add to Cart button ?>
				<?php if ( in_array( 'buy_button', $showing_items, true ) ) : ?>
				<tr>
					<td style="text-align:center;padding-top:5px;padding-bottom:10px;">
						<a href="<?php echo esc_url( $product['add_to_cart_url'] ); ?>"
						   target="_blank"
						   rel="noreferrer"
						   style="display:inline-block;padding:10px 22px;background-color:<?php echo esc_attr( $btn_bg ); ?>;color:<?php echo esc_attr( $btn_text ); ?>;font-size:14px;font-weight:bold;text-decoration:none;border-radius:4px;font-family:<?php echo esc_attr( $font_family ); ?>;">
							<?php echo esc_html( $btn_label ); ?>
						</a>
					</td>
				</tr>
				<?php endif; ?>
			</table>
		</td>
	<?php endforeach; ?>

	<?php // Fill remaining cells if row is incomplete ?>
	<?php
	$remaining = $products_per_row - count( $row );
	for ( $i = 0; $i < $remaining; $i++ ) :
	?>
		<td width="<?php echo esc_attr( $col_width ); ?>%" style="padding:10px;">&nbsp;</td>
	<?php endfor; ?>
	</tr>
<?php endforeach; ?>
</table>

<?php
$element_content = ob_get_clean();
TemplateHelpers::wrap_element_content( $element_content, $element, $wrapper_style );
