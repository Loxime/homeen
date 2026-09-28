<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Input;

use App\Dto\Input\TaskCompletionInput;
use App\Dto\Input\TaskCreateInput;
use App\Dto\Input\TaskUpdateInput;
use App\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class TaskInputTest extends TestCase
{
    public function testCreateUsesDefaults(): void
    {
        $input =
            TaskCreateInput::fromArray([
                'title' => 'Issue',
            ]);

        self::assertSame(
            'Issue',
            $input->title,
        );
        self::assertSame(
            '',
            $input->description,
        );
        self::assertSame(
            'normal',
            $input->priority,
        );
        self::assertSame(
            'todo',
            $input->status,
        );
        self::assertNull(
            $input->position,
        );
        self::assertSame(
            [],
            $input->tagIds,
        );
        self::assertNull(
            $input->startDate,
        );
        self::assertNull(
            $input->dueDate,
        );
    }

    public function testCreateSupportsLegacyContent():
    void {
        $input =
            TaskCreateInput::fromArray([
                'content' =>
                    'Ancienne tâche',
            ]);

        self::assertSame(
            'Ancienne tâche',
            $input->title,
        );
    }

    public function testCreateRejectsImplicitStringCast():
    void {
        $this->expectException(
            ValidationException::class,
        );

        TaskCreateInput::fromArray([
            'title' => 42,
        ]);
    }

    public function testCreateRejectsTags():
    void {
        try {
            TaskCreateInput::fromArray([
                'title' => 'Issue',
                'tagIds' => [1],
            ]);

            self::fail(
                'Expected validation exception.',
            );
        } catch (
            ValidationException $exception
        ) {
            self::assertSame(
                'TASK_TAGS_NOT_ALLOWED',
                $exception->errorCode(),
            );

            self::assertSame(
                'Tags cannot be added to note tasks.',
                $exception->publicMessage(),
            );
        }
    }

    public function testCreateRejectsInvalidPosition():
    void {
        $this->expectException(
            ValidationException::class,
        );

        TaskCreateInput::fromArray([
            'title' => 'Issue',
            'position' => '2',
        ]);
    }

    public function testCreateNormalizesEmptyDate():
    void {
        $input =
            TaskCreateInput::fromArray([
                'title' => 'Issue',
                'startDate' => '  ',
            ]);

        self::assertNull(
            $input->startDate,
        );
    }

    public function testUpdateTracksClearedDates():
    void {
        $input =
            TaskUpdateInput::fromArray([
                'startDate' => null,
            ]);

        self::assertTrue(
            $input->startDateProvided,
        );

        self::assertNull(
            $input->startDate,
        );

        self::assertFalse(
            $input->dueDateProvided,
        );
    }

    public function testUpdatePreservesMissingFields():
    void {
        $input =
            TaskUpdateInput::fromArray(
                [],
            );

        self::assertNull(
            $input->title,
        );
        self::assertNull(
            $input->description,
        );
        self::assertNull(
            $input->priority,
        );
        self::assertNull(
            $input->status,
        );
        self::assertNull(
            $input->tagIds,
        );
    }

    public function testUpdateAcceptsEmptyTagArray():
    void {
        $input =
            TaskUpdateInput::fromArray([
                'tagIds' => [],
            ]);

        self::assertSame(
            [],
            $input->tagIds,
        );
    }

    public function testCompletionRequiresBoolean():
    void {
        try {
            TaskCompletionInput::fromArray([
                'completed' => 1,
            ]);

            self::fail(
                'Expected validation exception.',
            );
        } catch (
            ValidationException $exception
        ) {
            self::assertSame(
                'INVALID_TASK_COMPLETION',
                $exception->errorCode(),
            );
        }
    }

    public function testCompletionDefaultsToFalse():
    void {
        $input =
            TaskCompletionInput::fromArray(
                [],
            );

        self::assertFalse(
            $input->completed,
        );
    }
}
