<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\PomodoroCalculator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class PomodoroRepository
{
    public function __construct(
        private Connection $connection,
        private ActivityLogger $logger,
        private PomodoroCalculator $calculator,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function presets(): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT id, work_minutes AS "workMinutes", created_at AS "createdAt", last_used_at AS "lastUsedAt"
FROM pomodoro_preset
ORDER BY last_used_at DESC, work_minutes ASC
SQL);

        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['workMinutes'] = (int) $row['workMinutes'];
            return $row;
        }, $rows);
    }

    /** @return array<string, mixed> */
    public function start(int $workMinutes): array
    {
        if ($workMinutes < 5) {
            throw new \InvalidArgumentException('Work duration must be at least 5 minutes.');
        }

        return $this->connection->transactional(function () use ($workMinutes): array {
            if ($this->connection->fetchOne('SELECT 1 FROM pomodoro_session WHERE stopped_at IS NULL LIMIT 1') !== false) {
                throw new \DomainException('A Pomodoro session is already running.');
            }

            $preset = $this->connection->fetchAssociative(<<<'SQL'
INSERT INTO pomodoro_preset (work_minutes)
VALUES (:minutes)
ON CONFLICT (work_minutes)
DO UPDATE SET last_used_at = NOW()
RETURNING id, work_minutes AS "workMinutes", created_at AS "createdAt", last_used_at AS "lastUsedAt"
SQL, ['minutes' => $workMinutes]);

            if ($preset === false) {
                throw new \RuntimeException('Unable to save Pomodoro preset.');
            }

            try {
                $row = $this->connection->fetchAssociative(<<<'SQL'
INSERT INTO pomodoro_session (preset_id, work_minutes_snapshot)
VALUES (:presetId, :minutes)
RETURNING id, preset_id AS "presetId", work_minutes_snapshot AS "workMinutes",
          started_at AS "startedAt", stopped_at AS "stoppedAt",
          focus_seconds AS "focusSeconds", break_seconds AS "breakSeconds"
SQL, ['presetId' => (int) $preset['id'], 'minutes' => $workMinutes]);
            } catch (UniqueConstraintViolationException) {
                throw new \DomainException('A Pomodoro session is already running.');
            }

            if ($row === false) {
                throw new \RuntimeException('Unable to start Pomodoro session.');
            }

            $id = (int) $row['id'];
            $this->logger->log('POMODORO_STARTED', 'pomodoro_session', $id, ['workMinutes' => $workMinutes]);

            return $this->withLiveState($row);
        });
    }

    /** @return array<string, mixed> */
    public function quickStart(): array
    {
        $minutes = $this->connection->fetchOne('SELECT work_minutes FROM pomodoro_preset ORDER BY last_used_at DESC LIMIT 1');
        if ($minutes === false) {
            throw new \OutOfBoundsException('No saved Pomodoro preset exists yet.');
        }

        return $this->start((int) $minutes);
    }

    /** @return array<string, mixed>|null */
    public function active(): ?array
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
SELECT id, preset_id AS "presetId", work_minutes_snapshot AS "workMinutes",
       started_at AS "startedAt", stopped_at AS "stoppedAt",
       focus_seconds AS "focusSeconds", break_seconds AS "breakSeconds"
FROM pomodoro_session
WHERE stopped_at IS NULL
ORDER BY started_at DESC
LIMIT 1
SQL);

        if ($row === false) {
            return null;
        }

        $row = $this->withLiveState($row);
        $this->connection->update('pomodoro_session', [
            'focus_seconds' => $row['focusSeconds'],
            'break_seconds' => $row['breakSeconds'],
        ], ['id' => (int) $row['id']]);

        return $row;
    }

    /** @return array<string, mixed> */
    public function stop(int $id): array
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
SELECT id, preset_id AS "presetId", work_minutes_snapshot AS "workMinutes",
       started_at AS "startedAt", stopped_at AS "stoppedAt",
       focus_seconds AS "focusSeconds", break_seconds AS "breakSeconds"
FROM pomodoro_session
WHERE id = :id
SQL, ['id' => $id]);

        if ($row === false) {
            throw new \OutOfBoundsException('Pomodoro session not found.');
        }
        if ($row['stoppedAt'] !== null) {
            return $this->withStoredSummary($row);
        }

        $row = $this->normalizeSession($row);
        $now = new \DateTimeImmutable();
        $state = $this->calculator->state(new \DateTimeImmutable((string) $row['startedAt']), (int) $row['workMinutes'], $now);
        $this->connection->update('pomodoro_session', [
            'stopped_at' => $now->format('Y-m-d H:i:sP'),
            'focus_seconds' => $state['focusSeconds'],
            'break_seconds' => $state['breakSeconds'],
        ], ['id' => $id]);

        $row['stoppedAt'] = $now->format(DATE_ATOM);
        $row['focusSeconds'] = $state['focusSeconds'];
        $row['breakSeconds'] = $state['breakSeconds'];
        $this->logger->log('POMODORO_STOPPED', 'pomodoro_session', $id, [
            'workMinutes' => (int) $row['workMinutes'],
            'focusSeconds' => $state['focusSeconds'],
            'breakSeconds' => $state['breakSeconds'],
            'completedWorkCycles' => $state['completedWorkCycles'],
            'completedBreakCycles' => $state['completedBreakCycles'],
        ]);

        return array_merge($row, $state, ['isActive' => false]);
    }

    /** @return list<array<string, mixed>> */
    public function history(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $rows = $this->connection->fetchAllAssociative(<<<SQL
SELECT id, work_minutes_snapshot AS "workMinutes", started_at AS "startedAt", stopped_at AS "stoppedAt",
       focus_seconds AS "focusSeconds", break_seconds AS "breakSeconds"
FROM pomodoro_session
ORDER BY started_at DESC
LIMIT $limit
SQL);

        return array_map($this->normalizeSession(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeSession(array $row): array
    {
        $row['id'] = (int) $row['id'];
        if (array_key_exists('presetId', $row)) {
            $row['presetId'] = $row['presetId'] !== null ? (int) $row['presetId'] : null;
        }
        $row['workMinutes'] = (int) $row['workMinutes'];
        $row['focusSeconds'] = (int) $row['focusSeconds'];
        $row['breakSeconds'] = (int) $row['breakSeconds'];
        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function withLiveState(array $row): array
    {
        $row = $this->normalizeSession($row);
        $state = $this->calculator->state(new \DateTimeImmutable((string) $row['startedAt']), (int) $row['workMinutes']);

        return array_merge($row, $state, ['isActive' => true]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function withStoredSummary(array $row): array
    {
        $row = $this->normalizeSession($row);
        $workSeconds = (int) $row['workMinutes'] * 60;
        $breakSeconds = PomodoroCalculator::BREAK_MINUTES * 60;
        $focus = (int) $row['focusSeconds'];
        $break = (int) $row['breakSeconds'];

        return array_merge($row, [
            'phase' => null,
            'remainingSeconds' => 0,
            'completedWorkCycles' => intdiv($focus, $workSeconds),
            'completedBreakCycles' => intdiv($break, $breakSeconds),
            'isActive' => false,
        ]);
    }
}
