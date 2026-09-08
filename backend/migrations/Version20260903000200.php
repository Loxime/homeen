<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user ownership to Homeen personal data.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE label
ADD user_id BIGINT NULL
REFERENCES app_user(id)
ON DELETE CASCADE
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD user_id BIGINT NULL
REFERENCES app_user(id)
ON DELETE CASCADE
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE pomodoro_preset
ADD user_id BIGINT NULL
REFERENCES app_user(id)
ON DELETE CASCADE
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE pomodoro_session
ADD user_id BIGINT NULL
REFERENCES app_user(id)
ON DELETE CASCADE
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE activity_event
ADD user_id BIGINT NULL
REFERENCES app_user(id)
ON DELETE CASCADE
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app_usage_session
ADD user_id BIGINT NULL
REFERENCES app_user(id)
ON DELETE CASCADE
SQL);

        $this->addSql(
            'CREATE INDEX idx_label_user ON label(user_id)'
        );

        $this->addSql(
            'CREATE INDEX idx_note_user ON note(user_id)'
        );

        $this->addSql(
            'CREATE INDEX idx_pomodoro_preset_user ON pomodoro_preset(user_id)'
        );

        $this->addSql(
            'CREATE INDEX idx_pomodoro_session_user ON pomodoro_session(user_id)'
        );

        $this->addSql(
            'CREATE INDEX idx_activity_event_user ON activity_event(user_id)'
        );

        $this->addSql(
            'CREATE INDEX idx_app_usage_session_user ON app_usage_session(user_id)'
        );

        /*
         * Labels used to be globally unique.
         * They are now unique only inside one user's workspace.
         */
        $this->addSql(
            'ALTER TABLE label DROP CONSTRAINT uniq_label_name'
        );

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX uniq_label_user_name
ON label(user_id, name)
WHERE user_id IS NOT NULL
SQL);

        /*
         * Pomodoro presets were also globally unique.
         */
        $this->addSql(
            'ALTER TABLE pomodoro_preset DROP CONSTRAINT uniq_pomodoro_work_minutes'
        );

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX uniq_pomodoro_preset_user_minutes
ON pomodoro_preset(user_id, work_minutes)
WHERE user_id IS NOT NULL
SQL);

        /*
         * Previously Homeen allowed only one active Pomodoro globally.
         * We now allow one active Pomodoro PER USER.
         */
        $this->addSql(
            'DROP INDEX uniq_active_pomodoro_session'
        );

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX uniq_active_pomodoro_session_per_user
ON pomodoro_session(user_id)
WHERE stopped_at IS NULL
  AND user_id IS NOT NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX IF EXISTS uniq_active_pomodoro_session_per_user'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS uniq_pomodoro_preset_user_minutes'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS uniq_label_user_name'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS idx_app_usage_session_user'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS idx_activity_event_user'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS idx_pomodoro_session_user'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS idx_pomodoro_preset_user'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS idx_note_user'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS idx_label_user'
        );

        $this->addSql(
            'ALTER TABLE app_usage_session DROP COLUMN user_id'
        );

        $this->addSql(
            'ALTER TABLE activity_event DROP COLUMN user_id'
        );

        $this->addSql(
            'ALTER TABLE pomodoro_session DROP COLUMN user_id'
        );

        $this->addSql(
            'ALTER TABLE pomodoro_preset DROP COLUMN user_id'
        );

        $this->addSql(
            'ALTER TABLE note DROP COLUMN user_id'
        );

        $this->addSql(
            'ALTER TABLE label DROP COLUMN user_id'
        );

        $this->addSql(
            'ALTER TABLE label ADD CONSTRAINT uniq_label_name UNIQUE (name)'
        );

        $this->addSql(
            'ALTER TABLE pomodoro_preset ADD CONSTRAINT uniq_pomodoro_work_minutes UNIQUE (work_minutes)'
        );

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX uniq_active_pomodoro_session
ON pomodoro_session ((1))
WHERE stopped_at IS NULL
SQL);
    }
}
