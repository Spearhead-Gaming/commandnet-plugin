<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Rank.role so a soldier holds the forumify Role tied to their current rank.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `rank` ADD role_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `rank` ADD CONSTRAINT FK_8879E8E5D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8879E8E5D60322AC ON `rank` (role_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `rank` DROP FOREIGN KEY FK_8879E8E5D60322AC');
        $this->addSql('DROP INDEX IDX_8879E8E5D60322AC ON `rank`');
        $this->addSql('ALTER TABLE `rank` DROP role_id');
    }
}
