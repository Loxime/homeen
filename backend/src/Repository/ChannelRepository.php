<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\CurrentUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class ChannelRepository
{
    private const MAX_CODE_ATTEMPTS = 20;

    public function __construct(
        private Connection $connection,
        private CurrentUser $currentUser,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForCurrentUser(): array
    {
        $rows = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    c.id,
    c.code,
    c.name,
    c.description,
    c.profile_image_url
        AS "profileImageUrl",
    c.creator_user_id
        AS "creatorUserId",
    c.created_at
        AS "createdAt",
    COUNT(member.user_id)
        AS "memberCount"
FROM channel c
INNER JOIN channel_member current_member
    ON current_member.channel_id = c.id
   AND current_member.user_id = :userId
LEFT JOIN channel_member member
    ON member.channel_id = c.id
WHERE c.closed_at IS NULL
GROUP BY c.id
ORDER BY c.updated_at DESC,
         c.id DESC
SQL,
                [
                    'userId' =>
                        $this->currentUser->id(),
                ],
            );

        return array_map(
            $this->normalize(...),
            $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        string $description = '',
    ): array {
        $name = trim($name);
        $description = trim($description);

        if (
            $name === ''
            || mb_strlen($name) > 120
        ) {
            throw new \InvalidArgumentException(
                'Channel name must contain between 1 and 120 characters.'
            );
        }

        if (
            mb_strlen($description)
            > 2000
        ) {
            throw new \InvalidArgumentException(
                'Channel description cannot exceed 2000 characters.'
            );
        }

        $userId =
            $this->currentUser->id();

        return $this->connection
            ->transactional(
                function (
                    Connection $connection
                ) use (
                    $userId,
                    $name,
                    $description,
                ): array {
                    $channelId = null;
                    $code = '';

                    for (
                        $attempt = 0;
                        $attempt
                            < self::MAX_CODE_ATTEMPTS;
                        ++$attempt
                    ) {
                        $candidate =
                            (string) random_int(
                                100000000,
                                999999999,
                            );

                        try {
                            $id =
                                $connection
                                    ->fetchOne(
                                        <<<'SQL'
INSERT INTO channel (
    code,
    name,
    description,
    creator_user_id
)
VALUES (
    :code,
    :name,
    :description,
    :creatorUserId
)
RETURNING id
SQL,
                                        [
                                            'code' =>
                                                $candidate,

                                            'name' =>
                                                $name,

                                            'description' =>
                                                $description,

                                            'creatorUserId' =>
                                                $userId,
                                        ],
                                    );

                            if ($id === false) {
                                throw new \RuntimeException(
                                    'Unable to create channel.'
                                );
                            }

                            $channelId =
                                (int) $id;

                            $code =
                                $candidate;

                            break;
                        } catch (
                            UniqueConstraintViolationException
                        ) {
                            /*
                             * Extremely unlikely:
                             * generated code already exists.
                             */
                        }
                    }

                    if ($channelId === null) {
                        throw new \RuntimeException(
                            'Unable to generate a unique channel code.'
                        );
                    }

                    $connection->insert(
                        'channel_member',
                        [
                            'channel_id' =>
                                $channelId,

                            'user_id' =>
                                $userId,
                        ],
                    );

                    return $this->getAccessible(
                        $code
                    );
                },
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function getAccessible(
        string $code,
    ): array {
        $this->validateCode($code);

        $userId =
            $this->currentUser->id();

        $row = $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT
    c.id,
    c.code,
    c.name,
    c.description,
    c.profile_image_url
        AS "profileImageUrl",
    c.creator_user_id
        AS "creatorUserId",
    c.created_at
        AS "createdAt",
    COUNT(member.user_id)
        AS "memberCount"
FROM channel c
INNER JOIN channel_member current_member
    ON current_member.channel_id = c.id
   AND current_member.user_id = :userId
LEFT JOIN channel_member member
    ON member.channel_id = c.id
WHERE c.code = :code
  AND c.closed_at IS NULL
GROUP BY c.id
SQL,
                [
                    'code' => $code,
                    'userId' => $userId,
                ],
            );

        if ($row !== false) {
            return $this->normalize(
                $row
            );
        }

        $exists = $this->connection
            ->fetchOne(
                <<<'SQL'
SELECT 1
FROM channel
WHERE code = :code
  AND closed_at IS NULL
LIMIT 1
SQL,
                [
                    'code' => $code,
                ],
            );

        if ($exists !== false) {
            throw new \DomainException(
                'Channel access denied.'
            );
        }

        throw new \OutOfBoundsException(
            'Channel not found.'
        );
    }

    public static function formatCode(
        string $code,
    ): string {
        if (
            preg_match(
                '/^\d{9}$/',
                $code,
            ) !== 1
        ) {
            return $code;
        }

        return substr($code, 0, 3)
            .'-'
            .substr($code, 3, 3)
            .'-'
            .substr($code, 6, 3);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalize(
        array $row,
    ): array {
        $row['id'] =
            (int) $row['id'];

        $row['code'] =
            trim((string) $row['code']);

        $row['formattedCode'] =
            self::formatCode(
                $row['code']
            );

        $row['creatorUserId'] =
            (int) $row[
                'creatorUserId'
            ];

        $row['memberCount'] =
            (int) $row[
                'memberCount'
            ];

        $row['isCreator'] =
            $row['creatorUserId']
                === $this
                    ->currentUser
                    ->id();

        return $row;
    }

    private function validateCode(
        string $code,
    ): void {
        if (
            preg_match(
                '/^\d{9}$/',
                $code,
            ) !== 1
        ) {
            throw new \InvalidArgumentException(
                'Channel code must contain exactly 9 digits.'
            );
        }
    }
}
