<?php
namespace Order100\Notification\Engine\Admin;

use Order100\Notification\Engine\Models\TemplateModel;

/**
 * Adds a "Custom Template" column to WooCommerce's Settings > Emails table,
 * showing whether our notification engine has an active template that overrides
 * the default WooCommerce email for each row.
 */
class WCEmailColumnIntegration {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'woocommerce_email_setting_columns', [ $this, 'add_column' ] );
        add_action( 'woocommerce_email_setting_column_o100ne_template', [ $this, 'render_column' ] );
    }

    /**
     * Insert our column before the "actions" column.
     */
    public function add_column( $columns ) {
        $new_columns = [];
        foreach ( $columns as $key => $label ) {
            if ( $key === 'actions' ) {
                $new_columns['o100ne_template'] = __( 'Custom Template', 'order100' );
            }
            $new_columns[ $key ] = $label;
        }
        // Fallback: if 'actions' wasn't found, append at end
        if ( ! isset( $new_columns['o100ne_template'] ) ) {
            $new_columns['o100ne_template'] = __( 'Custom Template', 'order100' );
        }
        return $new_columns;
    }

    /**
     * Render the column content for each email row.
     *
     * @param \WC_Email $email
     */
    public function render_column( $email ) {
        $email_id = $email->id;

        // Map WooCommerce email IDs to our template names
        $template_name = $this->get_template_name( $email_id );

        echo '<td class="wc-email-settings-table-o100ne_template">';

        if ( ! $template_name ) {
            echo '<span style="color:#94a3b8;">—</span>';
            echo '</td>';
            return;
        }

        $template_data = TemplateModel::get_short_data_by_name( $template_name );

        if ( $template_data && isset( $template_data['status'] ) && $template_data['status'] === 'active' ) {
            // Active custom template
            $edit_url = admin_url( 'admin.php?page=order100&tab=notifications#/email-editor/' . $template_name );
            echo '<span style="display:inline-flex;align-items:center;gap:4px;">';
            echo '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;"></span>';
            echo '<a href="' . esc_url( $edit_url ) . '" style="color:#16a34a;font-weight:500;text-decoration:none;font-size:12px;">';
            echo esc_html__( 'Active', 'order100' );
            echo '</a>';
            echo '</span>';
        } elseif ( $template_data && isset( $template_data['id'] ) ) {
            // Template exists but inactive
            $edit_url = admin_url( 'admin.php?page=order100&tab=notifications#/email-editor/' . $template_name );
            echo '<span style="display:inline-flex;align-items:center;gap:4px;">';
            echo '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#94a3b8;"></span>';
            echo '<a href="' . esc_url( $edit_url ) . '" style="color:#64748b;text-decoration:none;font-size:12px;">';
            echo esc_html__( 'Inactive', 'order100' );
            echo '</a>';
            echo '</span>';
        } else {
            // No template configured
            echo '<span style="color:#94a3b8;font-size:12px;">' . esc_html__( 'Not configured', 'order100' ) . '</span>';
        }

        echo '</td>';
    }

    /**
     * Map WooCommerce email ID to our notification engine template name.
     *
     * @param string $wc_email_id
     * @return string|null
     */
    private function get_template_name( $wc_email_id ) {
        // Our email classes use the WC email ID as the template name
        $map = [
            // Admin emails
            'new_order'                       => 'new_order',
            'cancelled_order'                 => 'cancelled_order',
            'failed_order'                    => 'failed_order',
            // Customer emails
            'customer_on_hold_order'          => 'customer_on_hold_order',
            'customer_processing_order'       => 'customer_processing_order',
            'customer_completed_order'        => 'customer_completed_order',
            'customer_refunded_order'         => 'customer_refunded_order',
            'customer_partially_refunded_order' => 'customer_refunded_order',
            'customer_invoice'                => 'customer_invoice',
            'customer_note'                   => 'customer_note',
            'customer_reset_password'         => 'customer_reset_password',
            'customer_new_account'            => 'customer_new_account',
            // POS emails
            'customer_pos_completed_order'    => 'customer_pos_completed_order',
            'customer_pos_refunded_order'     => 'customer_pos_refunded_order',
        ];

        return $map[ $wc_email_id ] ?? null;
    }
}


// TS: 20260429003914
