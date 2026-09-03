<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/channels')]
final readonly class ChannelController
{
    public function __construct(
        private ChannelRepository $channels,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '',
        name: 'api_channels_list',
        methods: ['GET'],
    )]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'channels' =>
                $this->channels
                    ->allForCurrentUser(),
        ]);
    }

    #[Route(
        '',
        name: 'api_channels_create',
        methods: ['POST'],
    )]
    public function create(
        Request $request,
    ): JsonResponse {
        $data = $this->input
            ->read($request);

        return new JsonResponse(
            $this->channels->create(
                (string) (
                    $data['name']
                    ?? ''
                ),
                (string) (
                    $data['description']
                    ?? ''
                ),
            ),
            201,
        );
    }

    #[Route(
        '/{code<\d{9}>}',
        name: 'api_channels_show',
        methods: ['GET'],
    )]
    public function show(
        string $code,
    ): JsonResponse {
        try {
            return new JsonResponse(
                $this->channels
                    ->getAccessible(
                        $code
                    ),
            );
        } catch (
            \DomainException
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        sprintf(
                            'You are not authorized to enter channel %s.',
                            ChannelRepository::formatCode(
                                $code
                            ),
                        ),

                    'code' =>
                        'CHANNEL_FORBIDDEN',

                    'channelCode' =>
                        $code,

                    'formattedCode' =>
                        ChannelRepository::formatCode(
                            $code
                        ),
                ],
                403,
            );
        } catch (
            \OutOfBoundsException
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
