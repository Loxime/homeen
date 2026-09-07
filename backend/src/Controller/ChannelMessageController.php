<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelMessageRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/channels/{code<\d{9}>}/messages'
)]
final readonly class ChannelMessageController
{
    public function __construct(
        private ChannelMessageRepository $messages,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '',
        name: 'api_channel_messages_list',
        methods: ['GET'],
    )]
    public function list(
        string $code,
        Request $request,
    ): JsonResponse {
        $after =
            $request->query
                ->getInt(
                    'after',
                    0,
                );

        try {
            return new JsonResponse([
                'messages' =>
                    $this->messages->list(
                        $code,
                        $after > 0
                            ? $after
                            : null,
                    ),
            ]);
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
            );
        }
    }

    #[Route(
        '',
        name: 'api_channel_messages_create',
        methods: ['POST'],
    )]
    public function create(
        string $code,
        Request $request,
    ): JsonResponse {
        $data =
            $this->input->read(
                $request
            );

        try {
            return new JsonResponse(
                $this->messages->create(
                    $code,
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
        '/read',
        name: 'api_channel_messages_read',
        methods: ['POST'],
    )]
    public function read(
        string $code,
    ): JsonResponse {
        try {
            $this->messages
                ->markRead(
                    $code
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
                        'CHANNEL_NOT_FOUND',
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
                        'INVALID_CHANNEL_MESSAGE',
                ],
                422,
            );
        }

        throw $exception;
    }
}
