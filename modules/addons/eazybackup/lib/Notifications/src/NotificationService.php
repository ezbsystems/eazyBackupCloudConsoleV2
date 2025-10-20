<?php
declare(strict_types=1);

namespace EazyBackup\Notifications;

use PDO;
use WHMCS\Database\Capsule;

class NotificationService
{
    private function billingTermLabelForService(int $serviceId): string
    {
        try {
            $cycle = (string)Capsule::table('tblhosting')->where('id', $serviceId)->value('billingcycle');
            $cycle = trim($cycle);
            // Normalize to human-friendly label
            return match ($cycle) {
                'Monthly' => 'monthly',
                'Quarterly' => 'quarterly',
                'Semi-Annually', 'Semiannually' => 'semi-annually',
                'Annually', 'Yearly' => 'annually',
                'Biennially' => 'biennially',
                'Triennially' => 'triennially',
                default => 'monthly',
            };
        } catch (\Throwable $e) { return 'monthly'; }
    }

    private function isStorageDeviceNotificationsDisabled(int $serviceId): bool
    {
        try {
            $pid = (int)Capsule::table('tblhosting')->where('id', $serviceId)->value('packageid');
            return ($pid === 52 || $pid === 57);
        } catch (\Throwable $e) { return false; }
    }

    private function isDeviceNotificationsDisabled(int $serviceId): bool
    {
        try {
            $pid = (int)Capsule::table('tblhosting')->where('id', $serviceId)->value('packageid');
            // Suppress device notifications for M365 (52,57) and Virtual Server (53,54)
            return ($pid === 52 || $pid === 57 || $pid === 53 || $pid === 54);
        } catch (\Throwable $e) { return false; }
    }

    private function debugEnabled(): bool
    {
        return getenv('EB_NOTIFY_DEBUG') === '1' || Config::bool('notify_debug', false);
    }

    private function debug(string $msg): void
    {
        if ($this->debugEnabled()) { error_log('[notify] ' . $msg); }
    }

    public function onDeviceRegistered(PDO $pdo, string $profile, string $username, string $deviceId, array $payload): void
    {
        if (getenv('EB_WS_DEBUG') === '1') { error_log("[{$profile}] notify:onDeviceRegistered user={$username} device={$deviceId}"); }
        // Global gate, then client-level preference
        if (!Config::bool('notify_devices', true)) return;
        $svc = $this->serviceForUsername($pdo, $username);
        if (!$svc) {
            if (Config::bool('notify_test_mode', false)) {
                // Fallback in Test Mode: allow send without strict service mapping
                $testClientId = (int)Config::get('notify_test_client_id', 0);
                $svc = ['service_id' => 0, 'client_id' => ($testClientId > 0 ? $testClientId : 0)];
                if (getenv('EB_WS_DEBUG') === '1') { error_log("[{$profile}] notify:onDeviceRegistered TEST-MODE fallback, username={$username}"); }
            } else {
                if (getenv('EB_WS_DEBUG') === '1') { error_log("[{$profile}] notify:onDeviceRegistered no service match for username={$username}"); }
                return; // unknown or suspended/canceled suppressed in helper
            }
        }

        // Suppress device notifications for product packages that include unlimited storage and a single device
        if ($this->isDeviceNotificationsDisabled((int)$svc['service_id'])) {
            $this->debug('device notify suppressed for excluded product package');
            return;
        }

        // Client preference gate
        try {
            $pref = Capsule::table('eb_client_notify_prefs')->where('client_id', (int)$svc['client_id'])->value('notify_devices');
            if ($pref !== null && (int)$pref === 0) { $this->debug('device notify suppressed by client preference'); return; }
        } catch (\Throwable $_) { /* ignore */ }

        $recips = RecipientResolver::resolve($svc['service_id'], (string)Config::get('notify_routing','billing'), (string)Config::get('notify_custom_emails',''));
        if (empty($recips)) return;

        $subject = 'Device added: ' . ($payload['FriendlyName'] ?? $deviceId);
        // Upsert grace record for device
        $grace = $this->upsertGrace($pdo, $svc, $username, 'device', $deviceId, (int)Config::get('grace_days_devices', 0), 'ws');

        // Suppress first-device email: only notify when active devices > 1
        $activeDevices = 0;
        try {
            $activeDevices = (int)Capsule::table('comet_devices')->where('username', $username)->whereNull('revoked_at')->count();
        } catch (\Throwable $e) {
            try { $st = $pdo->prepare("SELECT COUNT(*) FROM comet_devices WHERE username=? AND revoked_at IS NULL"); $st->execute([$username]); $activeDevices = (int)$st->fetchColumn(); } catch (\Throwable $_) { $activeDevices = 0; }
        }
        if ($activeDevices <= 1) {
            if (getenv('EB_WS_DEBUG') === '1') { error_log("[{$profile}] notify:onDeviceRegistered suppressed (first device), count={$activeDevices}"); }
            return;
        }

        $rowId = IdempotencyStore::reserve($username, 'device', 'device:' . $deviceId, [
            'service_id' => $svc['service_id'],
            'client_id' => $svc['client_id'],
            'template' => Config::templateName('tpl_device_added'),
            'subject' => $subject,
            'recipients' => implode(',', $recips),
            'merge_json' => json_encode([
                'username'=>$username,
                'device_id'=>$deviceId,
                'device_name'=>($payload['FriendlyName'] ?? ''),
                'service_id'=>$svc['service_id'],
                'grace_first_seen_at'=>$grace['first_seen_at'] ?? null,
                'grace_days'=>$grace['grace_days'] ?? 0,
                'grace_expires_at'=>$grace['grace_expires_at'] ?? null,
            ], JSON_UNESCAPED_SLASHES),
        ], $pdo);
        if ($rowId === null) {
            if (getenv('EB_WS_DEBUG') === '1') { error_log("[{$profile}] notify:onDeviceRegistered reserve skipped (duplicate or DB error)"); }
            return; // already sent or reserve failed
        }

        try {
            $resp = TemplateRenderer::send('tpl_device_added', [
                'subject' => $subject,
                'username' => $username,
                'service_id' => $svc['service_id'],
                'client_id' => $svc['client_id'],
                'device_id' => $deviceId,
                'device_name' => ($payload['FriendlyName'] ?? ''),
                'grace_first_seen_at' => $grace['first_seen_at'] ?? '',
                'grace_days' => $grace['grace_days'] ?? 0,
                'grace_expires_at' => $grace['grace_expires_at'] ?? '',
                'grace_expires_in_days' => $this->daysUntil($grace['grace_expires_at'] ?? null),
                'recipients' => implode(',', $recips),
            ]);
            if (getenv('EB_WS_DEBUG') === '1') { error_log('[notify] SendEmail device_added resp=' . json_encode($resp)); }
            $emailLogId = (int)($resp['id'] ?? 0);
            if ($emailLogId > 0) { IdempotencyStore::attachEmailLog($rowId, $emailLogId, $pdo); }
            IdempotencyStore::markSent($rowId, $emailLogId > 0 ? $emailLogId : null, $pdo);
        } catch (\Throwable $e) {
            IdempotencyStore::markFailed($rowId, $e->getMessage(), $pdo);
        }
    }

    public function onAccountProfileUpdated(PDO $pdo, string $profile, string $username): void
    {
        if (!Config::bool('notify_addons', true)) return;
        $svc = $this->serviceForUsername($pdo, $username);
        if (!$svc) return;
        // Client preference gate
        try { $pref = Capsule::table('eb_client_notify_prefs')->where('client_id', (int)$svc['client_id'])->value('notify_addons'); if ($pref !== null && (int)$pref === 0) { $this->debug('addon notify suppressed by client preference'); return; } } catch (\Throwable $_) {}
        // Only these add-ons are gated by usage > billed qty
        $addons = [
            91 => 'disk_image',
            60 => 'm365_accounts',
            97 => 'hyperv_vm',
            102 => 'proxmox_vm',
            99 => 'vmware_vm',
        ];
        // Engine mapping for usage counting
        $engineMap = [
            'disk_image' => 'engine1/windisk',
            'hyperv_vm'  => 'engine1/hyperv',
            'vmware_vm'  => 'engine1/vmware',
            'm365_accounts' => 'engine1/winmsofficemail',
            'proxmox_vm' => 'engine1/proxmox',
        ];
        foreach ($addons as $configId => $code) {
            $billedQty = (int)Capsule::table('tblhostingconfigoptions')
                ->where('relid', $svc['service_id'])
                ->where('configid', (int)$configId)
                ->value('qty');
            $engine = $engineMap[$code] ?? null;
            $usedUnits = 0;
            if ($engine === 'engine1/windisk') {
                // Count distinct owner_device using Disk Image
                $usedUnits = (int)Capsule::table('comet_items')
                    ->where('username', $username)
                    ->where('type', $engine)
                    ->distinct()->count('owner_device');
            } elseif ($engine) {
                // Count items for VM or M365 engines
                $usedUnits = (int)Capsule::table('comet_items')
                    ->where('username', $username)
                    ->where('type', $engine)
                    ->count();
            }
            $this->debug("addon code={$code} usedUnits={$usedUnits} billedQty={$billedQty}");
            if ($usedUnits <= $billedQty) { continue; }

            $recips = RecipientResolver::resolve($svc['service_id'], (string)Config::get('notify_routing','billing'), (string)Config::get('notify_custom_emails',''));
            if (empty($recips)) { $this->debug('addon: no recipients; skip'); continue; }
            $subject = 'Add-on enabled: ' . str_replace('_',' ', ucwords($code));
            $key = 'addon:' . $code . '@units_' . $usedUnits;
            $rowId = IdempotencyStore::reserve($username, 'addon', $key, [
                'service_id' => $svc['service_id'],
                'client_id' => $svc['client_id'],
                'template' => (string)Config::get('tpl_addon_enabled',''),
                'subject' => $subject,
                'recipients' => implode(',', $recips),
                'merge_json' => json_encode([
                    'username'=>$username,
                    'service_id'=>$svc['service_id'],
                    'addon_code'=>$code,
                    'used_units'=>$usedUnits,
                    'billed_qty'=>$billedQty,
                    'billing_term'=>$this->billingTermLabelForService($svc['service_id']),
                ], JSON_UNESCAPED_SLASHES),
            ]);
            if ($rowId === null) { $this->debug('addon: reserve returned null (already sent); skip'); continue; }
            try {
                $resp = TemplateRenderer::send('tpl_addon_enabled', [
                    'subject' => $subject,
                    'username' => $username,
                    'service_id' => $svc['service_id'],
                    'client_id' => $svc['client_id'],
                    'addon_code' => $code,
                    'billing_term' => $this->billingTermLabelForService($svc['service_id']),
                    'recipients' => implode(',', $recips),
                ]);
                $emailLogId = (int)($resp['id'] ?? 0);
                if ($emailLogId > 0) { IdempotencyStore::attachEmailLog($rowId, $emailLogId, $pdo); }
                IdempotencyStore::markSent($rowId, $emailLogId > 0 ? $emailLogId : null, $pdo);
            } catch (\Throwable $e) {
                IdempotencyStore::markFailed($rowId, $e->getMessage(), $pdo);
            }
        }
    }

    public function onBackupCompleted(PDO $pdo, string $profile, string $username): void
    {
        if (!Config::bool('notify_storage', true)) return;
		$this->debug("onBackupCompleted username={$username}");
        $this->scanStorageForUser($pdo, $username);
    }

    public function scanStorageForUser(PDO $pdo, string $username): void
    {
		$this->debug("scanStorageForUser enter username={$username}");
		$svc = $this->serviceForUsername($pdo, $username);
        if (!$svc) return;
		$this->debug("service mapped service_id={$svc['service_id']} client_id={$svc['client_id']}");

        // Suppress storage notifications for excluded product packages
        if ($this->isStorageDeviceNotificationsDisabled((int)$svc['service_id'])) {
            $this->debug('storage notify suppressed for excluded product package');
            return;
        }

        // Client preference gate
        try { $pref = Capsule::table('eb_client_notify_prefs')->where('client_id', (int)$svc['client_id'])->value('notify_storage'); if ($pref !== null && (int)$pref === 0) { $this->debug('storage notify suppressed by client preference'); return; } } catch (\Throwable $_) {}

		// Paid TiB from configurable option Cloud Storage cid=67
		$paidTiB = (int)Capsule::table('tblhostingconfigoptions')
			->where('configid', 67)
			->where('relid', $svc['service_id'])
			->value('qty');
        if ($paidTiB < 0) { $paidTiB = 0; }
		$this->debug("paidTiB={$paidTiB}");

        // Usage from comet_vaults type 1000,1003
		$bytes = (int)Capsule::table('comet_vaults')
            ->where('username', $username)
            ->where('is_active', 1)
            ->whereIn('type', [1000,1003])
            ->sum('total_bytes');
		$usageTiB = StorageThresholds::bytesToTiB((float)$bytes);
		$this->debug("bytes={$bytes} usageTiB=" . number_format($usageTiB, 4));

		$percent = (int)Config::get('notify_threshold_percent', '90');
        if ($percent <= 0 || $percent > 100) { $percent = 90; }
		$this->debug("threshold_percent={$percent}");

        // Check milestones and leap
		$milestones = StorageThresholds::milestonesToCheck(max(1,$paidTiB), $usageTiB);
        $crossed = [];
        foreach ($milestones as $k) {
            $thr = StorageThresholds::thresholdTiBForK($k, $percent);
            if ($usageTiB >= $thr) { $crossed[] = $k; }
        }
		$this->debug('milestones_to_check=[' . implode(',', $milestones) . '] crossed=[' . implode(',', $crossed) . ']');
		if (empty($crossed)) { $this->debug('no milestones crossed; exiting'); return; }

        // If usage >= paidTiB or crossed >1, send overage and mark all
		$sendOverage = ($usageTiB >= (float)$paidTiB) || (count($crossed) > 1);
		$this->debug('sendOverage=' . ($sendOverage ? '1' : '0'));
		$recips = RecipientResolver::resolve($svc['service_id'], (string)Config::get('notify_routing','billing'), (string)Config::get('notify_custom_emails',''));
		$this->debug('recipients_count=' . count($recips));
		if (empty($recips)) { $this->debug('no recipients resolved; exiting'); return; }

        if ($sendOverage) {
			$subject = 'Storage overage: ' . number_format($usageTiB, 2) . ' TiB (paid ' . (int)$paidTiB . ' TiB)';
            // Projected cost if auto-scaled now
			$optionRelid = (int)Capsule::table('tblhostingconfigoptions')
                ->where('relid', $svc['service_id'])
				->where('configid', 67)
                ->value('optionid');
            $requiredTiB = (int)max(0, ceil($usageTiB));
            $deltaTiB = max(0, $requiredTiB - $paidTiB);
            $projected = $optionRelid ? PricingCalculator::priceDeltaForConfigOption($svc['service_id'], $optionRelid, $deltaTiB) : 0.0;
            foreach ($crossed as $k) {
                $key = 'storage:tib_' . $k;
                $rowId = IdempotencyStore::reserve($username, 'storage', $key, [
                    'service_id' => $svc['service_id'],
                    'client_id' => $svc['client_id'],
                    'template' => (string)Config::get('tpl_storage_overage',''),
                    'subject' => $subject,
                    'recipients' => implode(',', $recips),
                    'merge_json' => json_encode([
                        'username'=>$username,
                        'service_id'=>$svc['service_id'],
                        'paid_tib'=>$paidTiB,
                        'usage_tib'=>$usageTiB,
                        'threshold_k'=>$k,
                        'projected_monthly_delta'=>$projected,
                        'projected_tib'=>$requiredTiB,
                        'billing_term'=>$this->billingTermLabelForService($svc['service_id']),
                    ], JSON_UNESCAPED_SLASHES),
                ]);
				$this->debug("overage reserve key={$key} rowId=" . ($rowId ?? 0));
				if ($rowId === null) { $this->debug('overage reserve returned null (already sent); skipping this K'); continue; }
                try {
                    $resp = TemplateRenderer::send('tpl_storage_overage', [
                        'subject' => $subject,
                        'username' => $username,
                        'service_id' => $svc['service_id'],
                'client_id' => $svc['client_id'],
                        'paid_tib' => $paidTiB,
                        'current_usage_tib' => $usageTiB,
                        'threshold_k_tib' => $k,
                        'projected_monthly_delta' => $projected,
                        'projected_tib' => $requiredTiB,
                        'billing_term' => $this->billingTermLabelForService($svc['service_id']),
                        'recipients' => implode(',', $recips),
                    ]);
					$emailLogId = (int)($resp['id'] ?? 0);
                    if ($emailLogId > 0) { IdempotencyStore::attachEmailLog($rowId, $emailLogId, $pdo); }
					$this->debug("overage sent key={$key} emailLogId={$emailLogId}");
                    IdempotencyStore::markSent($rowId, $emailLogId > 0 ? $emailLogId : null, $pdo);
				} catch (\Throwable $e) { $this->debug('overage send error: ' . $e->getMessage()); IdempotencyStore::markFailed($rowId, $e->getMessage(), $pdo); }
            }
            return;
        }

        // Otherwise send a single storage_warning for the highest crossed K not yet sent
        rsort($crossed);
        $k = $crossed[0];
        $key = 'storage:tib_' . $k;
        $subject = 'Storage warning: ' . number_format($usageTiB, 2) . ' TiB vs ' . $k . ' TiB milestone';
		$optionRelid = (int)Capsule::table('tblhostingconfigoptions')
            ->where('relid', $svc['service_id'])
			->where('configid', 67)
            ->value('optionid');
        // For a warning at milestone K, project the NEXT tier (K+1), not ceil(usage)
        $projectedTiB = (int)($k + 1);
        $deltaTiB = max(0, $projectedTiB - $paidTiB);
        $projected = $optionRelid ? PricingCalculator::priceDeltaForConfigOption($svc['service_id'], $optionRelid, $deltaTiB) : 0.0;

        $rowId = IdempotencyStore::reserve($username, 'storage', $key, [
            'service_id' => $svc['service_id'],
            'client_id' => $svc['client_id'],
            'template' => (string)Config::get('tpl_storage_warning',''),
            'subject' => $subject,
            'recipients' => implode(',', $recips),
            'merge_json' => json_encode([
                'username'=>$username,
                'service_id'=>$svc['service_id'],
                'paid_tib'=>$paidTiB,
                'usage_tib'=>$usageTiB,
                'threshold_k'=>$k,
                'projected_monthly_delta'=>$projected,
                'projected_tib'=>$projectedTiB,
                'billing_term'=>$this->billingTermLabelForService($svc['service_id']),
            ], JSON_UNESCAPED_SLASHES),
        ]);
		$this->debug("warning reserve key={$key} rowId=" . ($rowId ?? 0));
		if ($rowId === null) { $this->debug('warning reserve returned null (already sent); exiting'); return; }
        try {
            $resp = TemplateRenderer::send('tpl_storage_warning', [
                'subject' => $subject,
                'username' => $username,
                'service_id' => $svc['service_id'],
                'client_id' => $svc['client_id'],
                'paid_tib' => $paidTiB,
                'current_usage_tib' => $usageTiB,
                'threshold_k_tib' => $k,
                'projected_monthly_delta' => $projected,
                'projected_tib' => $projectedTiB,
                'billing_term' => $this->billingTermLabelForService($svc['service_id']),
                'recipients' => implode(',', $recips),
            ]);
			$emailLogId = (int)($resp['id'] ?? 0);
            if ($emailLogId > 0) { IdempotencyStore::attachEmailLog($rowId, $emailLogId, $pdo); }
			$this->debug("warning sent key={$key} emailLogId={$emailLogId}");
            IdempotencyStore::markSent($rowId, $emailLogId > 0 ? $emailLogId : null, $pdo);
		} catch (\Throwable $e) { $this->debug('warning send error: ' . $e->getMessage()); IdempotencyStore::markFailed($rowId, $e->getMessage(), $pdo); }
    }

    private function upsertGrace(PDO $pdo, array $svc, string $username, string $category, string $entityKey, int $graceDays, string $source): array
    {
        $now = date('Y-m-d H:i:s');
        $expires = $graceDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$graceDays} days")) : $now;
        try {
            $row = Capsule::table('eb_billing_grace')->where('username',$username)->where('category',$category)->where('entity_key',$entityKey)->first();
            if (!$row) {
                Capsule::table('eb_billing_grace')->insert([
                    'service_id' => (int)($svc['service_id'] ?? 0),
                    'client_id' => (int)($svc['client_id'] ?? 0),
                    'username' => $username,
                    'category' => $category,
                    'entity_key' => $entityKey,
                    'first_seen_at' => $now,
                    'grace_days' => $graceDays,
                    'grace_expires_at' => $expires,
                    'source' => $source,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                return ['first_seen_at'=>$now,'grace_days'=>$graceDays,'grace_expires_at'=>$expires];
            }
            // Backfill IDs if missing
            $updates = [];
            if ((int)$row->service_id === 0 && (int)($svc['service_id'] ?? 0) > 0) { $updates['service_id'] = (int)$svc['service_id']; }
            if ((int)$row->client_id === 0 && (int)($svc['client_id'] ?? 0) > 0) { $updates['client_id'] = (int)$svc['client_id']; }
            if ($updates) { $updates['updated_at'] = $now; Capsule::table('eb_billing_grace')->where('id',$row->id)->update($updates); }
            return ['first_seen_at'=>(string)$row->first_seen_at,'grace_days'=>(int)$row->grace_days,'grace_expires_at'=>(string)$row->grace_expires_at];
        } catch (\Throwable $e) {
            // PDO fallback
            try {
                $sel = $pdo->prepare("SELECT id, service_id, client_id, first_seen_at, grace_days, grace_expires_at FROM eb_billing_grace WHERE username=? AND category=? AND entity_key=? LIMIT 1");
                $sel->execute([$username,$category,$entityKey]);
                $r = $sel->fetch(\PDO::FETCH_ASSOC);
                if (!$r) {
                    $ins = $pdo->prepare("INSERT INTO eb_billing_grace (service_id, client_id, username, category, entity_key, first_seen_at, grace_days, grace_expires_at, source, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                    $ins->execute([(int)($svc['service_id'] ?? 0),(int)($svc['client_id'] ?? 0),$username,$category,$entityKey,$now,$graceDays,$expires,$source,$now,$now]);
                    return ['first_seen_at'=>$now,'grace_days'=>$graceDays,'grace_expires_at'=>$expires];
                }
                return ['first_seen_at'=>(string)$r['first_seen_at'],'grace_days'=>(int)$r['grace_days'],'grace_expires_at'=>(string)$r['grace_expires_at']];
            } catch (\Throwable $_) {
                return ['first_seen_at'=>$now,'grace_days'=>$graceDays,'grace_expires_at'=>$expires];
            }
        }
    }

    private function daysUntil(?string $expiresAt): int
    {
        if (!$expiresAt) return 0;
        $ts = strtotime($expiresAt); if ($ts === false) return 0;
        $delta = $ts - time();
        return $delta > 0 ? (int)ceil($delta / 86400) : 0;
    }

    /**
     * Resolve active service and client for username, suppressing suspended/canceled.
     * @return array{service_id:int,client_id:int}|null
     */
    private function serviceForUsername(PDO $pdo, string $username): ?array
    {
        // 1) Capsule path (preferred)
        try {
            $row = Capsule::table('tblhosting')
                ->where('username', $username)
                ->where('domainstatus', 'Active')
                ->select('id','userid')
                ->first();
            if ($row) { return ['service_id'=>(int)$row->id, 'client_id'=>(int)$row->userid]; }
        } catch (\Throwable $e) { /* ignore */ }

        // 2) PDO fallback with robust matching (case/collation independent)
        try {
            // Exact case-sensitive Active
            $stmt = $pdo->prepare("SELECT id, userid FROM tblhosting WHERE BINARY TRIM(username)=TRIM(?) AND domainstatus='Active' ORDER BY id ASC LIMIT 1");
            $stmt->execute([$username]);
            $r = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($r) { return ['service_id'=>(int)$r['id'], 'client_id'=>(int)$r['userid']]; }

            // Exact case-sensitive any status (prefer Active via ORDER BY)
            $stmt = $pdo->prepare("SELECT id, userid, (domainstatus='Active') AS is_active FROM tblhosting WHERE BINARY TRIM(username)=TRIM(?) ORDER BY is_active DESC, id ASC LIMIT 1");
            $stmt->execute([$username]);
            $r = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($r && (int)$r['is_active'] === 1) { return ['service_id'=>(int)$r['id'], 'client_id'=>(int)$r['userid']]; }

            // Case-insensitive, prefer Active
            $stmt = $pdo->prepare("SELECT id, userid, (domainstatus='Active') AS is_active FROM tblhosting WHERE LOWER(username)=LOWER(?) ORDER BY is_active DESC, id ASC LIMIT 1");
            $stmt->execute([$username]);
            $r = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($r && (int)$r['is_active'] === 1) { return ['service_id'=>(int)$r['id'], 'client_id'=>(int)$r['userid']]; }
        } catch (\Throwable $e) { /* ignore */ }

        return null;
    }

    private function isAddonEnabled(int $serviceId, int $configId): bool
    {
        try {
            $qty = Capsule::table('tblhostingconfigoptions')
                ->where('relid', $serviceId)
                ->where('configid', $configId)
                ->value('qty');
            return (int)$qty > 0;
        } catch (\Throwable $e) { return false; }
    }
}


