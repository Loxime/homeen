<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelUnreadRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ChannelUnreadController
{
    public function __construct(
        private ChannelUnreadRepository $unread,
    ) {
    }

    #[Route(
        '/api/channels/unread-messages',
        name: 'api_channel_unread_messages',
        methods: ['GET'],
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'total' =>
                $this->unread->total(),

            'channels' =>
                $this->unread->counts(),
        ]);
    }
}
