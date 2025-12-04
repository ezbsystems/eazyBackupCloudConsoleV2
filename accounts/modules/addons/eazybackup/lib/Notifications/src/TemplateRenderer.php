<?php
declare(strict_types=1);

namespace EazyBackup\Notifications;

final class TemplateRenderer
{
    private static function logEvent(string $message): void
    {
        $msg = '[notify] ' . $message;
        if (function_exists('logActivity')) {
            try {
                logActivity($msg);
                return;
            } catch (\Throwable $e) {
                // fall through to error_log below if WHMCS logging failed
                $msg .= ' (logActivity failed: ' . $e->getMessage() . ')';
            }
        }
        error_log($msg);
    }

    /** Send using WHMCS Local API SendEmail with a given template name and merge vars. */
    public static function send(string $templateSettingKey, array $mergeVars): array
    {
        if (!function_exists('localAPI')) {
            self::logEvent('localAPI unavailable when attempting to send template key ' . $templateSettingKey);
            throw new \RuntimeException('localAPI unavailable');
        }
        $templateName = Config::templateName($templateSettingKey);
        if ($templateName === '') { throw new \RuntimeException('Template not configured: ' . $templateSettingKey); }
        // Normal payload uses template by name
        $payload = [ 'messagename' => $templateName ];

        // In test mode, we must not email customers. Build a custom email from the template content
        // and send to explicit email(s) without requiring a related client ID.
        if (Config::bool('notify_test_mode', false)) {
            $csv = (string)Config::get('notify_test_recipient', '');
            if ($csv === '') { return ['result'=>'error','message'=>'Test mode has no recipients']; }
            // Fetch template body/subject and perform a simple token replacement for {$key}
            $tpl = \WHMCS\Database\Capsule::table('tblemailtemplates')->where('name', $templateName)->first(['subject','message']);
            $subj = is_object($tpl) ? (string)$tpl->subject : '';
            $body = is_object($tpl) ? (string)$tpl->message : '';
            if ($subj === '' || trim($subj) === '') { $subj = (string)($mergeVars['subject'] ?? $templateName); }
            if ($body === '' || trim($body) === '') { $body = (string)($mergeVars['message'] ?? ($mergeVars['subject'] ?? $templateName)); }
            foreach ($mergeVars as $k => $v) {
                $token = '{$' . $k . '}';
                $subj = str_replace($token, (string)$v, $subj);
                $body = str_replace($token, (string)$v, $body);
            }
            $recips = array_filter(array_map('trim', preg_split('/[;,]+/', $csv) ?: []));
            if (empty($recips)) { return ['result'=>'error','message'=>'Test mode recipients invalid']; }
            $last = null; $all = [];
            $testClientId = (int)Config::get('notify_test_client_id', 0);
            foreach ($recips as $addr) {
                $payload = [
                    'customtype' => 'general',
                    'customsubject' => $subj,
                    'custommessage' => $body !== '' ? $body : ($mergeVars['subject'] ?? $templateName),
                    'to' => $addr,
                ];
                if ($testClientId > 0) { $payload['id'] = $testClientId; }
                if (getenv('EB_WS_DEBUG') === '1') { error_log('[notify] SendEmail TEST payload -> ' . json_encode($payload)); }
                $resp = localAPI('SendEmail', $payload);
                if (($resp['result'] ?? '') !== 'success') {
                    self::logEvent('Test-mode SendEmail failed for template ' . $templateName . ': ' . json_encode($resp));
                }
                $last = $resp; $all[] = $resp;
            }
            return ['result'=>($last['result'] ?? 'success'), 'responses'=>$all];
        } else {
            // Normal mode: associate with client when available so built-in merge fields can resolve
            if (isset($mergeVars['client_id']) && (int)$mergeVars['client_id'] > 0) {
                $payload['id'] = (int)$mergeVars['client_id'];
            }
            // Provide custom variables to the template. WHMCS expects 'customvars'
            // (supports associative array; older versions expect base64-encoded serialized array).
            // To maximize compatibility, serialize.
            $customVars = [];
            foreach ($mergeVars as $k => $v) {
                // Cast scalars/arrays to string-safe representations
                if (is_array($v)) {
                    $customVars[$k] = implode(',', array_map('strval', $v));
                } else if (is_bool($v)) {
                    $customVars[$k] = $v ? '1' : '0';
                } else if (is_object($v)) {
                    $customVars[$k] = json_encode($v);
                } else {
                    $customVars[$k] = (string)$v;
                }
            }
            $payload['customvars'] = base64_encode(serialize($customVars));
        }

        if (getenv('EB_WS_DEBUG') === '1') {
            error_log('[notify] SendEmail payload keys=' . implode(',', array_keys($payload)) . ' tmpl=' . $templateName);
        }
        try {
            $resp = localAPI('SendEmail', $payload);
        } catch (\Throwable $e) {
            self::logEvent('SendEmail threw for template ' . $templateName . ': ' . $e->getMessage());
            throw $e;
        }
        if (($resp['result'] ?? '') !== 'success') {
            self::logEvent('SendEmail returned non-success for template ' . $templateName . ': ' . json_encode($resp));
        }
        if (getenv('EB_WS_DEBUG') === '1') {
            error_log('[notify] SendEmail resp=' . json_encode($resp));
        }
        return $resp;
    }
}


