<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\NoteCollectionRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/collections')]
final readonly class NoteCollectionController
{
    public function __construct(
        private NoteCollectionRepository $collections,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '',
        name: 'api_note_collections_list',
        methods: ['GET'],
    )]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'collections' =>
                $this->collections->all(),
        ]);
    }

    #[Route(
        '',
        name: 'api_note_collections_create',
        methods: ['POST'],
    )]
    public function create(
        Request $request,
    ): JsonResponse {
        $data = $this->input->read($request);

        return new JsonResponse(
            $this->collections->create(
                (string) ($data['name'] ?? ''),
                (string) (
                    $data['color']
                    ?? '#1A73E8'
                ),
            ),
            201,
        );
    }

    #[Route(
        '/{id<\d+>}',
        name: 'api_note_collections_update',
        methods: ['PUT'],
    )]
    public function update(
        int $id,
        Request $request,
    ): JsonResponse {
        $data = $this->input->read($request);

        return new JsonResponse(
            $this->collections->update(
                $id,
                (string) ($data['name'] ?? ''),
                (string) (
                    $data['color']
                    ?? '#1A73E8'
                ),
            ),
        );
    }

    #[Route(
        '/{id<\d+>}',
        name: 'api_note_collections_delete',
        methods: ['DELETE'],
    )]
    public function delete(
        int $id,
    ): JsonResponse {
        $this->collections->delete($id);

        return new JsonResponse(
            null,
            204,
        );
    }
}
