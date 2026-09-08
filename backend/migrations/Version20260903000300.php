<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Homeen collaboration channels and memberships.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE channel (
    id BIGSERIAL PRIMARY KEY,

    code CHAR(9) NOT NULL,

    name VARCHAR(120) NOT NULL,

    description TEXT NOT NULL
        DEFAULT '',

    profile_image_url VARCHAR(2048)
        NULL,

    creator_user_id BIGINT NOT NULL
        REFERENCES app_user(id)
        ON DELETE RESTRICT,

    created_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    closed_at TIMESTAMPTZ NULL,

    CONSTRAINT uniq_channel_code
        UNIQUE (code),

    CONSTRAINT chk_channel_code
        CHECK (
            code ~ '^[0-9]{9}$'
        )
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE channel_member (
    channel_id BIGINT NOT NULL
        REFERENCES channel(id)
        ON DELETE CASCADE,

    user_id BIGINT NOT NULL
        REFERENCES app_user(id)
        ON DELETE CASCADE,

    joined_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    last_read_message_at
        TIMESTAMPTZ NULL,

    PRIMARY KEY (
        channel_id,
        user_id
    )
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_channel_creator '
            .'ON channel(creator_user_id)'
        );

        $this->addSql(
            'CREATE INDEX idx_channel_member_user '
            .'ON channel_member(user_id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP TABLE IF EXISTS channel_member'
        );

        $this->addSql(
            'DROP TABLE IF EXISTS channel'
        );
    }
}
