<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class ProfilePasswordChangeInput
{
    public function __construct(
        public string $currentPassword,
        public string $password,
        public string $confirmation,
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
                'currentPassword',
                '',
                'INVALID_PASSWORD_INPUT',
            ),
            InputValue::string(
                $data,
                'password',
                '',
                'INVALID_PASSWORD_INPUT',
            ),
            InputValue::string(
                $data,
                'confirmation',
                '',
                'INVALID_PASSWORD_INPUT',
            ),
        );
    }
}
