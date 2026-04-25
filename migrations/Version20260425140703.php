<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425140703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set default plan_id to "plan_free" for users with NULL plan_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE users SET plan_id = 'plan_free' WHERE plan_id IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE users SET plan_id = NULL WHERE plan_id = 'plan_free'");
    }
}
