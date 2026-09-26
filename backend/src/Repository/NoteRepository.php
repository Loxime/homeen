<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class NoteRepository
{
    public function __construct(
        private Connection $connection,
        private ActivityLogger $logger,
        private CurrentUser $currentUser,
        private TaskRepository $tasks,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(
        string $scope = 'active',
        ?string $query = null,
        ?int $collectionId = null,
    ): array {
        $userId = $this->currentUser->id();

        $where = match ($scope) {
            'active' =>
                'n.user_id = :userId
                 AND n.deleted_at IS NULL
                 AND n.archived_at IS NULL',

            'archived' =>
                'n.user_id = :userId
                 AND n.deleted_at IS NULL
                 AND n.archived_at IS NOT NULL',

            'trash' =>
                'n.user_id = :userId
                 AND n.deleted_at IS NOT NULL',

            default =>
                throw new \InvalidArgumentException(
                    'Unknown note scope.'
                ),
        };

        $params = [
            'userId' => $userId,
        ];

        if ($collectionId !== null) {
            $this->validateCollection(
                $collectionId
            );

            $where .=
                ' AND n.collection_id = :collectionId';

            $params['collectionId'] =
                $collectionId;
        }

        if (
            $query !== null
            && trim($query) !== ''
        ) {
            $where .= <<<'SQL'
 AND (
    n.title ILIKE :query
    OR n.content ILIKE :query
    OR l.name ILIKE :query
    OR EXISTS (
        SELECT 1
        FROM task search_task
        WHERE search_task.note_id = n.id
          AND search_task.content ILIKE :query
    )
 )
SQL;

            $params['query'] =
                '%'.trim($query).'%';
        }

        $sql = <<<SQL
SELECT
    n.id,
    n.title,
    n.content,
    n.label_id AS "labelId",
    l.name AS "labelName",
    l.color AS "labelColor",
    n.collection_id AS "collectionId",
    collection.name AS "collectionName",
    collection.color AS "collectionColor",
    n.created_at AS "createdAt",
    n.updated_at AS "updatedAt",
    n.archived_at AS "archivedAt",
    n.deleted_at AS "deletedAt",
    COUNT(t.id) AS "taskCount",
    COUNT(t.id)
        FILTER (WHERE t.is_completed = TRUE)
        AS "completedTaskCount"
FROM note n
LEFT JOIN label l
    ON l.id = n.label_id
   AND l.user_id = :userId
LEFT JOIN note_collection collection
    ON collection.id = n.collection_id
   AND collection.user_id = :userId
LEFT JOIN task t
    ON t.note_id = n.id
WHERE $where
GROUP BY n.id, l.id, collection.id
ORDER BY n.updated_at DESC, n.id DESC
SQL;

        $rows = $this->connection
            ->fetchAllAssociative(
                $sql,
                $params,
            );

        return array_map(
            $this->normalizeSummary(...),
            $rows,
        );
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        $userId = $this->currentUser->id();

        $note = $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT
    n.id,
    n.title,
    n.content,
    n.label_id AS "labelId",
    l.name AS "labelName",
    l.color AS "labelColor",
    n.collection_id AS "collectionId",
    collection.name AS "collectionName",
    collection.color AS "collectionColor",
    n.created_at AS "createdAt",
    n.updated_at AS "updatedAt",
    n.archived_at AS "archivedAt",
    n.deleted_at AS "deletedAt"
FROM note n
LEFT JOIN label l
    ON l.id = n.label_id
   AND l.user_id = :userId
LEFT JOIN note_collection collection
    ON collection.id = n.collection_id
   AND collection.user_id = :userId
WHERE n.id = :id
  AND n.user_id = :userId
SQL,
                [
                    'id' => $id,
                    'userId' => $userId,
                ],
            );

        if ($note === false) {
            throw new \OutOfBoundsException(
                'Note not found.'
            );
        }

        $note['tasks'] =
            $this->tasks->forNote($id);

        $note['id'] = (int) $note['id'];

        $note['labelId'] =
            $note['labelId'] !== null
                ? (int) $note['labelId']
                : null;

        $note['collectionId'] =
            $note['collectionId'] !== null
                ? (int) $note['collectionId']
                : null;

        return $note;
    }

    /** @return array<string, mixed> */
    public function create(
        string $title,
        string $content,
        ?int $labelId,
        ?int $collectionId,
    ): array {
        $this->validateTitle($title);
        $this->validateLabel($labelId);
        $this->validateCollection(
            $collectionId
        );

        $userId = $this->currentUser->id();

        $id = $this->connection->fetchOne(
            <<<'SQL'
INSERT INTO note (
    user_id,
    title,
    content,
    label_id,
    collection_id
)
VALUES (
    :userId,
    :title,
    :content,
    :labelId,
    :collectionId
)
RETURNING id
SQL,
            [
                'userId' => $userId,
                'title' => trim($title),
                'content' => $content,
                'labelId' => $labelId,
                'collectionId' =>
                    $collectionId,
            ],
        );

        if ($id === false) {
            throw new \RuntimeException(
                'Unable to create note.'
            );
        }

        $noteId = (int) $id;

        $this->logger->log(
            'NOTE_CREATED',
            'note',
            $noteId,
            [
                'labelId' => $labelId,
                'collectionId' =>
                    $collectionId,
            ],
        );

        return $this->get($noteId);
    }

    /** @return array<string, mixed> */
    public function update(
        int $id,
        string $title,
        string $content,
        ?int $labelId,
        ?int $collectionId,
    ): array {
        $this->validateTitle($title);
        $this->validateLabel($labelId);
        $this->validateCollection(
            $collectionId
        );

        $userId = $this->currentUser->id();

        $affected = $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET title = :title,
    content = :content,
    label_id = :labelId,
    collection_id = :collectionId,
    updated_at = NOW()
WHERE id = :id
  AND user_id = :userId
  AND deleted_at IS NULL
SQL,
                [
                    'id' => $id,
                    'userId' => $userId,
                    'title' => trim($title),
                    'content' => $content,
                    'labelId' => $labelId,
                    'collectionId' =>
                        $collectionId,
                ],
            );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Note not found or is in trash.'
            );
        }

        $this->logger->log(
            'NOTE_UPDATED',
            'note',
            $id,
            [
                'labelId' => $labelId,
                'collectionId' =>
                    $collectionId,
            ],
        );

        return $this->get($id);
    }

    /** @return array<string, mixed> */
    public function duplicate(int $id): array
    {
        return $this->connection
            ->transactional(
                function () use ($id): array {
                    $original =
                        $this->get($id);

                    if (
                        $original['deletedAt']
                        !== null
                    ) {
                        throw new \DomainException(
                            'A trashed note cannot be duplicated.'
                        );
                    }

                    $userId =
                        $this->currentUser->id();

                    $newId = $this->connection
                        ->fetchOne(
                            <<<'SQL'
INSERT INTO note (
    user_id,
    title,
    content,
    label_id,
    collection_id
)
VALUES (
    :userId,
    :title,
    :content,
    :labelId,
    :collectionId
)
RETURNING id
SQL,
                            [
                                'userId' =>
                                    $userId,

                                'title' =>
                                    mb_substr(
                                        (string) $original['title']
                                            .' (copy)',
                                        0,
                                        255,
                                    ),

                                'content' =>
                                    (string) $original['content'],

                                'labelId' =>
                                    $original['labelId']
                                        !== null
                                            ? (int) $original['labelId']
                                            : null,

                                'collectionId' =>
                                    $original['collectionId']
                                        !== null
                                            ? (int) $original['collectionId']
                                            : null,
                            ],
                        );

                    if ($newId === false) {
                        throw new \RuntimeException(
                            'Unable to duplicate note.'
                        );
                    }

                    $newNoteId = (int) $newId;

                    foreach (
                        $original['tasks']
                        as $task
                    ) {
                        $taskId =
                            $this->connection
                                ->fetchOne(
                                    <<<'SQL'
INSERT INTO task (
    note_id,
    content,
    priority,
    status,
    position,
    is_completed
)
VALUES (
    :noteId,
    :content,
    :priority,
    'todo',
    :position,
    FALSE
)
RETURNING id
SQL,
                                    [
                                        'noteId' =>
                                            $newNoteId,

                                        'content' =>
                                            (string) $task['content'],

                                        'priority' =>
                                            (string) $task['priority'],

                                        'position' =>
                                            (int) $task['position'],
                                    ],
                                );

                        if ($taskId === false) {
                            throw new \RuntimeException(
                                'Unable to duplicate task.'
                            );
                        }

                        foreach (
                            $task['tags'] as $tag
                        ) {
                            $this->connection
                                ->insert(
                                    'task_tag',
                                    [
                                        'task_id' =>
                                            (int) $taskId,

                                        'tag_id' =>
                                            (int) $tag['id'],
                                    ],
                                );
                        }
                    }

                    $this->logger->log(
                        'NOTE_DUPLICATED',
                        'note',
                        $newNoteId,
                        [
                            'sourceNoteId' =>
                                $id,
                        ],
                    );

                    return $this->get(
                        $newNoteId
                    );
                },
            );
    }

    /** @return array<string, mixed> */
    public function archive(
        int $id,
        bool $archive,
    ): array {
        $affected = $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET archived_at = :archivedAt,
    updated_at = NOW()
WHERE id = :id
  AND user_id = :userId
  AND deleted_at IS NULL
SQL,
                [
                    'id' => $id,

                    'userId' =>
                        $this->currentUser->id(),

                    'archivedAt' =>
                        $archive
                            ? (new \DateTimeImmutable())
                                ->format(
                                    'Y-m-d H:i:sP'
                                )
                            : null,
                ],
            );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Note not found or is in trash.'
            );
        }

        $this->logger->log(
            $archive
                ? 'NOTE_ARCHIVED'
                : 'NOTE_UNARCHIVED',
            'note',
            $id,
        );

        return $this->get($id);
    }

    public function trash(int $id): void
    {
        $affected = $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET deleted_at = NOW(),
    archived_at = NULL,
    updated_at = NOW()
WHERE id = :id
  AND user_id = :userId
  AND deleted_at IS NULL
SQL,
                [
                    'id' => $id,
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Note not found or already in trash.'
            );
        }

        $this->logger->log(
            'NOTE_TRASHED',
            'note',
            $id,
        );
    }

    /** @return array<string, mixed> */
    public function restore(int $id): array
    {
        $affected = $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET deleted_at = NULL,
    updated_at = NOW()
WHERE id = :id
  AND user_id = :userId
  AND deleted_at IS NOT NULL
SQL,
                [
                    'id' => $id,
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Trashed note not found.'
            );
        }

        $this->logger->log(
            'NOTE_RESTORED',
            'note',
            $id,
        );

        return $this->get($id);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeSummary(
        array $row,
    ): array {
        $row['id'] =
            (int) $row['id'];

        $row['labelId'] =
            $row['labelId'] !== null
                ? (int) $row['labelId']
                : null;

        $row['collectionId'] =
            $row['collectionId'] !== null
                ? (int) $row['collectionId']
                : null;

        $row['taskCount'] =
            (int) $row['taskCount'];

        $row['completedTaskCount'] =
            (int) $row[
                'completedTaskCount'
            ];

        return $row;
    }

    private function validateTitle(
        string $title,
    ): void {
        if (
            mb_strlen(trim($title))
            > 255
        ) {
            throw new \InvalidArgumentException(
                'Note title cannot exceed 255 characters.'
            );
        }
    }

    private function validateCollection(
        ?int $collectionId,
    ): void {
        if ($collectionId === null) {
            return;
        }

        $exists = $this->connection
            ->fetchOne(
                <<<'SQL'
SELECT 1
FROM note_collection
WHERE id = :id
  AND user_id = :userId
SQL,
                [
                    'id' => $collectionId,
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );

        if ($exists === false) {
            throw new \InvalidArgumentException(
                'Selected collection does not exist.'
            );
        }
    }

    private function validateLabel(
        ?int $labelId,
    ): void {
        if ($labelId === null) {
            return;
        }

        $exists = $this->connection
            ->fetchOne(
                <<<'SQL'
SELECT 1
FROM label
WHERE id = :id
  AND user_id = :userId
SQL,
                [
                    'id' => $labelId,
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );

        if ($exists === false) {
            throw new \InvalidArgumentException(
                'Selected label does not exist.'
            );
        }
    }
}
