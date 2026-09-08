<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final readonly class ChannelSettingsRepository
{
    public function __construct(
        private Connection $connection,
        private ChannelRepository $channels,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function update(
        string $code,
        string $name,
        string $description,
        ?string $profileImageUrl,
    ): array {
        $channel =
            $this->requireCreator(
                $code,
            );

        $name =
            trim(
                $name,
            );

        $description =
            trim(
                $description,
            );

        $profileImageUrl =
            $this->normalizeProfileImageUrl(
                $profileImageUrl,
            );

        if (
            $name === ''
            || mb_strlen($name) > 120
        ) {
            throw new \InvalidArgumentException(
                'Channel name must contain between 1 and 120 characters.',
            );
        }

        if (
            mb_strlen($description) > 2000
        ) {
            throw new \InvalidArgumentException(
                'Channel description cannot exceed 2000 characters.',
            );
        }

        $affected =
            $this->connection
                ->executeStatement(
                    <<<'SQL'
UPDATE channel
SET
    name = :name,
    description = :description,
    profile_image_url = :profileImageUrl,
    updated_at = NOW()
WHERE id = :channelId
  AND closed_at IS NULL
SQL,
                    [
                        'name' =>
                            $name,

                        'description' =>
                            $description,

                        'profileImageUrl' =>
                            $profileImageUrl,

                        'channelId' =>
                            (int) $channel['id'],
                    ],
                );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Channel not found.',
            );
        }

        return $this->channels
            ->getAccessible(
                $code,
            );
    }

    public function close(
        string $code,
    ): void {
        $channel =
            $this->requireCreator(
                $code,
            );

        $affected =
            $this->connection
                ->executeStatement(
                    <<<'SQL'
UPDATE channel
SET
    closed_at = NOW(),
    updated_at = NOW()
WHERE id = :channelId
  AND closed_at IS NULL
SQL,
                    [
                        'channelId' =>
                            (int) $channel['id'],
                    ],
                );

        if ($affected !== 1) {
            throw new \OutOfBoundsException(
                'Channel not found.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requireCreator(
        string $code,
    ): array {
        $channel =
            $this->channels
                ->getAccessible(
                    $code,
                );

        if (
            !(
                $channel['isCreator']
                ?? false
            )
        ) {
            throw new \DomainException(
                'Only the channel creator can change its settings.',
            );
        }

        return $channel;
    }

    private function normalizeProfileImageUrl(
        ?string $profileImageUrl,
    ): ?string {
        if ($profileImageUrl === null) {
            return null;
        }

        $profileImageUrl =
            trim(
                $profileImageUrl,
            );

        if ($profileImageUrl === '') {
            return null;
        }

        if (
            mb_strlen($profileImageUrl)
            > 2048
        ) {
            throw new \InvalidArgumentException(
                'Channel image URL cannot exceed 2048 characters.',
            );
        }

        if (
            filter_var(
                $profileImageUrl,
                FILTER_VALIDATE_URL,
            ) === false
        ) {
            throw new \InvalidArgumentException(
                'Channel image URL is invalid.',
            );
        }

        $scheme =
            strtolower(
                (string) parse_url(
                    $profileImageUrl,
                    PHP_URL_SCHEME,
                ),
            );

        if (
            !in_array(
                $scheme,
                [
                    'http',
                    'https',
                ],
                true,
            )
        ) {
            throw new \InvalidArgumentException(
                'Channel image URL must use HTTP or HTTPS.',
            );
        }

        return $profileImageUrl;
    }
}
