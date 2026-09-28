<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Input;

use App\Dto\Input\ProjectCreateInput;
use App\Dto\Input\ProjectUpdateInput;
use App\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class ProjectInputTest extends TestCase
{
    public function testCreateUsesDefaults(): void
    {
        $input =
            ProjectCreateInput::fromArray(
                [],
            );

        self::assertSame(
            '',
            $input->name,
        );

        self::assertSame(
            '',
            $input->description,
        );

        self::assertSame(
            '#1A73E8',
            $input->color,
        );
    }

    public function testCreatePreservesTypedValues(): void
    {
        $input =
            ProjectCreateInput::fromArray([
                'name' => 'Harpocrate',
                'description' =>
                    'Produit',
                'color' =>
                    '#123456',
            ]);

        self::assertSame(
            'Harpocrate',
            $input->name,
        );

        self::assertSame(
            'Produit',
            $input->description,
        );

        self::assertSame(
            '#123456',
            $input->color,
        );
    }

    public function testCreateRejectsInvalidTypes():
    void {
        try {
            ProjectCreateInput::fromArray([
                'name' => 42,
            ]);

            self::fail(
                'Expected validation exception.',
            );
        } catch (
            ValidationException $exception
        ) {
            self::assertSame(
                422,
                $exception->statusCode(),
            );

            self::assertSame(
                'INVALID_PROJECT_INPUT',
                $exception->errorCode(),
            );

            self::assertSame(
                'name must be a string.',
                $exception->publicMessage(),
            );
        }
    }

    public function testUpdateDistinguishesMissingFields():
    void {
        $input =
            ProjectUpdateInput::fromArray([
                'description' =>
                    'Nouvelle description',
            ]);

        self::assertNull(
            $input->name,
        );

        self::assertSame(
            'Nouvelle description',
            $input->description,
        );

        self::assertNull(
            $input->color,
        );
    }

    public function testUpdateRejectsNullAsString():
    void {
        $this->expectException(
            ValidationException::class,
        );

        ProjectUpdateInput::fromArray([
            'color' => null,
        ]);
    }
}
