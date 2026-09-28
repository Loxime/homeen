<?php

declare(strict_types=1);

namespace App\Exception;

final class ValidationException extends ApiProblemException
{
    public function __construct(
        string $message,
        string $errorCode = 'INVALID_REQUEST',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            422,
            $errorCode,
            $previous,
        );
    }
}
