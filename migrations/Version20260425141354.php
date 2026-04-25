<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425141354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update monthly_request_limit for free and pro plans';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE plans SET monthly_request_limit = 50 WHERE id = 'plan_free'");
        $this->addSql("UPDATE plans SET monthly_request_limit = 100 WHERE id = 'plan_pro'");
    }

    public function down(Schema $schema): void
    {
        // No need to revert the monthly_request_limit changes as they are not critical and can be easily updated if needed.
    }
}
