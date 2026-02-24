<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

/**
 * Comet-style retention policy validator and resolver for Kopia vaults.
 * Validates hourly/daily/weekly/monthly/yearly keys and resolves effective
 * policy (job override vs vault default) by lifecycle state.
 *
 * Supports both policy shapes:
 * - flat: {hourly, daily, weekly, monthly, yearly}
 * - nested: {schema, timezone, retention: {hourly, daily, ...}}
 *
 * All-zero override semantics: An override with all retention keys set to 0
 * is treated as "no override" and falls back to the vault default. This applies
 * in active lifecycle; retired sources always use the vault default.
 */
class KopiaRetentionPolicyService
{
    /** @var string[] Comet-tier retention keys */
    private const RETENTION_KEYS = ['hourly', 'daily', 'weekly', 'monthly', 'yearly'];

    /** @var int Upper bound per key (reasonable retention ceiling) */
    private const MAX_PER_KEY = 999999;

    /**
     * Extract retention map from policy. Supports flat and nested shapes.
     *
     * @param array $policy Either flat {hourly,daily,...} or nested {retention:{hourly,daily,...}}
     * @return array Retention map with Comet-tier keys (may have missing keys)
     */
    public static function extractRetentionMap(array $policy): array
    {
        if (isset($policy['retention']) && is_array($policy['retention'])) {
            return $policy['retention'];
        }
        return $policy;
    }

    /**
     * Validate a retention policy. Comet-tier keys must be integer >= 0 with reasonable upper bound.
     * Accepts flat or nested policy shape.
     *
     * @param array $policy Policy array (flat or nested)
     * @return array{0: bool, 1: string[]} [valid, errors]
     */
    public static function validate(array $policy): array
    {
        $map = self::extractRetentionMap($policy);
        $errors = [];

        foreach (self::RETENTION_KEYS as $key) {
            if (!array_key_exists($key, $map)) {
                continue;
            }
            $val = $map[$key];
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
     * Active source: use job override when present (with any key > 0), otherwise vault default.
     * Retired source: always fall back to vault default (override ignored).
     * All-zero override: treated as no override; falls back to vault default.
     * Accepts flat or nested policy shape for both override and vault default.
     *
     * @param array|null $sourceOverride Job retention override (null, [], or all zeros treated as none)
     * @param array $vaultDefault Vault default policy (flat or nested)
     * @param string $lifecycleState 'active' or 'retired'
     * @return array Effective policy (normalized flat map with all keys)
     */
    public static function resolveEffectivePolicy(?array $sourceOverride, array $vaultDefault, string $lifecycleState): array
    {
        $state = strtolower(trim($lifecycleState));
        $overrideMap = $sourceOverride !== null ? self::extractRetentionMap($sourceOverride) : [];
        $vaultMap = self::extractRetentionMap($vaultDefault);
        $hasOverride = $sourceOverride !== null
            && $sourceOverride !== []
            && self::hasAnyRetentionValue($overrideMap);

        if ($state === 'active' && $hasOverride) {
            return self::normalizePolicy($overrideMap);
        }

        return self::normalizePolicy($vaultMap);
    }

    private static function hasAnyRetentionValue(array $retentionMap): bool
    {
        foreach (self::RETENTION_KEYS as $key) {
            if (isset($retentionMap[$key]) && (int) $retentionMap[$key] > 0) {
                return true;
            }
        }
        return false;
    }

    private static function normalizePolicy(array $retentionMap): array
    {
        $out = [];
        foreach (self::RETENTION_KEYS as $key) {
            $out[$key] = isset($retentionMap[$key]) ? max(0, (int) $retentionMap[$key]) : 0;
        }
        return $out;
    }
}
