<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Canonical backup scope flags for a physical job.
 */
final class BackupScope
{
    public const MAIL = 'mail';
    public const CALENDAR = 'calendar';
    public const CONTACTS = 'contacts';
    public const TASKS = 'tasks';
    public const ONEDRIVE = 'onedrive';
    public const FILES = 'files';
    public const LISTS = 'lists';
    public const TEAMS_METADATA = 'teams_metadata';
    public const TEAMS_MESSAGES = 'teams_messages';
    public const PLANNER = 'planner';
    public const ONENOTE = 'onenote';

    /** @var array<string, bool> */
    private array $flags;

    /** @param array<string, bool> $flags */
    public function __construct(array $flags = [])
    {
        $this->flags = $flags;
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public static function forResourceType(string $resourceType): self
    {
        $scope = new self();
        foreach (TenantResource::capabilityChips($resourceType) as $chip) {
            $key = self::chipToKey($chip);
            if ($key !== '') {
                $scope->flags[$key] = false;
            }
        }

        if (in_array($resourceType, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
            $scope->flags[self::MAIL] = true;
            $scope->flags[self::CALENDAR] = true;
        }

        return $scope;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromJson(?array $data): self
    {
        if ($data === null || $data === []) {
            return self::empty();
        }
        $flags = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $flags[$key] = (bool) $value;
            }
        }

        return new self($flags);
    }

    /**
     * @param array<string, mixed> $run
     */
    public static function fromLegacyRun(array $run): self
    {
        $raw = $run['scope_json'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::fromJson($decoded);
            }
        }

        return new self([
            self::MAIL => (bool) ($run['backup_mail'] ?? true),
            self::CALENDAR => (bool) ($run['backup_calendar'] ?? true),
        ]);
    }

    public function merge(self $other): self
    {
        $merged = $this->flags;
        foreach ($other->flags as $key => $enabled) {
            if ($enabled) {
                $merged[$key] = true;
            }
        }

        return new self($merged);
    }

    public function isEnabled(string $capability): bool
    {
        return (bool) ($this->flags[$capability] ?? false);
    }

    public function hasAnyEnabled(): bool
    {
        foreach ($this->flags as $enabled) {
            if ($enabled) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, bool> */
    public function toArray(): array
    {
        return $this->flags;
    }

    public function toJson(): string
    {
        return json_encode($this->flags, JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public static function chipToKey(string $chip): string
    {
        $normalized = strtolower(str_replace(' ', '_', $chip));

        return match ($normalized) {
            'mail' => self::MAIL,
            'calendar' => self::CALENDAR,
            'contacts' => self::CONTACTS,
            'tasks' => self::TASKS,
            'onedrive' => self::ONEDRIVE,
            'files' => self::FILES,
            'files_via_sharepoint' => self::FILES,
            'lists' => self::LISTS,
            'metadata' => self::TEAMS_METADATA,
            'teams_metadata' => self::TEAMS_METADATA,
            'channels' => self::TEAMS_MESSAGES,
            'messages' => self::TEAMS_MESSAGES,
            'teams_messages' => self::TEAMS_MESSAGES,
            'planner' => self::PLANNER,
            'onenote' => self::ONENOTE,
            default => $normalized,
        };
    }
}
