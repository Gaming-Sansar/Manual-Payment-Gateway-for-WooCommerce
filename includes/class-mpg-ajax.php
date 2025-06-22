<?php
/**
 * AJAX functionality for Manual Payment Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class MPG_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_mpg_upload_file', array($this, 'handle_file_upload'));
        add_action('wp_ajax_nopriv_mpg_upload_file', array($this, 'handle_file_upload'));
        add_action('wp_ajax_mpg_delete_file', array($this, 'handle_file_delete'));
        add_action('wp_ajax_nopriv_mpg_delete_file', array($this, 'handle_file_delete'));
    }
    
    /**
     * Handle file upload via AJAX
     */
    public function handle_file_upload() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mpg_upload_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'manual-payment-gateway')));
        }
        
        if (!isset($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file uploaded.', 'manual-payment-gateway')));
        }
        
        $file = $_FILES['file'];
        
        // Validate file
        $validation = $this->validate_file($file);
        if (is_wp_error($validation)) {
            wp_send_json_error(array('message' => $validation->get_error_message()));
        }
        
        // Handle the upload
        $upload_result = $this->process_file_upload($file);
        
        if (is_wp_error($upload_result)) {
            wp_send_json_error(array('message' => $upload_result->get_error_message()));
        }
        
        wp_send_json_success($upload_result);
    }
    
    /**
     * Validate uploaded file
     */
    private function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload failed.', 'manual-payment-gateway'));
        }
        
        // Check file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('File size exceeds 5MB limit.', 'manual-payment-gateway'));
        }
        
        // Check file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        $file_type = wp_check_filetype($file['name']);
        
        if (!in_array($file['type'], $allowed_types) && !in_array($file_type['type'], $allowed_types)) {
            return new WP_Error('invalid_file_type', __('Only image files are allowed.', 'manual-payment-gateway'));
        }
        
        // Additional security check
        if (!getimagesize($file['tmp_name'])) {
            return new WP_Error('invalid_image', __('Invalid image file.', 'manual-payment-gateway'));
        }
        
        return true;
    }
    
    /**
     * Process file upload
     */
    private function process_file_upload($file) {
        // Create upload directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $mpg_dir = $upload_dir['basedir'] . '/manual-payment-gateway';
        
        if (!file_exists($mpg_dir)) {
            wp_mkdir_p($mpg_dir);
        }
        
        // Generate unique filename
        $file_info = pathinfo($file['name']);
        $filename = wp_unique_filename($mpg_dir, sanitize_file_name($file['name']));
        $file_path = $mpg_dir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return new WP_Error('move_failed', __('Failed to save uploaded file.', 'manual-payment-gateway'));
        }
        
        // Return success data
        return array(
            'name' => $filename,
            'path' => $upload_dir['baseurl'] . '/manual-payment-gateway/' . $filename,
            'size' => $file['size'],
            'type' => $file['type']
        );
    }
    
    /**
     * Handle file deletion via AJAX
     */
    public function handle_file_delete() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mpg_upload_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'manual-payment-gateway')));
        }
        
        $filename = sanitize_file_name($_POST['filename']);
        
        if (empty($filename)) {
            wp_send_json_error(array('message' => __('Invalid filename.', 'manual-payment-gateway')));
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/manual-payment-gateway/' . $filename;
        
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                wp_send_json_success(array('message' => __('File deleted successfully.', 'manual-payment-gateway')));
            } else {
                wp_send_json_error(array('message' => __('Failed to delete file.', 'manual-payment-gateway')));
            }
        } else {
            wp_send_json_error(array('message' => __('File not found.', 'manual-payment-gateway')));
        }
    }
}