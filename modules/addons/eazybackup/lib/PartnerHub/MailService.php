<?php

namespace PartnerHub;

use WHMCS\Database\Capsule;

class MailService
{
    private static function getEmailSettings(int $mspId): array
    {
        return SettingsService::getEmailSettings($mspId);
    }

    private static function buildMailer(array $smtp)
    {
        // Prefer PHPMailer if available in environment
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mode = (string)($smtp['mode'] ?? 'builtin');
            if ($mode === 'smtp' || $mode === 'smtp-ssl') {
                $mailer->isSMTP();
                $mailer->Host = (string)($smtp['host'] ?? '');
                $mailer->Port = (int)($smtp['port'] ?? 587);
                $mailer->SMTPAuth = ((string)($smtp['username'] ?? '') !== '');
                $mailer->Username = (string)($smtp['username'] ?? '');
                $mailer->Password = (string)($smtp['password_enc'] ?? '');
                $mailer->SMTPSecure = $mode === 'smtp-ssl' ? 'ssl' : 'tls';
            }
            return $mailer;
        }
        return null;
    }

    private static function renderMarkdownToHtml(string $md): string
    {
        // Lightweight Markdown: **bold**, *italic*, [text](url), newlines
        $html = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');
        $html = preg_replace('/\*\*(.+?)\*\*/s','<strong>$1</strong>',$html);
        $html = preg_replace('/\*(.+?)\*/s','<em>$1</em>',$html);
        $html = preg_replace('/\[(.+?)\]\((https?:[^\)]+)\)/','<a href="$2" target="_blank" rel="noopener noreferrer">$1<\/a>',$html);
        $html = nl2br($html);
        return $html;
    }

    private static function sanitizeHtml(string $html): string
    {
        // Strip scripts and on* attributes
        $html = preg_replace('/<script\b[^>]*>([\s\S]*?)<\/script>/i','',$html);
        $html = preg_replace('/ on[a-z]+\s*=\s*"[^"]*"/i','',$html);
        $html = preg_replace("/ on[a-z]+\s*=\s*'[^']*'/i",'',$html);
        return $html;
    }

    private static function renderTemplateBody(string $md, array $brand): string
    {
        $primary = (string)($brand['primary_color'] ?? '#1B2C50');
        $content = self::sanitizeHtml(self::renderMarkdownToHtml($md));
        $headerImg = (string)($brand['header_image'] ?? '');
        $head = $headerImg !== '' ? '<div style="text-align:center;margin-bottom:16px"><img src="'.htmlspecialchars($headerImg,ENT_QUOTES,'UTF-8').'" alt="" style="max-width:240px;max-height:80px"></div>' : '';
        return '<div style="background:#0b1220;color:#e5e7eb;font-family:Inter,ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:24px">'
             . $head
             . '<div style="background:#0f1729;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:20px">'
             . $content
             . '</div>'
             . '<div style="margin-top:16px;color:#9ca3af;font-size:12px">Sent by eazyBackup • <a style="color:'.$primary.'" href="#" target="_blank">Manage preferences</a></div>'
             . '</div>';
    }

    private static function replaceTokens(string $text, array $vars): string
    {
        foreach ($vars as $k=>$v) {
            if (is_scalar($v)) { $text = str_replace('{{ '.$k.' }}', (string)$v, $text); }
        }
        // Flatten nested common tokens like customer.name
        if (isset($vars['customer']) && is_array($vars['customer'])) {
            foreach ($vars['customer'] as $k=>$v) { $text = str_replace('{{ customer.'.$k.' }}', (string)$v, $text); }
        }
        if (isset($vars['invoice']) && is_array($vars['invoice'])) {
            foreach ($vars['invoice'] as $k=>$v) { $text = str_replace('{{ invoice.'.$k.' }}', (string)$v, $text); }
        }
        if (isset($vars['subscription']) && is_array($vars['subscription'])) {
            foreach ($vars['subscription'] as $k=>$v) { $text = str_replace('{{ subscription.'.$k.' }}', (string)$v, $text); }
        }
        if (isset($vars['msp']) && is_array($vars['msp'])) {
            foreach ($vars['msp'] as $k=>$v) {
                if (is_array($v)) { foreach ($v as $kk=>$vv) { $text = str_replace('{{ msp.'.$k.'.'.$kk.' }}', (string)$vv, $text); } }
                else { $text = str_replace('{{ msp.'.$k.' }}', (string)$v, $text); }
            }
        }
        return $text;
    }

    public static function sendTemplate(int $mspId, string $key, string $toEmail, array $vars, bool $allowInactiveForTest = false): array
    {
        $settings = self::getEmailSettings($mspId);
        $tpl = (array)($settings['templates'][$key] ?? []);
        $subject = (string)($tpl['subject'] ?? '');
        $bodyMd = (string)($tpl['body_md'] ?? '');
        $subject = self::replaceTokens($subject, $vars);
        $bodyMd = self::replaceTokens($bodyMd, $vars);
        $html = self::renderTemplateBody($bodyMd, (array)($settings['sender']['brand'] ?? []));

        $fromName = (string)($settings['sender']['from_name'] ?? 'eazyBackup');
        $fromAddr = (string)($settings['sender']['from_address'] ?? 'no-reply@example.com');
        $replyTo = (string)($settings['sender']['reply_to'] ?? '');
        $cc = (array)($settings['sender']['cc_finance'] ?? []);

        $mailer = self::buildMailer((array)($settings['smtp'] ?? []));
        try {
            if ($mailer) {
                $mailer->setFrom($fromAddr, $fromName);
                $mailer->addAddress($toEmail);
                if ($replyTo !== '') { $mailer->addReplyTo($replyTo); }
                foreach ($cc as $addr) { if (is_string($addr) && $addr !== '') { $mailer->addCC($addr); } }
                $mailer->isHTML(true);
                $mailer->Subject = $subject;
                $mailer->Body = $html;
                $mailer->AltBody = strip_tags($bodyMd);
                $mailer->send();
                return ['ok'=>true];
            }
            // Fallback: naive mail()
            $headers = "From: ".$fromName." <".$fromAddr.">\r\n";
            if ($replyTo !== '') { $headers .= "Reply-To: ".$replyTo."\r\n"; }
            $headers .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
            @mail($toEmail, $subject, $html, $headers);
            return ['ok'=>true];
        } catch (\Throwable $e) {
            return ['ok'=>false,'error'=>$e->getMessage()];
        }
    }
}


