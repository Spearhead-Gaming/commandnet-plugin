<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917052546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a Steam ID column to soldier profiles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE soldier_profile ADD steam_id VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE soldier_profile DROP steam_id');
    }
}
