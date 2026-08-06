<?php
use CometBilling\PolicyStatusReport;

$report = PolicyStatusReport::report();

$warningAccounts = $report['warning_accounts'];
$billedUnhealthy = $report['billed_unhealthy'];
$billedSuccessful = $report['billed_successful'] ?? [];
$asPulledAt = $report['active_services_pulled_at'];
$serverErrors = $report['server_errors'];

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
?>
<style>
.cb-policy-status { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-box { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; margin: 20px 0; }
.cb-box h4 { margin: 0 0 15px 0; }
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
.cb-policy-list { margin: 10px 0 0; padding-left: 20px; }
</style>

<div class="cb-policy-status">
    <h3>Policy Status</h3>
    <p class="cb-muted">
        Accounts on configured CSW Policy IDs.
        Section A lists last-backup <strong>warning</strong>;
        Section B lists billed accounts with warning/error;
        Section C lists billed accounts with last-backup <strong>success</strong> and their device/booster Active Services charges.
    </p>

    <div class="cb-box">
        <h4>Configured Policies</h4>
        <ul class="cb-policy-list">
            <?php foreach (PolicyStatusReport::POLICY_MAP as $key => $policyId): ?>
            <li>
                <strong><?= htmlspecialchars(PolicyStatusReport::SERVER_LABELS[$key] ?? $key) ?></strong>
                (<?= htmlspecialchars($key) ?>):
                <code><?= htmlspecialchars($policyId) ?></code>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="cb-muted">
            Scanned <?= number_format((int) $report['account_count']) ?> account(s) across
            <?= number_format(count(PolicyStatusReport::POLICY_MAP)) ?> server(s).
        </p>
    </div>

    <div class="cb-box">
        <h4>Summary</h4>
        <div class="cb-summary">
            <div class="cb-stat">
                <div class="value negative"><?= number_format(count($warningAccounts)) ?></div>
                <div class="label">Section A — warning accounts</div>
            </div>
            <div class="cb-stat">
                <div class="value negative"><?= number_format(count($billedUnhealthy)) ?></div>
                <div class="label">Section B — billed unhealthy</div>
            </div>
            <div class="cb-stat">
                <div class="value positive"><?= number_format(count($billedSuccessful)) ?></div>
                <div class="label">Section C — billed successful</div>
            </div>
            <div class="cb-stat">
                <div class="value"><?= $asPulledAt ? htmlspecialchars((string) $asPulledAt) : '—' ?></div>
                <div class="label">Active Services snapshot</div>
            </div>
            <div class="cb-stat">
                <div class="value <?= count($serverErrors) > 0 ? 'negative' : 'positive' ?>"><?= number_format(count($serverErrors)) ?></div>
                <div class="label">Server errors</div>
            </div>
        </div>
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

    <div class="cb-box">
        <h4>Section A — Warning Accounts</h4>
        <p class="cb-muted">Accounts with last backup job status exactly <strong>warning</strong> (7001).</p>
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
        <h4>Section B — Billed Unhealthy</h4>
        <p class="cb-muted">
            Warning or error accounts present in the latest Active Services snapshot
            <?php if ($asPulledAt): ?>
            (<?= htmlspecialchars((string) $asPulledAt) ?>).
            <?php else: ?>
            (no snapshot available).
            <?php endif; ?>
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
                    <td><?= policyStatusFormatMoney((float) ($row['billed_booster_amount'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="cb-box">
        <h4>Section C — Billed Successful</h4>
        <p class="cb-muted">
            Accounts on the same Policy IDs whose worst last backup status is <strong>success</strong> (5000)
            and that appear in the latest Active Services snapshot
            <?php if ($asPulledAt): ?>
            (<?= htmlspecialchars((string) $asPulledAt) ?>).
            <?php else: ?>
            (no snapshot available).
            <?php endif; ?>
            Device and booster charges are Active Services run-rate amounts.
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
                    <td><?= policyStatusFormatMoney((float) ($row['billed_booster_amount'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
