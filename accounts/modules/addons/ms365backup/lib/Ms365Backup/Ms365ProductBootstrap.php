<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Auto-provisions the WHMCS MS365 Backup product + metered config options.
 */
final class Ms365ProductBootstrap
{
    public const PRODUCT_NAME = 'eazyBackup Microsoft 365 Backup';
    public const GROUP_NAME = 'e3';
    public const CONFIG_GROUP_NAME = 'MS365 Backup Metrics';
    /** Production WHMCS product id when pre-provisioned. */
    public const PREFERRED_PRODUCTION_PID = 107;

    /** @var array<string, array{name: string}> */
    private const METRICS = [
        Ms365BillingConfig::METRIC_PROTECTED_USERS => ['name' => 'Protected Users'],
        Ms365BillingConfig::METRIC_ONEDRIVE_OVERAGE_GIB => ['name' => 'OneDrive Overage (GiB)'],
    ];

    /** @return array<string, mixed> */
    public static function ensure(string $context = 'activate'): array
    {
        $report = [
            'product_pid' => 0,
            'product_created' => false,
            'group_id' => 0,
            'options_created' => [],
            'errors' => [],
        ];

        try {
            $pid = self::resolveOrCreateProduct($report);
            if ($pid <= 0) {
                $report['errors'][] = 'Could not resolve or create MS365 Backup product.';
                self::log($context, 'bootstrap_no_product', $report);

                return $report;
            }
            $report['product_pid'] = $pid;

            $configGroupId = self::resolveOrCreateConfigGroup($pid, $report);
            if ($configGroupId <= 0) {
                $report['errors'][] = 'Could not resolve or create config option group.';
                self::log($context, 'bootstrap_no_group', $report);

                return $report;
            }
            $report['group_id'] = $configGroupId;

            $currencyId = 1;
            try {
                $currencyId = (int) Capsule::table('tblcurrencies')->orderBy('id', 'asc')->value('id');
            } catch (\Throwable $_) {
            }
            if ($currencyId <= 0) {
                $currencyId = 1;
            }

            $optionIds = [];
            foreach (self::METRICS as $metricKey => $meta) {
                $cfg = self::resolveOrCreateOption($configGroupId, $metricKey, $meta, $currencyId, $report);
                if ($cfg['configid'] > 0) {
                    $optionIds[$metricKey] = $cfg['configid'];
                }
            }

            self::saveSetting('ms365_config_option_ids', json_encode($optionIds, JSON_UNESCAPED_SLASHES));
            self::saveSetting('pid_ms365_backup', (string) $pid);
            self::log($context, 'bootstrap_ok', $report);
        } catch (\Throwable $e) {
            $report['errors'][] = $e->getMessage();
            self::log($context, 'bootstrap_exception', $e->getMessage());
        }

        return $report;
    }

    private static function resolveOrCreateProduct(array &$report): int
    {
        $preferredPid = self::PREFERRED_PRODUCTION_PID;
        if ($preferredPid > 0) {
            $preferred = Capsule::table('tblproducts')->where('id', $preferredPid)->first();
            if ($preferred && (string) ($preferred->name ?? '') === self::PRODUCT_NAME) {
                return $preferredPid;
            }
        }

        $configuredPid = Ms365BillingConfig::getPid();
        if ($configuredPid > 0) {
            if (Capsule::table('tblproducts')->where('id', $configuredPid)->exists()) {
                return $configuredPid;
            }
        }

        $existingByName = Capsule::table('tblproducts')
            ->where('name', self::PRODUCT_NAME)
            ->orderBy('id', 'asc')
            ->first();
        if ($existingByName && isset($existingByName->id)) {
            return (int) $existingByName->id;
        }

        $groupId = self::resolveOrCreateProductGroup(self::GROUP_NAME);
        if ($groupId <= 0) {
            $report['errors'][] = 'Failed to create product group';

            return 0;
        }

        $row = [
            'type' => 'hostingaccount',
            'gid' => $groupId,
            'name' => self::PRODUCT_NAME,
            'description' => 'Microsoft 365 Backup — recurring fee is zero; billable usage is line-itemised via config options (Protected Users, OneDrive overage).',
            'hidden' => 0,
            'showdomainoptions' => 0,
            'welcomeemail' => 0,
            'stockcontrol' => 0,
            'qty' => 0,
            'proratabilling' => 0,
            'paytype' => 'recurring',
            'allowqty' => 0,
            'autosetup' => 'order',
            'tax' => 1,
            'order' => 0,
            'retired' => 0,
        ];
        try {
            $existingCols = [];
            foreach (Capsule::select('SHOW COLUMNS FROM `tblproducts`') as $cr) {
                $existingCols[(string) $cr->Field] = true;
            }
            $row = array_intersect_key($row, $existingCols);
        } catch (\Throwable $_) {
        }

        $newPid = (int) Capsule::table('tblproducts')->insertGetId($row);
        if ($newPid > 0) {
            $report['product_created'] = true;
            try {
                $exists = Capsule::table('tblpricing')
                    ->where('type', 'product')
                    ->where('currency', 1)
                    ->where('relid', $newPid)
                    ->exists();
                if (!$exists) {
                    Capsule::table('tblpricing')->insert([
                        'type' => 'product',
                        'currency' => 1,
                        'relid' => $newPid,
                        'msetupfee' => 0,
                        'qsetupfee' => 0,
                        'ssetupfee' => 0,
                        'asetupfee' => 0,
                        'bsetupfee' => 0,
                        'tsetupfee' => 0,
                        'monthly' => 0.00,
                        'quarterly' => -1.00,
                        'semiannually' => -1.00,
                        'annually' => 0.00,
                        'biennially' => -1.00,
                        'triennially' => -1.00,
                    ]);
                }
            } catch (\Throwable $e) {
                $report['errors'][] = 'product_pricing_insert_fail: ' . $e->getMessage();
            }
        }

        return $newPid;
    }

    private static function resolveOrCreateProductGroup(string $name): int
    {
        $existing = Capsule::table('tblproductgroups')->where('name', $name)->first();
        if ($existing && isset($existing->id)) {
            return (int) $existing->id;
        }

        try {
            return (int) Capsule::table('tblproductgroups')->insertGetId([
                'name' => $name,
                'tagline' => '',
                'order' => 0,
                'hidden' => 0,
            ]);
        } catch (\Throwable $_) {
            return 0;
        }
    }

    private static function resolveOrCreateConfigGroup(int $pid, array &$report): int
    {
        $existing = Capsule::table('tblproductconfiggroups')
            ->where('name', self::CONFIG_GROUP_NAME)
            ->orderBy('id', 'asc')
            ->first();
        $groupId = $existing ? (int) $existing->id : 0;
        if ($groupId <= 0) {
            try {
                $groupId = (int) Capsule::table('tblproductconfiggroups')->insertGetId([
                    'name' => self::CONFIG_GROUP_NAME,
                    'description' => 'Usage-metered options for MS365 Backup. Quantities are maintained automatically by the billing cron.',
                ]);
            } catch (\Throwable $e) {
                $report['errors'][] = 'config_group_insert_fail: ' . $e->getMessage();

                return 0;
            }
        }

        try {
            $linked = Capsule::table('tblproductconfiglinks')
                ->where('gid', $groupId)
                ->where('pid', $pid)
                ->exists();
            if (!$linked) {
                Capsule::table('tblproductconfiglinks')->insert(['gid' => $groupId, 'pid' => $pid]);
            }
        } catch (\Throwable $e) {
            $report['errors'][] = 'config_group_link_fail: ' . $e->getMessage();
        }

        return $groupId;
    }

    /**
     * @param array{name: string} $meta
     * @return array{configid: int, optionid: int}
     */
    private static function resolveOrCreateOption(int $groupId, string $metricKey, array $meta, int $currencyId, array &$report): array
    {
        $optionName = $meta['name'];
        $config = Capsule::table('tblproductconfigoptions')
            ->where('gid', $groupId)
            ->where('optionname', $optionName)
            ->first();
        $configId = $config ? (int) $config->id : 0;
        if ($configId <= 0) {
            try {
                $configId = (int) Capsule::table('tblproductconfigoptions')->insertGetId([
                    'gid' => $groupId,
                    'optionname' => $optionName,
                    'optiontype' => 4,
                    'qtyminimum' => 0,
                    'qtymaximum' => 0,
                    'order' => 0,
                    'hidden' => 0,
                ]);
                $report['options_created'][] = $metricKey;
            } catch (\Throwable $e) {
                $report['errors'][] = 'config_option_insert_fail_' . $metricKey . ': ' . $e->getMessage();

                return ['configid' => 0, 'optionid' => 0];
            }
        }

        $sub = Capsule::table('tblproductconfigoptionssub')
            ->where('configid', $configId)
            ->orderBy('sortorder', 'asc')
            ->orderBy('id', 'asc')
            ->first();
        $subId = $sub ? (int) $sub->id : 0;
        if ($subId <= 0) {
            try {
                $subId = (int) Capsule::table('tblproductconfigoptionssub')->insertGetId([
                    'configid' => $configId,
                    'optionname' => $optionName,
                    'sortorder' => 0,
                    'hidden' => 0,
                ]);
            } catch (\Throwable $e) {
                $report['errors'][] = 'config_sub_insert_fail_' . $metricKey . ': ' . $e->getMessage();

                return ['configid' => $configId, 'optionid' => 0];
            }
        }

        try {
            $priceExists = Capsule::table('tblpricing')
                ->where('type', 'configoptions')
                ->where('currency', $currencyId)
                ->where('relid', $subId)
                ->exists();
            if (!$priceExists) {
                Capsule::table('tblpricing')->insert([
                    'type' => 'configoptions',
                    'currency' => $currencyId,
                    'relid' => $subId,
                    'msetupfee' => 0,
                    'qsetupfee' => 0,
                    'ssetupfee' => 0,
                    'asetupfee' => 0,
                    'bsetupfee' => 0,
                    'tsetupfee' => 0,
                    'monthly' => 0.00,
                    'quarterly' => -1.00,
                    'semiannually' => -1.00,
                    'annually' => 0.00,
                    'biennially' => -1.00,
                    'triennially' => -1.00,
                ]);
            }
        } catch (\Throwable $_) {
        }

        return ['configid' => $configId, 'optionid' => $subId];
    }

    private static function saveSetting(string $key, string $value): void
    {
        try {
            $exists = Capsule::table('tbladdonmodules')
                ->where('module', 'ms365backup')
                ->where('setting', $key)
                ->exists();
            if ($exists) {
                Capsule::table('tbladdonmodules')
                    ->where('module', 'ms365backup')
                    ->where('setting', $key)
                    ->update(['value' => $value]);
            } else {
                Capsule::table('tbladdonmodules')->insert([
                    'module' => 'ms365backup',
                    'setting' => $key,
                    'value' => $value,
                ]);
            }
        } catch (\Throwable $_) {
        }
    }

    /** @param mixed $payload */
    private static function log(string $context, string $action, $payload): void
    {
        try {
            logModuleCall('ms365backup', 'product_bootstrap_' . $action, ['context' => $context], $payload, [], []);
        } catch (\Throwable $_) {
        }
    }
}
