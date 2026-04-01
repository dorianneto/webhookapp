<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260401165059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE delivery_attempts (
              id VARCHAR(255) NOT NULL,
              event_id VARCHAR(255) NOT NULL,
              endpoint_id VARCHAR(255) NOT NULL,
              attempt_number INT NOT NULL,
              status_code INT DEFAULT NULL,
              response_body VARCHAR(500) NOT NULL,
              duration_ms INT NOT NULL,
              attempted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_da_event_endpoint ON delivery_attempts (event_id, endpoint_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE endpoints (
              id VARCHAR(255) NOT NULL,
              source_id VARCHAR(255) NOT NULL,
              url VARCHAR(255) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE event_endpoint_deliveries (
              id VARCHAR(255) NOT NULL,
              event_id VARCHAR(255) NOT NULL,
              endpoint_id VARCHAR(255) NOT NULL,
              status VARCHAR(255) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_eed_event_id ON event_endpoint_deliveries (event_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_eed_event_endpoint ON event_endpoint_deliveries (event_id, endpoint_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE events (
              id VARCHAR(255) NOT NULL,
              source_id VARCHAR(255) NOT NULL,
              method VARCHAR(255) NOT NULL,
              headers JSON NOT NULL,
              body TEXT NOT NULL,
              status VARCHAR(255) NOT NULL,
              received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_events_source_received ON events (source_id, received_at DESC)');
        $this->addSql(<<<'SQL'
            CREATE TABLE sources (
              id VARCHAR(255) NOT NULL,
              user_id VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              inbound_uuid UUID NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D25D65F27C608550 ON sources (inbound_uuid)');
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
              id VARCHAR(255) NOT NULL,
              email VARCHAR(255) NOT NULL,
              password_hash VARCHAR(255) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE delivery_attempts');
        $this->addSql('DROP TABLE endpoints');
        $this->addSql('DROP TABLE event_endpoint_deliveries');
        $this->addSql('DROP TABLE events');
        $this->addSql('DROP TABLE sources');
        $this->addSql('DROP TABLE users');
    }
}
