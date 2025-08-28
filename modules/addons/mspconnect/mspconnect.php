<?php
/**
 * MSPConnect - Multi-tenant Reseller Module with Stripe Connect
 * 
 * A WHMCS addon module that creates a reseller/multi-tenant environment
 * allowing MSPs to manage their own customers and billing through Stripe Connect.
 * 
 * @author EazyBackup Team
 * @version 1.0.0
 */

use WHMCS\Database\Capsule;

// Include helper functions
require_once __DIR__ . '/lib/helpers.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Module Configuration Array
 * 
 * @return array
 */
function mspconnect_config()
{
    return [
        'name' => 'MSPConnect',
        'description' => 'Multi-tenant reseller platform with Stripe Connect integration for MSPs',
        'version' => '1.0.0',
        'author' => 'EazyBackup',
        'language' => 'english',
        'fields' => [
            'stripe_publishable_key' => [
                'FriendlyName' => 'Stripe Publishable Key',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Your Stripe publishable key (pk_live_... or pk_test_...)',
                'Default' => '',
            ],
            'stripe_secret_key' => [
                'FriendlyName' => 'Stripe Secret Key',
                'Type' => 'password',
                'Size' => '50',
                'Description' => 'Your Stripe secret key (sk_live_... or sk_test_...)',
                'Default' => '',
            ],
            'stripe_webhook_secret' => [
                'FriendlyName' => 'Stripe Webhook Secret',
                'Type' => 'password',
                'Size' => '50',
                'Description' => 'Stripe webhook endpoint secret for verifying webhook signatures',
                'Default' => '',
            ],
            'portal_domain' => [
                'FriendlyName' => 'Customer Portal Domain',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Domain for customer portal (e.g., portal.yourdomain.com)',
                'Default' => '',
            ],
            'default_from_email' => [
                'FriendlyName' => 'Default From Email',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Default from email for system notifications',
                'Default' => 'noreply@yourdomain.com',
            ],
            'enable_debug' => [
                'FriendlyName' => 'Enable Debug Mode',
                'Type' => 'yesno',
                'Description' => 'Enable debug logging for troubleshooting',
                'Default' => 'no',
            ],
        ]
    ];
}

/**
 * Module Activation
 * Creates all necessary database tables
 * 
 * @return array
 */
function mspconnect_activate()
{
    try {
        // MSP Settings Table
        if (!Capsule::schema()->hasTable('msp_reseller_msp_settings')) {
            Capsule::schema()->create('msp_reseller_msp_settings', function ($table) {
                $table->increments('id');
                $table->integer('client_id')->unique(); // WHMCS client ID of the MSP
                $table->string('stripe_account_id')->nullable(); // Connected Stripe account ID
                $table->text('stripe_access_token')->nullable(); // Encrypted access token
                $table->text('stripe_refresh_token')->nullable(); // Encrypted refresh token
                $table->enum('stripe_account_status', ['pending', 'connected', 'disconnected'])->default('pending');
                $table->timestamp('stripe_connected_at')->nullable();
                $table->json('stripe_capabilities')->nullable(); // Store Stripe account capabilities
                $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
                $table->timestamps();
            });
        }

        // Company Profile Table for MSP branding
        if (!Capsule::schema()->hasTable('msp_reseller_company_profile')) {
            Capsule::schema()->create('msp_reseller_company_profile', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('msp_id'); // References msp_reseller_msp_settings.id
                $table->string('company_name');
                $table->string('contact_email');
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country')->nullable();
                $table->string('phone')->nullable();
                $table->string('website')->nullable();
                $table->string('logo_filename')->nullable();
                $table->timestamps();
                $table->foreign('msp_id')->references('id')->on('msp_reseller_msp_settings')->onDelete('cascade');
            });
        }

        // SMTP Configuration Table
        if (!Capsule::schema()->hasTable('msp_reseller_smtp_config')) {
            Capsule::schema()->create('msp_reseller_smtp_config', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('msp_id'); // References msp_reseller_msp_settings.id
                $table->string('smtp_host');
                $table->integer('smtp_port')->default(587);
                $table->enum('smtp_encryption', ['none', 'ssl', 'tls'])->default('tls');
                $table->string('smtp_username');
                $table->text('smtp_password'); // Encrypted
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamp('last_tested')->nullable();
                $table->timestamps();
                $table->foreign('msp_id')->references('id')->on('msp_reseller_msp_settings')->onDelete('cascade');
            });
        }

        // Email Templates Table
        if (!Capsule::schema()->hasTable('msp_reseller_email_templates')) {
            Capsule::schema()->create('msp_reseller_email_templates', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('msp_id'); // References msp_reseller_msp_settings.id
                $table->string('template_name');
                $table->string('subject');
                $table->text('body_html');
                $table->text('body_text')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                $table->foreign('msp_id')->references('id')->on('msp_reseller_msp_settings')->onDelete('cascade');
                $table->unique(['msp_id', 'template_name']);
            });
        }

        // MSP Customers Table
        if (!Capsule::schema()->hasTable('msp_reseller_customers')) {
            Capsule::schema()->create('msp_reseller_customers', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('msp_id'); // References msp_reseller_msp_settings.id
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->string('company')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country')->nullable();
                $table->string('password_hash');
                $table->string('password_reset_token')->nullable();
                $table->timestamp('password_reset_expires')->nullable();
                $table->string('stripe_customer_id')->nullable(); // Stripe customer ID under MSP's account
                $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
                $table->timestamp('last_login')->nullable();
                $table->timestamps();
                $table->foreign('msp_id')->references('id')->on('msp_reseller_msp_settings')->onDelete('cascade');
            });
        }

        // Service Plans Table
        if (!Capsule::schema()->hasTable('msp_reseller_plans')) {
            Capsule::schema()->create('msp_reseller_plans', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('msp_id'); // References msp_reseller_msp_settings.id
                $table->string('plan_name');
                $table->text('description')->nullable();
                $table->decimal('monthly_price', 10, 2);
                $table->decimal('annual_price', 10, 2)->nullable();
                $table->json('features')->nullable(); // Store plan features as JSON
                $table->integer('storage_gb')->nullable();
                $table->integer('max_devices')->nullable();
                $table->enum('billing_cycle', ['monthly', 'annual', 'both'])->default('monthly');
                $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->foreign('msp_id')->references('id')->on('msp_reseller_msp_settings')->onDelete('cascade');
            });
        }

        // Customer Services Table
        if (!Capsule::schema()->hasTable('msp_reseller_services')) {
            Capsule::schema()->create('msp_reseller_services', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('customer_id'); // References msp_reseller_customers.id
                $table->unsignedInteger('plan_id'); // References msp_reseller_plans.id
                $table->string('service_name')->nullable();
                $table->decimal('price', 10, 2);
                $table->enum('billing_cycle', ['monthly', 'annual']);
                $table->date('next_due_date');
                $table->date('last_invoice_date')->nullable();
                $table->enum('status', ['active', 'suspended', 'cancelled', 'pending'])->default('pending');
                $table->json('service_data')->nullable(); // Store service-specific data
                $table->timestamps();
                $table->foreign('customer_id')->references('id')->on('msp_reseller_customers')->onDelete('cascade');
                $table->foreign('plan_id')->references('id')->on('msp_reseller_plans')->onDelete('cascade');
            });
        }

        // Invoices Table
        if (!Capsule::schema()->hasTable('msp_reseller_invoices')) {
            Capsule::schema()->create('msp_reseller_invoices', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('customer_id'); // References msp_reseller_customers.id
                $table->unsignedInteger('service_id')->nullable(); // References msp_reseller_services.id
                $table->string('invoice_number')->unique();
                $table->decimal('amount', 10, 2);
                $table->decimal('tax_amount', 10, 2)->default(0);
                $table->decimal('total_amount', 10, 2);
                $table->date('invoice_date');
                $table->date('due_date');
                $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');
                $table->string('stripe_payment_intent_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('line_items')->nullable(); // JSON encoded line items
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->foreign('customer_id')->references('id')->on('msp_reseller_customers')->onDelete('cascade');
                $table->foreign('service_id')->references('id')->on('msp_reseller_services')->onDelete('set null');
            });
        }

        // Payment Methods Table
        if (!Capsule::schema()->hasTable('msp_reseller_payment_methods')) {
            Capsule::schema()->create('msp_reseller_payment_methods', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('customer_id'); // References msp_reseller_customers.id
                $table->string('stripe_payment_method_id');
                $table->string('type'); // card, bank_account, etc.
                $table->string('last_four');
                $table->string('brand')->nullable();
                $table->integer('exp_month')->nullable();
                $table->integer('exp_year')->nullable();
                $table->boolean('is_default')->default(false);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                $table->foreign('customer_id')->references('id')->on('msp_reseller_customers')->onDelete('cascade');
            });
        }

        // Activity Log Table
        if (!Capsule::schema()->hasTable('msp_reseller_activity_log')) {
            Capsule::schema()->create('msp_reseller_activity_log', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('msp_id')->nullable(); // References msp_reseller_msp_settings.id
                $table->unsignedInteger('customer_id')->nullable(); // References msp_reseller_customers.id
                $table->string('action');
                $table->text('description');
                $table->json('metadata')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();
                $table->foreign('msp_id')->references('id')->on('msp_reseller_msp_settings')->onDelete('cascade');
                $table->foreign('customer_id')->references('id')->on('msp_reseller_customers')->onDelete('cascade');
            });
        }

        // Create default email templates for each MSP that might be activated later
        // This will be handled in the client area when MSP first accesses the module

        return [
            'status' => 'success',
            'description' => 'MSPConnect module activated successfully. All database tables created.'
        ];

    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Module activation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Module Deactivation
 * Clean up module data (optional - tables are preserved)
 * 
 * @return array
 */
function mspconnect_deactivate()
{
    try {
        // Optionally clean up temporary data, but preserve core tables
        // You may want to keep the data for reactivation
        
        return [
            'status' => 'success',
            'description' => 'MSPConnect module deactivated successfully.'
        ];

    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Module deactivation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Client Area Output
 * Main interface for MSPs to manage their reseller business
 * 
 * @param array $vars
 * @return array WHMCS format response
 */
function mspconnect_clientarea($vars)
{
    try {
        // Get current MSP client info
        $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
        if (!$clientId) {
            return [
                'pagetitle' => 'MSPConnect - Access Denied',
                'breadcrumb' => [
                    'index.php?m=mspconnect' => 'MSPConnect'
                ],
                'templatefile' => 'error',
                'requirelogin' => true,
                'vars' => [
                    'error' => 'You must be logged in to access this module.',
                    'title' => 'Access Denied'
                ]
            ];
        }

        // Check if required classes exist before loading
        $controllerPath = __DIR__ . '/lib/Client/MSPController.php';
        if (!file_exists($controllerPath)) {
            return [
                'pagetitle' => 'MSPConnect - Module Error',
                'breadcrumb' => [
                    'index.php?m=mspconnect' => 'MSPConnect'
                ],
                'templatefile' => 'error',
                'requirelogin' => true,
                'vars' => [
                    'error' => 'MSPController class not found. Please ensure the module is properly installed.',
                    'title' => 'Module Error'
                ]
            ];
        }

        // Load required classes
        require_once $controllerPath;

        $controller = new MSPController($clientId, $vars);
        
        // Handle different pages (using your custom URL structure with ?m=mspconnect&page=xxx)
        $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
        
        // Get page content
        $output = '';
        $pageTitle = 'MSPConnect Dashboard';
        
        switch ($page) {
            case 'dashboard':
                $output = $controller->showDashboard();
                $pageTitle = 'MSPConnect - Dashboard';
                break;
            case 'customers':
                $output = $controller->showCustomers();
                $pageTitle = 'MSPConnect - Customer Management';
                break;
            case 'customer':
                $output = $controller->showCustomer();
                $pageTitle = 'MSPConnect - Customer Details';
                break;
            case 'plans':
                $output = $controller->showPlans();
                $pageTitle = 'MSPConnect - Service Plans';
                break;
            case 'invoices':
                $output = $controller->showInvoices();
                $pageTitle = 'MSPConnect - Invoices';
                break;
            case 'settings':
                $output = $controller->showSettings();
                $pageTitle = 'MSPConnect - Settings';
                break;
            case 'branding':
                $output = $controller->showBranding();
                $pageTitle = 'MSPConnect - Company Branding';
                break;
            case 'email-templates':
                $output = $controller->showEmailTemplates();
                $pageTitle = 'MSPConnect - Email Templates';
                break;
            case 'stripe-connect':
                $output = $controller->handleStripeConnect();
                $pageTitle = 'MSPConnect - Stripe Integration';
                break;
            
            default:
                $output = $controller->showDashboard();
                $pageTitle = 'MSPConnect - Dashboard';
                break;
        }
        
        // Return in WHMCS format
        return [
            'pagetitle' => $pageTitle,
            'breadcrumb' => [
                'index.php?m=mspconnect' => 'MSPConnect',
                'index.php?m=mspconnect&page=' . $page => ucfirst($page)
            ],
            'templatefile' => 'page',
            'requirelogin' => true,
            'vars' => [
                'content' => $output,
                'page' => $page,
                'modulelink' => 'index.php?m=mspconnect'
            ]
        ];

    } catch (Exception $e) {
        // Return error in WHMCS format
        $errorContent = '<div class="alert alert-danger">
            <h4><i class="fa fa-exclamation-triangle"></i> MSPConnect Error</h4>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>';
        
        if (isset($vars['enable_debug']) && $vars['enable_debug'] == 'on') {
            $errorContent .= '<hr><strong>Debug Information:</strong>
            <pre style="font-size: 11px; max-height: 300px; overflow-y: auto;">' . 
            htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
        
        $errorContent .= '<hr><p><strong>Troubleshooting:</strong></p>
        <ul>
            <li>Ensure the Stripe PHP library is installed if using payment features</li>
            <li>Check that all module files are uploaded correctly</li>
            <li>Verify database tables were created during module activation</li>
        </ul>
        </div>';
        
        return [
            'pagetitle' => 'MSPConnect - Error',
            'breadcrumb' => [
                'index.php?m=mspconnect' => 'MSPConnect'
            ],
            'templatefile' => 'page',
            'requirelogin' => true,
            'vars' => [
                'content' => $errorContent,
                'page' => 'error',
                'modulelink' => 'index.php?m=mspconnect'
            ]
        ];
    }
}

/**
 * Admin Area Output
 * Administrative interface for managing the MSPConnect module
 * 
 * @param array $vars
 * @return string
 */
function mspconnect_output($vars)
{
    try {
        $modulelink = $vars['modulelink'];
        $version = $vars['version'];
        $LANG = $vars['_lang'];

        // Load admin controller
        require_once __DIR__ . '/lib/Admin/AdminController.php';
        
        $controller = new AdminController($vars);
        
        // Handle different admin actions
        $action = isset($_GET['action']) ? $_GET['action'] : 'overview';
        
        switch ($action) {
            case 'overview':
                return $controller->showOverview();
            case 'msps':
                return $controller->showMSPs();
            case 'customers':
                return $controller->showAllCustomers();
            case 'invoices':
                return $controller->showAllInvoices();
            case 'settings':
                return $controller->showGlobalSettings();
            case 'logs':
                return $controller->showLogs();
            default:
                return $controller->showOverview();
        }

    } catch (Exception $e) {
        $error = 'MSPConnect Admin Error: ' . $e->getMessage();
        if ($vars['enable_debug'] == 'on') {
            $error .= '<br><pre>' . $e->getTraceAsString() . '</pre>';
        }
        return '<div class="alert alert-danger">' . $error . '</div>';
    }
}

/**
 * Admin Area Sidebar Output
 * Custom sidebar for admin area navigation
 * 
 * @param array $vars
 * @return string
 */
function mspconnect_sidebar($vars)
{
    $modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $LANG = $vars['_lang'];

    $sidebar = '<div class="list-group">';
    $sidebar .= '<a href="' . $modulelink . '" class="list-group-item">Overview</a>';
    $sidebar .= '<a href="' . $modulelink . '&action=msps" class="list-group-item">MSP Accounts</a>';
    $sidebar .= '<a href="' . $modulelink . '&action=customers" class="list-group-item">All Customers</a>';
    $sidebar .= '<a href="' . $modulelink . '&action=invoices" class="list-group-item">All Invoices</a>';
    $sidebar .= '<a href="' . $modulelink . '&action=settings" class="list-group-item">Global Settings</a>';
    $sidebar .= '<a href="' . $modulelink . '&action=logs" class="list-group-item">Activity Logs</a>';
    $sidebar .= '</div>';

    return $sidebar;
}

 