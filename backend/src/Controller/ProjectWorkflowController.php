<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\ProjectWorkflowOrderInput;
use App\Dto\Input\ProjectWorkflowStageInput;
use App\Repository\ProjectWorkflowRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/projects/{projectId<\d+>}/workflow'
)]
final readonly class ProjectWorkflowController
{
    public function __construct(
        private ProjectWorkflowRepository $workflow,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '',
        name: 'api_project_workflow',
        methods: ['GET'],
    )]
    public function list(
        int $projectId,
    ): JsonResponse {
        return new JsonResponse([
            'stages' =>
                $this->workflow
                    ->forProject(
                        $projectId,
                    ),
        ]);
    }

    #[Route(
        '/stages',
        name: 'api_project_workflow_stage_create',
        methods: ['POST'],
    )]
    public function create(
        int $projectId,
        Request $request,
    ): JsonResponse {
        $input =
            ProjectWorkflowStageInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->workflow->create(
                $projectId,
                $input->name,
            ),
            201,
        );
    }

    #[Route(
        '/stages/{stageId<\d+>}',
        name: 'api_project_workflow_stage_update',
        methods: ['PUT'],
    )]
    public function update(
        int $projectId,
        int $stageId,
        Request $request,
    ): JsonResponse {
        $input =
            ProjectWorkflowStageInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->workflow->rename(
                $projectId,
                $stageId,
                $input->name,
            ),
        );
    }

    #[Route(
        '/order',
        name: 'api_project_workflow_order',
        methods: ['PUT'],
    )]
    public function reorder(
        int $projectId,
        Request $request,
    ): JsonResponse {
        $input =
            ProjectWorkflowOrderInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse([
            'stages' =>
                $this->workflow
                    ->reorder(
                        $projectId,
                        $input->stageIds,
                    ),
        ]);
    }

    #[Route(
        '/stages/{stageId<\d+>}',
        name: 'api_project_workflow_stage_delete',
        methods: ['DELETE'],
    )]
    public function delete(
        int $projectId,
        int $stageId,
    ): JsonResponse {
        $this->workflow->delete(
            $projectId,
            $stageId,
        );

        return new JsonResponse(
            null,
            204,
        );
    }
}
