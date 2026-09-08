<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class ChannelNoteRepository
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
        string $scope = 'active',
        ?string $query = null,
    ): array {
        $channelId =
            $this->accessibleChannelId($code);

        $where = match ($scope) {
            'active' =>
                'n.channel_id = :channelId
                 AND n.user_id IS NULL
                 AND n.deleted_at IS NULL
                 AND n.archived_at IS NULL',

            'archived' =>
                'n.channel_id = :channelId
                 AND n.user_id IS NULL
                 AND n.deleted_at IS NULL
                 AND n.archived_at IS NOT NULL',

            'trash' =>
                'n.channel_id = :channelId
                 AND n.user_id IS NULL
                 AND n.deleted_at IS NOT NULL',

            default =>
                throw new \InvalidArgumentException(
                    'Unknown note scope.'
                ),
        };

        $params = [
            'channelId' => $channelId,
        ];

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
        FROM task search_task
        WHERE search_task.note_id = n.id
          AND search_task.content ILIKE :query
    )
 )
SQL;

            $params['query'] =
                '%'.trim($query).'%';
        }

        $rows = $this->connection
            ->fetchAllAssociative(
                <<<SQL
SELECT
    n.id,
    n.title,
    n.content,
    n.version,
    n.created_by_user_id
        AS "createdByUserId",
    creator_email.email
        AS "createdByEmail",
    n.created_at
        AS "createdAt",
    n.updated_at
        AS "updatedAt",
    n.archived_at
        AS "archivedAt",
    n.deleted_at
        AS "deletedAt",
    COUNT(t.id)
        AS "taskCount",
    COUNT(t.id)
        FILTER (
            WHERE t.is_completed = TRUE
        )
        AS "completedTaskCount"
FROM note n
LEFT JOIN user_email creator_email
    ON creator_email.user_id =
        n.created_by_user_id
   AND creator_email.is_primary = TRUE
LEFT JOIN task t
    ON t.note_id = n.id
WHERE $where
GROUP BY
    n.id,
    creator_email.email
ORDER BY
    n.updated_at DESC,
    n.id DESC
SQL,
                $params,
            );

        return array_map(
            $this->normalizeSummary(...),
            $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get(
        string $code,
        int $noteId,
    ): array {
        $channelId =
            $this->accessibleChannelId($code);

        $note = $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT
    n.id,
    n.title,
    n.content,
    n.version,
    n.created_by_user_id
        AS "createdByUserId",
    creator_email.email
        AS "createdByEmail",
    n.created_at
        AS "createdAt",
    n.updated_at
        AS "updatedAt",
    n.archived_at
        AS "archivedAt",
    n.deleted_at
        AS "deletedAt"
FROM note n
LEFT JOIN user_email creator_email
    ON creator_email.user_id =
        n.created_by_user_id
   AND creator_email.is_primary = TRUE
WHERE n.id = :noteId
  AND n.channel_id = :channelId
  AND n.user_id IS NULL
SQL,
                [
                    'noteId' => $noteId,
                    'channelId' => $channelId,
                ],
            );

        if ($note === false) {
            throw new \OutOfBoundsException(
                'Channel note not found.'
            );
        }

        $tasks = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
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
FROM task
WHERE note_id = :noteId
ORDER BY
    is_completed ASC,
    created_at ASC,
    id ASC
SQL,
                [
                    'noteId' => $noteId,
                ],
            );

        $note['tasks'] = array_map(
            $this->normalizeTask(...),
            $tasks,
        );

        return $this->normalizeNote(
            $note
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        string $code,
        string $title,
        string $content,
    ): array {
        $this->validateTitle($title);

        $channelId =
            $this->accessibleChannelId($code);

        $userId =
            $this->currentUser->id();

        $id = $this->connection
            ->fetchOne(
                <<<'SQL'
INSERT INTO note (
    user_id,
    channel_id,
    created_by_user_id,
    label_id,
    title,
    content,
    version
)
VALUES (
    NULL,
    :channelId,
    :createdByUserId,
    NULL,
    :title,
    :content,
    1
)
RETURNING id
SQL,
                [
                    'channelId' =>
                        $channelId,

                    'createdByUserId' =>
                        $userId,

                    'title' =>
                        trim($title),

                    'content' =>
                        $content,
                ],
            );

        if ($id === false) {
            throw new \RuntimeException(
                'Unable to create channel note.'
            );
        }

        $noteId = (int) $id;

        $this->logger->log(
            'CHANNEL_NOTE_CREATED',
            'note',
            $noteId,
            [
                'channelId' =>
                    $channelId,
            ],
        );

        return $this->get(
            $code,
            $noteId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function update(
        string $code,
        int $noteId,
        string $title,
        string $content,
        int $expectedVersion,
    ): array {
        $this->validateTitle($title);
        $this->validateVersion(
            $expectedVersion
        );

        $channelId =
            $this->accessibleChannelId($code);

        $affected = $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET title = :title,
    content = :content,
    version = version + 1,
    updated_at = NOW()
WHERE id = :noteId
  AND channel_id = :channelId
  AND user_id IS NULL
  AND deleted_at IS NULL
  AND version = :expectedVersion
SQL,
                [
                    'noteId' =>
                        $noteId,

                    'channelId' =>
                        $channelId,

                    'title' =>
                        trim($title),

                    'content' =>
                        $content,

                    'expectedVersion' =>
                        $expectedVersion,
                ],
            );

        if ($affected !== 1) {
            $exists = $this->connection
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

            if ($exists !== false) {
                throw new \DomainException(
                    'NOTE_VERSION_CONFLICT'
                );
            }

            throw new \OutOfBoundsException(
                'Channel note not found or is in trash.'
            );
        }

        $this->logger->log(
            'CHANNEL_NOTE_UPDATED',
            'note',
            $noteId,
            [
                'channelId' =>
                    $channelId,

                'previousVersion' =>
                    $expectedVersion,

                'version' =>
                    $expectedVersion + 1,
            ],
        );

        return $this->get(
            $code,
            $noteId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function duplicate(
        string $code,
        int $noteId,
    ): array {
        return $this->connection
            ->transactional(
                function () use (
                    $code,
                    $noteId,
                ): array {
                    $channelId =
                        $this->accessibleChannelId(
                            $code
                        );

                    $original =
                        $this->get(
                            $code,
                            $noteId,
                        );

                    if (
                        $original['deletedAt']
                        !== null
                    ) {
                        throw new \DomainException(
                            'A trashed note cannot be duplicated.'
                        );
                    }

                    $newId = $this->connection
                        ->fetchOne(
                            <<<'SQL'
INSERT INTO note (
    user_id,
    channel_id,
    created_by_user_id,
    label_id,
    title,
    content,
    version
)
VALUES (
    NULL,
    :channelId,
    :createdByUserId,
    NULL,
    :title,
    :content,
    1
)
RETURNING id
SQL,
                            [
                                'channelId' =>
                                    $channelId,

                                'createdByUserId' =>
                                    $this
                                        ->currentUser
                                        ->id(),

                                'title' =>
                                    mb_substr(
                                        (string) $original[
                                            'title'
                                        ]
                                        .' (copy)',
                                        0,
                                        255,
                                    ),

                                'content' =>
                                    (string) $original[
                                        'content'
                                    ],
                            ],
                        );

                    if ($newId === false) {
                        throw new \RuntimeException(
                            'Unable to duplicate channel note.'
                        );
                    }

                    $newNoteId =
                        (int) $newId;

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
                                        (string) $task[
                                            'content'
                                        ],

                                    'is_completed' =>
                                        false,
                                ],
                            );
                    }

                    $this->logger->log(
                        'CHANNEL_NOTE_DUPLICATED',
                        'note',
                        $newNoteId,
                        [
                            'channelId' =>
                                $channelId,

                            'sourceNoteId' =>
                                $noteId,
                        ],
                    );

                    return $this->get(
                        $code,
                        $newNoteId,
                    );
                },
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function archive(
        string $code,
        int $noteId,
        bool $archive,
    ): array {
        $channelId =
            $this->accessibleChannelId($code);

        $affected = $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET archived_at = :archivedAt,
    updated_at = NOW(),
    version = version + 1
WHERE id = :noteId
  AND channel_id = :channelId
  AND user_id IS NULL
  AND deleted_at IS NULL
SQL,
                [
                    'noteId' =>
                        $noteId,

                    'channelId' =>
                        $channelId,

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
                'Channel note not found or is in trash.'
            );
        }

        $this->logger->log(
            $archive
                ? 'CHANNEL_NOTE_ARCHIVED'
                : 'CHANNEL_NOTE_UNARCHIVED',
            'note',
            $noteId,
            [
                'channelId' =>
                    $channelId,
            ],
        );

        return $this->get(
            $code,
            $noteId,
        );
    }

    public function trash(
        string $code,
        int $noteId,
    ): void {
        $channelId =
            $this->accessibleChannelId($code);

        $affected = $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET deleted_at = NOW(),
    archived_at = NULL,
    updated_at = NOW(),
    version = version + 1
WHERE id = :noteId
  AND channel_id = :channelId
  AND user_id IS NULL
  AND deleted_at IS NULL
SQL,
                [
                    'noteId' =>
                        $noteId,

                    'channelId' =>
                        $channelId,
                ],
            );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Channel note not found or already in trash.'
            );
        }

        $this->logger->log(
            'CHANNEL_NOTE_TRASHED',
            'note',
            $noteId,
            [
                'channelId' =>
                    $channelId,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(
        string $code,
        int $noteId,
    ): array {
        $channelId =
            $this->accessibleChannelId($code);

        $affected = $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET deleted_at = NULL,
    updated_at = NOW(),
    version = version + 1
WHERE id = :noteId
  AND channel_id = :channelId
  AND user_id IS NULL
  AND deleted_at IS NOT NULL
SQL,
                [
                    'noteId' =>
                        $noteId,

                    'channelId' =>
                        $channelId,
                ],
            );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Trashed channel note not found.'
            );
        }

        $this->logger->log(
            'CHANNEL_NOTE_RESTORED',
            'note',
            $noteId,
            [
                'channelId' =>
                    $channelId,
            ],
        );

        return $this->get(
            $code,
            $noteId,
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

        $channelId = $this->connection
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
                        $this->currentUser->id(),
                ],
            );

        if ($channelId !== false) {
            return (int) $channelId;
        }

        $exists = $this->connection
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

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeSummary(
        array $row,
    ): array {
        $row['id'] =
            (int) $row['id'];

        $row['version'] =
            (int) $row['version'];

        $row['createdByUserId'] =
            $row['createdByUserId']
                !== null
                    ? (int) $row[
                        'createdByUserId'
                    ]
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
     *
     * @return array<string, mixed>
     */
    private function normalizeNote(
        array $row,
    ): array {
        $row['id'] =
            (int) $row['id'];

        $row['version'] =
            (int) $row['version'];

        $row['createdByUserId'] =
            $row['createdByUserId']
                !== null
                    ? (int) $row[
                        'createdByUserId'
                    ]
                    : null;

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeTask(
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

    private function validateTitle(
        string $title,
    ): void {
        if (
            mb_strlen(
                trim($title)
            ) > 255
        ) {
            throw new \InvalidArgumentException(
                'Note title cannot exceed 255 characters.'
            );
        }
    }

    private function validateVersion(
        int $version,
    ): void {
        if ($version < 1) {
            throw new \InvalidArgumentException(
                'Note version must be greater than zero.'
            );
        }
    }
}
