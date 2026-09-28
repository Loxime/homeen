<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class ProjectTaskCreateInput
{
    /**
     * @param list<int> $tagIds
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $priority,
        public string $status,
        public ?int $workflowStageId,
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
        return new self(
            self::title($data),

            InputValue::string(
                $data,
                'description',
                '',
                'INVALID_PROJECT_TASK_INPUT',
            ),

            InputValue::string(
                $data,
                'priority',
                'normal',
                'INVALID_PROJECT_TASK_INPUT',
            ),

            InputValue::string(
                $data,
                'status',
                'todo',
                'INVALID_PROJECT_TASK_INPUT',
            ),

            array_key_exists(
                'workflowStageId',
                $data,
            )
                ? InputValue::positiveInt(
                    $data['workflowStageId'],
                    'workflowStageId',
                    'INVALID_PROJECT_TASK_INPUT',
                )
                : null,

            array_key_exists(
                'position',
                $data,
            )
                ? InputValue::nonNegativeInt(
                    $data['position'],
                    'position',
                    'INVALID_PROJECT_TASK_INPUT',
                )
                : null,

            InputValue::integerList(
                $data['tagIds'] ?? [],
                'tagIds',
                'INVALID_PROJECT_TASK_INPUT',
            ),

            InputValue::nullableDate(
                $data['startDate'] ?? null,
                'startDate',
                'INVALID_PROJECT_TASK_INPUT',
            ),

            InputValue::nullableDate(
                $data['dueDate'] ?? null,
                'dueDate',
                'INVALID_PROJECT_TASK_INPUT',
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
                'INVALID_PROJECT_TASK_INPUT',
            );
        }

        return InputValue::string(
            $data,
            'content',
            '',
            'INVALID_PROJECT_TASK_INPUT',
        );
    }
}
