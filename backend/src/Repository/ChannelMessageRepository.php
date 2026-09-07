<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class ChannelMessageRepository
{
    public function __construct(
        private Connection $connection,
        private CurrentUser $currentUser,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(
        string $code,
        ?int $afterId = null,
    ): array {
        $channelId =
            $this->accessibleChannelId(
                $code
            );

        if ($afterId !== null) {
            $rows = $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    message.id,
    message.content,
    message.author_user_id
        AS "authorUserId",
    author_email.email
        AS "authorEmail",
    message.created_at
        AS "createdAt",
    message.updated_at
        AS "updatedAt",
    message.edited_at
        AS "editedAt",
    CASE
        WHEN message.author_user_id = :userId
        THEN TRUE
        ELSE FALSE
    END AS "isMine"
FROM channel_message message
LEFT JOIN user_email author_email
    ON author_email.user_id =
        message.author_user_id
   AND author_email.is_primary = TRUE
WHERE message.channel_id = :channelId
  AND message.id > :afterId
ORDER BY message.id ASC
LIMIT 100
SQL,
                    [
                        'channelId' =>
                            $channelId,

                        'userId' =>
                            $this
                                ->currentUser
                                ->id(),

                        'afterId' =>
                            $afterId,
                    ],
                );

            return array_map(
                $this->normalize(...),
                $rows,
            );
        }

        /*
         * Initial opening:
         * fetch only the most recent 100 messages,
         * then restore chronological order.
         */
        $rows = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT *
FROM (
    SELECT
        message.id,
        message.content,
        message.author_user_id
            AS "authorUserId",
        author_email.email
            AS "authorEmail",
        message.created_at
            AS "createdAt",
        message.updated_at
            AS "updatedAt",
        message.edited_at
            AS "editedAt",
        CASE
            WHEN message.author_user_id = :userId
            THEN TRUE
            ELSE FALSE
        END AS "isMine"
    FROM channel_message message
    LEFT JOIN user_email author_email
        ON author_email.user_id =
            message.author_user_id
       AND author_email.is_primary = TRUE
    WHERE message.channel_id = :channelId
    ORDER BY message.id DESC
    LIMIT 100
) latest_messages
ORDER BY id ASC
SQL,
                [
                    'channelId' =>
                        $channelId,

                    'userId' =>
                        $this
                            ->currentUser
                            ->id(),
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
    public function create(
        string $code,
        string $content,
    ): array {
        $content = trim($content);

        if (
            $content === ''
            || mb_strlen($content) > 4000
        ) {
            throw new \InvalidArgumentException(
                'Message content must contain between 1 and 4000 characters.'
            );
        }

        $channelId =
            $this->accessibleChannelId(
                $code
            );

        $userId =
            $this->currentUser->id();

        $row = $this->connection
            ->fetchAssociative(
                <<<'SQL'
INSERT INTO channel_message (
    channel_id,
    author_user_id,
    content
)
VALUES (
    :channelId,
    :userId,
    :content
)
RETURNING
    id,
    content,
    author_user_id
        AS "authorUserId",
    created_at
        AS "createdAt",
    updated_at
        AS "updatedAt",
    edited_at
        AS "editedAt"
SQL,
                [
                    'channelId' =>
                        $channelId,

                    'userId' =>
                        $userId,

                    'content' =>
                        $content,
                ],
            );

        if ($row === false) {
            throw new \RuntimeException(
                'Unable to create channel message.'
            );
        }

        $authorEmail =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT email
FROM user_email
WHERE user_id = :userId
  AND is_primary = TRUE
LIMIT 1
SQL,
                    [
                        'userId' =>
                            $userId,
                    ],
                );

        $row['authorEmail'] =
            $authorEmail !== false
                ? (string) $authorEmail
                : null;

        $row['isMine'] = true;

        return $this->normalize(
            $row
        );
    }

    public function markRead(
        string $code,
    ): void {
        $channelId =
            $this->accessibleChannelId(
                $code
            );

        $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE channel_member
SET last_read_message_at = NOW()
WHERE channel_id = :channelId
  AND user_id = :userId
SQL,
                [
                    'channelId' =>
                        $channelId,

                    'userId' =>
                        $this
                            ->currentUser
                            ->id(),
                ],
            );
    }

    private function accessibleChannelId(
        string $code,
    ): int {
        if (
            preg_match(
                '/^\d{9}$/',
                $code,
            ) !== 1
        ) {
            throw new \OutOfBoundsException(
                'Channel not found.'
            );
        }

        $channelId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT channel.id
FROM channel
INNER JOIN channel_member member
    ON member.channel_id = channel.id
   AND member.user_id = :userId
WHERE channel.code = :code
  AND channel.closed_at IS NULL
LIMIT 1
SQL,
                    [
                        'code' =>
                            $code,

                        'userId' =>
                            $this
                                ->currentUser
                                ->id(),
                    ],
                );

        if ($channelId !== false) {
            return (int) $channelId;
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
                'CHANNEL_FORBIDDEN'
            );
        }

        throw new \OutOfBoundsException(
            'Channel not found.'
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

        $row['authorUserId'] =
            $row['authorUserId']
                !== null
                    ? (int) $row[
                        'authorUserId'
                    ]
                    : null;

        $row['isMine'] =
            filter_var(
                $row['isMine'],
                FILTER_VALIDATE_BOOLEAN,
            );

        return $row;
    }
}
