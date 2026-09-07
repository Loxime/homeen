<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelRoleRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/channels/{code<\d{9}>}')]
final readonly class ChannelRoleController
{
    public function __construct(
        private ChannelRoleRepository $roles,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '/roles',
        name: 'api_channel_roles',
        methods: ['GET'],
    )]
    public function list(
        string $code,
    ): JsonResponse {
        try {
            return new JsonResponse([
                'members' =>
                    $this->roles
                        ->members(
                            $code,
                        ),
            ]);
        } catch (\Throwable $exception) {
            return $this->error(
                $exception,
            );
        }
    }

    #[Route(
        '/members/{memberUserId<\d+>}/role',
        name: 'api_channel_member_role_update',
        methods: ['PATCH'],
    )]
    public function update(
        string $code,
        int $memberUserId,
        Request $request,
    ): JsonResponse {
        $data =
            $this->input
                ->read(
                    $request,
                );

        try {
            return new JsonResponse(
                $this->roles
                    ->setRole(
                        $code,
                        $memberUserId,
                        (string) (
                            $data['role']
                            ?? ''
                        ),
                    ),
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception,
            );
        }
    }

    #[Route(
        '/ownership',
        name: 'api_channel_ownership_transfer',
        methods: ['PATCH'],
    )]
    public function transferOwnership(
        string $code,
        Request $request,
    ): JsonResponse {
        $data =
            $this->input
                ->read(
                    $request,
                );

        $newCreatorUserId =
            (int) (
                $data['newCreatorUserId']
                ?? 0
            );

        if ($newCreatorUserId <= 0) {
            return new JsonResponse(
                [
                    'error' =>
                        'newCreatorUserId must be a valid user identifier.',

                    'code' =>
                        'INVALID_CHANNEL_OWNER',
                ],
                422,
            );
        }

        try {
            return new JsonResponse(
                $this->roles
                    ->transferOwnership(
                        $code,
                        $newCreatorUserId,
                    ),
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
                        'INVALID_CHANNEL_ROLE',
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
                'CHANNEL_ROLE_CREATOR_REQUIRED' =>
                    new JsonResponse(
                        [
                            'error' =>
                                'Only the channel creator can manage roles.',

                            'code' =>
                                'CHANNEL_ROLE_CREATOR_REQUIRED',
                        ],
                        403,
                    ),

                'CHANNEL_NEW_CREATOR_SAME_USER' =>
                    new JsonResponse(
                        [
                            'error' =>
                                'You already own this channel.',

                            'code' =>
                                'CHANNEL_NEW_CREATOR_SAME_USER',
                        ],
                        409,
                    ),

                'CHANNEL_CREATOR_ROLE_IMMUTABLE' =>
                    new JsonResponse(
                        [
                            'error' =>
                                'The channel creator role cannot be changed.',

                            'code' =>
                                'CHANNEL_CREATOR_ROLE_IMMUTABLE',
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
            return new JsonResponse(
                [
                    'error' =>
                        $exception
                            ->getMessage(),

                    'code' =>
                        $exception->getMessage()
                            === 'Channel member not found.'
                            ? 'CHANNEL_MEMBER_NOT_FOUND'
                            : 'CHANNEL_NOT_FOUND',
                ],
                404,
            );
        }

        throw $exception;
    }
}
