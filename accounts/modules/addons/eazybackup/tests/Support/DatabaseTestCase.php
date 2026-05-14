<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Support;

use PHPUnit\Framework\TestCase;
use WHMCS\Database\Capsule;

/**
 * Base test case for tests that touch the WHMCS database.
 *
 * Wraps every test in a transaction that is always rolled back, so even when
 * a service-under-test does its own commit-less writes the database stays
 * clean. tearDown() asserts the transaction is still open as a tripwire for
 * accidental commits that would leak data into the dev DB.
 */
abstract class DatabaseTestCase extends TestCase
{
    /**
     * Pseudo-random msp id used by tests that don't care which one they get.
     * Picked outside the realistic range so it can never collide with real data.
     */
    protected int $testMspId;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = Capsule::connection();
        $connection->beginTransaction();

        // Stable-but-unique per test run; tests that need a deterministic id can override.
        $this->testMspId = random_int(900_000_000, 999_999_999);
    }

    protected function tearDown(): void
    {
        $connection = Capsule::connection();

        // Tripwire: anything that commits mid-test would leave us at level 0.
        $level = $connection->transactionLevel();
        if ($level < 1) {
            // Defensive: if there's still an outer transaction, roll it back so we don't poison
            // subsequent tests, then fail loudly.
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            parent::tearDown();
            $this->fail('DatabaseTestCase: a test committed the outer transaction. Wrap state-changing calls so they live inside the test transaction.');
            return;
        }

        // Roll back exactly the test transaction we opened (and any savepoints above it).
        while ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    protected function capsule(): Capsule
    {
        return new Capsule();
    }

    /**
     * Returns the underlying \PDO for the active connection. Useful when a service
     * being tested catches exceptions internally and we want to assert raw row counts.
     */
    protected function pdo(): \PDO
    {
        return Capsule::connection()->getPdo();
    }
}
