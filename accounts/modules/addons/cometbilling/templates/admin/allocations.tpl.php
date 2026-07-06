<?php
use WHMCS\Database\Capsule;

$baseUrl = 'addonmodules.php?module=cometbilling';
$limit = 100;

$rows = Capsule::table('cb_credit_allocations')
    ->orderBy('usage_date', 'desc')
    ->orderBy('id', 'desc')
    ->limit($limit)
    ->get();
?>
<style>
.cb-alloc { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
.cb-table th, .cb-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e5e5; }
.cb-table th { background: #f9fafb; font-weight: 600; font-size: 12px; text-transform: uppercase; }
.lot-purchased { color: #1a73e8; }
.lot-bonus { color: #10b981; }
</style>

<div class="cb-alloc">
    <h3>📒 Credit Allocation History (FIFO)</h3>
    <p style="color: #666;">Daily portal usage allocated to credit lots in FIFO order (purchased before bonus).</p>

    <?php if ($rows->isEmpty()): ?>
    <p>No allocations recorded yet. Allocations are created automatically during portal pull.</p>
    <?php else: ?>
    <table class="cb-table">
        <thead>
            <tr>
                <th>Usage Date</th>
                <th>Amount</th>
                <th>Description</th>
                <th>Lots Consumed</th>
                <th>Recorded</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row):
                $allocs = json_decode($row->allocations ?? '[]', true) ?: [];
            ?>
            <tr>
                <td><?= htmlspecialchars($row->usage_date) ?></td>
                <td><strong>$<?= number_format((float) $row->total_amount, 2) ?></strong></td>
                <td><?= htmlspecialchars($row->description ?? '') ?></td>
                <td>
                    <details>
                        <summary><?= count($allocs) ?> lot(s)</summary>
                        <ul style="margin: 8px 0; font-size: 12px;">
                            <?php foreach ($allocs as $a): ?>
                            <li>
                                Lot #<?= (int) $a['lot_id'] ?> —
                                <span class="lot-<?= htmlspecialchars($a['lot_type'] ?? '') ?>"><?= htmlspecialchars($a['lot_type'] ?? '') ?></span>:
                                $<?= number_format((float) ($a['amount'] ?? 0), 2) ?>
                                (remaining: $<?= number_format((float) ($a['lot_remaining_after'] ?? 0), 2) ?>)
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                </td>
                <td><?= htmlspecialchars($row->created_at) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="font-size: 12px; color: #666;">Showing most recent <?= $limit ?> allocations.</p>
    <?php endif; ?>

    <p>
        <a href="<?= $baseUrl ?>&action=credit_lots" class="btn btn-default">Credit Lots</a>
        <a href="<?= $baseUrl ?>" class="btn btn-default">Dashboard</a>
    </p>
</div>
