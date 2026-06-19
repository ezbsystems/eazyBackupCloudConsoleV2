<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Maps MS365 wizard retention tiers to Comet-style Kopia retention keys.
 */
final class Ms365RetentionTierPolicyService
{
    /** @var array<string, array{hourly: int, daily: int, weekly: int, monthly: int, yearly: int}> */
    private const TIER_MAP = [
        '1y' => ['hourly' => 0, 'daily' => 30, 'weekly' => 52, 'monthly' => 0, 'yearly' => 0],
        '2y' => ['hourly' => 0, 'daily' => 30, 'weekly' => 104, 'monthly' => 0, 'yearly' => 0],
        '3y' => ['hourly' => 0, 'daily' => 30, 'weekly' => 156, 'monthly' => 0, 'yearly' => 0],
        '4y' => ['hourly' => 0, 'daily' => 30, 'weekly' => 208, 'monthly' => 0, 'yearly' => 0],
        '5y' => ['hourly' => 0, 'daily' => 30, 'weekly' => 260, 'monthly' => 0, 'yearly' => 0],
        '6y' => ['hourly' => 0, 'daily' => 30, 'weekly' => 312, 'monthly' => 0, 'yearly' => 0],
        '7y' => ['hourly' => 0, 'daily' => 30, 'weekly' => 364, 'monthly' => 0, 'yearly' => 0],
    ];

    public const DEFAULT_TIER = '1y';

    /** @return list<string> */
    public static function validTiers(): array
    {
        return array_keys(self::TIER_MAP);
    }

    public static function isValidTier(string $tier): bool
    {
        return isset(self::TIER_MAP[strtolower(trim($tier))]);
    }

    /**
     * @return array{hourly: int, daily: int, weekly: int, monthly: int, yearly: int}
     */
    public static function tierToCometMap(string $tier): array
    {
        $key = strtolower(trim($tier));
        if (!isset(self::TIER_MAP[$key])) {
            $key = self::DEFAULT_TIER;
        }

        return self::TIER_MAP[$key];
    }

    /**
     * Nested policy document for s3_kopia_policy_versions.
     *
     * @return array{schema: int, timezone: string, retention: array<string, int>}
     */
    public static function tierToPolicyDocument(string $tier): array
    {
        return [
            'schema' => 1,
            'timezone' => 'UTC',
            'retention' => self::tierToCometMap($tier),
        ];
    }

    /**
     * @param array<string, mixed>|null $retentionJson
     */
    public static function tierFromRetentionJson(?array $retentionJson): string
    {
        if ($retentionJson === null || $retentionJson === []) {
            return self::DEFAULT_TIER;
        }
        if (isset($retentionJson['tier']) && is_string($retentionJson['tier'])) {
            $tier = strtolower(trim($retentionJson['tier']));
            if (self::isValidTier($tier)) {
                return $tier;
            }
        }

        return self::DEFAULT_TIER;
    }

    /**
     * @return array{hourly: int, daily: int, weekly: int, monthly: int, yearly: int, tier: string}
     */
    public static function retentionJsonForTier(string $tier): array
    {
        $tier = self::isValidTier($tier) ? strtolower(trim($tier)) : self::DEFAULT_TIER;

        return array_merge(self::tierToCometMap($tier), ['tier' => $tier]);
    }
}
