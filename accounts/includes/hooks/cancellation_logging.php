<?php

use WHMCS\Database\Capsule;

/**
 * Log cancellation details and append a client note when a request is submitted.
 * Hooked to ClientAreaPageCancellation so we have $vars like service id, client, and requested flag.
 */
add_hook('ClientAreaPageCancellation', 2, function (array $vars) {
    try {
        // Only proceed after a successful request or on the explicit submit POST
        $isPostSubmit = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')
            && isset($_POST['sub']) && $_POST['sub'] === 'submit';
        $isRequested = !empty($vars['requested']);
        if (!$isPostSubmit && !$isRequested) {
            return $vars;
        }

        $clientId = (int)($vars['client']->id ?? ($_SESSION['uid'] ?? 0));
        if ($clientId <= 0) {
            return $vars;
        }

        $serviceId = (int)($vars['id'] ?? 0);
        // Avoid duplicate note creation on refresh
        $dedupeKey = 'cancel_note_logged_' . $serviceId;
        if ($serviceId > 0 && !empty($_SESSION[$dedupeKey])) {
            return $vars;
        }

        // Collect details
        $clientEmail = (string)(Capsule::table('tblclients')->where('id', $clientId)->value('email') ?? '');
        $ipAddress   = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $userAgent   = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $nowUtc      = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s') . ' UTC';

        $type        = isset($_POST['type']) ? (string)$_POST['type'] : '';
        $reason      = isset($_POST['cancellationreason']) ? trim((string)$_POST['cancellationreason']) : '';

        // Optional: include the service next due date to indicate likely removal date for EoBP
        $nextDue = '';
        if ($serviceId > 0) {
            $nextDue = (string)(Capsule::table('tblhosting')->where('id', $serviceId)->value('nextduedate') ?? '');
        }

        $lines = [];
        $lines[] = 'Service cancellation requested';
        $lines[] = 'Date/Time: ' . $nowUtc;
        $lines[] = 'Client Email: ' . $clientEmail;
        $lines[] = 'IP: ' . $ipAddress;
        $lines[] = 'User Agent: ' . $userAgent;
        $lines[] = 'Service ID: ' . $serviceId;
        if ($type !== '') {
            $lines[] = 'Cancellation Type: ' . $type;
        }
        if ($nextDue !== '') {
            $lines[] = 'Service Next Due Date: ' . $nextDue;
        }
        if ($reason !== '') {
            $lines[] = 'Reason: ' . $reason;
        }
        $noteBody = implode("\n", $lines);

        // Persist: admin activity log + client note
        logActivity('[Cancellation] ' . ($clientEmail !== '' ? $clientEmail . ' - ' : '') . "Service {$serviceId} requested cancellation", $clientId);

        // Use the built-in API to create a client note (non-destructive; does not overwrite client notes field)
        // If your install requires an admin username, set as third param or define an admin in configuration.
        @localAPI('AddClientNote', ['userid' => $clientId, 'notes' => $noteBody]);

        if ($serviceId > 0) {
            $_SESSION[$dedupeKey] = 1;
        }
    } catch (\Throwable $e) {
        // Don't break the client flow if logging fails
        try { logActivity('[Cancellation logging error] ' . $e->getMessage()); } catch (\Throwable $_) {}
    }

    return $vars;
});


