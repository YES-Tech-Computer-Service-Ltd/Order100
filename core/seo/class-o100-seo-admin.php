<?php
/**
 * Smart SEO Admin Class — Step-based Wizard UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_SEO_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		// AJAX save for Schema settings (CMB2 form is broken by nested forms)
		add_action( 'wp_ajax_o100_save_schema', array( $this, 'ajax_save_schema' ) );
	}

	/**
	 * AJAX handler for saving all SEO/Schema settings
	 */
	public function ajax_save_schema() {
		check_ajax_referer( 'o100_seo_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}
		$this->save_seo_settings();
		wp_send_json_success( array( 'message' => 'Settings saved.' ) );
	}

	/**
	 * Save SEO-specific settings from the POST data
	 */
	public function save_seo_settings() {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$options = get_option( 'o100_options', array() );

		$features = array( 'focus_keyword', 'alt_text', 'slug', 'title_desc', 'snippet', 'image_rename' );
		foreach ( $features as $f ) {
			$options[ 'o100_seo_' . $f . '_enabled' ] = isset( $_POST[ 'o100_seo_' . $f . '_enabled' ] ) ? 'on' : 'off';
			if ( isset( $_POST[ 'o100_seo_' . $f . '_rule' ] ) ) {
				$options[ 'o100_seo_' . $f . '_rule' ] = stripslashes( $_POST[ 'o100_seo_' . $f . '_rule' ] );
			}
		}

		// Save separate title/description rules
		if ( isset( $_POST['o100_seo_title_rule'] ) ) {
			$options['o100_seo_title_rule'] = stripslashes( $_POST['o100_seo_title_rule'] );
		}
		if ( isset( $_POST['o100_seo_desc_rule'] ) ) {
			$options['o100_seo_desc_rule'] = stripslashes( $_POST['o100_seo_desc_rule'] );
		}

		update_option( 'o100_options', $options );

		// Save Google Reviews settings
		O100_Google_Reviews::save_settings();

		// Save Schema settings
		$schema = get_option( 'o100_schema_settings', array() );
		$schema_fields = array( 'restaurant_name', 'cuisine', 'phone', 'price_range', 'address', 'brand' );
		foreach ( $schema_fields as $field ) {
			if ( isset( $_POST[ 'o100_schema_' . $field ] ) ) {
				$schema[ $field ] = sanitize_text_field( $_POST[ 'o100_schema_' . $field ] );
			}
		}
		// FAQ pairs
		if ( isset( $_POST['o100_schema_faq_q'] ) && is_array( $_POST['o100_schema_faq_q'] ) ) {
			$faqs = array();
			$questions = $_POST['o100_schema_faq_q'];
			$answers   = $_POST['o100_schema_faq_a'] ?? array();
			for ( $i = 0; $i < count( $questions ); $i++ ) {
				$q = sanitize_text_field( $questions[ $i ] );
				$a = sanitize_textarea_field( $answers[ $i ] ?? '' );
				if ( ! empty( $q ) && ! empty( $a ) ) {
					$faqs[] = array( 'q' => $q, 'a' => $a );
				}
			}
			$schema['faqs'] = $faqs;
		}

		// Opening hours
		$hours = array();
		$days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		foreach ( $days as $day ) {
			if ( isset( $_POST[ 'o100_hours_' . $day . '_open' ] ) ) {
				$hours[ $day . '_open' ]  = sanitize_text_field( $_POST[ 'o100_hours_' . $day . '_open' ] );
				$hours[ $day . '_close' ] = sanitize_text_field( $_POST[ 'o100_hours_' . $day . '_close' ] );
				$hours[ $day . '_closed' ] = ! empty( $_POST[ 'o100_hours_' . $day . '_closed' ] ) ? 1 : 0;
			}
		}
		$schema['hours'] = $hours;

		// Geo location
		$geo_fields = array( 'geo_lat', 'geo_lng', 'geo_region', 'geo_city' );
		foreach ( $geo_fields as $field ) {
			if ( isset( $_POST[ 'o100_schema_' . $field ] ) ) {
				$schema[ $field ] = sanitize_text_field( $_POST[ 'o100_schema_' . $field ] );
			}
		}

		// NAP footer
		$schema['nap_footer'] = ! empty( $_POST['o100_schema_nap_footer'] ) ? 1 : 0;

		update_option( 'o100_schema_settings', $schema );
	}

	/**
	 * Render the SEO tab contents — Step-based wizard layout
	 */
	public function render_seo_tab() {
		$options = get_option( 'o100_options', array() );

		$steps = array(
			'focus_keyword' => array(
				'num'         => 1,
				'icon'        => 'dashicons-tag',
				'title'       => __( 'Focus Keyword', 'order100' ),
				'desc'        => __( 'Generate Focus Keywords for Rank Math. This is the foundation — all other steps reference it.', 'order100' ),
				'help'        => __( 'The system reads each product name, removes Chinese characters, item numbers, filler words (e.g. "House Special", "Combo"), and extra items after "&" or "or". It then extracts the core dish identity — typically 3-4 words like "Ginger Beef Noodles" — and appends your location for local SEO targeting. Products that already have a keyword will be preserved unless you check "Force overwrite".', 'order100' ),
				'default'     => '[smart_keyword] [location]',
				'high_risk'   => false,
				'dynamic'     => false,
			),
			'alt_text' => array(
				'num'         => 2,
				'icon'        => 'dashicons-format-image',
				'title'       => __( 'Image Alt Text', 'order100' ),
				'desc'        => __( 'Generate Alt Text using the Focus Keyword for SEO consistency.', 'order100' ),
				'help'        => __( 'Alt Text helps search engines understand your images and improves accessibility. This step uses the Focus Keyword you generated in Step 1 as the base, ensuring your images are indexed for the same terms as your product page. Google Images is a significant traffic source for food businesses.', 'order100' ),
				'default'     => '[focus_keyword] - [site_name]',
				'high_risk'   => false,
				'dynamic'     => false,
			),
			'slug' => array(
				'num'         => 3,
				'icon'        => 'dashicons-admin-links',
				'title'       => __( 'Product Slug (URL)', 'order100' ),
				'desc'        => __( 'Update product URL slugs based on Focus Keyword. Auto-creates 301 redirects via Rank Math.', 'order100' ),
				'help'        => __( 'A clean URL like /lemon-chicken-regina/ ranks better than /product-2387/. This step converts the Focus Keyword into a URL-friendly slug. Old URLs are automatically redirected (301) so you don\'t lose any existing Google rankings or break bookmarked links.', 'order100' ),
				'default'     => '[focus_keyword_slug]',
				'high_risk'   => true,
				'dynamic'     => false,
			),
			'image_rename' => array(
				'num'         => 4,
				'icon'        => 'dashicons-images-alt2',
				'title'       => __( 'Image File Rename', 'order100' ),
				'desc'        => __( 'Rename image files to match Focus Keyword slug. Updates thumbnails and all DB references.', 'order100' ),
				'help'        => __( 'Google reads image filenames for ranking signals. A file named "lemon-chicken-regina.jpg" performs much better than "IMG_20240315_001.jpg" or "宫保鸡丁.png". This step renames all physical files, regenerates thumbnail references, and updates every URL reference across your database to prevent broken images.', 'order100' ),
				'default'     => '[focus_keyword_slug]',
				'high_risk'   => true,
				'dynamic'     => false,
			),
			'title_desc' => array(
				'num'           => 5,
				'icon'          => 'dashicons-editor-textcolor',
				'title'         => __( 'SEO Title & Description', 'order100' ),
				'desc'          => __( 'Generate SEO Title and Meta Description and write them to Rank Math fields. Products with existing custom titles/descriptions will be preserved unless Force is checked.', 'order100' ),
				'help'          => __( 'The SEO Title (blue link in Google results) should be pure English with your Focus Keyword — Chinese characters waste the 60-char limit and confuse language signals. The Meta Description (grey text below the link) has 160 chars and is a good place for Chinese names to attract bilingual customers. Data is written to Rank Math meta fields so you can see SEO scores and edit individual products.', 'order100' ),
				'default'       => '[focus_keyword] in [location] | [site_name]',
				'default_desc'  => 'Order [focus_keyword] [chinese_name] in [location]. Best Chinese food delivery & takeout in [city].',
				'high_risk'     => false,
				'dynamic'       => false,
				'split_fields'  => true,
			),
			'snippet' => array(
				'num'         => 6,
				'icon'        => 'dashicons-editor-code',
				'title'       => __( 'Keyword in Content', 'order100' ),
				'desc'        => __( 'Append a visible sentence with your Focus Keyword at the end of the product description to boost Rank Math scores.', 'order100' ),
				'help'        => __( 'Rank Math checks if your Focus Keyword appears in the content and calculates keyword density. Most WooCommerce food products only have a short description and score 0. This step appends a natural, visible sentence at the end of the description — after the menu item details — so it reads like a call-to-action without disrupting the food description.', 'order100' ),
				'default'     => 'Order [focus_keyword] online from [site_name]. Fresh, fast delivery & pickup!',
				'high_risk'   => false,
				'dynamic'       => false,
				'textarea'    => true,
			),
		);
		?>
		<div class="cmb-row o100-tab-seo" style="border-bottom:none; padding:0;">
			<div class="o100-seo-wizard">

				<!-- Step Navigation Tabs -->
				<div class="o100-seo-steps-nav">
					<?php foreach ( $steps as $id => $step ) : ?>
						<a href="#" class="o100-seo-step-link <?php echo $id === 'focus_keyword' ? 'is-active' : ''; ?>" data-step="<?php echo esc_attr( $id ); ?>">
							<span class="o100-seo-step-num"><?php echo $step['num']; ?></span>
							<span class="dashicons <?php echo esc_attr( $step['icon'] ); ?>"></span>
							<span class="o100-seo-step-text"><?php echo esc_html( $step['title'] ); ?></span>
						</a>
					<?php endforeach; ?>
					<a href="#" class="o100-seo-step-link" data-step="schema">
						<span class="o100-seo-step-num">7</span>
						<span class="dashicons dashicons-shortcode"></span>
						<span class="o100-seo-step-text"><?php esc_html_e( 'Schema', 'order100' ); ?></span>
					</a>
				</div>

				<!-- Tags Reference (collapsible) -->
				<div class="o100-seo-tags-toggle" style="padding: 12px 24px; background: #fafafa; border-bottom: 1px solid #e5e7eb; cursor: pointer;" onclick="jQuery('.o100-seo-tags-panel').slideToggle(200); jQuery(this).find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');">
					<span class="dashicons dashicons-arrow-down-alt2" style="font-size:14px; width:14px; height:14px; margin-right:6px; color:#6b7280;"></span>
					<strong style="font-size:13px; color:#374151;"><?php esc_html_e( 'Available Tags Reference', 'order100' ); ?></strong>
				</div>
				<div class="o100-seo-tags-panel" style="display:none; padding:16px 24px; background: #fff; border-bottom: 1px solid #e5e7eb;">
					<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:8px; font-size:13px;">
						<div><code>[smart_keyword]</code> — <?php esc_html_e( 'Intelligent extraction (Step 1)', 'order100' ); ?></div>
						<div><code style="color:#059669;">[focus_keyword]</code> — <?php esc_html_e( 'Saved keyword (Steps 2-6)', 'order100' ); ?></div>
						<div><code style="color:#059669;">[focus_keyword_slug]</code> — <?php esc_html_e( 'URL slug of keyword', 'order100' ); ?></div>
						<div><code>[dish_name]</code> — <?php esc_html_e( 'Full English dish name', 'order100' ); ?></div>
						<div><code>[chinese_name]</code> — <?php esc_html_e( 'Chinese characters', 'order100' ); ?></div>
						<div><code>[location]</code> / <code>[city]</code> — <?php esc_html_e( 'Location/City', 'order100' ); ?></div>
						<div><code>[site_name]</code> — <?php esc_html_e( 'Website title', 'order100' ); ?></div>
						<div><code>[category]</code> / <code>[price]</code> / <code>[sku]</code></div>
					</div>
				</div>

				<!-- Step Content Panels -->
				<?php foreach ( $steps as $id => $step ) : ?>
					<div class="o100-seo-step-panel <?php echo $id === 'focus_keyword' ? 'is-active' : ''; ?>" data-step="<?php echo esc_attr( $id ); ?>">
						<?php $this->render_step_content( $id, $step, $options ); ?>
					</div>
				<?php endforeach; ?>

				<!-- Step 7: Schema Enhancement -->
				<div class="o100-seo-step-panel" data-step="schema">
					<?php $this->render_schema_step( $options ); ?>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Render Schema Enhancement step (Step 7)
	 */
	private function render_schema_step( $options ) {
		$schema = get_option( 'o100_schema_settings', array() );
		$google = get_option( 'o100_google_reviews', array() );
		?>
		<div class="o100-seo-step-inner">
			<div class="o100-seo-step-header">
				<div>
					<h3><?php esc_html_e( 'Schema Enhancement', 'order100' ); ?></h3>
					<p style="color:#6b7280; font-size:13px; margin:4px 0 0;">
						<?php esc_html_e( 'Enhance structured data to get rich snippets (stars, FAQ, business info) in Google search results.', 'order100' ); ?>
					</p>
				</div>
			</div>

			<!-- Prerequisite Notice -->
			<div style="margin-top:16px; padding:14px 18px; background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; display:flex; align-items:flex-start; gap:10px;">
				<span class="dashicons dashicons-warning" style="color:#ea580c; margin-top:2px;"></span>
				<div style="font-size:13px; color:#9a3412; line-height:1.5;">
					<strong><?php esc_html_e( 'Prerequisite:', 'order100' ); ?></strong>
					<?php printf(
						/* translators: %s = link to Rank Math settings */
						esc_html__( 'Please ensure Rank Math Schema type is set to "WooCommerce Product" in %s. Go to Rank Math → Titles & Meta → Products → Schema Type → select "Product".', 'order100' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=rank-math-options-titles#setting-panel-post-type-product' ) ) . '" target="_blank" style="color:#ea580c; font-weight:600;">' .
						esc_html__( 'Rank Math Settings', 'order100' ) . ' ↗</a>'
					); ?>
				</div>
			</div>

			<!-- Section A: Google Reviews → AggregateRating -->
			<div style="margin-top:20px; padding:20px; background:#fefce8; border:1px solid #fde68a; border-radius:8px;">
				<h4 style="margin:0 0 8px; font-size:14px; display:flex; align-items:center; gap:6px;">
					<span class="dashicons dashicons-star-filled" style="color:#fbbc04;"></span>
					<?php esc_html_e( 'Google Reviews → Star Ratings', 'order100' ); ?>
				</h4>
				<p style="margin:0 0 12px; color:#6b7280; font-size:12px;">
					<?php esc_html_e( 'Enter your Google rating. All product pages will show ⭐ stars in search results.', 'order100' ); ?>
				</p>
				<div style="display:flex; gap:12px; flex-wrap:wrap;">
					<div style="min-width:100px;">
						<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Rating', 'order100' ); ?></label>
						<input type="number" name="o100_google_rating" value="<?php echo esc_attr( $google['rating'] ?? '' ); ?>"
							   min="1" max="5" step="0.1" placeholder="4.5"
							   style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
					</div>
					<div style="min-width:100px;">
						<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Reviews', 'order100' ); ?></label>
						<input type="number" name="o100_google_review_count" value="<?php echo esc_attr( $google['review_count'] ?? '' ); ?>"
							   min="0" step="1" placeholder="120"
							   style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
					</div>
					<div style="flex:1; min-width:200px;">
						<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Google Review URL', 'order100' ); ?></label>
						<input type="url" name="o100_google_review_url" value="<?php echo esc_attr( $google['review_url'] ?? '' ); ?>"
							   placeholder="https://g.page/r/..." style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
					</div>
				</div>
				<?php if ( ! empty( $google['rating'] ) && ! empty( $google['review_count'] ) ) : ?>
				<div style="margin-top:8px; padding:6px 10px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px; font-size:12px;">
					<strong style="color:#166534;">✅ Active</strong> —
					⭐ <?php echo esc_html( $google['rating'] ); ?> (<?php echo esc_html( $google['review_count'] ); ?> reviews) in Schema
				</div>
				<?php endif; ?>
			</div>

			<!-- Section B: Restaurant / FoodEstablishment Schema -->
			<div style="margin-top:16px; padding:20px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px;">
				<h4 style="margin:0 0 8px; font-size:14px; display:flex; align-items:center; gap:6px;">
					<span class="dashicons dashicons-store" style="color:#3b82f6;"></span>
					<?php esc_html_e( 'Restaurant Schema', 'order100' ); ?>
				</h4>
				<p style="margin:0 0 12px; color:#6b7280; font-size:12px;">
					<?php esc_html_e( 'Injected as FoodEstablishment on your shop/homepage for local search visibility.', 'order100' ); ?>
				</p>
				<div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
					<div>
						<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Restaurant Name', 'order100' ); ?></label>
						<input type="text" name="o100_schema_restaurant_name" value="<?php echo esc_attr( $schema['restaurant_name'] ?? '' ); ?>"
							   placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
							   style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
					</div>
					<div>
						<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Cuisine Type', 'order100' ); ?></label>
						<input type="text" name="o100_schema_cuisine" value="<?php echo esc_attr( $schema['cuisine'] ?? '' ); ?>"
							   placeholder="Chinese, Canadian"
							   style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
					</div>
					<div>
						<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Phone', 'order100' ); ?></label>
						<input type="text" name="o100_schema_phone" value="<?php echo esc_attr( $schema['phone'] ?? '' ); ?>"
							   placeholder="+1-306-555-1234"
							   style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
					</div>
					<div>
						<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Price Range', 'order100' ); ?></label>
						<select name="o100_schema_price_range" style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
							<option value=""><?php esc_html_e( '— Select —', 'order100' ); ?></option>
							<option value="$" <?php selected( $schema['price_range'] ?? '', '$' ); ?>>$ (Budget)</option>
							<option value="$$" <?php selected( $schema['price_range'] ?? '', '$$' ); ?>>$$ (Moderate)</option>
							<option value="$$$" <?php selected( $schema['price_range'] ?? '', '$$$' ); ?>>$$$ (Upscale)</option>
						</select>
					</div>
					<div style="grid-column: 1 / -1;">
						<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Full Address', 'order100' ); ?></label>
						<input type="text" name="o100_schema_address" value="<?php echo esc_attr( $schema['address'] ?? '' ); ?>"
							   placeholder="1234 Main St, Regina, SK S4P 1A1"
							   style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
					</div>
				</div>
			</div>

			<!-- Section C: Product Brand -->
			<div style="margin-top:16px; padding:20px; background:#faf5ff; border:1px solid #e9d5ff; border-radius:8px;">
				<h4 style="margin:0 0 8px; font-size:14px; display:flex; align-items:center; gap:6px;">
					<span class="dashicons dashicons-nametag" style="color:#a855f7;"></span>
					<?php esc_html_e( 'Product Brand', 'order100' ); ?>
				</h4>
				<p style="margin:0 0 12px; color:#6b7280; font-size:12px;">
					<?php esc_html_e( 'Automatically sets brand on all products for Google search brand association.', 'order100' ); ?>
				</p>
				<div style="max-width:300px;">
					<input type="text" name="o100_schema_brand" value="<?php echo esc_attr( $schema['brand'] ?? '' ); ?>"
						   placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
						   style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
				</div>
			</div>

			<!-- Section D: FAQ Schema -->
			<div style="margin-top:16px; padding:20px; background:#f0fdfa; border:1px solid #99f6e4; border-radius:8px;">
				<h4 style="margin:0 0 8px; font-size:14px; display:flex; align-items:center; gap:6px;">
					<span class="dashicons dashicons-editor-help" style="color:#14b8a6;"></span>
					<?php esc_html_e( 'FAQ Schema', 'order100' ); ?>
				</h4>
				<p style="margin:0 0 12px; color:#6b7280; font-size:12px;">
					<?php esc_html_e( 'Add FAQ structured data to all product pages. Google may show these as expandable Q&A in search results. Pre-filled with restaurant-optimized content — edit as needed.', 'order100' ); ?>
				</p>
				<?php
				$faqs = $schema['faqs'] ?? array();
				$defaults = $this->get_default_faqs();
				if ( empty( $faqs ) ) {
					$faqs = $defaults;
				}
				?>
				<div id="o100-faq-list">
				<?php foreach ( $faqs as $i => $faq ) : ?>
				<div class="o100-faq-item" style="margin-bottom:10px; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:6px; position:relative;">
					<button type="button" class="o100-faq-remove" style="position:absolute; top:8px; right:8px; background:none; border:none; color:#dc2626; cursor:pointer; font-size:16px;" title="Remove">&times;</button>
					<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;">Q:</label>
					<input type="text" name="o100_schema_faq_q[]" value="<?php echo esc_attr( $faq['q'] ); ?>"
						   placeholder="<?php esc_attr_e( 'Enter question...', 'order100' ); ?>"
						   style="width:calc(100% - 24px); padding:5px 8px; border:1px solid #d1d5db; border-radius:4px; margin-bottom:6px;">
					<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;">A:</label>
					<textarea name="o100_schema_faq_a[]" rows="2"
							  placeholder="<?php esc_attr_e( 'Enter answer...', 'order100' ); ?>"
							  style="width:100%; padding:5px 8px; border:1px solid #d1d5db; border-radius:4px; resize:vertical;"><?php echo esc_textarea( $faq['a'] ); ?></textarea>
				</div>
				<?php endforeach; ?>
				</div>
				<button type="button" id="o100-faq-add" class="button" style="margin-top:8px;">
					<span class="dashicons dashicons-plus-alt2" style="margin-top:3px;"></span>
					<?php esc_html_e( 'Add FAQ', 'order100' ); ?>
				</button>
				<p style="font-size:11px; color:#9ca3af; margin:4px 0 0;">
					<?php esc_html_e( 'Tags: [site_name], [location], [city]. Empty pairs are skipped.', 'order100' ); ?>
				</p>
				<script>
				jQuery(function($){
					$('#o100-faq-add').on('click', function(){
						var html = '<div class="o100-faq-item" style="margin-bottom:10px; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:6px; position:relative;">' +
							'<button type="button" class="o100-faq-remove" style="position:absolute; top:8px; right:8px; background:none; border:none; color:#dc2626; cursor:pointer; font-size:16px;" title="Remove">&times;</button>' +
							'<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;">Q:</label>' +
							'<input type="text" name="o100_schema_faq_q[]" placeholder="Enter question..." style="width:calc(100% - 24px); padding:5px 8px; border:1px solid #d1d5db; border-radius:4px; margin-bottom:6px;">' +
							'<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;">A:</label>' +
							'<textarea name="o100_schema_faq_a[]" rows="2" placeholder="Enter answer..." style="width:100%; padding:5px 8px; border:1px solid #d1d5db; border-radius:4px; resize:vertical;"></textarea>' +
							'</div>';
						$('#o100-faq-list').append(html);
					});
					$(document).on('click', '.o100-faq-remove', function(){
						$(this).closest('.o100-faq-item').fadeOut(200, function(){ $(this).remove(); });
					});
				});
				</script>
			</div>

			<!-- Section E: Opening Hours -->
			<div style="margin-top:16px; padding:20px; background:#fefce8; border:1px solid #fde68a; border-radius:8px;">
				<h4 style="margin:0 0 8px; font-size:14px; display:flex; align-items:center; gap:6px;">
					<span class="dashicons dashicons-clock" style="color:#d97706;"></span>
					<?php esc_html_e( 'Opening Hours', 'order100' ); ?>
				</h4>
				<p style="margin:0 0 12px; color:#6b7280; font-size:12px;">
					<?php esc_html_e( 'Added to Restaurant Schema. Google shows "Open now · Closes 10 PM" in search results.', 'order100' ); ?>
				</p>
				<?php
				$hours = $schema['hours'] ?? array();
				$days = array(
					'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
				);
				?>
				<div style="display:grid; grid-template-columns: auto 1fr 1fr auto; gap:6px 10px; align-items:center; font-size:13px;">
					<div style="font-weight:600; font-size:11px; color:#6b7280;"><?php esc_html_e( 'Day', 'order100' ); ?></div>
					<div style="font-weight:600; font-size:11px; color:#6b7280;"><?php esc_html_e( 'Open', 'order100' ); ?></div>
					<div style="font-weight:600; font-size:11px; color:#6b7280;"><?php esc_html_e( 'Close', 'order100' ); ?></div>
					<div style="font-weight:600; font-size:11px; color:#6b7280;"><?php esc_html_e( 'Closed?', 'order100' ); ?></div>
					<?php foreach ( $days as $day ) :
						$key = strtolower( $day );
						$open  = $hours[ $key . '_open' ] ?? '11:00';
						$close = $hours[ $key . '_close' ] ?? '21:00';
						$closed = ! empty( $hours[ $key . '_closed' ] );
					?>
					<div style="font-size:12px;"><?php echo esc_html( substr( $day, 0, 3 ) ); ?></div>
					<input type="time" name="o100_hours_<?php echo $key; ?>_open" value="<?php echo esc_attr( $open ); ?>"
						   style="padding:4px 6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
					<input type="time" name="o100_hours_<?php echo $key; ?>_close" value="<?php echo esc_attr( $close ); ?>"
						   style="padding:4px 6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
					<label style="font-size:12px; display:flex; align-items:center; gap:3px;">
						<input type="checkbox" name="o100_hours_<?php echo $key; ?>_closed" value="1" <?php checked( $closed ); ?>>
						<?php esc_html_e( 'Closed', 'order100' ); ?>
					</label>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Section F: Geo Location -->
			<div style="margin-top:16px; padding:20px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px;">
				<h4 style="margin:0 0 8px; font-size:14px; display:flex; align-items:center; gap:6px;">
					<span class="dashicons dashicons-location" style="color:#0284c7;"></span>
					<?php esc_html_e( 'Geo Location Tags', 'order100' ); ?>
				</h4>
				<p style="margin:0 0 12px; color:#6b7280; font-size:12px;">
					<?php esc_html_e( 'Geo meta tags help search engines pinpoint your service area. Find coordinates at Google Maps → right-click your location → copy coordinates.', 'order100' ); ?>
				</p>
				<div style="display:flex; gap:12px; flex-wrap:wrap;">
					<div style="min-width:120px;">
						<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Latitude', 'order100' ); ?></label>
						<input type="text" name="o100_schema_geo_lat" value="<?php echo esc_attr( $schema['geo_lat'] ?? '' ); ?>"
							   placeholder="50.4452" style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
					</div>
					<div style="min-width:120px;">
						<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Longitude', 'order100' ); ?></label>
						<input type="text" name="o100_schema_geo_lng" value="<?php echo esc_attr( $schema['geo_lng'] ?? '' ); ?>"
							   placeholder="-104.6189" style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
					</div>
					<div style="min-width:100px;">
						<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Region', 'order100' ); ?></label>
						<input type="text" name="o100_schema_geo_region" value="<?php echo esc_attr( $schema['geo_region'] ?? '' ); ?>"
							   placeholder="CA-SK" style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
					</div>
					<div style="flex:1; min-width:140px;">
						<label style="display:block; font-weight:600; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'City', 'order100' ); ?></label>
						<input type="text" name="o100_schema_geo_city" value="<?php echo esc_attr( $schema['geo_city'] ?? '' ); ?>"
							   placeholder="Regina" style="width:100%; padding:6px 10px; border:1px solid #d1d5db; border-radius:6px;">
					</div>
				</div>
			</div>

			<!-- Section G: NAP Footer -->
			<div style="margin-top:16px; padding:20px; background:#fdf2f8; border:1px solid #fbcfe8; border-radius:8px;">
				<h4 style="margin:0 0 8px; font-size:14px; display:flex; align-items:center; gap:6px;">
					<span class="dashicons dashicons-admin-home" style="color:#db2777;"></span>
					<?php esc_html_e( 'NAP Footer (Name · Address · Phone)', 'order100' ); ?>
				</h4>
				<p style="margin:0 0 12px; color:#6b7280; font-size:12px;">
					<?php esc_html_e( 'Automatically show restaurant name, address, and phone at the bottom of every page. Google checks NAP consistency across all pages.', 'order100' ); ?>
				</p>
				<label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
					<input type="checkbox" name="o100_schema_nap_footer" value="1" <?php checked( ! empty( $schema['nap_footer'] ) ); ?>>
					<strong><?php esc_html_e( 'Enable NAP Footer on all pages', 'order100' ); ?></strong>
				</label>
				<?php if ( ! empty( $schema['nap_footer'] ) ) : ?>
				<div style="margin-top:8px; padding:8px 12px; background:#fff; border:1px solid #e5e7eb; border-radius:4px; font-size:12px; color:#6b7280;">
					<?php esc_html_e( 'Preview:', 'order100' ); ?>
					<strong><?php echo esc_html( $schema['restaurant_name'] ?? get_bloginfo( 'name' ) ); ?></strong>
					· <?php echo esc_html( $schema['address'] ?? '—' ); ?>
					· <?php echo esc_html( $schema['phone'] ?? '—' ); ?>
				</div>
				<?php endif; ?>
				<p style="font-size:11px; color:#9ca3af; margin:6px 0 0;">
					<?php esc_html_e( 'Uses the Restaurant Name, Address, and Phone from the Restaurant Schema section above.', 'order100' ); ?>
				</p>
			</div>

			<!-- Save Schema Button -->
			<div style="margin-top:24px; display:flex; align-items:center; justify-content:flex-end; gap:12px;">
				<span id="o100-schema-feedback" style="font-size:13px; font-weight:500; display:none;"></span>
				<button type="button" id="o100-save-schema-btn"
						style="padding:10px 28px; font-size:14px; font-weight:600; color:#fff;
						background:linear-gradient(135deg, #1800AD, #6366f1); border:none; border-radius:8px;
						cursor:pointer; display:inline-flex; align-items:center; gap:6px;
						box-shadow:0 2px 8px rgba(99,102,241,0.3); transition:all 0.2s ease;"
						onmouseover="this.style.boxShadow='0 4px 14px rgba(99,102,241,0.45)'; this.style.transform='translateY(-1px)';"
						onmouseout="this.style.boxShadow='0 2px 8px rgba(99,102,241,0.3)'; this.style.transform='none';">
					<span class="dashicons dashicons-saved" style="font-size:16px; width:16px; height:16px;"></span>
					<?php esc_html_e( 'Save Schema Settings', 'order100' ); ?>
				</button>
			</div>

			<script>
			jQuery(function($){
				$('#o100-save-schema-btn').on('click', function(){
					var $btn = $(this);
					var $fb = $('#o100-schema-feedback');
					$btn.prop('disabled', true).text('Saving...');
					$fb.hide();

					// Collect all schema fields from Step 7
					var data = {
						action: 'o100_save_schema',
						nonce: o100SeoData.nonce,
						// Google Reviews
						o100_google_rating: $('input[name="o100_google_rating"]').val() || '',
						o100_google_review_count: $('input[name="o100_google_review_count"]').val() || '',
						o100_google_review_url: $('input[name="o100_google_review_url"]').val() || '',
						// Restaurant Schema
						o100_schema_restaurant_name: $('input[name="o100_schema_restaurant_name"]').val() || '',
						o100_schema_cuisine: $('input[name="o100_schema_cuisine"]').val() || '',
						o100_schema_phone: $('input[name="o100_schema_phone"]').val() || '',
						o100_schema_price_range: $('input[name="o100_schema_price_range"]').val() || '',
						o100_schema_address: $('input[name="o100_schema_address"]').val() || '',
						o100_schema_brand: $('input[name="o100_schema_brand"]').val() || '',
						// Geo
						o100_schema_geo_lat: $('input[name="o100_schema_geo_lat"]').val() || '',
						o100_schema_geo_lng: $('input[name="o100_schema_geo_lng"]').val() || '',
						o100_schema_geo_region: $('input[name="o100_schema_geo_region"]').val() || '',
						o100_schema_geo_city: $('input[name="o100_schema_geo_city"]').val() || '',
						// NAP
						o100_schema_nap_footer: $('input[name="o100_schema_nap_footer"]').is(':checked') ? '1' : '',
					};

					// FAQ pairs
					var faqQ = [], faqA = [];
					$('input[name="o100_schema_faq_q[]"]').each(function(){ faqQ.push($(this).val()); });
					$('textarea[name="o100_schema_faq_a[]"]').each(function(){ faqA.push($(this).val()); });
					data['o100_schema_faq_q'] = faqQ;
					data['o100_schema_faq_a'] = faqA;

					// Opening hours
					var days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
					$.each(days, function(i, day){
						data['o100_hours_' + day + '_open'] = $('input[name="o100_hours_' + day + '_open"]').val() || '';
						data['o100_hours_' + day + '_close'] = $('input[name="o100_hours_' + day + '_close"]').val() || '';
						data['o100_hours_' + day + '_closed'] = $('input[name="o100_hours_' + day + '_closed"]').is(':checked') ? '1' : '';
					});

					// SEO feature toggles (Steps 1-6)
					var features = ['focus_keyword','alt_text','slug','title_desc','snippet','image_rename'];
					$.each(features, function(i, f){
						data['o100_seo_' + f + '_enabled'] = $('input[name="o100_seo_' + f + '_enabled"]').is(':checked') ? 'on' : '';
						var $rule = $('[name="o100_seo_' + f + '_rule"]');
						if ($rule.length) data['o100_seo_' + f + '_rule'] = $rule.val() || '';
					});
					var $titleRule = $('[name="o100_seo_title_rule"]');
					var $descRule = $('[name="o100_seo_desc_rule"]');
					if ($titleRule.length) data['o100_seo_title_rule'] = $titleRule.val() || '';
					if ($descRule.length) data['o100_seo_desc_rule'] = $descRule.val() || '';

					$.post(o100SeoData.ajaxUrl, data, function(res){
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top:3px;margin-right:4px;"></span> Save Schema Settings');
						if (res.success) {
							$fb.css('color', '#16a34a').text('✓ Saved successfully!').fadeIn();
						} else {
							$fb.css('color', '#dc2626').text('✗ Save failed: ' + (res.data || 'Unknown error')).fadeIn();
						}
						setTimeout(function(){ $fb.fadeOut(); }, 3000);
					}).fail(function(){
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top:3px;margin-right:4px;"></span> Save Schema Settings');
						$fb.css('color', '#dc2626').text('✗ Network error').fadeIn();
						setTimeout(function(){ $fb.fadeOut(); }, 3000);
					});
				});
			});
			</script>

		</div>
		<?php
	}

	/**
	 * Pre-filled FAQ defaults optimized for restaurant SEO
	 */
	private function get_default_faqs() {
		return array(
			array(
				'q' => 'Do you offer delivery and takeout?',
				'a' => 'Yes! [site_name] offers both delivery and takeout in [city]. Order online for fast, convenient service right to your door.',
			),
			array(
				'q' => 'What type of food does [site_name] serve?',
				'a' => 'We serve authentic Chinese cuisine including popular dishes like Ginger Beef, Lemon Chicken, Chow Mein, and more. View our full menu online.',
			),
			array(
				'q' => 'How do I place an order online?',
				'a' => 'Simply browse our menu, add items to your cart, and checkout. You can pay online or at the door. Orders are confirmed instantly.',
			),
			array(
				'q' => 'Do you accommodate food allergies or dietary restrictions?',
				'a' => 'Yes, please note any allergies or special requests in the order comments. Contact us directly for detailed allergen information on any menu item.',
			),
			array(
				'q' => 'What areas do you deliver to?',
				'a' => 'We deliver throughout [city] and surrounding areas. Enter your address at checkout to confirm delivery availability and see estimated delivery time.',
			),
		);
	}

	/**
	 * Render individual step content
	 */
	private function render_step_content( $id, $step, $options ) {
		$saved_enabled = $options[ 'o100_seo_' . $id . '_enabled' ] ?? '';
		$is_enabled = ( $saved_enabled === 'on' || $saved_enabled === '' );
		$rule = $options[ 'o100_seo_' . $id . '_rule' ] ?? $step['default'];
		if ( empty( $rule ) ) {
			$rule = $step['default'];
		}
		$is_high_risk = $step['high_risk'] ?? false;
		$is_dynamic = $step['dynamic'] ?? false;
		$use_textarea = $step['textarea'] ?? false;
		$split_fields = $step['split_fields'] ?? false;
		?>
		<div class="o100-seo-step-inner">
			<!-- Step Header -->
			<div class="o100-seo-step-header">
				<div>
					<h3 class="o100-seo-step-title">
						<?php echo esc_html( $step['title'] ); ?>
						<?php if ( ! empty( $step['help'] ) ) : ?>
							<span class="o100-seo-help-toggle" title="<?php esc_attr_e( 'How does this work?', 'order100' ); ?>">
								<span class="dashicons dashicons-editor-help"></span>
							</span>
						<?php endif; ?>
					</h3>
					<p class="o100-seo-step-desc"><?php echo esc_html( $step['desc'] ); ?></p>
					<?php if ( ! empty( $step['help'] ) ) : ?>
						<div class="o100-seo-help-panel" style="display:none;">
							<p><?php echo esc_html( $step['help'] ); ?></p>
						</div>
					<?php endif; ?>
				</div>
				<div class="o100-seo-step-toggle">
					<label class="o100-seo-toggle">
						<input type="checkbox" name="o100_seo_<?php echo esc_attr( $id ); ?>_enabled" <?php checked( $is_enabled, true ); ?>>
						<span class="o100-seo-toggle-slider"></span>
					</label>
				</div>
			</div>

			<?php if ( $is_high_risk ) : ?>
				<div class="o100-seo-alert o100-seo-alert-warning">
					<?php if ( $id === 'slug' ) :
						$has_redirections = class_exists( '\\RankMath\\Redirections\\Redirection' );
					?>
						<?php if ( $has_redirections ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>
							<div>
								<strong><?php esc_html_e( '301 Redirect: Active ✓', 'order100' ); ?></strong>
								<p><?php esc_html_e( 'Rank Math Redirections module detected. Old URLs will be automatically 301-redirected when slugs change.', 'order100' ); ?></p>
							</div>
						<?php else : ?>
							<span class="dashicons dashicons-warning" style="color:#d63638;"></span>
							<div>
								<strong style="color:#d63638;"><?php esc_html_e( '⚠ 301 Redirect: NOT Available', 'order100' ); ?></strong>
								<p><?php esc_html_e( 'Rank Math Redirections module not found! Changing slugs without 301 redirects will cause 404 errors. Please enable it in Rank Math → Dashboard → Redirections before proceeding.', 'order100' ); ?></p>
							</div>
						<?php endif; ?>
					<?php elseif ( $id === 'image_rename' ) : ?>
						<span class="dashicons dashicons-warning" style="color:#f0b849;"></span>
						<div>
							<strong><?php esc_html_e( 'Disk + Database Changes', 'order100' ); ?></strong>
							<p><?php esc_html_e( 'Files will be physically renamed. All thumbnails, metadata, and URL references will be updated.', 'order100' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $id === 'slug' ) : ?>
				<div class="o100-seo-option-row">
					<label>
						<input type="checkbox" name="o100_seo_slug_filter" value="1" checked>
						<?php esc_html_e( 'Only fix invalid slugs (numeric/Chinese). Skip existing valid English slugs.', 'order100' ); ?>
					</label>
				</div>
			<?php endif; ?>

			<?php if ( $id === 'image_rename' ) : ?>
				<div class="o100-seo-option-row">
					<label>
						<input type="checkbox" name="o100_seo_image_rename_chinese_only" value="1" checked>
						<?php esc_html_e( 'Only rename files with Chinese chars, numeric-only, or hash names. Skip clean English filenames.', 'order100' ); ?>
					</label>
				</div>
			<?php endif; ?>

			<!-- Rule Input -->
			<?php if ( $split_fields ) :
				// Separate Title and Description fields
				$title_rule = $options['o100_seo_title_rule'] ?? $step['default'];
				if ( empty( $title_rule ) ) $title_rule = $step['default'];
				$desc_rule = $options['o100_seo_desc_rule'] ?? ( $step['default_desc'] ?? '' );
				if ( empty( $desc_rule ) ) $desc_rule = $step['default_desc'] ?? '';
			?>
				<div class="o100-seo-rule-group">
					<label class="o100-seo-rule-label">
						<?php esc_html_e( 'SEO Title Template:', 'order100' ); ?>
						<span style="font-weight:400; color:#6b7280; font-size:12px; margin-left:6px;"><?php esc_html_e( '(~60 chars, English only, no Chinese)', 'order100' ); ?></span>
					</label>
					<input type="text" name="o100_seo_title_rule" value="<?php echo esc_attr( $title_rule ); ?>" class="o100-seo-rule-input">
				</div>
				<div class="o100-seo-rule-group">
					<label class="o100-seo-rule-label">
						<?php esc_html_e( 'Meta Description Template:', 'order100' ); ?>
						<span style="font-weight:400; color:#6b7280; font-size:12px; margin-left:6px;"><?php esc_html_e( '(~160 chars, Chinese name OK here)', 'order100' ); ?></span>
					</label>
					<input type="text" name="o100_seo_desc_rule" value="<?php echo esc_attr( $desc_rule ); ?>" class="o100-seo-rule-input">
				</div>
			<?php else : ?>
				<div class="o100-seo-rule-group">
					<label class="o100-seo-rule-label"><?php esc_html_e( 'Generation Rule / Template:', 'order100' ); ?></label>
					<?php if ( $use_textarea ) : ?>
						<textarea name="o100_seo_<?php echo esc_attr( $id ); ?>_rule" rows="3" class="o100-seo-rule-input"><?php echo esc_textarea( $rule ); ?></textarea>
					<?php else : ?>
						<input type="text" name="o100_seo_<?php echo esc_attr( $id ); ?>_rule" value="<?php echo esc_attr( $rule ); ?>" class="o100-seo-rule-input">
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Action Bar -->
			<div class="o100-seo-action-bar">
				<?php if ( ! $is_dynamic ) : ?>
					<div class="o100-seo-action-buttons">
						<button type="button" class="o100-seo-btn o100-seo-btn-outline o100-seo-scan-btn" data-feature="<?php echo esc_attr( $id ); ?>" <?php echo $is_high_risk ? 'data-risk="1"' : ''; ?>>
							<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Preview', 'order100' ); ?>
						</button>
						<button type="button" class="o100-seo-btn o100-seo-btn-primary o100-seo-fix-btn" data-feature="<?php echo esc_attr( $id ); ?>" <?php echo $is_high_risk ? 'data-risk="1"' : ''; ?>>
							<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Apply', 'order100' ); ?>
						</button>
						<button type="button" class="o100-seo-btn o100-seo-btn-danger o100-seo-revert-btn" data-feature="<?php echo esc_attr( $id ); ?>" disabled>
							<span class="dashicons dashicons-undo"></span> <?php esc_html_e( 'Revert', 'order100' ); ?>
						</button>
					</div>
				<?php else : ?>
					<div class="o100-seo-dynamic-note">
						<span class="dashicons dashicons-info" style="color:#4f46e5;"></span>
						<?php esc_html_e( 'No Preview/Apply needed — this works automatically. Just save your templates above, and the system will generate Title & Description in real-time whenever someone visits a product page.', 'order100' ); ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Preview Table -->
			<div class="o100-seo-preview o100-preview-<?php echo esc_attr( $id ); ?>" style="display:none;">
				<div class="o100-seo-preview-header">
					<h4><?php esc_html_e( 'Preview', 'order100' ); ?></h4>
					<p style="margin:4px 0 0; font-size:12px; color:#6b7280;"><?php esc_html_e( '✓ Checked = will be updated to the "New" value when you click Apply. Uncheck to skip.', 'order100' ); ?></p>
				</div>
				<div class="o100-seo-preview-table-wrap">
					<table class="o100-seo-preview-table">
						<thead><tr>
							<th style="width:40px;"><input type="checkbox" class="o100-seo-select-all" checked></th>
							<th style="width:50px;">ID</th>
							<th><?php esc_html_e( 'Product', 'order100' ); ?></th>
							<th><?php esc_html_e( 'Current', 'order100' ); ?></th>
							<th><?php esc_html_e( 'New', 'order100' ); ?></th>
							<th style="width:90px;"><?php esc_html_e( 'Status', 'order100' ); ?></th>
						</tr></thead>
						<tbody class="o100-preview-body"></tbody>
					</table>
				</div>
				<div class="o100-seo-summary-bar">
					<span class="o100-summary-text"></span>
				</div>
			</div>

			<!-- Progress Bar -->
			<div class="o100-seo-progress o100-progress-<?php echo esc_attr( $id ); ?>" style="display:none;">
				<div class="o100-seo-progress-track">
					<div class="o100-progress-fill"></div>
				</div>
				<span class="o100-progress-text">0 / 0</span>
			</div>
		</div>
		<?php
	}
}
