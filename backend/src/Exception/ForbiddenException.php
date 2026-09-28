<?php

declare(strict_types=1);

namespace App\Exception;

final class ForbiddenException extends ApiProblemException
{
    public function __construct(
        string $message,
        string $errorCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            403,
            $errorCode,
            $previous,
        );
    }
}
