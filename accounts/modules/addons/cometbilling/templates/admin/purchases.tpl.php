<?php
use WHMCS\Database\Capsule;
use CometBilling\PurchaseCsvImporter;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_token('WHMCS.admin.default')) {
    $action = $_POST['cb_action'] ?? 'add_manual';

    try {
        switch ($action) {
            case 'delete_selected':
                $ids = $_POST['purchase_ids'] ?? [];
                if (!is_array($ids) || $ids === []) {
                    $message = '<div class="errorbox">Select at least one purchase to delete.</div>';
                    break;
                }

                $deleted = PurchaseCsvImporter::deleteByIds($ids);
                $message = '<div class="infobox">Deleted ' . (int) $deleted . ' purchase(s) and associated credit lots.</div>';
                break;

            case 'import_csv':
                if (empty($_FILES['csv_file']['tmp_name']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
                    $message = '<div class="errorbox">Please choose a CSV file to upload.</div>';
                    break;
                }

                $upload = $_FILES['csv_file'];
                if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $message = '<div class="errorbox">CSV upload failed. Please try again.</div>';
                    break;
                }

                if (($upload['size'] ?? 0) > 2 * 1024 * 1024) {
                    $message = '<div class="errorbox">CSV file is too large (maximum 2 MB).</div>';
                    break;
                }

                $originalName = (string) ($upload['name'] ?? '');
                if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'csv') {
                    $message = '<div class="errorbox">Only .csv files are accepted.</div>';
                    break;
                }

                $tempPath = tempnam(sys_get_temp_dir(), 'cb_csv_');
                if ($tempPath === false || !move_uploaded_file($upload['tmp_name'], $tempPath)) {
                    if ($tempPath !== false) {
                        @unlink($tempPath);
                    }
                    $message = '<div class="errorbox">Failed to process uploaded CSV file.</div>';
                    break;
                }

                $result = PurchaseCsvImporter::import($tempPath);
                @unlink($tempPath);

                if (!empty($result['errors'])) {
                    $message = '<div class="errorbox">' . htmlspecialchars(implode(' ', $result['errors'])) . '</div>';
                } else {
                    $message = '<div class="infobox">Imported ' . (int) $result['imported']
                        . ' purchases, skipped ' . (int) $result['skipped']
                        . ' duplicates, created ' . (int) $result['lots'] . ' credit lots.</div>';
                }
                break;

            case 'add_manual':
            default:
                $data = [
                    'purchased_at'  => $_POST['purchased_at'],
                    'currency'      => $_POST['currency'] ?? 'USD',
                    'pack_label'    => $_POST['pack_label'] ?? null,
                    'pack_units'    => $_POST['pack_units'] !== '' ? (int) $_POST['pack_units'] : null,
                    'credit_amount' => $_POST['credit_amount'],
                    'bonus_credit'  => $_POST['bonus_credit'] !== '' ? $_POST['bonus_credit'] : 0,
                    'payment_method'=> null,
                    'receipt_no'    => null,
                    'external_ref'  => null,
                    'notes'         => null,
                    'raw_receipt'   => null,
                ];

                try {
                    $purchaseId = PurchaseCsvImporter::persistPurchase($data);
                    $lotCount = Capsule::table('cb_credit_lots')->where('purchase_id', $purchaseId)->count();
                    $message = '<div class="infobox">Saved purchase #' . (int) $purchaseId
                        . ' and created ' . (int) $lotCount . ' credit lot(s).</div>';
                } catch (\Exception $e) {
                    $message = '<div class="errorbox">Purchase save failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                break;
        }
    } catch (\Exception $e) {
        $message = '<div class="errorbox">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

$rows = Capsule::table('cb_credit_purchases')->orderBy('purchased_at', 'desc')->limit(500)->get();
?>
<style>
.cb-purchases { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-box { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; margin: 20px 0; }
.cb-box h4 { margin: 0 0 15px 0; }
.cb-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px 16px; align-items: end; }
.cb-field label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
.cb-field input { width: 100%; max-width: 220px; }
.cb-field-wide { grid-column: 1 / -1; }
.cb-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
.cb-table th, .cb-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e5e5; }
.cb-table th { background: #f9fafb; font-weight: 600; font-size: 12px; text-transform: uppercase; }
.cb-table tr:hover { background: #f9fafb; }
.cb-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.cb-actions { margin: 12px 0; }
.cb-muted { color: #666; font-size: 12px; margin: 8px 0 0; }
</style>

<div class="cb-purchases">
    <h3>Credit Purchases</h3>
    <?= $message ?>

    <div class="cb-box">
        <h4>Import from Comet CSV</h4>
        <p class="cb-muted">Upload a purchase history export from the Comet Account Portal. Dates are stored at 00:00:00 UTC.</p>
        <form method="post" enctype="multipart/form-data">
            <?php echo generate_token('WHMCS.admin.default'); ?>
            <input type="hidden" name="cb_action" value="import_csv">
            <div class="cb-form-grid">
                <div class="cb-field cb-field-wide">
                    <label for="csv_file">CSV file</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
                </div>
                <div class="cb-field">
                    <button class="btn btn-primary" type="submit">Import CSV</button>
                </div>
            </div>
            <p class="cb-muted">Expects columns: Date, Type, Item, Credit Amount, Cost. Only Customer Purchase rows are imported.</p>
        </form>
    </div>

    <div class="cb-box">
        <h4>Add Purchase Manually</h4>
        <form method="post">
            <?php echo generate_token('WHMCS.admin.default'); ?>
            <input type="hidden" name="cb_action" value="add_manual">
            <div class="cb-form-grid">
                <div class="cb-field">
                    <label for="purchased_at">Date/Time (UTC)</label>
                    <input type="datetime-local" id="purchased_at" name="purchased_at" required>
                </div>
                <div class="cb-field">
                    <label for="currency">Currency</label>
                    <input type="text" id="currency" name="currency" value="USD" maxlength="3">
                </div>
                <div class="cb-field">
                    <label for="pack_label">Pack</label>
                    <input type="text" id="pack_label" name="pack_label" placeholder="e.g. 10,000 Dollars">
                </div>
                <div class="cb-field">
                    <label for="pack_units">Units</label>
                    <input type="number" id="pack_units" name="pack_units" min="0" step="1">
                </div>
                <div class="cb-field">
                    <label for="credit_amount">Credit</label>
                    <input type="number" id="credit_amount" name="credit_amount" step="0.01" required>
                </div>
                <div class="cb-field">
                    <label for="bonus_credit">Bonus</label>
                    <input type="number" id="bonus_credit" name="bonus_credit" step="0.01" value="0">
                </div>
                <div class="cb-field">
                    <label>&nbsp;</label>
                    <button class="btn btn-primary" type="submit">Add Purchase</button>
                </div>
            </div>
        </form>
    </div>

    <div class="cb-box">
        <h4>Purchase History</h4>
        <?php if ($rows->isEmpty()): ?>
        <p class="cb-muted">No purchases recorded yet.</p>
        <?php else: ?>
        <form method="post" id="cb-purchase-delete-form" onsubmit="return cbConfirmDeletePurchases();">
            <?php echo generate_token('WHMCS.admin.default'); ?>
            <input type="hidden" name="cb_action" value="delete_selected">
            <div class="cb-actions">
                <button type="submit" class="btn btn-danger">Delete Selected</button>
            </div>
            <table class="cb-table">
                <thead>
                    <tr>
                        <th style="width: 36px;">
                            <input type="checkbox" id="cb-select-all-purchases" title="Select all">
                        </th>
                        <th>Purchased</th>
                        <th>Currency</th>
                        <th>Pack</th>
                        <th class="num">Units</th>
                        <th class="num">Credit</th>
                        <th class="num">Bonus</th>
                        <th class="num">Credit Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $credit = (float) $r->credit_amount;
                    $bonus = (float) $r->bonus_credit;
                    $total = $credit + $bonus;
                ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="cb-purchase-row" name="purchase_ids[]" value="<?= (int) $r->id ?>">
                        </td>
                        <td><?= htmlspecialchars($r->purchased_at) ?></td>
                        <td><?= htmlspecialchars($r->currency) ?></td>
                        <td><?= htmlspecialchars((string) $r->pack_label) ?></td>
                        <td class="num"><?= $r->pack_units !== null ? number_format((int) $r->pack_units) : '—' ?></td>
                        <td class="num">$<?= number_format($credit, 2) ?></td>
                        <td class="num">$<?= number_format($bonus, 2) ?></td>
                        <td class="num"><strong>$<?= number_format($total, 2) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var selectAll = document.getElementById('cb-select-all-purchases');
    if (!selectAll) return;
    selectAll.addEventListener('change', function () {
        document.querySelectorAll('.cb-purchase-row').forEach(function (cb) {
            cb.checked = selectAll.checked;
        });
    });
})();

function cbConfirmDeletePurchases() {
    var checked = document.querySelectorAll('.cb-purchase-row:checked');
    if (checked.length === 0) {
        alert('Select at least one purchase to delete.');
        return false;
    }
    return confirm('Delete ' + checked.length + ' selected purchase(s) and their credit lots? This cannot be undone.');
}
</script>
