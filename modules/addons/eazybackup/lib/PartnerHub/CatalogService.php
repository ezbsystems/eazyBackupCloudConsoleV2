<?php

namespace PartnerHub;

use WHMCS\Database\Capsule;

class CatalogService
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

    private function request(string $method, string $path, array $params = [], ?string $stripeAccount = null, ?array $extraHeaders = null): array
    {
        $apiKey = $this->getSetting('stripe_platform_secret');
        $url = rtrim($this->apiBase,'/').$path;
        $ch = curl_init();
        $headers = [ 'Authorization: Bearer '.$apiKey ];
        if ($stripeAccount) { $headers[] = 'Stripe-Account: '.$stripeAccount; }
        if (is_array($extraHeaders)) {
            foreach ($extraHeaders as $h) { if (is_string($h) && $h !== '') { $headers[] = $h; } }
        }
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];
        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        } elseif (strtoupper($method) === 'GET' && !empty($params)) {
            $opts[CURLOPT_URL] = $url.'?'.http_build_query($params);
        } else if (strtoupper($method) === 'DELETE') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }
        curl_setopt_array($ch, $opts);
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) { throw new \RuntimeException('Stripe request failed: '.$err); }
        $data = json_decode($res, true);
        if (!is_array($data)) { throw new \RuntimeException('Stripe response parse error (HTTP '.$code.'): '.$res); }
        if ($code >= 400) {
            $msg = (string)($data['error']['message'] ?? 'Stripe error');
            throw new \RuntimeException('Stripe error (HTTP '.$code.'): '.$msg);
        }
        return $data;
    }

    public function createProduct(string $name, ?string $description, string $stripeAccount, ?string $idempotencyKey = null): array
    {
        $params = ['name' => $name];
        if ($description) { $params['description'] = $description; }
        $headers = $idempotencyKey ? ['Idempotency-Key: '.$idempotencyKey] : null;
        return $this->request('POST','/v1/products', $params, $stripeAccount, $headers);
    }

    public function updateProduct(string $productId, array $fields, string $stripeAccount): array
    {
        $params = [];
        if (isset($fields['name'])) { $params['name'] = (string)$fields['name']; }
        if (array_key_exists('description', $fields)) { $params['description'] = ($fields['description'] === null ? '' : (string)$fields['description']); }
        if (isset($fields['active'])) { $params['active'] = $fields['active'] ? 'true' : 'false'; }
        return $this->request('POST','/v1/products/'.rawurlencode($productId), $params, $stripeAccount);
    }

    public function deleteProduct(string $productId, string $stripeAccount): array
    {
        return $this->request('DELETE','/v1/products/'.rawurlencode($productId), [], $stripeAccount);
    }

    public function createPrice(array $args, string $stripeAccount, ?string $idempotencyKey = null): array
    {
        $headers = $idempotencyKey ? ['Idempotency-Key: '.$idempotencyKey] : null;
        return $this->request('POST','/v1/prices', $args, $stripeAccount, $headers);
    }

    public function listProducts(string $stripeAccount, int $limit = 100): array
    {
        $limit = max(1, min(100, (int)$limit));
        return $this->request('GET','/v1/products', [ 'limit' => $limit ], $stripeAccount);
    }

    public function retrieveProduct(string $productId, string $stripeAccount): array
    {
        return $this->request('GET','/v1/products/'.rawurlencode($productId), [], $stripeAccount);
    }

    public function listPrices(string $productId, string $stripeAccount, int $limit = 100, ?bool $active = null): array
    {
        $params = [ 'product' => $productId, 'limit' => max(1, min(100, (int)$limit)) ];
        if ($active !== null) { $params['active'] = $active ? 'true' : 'false'; }
        return $this->request('GET','/v1/prices', $params, $stripeAccount);
    }

    public function updatePrice(string $priceId, array $fields, string $stripeAccount): array
    {
        $params = [];
        if (isset($fields['active'])) { $params['active'] = $fields['active'] ? 'true' : 'false'; }
        if (isset($fields['nickname'])) { $params['nickname'] = (string)$fields['nickname']; }
        return $this->request('POST','/v1/prices/'.rawurlencode($priceId), $params, $stripeAccount);
    }

    public function createSubscriptionMulti(string $customerId, array $items, string $stripeAccount, ?float $applicationFeePercent = null, ?int $trialDays = null): array
    {
        $params = [
            'customer' => $customerId,
            'collection_method' => 'charge_automatically',
            'proration_behavior' => 'create_prorations',
            'automatic_tax[enabled]' => 'true',
        ];
        if ($trialDays !== null && $trialDays > 0) { $params['trial_period_days'] = $trialDays; }
        if ($applicationFeePercent !== null && $applicationFeePercent > 0) { $params['application_fee_percent'] = $applicationFeePercent; }
        // Flatten items into indexed params
        $i = 0;
        foreach ($items as $it) {
            $params['items['.$i.'][price]'] = (string)$it['price'];
            if (isset($it['quantity']) && $it['quantity'] !== null) {
                $params['items['.$i.'][quantity]'] = (int)$it['quantity'];
            }
            $i++;
        }
        return $this->request('POST','/v1/subscriptions', $params, $stripeAccount);
    }

    public function createUsageRecordConnected(string $subscriptionItemId, int $quantity, int $timestamp, string $stripeAccount): array
    {
        return $this->request('POST','/v1/subscription_items/'.$subscriptionItemId.'/usage_records', [
            'quantity' => $quantity,
            'timestamp' => $timestamp,
            'action' => 'set',
        ], $stripeAccount);
    }

    public function updateSubscriptionItemQuantity(string $subscriptionItemId, int $quantity, string $stripeAccount, string $prorationBehavior = 'create_prorations'): array
    {
        return $this->request('POST','/v1/subscription_items/'.$subscriptionItemId, [
            'quantity' => $quantity,
            'proration_behavior' => $prorationBehavior,
        ], $stripeAccount);
    }
}


