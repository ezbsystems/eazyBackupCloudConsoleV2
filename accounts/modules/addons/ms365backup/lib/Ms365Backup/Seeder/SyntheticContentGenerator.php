<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

final class SyntheticContentGenerator
{
    private const SUBJECTS = [
        'Q4 planning sync',
        'Invoice review',
        'Project kickoff notes',
        'Weekly status update',
        'Client feedback summary',
        'Budget approval request',
        'Team offsite agenda',
        'Security patch window',
        'Vendor contract renewal',
        'Onboarding checklist',
    ];

    private const EVENT_TITLES = [
        'Standup',
        'Sprint review',
        '1:1 meeting',
        'Budget review',
        'Training session',
        'All-hands',
        'Client demo',
        'Architecture review',
    ];

    public function __construct(
        private readonly string $runId,
    ) {
    }

    public function mailSubject(int $index): string
    {
        $base = self::SUBJECTS[$index % count(self::SUBJECTS)];

        return $base . ' #' . ($index + 1) . ' [' . substr($this->runId, 0, 8) . ']';
    }

    public function mailBody(int $index): string
    {
        return '<p>Automated seed message ' . ($index + 1) . ' for backup testing.</p>'
            . '<p>Run: <code>' . htmlspecialchars($this->runId, ENT_QUOTES, 'UTF-8') . '</code></p>'
            . '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt.</p>';
    }

    public function eventTitle(int $index): string
    {
        return self::EVENT_TITLES[$index % count(self::EVENT_TITLES)] . ' ' . ($index + 1);
    }

    public function contactName(int $index): string
    {
        return 'Seed Contact ' . ($index + 1);
    }

    public function contactEmail(int $index): string
    {
        return 'seed.contact' . ($index + 1) . '@example.test';
    }

    public function taskTitle(int $index): string
    {
        return 'Seed task ' . ($index + 1);
    }

    public function teamsMessage(int $index): string
    {
        return 'Seeder message ' . ($index + 1) . ' — backup QA run ' . substr($this->runId, 0, 8);
    }

    public function fileName(int $index, string $ext = 'txt'): string
    {
        return 'seed-file-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT) . '.' . $ext;
    }

    public function fileContents(int $index, int $sizeKb = 4): string
    {
        $line = 'Seed data line ' . ($index + 1) . ' run=' . $this->runId . "\n";
        $target = max(256, $sizeKb * 1024);
        $out = '';
        while (strlen($out) < $target) {
            $out .= $line;
        }

        return substr($out, 0, $target);
    }
}
