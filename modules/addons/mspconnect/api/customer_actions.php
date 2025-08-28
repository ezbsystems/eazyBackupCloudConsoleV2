<?php
/**
 * Customer Actions API Endpoint
 * 
 * Handles AJAX requests for customer management operations
 * including password reset, suspension, deletion, etc.
 */

use WHMCS\Database\Capsule;

// Include WHMCS configuration
require_once __DIR__ . '/../../../../configuration.php';
require_once __DIR__ . '/../../../../init.php';

// Set JSON response headers
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['uid']) || empty($_SESSION['uid'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get MSP settings
$clientId = (int) $_SESSION['uid'];
$mspSettings = Capsule::table('msp_reseller_msp_settings')
    ->where('client_id', $clientId)
    ->first();

if (!$mspSettings) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'MSP account not found']);
    exit;
}

// Get action from POST data
$action = $_POST['action'] ?? '';
$customerId = (int) ($_POST['customer_id'] ?? 0);

if (!$action || !$customerId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

// Verify customer belongs to this MSP
$customer = Capsule::table('msp_reseller_customers')
    ->where('id', $customerId)
    ->where('msp_id', $mspSettings->id)
    ->first();

if (!$customer) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
    exit;
}

try {
    switch ($action) {
        case 'send_password_reset':
            $result = handlePasswordReset($mspSettings->id, $customer);
            break;
            
        case 'suspend':
            $result = handleSuspendCustomer($mspSettings->id, $customer);
            break;
            
        case 'activate':
            $result = handleActivateCustomer($mspSettings->id, $customer);
            break;
            
        case 'delete':
            $result = handleDeleteCustomer($mspSettings->id, $customer);
            break;
            
        case 'update_status':
            $status = $_POST['status'] ?? '';
            $result = handleUpdateStatus($mspSettings->id, $customer, $status);
            break;
            
        case 'resend_welcome':
            $result = handleResendWelcome($mspSettings->id, $customer);
            break;
            
        default:
            $result = ['success' => false, 'message' => 'Unknown action'];
            break;
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('MSPConnect Customer Action Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}

/**
 * Handle password reset request
 */
function handlePasswordReset($mspId, $customer)
{
    try {
        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Update customer with reset token
        Capsule::table('msp_reseller_customers')
            ->where('id', $customer->id)
            ->update([
                'password_reset_token' => $resetToken,
                'password_reset_expires' => $expires,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        
        // Send password reset email
        require_once __DIR__ . '/../lib/Email/EmailManager.php';
        $emailManager = new EmailManager($mspId);
        $emailManager->sendPasswordResetEmail($customer->id, $resetToken);
        
        // Log activity
        mspconnect_log_activity($mspId, $customer->id, 'password_reset_sent', 
            'Password reset email sent to customer');
        
        return [
            'success' => true, 
            'message' => 'Password reset email sent successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => 'Failed to send password reset email: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle customer suspension
 */
function handleSuspendCustomer($mspId, $customer)
{
    try {
        // Update customer status
        Capsule::table('msp_reseller_customers')
            ->where('id', $customer->id)
            ->update([
                'status' => 'suspended',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        
        // Suspend all active services
        Capsule::table('msp_reseller_services')
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->update([
                'status' => 'suspended',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        
        // Log activity
        mspconnect_log_activity($mspId, $customer->id, 'customer_suspended', 
            'Customer account suspended');
        
        return [
            'success' => true, 
            'message' => 'Customer suspended successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => 'Failed to suspend customer: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle customer activation
 */
function handleActivateCustomer($mspId, $customer)
{
    try {
        // Update customer status
        Capsule::table('msp_reseller_customers')
            ->where('id', $customer->id)
            ->update([
                'status' => 'active',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        
        // Reactivate suspended services
        Capsule::table('msp_reseller_services')
            ->where('customer_id', $customer->id)
            ->where('status', 'suspended')
            ->update([
                'status' => 'active',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        
        // Log activity
        mspconnect_log_activity($mspId, $customer->id, 'customer_activated', 
            'Customer account activated');
        
        return [
            'success' => true, 
            'message' => 'Customer activated successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => 'Failed to activate customer: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle customer deletion
 */
function handleDeleteCustomer($mspId, $customer)
{
    try {
        // Start transaction
        Capsule::beginTransaction();
        
        // Delete payment methods
        Capsule::table('msp_reseller_payment_methods')
            ->where('customer_id', $customer->id)
            ->delete();
        
        // Delete invoices
        Capsule::table('msp_reseller_invoices')
            ->where('customer_id', $customer->id)
            ->delete();
        
        // Delete services
        Capsule::table('msp_reseller_services')
            ->where('customer_id', $customer->id)
            ->delete();
        
        // Delete activity logs for this customer
        Capsule::table('msp_reseller_activity_log')
            ->where('customer_id', $customer->id)
            ->delete();
        
        // Delete customer
        Capsule::table('msp_reseller_customers')
            ->where('id', $customer->id)
            ->delete();
        
        // If customer has Stripe customer ID, delete from Stripe too
        if ($customer->stripe_customer_id) {
            try {
                require_once __DIR__ . '/../lib/Stripe/StripeManager.php';
                
                // Get module config
                $moduleConfig = Capsule::table('tbladdonmodules')
                    ->where('module', 'mspconnect')
                    ->pluck('value', 'setting');
                
                $stripeManager = new StripeManager($moduleConfig);
                
                // Get MSP's Stripe account
                $mspSettings = Capsule::table('msp_reseller_msp_settings')
                    ->where('id', $mspId)
                    ->first();
                
                if ($mspSettings->stripe_account_id) {
                    // Delete customer from Stripe
                    \Stripe\Customer::retrieve($customer->stripe_customer_id, [
                        'stripe_account' => $mspSettings->stripe_account_id
                    ])->delete();
                }
            } catch (Exception $e) {
                // Log Stripe deletion error but don't fail the whole operation
                error_log('Failed to delete Stripe customer: ' . $e->getMessage());
            }
        }
        
        // Commit transaction
        Capsule::commit();
        
        // Log activity
        mspconnect_log_activity($mspId, null, 'customer_deleted', 
            'Customer deleted: ' . $customer->first_name . ' ' . $customer->last_name);
        
        return [
            'success' => true, 
            'message' => 'Customer deleted successfully'
        ];
        
    } catch (Exception $e) {
        // Rollback transaction
        Capsule::rollback();
        
        return [
            'success' => false, 
            'message' => 'Failed to delete customer: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle status update
 */
function handleUpdateStatus($mspId, $customer, $newStatus)
{
    $validStatuses = ['active', 'inactive', 'suspended'];
    
    if (!in_array($newStatus, $validStatuses)) {
        return [
            'success' => false, 
            'message' => 'Invalid status provided'
        ];
    }
    
    try {
        // Update customer status
        Capsule::table('msp_reseller_customers')
            ->where('id', $customer->id)
            ->update([
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        
        // Update service statuses if suspending/activating
        if ($newStatus === 'suspended') {
            Capsule::table('msp_reseller_services')
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->update(['status' => 'suspended']);
        } elseif ($newStatus === 'active') {
            Capsule::table('msp_reseller_services')
                ->where('customer_id', $customer->id)
                ->where('status', 'suspended')
                ->update(['status' => 'active']);
        }
        
        // Log activity
        mspconnect_log_activity($mspId, $customer->id, 'status_updated', 
            'Customer status updated to: ' . $newStatus);
        
        return [
            'success' => true, 
            'message' => 'Customer status updated successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => 'Failed to update status: ' . $e->getMessage()
        ];
    }
}

/**
 * Handle resend welcome email
 */
function handleResendWelcome($mspId, $customer)
{
    try {
        // Send welcome email
        require_once __DIR__ . '/../lib/Email/EmailManager.php';
        $emailManager = new EmailManager($mspId);
        $emailManager->sendWelcomeEmail($customer->id);
        
        // Log activity
        mspconnect_log_activity($mspId, $customer->id, 'welcome_email_resent', 
            'Welcome email resent to customer');
        
        return [
            'success' => true, 
            'message' => 'Welcome email sent successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => 'Failed to send welcome email: ' . $e->getMessage()
        ];
    }
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