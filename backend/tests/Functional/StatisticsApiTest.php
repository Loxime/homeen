<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StatisticsApiTest extends WebTestCase
{
    private KernelBrowser $client;

    private Connection $connection;

    private int $userId;

    private string $email;

    private string $password;

    private string $csrfToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client =
            static::createClient();

        $this->client->disableReboot();

        $connection =
            static::getContainer()
                ->get(Connection::class);

        if (!$connection instanceof Connection) {
            throw new \LogicException(
                'Doctrine DBAL connection service is unavailable.',
            );
        }

        $this->connection = $connection;

        $this->connection
            ->beginTransaction();

        $this->password =
            'Statistics-Test-Password-123!';

        $this->email = sprintf(
            'statistics-api-%s@example.test',
            bin2hex(random_bytes(8)),
        );

        $userId =
            $this->connection->fetchOne(
                <<<'SQL'
INSERT INTO app_user (
    password_hash,
    must_change_password
)
VALUES (
    :passwordHash,
    FALSE
)
RETURNING id
SQL,
                [
                    'passwordHash' =>
                        password_hash(
                            $this->password,
                            PASSWORD_DEFAULT,
                        ),
                ],
            );

        self::assertNotFalse($userId);

        $this->userId = (int) $userId;

        $this->connection->insert(
            'user_email',
            [
                'user_id' =>
                    $this->userId,

                'email' =>
                    $this->email,

                'normalized_email' =>
                    mb_strtolower(
                        $this->email,
                    ),

                'is_primary' => true,
            ],
        );

        $this->authenticate();
    }

    protected function tearDown(): void
    {
        if (
            isset($this->connection)
            && $this->connection
                ->isTransactionActive()
        ) {
            $this->connection
                ->rollBack();
        }

        parent::tearDown();
    }

    public function testPeriodStatisticsAreAccurateAndUserScoped():
    void {
        $this->insertPomodoro(
            $this->userId,
            '2026-09-01 12:00:00+00',
            '2026-09-01 12:30:00+00',
        );

        $this->insertPomodoro(
            $this->userId,
            '2026-09-02 12:00:00+00',
            '2026-09-02 12:15:00+00',
        );

        $this->insertEvent(
            $this->userId,
            'TASK_COMPLETED',
            '2026-09-01 13:00:00+00',
            [
                'tags' => [
                    [
                        'name' => 'Focus',
                    ],
                ],
            ],
        );

        $this->insertEvent(
            $this->userId,
            'NOTE_CREATED',
            '2026-09-02 13:00:00+00',
        );

        $this->insertUsage(
            $this->userId,
            '2026-09-01 14:00:00+00',
            30,
        );

        $this->insertUsage(
            $this->userId,
            '2026-09-02 14:00:00+00',
            45,
        );

        /*
         * Foreign data must never contribute
         * to the authenticated user's stats.
         */
        $otherUserId =
            $this->createOtherUser();

        $this->insertPomodoro(
            $otherUserId,
            '2026-09-01 15:00:00+00',
            '2026-09-01 15:30:00+00',
        );

        $this->insertEvent(
            $otherUserId,
            'TASK_COMPLETED',
            '2026-09-01 16:00:00+00',
        );

        $this->insertUsage(
            $otherUserId,
            '2026-09-01 17:00:00+00',
            60,
        );

        $response =
            $this->jsonRequest(
                'GET',
                '/api/statistics'
                .'?start=2026-09-01'
                .'&end=2026-09-03'
                .'&compareStart=2026-08-29'
                .'&compareEnd=2026-08-31',
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            [
                'start' => '2026-09-01',
                'end' => '2026-09-03',
            ],
            $response['range'],
        );

        self::assertSame(
            2,
            $response['summary']
                ['pomodoroSessions'],
        );

        self::assertSame(
            2400,
            $response['summary']
                ['focusSeconds'],
        );

        self::assertSame(
            300,
            $response['summary']
                ['breakSeconds'],
        );

        self::assertSame(
            88.9,
            $response['summary']
                ['focusEfficiency'],
        );

        self::assertSame(
            1,
            $response['summary']
                ['tasksCompleted'],
        );

        self::assertSame(
            1,
            $response['summary']
                ['notesCreated'],
        );

        self::assertSame(
            75,
            $response['summary']
                ['activeAppSeconds'],
        );

        self::assertCount(
            3,
            $response['days'],
        );

        self::assertSame(
            '2026-09-01',
            $response['days'][0]['date'],
        );

        self::assertSame(
            1500,
            $response['days'][0]
                ['focusSeconds'],
        );

        self::assertSame(
            300,
            $response['days'][0]
                ['breakSeconds'],
        );

        self::assertSame(
            1,
            $response['days'][0]
                ['tasksCompleted'],
        );

        self::assertSame(
            30,
            $response['days'][0]
                ['activeAppSeconds'],
        );

        self::assertSame(
            '2026-09-02',
            $response['days'][1]['date'],
        );

        self::assertSame(
            900,
            $response['days'][1]
                ['focusSeconds'],
        );

        self::assertSame(
            1,
            $response['days'][1]
                ['notesCreated'],
        );

        self::assertSame(
            45,
            $response['days'][1]
                ['activeAppSeconds'],
        );

        self::assertSame(
            'Focus',
            $response['mostCompletedTag']
                ['tagName'],
        );

        self::assertSame(
            1,
            $response['mostCompletedTag']
                ['count'],
        );
    }

    public function testInvalidCalendarDateIsRejected():
    void {
        $response =
            $this->jsonRequest(
                'GET',
                '/api/statistics'
                .'?start=2026-02-31'
                .'&end=2026-03-01',
            );

        self::assertSame(
            422,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Dates must be valid calendar dates using YYYY-MM-DD format.',
            $response['error'],
        );
    }

    private function insertPomodoro(
        int $userId,
        string $startedAt,
        string $stoppedAt,
    ): void {
        $this->connection->insert(
            'pomodoro_session',
            [
                'user_id' => $userId,

                'work_minutes_snapshot' =>
                    25,

                'started_at' =>
                    $startedAt,

                'stopped_at' =>
                    $stoppedAt,

                'focus_seconds' => 0,
                'break_seconds' => 0,
            ],
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function insertEvent(
        int $userId,
        string $eventType,
        string $occurredAt,
        array $metadata = [],
    ): void {
        $this->connection->insert(
            'activity_event',
            [
                'user_id' => $userId,

                'event_type' =>
                    $eventType,

                'entity_type' =>
                    'test',

                'metadata' =>
                    json_encode(
                        $metadata,
                        JSON_THROW_ON_ERROR,
                    ),

                'occurred_at' =>
                    $occurredAt,
            ],
        );
    }

    private function insertUsage(
        int $userId,
        string $occurredAt,
        int $seconds,
    ): void {
        $sessionId =
            $this->connection->fetchOne(
                <<<'SQL'
INSERT INTO app_usage_session (
    user_id,
    started_at,
    last_seen_at,
    active_seconds
)
VALUES (
    :userId,
    :occurredAt,
    :occurredAt,
    :seconds
)
RETURNING id
SQL,
                [
                    'userId' => $userId,
                    'occurredAt' => $occurredAt,
                    'seconds' => $seconds,
                ],
            );

        self::assertNotFalse($sessionId);

        $this->connection->insert(
            'app_usage_slice',
            [
                'session_id' =>
                    (int) $sessionId,

                'active_seconds' =>
                    $seconds,

                'occurred_at' =>
                    $occurredAt,
            ],
        );
    }

    private function createOtherUser(): int
    {
        $id =
            $this->connection->fetchOne(
                <<<'SQL'
INSERT INTO app_user (
    password_hash,
    must_change_password
)
VALUES (
    :passwordHash,
    FALSE
)
RETURNING id
SQL,
                [
                    'passwordHash' =>
                        password_hash(
                            'Other-Statistics-Password-123!',
                            PASSWORD_DEFAULT,
                        ),
                ],
            );

        self::assertNotFalse($id);

        return (int) $id;
    }

    private function authenticate(): void
    {
        $this->client->request(
            'GET',
            '/api/access/status',
        );

        self::assertResponseIsSuccessful();

        $status =
            $this->responseData();

        self::assertArrayHasKey(
            'csrfToken',
            $status,
        );

        $this->csrfToken =
            (string) $status['csrfToken'];

        $login =
            $this->jsonRequest(
                'POST',
                '/api/auth/login',
                [
                    'email' =>
                        $this->email,

                    'password' =>
                        $this->password,
                ],
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertTrue(
            $login['authenticated'],
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function jsonRequest(
        string $method,
        string $uri,
        array $payload = [],
    ): array {
        $this->client->request(
            $method,
            $uri,
            [],
            [],
            [
                'CONTENT_TYPE' =>
                    'application/json',

                'HTTP_ACCEPT' =>
                    'application/json',

                'HTTP_X_CSRF_TOKEN' =>
                    $this->csrfToken
                    ?? '',
            ],
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR,
            ),
        );

        return $this->responseData();
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(): array
    {
        $content =
            $this->client
                ->getResponse()
                ->getContent();

        self::assertNotFalse(
            $content,
        );

        $data = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($data);

        return $data;
    }
}
