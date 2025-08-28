<?php
/**
 * Stripe Connect OAuth Callback Handler
 * 
 * Handles the OAuth callback from Stripe Connect when MSPs
 * authorize their Stripe accounts to be connected.
 */

use WHMCS\Database\Capsule;

// Include WHMCS configuration
require_once __DIR__ . '/../../../../configuration.php';
require_once __DIR__ . '/../../../../init.php';

// Check if user is logged in
if (!isset($_SESSION['uid']) || empty($_SESSION['uid'])) {
    // Redirect to login with error
    header('Location: /clientarea.php?action=login&error=Please log in to continue');
    exit;
}

// Get MSP settings
$clientId = (int) $_SESSION['uid'];
$mspSettings = Capsule::table('msp_reseller_msp_settings')
    ->where('client_id', $clientId)
    ->first();

if (!$mspSettings) {
    showErrorPage('MSP account not found. Please contact support.');
    exit;
}

// Check for error from Stripe
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $errorDescription = $_GET['error_description'] ?? 'Unknown error occurred';
    
    // Log the error
    mspconnect_log_activity($mspSettings->id, null, 'stripe_connect_error', 
        'Stripe connection failed: ' . $error . ' - ' . $errorDescription);
    
    // Redirect back to module with error
    $moduleUrl = '/modules/addons/mspconnect/?action=stripe-connect&error=' . urlencode($errorDescription);
    header('Location: ' . $moduleUrl);
    exit;
}

// Check for authorization code
if (!isset($_GET['code'])) {
    showErrorPage('No authorization code received from Stripe.');
    exit;
}

try {
    // Get module configuration
    $moduleConfig = Capsule::table('tbladdonmodules')
        ->where('module', 'mspconnect')
        ->pluck('value', 'setting');
    
    if (!$moduleConfig) {
        throw new Exception('Module configuration not found');
    }
    
    // Load Stripe manager
    require_once __DIR__ . '/../lib/Stripe/StripeManager.php';
    $stripeManager = new StripeManager($moduleConfig);
    
    // Handle the OAuth callback
    $result = $stripeManager->handleOAuthCallback($mspSettings->id, $_GET['code']);
    
    // Redirect back to module with success/error message
    if (strpos($result, 'alert-success') !== false) {
        $moduleUrl = '/modules/addons/mspconnect/?action=stripe-connect&success=1';
    } else {
        $moduleUrl = '/modules/addons/mspconnect/?action=stripe-connect&error=' . urlencode(strip_tags($result));
    }
    
    header('Location: ' . $moduleUrl);
    exit;
    
} catch (Exception $e) {
    // Log the error
    error_log('Stripe OAuth Callback Error: ' . $e->getMessage());
    mspconnect_log_activity($mspSettings->id, null, 'stripe_connect_error', 
        'Stripe OAuth callback error: ' . $e->getMessage());
    
    // Redirect with error
    $moduleUrl = '/modules/addons/mspconnect/?action=stripe-connect&error=' . urlencode($e->getMessage());
    header('Location: ' . $moduleUrl);
    exit;
}

/**
 * Show error page
 */
function showErrorPage($message)
{
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Stripe Connect Error</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 50px; }
            .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; border: 1px solid #f5c6cb; }
            .button { background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="error">
            <h3>Connection Error</h3>
            <p>' . htmlspecialchars($message) . '</p>
            <a href="/modules/addons/mspconnect/" class="button">Return to MSP Dashboard</a>
        </div>
    </body>
    </html>';
}

/**
 * Helper function to log activities (if not already included)
 */
if (!function_exists('mspconnect_log_activity')) {
    function mspconnect_log_activity($mspId = null, $customerId = null, $action, $description, $metadata = [])
    {
        try {
            Capsule::table('msp_reseller_activity_log')->insert([
                'msp_id' => $mspId,
                'customer_id' => $customerId,
                'action' => $action,
                'description' => $description,
                'metadata' => json_encode($metadata),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log('MSPConnect Activity Log Error: ' . $e->getMessage());
        }
    }
} 