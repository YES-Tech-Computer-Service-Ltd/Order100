<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class O100_Menu_Builder_Tab {
    
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'cmb2_admin_init', array( $this, 'register_menu_builder_tab' ) );
        
        // AJAX handlers for the List + Modal UI
        add_action( 'wp_ajax_o100_get_menu_configs', array( $this, 'ajax_get_menu_configs' ) );
        add_action( 'wp_ajax_o100_save_menu_config', array( $this, 'ajax_save_menu_config' ) );
        add_action( 'wp_ajax_o100_delete_menu_config', array( $this, 'ajax_delete_menu_config' ) );
        add_action( 'wp_ajax_o100_mb_search_products', array( $this, 'ajax_search_products' ) );
        add_action( 'wp_ajax_o100_get_product_titles', array( $this, 'ajax_get_product_titles' ) );
    }

    public function register_menu_builder_tab() {
        $cmb = new_cmb2_box( array(
            'id'           => 'o100_menu_builder',
            'title'        => __( 'Menu Builder', 'order100' ),
            'object_types' => array( 'options-page' ),
            'option_key'   => 'o100_menu_shortcodes_dummy', // We don't use CMB2 to save this anymore, we use AJAX
        ) );

        // Ensure select2 is enqueued
        wp_enqueue_style( 'select2' );
        wp_enqueue_script( 'select2' );

        $cmb->add_field( array(
            'name' => '',
            'id'   => 'o100_menu_builder_ui',
            'type' => 'title',
            'render_row_cb' => array( $this, 'render_fluent_menu_builder_ui' ),
        ) );
    }

    public function render_fluent_menu_builder_ui() {
        ?>
        <div class="o100-fluent-locations-app" style="container-type: inline-size; width:100%; box-sizing:border-box;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px;">
                <h3 style="margin:0; font-size:16px; color:#0f172a;"><?php esc_html_e( 'Menu Shortcodes', 'order100' ); ?></h3>
                <button type="button" class="button button-primary o100-add-menu-btn" style="background:#2563eb; border-color:#2563eb; box-shadow:none;">
                    <span class="dashicons dashicons-plus-alt2" style="line-height:inherit; margin-right:4px;"></span>
                    <?php esc_html_e( 'Create Menu', 'order100' ); ?>
                </button>
            </div>
            
            <div class="o100-menus-list-container">
                <p style="color:#64748b; font-size:14px; text-align:center; padding: 40px 0; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1;">
                    <?php esc_html_e( 'Loading menus...', 'order100' ); ?>
                </p>
            </div>

            <!-- Fluent Style Modal -->
            <div class="o100-menu-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; justify-content:center; align-items:center;">
                <div class="o100-menu-modal-content" style="background:#fff; width:100%; max-width:700px; height:85vh; border-radius:8px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); display:flex; flex-direction:column; max-height:90vh;">
                    
                    <div style="padding:15px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:16px; font-weight:600; color:#0f172a;" id="o100-menu-modal-title"><?php esc_html_e( 'Configure Menu', 'order100' ); ?></h3>
                        <span class="dashicons dashicons-no-alt o100-close-menu-modal" style="cursor:pointer; color:#64748b;"></span>
                    </div>
                    
                                                            <div id="o100-menu-modal-sc-display" style="display:none; padding:12px 20px; background:#eff6ff; border-bottom:1px solid #bfdbfe; color:#1e3a8a; font-family:monospace; font-size:14px; align-items:center; justify-content:space-between;">
                        <span class="sc-text" style="user-select:all; font-weight:600;"></span>
                        <a href="#" class="o100-copy-sc-btn" style="text-decoration:none; font-weight:600; font-family:sans-serif; font-size:13px; color:#2563eb; cursor:pointer;" title="Copy to clipboard"><span class="dashicons dashicons-admin-page" style="line-height:inherit;"></span> Copy</a>
                    </div>
                    <div style="display:flex; border-bottom:1px solid #e2e8f0; background:#f8fafc; padding:0 20px;">
                        <a href="#" class="o100-menu-tab active" data-tab="layout" style="padding:12px 16px; font-size:14px; font-weight:500; color:#2563eb; border-bottom:2px solid #2563eb; text-decoration:none; margin-bottom:-1px;">Layout</a>
                        <a href="#" class="o100-menu-tab" data-tab="query" style="padding:12px 16px; font-size:14px; font-weight:500; color:#64748b; border-bottom:2px solid transparent; text-decoration:none; margin-bottom:-1px;">Query</a>
                        <a href="#" class="o100-menu-tab" data-tab="misc" style="padding:12px 16px; font-size:14px; font-weight:500; color:#64748b; border-bottom:2px solid transparent; text-decoration:none; margin-bottom:-1px;">Misc</a>
                    </div>
                    
                    <div style="padding:20px; overflow-y:auto; flex-grow:1; background:#fbfbfb;">
                        <style>
                            .o100-menu-field { margin-bottom: 20px; background: #fff; padding: 15px; border: 1px solid #e2e8f0; border-radius: 6px; }
                            .o100-menu-field-header { margin-bottom: 8px; font-weight: 600; color: #1e293b; font-size: 13px; }
                            .o100-menu-help { color: #64748b; font-size: 12px; margin-bottom: 10px; line-height:1.4; }
                            .o100-menu-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 12px; font-size: 13px; color: #334155; }
                            .o100-menu-input:focus { border-color: #3b82f6; outline: none; }
                            .o100-menu-row { display: flex; gap: 15px; }
                            .o100-menu-col { flex: 1; }
                            .o100-responsive-controls { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
                            .o100-responsive-icons { display: flex; gap: 4px; }
                            .o100-responsive-icon { padding: 4px; border-radius: 4px; cursor: pointer; color: #94a3b8; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
                            .o100-responsive-icon svg, .o100-responsive-icon span { width: 16px; height: 16px; font-size: 16px; }
                            .o100-responsive-icon:hover { background: #f1f5f9; color: #475569; }
                            .o100-responsive-icon.active { background: #e2e8f0; color: #0f172a; }
                            .o100-responsive-input-wrap { display: none; }
                            .o100-responsive-input-wrap.active { display: block; }
                            @container (max-width: 700px) {
                                .o100-hide-on-mobile { display: none !important; }
                            }
                            @media (max-width: 1024px) {
                                .o100-hide-on-mobile { display: none !important; }
                            }


                        </style>

                                                <!-- LAYOUT TAB -->
                        <div id="o100-menu-tab-layout" class="o100-menu-tab-content" style="display:block;">
                            <div style="font-weight: 700; color: #0f172a; margin: 0 0 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; font-size: 14px;">General Settings</div>
                            <div class="o100-menu-field">
                                <div class="o100-menu-field-header"><?php esc_html_e( 'Shortcode ID', 'order100' ); ?></div>
                                <div class="o100-menu-help"><?php esc_html_e( 'Unique identifier for this menu. Will be used like [order100_food_menu id="lunch"]', 'order100' ); ?></div>
                                <input type="text" id="mb_id" class="o100-menu-input" placeholder="e.g. main-menu">
                                <input type="hidden" id="mb_original_id" value="">
                            </div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Display Type', 'order100' ); ?></div>
                                    <div class="o100-menu-help"><?php esc_html_e( 'Choose the primary layout architecture.', 'order100' ); ?></div>
                                    <select id="mb_sc_type" class="o100-menu-input">
                                        <option value="mn_group">Menu Group (with navigation)</option>
                                        <option value="grid">Standalone Grid</option>
                                        <option value="list">Standalone List</option>
                                        <option value="table">Standalone Table</option>
                                        <option value="carousel">Standalone Carousel</option>
                                    </select>
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Inner Layout', 'order100' ); ?></div>
                                    <div class="o100-menu-help"><?php esc_html_e( 'Choose how items are displayed inside the group.', 'order100' ); ?></div>
                                    <select id="mb_sc_layout" class="o100-menu-input">
                                        <option value="list">List View</option>
                                        <option value="grid">Grid View</option>
                                        <option value="table">Table View</option>
                                    </select>
                                </div>
                            </div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col" style="display:none;">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Style Preset', 'order100' ); ?></div>
                                    <select id="mb_style" class="o100-menu-input">
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                    </select>
                                </div>
                                <div class="o100-menu-field o100-menu-col" id="mb_columns_field_wrapper">
                                    <div class="o100-responsive-controls">
                                        <div class="o100-menu-field-header" style="margin-bottom:0;"><?php esc_html_e( 'Columns', 'order100' ); ?></div>
                                        <div class="o100-responsive-icons">
                                            <div class="o100-responsive-icon active" data-device="pc" title="Desktop"><span class="dashicons dashicons-desktop"></span></div>
                                            <div class="o100-responsive-icon" data-device="tablet" title="Tablet"><span class="dashicons dashicons-tablet"></span></div>
                                            <div class="o100-responsive-icon" data-device="mobile" title="Mobile"><span class="dashicons dashicons-smartphone"></span></div>
                                        </div>
                                    </div>
                                    <div class="o100-responsive-input-wrap active" data-device="pc">
                                        <select id="mb_column" class="o100-menu-input">
                                            <option value="1">1 Column</option>
                                            <option value="2">2 Columns</option>
                                            <option value="3">3 Columns</option>
                                            <option value="4">4 Columns</option>
                                            <option value="5">5 Columns</option>
                                        </select>
                                    </div>
                                    <div class="o100-responsive-input-wrap" data-device="tablet">
                                        <select id="mb_column_tablet" class="o100-menu-input">
                                            <option value="1">1 Column</option>
                                            <option value="2">2 Columns</option>
                                            <option value="3">3 Columns</option>
                                            <option value="4">4 Columns</option>
                                        </select>
                                    </div>
                                    <div class="o100-responsive-input-wrap" data-device="mobile">
                                        <select id="mb_column_mobile" class="o100-menu-input">
                                            <option value="1">1 Column</option>
                                            <option value="2">2 Columns</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Heading Style', 'order100' ); ?></div>
                                    <select id="mb_sc_heading" class="o100-menu-input">
                                        <option value="">Default</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                    </select>
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Image Size', 'order100' ); ?></div>
                                    <div class="o100-menu-help"><?php esc_html_e( 'Leave blank to use default WP thumbnail size.', 'order100' ); ?></div>
                                    <input type="text" id="mb_img_size" class="o100-menu-input" placeholder="e.g. medium_large">
                                </div>
                            </div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Excerpt Word Limit', 'order100' ); ?></div>
                                    <div class="o100-menu-help"><?php esc_html_e( 'Limit the short description word count.', 'order100' ); ?></div>
                                    <input type="number" id="mb_number_excerpt" class="o100-menu-input" placeholder="Leave blank for default">
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <!-- Empty column to keep grid layout aligned -->
                                </div>
                            </div>

                            <div id="section_filter" style="margin-top:24px;"><div style="font-weight: 700; color: #0f172a; margin: 0 0 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; font-size: 14px;">Filter & Navigation Settings</div>
                            
                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Show Menu Filter', 'order100' ); ?></div>
                                    <select id="mb_menu_filter" class="o100-menu-input">
                                        <option value="hide">Hide Filter</option>
                                        <option value="show">Show Filter</option>
                                    </select>
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Filter Position', 'order100' ); ?></div>
                                    <select id="mb_menu_pos" class="o100-menu-input">
                                        <option value="top">Top</option>
                                        <option value="left">Left Sidebar</option>
                                    </select>
                                </div>
                            </div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Filter Style', 'order100' ); ?></div>
                                    <select id="mb_filter_style" class="o100-menu-input">
                                        <option value="">Default Text</option>
                                        <option value="icon">With Icons</option>
                                    </select>
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Active Filter by Default', 'order100' ); ?></div>
                                    <input type="text" id="mb_active_filter" class="o100-menu-input" placeholder="Enter category slug">
                                </div>
                            </div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Re-order Menu Filter', 'order100' ); ?></div>
                                    <select id="mb_order_cat" class="o100-menu-input">
                                        <option value="">No</option>
                                        <option value="yes">Yes</option>
                                    </select>
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Hide "All" Filter Option', 'order100' ); ?></div>
                                    <select id="mb_hide_ftall" class="o100-menu-input">
                                        <option value="">No</option>
                                        <option value="yes">Yes</option>
                                    </select>
                                </div>
                            </div>

                            </div><div id="section_carousel" style="margin-top:24px;"><div style="font-weight: 700; color: #0f172a; margin: 0 0 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; font-size: 14px;">Carousel Settings</div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Carousel Items Visible', 'order100' ); ?></div>
                                    <div class="o100-menu-help"><?php esc_html_e( 'Number of items visible in carousel mode.', 'order100' ); ?></div>
                                    <input type="number" id="mb_slidesshow" class="o100-menu-input" placeholder="e.g. 3">
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <!-- Empty column to replace the moved excerpt field -->
                                </div>
                            </div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Autoplay Carousel', 'order100' ); ?></div>
                                    <select id="mb_autoplay" class="o100-menu-input">
                                        <option value="">No</option>
                                        <option value="1">Yes</option>
                                    </select>
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Autoplay Speed (ms)', 'order100' ); ?></div>
                                    <input type="number" id="mb_autoplayspeed" class="o100-menu-input" placeholder="e.g. 3000">
                                </div>
                            </div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Infinite Loop', 'order100' ); ?></div>
                                    <select id="mb_infinite" class="o100-menu-input">
                                        <option value="">No</option>
                                        <option value="yes">Yes</option>
                                    </select>
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Enable Lazy Loading', 'order100' ); ?></div>
                                    <select id="mb_loading_effect" class="o100-menu-input">
                                        <option value="">No</option>
                                        <option value="1">Yes</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        </div>
                        <!-- QUERY TAB -->
                        <div id="o100-menu-tab-query" class="o100-menu-tab-content" style="display:none;">
                            <div style="font-weight: 700; color: #0f172a; margin: 0 0 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; font-size: 14px;">Data Source</div>
                            
                            <div class="o100-menu-field">
                                <div class="o100-menu-field-header"><?php esc_html_e( 'Include Categories', 'order100' ); ?></div>
                                <div class="o100-menu-help"><?php esc_html_e( 'Select which categories to fetch products from. Leave empty to fetch from all categories.', 'order100' ); ?></div>
                                <div style="max-height: 150px; overflow-y: auto; border: 1px solid #cbd5e1; padding: 10px; border-radius: 4px; background: #fff;">
                                    <?php 
                                    $cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
                                    if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
                                        foreach ( $cats as $cat ) {
                                            echo '<label style="display:block; margin-bottom:5px;"><input type="checkbox" class="mb-cat-checkbox" value="' . esc_attr( $cat->slug ) . '"> ' . esc_html( $cat->name ) . '</label>';
                                        }
                                    } else {
                                        echo '<p>No categories found.</p>';
                                    }
                                    ?>
                                </div>
                                <input type="hidden" id="mb_cat" value="">
                            </div>

                            <div style="display: flex; align-items: center; justify-content: center; margin: -10px 0 10px;">
                                <span style="background: #e2e8f0; color: #475569; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid #cbd5e1;">AND (Intersection)</span>
                            </div>

                            <div class="o100-menu-field">
                                <div class="o100-menu-field-header"><?php esc_html_e( 'Specific Product IDs', 'order100' ); ?></div>
                                <div class="o100-menu-help">
                                    <?php esc_html_e( 'Search and select products to explicitly include.', 'order100' ); ?><br>
                                    <strong style="color: #ea580c;">Note:</strong> <?php esc_html_e( 'If both Categories and Specific Products are set, a product will ONLY show if it belongs to the selected categories AND is selected here.', 'order100' ); ?>
                                </div>
                                <select id="mb_ids" class="o100-menu-input o100-product-select" multiple="multiple" style="width: 100%;"></select>
                            </div>

                            <div style="font-weight: 700; color: #0f172a; margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; font-size: 14px;">Pagination</div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Total Item Limit', 'order100' ); ?></div>
                                    <div class="o100-menu-help"><?php esc_html_e( 'Total max items to fetch (-1 for all).', 'order100' ); ?></div>
                                    <input type="number" id="mb_count" class="o100-menu-input" placeholder="e.g. 9" value="9">
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Items Per Page', 'order100' ); ?></div>
                                    <div class="o100-menu-help"><?php esc_html_e( 'Number of items per page for pagination.', 'order100' ); ?></div>
                                    <input type="number" id="mb_posts_per_page" class="o100-menu-input" placeholder="e.g. 12">
                                </div>
                            </div>
                            
                            <div class="o100-menu-field">
                                <div class="o100-menu-field-header"><?php esc_html_e( 'Pagination Type', 'order100' ); ?></div>
                                <select id="mb_page_navi" class="o100-menu-input">
                                    <option value="">Standard Numbers</option>
                                    <option value="loadmore">Load More Button</option>
                                </select>
                            </div>

                            <div style="font-weight: 700; color: #0f172a; margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; font-size: 14px;">Sorting & Filters</div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Order Direction', 'order100' ); ?></div>
                                    <select id="mb_order" class="o100-menu-input">
                                        <option value="DESC">Descending (Newest first)</option>
                                        <option value="ASC">Ascending (Oldest first)</option>
                                    </select>
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Order By', 'order100' ); ?></div>
                                    <select id="mb_orderby" class="o100-menu-input">
                                        <option value="date">Date</option>
                                        <option value="order_field">Custom order field</option>
                                        <option value="sale">Sale</option>
                                        <option value="ID">ID</option>
                                        <option value="author">Author</option>
                                        <option value="title">Title</option>
                                        <option value="name">Name</option>
                                        <option value="modified">Modified</option>
                                        <option value="parent">Parent</option>
                                        <option value="rand">Rand</option>
                                        <option value="menu_order">Menu order</option>
                                        <option value="meta_value">Meta value</option>
                                        <option value="meta_value_num">Meta value num</option>
                                        <option value="post__in">Post__in</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>
                            </div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Custom Meta Key', 'order100' ); ?></div>
                                    <input type="text" id="mb_meta_key" class="o100-menu-input" placeholder="e.g. _price">
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Custom Meta Value', 'order100' ); ?></div>
                                    <input type="text" id="mb_meta_value" class="o100-menu-input" placeholder="Target value">
                                </div>
                            </div>

                            <div class="o100-menu-field">
                                <div class="o100-menu-field-header"><?php esc_html_e( 'Only Featured Food', 'order100' ); ?></div>
                                <select id="mb_featured" class="o100-menu-input">
                                    <option value="">No (Show All)</option>
                                    <option value="1">Yes (Only Featured)</option>
                                </select>
                            </div>

                            <div class="o100-menu-field">
                                <div class="o100-menu-field-header"><?php esc_html_e( 'Photos First', 'order100' ); ?></div>
                                <div class="o100-menu-help"><?php esc_html_e( 'Products with images will be displayed before products without images. Within each group, the original sort order is preserved.', 'order100' ); ?></div>
                                <select id="mb_photos_first" class="o100-menu-input">
                                    <option value="">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                            </div>
                        </div>

                        <!-- MISC TAB -->
                        <div id="o100-menu-tab-misc" class="o100-menu-tab-content" style="display:none;">
                            <div style="font-weight: 700; color: #0f172a; margin: 0 0 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; font-size: 14px;">UI & Layout Modifications</div>
                            
                            <div class="o100-menu-field">
                                <div class="o100-menu-field-header"><?php esc_html_e( 'Custom CSS Class Name', 'order100' ); ?></div>
                                <div class="o100-menu-help"><?php esc_html_e( 'Add custom CSS classes for developer styling.', 'order100' ); ?></div>
                                <input type="text" id="mb_class" class="o100-menu-input" placeholder="e.g. my-custom-menu-class">
                            </div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Show Item Counts', 'order100' ); ?></div>
                                    <div class="o100-menu-help"><?php esc_html_e( 'Display the number of products next to categories.', 'order100' ); ?></div>
                                    <select id="mb_show_count" class="o100-menu-input">
                                        <option value="">No</option>
                                        <option value="yes">Yes</option>
                                    </select>
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Enable Floating Cart', 'order100' ); ?></div>
                                    <div class="o100-menu-help"><?php esc_html_e( 'Show a sticky side cart on the menu page.', 'order100' ); ?></div>
                                    <select id="mb_cart_enable" class="o100-menu-input">
                                        <option value="">Default Settings</option>
                                        <option value="yes">Always Show</option>
                                        <option value="no">Always Hide</option>
                                    </select>
                                </div>
                            </div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Enable AJAX Search Bar', 'order100' ); ?></div>
                                    <select id="mb_enable_search" class="o100-menu-input">
                                        <option value="">No</option>
                                        <option value="yes">Yes</option>
                                    </select>
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Enable Live Sort Dropdown', 'order100' ); ?></div>
                                    <select id="mb_live_sort" class="o100-menu-input">
                                        <option value="">No</option>
                                        <option value="1">Yes</option>
                                    </select>
                                </div>
                            </div>

                            <div style="font-weight: 700; color: #0f172a; margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; font-size: 14px;">Product Interaction & Modals</div>

                            <div class="o100-menu-row">
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Enable Product Modal', 'order100' ); ?></div>
                                    <div class="o100-menu-help"><?php esc_html_e( 'Open product details in a popup when clicked.', 'order100' ); ?></div>
                                    <select id="mb_enable_modal" class="o100-menu-input">
                                        <option value="">Default Settings</option>
                                        <option value="yes">Yes</option>
                                        <option value="no">No (Direct Link)</option>
                                    </select>
                                </div>
                                <div class="o100-menu-field o100-menu-col">
                                    <div class="o100-menu-field-header"><?php esc_html_e( 'Hide Add to Cart Form', 'order100' ); ?></div>
                                    <div class="o100-menu-help"><?php esc_html_e( 'Disable the add to cart functionality entirely.', 'order100' ); ?></div>
                                    <select id="mb_hide_atc" class="o100-menu-input">
                                        <option value="">No</option>
                                        <option value="yes">Yes</option>
                                    </select>
                                </div>
                            </div>

                            <div class="o100-menu-field">
                                <div class="o100-menu-field-header"><?php esc_html_e( 'Popup Order Method Overlay', 'order100' ); ?></div>
                                <div class="o100-menu-help"><?php esc_html_e( 'Force users to choose Pickup/Delivery method via popup.', 'order100' ); ?></div>
                                <select id="mb_enable_mtod" class="o100-menu-input">
                                    <option value="">Default</option>
                                    <option value="yes">Yes</option>
                                    <option value="no">No</option>
                                </select>
                            </div>
                        </div>
</div>

                    <div style="padding:15px 20px; border-top:1px solid #e2e8f0; background:#f8fafc; border-radius:0 0 8px 8px; display:flex; justify-content:flex-end;">
                        <button type="button" class="button button-primary o100-save-menu-action" style="background:#3b82f6; border-color:#3b82f6; padding:0 20px; height:36px;">
                            <?php esc_html_e( 'Save Menu Configuration', 'order100' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        $settings = get_option( 'o100_menu_shortcodes', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        $menus_json = wp_json_encode( empty($settings) ? new stdClass() : $settings );
        if ( ! $menus_json ) {
            $menus_json = '{}';
        }
        ?>
        <script>
        window.o100_menus_initial_data = <?php echo $menus_json; ?>;
        jQuery(document).ready(function($) {
            function renderMenusList(data) {
                window.o100_menus_data = data;
                var html = '';
                var count = 0;
                for (var k in data) count++;
                
                if (count === 0) {
                    html = '<p style="color:#64748b; font-size:14px; text-align:center; padding: 40px 0; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1;"><?php esc_html_e( 'No menus configured yet. Click Create Menu.', 'order100' ); ?></p>';
                } else {
                    html = '<div style="width:100%; overflow-x:auto; border:1px solid #e2e8f0; border-radius:8px;"><table class="o100-menu-table" style="width:100%; table-layout:auto; border-collapse:collapse; min-width:400px;"><thead><tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;"><th style="padding:12px 16px; text-align:left; font-weight:600; color:#475569; width:auto; border-right:1px solid #e2e8f0;">Name</th><th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; border-right:1px solid #e2e8f0;" class="o100-hide-xs">Type</th><th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; border-right:1px solid #e2e8f0;" class="o100-hide-sm">Layout</th><th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; border-right:1px solid #e2e8f0;" class="o100-hide-md">Columns</th><th style="padding:12px 16px; text-align:right; font-weight:600; color:#475569; width:120px;">Actions</th></tr></thead><tbody>';
                    $.each(data, function(id, conf) {
                        var sc = '[order100_food_menu id="' + id + '"]';
                        html += '<tr style="border-bottom:1px solid #e2e8f0; transition: background 0.2s;">';
                        html += '<td style="padding:12px 16px; text-align:left; white-space:nowrap; vertical-align:middle; border-right:1px solid #e2e8f0;"><strong class="o100-tooltip" data-tooltip="' + sc.replace(/"/g, '\'') + '" style="cursor:help; color:#0f172a;">' + id + '</strong></td>';
                        html += '<td style="padding:12px 16px; text-align:center; vertical-align:middle; color:#64748b; border-right:1px solid #e2e8f0;" class="o100-hide-xs">' + (conf.sc_type || 'mn_group') + '</td>';
                        html += '<td style="padding:12px 16px; text-align:center; vertical-align:middle; color:#64748b; border-right:1px solid #e2e8f0;" class="o100-hide-sm">' + (conf.sc_layout || 'list') + '</td>';
                        html += '<td style="padding:12px 16px; text-align:center; vertical-align:middle; color:#64748b; border-right:1px solid #e2e8f0;" class="o100-hide-md">' + (conf.column || '2') + '</td>';
                        html += '<td style="padding:12px 16px; text-align:right; white-space:nowrap; vertical-align:middle;">';
                        html += '<a href="#" class="o100-copy-sc o100-tooltip" data-sc="' + sc.replace(/"/g, '\'') + '" style="color:#059669; font-weight:500; margin-right:12px; text-decoration:none; font-size:16px;" data-tooltip="Copy shortcode"><span class="dashicons dashicons-clipboard"></span></a>';
                        html += '<a href="#" class="o100-edit-menu o100-tooltip" data-id="' + id + '" style="color:#2563eb; font-weight:500; margin-right:12px; text-decoration:none; font-size:16px;" data-tooltip="Edit menu"><span class="dashicons dashicons-edit"></span></a>';
                        html += '<a href="#" class="o100-delete-menu o100-tooltip" data-id="' + id + '" style="color:#ef4444; font-weight:500; text-decoration:none; font-size:16px;" data-tooltip="Delete menu"><span class="dashicons dashicons-trash"></span></a>';
                        html += '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                }
                $('.o100-menus-list-container').html(html);
            }

            function loadMenus() {
                $.post(ajaxurl, { action: 'o100_get_menu_configs' }, function(response) {
                    if (response.success) {
                        renderMenusList(response.data);
                    }
                });
            }

            // Render instantly on page load using PHP injected data
            renderMenusList(window.o100_menus_initial_data);

            $('.o100-menu-tab').on('click', function(e) {
                e.preventDefault();
                $('.o100-menu-tab').removeClass('active').css({'color': '#64748b', 'border-bottom-color': 'transparent'});
                $(this).addClass('active').css({'color': '#2563eb', 'border-bottom-color': '#2563eb'});
                $('.o100-menu-tab-content').hide();
                $('#o100-menu-tab-' + $(this).data('tab')).show();
            });

            // Responsive icon switcher
            $(document).on('click', '.o100-responsive-icon', function() {
                var $container = $(this).closest('.o100-menu-field');
                var device = $(this).data('device');
                
                $container.find('.o100-responsive-icon').removeClass('active');
                $(this).addClass('active');
                
                $container.find('.o100-responsive-input-wrap').removeClass('active');
                $container.find('.o100-responsive-input-wrap[data-device="' + device + '"]').addClass('active');
            });

            $(document).on('click', '.o100-delete-menu', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to delete this menu?')) return;
                var mId = $(this).data('id');
                $(this).closest('tr').css('opacity', '0.5');
                $.post(ajaxurl, { action: 'o100_delete_menu_config', id: mId }, function(res) {
                    loadMenus();
                });
            });

            
            $(document).on('click', '.o100-copy-sc-btn', function(e) {
                e.preventDefault();
                var sc = $(this).siblings('.sc-text').text();
                var $link = $(this);
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(sc).then(function() {
                        $link.html('<span class="dashicons dashicons-saved" style="line-height:inherit;"></span> Copied!');
                        setTimeout(function(){ $link.html('<span class="dashicons dashicons-clipboard" style="line-height:inherit;"></span> Copy'); }, 1500);
                    });
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = sc; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
                    $link.html('<span class="dashicons dashicons-saved" style="line-height:inherit;"></span> Copied!');
                    setTimeout(function(){ $link.html('<span class="dashicons dashicons-clipboard" style="line-height:inherit;"></span> Copy'); }, 1500);
                }
            });

            $(document).on('click', '.o100-copy-sc', function(e) {
                e.preventDefault();
                var sc = $(this).data('sc');
                var $link = $(this);
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(sc).then(function() {
                        $link.text('Copied!').css('color','#16a34a');
                        setTimeout(function(){ $link.text('Copy').css('color','#059669'); }, 1500);
                    });
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = sc; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
                    $link.text('Copied!').css('color','#16a34a');
                    setTimeout(function(){ $link.text('Copy').css('color','#059669'); }, 1500);
                }
            });

            function updateFieldVisibility() {
                var type = $('#mb_sc_type').val();
                var layout = $('#mb_sc_layout').val();
                
                var is_mn_group = (type === 'mn_group');
                var is_carousel = (type === 'carousel');
                var is_table = (type === 'table') || (type === 'mn_group' && layout === 'table');
                var is_list = (type === 'list') || (type === 'mn_group' && layout === 'list');
                var is_grid = (type === 'grid') || (type === 'mn_group' && layout === 'grid');
                
                var toggleField = function(sel, show) {
                    if (show) { $(sel).closest('.o100-menu-field').show(); } else { $(sel).closest('.o100-menu-field').hide(); }
                };
                
                // sc_layout: show-mn_group
                toggleField('#mb_sc_layout', is_mn_group);
                
                // column: show-inlist show-ingrid, hide-incarousel hide-intable
                toggleField('#mb_column', (is_list || is_grid) && !is_carousel && !is_table);
                
                // sc_heading: show-mn_group
                toggleField('#mb_sc_heading', is_mn_group);
                
                // posts_per_page: show-intable show-inlist show-ingrid, hide-incarousel
                toggleField('#mb_posts_per_page', (is_table || is_list || is_grid) && !is_carousel);
                
                // slidesshow: show-incarousel hide-mn_group
                toggleField('#mb_slidesshow', is_carousel && !is_mn_group);
                
                // ids: hide-mn_group
                toggleField('#mb_ids', !is_mn_group);
                
                // page_navi: show-intable show-inlist show-ingrid hide-incarousel
                toggleField('#mb_page_navi', (is_table || is_list || is_grid) && !is_carousel);
                
                // menu_filter: show-intable show-inlist show-ingrid hide-mn_group hide-incarousel
                toggleField('#mb_menu_filter', (is_table || is_list || is_grid) && !is_mn_group && !is_carousel);
                
                // show_count: show-intable show-inlist show-ingrid show-mn_group hide-incarousel
                toggleField('#mb_show_count', (is_table || is_list || is_grid || is_mn_group) && !is_carousel);
                
                // filter_style: show-intable show-inlist show-ingrid hide-mn_group hide-incarousel
                toggleField('#mb_filter_style', (is_table || is_list || is_grid) && !is_mn_group && !is_carousel);
                
                // active_filter: show-intable show-inlist show-ingrid hide-mn_group hide-incarousel
                toggleField('#mb_active_filter', (is_table || is_list || is_grid) && !is_mn_group && !is_carousel);
                
                // order_cat: show-intable show-inlist show-ingrid hide-incarousel
                toggleField('#mb_order_cat', (is_table || is_list || is_grid) && !is_carousel);
                
                // hide_ftall: show-intable show-inlist show-ingrid hide-mn_group hide-incarousel
                toggleField('#mb_hide_ftall', (is_table || is_list || is_grid) && !is_mn_group && !is_carousel);
                
                // menu_pos: show-inlist hide-intable hide-ingrid hide-mn_group hide-incarousel
                toggleField('#mb_menu_pos', is_list && !is_table && !is_grid && !is_mn_group && !is_carousel);
                
                // enable_search: hide-mn_group hide-incarousel
                toggleField('#mb_enable_search', !is_mn_group && !is_carousel);
                
                // live_sort: show-intable hide-inlist hide-ingrid hide-mn_group hide-incarousel
                toggleField('#mb_live_sort', is_table && !is_list && !is_grid && !is_mn_group && !is_carousel);
                
                // autoplay, autoplayspeed, loading_effect, infinite: show-incarousel hide-mn_group
                var showCarouselOpts = is_carousel && !is_mn_group;
                toggleField('#mb_autoplay', showCarouselOpts);
                toggleField('#mb_autoplayspeed', showCarouselOpts);
                toggleField('#mb_loading_effect', showCarouselOpts);
                toggleField('#mb_infinite', showCarouselOpts);
            
                // Toggle sections based on child visibility
                var checkSection = function(id) {
                    var $sec = $(id);
                    if ($sec.length) {
                        var hasVisibleFields = false;
                        $sec.find('.o100-menu-field').each(function(){
                            if ($(this).css('display') !== 'none') hasVisibleFields = true;
                        });
                        if (hasVisibleFields) { $sec.show(); } else { $sec.hide(); }
                    }
                };
                checkSection('#section_filter');
                checkSection('#section_carousel');
}

            // Initialize Select2 for product search
            function initProductSelect2() {
                if (typeof $.fn.select2 !== 'undefined' || typeof $.fn.selectWoo !== 'undefined') {
                    var selectFn = $.fn.selectWoo ? 'selectWoo' : 'select2';
                    $('#mb_ids')[selectFn]({
                        minimumInputLength: 2,
                        placeholder: "Search for products...",
                        allowClear: true,
                        closeOnSelect: false,
                        ajax: {
                            url: ajaxurl,
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term,
                                    action: 'o100_mb_search_products',
                                    nonce: (typeof o100Settings !== 'undefined') ? o100Settings.adminNonce : ''
                                };
                            },
                            processResults: function (data) {
                                var terms = [];
                                if (data.success && data.data) {
                                    $.each(data.data, function (id, text) {
                                        terms.push({ id: id, text: text });
                                    });
                                }
                                return { results: terms };
                            },
                            cache: true
                        },
                        minimumInputLength: 2,
                        closeOnSelect: false,
                        templateResult: function(result) {
                            if (result.loading) return result.text;
                            var selectedIds = $('#mb_ids').val() || [];
                            var isSelected = (selectedIds.indexOf(result.id.toString()) !== -1 || selectedIds.indexOf(result.id) !== -1);
                            
                            if (isSelected) {
                                setTimeout(function() {
                                    $('.o100-select2-item[data-id="'+result.id+'"]').closest('li').addClass('o100-disabled-li');
                                }, 0);
                            }
                            
                            return jQuery('<div class="o100-select2-item" data-id="' + result.id + '" style="display:flex; align-items:center;"><span class="o100-custom-cb"></span><span>' + result.text + '</span></div>');
                        },
                        templateSelection: function(result) {
                            return result.text;
                        }
                    }).on('select2:select select2:unselect', function(e) {
                        var id = e.params.data.id;
                        var $li = $('.o100-select2-item[data-id="'+id+'"]').closest('li');
                        if (e.type === 'select2:select') {
                            $li.addClass('o100-disabled-li');
                        } else {
                            $li.removeClass('o100-disabled-li');
                        }
                    });
                }
            }
            initProductSelect2();

            $('#mb_sc_type, #mb_sc_layout').on('change', function() {
                updateFieldVisibility();
            });

            $(document).on('click', '.o100-edit-menu', function(e) {
                e.preventDefault();
                var mId = $(this).data('id');
                var conf = window.o100_menus_data[mId];
                if (!conf) return;

                $('#o100-menu-modal-title').text('Edit Menu: ' + mId);
                $('#mb_id').val(mId);
                $('#mb_original_id').val(mId);
                var sc = '[order100_food_menu id="' + mId + '"]';
                $('#o100-menu-modal-sc-display').css('display', 'flex').find('.sc-text').text(sc);

                
                $('#mb_sc_type').val(conf.sc_type || 'grid');
                $('#mb_sc_layout').val(conf.sc_layout || 'grid');
                $('#mb_style').val(conf.style || '1');
                $('#mb_column').val(conf.column || '2');
                $('#mb_column_tablet').val(conf.column_tablet || '2');
                $('#mb_column_mobile').val(conf.column_mobile || '1');
                $('#mb_sc_heading').val(conf.sc_heading || '');
                $('#mb_img_size').val(conf.img_size || '');
                $('#mb_class').val(conf.class || '');
                
                $('#mb_count').val(conf.count || '');
                $('#mb_posts_per_page').val(conf.posts_per_page || '');
                $('#mb_slidesshow').val(conf.slidesshow || '');
                
                // Pre-fill Select2 for specific product IDs
                $('#mb_ids').empty();
                if (conf.ids) {
                    $.ajax({
                        url: ajaxurl,
                        data: {
                            action: 'o100_get_product_titles',
                            ids: conf.ids
                        },
                        success: function(res) {
                            if (res.success && res.data) {
                                $.each(res.data, function(id, text) {
                                    var option = new Option(text, id, true, true);
                                    $('#mb_ids').append(option);
                                });
                                $('#mb_ids').trigger('change');
                            }
                        }
                    });
                } else {
                    $('#mb_ids').trigger('change');
                }
                
                $('.mb-cat-checkbox').prop('checked', false);
                if (conf.cat) {
                    var cats = conf.cat.split(',');
                    $.each(cats, function(i, val) {
                        $('.mb-cat-checkbox[value="' + $.trim(val) + '"]').prop('checked', true);
                    });
                }
                $('#mb_cat').val(conf.cat || '');
                
                $('#mb_order').val(conf.order || 'DESC');
                $('#mb_orderby').val(conf.orderby || '');
                $('#mb_meta_key').val(conf.meta_key || '');
                $('#mb_meta_value').val(conf.meta_value || '');
                $('#mb_number_excerpt').val(conf.number_excerpt || '');
                $('#mb_page_navi').val(conf.page_navi || '');
                $('#mb_featured').val(conf.featured || '');
                
                $('#mb_menu_filter').val(conf.menu_filter || 'hide');
                $('#mb_show_count').val(conf.show_count || '');
                $('#mb_filter_style').val(conf.filter_style || '');
                $('#mb_active_filter').val(conf.active_filter || '');
                $('#mb_order_cat').val(conf.order_cat || '');
                $('#mb_hide_ftall').val(conf.hide_ftall || '');
                $('#mb_menu_pos').val(conf.menu_pos || '');
                $('#mb_cart_enable').val(conf.cart_enable || '');
                $('#mb_enable_search').val(conf.enable_search || '');
                $('#mb_live_sort').val(conf.live_sort || '');
                $('#mb_autoplay').val(conf.autoplay || '');
                $('#mb_autoplayspeed').val(conf.autoplayspeed || '');
                $('#mb_loading_effect').val(conf.loading_effect || '');
                $('#mb_infinite').val(conf.infinite || '');
                $('#mb_enable_modal').val(conf.enable_modal || '');
                $('#mb_hide_atc').val(conf.hide_atc || '');
                $('#mb_enable_mtod').val(conf.enable_mtod || '');
                $('#mb_photos_first').val(conf.photos_first || '');
                
                updateFieldVisibility();
                $('.o100-menu-tab[data-tab="layout"]').click();
                $('.o100-menu-modal-overlay').css('display', 'flex');
            });

            $('.o100-add-menu-btn').on('click', function(e) {
                e.preventDefault();
                $('#o100-menu-modal-title').text('Create a new Menu');
                $('#o100-menu-modal-sc-display').hide();
                $('#mb_id, #mb_original_id, #mb_cat, #mb_posts_per_page, #mb_slidesshow, #mb_meta_key, #mb_meta_value, #mb_number_excerpt, #mb_active_filter, #mb_img_size, #mb_class, #mb_autoplayspeed').val('');
                $('#mb_ids').empty().trigger('change');
                $('.mb-cat-checkbox').prop('checked', false);
                $('#mb_count').val('9');
                $('#mb_sc_type').val('mn_group');
                $('#mb_sc_layout').val('list');
                $('#mb_style').val('1');
                $('#mb_column').val('2');
                $('#mb_column_tablet').val('2');
                $('#mb_column_mobile').val('1');
                $('#mb_sc_heading').val('');
                $('#mb_order').val('DESC');
                $('#mb_orderby').val('');
                $('#mb_page_navi').val('');
                $('#mb_featured').val('');
                $('#mb_menu_filter').val('hide');
                $('#mb_show_count').val('');
                $('#mb_filter_style').val('');
                $('#mb_order_cat').val('');
                $('#mb_hide_ftall').val('');
                $('#mb_menu_pos').val('top');
                $('#mb_cart_enable').val('');
                $('#mb_enable_search').val('');
                $('#mb_live_sort').val('');
                $('#mb_autoplay').val('');
                $('#mb_loading_effect').val('');
                $('#mb_infinite').val('');
                $('#mb_enable_modal').val('');
                $('#mb_hide_atc').val('');
                $('#mb_enable_mtod').val('');
                $('#mb_photos_first').val('');
                
                updateFieldVisibility();
                $('.o100-menu-tab[data-tab="layout"]').click();
                $('.o100-menu-modal-overlay').css('display', 'flex');
            });

            $('.o100-close-menu-modal').on('click', function() {
                $('.o100-menu-modal-overlay').hide();
            });

            $('.o100-save-menu-action').on('click', function(e) {
                e.preventDefault();
                var selectedCats = [];
                $('.mb-cat-checkbox:checked').each(function() {
                    selectedCats.push($(this).val());
                });
                $('#mb_cat').val(selectedCats.join(','));
                
                var $btn = $(this);
                var id = $('#mb_id').val().trim();
                var orig_id = $('#mb_original_id').val().trim();

                if (!id) {
                    alert('Shortcode ID is required.');
                    $('#mb_id').focus();
                    return;
                }
                
                // Strip quotes, brackets and spaces to ensure safe shortcodes, but allow multi-byte characters like Chinese
                id = id.replace(/["'\[\]\s]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
                if (id !== $('#mb_id').val().trim()) {
                    $('#mb_id').val(id);
                }

                var data = {
                    action: 'o100_save_menu_config',
                    original_id: orig_id,
                    id: id,
                    sc_type: $('#mb_sc_type').val(),
                    sc_layout: $('#mb_sc_layout').val(),
                    style: $('#mb_style').val(),
                    column: $('#mb_column').val(),
                    column_tablet: $('#mb_column_tablet').val(),
                    column_mobile: $('#mb_column_mobile').val(),
                    sc_heading: $('#mb_sc_heading').val(),
                    img_size: $('#mb_img_size').val(),
                    class: $('#mb_class').val(),
                    
                    count: $('#mb_count').val(),
                    posts_per_page: $('#mb_posts_per_page').val(),
                    slidesshow: $('#mb_slidesshow').val(),
                    ids: $('#mb_ids').val() ? $('#mb_ids').val().join(',') : '',
                    cat: $('#mb_cat').val(),
                    order: $('#mb_order').val(),
                    orderby: $('#mb_orderby').val(),
                    meta_key: $('#mb_meta_key').val(),
                    meta_value: $('#mb_meta_value').val(),
                    number_excerpt: $('#mb_number_excerpt').val(),
                    page_navi: $('#mb_page_navi').val(),
                    featured: $('#mb_featured').val(),
                    
                    menu_filter: $('#mb_menu_filter').val(),
                    show_count: $('#mb_show_count').val(),
                    filter_style: $('#mb_filter_style').val(),
                    active_filter: $('#mb_active_filter').val(),
                    order_cat: $('#mb_order_cat').val(),
                    hide_ftall: $('#mb_hide_ftall').val(),
                    menu_pos: $('#mb_menu_pos').val(),
                    cart_enable: $('#mb_cart_enable').val(),
                    enable_search: $('#mb_enable_search').val(),
                    live_sort: $('#mb_live_sort').val(),
                    autoplay: $('#mb_autoplay').val(),
                    autoplayspeed: $('#mb_autoplayspeed').val(),
                    loading_effect: $('#mb_loading_effect').val(),
                    infinite: $('#mb_infinite').val(),
                    enable_modal: $('#mb_enable_modal').val(),
                    hide_atc: $('#mb_hide_atc').val(),
                    enable_mtod: $('#mb_enable_mtod').val(),
                    photos_first: $('#mb_photos_first').val()
                };

                $btn.text('Saving...').prop('disabled', true);

                $.post(ajaxurl, data, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $btn.text('Saved!').css({'background':'#16a34a','border-color':'#16a34a'});
                        $('#mb_original_id').val(id);
                        var savedSc = '[order100_food_menu id="' + id + '"]';
                        $('#o100-menu-modal-sc-display').css('display', 'flex').find('.sc-text').text(savedSc);
                        loadMenus();
                    } else {
                        $btn.text('<?php esc_html_e( 'Save Menu Configuration', 'order100' ); ?>');
                        alert(response.data || 'Error saving menu');
                    }
                });
            });

            // Revert save button to original style when any field changes
            $(document).on('change input', '.o100-menu-modal-content .o100-menu-input, .o100-menu-modal-content .mb-cat-checkbox', function() {
                var $btn = $('.o100-save-menu-action');
                if ($btn.text() === 'Saved!') {
                    $btn.text('<?php esc_html_e( 'Save Menu Configuration', 'order100' ); ?>').css({'background':'#3b82f6','border-color':'#3b82f6'});
                }
            });
        });
        </script>
        <?php
    }

    public function ajax_get_menu_configs() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        $configs = get_option( 'o100_menu_shortcodes', array() );
        wp_send_json_success( $configs );
    }

    public function ajax_save_menu_config() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        
        $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        $orig_id = isset( $_POST['original_id'] ) ? sanitize_text_field( wp_unslash( $_POST['original_id'] ) ) : '';
        
        if ( empty( $id ) ) wp_send_json_error( 'ID is required' );

        $configs = get_option( 'o100_menu_shortcodes', array() );
        if ( ! is_array( $configs ) ) $configs = array();

        // If ID changed, delete old one
        if ( ! empty( $orig_id ) && $orig_id !== $id && isset( $configs[ $orig_id ] ) ) {
            unset( $configs[ $orig_id ] );
        }

        // Map shortcode builder values exactly as they expect
        $configs[ $id ] = array(
            'sc_type'        => isset($_POST['sc_type']) ? sanitize_text_field($_POST['sc_type']) : 'mn_group',
            'sc_layout'      => isset($_POST['sc_layout']) ? sanitize_text_field($_POST['sc_layout']) : 'list',
            'style'          => isset($_POST['style']) ? sanitize_text_field($_POST['style']) : '1',
            'sc_heading'     => isset($_POST['sc_heading']) ? sanitize_text_field($_POST['sc_heading']) : '',
            'column'         => isset($_POST['column']) ? sanitize_text_field($_POST['column']) : '2',
            'column_tablet'  => isset($_POST['column_tablet']) ? sanitize_text_field($_POST['column_tablet']) : '2',
            'column_mobile'  => isset($_POST['column_mobile']) ? sanitize_text_field($_POST['column_mobile']) : '1',
            'count'          => isset($_POST['count']) ? sanitize_text_field($_POST['count']) : '9',
            'posts_per_page' => isset($_POST['posts_per_page']) ? sanitize_text_field($_POST['posts_per_page']) : '',
            'slidesshow'     => isset($_POST['slidesshow']) ? sanitize_text_field($_POST['slidesshow']) : '',
            'filter_style'   => isset($_POST['filter_style']) ? sanitize_text_field($_POST['filter_style']) : '',
            'cat'            => isset($_POST['cat']) ? sanitize_text_field($_POST['cat']) : '',
            'order'          => isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC',
            'orderby'        => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : '',
            'meta_key'       => isset($_POST['meta_key']) ? sanitize_text_field($_POST['meta_key']) : '',
            'meta_value'     => isset($_POST['meta_value']) ? sanitize_text_field($_POST['meta_value']) : '',
            'menu_filter'    => isset($_POST['menu_filter']) ? sanitize_text_field($_POST['menu_filter']) : 'no',
            'enable_search'  => isset($_POST['enable_search']) ? sanitize_text_field($_POST['enable_search']) : 'no',
            'show_count'     => isset($_POST['show_count']) ? sanitize_text_field($_POST['show_count']) : '',
            'order_cat'      => isset($_POST['order_cat']) ? sanitize_text_field($_POST['order_cat']) : '',
            'active_filter'  => isset($_POST['active_filter']) ? sanitize_text_field($_POST['active_filter']) : '',
            'hide_ftall'     => isset($_POST['hide_ftall']) ? sanitize_text_field($_POST['hide_ftall']) : '',
            'featured'       => isset($_POST['featured']) ? sanitize_text_field($_POST['featured']) : '',
            'live_sort'      => isset($_POST['live_sort']) ? sanitize_text_field($_POST['live_sort']) : '',
            'enable_modal'   => isset($_POST['enable_modal']) ? sanitize_text_field($_POST['enable_modal']) : 'yes',
            'cart_enable'    => isset($_POST['cart_enable']) ? sanitize_text_field($_POST['cart_enable']) : 'yes',
            'hide_atc'       => isset($_POST['hide_atc']) ? sanitize_text_field($_POST['hide_atc']) : '',
            'enable_mtod'    => isset($_POST['enable_mtod']) ? sanitize_text_field($_POST['enable_mtod']) : '',
            'page_navi'      => isset($_POST['page_navi']) ? sanitize_text_field($_POST['page_navi']) : '',
            'menu_pos'       => isset($_POST['menu_pos']) ? sanitize_text_field($_POST['menu_pos']) : '',
            'img_size'       => isset($_POST['img_size']) ? sanitize_text_field($_POST['img_size']) : '',
            'number_excerpt' => isset($_POST['number_excerpt']) ? sanitize_text_field($_POST['number_excerpt']) : '',
            'class'          => isset($_POST['class']) ? sanitize_text_field($_POST['class']) : '',
            'autoplay'       => isset($_POST['autoplay']) ? sanitize_text_field($_POST['autoplay']) : '',
            'autoplayspeed'  => isset($_POST['autoplayspeed']) ? sanitize_text_field($_POST['autoplayspeed']) : '',
            'loading_effect' => isset($_POST['loading_effect']) ? sanitize_text_field($_POST['loading_effect']) : '',
            'infinite'       => isset($_POST['infinite']) ? sanitize_text_field($_POST['infinite']) : '',
            'photos_first'   => isset($_POST['photos_first']) ? sanitize_text_field($_POST['photos_first']) : '',
            
            // To ensure compatibility with class-o100-menu-renderer.php mappings:
            'type'           => isset($_POST['sc_type']) ? sanitize_text_field($_POST['sc_type']) : 'mn_group',
            'inner_layout'   => isset($_POST['sc_layout']) ? sanitize_text_field($_POST['sc_layout']) : 'list',
            'categories'     => isset($_POST['cat']) ? sanitize_text_field($_POST['cat']) : '',
            'columns'        => isset($_POST['column']) ? sanitize_text_field($_POST['column']) : '2',
        );

        update_option( 'o100_menu_shortcodes', $configs );
        wp_send_json_success( $configs[ $id ] );
    }

    public function ajax_delete_menu_config() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        
        $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        if ( empty( $id ) ) wp_send_json_error( 'ID is required' );

        $configs = get_option( 'o100_menu_shortcodes', array() );
        if ( isset( $configs[ $id ] ) ) {
            unset( $configs[ $id ] );
            update_option( 'o100_menu_shortcodes', $configs );
        }
        
        wp_send_json_success();
    }

    public function ajax_search_products() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        if ( isset( $_GET['nonce'] ) ) {
            check_ajax_referer( 'o100_admin_nonce', 'nonce' );
        }

        $term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
        if ( empty( $term ) ) {
            wp_send_json_success( array() );
        }

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            's'              => $term,
            'fields'         => 'ids',
        );

        $query = new WP_Query( $args );
        $results = array();

        if ( ! empty( $query->posts ) ) {
            foreach ( $query->posts as $post_id ) {
                $product = wc_get_product( $post_id );
                if ( $product ) {
                    $results[ $post_id ] = $product->get_formatted_name();
                }
            }
        }

        wp_send_json_success( $results );
    }

    public function ajax_get_product_titles() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $ids_string = isset( $_GET['ids'] ) ? sanitize_text_field( wp_unslash( $_GET['ids'] ) ) : '';
        if ( empty( $ids_string ) ) {
            wp_send_json_success( array() );
        }

        $ids = array_map( 'intval', explode( ',', $ids_string ) );
        $results = array();

        foreach ( $ids as $id ) {
            $product = wc_get_product( $id );
            if ( $product ) {
                $results[ $id ] = $product->get_formatted_name();
            }
        }

        wp_send_json_success( $results );
    }
}

O100_Menu_Builder_Tab::instance();
