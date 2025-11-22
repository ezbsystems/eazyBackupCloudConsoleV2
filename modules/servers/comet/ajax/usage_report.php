<?php

use \WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . "/../functions.php";
require_once __DIR__ . "/../summary_functions.php";

if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
	header('HTTP/1.1 403 Forbidden');
	echo json_encode(['error' => 'Not authorized']);
	exit;
}

$clientId = (int) $_SESSION['uid'];
$currencyData = getCurrency($clientId);
$currencyId = (int) ($currencyData['id'] ?? 1);

// === Config Option IDs (single source of truth) ===
const COID_EAZYBACKUP_SERVER_ENDPOINT = 89;
const COID_CLOUD_STORAGE              = 67;
const COID_DEVICE_ENDPOINT            = 88;
const COID_DISK_IMAGE                 = 91; // updated per latest mapping
const COID_HYPERV_GUEST_VM            = 97;
const COID_VMWARE_GUEST_VM            = 99;
const COID_MICROSOFT_365_ACCOUNTS     = 60;
const COID_PROXMOX_GUEST_VM           = 102;

/** Get purchased quantity for a service/config option. */
function getConfigQty(int $serviceId, int $configId): int {
	$row = Capsule::table('tblhostingconfigoptions')
		->select(['qty'])
		->where('relid', $serviceId)
		->where('configid', $configId)
		->first();
	return (int)($row->qty ?? 0);
}

/** Get the single unit sub-option id for a Quantity-style option (e.g., TB @). */
function getUnitSubOptionId(int $configId): ?int {
	$val = Capsule::table('tblproductconfigoptionssub')
		->where('configid', $configId)
		->orderBy('sortorder')->orderBy('id')
		->value('id');
	return $val ? (int)$val : null;
}

/** Get the selected sub-option id for Dropdown/Radio options on a given service. */
function getSelectedSubOptionId(int $serviceId, int $configId): ?int {
	$id = Capsule::table('tblhostingconfigoptions')
		->where('relid', $serviceId)
		->where('configid', $configId)
		->value('optionid');
	return $id ? (int)$id : null;
}

/** Map WHMCS billingcycle to tblpricing column name */
function mapBillingCycleToPricingField(?string $billingCycle): string {
	$map = [
		'Monthly' => 'monthly',
		'Quarterly' => 'quarterly',
		'Semi-Annually' => 'semiannually',
		'Semiannually' => 'semiannually',
		'Annually' => 'annually',
		'Biennially' => 'biennially',
		'Triennially' => 'triennially',
		// Common aliases
		'Bi-Annually' => 'biennially',
		'Biannually' => 'biennially',
	];
	return $map[$billingCycle] ?? 'monthly';
}

/** Resolve the per-unit price from tblpricing for a config option. */
function getConfigUnitPrice(int $serviceId, int $configId, int $currencyId, string $cycle = 'monthly'): float {
	$selectedSubId = getSelectedSubOptionId($serviceId, $configId);
	$unitSubId = getUnitSubOptionId($configId);
	$relid = $selectedSubId ?? $unitSubId ?? $configId;
	$row = Capsule::table('tblpricing')
		->where('type', 'configoptions')
		->where('currency', $currencyId)
		->where('relid', $relid)
		->first();
	return $row ? (float)($row->{$cycle} ?? 0.0) : 0.0;
}

header('Content-Type: application/json');

	$services = Capsule::table('tblhosting')
	->select('id','username','packageid','amount','billingcycle','nextduedate')
	->where('userid', $clientId)
	->get();

$rows = [];

foreach ($services as $svc) {
	$serviceId = (int)$svc->id;
	$username = (string)$svc->username;
	$packageId = (int)$svc->packageid;
	$recurringAmount = (float)($svc->amount ?? 0);
	$plan = (string)($svc->billingcycle ?? '');
	$pricingCycle = mapBillingCycleToPricingField($plan);
	$nextDueDate = (string)($svc->nextduedate ?? '');

	$params = comet_ProductParams($packageId);
	$params['username'] = $username;

	$user = comet_User($params);
	if (is_string($user)) {
		// Skip if user not found or API error
		continue;
	}

	// Storage usage (human): sum Comet vault sizes for types 1000 and 1003
	$storageHuman = getUserStorage($username);

	// Purchased qty / unit / total
	$storageQty = getConfigQty($serviceId, COID_CLOUD_STORAGE);
	$storageUnit = getConfigUnitPrice($serviceId, COID_CLOUD_STORAGE, $currencyId, $pricingCycle);
	$storageTotal = $storageQty * $storageUnit;

	// Device usage: number of devices; 0 if device plan not purchased
	$devicePurchased = getConfigQty($serviceId, COID_DEVICE_ENDPOINT);
	$deviceUsage = $devicePurchased > 0 ? (isset($user->Devices) ? count((array)$user->Devices) : 0) : 0;
	$deviceUnit = getConfigUnitPrice($serviceId, COID_DEVICE_ENDPOINT, $currencyId, $pricingCycle);
	$deviceTotal = $devicePurchased * $deviceUnit;

	// Server endpoints usage: show usage only if purchased
	$serverUsageCount = 0;
	if (!empty($user->Devices) && is_array($user->Devices)) {
		$serverPatterns = ['windows server','windows small business server','windows web server'];
		foreach ($user->Devices as $deviceConfig) {
			if (!empty($deviceConfig->PlatformVersion->Distribution)) {
				$dist = strtolower($deviceConfig->PlatformVersion->Distribution);
				foreach ($serverPatterns as $pattern) {
					if (strpos($dist, $pattern) !== false) {
						$serverUsageCount++;
						break;
					}
				}
			}
		}
	}
    $serverPurchased = getConfigQty($serviceId, COID_EAZYBACKUP_SERVER_ENDPOINT);
    $serverUsage = $serverPurchased > 0 ? $serverUsageCount : 0;
    $serverUnit  = getConfigUnitPrice($serviceId, COID_EAZYBACKUP_SERVER_ENDPOINT, $currencyId, $pricingCycle);
    $serverTotal = $serverPurchased * $serverUnit;

	// Disk Image usage: unique OwnerDevice for engine1/windisk
	$diskImagePurchased = getConfigQty($serviceId, COID_DISK_IMAGE);
	$diskImageUsage = 0;
	if (!empty($user->Sources)) {
		$uniqueOwnerDevices = [];
		foreach ($user->Sources as $src) {
			if (!empty($src->Engine) && $src->Engine === 'engine1/windisk') {
				$ownerDevice = $src->OwnerDevice ?? null;
				if ($ownerDevice && !in_array($ownerDevice, $uniqueOwnerDevices, true)) {
					$uniqueOwnerDevices[] = $ownerDevice;
				}
			}
		}
		$diskImageUsage = count($uniqueOwnerDevices);
	}
	$diskImageUsage = $diskImagePurchased > 0 ? $diskImageUsage : 0;
	$diskImageUnit = getConfigUnitPrice($serviceId, COID_DISK_IMAGE, $currencyId, $pricingCycle);
	$diskImageTotal = $diskImagePurchased * $diskImageUnit;

	// VM engines
	$vmCounts = comet_getVmCountsByEngineFromUser($user);
	$hypervUsage = (int)($vmCounts['hyperv'] ?? 0);
	$vmwareUsage = (int)($vmCounts['vmware'] ?? 0);
	$proxmoxUsage = (int)($vmCounts['proxmox'] ?? 0);

	$hypervPurchased = getConfigQty($serviceId, COID_HYPERV_GUEST_VM);
	$vmwarePurchased = getConfigQty($serviceId, COID_VMWARE_GUEST_VM);
	$proxmoxPurchased = getConfigQty($serviceId, COID_PROXMOX_GUEST_VM);

	$hypervUsage = $hypervPurchased > 0 ? $hypervUsage : 0;
	$vmwareUsage = $vmwarePurchased > 0 ? $vmwareUsage : 0;
	$proxmoxUsage = $proxmoxPurchased > 0 ? $proxmoxUsage : 0;

	$hypervUnit = getConfigUnitPrice($serviceId, COID_HYPERV_GUEST_VM, $currencyId, $pricingCycle);
	$vmwareUnit = getConfigUnitPrice($serviceId, COID_VMWARE_GUEST_VM, $currencyId, $pricingCycle);
	$proxmoxUnit = getConfigUnitPrice($serviceId, COID_PROXMOX_GUEST_VM, $currencyId, $pricingCycle);

	$hypervTotal = $hypervPurchased * $hypervUnit;
	$vmwareTotal = $vmwarePurchased * $vmwareUnit;
	$proxmoxTotal = $proxmoxPurchased * $proxmoxUnit;

	// M365
	$m365Usage = 0;
	try {
		$m365Usage = (int) (MicrosoftAccountCount($user) ?? 0);
	} catch (\Throwable $e) { $m365Usage = 0; }
	$m365Purchased = getConfigQty($serviceId, COID_MICROSOFT_365_ACCOUNTS);
	$m365Usage = $m365Purchased > 0 ? $m365Usage : 0;
	$m365Unit = getConfigUnitPrice($serviceId, COID_MICROSOFT_365_ACCOUNTS, $currencyId, $pricingCycle);
	$m365Total = $m365Purchased * $m365Unit;

    $rows[] = [
        $username,
        $storageHuman, $storageQty, number_format($storageUnit, 2, '.', ''), number_format($storageTotal, 2, '.', ''),
        $deviceUsage, $devicePurchased, number_format($deviceUnit, 2, '.', ''), number_format($deviceTotal, 2, '.', ''),
        $serverUsage, $serverPurchased, number_format($serverUnit, 2, '.', ''), number_format($serverTotal, 2, '.', ''),
        $diskImageUsage, $diskImagePurchased, number_format($diskImageUnit, 2, '.', ''), number_format($diskImageTotal, 2, '.', ''),
        $hypervUsage, $hypervPurchased, number_format($hypervUnit, 2, '.', ''), number_format($hypervTotal, 2, '.', ''),
        $vmwareUsage, $vmwarePurchased, number_format($vmwareUnit, 2, '.', ''), number_format($vmwareTotal, 2, '.', ''),
        $m365Usage, $m365Purchased, number_format($m365Unit, 2, '.', ''), number_format($m365Total, 2, '.', ''),
        $proxmoxUsage, $proxmoxPurchased, number_format($proxmoxUnit, 2, '.', ''), number_format($proxmoxTotal, 2, '.', ''),
        number_format($recurringAmount, 2, '.', ''),
        $plan,
        $nextDueDate ? date('Y-m-d', strtotime($nextDueDate)) : ''
    ];
}

echo json_encode($rows);
exit;


