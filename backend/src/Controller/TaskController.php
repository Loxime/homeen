<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\TaskCompletionInput;
use App\Dto\Input\TaskCreateInput;
use App\Dto\Input\TaskUpdateInput;
use App\Repository\TaskRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class TaskController
{
    public function __construct(
        private TaskRepository $tasks,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '/api/notes/{noteId<\d+>}/tasks',
        name: 'api_tasks_create',
        methods: ['POST'],
    )]
    public function create(
        int $noteId,
        Request $request,
    ): JsonResponse {
        $input =
            TaskCreateInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->tasks->create(
                $noteId,
                $input->title,
                $input->description,
                $input->priority,
                $input->status,
                $input->position,
                $input->tagIds,
                $input->startDate,
                $input->dueDate,
            ),
            201,
        );
    }

    #[Route(
        '/api/tasks/{id<\d+>}',
        name: 'api_tasks_get',
        methods: ['GET'],
    )]
    public function get(
        int $id,
    ): JsonResponse {
        return new JsonResponse(
            $this->tasks->get(
                $id,
            ),
        );
    }

    #[Route(
        '/api/tasks/{id<\d+>}',
        name: 'api_tasks_update',
        methods: ['PUT'],
    )]
    public function update(
        int $id,
        Request $request,
    ): JsonResponse {
        $input =
            TaskUpdateInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        $current =
            $this->tasks->get(
                $id,
            );

        return new JsonResponse(
            $this->tasks->update(
                $id,
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
                $input->position
                    ?? (int) $current[
                        'position'
                    ],
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
        '/api/tasks/{id<\d+>}/completed',
        name: 'api_tasks_completed',
        methods: ['PUT'],
    )]
    public function completed(
        int $id,
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
                $id,
                $input->completed,
            ),
        );
    }

    #[Route(
        '/api/tasks/{id<\d+>}',
        name: 'api_tasks_delete',
        methods: ['DELETE'],
    )]
    public function delete(
        int $id,
    ): JsonResponse {
        $this->tasks->delete(
            $id,
        );

        return new JsonResponse(
            null,
            204,
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
