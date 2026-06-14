<?php
declare(strict_types=1);

namespace Ms365Backup;

final class BackupEngineRegistry
{
    /** @var list<BackupEngineInterface> */
    private array $engines;

    public function __construct()
    {
        $this->engines = [
            new MailBackupEngine(),
            new CalendarBackupEngine(),
            new ContactsBackupEngine(),
            new TasksBackupEngine(),
            new OneDriveBackupEngine(),
            new SharePointSiteBackupEngine(),
            new TeamsBackupEngine(),
            new GroupBackupEngine(),
            new PlannerBackupEngine(),
            new OneNoteBackupEngine(),
            new DirectoryBackupEngine(),
            new DeferredBackupEngine(),
        ];
    }

    /** @return list<BackupEngineInterface> */
    public function enginesFor(PhysicalBackupJob $job, BackupScope $scope): array
    {
        $matched = [];
        foreach ($this->engines as $engine) {
            if ($engine->supports($job, $scope)) {
                $matched[] = $engine;
            }
        }

        return $matched;
    }
}
