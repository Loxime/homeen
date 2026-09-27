<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove retired empty note collections and image library schema.';
    }

    public function up(Schema $schema): void
    {
        /*
         * Never silently discard Collection or
         * Image content from another environment.
         */
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM note
        WHERE collection_id IS NOT NULL
    ) THEN
        RAISE EXCEPTION
            'Cannot remove Collections: notes still reference note.collection_id';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM note_collection
    ) THEN
        RAISE EXCEPTION
            'Cannot remove Collections: note_collection still contains rows';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM note_image
    ) THEN
        RAISE EXCEPTION
            'Cannot remove Images: note_image still contains rows';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM image_asset
    ) THEN
        RAISE EXCEPTION
            'Cannot remove Images: image_asset still contains rows';
    END IF;
END
$$
SQL);

        /*
         * Migration provenance from Collections
         * is no longer needed. Project data itself
         * is preserved.
         */
        $this->addSql(
            'ALTER TABLE project '
            .'DROP COLUMN legacy_collection_id'
        );

        $this->addSql(
            'DROP TABLE note_image'
        );

        $this->addSql(
            'DROP TABLE image_asset'
        );

        $this->addSql(
            'DROP INDEX IF EXISTS idx_note_collection'
        );

        $this->addSql(
            'ALTER TABLE note '
            .'DROP COLUMN collection_id'
        );

        $this->addSql(
            'DROP TABLE note_collection'
        );
    }

    public function down(Schema $schema): void
    {
        /*
         * Collection/Image rows were required
         * to be empty before up().
         *
         * Legacy Project provenance cannot be
         * reconstructed and therefore comes
         * back nullable.
         */
        $this->addSql(<<<'SQL'
CREATE TABLE note_collection (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL
        REFERENCES app_user(id)
        ON DELETE CASCADE,
    name VARCHAR(80) NOT NULL,
    color CHAR(7) NOT NULL
        DEFAULT '#1A73E8',
    created_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),
    CONSTRAINT chk_note_collection_name
        CHECK (
            char_length(trim(name))
            BETWEEN 1 AND 80
        ),
    CONSTRAINT chk_note_collection_color
        CHECK (
            color ~ '^#[0-9A-Fa-f]{6}$'
        )
)
SQL);

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX
    uniq_note_collection_user_name
ON note_collection (
    user_id,
    lower(name)
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_note_collection_user '
            .'ON note_collection(user_id)'
        );

        $this->addSql(<<<'SQL'
ALTER TABLE note
ADD collection_id BIGINT NULL
    REFERENCES note_collection(id)
    ON DELETE SET NULL
SQL);

        $this->addSql(
            'CREATE INDEX idx_note_collection '
            .'ON note(collection_id)'
        );

        $this->addSql(
            'ALTER TABLE project '
            .'ADD legacy_collection_id BIGINT NULL'
        );

        $this->addSql(<<<'SQL'
ALTER TABLE project
ADD CONSTRAINT chk_project_legacy_source
CHECK (
    NOT (
        legacy_collection_id IS NOT NULL
        AND legacy_channel_id IS NOT NULL
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
CREATE TABLE image_asset (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL
        REFERENCES app_user(id)
        ON DELETE CASCADE,
    stored_name VARCHAR(80) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(64) NOT NULL,
    size_bytes BIGINT NOT NULL,
    width INTEGER NULL,
    height INTEGER NULL,
    created_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),
    CONSTRAINT uniq_image_asset_stored_name
        UNIQUE (stored_name),
    CONSTRAINT chk_image_asset_size
        CHECK (
            size_bytes > 0
            AND size_bytes <= 8388608
        ),
    CONSTRAINT chk_image_asset_width
        CHECK (
            width IS NULL
            OR width > 0
        ),
    CONSTRAINT chk_image_asset_height
        CHECK (
            height IS NULL
            OR height > 0
        )
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_image_asset_user '
            .'ON image_asset(user_id, created_at DESC)'
        );

        $this->addSql(<<<'SQL'
CREATE TABLE note_image (
    note_id BIGINT NOT NULL
        REFERENCES note(id)
        ON DELETE CASCADE,
    image_id BIGINT NOT NULL
        REFERENCES image_asset(id)
        ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),
    PRIMARY KEY (
        note_id,
        image_id
    )
)
SQL);

        $this->addSql(
            'CREATE INDEX idx_note_image_image '
            .'ON note_image(image_id)'
        );
    }
}
