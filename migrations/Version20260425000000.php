<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quota plans: plans table, request_usage table, and plan_id on users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plans (
            id VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            monthly_request_limit INT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_plans_name ON plans (name)');

        $this->addSql("INSERT INTO plans (id, name, monthly_request_limit, created_at) VALUES ('plan_free', 'free', 10000, NOW())");
        $this->addSql("INSERT INTO plans (id, name, monthly_request_limit, created_at) VALUES ('plan_pro', 'pro', 500000, NOW())");

        $this->addSql('ALTER TABLE users ADD plan_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_users_plan_id ON users (plan_id)');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_users_plan_id FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE request_usage (
            id SERIAL NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            bucket_date DATE NOT NULL,
            count INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_request_usage_user_date ON request_usage (user_id, bucket_date)');
        $this->addSql('CREATE INDEX IDX_request_usage_user_date ON request_usage (user_id, bucket_date)');
        $this->addSql('ALTER TABLE request_usage ADD CONSTRAINT FK_request_usage_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE request_usage DROP CONSTRAINT FK_request_usage_user_id');
        $this->addSql('DROP TABLE request_usage');
        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_users_plan_id');
        $this->addSql('DROP INDEX IDX_users_plan_id');
        $this->addSql('ALTER TABLE users DROP plan_id');
        $this->addSql('DROP TABLE plans');
    }
}
