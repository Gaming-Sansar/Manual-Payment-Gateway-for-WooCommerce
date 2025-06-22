jQuery(document).ready(function($) {
    let uploadedFiles = [];
    
    // Handle file input change
    $(document).on('change', '.mpg-file-input', function() {
        const input = this;
        const file = input.files[0];
        const container = $(input).closest('.mpg-file-upload-container');
        
        if (!file) {
            return;
        }
        
        // Validate file
        if (!validateFile(file)) {
            input.value = '';
            return;
        }
        
        // Show progress bar
        const progressContainer = container.find('.mpg-progress-container');
        const progressBar = container.find('.mpg-progress-fill');
        const progressText = container.find('.mpg-progress-text');
        
        progressContainer.show();
        progressBar.css('width', '0%');
        progressText.text('0%');
        
        // Create FormData
        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'mpg_upload_file');
        formData.append('nonce', mpg_ajax.nonce);
        
        // Upload file
        $.ajax({
            url: mpg_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBar.css('width', percentComplete + '%');
                        progressText.text(Math.round(percentComplete) + '%');
                    }
                });
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    // Hide progress bar
                    progressContainer.hide();
                    
                    // Show preview
                    showFilePreview(container, response.data);
                    
                    // Add to uploaded files array
                    uploadedFiles.push(response.data);
                    updateUploadedFilesInput();
                    
                    // Show success message
                    showMessage(mpg_ajax.messages.upload_success, 'success');
                } else {
                    progressContainer.hide();
                    showMessage(response.data.message || mpg_ajax.messages.upload_error, 'error');
                    input.value = '';
                }
            },
            error: function() {
                progressContainer.hide();
                showMessage(mpg_ajax.messages.upload_error, 'error');
                input.value = '';
            }
        });
    });
    
    // Remove file
    $(document).on('click', '.mpg-remove-file', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const filename = button.data('filename');
        const container = button.closest('.mpg-file-upload-container');
        
        // Remove from uploaded files array
        uploadedFiles = uploadedFiles.filter(file => file.name !== filename);
        updateUploadedFilesInput();
        
        // Clear file input and preview
        container.find('.mpg-file-input').val('');
        container.find('.mpg-file-preview').empty();
        
        // Delete file from server
        $.ajax({
            url: mpg_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mpg_delete_file',
                filename: filename,
                nonce: mpg_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('File removed successfully', 'success');
                }
            }
        });
    });
    
    // Validate file
    function validateFile(file) {
        // Check file type
        if (!mpg_ajax.allowed_types.includes(file.type)) {
            showMessage(mpg_ajax.messages.invalid_file, 'error');
            return false;
        }
        
        // Check file size
        if (file.size > mpg_ajax.max_file_size) {
            showMessage(mpg_ajax.messages.file_too_large, 'error');
            return false;
        }
        
        return true;
    }
    
    // Show file preview
    function showFilePreview(container, fileData) {
        const preview = container.find('.mpg-file-preview');
        const previewHtml = `
            <div class="mpg-file-item">
                <img src="${fileData.path}" alt="${fileData.name}" style="max-width: 100px; height: auto;" />
                <p>${fileData.name}</p>
                <button type="button" class="mpg-remove-file button" data-filename="${fileData.name}">Remove</button>
            </div>
        `;
        
        preview.html(previewHtml);
    }
    
    // Update hidden input with uploaded files data
    function updateUploadedFilesInput() {
        $('#mpg_uploaded_files').val(JSON.stringify(uploadedFiles));
    }
    
    // Show message
    function showMessage(message, type) {
        const messageClass = type === 'error' ? 'woocommerce-error' : 'woocommerce-message';
        const messageHtml = `<div class="${messageClass}">${message}</div>`;
        
        // Remove existing messages
        $('.woocommerce-error, .woocommerce-message').remove();
        
        // Add new message
        $('.woocommerce-checkout').prepend(messageHtml);
        
        // Scroll to top
        $('html, body').animate({
            scrollTop: $('.woocommerce-checkout').offset().top - 100
        }, 500);
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                $('.woocommerce-message').fadeOut();
            }, 3000);
        }
    }
    
    // Validate before checkout submission
    $('body').on('checkout_place_order_manual_payment_gateway', function() {
        if (uploadedFiles.length === 0) {
            showMessage('Please upload at least one payment screenshot.', 'error');
            return false;
        }
        
        return true;
    });
    
    // Handle drag and drop
    $(document).on('dragover dragenter', '.mpg-file-input', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drag-over');
    });
    
    $(document).on('dragleave', '.mpg-file-input', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
    });
    
    $(document).on('drop', '.mpg-file-input', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
        
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            this.files = files;
            $(this).trigger('change');
        }
    });
});