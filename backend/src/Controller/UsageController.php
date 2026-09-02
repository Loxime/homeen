<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UsageRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/usage')]
final readonly class UsageController
{
    public function __construct(private UsageRepository $usage, private JsonInput $input)
    {
    }

    #[Route('/sessions', name: 'api_usage_start', methods: ['POST'])]
    public function start(): JsonResponse
    {
        return new JsonResponse($this->usage->start(), 201);
    }

    #[Route('/sessions/{id<\\d+>}/heartbeat', name: 'api_usage_heartbeat', methods: ['POST'])]
    public function heartbeat(int $id, Request $request): JsonResponse
    {
        $data = $this->input->read($request);
        $this->usage->heartbeat($id, (int) ($data['activeSeconds'] ?? 0));
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/sessions/{id<\\d+>}/stop', name: 'api_usage_stop', methods: ['POST'])]
    public function stop(int $id, Request $request): JsonResponse
    {
        $data = $this->input->read($request);
        $this->usage->stop($id, (int) ($data['activeSeconds'] ?? 0));
        return new JsonResponse(['ok' => true]);
    }
}
