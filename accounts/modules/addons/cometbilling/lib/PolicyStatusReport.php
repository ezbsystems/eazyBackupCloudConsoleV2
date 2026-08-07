<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

final class PolicyStatusReport
{
    public const GROUP_M365 = 'm365';
    public const GROUP_VIRTUAL_SERVER = 'virtual_server';

    /**
     * Named policy sets: server key → PolicyID.
     * M365 = Office 365 protected-accounts policies; Virtual Server = guest-VM-only policies.
     */
    public const POLICY_GROUPS = [
        self::GROUP_M365 => [
            'label' => 'Microsoft 365',
            'slug' => 'm365',
            'policies' => [
                'obc' => '9005920f-fa54-4a22-8844-533bda81da4c',
                'cometbackup' => '0e545d31-e0b3-4b38-8456-0999fa46f588',
            ],
        ],
        self::GROUP_VIRTUAL_SERVER => [
            'label' => 'Virtual Server',
            'slug' => 'virtual-server',
            'policies' => [
                'obc' => '77ad6576-912a-4ac7-8cd2-064c7f8907d2',
                'cometbackup' => 'a0d772aa-bf57-47f1-86f3-ff078364bee4',
            ],
        ],
    ];

    /** @deprecated Use POLICY_GROUPS[GROUP_M365]['policies']; kept for older call sites. */
    public const POLICY_MAP = [
        'obc' => '9005920f-fa54-4a22-8844-533bda81da4c',
        'cometbackup' => '0e545d31-e0b3-4b38-8456-0999fa46f588',
    ];

    public const SERVER_LABELS = [
        'obc' => 'csw.obcbackup.com',
        'cometbackup' => 'csw.eazybackup.ca',
    ];

    public const SECTION_WARNING = 'warning_accounts';
    public const SECTION_BILLED_UNHEALTHY = 'billed_unhealthy';
    public const SECTION_BILLED_SUCCESSFUL = 'billed_successful';
    public const SECTION_HISTORICAL_DEVICE_SUMMARY = 'historical_device_summary';
    public const SECTION_HISTORICAL_DEVICE_DETAIL = 'historical_device_detail';

    public const SECTION_LABELS = [
        self::SECTION_WARNING => 'warning-accounts',
        self::SECTION_BILLED_UNHEALTHY => 'billed-unhealthy',
        self::SECTION_BILLED_SUCCESSFUL => 'billed-successful',
        self::SECTION_HISTORICAL_DEVICE_SUMMARY => 'historical-device-summary',
        self::SECTION_HISTORICAL_DEVICE_DETAIL => 'historical-device-detail',
    ];

    public static function severityRank(int $status): int
    {
        if ($status === 5000) return 0;
        if ($status >= 6000 && $status <= 6002) return 1;
        if ($status === 7004) return 2;
        if ($status === 7001) return 3;
        if (in_array($status, [7000, 7002, 7003, 7005, 7006, 7007], true)) return 4;
        if ($status > 0) return 4;
        return -1;
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            5000 => 'success',
            6000, 6001, 6002 => 'running',
            7000 => 'timeout',
            7001 => 'warning',
            7002 => 'error',
            7003 => 'quota',
            7004 => 'missed',
            7005 => 'cancelled',
            7006 => 'already_running',
            7007 => 'abandoned',
            default => 'unknown',
        };
    }

    public static function isWarning(int $status): bool
    {
        return $status === 7001;
    }

    public static function isSuccess(int $status): bool
    {
        return $status === 5000;
    }

    public static function isWarningOrError(int $status): bool
    {
        return self::severityRank($status) >= 3;
    }

    public static function normalizeAccountKey(string $name): string
    {
        return strtolower(trim($name));
    }

    public static function indexLatestActiveServicesByAccount(): array
    {
        $latest = Capsule::table('cb_active_services')->max('pulled_at');
        if (!$latest) {
            return ['pulled_at' => null, 'by_account' => []];
        }
        $rows = Capsule::table('cb_active_services')->where('pulled_at', $latest)->get();
        return ['pulled_at' => $latest, 'by_account' => self::indexActiveServicesRows($rows)];
    }

    public static function indexActiveServicesRows(iterable $rows): array
    {
        $by = [];
        foreach ($rows as $row) {
            $account = trim((string) ($row->tenant_id ?? ''));
            if ($account === '') {
                $sn = (string) ($row->service_name ?? '');
                if (preg_match('/Account\s+([^\-]+)/i', $sn, $m)) {
                    $account = trim($m[1]);
                }
            }
            if ($account === '') {
                continue;
            }
            $key = self::normalizeAccountKey($account);
            if (!isset($by[$key])) {
                $by[$key] = [
                    'categories' => [],
                    'amount' => 0.0,
                    'device_amount' => 0.0,
                    'booster_amount' => 0.0,
                    'line_count' => 0,
                    'display_name' => $account,
                ];
            }
            $serviceName = (string) ($row->service_name ?? '');
            $bucket = self::activeServiceChargeBucket($row, $serviceName);
            $cat = self::activeServiceCategory($bucket, $serviceName);
            if ($cat !== null && !in_array($cat, $by[$key]['categories'], true)) {
                $by[$key]['categories'][] = $cat;
            }
            $amount = (float) ($row->amount ?? 0);
            $by[$key]['amount'] += $amount;
            if ($bucket === 'device') {
                $by[$key]['device_amount'] += $amount;
            } elseif ($bucket === 'booster') {
                $by[$key]['booster_amount'] += $amount;
            }
            $by[$key]['line_count']++;
        }
        return $by;
    }

    /**
     * Classify an Active Services row as device or booster using portal Type when present.
     */
    public static function activeServiceChargeBucket(object $row, ?string $serviceName = null): string
    {
        $extra = $row->extra ?? null;
        if (is_string($extra) && $extra !== '') {
            $decoded = json_decode($extra, true);
            $extra = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($extra)) {
            $extra = [];
        }
        $type = strtolower(trim((string) ($extra['Type'] ?? '')));
        if ($type === 'device') {
            return 'device';
        }
        if ($type === 'booster') {
            return 'booster';
        }

        $sn = strtolower((string) ($serviceName ?? $row->service_name ?? ''));
        if (str_contains($sn, 'booster') || str_contains($sn, 'office 365')
            || str_contains($sn, 'microsoft 365') || str_contains($sn, 'm365')
            || str_contains($sn, 'hyper-v') || str_contains($sn, 'hyperv')
            || str_contains($sn, 'vmware') || str_contains($sn, 'proxmox')
            || str_contains($sn, 'disk image') || str_contains($sn, 'sql server')) {
            return 'booster';
        }
        if ($type === 'account' || $type === 'serial') {
            return 'other';
        }
        return 'device';
    }

    private static function activeServiceCategory(string $bucket, string $serviceName): ?string
    {
        if ($bucket === 'device') {
            return 'devices';
        }
        if ($bucket === 'booster') {
            $sn = strtolower($serviceName);
            if (str_contains($sn, 'office 365') || str_contains($sn, 'microsoft 365') || str_contains($sn, 'm365')) {
                return 'm365_accounts';
            }
            if (str_contains($sn, 'hyper-v') || str_contains($sn, 'hyperv')) {
                return 'hyperv_vms';
            }
            if (str_contains($sn, 'vmware')) {
                return 'vmware_vms';
            }
            if (str_contains($sn, 'proxmox')) {
                return 'proxmox_vms';
            }
            if (str_contains($sn, 'disk image')) {
                return 'disk_image';
            }
            if (str_contains($sn, 'sql server') || str_contains($sn, 'mssql')) {
                return 'mssql';
            }
            return 'other';
        }
        return null;
    }

    public static function report(): array
    {
        $accountsByGroup = [];
        $membersByGroup = [];
        foreach (self::POLICY_GROUPS as $groupKey => $_meta) {
            $accountsByGroup[$groupKey] = [];
            $membersByGroup[$groupKey] = [];
        }
        $serverErrors = [];

        // serverKey => [policyId => groupKey]
        $policyLookup = [];
        $serverKeys = [];
        foreach (self::POLICY_GROUPS as $groupKey => $group) {
            foreach ($group['policies'] as $serverKey => $policyId) {
                $policyLookup[$serverKey][$policyId] = $groupKey;
                $serverKeys[$serverKey] = true;
            }
        }

        foreach (array_keys($serverKeys) as $serverKey) {
            try {
                $server = ServerUsageCollector::openServer($serverKey);
                if ($server === null) {
                    $serverErrors[$serverKey] = "Cannot connect to Comet server: {$serverKey}";
                    continue;
                }
                $profiles = $server->AdminListUsersFull();
                $wanted = $policyLookup[$serverKey] ?? [];
                foreach ($profiles as $profile) {
                    $profilePolicyId = (string) self::profileValue($profile, 'PolicyID');
                    if ($profilePolicyId === '' || !isset($wanted[$profilePolicyId])) {
                        continue;
                    }
                    $groupKey = $wanted[$profilePolicyId];
                    $username = (string) self::profileValue($profile, 'Username');
                    $deviceIds = self::profileDeviceIds($profile);

                    $membersByGroup[$groupKey][] = [
                        'server_key' => $serverKey,
                        'server_label' => self::SERVER_LABELS[$serverKey] ?? $serverKey,
                        'policy_id' => $profilePolicyId,
                        'policy_group' => $groupKey,
                        'username' => $username,
                        'device_ids' => $deviceIds,
                    ];

                    $sources = [];
                    foreach ((self::profileValue($profile, 'Sources') ?? []) as $sourceId => $source) {
                        $job = $source->Statistics->LastBackupJob ?? null;
                        if ($job === null) {
                            continue;
                        }
                        $status = (int) ($job->Status ?? 0);
                        if ($status <= 0) {
                            continue;
                        }
                        $sources[] = [
                            'status' => $status,
                            'end_time' => (int) ($job->EndTime ?? 0),
                            'start_time' => (int) ($job->StartTime ?? 0),
                            'source_id' => (string) $sourceId,
                        ];
                    }
                    $agg = self::aggregateAccountFromSources($sources);
                    if ($agg === null) {
                        continue;
                    }
                    $accountsByGroup[$groupKey][] = [
                        'server_key' => $serverKey,
                        'server_label' => self::SERVER_LABELS[$serverKey] ?? $serverKey,
                        'policy_id' => $profilePolicyId,
                        'policy_group' => $groupKey,
                        'username' => $username,
                        'status' => $agg['status'],
                        'status_label' => $agg['status_label'],
                        'last_job_time' => $agg['last_job_time'],
                        'source_count' => $agg['source_count'],
                        'warning_source_count' => $agg['warning_source_count'],
                        'error_source_count' => $agg['error_source_count'],
                    ];
                }
            } catch (\Throwable $e) {
                $serverErrors[$serverKey] = $e->getMessage();
            }
        }

        $activeServices = self::indexLatestActiveServicesByAccount();
        $groups = [];
        $totalAccounts = 0;
        $totalMembers = 0;
        foreach (self::POLICY_GROUPS as $groupKey => $groupMeta) {
            $accounts = $accountsByGroup[$groupKey] ?? [];
            $members = $membersByGroup[$groupKey] ?? [];
            $sections = self::buildSections($accounts, $activeServices['by_account']);
            $bhDeviceByAccount = self::indexBhDeviceTotalsByAccount($members);
            $sections = self::enrichBilledSectionsWithBhDevice($sections, $bhDeviceByAccount);
            $historical = self::historicalDeviceCharges($members);
            $totalAccounts += count($accounts);
            $totalMembers += count($members);
            $groups[$groupKey] = $sections + [
                'key' => $groupKey,
                'label' => $groupMeta['label'],
                'slug' => $groupMeta['slug'],
                'policies' => $groupMeta['policies'],
                'account_count' => count($accounts),
                'member_count' => count($members),
                'historical_device' => $historical,
            ];
        }

        // Backward-compatible top-level M365 sections for older callers.
        $m365 = $groups[self::GROUP_M365] ?? [
            'warning_accounts' => [],
            'billed_unhealthy' => [],
            'billed_successful' => [],
            'account_count' => 0,
        ];

        return [
            'groups' => $groups,
            'warning_accounts' => $m365['warning_accounts'],
            'billed_unhealthy' => $m365['billed_unhealthy'],
            'billed_successful' => $m365['billed_successful'],
            'active_services_pulled_at' => $activeServices['pulled_at'],
            'server_errors' => $serverErrors,
            'account_count' => $totalAccounts,
            'member_count' => $totalMembers,
            'server_error_count' => count($serverErrors),
        ];
    }

    /**
     * @return list<string>
     */
    private static function profileDeviceIds($profile): array
    {
        $devices = self::profileValue($profile, 'Devices');
        if (!is_array($devices) && !($devices instanceof \Traversable)) {
            return [];
        }
        $ids = [];
        foreach ($devices as $deviceId => $_device) {
            $id = trim((string) $deviceId);
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * Classify AS device run-rate vs Bill History device total.
     *
     * @return 'phantom'|'verified'|'bh_only'|'none'
     */
    public static function classifyBhDeviceStatus(float $asDeviceAmount, float $bhDeviceAmount): string
    {
        $as = round($asDeviceAmount, 2);
        $bh = round($bhDeviceAmount, 2);
        if ($as > 0 && $bh <= 0) {
            return 'phantom';
        }
        if ($as > 0 && $bh > 0) {
            return 'verified';
        }
        if ($as <= 0 && $bh > 0) {
            return 'bh_only';
        }
        return 'none';
    }

    /**
     * Bill History device totals keyed by normalized account (for Section B/C reconcile).
     *
     * @param list<array{username:string,device_ids?:list<string>}> $members
     * @return array<string, array{total_amount: float, last_charge: ?string}>
     */
    public static function indexBhDeviceTotalsByAccount(array $members): array
    {
        $lookup = self::buildMemberLookup($members);
        if ($lookup['by_user'] === []) {
            return [];
        }

        $totals = [];
        foreach (self::queryPolicyMemberDeviceUsageRows($lookup) as $match) {
            $userKey = $match['user_key'];
            $amount = $match['amount'];
            $date = $match['usage_date'];
            if (!isset($totals[$userKey])) {
                $totals[$userKey] = [
                    'total_amount' => 0.0,
                    'last_charge' => $date !== '' ? $date : null,
                ];
            }
            $totals[$userKey]['total_amount'] = round(
                (float) $totals[$userKey]['total_amount'] + $amount,
                2
            );
            if ($date !== '') {
                $last = $totals[$userKey]['last_charge'];
                if ($last === null || $date > $last) {
                    $totals[$userKey]['last_charge'] = $date;
                }
            }
        }

        return $totals;
    }

    /**
     * @param array<string, array{total_amount: float, last_charge: ?string}> $bhDeviceByAccount
     * @param array{
     *   warning_accounts: list<array<string,mixed>>,
     *   billed_unhealthy: list<array<string,mixed>>,
     *   billed_successful: list<array<string,mixed>>
     * } $sections
     * @return array{
     *   warning_accounts: list<array<string,mixed>>,
     *   billed_unhealthy: list<array<string,mixed>>,
     *   billed_successful: list<array<string,mixed>>
     * }
     */
    public static function enrichBilledSectionsWithBhDevice(array $sections, array $bhDeviceByAccount): array
    {
        $enrich = static function (array $row) use ($bhDeviceByAccount): array {
            return self::attachBhDeviceFields($row, $bhDeviceByAccount);
        };

        return [
            'warning_accounts' => $sections['warning_accounts'] ?? [],
            'billed_unhealthy' => array_map($enrich, $sections['billed_unhealthy'] ?? []),
            'billed_successful' => array_map($enrich, $sections['billed_successful'] ?? []),
        ];
    }

    /**
     * @param array<string, array{total_amount: float, last_charge: ?string}> $bhDeviceByAccount
     */
    public static function attachBhDeviceFields(array $row, array $bhDeviceByAccount): array
    {
        $key = self::normalizeAccountKey((string) ($row['username'] ?? ''));
        $bh = $bhDeviceByAccount[$key] ?? ['total_amount' => 0.0, 'last_charge' => null];
        $asDevice = (float) ($row['billed_device_amount'] ?? 0);
        $bhAmount = (float) ($bh['total_amount'] ?? 0);

        return $row + [
            'bh_device_amount' => $bhAmount,
            'bh_device_last' => $bh['last_charge'] ?? null,
            'bh_device_status' => self::classifyBhDeviceStatus($asDevice, $bhAmount),
        ];
    }

    /**
     * Bill History device charges for current policy members (claim support).
     *
     * @param list<array{username:string,server_key?:string,server_label?:string,policy_id?:string,device_ids?:list<string>}> $members
     * @return array{
     *   summary: list<array<string,mixed>>,
     *   details: list<array<string,mixed>>,
     *   total_amount: float,
     *   charge_count: int,
     *   account_count_with_charges: int,
     *   earliest: ?string,
     *   latest: ?string
     * }
     */
    public static function historicalDeviceCharges(array $members): array
    {
        $lookup = self::buildMemberLookup($members);
        if ($lookup['by_user'] === []) {
            return [
                'summary' => [],
                'details' => [],
                'total_amount' => 0.0,
                'charge_count' => 0,
                'account_count_with_charges' => 0,
                'earliest' => null,
                'latest' => null,
            ];
        }

        $byUser = $lookup['by_user'];
        $summaryMap = [];
        $details = [];
        $totalAmount = 0.0;
        $earliest = null;
        $latest = null;

        foreach (self::queryPolicyMemberDeviceUsageRows($lookup) as $match) {
            $userKey = $match['user_key'];
            $amount = $match['amount'];
            $date = $match['usage_date'];
            $meta = $byUser[$userKey];

            if (!isset($summaryMap[$userKey])) {
                $summaryMap[$userKey] = [
                    'server_key' => $meta['server_key'],
                    'server_label' => $meta['server_label'],
                    'policy_id' => $meta['policy_id'],
                    'username' => $meta['username'],
                    'charge_count' => 0,
                    'total_amount' => 0.0,
                    'first_charge' => $date !== '' ? $date : null,
                    'last_charge' => $date !== '' ? $date : null,
                ];
            }
            $summaryMap[$userKey]['charge_count']++;
            $summaryMap[$userKey]['total_amount'] = round(
                (float) $summaryMap[$userKey]['total_amount'] + $amount,
                2
            );
            if ($date !== '') {
                if ($summaryMap[$userKey]['first_charge'] === null || $date < $summaryMap[$userKey]['first_charge']) {
                    $summaryMap[$userKey]['first_charge'] = $date;
                }
                if ($summaryMap[$userKey]['last_charge'] === null || $date > $summaryMap[$userKey]['last_charge']) {
                    $summaryMap[$userKey]['last_charge'] = $date;
                }
                if ($earliest === null || $date < $earliest) {
                    $earliest = $date;
                }
                if ($latest === null || $date > $latest) {
                    $latest = $date;
                }
            }

            $totalAmount = round($totalAmount + $amount, 2);
            $details[] = [
                'server_key' => $meta['server_key'],
                'server_label' => $meta['server_label'],
                'policy_id' => $meta['policy_id'],
                'username' => $meta['username'],
                'usage_date' => $date,
                'device_id' => $match['device_id'],
                'item_type' => $match['item_type'],
                'item_desc' => $match['item_desc'],
                'amount' => $amount,
            ];
        }

        $summary = array_values($summaryMap);
        usort($summary, static function (array $a, array $b): int {
            $cmp = ((float) $b['total_amount']) <=> ((float) $a['total_amount']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string) $a['username'], (string) $b['username']);
        });

        return [
            'summary' => $summary,
            'details' => $details,
            'total_amount' => $totalAmount,
            'charge_count' => count($details),
            'account_count_with_charges' => count($summary),
            'earliest' => $earliest,
            'latest' => $latest,
        ];
    }

    /**
     * @param list<array{username:string,server_key?:string,server_label?:string,policy_id?:string,device_ids?:list<string>}> $members
     * @return array{
     *   by_user: array<string, array{username:string,server_key:string,server_label:string,policy_id:string}>,
     *   device_to_user: array<string, string>,
     *   username_list: list<string>,
     *   device_list: list<string>
     * }
     */
    private static function buildMemberLookup(array $members): array
    {
        $byUser = [];
        $usernames = [];
        $deviceToUser = [];
        $allDeviceIds = [];

        foreach ($members as $member) {
            $username = trim((string) ($member['username'] ?? ''));
            if ($username === '') {
                continue;
            }
            $key = self::normalizeAccountKey($username);
            $byUser[$key] = [
                'username' => $username,
                'server_key' => (string) ($member['server_key'] ?? ''),
                'server_label' => (string) ($member['server_label'] ?? ''),
                'policy_id' => (string) ($member['policy_id'] ?? ''),
            ];
            $usernames[$username] = true;
            foreach (($member['device_ids'] ?? []) as $deviceId) {
                $deviceId = trim((string) $deviceId);
                if ($deviceId === '') {
                    continue;
                }
                $deviceToUser[strtolower($deviceId)] = $key;
                $allDeviceIds[$deviceId] = true;
            }
        }

        return [
            'by_user' => $byUser,
            'device_to_user' => $deviceToUser,
            'username_list' => array_keys($usernames),
            'device_list' => array_keys($allDeviceIds),
        ];
    }

    /**
     * @param array{
     *   by_user: array<string, array{username:string,server_key:string,server_label:string,policy_id:string}>,
     *   device_to_user: array<string, string>,
     *   username_list: list<string>,
     *   device_list: list<string>
     * } $lookup
     * @return \Generator<int, array{
     *   user_key: string,
     *   usage_date: string,
     *   amount: float,
     *   device_id: string,
     *   item_type: string,
     *   item_desc: string
     * }>
     */
    private static function queryPolicyMemberDeviceUsageRows(array $lookup): \Generator
    {
        $byUser = $lookup['by_user'];
        $deviceToUser = $lookup['device_to_user'];
        $usernameList = $lookup['username_list'];
        $deviceList = $lookup['device_list'];

        if ($byUser === []) {
            return;
        }

        $query = CanonicalUsage::query()
            ->where('amount', '>', 0)
            ->where(function ($q) use ($usernameList, $deviceList) {
                $q->whereIn('tenant_id', $usernameList);
                if ($deviceList !== []) {
                    $q->orWhereIn('device_id', $deviceList);
                }
            })
            ->orderBy('usage_date')
            ->orderBy('id');

        foreach ($query->get(['usage_date', 'tenant_id', 'device_id', 'item_type', 'item_desc', 'amount']) as $row) {
            $category = ChargeCategoryResolver::fromUsageRow(
                (string) ($row->item_type ?? ''),
                $row->item_desc !== null ? (string) $row->item_desc : null
            );
            if ($category !== 'devices') {
                continue;
            }

            $tenantKey = self::normalizeAccountKey((string) ($row->tenant_id ?? ''));
            $deviceId = strtolower(trim((string) ($row->device_id ?? '')));
            $userKey = null;
            if ($tenantKey !== '' && isset($byUser[$tenantKey])) {
                $userKey = $tenantKey;
            } elseif ($deviceId !== '' && isset($deviceToUser[$deviceId])) {
                $userKey = $deviceToUser[$deviceId];
            }
            if ($userKey === null || !isset($byUser[$userKey])) {
                continue;
            }

            yield [
                'user_key' => $userKey,
                'usage_date' => substr((string) ($row->usage_date ?? ''), 0, 10),
                'amount' => round((float) ($row->amount ?? 0), 2),
                'device_id' => (string) ($row->device_id ?? ''),
                'item_type' => (string) ($row->item_type ?? ''),
                'item_desc' => (string) ($row->item_desc ?? ''),
            ];
        }
    }

    private static function profileValue($profile, string $key)
    {
        if (is_array($profile)) {
            return $profile[$key] ?? null;
        }

        return is_object($profile) ? ($profile->{$key} ?? null) : null;
    }

    /** @param list<array{status:int,end_time?:int,start_time?:int,source_id?:string}> $sources */
    public static function aggregateAccountFromSources(array $sources): ?array
    {
        $best = null;
        $warn = 0;
        $err = 0;
        $withJob = 0;
        foreach ($sources as $src) {
            $status = (int) ($src['status'] ?? 0);
            if ($status <= 0) {
                continue;
            }
            $withJob++;
            if ($status === 7001) {
                $warn++;
            }
            if (self::severityRank($status) === 4) {
                $err++;
            }
            $end = (int) ($src['end_time'] ?? $src['start_time'] ?? 0);
            if ($best === null
                || self::severityRank($status) > self::severityRank((int) $best['status'])
                || (self::severityRank($status) === self::severityRank((int) $best['status']) && $end > (int) $best['last_job_time'])
            ) {
                $best = [
                    'status' => $status,
                    'last_job_time' => $end,
                    'source_id' => $src['source_id'] ?? null,
                ];
            }
        }
        if ($best === null) {
            return null;
        }
        return [
            'status' => $best['status'],
            'status_label' => self::statusLabel((int) $best['status']),
            'last_job_time' => $best['last_job_time'],
            'source_count' => $withJob,
            'warning_source_count' => $warn,
            'error_source_count' => $err,
        ];
    }

    /**
     * @param list<array<string,mixed>> $accounts
     * @param array<string, array{categories: list<string>, amount: float, device_amount?: float, booster_amount?: float, line_count: int}> $billedByAccount
     */
    public static function buildSections(array $accounts, array $billedByAccount): array
    {
        $warning = [];
        $billedUnhealthy = [];
        $billedSuccessful = [];
        foreach ($accounts as $acct) {
            $status = (int) ($acct['status'] ?? 0);
            if (self::isWarning($status)) {
                $warning[] = $acct;
            }
            $key = self::normalizeAccountKey((string) ($acct['username'] ?? ''));
            if ($key === '' || !isset($billedByAccount[$key])) {
                continue;
            }
            $billed = $billedByAccount[$key];
            $billedFields = [
                'billed_categories' => $billed['categories'],
                'billed_amount' => $billed['amount'],
                'billed_device_amount' => (float) ($billed['device_amount'] ?? 0),
                'billed_booster_amount' => (float) ($billed['booster_amount'] ?? 0),
                'billed_line_count' => $billed['line_count'],
            ];
            if (self::isWarningOrError($status)) {
                $billedUnhealthy[] = $acct + $billedFields;
            }
            if (self::isSuccess($status)) {
                $billedSuccessful[] = $acct + $billedFields;
            }
        }
        return [
            'warning_accounts' => $warning,
            'billed_unhealthy' => $billedUnhealthy,
            'billed_successful' => $billedSuccessful,
        ];
    }

    public static function isValidGroup(string $group): bool
    {
        return isset(self::POLICY_GROUPS[$group]);
    }

    public static function isValidSection(string $section): bool
    {
        return isset(self::SECTION_LABELS[$section]);
    }

    /**
     * @param array<string, mixed> $reportSection Output of buildSections() (or a groups[*] entry)
     */
    public static function buildCsvForSection(array $reportSection, string $section): string
    {
        if (!self::isValidSection($section)) {
            throw new \InvalidArgumentException('Unknown Policy Status section: ' . $section);
        }

        if ($section === self::SECTION_HISTORICAL_DEVICE_SUMMARY
            || $section === self::SECTION_HISTORICAL_DEVICE_DETAIL) {
            return self::buildHistoricalDeviceCsv($reportSection, $section);
        }

        $rows = $reportSection[$section] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }

        $includeCharges = $section !== self::SECTION_WARNING;
        $headers = [
            'Server',
            'Server key',
            'Policy ID',
            'Username',
            'Sources warn',
            'Sources total',
            'Last job UTC',
            'Status',
        ];
        if ($includeCharges) {
            $headers[] = 'Device charge';
            $headers[] = 'BH device';
            $headers[] = 'BH device status';
            $headers[] = 'BH device last';
            $headers[] = 'Booster charge';
        }

        return self::csvFromRows($headers, $rows, static function (array $row) use ($includeCharges): array {
            $ts = (int) ($row['last_job_time'] ?? 0);
            $line = [
                (string) ($row['server_label'] ?? ''),
                (string) ($row['server_key'] ?? ''),
                (string) ($row['policy_id'] ?? ''),
                (string) ($row['username'] ?? ''),
                (int) ($row['warning_source_count'] ?? 0),
                (int) ($row['source_count'] ?? 0),
                $ts > 0 ? gmdate('Y-m-d H:i:s', $ts) : '',
                (string) ($row['status_label'] ?? self::statusLabel((int) ($row['status'] ?? 0))),
            ];
            if ($includeCharges) {
                $line[] = number_format((float) ($row['billed_device_amount'] ?? 0), 2, '.', '');
                $line[] = number_format((float) ($row['bh_device_amount'] ?? 0), 2, '.', '');
                $line[] = (string) ($row['bh_device_status'] ?? 'none');
                $line[] = (string) ($row['bh_device_last'] ?? '');
                $line[] = number_format((float) ($row['billed_booster_amount'] ?? 0), 2, '.', '');
            }
            return $line;
        });
    }

    private static function buildHistoricalDeviceCsv(array $reportSection, string $section): string
    {
        $historical = $reportSection['historical_device'] ?? [];
        if ($section === self::SECTION_HISTORICAL_DEVICE_SUMMARY) {
            $rows = $historical['summary'] ?? [];
            if (!is_array($rows)) {
                $rows = [];
            }
            return self::csvFromRows(
                [
                    'Server',
                    'Server key',
                    'Policy ID',
                    'Username',
                    'First device charge',
                    'Last device charge',
                    'Charge count',
                    'Total device amount',
                ],
                $rows,
                static function (array $row): array {
                    return [
                        (string) ($row['server_label'] ?? ''),
                        (string) ($row['server_key'] ?? ''),
                        (string) ($row['policy_id'] ?? ''),
                        (string) ($row['username'] ?? ''),
                        (string) ($row['first_charge'] ?? ''),
                        (string) ($row['last_charge'] ?? ''),
                        (int) ($row['charge_count'] ?? 0),
                        number_format((float) ($row['total_amount'] ?? 0), 2, '.', ''),
                    ];
                }
            );
        }

        $rows = $historical['details'] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }
        return self::csvFromRows(
            [
                'Server',
                'Server key',
                'Policy ID',
                'Username',
                'Usage date',
                'Device ID',
                'Item type',
                'Item description',
                'Amount',
            ],
            $rows,
            static function (array $row): array {
                return [
                    (string) ($row['server_label'] ?? ''),
                    (string) ($row['server_key'] ?? ''),
                    (string) ($row['policy_id'] ?? ''),
                    (string) ($row['username'] ?? ''),
                    (string) ($row['usage_date'] ?? ''),
                    (string) ($row['device_id'] ?? ''),
                    (string) ($row['item_type'] ?? ''),
                    (string) ($row['item_desc'] ?? ''),
                    number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                ];
            }
        );
    }

    /**
     * @param list<string> $headers
     * @param list<array<string,mixed>> $rows
     * @param callable(array<string,mixed>): list<scalar> $mapper
     */
    private static function csvFromRows(array $headers, array $rows, callable $mapper): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            throw new \RuntimeException('Unable to open CSV buffer');
        }
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            fputcsv($fh, $mapper($row));
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv === false ? '' : $csv;
    }

    public static function streamCsv(string $section, ?string $group = null): void
    {
        if (!self::isValidSection($section)) {
            throw new \InvalidArgumentException('Unknown Policy Status section: ' . $section);
        }
        $groupKey = $group !== null && $group !== '' ? $group : self::GROUP_M365;
        if (!self::isValidGroup($groupKey)) {
            throw new \InvalidArgumentException('Unknown Policy Status group: ' . $groupKey);
        }

        $report = self::report();
        $groupData = $report['groups'][$groupKey] ?? null;
        if (!is_array($groupData)) {
            throw new \RuntimeException('Policy group data missing: ' . $groupKey);
        }
        $csv = self::buildCsvForSection($groupData, $section);
        $groupSlug = (string) (self::POLICY_GROUPS[$groupKey]['slug'] ?? $groupKey);
        $slug = self::SECTION_LABELS[$section];
        $filename = 'policy-status-' . $groupSlug . '-' . $slug . '-' . gmdate('Y-m-d') . '.csv';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($csv));
        header('Cache-Control: no-store');
        echo $csv;
        exit;
    }
}
