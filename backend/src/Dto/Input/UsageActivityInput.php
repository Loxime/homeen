<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class UsageActivityInput
{
    public function __construct(
        public int $activeSeconds,
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
                'activeSeconds',
                0,
                'INVALID_USAGE_INPUT',
            ),
        );
    }
}
