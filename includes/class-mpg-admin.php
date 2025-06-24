<?php
/**
 * Admin functionality for Manual Payment Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class MPG_Admin {
    
    private $screen_option;
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        add_action('wp_ajax_mpg_update_payment_status', array($this, 'update_payment_status'));
        add_action('wp_ajax_mpg_save_hidden_columns', array($this, 'save_hidden_columns'));
        add_filter('set-screen-option', array($this, 'set_screen_options'), 10, 3);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $hook = add_submenu_page(
            'woocommerce',
            __('Manual Payment Logs', 'manual-payment-gateway'),
            __('Payment Logs', 'manual-payment-gateway'),
            'manage_woocommerce',
            'mpg-payment-logs',
            array($this, 'payment_logs_page')
        );
        
        // Add screen options
        add_action("load-$hook", array($this, 'screen_option'));
    }
    
    /**
     * Screen options
     */
    public function screen_option() {
        $option = 'per_page';
        $args = [
            'label' => __('Payment Logs per page', 'manual-payment-gateway'),
            'default' => 50,
            'option' => 'mpg_payment_logs_per_page'
        ];
        
        add_screen_option($option, $args);
        
        // Add column options
        add_filter('screen_settings', array($this, 'add_column_options'), 10, 2);
        
        $this->screen_option = $option;
    }
    
    /**
     * Set screen options
     */
    public function set_screen_options($status, $option, $value) {
        if ('mpg_payment_logs_per_page' == $option) {
            return $value;
        }
        return $status;
    }
    
    /**
     * Add column options to screen options
     */
    public function add_column_options($settings, $screen) {
        if ($screen->id !== get_current_screen()->id) {
            return $settings;
        }
        
        // Get hidden columns from user meta
        $hidden_columns = get_user_meta(get_current_user_id(), 'managempg-payment-logscolumnshidden', true);
        if (!is_array($hidden_columns)) {
            $hidden_columns = array();
        }
        
        $columns = array(
            'mpg_id' => __('ID', 'manual-payment-gateway'),
            'mpg_order_id' => __('Order ID', 'manual-payment-gateway'),
            'mpg_order_status' => __('Order Status', 'manual-payment-gateway'),
            'mpg_transaction_id' => __('Transaction ID', 'manual-payment-gateway'),
            'mpg_file' => __('File', 'manual-payment-gateway'),
            'mpg_user' => __('User', 'manual-payment-gateway'),
            'mpg_payment_status' => __('Payment Status', 'manual-payment-gateway'),
            'mpg_date' => __('Date', 'manual-payment-gateway'),
            'mpg_actions' => __('Actions', 'manual-payment-gateway')
        );
        
        $column_options = '<fieldset><legend>' . __('Columns', 'manual-payment-gateway') . '</legend>';
        
        foreach ($columns as $column_key => $column_name) {
            $checked = !in_array($column_key, $hidden_columns) ? 'checked="checked"' : '';
            $column_options .= '<label>';
            $column_options .= '<input type="checkbox" name="' . $column_key . '" value="' . $column_key . '" ' . $checked . '>';
            $column_options .= ' ' . $column_name;
            $column_options .= '</label>';
        }
        
        $column_options .= '</fieldset>';
        
        // Add JavaScript to handle column toggles
        $column_options .= '<script type="text/javascript">
        jQuery(document).ready(function($) {
            $("#screen-options-wrap input[type=checkbox]").change(function() {
                var column = $(this).val();
                var checked = $(this).is(":checked");
                
                if (checked) {
                    $(".mpg-table ." + column).show();
                } else {
                    $(".mpg-table ." + column).hide();
                }
                
                // Save hidden columns via AJAX
                var hiddenColumns = [];
                $("#screen-options-wrap input[type=checkbox]:not(:checked)").each(function() {
                    hiddenColumns.push($(this).val());
                });
                
                $.post(ajaxurl, {
                    action: "mpg_save_hidden_columns",
                    hidden_columns: hiddenColumns,
                    nonce: "' . wp_create_nonce('mpg_save_columns') . '"
                });
            });
            
            // Apply hidden columns on page load
            var hiddenColumns = ' . json_encode($hidden_columns) . ';
            hiddenColumns.forEach(function(column) {
                $(".mpg-table ." + column).hide();
            });
        });
        </script>';
        
        return $settings . $column_options;
    }
    
    /**
     * Payment logs page
     */
    public function payment_logs_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpg_payment_logs';
        
        // Handle status updates
        if (isset($_POST['update_status']) && wp_verify_nonce($_POST['mpg_nonce'], 'mpg_update_status')) {
            $log_id = intval($_POST['log_id']);
            $new_status = sanitize_text_field($_POST['new_status']);
            
            // Get the payment log to retrieve order ID
            $payment_log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $log_id));
            
            if ($payment_log) {
                // Update payment log status
                $wpdb->update(
                    $table_name,
                    array('status' => $new_status),
                    array('id' => $log_id),
                    array('%s'),
                    array('%d')
                );
                
                // Update WooCommerce order status based on payment approval (if enabled)
                $sync_result = $this->sync_order_status($payment_log->order_id, $new_status);
                
                if ($sync_result) {
                    echo '<div class="notice notice-success"><p>' . __('Status updated successfully and order status synchronized.', 'manual-payment-gateway') . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Payment status updated successfully. Order status synchronization is disabled.', 'manual-payment-gateway') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . __('Payment log not found.', 'manual-payment-gateway') . '</p></div>';
            }
        }
        
        // Get pagination settings
        $user = get_current_user_id();
        $screen = get_current_screen();
        
        // Check if per_page is set in URL parameter
        if (isset($_GET['per_page']) && is_numeric($_GET['per_page'])) {
            $per_page = intval($_GET['per_page']);
            // Save the user preference
            update_user_meta($user, $screen->get_option('per_page', 'option'), $per_page);
        } else {
            // Get from user meta or default
            $per_page = get_user_meta($user, $screen->get_option('per_page', 'option'), true);
            if (empty($per_page) || $per_page < 1) {
                $per_page = $screen->get_option('per_page', 'default');
            }
        }
        
        // Ensure per_page is within valid range
        $valid_per_page_values = array(10, 20, 50, 100, 200);
        if (!in_array($per_page, $valid_per_page_values)) {
            $per_page = 50; // Default fallback
        }
        
        // Get current page
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Get logs with pagination
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Manual Payment Logs', 'manual-payment-gateway'); ?></h1>
            
            <?php 
            $gateway = new MPG_Gateway();
            $auto_sync_enabled = $gateway->is_auto_sync_enabled();
            ?>
            <div class="notice <?php echo $auto_sync_enabled ? 'notice-info' : 'notice-warning'; ?>">
                <p><strong><?php _e('Order Status Synchronization:', 'manual-payment-gateway'); ?></strong> 
                <?php if ($auto_sync_enabled): ?>
                    <?php _e('When you approve a payment, the corresponding WooCommerce order status will automatically change from "On Hold" to "Processing". When you reject a payment, the order status will change to "Cancelled".', 'manual-payment-gateway'); ?>
                <?php else: ?>
                    <?php _e('Automatic order status synchronization is currently DISABLED. Order status will remain "On Hold" and must be changed manually. You can enable this feature in WooCommerce > Settings > Payments > Manual Payment Gateway.', 'manual-payment-gateway'); ?>
                <?php endif; ?>
                </p>
            </div>
            
            <!-- Top table navigation -->
            <div class="tablenav top">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total_items, 'manual-payment-gateway'), number_format_i18n($total_items)); ?></span>
                    <?php if ($total_items > 0): ?>
                        <span class="pagination-links">
                            <label for="mpg-per-page" class="screen-reader-text"><?php _e('Number of items per page:', 'manual-payment-gateway'); ?></label>
                            <select name="mpg-per-page" id="mpg-per-page" onchange="mpgChangePerPage(this.value)">
                                <option value="10" <?php selected($per_page, 10); ?>>10</option>
                                <option value="20" <?php selected($per_page, 20); ?>>20</option>
                                <option value="50" <?php selected($per_page, 50); ?>>50</option>
                                <option value="100" <?php selected($per_page, 100); ?>>100</option>
                                <option value="200" <?php selected($per_page, 200); ?>>200</option>
                            </select>
                            <?php _e('items per page', 'manual-payment-gateway'); ?>
                            
                            <?php if ($total_items > $per_page): ?>
                                <?php
                                $total_pages = ceil($total_items / $per_page);
                                
                                $pagination_args = array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => __('&laquo;'),
                                    'next_text' => __('&raquo;'),
                                    'total' => $total_pages,
                                    'current' => $current_page
                                );
                                
                                echo paginate_links($pagination_args);
                                ?>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped mpg-table">
                <thead>
                    <tr>
                        <th class="mpg_id"><?php _e('ID', 'manual-payment-gateway'); ?></th>
                        <th class="mpg_order_id"><?php _e('Order ID', 'manual-payment-gateway'); ?></th>
                        <th class="mpg_order_status"><?php _e('Order Status', 'manual-payment-gateway'); ?></th>
                        <th class="mpg_transaction_id"><?php _e('Transaction ID', 'manual-payment-gateway'); ?></th>
                        <th class="mpg_file"><?php _e('File', 'manual-payment-gateway'); ?></th>
                        <th class="mpg_user"><?php _e('User', 'manual-payment-gateway'); ?></th>
                        <th class="mpg_payment_status"><?php _e('Payment Status', 'manual-payment-gateway'); ?></th>
                        <th class="mpg_date"><?php _e('Date', 'manual-payment-gateway'); ?></th>
                        <th class="mpg_actions"><?php _e('Actions', 'manual-payment-gateway'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="9"><?php _e('No payment logs found.', 'manual-payment-gateway'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <?php 
                        $order = wc_get_order($log->order_id);
                        $order_status = $order ? $order->get_status() : 'unknown';
                        $order_status_name = $order ? wc_get_order_status_name($order_status) : __('Unknown', 'manual-payment-gateway');
                        ?>
                        <tr>
                            <td class="mpg_id"><?php echo $log->id; ?></td>
                            <td class="mpg_order_id">
                                <a href="<?php echo admin_url('post.php?post=' . $log->order_id . '&action=edit'); ?>">
                                    #<?php echo $log->order_id; ?>
                                </a>
                            </td>
                            <td class="mpg_order_status">
                                <span class="order-status-<?php echo esc_attr($order_status); ?>">
                                    <?php echo esc_html($order_status_name); ?>
                                </span>
                            </td>
                            <td class="mpg_transaction_id"><?php echo esc_html($log->transaction_id); ?></td>
                            <td class="mpg_file">
                                <?php if ($log->file_path): ?>
                                    <a href="<?php echo esc_url($log->file_path); ?>" target="_blank">
                                        <?php echo esc_html($log->file_name); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="mpg_user">
                                <?php 
                                $user_data = get_user_by('id', $log->user_id);
                                echo $user_data ? esc_html($user_data->display_name) : __('Guest', 'manual-payment-gateway');
                                ?>
                            </td>
                            <td class="mpg_payment_status">
                                <span class="status-<?php echo esc_attr($log->status); ?>">
                                    <?php echo ucfirst(esc_html($log->status)); ?>
                                </span>
                            </td>
                            <td class="mpg_date"><?php echo date('Y-m-d H:i:s', strtotime($log->created_at)); ?></td>
                            <td class="mpg_actions">
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('mpg_update_status', 'mpg_nonce'); ?>
                                    <input type="hidden" name="log_id" value="<?php echo $log->id; ?>" />
                                    <select name="new_status">
                                        <option value="pending" <?php selected($log->status, 'pending'); ?>>Pending</option>
                                        <option value="approved" <?php selected($log->status, 'approved'); ?>>Approved</option>
                                        <option value="rejected" <?php selected($log->status, 'rejected'); ?>>Rejected</option>
                                    </select>
                                    <input type="submit" name="update_status" value="Update" class="button button-small" />
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_items > $per_page): ?>
            <!-- Bottom table navigation -->
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total_items, 'manual-payment-gateway'), number_format_i18n($total_items)); ?></span>
                    <?php
                    $total_pages = ceil($total_items / $per_page);
                    
                    $pagination_args = array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $current_page
                    );
                    
                    echo paginate_links($pagination_args);
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Add meta box to order edit page
     */
    public function add_order_meta_box() {
        add_meta_box(
            'mpg-payment-details',
            __('Manual Payment Details', 'manual-payment-gateway'),
            array($this, 'order_meta_box_content'),
            'shop_order',
            'normal',
            'default'
        );
    }
    
    /**
     * Order meta box content
     */
    public function order_meta_box_content($post) {
        $order = wc_get_order($post->ID);
        
        if ($order->get_payment_method() !== 'manual_payment_gateway') {
            return;
        }
        
        $files = $order->get_meta('_mpg_files');
        $transaction_id = $order->get_meta('_mpg_transaction_id');
        
        ?>
        <div class="mpg-order-details">
            <h4><?php _e('Payment Information', 'manual-payment-gateway'); ?></h4>
            
            <?php if ($transaction_id): ?>
            <p><strong><?php _e('Transaction ID:', 'manual-payment-gateway'); ?></strong> <?php echo esc_html($transaction_id); ?></p>
            <?php endif; ?>
            
            <?php if ($files): ?>
            <h4><?php _e('Uploaded Files:', 'manual-payment-gateway'); ?></h4>
            <div class="mpg-uploaded-files">
                <?php foreach ($files as $file): ?>
                <div class="mpg-file-item">
                    <a href="<?php echo esc_url($file['path']); ?>" target="_blank">
                        <img src="<?php echo esc_url($file['path']); ?>" alt="<?php echo esc_attr($file['name']); ?>" style="max-width: 150px; height: auto; margin: 5px;" />
                    </a>
                    <p><?php echo esc_html($file['name']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .mpg-uploaded-files {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .mpg-file-item {
            text-align: center;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
        }
        .mpg-file-item img {
            display: block;
            margin: 0 auto 5px;
        }
        </style>
        <?php
    }
    
    /**
     * Update payment status via AJAX
     */
    public function update_payment_status() {
        check_ajax_referer('mpg_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions.', 'manual-payment-gateway'));
        }
        
        $log_id = intval($_POST['log_id']);
        $status = sanitize_text_field($_POST['status']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'mpg_payment_logs';
        
        // Get the payment log to retrieve order ID before updating
        $payment_log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $log_id));
        
        if (!$payment_log) {
            wp_send_json_error(array('message' => __('Payment log not found.', 'manual-payment-gateway')));
        }
        
        // Update payment log status
        $result = $wpdb->update(
            $table_name,
            array('status' => $status),
            array('id' => $log_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Update WooCommerce order status based on payment approval (if enabled)
            $sync_result = $this->sync_order_status($payment_log->order_id, $status);
            
            if ($sync_result) {
                wp_send_json_success(array('message' => __('Status updated successfully and order status synchronized.', 'manual-payment-gateway')));
            } else {
                wp_send_json_success(array('message' => __('Payment status updated successfully. Order status synchronization is disabled.', 'manual-payment-gateway')));
            }
        } else {
            wp_send_json_error(array('message' => __('Failed to update status.', 'manual-payment-gateway')));
        }
    }

    /**
     * Sync WooCommerce order status based on payment approval
     * 
     * This method automatically updates the WooCommerce order status when
     * the payment log status is changed:
     * - approved -> processing (payment verified, ready for fulfillment)
     * - rejected -> cancelled (payment verification failed)
     * - pending -> no change (awaiting review)
     * 
     * @param int $order_id WooCommerce order ID
     * @param string $status Payment log status (approved, rejected, pending)
     * @return bool Success status
     */
    private function sync_order_status($order_id, $status) {
        // Check if auto-sync is enabled
        $gateway = new MPG_Gateway();
        if (!$gateway->is_auto_sync_enabled()) {
            return false; // Auto-sync is disabled
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        // Only sync if the order is currently on-hold (to avoid overriding other statuses)
        if ($order->get_status() !== 'on-hold') {
            return false;
        }
        
        // Check if this order was created with our manual payment gateway
        if ($order->get_payment_method() !== 'manual_payment_gateway') {
            return false;
        }
        
        switch ($status) {
            case 'approved':
                $order->update_status('processing', __('Payment approved - Manual payment screenshot verified by administrator.', 'manual-payment-gateway'));
                
                // Add order note for admin reference
                $order->add_order_note(__('Manual payment approved by administrator. Payment screenshot verified successfully.', 'manual-payment-gateway'));
                
                break;
                
            case 'rejected':
                $order->update_status('cancelled', __('Payment rejected - Manual payment screenshot verification failed.', 'manual-payment-gateway'));
                
                // Add order note for admin reference
                $order->add_order_note(__('Manual payment rejected by administrator. Payment screenshot verification failed.', 'manual-payment-gateway'));
                
                break;
                
            case 'pending':
                // No action needed for pending status
                break;
        }
        
        return true;
    }
    
    /**
     * Save hidden columns via AJAX
     */
    public function save_hidden_columns() {
        check_ajax_referer('mpg_save_columns', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions.', 'manual-payment-gateway'));
        }
        
        $hidden_columns = isset($_POST['hidden_columns']) ? array_map('sanitize_text_field', $_POST['hidden_columns']) : array();
        
        update_user_meta(get_current_user_id(), 'managempg-payment-logscolumnshidden', $hidden_columns);
        
        wp_send_json_success();
    }
}

// Add JavaScript for per-page functionality
add_action('admin_footer', 'mpg_admin_footer_script');

function mpg_admin_footer_script() {
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'mpg-payment-logs') !== false) {
        ?>
        <script type="text/javascript">
        function mpgChangePerPage(perPage) {
            var url = new URL(window.location);
            url.searchParams.set('per_page', perPage);
            url.searchParams.delete('paged'); // Reset to first page
            window.location.href = url.toString();
        }
        </script>
        <?php
    }
}