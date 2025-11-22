<?php
use WHMCS\Database\Capsule;
$limit = 500;
$rows = Capsule::table('cb_credit_usage')
    ->orderBy('usage_date', 'desc')
    ->limit($limit)
    ->get();
?>
<h3>Credit Usage (latest <?= $limit ?>)</h3>
<table class="datatable" width="100%">
  <thead>
    <tr>
      <th>Usage Date</th><th>Posted</th><th>Type</th><th>Description</th>
      <th>Qty</th><th>Unit</th><th>Amount</th><th>Tenant</th><th>Device</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r->usage_date) ?></td>
      <td><?= htmlspecialchars($r->posted_at) ?></td>
      <td><?= htmlspecialchars($r->item_type) ?></td>
      <td><?= htmlspecialchars($r->item_desc) ?></td>
      <td><?= htmlspecialchars($r->quantity) ?></td>
      <td><?= htmlspecialchars($r->unit_cost) ?></td>
      <td><?= htmlspecialchars($r->amount) ?></td>
      <td><?= htmlspecialchars($r->tenant_id) ?></td>
      <td><?= htmlspecialchars($r->device_id) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  </table>


