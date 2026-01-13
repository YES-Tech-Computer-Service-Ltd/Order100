<?php
/**
 * Customer Management View
 *
 * @package Order100
 * @since   3.3.0
 */
defined( 'ABSPATH' ) || exit;

// Localize some initial data if needed (most is handled via AJAX)
?>

<div class="o100-customers-container">
    
    <!-- ============================================== -->
    <!-- 1. CUSTOMERS LIST VIEW -->
    <!-- ============================================== -->
    <div id="o100-customers-list-view">
        <div class="o100-view-header">
            <div class="o100-header-left" style="display: flex; flex-direction: column; gap: 5px;">
                <h2 style="margin:0; font-size:18px; font-weight:600; text-transform:uppercase;"><?php esc_html_e( 'CUSTOMERS', 'order100' ); ?></h2>
                <div class="o100-counter-badge" style="background: #e0e7ff; color: #4338ca; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block; width: fit-content;">
                    <span id="o100-customer-count-display">0</span> <?php esc_html_e( 'Total Members', 'order100' ); ?>
                </div>
            </div>
            <div class="o100-view-actions" style="display:flex; gap:10px; align-items:center;">
                <div class="o100-search-box" style="position: relative;">
                    <span class="dashicons dashicons-search" style="position: absolute; left: 10px; top: 8px; color: #9ca3af;"></span>
                    <input type="text" id="o100-customer-search" placeholder="<?php esc_attr_e( 'Search by email...', 'order100' ); ?>" style="padding-left: 35px !important; height: 35px; width: 250px; border-radius: 6px; border: 1px solid #d1d5db;" />
                </div>
                <!-- Hidden file input for import -->
                <input type="file" id="o100-import-customer-file" accept=".csv" style="display:none;" />
                <button type="button" id="o100-btn-import-customers" class="button o100-secondary-btn" style="height:35px; border-radius:6px; display:flex; align-items:center; gap:5px; background:#fff; border:1px solid #d1d5db;">
                    <span class="dashicons dashicons-upload" style="font-size:16px; width:16px; height:16px;"></span> <?php esc_html_e( 'Import CSV', 'order100' ); ?>
                </button>
                <button type="button" id="o100-btn-export-customers" class="button o100-secondary-btn" style="height:35px; border-radius:6px; display:flex; align-items:center; gap:5px; background:#fff; border:1px solid #d1d5db;">
                    <span class="dashicons dashicons-download" style="font-size:16px; width:16px; height:16px;"></span> <?php esc_html_e( 'Export CSV', 'order100' ); ?>
                </button>
            </div>

        </div>

        <div class="o100-table-scroll" style="margin-top: 20px;">
            <table class="wp-list-table widefat fixed striped o100-loyalty-table o100-customers-table">
                <thead>
                    <tr>
                        <th scope="col" class="column-email sortable" data-sort="user_email" style="padding-left: 15px; cursor:pointer;">
                            <?php esc_html_e( 'CUSTOMER EMAIL', 'order100' ); ?> <span class="dashicons dashicons-sort"></span>
                        </th>
                        <th scope="col" class="column-points text-center sortable" data-sort="points" style="width: 100px; text-align: center; cursor:pointer;">
                            <?php esc_html_e( 'POINTS', 'order100' ); ?> <span class="dashicons dashicons-sort"></span>
                        </th>
                        <th scope="col" class="column-level text-center sortable" data-sort="level_id" style="width: 140px; text-align: center; cursor:pointer;">
                            <?php esc_html_e( 'LEVEL', 'order100' ); ?> <span class="dashicons dashicons-sort"></span>
                        </th>
                        <th scope="col" class="column-total-earned text-center" style="width: 100px; text-align: center;"><?php esc_html_e( 'EARNED', 'order100' ); ?></th>
                        <th scope="col" class="column-total-used text-center" style="width: 100px; text-align: center;"><?php esc_html_e( 'USED', 'order100' ); ?></th>
                        <th scope="col" class="column-status text-center" style="width: 100px; text-align: center;"><?php esc_html_e( 'STATUS', 'order100' ); ?></th>
                        <th scope="col" class="column-actions text-right" style="width: 250px; text-align: right; padding-right: 15px;"><?php esc_html_e( 'ACTIONS', 'order100' ); ?></th>
                    </tr>
                </thead>


                <tbody id="o100-customers-tbody">
                    <tr><td colspan="7" class="o100-loyalty-empty" style="text-align: center; padding: 40px 0; color: #6b7280;"><?php esc_html_e( 'Loading customers...', 'order100' ); ?></td></tr>
                </tbody>
            </table>
        </div>

        <div class="o100-pagination" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; align-items: center;">
            <button type="button" class="button o100-btn-prev-customers" disabled>
                <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span> <?php esc_html_e( 'Previous', 'order100' ); ?>
            </button>
            <button type="button" class="button o100-btn-next-customers">
                <?php esc_html_e( 'Next', 'order100' ); ?> <span class="dashicons dashicons-arrow-right-alt2" style="vertical-align: middle;"></span>
            </button>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- 2. CUSTOMER DETAIL VIEW -->
    <!-- ============================================== -->
    <div id="o100-customer-detail-view" style="display: none;">
        <div class="o100-view-header" style="margin-bottom: 25px;">
            <button type="button" class="button o100-btn-back-to-customers" style="border-radius:6px; background:#fff; border:1px solid #d1d5db; box-shadow:none;">
                <span class="dashicons dashicons-arrow-left-alt" style="vertical-align: middle; font-size:16px;"></span> <?php esc_html_e( 'BACK TO LIST', 'order100' ); ?>
            </button>
        </div>

        <!-- Detail Header -->
        <div class="o100-customer-detail-top" style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px;">
            <div class="o100-detail-avatar" style="width: 70px; height: 70px; background: #4338ca; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff;">
               <span class="dashicons dashicons-admin-users" style="font-size: 35px; width: 35px; height: 35px;"></span>
            </div>
            <div class="o100-detail-main-info">
                <h1 id="detail-customer-email" style="margin: 0; font-size: 24px; font-weight: 700; color: #1e293b;">-</h1>
                <div class="o100-detail-badges" style="display:flex; gap:10px; margin-top:5px;">
                    <span id="detail-customer-level-badge" class="o100-badge status-active" style="padding:4px 12px; font-size:12px;">-</span>
                    <span id="detail-customer-status-badge" class="o100-badge" style="padding:4px 12px; font-size:12px;">-</span>
                </div>
            </div>
        </div>

        <!-- Detail Grid Body -->
        <div class="o100-detail-grid" style="display: grid; grid-template-columns: 350px 1fr; gap: 30px; margin-bottom: 40px;">
            
            <!-- Left Column: Profile Card -->
            <div class="o100-profile-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; height: fit-content; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="o100-card-header" style="background: #f8fafc; padding: 15px 20px; border-bottom: 1px solid #e2e8f0;">
                    <h3 style="margin:0; font-size:14px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.05em;"><?php esc_html_e( 'Customer Profile', 'order100' ); ?></h3>
                </div>
                <div class="o100-card-body" style="padding: 20px;">
                    <div class="o100-field-group" style="margin-bottom: 20px;">
                        <label style="display:block; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:8px;"><?php esc_html_e( 'Referral Code', 'order100' ); ?></label>
                        <div class="o100-copy-box" style="display:flex; border:1px solid #cbd5e1; border-radius:6px; overflow:hidden;">
                            <input type="text" id="detail-referral-code" value="-" readonly style="flex:1; border:none; background:#f8fafc; font-weight:700; color:#334155; font-family:monospace; padding:8px 12px;" />
                            <button type="button" id="o100-copy-refer-btn" style="border:none; background:#4338ca; color:#fff; padding:0 15px; cursor:pointer;"><span class="dashicons dashicons-admin-page" style="font-size:16px;"></span></button>
                        </div>
                    </div>

                    <div class="o100-field-group" style="margin-bottom: 20px;">
                        <label style="display:block; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:8px;"><?php esc_html_e( 'Birthday', 'order100' ); ?></label>
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <span id="detail-birthday-display" style="font-weight:600; color:#334155;">-</span>
                            <button type="button" class="o100-btn-edit-profile" style="background:none; border:none; color:#4338ca; font-weight:600; cursor:pointer; font-size:12px;"><?php esc_html_e( 'Edit', 'order100' ); ?></button>
                        </div>
                    </div>

                    <div class="o100-field-divider" style="height:1px; background:#f1f5f9; margin: 20px 0;"></div>

                    <div class="o100-field-group" style="margin-bottom: 15px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; color:#334155;"><?php esc_html_e( 'Email Opt-in', 'order100' ); ?></label>
                            <small style="color:#94a3b8;"><?php esc_html_e( 'Allow sending emails', 'order100' ); ?></small>
                        </div>
                        <label class="o100-toggle">
                            <input type="checkbox" id="detail-optin-toggle" checked>
                            <span class="o100-slider"></span>
                        </label>
                    </div>

                    <div class="o100-field-group" style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:600; color:#334155;"><?php esc_html_e( 'Ban Customer', 'order100' ); ?></label>
                            <small style="color:#94a3b8;"><?php esc_html_e( 'Restrict all actions', 'order100' ); ?></small>
                        </div>
                        <label class="o100-toggle">
                            <input type="checkbox" id="detail-ban-toggle">
                            <span class="o100-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Right Column: Statistics Grid -->
            <div class="o100-stats-grid-container">
                <h3 style="margin:0 0 15px 0; font-size:14px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.05em;"><?php esc_html_e( 'Loyalty Statistics', 'order100' ); ?></h3>
                <div class="o100-stats-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <!-- Stat 1: Current Points -->
                    <div class="o100-stat-box" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div style="width:40px; height:40px; background:#e0e7ff; color:#4338ca; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                                <span class="dashicons dashicons-awards"></span>
                            </div>
                            <button type="button" class="o100-btn-adjust-points-inline" style="background:none; border:none; color:#4338ca; font-weight:700; font-size:11px; cursor:pointer; text-transform:uppercase;"><?php esc_html_e( 'Adjust', 'order100' ); ?></button>
                        </div>
                        <div style="margin-top:15px;">
                            <span id="stat-current-points" style="display:block; font-size:28px; font-weight:800; color:#1e293b;">0</span>
                            <span style="font-size:12px; font-weight:600; color:#94a3b8; text-transform:uppercase;"><?php esc_html_e( 'Point Balance', 'order100' ); ?></span>
                        </div>
                    </div>

                    <!-- Stat 2: Rewards Earned -->
                    <div class="o100-stat-box" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <div style="width:40px; height:40px; background:#f0fdf4; color:#16a34a; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                            <span class="dashicons dashicons-download"></span>
                        </div>
                        <div style="margin-top:15px;">
                            <span id="stat-rewards-earned" style="display:block; font-size:28px; font-weight:800; color:#1e293b;">0</span>
                            <span style="font-size:12px; font-weight:600; color:#94a3b8; text-transform:uppercase;"><?php esc_html_e( 'Rewards Earned', 'order100' ); ?></span>
                        </div>
                    </div>

                    <!-- Stat 3: Rewards Used -->
                    <div class="o100-stat-box" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <div style="width:40px; height:40px; background:#fff1f2; color:#e11d48; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                            <span class="dashicons dashicons-upload"></span>
                        </div>
                        <div style="margin-top:15px;">
                            <span id="stat-rewards-used" style="display:block; font-size:28px; font-weight:800; color:#1e293b;">0</span>
                            <span style="font-size:12px; font-weight:600; color:#94a3b8; text-transform:uppercase;"><?php esc_html_e( 'Rewards Used', 'order100' ); ?></span>
                        </div>
                    </div>

                    <!-- Stat 4: Reward Value -->
                    <div class="o100-stat-box" style="background:linear-gradient(135deg, #4338ca 0%, #6366f1 100%); border:none; border-radius:12px; padding:20px; box-shadow: 0 4px 12px rgba(67, 56, 202, 0.2);">
                        <div style="width:40px; height:40px; background:rgba(255,255,255,0.2); color:#fff; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                            <span class="dashicons dashicons-cart"></span>
                        </div>
                        <div style="margin-top:15px;">
                            <span id="stat-reward-value" style="display:block; font-size:28px; font-weight:800; color:#fff;">$0.00</span>
                            <span style="font-size:12px; font-weight:600; color:rgba(255,255,255,0.7); text-transform:uppercase;"><?php esc_html_e( 'Total Reward Value', 'order100' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Tables Section -->
        <div class="o100-history-sections" style="display:flex; flex-direction:column; gap:40px;">
            
            <!-- Transaction History -->
            <div class="o100-history-table-wrapper">
                <h3 style="margin:0 0 15px 0; font-size:14px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:10px;">
                    <span class="dashicons dashicons-list-view" style="font-size:18px;"></span> <?php esc_html_e( 'Transaction Details', 'order100' ); ?>
                </h3>
                <table class="wp-list-table widefat fixed striped o100-loyalty-table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 180px; padding-left: 15px;"><?php esc_html_e( 'DATE', 'order100' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'ACTIVITY / CAMPAIGN', 'order100' ); ?></th>
                            <th scope="col" class="text-center" style="width: 120px; text-align: center;"><?php esc_html_e( 'POINTS', 'order100' ); ?></th>
                            <th scope="col" class="text-center" style="width: 140px; text-align: center;"><?php esc_html_e( 'ORDER TOTAL', 'order100' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="o100-customer-transactions-tbody">
                        <tr><td colspan="4" class="o100-loyalty-empty" style="text-align: center; padding: 30px 0;"><?php esc_html_e( 'No transactions found.', 'order100' ); ?></td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Reward History -->
            <div class="o100-history-table-wrapper">
                <h3 style="margin:0 0 15px 0; font-size:14px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:10px;">
                    <span class="dashicons dashicons-tickets" style="font-size:18px;"></span> <?php esc_html_e( 'Reward Details', 'order100' ); ?>
                </h3>
                <table class="wp-list-table widefat fixed striped o100-loyalty-table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 180px; padding-left: 15px;"><?php esc_html_e( 'REDEEMED DATE', 'order100' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'REWARD NAME', 'order100' ); ?></th>
                            <th scope="col" class="text-center" style="width: 120px; text-align: center;"><?php esc_html_e( 'COUPON CODE', 'order100' ); ?></th>
                            <th scope="col" class="text-center" style="width: 180px; text-align: center;"><?php esc_html_e( 'EXPIRY DATE', 'order100' ); ?></th>
                            <th scope="col" class="text-center" style="width: 100px; text-align: center;"><?php esc_html_e( 'STATUS', 'order100' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="o100-customer-rewards-tbody">
                        <tr><td colspan="5" class="o100-loyalty-empty" style="text-align: center; padding: 30px 0;"><?php esc_html_e( 'No rewards found.', 'order100' ); ?></td></tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- ============================================== -->
    <!-- 3. MODALS -->
    <!-- ============================================== -->

    <!-- Adjust Points Modal (Shared) -->
    <div id="o100-adjust-points-modal" class="o100-modal" style="display: none; position: fixed; z-index: 99999; left:0; top:0; width:100%; height:100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center;">
        <div class="o100-modal-content" style="background:#fff; width:450px; border-radius:10px; overflow:hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div class="o100-modal-header" style="padding:15px 20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; background:#f9fafb;">
                <h3 style="margin:0; font-size:16px; font-weight:700;"><?php esc_html_e( 'ADJUST POINTS', 'order100' ); ?></h3>
                <span class="o100-modal-close dashicons dashicons-no-alt" style="cursor:pointer; color:#999;"></span>
            </div>
            <div class="o100-modal-body" style="padding:20px;">
                <input type="hidden" id="adjust-customer-id" />
                <div class="o100-form-group" style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:600; margin-bottom:5px;"><?php esc_html_e( 'Customer:', 'order100' ); ?></label>
                    <input type="text" id="adjust-customer-email" disabled style="width:100%; background:#f3f4f6; border-radius:6px; border:1px solid #d1d5db;" />
                </div>
                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <div class="o100-form-group" style="flex:1;">
                        <label style="display:block; font-weight:600; margin-bottom:5px;"><?php esc_html_e( 'Action:', 'order100' ); ?></label>
                        <select id="adjust-action-type" style="width:100%; border-radius:6px; height:35px;">
                            <option value="add"><?php esc_html_e( 'Add (+)', 'order100' ); ?></option>
                            <option value="reduce"><?php esc_html_e( 'Reduce (-)', 'order100' ); ?></option>
                            <option value="overwrite"><?php esc_html_e( 'Set Total', 'order100' ); ?></option>
                        </select>
                    </div>
                    <div class="o100-form-group" style="flex:1;">
                        <label style="display:block; font-weight:600; margin-bottom:5px;"><?php esc_html_e( 'Points:', 'order100' ); ?></label>
                        <input type="number" id="adjust-points-value" min="1" style="width:100%; height:35px; border-radius:6px; border:1px solid #d1d5db;" />
                    </div>
                </div>
                <div class="o100-form-group">
                    <label style="display:block; font-weight:600; margin-bottom:5px;"><?php esc_html_e( 'Reason / Admin Note:', 'order100' ); ?></label>
                    <textarea id="adjust-admin-note" rows="2" style="width:100%; border-radius:6px; border:1px solid #d1d5db; padding:8px;"></textarea>
                </div>
            </div>
            <div class="o100-modal-footer" style="padding:15px 20px; border-top:1px solid #eee; display:flex; justify-content:flex-end; gap:10px; background:#f9fafb;">
                <button type="button" class="button o100-modal-close-btn" style="border-radius:6px;"><?php esc_html_e( 'Cancel', 'order100' ); ?></button>
                <button type="button" class="button button-primary o100-btn-confirm-adjust" style="border-radius:6px; background:#4338ca; border-color:#4338ca;"><?php esc_html_e( 'Save Adjustment', 'order100' ); ?></button>
            </div>
        </div>
    </div>

    <!-- Edit Customer Profile Modal -->
    <div id="o100-edit-customer-modal" class="o100-modal" style="display: none; position: fixed; z-index: 99999; left:0; top:0; width:100%; height:100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center;">
        <div class="o100-modal-content" style="background:#fff; width:400px; border-radius:10px; overflow:hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div class="o100-modal-header" style="padding:15px 20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; background:#f9fafb;">
                <h3 style="margin:0; font-size:16px; font-weight:700;"><?php esc_html_e( 'EDIT PROFILE', 'order100' ); ?></h3>
                <span class="o100-modal-close dashicons dashicons-no-alt" style="cursor:pointer; color:#999;"></span>
            </div>
            <div class="o100-modal-body" style="padding:20px;">
                <input type="hidden" id="edit-customer-id" />
                <div class="o100-form-group" style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:600; margin-bottom:5px;"><?php esc_html_e( 'Email:', 'order100' ); ?></label>
                    <input type="text" id="edit-customer-email" disabled style="width:100%; background:#f3f4f6; border-radius:6px; border:1px solid #d1d5db;" />
                </div>
                <div class="o100-form-group">
                    <label style="display:block; font-weight:600; margin-bottom:5px;"><?php esc_html_e( 'Birth Date:', 'order100' ); ?></label>
                    <input type="date" id="edit-customer-birthday" style="width:100%; height:35px; border-radius:6px; border:1px solid #d1d5db;" />
                </div>
            </div>
            <div class="o100-modal-footer" style="padding:15px 20px; border-top:1px solid #eee; display:flex; justify-content:flex-end; gap:10px; background:#f9fafb;">
                <button type="button" class="button o100-modal-close-btn" style="border-radius:6px;"><?php esc_html_e( 'Cancel', 'order100' ); ?></button>
                <button type="button" class="button button-primary o100-btn-confirm-edit" style="border-radius:6px; background:#4338ca; border-color:#4338ca;"><?php esc_html_e( 'Update Data', 'order100' ); ?></button>
            </div>
        </div>
    </div>

</div>

<style>
.o100-customers-container { background: #fff; padding: 20px; border-radius: 8px; min-height: 600px; }
.o100-view-header { display: flex; justify-content: space-between; align-items: flex-start; }
.o100-loyalty-table thead th { background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #64748b; font-weight: 700; font-size: 11px; text-transform: uppercase; padding: 12px 10px; }
.o100-loyalty-table tbody td { padding: 12px 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
.o100-badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; display: inline-block; }
.status-active { background: #dcfce7; color: #166534; }
.status-banned { background: #fee2e2; color: #991b1b; }
.points-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-weight: 700; font-size: 12px; min-width: 30px; text-align: center; }
.points-badge.positive { background: #ecfdf5; color: #059669; }
.points-badge.negative { background: #fff1f2; color: #e11d48; }
.action-link { font-weight: 600; text-decoration: none; font-size: 12px; }
.action-link:hover { text-decoration: underline; }
#o100-customers-tbody .column-email strong { color: #1e293b; font-size: 14px; }

/* Sortable Headers */
th.sortable:hover { background: #f1f5f9 !important; }
th.sortable span.dashicons { font-size: 14px; color: #94a3b8; }
th.sort-asc span.dashicons:before { content: "\f142"; color: #4338ca; }
th.sort-desc span.dashicons:before { content: "\f140"; color: #4338ca; }

/* Toggle Switch Styling */
.o100-toggle { position: relative; display: inline-block; width: 44px; height: 22px; }
.o100-toggle input { opacity: 0; width: 0; height: 0; }
.o100-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 22px; }
.o100-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .o100-slider { background-color: #4338ca; }
input:focus + .o100-slider { box-shadow: 0 0 1px #4338ca; }
input:checked + .o100-slider:before { transform: translateX(22px); }

/* Custom Scrollbar for the table if needed */
.o100-table-scroll { overflow-x: auto; }

/* Detail Loading State */
.o100-loading-overlay { position: absolute; top:0; left:0; width:100%; height:100%; background: rgba(255,255,255,0.7); display:flex; align-items:center; justify-content:center; z-index:10; border-radius:12px; }
</style>





// TS: 20260104170421

// TS: 20260113143610
