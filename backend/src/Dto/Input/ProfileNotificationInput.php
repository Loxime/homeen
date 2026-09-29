<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Exception\ValidationException;

final readonly class ProfileNotificationInput
{
    public function __construct(
        public bool $soundEnabled,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data,
    ): self {
        if (
            !array_key_exists(
                'soundEnabled',
                $data,
            )
            || !is_bool(
                $data['soundEnabled']
            )
        ) {
            throw new ValidationException(
                'soundEnabled must be a boolean.',
                'INVALID_NOTIFICATION_SETTING',
            );
        }

        return new self(
            $data['soundEnabled'],
        );
    }
}
