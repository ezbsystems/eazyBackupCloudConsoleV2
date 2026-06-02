<?php
declare(strict_types=1);

namespace Ms365Backup;

final class ChannelMessageStore
{
    public function __construct(
        private readonly StorageLayout $storage,
        private readonly string $groupId,
        private readonly string $channelId,
        private readonly string $runId,
    ) {
    }

    /**
     * @param array<string, mixed> $item
     * @return array{action: string, id: string}
     */
    public function writeRemoved(array $item): array
    {
        $id = (string) ($item['id'] ?? '');
        if ($id === '') {
            return ['action' => 'skip', 'id' => ''];
        }
        $this->storage->writeJson(
            $this->storage->teamChannelMessageRemovedPath($this->groupId, $this->channelId, $id),
            [
                '@removed' => $item['@removed'] ?? [],
                'id' => $id,
                'removedAt' => gmdate('c'),
                'runId' => $this->runId,
            ],
        );

        return ['action' => 'removed', 'id' => $id];
    }

    /**
     * @param array<string, mixed> $message
     * @return array{action: string, id: string}
     */
    public function writeMessage(array $message): array
    {
        $id = (string) ($message['id'] ?? '');
        if ($id === '') {
            return ['action' => 'skip', 'id' => ''];
        }

        $path = $this->storage->teamChannelMessagePath($this->groupId, $this->channelId, $id);
        $existed = is_file($path);
        $this->storage->writeJson($path, [
            'teamId' => $this->groupId,
            'channelId' => $this->channelId,
            'messageId' => $id,
            'rawGraphJson' => $message,
            'backedUpAt' => gmdate('c'),
            'runId' => $this->runId,
        ]);

        return ['action' => $existed ? 'updated' : 'created', 'id' => $id];
    }

    /**
     * @param array<string, mixed> $reply
     * @return array{action: string, id: string}
     */
    public function writeReply(string $parentMessageId, array $reply): array
    {
        $id = (string) ($reply['id'] ?? '');
        if ($id === '' || $parentMessageId === '') {
            return ['action' => 'skip', 'id' => ''];
        }

        $path = $this->storage->teamChannelReplyPath($this->groupId, $this->channelId, $parentMessageId, $id);
        $existed = is_file($path);
        $this->storage->writeJson($path, [
            'teamId' => $this->groupId,
            'channelId' => $this->channelId,
            'parentMessageId' => $parentMessageId,
            'replyId' => $id,
            'rawGraphJson' => $reply,
            'backedUpAt' => gmdate('c'),
            'runId' => $this->runId,
        ]);

        return ['action' => $existed ? 'updated' : 'created', 'id' => $id];
    }
}
