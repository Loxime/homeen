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
    public function __construct(private TaskRepository $tasks, private JsonInput $input)
    {
    }

    #[Route('/api/notes/{noteId<\\d+>}/tasks', name: 'api_tasks_create', methods: ['POST'])]
    public function create(int $noteId, Request $request): JsonResponse
    {
        $data = $this->input->read($request);
        return new JsonResponse($this->tasks->create($noteId, (string) ($data['content'] ?? '')), 201);
    }

    #[Route('/api/tasks/{id<\\d+>}/completed', name: 'api_tasks_completed', methods: ['PUT'])]
    public function completed(int $id, Request $request): JsonResponse
    {
        $data = $this->input->read($request);
        return new JsonResponse($this->tasks->setCompleted($id, (bool) ($data['completed'] ?? false)));
    }

    #[Route('/api/tasks/{id<\\d+>}', name: 'api_tasks_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->tasks->delete($id);
        return new JsonResponse(null, 204);
    }
}
