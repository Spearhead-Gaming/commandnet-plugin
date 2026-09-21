<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add specialties and SoldierProfile.specialty.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE specialty (name VARCHAR(100) NOT NULL, abbreviation VARCHAR(20) NOT NULL, description LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, role_id INT DEFAULT NULL, INDEX IDX_E066A6ECD60322AC (role_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE specialty ADD CONSTRAINT FK_E066A6ECD60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE soldier_profile ADD specialty_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE soldier_profile ADD CONSTRAINT FK_F799A4FE9A353316 FOREIGN KEY (specialty_id) REFERENCES specialty (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F799A4FE9A353316 ON soldier_profile (specialty_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE soldier_profile DROP FOREIGN KEY FK_F799A4FE9A353316');
        $this->addSql('DROP INDEX IDX_F799A4FE9A353316 ON soldier_profile');
        $this->addSql('ALTER TABLE soldier_profile DROP specialty_id');
        $this->addSql('ALTER TABLE specialty DROP FOREIGN KEY FK_E066A6ECD60322AC');
        $this->addSql('DROP TABLE specialty');
    }
}
