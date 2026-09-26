<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class ProjectSharingRepository
{
    public function __construct(
        private Connection $connection,
        private CurrentUser $currentUser,
        private UserRepository $users,
        private ActivityLogger $logger,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pending(): array
    {
        $rows =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    invitation.id,
    invitation.project_id
        AS "projectId",
    project.name,
    project.description,
    project.color,
    inviter_email.email
        AS "invitedByEmail",
    invitation.created_at
        AS "createdAt"
FROM project_invitation invitation
INNER JOIN project
    ON project.id =
        invitation.project_id
   AND project.archived_at IS NULL
LEFT JOIN user_email inviter_email
    ON inviter_email.user_id =
        invitation.invited_by_user_id
   AND inviter_email.is_primary = TRUE
WHERE invitation.invited_user_id =
        :userId
ORDER BY
    invitation.created_at DESC,
    invitation.id DESC
SQL,
                    [
                        'userId' =>
                            $this->currentUser
                                ->id(),
                    ],
                );

        return array_map(
            static fn (
                array $row,
            ): array => [
                'id' =>
                    (int) $row['id'],

                'projectId' =>
                    (int) $row[
                        'projectId'
                    ],

                'name' =>
                    (string) $row['name'],

                'description' =>
                    (string) $row[
                        'description'
                    ],

                'color' =>
                    (string) $row['color'],

                'invitedByEmail' =>
                    $row['invitedByEmail']
                        !== null
                            ? (string) $row[
                                'invitedByEmail'
                            ]
                            : null,

                'createdAt' =>
                    (string) $row[
                        'createdAt'
                    ],
            ],
            $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function invite(
        int $projectId,
        string $email,
    ): array {
        $email = trim($email);

        if (
            $email === ''
            || strlen($email) > 254
            || filter_var(
                $email,
                FILTER_VALIDATE_EMAIL,
            ) === false
        ) {
            throw new \InvalidArgumentException(
                'Invalid email address.',
            );
        }

        $context =
            $this->context($projectId);

        $this->requireManager(
            $context['role'],
        );

        $target =
            $this->users
                ->findIdentityByEmail(
                    $email,
                );

        if ($target === null) {
            throw new \OutOfBoundsException(
                'User not found.',
            );
        }

        if (
            $target['id']
            === $context['currentUserId']
        ) {
            throw new \DomainException(
                'PROJECT_CANNOT_INVITE_SELF',
            );
        }

        $alreadyMember =
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
                            $target['id'],
                    ],
                );

        if ($alreadyMember !== false) {
            throw new \DomainException(
                'PROJECT_ALREADY_MEMBER',
            );
        }

        $alreadyInvited =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM project_invitation
WHERE project_id = :projectId
  AND invited_user_id = :userId
LIMIT 1
SQL,
                    [
                        'projectId' =>
                            $projectId,

                        'userId' =>
                            $target['id'],
                    ],
                );

        if ($alreadyInvited !== false) {
            throw new \DomainException(
                'PROJECT_ALREADY_INVITED',
            );
        }

        try {
            $row =
                $this->connection
                    ->fetchAssociative(
                        <<<'SQL'
INSERT INTO project_invitation (
    project_id,
    invited_user_id,
    invited_by_user_id
)
VALUES (
    :projectId,
    :invitedUserId,
    :invitedByUserId
)
RETURNING
    id,
    created_at AS "createdAt"
SQL,
                        [
                            'projectId' =>
                                $projectId,

                            'invitedUserId' =>
                                $target['id'],

                            'invitedByUserId' =>
                                $context[
                                    'currentUserId'
                                ],
                        ],
                    );
        } catch (
            UniqueConstraintViolationException
        ) {
            throw new \DomainException(
                'PROJECT_ALREADY_INVITED',
            );
        }

        if ($row === false) {
            throw new \RuntimeException(
                'Unable to create project invitation.',
            );
        }

        $this->logger->log(
            'PROJECT_INVITATION_CREATED',
            'project',
            $projectId,
            [
                'invitedUserId' =>
                    $target['id'],
            ],
        );

        return [
            'id' =>
                (int) $row['id'],

            'projectId' =>
                $projectId,

            'email' =>
                (string) $target['email'],

            'createdAt' =>
                (string) $row[
                    'createdAt'
                ],
        ];
    }

    /**
     * @return array{projectId:int}
     */
    public function accept(
        int $invitationId,
    ): array {
        $userId =
            $this->currentUser->id();

        return $this->connection
            ->transactional(
                function (
                    Connection $connection,
                ) use (
                    $invitationId,
                    $userId,
                ): array {
                    $invitation =
                        $connection
                            ->fetchAssociative(
                                <<<'SQL'
SELECT
    invitation.project_id
        AS "projectId"
FROM project_invitation invitation
INNER JOIN project
    ON project.id =
        invitation.project_id
   AND project.archived_at IS NULL
WHERE invitation.id = :id
  AND invitation.invited_user_id =
        :userId
FOR UPDATE
SQL,
                                [
                                    'id' =>
                                        $invitationId,

                                    'userId' =>
                                        $userId,
                                ],
                            );

                    if (
                        $invitation === false
                    ) {
                        throw new \OutOfBoundsException(
                            'Invitation not found.',
                        );
                    }

                    $projectId =
                        (int) $invitation[
                            'projectId'
                        ];

                    $connection
                        ->executeStatement(
                            <<<'SQL'
INSERT INTO project_member (
    project_id,
    user_id,
    role
)
VALUES (
    :projectId,
    :userId,
    'member'
)
ON CONFLICT (
    project_id,
    user_id
)
DO NOTHING
SQL,
                            [
                                'projectId' =>
                                    $projectId,

                                'userId' =>
                                    $userId,
                            ],
                        );

                    $connection->delete(
                        'project_invitation',
                        [
                            'id' =>
                                $invitationId,
                        ],
                    );

                    $this->logger->log(
                        'PROJECT_INVITATION_ACCEPTED',
                        'project',
                        $projectId,
                    );

                    return [
                        'projectId' =>
                            $projectId,
                    ];
                },
            );
    }

    public function reject(
        int $invitationId,
    ): void {
        $projectId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
DELETE FROM project_invitation
WHERE id = :id
  AND invited_user_id = :userId
RETURNING project_id
SQL,
                    [
                        'id' =>
                            $invitationId,

                        'userId' =>
                            $this->currentUser
                                ->id(),
                    ],
                );

        if ($projectId === false) {
            throw new \OutOfBoundsException(
                'Invitation not found.',
            );
        }

        $this->logger->log(
            'PROJECT_INVITATION_REJECTED',
            'project',
            (int) $projectId,
        );
    }

    /**
     * @return array{
     *     userId:int,
     *     email:string,
     *     role:string,
     *     joinedAt:string
     * }
     */
    public function setRole(
        int $projectId,
        int $userId,
        string $role,
    ): array {
        $role =
            $this->validateRole($role);

        $context =
            $this->context($projectId);

        $this->requireOwner(
            $context['role'],
        );

        $target =
            $this->member(
                $projectId,
                $userId,
            );

        if ($target['role'] === 'owner') {
            throw new \DomainException(
                'PROJECT_OWNER_ROLE_IMMUTABLE',
            );
        }

        $this->connection
            ->update(
                'project_member',
                [
                    'role' => $role,
                ],
                [
                    'project_id' =>
                        $projectId,

                    'user_id' =>
                        $userId,
                ],
            );

        $this->logger->log(
            'PROJECT_MEMBER_ROLE_UPDATED',
            'project',
            $projectId,
            [
                'userId' =>
                    $userId,

                'role' =>
                    $role,
            ],
        );

        return $this->member(
            $projectId,
            $userId,
        );
    }

    public function removeMember(
        int $projectId,
        int $userId,
    ): void {
        $context =
            $this->context($projectId);

        $this->requireManager(
            $context['role'],
        );

        if (
            $userId
            === $context['currentUserId']
        ) {
            throw new \DomainException(
                'PROJECT_MANAGER_USE_LEAVE',
            );
        }

        $target =
            $this->member(
                $projectId,
                $userId,
            );

        if ($target['role'] === 'owner') {
            throw new \DomainException(
                'PROJECT_OWNER_ROLE_IMMUTABLE',
            );
        }

        if (
            $context['role'] === 'admin'
            && $target['role'] === 'admin'
        ) {
            throw new \DomainException(
                'PROJECT_ADMIN_CANNOT_REMOVE_ADMIN',
            );
        }

        $this->connection->delete(
            'project_member',
            [
                'project_id' =>
                    $projectId,

                'user_id' =>
                    $userId,
            ],
        );

        $this->logger->log(
            'PROJECT_MEMBER_REMOVED',
            'project',
            $projectId,
            [
                'userId' =>
                    $userId,
            ],
        );
    }

    public function leave(
        int $projectId,
    ): void {
        $context =
            $this->context($projectId);

        if ($context['role'] === 'owner') {
            throw new \DomainException(
                'PROJECT_OWNER_CANNOT_LEAVE',
            );
        }

        $this->connection->delete(
            'project_member',
            [
                'project_id' =>
                    $projectId,

                'user_id' =>
                    $context[
                        'currentUserId'
                    ],
            ],
        );

        $this->logger->log(
            'PROJECT_MEMBER_LEFT',
            'project',
            $projectId,
        );
    }

    /**
     * @return array{
     *     currentUserId:int,
     *     role:string
     * }
     */
    private function context(
        int $projectId,
    ): array {
        $userId =
            $this->currentUser->id();

        $row =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    member.role
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
                            $userId,
                    ],
                );

        if ($row === false) {
            throw new \OutOfBoundsException(
                'Project not found.',
            );
        }

        return [
            'currentUserId' =>
                $userId,

            'role' =>
                (string) $row['role'],
        ];
    }

    /**
     * @return array{
     *     userId:int,
     *     email:string,
     *     role:string,
     *     joinedAt:string
     * }
     */
    private function member(
        int $projectId,
        int $userId,
    ): array {
        $row =
            $this->connection
                ->fetchAssociative(
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
WHERE member.project_id =
        :projectId
  AND member.user_id =
        :userId
LIMIT 1
SQL,
                    [
                        'projectId' =>
                            $projectId,

                        'userId' =>
                            $userId,
                    ],
                );

        if ($row === false) {
            throw new \OutOfBoundsException(
                'Project member not found.',
            );
        }

        return [
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
        ];
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

    private function requireOwner(
        string $role,
    ): void {
        if ($role === 'owner') {
            return;
        }

        throw new \DomainException(
            'PROJECT_OWNER_REQUIRED',
        );
    }

    private function validateRole(
        string $role,
    ): string {
        if (
            !in_array(
                $role,
                [
                    'admin',
                    'member',
                ],
                true,
            )
        ) {
            throw new \InvalidArgumentException(
                'Project role must be admin or member.',
            );
        }

        return $role;
    }
}
