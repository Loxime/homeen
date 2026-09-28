<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Exception\ValidationException;

final readonly class ProjectTaskOrderInput
{
    /**
     * @param list<array{
     *     workflowStageId:int,
     *     taskIds:list<int>
     * }> $columns
     */
    public function __construct(
        public array $columns,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data,
    ): self {
        $rawColumns =
            $data['columns']
            ?? null;

        if (!is_array($rawColumns)) {
            throw new ValidationException(
                'columns must be an array.',
                'INVALID_PROJECT_TASK_ORDER',
            );
        }

        $columns = [];

        foreach ($rawColumns as $rawColumn) {
            if (!is_array($rawColumn)) {
                throw new ValidationException(
                    'Each task-order column must be an object.',
                    'INVALID_PROJECT_TASK_ORDER',
                );
            }

            $rawTaskIds =
                $rawColumn['taskIds']
                ?? null;

            if (!is_array($rawTaskIds)) {
                throw new ValidationException(
                    'taskIds must be an array.',
                    'INVALID_PROJECT_TASK_ORDER',
                );
            }

            $columns[] = [
                'workflowStageId' =>
                    InputValue::positiveInt(
                        $rawColumn[
                            'workflowStageId'
                        ] ?? null,
                        'workflowStageId',
                        'INVALID_PROJECT_TASK_ORDER',
                    ),

                'taskIds' =>
                    InputValue::integerList(
                        $rawTaskIds,
                        'taskIds',
                        'INVALID_PROJECT_TASK_ORDER',
                    ),
            ];
        }

        return new self(
            $columns,
        );
    }
}
