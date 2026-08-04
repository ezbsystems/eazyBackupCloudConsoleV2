<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

final class CanonicalUsage
{
    private static ?bool $hasCanonicalPull = null;

    public static function query(): object
    {
        $query = Capsule::table('cb_credit_usage');
        if (self::hasCanonicalPull()) {
            $query->where('is_present_in_latest_pull', 1);
        }
        return $query;
    }

    public static function hasCanonicalPull(): bool
    {
        if (self::$hasCanonicalPull !== null) {
            return self::$hasCanonicalPull;
        }
        if (!Capsule::schema()->hasTable('cb_portal_pull_manifests')
            || !Capsule::schema()->hasColumn('cb_credit_usage', 'is_present_in_latest_pull')) {
            return self::$hasCanonicalPull = false;
        }
        return self::$hasCanonicalPull = Capsule::table('cb_portal_pull_manifests')->first() !== null;
    }

    public static function clearCache(): void
    {
        self::$hasCanonicalPull = null;
    }
}
