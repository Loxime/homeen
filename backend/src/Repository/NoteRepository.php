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
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(
        string $scope = 'active',
        ?string $query = null,
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
LEFT JOIN task t
    ON t.note_id = n.id
WHERE $where
GROUP BY n.id, l.id
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
    n.created_at AS "createdAt",
    n.updated_at AS "updatedAt",
    n.archived_at AS "archivedAt",
    n.deleted_at AS "deletedAt"
FROM note n
LEFT JOIN label l
    ON l.id = n.label_id
   AND l.user_id = :userId
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

        $tasks = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    id,
    content,
    is_completed AS "isCompleted",
    completed_at AS "completedAt",
    created_at AS "createdAt",
    updated_at AS "updatedAt"
FROM task
WHERE note_id = :id
ORDER BY is_completed ASC,
         created_at ASC,
         id ASC
SQL,
                ['id' => $id],
            );

        $note['tasks'] = array_map(
            $this->normalizeTask(...),
            $tasks,
        );

        $note['id'] = (int) $note['id'];

        $note['labelId'] =
            $note['labelId'] !== null
                ? (int) $note['labelId']
                : null;

        return $note;
    }

    /** @return array<string, mixed> */
    public function create(
        string $title,
        string $content,
        ?int $labelId,
    ): array {
        $this->validateTitle($title);
        $this->validateLabel($labelId);

        $userId = $this->currentUser->id();

        $id = $this->connection->fetchOne(
            <<<'SQL'
INSERT INTO note (
    user_id,
    title,
    content,
    label_id
)
VALUES (
    :userId,
    :title,
    :content,
    :labelId
)
RETURNING id
SQL,
            [
                'userId' => $userId,
                'title' => trim($title),
                'content' => $content,
                'labelId' => $labelId,
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
    ): array {
        $this->validateTitle($title);
        $this->validateLabel($labelId);

        $userId = $this->currentUser->id();

        $affected = $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET title = :title,
    content = :content,
    label_id = :labelId,
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
    label_id
)
VALUES (
    :userId,
    :title,
    :content,
    :labelId
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
                        $this->connection
                            ->insert(
                                'task',
                                [
                                    'note_id' =>
                                        $newNoteId,

                                    'content' =>
                                        (string) $task['content'],

                                    'is_completed' =>
                                        false,
                                ],
                            );
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

        $row['taskCount'] =
            (int) $row['taskCount'];

        $row['completedTaskCount'] =
            (int) $row[
                'completedTaskCount'
            ];

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeTask(
        array $row,
    ): array {
        $row['id'] =
            (int) $row['id'];

        $row['isCompleted'] =
            (bool) $row['isCompleted'];

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
