<?php

namespace EazyBackup\Whitelabel;

use WHMCS\Database\Capsule;

class MailService
{
    private array $cfg;

    public function __construct(array $vars = [])
    {
        $this->cfg = $vars;
    }

    public function seedSmtpIfMissing(int $tenantId): void
    {
        try {
            $exists = Capsule::table('eb_whitelabel_tenant_mail')->where('tenant_id', $tenantId)->exists();
            if ($exists) { return; }
            $row = Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first();
            if (!$row) { return; }
            $emailJson = json_decode((string)($row->email_json ?? '{}'), true) ?: [];
            $inherit = (int)($emailJson['inherit'] ?? 1) === 1;
            if ($inherit) { return; }
            $mode = (string)($emailJson['Mode'] ?? '');
            $mapMode = 'builtin';
            if ($mode === 'smtp-ssl') { $mapMode = 'smtp-ssl'; }
            else if ($mode === 'smtp') { $mapMode = 'smtp'; }
            $allowUnenc = !empty($emailJson['SMTPAllowUnencrypted']) ? 1 : 0;
            $ins = [
                'tenant_id' => $tenantId,
                'mode' => $mapMode,
                'host' => (string)($emailJson['SMTPHost'] ?? ''),
                'port' => (int)($emailJson['SMTPPort'] ?? 0),
                'username' => (string)($emailJson['SMTPUsername'] ?? ''),
                'password_enc' => null,
                'from_name' => (string)($emailJson['FromName'] ?? ''),
                'from_email' => (string)($emailJson['FromEmail'] ?? ''),
                'allow_unencrypted' => $allowUnenc,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $plain = (string)($emailJson['SMTPPassword'] ?? '');
            if ($plain !== '' && function_exists('encrypt')) { $ins['password_enc'] = encrypt($plain); }
            Capsule::table('eb_whitelabel_tenant_mail')->insert($ins);
        } catch (\Throwable $__) { /* ignore seed errors */ }
    }

    public function seedDefaultTemplatesIfMissing(int $tenantId): void
    {
        try {
            $exists = Capsule::table('eb_whitelabel_email_templates')->where('tenant_id', $tenantId)->where('key','welcome')->exists();
            if ($exists) { return; }
            $subject = 'Welcome to {{brand_name}} Backup';
            $bodyHtml = '<p>Hello {{customer_name}},</p><p>Welcome to {{brand_name}} Backup. You can download the software and get started here: <a href="{{portal_url}}">{{portal_url}}</a>.</p><p>Need help? Visit {{help_url}}.</p><p>Thanks,<br>{{brand_name}} Team</p>';
            Capsule::table('eb_whitelabel_email_templates')->insert([
                'tenant_id' => $tenantId,
                'key' => 'welcome',
                'name' => 'Welcome Email',
                'subject' => $subject,
                'body_html' => $bodyHtml,
                'body_text' => "Hello {{customer_name}},\n\nWelcome to {{brand_name}} Backup. Download here: {{portal_url}}\n\nHelp: {{help_url}}\n\nThanks,\n{{brand_name}} Team",
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $__) { /* ignore seed errors */ }
    }

    public function isSmtpConfigured(int $tenantId): bool
    {
        try {
            $r = Capsule::table('eb_whitelabel_tenant_mail')->where('tenant_id', $tenantId)->first();
            if (!$r) return false;
            if ((string)$r->mode === 'builtin') return false;
            if ((string)$r->host === '' || (int)$r->port <= 0) return false;
            return true;
        } catch (\Throwable $__) { return false; }
    }

    public function sendTemplate(int $tenantId, string $key, string $toEmail, array $vars = [], bool $allowInactiveForTest = false): array
    {
        $tpl = Capsule::table('eb_whitelabel_email_templates')->where('tenant_id',$tenantId)->where('key',$key)->first();
        if (!$tpl) { return ['ok'=>false,'error'=>'template_missing']; }
        if ((int)$tpl->is_active !== 1 && !$allowInactiveForTest) { return ['ok'=>false,'error'=>'template_disabled']; }

        // SMTP config
        $smtp = Capsule::table('eb_whitelabel_tenant_mail')->where('tenant_id',$tenantId)->first();
        if (!$smtp || (string)$smtp->mode === 'builtin') { return ['ok'=>false,'error'=>'smtp_missing']; }
        if ((string)$smtp->host === '' || (int)$smtp->port <= 0) { return ['ok'=>false,'error'=>'smtp_invalid']; }

        $subject = $this->renderString((string)$tpl->subject, $vars);
        $html = $this->renderString((string)$tpl->body_html, $vars);
        $text = $this->renderString((string)($tpl->body_text ?? ''), $vars);

        $logId = null; $status = 'queued'; $resp = '';
        try { $logId = Capsule::table('eb_whitelabel_email_log')->insertGetId(['tenant_id'=>$tenantId,'template_id'=>(int)$tpl->id,'to_email'=>$toEmail,'status'=>'queued','created_at'=>date('Y-m-d H:i:s')]); } catch (\Throwable $__) {}

        $ok = false; $err = '';
        try {
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                throw new \RuntimeException('phpmailer_missing');
            }
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string)$smtp->host;
            $mail->Port = (int)$smtp->port;
            $mail->SMTPAuth = ((string)$smtp->username !== '' || (string)$smtp->password_enc !== '');
            if ($mail->SMTPAuth) {
                $mail->Username = (string)$smtp->username;
                $pwd = (string)($smtp->password_enc ?? '');
                if ($pwd !== '' && function_exists('decrypt')) { $pwd = (string)decrypt($pwd); }
                $mail->Password = $pwd;
            }
            $mode = (string)$smtp->mode;
            if ($mode === 'smtp-ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else if ($mode === 'smtp') {
                if ((int)$smtp->allow_unencrypted === 1) { $mail->SMTPSecure = false; }
                else { $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; }
            }
            $fromEmail = (string)$smtp->from_email ?: 'no-reply@example.com';
            $fromName  = (string)$smtp->from_name ?: 'eazyBackup';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            if ($html !== '') { $mail->isHTML(true); $mail->Body = $html; $mail->AltBody = $text ?: strip_tags($html); }
            else { $mail->isHTML(false); $mail->Body = $text ?: $subject; }
            $mail->send();
            $ok = true; $status = 'sent'; $resp = 'ok';
        } catch (\Throwable $e) {
            $ok = false; $status = 'failed'; $err = $e->getMessage(); $resp = $err;
        }
        try {
            if ($logId) {
                Capsule::table('eb_whitelabel_email_log')->where('id',$logId)->update(['status'=>$status,'provider_resp'=>substr($resp,0,2000)]);
            }
        } catch (\Throwable $__) {}
        return $ok ? ['ok'=>true] : ['ok'=>false,'error'=>$err ?: 'send_failed'];
    }

    public function testSend(int $tenantId, string $key, string $toEmail, array $vars = []): array
    {
        return $this->sendTemplate($tenantId, $key, $toEmail, $vars, true);
    }

    public function renderString(string $tpl, array $vars): string
    {
        if ($tpl === '') return '';
        $out = $tpl;
        foreach ($vars as $k => $v) {
            $out = str_replace('{{'.$k.'}}', (string)$v, $out);
        }
        return $out;
    }
}


