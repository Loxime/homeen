<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class ChannelManagementRepository
{
    public function __construct(
        private Connection $connection,
        private CurrentUser $currentUser,
    ) {
    }

    /**
     * @return array{
     *     currentUserId:int,
     *     role:string,
     *     isCreator:bool,
     *     isAdmin:bool,
     *     canManageMembers:bool
     * }
     */
    public function permissions(
        string $code,
    ): array {
        $context =
            $this->context(
                $code,
            );

        $isCreator =
            $context['creatorUserId']
            === $context['currentUserId'];

        $isAdmin =
            !$isCreator
            && $context['currentRole']
                === 'admin';

        return [
            'currentUserId' =>
                $context['currentUserId'],

            'role' =>
                $isCreator
                    ? 'creator'
                    : $context['currentRole'],

            'isCreator' =>
                $isCreator,

            'isAdmin' =>
                $isAdmin,

            'canManageMembers' =>
                $isCreator
                || $isAdmin,
        ];
    }

    /**
     * @return array{
     *     invitedUserId:int,
     *     email:string
     * }
     */
    public function invite(
        string $code,
        string $email,
    ): array {
        $email =
            trim(
                $email,
            );

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
            $this->context(
                $code,
            );

        $this->requireManager(
            $context,
        );

        $normalizedEmail =
            mb_strtolower(
                $email,
            );

        $target =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    user_id AS "userId",
    email
FROM user_email
WHERE normalized_email = :email
LIMIT 1
SQL,
                    [
                        'email' =>
                            $normalizedEmail,
                    ],
                );

        if ($target === false) {
            throw new \OutOfBoundsException(
                'User not found.',
            );
        }

        $targetUserId =
            (int) $target['userId'];

        $alreadyMember =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM channel_member
WHERE channel_id = :channelId
  AND user_id = :userId
LIMIT 1
SQL,
                    [
                        'channelId' =>
                            $context['channelId'],

                        'userId' =>
                            $targetUserId,
                    ],
                );

        if ($alreadyMember !== false) {
            throw new \DomainException(
                'CHANNEL_ALREADY_MEMBER',
            );
        }

        $this->connection
            ->transactional(
                function (
                    Connection $connection,
                ) use (
                    $context,
                    $targetUserId,
                ): void {
                    /*
                     * Reinviting resets an existing
                     * pending invitation instead of
                     * creating duplicates.
                     */
                    $connection
                        ->executeStatement(
                            <<<'SQL'
DELETE FROM channel_invitation
WHERE channel_id = :channelId
  AND invited_user_id = :userId
SQL,
                            [
                                'channelId' =>
                                    $context[
                                        'channelId'
                                    ],

                                'userId' =>
                                    $targetUserId,
                            ],
                        );

                    $connection
                        ->executeStatement(
                            <<<'SQL'
INSERT INTO channel_invitation (
    channel_id,
    invited_user_id,
    invited_by_user_id
)
VALUES (
    :channelId,
    :invitedUserId,
    :invitedByUserId
)
SQL,
                            [
                                'channelId' =>
                                    $context[
                                        'channelId'
                                    ],

                                'invitedUserId' =>
                                    $targetUserId,

                                'invitedByUserId' =>
                                    $context[
                                        'currentUserId'
                                    ],
                            ],
                        );
                },
            );

        return [
            'invitedUserId' =>
                $targetUserId,

            'email' =>
                (string) $target['email'],
        ];
    }

    public function removeMember(
        string $code,
        int $memberUserId,
    ): void {
        $context =
            $this->context(
                $code,
            );

        $this->requireManager(
            $context,
        );

        if (
            $memberUserId
            === $context['currentUserId']
        ) {
            throw new \DomainException(
                'CHANNEL_MANAGER_USE_LEAVE',
            );
        }

        $target =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    member.role,
    channel.creator_user_id
        AS "creatorUserId"
FROM channel_member member
INNER JOIN channel
    ON channel.id =
        member.channel_id
WHERE member.channel_id = :channelId
  AND member.user_id = :userId
LIMIT 1
SQL,
                    [
                        'channelId' =>
                            $context['channelId'],

                        'userId' =>
                            $memberUserId,
                    ],
                );

        if ($target === false) {
            throw new \OutOfBoundsException(
                'Channel member not found.',
            );
        }

        if (
            $memberUserId
            === (int) $target[
                'creatorUserId'
            ]
        ) {
            throw new \DomainException(
                'CHANNEL_CANNOT_REMOVE_CREATOR',
            );
        }

        $callerIsCreator =
            $context['creatorUserId']
            === $context['currentUserId'];

        if (
            !$callerIsCreator
            && (string) $target['role']
                !== 'member'
        ) {
            throw new \DomainException(
                'CHANNEL_ADMIN_CANNOT_REMOVE_ADMIN',
            );
        }

        $affected =
            $this->connection
                ->executeStatement(
                    <<<'SQL'
DELETE FROM channel_member
WHERE channel_id = :channelId
  AND user_id = :userId
SQL,
                    [
                        'channelId' =>
                            $context['channelId'],

                        'userId' =>
                            $memberUserId,
                    ],
                );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Channel member not found.',
            );
        }
    }

    /**
     * @return array{
     *     channelId:int,
     *     creatorUserId:int,
     *     currentUserId:int,
     *     currentRole:string
     * }
     */
    private function context(
        string $code,
    ): array {
        if (
            preg_match(
                '/^\d{9}$/',
                $code,
            ) !== 1
        ) {
            throw new \OutOfBoundsException(
                'Channel not found.',
            );
        }

        $currentUserId =
            $this->currentUser
                ->id();

        $row =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    channel.id AS "channelId",
    channel.creator_user_id
        AS "creatorUserId",
    current_member.role
        AS "currentRole"
FROM channel
INNER JOIN channel_member current_member
    ON current_member.channel_id =
        channel.id
   AND current_member.user_id =
        :currentUserId
WHERE channel.code = :code
  AND channel.closed_at IS NULL
LIMIT 1
SQL,
                    [
                        'code' =>
                            $code,

                        'currentUserId' =>
                            $currentUserId,
                    ],
                );

        if ($row !== false) {
            return [
                'channelId' =>
                    (int) $row[
                        'channelId'
                    ],

                'creatorUserId' =>
                    (int) $row[
                        'creatorUserId'
                    ],

                'currentUserId' =>
                    $currentUserId,

                'currentRole' =>
                    (string) $row[
                        'currentRole'
                    ],
            ];
        }

        $exists =
            $this->connection
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
                'CHANNEL_FORBIDDEN',
            );
        }

        throw new \OutOfBoundsException(
            'Channel not found.',
        );
    }

    /**
     * @param array{
     *     channelId:int,
     *     creatorUserId:int,
     *     currentUserId:int,
     *     currentRole:string
     * } $context
     */
    private function requireManager(
        array $context,
    ): void {
        if (
            $context['creatorUserId']
            === $context['currentUserId']
        ) {
            return;
        }

        if (
            $context['currentRole']
            === 'admin'
        ) {
            return;
        }

        throw new \DomainException(
            'CHANNEL_MANAGEMENT_REQUIRED',
        );
    }
}
