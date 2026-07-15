<?php

require_once __DIR__ . '/../../lib/Admin/E3BackupOrphanRemediation.php';

use WHMCS\Module\Addon\CloudStorage\Admin\E3BackupOrphanRemediation;

/**
 * Admin UI for orphaned e3 backup resources after legacy hard user deletes.
 */
function cloudstorage_admin_orphan_remediation($vars): void
{
    if (isset($_REQUEST['cs_action'])) {
        header('Content-Type: application/json');
        try {
            $action = (string) ($_REQUEST['cs_action'] ?? '');
            if ($action === 'scan') {
                $clientId = isset($_REQUEST['client_id']) ? (int) $_REQUEST['client_id'] : 0;
                $scan = E3BackupOrphanRemediation::scan($clientId > 0 ? $clientId : null);
                $counts = [];
                foreach ($scan as $type => $items) {
                    $counts[$type] = count($items);
                }
                echo json_encode(['status' => 'success', 'scan' => $scan, 'counts' => $counts]);
                exit;
            }
            if ($action === 'remediate') {
                $payload = json_decode((string) ($_POST['item'] ?? ''), true);
                if (!is_array($payload)) {
                    echo json_encode(['status' => 'fail', 'message' => 'Invalid item payload.']);
                    exit;
                }
                $dryRun = !isset($_POST['apply']) || (string) $_POST['apply'] !== '1';
                echo json_encode(E3BackupOrphanRemediation::remediate($payload, $dryRun));
                exit;
            }
            if ($action === 'remediate_all') {
                $clientId = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
                $dryRun = !isset($_POST['apply']) || (string) $_POST['apply'] !== '1';
                $scan = E3BackupOrphanRemediation::scan($clientId > 0 ? $clientId : null);
                $results = [];
                foreach ($scan as $items) {
                    foreach ($items as $item) {
                        $results[] = E3BackupOrphanRemediation::remediate($item, $dryRun);
                    }
                }
                echo json_encode(['status' => 'success', 'dry_run' => $dryRun, 'results' => $results]);
                exit;
            }
            echo json_encode(['status' => 'fail', 'message' => 'Unknown action.']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
        }
        exit;
    }

    $baseUrl = $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=orphan_remediation';
    $csrfToken = function_exists('generate_token') ? generate_token('plain') : '';
    ?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">e3 Backup Orphan Remediation</h3>
        </div>
        <div class="panel-body">
            <p>Detect and remediate orphaned jobs, agents, MS365 tenant connections, and MS365 vaults left behind when backup users were hard-deleted.</p>
            <div class="form-inline" style="margin-bottom: 12px;">
                <label for="orphan-client-id">Client ID (optional)</label>
                <input type="number" id="orphan-client-id" class="form-control input-sm" min="0" placeholder="All clients">
                <button type="button" class="btn btn-default btn-sm" id="orphan-scan-btn">Scan</button>
                <button type="button" class="btn btn-warning btn-sm" id="orphan-remediate-dry-btn">Dry-run remediate all</button>
                <button type="button" class="btn btn-danger btn-sm" id="orphan-remediate-apply-btn">Apply remediate all</button>
            </div>
            <pre id="orphan-output" style="max-height: 480px; overflow: auto;">Click Scan to load orphan report.</pre>
        </div>
    </div>
    <script>
    (function () {
        const baseUrl = <?php echo json_encode($baseUrl); ?>;
        const token = <?php echo json_encode($csrfToken); ?>;
        const output = document.getElementById('orphan-output');

        function clientId() {
            const raw = document.getElementById('orphan-client-id').value.trim();
            return raw === '' ? '' : String(parseInt(raw, 10) || '');
        }

        async function post(action, extra) {
            const body = new URLSearchParams(Object.assign({ cs_action: action, token: token }, extra || {}));
            const res = await fetch(baseUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
            return res.json();
        }

        document.getElementById('orphan-scan-btn').addEventListener('click', async function () {
            output.textContent = 'Scanning...';
            const data = await post('scan', clientId() ? { client_id: clientId() } : {});
            output.textContent = JSON.stringify(data, null, 2);
        });

        async function remediateAll(apply) {
            output.textContent = apply ? 'Applying remediation...' : 'Dry-run remediation...';
            const data = await post('remediate_all', Object.assign({ apply: apply ? '1' : '0' }, clientId() ? { client_id: clientId() } : {}));
            output.textContent = JSON.stringify(data, null, 2);
        }

        document.getElementById('orphan-remediate-dry-btn').addEventListener('click', function () { remediateAll(false); });
        document.getElementById('orphan-remediate-apply-btn').addEventListener('click', function () {
            if (!window.confirm('Apply orphan remediation for all detected items?')) return;
            remediateAll(true);
        });
    })();
    </script>
    <?php
}
