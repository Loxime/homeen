<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Exception\ValidationException;

final readonly class ProjectWorkflowOrderInput
{
    /**
     * @param list<int> $stageIds
     */
    public function __construct(
        public array $stageIds,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data,
    ): self {
        $rawStageIds =
            $data['stageIds']
            ?? null;

        if (!is_array($rawStageIds)) {
            throw new ValidationException(
                'stageIds must be an array.',
                'PROJECT_WORKFLOW_INVALID',
            );
        }

        $stageIds = [];

        foreach ($rawStageIds as $stageId) {
            if (
                !is_int($stageId)
                || $stageId <= 0
            ) {
                throw new ValidationException(
                    'stageIds must contain positive integers.',
                    'PROJECT_WORKFLOW_INVALID',
                );
            }

            /*
             * Do not deduplicate here.
             * The repository validates that the order
             * contains each workflow stage exactly once.
             */
            $stageIds[] = $stageId;
        }

        return new self(
            $stageIds,
        );
    }
}
