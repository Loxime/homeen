<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class ProjectWorkflowStageInput
{
    public function __construct(
        public string $name,
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
                'PROJECT_WORKFLOW_INVALID',
            ),
        );
    }
}
