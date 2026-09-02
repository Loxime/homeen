<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PomodoroRepository;
use App\Service\JsonInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/pomodoro')]
final readonly class PomodoroController
{
    public function __construct(private PomodoroRepository $pomodoro, private JsonInput $input)
    {
    }

    #[Route('/presets', name: 'api_pomodoro_presets', methods: ['GET'])]
    public function presets(): JsonResponse
    {
        return new JsonResponse(['presets' => $this->pomodoro->presets()]);
    }

    #[Route('/active', name: 'api_pomodoro_active', methods: ['GET'])]
    public function active(): JsonResponse
    {
        return new JsonResponse(['session' => $this->pomodoro->active()]);
    }

    #[Route('/sessions', name: 'api_pomodoro_start', methods: ['POST'])]
    public function start(Request $request): JsonResponse
    {
        $data = $this->input->read($request);
        return new JsonResponse($this->pomodoro->start((int) ($data['workMinutes'] ?? 0)), 201);
    }

    #[Route('/quick-start', name: 'api_pomodoro_quick_start', methods: ['POST'])]
    public function quickStart(): JsonResponse
    {
        $active = $this->pomodoro->active();
        if ($active !== null) {
            return new JsonResponse($active);
        }

        return new JsonResponse($this->pomodoro->quickStart(), 201);
    }

    #[Route('/sessions/{id<\\d+>}/stop', name: 'api_pomodoro_stop', methods: ['POST'])]
    public function stop(int $id): JsonResponse
    {
        return new JsonResponse($this->pomodoro->stop($id));
    }

    #[Route('/history', name: 'api_pomodoro_history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        return new JsonResponse(['sessions' => $this->pomodoro->history($request->query->getInt('limit', 50))]);
    }
}
