<?php
/**
 * Order100 Custom Product Order
 * Restores the "Custom order field" functionality from exfood.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class O100_Product_Custom_Order {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Add column to product list
		add_filter( 'manage_edit-product_columns', array( $this, 'add_custom_order_column' ), 99 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_custom_order_column' ), 12 );

		// AJAX handler for saving custom order
		add_action( 'wp_ajax_exwoofood_change_sort_food', array( $this, 'ajax_save_custom_order' ) );

		// Inject inline JS in the admin footer for the edit-product page
		add_action( 'admin_footer-edit.php', array( $this, 'admin_inline_js' ) );
	}

	/**
	 * Add custom column to WooCommerce products table
	 */
	public function add_custom_order_column( $columns ) {
		$columns['exwoofood_order'] = esc_html__( 'CT Order', 'order100' );
		return $columns;
	}

	/**
	 * Render the custom order column content
	 */
	public function render_custom_order_column( $column ) {
		global $post;
		if ( 'exwoofood_order' === $column ) {
			$exwf_order = get_post_meta( $post->ID, 'exwoofood_order', true );
			echo '<input type="number" class="o100-custom-order-input" style="max-width:60px;" data-id="' . esc_attr( $post->ID ) . '" name="exwoofood_order" value="' . esc_attr( $exwf_order ) . '">';
		}
	}

	/**
	 * AJAX endpoint to save the custom order
	 */
	public function ajax_save_custom_order() {
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$value   = isset( $_POST['value'] ) ? sanitize_text_field( $_POST['value'] ) : '';

		if ( $post_id ) {
			update_post_meta( $post_id, 'exwoofood_order', str_replace( ' ', '', $value ) );
			wp_send_json_success( 'Saved' );
		}
		
		wp_send_json_error( 'Invalid post ID' );
	}

	/**
	 * Output inline JS to handle the input change event
	 */
	public function admin_inline_js() {
		global $typenow;
		if ( 'product' !== $typenow ) {
			return;
		}
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$(document).on('change', 'input.o100-custom-order-input', function() {
				var $input = $(this);
				var post_id = $input.data('id');
				var val = $input.val();
				
				$input.css('opacity', '0.5');

				$.ajax({
					type: 'post',
					url: ajaxurl,
					data: {
						action: 'exwoofood_change_sort_food',
						post_id: post_id,
						value: val
					},
					success: function(response) {
						$input.css('opacity', '1');
						$input.css('border-color', '#46b450'); // Green flash
						setTimeout(function() {
							$input.css('border-color', '');
						}, 1000);
					},
					error: function() {
						$input.css('opacity', '1');
						alert('Failed to save order.');
					}
				});
			});
		});
		</script>
		<?php
	}
}

new O100_Product_Custom_Order();


