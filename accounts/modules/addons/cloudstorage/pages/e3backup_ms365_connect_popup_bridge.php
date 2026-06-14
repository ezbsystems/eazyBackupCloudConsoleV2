<?php
/**
 * Minimal popup bridge after MS365 admin consent (no WHMCS chrome).
 *
 * @var string $bridgeStatus success|error
 * @var string $bridgeError
 * @var string $bridgeBackupUserId public_id for opener validation
 */
$bridgeStatus = ($bridgeStatus ?? '') === 'success' ? 'success' : 'error';
$bridgeError = (string) ($bridgeError ?? '');
$bridgeBackupUserId = (string) ($bridgeBackupUserId ?? '');

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');

$statusJs = json_encode($bridgeStatus, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$userJs = json_encode($bridgeBackupUserId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$errorJs = json_encode($bridgeError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Microsoft 365 connection</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 2rem 1.5rem; background: #f8fafc; color: #0f172a; text-align: center; }
        .card { max-width: 24rem; margin: 2rem auto; padding: 1.5rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 0.75rem; }
        .ok { color: #15803d; }
        .err { color: #b91c1c; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($bridgeStatus === 'success'): ?>
            <p class="ok"><strong>Microsoft 365 connected.</strong></p>
            <p>This window will close automatically. If it does not, you can close it and return to the backup wizard.</p>
        <?php else: ?>
            <p class="err"><strong>Connection failed</strong></p>
            <p><?= htmlspecialchars($bridgeError, ENT_QUOTES, 'UTF-8') ?></p>
            <p>You can close this window and try again from the wizard.</p>
        <?php endif; ?>
    </div>
    <script>
    (function () {
        var payload = {
            type: 'ms365_connect_result',
            status: <?= $statusJs ?>,
            backupUserId: <?= $userJs ?>,
            error: <?= $errorJs ?>
        };
        function notifyParent() {
            try {
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage(payload, window.location.origin);
                }
            } catch (e) {}
            try {
                if (typeof BroadcastChannel !== 'undefined') {
                    var channel = new BroadcastChannel('ms365_connect_result');
                    channel.postMessage(payload);
                    channel.close();
                }
            } catch (e2) {}
            try {
                sessionStorage.setItem('ms365_connect_result', JSON.stringify({
                    at: Date.now(),
                    payload: payload
                }));
            } catch (e3) {}
        }
        notifyParent();
        setTimeout(function () {
            try { window.close(); } catch (e4) {}
        }, 400);
    })();
    </script>
</body>
</html>
