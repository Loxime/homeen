<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Homeen users and multiple login email addresses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE app_user (
    id BIGSERIAL PRIMARY KEY,
    password_hash VARCHAR(255) NOT NULL,
    must_change_password BOOLEAN NOT NULL DEFAULT TRUE,
    temporary_password_consumed_at TIMESTAMPTZ NULL,
    password_changed_at TIMESTAMPTZ NULL,
    notification_sound_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE user_email (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL
        REFERENCES app_user(id)
        ON DELETE CASCADE,
    email VARCHAR(254) NOT NULL,
    normalized_email VARCHAR(254) NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uniq_user_email_normalized
        UNIQUE (normalized_email)
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_user_email_user ON user_email(user_id)'
        );

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX uniq_user_primary_email
ON user_email(user_id)
WHERE is_primary = TRUE
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS user_email');
        $this->addSql('DROP TABLE IF EXISTS app_user');
    }
}
