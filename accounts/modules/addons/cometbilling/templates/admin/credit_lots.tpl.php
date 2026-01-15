<?php
use WHMCS\Database\Capsule;
use CometBilling\CreditLedger;

// Load classes
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;

$baseUrl = 'addonmodules.php?module=cometbilling';
$message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_token('WHMCS.admin.default')) {
    $action = $_POST['cb_action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_opening_balance':
                $purchased = (float)($_POST['purchased_amount'] ?? 0);
                $bonus = (float)($_POST['bonus_amount'] ?? 0);
                $asOf = $_POST['as_of_date'] ?: null;
                
                if ($purchased > 0 || $bonus > 0) {
                    $lotIds = CreditLedger::createOpeningBalance($purchased, $bonus, $asOf);
                    $message = '<div class="successbox">Opening balance created. Lot IDs: ' . implode(', ', $lotIds) . '</div>';
                } else {
                    $message = '<div class="errorbox">Please enter a positive amount.</div>';
                }
                break;
                
            case 'sync_purchases':
                $count = CreditLedger::syncLotsFromPurchases();
                $message = '<div class="successbox">Synced ' . $count . ' purchase(s) to credit lots.</div>';
                break;
        }
    } catch (\Exception $e) {
        $message = '<div class="errorbox">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Get data
$balance = ['purchased' => 0, 'bonus' => 0, 'total' => 0];
$original = ['purchased' => 0, 'bonus' => 0, 'total' => 0];
$consumed = ['purchased' => 0, 'bonus' => 0, 'total' => 0];
$lots = [];
$runway = ['daily_burn' => 0, 'days_remaining' => null];

try {
    $balance = CreditLedger::getCurrentBalance();
    $original = CreditLedger::getOriginalTotals();
    $consumed = CreditLedger::getConsumed();
    $lots = CreditLedger::getLots(true, 50);
    $runway = CreditLedger::estimateRunway(30);
} catch (\Exception $e) {
    $message .= '<div class="errorbox">Error loading data: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

$isUsingBonus = CreditLedger::isUsingBonusCredits();
?>
<style>
.cb-lots { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
.cb-stat { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 15px; text-align: center; }
.cb-stat .value { font-size: 24px; font-weight: 700; }
.cb-stat .label { font-size: 12px; color: #666; margin-top: 4px; }
.cb-stat.purchased .value { color: #1a73e8; }
.cb-stat.bonus .value { color: #10b981; }
.cb-stat.consumed .value { color: #6b7280; }
.cb-stat.warning .value { color: #f59e0b; }
.cb-box { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; margin: 20px 0; }
.cb-table { width: 100%; border-collapse: collapse; }
.cb-table th, .cb-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e5e5; }
.cb-table th { background: #f9fafb; font-weight: 600; font-size: 12px; text-transform: uppercase; }
.lot-purchased { color: #1a73e8; }
.lot-bonus { color: #10b981; }
.lot-adjustment { color: #8b5cf6; }
.lot-depleted { opacity: 0.5; }
.progress-bar { height: 20px; background: #e5e5e5; border-radius: 10px; overflow: hidden; position: relative; }
.progress-fill { height: 100%; border-radius: 10px; transition: width 0.3s; }
.progress-purchased { background: linear-gradient(90deg, #1a73e8, #4285f4); }
.progress-bonus { background: linear-gradient(90deg, #10b981, #34d399); }
.alert-box { padding: 15px; border-radius: 8px; margin: 15px 0; }
.alert-warning { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; }
</style>

<div class="cb-lots">
    <h3>💳 Credit Lots (FIFO Tracking)</h3>
    
    <?= $message ?>
    
    <?php if ($isUsingBonus): ?>
    <div class="alert-box alert-warning">
        <strong>⚠️ Using Bonus Credits!</strong> Your purchased credits are depleted. You're now consuming bonus credits.
    </div>
    <?php endif; ?>
    
    <!-- Summary Cards -->
    <div class="cb-summary">
        <div class="cb-stat purchased">
            <div class="value">$<?= number_format($balance['purchased'], 2) ?></div>
            <div class="label">Purchased Remaining</div>
        </div>
        <div class="cb-stat bonus">
            <div class="value">$<?= number_format($balance['bonus'], 2) ?></div>
            <div class="label">Bonus Remaining</div>
        </div>
        <div class="cb-stat">
            <div class="value">$<?= number_format($balance['total'], 2) ?></div>
            <div class="label">Total Balance</div>
        </div>
        <div class="cb-stat consumed">
            <div class="value">$<?= number_format($consumed['total'], 2) ?></div>
            <div class="label">Total Consumed</div>
        </div>
        <div class="cb-stat <?= ($runway['days_remaining'] ?? 999) < 30 ? 'warning' : '' ?>">
            <div class="value"><?= $runway['days_remaining'] !== null ? $runway['days_remaining'] . ' days' : 'N/A' ?></div>
            <div class="label">Runway ($<?= number_format($runway['daily_burn'], 2) ?>/day)</div>
        </div>
    </div>
    
    <!-- Balance Bar -->
    <?php if ($original['total'] > 0): ?>
    <div class="cb-box">
        <h4>Credit Consumption</h4>
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="flex: 1;">
                <div class="progress-bar">
                    <?php 
                    $purchUsedPct = $original['purchased'] > 0 
                        ? (($original['purchased'] - $balance['purchased']) / $original['total']) * 100 
                        : 0;
                    $purchRemPct = $original['purchased'] > 0 
                        ? ($balance['purchased'] / $original['total']) * 100 
                        : 0;
                    $bonusUsedPct = $original['bonus'] > 0 
                        ? (($original['bonus'] - $balance['bonus']) / $original['total']) * 100 
                        : 0;
                    $bonusRemPct = $original['bonus'] > 0 
                        ? ($balance['bonus'] / $original['total']) * 100 
                        : 0;
                    ?>
                    <div class="progress-fill progress-purchased" style="width: <?= $purchRemPct ?>%; float: left;"></div>
                    <div class="progress-fill progress-bonus" style="width: <?= $bonusRemPct ?>%; float: left;"></div>
                </div>
            </div>
            <div style="font-size: 12px; color: #666;">
                <span style="color: #1a73e8;">■</span> Purchased &nbsp;
                <span style="color: #10b981;">■</span> Bonus
            </div>
        </div>
        <p style="font-size: 13px; color: #666; margin-top: 10px;">
            Original: $<?= number_format($original['total'], 2) ?> 
            (Purchased: $<?= number_format($original['purchased'], 2) ?>, 
            Bonus: $<?= number_format($original['bonus'], 2) ?>)
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Actions -->
    <div class="cb-box">
        <h4>Actions</h4>
        
        <form method="post" style="display: inline-block; margin-right: 20px;">
            <?= generate_token('WHMCS.admin.default') ?>
            <input type="hidden" name="cb_action" value="sync_purchases">
            <button type="submit" class="btn btn-default">Sync Lots from Purchases</button>
            <span style="font-size: 12px; color: #666;"> – Create lots for any purchases missing them</span>
        </form>
        
        <hr style="margin: 20px 0;">
        
        <h5>Create Opening Balance</h5>
        <form method="post">
            <?= generate_token('WHMCS.admin.default') ?>
            <input type="hidden" name="cb_action" value="create_opening_balance">
            <p>
                <label>Purchased Credit: $<input type="number" name="purchased_amount" step="0.01" min="0" value="0" style="width: 120px;"></label>
                &nbsp;
                <label>Bonus Credit: $<input type="number" name="bonus_amount" step="0.01" min="0" value="0" style="width: 120px;"></label>
                &nbsp;
                <label>As of: <input type="date" name="as_of_date" value="<?= date('Y-m-d') ?>"></label>
                &nbsp;
                <button type="submit" class="btn btn-primary">Create Opening Balance</button>
            </p>
        </form>
    </div>
    
    <!-- Lots Table -->
    <div class="cb-box">
        <h4>Credit Lots (FIFO Order)</h4>
        <?php if (empty($lots)): ?>
        <p style="color: #666;">No credit lots found. Add purchases or create an opening balance.</p>
        <?php else: ?>
        <table class="cb-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Pack/Purchase</th>
                    <th>Original</th>
                    <th>Remaining</th>
                    <th>Used</th>
                    <th>Created</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lots as $lot): 
                    $isDepleted = (float)$lot->remaining_amount <= 0;
                    $used = (float)$lot->original_amount - (float)$lot->remaining_amount;
                    $usedPct = (float)$lot->original_amount > 0 
                        ? round(($used / (float)$lot->original_amount) * 100, 1) 
                        : 100;
                ?>
                <tr class="<?= $isDepleted ? 'lot-depleted' : '' ?>">
                    <td><?= $lot->id ?></td>
                    <td>
                        <span class="lot-<?= $lot->lot_type ?>">
                            <?= ucfirst($lot->lot_type) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($lot->pack_label): ?>
                        <?= htmlspecialchars($lot->pack_label) ?>
                        <?php elseif ($lot->receipt_no): ?>
                        <?= htmlspecialchars($lot->receipt_no) ?>
                        <?php elseif ($lot->purchase_id): ?>
                        Purchase #<?= $lot->purchase_id ?>
                        <?php else: ?>
                        <em>Opening Balance</em>
                        <?php endif; ?>
                    </td>
                    <td>$<?= number_format((float)$lot->original_amount, 2) ?></td>
                    <td>$<?= number_format((float)$lot->remaining_amount, 2) ?></td>
                    <td>
                        $<?= number_format($used, 2) ?>
                        <span style="font-size: 11px; color: #666;">(<?= $usedPct ?>%)</span>
                    </td>
                    <td><?= date('M j, Y', strtotime($lot->created_at)) ?></td>
                    <td>
                        <?php if ($isDepleted): ?>
                        <span style="color: #6b7280;">Depleted <?= $lot->depleted_at ? date('M j', strtotime($lot->depleted_at)) : '' ?></span>
                        <?php else: ?>
                        <span style="color: #10b981;">Active</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <p>
        <a href="<?= $baseUrl ?>" class="btn btn-default">← Back to Dashboard</a>
        <a href="<?= $baseUrl ?>&action=purchases" class="btn btn-default">View Purchases</a>
    </p>
</div>
