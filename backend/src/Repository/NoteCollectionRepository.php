<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class NoteCollectionRepository
{
    public function __construct(
        private Connection $connection,
        private ActivityLogger $logger,
        private CurrentUser $currentUser,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $rows = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    collection.id,
    collection.name,
    collection.color,
    collection.created_at AS "createdAt",
    collection.updated_at AS "updatedAt",
    COUNT(note.id)
        FILTER (
            WHERE note.deleted_at IS NULL
        ) AS "noteCount"
FROM note_collection collection
LEFT JOIN note
    ON note.collection_id = collection.id
   AND note.user_id = :userId
WHERE collection.user_id = :userId
GROUP BY collection.id
ORDER BY
    lower(collection.name),
    collection.id
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

    /** @return array<string, mixed> */
    public function create(
        string $name,
        string $color,
    ): array {
        $name = $this->validateName($name);
        $color = $this->validateColor($color);

        try {
            $row = $this->connection
                ->fetchAssociative(
                    <<<'SQL'
INSERT INTO note_collection (
    user_id,
    name,
    color
)
VALUES (
    :userId,
    :name,
    :color
)
RETURNING
    id,
    name,
    color,
    created_at AS "createdAt",
    updated_at AS "updatedAt"
SQL,
                    [
                        'userId' =>
                            $this->currentUser->id(),
                        'name' => $name,
                        'color' => $color,
                    ],
                );
        } catch (
            UniqueConstraintViolationException
        ) {
            throw new \DomainException(
                'A collection with this name already exists.'
            );
        }

        if ($row === false) {
            throw new \RuntimeException(
                'Unable to create collection.'
            );
        }

        $row['noteCount'] = 0;

        $this->logger->log(
            'NOTE_COLLECTION_CREATED',
            'note_collection',
            (int) $row['id'],
            [
                'name' => $name,
                'color' => $color,
            ],
        );

        return $this->normalize($row);
    }

    /** @return array<string, mixed> */
    public function update(
        int $id,
        string $name,
        string $color,
    ): array {
        $name = $this->validateName($name);
        $color = $this->validateColor($color);
        $userId = $this->currentUser->id();

        try {
            $row = $this->connection
                ->fetchAssociative(
                    <<<'SQL'
UPDATE note_collection
SET
    name = :name,
    color = :color,
    updated_at = NOW()
WHERE id = :id
  AND user_id = :userId
RETURNING
    id,
    name,
    color,
    created_at AS "createdAt",
    updated_at AS "updatedAt"
SQL,
                    [
                        'id' => $id,
                        'userId' => $userId,
                        'name' => $name,
                        'color' => $color,
                    ],
                );
        } catch (
            UniqueConstraintViolationException
        ) {
            throw new \DomainException(
                'A collection with this name already exists.'
            );
        }

        if ($row === false) {
            throw new \OutOfBoundsException(
                'Collection not found.'
            );
        }

        $row['noteCount'] =
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COUNT(*)
FROM note
WHERE collection_id = :id
  AND user_id = :userId
  AND deleted_at IS NULL
SQL,
                    [
                        'id' => $id,
                        'userId' => $userId,
                    ],
                );

        $this->logger->log(
            'NOTE_COLLECTION_UPDATED',
            'note_collection',
            $id,
            [
                'name' => $name,
                'color' => $color,
            ],
        );

        return $this->normalize($row);
    }

    public function delete(int $id): void
    {
        $userId = $this->currentUser->id();

        $collection = $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT name, color
FROM note_collection
WHERE id = :id
  AND user_id = :userId
SQL,
                [
                    'id' => $id,
                    'userId' => $userId,
                ],
            );

        if ($collection === false) {
            throw new \OutOfBoundsException(
                'Collection not found.'
            );
        }

        $this->connection->delete(
            'note_collection',
            [
                'id' => $id,
                'user_id' => $userId,
            ],
        );

        $this->logger->log(
            'NOTE_COLLECTION_DELETED',
            'note_collection',
            $id,
            $collection,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['noteCount'] =
            (int) ($row['noteCount'] ?? 0);

        return $row;
    }

    private function validateName(
        string $name,
    ): string {
        $name = trim($name);

        if (
            $name === ''
            || mb_strlen($name) > 80
        ) {
            throw new \InvalidArgumentException(
                'Collection name must contain between 1 and 80 characters.'
            );
        }

        return $name;
    }

    private function validateColor(
        string $color,
    ): string {
        if (
            preg_match(
                '/^#[0-9A-Fa-f]{6}$/',
                $color,
            ) !== 1
        ) {
            throw new \InvalidArgumentException(
                'Collection color must be a six-digit hexadecimal color.'
            );
        }

        return strtoupper($color);
    }
}
