<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use Doctrine\DBAL\Connection;

final readonly class NoteRepository
{
    public function __construct(private Connection $connection, private ActivityLogger $logger)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(string $scope = 'active', ?string $query = null): array
    {
        $where = match ($scope) {
            'active' => 'n.deleted_at IS NULL AND n.archived_at IS NULL',
            'archived' => 'n.deleted_at IS NULL AND n.archived_at IS NOT NULL',
            'trash' => 'n.deleted_at IS NOT NULL',
            default => throw new \InvalidArgumentException('Unknown note scope.'),
        };

        $params = [];
        if ($query !== null && trim($query) !== '') {
            $where .= <<<'SQL'
 AND (
    n.title ILIKE :query OR
    n.content ILIKE :query OR
    l.name ILIKE :query OR
    EXISTS (SELECT 1 FROM task search_task WHERE search_task.note_id = n.id AND search_task.content ILIKE :query)
 )
SQL;
            $params['query'] = '%'.trim($query).'%';
        }

        $sql = <<<SQL
SELECT n.id, n.title, n.content,
       n.label_id AS "labelId",
       l.name AS "labelName", l.color AS "labelColor",
       n.created_at AS "createdAt", n.updated_at AS "updatedAt",
       n.archived_at AS "archivedAt", n.deleted_at AS "deletedAt",
       COUNT(t.id) AS "taskCount",
       COUNT(t.id) FILTER (WHERE t.is_completed = TRUE) AS "completedTaskCount"
FROM note n
LEFT JOIN label l ON l.id = n.label_id
LEFT JOIN task t ON t.note_id = n.id
WHERE $where
GROUP BY n.id, l.id
ORDER BY n.updated_at DESC, n.id DESC
SQL;

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        return array_map($this->normalizeSummary(...), $rows);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        $note = $this->connection->fetchAssociative(<<<'SQL'
SELECT n.id, n.title, n.content,
       n.label_id AS "labelId",
       l.name AS "labelName", l.color AS "labelColor",
       n.created_at AS "createdAt", n.updated_at AS "updatedAt",
       n.archived_at AS "archivedAt", n.deleted_at AS "deletedAt"
FROM note n
LEFT JOIN label l ON l.id = n.label_id
WHERE n.id = :id
SQL, ['id' => $id]);

        if ($note === false) {
            throw new \OutOfBoundsException('Note not found.');
        }

        $note['tasks'] = array_map($this->normalizeTask(...), $this->connection->fetchAllAssociative(<<<'SQL'
SELECT id, content, is_completed AS "isCompleted", completed_at AS "completedAt",
       created_at AS "createdAt", updated_at AS "updatedAt"
FROM task
WHERE note_id = :id
ORDER BY is_completed ASC, created_at ASC, id ASC
SQL, ['id' => $id]));

        $note['id'] = (int) $note['id'];
        $note['labelId'] = $note['labelId'] !== null ? (int) $note['labelId'] : null;

        return $note;
    }

    /** @return array<string, mixed> */
    public function create(string $title, string $content, ?int $labelId): array
    {
        $this->validateTitle($title);
        $this->validateLabel($labelId);

        $id = (int) $this->connection->fetchOne(
            'INSERT INTO note (title, content, label_id) VALUES (:title, :content, :labelId) RETURNING id',
            ['title' => trim($title), 'content' => $content, 'labelId' => $labelId],
        );
        $this->logger->log('NOTE_CREATED', 'note', $id, ['labelId' => $labelId]);

        return $this->get($id);
    }

    /** @return array<string, mixed> */
    public function update(int $id, string $title, string $content, ?int $labelId): array
    {
        $this->validateTitle($title);
        $this->validateLabel($labelId);
        $exists = $this->connection->fetchOne('SELECT 1 FROM note WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if ($exists === false) {
            throw new \OutOfBoundsException('Note not found or is in trash.');
        }

        $this->connection->update('note', [
            'title' => trim($title),
            'content' => $content,
            'label_id' => $labelId,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:sP'),
        ], ['id' => $id]);
        $this->logger->log('NOTE_UPDATED', 'note', $id, ['labelId' => $labelId]);

        return $this->get($id);
    }

    /** @return array<string, mixed> */
    public function duplicate(int $id): array
    {
        return $this->connection->transactional(function () use ($id): array {
            $original = $this->get($id);
            if ($original['deletedAt'] !== null) {
                throw new \DomainException('A trashed note cannot be duplicated.');
            }

            $newId = (int) $this->connection->fetchOne(
                'INSERT INTO note (title, content, label_id) VALUES (:title, :content, :labelId) RETURNING id',
                [
                    'title' => mb_substr((string) $original['title'].' (copy)', 0, 255),
                    'content' => (string) $original['content'],
                    'labelId' => $original['labelId'] !== null ? (int) $original['labelId'] : null,
                ],
            );

            foreach ($original['tasks'] as $task) {
                $this->connection->insert('task', [
                    'note_id' => $newId,
                    'content' => (string) $task['content'],
                    'is_completed' => false,
                ]);
            }

            $this->logger->log('NOTE_DUPLICATED', 'note', $newId, ['sourceNoteId' => $id]);

            return $this->get($newId);
        });
    }

    /** @return array<string, mixed> */
    public function archive(int $id, bool $archive): array
    {
        $exists = $this->connection->fetchOne('SELECT 1 FROM note WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if ($exists === false) {
            throw new \OutOfBoundsException('Note not found or is in trash.');
        }

        $this->connection->executeStatement(
            'UPDATE note SET archived_at = :archivedAt, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'archivedAt' => $archive ? (new \DateTimeImmutable())->format('Y-m-d H:i:sP') : null],
        );
        $this->logger->log($archive ? 'NOTE_ARCHIVED' : 'NOTE_UNARCHIVED', 'note', $id);

        return $this->get($id);
    }

    public function trash(int $id): void
    {
        $exists = $this->connection->fetchOne('SELECT 1 FROM note WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if ($exists === false) {
            throw new \OutOfBoundsException('Note not found or already in trash.');
        }

        $this->connection->executeStatement('UPDATE note SET deleted_at = NOW(), archived_at = NULL, updated_at = NOW() WHERE id = :id', ['id' => $id]);
        $this->logger->log('NOTE_TRASHED', 'note', $id);
    }

    /** @return array<string, mixed> */
    public function restore(int $id): array
    {
        $exists = $this->connection->fetchOne('SELECT 1 FROM note WHERE id = :id AND deleted_at IS NOT NULL', ['id' => $id]);
        if ($exists === false) {
            throw new \OutOfBoundsException('Trashed note not found.');
        }

        $this->connection->executeStatement('UPDATE note SET deleted_at = NULL, updated_at = NOW() WHERE id = :id', ['id' => $id]);
        $this->logger->log('NOTE_RESTORED', 'note', $id);

        return $this->get($id);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeSummary(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['labelId'] = $row['labelId'] !== null ? (int) $row['labelId'] : null;
        $row['taskCount'] = (int) $row['taskCount'];
        $row['completedTaskCount'] = (int) $row['completedTaskCount'];

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeTask(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['isCompleted'] = (bool) $row['isCompleted'];

        return $row;
    }

    private function validateTitle(string $title): void
    {
        if (mb_strlen(trim($title)) > 255) {
            throw new \InvalidArgumentException('Note title cannot exceed 255 characters.');
        }
    }

    private function validateLabel(?int $labelId): void
    {
        if ($labelId === null) {
            return;
        }

        if ($this->connection->fetchOne('SELECT 1 FROM label WHERE id = :id', ['id' => $labelId]) === false) {
            throw new \InvalidArgumentException('Selected label does not exist.');
        }
    }
}
