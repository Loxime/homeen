<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PomodoroRepository;
use App\Repository\StatisticsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class StatisticsController
{
    public function __construct(
        private StatisticsRepository $statistics,
        private PomodoroRepository $pomodoro,
        #[Autowire('%app.timezone%')] private string $timezone,
    ) {
    }

    #[Route('/api/statistics', name: 'api_statistics', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        // Refresh live Pomodoro metrics before aggregating the current month.
        $this->pomodoro->active();
        $month = $request->query->getString('month');
        if ($month === '') {
            $month = (new \DateTimeImmutable('now', new \DateTimeZone($this->timezone)))->format('Y-m');
        }

        return new JsonResponse($this->statistics->month($month));
    }
}
