<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class ProjectTaskUpdateInput
{
    /**
     * @param list<int>|null $tagIds
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $priority,
        public ?string $status,
        public ?int $workflowStageId,
        public bool $workflowStageProvided,
        public ?int $position,
        public bool $positionProvided,
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
        $workflowStageProvided =
            array_key_exists(
                'workflowStageId',
                $data,
            );

        $positionProvided =
            array_key_exists(
                'position',
                $data,
            );

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
                    'INVALID_PROJECT_TASK_INPUT',
                );
        }

        return new self(
            self::title($data),

            InputValue::optionalString(
                $data,
                'description',
                'INVALID_PROJECT_TASK_INPUT',
            ),

            InputValue::optionalString(
                $data,
                'priority',
                'INVALID_PROJECT_TASK_INPUT',
            ),

            InputValue::optionalString(
                $data,
                'status',
                'INVALID_PROJECT_TASK_INPUT',
            ),

            $workflowStageProvided
                ? InputValue::positiveInt(
                    $data['workflowStageId'],
                    'workflowStageId',
                    'INVALID_PROJECT_TASK_INPUT',
                )
                : null,

            $workflowStageProvided,

            $positionProvided
                ? InputValue::nonNegativeInt(
                    $data['position'],
                    'position',
                    'INVALID_PROJECT_TASK_INPUT',
                )
                : null,

            $positionProvided,

            $tagIds,

            $startDateProvided,

            $startDateProvided
                ? InputValue::nullableDate(
                    $data['startDate'],
                    'startDate',
                    'INVALID_PROJECT_TASK_INPUT',
                )
                : null,

            $dueDateProvided,

            $dueDateProvided
                ? InputValue::nullableDate(
                    $data['dueDate'],
                    'dueDate',
                    'INVALID_PROJECT_TASK_INPUT',
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
                'INVALID_PROJECT_TASK_INPUT',
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
                'INVALID_PROJECT_TASK_INPUT',
            );
        }

        return null;
    }
}
