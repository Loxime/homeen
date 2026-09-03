<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final readonly class UserRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function emailExists(string $email): bool
    {
        return $this->connection->fetchOne(
            <<<'SQL'
SELECT EXISTS (
    SELECT 1
    FROM user_email
    WHERE normalized_email = :email
)
SQL,
            [
                'email' => self::normalizeEmail($email),
            ],
        ) === true;
    }

    /**
     * @return array{
     *     id:int,
     *     email:string
     * }
     */
    public function createWithPrimaryEmail(
        string $email,
        string $passwordHash,
    ): array {
        return $this->connection->transactional(
            function (Connection $connection) use (
                $email,
                $passwordHash,
            ): array {
                $userId = $connection->fetchOne(
                    <<<'SQL'
INSERT INTO app_user (
    password_hash,
    must_change_password
)
VALUES (
    :passwordHash,
    TRUE
)
RETURNING id
SQL,
                    [
                        'passwordHash' => $passwordHash,
                    ],
                );

                if ($userId === false) {
                    throw new \RuntimeException(
                        'Unable to create user.'
                    );
                }

                $id = (int) $userId;

                $connection->insert(
                    'user_email',
                    [
                        'user_id' => $id,
                        'email' => trim($email),
                        'normalized_email' =>
                            self::normalizeEmail($email),
                        'is_primary' => true,
                    ],
                );

                return [
                    'id' => $id,
                    'email' => trim($email),
                ];
            },
        );
    }

    /**
     * @return array{
     *     id:int,
     *     email:string,
     *     passwordHash:string,
     *     mustChangePassword:bool,
     *     temporaryPasswordConsumedAt:?string
     * }|null
     */
    public function findForAuthentication(
        string $email,
    ): ?array {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
SELECT
    u.id,
    ue.email,
    u.password_hash AS "passwordHash",
    u.must_change_password AS "mustChangePassword",
    u.temporary_password_consumed_at
        AS "temporaryPasswordConsumedAt"
FROM app_user u
INNER JOIN user_email ue
    ON ue.user_id = u.id
WHERE ue.normalized_email = :email
LIMIT 1
SQL,
            [
                'email' => self::normalizeEmail($email),
            ],
        );

        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'passwordHash' =>
                (string) $row['passwordHash'],
            'mustChangePassword' =>
                self::toBool($row['mustChangePassword']),
            'temporaryPasswordConsumedAt' =>
                $row['temporaryPasswordConsumedAt'] !== null
                    ? (string) $row[
                        'temporaryPasswordConsumedAt'
                    ]
                    : null,
        ];
    }

    public function consumeTemporaryPassword(
        int $userId,
    ): bool {
        $affected = $this->connection->executeStatement(
            <<<'SQL'
UPDATE app_user
SET temporary_password_consumed_at = NOW(),
    last_login_at = NOW(),
    updated_at = NOW()
WHERE id = :id
  AND must_change_password = TRUE
  AND temporary_password_consumed_at IS NULL
SQL,
            ['id' => $userId],
        );

        return $affected === 1;
    }

    public function markSuccessfulLogin(
        int $userId,
    ): void {
        $this->connection->executeStatement(
            <<<'SQL'
UPDATE app_user
SET last_login_at = NOW(),
    updated_at = NOW()
WHERE id = :id
SQL,
            ['id' => $userId],
        );
    }

    public function completeInitialPasswordChange(
        int $userId,
        string $passwordHash,
    ): void {
        $affected = $this->connection->executeStatement(
            <<<'SQL'
UPDATE app_user
SET password_hash = :passwordHash,
    must_change_password = FALSE,
    password_changed_at = NOW(),
    updated_at = NOW()
WHERE id = :id
  AND must_change_password = TRUE
SQL,
            [
                'id' => $userId,
                'passwordHash' => $passwordHash,
            ],
        );

        if ($affected !== 1) {
            throw new \RuntimeException(
                'Unable to complete password change.'
            );
        }
    }

    public function findPasswordHash(
        int $userId,
    ): ?string {
        $hash = $this->connection->fetchOne(
            <<<'SQL'
SELECT password_hash
FROM app_user
WHERE id = :id
SQL,
            ['id' => $userId],
        );

        return $hash === false
            ? null
            : (string) $hash;
    }

/**
 * @return array{
 *     id:int,
 *     email:string
 * }|null
 */
public function resetPasswordToTemporary(
    string $email,
    string $passwordHash,
): ?array {
    return $this->connection->transactional(
        function (Connection $connection) use (
            $email,
            $passwordHash,
        ): ?array {
            $row = $connection->fetchAssociative(
                <<<'SQL'
SELECT
    u.id,
    ue.email
FROM app_user u
INNER JOIN user_email ue
    ON ue.user_id = u.id
WHERE ue.normalized_email = :email
LIMIT 1
SQL,
                [
                    'email' =>
                        self::normalizeEmail($email),
                ],
            );

            if ($row === false) {
                return null;
            }

            $userId = (int) $row['id'];

            $connection->executeStatement(
                <<<'SQL'
UPDATE app_user
SET password_hash = :passwordHash,
    must_change_password = TRUE,
    temporary_password_consumed_at = NULL,
    updated_at = NOW()
WHERE id = :id
SQL,
                [
                    'id' => $userId,
                    'passwordHash' => $passwordHash,
                ],
            );

            return [
                'id' => $userId,
                'email' => (string) $row['email'],
            ];
        },
    );
}


/**
 * @return array{
 *     id:int,
 *     primaryEmail:string,
 *     notificationSoundEnabled:bool,
 *     emails:list<array{
 *         id:int,
 *         email:string,
 *         isPrimary:bool
 *     }>
 * }|null
 */
public function getProfile(int $userId): ?array
{
    $user = $this->connection->fetchAssociative(
        <<<'SQL'
SELECT
    id,
    notification_sound_enabled AS "notificationSoundEnabled"
FROM app_user
WHERE id = :id
SQL,
        ['id' => $userId],
    );

    if ($user === false) {
        return null;
    }

    $rows = $this->connection->fetchAllAssociative(
        <<<'SQL'
SELECT
    id,
    email,
    is_primary AS "isPrimary"
FROM user_email
WHERE user_id = :userId
ORDER BY is_primary DESC, created_at ASC
SQL,
        ['userId' => $userId],
    );

    $emails = [];
    $primaryEmail = '';

    foreach ($rows as $row) {
        $isPrimary = self::toBool(
            $row['isPrimary']
        );

        $email = (string) $row['email'];

        if ($isPrimary) {
            $primaryEmail = $email;
        }

        $emails[] = [
            'id' => (int) $row['id'],
            'email' => $email,
            'isPrimary' => $isPrimary,
        ];
    }

    return [
        'id' => (int) $user['id'],
        'primaryEmail' => $primaryEmail,
        'notificationSoundEnabled' =>
            self::toBool(
                $user['notificationSoundEnabled']
            ),
        'emails' => $emails,
    ];
}

/**
 * @return array{
 *     id:int,
 *     email:string,
 *     isPrimary:bool
 * }
 */
public function addEmail(
    int $userId,
    string $email,
): array {
    $normalized = self::normalizeEmail($email);

    $id = $this->connection->fetchOne(
        <<<'SQL'
INSERT INTO user_email (
    user_id,
    email,
    normalized_email,
    is_primary
)
VALUES (
    :userId,
    :email,
    :normalizedEmail,
    FALSE
)
RETURNING id
SQL,
        [
            'userId' => $userId,
            'email' => trim($email),
            'normalizedEmail' => $normalized,
        ],
    );

    if ($id === false) {
        throw new \RuntimeException(
            'Unable to add email address.'
        );
    }

    return [
        'id' => (int) $id,
        'email' => trim($email),
        'isPrimary' => false,
    ];
}

public function deleteSecondaryEmail(
    int $userId,
    int $emailId,
): bool {
    $affected = $this->connection->executeStatement(
        <<<'SQL'
DELETE FROM user_email
WHERE id = :emailId
  AND user_id = :userId
  AND is_primary = FALSE
SQL,
        [
            'emailId' => $emailId,
            'userId' => $userId,
        ],
    );

    return $affected === 1;
}

public function changePassword(
    int $userId,
    string $passwordHash,
): void {
    $affected = $this->connection->executeStatement(
        <<<'SQL'
UPDATE app_user
SET password_hash = :passwordHash,
    password_changed_at = NOW(),
    updated_at = NOW()
WHERE id = :id
SQL,
        [
            'id' => $userId,
            'passwordHash' => $passwordHash,
        ],
    );

    if ($affected !== 1) {
        throw new \RuntimeException(
            'Unable to change password.'
        );
    }
}

public function setNotificationSoundEnabled(
    int $userId,
    bool $enabled,
): void {
    $affected = $this->connection->executeStatement(
        <<<'SQL'
UPDATE app_user
SET notification_sound_enabled = :enabled,
    updated_at = NOW()
WHERE id = :id
SQL,
        [
            'id' => $userId,
            'enabled' => $enabled,
        ],
    );

    if ($affected !== 1) {
        throw new \RuntimeException(
            'Unable to update notification preference.'
        );
    }
}

    public function deleteAccount(int $userId): void
    {
        $affected = $this->connection->delete(
            'app_user',
            [
                'id' => $userId,
            ],
        );

        if ($affected !== 1) {
            throw new \RuntimeException(
                'Unable to delete user account.'
            );
        }
    }

    public static function normalizeEmail(
        string $email,
    ): string {
        return mb_strtolower(trim($email));
    }

    private static function toBool(
        mixed $value,
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
        );
    }
}
