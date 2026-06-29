<?php
/**
 * Prep Station (打印机备餐台) Category Custom Field
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Prep_Station {

	public function __construct() {
		// Add field to category add form
		add_action( 'product_cat_add_form_fields', array( $this, 'add_category_field' ), 10, 1 );
		
		// Add field to category edit form
		add_action( 'product_cat_edit_form_fields', array( $this, 'edit_category_field' ), 10, 2 );
		
		// Save category field
		add_action( 'created_term', array( $this, 'save_category_field' ), 10, 3 );
		add_action( 'edit_term', array( $this, 'save_category_field' ), 10, 3 );
	}

	/**
	 * Add Prep Station field to the Add Category form
	 */
	public function add_category_field( $taxonomy ) {
		?>
		<div class="form-field term-o100-prep-station-wrap">
			<label for="o100_prep_station"><?php esc_html_e( 'Prep Station (For App Printer Routing)', 'order100' ); ?></label>
			<input type="text" name="o100_prep_station" id="o100_prep_station" value="" size="40">
			<p class="description"><?php esc_html_e( 'Enter a prep station identifier (e.g., "dimsum", "kitchen", "bar"). The Order100 App will use this to route items to specific printers.', 'order100' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Add Prep Station field to the Edit Category form
	 */
	public function edit_category_field( $term, $taxonomy ) {
		$prep_station = get_term_meta( $term->term_id, 'o100_prep_station', true );
		?>
		<tr class="form-field term-o100-prep-station-wrap">
			<th scope="row"><label for="o100_prep_station"><?php esc_html_e( 'Prep Station (For App Printer Routing)', 'order100' ); ?></label></th>
			<td>
				<input type="text" name="o100_prep_station" id="o100_prep_station" value="<?php echo esc_attr( $prep_station ); ?>" size="40">
				<p class="description"><?php esc_html_e( 'Enter a prep station identifier (e.g., "dimsum", "kitchen", "bar"). The Order100 App will use this to route items to specific printers.', 'order100' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save Prep Station field
	 */
	public function save_category_field( $term_id, $tt_id = '', $taxonomy = '' ) {
		if ( 'product_cat' === $taxonomy && isset( $_POST['o100_prep_station'] ) ) {
			update_term_meta( $term_id, 'o100_prep_station', sanitize_text_field( wp_unslash( $_POST['o100_prep_station'] ) ) );
		}
	}
}

new O100_Prep_Station();
