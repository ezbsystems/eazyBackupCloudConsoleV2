<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * TenantEmailService - Sends tenant portal emails using WHMCS email templates.
 * 
 * Uses localAPI('SendEmail') for proper WHMCS integration including:
 * - Email logging
 * - Template customization by admins
 * - Proper merge field handling
 */
class TenantEmailService
{
    private static $module = 'cloudstorage';

    /**
     * Get an addon config setting value.
     */
    private static function getConfigSetting(string $setting): ?string
    {
        try {
            $value = Capsule::table('tbladdonmodules')
                ->where('module', self::$module)
                ->where('setting', $setting)
                ->value('value');
            return $value ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get template name by ID from WHMCS.
     */
    private static function getTemplateName(?string $templateSetting): ?string
    {
        if (empty($templateSetting)) {
            return null;
        }

        // If numeric, look up by ID
        if (ctype_digit($templateSetting)) {
            try {
                $template = Capsule::table('tblemailtemplates')
                    ->where('id', (int)$templateSetting)
                    ->where('type', 'general')
                    ->first(['name']);
                
                if ($template && !empty($template->name)) {
                    return $template->name;
                }
            } catch (\Throwable $e) {
                logModuleCall(self::$module, 'TenantEmailService::getTemplateName', 
                    ['template_id' => $templateSetting], $e->getMessage());
            }
        }

        // Otherwise assume it's a template name
        return $templateSetting;
    }

    /**
     * Send an email using WHMCS localAPI.
     */
    private static function sendEmail(string $templateName, string $toEmail, array $mergeVars): array
    {
        if (!function_exists('localAPI')) {
            logModuleCall(self::$module, 'TenantEmailService::sendEmail', 
                ['template' => $templateName], 'localAPI not available');
            return ['status' => 'error', 'message' => 'localAPI not available'];
        }

        // Build custom vars for merge
        $customVars = [];
        foreach ($mergeVars as $k => $v) {
            if (is_array($v)) {
                $customVars[$k] = implode(',', array_map('strval', $v));
            } elseif (is_bool($v)) {
                $customVars[$k] = $v ? '1' : '0';
            } elseif (is_object($v)) {
                $customVars[$k] = json_encode($v);
            } else {
                $customVars[$k] = (string)$v;
            }
        }

        $payload = [
            'messagename' => $templateName,
            'customtype' => 'general',
            'customsubject' => '', // Use template subject
            'custommessage' => '', // Use template message
            'customvars' => base64_encode(serialize($customVars)),
        ];

        // For non-WHMCS users, we need to specify the recipient directly
        // Using 'customtype' => 'general' with explicit email
        $payload['type'] = 'general';
        
        try {
            $response = localAPI('SendEmail', $payload);
            
            logModuleCall(self::$module, 'TenantEmailService::sendEmail', [
                'template' => $templateName,
                'to' => $toEmail,
                'vars' => array_keys($mergeVars),
            ], json_encode($response));

            if (($response['result'] ?? '') === 'success') {
                return ['status' => 'success', 'message' => 'Email sent'];
            } else {
                return [
                    'status' => 'error',
                    'message' => $response['message'] ?? 'Failed to send email',
                    'response' => $response
                ];
            }
        } catch (\Throwable $e) {
            logModuleCall(self::$module, 'TenantEmailService::sendEmail', 
                ['template' => $templateName, 'to' => $toEmail], $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Send welcome email to newly created tenant admin.
     * 
     * @param string $adminEmail Recipient email
     * @param string $adminName Recipient name
     * @param string $tenantName Tenant/company name
     * @param string $tenantSlug Tenant slug for portal URL
     * @param string $password Temporary password
     * @param int $mspClientId MSP's WHMCS client ID (for branding)
     * @return array Result with status and message
     */
    public static function sendWelcomeEmail(
        string $adminEmail,
        string $adminName,
        string $tenantName,
        string $tenantSlug,
        string $password,
        int $mspClientId
    ): array {
        // Get configured template
        $templateSetting = self::getConfigSetting('tenant_welcome_email_template');
        $templateName = self::getTemplateName($templateSetting);

        if (!$templateName) {
            logModuleCall(self::$module, 'sendWelcomeEmail', 
                ['to' => $adminEmail, 'tenant' => $tenantName], 
                'Template not configured');
            return ['status' => 'skipped', 'message' => 'Welcome email template not configured'];
        }

        // Build portal URL
        $systemUrl = rtrim(\WHMCS\Config\Setting::getValue('SystemURL'), '/');
        $portalUrl = $systemUrl . '/portal/index.php?msp=' . urlencode($tenantSlug);

        // Get MSP info for branding
        $mspClient = Capsule::table('tblclients')
            ->where('id', $mspClientId)
            ->first(['companyname', 'firstname', 'lastname', 'email']);

        $mspName = 'Your Service Provider';
        if ($mspClient) {
            $mspName = $mspClient->companyname ?: trim(($mspClient->firstname ?? '') . ' ' . ($mspClient->lastname ?? ''));
        }

        $mergeVars = [
            'tenant_name' => $tenantName,
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'portal_url' => $portalUrl,
            'temp_password' => $password,
            'msp_name' => $mspName,
            'msp_email' => $mspClient->email ?? '',
        ];

        return self::sendEmail($templateName, $adminEmail, $mergeVars);
    }

    /**
     * Send password reset email to tenant portal user.
     * 
     * @param string $userEmail Recipient email
     * @param string $userName Recipient name
     * @param string $resetUrl Full reset URL with token
     * @param string $tenantName Tenant/company name
     * @return array Result with status and message
     */
    public static function sendPasswordResetEmail(
        string $userEmail,
        string $userName,
        string $resetUrl,
        string $tenantName
    ): array {
        // Get configured template
        $templateSetting = self::getConfigSetting('tenant_password_reset_email_template');
        $templateName = self::getTemplateName($templateSetting);

        if (!$templateName) {
            logModuleCall(self::$module, 'sendPasswordResetEmail', 
                ['to' => $userEmail], 
                'Template not configured');
            return ['status' => 'skipped', 'message' => 'Password reset email template not configured'];
        }

        // Get system company name
        $companyName = \WHMCS\Config\Setting::getValue('CompanyName') ?: 'e3 Cloud Backup';

        $mergeVars = [
            'user_name' => $userName,
            'user_email' => $userEmail,
            'reset_url' => $resetUrl,
            'tenant_name' => $tenantName,
            'company_name' => $companyName,
        ];

        return self::sendEmail($templateName, $userEmail, $mergeVars);
    }

    /**
     * Fallback email sending using PHPMailer or mail() for when WHMCS templates
     * are not configured. This maintains backwards compatibility.
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $html HTML body
     * @param string $text Plain text body
     * @param string $fromEmail From email
     * @param string $fromName From name
     * @return bool Success
     */
    public static function sendFallbackEmail(
        string $to,
        string $subject,
        string $html,
        string $text,
        string $fromEmail = '',
        string $fromName = ''
    ): bool {
        if (empty($fromEmail)) {
            $fromEmail = \WHMCS\Config\Setting::getValue('Email') ?: 'no-reply@example.com';
        }
        if (empty($fromName)) {
            $fromName = \WHMCS\Config\Setting::getValue('CompanyName') ?: 'e3 Cloud Backup';
        }

        $sent = false;

        // Try PHPMailer first
        try {
            if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                $m = new \PHPMailer\PHPMailer\PHPMailer(true);
                $m->setFrom($fromEmail, $fromName);
                $m->addAddress($to);
                $m->Subject = $subject;
                $m->isHTML(true);
                $m->Body = $html;
                $m->AltBody = $text ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
                $m->send();
                $sent = true;
            }
        } catch (\Throwable $e) {
            logModuleCall(self::$module, 'sendFallbackEmail', 
                ['to' => $to, 'subject' => $subject], 
                'PHPMailer failed: ' . $e->getMessage());
        }

        // Fallback to mail()
        if (!$sent) {
            $headers = "From: {$fromName} <{$fromEmail}>\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $sent = @mail($to, $subject, $html, $headers);
        }

        return $sent;
    }
}

