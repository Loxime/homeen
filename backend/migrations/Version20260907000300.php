<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add administrator and member roles to Homeen channel memberships.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
ALTER TABLE channel_member
ADD COLUMN role VARCHAR(16) NOT NULL DEFAULT 'member'
SQL
        );

        $this->addSql(
            <<<'SQL'
ALTER TABLE channel_member
ADD CONSTRAINT chk_channel_member_role
CHECK (role IN ('member', 'admin'))
SQL
        );

        $this->addSql(
            <<<'SQL'
CREATE INDEX idx_channel_member_role
ON channel_member (channel_id, role)
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX idx_channel_member_role'
        );

        $this->addSql(
            'ALTER TABLE channel_member DROP CONSTRAINT chk_channel_member_role'
        );

        $this->addSql(
            'ALTER TABLE channel_member DROP COLUMN role'
        );
    }
}
