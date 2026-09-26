<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class ProjectWorkflowRepository
{
    private const MAX_STAGES = 20;

    public function __construct(
        private Connection $connection,
        private ActivityLogger $logger,
        private CurrentUser $currentUser,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forProject(
        int $projectId,
    ): array {
        $this->context($projectId);

        return $this->fetchStages(
            $projectId
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        int $projectId,
        string $name,
    ): array {
        $context =
            $this->context($projectId);

        $this->requireManager(
            $context['role'],
        );

        $name =
            $this->validateName($name);

        $this->assertNameAvailable(
            $projectId,
            $name,
        );

        $count =
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COUNT(*)
FROM project_workflow_stage
WHERE project_id = :projectId
SQL,
                    [
                        'projectId' =>
                            $projectId,
                    ],
                );

        if ($count >= self::MAX_STAGES) {
            throw new \DomainException(
                'PROJECT_WORKFLOW_LIMIT',
            );
        }

        $id =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO project_workflow_stage (
    project_id,
    name,
    position
)
VALUES (
    :projectId,
    :name,
    :position
)
RETURNING id
SQL,
                    [
                        'projectId' =>
                            $projectId,

                        'name' =>
                            $name,

                        'position' =>
                            $count,
                    ],
                );

        if ($id === false) {
            throw new \RuntimeException(
                'Unable to create workflow stage.',
            );
        }

        $id = (int) $id;

        $this->touchProject(
            $projectId
        );

        $this->logger->log(
            'PROJECT_WORKFLOW_STAGE_CREATED',
            'project_workflow_stage',
            $id,
            [
                'projectId' =>
                    $projectId,

                'name' =>
                    $name,

                'position' =>
                    $count,
            ],
        );

        return $this->stage(
            $projectId,
            $id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rename(
        int $projectId,
        int $stageId,
        string $name,
    ): array {
        $context =
            $this->context($projectId);

        $this->requireManager(
            $context['role'],
        );

        $this->stage(
            $projectId,
            $stageId,
        );

        $name =
            $this->validateName($name);

        $this->assertNameAvailable(
            $projectId,
            $name,
            $stageId,
        );

        $affected =
            $this->connection
                ->executeStatement(
                    <<<'SQL'
UPDATE project_workflow_stage
SET
    name = :name,
    updated_at = NOW()
WHERE id = :stageId
  AND project_id = :projectId
SQL,
                    [
                        'name' =>
                            $name,

                        'stageId' =>
                            $stageId,

                        'projectId' =>
                            $projectId,
                    ],
                );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Workflow stage not found.',
            );
        }

        $this->touchProject(
            $projectId
        );

        $this->logger->log(
            'PROJECT_WORKFLOW_STAGE_RENAMED',
            'project_workflow_stage',
            $stageId,
            [
                'projectId' =>
                    $projectId,

                'name' =>
                    $name,
            ],
        );

        return $this->stage(
            $projectId,
            $stageId,
        );
    }

    /**
     * @param list<int> $stageIds
     *
     * @return list<array<string, mixed>>
     */
    public function reorder(
        int $projectId,
        array $stageIds,
    ): array {
        $context =
            $this->context($projectId);

        $this->requireManager(
            $context['role'],
        );

        if (
            $stageIds === []
            || count($stageIds)
                > self::MAX_STAGES
            || count($stageIds)
                !== count(
                    array_unique(
                        $stageIds,
                    ),
                )
        ) {
            throw new \InvalidArgumentException(
                'Workflow stage order is invalid.',
            );
        }

        $existing =
            array_map(
                static fn (
                    string|int $id,
                ): int => (int) $id,
                $this->connection
                    ->fetchFirstColumn(
                        <<<'SQL'
SELECT id
FROM project_workflow_stage
WHERE project_id = :projectId
ORDER BY id
SQL,
                        [
                            'projectId' =>
                                $projectId,
                        ],
                    ),
            );

        $provided = $stageIds;

        sort($existing);
        sort($provided);

        if ($existing !== $provided) {
            throw new \InvalidArgumentException(
                'Workflow stage order must contain every project stage exactly once.',
            );
        }

        $this->connection
            ->transactional(
                function (
                    Connection $connection,
                ) use (
                    $projectId,
                    $stageIds,
                ): void {
                    $connection
                        ->executeStatement(
                            'SET CONSTRAINTS '
                            .'uniq_project_workflow_position '
                            .'DEFERRED'
                        );

                    foreach (
                        $stageIds
                        as $position => $stageId
                    ) {
                        $connection
                            ->executeStatement(
                                <<<'SQL'
UPDATE project_workflow_stage
SET
    position = :position,
    updated_at = NOW()
WHERE id = :stageId
  AND project_id = :projectId
SQL,
                                [
                                    'position' =>
                                        $position,

                                    'stageId' =>
                                        $stageId,

                                    'projectId' =>
                                        $projectId,
                                ],
                            );
                    }

                    $this->touchProject(
                        $projectId
                    );
                },
            );

        $this->logger->log(
            'PROJECT_WORKFLOW_REORDERED',
            'project',
            $projectId,
            [
                'stageIds' =>
                    $stageIds,
            ],
        );

        return $this->fetchStages(
            $projectId
        );
    }

    public function delete(
        int $projectId,
        int $stageId,
    ): void {
        $context =
            $this->context($projectId);

        $this->requireManager(
            $context['role'],
        );

        $stage =
            $this->stage(
                $projectId,
                $stageId,
            );

        $count =
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COUNT(*)
FROM project_workflow_stage
WHERE project_id = :projectId
SQL,
                    [
                        'projectId' =>
                            $projectId,
                    ],
                );

        if ($count <= 1) {
            throw new \DomainException(
                'PROJECT_WORKFLOW_MINIMUM',
            );
        }

        $position =
            (int) $stage['position'];

        $this->connection
            ->transactional(
                function (
                    Connection $connection,
                ) use (
                    $projectId,
                    $stageId,
                    $position,
                ): void {
                    $connection
                        ->executeStatement(
                            'SET CONSTRAINTS '
                            .'uniq_project_workflow_position '
                            .'DEFERRED'
                        );

                    $affected =
                        $connection
                            ->executeStatement(
                                <<<'SQL'
DELETE FROM project_workflow_stage
WHERE id = :stageId
  AND project_id = :projectId
SQL,
                                [
                                    'stageId' =>
                                        $stageId,

                                    'projectId' =>
                                        $projectId,
                                ],
                            );

                    if ($affected !== 1) {
                        throw new \OutOfBoundsException(
                            'Workflow stage not found.',
                        );
                    }

                    $connection
                        ->executeStatement(
                            <<<'SQL'
UPDATE project_workflow_stage
SET
    position = position - 1,
    updated_at = NOW()
WHERE project_id = :projectId
  AND position > :position
SQL,
                            [
                                'projectId' =>
                                    $projectId,

                                'position' =>
                                    $position,
                            ],
                        );

                    $this->touchProject(
                        $projectId
                    );
                },
            );

        $this->logger->log(
            'PROJECT_WORKFLOW_STAGE_DELETED',
            'project_workflow_stage',
            $stageId,
            [
                'projectId' =>
                    $projectId,
            ],
        );
    }

    /**
     * @return array{
     *     role:string
     * }
     */
    private function context(
        int $projectId,
    ): array {
        $row =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT member.role
FROM project
INNER JOIN project_member member
    ON member.project_id = project.id
   AND member.user_id = :userId
WHERE project.id = :projectId
  AND project.archived_at IS NULL
LIMIT 1
SQL,
                    [
                        'projectId' =>
                            $projectId,

                        'userId' =>
                            $this->currentUser->id(),
                    ],
                );

        if ($row === false) {
            throw new \OutOfBoundsException(
                'Project not found.',
            );
        }

        return [
            'role' =>
                (string) $row['role'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stage(
        int $projectId,
        int $stageId,
    ): array {
        $row =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    id,
    project_id AS "projectId",
    name,
    position,
    created_at AS "createdAt",
    updated_at AS "updatedAt"
FROM project_workflow_stage
WHERE id = :stageId
  AND project_id = :projectId
LIMIT 1
SQL,
                    [
                        'stageId' =>
                            $stageId,

                        'projectId' =>
                            $projectId,
                    ],
                );

        if ($row === false) {
            throw new \OutOfBoundsException(
                'Workflow stage not found.',
            );
        }

        return $this->normalize(
            $row
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchStages(
        int $projectId,
    ): array {
        $rows =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    id,
    project_id AS "projectId",
    name,
    position,
    created_at AS "createdAt",
    updated_at AS "updatedAt"
FROM project_workflow_stage
WHERE project_id = :projectId
ORDER BY
    position ASC,
    id ASC
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

    private function assertNameAvailable(
        int $projectId,
        string $name,
        ?int $excludedStageId = null,
    ): void {
        if ($excludedStageId === null) {
            $exists =
                $this->connection
                    ->fetchOne(
                        <<<'SQL'
SELECT 1
FROM project_workflow_stage
WHERE project_id = :projectId
  AND lower(name) = lower(:name)
LIMIT 1
SQL,
                        [
                            'projectId' =>
                                $projectId,

                            'name' =>
                                $name,
                        ],
                    );
        } else {
            $exists =
                $this->connection
                    ->fetchOne(
                        <<<'SQL'
SELECT 1
FROM project_workflow_stage
WHERE project_id = :projectId
  AND lower(name) = lower(:name)
  AND id <> :stageId
LIMIT 1
SQL,
                        [
                            'projectId' =>
                                $projectId,

                            'name' =>
                                $name,

                            'stageId' =>
                                $excludedStageId,
                        ],
                    );
        }

        if ($exists !== false) {
            throw new \DomainException(
                'PROJECT_WORKFLOW_NAME_CONFLICT',
            );
        }
    }

    private function validateName(
        string $name,
    ): string {
        $name = trim($name);

        if (
            $name === ''
            || mb_strlen($name) > 80
        ) {
            throw new \InvalidArgumentException(
                'Workflow stage name must contain between 1 and 80 characters.',
            );
        }

        return $name;
    }

    private function requireManager(
        string $role,
    ): void {
        if (
            $role === 'owner'
            || $role === 'admin'
        ) {
            return;
        }

        throw new \DomainException(
            'PROJECT_MANAGEMENT_REQUIRED',
        );
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

        $row['projectId'] =
            (int) $row['projectId'];

        $row['position'] =
            (int) $row['position'];

        return $row;
    }
}
