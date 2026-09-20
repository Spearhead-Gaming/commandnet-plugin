<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rank groups (promotion tracks) and Rank.group.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE rank_group (name VARCHAR(100) NOT NULL, id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `rank` ADD group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `rank` ADD CONSTRAINT FK_8879E8E5FE54D947 FOREIGN KEY (group_id) REFERENCES rank_group (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8879E8E5FE54D947 ON `rank` (group_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `rank` DROP FOREIGN KEY FK_8879E8E5FE54D947');
        $this->addSql('DROP INDEX IDX_8879E8E5FE54D947 ON `rank`');
        $this->addSql('ALTER TABLE `rank` DROP group_id');
        $this->addSql('DROP TABLE rank_group');
    }
}
