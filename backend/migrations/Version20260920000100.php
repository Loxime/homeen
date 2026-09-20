<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increase personal and channel task content limit to 4000 characters.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE task '
            .'DROP CONSTRAINT IF EXISTS '
            .'chk_task_content_length'
        );

        $this->addSql(
            'ALTER TABLE task '
            .'ALTER COLUMN content TYPE TEXT'
        );

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT chk_task_content_length
CHECK (char_length(content) <= 4000)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE task '
            .'DROP CONSTRAINT IF EXISTS '
            .'chk_task_content_length'
        );

        $this->addSql(<<<'SQL'
UPDATE task
SET content = LEFT(content, 255)
WHERE char_length(content) > 255
SQL);

        $this->addSql(
            'ALTER TABLE task '
            .'ALTER COLUMN content '
            .'TYPE VARCHAR(255)'
        );

        $this->addSql(<<<'SQL'
ALTER TABLE task
ADD CONSTRAINT chk_task_content_length
CHECK (char_length(content) <= 255)
SQL);
    }
}
