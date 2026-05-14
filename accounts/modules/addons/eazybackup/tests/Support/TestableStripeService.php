<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Support;

use PartnerHub\StripeService;

/**
 * Test seam for PartnerHub\StripeService.
 *
 * Overrides the (now `protected`) request() so unit tests can:
 *   - assert the exact verb, path, params, and Stripe-Account header
 *     that the public method tried to send;
 *   - return a canned response without touching the network.
 *
 * Tests can either:
 *   - push a single response that applies to every request (default),
 *   - push a queue of responses to be returned in order, OR
 *   - register per-path responders for finer control.
 */
class TestableStripeService extends StripeService
{
    /** @var RecordedRequest[] */
    public array $calls = [];

    /** @var array<int,array> Queue of canned responses; popped FIFO. */
    private array $queue = [];

    /** @var array Default response when the queue is empty. */
    private array $defaultResponse = ['id' => 'fixture_default'];

    /** @var \Throwable|null Optional throwable to raise on the next call. */
    private ?\Throwable $throwOnNext = null;

    public function queueResponse(array $response): void
    {
        $this->queue[] = $response;
    }

    public function setDefaultResponse(array $response): void
    {
        $this->defaultResponse = $response;
    }

    public function throwOnNext(\Throwable $e): void
    {
        $this->throwOnNext = $e;
    }

    public function lastCall(): ?RecordedRequest
    {
        $count = count($this->calls);
        return $count > 0 ? $this->calls[$count - 1] : null;
    }

    protected function request(string $method, string $path, array $params = [], ?string $apiKey = null, ?string $stripeAccount = null, ?array $extraHeaders = null): array
    {
        $this->calls[] = new RecordedRequest($method, $path, $params, $apiKey, $stripeAccount, $extraHeaders);

        if ($this->throwOnNext !== null) {
            $err = $this->throwOnNext;
            $this->throwOnNext = null;
            throw $err;
        }

        if ($this->queue !== []) {
            return array_shift($this->queue);
        }

        return $this->defaultResponse;
    }
}
