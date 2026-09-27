<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PomodoroApiTest extends WebTestCase
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

        $this->client
            ->disableReboot();

        $connection =
            static::getContainer()
                ->get(
                    Connection::class,
                );

        if (
            !$connection
            instanceof Connection
        ) {
            throw new \LogicException(
                'Doctrine DBAL connection service is unavailable.',
            );
        }

        $this->connection =
            $connection;

        $this->connection
            ->beginTransaction();

        $this->password =
            'Pomodoro-Test-Password-123!';

        $this->email = sprintf(
            'pomodoro-api-%s@example.test',
            bin2hex(
                random_bytes(8),
            ),
        );

        $userId =
            $this->connection
                ->fetchOne(
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

        self::assertNotFalse(
            $userId,
        );

        $this->userId =
            (int) $userId;

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

                'is_primary' =>
                    true,
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

    public function testHistoryIsPaginatedNewestFirst():
    void {
        $ids = [];

        $start =
            new \DateTimeImmutable(
                '2026-09-01T08:00:00+00:00',
            );

        for (
            $index = 1;
            $index <= 45;
            ++$index
        ) {
            $startedAt =
                $start->modify(
                    sprintf(
                        '+%d minutes',
                        $index,
                    ),
                );

            $stoppedAt =
                $startedAt->modify(
                    '+25 minutes',
                );

            $id =
                $this->connection
                    ->fetchOne(
                        <<<'SQL'
INSERT INTO pomodoro_session (
    user_id,
    work_minutes_snapshot,
    started_at,
    stopped_at,
    focus_seconds,
    break_seconds
)
VALUES (
    :userId,
    25,
    :startedAt,
    :stoppedAt,
    1500,
    0
)
RETURNING id
SQL,
                        [
                            'userId' =>
                                $this->userId,

                            'startedAt' =>
                                $startedAt->format(
                                    'Y-m-d H:i:sP',
                                ),

                            'stoppedAt' =>
                                $stoppedAt->format(
                                    'Y-m-d H:i:sP',
                                ),
                        ],
                    );

            self::assertNotFalse(
                $id,
            );

            $ids[] = (int) $id;
        }

        $newestFirst =
            array_reverse($ids);

        $firstPage =
            $this->jsonRequest(
                'GET',
                '/api/pomodoro/history?page=1&limit=200',
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertCount(
            20,
            $firstPage['sessions'],
        );

        self::assertSame(
            array_slice(
                $newestFirst,
                0,
                20,
            ),
            array_column(
                $firstPage['sessions'],
                'id',
            ),
        );

        self::assertSame(
            [
                'page' => 1,
                'limit' => 20,
                'total' => 45,
                'pageCount' => 3,
                'hasPrevious' => false,
                'hasNext' => true,
            ],
            $firstPage['pagination'],
        );

        $secondPage =
            $this->jsonRequest(
                'GET',
                '/api/pomodoro/history?page=2',
            );

        self::assertCount(
            20,
            $secondPage['sessions'],
        );

        self::assertSame(
            array_slice(
                $newestFirst,
                20,
                20,
            ),
            array_column(
                $secondPage['sessions'],
                'id',
            ),
        );

        self::assertSame(
            2,
            $secondPage[
                'pagination'
            ]['page'],
        );

        self::assertTrue(
            $secondPage[
                'pagination'
            ]['hasPrevious'],
        );

        self::assertTrue(
            $secondPage[
                'pagination'
            ]['hasNext'],
        );

        $thirdPage =
            $this->jsonRequest(
                'GET',
                '/api/pomodoro/history?page=3',
            );

        self::assertCount(
            5,
            $thirdPage['sessions'],
        );

        self::assertSame(
            array_slice(
                $newestFirst,
                40,
                5,
            ),
            array_column(
                $thirdPage['sessions'],
                'id',
            ),
        );

        self::assertSame(
            [
                'page' => 3,
                'limit' => 20,
                'total' => 45,
                'pageCount' => 3,
                'hasPrevious' => true,
                'hasNext' => false,
            ],
            $thirdPage['pagination'],
        );

        /*
         * KNP fixes pages beyond the end
         * to the last available page.
         */
        $outOfRange =
            $this->jsonRequest(
                'GET',
                '/api/pomodoro/history?page=99',
            );

        self::assertSame(
            3,
            $outOfRange[
                'pagination'
            ]['page'],
        );

        self::assertCount(
            5,
            $outOfRange['sessions'],
        );
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

        self::assertIsArray(
            $data,
        );

        return $data;
    }
}
