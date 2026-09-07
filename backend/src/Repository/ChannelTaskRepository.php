<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class ChannelTaskRepository
{
    public function __construct(
        private Connection $connection,
        private ActivityLogger $logger,
        private CurrentUser $currentUser,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(
        string $code,
    ): array {
        $channelId =
            $this->accessibleChannelId(
                $code
            );

        $rows = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    t.id,
    t.note_id
        AS "noteId",
    n.title
        AS "noteTitle",
    t.content,
    t.is_completed
        AS "isCompleted",
    t.completed_at
        AS "completedAt",
    t.created_at
        AS "createdAt",
    t.updated_at
        AS "updatedAt"
FROM task t
INNER JOIN note n
    ON n.id = t.note_id
WHERE n.channel_id = :channelId
  AND n.user_id IS NULL
  AND n.deleted_at IS NULL
  AND n.archived_at IS NULL
ORDER BY
    t.is_completed ASC,
    t.updated_at DESC,
    t.id DESC
SQL,
                [
                    'channelId' =>
                        $channelId,
                ],
            );

        return array_map(
            function (
                array $row,
            ): array {
                $row['id'] =
                    (int) $row['id'];

                $row['noteId'] =
                    (int) $row['noteId'];

                $row['isCompleted'] =
                    filter_var(
                        $row['isCompleted'],
                        FILTER_VALIDATE_BOOLEAN,
                    );

                return $row;
            },
            $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        string $code,
        int $noteId,
        string $content,
    ): array {
        $content = trim($content);

        if (
            $content === ''
            || mb_strlen($content) > 255
        ) {
            throw new \InvalidArgumentException(
                'Task content must contain between 1 and 255 characters.'
            );
        }

        $channelId =
            $this->accessibleChannelId(
                $code
            );

        $this->assertAccessibleNote(
            $channelId,
            $noteId,
        );

        $row = $this->connection
            ->fetchAssociative(
                <<<'SQL'
INSERT INTO task (
    note_id,
    content
)
VALUES (
    :noteId,
    :content
)
RETURNING
    id,
    content,
    is_completed
        AS "isCompleted",
    completed_at
        AS "completedAt",
    created_at
        AS "createdAt",
    updated_at
        AS "updatedAt"
SQL,
                [
                    'noteId' =>
                        $noteId,

                    'content' =>
                        $content,
                ],
            );

        if ($row === false) {
            throw new \RuntimeException(
                'Unable to create channel task.'
            );
        }

        $this->touchNote(
            $channelId,
            $noteId,
        );

        $this->logger->log(
            'CHANNEL_TASK_CREATED',
            'task',
            (int) $row['id'],
            [
                'channelId' =>
                    $channelId,

                'noteId' =>
                    $noteId,
            ],
        );

        return $this->normalize(
            $row
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function setCompleted(
        string $code,
        int $noteId,
        int $taskId,
        bool $completed,
    ): array {
        $channelId =
            $this->accessibleChannelId(
                $code
            );

        $this->assertAccessibleNote(
            $channelId,
            $noteId,
        );

        $task =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    id,
    is_completed
        AS "wasCompleted"
FROM task
WHERE id = :taskId
  AND note_id = :noteId
LIMIT 1
SQL,
                    [
                        'taskId' =>
                            $taskId,

                        'noteId' =>
                            $noteId,
                    ],
                );

        if ($task === false) {
            throw new \OutOfBoundsException(
                'Channel task not found.'
            );
        }

        $row = $this->connection
            ->fetchAssociative(
                $completed
                    ? <<<'SQL'
UPDATE task
SET is_completed = TRUE,
    completed_at = NOW(),
    updated_at = NOW()
WHERE id = :taskId
  AND note_id = :noteId
RETURNING
    id,
    content,
    is_completed
        AS "isCompleted",
    completed_at
        AS "completedAt",
    created_at
        AS "createdAt",
    updated_at
        AS "updatedAt"
SQL
                    : <<<'SQL'
UPDATE task
SET is_completed = FALSE,
    completed_at = NULL,
    updated_at = NOW()
WHERE id = :taskId
  AND note_id = :noteId
RETURNING
    id,
    content,
    is_completed
        AS "isCompleted",
    completed_at
        AS "completedAt",
    created_at
        AS "createdAt",
    updated_at
        AS "updatedAt"
SQL,
                [
                    'taskId' =>
                        $taskId,

                    'noteId' =>
                        $noteId,
                ],
            );

        if ($row === false) {
            throw new \RuntimeException(
                'Unable to update channel task.'
            );
        }

        $this->touchNote(
            $channelId,
            $noteId,
        );

        $wasCompleted =
            filter_var(
                $task['wasCompleted'],
                FILTER_VALIDATE_BOOLEAN,
            );

        if (
            $wasCompleted
            !== $completed
        ) {
            /*
             * We deliberately keep the generic
             * TASK_COMPLETED event here so that
             * collaborative work contributes to
             * the user's personal statistics.
             */
            $this->logger->log(
                $completed
                    ? 'TASK_COMPLETED'
                    : 'TASK_UNCOMPLETED',
                'task',
                $taskId,
                [
                    'channelId' =>
                        $channelId,

                    'channelCode' =>
                        $code,

                    'noteId' =>
                        $noteId,

                    'labelId' =>
                        null,

                    'labelName' =>
                        null,
                ],
            );
        }

        return $this->normalize(
            $row
        );
    }

    public function delete(
        string $code,
        int $noteId,
        int $taskId,
    ): void {
        $channelId =
            $this->accessibleChannelId(
                $code
            );

        $this->assertAccessibleNote(
            $channelId,
            $noteId,
        );

        $taskExists =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM task
WHERE id = :taskId
  AND note_id = :noteId
LIMIT 1
SQL,
                    [
                        'taskId' =>
                            $taskId,

                        'noteId' =>
                            $noteId,
                    ],
                );

        if ($taskExists === false) {
            throw new \OutOfBoundsException(
                'Channel task not found.'
            );
        }

        $affected =
            $this->connection
                ->executeStatement(
                    <<<'SQL'
DELETE FROM task
WHERE id = :taskId
  AND note_id = :noteId
SQL,
                    [
                        'taskId' =>
                            $taskId,

                        'noteId' =>
                            $noteId,
                    ],
                );

        if ($affected !== 1) {
            throw new \RuntimeException(
                'Unable to delete channel task.'
            );
        }

        $this->touchNote(
            $channelId,
            $noteId,
        );

        $this->logger->log(
            'CHANNEL_TASK_DELETED',
            'task',
            $taskId,
            [
                'channelId' =>
                    $channelId,

                'noteId' =>
                    $noteId,
            ],
        );
    }

    private function accessibleChannelId(
        string $code,
    ): int {
        if (
            preg_match(
                '/^\d{9}$/',
                $code,
            ) !== 1
        ) {
            throw new \OutOfBoundsException(
                'Channel not found.'
            );
        }

        $channelId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT channel.id
FROM channel
INNER JOIN channel_member member
    ON member.channel_id = channel.id
   AND member.user_id = :userId
WHERE channel.code = :code
  AND channel.closed_at IS NULL
LIMIT 1
SQL,
                    [
                        'code' =>
                            $code,

                        'userId' =>
                            $this
                                ->currentUser
                                ->id(),
                    ],
                );

        if ($channelId !== false) {
            return (int) $channelId;
        }

        $exists =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM channel
WHERE code = :code
  AND closed_at IS NULL
LIMIT 1
SQL,
                    [
                        'code' =>
                            $code,
                    ],
                );

        if ($exists !== false) {
            throw new \DomainException(
                'CHANNEL_FORBIDDEN'
            );
        }

        throw new \OutOfBoundsException(
            'Channel not found.'
        );
    }

    private function assertAccessibleNote(
        int $channelId,
        int $noteId,
    ): void {
        $exists =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM note
WHERE id = :noteId
  AND channel_id = :channelId
  AND user_id IS NULL
  AND deleted_at IS NULL
LIMIT 1
SQL,
                    [
                        'noteId' =>
                            $noteId,

                        'channelId' =>
                            $channelId,
                    ],
                );

        if ($exists === false) {
            throw new \OutOfBoundsException(
                'Channel note not found or is in trash.'
            );
        }
    }

    private function touchNote(
        int $channelId,
        int $noteId,
    ): void {
        $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET updated_at = NOW()
WHERE id = :noteId
  AND channel_id = :channelId
  AND user_id IS NULL
SQL,
                [
                    'noteId' =>
                        $noteId,

                    'channelId' =>
                        $channelId,
                ],
            );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalize(
        array $row,
    ): array {
        $row['id'] =
            (int) $row['id'];

        $row['isCompleted'] =
            filter_var(
                $row['isCompleted'],
                FILTER_VALIDATE_BOOLEAN,
            );

        return $row;
    }
}
