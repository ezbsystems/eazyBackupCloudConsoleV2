<?php
namespace CometBilling;

/**
 * Shared charge/service categorization for portal usage and active services.
 */
class ChargeCategoryResolver
{
  public const CATEGORIES = [
        'devices' => 'Devices',
        'hyperv_vms' => 'Hyper-V VMs',
        'vmware_vms' => 'VMware VMs',
        'proxmox_vms' => 'Proxmox VMs',
        'disk_image' => 'Disk Image',
        'mssql' => 'MS SQL Server',
        'm365_accounts' => 'M365 Accounts',
        'account_plan' => 'Account Plan',
        'other' => 'Other',
    ];

    public static function fromUsageRow(string $itemType, ?string $itemDesc): string
    {
        return self::fromText($itemType, $itemDesc);
    }

    public static function fromServiceName(string $serviceName): string
    {
        return self::fromText('', $serviceName);
    }

    public static function fromText(string $itemType, ?string $text): string
    {
        $desc = strtolower((string) $text);
        $type = strtolower($itemType);

        if ($type === 'device' || str_contains($desc, 'device -') || preg_match('/device\s+[a-f0-9]{6}/i', $desc)) {
            return 'devices';
        }
        if (str_contains($desc, 'hyper-v') || str_contains($desc, 'hyperv')) {
            return 'hyperv_vms';
        }
        if (str_contains($desc, 'vmware')) {
            return 'vmware_vms';
        }
        if (str_contains($desc, 'proxmox')) {
            return 'proxmox_vms';
        }
        if (str_contains($desc, 'disk image')) {
            return 'disk_image';
        }
        if (str_contains($desc, 'sql server') || str_contains($desc, 'mssql')) {
            return 'mssql';
        }
        if (str_contains($desc, 'office 365') || str_contains($desc, 'm365') || str_contains($desc, 'microsoft 365')) {
            return 'm365_accounts';
        }
        if (str_contains($desc, 'advanced plan') || ($type === 'plan' && str_contains($desc, 'plan'))) {
            return 'account_plan';
        }

        return 'other';
    }

    public static function label(string $category): string
    {
        return self::CATEGORIES[$category] ?? $category;
    }
}
