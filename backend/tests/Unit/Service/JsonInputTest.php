<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Exception\ValidationException;
use App\Service\JsonInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class JsonInputTest extends TestCase
{
    private JsonInput $input;

    protected function setUp(): void
    {
        $this->input =
            new JsonInput();
    }

    public function testReadsJsonObject(): void
    {
        $request =
            Request::create(
                '/api/test',
                'POST',
                [],
                [],
                [],
                [],
                '{"name":"Harpocrate"}',
            );

        self::assertSame(
            [
                'name' =>
                    'Harpocrate',
            ],
            $this->input->read(
                $request,
            ),
        );
    }

    public function testEmptyBodyIsEmptyObject():
    void {
        $request =
            Request::create(
                '/api/test',
                'POST',
            );

        self::assertSame(
            [],
            $this->input->read(
                $request,
            ),
        );
    }

    public function testRejectsInvalidJson():
    void {
        $request =
            Request::create(
                '/api/test',
                'POST',
                [],
                [],
                [],
                [],
                '{invalid',
            );

        try {
            $this->input->read(
                $request,
            );

            self::fail(
                'Expected validation exception.',
            );
        } catch (
            ValidationException $exception
        ) {
            self::assertSame(
                'INVALID_JSON',
                $exception->errorCode(),
            );

            self::assertSame(
                422,
                $exception->statusCode(),
            );
        }
    }

    public function testRejectsJsonArray():
    void {
        $request =
            Request::create(
                '/api/test',
                'POST',
                [],
                [],
                [],
                [],
                '[]',
            );

        try {
            $this->input->read(
                $request,
            );

            self::fail(
                'Expected validation exception.',
            );
        } catch (
            ValidationException $exception
        ) {
            self::assertSame(
                'INVALID_JSON_OBJECT',
                $exception->errorCode(),
            );
        }
    }
}
