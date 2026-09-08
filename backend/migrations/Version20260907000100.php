<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add channel ownership, authorship, and versioning to notes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD COLUMN channel_id BIGINT NULL
    REFERENCES channel(id)
    ON DELETE CASCADE
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD COLUMN created_by_user_id BIGINT NULL
    REFERENCES app_user(id)
    ON DELETE SET NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD COLUMN version INTEGER NOT NULL
    DEFAULT 1
SQL);

        /*
         * A note may not simultaneously belong
         * to a personal user and a channel.
         *
         * We intentionally still allow both to
         * be NULL for the staged legacy-data
         * migration strategy.
         */
        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD CONSTRAINT chk_note_single_owner
CHECK (
    NOT (
        user_id IS NOT NULL
        AND channel_id IS NOT NULL
    )
)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD CONSTRAINT chk_note_version
CHECK (version >= 1)
SQL);

        $this->addSql(
            'CREATE INDEX idx_note_channel '
            .'ON note(channel_id)'
        );

        $this->addSql(
            'CREATE INDEX idx_note_created_by '
            .'ON note(created_by_user_id)'
        );

        $this->addSql(
            'CREATE INDEX idx_note_channel_updated '
            .'ON note(channel_id, updated_at DESC)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX IF EXISTS idx_note_channel_updated'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS idx_note_created_by'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS idx_note_channel'
        );

        $this->addSql(
            'ALTER TABLE note '
            .'DROP CONSTRAINT IF EXISTS chk_note_version'
        );

        $this->addSql(
            'ALTER TABLE note '
            .'DROP CONSTRAINT IF EXISTS chk_note_single_owner'
        );

        $this->addSql(
            'ALTER TABLE note '
            .'DROP COLUMN IF EXISTS version'
        );

        $this->addSql(
            'ALTER TABLE note '
            .'DROP COLUMN IF EXISTS created_by_user_id'
        );

        $this->addSql(
            'ALTER TABLE note '
            .'DROP COLUMN IF EXISTS channel_id'
        );
    }
}
