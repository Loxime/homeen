<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926000900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add native project tasks with workflow stage ownership.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE project_workflow_stage
ADD CONSTRAINT uniq_project_workflow_project_stage
UNIQUE (
    project_id,
    id
)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE task
ALTER COLUMN note_id DROP NOT NULL,
ADD COLUMN project_id BIGINT NULL,
ADD COLUMN workflow_stage_id BIGINT NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT fk_task_project
FOREIGN KEY (project_id)
REFERENCES project(id)
ON DELETE CASCADE
SQL);

        /*
         * Composite FK guarantees that the
         * selected workflow stage belongs to
         * the same Project as the task.
         */
        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT fk_task_project_workflow_stage
FOREIGN KEY (
    project_id,
    workflow_stage_id
)
REFERENCES project_workflow_stage (
    project_id,
    id
)
ON DELETE RESTRICT
SQL);

        /*
         * A task belongs either to a note OR
         * directly to a Project, never both.
         */
        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT chk_task_single_scope
CHECK (
    (
        note_id IS NOT NULL
        AND project_id IS NULL
        AND workflow_stage_id IS NULL
    )
    OR
    (
        note_id IS NULL
        AND project_id IS NOT NULL
        AND workflow_stage_id IS NOT NULL
    )
)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_task_project_stage_position
ON task (
    project_id,
    workflow_stage_id,
    position,
    id
)
WHERE project_id IS NOT NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        /*
         * Never silently destroy native
         * Project tasks during a rollback.
         */
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM task
        WHERE project_id IS NOT NULL
    ) THEN
        RAISE EXCEPTION
            'Cannot rollback project tasks while native project tasks exist';
    END IF;
END
$$
SQL);

        $this->addSql(
            'DROP INDEX IF EXISTS '
            .'idx_task_project_stage_position'
        );

        $this->addSql(
            'ALTER TABLE task '
            .'DROP CONSTRAINT IF EXISTS '
            .'chk_task_single_scope'
        );

        $this->addSql(
            'ALTER TABLE task '
            .'DROP CONSTRAINT IF EXISTS '
            .'fk_task_project_workflow_stage'
        );

        $this->addSql(
            'ALTER TABLE task '
            .'DROP CONSTRAINT IF EXISTS '
            .'fk_task_project'
        );

        $this->addSql(<<<'SQL'
ALTER TABLE task
DROP COLUMN IF EXISTS workflow_stage_id,
DROP COLUMN IF EXISTS project_id
SQL);

        $this->addSql(
            'ALTER TABLE task '
            .'ALTER COLUMN note_id SET NOT NULL'
        );

        $this->addSql(
            'ALTER TABLE project_workflow_stage '
            .'DROP CONSTRAINT IF EXISTS '
            .'uniq_project_workflow_project_stage'
        );
    }
}
