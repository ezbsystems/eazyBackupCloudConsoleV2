<?php
declare(strict_types=1);

use Ms365Backup\BackupRunRepository;
use Ms365Backup\TenantRecordRepository;

$clientId = (int) ($_SESSION['uid'] ?? 0);
$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$tenants = $clientId > 0 ? TenantRecordRepository::listForClient($clientId) : [];
$runs = $clientId > 0 ? BackupRunRepository::listRecentForClient($clientId, 15) : [];
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Microsoft 365 Backup</strong></div>
    <div class="panel-body">
        <p class="text-muted">Manage Microsoft 365 backups for your organization. Backups are stored in secure object storage configured for your account.</p>
        <?php if ($tenants === []): ?>
            <div class="alert alert-info">
                No Microsoft 365 tenant is linked to your account yet. Contact support to connect your Entra ID tenant and enable backup scopes.
            </div>
        <?php else: ?>
            <h4>Linked tenants</h4>
            <ul>
                <?php foreach ($tenants as $tenant): ?>
                    <li><?= $e($tenant['label'] ?? 'Tenant') ?> <small class="text-muted">(<?= $e($tenant['tenant_id'] ?? '') ?>)</small></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h4>Recent backup runs</h4>
        <?php if ($runs === []): ?>
            <p class="text-muted">No backup runs yet.</p>
        <?php else: ?>
            <table class="table table-striped table-condensed">
                <thead>
                    <tr>
                        <th>Resource</th>
                        <th>Status</th>
                        <th>Phase</th>
                        <th>Started</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($runs as $run): ?>
                        <tr>
                            <td><?= $e($run['user_display_name'] ?: $run['physical_key'] ?: $run['id']) ?></td>
                            <td><?= $e($run['status']) ?></td>
                            <td><?= $e($run['phase']) ?></td>
                            <td><?= $e($run['started_at'] ? date('Y-m-d H:i', (int) $run['started_at']) : '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p><small>Scope presets and self-service backup scheduling are available as your administrator enables them. See <code>Docs/CUSTOMER_ONBOARDING.md</code> for connection requirements.</small></p>
    </div>
</div>
