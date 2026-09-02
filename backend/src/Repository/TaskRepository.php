<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use Doctrine\DBAL\Connection;

final readonly class TaskRepository
{
    public function __construct(private Connection $connection, private ActivityLogger $logger)
    {
    }

    /** @return array<string, mixed> */
    public function create(int $noteId, string $content): array
    {
        $content = trim($content);
        if ($content === '' || mb_strlen($content) > 255) {
            throw new \InvalidArgumentException('Task content must contain between 1 and 255 characters.');
        }
        if ($this->connection->fetchOne('SELECT 1 FROM note WHERE id = :id AND deleted_at IS NULL', ['id' => $noteId]) === false) {
            throw new \OutOfBoundsException('Note not found or is in trash.');
        }

        $row = $this->connection->fetchAssociative(<<<'SQL'
INSERT INTO task (note_id, content)
VALUES (:noteId, :content)
RETURNING id, content, is_completed AS "isCompleted", completed_at AS "completedAt", created_at AS "createdAt", updated_at AS "updatedAt"
SQL, ['noteId' => $noteId, 'content' => $content]);

        if ($row === false) {
            throw new \RuntimeException('Unable to create task.');
        }

        $this->connection->executeStatement('UPDATE note SET updated_at = NOW() WHERE id = :id', ['id' => $noteId]);
        $this->logger->log('TASK_CREATED', 'task', (int) $row['id'], ['noteId' => $noteId]);

        return $this->normalize($row);
    }

    /** @return array<string, mixed> */
    public function setCompleted(int $id, bool $completed): array
    {
        $context = $this->connection->fetchAssociative(<<<'SQL'
SELECT t.id, t.note_id AS "noteId", t.is_completed AS "wasCompleted",
       n.label_id AS "labelId", l.name AS "labelName"
FROM task t
JOIN note n ON n.id = t.note_id
LEFT JOIN label l ON l.id = n.label_id
WHERE t.id = :id AND n.deleted_at IS NULL
SQL, ['id' => $id]);
        if ($context === false) {
            throw new \OutOfBoundsException('Task not found.');
        }

        $sql = $completed
            ? 'UPDATE task SET is_completed = TRUE, completed_at = NOW(), updated_at = NOW() WHERE id = :id RETURNING id, content, is_completed AS "isCompleted", completed_at AS "completedAt", created_at AS "createdAt", updated_at AS "updatedAt"'
            : 'UPDATE task SET is_completed = FALSE, completed_at = NULL, updated_at = NOW() WHERE id = :id RETURNING id, content, is_completed AS "isCompleted", completed_at AS "completedAt", created_at AS "createdAt", updated_at AS "updatedAt"';
        $row = $this->connection->fetchAssociative($sql, ['id' => $id]);

        if ($row === false) {
            throw new \RuntimeException('Unable to update task.');
        }

        $this->connection->executeStatement('UPDATE note SET updated_at = NOW() WHERE id = :id', ['id' => (int) $context['noteId']]);
        if ((bool) $context['wasCompleted'] !== $completed) {
            $this->logger->log($completed ? 'TASK_COMPLETED' : 'TASK_UNCOMPLETED', 'task', $id, [
                'noteId' => (int) $context['noteId'],
                'labelId' => $context['labelId'] !== null ? (int) $context['labelId'] : null,
                'labelName' => $context['labelName'],
            ]);
        }

        return $this->normalize($row);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['isCompleted'] = (bool) $row['isCompleted'];

        return $row;
    }

    public function delete(int $id): void
    {
        $task = $this->connection->fetchAssociative('SELECT id, note_id AS "noteId", content FROM task WHERE id = :id', ['id' => $id]);
        if ($task === false) {
            throw new \OutOfBoundsException('Task not found.');
        }

        $this->connection->delete('task', ['id' => $id]);
        $this->connection->executeStatement('UPDATE note SET updated_at = NOW() WHERE id = :id', ['id' => (int) $task['noteId']]);
        $this->logger->log('TASK_DELETED', 'task', $id, ['noteId' => (int) $task['noteId']]);
    }
}
