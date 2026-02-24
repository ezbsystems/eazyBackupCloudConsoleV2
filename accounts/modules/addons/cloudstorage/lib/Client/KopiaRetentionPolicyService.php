<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

/**
 * Comet-style retention policy validator and resolver for Kopia vaults.
 * Validates hourly/daily/weekly/monthly/yearly keys and resolves effective
 * policy (job override vs vault default) by lifecycle state.
 */
class KopiaRetentionPolicyService
{
    /** @var string[] Comet-tier retention keys */
    private const RETENTION_KEYS = ['hourly', 'daily', 'weekly', 'monthly', 'yearly'];

    /** @var int Upper bound per key (reasonable retention ceiling) */
    private const MAX_PER_KEY = 999999;

    /**
     * Validate a retention policy. Comet-tier keys must be integer >= 0 with reasonable upper bound.
     *
     * @param array $policy Policy array (may have hourly, daily, weekly, monthly, yearly)
     * @return array{0: bool, 1: string[]} [valid, errors]
     */
    public static function validate(array $policy): array
    {
        $errors = [];

        foreach (self::RETENTION_KEYS as $key) {
            if (!array_key_exists($key, $policy)) {
                continue;
            }
            $val = $policy[$key];
            if (!is_int($val) && !(is_string($val) && ctype_digit($val))) {
                $errors[] = "{$key} must be an integer >= 0";
                continue;
            }
            $intVal = (int) $val;
            if ($intVal < 0) {
                $errors[] = "{$key} must be >= 0";
                continue;
            }
            if ($intVal > self::MAX_PER_KEY) {
                $errors[] = "{$key} exceeds maximum " . self::MAX_PER_KEY;
            }
        }

        return [empty($errors), $errors];
    }

    /**
     * Resolve effective policy from job override and vault default by lifecycle state.
     * Active source: use job override when present, otherwise vault default.
     * Retired source: always fall back to vault default (override ignored).
     *
     * @param array|null $sourceOverride Job retention override (null or [] treated as none)
     * @param array $vaultDefault Vault default policy (required)
     * @param string $lifecycleState 'active' or 'retired'
     * @return array Effective policy (normalized with all keys)
     */
    public static function resolveEffectivePolicy(?array $sourceOverride, array $vaultDefault, string $lifecycleState): array
    {
        $state = strtolower(trim($lifecycleState));
        $hasOverride = $sourceOverride !== null
            && $sourceOverride !== []
            && self::hasAnyRetentionValue($sourceOverride);

        if ($state === 'active' && $hasOverride) {
            return self::normalizePolicy($sourceOverride);
        }

        return self::normalizePolicy($vaultDefault);
    }

    private static function hasAnyRetentionValue(array $policy): bool
    {
        foreach (self::RETENTION_KEYS as $key) {
            if (isset($policy[$key]) && (int) $policy[$key] > 0) {
                return true;
            }
        }
        return false;
    }

    private static function normalizePolicy(array $policy): array
    {
        $out = [];
        foreach (self::RETENTION_KEYS as $key) {
            $out[$key] = isset($policy[$key]) ? max(0, (int) $policy[$key]) : 0;
        }
        return $out;
    }
}
