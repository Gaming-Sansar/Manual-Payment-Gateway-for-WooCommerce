# Manual Payment Gateway for WooCommerce

A comprehensive WooCommerce payment gateway that allows customers to upload payment screenshots for manual verification.

## Description

The Manual Payment Gateway plugin provides a seamless way for customers to submit payment proof through image uploads while giving administrators complete control over the payment verification process.

## Features

✅ **Complete Payment Gateway Integration**
- Fully integrated with WooCommerce payment system
- Appears in WooCommerce > Settings > Payments
- Customizable gateway title and descriptions

✅ **Advanced File Upload System**
- Image-only uploads with 5MB maximum file size
- Configurable upload limit (1-5 files per order)
- AJAX-powered uploads with progress bars
- Drag & drop file upload support
- Real-time file validation

✅ **Admin Control Panel**
- Rename gateway title and set custom instructions
- Upload/select QR code from WordPress media library
- QR code display on checkout page
- Comprehensive payment logs with timestamps

✅ **Payment Verification System**
- Detailed logging of each submission
- Screenshot storage and management
- Optional transaction ID field
- Order status management
- Admin meta box on order edit pages

✅ **Automatic Order Status Synchronization**
- Auto-sync WooCommerce order status with payment approvals
- Approved payments automatically change order status to "Processing"
- Rejected payments automatically change order status to "Cancelled"
- Enable/disable toggle with granular control
- Real-time status notifications in admin panel

✅ **User Experience**
- Responsive design for all devices
- Progress indicators during upload
- File preview functionality
- Intuitive interface

## Installation

1. Download the plugin files
2. Upload the manual-payment-gateway folder to /wp-content/plugins/
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to WooCommerce > Settings > Payments
5. Enable and configure the Manual Payment Gateway

## Configuration

### Basic Settings
1. Navigate to **WooCommerce > Settings > Payments**
2. Click on **Manual Payment Gateway**
3. Configure the following options:
   - **Enable/Disable**: Toggle the payment method
   - **Title**: Customer-facing payment method name
   - **Description**: Instructions shown during checkout
   - **Instructions**: Text shown on thank you page and emails
   - **QR Code**: Upload payment QR code for customers
   - **Maximum Files**: Set upload limit (1-5 files)
   - **Auto-Sync Order Status**: Enable/disable automatic order status updates

### QR Code Setup
1. Click "Select QR Code" button in settings
2. Choose image from media library or upload new
3. QR code will display on checkout page

### File Upload Limits
- **File Types**: Images only (JPEG, PNG, GIF, WebP)
- **File Size**: 5MB maximum per file
- **Upload Count**: Configurable 1-5 files per order

### Order Status Synchronization
- **Default**: Enabled by default for seamless workflow
- **When Enabled**: Approved payments automatically change order status to "Processing", rejected payments to "Cancelled"
- **When Disabled**: Order status remains "On Hold" for manual management
- **Safety**: Only affects orders created with manual payment gateway and currently in "On Hold" status

## Usage

### For Customers
1. Select "Manual Payment" at checkout
2. View QR code (if configured)
3. Upload payment screenshot(s)
4. Enter transaction ID (optional)
5. Complete order

### For Administrators
1. View payment logs at **WooCommerce > Payment Logs**
2. Review uploaded screenshots and transaction details
3. Update payment status (Pending/Approved/Rejected)
4. **Automatic Order Processing**: When auto-sync is enabled, order status updates automatically
5. Access payment details from order edit page with full order history

## Workflow

### Complete Payment Process
1. **Customer Checkout**: Customer selects manual payment gateway
2. **File Upload**: Customer uploads payment screenshot(s) and optional transaction ID
3. **Order Creation**: Order is created with "On Hold" status
4. **Admin Review**: Administrator reviews payment proof in Payment Logs
5. **Status Update**: Administrator approves or rejects the payment
6. **Auto-Sync** (if enabled): 
   - ✅ **Approved** → Order status automatically changes to "Processing"
   - ❌ **Rejected** → Order status automatically changes to "Cancelled"
7. **Order Fulfillment**: Order proceeds through normal WooCommerce workflow

### Manual Override
- Auto-sync can be disabled in gateway settings
- When disabled, order status remains "On Hold" for manual management
- Administrators can still change order status manually in WooCommerce orders

## Technical Details

### File Storage
- Uploaded files stored in /wp-content/uploads/manual-payment-gateway/
- Secure file handling with validation
- Automatic directory creation

### Database
- Creates wp_mpg_payment_logs table for tracking
- Stores order metadata for payment details
- Maintains audit trail of all submissions
- Tracks payment status changes and order synchronization

### Security Features
- Nonce verification for all AJAX requests
- File type and size validation
- Secure file upload handling
- User permission checks

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Author

**Gaming Sansar**
- Website: https://gamingsansar.com
- Plugin development and WordPress solutions

## Changelog

### Version 1.1.0
- **NEW**: Automatic Order Status Synchronization
- **NEW**: Enable/disable toggle for auto-sync functionality (enabled by default)
- **ENHANCEMENT**: Enhanced admin panel with real-time sync status notifications
- **ENHANCEMENT**: Improved payment logs interface with order status column
- **ENHANCEMENT**: Smart color-coded status indicators
- **ENHANCEMENT**: Detailed order notes for payment approval/rejection tracking
- **IMPROVEMENT**: Enhanced user experience with contextual success/warning messages

### Version 1.0.0
- Initial release
- Complete payment gateway implementation
- AJAX file upload system
- Admin management interface
- QR code support
- Payment logging system