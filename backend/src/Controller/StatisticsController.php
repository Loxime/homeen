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
        #[Autowire('%app.timezone%')]
        private string $timezone,
    ) {
    }

    #[Route(
        '/api/statistics',
        name: 'api_statistics',
        methods: ['GET'],
    )]
    public function __invoke(
        Request $request,
    ): JsonResponse {
        /*
         * Refresh live Pomodoro metrics before
         * aggregating statistics.
         */
        $this->pomodoro->active();

        $start =
            $request->query
                ->getString('start');

        $end =
            $request->query
                ->getString('end');

        if (
            $start !== ''
            || $end !== ''
        ) {
            if (
                $start === ''
                || $end === ''
            ) {
                throw new \InvalidArgumentException(
                    'Both start and end dates are required.'
                );
            }

            $compareStart =
                $request->query
                    ->getString(
                        'compareStart'
                    );

            $compareEnd =
                $request->query
                    ->getString(
                        'compareEnd'
                    );

            return new JsonResponse(
                $this->statistics
                    ->period(
                        $start,
                        $end,
                        $compareStart !== ''
                            ? $compareStart
                            : null,
                        $compareEnd !== ''
                            ? $compareEnd
                            : null,
                    )
            );
        }

        $month =
            $request->query
                ->getString('month');

        if ($month === '') {
            $month =
                (
                    new \DateTimeImmutable(
                        'now',
                        new \DateTimeZone(
                            $this->timezone
                        ),
                    )
                )->format('Y-m');
        }

        return new JsonResponse(
            $this->statistics
                ->month($month)
        );
    }
}
