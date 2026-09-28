<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Exception\ValidationException;

final readonly class ProjectCreateInput
{
    public function __construct(
        public string $name,
        public string $description,
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
            self::stringValue(
                $data,
                'name',
                '',
            ),
            self::stringValue(
                $data,
                'description',
                '',
            ),
            self::stringValue(
                $data,
                'color',
                '#1A73E8',
            ),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function stringValue(
        array $data,
        string $field,
        string $default,
    ): string {
        if (
            !array_key_exists(
                $field,
                $data,
            )
        ) {
            return $default;
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
