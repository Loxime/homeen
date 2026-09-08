<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class ChannelUnreadRepository
{
    public function __construct(
        private Connection $connection,
        private CurrentUser $currentUser,
    ) {
    }

    /**
     * @return list<array{
     *     channelId: int,
     *     channelCode: string,
     *     unreadCount: int
     * }>
     */
    public function counts(): array
    {
        $userId =
            $this->currentUser->id();

        $rows =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    channel.id
        AS "channelId",
    channel.code
        AS "channelCode",
    COUNT(message.id)
        AS "unreadCount"
FROM channel_member member
INNER JOIN channel
    ON channel.id = member.channel_id
   AND channel.closed_at IS NULL
LEFT JOIN channel_message message
    ON message.channel_id = channel.id
   AND message.author_user_id IS DISTINCT FROM :userId
   AND (
       member.last_read_message_at IS NULL
       OR message.created_at
          > member.last_read_message_at
   )
WHERE member.user_id = :userId
GROUP BY
    channel.id,
    channel.code
HAVING COUNT(message.id) > 0
ORDER BY channel.id
SQL,
                    [
                        'userId' =>
                            $userId,
                    ],
                );

        return array_map(
            static function (
                array $row,
            ): array {
                return [
                    'channelId' =>
                        (int) $row[
                            'channelId'
                        ],

                    'channelCode' =>
                        (string) $row[
                            'channelCode'
                        ],

                    'unreadCount' =>
                        (int) $row[
                            'unreadCount'
                        ],
                ];
            },
            $rows,
        );
    }

    public function total(): int
    {
        $userId =
            $this->currentUser->id();

        return (int) $this->connection
            ->fetchOne(
                <<<'SQL'
SELECT COUNT(message.id)
FROM channel_member member
INNER JOIN channel
    ON channel.id = member.channel_id
   AND channel.closed_at IS NULL
INNER JOIN channel_message message
    ON message.channel_id = channel.id
   AND message.author_user_id IS DISTINCT FROM :userId
   AND (
       member.last_read_message_at IS NULL
       OR message.created_at
          > member.last_read_message_at
   )
WHERE member.user_id = :userId
SQL,
                [
                    'userId' =>
                        $userId,
                ],
            );
    }
}
