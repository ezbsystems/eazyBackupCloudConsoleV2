<?php
/**
 * EmailManager - White-label Email System
 * 
 * Handles custom SMTP configuration, email template processing,
 * merge field replacement, and email sending for MSPs.
 */

use WHMCS\Database\Capsule;

class EmailManager
{
    private $mspId;
    private $mspSettings;
    private $companyProfile;
    private $smtpConfig;
    
    public function __construct($mspId)
    {
        $this->mspId = $mspId;
        $this->loadMSPData();
    }
    
    /**
     * Load MSP settings and configuration
     */
    private function loadMSPData()
    {
        $this->mspSettings = Capsule::table('msp_reseller_msp_settings')
            ->where('id', $this->mspId)
            ->first();
            
        $this->companyProfile = Capsule::table('msp_reseller_company_profile')
            ->where('msp_id', $this->mspId)
            ->first();
            
        $this->smtpConfig = Capsule::table('msp_reseller_smtp_config')
            ->where('msp_id', $this->mspId)
            ->where('status', 'active')
            ->first();
    }
    
    /**
     * Send welcome email to new customer
     */
    public function sendWelcomeEmail($customerId, $temporaryPassword = null)
    {
        $customer = $this->getCustomer($customerId);
        if (!$customer) {
            throw new Exception('Customer not found');
        }
        
        $template = $this->getEmailTemplate('welcome_email');
        if (!$template) {
            throw new Exception('Welcome email template not found');
        }
        
        $portalUrl = $this->getPortalUrl();
        $passwordResetLink = $this->generatePasswordResetLink($customer);
        
        $mergeFields = [
            'msp_company_name' => $this->companyProfile->company_name,
            'msp_website_url' => $this->companyProfile->website,
            'msp_contact_email' => $this->companyProfile->contact_email,
            'customer_first_name' => $customer->first_name,
            'customer_last_name' => $customer->last_name,
            'customer_email' => $customer->email,
            'portal_url' => $portalUrl,
            'password_reset_link' => $passwordResetLink,
            'temporary_password' => $temporaryPassword
        ];
        
        $subject = $this->processTemplate($template->subject, $mergeFields);
        $bodyHtml = $this->processTemplate($template->body_html, $mergeFields);
        $bodyText = $this->processTemplate($template->body_text, $mergeFields);
        
        return $this->sendEmail($customer->email, $subject, $bodyHtml, $bodyText);
    }
    
    /**
     * Send invoice created notification
     */
    public function sendInvoiceNotification($customerId, $invoiceId)
    {
        $customer = $this->getCustomer($customerId);
        $invoice = $this->getInvoice($invoiceId);
        
        if (!$customer || !$invoice) {
            throw new Exception('Customer or invoice not found');
        }
        
        $template = $this->getEmailTemplate('invoice_created');
        if (!$template) {
            throw new Exception('Invoice notification template not found');
        }
        
        $invoiceUrl = $this->getInvoiceUrl($invoice);
        
        $mergeFields = [
            'msp_company_name' => $this->companyProfile->company_name,
            'msp_website_url' => $this->companyProfile->website,
            'msp_contact_email' => $this->companyProfile->contact_email,
            'customer_first_name' => $customer->first_name,
            'customer_last_name' => $customer->last_name,
            'customer_email' => $customer->email,
            'invoice_number' => $invoice->invoice_number,
            'invoice_total' => number_format($invoice->total_amount, 2),
            'invoice_amount' => number_format($invoice->amount, 2),
            'invoice_tax' => number_format($invoice->tax_amount, 2),
            'invoice_date' => date('M j, Y', strtotime($invoice->invoice_date)),
            'invoice_due_date' => date('M j, Y', strtotime($invoice->due_date)),
            'invoice_url' => $invoiceUrl
        ];
        
        $subject = $this->processTemplate($template->subject, $mergeFields);
        $bodyHtml = $this->processTemplate($template->body_html, $mergeFields);
        $bodyText = $this->processTemplate($template->body_text, $mergeFields);
        
        return $this->sendEmail($customer->email, $subject, $bodyHtml, $bodyText);
    }
    
    /**
     * Send payment confirmation email
     */
    public function sendPaymentConfirmation($customerId, $invoiceId)
    {
        $customer = $this->getCustomer($customerId);
        $invoice = $this->getInvoice($invoiceId);
        
        if (!$customer || !$invoice) {
            throw new Exception('Customer or invoice not found');
        }
        
        $template = $this->getEmailTemplate('payment_confirmation');
        if (!$template) {
            throw new Exception('Payment confirmation template not found');
        }
        
        $mergeFields = [
            'msp_company_name' => $this->companyProfile->company_name,
            'msp_website_url' => $this->companyProfile->website,
            'msp_contact_email' => $this->companyProfile->contact_email,
            'customer_first_name' => $customer->first_name,
            'customer_last_name' => $customer->last_name,
            'customer_email' => $customer->email,
            'invoice_number' => $invoice->invoice_number,
            'payment_amount' => number_format($invoice->total_amount, 2),
            'payment_date' => date('M j, Y', strtotime($invoice->paid_at))
        ];
        
        $subject = $this->processTemplate($template->subject, $mergeFields);
        $bodyHtml = $this->processTemplate($template->body_html, $mergeFields);
        $bodyText = $this->processTemplate($template->body_text, $mergeFields);
        
        return $this->sendEmail($customer->email, $subject, $bodyHtml, $bodyText);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($customerId, $resetToken)
    {
        $customer = $this->getCustomer($customerId);
        if (!$customer) {
            throw new Exception('Customer not found');
        }
        
        $template = $this->getEmailTemplate('password_reset');
        if (!$template) {
            throw new Exception('Password reset template not found');
        }
        
        $resetLink = $this->getPortalUrl() . '/reset-password.php?token=' . $resetToken;
        
        $mergeFields = [
            'msp_company_name' => $this->companyProfile->company_name,
            'msp_website_url' => $this->companyProfile->website,
            'msp_contact_email' => $this->companyProfile->contact_email,
            'customer_first_name' => $customer->first_name,
            'customer_last_name' => $customer->last_name,
            'customer_email' => $customer->email,
            'password_reset_link' => $resetLink
        ];
        
        $subject = $this->processTemplate($template->subject, $mergeFields);
        $bodyHtml = $this->processTemplate($template->body_html, $mergeFields);
        $bodyText = $this->processTemplate($template->body_text, $mergeFields);
        
        return $this->sendEmail($customer->email, $subject, $bodyHtml, $bodyText);
    }
    
    /**
     * Send test email to verify SMTP configuration
     */
    public function sendTestEmail($toEmail)
    {
        $subject = 'SMTP Test Email from ' . $this->companyProfile->company_name;
        $bodyHtml = '<h2>SMTP Configuration Test</h2>';
        $bodyHtml .= '<p>This is a test email to verify your SMTP configuration is working correctly.</p>';
        $bodyHtml .= '<p>Sent from: ' . $this->companyProfile->company_name . '</p>';
        $bodyHtml .= '<p>Date: ' . date('Y-m-d H:i:s') . '</p>';
        
        $bodyText = 'SMTP Configuration Test - This is a test email to verify your SMTP configuration. ';
        $bodyText .= 'Sent from: ' . $this->companyProfile->company_name . ' on ' . date('Y-m-d H:i:s');
        
        return $this->sendEmail($toEmail, $subject, $bodyHtml, $bodyText);
    }
    
    /**
     * Process template with merge fields
     */
    private function processTemplate($template, $mergeFields)
    {
        $processed = $template;
        
        foreach ($mergeFields as $field => $value) {
            $processed = str_replace('{$' . $field . '}', $value, $processed);
        }
        
        // Handle any remaining merge fields that weren't provided
        $processed = preg_replace('/\{\$[^}]+\}/', '', $processed);
        
        return $processed;
    }
    
    /**
     * Send email using configured SMTP or fallback to WHMCS default
     */
    private function sendEmail($to, $subject, $bodyHtml, $bodyText = '')
    {
        try {
            if ($this->smtpConfig) {
                return $this->sendViaSMTP($to, $subject, $bodyHtml, $bodyText);
            } else {
                return $this->sendViaWHMCS($to, $subject, $bodyHtml, $bodyText);
            }
        } catch (Exception $e) {
            // Log the error
            mspconnect_log_activity($this->mspId, null, 'email_failed', 
                'Email sending failed: ' . $e->getMessage(), [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Send email via custom SMTP configuration
     */
    private function sendViaSMTP($to, $subject, $bodyHtml, $bodyText)
    {
        // Use PHPMailer (already available in WHMCS)
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtpConfig->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpConfig->smtp_username;
            $mail->Password = mspconnect_decrypt($this->smtpConfig->smtp_password);
            $mail->Port = $this->smtpConfig->smtp_port;
            
            // Set encryption
            if ($this->smtpConfig->smtp_encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($this->smtpConfig->smtp_encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // Recipients
            $mail->setFrom($this->companyProfile->contact_email, $this->companyProfile->company_name);
            $mail->addAddress($to);
            $mail->addReplyTo($this->companyProfile->contact_email, $this->companyProfile->company_name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $this->addEmailWrapper($bodyHtml);
            $mail->AltBody = $bodyText;
            
            $result = $mail->send();
            
            // Update last tested timestamp
            Capsule::table('msp_reseller_smtp_config')
                ->where('id', $this->smtpConfig->id)
                ->update(['last_tested' => date('Y-m-d H:i:s')]);
            
            // Log successful send
            mspconnect_log_activity($this->mspId, null, 'email_sent', 
                'Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
                'via' => 'smtp'
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            throw new Exception('SMTP Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Send email via WHMCS default system
     */
    private function sendViaWHMCS($to, $subject, $bodyHtml, $bodyText)
    {
        // Use WHMCS sendMessage function if available
        if (function_exists('sendMessage')) {
            $messageData = [
                'messagename' => 'MSPConnect Custom Email',
                'id' => 0, // Not tied to a specific client
                'customsubject' => $subject,
                'custommessage' => $this->addEmailWrapper($bodyHtml),
                'customtype' => 'general'
            ];
            
            return sendMessage($messageData, $to);
        }
        
        // Fallback to basic PHP mail
        $headers = [
            'From: ' . $this->companyProfile->contact_email,
            'Reply-To: ' . $this->companyProfile->contact_email,
            'Content-Type: text/html; charset=UTF-8',
            'MIME-Version: 1.0'
        ];
        
        $result = mail($to, $subject, $this->addEmailWrapper($bodyHtml), implode("\r\n", $headers));
        
        // Log send attempt
        mspconnect_log_activity($this->mspId, null, 'email_sent', 
            'Email sent via fallback method', [
            'to' => $to,
            'subject' => $subject,
            'via' => 'fallback'
        ]);
        
        return $result;
    }
    
    /**
     * Add email wrapper with company branding
     */
    private function addEmailWrapper($content)
    {
        $logoImg = '';
        if ($this->companyProfile->logo_filename) {
            $logoPath = '/modules/addons/mspconnect/assets/logos/' . $this->companyProfile->logo_filename;
            $logoImg = '<img src="' . $logoPath . '" alt="' . $this->companyProfile->company_name . '" style="max-width: 200px; margin-bottom: 20px;">';
        }
        
        $wrapper = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($this->companyProfile->company_name) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .email-container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
                .footer { border-top: 2px solid #eee; padding-top: 20px; margin-top: 20px; text-align: center; font-size: 12px; color: #666; }
                .content { padding: 20px 0; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    ' . $logoImg . '
                    <h1>' . htmlspecialchars($this->companyProfile->company_name) . '</h1>
                </div>
                <div class="content">
                    ' . $content . '
                </div>
                <div class="footer">
                    <p>' . htmlspecialchars($this->companyProfile->company_name) . '</p>';
        
        if ($this->companyProfile->address) {
            $wrapper .= '<p>' . htmlspecialchars($this->companyProfile->address);
            if ($this->companyProfile->city) $wrapper .= ', ' . htmlspecialchars($this->companyProfile->city);
            if ($this->companyProfile->state) $wrapper .= ', ' . htmlspecialchars($this->companyProfile->state);
            if ($this->companyProfile->postal_code) $wrapper .= ' ' . htmlspecialchars($this->companyProfile->postal_code);
            $wrapper .= '</p>';
        }
        
        if ($this->companyProfile->phone) {
            $wrapper .= '<p>Phone: ' . htmlspecialchars($this->companyProfile->phone) . '</p>';
        }
        
        if ($this->companyProfile->website) {
            $wrapper .= '<p>Web: <a href="' . htmlspecialchars($this->companyProfile->website) . '">' . htmlspecialchars($this->companyProfile->website) . '</a></p>';
        }
        
        $wrapper .= '
                </div>
            </div>
        </body>
        </html>';
        
        return $wrapper;
    }
    
    /**
     * Get customer record
     */
    private function getCustomer($customerId)
    {
        return Capsule::table('msp_reseller_customers')
            ->where('id', $customerId)
            ->where('msp_id', $this->mspId)
            ->first();
    }
    
    /**
     * Get invoice record
     */
    private function getInvoice($invoiceId)
    {
        return Capsule::table('msp_reseller_invoices')
            ->join('msp_reseller_customers', 'msp_reseller_invoices.customer_id', '=', 'msp_reseller_customers.id')
            ->where('msp_reseller_invoices.id', $invoiceId)
            ->where('msp_reseller_customers.msp_id', $this->mspId)
            ->select('msp_reseller_invoices.*')
            ->first();
    }
    
    /**
     * Get email template
     */
    private function getEmailTemplate($templateName)
    {
        return Capsule::table('msp_reseller_email_templates')
            ->where('msp_id', $this->mspId)
            ->where('template_name', $templateName)
            ->where('status', 'active')
            ->first();
    }
    
    /**
     * Get portal URL
     */
    private function getPortalUrl()
    {
        // This should be configurable per MSP or use a global setting
        global $CONFIG;
        $portalDomain = $CONFIG['MSPConnect']['portal_domain'] ?? 'portal.yourdomain.com';
        return 'https://' . $portalDomain;
    }
    
    /**
     * Get invoice URL for customer portal
     */
    private function getInvoiceUrl($invoice)
    {
        return $this->getPortalUrl() . '/invoice.php?id=' . $invoice->id;
    }
    
    /**
     * Generate password reset link
     */
    private function generatePasswordResetLink($customer)
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Update customer with reset token
        Capsule::table('msp_reseller_customers')
            ->where('id', $customer->id)
            ->update([
                'password_reset_token' => $token,
                'password_reset_expires' => $expires,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        
        return $this->getPortalUrl() . '/reset-password.php?token=' . $token;
    }
    
    /**
     * Get available merge fields for templates
     */
    public static function getAvailableMergeFields()
    {
        return [
            'MSP Fields' => [
                'msp_company_name' => 'Company Name',
                'msp_website_url' => 'Website URL',
                'msp_contact_email' => 'Contact Email'
            ],
            'Customer Fields' => [
                'customer_first_name' => 'Customer First Name',
                'customer_last_name' => 'Customer Last Name',
                'customer_email' => 'Customer Email',
                'customer_company' => 'Customer Company'
            ],
            'Invoice Fields' => [
                'invoice_number' => 'Invoice Number',
                'invoice_total' => 'Invoice Total Amount',
                'invoice_amount' => 'Invoice Subtotal',
                'invoice_tax' => 'Invoice Tax Amount',
                'invoice_date' => 'Invoice Date',
                'invoice_due_date' => 'Invoice Due Date',
                'invoice_url' => 'Invoice Payment URL'
            ],
            'System Fields' => [
                'portal_url' => 'Customer Portal URL',
                'password_reset_link' => 'Password Reset Link',
                'payment_amount' => 'Payment Amount',
                'payment_date' => 'Payment Date'
            ]
        ];
    }
} 