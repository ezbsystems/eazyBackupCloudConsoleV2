<?php
declare(strict_types=1);

/**
 * Regression guard for DeployService drain vs force semantics.
 * Mirrors updateInstructionForNode needsDrain logic.
 */
function deployOfferNeedsDrain(bool $baselineOnly, int $load, string $strategy, bool $force): bool
{
    return !$baselineOnly
        && $load > 0
        && $strategy === 'rolling'
        && !$force;
}

assert(deployOfferNeedsDrain(false, 3, 'rolling', false) === true);
assert(deployOfferNeedsDrain(false, 3, 'rolling', true) === false);
assert(deployOfferNeedsDrain(false, 3, 'force', true) === false);
assert(deployOfferNeedsDrain(false, 3, 'force', false) === false);
assert(deployOfferNeedsDrain(false, 0, 'rolling', false) === false);
assert(deployOfferNeedsDrain(true, 3, 'rolling', false) === false);

echo "deploy_drain_test: ok\n";
