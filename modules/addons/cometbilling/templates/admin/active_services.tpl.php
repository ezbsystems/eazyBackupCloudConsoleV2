<?php
use WHMCS\Database\Capsule;

$rows = Capsule::table('cb_active_services')
    ->orderBy('pulled_at', 'desc')
    ->limit(500)
    ->get();

?>
<h3>Active Services</h3>
<table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
  <thead>
    <tr>
      <th>Pulled At (UTC)</th>
      <th>Service</th>
      <th>Billing Cycle (Days)</th>
      <th>Next Due Date</th>
      <th>Unit Cost</th>
      <th>Quantity</th>
      <th>Amount</th>
      <th>Tenant</th>
      <th>Device</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r->pulled_at) ?></td>
      <td><?= htmlspecialchars($r->service_name) ?></td>
      <td><?= (int)$r->billing_cycle_days ?></td>
      <td><?= htmlspecialchars($r->next_due_date) ?></td>
      <td><?= $r->unit_cost ?></td>
      <td><?= $r->quantity ?></td>
      <td><?= $r->amount ?></td>
      <td><?= htmlspecialchars($r->tenant_id) ?></td>
      <td><?= htmlspecialchars($r->device_id) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  </table>


