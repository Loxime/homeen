<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelManagementRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/channels/{code<\d{9}>}/management'
)]
final readonly class ChannelManagementController
{
    public function __construct(
        private ChannelManagementRepository $management,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '/permissions',
        name: 'api_channel_management_permissions',
        methods: ['GET'],
    )]
    public function permissions(
        string $code,
    ): JsonResponse {
        try {
            return new JsonResponse(
                $this->management
                    ->permissions(
                        $code,
                    ),
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception,
            );
        }
    }

    #[Route(
        '/invitations',
        name: 'api_channel_management_invite',
        methods: ['POST'],
    )]
    public function invite(
        string $code,
        Request $request,
    ): JsonResponse {
        $data =
            $this->input
                ->read(
                    $request,
                );

        try {
            return new JsonResponse(
                $this->management
                    ->invite(
                        $code,
                        (string) (
                            $data['email']
                            ?? ''
                        ),
                    ),
                201,
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception,
            );
        }
    }

    #[Route(
        '/members/{userId<\d+>}',
        name: 'api_channel_management_member_remove',
        methods: ['DELETE'],
    )]
    public function remove(
        string $code,
        int $userId,
    ): JsonResponse {
        try {
            $this->management
                ->removeMember(
                    $code,
                    $userId,
                );

            return new JsonResponse(
                null,
                204,
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception,
            );
        }
    }

    private function error(
        \Throwable $exception,
    ): JsonResponse {
        if (
            $exception
            instanceof \InvalidArgumentException
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        $exception
                            ->getMessage(),

                    'code' =>
                        'INVALID_EMAIL',
                ],
                422,
            );
        }

        if (
            $exception
            instanceof \DomainException
        ) {
            return match (
                $exception->getMessage()
            ) {
                'CHANNEL_MANAGEMENT_REQUIRED' =>
                    new JsonResponse(
                        [
                            'error' =>
                                'Channel administrator privileges are required.',

                            'code' =>
                                'CHANNEL_MANAGEMENT_REQUIRED',
                        ],
                        403,
                    ),

                'CHANNEL_ALREADY_MEMBER' =>
                    new JsonResponse(
                        [
                            'error' =>
                                'This user is already a member of the channel.',

                            'code' =>
                                'CHANNEL_ALREADY_MEMBER',
                        ],
                        409,
                    ),

                'CHANNEL_CANNOT_REMOVE_CREATOR' =>
                    new JsonResponse(
                        [
                            'error' =>
                                'The channel creator cannot be removed.',

                            'code' =>
                                'CHANNEL_CANNOT_REMOVE_CREATOR',
                        ],
                        409,
                    ),

                'CHANNEL_ADMIN_CANNOT_REMOVE_ADMIN' =>
                    new JsonResponse(
                        [
                            'error' =>
                                'An administrator cannot remove another administrator.',

                            'code' =>
                                'CHANNEL_ADMIN_CANNOT_REMOVE_ADMIN',
                        ],
                        403,
                    ),

                'CHANNEL_MANAGER_USE_LEAVE' =>
                    new JsonResponse(
                        [
                            'error' =>
                                'Use the leave-channel action to remove yourself.',

                            'code' =>
                                'CHANNEL_MANAGER_USE_LEAVE',
                        ],
                        409,
                    ),

                default =>
                    new JsonResponse(
                        [
                            'error' =>
                                'Channel access denied.',

                            'code' =>
                                'CHANNEL_FORBIDDEN',
                        ],
                        403,
                    ),
            };
        }

        if (
            $exception
            instanceof \OutOfBoundsException
        ) {
            $code =
                match (
                    $exception->getMessage()
                ) {
                    'User not found.' =>
                        'CHANNEL_USER_NOT_FOUND',

                    'Channel member not found.' =>
                        'CHANNEL_MEMBER_NOT_FOUND',

                    default =>
                        'CHANNEL_NOT_FOUND',
                };

            return new JsonResponse(
                [
                    'error' =>
                        $exception
                            ->getMessage(),

                    'code' =>
                        $code,
                ],
                404,
            );
        }

        throw $exception;
    }
}
