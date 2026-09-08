<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class ChannelLeaveRepository
{
    public function __construct(
        private Connection $connection,
        private CurrentUser $currentUser,
        private ChannelRepository $channels,
    ) {
    }

    public function leave(
        string $code,
    ): void {
        $channel =
            $this->channels
                ->getAccessible(
                    $code,
                );

        if (
            (
                $channel['isCreator']
                ?? false
            )
        ) {
            throw new \DomainException(
                'CHANNEL_CREATOR_CANNOT_LEAVE',
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
                            (int) $channel['id'],

                        'userId' =>
                            $this->currentUser
                                ->id(),
                    ],
                );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Channel membership not found.',
            );
        }
    }
}
