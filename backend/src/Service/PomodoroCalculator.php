<?php

declare(strict_types=1);

namespace App\Service;

final class PomodoroCalculator
{
    public const BREAK_MINUTES = 5;

    /** @return array{focusSeconds:int,breakSeconds:int} */
    public function overlap(\DateTimeImmutable $startedAt, int $workMinutes, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($to <= $from) {
            return ['focusSeconds' => 0, 'breakSeconds' => 0];
        }

        $fromState = $this->state($startedAt, $workMinutes, $from);
        $toState = $this->state($startedAt, $workMinutes, $to);

        return [
            'focusSeconds' => max(0, $toState['focusSeconds'] - $fromState['focusSeconds']),
            'breakSeconds' => max(0, $toState['breakSeconds'] - $fromState['breakSeconds']),
        ];
    }

    /**
     * @return array{phase: 'work'|'break', remainingSeconds: int, completedWorkCycles: int, completedBreakCycles: int, focusSeconds: int, breakSeconds: int}
     */
    public function state(\DateTimeImmutable $startedAt, int $workMinutes, ?\DateTimeImmutable $at = null): array
    {
        if ($workMinutes < 5) {
            throw new \InvalidArgumentException('Work duration must be at least 5 minutes.');
        }

        $at ??= new \DateTimeImmutable();
        $elapsed = max(0, $at->getTimestamp() - $startedAt->getTimestamp());
        $workSeconds = $workMinutes * 60;
        $breakSeconds = self::BREAK_MINUTES * 60;
        $cycleSeconds = $workSeconds + $breakSeconds;

        $fullCycles = intdiv($elapsed, $cycleSeconds);
        $withinCycle = $elapsed % $cycleSeconds;

        $focusTotal = $fullCycles * $workSeconds + min($withinCycle, $workSeconds);
        $breakTotal = $fullCycles * $breakSeconds + max(0, $withinCycle - $workSeconds);

        if ($withinCycle < $workSeconds) {
            return [
                'phase' => 'work',
                'remainingSeconds' => $workSeconds - $withinCycle,
                'completedWorkCycles' => $fullCycles,
                'completedBreakCycles' => $fullCycles,
                'focusSeconds' => $focusTotal,
                'breakSeconds' => $breakTotal,
            ];
        }

        return [
            'phase' => 'break',
            'remainingSeconds' => $cycleSeconds - $withinCycle,
            'completedWorkCycles' => $fullCycles + 1,
            'completedBreakCycles' => $fullCycles,
            'focusSeconds' => $focusTotal,
            'breakSeconds' => $breakTotal,
        ];
    }
}
