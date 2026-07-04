<?php

namespace WHMCS\Module\Addon\CloudStorage\Provision;

use WHMCS\Database\Capsule;

/**
 * Auto-provisions the WHMCS "e3 Backup User" product ($0 recurring) with config
 * options for all billable metrics (local agent, MS365, SaaS). Idempotent.
 */
class E3BackupUserProductBootstrap
{
    /** @var array<string, array{name: string, default_price: float}> */
    private const METRICS = [
        'endpoint'             => ['name' => 'Endpoint',              'default_price' => 4.50],
        'disk_image'           => ['name' => 'Disk Image',            'default_price' => 4.50],
        'hyperv_vm'            => ['name' => 'Hyper-V Guest VM',      'default_price' => 4.50],
        'proxmox_vm'           => ['name' => 'Proxmox Guest VM',      'default_price' => 4.50],
        'vmware_vm'            => ['name' => 'VMware Guest VM',       'default_price' => 4.50],
        'protected_users'      => ['name' => 'Protected Users',       'default_price' => 0.00],
        'onedrive_overage_gib' => ['name' => 'OneDrive Overage (GiB)', 'default_price' => 0.00],
        'saas_connector'       => ['name' => 'SaaS Connector',        'default_price' => 0.00],
    ];

    /** Metrics owned by the e3 Cloud Backup billing engine. */
    private const E3CB_METRICS = [
        'endpoint', 'disk_image', 'hyperv_vm', 'proxmox_vm', 'vmware_vm', 'saas_connector',
    ];

    /** Metrics owned by the MS365 billing engine. */
    private const MS365_METRICS = ['protected_users', 'onedrive_overage_gib'];

    public const PRODUCT_NAME = 'e3 Backup User';
    public const GROUP_NAME = 'e3';
    public const CONFIG_GROUP_NAME = 'e3 Backup User Metrics';

    public static function ensure(string $context = 'activate'): array
    {
        $report = [
            'product_pid' => 0,
            'product_created' => false,
            'group_id' => 0,
            'options_created' => [],
            'pricing_rows_created' => [],
            'errors' => [],
        ];

        try {
            $pid = self::resolveOrCreateProduct($report);
            if ($pid <= 0) {
                $report['errors'][] = 'Could not resolve or create e3 Backup User product.';
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

            $currencyId = (int) self::getSetting('e3cb_currency_id', 1);
            if ($currencyId <= 0) {
                $currencyId = 1;
            }

            $optionIds = [];
            foreach (self::METRICS as $metricKey => $meta) {
                $price = self::defaultPriceForMetric($metricKey, (float) $meta['default_price']);
                $cfg = self::resolveOrCreateOption($configGroupId, $metricKey, $meta, $currencyId, $price, $report);
                if ($cfg['configid'] > 0) {
                    $optionIds[$metricKey] = $cfg['configid'];
                }
            }

            self::saveSetting('e3bu_config_option_ids', json_encode($optionIds, JSON_UNESCAPED_SLASHES));
            self::saveSetting('pid_e3_backup_user', (string) $pid);
            self::log($context, 'bootstrap_ok', $report);
        } catch (\Throwable $e) {
            $report['errors'][] = $e->getMessage();
            self::log($context, 'bootstrap_exception', $e->getMessage());
        }

        return $report;
    }

    public static function getPid(): int
    {
        return (int) self::getSetting('pid_e3_backup_user', 0);
    }

    public static function isUnifiedEnabled(): bool
    {
        $raw = strtolower(trim((string) self::getSetting('e3_backup_user_unified_enabled', '')));
        return in_array($raw, ['1', 'on', 'yes', 'true'], true);
    }

    /** @return array<string,int> */
    public static function getConfigOptionMap(): array
    {
        $raw = (string) self::getSetting('e3bu_config_option_ids', '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $clean = [];
        foreach ($decoded as $metric => $cid) {
            if (!is_string($metric)) {
                continue;
            }
            $cid = (int) $cid;
            if ($cid > 0) {
                $clean[$metric] = $cid;
            }
        }
        return $clean;
    }

    /** @return array<string,int> */
    public static function configOptionMapForE3cbBilling(): array
    {
        return self::filterMap(self::getConfigOptionMap(), self::E3CB_METRICS);
    }

    /** @return array<string,int> */
    public static function configOptionMapForMs365Billing(): array
    {
        return self::filterMap(self::getConfigOptionMap(), self::MS365_METRICS);
    }

    public static function isUnifiedService(int $serviceId): bool
    {
        $pid = self::getPid();
        if ($pid <= 0 || $serviceId <= 0) {
            return false;
        }
        try {
            return (int) Capsule::table('tblhosting')->where('id', $serviceId)->value('packageid') === $pid;
        } catch (\Throwable $_) {
            return false;
        }
    }

    /** @return string[] */
    public static function metricKeys(): array
    {
        return array_keys(self::METRICS);
    }

    public static function metricFriendlyName(string $metric): string
    {
        return self::METRICS[$metric]['name'] ?? ucfirst(str_replace('_', ' ', $metric));
    }

    public static function metricDefaultPrice(string $metric): float
    {
        $fallback = (float) (self::METRICS[$metric]['default_price'] ?? 0.0);
        return self::defaultPriceForMetric($metric, $fallback);
    }

  /**
   * Resolve config option map for e3 CB billing (unified service or legacy).
   *
   * @return array<string,int>
   */
    public static function resolveE3cbConfigOptionMap(?int $serviceId = null): array
    {
        if ($serviceId !== null && $serviceId > 0 && self::isUnifiedService($serviceId)) {
            $unified = self::configOptionMapForE3cbBilling();
            if ($unified !== []) {
                return $unified;
            }
        }
        return E3CloudBackupProductBootstrap::getConfigOptionMap();
    }

    /**
     * Resolve config option map for MS365 billing (unified service or legacy).
     *
     * @return array<string,int>
     */
    public static function resolveMs365ConfigOptionMap(?int $serviceId = null): array
    {
        if ($serviceId !== null && $serviceId > 0 && self::isUnifiedService($serviceId)) {
            $unified = self::configOptionMapForMs365Billing();
            if ($unified !== []) {
                return $unified;
            }
        }
        if (class_exists('\\Ms365Backup\\Ms365BillingConfig')) {
            return \Ms365Backup\Ms365BillingConfig::getConfigOptionMap();
        }
        return [];
    }

    // ------------------------------------------------------------------

    private static function defaultPriceForMetric(string $metric, float $fallback): float
    {
        if ($metric === 'protected_users') {
            $ms365 = self::getMs365Setting('protected_user_price_cad', '');
            if ($ms365 !== '' && is_numeric($ms365)) {
                return max(0.0, (float) $ms365);
            }
        }
        if ($metric === 'onedrive_overage_gib') {
            $storage = (string) self::getSetting('storage_overage_per_gib_cad', '');
            if ($storage !== '' && is_numeric($storage) && (float) $storage > 0) {
                return (float) $storage;
            }
        }
        if ($metric === 'saas_connector') {
            return 0.0;
        }
        if (in_array($metric, E3CloudBackupProductBootstrap::metricKeys(), true)) {
            return E3CloudBackupProductBootstrap::metricDefaultPrice($metric);
        }
        return $fallback;
    }

    /** @param list<string> $keys @return array<string,int> */
    private static function filterMap(array $map, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (isset($map[$key]) && (int) $map[$key] > 0) {
                $out[$key] = (int) $map[$key];
            }
        }
        return $out;
    }

    private static function resolveOrCreateProduct(array &$report): int
    {
        $configuredPid = (int) self::getSetting('pid_e3_backup_user', 0);
        if ($configuredPid > 0 && Capsule::table('tblproducts')->where('id', $configuredPid)->exists()) {
            return $configuredPid;
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
            'type'              => 'hostingaccount',
            'gid'               => $groupId,
            'name'              => self::PRODUCT_NAME,
            'description'       => 'e3 Backup User — one WHMCS service per backup user. Recurring fee is zero; all workloads are line-itemised via config options.',
            'hidden'            => 0,
            'showdomainoptions' => 0,
            'welcomeemail'      => 0,
            'stockcontrol'      => 0,
            'qty'               => 0,
            'proratabilling'    => 0,
            'paytype'           => 'recurring',
            'allowqty'          => 0,
            'autosetup'         => 'order',
            'tax'               => 1,
            'order'             => 0,
            'retired'           => 0,
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
            $currencyId = (int) self::getSetting('e3cb_currency_id', 1);
            if ($currencyId <= 0) {
                $currencyId = 1;
            }
            try {
                $exists = Capsule::table('tblpricing')
                    ->where('type', 'product')
                    ->where('currency', $currencyId)
                    ->where('relid', $newPid)
                    ->exists();
                if (!$exists) {
                    Capsule::table('tblpricing')->insert([
                        'type' => 'product', 'currency' => $currencyId, 'relid' => $newPid,
                        'msetupfee' => 0, 'qsetupfee' => 0, 'ssetupfee' => 0,
                        'asetupfee' => 0, 'bsetupfee' => 0, 'tsetupfee' => 0,
                        'monthly' => 0.00, 'quarterly' => -1.00, 'semiannually' => -1.00,
                        'annually' => 0.00, 'biennially' => -1.00, 'triennially' => -1.00,
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
                'name' => $name, 'tagline' => '', 'order' => 0, 'hidden' => 0,
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
        $groupId = $existing && isset($existing->id) ? (int) $existing->id : 0;
        if ($groupId <= 0) {
            try {
                $groupId = (int) Capsule::table('tblproductconfiggroups')->insertGetId([
                    'name' => self::CONFIG_GROUP_NAME,
                    'description' => 'Usage-metered options for e3 Backup User. Quantities are maintained automatically by billing crons.',
                ]);
            } catch (\Throwable $e) {
                $report['errors'][] = 'config_group_insert_fail: ' . $e->getMessage();
                return 0;
            }
        }

        try {
            $linked = Capsule::table('tblproductconfiglinks')
                ->where('gid', $groupId)->where('pid', $pid)->exists();
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
    private static function resolveOrCreateOption(
        int $configGroupId,
        string $metricKey,
        array $meta,
        int $currencyId,
        float $defaultPrice,
        array &$report
    ): array {
        $optionName = (string) $meta['name'];
        $existing = Capsule::table('tblproductconfigoptions')
            ->where('gid', $configGroupId)
            ->where('optionname', $optionName)
            ->orderBy('id', 'asc')
            ->first();
        $configId = $existing && isset($existing->id) ? (int) $existing->id : 0;

        if ($configId <= 0) {
            try {
                $configId = (int) Capsule::table('tblproductconfigoptions')->insertGetId([
                    'gid' => $configGroupId, 'optionname' => $optionName, 'optiontype' => 4,
                    'qtyminimum' => 0, 'qtymaximum' => 0, 'order' => 0, 'hidden' => 0,
                ]);
                if ($configId > 0) {
                    $report['options_created'][] = $metricKey;
                }
            } catch (\Throwable $e) {
                $report['errors'][] = "config_option_insert_fail({$metricKey}): " . $e->getMessage();
                return ['configid' => 0, 'optionid' => 0];
            }
        }

        $optionId = (int) Capsule::table('tblproductconfigoptionssub')
            ->where('configid', $configId)
            ->orderBy('sortorder', 'asc')->orderBy('id', 'asc')
            ->value('id');

        if ($optionId <= 0) {
            try {
                $optionId = (int) Capsule::table('tblproductconfigoptionssub')->insertGetId([
                    'configid' => $configId, 'optionname' => $optionName, 'sortorder' => 0, 'hidden' => 0,
                ]);
            } catch (\Throwable $e) {
                $report['errors'][] = "config_option_sub_insert_fail({$metricKey}): " . $e->getMessage();
            }
        }

        if ($optionId > 0) {
            try {
                $hasPricing = Capsule::table('tblpricing')
                    ->where('type', 'configoptions')
                    ->where('currency', $currencyId)
                    ->where('relid', $optionId)
                    ->exists();
                if (!$hasPricing) {
                    Capsule::table('tblpricing')->insert([
                        'type' => 'configoptions', 'currency' => $currencyId, 'relid' => $optionId,
                        'msetupfee' => 0, 'qsetupfee' => 0, 'ssetupfee' => 0,
                        'asetupfee' => 0, 'bsetupfee' => 0, 'tsetupfee' => 0,
                        'monthly' => $defaultPrice, 'quarterly' => -1.00, 'semiannually' => -1.00,
                        'annually' => round($defaultPrice * 12, 2), 'biennially' => -1.00, 'triennially' => -1.00,
                    ]);
                    $report['pricing_rows_created'][] = $metricKey;
                }
            } catch (\Throwable $e) {
                $report['errors'][] = "tblpricing_insert_fail({$metricKey}): " . $e->getMessage();
            }
        }

        return ['configid' => $configId, 'optionid' => $optionId];
    }

    private static function getSetting(string $key, $default = null)
    {
        try {
            $val = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', $key)
                ->value('value');
            return ($val !== null && $val !== '') ? $val : $default;
        } catch (\Throwable $_) {
            return $default;
        }
    }

    private static function getMs365Setting(string $key, string $default = ''): string
    {
        try {
            $val = Capsule::table('tbladdonmodules')
                ->where('module', 'ms365backup')
                ->where('setting', $key)
                ->value('value');
            return ($val !== null && $val !== '') ? (string) $val : $default;
        } catch (\Throwable $_) {
            return $default;
        }
    }

    private static function saveSetting(string $key, string $value): void
    {
        try {
            $exists = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', $key)
                ->exists();
            if ($exists) {
                Capsule::table('tbladdonmodules')
                    ->where('module', 'cloudstorage')
                    ->where('setting', $key)
                    ->update(['value' => $value]);
            } else {
                Capsule::table('tbladdonmodules')->insert([
                    'module' => 'cloudstorage', 'setting' => $key, 'value' => $value,
                ]);
            }
        } catch (\Throwable $_) {
        }
    }

    /** @param mixed $payload */
    private static function log(string $context, string $event, $payload): void
    {
        try {
            logModuleCall('cloudstorage', $event, ['context' => $context], $payload, [], []);
        } catch (\Throwable $_) {
        }
    }
}
