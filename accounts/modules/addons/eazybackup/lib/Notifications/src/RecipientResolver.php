<?php
declare(strict_types=1);

namespace EazyBackup\Notifications;

use WHMCS\Database\Capsule;

final class RecipientResolver
{
    /**
     * Resolve recipients for a service according to policy.
     * @return string[] list of emails
     */
    public static function resolve(int $serviceId, string $policy, string $customCsv = ''): array
    {
        // Test mode: short-circuit to test recipients only
        if (Config::bool('notify_test_mode', false)) {
            $csv = (string)Config::get('notify_test_recipient', '');
            $out = [];
            foreach (preg_split('/[;,]+/', $csv) ?: [] as $e) {
                $e = trim($e);
                if ($e !== '') { $out[$e] = true; }
            }
            return array_keys($out);
        }

        $emails = [];
        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first(['userid']);
        if (!$service) return [];
        // Load client-level overrides
        try {
            $pref = Capsule::table('eb_client_notify_prefs')->where('client_id', (int)$service->userid)->first();
            if ($pref) {
                $p = (string)($pref->routing_policy ?? '');
                if ($p !== '') { $policy = $p; }
                if ($policy === 'custom') { $customCsv = (string)($pref->custom_recipients ?? $customCsv); }
            }
        } catch (\Throwable $_) { /* ignore */ }

        $client = Capsule::table('tblclients')->where('id', $service->userid)->first(['email']);
        $primary = is_object($client) ? (string)$client->email : '';

        if ($policy === 'custom') {
            $parts = preg_split('/[;,]+/', (string)$customCsv) ?: [];
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') { $emails[$p] = true; }
            }
            return array_keys($emails);
        }

        if ($policy === 'primary' && $primary) { $emails[$primary] = true; }

        $contacts = Capsule::table('tblcontacts')->where('userid', $service->userid)->get();
        foreach ($contacts as $c) {
            $email = (string)$c->email;
            if ($email === '') continue;
            if ($policy === 'billing' && ((int)$c->invoiceemails === 1 || (int)$c->generalemails === 1)) { $emails[$email] = true; }
            if ($policy === 'technical' && ((int)$c->supportemails === 1 || (int)$c->generalemails === 1)) { $emails[$email] = true; }
        }
        if ($primary) { $emails[$primary] = true; }
        return array_keys($emails);
    }
}


