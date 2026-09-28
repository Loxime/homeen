<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\ProjectCreateInput;
use App\Dto\Input\ProjectUpdateInput;
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
        $input =
            ProjectCreateInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->projects->create(
                $input->name,
                $input->description,
                $input->color,
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
        $input =
            ProjectUpdateInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->projects->update(
                $id,
                $input->name,
                $input->description,
                $input->color,
            ),
        );
    }

    #[Route(
        '/{id<\d+>}',
        name: 'api_projects_delete',
        methods: ['DELETE'],
    )]
    public function delete(
        int $id,
    ): JsonResponse {
        $this->projects->delete(
            $id,
        );

        return new JsonResponse(
            null,
            204,
        );
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
