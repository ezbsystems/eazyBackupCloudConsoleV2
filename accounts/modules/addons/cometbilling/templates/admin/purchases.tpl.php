<?php
use WHMCS\Database\Capsule;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_token('WHMCS.admin.default')) {
    $data = [
        'purchased_at'  => $_POST['purchased_at'],
        'currency'      => $_POST['currency'] ?? 'USD',
        'pack_label'    => $_POST['pack_label'] ?? null,
        'pack_units'    => $_POST['pack_units'] !== '' ? (int)$_POST['pack_units'] : null,
        'credit_amount' => $_POST['credit_amount'],
        'bonus_credit'  => $_POST['bonus_credit'] !== '' ? $_POST['bonus_credit'] : 0,
        'payment_method'=> $_POST['payment_method'] ?? null,
        'receipt_no'    => $_POST['receipt_no'] ?? null,
        'external_ref'  => $_POST['external_ref'] ?? null,
        'notes'         => $_POST['notes'] ?? null,
        'raw_receipt'   => null,
    ];
    Capsule::table('cb_credit_purchases')->insert($data);
    echo '<div class="infobox">Saved.</div>';
}

$rows = Capsule::table('cb_credit_purchases')->orderBy('purchased_at', 'desc')->limit(200)->get();
?>
<h3>Credit Purchases</h3>
<form method="post">
  <?php echo generate_token('WHMCS.admin.default'); ?>
  <p>
    Date/Time (UTC) <input type="datetime-local" name="purchased_at" required>
    Currency <input type="text" name="currency" value="USD" size="4">
    Pack Label <input type="text" name="pack_label" size="16">
    Pack Units <input type="number" name="pack_units" min="0" step="1">
  </p>
  <p>
    Credit Amount <input type="number" name="credit_amount" step="0.0001" required>
    Bonus Credit <input type="number" name="bonus_credit" step="0.0001">
    Payment Method <input type="text" name="payment_method">
    Receipt # <input type="text" name="receipt_no">
    External Ref <input type="text" name="external_ref">
  </p>
  <p>
    Notes <input type="text" name="notes" size="80">
    <button class="btn btn-primary" type="submit">Add Purchase</button>
  </p>
  </form>

<table class="datatable" width="100%">
  <thead><tr>
    <th>Purchased</th><th>Currency</th><th>Pack</th><th>Units</th>
    <th>Credit</th><th>Bonus</th><th>Method</th><th>Receipt</th><th>External</th><th>Notes</th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= htmlspecialchars($r->purchased_at) ?></td>
    <td><?= htmlspecialchars($r->currency) ?></td>
    <td><?= htmlspecialchars($r->pack_label) ?></td>
    <td><?= htmlspecialchars($r->pack_units) ?></td>
    <td><?= htmlspecialchars($r->credit_amount) ?></td>
    <td><?= htmlspecialchars($r->bonus_credit) ?></td>
    <td><?= htmlspecialchars($r->payment_method) ?></td>
    <td><?= htmlspecialchars($r->receipt_no) ?></td>
    <td><?= htmlspecialchars($r->external_ref) ?></td>
    <td><?= htmlspecialchars($r->notes) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
  </table>


