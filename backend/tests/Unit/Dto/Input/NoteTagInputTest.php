<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Input;

use App\Dto\Input\NoteCreateInput;
use App\Dto\Input\NoteUpdateInput;
use App\Dto\Input\TagCreateInput;
use App\Dto\Input\TagUpdateInput;
use App\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class NoteTagInputTest extends TestCase
{
    public function testNoteCreateUsesDefaults():
    void {
        $input =
            NoteCreateInput::fromArray(
                [],
            );

        self::assertSame('', $input->title);
        self::assertSame('', $input->content);
        self::assertSame('text', $input->noteType);
        self::assertSame([], $input->tagIds);
        self::assertNull($input->projectId);
        self::assertFalse($input->isPinned);
        self::assertSame('#FFFFFF', $input->color);
    }

    public function testNoteCreateRejectsStringProjectId():
    void {
        $this->expectException(
            ValidationException::class,
        );

        NoteCreateInput::fromArray([
            'projectId' => '1',
        ]);
    }

    public function testNoteCreateRejectsNonBooleanPinned():
    void {
        $this->expectException(
            ValidationException::class,
        );

        NoteCreateInput::fromArray([
            'isPinned' => 1,
        ]);
    }

    public function testNoteUpdatePreservesProjectPresence():
    void {
        $missing =
            NoteUpdateInput::fromArray(
                [],
            );

        self::assertFalse(
            $missing->projectProvided,
        );

        $cleared =
            NoteUpdateInput::fromArray([
                'projectId' => null,
            ]);

        self::assertTrue(
            $cleared->projectProvided,
        );

        self::assertNull(
            $cleared->projectId,
        );
    }

    public function testNoteUpdateAcceptsNullColor():
    void {
        $input =
            NoteUpdateInput::fromArray([
                'color' => null,
            ]);

        self::assertNull(
            $input->color,
        );
    }

    public function testNoteRejectsStringTagIds():
    void {
        $this->expectException(
            ValidationException::class,
        );

        NoteCreateInput::fromArray([
            'tagIds' => ['1'],
        ]);
    }

    public function testTagCreateUsesExistingDefaults():
    void {
        $input =
            TagCreateInput::fromArray(
                [],
            );

        self::assertSame(
            '',
            $input->name,
        );

        self::assertSame(
            '',
            $input->color,
        );
    }

    public function testTagCreateRejectsImplicitCast():
    void {
        $this->expectException(
            ValidationException::class,
        );

        TagCreateInput::fromArray([
            'name' => 42,
        ]);
    }

    public function testTagUpdateIsTyped():
    void {
        $input =
            TagUpdateInput::fromArray([
                'name' => 'Urgent',
                'color' => '#FF0000',
            ]);

        self::assertSame(
            'Urgent',
            $input->name,
        );

        self::assertSame(
            '#FF0000',
            $input->color,
        );
    }
}
