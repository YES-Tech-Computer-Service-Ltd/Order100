<?php
/**
 * Coupons (Rewards) management view
 *
 * @package Order100
 * @since   3.2.0
 */
defined( 'ABSPATH' ) || exit;

// Fetch all rewards/coupons natively via Promotions DB
$all_rewards = class_exists( 'O100_Promotions_DB' ) ? O100_Promotions_DB::query( array( 'source' => 'loyalty' ) ) : array();

<div class="o100-loyalty-coupons-wrap">
	<input type="hidden" id="o100-reward-nonce" value="<?php echo esc_attr( wp_create_nonce( 'o100-reward-nonce' ) ); ?>" />

	<!-- ============================================== -->
	<!-- 1. COUPONS LIST VIEW -->
	<!-- ============================================== -->
	<div id="o100-coupons-list-view">
		<div class="o100-section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
			<h2 style="margin:0; font-size:18px; font-weight:600; text-transform:uppercase;"><?php esc_html_e( 'COUPONS', 'order100' ); ?></h2>
			<div class="o100-header-actions" style="display: flex; gap: 10px;">
				<div class="o100-search-box">
					<input type="text" id="o100-coupon-search" placeholder="<?php esc_attr_e( 'Search coupons...', 'order100' ); ?>" class="regular-text" style="height:35px; margin:0;" />
				</div>
				<button type="button" class="button button-primary o100-btn-show-coupon-types">
					<span class="dashicons dashicons-plus" style="vertical-align: middle; margin-top: 3px;"></span>
					<?php esc_html_e( 'Create New Coupon', 'order100' ); ?>
				</button>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped o100-loyalty-table">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th>
					<th scope="col" class="manage-column column-title"><?php esc_html_e( 'TITLE / DESCRIPTION', 'order100' ); ?></th>
					<th scope="col" class="manage-column column-type" style="width: 200px;"><?php esc_html_e( 'COUPON TYPE', 'order100' ); ?></th>
					<th scope="col" class="manage-column column-used" style="width: 150px;"><?php esc_html_e( 'USED IN CAMPAIGNS', 'order100' ); ?></th>
					<th scope="col" class="manage-column column-status" style="width: 120px;"><?php esc_html_e( 'ENABLE / DISABLE', 'order100' ); ?></th>
					<th scope="col" class="manage-column column-date" style="width: 150px;"><?php esc_html_e( 'CREATED ON', 'order100' ); ?></th>
					<th scope="col" class="manage-column column-actions" style="width: 120px; text-align: right;"><?php esc_html_e( 'ACTIONS', 'order100' ); ?></th>
				</tr>
			</thead>
			<tbody id="o100-coupons-table-body">
				<?php if ( ! empty( $all_rewards ) ) : ?>
					<?php foreach ( $all_rewards as $reward ) : 
						$type_label = '';
						switch($reward->discount_type) {
							case 'points_conversion': $type_label = __('Points Conversion', 'order100'); break;
							case 'fixed_cart': $type_label = __('Fixed Discount', 'order100'); break;
							case 'percent': $type_label = __('Percentage Discount', 'order100'); break;
							case 'free_product': $type_label = __('Free Product', 'order100'); break;
							case 'free_shipping': $type_label = __('Free Shipping', 'order100'); break;
							default: $type_label = $reward->discount_type;
						}
					?>
						<tr data-id="<?php echo esc_attr( $reward['id'] ); ?>">
							<th scope="row" class="check-column"><input type="checkbox" name="coupon[]" value="<?php echo esc_attr( $reward['id'] ); ?>" /></th>
							<td class="column-title">
								<div style="display: flex; align-items: center; gap: 10px;">
									<div style="width: 32px; height: 32px; background: #f3f4f6; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
										<span class="dashicons dashicons-awards" style="color: #9ca3af;"></span>
									</div>
									<div>
										<strong><?php echo esc_html( $reward['title'] ); ?></strong>
										<div class="row-actions" style="visibility: visible; position: static; margin: 0;">
											<span style="font-size: 11px; color: #6b7280;"><?php echo esc_html( $reward['description'] ); ?></span>
										</div>
									</div>
								</div>
							</td>
							<td class="column-type">
								<span class="o100-badge o100-badge-point"><?php echo esc_html( 'point' ); ?></span>
								<span style="font-size: 13px; margin-left: 5px;"><?php echo esc_html( $reward['rule_type'] ); ?></span>
							</td>
							<td class="column-used">-</td>
							<td class="column-status">
								<label class="o100-toggle-switch">
									<input type="checkbox" class="o100-toggle-coupon-status" data-id="<?php echo esc_attr( $reward['id'] ); ?>" <?php checked( $reward['status'], 'active' ); ?> />
									<span class="o100-toggle-slider"></span>
								</label>
							</td>
							<td class="column-date">
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $reward['created_at'] ) ) ); ?>
								<div style="font-size: 11px; color: #9ca3af;">ID: <?php echo esc_attr( $reward['id'] ); ?></div>
							</td>
							<td class="column-actions" style="text-align: right;">
								<div style="display: flex; justify-content: flex-end; gap: 5px;">
									<button type="button" class="button o100-btn-edit-coupon" data-id="<?php echo esc_attr( $reward['id'] ); ?>"><span class="dashicons dashicons-edit"></span></button>
									<button type="button" class="button o100-btn-delete-coupon" data-id="<?php echo esc_attr( $reward['id'] ); ?>"><span class="dashicons dashicons-trash"></span></button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="7" style="text-align:center; padding: 50px 0;">
							<div class="o100-empty-state">
								<span class="dashicons dashicons-awards" style="font-size: 48px; width: 48px; height: 48px; color: #e5e7eb; margin-bottom: 10px;"></span>
								<p><?php esc_html_e( 'No coupons found. Create your first coupon to reward your customers!', 'order100' ); ?></p>
								<button type="button" class="button button-primary o100-btn-show-coupon-types"><?php esc_html_e( 'Create New Coupon', 'order100' ); ?></button>
							</div>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- ============================================== -->
	<!-- 2. COUPON TYPE SELECTION -->
	<!-- ============================================== -->
	<div id="o100-coupon-types-view" style="display: none;">
		<div class="o100-section-header" style="margin-bottom: 30px;">
			<button type="button" class="button o100-btn-back-to-list"><span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'Back to list', 'order100' ); ?></button>
			<h2 style="margin:20px 0 10px 0; font-size:22px; font-weight:700;"><?php esc_html_e( 'CHOOSE COUPON TYPE', 'order100' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Choose the type of coupon/reward you want to create.', 'order100' ); ?></p>
		</div>

		<div class="o100-type-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
			<!-- Points Conversion -->
			<div class="o100-type-card o100-create-coupon-type" data-type="points_conversion">
				<div class="o100-type-icon"><span class="dashicons dashicons-randomize"></span></div>
				<h3><?php esc_html_e( 'Points Conversion', 'order100' ); ?></h3>
				<p><?php esc_html_e( 'Convert points to $ or % discount dynamically.', 'order100' ); ?></p>
				<button type="button" class="button"><?php esc_html_e( 'Create Coupon', 'order100' ); ?></button>
			</div>

			<!-- Fixed Discount -->
			<div class="o100-type-card o100-create-coupon-type" data-type="fixed_cart">
				<div class="o100-type-icon"><span class="dashicons dashicons-money-alt"></span></div>
				<h3><?php esc_html_e( 'Fixed Discount', 'order100' ); ?></h3>
				<p><?php esc_html_e( 'Reward with a fixed currency amount discount.', 'order100' ); ?></p>
				<button type="button" class="button"><?php esc_html_e( 'Create Coupon', 'order100' ); ?></button>
			</div>

			<!-- Percentage Discount -->
			<div class="o100-type-card o100-create-coupon-type" data-type="percent">
				<div class="o100-type-icon"><span class="dashicons dashicons-cart"></span></div>
				<h3><?php esc_html_e( 'Percentage Discount', 'order100' ); ?></h3>
				<p><?php esc_html_e( 'Reward with a percentage-based discount.', 'order100' ); ?></p>
				<button type="button" class="button"><?php esc_html_e( 'Create Coupon', 'order100' ); ?></button>
			</div>

			<!-- Free Product -->
			<div class="o100-type-card o100-create-coupon-type" data-type="free_product">
				<div class="o100-type-icon"><span class="dashicons dashicons-products"></span></div>
				<h3><?php esc_html_e( 'Free Product', 'order100' ); ?></h3>
				<p><?php esc_html_e( 'Provide a specific product as a gift.', 'order100' ); ?></p>
				<button type="button" class="button"><?php esc_html_e( 'Create Coupon', 'order100' ); ?></button>
			</div>

			<!-- Free Shipping -->
			<div class="o100-type-card o100-create-coupon-type" data-type="free_shipping">
				<div class="o100-type-icon"><span class="dashicons dashicons-location"></span></div>
				<h3><?php esc_html_e( 'Free Shipping', 'order100' ); ?></h3>
				<p><?php esc_html_e( 'Customer gets Free Shipping as a reward.', 'order100' ); ?></p>
				<button type="button" class="button"><?php esc_html_e( 'Create Coupon', 'order100' ); ?></button>
			</div>
		</div>
	</div>

	<!-- ============================================== -->
	<!-- 3. COUPON EDITOR VIEW -->
	<!-- ============================================== -->
	<div id="o100-coupon-editor-view" style="display: none;">
		<div id="o100-coupon-form" class="o100-loyalty-settings">
			<input type="hidden" name="action" value="o100_loyalty_save_reward" />
			<input type="hidden" name="o100_loyalty_nonce" value="<?php echo esc_attr( wp_create_nonce( 'o100-edit-reward-nonce' ) ); ?>" />
			<input type="hidden" name="id" id="o100-coupon-id" value="0" />
			<input type="hidden" name="discount_type" id="o100-coupon-discount-type" value="" />

			<div class="o100-editor-header">
				<h2><?php esc_html_e( 'EDIT REWARD', 'order100' ); ?> [<span id="o100-coupon-title-type"></span>]</h2>
				<div class="o100-editor-status">
					<span class="o100-status-label"><?php esc_html_e( 'Active', 'order100' ); ?></span>
					<label class="o100-toggle-switch">
						<input type="checkbox" name="active" id="o100-coupon-active" value="1" checked />
						<span class="o100-toggle-slider"></span>
					</label>
				</div>
				<div class="o100-editor-actions">
					<button type="button" class="button o100-btn-back-to-list"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Back', 'order100' ); ?></button>
					<button type="button" class="button button-primary o100-btn-save-coupon"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Save', 'order100' ); ?></button>
					<button type="button" class="button button-primary o100-btn-save-close-coupon"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save & Close', 'order100' ); ?></button>
				</div>
			</div>

			<div class="o100-editor-body">
				<!-- Left Column: Form Fields -->
				<div class="o100-editor-main">
					<div class="o100-form-row">
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Reward Title *', 'order100' ); ?></label>
							<input type="text" name="name" id="o100-coupon-name" class="regular-text" required />
							<p class="description"><?php esc_html_e( 'Give a name to your Reward', 'order100' ); ?></p>
						</div>
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Reward image (Optional)', 'order100' ); ?></label>
							<div class="o100-image-upload-wrap">
								<input type="hidden" name="icon" class="o100-image-url" />
								<div class="o100-image-preview"></div>
								<button type="button" class="button o100-upload-image-btn"><span class="dashicons dashicons-format-image"></span> <?php esc_html_e( 'Choose an image', 'order100' ); ?></button>
							</div>
							<p class="description"><?php esc_html_e( 'Upload an image for this Reward.', 'order100' ); ?></p>
						</div>
					</div>

					<div class="o100-form-group">
						<label><?php esc_html_e( 'Reward description (Optional)', 'order100' ); ?></label>
						<textarea name="description" id="o100-coupon-description" rows="3" class="large-text"></textarea>
						<p class="description"><?php esc_html_e( 'Give a description to your Reward', 'order100' ); ?></p>
					</div>

					<div class="o100-form-group o100-global-reward-type" style="margin-bottom: 20px;">
						<label><?php esc_html_e( 'Choose how this reward should be used? *', 'order100' ); ?></label>
						<div class="o100-reward-use-options" style="margin-top: 10px;">
							<label style="display: block; margin-bottom: 15px; cursor: pointer;">
								<input type="radio" name="reward_type" class="o100-reward-type-radio" value="redeem_point" checked />
								<span style="font-weight: 600; font-size: 14px;"><?php esc_html_e( 'Reward for Points', 'order100' ); ?></span>
								<p class="description" style="margin: 5px 0 0 25px;"><?php esc_html_e( 'This reward will be provided for redeeming their points', 'order100' ); ?></p>
							</label>
							<label style="display: block; cursor: pointer;">
								<input type="radio" name="reward_type" class="o100-reward-type-radio" value="redeem_coupon" />
								<span style="font-weight: 600; font-size: 14px;"><?php esc_html_e( 'Reward as a coupon immediately after completing a campaign.', 'order100' ); ?></span>
								<p class="description" style="margin: 5px 0 0 25px;"><?php esc_html_e( 'Reward as a coupon code immediately after completing a campaign.', 'order100' ); ?></p>
							</label>
						</div>
					</div>

					<!-- Dynamic Fields for specific types -->
					<div id="o100-coupon-dynamic-fields"></div>

					<div class="o100-form-row">
						<div class="o100-form-group" style="flex:1;">
							<label><?php esc_html_e( 'Display name for the coupon (when redeeming) *', 'order100' ); ?></label>
							<input type="text" name="display_name" id="o100-coupon-display-name" class="regular-text" required />
							<p class="description"><?php esc_html_e( 'What would be the name to show for the discount when customer redeems', 'order100' ); ?></p>
						</div>
						<div class="o100-form-group" style="flex:1;">
							<label><?php esc_html_e( 'Ordering (Optional)', 'order100' ); ?></label>
							<input type="number" name="ordering" id="o100-coupon-ordering" class="regular-text" value="0" />
							<p class="description"><?php esc_html_e( 'Visible order for reward list.', 'order100' ); ?></p>
						</div>
					</div>

					<div class="o100-form-group" style="margin-bottom: 20px;">
						<label><?php esc_html_e( 'Coupon Expiry (Optional)', 'order100' ); ?></label>
						<div style="display:flex; gap:10px; align-items: center;">
							<select id="o100-coupon-expiry-type" style="width: 150px;">
								<option value="unlimited"><?php esc_html_e( 'Unlimited', 'order100' ); ?></option>
								<option value="limited"><?php esc_html_e( 'Limited', 'order100' ); ?></option>
							</select>
							<div class="o100-expiry-value-wrap" style="display:none; flex:1; gap:10px; align-items: center;">
								<input type="number" name="expire_after" id="o100-coupon-expire-after" style="width:80px;" value="0" min="0" />
								<select name="expire_period" id="o100-coupon-expire-period" style="width: 120px;">
									<option value="day"><?php esc_html_e( 'Day(s)', 'order100' ); ?></option>
									<option value="week"><?php esc_html_e( 'Week(s)', 'order100' ); ?></option>
									<option value="month"><?php esc_html_e( 'Month(s)', 'order100' ); ?></option>
									<option value="year"><?php esc_html_e( 'Year(s)', 'order100' ); ?></option>
								</select>
							</div>
						</div>
						<p class="description"><?php esc_html_e( 'Coupon Expiry', 'order100' ); ?></p>
					</div>

					<div class="o100-form-group" style="margin-bottom: 20px;">
						<label><?php esc_html_e( 'Reward visibility on "Reward Opportunities" section', 'order100' ); ?></label>
						<select name="is_show_reward" id="o100-coupon-is-show-reward" style="width: 100%;">
							<option value="1"><?php esc_html_e( 'Show', 'order100' ); ?></option>
							<option value="0"><?php esc_html_e( 'Hide', 'order100' ); ?></option>
						</select>
					</div>

					<div class="o100-form-row">
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Would you like to send an expiry email?', 'order100' ); ?></label>
							<select name="enable_expiry_email" id="o100-coupon-enable-expiry-email" style="width: 100%;">
								<option value="0"><?php esc_html_e( 'No', 'order100' ); ?></option>
								<option value="1"><?php esc_html_e( 'Yes', 'order100' ); ?></option>
							</select>
						</div>
						<div class="o100-form-group o100-expiry-email-days-group" style="display:none; flex:1;">
							<label><?php esc_html_e( 'Wait Period', 'order100' ); ?></label>
							<input type="number" name="expire_email" id="o100-coupon-expire-email" class="regular-text" style="width: 100%;" value="0" min="0" />
							<p class="description"><?php esc_html_e( 'Set how many days to wait before sending the expiry notification email', 'order100' ); ?></p>
						</div>
					</div>

					<div class="o100-form-group" style="margin-top: 20px;">
						<label><?php esc_html_e( 'Redeem Count (Optional)', 'order100' ); ?></label>
						<input type="number" name="usage_limits" id="o100-coupon-usage-limits" class="regular-text" style="width: 100%;" value="0" min="0" />
						<p class="description"><?php esc_html_e( 'Useful if you want to limit the number of times a customer can redeem this reward. NOTE: Only applicable for "Points" based redeems. Set this to 0 for unlimited redeems. Default is 0', 'order100' ); ?></p>
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

						<div class="o100-conditions-wrapper"></div>
					</div>
				</div>
			</div>
		</div>
	</div>

</div>

<!-- Templates for Dynamic Coupon Fields -->
<script type="text/html" id="tmpl-o100-coupon-points_conversion">
	<!-- Conversion Rate Card -->
	<div class="o100-conversion-rate-card" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
		<label style="display: block; font-weight: 600; margin-bottom: 15px; color: #1e293b;"><?php esc_html_e( 'Conversion Rate *', 'order100' ); ?></label>
		<div style="display: flex; align-items: center; gap: 15px;">
			<div style="flex: 1;">
				<input type="number" name="require_point" class="regular-text o100-conversion-points" value="0" min="1" required style="width: 100%;" />
			</div>
			<div style="font-weight: 700; color: #64748b;"><?php esc_html_e( 'points =', 'order100' ); ?></div>
			<div style="flex: 1;">
				<input type="number" name="discount_value" class="regular-text o100-conversion-value" step="0.01" value="0" required style="width: 100%;" />
			</div>
			<div style="flex: 1;">
				<select name="coupon_type" class="o100-conversion-type" style="width: 100%;">
					<option value="fixed_cart"><?php esc_html_e( 'Fixed Discount ($)', 'order100' ); ?></option>
					<option value="percent"><?php esc_html_e( 'Percentage Discount (%)', 'order100' ); ?></option>
				</select>
			</div>
		</div>
		<p class="description o100-conversion-rate-desc" style="margin-top: 15px; font-style: italic; color: #64748b; font-size: 12px; min-height: 48px;">
			<?php esc_html_e( 'WFA will automatically calculate the value of each point using the following formula: value of the discount / number of points required = value of each point.', 'order100' ); ?>
		</p>
	</div>

	<!-- Percentage Caps (Conditional) -->
	<div class="o100-form-row o100-conversion-caps-percentage" style="display:none; margin-bottom: 20px;">
		<div class="o100-form-group">
			<label><?php esc_html_e( 'Maximum percentage allowed *', 'order100' ); ?></label>
			<input type="number" name="max_percentage" class="regular-text" value="0" min="0" max="100" />
			<p class="description"><?php esc_html_e( 'It should be less than 50 percentage.', 'order100' ); ?></p>
		</div>
		<div class="o100-form-group">
			<label><?php esc_html_e( 'Maximum reward amount per redemption. *', 'order100' ); ?></label>
			<input type="number" name="max_discount" class="regular-text" value="0" min="0" />
			<p class="description"><?php esc_html_e( 'Maximum reward amount allowed per redemption.', 'order100' ); ?></p>
		</div>
	</div>

	<!-- Fixed Caps (Optional) -->
	<div class="o100-form-row o100-conversion-caps-fixed" style="margin-bottom: 20px;">
		<div class="o100-form-group">
			<label><?php esc_html_e( 'Maximum reward amount per redemption. (Optional)', 'order100' ); ?></label>
			<input type="number" name="max_discount_fixed" class="regular-text o100-field-max-discount-fixed" value="0" min="0" />
			<p class="description"><?php esc_html_e( 'Maximum reward amount allowed per redemption.', 'order100' ); ?></p>
		</div>
		<div class="o100-form-group" style="visibility:hidden;">
			<label>-</label>
			<input type="text" disabled style="width:100%"/>
		</div>
	</div>

	<div class="o100-form-row" style="margin-bottom: 20px;">
		<div class="o100-form-group">
			<label><?php esc_html_e( 'Minimum points a customer can redeem per coupon (Optional)', 'order100' ); ?></label>
			<input type="number" name="minimum_point" class="regular-text" value="0" min="0" />
			<p class="description"><?php esc_html_e( 'You can set a minimum number of points to be redeemed per coupon.', 'order100' ); ?></p>
		</div>
		<div class="o100-form-group">
			<label><?php esc_html_e( 'Maximum points a customer can redeem per coupon (Optional)', 'order100' ); ?></label>
			<input type="number" name="maximum_point" class="regular-text" value="0" min="0" />
			<p class="description"><?php esc_html_e( 'You can set a maximum number of points to be redeemed per coupon.', 'order100' ); ?></p>
		</div>
	</div>
</script>

<script type="text/html" id="tmpl-o100-coupon-fixed_cart">
	<div class="o100-form-row" style="margin-bottom: 20px;">
		<div class="o100-form-group">
			<label><?php esc_html_e( 'Discount Value ($) *', 'order100' ); ?></label>
			<input type="number" name="discount_value" class="regular-text" value="0" required />
			<p class="description"><?php esc_html_e( 'Set the discount amount.', 'order100' ); ?></p>
		</div>
		<div class="o100-form-group o100-require-point-wrapper">
			<label><?php esc_html_e( 'Set points *', 'order100' ); ?></label>
			<input type="number" name="require_point" class="regular-text" value="0" min="0" />
			<p class="description"><?php esc_html_e( 'Enter value of points to be used to redeem this reward', 'order100' ); ?></p>
		</div>
	</div>
</script>

<script type="text/html" id="tmpl-o100-coupon-percent">
	<div class="o100-form-row" style="margin-bottom: 20px;">
		<div class="o100-form-group">
			<label><?php esc_html_e( 'Discount Value (%) *', 'order100' ); ?></label>
			<input type="number" name="discount_value" class="regular-text" value="0" required />
			<p class="description"><?php esc_html_e( 'Enter the value of Percentage discount to be earned', 'order100' ); ?></p>
		</div>
		<div class="o100-form-group o100-require-point-wrapper">
			<label><?php esc_html_e( 'Set points *', 'order100' ); ?></label>
			<input type="number" name="require_point" class="regular-text" value="0" min="0" />
			<p class="description"><?php esc_html_e( 'Enter value of points to be used to redeem this reward', 'order100' ); ?></p>
		</div>
	</div>
</script>

<script type="text/html" id="tmpl-o100-coupon-free_product">
	<div class="o100-form-row" style="margin-bottom: 20px;">
		<div class="o100-form-group" style="flex:1;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
				<label style="margin: 0;"><?php esc_html_e( 'Select Products *', 'order100' ); ?></label>
				<button type="button" class="button button-small o100-btn-add-free-product-row"><?php esc_html_e( 'Add Product', 'order100' ); ?></button>
			</div>
			<div class="o100-free-products-rows-container">
				<div class="o100-free-product-row" style="display: flex; gap: 10px; margin-bottom: 5px;">
					<div style="flex: 1;">
						<select name="free_product[]" class="o100-select-product" style="width:100%;">
							<option value=""><?php esc_html_e( 'Select a product', 'order100' ); ?></option>
						</select>
					</div>
					<button type="button" class="button o100-btn-remove-free-product-row" style="display: none;"><span class="dashicons dashicons-no-alt"></span></button>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'select product(s) you want to give as reward.', 'order100' ); ?></p>
		</div>
		<div class="o100-form-group o100-require-point-wrapper" style="flex:1;">
			<label><?php esc_html_e( 'Set points *', 'order100' ); ?></label>
			<input type="number" name="require_point" class="regular-text" value="0" min="0" />
			<p class="description"><?php esc_html_e( 'Enter value of points to be used to redeem this reward', 'order100' ); ?></p>
		</div>
	</div>
</script>

<script type="text/html" id="tmpl-o100-coupon-free_shipping">
	<div class="o100-form-row" style="margin-bottom: 20px;">
		<div class="o100-form-group">
			<label><?php esc_html_e( 'Free Shipping Description', 'order100' ); ?></label>
			<p class="description"><?php esc_html_e( 'Customer will get free shipping on their order when using this coupon.', 'order100' ); ?></p>
		</div>
		<div class="o100-form-group o100-require-point-wrapper">
			<label><?php esc_html_e( 'Set points *', 'order100' ); ?></label>
			<input type="number" name="require_point" class="regular-text" value="0" min="0" />
			<p class="description"><?php esc_html_e( 'Enter value of points to be used to redeem this reward', 'order100' ); ?></p>
		</div>
	</div>
</script>

// TS: 20260317162141
