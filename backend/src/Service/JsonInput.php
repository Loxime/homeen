<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Request;

final class JsonInput
{
    /**
     * @return array<string, mixed>
     */
    public function read(
        Request $request,
    ): array {
        $content =
            $request->getContent();

        if ($content === '') {
            $content = '{}';
        }

        try {
            $data =
                json_decode(
                    $content,
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
        } catch (\JsonException $exception) {
            throw new ValidationException(
                'Request body must contain valid JSON.',
                'INVALID_JSON',
                $exception,
            );
        }

        if (
            !str_starts_with(
                ltrim($content),
                '{',
            )
            || !is_array($data)
        ) {
            throw new ValidationException(
                'Request body must be a JSON object.',
                'INVALID_JSON_OBJECT',
            );
        }

        return $data;
    }
}
