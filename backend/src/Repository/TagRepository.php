<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class TagRepository
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
    tag.id,
    tag.name,
    tag.color,
    tag.created_at AS "createdAt",
    tag.updated_at AS "updatedAt",
    COUNT(task_tag.task_id) AS "taskCount"
FROM tag
LEFT JOIN task_tag
    ON task_tag.tag_id = tag.id
WHERE tag.user_id = :userId
GROUP BY tag.id
ORDER BY lower(tag.name), tag.id
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
INSERT INTO tag (
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
                'A tag with this name already exists.'
            );
        }

        if ($row === false) {
            throw new \RuntimeException(
                'Unable to create tag.'
            );
        }

        $row['taskCount'] = 0;

        $this->logger->log(
            'TAG_CREATED',
            'tag',
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

        try {
            $row = $this->connection
                ->fetchAssociative(
                    <<<'SQL'
UPDATE tag
SET name = :name,
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
                'A tag with this name already exists.'
            );
        }

        if ($row === false) {
            throw new \OutOfBoundsException(
                'Tag not found.'
            );
        }

        $row['taskCount'] =
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COUNT(*)
FROM task_tag
WHERE tag_id = :id
SQL,
                    [
                        'id' => $id,
                    ],
                );

        $this->logger->log(
            'TAG_UPDATED',
            'tag',
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
        $tag = $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT
    name,
    color
FROM tag
WHERE id = :id
  AND user_id = :userId
SQL,
                [
                    'id' => $id,

                    'userId' =>
                        $this->currentUser->id(),
                ],
            );

        if ($tag === false) {
            throw new \OutOfBoundsException(
                'Tag not found.'
            );
        }

        $this->connection->delete(
            'tag',
            [
                'id' => $id,
                'user_id' =>
                    $this->currentUser->id(),
            ],
        );

        $this->logger->log(
            'TAG_DELETED',
            'tag',
            $id,
            $tag,
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
        $row['id'] = (int) $row['id'];

        $row['taskCount'] =
            (int) ($row['taskCount'] ?? 0);

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
                'Tag name must contain between 1 and 80 characters.'
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
                'Tag color must be a six-digit hexadecimal color.'
            );
        }

        return strtoupper($color);
    }
}
