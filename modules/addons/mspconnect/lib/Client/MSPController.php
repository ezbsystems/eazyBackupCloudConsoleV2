<?php
/**
 * MSPController - Client Area Controller for MSPs
 * 
 * Handles all MSP-facing functionality including dashboard, customer management,
 * service plans, invoicing, and Stripe Connect integration.
 */

use WHMCS\Database\Capsule;

class MSPController
{
    private $clientId;
    private $vars;
    private $mspSettings;
    private $templatePath;
    
    public function __construct($clientId, $vars)
    {
        $this->clientId = $clientId;
        $this->vars = $vars;
        $this->templatePath = __DIR__ . '/../../templates/clientarea/';
        
        // Initialize or get MSP settings
        $this->initializeMSP();
    }
    
    /**
     * Initialize MSP settings record
     */
    private function initializeMSP()
    {
        $this->mspSettings = Capsule::table('msp_reseller_msp_settings')
            ->where('client_id', $this->clientId)
            ->first();
            
        if (!$this->mspSettings) {
            // Create new MSP record
            $mspId = Capsule::table('msp_reseller_msp_settings')->insertGetId([
                'client_id' => $this->clientId,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->mspSettings = Capsule::table('msp_reseller_msp_settings')
                ->where('id', $mspId)
                ->first();
                
            // Create default company profile
            $this->createDefaultCompanyProfile($mspId);
            
            // Create default email templates
            $this->createDefaultEmailTemplates($mspId);
            
            // Log activity
            mspconnect_log_activity($mspId, null, 'msp_initialized', 'MSP account initialized');
        }
    }
    
    /**
     * Create default company profile
     */
    private function createDefaultCompanyProfile($mspId)
    {
        // Get client info from WHMCS
        $client = Capsule::table('tblclients')
            ->where('id', $this->clientId)
            ->first();
            
        if ($client) {
            Capsule::table('msp_reseller_company_profile')->insert([
                'msp_id' => $mspId,
                'company_name' => $client->companyname ?: ($client->firstname . ' ' . $client->lastname),
                'contact_email' => $client->email,
                'address' => $client->address1,
                'city' => $client->city,
                'state' => $client->state,
                'postal_code' => $client->postcode,
                'country' => $client->country,
                'phone' => $client->phonenumber,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    /**
     * Create default email templates
     */
    private function createDefaultEmailTemplates($mspId)
    {
        $templates = [
            'welcome_email' => [
                'subject' => 'Welcome to {$msp_company_name}',
                'body_html' => '<h2>Welcome {$customer_first_name}!</h2><p>Your account has been created successfully.</p><p>You can access your customer portal at: <a href="{$portal_url}">{$portal_url}</a></p><p>Best regards,<br>{$msp_company_name}</p>',
                'body_text' => 'Welcome {$customer_first_name}! Your account has been created successfully. Portal: {$portal_url}'
            ],
            'invoice_created' => [
                'subject' => 'New Invoice #{$invoice_number} from {$msp_company_name}',
                'body_html' => '<h2>Invoice #{$invoice_number}</h2><p>Dear {$customer_first_name},</p><p>A new invoice has been generated for ${$invoice_total}.</p><p>Due Date: {$invoice_due_date}</p><p><a href="{$invoice_url}">View & Pay Invoice</a></p>',
                'body_text' => 'Invoice #{$invoice_number} for ${$invoice_total}. Due: {$invoice_due_date}. Pay at: {$invoice_url}'
            ],
            'payment_confirmation' => [
                'subject' => 'Payment Confirmation - Invoice #{$invoice_number}',
                'body_html' => '<h2>Payment Received</h2><p>Thank you {$customer_first_name}! We have received your payment of ${$payment_amount} for invoice #{$invoice_number}.</p>',
                'body_text' => 'Payment of ${$payment_amount} received for invoice #{$invoice_number}. Thank you!'
            ],
            'password_reset' => [
                'subject' => 'Password Reset Request',
                'body_html' => '<h2>Password Reset</h2><p>Click here to reset your password: <a href="{$password_reset_link}">Reset Password</a></p><p>This link expires in 24 hours.</p>',
                'body_text' => 'Reset your password: {$password_reset_link} (expires in 24 hours)'
            ]
        ];
        
        foreach ($templates as $templateName => $template) {
            Capsule::table('msp_reseller_email_templates')->insert([
                'msp_id' => $mspId,
                'template_name' => $templateName,
                'subject' => $template['subject'],
                'body_html' => $template['body_html'],
                'body_text' => $template['body_text'],
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    /**
     * Show MSP Dashboard
     */
    public function showDashboard()
    {
        try {
            // Get dashboard statistics
            $stats = $this->getDashboardStats();
            
            // Get recent activity
            $recentActivity = $this->getRecentActivity();
            
            // Load template
            $template = $this->loadTemplate('dashboard', [
                'msp_settings' => $this->mspSettings,
                'stats' => $stats,
                'recent_activity' => $recentActivity,
                'stripe_connected' => !empty($this->mspSettings->stripe_account_id)
            ]);
            
            // If template loading fails, return fallback HTML
            if (empty($template) || strpos($template, 'Template not found') !== false) {
                return $this->getFallbackDashboard($stats);
            }
            
            return $template;
            
        } catch (Exception $e) {
            // Return basic dashboard on error
            return $this->getFallbackDashboard([
                'total_customers' => 0,
                'total_services' => 0,
                'monthly_revenue' => '0.00',
                'pending_invoices' => 0
            ]);
        }
    }
    
    /**
     * Fallback dashboard HTML in case template fails
     */
    private function getFallbackDashboard($stats)
    {
        $html = '
        <div class="row">
            <div class="col-md-12">
                <h2>MSP Dashboard</h2>
                <div class="alert alert-info">
                    <strong>Welcome to MSPConnect!</strong> Your multi-tenant reseller platform is ready.
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3 class="text-primary">' . $stats['total_customers'] . '</h3>
                        <p>Total Customers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3 class="text-success">' . $stats['total_services'] . '</h3>
                        <p>Active Services</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3 class="text-info">$' . $stats['monthly_revenue'] . '</h3>
                        <p>Monthly Revenue</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3 class="text-warning">' . $stats['pending_invoices'] . '</h3>
                        <p>Pending Invoices</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Quick Actions</h3>
                    </div>
                    <div class="panel-body">
                        <a href="index.php?m=mspconnect&page=customers" class="btn btn-primary">
                            <i class="fa fa-users"></i> Manage Customers
                        </a>
                        <a href="index.php?m=mspconnect&page=plans" class="btn btn-success">
                            <i class="fa fa-list"></i> Service Plans
                        </a>
                        <a href="index.php?m=mspconnect&page=invoices" class="btn btn-info">
                            <i class="fa fa-file-invoice"></i> Invoices
                        </a>
                        <a href="index.php?m=mspconnect&page=settings" class="btn btn-default">
                            <i class="fa fa-cog"></i> Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Get dashboard statistics
     */
    private function getDashboardStats()
    {
        $mspId = $this->mspSettings->id;
        
        $totalCustomers = Capsule::table('msp_reseller_customers')
            ->where('msp_id', $mspId)
            ->where('status', 'active')
            ->count();
            
        $totalServices = Capsule::table('msp_reseller_services')
            ->join('msp_reseller_customers', 'msp_reseller_services.customer_id', '=', 'msp_reseller_customers.id')
            ->where('msp_reseller_customers.msp_id', $mspId)
            ->where('msp_reseller_services.status', 'active')
            ->count();
            
        $monthlyRevenue = Capsule::table('msp_reseller_invoices')
            ->join('msp_reseller_customers', 'msp_reseller_invoices.customer_id', '=', 'msp_reseller_customers.id')
            ->where('msp_reseller_customers.msp_id', $mspId)
            ->where('msp_reseller_invoices.status', 'paid')
            ->whereMonth('msp_reseller_invoices.paid_at', date('m'))
            ->whereYear('msp_reseller_invoices.paid_at', date('Y'))
            ->sum('total_amount');
            
        $pendingInvoices = Capsule::table('msp_reseller_invoices')
            ->join('msp_reseller_customers', 'msp_reseller_invoices.customer_id', '=', 'msp_reseller_customers.id')
            ->where('msp_reseller_customers.msp_id', $mspId)
            ->whereIn('msp_reseller_invoices.status', ['sent', 'overdue'])
            ->count();
            
        return [
            'total_customers' => $totalCustomers,
            'total_services' => $totalServices,
            'monthly_revenue' => number_format($monthlyRevenue, 2),
            'pending_invoices' => $pendingInvoices
        ];
    }
    
    /**
     * Get recent activity
     */
    private function getRecentActivity()
    {
        return Capsule::table('msp_reseller_activity_log')
            ->where('msp_id', $this->mspSettings->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }
    
    /**
     * Show customers list
     */
    public function showCustomers()
    {
        try {
            $action = $_GET['sub_action'] ?? 'list';
            
            // For now, just show the list regardless of action
            return $this->listCustomers();
            
        } catch (Exception $e) {
            return '<div class="alert alert-danger">
                <h4>Customer Management Error</h4>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <a href="index.php?m=mspconnect&page=dashboard" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>';
        }
    }
    
    /**
     * Show customer details
     */
    public function showCustomer()
    {
        return '<div class="alert alert-info">
            <h4>Customer Details</h4>
            <p>Customer detail view is being developed.</p>
            <a href="index.php?m=mspconnect&page=customers" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back to Customers
            </a>
        </div>';
    }
    
    /**
     * Show service plans
     */
    public function showPlans()
    {
        return '<div class="alert alert-info">
            <h4>Service Plans</h4>
            <p>Service plan management is being developed.</p>
            <a href="index.php?m=mspconnect&page=dashboard" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>';
    }
    
    /**
     * Show invoices
     */
    public function showInvoices()
    {
        return '<div class="alert alert-info">
            <h4>Invoice Management</h4>
            <p>Invoice management is being developed.</p>
            <a href="index.php?m=mspconnect&page=dashboard" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>';
    }
    
    /**
     * Show settings
     */
    public function showSettings()
    {
        try {
            // Handle form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                return $this->processSettingsUpdate();
            }

            // Get current company profile
            $companyProfile = Capsule::table('msp_reseller_company_profile')
                ->where('msp_id', $this->mspSettings->id)
                ->first();

            // Get current SMTP settings
            $smtpConfig = Capsule::table('msp_reseller_smtp_config')
                ->where('msp_id', $this->mspSettings->id)
                ->first();

            // Get current logo URL
            $logoUrl = '';
            if ($companyProfile && $companyProfile->logo_filename) {
                $logoPath = __DIR__ . '/../../uploads/logos/' . $companyProfile->logo_filename;
                if (file_exists($logoPath)) {
                    $logoUrl = '/modules/addons/mspconnect/uploads/logos/' . $companyProfile->logo_filename;
                }
            }

            // Try to load template, but fallback to safe render if it fails
            try {
                $templatePath = __DIR__ . '/../../templates/clientarea/settings.tpl';
                if (file_exists($templatePath)) {
                    // For now, return the rendered template content to ensure it works
                    return $this->renderSettingsTemplate([
                        'company_profile' => $companyProfile,
                        'smtp_config' => $smtpConfig,
                        'msp_settings' => $this->mspSettings,
                        'current_logo_url' => $logoUrl,
                        'success_message' => $_GET['success'] ?? null,
                        'error_message' => $_GET['error'] ?? null
                    ]);
                }
            } catch (Exception $e) {
                error_log('MSPConnect Template Error: ' . $e->getMessage());
            }

            // Fallback rendering
            return $this->renderSettingsTemplate([
                'company_profile' => $companyProfile,
                'smtp_config' => $smtpConfig,
                'msp_settings' => $this->mspSettings,
                'current_logo_url' => $logoUrl,
                'success_message' => $_GET['success'] ?? null,
                'error_message' => $_GET['error'] ?? null
            ]);

        } catch (Exception $e) {
            return '<div class="alert alert-danger">
                <h4>Settings Error</h4>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <a href="index.php?m=mspconnect&page=dashboard" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>';
        }
    }

    /**
     * Process settings form submission
     */
    private function processSettingsUpdate()
    {
        try {
            $tab = $_POST['tab'] ?? 'company';
            
            if ($tab === 'company') {
                return $this->updateCompanyProfile();
            } elseif ($tab === 'smtp') {
                return $this->updateSmtpSettings();
            }
            
            return $this->showSettings();
            
        } catch (Exception $e) {
            return '<div class="alert alert-danger">
                <h4>Update Error</h4>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
            </div>' . $this->showSettings();
        }
    }

    /**
     * Update company profile
     */
    private function updateCompanyProfile()
    {
        try {
            $data = [
                'company_name' => $_POST['company_name'] ?? '',
                'contact_email' => $_POST['contact_email'] ?? '',
                'address' => $_POST['address'] ?? '',
                'city' => $_POST['city'] ?? '',
                'state' => $_POST['state'] ?? '',
                'postal_code' => $_POST['postal_code'] ?? '',
                'country' => $_POST['country'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'website' => $_POST['website'] ?? '',
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Handle logo removal
            if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
                error_log('MSPConnect Debug: Processing logo removal for MSP ID ' . $this->mspSettings->id);
                $existingProfile = Capsule::table('msp_reseller_company_profile')
                    ->where('msp_id', $this->mspSettings->id)
                    ->first();
                
                if ($existingProfile && $existingProfile->logo_filename) {
                    // Delete physical file
                    $logoPath = __DIR__ . '/../../uploads/logos/' . $existingProfile->logo_filename;
                    if (file_exists($logoPath)) {
                        unlink($logoPath);
                        error_log('MSPConnect Debug: Deleted existing logo file - ' . $logoPath);
                    }
                }
                
                $data['logo_filename'] = null;
            }
            // Handle logo upload
            elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                error_log('MSPConnect Debug: Processing logo upload for MSP ID ' . $this->mspSettings->id);
                error_log('MSPConnect Debug: $_FILES[logo] - ' . print_r($_FILES['logo'], true));
                
                $logoFilename = $this->handleLogoUpload($_FILES['logo']);
                if ($logoFilename) {
                    error_log('MSPConnect Debug: Logo upload successful, filename: ' . $logoFilename);
                    
                    // Remove old logo if exists
                    $existingProfile = Capsule::table('msp_reseller_company_profile')
                        ->where('msp_id', $this->mspSettings->id)
                        ->first();
                    
                    if ($existingProfile && $existingProfile->logo_filename) {
                        $oldLogoPath = __DIR__ . '/../../uploads/logos/' . $existingProfile->logo_filename;
                        if (file_exists($oldLogoPath)) {
                            unlink($oldLogoPath);
                            error_log('MSPConnect Debug: Deleted old logo file - ' . $oldLogoPath);
                        }
                    }
                    
                    $data['logo_filename'] = $logoFilename;
                } else {
                    error_log('MSPConnect Debug: Logo upload failed - handleLogoUpload returned null');
                }
            } else {
                // Debug file upload issues
                if (isset($_FILES['logo'])) {
                    error_log('MSPConnect Debug: Logo file upload error - Error code: ' . $_FILES['logo']['error']);
                    error_log('MSPConnect Debug: Full $_FILES[logo] array - ' . print_r($_FILES['logo'], true));
                } else {
                    error_log('MSPConnect Debug: No logo file in $_FILES array');
                    error_log('MSPConnect Debug: Full $_FILES array - ' . print_r($_FILES, true));
                }
            }

            // Check if profile exists
            $existingProfile = Capsule::table('msp_reseller_company_profile')
                ->where('msp_id', $this->mspSettings->id)
                ->first();

            if ($existingProfile) {
                // Update existing profile
                Capsule::table('msp_reseller_company_profile')
                    ->where('msp_id', $this->mspSettings->id)
                    ->update($data);
            } else {
                // Create new profile
                $data['msp_id'] = $this->mspSettings->id;
                $data['created_at'] = date('Y-m-d H:i:s');
                Capsule::table('msp_reseller_company_profile')->insert($data);
            }

            // Log activity
            mspconnect_log_activity($this->mspSettings->id, null, 'company_profile_updated', 
                'Company profile updated');

            // Redirect with success message
            header('Location: index.php?m=mspconnect&page=settings&success=profile_updated');
            exit;

        } catch (Exception $e) {
            // Redirect with error message
            header('Location: index.php?m=mspconnect&page=settings&error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    /**
     * Handle logo file upload
     */
    private function handleLogoUpload($file)
    {
        try {
            // Debug: Log file upload attempt
            error_log('MSPConnect Logo Upload Debug: Starting upload for MSP ID ' . $this->mspSettings->id);
            error_log('MSPConnect Logo Upload Debug: File info - ' . print_r($file, true));
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                error_log('MSPConnect Logo Upload Debug: Invalid file type - ' . $file['type']);
                throw new Exception('Invalid file type. Please upload JPEG, PNG, GIF, or WebP images only.');
            }

            // Validate file size (max 2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                error_log('MSPConnect Logo Upload Debug: File too large - ' . $file['size'] . ' bytes');
                throw new Exception('File size too large. Maximum size is 2MB.');
            }

            // Create upload directory if it doesn't exist
            $uploadDir = __DIR__ . '/../../uploads/logos/';
            error_log('MSPConnect Logo Upload Debug: Upload directory - ' . $uploadDir);
            
            if (!is_dir($uploadDir)) {
                error_log('MSPConnect Logo Upload Debug: Creating directory - ' . $uploadDir);
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log('MSPConnect Logo Upload Debug: Failed to create directory');
                    throw new Exception('Failed to create upload directory.');
                }
            }

            // Check directory permissions
            if (!is_writable($uploadDir)) {
                error_log('MSPConnect Logo Upload Debug: Directory not writable - ' . $uploadDir);
                throw new Exception('Upload directory is not writable.');
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'msp_' . $this->mspSettings->id . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $filename;
            
            error_log('MSPConnect Logo Upload Debug: Target path - ' . $targetPath);
            error_log('MSPConnect Logo Upload Debug: Temp file - ' . $file['tmp_name']);

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                error_log('MSPConnect Logo Upload Debug: File uploaded successfully to - ' . $targetPath);
                return $filename;
            } else {
                error_log('MSPConnect Logo Upload Debug: move_uploaded_file failed');
                error_log('MSPConnect Logo Upload Debug: PHP Error - ' . error_get_last()['message'] ?? 'No PHP error');
                throw new Exception('Failed to upload logo file.');
            }

        } catch (Exception $e) {
            error_log('MSPConnect Logo Upload Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update SMTP settings
     */
    private function updateSmtpSettings()
    {
        try {
            $data = [
                'smtp_host' => $_POST['smtp_host'] ?? '',
                'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
                'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                'smtp_username' => $_POST['smtp_username'] ?? '',
                'status' => $_POST['smtp_status'] ?? 'active',
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Only update password if provided
            if (!empty($_POST['smtp_password'])) {
                $data['smtp_password'] = mspconnect_encrypt($_POST['smtp_password']);
            }

            // Check if SMTP config exists
            $existingConfig = Capsule::table('msp_reseller_smtp_config')
                ->where('msp_id', $this->mspSettings->id)
                ->first();

            if ($existingConfig) {
                // Update existing config
                Capsule::table('msp_reseller_smtp_config')
                    ->where('msp_id', $this->mspSettings->id)
                    ->update($data);
            } else {
                // Create new config
                $data['msp_id'] = $this->mspSettings->id;
                $data['created_at'] = date('Y-m-d H:i:s');
                if (empty($data['smtp_password'])) {
                    $data['smtp_password'] = '';
                }
                Capsule::table('msp_reseller_smtp_config')->insert($data);
            }

            // Log activity
            mspconnect_log_activity($this->mspSettings->id, null, 'smtp_settings_updated', 
                'SMTP settings updated');

            // Redirect with success message
            header('Location: index.php?m=mspconnect&page=settings&success=smtp_updated');
            exit;

        } catch (Exception $e) {
            // Redirect with error message
            header('Location: index.php?m=mspconnect&page=settings&error=' . urlencode($e->getMessage()));
            exit;
        }
    }
    
    /**
     * Show branding settings
     */
    public function showBranding()
    {
        return '<div class="alert alert-info">
            <h4>Company Branding</h4>
            <p>Branding settings are being developed.</p>
            <a href="index.php?m=mspconnect&page=dashboard" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>';
    }
    
    /**
     * Show email templates
     */
    public function showEmailTemplates()
    {
        return '<div class="alert alert-info">
            <h4>Email Templates</h4>
            <p>Email template management is being developed.</p>
            <a href="index.php?m=mspconnect&page=dashboard" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>';
    }
    
    /**
     * List all customers
     */
    private function listCustomers()
    {
        try {
            $customers = Capsule::table('msp_reseller_customers')
                ->leftJoin('msp_reseller_services', 'msp_reseller_customers.id', '=', 'msp_reseller_services.customer_id')
                ->where('msp_reseller_customers.msp_id', $this->mspSettings->id)
                ->select('msp_reseller_customers.*', 
                    Capsule::raw('COUNT(msp_reseller_services.id) as service_count'))
                ->groupBy('msp_reseller_customers.id')
                ->orderBy('msp_reseller_customers.created_at', 'desc')
                ->get();
                
            return $this->loadTemplate('customers', [
                'customers' => $customers,
                'action' => 'list'
            ]);
        } catch (Exception $e) {
            return '<div class="alert alert-danger">
                <h4>Error Loading Customers</h4>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <a href="index.php?m=mspconnect&page=dashboard" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>';
        }
    }
    

    
    /**
     * Add new customer
     */
    private function addCustomer()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->processAddCustomer();
        }
        
        return $this->loadTemplate('customer_form', [
            'action' => 'add',
            'customer' => null
        ]);
    }
    
    /**
     * Process add customer form
     */
    private function processAddCustomer()
    {
        try {
            $data = [
                'msp_id' => $this->mspSettings->id,
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'email' => $_POST['email'],
                'company' => $_POST['company'] ?? null,
                'phone' => $_POST['phone'] ?? null,
                'address' => $_POST['address'] ?? null,
                'city' => $_POST['city'] ?? null,
                'state' => $_POST['state'] ?? null,
                'postal_code' => $_POST['postal_code'] ?? null,
                'country' => $_POST['country'] ?? null,
                'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $customerId = Capsule::table('msp_reseller_customers')->insertGetId($data);
            
            // Create Stripe customer if connected
            if ($this->mspSettings->stripe_account_id) {
                $this->createStripeCustomer($customerId);
            }
            
            // Log activity
            mspconnect_log_activity($this->mspSettings->id, $customerId, 'customer_created', 
                'Customer created: ' . $data['first_name'] . ' ' . $data['last_name']);
            
            return '<div class="alert alert-success">Customer created successfully!</div>' . $this->listCustomers();
            
        } catch (Exception $e) {
            return '<div class="alert alert-danger">Error creating customer: ' . $e->getMessage() . '</div>' . $this->addCustomer();
        }
    }
    
    /**
     * Create Stripe customer
     */
    private function createStripeCustomer($customerId)
    {
        try {
            require_once __DIR__ . '/../Stripe/StripeManager.php';
            
            $customer = Capsule::table('msp_reseller_customers')->where('id', $customerId)->first();
            $stripeManager = new StripeManager($this->vars);
            
            $stripeCustomerId = $stripeManager->createCustomer(
                $this->mspSettings->stripe_account_id,
                [
                    'name' => $customer->first_name . ' ' . $customer->last_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'address' => [
                        'line1' => $customer->address,
                        'city' => $customer->city,
                        'state' => $customer->state,
                        'postal_code' => $customer->postal_code,
                        'country' => $customer->country
                    ]
                ]
            );
            
            // Update customer with Stripe ID (only if we got a valid ID)
            if ($stripeCustomerId) {
                Capsule::table('msp_reseller_customers')
                    ->where('id', $customerId)
                    ->update(['stripe_customer_id' => $stripeCustomerId]);
            }
        } catch (Exception $e) {
            // Log error but don't fail customer creation
            error_log('MSPConnect: Failed to create Stripe customer: ' . $e->getMessage());
        }
    }
    

    
    /**
     * List service plans
     */
    private function listPlans()
    {
        try {
            $plans = Capsule::table('msp_reseller_plans')
                ->where('msp_id', $this->mspSettings->id)
                ->orderBy('sort_order')
                ->get();
                
            return $this->loadTemplate('plans', [
                'plans' => $plans,
                'action' => 'list'
            ]);
        } catch (Exception $e) {
            return '<div class="alert alert-danger">Error loading plans: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    /**
     * Handle Stripe Connect OAuth
     */
    public function handleStripeConnect()
    {
        try {
            require_once __DIR__ . '/../Stripe/StripeManager.php';
            
            $stripeManager = new StripeManager($this->vars);
            
            // Handle OAuth callback
            if (isset($_GET['code'])) {
                return $stripeManager->handleOAuthCallback($this->mspSettings->id, $_GET['code']);
            }
            
            // Handle disconnect
            if (isset($_GET['disconnect'])) {
                return $stripeManager->disconnectAccount($this->mspSettings->id);
            }
            
            // Show connect interface
            return $this->loadTemplate('stripe_connect', [
                'stripe_connected' => !empty($this->mspSettings->stripe_account_id),
                'connect_url' => $stripeManager->getConnectUrl(),
                'msp_settings' => $this->mspSettings
            ]);
            
        } catch (Exception $e) {
            return '<div class="alert alert-danger">
                <h4><i class="fa fa-exclamation-triangle"></i> Stripe Configuration Error</h4>
                <p>Unable to initialize Stripe integration: ' . htmlspecialchars($e->getMessage()) . '</p>
                <hr>
                <p><strong>Required:</strong> Please install the Stripe PHP library to enable payment processing.</p>
                <p>You can install it via Composer:</p>
                <code>composer require stripe/stripe-php</code>
            </div>';
        }
    }
    
    /**
     * Load template with variables (simplified)
     */
    private function loadTemplate($templateName, $vars = [])
    {
        // For settings, ensure logo URL is available
        if ($templateName === 'settings') {
            // Add current logo URL to template variables
            $logoUrl = '';
            if ($vars['company_profile'] && $vars['company_profile']->logo_filename) {
                $logoPath = __DIR__ . '/../../uploads/logos/' . $vars['company_profile']->logo_filename;
                if (file_exists($logoPath)) {
                    $logoUrl = '/modules/addons/mspconnect/uploads/logos/' . $vars['company_profile']->logo_filename;
                }
            }
            $vars['current_logo_url'] = $logoUrl;
            
            return $this->renderSettingsTemplate($vars);
        }
        
        // For other templates, continue with fallback system
        switch ($templateName) {
            case 'dashboard':
                return $this->getFallbackDashboard($vars['stats'] ?? []);
                
            case 'customers':
                return '<div class="alert alert-info">
                    <h4>Customer Management</h4>
                    <p>Customer management interface is being developed.</p>
                    <p>You have ' . (count($vars['customers'] ?? [])) . ' customers.</p>
                </div>';
                
            default:
                return '<div class="alert alert-warning">
                    <h4>Template: ' . htmlspecialchars($templateName) . '</h4>
                    <p>This feature is being developed.</p>
                    <a href="index.php?m=mspconnect&page=dashboard" class="btn btn-default">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>';
        }
    }

    /**
     * Render settings template using Smarty templates with fallback
     */
    private function renderSettingsTemplate($vars)
    {
        // Get current logo URL
        $logoUrl = $vars['current_logo_url'] ?? '';

        // Convert success/error messages to user-friendly text
        $successMessage = '';
        $errorMessage = '';
        
        if ($vars['success_message']) {
            switch ($vars['success_message']) {
                case 'profile_updated':
                    $successMessage = 'Company profile updated successfully!';
                    break;
                case 'smtp_updated':
                    $successMessage = 'SMTP settings updated successfully!';
                    break;
                default:
                    $successMessage = 'Settings updated successfully!';
            }
        }
        
        if ($vars['error_message']) {
            $errorMessage = $vars['error_message'];
        }

        // Try to load the actual Smarty template content
        $companyProfileContent = '';
        $smtpContent = '';
        
        try {
            // Load company profile template
            $companyTemplatePath = __DIR__ . '/../../templates/clientarea/settings_company_profile.tpl';
            if (file_exists($companyTemplatePath)) {
                $companyProfileContent = $this->processSmartyTemplate($companyTemplatePath, $vars);
            }
            
            // Load SMTP template
            $smtpTemplatePath = __DIR__ . '/../../templates/clientarea/settings_smtp.tpl';
            if (file_exists($smtpTemplatePath)) {
                $smtpContent = $this->processSmartyTemplate($smtpTemplatePath, $vars);
            }
        } catch (Exception $e) {
            error_log('MSPConnect: Template processing error: ' . $e->getMessage());
            // Continue with fallback if template processing fails
        }

        // If template processing failed, use fallback
        if (empty($companyProfileContent)) {
            $companyProfileContent = $this->getCompanyProfileTemplate($vars['company_profile'], $logoUrl);
        }
        if (empty($smtpContent)) {
            $smtpContent = $this->getSmtpTemplate($vars['smtp_config']);
        }

        return $this->getFallbackSettingsTemplate($vars, $logoUrl, $successMessage, $errorMessage, $companyProfileContent, $smtpContent);
    }

    /**
     * Process Smarty template with basic variable replacement
     */
    private function processSmartyTemplate($templatePath, $vars)
    {
        $content = file_get_contents($templatePath);
        if (!$content) {
            return '';
        }

        // Basic Smarty variable replacement
        $replacements = [];
        
        // Company profile variables
        if (isset($vars['company_profile']) && $vars['company_profile']) {
            $profile = $vars['company_profile'];
            $replacements['{$company_profile->company_name|default:\'\'|escape}'] = htmlspecialchars($profile->company_name ?? '');
            $replacements['{$company_profile->contact_email|default:\'\'|escape}'] = htmlspecialchars($profile->contact_email ?? '');
            $replacements['{$company_profile->phone|default:\'\'|escape}'] = htmlspecialchars($profile->phone ?? '');
            $replacements['{$company_profile->website|default:\'\'|escape}'] = htmlspecialchars($profile->website ?? '');
            $replacements['{$company_profile->address|default:\'\'|escape}'] = htmlspecialchars($profile->address ?? '');
            $replacements['{$company_profile->city|default:\'\'|escape}'] = htmlspecialchars($profile->city ?? '');
            $replacements['{$company_profile->state|default:\'\'|escape}'] = htmlspecialchars($profile->state ?? '');
            $replacements['{$company_profile->postal_code|default:\'\'|escape}'] = htmlspecialchars($profile->postal_code ?? '');
            $replacements['{$company_profile->country|default:\'\'|escape}'] = htmlspecialchars($profile->country ?? '');
        } else {
            // Default empty values
            $replacements['{$company_profile->company_name|default:\'\'|escape}'] = '';
            $replacements['{$company_profile->contact_email|default:\'\'|escape}'] = '';
            $replacements['{$company_profile->phone|default:\'\'|escape}'] = '';
            $replacements['{$company_profile->website|default:\'\'|escape}'] = '';
            $replacements['{$company_profile->address|default:\'\'|escape}'] = '';
            $replacements['{$company_profile->city|default:\'\'|escape}'] = '';
            $replacements['{$company_profile->state|default:\'\'|escape}'] = '';
            $replacements['{$company_profile->postal_code|default:\'\'|escape}'] = '';
            $replacements['{$company_profile->country|default:\'\'|escape}'] = '';
        }

        // SMTP config variables
        if (isset($vars['smtp_config']) && $vars['smtp_config']) {
            $smtp = $vars['smtp_config'];
            $replacements['{$smtp_config->smtp_host|default:\'\'|escape}'] = htmlspecialchars($smtp->smtp_host ?? '');
            $replacements['{$smtp_config->smtp_username|default:\'\'|escape}'] = htmlspecialchars($smtp->smtp_username ?? '');
            
            // Handle port selection
            $port = $smtp->smtp_port ?? 587;
            $replacements['{if ($smtp_config->smtp_port|default:587) == 25}selected{/if}'] = ($port == 25) ? 'selected' : '';
            $replacements['{if ($smtp_config->smtp_port|default:587) == 587}selected{/if}'] = ($port == 587) ? 'selected' : '';
            $replacements['{if ($smtp_config->smtp_port|default:587) == 465}selected{/if}'] = ($port == 465) ? 'selected' : '';
            $replacements['{if ($smtp_config->smtp_port|default:587) == 2525}selected{/if}'] = ($port == 2525) ? 'selected' : '';
            
            // Handle encryption selection
            $encryption = $smtp->smtp_encryption ?? 'tls';
            $replacements['{if ($smtp_config->smtp_encryption|default:\'tls\') == \'none\'}selected{/if}'] = ($encryption == 'none') ? 'selected' : '';
            $replacements['{if ($smtp_config->smtp_encryption|default:\'tls\') == \'tls\'}selected{/if}'] = ($encryption == 'tls') ? 'selected' : '';
            $replacements['{if ($smtp_config->smtp_encryption|default:\'tls\') == \'ssl\'}selected{/if}'] = ($encryption == 'ssl') ? 'selected' : '';
            
            // Handle status selection
            $status = $smtp->status ?? 'active';
            $replacements['{if ($smtp_config->status|default:\'active\') == \'active\'}selected{/if}'] = ($status == 'active') ? 'selected' : '';
            $replacements['{if ($smtp_config->status|default:\'active\') == \'inactive\'}selected{/if}'] = ($status == 'inactive') ? 'selected' : '';
            
            // Handle conditional display for password field
            $replacements['{if $smtp_config}'] = $smtp ? '' : '';
            $replacements['{else}'] = !$smtp ? '' : '';
            $replacements['{/if}'] = '';
        } else {
            // Default SMTP values
            $replacements['{$smtp_config->smtp_host|default:\'\'|escape}'] = '';
            $replacements['{$smtp_config->smtp_username|default:\'\'|escape}'] = '';
            $replacements['{if ($smtp_config->smtp_port|default:587) == 25}selected{/if}'] = '';
            $replacements['{if ($smtp_config->smtp_port|default:587) == 587}selected{/if}'] = 'selected';
            $replacements['{if ($smtp_config->smtp_port|default:587) == 465}selected{/if}'] = '';
            $replacements['{if ($smtp_config->smtp_port|default:587) == 2525}selected{/if}'] = '';
            $replacements['{if ($smtp_config->smtp_encryption|default:\'tls\') == \'none\'}selected{/if}'] = '';
            $replacements['{if ($smtp_config->smtp_encryption|default:\'tls\') == \'tls\'}selected{/if}'] = 'selected';
            $replacements['{if ($smtp_config->smtp_encryption|default:\'tls\') == \'ssl\'}selected{/if}'] = '';
            $replacements['{if ($smtp_config->status|default:\'active\') == \'active\'}selected{/if}'] = 'selected';
            $replacements['{if ($smtp_config->status|default:\'active\') == \'inactive\'}selected{/if}'] = '';
            $replacements['{if $smtp_config}'] = '';
            $replacements['{else}'] = '';
            $replacements['{/if}'] = '';
        }

        // Logo URL
        $replacements['{$current_logo_url|default:\'\'}'] = htmlspecialchars($vars['current_logo_url'] ?? '');
        
        // Logo display conditional
        if (!empty($vars['current_logo_url'])) {
            $replacements['{if !$current_logo_url}style="display: none;"{/if}'] = '';
        } else {
            $replacements['{if !$current_logo_url}style="display: none;"{/if}'] = 'style="display: none;"';
        }

        // Apply all replacements
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        return $content;
    }

    /**
     * Fallback settings template with processed content
     */
    private function getFallbackSettingsTemplate($vars, $logoUrl, $successMessage, $errorMessage, $companyProfileContent = '', $smtpContent = '')
    {
        $companyProfile = $vars['company_profile'];
        $smtpConfig = $vars['smtp_config'];

        // Success/Error messages
        $alertHtml = '';
        if ($successMessage) {
            $alertHtml = '<div class="mb-6 bg-emerald-900 border border-emerald-700 text-emerald-200 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    ' . htmlspecialchars($successMessage) . '
                </div>
            </div>
            <script>
                setTimeout(function() {
                    const alert = document.querySelector(".bg-emerald-900");
                    if (alert) {
                        alert.style.opacity = "0";
                        alert.style.transition = "opacity 0.5s";
                        setTimeout(() => alert.remove(), 500);
                    }
                }, 5000);
            </script>';
        } elseif ($errorMessage) {
            $alertHtml = '<div class="mb-6 bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    Error: ' . htmlspecialchars($errorMessage) . '
                </div>
            </div>';
        }

                 return '
 <div class="min-h-screen bg-[#11182759] text-slate-200">
     <div class="container mx-auto px-4 pb-8">
         
         <!-- Message Container for JavaScript -->
         <div id="message-container" class="fixed top-4 right-4 z-50 max-w-sm w-full"></div>
         
         ' . $alertHtml . '

         <!-- Header -->
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center mb-8">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <h2 class="text-2xl font-semibold text-white">Settings</h2>
            </div>
        </div>

        <!-- Fixed Container for Tabs and Content -->
        <div class="w-full max-w-7xl mx-auto">
            <!-- Tabs -->
            <div class="mb-8">
                <nav class="flex space-x-8" aria-label="Tabs">
                    <a href="#company" onclick="showTab(\'company\')" id="tab-company" class="tab-link active py-2 px-1 border-b-2 border-sky-600 font-medium text-sm text-sky-400 whitespace-nowrap">
                        Company Profile
                    </a>
                    <a href="#smtp" onclick="showTab(\'smtp\')" id="tab-smtp" class="tab-link py-2 px-1 border-b-2 border-transparent font-medium text-sm text-slate-400 hover:text-slate-200 hover:border-slate-300 whitespace-nowrap">
                        Email Settings
                    </a>
                </nav>
            </div>

            <!-- Company Profile Tab -->
            <div id="content-company" class="tab-content">
                ' . $companyProfileContent . '
            </div>

            <!-- SMTP Settings Tab -->
            <div id="content-smtp" class="tab-content hidden">
                ' . $smtpContent . '
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    const contents = document.querySelectorAll(\'.tab-content\');
    contents.forEach(content => content.classList.add(\'hidden\'));
    
    const tabs = document.querySelectorAll(\'.tab-link\');
    tabs.forEach(tab => {
        tab.classList.remove(\'active\', \'border-sky-600\', \'text-sky-400\');
        tab.classList.add(\'border-transparent\', \'text-slate-400\');
    });
    
    document.getElementById(\'content-\' + tabName).classList.remove(\'hidden\');
    
    const activeTab = document.getElementById(\'tab-\' + tabName);
    activeTab.classList.add(\'active\', \'border-sky-600\', \'text-sky-400\');
    activeTab.classList.remove(\'border-transparent\', \'text-slate-400\');
}

function removeLogo() {
    if (confirm(\'Are you sure you want to remove the current logo?\')) {
        document.getElementById(\'remove_logo\').value = \'1\';
        const logoContainer = document.getElementById(\'currentLogoContainer\');
        if (logoContainer) {
            logoContainer.style.display = \'none\';
        }
        showMessage(\'Logo will be removed when you save the form.\', \'warning\');
    }
}

function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(\'logoPreview\');
            if (preview) {
                preview.innerHTML = \'<div class="mb-4"><label class="block text-sm font-medium text-slate-300 mb-2">New Logo Preview:</label><img src="\' + e.target.result + \'" alt="Logo Preview" class="max-w-xs max-h-32 object-contain border border-slate-600 rounded" /></div>\';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearNewLogo() {
    const logoInput = document.getElementById(\'logo\');
    const preview = document.getElementById(\'new-logo-preview-container\');
    if (logoInput) {
        logoInput.value = \'\';
    }
    if (preview) {
        preview.style.display = \'none\';
    }
}

function previewLogo(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file type
        const allowedTypes = [\'image/jpeg\', \'image/jpg\', \'image/png\', \'image/gif\', \'image/webp\'];
        if (!allowedTypes.includes(file.type)) {
            alert(\'Please select a valid image file (JPEG, PNG, GIF, or WebP)\');
            input.value = \'\';
            return;
        }
        
        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            alert(\'Logo file size must be less than 2MB\');
            input.value = \'\';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(\'new-logo-preview\');
            const container = document.getElementById(\'new-logo-preview-container\');
            
            if (preview && container) {
                preview.src = e.target.result;
                container.style.display = \'block\';
            }
        };
        reader.readAsDataURL(file);
    }
}

function removeLogo() {
    if (confirm(\'Are you sure you want to remove the current logo?\')) {
        const logoContainer = document.getElementById(\'current-logo-container\');
        const removeInput = document.getElementById(\'remove_logo\');
        
        if (logoContainer) {
            logoContainer.style.display = \'none\';
        }
        
        if (removeInput) {
            removeInput.value = \'1\';
        }
        
        alert(\'Logo will be removed when you save the form\');
    }
}

function testSmtpConnection() {
    alert(\'SMTP testing functionality is being implemented\');
}

function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const eyeOpen = document.getElementById(fieldId + \'_eye_open\');
    const eyeClosed = document.getElementById(fieldId + \'_eye_closed\');
    
    if (field.type === \'password\') {
        field.type = \'text\';
        if (eyeOpen) eyeOpen.classList.add(\'hidden\');
        if (eyeClosed) eyeClosed.classList.remove(\'hidden\');
    } else {
        field.type = \'password\';
        if (eyeOpen) eyeOpen.classList.remove(\'hidden\');
        if (eyeClosed) eyeClosed.classList.add(\'hidden\');
    }
}

// Initialize country/state functionality when page loads
document.addEventListener(\'DOMContentLoaded\', function() {
    if (typeof initializeCountryState === \'function\') {
        initializeCountryState();
    }
    
    if (typeof initializeLogoPreview === \'function\') {
        initializeLogoPreview();
    }
});

function showMessage(message, type) {
    alert(message);
}
</script>
 
 <!-- Include Settings JavaScript -->
 <script src="/modules/addons/mspconnect/assets/js/settings.js"></script>
         ';
    }



     /**
      * Get MSP logo URL for use in templates, emails, etc.
      * 
      * @param int|null $mspId Optional MSP ID, uses current MSP if not provided
      * @return string|null Logo URL or null if no logo
      */
     public static function getMspLogoUrl($mspId = null)
     {
         try {
             if (!$mspId) {
                 return null;
             }

             $companyProfile = Capsule::table('msp_reseller_company_profile')
                 ->where('msp_id', $mspId)
                 ->first();

             if ($companyProfile && $companyProfile->logo_filename) {
                 // Check if file exists
                 $logoPath = __DIR__ . '/../../uploads/logos/' . $companyProfile->logo_filename;
                 if (file_exists($logoPath)) {
                     return '/modules/addons/mspconnect/uploads/logos/' . $companyProfile->logo_filename;
                 }
             }

             return null;

         } catch (Exception $e) {
             error_log('MSPConnect: Error getting logo URL: ' . $e->getMessage());
             return null;
         }
     }

     /**
      * Get MSP company information for use in templates, emails, etc.
      * 
      * @param int|null $mspId Optional MSP ID, uses current MSP if not provided
      * @return object|null Company profile or null if not found
      */
     public static function getMspCompanyInfo($mspId = null)
     {
         try {
             if (!$mspId) {
                 return null;
             }

             return Capsule::table('msp_reseller_company_profile')
                 ->where('msp_id', $mspId)
                 ->first();

         } catch (Exception $e) {
             error_log('MSPConnect: Error getting company info: ' . $e->getMessage());
             return null;
         }
     }

     /**
      * Get company profile template HTML
      */
     private function getCompanyProfileTemplate($companyProfile, $logoUrl)
     {
         return '<div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg">
             <div class="p-6 border-b border-slate-700">
                 <div class="flex items-start">
                     <div class="flex-shrink-0 border-2 border-sky-600 p-3 rounded-md">
                         <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-sky-600 size-8">
                             <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m2.25-18h15M5.25 21V9.375c0-.621.504-1.125 1.125-1.125h3.375m-5.625 12h10.5m-10.5 0V11.25m0 9.75h10.5m0 0V9.375c0-.621.504-1.125 1.125-1.125h3.375M8.25 21V9.375c0-.621.504-1.125 1.125-1.125h3.375m0 0V21.75M15 10.5h3.375c.621 0 1.125.504 1.125 1.125v6.75c0 .621-.504 1.125-1.125 1.125h-3.375M15 10.5V21.75" />
                         </svg>
                     </div>
                     <div class="ml-4">
                         <h4 class="text-lg font-medium text-white">Company Information</h4>
                         <span class="text-sm text-slate-400">Update your company details and branding</span>
                     </div>
                 </div>
             </div>

             <form method="POST" enctype="multipart/form-data" class="p-6">
                 <input type="hidden" name="tab" value="company">
                 
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                     <div>
                         <label for="company_name" class="block text-sm font-medium text-slate-300 mb-2">Company Name *</label>
                         <input type="text" name="company_name" id="company_name" required
                             value="' . htmlspecialchars($companyProfile->company_name ?? '') . '"
                             class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                     </div>
                     <div>
                         <label for="contact_email" class="block text-sm font-medium text-slate-300 mb-2">Contact Email *</label>
                         <input type="email" name="contact_email" id="contact_email" required
                             value="' . htmlspecialchars($companyProfile->contact_email ?? '') . '"
                             class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                     </div>
                     <div>
                         <label for="phone" class="block text-sm font-medium text-slate-300 mb-2">Phone Number</label>
                         <input type="tel" name="phone" id="phone"
                             value="' . htmlspecialchars($companyProfile->phone ?? '') . '"
                             class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                     </div>
                     <div>
                         <label for="website" class="block text-sm font-medium text-slate-300 mb-2">Website</label>
                         <input type="url" name="website" id="website"
                             value="' . htmlspecialchars($companyProfile->website ?? '') . '"
                             placeholder="https://www.example.com"
                             class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                     </div>
                     <div class="md:col-span-2">
                         <label for="address" class="block text-sm font-medium text-slate-300 mb-2">Street Address</label>
                         <input type="text" name="address" id="address"
                             value="' . htmlspecialchars($companyProfile->address ?? '') . '"
                             class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                     </div>
                     <div>
                         <label for="city" class="block text-sm font-medium text-slate-300 mb-2">City</label>
                         <input type="text" name="city" id="city"
                             value="' . htmlspecialchars($companyProfile->city ?? '') . '"
                             class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                     </div>
                     <div>
                         <label for="state" class="block text-sm font-medium text-slate-300 mb-2">State/Province</label>
                         <select name="state" id="state" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                             <option value="' . htmlspecialchars($companyProfile->state ?? '') . '">' . htmlspecialchars($companyProfile->state ?? 'Select State/Province') . '</option>
                         </select>
                     </div>
                     <div>
                         <label for="postal_code" class="block text-sm font-medium text-slate-300 mb-2">Postal Code</label>
                         <input type="text" name="postal_code" id="postal_code"
                             value="' . htmlspecialchars($companyProfile->postal_code ?? '') . '"
                             class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                     </div>
                     <div>
                         <label for="country" class="block text-sm font-medium text-slate-300 mb-2">Country</label>
                         <select name="country" id="country" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                             <option value="' . htmlspecialchars($companyProfile->country ?? '') . '">' . htmlspecialchars($companyProfile->country ?? 'Select Country') . '</option>
                         </select>
                     </div>
                     
                     <!-- Current Logo Display -->
                     ' . ($logoUrl ? '
                     <div class="md:col-span-2">
                         <label class="block text-sm font-medium text-slate-300 mb-2">Current Logo</label>
                         <div id="currentLogoContainer" class="mb-4">
                             <div class="flex items-center space-x-4 p-4 bg-slate-700 rounded-md border border-slate-600">
                                 <img src="' . htmlspecialchars($logoUrl) . '" alt="Current Logo" class="max-h-20 max-w-40 border border-slate-500 rounded-md">
                                 <div class="flex-1">
                                     <p class="text-sm text-slate-300 font-medium">Current Logo</p>
                                     <p class="text-xs text-slate-400 mt-1">Used in customer portal, invoices, and emails</p>
                                 </div>
                                 <button type="button" id="removeLogo" onclick="removeLogo()" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                                     Remove
                                 </button>
                             </div>
                         </div>
                     </div>
                     ' : '') . '
                     
                     <!-- Logo Upload -->
                     <div class="md:col-span-2">
                         <label for="logo" class="block text-sm font-medium text-slate-300 mb-2">Company Logo</label>
                         <input type="file" name="logo" id="logo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" onchange="previewLogo(this)"
                             class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-sky-600 file:text-white hover:file:bg-sky-700">
                         <p class="text-xs text-slate-400 mt-1">Upload JPEG, PNG, GIF, or WebP. Max size: 2MB</p>
                         
                         <!-- Logo Preview -->
                         <div id="logoPreview" class="mt-4"></div>
                         
                         <!-- Hidden input for logo removal -->
                         <input type="hidden" name="remove_logo" id="remove_logo" value="">
                     </div>
                 </div>

                 <div class="mt-8 flex justify-end">
                     <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white px-6 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-800">
                         Save Company Profile
                     </button>
                 </div>
             </form>
         </div>';
     }

           /**
       * Get SMTP template HTML
       */
      private function getSmtpTemplate($smtpConfig)
      {
          return '<div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg">
              <div class="p-6 border-b border-slate-700">
                  <div class="flex items-start">
                      <div class="flex-shrink-0 border-2 border-emerald-600 p-3 rounded-md">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-emerald-600 size-8">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.32 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                          </svg>
                      </div>
                      <div class="ml-4">
                          <h4 class="text-lg font-medium text-white">SMTP Configuration</h4>
                          <span class="text-sm text-slate-400">Configure email settings for customer notifications</span>
                      </div>
                  </div>
              </div>

              <form method="POST" class="p-6" id="smtpForm">
                  <input type="hidden" name="tab" value="smtp">
                  
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div>
                          <label for="smtp_host" class="block text-sm font-medium text-slate-300 mb-2">SMTP Host *</label>
                          <input type="text" name="smtp_host" id="smtp_host" required
                              value="' . htmlspecialchars($smtpConfig->smtp_host ?? '') . '"
                              placeholder="smtp.gmail.com"
                              class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                      </div>
                      <div>
                          <label for="smtp_port" class="block text-sm font-medium text-slate-300 mb-2">SMTP Port *</label>
                          <input type="number" name="smtp_port" id="smtp_port" required
                              value="' . htmlspecialchars($smtpConfig->smtp_port ?? '587') . '"
                              min="1" max="65535"
                              class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                      </div>
                      <div>
                          <label for="smtp_encryption" class="block text-sm font-medium text-slate-300 mb-2">Encryption</label>
                          <select name="smtp_encryption" id="smtp_encryption" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                              <option value="none"' . (($smtpConfig->smtp_encryption ?? 'tls') === 'none' ? ' selected' : '') . '>None</option>
                              <option value="tls"' . (($smtpConfig->smtp_encryption ?? 'tls') === 'tls' ? ' selected' : '') . '>TLS (Recommended)</option>
                              <option value="ssl"' . (($smtpConfig->smtp_encryption ?? 'tls') === 'ssl' ? ' selected' : '') . '>SSL</option>
                          </select>
                      </div>
                      <div>
                          <label for="smtp_status" class="block text-sm font-medium text-slate-300 mb-2">Status</label>
                          <select name="smtp_status" id="smtp_status" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                              <option value="active"' . (($smtpConfig->status ?? 'active') === 'active' ? ' selected' : '') . '>Active</option>
                              <option value="inactive"' . (($smtpConfig->status ?? 'active') === 'inactive' ? ' selected' : '') . '>Inactive</option>
                          </select>
                      </div>
                      <div class="md:col-span-2">
                          <label for="smtp_username" class="block text-sm font-medium text-slate-300 mb-2">SMTP Username *</label>
                          <input type="text" name="smtp_username" id="smtp_username" required
                              value="' . htmlspecialchars($smtpConfig->smtp_username ?? '') . '"
                              placeholder="your-email@gmail.com"
                              class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                      </div>
                      <div class="md:col-span-2">
                          <label for="smtp_password" class="block text-sm font-medium text-slate-300 mb-2">SMTP Password</label>
                          <div class="relative">
                              <input type="password" name="smtp_password" id="smtp_password"
                                  placeholder="' . ($smtpConfig ? 'Leave blank to keep current password' : 'Enter SMTP password') . '"
                                  class="w-full px-3 py-2 pr-10 bg-slate-700 border border-slate-600 rounded-md text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                              <button type="button" class="password-toggle absolute inset-y-0 right-0 pr-3 flex items-center">
                                  <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                  </svg>
                              </button>
                          </div>
                      </div>
                  </div>

                  <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-between">
                      <div class="flex gap-2">
                          <button type="button" id="testSmtp" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-800 text-sm">
                              Test Connection
                          </button>
                          <button type="button" id="testSmtpEmail" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-800 text-sm">
                              Send Test Email
                          </button>
                      </div>
                      <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-800">
                          Save SMTP Settings
                      </button>
                  </div>
                                            </form>
           </div>';
       }
 }  