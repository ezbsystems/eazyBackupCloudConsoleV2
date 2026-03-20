<?php

namespace PartnerHub;

use WHMCS\Database\Capsule;

class StripeService
{
    private string $apiBase = 'https://api.stripe.com';

    private function getSetting(string $key): string
    {
        try {
            $val = Capsule::table('tbladdonmodules')
                ->where('module','eazybackup')
                ->where('setting',$key)
                ->value('value');
            return (string)($val ?? '');
        } catch (\Throwable $__) { return ''; }
    }

    public function getSecret(): string
    {
        return $this->getSetting('stripe_platform_secret');
    }

    public function getPublishable(): string
    {
        return $this->getSetting('stripe_platform_publishable_key');
    }

    private function request(string $method, string $path, array $params = [], ?string $apiKey = null, ?string $stripeAccount = null, ?array $extraHeaders = null): array
    {
        $apiKey = $apiKey ?: $this->getSecret();
        $url = rtrim($this->apiBase,'/').$path;
        $ch = curl_init();
        $headers = [
            'Authorization: Bearer '.$apiKey,
        ];
        if ($stripeAccount) {
            $headers[] = 'Stripe-Account: '.$stripeAccount;
        }
        if (is_array($extraHeaders)) {
            foreach ($extraHeaders as $header) {
                if (is_string($header) && $header !== '') {
                    $headers[] = $header;
                }
            }
        }
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];
        $methodU = strtoupper($method);
        if ($methodU === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        } elseif ($methodU === 'GET' && !empty($params)) {
            $opts[CURLOPT_URL] = $url.'?'.http_build_query($params);
        } elseif ($methodU === 'DELETE') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
            if (!empty($params)) {
                $opts[CURLOPT_URL] = $url.'?'.http_build_query($params);
            }
        }
        curl_setopt_array($ch, $opts);
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) {
            throw new \RuntimeException('Stripe request failed: '.$err);
        }
        $data = json_decode($res, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Stripe response parse error (HTTP '.$code.'): '.$res);
        }
        if ($code >= 400) {
            $msg = (string)($data['error']['message'] ?? 'Stripe error');
            throw new \RuntimeException('Stripe error (HTTP '.$code.'): '.$msg);
        }
        return $data;
    }

    public function ensureConnectedAccount(int $mspId): string
    {
        $row = Capsule::table('eb_msp_accounts')->where('id',$mspId)->first();
        if (!$row) { throw new \RuntimeException('MSP not found'); }
        $acct = (string)($row->stripe_connect_id ?? '');
        if ($acct === '') {
            $created = $this->request('POST','/v1/accounts',[
                'type' => 'express',
                'capabilities[card_payments][requested]' => 'true',
                'capabilities[transfers][requested]' => 'true',
            ]);
            $acct = (string)($created['id'] ?? '');
            if ($acct !== '') {
                Capsule::table('eb_msp_accounts')->where('id',$mspId)->update([
                    'stripe_connect_id' => $acct,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        return $acct;
    }

    public function createAccountLink(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        $al = $this->request('POST','/v1/account_links',[
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);
        return (string)($al['url'] ?? '');
    }

    public function retrieveAccount(string $accountId): array
    {
        return $this->request('GET','/v1/accounts/'.$accountId);
    }

    public function createAccountSession(string $accountId, array $components = []): array
    {
        // Example: ['components[account_management][enabled]' => 'true']
        $params = array_merge(['account' => $accountId], $components);
        return $this->request('POST','/v1/account_sessions', $params);
    }

    public function createAccountLoginLink(string $accountId): array
    {
        // Express login link to the Stripe-hosted dashboard for the connected account
        return $this->request('POST','/v1/accounts/'.$accountId.'/login_links');
    }

    public function createProduct(string $name, string $description = '', ?string $stripeAccount = null): array
    {
        $params = ['name' => $name];
        if ($description !== '') { $params['description'] = $description; }
        return $this->request('POST','/v1/products', $params, null, $stripeAccount);
    }

    public function createPrice(string $productId, string $currency, int $unitAmount, string $interval = 'month', bool $metered = false, ?string $stripeAccount = null): array
    {
        $params = [
            'product' => $productId,
            'currency' => strtolower($currency),
            'unit_amount' => $unitAmount,
            'recurring[interval]' => $interval,
        ];
        if ($metered) {
            $params['recurring[usage_type]'] = 'metered';
            $params['recurring[aggregate_usage]'] = 'sum';
        }
        return $this->request('POST','/v1/prices', $params, null, $stripeAccount);
    }

    public function ensureStripeCustomerFor(int $tenantId, ?string $stripeAccount = null): string
    {
        $tenant = Capsule::table('eb_tenants')->where('id',$tenantId)->first();
        if (!$tenant) { throw new \RuntimeException('Tenant not found'); }
        $scus = (string)($tenant->stripe_customer_id ?? '');
        if ($scus !== '') { return $scus; }
        $name = trim((string)($tenant->name ?? ''));
        if ($name === '') { $name = trim((string)($tenant->contact_name ?? '')); }
        $email = (string)($tenant->contact_email ?? '');
        $created = $this->request('POST','/v1/customers',[
            'name' => $name !== '' ? $name : 'Tenant ' . $tenantId,
            'email' => $email !== '' ? $email : null,
        ], null, $stripeAccount);
        $scus = (string)($created['id'] ?? '');
        if ($scus !== '') {
            Capsule::table('eb_tenants')->where('id',$tenantId)->update([
                'stripe_customer_id' => $scus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $scus;
    }

    public function createSetupIntent(string $stripeCustomerId, ?string $stripeAccount = null): array
    {
        return $this->request('POST','/v1/setup_intents',[
            'customer' => $stripeCustomerId,
            'payment_method_types[]' => 'card',
            'usage' => 'off_session',
        ], null, $stripeAccount);
    }

    public function createSetupIntentAdhoc(?string $stripeAccount = null): array
    {
        return $this->request('POST','/v1/setup_intents',[
            'payment_method_types[]' => 'card',
            'usage' => 'off_session',
        ], null, $stripeAccount);
    }

    public function createSubscription(string $stripeCustomerId, string $priceId, string $mspAccountId, ?float $applicationFeePercent = null): array
    {
        $params = [
            'customer' => $stripeCustomerId,
            'items[0][price]' => $priceId,
            'automatic_tax[enabled]' => 'true',
            'payment_behavior' => 'default_incomplete',
            'collection_method' => 'charge_automatically',
        ];
        if ($applicationFeePercent !== null && $applicationFeePercent > 0) {
            $params['application_fee_percent'] = $applicationFeePercent;
        }
        // Direct charge on connected account by sending Stripe-Account header
        return $this->request('POST','/v1/subscriptions', $params, null, $mspAccountId);
    }

    public function attachPaymentMethod(string $paymentMethodId, string $stripeCustomerId, ?string $stripeAccount = null): array
    {
        return $this->request('POST', '/v1/payment_methods/'.$paymentMethodId.'/attach', [ 'customer' => $stripeCustomerId ], null, $stripeAccount);
    }

    public function updateCustomerDefaultPaymentMethod(string $stripeCustomerId, string $paymentMethodId, ?string $stripeAccount = null): array
    {
        return $this->request('POST', '/v1/customers/'.$stripeCustomerId, [ 'invoice_settings[default_payment_method]' => $paymentMethodId ], null, $stripeAccount);
    }

    public function retrieveCustomer(string $stripeCustomerId, ?string $stripeAccount = null): array
    {
        return $this->request('GET', '/v1/customers/'.$stripeCustomerId, [], null, $stripeAccount);
    }

    /**
     * Update a Customer on the platform or a connected account (Stripe-Account header).
     * $fields may include: name, email, phone, and address (array with line1, line2, city, state, postal_code, country).
     */
    public function updateCustomer(string $stripeCustomerId, array $fields, ?string $stripeAccount = null): array
    {
        $stripeCustomerId = trim($stripeCustomerId);
        if ($stripeCustomerId === '') {
            throw new \RuntimeException('Missing Stripe customer id');
        }
        $params = [];
        if (array_key_exists('name', $fields)) {
            $n = trim((string)($fields['name'] ?? ''));
            $params['name'] = $n;
        }
        if (array_key_exists('email', $fields)) {
            $e = strtolower(trim((string)($fields['email'] ?? '')));
            $params['email'] = $e;
        }
        if (array_key_exists('phone', $fields)) {
            $p = trim((string)($fields['phone'] ?? ''));
            $params['phone'] = $p;
        }
        if (isset($fields['address']) && is_array($fields['address'])) {
            $a = $fields['address'];
            $map = [
                'line1' => 'line1',
                'line2' => 'line2',
                'city' => 'city',
                'state' => 'state',
                'postal_code' => 'postal_code',
                'country' => 'country',
            ];
            foreach ($map as $src => $stripeKey) {
                if (!array_key_exists($src, $a)) {
                    continue;
                }
                $v = trim((string)($a[$src] ?? ''));
                if ($src === 'country') {
                    $v = strtoupper($v);
                }
                $params['address['.$stripeKey.']'] = $v;
            }
        }
        if (empty($params)) {
            return $this->retrieveCustomer($stripeCustomerId, $stripeAccount);
        }
        return $this->request('POST', '/v1/customers/'.$stripeCustomerId, $params, null, $stripeAccount);
    }

    public function listCustomerPaymentMethods(string $stripeCustomerId, string $type = 'card', ?string $stripeAccount = null): array
    {
        return $this->request('GET', '/v1/payment_methods', [
            'customer' => $stripeCustomerId,
            'type' => $type,
        ], null, $stripeAccount);
    }

    public function createCustomerBasic(string $name, string $email, ?string $stripeAccount = null): array
    {
        return $this->request('POST','/v1/customers',[ 'name' => $name, 'email' => $email ], null, $stripeAccount);
    }

    public function listInvoices(string $stripeCustomerId, int $createdGte = 0, int $limit = 100, ?string $stripeAccount = null): array
    {
        $params = ['customer' => $stripeCustomerId, 'limit' => $limit];
        if ($createdGte > 0) { $params['created[gte]'] = $createdGte; }
        return $this->request('GET','/v1/invoices', $params, null, $stripeAccount);
    }

    public function listCharges(string $stripeCustomerId, int $createdGte = 0, int $limit = 100, ?string $stripeAccount = null): array
    {
        $params = ['customer' => $stripeCustomerId, 'limit' => $limit];
        if ($createdGte > 0) { $params['created[gte]'] = $createdGte; }
        return $this->request('GET','/v1/charges', $params, null, $stripeAccount);
    }

    public function listInvoicesForAccount(string $stripeAccount, array $params = []): array
    {
        if (!isset($params['limit'])) { $params['limit'] = 100; }
        return $this->request('GET', '/v1/invoices', $params, null, $stripeAccount);
    }

    public function listChargesForAccount(string $stripeAccount, array $params = []): array
    {
        if (!isset($params['limit'])) { $params['limit'] = 100; }
        return $this->request('GET', '/v1/charges', $params, null, $stripeAccount);
    }

    public function retrieveSubscription(string $subscriptionId, ?string $stripeAccount = null): array
    {
        return $this->request('GET','/v1/subscriptions/'.$subscriptionId, [], null, $stripeAccount);
    }

    public function cancelSubscription(string $subscriptionId, ?string $stripeAccount = null): array
    {
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            throw new \RuntimeException('Missing Stripe subscription id');
        }
        return $this->request('DELETE', '/v1/subscriptions/'.$subscriptionId, [], null, $stripeAccount);
    }

    public function updateSubscription(string $subscriptionId, array $params, ?string $stripeAccount = null): array
    {
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            throw new \RuntimeException('Missing Stripe subscription id');
        }
        return $this->request('POST', '/v1/subscriptions/'.$subscriptionId, $params, null, $stripeAccount);
    }

    public function pauseSubscription(string $subscriptionId, ?string $stripeAccount = null): array
    {
        return $this->updateSubscription($subscriptionId, [
            'pause_collection[behavior]' => 'void',
        ], $stripeAccount);
    }

    public function resumeSubscription(string $subscriptionId, ?string $stripeAccount = null): array
    {
        return $this->updateSubscription($subscriptionId, [
            'pause_collection' => '',
        ], $stripeAccount);
    }

    public function previewUpcomingInvoice(array $params, ?string $stripeAccount = null): array
    {
        return $this->request('GET', '/v1/invoices/upcoming', $params, null, $stripeAccount);
    }

    public function createUsageRecord(string $subscriptionItemId, int $quantity, int $timestamp, ?string $stripeAccount = null, ?string $idempotencyKey = null): array
    {
        $headers = $idempotencyKey ? ['Idempotency-Key: '.$idempotencyKey] : null;
        return $this->request('POST','/v1/subscription_items/'.$subscriptionItemId.'/usage_records',[
            'quantity' => $quantity,
            'timestamp' => $timestamp,
            'action' => 'set',
        ], null, $stripeAccount, $headers);
    }

    public function createPaymentIntentOneTime(string $mspAccountId, array $params): array
    {
        // Expect: amount, currency, customer (optional), payment_method (or capture via client)
        // Include application_fee_amount if platform fee applies
        return $this->request('POST','/v1/payment_intents', $params, null, $mspAccountId);
    }

    public function createRefund(string $paymentIntentId, ?int $amount = null, ?string $stripeAccount = null): array
    {
        $paymentIntentId = trim($paymentIntentId);
        if ($paymentIntentId === '') {
            throw new \RuntimeException('Missing payment intent id');
        }
        $params = ['payment_intent' => $paymentIntentId];
        if ($amount !== null && $amount > 0) {
            $params['amount'] = $amount;
        }
        return $this->request('POST', '/v1/refunds', $params, null, $stripeAccount);
    }

    public function getBalance(?string $stripeAccount = null): array
    {
        return $this->request('GET','/v1/balance', [], null, $stripeAccount);
    }

    public function listBalanceTransactions(?string $stripeAccount = null, array $params = []): array
    {
        return $this->request('GET','/v1/balance_transactions', $params, null, $stripeAccount);
    }

    public function listPayouts(?string $stripeAccount = null, array $params = []): array
    {
        return $this->request('GET','/v1/payouts', $params, null, $stripeAccount);
    }

    public function listDisputes(?string $stripeAccount = null, array $params = []): array
    {
        return $this->request('GET','/v1/disputes', $params, null, $stripeAccount);
    }

    public function updateConnectedAccountProfileById(string $accountId, array $fields): array
    {
        // Accepts keys like business_profile[statement_descriptor], business_profile[support_url]
        return $this->request('POST','/v1/accounts/'.$accountId, $fields);
    }

    public function updateConnectedAccountProfile(int $mspId, array $profileFields): bool
    {
        try {
            $acct = (string)(Capsule::table('eb_msp_accounts')->where('id',$mspId)->value('stripe_connect_id') ?? '');
            if ($acct === '') { return false; }
            $params = [];
            if (isset($profileFields['statement_descriptor']) && $profileFields['statement_descriptor'] !== '') {
                $params['business_profile[statement_descriptor]'] = (string)$profileFields['statement_descriptor'];
            }
            if (isset($profileFields['support_url']) && $profileFields['support_url'] !== '') {
                $params['business_profile[support_url]'] = (string)$profileFields['support_url'];
            }
            if (empty($params)) { return true; }
            $this->updateConnectedAccountProfileById($acct, $params);
            return true;
        } catch (\Throwable $__) { return false; }
    }

    public function updateInvoiceSettings(int $mspId, array $fields): bool
    {
        try {
            $acct = (string)(Capsule::table('eb_msp_accounts')->where('id',$mspId)->value('stripe_connect_id') ?? '');
            if ($acct === '') { return false; }
            // Best-effort: try multiple accepted paths; ignore 4xx errors
            $params = [];
            if (isset($fields['footer']) && $fields['footer'] !== '') {
                $params['settings[invoice][footer]'] = (string)$fields['footer'];
                // Some accounts may accept this legacy alias
                $params['invoice_settings[footer]'] = (string)$fields['footer'];
            }
            if (array_key_exists('days_until_due', $fields) && $fields['days_until_due'] !== null) {
                $params['settings[invoice][days_until_due]'] = (int)$fields['days_until_due'];
            }
            if (empty($params)) { return true; }
            $this->request('POST','/v1/accounts/'.$acct, $params);
            return true;
        } catch (\Throwable $__) { return false; }
    }

    // Stripe Tax Registrations (Connect)
    public function listTaxRegistrations(string $accountId): array
    {
        try { return $this->request('GET','/v1/tax/registrations', [], null, $accountId); }
        catch (\Throwable $__) { return ['data'=>[]]; }
    }

    public function createTaxRegistration(string $accountId, array $params): array
    {
        // Minimal required fields differ by country; caller supplies params
        return $this->request('POST','/v1/tax/registrations', $params, null, $accountId);
    }

    public function deleteTaxRegistration(string $accountId, string $registrationId): bool
    {
        try { $this->request('DELETE','/v1/tax/registrations/'.$registrationId, [], null, $accountId); return true; }
        catch (\Throwable $__) { return false; }
    }
}


