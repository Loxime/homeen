<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelInvitationRepository;
use App\Repository\ChannelRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/channels/{code<\d{9}>}')]
final readonly class ChannelMembershipController
{
    public function __construct(
        private ChannelRepository $channels,
        private ChannelInvitationRepository $invitations,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '/members',
        name: 'api_channel_members',
        methods: ['GET'],
    )]
    public function members(
        string $code,
    ): JsonResponse {
        try {
            return new JsonResponse([
                'members' =>
                    $this->channels
                        ->members($code),
            ]);
        } catch (
            \DomainException
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
        '/invitations',
        name: 'api_channel_invite',
        methods: ['POST'],
    )]
    public function invite(
        string $code,
        Request $request,
    ): JsonResponse {
        $data =
            $this->input
                ->read($request);

        $email = trim(
            (string) (
                $data['email']
                ?? ''
            )
        );

        if (
            $email === ''
            || filter_var(
                $email,
                FILTER_VALIDATE_EMAIL,
            ) === false
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Invalid email address.',
                    'code' =>
                        'INVALID_EMAIL',
                ],
                422,
            );
        }

        try {
            return new JsonResponse(
                $this->invitations
                    ->invite(
                        $code,
                        $email,
                    ),
                201,
            );
        } catch (
            \DomainException $exception
        ) {
            return match (
                $exception->getMessage()
            ) {
                'INVITEE_NOT_FOUND' =>
                    new JsonResponse(
                        [
                            'error' =>
                                'No Homeen account uses this email address.',
                            'code' =>
                                'INVITEE_NOT_FOUND',
                        ],
                        404,
                    ),

                'CANNOT_INVITE_SELF' =>
                    new JsonResponse(
                        [
                            'error' =>
                                'You cannot invite yourself.',
                            'code' =>
                                'CANNOT_INVITE_SELF',
                        ],
                        409,
                    ),

                'ALREADY_MEMBER' =>
                    new JsonResponse(
                        [
                            'error' =>
                                'This user is already a channel member.',
                            'code' =>
                                'ALREADY_MEMBER',
                        ],
                        409,
                    ),

                'ALREADY_INVITED' =>
                    new JsonResponse(
                        [
                            'error' =>
                                'This user already has a pending invitation.',
                            'code' =>
                                'ALREADY_INVITED',
                        ],
                        409,
                    ),

                default =>
                    new JsonResponse(
                        [
                            'error' =>
                                'Only the channel creator can invite members.',
                            'code' =>
                                'CHANNEL_CREATOR_REQUIRED',
                        ],
                        403,
                    ),
            };
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
        '/members/{userId<\d+>}',
        name: 'api_channel_member_remove',
        methods: ['DELETE'],
    )]
    public function remove(
        string $code,
        int $userId,
    ): JsonResponse {
        try {
            $this->channels
                ->removeMember(
                    $code,
                    $userId,
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
                === 'CREATOR_CANNOT_BE_REMOVED'
            ) {
                return new JsonResponse(
                    [
                        'error' =>
                            'The channel creator cannot be removed.',
                        'code' =>
                            'CREATOR_CANNOT_BE_REMOVED',
                    ],
                    409,
                );
            }

            return new JsonResponse(
                [
                    'error' =>
                        'Only the channel creator can remove members.',
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
                        'Channel member not found.',
                    'code' =>
                        'CHANNEL_MEMBER_NOT_FOUND',
                ],
                404,
            );
        }
    }
}
