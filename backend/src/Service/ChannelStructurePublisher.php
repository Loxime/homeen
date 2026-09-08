<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class ChannelStructurePublisher
{
    public function __construct(
        private Connection $connection,
        private ChannelMercureTopic $topics,
        private HubInterface $hub,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int> $extraUserIds
     */
    public function publish(
        string $code,
        string $event,
        array $data = [],
        array $extraUserIds = [],
    ): void {
        try {
            $userIds =
                array_map(
                    static fn (
                        mixed $userId,
                    ): int =>
                        (int) $userId,
                    $this->connection
                        ->fetchFirstColumn(
                            <<<'SQL'
SELECT member.user_id
FROM channel_member member
INNER JOIN channel
    ON channel.id = member.channel_id
WHERE channel.code = :code
SQL,
                            [
                                'code' =>
                                    $code,
                            ],
                        ),
                );

            $userIds =
                array_values(
                    array_unique([
                        ...$userIds,
                        ...$extraUserIds,
                    ]),
                );

            $userIds =
                array_values(
                    array_filter(
                        $userIds,
                        static fn (
                            int $userId,
                        ): bool =>
                            $userId > 0,
                    ),
                );

            if ($userIds === []) {
                return;
            }

            $notificationTopics =
                array_map(
                    fn (
                        int $userId,
                    ): string =>
                        $this->topics
                            ->notifications(
                                $userId,
                            ),
                    $userIds,
                );

            $payload =
                json_encode(
                    array_merge(
                        $data,
                        [
                            'type' =>
                                'channel-structure',

                            'event' =>
                                $event,

                            'channelCode' =>
                                $code,
                        ],
                    ),
                    JSON_THROW_ON_ERROR,
                );

            $this->hub
                ->publish(
                    new Update(
                        $notificationTopics,
                        $payload,
                        true,
                    ),
                );
        } catch (\Throwable) {
            /*
             * PostgreSQL is authoritative.
             * Realtime delivery is best effort
             * and must never break a successful
             * channel mutation.
             */
        }
    }
}
