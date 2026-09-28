<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\TagCreateInput;
use App\Dto\Input\TagUpdateInput;
use App\Repository\TagRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tags')]
final readonly class TagController
{
    public function __construct(
        private TagRepository $tags,
        private JsonInput $input,
    ) {
    }

    #[Route(
        '',
        name: 'api_tags_list',
        methods: ['GET'],
    )]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'tags' =>
                $this->tags->all(),
        ]);
    }

    #[Route(
        '',
        name: 'api_tags_create',
        methods: ['POST'],
    )]
    public function create(
        Request $request,
    ): JsonResponse {
        $input =
            TagCreateInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->tags->create(
                $input->name,
                $input->color,
            ),
            201,
        );
    }

    #[Route(
        '/{id<\d+>}',
        name: 'api_tags_update',
        methods: ['PUT'],
    )]
    public function update(
        int $id,
        Request $request,
    ): JsonResponse {
        $input =
            TagUpdateInput::fromArray(
                $this->input->read(
                    $request,
                ),
            );

        return new JsonResponse(
            $this->tags->update(
                $id,
                $input->name,
                $input->color,
            ),
        );
    }

    #[Route(
        '/{id<\d+>}',
        name: 'api_tags_delete',
        methods: ['DELETE'],
    )]
    public function delete(
        int $id,
    ): JsonResponse {
        $this->tags->delete(
            $id,
        );

        return new JsonResponse(
            null,
            204,
        );
    }
}
