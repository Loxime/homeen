<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class ChannelInvitationRepository
{
    public function __construct(
        private Connection $connection,
        private CurrentUser $currentUser,
        private UserRepository $users,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pending(): array
    {
        $rows = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    invitation.id,
    invitation.created_at
        AS "createdAt",
    invitation.seen_at
        AS "seenAt",

    channel.id
        AS "channelId",
    channel.code,
    channel.name,
    channel.description,

    inviter_email.email
        AS "invitedByEmail"

FROM channel_invitation invitation

INNER JOIN channel
    ON channel.id =
        invitation.channel_id
   AND channel.closed_at IS NULL

INNER JOIN user_email inviter_email
    ON inviter_email.user_id =
        invitation.invited_by_user_id
   AND inviter_email.is_primary = TRUE

WHERE invitation.invited_user_id
    = :userId

ORDER BY
    invitation.created_at DESC,
    invitation.id DESC
SQL,
                [
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );

        return array_map(
            static function (
                array $row,
            ): array {
                $code =
                    trim(
                        (string) $row['code']
                    );

                return [
                    'id' =>
                        (int) $row['id'],

                    'channelId' =>
                        (int) $row[
                            'channelId'
                        ],

                    'code' =>
                        $code,

                    'formattedCode' =>
                        ChannelRepository
                            ::formatCode($code),

                    'name' =>
                        (string) $row[
                            'name'
                        ],

                    'description' =>
                        (string) $row[
                            'description'
                        ],

                    'invitedByEmail' =>
                        (string) $row[
                            'invitedByEmail'
                        ],

                    'createdAt' =>
                        (string) $row[
                            'createdAt'
                        ],

                    'seenAt' =>
                        $row['seenAt']
                            !== null
                                ? (string) $row[
                                    'seenAt'
                                ]
                                : null,
                ];
            },
            $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function invite(
        string $code,
        string $email,
    ): array {
        $currentUserId =
            $this->currentUser->id();

        $target =
            $this->users
                ->findIdentityByEmail(
                    $email
                );

        if ($target === null) {
            throw new \DomainException(
                'INVITEE_NOT_FOUND'
            );
        }

        if (
            $target['id']
            === $currentUserId
        ) {
            throw new \DomainException(
                'CANNOT_INVITE_SELF'
            );
        }

        $channel = $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT
    id,
    name,
    creator_user_id
        AS "creatorUserId"
FROM channel
WHERE code = :code
  AND closed_at IS NULL
SQL,
                [
                    'code' => $code,
                ],
            );

        if ($channel === false) {
            throw new \OutOfBoundsException(
                'Channel not found.'
            );
        }

        if (
            (int) $channel[
                'creatorUserId'
            ] !== $currentUserId
        ) {
            throw new \DomainException(
                'CHANNEL_CREATOR_REQUIRED'
            );
        }

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
                            (int) $channel['id'],

                        'userId' =>
                            $target['id'],
                    ],
                );

        if ($alreadyMember !== false) {
            throw new \DomainException(
                'ALREADY_MEMBER'
            );
        }

        try {
            $id = $this->connection
                ->fetchOne(
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
RETURNING id
SQL,
                    [
                        'channelId' =>
                            (int) $channel['id'],

                        'invitedUserId' =>
                            $target['id'],

                        'invitedByUserId' =>
                            $currentUserId,
                    ],
                );
        } catch (
            UniqueConstraintViolationException
        ) {
            throw new \DomainException(
                'ALREADY_INVITED'
            );
        }

        if ($id === false) {
            throw new \RuntimeException(
                'Unable to create channel invitation.'
            );
        }

        return [
            'id' => (int) $id,
            'email' =>
                $target['email'],
            'channelCode' => $code,
            'formattedCode' =>
                ChannelRepository
                    ::formatCode($code),
        ];
    }

    /**
     * @return array{
     *     code:string,
     *     formattedCode:string
     * }
     */
    public function accept(
        int $invitationId,
    ): array {
        $userId =
            $this->currentUser->id();

        return $this->connection
            ->transactional(
                function (
                    Connection $connection
                ) use (
                    $invitationId,
                    $userId,
                ): array {
                    $invitation =
                        $connection
                            ->fetchAssociative(
                                <<<'SQL'
SELECT
    invitation.channel_id
        AS "channelId",
    channel.code
FROM channel_invitation invitation
INNER JOIN channel
    ON channel.id =
        invitation.channel_id
WHERE invitation.id = :id
  AND invitation.invited_user_id
        = :userId
  AND channel.closed_at IS NULL
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
                            'Invitation not found.'
                        );
                    }

                    $connection
                        ->executeStatement(
                            <<<'SQL'
INSERT INTO channel_member (
    channel_id,
    user_id
)
VALUES (
    :channelId,
    :userId
)
ON CONFLICT (
    channel_id,
    user_id
)
DO NOTHING
SQL,
                            [
                                'channelId' =>
                                    (int) $invitation[
                                        'channelId'
                                    ],

                                'userId' =>
                                    $userId,
                            ],
                        );

                    $connection
                        ->delete(
                            'channel_invitation',
                            [
                                'id' =>
                                    $invitationId,
                            ],
                        );

                    $code =
                        trim(
                            (string) $invitation[
                                'code'
                            ]
                        );

                    return [
                        'code' => $code,

                        'formattedCode' =>
                            ChannelRepository
                                ::formatCode(
                                    $code
                                ),
                    ];
                },
            );
    }

    public function reject(
        int $invitationId,
    ): void {
        $affected =
            $this->connection
                ->executeStatement(
                    <<<'SQL'
DELETE FROM channel_invitation
WHERE id = :id
  AND invited_user_id = :userId
SQL,
                    [
                        'id' =>
                            $invitationId,

                        'userId' =>
                            $this->currentUser->id(),
                    ],
                );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Invitation not found.'
            );
        }
    }

    public function unreadCount(): int
    {
        return (int) $this->connection
            ->fetchOne(
                <<<'SQL'
SELECT COUNT(*)
FROM channel_invitation
WHERE invited_user_id = :userId
  AND seen_at IS NULL
SQL,
                [
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );
    }

    public function markAllSeen(): void
    {
        $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE channel_invitation
SET seen_at = NOW()
WHERE invited_user_id = :userId
  AND seen_at IS NULL
SQL,
                [
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );
    }
}
