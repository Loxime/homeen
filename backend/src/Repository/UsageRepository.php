<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use Doctrine\DBAL\Connection;

final readonly class UsageRepository
{
    public function __construct(private Connection $connection, private ActivityLogger $logger)
    {
    }

    /** @return array{id:int, startedAt:string} */
    public function start(): array
    {
        $row = $this->connection->fetchAssociative('INSERT INTO app_usage_session DEFAULT VALUES RETURNING id, started_at AS "startedAt"');
        if ($row === false) {
            throw new \RuntimeException('Unable to start application usage session.');
        }

        $id = (int) $row['id'];
        $this->logger->log('APP_SESSION_STARTED', 'app_usage_session', $id);

        return ['id' => $id, 'startedAt' => (string) $row['startedAt']];
    }

    public function heartbeat(int $id, int $activeSeconds): void
    {
        $activeSeconds = max(0, min(60, $activeSeconds));
        $affected = $this->connection->executeStatement(<<<'SQL'
UPDATE app_usage_session
SET active_seconds = active_seconds + :seconds,
    last_seen_at = NOW()
WHERE id = :id AND ended_at IS NULL
SQL, ['id' => $id, 'seconds' => $activeSeconds]);

        if ($affected === 0) {
            throw new \OutOfBoundsException('Application usage session not found or already ended.');
        }
        if ($activeSeconds > 0) {
            $this->connection->insert('app_usage_slice', [
                'session_id' => $id,
                'active_seconds' => $activeSeconds,
            ]);
        }
    }

    public function stopAllOpen(): void
    {
        $ids = $this->connection->fetchFirstColumn('SELECT id FROM app_usage_session WHERE ended_at IS NULL');
        foreach ($ids as $id) {
            $this->stop((int) $id);
        }
    }

    public function stop(int $id, int $finalActiveSeconds = 0): void
    {
        $finalActiveSeconds = max(0, min(60, $finalActiveSeconds));
        $row = $this->connection->fetchAssociative(<<<'SQL'
UPDATE app_usage_session
SET active_seconds = active_seconds + :finalActiveSeconds,
    ended_at = NOW(),
    last_seen_at = NOW()
WHERE id = :id AND ended_at IS NULL
RETURNING active_seconds AS "activeSeconds", started_at AS "startedAt", ended_at AS "endedAt"
SQL, ['id' => $id, 'finalActiveSeconds' => $finalActiveSeconds]);

        if ($row === false) {
            return;
        }
        if ($finalActiveSeconds > 0) {
            $this->connection->insert('app_usage_slice', [
                'session_id' => $id,
                'active_seconds' => $finalActiveSeconds,
            ]);
        }

        $this->logger->log('APP_SESSION_ENDED', 'app_usage_session', $id, [
            'activeSeconds' => (int) $row['activeSeconds'],
        ]);
    }
}
