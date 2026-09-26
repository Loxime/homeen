<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class ProjectTaskRepository
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

    /**
     * @return list<array<string, mixed>>
     */
    public function all(
        int $projectId,
    ): array {
        $this->assertProjectAccess(
            $projectId
        );

        $rows =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    task.id,
    task.note_id AS "noteId",
    task.project_id AS "projectId",
    task.workflow_stage_id
        AS "workflowStageId",
    stage.name
        AS "workflowStageName",
    stage.position
        AS "workflowStagePosition",
    task.content,
    task.priority,
    task.status,
    task.position,
    task.start_date AS "startDate",
    task.due_date AS "dueDate",
    task.is_completed AS "isCompleted",
    task.completed_at AS "completedAt",
    task.created_at AS "createdAt",
    task.updated_at AS "updatedAt"
FROM task
INNER JOIN project_workflow_stage stage
    ON stage.id =
        task.workflow_stage_id
   AND stage.project_id =
        task.project_id
WHERE task.project_id = :projectId
  AND task.note_id IS NULL
ORDER BY
    stage.position ASC,
    task.position ASC,
    task.created_at ASC,
    task.id ASC
SQL,
                    [
                        'projectId' =>
                            $projectId,
                    ],
                );

        return array_map(
            $this->normalize(...),
            $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get(
        int $projectId,
        int $taskId,
    ): array {
        $this->assertProjectAccess(
            $projectId
        );

        $task =
            $this->findTask(
                $projectId,
                $taskId,
            );

        if ($task === false) {
            throw new \OutOfBoundsException(
                'Project task not found.'
            );
        }

        return $this->normalize(
            $task
        );
    }

    /**
     * @param list<int> $tagIds
     *
     * @return array<string, mixed>
     */
    public function create(
        int $projectId,
        string $content,
        string $priority = 'normal',
        string $status = 'todo',
        ?int $workflowStageId = null,
        ?int $position = null,
        array $tagIds = [],
        ?string $startDate = null,
        ?string $dueDate = null,
    ): array {
        $this->assertProjectAccess(
            $projectId
        );

        $content =
            $this->validateContent(
                $content
            );

        $priority =
            $this->validatePriority(
                $priority
            );

        $status =
            $this->validateStatus(
                $status
            );

        $tagIds =
            $this->validateTagIds(
                $tagIds
            );

        $startDate =
            $this->validateDate(
                $startDate,
                'startDate',
            );

        $dueDate =
            $this->validateDate(
                $dueDate,
                'dueDate',
            );

        $this->validateDateRange(
            $startDate,
            $dueDate,
        );

        $workflowStageId =
            $this->resolveStageId(
                $projectId,
                $workflowStageId,
            );

        if ($position === null) {
            $position =
                $this->nextPosition(
                    $projectId,
                    $workflowStageId,
                );
        }

        $this->validatePosition(
            $position
        );

        $completed =
            $status === 'done';

        return $this->connection
            ->transactional(
                function (
                    Connection $connection,
                ) use (
                    $projectId,
                    $content,
                    $priority,
                    $status,
                    $workflowStageId,
                    $position,
                    $tagIds,
                    $startDate,
                    $dueDate,
                    $completed,
                ): array {
                    $taskId =
                        $connection
                            ->fetchOne(
                                <<<'SQL'
INSERT INTO task (
    note_id,
    project_id,
    workflow_stage_id,
    content,
    priority,
    status,
    position,
    start_date,
    due_date,
    is_completed,
    completed_at
)
VALUES (
    NULL,
    :projectId,
    :workflowStageId,
    :content,
    :priority,
    :status,
    :position,
    :startDate,
    :dueDate,
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
                                    'projectId' =>
                                        $projectId,

                                    'workflowStageId' =>
                                        $workflowStageId,

                                    'content' =>
                                        $content,

                                    'priority' =>
                                        $priority,

                                    'status' =>
                                        $status,

                                    'position' =>
                                        $position,

                                    'startDate' =>
                                        $startDate,

                                    'dueDate' =>
                                        $dueDate,

                                    'completed' =>
                                        $completed,
                                ],
                                [
                                    'completed' =>
                                        ParameterType::BOOLEAN,
                                ],
                            );

                    if ($taskId === false) {
                        throw new \RuntimeException(
                            'Unable to create project task.'
                        );
                    }

                    $taskId =
                        (int) $taskId;

                    $this->replaceTags(
                        $taskId,
                        $tagIds,
                    );

                    $this->touchProject(
                        $projectId
                    );

                    $this->logger->log(
                        'PROJECT_TASK_CREATED',
                        'task',
                        $taskId,
                        [
                            'projectId' =>
                                $projectId,

                            'workflowStageId' =>
                                $workflowStageId,

                            'priority' =>
                                $priority,

                            'status' =>
                                $status,
                        ],
                    );

                    return $this->get(
                        $projectId,
                        $taskId,
                    );
                },
            );
    }

    /**
     * @param list<int> $tagIds
     *
     * @return array<string, mixed>
     */
    public function update(
        int $projectId,
        int $taskId,
        string $content,
        string $priority,
        string $status,
        int $workflowStageId,
        ?int $position,
        array $tagIds,
        ?string $startDate,
        ?string $dueDate,
    ): array {
        $current =
            $this->get(
                $projectId,
                $taskId,
            );

        $content =
            $this->validateContent(
                $content
            );

        $priority =
            $this->validatePriority(
                $priority
            );

        $status =
            $this->validateStatus(
                $status
            );

        $workflowStageId =
            $this->resolveStageId(
                $projectId,
                $workflowStageId,
            );

        $tagIds =
            $this->validateTagIds(
                $tagIds
            );

        $startDate =
            $this->validateDate(
                $startDate,
                'startDate',
            );

        $dueDate =
            $this->validateDate(
                $dueDate,
                'dueDate',
            );

        $this->validateDateRange(
            $startDate,
            $dueDate,
        );

        $previousStageId =
            (int) $current[
                'workflowStageId'
            ];

        if ($position === null) {
            $position =
                $workflowStageId
                    === $previousStageId
                ? (int) $current[
                    'position'
                ]
                : $this->nextPosition(
                    $projectId,
                    $workflowStageId,
                );
        }

        $this->validatePosition(
            $position
        );

        $wasCompleted =
            (bool) $current[
                'isCompleted'
            ];

        $completed =
            $status === 'done';

        return $this->connection
            ->transactional(
                function (
                    Connection $connection,
                ) use (
                    $projectId,
                    $taskId,
                    $content,
                    $priority,
                    $status,
                    $workflowStageId,
                    $position,
                    $tagIds,
                    $startDate,
                    $dueDate,
                    $wasCompleted,
                    $completed,
                ): array {
                    $affected =
                        $connection
                            ->executeStatement(
                                <<<'SQL'
UPDATE task
SET
    content = :content,
    priority = :priority,
    status = :status,
    workflow_stage_id =
        :workflowStageId,
    position = :position,
    start_date = :startDate,
    due_date = :dueDate,
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
WHERE id = :taskId
  AND project_id = :projectId
  AND note_id IS NULL
SQL,
                                [
                                    'taskId' =>
                                        $taskId,

                                    'projectId' =>
                                        $projectId,

                                    'content' =>
                                        $content,

                                    'priority' =>
                                        $priority,

                                    'status' =>
                                        $status,

                                    'workflowStageId' =>
                                        $workflowStageId,

                                    'position' =>
                                        $position,

                                    'startDate' =>
                                        $startDate,

                                    'dueDate' =>
                                        $dueDate,

                                    'completed' =>
                                        $completed,
                                ],
                                [
                                    'completed' =>
                                        ParameterType::BOOLEAN,
                                ],
                            );

                    if ($affected !== 1) {
                        throw new \RuntimeException(
                            'Unable to update project task.'
                        );
                    }

                    $this->replaceTags(
                        $taskId,
                        $tagIds,
                    );

                    $this->touchProject(
                        $projectId
                    );

                    $this->logCompletionChange(
                        $taskId,
                        $projectId,
                        $workflowStageId,
                        $wasCompleted,
                        $completed,
                    );

                    $this->logger->log(
                        'PROJECT_TASK_UPDATED',
                        'task',
                        $taskId,
                        [
                            'projectId' =>
                                $projectId,

                            'workflowStageId' =>
                                $workflowStageId,

                            'priority' =>
                                $priority,

                            'status' =>
                                $status,
                        ],
                    );

                    return $this->get(
                        $projectId,
                        $taskId,
                    );
                },
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function setCompleted(
        int $projectId,
        int $taskId,
        bool $completed,
    ): array {
        $current =
            $this->get(
                $projectId,
                $taskId,
            );

        $wasCompleted =
            (bool) $current[
                'isCompleted'
            ];

        $affected =
            $this->connection
                ->executeStatement(
                    <<<'SQL'
UPDATE task
SET
    is_completed = :completed,
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
WHERE id = :taskId
  AND project_id = :projectId
  AND note_id IS NULL
SQL,
                    [
                        'taskId' =>
                            $taskId,

                        'projectId' =>
                            $projectId,

                        'completed' =>
                            $completed,

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
                'Unable to update project task.'
            );
        }

        $this->touchProject(
            $projectId
        );

        $this->logCompletionChange(
            $taskId,
            $projectId,
            (int) $current[
                'workflowStageId'
            ],
            $wasCompleted,
            $completed,
        );

        return $this->get(
            $projectId,
            $taskId,
        );
    }

    public function delete(
        int $projectId,
        int $taskId,
    ): void {
        $this->get(
            $projectId,
            $taskId,
        );

        $affected =
            $this->connection
                ->executeStatement(
                    <<<'SQL'
DELETE FROM task
WHERE id = :taskId
  AND project_id = :projectId
  AND note_id IS NULL
SQL,
                    [
                        'taskId' =>
                            $taskId,

                        'projectId' =>
                            $projectId,
                    ],
                );

        if ($affected !== 1) {
            throw new \RuntimeException(
                'Unable to delete project task.'
            );
        }

        $this->touchProject(
            $projectId
        );

        $this->logger->log(
            'PROJECT_TASK_DELETED',
            'task',
            $taskId,
            [
                'projectId' =>
                    $projectId,
            ],
        );
    }

    private function assertProjectAccess(
        int $projectId,
    ): void {
        $exists =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM project
INNER JOIN project_member member
    ON member.project_id =
        project.id
   AND member.user_id =
        :userId
WHERE project.id = :projectId
  AND project.archived_at IS NULL
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
            throw new \OutOfBoundsException(
                'Project not found.'
            );
        }
    }

    /**
     * @return array<string, mixed>|false
     */
    private function findTask(
        int $projectId,
        int $taskId,
    ): array|false {
        return $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT
    task.id,
    task.note_id AS "noteId",
    task.project_id AS "projectId",
    task.workflow_stage_id
        AS "workflowStageId",
    stage.name
        AS "workflowStageName",
    stage.position
        AS "workflowStagePosition",
    task.content,
    task.priority,
    task.status,
    task.position,
    task.start_date AS "startDate",
    task.due_date AS "dueDate",
    task.is_completed AS "isCompleted",
    task.completed_at AS "completedAt",
    task.created_at AS "createdAt",
    task.updated_at AS "updatedAt"
FROM task
INNER JOIN project_workflow_stage stage
    ON stage.id =
        task.workflow_stage_id
   AND stage.project_id =
        task.project_id
WHERE task.id = :taskId
  AND task.project_id = :projectId
  AND task.note_id IS NULL
LIMIT 1
SQL,
                [
                    'taskId' =>
                        $taskId,

                    'projectId' =>
                        $projectId,
                ],
            );
    }

    private function resolveStageId(
        int $projectId,
        ?int $workflowStageId,
    ): int {
        if ($workflowStageId === null) {
            $stageId =
                $this->connection
                    ->fetchOne(
                        <<<'SQL'
SELECT id
FROM project_workflow_stage
WHERE project_id = :projectId
ORDER BY
    position ASC,
    id ASC
LIMIT 1
SQL,
                        [
                            'projectId' =>
                                $projectId,
                        ],
                    );

            if ($stageId === false) {
                throw new \DomainException(
                    'PROJECT_WORKFLOW_EMPTY'
                );
            }

            return (int) $stageId;
        }

        if ($workflowStageId <= 0) {
            throw new \InvalidArgumentException(
                'Workflow stage identifier must be positive.'
            );
        }

        $exists =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM project_workflow_stage
WHERE id = :stageId
  AND project_id = :projectId
LIMIT 1
SQL,
                    [
                        'stageId' =>
                            $workflowStageId,

                        'projectId' =>
                            $projectId,
                    ],
                );

        if ($exists === false) {
            throw new \OutOfBoundsException(
                'Workflow stage not found.'
            );
        }

        return $workflowStageId;
    }

    private function nextPosition(
        int $projectId,
        int $workflowStageId,
    ): int {
        return (int) $this->connection
            ->fetchOne(
                <<<'SQL'
SELECT
    COALESCE(
        MAX(position),
        -1
    ) + 1
FROM task
WHERE project_id = :projectId
  AND workflow_stage_id =
        :workflowStageId
  AND note_id IS NULL
SQL,
                [
                    'projectId' =>
                        $projectId,

                    'workflowStageId' =>
                        $workflowStageId,
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
        /*
         * Tags stay personal metadata even on
         * collaborative Project tasks.
         */
        $this->connection
            ->executeStatement(
                <<<'SQL'
DELETE FROM task_tag
USING tag
WHERE task_tag.task_id = :taskId
  AND tag.id = task_tag.tag_id
  AND tag.user_id = :userId
SQL,
                [
                    'taskId' =>
                        $taskId,

                    'userId' =>
                        $this->currentUser
                            ->id(),
                ],
            );

        foreach ($tagIds as $tagId) {
            $this->connection->insert(
                'task_tag',
                [
                    'task_id' =>
                        $taskId,

                    'tag_id' =>
                        $tagId,
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
        $tagIds =
            array_values(
                array_unique(
                    $tagIds
                ),
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

        $existing =
            $this->connection
                ->fetchFirstColumn(
                    <<<'SQL'
SELECT id
FROM tag
WHERE user_id = :userId
  AND id IN (:tagIds)
SQL,
                    [
                        'userId' =>
                            $this->currentUser
                                ->id(),

                        'tagIds' =>
                            $tagIds,
                    ],
                    [
                        'tagIds' =>
                            ArrayParameterType::INTEGER,
                    ],
                );

        $existing =
            array_map(
                static fn (
                    mixed $id,
                ): int => (int) $id,
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

    private function touchProject(
        int $projectId,
    ): void {
        $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE project
SET updated_at = NOW()
WHERE id = :projectId
SQL,
                [
                    'projectId' =>
                        $projectId,
                ],
            );
    }

    private function logCompletionChange(
        int $taskId,
        int $projectId,
        int $workflowStageId,
        bool $wasCompleted,
        bool $completed,
    ): void {
        if ($wasCompleted === $completed) {
            return;
        }

        $tags =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    tag.id,
    tag.name
FROM tag
INNER JOIN task_tag
    ON task_tag.tag_id =
        tag.id
WHERE task_tag.task_id = :taskId
  AND tag.user_id = :userId
ORDER BY
    lower(tag.name),
    tag.id
SQL,
                    [
                        'taskId' =>
                            $taskId,

                        'userId' =>
                            $this->currentUser
                                ->id(),
                    ],
                );

        foreach ($tags as &$tag) {
            $tag['id'] =
                (int) $tag['id'];
        }

        unset($tag);

        $this->logger->log(
            $completed
                ? 'TASK_COMPLETED'
                : 'TASK_UNCOMPLETED',
            'task',
            $taskId,
            [
                'projectId' =>
                    $projectId,

                'workflowStageId' =>
                    $workflowStageId,

                'tags' =>
                    $tags,
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

        $row['noteId'] =
            $row['noteId'] !== null
                ? (int) $row['noteId']
                : null;

        $row['projectId'] =
            (int) $row['projectId'];

        $row['workflowStageId'] =
            (int) $row[
                'workflowStageId'
            ];

        $row['workflowStagePosition'] =
            (int) $row[
                'workflowStagePosition'
            ];

        $row['position'] =
            (int) $row['position'];

        $row['isCompleted'] =
            filter_var(
                $row['isCompleted'],
                FILTER_VALIDATE_BOOLEAN,
            );

        $row['tags'] =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    tag.id,
    tag.name,
    tag.color
FROM tag
INNER JOIN task_tag
    ON task_tag.tag_id =
        tag.id
WHERE task_tag.task_id = :taskId
  AND tag.user_id = :userId
ORDER BY
    lower(tag.name),
    tag.id
SQL,
                    [
                        'taskId' =>
                            $row['id'],

                        'userId' =>
                            $this->currentUser
                                ->id(),
                    ],
                );

        foreach ($row['tags'] as &$tag) {
            $tag['id'] =
                (int) $tag['id'];
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

    private function validateDate(
        ?string $date,
        string $field,
    ): ?string {
        if (
            $date === null
            || trim($date) === ''
        ) {
            return null;
        }

        $date = trim($date);

        $parsed =
            \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date,
            );

        $errors =
            \DateTimeImmutable::getLastErrors();

        if (
            $parsed === false
            || (
                $errors !== false
                && (
                    $errors[
                        'warning_count'
                    ] > 0
                    || $errors[
                        'error_count'
                    ] > 0
                )
            )
            || $parsed->format('Y-m-d')
                !== $date
        ) {
            throw new \InvalidArgumentException(
                $field
                .' must use YYYY-MM-DD.'
            );
        }

        return $date;
    }

    private function validateDateRange(
        ?string $startDate,
        ?string $dueDate,
    ): void {
        if (
            $startDate !== null
            && $dueDate !== null
            && $dueDate < $startDate
        ) {
            throw new \InvalidArgumentException(
                'Task due date cannot be before start date.'
            );
        }
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
