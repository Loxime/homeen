<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelExportRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ChannelExportController
{
    public function __construct(
        private ChannelExportRepository $exports,
    ) {
    }

    #[Route(
        '/api/channels/{code<\d{9}>}/export',
        name: 'api_channel_export',
        methods: ['GET'],
    )]
    public function __invoke(
        string $code,
    ): JsonResponse {
        try {
            $response =
                new JsonResponse(
                    $this->exports
                        ->export(
                            $code,
                        ),
                );

            $response->setEncodingOptions(
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            );

            $response->headers->set(
                'Content-Disposition',
                sprintf(
                    'attachment; filename="homeen-channel-%s.json"',
                    $code,
                ),
            );

            return $response;
        } catch (
            \DomainException $exception
        ) {
            if (
                $exception->getMessage()
                === 'CHANNEL_EXPORT_CREATOR_REQUIRED'
            ) {
                return new JsonResponse(
                    [
                        'error' =>
                            'Only the channel creator can export this channel.',

                        'code' =>
                            'CHANNEL_EXPORT_CREATOR_REQUIRED',
                    ],
                    403,
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
