<?php

declare(strict_types=1);

namespace App\Exception;

final class NotFoundException extends ApiProblemException
{
    public function __construct(
        string $message,
        string $errorCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            404,
            $errorCode,
            $previous,
        );
    }
}
