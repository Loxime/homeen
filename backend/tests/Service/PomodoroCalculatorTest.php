<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PomodoroCalculator;
use PHPUnit\Framework\TestCase;

final class PomodoroCalculatorTest extends TestCase
{
    public function testStartsInWorkPhase(): void
    {
        $calculator = new PomodoroCalculator();
        $start = new \DateTimeImmutable('2026-09-02 10:00:00+00:00');

        $state = $calculator->state($start, 25, $start);

        self::assertSame('work', $state['phase']);
        self::assertSame(1500, $state['remainingSeconds']);
        self::assertSame(0, $state['completedWorkCycles']);
    }

    public function testTransitionsToFixedFiveMinuteBreak(): void
    {
        $calculator = new PomodoroCalculator();
        $start = new \DateTimeImmutable('2026-09-02 10:00:00+00:00');
        $at = $start->modify('+25 minutes');

        $state = $calculator->state($start, 25, $at);

        self::assertSame('break', $state['phase']);
        self::assertSame(300, $state['remainingSeconds']);
        self::assertSame(1, $state['completedWorkCycles']);
        self::assertSame(0, $state['completedBreakCycles']);
    }

    public function testCyclesInfinitelyFromTimestamps(): void
    {
        $calculator = new PomodoroCalculator();
        $start = new \DateTimeImmutable('2026-09-02 10:00:00+00:00');
        $at = $start->modify('+62 minutes');

        $state = $calculator->state($start, 25, $at);

        self::assertSame('work', $state['phase']);
        self::assertSame(1380, $state['remainingSeconds']);
        self::assertSame(2, $state['completedWorkCycles']);
        self::assertSame(2, $state['completedBreakCycles']);
        self::assertSame(3120, $state['focusSeconds']);
        self::assertSame(600, $state['breakSeconds']);
    }

    public function testCalculatesPartialOverlapAcrossCycleBoundary(): void
    {
        $calculator = new PomodoroCalculator();
        $start = new \DateTimeImmutable('2026-09-02 23:50:00+00:00');
        $from = new \DateTimeImmutable('2026-09-03 00:00:00+00:00');
        $to = new \DateTimeImmutable('2026-09-03 00:20:00+00:00');

        $overlap = $calculator->overlap($start, 25, $from, $to);

        self::assertSame(900, $overlap['focusSeconds']);
        self::assertSame(300, $overlap['breakSeconds']);
    }

    public function testRejectsWorkDurationUnderFiveMinutes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PomodoroCalculator())->state(new \DateTimeImmutable(), 4);
    }
}
