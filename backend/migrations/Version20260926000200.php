<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add start and due dates to tasks.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD COLUMN start_date DATE NULL,
ADD COLUMN due_date DATE NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT chk_task_date_range
CHECK (
    start_date IS NULL
    OR due_date IS NULL
    OR due_date >= start_date
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_task_start_date '
            .'ON task(start_date) '
            .'WHERE start_date IS NOT NULL'
        );

        $this->addSql(
            'CREATE INDEX idx_task_due_date '
            .'ON task(due_date) '
            .'WHERE due_date IS NOT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX IF EXISTS idx_task_due_date'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS idx_task_start_date'
        );

        $this->addSql(
            'ALTER TABLE task '
            .'DROP CONSTRAINT IF EXISTS chk_task_date_range'
        );

        $this->addSql(
            'ALTER TABLE task '
            .'DROP COLUMN IF EXISTS due_date'
        );

        $this->addSql(
            'ALTER TABLE task '
            .'DROP COLUMN IF EXISTS start_date'
        );
    }
}
