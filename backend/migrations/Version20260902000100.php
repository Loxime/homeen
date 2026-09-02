<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Homeen notes, labels, tasks, Pomodoro, activity log, and usage tracking tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE label (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    color CHAR(7) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uniq_label_name UNIQUE (name),
    CONSTRAINT chk_label_color CHECK (color ~ '^#[0-9A-Fa-f]{6}$')
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE note (
    id BIGSERIAL PRIMARY KEY,
    label_id BIGINT NULL REFERENCES label(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    content TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    archived_at TIMESTAMPTZ NULL,
    deleted_at TIMESTAMPTZ NULL
)
SQL);
        $this->addSql('CREATE INDEX idx_note_label ON note(label_id)');
        $this->addSql('CREATE INDEX idx_note_archived ON note(archived_at)');
        $this->addSql('CREATE INDEX idx_note_deleted ON note(deleted_at)');

        $this->addSql(<<<'SQL'
CREATE TABLE task (
    id BIGSERIAL PRIMARY KEY,
    note_id BIGINT NOT NULL REFERENCES note(id) ON DELETE CASCADE,
    content VARCHAR(255) NOT NULL,
    is_completed BOOLEAN NOT NULL DEFAULT FALSE,
    completed_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_task_content_nonempty CHECK (char_length(trim(content)) > 0),
    CONSTRAINT chk_task_content_length CHECK (char_length(content) <= 255)
)
SQL);
        $this->addSql('CREATE INDEX idx_task_note ON task(note_id)');
        $this->addSql('CREATE INDEX idx_task_completed ON task(completed_at)');

        $this->addSql(<<<'SQL'
CREATE TABLE pomodoro_preset (
    id BIGSERIAL PRIMARY KEY,
    work_minutes INTEGER NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_used_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uniq_pomodoro_work_minutes UNIQUE (work_minutes),
    CONSTRAINT chk_pomodoro_work_minutes CHECK (work_minutes >= 5)
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE pomodoro_session (
    id BIGSERIAL PRIMARY KEY,
    preset_id BIGINT NULL REFERENCES pomodoro_preset(id) ON DELETE SET NULL,
    work_minutes_snapshot INTEGER NOT NULL,
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    stopped_at TIMESTAMPTZ NULL,
    focus_seconds INTEGER NOT NULL DEFAULT 0,
    break_seconds INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT chk_session_work_minutes CHECK (work_minutes_snapshot >= 5),
    CONSTRAINT chk_session_times CHECK (stopped_at IS NULL OR stopped_at >= started_at)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_active_pomodoro_session ON pomodoro_session ((1)) WHERE stopped_at IS NULL');
        $this->addSql('CREATE INDEX idx_pomodoro_started ON pomodoro_session(started_at)');

        $this->addSql(<<<'SQL'
CREATE TABLE activity_event (
    id BIGSERIAL PRIMARY KEY,
    event_type VARCHAR(64) NOT NULL,
    entity_type VARCHAR(64) NULL,
    entity_id BIGINT NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
)
SQL);
        $this->addSql('CREATE INDEX idx_activity_event_type_time ON activity_event(event_type, occurred_at)');
        $this->addSql('CREATE INDEX idx_activity_event_time ON activity_event(occurred_at)');

        $this->addSql(<<<'SQL'
CREATE TABLE app_usage_session (
    id BIGSERIAL PRIMARY KEY,
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_seen_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ended_at TIMESTAMPTZ NULL,
    active_seconds INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT chk_usage_active_seconds CHECK (active_seconds >= 0)
)
SQL);
        $this->addSql('CREATE INDEX idx_app_usage_started ON app_usage_session(started_at)');

        $this->addSql(<<<'SQL'
CREATE TABLE app_usage_slice (
    id BIGSERIAL PRIMARY KEY,
    session_id BIGINT NOT NULL REFERENCES app_usage_session(id) ON DELETE CASCADE,
    active_seconds SMALLINT NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_usage_slice_seconds CHECK (active_seconds > 0 AND active_seconds <= 60)
)
SQL);
        $this->addSql('CREATE INDEX idx_app_usage_slice_time ON app_usage_slice(occurred_at)');
        $this->addSql('CREATE INDEX idx_app_usage_slice_session ON app_usage_slice(session_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_usage_slice');
        $this->addSql('DROP TABLE IF EXISTS app_usage_session');
        $this->addSql('DROP TABLE IF EXISTS activity_event');
        $this->addSql('DROP TABLE IF EXISTS pomodoro_session');
        $this->addSql('DROP TABLE IF EXISTS pomodoro_preset');
        $this->addSql('DROP TABLE IF EXISTS task');
        $this->addSql('DROP TABLE IF EXISTS note');
        $this->addSql('DROP TABLE IF EXISTS label');
    }
}
