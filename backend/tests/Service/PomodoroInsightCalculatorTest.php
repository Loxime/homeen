<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PomodoroInsightCalculator;
use PHPUnit\Framework\TestCase;

final class PomodoroInsightCalculatorTest
    extends TestCase
{
    public function testGrowthStages(): void
    {
        $calculator =
            new PomodoroInsightCalculator();

        self::assertSame(
            'roots',
            $calculator
                ->calculate(
                    0,
                    [],
                )['stage'],
        );

        self::assertSame(
            'tree',
            $calculator
                ->calculate(
                    60 * 60,
                    [],
                )['stage'],
        );

        self::assertSame(
            'forest',
            $calculator
                ->calculate(
                    4 * 60 * 60,
                    [],
                )['stage'],
        );
    }

    public function testRecommendationNeedsThreeRatings(): void
    {
        $calculator =
            new PomodoroInsightCalculator();

        $result =
            $calculator->calculate(
                0,
                [
                    [
                        'workMinutes' => 25,
                        'rating' => 2,
                    ],
                    [
                        'workMinutes' => 25,
                        'rating' => 3,
                    ],
                ],
            );

        self::assertNull(
            $result[
                'recommendedMinutes'
            ],
        );
    }

    public function testRecommendationAdaptsDuration(): void
    {
        $calculator =
            new PomodoroInsightCalculator();

        $result =
            $calculator->calculate(
                0,
                [
                    [
                        'workMinutes' => 20,
                        'rating' => 3,
                    ],
                    [
                        'workMinutes' => 25,
                        'rating' => 2,
                    ],
                    [
                        'workMinutes' => 30,
                        'rating' => 1,
                    ],
                ],
            );

        self::assertSame(
            25,
            $result[
                'recommendedMinutes'
            ],
        );
    }
}
