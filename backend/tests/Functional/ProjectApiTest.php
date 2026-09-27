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

    public function testProjectInvitationsRolesAndRemoval():
    void {
        $project =
            $this->jsonRequest(
                'POST',
                '/api/projects',
                [
                    'name' =>
                        'Shared workspace',
                ],
            );

        $projectId =
            (int) $project['id'];

        $otherUserId =
            $this->createOtherUser();

        $otherEmail =
            $this->primaryEmail(
                $otherUserId,
            );

        $invitation =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/projects/%d/invitations',
                    $projectId,
                ),
                [
                    'email' =>
                        $otherEmail,
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
            $invitation['projectId'],
        );

        self::assertSame(
            $otherEmail,
            $invitation['email'],
        );

        $duplicateInvitation =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/projects/%d/invitations',
                    $projectId,
                ),
                [
                    'email' =>
                        $otherEmail,
                ],
            );

        self::assertSame(
            409,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'PROJECT_ALREADY_INVITED',
            $duplicateInvitation['code'],
        );

        $selfInvitation =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/projects/%d/invitations',
                    $projectId,
                ),
                [
                    'email' =>
                        $this->email,
                ],
            );

        self::assertSame(
            409,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'PROJECT_CANNOT_INVITE_SELF',
            $selfInvitation['code'],
        );

        /*
         * Simulate invitation acceptance by the
         * other account so owner-side role
         * management can be tested without
         * switching browser sessions.
         */
        $this->connection->delete(
            'project_invitation',
            [
                'id' =>
                    (int) $invitation['id'],
            ],
        );

        $this->connection->insert(
            'project_member',
            [
                'project_id' =>
                    $projectId,

                'user_id' =>
                    $otherUserId,

                'role' =>
                    'member',
            ],
        );

        $promoted =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/projects/%d/members/%d/role',
                    $projectId,
                    $otherUserId,
                ),
                [
                    'role' => 'admin',
                ],
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'admin',
            $promoted['role'],
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
            2,
            $members['members'],
        );

        $this->jsonRequest(
            'DELETE',
            sprintf(
                '/api/projects/%d/members/%d',
                $projectId,
                $otherUserId,
            ),
        );

        self::assertSame(
            204,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertFalse(
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM project_member
WHERE project_id = :projectId
  AND user_id = :userId
SQL,
                    [
                        'projectId' =>
                            $projectId,

                        'userId' =>
                            $otherUserId,
                    ],
                ),
        );

        $ownerCannotLeave =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/projects/%d/leave',
                    $projectId,
                ),
            );

        self::assertSame(
            409,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'PROJECT_OWNER_CANNOT_LEAVE',
            $ownerCannotLeave['code'],
        );
    }

    public function testProjectInvitationAcceptRejectAndLeave():
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
    'Invited project',
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
                    $ownerUserId,

                'role' =>
                    'owner',
            ],
        );

        $invitationId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO project_invitation (
    project_id,
    invited_user_id,
    invited_by_user_id
)
VALUES (
    :projectId,
    :userId,
    :ownerUserId
)
RETURNING id
SQL,
                    [
                        'projectId' =>
                            $projectId,

                        'userId' =>
                            $this->userId,

                        'ownerUserId' =>
                            $ownerUserId,
                    ],
                );

        self::assertNotFalse(
            $invitationId,
        );

        $pending =
            $this->jsonRequest(
                'GET',
                '/api/project-invitations',
            );

        self::assertCount(
            1,
            $pending['invitations'],
        );

        self::assertSame(
            $projectId,
            $pending[
                'invitations'
            ][0]['projectId'],
        );

        $accepted =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/project-invitations/%d/accept',
                    (int) $invitationId,
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
            $accepted['projectId'],
        );

        self::assertSame(
            'member',
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

        $forbiddenRole =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/projects/%d/members/%d/role',
                    $projectId,
                    $ownerUserId,
                ),
                [
                    'role' => 'admin',
                ],
            );

        self::assertSame(
            403,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'PROJECT_OWNER_REQUIRED',
            $forbiddenRole['code'],
        );

        $this->jsonRequest(
            'POST',
            sprintf(
                '/api/projects/%d/leave',
                $projectId,
            ),
        );

        self::assertSame(
            204,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertFalse(
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
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

        /*
         * Separate invitation used to exercise
         * explicit rejection.
         */
        $secondProjectId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO project (
    name,
    description,
    color
)
VALUES (
    'Rejected project',
    '',
    '#1A73E8'
)
RETURNING id
SQL,
                );

        self::assertNotFalse(
            $secondProjectId,
        );

        $secondProjectId =
            (int) $secondProjectId;

        $this->connection->insert(
            'project_member',
            [
                'project_id' =>
                    $secondProjectId,

                'user_id' =>
                    $ownerUserId,

                'role' =>
                    'owner',
            ],
        );

        $rejectId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO project_invitation (
    project_id,
    invited_user_id,
    invited_by_user_id
)
VALUES (
    :projectId,
    :userId,
    :ownerUserId
)
RETURNING id
SQL,
                    [
                        'projectId' =>
                            $secondProjectId,

                        'userId' =>
                            $this->userId,

                        'ownerUserId' =>
                            $ownerUserId,
                    ],
                );

        self::assertNotFalse(
            $rejectId,
        );

        $this->jsonRequest(
            'DELETE',
            sprintf(
                '/api/project-invitations/%d',
                (int) $rejectId,
            ),
        );

        self::assertSame(
            204,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertFalse(
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM project_invitation
WHERE id = :id
SQL,
                    [
                        'id' =>
                            (int) $rejectId,
                    ],
                ),
        );
    }

    public function testProjectMembersCanManageSharedTasksWithoutRemovingForeignTags():
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
    'Collaborative tasks',
    '',
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
    NULL,
    :projectId,
    'Shared task note',
    ''
)
RETURNING id
SQL,
                    [
                        'projectId' =>
                            $projectId,
                    ],
                );

        self::assertNotFalse(
            $noteId,
        );

        $noteId =
            (int) $noteId;

        $currentTagId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO tag (
    user_id,
    name,
    color
)
VALUES (
    :userId,
    'My shared tag',
    '#12B886'
)
RETURNING id
SQL,
                    [
                        'userId' =>
                            $this->userId,
                    ],
                );

        self::assertNotFalse(
            $currentTagId,
        );

        $currentTagId =
            (int) $currentTagId;

        $foreignTagId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO tag (
    user_id,
    name,
    color
)
VALUES (
    :userId,
    'Owner tag',
    '#FA5252'
)
RETURNING id
SQL,
                    [
                        'userId' =>
                            $ownerUserId,
                    ],
                );

        self::assertNotFalse(
            $foreignTagId,
        );

        $foreignTagId =
            (int) $foreignTagId;

        $task =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/notes/%d/tasks',
                    $noteId,
                ),
                [
                    'content' =>
                        'Shared Project task',

                    'priority' =>
                        'high',

                    'status' =>
                        'in_progress',

                    'tagIds' => [
                        $currentTagId,
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
            $noteId,
            $task['noteId'],
        );

        self::assertSame(
            'high',
            $task['priority'],
        );

        self::assertSame(
            'in_progress',
            $task['status'],
        );

        self::assertCount(
            1,
            $task['tags'],
        );

        $taskId =
            (int) $task['id'];

        /*
         * Simulate a tag attached by another
         * Project member.
         */
        $this->connection->insert(
            'task_tag',
            [
                'task_id' =>
                    $taskId,

                'tag_id' =>
                    $foreignTagId,
            ],
        );

        /*
         * Updating with no local tags must
         * remove only this user's tag.
         */
        $updated =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/tasks/%d',
                    $taskId,
                ),
                [
                    'content' =>
                        'Updated shared task',

                    'priority' =>
                        'urgent',

                    'status' =>
                        'todo',

                    'position' =>
                        0,

                    'tagIds' => [],

                    'startDate' =>
                        null,

                    'dueDate' =>
                        null,
                ],
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Updated shared task',
            $updated['content'],
        );

        self::assertSame(
            'urgent',
            $updated['priority'],
        );

        /*
         * API exposes only the current user's
         * personal tags.
         */
        self::assertSame(
            [],
            $updated['tags'],
        );

        self::assertFalse(
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM task_tag
WHERE task_id = :taskId
  AND tag_id = :tagId
LIMIT 1
SQL,
                    [
                        'taskId' =>
                            $taskId,

                        'tagId' =>
                            $currentTagId,
                    ],
                ),
        );

        self::assertSame(
            1,
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COUNT(*)
FROM task_tag
WHERE task_id = :taskId
  AND tag_id = :tagId
SQL,
                    [
                        'taskId' =>
                            $taskId,

                        'tagId' =>
                            $foreignTagId,
                    ],
                ),
        );

        $completed =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/tasks/%d/completed',
                    $taskId,
                ),
                [
                    'completed' =>
                        true,
                ],
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertTrue(
            $completed[
                'isCompleted'
            ],
        );

        self::assertSame(
            'done',
            $completed['status'],
        );

        $detail =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/notes/%d',
                    $noteId,
                ),
            );

        self::assertCount(
            1,
            $detail['tasks'],
        );

        self::assertTrue(
            $detail[
                'tasks'
            ][0]['isCompleted'],
        );

        $this->jsonRequest(
            'DELETE',
            sprintf(
                '/api/tasks/%d',
                $taskId,
            ),
        );

        self::assertSame(
            204,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertFalse(
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM task
WHERE id = :id
LIMIT 1
SQL,
                    [
                        'id' =>
                            $taskId,
                    ],
                ),
        );
    }

    public function testProjectWorkflowLifecycle():
    void {
        $project =
            $this->jsonRequest(
                'POST',
                '/api/projects',
                [
                    'name' =>
                        'Workflow project',

                    'description' =>
                        '',

                    'color' =>
                        '#1A73E8',
                ],
            );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        $projectId =
            (int) $project['id'];

        $workflow =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/projects/%d/workflow',
                    $projectId,
                ),
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            [
                'Backlog',
                'En cours',
                'Terminé',
            ],
            array_column(
                $workflow['stages'],
                'name',
            ),
        );

        self::assertSame(
            [0, 1, 2],
            array_column(
                $workflow['stages'],
                'position',
            ),
        );

        $created =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/projects/%d/workflow/stages',
                    $projectId,
                ),
                [
                    'name' =>
                        'Revue',
                ],
            );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Revue',
            $created['name'],
        );

        self::assertSame(
            3,
            $created['position'],
        );

        $stageId =
            (int) $created['id'];

        $renamed =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/projects/%d/workflow/stages/%d',
                    $projectId,
                    $stageId,
                ),
                [
                    'name' =>
                        'Validation',
                ],
            );

        self::assertSame(
            'Validation',
            $renamed['name'],
        );

        $defaultIds =
            array_map(
                static fn (
                    mixed $id,
                ): int => (int) $id,
                array_column(
                    $workflow['stages'],
                    'id',
                ),
            );

        $ordered =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/projects/%d/workflow/order',
                    $projectId,
                ),
                [
                    'stageIds' => [
                        $stageId,
                        ...$defaultIds,
                    ],
                ],
            );

        self::assertSame(
            [
                $stageId,
                ...$defaultIds,
            ],
            array_column(
                $ordered['stages'],
                'id',
            ),
        );

        self::assertSame(
            [0, 1, 2, 3],
            array_column(
                $ordered['stages'],
                'position',
            ),
        );

        $duplicate =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/projects/%d/workflow/stages',
                    $projectId,
                ),
                [
                    'name' =>
                        'backlog',
                ],
            );

        self::assertSame(
            409,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'PROJECT_WORKFLOW_NAME_CONFLICT',
            $duplicate['code'],
        );

        /*
         * Managers only.
         */
        $this->connection->update(
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

        $forbidden =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/projects/%d/workflow/stages',
                    $projectId,
                ),
                [
                    'name' =>
                        'Interdit',
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
            $forbidden['code'],
        );

        $this->connection->update(
            'project_member',
            [
                'role' =>
                    'owner',
            ],
            [
                'project_id' =>
                    $projectId,

                'user_id' =>
                    $this->userId,
            ],
        );

        /*
         * Maximum 20 stages.
         *
         * Four already exist.
         */
        for (
            $index = 4;
            $index < 20;
            ++$index
        ) {
            $extra =
                $this->jsonRequest(
                    'POST',
                    sprintf(
                        '/api/projects/%d/workflow/stages',
                        $projectId,
                    ),
                    [
                        'name' =>
                            sprintf(
                                'Étape %d',
                                $index,
                            ),
                    ],
                );

            self::assertSame(
                201,
                $this->client
                    ->getResponse()
                    ->getStatusCode(),
            );

            self::assertSame(
                $index,
                $extra['position'],
            );
        }

        $overflow =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/projects/%d/workflow/stages',
                    $projectId,
                ),
                [
                    'name' =>
                        'Étape 21',
                ],
            );

        self::assertSame(
            409,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'PROJECT_WORKFLOW_LIMIT',
            $overflow['code'],
        );

        /*
         * A workflow must always keep at least
         * one stage.
         */
        $keepStageId =
            $stageId;

        $this->connection
            ->executeStatement(
                <<<'SQL'
DELETE FROM project_workflow_stage
WHERE project_id = :projectId
  AND id <> :stageId
SQL,
                [
                    'projectId' =>
                        $projectId,

                    'stageId' =>
                        $keepStageId,
                ],
            );

        $minimum =
            $this->jsonRequest(
                'DELETE',
                sprintf(
                    '/api/projects/%d/workflow/stages/%d',
                    $projectId,
                    $keepStageId,
                ),
            );

        self::assertSame(
            409,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'PROJECT_WORKFLOW_MINIMUM',
            $minimum['code'],
        );
    }

    public function testNativeProjectTasksLifecycle():
    void {
        $project =
            $this->jsonRequest(
                'POST',
                '/api/projects',
                [
                    'name' =>
                        'Native tasks',

                    'description' =>
                        '',

                    'color' =>
                        '#845EF7',
                ],
            );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        $projectId =
            (int) $project['id'];

        $workflow =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/projects/%d/workflow',
                    $projectId,
                ),
            );

        self::assertCount(
            3,
            $workflow['stages'],
        );

        $firstStageId =
            (int) $workflow[
                'stages'
            ][0]['id'];

        $secondStageId =
            (int) $workflow[
                'stages'
            ][1]['id'];

        $tagId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO tag (
    user_id,
    name,
    color
)
VALUES (
    :userId,
    'Project tag',
    '#12B886'
)
RETURNING id
SQL,
                    [
                        'userId' =>
                            $this->userId,
                    ],
                );

        self::assertNotFalse(
            $tagId,
        );

        $tagId = (int) $tagId;

        /*
         * Omitting workflowStageId places the
         * task in the first workflow stage.
         */
        $created =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/projects/%d/tasks',
                    $projectId,
                ),
                [
                    'content' =>
                        'Native project task',

                    'priority' =>
                        'urgent',

                    'tagIds' => [
                        $tagId,
                    ],

                    'startDate' =>
                        '2026-09-26',

                    'dueDate' =>
                        '2026-09-30',
                ],
            );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        $taskId =
            (int) $created['id'];

        self::assertNull(
            $created['noteId'],
        );

        self::assertSame(
            $projectId,
            $created['projectId'],
        );

        self::assertSame(
            $firstStageId,
            $created['workflowStageId'],
        );

        self::assertSame(
            'Backlog',
            $created[
                'workflowStageName'
            ],
        );

        self::assertSame(
            'urgent',
            $created['priority'],
        );

        self::assertSame(
            'todo',
            $created['status'],
        );

        self::assertFalse(
            $created['isCompleted'],
        );

        self::assertSame(
            $tagId,
            $created['tags'][0]['id'],
        );

        self::assertSame(
            [
                null,
                $projectId,
                $firstStageId,
            ],
            $this->connection
                ->fetchNumeric(
                    <<<'SQL'
SELECT
    note_id,
    project_id,
    workflow_stage_id
FROM task
WHERE id = :taskId
SQL,
                    [
                        'taskId' =>
                            $taskId,
                    ],
                ),
        );

        $listed =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/projects/%d/tasks',
                    $projectId,
                ),
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertCount(
            1,
            $listed['tasks'],
        );

        /*
         * Shared project tasks keep tags
         * personal. Updating my tags must not
         * remove another user's tag.
         */
        $otherUserId =
            $this->createOtherUser();

        $foreignTagId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO tag (
    user_id,
    name,
    color
)
VALUES (
    :userId,
    'Foreign project tag',
    '#FA5252'
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

        $foreignTagId =
            (int) $foreignTagId;

        $this->connection->insert(
            'task_tag',
            [
                'task_id' =>
                    $taskId,

                'tag_id' =>
                    $foreignTagId,
            ],
        );

        /*
         * A regular Project member may edit
         * the task; management privileges are
         * not required.
         */
        $this->connection->update(
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

        $updated =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/projects/%d/tasks/%d',
                    $projectId,
                    $taskId,
                ),
                [
                    'content' =>
                        'Moved project task',

                    'priority' =>
                        'high',

                    'status' =>
                        'in_progress',

                    'workflowStageId' =>
                        $secondStageId,

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
            'Moved project task',
            $updated['content'],
        );

        self::assertSame(
            $secondStageId,
            $updated[
                'workflowStageId'
            ],
        );

        self::assertSame(
            'En cours',
            $updated[
                'workflowStageName'
            ],
        );

        self::assertSame(
            'in_progress',
            $updated['status'],
        );

        self::assertSame(
            [],
            $updated['tags'],
        );

        self::assertFalse(
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT 1
FROM task_tag
WHERE task_id = :taskId
  AND tag_id = :tagId
LIMIT 1
SQL,
                    [
                        'taskId' =>
                            $taskId,

                        'tagId' =>
                            $tagId,
                    ],
                ),
        );

        self::assertSame(
            1,
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COUNT(*)
FROM task_tag
WHERE task_id = :taskId
  AND tag_id = :tagId
SQL,
                    [
                        'taskId' =>
                            $taskId,

                        'tagId' =>
                            $foreignTagId,
                    ],
                ),
        );

        /*
         * Workflow administration still
         * requires owner/admin.
         */
        $this->connection->update(
            'project_member',
            [
                'role' =>
                    'owner',
            ],
            [
                'project_id' =>
                    $projectId,

                'user_id' =>
                    $this->userId,
            ],
        );

        $occupied =
            $this->jsonRequest(
                'DELETE',
                sprintf(
                    '/api/projects/%d/workflow/stages/%d',
                    $projectId,
                    $secondStageId,
                ),
            );

        self::assertSame(
            409,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'PROJECT_WORKFLOW_STAGE_NOT_EMPTY',
            $occupied['code'],
        );

        $completed =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/projects/%d/tasks/%d/completed',
                    $projectId,
                    $taskId,
                ),
                [
                    'completed' =>
                        true,
                ],
            );

        self::assertTrue(
            $completed['isCompleted'],
        );

        self::assertSame(
            'done',
            $completed['status'],
        );

        $reopened =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/projects/%d/tasks/%d/completed',
                    $projectId,
                    $taskId,
                ),
                [
                    'completed' =>
                        false,
                ],
            );

        self::assertFalse(
            $reopened['isCompleted'],
        );

        self::assertSame(
            'todo',
            $reopened['status'],
        );

        $this->jsonRequest(
            'DELETE',
            sprintf(
                '/api/projects/%d/tasks/%d',
                $projectId,
                $taskId,
            ),
        );

        self::assertSame(
            204,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertFalse(
            $this->connection
                ->fetchOne(
                    'SELECT 1 FROM task '
                    .'WHERE id = :taskId',
                    [
                        'taskId' =>
                            $taskId,
                    ],
                ),
        );

        /*
         * Once empty, the workflow stage can
         * be removed normally.
         */
        $this->jsonRequest(
            'DELETE',
            sprintf(
                '/api/projects/%d/workflow/stages/%d',
                $projectId,
                $secondStageId,
            ),
        );

        self::assertSame(
            204,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );
    }

    public function testProjectTaskOrderAcrossWorkflowStages():
    void {
        $project =
            $this->jsonRequest(
                'POST',
                '/api/projects',
                [
                    'name' =>
                        'Ordered board',

                    'description' =>
                        '',

                    'color' =>
                        '#228BE6',
                ],
            );

        $projectId =
            (int) $project['id'];

        $workflow =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/projects/%d/workflow',
                    $projectId,
                ),
            );

        $firstStageId =
            (int) $workflow[
                'stages'
            ][0]['id'];

        $secondStageId =
            (int) $workflow[
                'stages'
            ][1]['id'];

        $thirdStageId =
            (int) $workflow[
                'stages'
            ][2]['id'];

        $taskIds = [];

        foreach (
            ['One', 'Two', 'Three']
            as $content
        ) {
            $task =
                $this->jsonRequest(
                    'POST',
                    sprintf(
                        '/api/projects/%d/tasks',
                        $projectId,
                    ),
                    [
                        'content' =>
                            $content,

                        'workflowStageId' =>
                            $firstStageId,
                    ],
                );

            $taskIds[] =
                (int) $task['id'];
        }

        $ordered =
            $this->jsonRequest(
                'PUT',
                sprintf(
                    '/api/projects/%d/tasks/order',
                    $projectId,
                ),
                [
                    'columns' => [
                        [
                            'workflowStageId' =>
                                $firstStageId,

                            'taskIds' => [
                                $taskIds[2],
                            ],
                        ],

                        [
                            'workflowStageId' =>
                                $secondStageId,

                            'taskIds' => [
                                $taskIds[1],
                                $taskIds[0],
                            ],
                        ],

                        [
                            'workflowStageId' =>
                                $thirdStageId,

                            'taskIds' => [],
                        ],
                    ],
                ],
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            [
                $taskIds[2],
                $taskIds[1],
                $taskIds[0],
            ],
            array_column(
                $ordered['tasks'],
                'id',
            ),
        );

        self::assertSame(
            [
                [
                    $firstStageId,
                    0,
                ],
                [
                    $secondStageId,
                    0,
                ],
                [
                    $secondStageId,
                    1,
                ],
            ],
            array_map(
                static fn (
                    array $task,
                ): array => [
                    (int) $task[
                        'workflowStageId'
                    ],

                    (int) $task[
                        'position'
                    ],
                ],
                $ordered['tasks'],
            ),
        );
    }

    public function testProjectMembersCanArchiveTrashAndRestoreSharedNotes():
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
    'Lifecycle project',
    '',
    '#15AABF'
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
    NULL,
    :projectId,
    'Shared lifecycle note',
    'Lifecycle test'
)
RETURNING id
SQL,
                    [
                        'projectId' =>
                            $projectId,
                    ],
                );

        self::assertNotFalse(
            $noteId,
        );

        $noteId =
            (int) $noteId;

        $archived =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/notes/%d/archive',
                    $noteId,
                ),
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertNotNull(
            $archived['archivedAt'],
        );

        $archivedList =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/notes?scope=archived&projectId=%d',
                    $projectId,
                ),
            );

        self::assertSame(
            [$noteId],
            array_column(
                $archivedList['notes'],
                'id',
            ),
        );

        $unarchived =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/notes/%d/unarchive',
                    $noteId,
                ),
            );

        self::assertNull(
            $unarchived['archivedAt'],
        );

        $this->jsonRequest(
            'DELETE',
            sprintf(
                '/api/notes/%d',
                $noteId,
            ),
        );

        self::assertSame(
            204,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        $trashList =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/notes?scope=trash&projectId=%d',
                    $projectId,
                ),
            );

        self::assertSame(
            [$noteId],
            array_column(
                $trashList['notes'],
                'id',
            ),
        );

        $restored =
            $this->jsonRequest(
                'POST',
                sprintf(
                    '/api/notes/%d/restore',
                    $noteId,
                ),
            );

        self::assertSame(
            200,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertNull(
            $restored['deletedAt'],
        );

        $activeList =
            $this->jsonRequest(
                'GET',
                sprintf(
                    '/api/notes?projectId=%d',
                    $projectId,
                ),
            );

        self::assertSame(
            [$noteId],
            array_column(
                $activeList['notes'],
                'id',
            ),
        );

        $this->connection->delete(
            'project_member',
            [
                'project_id' =>
                    $projectId,
                'user_id' =>
                    $this->userId,
            ],
        );

        $this->jsonRequest(
            'POST',
            sprintf(
                '/api/notes/%d/archive',
                $noteId,
            ),
        );

        self::assertSame(
            404,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );
    }

    public function testDeletingAccountRetiresMigratedLegacyChannel():
    void {
        $collaboratorUserId =
            $this->createOtherUser();

        $project =
            $this->jsonRequest(
                'POST',
                '/api/projects',
                [
                    'name' =>
                        'Account deletion project',

                    'description' =>
                        'Compatibility project',

                    'color' =>
                        '#339AF0',
                ],
            );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        $projectId =
            (int) $project['id'];

        $this->connection->insert(
            'project_member',
            [
                'project_id' =>
                    $projectId,

                'user_id' =>
                    $collaboratorUserId,

                'role' =>
                    'member',
            ],
        );

        $channelId =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
INSERT INTO channel (
    code,
    name,
    description,
    creator_user_id
)
VALUES (
    '987654321',
    'Legacy project channel',
    '',
    :userId
)
RETURNING id
SQL,
                    [
                        'userId' =>
                            $this->userId,
                    ],
                );

        self::assertNotFalse(
            $channelId,
        );

        $channelId =
            (int) $channelId;

        $this->connection->insert(
            'channel_member',
            [
                'channel_id' =>
                    $channelId,

                'user_id' =>
                    $this->userId,
            ],
        );

        /*
         * An unmigrated legacy Channel remains
         * a hard safety blocker.
         */
        $blocked =
            $this->jsonRequest(
                'DELETE',
                '/api/profile',
                [
                    'password' =>
                        $this->password,
                ],
            );

        self::assertSame(
            409,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'CHANNEL_OWNERSHIP_BLOCKS_ACCOUNT_DELETION',
            $blocked['code'],
        );

        self::assertSame(
            1,
            $blocked['channelCount'],
        );

        self::assertSame(
            1,
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COUNT(*)
FROM app_user
WHERE id = :userId
SQL,
                    [
                        'userId' =>
                            $this->userId,
                    ],
                ),
        );

        /*
         * Represent the legacy Channel through
         * its migrated Project.
         */
        $this->connection->update(
            'project',
            [
                'legacy_channel_id' =>
                    $channelId,
            ],
            [
                'id' =>
                    $projectId,
            ],
        );

        $this->jsonRequest(
            'DELETE',
            '/api/profile',
            [
                'password' =>
                    $this->password,
            ],
        );

        self::assertSame(
            204,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            0,
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COUNT(*)
FROM app_user
WHERE id = :userId
SQL,
                    [
                        'userId' =>
                            $this->userId,
                    ],
                ),
        );

        self::assertSame(
            1,
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COUNT(*)
FROM project
WHERE id = :projectId
  AND archived_at IS NOT NULL
SQL,
                    [
                        'projectId' =>
                            $projectId,
                    ],
                ),
        );

        self::assertSame(
            0,
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COUNT(*)
FROM project_member
WHERE project_id = :projectId
  AND role = 'owner'
SQL,
                    [
                        'projectId' =>
                            $projectId,
                    ],
                ),
        );

        self::assertSame(
            1,
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COUNT(*)
FROM project_member
WHERE project_id = :projectId
  AND user_id = :userId
  AND role = 'member'
SQL,
                    [
                        'projectId' =>
                            $projectId,

                        'userId' =>
                            $collaboratorUserId,
                    ],
                ),
        );

        self::assertSame(
            1,
            (int) $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT COUNT(*)
FROM channel
WHERE id = :channelId
  AND closed_at IS NOT NULL
  AND creator_user_id IS NULL
SQL,
                    [
                        'channelId' =>
                            $channelId,
                    ],
                ),
        );
    }

    public function testPinnedNotesAreLimitedToTwenty():
    void {
        $this->connection
            ->executeStatement(
                <<<'SQL'
INSERT INTO note (
    user_id,
    title,
    content,
    is_pinned,
    color
)
SELECT
    :userId,
    'Pinned note ' || series::text,
    '',
    TRUE,
    '#FFFFFF'
FROM generate_series(
    1,
    20
) AS series
SQL,
                [
                    'userId' =>
                        $this->userId,
                ],
            );

        $blocked =
            $this->jsonRequest(
                'POST',
                '/api/notes',
                [
                    'title' =>
                        'Pinned note 21',

                    'content' =>
                        '',

                    'tagIds' =>
                        [],

                    'projectId' =>
                        null,

                    'isPinned' =>
                        true,

                    'color' =>
                        '#FFFFFF',
                ],
            );

        self::assertSame(
            409,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertSame(
            'Vous ne pouvez pas épingler plus de 20 notes. Désépinglez une autre note avant de continuer.',
            $blocked['error'],
        );

        $this->connection
            ->executeStatement(
                <<<'SQL'
UPDATE note
SET is_pinned = FALSE
WHERE id = (
    SELECT id
    FROM note
    WHERE user_id = :userId
      AND is_pinned = TRUE
    ORDER BY id
    LIMIT 1
)
SQL,
                [
                    'userId' =>
                        $this->userId,
                ],
            );

        $created =
            $this->jsonRequest(
                'POST',
                '/api/notes',
                [
                    'title' =>
                        'Pinned note after slot freed',

                    'content' =>
                        '',

                    'tagIds' =>
                        [],

                    'projectId' =>
                        null,

                    'isPinned' =>
                        true,

                    'color' =>
                        '#FFFFFF',
                ],
            );

        self::assertSame(
            201,
            $this->client
                ->getResponse()
                ->getStatusCode(),
        );

        self::assertTrue(
            $created['isPinned'],
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

    private function primaryEmail(
        int $userId,
    ): string {
        $email =
            $this->connection
                ->fetchOne(
                    <<<'SQL'
SELECT email
FROM user_email
WHERE user_id = :userId
  AND is_primary = TRUE
LIMIT 1
SQL,
                    [
                        'userId' =>
                            $userId,
                    ],
                );

        self::assertNotFalse(
            $email,
        );

        return (string) $email;
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
        $response =
            $this->client
                ->getResponse();

        if (
            $response->getStatusCode()
            === 204
        ) {
            return [];
        }

        $content =
            $response->getContent();

        self::assertNotFalse(
            $content,
        );

        if ($content === '') {
            return [];
        }

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
