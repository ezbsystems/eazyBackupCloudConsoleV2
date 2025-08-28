<?php
/**
 * StripeManager - Stripe Connect Integration
 * 
 * Handles all Stripe Connect functionality including OAuth, payments,
 * customer management, and webhook processing.
 */

use WHMCS\Database\Capsule;

class StripeManager
{
    private $config;
    private $stripe;
    private $stripeAvailable = false;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->initializeStripe();
    }
    
    /**
     * Check if Stripe SDK is available
     */
    private function isStripeAvailable()
    {
        return $this->stripeAvailable && class_exists('\Stripe\Stripe');
    }
    
    /**
     * Initialize Stripe SDK
     */
    private function initializeStripe()
    {
        // Try multiple paths for Stripe SDK
        $stripePaths = [
            __DIR__ . '/../../vendor/stripe/stripe-php/init.php',
            __DIR__ . '/../../../../vendor/stripe/stripe-php/init.php',
            __DIR__ . '/../../../../../vendor/stripe/stripe-php/init.php'
        ];
        
        $stripeLoaded = false;
        foreach ($stripePaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $stripeLoaded = true;
                break;
            }
        }
        
        if (!$stripeLoaded) {
            // Stripe SDK not found - module will work in limited mode
            error_log('MSPConnect: Stripe PHP SDK not found. Stripe functionality will be disabled.');
            $this->stripeAvailable = false;
            return;
        }
        
        if (!empty($this->config['stripe_secret_key'])) {
            \Stripe\Stripe::setApiKey($this->config['stripe_secret_key']);
            \Stripe\Stripe::setApiVersion('2023-10-16');
            $this->stripeAvailable = true;
        } else {
            $this->stripeAvailable = false;
        }
    }
    
    /**
     * Get Stripe Connect OAuth URL
     */
    public function getConnectUrl()
    {
        if (!$this->isStripeAvailable()) {
            return '#stripe-not-configured';
        }
        
        $state = bin2hex(random_bytes(16)); // CSRF protection
        $_SESSION['stripe_connect_state'] = $state;
        
        $params = [
            'response_type' => 'code',
            'client_id' => $this->getStripeClientId(),
            'scope' => 'read_write',
            'redirect_uri' => $this->getRedirectUri(),
            'state' => $state,
            'stripe_user[email]' => $this->getMSPEmail(),
            'stripe_user[business_type]' => 'company'
        ];
        
        return 'https://connect.stripe.com/oauth/authorize?' . http_build_query($params);
    }
    
    /**
     * Handle OAuth callback
     */
    public function handleOAuthCallback($mspId, $code)
    {
        if (!$this->isStripeAvailable()) {
            return '<div class="alert alert-danger">Stripe SDK is not installed. Please install the Stripe PHP library to enable payment processing.</div>';
        }
        
        try {
            // Verify state parameter
            if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['stripe_connect_state']) {
                throw new Exception('Invalid state parameter');
            }
            
            // Exchange code for access token
            $response = \Stripe\OAuth::token([
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);
            
            // Update MSP settings with Stripe account info
            Capsule::table('msp_reseller_msp_settings')
                ->where('id', $mspId)
                ->update([
                    'stripe_account_id' => $response->stripe_user_id,
                    'stripe_access_token' => mspconnect_encrypt($response->access_token),
                    'stripe_refresh_token' => mspconnect_encrypt($response->refresh_token ?? ''),
                    'stripe_account_status' => 'connected',
                    'stripe_connected_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            
            // Get account capabilities
            $account = \Stripe\Account::retrieve($response->stripe_user_id);
            Capsule::table('msp_reseller_msp_settings')
                ->where('id', $mspId)
                ->update([
                    'stripe_capabilities' => json_encode($account->capabilities)
                ]);
            
            // Log activity
            mspconnect_log_activity($mspId, null, 'stripe_connected', 
                'Stripe account connected: ' . $response->stripe_user_id);
            
            unset($_SESSION['stripe_connect_state']);
            
            return '<div class="alert alert-success">Stripe account connected successfully!</div>';
            
        } catch (Exception $e) {
            mspconnect_log_activity($mspId, null, 'stripe_connect_failed', 
                'Stripe connection failed: ' . $e->getMessage());
                
            return '<div class="alert alert-danger">Failed to connect Stripe account: ' . $e->getMessage() . '</div>';
        }
    }
    
    /**
     * Disconnect Stripe account
     */
    public function disconnectAccount($mspId)
    {
        try {
            $mspSettings = Capsule::table('msp_reseller_msp_settings')->where('id', $mspId)->first();
            
            if ($mspSettings->stripe_account_id) {
                // Revoke access token
                \Stripe\OAuth::deauthorize([
                    'client_id' => $this->getStripeClientId(),
                    'stripe_user_id' => $mspSettings->stripe_account_id,
                ]);
            }
            
            // Clear Stripe settings
            Capsule::table('msp_reseller_msp_settings')
                ->where('id', $mspId)
                ->update([
                    'stripe_account_id' => null,
                    'stripe_access_token' => null,
                    'stripe_refresh_token' => null,
                    'stripe_account_status' => 'disconnected',
                    'stripe_capabilities' => null,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            
            mspconnect_log_activity($mspId, null, 'stripe_disconnected', 'Stripe account disconnected');
            
            return '<div class="alert alert-success">Stripe account disconnected successfully.</div>';
            
        } catch (Exception $e) {
            return '<div class="alert alert-danger">Error disconnecting Stripe account: ' . $e->getMessage() . '</div>';
        }
    }
    
    /**
     * Create Stripe customer under MSP's connected account
     */
    public function createCustomer($stripeAccountId, $customerData)
    {
        if (!$this->isStripeAvailable()) {
            return null; // Return null when Stripe is not available
        }
        
        try {
            $customer = \Stripe\Customer::create($customerData, [
                'stripe_account' => $stripeAccountId
            ]);
            
            return $customer->id;
            
        } catch (Exception $e) {
            throw new Exception('Failed to create Stripe customer: ' . $e->getMessage());
        }
    }
    
    /**
     * Create payment method for customer
     */
    public function createPaymentMethod($stripeAccountId, $customerId, $paymentMethodData)
    {
        try {
            $paymentMethod = \Stripe\PaymentMethod::create($paymentMethodData, [
                'stripe_account' => $stripeAccountId
            ]);
            
            // Attach to customer
            $paymentMethod->attach([
                'customer' => $customerId
            ], [
                'stripe_account' => $stripeAccountId
            ]);
            
            return $paymentMethod;
            
        } catch (Exception $e) {
            throw new Exception('Failed to create payment method: ' . $e->getMessage());
        }
    }
    
    /**
     * Create payment intent for invoice
     */
    public function createPaymentIntent($stripeAccountId, $amount, $currency, $customerId, $metadata = [])
    {
        try {
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => $currency,
                'customer' => $customerId,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => $metadata
            ], [
                'stripe_account' => $stripeAccountId
            ]);
            
            return $paymentIntent;
            
        } catch (Exception $e) {
            throw new Exception('Failed to create payment intent: ' . $e->getMessage());
        }
    }
    
    /**
     * Create Stripe Checkout session
     */
    public function createCheckoutSession($stripeAccountId, $lineItems, $successUrl, $cancelUrl, $customerId = null, $metadata = [])
    {
        try {
            $sessionData = [
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => $metadata
            ];
            
            if ($customerId) {
                $sessionData['customer'] = $customerId;
            }
            
            $session = \Stripe\Checkout\Session::create($sessionData, [
                'stripe_account' => $stripeAccountId
            ]);
            
            return $session;
            
        } catch (Exception $e) {
            throw new Exception('Failed to create checkout session: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle webhook from Stripe
     */
    public function handleWebhook($payload, $signature)
    {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $this->config['stripe_webhook_secret']
            );
            
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentSuccess($event->data->object);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $this->handlePaymentFailed($event->data->object);
                    break;
                    
                case 'customer.subscription.created':
                    $this->handleSubscriptionCreated($event->data->object);
                    break;
                    
                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;
                    
                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($event->data->object);
                    break;
                    
                case 'account.updated':
                    $this->handleAccountUpdated($event->data->object);
                    break;
                    
                default:
                    // Log unhandled event
                    error_log('Unhandled Stripe webhook event: ' . $event->type);
                    break;
            }
            
            return ['status' => 'success'];
            
        } catch (Exception $e) {
            error_log('Stripe webhook error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Handle successful payment
     */
    private function handlePaymentSuccess($paymentIntent)
    {
        try {
            // Find invoice by payment intent ID
            $invoice = Capsule::table('msp_reseller_invoices')
                ->where('stripe_payment_intent_id', $paymentIntent->id)
                ->first();
                
            if ($invoice) {
                // Update invoice status
                Capsule::table('msp_reseller_invoices')
                    ->where('id', $invoice->id)
                    ->update([
                        'status' => 'paid',
                        'paid_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                
                // Get customer and MSP info
                $customer = Capsule::table('msp_reseller_customers')
                    ->where('id', $invoice->customer_id)
                    ->first();
                    
                if ($customer) {
                    // Send payment confirmation email
                    $this->sendPaymentConfirmationEmail($customer->msp_id, $customer->id, $invoice->id);
                    
                    // Log activity
                    mspconnect_log_activity($customer->msp_id, $customer->id, 'payment_received', 
                        'Payment received for invoice #' . $invoice->invoice_number, [
                            'amount' => $paymentIntent->amount / 100,
                            'payment_intent_id' => $paymentIntent->id
                        ]);
                }
            }
            
        } catch (Exception $e) {
            error_log('Error handling payment success: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle failed payment
     */
    private function handlePaymentFailed($paymentIntent)
    {
        try {
            // Find invoice and log the failure
            $invoice = Capsule::table('msp_reseller_invoices')
                ->where('stripe_payment_intent_id', $paymentIntent->id)
                ->first();
                
            if ($invoice) {
                $customer = Capsule::table('msp_reseller_customers')
                    ->where('id', $invoice->customer_id)
                    ->first();
                    
                if ($customer) {
                    mspconnect_log_activity($customer->msp_id, $customer->id, 'payment_failed', 
                        'Payment failed for invoice #' . $invoice->invoice_number, [
                            'payment_intent_id' => $paymentIntent->id,
                            'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Unknown'
                        ]);
                }
            }
            
        } catch (Exception $e) {
            error_log('Error handling payment failure: ' . $e->getMessage());
        }
    }
    
    /**
     * Send payment confirmation email
     */
    private function sendPaymentConfirmationEmail($mspId, $customerId, $invoiceId)
    {
        try {
            require_once __DIR__ . '/../Email/EmailManager.php';
            
            $emailManager = new EmailManager($mspId);
            $emailManager->sendPaymentConfirmation($customerId, $invoiceId);
            
        } catch (Exception $e) {
            error_log('Error sending payment confirmation email: ' . $e->getMessage());
        }
    }
    
    /**
     * Get Stripe client ID from environment or config
     */
    private function getStripeClientId()
    {
        // This should be your Stripe Connect application's client ID
        return $this->config['stripe_client_id'] ?? 'ca_test_your_client_id';
    }
    
    /**
     * Get OAuth redirect URI
     */
    private function getRedirectUri()
    {
        $baseUrl = $_SERVER['HTTPS'] ? 'https://' : 'http://';
        $baseUrl .= $_SERVER['HTTP_HOST'];
        
        return $baseUrl . '/modules/addons/mspconnect/api/stripe_callback.php';
    }
    
    /**
     * Get MSP email for pre-filling OAuth form
     */
    private function getMSPEmail()
    {
        if (isset($_SESSION['uid'])) {
            $client = Capsule::table('tblclients')
                ->where('id', $_SESSION['uid'])
                ->first();
            return $client->email ?? '';
        }
        
        return '';
    }
    
    /**
     * Retrieve account information
     */
    public function getAccountInfo($stripeAccountId)
    {
        try {
            $account = \Stripe\Account::retrieve($stripeAccountId);
            return $account;
        } catch (Exception $e) {
            throw new Exception('Failed to retrieve account info: ' . $e->getMessage());
        }
    }
    
    /**
     * Get account balance
     */
    public function getAccountBalance($stripeAccountId)
    {
        try {
            $balance = \Stripe\Balance::retrieve([], [
                'stripe_account' => $stripeAccountId
            ]);
            return $balance;
        } catch (Exception $e) {
            throw new Exception('Failed to retrieve balance: ' . $e->getMessage());
        }
    }
    
    /**
     * List recent transactions
     */
    public function getRecentTransactions($stripeAccountId, $limit = 10)
    {
        try {
            $transactions = \Stripe\BalanceTransaction::all([
                'limit' => $limit
            ], [
                'stripe_account' => $stripeAccountId
            ]);
            
            return $transactions;
        } catch (Exception $e) {
            throw new Exception('Failed to retrieve transactions: ' . $e->getMessage());
        }
    }
    
    /**
     * Create Express Dashboard login link
     */
    public function createExpressDashboardLink($stripeAccountId)
    {
        try {
            $link = \Stripe\Account::createLoginLink($stripeAccountId);
            return $link->url;
        } catch (Exception $e) {
            throw new Exception('Failed to create dashboard link: ' . $e->getMessage());
        }
    }
} 