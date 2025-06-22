<?php
/**
 * Admin functionality for Manual Payment Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class MPG_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        add_action('wp_ajax_mpg_update_payment_status', array($this, 'update_payment_status'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Manual Payment Logs', 'manual-payment-gateway'),
            __('Payment Logs', 'manual-payment-gateway'),
            'manage_woocommerce',
            'mpg-payment-logs',
            array($this, 'payment_logs_page')
        );
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
        
        // Get logs
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        
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
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'manual-payment-gateway'); ?></th>
                        <th><?php _e('Order ID', 'manual-payment-gateway'); ?></th>
                        <th><?php _e('Order Status', 'manual-payment-gateway'); ?></th>
                        <th><?php _e('Transaction ID', 'manual-payment-gateway'); ?></th>
                        <th><?php _e('File', 'manual-payment-gateway'); ?></th>
                        <th><?php _e('User', 'manual-payment-gateway'); ?></th>
                        <th><?php _e('Payment Status', 'manual-payment-gateway'); ?></th>
                        <th><?php _e('Date', 'manual-payment-gateway'); ?></th>
                        <th><?php _e('Actions', 'manual-payment-gateway'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <?php 
                    $order = wc_get_order($log->order_id);
                    $order_status = $order ? $order->get_status() : 'unknown';
                    $order_status_name = $order ? wc_get_order_status_name($order_status) : __('Unknown', 'manual-payment-gateway');
                    ?>
                    <tr>
                        <td><?php echo $log->id; ?></td>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $log->order_id . '&action=edit'); ?>">
                                #<?php echo $log->order_id; ?>
                            </a>
                        </td>
                        <td>
                            <span class="order-status-<?php echo esc_attr($order_status); ?>">
                                <?php echo esc_html($order_status_name); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log->transaction_id); ?></td>
                        <td>
                            <?php if ($log->file_path): ?>
                                <a href="<?php echo esc_url($log->file_path); ?>" target="_blank">
                                    <?php echo esc_html($log->file_name); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $user = get_user_by('id', $log->user_id);
                            echo $user ? esc_html($user->display_name) : __('Guest', 'manual-payment-gateway');
                            ?>
                        </td>
                        <td>
                            <span class="status-<?php echo esc_attr($log->status); ?>">
                                <?php echo ucfirst(esc_html($log->status)); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($log->created_at)); ?></td>
                        <td>
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
                </tbody>
            </table>
        </div>
        
        <style>
        .status-pending { color: #ff9500; }
        .status-approved { color: #46b450; }
        .status-rejected { color: #dc3232; }
        .order-status-on-hold { color: #ff9500; font-weight: bold; }
        .order-status-processing { color: #46b450; font-weight: bold; }
        .order-status-completed { color: #46b450; font-weight: bold; }
        .order-status-cancelled { color: #dc3232; font-weight: bold; }
        .order-status-failed { color: #dc3232; font-weight: bold; }
        .order-status-pending { color: #ff9500; font-weight: bold; }
        </style>
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
}