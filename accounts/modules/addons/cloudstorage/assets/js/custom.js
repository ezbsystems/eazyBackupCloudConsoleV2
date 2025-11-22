
function formatSizeUnits(bytes) {
    if (bytes >= 1099511627776) {
        return (bytes / 1099511627776).toFixed(2) + ' TiB';
    } else if (bytes >= 1073741824) {
        return (bytes / 1073741824).toFixed(2) + ' GiB';
    } else if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + ' MiB';
    } else if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + ' KiB';
    } else if (bytes > 1) {
        return bytes + ' bytes';
    } else if (bytes == 1) {
        return bytes + ' byte';
    } else {
        return '0 bytes';
    }
}

function hideLoader() {
    jQuery('#loading-overlay').addClass('hidden');
}

function showLoader() {
    jQuery('#loading-overlay').removeClass('hidden');
}

jQuery.ajaxSetup({
    beforeSend: function() {
        showLoader();
    },
    complete: function() {
        hideLoader();
    }
});

function hideMessage(container) {
    jQuery('#' + container).text('').addClass('hidden');
}

// Helper function to clear all message timeouts
function clearAllMessageTimeouts() {
    if (window.messageTimeouts) {
        Object.keys(window.messageTimeouts).forEach(function(container) {
            clearTimeout(window.messageTimeouts[container]);
            delete window.messageTimeouts[container];
        });
    }
}

function showMessage(message, container, status) {
    const containerElement = jQuery('#' + container);
    
    // Clear any existing timeout for this container
    if (window.messageTimeouts && window.messageTimeouts[container]) {
        clearTimeout(window.messageTimeouts[container]);
        delete window.messageTimeouts[container];
    }
    
    // Initialize timeouts object if it doesn't exist
    if (!window.messageTimeouts) {
        window.messageTimeouts = {};
    }
    
    // Create message content with close button
    const closeButton = '<button type="button" class="ml-auto pl-4 text-current hover:text-gray-200 focus:outline-none" onclick="hideMessage(\'' + container + '\')" aria-label="Close message">' +
                       '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                       '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>' +
                       '</svg></button>';
    
    const messageContent = '<div class="flex items-start justify-between">' +
                          '<div class="flex-1 pr-2">' + message + '</div>' +
                          closeButton +
                          '</div>';
    
    // Apply styles and show message
    if (status == 'success') {
        containerElement.removeClass('bg-red-600 hidden').addClass('bg-green-600').html(messageContent);
        
        // Success messages auto-hide after 5 seconds
        window.messageTimeouts[container] = setTimeout(function() {
            hideMessage(container);
        }, 5000);
    } else {
        containerElement.removeClass('bg-green-600 hidden').addClass('bg-red-600').html(messageContent);
        
        // Error messages stay visible longer (10 seconds) or until manually closed
        window.messageTimeouts[container] = setTimeout(function() {
            hideMessage(container);
        }, 10000);
    }
}

// Test function to demonstrate the enhanced message system (for development/testing)
function testEnhancedMessages() {
    console.log('Testing enhanced message system...');
    
    // Check if jQuery is available
    if (typeof jQuery === 'undefined') {
        console.error('jQuery is not loaded. Please ensure jQuery is available.');
        return;
    }
    
    // Check if alertMessage element exists
    const alertElement = jQuery('#alertMessage');
    if (alertElement.length === 0) {
        console.error('No element with ID "alertMessage" found on this page.');
        console.log('Available message containers:', 
            jQuery('[id*="Message"], [id*="message"], [class*="alert"]').map(function() {
                return this.id || this.className;
            }).get());
        
        // Create a temporary test element
        const testElement = '<div id="testAlertMessage" class="text-white px-4 py-3 rounded-md mb-6 hidden" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;"></div>';
        jQuery('body').append(testElement);
        
        console.log('Created temporary test element. Testing with ID "testAlertMessage"...');
        
        // Test with temporary element
        setTimeout(function() {
            showMessage('✅ Success message test (5 seconds)', 'testAlertMessage', 'success');
        }, 500);
        
        setTimeout(function() {
            showMessage('❌ Error message test (10 seconds)', 'testAlertMessage', 'error');
        }, 3000);
        
        return;
    }
    
    console.log('Found alertMessage element. Testing...');
    
    // Test success message
    showMessage('✅ This is a success message that will auto-hide in 5 seconds!', 'alertMessage', 'success');
    
    // Test error message after 2 seconds
    setTimeout(function() {
        showMessage('❌ This is an error message that stays visible for 10 seconds. You can close it manually with the X button.', 'alertMessage', 'error');
    }, 2000);
}

// Simple test function that works on any page
function testMessageAnywhere() {
    console.log('Testing message system...');
    
    // Check if global message container exists
    if (jQuery('#globalMessage').length > 0) {
        console.log('✅ Global message container found! Testing with globalMessage...');
        
        // Test success message
        showMessage('✅ Success message test with global container!', 'globalMessage', 'success');
        
        // Test error message after 3 seconds
        setTimeout(function() {
            showMessage('❌ Error message test with close button!', 'globalMessage', 'error');
        }, 3000);
        
        console.log('✅ Test sequence initiated with global container. Watch for messages!');
        return;
    }
    
    // Fallback: Create temporary message container
    console.log('No global container found. Creating temporary element...');
    
    // Remove any existing test element
    jQuery('#tempTestMessage').remove();
    
    // Create temporary message container
    const tempContainer = '<div id="tempTestMessage" class="text-white px-4 py-3 rounded-md mb-6 hidden" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);"></div>';
    jQuery('body').append(tempContainer);
    
    // Test success message
    showMessage('✅ Success message test!', 'tempTestMessage', 'success');
    
    // Test error message after 3 seconds
    setTimeout(function() {
        showMessage('❌ Error message test with close button!', 'tempTestMessage', 'error');
    }, 3000);
    
    // Clean up after 15 seconds
    setTimeout(function() {
        jQuery('#tempTestMessage').remove();
        console.log('Test completed and cleaned up.');
    }, 15000);
}

// Make both test functions available in console for testing
window.testEnhancedMessages = testEnhancedMessages;
window.testMessageAnywhere = testMessageAnywhere;

document.addEventListener('DOMContentLoaded', function () {
    window.addEventListener('beforeunload', function () {
        localStorage.removeItem('passwordModalOpened');
    });
});