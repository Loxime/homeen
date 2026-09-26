<?php

declare(strict_types=1);

namespace App\Controller;

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
        '/api/projects/{id<\d+>}/invitations',
        name: 'api_project_invitation_create',
        methods: ['POST'],
    )]
    public function invite(
        int $id,
        Request $request,
    ): JsonResponse {
        $data =
            $this->input->read(
                $request,
            );

        try {
            return new JsonResponse(
                $this->sharing->invite(
                    $id,
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
        '/api/project-invitations/{id<\d+>}/accept',
        name: 'api_project_invitation_accept',
        methods: ['POST'],
    )]
    public function accept(
        int $id,
    ): JsonResponse {
        try {
            return new JsonResponse(
                $this->sharing->accept(
                    $id,
                ),
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception,
            );
        }
    }

    #[Route(
        '/api/project-invitations/{id<\d+>}',
        name: 'api_project_invitation_reject',
        methods: ['DELETE'],
    )]
    public function reject(
        int $id,
    ): JsonResponse {
        try {
            $this->sharing->reject($id);

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
        $data =
            $this->input->read(
                $request,
            );

        try {
            return new JsonResponse(
                $this->sharing->setRole(
                    $id,
                    $userId,
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
        '/api/projects/{id<\d+>}/members/{userId<\d+>}',
        name: 'api_project_member_remove',
        methods: ['DELETE'],
    )]
    public function remove(
        int $id,
        int $userId,
    ): JsonResponse {
        try {
            $this->sharing
                ->removeMember(
                    $id,
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

    #[Route(
        '/api/projects/{id<\d+>}/leave',
        name: 'api_project_leave',
        methods: ['POST'],
    )]
    public function leave(
        int $id,
    ): JsonResponse {
        try {
            $this->sharing->leave($id);

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
                        'PROJECT_INVALID_INPUT',
                ],
                422,
            );
        }

        if (
            $exception
            instanceof \OutOfBoundsException
        ) {
            $code = match (
                $exception->getMessage()
            ) {
                'User not found.' =>
                    'PROJECT_USER_NOT_FOUND',

                'Project member not found.' =>
                    'PROJECT_MEMBER_NOT_FOUND',

                'Invitation not found.' =>
                    'PROJECT_INVITATION_NOT_FOUND',

                default =>
                    'PROJECT_NOT_FOUND',
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

        if (
            $exception
            instanceof \DomainException
        ) {
            $status = match (
                $exception->getMessage()
            ) {
                'PROJECT_MANAGEMENT_REQUIRED',
                'PROJECT_OWNER_REQUIRED',
                'PROJECT_ADMIN_CANNOT_REMOVE_ADMIN'
                    => 403,

                default => 409,
            };

            return new JsonResponse(
                [
                    'error' =>
                        $exception
                            ->getMessage(),

                    'code' =>
                        $exception
                            ->getMessage(),
                ],
                $status,
            );
        }

        throw $exception;
    }
}
