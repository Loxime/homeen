<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TaskApiTest extends WebTestCase
{
    private KernelBrowser $client;

    private Connection $connection;

    private int $userId;

    private int $noteId;

    private string $email;

    private string $password;

    private string $csrfToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $connection = static::getContainer()
            ->get(Connection::class);

        if (!$connection instanceof Connection) {
            throw new \LogicException(
                'Doctrine DBAL connection service is unavailable.'
            );
        }

        $this->connection = $connection;
        $this->connection->beginTransaction();

        $this->password =
            'Functional-Test-Password-123!';

        $this->email = sprintf(
            'task-api-%s@example.test',
            bin2hex(random_bytes(8)),
        );

        $userId = $this->connection->fetchOne(
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
                'user_id' => $this->userId,
                'email' => $this->email,
                'normalized_email' =>
                    mb_strtolower($this->email),
                'is_primary' => true,
            ],
        );

        $noteId = $this->connection->fetchOne(
            <<<'SQL'
INSERT INTO note (
    user_id,
    title,
    content
)
VALUES (
    :userId,
    'Functional task test',
    ''
)
RETURNING id
SQL,
            [
                'userId' => $this->userId,
            ],
        );

        self::assertNotFalse($noteId);

        $this->noteId = (int) $noteId;

        $this->authenticate();
    }

    protected function tearDown(): void
    {
        if (
            isset($this->connection)
            && $this->connection
                ->isTransactionActive()
        ) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testTaskDefaultsAndMultipleTags(): void
    {
        $firstTag = $this->createTag(
            'Backend',
            '#3366FF',
        );

        $secondTag = $this->createTag(
            'Important',
            '#FF6600',
        );

        $defaultTask = $this->jsonRequest(
            'POST',
            sprintf(
                '/api/notes/%d/tasks',
                $this->noteId,
            ),
            [
                'content' => 'Default task',
            ],
        );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'normal',
            $defaultTask['priority'],
        );

        self::assertSame(
            'todo',
            $defaultTask['status'],
        );

        self::assertSame(
            0,
            $defaultTask['position'],
        );

        self::assertFalse(
            $defaultTask['isCompleted'],
        );

        self::assertNull(
            $defaultTask['completedAt'],
        );

        self::assertNull(
            $defaultTask['startDate'],
        );

        self::assertNull(
            $defaultTask['dueDate'],
        );

        self::assertSame(
            [],
            $defaultTask['tags'],
        );

        $taggedTask = $this->jsonRequest(
            'POST',
            sprintf(
                '/api/notes/%d/tasks',
                $this->noteId,
            ),
            [
                'content' =>
                    'Tagged task',

                'priority' =>
                    'high',

                'status' =>
                    'in_progress',

                'tagIds' => [
                    $firstTag['id'],
                    $secondTag['id'],
                ],
            ],
        );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'high',
            $taggedTask['priority'],
        );

        self::assertSame(
            'in_progress',
            $taggedTask['status'],
        );

        self::assertSame(
            1,
            $taggedTask['position'],
        );

        self::assertFalse(
            $taggedTask['isCompleted'],
        );

        self::assertCount(
            2,
            $taggedTask['tags'],
        );

        $tagIds = array_column(
            $taggedTask['tags'],
            'id',
        );

        sort($tagIds);

        $expected = [
            $firstTag['id'],
            $secondTag['id'],
        ];

        sort($expected);

        self::assertSame(
            $expected,
            $tagIds,
        );
    }

    public function testUpdateSynchronizesStatusAndCompletedState(): void
    {
        $tag = $this->createTag(
            'Focus',
            '#22AA88',
        );

        $task = $this->jsonRequest(
            'POST',
            sprintf(
                '/api/notes/%d/tasks',
                $this->noteId,
            ),
            [
                'content' =>
                    'Finish API',

                'priority' =>
                    'urgent',

                'status' =>
                    'in_progress',

                'tagIds' => [
                    $tag['id'],
                ],
            ],
        );

        $updated = $this->jsonRequest(
            'PUT',
            sprintf(
                '/api/tasks/%d',
                $task['id'],
            ),
            [
                'status' => 'done',
            ],
        );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'done',
            $updated['status'],
        );

        self::assertTrue(
            $updated['isCompleted'],
        );

        self::assertNotNull(
            $updated['completedAt'],
        );

        self::assertSame(
            'urgent',
            $updated['priority'],
        );

        self::assertSame(
            'Finish API',
            $updated['content'],
        );

        self::assertCount(
            1,
            $updated['tags'],
        );

        self::assertSame(
            $tag['id'],
            $updated['tags'][0]['id'],
        );

        $legacyUpdated = $this->jsonRequest(
            'PUT',
            sprintf(
                '/api/tasks/%d/completed',
                $task['id'],
            ),
            [
                'completed' => false,
            ],
        );

        self::assertSame(
            'todo',
            $legacyUpdated['status'],
        );

        self::assertFalse(
            $legacyUpdated['isCompleted'],
        );

        self::assertNull(
            $legacyUpdated['completedAt'],
        );

        self::assertSame(
            'urgent',
            $legacyUpdated['priority'],
        );

        self::assertSame(
            $tag['id'],
            $legacyUpdated['tags'][0]['id'],
        );
    }

    public function testTaskRejectsAnotherUsersTag(): void
    {
        $otherUserId =
            $this->createOtherUser();

        $foreignTagId =
            $this->connection->fetchOne(
                <<<'SQL'
INSERT INTO tag (
    user_id,
    name,
    color
)
VALUES (
    :userId,
    'Foreign',
    '#123456'
)
RETURNING id
SQL,
                [
                    'userId' =>
                        $otherUserId,
                ],
            );

        self::assertNotFalse(
            $foreignTagId,
        );

        $response = $this->jsonRequest(
            'POST',
            sprintf(
                '/api/notes/%d/tasks',
                $this->noteId,
            ),
            [
                'content' =>
                    'Forbidden foreign tag',

                'tagIds' => [
                    (int) $foreignTagId,
                ],
            ],
        );

        self::assertSame(
            422,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'One or more selected tags do not exist.',
            $response['error'],
        );
    }

    public function testTaskDatesCanBeCreatedUpdatedClearedAndValidated(): void
    {
        $task = $this->jsonRequest(
            'POST',
            sprintf(
                '/api/notes/%d/tasks',
                $this->noteId,
            ),
            [
                'content' =>
                    'Scheduled task',

                'startDate' =>
                    '2026-10-01',

                'dueDate' =>
                    '2026-10-15',
            ],
        );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            '2026-10-01',
            $task['startDate'],
        );

        self::assertSame(
            '2026-10-15',
            $task['dueDate'],
        );

        /*
         * Fields omitted from a partial update
         * must retain their current values.
         */
        $preserved = $this->jsonRequest(
            'PUT',
            sprintf(
                '/api/tasks/%d',
                $task['id'],
            ),
            [
                'priority' => 'high',
            ],
        );

        self::assertSame(
            'high',
            $preserved['priority'],
        );

        self::assertSame(
            '2026-10-01',
            $preserved['startDate'],
        );

        self::assertSame(
            '2026-10-15',
            $preserved['dueDate'],
        );

        /*
         * Explicit null removes a date.
         */
        $cleared = $this->jsonRequest(
            'PUT',
            sprintf(
                '/api/tasks/%d',
                $task['id'],
            ),
            [
                'startDate' => null,
                'dueDate' => null,
            ],
        );

        self::assertNull(
            $cleared['startDate'],
        );

        self::assertNull(
            $cleared['dueDate'],
        );

        $invalidFormat =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/tasks/%d',
                    $task['id'],
                ),
                [
                    'startDate' =>
                        '01/10/2026',
                ],
            );

        self::assertSame(
            422,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'startDate must use YYYY-MM-DD.',
            $invalidFormat['error'],
        );

        $invalidRange =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/tasks/%d',
                    $task['id'],
                ),
                [
                    'startDate' =>
                        '2026-10-20',

                    'dueDate' =>
                        '2026-10-10',
                ],
            );

        self::assertSame(
            422,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Task due date cannot be before start date.',
            $invalidRange['error'],
        );
    }

    public function testGlobalSearchFindsNotesTasksAndTags(): void
    {
        $tag = $this->createTag(
            'SearchNeedleTag',
            '#4455CC',
        );

        $contentTask = $this->jsonRequest(
            'POST',
            sprintf(
                '/api/notes/%d/tasks',
                $this->noteId,
            ),
            [
                'content' =>
                    'Unique searchable task content',
            ],
        );

        $taggedTask = $this->jsonRequest(
            'POST',
            sprintf(
                '/api/notes/%d/tasks',
                $this->noteId,
            ),
            [
                'content' =>
                    'Ordinary tagged task',

                'tagIds' => [
                    $tag['id'],
                ],
            ],
        );

        $noteSearch = $this->jsonRequest(
            'GET',
            '/api/search?q=Functional%20task%20test',
        );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertCount(
            1,
            $noteSearch['notes'],
        );

        self::assertSame(
            $this->noteId,
            $noteSearch['notes'][0]['id'],
        );

        $taskSearch = $this->jsonRequest(
            'GET',
            '/api/search?q=Unique%20searchable',
        );

        self::assertCount(
            1,
            $taskSearch['tasks'],
        );

        self::assertSame(
            $contentTask['id'],
            $taskSearch['tasks'][0]['id'],
        );

        self::assertSame(
            $this->noteId,
            $taskSearch['tasks'][0]['noteId'],
        );

        $tagSearch = $this->jsonRequest(
            'GET',
            '/api/search?q=SearchNeedleTag',
        );

        self::assertCount(
            1,
            $tagSearch['tasks'],
        );

        self::assertSame(
            $taggedTask['id'],
            $tagSearch['tasks'][0]['id'],
        );

        $emptySearch = $this->jsonRequest(
            'GET',
            '/api/search',
        );

        self::assertSame(
            [],
            $emptySearch['notes'],
        );

        self::assertSame(
            [],
            $emptySearch['tasks'],
        );
    }

    public function testInvalidPriorityAndStatusAreRejected(): void
    {
        $invalidPriority =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/notes/%d/tasks',
                    $this->noteId,
                ),
                [
                    'content' =>
                        'Invalid priority',

                    'priority' =>
                        'critical',
                ],
            );

        self::assertSame(
            422,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Unknown task priority.',
            $invalidPriority['error'],
        );

        $invalidStatus =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/notes/%d/tasks',
                    $this->noteId,
                ),
                [
                    'content' =>
                        'Invalid status',

                    'status' =>
                        'automatic',
                ],
            );

        self::assertSame(
            422,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Unknown task status.',
            $invalidStatus['error'],
        );
    }

    public function testDuplicatingNotePreservesTaskMetadataButResetsStatus(): void
    {
        $tag = $this->createTag(
            'Duplicated',
            '#8844CC',
        );

        $task = $this->jsonRequest(
            'POST',
            sprintf(
                '/api/notes/%d/tasks',
                $this->noteId,
            ),
            [
                'content' =>
                    'Duplicated task',

                'priority' =>
                    'urgent',

                'status' =>
                    'done',

                'position' =>
                    7,

                'startDate' =>
                    '2026-11-03',

                'dueDate' =>
                    '2026-11-21',

                'tagIds' => [
                    $tag['id'],
                ],
            ],
        );

        self::assertTrue(
            $task['isCompleted'],
        );

        $duplicate =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/notes/%d/duplicate',
                    $this->noteId,
                ),
            );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertCount(
            1,
            $duplicate['tasks'],
        );

        $duplicatedTask =
            $duplicate['tasks'][0];

        self::assertSame(
            'Duplicated task',
            $duplicatedTask['content'],
        );

        self::assertSame(
            'urgent',
            $duplicatedTask['priority'],
        );

        self::assertSame(
            7,
            $duplicatedTask['position'],
        );

        self::assertSame(
            '2026-11-03',
            $duplicatedTask['startDate'],
        );

        self::assertSame(
            '2026-11-21',
            $duplicatedTask['dueDate'],
        );

        self::assertSame(
            'todo',
            $duplicatedTask['status'],
        );

        self::assertFalse(
            $duplicatedTask['isCompleted'],
        );

        self::assertNull(
            $duplicatedTask['completedAt'],
        );

        self::assertCount(
            1,
            $duplicatedTask['tags'],
        );

        self::assertSame(
            $tag['id'],
            $duplicatedTask['tags'][0]['id'],
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

        $login = $this->jsonRequest(
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
     * @return array<string, mixed>
     */
    private function createTag(
        string $name,
        string $color,
    ): array {
        $tag = $this->jsonRequest(
            'POST',
            '/api/tags',
            [
                'name' => $name,
                'color' => $color,
            ],
        );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        return $tag;
    }

    private function createOtherUser(): int
    {
        $id = $this->connection->fetchOne(
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
                        'Other-User-Password-123!',
                        PASSWORD_DEFAULT,
                    ),
            ],
        );

        self::assertNotFalse($id);

        return (int) $id;
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
                    $this->csrfToken ?? '',
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
        $content = $this->client
            ->getResponse()
            ->getContent();

        self::assertNotFalse($content);

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
