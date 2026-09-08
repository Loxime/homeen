<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow closed channels to outlive a deleted creator.';
    }

    public function up(Schema $schema): void
    {
        /*
         * Active channels still require a creator at
         * application level.
         *
         * A closed channel, however, may be retained
         * after its former creator deletes their account.
         *
         * The existing FK remains ON DELETE RESTRICT:
         * UserRepository explicitly nulls only CLOSED
         * channels before deleting the account.
         */
        $this->addSql(
            'ALTER TABLE channel '
            .'ALTER COLUMN creator_user_id DROP NOT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        /*
         * Refuse an unsafe rollback rather than deleting
         * preserved collaboration data silently.
         */
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM channel
        WHERE creator_user_id IS NULL
    ) THEN
        RAISE EXCEPTION
            'Cannot restore creator_user_id NOT NULL while preserved channels have no creator';
    END IF;
END
$$
SQL);

        $this->addSql(
            'ALTER TABLE channel '
            .'ALTER COLUMN creator_user_id SET NOT NULL'
        );
    }
}
