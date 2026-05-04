<?php
/**
 * Loyalty Levels View
 *
 * @package Order100
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$levels = class_exists( 'O100_Loyalty_DB' ) ? O100_Loyalty_DB::get_levels() : array();

// Get Global Settings
$wllp_settings = get_option( 'o100_loyalty_settings', array() );
$levels_based_on = isset( $wllp_settings['levels_from_which_point_based'] ) ? $wllp_settings['levels_from_which_point_based'] : 'from_current_balance';
$grace_enabled = isset( $wllp_settings['grace_period_enabled'] ) ? $wllp_settings['grace_period_enabled'] : 0;
$grace_days = isset( $wllp_settings['grace_period_days'] ) ? $wllp_settings['grace_period_days'] : 30;

$based_on_options = array(
	'from_current_balance'     => __( 'Points Balance', 'order100' ),
	'from_total_earned_points' => __( 'Earned Points', 'order100' ),
	'from_points_redeemed'     => __( 'Redeemed Points', 'order100' ),
);

?>
<div class="o100-levels-container">

	<!-- ============================================== -->
	<!-- 1. LEVEL OPTIONS (GLOBAL SETTINGS) -->
	<!-- ============================================== -->
	<div class="o100-setting-card o100-levels-options-card">
		<div class="cmb-type-title">
			<h5><?php esc_html_e( 'LEVEL OPTIONS', 'order100' ); ?></h5>
			<p class="cmb2-metabox-description"><?php esc_html_e( 'Configure how levels are calculated and managed globally.', 'order100' ); ?></p>
		</div>

		<div class="o100-form-body" id="o100-level-settings-form">
			<div class="o100-form-row">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Levels should be based on', 'order100' ); ?></label>
					<select name="levels_from_which_point_based" id="o100-levels-based-on" class="o100-select-full">
						<?php foreach ( $based_on_options as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $levels_based_on, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Choose the criteria for moving users between different levels.', 'order100' ); ?></p>
				</div>
			</div>

			<div class="o100-form-row o100-grace-period-section">
				<div class="o100-form-group o100-toggle-group">
					<div class="o100-label-desc">
						<label><?php esc_html_e( 'Enable grace period after level downgrade?', 'order100' ); ?></label>
						<p class="description"><?php esc_html_e( 'If enabled, customers will keep their current level for a specified number of days before being downgraded.', 'order100' ); ?></p>
					</div>
					<div class="o100-toggle-wrap">
						<label class="o100-toggle-switch">
							<input type="checkbox" name="grace_period_enabled" id="o100-grace-enabled" value="1" <?php checked( $grace_enabled, 1 ); ?> />
							<span class="o100-toggle-slider"></span>
						</label>
					</div>
				</div>
			</div>

			<div class="o100-form-row o100-grace-days-row" style="<?php echo $grace_enabled ? '' : 'display:none;'; ?>">
				<div class="o100-form-group">
					<label><?php esc_html_e( 'Grace period days', 'order100' ); ?></label>
					<div class="o100-inline-input">
						<input type="number" name="grace_period_days" id="o100-grace-period-days" value="<?php echo esc_attr( $grace_days ); ?>" min="1" max="365" />
						<span class="o100-input-suffix"><?php esc_html_e( 'days', 'order100' ); ?></span>
					</div>
				</div>
			</div>

			<div class="o100-form-actions">
				<button type="button" class="button button-primary o100-btn-save-level-settings">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save Global Settings', 'order100' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- ============================================== -->
	<!-- 2. LEVELS LIST VIEW -->
	<!-- ============================================== -->
	<div id="o100-levels-list-view">
		<div class="o100-view-header">
			<h2><?php esc_html_e( 'LEVELS', 'order100' ); ?></h2>
			<div class="o100-view-actions">
				<button type="button" class="button button-primary o100-btn-create-level">
					<span class="dashicons dashicons-plus"></span>
					<?php esc_html_e( 'Add New Level', 'order100' ); ?>
				</button>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped o100-levels-table">
			<thead>
			<tr>
				<th scope="col" class="column-badge"><?php esc_html_e( 'BADGE', 'order100' ); ?></th>
				<th scope="col" class="column-title"><?php esc_html_e( 'LEVEL NAME', 'order100' ); ?></th>
				<th scope="col" class="column-points"><?php esc_html_e( 'POINTS RANGE', 'order100' ); ?></th>
				<th scope="col" class="column-status"><?php esc_html_e( 'STATUS', 'order100' ); ?></th>
				<th scope="col" class="column-actions"><?php esc_html_e( 'ACTIONS', 'order100' ); ?></th>
			</tr>
			</thead>
			<tbody id="o100-levels-tbody">
			<?php if ( empty( $levels ) ) : ?>
				<tr class="o100-no-data">
					<td colspan="5"><?php esc_html_e( 'No levels found. Please add a level to get started.', 'order100' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $levels as $level ) : ?>
					<tr data-id="<?php echo esc_attr( $level->id ); ?>">
						<td class="column-badge">
							<?php if ( ! empty( $level->icon ) ) : ?>
								<img src="<?php echo esc_url( $level->icon ); ?>" class="o100-level-badge-preview" />
							<?php else : ?>
								<span class="dashicons dashicons-awards o100-placeholder-badge"></span>
							<?php endif; ?>
						</td>
						<td class="column-title">
							<strong><?php echo esc_html( $level->name ); ?></strong>
							<?php if ( ! empty( $level->perks ) ) : ?>
								<br/><small class="description"><?php echo esc_html( $level->perks ); ?></small>
							<?php endif; ?>
						</td>
						<td class="column-points">
							<?php 
								$from = (int) $level->min_points;
								$max = (int) $level->max_points;
								if ( $max > 0 ) {
									echo sprintf( esc_html__( '%1$d - %2$d Points', 'order100' ), $from, $max );
								} else {
									echo sprintf( esc_html__( 'From %d Points', 'order100' ), $from );
								}
							?>
						</td>
						<td class="column-status">
							<span class="o100-badge o100-badge-active"><?php esc_html_e( 'Active', 'order100' ); ?></span>
						</td>
						<td class="column-actions">
							<a href="#" class="o100-action-btn o100-edit-level" data-id="<?php echo esc_attr( $level->id ); ?>" title="<?php esc_attr_e( 'Edit', 'order100' ); ?>"><span class="dashicons dashicons-edit"></span></a>
							<a href="#" class="o100-action-btn o100-delete-level" data-id="<?php echo esc_attr( $level->id ); ?>" title="<?php esc_attr_e( 'Delete', 'order100' ); ?>"><span class="dashicons dashicons-trash"></span></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- ============================================== -->
	<!-- 3. LEVEL EDITOR VIEW -->
	<!-- ============================================== -->
	<div id="o100-level-editor-view" style="display: none;">
		<div id="o100-level-form" class="o100-loyalty-settings">
			<input type="hidden" name="action" value="o100_loyalty_save_level" />
			<input type="hidden" name="o100_loyalty_nonce" value="<?php echo esc_attr( wp_create_nonce( 'o100_loyalty_nonce' ) ); ?>" />
			<input type="hidden" name="id" id="o100-level-id" value="0" />
			
			<div class="o100-editor-header">
				<h2><?php esc_html_e( 'EDIT LEVEL', 'order100' ); ?></h2>
				<div class="o100-editor-status">
					<span class="o100-status-label"><?php esc_html_e( 'Active', 'order100' ); ?></span>
					<label class="o100-toggle-switch">
						<input type="checkbox" name="active" id="o100-level-active" value="1" checked />
						<span class="o100-toggle-slider"></span>
					</label>
				</div>
				<div class="o100-editor-actions">
					<button type="button" class="button o100-btn-back-to-levels"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Cancel', 'order100' ); ?></button>
					<button type="button" class="button button-primary o100-btn-save-level"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Save Level', 'order100' ); ?></button>
				</div>
			</div>

			<div class="o100-editor-body">
				<div class="o100-editor-main">
					<div class="o100-form-row">
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Level name *', 'order100' ); ?></label>
							<input type="text" name="name" id="o100-level-name" class="regular-text" required />
						</div>
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Level Badge (Optional)', 'order100' ); ?></label>
							<div class="o100-image-upload-wrap">
								<input type="hidden" name="badge" class="o100-image-url" id="o100-level-badge" />
								<div class="o100-image-preview"></div>
								<button type="button" class="button o100-upload-image-btn"><span class="dashicons dashicons-format-image"></span> <?php esc_html_e( 'Choose Badge', 'order100' ); ?></button>
								<p class="description"><?php esc_html_e( 'Upload an icon or badge for this level.', 'order100' ); ?></p>
							</div>
						</div>
					</div>

					<div class="o100-form-group">
						<label><?php esc_html_e( 'Level perks (Optional)', 'order100' ); ?></label>
						<textarea name="perks" id="o100-level-perks" rows="3" class="large-text"></textarea>
					</div>

					<div class="o100-form-row">
						<div class="o100-form-group">
							<label><?php esc_html_e( 'From points *', 'order100' ); ?></label>
							<input type="number" name="min_points" id="o100-level-min-points" class="regular-text" required value="0" />
							<p class="description"><?php esc_html_e( 'Minimum points required to reach this level.', 'order100' ); ?></p>
						</div>
						<div class="o100-form-group">
							<label><?php esc_html_e( 'To points (Optional)', 'order100' ); ?></label>
							<input type="number" name="max_points" id="o100-level-max-points" class="regular-text" value="0" />
							<p class="description"><?php esc_html_e( 'Maximum points for this level (0 for unlimited).', 'order100' ); ?></p>
						</div>
					</div>

					<div class="o100-form-row">
						<div class="o100-form-group">
							<label><?php esc_html_e( 'Text Color (Optional)', 'order100' ); ?></label>
							<input type="text" name="text_color" id="o100-level-text-color" class="o100-color-picker" value="#000000" />
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

</div>



// TS: 20260119130554

// TS: 20260503194024
