<?php

declare(strict_types=1);

namespace App\Controller;

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
        try {
            return new JsonResponse([
                'stages' =>
                    $this->workflow
                        ->forProject(
                            $projectId,
                        ),
            ]);
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
            );
        }
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
        $data =
            $this->input->read(
                $request
            );

        try {
            return new JsonResponse(
                $this->workflow->create(
                    $projectId,
                    (string) (
                        $data['name']
                        ?? ''
                    ),
                ),
                201,
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
            );
        }
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
        $data =
            $this->input->read(
                $request
            );

        try {
            return new JsonResponse(
                $this->workflow->rename(
                    $projectId,
                    $stageId,
                    (string) (
                        $data['name']
                        ?? ''
                    ),
                ),
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
            );
        }
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
        $data =
            $this->input->read(
                $request
            );

        $rawStageIds =
            $data['stageIds']
            ?? null;

        if (!is_array($rawStageIds)) {
            return $this->invalid(
                'stageIds must be an array.',
            );
        }

        $stageIds = [];

        foreach ($rawStageIds as $stageId) {
            if (
                !is_int($stageId)
                || $stageId <= 0
            ) {
                return $this->invalid(
                    'stageIds must contain positive integers.',
                );
            }

            $stageIds[] =
                $stageId;
        }

        try {
            return new JsonResponse([
                'stages' =>
                    $this->workflow
                        ->reorder(
                            $projectId,
                            $stageIds,
                        ),
            ]);
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
            );
        }
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
        try {
            $this->workflow->delete(
                $projectId,
                $stageId,
            );

            return new JsonResponse(
                null,
                204,
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
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
            return $this->invalid(
                $exception->getMessage(),
            );
        }

        if (
            $exception
            instanceof \OutOfBoundsException
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        $exception->getMessage(),

                    'code' =>
                        'PROJECT_WORKFLOW_NOT_FOUND',
                ],
                404,
            );
        }

        if (
            $exception
            instanceof \DomainException
        ) {
            $code =
                $exception->getMessage();

            return new JsonResponse(
                [
                    'error' =>
                        $code,

                    'code' =>
                        $code,
                ],
                $code
                    === 'PROJECT_MANAGEMENT_REQUIRED'
                        ? 403
                        : 409,
            );
        }

        throw $exception;
    }

    private function invalid(
        string $message,
    ): JsonResponse {
        return new JsonResponse(
            [
                'error' =>
                    $message,

                'code' =>
                    'PROJECT_WORKFLOW_INVALID',
            ],
            422,
        );
    }
}
