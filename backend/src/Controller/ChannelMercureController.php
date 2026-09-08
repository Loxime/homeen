<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelRepository;
use App\Service\ChannelMercureAuthorizationService;
use App\Service\ChannelMercureTopic;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ChannelMercureController
{
    public function __construct(
        private ChannelRepository $channels,
        private ChannelMercureTopic $topics,
        private ChannelMercureAuthorizationService $authorization,
        private HubInterface $hub,
    ) {
    }

    #[Route(
        '/api/channels/{code<\d{9}>}/mercure-auth',
        name: 'api_channel_mercure_auth',
        methods: ['POST'],
    )]
    public function authorize(
        string $code,
        Request $request,
    ): JsonResponse {
        try {
            /*
             * The requested channel must still
             * be accessible before its topic is
             * exposed to the frontend.
             */
            $this->channels->getAccessible(
                $code
            );

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
                    $this->topics
                        ->messagesForUser(
                            $code,
                            (int) $authorization[
                                'currentUserId'
                            ],
                        ),

                'currentUserId' =>
                    $authorization[
                        'currentUserId'
                    ],
            ]);
        } catch (
            \DomainException $exception
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Channel access denied.',

                    'code' =>
                        'CHANNEL_FORBIDDEN',
                ],
                403,
            );
        } catch (
            \OutOfBoundsException $exception
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Channel not found.',

                    'code' =>
                        'CHANNEL_NOT_FOUND',
                ],
                404,
            );
        }
    }
}
