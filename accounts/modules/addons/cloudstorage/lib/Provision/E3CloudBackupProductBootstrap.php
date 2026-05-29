<?php

namespace WHMCS\Module\Addon\CloudStorage\Provision;

use WHMCS\Database\Capsule;

/**
 * Auto-provisions the WHMCS "e3 Cloud Backup" product + its config option group +
 * the five usage-metered options (endpoint, disk_image, hyperv_vm, proxmox_vm,
 * vmware_vm) + base tblpricing rows. Idempotent: safe to call on every activate
 * and upgrade.
 *
 * The product is intentionally created with a $0 recurring price. All billable
 * value is line-itemised through its config options.
 */
class E3CloudBackupProductBootstrap
{
    /**
     * Stable metric -> default friendly name + default unit price (CAD/month).
     */
    private const METRICS = [
        'endpoint'   => ['name' => 'Endpoint',         'default_price' => 4.50],
        'disk_image' => ['name' => 'Disk Image',       'default_price' => 4.50],
        'hyperv_vm'  => ['name' => 'Hyper-V Guest VM', 'default_price' => 4.50],
        'proxmox_vm' => ['name' => 'Proxmox Guest VM', 'default_price' => 4.50],
        'vmware_vm'  => ['name' => 'VMware Guest VM',  'default_price' => 4.50],
    ];

    public const PRODUCT_NAME = 'e3 Cloud Backup';
    public const GROUP_NAME = 'e3';
    public const CONFIG_GROUP_NAME = 'e3 Cloud Backup Metrics';

    /**
     * Run the full create-if-missing flow. Returns a small report array used by
     * the activation / upgrade entry points.
     */
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
                $report['errors'][] = 'Could not resolve or create e3 Cloud Backup product.';
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
                $cfg = self::resolveOrCreateOption($configGroupId, $metricKey, $meta, $currencyId, $report);
                if ($cfg['configid'] > 0) {
                    $optionIds[$metricKey] = $cfg['configid'];
                }
            }

            self::saveSetting('e3cb_config_option_ids', json_encode($optionIds, JSON_UNESCAPED_SLASHES));
            self::saveSetting('pid_e3_cloud_backup', (string) $pid);

            self::log($context, 'bootstrap_ok', $report);
        } catch (\Throwable $e) {
            $report['errors'][] = $e->getMessage();
            self::log($context, 'bootstrap_exception', $e->getMessage());
        }

        return $report;
    }

    /**
     * Return the addon setting for the configured e3 Cloud Backup PID, or 0 if
     * none. This is the canonical reader used by the rest of the codebase.
     */
    public static function getPid(): int
    {
        return (int) self::getSetting('pid_e3_cloud_backup', 0);
    }

    /**
     * Return the metric -> configid map persisted by ensure().
     *
     * @return array<string,int>
     */
    public static function getConfigOptionMap(): array
    {
        $raw = (string) self::getSetting('e3cb_config_option_ids', '');
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

    /**
     * Return the list of supported metric keys.
     *
     * @return string[]
     */
    public static function metricKeys(): array
    {
        return array_keys(self::METRICS);
    }

    /**
     * Friendly display name for a metric (used in invoice line descriptions).
     */
    public static function metricFriendlyName(string $metric): string
    {
        return self::METRICS[$metric]['name'] ?? ucfirst(str_replace('_', ' ', $metric));
    }

    /**
     * Default per-unit price (CAD / month) for a metric. Used only when
     * tblpricing has no row.
     */
    public static function metricDefaultPrice(string $metric): float
    {
        return (float) (self::METRICS[$metric]['default_price'] ?? 0.0);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private static function resolveOrCreateProduct(array &$report): int
    {
        $configuredPid = (int) self::getSetting('pid_e3_cloud_backup', 0);
        if ($configuredPid > 0) {
            $exists = Capsule::table('tblproducts')->where('id', $configuredPid)->exists();
            if ($exists) {
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

        // Build the row dynamically so we tolerate column drift across WHMCS
        // versions (e.g. some installs have 'affiliateonetime' vs 'affiliate_onetime').
        $row = [
            'type'              => 'hostingaccount',
            'gid'               => $groupId,
            'name'              => self::PRODUCT_NAME,
            'description'       => 'e3 Cloud Backup compute (endpoints, disk image, virtual machines). Recurring fee is zero; billable usage is line-itemised via config options.',
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
        // Filter to only columns that actually exist in this install.
        try {
            $existingCols = [];
            $colRows = Capsule::select('SHOW COLUMNS FROM `tblproducts`');
            foreach ($colRows as $cr) {
                $existingCols[(string) $cr->Field] = true;
            }
            $row = array_intersect_key($row, $existingCols);
        } catch (\Throwable $e) {
            // If SHOW COLUMNS fails, attempt the insert as-is.
        }
        $newPid = (int) Capsule::table('tblproducts')->insertGetId($row);

        if ($newPid > 0) {
            $report['product_created'] = true;
            // Create $0.00 monthly + annually pricing row so WHMCS recognises this
            // product as a valid recurring product.
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
                        'type'     => 'product',
                        'currency' => $currencyId,
                        'relid'    => $newPid,
                        'msetupfee'     => 0,
                        'qsetupfee'     => 0,
                        'ssetupfee'     => 0,
                        'asetupfee'     => 0,
                        'bsetupfee'     => 0,
                        'tsetupfee'     => 0,
                        'monthly'       => 0.00,
                        'quarterly'     => -1.00,
                        'semiannually'  => -1.00,
                        'annually'      => 0.00,
                        'biennially'    => -1.00,
                        'triennially'   => -1.00,
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
                'name'    => $name,
                'tagline' => '',
                'order'   => 0,
                'hidden'  => 0,
            ]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function resolveOrCreateConfigGroup(int $pid, array &$report): int
    {
        $existing = Capsule::table('tblproductconfiggroups')
            ->where('name', self::CONFIG_GROUP_NAME)
            ->orderBy('id', 'asc')
            ->first();
        $groupId = 0;
        if ($existing && isset($existing->id)) {
            $groupId = (int) $existing->id;
        } else {
            try {
                $groupId = (int) Capsule::table('tblproductconfiggroups')->insertGetId([
                    'name'        => self::CONFIG_GROUP_NAME,
                    'description' => 'Usage-metered options for e3 Cloud Backup. Quantities are maintained automatically by the billing rater cron.',
                ]);
            } catch (\Throwable $e) {
                $report['errors'][] = 'config_group_insert_fail: ' . $e->getMessage();
                return 0;
            }
        }

        // Link group <-> product (tblproductconfiglinks) so this option group
        // appears on this product.
        try {
            $linked = Capsule::table('tblproductconfiglinks')
                ->where('gid', $groupId)
                ->where('pid', $pid)
                ->exists();
            if (!$linked) {
                Capsule::table('tblproductconfiglinks')->insert([
                    'gid' => $groupId,
                    'pid' => $pid,
                ]);
            }
        } catch (\Throwable $e) {
            $report['errors'][] = 'config_group_link_fail: ' . $e->getMessage();
        }

        return $groupId;
    }

    /**
     * Ensure a single metric config option + its sub-option + its tblpricing
     * row exist. Returns the configid (or 0 on failure).
     *
     * @return array{configid:int, optionid:int}
     */
    private static function resolveOrCreateOption(int $configGroupId, string $metricKey, array $meta, int $currencyId, array &$report): array
    {
        $optionName = (string) $meta['name'];
        $defaultPrice = (float) $meta['default_price'];

        // Find existing option by gid + name match.
        $existing = Capsule::table('tblproductconfigoptions')
            ->where('gid', $configGroupId)
            ->where('optionname', $optionName)
            ->orderBy('id', 'asc')
            ->first();

        $configId = $existing && isset($existing->id) ? (int) $existing->id : 0;

        if ($configId <= 0) {
            try {
                // optiontype 4 = Quantity (used by Comet eazybackup model).
                $configId = (int) Capsule::table('tblproductconfigoptions')->insertGetId([
                    'gid'         => $configGroupId,
                    'optionname'  => $optionName,
                    'optiontype'  => 4,
                    'qtyminimum'  => 0,
                    'qtymaximum'  => 0,
                    'order'       => 0,
                    'hidden'      => 0,
                ]);
                if ($configId > 0) {
                    $report['options_created'][] = $metricKey;
                }
            } catch (\Throwable $e) {
                $report['errors'][] = "config_option_insert_fail({$metricKey}): " . $e->getMessage();
                return ['configid' => 0, 'optionid' => 0];
            }
        }

        // Ensure a single sub-option exists for this configid (the "per unit"
        // sub-option). WHMCS uses sub-option ID as the tblpricing.relid.
        $optionId = (int) Capsule::table('tblproductconfigoptionssub')
            ->where('configid', $configId)
            ->orderBy('sortorder', 'asc')
            ->orderBy('id', 'asc')
            ->value('id');

        if ($optionId <= 0) {
            try {
                $optionId = (int) Capsule::table('tblproductconfigoptionssub')->insertGetId([
                    'configid'   => $configId,
                    'optionname' => $optionName,
                    'sortorder'  => 0,
                    'hidden'     => 0,
                ]);
            } catch (\Throwable $e) {
                $report['errors'][] = "config_option_sub_insert_fail({$metricKey}): " . $e->getMessage();
            }
        }

        // Ensure a tblpricing row exists for this sub-option for the configured currency.
        if ($optionId > 0) {
            try {
                $hasPricing = Capsule::table('tblpricing')
                    ->where('type', 'configoptions')
                    ->where('currency', $currencyId)
                    ->where('relid', $optionId)
                    ->exists();
                if (!$hasPricing) {
                    Capsule::table('tblpricing')->insert([
                        'type'         => 'configoptions',
                        'currency'     => $currencyId,
                        'relid'        => $optionId,
                        'msetupfee'    => 0,
                        'qsetupfee'    => 0,
                        'ssetupfee'    => 0,
                        'asetupfee'    => 0,
                        'bsetupfee'    => 0,
                        'tsetupfee'    => 0,
                        'monthly'      => $defaultPrice,
                        'quarterly'    => -1.00,
                        'semiannually' => -1.00,
                        'annually'     => round($defaultPrice * 12, 2),
                        'biennially'   => -1.00,
                        'triennially'  => -1.00,
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
        } catch (\Throwable $e) {
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
                    'module'  => 'cloudstorage',
                    'setting' => $key,
                    'value'   => $value,
                ]);
            }
        } catch (\Throwable $e) {
            // non-fatal
        }
    }

    private static function log(string $context, string $event, $payload): void
    {
        try {
            logModuleCall('cloudstorage', $event, ['context' => $context], $payload, [], []);
        } catch (\Throwable $_) {
        }
    }
}
