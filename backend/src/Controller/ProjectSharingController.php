<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\ProjectInviteInput;
use App\Dto\Input\ProjectMemberRoleInput;
use App\Repository\ProjectSharingRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ProjectSharingController
{
    public function __construct(
        private ProjectSharingRepository $sharing,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '/api/project-invitations',
        name: 'api_project_invitations_pending',
        methods: ['GET'],
    )]
    public function pending(): JsonResponse
    {
        return new JsonResponse([
            'invitations' =>
                $this->sharing->pending(),
        ]);
    }

    #[Route(
        '/api/projects/{id<\d+>}/invitees/lookup',
        name: 'api_project_invitee_lookup',
        methods: ['POST'],
    )]
    public function lookupInvitee(
        int $id,
        Request $request,
    ): JsonResponse {
        $input =
            ProjectInviteInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->sharing
                ->lookupInvitee(
                    $id,
                    $input->email,
                ),
        );
    }

    #[Route(
        '/api/projects/{id<\d+>}/invitations',
        name: 'api_project_invitation_create',
        methods: ['POST'],
    )]
    public function invite(
        int $id,
        Request $request,
    ): JsonResponse {
        $input =
            ProjectInviteInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->sharing->invite(
                $id,
                $input->email,
            ),
            201,
        );
    }

    #[Route(
        '/api/project-invitations/{id<\d+>}/accept',
        name: 'api_project_invitation_accept',
        methods: ['POST'],
    )]
    public function accept(
        int $id,
    ): JsonResponse {
        return new JsonResponse(
            $this->sharing->accept(
                $id,
            ),
        );
    }

    #[Route(
        '/api/project-invitations/{id<\d+>}',
        name: 'api_project_invitation_reject',
        methods: ['DELETE'],
    )]
    public function reject(
        int $id,
    ): JsonResponse {
        $this->sharing->reject(
            $id,
        );

        return new JsonResponse(
            null,
            204,
        );
    }

    #[Route(
        '/api/projects/{id<\d+>}/members/{userId<\d+>}/role',
        name: 'api_project_member_role',
        methods: ['PUT'],
    )]
    public function role(
        int $id,
        int $userId,
        Request $request,
    ): JsonResponse {
        $input =
            ProjectMemberRoleInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->sharing->setRole(
                $id,
                $userId,
                $input->role,
            ),
        );
    }

    #[Route(
        '/api/projects/{id<\d+>}/members/{userId<\d+>}',
        name: 'api_project_member_remove',
        methods: ['DELETE'],
    )]
    public function remove(
        int $id,
        int $userId,
    ): JsonResponse {
        $this->sharing
            ->removeMember(
                $id,
                $userId,
            );

        return new JsonResponse(
            null,
            204,
        );
    }

    #[Route(
        '/api/projects/{id<\d+>}/leave',
        name: 'api_project_leave',
        methods: ['POST'],
    )]
    public function leave(
        int $id,
    ): JsonResponse {
        $this->sharing->leave(
            $id,
        );

        return new JsonResponse(
            null,
            204,
        );
    }
}
