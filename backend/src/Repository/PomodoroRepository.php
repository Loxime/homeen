<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ActivityLogger;
use App\Service\CurrentUser;
use App\Service\PomodoroCalculator;
use App\Service\PomodoroInsightCalculator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Knp\Component\Pager\PaginatorInterface;

final readonly class PomodoroRepository
{
    private const HISTORY_PAGE_SIZE = 20;

    public function __construct(
        private Connection $connection,
        private ActivityLogger $logger,
        private PomodoroCalculator $calculator,
        private PomodoroInsightCalculator $insightCalculator,
        private CurrentUser $currentUser,
        private PaginatorInterface $paginator,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function presets(): array
    {
        $rows = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    id,
    work_minutes AS "workMinutes",
    created_at AS "createdAt",
    last_used_at AS "lastUsedAt"
FROM pomodoro_preset
WHERE user_id = :userId
ORDER BY last_used_at DESC,
         work_minutes ASC
SQL,
                [
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );

        return array_map(
            static function (
                array $row,
            ): array {
                $row['id'] =
                    (int) $row['id'];

                $row['workMinutes'] =
                    (int) $row['workMinutes'];

                return $row;
            },
            $rows,
        );
    }

    /** @return array<string, mixed> */
    public function start(
        int $workMinutes,
    ): array {
        if ($workMinutes < 5) {
            throw new \InvalidArgumentException(
                'Work duration must be at least 5 minutes.'
            );
        }

        $userId = $this->currentUser->id();

        return $this->connection
            ->transactional(
                function () use (
                    $workMinutes,
                    $userId,
                ): array {
                    $active = $this->connection
                        ->fetchOne(
                            <<<'SQL'
SELECT 1
FROM pomodoro_session
WHERE user_id = :userId
  AND stopped_at IS NULL
LIMIT 1
SQL,
                            [
                                'userId' =>
                                    $userId,
                            ],
                        );

                    if ($active !== false) {
                        throw new \DomainException(
                            'A Pomodoro session is already running.'
                        );
                    }

                    $preset = $this->connection
                        ->fetchAssociative(
                            <<<'SQL'
INSERT INTO pomodoro_preset (
    user_id,
    work_minutes
)
VALUES (
    :userId,
    :minutes
)
ON CONFLICT (
    user_id,
    work_minutes
)
WHERE user_id IS NOT NULL
DO UPDATE SET
    last_used_at = NOW()
RETURNING
    id,
    work_minutes AS "workMinutes",
    created_at AS "createdAt",
    last_used_at AS "lastUsedAt"
SQL,
                            [
                                'userId' =>
                                    $userId,
                                'minutes' =>
                                    $workMinutes,
                            ],
                        );

                    if ($preset === false) {
                        throw new \RuntimeException(
                            'Unable to save Pomodoro preset.'
                        );
                    }

                    try {
                        $row = $this
                            ->connection
                            ->fetchAssociative(
                                <<<'SQL'
INSERT INTO pomodoro_session (
    user_id,
    preset_id,
    work_minutes_snapshot
)
VALUES (
    :userId,
    :presetId,
    :minutes
)
RETURNING
    id,
    preset_id AS "presetId",
    work_minutes_snapshot AS "workMinutes",
    started_at AS "startedAt",
    stopped_at AS "stoppedAt",
    focus_seconds AS "focusSeconds",
    break_seconds AS "breakSeconds"
SQL,
                                [
                                    'userId' =>
                                        $userId,
                                    'presetId' =>
                                        (int) $preset['id'],
                                    'minutes' =>
                                        $workMinutes,
                                ],
                            );
                    } catch (
                        UniqueConstraintViolationException
                    ) {
                        throw new \DomainException(
                            'A Pomodoro session is already running.'
                        );
                    }

                    if ($row === false) {
                        throw new \RuntimeException(
                            'Unable to start Pomodoro session.'
                        );
                    }

                    $id = (int) $row['id'];

                    $this->logger->log(
                        'POMODORO_STARTED',
                        'pomodoro_session',
                        $id,
                        [
                            'workMinutes' =>
                                $workMinutes,
                        ],
                    );

                    return $this->withLiveState(
                        $row
                    );
                },
            );
    }

    /** @return array<string, mixed> */
    public function quickStart(): array
    {
        $minutes = $this->connection
            ->fetchOne(
                <<<'SQL'
SELECT work_minutes
FROM pomodoro_preset
WHERE user_id = :userId
ORDER BY last_used_at DESC
LIMIT 1
SQL,
                [
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );

        if ($minutes === false) {
            throw new \OutOfBoundsException(
                'No saved Pomodoro preset exists yet.'
            );
        }

        return $this->start(
            (int) $minutes
        );
    }

    /** @return array<string, mixed>|null */
    public function active(): ?array
    {
        $userId = $this->currentUser->id();

        $row = $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT
    id,
    preset_id AS "presetId",
    work_minutes_snapshot AS "workMinutes",
    started_at AS "startedAt",
    stopped_at AS "stoppedAt",
    focus_seconds AS "focusSeconds",
    break_seconds AS "breakSeconds"
FROM pomodoro_session
WHERE user_id = :userId
  AND stopped_at IS NULL
ORDER BY started_at DESC
LIMIT 1
SQL,
                [
                    'userId' =>
                        $userId,
                ],
            );

        if ($row === false) {
            return null;
        }

        $row = $this->withLiveState(
            $row
        );

        $this->connection->update(
            'pomodoro_session',
            [
                'focus_seconds' =>
                    $row['focusSeconds'],
                'break_seconds' =>
                    $row['breakSeconds'],
            ],
            [
                'id' =>
                    (int) $row['id'],
                'user_id' =>
                    $userId,
            ],
        );

        return $row;
    }

    /** @return array<string, mixed> */
    public function stop(int $id): array
    {
        $userId = $this->currentUser->id();

        $row = $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT
    id,
    preset_id AS "presetId",
    work_minutes_snapshot AS "workMinutes",
    started_at AS "startedAt",
    stopped_at AS "stoppedAt",
    focus_seconds AS "focusSeconds",
    break_seconds AS "breakSeconds"
FROM pomodoro_session
WHERE id = :id
  AND user_id = :userId
SQL,
                [
                    'id' => $id,
                    'userId' => $userId,
                ],
            );

        if ($row === false) {
            throw new \OutOfBoundsException(
                'Pomodoro session not found.'
            );
        }

        if ($row['stoppedAt'] !== null) {
            return $this
                ->withStoredSummary($row);
        }

        $row = $this->normalizeSession(
            $row
        );

        $now = new \DateTimeImmutable();

        $state = $this->calculator->state(
            new \DateTimeImmutable(
                (string) $row['startedAt']
            ),
            (int) $row['workMinutes'],
            $now,
        );

        $this->connection->update(
            'pomodoro_session',
            [
                'stopped_at' =>
                    $now->format(
                        'Y-m-d H:i:sP'
                    ),
                'focus_seconds' =>
                    $state['focusSeconds'],
                'break_seconds' =>
                    $state['breakSeconds'],
            ],
            [
                'id' => $id,
                'user_id' => $userId,
            ],
        );

        $row['stoppedAt'] =
            $now->format(DATE_ATOM);

        $row['focusSeconds'] =
            $state['focusSeconds'];

        $row['breakSeconds'] =
            $state['breakSeconds'];

        $this->logger->log(
            'POMODORO_STOPPED',
            'pomodoro_session',
            $id,
            [
                'workMinutes' =>
                    (int) $row[
                        'workMinutes'
                    ],
                'focusSeconds' =>
                    $state[
                        'focusSeconds'
                    ],
                'breakSeconds' =>
                    $state[
                        'breakSeconds'
                    ],
                'completedWorkCycles' =>
                    $state[
                        'completedWorkCycles'
                    ],
                'completedBreakCycles' =>
                    $state[
                        'completedBreakCycles'
                    ],
            ],
        );

        return array_merge(
            $row,
            $state,
            [
                'isActive' => false,
            ],
        );
    }

    /** @return array<string, mixed> */
    public function insights(): array
    {
        $userId =
            $this->currentUser->id();

        $totalFocusSeconds =
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COALESCE(
    SUM(focus_seconds),
    0
)
FROM pomodoro_session
WHERE user_id = :userId
SQL,
                    [
                        'userId' => $userId,
                    ],
                );

        $rows = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    work_minutes_snapshot
        AS "workMinutes",
    focus_rating AS rating
FROM pomodoro_session
WHERE user_id = :userId
  AND stopped_at IS NOT NULL
  AND focus_rating IS NOT NULL
ORDER BY
    rated_at DESC,
    id DESC
LIMIT 20
SQL,
                [
                    'userId' => $userId,
                ],
            );

        $ratedSessions =
            array_map(
                static fn (
                    array $row,
                ): array => [
                    'workMinutes' =>
                        (int) $row[
                            'workMinutes'
                        ],

                    'rating' =>
                        (int) $row[
                            'rating'
                        ],
                ],
                $rows,
            );

        return $this
            ->insightCalculator
            ->calculate(
                $totalFocusSeconds,
                $ratedSessions,
            );
    }

    /** @return array<string, mixed> */
    public function rate(
        int $id,
        int $rating,
    ): array {
        if (
            $rating < 1
            || $rating > 3
        ) {
            throw new \InvalidArgumentException(
                'Rating must be between 1 and 3.'
            );
        }

        $userId =
            $this->currentUser->id();

        $row = $this->connection
            ->fetchAssociative(
                <<<'SQL'
UPDATE pomodoro_session
SET
    focus_rating = :rating,
    rated_at = NOW()
WHERE id = :id
  AND user_id = :userId
  AND stopped_at IS NOT NULL
RETURNING
    id,
    work_minutes_snapshot
        AS "workMinutes",
    started_at AS "startedAt",
    stopped_at AS "stoppedAt",
    focus_seconds AS "focusSeconds",
    break_seconds AS "breakSeconds",
    focus_rating AS "focusRating",
    rated_at AS "ratedAt"
SQL,
                [
                    'id' => $id,
                    'userId' => $userId,
                    'rating' => $rating,
                ],
            );

        if ($row === false) {
            throw new \OutOfBoundsException(
                'Completed Pomodoro session not found.'
            );
        }

        $this->logger->log(
            'POMODORO_RATED',
            'pomodoro_session',
            $id,
            [
                'rating' => $rating,
            ],
        );

        return $this->normalizeSession(
            $row
        );
    }

    /**
     * @return array{
     *     sessions: list<array<string, mixed>>,
     *     pagination: array{
     *         page: int,
     *         limit: int,
     *         total: int,
     *         pageCount: int,
     *         hasPrevious: bool,
     *         hasNext: bool
     *     }
     * }
     */
    public function history(
        int $page = 1,
    ): array {
        $page = max(
            1,
            $page,
        );

        $query = $this->connection
            ->createQueryBuilder()
            ->select(
                'p.id',
                'p.work_minutes_snapshot AS "workMinutes"',
                'p.started_at AS "startedAt"',
                'p.stopped_at AS "stoppedAt"',
                'p.focus_seconds AS "focusSeconds"',
                'p.break_seconds AS "breakSeconds"',
                'p.focus_rating AS "focusRating"',
                'p.rated_at AS "ratedAt"',
            )
            ->from(
                'pomodoro_session',
                'p',
            )
            ->where(
                'p.user_id = :userId',
            )
            ->setParameter(
                'userId',
                $this->currentUser->id(),
            )
            ->orderBy(
                'p.started_at',
                'DESC',
            )
            ->addOrderBy(
                'p.id',
                'DESC',
            );

        $pagination =
            $this->paginator->paginate(
                $query,
                $page,
                self::HISTORY_PAGE_SIZE,
                [
                    PaginatorInterface::PAGE_OUT_OF_RANGE =>
                        PaginatorInterface::PAGE_OUT_OF_RANGE_FIX,
                ],
            );

        $sessions = [];

        foreach (
            $pagination->getItems()
            as $row
        ) {
            if (!is_array($row)) {
                throw new \LogicException(
                    'Unexpected Pomodoro pagination row.',
                );
            }

            $sessions[] =
                $this->normalizeSession(
                    $row,
                );
        }

        $total =
            $pagination
                ->getTotalItemCount();

        $pageCount = max(
            1,
            (int) ceil(
                $total
                / self::HISTORY_PAGE_SIZE,
            ),
        );

        $currentPage =
            $total === 0
                ? 1
                : $pagination
                    ->getCurrentPageNumber();

        return [
            'sessions' => $sessions,

            'pagination' => [
                'page' => $currentPage,
                'limit' =>
                    self::HISTORY_PAGE_SIZE,
                'total' => $total,
                'pageCount' => $pageCount,

                'hasPrevious' =>
                    $currentPage > 1,

                'hasNext' =>
                    $total > 0
                    && $currentPage
                        < $pageCount,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeSession(
        array $row,
    ): array {
        $row['id'] =
            (int) $row['id'];

        if (
            array_key_exists(
                'presetId',
                $row,
            )
        ) {
            $row['presetId'] =
                $row['presetId'] !== null
                    ? (int) $row['presetId']
                    : null;
        }

        $row['workMinutes'] =
            (int) $row['workMinutes'];

        $row['focusSeconds'] =
            (int) $row['focusSeconds'];

        $row['breakSeconds'] =
            (int) $row['breakSeconds'];

        if (
            array_key_exists(
                'focusRating',
                $row,
            )
        ) {
            $row['focusRating'] =
                $row['focusRating'] !== null
                    ? (int) $row['focusRating']
                    : null;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function withLiveState(
        array $row,
    ): array {
        $row = $this->normalizeSession(
            $row
        );

        $state = $this->calculator->state(
            new \DateTimeImmutable(
                (string) $row['startedAt']
            ),
            (int) $row['workMinutes'],
        );

        return array_merge(
            $row,
            $state,
            [
                'isActive' => true,
            ],
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function withStoredSummary(
        array $row,
    ): array {
        $row = $this->normalizeSession(
            $row
        );

        $workSeconds =
            (int) $row['workMinutes']
            * 60;

        $breakSeconds =
            PomodoroCalculator::BREAK_MINUTES
            * 60;

        $focus =
            (int) $row['focusSeconds'];

        $break =
            (int) $row['breakSeconds'];

        return array_merge(
            $row,
            [
                'phase' => null,
                'remainingSeconds' => 0,

                'completedWorkCycles' =>
                    intdiv(
                        $focus,
                        $workSeconds,
                    ),

                'completedBreakCycles' =>
                    intdiv(
                        $break,
                        $breakSeconds,
                    ),

                'isActive' => false,
            ],
        );
    }
}
