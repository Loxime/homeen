<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persistent chat messages to Homeen channels.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE channel_message (
    id BIGSERIAL PRIMARY KEY,
    channel_id BIGINT NOT NULL
        REFERENCES channel(id)
        ON DELETE CASCADE,
    author_user_id BIGINT NULL
        REFERENCES app_user(id)
        ON DELETE SET NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),
    edited_at TIMESTAMPTZ NULL,
    CONSTRAINT chk_channel_message_content
        CHECK (
            char_length(trim(content))
            BETWEEN 1 AND 4000
        )
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_channel_message_channel_id '
            .'ON channel_message(channel_id, id)'
        );

        $this->addSql(
            'CREATE INDEX idx_channel_message_author '
            .'ON channel_message(author_user_id)'
        );

        $this->addSql(
            'CREATE INDEX idx_channel_message_created '
            .'ON channel_message(channel_id, created_at)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP TABLE IF EXISTS channel_message'
        );
    }
}
