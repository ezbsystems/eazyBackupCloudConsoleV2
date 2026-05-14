<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Base test case for pure-logic tests with no DB or external dependencies.
 *
 * Use this when the code under test is a pure function (e.g.
 * PartnerHub\computeBillableMeteredUsage) so we keep the test fast and free
 * of WHMCS/Capsule side-effects.
 */
abstract class UnitTestCase extends TestCase
{
}
