<?php

namespace PartnerHub;

use WHMCS\Database\Capsule;

class SettingsService
{
    private static function defaults(?string $fallbackCurrency = 'CAD'): array
    {
        return [
            'checkout_experience' => [
                'require_billing_address' => 'postal_code', // none|postal_code|full_address
                'collect_tax_id' => false,
                'statement_descriptor' => '',
                'support_url' => '',
                'default_currency' => $fallbackCurrency ?: 'USD',
            ],
            'payment_methods' => [
                'cards' => true,
                'bank_debits' => false,
                'apple_google_pay' => true,
                'retry_mandate_bank_debits' => false,
            ],
            'trials_proration' => [
                'default_trial_days' => 0,
                'proration_behavior' => 'prorate_now', // prorate_now|prorate_on_next_invoice|no_proration
                'end_trial_on_usage' => false,
            ],
            'dunning_collections' => [
                'retry_schedule_days' => [0,3,7,14],
                'send_payment_failed_email' => true,
                'auto_pause_after_attempts' => null,
                'auto_cancel_after_days' => null,
                'take_past_due_on_next_success' => true,
            ],
            'customer_portal' => [
                'enabled' => false,
                'allow_update_payment' => true,
                'allow_view_invoices' => true,
                'allow_cancel' => false,
                'allow_resume' => true,
                'return_url' => '',
            ],
        ];
    }

    private static function arrayMergeDeep(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                $base[$k] = self::arrayMergeDeep($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }

    public static function getCheckoutSettings(int $mspId): array
    {
        $fallbackCurrency = 'CAD';
        try {
            $cur = (string)(Capsule::table('eb_msp_accounts')->where('id',$mspId)->value('default_currency') ?? '');
            if ($cur !== '') { $fallbackCurrency = strtoupper($cur); }
        } catch (\Throwable $__) {}
        $defaults = self::defaults($fallbackCurrency);
        try {
            $row = Capsule::table('eb_msp_settings')->where('msp_id',$mspId)->first(['checkout_json']);
            if ($row && isset($row->checkout_json)) {
                $stored = json_decode((string)$row->checkout_json, true);
                if (is_array($stored)) {
                    return self::arrayMergeDeep($defaults, $stored);
                }
            }
        } catch (\Throwable $__) {}
        return $defaults;
    }

    public static function saveCheckoutSettings(int $mspId, array $json): void
    {
        $now = date('Y-m-d H:i:s');
        $payload = [
            'msp_id' => $mspId,
            'checkout_json' => json_encode($json),
            'updated_at' => $now,
        ];
        try {
            $exists = Capsule::table('eb_msp_settings')->where('msp_id',$mspId)->exists();
            if ($exists) {
                Capsule::table('eb_msp_settings')->where('msp_id',$mspId)->update($payload);
            } else {
                $payload['created_at'] = $now;
                Capsule::table('eb_msp_settings')->insert($payload);
            }
        } catch (\Throwable $__) { /* ignore */ }
        // Mirror default currency to eb_msp_accounts if provided
        $cur = strtoupper((string)($json['checkout_experience']['default_currency'] ?? ''));
        if ($cur !== '') {
            try { Capsule::table('eb_msp_accounts')->where('id',$mspId)->update(['default_currency'=>$cur, 'updated_at'=>$now]); } catch (\Throwable $__) {}
        }
    }

    private static function taxDefaults(): array
    {
        return [
            'tax_mode' => [
                'stripe_tax_enabled' => false,
                'default_tax_behavior' => 'exclusive',
                'respect_exemption' => true,
            ],
            'registrations' => [
                'business_address' => [ 'line1'=>'', 'line2'=>'', 'city'=>'', 'state'=>'', 'postal'=>'', 'country'=>'CA' ],
            ],
            'invoice_presentation' => [
                'invoice_prefix' => '',
                'footer_md' => '',
                'show_logo' => true,
                'show_legal_override' => false,
                'legal_name_override' => '',
                'payment_terms' => 'due_immediately',
                'show_qty_x_price' => true,
            ],
            'credit_notes' => [
                'allow_partial' => true,
                'allow_negative_lines' => false,
                'default_reason' => 'customer_request',
            ],
            'rounding' => [
                'rounding_mode' => 'bankers_rounding',
                'writeoff_threshold_cents' => 50,
            ],
        ];
    }

    public static function getTaxSettings(int $mspId): array
    {
        $defaults = self::taxDefaults();
        try {
            $row = Capsule::table('eb_msp_settings')->where('msp_id',$mspId)->first(['tax_json']);
            if ($row && isset($row->tax_json)) {
                $stored = json_decode((string)$row->tax_json, true);
                if (is_array($stored)) { return self::arrayMergeDeep($defaults, $stored); }
            }
        } catch (\Throwable $__) {}
        return $defaults;
    }

    public static function saveTaxSettings(int $mspId, array $json): void
    {
        $now = date('Y-m-d H:i:s');
        $payload = [ 'msp_id' => $mspId, 'tax_json' => json_encode($json), 'updated_at' => $now ];
        try {
            $exists = Capsule::table('eb_msp_settings')->where('msp_id',$mspId)->exists();
            if ($exists) { Capsule::table('eb_msp_settings')->where('msp_id',$mspId)->update($payload); }
            else { $payload['created_at']=$now; Capsule::table('eb_msp_settings')->insert($payload); }
        } catch (\Throwable $__) { /* ignore */ }
    }

    public static function listRegistrations(int $mspId): array
    {
        try { $rows = Capsule::table('eb_msp_tax_regs')->where('msp_id',$mspId)->orderBy('created_at','desc')->get(); }
        catch (\Throwable $__) { $rows = []; }
        $out = [];
        foreach ($rows as $r) { $out[] = (array)$r; }
        return $out;
    }

    public static function upsertRegistration(int $mspId, array $reg): array
    {
        $now = date('Y-m-d H:i:s');
        $data = [
            'msp_id' => $mspId,
            'country' => strtoupper((string)($reg['country'] ?? '')),
            'region' => strtoupper((string)($reg['region'] ?? '')) ?: null,
            'registration_number' => (string)($reg['registration_number'] ?? ''),
            'legal_name' => (string)($reg['legal_name'] ?? '') ?: null,
            'stripe_registration_id' => (string)($reg['stripe_registration_id'] ?? '') ?: null,
            'source' => (string)($reg['source'] ?? 'local') === 'stripe' ? 'stripe' : 'local',
            'is_active' => (int)($reg['is_active'] ?? 1) ? 1 : 0,
            'updated_at' => $now,
        ];
        $id = (int)($reg['id'] ?? 0);
        try {
            if ($id > 0) { Capsule::table('eb_msp_tax_regs')->where('id',$id)->where('msp_id',$mspId)->update($data); }
            else { $data['created_at']=$now; $id = (int)Capsule::table('eb_msp_tax_regs')->insertGetId($data); }
        } catch (\Throwable $__) { /* ignore */ }
        $data['id'] = $id;
        return $data;
    }

    public static function deleteRegistration(int $mspId, int $id): bool
    {
        try { Capsule::table('eb_msp_tax_regs')->where('id',$id)->where('msp_id',$mspId)->delete(); return true; } catch (\Throwable $__) { return false; }
    }

    public static function auditTax(int $mspId, string $action, array $before = null, array $after = null, array $meta = null, ?int $userId = null): void
    {
        try {
            Capsule::table('eb_msp_tax_audit')->insert([
                'msp_id' => $mspId,
                'user_id' => $userId,
                'action' => $action,
                'before_json' => $before ? json_encode($before) : null,
                'after_json' => $after ? json_encode($after) : null,
                'meta_json' => $meta ? json_encode($meta) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $__) { /* ignore */ }
    }

    // ===== Email Settings =====
    private static function emailDefaults(): array
    {
        return [
            'sender' => [
                'from_name' => '',
                'from_address' => '',
                'reply_to' => '',
                'cc_finance' => [],
                'brand' => [ 'header_image' => '', 'primary_color' => '#1B2C50' ],
            ],
            'smtp' => [
                'mode' => 'builtin', // builtin|smtp|smtp-ssl
                'host' => '', 'port' => 587, 'username' => '', 'password_enc' => '', 'allow_unencrypted' => false,
            ],
            'templates' => [
                'welcome' => [ 'subject' => 'Welcome to {{ msp.brand.name }} Backup', 'body_md' => 'Hi {{ customer.name }}, welcome!' ],
                'trial_ending' => [ 'subject' => 'Your trial ends soon', 'body_md' => 'Hi {{ customer.name }}, your trial ends soon.' ],
                'payment_failed' => [ 'subject' => 'Payment failed on {{ invoice.number }}', 'body_md' => 'We could not process your payment.' ],
                'card_expiring' => [ 'subject' => 'Your card is expiring soon', 'body_md' => 'Please update your payment method.' ],
                'subscription_changed' => [ 'subject' => 'Subscription updated', 'body_md' => 'Your subscription has changed.' ],
                'new_invoice' => [ 'subject' => 'New invoice {{ invoice.number }}', 'body_md' => 'A new invoice is available.' ],
                'pay_link' => [ 'subject' => 'Complete your payment', 'body_md' => '{{ pay_link_url }}' ],
            ],
            'stripe_emails' => [ 'send_invoices' => true, 'send_receipts' => true, 'bcc_msp_on_invoices' => false ],
        ];
    }

    public static function getEmailSettings(int $mspId): array
    {
        $defaults = self::emailDefaults();
        try {
            $row = Capsule::table('eb_msp_settings')->where('msp_id',$mspId)->first(['email_json']);
            if ($row && isset($row->email_json)) {
                $stored = json_decode((string)$row->email_json, true);
                if (is_array($stored)) { return self::arrayMergeDeep($defaults, $stored); }
            }
        } catch (\Throwable $__) {}
        return $defaults;
    }

    public static function saveEmailSettings(int $mspId, array $json): void
    {
        $now = date('Y-m-d H:i:s');
        $payload = [ 'msp_id' => $mspId, 'email_json' => json_encode($json), 'updated_at' => $now ];
        try {
            $exists = Capsule::table('eb_msp_settings')->where('msp_id',$mspId)->exists();
            if ($exists) { Capsule::table('eb_msp_settings')->where('msp_id',$mspId)->update($payload); }
            else { $payload['created_at']=$now; Capsule::table('eb_msp_settings')->insert($payload); }
        } catch (\Throwable $__) { /* ignore */ }
    }
    public static function hasPublishedPricesInOtherCurrencies(int $mspId, string $currency): bool
    {
        $currency = strtoupper($currency);
        try {
            $count = Capsule::table('eb_catalog_prices as p')
                ->join('eb_catalog_products as pr','pr.id','=','p.product_id')
                ->where('pr.msp_id',$mspId)
                ->where('p.is_published',1)
                ->whereRaw('UPPER(p.currency) <> ?', [$currency])
                ->count();
            return (int)$count > 0;
        } catch (\Throwable $__) { return false; }
    }
}


