<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\NoteCreateInput;
use App\Dto\Input\NoteUpdateInput;
use App\Repository\NoteRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notes')]
final readonly class NoteController
{
    public function __construct(
        private NoteRepository $notes,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '',
        name: 'api_notes_list',
        methods: ['GET'],
    )]
    public function list(
        Request $request,
    ): JsonResponse {
        $projectId =
            $request->query->has(
                'projectId',
            )
                ? $request->query->getInt(
                    'projectId',
                )
                : null;

        return new JsonResponse([
            'notes' =>
                $this->notes->list(
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
                    $projectId !== null
                    && $projectId > 0
                        ? $projectId
                        : null,
                ),
        ]);
    }

    #[Route(
        '/{id<\d+>}',
        name: 'api_notes_get',
        methods: ['GET'],
    )]
    public function get(
        int $id,
    ): JsonResponse {
        return new JsonResponse(
            $this->notes->get(
                $id,
            ),
        );
    }

    #[Route(
        '',
        name: 'api_notes_create',
        methods: ['POST'],
    )]
    public function create(
        Request $request,
    ): JsonResponse {
        $input =
            NoteCreateInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->notes->create(
                $input->title,
                $input->content,
                $input->noteType,
                $input->tagIds,
                $input->projectId,
                $input->isPinned,
                $input->color,
            ),
            201,
        );
    }

    #[Route(
        '/{id<\d+>}',
        name: 'api_notes_update',
        methods: ['PUT'],
    )]
    public function update(
        int $id,
        Request $request,
    ): JsonResponse {
        $input =
            NoteUpdateInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->notes->update(
                $id,
                $input->title,
                $input->content,
                $input->tagIds,
                $input->projectId,
                $input->projectProvided,
                $input->isPinned,
                $input->color,
            ),
        );
    }

    #[Route(
        '/{id<\d+>}/duplicate',
        name: 'api_notes_duplicate',
        methods: ['POST'],
    )]
    public function duplicate(
        int $id,
    ): JsonResponse {
        return new JsonResponse(
            $this->notes->duplicate(
                $id,
            ),
            201,
        );
    }

    #[Route(
        '/{id<\d+>}/archive',
        name: 'api_notes_archive',
        methods: ['POST'],
    )]
    public function archive(
        int $id,
    ): JsonResponse {
        return new JsonResponse(
            $this->notes->archive(
                $id,
                true,
            ),
        );
    }

    #[Route(
        '/{id<\d+>}/unarchive',
        name: 'api_notes_unarchive',
        methods: ['POST'],
    )]
    public function unarchive(
        int $id,
    ): JsonResponse {
        return new JsonResponse(
            $this->notes->archive(
                $id,
                false,
            ),
        );
    }

    #[Route(
        '/{id<\d+>}',
        name: 'api_notes_trash',
        methods: ['DELETE'],
    )]
    public function trash(
        int $id,
    ): JsonResponse {
        $this->notes->trash(
            $id,
        );

        return new JsonResponse(
            null,
            204,
        );
    }

    #[Route(
        '/{id<\d+>}/restore',
        name: 'api_notes_restore',
        methods: ['POST'],
    )]
    public function restore(
        int $id,
    ): JsonResponse {
        return new JsonResponse(
            $this->notes->restore(
                $id,
            ),
        );
    }
}
