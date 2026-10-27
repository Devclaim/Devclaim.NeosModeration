<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates moderation cases and their append-only audit history.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on MySQL or MariaDB.',
        );

        $this->addSql('CREATE TABLE devclaim_moderation_case (
            identifier VARCHAR(36) NOT NULL,
            type_key VARCHAR(150) NOT NULL,
            status VARCHAR(16) NOT NULL,
            locale VARCHAR(35) NOT NULL,
            payload JSON NOT NULL,
            private_metadata JSON DEFAULT NULL,
            submitted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            decided_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            moderator_identifier VARCHAR(255) DEFAULT NULL,
            decision_reason LONGTEXT DEFAULT NULL,
            external_reference VARCHAR(500) DEFAULT NULL,
            version INT NOT NULL DEFAULT 1,
            INDEX moderation_status_submitted_idx (status, submitted_at),
            INDEX moderation_type_status_idx (type_key, status),
            PRIMARY KEY(identifier)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE devclaim_moderation_audit_entry (
            identifier VARCHAR(36) NOT NULL,
            moderation_case_id VARCHAR(36) NOT NULL,
            event VARCHAR(32) NOT NULL,
            actor_identifier VARCHAR(255) DEFAULT NULL,
            recorded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            context JSON NOT NULL,
            INDEX moderation_audit_case_idx (moderation_case_id, recorded_at),
            PRIMARY KEY(identifier),
            CONSTRAINT FK_MODERATION_AUDIT_CASE FOREIGN KEY (moderation_case_id)
                REFERENCES devclaim_moderation_case (identifier) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on MySQL or MariaDB.',
        );
        $this->addSql('DROP TABLE devclaim_moderation_audit_entry');
        $this->addSql('DROP TABLE devclaim_moderation_case');
    }
}
