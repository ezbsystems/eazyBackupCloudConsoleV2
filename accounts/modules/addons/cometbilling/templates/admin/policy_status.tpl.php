<?php
use CometBilling\PolicyStatusReport;

$report = PolicyStatusReport::report();
$groups = $report['groups'] ?? [];
$asPulledAt = $report['active_services_pulled_at'];
$serverErrors = $report['server_errors'];
$csvBase = 'addonmodules.php?module=cometbilling&action=policy_status_csv';

function policyStatusFormatJobTime(int $ts): string
{
    return $ts > 0 ? gmdate('Y-m-d H:i:s', $ts) . ' UTC' : '—';
}

function policyStatusStatusClass(string $label): string
{
    if ($label === 'success') {
        return 'cb-status-ok';
    }
    if ($label === 'warning') {
        return 'cb-status-warn';
    }
    if (in_array($label, ['error', 'timeout', 'quota', 'missed', 'unknown'], true)) {
        return 'cb-status-error';
    }
    return '';
}

function policyStatusFormatMoney(float $amount): string
{
    return $amount > 0 ? '$' . number_format($amount, 2) : '—';
}

function policyStatusBhDeviceClass(string $status): string
{
    if ($status === 'phantom') {
        return 'cb-bh-phantom';
    }
    if ($status === 'verified') {
        return 'cb-bh-verified';
    }
    if ($status === 'bh_only') {
        return 'cb-bh-bh-only';
    }
    return '';
}

function policyStatusFormatBhDevice(array $row): string
{
    $amount = (float) ($row['bh_device_amount'] ?? 0);
    $status = (string) ($row['bh_device_status'] ?? 'none');
    $last = (string) ($row['bh_device_last'] ?? '');

    if ($status === 'none' && $amount <= 0) {
        return '—';
    }

    $money = '$' . number_format($amount, 2);
    $label = htmlspecialchars($status);
    if ($status === 'verified' && $last !== '') {
        $label .= ' <span class="cb-muted">(last ' . htmlspecialchars($last) . ')</span>';
    }
    return '<span class="' . policyStatusBhDeviceClass($status) . '">' . $money . ' ' . $label . '</span>';
}

function policyStatusCsvUrl(string $csvBase, string $groupKey, string $section): string
{
    return $csvBase
        . '&group=' . urlencode($groupKey)
        . '&section=' . urlencode($section);
}
?>
<style>
.cb-policy-status { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-box { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; margin: 20px 0; }
.cb-box h4 { margin: 0 0 15px 0; }
.cb-box h3.cb-group-title { margin: 0 0 8px 0; font-size: 18px; }
.cb-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin: 15px 0 0; }
.cb-stat { background: #f9fafb; border: 1px solid #e5e5e5; border-radius: 8px; padding: 15px; text-align: center; }
.cb-stat .value { font-size: 22px; font-weight: 700; color: #1a73e8; }
.cb-stat .value.negative { color: #c5221f; }
.cb-stat .value.positive { color: #137333; }
.cb-stat .label { font-size: 12px; color: #666; margin-top: 4px; }
.cb-muted { color: #666; font-size: 12px; margin-top: 10px; }
.cb-status-ok { color: #137333; font-weight: 600; }
.cb-status-warn { color: #e37400; font-weight: 600; }
.cb-status-error { color: #c5221f; font-weight: 600; }
.cb-bh-phantom { color: #c5221f; font-weight: 600; }
.cb-bh-verified { color: #137333; font-weight: 600; }
.cb-bh-bh-only { color: #e37400; font-weight: 600; }
.cb-policy-list { margin: 10px 0 0; padding-left: 20px; }
.cb-section-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 15px; }
.cb-section-head h4 { margin: 0; }
.cb-group-block { border-top: 3px solid #1a73e8; margin-top: 30px; padding-top: 10px; }
</style>

<div class="cb-policy-status">
    <h3>Policy Status</h3>
    <p class="cb-muted">
        Accounts on configured CSW Policy IDs for <strong>Microsoft 365</strong> and <strong>Virtual Server</strong> policies.
        For each policy set: Section A = last-backup warning; Section B = billed warning/error;
        Section C = billed success with device/booster Active Services charges.
        Sections B and C reconcile Active Services device fees against canonical Bill History:
        <strong>phantom</strong> = AS shows a device fee with no BH deduction;
        <strong>verified</strong> = BH has device debits.
    </p>

    <div class="cb-box">
        <h4>Configured Policy Sets</h4>
        <?php foreach (PolicyStatusReport::POLICY_GROUPS as $groupKey => $groupMeta): ?>
        <p style="margin: 12px 0 4px;"><strong><?= htmlspecialchars((string) $groupMeta['label']) ?></strong></p>
        <ul class="cb-policy-list">
            <?php foreach ($groupMeta['policies'] as $serverKey => $policyId): ?>
            <li>
                <strong><?= htmlspecialchars(PolicyStatusReport::SERVER_LABELS[$serverKey] ?? $serverKey) ?></strong>
                (<?= htmlspecialchars($serverKey) ?>):
                <code><?= htmlspecialchars($policyId) ?></code>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endforeach; ?>
        <p class="cb-muted">
            Scanned <?= number_format((int) $report['account_count']) ?> account(s) with a last backup job
            across <?= number_format(count(PolicyStatusReport::SERVER_LABELS)) ?> server(s).
            Active Services snapshot:
            <?= $asPulledAt ? htmlspecialchars((string) $asPulledAt) : '—' ?>.
        </p>
        <?php if (count($serverErrors) > 0): ?>
        <div class="cb-muted" style="margin-top: 15px;">
            <strong>Server errors:</strong>
            <ul style="margin: 8px 0 0; padding-left: 20px;">
                <?php foreach ($serverErrors as $key => $message): ?>
                <li>
                    <?= htmlspecialchars(PolicyStatusReport::SERVER_LABELS[$key] ?? $key) ?>:
                    <?= htmlspecialchars((string) $message) ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <?php foreach ($groups as $groupKey => $group): ?>
        <?php
        $warningAccounts = $group['warning_accounts'] ?? [];
        $billedUnhealthy = $group['billed_unhealthy'] ?? [];
        $billedSuccessful = $group['billed_successful'] ?? [];
        $groupLabel = (string) ($group['label'] ?? $groupKey);
        $csvWarningUrl = policyStatusCsvUrl($csvBase, (string) $groupKey, PolicyStatusReport::SECTION_WARNING);
        $csvUnhealthyUrl = policyStatusCsvUrl($csvBase, (string) $groupKey, PolicyStatusReport::SECTION_BILLED_UNHEALTHY);
        $csvSuccessfulUrl = policyStatusCsvUrl($csvBase, (string) $groupKey, PolicyStatusReport::SECTION_BILLED_SUCCESSFUL);
        $csvHistSummaryUrl = policyStatusCsvUrl($csvBase, (string) $groupKey, PolicyStatusReport::SECTION_HISTORICAL_DEVICE_SUMMARY);
        $csvHistDetailUrl = policyStatusCsvUrl($csvBase, (string) $groupKey, PolicyStatusReport::SECTION_HISTORICAL_DEVICE_DETAIL);
        $historical = $group['historical_device'] ?? [];
        $histSummary = $historical['summary'] ?? [];
        ?>
        <div class="cb-group-block">
            <div class="cb-box">
                <h3 class="cb-group-title"><?= htmlspecialchars($groupLabel) ?></h3>
                <p class="cb-muted" style="margin-top: 0;">
                    <?= number_format((int) ($group['member_count'] ?? 0)) ?> account(s) currently on this policy set;
                    <?= number_format((int) ($group['account_count'] ?? 0)) ?> with a last backup job for Sections A–C.
                </p>
                <ul class="cb-policy-list">
                    <?php foreach (($group['policies'] ?? []) as $serverKey => $policyId): ?>
                    <li>
                        <?= htmlspecialchars(PolicyStatusReport::SERVER_LABELS[$serverKey] ?? $serverKey) ?>:
                        <code><?= htmlspecialchars((string) $policyId) ?></code>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="cb-summary">
                    <div class="cb-stat">
                        <div class="value negative"><?= number_format(count($warningAccounts)) ?></div>
                        <div class="label">Section A — warning</div>
                    </div>
                    <div class="cb-stat">
                        <div class="value negative"><?= number_format(count($billedUnhealthy)) ?></div>
                        <div class="label">Section B — billed unhealthy</div>
                    </div>
                    <div class="cb-stat">
                        <div class="value positive"><?= number_format(count($billedSuccessful)) ?></div>
                        <div class="label">Section C — billed successful</div>
                    </div>
                </div>
            </div>

            <div class="cb-box">
                <div class="cb-section-head">
                    <h4>Section A — Warning Accounts</h4>
                    <a class="btn btn-default btn-sm" href="<?= htmlspecialchars($csvWarningUrl) ?>">Export CSV</a>
                </div>
                <p class="cb-muted">Last backup status exactly <strong>warning</strong> (7001).</p>
                <?php if (count($warningAccounts) === 0): ?>
                <p class="cb-muted">No warning accounts found.</p>
                <?php else: ?>
                <table class="datatable" width="100%">
                    <thead>
                        <tr>
                            <th>Server</th>
                            <th>Policy ID</th>
                            <th>Username</th>
                            <th>Sources (warn/total)</th>
                            <th>Last job (UTC)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($warningAccounts as $row): ?>
                        <?php $statusLabel = (string) ($row['status_label'] ?? ''); ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['server_label'] ?? '')) ?></td>
                            <td><code><?= htmlspecialchars((string) ($row['policy_id'] ?? '')) ?></code></td>
                            <td><?= htmlspecialchars((string) ($row['username'] ?? '')) ?></td>
                            <td><?= number_format((int) ($row['warning_source_count'] ?? 0)) ?> / <?= number_format((int) ($row['source_count'] ?? 0)) ?></td>
                            <td><?= htmlspecialchars(policyStatusFormatJobTime((int) ($row['last_job_time'] ?? 0))) ?></td>
                            <td class="<?= policyStatusStatusClass($statusLabel) ?>"><?= htmlspecialchars($statusLabel) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <div class="cb-box">
                <div class="cb-section-head">
                    <h4>Section B — Billed Unhealthy</h4>
                    <a class="btn btn-default btn-sm" href="<?= htmlspecialchars($csvUnhealthyUrl) ?>">Export CSV</a>
                </div>
                <p class="cb-muted">
                    Warning or error accounts in Active Services
                    <?php if ($asPulledAt): ?>
                    (<?= htmlspecialchars((string) $asPulledAt) ?>).
                    <?php endif; ?>
                    Device charge = Active Services run-rate; BH device = Bill History total.
                </p>
                <?php if (count($billedUnhealthy) === 0): ?>
                <p class="cb-muted">No billed unhealthy accounts found.</p>
                <?php else: ?>
                <table class="datatable" width="100%">
                    <thead>
                        <tr>
                            <th>Server</th>
                            <th>Policy ID</th>
                            <th>Username</th>
                            <th>Sources (warn/total)</th>
                            <th>Last job (UTC)</th>
                            <th>Status</th>
                            <th>Device charge</th>
                            <th>BH device</th>
                            <th>Booster charge</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($billedUnhealthy as $row): ?>
                        <?php $statusLabel = (string) ($row['status_label'] ?? ''); ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['server_label'] ?? '')) ?></td>
                            <td><code><?= htmlspecialchars((string) ($row['policy_id'] ?? '')) ?></code></td>
                            <td><?= htmlspecialchars((string) ($row['username'] ?? '')) ?></td>
                            <td><?= number_format((int) ($row['warning_source_count'] ?? 0)) ?> / <?= number_format((int) ($row['source_count'] ?? 0)) ?></td>
                            <td><?= htmlspecialchars(policyStatusFormatJobTime((int) ($row['last_job_time'] ?? 0))) ?></td>
                            <td class="<?= policyStatusStatusClass($statusLabel) ?>"><?= htmlspecialchars($statusLabel) ?></td>
                            <td><?= policyStatusFormatMoney((float) ($row['billed_device_amount'] ?? 0)) ?></td>
                            <td><?= policyStatusFormatBhDevice($row) ?></td>
                            <td><?= policyStatusFormatMoney((float) ($row['billed_booster_amount'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <div class="cb-box">
                <div class="cb-section-head">
                    <h4>Section C — Billed Successful</h4>
                    <a class="btn btn-default btn-sm" href="<?= htmlspecialchars($csvSuccessfulUrl) ?>">Export CSV</a>
                </div>
                <p class="cb-muted">
                    Last backup <strong>success</strong> (5000) and present in Active Services.
                    Device/booster amounts are Active Services run-rate; BH device reconciles against Bill History.
                </p>
                <?php if (count($billedSuccessful) === 0): ?>
                <p class="cb-muted">No billed successful accounts found.</p>
                <?php else: ?>
                <table class="datatable" width="100%">
                    <thead>
                        <tr>
                            <th>Server</th>
                            <th>Policy ID</th>
                            <th>Username</th>
                            <th>Sources (warn/total)</th>
                            <th>Last job (UTC)</th>
                            <th>Status</th>
                            <th>Device charge</th>
                            <th>BH device</th>
                            <th>Booster charge</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($billedSuccessful as $row): ?>
                        <?php $statusLabel = (string) ($row['status_label'] ?? ''); ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['server_label'] ?? '')) ?></td>
                            <td><code><?= htmlspecialchars((string) ($row['policy_id'] ?? '')) ?></code></td>
                            <td><?= htmlspecialchars((string) ($row['username'] ?? '')) ?></td>
                            <td><?= number_format((int) ($row['warning_source_count'] ?? 0)) ?> / <?= number_format((int) ($row['source_count'] ?? 0)) ?></td>
                            <td><?= htmlspecialchars(policyStatusFormatJobTime((int) ($row['last_job_time'] ?? 0))) ?></td>
                            <td class="<?= policyStatusStatusClass($statusLabel) ?>"><?= htmlspecialchars($statusLabel) ?></td>
                            <td><?= policyStatusFormatMoney((float) ($row['billed_device_amount'] ?? 0)) ?></td>
                            <td><?= policyStatusFormatBhDevice($row) ?></td>
                            <td><?= policyStatusFormatMoney((float) ($row['billed_booster_amount'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <div class="cb-box">
                <div class="cb-section-head">
                    <h4>Historical Device Charges (Bill History)</h4>
                    <div>
                        <a class="btn btn-default btn-sm" href="<?= htmlspecialchars($csvHistSummaryUrl) ?>">Export summary CSV</a>
                        <a class="btn btn-default btn-sm" href="<?= htmlspecialchars($csvHistDetailUrl) ?>">Export detail CSV</a>
                    </div>
                </div>
                <p class="cb-muted">
                    Every account currently on this policy set, with all canonical Bill History
                    <strong>device</strong> charges as far back as available (for Comet credit claims).
                    This does not change Sections A–C above (those still use Active Services run-rate).
                    <?php if (!empty($historical['earliest']) || !empty($historical['latest'])): ?>
                    Range: <?= htmlspecialchars((string) ($historical['earliest'] ?? '—')) ?>
                    → <?= htmlspecialchars((string) ($historical['latest'] ?? '—')) ?>.
                    <?php endif; ?>
                    Total device charges:
                    <strong>$<?= number_format((float) ($historical['total_amount'] ?? 0), 2) ?></strong>
                    across <?= number_format((int) ($historical['charge_count'] ?? 0)) ?> line(s)
                    on <?= number_format((int) ($historical['account_count_with_charges'] ?? 0)) ?> account(s).
                </p>
                <?php if (count($histSummary) === 0): ?>
                <p class="cb-muted">No historical device charges found for current policy members.</p>
                <?php else: ?>
                <table class="datatable" width="100%">
                    <thead>
                        <tr>
                            <th>Server</th>
                            <th>Policy ID</th>
                            <th>Username</th>
                            <th>First device charge</th>
                            <th>Last device charge</th>
                            <th>Charge count</th>
                            <th>Total device $</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($histSummary as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['server_label'] ?? '')) ?></td>
                            <td><code><?= htmlspecialchars((string) ($row['policy_id'] ?? '')) ?></code></td>
                            <td><?= htmlspecialchars((string) ($row['username'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['first_charge'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['last_charge'] ?? '—')) ?></td>
                            <td><?= number_format((int) ($row['charge_count'] ?? 0)) ?></td>
                            <td>$<?= number_format((float) ($row['total_amount'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
