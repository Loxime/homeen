<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\UserPasswordService;
use PHPUnit\Framework\TestCase;

final class UserPasswordServiceTest extends TestCase
{
    public function testTemporaryPasswordsAreRandomAndExpectedLength(): void
    {
        $service = new UserPasswordService();

        $first = $service->generateTemporaryPassword();
        $second = $service->generateTemporaryPassword();

        self::assertSame(24, strlen($first));
        self::assertSame(24, strlen($second));
        self::assertNotSame($first, $second);
    }

    public function testPasswordCanBeVerified(): void
    {
        $service = new UserPasswordService();

        $hash = $service->hash(
            'temporary-password'
        );

        self::assertTrue(
            $service->verify(
                'temporary-password',
                $hash,
            )
        );

        self::assertFalse(
            $service->verify(
                'wrong-password',
                $hash,
            )
        );
    }
}
