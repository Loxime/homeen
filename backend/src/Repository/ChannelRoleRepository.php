<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class ChannelRoleRepository
{
    public function __construct(
        private Connection $connection,
        private CurrentUser $currentUser,
    ) {
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
        string $code,
    ): array {
        $channel =
            $this->accessibleChannel(
                $code,
            );

        $rows =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    member.user_id
        AS "userId",
    primary_email.email,
    member.joined_at
        AS "joinedAt",
    CASE
        WHEN member.user_id =
             channel.creator_user_id
        THEN 'creator'
        ELSE member.role
    END AS role
FROM channel_member member
INNER JOIN channel
    ON channel.id =
        member.channel_id
LEFT JOIN user_email primary_email
    ON primary_email.user_id =
        member.user_id
   AND primary_email.is_primary = TRUE
WHERE channel.id = :channelId
ORDER BY
    CASE
        WHEN member.user_id =
             channel.creator_user_id
        THEN 0
        WHEN member.role = 'admin'
        THEN 1
        ELSE 2
    END,
    lower(primary_email.email)
SQL,
                    [
                        'channelId' =>
                            (int) $channel['id'],
                    ],
                );

        return array_map(
            static fn (
                array $row,
            ): array => [
                'userId' =>
                    (int) $row['userId'],

                'email' =>
                    $row['email'] !== null
                        ? (string) $row['email']
                        : 'Ancien membre',

                'role' =>
                    (string) $row['role'],

                'joinedAt' =>
                    (string) $row['joinedAt'],
            ],
            $rows,
        );
    }

    /**
     * @return array{
     *     previousCreatorUserId:int,
     *     newCreatorUserId:int
     * }
     */
    public function transferOwnership(
        string $code,
        int $newCreatorUserId,
    ): array {
        $channel =
            $this->accessibleChannel(
                $code,
            );

        $currentUserId =
            $this->currentUser
                ->id();

        if (
            (int) $channel[
                'creator_user_id'
            ]
            !== $currentUserId
        ) {
            throw new \DomainException(
                'CHANNEL_ROLE_CREATOR_REQUIRED',
            );
        }

        if (
            $newCreatorUserId
            === $currentUserId
        ) {
            throw new \DomainException(
                'CHANNEL_NEW_CREATOR_SAME_USER',
            );
        }

        $targetRole =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT role
FROM channel_member
WHERE channel_id = :channelId
  AND user_id = :userId
LIMIT 1
SQL,
                    [
                        'channelId' =>
                            (int) $channel['id'],

                        'userId' =>
                            $newCreatorUserId,
                    ],
                );

        if ($targetRole === false) {
            throw new \OutOfBoundsException(
                'Channel member not found.',
            );
        }

        return $this->connection
            ->transactional(
                function (
                    Connection $connection,
                ) use (
                    $channel,
                    $currentUserId,
                    $newCreatorUserId,
                ): array {
                    /*
                     * Conditional update prevents a stale
                     * creator session from transferring a
                     * channel after ownership changed.
                     */
                    $affected =
                        $connection
                            ->executeStatement(
                                <<<'SQL'
UPDATE channel
SET
    creator_user_id = :newCreatorUserId,
    updated_at = NOW()
WHERE id = :channelId
  AND creator_user_id = :previousCreatorUserId
  AND closed_at IS NULL
SQL,
                                [
                                    'newCreatorUserId' =>
                                        $newCreatorUserId,

                                    'previousCreatorUserId' =>
                                        $currentUserId,

                                    'channelId' =>
                                        (int) $channel['id'],
                                ],
                            );

                    if ($affected !== 1) {
                        throw new \DomainException(
                            'CHANNEL_ROLE_CREATOR_REQUIRED',
                        );
                    }

                    /*
                     * The former creator remains able to
                     * help manage the channel.
                     */
                    $connection
                        ->executeStatement(
                            <<<'SQL'
UPDATE channel_member
SET role = 'admin'
WHERE channel_id = :channelId
  AND user_id = :userId
SQL,
                            [
                                'channelId' =>
                                    (int) $channel['id'],

                                'userId' =>
                                    $currentUserId,
                            ],
                        );

                    /*
                     * Ownership is represented by
                     * channel.creator_user_id, not by
                     * the membership role.
                     *
                     * Resetting the target membership
                     * prevents a stale admin role if
                     * ownership is transferred again.
                     */
                    $connection
                        ->executeStatement(
                            <<<'SQL'
UPDATE channel_member
SET role = 'member'
WHERE channel_id = :channelId
  AND user_id = :userId
SQL,
                            [
                                'channelId' =>
                                    (int) $channel['id'],

                                'userId' =>
                                    $newCreatorUserId,
                            ],
                        );

                    return [
                        'previousCreatorUserId' =>
                            $currentUserId,

                        'newCreatorUserId' =>
                            $newCreatorUserId,
                    ];
                },
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
        string $code,
        int $memberUserId,
        string $role,
    ): array {
        if (
            !in_array(
                $role,
                [
                    'member',
                    'admin',
                ],
                true,
            )
        ) {
            throw new \InvalidArgumentException(
                'Invalid channel role.',
            );
        }

        $channel =
            $this->accessibleChannel(
                $code,
            );

        if (
            (int) $channel[
                'creator_user_id'
            ]
            !== $this->currentUser->id()
        ) {
            throw new \DomainException(
                'CHANNEL_ROLE_CREATOR_REQUIRED',
            );
        }

        if (
            $memberUserId
            === (int) $channel[
                'creator_user_id'
            ]
        ) {
            throw new \DomainException(
                'CHANNEL_CREATOR_ROLE_IMMUTABLE',
            );
        }

        $affected =
            $this->connection
                ->executeStatement(
                    <<<'SQL'
UPDATE channel_member
SET role = :role
WHERE channel_id = :channelId
  AND user_id = :memberUserId
SQL,
                    [
                        'role' =>
                            $role,

                        'channelId' =>
                            (int) $channel['id'],

                        'memberUserId' =>
                            $memberUserId,
                    ],
                );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Channel member not found.',
            );
        }

        $row =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    member.user_id
        AS "userId",
    primary_email.email,
    member.role,
    member.joined_at
        AS "joinedAt"
FROM channel_member member
LEFT JOIN user_email primary_email
    ON primary_email.user_id =
        member.user_id
   AND primary_email.is_primary = TRUE
WHERE member.channel_id = :channelId
  AND member.user_id = :memberUserId
LIMIT 1
SQL,
                    [
                        'channelId' =>
                            (int) $channel['id'],

                        'memberUserId' =>
                            $memberUserId,
                    ],
                );

        if ($row === false) {
            throw new \OutOfBoundsException(
                'Channel member not found.',
            );
        }

        return [
            'userId' =>
                (int) $row['userId'],

            'email' =>
                $row['email'] !== null
                    ? (string) $row['email']
                    : 'Ancien membre',

            'role' =>
                (string) $row['role'],

            'joinedAt' =>
                (string) $row['joinedAt'],
        ];
    }

    /**
     * @return array{
     *     id:int,
     *     creator_user_id:int
     * }
     */
    private function accessibleChannel(
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

        $row =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    channel.id,
    channel.creator_user_id
FROM channel
INNER JOIN channel_member current_member
    ON current_member.channel_id =
        channel.id
   AND current_member.user_id =
        :userId
WHERE channel.code = :code
  AND channel.closed_at IS NULL
LIMIT 1
SQL,
                    [
                        'code' =>
                            $code,

                        'userId' =>
                            $this->currentUser
                                ->id(),
                    ],
                );

        if ($row !== false) {
            return [
                'id' =>
                    (int) $row['id'],

                'creator_user_id' =>
                    (int) $row[
                        'creator_user_id'
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
}
