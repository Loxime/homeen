<?php

declare(strict_types=1);

namespace App\Controller;

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
        $data =
            $this->input->read(
                $request
            );

        return new JsonResponse(
            $this->tasks->create(
                $projectId,
                (string) (
                    $data['content']
                    ?? ''
                ),
                (string) (
                    $data['priority']
                    ?? 'normal'
                ),
                (string) (
                    $data['status']
                    ?? 'todo'
                ),
                array_key_exists(
                    'workflowStageId',
                    $data,
                )
                    ? $this->positiveInt(
                        $data[
                            'workflowStageId'
                        ],
                        'workflowStageId',
                    )
                    : null,
                array_key_exists(
                    'position',
                    $data,
                )
                    ? $this->position(
                        $data['position'],
                    )
                    : null,
                $this->tagIds(
                    $data['tagIds']
                    ?? [],
                ),
                $this->dateValue(
                    $data['startDate']
                    ?? null,
                ),
                $this->dateValue(
                    $data['dueDate']
                    ?? null,
                ),
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
        $data =
            $this->input->read(
                $request
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
                (string) (
                    $data['content']
                    ?? $current['content']
                ),
                (string) (
                    $data['priority']
                    ?? $current['priority']
                ),
                (string) (
                    $data['status']
                    ?? $current['status']
                ),
                array_key_exists(
                    'workflowStageId',
                    $data,
                )
                    ? $this->positiveInt(
                        $data[
                            'workflowStageId'
                        ],
                        'workflowStageId',
                    )
                    : (int) $current[
                        'workflowStageId'
                    ],
                array_key_exists(
                    'position',
                    $data,
                )
                    ? $this->position(
                        $data['position'],
                    )
                    : null,
                array_key_exists(
                    'tagIds',
                    $data,
                )
                    ? $this->tagIds(
                        $data['tagIds'],
                    )
                    : $this->currentTagIds(
                        $current,
                    ),
                array_key_exists(
                    'startDate',
                    $data,
                )
                    ? $this->dateValue(
                        $data['startDate'],
                    )
                    : (
                        $current['startDate']
                        !== null
                            ? (string) $current[
                                'startDate'
                            ]
                            : null
                    ),
                array_key_exists(
                    'dueDate',
                    $data,
                )
                    ? $this->dateValue(
                        $data['dueDate'],
                    )
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
        $data =
            $this->input->read(
                $request
            );

        return new JsonResponse(
            $this->tasks
                ->setCompleted(
                    $projectId,
                    $taskId,
                    (bool) (
                        $data['completed']
                        ?? false
                    ),
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

    private function positiveInt(
        mixed $value,
        string $field,
    ): int {
        if (
            is_int($value)
            && $value > 0
        ) {
            return $value;
        }

        if (
            is_string($value)
            && ctype_digit($value)
            && (int) $value > 0
        ) {
            return (int) $value;
        }

        throw new \InvalidArgumentException(
            $field
            .' must be a positive integer.'
        );
    }

    private function position(
        mixed $value,
    ): int {
        if (
            is_int($value)
            && $value >= 0
        ) {
            return $value;
        }

        if (
            is_string($value)
            && ctype_digit($value)
        ) {
            return (int) $value;
        }

        throw new \InvalidArgumentException(
            'position must be a non-negative integer.'
        );
    }

    private function dateValue(
        mixed $value,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException(
                'Task dates must be strings or null.'
            );
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    /**
     * @return list<int>
     */
    private function tagIds(
        mixed $value,
    ): array {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(
                'tagIds must be an array.'
            );
        }

        $ids = [];

        foreach ($value as $item) {
            if (
                !is_int($item)
                && !(
                    is_string($item)
                    && ctype_digit($item)
                )
            ) {
                throw new \InvalidArgumentException(
                    'Tag identifiers must be integers.'
                );
            }

            $ids[] =
                (int) $item;
        }

        return array_values(
            array_unique(
                $ids
            ),
        );
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
