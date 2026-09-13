<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add personal note collections for project organization.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE note_collection (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL
        REFERENCES app_user(id)
        ON DELETE CASCADE,
    name VARCHAR(80) NOT NULL,
    color CHAR(7) NOT NULL
        DEFAULT '#1A73E8',
    created_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),
    CONSTRAINT chk_note_collection_name
        CHECK (
            char_length(trim(name))
            BETWEEN 1 AND 80
        ),
    CONSTRAINT chk_note_collection_color
        CHECK (
            color ~ '^#[0-9A-Fa-f]{6}$'
        )
)
SQL);

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX
    uniq_note_collection_user_name
ON note_collection (
    user_id,
    lower(name)
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_note_collection_user '
            .'ON note_collection(user_id)'
        );

        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD collection_id BIGINT NULL
    REFERENCES note_collection(id)
    ON DELETE SET NULL
SQL);

        $this->addSql(
            'CREATE INDEX idx_note_collection '
            .'ON note(collection_id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX IF EXISTS idx_note_collection'
        );

        $this->addSql(
            'ALTER TABLE note DROP COLUMN collection_id'
        );

        $this->addSql(
            'DROP TABLE IF EXISTS note_collection'
        );
    }
}
