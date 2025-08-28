<?php
/**
 * MSPConnect Helper Functions
 */

use WHMCS\Database\Capsule;

/**
 * Log activity for MSP actions
 */
function mspconnect_log_activity($mspId, $customerId = null, $action = '', $description = '', $metadata = [])
{
    try {
        Capsule::table('msp_reseller_activity_log')->insert([
            'msp_id' => $mspId,
            'customer_id' => $customerId,
            'action' => $action,
            'description' => $description,
            'metadata' => json_encode($metadata),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        error_log('MSPConnect Activity Log Error: ' . $e->getMessage());
    }
}

/**
 * Encrypt sensitive data (like passwords)
 */
function mspconnect_encrypt($data)
{
    if (empty($data)) {
        return '';
    }
    
    // Use WHMCS encryption if available
    if (function_exists('encrypt')) {
        return encrypt($data);
    }
    
    // Fallback to simple base64 encoding (not secure, but functional)
    return base64_encode($data);
}

/**
 * Decrypt sensitive data
 */
function mspconnect_decrypt($encryptedData)
{
    if (empty($encryptedData)) {
        return '';
    }
    
    // Use WHMCS decryption if available
    if (function_exists('decrypt')) {
        return decrypt($encryptedData);
    }
    
    // Fallback to base64 decoding
    return base64_decode($encryptedData);
}

/**
 * Generate secure random string
 */
function mspconnect_generate_token($length = 32)
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    }
    
    // Fallback for older PHP versions
    return bin2hex(openssl_random_pseudo_bytes($length / 2));
}

/**
 * Validate email address
 */
function mspconnect_validate_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Format currency amount
 */
function mspconnect_format_currency($amount, $currency = 'USD')
{
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'CAD' => 'C$',
        'AUD' => 'A$'
    ];
    
    $symbol = $symbols[$currency] ?? '$';
    return $symbol . number_format($amount, 2);
}

/**
 * Sanitize file name for uploads
 */
function mspconnect_sanitize_filename($filename)
{
    // Remove any path info
    $filename = basename($filename);
    
    // Remove any characters that aren't alphanumeric, dot, dash, or underscore
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Ensure filename isn't empty
    if (empty($filename)) {
        $filename = 'file_' . time();
    }
    
    return $filename;
}

/**
 * Get client IP address
 */
function mspconnect_get_client_ip()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

/**
 * Check if MSP has permission for a specific action
 */
function mspconnect_check_permission($mspId, $action)
{
    // For now, all MSPs have all permissions
    // This can be expanded later for role-based access
    return true;
}

/**
 * Send notification email
 */
function mspconnect_send_notification($to, $subject, $message, $mspId = null)
{
    try {
        // Get MSP SMTP settings if MSP ID provided
        if ($mspId) {
            $smtpConfig = Capsule::table('msp_reseller_smtp_config')
                ->where('msp_id', $mspId)
                ->first();
                
            if ($smtpConfig && $smtpConfig->status === 'active') {
                // Use MSP's custom SMTP settings
                require_once __DIR__ . '/Email/EmailManager.php';
                $emailManager = new MSPConnect\Email\EmailManager();
                return $emailManager->sendCustomEmail($to, $subject, $message, $mspId);
            }
        }
        
        // Fallback to WHMCS default email system
        if (function_exists('sendMessage')) {
            return sendMessage('General', $to, ['subject' => $subject, 'message' => $message]);
        }
        
        // Last resort - use PHP mail()
        return mail($to, $subject, $message);
        
    } catch (Exception $e) {
        error_log('MSPConnect Email Error: ' . $e->getMessage());
        return false;
    }
}