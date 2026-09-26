<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class SearchRepository
{
    public function __construct(
        private Connection $connection,
        private CurrentUser $currentUser,
    ) {
    }

    /**
     * @return array{
     *     notes: list<array<string, mixed>>,
     *     tasks: list<array<string, mixed>>
     * }
     */
    public function search(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [
                'notes' => [],
                'tasks' => [],
            ];
        }

        if (mb_strlen($query) > 200) {
            throw new \InvalidArgumentException(
                'Search query cannot exceed 200 characters.'
            );
        }

        $userId = $this->currentUser->id();

        $params = [
            'userId' => $userId,
            'query' => '%'.$query.'%',
        ];

        $notes = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    n.id,
    n.title,
    n.content,
    l.name AS "labelName",
    collection.name AS "collectionName",
    n.archived_at AS "archivedAt",
    n.updated_at AS "updatedAt"
FROM note n
LEFT JOIN label l
    ON l.id = n.label_id
   AND l.user_id = :userId
LEFT JOIN note_collection collection
    ON collection.id = n.collection_id
   AND collection.user_id = :userId
WHERE n.user_id = :userId
  AND n.channel_id IS NULL
  AND n.deleted_at IS NULL
  AND (
      n.title ILIKE :query
      OR n.content ILIKE :query
      OR l.name ILIKE :query
      OR collection.name ILIKE :query
  )
ORDER BY
    n.updated_at DESC,
    n.id DESC
LIMIT 50
SQL,
                $params,
            );

        foreach ($notes as &$note) {
            $note['id'] = (int) $note['id'];
        }

        unset($note);

        $tasks = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    t.id,
    t.note_id AS "noteId",
    n.title AS "noteTitle",
    t.content,
    t.priority,
    t.status,
    t.start_date AS "startDate",
    t.due_date AS "dueDate",
    n.archived_at AS "noteArchivedAt",
    t.updated_at AS "updatedAt"
FROM task t
INNER JOIN note n
    ON n.id = t.note_id
WHERE n.user_id = :userId
  AND n.channel_id IS NULL
  AND n.deleted_at IS NULL
  AND (
      t.content ILIKE :query
      OR EXISTS (
          SELECT 1
          FROM task_tag search_task_tag
          INNER JOIN tag search_tag
              ON search_tag.id =
                  search_task_tag.tag_id
          WHERE search_task_tag.task_id = t.id
            AND search_tag.user_id = :userId
            AND search_tag.name ILIKE :query
      )
  )
ORDER BY
    t.updated_at DESC,
    t.id DESC
LIMIT 50
SQL,
                $params,
            );

        foreach ($tasks as &$task) {
            $task['id'] = (int) $task['id'];
            $task['noteId'] =
                (int) $task['noteId'];
        }

        unset($task);

        return [
            'notes' => $notes,
            'tasks' => $tasks,
        ];
    }
}
