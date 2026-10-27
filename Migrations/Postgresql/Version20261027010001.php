<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027010001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates moderation cases and their append-only audit history.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('CREATE TABLE devclaim_moderation_case (
            identifier VARCHAR(36) NOT NULL,
            type_key VARCHAR(150) NOT NULL,
            status VARCHAR(16) NOT NULL,
            locale VARCHAR(35) NOT NULL,
            payload JSON NOT NULL,
            private_metadata JSON DEFAULT NULL,
            submitted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            decided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            moderator_identifier VARCHAR(255) DEFAULT NULL,
            decision_reason TEXT DEFAULT NULL,
            external_reference VARCHAR(500) DEFAULT NULL,
            version INT NOT NULL DEFAULT 1,
            PRIMARY KEY(identifier)
        )');
        $this->addSql('COMMENT ON COLUMN devclaim_moderation_case.submitted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN devclaim_moderation_case.decided_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(
            'CREATE INDEX moderation_status_submitted_idx ON devclaim_moderation_case (status, submitted_at)',
        );
        $this->addSql('CREATE INDEX moderation_type_status_idx ON devclaim_moderation_case (type_key, status)');
        $this->addSql('CREATE TABLE devclaim_moderation_audit_entry (
            identifier VARCHAR(36) NOT NULL,
            moderation_case_id VARCHAR(36) NOT NULL,
            event VARCHAR(32) NOT NULL,
            actor_identifier VARCHAR(255) DEFAULT NULL,
            recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            context JSON NOT NULL,
            PRIMARY KEY(identifier),
            CONSTRAINT FK_MODERATION_AUDIT_CASE FOREIGN KEY (moderation_case_id)
                REFERENCES devclaim_moderation_case (identifier) ON DELETE CASCADE
        )');
        $this->addSql(
            'COMMENT ON COLUMN devclaim_moderation_audit_entry.recorded_at IS \'(DC2Type:datetime_immutable)\'',
        );
        $this->addSql(
            'CREATE INDEX moderation_audit_case_idx'
            . ' ON devclaim_moderation_audit_entry (moderation_case_id, recorded_at)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );
        $this->addSql('DROP TABLE devclaim_moderation_audit_entry');
        $this->addSql('DROP TABLE devclaim_moderation_case');
    }
}
