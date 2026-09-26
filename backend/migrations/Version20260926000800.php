<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926000800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add configurable project workflow stages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE project_workflow_stage (
    id BIGSERIAL PRIMARY KEY,

    project_id BIGINT NOT NULL
        REFERENCES project(id)
        ON DELETE CASCADE,

    name VARCHAR(80) NOT NULL,

    position SMALLINT NOT NULL,

    created_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    CONSTRAINT chk_project_workflow_name
        CHECK (
            char_length(trim(name))
                BETWEEN 1 AND 80
        ),

    CONSTRAINT chk_project_workflow_position
        CHECK (
            position >= 0
            AND position < 20
        ),

    CONSTRAINT uniq_project_workflow_position
        UNIQUE (
            project_id,
            position
        )
        DEFERRABLE INITIALLY IMMEDIATE
)
SQL);

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX
    uniq_project_workflow_name
ON project_workflow_stage (
    project_id,
    lower(name)
)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_project_workflow_project
ON project_workflow_stage (
    project_id,
    position
)
SQL);

        /*
         * Existing projects get a safe default
         * workflow. It can immediately be
         * customized through the API.
         */
        $this->addSql(<<<'SQL'
INSERT INTO project_workflow_stage (
    project_id,
    name,
    position
)
SELECT
    project.id,
    defaults.name,
    defaults.position
FROM project
CROSS JOIN (
    VALUES
        ('Backlog', 0),
        ('En cours', 1),
        ('Terminé', 2)
) AS defaults (
    name,
    position
)
ORDER BY
    project.id,
    defaults.position
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP TABLE IF EXISTS '
            .'project_workflow_stage'
        );
    }
}
