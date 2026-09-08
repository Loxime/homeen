<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ChannelMercureAuthorizationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ChannelNotificationMercureController
{
    public function __construct(
        private ChannelMercureAuthorizationService $authorization,
        private HubInterface $hub,
    ) {
    }

    #[Route(
        '/api/channel-notifications/mercure-auth',
        name: 'api_channel_notification_mercure_auth',
        methods: ['POST'],
    )]
    public function __invoke(
        Request $request,
    ): JsonResponse {
        $authorization =
            $this->authorization
                ->authorize(
                    $request,
                );

        return new JsonResponse([
            'hubUrl' =>
                $this->hub
                    ->getPublicUrl(),

            'topic' =>
                $authorization[
                    'notificationTopic'
                ],

            'currentUserId' =>
                $authorization[
                    'currentUserId'
                ],
        ]);
    }
}
