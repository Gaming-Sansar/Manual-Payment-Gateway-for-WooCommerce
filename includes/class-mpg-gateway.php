<?php
/**
 * Manual Payment Gateway Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MPG_Gateway extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id = 'manual_payment_gateway';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = __('Manual Payment Gateway', 'manual-payment-gateway');
        $this->method_description = __('Allow customers to upload payment screenshots for manual verification.', 'manual-payment-gateway');
        
        $this->supports = array(
            'products'
        );
        
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->qr_code = $this->get_option('qr_code');
        $this->max_files = $this->get_option('max_files', 1);
        $this->enabled = $this->get_option('enabled');
        $this->auto_sync_order_status = $this->get_option('auto_sync_order_status', 'yes');
        
        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
    }
    
    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'manual-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Manual Payment Gateway', 'manual-payment-gateway'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'manual-payment-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'manual-payment-gateway'),
                'default' => __('Manual Payment', 'manual-payment-gateway'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'manual-payment-gateway'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'manual-payment-gateway'),
                'default' => __('Please upload your payment screenshot and provide transaction details.', 'manual-payment-gateway'),
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => __('Instructions', 'manual-payment-gateway'),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'manual-payment-gateway'),
                'default' => __('Please upload your payment screenshot for verification.', 'manual-payment-gateway'),
                'desc_tip' => true,
            ),
            'qr_code' => array(
                'title' => __('QR Code', 'manual-payment-gateway'),
                'type' => 'text',
                'description' => __('Upload or select QR code image from media library. <button type="button" class="button mpg-upload-qr">Select QR Code</button>', 'manual-payment-gateway'),
                'desc_tip' => false,
            ),
            'max_files' => array(
                'title' => __('Maximum Files', 'manual-payment-gateway'),
                'type' => 'number',
                'description' => __('Maximum number of files customers can upload (1-5).', 'manual-payment-gateway'),
                'default' => 1,
                'custom_attributes' => array(
                    'min' => 1,
                    'max' => 5
                ),
                'desc_tip' => true,
            ),
            'auto_sync_order_status' => array(
                'title' => __('Auto-Sync Order Status', 'manual-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Automatically change WooCommerce order status when payment is approved/rejected', 'manual-payment-gateway'),
                'description' => __('When enabled: Approved payments change order status to "Processing", Rejected payments change order status to "Cancelled". When disabled: Order status remains "On Hold" and must be changed manually.', 'manual-payment-gateway'),
                'default' => 'yes',
                'desc_tip' => true,
            )
        );
    }
    
    /**
     * Payment fields on checkout
     */
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        
        // Display QR Code if set
        if ($this->qr_code) {
            echo '<div class="mpg-qr-code">';
            echo '<h4>' . __('Payment QR Code', 'manual-payment-gateway') . '</h4>';
            echo '<img src="' . esc_url($this->qr_code) . '" alt="QR Code" style="max-width: 200px; height: auto;" />';
            echo '</div>';
        }
        
        // File upload fields
        echo '<div class="mpg-payment-fields">';
        echo '<h4>' . __('Upload Payment Screenshot', 'manual-payment-gateway') . '</h4>';
        
        for ($i = 1; $i <= $this->max_files; $i++) {
            $required = ($i === 1) ? 'required' : '';
            echo '<div class="mpg-file-upload-container">';
            echo '<label for="mpg_file_' . $i . '">' . sprintf(__('Payment Screenshot %d:', 'manual-payment-gateway'), $i) . '</label>';
            echo '<input type="file" id="mpg_file_' . $i . '" name="mpg_files[]" accept="image/*" class="mpg-file-input" ' . $required . ' />';
            echo '<div class="mpg-progress-container" style="display: none;">';
            echo '<div class="mpg-progress-bar"><div class="mpg-progress-fill"></div></div>';
            echo '<span class="mpg-progress-text">0%</span>';
            echo '</div>';
            echo '<div class="mpg-file-preview"></div>';
            echo '</div>';
        }
        
        // Transaction ID field
        echo '<div class="mpg-transaction-field">';
        echo '<label for="mpg_transaction_id">' . __('Transaction ID (Optional):', 'manual-payment-gateway') . '</label>';
        echo '<input type="text" id="mpg_transaction_id" name="mpg_transaction_id" placeholder="' . __('Enter transaction ID if available', 'manual-payment-gateway') . '" />';
        echo '</div>';
        
        echo '</div>';
        
        // Hidden fields to store uploaded file data
        echo '<input type="hidden" id="mpg_uploaded_files" name="mpg_uploaded_files" value="" />';
    }
    
    /**
     * Validate payment fields
     */
    public function validate_fields() {
        if (empty($_POST['mpg_uploaded_files'])) {
            wc_add_notice(__('Please upload at least one payment screenshot.', 'manual-payment-gateway'), 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Process the payment
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        // Get uploaded files data
        $uploaded_files = json_decode(stripslashes($_POST['mpg_uploaded_files']), true);
        $transaction_id = sanitize_text_field($_POST['mpg_transaction_id']);
        
        if (!empty($uploaded_files)) {
            // Log the payment submission
            $this->log_payment_submission($order_id, $uploaded_files, $transaction_id);
            
            // Store data in order meta
            $order->update_meta_data('_mpg_files', $uploaded_files);
            $order->update_meta_data('_mpg_transaction_id', $transaction_id);
            $order->save();
            
            // Set order status to on-hold for manual review
            $order->update_status('on-hold', __('Awaiting payment screenshot verification.', 'manual-payment-gateway'));
            
            // Reduce stock levels
            wc_reduce_stock_levels($order_id);
            
            // Remove cart
            WC()->cart->empty_cart();
            
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        
        wc_add_notice(__('Payment processing failed. Please try again.', 'manual-payment-gateway'), 'error');
        return array('result' => 'fail');
    }
    
    /**
     * Log payment submission
     */
    private function log_payment_submission($order_id, $uploaded_files, $transaction_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpg_payment_logs';
        
        foreach ($uploaded_files as $file) {
            $wpdb->insert(
                $table_name,
                array(
                    'order_id' => $order_id,
                    'transaction_id' => $transaction_id,
                    'file_path' => $file['path'],
                    'file_name' => $file['name'],
                    'user_id' => get_current_user_id(),
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%d', '%s', '%s')
            );
        }
    }
    
    /**
     * Receipt page
     */
    public function receipt_page($order_id) {
        echo '<p>' . __('Thank you for your order. Please wait for payment verification.', 'manual-payment-gateway') . '</p>';
    }
    
    /**
     * Thank you page
     */
    public function thankyou_page($order_id) {
        if ($this->instructions) {
            echo wpautop(wptexturize(wp_kses_post($this->instructions)));
        }
    }
    
    /**
     * Check if auto-sync order status is enabled
     */
    public function is_auto_sync_enabled() {
        return $this->auto_sync_order_status === 'yes';
    }
}