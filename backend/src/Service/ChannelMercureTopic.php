<?php

declare(strict_types=1);

namespace App\Service;

final readonly class ChannelMercureTopic
{
    public function messagesForUser(
        string $code,
        int $userId,
    ): string {
        return sprintf(
            'urn:homeen:user:%d:channel:%s:messages',
            $userId,
            $code,
        );
    }

    public function notifications(
        int $userId,
    ): string {
        return sprintf(
            'urn:homeen:user:%d:channel-notifications',
            $userId,
        );
    }
}
