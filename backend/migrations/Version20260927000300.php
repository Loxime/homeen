<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stable per-project task numbers.';
    }

    public function up(Schema $schema): void
    {
        /*
         * The counter belongs to the Project
         * so deleted task numbers are never
         * reused.
         */
        $this->addSql(<<<'SQL'
ALTER TABLE project
ADD COLUMN next_task_number BIGINT
NOT NULL
DEFAULT 1
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE project
ADD CONSTRAINT chk_project_next_task_number
CHECK (
    next_task_number > 0
)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD COLUMN project_task_number BIGINT NULL
SQL);

        /*
         * Give historical native Project tasks
         * deterministic numbers starting at #1
         * independently for every Project.
         */
        $this->addSql(<<<'SQL'
WITH numbered AS (
    SELECT
        id,
        ROW_NUMBER() OVER (
            PARTITION BY project_id
            ORDER BY
                created_at ASC,
                id ASC
        ) AS task_number
    FROM task
    WHERE project_id IS NOT NULL
      AND note_id IS NULL
)
UPDATE task
SET project_task_number =
    numbered.task_number
FROM numbered
WHERE task.id = numbered.id
SQL);

        /*
         * Continue after the highest historical
         * number. This counter only increases.
         */
        $this->addSql(<<<'SQL'
UPDATE project
SET next_task_number =
    COALESCE(
        (
            SELECT
                MAX(
                    task.project_task_number
                ) + 1
            FROM task
            WHERE task.project_id =
                    project.id
              AND task.note_id IS NULL
        ),
        1
    )
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT chk_task_project_number
CHECK (
    (
        note_id IS NOT NULL
        AND project_task_number IS NULL
    )
    OR
    (
        note_id IS NULL
        AND project_id IS NOT NULL
        AND project_task_number IS NOT NULL
        AND project_task_number > 0
    )
)
SQL);

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX uniq_task_project_number
ON task (
    project_id,
    project_task_number
)
WHERE project_id IS NOT NULL
  AND project_task_number IS NOT NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        /*
         * Once numbers have been exposed as
         * stable task identities, rebuilding
         * them after deletions could change
         * those identities.
         */
        $this->throwIrreversibleMigrationException(
            'Project task numbers are stable identities and cannot be safely reconstructed after rollback.'
        );
    }
}
