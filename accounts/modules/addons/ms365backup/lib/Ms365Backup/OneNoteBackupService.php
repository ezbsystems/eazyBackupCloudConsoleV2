<?php
declare(strict_types=1);

namespace Ms365Backup;

final class OneNoteBackupService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @param array{owner_kind: string, owner_id: string, notebook_id: string} $context
     * @return array{sections: int, pages: int}
     */
    public function backupNotebook(array $context): array
    {
        $notebookId = (string) ($context['notebook_id'] ?? '');
        $ownerKind = (string) ($context['owner_kind'] ?? 'users');
        $ownerId = (string) ($context['owner_id'] ?? '');
        $basePath = "{$ownerKind}/{$ownerId}/onenote/notebooks/{$notebookId}";

        $this->logger->info('Starting OneNote backup', [
            'notebook_id' => $notebookId,
            'owner' => "{$ownerKind}/{$ownerId}",
        ]);

        $root = $this->storage->onenoteNotebookRoot($notebookId);
        $notebook = $this->graph->get($basePath);
        $this->storage->writeJson($root . '/notebook.json', [
            'fetched_at' => gmdate('c'),
            'owner_kind' => $ownerKind,
            'owner_id' => $ownerId,
            'value' => $notebook,
        ]);

        $sections = [];
        $sectionMonitor = PaginationMonitor::forBackup($this->logger, 'onenote.sections');
        foreach ($this->graph->paginate("{$basePath}/sections", ['$top' => '100'], [], $sectionMonitor) as $section) {
            $this->cancellation?->check();
            $sections[] = $section;
        }
        $this->storage->writeJson($root . '/sections.json', [
            'fetched_at' => gmdate('c'),
            'value' => $sections,
        ]);

        $pageCount = 0;
        foreach ($sections as $section) {
            $this->cancellation?->check();
            $sectionId = (string) ($section['id'] ?? '');
            if ($sectionId === '') {
                continue;
            }
            $pageMonitor = PaginationMonitor::forBackup($this->logger, 'onenote.pages:' . $sectionId);
            foreach ($this->graph->paginate("{$basePath}/sections/{$sectionId}/pages", ['$top' => '100'], [], $pageMonitor) as $page) {
                $this->cancellation?->check();
                $pageId = (string) ($page['id'] ?? '');
                if ($pageId === '') {
                    continue;
                }
                $this->storage->writeJson(
                    $this->storage->onenotePagePath($notebookId, $sectionId, $pageId),
                    $page,
                );
                $pageCount++;
            }
        }

        $stats = ['sections' => count($sections), 'pages' => $pageCount];
        $this->logger->info('OneNote backup finished', $stats);

        return $stats;
    }
}
