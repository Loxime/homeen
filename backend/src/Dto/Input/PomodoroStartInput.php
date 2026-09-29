<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class PomodoroStartInput
{
    public function __construct(
        public int $workMinutes,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data,
    ): self {
        return new self(
            InputValue::integer(
                $data,
                'workMinutes',
                0,
                'INVALID_POMODORO_INPUT',
            ),
        );
    }
}
