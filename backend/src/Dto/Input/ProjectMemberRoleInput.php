<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class ProjectMemberRoleInput
{
    public function __construct(
        public string $role,
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
                'role',
                '',
                'PROJECT_INVALID_INPUT',
            ),
        );
    }
}
