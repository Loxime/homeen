<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926000700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add project invitations and migrate pending channel invitations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE project_invitation (
    id BIGSERIAL PRIMARY KEY,

    project_id BIGINT NOT NULL
        REFERENCES project(id)
        ON DELETE CASCADE,

    invited_user_id BIGINT NOT NULL
        REFERENCES app_user(id)
        ON DELETE CASCADE,

    invited_by_user_id BIGINT NULL
        REFERENCES app_user(id)
        ON DELETE SET NULL,

    created_at TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    CONSTRAINT uniq_project_invitation
        UNIQUE (
            project_id,
            invited_user_id
        ),

    CONSTRAINT chk_project_invitation_users
        CHECK (
            invited_by_user_id IS NULL
            OR invited_user_id
                <> invited_by_user_id
        )
)
SQL);

        $this->addSql(
            'CREATE INDEX '
            .'idx_project_invitation_user '
            .'ON project_invitation('
            .'invited_user_id, created_at DESC)'
        );

        $this->addSql(
            'CREATE INDEX '
            .'idx_project_invitation_project '
            .'ON project_invitation(project_id)'
        );

        /*
         * Preserve pending invitations from
         * active legacy channels.
         */
        $this->addSql(<<<'SQL'
INSERT INTO project_invitation (
    project_id,
    invited_user_id,
    invited_by_user_id,
    created_at
)
SELECT
    project.id,
    invitation.invited_user_id,
    invitation.invited_by_user_id,
    invitation.created_at
FROM channel_invitation invitation
INNER JOIN project
    ON project.legacy_channel_id =
        invitation.channel_id
WHERE project.archived_at IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM project_member member
      WHERE member.project_id =
                project.id
        AND member.user_id =
                invitation.invited_user_id
  )
ON CONFLICT (
    project_id,
    invited_user_id
)
DO NOTHING
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP TABLE IF EXISTS '
            .'project_invitation'
        );
    }
}
