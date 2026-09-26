<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\NoteRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notes')]
final readonly class NoteController
{
    public function __construct(private NoteRepository $notes, private JsonInput $input)
    {
    }

    #[Route('', name: 'api_notes_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $collectionId =
            $request->query->has('collectionId')
                ? $request->query->getInt(
                    'collectionId'
                )
                : null;

        return new JsonResponse([
            'notes' => $this->notes->list(
                (string) $request->query->get(
                    'scope',
                    'active',
                ),
                $request->query->getString('q') !== ''
                    ? $request->query->getString('q')
                    : null,
                $collectionId !== null
                && $collectionId > 0
                    ? $collectionId
                    : null,
            ),
        ]);
    }

    #[Route('/{id<\\d+>}', name: 'api_notes_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->notes->get($id));
    }

    #[Route('', name: 'api_notes_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->input->read($request);
        return new JsonResponse(
            $this->notes->create(
                (string) ($data['title'] ?? ''),
                (string) ($data['content'] ?? ''),
                $this->tagIds(
                    $data['tagIds']
                    ?? [],
                ),
                isset($data['collectionId'])
                    ? (int) $data['collectionId']
                    : null,

                $this->booleanValue(
                    $data,
                    'isPinned',
                ) ?? false,

                (string) (
                    $data['color']
                    ?? '#FFFFFF'
                ),
            ),
            201,
        );
    }

    #[Route('/{id<\\d+>}', name: 'api_notes_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = $this->input->read($request);
        return new JsonResponse(
            $this->notes->update(
                $id,
                (string) ($data['title'] ?? ''),
                (string) ($data['content'] ?? ''),
                $this->tagIds(
                    $data['tagIds']
                    ?? [],
                ),
                isset($data['collectionId'])
                    ? (int) $data['collectionId']
                    : null,

                $this->booleanValue(
                    $data,
                    'isPinned',
                ),

                isset($data['color'])
                    ? (string) $data['color']
                    : null,
            ),
        );
    }

    #[Route('/{id<\\d+>}/duplicate', name: 'api_notes_duplicate', methods: ['POST'])]
    public function duplicate(int $id): JsonResponse
    {
        return new JsonResponse($this->notes->duplicate($id), 201);
    }

    #[Route('/{id<\\d+>}/archive', name: 'api_notes_archive', methods: ['POST'])]
    public function archive(int $id): JsonResponse
    {
        return new JsonResponse($this->notes->archive($id, true));
    }

    #[Route('/{id<\\d+>}/unarchive', name: 'api_notes_unarchive', methods: ['POST'])]
    public function unarchive(int $id): JsonResponse
    {
        return new JsonResponse($this->notes->archive($id, false));
    }

    #[Route('/{id<\\d+>}', name: 'api_notes_trash', methods: ['DELETE'])]
    public function trash(int $id): JsonResponse
    {
        $this->notes->trash($id);
        return new JsonResponse(null, 204);
    }

    #[Route('/{id<\\d+>}/restore', name: 'api_notes_restore', methods: ['POST'])]
    public function restore(int $id): JsonResponse
    {
        return new JsonResponse($this->notes->restore($id));
    }
    /**
     * @param array<string, mixed> $data
     */
    private function booleanValue(
        array $data,
        string $field,
    ): ?bool {
        if (
            !array_key_exists(
                $field,
                $data,
            )
        ) {
            return null;
        }

        if (!is_bool($data[$field])) {
            throw new \InvalidArgumentException(
                $field.' must be a boolean.'
            );
        }

        return $data[$field];
    }

    /**
     * @return list<int>
     */
    private function tagIds(
        mixed $value,
    ): array {
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

}
