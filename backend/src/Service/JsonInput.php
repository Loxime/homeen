<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

final class JsonInput
{
    /** @return array<string, mixed> */
    public function read(Request $request): array
    {
        try {
            $data = json_decode($request->getContent() !== '' ? $request->getContent() : '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('Request body must contain valid JSON.');
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Request body must be a JSON object.');
        }

        return $data;
    }
}
