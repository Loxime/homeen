<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class TaskRepository
{
    public function __construct(
        private Connection $connection,
        private ActivityLogger $logger,
        private CurrentUser $currentUser,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
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

        $userId =
            $this->currentUser->id();

        $noteExists =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM note
WHERE id = :noteId
  AND user_id = :userId
  AND channel_id IS NULL
  AND deleted_at IS NULL
LIMIT 1
SQL,
                    [
                        'noteId' =>
                            $noteId,

                        'userId' =>
                            $userId,
                    ],
                );

        if ($noteExists === false) {
            throw new \OutOfBoundsException(
                'Note not found or is in trash.'
            );
        }

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
                'Unable to create task.'
            );
        }

        $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET updated_at = NOW()
WHERE id = :noteId
  AND user_id = :userId
  AND channel_id IS NULL
SQL,
                [
                    'noteId' =>
                        $noteId,

                    'userId' =>
                        $userId,
                ],
            );

        $this->logger->log(
            'TASK_CREATED',
            'task',
            (int) $row['id'],
            [
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
        int $id,
        bool $completed,
    ): array {
        $userId =
            $this->currentUser->id();

        $context =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    t.id,
    t.note_id
        AS "noteId",
    t.is_completed
        AS "wasCompleted",
    n.label_id
        AS "labelId",
    l.name
        AS "labelName"
FROM task t
INNER JOIN note n
    ON n.id = t.note_id
LEFT JOIN label l
    ON l.id = n.label_id
   AND l.user_id = :userId
WHERE t.id = :id
  AND n.user_id = :userId
  AND n.channel_id IS NULL
  AND n.deleted_at IS NULL
LIMIT 1
SQL,
                    [
                        'id' =>
                            $id,

                        'userId' =>
                            $userId,
                    ],
                );

        if ($context === false) {
            throw new \OutOfBoundsException(
                'Task not found.'
            );
        }

        $row =
            $this->connection
                ->fetchAssociative(
                    $completed
                        ? <<<'SQL'
UPDATE task
SET is_completed = TRUE,
    completed_at = NOW(),
    updated_at = NOW()
WHERE id = :id
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
WHERE id = :id
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
                        'id' =>
                            $id,

                        'noteId' =>
                            (int) $context[
                                'noteId'
                            ],
                    ],
                );

        if ($row === false) {
            throw new \RuntimeException(
                'Unable to update task.'
            );
        }

        $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET updated_at = NOW()
WHERE id = :noteId
  AND user_id = :userId
  AND channel_id IS NULL
SQL,
                [
                    'noteId' =>
                        (int) $context[
                            'noteId'
                        ],

                    'userId' =>
                        $userId,
                ],
            );

        if (
            filter_var(
                $context['wasCompleted'],
                FILTER_VALIDATE_BOOLEAN,
            ) !== $completed
        ) {
            $this->logger->log(
                $completed
                    ? 'TASK_COMPLETED'
                    : 'TASK_UNCOMPLETED',
                'task',
                $id,
                [
                    'noteId' =>
                        (int) $context[
                            'noteId'
                        ],

                    'labelId' =>
                        $context['labelId']
                            !== null
                                ? (int) $context[
                                    'labelId'
                                ]
                                : null,

                    'labelName' =>
                        $context[
                            'labelName'
                        ],
                ],
            );
        }

        return $this->normalize(
            $row
        );
    }

    public function delete(
        int $id,
    ): void {
        $userId =
            $this->currentUser->id();

        $task =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    t.id,
    t.note_id
        AS "noteId",
    t.content
FROM task t
INNER JOIN note n
    ON n.id = t.note_id
WHERE t.id = :id
  AND n.user_id = :userId
  AND n.channel_id IS NULL
LIMIT 1
SQL,
                    [
                        'id' =>
                            $id,

                        'userId' =>
                            $userId,
                    ],
                );

        if ($task === false) {
            throw new \OutOfBoundsException(
                'Task not found.'
            );
        }

        $affected =
            $this->connection
                ->executeStatement(
                    <<<'SQL'
DELETE FROM task
WHERE id = :id
  AND note_id = :noteId
SQL,
                    [
                        'id' =>
                            $id,

                        'noteId' =>
                            (int) $task[
                                'noteId'
                            ],
                    ],
                );

        if ($affected !== 1) {
            throw new \RuntimeException(
                'Unable to delete task.'
            );
        }

        $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET updated_at = NOW()
WHERE id = :noteId
  AND user_id = :userId
  AND channel_id IS NULL
SQL,
                [
                    'noteId' =>
                        (int) $task[
                            'noteId'
                        ],

                    'userId' =>
                        $userId,
                ],
            );

        $this->logger->log(
            'TASK_DELETED',
            'task',
            $id,
            [
                'noteId' =>
                    (int) $task[
                        'noteId'
                    ],
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
