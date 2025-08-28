<?php
/**
 * Customer Portal Login Page
 * 
 * Provides authentication for end-customers to access their
 * dedicated portal for managing services and invoices.
 */

use WHMCS\Database\Capsule;

// Start session
session_start();

// Include WHMCS configuration
require_once __DIR__ . '/../../../../configuration.php';
require_once __DIR__ . '/../../../../init.php';

// Handle auto-login for MSP testing (admin impersonation)
if (isset($_GET['auto_login']) && isset($_SESSION['uid'])) {
    $customerId = (int) $_GET['auto_login'];
    
    // Verify the MSP owns this customer
    $clientId = (int) $_SESSION['uid'];
    $mspSettings = Capsule::table('msp_reseller_msp_settings')
        ->where('client_id', $clientId)
        ->first();
    
    if ($mspSettings) {
        $customer = Capsule::table('msp_reseller_customers')
            ->where('id', $customerId)
            ->where('msp_id', $mspSettings->id)
            ->first();
            
        if ($customer) {
            $_SESSION['customer_id'] = $customer->id;
            $_SESSION['customer_msp_id'] = $customer->msp_id;
            
            // Log the impersonation
            mspconnect_log_activity($customer->msp_id, $customer->id, 'admin_impersonation', 
                'MSP logged in as customer for testing');
            
            header('Location: dashboard.php');
            exit;
        }
    }
}

// Check if customer is already logged in
if (isset($_SESSION['customer_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle password reset request
if (isset($_POST['reset_password']) && isset($_POST['email'])) {
    try {
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            throw new Exception('Please enter a valid email address.');
        }
        
        $customer = Capsule::table('msp_reseller_customers')
            ->where('email', $email)
            ->where('status', 'active')
            ->first();
            
        if ($customer) {
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
            $emailManager = new EmailManager($customer->msp_id);
            $emailManager->sendPasswordResetEmail($customer->id, $resetToken);
            
            // Log activity
            mspconnect_log_activity($customer->msp_id, $customer->id, 'password_reset_requested', 
                'Password reset requested from portal');
        }
        
        // Always show success message for security (don't reveal if email exists)
        $success = 'If an account with that email exists, a password reset link has been sent.';
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Handle login form submission
if (isset($_POST['login'])) {
    try {
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        
        if (!$email || !$password) {
            throw new Exception('Please enter both email and password.');
        }
        
        // Find customer
        $customer = Capsule::table('msp_reseller_customers')
            ->where('email', $email)
            ->first();
            
        if (!$customer) {
            throw new Exception('Invalid email or password.');
        }
        
        // Check if account is active
        if ($customer->status !== 'active') {
            throw new Exception('Your account is currently ' . $customer->status . '. Please contact support.');
        }
        
        // Verify password
        if (!password_verify($password, $customer->password_hash)) {
            throw new Exception('Invalid email or password.');
        }
        
        // Set session
        $_SESSION['customer_id'] = $customer->id;
        $_SESSION['customer_msp_id'] = $customer->msp_id;
        
        // Update last login
        Capsule::table('msp_reseller_customers')
            ->where('id', $customer->id)
            ->update([
                'last_login' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        
        // Log activity
        mspconnect_log_activity($customer->msp_id, $customer->id, 'customer_login', 
            'Customer logged into portal');
        
        // Redirect to dashboard
        header('Location: dashboard.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        
        // Log failed login attempt
        if (isset($customer)) {
            mspconnect_log_activity($customer->msp_id, $customer->id, 'login_failed', 
                'Failed login attempt: ' . $e->getMessage());
        }
    }
}

// Get portal branding (use the first active MSP for general portal styling)
$portalBranding = Capsule::table('msp_reseller_company_profile')
    ->join('msp_reseller_msp_settings', 'msp_reseller_company_profile.msp_id', '=', 'msp_reseller_msp_settings.id')
    ->where('msp_reseller_msp_settings.status', 'active')
    ->select('msp_reseller_company_profile.*')
    ->first();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Arial', sans-serif;
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 300;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            height: 45px;
            border-radius: 8px;
            border: 2px solid #e1e5e9;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            height: 45px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .company-logo {
            max-width: 120px;
            margin-bottom: 15px;
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }
        
        .forgot-password a {
            color: #667eea;
            text-decoration: none;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .reset-form {
            display: none;
        }
        
        .back-to-login {
            color: #667eea;
            cursor: pointer;
            text-decoration: none;
        }
        
        .back-to-login:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .login-container {
                margin: 20px;
            }
            
            .login-header, .login-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <?php if ($portalBranding && $portalBranding->logo_filename): ?>
                <img src="/modules/addons/mspconnect/assets/logos/<?php echo htmlspecialchars($portalBranding->logo_filename); ?>" 
                     alt="Logo" class="company-logo">
            <?php endif; ?>
            <h2>Customer Portal</h2>
            <p style="margin: 0; opacity: 0.8;">Access your account</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="post" class="login-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="Enter your email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter your password" required>
                </div>
                
                <button type="submit" name="login" class="btn btn-login btn-block">
                    <i class="fa fa-sign-in"></i> Sign In
                </button>
            </form>
            
            <!-- Reset Password Form -->
            <form method="post" class="reset-form">
                <div class="form-group">
                    <label for="reset_email">Email Address</label>
                    <input type="email" class="form-control" id="reset_email" name="email" 
                           placeholder="Enter your email" required>
                </div>
                
                <button type="submit" name="reset_password" class="btn btn-info btn-block">
                    <i class="fa fa-envelope"></i> Send Reset Link
                </button>
                
                <div class="text-center" style="margin-top: 15px;">
                    <a href="#" class="back-to-login">
                        <i class="fa fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            </form>
            
            <div class="forgot-password">
                <a href="#" id="showResetForm">
                    <i class="fa fa-question-circle"></i> Forgot your password?
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#showResetForm').click(function(e) {
                e.preventDefault();
                $('.login-form').fadeOut(function() {
                    $('.reset-form').fadeIn();
                    $('.forgot-password').hide();
                });
            });
            
            $('.back-to-login').click(function(e) {
                e.preventDefault();
                $('.reset-form').fadeOut(function() {
                    $('.login-form').fadeIn();
                    $('.forgot-password').show();
                });
            });
        });
    </script>
</body>
</html>

<?php
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