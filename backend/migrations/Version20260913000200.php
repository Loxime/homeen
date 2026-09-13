<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Pomodoro session feedback for personalized duration recommendations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE pomodoro_session
ADD focus_rating SMALLINT NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE pomodoro_session
ADD rated_at TIMESTAMPTZ NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE pomodoro_session
ADD CONSTRAINT chk_pomodoro_focus_rating
CHECK (
    focus_rating IS NULL
    OR focus_rating BETWEEN 1 AND 3
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_pomodoro_rating '
            .'ON pomodoro_session(user_id, rated_at)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX IF EXISTS idx_pomodoro_rating'
        );

        $this->addSql(
            'ALTER TABLE pomodoro_session '
            .'DROP CONSTRAINT IF EXISTS '
            .'chk_pomodoro_focus_rating'
        );

        $this->addSql(
            'ALTER TABLE pomodoro_session '
            .'DROP COLUMN rated_at'
        );

        $this->addSql(
            'ALTER TABLE pomodoro_session '
            .'DROP COLUMN focus_rating'
        );
    }
}
