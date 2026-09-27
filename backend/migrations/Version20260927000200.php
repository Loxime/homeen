<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Separate text notes from task lists without losing mixed legacy notes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD note_type VARCHAR(8) NULL
SQL);

        /*
         * Split every historical mixed note:
         *
         * - keep the original note as text;
         * - create a sibling list;
         * - copy note metadata and tags;
         * - move existing tasks without changing
         *   their identifiers or task metadata.
         */
        $this->addSql(<<<'SQL'
DO $$
DECLARE
    source_note RECORD;
    list_note_id BIGINT;
BEGIN
    FOR source_note IN
        SELECT n.*
        FROM note n
        WHERE btrim(COALESCE(n.content, '')) <> ''
          AND EXISTS (
              SELECT 1
              FROM task t
              WHERE t.note_id = n.id
          )
        ORDER BY n.id
    LOOP
        INSERT INTO note (
            label_id,
            title,
            content,
            created_at,
            updated_at,
            archived_at,
            deleted_at,
            user_id,
            channel_id,
            created_by_user_id,
            version,
            project_id,
            is_pinned,
            color,
            note_type
        )
        VALUES (
            source_note.label_id,
            source_note.title,
            '',
            source_note.created_at,
            source_note.updated_at,
            source_note.archived_at,
            source_note.deleted_at,
            source_note.user_id,
            source_note.channel_id,
            source_note.created_by_user_id,
            source_note.version,
            source_note.project_id,
            FALSE,
            source_note.color,
            'list'
        )
        RETURNING id
        INTO list_note_id;

        INSERT INTO note_tag (
            note_id,
            tag_id
        )
        SELECT
            list_note_id,
            note_tag.tag_id
        FROM note_tag
        WHERE note_tag.note_id =
            source_note.id;

        UPDATE task
        SET note_id = list_note_id
        WHERE note_id = source_note.id;

        UPDATE note
        SET note_type = 'text'
        WHERE id = source_note.id;
    END LOOP;
END
$$
SQL);

        /*
         * Remaining historical notes are now
         * unambiguous.
         */
        $this->addSql(<<<'SQL'
UPDATE note n
SET note_type = CASE
    WHEN EXISTS (
        SELECT 1
        FROM task t
        WHERE t.note_id = n.id
    )
        THEN 'list'
    ELSE 'text'
END
WHERE note_type IS NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE note
ALTER COLUMN note_type
SET DEFAULT 'text'
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE note
ALTER COLUMN note_type
SET NOT NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD CONSTRAINT chk_note_type
CHECK (
    note_type IN (
        'text',
        'list'
    )
)
SQL);

        /*
         * Lists do not have a free-text body.
         */
        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD CONSTRAINT chk_note_list_content
CHECK (
    note_type <> 'list'
    OR btrim(content) = ''
)
SQL);

        /*
         * A note-bound task may only target
         * a list note. Native Project tasks
         * have note_id = NULL and are unaffected.
         */
        $this->addSql(<<<'SQL'
CREATE FUNCTION enforce_task_list_note()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.note_id IS NOT NULL
       AND NOT EXISTS (
           SELECT 1
           FROM note
           WHERE id = NEW.note_id
             AND note_type = 'list'
       )
    THEN
        RAISE EXCEPTION
            'Note-bound tasks require a list note';
    END IF;

    RETURN NEW;
END
$$
SQL);

        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_task_list_note
BEFORE INSERT OR UPDATE OF note_id
ON task
FOR EACH ROW
EXECUTE FUNCTION enforce_task_list_note()
SQL);

        /*
         * Also prevent a list containing tasks
         * from being changed to a text note by
         * direct SQL.
         */
        $this->addSql(<<<'SQL'
CREATE FUNCTION enforce_note_type_tasks()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.note_type = 'text'
       AND EXISTS (
           SELECT 1
           FROM task
           WHERE note_id = NEW.id
       )
    THEN
        RAISE EXCEPTION
            'A text note cannot contain tasks';
    END IF;

    RETURN NEW;
END
$$
SQL);

        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_note_type_tasks
BEFORE UPDATE OF note_type
ON note
FOR EACH ROW
EXECUTE FUNCTION enforce_note_type_tasks()
SQL);
    }

    public function down(Schema $schema): void
    {
        /*
         * This migration splits historical
         * mixed notes into two records.
         *
         * The original relation cannot be
         * reconstructed reliably afterwards.
         */
        $this->throwIrreversibleMigrationException(
            'Text/list migration splits mixed notes and cannot be reversed safely.'
        );
    }
}
