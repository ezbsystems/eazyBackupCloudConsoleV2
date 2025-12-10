<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../lib/Client/TenantEmailService.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\TenantEmailService;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

// Check MSP access
if (!MspController::isMspClient($clientId)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'MSP access required'], 403))->send();
    exit;
}

// Basic tenant info
$name = trim($_POST['name'] ?? '');
$slug = trim($_POST['slug'] ?? '');
$status = $_POST['status'] ?? 'active';

// Profile/billing fields
$contactEmail = strtolower(trim($_POST['contact_email'] ?? ''));
$contactName = trim($_POST['contact_name'] ?? '');
$contactPhone = trim($_POST['contact_phone'] ?? '');
$addressLine1 = trim($_POST['address_line1'] ?? '');
$addressLine2 = trim($_POST['address_line2'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$postalCode = trim($_POST['postal_code'] ?? '');
$country = trim($_POST['country'] ?? '');

// Portal admin creation flags
$createAdmin = ($_POST['create_admin'] ?? '') === '1';
$adminEmail = strtolower(trim($_POST['admin_email'] ?? ''));
$adminName = trim($_POST['admin_name'] ?? '');
$adminPassword = $_POST['admin_password'] ?? '';
$autoPassword = ($_POST['auto_password'] ?? '0') === '1';

// Validation
if (empty($name)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Company name is required'], 400))->send();
    exit;
}

if (empty($contactEmail) || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Valid contact email is required'], 400))->send();
    exit;
}

if (empty($contactName)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Contact name is required'], 400))->send();
    exit;
}

// Generate slug if not provided
if (empty($slug)) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $slug = trim($slug, '-');
}

// Validate slug format
if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/', $slug)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Slug must contain only lowercase letters, numbers, and hyphens'], 400))->send();
    exit;
}

// Check slug uniqueness for this client
$existing = Capsule::table('s3_backup_tenants')
    ->where('client_id', $clientId)
    ->where('slug', $slug)
    ->where('status', '!=', 'deleted')
    ->first();

if ($existing) {
    (new JsonResponse(['status' => 'fail', 'message' => 'A tenant with this slug already exists'], 400))->send();
    exit;
}

// Validate admin creation params if requested
if ($createAdmin) {
    // Default admin email/name to contact if not specified
    if (empty($adminEmail)) {
        $adminEmail = $contactEmail;
    }
    if (empty($adminName)) {
        $adminName = $contactName;
    }
    
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Valid admin email is required'], 400))->send();
        exit;
    }
    
    if (empty($adminName)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Admin name is required'], 400))->send();
        exit;
    }
    
    // Generate or validate password
    if ($autoPassword) {
        $adminPassword = bin2hex(random_bytes(8)); // 16 char password
    } elseif (strlen($adminPassword) < 8) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Password must be at least 8 characters'], 400))->send();
        exit;
    }
}

// Generate Ceph UID (placeholder - actual creation handled elsewhere)
$cephUid = 'tenant_' . $clientId . '_' . $slug . '_' . substr(md5(random_bytes(8)), 0, 8);

// Create tenant with all profile fields
$tenantId = Capsule::table('s3_backup_tenants')->insertGetId([
    'client_id' => $clientId,
    'name' => $name,
    'slug' => $slug,
    'contact_email' => $contactEmail,
    'contact_name' => $contactName,
    'contact_phone' => $contactPhone ?: null,
    'address_line1' => $addressLine1 ?: null,
    'address_line2' => $addressLine2 ?: null,
    'city' => $city ?: null,
    'state' => $state ?: null,
    'postal_code' => $postalCode ?: null,
    'country' => $country ?: null,
    'ceph_uid' => $cephUid,
    'status' => in_array($status, ['active', 'suspended']) ? $status : 'active',
    'created_at' => Capsule::raw('NOW()'),
    'updated_at' => Capsule::raw('NOW()'),
]);

$adminCreated = false;
$welcomeEmailSent = false;
$welcomeEmailSkipped = false;

// Create admin user if requested
if ($createAdmin && $tenantId) {
    // Check email uniqueness within tenant
    $existingUser = Capsule::table('s3_backup_tenant_users')
        ->where('tenant_id', $tenantId)
        ->where('email', $adminEmail)
        ->first();
    
    if (!$existingUser) {
        Capsule::table('s3_backup_tenant_users')->insert([
            'tenant_id' => $tenantId,
            'email' => $adminEmail,
            'name' => $adminName,
            'password_hash' => password_hash($adminPassword, PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => Capsule::raw('NOW()'),
            'updated_at' => Capsule::raw('NOW()'),
        ]);
        $adminCreated = true;
        
        // Send welcome email if auto-generated password
        if ($autoPassword) {
            $emailResult = TenantEmailService::sendWelcomeEmail(
                $adminEmail,
                $adminName,
                $name,
                $slug,
                $adminPassword,
                $clientId
            );
            
            if ($emailResult['status'] === 'success') {
                $welcomeEmailSent = true;
            } elseif ($emailResult['status'] === 'skipped') {
                $welcomeEmailSkipped = true;
                // Template not configured - use fallback
                $systemUrl = rtrim(\WHMCS\Config\Setting::getValue('SystemURL'), '/');
                $portalUrl = $systemUrl . '/portal/index.php?msp=' . urlencode($slug);
                
                $mspClient = Capsule::table('tblclients')
                    ->where('id', $clientId)
                    ->first(['companyname', 'firstname', 'lastname', 'email']);
                
                $mspName = $mspClient->companyname ?: trim(($mspClient->firstname ?? '') . ' ' . ($mspClient->lastname ?? ''));
                $supportEmail = $mspClient->email ?: 'support@eazybackup.ca';
                
                $html = buildWelcomeEmailHtml($adminName, $name, $adminEmail, $adminPassword, $portalUrl, $mspName, $supportEmail);
                $text = buildWelcomeEmailText($adminName, $name, $adminEmail, $adminPassword, $portalUrl, $mspName, $supportEmail);
                
                $welcomeEmailSent = TenantEmailService::sendFallbackEmail(
                    $adminEmail,
                    "Your backup portal account is ready - {$name}",
                    $html,
                    $text,
                    $supportEmail,
                    $mspName
                );
            }
        }
    }
}

// TODO: Create Ceph RGW user via Admin Ops API

$response = [
    'status' => 'success', 
    'tenant_id' => $tenantId,
    'message' => 'Tenant created successfully',
    'admin_created' => $adminCreated,
];

if ($welcomeEmailSent) {
    $response['welcome_email_sent'] = true;
}
if ($welcomeEmailSkipped) {
    $response['welcome_email_fallback'] = true;
}

(new JsonResponse($response, 200))->send();
exit;

/**
 * Build HTML welcome email (fallback when template not configured)
 */
function buildWelcomeEmailHtml(string $name, string $tenantName, string $email, string $password, string $portalUrl, string $mspName, string $supportEmail): string
{
    return "
    <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto;\">
        <h2 style=\"color: #1e293b;\">Welcome to your Backup Portal</h2>
        <p>Hi {$name},</p>
        <p>Your organization <strong>{$tenantName}</strong> has been set up with cloud backup services.</p>
        
        <div style=\"background: #f1f5f9; border-radius: 8px; padding: 20px; margin: 20px 0;\">
            <p style=\"margin: 0 0 10px 0;\"><strong>Portal URL:</strong><br>
            <a href=\"{$portalUrl}\" style=\"color: #0ea5e9;\">{$portalUrl}</a></p>
            
            <p style=\"margin: 0 0 10px 0;\"><strong>Email:</strong><br>{$email}</p>
            
            <p style=\"margin: 0;\"><strong>Temporary Password:</strong><br>
            <code style=\"background: #e2e8f0; padding: 4px 8px; border-radius: 4px;\">{$password}</code></p>
        </div>
        
        <p style=\"color: #ef4444;\"><strong>Important:</strong> Please change your password after your first login.</p>
        
        <p>If you have any questions, contact <a href=\"mailto:{$supportEmail}\">{$supportEmail}</a>.</p>
        
        <p style=\"color: #64748b; font-size: 14px;\">Best regards,<br>{$mspName}</p>
    </div>";
}

/**
 * Build plain text welcome email (fallback when template not configured)
 */
function buildWelcomeEmailText(string $name, string $tenantName, string $email, string $password, string $portalUrl, string $mspName, string $supportEmail): string
{
    return "Welcome to your Backup Portal\n\n"
        . "Hi {$name},\n\n"
        . "Your organization \"{$tenantName}\" has been set up with cloud backup services.\n\n"
        . "Portal URL: {$portalUrl}\n"
        . "Email: {$email}\n"
        . "Temporary Password: {$password}\n\n"
        . "Important: Please change your password after your first login.\n\n"
        . "If you have any questions, contact {$supportEmail}.\n\n"
        . "Best regards,\n{$mspName}";
}
