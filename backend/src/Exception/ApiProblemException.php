<?php

declare(strict_types=1);

namespace App\Exception;

class ApiProblemException extends \RuntimeException implements ApiProblem
{
    public function __construct(
        private readonly string $publicMessage,
        private readonly int $statusCode,
        private readonly string $errorCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $publicMessage,
            0,
            $previous,
        );
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function publicMessage(): string
    {
        return $this->publicMessage;
    }
}
