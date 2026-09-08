<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Homeen channel invitations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE channel_invitation (
    id BIGSERIAL PRIMARY KEY,

    channel_id BIGINT NOT NULL
        REFERENCES channel(id)
        ON DELETE CASCADE,

    invited_user_id BIGINT NOT NULL
        REFERENCES app_user(id)
        ON DELETE CASCADE,

    invited_by_user_id BIGINT NOT NULL
        REFERENCES app_user(id)
        ON DELETE RESTRICT,

    created_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    seen_at TIMESTAMPTZ NULL,

    CONSTRAINT uniq_channel_invitation
        UNIQUE (
            channel_id,
            invited_user_id
        ),

    CONSTRAINT chk_channel_invitation_users
        CHECK (
            invited_user_id
            <> invited_by_user_id
        )
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_channel_invitation_user '
            .'ON channel_invitation(invited_user_id)'
        );

        $this->addSql(
            'CREATE INDEX idx_channel_invitation_channel '
            .'ON channel_invitation(channel_id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP TABLE IF EXISTS channel_invitation'
        );
    }
}
