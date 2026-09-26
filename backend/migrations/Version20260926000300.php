<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reusable note tags and migrate existing note labels.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE note_tag (
    note_id BIGINT NOT NULL
        REFERENCES note(id)
        ON DELETE CASCADE,
    tag_id BIGINT NOT NULL
        REFERENCES tag(id)
        ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),
    PRIMARY KEY (
        note_id,
        tag_id
    )
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_note_tag_tag '
            .'ON note_tag(tag_id, note_id)'
        );

        /*
         * Convert each user-owned legacy label
         * into a reusable tag.
         *
         * Existing tags with the same
         * case-insensitive name are reused.
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
        n.user_id,
        lower(l.name)
    )
        n.user_id,
        l.name,
        l.color,
        l.created_at,
        l.updated_at,
        l.id
    FROM note n
    INNER JOIN label l
        ON l.id = n.label_id
    WHERE n.user_id IS NOT NULL
      AND n.label_id IS NOT NULL
    ORDER BY
        n.user_id,
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

        /*
         * Preserve every existing note/label
         * association through note_tag.
         */
        $this->addSql(<<<'SQL'
INSERT INTO note_tag (
    note_id,
    tag_id
)
SELECT
    n.id,
    tag.id
FROM note n
INNER JOIN label l
    ON l.id = n.label_id
INNER JOIN tag
    ON tag.user_id = n.user_id
   AND lower(tag.name) =
       lower(l.name)
WHERE n.user_id IS NOT NULL
  AND n.label_id IS NOT NULL
ON CONFLICT DO NOTHING
SQL);
    }

    public function down(Schema $schema): void
    {
        /*
         * Legacy label_id values are deliberately
         * left untouched by up(), so dropping the
         * join table restores the previous model.
         *
         * Migrated tags are kept because the tag
         * model already existed before this
         * migration and those tags may also have
         * been attached to tasks.
         */
        $this->addSql(
            'DROP TABLE IF EXISTS note_tag'
        );
    }
}
