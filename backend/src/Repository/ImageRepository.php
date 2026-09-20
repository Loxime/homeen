<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;

final readonly class ImageRepository
{
    public function __construct(
        private Connection $connection,
        private CurrentUser $currentUser,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $rows =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    image.id,
    image.original_name
        AS "originalName",
    image.mime_type
        AS "mimeType",
    image.size_bytes
        AS "sizeBytes",
    image.width,
    image.height,
    image.created_at
        AS "createdAt",
    COUNT(note_image.note_id)
        AS "noteCount"
FROM image_asset image
LEFT JOIN note_image
    ON note_image.image_id = image.id
WHERE image.user_id = :userId
GROUP BY image.id
ORDER BY
    image.created_at DESC,
    image.id DESC
SQL,
                    [
                        'userId' =>
                            $this->currentUser->id(),
                    ],
                );

        return array_map(
            $this->normalize(...),
            $rows,
        );
    }

    /**
     * @param array{
     *     storedName:string,
     *     originalName:string,
     *     mimeType:string,
     *     sizeBytes:int,
     *     width:int|null,
     *     height:int|null
     * } $image
     *
     * @return array<string, mixed>
     */
    public function create(
        array $image,
    ): array {
        $id =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO image_asset (
    user_id,
    stored_name,
    original_name,
    mime_type,
    size_bytes,
    width,
    height
)
VALUES (
    :userId,
    :storedName,
    :originalName,
    :mimeType,
    :sizeBytes,
    :width,
    :height
)
RETURNING id
SQL,
                    [
                        'userId' =>
                            $this->currentUser->id(),

                        'storedName' =>
                            $image['storedName'],

                        'originalName' =>
                            $image['originalName'],

                        'mimeType' =>
                            $image['mimeType'],

                        'sizeBytes' =>
                            $image['sizeBytes'],

                        'width' =>
                            $image['width'],

                        'height' =>
                            $image['height'],
                    ],
                );

        if ($id === false) {
            throw new \RuntimeException(
                'Unable to create image asset.'
            );
        }

        return $this->get(
            (int) $id
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get(
        int $id,
    ): array {
        $row =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    image.id,
    image.original_name
        AS "originalName",
    image.mime_type
        AS "mimeType",
    image.size_bytes
        AS "sizeBytes",
    image.width,
    image.height,
    image.created_at
        AS "createdAt",
    (
        SELECT COUNT(*)
        FROM note_image
        WHERE note_image.image_id
            = image.id
    ) AS "noteCount"
FROM image_asset image
WHERE image.id = :id
  AND image.user_id = :userId
SQL,
                    [
                        'id' => $id,
                        'userId' =>
                            $this->currentUser->id(),
                    ],
                );

        if ($row === false) {
            throw new \OutOfBoundsException(
                'Image not found.'
            );
        }

        return $this->normalize(
            $row
        );
    }

    /**
     * @return array{
     *     storedName:string,
     *     originalName:string,
     *     mimeType:string
     * }
     */
    public function file(
        int $id,
    ): array {
        $row =
            $this->connection
                ->fetchAssociative(
                    <<<'SQL'
SELECT
    stored_name AS "storedName",
    original_name AS "originalName",
    mime_type AS "mimeType"
FROM image_asset
WHERE id = :id
  AND user_id = :userId
SQL,
                    [
                        'id' => $id,
                        'userId' =>
                            $this->currentUser->id(),
                    ],
                );

        if ($row === false) {
            throw new \OutOfBoundsException(
                'Image not found.'
            );
        }

        return [
            'storedName' =>
                (string) $row['storedName'],

            'originalName' =>
                (string) $row['originalName'],

            'mimeType' =>
                (string) $row['mimeType'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forNote(
        int $noteId,
    ): array {
        $this->assertNote(
            $noteId
        );

        $rows =
            $this->connection
                ->fetchAllAssociative(
                    <<<'SQL'
SELECT
    image.id,
    image.original_name
        AS "originalName",
    image.mime_type
        AS "mimeType",
    image.size_bytes
        AS "sizeBytes",
    image.width,
    image.height,
    image.created_at
        AS "createdAt",
    (
        SELECT COUNT(*)
        FROM note_image count_link
        WHERE count_link.image_id
            = image.id
    ) AS "noteCount"
FROM note_image
INNER JOIN image_asset image
    ON image.id = note_image.image_id
WHERE note_image.note_id = :noteId
  AND image.user_id = :userId
ORDER BY
    note_image.created_at ASC,
    image.id ASC
SQL,
                    [
                        'noteId' =>
                            $noteId,

                        'userId' =>
                            $this->currentUser->id(),
                    ],
                );

        return array_map(
            $this->normalize(...),
            $rows,
        );
    }

    public function attach(
        int $imageId,
        int $noteId,
    ): void {
        $this->assertNote(
            $noteId
        );

        $this->get(
            $imageId
        );

        $this->connection
            ->executeStatement(
                <<<'SQL'
INSERT INTO note_image (
    note_id,
    image_id
)
VALUES (
    :noteId,
    :imageId
)
ON CONFLICT DO NOTHING
SQL,
                [
                    'noteId' =>
                        $noteId,

                    'imageId' =>
                        $imageId,
                ],
            );

        $this->touchNote(
            $noteId
        );
    }

    public function detach(
        int $imageId,
        int $noteId,
    ): void {
        $this->assertNote(
            $noteId
        );

        $this->get(
            $imageId
        );

        $this->connection
            ->executeStatement(
                <<<'SQL'
DELETE FROM note_image
WHERE note_id = :noteId
  AND image_id = :imageId
SQL,
                [
                    'noteId' =>
                        $noteId,

                    'imageId' =>
                        $imageId,
                ],
            );

        $this->touchNote(
            $noteId
        );
    }

    public function delete(
        int $id,
    ): string {
        $file =
            $this->file(
                $id
            );

        $affected =
            $this->connection
                ->executeStatement(
                    <<<'SQL'
DELETE FROM image_asset
WHERE id = :id
  AND user_id = :userId
SQL,
                    [
                        'id' => $id,
                        'userId' =>
                            $this->currentUser->id(),
                    ],
                );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Image not found.'
            );
        }

        return $file['storedName'];
    }

    private function assertNote(
        int $noteId,
    ): void {
        $exists =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM note
WHERE id = :noteId
  AND user_id = :userId
  AND channel_id IS NULL
  AND deleted_at IS NULL
LIMIT 1
SQL,
                    [
                        'noteId' =>
                            $noteId,

                        'userId' =>
                            $this->currentUser->id(),
                    ],
                );

        if ($exists === false) {
            throw new \OutOfBoundsException(
                'Note not found.'
            );
        }
    }

    private function touchNote(
        int $noteId,
    ): void {
        $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET updated_at = NOW()
WHERE id = :noteId
  AND user_id = :userId
  AND channel_id IS NULL
SQL,
                [
                    'noteId' =>
                        $noteId,

                    'userId' =>
                        $this->currentUser->id(),
                ],
            );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalize(
        array $row,
    ): array {
        $id =
            (int) $row['id'];

        return [
            'id' =>
                $id,

            'originalName' =>
                (string) $row['originalName'],

            'mimeType' =>
                (string) $row['mimeType'],

            'sizeBytes' =>
                (int) $row['sizeBytes'],

            'width' =>
                $row['width'] !== null
                    ? (int) $row['width']
                    : null,

            'height' =>
                $row['height'] !== null
                    ? (int) $row['height']
                    : null,

            'createdAt' =>
                (string) $row['createdAt'],

            'noteCount' =>
                (int) $row['noteCount'],

            'contentUrl' =>
                sprintf(
                    '/api/images/%d/content',
                    $id,
                ),
        ];
    }
}
