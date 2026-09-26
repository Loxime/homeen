<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926000600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add projects, project memberships, and migrate legacy collections and channels.';
    }

    public function up(Schema $schema): void
    {
        /*
         * A note can only map to one project.
         * Refuse migration rather than silently
         * choosing between legacy sources.
         */
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM note
        WHERE collection_id IS NOT NULL
          AND channel_id IS NOT NULL
    ) THEN
        RAISE EXCEPTION
            'Cannot migrate notes linked to both a collection and a channel';
    END IF;
END
$$
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE project (
    id BIGSERIAL PRIMARY KEY,

    name VARCHAR(120) NOT NULL,

    description TEXT NOT NULL
        DEFAULT '',

    color CHAR(7) NOT NULL
        DEFAULT '#1A73E8',

    image_url VARCHAR(2048) NULL,

    created_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    archived_at TIMESTAMPTZ NULL,

    /*
     * Temporary migration provenance.
     *
     * No foreign keys intentionally:
     * legacy tables will eventually disappear.
     */
    legacy_collection_id BIGINT NULL,
    legacy_channel_id BIGINT NULL,

    CONSTRAINT chk_project_name
        CHECK (
            char_length(trim(name))
            BETWEEN 1 AND 120
        ),

    CONSTRAINT chk_project_description
        CHECK (
            char_length(description)
            <= 4000
        ),

    CONSTRAINT chk_project_color
        CHECK (
            color ~ '^#[0-9A-Fa-f]{6}$'
        ),

    CONSTRAINT chk_project_legacy_source
        CHECK (
            NOT (
                legacy_collection_id IS NOT NULL
                AND legacy_channel_id IS NOT NULL
            )
        )
)
SQL);

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX
    uniq_project_legacy_collection
ON project (legacy_collection_id)
WHERE legacy_collection_id IS NOT NULL
SQL);

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX
    uniq_project_legacy_channel
ON project (legacy_channel_id)
WHERE legacy_channel_id IS NOT NULL
SQL);

        $this->addSql(
            'CREATE INDEX idx_project_updated '
            .'ON project(updated_at DESC, id DESC)'
        );

        $this->addSql(
            'CREATE INDEX idx_project_archived '
            .'ON project(archived_at)'
        );

        $this->addSql(<<<'SQL'
CREATE TABLE project_member (
    project_id BIGINT NOT NULL
        REFERENCES project(id)
        ON DELETE CASCADE,

    user_id BIGINT NOT NULL
        REFERENCES app_user(id)
        ON DELETE CASCADE,

    role VARCHAR(16) NOT NULL
        DEFAULT 'member',

    joined_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    PRIMARY KEY (
        project_id,
        user_id
    ),

    CONSTRAINT chk_project_member_role
        CHECK (
            role IN (
                'owner',
                'admin',
                'member'
            )
        )
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_project_member_user '
            .'ON project_member(user_id, project_id)'
        );

        $this->addSql(
            'CREATE INDEX idx_project_member_role '
            .'ON project_member(project_id, role)'
        );

        /*
         * At most one owner.
         *
         * Archived projects migrated from old
         * creator-less channels may deliberately
         * have no owner.
         */
        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX uniq_project_owner
ON project_member(project_id)
WHERE role = 'owner'
SQL);

        /*
         * Personal collections become projects.
         */
        $this->addSql(<<<'SQL'
INSERT INTO project (
    name,
    description,
    color,
    created_at,
    updated_at,
    legacy_collection_id
)
SELECT
    collection.name,
    '',
    collection.color,
    collection.created_at,
    collection.updated_at,
    collection.id
FROM note_collection collection
ORDER BY collection.id
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO project_member (
    project_id,
    user_id,
    role,
    joined_at
)
SELECT
    project.id,
    collection.user_id,
    'owner',
    collection.created_at
FROM project
INNER JOIN note_collection collection
    ON collection.id =
        project.legacy_collection_id
SQL);

        /*
         * Collaboration channels also become
         * projects. Closed channels are retained
         * as archived projects.
         */
        $this->addSql(<<<'SQL'
INSERT INTO project (
    name,
    description,
    color,
    image_url,
    created_at,
    updated_at,
    archived_at,
    legacy_channel_id
)
SELECT
    channel.name,
    channel.description,
    '#1A73E8',
    channel.profile_image_url,
    channel.created_at,
    channel.updated_at,
    channel.closed_at,
    channel.id
FROM channel
ORDER BY channel.id
SQL);

        /*
         * Preserve all channel memberships.
         * The creator becomes project owner.
         */
        $this->addSql(<<<'SQL'
INSERT INTO project_member (
    project_id,
    user_id,
    role,
    joined_at
)
SELECT
    project.id,
    member.user_id,
    CASE
        WHEN member.user_id =
            channel.creator_user_id
            THEN 'owner'
        WHEN member.role = 'admin'
            THEN 'admin'
        ELSE 'member'
    END,
    member.joined_at
FROM project
INNER JOIN channel
    ON channel.id =
        project.legacy_channel_id
INNER JOIN channel_member member
    ON member.channel_id =
        channel.id
SQL);

        /*
         * Historical data should already have
         * the creator in channel_member, but
         * preserve ownership even if it does not.
         */
        $this->addSql(<<<'SQL'
INSERT INTO project_member (
    project_id,
    user_id,
    role,
    joined_at
)
SELECT
    project.id,
    channel.creator_user_id,
    'owner',
    channel.created_at
FROM project
INNER JOIN channel
    ON channel.id =
        project.legacy_channel_id
WHERE channel.creator_user_id IS NOT NULL
ON CONFLICT (
    project_id,
    user_id
)
DO UPDATE
SET role = 'owner'
SQL);

        /*
         * Keep the old ownership/grouping columns
         * during the compatibility phase.
         */
        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD project_id BIGINT NULL
    REFERENCES project(id)
    ON DELETE SET NULL
SQL);

        $this->addSql(
            'CREATE INDEX idx_note_project '
            .'ON note(project_id)'
        );

        $this->addSql(
            'CREATE INDEX idx_note_project_updated '
            .'ON note(project_id, updated_at DESC, id DESC)'
        );

        /*
         * Personal collection notes.
         */
        $this->addSql(<<<'SQL'
UPDATE note
SET project_id = project.id
FROM project
WHERE note.collection_id =
        project.legacy_collection_id
  AND note.collection_id IS NOT NULL
SQL);

        /*
         * Shared channel notes.
         */
        $this->addSql(<<<'SQL'
UPDATE note
SET project_id = project.id
FROM project
WHERE note.channel_id =
        project.legacy_channel_id
  AND note.channel_id IS NOT NULL
SQL);

        /*
         * Every legacy grouping must now have
         * an equivalent Project.
         */
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM note_collection collection
        LEFT JOIN project
            ON project.legacy_collection_id =
                collection.id
        WHERE project.id IS NULL
    ) THEN
        RAISE EXCEPTION
            'One or more collections were not migrated to projects';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM channel
        LEFT JOIN project
            ON project.legacy_channel_id =
                channel.id
        WHERE project.id IS NULL
    ) THEN
        RAISE EXCEPTION
            'One or more channels were not migrated to projects';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM note
        WHERE collection_id IS NOT NULL
          AND project_id IS NULL
    ) THEN
        RAISE EXCEPTION
            'One or more collection notes were not migrated to projects';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM note
        WHERE channel_id IS NOT NULL
          AND project_id IS NULL
    ) THEN
        RAISE EXCEPTION
            'One or more channel notes were not migrated to projects';
    END IF;
END
$$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX IF EXISTS '
            .'idx_note_project_updated'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS '
            .'idx_note_project'
        );

        $this->addSql(
            'ALTER TABLE note '
            .'DROP COLUMN IF EXISTS project_id'
        );

        $this->addSql(
            'DROP TABLE IF EXISTS project_member'
        );

        $this->addSql(
            'DROP TABLE IF EXISTS project'
        );
    }
}
