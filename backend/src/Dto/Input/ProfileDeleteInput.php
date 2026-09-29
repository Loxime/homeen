<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class ProfileDeleteInput
{
    public function __construct(
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
            InputValue::string(
                $data,
                'password',
                '',
                'PASSWORD_REQUIRED',
            ),
        );
    }
}
