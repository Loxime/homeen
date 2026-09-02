<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\LabelRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/labels')]
final readonly class LabelController
{
    public function __construct(private LabelRepository $labels, private JsonInput $input)
    {
    }

    #[Route('', name: 'api_labels_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(['labels' => $this->labels->all()]);
    }

    #[Route('', name: 'api_labels_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->input->read($request);
        return new JsonResponse($this->labels->create((string) ($data['name'] ?? ''), (string) ($data['color'] ?? '')), 201);
    }

    #[Route('/{id<\\d+>}', name: 'api_labels_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = $this->input->read($request);
        return new JsonResponse($this->labels->update($id, (string) ($data['name'] ?? ''), (string) ($data['color'] ?? '')));
    }

    #[Route('/{id<\\d+>}', name: 'api_labels_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->labels->delete($id);
        return new JsonResponse(null, 204);
    }
}
