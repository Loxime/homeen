<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926000500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Keep-like pinning and colors to personal notes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD is_pinned BOOLEAN NOT NULL
    DEFAULT FALSE
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD color CHAR(7) NOT NULL
    DEFAULT '#FFFFFF'
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD CONSTRAINT chk_note_color
CHECK (
    color ~ '^#[0-9A-Fa-f]{6}$'
)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_note_user_pinned_updated
ON note (
    user_id,
    is_pinned DESC,
    updated_at DESC,
    id DESC
)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX IF EXISTS '
            .'idx_note_user_pinned_updated'
        );

        $this->addSql(
            'ALTER TABLE note '
            .'DROP CONSTRAINT IF EXISTS chk_note_color'
        );

        $this->addSql(
            'ALTER TABLE note '
            .'DROP COLUMN IF EXISTS color'
        );

        $this->addSql(
            'ALTER TABLE note '
            .'DROP COLUMN IF EXISTS is_pinned'
        );
    }
}
