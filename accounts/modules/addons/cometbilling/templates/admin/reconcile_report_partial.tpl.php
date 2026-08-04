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

<div class="cb-box" id="cb-item-comparison">
    <h4>📋 Item Comparison</h4>
    <?php if ($showDrilldown): ?>
    <div class="cb-recon-filters" style="display:flex;flex-wrap:wrap;gap:12px 20px;align-items:center;margin:0 0 12px;padding:10px 12px;background:#f9fafb;border:1px solid #e5e5e5;border-radius:6px;font-size:13px;">
        <label style="display:inline-flex;align-items:center;gap:6px;margin:0;">
            <span style="color:#4b5563;">Category status</span>
            <select id="cb-filter-category" style="min-width:140px;">
                <option value="all">All</option>
                <option value="over_billed">Over-billed</option>
                <option value="under_billed">Under-billed</option>
                <option value="warning">Within tolerance</option>
                <option value="ok">OK</option>
                <option value="variance">Any variance</option>
            </select>
        </label>
        <label style="display:inline-flex;align-items:center;gap:6px;margin:0;">
            <span style="color:#4b5563;">Portal-only billing</span>
            <select id="cb-filter-billing" style="min-width:180px;">
                <option value="all">All</option>
                <option value="overbilled_past_grace">Overbilled past grace</option>
                <option value="expected_grace">Expected grace</option>
                <option value="unknown">Unknown revoke</option>
            </select>
        </label>
        <span id="cb-filter-summary" style="color:#6b7280;font-size:12px;"></span>
        <?php if (!empty($report['summary']['past_grace_overbill'])): ?>
        <span style="margin-left:auto;font-size:13px;color:#b91c1c;">
            <strong>Total past-grace overbill:</strong> $<?= number_format((float) $report['summary']['past_grace_overbill'], 2) ?>
        </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <table class="cb-items-table cb-sortable-table" id="cb-items-comparison-table">
        <thead>
            <tr>
                <th class="cb-sortable" data-type="text">Item Type</th>
                <th class="cb-sortable" data-type="number">Server Count</th>
                <th class="cb-sortable" data-type="number">Portal Count</th>
                <th class="cb-sortable" data-type="number">Portal Amount</th>
                <th class="cb-sortable" data-type="number">Variance</th>
                <th class="cb-sortable" data-type="number">Past-grace overbill</th>
                <th class="cb-sortable" data-type="text">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report['items'] as $key => $item): ?>
            <tr class="cb-category-row" data-category-status="<?= htmlspecialchars($item['status']) ?>">
                <td data-sort="<?= htmlspecialchars(strtolower((string) $item['label'])) ?>"><strong><?= htmlspecialchars($item['label']) ?></strong></td>
                <td data-sort="<?= htmlspecialchars((string) $item['server']) ?>"><?= number_format($item['server']) ?></td>
                <td data-sort="<?= htmlspecialchars((string) $item['portal']) ?>"><?= number_format($item['portal']) ?></td>
                <td data-sort="<?= htmlspecialchars((string) $item['portal_amount']) ?>">$<?= number_format($item['portal_amount'], 2) ?></td>
                <td data-sort="<?= htmlspecialchars((string) $item['variance']) ?>">
                    <?php
                    $sign = $item['variance'] > 0 ? '+' : '';
                    $class = $item['status'] === 'ok' ? 'variance-ok' : ($item['status'] === 'warning' ? 'variance-over' : ($item['status'] === 'over_billed' ? 'variance-over' : 'variance-under'));
                    ?>
                    <span class="variance-badge <?= $class ?>"><?= $sign . $item['variance'] ?></span>
                    <?php if ($item['variance_pct'] !== null && abs($item['variance']) > 0.0001): ?>
                    <span style="font-size: 11px; color: #666;">(<?= $sign . $item['variance_pct'] ?>%)</span>
                    <?php endif; ?>
                </td>
                <td data-sort="<?= htmlspecialchars((string) ((float) ($item['past_grace_overbill'] ?? 0))) ?>">
                    <?php if (!empty($item['past_grace_overbill']) && $item['past_grace_overbill'] > 0): ?>
                    <span style="color:#b91c1c;font-weight:600;">$<?= number_format((float) $item['past_grace_overbill'], 2) ?></span>
                    <?php if (!empty($item['past_grace_count'])): ?>
                    <span style="font-size:11px;color:#666;">(<?= (int) $item['past_grace_count'] ?> item<?= $item['past_grace_count'] === 1 ? '' : 's' ?>)</span>
                    <?php endif; ?>
                    <?php else: ?>
                    —
                    <?php endif; ?>
                </td>
                <td data-sort="<?= htmlspecialchars((string) $item['status']) ?>">
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
            <?php if ($showDrilldown && $item['status'] !== 'ok'): ?>
            <tr class="cb-drilldown-row" data-category-status="<?= htmlspecialchars($item['status']) ?>">
                <td colspan="7" style="background: #fafafa; padding-left: 30px;">
                    <?php if (!empty($item['unmatched_unavailable'])): ?>
                    <p style="font-size: 12px; color: #92400e; margin: 0 0 8px;"><?= htmlspecialchars($item['unmatched_unavailable']) ?></p>
                    <?php elseif (!empty($item['unmatched'])): ?>
                    <?php
                    $unmatched = $item['unmatched'];
                    $pastGraceCount = 0;
                    $graceCount = 0;
                    $unknownCount = 0;
                    foreach ($unmatched['portal_only'] ?? [] as $po) {
                        $bs = $po['billing_status'] ?? '';
                        if ($bs === 'overbilled_past_grace') {
                            $pastGraceCount++;
                        } elseif ($bs === 'expected_grace') {
                            $graceCount++;
                        } elseif ($bs === 'unknown') {
                            $unknownCount++;
                        }
                    }
                    $renderUnmatchedTable = static function (array $rows, bool $showQty = true, bool $showBilling = false) {
                        if (empty($rows)) {
                            echo '<p style="font-size: 12px; color: #666;">None</p>';
                            return;
                        }
                        echo '<table class="cb-items-table cb-portal-only-table cb-sortable-table" style="margin-top: 8px; font-size: 12px;"><thead><tr>';
                        echo '<th class="cb-sortable" data-type="text">Account</th>'
                            . '<th class="cb-sortable" data-type="text">Device</th>'
                            . '<th class="cb-sortable" data-type="text">Server</th>'
                            . '<th class="cb-sortable" data-type="text">Friendly Name</th>';
                        if ($showQty) {
                            echo '<th class="cb-sortable" data-type="number">Portal Qty</th>'
                                . '<th class="cb-sortable" data-type="number">Server Qty</th>';
                        }
                        if ($showBilling) {
                            echo '<th class="cb-sortable" data-type="text">Registered</th>'
                                . '<th class="cb-sortable" data-type="text">Revoked / removed</th>'
                                . '<th class="cb-sortable" data-type="text">Cycle</th>'
                                . '<th class="cb-sortable" data-type="text">Next due</th>'
                                . '<th class="cb-sortable" data-type="text">Expected end</th>'
                                . '<th class="cb-sortable" data-type="text">Billing status</th>'
                                . '<th class="cb-sortable" data-type="number">Overbill $</th>';
                        }
                        echo '<th class="cb-sortable" data-type="number">Amount</th></tr></thead><tbody>';
                        foreach ($rows as $row) {
                            $status = $row['billing_status'] ?? null;
                            $rowStyle = $status === 'overbilled_past_grace' ? ' style="background:#fef2f2;"' : ($status === 'expected_grace' ? ' style="background:#fffbeb;"' : '');
                            $billingAttr = $showBilling ? ' data-billing-status="' . htmlspecialchars((string) ($status ?: 'unknown')) . '"' : '';
                            $account = (string) ($row['account'] ?? $row['username'] ?? '—');
                            $deviceId = (string) ($row['device_id'] ?? '—');
                            $serverKey = (string) ($row['server_key'] ?? '—');
                            $friendly = (string) ($row['friendly_name'] ?? $row['device_name'] ?? '—');
                            echo '<tr class="cb-portal-row"' . $billingAttr . $rowStyle . '>';
                            echo '<td data-sort="' . htmlspecialchars(strtolower($account)) . '">' . htmlspecialchars($account) . '</td>';
                            echo '<td data-sort="' . htmlspecialchars(strtolower($deviceId)) . '">' . htmlspecialchars($deviceId) . '</td>';
                            echo '<td data-sort="' . htmlspecialchars(strtolower($serverKey)) . '">' . htmlspecialchars($serverKey) . '</td>';
                            echo '<td data-sort="' . htmlspecialchars(strtolower($friendly)) . '">' . htmlspecialchars($friendly) . '</td>';
                            if ($showQty) {
                                $pq = $row['portal_qty'] ?? null;
                                $sq = $row['server_qty'] ?? null;
                                echo '<td data-sort="' . htmlspecialchars((string) ($pq ?? '')) . '">' . htmlspecialchars((string) ($pq ?? '—')) . '</td>';
                                echo '<td data-sort="' . htmlspecialchars((string) ($sq ?? '')) . '">' . htmlspecialchars((string) ($sq ?? '—')) . '</td>';
                            }
                            if ($showBilling) {
                                $registered = !empty($row['registered_at']) ? substr((string) $row['registered_at'], 0, 10) : '';
                                $revokedDisp = $row['revoked_at'] ? substr((string) $row['revoked_at'], 0, 19) : (!empty($row['expected_billing_end']) ? (string) $row['expected_billing_end'] : '');
                                $cycle = (int) ($row['billing_cycle_days'] ?? 30);
                                $cycleDisp = $cycle <= 1 ? 'daily' : (string) $cycle;
                                $nextDue = (string) ($row['next_due_date'] ?? '');
                                $expectedEnd = (string) ($row['expected_billing_end'] ?? '');
                                $overbill = (float) ($row['overbill_amount'] ?? 0);
                                echo '<td data-sort="' . htmlspecialchars($registered) . '">' . htmlspecialchars($registered !== '' ? $registered : '—') . '</td>';
                                echo '<td data-sort="' . htmlspecialchars($revokedDisp) . '">' . htmlspecialchars($revokedDisp !== '' ? $revokedDisp : '—') . '</td>';
                                echo '<td data-sort="' . htmlspecialchars($cycle <= 1 ? '1' : (string) $cycle) . '">' . htmlspecialchars($cycleDisp) . '</td>';
                                echo '<td data-sort="' . htmlspecialchars($nextDue) . '">' . htmlspecialchars($nextDue !== '' ? $nextDue : '—') . '</td>';
                                echo '<td data-sort="' . htmlspecialchars($expectedEnd) . '">' . htmlspecialchars($expectedEnd !== '' ? $expectedEnd : '—') . '</td>';
                                if ($status === 'overbilled_past_grace') {
                                    echo '<td data-sort="overbilled_past_grace"><span style="color:#b91c1c;font-weight:600;">Overbilled past grace</span></td>';
                                } elseif ($status === 'expected_grace') {
                                    echo '<td data-sort="expected_grace"><span style="color:#92400e;">Expected grace</span></td>';
                                } elseif ($status === 'unknown') {
                                    echo '<td data-sort="unknown"><span style="color:#6b7280;">Unknown revoke</span></td>';
                                } else {
                                    echo '<td data-sort="">—</td>';
                                }
                                if ($status === 'overbilled_past_grace' && $overbill > 0) {
                                    echo '<td data-sort="' . htmlspecialchars((string) $overbill) . '"><span style="color:#b91c1c;font-weight:600;">$' . number_format($overbill, 2) . '</span></td>';
                                } else {
                                    echo '<td data-sort="0">—</td>';
                                }
                            }
                            $amt = $row['amount'] ?? null;
                            echo '<td data-sort="' . htmlspecialchars((string) ($amt ?? '')) . '">' . ($amt !== null ? '$' . number_format((float) $amt, 2) : '—') . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                    };
                    ?>
                    <p style="font-size: 11px; color: #666; margin: 0 0 8px;">Billing mode follows portal cycle days: monthly lines use Comet next_due periods (expected end is the period containing revoke); daily lines (cycle 1) use remove day as last billable.</p>
                    <?php if ($pastGraceCount > 0): ?>
                    <p style="font-size: 12px; color: #b91c1c; margin: 0 0 8px;"><strong><?= (int) $pastGraceCount ?></strong> portal-only item(s) billed past expected billing end<?php if ($graceCount > 0): ?> · <?= (int) $graceCount ?> still within expected end<?php endif; ?>.</p>
                    <?php endif; ?>
                    <details open>
                        <summary style="cursor: pointer; font-size: 13px;">Unmatched items</summary>
                        <details style="margin-top: 8px;" open>
                            <summary style="cursor: pointer; font-size: 12px;">Portal only (<?= count($unmatched['portal_only'] ?? []) ?><?php if (!empty($unmatched['truncated']['portal_only'])): ?>, +<?= (int) $unmatched['truncated']['portal_only'] ?> more<?php endif; ?>)</summary>
                            <?php $renderUnmatchedTable($unmatched['portal_only'] ?? [], true, true); ?>
                        </details>
                        <details style="margin-top: 8px;">
                            <summary style="cursor: pointer; font-size: 12px;">Server only (<?= count($unmatched['server_only'] ?? []) ?><?php if (!empty($unmatched['truncated']['server_only'])): ?>, +<?= (int) $unmatched['truncated']['server_only'] ?> more<?php endif; ?>)</summary>
                            <?php $renderUnmatchedTable($unmatched['server_only'] ?? [], false); ?>
                        </details>
                        <?php if (!empty($unmatched['qty_mismatch'])): ?>
                        <details style="margin-top: 8px;">
                            <summary style="cursor: pointer; font-size: 12px;">Qty mismatch (<?= count($unmatched['qty_mismatch']) ?><?php if (!empty($unmatched['truncated']['qty_mismatch'])): ?>, +<?= (int) $unmatched['truncated']['qty_mismatch'] ?> more<?php endif; ?>)</summary>
                            <?php $renderUnmatchedTable($unmatched['qty_mismatch'] ?? []); ?>
                        </details>
                        <?php endif; ?>
                    </details>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.cb-items-table th.cb-sortable { cursor: pointer; user-select: none; white-space: nowrap; }
.cb-items-table th.cb-sortable:hover { background: #f3f4f6; color: #111827; }
.cb-items-table th.cb-sortable::after { content: " \2195"; opacity: 0.35; font-size: 10px; }
.cb-items-table th.cb-sort-asc::after { content: " \25B2"; opacity: 0.7; }
.cb-items-table th.cb-sort-desc::after { content: " \25BC"; opacity: 0.7; }
</style>
<script>
(function () {
    function cellSortValue(row, colIndex, type) {
        var cell = row.children[colIndex];
        if (!cell) return type === 'number' ? 0 : '';
        var raw = cell.getAttribute('data-sort');
        if (raw === null || raw === undefined) {
            raw = (cell.textContent || '').replace(/\s+/g, ' ').trim();
        }
        if (type === 'number') {
            if (raw === '' || raw === '—' || raw === '-') return Number.NEGATIVE_INFINITY;
            var n = parseFloat(String(raw).replace(/[^0-9.\-]/g, ''));
            return isNaN(n) ? Number.NEGATIVE_INFINITY : n;
        }
        return String(raw).toLowerCase();
    }

    function compareValues(a, b, type, dir) {
        if (type === 'number') {
            return dir === 'asc' ? a - b : b - a;
        }
        if (a < b) return dir === 'asc' ? -1 : 1;
        if (a > b) return dir === 'asc' ? 1 : -1;
        return 0;
    }

    function sortPlainTable(table, colIndex, type, dir) {
        var tbody = table.tBodies[0];
        if (!tbody) return;
        var rows = Array.prototype.slice.call(tbody.rows);
        rows.sort(function (ra, rb) {
            return compareValues(
                cellSortValue(ra, colIndex, type),
                cellSortValue(rb, colIndex, type),
                type,
                dir
            );
        });
        rows.forEach(function (row) { tbody.appendChild(row); });
    }

    function sortCategoryTable(table, colIndex, type, dir) {
        var tbody = table.tBodies[0];
        if (!tbody) return;
        var groups = [];
        var rows = Array.prototype.slice.call(tbody.rows);
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            if (!row.classList.contains('cb-category-row')) continue;
            var group = [row];
            var next = rows[i + 1];
            if (next && next.classList.contains('cb-drilldown-row')) {
                group.push(next);
                i++;
            }
            groups.push(group);
        }
        groups.sort(function (ga, gb) {
            return compareValues(
                cellSortValue(ga[0], colIndex, type),
                cellSortValue(gb[0], colIndex, type),
                type,
                dir
            );
        });
        groups.forEach(function (group) {
            group.forEach(function (row) { tbody.appendChild(row); });
        });
    }

    function bindSortableTable(table) {
        var headers = table.querySelectorAll('thead th.cb-sortable');
        headers.forEach(function (th, colIndex) {
            th.addEventListener('click', function () {
                var type = th.getAttribute('data-type') || 'text';
                var current = th.getAttribute('data-sort-dir');
                var dir = current === 'asc' ? 'desc' : 'asc';
                headers.forEach(function (h) {
                    h.removeAttribute('data-sort-dir');
                    h.classList.remove('cb-sort-asc', 'cb-sort-desc');
                });
                th.setAttribute('data-sort-dir', dir);
                th.classList.add(dir === 'asc' ? 'cb-sort-asc' : 'cb-sort-desc');

                if (table.id === 'cb-items-comparison-table') {
                    sortCategoryTable(table, colIndex, type, dir);
                } else {
                    sortPlainTable(table, colIndex, type, dir);
                }
            });
        });
    }

    document.querySelectorAll('table.cb-sortable-table').forEach(bindSortableTable);

    var catSel = document.getElementById('cb-filter-category');
    var billSel = document.getElementById('cb-filter-billing');
    var summary = document.getElementById('cb-filter-summary');
    if (!catSel || !billSel) return;

    function applyFilters() {
        var catFilter = catSel.value;
        var billFilter = billSel.value;
        var catVisible = 0;
        var portalVisible = 0;
        var portalTotal = 0;

        document.querySelectorAll('#cb-items-comparison-table tr.cb-category-row').forEach(function (row) {
            var status = row.getAttribute('data-category-status') || '';
            var show = catFilter === 'all'
                || (catFilter === 'variance' && status !== 'ok')
                || status === catFilter;
            row.style.display = show ? '' : 'none';

            var drill = row.nextElementSibling;
            if (drill && drill.classList.contains('cb-drilldown-row')) {
                drill.style.display = show ? '' : 'none';
            }
            if (show) catVisible++;
        });

        document.querySelectorAll('#cb-items-comparison-table tr.cb-portal-row[data-billing-status]').forEach(function (row) {
            portalTotal++;
            var status = row.getAttribute('data-billing-status') || 'unknown';
            var catRow = row.closest('tr.cb-drilldown-row');
            var catHidden = catRow && catRow.style.display === 'none';
            var show = !catHidden && (billFilter === 'all' || status === billFilter);
            row.style.display = show ? '' : 'none';
            if (show) portalVisible++;
        });

        if (summary) {
            var parts = [catVisible + ' categor' + (catVisible === 1 ? 'y' : 'ies')];
            if (billFilter !== 'all' || portalTotal > 0) {
                parts.push(portalVisible + ' of ' + portalTotal + ' portal-only row' + (portalTotal === 1 ? '' : 's'));
            }
            summary.textContent = parts.join(' · ');
        }
    }

    catSel.addEventListener('change', applyFilters);
    billSel.addEventListener('change', applyFilters);
    applyFilters();
})();
</script>

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
