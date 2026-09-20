<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add enlistment applications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE enlistment_application (callsign VARCHAR(50) DEFAULT NULL, steam_id VARCHAR(30) DEFAULT NULL, motivation LONGTEXT NOT NULL, experience LONGTEXT DEFAULT NULL, availability VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, reviewed_at DATETIME DEFAULT NULL, decision_note LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, INDEX IDX_91B2A861A76ED395 (user_id), INDEX IDX_91B2A861FC6B21F1 (reviewed_by_id), INDEX IDX_91B2A8618B8E8428 (created_at), INDEX IDX_91B2A86143625D9F (updated_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE enlistment_application ADD CONSTRAINT FK_91B2A861A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE enlistment_application ADD CONSTRAINT FK_91B2A861FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enlistment_application DROP FOREIGN KEY FK_91B2A861A76ED395');
        $this->addSql('ALTER TABLE enlistment_application DROP FOREIGN KEY FK_91B2A861FC6B21F1');
        $this->addSql('DROP TABLE enlistment_application');
    }
}
