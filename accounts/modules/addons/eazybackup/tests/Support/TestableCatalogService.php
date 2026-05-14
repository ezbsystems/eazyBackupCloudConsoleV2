<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Support;

use PartnerHub\CatalogService;

/**
 * Test seam for PartnerHub\CatalogService.
 *
 * Same pattern as TestableStripeService — captures calls to the (now
 * `protected`) request() and returns canned data so tests can assert the
 * exact wire shape without hitting Stripe.
 */
class TestableCatalogService extends CatalogService
{
    /** @var RecordedRequest[] */
    public array $calls = [];

    /** @var array<int,array> */
    private array $queue = [];

    /** @var array */
    private array $defaultResponse = ['id' => 'fixture_default'];

    public function queueResponse(array $response): void
    {
        $this->queue[] = $response;
    }

    public function setDefaultResponse(array $response): void
    {
        $this->defaultResponse = $response;
    }

    public function lastCall(): ?RecordedRequest
    {
        $count = count($this->calls);
        return $count > 0 ? $this->calls[$count - 1] : null;
    }

    protected function request(string $method, string $path, array $params = [], ?string $stripeAccount = null, ?array $extraHeaders = null): array
    {
        // CatalogService::request has a different signature than StripeService::request
        // (no $apiKey arg). Record into the same value object for ergonomic assertions.
        $this->calls[] = new RecordedRequest($method, $path, $params, null, $stripeAccount, $extraHeaders);

        if ($this->queue !== []) {
            return array_shift($this->queue);
        }

        return $this->defaultResponse;
    }
}
