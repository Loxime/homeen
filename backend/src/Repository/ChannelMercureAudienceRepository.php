<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class ChannelMercureAudienceRepository
{
    public function __construct(
        private Connection $connection,
        private CurrentUser $currentUser,
    ) {
    }

    /**
     * @return list<string>
     */
    public function accessibleChannelCodes(): array
    {
        $rows =
            $this->connection
                ->fetchFirstColumn(
                    <<<'SQL'
SELECT channel.code
FROM channel_member member
INNER JOIN channel
    ON channel.id = member.channel_id
WHERE member.user_id = :userId
  AND channel.closed_at IS NULL
ORDER BY channel.id
SQL,
                    [
                        'userId' =>
                            $this->currentUser
                                ->id(),
                    ],
                );

        return array_map(
            static fn (
                mixed $code,
            ): string =>
                (string) $code,
            $rows,
        );
    }

    /**
     * @return list<int>
     */
    public function recipientUserIds(
        string $code,
        int $authorUserId,
    ): array {
        $rows =
            $this->connection
                ->fetchFirstColumn(
                    <<<'SQL'
SELECT member.user_id
FROM channel_member member
INNER JOIN channel
    ON channel.id = member.channel_id
WHERE channel.code = :code
  AND channel.closed_at IS NULL
  AND member.user_id <> :authorUserId
ORDER BY member.user_id
SQL,
                    [
                        'code' =>
                            $code,

                        'authorUserId' =>
                            $authorUserId,
                    ],
                );

        return array_map(
            static fn (
                mixed $userId,
            ): int =>
                (int) $userId,
            $rows,
        );
    }
}
