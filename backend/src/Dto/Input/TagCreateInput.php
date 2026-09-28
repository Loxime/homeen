<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class TagCreateInput
{
    public function __construct(
        public string $name,
        public string $color,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data,
    ): self {
        return new self(
            InputValue::string(
                $data,
                'name',
                '',
                'INVALID_TAG_INPUT',
            ),
            InputValue::string(
                $data,
                'color',
                '',
                'INVALID_TAG_INPUT',
            ),
        );
    }
}
