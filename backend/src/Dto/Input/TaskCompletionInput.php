<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class TaskCompletionInput
{
    public function __construct(
        public bool $completed,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data,
    ): self {
        return new self(
            InputValue::boolean(
                $data,
                'completed',
                false,
                'INVALID_TASK_COMPLETION',
            ),
        );
    }
}
