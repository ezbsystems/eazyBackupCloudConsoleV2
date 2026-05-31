<?php
/**
 * Attach e3 Cloud Backup run logs to support tickets server-side.
 *
 * Browser file prefill creates tbltickets.attachment metadata without writing
 * files to WHMCS storage (dl.php 500). This hook runs at shutdown after custom
 * fields are saved, prunes broken tokens, and writes the log to the configured
 * ticket_attachments path (often not the legacy AttachmentsDir setting).
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupRunTicketLog;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupTicketAttachmentStorage;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('TicketOpen', 1, function (array $vars) {
    $ticketId = (int) ($vars['ticketid'] ?? 0);
    $clientId = (int) ($vars['userid'] ?? 0);
    if ($ticketId <= 0 || $clientId <= 0) {
        return;
    }

    register_shutdown_function(static function () use ($ticketId, $clientId) {
        eb_e3_apply_run_ticket_attachment($ticketId, $clientId);
    });
});

function eb_e3_apply_run_ticket_attachment(int $ticketId, int $clientId): void
{
    try {
        CloudBackupTicketAttachmentStorage::pruneBrokenOpeningAttachments($ticketId);

        $runId = eb_e3_resolve_run_id_for_ticket($ticketId);
        if ($runId === '') {
            return;
        }

        if (eb_e3_opening_has_valid_run_log($ticketId, $runId)) {
            return;
        }

        $built = CloudBackupRunTicketLog::buildForRun($runId, $clientId);
        if (!$built || ($built['content'] ?? '') === '') {
            return;
        }

        CloudBackupTicketAttachmentStorage::pruneBrokenOpeningAttachments($ticketId);

        $stored = CloudBackupTicketAttachmentStorage::writeOpeningAttachment(
            $ticketId,
            (string) $built['filename'],
            (string) $built['content']
        );

        if ($stored === '') {
            logActivity('eb_e3_run_ticket_attachment: failed to write log for ticket ' . $ticketId . ' run ' . $runId);
        }
    } catch (\Throwable $e) {
        try {
            logActivity('eb_e3_run_ticket_attachment: ' . $e->getMessage());
        } catch (\Throwable $__) {
        }
    }
}

function eb_e3_resolve_run_id_for_ticket(int $ticketId): string
{
    try {
        $fieldId = (int) (Capsule::table('tblcustomfields')
            ->where('type', 'support')
            ->where('fieldname', 'eb_run_id')
            ->value('id') ?? 0);
        if ($fieldId > 0) {
            $runId = trim((string) (Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $fieldId)
                ->where('relid', $ticketId)
                ->value('value') ?? ''));
            if ($runId !== '') {
                return $runId;
            }
        }
    } catch (\Throwable $e) {
        // continue
    }

    if (Capsule::schema()->hasColumn('tbltickets', 'attachment')) {
        $att = (string) (Capsule::table('tbltickets')->where('id', $ticketId)->value('attachment') ?? '');
        foreach (CloudBackupTicketAttachmentStorage::parseAttachmentField($att) as $part) {
            $runId = CloudBackupTicketAttachmentStorage::runIdFromStoredName($part);
            if ($runId !== '') {
                return $runId;
            }
        }
    }

    return '';
}

function eb_e3_opening_has_valid_run_log(int $ticketId, string $runId): bool
{
    if (!Capsule::schema()->hasColumn('tbltickets', 'attachment')) {
        return false;
    }

    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $runId);
    $safe = trim((string) $safe, '_');
    $needle = 'run-' . $safe;

    $att = (string) (Capsule::table('tbltickets')->where('id', $ticketId)->value('attachment') ?? '');
    foreach (CloudBackupTicketAttachmentStorage::parseAttachmentField($att) as $part) {
        if (stripos($part, $needle) === false) {
            continue;
        }
        if (CloudBackupTicketAttachmentStorage::fileExists($part)) {
            return true;
        }
    }

    return false;
}
