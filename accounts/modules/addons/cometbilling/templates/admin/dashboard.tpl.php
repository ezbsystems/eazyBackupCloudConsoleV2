<?php
use WHMCS\Database\Capsule;

$usageCount = Capsule::table('cb_credit_usage')->count();
$svcCount   = Capsule::table('cb_active_services')->count();
$purchSum   = Capsule::table('cb_credit_purchases')->sum(Capsule::raw('credit_amount + bonus_credit'));
$lastBal    = Capsule::table('cb_daily_balance')->orderBy('balance_date','desc')->first();
?>
<h3>Comet Billing Dashboard</h3>
<ul>
  <li><strong>Total Usage Rows:</strong> <?= (int)$usageCount ?></li>
  <li><strong>Active Service Rows:</strong> <?= (int)$svcCount ?></li>
  <li><strong>Total Purchases (lifetime):</strong> <?= number_format((float)$purchSum, 4) ?></li>
  <li><strong>Last Closing Balance:</strong> <?= $lastBal ? number_format((float)$lastBal->closing_credit, 4) : 'n/a' ?></li>
  <li><strong>Last Balance Date:</strong> <?= $lastBal ? htmlspecialchars($lastBal->balance_date) : 'n/a' ?></li>
  <li>
    <form method="post" action="addonmodules.php?module=cometbilling&action=pullnow" style="display:inline">
      <?php echo generate_token('WHMCS.admin.default'); ?>
      <button class="btn btn-primary" type="submit">Pull Now</button>
    </form>
  </li>
  <li><a href="addonmodules.php?module=cometbilling&action=usage">View Usage</a></li>
  <li><a href="addonmodules.php?module=cometbilling&action=active_services">View Active Services</a></li>
  <li><a href="addonmodules.php?module=cometbilling&action=reconcile">Reconcile</a></li>
  <li><a href="addonmodules.php?module=cometbilling&action=purchases">Credit Purchases</a></li>
  <li><a href="addonmodules.php?module=cometbilling&action=keys">Additional API Keys</a></li>
</ul>


