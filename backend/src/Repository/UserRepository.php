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
