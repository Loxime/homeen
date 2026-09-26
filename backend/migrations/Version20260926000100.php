<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prepare Sprint 1 task model and add reusable task tags.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE tag (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL
        REFERENCES app_user(id)
        ON DELETE CASCADE,
    name VARCHAR(80) NOT NULL,
    color CHAR(7) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_tag_name_nonempty
        CHECK (char_length(trim(name)) > 0),
    CONSTRAINT chk_tag_color
        CHECK (color ~ '^#[0-9A-Fa-f]{6}$')
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_tag_user '
            .'ON tag(user_id)'
        );

        $this->addSql(
            'CREATE UNIQUE INDEX uniq_tag_user_name '
            .'ON tag(user_id, lower(name))'
        );

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD COLUMN priority VARCHAR(16)
    NOT NULL DEFAULT 'normal',
ADD COLUMN status VARCHAR(20)
    NOT NULL DEFAULT 'todo',
ADD COLUMN position INTEGER
    NOT NULL DEFAULT 0
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT chk_task_priority
CHECK (
    priority IN (
        'low',
        'normal',
        'high',
        'urgent'
    )
)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT chk_task_status
CHECK (
    status IN (
        'todo',
        'in_progress',
        'done'
    )
)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT chk_task_position
CHECK (position >= 0)
SQL);

        $this->addSql(<<<'SQL'
UPDATE task
SET status = CASE
    WHEN is_completed = TRUE
        THEN 'done'
    ELSE 'todo'
END
SQL);

        $this->addSql(
            'CREATE INDEX idx_task_status '
            .'ON task(status)'
        );

        $this->addSql(
            'CREATE INDEX idx_task_priority '
            .'ON task(priority)'
        );

        $this->addSql(
            'CREATE INDEX idx_task_note_position '
            .'ON task(note_id, position, id)'
        );

        $this->addSql(<<<'SQL'
CREATE TABLE task_tag (
    task_id BIGINT NOT NULL
        REFERENCES task(id)
        ON DELETE CASCADE,
    tag_id BIGINT NOT NULL
        REFERENCES tag(id)
        ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (task_id, tag_id)
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_task_tag_tag '
            .'ON task_tag(tag_id, task_id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP TABLE IF EXISTS task_tag'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS '
            .'idx_task_note_position'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS '
            .'idx_task_priority'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS '
            .'idx_task_status'
        );

        $this->addSql(
            'ALTER TABLE task '
            .'DROP CONSTRAINT IF EXISTS '
            .'chk_task_position'
        );

        $this->addSql(
            'ALTER TABLE task '
            .'DROP CONSTRAINT IF EXISTS '
            .'chk_task_status'
        );

        $this->addSql(
            'ALTER TABLE task '
            .'DROP CONSTRAINT IF EXISTS '
            .'chk_task_priority'
        );

        $this->addSql(<<<'SQL'
ALTER TABLE task
DROP COLUMN IF EXISTS position,
DROP COLUMN IF EXISTS status,
DROP COLUMN IF EXISTS priority
SQL);

        $this->addSql(
            'DROP TABLE IF EXISTS tag'
        );
    }
}
