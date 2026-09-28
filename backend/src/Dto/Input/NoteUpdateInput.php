<?php

declare(strict_types=1);

namespace App\Dto\Input;

final readonly class NoteUpdateInput
{
    /**
     * @param list<int> $tagIds
     */
    public function __construct(
        public string $title,
        public string $content,
        public array $tagIds,
        public ?int $projectId,
        public bool $projectProvided,
        public ?bool $isPinned,
        public ?string $color,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(
        array $data,
    ): self {
        $projectProvided =
            array_key_exists(
                'projectId',
                $data,
            );

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

            InputValue::integerList(
                $data['tagIds'] ?? [],
                'tagIds',
                'INVALID_NOTE_INPUT',
            ),

            self::projectId(
                $data,
                $projectProvided,
            ),

            $projectProvided,

            InputValue::optionalBoolean(
                $data,
                'isPinned',
                'INVALID_NOTE_INPUT',
            ),

            InputValue::nullableOptionalString(
                $data,
                'color',
                'INVALID_NOTE_INPUT',
            ),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function projectId(
        array $data,
        bool $provided,
    ): ?int {
        if (
            !$provided
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
