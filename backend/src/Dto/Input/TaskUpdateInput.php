<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Exception\ValidationException;

final readonly class TaskUpdateInput
{
    /**
     * @param list<int>|null $tagIds
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $priority,
        public ?string $status,
        public ?int $position,
        public ?array $tagIds,
        public bool $startDateProvided,
        public ?string $startDate,
        public bool $dueDateProvided,
        public ?string $dueDate,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data,
    ): self {
        $tagIds = null;

        if (
            array_key_exists(
                'tagIds',
                $data,
            )
        ) {
            $tagIds =
                InputValue::integerList(
                    $data['tagIds'],
                    'tagIds',
                    'INVALID_TASK_INPUT',
                );

            if ($tagIds !== []) {
                throw new ValidationException(
                    'Tags cannot be added to note tasks.',
                    'TASK_TAGS_NOT_ALLOWED',
                );
            }
        }

        $startDateProvided =
            array_key_exists(
                'startDate',
                $data,
            );

        $dueDateProvided =
            array_key_exists(
                'dueDate',
                $data,
            );

        return new self(
            self::title(
                $data,
            ),
            InputValue::optionalString(
                $data,
                'description',
                'INVALID_TASK_INPUT',
            ),
            InputValue::optionalString(
                $data,
                'priority',
                'INVALID_TASK_INPUT',
            ),
            InputValue::optionalString(
                $data,
                'status',
                'INVALID_TASK_INPUT',
            ),
            array_key_exists(
                'position',
                $data,
            )
                ? InputValue::optionalNonNegativeInt(
                    $data['position'],
                    'position',
                    'INVALID_TASK_INPUT',
                )
                : null,
            $tagIds,
            $startDateProvided,
            $startDateProvided
                ? InputValue::nullableDate(
                    $data['startDate'],
                    'startDate',
                    'INVALID_TASK_INPUT',
                )
                : null,
            $dueDateProvided,
            $dueDateProvided
                ? InputValue::nullableDate(
                    $data['dueDate'],
                    'dueDate',
                    'INVALID_TASK_INPUT',
                )
                : null,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function title(
        array $data,
    ): ?string {
        if (
            array_key_exists(
                'title',
                $data,
            )
        ) {
            return InputValue::optionalString(
                $data,
                'title',
                'INVALID_TASK_INPUT',
            );
        }

        if (
            array_key_exists(
                'content',
                $data,
            )
        ) {
            return InputValue::optionalString(
                $data,
                'content',
                'INVALID_TASK_INPUT',
            );
        }

        return null;
    }
}
