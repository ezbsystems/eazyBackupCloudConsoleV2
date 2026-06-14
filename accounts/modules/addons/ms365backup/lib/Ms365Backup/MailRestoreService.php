<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Restores mailbox folders/messages from backed-up JSON in object storage (mail-first restore).
 */
final class MailRestoreService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly BackupStorageInterface $storage,
        private readonly StorageLayout $layout,
        private readonly string $userId,
    ) {
    }

    public function restoreMailbox(string $restoreRunId, string $mailboxPath): void
    {
        RestoreRunRepository::update($restoreRunId, [
            'status' => 'running',
            'phase' => 'mail_import',
            'started_at' => time(),
        ]);

        $manifestPath = rtrim($mailboxPath, '/') . '/manifest.json';
        $manifest = $this->storage->readJson($manifestPath);
        if ($manifest === null) {
            throw new \RuntimeException('Mailbox manifest not found at ' . $manifestPath);
        }

        $folders = $manifest['folders'] ?? [];
        if (!is_array($folders)) {
            $folders = [];
        }

        $imported = 0;
        foreach ($folders as $folder) {
            if (!is_array($folder)) {
                continue;
            }
            $folderId = (string) ($folder['id'] ?? '');
            $messagesPath = rtrim($mailboxPath, '/') . '/folders/' . $folderId . '/messages.json';
            $messages = $this->storage->readJson($messagesPath);
            if ($messages === null) {
                continue;
            }
            foreach ($messages['value'] ?? [] as $message) {
                if (!is_array($message)) {
                    continue;
                }
                $this->importMessage($folderId, $message);
                $imported++;
            }
            RestoreRunRepository::update($restoreRunId, ['phase' => 'mail_import:' . $imported]);
        }

        RestoreRunRepository::update($restoreRunId, [
            'status' => 'success',
            'phase' => 'complete',
            'finished_at' => time(),
        ]);
    }

    /** @param array<string, mixed> $message */
    private function importMessage(string $folderId, array $message): void
    {
        $body = $message;
        unset($body['@odata.context'], $body['id']);
        $targetFolder = $folderId !== '' ? $folderId : 'inbox';
        $this->graph->post('users/' . $this->userId . '/mailFolders/' . $targetFolder . '/messages', $body);
    }
}
