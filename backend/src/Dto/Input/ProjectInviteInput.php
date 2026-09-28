<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class ProjectInviteInput
{
    public function __construct(
        public string $email,
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
                'email',
                '',
                'PROJECT_INVALID_INPUT',
            ),
        );
    }
}
