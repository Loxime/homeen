<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Exception\ValidationException;

final readonly class ProjectUpdateInput
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?string $color,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data,
    ): self {
        return new self(
            self::optionalString(
                $data,
                'name',
            ),
            self::optionalString(
                $data,
                'description',
            ),
            self::optionalString(
                $data,
                'color',
            ),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function optionalString(
        array $data,
        string $field,
    ): ?string {
        if (
            !array_key_exists(
                $field,
                $data,
            )
        ) {
            return null;
        }

        $value = $data[$field];

        if (!is_string($value)) {
            throw new ValidationException(
                $field.' must be a string.',
                'INVALID_PROJECT_INPUT',
            );
        }

        return $value;
    }
}
