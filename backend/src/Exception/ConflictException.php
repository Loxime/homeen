<?php

declare(strict_types=1);

namespace App\Exception;

final class ConflictException extends ApiProblemException
{
    public function __construct(
        string $message,
        string $errorCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            409,
            $errorCode,
            $previous,
        );
    }
}
