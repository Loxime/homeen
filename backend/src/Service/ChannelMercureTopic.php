<?php

declare(strict_types=1);

namespace App\Service;

final readonly class ChannelMercureTopic
{
    public function messages(
        string $code,
    ): string {
        return sprintf(
            'urn:homeen:channel:%s:messages',
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
