<?php

declare(strict_types=1);

namespace App\Repository;

use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class ProjectRepository
{
    public function __construct(
        private Connection $connection,
        private CurrentUser $currentUser,
        private ActivityLogger $logger,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(
        string $scope = 'active',
    ): array {
        $where = match ($scope) {
            'active' =>
                'project.archived_at IS NULL',

            'archived' =>
                'project.archived_at IS NOT NULL',

            'all' =>
                'TRUE',

            default =>
                throw new ValidationException(
                    'Unknown project scope.',
                    'INVALID_PROJECT_SCOPE',
                ),
        };

        $rows = $this->connection
            ->fetchAllAssociative(
                <<<SQL
SELECT
    project.id,
    project.name,
    project.description,
    project.color,
    project.image_url AS "imageUrl",
    project.created_at AS "createdAt",
    project.updated_at AS "updatedAt",
    project.archived_at AS "archivedAt",
    current_member.role,
    COUNT(DISTINCT member.user_id)
        AS "memberCount",
    COUNT(DISTINCT note.id)
        FILTER (
            WHERE note.deleted_at IS NULL
        ) AS "noteCount"
FROM project
INNER JOIN project_member current_member
    ON current_member.project_id =
        project.id
   AND current_member.user_id =
        :userId
LEFT JOIN project_member member
    ON member.project_id =
        project.id
LEFT JOIN note
    ON note.project_id =
        project.id
WHERE {$where}
GROUP BY
    project.id,
    current_member.role
ORDER BY
    project.archived_at NULLS FIRST,
    project.updated_at DESC,
    project.id DESC
SQL,
                [
                    'userId' =>
                        $this->currentUser->id(),
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
        int $id,
    ): array {
        return $this->context($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        string $description,
        string $color,
    ): array {
        $name =
            $this->validateName($name);

        $description =
            $this->validateDescription(
                $description,
            );

        $color =
            $this->validateColor(
                $color,
            );

        $userId =
            $this->currentUser->id();

        return $this->connection
            ->transactional(
                function (
                    Connection $connection,
                ) use (
                    $name,
                    $description,
                    $color,
                    $userId,
                ): array {
                    $projectId =
                        $connection
                            ->fetchOne(
                                <<<'SQL'
INSERT INTO project (
    name,
    description,
    color
)
VALUES (
    :name,
    :description,
    :color
)
RETURNING id
SQL,
                                [
                                    'name' =>
                                        $name,

                                    'description' =>
                                        $description,

                                    'color' =>
                                        $color,
                                ],
                            );

                    if ($projectId === false) {
                        throw new \RuntimeException(
                            'Unable to create project.',
                        );
                    }

                    $projectId =
                        (int) $projectId;

                    $connection->insert(
                        'project_member',
                        [
                            'project_id' =>
                                $projectId,

                            'user_id' =>
                                $userId,

                            'role' =>
                                'owner',
                        ],
                    );

                    $connection
                        ->executeStatement(
                            <<<'SQL'
INSERT INTO project_workflow_stage (
    project_id,
    name,
    position
)
VALUES
    (:projectId, 'Backlog', 0),
    (:projectId, 'En cours', 1),
    (:projectId, 'Terminé', 2)
SQL,
                            [
                                'projectId' =>
                                    $projectId,
                            ],
                        );

                    $this->logger->log(
                        'PROJECT_CREATED',
                        'project',
                        $projectId,
                        [
                            'name' =>
                                $name,

                            'color' =>
                                $color,
                        ],
                    );

                    return $this->context(
                        $projectId,
                    );
                },
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function update(
        int $id,
        ?string $name,
        ?string $description,
        ?string $color,
    ): array {
        $project =
            $this->context($id);

        $this->requireManager(
            (string) $project['role'],
        );

        $name =
            $name !== null
                ? $this->validateName(
                    $name,
                )
                : (string) $project['name'];

        $description =
            $description !== null
                ? $this->validateDescription(
                    $description,
                )
                : (string) $project[
                    'description'
                ];

        $color =
            $color !== null
                ? $this->validateColor(
                    $color,
                )
                : (string) $project['color'];

        $affected =
            $this->connection
                ->executeStatement(
                    <<<'SQL'
UPDATE project
SET
    name = :name,
    description = :description,
    color = :color,
    updated_at = NOW()
WHERE id = :id
SQL,
                    [
                        'id' => $id,
                        'name' => $name,
                        'description' =>
                            $description,
                        'color' => $color,
                    ],
                );

        if ($affected !== 1) {
            throw new NotFoundException(
                'Project not found.',
                'PROJECT_NOT_FOUND',
            );
        }

        $this->logger->log(
            'PROJECT_UPDATED',
            'project',
            $id,
            [
                'name' =>
                    $name,

                'color' =>
                    $color,
            ],
        );

        return $this->context($id);
    }

    public function delete(
        int $id,
    ): void {
        $project =
            $this->context($id);

        $this->requireOwner(
            (string) $project['role'],
        );

        $ownerUserId =
            $this->currentUser->id();

        $this->connection
            ->transactional(
                function (
                    Connection $connection,
                ) use (
                    $id,
                    $ownerUserId,
                ): void {
                    /*
                     * Keep all notes and their
                     * archive/trash state.
                     *
                     * Historical shared notes may
                     * have no personal owner. Give
                     * those to the Project owner
                     * before removing the Project.
                     */
                    $connection
                        ->executeStatement(
                            <<<'SQL'
UPDATE note
SET
    user_id = COALESCE(
        user_id,
        :ownerUserId
    ),
    project_id = NULL,
    updated_at = NOW()
WHERE project_id = :projectId
SQL,
                            [
                                'ownerUserId' =>
                                    $ownerUserId,

                                'projectId' =>
                                    $id,
                            ],
                        );

                    $affected =
                        $connection->delete(
                            'project',
                            [
                                'id' => $id,
                            ],
                        );

                    if ($affected !== 1) {
                        throw new NotFoundException(
                            'Project not found.',
                            'PROJECT_NOT_FOUND',
                        );
                    }
                },
            );

        $this->logger->log(
            'PROJECT_DELETED',
            'project',
            $id,
        );
    }

    /**
     * @return list<array{
     *     userId:int,
     *     email:string,
     *     role:string,
     *     joinedAt:string
     * }>
     */
    public function members(
        int $id,
    ): array {
        /*
         * Access check first so project
         * membership cannot be enumerated.
         */
        $this->context($id);

        $rows =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    member.user_id AS "userId",
    email.email,
    member.role,
    member.joined_at AS "joinedAt"
FROM project_member member
INNER JOIN user_email email
    ON email.user_id =
        member.user_id
   AND email.is_primary = TRUE
WHERE member.project_id = :projectId
ORDER BY
    CASE member.role
        WHEN 'owner' THEN 0
        WHEN 'admin' THEN 1
        ELSE 2
    END,
    lower(email.email),
    member.user_id
SQL,
                    [
                        'projectId' => $id,
                    ],
                );

        return array_map(
            static fn (
                array $row,
            ): array => [
                'userId' =>
                    (int) $row['userId'],

                'email' =>
                    (string) $row['email'],

                'role' =>
                    (string) $row['role'],

                'joinedAt' =>
                    (string) $row[
                        'joinedAt'
                    ],
            ],
            $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function context(
        int $id,
    ): array {
        $row =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    project.id,
    project.name,
    project.description,
    project.color,
    project.image_url AS "imageUrl",
    project.created_at AS "createdAt",
    project.updated_at AS "updatedAt",
    project.archived_at AS "archivedAt",
    current_member.role,
    (
        SELECT COUNT(*)
        FROM project_member member
        WHERE member.project_id =
            project.id
    ) AS "memberCount",
    (
        SELECT COUNT(*)
        FROM note
        WHERE note.project_id =
            project.id
          AND note.deleted_at IS NULL
    ) AS "noteCount"
FROM project
INNER JOIN project_member current_member
    ON current_member.project_id =
        project.id
   AND current_member.user_id =
        :userId
WHERE project.id = :id
LIMIT 1
SQL,
                    [
                        'id' => $id,

                        'userId' =>
                            $this->currentUser
                                ->id(),
                    ],
                );

        if ($row === false) {
            /*
             * Deliberately return the same 404
             * for missing and inaccessible
             * projects.
             */
            throw new NotFoundException(
                'Project not found.',
                'PROJECT_NOT_FOUND',
            );
        }

        return $this->normalize($row);
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

        $row['memberCount'] =
            (int) $row['memberCount'];

        $row['noteCount'] =
            (int) $row['noteCount'];

        return $row;
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

        throw new ForbiddenException(
            'Project administrator privileges are required.',
            'PROJECT_MANAGEMENT_REQUIRED',
        );
    }

    private function requireOwner(
        string $role,
    ): void {
        if ($role === 'owner') {
            return;
        }

        throw new ForbiddenException(
            'Only the project owner can delete this project.',
            'PROJECT_OWNER_REQUIRED',
        );
    }

    private function validateName(
        string $name,
    ): string {
        $name = trim($name);

        if (
            $name === ''
            || mb_strlen($name) > 120
        ) {
            throw new ValidationException(
                'Project name must contain between 1 and 120 characters.',
                'INVALID_PROJECT_INPUT',
            );
        }

        return $name;
    }

    private function validateDescription(
        string $description,
    ): string {
        $description =
            trim($description);

        if (
            mb_strlen($description)
            > 4000
        ) {
            throw new ValidationException(
                'Project description cannot exceed 4000 characters.',
                'INVALID_PROJECT_INPUT',
            );
        }

        return $description;
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
            throw new ValidationException(
                'Project color must be a six-digit hexadecimal color.',
                'INVALID_PROJECT_INPUT',
            );
        }

        return strtoupper($color);
    }
}
