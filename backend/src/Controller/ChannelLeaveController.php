<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelLeaveRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ChannelLeaveController
{
    public function __construct(
        private ChannelLeaveRepository $channels,
    ) {
    }

    #[Route(
        '/api/channels/{code<\d{9}>}/leave',
        name: 'api_channel_leave',
        methods: ['POST'],
    )]
    public function __invoke(
        string $code,
    ): JsonResponse {
        try {
            $this->channels
                ->leave(
                    $code,
                );

            return new JsonResponse(
                null,
                204,
            );
        } catch (
            \DomainException $exception
        ) {
            if (
                $exception->getMessage()
                === 'CHANNEL_CREATOR_CANNOT_LEAVE'
            ) {
                return new JsonResponse(
                    [
                        'error' =>
                            'The channel creator must close the channel instead.',

                        'code' =>
                            'CHANNEL_CREATOR_CANNOT_LEAVE',
                    ],
                    409,
                );
            }

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
                        $exception
                            ->getMessage(),

                    'code' =>
                        'CHANNEL_NOT_FOUND',
                ],
                404,
            );
        }
    }
}
