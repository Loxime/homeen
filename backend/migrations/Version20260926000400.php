<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve unused legacy labels as reusable tags.';
    }

    public function up(Schema $schema): void
    {
        /*
         * Version20260926000300 converted labels that were
         * attached to notes. Preserve user-owned labels that
         * currently have no note association as well.
         *
         * Legacy labels were case-sensitive while tags are
         * unique case-insensitively, so collapse collisions
         * deterministically by the oldest label id.
         */
        $this->addSql(<<<'SQL'
INSERT INTO tag (
    user_id,
    name,
    color,
    created_at,
    updated_at
)
SELECT
    candidate.user_id,
    candidate.name,
    candidate.color,
    candidate.created_at,
    candidate.updated_at
FROM (
    SELECT DISTINCT ON (
        l.user_id,
        lower(l.name)
    )
        l.user_id,
        l.name,
        l.color,
        l.created_at,
        l.updated_at,
        l.id
    FROM label l
    WHERE l.user_id IS NOT NULL
    ORDER BY
        l.user_id,
        lower(l.name),
        l.id
) candidate
WHERE NOT EXISTS (
    SELECT 1
    FROM tag existing
    WHERE existing.user_id =
        candidate.user_id
      AND lower(existing.name) =
        lower(candidate.name)
)
SQL);
    }

    public function down(Schema $schema): void
    {
        /*
         * Do not delete tags on rollback: after migration,
         * they may have been attached to notes or tasks.
         */
    }
}
