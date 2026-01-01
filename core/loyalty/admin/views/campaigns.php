<?php
/**
 * Loyalty Campaigns View
 *
 * @package Order100
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$campaigns = class_exists( 'O100_Loyalty_DB' ) ? O100_Loyalty_DB::get_campaigns() : array();

// Fetch reward options: WooCommerce coupons + O100 Promotions
$rewards_list = array();
// WooCommerce native coupons
$coupon_posts = get_posts( array( 'post_type' => 'shop_coupon', 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => 'title', 'order' => 'ASC' ) );
foreach ( $coupon_posts as $cp ) {
	$coupon = new WC_Coupon( $cp->ID );
	$rewards_list[] = (object) array(
		'id'    => 'wc_' . $cp->ID,
		'name'  => '[WC] ' . $cp->post_title . ' (' . $coupon->get_discount_type() . ': ' . $coupon->get_amount() . ')',
		'group' => 'WooCommerce',
	);
}
// O100 Promotions
if ( class_exists( 'O100_Promotions_DB' ) ) {
	$promos = O100_Promotions_DB::query( array( 'status' => 'active' ) );
	foreach ( $promos as $p ) {
		if ( $p['rule_type'] !== 'simple' ) continue;
		$cfg = json_decode( $p['action_config'] ?? '{}', true );
		$dt  = $cfg['discount_type'] ?? '';
		$dv  = $cfg['discount_value'] ?? '';
		$sum = $dt === 'percentage' ? $dv . '%' : '$' . $dv;
		$rewards_list[] = (object) array(
			'id'    => 'promo_' . $p['id'],
			'name'  => '[Promo] ' . $p['title'] . ' (' . $sum . ')',
			'group' => 'O100 Promotions',
		);
	}
}

// Define the 12 Campaign Types (matching original WPLoyalty types)
$campaign_types = array(
	array( 'id' => 'point_for_purchase', 'title' => __( 'Points for Purchase', 'order100' ), 'desc' => __( 'Reward customers for their purchases. Ex: 10 points for every $100 spent.', 'order100' ), 'icon' => 'dashicons-cart' ),
	array( 'id' => 'order_value', 'title' => __( 'Reward based on spending', 'order100' ), 'desc' => __( 'Let customers earn points or rewards for their spending.', 'order100' ), 'icon' => 'dashicons-money-alt' ),
	array( 'id' => 'referral', 'title' => __( 'Referral', 'order100' ), 'desc' => __( 'Reward customers for referring their friends to your store.', 'order100' ), 'icon' => 'dashicons-groups' ),
	array( 'id' => 'signup', 'title' => __( 'Sign Up', 'order100' ), 'desc' => __( 'Reward customers for creating / registering an account.', 'order100' ), 'icon' => 'dashicons-id-alt' ),
	array( 'id' => 'product_review', 'title' => __( 'Write a review', 'order100' ), 'desc' => __( 'Reward customers when they write a review for a product.', 'order100' ), 'icon' => 'dashicons-star-filled' ),
	array( 'id' => 'birthday', 'title' => __( 'Birthday', 'order100' ), 'desc' => __( 'Reward customers for sharing their date of birth.', 'order100' ), 'icon' => 'dashicons-calendar-alt' ),
	array( 'id' => 'facebook_share', 'title' => __( 'Facebook Share', 'order100' ), 'desc' => __( 'Reward customers for sharing your store to Facebook.', 'order100' ), 'icon' => 'dashicons-facebook' ),
	array( 'id' => 'twitter_share', 'title' => __( 'Twitter Share', 'order100' ), 'desc' => __( 'Reward customers for sharing your store to Twitter.', 'order100' ), 'icon' => 'dashicons-twitter' ),
	array( 'id' => 'reddit_share', 'title' => __( 'Reddit Share', 'order100' ), 'desc' => __( 'Reward customers for sharing your store to Reddit.', 'order100' ), 'icon' => 'dashicons-share' ),
	array( 'id' => 'whatsapp_share', 'title' => __( 'WhatsApp Share', 'order100' ), 'desc' => __( 'Let customers share your products via WhatsApp.', 'order100' ), 'icon' => 'dashicons-smartphone' ),
	array( 'id' => 'email_share', 'title' => __( 'Email Share', 'order100' ), 'desc' => __( 'Reward customers for sharing your products via Email.', 'order100' ), 'icon' => 'dashicons-email' ),
	array( 'id' => 'followup_share', 'title' => __( 'Follow', 'order100' ), 'desc' => __( 'Let customers follow your pages in social media.', 'order100' ), 'icon' => 'dashicons-heart' ),
	array( 'id' => 'achievement', 'title' => __( 'Achievement', 'order100' ), 'desc' => __( 'Let customers earn points for achievements like Moving Up a level.', 'order100' ), 'icon' => 'dashicons-awards' ),
);

?>
<div class="o100-campaigns-container">

	<!-- ============================================== -->
	<!-- 1. CAMPAIGNS LIST VIEW -->
	<!-- ============================================== -->
	<div id="o100-campaigns-list-view">
		<div class="o100-view-header">
			<h2><?php esc_html_e( 'CAMPAIGNS', 'order100' ); ?></h2>
			<div class="o100-view-actions">
				<div class="o100-search-box">
					<input type="text" id="o100-campaign-search" placeholder="<?php esc_attr_e( 'Search campaign', 'order100' ); ?>" />
				</div>
				<button type="button" class="button button-primary o100-btn-create-campaign">
					<span class="dashicons dashicons-plus"></span>
					<?php esc_html_e( 'Create New Campaign', 'order100' ); ?>
				</button>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped o100-campaigns-table">
			<thead>
			<tr>
				<td class="manage-column column-cb check-column"><input type="checkbox" /></td>
				<th scope="col" class="column-title"><?php esc_html_e( 'TITLE / DESCRIPTION', 'order100' ); ?></th>
				<th scope="col" class="column-type"><?php esc_html_e( 'CAMPAIGN TYPE', 'order100' ); ?></th>
				<th scope="col" class="column-valid"><?php esc_html_e( 'VALID TILL', 'order100' ); ?></th>
				<th scope="col" class="column-status"><?php esc_html_e( 'ENABLE / DISABLE', 'order100' ); ?></th>
				<th scope="col" class="column-created"><?php esc_html_e( 'CREATED ON', 'order100' ); ?></th>
				<th scope="col" class="column-actions"><?php esc_html_e( 'ACTIONS', 'order100' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php if ( empty( $campaigns ) ) : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'No campaigns found.', 'order100' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $campaigns as $campaign ) : ?>
					<tr data-id="<?php echo esc_attr( $campaign->id ); ?>">
						<th scope="row" class="check-column"><input type="checkbox" name="campaign_ids[]" value="<?php echo esc_attr( $campaign->id ); ?>" /></th>
						<td class="column-title">
							<strong><?php echo esc_html( $campaign->name ); ?></strong><br/>
							<span class="description"><?php echo esc_html( $campaign->description ); ?></span>
						</td>
						<td class="column-type"><?php echo esc_html( ucwords( str_replace( '_', ' ', $campaign->action_type ) ) ); ?></td>
						<td class="column-valid"><?php echo ( $campaign->end_at > 0 ) ? date_i18n( get_option( 'date_format' ), $campaign->end_at ) : 'N/A'; ?></td>
						<td class="column-status">
							<label class="o100-toggle-switch">
								<input type="checkbox" class="o100-campaign-status-toggle" data-id="<?php echo esc_attr( $campaign->id ); ?>" <?php checked( $campaign->active, 1 ); ?> />
								<span class="o100-toggle-slider"></span>
							</label>
						</td>
						<td class="column-created">
							<?php echo date_i18n( 'F j, Y', $campaign->created_at ); ?><br/>
							<small>ID: <?php echo esc_html( $campaign->id ); ?></small>
						</td>
						<td class="column-actions">
							<a href="#" class="o100-action-btn o100-clone-campaign" data-id="<?php echo esc_attr( $campaign->id ); ?>" title="<?php esc_attr_e( 'Duplicate', 'order100' ); ?>"><span class="dashicons dashicons-admin-page"></span></a>
							<a href="#" class="o100-action-btn o100-edit-campaign" data-id="<?php echo esc_attr( $campaign->id ); ?>" title="<?php esc_attr_e( 'Edit', 'order100' ); ?>"><span class="dashicons dashicons-edit"></span></a>
							<a href="#" class="o100-action-btn o100-delete-campaign" data-id="<?php echo esc_attr( $campaign->id ); ?>" title="<?php esc_attr_e( 'Delete', 'order100' ); ?>"><span class="dashicons dashicons-trash"></span></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- ============================================== -->
	<!-- 2. CHOOSE CAMPAIGN TYPE VIEW -->
	<!-- ============================================== -->
	<div id="o100-campaigns-type-view" style="display: none;">
		<div class="o100-view-header o100-back-header">
			<a href="#" class="o100-btn-back-to-list"><span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'CHOOSE YOUR CAMPAIGN TYPE', 'order100' ); ?></a>
		</div>
		<p class="o100-page-description"><?php esc_html_e( 'You can reward customers for purchases, sign up, writing reviews, social sharing, referring their friends and more. Choose a type to get started. (You can create more than one reward campaign)', 'order100' ); ?></p>

		<div class="o100-campaign-type-grid">
			<?php foreach ( $campaign_types as $type ) : ?>
				<div class="o100-campaign-type-card">
					<div class="o100-type-icon"><span class="dashicons <?php echo esc_attr( $type['icon'] ); ?>"></span></div>
					<h3><?php echo esc_html( $type['title'] ); ?></h3>
					<p><?php echo esc_html( $type['desc'] ); ?></p>
					<button type="button" class="button button-secondary o100-btn-select-type" data-type="<?php echo esc_attr( $type['id'] ); ?>"><?php esc_html_e( 'Create Campaign', 'order100' ); ?></button>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- ============================================== -->
	<!-- 3. CAMPAIGN EDITOR VIEW -->
	<!-- ============================================== -->
	<div id="o100-campaigns-editor-view" style="display: none;">
		<div id="o100-campaign-form" class="o100-loyalty-settings">
			<input type="hidden" name="action" value="o100_save_campaign" />
			<input type="hidden" name="o100_nonce" value="<?php echo esc_attr( wp_create_nonce( 'o100-campaign-nonce' ) ); ?>" />
			<input type="hidden" name="id" id="o100-campaign-id" value="0" />
			<input type="hidden" name="action_type" id="o100-campaign-action-type" value="" />
			<input type="hidden" name="campaign_type" id="o100-campaign-reward-type" value="point" />
			
			<div class="o100-editor-header">
				<h2><?php esc_html_e( 'EDIT CAMPAIGN', 'order100' ); ?> [<span id="o100-editor-title-type"></span>]</h2>
				<div class="o100-editor-status">
					<span class="o100-status-label"><?php esc_html_e( 'Active', 'order100' ); ?></span>
					<label class="o100-toggle-switch">
						<input type="checkbox" name="active" id="o100-campaign-active" value="1" checked />
						<span class="o100-toggle-slider"></span>
					</label>
				</div>
				<div class="o100-editor-actions">
					<button type="button" class="button o100-btn-back-to-list"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Back', 'order100' ); ?></button>
					<button type="button" class="button button-primary o100-btn-save-campaign"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Save', 'order100' ); ?></button>
					<button type="button" class="button button-primary o100-btn-save-close-campaign"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save & Close', 'order100' ); ?></button>
				</div>
			</div>

			<div class="o100-editor-body">
				<!-- Left Column: Form Fields -->
				<div class="o100-editor-main">
					<div class="o100-form-row">
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Campaign name *', 'order100' ); ?></label>
							<input type="text" name="name" id="o100-campaign-name" class="regular-text" required />
						</div>
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Campaign image (Optional)', 'order100' ); ?></label>
							<div class="o100-image-upload-wrap">
								<input type="hidden" name="icon" class="o100-image-url" />
								<div class="o100-image-preview"></div>
								<button type="button" class="button o100-upload-image-btn"><span class="dashicons dashicons-format-image"></span> <?php esc_html_e( 'Choose an image', 'order100' ); ?></button>
								<p class="description"><?php esc_html_e( 'Upload an image for this Campaign.', 'order100' ); ?></p>
							</div>
						</div>
					</div>

					<div class="o100-form-group">
						<label><?php esc_html_e( 'Campaign description (Optional)', 'order100' ); ?></label>
						<textarea name="description" id="o100-campaign-description" rows="4" class="large-text"></textarea>
					</div>

					<!-- Dynamic Rule Container (Injected via JS based on type) -->
					<div id="o100-campaign-dynamic-rules"></div>

					<div class="o100-form-row o100-date-row">
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Campaign Start date (Optional)', 'order100' ); ?></label>
							<input type="date" name="start_at" class="regular-text" />
						</div>
						<div class="o100-form-group o100-end-date-group" style="display:none;">
							<label><?php esc_html_e( 'Campaign End date (Optional)', 'order100' ); ?></label>
							<input type="date" name="end_at" class="regular-text" />
						</div>
					</div>
					<label class="o100-checkbox-label" style="display:block; margin-bottom:15px; cursor:pointer;">
						<input type="checkbox" class="o100-include-end-date" /> <?php esc_html_e( 'Include end date', 'order100' ); ?>
					</label>

					<div class="o100-form-row">
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Campaign visibility on "Ways to earn" section (Optional)', 'order100' ); ?></label>
							<select name="is_show_way_to_earn">
								<option value="1" selected><?php esc_html_e( 'Show', 'order100' ); ?></option>
								<option value="0"><?php esc_html_e( 'Hide', 'order100' ); ?></option>
							</select>
						</div>
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Ordering (Optional)', 'order100' ); ?></label>
							<input type="number" name="ordering" class="regular-text" />
						</div>
					</div>
				</div>

				<!-- Right Column: Conditional Rules -->
				<div class="o100-editor-sidebar">
					<div class="o100-conditions-panel">
						<div class="o100-conditions-header">
							<h3><?php esc_html_e( 'CONDITIONAL RULES', 'order100' ); ?> <small>(Optional)</small></h3>
							<button type="button" class="button button-primary o100-add-condition-btn"><span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add Conditions', 'order100' ); ?></button>
						</div>
						
						<div class="o100-conditions-match-type">
							<strong><?php esc_html_e( 'Conditions', 'order100' ); ?></strong>
							<label><input type="radio" name="condition_relationship" value="and" checked /> <?php esc_html_e( 'Match All', 'order100' ); ?></label>
							<label><input type="radio" name="condition_relationship" value="or" /> <?php esc_html_e( 'Match Any', 'order100' ); ?></label>
						</div>

						<!-- Condition Blocks Wrapper -->
						<div class="o100-conditions-wrapper"></div>
					</div>
				</div>
			</div>
		</div>
	</div>

</div>

	<!-- ============================================== -->
	<!-- TEMPLATES FOR DYNAMIC FIELDS -->
	<!-- ============================================== -->
	<script type="text/template" id="tmpl-o100-rules-point_for_purchase">
		<div class="o100-dynamic-rule-block">
			<h3><?php esc_html_e( 'EXISTING CUSTOMER', 'order100' ); ?></h3>
			<div class="o100-form-row">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Choose the type of reward *', 'order100' ); ?></label>
					<select name="campaign_type" class="o100-campaign-reward-type-select">
						<option value="point"><?php esc_html_e( 'Points', 'order100' ); ?></option>
						<option value="coupon"><?php esc_html_e( 'Discount', 'order100' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'The reward can be either points or a discount reward.', 'order100' ); ?></p>
				</div>
				<div class="o100-form-group o100-coupon-reward-type-group" style="display:none;">
					<label><?php esc_html_e( 'Choose the coupon reward *', 'order100' ); ?></label>
					<select name="point_rule[earn_reward]" class="o100-select2" style="width:100%;">
						<option value=""><?php esc_html_e( 'Select Coupon', 'order100' ); ?></option>
						<?php if ( ! empty( $rewards_list ) ) : ?>
							<?php foreach ( $rewards_list as $reward ) : ?>
								<option value="<?php echo esc_attr( $reward->id ); ?>" <?php selected( ( isset( $point_rule['earn_reward'] ) ? $point_rule['earn_reward'] : '' ), $reward->id ); ?>><?php echo esc_html( $reward->name ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>
				<div class="o100-form-group o100-point-reward-type-group">
					<label><?php esc_html_e( 'Point Reward Type *', 'order100' ); ?></label>
					<select name="point_rule[earn_type]">
						<option value="fixed"><?php esc_html_e( 'Fixed Points', 'order100' ); ?></option>
						<option value="subtotal_percentage"><?php esc_html_e( 'Percentage of Cart Subtotal', 'order100' ); ?></option>
					</select>
				</div>
			</div>
			
			<div class="o100-form-row o100-point-reward-type-group">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Set points *', 'order100' ); ?></label>
					<input type="number" name="point_rule[earn_point]" class="regular-text" required value="10" />
					<p class="description"><?php esc_html_e( 'Points to be earned by this campaign.', 'order100' ); ?></p>
				</div>
				<div class="o100-form-group o100-point-reward-type-group">
					<label><?php esc_html_e( 'For every X amount spent *', 'order100' ); ?></label>
					<input type="number" name="point_rule[o100_point_earn_price]" class="regular-text" required value="1" />
					<p class="description"><?php esc_html_e( 'Set the amount to be spent to earn the above points.', 'order100' ); ?></p>
				</div>
			</div>

			<div class="o100-form-row o100-point-reward-type-group">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Minimum points', 'order100' ); ?></label>
					<input type="number" name="point_rule[minimum_point]" class="regular-text" value="0" />
					<p class="description"><?php esc_html_e( 'Minimum points a customer can earn for each order (0 for no limit).', 'order100' ); ?></p>
				</div>
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Maximum points', 'order100' ); ?></label>
					<input type="number" name="point_rule[maximum_point]" class="regular-text" value="0" />
					<p class="description"><?php esc_html_e( 'Maximum points a customer can earn for each order (0 for no limit).', 'order100' ); ?></p>
				</div>
			</div>

			<div class="o100-dynamic-rule-block">
				<h3><?php esc_html_e( 'PRODUCT PAGE MESSAGE', 'order100' ); ?></h3>
				<div class="o100-form-row">
					<div class="o100-form-group">
						<label><?php esc_html_e( 'Message for simple products', 'order100' ); ?></label>
						<textarea name="point_rule[single_product_message]" class="large-text"></textarea>
						<p class="description"><?php esc_html_e( 'Leave empty to use global default.', 'order100' ); ?></p>
					</div>
				</div>
				<div class="o100-form-row">
					<div class="o100-form-group">
						<label><?php esc_html_e( 'Message for variable products', 'order100' ); ?></label>
						<textarea name="point_rule[variable_product_message]" class="large-text"></textarea>
					</div>
				</div>
				<div class="o100-form-row">
					<div class="o100-form-group">
						<label><?php esc_html_e( 'Display on pages', 'order100' ); ?></label>
						<select name="point_rule[display_product_message_page]">
							<option value="all"><?php esc_html_e( 'All pages', 'order100' ); ?></option>
							<option value="single"><?php esc_html_e( 'Single product page', 'order100' ); ?></option>
							<option value="list"><?php esc_html_e( 'Product list page', 'order100' ); ?></option>
						</select>
					</div>
				</div>
				<div class="o100-form-row">
					<div class="o100-form-group">
						<label><?php esc_html_e( 'Background Color', 'order100' ); ?></label>
						<input type="color" name="point_rule[dm_bg_color]" value="#ffffff" />
					</div>
					<div class="o100-form-group">
						<label><?php esc_html_e( 'Text Color', 'order100' ); ?></label>
						<input type="color" name="point_rule[dm_text_color]" value="#000000" />
					</div>
				</div>
				<div class="o100-form-row">
					<div class="o100-form-group">
						<label><?php esc_html_e( 'Border Color', 'order100' ); ?></label>
						<input type="color" name="point_rule[dm_border_color]" value="#cccccc" />
					</div>
					<div class="o100-form-group">
						<label><?php esc_html_e( 'Message Icon (Image URL)', 'order100' ); ?></label>
						<input type="text" name="point_rule[dm_message_icon]" class="regular-text" />
					</div>
				</div>
			</div>
		</div>
	</script>

	<script type="text/template" id="tmpl-o100-rules-referral">
		<!-- ═══ ADVOCATE (Existing Customer) ═══ -->
		<div class="o100-dynamic-rule-block" data-role="advocate">
			<h3 style="color:#4F46E5; border-left:3px solid #4F46E5; padding-left:10px;"><?php esc_html_e( 'EXISTING CUSTOMER / ADVOCATE REWARD', 'order100' ); ?></h3>
			<div class="o100-form-row">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Choose the type of reward *', 'order100' ); ?></label>
					<select name="point_rule[advocate][campaign_type]" class="o100-campaign-reward-type-select">
						<option value="point"><?php esc_html_e( 'Points', 'order100' ); ?></option>
						<option value="coupon"><?php esc_html_e( 'Discount', 'order100' ); ?></option>
					</select>
				</div>
				<div class="o100-form-group o100-point-reward-type-group">
					<label><?php esc_html_e( 'Point Reward Type *', 'order100' ); ?></label>
					<select name="point_rule[advocate][earn_type]">
						<option value="fixed_point"><?php esc_html_e( 'Fixed Points', 'order100' ); ?></option>
					</select>
				</div>
			</div>
			<div class="o100-form-row o100-point-reward-type-group">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Set points *', 'order100' ); ?></label>
					<input type="number" name="point_rule[advocate][earn_point]" class="regular-text" required value="100" />
					<p class="description"><?php esc_html_e( 'Points the advocate earns when their referral completes a qualifying order.', 'order100' ); ?></p>
				</div>
			</div>
			<div class="o100-form-row o100-coupon-reward-type-group" style="display:none;">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Choose the coupon reward *', 'order100' ); ?></label>
					<select name="point_rule[advocate][earn_reward]" class="o100-referral-coupon-select" style="width:100%;">
						<option value=""><?php esc_html_e( '── Select Existing Coupon ──', 'order100' ); ?></option>
						<?php foreach ( $rewards_list as $reward ) : ?>
							<option value="<?php echo esc_attr( $reward->id ); ?>"><?php echo esc_html( $reward->name ); ?></option>
						<?php endforeach; ?>
						<option value="__custom__"><?php esc_html_e( '── Create Custom Coupon ──', 'order100' ); ?></option>
					</select>
				</div>
				<!-- Inline Custom Coupon Creator -->
				<div class="o100-custom-coupon-fields" style="display:none; background:#f8f9fa; padding:15px; border-radius:8px; margin-top:10px; border:1px dashed #c7d2fe;">
					<h4 style="margin-top:0; color:#4F46E5;"><?php esc_html_e( 'Create New Coupon', 'order100' ); ?></h4>
					<div class="o100-form-row">
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Discount Type', 'order100' ); ?></label>
							<select name="point_rule[advocate][custom_coupon_type]">
								<option value="fixed_cart"><?php esc_html_e( 'Fixed Cart Discount ($)', 'order100' ); ?></option>
								<option value="percent"><?php esc_html_e( 'Percentage Discount (%)', 'order100' ); ?></option>
							</select>
						</div>
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Discount Amount *', 'order100' ); ?></label>
							<input type="number" name="point_rule[advocate][custom_coupon_value]" class="regular-text" step="0.01" value="5" />
						</div>
					</div>
					<div class="o100-form-row">
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Expiry (days)', 'order100' ); ?></label>
							<input type="number" name="point_rule[advocate][custom_coupon_expiry]" class="regular-text" value="30" />
						</div>
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Usage Limit', 'order100' ); ?></label>
							<input type="number" name="point_rule[advocate][custom_coupon_limit]" class="regular-text" value="1" />
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ═══ FRIEND (New Customer / Referred Person) ═══ -->
		<div class="o100-dynamic-rule-block" data-role="friend">
			<h3 style="color:#10B981; border-left:3px solid #10B981; padding-left:10px;"><?php esc_html_e( 'NEW CUSTOMER / REFERRED PERSON / FRIEND REWARD', 'order100' ); ?></h3>
			<div class="o100-form-row">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Choose the type of reward *', 'order100' ); ?></label>
					<select name="point_rule[friend][campaign_type]" class="o100-campaign-reward-type-select">
						<option value="point"><?php esc_html_e( 'Points', 'order100' ); ?></option>
						<option value="coupon"><?php esc_html_e( 'Discount', 'order100' ); ?></option>
					</select>
				</div>
				<div class="o100-form-group o100-point-reward-type-group">
					<label><?php esc_html_e( 'Point Reward Type *', 'order100' ); ?></label>
					<select name="point_rule[friend][earn_type]">
						<option value="fixed_point"><?php esc_html_e( 'Fixed Points', 'order100' ); ?></option>
					</select>
				</div>
			</div>
			<div class="o100-form-row o100-point-reward-type-group">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Set points *', 'order100' ); ?></label>
					<input type="number" name="point_rule[friend][earn_point]" class="regular-text" required value="50" />
					<p class="description"><?php esc_html_e( 'Points the friend earns as a welcome bonus.', 'order100' ); ?></p>
				</div>
			</div>
			<div class="o100-form-row o100-coupon-reward-type-group" style="display:none;">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Choose the coupon reward *', 'order100' ); ?></label>
					<select name="point_rule[friend][earn_reward]" class="o100-referral-coupon-select" style="width:100%;">
						<option value=""><?php esc_html_e( '── Select Existing Coupon ──', 'order100' ); ?></option>
						<?php foreach ( $rewards_list as $reward ) : ?>
							<option value="<?php echo esc_attr( $reward->id ); ?>"><?php echo esc_html( $reward->name ); ?></option>
						<?php endforeach; ?>
						<option value="__custom__"><?php esc_html_e( '── Create Custom Coupon ──', 'order100' ); ?></option>
					</select>
				</div>
				<!-- Inline Custom Coupon Creator -->
				<div class="o100-custom-coupon-fields" style="display:none; background:#f0fdf4; padding:15px; border-radius:8px; margin-top:10px; border:1px dashed #86efac;">
					<h4 style="margin-top:0; color:#10B981;"><?php esc_html_e( 'Create New Coupon', 'order100' ); ?></h4>
					<div class="o100-form-row">
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Discount Type', 'order100' ); ?></label>
							<select name="point_rule[friend][custom_coupon_type]">
								<option value="fixed_cart"><?php esc_html_e( 'Fixed Cart Discount ($)', 'order100' ); ?></option>
								<option value="percent"><?php esc_html_e( 'Percentage Discount (%)', 'order100' ); ?></option>
							</select>
						</div>
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Discount Amount *', 'order100' ); ?></label>
							<input type="number" name="point_rule[friend][custom_coupon_value]" class="regular-text" step="0.01" value="5" />
						</div>
					</div>
					<div class="o100-form-row">
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Expiry (days)', 'order100' ); ?></label>
							<input type="number" name="point_rule[friend][custom_coupon_expiry]" class="regular-text" value="30" />
						</div>
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Usage Limit', 'order100' ); ?></label>
							<input type="number" name="point_rule[friend][custom_coupon_limit]" class="regular-text" value="1" />
						</div>
					</div>
				</div>
			</div>
		</div>
	</script>

	<!-- GENERIC ACTION TEMPLATES (Signup, Review, Shares, etc) -->
	<?php
	$action_templates = [
		'order_value'    => [
			[ 'field' => 'min_subtotal', 'label' => __( 'Minimum spend', 'order100' ), 'type' => 'number', 'default' => '0', 'desc' => __( 'How much a customer should spend to get this reward? (0 for no limit)', 'order100' ) ],
			[ 'field' => 'max_subtotal', 'label' => __( 'Maximum spend', 'order100' ), 'type' => 'number', 'default' => '0', 'desc' => __( 'The maximum amount a customer can spend. (0 for no limit)', 'order100' ) ]
		],
		'signup'         => [ 'field' => 'signup_message', 'label' => __( 'Sign Up Message *', 'order100' ), 'type' => 'textarea', 'default' => __( 'Thank you for signing up! Here are your reward points.', 'order100' )],
		'product_review' => [ 'field' => 'review_message', 'label' => __( 'Review Message *', 'order100' ), 'type' => 'textarea', 'default' => __( 'Thank you for your review! Here are your reward points.', 'order100' )],
		'birthday'       => [ 'field' => 'birthday_message', 'label' => __( 'Birthday Message *', 'order100' ), 'type' => 'textarea', 'default' => __( 'Happy Birthday! Here are your reward points.', 'order100' )],
		'facebook_share' => [ 'field' => 'share_message', 'label' => __( 'Share Message *', 'order100' ), 'type' => 'textarea', 'default' => __( 'Check out these amazing products!', 'order100' )],
		'twitter_share'  => [ 'field' => 'share_message', 'label' => __( 'Share Message *', 'order100' ), 'type' => 'textarea', 'default' => __( 'Check out these amazing products!', 'order100' )],
		'whatsapp_share' => [ 'field' => 'share_message', 'label' => __( 'Share Message *', 'order100' ), 'type' => 'textarea', 'default' => __( 'Check out these amazing products!', 'order100' )],
		'reddit_share'   => [ 'field' => 'share_message', 'label' => __( 'Share Message *', 'order100' ), 'type' => 'textarea', 'default' => __( 'Check out these amazing products!', 'order100' )],
		'email_share'    => [
			[ 'field' => 'share_subject', 'label' => __( 'Email Subject *', 'order100' ), 'type' => 'text', 'default' => __( 'Check out this store', 'order100' ) ],
			[ 'field' => 'share_body', 'label' => __( 'Email Body *', 'order100' ), 'type' => 'textarea', 'default' => __( 'I found this amazing store and thought you might like it!', 'order100' ) ]
		],
		'followup_share' => [ 'field' => 'share_url', 'label' => __( 'Page URL to Follow *', 'order100' ), 'type' => 'text', 'default' => 'https://facebook.com/yourpage' ],
		'achievement'    => [ 'field' => 'level_ids', 'label' => __( 'Target Level *', 'order100' ), 'type' => 'text', 'default' => '', 'desc' => __( 'Enter Level ID to reach', 'order100' ) ],
	];
	?>

	<?php foreach ( $action_templates as $type_id => $extra_fields ) : ?>
	<script type="text/template" id="tmpl-o100-rules-<?php echo esc_attr( $type_id ); ?>">
		<div class="o100-dynamic-rule-block">
			<div class="o100-form-row">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Choose the type of reward *', 'order100' ); ?></label>
					<select name="campaign_type" class="o100-campaign-reward-type-select">
						<option value="point"><?php esc_html_e( 'Points', 'order100' ); ?></option>
						<option value="coupon"><?php esc_html_e( 'Coupon reward', 'order100' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'The reward can be either points or a discount reward.', 'order100' ); ?></p>
				</div>
				<div class="o100-form-group o100-coupon-reward-type-group" style="display:none;">
					<label><?php esc_html_e( 'Choose the coupon reward *', 'order100' ); ?></label>
					<select name="point_rule[earn_reward]" class="o100-select2" style="width:100%;">
						<option value=""><?php esc_html_e( 'Select Coupon', 'order100' ); ?></option>
						<?php if ( ! empty( $rewards_list ) ) : ?>
							<?php foreach ( $rewards_list as $reward ) : ?>
								<option value="<?php echo esc_attr( $reward->id ); ?>"><?php echo esc_html( $reward->name ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>
			</div>
			
			<div class="o100-form-row o100-point-reward-type-group">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Set points *', 'order100' ); ?></label>
					<input type="number" name="point_rule[earn_point]" class="regular-text" required value="10" />
					<p class="description"><?php esc_html_e( 'Points to be earned by this campaign.', 'order100' ); ?></p>
				</div>
			</div>

			<?php
			if ( ! empty( $extra_fields ) ) {
				// Handle single vs multiple fields
				$fields_to_render = isset( $extra_fields['field'] ) ? [ $extra_fields ] : $extra_fields;
				foreach ( $fields_to_render as $field ) :
			?>
				<div class="o100-form-row">
					<div class="o100-form-group">
						<label><?php echo esc_html( $field['label'] ); ?></label>
						<?php if ( $field['type'] === 'textarea' ) : ?>
							<textarea name="point_rule[<?php echo esc_attr( $field['field'] ); ?>]" class="large-text" required><?php echo esc_html( $field['default'] ); ?></textarea>
						<?php else : ?>
							<input type="text" name="point_rule[<?php echo esc_attr( $field['field'] ); ?>]" class="regular-text" required value="<?php echo esc_attr( $field['default'] ); ?>" />
						<?php endif; ?>
						<?php if ( ! empty( $field['desc'] ) ) : ?>
							<p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php 
				endforeach; 
			}
			?>
		</div>
	</script>
	<?php endforeach; ?>

	<!-- BIRTHDAY TEMPLATE -->
	<script type="text/template" id="tmpl-o100-rules-birthday">
		<div class="o100-dynamic-rule-block">
			<div class="o100-form-row">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Choose the type of reward *', 'order100' ); ?></label>
					<select name="campaign_type" class="o100-campaign-reward-type-select">
						<option value="point"><?php esc_html_e( 'Points', 'order100' ); ?></option>
						<option value="coupon"><?php esc_html_e( 'Coupon reward', 'order100' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'The reward can be either points or a discount reward.', 'order100' ); ?></p>
				</div>
				<div class="o100-form-group o100-coupon-reward-type-group" style="display:none;">
					<label><?php esc_html_e( 'Choose the coupon reward *', 'order100' ); ?></label>
					<select name="point_rule[earn_reward]" class="o100-select2" style="width:100%;">
						<option value=""><?php esc_html_e( 'Select Coupon', 'order100' ); ?></option>
						<?php if ( ! empty( $rewards_list ) ) : ?>
							<?php foreach ( $rewards_list as $reward ) : ?>
								<option value="<?php echo esc_attr( $reward->id ); ?>" <?php selected( ( isset( $point_rule['earn_reward'] ) ? $point_rule['earn_reward'] : '' ), $reward->id ); ?>><?php echo esc_html( $reward->name ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>
			</div>
			
			<div class="o100-form-row o100-point-reward-type-group">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Set points *', 'order100' ); ?></label>
					<input type="number" name="point_rule[earn_point]" class="regular-text" required value="0" />
					<p class="description"><?php esc_html_e( 'Points to be earned by this campaign.', 'order100' ); ?></p>
				</div>
			</div>

			<div class="o100-form-row">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'When this reward be given ? *', 'order100' ); ?></label>
					<select name="point_rule[birthday_earn_type]" style="width:100%;">
						<option value="on_their_birthday"><?php esc_html_e( 'On their birthday', 'order100' ); ?></option>
						<option value="update_birth_date"><?php esc_html_e( 'When providing birthday date', 'order100' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Select the event to apply reward.', 'order100' ); ?></p>
				</div>
			</div>
		</div>
	</script>
