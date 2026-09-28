<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add issue title and Markdown description to tasks.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD COLUMN title VARCHAR(255) NULL,
ADD COLUMN description TEXT NOT NULL DEFAULT ''
SQL);

        /*
         * Existing task content becomes the
         * issue title. The legacy content column
         * remains temporarily for compatibility.
         */
        $this->addSql(<<<'SQL'
UPDATE task
SET title = LEFT(content, 255)
WHERE title IS NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE task
ALTER COLUMN title SET NOT NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT chk_task_title_nonempty
CHECK (char_length(trim(title)) > 0)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT chk_task_title_length
CHECK (char_length(title) <= 255)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT chk_task_description_length
CHECK (char_length(description) <= 20000)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE task '
            .'DROP CONSTRAINT IF EXISTS '
            .'chk_task_description_length'
        );

        $this->addSql(
            'ALTER TABLE task '
            .'DROP CONSTRAINT IF EXISTS '
            .'chk_task_title_length'
        );

        $this->addSql(
            'ALTER TABLE task '
            .'DROP CONSTRAINT IF EXISTS '
            .'chk_task_title_nonempty'
        );

        $this->addSql(<<<'SQL'
ALTER TABLE task
DROP COLUMN description,
DROP COLUMN title
SQL);
    }
}
