<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\ProjectTaskCreateInput;
use App\Dto\Input\ProjectTaskOrderInput;
use App\Dto\Input\ProjectTaskUpdateInput;
use App\Dto\Input\TaskCompletionInput;
use App\Repository\ProjectTaskRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/projects/{projectId<\d+>}/tasks'
)]
final readonly class ProjectTaskController
{
    public function __construct(
        private ProjectTaskRepository $tasks,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '',
        name: 'api_project_tasks_list',
        methods: ['GET'],
    )]
    public function list(
        int $projectId,
    ): JsonResponse {
        return new JsonResponse([
            'tasks' =>
                $this->tasks->all(
                    $projectId,
                ),
        ]);
    }

    #[Route(
        '',
        name: 'api_project_tasks_create',
        methods: ['POST'],
    )]
    public function create(
        int $projectId,
        Request $request,
    ): JsonResponse {
        $input =
            ProjectTaskCreateInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->tasks->create(
                $projectId,
                $input->title,
                $input->description,
                $input->priority,
                $input->status,
                $input->workflowStageId,
                $input->position,
                $input->tagIds,
                $input->startDate,
                $input->dueDate,
            ),
            201,
        );
    }

    #[Route(
        '/{taskId<\d+>}',
        name: 'api_project_tasks_get',
        methods: ['GET'],
    )]
    public function get(
        int $projectId,
        int $taskId,
    ): JsonResponse {
        return new JsonResponse(
            $this->tasks->get(
                $projectId,
                $taskId,
            ),
        );
    }

    #[Route(
        '/{taskId<\d+>}',
        name: 'api_project_tasks_update',
        methods: ['PUT'],
    )]
    public function update(
        int $projectId,
        int $taskId,
        Request $request,
    ): JsonResponse {
        $input =
            ProjectTaskUpdateInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        $current =
            $this->tasks->get(
                $projectId,
                $taskId,
            );

        return new JsonResponse(
            $this->tasks->update(
                $projectId,
                $taskId,

                $input->title
                    ?? (string) (
                        $current['title']
                        ?? $current['content']
                    ),

                $input->description
                    ?? (string) (
                        $current[
                            'description'
                        ] ?? ''
                    ),

                $input->priority
                    ?? (string) $current[
                        'priority'
                    ],

                $input->status
                    ?? (string) $current[
                        'status'
                    ],

                $input->workflowStageProvided
                    ? (int) $input
                        ->workflowStageId
                    : (int) $current[
                        'workflowStageId'
                    ],

                $input->positionProvided
                    ? $input->position
                    : null,

                $input->tagIds
                    ?? $this->currentTagIds(
                        $current,
                    ),

                $input->startDateProvided
                    ? $input->startDate
                    : (
                        $current['startDate']
                        !== null
                            ? (string) $current[
                                'startDate'
                            ]
                            : null
                    ),

                $input->dueDateProvided
                    ? $input->dueDate
                    : (
                        $current['dueDate']
                        !== null
                            ? (string) $current[
                                'dueDate'
                            ]
                            : null
                    ),
            ),
        );
    }

    #[Route(
        '/{taskId<\d+>}/completed',
        name: 'api_project_tasks_completed',
        methods: ['PUT'],
    )]
    public function completed(
        int $projectId,
        int $taskId,
        Request $request,
    ): JsonResponse {
        $input =
            TaskCompletionInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->tasks->setCompleted(
                $projectId,
                $taskId,
                $input->completed,
            ),
        );
    }

    #[Route(
        '/{taskId<\d+>}',
        name: 'api_project_tasks_delete',
        methods: ['DELETE'],
    )]
    public function delete(
        int $projectId,
        int $taskId,
    ): JsonResponse {
        $this->tasks->delete(
            $projectId,
            $taskId,
        );

        return new JsonResponse(
            null,
            204,
        );
    }

    #[Route(
        '/order',
        name: 'api_project_tasks_order',
        methods: ['PUT'],
    )]
    public function reorder(
        int $projectId,
        Request $request,
    ): JsonResponse {
        $input =
            ProjectTaskOrderInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse([
            'tasks' =>
                $this->tasks->reorder(
                    $projectId,
                    $input->columns,
                ),
        ]);
    }

    /**
     * @param array<string, mixed> $task
     *
     * @return list<int>
     */
    private function currentTagIds(
        array $task,
    ): array {
        $tags =
            $task['tags']
            ?? [];

        if (!is_array($tags)) {
            return [];
        }

        $ids = [];

        foreach ($tags as $tag) {
            if (
                is_array($tag)
                && isset($tag['id'])
            ) {
                $ids[] =
                    (int) $tag['id'];
            }
        }

        return $ids;
    }
}
