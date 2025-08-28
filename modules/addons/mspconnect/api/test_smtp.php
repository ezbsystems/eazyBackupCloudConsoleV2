<?php
/**
 * MSPConnect - SMTP Test API Endpoint
 * Tests SMTP connection settings
 */

// Include WHMCS
require_once '../../../../../init.php';
use WHMCS\Authentication\CurrentUser;
use WHMCS\Database\Capsule;

// Include PHPMailer classes
require_once '../lib/Email/phpmailer/src/PHPMailer.php';
require_once '../lib/Email/phpmailer/src/SMTP.php';
require_once '../lib/Email/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

// Set JSON response header
header('Content-Type: application/json');

// Check if user is authenticated
$currentUser = new CurrentUser();
if (!$currentUser->isAuthenticatedUser()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get MSP ID from session or user client info
$clientId = $currentUser->client()->id ?? null;
if (!$clientId) {
    echo json_encode(['success' => false, 'message' => 'Client not found']);
    exit;
}

// Check if this client has MSPConnect service
try {
    $mspSettings = Capsule::table('msp_reseller_msp_settings')
        ->where('client_id', $clientId)
        ->first();
    
    if (!$mspSettings) {
        echo json_encode(['success' => false, 'message' => 'MSP account not found']);
        exit;
    }
    
    $mspId = $mspSettings->id;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Get SMTP settings from form or database
$smtpHost = $_POST['smtp_host'] ?? '';
$smtpPort = $_POST['smtp_port'] ?? 587;
$smtpEncryption = $_POST['smtp_encryption'] ?? 'tls';
$smtpUsername = $_POST['smtp_username'] ?? '';
$smtpPassword = $_POST['smtp_password'] ?? '';

// If no form data, get from database
if (empty($smtpHost)) {
    try {
        $smtpConfig = Capsule::table('msp_reseller_smtp_config')
            ->where('msp_id', $mspId)
            ->first();
        
        if ($smtpConfig) {
            $smtpHost = $smtpConfig->smtp_host;
            $smtpPort = $smtpConfig->smtp_port;
            $smtpEncryption = $smtpConfig->smtp_encryption;
            $smtpUsername = $smtpConfig->smtp_username;
            
            // Include helpers for decryption
            require_once '../lib/helpers.php';
            $smtpPassword = mspconnect_decrypt($smtpConfig->smtp_password);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error loading SMTP settings: ' . $e->getMessage()]);
        exit;
    }
}

// Validate required fields
if (empty($smtpHost) || empty($smtpPort) || empty($smtpUsername)) {
    echo json_encode(['success' => false, 'message' => 'SMTP host, port, and username are required']);
    exit;
}

// Test SMTP connection
try {
    
    $mail = new PHPMailer(true);
    
    // Server settings
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->Port = intval($smtpPort);
    
    // Set encryption
    if ($smtpEncryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($smtpEncryption === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    
    // Enable verbose debug output for testing
    $mail->SMTPDebug = 0; // Set to 2 for detailed debugging
    $mail->Debugoutput = function($str, $level) {
        // Capture debug output if needed
    };
    
    // Set timeout
    $mail->Timeout = 10;
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Test connection by sending to a test email or just connecting
    $testMode = $_POST['test_mode'] ?? 'connect'; // 'connect' or 'send'
    
    if ($testMode === 'send') {
        // Send a test email
        $testEmail = $_POST['test_email'] ?? $smtpUsername;
        
        $mail->setFrom($smtpUsername, 'MSPConnect SMTP Test');
        $mail->addAddress($testEmail);
        $mail->Subject = 'MSPConnect SMTP Test - ' . date('Y-m-d H:i:s');
        $mail->Body = 'This is a test email from MSPConnect to verify SMTP configuration. If you receive this email, your SMTP settings are working correctly.';
        
        $result = $mail->send();
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'SMTP connection successful and test email sent to ' . $testEmail
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'SMTP connection successful but failed to send test email: ' . $mail->ErrorInfo
            ]);
        }
    } else {
        // Just test connection
        $smtp = $mail->getSMTPInstance();
        $smtp->connect($smtpHost, $smtpPort);
        
        if ($smtp->authenticate($smtpUsername, $smtpPassword)) {
            $smtp->quit();
            echo json_encode([
                'success' => true, 
                'message' => 'SMTP connection and authentication successful'
            ]);
        } else {
            $smtp->quit();
            echo json_encode([
                'success' => false, 
                'message' => 'SMTP connection failed: Authentication error'
            ]);
        }
    }
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    
    // Provide more user-friendly error messages
    if (strpos($errorMessage, 'Connection refused') !== false) {
        $errorMessage = 'Connection refused. Please check the SMTP host and port.';
    } elseif (strpos($errorMessage, 'Username and Password not accepted') !== false) {
        $errorMessage = 'Authentication failed. Please check your username and password.';
    } elseif (strpos($errorMessage, 'SSL') !== false || strpos($errorMessage, 'TLS') !== false) {
        $errorMessage = 'SSL/TLS connection failed. Please check your encryption settings.';
    } elseif (strpos($errorMessage, 'timeout') !== false) {
        $errorMessage = 'Connection timeout. Please check the SMTP host and your network connection.';
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'SMTP test failed: ' . $errorMessage
    ]);
}