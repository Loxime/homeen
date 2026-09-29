<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class AuthLoginInput
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data,
    ): self {
        return new self(
            trim(
                InputValue::string(
                    $data,
                    'email',
                    '',
                    'INVALID_CREDENTIALS',
                )
            ),
            InputValue::string(
                $data,
                'password',
                '',
                'INVALID_CREDENTIALS',
            ),
        );
    }
}
