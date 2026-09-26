<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProjectRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/projects')]
final readonly class ProjectController
{
    public function __construct(
        private ProjectRepository $projects,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '',
        name: 'api_projects_list',
        methods: ['GET'],
    )]
    public function list(
        Request $request,
    ): JsonResponse {
        return new JsonResponse([
            'projects' =>
                $this->projects->all(
                    $request->query
                        ->getString(
                            'scope',
                            'active',
                        ),
                ),
        ]);
    }

    #[Route(
        '',
        name: 'api_projects_create',
        methods: ['POST'],
    )]
    public function create(
        Request $request,
    ): JsonResponse {
        $data =
            $this->input->read(
                $request,
            );

        return new JsonResponse(
            $this->projects->create(
                (string) (
                    $data['name']
                    ?? ''
                ),
                (string) (
                    $data['description']
                    ?? ''
                ),
                (string) (
                    $data['color']
                    ?? '#1A73E8'
                ),
            ),
            201,
        );
    }

    #[Route(
        '/{id<\d+>}',
        name: 'api_projects_get',
        methods: ['GET'],
    )]
    public function get(
        int $id,
    ): JsonResponse {
        return new JsonResponse(
            $this->projects->get($id),
        );
    }

    #[Route(
        '/{id<\d+>}',
        name: 'api_projects_update',
        methods: ['PUT'],
    )]
    public function update(
        int $id,
        Request $request,
    ): JsonResponse {
        $data =
            $this->input->read(
                $request,
            );

        try {
            return new JsonResponse(
                $this->projects->update(
                    $id,
                    array_key_exists(
                        'name',
                        $data,
                    )
                        ? (string) $data['name']
                        : null,

                    array_key_exists(
                        'description',
                        $data,
                    )
                        ? (string) $data[
                            'description'
                        ]
                        : null,

                    array_key_exists(
                        'color',
                        $data,
                    )
                        ? (string) $data['color']
                        : null,
                ),
            );
        } catch (
            \DomainException $exception
        ) {
            if (
                $exception->getMessage()
                !== 'PROJECT_MANAGEMENT_REQUIRED'
            ) {
                throw $exception;
            }

            return new JsonResponse(
                [
                    'error' =>
                        'Project administrator privileges are required.',

                    'code' =>
                        'PROJECT_MANAGEMENT_REQUIRED',
                ],
                403,
            );
        }
    }

    #[Route(
        '/{id<\d+>}/members',
        name: 'api_projects_members',
        methods: ['GET'],
    )]
    public function members(
        int $id,
    ): JsonResponse {
        return new JsonResponse([
            'members' =>
                $this->projects
                    ->members($id),
        ]);
    }
}
