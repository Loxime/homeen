<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\PomodoroCalculator;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class StatisticsRepository
{
    public function __construct(
        private Connection $connection,
        private PomodoroCalculator $pomodoroCalculator,
        #[Autowire('%app.timezone%')] private string $timezone,
    ) {
    }

    /** @return array<string, mixed> */
    public function month(string $month): array
    {
        [$start, $end] = $this->range($month);
        $previousStart = $start->modify('-1 month');
        $previousEnd = $start;

        $current = $this->summary($start, $end);
        $previous = $this->summary($previousStart, $previousEnd);

        return [
            'month' => $month,
            'timezone' => $this->timezone,
            'summary' => $current,
            'previous' => $previous,
            'changes' => $this->changes($current, $previous),
            'days' => $this->daily($start, $end),
            'mostCompletedLabel' => $this->mostCompletedLabel($start, $end),
        ];
    }

    /** @return array<string, int|float> */
    private function summary(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $params = ['start' => $this->utc($start), 'end' => $this->utc($end)];

        $pomodoro = $this->pomodoroMetrics($start, $end);

        $tasksCompleted = (int) $this->connection->fetchOne(<<<'SQL'
SELECT COUNT(*) FROM activity_event
WHERE event_type = 'TASK_COMPLETED' AND occurred_at >= :start AND occurred_at < :end
SQL, $params);

        $notesCreated = (int) $this->connection->fetchOne(<<<'SQL'
SELECT COUNT(*) FROM activity_event
WHERE event_type = 'NOTE_CREATED' AND occurred_at >= :start AND occurred_at < :end
SQL, $params);

        $activeSeconds = (int) $this->connection->fetchOne(<<<'SQL'
SELECT COALESCE(SUM(active_seconds), 0) FROM app_usage_slice
WHERE occurred_at >= :start AND occurred_at < :end
SQL, $params);

        $noteCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM note WHERE deleted_at IS NULL');
        $focus = (int) $pomodoro['focusSeconds'];
        $break = (int) $pomodoro['breakSeconds'];
        $focusEfficiency = ($focus + $break) > 0 ? round(($focus / ($focus + $break)) * 100, 1) : 0.0;

        return [
            'pomodoroSessions' => (int) $pomodoro['sessions'],
            'focusSeconds' => $focus,
            'breakSeconds' => $break,
            'focusEfficiency' => $focusEfficiency,
            'tasksCompleted' => $tasksCompleted,
            'notesCreated' => $notesCreated,
            'noteCount' => $noteCount,
            'activeAppSeconds' => $activeSeconds,
        ];
    }

    /** @return list<array<string, int|string>> */
    private function daily(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $params = ['start' => $this->utc($start), 'end' => $this->utc($end), 'tz' => $this->timezone];
        $pomodoros = $this->pomodoroDaily($start, $end);
        $events = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT to_char(occurred_at AT TIME ZONE :tz, 'YYYY-MM-DD') AS day,
       COUNT(*) FILTER (WHERE event_type = 'TASK_COMPLETED') AS "tasksCompleted",
       COUNT(*) FILTER (WHERE event_type = 'NOTE_CREATED') AS "notesCreated"
FROM activity_event
WHERE occurred_at >= :start AND occurred_at < :end
GROUP BY day
SQL, $params);
        $usage = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT to_char(occurred_at AT TIME ZONE :tz, 'YYYY-MM-DD') AS day,
       COALESCE(SUM(active_seconds), 0) AS "activeAppSeconds"
FROM app_usage_slice
WHERE occurred_at >= :start AND occurred_at < :end
GROUP BY day
SQL, $params);

        $indexed = [];
        foreach ($pomodoros as $row) {
            $indexed[(string) $row['day']] = [
                'date' => (string) $row['day'],
                'pomodoroSessions' => (int) $row['sessions'],
                'focusSeconds' => (int) $row['focusSeconds'],
                'breakSeconds' => (int) $row['breakSeconds'],
                'tasksCompleted' => 0,
                'notesCreated' => 0,
                'activeAppSeconds' => 0,
            ];
        }
        foreach ($events as $row) {
            $day = (string) $row['day'];
            $indexed[$day] ??= $this->emptyDay($day);
            $indexed[$day]['tasksCompleted'] = (int) $row['tasksCompleted'];
            $indexed[$day]['notesCreated'] = (int) $row['notesCreated'];
        }
        foreach ($usage as $row) {
            $day = (string) $row['day'];
            $indexed[$day] ??= $this->emptyDay($day);
            $indexed[$day]['activeAppSeconds'] = (int) $row['activeAppSeconds'];
        }

        $days = [];
        for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $days[] = $indexed[$key] ?? $this->emptyDay($key);
        }

        return $days;
    }

    /** @return array{sessions:int,focusSeconds:int,breakSeconds:int} */
    private function pomodoroMetrics(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $sessions = $this->pomodoroRows($start, $end);
        $sessionCount = 0;
        $focusSeconds = 0;
        $breakSeconds = 0;

        foreach ($sessions as $session) {
            $startedAt = new \DateTimeImmutable((string) $session['startedAt']);
            if ($startedAt >= $start && $startedAt < $end) {
                ++$sessionCount;
            }

            [$focus, $break] = $this->pomodoroOverlap($session, $start, $end);
            $focusSeconds += $focus;
            $breakSeconds += $break;
        }

        return ['sessions' => $sessionCount, 'focusSeconds' => $focusSeconds, 'breakSeconds' => $breakSeconds];
    }

    /** @return list<array{day:string,sessions:int,focusSeconds:int,breakSeconds:int}> */
    private function pomodoroDaily(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $sessions = $this->pomodoroRows($start, $end);
        $timezone = new \DateTimeZone($this->timezone);
        $result = [];

        for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
            $nextDay = $day->modify('+1 day');
            $key = $day->format('Y-m-d');
            $sessionCount = 0;
            $focusSeconds = 0;
            $breakSeconds = 0;

            foreach ($sessions as $session) {
                $startedAt = (new \DateTimeImmutable((string) $session['startedAt']))->setTimezone($timezone);
                if ($startedAt >= $day && $startedAt < $nextDay) {
                    ++$sessionCount;
                }
                [$focus, $break] = $this->pomodoroOverlap($session, $day, $nextDay);
                $focusSeconds += $focus;
                $breakSeconds += $break;
            }

            if ($sessionCount > 0 || $focusSeconds > 0 || $breakSeconds > 0) {
                $result[] = [
                    'day' => $key,
                    'sessions' => $sessionCount,
                    'focusSeconds' => $focusSeconds,
                    'breakSeconds' => $breakSeconds,
                ];
            }
        }

        return $result;
    }

    /** @return list<array{workMinutes:mixed,startedAt:mixed,stoppedAt:mixed}> */
    private function pomodoroRows(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        /** @var list<array{workMinutes:mixed,startedAt:mixed,stoppedAt:mixed}> $rows */
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT work_minutes_snapshot AS "workMinutes", started_at AS "startedAt", stopped_at AS "stoppedAt"
FROM pomodoro_session
WHERE started_at < :end
  AND COALESCE(stopped_at, NOW()) > :start
ORDER BY started_at ASC
SQL, ['start' => $this->utc($start), 'end' => $this->utc($end)]);

        return $rows;
    }

    /**
     * @param array{workMinutes: mixed, startedAt: mixed, stoppedAt: mixed} $session
     *
     * @return array{0: int, 1: int}
     */
    private function pomodoroOverlap(array $session, \DateTimeImmutable $rangeStart, \DateTimeImmutable $rangeEnd): array
    {
        $startedAt = new \DateTimeImmutable((string) $session['startedAt']);
        $stoppedAt = $session['stoppedAt'] !== null ? new \DateTimeImmutable((string) $session['stoppedAt']) : new \DateTimeImmutable();
        $segmentStart = $startedAt > $rangeStart ? $startedAt : $rangeStart;
        $segmentEnd = $stoppedAt < $rangeEnd ? $stoppedAt : $rangeEnd;
        if ($segmentEnd <= $segmentStart) {
            return [0, 0];
        }

        $overlap = $this->pomodoroCalculator->overlap($startedAt, (int) $session['workMinutes'], $segmentStart, $segmentEnd);

        return [$overlap['focusSeconds'], $overlap['breakSeconds']];
    }

    /** @return array{labelName:string, count:int}|null */
    private function mostCompletedLabel(\DateTimeImmutable $start, \DateTimeImmutable $end): ?array
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
SELECT metadata->>'labelName' AS "labelName", COUNT(*) AS count
FROM activity_event
WHERE event_type = 'TASK_COMPLETED'
  AND occurred_at >= :start AND occurred_at < :end
  AND NULLIF(metadata->>'labelName', '') IS NOT NULL
GROUP BY metadata->>'labelName'
ORDER BY count DESC, "labelName" ASC
LIMIT 1
SQL, ['start' => $this->utc($start), 'end' => $this->utc($end)]);

        return $row === false ? null : ['labelName' => (string) $row['labelName'], 'count' => (int) $row['count']];
    }

    /**
     * @param array<string, int|float> $current
     * @param array<string, int|float> $previous
     * @return array<string, float|null>
     */
    private function changes(array $current, array $previous): array
    {
        $keys = ['pomodoroSessions', 'focusSeconds', 'tasksCompleted', 'notesCreated', 'activeAppSeconds', 'focusEfficiency'];
        $changes = [];
        foreach ($keys as $key) {
            $prev = (float) $previous[$key];
            $cur = (float) $current[$key];
            $changes[$key] = $prev == 0.0 ? ($cur == 0.0 ? 0.0 : null) : round((($cur - $prev) / abs($prev)) * 100, 1);
        }

        return $changes;
    }

    /** @return array{0:\DateTimeImmutable,1:\DateTimeImmutable} */
    private function range(string $month): array
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new \InvalidArgumentException('Month must use YYYY-MM format.');
        }

        $timezone = new \DateTimeZone($this->timezone);
        $start = new \DateTimeImmutable($month.'-01 00:00:00', $timezone);

        return [$start, $start->modify('+1 month')];
    }

    private function utc(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
    }

    /** @return array{date:string,pomodoroSessions:int,focusSeconds:int,breakSeconds:int,tasksCompleted:int,notesCreated:int,activeAppSeconds:int} */
    private function emptyDay(string $day): array
    {
        return [
            'date' => $day,
            'pomodoroSessions' => 0,
            'focusSeconds' => 0,
            'breakSeconds' => 0,
            'tasksCompleted' => 0,
            'notesCreated' => 0,
            'activeAppSeconds' => 0,
        ];
    }
}
