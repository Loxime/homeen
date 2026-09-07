<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelRepository;
use App\Service\ChannelMercureTopic;
use App\Service\CurrentUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ChannelMercureController
{
    public function __construct(
        private ChannelRepository $channels,
        private ChannelMercureTopic $topics,
        private CurrentUser $currentUser,
        private Authorization $authorization,
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
             * Security boundary:
             * never issue a Mercure subscriber token
             * before verifying channel membership.
             */
            $this->channels->getAccessible(
                $code
            );

            $topic =
                $this->topics->messages(
                    $code
                );

            /*
             * Symfony Mercure 0.8 interprets a
             * flat topic list as subscribe grants.
             */
            $this->authorization->setCookie(
                $request,
                [
                    $topic,
                ],
            );

            return new JsonResponse([
                'hubUrl' =>
                    $this->hub
                        ->getPublicUrl(),

                'topic' =>
                    $topic,

                'currentUserId' =>
                    $this->currentUser
                        ->id(),
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
