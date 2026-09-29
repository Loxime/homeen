<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Input;

use App\Dto\Input\AuthLoginInput;
use App\Dto\Input\AuthPasswordChangeInput;
use App\Dto\Input\PomodoroRatingInput;
use App\Dto\Input\PomodoroStartInput;
use App\Dto\Input\ProfileDeleteInput;
use App\Dto\Input\ProfileEmailInput;
use App\Dto\Input\ProfileNotificationInput;
use App\Dto\Input\ProfilePasswordChangeInput;
use App\Dto\Input\UsageActivityInput;
use App\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class ActiveInputTest extends TestCase
{
    public function testProfileEmailIsTrimmed(): void
    {
        $input = ProfileEmailInput::fromArray([
            'email' => '  user@example.test  ',
        ]);

        self::assertSame(
            'user@example.test',
            $input->email,
        );
    }

    public function testProfilePasswordInputIsTyped(): void
    {
        $input =
            ProfilePasswordChangeInput::fromArray([
                'currentPassword' => 'old',
                'password' => 'new',
                'confirmation' => 'new',
            ]);

        self::assertSame(
            'old',
            $input->currentPassword,
        );

        self::assertSame(
            'new',
            $input->password,
        );

        self::assertSame(
            'new',
            $input->confirmation,
        );
    }

    public function testProfileNotificationRequiresBoolean(): void
    {
        $this->expectException(
            ValidationException::class,
        );

        ProfileNotificationInput::fromArray([
            'soundEnabled' => 'yes',
        ]);
    }

    public function testProfileDeleteDefaultsPasswordToEmpty(): void
    {
        $input =
            ProfileDeleteInput::fromArray([]);

        self::assertSame(
            '',
            $input->password,
        );
    }

    public function testAuthLoginIsTypedAndEmailTrimmed(): void
    {
        $input =
            AuthLoginInput::fromArray([
                'email' => ' user@example.test ',
                'password' => 'secret',
            ]);

        self::assertSame(
            'user@example.test',
            $input->email,
        );

        self::assertSame(
            'secret',
            $input->password,
        );
    }

    public function testAuthPasswordChangeIsTyped(): void
    {
        $input =
            AuthPasswordChangeInput::fromArray([
                'password' => 'new-password',
                'confirmation' => 'new-password',
            ]);

        self::assertSame(
            'new-password',
            $input->password,
        );

        self::assertSame(
            'new-password',
            $input->confirmation,
        );
    }

    public function testPomodoroInputsKeepIntegerValues(): void
    {
        self::assertSame(
            25,
            PomodoroStartInput::fromArray([
                'workMinutes' => 25,
            ])->workMinutes,
        );

        self::assertSame(
            3,
            PomodoroRatingInput::fromArray([
                'rating' => 3,
            ])->rating,
        );
    }

    public function testPomodoroRejectsStringIntegers(): void
    {
        $this->expectException(
            ValidationException::class,
        );

        PomodoroStartInput::fromArray([
            'workMinutes' => '25',
        ]);
    }

    public function testUsageInputDefaultsToZero(): void
    {
        self::assertSame(
            0,
            UsageActivityInput::fromArray([])
                ->activeSeconds,
        );
    }

    public function testUsageRejectsNonIntegerSeconds(): void
    {
        $this->expectException(
            ValidationException::class,
        );

        UsageActivityInput::fromArray([
            'activeSeconds' => 12.5,
        ]);
    }
}
