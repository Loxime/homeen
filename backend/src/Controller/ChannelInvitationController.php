<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelInvitationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/channel-invitations')]
final readonly class ChannelInvitationController
{
    public function __construct(
        private ChannelInvitationRepository $invitations,
    ) {
    }

    #[Route(
        '',
        name: 'api_channel_invitations',
        methods: ['GET'],
    )]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'invitations' =>
                $this->invitations
                    ->pending(),
        ]);
    }

    #[Route(
        '/unread-count',
        name: 'api_channel_invitations_unread_count',
        methods: ['GET'],
    )]
    public function unreadCount(): JsonResponse
    {
        return new JsonResponse([
            'count' =>
                $this->invitations
                    ->unreadCount(),
        ]);
    }

    #[Route(
        '/seen',
        name: 'api_channel_invitations_seen',
        methods: ['POST'],
    )]
    public function seen(): JsonResponse
    {
        $this->invitations
            ->markAllSeen();

        return new JsonResponse(
            null,
            204,
        );
    }

    #[Route(
        '/{id<\d+>}/accept',
        name: 'api_channel_invitation_accept',
        methods: ['POST'],
    )]
    public function accept(
        int $id,
    ): JsonResponse {
        try {
            return new JsonResponse(
                $this->invitations
                    ->accept($id),
            );
        } catch (
            \OutOfBoundsException
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Invitation not found.',
                    'code' =>
                        'INVITATION_NOT_FOUND',
                ],
                404,
            );
        }
    }

    #[Route(
        '/{id<\d+>}/reject',
        name: 'api_channel_invitation_reject',
        methods: ['POST'],
    )]
    public function reject(
        int $id,
    ): JsonResponse {
        try {
            $this->invitations
                ->reject($id);

            return new JsonResponse(
                null,
                204,
            );
        } catch (
            \OutOfBoundsException
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Invitation not found.',
                    'code' =>
                        'INVITATION_NOT_FOUND',
                ],
                404,
            );
        }
    }
}
