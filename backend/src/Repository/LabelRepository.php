<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class LabelRepository
{
    public function __construct(private Connection $connection, private ActivityLogger $logger)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT l.id, l.name, l.color,
       l.created_at AS "createdAt", l.updated_at AS "updatedAt",
       COUNT(n.id) FILTER (WHERE n.deleted_at IS NULL) AS "noteCount"
FROM label l
LEFT JOIN note n ON n.label_id = l.id
GROUP BY l.id
ORDER BY lower(l.name)
SQL);

        return array_map($this->normalize(...), $rows);
    }

    /** @return array<string, mixed> */
    public function create(string $name, string $color): array
    {
        $name = $this->validateName($name);
        $color = $this->validateColor($color);

        try {
            $row = $this->connection->fetchAssociative(
                'INSERT INTO label (name, color) VALUES (:name, :color) RETURNING id, name, color, created_at AS "createdAt", updated_at AS "updatedAt"',
                ['name' => $name, 'color' => $color],
            );
        } catch (UniqueConstraintViolationException) {
            throw new \DomainException('A label with this name already exists.');
        }

        if ($row === false) {
            throw new \RuntimeException('Unable to create label.');
        }

        $this->logger->log('LABEL_CREATED', 'label', (int) $row['id'], ['name' => $name, 'color' => $color]);
        $row['noteCount'] = 0;

        return $this->normalize($row);
    }

    /** @return array<string, mixed> */
    public function update(int $id, string $name, string $color): array
    {
        $name = $this->validateName($name);
        $color = $this->validateColor($color);

        try {
            $row = $this->connection->fetchAssociative(
                'UPDATE label SET name = :name, color = :color, updated_at = NOW() WHERE id = :id RETURNING id, name, color, created_at AS "createdAt", updated_at AS "updatedAt"',
                ['id' => $id, 'name' => $name, 'color' => $color],
            );
        } catch (UniqueConstraintViolationException) {
            throw new \DomainException('A label with this name already exists.');
        }

        if ($row === false) {
            throw new \OutOfBoundsException('Label not found.');
        }

        $row['noteCount'] = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM note WHERE label_id = :id AND deleted_at IS NULL', ['id' => $id]);
        $this->logger->log('LABEL_UPDATED', 'label', $id, ['name' => $name, 'color' => $color]);

        return $this->normalize($row);
    }

    public function delete(int $id): void
    {
        $label = $this->connection->fetchAssociative('SELECT name, color FROM label WHERE id = :id', ['id' => $id]);
        if ($label === false) {
            throw new \OutOfBoundsException('Label not found.');
        }

        $this->connection->delete('label', ['id' => $id]);
        $this->logger->log('LABEL_DELETED', 'label', $id, $label);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['noteCount'] = (int) ($row['noteCount'] ?? 0);

        return $row;
    }

    private function validateName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new \InvalidArgumentException('Label name must contain between 1 and 80 characters.');
        }

        return $name;
    }

    private function validateColor(string $color): string
    {
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
            throw new \InvalidArgumentException('Label color must be a six-digit hexadecimal color.');
        }

        return strtoupper($color);
    }
}
