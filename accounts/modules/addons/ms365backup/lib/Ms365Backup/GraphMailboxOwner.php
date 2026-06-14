<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Graph mail/calendar owner: /users/{id} or /groups/{id}.
 */
final class GraphMailboxOwner
{
    private function __construct(
        private readonly string $prefix,
        private readonly string $id,
    ) {
    }

    public static function user(string $userId): self
    {
        return new self('users', $userId);
    }

    public static function group(string $groupId): self
    {
        return new self('groups', $groupId);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function isGroup(): bool
    {
        return $this->prefix === 'groups';
    }

    /** Graph path segment e.g. users/{id} or groups/{id} */
    public function graphSegment(): string
    {
        return $this->prefix . '/' . $this->id;
    }

    public function graphPath(string $relative): string
    {
        return $this->graphSegment() . '/' . ltrim($relative, '/');
    }
}
