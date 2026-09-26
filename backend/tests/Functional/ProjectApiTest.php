<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjectApiTest extends WebTestCase
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
            'Project-Test-Password-123!';

        $this->email = sprintf(
            'project-api-%s@example.test',
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

    public function testProjectLifecycleAndMembership():
    void {
        $created =
            $this->jsonRequest(
                'POST',
                '/api/projects',
                [
                    'name' =>
                        'Sprint 3',

                    'description' =>
                        'Project backend foundation',

                    'color' =>
                        '#a1b2c3',
                ],
            );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Sprint 3',
            $created['name'],
        );

        self::assertSame(
            'Project backend foundation',
            $created['description'],
        );

        self::assertSame(
            '#A1B2C3',
            $created['color'],
        );

        self::assertSame(
            'owner',
            $created['role'],
        );

        self::assertSame(
            1,
            $created['memberCount'],
        );

        self::assertSame(
            0,
            $created['noteCount'],
        );

        self::assertNull(
            $created['archivedAt'],
        );

        $projectId =
            (int) $created['id'];

        self::assertSame(
            'owner',
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT role
FROM project_member
WHERE project_id = :projectId
  AND user_id = :userId
SQL,
                    [
                        'projectId' =>
                            $projectId,

                        'userId' =>
                            $this->userId,
                    ],
                ),
        );

        $list =
            $this->jsonRequest(
                'GET',
                '/api/projects',
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertCount(
            1,
            $list['projects'],
        );

        self::assertSame(
            $projectId,
            $list['projects'][0]['id'],
        );

        $detail =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/projects/%d',
                    $projectId,
                ),
            );

        self::assertSame(
            $projectId,
            $detail['id'],
        );

        $members =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/projects/%d/members',
                    $projectId,
                ),
            );

        self::assertCount(
            1,
            $members['members'],
        );

        self::assertSame(
            $this->userId,
            $members[
                'members'
            ][0]['userId'],
        );

        self::assertSame(
            $this->email,
            $members[
                'members'
            ][0]['email'],
        );

        self::assertSame(
            'owner',
            $members[
                'members'
            ][0]['role'],
        );

        /*
         * Attach a note directly for now.
         * Note APIs will gain projectId during
         * the next compatibility checkpoint.
         */
        $noteId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO note (
    user_id,
    project_id,
    title,
    content
)
VALUES (
    :userId,
    :projectId,
    'Project note',
    ''
)
RETURNING id
SQL,
                    [
                        'userId' =>
                            $this->userId,

                        'projectId' =>
                            $projectId,
                    ],
                );

        self::assertNotFalse(
            $noteId,
        );

        $afterNote =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/projects/%d',
                    $projectId,
                ),
            );

        self::assertSame(
            1,
            $afterNote['noteCount'],
        );

        $updated =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/projects/%d',
                    $projectId,
                ),
                [
                    'name' =>
                        'Sprint 3 Updated',

                    'color' =>
                        '#ffe066',
                ],
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Sprint 3 Updated',
            $updated['name'],
        );

        self::assertSame(
            'Project backend foundation',
            $updated['description'],
        );

        self::assertSame(
            '#FFE066',
            $updated['color'],
        );

        /*
         * Members may read a project but may
         * not edit project metadata.
         */
        $this->connection
            ->update(
                'project_member',
                [
                    'role' =>
                        'member',
                ],
                [
                    'project_id' =>
                        $projectId,

                    'user_id' =>
                        $this->userId,
                ],
            );

        $denied =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/projects/%d',
                    $projectId,
                ),
                [
                    'name' =>
                        'Forbidden update',
                ],
            );

        self::assertSame(
            403,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'PROJECT_MANAGEMENT_REQUIRED',
            $denied['code'],
        );
    }

    public function testProjectAccessIsPrivate():
    void {
        $otherUserId =
            $this->createOtherUser();

        $projectId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO project (
    name,
    description,
    color
)
VALUES (
    'Private foreign project',
    '',
    '#1A73E8'
)
RETURNING id
SQL,
                );

        self::assertNotFalse(
            $projectId,
        );

        $projectId =
            (int) $projectId;

        $this->connection->insert(
            'project_member',
            [
                'project_id' =>
                    $projectId,

                'user_id' =>
                    $otherUserId,

                'role' =>
                    'owner',
            ],
        );

        $response =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/projects/%d',
                    $projectId,
                ),
            );

        self::assertSame(
            404,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Project not found.',
            $response['error'],
        );

        $members =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/projects/%d/members',
                    $projectId,
                ),
            );

        self::assertSame(
            404,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Project not found.',
            $members['error'],
        );

        $list =
            $this->jsonRequest(
                'GET',
                '/api/projects',
            );

        self::assertSame(
            [],
            $list['projects'],
        );
    }

    public function testProjectValidation():
    void {
        $invalidName =
            $this->jsonRequest(
                'POST',
                '/api/projects',
                [
                    'name' => ' ',
                ],
            );

        self::assertSame(
            422,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Project name must contain between 1 and 120 characters.',
            $invalidName['error'],
        );

        $invalidColor =
            $this->jsonRequest(
                'POST',
                '/api/projects',
                [
                    'name' =>
                        'Invalid color',

                    'color' =>
                        'orange',
                ],
            );

        self::assertSame(
            422,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Project color must be a six-digit hexadecimal color.',
            $invalidColor['error'],
        );

        $invalidScope =
            $this->jsonRequest(
                'GET',
                '/api/projects?scope=nope',
            );

        self::assertSame(
            422,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Unknown project scope.',
            $invalidScope['error'],
        );
    }

    public function testNotesBridgeToSharedProject():
    void {
        $ownerUserId =
            $this->createOtherUser();

        $projectId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO project (
    name,
    description,
    color
)
VALUES (
    'Shared project',
    'Shared note bridge',
    '#74C0FC'
)
RETURNING id
SQL,
                );

        self::assertNotFalse(
            $projectId,
        );

        $projectId =
            (int) $projectId;

        $this->connection->insert(
            'project_member',
            [
                'project_id' =>
                    $projectId,
                'user_id' =>
                    $ownerUserId,
                'role' =>
                    'owner',
            ],
        );

        $this->connection->insert(
            'project_member',
            [
                'project_id' =>
                    $projectId,
                'user_id' =>
                    $this->userId,
                'role' =>
                    'member',
            ],
        );

        $created =
            $this->jsonRequest(
                'POST',
                '/api/notes',
                [
                    'title' =>
                        'Project API note',
                    'content' =>
                        'Created through projectId',
                    'tagIds' => [],
                    'projectId' =>
                        $projectId,
                ],
            );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            $projectId,
            $created['projectId'],
        );

        self::assertSame(
            'Shared project',
            $created['projectName'],
        );

        $filtered =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/notes?projectId=%d',
                    $projectId,
                ),
            );

        self::assertCount(
            1,
            $filtered['notes'],
        );

        /*
         * Simulate a Channel note already
         * migrated to Project ownership.
         */
        $sharedNoteId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO note (
    user_id,
    project_id,
    title,
    content
)
VALUES (
    NULL,
    :projectId,
    'Migrated shared note',
    'Shared content'
)
RETURNING id
SQL,
                    [
                        'projectId' =>
                            $projectId,
                    ],
                );

        self::assertNotFalse(
            $sharedNoteId,
        );

        $sharedNoteId =
            (int) $sharedNoteId;

        $this->connection->insert(
            'task',
            [
                'note_id' =>
                    $sharedNoteId,
                'content' =>
                    'Existing shared task',
                'priority' =>
                    'normal',
                'status' =>
                    'todo',
                'position' =>
                    0,
            ],
        );

        $detail =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/notes/%d',
                    $sharedNoteId,
                ),
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            $projectId,
            $detail['projectId'],
        );

        self::assertCount(
            1,
            $detail['tasks'],
        );

        $updated =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/notes/%d',
                    $sharedNoteId,
                ),
                [
                    'title' =>
                        'Migrated note updated',
                    'content' =>
                        'Still shared',
                    'tagIds' => [],
                ],
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            $projectId,
            $updated['projectId'],
        );

        $detached =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/notes/%d',
                    $sharedNoteId,
                ),
                [
                    'title' =>
                        'Migrated note updated',
                    'content' =>
                        'Still shared',
                    'tagIds' => [],
                    'projectId' =>
                        null,
                ],
            );

        self::assertSame(
            422,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'A shared note must belong to a project.',
            $detached['error'],
        );

        $duplicate =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/notes/%d/duplicate',
                    $sharedNoteId,
                ),
            );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            $projectId,
            $duplicate['projectId'],
        );

        self::assertCount(
            1,
            $duplicate['tasks'],
        );

        $foreignProjectId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO project (
    name,
    description,
    color
)
VALUES (
    'Foreign project',
    '',
    '#1A73E8'
)
RETURNING id
SQL,
                );

        self::assertNotFalse(
            $foreignProjectId,
        );

        $foreignProjectId =
            (int) $foreignProjectId;

        $this->connection->insert(
            'project_member',
            [
                'project_id' =>
                    $foreignProjectId,
                'user_id' =>
                    $ownerUserId,
                'role' =>
                    'owner',
            ],
        );

        $foreign =
            $this->jsonRequest(
                'POST',
                '/api/notes',
                [
                    'title' =>
                        'Forbidden project note',
                    'tagIds' => [],
                    'projectId' =>
                        $foreignProjectId,
                ],
            );

        self::assertSame(
            422,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Selected project does not exist.',
            $foreign['error'],
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
            (string) $status[
                'csrfToken'
            ];

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

    private function createOtherUser(): int
    {
        $email = sprintf(
            'project-other-%s@example.test',
            bin2hex(
                random_bytes(8),
            ),
        );

        $id =
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
                                'Other-Project-Password-123!',
                                PASSWORD_DEFAULT,
                            ),
                    ],
                );

        self::assertNotFalse($id);

        $id = (int) $id;

        $this->connection->insert(
            'user_email',
            [
                'user_id' =>
                    $id,

                'email' =>
                    $email,

                'normalized_email' =>
                    mb_strtolower($email),

                'is_primary' =>
                    true,
            ],
        );

        return $id;
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
