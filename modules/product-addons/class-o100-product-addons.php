<?php
/**
 * Product Add-ons Module
 *
 * Registers the o100_global_options CPT under the Products menu,
 * adds metaboxes for global & per-product extra options,
 * and registers custom CMB2 field types for option management.
 *
 * @package Order100
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class O100_Product_Addons {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'cmb2_admin_init', array( $this, 'register_metaboxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );

		// Custom CMB2 field types
		add_filter( 'cmb2_render_o100_price_options', array( $this, 'render_price_options' ), 10, 5 );
		add_filter( 'cmb2_sanitize_o100_price_options', array( $this, 'sanitize_repeatable' ), 10, 5 );
		add_filter( 'cmb2_types_esc_o100_price_options', array( $this, 'escape_repeatable' ), 10, 4 );

		// Save custom "Include Specific Modifiers" field (multi-select needs explicit save)
		add_action( 'save_post_product', array( $this, 'save_addon_include_field' ), 20 );

		// WooCommerce import/export support
		add_filter( 'woocommerce_product_import_process_item_data', array( $this, 'import_unserialize' ) );
		add_filter( 'woocommerce_product_export_meta_value', array( $this, 'export_serialize' ), 10, 4 );
	}

	/* ───────────────────────────────────────────────
	 * 3. CMB2 Metaboxes
	 * ─────────────────────────────────────────────── */

	public function register_metaboxes() {
		$this->register_options_metabox();
		$this->register_food_labels_metabox();
	}

	/**
	 * Per-product "Additional Options" metabox.
	 */
	private function register_options_metabox() {
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_addon_options',
			'title'        => __( 'Item Modifiers', 'order100' ),
			'object_types' => array( 'product' ),
		) );

		// Per-product controls
		$cmb->add_field( array(
			'name'        => __( 'Exclude Global Modifiers', 'order100' ),
			'description' => __( 'Check to prevent all global modifier templates from applying to this product.', 'order100' ),
			'id'          => 'o100_addon_exclude',
			'type'        => 'checkbox',
		) );

		$cmb->add_field( array(
			'name'        => __( 'Include Specific Modifiers', 'order100' ),
			'description' => __( 'Select global modifier templates to include for this product.', 'order100' ),
			'id'          => 'o100_addon_include',
			'type'        => 'o100_modifier_select',
			'render_row_cb' => array( $this, 'render_modifier_select_field' ),
		) );

		$cmb->add_field( array(
			'name'        => __( 'Modifier Display Order', 'order100' ),
			'description' => __( 'Choose whether global modifiers appear before or after product-level modifiers.', 'order100' ),
			'id'          => 'o100_addon_pos',
			'type'        => 'select',
			'default'     => '',
			'options'     => array(
				''       => __( 'After product modifiers', 'order100' ),
				'before' => __( 'Before product modifiers', 'order100' ),
			),
		) );

		// --- Repeatable Modifier Groups ---
		$group_id = $cmb->add_field( array(
			'id'          => 'o100_addon_groups',
			'type'        => 'group',
			'description' => __( 'Configure item modifiers that customers can customize when ordering.', 'order100' ),
			'options'     => array(
				'group_title'   => __( 'Modifier {#}', 'order100' ),
				'add_button'    => __( 'Add Modifier Group', 'order100' ),
				'remove_button' => __( 'Remove', 'order100' ),
				'sortable'      => true,
				'closed'        => true,
			),
		) );

		// Group field: Name
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Name', 'order100' ),
			'id'      => '_name',
			'type'    => 'text',
			'classes' => 'o100-addon-name',
		) );

		// Group field: Option Type
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Option Type', 'order100' ),
			'desc'    => __( 'Select the input type for this option group.', 'order100' ),
			'id'      => '_type',
			'type'    => 'select',
			'classes' => 'o100-addon-type',
			'default' => '',
			'options' => array(
				''         => __( 'Checkboxes', 'order100' ),
				'radio'    => __( 'Radio Buttons', 'order100' ),
				'select'   => __( 'Dropdown', 'order100' ),
				'text'     => __( 'Text Field', 'order100' ),
				'textarea' => __( 'Textarea', 'order100' ),
				'quantity' => __( 'Quantity', 'order100' ),
			),
		) );

		// Group field: Display type
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Display Style', 'order100' ),
			'desc'    => __( 'Override the global display style for this option group.', 'order100' ),
			'id'      => '_display_type',
			'type'    => 'select',
			'classes' => 'o100-addon-display',
			'default' => '',
			'options' => array(
				''      => __( 'Default', 'order100' ),
				'nor'   => __( 'Normal', 'order100' ),
				'accor' => __( 'Accordion', 'order100' ),
			),
		) );

		// Group field: Required
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Required?', 'order100' ),
			'id'      => '_required',
			'type'    => 'select',
			'classes' => 'o100-addon-required',
			'default' => '',
			'options' => array(
				''    => __( 'No', 'order100' ),
				'yes' => __( 'Yes', 'order100' ),
			),
		) );

		// Group field: Min selection (checkboxes only)
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Minimum Checkboxes (Selection Limit)', 'order100' ),
			'desc'    => __( 'Min number of different choices the user must select.', 'order100' ),
			'id'      => '_min_op',
			'type'    => 'text',
			'classes' => 'o100-addon-min',
		) );

		// Group field: Max selection (checkboxes only)
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Maximum Checkboxes (Selection Limit)', 'order100' ),
			'desc'    => __( 'Max number of different choices the user can select.', 'order100' ),
			'id'      => '_max_op',
			'type'    => 'text',
			'classes' => 'o100-addon-max',
		) );

		// Group field: Enable images
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Enable Images', 'order100' ),
			'desc'    => __( 'Show an image thumbnail next to each choice.', 'order100' ),
			'id'      => '_enb_img',
			'type'    => 'select',
			'classes' => 'o100-addon-img',
			'default' => '',
			'options' => array(
				''    => __( 'No', 'order100' ),
				'yes' => __( 'Yes', 'order100' ),
			),
		) );

		// Group field: Show quantity per choice
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Enable Quantity Selectors', 'order100' ),
			'desc'    => __( 'Allow customers to choose quantities per item.', 'order100' ),
			'id'      => '_enb_qty',
			'type'    => 'select',
			'classes' => 'o100-addon-qty',
			'default' => '',
			'options' => array(
				''    => __( 'No', 'order100' ),
				'yes' => __( 'Yes', 'order100' ),
			),
		) );

		// Group field: Min total option qty
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Minimum Total Quantity', 'order100' ),
			'desc'    => __( 'Sum of all quantities must be at least this number.', 'order100' ),
			'id'      => '_min_opqty',
			'type'    => 'text',
			'classes' => 'o100-addon-minqty',
		) );

		// Group field: Max total option qty
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Maximum Total Quantity', 'order100' ),
			'desc'    => __( 'Sum of all quantities cannot exceed this number.', 'order100' ),
			'id'      => '_max_opqty',
			'type'    => 'text',
			'classes' => 'o100-addon-maxqty',
		) );

		// Group field: Sub-options (custom repeatable price_options type)
		$cmb->add_group_field( $group_id, array(
			'name'       => __( 'Options', 'order100' ),
			'desc'       => __( 'Configure name, price, and image for each option.', 'order100' ),
			'id'         => '_options',
			'type'       => 'o100_price_options',
			'repeatable' => true,
			'classes'    => 'o100-addon-choices',
		) );

		// Group field: Price title (for text/textarea/quantity types)
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Text/Quantity Input Pricing', 'order100' ),
			'desc'    => '',
			'id'      => '_price_title',
			'type'    => 'title',
			'classes' => 'o100-addon-pricetype-title o100-hidden',
		) );

		// Group field: Price type (for text/textarea/quantity types)
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Price Type', 'order100' ),
			'id'      => '_price_type',
			'type'    => 'select',
			'classes' => 'o100-addon-pricetype o100-hidden',
			'default' => '',
			'options' => array(
				''      => __( 'Quantity Based', 'order100' ),
				'fixed' => __( 'Fixed Amount', 'order100' ),
			),
		) );

		// Group field: Price (for text/textarea/quantity types)
		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Price', 'order100' ),
			'id'      => '_price',
			'type'    => 'text',
			'classes' => 'o100-addon-price o100-hidden',
		) );



		// Group field: Unique ID (auto-generated, hidden)
		$cmb->add_group_field( $group_id, array(
			'name'            => __( 'Option ID', 'order100' ),
			'id'              => '_id',
			'type'            => 'text',
			'classes'         => 'o100-hidden',
			'sanitization_cb' => array( $this, 'sanitize_option_id' ),
		) );
	}

	/**
	 * Per-product "Food Labels" metabox.
	 */
	private function register_food_labels_metabox() {
		$cmb_labels = new_cmb2_box( array(
			'id'           => 'o100_product_food_labels_metabox',
			'title'        => __( 'Food Labels', 'order100' ),
			'object_types' => array( 'product' ),
		) );

		$cmb_labels->add_field( array(
			'name'    => __( 'Select Labels', 'order100' ),
			'desc'    => __( 'Select labels to display on this product.', 'order100' ) . '<br><em>' . __( 'If you need other labels, please go to', 'order100' ) . ' <a href="' . admin_url('admin.php?page=o100-settings&tab=misc') . '">' . __( 'Global Settings -> Misc', 'order100' ) . '</a> ' . __( 'to add them.', 'order100' ) . '</em>',
			'id'      => 'o100_product_labels',
			'type'    => 'multicheck_inline',
			'options' => array( $this, 'get_global_food_labels_options' ),
		) );
	}

	/**
	 * Get global food labels as options array for the multicheck field.
	 */
	public function get_global_food_labels_options() {
		$options = array();
		$settings = get_option( 'o100_misc', array() );
		if ( !empty($settings['o100_global_food_labels']) && is_array($settings['o100_global_food_labels']) ) {
			foreach ( $settings['o100_global_food_labels'] as $index => $label ) {
				$name = !empty($label['name']) ? $label['name'] : sprintf( __( 'Label %d', 'order100' ), $index + 1 );
				$options[ $index ] = $name;
			}
		}
		return $options;
	}


	/* ───────────────────────────────────────────────
	 * 4. Custom CMB2 Field: price_options
	 * ─────────────────────────────────────────────── */

	public function render_price_options( $field, $value, $object_id, $object_type, $field_type ) {
		$value = wp_parse_args( $value, array(
			'name'  => '',
			'type'  => '',
			'def'   => '',
			'dis'   => '',
			'price' => '',
			'image' => '',
			'min'   => '',
			'max'   => '',
		) );
		?>
		<div class="o100-addon-choice-row">
			<div class="o100-addon-col o100-addon-col-handle">
				<span class="dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'order100' ); ?>"></span>
			</div>
			<div class="o100-addon-col o100-addon-col-img">
				<label><?php esc_html_e( 'Image', 'order100' ); ?></label>
				<?php echo $field_type->file( array(
					'class' => 'cmb2-upload-file regular-text',
					'name'  => $field_type->_name( '[image]' ),
					'id'    => $field_type->_id( '_image' ),
					'value' => $value['image'],
					'type'  => 'hidden',
					'size'  => 45,
					'js_dependencies' => 'media-editor',
					'query_args' => array( 'type' => array( 'image/gif', 'image/jpeg', 'image/png', 'image/webp' ) ),
					'preview_size' => array( 30, 30 ),
					'desc'  => '',
				) ); ?>
			</div>

			<div class="o100-addon-col o100-addon-col-name">
				<label><?php esc_html_e( 'Name', 'order100' ); ?></label>
				<?php echo $field_type->input( array(
					'name'  => $field_type->_name( '[name]' ),
					'id'    => $field_type->_id( '_name' ),
					'value' => $value['name'],
					'type'  => 'text',
					'desc'  => '',
				) ); ?>
			</div>

			<div class="o100-addon-col o100-addon-col-def">
				<label><?php esc_html_e( 'Default', 'order100' ); ?></label>
				<input type="checkbox"
					name="<?php echo esc_attr( $field_type->_name( '[def]' ) ); ?>"
					id="<?php echo esc_attr( $field_type->_id( '_def' ) ); ?>"
					value="yes"
					<?php checked( $value['def'], 'yes' ); ?>>
			</div>

			<div class="o100-addon-col o100-addon-col-dis">
				<label><?php esc_html_e( 'Disable', 'order100' ); ?></label>
				<input type="checkbox"
					name="<?php echo esc_attr( $field_type->_name( '[dis]' ) ); ?>"
					id="<?php echo esc_attr( $field_type->_id( '_dis' ) ); ?>"
					value="yes"
					<?php checked( $value['dis'], 'yes' ); ?>>
			</div>

			<div class="o100-addon-col o100-addon-col-price">
				<label><?php esc_html_e( 'Price', 'order100' ); ?></label>
				<?php echo $field_type->input( array(
					'name'  => $field_type->_name( '[price]' ),
					'id'    => $field_type->_id( '_price' ),
					'value' => $value['price'],
					'type'  => 'text',
					'desc'  => '',
				) ); ?>
			</div>

			<div class="o100-addon-col o100-addon-col-type">
				<label><?php esc_html_e( 'Price Type', 'order100' ); ?></label>
				<?php echo $field_type->select( array(
					'name'    => $field_type->_name( '[type]' ),
					'id'      => $field_type->_id( '_type' ),
					'value'   => $value['type'],
					'options' => $this->get_price_type_html( $value['type'] ),
					'desc'    => '',
				) ); ?>
			</div>

			<div class="o100-addon-col o100-addon-col-minmax">
				<label><?php esc_html_e( 'Min', 'order100' ); ?></label>
				<?php echo $field_type->input( array(
					'name'  => $field_type->_name( '[min]' ),
					'id'    => $field_type->_id( '_min' ),
					'value' => $value['min'],
					'type'  => 'number',
					'desc'  => '',
				) ); ?>
			</div>

			<div class="o100-addon-col o100-addon-col-minmax">
				<label><?php esc_html_e( 'Max', 'order100' ); ?></label>
				<?php echo $field_type->input( array(
					'name'  => $field_type->_name( '[max]' ),
					'id'    => $field_type->_id( '_max' ),
					'value' => $value['max'],
					'type'  => 'number',
					'desc'  => '',
				) ); ?>
			</div>

			<div class="o100-addon-col o100-addon-col-remove">
				<button type="button" class="o100-remove-choice-row dashicons dashicons-trash" title="<?php esc_attr_e( 'Remove', 'order100' ); ?>"></button>
			</div>
		</div>
		<br class="clear">
		<?php
		echo $field_type->_desc( true );
	}

	private function get_price_type_html( $current = '' ) {
		$types = array(
			''      => __( 'Qty Based', 'order100' ),
			'fixed' => __( 'Fixed', 'order100' ),
		);
		$html = '';
		foreach ( $types as $val => $label ) {
			$html .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $val ),
				selected( $current, $val, false ),
				esc_html( $label )
			);
		}
		return $html;
	}

	/* ───────────────────────────────────────────────
	 * 5. Sanitization & Callbacks
	 * ─────────────────────────────────────────────── */

	public function sanitize_repeatable( $check, $meta_value, $object_id, $field_args, $sanitize_object ) {
		if ( ! is_array( $meta_value ) || empty( $field_args['repeatable'] ) ) {
			return $check;
		}
		foreach ( $meta_value as $key => $val ) {
			if ( is_array( $val ) ) {
				$meta_value[ $key ] = array_filter( array_map( 'sanitize_text_field', $val ) );
			}
		}
		return array_filter( $meta_value );
	}

	public function escape_repeatable( $check, $meta_value, $field_args, $field_object ) {
		if ( ! is_array( $meta_value ) || empty( $field_args['repeatable'] ) ) {
			return $check;
		}
		foreach ( $meta_value as $key => $val ) {
			if ( is_array( $val ) ) {
				$meta_value[ $key ] = array_filter( array_map( 'esc_attr', $val ) );
			}
		}
		return array_filter( $meta_value );
	}

	public function sanitize_option_id( $original_value, $args, $cmb2_field ) {
		if ( empty( $original_value ) ) {
			$original_value = 'o100-id' . wp_rand( 10000, 9999999999 );
		}
		return $original_value;
	}

	public static function sanitize_option_id_static( $original_value, $args, $cmb2_field ) {
		if ( empty( $original_value ) ) {
			$original_value = 'o100-id' . wp_rand( 10000, 9999999999 );
		}
		return $original_value;
	}

	public function save_product_ids_array( $value, $field_args, $field ) {
		if ( ! empty( $value ) && ! empty( $_POST['post_ID'] ) ) {
			$post_id = absint( $_POST['post_ID'] );
			delete_post_meta( $post_id, 'o100_addon_product_ids_arr' );
			$arr_ids = array_filter( array_map( 'trim', explode( ',', $value ) ) );
			foreach ( $arr_ids as $item ) {
				add_post_meta( $post_id, 'o100_addon_product_ids_arr', absint( $item ) );
			}
		}
		return $value;
	}

	/* ───────────────────────────────────────────────
	 * 5b. Save Custom Include Field
	 * ─────────────────────────────────────────────── */

	/**
	 * Save the "Include Specific Modifiers" multi-select field.
	 * When nothing is selected, the POST key is absent, so we must
	 * explicitly delete the meta to allow deselection.
	 */
	public function save_addon_include_field( $post_id ) {
		// Don't run on autosave or revisions
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		if ( isset( $_POST['o100_addon_include'] ) && is_array( $_POST['o100_addon_include'] ) ) {
			$values = array_map( 'sanitize_text_field', $_POST['o100_addon_include'] );
			update_post_meta( $post_id, 'o100_addon_include', $values );
		} else {
			// Nothing selected — save empty array (NOT delete, so metadata_exists stays true)
			update_post_meta( $post_id, 'o100_addon_include', array() );
		}
	}

	/* ───────────────────────────────────────────────
	 * 6. Show/Hide Callbacks
	 * ─────────────────────────────────────────────── */

	/**
	 * Custom render callback for the "Include Specific Modifiers" field.
	 * Renders a <select multiple> dropdown and auto-selects modifiers
	 * that already target this product via apply_to rules.
	 */
	public function render_modifier_select_field( $field_args, $field ) {
		$product_id = $field->object_id;

		// Check if the field has ever been saved (meta key exists in DB)
		$has_been_saved = metadata_exists( 'post', $product_id, 'o100_addon_include' );
		$saved          = get_post_meta( $product_id, 'o100_addon_include', true );
		if ( ! is_array( $saved ) ) {
			$saved = $saved ? array( $saved ) : array();
		}

		$settings = get_option( 'o100_product_options', array() );
		$groups   = ( is_array( $settings ) && isset( $settings['o100_addon_groups'] ) && is_array( $settings['o100_addon_groups'] ) )
			? $settings['o100_addon_groups'] : array();

		// Get this product's category IDs
		$product_cat_ids = wc_get_product_term_ids( $product_id, 'product_cat' );

		// Build options and determine auto-selected
		$auto_selected = array();
		$options_list  = array();

		foreach ( $groups as $index => $group ) {
			$name = ! empty( $group['_name'] ) ? $group['_name'] : sprintf( __( 'Modifier %d', 'order100' ), $index + 1 );
			$id   = ! empty( $group['_id'] ) ? $group['_id'] : 'grp_' . $index;

			$apply_to = isset( $group['_apply_to'] ) ? $group['_apply_to'] : 'all';
			$is_auto  = false;

			if ( $apply_to === 'all' ) {
				$is_auto = true;
			} elseif ( $apply_to === 'categories' && ! empty( $group['_category_ids'] ) ) {
				$cat_ids = array_map( 'intval', (array) $group['_category_ids'] );
				if ( array_intersect( $product_cat_ids, $cat_ids ) ) {
					$is_auto = true;
				}
			} elseif ( $apply_to === 'products' && ! empty( $group['_product_ids'] ) ) {
				$prod_ids = is_array( $group['_product_ids'] )
					? array_map( 'intval', $group['_product_ids'] )
					: array_map( 'intval', array_filter( explode( ',', $group['_product_ids'] ) ) );
				if ( in_array( (int) $product_id, $prod_ids, true ) ) {
					$is_auto = true;
				}
			}

			if ( $is_auto ) {
				$auto_selected[] = $id;
			}

			$options_list[] = array(
				'id'      => $id,
				'name'    => $name,
				'is_auto' => $is_auto,
			);
		}

		// If field has NEVER been saved, use auto-selected as defaults.
		// Once saved, respect ONLY the user's explicit choices.
		if ( $has_been_saved ) {
			$selected = $saved;
		} else {
			$selected = $auto_selected;
		}

		// Render
		$field_name = $field->args( '_name' ) ?: 'o100_addon_include';
		?>
		<div class="cmb-row cmb-type-select">
			<div class="cmb-th">
				<label for="o100_addon_include"><?php echo esc_html( $field->args( 'name' ) ); ?></label>
			</div>
			<div class="cmb-td">
				<select id="o100_addon_include" name="o100_addon_include[]" multiple="multiple"
						style="width:100%; min-height:38px;" class="o100-modifier-multiselect">
					<?php foreach ( $options_list as $opt ) :
						$is_selected = in_array( $opt['id'], $selected, true );
						$label = esc_html( $opt['name'] );
						if ( $opt['is_auto'] ) {
							$label .= ' ★';
						}
					?>
						<option value="<?php echo esc_attr( $opt['id'] ); ?>"<?php selected( $is_selected ); ?>>
							<?php echo $label; ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="cmb2-metabox-description">
					<?php echo esc_html( $field->args( 'description' ) ); ?>
					<br><small><?php esc_html_e( '★ = auto-applied by global modifier rules (category/product targeting)', 'order100' ); ?></small>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Get global modifier options as id => name array.
	 */
	public function get_global_modifier_options( $field ) {
		$options  = array();
		$settings = get_option( 'o100_product_options', array() );

		if ( ! is_array( $settings ) ) {
			return $options;
		}

		$groups = isset( $settings['o100_addon_groups'] ) && is_array( $settings['o100_addon_groups'] )
			? $settings['o100_addon_groups']
			: array();

		foreach ( $groups as $index => $group ) {
			$name = ! empty( $group['_name'] ) ? $group['_name'] : sprintf( __( 'Modifier %d', 'order100' ), $index + 1 );
			$id   = ! empty( $group['_id'] ) ? $group['_id'] : 'grp_' . $index;
			$options[ $id ] = $name;
		}

		return $options;
	}

	public function is_product_context( $field ) {
		return get_post_type( $field->object_id ) === 'product';
	}

	/* ───────────────────────────────────────────────
	 * 7. Admin Assets
	 * ─────────────────────────────────────────────── */

	public function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Load on product edit page and Order100 settings page
		if ( $screen->post_type !== 'product' && $screen->id !== 'toplevel_page_order100' ) {
			return;
		}

		wp_enqueue_style(
			'o100-product-addons-admin',
			O100_ADDONS_URL . 'assets/css/admin.css',
			array(),
			filemtime( O100_ADDONS_PATH . 'assets/css/admin.css' )
		);

		// Enqueue selectWoo for the modifier multiselect dropdown
		if ( $screen->post_type === 'product' ) {
			if ( wp_script_is( 'selectWoo', 'registered' ) ) {
				wp_enqueue_script( 'selectWoo' );
				wp_enqueue_style( 'select2' );
			}
		}

		wp_enqueue_script(
			'o100-product-addons-admin',
			O100_ADDONS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			filemtime( O100_ADDONS_PATH . 'assets/js/admin.js' ),
			true
		);
	}

	/* ───────────────────────────────────────────────
	 * 8. WooCommerce Import/Export
	 * ─────────────────────────────────────────────── */

	public function import_unserialize( $data ) {
		$keys = array( 'o100_addon_groups' );
		if ( isset( $data['meta_data'] ) ) {
			foreach ( $data['meta_data'] as $index => $meta ) {
				if ( in_array( $meta['key'], $keys, true ) && ! empty( $meta['value'] ) && is_string( $meta['value'] ) ) {
					$data['meta_data'][ $index ]['value'] = maybe_unserialize( $meta['value'] );
				}
			}
		}
		return $data;
	}

	public function export_serialize( $value, $meta, $product, $row ) {
		if ( $meta->key === 'o100_addon_groups' ) {
			return maybe_serialize( $value );
		}
		return $value;
	}
}

