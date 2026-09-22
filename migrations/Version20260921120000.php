<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deployments, and a leader, deployment and joiner cap to events for member-led patrols.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE deployment (name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE operation ADD max_participants INT DEFAULT NULL, ADD leader_id INT DEFAULT NULL, ADD deployment_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66D73154ED4 FOREIGN KEY (leader_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66D9DF4CE98 FOREIGN KEY (deployment_id) REFERENCES deployment (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1981A66D73154ED4 ON operation (leader_id)');
        $this->addSql('CREATE INDEX IDX_1981A66D9DF4CE98 ON operation (deployment_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation DROP FOREIGN KEY FK_1981A66D73154ED4');
        $this->addSql('ALTER TABLE operation DROP FOREIGN KEY FK_1981A66D9DF4CE98');
        $this->addSql('DROP INDEX IDX_1981A66D73154ED4 ON operation');
        $this->addSql('DROP INDEX IDX_1981A66D9DF4CE98 ON operation');
        $this->addSql('ALTER TABLE operation DROP max_participants, DROP leader_id, DROP deployment_id');
        $this->addSql('DROP TABLE deployment');
    }
}
