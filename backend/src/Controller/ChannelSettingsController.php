<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelSettingsRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/channels/{code<\d{9}>}')]
final readonly class ChannelSettingsController
{
    public function __construct(
        private ChannelSettingsRepository $settings,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '/settings',
        name: 'api_channel_settings_update',
        methods: ['PATCH'],
    )]
    public function update(
        string $code,
        Request $request,
    ): JsonResponse {
        try {
            $data =
                $this->input
                    ->read(
                        $request,
                    );

            return new JsonResponse(
                $this->settings
                    ->update(
                        $code,
                        (string) (
                            $data['name']
                            ?? ''
                        ),
                        (string) (
                            $data['description']
                            ?? ''
                        ),
                        isset(
                            $data['profileImageUrl']
                        )
                            ? (string) $data[
                                'profileImageUrl'
                            ]
                            : null,
                    ),
            );
        } catch (
            \InvalidArgumentException $exception
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        $exception
                            ->getMessage(),

                    'code' =>
                        'CHANNEL_SETTINGS_INVALID',
                ],
                422,
            );
        } catch (
            \DomainException
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Only the channel creator can change these settings.',

                    'code' =>
                        'CHANNEL_CREATOR_REQUIRED',
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

    #[Route(
        '/close',
        name: 'api_channel_close',
        methods: ['POST'],
    )]
    public function close(
        string $code,
    ): JsonResponse {
        try {
            $this->settings
                ->close(
                    $code,
                );

            return new JsonResponse(
                null,
                204,
            );
        } catch (
            \DomainException
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Only the channel creator can close this channel.',

                    'code' =>
                        'CHANNEL_CREATOR_REQUIRED',
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
