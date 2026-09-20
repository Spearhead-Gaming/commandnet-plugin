<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920250000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rosters, named sets of units shown as tabs on the roster page.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE roster (name VARCHAR(150) NOT NULL, description LONGTEXT NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, position INT NOT NULL, INDEX IDX_60B9ADF98B8E8428 (created_at), INDEX IDX_60B9ADF943625D9F (updated_at), INDEX IDX_60B9ADF9462CE4F5 (position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE roster_unit (roster_id INT NOT NULL, unit_id INT NOT NULL, INDEX IDX_D4C9218475404483 (roster_id), INDEX IDX_D4C92184F8BD700D (unit_id), PRIMARY KEY (roster_id, unit_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE roster_unit ADD CONSTRAINT FK_D4C9218475404483 FOREIGN KEY (roster_id) REFERENCES roster (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE roster_unit ADD CONSTRAINT FK_D4C92184F8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE roster_unit');
        $this->addSql('DROP TABLE roster');
    }
}
