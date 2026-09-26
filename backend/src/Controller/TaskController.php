<?php

declare(strict_types=1);

namespace App\Controller;

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
        $data = $this->input->read($request);

        return new JsonResponse(
            $this->tasks->create(
                $noteId,
                (string) ($data['content'] ?? ''),
                (string) ($data['priority'] ?? 'normal'),
                (string) ($data['status'] ?? 'todo'),
                isset($data['position'])
                    ? (int) $data['position']
                    : null,
                $this->tagIds(
                    $data['tagIds'] ?? [],
                ),
            ),
            201,
        );
    }

    #[Route(
        '/api/tasks/{id<\d+>}',
        name: 'api_tasks_get',
        methods: ['GET'],
    )]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse(
            $this->tasks->get($id),
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
        $data = $this->input->read($request);

        $current = $this->tasks->get($id);

        return new JsonResponse(
            $this->tasks->update(
                $id,
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
                isset($data['position'])
                    ? (int) $data['position']
                    : (int) $current['position'],
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
        $data = $this->input->read($request);

        return new JsonResponse(
            $this->tasks->setCompleted(
                $id,
                (bool) (
                    $data['completed']
                    ?? false
                ),
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
        $this->tasks->delete($id);

        return new JsonResponse(
            null,
            204,
        );
    }

    /**
     * @return list<int>
     */
    private function tagIds(mixed $value): array
    {
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

            $ids[] = (int) $item;
        }

        return array_values(
            array_unique($ids),
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
        $tags = $task['tags'] ?? [];

        if (!is_array($tags)) {
            return [];
        }

        $ids = [];

        foreach ($tags as $tag) {
            if (
                is_array($tag)
                && isset($tag['id'])
            ) {
                $ids[] = (int) $tag['id'];
            }
        }

        return $ids;
    }
}
