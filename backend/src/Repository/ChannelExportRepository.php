<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final readonly class ChannelExportRepository
{
    public function __construct(
        private Connection $connection,
        private ChannelRepository $channels,
    ) {
    }

    /**
     * @return array{
     *     exportVersion:int,
     *     exportedAt:string,
     *     channel:array<string, mixed>,
     *     members:list<array<string, mixed>>,
     *     notes:list<array<string, mixed>>,
     *     tasks:list<array<string, mixed>>,
     *     messages:list<array<string, mixed>>
     * }
     */
    public function export(
        string $code,
    ): array {
        $channel =
            $this->channels
                ->getAccessible(
                    $code,
                );

        if (
            !(
                $channel['isCreator']
                ?? false
            )
        ) {
            throw new \DomainException(
                'CHANNEL_EXPORT_CREATOR_REQUIRED',
            );
        }

        $channelId =
            (int) $channel['id'];

        $members =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    member.user_id AS "userId",
    primary_email.email,
    member.joined_at AS "joinedAt",
    (
        member.user_id =
        channel.creator_user_id
    ) AS "isCreator"
FROM channel_member member
INNER JOIN channel
    ON channel.id = member.channel_id
LEFT JOIN user_email primary_email
    ON primary_email.user_id =
        member.user_id
   AND primary_email.is_primary = TRUE
WHERE member.channel_id = :channelId
ORDER BY
    "isCreator" DESC,
    lower(primary_email.email)
SQL,
                    [
                        'channelId' =>
                            $channelId,
                    ],
                );

        /*
         * Selecting note.* keeps the export useful
         * when the note schema gains new fields:
         * new columns automatically become part
         * of future exports.
         */
        $notes =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT note.*
FROM note
WHERE note.channel_id = :channelId
ORDER BY note.id ASC
SQL,
                    [
                        'channelId' =>
                            $channelId,
                    ],
                );

        /*
         * Tasks belong to channel notes rather
         * than directly to a channel.
         */
        $tasks =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT task.*
FROM task
INNER JOIN note
    ON note.id = task.note_id
WHERE note.channel_id = :channelId
ORDER BY task.id ASC
SQL,
                    [
                        'channelId' =>
                            $channelId,
                    ],
                );

        $messages =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    message.id,
    message.author_user_id
        AS "authorUserId",
    author_email.email
        AS "authorEmail",
    message.content,
    message.created_at
        AS "createdAt",
    message.updated_at
        AS "updatedAt",
    message.edited_at
        AS "editedAt"
FROM channel_message message
LEFT JOIN user_email author_email
    ON author_email.user_id =
        message.author_user_id
   AND author_email.is_primary = TRUE
WHERE message.channel_id = :channelId
ORDER BY message.id ASC
SQL,
                    [
                        'channelId' =>
                            $channelId,
                    ],
                );

        return [
            'exportVersion' =>
                1,

            'exportedAt' =>
                gmdate(
                    DATE_ATOM,
                ),

            'channel' =>
                $channel,

            'members' =>
                $members,

            'notes' =>
                $notes,

            'tasks' =>
                $tasks,

            'messages' =>
                $messages,
        ];
    }
}
