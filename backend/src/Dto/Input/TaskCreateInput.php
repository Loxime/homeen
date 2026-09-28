<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Exception\ValidationException;

final readonly class TaskCreateInput
{
    /**
     * @param list<int> $tagIds
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $priority,
        public string $status,
        public ?int $position,
        public array $tagIds,
        public ?string $startDate,
        public ?string $dueDate,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data,
    ): self {
        $title =
            self::title(
                $data,
            );

        $tagIds =
            InputValue::integerList(
                $data['tagIds'] ?? [],
                'tagIds',
                'INVALID_TASK_INPUT',
            );

        if ($tagIds !== []) {
            throw new ValidationException(
                'Tags cannot be added to note tasks.',
                'TASK_TAGS_NOT_ALLOWED',
            );
        }

        return new self(
            $title,
            InputValue::string(
                $data,
                'description',
                '',
                'INVALID_TASK_INPUT',
            ),
            InputValue::string(
                $data,
                'priority',
                'normal',
                'INVALID_TASK_INPUT',
            ),
            InputValue::string(
                $data,
                'status',
                'todo',
                'INVALID_TASK_INPUT',
            ),
            InputValue::optionalNonNegativeInt(
                $data['position'] ?? null,
                'position',
                'INVALID_TASK_INPUT',
            ),
            $tagIds,
            InputValue::nullableDate(
                $data['startDate'] ?? null,
                'startDate',
                'INVALID_TASK_INPUT',
            ),
            InputValue::nullableDate(
                $data['dueDate'] ?? null,
                'dueDate',
                'INVALID_TASK_INPUT',
            ),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function title(
        array $data,
    ): string {
        if (
            array_key_exists(
                'title',
                $data,
            )
        ) {
            return InputValue::string(
                $data,
                'title',
                '',
                'INVALID_TASK_INPUT',
            );
        }

        return InputValue::string(
            $data,
            'content',
            '',
            'INVALID_TASK_INPUT',
        );
    }
}
