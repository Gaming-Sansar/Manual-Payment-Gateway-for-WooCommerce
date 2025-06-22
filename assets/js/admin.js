jQuery(document).ready(function($) {
    
    // Media uploader for QR code
    $(document).on('click', '.mpg-upload-qr', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const inputField = $('#woocommerce_manual_payment_gateway_qr_code');
        
        // Create media frame
        const frame = wp.media({
            title: 'Select QR Code Image',
            button: {
                text: 'Use this image'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        // When image is selected
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            inputField.val(attachment.url);
            
            // Show preview
            let preview = inputField.next('.qr-code-preview');
            if (preview.length === 0) {
                preview = $('<div class="qr-code-preview"></div>');
                inputField.after(preview);
            }
            
            preview.html(`
                <img src="${attachment.url}" style="max-width: 150px; margin-top: 10px;" />
                <br><button type="button" class="button mpg-remove-qr" style="margin-top: 5px;">Remove QR Code</button>
            `);
        });
        
        // Open media frame
        frame.open();
    });
    
    // Remove QR code
    $(document).on('click', '.mpg-remove-qr', function(e) {
        e.preventDefault();
        $('#woocommerce_manual_payment_gateway_qr_code').val('');
        $(this).closest('.qr-code-preview').remove();
    });
    
    // Show QR code preview if already set
    const qrCodeField = $('#woocommerce_manual_payment_gateway_qr_code');
    if (qrCodeField.length && qrCodeField.val()) {
        const qrCodeUrl = qrCodeField.val();
        if (!qrCodeField.next('.qr-code-preview').length) {
            qrCodeField.after(`
                <div class="qr-code-preview">
                    <img src="${qrCodeUrl}" style="max-width: 150px; margin-top: 10px;" />
                    <br><button type="button" class="button mpg-remove-qr" style="margin-top: 5px;">Remove QR Code</button>
                </div>
            `);
        }
    }
});