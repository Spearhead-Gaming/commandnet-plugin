<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link service records back to the award/qualification/assignment that created them, so deleting the source can clean up its timeline entry.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_record ADD source_type VARCHAR(30) DEFAULT NULL, ADD source_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_service_record_source ON service_record (source_type, source_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_service_record_source ON service_record');
        $this->addSql('ALTER TABLE service_record DROP source_type, DROP source_id');
    }
}
