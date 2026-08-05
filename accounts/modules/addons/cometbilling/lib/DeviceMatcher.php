<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Match portal billing line items to server device inventory for reconcile drill-down.
 */
class DeviceMatcher
{
    private const QTY_CATEGORIES = ['hyperv_vms', 'vmware_vms', 'proxmox_vms', 'm365_accounts'];
    private const PRESENCE_BOOSTERS = ['disk_image', 'mssql'];
    private const LIST_CAP = 100;

    /**
     * Load flattened device inventory rows for a snapshot date.
     *
     * @return list<array<string, mixed>>
     */
    public static function loadInventoryForSnapshot(string $snapshotDate): array
    {
        self::ensureTable();

        return Capsule::table('cb_server_device_inventory')
            ->where('snapshot_date', $snapshotDate)
            ->get()
            ->map(static fn ($row) => [
                'server_key' => (string) $row->server_key,
                'username' => (string) $row->username,
                'device_id' => (string) $row->device_id,
                'friendly_name' => $row->friendly_name,
                'hyperv_vms' => (int) $row->hyperv_vms,
                'vmware_vms' => (int) $row->vmware_vms,
                'proxmox_vms' => (int) $row->proxmox_vms,
                'disk_image' => (int) $row->disk_image,
                'mssql' => (int) $row->mssql,
                'm365_accounts' => (int) $row->m365_accounts,
            ])
            ->all();
    }

    /**
     * Flatten device_inventory arrays from collectAll/collectFromServer output.
     *
     * @return list<array<string, mixed>>
     */
    public static function flattenCollectedInventory(array $serverData): array
    {
        $rows = [];
        foreach ($serverData['servers'] ?? [] as $serverKey => $srv) {
            foreach ($srv['device_inventory'] ?? [] as $device) {
                $device['server_key'] = $serverKey;
                $rows[] = $device;
            }
        }

        return $rows;
    }

    /**
     * Compute unmatched lists for one reconcile category.
     *
     * @param list<array<string, mixed>> $portalItems
     * @param list<array<string, mixed>> $serverInventory
     */
    public static function matchCategory(
        string $categoryKey,
        array $portalItems,
        array $serverInventory,
        bool $applyCap = true,
        ?string $snapshotDate = null
    ): array {
        $portalOnly = [];
        $serverOnly = [];
        $qtyMismatch = [];
        $matchedServerDeviceIds = [];

        foreach ($portalItems as $portalItem) {
            $portalDeviceId = strtolower(trim((string) ($portalItem['device_id'] ?? '')));
            if ($portalDeviceId === '') {
                $portalOnly[] = self::formatPortalRow($portalItem, null, $categoryKey);
                continue;
            }

            $serverRow = self::findServerDevice($portalDeviceId, $portalItem['account'] ?? null, $serverInventory);
            if ($serverRow === null) {
                $portalOnly[] = self::formatPortalRow($portalItem, null, $categoryKey);
                continue;
            }

            $fullDeviceId = $serverRow['device_id'];
            $matchedServerDeviceIds[$fullDeviceId] = true;

            if ($categoryKey === 'devices') {
                continue;
            }

            $serverQty = (float) ($serverRow[$categoryKey] ?? 0);
            $portalQty = (float) ($portalItem['quantity'] ?? 1);

            if (in_array($categoryKey, self::PRESENCE_BOOSTERS, true)) {
                if ($serverQty <= 0) {
                    $portalOnly[] = self::formatPortalRow($portalItem, $serverRow, $categoryKey);
                }
                continue;
            }

            if (in_array($categoryKey, self::QTY_CATEGORIES, true)) {
                if ($serverQty <= 0 && $portalQty > 0) {
                    $portalOnly[] = self::formatPortalRow($portalItem, $serverRow, $categoryKey);
                } elseif (abs($serverQty - $portalQty) > 0.0001) {
                    $qtyMismatch[] = self::formatQtyMismatch($portalItem, $serverRow, $categoryKey, $portalQty, $serverQty);
                }
            }
        }

        foreach ($serverInventory as $serverRow) {
            if ($categoryKey === 'devices') {
                if (!self::portalHasDevice($serverRow['device_id'], $portalItems)) {
                    $serverOnly[] = self::formatServerRow($serverRow, $categoryKey, 1);
                }
                continue;
            }

            $serverQty = (float) ($serverRow[$categoryKey] ?? 0);
            if ($serverQty <= 0) {
                continue;
            }

            $fullDeviceId = $serverRow['device_id'];
            if (!isset($matchedServerDeviceIds[$fullDeviceId])) {
                $serverOnly[] = self::formatServerRow($serverRow, $categoryKey, $serverQty);
                continue;
            }

            if (in_array($categoryKey, self::QTY_CATEGORIES, true)
                && !self::portalLineExistsForDevice($fullDeviceId, $portalItems)) {
                $serverOnly[] = self::formatServerRow($serverRow, $categoryKey, $serverQty);
            }
        }

        $portalOnly = self::enrichPortalOnlyRows($portalOnly, $categoryKey, $snapshotDate);

        $pastGraceOverbill = 0.0;
        $pastGraceCount = 0;
        foreach ($portalOnly as $row) {
            if (($row['billing_status'] ?? '') === 'overbilled_past_grace') {
                $pastGraceCount++;
                $pastGraceOverbill += (float) ($row['overbill_amount'] ?? 0);
            }
        }

        $lists = [
            'portal_only' => $portalOnly,
            'server_only' => $serverOnly,
            'qty_mismatch' => $qtyMismatch,
            'past_grace_count' => $pastGraceCount,
            'past_grace_overbill_total' => round($pastGraceOverbill, 2),
        ];

        return $applyCap ? self::capLists($lists) : $lists;
    }

    public static function ensureTable(): void
    {
        if (Capsule::schema()->hasTable('cb_server_device_inventory')) {
            return;
        }

        Capsule::schema()->create('cb_server_device_inventory', function ($table) {
            $table->bigIncrements('id');
            $table->date('snapshot_date');
            $table->string('server_key', 64);
            $table->string('username', 255)->default('');
            $table->string('device_id', 64);
            $table->string('friendly_name', 255)->nullable();
            $table->unsignedInteger('hyperv_vms')->default(0);
            $table->unsignedInteger('vmware_vms')->default(0);
            $table->unsignedInteger('proxmox_vms')->default(0);
            $table->unsignedInteger('disk_image')->default(0);
            $table->unsignedInteger('mssql')->default(0);
            $table->unsignedInteger('m365_accounts')->default(0);
            $table->dateTime('created_at');
            $table->unique(['snapshot_date', 'server_key', 'device_id'], 'uq_snapshot_device');
            $table->index('snapshot_date');
            $table->index('server_key');
        });
    }

  /**
     * @param list<array<string, mixed>> $serverInventory
     * @return array<string, mixed>|null
     */
    private static function findServerDevice(string $portalDeviceId, ?string $account, array $serverInventory): ?array
    {
        $candidates = [];
        foreach ($serverInventory as $row) {
            if (str_starts_with(strtolower($row['device_id']), $portalDeviceId)) {
                $candidates[] = $row;
            }
        }

        if ($candidates === []) {
            return null;
        }
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        if ($account !== null && $account !== '') {
            $normAccount = self::normalizeName($account);
            foreach ($candidates as $candidate) {
                $normUser = self::normalizeName($candidate['username'] ?? '');
                if ($normUser === $normAccount || str_contains($normUser, $normAccount) || str_contains($normAccount, $normUser)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $portalItems
     */
    private static function portalHasDevice(string $fullDeviceId, array $portalItems): bool
    {
        $needle = strtolower($fullDeviceId);
        foreach ($portalItems as $item) {
            $portalId = strtolower(trim((string) ($item['device_id'] ?? '')));
            if ($portalId !== '' && str_starts_with($needle, $portalId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $portalItems
     */
    private static function portalLineExistsForDevice(string $fullDeviceId, array $portalItems): bool
    {
        foreach ($portalItems as $item) {
            $serverRow = self::findServerDevice(
                strtolower(trim((string) ($item['device_id'] ?? ''))),
                $item['account'] ?? null,
                [['device_id' => $fullDeviceId, 'username' => '']]
            );
            if ($serverRow !== null) {
                return true;
            }
        }

        return false;
    }

    private static function formatPortalRow(array $portalItem, ?array $serverRow, string $categoryKey): array
    {
        return [
            'account' => $portalItem['account'] ?? null,
            'device_id' => $portalItem['device_id'] ?? null,
            'server_device_id' => $serverRow['device_id'] ?? null,
            'friendly_name' => $serverRow['friendly_name'] ?? null,
            'server_key' => $serverRow['server_key'] ?? null,
            'username' => $serverRow['username'] ?? null,
            'portal_qty' => (float) ($portalItem['quantity'] ?? 1),
            'server_qty' => $serverRow !== null ? (float) ($serverRow[$categoryKey] ?? ($categoryKey === 'devices' ? 1 : 0)) : null,
            'amount' => (float) ($portalItem['amount'] ?? 0),
            'service' => $portalItem['raw_service_name'] ?? $portalItem['booster_type'] ?? null,
            'next_due_date' => $portalItem['next_due_date'] ?? null,
            'billing_cycle_days' => (int) ($portalItem['billing_cycle_days'] ?? 30) ?: 30,
            'revoked_at' => null,
            'registered_at' => null,
            'device_name' => null,
            'expected_billing_end' => null,
            'billing_status' => null,
            'overbill_amount' => 0.0,
        ];
    }

    /**
     * Attach revocation and billing period status to portal-only rows.
     *
     * Mode is driven by portal billing_cycle_days on the line:
     * - cycle > 1: registration-aligned periods (devices, M365, disk image, MSSQL, …)
     * - cycle <= 1: daily remove-day (Hyper-V, VMware, Proxmox, …)
     *
     * @param list<array<string, mixed>> $portalOnly
     * @return list<array<string, mixed>>
     */
    private static function enrichPortalOnlyRows(
        array $portalOnly,
        string $categoryKey,
        ?string $snapshotDate = null
    ): array {
        if ($portalOnly === []) {
            return $portalOnly;
        }

        $index = self::loadRevokedDeviceIndex();

        foreach ($portalOnly as $i => $row) {
            $revoked = self::findRevokedDevice($row, $index);
            $cycleDays = (int) ($row['billing_cycle_days'] ?? 30) ?: 30;
            $nextDue = $row['next_due_date'] ?? null;
            $isDaily = $cycleDays <= 1;

            if ($isDaily) {
                $removeDate = self::resolveBoosterRemoveDate($row, $revoked, $categoryKey, $snapshotDate);
                $snap = $snapshotDate ?: gmdate('Y-m-d');
                $status = BillingPeriodCalculator::boosterBillingStatus($removeDate, $snap);

                if ($revoked !== null) {
                    $portalOnly[$i]['revoked_at'] = (string) $revoked['revoked_at'];
                    $portalOnly[$i]['registered_at'] = $revoked['registered_at'] ?? null;
                    if (empty($portalOnly[$i]['friendly_name']) && !empty($revoked['name'])) {
                        $portalOnly[$i]['friendly_name'] = $revoked['name'];
                    }
                    $portalOnly[$i]['device_name'] = $revoked['name'] ?? null;
                }

                $portalOnly[$i]['billing_cycle_days'] = 1;
                $portalOnly[$i]['expected_billing_end'] = $removeDate;
                $portalOnly[$i]['billing_status'] = $status;
                $portalOnly[$i]['overbill_amount'] = $status === 'overbilled_past_grace'
                    ? (float) ($row['amount'] ?? 0)
                    : 0.0;
                continue;
            }

            // Monthly / multi-day cycle: registration-aligned period containing revoke/remove.
            $eventDate = null;
            $registeredAt = null;
            if ($revoked !== null) {
                $eventDate = BillingPeriodCalculator::utcDateOnly((string) $revoked['revoked_at']);
                $registeredAt = $revoked['registered_at'] ?? null;
                $portalOnly[$i]['revoked_at'] = $eventDate;
                $portalOnly[$i]['registered_at'] = $registeredAt;
                $portalOnly[$i]['device_name'] = $revoked['name'] ?? null;
                if (empty($portalOnly[$i]['friendly_name']) && !empty($revoked['name'])) {
                    $portalOnly[$i]['friendly_name'] = $revoked['name'];
                }
            } elseif ($categoryKey !== 'devices') {
                $eventDate = self::resolveBoosterRemoveDate($row, null, $categoryKey, $snapshotDate);
                if ($eventDate !== null) {
                    $portalOnly[$i]['revoked_at'] = $eventDate;
                }
            }

            if ($eventDate === null) {
                $portalOnly[$i]['billing_status'] = 'unknown';
                $portalOnly[$i]['overbill_amount'] = 0.0;
                continue;
            }

            $expectedEnd = BillingPeriodCalculator::deviceExpectedEnd(
                $registeredAt,
                $eventDate,
                $cycleDays,
                $nextDue
            );
            $billingStatus = BillingPeriodCalculator::deviceBillingStatus($expectedEnd, $nextDue);

            $portalOnly[$i]['billing_cycle_days'] = $cycleDays;
            $portalOnly[$i]['expected_billing_end'] = $expectedEnd;
            $portalOnly[$i]['billing_status'] = $billingStatus;
            $portalOnly[$i]['overbill_amount'] = $billingStatus === 'overbilled_past_grace'
                ? (float) ($row['amount'] ?? 0)
                : 0.0;
        }

        return $portalOnly;
    }

    private static function resolveBoosterRemoveDate(
        array $row,
        ?array $revoked,
        string $categoryKey,
        ?string $snapshotDate
    ): ?string {
        if ($revoked !== null && !empty($revoked['revoked_at'])) {
            return BillingPeriodCalculator::utcDateOnly((string) $revoked['revoked_at']);
        }

        $deviceId = (string) ($row['server_device_id'] ?? '');
        if ($deviceId === '' || $snapshotDate === null || $snapshotDate === '') {
            return null;
        }

        return self::findBoosterDisappearedDate($deviceId, $categoryKey, $snapshotDate);
    }

    /**
     * Last snapshot date where category quantity was positive before a later
     * snapshot showed zero or did not include the device.
     */
    private static function findBoosterDisappearedDate(
        string $deviceId,
        string $categoryKey,
        string $asOfSnapshotDate
    ): ?string {
        if (!Capsule::schema()->hasTable('cb_server_device_inventory')) {
            return null;
        }

        $allowed = [
            'hyperv_vms',
            'vmware_vms',
            'proxmox_vms',
            'disk_image',
            'mssql',
            'm365_accounts',
        ];
        if (!in_array($categoryKey, $allowed, true)) {
            return null;
        }

        $rows = Capsule::table('cb_server_device_inventory')
            ->where('device_id', $deviceId)
            ->where('snapshot_date', '<=', $asOfSnapshotDate)
            ->orderBy('snapshot_date', 'asc')
            ->get(['snapshot_date', $categoryKey]);

        $lastPositive = null;
        $prevQty = null;
        $prevDate = null;
        foreach ($rows as $row) {
            $qty = (int) $row->{$categoryKey};
            $date = (string) $row->snapshot_date;
            if ($prevDate !== null && $prevQty > 0 && $qty <= 0) {
                return $prevDate;
            }
            if ($qty > 0) {
                $lastPositive = $date;
            }
            $prevQty = $qty;
            $prevDate = $date;
        }

        if ($lastPositive !== null && $lastPositive < $asOfSnapshotDate) {
            $laterExists = Capsule::table('cb_server_device_inventory')
                ->where('snapshot_date', '>', $lastPositive)
                ->where('snapshot_date', '<=', $asOfSnapshotDate)
                ->where('device_id', $deviceId)
                ->exists();
            if (!$laterExists) {
                $snapshotTaken = Capsule::table('cb_server_device_inventory')
                    ->where('snapshot_date', '>', $lastPositive)
                    ->where('snapshot_date', '<=', $asOfSnapshotDate)
                    ->exists();
                if ($snapshotTaken) {
                    return $lastPositive;
                }
            }
        }

        return null;
    }

    /**
     * @return array{by_prefix: array<string, list<array>>, by_hash: array<string, array>}
     */
    private static function loadRevokedDeviceIndex(): array
    {
        $byPrefix = [];
        $byHash = [];

        if (!Capsule::schema()->hasTable('comet_devices')) {
            return ['by_prefix' => $byPrefix, 'by_hash' => $byHash];
        }

        $rows = Capsule::table('comet_devices')
            ->whereNotNull('revoked_at')
            ->select(['hash', 'username', 'name', 'revoked_at', 'content'])
            ->get();

        foreach ($rows as $row) {
            $hash = strtolower((string) $row->hash);
            $registrationAt = null;
            $content = json_decode((string) ($row->content ?? ''), true);
            if (is_array($content)) {
                $registrationTime = (int) ($content['RegistrationTime'] ?? 0);
                if ($registrationTime > 0) {
                    $registrationAt = gmdate('Y-m-d', $registrationTime);
                }
            }
            $entry = [
                'hash' => $hash,
                'username' => (string) $row->username,
                'name' => $row->name,
                'revoked_at' => (string) $row->revoked_at,
                'registered_at' => $registrationAt,
            ];
            $byHash[$hash] = $entry;
            $prefix = substr($hash, 0, 6);
            $byPrefix[$prefix][] = $entry;
        }

        return ['by_prefix' => $byPrefix, 'by_hash' => $byHash];
    }

    /**
     * @param array{by_prefix: array<string, list<array>>, by_hash: array<string, array>} $index
     * @return array<string, mixed>|null
     */
    private static function findRevokedDevice(array $row, array $index): ?array
    {
        $serverDeviceId = strtolower((string) ($row['server_device_id'] ?? ''));
        if ($serverDeviceId !== '' && isset($index['by_hash'][$serverDeviceId])) {
            return $index['by_hash'][$serverDeviceId];
        }

        $portalDeviceId = strtolower(trim((string) ($row['device_id'] ?? '')));
        if ($portalDeviceId === '') {
            return null;
        }

        $candidates = $index['by_prefix'][$portalDeviceId] ?? [];
        if ($candidates === []) {
            return null;
        }
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $account = $row['account'] ?? $row['username'] ?? null;
        if ($account !== null && $account !== '') {
            $normAccount = self::normalizeName((string) $account);
            foreach ($candidates as $candidate) {
                $normUser = self::normalizeName($candidate['username'] ?? '');
                if ($normUser === $normAccount
                    || str_contains($normUser, $normAccount)
                    || str_contains($normAccount, $normUser)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private static function formatServerRow(array $serverRow, string $categoryKey, float $serverQty): array
    {
        return [
            'account' => $serverRow['username'] ?? null,
            'device_id' => self::shortDeviceId($serverRow['device_id']),
            'server_device_id' => $serverRow['device_id'],
            'friendly_name' => $serverRow['friendly_name'] ?? null,
            'server_key' => $serverRow['server_key'] ?? null,
            'username' => $serverRow['username'] ?? null,
            'portal_qty' => null,
            'server_qty' => $serverQty,
            'amount' => null,
            'service' => null,
        ];
    }

    private static function formatQtyMismatch(
        array $portalItem,
        array $serverRow,
        string $categoryKey,
        float $portalQty,
        float $serverQty
    ): array {
        return [
            'account' => $portalItem['account'] ?? $serverRow['username'] ?? null,
            'device_id' => $portalItem['device_id'] ?? self::shortDeviceId($serverRow['device_id']),
            'server_device_id' => $serverRow['device_id'],
            'friendly_name' => $serverRow['friendly_name'] ?? null,
            'server_key' => $serverRow['server_key'] ?? null,
            'username' => $serverRow['username'] ?? null,
            'portal_qty' => $portalQty,
            'server_qty' => $serverQty,
            'qty_variance' => $portalQty - $serverQty,
            'amount' => (float) ($portalItem['amount'] ?? 0),
            'service' => $portalItem['raw_service_name'] ?? $portalItem['booster_type'] ?? null,
        ];
    }

    private static function capLists(array $lists): array
    {
        $listBuckets = ['portal_only', 'server_only', 'qty_mismatch'];
        $result = [
            'past_grace_count' => (int) ($lists['past_grace_count'] ?? 0),
            'past_grace_overbill_total' => (float) ($lists['past_grace_overbill_total'] ?? 0),
        ];

        foreach ($listBuckets as $bucket) {
            $rows = $lists[$bucket] ?? [];
            $total = count($rows);
            if ($bucket === 'portal_only' && $total > self::LIST_CAP) {
                // Prefer overbilled-past-grace rows when truncating UI lists
                usort($rows, static function ($a, $b) {
                    $rank = static function ($row) {
                        return ($row['billing_status'] ?? '') === 'overbilled_past_grace' ? 0 : 1;
                    };
                    return $rank($a) <=> $rank($b);
                });
            }
            $result[$bucket] = array_slice($rows, 0, self::LIST_CAP);
            if ($total > self::LIST_CAP) {
                $result['truncated'][$bucket] = $total - self::LIST_CAP;
            }
        }

        return $result;
    }

    private static function shortDeviceId(string $deviceId): string
    {
        return strlen($deviceId) > 6 ? substr($deviceId, 0, 6) : $deviceId;
    }

    private static function normalizeName(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? '';
    }
}
