<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelTaskRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ChannelTaskOverviewController
{
    public function __construct(
        private ChannelTaskRepository $tasks,
    ) {
    }

    #[Route(
        '/api/channels/{code<\d{9}>}/tasks',
        name: 'api_channel_tasks_list',
        methods: ['GET'],
    )]
    public function list(
        string $code,
    ): JsonResponse {
        try {
            return new JsonResponse([
                'tasks' =>
                    $this->tasks->list(
                        $code,
                    ),
            ]);
        } catch (
            \DomainException $exception
        ) {
            if (
                $exception->getMessage()
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

            throw $exception;
        } catch (
            \OutOfBoundsException $exception
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        $exception
                            ->getMessage(),

                    'code' =>
                        'CHANNEL_NOT_FOUND',
                ],
                404,
            );
        }
    }
}
