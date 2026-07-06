<?php
/**
 * Shared reconciliation report display.
 * Expects $report (array) and optional $showDrilldown (bool, default true).
 */
$showDrilldown = $showDrilldown ?? true;
?>
<div class="overall-status overall-<?= $report['overall_status'] === 'ok' ? 'ok' : ($report['overall_status'] === 'incomplete' ? 'incomplete' : 'variance') ?>">
    <?php if ($report['overall_status'] === 'ok'): ?>
        ✓ ALL ITEMS MATCH
    <?php elseif ($report['overall_status'] === 'incomplete'): ?>
        ⚠️ INCOMPLETE (Server Errors)
    <?php else: ?>
        ⚠️ VARIANCE DETECTED
    <?php endif; ?>
</div>

<?php if (!empty($report['mode'])): ?>
<p style="color: #666; font-size: 13px;">
    Mode: <strong><?= htmlspecialchars($report['mode'] === 'live' ? 'Live Server Pull' : 'Stored Snapshots') ?></strong>
    <?php if (!empty($report['snapshot_date'])): ?>
    &nbsp;| Snapshot date: <strong><?= htmlspecialchars($report['snapshot_date']) ?></strong>
    <?php endif; ?>
    <?php if (isset($report['tolerance'])): ?>
    &nbsp;| Tolerance: ±<?= (int) $report['tolerance'] ?>
    <?php endif; ?>
</p>
<?php endif; ?>

<div class="cb-comparison">
    <div class="cb-box">
        <h4>🖥️ Comet Servers (Actual Usage)</h4>
        <p>Collected: <?= htmlspecialchars($report['server_collected_at'] ?? 'N/A') ?></p>
        <ul>
            <li>Total Users: <strong><?= $report['server_raw']['total_users'] ?? 0 ?></strong></li>
            <li>Total Devices: <strong><?= $report['server_raw']['total_devices'] ?? 0 ?></strong></li>
            <li>Protected Items: <strong><?= $report['server_raw']['total_protected_items'] ?? 0 ?></strong></li>
            <li>Storage: <strong><?= htmlspecialchars($report['server_raw']['storage_human'] ?? 'N/A') ?></strong></li>
        </ul>
        <?php if (!empty($report['server_raw']['errors'])): ?>
        <div style="color: #ef4444; margin-top: 10px;">
            <strong>Errors:</strong>
            <ul>
                <?php foreach ($report['server_raw']['errors'] as $srv => $err): ?>
                <li><?= htmlspecialchars($srv) ?>: <?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php if (!empty($report['per_server'])): ?>
        <details style="margin-top: 10px;">
            <summary><strong>Per-server breakdown</strong></summary>
            <table class="cb-items-table" style="margin-top: 8px;">
                <thead>
                    <tr>
                        <th>Server</th>
                        <th>Devices</th>
                        <th>Hyper-V</th>
                        <th>VMware</th>
                        <th>Proxmox</th>
                        <th>M365</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['per_server'] as $srvKey => $srv): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($srvKey) ?></strong></td>
                        <td><?= (int) ($srv['devices'] ?? 0) ?></td>
                        <td><?= (int) ($srv['hyperv_vms'] ?? 0) ?></td>
                        <td><?= (int) ($srv['vmware_vms'] ?? 0) ?></td>
                        <td><?= (int) ($srv['proxmox_vms'] ?? 0) ?></td>
                        <td><?= (int) ($srv['m365_accounts'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>
        <?php endif; ?>
    </div>

    <div class="cb-box">
        <h4>📦 Comet Portal (Billing)</h4>
        <p>Snapshot: <?= htmlspecialchars($report['portal_snapshot_at'] ?? 'N/A') ?></p>
        <ul>
            <li>Active Rows: <strong><?= $report['portal_raw']['raw_rows'] ?? 0 ?></strong></li>
            <li>Total Billable: <strong>$<?= number_format($report['portal_raw']['total_amount'] ?? 0, 2) ?></strong></li>
            <li>Account Fees: <strong>$<?= number_format($report['portal_raw']['account_fees'] ?? 0, 2) ?></strong></li>
            <li>Server Licenses: <strong>$<?= number_format($report['portal_raw']['server_licenses'] ?? 0, 2) ?></strong></li>
            <?php if (!empty($report['portal_raw']['other_boosters_count'])): ?>
            <li>Other Boosters: <strong><?= (int) $report['portal_raw']['other_boosters_count'] ?></strong>
                ($<?= number_format($report['portal_raw']['other_boosters_amount'] ?? 0, 2) ?>)</li>
            <?php endif; ?>
            <?php if (!empty($report['portal_raw']['unknown_count'])): ?>
            <li>Unknown Types: <strong><?= (int) $report['portal_raw']['unknown_count'] ?></strong>
                ($<?= number_format($report['portal_raw']['unknown_amount'] ?? 0, 2) ?>)</li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<div class="cb-box">
    <h4>📋 Item Comparison</h4>
    <table class="cb-items-table">
        <thead>
            <tr>
                <th>Item Type</th>
                <th>Server Count</th>
                <th>Portal Count</th>
                <th>Portal Amount</th>
                <th>Variance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report['items'] as $key => $item): ?>
            <tr>
                <td><strong><?= htmlspecialchars($item['label']) ?></strong></td>
                <td><?= number_format($item['server']) ?></td>
                <td><?= number_format($item['portal']) ?></td>
                <td>$<?= number_format($item['portal_amount'], 2) ?></td>
                <td>
                    <?php
                    $sign = $item['variance'] > 0 ? '+' : '';
                    $class = $item['status'] === 'ok' ? 'variance-ok' : ($item['status'] === 'warning' ? 'variance-over' : ($item['status'] === 'over_billed' ? 'variance-over' : 'variance-under'));
                    ?>
                    <span class="variance-badge <?= $class ?>"><?= $sign . $item['variance'] ?></span>
                    <?php if ($item['variance_pct'] !== null && abs($item['variance']) > 0.0001): ?>
                    <span style="font-size: 11px; color: #666;">(<?= $sign . $item['variance_pct'] ?>%)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($item['status'] === 'ok'): ?>
                    <span class="status-ok">✓ OK</span>
                    <?php elseif ($item['status'] === 'warning'): ?>
                    <span class="status-over">⚠ Within tolerance</span>
                    <?php elseif ($item['status'] === 'over_billed'): ?>
                    <span class="status-over">⚠️ Over-billed</span>
                    <?php else: ?>
                    <span class="status-under">⚠️ Under-billed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($showDrilldown && !empty($item['portal_items']) && $item['status'] !== 'ok'): ?>
            <tr>
                <td colspan="6" style="background: #fafafa; padding-left: 30px;">
                    <details>
                        <summary style="cursor: pointer; font-size: 13px;">Portal line items (<?= count($item['portal_items']) ?>)</summary>
                        <table class="cb-items-table" style="margin-top: 8px; font-size: 12px;">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Device</th>
                                    <th>Service / Type</th>
                                    <th>Qty</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($item['portal_items'], 0, 50) as $pi): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pi['account'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($pi['device_id'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($pi['raw_service_name'] ?? $pi['booster_type'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars((string) ($pi['quantity'] ?? 1)) ?></td>
                                    <td>$<?= number_format((float) ($pi['amount'] ?? 0), 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (count($item['portal_items']) > 50): ?>
                        <p style="font-size: 11px; color: #666;">Showing first 50 of <?= count($item['portal_items']) ?> items.</p>
                        <?php endif; ?>
                    </details>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($showDrilldown && (!empty($report['other_boosters']) || !empty($report['unknown']))): ?>
<div class="cb-box">
    <h4>Other Portal Categories</h4>
    <?php if (!empty($report['other_boosters']['count'])): ?>
    <p><strong>Other Boosters:</strong> <?= (int) $report['other_boosters']['count'] ?> items
        ($<?= number_format($report['other_boosters']['amount'] ?? 0, 2) ?>)</p>
    <?php endif; ?>
    <?php if (!empty($report['unknown']['count'])): ?>
    <p><strong>Unknown Types:</strong> <?= (int) $report['unknown']['count'] ?> items
        ($<?= number_format($report['unknown']['amount'] ?? 0, 2) ?>)
        <?php if (!empty($report['unknown']['types'])): ?>
        — types: <?= htmlspecialchars(implode(', ', $report['unknown']['types'])) ?>
        <?php endif; ?>
    </p>
    <?php endif; ?>
</div>
<?php endif; ?>
