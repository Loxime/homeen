<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChannelNoteRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/channels/{code<\d{9}>}/notes')]
final readonly class ChannelNoteController
{
    public function __construct(
        private ChannelNoteRepository $notes,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '',
        name: 'api_channel_notes_list',
        methods: ['GET'],
    )]
    public function list(
        string $code,
        Request $request,
    ): JsonResponse {
        try {
            return new JsonResponse([
                'notes' =>
                    $this->notes->list(
                        $code,
                        (string) $request
                            ->query
                            ->get(
                                'scope',
                                'active',
                            ),
                        $request
                            ->query
                            ->getString('q') !== ''
                                ? $request
                                    ->query
                                    ->getString('q')
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
        '/{noteId<\d+>}',
        name: 'api_channel_notes_get',
        methods: ['GET'],
    )]
    public function get(
        string $code,
        int $noteId,
    ): JsonResponse {
        try {
            return new JsonResponse(
                $this->notes->get(
                    $code,
                    $noteId,
                ),
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
            );
        }
    }

    #[Route(
        '',
        name: 'api_channel_notes_create',
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
                $this->notes->create(
                    $code,
                    (string) (
                        $data['title']
                        ?? ''
                    ),
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
        '/{noteId<\d+>}',
        name: 'api_channel_notes_update',
        methods: ['PUT'],
    )]
    public function update(
        string $code,
        int $noteId,
        Request $request,
    ): JsonResponse {
        $data =
            $this->input->read(
                $request
            );

        $version =
            isset($data['version'])
                ? (int) $data[
                    'version'
                ]
                : 0;

        try {
            return new JsonResponse(
                $this->notes->update(
                    $code,
                    $noteId,
                    (string) (
                        $data['title']
                        ?? ''
                    ),
                    (string) (
                        $data['content']
                        ?? ''
                    ),
                    $version,
                ),
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
            );
        }
    }

    #[Route(
        '/{noteId<\d+>}/duplicate',
        name: 'api_channel_notes_duplicate',
        methods: ['POST'],
    )]
    public function duplicate(
        string $code,
        int $noteId,
    ): JsonResponse {
        try {
            return new JsonResponse(
                $this->notes->duplicate(
                    $code,
                    $noteId,
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
        '/{noteId<\d+>}/archive',
        name: 'api_channel_notes_archive',
        methods: ['POST'],
    )]
    public function archive(
        string $code,
        int $noteId,
    ): JsonResponse {
        try {
            return new JsonResponse(
                $this->notes->archive(
                    $code,
                    $noteId,
                    true,
                ),
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
            );
        }
    }

    #[Route(
        '/{noteId<\d+>}/unarchive',
        name: 'api_channel_notes_unarchive',
        methods: ['POST'],
    )]
    public function unarchive(
        string $code,
        int $noteId,
    ): JsonResponse {
        try {
            return new JsonResponse(
                $this->notes->archive(
                    $code,
                    $noteId,
                    false,
                ),
            );
        } catch (\Throwable $exception) {
            return $this->error(
                $exception
            );
        }
    }

    #[Route(
        '/{noteId<\d+>}',
        name: 'api_channel_notes_trash',
        methods: ['DELETE'],
    )]
    public function trash(
        string $code,
        int $noteId,
    ): JsonResponse {
        try {
            $this->notes->trash(
                $code,
                $noteId,
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

    #[Route(
        '/{noteId<\d+>}/restore',
        name: 'api_channel_notes_restore',
        methods: ['POST'],
    )]
    public function restore(
        string $code,
        int $noteId,
    ): JsonResponse {
        try {
            return new JsonResponse(
                $this->notes->restore(
                    $code,
                    $noteId,
                ),
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
            instanceof \DomainException
            && $exception->getMessage()
                === 'NOTE_VERSION_CONFLICT'
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        'This note has been modified by another user.',
                    'code' =>
                        'NOTE_VERSION_CONFLICT',
                ],
                409,
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
                        'CHANNEL_NOTE_NOT_FOUND',
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
                        'INVALID_CHANNEL_NOTE',
                ],
                422,
            );
        }

        throw $exception;
    }
}
