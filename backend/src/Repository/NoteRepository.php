<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

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
        ?int $projectId = null,
    ): array {
        $userId = $this->currentUser->id();

        if (
            $collectionId !== null
            && $projectId !== null
        ) {
            throw new \InvalidArgumentException(
                'Use either collectionId or projectId when filtering notes.'
            );
        }

        if ($projectId !== null) {
            $this->validateProject(
                $projectId
            );

            $ownerWhere =
                'n.project_id = :projectId';
        } else {
            $ownerWhere =
                'n.user_id = :userId';
        }

        $where = match ($scope) {
            'active' =>
                $ownerWhere
                .' AND n.deleted_at IS NULL'
                .' AND n.archived_at IS NULL',

            'archived' =>
                $ownerWhere
                .' AND n.deleted_at IS NULL'
                .' AND n.archived_at IS NOT NULL',

            'trash' =>
                $ownerWhere
                .' AND n.deleted_at IS NOT NULL',

            default =>
                throw new \InvalidArgumentException(
                    'Unknown note scope.'
                ),
        };

        $params = [
            'userId' => $userId,
        ];

        if ($projectId !== null) {
            $params['projectId'] =
                $projectId;
        }

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
    OR EXISTS (
        SELECT 1
        FROM note_tag search_note_tag
        INNER JOIN tag search_note_tag_value
            ON search_note_tag_value.id =
                search_note_tag.tag_id
        WHERE search_note_tag.note_id = n.id
          AND search_note_tag_value.user_id = :userId
          AND search_note_tag_value.name ILIKE :query
    )
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
    n.is_pinned AS "isPinned",
    n.color,
    (
        SELECT
            '/api/images/'
            || preview_image.id::text
            || '/content'
        FROM note_image preview_link
        INNER JOIN image_asset preview_image
            ON preview_image.id =
                preview_link.image_id
        WHERE preview_link.note_id = n.id
          AND preview_image.user_id = :userId
        ORDER BY
            preview_link.created_at ASC,
            preview_image.id ASC
        LIMIT 1
    ) AS "previewImageUrl",
    n.collection_id AS "collectionId",
    collection.name AS "collectionName",
    collection.color AS "collectionColor",
    n.project_id AS "projectId",
    project.name AS "projectName",
    project.color AS "projectColor",
    n.created_at AS "createdAt",
    n.updated_at AS "updatedAt",
    n.archived_at AS "archivedAt",
    n.deleted_at AS "deletedAt",
    COUNT(t.id) AS "taskCount",
    COUNT(t.id)
        FILTER (WHERE t.is_completed = TRUE)
        AS "completedTaskCount"
FROM note n
LEFT JOIN note_collection collection
    ON collection.id = n.collection_id
   AND collection.user_id = :userId
LEFT JOIN project
    ON project.id = n.project_id
LEFT JOIN task t
    ON t.note_id = n.id
WHERE $where
GROUP BY
    n.id,
    collection.id,
    project.id
ORDER BY
    n.is_pinned DESC,
    n.updated_at DESC,
    n.id DESC
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
        $userId =
            $this->currentUser->id();

        $note =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    n.id,
    n.title,
    n.content,
    n.is_pinned AS "isPinned",
    n.color,
    (
        SELECT
            '/api/images/'
            || preview_image.id::text
            || '/content'
        FROM note_image preview_link
        INNER JOIN image_asset preview_image
            ON preview_image.id =
                preview_link.image_id
        WHERE preview_link.note_id = n.id
          AND preview_image.user_id = :userId
        ORDER BY
            preview_link.created_at ASC,
            preview_image.id ASC
        LIMIT 1
    ) AS "previewImageUrl",
    n.collection_id AS "collectionId",
    collection.name AS "collectionName",
    collection.color AS "collectionColor",
    n.project_id AS "projectId",
    project.name AS "projectName",
    project.color AS "projectColor",
    n.created_at AS "createdAt",
    n.updated_at AS "updatedAt",
    n.archived_at AS "archivedAt",
    n.deleted_at AS "deletedAt"
FROM note n
LEFT JOIN note_collection collection
    ON collection.id = n.collection_id
   AND collection.user_id = :userId
LEFT JOIN project
    ON project.id = n.project_id
WHERE n.id = :id
  AND (
      n.user_id = :userId
      OR EXISTS (
          SELECT 1
          FROM project_member member
          WHERE member.project_id =
                    n.project_id
            AND member.user_id =
                    :userId
      )
  )
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

        $note['tags'] =
            $this->tagsForNote($id);

        $note['id'] =
            (int) $note['id'];

        $note['isPinned'] =
            filter_var(
                $note['isPinned'],
                FILTER_VALIDATE_BOOLEAN,
            );

        $note['collectionId'] =
            $note['collectionId'] !== null
                ? (int) $note['collectionId']
                : null;

        $note['projectId'] =
            $note['projectId'] !== null
                ? (int) $note['projectId']
                : null;

        return $note;
    }

    /**
     * @param list<int> $tagIds
     *
     * @return array<string, mixed>
     */
    public function create(
        string $title,
        string $content,
        array $tagIds,
        ?int $collectionId,
        ?int $projectId,
        bool $isPinned,
        string $color,
    ): array {
        if (
            $collectionId !== null
            && $projectId !== null
        ) {
            throw new \InvalidArgumentException(
                'A note cannot belong to both a collection and a project.'
            );
        }

        $this->validateTitle($title);

        $color =
            $this->validateColor(
                $color
            );

        $tagIds =
            $this->validateTags($tagIds);

        $this->validateCollection(
            $collectionId
        );

        $this->validateProject(
            $projectId
        );

        $userId =
            $this->currentUser->id();

        return $this->connection
            ->transactional(
                function () use (
                    $title,
                    $content,
                    $tagIds,
                    $collectionId,
                    $projectId,
                    $isPinned,
                    $color,
                    $userId,
                ): array {
                    $id =
                        $this->connection
                            ->fetchOne(
                                <<<'SQL'
INSERT INTO note (
    user_id,
    title,
    content,
    collection_id,
    project_id,
    is_pinned,
    color
)
VALUES (
    :userId,
    :title,
    :content,
    :collectionId,
    :projectId,
    :isPinned,
    :color
)
RETURNING id
SQL,
                                [
                                    'userId' =>
                                        $userId,

                                    'title' =>
                                        trim($title),

                                    'content' =>
                                        $content,

                                    'collectionId' =>
                                        $collectionId,

                                    'projectId' =>
                                        $projectId,

                                    'isPinned' =>
                                        $isPinned,

                                    'color' =>
                                        $color,
                                ],
                                [
                                    'isPinned' =>
                                        ParameterType::BOOLEAN,
                                ],
                            );

                    if ($id === false) {
                        throw new \RuntimeException(
                            'Unable to create note.'
                        );
                    }

                    $noteId =
                        (int) $id;

                    $this->syncTags(
                        $noteId,
                        $tagIds,
                    );

                    $this->logger->log(
                        'NOTE_CREATED',
                        'note',
                        $noteId,
                        [
                            'tagIds' =>
                                $tagIds,

                            'collectionId' =>
                                $collectionId,

                            'projectId' =>
                                $projectId,

                            'isPinned' =>
                                $isPinned,

                            'color' =>
                                $color,
                        ],
                    );

                    return $this->get(
                        $noteId
                    );
                },
            );
    }

    /**
     * @param list<int> $tagIds
     *
     * @return array<string, mixed>
     */
    /**
     * @param list<int> $tagIds
     *
     * @return array<string, mixed>
     */
    public function update(
        int $id,
        string $title,
        string $content,
        array $tagIds,
        ?int $collectionId,
        ?int $projectId,
        bool $projectProvided,
        ?bool $isPinned,
        ?string $color,
    ): array {
        $this->validateTitle($title);

        $current =
            $this->get($id);

        if (!$projectProvided) {
            $projectId =
                $current['projectId'] !== null
                    ? (int) $current['projectId']
                    : null;
        }

        if (
            $projectProvided
            && $projectId === null
            && !$this->hasPersonalOwner(
                $id
            )
        ) {
            throw new \InvalidArgumentException(
                'A shared note must belong to a project.'
            );
        }

        $this->validateProject(
            $projectId
        );

        $isPinned ??=
            (bool) $current['isPinned'];

        $color =
            $this->validateColor(
                $color
                ?? (string) $current['color']
            );

        $tagIds =
            $this->validateTags($tagIds);

        $this->validateCollection(
            $collectionId
        );

        $userId =
            $this->currentUser->id();

        return $this->connection
            ->transactional(
                function () use (
                    $id,
                    $title,
                    $content,
                    $tagIds,
                    $collectionId,
                    $projectId,
                    $isPinned,
                    $color,
                    $userId,
                ): array {
                    $affected =
                        $this->connection
                            ->executeStatement(
                                <<<'SQL'
UPDATE note
SET title = :title,
    content = :content,
    collection_id = :collectionId,
    project_id = :projectId,
    is_pinned = :isPinned,
    color = :color,
    updated_at = NOW()
WHERE id = :id
  AND (
      user_id = :userId
      OR EXISTS (
          SELECT 1
          FROM project_member member
          WHERE member.project_id =
                    note.project_id
            AND member.user_id =
                    :userId
      )
  )
  AND deleted_at IS NULL
SQL,
                                [
                                    'id' =>
                                        $id,

                                    'userId' =>
                                        $userId,

                                    'title' =>
                                        trim($title),

                                    'content' =>
                                        $content,

                                    'collectionId' =>
                                        $collectionId,

                                    'projectId' =>
                                        $projectId,

                                    'isPinned' =>
                                        $isPinned,

                                    'color' =>
                                        $color,
                                ],
                                [
                                    'isPinned' =>
                                        ParameterType::BOOLEAN,
                                ],
                            );

                    if ($affected !== 1) {
                        throw new \OutOfBoundsException(
                            'Note not found or is in trash.'
                        );
                    }

                    $this->syncTags(
                        $id,
                        $tagIds,
                    );

                    $this->logger->log(
                        'NOTE_UPDATED',
                        'note',
                        $id,
                        [
                            'tagIds' =>
                                $tagIds,

                            'collectionId' =>
                                $collectionId,

                            'projectId' =>
                                $projectId,

                            'isPinned' =>
                                $isPinned,

                            'color' =>
                                $color,
                        ],
                    );

                    return $this->get($id);
                },
            );
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
    collection_id,
    project_id,
    color
)
VALUES (
    :userId,
    :title,
    :content,
    :collectionId,
    :projectId,
    :color
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

                                'collectionId' =>
                                    $original['collectionId']
                                        !== null
                                            ? (int) $original['collectionId']
                                            : null,

                                'projectId' =>
                                    $original['projectId'] !== null
                                        ? (int) $original['projectId']
                                        : null,

                                'color' =>
                                    (string) $original['color'],
                            ],
                        );

                    if ($newId === false) {
                        throw new \RuntimeException(
                            'Unable to duplicate note.'
                        );
                    }

                    $newNoteId = (int) $newId;

                    foreach (
                        $original['tags']
                        as $tag
                    ) {
                        $this->connection
                            ->insert(
                                'note_tag',
                                [
                                    'note_id' =>
                                        $newNoteId,

                                    'tag_id' =>
                                        (int) $tag['id'],
                                ],
                            );
                    }

                    $this->connection
                        ->executeStatement(
                            <<<'SQL'
INSERT INTO note_image (
    note_id,
    image_id,
    created_at
)
SELECT
    :newNoteId,
    source_image.image_id,
    source_image.created_at
FROM note_image source_image
INNER JOIN image_asset image
    ON image.id = source_image.image_id
WHERE source_image.note_id = :sourceNoteId
  AND image.user_id = :userId
SQL,
                            [
                                'newNoteId' =>
                                    $newNoteId,

                                'sourceNoteId' =>
                                    $id,

                                'userId' =>
                                    $userId,
                            ],
                        );

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
    start_date,
    due_date,
    is_completed
)
VALUES (
    :noteId,
    :content,
    :priority,
    'todo',
    :position,
    :startDate,
    :dueDate,
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

                                        'startDate' =>
                                            $task['startDate'] !== null
                                                ? (string) $task['startDate']
                                                : null,

                                        'dueDate' =>
                                            $task['dueDate'] !== null
                                                ? (string) $task['dueDate']
                                                : null,
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

        $row['isPinned'] =
            filter_var(
                $row['isPinned'],
                FILTER_VALIDATE_BOOLEAN,
            );

        $row['tags'] =
            $this->tagsForNote(
                $row['id']
            );

        $row['collectionId'] =
            $row['collectionId'] !== null
                ? (int) $row['collectionId']
                : null;

        $row['projectId'] =
            $row['projectId'] !== null
                ? (int) $row['projectId']
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

    private function validateColor(
        string $color,
    ): string {
        if (
            preg_match(
                '/^#[0-9A-Fa-f]{6}$/',
                $color,
            ) !== 1
        ) {
            throw new \InvalidArgumentException(
                'Note color must be a six-digit hexadecimal color.'
            );
        }

        return strtoupper($color);
    }

    private function validateProject(
        ?int $projectId,
    ): void {
        if ($projectId === null) {
            return;
        }

        $exists =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM project_member
WHERE project_id = :projectId
  AND user_id = :userId
LIMIT 1
SQL,
                    [
                        'projectId' =>
                            $projectId,

                        'userId' =>
                            $this->currentUser
                                ->id(),
                    ],
                );

        if ($exists === false) {
            throw new \InvalidArgumentException(
                'Selected project does not exist.'
            );
        }
    }

    private function hasPersonalOwner(
        int $noteId,
    ): bool {
        return $this->connection
            ->fetchOne(
                <<<'SQL'
SELECT 1
FROM note
WHERE id = :id
  AND user_id IS NOT NULL
LIMIT 1
SQL,
                [
                    'id' => $noteId,
                ],
            ) !== false;
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

    /**
     * @param list<int> $tagIds
     *
     * @return list<int>
     */
    private function validateTags(
        array $tagIds,
    ): array {
        $tagIds = array_values(
            array_unique(
                array_map(
                    static fn (
                        mixed $id,
                    ): int => (int) $id,
                    $tagIds,
                ),
            ),
        );

        if ($tagIds === []) {
            return [];
        }

        foreach ($tagIds as $tagId) {
            if ($tagId <= 0) {
                throw new \InvalidArgumentException(
                    'Tag identifiers must be positive integers.'
                );
            }
        }

        $count = (int) $this->connection
            ->fetchOne(
                <<<'SQL'
SELECT COUNT(*)
FROM tag
WHERE user_id = :userId
  AND id IN (:tagIds)
SQL,
                [
                    'userId' =>
                        $this->currentUser->id(),

                    'tagIds' =>
                        $tagIds,
                ],
                [
                    'tagIds' =>
                        ArrayParameterType::INTEGER,
                ],
            );

        if ($count !== count($tagIds)) {
            throw new \InvalidArgumentException(
                'One or more selected tags do not exist.'
            );
        }

        return $tagIds;
    }

    /**
     * @param list<int> $tagIds
     */
    private function syncTags(
        int $noteId,
        array $tagIds,
    ): void {
        $this->connection
            ->executeStatement(
                <<<'SQL'
DELETE FROM note_tag
USING tag
WHERE note_tag.note_id = :noteId
  AND tag.id = note_tag.tag_id
  AND tag.user_id = :userId
SQL,
                [
                    'noteId' =>
                        $noteId,

                    'userId' =>
                        $this->currentUser
                            ->id(),
                ],
            );

        foreach ($tagIds as $tagId) {
            $this->connection->insert(
                'note_tag',
                [
                    'note_id' => $noteId,
                    'tag_id' => $tagId,
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tagsForNote(
        int $noteId,
    ): array {
        $rows = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    tag.id,
    tag.name,
    tag.color
FROM tag
INNER JOIN note_tag
    ON note_tag.tag_id = tag.id
WHERE note_tag.note_id = :noteId
  AND tag.user_id = :userId
ORDER BY lower(tag.name), tag.id
SQL,
                [
                    'noteId' => $noteId,

                    'userId' =>
                        $this->currentUser->id(),
                ],
            );

        foreach ($rows as &$tag) {
            $tag['id'] =
                (int) $tag['id'];
        }

        unset($tag);

        return $rows;
    }

}
