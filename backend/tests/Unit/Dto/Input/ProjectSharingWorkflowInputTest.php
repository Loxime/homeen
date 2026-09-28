<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Input;

use App\Dto\Input\ProjectInviteInput;
use App\Dto\Input\ProjectMemberRoleInput;
use App\Dto\Input\ProjectWorkflowOrderInput;
use App\Dto\Input\ProjectWorkflowStageInput;
use App\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class ProjectSharingWorkflowInputTest extends TestCase
{
    public function testInviteInputAcceptsEmailString(): void
    {
        $input =
            ProjectInviteInput::fromArray([
                'email' => 'user@example.test',
            ]);

        self::assertSame(
            'user@example.test',
            $input->email,
        );
    }

    public function testInviteInputRejectsNonStringEmail(): void
    {
        try {
            ProjectInviteInput::fromArray([
                'email' => 123,
            ]);

            self::fail(
                'Expected ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertSame(
                'PROJECT_INVALID_INPUT',
                $exception->errorCode(),
            );
        }
    }

    public function testMemberRoleInputRejectsNonStringRole(): void
    {
        try {
            ProjectMemberRoleInput::fromArray([
                'role' => true,
            ]);

            self::fail(
                'Expected ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertSame(
                'PROJECT_INVALID_INPUT',
                $exception->errorCode(),
            );
        }
    }

    public function testWorkflowStageInputRejectsNonStringName(): void
    {
        try {
            ProjectWorkflowStageInput::fromArray([
                'name' => [],
            ]);

            self::fail(
                'Expected ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertSame(
                'PROJECT_WORKFLOW_INVALID',
                $exception->errorCode(),
            );
        }
    }

    public function testWorkflowOrderRequiresAnArray(): void
    {
        try {
            ProjectWorkflowOrderInput::fromArray([
                'stageIds' => '1,2',
            ]);

            self::fail(
                'Expected ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertSame(
                'stageIds must be an array.',
                $exception->publicMessage(),
            );

            self::assertSame(
                'PROJECT_WORKFLOW_INVALID',
                $exception->errorCode(),
            );
        }
    }

    public function testWorkflowOrderRejectsInvalidIds(): void
    {
        try {
            ProjectWorkflowOrderInput::fromArray([
                'stageIds' => [
                    1,
                    0,
                ],
            ]);

            self::fail(
                'Expected ValidationException.'
            );
        } catch (ValidationException $exception) {
            self::assertSame(
                'stageIds must contain positive integers.',
                $exception->publicMessage(),
            );

            self::assertSame(
                'PROJECT_WORKFLOW_INVALID',
                $exception->errorCode(),
            );
        }
    }

    public function testWorkflowOrderPreservesDuplicates(): void
    {
        $input =
            ProjectWorkflowOrderInput::fromArray([
                'stageIds' => [
                    3,
                    1,
                    3,
                ],
            ]);

        self::assertSame(
            [
                3,
                1,
                3,
            ],
            $input->stageIds,
        );
    }
}
