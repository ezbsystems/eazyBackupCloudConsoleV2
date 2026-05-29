<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Provision\E3CloudBackupProductBootstrap;

/**
 * Lifecycle state machine for the e3 Cloud Backup trial.
 *
 * States (s3_cloudbackup_trial_state.status):
 *   trialing               - initial state at provisioning
 *   converted              - trial ended and a payment method is on file
 *   suspended_no_payment   - trial ended with no payment method; service is
 *                            suspended in WHMCS (but data is preserved)
 *   cancelled              - admin manually cancelled a suspended customer
 *
 * Transitions:
 *   trialing -> converted              (auto, when trial_ends_at <= today AND card on file)
 *   trialing -> suspended_no_payment   (auto, when trial_ends_at <= today AND no card)
 *   suspended_no_payment -> converted  (auto, when customer adds a card)
 *   suspended_no_payment -> cancelled  (admin action)
 *
 * All operations are idempotent and log a row in `last_evaluated_at` /
 * `notes` so the trial state table doubles as an audit log.
 */
class E3CloudBackupTrial
{
    private const MODULE = 'cloudstorage';

    /**
     * Insert a new trial-state row at provisioning time. Idempotent: if a row
     * already exists for this service it is left alone.
     */
    public static function startTrial(int $serviceId, int $clientId, ?int $trialDays = null): void
    {
        if ($serviceId <= 0 || $clientId <= 0) {
            return;
        }
        $days = $trialDays !== null ? max(0, (int) $trialDays) : (int) self::getSetting('e3cb_trial_days', 30);
        if ($days <= 0) {
            $days = 30;
        }
        try {
            $exists = Capsule::table('s3_cloudbackup_trial_state')->where('service_id', $serviceId)->exists();
            if ($exists) {
                return;
            }
            $now = date('Y-m-d H:i:s');
            $ends = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            Capsule::table('s3_cloudbackup_trial_state')->insert([
                'service_id'        => $serviceId,
                'client_id'         => $clientId,
                'trial_started_at'  => $now,
                'trial_ends_at'     => $ends,
                'status'            => 'trialing',
                'last_evaluated_at' => $now,
                'notes'             => "Trial started ({$days} days).",
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            self::log('trial_started', [
                'service_id' => $serviceId,
                'client_id'  => $clientId,
                'days'       => $days,
                'ends_at'    => $ends,
            ], 'ok');
        } catch (\Throwable $e) {
            self::log('trial_start_fail', ['service_id' => $serviceId], $e->getMessage());
        }
    }

    /**
     * Daily evaluation pass: every trialing row whose trial_ends_at is in the
     * past, plus every suspended_no_payment row (to catch self-service
     * reactivation once a card has been added).
     *
     * @return array{evaluated:int, converted:int, suspended:int, errors:int}
     */
    public static function evaluateAll(): array
    {
        $result = ['evaluated' => 0, 'converted' => 0, 'suspended' => 0, 'errors' => 0];

        try {
            $rows = Capsule::table('s3_cloudbackup_trial_state')
                ->whereIn('status', ['trialing', 'suspended_no_payment'])
                ->get();
        } catch (\Throwable $e) {
            self::log('evaluate_all_query_fail', [], $e->getMessage());
            return $result;
        }

        $today = date('Y-m-d');
        foreach ($rows as $row) {
            $result['evaluated']++;
            try {
                $serviceId = (int) $row->service_id;
                $clientId = (int) $row->client_id;
                $status = (string) $row->status;
                $trialEnds = (string) ($row->trial_ends_at ?? '');
                $trialOver = $trialEnds !== '' && substr($trialEnds, 0, 10) <= $today;

                if ($status === 'trialing') {
                    if (!$trialOver) {
                        // Still in trial - just stamp evaluation time.
                        self::touch($serviceId);
                        continue;
                    }
                    if (self::clientHasCard($clientId)) {
                        self::convert($serviceId, $clientId, 'trial_end_with_card');
                        $result['converted']++;
                    } else {
                        self::suspend($serviceId, $clientId, 'trial_end_no_card');
                        $result['suspended']++;
                    }
                } elseif ($status === 'suspended_no_payment') {
                    if (self::clientHasCard($clientId)) {
                        self::convert($serviceId, $clientId, 'reactivate_card_added');
                        $result['converted']++;
                    } else {
                        self::touch($serviceId);
                    }
                }
            } catch (\Throwable $e) {
                $result['errors']++;
                self::log('evaluate_row_fail', [
                    'service_id' => $row->service_id ?? null,
                ], $e->getMessage());
            }
        }
        return $result;
    }

    /**
     * Force one specific service through the evaluation. Used by manual admin
     * actions ("Convert manually") and by the customer-facing reactivation API.
     */
    public static function evaluateService(int $serviceId, ?string $forceTo = null): string
    {
        try {
            $row = Capsule::table('s3_cloudbackup_trial_state')->where('service_id', $serviceId)->first();
            if (!$row) {
                return 'no_trial_state';
            }
            $clientId = (int) $row->client_id;
            if ($forceTo === 'converted') {
                self::convert($serviceId, $clientId, 'admin_force_convert');
                return 'converted';
            }
            if ($forceTo === 'cancelled') {
                self::cancel($serviceId, $clientId, 'admin_force_cancel');
                return 'cancelled';
            }
            if ($forceTo === 'suspended_no_payment') {
                self::suspend($serviceId, $clientId, 'admin_force_suspend');
                return 'suspended_no_payment';
            }
            // Run the regular daily logic for just this service.
            $oneRow = Capsule::table('s3_cloudbackup_trial_state')->where('service_id', $serviceId)->first();
            if (!$oneRow) {
                return 'no_trial_state';
            }
            $today = date('Y-m-d');
            $status = (string) $oneRow->status;
            $trialEnds = (string) ($oneRow->trial_ends_at ?? '');
            $trialOver = $trialEnds !== '' && substr($trialEnds, 0, 10) <= $today;

            if ($status === 'trialing' && $trialOver) {
                if (self::clientHasCard($clientId)) {
                    self::convert($serviceId, $clientId, 'trial_end_with_card');
                    return 'converted';
                }
                self::suspend($serviceId, $clientId, 'trial_end_no_card');
                return 'suspended_no_payment';
            }
            if ($status === 'suspended_no_payment' && self::clientHasCard($clientId)) {
                self::convert($serviceId, $clientId, 'reactivate_card_added');
                return 'converted';
            }
            self::touch($serviceId);
            return $status;
        } catch (\Throwable $e) {
            self::log('evaluate_service_fail', ['service_id' => $serviceId], $e->getMessage());
            return 'error';
        }
    }

    /**
     * Transition to converted: set status, anchor next due dates to today so
     * the next WHMCS billing cycle generates the first real invoice. Both
     * the e3 Cloud Backup and the e3 Object Storage services are anchored.
     */
    public static function convert(int $serviceId, int $clientId, string $reason): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            Capsule::table('s3_cloudbackup_trial_state')
                ->where('service_id', $serviceId)
                ->update([
                    'status'                  => 'converted',
                    'converted_at'            => $now,
                    'payment_method_seen_at'  => $now,
                    'last_evaluated_at'       => $now,
                    'notes'                   => 'Converted: ' . $reason,
                    'updated_at'              => $now,
                ]);

            // Anchor billing day to today on BOTH products (cloud backup + cloud storage).
            self::anchorNextDueDate($serviceId);
            $storageSvcId = self::resolveStorageServiceId($clientId);
            if ($storageSvcId > 0) {
                self::anchorNextDueDate($storageSvcId);
            }

            // If suspended, un-suspend.
            self::setServiceStatus($serviceId, 'Active');
            if ($storageSvcId > 0) {
                self::setServiceStatus($storageSvcId, 'Active');
            }

            self::log('trial_converted', [
                'service_id'  => $serviceId,
                'client_id'   => $clientId,
                'storage_svc' => $storageSvcId,
                'reason'      => $reason,
            ], 'ok');
        } catch (\Throwable $e) {
            self::log('trial_convert_fail', ['service_id' => $serviceId], $e->getMessage());
        }
    }

    /**
     * Transition to suspended_no_payment: keep all data, set domainstatus =
     * Suspended on both products. Sends the configured email (best-effort).
     */
    public static function suspend(int $serviceId, int $clientId, string $reason): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            Capsule::table('s3_cloudbackup_trial_state')
                ->where('service_id', $serviceId)
                ->update([
                    'status'             => 'suspended_no_payment',
                    'suspended_at'       => $now,
                    'last_evaluated_at'  => $now,
                    'notes'              => 'Suspended: ' . $reason,
                    'updated_at'         => $now,
                ]);

            self::setServiceStatus($serviceId, 'Suspended', 'e3 Cloud Backup trial ended without a payment method on file.');
            $storageSvcId = self::resolveStorageServiceId($clientId);
            if ($storageSvcId > 0) {
                self::setServiceStatus($storageSvcId, 'Suspended', 'e3 Cloud Storage suspended (linked e3 Cloud Backup trial ended without payment method).');
            }

            self::log('trial_suspended', [
                'service_id'  => $serviceId,
                'client_id'   => $clientId,
                'storage_svc' => $storageSvcId,
                'reason'      => $reason,
            ], 'ok');

            // Send notification email (uses WHMCS 'cloudbackup_trial_suspended'
            // template if one is configured; non-fatal if missing).
            self::sendSuspensionEmail($clientId, $serviceId);
        } catch (\Throwable $e) {
            self::log('trial_suspend_fail', ['service_id' => $serviceId], $e->getMessage());
        }
    }

    /**
     * Final cancellation: terminate both services and mark cancelled. Admin
     * action only.
     */
    public static function cancel(int $serviceId, int $clientId, string $reason): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            Capsule::table('s3_cloudbackup_trial_state')
                ->where('service_id', $serviceId)
                ->update([
                    'status'            => 'cancelled',
                    'last_evaluated_at' => $now,
                    'notes'             => 'Cancelled: ' . $reason,
                    'updated_at'        => $now,
                ]);
            self::setServiceStatus($serviceId, 'Cancelled', 'Admin cancellation: ' . $reason);
            $storageSvcId = self::resolveStorageServiceId($clientId);
            if ($storageSvcId > 0) {
                self::setServiceStatus($storageSvcId, 'Cancelled', 'Admin cancellation: ' . $reason);
            }
            self::log('trial_cancelled', [
                'service_id' => $serviceId,
                'client_id'  => $clientId,
                'reason'     => $reason,
            ], 'ok');
        } catch (\Throwable $e) {
            self::log('trial_cancel_fail', ['service_id' => $serviceId], $e->getMessage());
        }
    }

    /**
     * Does the client have a Stripe (or other) card on file?
     *
     * Reuses the logic already proven in
     * accounts/modules/addons/cloudstorage/api/setpassword_and_provision.php.
     */
    public static function clientHasCard(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }
        try {
            if (Capsule::schema()->hasTable('tblpaymethods')) {
                $exists = Capsule::table('tblpaymethods')
                    ->where('userid', $clientId)
                    ->whereNull('deleted_at')
                    ->whereIn('payment_type', ['CreditCard', 'RemoteCreditCard'])
                    ->exists();
                if ($exists) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
        }
        try {
            $resp = localAPI('GetPayMethods', ['clientid' => $clientId]);
            if (($resp['result'] ?? '') === 'success' && !empty($resp['paymethods']) && is_array($resp['paymethods'])) {
                foreach ($resp['paymethods'] as $pm) {
                    $ptype = strtolower((string) ($pm['payment_type'] ?? ''));
                    if ($ptype === 'creditcard' || $ptype === 'remotecreditcard') {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
        }
        return false;
    }

    /**
     * Look up the client's e3 Object Storage service ID. Returns 0 if none.
     */
    public static function resolveStorageServiceId(int $clientId): int
    {
        try {
            $pid = (int) self::getSetting('pid_cloud_storage', 0);
            if ($pid <= 0) {
                return 0;
            }
            return (int) Capsule::table('tblhosting')
                ->where('userid', $clientId)
                ->where('packageid', $pid)
                ->whereIn('domainstatus', ['Active', 'Suspended'])
                ->orderBy('id', 'desc')
                ->value('id');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Set tblhosting.domainstatus and write a small admin note. Non-destructive.
     */
    public static function setServiceStatus(int $serviceId, string $status, ?string $note = null): void
    {
        try {
            $update = ['domainstatus' => $status];
            if ($status === 'Suspended' && $note !== null) {
                $update['suspendreason'] = $note;
            } elseif ($status === 'Active') {
                $update['suspendreason'] = '';
            }
            Capsule::table('tblhosting')->where('id', $serviceId)->update($update);
        } catch (\Throwable $e) {
            self::log('set_service_status_fail', ['service_id' => $serviceId, 'status' => $status], $e->getMessage());
        }
    }

    /**
     * Move nextduedate / nextinvoicedate forward to today so the next billing
     * cron generates a fresh invoice.
     */
    private static function anchorNextDueDate(int $serviceId): void
    {
        try {
            $today = date('Y-m-d');
            Capsule::table('tblhosting')->where('id', $serviceId)->update([
                'nextduedate'      => $today,
                'nextinvoicedate'  => $today,
            ]);
        } catch (\Throwable $e) {
            self::log('anchor_due_fail', ['service_id' => $serviceId], $e->getMessage());
        }
    }

    private static function touch(int $serviceId): void
    {
        try {
            Capsule::table('s3_cloudbackup_trial_state')->where('service_id', $serviceId)->update([
                'last_evaluated_at' => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Send the trial-suspended email to the customer (best-effort). The hook
     * uses the addon setting `cloudbackup_trial_suspended_email_template` if
     * present; otherwise the call is a no-op.
     */
    private static function sendSuspensionEmail(int $clientId, int $serviceId): void
    {
        $templateName = (string) self::getSetting('cloudbackup_trial_suspended_email_template', '');
        if ($templateName === '') {
            return;
        }
        try {
            // Resolve template by id or name.
            $templateRow = null;
            if (is_numeric($templateName)) {
                $templateRow = Capsule::table('tblemailtemplates')->where('id', (int) $templateName)->first();
            }
            if (!$templateRow) {
                $templateRow = Capsule::table('tblemailtemplates')->where('name', $templateName)->first();
            }
            if (!$templateRow) {
                return;
            }
            $args = [
                'messagename' => $templateRow->name,
                'id'          => $clientId,
                'customtype'  => 'product',
                'customsubject' => $templateRow->subject ?? null,
                'customvars'  => base64_encode(serialize([
                    'service_id' => $serviceId,
                ])),
            ];
            localAPI('SendEmail', $args);
        } catch (\Throwable $e) {
            self::log('suspension_email_fail', ['client_id' => $clientId], $e->getMessage());
        }
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

    private static function log(string $event, array $context, $payload): void
    {
        try {
            logModuleCall(self::MODULE, $event, $context, $payload, [], []);
        } catch (\Throwable $_) {
        }
    }
}
