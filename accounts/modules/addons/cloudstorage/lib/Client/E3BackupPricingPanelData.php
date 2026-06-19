<?php
declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupPricing;
use WHMCS\Module\Addon\CloudStorage\Provision\E3CloudBackupProductBootstrap;

/**
 * Read-only pricing snapshot for customer-facing e3 + MS365 panels.
 */
final class E3BackupPricingPanelData
{
    /** @return array<string, mixed> */
    public static function forClient(int $clientId): array
    {
        $currencyId = 1;
        try {
            if ($clientId > 0) {
                $cur = (int) Capsule::table('tblclients')->where('id', $clientId)->value('currency');
                if ($cur > 0) {
                    $currencyId = $cur;
                }
            }
        } catch (\Throwable $_) {
        }

        $e3Lines = [];
        foreach (E3CloudBackupPricing::METRICS as $metric) {
            $resolved = E3CloudBackupPricing::resolve($clientId, $metric, $currencyId, 1);
            $e3Lines[] = [
                'metric' => $metric,
                'label' => E3CloudBackupProductBootstrap::metricFriendlyName($metric),
                'unit_price' => round((float) ($resolved['unit_price'] ?? 0.0), 2),
                'unit' => 'per month',
            ];
        }

        $ms365 = [
            'protected_user_price' => 0.0,
            'onedrive_included_gib' => 1024,
            'onedrive_overage_per_gib' => 0.0,
            'currency' => 'CAD',
        ];
        $autoload = dirname(__DIR__, 3) . '/ms365backup/ms365backup_autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
            if (class_exists('\\Ms365Backup\\Ms365BillingConfig')) {
                $ms365 = [
                    'protected_user_price' => round(\Ms365Backup\Ms365BillingConfig::protectedUserPriceCad(), 2),
                    'onedrive_included_gib' => \Ms365Backup\Ms365BillingConfig::onedriveIncludedGib(),
                    'onedrive_overage_per_gib' => round(\Ms365Backup\Ms365BillingConfig::onedriveOveragePricePerGibCad(), 2),
                    'currency' => 'CAD',
                ];
            }
        }

        return [
            'e3_cloud_backup' => [
                'title' => 'e3 Cloud Backup',
                'lines' => $e3Lines,
                'note' => 'Usage is metered monthly. Billable quantity is the peak count seen during each billing period.',
            ],
            'ms365_backup' => [
                'title' => 'Microsoft 365 Backup',
                'protected_user_price' => $ms365['protected_user_price'],
                'onedrive_included_gib' => $ms365['onedrive_included_gib'],
                'onedrive_overage_per_gib' => $ms365['onedrive_overage_per_gib'],
                'currency' => $ms365['currency'],
                'note' => 'One MS365 Backup product is added per backup user when you create your first Microsoft 365 job. The WHMCS service username matches this backup user so MSPs can reconcile billing.',
            ],
        ];
    }
}
