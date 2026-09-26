<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class TaskRepository
{
    private const PRIORITIES = [
        'low',
        'normal',
        'high',
        'urgent',
    ];

    private const STATUSES = [
        'todo',
        'in_progress',
        'done',
    ];

    public function __construct(
        private Connection $connection,
        private ActivityLogger $logger,
        private CurrentUser $currentUser,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function forNote(int $noteId): array
    {
        $userId = $this->currentUser->id();

        $noteExists = $this->connection->fetchOne(
            <<<'SQL'
SELECT 1
FROM note
WHERE id = :noteId
  AND user_id = :userId
LIMIT 1
SQL,
            [
                'noteId' => $noteId,
                'userId' => $userId,
            ],
        );

        if ($noteExists === false) {
            throw new \OutOfBoundsException(
                'Note not found.'
            );
        }

        $rows = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    t.id,
    t.note_id AS "noteId",
    t.content,
    t.priority,
    t.status,
    t.position,
    t.is_completed AS "isCompleted",
    t.completed_at AS "completedAt",
    t.created_at AS "createdAt",
    t.updated_at AS "updatedAt"
FROM task t
WHERE t.note_id = :noteId
ORDER BY
    t.position ASC,
    t.created_at ASC,
    t.id ASC
SQL,
                [
                    'noteId' => $noteId,
                ],
            );

        return array_map(
            fn (array $row): array =>
                $this->normalize($row),
            $rows,
        );
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        $row = $this->findOwned($id);

        if ($row === false) {
            throw new \OutOfBoundsException(
                'Task not found.'
            );
        }

        return $this->normalize($row);
    }

    /**
     * @param list<int> $tagIds
     *
     * @return array<string, mixed>
     */
    public function create(
        int $noteId,
        string $content,
        string $priority = 'normal',
        string $status = 'todo',
        ?int $position = null,
        array $tagIds = [],
    ): array {
        $content = $this->validateContent($content);
        $priority = $this->validatePriority($priority);
        $status = $this->validateStatus($status);
        $tagIds = $this->validateTagIds($tagIds);

        $userId = $this->currentUser->id();

        $noteExists = $this->connection->fetchOne(
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
                'noteId' => $noteId,
                'userId' => $userId,
            ],
        );

        if ($noteExists === false) {
            throw new \OutOfBoundsException(
                'Note not found or is in trash.'
            );
        }

        if ($position === null) {
            $position = (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COALESCE(MAX(position), -1) + 1
FROM task
WHERE note_id = :noteId
SQL,
                    [
                        'noteId' => $noteId,
                    ],
                );
        }

        $this->validatePosition($position);

        return $this->connection->transactional(
            function () use (
                $noteId,
                $content,
                $priority,
                $status,
                $position,
                $tagIds,
                $userId,
            ): array {
                $completed = $status === 'done';

                $id = $this->connection->fetchOne(
                    <<<'SQL'
INSERT INTO task (
    note_id,
    content,
    priority,
    status,
    position,
    is_completed,
    completed_at
)
VALUES (
    :noteId,
    :content,
    :priority,
    :status,
    :position,
    :completed,
    CASE
        WHEN :completed = TRUE
            THEN NOW()
        ELSE NULL
    END
)
RETURNING id
SQL,
                    [
                        'noteId' => $noteId,
                        'content' => $content,
                        'priority' => $priority,
                        'status' => $status,
                        'position' => $position,
                        'completed' => $completed,
                    ],
                    [
                        'completed' =>
                            ParameterType::BOOLEAN,
                    ],
                );

                if ($id === false) {
                    throw new \RuntimeException(
                        'Unable to create task.'
                    );
                }

                $taskId = (int) $id;

                $this->replaceTags(
                    $taskId,
                    $tagIds,
                );

                $this->touchNote(
                    $noteId,
                    $userId,
                );

                $this->logger->log(
                    'TASK_CREATED',
                    'task',
                    $taskId,
                    [
                        'noteId' => $noteId,
                        'priority' => $priority,
                        'status' => $status,
                    ],
                );

                return $this->get($taskId);
            },
        );
    }

    /**
     * @param list<int> $tagIds
     *
     * @return array<string, mixed>
     */
    public function update(
        int $id,
        string $content,
        string $priority,
        string $status,
        int $position,
        array $tagIds,
    ): array {
        $content = $this->validateContent($content);
        $priority = $this->validatePriority($priority);
        $status = $this->validateStatus($status);
        $this->validatePosition($position);
        $tagIds = $this->validateTagIds($tagIds);

        $context = $this->findOwned($id);

        if ($context === false) {
            throw new \OutOfBoundsException(
                'Task not found.'
            );
        }

        $noteId = (int) $context['noteId'];
        $wasCompleted = filter_var(
            $context['isCompleted'],
            FILTER_VALIDATE_BOOLEAN,
        );

        $completed = $status === 'done';

        return $this->connection->transactional(
            function () use (
                $id,
                $content,
                $priority,
                $status,
                $position,
                $tagIds,
                $noteId,
                $wasCompleted,
                $completed,
            ): array {
                $affected = $this->connection
                    ->executeStatement(
                        <<<'SQL'
UPDATE task
SET content = :content,
    priority = :priority,
    status = :status,
    position = :position,
    is_completed = :completed,
    completed_at = CASE
        WHEN :completed = TRUE
            THEN COALESCE(
                completed_at,
                NOW()
            )
        ELSE NULL
    END,
    updated_at = NOW()
WHERE id = :id
  AND note_id = :noteId
SQL,
                        [
                            'id' => $id,
                            'noteId' => $noteId,
                            'content' => $content,
                            'priority' => $priority,
                            'status' => $status,
                            'position' => $position,
                            'completed' => $completed,
                        ],
                        [
                            'completed' =>
                                ParameterType::BOOLEAN,
                        ],
                    );

                if ($affected !== 1) {
                    throw new \RuntimeException(
                        'Unable to update task.'
                    );
                }

                $this->replaceTags(
                    $id,
                    $tagIds,
                );

                $this->touchNote(
                    $noteId,
                    $this->currentUser->id(),
                );

                $this->logCompletionChange(
                    $id,
                    $noteId,
                    $wasCompleted,
                    $completed,
                );

                $this->logger->log(
                    'TASK_UPDATED',
                    'task',
                    $id,
                    [
                        'noteId' => $noteId,
                        'priority' => $priority,
                        'status' => $status,
                    ],
                );

                return $this->get($id);
            },
        );
    }

    /** @return array<string, mixed> */
    public function setCompleted(
        int $id,
        bool $completed,
    ): array {
        $task = $this->findOwned($id);

        if ($task === false) {
            throw new \OutOfBoundsException(
                'Task not found.'
            );
        }

        $noteId = (int) $task['noteId'];

        $wasCompleted = filter_var(
            $task['isCompleted'],
            FILTER_VALIDATE_BOOLEAN,
        );

        $affected = $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE task
SET is_completed = :completed,
    status = :status,
    completed_at = CASE
        WHEN :completed = TRUE
            THEN COALESCE(
                completed_at,
                NOW()
            )
        ELSE NULL
    END,
    updated_at = NOW()
WHERE id = :id
  AND note_id = :noteId
SQL,
                [
                    'id' => $id,
                    'noteId' => $noteId,
                    'completed' => $completed,
                    'status' =>
                        $completed
                            ? 'done'
                            : 'todo',
                ],
                [
                    'completed' =>
                        ParameterType::BOOLEAN,
                ],
            );

        if ($affected !== 1) {
            throw new \RuntimeException(
                'Unable to update task.'
            );
        }

        $this->touchNote(
            $noteId,
            $this->currentUser->id(),
        );

        $this->logCompletionChange(
            $id,
            $noteId,
            $wasCompleted,
            $completed,
        );

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $task = $this->findOwned($id);

        if ($task === false) {
            throw new \OutOfBoundsException(
                'Task not found.'
            );
        }

        $noteId = (int) $task['noteId'];

        $affected = $this->connection
            ->executeStatement(
                <<<'SQL'
DELETE FROM task
WHERE id = :id
  AND note_id = :noteId
SQL,
                [
                    'id' => $id,
                    'noteId' => $noteId,
                ],
            );

        if ($affected !== 1) {
            throw new \RuntimeException(
                'Unable to delete task.'
            );
        }

        $this->touchNote(
            $noteId,
            $this->currentUser->id(),
        );

        $this->logger->log(
            'TASK_DELETED',
            'task',
            $id,
            [
                'noteId' => $noteId,
            ],
        );
    }

    /**
     * @return array<string, mixed>|false
     */
    private function findOwned(
        int $id,
    ): array|false {
        return $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT
    t.id,
    t.note_id AS "noteId",
    t.content,
    t.priority,
    t.status,
    t.position,
    t.is_completed AS "isCompleted",
    t.completed_at AS "completedAt",
    t.created_at AS "createdAt",
    t.updated_at AS "updatedAt"
FROM task t
INNER JOIN note n
    ON n.id = t.note_id
WHERE t.id = :id
  AND n.user_id = :userId
  AND n.channel_id IS NULL
  AND n.deleted_at IS NULL
LIMIT 1
SQL,
                [
                    'id' => $id,
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );
    }

    /**
     * @param list<int> $tagIds
     */
    private function replaceTags(
        int $taskId,
        array $tagIds,
    ): void {
        $this->connection->delete(
            'task_tag',
            [
                'task_id' => $taskId,
            ],
        );

        foreach ($tagIds as $tagId) {
            $this->connection->insert(
                'task_tag',
                [
                    'task_id' => $taskId,
                    'tag_id' => $tagId,
                ],
            );
        }
    }

    /**
     * @param list<int> $tagIds
     *
     * @return list<int>
     */
    private function validateTagIds(
        array $tagIds,
    ): array {
        $tagIds = array_values(
            array_unique($tagIds)
        );

        foreach ($tagIds as $tagId) {
            if ($tagId <= 0) {
                throw new \InvalidArgumentException(
                    'Tag identifiers must be positive integers.'
                );
            }
        }

        if ($tagIds === []) {
            return [];
        }

        $existing = $this->connection
            ->fetchFirstColumn(
                <<<'SQL'
SELECT id
FROM tag
WHERE user_id = :userId
  AND id IN (:tagIds)
SQL,
                [
                    'userId' =>
                        $this->currentUser->id(),
                    'tagIds' => $tagIds,
                ],
                [
                    'tagIds' =>
                        \Doctrine\DBAL\ArrayParameterType::INTEGER,
                ],
            );

        $existing = array_map(
            static fn (mixed $id): int =>
                (int) $id,
            $existing,
        );

        sort($existing);
        sort($tagIds);

        if ($existing !== $tagIds) {
            throw new \InvalidArgumentException(
                'One or more selected tags do not exist.'
            );
        }

        return $tagIds;
    }

    private function touchNote(
        int $noteId,
        int $userId,
    ): void {
        $this->connection->executeStatement(
            <<<'SQL'
UPDATE note
SET updated_at = NOW()
WHERE id = :noteId
  AND user_id = :userId
  AND channel_id IS NULL
SQL,
            [
                'noteId' => $noteId,
                'userId' => $userId,
            ],
        );
    }

    private function logCompletionChange(
        int $taskId,
        int $noteId,
        bool $wasCompleted,
        bool $completed,
    ): void {
        if ($wasCompleted === $completed) {
            return;
        }

        $label = $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT
    n.label_id AS "labelId",
    l.name AS "labelName"
FROM note n
LEFT JOIN label l
    ON l.id = n.label_id
WHERE n.id = :noteId
  AND n.user_id = :userId
LIMIT 1
SQL,
                [
                    'noteId' => $noteId,
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );

        $this->logger->log(
            $completed
                ? 'TASK_COMPLETED'
                : 'TASK_UNCOMPLETED',
            'task',
            $taskId,
            [
                'noteId' => $noteId,
                'labelId' =>
                    $label !== false
                    && $label['labelId'] !== null
                        ? (int) $label['labelId']
                        : null,
                'labelName' =>
                    $label !== false
                        ? $label['labelName']
                        : null,
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
        $row['id'] = (int) $row['id'];
        $row['noteId'] =
            (int) $row['noteId'];
        $row['position'] =
            (int) $row['position'];

        $row['isCompleted'] = filter_var(
            $row['isCompleted'],
            FILTER_VALIDATE_BOOLEAN,
        );

        $row['tags'] = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    tag.id,
    tag.name,
    tag.color
FROM tag
INNER JOIN task_tag
    ON task_tag.tag_id = tag.id
WHERE task_tag.task_id = :taskId
  AND tag.user_id = :userId
ORDER BY lower(tag.name), tag.id
SQL,
                [
                    'taskId' => $row['id'],
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );

        foreach ($row['tags'] as &$tag) {
            $tag['id'] = (int) $tag['id'];
        }

        unset($tag);

        return $row;
    }

    private function validateContent(
        string $content,
    ): string {
        $content = trim($content);

        if (
            $content === ''
            || mb_strlen($content) > 4000
        ) {
            throw new \InvalidArgumentException(
                'Task content must contain between 1 and 4000 characters.'
            );
        }

        return $content;
    }

    private function validatePriority(
        string $priority,
    ): string {
        if (
            !in_array(
                $priority,
                self::PRIORITIES,
                true,
            )
        ) {
            throw new \InvalidArgumentException(
                'Unknown task priority.'
            );
        }

        return $priority;
    }

    private function validateStatus(
        string $status,
    ): string {
        if (
            !in_array(
                $status,
                self::STATUSES,
                true,
            )
        ) {
            throw new \InvalidArgumentException(
                'Unknown task status.'
            );
        }

        return $status;
    }

    private function validatePosition(
        int $position,
    ): void {
        if ($position < 0) {
            throw new \InvalidArgumentException(
                'Task position cannot be negative.'
            );
        }
    }
}
