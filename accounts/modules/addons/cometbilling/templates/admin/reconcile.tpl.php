<?php
use WHMCS\Database\Capsule;
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

$usage = Capsule::table('cb_credit_usage')
  ->select('item_type', Capsule::raw('SUM(amount) as amt'))
  ->whereBetween('usage_date', [$from, $to])
  ->groupBy('item_type')
  ->pluck('amt', 'item_type');

$purchases = Capsule::table('cb_credit_purchases')
  ->whereBetween(Capsule::raw('DATE(purchased_at)'), [$from, $to])
  ->sum(Capsule::raw('credit_amount + bonus_credit'));
?>
<h3>Reconciliation</h3>
<form method="get">
  <input type="hidden" name="module" value="cometbilling">
  <input type="hidden" name="action" value="reconcile">
  From <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
  To <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
  <button class="btn btn-default">Apply</button>
  </form>

<h4>Usage by Type</h4>
<table class="datatable" width="50%">
  <thead><tr><th>Type</th><th>Amount</th></tr></thead>
  <tbody>
  <?php foreach ($usage as $type => $amt): ?>
    <tr><td><?= htmlspecialchars($type) ?></td><td><?= number_format((float)$amt, 4) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
  </table>

<p><strong>Total Purchases in Period:</strong> <?= number_format((float)$purchases, 4) ?></p>


