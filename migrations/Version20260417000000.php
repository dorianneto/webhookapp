<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add foreign key constraints with cascade/restrict delete rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sources ADD CONSTRAINT fk_sources_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE endpoints ADD CONSTRAINT fk_endpoints_source_id FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT fk_events_source_id FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_endpoint_deliveries ADD CONSTRAINT fk_eed_event_id FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_endpoint_deliveries ADD CONSTRAINT fk_eed_endpoint_id FOREIGN KEY (endpoint_id) REFERENCES endpoints (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_attempts ADD CONSTRAINT fk_da_event_id FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_attempts ADD CONSTRAINT fk_da_endpoint_id FOREIGN KEY (endpoint_id) REFERENCES endpoints (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_attempts DROP CONSTRAINT fk_da_endpoint_id');
        $this->addSql('ALTER TABLE delivery_attempts DROP CONSTRAINT fk_da_event_id');
        $this->addSql('ALTER TABLE event_endpoint_deliveries DROP CONSTRAINT fk_eed_endpoint_id');
        $this->addSql('ALTER TABLE event_endpoint_deliveries DROP CONSTRAINT fk_eed_event_id');
        $this->addSql('ALTER TABLE events DROP CONSTRAINT fk_events_source_id');
        $this->addSql('ALTER TABLE endpoints DROP CONSTRAINT fk_endpoints_source_id');
        $this->addSql('ALTER TABLE sources DROP CONSTRAINT fk_sources_user_id');
    }
}
