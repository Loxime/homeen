<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add private image library and note image attachments.';
    }

    public function up(Schema $schema): void
    {
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

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP TABLE IF EXISTS note_image'
        );

        $this->addSql(
            'DROP TABLE IF EXISTS image_asset'
        );
    }
}
