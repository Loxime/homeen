<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Input;

use App\Dto\Input\ProjectTaskCreateInput;
use App\Dto\Input\ProjectTaskOrderInput;
use App\Dto\Input\ProjectTaskUpdateInput;
use App\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class ProjectTaskInputTest extends TestCase
{
    public function testCreateUsesDefaults():
    void {
        $input =
            ProjectTaskCreateInput::fromArray([
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
        self::assertNull(
            $input->workflowStageId,
        );
        self::assertNull(
            $input->position,
        );
        self::assertSame(
            [],
            $input->tagIds,
        );
    }

    public function testCreateRejectsStringStageId():
    void {
        $this->expectException(
            ValidationException::class,
        );

        ProjectTaskCreateInput::fromArray([
            'title' => 'Issue',
            'workflowStageId' => '1',
        ]);
    }

    public function testCreateAcceptsTags():
    void {
        $input =
            ProjectTaskCreateInput::fromArray([
                'title' => 'Issue',
                'tagIds' => [1, 2, 2],
            ]);

        self::assertSame(
            [1, 2],
            $input->tagIds,
        );
    }

    public function testUpdateTracksStageAndPosition():
    void {
        $input =
            ProjectTaskUpdateInput::fromArray([
                'workflowStageId' => 3,
                'position' => 4,
            ]);

        self::assertTrue(
            $input->workflowStageProvided,
        );
        self::assertSame(
            3,
            $input->workflowStageId,
        );
        self::assertTrue(
            $input->positionProvided,
        );
        self::assertSame(
            4,
            $input->position,
        );
    }

    public function testUpdateRejectsNullStage():
    void {
        $this->expectException(
            ValidationException::class,
        );

        ProjectTaskUpdateInput::fromArray([
            'workflowStageId' => null,
        ]);
    }

    public function testOrderParsesColumns():
    void {
        $input =
            ProjectTaskOrderInput::fromArray([
                'columns' => [
                    [
                        'workflowStageId' => 2,
                        'taskIds' =>
                            [5, 7],
                    ],
                ],
            ]);

        self::assertSame(
            [
                [
                    'workflowStageId' =>
                        2,
                    'taskIds' =>
                        [5, 7],
                ],
            ],
            $input->columns,
        );
    }

    public function testOrderRejectsMissingColumns():
    void {
        try {
            ProjectTaskOrderInput::fromArray(
                [],
            );

            self::fail(
                'Expected validation exception.',
            );
        } catch (
            ValidationException $exception
        ) {
            self::assertSame(
                'INVALID_PROJECT_TASK_ORDER',
                $exception->errorCode(),
            );
        }
    }

    public function testOrderRejectsStringTaskId():
    void {
        $this->expectException(
            ValidationException::class,
        );

        ProjectTaskOrderInput::fromArray([
            'columns' => [
                [
                    'workflowStageId' => 2,
                    'taskIds' => ['5'],
                ],
            ],
        ]);
    }
}
