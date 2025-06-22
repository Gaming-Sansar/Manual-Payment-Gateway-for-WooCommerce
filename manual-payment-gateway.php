<?php
/**
 * Plugin Name: Manual Payment Gateway for WooCommerce
 * Plugin URI: https://github.com/gamingsansar/manual-payment-gateway
 * Description: A manual payment gateway for WooCommerce with file upload and QR code support
 * Version: 1.0.0
 * Author: Gaming Sansar
 * Author URI: https://gamingsansar.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: manual-payment-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.2
 * WC requires at least: 5.0
 * WC tested up to: 7.8
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MPG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MPG_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MPG_PLUGIN_VERSION', '1.0.0');

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'mpg_woocommerce_missing_notice');
    return;
}

function mpg_woocommerce_missing_notice() {
    echo '<div class="notice notice-error"><p>' . __('Manual Payment Gateway requires WooCommerce to be installed and active.', 'manual-payment-gateway') . '</p></div>';
}

// Initialize the plugin
add_action('plugins_loaded', 'mpg_init');

function mpg_init() {
    // Load text domain
    load_plugin_textdomain('manual-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Include the gateway class
    include_once MPG_PLUGIN_PATH . 'includes/class-mpg-gateway.php';
    include_once MPG_PLUGIN_PATH . 'includes/class-mpg-admin.php';
    include_once MPG_PLUGIN_PATH . 'includes/class-mpg-ajax.php';
    
    // Initialize admin class
    new MPG_Admin();
    new MPG_Ajax();
    
    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'mpg_add_gateway');
}

function mpg_add_gateway($gateways) {
    $gateways[] = 'MPG_Gateway';
    return $gateways;
}

// Activation hook
register_activation_hook(__FILE__, 'mpg_activate');

function mpg_activate() {
    // Create upload directory
    $upload_dir = wp_upload_dir();
    $mpg_dir = $upload_dir['basedir'] . '/manual-payment-gateway';
    
    if (!file_exists($mpg_dir)) {
        wp_mkdir_p($mpg_dir);
    }
    
    // Create database table for logs
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'mpg_payment_logs';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        transaction_id varchar(255) DEFAULT '',
        file_path varchar(500) DEFAULT '',
        file_name varchar(255) DEFAULT '',
        user_id bigint(20) NOT NULL,
        status varchar(50) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'mpg_deactivate');

function mpg_deactivate() {
    // Clean up if needed
}

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'mpg_enqueue_scripts');

function mpg_enqueue_scripts() {
    if (is_checkout()) {
        wp_enqueue_script('mpg-checkout', MPG_PLUGIN_URL . 'assets/js/checkout.js', array('jquery'), MPG_PLUGIN_VERSION, true);
        wp_enqueue_style('mpg-checkout', MPG_PLUGIN_URL . 'assets/css/checkout.css', array(), MPG_PLUGIN_VERSION);
        
        wp_localize_script('mpg-checkout', 'mpg_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mpg_upload_nonce'),
            'max_file_size' => 5 * 1024 * 1024, // 5MB in bytes
            'allowed_types' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
            'messages' => array(
                'invalid_file' => __('Please select a valid image file.', 'manual-payment-gateway'),
                'file_too_large' => __('File size exceeds 5MB limit.', 'manual-payment-gateway'),
                'upload_error' => __('Upload failed. Please try again.', 'manual-payment-gateway'),
                'upload_success' => __('File uploaded successfully.', 'manual-payment-gateway')
            )
        ));
    }
}

// Admin enqueue scripts
add_action('admin_enqueue_scripts', 'mpg_admin_enqueue_scripts');

function mpg_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'wc-settings') !== false) {
        wp_enqueue_media();
        wp_enqueue_script('mpg-admin', MPG_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), MPG_PLUGIN_VERSION, true);
        wp_enqueue_style('mpg-admin', MPG_PLUGIN_URL . 'assets/css/admin.css', array(), MPG_PLUGIN_VERSION);
    }
}