<!-- accounts\modules\servers\comet\ajax\ajax.php -->

<?php

use \WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . "/../functions.php";
require_once __DIR__ . "/../summary_functions.php";

// === Config Option IDs (single source of truth) ===
const COID_EAZYBACKUP_SERVER_ENDPOINT = 89;
const COID_CLOUD_STORAGE              = 67;
const COID_DEVICE_ENDPOINT            = 88;
const COID_DISK_IMAGE                 = 91;
const COID_HYPERV_GUEST_VM            = 97;
const COID_VMWARE_GUEST_VM            = 99;
const COID_MICROSOFT_365_ACCOUNTS     = 60;
const COID_PROXMOX_GUEST_VM           = 102;

// Optional: labels for UI only (so display text can change freely)
$CO_LABELS = [
	COID_EAZYBACKUP_SERVER_ENDPOINT => 'Server endpoint',
	COID_CLOUD_STORAGE              => 'Cloud Storage',
	COID_DEVICE_ENDPOINT            => 'Device endpoint',
	COID_DISK_IMAGE                 => 'Disk Image',
	COID_HYPERV_GUEST_VM            => 'Hyper-V Guest VM',
	COID_VMWARE_GUEST_VM            => 'VMware Guest VM',
	COID_MICROSOFT_365_ACCOUNTS     => 'Microsoft 365 Accounts',
	COID_PROXMOX_GUEST_VM           => 'Proxmox Guest VM',
];

// Config options used on this page
$CONFIG_OPTION_IDS = [
	COID_DEVICE_ENDPOINT,
	COID_EAZYBACKUP_SERVER_ENDPOINT,
	COID_DISK_IMAGE,
	COID_HYPERV_GUEST_VM,
	COID_VMWARE_GUEST_VM,
	COID_MICROSOFT_365_ACCOUNTS,
	COID_PROXMOX_GUEST_VM,
	COID_CLOUD_STORAGE,
];

/**
 * Get the selected value for a service/config option by config option ID.
 * Handles common WHMCS option types:
 *  - Quantity (optiontype=3): value from tblhostingconfigoptions.qty
 *  - Yes/No (optiontype=4): 0/1 from tblhostingconfigoptions.optionid
 *  - Dropdown/Radio (optiontype=1/2): suboption id from optionid
 *  - Text/Text Area/Password: value often stored in optionid
 */
function getConfigValueByConfigId(int $serviceId, int $configId) {
	$opt = Capsule::table('tblproductconfigoptions')
		->select(['id','optiontype'])
		->where('id', $configId)->first();

	if (!$opt) return null;

	$row = Capsule::table('tblhostingconfigoptions')
		->where('relid', $serviceId)
		->where('configid', $configId)
		->first();

	if (!$row) return null;

	$type = (int) $opt->optiontype;
	// 1=Dropdown, 2=Radio, 3=Quantity, 4=Yes/No, 5=Text Area (WHMCS may vary by minor version)
	switch ($type) {
		case 3: // Quantity
			return (int)($row->qty ?? 0);
		case 4: // Yes/No
			return (int)($row->optionid ?? 0); // 0/1
		case 1:  // Dropdown
		case 2:  // Radio
			return (int)($row->optionid ?? 0); // suboption id
		default: // Text/TextArea/Password → WHMCS stores value in optionid column as string
			return $row->optionid ?? null;
	}
}

/**
 * Get the per-unit price configured for a given config option ID.
 * For Quantity options: pricing lives on tblpricing.type='configoptions' with relid = configId.
 * For Dropdown/Radio/YesNo: pricing lives on SUB-options; this page uses quantity-type IDs.
 */
function getPerUnitPriceByConfigId(int $configId, int $currencyId): float {
	$opt = Capsule::table('tblproductconfigoptions')
		->select(['id','optiontype'])
		->where('id', $configId)->first();
	if (!$opt) return 0.0;

	$type = (int) $opt->optiontype;

	// Quantity / Yes-No use option's own pricing row
	if ($type === 3 || $type === 4) {
		$pricing = Capsule::table('tblpricing')
			->where('type', 'configoptions')
			->where('currency', $currencyId)
			->where('relid', $configId)
			->first();
		if (!$pricing) return 0.0;
		return (float) $pricing->monthly; // adjust if another billing cycle is needed
	}

	// Dropdown/Radio require suboption-specific pricing; caller should use getPerUnitPriceForSelectedSubOption
	return 0.0;
}

/**
 * Get per-unit price for the selected suboption (dropdown/radio) for this service/config.
 */
function getPerUnitPriceForSelectedSubOption(int $serviceId, int $configId, int $currencyId): float {
	$opt = Capsule::table('tblproductconfigoptions')
		->select(['id','optiontype'])
		->where('id', $configId)->first();
	if (!$opt) return 0.0;
	$type = (int) $opt->optiontype;
	if (!in_array($type, [1,2], true)) {
		return 0.0;
	}
	$hc = Capsule::table('tblhostingconfigoptions')
		->select(['optionid'])
		->where('relid', $serviceId)
		->where('configid', $configId)
		->first();
	$selectedSubId = (int)($hc->optionid ?? 0);
	if ($selectedSubId <= 0) {
		return 0.0;
	}
	$pricing = Capsule::table('tblpricing')
		->where('type', 'configoptions')
		->where('currency', $currencyId)
		->where('relid', $selectedSubId)
		->first();
	if (!$pricing) return 0.0;
	return (float) $pricing->monthly;
}

/**
 * Get purchased quantity for a service/config option.
 * WHMCS stores quantity in tblhostingconfigoptions.qty for Quantity options.
 * For Yes/No/Dropdown you may still see optionid populated; qty will be NULL/0.
 */
function getConfigQty(int $serviceId, int $configId): int {
	$row = Capsule::table('tblhostingconfigoptions')
		->select(['qty'])
		->where('relid', $serviceId)
		->where('configid', $configId)
		->first();
	return (int)($row->qty ?? 0);
}

/** Get the single “unit” sub-option id for a Quantity-style option (e.g., “TB @”). */
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

/**
 * Get the per-unit price for a config option, resolving the correct relid for tblpricing:
 *  - Quantity: relid = unit sub-option id (tblproductconfigoptionssub.id)
 *  - Dropdown/Radio: relid = selected sub-option id (tblproductconfigoptionssub.id)
 *  - Yes/No: relid = option id (tblproductconfigoptions.id)
 * If both selectedSubId and unitSubId are empty, fall back to option id.
 */
function mapBillingCycleToPricingField(?string $billingCycle): string {
    $map = [
        'Monthly' => 'monthly',
        'Quarterly' => 'quarterly',
        'Semi-Annually' => 'semiannually',
        'Semiannually' => 'semiannually',
        'Annually' => 'annually',
        'Biennially' => 'biennially',
        'Triennially' => 'triennially',
        'Bi-Annually' => 'biennially',
        'Biannually' => 'biennially',
    ];
    return $map[$billingCycle] ?? 'monthly';
}

function getConfigUnitPrice(int $configId, int $currencyId, string $cycle = 'monthly', ?int $serviceId = null): float {
	// Prefer an explicitly selected sub-option (Dropdown/Radio)
	$selectedSubId = $serviceId ? getSelectedSubOptionId($serviceId, $configId) : null;

	// Quantity-style “unit” sub-option (e.g., TB @) if present
	$unitSubId = getUnitSubOptionId($configId);

	$relid = $selectedSubId ?? $unitSubId ?? $configId;

	$row = Capsule::table('tblpricing')
		->where('type', 'configoptions')
		->where('currency', $currencyId)
		->where('relid', $relid)
		->first();

	return $row ? (float)($row->{$cycle} ?? 0.0) : 0.0;
}

/**
 * Always return qty from tblhostingconfigoptions for a given service/config ID,
 * regardless of the option type.
 */
function getQuantityForServiceByConfigId(int $serviceId, int $configId): int {
	$row = Capsule::table('tblhostingconfigoptions')
		->select(['qty'])
		->where('relid', $serviceId)
		->where('configid', $configId)
		->first();
	if (!$row) {
		return 0;
	}
	return (int)($row->qty ?? 0);
}

// Sanity check at runtime
foreach ([COID_EAZYBACKUP_SERVER_ENDPOINT, COID_CLOUD_STORAGE, COID_DEVICE_ENDPOINT,
		  COID_DISK_IMAGE, COID_HYPERV_GUEST_VM, COID_VMWARE_GUEST_VM, COID_MICROSOFT_365_ACCOUNTS, COID_PROXMOX_GUEST_VM] as $__cid) {
	if (!Capsule::table('tblproductconfigoptions')->where('id',$__cid)->exists()) {
		error_log("Config Option ID $__cid not found. Check constants in ajax.php");
	}
}

if (!empty($_POST['id'])) {

    $currencyData = getCurrency($_SESSION['uid']);

    $serviceId = (int)$_POST['id'];
    $pid = Capsule::table("tblhosting")->where(["id" => $serviceId])->value('packageid');

    if ($pid) {
        // Resolve full module/server params for this specific service to
        // ensure we hit the correct Comet server (including white‑label tenants).
        $params = comet_ServiceParams($serviceId);

        // Basic diagnostics for server params (no secrets)
        try {
            logModuleCall(
                'comet',
                'services-ajax.serverParams',
                ['serviceId' => $serviceId, 'pid' => (int)$pid],
                null,
                [
                    'serverhttpprefix' => $params['serverhttpprefix'] ?? null,
                    'serverhostname'   => $params['serverhostname'] ?? null,
                    'serverusername'   => $params['serverusername'] ?? null,
                ],
                []
            );
        } catch (\Throwable $__) { /* ignore */ }

        $productName = Capsule::table("tblproducts")->where(["id" => $pid])->value('name');

		// ID-keyed accumulators
		$formattedTotalsById = [];
		$deviceCountsById = [];
        // determine billing cycle for pricing column
        $billingCycle = Capsule::table('tblhosting')->where('id', (int)$serviceId)->value('billingcycle');
        $pricingCycle = mapBillingCycleToPricingField($billingCycle);

        foreach ($CONFIG_OPTION_IDS as $__configId) {
			$__qty = getQuantityForServiceByConfigId((int)$serviceId, (int)$__configId);
            $__unit = getConfigUnitPrice((int)$__configId, (int)$currencyData['id'], $pricingCycle, (int)$serviceId);
			$deviceCountsById[$__configId] = $__qty;
			$formattedTotalsById[$__configId] = formatCurrency($__qty * $__unit, (int)$currencyData['id']);
		}

		// Storage (Cloud Storage) by config ID
        $Storageprice = getConfigUnitPrice(COID_CLOUD_STORAGE, (int)$currencyData['id'], $pricingCycle, (int)$serviceId);
		$storage_quantity = getConfigQty((int)$serviceId, COID_CLOUD_STORAGE);
		$totalStoragePrice = $Storageprice * $storage_quantity;
		$formattedStoragePrice = formatCurrency($totalStoragePrice, (int)$currencyData['id']);
		$storage_quantity_display = $storage_quantity . ' TB';
		$formattedUnitPrice = formatCurrency($Storageprice, (int)$currencyData['id']);

        // Ensure username is set on params from the hosting record
        $username = Capsule::table("tblhosting")->where(["id" => $serviceId])->value('username');
        if ($username) {
            $params['username'] = $username;
        }

        if (!empty($params['username'])) {
            try {
                $user = comet_User($params);
            } catch (\Throwable $e) {
                // Surface a friendly message and log details if we cannot load the user
                try {
                    logModuleCall(
                        'comet',
                        'services-ajax.userError',
                        ['serviceId' => $serviceId, 'username' => $params['username']],
                        null,
                        $e->getMessage(),
                        [$e->getTraceAsString()]
                    );
                } catch (\Throwable $__) {}
                echo '<div class="p-4 bg-red-700 text-white rounded">Unable to load backup details for this service. Please contact support.</div>';
                exit;
            }

            $microsoftUser = [];
            $MicrosoftAccountCount = MicrosoftAccountCount($user);
            $comet_username = $user->Username;
            $comet_AccountName = $user->AccountName;

            $MaximumDevices = $user->MaximumDevices;
            $unlimitedDeviceChecked = 'checked';
            $not_allowed = 'pointer-events-none';
            $mdenable = '';
            $disableDevice = 'disabled';
            if ($MaximumDevices != 0) {
                $unlimitedDeviceChecked = '';
                $not_allowed = '';
                $mdenable = 'enabled';
                $disableDevice = '';
            }

            $emailRecords = $user->Emails;
            $emailBackupReport = $user->SendEmailReports;

            if ($MicrosoftAccountCount == 0) {
                $MicrosoftAccountData = '<tr>
                    <td class="text-center text-slate-400 text-sm" colspan="2">No data available in table</td>
                </tr>';
            } else {
                $MicrosoftAccountData = '<tr>
                    <td class="text-left">' . $MicrosoftAccountCount . '</td>
					<td class="text-right">' . ($formattedTotalsById[COID_MICROSOFT_365_ACCOUNTS] ?? formatCurrency(0, (int)$currencyData['id'])) . '</td>
                </tr>';
            }

            if ($emailBackupReport == 1) {
                $emailReportingChecked = 'checked';
                $emailReportingEnabled = 'true';
            } else {
                $emailReportingChecked = '';
                $emailReportingEnabled = 'false';
            }
            

            $email_data = '
            <table class="w-full table-auto mb-4">
                <thead>
                    <tr class="bg-slate-700 border-b border-slate-700">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-100 tracking-wide uppercase sorting_asc">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-100 tracking-wide uppercase sorting_asc">Actions</th>
                    </tr>
                </thead>
                <tbody>
        ';

        
        
        // Iterate through each email record and create a table row
        foreach ($emailRecords as $emailKey => $emailValue) {
            $escapedEmailValue = htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8');
            $escapedEmailKey = htmlspecialchars($emailKey, ENT_QUOTES, 'UTF-8');
            
            $email_data .= '
            <tr class="text-sm text-slate-200 py-2 px-4 border-b border-slate-700 hover:bg-slate-600">
                <td class="px-4 py-2">
                    <input type="hidden" class="form-input text-sm" 
                           id="email-' . $escapedEmailKey . '" 
                           name="email[]" 
                           value="' . htmlspecialchars($emailValue, ENT_QUOTES, "UTF-8") . '">
                    <span class="email-' . $escapedEmailKey . '">' . htmlspecialchars($emailValue, ENT_QUOTES, "UTF-8") . '</span>
                </td>
                <td class="px-4 py-2">
                    <div class="flex space-x-2">
                        <!-- Remove Email Button -->
                        <button class="btn-edit remove-email email-buttons text-slate-400 px-2 py-1 rounded"
                            title="Remove"
                            @click="$dispatch(\'open-remove-email-modal\', { 
                                serviceId: \'' . $serviceId . '\',
                                email: \'' . $escapedEmailValue . '\'
                            })"
                        >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>

                        </button>
        
                        <!-- Update Email Button -->
                        <button type="button" class="btn-edit update-email text-slate-400 email-buttons px-2 py-1 rounded"
                                @click="$dispatch(\'open-update-email-modal\')" 
                                data-emailid="' . htmlspecialchars($emailKey, ENT_QUOTES, "UTF-8") . '" 
                                data-email="' . htmlspecialchars($emailValue, ENT_QUOTES, "UTF-8") . '">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                </svg>

                        </button>
                    </div>
                </td>
            </tr>
        ';
        }
        
        // Close the table
        $email_data .= '
                </tbody>
            </table>
        ';

            $activeDevices = [];
            try {
                foreach (comet_Server($params)->AdminDispatcherListActive() as $id => $connection) {
                    $activeDevices[$connection->DeviceID] = $id;
                }
            } catch (\Throwable $e) {
                try {
                    logModuleCall(
                        'comet',
                        'services-ajax.listActiveError',
                        ['serviceId' => $serviceId, 'username' => $params['username'] ?? null],
                        null,
                        $e->getMessage(),
                        [$e->getTraceAsString()]
                    );
                } catch (\Throwable $__) {}
                // Continue without active-devices data; rest of the panel is still useful
                $activeDevices = [];
            }

            $destinations = [];
            $StorageTotal = [];
            $vaultType = [
                "0" => "INVALID",
                "1000" => "S3-compatible",
                "1001" => "SFTP",
                "1002" => "Local Path",
                "1003" => "eazyBackup",
                "1004" => "FTP",
                "1005" => "Azure",
                "1006" => "SPANNED",
                "1007" => "OpenStack",
                "1008" => "Backblaze B2",
                "1100" => "latest",
                "1101" => "All",
            ];

            // echo "<pre>DEBUG: About to calculate usage.\n";

            // if (!isset($user)) {
            //     echo "Warning: \$user is not set or is null.\n";
            // } else {
            //     var_dump($user);
            // }

            // if (isset($user->Destinations)) {
            //     echo "\n\$user->Destinations:\n";
            //     var_dump($user->Destinations);
            // } else {
            //     echo "Warning: \$user->Destinations not set.\n";
            // }

            // echo "</pre>";

            
            foreach ($user->Destinations as $id => $destination) {
                if (!empty($destination->CometServer)) {
                    $region = "ca-central-1";
                    $StorageTotal[] = $destination->Statistics->ClientProvidedSize->Size;
                } else {
                    $region = "";
                }
                $destinations[$id] = [
                    "description" => $destination->Description,
                    "bucket_id" => $destination->CometBucket,
                    "type" => $vaultType[$destination->DestinationType],
                    "stored" => comet_HumanFileSize($destination->Statistics->ClientProvidedSize->Size, 2),
                    "Region" => $region,
                    "Amount" => "-",
                    "StorageLimitEnabled" => $destination->StorageLimitEnabled,
                    "StorageLimitBytes" => comet_HumanFileSize($destination->StorageLimitBytes),
                    "vaultStorageId" => $id,
                ];
            }
            $CometStorageTotal = array_sum($StorageTotal);

            if ($destinations) {
                $StotageVault = '';
                foreach ($destinations as $VaultStorageData) {
                    $StotageVault .=  '<tr class="border-b border-slate-700/60 hover:bg-white/5 odd:bg-white/2">
                        <td class="px-4 py-3 text-slate-300">' . htmlspecialchars($VaultStorageData["description"]) . '</td>
                        <td class="px-4 py-3 text-slate-300">' . htmlspecialchars($VaultStorageData["type"]) . '</td>
                        <td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . htmlspecialchars($VaultStorageData["stored"]) . '</td>
                        <td class="px-4 py-3">
                            <a href="/index.php?m=eazybackup&a=user-profile&username=' . htmlspecialchars($comet_username, ENT_QUOTES, 'UTF-8') . '&serviceid=' . htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8') . '" class="text-sky-500 hover:text-sky-400 inline-flex items-center gap-1 px-2 py-1 rounded">Manage</a>
                        </td>
                    </tr>';
                }           
            }
            
            


            // Disk Image count using shared helper; fallback to direct count if needed
            $engineCounts = getUserEngineCounts($user->Username, $params, $user->OrganizationID ?? null);
            $diskImageEngineCount = (int)($engineCounts['engine1/windisk'] ?? 0);
            if ($diskImageEngineCount === 0) {
                // Fallback to direct unique OwnerDevice count for engine1/windisk
            $uniqueOwnerDevices = [];
            if (!empty($user->Sources)) {
                foreach ($user->Sources as $sourceUUID => $sourceConfig) {
                    if (!empty($sourceConfig->Engine) && $sourceConfig->Engine === 'engine1/windisk') {
                            $ownerDevice = $sourceConfig->OwnerDevice ?? null;
                            if ($ownerDevice && !in_array($ownerDevice, $uniqueOwnerDevices, true)) {
                            $uniqueOwnerDevices[] = $ownerDevice;
                            }
                        }
                    }
                }
                $diskImageEngineCount = count($uniqueOwnerDevices);
            }
            
            // Use shared helper to compute VM guest counts by engine
            $vmCounts = comet_getVmCountsByEngineFromUser($user);
            $hypervVmCount = (int)($vmCounts['hyperv'] ?? 0);
            $vmwareVmCount = (int)($vmCounts['vmware'] ?? 0);


            // Define patterns for each category
            $workstationPatterns = [
                'windows 7',
                'windows 10',
                'windows 11',
                'ubuntu',
                'debian',
                'linux',
                'macos',
                'mac os',
                // etc.  
                // You can add 'opensuse-slowroll', etc. here
            ];

            $serverPatterns = [
                'windows server',
                'windows small business server',
                'windows web server',
            ];

            $synologyPatterns = [
                'synology dsm',
            ];

            // Initialize usage counters
            $workstationUsageCount = 0;
            $serverUsageCount      = 0;
            $synologyUsageCount    = 0;

            // Loop through the user->Devices
            if (!empty($user->Devices) && is_array($user->Devices)) {
                foreach ($user->Devices as $deviceUUID => $deviceConfig) {
                    // Always check that PlatformVersion and Distribution are set
                    if (!empty($deviceConfig->PlatformVersion->Distribution)) {
                        // Convert to lowercase for easy substring checks
                        $dist = strtolower($deviceConfig->PlatformVersion->Distribution);

                        // Check if distribution contains server patterns
                        foreach ($serverPatterns as $pattern) {
                            if (strpos($dist, $pattern) !== false) {
                                $serverUsageCount++;
                                continue 2; // Skip to next device (already classified)
                            }
                        }

                        // Check if distribution contains workstation patterns
                        foreach ($workstationPatterns as $pattern) {
                            if (strpos($dist, $pattern) !== false) {
                                $workstationUsageCount++;
                                continue 2; // Skip to next device
                            }
                        }

                        // Check if distribution contains synology patterns
                        foreach ($synologyPatterns as $pattern) {
                            if (strpos($dist, $pattern) !== false) {
                                $synologyUsageCount++;
                                continue 2; // Skip to next device
                            }
                        }

                        // If none match, you could optionally handle “unknown” or “other”
                    }
                }
            }


            // We'll keep track of total usage for each type
            $S3TotalUsage = 0;
            $eazyBackupTotalUsage = 0;

            // Iterate over all destinations
            foreach ($user->Destinations as $id => $destination) {
                if ($destination->DestinationType == 1000) {
                    // S3-compatible
                    $S3TotalUsage += $destination->Statistics->ClientProvidedSize->Size;
                } elseif ($destination->DestinationType == 1003) {
                    // eazyBackup
                    $eazyBackupTotalUsage += $destination->Statistics->ClientProvidedSize->Size;
                }
            }

            // Convert after we sum
            $S3TotalUsageHuman       = comet_HumanFileSize($S3TotalUsage, 2);
            $eazyBackupTotalUsageHuman = comet_HumanFileSize($eazyBackupTotalUsage, 2);
            $combinedTotalUsage        = $S3TotalUsage + $eazyBackupTotalUsage;
            $combinedTotalUsageHuman   = comet_HumanFileSize($combinedTotalUsage, 2);

            $usageTable = '<tr class="border-b border-slate-700/60 hover:bg-white/5 odd:bg-white/2">
                <td class="px-4 py-3 text-slate-300">Storage</td>
                <td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . htmlspecialchars($combinedTotalUsageHuman, ENT_QUOTES, "UTF-8") . '</td>
                <td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . htmlspecialchars($storage_quantity_display, ENT_QUOTES, "UTF-8") . '</td>
                <td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . htmlspecialchars($formattedUnitPrice, ENT_QUOTES, "UTF-8") . '</td>
                <td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . htmlspecialchars($formattedStoragePrice, ENT_QUOTES, "UTF-8") . '</td>
                <td class="px-4 py-3">
                    <a href="/index.php?m=eazybackup&a=user-profile&username=' . htmlspecialchars($comet_username, ENT_QUOTES, 'UTF-8') . '&serviceid=' . htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8') . '" class="text-sky-500 hover:text-sky-400 inline-flex items-center gap-1 px-2 py-1 rounded">Manage</a>
                </td>
            </tr>'; 

			// Device endpoint (ID-based)
			$deviceQty = (int) getQuantityForServiceByConfigId((int)$serviceId, COID_DEVICE_ENDPOINT);
            $deviceUnit = getConfigUnitPrice(COID_DEVICE_ENDPOINT, (int)$currencyData['id'], $pricingCycle, (int)$serviceId);
			$deviceUnitFormatted = formatCurrency($deviceUnit, (int)$currencyData['id']);
			$deviceTotalFormatted = formatCurrency($deviceQty * $deviceUnit, (int)$currencyData['id']);
			$deviceUsageCount = ($deviceQty > 0) ? (int) ($serverUsageCount + $workstationUsageCount + $synologyUsageCount) : 0;
			$usageTable .= '<tr class="border-b border-slate-700/60 hover:bg-white/5 odd:bg-white/2">
				<td class="px-4 py-3 text-slate-300">' . htmlspecialchars($CO_LABELS[COID_DEVICE_ENDPOINT] ?? 'Device endpoint', ENT_QUOTES, "UTF-8") . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . $deviceUsageCount . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . (int)$deviceQty . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . $deviceUnitFormatted . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . $deviceTotalFormatted . '</td>
				<td class="px-4 py-3">
					<a href="/index.php?m=eazybackup&a=user-profile&username=' . htmlspecialchars($comet_username, ENT_QUOTES, 'UTF-8') . '&serviceid=' . htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8') . '" class="text-sky-500 hover:text-sky-400 inline-flex items-center gap-1 px-2 py-1 rounded">Manage</a>
                </td>
            </tr>';

			// Server endpoint (separate from device endpoint)
			$serverEpQty = (int) getQuantityForServiceByConfigId((int)$serviceId, COID_EAZYBACKUP_SERVER_ENDPOINT);
            $serverEpUnit = getConfigUnitPrice(COID_EAZYBACKUP_SERVER_ENDPOINT, (int)$currencyData['id'], $pricingCycle, (int)$serviceId);
			$serverEpUnitFormatted = formatCurrency($serverEpUnit, (int)$currencyData['id']);
			$serverEpTotalFormatted = formatCurrency($serverEpQty * $serverEpUnit, (int)$currencyData['id']);
			$serverEpUsage = ($serverEpQty > 0) ? (int)$serverUsageCount : 0;
			$usageTable .= '<tr class="border-b border-slate-700/60 hover:bg-white/5 odd:bg-white/2">
				<td class="px-4 py-3 text-slate-300">' . htmlspecialchars($CO_LABELS[COID_EAZYBACKUP_SERVER_ENDPOINT] ?? 'Server endpoint', ENT_QUOTES, "UTF-8") . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . $serverEpUsage . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . (int)$serverEpQty . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . $serverEpUnitFormatted . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . $serverEpTotalFormatted . '</td>
				<td class="px-4 py-3">
					<a href="/index.php?m=eazybackup&a=user-profile&username=' . htmlspecialchars($comet_username, ENT_QUOTES, 'UTF-8') . '&serviceid=' . htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8') . '" class="text-sky-500 hover:text-sky-400 inline-flex items-center gap-1 px-2 py-1 rounded">Manage</a>
                </td>
            </tr>';

			// OBC server endpoint removed

			// Disk Image (ID-based with fallback by name for this service if needed)
			$diskImgConfigId = COID_DISK_IMAGE;
			$diskImgQty = (int) getQuantityForServiceByConfigId((int)$serviceId, (int)$diskImgConfigId);
			if ($diskImgQty === 0) {
				$__alt = Capsule::table('tblhostingconfigoptions')
					->join('tblproductconfigoptions', 'tblproductconfigoptions.id', '=', 'tblhostingconfigoptions.configid')
					->select(['tblhostingconfigoptions.qty','tblhostingconfigoptions.configid'])
					->where('tblhostingconfigoptions.relid', (int)$serviceId)
					->where('tblproductconfigoptions.optionname', 'like', '%Disk Image%')
					->first();
				if ($__alt) {
					$diskImgQty = (int) ($__alt->qty ?? 0);
					$diskImgConfigId = (int) ($__alt->configid ?? COID_DISK_IMAGE);
				}
			}
            $diskImgUnit = getConfigUnitPrice((int)$diskImgConfigId, (int)$currencyData['id'], $pricingCycle, (int)$serviceId);
			$diskImgUnitFormatted = formatCurrency($diskImgUnit, (int)$currencyData['id']);
			$diskImgTotalFormatted = formatCurrency($diskImgQty * $diskImgUnit, (int)$currencyData['id']);
			$usageTable .= '<tr class="border-b border-slate-700/60 hover:bg-white/5 odd:bg-white/2">
				<td class="px-4 py-3 text-slate-300">' . htmlspecialchars($CO_LABELS[COID_DISK_IMAGE] ?? 'Disk Image', ENT_QUOTES, "UTF-8") . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . (int)$diskImageEngineCount . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . (int)$diskImgQty . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . $diskImgUnitFormatted . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . $diskImgTotalFormatted . '</td>
				<td class="px-4 py-3">
					<a href="/index.php?m=eazybackup&a=user-profile&username=' . htmlspecialchars($comet_username, ENT_QUOTES, 'UTF-8') . '&serviceid=' . htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8') . '" class="text-sky-500 hover:text-sky-400 inline-flex items-center gap-1 px-2 py-1 rounded">Manage</a>
                </td>
            </tr>';            

			// Hyper-V (ID-based)
			$hypervQty = (int)($deviceCountsById[COID_HYPERV_GUEST_VM] ?? 0);
            $hypervUnit = getConfigUnitPrice(COID_HYPERV_GUEST_VM, (int)$currencyData['id'], $pricingCycle, (int)$serviceId);
			$hypervUnitFormatted = formatCurrency($hypervUnit, (int)$currencyData['id']);
			$usageTable .= '<tr class="border-b border-slate-700/60 hover:bg-white/5 odd:bg-white/2">
				<td class="px-4 py-3 text-slate-300">' . htmlspecialchars($CO_LABELS[COID_HYPERV_GUEST_VM] ?? 'Hyper-V Guest VM', ENT_QUOTES, "UTF-8") . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . (int)$hypervVmCount . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . (int)$hypervQty . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . $hypervUnitFormatted . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . ($formattedTotalsById[COID_HYPERV_GUEST_VM] ?? formatCurrency(0,(int)$currencyData['id'])) . '</td>
				<td class="px-4 py-3">
					<a href="/index.php?m=eazybackup&a=user-profile&username=' . htmlspecialchars($comet_username, ENT_QUOTES, 'UTF-8') . '&serviceid=' . htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8') . '" class="text-sky-500 hover:text-sky-400 inline-flex items-center gap-1 px-2 py-1 rounded">Manage</a>
                </td>
            </tr>';

			// Microsoft 365 Accounts (ID-based)
			$m365Qty = (int)($deviceCountsById[COID_MICROSOFT_365_ACCOUNTS] ?? 0);
            $m365Unit = getConfigUnitPrice(COID_MICROSOFT_365_ACCOUNTS, (int)$currencyData['id'], $pricingCycle, (int)$serviceId);
			$m365UnitFormatted = formatCurrency($m365Unit, (int)$currencyData['id']);
			$usageTable .= '<tr class="border-b border-slate-700/60 hover:bg-white/5 odd:bg-white/2">
				<td class="px-4 py-3 text-slate-300">' . htmlspecialchars($CO_LABELS[COID_MICROSOFT_365_ACCOUNTS] ?? 'Microsoft 365 Accounts', ENT_QUOTES, "UTF-8") . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . htmlspecialchars($MicrosoftAccountCount) . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . (int)$m365Qty . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . $m365UnitFormatted . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . ($formattedTotalsById[COID_MICROSOFT_365_ACCOUNTS] ?? formatCurrency(0,(int)$currencyData['id'])) . '</td>
				<td class="px-4 py-3">
					<a href="/index.php?m=eazybackup&a=user-profile&username=' . htmlspecialchars($comet_username, ENT_QUOTES, 'UTF-8') . '&serviceid=' . htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8') . '" class="text-sky-500 hover:text-sky-400 inline-flex items-center gap-1 px-2 py-1 rounded">Manage</a>
                </td>
            </tr>';            

			// VMware Guest VM (ID-based)
			$vmwareQty = (int)($deviceCountsById[COID_VMWARE_GUEST_VM] ?? 0);
            $vmwareUnit = getConfigUnitPrice(COID_VMWARE_GUEST_VM, (int)$currencyData['id'], $pricingCycle, (int)$serviceId);
			$vmwareUnitFormatted = formatCurrency($vmwareUnit, (int)$currencyData['id']);
			$usageTable .= '<tr class="border-b border-slate-700/60 hover:bg-white/5 odd:bg-white/2">
				<td class="px-4 py-3 text-slate-300">' . htmlspecialchars($CO_LABELS[COID_VMWARE_GUEST_VM] ?? 'VMware Guest VM', ENT_QUOTES, "UTF-8") . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . (int)$vmwareVmCount . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . (int)$vmwareQty . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . $vmwareUnitFormatted . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . ($formattedTotalsById[COID_VMWARE_GUEST_VM] ?? formatCurrency(0,(int)$currencyData['id'])) . '</td>
				<td class="px-4 py-3">
					<a href="/index.php?m=eazybackup&a=user-profile&username=' . htmlspecialchars($comet_username, ENT_QUOTES, 'UTF-8') . '&serviceid=' . htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8') . '" class="text-sky-500 hover:text-sky-400 inline-flex items-center gap-1 px-2 py-1 rounded">Manage</a>
                </td>
            </tr>';           

			// Proxmox Guest VM (ID-based)
			$proxmoxQty = (int)($deviceCountsById[COID_PROXMOX_GUEST_VM] ?? 0);
            $proxmoxUnit = getConfigUnitPrice(COID_PROXMOX_GUEST_VM, (int)$currencyData['id'], $pricingCycle, (int)$serviceId);
			$proxmoxUnitFormatted = formatCurrency($proxmoxUnit, (int)$currencyData['id']);
			$usageTable .= '<tr class="border-b border-slate-700/60 hover:bg-white/5 odd:bg-white/2">
				<td class="px-4 py-3 text-slate-300">' . htmlspecialchars($CO_LABELS[COID_PROXMOX_GUEST_VM] ?? 'Proxmox Guest VM', ENT_QUOTES, "UTF-8") . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">0</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . (int)$proxmoxQty . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . $proxmoxUnitFormatted . '</td>
				<td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . ($formattedTotalsById[COID_PROXMOX_GUEST_VM] ?? formatCurrency(0,(int)$currencyData['id'])) . '</td>
				<td class="px-4 py-3">
					<a href="/index.php?m=eazybackup&a=user-profile&username=' . htmlspecialchars($comet_username, ENT_QUOTES, 'UTF-8') . '&serviceid=' . htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8') . '" class="text-sky-500 hover:text-sky-400 inline-flex items-center gap-1 px-2 py-1 rounded">Manage</a>
                </td>
            </tr>';        




            $deviceSources = [];
            foreach ($user->Sources as $source) {
                if (isset($deviceSources[$source->OwnerDevice])) {
                    $deviceSources[$source->OwnerDevice]++;
                } else {
                    $deviceSources[$source->OwnerDevice] = 1;
                }
            }
            $devices = [];
            $i = 0;

            foreach ($user->Devices as $id => $device) {
                $totalPrice = 0;
                $devices[$id] = [
                    "id" => $id,
                    "name" => $device->FriendlyName,
                    "protecteditems" => isset($deviceSources[$id]) ? $deviceSources[$id] : 0,
                    "deviceAmount" => "-",
                    "activity" => in_array($id, array_keys($activeDevices)) ? "Online" : "Offline",
                ];
                $i++;
            }
        }

        if ($devices) {
            $device_data = '';
            foreach ($devices as $device) {
                $device_data .= '<tr class="border-b border-slate-700/60 hover:bg-white/5 odd:bg-white/2">
                    <td class="px-4 py-3 text-slate-300">' . htmlspecialchars($device["name"], ENT_QUOTES, 'UTF-8') . '</td>
                    <td class="px-4 py-3 text-slate-200 text-right tabular-nums whitespace-nowrap">' . htmlspecialchars($device["protecteditems"], ENT_QUOTES, 'UTF-8') . '</td>
                    <td class="px-4 py-3 text-slate-300">' . htmlspecialchars($device["activity"], ENT_QUOTES, 'UTF-8') . '</td>
                    <td class="px-4 py-3">
                        <a href="/index.php?m=eazybackup&a=user-profile&username=' . htmlspecialchars($comet_username, ENT_QUOTES, 'UTF-8') . '&serviceid=' . htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8') . '" class="text-sky-500 hover:text-sky-400 inline-flex items-center gap-1 px-2 py-1 rounded">Manage</a>
                    </td>
                </tr>';
            }
        
    

        

			// (Removed) name-based configurable group iteration; now using ID-based accumulators
        } else {
            $device_data .=  '<tr class="border-b border-slate-700/60 hover:bg-white/5 odd:bg-white/2">
                <td colspan="4" class="px-4 py-3 text-center text-slate-400"><h3>No data available in table</h3></td>
            </tr>';
        }

        $MicrosoftAccountData = '';
        if ($MicrosoftAccountCount == 0) {
            $MicrosoftAccountData = '<tr>
				<td class="text-center text-sm text-slate-400" colspan="2">No data available in table</td>
            </tr>';
        } else {
            $MicrosoftAccountData = '<tr>
                <td class="text-left">' . htmlspecialchars($MicrosoftAccountCount) . '</td>
				<td class="text-right">' . htmlspecialchars($formattedTotalsById[COID_MICROSOFT_365_ACCOUNTS] ?? formatCurrency(0,(int)$currencyData['id'])) . '</td>
            </tr>';
        }


        
            $data .= '
                <div id="servicedetails_tab" class="pt-6 w-full min-h-screen">
                    <div class="">
                        <div class="flex flex-wrap">
                            <input type="hidden" name="serviceId" value="' . htmlspecialchars($serviceId) . '">
        
                            <!-- Profile Header -->
                            <div class="w-full mb-4">
                                <div class="flex justify-between items-center p-4 rounded-lg bg-slate-800/60 border border-slate-700 shadow-lg">
                                    <!-- Left Side: Profile Heading and Icon -->
                                    <div class="flex items-center">
                                        <h3 class="text-xl font-semibold text-slate-100 tracking-tight mr-2">' . htmlspecialchars($comet_username, ENT_QUOTES, 'UTF-8') . '</h3>                                           
                                    </div>
                                    
                                    <!-- Right Side: Button Group with Save and Actions -->
                                    <div class="flex space-x-2">
                                        <!-- Save Button -->
                                        <!-- <button type="button" class="text-sm bg-green-600 border border-green-600 text-slate-200 hover:border-green-500 hover:bg-green-500 hover:text-white px-4 py-2 rounded save_changes_btn" id="saveprofile">
                                            Save changes
                                         </button>-->
                                        
                                        <!-- Actions Dropdown Toggle -->
                                        <div class="relative" x-data="{ open: false, serviceId: \'' . htmlspecialchars($serviceId, ENT_QUOTES) . '\', cometUsername: \'' . htmlspecialchars($comet_username, ENT_QUOTES) . '\' }">
                                            <button type="button" class="bg-sky-600 hover:bg-sky-500 text-white text-sm px-4 py-2 border border-sky-600 rounded-md shadow focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900 flex items-center" id="actionsDropdown" aria-haspopup="true" :aria-expanded="open" @click="open = !open">
                                                Actions
                                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>

                                            <!-- Dropdown menu using Alpine.js -->
                                            <div class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-xl bg-slate-800/95 border border-slate-700 z-20" 
                                                    x-show="open" 
                                                    x-transition 
                                                    @click.away="open = false" 
                                                    role="menu" 
                                                    aria-orientation="vertical" 
                                                    aria-labelledby="actionsDropdown">
                                                <!-- Reset Password -->
                                                <a href="/index.php?m=eazybackup&a=user-profile&username=' . htmlspecialchars($comet_username, ENT_QUOTES, 'UTF-8') . '&serviceid=' . htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8') . '" 
                                                class="block flex items-center px-4 py-2 text-sm text-slate-200 hover:bg-slate-700/60" 
                                                role="menuitem" target="_blank" rel="noopener">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                    </svg>
                                                    Reset Password
                                                </a>

                                                <!-- Cancel Service -->
                                                <a href="/clientarea.php?action=cancel&id=' . htmlspecialchars($serviceId, ENT_QUOTES) . '" 
                                                class="block flex items-center px-4 py-2 text-sm text-slate-200 hover:bg-slate-700/60" 
                                                role="menuitem">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                    Cancel Service
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
        
                            

                            <!-- Usage Summary -->
                            <div class="w-full mb-6">
                                <div class="px-4 py-3 rounded-t-lg bg-slate-800/60 border border-b-0 border-slate-700">
                                    <h3 class="text-lg font-semibold text-slate-100 tracking-tight">Usage Summary</h3>
                                </div>
                                <div class="p-4 rounded-b-lg bg-slate-800/60 border border-t-0 border-slate-700 shadow-lg">
                                    <div class="overflow-visible">
                                        <table class="min-w-full">
                                            <thead>
                                                <tr class="border-b border-slate-700">
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-100 tracking-wide uppercase">
                                                        Billable Item
                                                    </th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-100 tracking-wide uppercase tabular-nums whitespace-nowrap">
                                                        Total Usage
                                                    </th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-100 tracking-wide uppercase tabular-nums whitespace-nowrap">
                                                        Purchased Amount
                                                    </th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-100 tracking-wide uppercase tabular-nums whitespace-nowrap">
                                                        Price per Unit
                                                    </th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-100 tracking-wide uppercase tabular-nums whitespace-nowrap">
                                                        Total Amount
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-100 tracking-wide uppercase">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            ' . $usageTable . '</tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
 
        
                            <!-- Microsoft 365 Accounts -->
                            <!-- <div class="w-full mb-6">
                                <div class="bg-slate-700 p-4 rounded-t-md shadow">
                                    <h3 class=" text-lg text-slate-200">Microsoft 365 Accounts</h3>
                                </div>
                                <div class="bg-slate-800 p-4 rounded-md shadow">
                                    <div class="overflow-y-visible">
                                        <table class="min-w-full">
                                            <thead>
                                                <tr class="bg-slate-800 border-b">
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-100 tracking-wide uppercase sorting_asc">Quantity</th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-100 tracking-wide uppercase sorting_asc">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>' . $MicrosoftAccountData . '</tbody>
                                        </table>
                                    </div>
                                </div>
                            </div> -->
        
                            <!-- Storage Vaults -->
                            <div id="storage-vaults" class="w-full mb-6">
                                <div class="px-4 py-3 rounded-t-lg bg-slate-800/60 border border-b-0 border-slate-700">
                                    <h3 class="text-lg font-semibold text-slate-100 tracking-tight">Storage Vaults</h3>
                                </div>
                                <div class="p-4 rounded-b-lg bg-slate-800/60 border border-t-0 border-slate-700 shadow-lg">
                                    <div class="overflow-visible">
                                        <table class="min-w-full">
                                            <thead>
                                                <tr class="border-b border-slate-700">
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-100 tracking-wide uppercase">
                                                        Vault Name
                                                    </th>                                            
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-100 tracking-wide uppercase">
                                                        Type
                                                    </th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-100 tracking-wide uppercase tabular-nums whitespace-nowrap">
                                                        Usage
                                                    </th>                                                    
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-100 tracking-wide uppercase">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>' . $StotageVault . $StotageVaultSum . '</tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
        
                            <!-- Registered Devices -->
                            <div class="w-full mb-6">
                                <div class="px-4 py-3 rounded-t-lg bg-slate-800/60 border border-b-0 border-slate-700">
                                    <h3 class="text-lg font-semibold text-slate-100 tracking-tight">Registered Devices</h3>
                                </div>
                                <div class="p-4 rounded-b-lg bg-slate-800/60 border border-t-0 border-slate-700 shadow-lg">                                    
                                    <div class="overflow-visible">
                                        <table class="min-w-full">
                                            <thead>
                                                <tr class="border-b border-slate-700">
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-100 tracking-wide uppercase">
                                                        Device Name
                                                    </th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-100 tracking-wide uppercase tabular-nums whitespace-nowrap">
                                                        Protected Items
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-100 tracking-wide uppercase">
                                                        Status
                                                    </th>                                                    
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-100 tracking-wide uppercase">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>' . $device_data . '</tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
        
            echo $data;
        }
    }
        


