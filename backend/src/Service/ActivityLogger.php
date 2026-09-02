<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final readonly class ActivityLogger
{
    public function __construct(private Connection $connection)
    {
    }

    /** @param array<string, mixed> $metadata */
    public function log(string $eventType, ?string $entityType = null, ?int $entityId = null, array $metadata = []): void
    {
        $this->connection->insert('activity_event', [
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:sP'),
        ], [
            'metadata' => \Doctrine\DBAL\Types\Types::JSON,
        ]);
    }
}
