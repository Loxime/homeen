<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Exception\ValidationException;

final class InputValue
{
    /**
     * @param array<string, mixed> $data
     */
    public static function string(
        array $data,
        string $field,
        string $default,
        string $errorCode,
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
                $errorCode,
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function optionalString(
        array $data,
        string $field,
        string $errorCode,
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
                $errorCode,
            );
        }

        return $value;
    }

    public static function optionalNonNegativeInt(
        mixed $value,
        string $field,
        string $errorCode,
    ): ?int {
        if ($value === null) {
            return null;
        }

        if (
            !is_int($value)
            || $value < 0
        ) {
            throw new ValidationException(
                $field
                .' must be a non-negative integer.',
                $errorCode,
            );
        }

        return $value;
    }

    public static function nullableDate(
        mixed $value,
        string $field,
        string $errorCode,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new ValidationException(
                $field
                .' must be a string or null.',
                $errorCode,
            );
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    /**
     * @return list<int>
     */
    public static function integerList(
        mixed $value,
        string $field,
        string $errorCode,
    ): array {
        if (!is_array($value)) {
            throw new ValidationException(
                $field.' must be an array.',
                $errorCode,
            );
        }

        $values = [];

        foreach ($value as $item) {
            if (
                !is_int($item)
                || $item <= 0
            ) {
                throw new ValidationException(
                    $field
                    .' must contain positive integers.',
                    $errorCode,
                );
            }

            $values[] = $item;
        }

        return array_values(
            array_unique(
                $values,
            ),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function boolean(
        array $data,
        string $field,
        bool $default,
        string $errorCode,
    ): bool {
        if (
            !array_key_exists(
                $field,
                $data,
            )
        ) {
            return $default;
        }

        $value = $data[$field];

        if (!is_bool($value)) {
            throw new ValidationException(
                $field.' must be a boolean.',
                $errorCode,
            );
        }

        return $value;
    }
}
