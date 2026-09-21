<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add documents and ServiceRecord.document.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE document (name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, content LONGTEXT NOT NULL, id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE service_record ADD document_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE service_record ADD CONSTRAINT FK_A5F39AA7C33F7837 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A5F39AA7C33F7837 ON service_record (document_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_record DROP FOREIGN KEY FK_A5F39AA7C33F7837');
        $this->addSql('DROP INDEX IDX_A5F39AA7C33F7837 ON service_record');
        $this->addSql('ALTER TABLE service_record DROP document_id');
        $this->addSql('DROP TABLE document');
    }
}
