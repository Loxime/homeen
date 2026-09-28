<?php

declare(strict_types=1);

namespace App\Exception;

interface ApiProblem
{
    public function statusCode(): int;

    public function errorCode(): string;

    public function publicMessage(): string;
}
