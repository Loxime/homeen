<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelTaskRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/channels/{code<\d{9}>}/notes/{noteId<\d+>}/tasks'
)]
final readonly class ChannelTaskController
{
    public function __construct(
        private ChannelTaskRepository $tasks,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '',
        name: 'api_channel_tasks_create',
        methods: ['POST'],
    )]
    public function create(
        string $code,
        int $noteId,
        Request $request,
    ): JsonResponse {
        $data =
            $this->input
                ->read($request);

        try {
            return new JsonResponse(
                $this->tasks->create(
                    $code,
                    $noteId,
                    (string) (
                        $data['content']
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
        '/{taskId<\d+>}/completed',
        name: 'api_channel_tasks_completed',
        methods: ['PUT'],
    )]
    public function completed(
        string $code,
        int $noteId,
        int $taskId,
        Request $request,
    ): JsonResponse {
        $data =
            $this->input
                ->read($request);

        try {
            return new JsonResponse(
                $this->tasks
                    ->setCompleted(
                        $code,
                        $noteId,
                        $taskId,
                        (bool) (
                            $data['completed']
                            ?? false
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
        '/{taskId<\d+>}',
        name: 'api_channel_tasks_delete',
        methods: ['DELETE'],
    )]
    public function delete(
        string $code,
        int $noteId,
        int $taskId,
    ): JsonResponse {
        try {
            $this->tasks->delete(
                $code,
                $noteId,
                $taskId,
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
            instanceof \DomainException
            && $exception->getMessage()
                === 'CHANNEL_FORBIDDEN'
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'Channel access denied.',

                    'code' =>
                        'CHANNEL_FORBIDDEN',
                ],
                403,
            );
        }

        if (
            $exception
            instanceof \OutOfBoundsException
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        $exception
                            ->getMessage(),

                    'code' =>
                        'CHANNEL_TASK_NOT_FOUND',
                ],
                404,
            );
        }

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
                        'INVALID_CHANNEL_TASK',
                ],
                422,
            );
        }

        throw $exception;
    }
}
