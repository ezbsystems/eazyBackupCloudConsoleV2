<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Support;

/**
 * Value object capturing a single intercepted Stripe-API call from one of the
 * Testable* service doubles. Lets tests assert the exact verb, path, params,
 * and connected-account header that was about to be sent to Stripe.
 */
final class RecordedRequest
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $params,
        public readonly ?string $apiKey,
        public readonly ?string $stripeAccount,
        public readonly ?array $extraHeaders
    ) {
    }
}
