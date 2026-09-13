<?php

declare(strict_types=1);

namespace App\Service;

final readonly class PomodoroInsightCalculator
{
    /**
     * @param list<array{
     *     workMinutes:int,
     *     rating:int
     * }> $ratedSessions
     *
     * @return array{
     *     totalFocusSeconds:int,
     *     totalFocusMinutes:int,
     *     stage:string,
     *     stageLabel:string,
     *     progressPercent:int,
     *     nextStageMinutes:int|null,
     *     recommendedMinutes:int|null,
     *     ratingCount:int
     * }
     */
    public function calculate(
        int $totalFocusSeconds,
        array $ratedSessions,
    ): array {
        $totalFocusSeconds =
            max(
                0,
                $totalFocusSeconds,
            );

        $totalMinutes =
            intdiv(
                $totalFocusSeconds,
                60,
            );

        [
            $stage,
            $stageLabel,
            $stageStart,
            $stageEnd,
        ] = $this->stage(
            $totalMinutes
        );

        $progress =
            $stageEnd === null
                ? 100
                : (int) round(
                    (
                        (
                            $totalMinutes
                            - $stageStart
                        )
                        / max(
                            1,
                            $stageEnd
                            - $stageStart,
                        )
                    ) * 100,
                );

        $progress =
            max(
                0,
                min(
                    100,
                    $progress,
                ),
            );

        return [
            'totalFocusSeconds' =>
                $totalFocusSeconds,

            'totalFocusMinutes' =>
                $totalMinutes,

            'stage' =>
                $stage,

            'stageLabel' =>
                $stageLabel,

            'progressPercent' =>
                $progress,

            'nextStageMinutes' =>
                $stageEnd,

            'recommendedMinutes' =>
                $this->recommend(
                    $ratedSessions
                ),

            'ratingCount' =>
                count(
                    $ratedSessions
                ),
        ];
    }

    /**
     * @return array{
     *     0:string,
     *     1:string,
     *     2:int,
     *     3:int|null
     * }
     */
    private function stage(
        int $minutes,
    ): array {
        if ($minutes < 15) {
            return [
                'roots',
                'Racines',
                0,
                15,
            ];
        }

        if ($minutes < 30) {
            return [
                'sprout',
                'Pousse',
                15,
                30,
            ];
        }

        if ($minutes < 60) {
            return [
                'sapling',
                'Jeune arbre',
                30,
                60,
            ];
        }

        if ($minutes < 240) {
            return [
                'tree',
                'Arbre',
                60,
                240,
            ];
        }

        return [
            'forest',
            'Forêt',
            240,
            null,
        ];
    }

    /**
     * @param list<array{
     *     workMinutes:int,
     *     rating:int
     * }> $sessions
     */
    private function recommend(
        array $sessions,
    ): ?int {
        if (count($sessions) < 3) {
            return null;
        }

        $targets = [];

        foreach ($sessions as $session) {
            $minutes =
                max(
                    5,
                    $session[
                        'workMinutes'
                    ],
                );

            $factor =
                match (
                    $session['rating']
                ) {
                    1 => 0.80,
                    2 => 1.00,
                    3 => 1.15,
                    default => 1.00,
                };

            $targets[] =
                $minutes
                * $factor;
        }

        $average =
            array_sum($targets)
            / count($targets);

        $rounded =
            (int) (
                round(
                    $average / 5
                ) * 5
            );

        return max(
            5,
            min(
                180,
                $rounded,
            ),
        );
    }
}
