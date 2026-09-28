<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class NoteCreateInput
{
    /**
     * @param list<int> $tagIds
     */
    public function __construct(
        public string $title,
        public string $content,
        public string $noteType,
        public array $tagIds,
        public ?int $projectId,
        public bool $isPinned,
        public string $color,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data,
    ): self {
        return new self(
            InputValue::string(
                $data,
                'title',
                '',
                'INVALID_NOTE_INPUT',
            ),

            InputValue::string(
                $data,
                'content',
                '',
                'INVALID_NOTE_INPUT',
            ),

            InputValue::string(
                $data,
                'noteType',
                'text',
                'INVALID_NOTE_INPUT',
            ),

            InputValue::integerList(
                $data['tagIds'] ?? [],
                'tagIds',
                'INVALID_NOTE_INPUT',
            ),

            self::projectId(
                $data,
            ),

            InputValue::boolean(
                $data,
                'isPinned',
                false,
                'INVALID_NOTE_INPUT',
            ),

            InputValue::string(
                $data,
                'color',
                '#FFFFFF',
                'INVALID_NOTE_INPUT',
            ),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function projectId(
        array $data,
    ): ?int {
        if (
            !array_key_exists(
                'projectId',
                $data,
            )
            || $data['projectId'] === null
        ) {
            return null;
        }

        return InputValue::positiveInt(
            $data['projectId'],
            'projectId',
            'INVALID_NOTE_INPUT',
        );
    }
}
